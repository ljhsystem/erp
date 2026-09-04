<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Ledger\VoucherEvidenceRecommendationService;
use Core\DbPdo;

function coverageAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$service = new VoucherEvidenceRecommendationService(DbPdo::conn());
$candidate = static function (string $id, string $debitAccount, float $amount, string $clientId = '', string $employeeId = 'employee-1'): array {
    $source = static fn(string $side, string $role): array => [
        'evidence_type' => 'EMPLOYEE_EXPENSE_PERSONAL', 'evidence_id' => $id,
        'source_type' => 'PERSONAL_EXPENSE_ITEM', 'source_line_key' => $id,
        'debit_credit' => $side, 'accounting_role_code' => $role,
        'source_amount' => $amount, 'allocated_amount' => $amount,
    ];
    return [
        'balanced' => true, 'score' => 100, 'confidence' => 'HIGH',
        'source_types' => ['JOURNAL_RULE'], 'reasons' => ['공식 역할형 분개규칙'], 'signals' => [],
        'lines' => [
            ['line_type' => 'DEBIT', 'account_id' => $debitAccount, 'debit' => $amount, 'credit' => 0,
                'refs' => [['ref_target' => 'CLIENT', 'ref_id' => $clientId !== '' ? $clientId : 'client-' . $id]], 'source_refs' => [$source('DEBIT', 'EXPENSE')]],
            ['line_type' => 'CREDIT', 'account_id' => '216100', 'debit' => 0, 'credit' => $amount,
                'refs' => [['ref_target' => 'EMPLOYEE', 'ref_id' => $employeeId, 'ref_name' => '직원']],
                'source_refs' => [$source('CREDIT', 'EMPLOYEE_ACCRUED_EXPENSE')]],
        ],
    ];
};
$result = static fn(string $id, float $amount, array $candidate): array => [
    'import_type' => 'EMPLOYEE_EXPENSE_PERSONAL', 'evidence_id' => $id,
    'status' => 'RECOMMENDED', 'amount' => $amount, 'candidate' => $candidate, 'candidates' => [$candidate],
];

$complete = [
    $result('tax', 800000, $candidate('tax', '551091', 800000)),
    $result('stamp', 25000, $candidate('stamp', '551220', 25000)),
];
$coverage = $service->coverage($complete);
coverageAssert($coverage['status'] === 'COMPLETE', '전체 Coverage가 COMPLETE가 아닙니다.');
coverageAssert($coverage['identity_status'] === 'COMPLETE' && $coverage['sub_account_status'] === 'COMPLETE', 'Identity 또는 보조정보 Coverage가 불완전합니다.');
$sets = $service->recommendationSets($complete);
coverageAssert(count($sets) === 1 && count($sets[0]['lines']) === 3, '차변 2줄과 통합 대변 1줄이 생성되지 않았습니다.');

$sameAccountSameClient = [
    $result('one', 1000, $candidate('one', '551380', 1000, 'same-client')),
    $result('two', 2000, $candidate('two', '551380', 2000, 'same-client')),
];
$sameAccountSet = $service->recommendationSets($sameAccountSameClient)[0] ?? [];
coverageAssert(count($sameAccountSet['lines'] ?? []) === 3, '같은 계정·거래처라도 서로 다른 Evidence 차변이 합산되었습니다.');

$differentEmployee = [
    $result('one', 1000, $candidate('one', '551380', 1000, 'client-1', 'employee-1')),
    $result('two', 2000, $candidate('two', '551380', 2000, 'client-2', 'employee-2')),
];
$employeeLines = $service->recommendationSets($differentEmployee)[0]['lines'] ?? [];
coverageAssert(count($employeeLines) === 4, '직원이 다른 미지급비용 대변이 하나로 합산되었습니다.');

$missingClient = $complete;
$missingClient[1]['candidate']['lines'][0]['refs'] = [];
$missingClient[1]['candidates'][0]['lines'][0]['refs'] = [];
coverageAssert($service->coverage($missingClient)['status'] === 'INCOMPLETE', '거래처 보조정보 누락이 적용 차단되지 않았습니다.');

$incomplete = $complete;
$incomplete[1] = ['import_type' => 'EMPLOYEE_EXPENSE_PERSONAL', 'evidence_id' => 'stamp', 'status' => 'UNAVAILABLE', 'amount' => 25000, 'candidates' => []];
coverageAssert($service->coverage($incomplete)['status'] === 'INCOMPLETE', '부분 추천이 INCOMPLETE가 아닙니다.');
coverageAssert($service->recommendationSets($incomplete) === [], '부분 추천이 적용 가능한 추천안으로 조립되었습니다.');

echo "voucher evidence recommendation coverage contract: OK\n";
