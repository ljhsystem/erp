<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Services\Institution\DailyEmploymentIncomeService;
use Core\Session;

$documentId = 'e8650425-ef60-4bbb-bd5e-88deeeff7f48';
$originalItemId = '294ef269-185c-4dd8-8d59-b5583669a9bf';
$db = Core\Database::getInstance()->getConnection();
$service = new DailyEmploymentIncomeService($db);

$hashRows = static function (PDO $connection, string $sql, array $params = []): string {
    $statement = $connection->prepare($sql);
    $statement->execute($params);
    return hash('sha256', json_encode($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};
$auditSql = 'SELECT id,migration_id,daily_employment_income_line_id,previous_snapshot,new_snapshot,decision_rule_code,decision_basis_id,payload_hash,verification_status_code,executed_at,executed_by FROM institution_daily_employment_income_line_backfill_audits ORDER BY id';
$otherQueries = [
    'headers' => 'SELECT * FROM institution_daily_employment_incomes WHERE id<>:document ORDER BY id',
    'groups' => 'SELECT g.* FROM institution_daily_employment_income_groups g WHERE g.daily_employment_income_id<>:document ORDER BY g.id',
    'items' => 'SELECT i.* FROM institution_daily_employment_income_items i JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id WHERE g.daily_employment_income_id<>:document ORDER BY i.id',
    'workdays' => 'SELECT w.* FROM institution_daily_employment_income_workdays w JOIN institution_daily_employment_income_items i ON i.id=w.daily_employment_income_item_id JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id WHERE g.daily_employment_income_id<>:document ORDER BY w.id',
    'lines' => 'SELECT l.* FROM institution_daily_employment_income_lines l JOIN institution_daily_employment_income_items i ON i.id=l.daily_employment_income_item_id JOIN institution_daily_employment_income_groups g ON g.id=i.daily_employment_income_group_id WHERE g.daily_employment_income_id<>:document ORDER BY l.id',
];
$otherHashes = static function () use ($db, $hashRows, $otherQueries, $documentId): array {
    $result = [];
    foreach ($otherQueries as $key => $sql) $result[$key] = $hashRows($db, $sql, ['document'=>$documentId]);
    return $result;
};

$detailBefore = $service->detail($documentId)['data'];
$header = $detailBefore['header'];
if (($header['status_code'] ?? null) !== 'DRAFT') throw new RuntimeException('대상 문서가 DRAFT가 아닙니다.');
if ((string)($detailBefore['groups'][0]['items'][0]['id'] ?? '') !== $originalItemId) throw new RuntimeException('대상 Item 연결이 변경됐습니다.');
$actor = (string)$header['updated_by'];
if (!preg_match('/^USER:([a-f0-9-]{36})$/i', $actor, $match)) throw new RuntimeException('대상 문서 Actor를 확인할 수 없습니다.');
Session::start(30);
$_SESSION['user'] = ['id'=>$match[1]];
$_SESSION['auth_state'] = ['user_id'=>$match[1], 'status'=>'NORMAL'];

$groups = [];
foreach ($detailBefore['groups'] as $storedGroup) {
    $items = [];
    foreach ($storedGroup['items'] as $storedItem) {
        $workdays = [];
        foreach ($storedItem['workdays'] as $storedDay) {
            $workdays[] = [
                'work_date'=>$storedDay['work_date'],
                'actual_work_minutes'=>(int)$storedDay['actual_work_minutes'],
                'daily_rate_amount'=>(float)$storedDay['daily_rate_amount'],
                'taxable_additional_amount'=>(float)$storedDay['allowance_amount'],
                'non_taxable_additional_amount'=>(float)$storedDay['non_taxable_amount'],
                'non_taxable_reason'=>$storedDay['non_taxable_reason'],
                'calculation_note'=>$storedDay['calculation_note'],
            ];
        }
        $items[] = [
            'id'=>$storedItem['id'],
            'worker_client_id'=>$storedItem['worker_client_id'],
            'work_type_code'=>$storedItem['work_type_code'],
            'work_description'=>$storedItem['work_description'],
            'workdays'=>$workdays,
        ];
    }
    $groups[] = [
        'business_unit'=>$storedGroup['business_unit'],
        'project_id'=>$storedGroup['project_id'],
        'work_team_id'=>$storedGroup['work_team_id'],
        'work_description'=>$storedGroup['work_description'],
        'employment_insurance_application_status_code'=>$storedGroup['employment_insurance_application_status_code'],
        'employment_insurance_decision_reason'=>$storedGroup['employment_insurance_decision_reason'],
        'employment_insurance_decision_source_code_id'=>$storedGroup['employment_insurance_decision_source_code_id'],
        'industrial_accident_application_status_code'=>$storedGroup['industrial_accident_application_status_code'],
        'industrial_accident_decision_reason'=>$storedGroup['industrial_accident_decision_reason'],
        'industrial_accident_decision_source_code_id'=>$storedGroup['industrial_accident_decision_source_code_id'],
        'items'=>$items,
    ];
}
$payload = [
    'id'=>$documentId,
    'request_key'=>'daily-closure-save-20260831-insurance-integration-v1',
    'income_year_month'=>$header['income_year_month'],
    'payment_date'=>$header['payment_date'],
    'document_title'=>$header['document_title'],
    'description'=>$header['description'],
    'memo'=>$header['memo'],
    'groups'=>$groups,
];

$extract = static function (array $calculation): array {
    $item = $calculation['groups'][0]['items'][0];
    $lines = [];
    foreach ($item['lines'] as $line) {
        $key = ($line['line_type_code'] ?? '') . ':' . ($line['line_code'] ?? '');
        if (!in_array($line['line_code'] ?? '', ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'EMPLOYMENT_INSURANCE_VOCATIONAL', 'INDUSTRIAL_ACCIDENT_INSURANCE'], true)) continue;
        $lines[$key] = [
            'application_status_code'=>$line['application_status_code'] ?? null,
            'eligibility_status_code'=>$line['eligibility_result']['status'] ?? null,
            'calculation_basis_amount'=>$line['calculation_basis_amount'] ?? null,
            'calculation_rate'=>$line['calculation_rate'] ?? null,
            'calculation_before_rounding'=>$line['calculation_before_rounding'] ?? null,
            'rounding_method_code'=>$line['rounding_method_code'] ?? null,
            'rounding_unit'=>$line['rounding_unit'] ?? null,
            'calculated_amount'=>$line['calculated_amount'] ?? null,
            'final_amount'=>$line['final_amount'] ?? null,
            'adjustment_amount'=>$line['adjustment_amount'] ?? null,
            'adjustment_reason'=>$line['adjustment_reason'] ?? null,
        ];
    }
    return ['summary'=>$item['summary'], 'lines'=>$lines, 'insurance_preflight'=>$calculation['insurance_preflight']];
};
$assertExpected = static function (array $result): void {
    $expectedSummary = ['total_work_days'=>5, 'total_gross_amount'=>452940, 'total_deduction_amount'=>2940, 'total_net_payment_amount'=>450000, 'total_employer_burden_amount'=>20820];
    foreach ($expectedSummary as $key => $value) if ((float)($result['summary'][$key] ?? -1) !== (float)$value) throw new RuntimeException('Preview 합계가 승인값과 다릅니다: ' . $key);
    foreach (['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE'] as $code) {
        $line = $result['lines']['DEDUCTION:' . $code] ?? [];
        if (($line['application_status_code'] ?? null) !== 'EXCLUDED' || ($line['eligibility_status_code'] ?? null) !== 'NOT_ELIGIBLE' || (float)($line['final_amount'] ?? -1) !== 0.0) throw new RuntimeException($code . ' Preview가 승인값과 다릅니다.');
    }
    $employment = $result['lines']['DEDUCTION:EMPLOYMENT_INSURANCE'] ?? [];
    if ((float)($employment['calculation_basis_amount'] ?? 0) !== 452940.0 || (float)($employment['calculation_rate'] ?? 0) !== 0.0065 || (float)($employment['calculation_before_rounding'] ?? 0) !== 2944.11 || (float)($employment['calculated_amount'] ?? 0) !== 2940.0 || (float)($employment['final_amount'] ?? 0) !== 2940.0 || !empty($employment['adjustment_reason'])) throw new RuntimeException('고용보험 Preview가 승인값과 다릅니다.');
    if (($result['insurance_preflight']['status_code'] ?? null) !== 'CALCULATED') throw new RuntimeException('보험 Preview가 저장 가능 상태가 아닙니다.');
};

$before = [
    'audit_count'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_line_backfill_audits')->fetchColumn(),
    'audit_hash'=>$hashRows($db, $auditSql),
    'other_document_hashes'=>$otherHashes(),
    'calculation_revision_count'=>(int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_calculation_revisions WHERE daily_employment_income_id=" . $db->quote($documentId))->fetchColumn(),
    'calculation_result_count'=>(int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results result_row JOIN institution_daily_employment_income_calculation_revisions revision ON revision.id=result_row.calculation_revision_id WHERE revision.daily_employment_income_id=" . $db->quote($documentId))->fetchColumn(),
    'existing_result_hash'=>$hashRows($db, "SELECT * FROM institution_daily_employment_income_calculation_results WHERE id IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f') ORDER BY id"),
];
if ($before['audit_count'] !== 32 || $before['calculation_revision_count'] !== 1 || $before['calculation_result_count'] !== 3) throw new RuntimeException('운영 저장 직전 기준선이 다릅니다.');

$preview = $extract($service->calculate($payload)['data']);
$assertExpected($preview);
$save = $service->save($payload);
$detailAfter = $service->detail($documentId)['data'];
$recalculation = $extract($service->calculate($payload)['data']);
$assertExpected($recalculation);
if ($preview !== $recalculation) throw new RuntimeException('동일 입력 재계산 결과가 최초 Preview와 다릅니다.');
$idempotent = $service->save($payload);
$preflight = $service->submissionPreflight($documentId)['data'];

$after = [
    'audit_count'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_line_backfill_audits')->fetchColumn(),
    'audit_hash'=>$hashRows($db, $auditSql),
    'other_document_hashes'=>$otherHashes(),
    'calculation_revision_count'=>(int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_calculation_revisions WHERE daily_employment_income_id=" . $db->quote($documentId))->fetchColumn(),
    'calculation_result_count'=>(int)$db->query("SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results result_row JOIN institution_daily_employment_income_calculation_revisions revision ON revision.id=result_row.calculation_revision_id WHERE revision.daily_employment_income_id=" . $db->quote($documentId))->fetchColumn(),
    'line_count'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn(),
    'existing_result_hash'=>$hashRows($db, "SELECT * FROM institution_daily_employment_income_calculation_results WHERE id IN('d4b2ce27-73c0-4d93-9286-523bc286560a','4992fec8-d2a5-4618-ac31-604eab26dde8','cd5820dc-849c-45fd-a7c2-7e7e9157212f') ORDER BY id"),
];
if ($before['audit_count'] !== $after['audit_count'] || $before['audit_hash'] !== $after['audit_hash']) throw new RuntimeException('Audit Snapshot이 변경됐습니다.');
if ($before['other_document_hashes'] !== $after['other_document_hashes']) throw new RuntimeException('다른 일용근로소득 문서가 변경됐습니다.');
if ($before['existing_result_hash'] !== $after['existing_result_hash']) throw new RuntimeException('기존 계산 Result 3건이 변경됐습니다.');
if ($after['calculation_revision_count'] !== 2 || $after['calculation_result_count'] !== 6) throw new RuntimeException('신규 계산 Revision/Result가 정확히 append되지 않았습니다.');

$storedItem = $detailAfter['groups'][0]['items'][0];
$storedLines = [];
foreach ($storedItem['lines'] as $line) {
    if (!in_array($line['line_code'] ?? '', ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'EMPLOYMENT_INSURANCE_VOCATIONAL', 'INDUSTRIAL_ACCIDENT_INSURANCE'], true)) continue;
    $storedLines[($line['line_type_code'] ?? '') . ':' . ($line['line_code'] ?? '')] = array_intersect_key($line, array_flip(['application_status_code', 'calculation_basis_amount', 'calculation_rate', 'calculation_before_rounding', 'rounding_method_code', 'rounding_unit', 'calculated_amount', 'final_amount', 'adjustment_reason']));
}

echo json_encode([
    'success'=>true,
    'document_id'=>$documentId,
    'original_item_id'=>$originalItemId,
    'stored_item_id'=>$storedItem['id'],
    'before'=>$before,
    'preview'=>$preview,
    'save'=>$save,
    'detail_api'=>['header'=>array_intersect_key($detailAfter['header'], array_flip(['status_code', 'total_work_days', 'total_gross_amount', 'total_deduction_amount', 'total_net_payment_amount', 'total_employer_burden_amount'])), 'workday_count'=>count($storedItem['workdays']), 'lines'=>$storedLines],
    'same_input_recalculation_matches'=>true,
    'idempotent_save'=>$idempotent,
    'preflight'=>$preflight,
    'after'=>$after,
    'audit_unchanged'=>true,
    'other_documents_changed'=>0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
