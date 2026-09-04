<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\LeaveService;
use Core\DbPdo;
use Core\Session;

$pdo = DbPdo::conn();
$before = [
    'grants' => (int) $pdo->query('SELECT COUNT(*) FROM institution_leave_grants')->fetchColumn(),
    'ledger' => (int) $pdo->query('SELECT COUNT(*) FROM institution_leave_ledger_entries')->fetchColumn(),
];
$user = $pdo->query("SELECT u.id,u.username,e.id employee_id FROM auth_users u JOIN user_employees e ON e.user_id=u.id WHERE u.is_active=1 ORDER BY e.sort_no LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$typeId = (string) $pdo->query("SELECT id FROM institution_leave_types WHERE deducts_balance=1 AND is_active=1 ORDER BY sort_no LIMIT 1")->fetchColumn();
if (!$user || $typeId === '') {
    throw new RuntimeException('Grant-Ledger 검증용 직원 또는 차감형 휴가 종류가 없습니다.');
}

Session::start(30);
$_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username']];
$service = new LeaveService($pdo);
$uuid = static function (): string {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};
$grant = static function (LeaveService $service, array $overrides) use ($user, $typeId, $uuid): array {
    return $service->grant(array_merge([
        'employee_id' => $user['employee_id'],
        'leave_type_id' => $typeId,
        'base_year' => 2097,
        'usable_from' => '2097-01-01',
        'usable_to' => '2097-12-31',
        'reason' => 'Grant-Ledger rollback 검증',
        'request_key' => $uuid(),
    ], $overrides))['data'];
};

$pdo->beginTransaction();
try {
    $grantA = $grant($service, ['granted_minutes' => 480, 'expires_on' => '2097-06-30']);
    $grantB = $grant($service, [
        'granted_minutes' => 960,
        'expires_on' => '2097-12-31',
        'grant_source_code' => 'CALCULATED_CONFIRMATION',
        'calculation_basis_json' => ['schema_version' => 1, 'calculation_date' => '2097-01-01', 'calculation_period' => ['from' => '2097-01-01', 'to' => '2097-12-31'], 'employment_basis' => ['service_months' => 12], 'attendance_basis' => ['period' => '2096', 'aggregation' => 'confirmed'], 'statutory_standard' => ['lineage' => 'leave-accrual', 'version' => 1], 'policy_version' => 1, 'calculated_minutes' => 960],
    ]);
    if (empty($grantA['grant_id']) || empty($grantB['grant_id'])) {
        throw new RuntimeException('부여 원장이 Grant와 연결되지 않았습니다.');
    }

    $consume = new ReflectionMethod(LeaveService::class, 'consumeGrants');
    $usageId = $uuid();
    $itemId = $uuid();
    $consume->invoke($service, $user['employee_id'], $typeId, 2097, '2097-05-10', 720, $usageId, $itemId, 'SYSTEM:ROLLBACK_TEST');
    $stmt = $pdo->prepare("SELECT grant_id,amount_minutes,source_sequence FROM institution_leave_ledger_entries WHERE source_domain_code='USAGE' AND source_id=:usage ORDER BY source_sequence");
    $stmt->execute([':usage' => $usageId]);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($allocations) !== 2 || $allocations[0]['grant_id'] !== $grantA['grant_id'] || (int) $allocations[0]['amount_minutes'] !== -480 || $allocations[1]['grant_id'] !== $grantB['grant_id'] || (int) $allocations[1]['amount_minutes'] !== -240) {
        throw new RuntimeException('720분 Grant 분할 소비 순서 또는 금액이 올바르지 않습니다.');
    }

    $ledger = new ReflectionMethod(LeaveService::class, 'ledger');
    foreach ($allocations as $allocation) {
        $sequence = (int) $allocation['source_sequence'];
        $ledger->invoke($service, $user['employee_id'], $typeId, 2097, 'RESTORE', abs((int) $allocation['amount_minutes']), 'CANCELLATION', $usageId, '원배분 복원 검증', 'RESTORE-'.$usageId.'-'.$sequence, 'SYSTEM:ROLLBACK_TEST', $allocation['grant_id'], $sequence, '2097-05-10');
    }
    $balanceStmt = $pdo->prepare('SELECT grant_id,SUM(amount_minutes) balance_minutes FROM institution_leave_ledger_entries WHERE grant_id IN (:grant_a,:grant_b) GROUP BY grant_id');
    $balanceStmt->execute([':grant_a' => $grantA['grant_id'], ':grant_b' => $grantB['grant_id']]);
    $balances = array_column($balanceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'balance_minutes', 'grant_id');
    if ((int) ($balances[$grantA['grant_id']] ?? 0) !== 480 || (int) ($balances[$grantB['grant_id']] ?? 0) !== 960) {
        throw new RuntimeException('취소 복원이 원래 Grant 배분을 복원하지 못했습니다.');
    }

    $usageCount = (int) $pdo->query("SELECT COUNT(*) FROM institution_leave_ledger_entries WHERE source_domain_code='USAGE'")->fetchColumn();
    try {
        $consume->invoke($service, $user['employee_id'], $typeId, 2097, '2097-05-10', 2000, $uuid(), $uuid(), 'SYSTEM:ROLLBACK_TEST');
        throw new RuntimeException('잔액 부족 소비가 차단되지 않았습니다.');
    } catch (ReflectionException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), '잔액')) {
            throw $exception;
        }
    }
    if ((int) $pdo->query("SELECT COUNT(*) FROM institution_leave_ledger_entries WHERE source_domain_code='USAGE'")->fetchColumn() !== $usageCount) {
        throw new RuntimeException('잔액 부족 처리 후 부분 원장이 남았습니다.');
    }

    try {
        $grant($service, ['granted_minutes' => 60, 'grant_source_code' => 'CALCULATED_CONFIRMATION', 'calculation_basis_json' => null]);
        throw new RuntimeException('계산 근거 없는 계산확정 부여가 허용되었습니다.');
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), '계산 근거')) {
            throw $exception;
        }
    }
    $expirationUsageId = $uuid();
    $consume->invoke($service, $user['employee_id'], $typeId, 2097, '2097-05-11', 300, $expirationUsageId, $uuid(), 'SYSTEM:ROLLBACK_TEST');
    $ledger->invoke($service, $user['employee_id'], $typeId, 2097, 'EXPIRATION', -180, 'EXPIRATION', $grantA['grant_id'], 'Grant 잔여분 소멸 검증', $uuid(), 'SYSTEM:ROLLBACK_TEST', $grantA['grant_id'], 1, '2097-06-30');
    $grantABalanceStmt = $pdo->prepare('SELECT SUM(amount_minutes) FROM institution_leave_ledger_entries WHERE grant_id=:grant');
    $grantABalanceStmt->execute([':grant' => $grantA['grant_id']]);
    if ((int) $grantABalanceStmt->fetchColumn() !== 0) {
        throw new RuntimeException('Grant 잔여분 소멸 후 잔액이 0이 아닙니다.');
    }

    $carryoverSource = $grant($service, ['granted_minutes' => 300]);
    $ledger->invoke($service, $user['employee_id'], $typeId, 2097, 'CARRYOVER', -300, 'CARRYOVER', $carryoverSource['grant_id'], '이월 원 Grant 마감 검증', $uuid(), 'SYSTEM:ROLLBACK_TEST', $carryoverSource['grant_id'], 1, '2097-12-31');
    $carryover = $grant($service, ['granted_minutes' => 300, 'grant_source_code' => 'CARRYOVER']);
    $historical = $grant($service, ['granted_minutes' => 120, 'grant_source_code' => 'HISTORICAL_IMPORT', 'occurred_on' => '2097-01-02']);
    if ($carryover['entry_type_code'] !== 'CARRYOVER' || $carryover['source_domain_code'] !== 'CARRYOVER' || $historical['source_domain_code'] !== 'HISTORICAL_IMPORT') {
        throw new RuntimeException('이월 또는 과거이관 원장 출처 구조가 올바르지 않습니다.');
    }
    $historicalUsageId = $uuid();
    $ledger->invoke($service, $user['employee_id'], $typeId, 2097, 'USAGE', -60, 'HISTORICAL_IMPORT', $historicalUsageId, '과거 사용 이관 검증', $uuid(), 'SYSTEM:ROLLBACK_TEST', $historical['grant_id'], 1, '2097-02-01');
    $ledger->invoke($service, $user['employee_id'], $typeId, 2097, 'RESTORE', 30, 'HISTORICAL_IMPORT', $historicalUsageId, '과거 복원 이관 검증', $uuid(), 'SYSTEM:ROLLBACK_TEST', $historical['grant_id'], 2, '2097-02-02');
    $ledger->invoke($service, $user['employee_id'], $typeId, 2097, 'ADJUSTMENT', 1, 'HISTORICAL_IMPORT', $uuid(), '과거 조정 이관 검증', $uuid(), 'SYSTEM:ROLLBACK_TEST', null, 1, '2097-02-03');
    $adjustment = $service->adjust(['employee_id' => $user['employee_id'], 'leave_type_id' => $typeId, 'base_year' => 2097, 'amount_minutes' => 1, 'reason' => '조정 Grant null 검증', 'request_key' => $uuid()])['data'];
    if ($adjustment['grant_id'] !== null) {
        throw new RuntimeException('관리자 조정 원장에 Grant가 연결되었습니다.');
    }

    echo "leave grant-ledger policy rollback test passed\n";
} finally {
    $pdo->rollBack();
}

$after = [
    'grants' => (int) $pdo->query('SELECT COUNT(*) FROM institution_leave_grants')->fetchColumn(),
    'ledger' => (int) $pdo->query('SELECT COUNT(*) FROM institution_leave_ledger_entries')->fetchColumn(),
];
if ($before !== $after) {
    throw new RuntimeException('Rollback 후 휴가 Grant 또는 Ledger 건수가 복원되지 않았습니다.');
}
