<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$source = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$test = 'codex_regular_income_recovery_' . bin2hex(random_bytes(4));
$tables = [
    'system_statutory_standards', 'user_approval_requests',
    'institution_regular_employment_incomes', 'institution_regular_employment_income_items',
    'institution_regular_employment_income_line_items', 'institution_regular_employment_income_calculation_bases',
    'ledger_evidence_salary_report', 'ledger_transactions', 'ledger_transaction_items',
    'ledger_transaction_settlements', 'ledger_payment_schedules',
    'institution_regular_employment_income_accounting_links',
    'institution_regular_income_accounting_schedules',
];

$execute = static function (PDO $db, string $file): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $db->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};
$blocked = static function (callable $callback): bool {
    try { $callback(); } catch (PDOException $exception) { return $exception->getCode() === '45000'; }
    return false;
};

$before = [];
foreach (['institution_regular_employment_income_accounting_links','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_salary_report','ledger_payment_schedules'] as $table) {
    $before[$table] = (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

$db->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    $db->exec("USE `{$test}`");
    foreach ($tables as $table) {
        $db->exec("USE `{$source}`");
        $row = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $ddl = (string) ($row[1] ?? '');
        if ($ddl === '') throw new RuntimeException("실제 DDL을 가져오지 못했습니다: {$table}");
        $db->exec("USE `{$test}`");
        $db->exec($ddl);
    }
    $db->exec('SET FOREIGN_KEY_CHECKS=1');

    $down = PROJECT_ROOT . '/app/migrations/20260825_05_resume_regular_income_accounting_generation_identity.down.sql';
    $up = PROJECT_ROOT . '/app/migrations/20260825_05_resume_regular_income_accounting_generation_identity.up.sql';
    $db->exec('ALTER TABLE institution_regular_employment_income_accounting_links ADD KEY fixture_fk_header_support (regular_employment_income_id), ADD KEY fixture_fk_evidence_support (evidence_id)');
    $execute($db,$down);
    $db->exec("ALTER TABLE ledger_transaction_items
        ADD COLUMN regular_employment_income_line_item_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
        ADD COLUMN statutory_standard_revision_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
        ADD COLUMN calculation_basis_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
        ADD KEY idx_transaction_item_regular_income_line (regular_employment_income_line_item_id),
        ADD KEY idx_transaction_item_statutory_standard (statutory_standard_revision_id),
        ADD KEY idx_transaction_item_calculation_basis (calculation_basis_id),
        ADD CONSTRAINT fk_transaction_item_regular_income_line FOREIGN KEY (regular_employment_income_line_item_id) REFERENCES institution_regular_employment_income_line_items(id),
        ADD CONSTRAINT fk_transaction_item_statutory_standard FOREIGN KEY (statutory_standard_revision_id) REFERENCES system_statutory_standards(id),
        ADD CONSTRAINT fk_transaction_item_calculation_basis FOREIGN KEY (calculation_basis_id) REFERENCES institution_regular_employment_income_calculation_bases(id)");
    $db->exec("ALTER TABLE ledger_transaction_settlements
        ADD COLUMN regular_employment_income_line_item_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
        ADD COLUMN statutory_standard_revision_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
        ADD COLUMN calculation_basis_id varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
        ADD KEY idx_transaction_settlement_regular_income_line (regular_employment_income_line_item_id),
        ADD KEY idx_transaction_settlement_statutory_standard (statutory_standard_revision_id),
        ADD KEY idx_transaction_settlement_calculation_basis (calculation_basis_id),
        ADD CONSTRAINT fk_transaction_settlement_regular_income_line FOREIGN KEY (regular_employment_income_line_item_id) REFERENCES institution_regular_employment_income_line_items(id),
        ADD CONSTRAINT fk_transaction_settlement_statutory_standard FOREIGN KEY (statutory_standard_revision_id) REFERENCES system_statutory_standards(id),
        ADD CONSTRAINT fk_transaction_settlement_calculation_basis FOREIGN KEY (calculation_basis_id) REFERENCES institution_regular_employment_income_calculation_bases(id)");
    $baselineFk = (int) $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_regular_income_accounting_detail'")->fetchColumn();
    if ($baselineFk !== 1) throw new RuntimeException('실제 기존 Detail FK가 격리 DB에 재현되지 않았습니다.');

    $execute($db, $up);
    $checks = [
        'support_index' => (int) $db->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND INDEX_NAME='idx_regular_income_accounting_detail' AND NON_UNIQUE=1")->fetchColumn(),
        'detail_fk' => (int) $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_regular_income_accounting_detail'")->fetchColumn(),
        'registry_columns' => (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND COLUMN_NAME IN ('generation_role','aggregation_key','approval_request_id','attribution_month','recognition_date','payload_hash')")->fetchColumn(),
        'schedule_table' => (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'")->fetchColumn(),
        'checks' => (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK' AND CONSTRAINT_NAME IN ('chk_regular_income_accounting_role','chk_regular_income_accounting_month','chk_regular_income_accounting_payload_hash','chk_regular_income_accounting_role_fields','chk_regular_income_accounting_schedule_role')")->fetchColumn(),
    ];
    if ($checks !== ['support_index'=>1,'detail_fk'=>1,'registry_columns'=>6,'schedule_table'=>1,'checks'=>5]) {
        throw new RuntimeException('Recovery Up 구조 검증 실패: ' . json_encode($checks));
    }
    $rerunBlocked = $blocked(fn() => $execute($db, $up));

    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    $db->exec("INSERT INTO institution_regular_income_accounting_schedules (id,accounting_link_id,payment_schedule_id,schedule_role,created_by) VALUES ('test-schedule','test-link','test-payment','EMPLOYEE_NET','SYSTEM:TEST')");
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    $dataDownBlocked = $blocked(fn() => $execute($db, $down));
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    $db->exec("DELETE FROM institution_regular_income_accounting_schedules WHERE id='test-schedule'");
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    $execute($db, $down);
    $pre04Blocked = $blocked(fn() => $execute($db, $up));
    if (!$rerunBlocked || !$dataDownBlocked || !$pre04Blocked) throw new RuntimeException('재실행 또는 Down Guard 검증에 실패했습니다.');

    $db->exec("USE `{$source}`");
    $after = [];
    foreach (array_keys($before) as $table) $after[$table] = (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    if ($before !== $after) throw new RuntimeException('격리 검증 중 운영 데이터 건수가 변경됐습니다.');
    echo json_encode(['success'=>true,'clone_method'=>'SHOW CREATE TABLE with actual FK','checks'=>$checks,'completed_rerun_blocked'=>$rerunBlocked,'data_down_blocked'=>$dataDownBlocked,'pre04_state_blocked'=>$pre04Blocked,'empty_integrated_down'=>'PASS','operating_counts_unchanged'=>true], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $db->exec("USE `{$source}`");
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    $db->exec("DROP DATABASE IF EXISTS `{$test}`");
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
}
