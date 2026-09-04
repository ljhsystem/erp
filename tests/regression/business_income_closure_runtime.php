<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require PROJECT_ROOT . '/core/Storage.php';

use App\Models\Institution\BusinessIncomeModel;
use App\Models\User\ApprovalRequestModel;
use App\Services\Auth\AuthSessionService;
use App\Services\Institution\BusinessIncomeCalculationService;
use App\Services\Institution\BusinessIncomeTransactionGenerationService;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

$db = DbPdo::conn();
$user = $db->query("SELECT id,username,role_id FROM auth_users WHERE approved=1 AND is_active=1 ORDER BY created_at,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) throw new RuntimeException('격리 Runtime 검증용 활성 사용자가 없습니다.');
(new AuthSessionService())->createLoginSession($user + ['role_key' => null, 'role_name' => null]);
$actor = 'USER:' . $user['id'];
$client = $db->query("SELECT id,client_name FROM system_clients WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no,id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$client) throw new RuntimeException('격리 Runtime 검증용 활성 거래처가 없습니다.');
$templateId = (string) $db->query("SELECT id FROM user_approval_templates WHERE document_type='BUSINESS_INCOME' AND is_active=1 ORDER BY sort_no,id LIMIT 1")->fetchColumn();
if ($templateId === '') throw new RuntimeException('사업소득 결재양식이 없습니다.');
$businessUnit = (string) $db->query("SELECT code FROM system_codes WHERE code_group='BUSINESS_UNIT' AND is_active=1 ORDER BY sort_no,code LIMIT 1")->fetchColumn();
if ($businessUnit === '') throw new RuntimeException('격리 Runtime 검증용 사업구분이 없습니다.');

$insert = static function (PDO $db, string $table, array $row): void {
    $columns = array_keys($row);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')';
    $statement = $db->prepare($sql);
    foreach ($row as $key => $value) $statement->bindValue(':' . $key, $value);
    $statement->execute();
};
$counts = static function (PDO $db, string $documentId): array {
    $queries = [
        'evidence' => "SELECT COUNT(*) FROM ledger_evidence_business_income WHERE source_business_income_id=:id",
        'raw_lines' => "SELECT COUNT(*) FROM ledger_evidence_business_income_raw_lines r JOIN ledger_evidence_business_income e ON e.id=r.evidence_id WHERE e.source_business_income_id=:id",
        'work_lines' => "SELECT COUNT(*) FROM ledger_evidence_business_income_work_lines r JOIN ledger_evidence_business_income e ON e.id=r.evidence_id WHERE e.source_business_income_id=:id",
        'transactions' => "SELECT COUNT(*) FROM institution_business_income_artifact_links WHERE business_income_id=:id AND transaction_id IS NOT NULL",
        'items' => "SELECT COUNT(*) FROM ledger_transaction_items i JOIN institution_business_income_artifact_links a ON a.transaction_id=i.transaction_id WHERE a.business_income_id=:id",
        'settlements' => "SELECT COUNT(*) FROM ledger_transaction_settlements s JOIN institution_business_income_artifact_links a ON a.transaction_id=s.transaction_id WHERE a.business_income_id=:id",
        'links' => "SELECT COUNT(*) FROM ledger_evidence_links l JOIN institution_business_income_artifact_links a ON a.evidence_id=l.evidence_id AND a.transaction_id=l.target_id WHERE a.business_income_id=:id AND l.target_type='TRANSACTION' AND l.deleted_at IS NULL",
        'closures' => "SELECT COUNT(*) FROM institution_business_income_closures WHERE business_income_id=:id",
    ];
    $result = [];
    foreach ($queries as $key => $sql) {
        $statement = $db->prepare($sql); $statement->execute([':id' => $documentId]); $result[$key] = (int) $statement->fetchColumn();
    }
    return $result;
};

$run = static function (PDO $db, callable $insert, callable $counts, string $actor, array $user, array $client, string $templateId, string $businessUnit, ?string $failurePoint): array {
    $documentId = UuidHelper::generate(); $groupId = UuidHelper::generate(); $itemId = UuidHelper::generate();
    $profileId = UuidHelper::generate(); $revisionId = UuidHelper::generate(); $requestId = UuidHelper::generate();
    $paymentDate = date('Y-m-d');
    $calculated = (new BusinessIncomeCalculationService($db))->calculate($paymentDate, 1000000);
    $db->beginTransaction();
    try {
        $insert($db, 'system_client_tax_profiles', ['id'=>$profileId,'client_id'=>$client['id'],'effective_from'=>$paymentDate,'taxpayer_entity_type'=>'INDIVIDUAL','residency_status'=>'RESIDENT','income_recipient_type'=>'BUSINESS_INCOME','withholding_policy_code'=>'BUSINESS_INCOME_WITHHOLDING','verification_status'=>'VERIFIED','verified_at'=>date('Y-m-d H:i:s'),'verified_by'=>$actor,'created_by'=>$actor,'updated_by'=>$actor]);
        $insert($db, 'institution_business_incomes', ['id'=>$documentId,'income_year_month'=>date('Y-m'),'title'=>'사업소득 P1 격리 Runtime 검증','description'=>'Rollback fixture','created_by'=>$actor,'updated_by'=>$actor]);
        $insert($db, 'institution_business_income_groups', ['id'=>$groupId,'business_income_id'=>$documentId,'business_unit'=>$businessUnit,'sort_no'=>1,'created_by'=>$actor,'updated_by'=>$actor]);
        $insert($db, 'institution_business_income_items', ['id'=>$itemId,'group_id'=>$groupId,'client_id'=>$client['id'],'client_tax_profile_id'=>$profileId,'transaction_date'=>$paymentDate,'service_type_code'=>'GENERAL_SERVICE','service_description'=>'격리 검증 용역','gross_payment_amount'=>$calculated['gross_payment_amount'],'income_tax_amount'=>$calculated['income_tax_amount'],'local_income_tax_amount'=>$calculated['local_income_tax_amount'],'total_deduction_amount'=>$calculated['total_deduction_amount'],'net_payment_amount'=>$calculated['net_payment_amount'],'recipient_tax_snapshot_json'=>json_encode(['client_name'=>$client['client_name']],JSON_UNESCAPED_UNICODE),'sort_no'=>1,'created_by'=>$actor,'updated_by'=>$actor]);
        $insert($db,'institution_business_income_work_lines',['id'=>UuidHelper::generate(),'business_income_item_id'=>$itemId,'item_name'=>'설계 용역','item_specification'=>'기본설계','item_unit_name'=>'식','item_quantity'=>1,'item_unit_price'=>700000,'calculated_amount'=>700000,'adjustment_amount'=>0,'adjustment_reason'=>null,'final_amount'=>700000,'sort_no'=>1,'created_by'=>$actor,'updated_by'=>$actor]);
        $insert($db,'institution_business_income_work_lines',['id'=>UuidHelper::generate(),'business_income_item_id'=>$itemId,'item_name'=>'현장 자문','item_specification'=>'기술 검토','item_unit_name'=>'시간','item_quantity'=>10,'item_unit_price'=>30000,'calculated_amount'=>300000,'adjustment_amount'=>0,'adjustment_reason'=>null,'final_amount'=>300000,'sort_no'=>2,'created_by'=>$actor,'updated_by'=>$actor]);
        $insert($db, 'institution_business_income_calculation_revisions', ['id'=>$revisionId,'business_income_id'=>$documentId,'revision_no'=>1,'revision_status'=>'CONFIRMED','calculation_date'=>$paymentDate,'policy_status'=>'READY','source_hash'=>hash('sha256',$documentId),'calculated_at'=>date('Y-m-d H:i:s'),'calculated_by'=>$actor,'created_by'=>$actor]);
        foreach ($calculated['lines'] as $line) $insert($db, 'institution_business_income_calculation_lines', ['id'=>UuidHelper::generate(),'calculation_revision_id'=>$revisionId,'business_income_item_id'=>$itemId,'created_by'=>$actor]+$line);
        $db->prepare("UPDATE institution_business_incomes SET current_calculation_revision_id=:revision,calculation_status='CALCULATED',document_status='PENDING',approval_status='PENDING' WHERE id=:id")->execute([':revision'=>$revisionId,':id'=>$documentId]);
        (new ApprovalRequestModel($db))->create(['id'=>$requestId,'sort_no'=>(new ApprovalRequestModel($db))->nextSortNo(),'template_id'=>$templateId,'document_type'=>'BUSINESS_INCOME','document_id'=>$documentId,'requester_id'=>$user['id'],'status'=>'approved','current_step'=>1,'is_active'=>1,'created_by'=>$actor]);
        $db->prepare('UPDATE institution_business_incomes SET current_approval_request_id=:request WHERE id=:id')->execute([':request'=>$requestId,':id'=>$documentId]);
        $injector = $failurePoint === null ? null : static function (string $point) use ($failurePoint): void { if ($point === $failurePoint) throw new RuntimeException('의도된 원자성 검증 실패'); };
        $service = new BusinessIncomeTransactionGenerationService($db, $injector);
        $first = $service->generate($documentId, $requestId, $actor);
        $amountStatement = $db->prepare("SELECT e.raw_gross_payment_amount,e.raw_total_deduction_amount,e.raw_net_payment_amount,t.transaction_supply_amount,t.transaction_settlement_amount,t.transaction_final_amount FROM institution_business_income_artifact_links a JOIN ledger_evidence_business_income e ON e.id=a.evidence_id JOIN ledger_transactions t ON t.id=a.transaction_id WHERE a.business_income_id=:id");
        $amountStatement->execute([':id'=>$documentId]);
        $amounts = $amountStatement->fetch(PDO::FETCH_ASSOC) ?: [];
        $settlementStatement = $db->prepare("SELECT s.settlement_type,s.amount_sign,s.amount FROM ledger_transaction_settlements s JOIN institution_business_income_artifact_links a ON a.transaction_id=s.transaction_id WHERE a.business_income_id=:id ORDER BY s.sort_no,s.id");
        $settlementStatement->execute([':id'=>$documentId]);
        $settlements = $settlementStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $beforeReplay = $counts($db, $documentId);
        $second = $service->generate($documentId, $requestId, $actor);
        $afterReplay = $counts($db, $documentId);
        $db->rollBack();
        return ['first'=>$first,'second'=>$second,'amounts'=>$amounts,'settlements'=>$settlements,'counts'=>$beforeReplay,'replay_unchanged'=>$beforeReplay===$afterReplay,'persisted_after_rollback'=>$counts($db,$documentId)];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($failurePoint === null) throw $e;
        return ['failure_message'=>$e->getMessage(),'persisted_after_rollback'=>$counts($db,$documentId)];
    }
};

$successCase = $run($db,$insert,$counts,$actor,$user,$client,$templateId,$businessUnit,null);
$failurePoints = [
    'business_income_closure.after_raw_line',
    'business_income_closure.after_evidence',
    'transaction.after_header',
    'transaction.after_items',
    'transaction.after_settlements',
    'transaction.after_links',
    'business_income_closure.after_link',
    'business_income_closure.after_artifact_registry',
    'business_income_closure.before_closure_complete',
];
$failureCases=[];
foreach($failurePoints as $failurePoint){
    $failureCases[$failurePoint]=$run($db,$insert,$counts,$actor,$user,$client,$templateId,$businessUnit,$failurePoint);
}
$zero = ['evidence'=>0,'raw_lines'=>0,'work_lines'=>0,'transactions'=>0,'items'=>0,'settlements'=>0,'links'=>0,'closures'=>0];
$success = $successCase['counts'] === ['evidence'=>1,'raw_lines'=>3,'work_lines'=>2,'transactions'=>1,'items'=>2,'settlements'=>2,'links'=>1,'closures'=>1]
    && $successCase['replay_unchanged'] && $successCase['second']['status'] === 'ALREADY_PROCESSED'
    && $successCase['persisted_after_rollback'] === $zero
    && array_reduce($failureCases,static fn(bool $passed,array $case):bool=>$passed&&$case['persisted_after_rollback']===$zero,true);
echo json_encode(['success'=>$success,'success_case'=>$successCase,'failure_cases'=>$failureCases],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($success ? 0 : 1);
