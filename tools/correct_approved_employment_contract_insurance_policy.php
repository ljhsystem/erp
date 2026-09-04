<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = $argv[1] ?? 'audit';
if (!in_array($mode, ['audit', 'apply'], true)) {
    throw new InvalidArgumentException('사용법: php tools/correct_approved_employment_contract_insurance_policy.php [audit|apply]');
}

$db = DbPdo::conn();
$select = $db->prepare(
    "SELECT c.id,c.contract_no,c.contract_status,c.contract_start_date,c.contract_end_date,
            c.employment_insurance_application_status_code,c.employment_insurance_exclusion_reason,
            c.industrial_accident_application_status_code,c.industrial_accident_exclusion_reason,
            c.updated_at,c.updated_by,e.employee_name
       FROM institution_employment_contracts c
       JOIN user_employees e ON e.id=c.employee_id
      WHERE c.deleted_at IS NULL
        AND c.contract_status='APPROVED'
        AND e.employee_name IN ('이정호','박한호')
      ORDER BY e.employee_name,c.contract_start_date,c.id"
);
$select->execute();
$before = $select->fetchAll(\PDO::FETCH_ASSOC);

$counts = array_count_values(array_column($before, 'employee_name'));
if (count($before) !== 2 || ($counts['이정호'] ?? 0) !== 1 || ($counts['박한호'] ?? 0) !== 1) {
    throw new RuntimeException('승인 계약 대상이 이정호 1건, 박한호 1건과 정확히 일치하지 않습니다.');
}

if ($mode === 'audit') {
    echo json_encode(['success' => true, 'mode' => $mode, 'contracts' => $before], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
    exit(0);
}

$targets = [
    '이정호' => ['EXCLUDED', '대표자라서 고용가입안됨', 'EXCLUDED', '대표자라서 산재가입안됨'],
    '박한호' => ['APPLICABLE', null, 'APPLICABLE', null],
];

$db->beginTransaction();
try {
    $update = $db->prepare(
        'UPDATE institution_employment_contracts
            SET employment_insurance_application_status_code=:employment_status,
                employment_insurance_exclusion_reason=:employment_reason,
                industrial_accident_application_status_code=:accident_status,
                industrial_accident_exclusion_reason=:accident_reason,
                updated_at=updated_at,
                updated_by=updated_by
          WHERE id=:id AND contract_status=\'APPROVED\''
    );
    $affected = 0;
    $expectedAffected = 0;
    foreach ($before as $contract) {
        [$employmentStatus, $employmentReason, $accidentStatus, $accidentReason] = $targets[$contract['employee_name']];
        if ($contract['employment_insurance_application_status_code'] !== $employmentStatus
            || $contract['employment_insurance_exclusion_reason'] !== $employmentReason
            || $contract['industrial_accident_application_status_code'] !== $accidentStatus
            || $contract['industrial_accident_exclusion_reason'] !== $accidentReason) {
            $expectedAffected++;
        }
        $update->execute([
            'employment_status' => $employmentStatus,
            'employment_reason' => $employmentReason,
            'accident_status' => $accidentStatus,
            'accident_reason' => $accidentReason,
            'id' => $contract['id'],
        ]);
        $affected += $update->rowCount();
    }
    if ($affected !== $expectedAffected) {
        throw new RuntimeException('수정 행 수가 정정이 필요한 계약 건수와 일치하지 않습니다.');
    }

    $select->execute();
    $after = $select->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($after as $contract) {
        [$employmentStatus, $employmentReason, $accidentStatus, $accidentReason] = $targets[$contract['employee_name']];
        if ($contract['employment_insurance_application_status_code'] !== $employmentStatus
            || $contract['employment_insurance_exclusion_reason'] !== $employmentReason
            || $contract['industrial_accident_application_status_code'] !== $accidentStatus
            || $contract['industrial_accident_exclusion_reason'] !== $accidentReason) {
            throw new RuntimeException($contract['employee_name'] . ' 보험 적용정책 검증에 실패했습니다.');
        }
        $original = current(array_filter($before, static fn(array $row): bool => $row['id'] === $contract['id']));
        if (!$original || $contract['updated_at'] !== $original['updated_at'] || $contract['updated_by'] !== $original['updated_by']) {
            throw new RuntimeException($contract['employee_name'] . ' timestamp 또는 Actor가 변경되었습니다.');
        }
    }
    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

echo json_encode([
    'success' => true,
    'mode' => $mode,
    'affected' => $affected,
    'before' => $before,
    'after' => $after,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
