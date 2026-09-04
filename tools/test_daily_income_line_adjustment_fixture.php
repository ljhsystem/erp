<?php

declare(strict_types=1);

use App\Services\Institution\DailyEmploymentIncomeLineContractService;
use App\Services\Institution\IncomeCalculationCodeService;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$lineContract = new DailyEmploymentIncomeLineContractService();
$incomeCodes = new IncomeCalculationCodeService($db);
$base = $db->query(
    'SELECT l.daily_employment_income_item_id,l.daily_employment_income_workday_id,l.created_by'
    . ' FROM institution_daily_employment_income_lines l ORDER BY l.id LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);
if (!$base) throw new RuntimeException('Fixture 기준 Line을 찾을 수 없습니다.');
$beforeCount = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn();
$results = [];

$insert = static function (string $lineCode, ?float $calculated, ?float $final, ?string $reason = null) use ($db, $base): string {
    $id = UuidHelper::generate();
    $statement = $db->prepare(
        'INSERT INTO institution_daily_employment_income_lines'
        . ' (id,sort_no,daily_employment_income_item_id,daily_employment_income_workday_id,workday_scope_key,'
        . 'line_type_code,line_code,line_name_snapshot,calculated_amount,final_amount,adjustment_reason,'
        . 'created_at,created_by,updated_at,updated_by)'
        . ' VALUES (:id,999,:item_id,NULL,\'ITEM\',\'DEDUCTION\',:line_code,:line_name,'
        . ':calculated_amount,:final_amount,:adjustment_reason,NOW(),:created_by,NOW(),:updated_by)'
    );
    $statement->execute([
        ':id' => $id, ':item_id' => $base['daily_employment_income_item_id'], ':line_code' => $lineCode,
        ':line_name' => 'A-1 Fixture', ':calculated_amount' => $calculated, ':final_amount' => $final,
        ':adjustment_reason' => $reason, ':created_by' => $base['created_by'], ':updated_by' => $base['created_by'],
    ]);
    return $id;
};
$mustFail = static function (string $label, callable $callback) use ($db, &$results): void {
    $savepoint = 'fixture_' . count($results);
    $db->exec("SAVEPOINT {$savepoint}");
    try {
        $callback();
        throw new RuntimeException("{$label}이 차단되지 않았습니다.");
    } catch (PDOException) {
        $db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
        $results[$label] = 'BLOCKED';
    }
};

try {
    $db->beginTransaction();
    $nullCalculatedId = $insert('FIXTURE_NULL_CALCULATED', null, 0.0);
    $results['calculated NULL/final 0'] = 'ALLOWED';
    $mustFail('Item Grain 동일 Line 중복', static fn() => $insert('FIXTURE_NULL_CALCULATED', null, 0.0));

    $sameId = $insert('FIXTURE_SAME_AMOUNT', 2940.0, 2940.0);
    $sameAdjustment = $db->query('SELECT adjustment_amount FROM institution_daily_employment_income_lines WHERE id=' . $db->quote($sameId))->fetchColumn();
    if ((float) $sameAdjustment !== 0.0) throw new RuntimeException('동일 계산액·적용액의 Generated adjustment가 0이 아닙니다.');
    $results['calculated 2940/final 2940'] = 'ALLOWED_ADJUSTMENT_0';

    $mustFail('차이 사유 누락', static fn() => $insert('FIXTURE_REASON_REQUIRED', 2920.0, 2940.0));
    $reasonId = $insert('FIXTURE_REASON_PRESENT', 2920.0, 2940.0, '과거 실제 공제액 확인');
    $adjustment = $db->query('SELECT adjustment_amount FROM institution_daily_employment_income_lines WHERE id=' . $db->quote($reasonId))->fetchColumn();
    if ((float) $adjustment !== 20.0) throw new RuntimeException('Generated adjustment 20원을 확인할 수 없습니다.');
    $results['차이와 정상 사유'] = 'ALLOWED_ADJUSTMENT_20';
    $mustFail('음수 DEDUCTION', static fn() => $insert('FIXTURE_NEGATIVE', 0.0, -1.0, '음수 차단 확인'));

    $lineContract->assertGrain('DEDUCTION', 'EMPLOYMENT_INSURANCE', null);
    $lineContract->assertGrain('DEDUCTION', 'DAILY_WORKER_INCOME_TAX', (string) $base['daily_employment_income_workday_id']);
    $results['Item 보험 Grain'] = 'ALLOWED';
    $results['Workday 세금 Grain'] = 'ALLOWED';
    try {
        $lineContract->assertGrain('DEDUCTION', 'EMPLOYMENT_INSURANCE', (string) $base['daily_employment_income_workday_id']);
        throw new RuntimeException('보험 Workday Grain이 차단되지 않았습니다.');
    } catch (InvalidArgumentException) {
        $results['보험 Workday Grain 혼용'] = 'BLOCKED';
    }

    $wrongGroupId = $db->query(
        "SELECT id FROM system_codes WHERE code_group<>'INCOME_STATUTORY_CALCULATION_SOURCE' ORDER BY id LIMIT 1"
    )->fetchColumn();
    try {
        $incomeCodes->assertIdInGroup((string) $wrongGroupId, IncomeCalculationCodeService::STATUTORY_SOURCE_GROUP);
        throw new RuntimeException('다른 코드그룹 ID가 차단되지 않았습니다.');
    } catch (InvalidArgumentException) {
        $results['다른 코드그룹 ID'] = 'BLOCKED';
    }

    $db->rollBack();
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    throw $exception;
}

$afterCount = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn();
if ($beforeCount !== $afterCount) throw new RuntimeException('Fixture Line이 잔존합니다.');
echo json_encode(['results' => $results, 'line_count_before' => $beforeCount, 'line_count_after' => $afterCount, 'fixture_remaining' => 0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
