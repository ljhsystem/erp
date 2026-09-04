<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Models\Institution\EmploymentContractModel;
use App\Services\Institution\EmploymentContractService;
use App\Services\Institution\EmploymentContractValidityService;
use App\Services\Institution\RegularEmploymentIncomeCalculationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$pdo = Core\DbPdo::conn();
$employee = $pdo->query("SELECT employee_id FROM institution_employment_contracts WHERE employee_name_snapshot='이정호' AND deleted_at IS NULL ORDER BY revision_no DESC,created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$assert(!empty($employee['employee_id']), '검증할 대표자 근로계약을 찾을 수 없습니다.');

$valid = (new EmploymentContractValidityService(new EmploymentContractModel($pdo)))
    ->effectiveContracts((string) $employee['employee_id'], '2013-08-31');
$assert(count($valid) === 1, '2013년 8월 유효 근로계약 Snapshot이 한 건으로 정규화되지 않았습니다.');
$contractId = (string) $valid[0]['id'];
$detail = (new EmploymentContractService($pdo))->detail($contractId)['data'];
$amounts = array_map(static fn(array $row): float => (float) $row['amount'], $detail['components']);
$assert($amounts === [653011.0, 304634.0, 31245.0, 100000.0], '지급항목 계약금액 Snapshot이 실제 급여대장과 다릅니다.');
$assert((float) $detail['compensation_summary']['total_amount'] === 1088890.0, '서버 월 지급합계가 components 합계와 다릅니다.');
$assert((float) $detail['compensation_summary']['converted_amount'] === 13066680.0, '서버 연 환산액이 월 지급합계 × 12와 다릅니다.');

$preview = (new RegularEmploymentIncomeCalculationService($pdo))->preview(
    '2013-08',
    '2013-09-11',
    [(string) $employee['employee_id']],
    'SYSTEM:COMPENSATION_SSOT_FIXTURE'
);
$assert((float) ($preview['results'][0]['gross_amount'] ?? 0) === 1088890.0, '상용근로소득 계약 지급액이 최신 유효 계약 components 합계와 다릅니다.');

echo json_encode([
    'success' => true,
    'contract_id' => $contractId,
    'components' => $amounts,
    'monthly_total' => $detail['compensation_summary']['total_amount'],
    'annualized_amount' => $detail['compensation_summary']['converted_amount'],
    'regular_income_contract_amount' => $preview['results'][0]['gross_amount'],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
