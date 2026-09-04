<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$direction = $argv[1] ?? 'verify';
if (!in_array($direction, ['up','down','verify','sync-permissions'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_employment_regulation_boundary.php [up|down|verify|sync-permissions]\n");
    exit(1);
}
$pdo = DbPdo::conn();
if ($direction === 'up') {
    $sql = file_get_contents(PROJECT_ROOT . '/app/migrations/20260821_12_close_employment_regulation_ssot_boundary.up.sql');
    if ($sql === false) throw new RuntimeException('Migration SQL을 읽을 수 없습니다.');
    if (!preg_match('/DELIMITER \$\$(.*?)\$\$\s*DELIMITER ;(.*)/s', $sql, $matches)) {
        throw new RuntimeException('Migration procedure 구문을 해석할 수 없습니다.');
    }
    $pdo->exec(trim($matches[1]));
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->exec(trim($matches[2]));
} elseif ($direction === 'down') {
    $sql = file_get_contents(PROJECT_ROOT . '/app/migrations/20260821_12_close_employment_regulation_ssot_boundary.down.sql');
    if ($sql === false) throw new RuntimeException('Rollback SQL을 읽을 수 없습니다.');
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->exec($sql);
} elseif ($direction === 'sync-permissions') {
    $pdo->exec("UPDATE auth_permissions
        SET page_key='web.institution.human_resources.employment_rules', updated_at=NOW(), updated_by='SYSTEM:MIGRATION'
        WHERE permission_key='web.institution.human_resources.employment_rules'
           OR permission_key LIKE 'api.institution.human_resources.employment_rules.%'");
}

$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'institution_employment_rules%' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
$columns = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLUMN_KEY,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('institution_employment_rules','institution_employment_rules_revisions','institution_employment_rules_audits') ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
$registry = (int) $pdo->query("SELECT COUNT(*) FROM system_page_registry WHERE page_key='web.institution.human_resources.employment_rules'")->fetchColumn();
$permissionNulls = (int) $pdo->query("SELECT COUNT(*) FROM auth_permissions WHERE (permission_key='web.institution.human_resources.employment_rules' OR permission_key LIKE 'api.institution.human_resources.employment_rules.%') AND page_key IS NULL")->fetchColumn();
$permissions = (int) $pdo->query("SELECT COUNT(*) FROM auth_permissions WHERE permission_key='web.institution.human_resources.employment_rules' OR permission_key LIKE 'api.institution.human_resources.employment_rules.%'")->fetchColumn();
$excelPermissions = (int) $pdo->query("SELECT COUNT(*) FROM auth_permissions WHERE permission_key LIKE 'api.institution.human_resources.employment_rules.%excel%'")->fetchColumn();
$types = $pdo->query("SELECT code,code_name FROM system_codes WHERE code_group='EMPLOYMENT_RULE_TYPE' AND is_active=1 ORDER BY sort_no")->fetchAll(PDO::FETCH_ASSOC);
$statuses = $pdo->query("SELECT code,code_name FROM system_codes WHERE code_group='EMPLOYMENT_RULE_STATUS' AND is_active=1 ORDER BY sort_no")->fetchAll(PDO::FETCH_ASSOC);
$createChecks = [];
foreach (['institution_employment_rules','institution_employment_rules_revisions','institution_employment_rules_audits'] as $table) {
    $row = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
    $ddl = (string) ($row[1] ?? '');
    $createChecks[$table] = [
        'show_create_table' => $ddl !== '',
        'foreign_keys' => substr_count($ddl, 'CONSTRAINT `'),
        'indexes' => substr_count($ddl, ' KEY `'),
        'checks' => substr_count(strtoupper($ddl), 'CHECK ('),
        'has_comments' => str_contains($ddl, 'COMMENT'),
        'has_request_key' => str_contains($ddl, '`request_key`'),
        'has_actor' => str_contains($ddl, '_by`'),
    ];
}
echo json_encode(compact('direction','tables','columns','createChecks','registry','permissionNulls','permissions','excelPermissions','types','statuses'), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
