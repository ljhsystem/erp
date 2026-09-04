<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Database.php';
require_once PROJECT_ROOT . '/core/DbPdo.php';

use App\Models\Institution\RegularEmploymentIncomeModel;
use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;

function idempotencyAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$operating = DbPdo::conn();
$source = (string) $operating->query('SELECT DATABASE()')->fetchColumn();
$database = 'codex_regular_income_idempotency_' . bin2hex(random_bytes(4));
idempotencyAssert((bool) preg_match('/^codex_regular_income_idempotency_[0-9a-f]{8}$/', $database), '격리 DB 이름 검증 실패');
$operatingCounts = [
    'headers' => (int) $operating->query('SELECT COUNT(*) FROM institution_regular_employment_incomes')->fetchColumn(),
    'items' => (int) $operating->query('SELECT COUNT(*) FROM institution_regular_employment_income_items')->fetchColumn(),
    'lines' => (int) $operating->query('SELECT COUNT(*) FROM institution_regular_employment_income_line_items')->fetchColumn(),
];
$operating->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    foreach (['institution_regular_employment_incomes','institution_regular_employment_income_line_items','institution_regular_employment_income_audits'] as $table) {
        $operating->exec("CREATE TABLE `{$database}`.`{$table}` LIKE `{$source}`.`{$table}`");
    }
} catch (Throwable $exception) {
    $operating->exec("DROP DATABASE IF EXISTS `{$database}`");
    throw $exception;
}

// 이 검증은 별도 연결이 아니라 격리 DB의 실제 Model 저장계약을 확인한다.
$operating->exec("USE `{$database}`");
try {
    $operating->exec('SET FOREIGN_KEY_CHECKS=0');
    $operating->exec("INSERT INTO institution_regular_employment_incomes(id,sort_no,income_year_month,payment_date,title,document_status,created_by,updated_by) VALUES('fixture-document',1,'2013-08','2013-08-31','격리 급여','WITHDRAWN','SYSTEM:FIXTURE','SYSTEM:FIXTURE')");
    $model = new RegularEmploymentIncomeModel($operating);
    $service = (new ReflectionClass(RegularEmploymentIncomeService::class))->newInstanceWithoutConstructor();
    $hashMethod = new ReflectionMethod($service, 'payloadHash');
    $allowed = array_flip([
        'sort_no','item_type_code','pay_effect_code','item_code','item_name_snapshot','taxable_flag',
        'calculated_amount','adjustment_amount','final_amount','adjustment_reason','calculation_source_code',
        'business_source_code','source_reference_id','source_key','business_reason','processed_at','processed_by',
        ...RegularEmploymentIncomeCalculationService::TRACE_COLUMNS,
    ]);
    $baseLines = [
        ['item_type_code'=>'PAY','pay_effect_code'=>'CONTRACT_BASE','item_code'=>'BASE_SALARY','item_name_snapshot'=>'기본급','taxable_flag'=>1,'calculated_amount'=>988890,'adjustment_amount'=>0,'final_amount'=>988890,'calculation_source_code'=>'CALCULATED','business_source_code'=>'EMPLOYMENT_CONTRACT','source_reference_id'=>'fixture-contract'],
        ['item_type_code'=>'DEDUCTION','pay_effect_code'=>null,'item_code'=>'EMPLOYMENT_INSURANCE','item_name_snapshot'=>'고용보험','taxable_flag'=>null,'calculated_amount'=>6420,'adjustment_amount'=>0,'final_amount'=>6420,'calculation_source_code'=>'CALCULATED','application_status_code'=>'APPLICABLE','calculation_basis_amount'=>988890,'calculation_rate'=>0.0065,'calculation_before_rounding'=>6427.785,'rounding_method_code'=>'TRUNCATE','rounding_unit'=>10,'statutory_standard_id'=>'fixture-standard','social_insurance_coverage_id'=>'fixture-coverage','workplace_size_period_id'=>null],
    ];
    $save = static function (array $payload, string $requestKey, bool $failAfterLines = false) use ($operating, $model, $hashMethod, $service, $allowed): array {
        $operating->beginTransaction();
        try {
            $operating->query("SELECT id FROM institution_regular_employment_incomes WHERE id='fixture-document' FOR UPDATE")->fetchColumn();
            $hash = $hashMethod->invoke($service, $payload);
            $audit = $model->findAuditByRequestKey('fixture-document', $requestKey);
            if ($audit) {
                $stored = json_decode((string) $audit['after_value'], true);
                if (($stored['payload_hash'] ?? null) !== $hash) throw new DomainException('같은 요청키에 다른 급여 계산 Payload를 사용할 수 없습니다.');
                $operating->commit();
                return ['idempotent' => true];
            }
            $rows = [];
            foreach ($payload['lines'] as $index => $line) {
                $rows[] = ['id'=>'line-'.($index+1),'regular_employment_income_item_id'=>'fixture-item','sort_no'=>$index+1,'created_by'=>'SYSTEM:FIXTURE','updated_by'=>'SYSTEM:FIXTURE'] + array_intersect_key($line, $allowed);
            }
            $model->replaceLineItems('fixture-item', $rows);
            if ($failAfterLines) throw new RuntimeException('Rollback 검증');
            $model->insertAudit(['id'=>'audit-'.substr(hash('sha256',$requestKey),0,20),'regular_employment_income_id'=>'fixture-document','regular_employment_income_item_id'=>null,'action_code'=>'CALCULATE','reason'=>'격리 멱등성 검증','before_value'=>null,'after_value'=>json_encode(['payload_hash'=>$hash]),'request_key'=>$requestKey,'acted_by'=>'SYSTEM:FIXTURE']);
            $operating->commit();
            return ['idempotent' => false];
        } catch (Throwable $exception) {
            if ($operating->inTransaction()) $operating->rollBack();
            throw $exception;
        }
    };

    $payload = ['document_id'=>'fixture-document','lines'=>$baseLines];
    $first = $save($payload, 'same-key');
    $same = $save($payload, 'same-key');
    $differentKey = $save($payload, 'different-key');
    $collision = false;
    try { $save(['document_id'=>'fixture-document','lines'=>array_replace($baseLines,[1=>array_replace($baseLines[1],['final_amount'=>6430])])], 'same-key'); }
    catch (DomainException) { $collision = true; }
    $beforeRollback = $model->lineItems('fixture-item');
    try { $save(['document_id'=>'fixture-document','lines'=>array_replace($baseLines,[1=>array_replace($baseLines[1],['final_amount'=>7000])])], 'rollback-key', true); }
    catch (RuntimeException) {}
    $afterRollback = $model->lineItems('fixture-item');
    $lines = $model->lineItems('fixture-item');
    $pay = $lines[0];
    $statutory = $lines[1];
    foreach (RegularEmploymentIncomeCalculationService::TRACE_COLUMNS as $column) idempotencyAssert(array_key_exists($column, $statutory), '법정 Line 추적 컬럼 누락: '.$column);
    foreach (RegularEmploymentIncomeCalculationService::TRACE_COLUMNS as $column) idempotencyAssert($pay[$column] === null, '비법정 PAY Line 추적 컬럼은 NULL이어야 합니다: '.$column);
    idempotencyAssert(!$first['idempotent'] && $same['idempotent'] && !$differentKey['idempotent'], '요청키별 멱등 응답 검증 실패');
    idempotencyAssert($collision, '동일 요청키의 다른 Payload 충돌 검증 실패');
    idempotencyAssert(count($lines) === 2, '반복 저장 후 Line 수가 증가했습니다.');
    idempotencyAssert($beforeRollback === $afterRollback, '실패 저장이 전체 Rollback되지 않았습니다.');
    echo json_encode(['success'=>true,'first_save'=>true,'same_payload_same_key'=>'IDEMPOTENT','same_payload_different_key'=>'REPLACED_WITHOUT_DUPLICATE','different_payload_same_key'=>'CONFLICT','line_count'=>count($lines),'trace_columns'=>count(RegularEmploymentIncomeCalculationService::TRACE_COLUMNS),'non_statutory_pay_trace_null'=>true,'rollback'=>true,'operating_before'=>$operatingCounts], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
} finally {
    $operating->exec('SET FOREIGN_KEY_CHECKS=1');
    $operating->exec("USE `{$source}`");
    $operating->exec("DROP DATABASE IF EXISTS `{$database}`");
}
