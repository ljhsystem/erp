<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$direction = $argv[1] ?? 'verify';
if (!in_array($direction, ['up', 'down', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_employment_rules_migration.php [up|down|verify]\n");
    exit(1);
}
$pdo = DbPdo::conn();
if ($direction !== 'verify') {
    $file = PROJECT_ROOT . '/app/migrations/20260806_05_create_employment_rules_phase1.' . $direction . '.sql';
    $sql = file_get_contents($file);
    if ($sql === false) throw new RuntimeException('Migration SQL을 읽을 수 없습니다.');
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->exec($sql);
}
$tables = ['institution_employment_rules','institution_employment_rules_revisions','institution_employment_rules_items','institution_employment_rules_scopes','institution_employment_rules_audits'];
$result = [];
foreach ($tables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $stmt->execute([':table'=>$table]);
    $exists = (bool) $stmt->fetchColumn();
    $result[$table] = ['exists'=>$exists,'rows'=>$exists?(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn():null];
}
$permissions = (int) $pdo->query("SELECT COUNT(*) FROM auth_permissions WHERE permission_key LIKE 'api.institution.human_resources.employment_rules.%'")->fetchColumn();
$codes = (int) $pdo->query("SELECT COUNT(*) FROM system_codes WHERE code_group LIKE 'EMPLOYMENT_RULE_%'")->fetchColumn();
$templates = (int) $pdo->query("SELECT COUNT(*) FROM user_approval_templates WHERE document_type='EMPLOYMENT_RULE_REVISION'")->fetchColumn();
$referenceColumns = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='system_company' AND COLUMN_NAME IN ('id','company_name')) OR (TABLE_NAME='user_departments' AND COLUMN_NAME='id') OR (TABLE_NAME='user_positions' AND COLUMN_NAME='id') OR (TABLE_NAME='institution_job_assignments_jobs' AND COLUMN_NAME='id') OR (TABLE_NAME='user_approval_requests' AND COLUMN_NAME='id')) ORDER BY TABLE_NAME,COLUMN_NAME")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['direction'=>$direction,'tables'=>$result,'api_permissions'=>$permissions,'codes'=>$codes,'approval_templates'=>$templates,'reference_columns'=>$referenceColumns], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), "\n";
