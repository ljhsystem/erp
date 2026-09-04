<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

$direction = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($direction, ['up', 'verify', 'down', 'repair'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_leave_grant_ledger_policy_migration.php [up|verify|down|repair]');
}

$pdo = Core\DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

if ($direction === 'down') {
    $grantCount = (int) $pdo->query('SELECT COUNT(*) FROM institution_leave_grants')->fetchColumn();
    $ledgerCount = (int) $pdo->query('SELECT COUNT(*) FROM institution_leave_ledger_entries')->fetchColumn();
    if ($grantCount !== 0 || $ledgerCount !== 0) {
        throw new RuntimeException('운영 데이터가 존재하여 Down Migration을 실행할 수 없습니다.');
    }
}

if ($direction === 'repair') {
    $pdo->exec("ALTER TABLE institution_leave_types DROP CONSTRAINT chk_leave_type_accrual_mode, MODIFY COLUMN minimum_hourly_minutes SMALLINT UNSIGNED NULL COMMENT '시간차 최소 신청 분', MODIFY COLUMN accrual_mode_code VARCHAR(30) NOT NULL DEFAULT 'MANUAL' COMMENT '발생 방식: MANUAL, CALCULATED_CONFIRMATION', ADD CONSTRAINT chk_leave_type_accrual_mode CHECK (accrual_mode_code IN ('MANUAL','CALCULATED_CONFIRMATION'))");
} elseif ($direction !== 'verify') {
    $file = PROJECT_ROOT . '/app/migrations/20260821_02_strengthen_leave_grant_ledger_policy.' . $direction . '.sql';
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Migration SQL을 읽을 수 없습니다.');
    }
    if ($direction === 'up') {
        $policyColumnCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='institution_leave_types' AND COLUMN_NAME='minimum_hourly_minutes') OR (TABLE_NAME='institution_leave_grants' AND COLUMN_NAME='grant_source_code'))")->fetchColumn();
        $grantIdExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_leave_ledger_entries' AND COLUMN_NAME='grant_id'")->fetchColumn();
        if ($policyColumnCount === 2 && $grantIdExists === 0) {
            $offset = strpos($sql, 'ALTER TABLE institution_leave_ledger_entries');
            if ($offset === false) {
                throw new RuntimeException('Ledger Migration SQL을 찾을 수 없습니다.');
            }
            $sql = substr($sql, $offset);
        }
    }
    $pdo->exec($sql);
}

$columns = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='institution_leave_types' AND COLUMN_NAME IN ('minimum_hourly_minutes','accrual_mode_code','carryover_policy_code','carryover_limit_minutes')) OR (TABLE_NAME='institution_leave_grants' AND COLUMN_NAME IN ('grant_source_code','calculation_basis_json')) OR (TABLE_NAME='institution_leave_ledger_entries' AND COLUMN_NAME='grant_id')) ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$constraints = $pdo->query("SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('institution_leave_types','institution_leave_grants','institution_leave_ledger_entries') AND (CONSTRAINT_NAME LIKE 'chk_leave_type_%' OR CONSTRAINT_NAME LIKE 'chk_leave_grant_%' OR CONSTRAINT_NAME IN ('chk_leave_ledger_grant_binding','chk_leave_ledger_source','fk_leave_ledger_grant')) ORDER BY TABLE_NAME,CONSTRAINT_NAME")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$indexes = $pdo->query("SELECT TABLE_NAME,INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_in_order FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME IN ('idx_leave_grant_expiration','idx_leave_ledger_grant_occurred') GROUP BY TABLE_NAME,INDEX_NAME ORDER BY TABLE_NAME,INDEX_NAME")->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode(['direction' => $direction, 'columns' => $columns, 'constraints' => $constraints, 'indexes' => $indexes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
