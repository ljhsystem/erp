<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$serviceSource = file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeService.php') ?: '';
$assert(!str_contains($serviceSource, 'nominalPaymentDate'), '소득자료에 명목 지급일 계산이 남아 있습니다.');

echo "employment contract payment terms: OK\n";

if (in_array('--db', $argv, true)) {
    require_once PROJECT_ROOT . '/core/Storage.php';
    $db = Core\DbPdo::conn();
    $contract = $db->query("SELECT salary_type,payment_day,payment_timing FROM institution_employment_contracts ORDER BY created_at LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $assert((string) ($contract['salary_type'] ?? '') === 'MONTHLY', '기존 월급제 계약이 복원되지 않았습니다.');
    $assert((int) ($contract['payment_day'] ?? 0) === 11, '기존 급여지급일이 복원되지 않았습니다.');
    $assert((string) ($contract['payment_timing'] ?? '') === 'NEXT_MONTH', '기존 익월 지급기준이 복원되지 않았습니다.');

    $scheduledColumns = (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_incomes' AND COLUMN_NAME IN ('nominal_payment_date','proposed_payment_date','payment_date','payment_date_override_reason')")->fetchColumn();
    $assert($scheduledColumns === 0, '상용근로소득 지급예정일 컬럼이 남아 있습니다.');
    echo "employment contract payment terms DB runtime: OK\n";
}
