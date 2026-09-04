<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$source = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$token = bin2hex(random_bytes(6));
$schema = 'tmp_daily_income_closure_' . $token;
$created = false;

if (!preg_match('/^tmp_daily_income_closure_[0-9a-f]{12}$/', $schema)) {
    throw new RuntimeException('격리 Schema 이름 검증에 실패했습니다.');
}

$execute = static function (PDO $connection, string $file): void {
    $delimiter = ';';
    $buffer = '';
    $sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file);
    foreach (preg_split('/\r\n|\n|\r/', $sql) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $connection->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException($file . ' SQL 구문이 완결되지 않았습니다.');
};

$tables = [
    'institution_daily_employment_incomes',
    'institution_daily_employment_income_groups',
    'institution_daily_employment_income_items',
    'institution_daily_employment_income_lines',
    'institution_daily_employment_income_calculation_revisions',
    'institution_daily_employment_income_calculation_results',
    'institution_daily_employment_income_commands',
    'ledger_evidence_daily_employment_income',
    'ledger_transactions',
    'ledger_evidence_links',
    'user_approval_templates',
    'user_approval_template_steps',
    'user_approval_requests',
    'system_clients',
];

$pdo->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$created = true;
try {
    foreach ($tables as $table) {
        $pdo->exec("CREATE TABLE `{$schema}`.`{$table}` LIKE `{$source}`.`{$table}`");
    }
    $pdo->exec("CREATE TABLE `{$schema}`.`_test_schema_ownership` ("
        . 'test_tool_code VARCHAR(100) NOT NULL,unique_token VARCHAR(64) NOT NULL,'
        . 'source_script VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB');
    $marker = $pdo->prepare("INSERT INTO `{$schema}`.`_test_schema_ownership` VALUES ('DAILY_INCOME_CLOSURE',:token,:script,NOW())");
    $marker->execute([':token' => $token, ':script' => basename(__FILE__)]);
    $pdo->exec("INSERT INTO `{$schema}`.`user_approval_templates` SELECT * FROM `{$source}`.`user_approval_templates` WHERE document_type='REGULAR_EMPLOYMENT_INCOME'");
    $pdo->exec("INSERT INTO `{$schema}`.`user_approval_template_steps` SELECT step_row.* FROM `{$source}`.`user_approval_template_steps` step_row JOIN `{$source}`.`user_approval_templates` template_row ON template_row.id=step_row.template_id WHERE template_row.document_type='REGULAR_EMPLOYMENT_INCOME'");
    $pdo->exec("USE `{$schema}`");

    $migrationTimings = [];
    foreach ([
        '20260831_09_close_daily_employment_income_approval_accounting.up.sql',
        '20260831_10_widen_daily_income_line_application_status.up.sql',
        '20260831_11_close_daily_calculation_result_group_worker_grain.up.sql',
    ] as $migrationFile) {
        $started = microtime(true);
        $execute($pdo, $migrationFile);
        $migrationTimings[$migrationFile] = round((microtime(true) - $started) * 1000, 2);
    }
    $checks = [
        'closure_tables' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('institution_daily_employment_income_closures','institution_daily_employment_income_accounting_links')")->fetchColumn(),
        'evidence_columns' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND COLUMN_NAME IN ('daily_employment_income_group_id','calculation_revision_id','source_hash','snapshot_json','business_key_hash')")->fetchColumn(),
        'template_count' => (int) $pdo->query("SELECT COUNT(*) FROM user_approval_templates WHERE document_type='DAILY_EMPLOYMENT_INCOME' AND is_active=1")->fetchColumn(),
        'template_steps' => (int) $pdo->query("SELECT COUNT(*) FROM user_approval_template_steps step_row JOIN user_approval_templates template_row ON template_row.id=step_row.template_id WHERE template_row.document_type='DAILY_EMPLOYMENT_INCOME' AND step_row.is_active=1")->fetchColumn(),
        'evidence_rows' => (int) $pdo->query('SELECT COUNT(*) FROM ledger_evidence_daily_employment_income')->fetchColumn(),
        'result_grain_item' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_calculation_results' AND INDEX_NAME='uq_daily_calc_result_grain' AND COLUMN_NAME='daily_employment_income_item_id'")->fetchColumn(),
        'line_status_width' => (string) $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_lines' AND COLUMN_NAME='application_status_code'")->fetchColumn(),
    ];
    if ($checks !== ['closure_tables' => 2, 'evidence_columns' => 5, 'template_count' => 1, 'template_steps' => 2, 'evidence_rows' => 0, 'result_grain_item' => 1, 'line_status_width' => 'varchar(30)']) {
        throw new RuntimeException('Up 검증 실패: ' . json_encode($checks, JSON_UNESCAPED_UNICODE));
    }

    $duplicateBlocked = false;
    try {
        $execute($pdo, '20260831_09_close_daily_employment_income_approval_accounting.up.sql');
    } catch (PDOException $exception) {
        $duplicateBlocked = $exception->getCode() === '45000';
    }
    if (!$duplicateBlocked) throw new RuntimeException('중복 Up 차단 검증에 실패했습니다.');

    $execute($pdo, '20260831_11_close_daily_calculation_result_group_worker_grain.down.sql');
    $execute($pdo, '20260831_10_widen_daily_income_line_application_status.down.sql');
    $execute($pdo, '20260831_09_close_daily_employment_income_approval_accounting.down.sql');
    $downChecks = [
        'closure_tables' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('institution_daily_employment_income_closures','institution_daily_employment_income_accounting_links')")->fetchColumn(),
        'evidence_columns' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_evidence_daily_employment_income' AND COLUMN_NAME IN ('daily_employment_income_group_id','calculation_revision_id','source_hash','snapshot_json','business_key_hash')")->fetchColumn(),
        'template_count' => (int) $pdo->query("SELECT COUNT(*) FROM user_approval_templates WHERE document_type='DAILY_EMPLOYMENT_INCOME'")->fetchColumn(),
        'result_grain_item' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_calculation_results' AND INDEX_NAME='uq_daily_calc_result_grain' AND COLUMN_NAME='daily_employment_income_item_id'")->fetchColumn(),
        'line_status_width' => (string) $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_lines' AND COLUMN_NAME='application_status_code'")->fetchColumn(),
    ];
    if ($downChecks !== ['closure_tables' => 0, 'evidence_columns' => 0, 'template_count' => 0, 'result_grain_item' => 0, 'line_status_width' => 'varchar(20)']) {
        throw new RuntimeException('Down 검증 실패: ' . json_encode($downChecks, JSON_UNESCAPED_UNICODE));
    }
    echo json_encode([
        'success' => true,
        'schema' => $schema,
        'up' => $checks,
        'duplicate_up_blocked' => true,
        'up_elapsed_ms' => $migrationTimings,
        'down' => $downChecks,
        'operating_dml' => 0,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $pdo->exec("USE `{$source}`");
    if ($created) {
        $marker = $pdo->prepare("SELECT COUNT(*) FROM `{$schema}`.`_test_schema_ownership` WHERE test_tool_code='DAILY_INCOME_CLOSURE' AND unique_token=:token AND source_script=:script");
        $marker->execute([':token' => $token, ':script' => basename(__FILE__)]);
        if ((int) $marker->fetchColumn() !== 1) throw new RuntimeException('격리 Schema 소유권 Marker가 일치하지 않습니다.');
        $pdo->exec("DROP DATABASE `{$schema}`");
    }
}
