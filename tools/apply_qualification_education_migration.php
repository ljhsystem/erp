<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$direction = $argv[1] ?? 'verify';
if (!in_array($direction, ['up', 'down', 'sync-up', 'sync-down', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_qualification_education_migration.php [up|down|sync-up|sync-down|verify]\n");
    exit(1);
}

$pdo = DbPdo::conn();
if ($direction !== 'verify') {
    $paths = match ($direction) {
        'up' => ['20260806_03_create_qualification_education_phase1.up.sql','20260806_04_sync_qualification_education_permissions.up.sql'],
        'down' => ['20260806_04_sync_qualification_education_permissions.down.sql','20260806_03_create_qualification_education_phase1.down.sql'],
        'sync-up' => ['20260806_04_sync_qualification_education_permissions.up.sql'],
        'sync-down' => ['20260806_04_sync_qualification_education_permissions.down.sql'],
    };
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    foreach ($paths as $file) {
        $sql = file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file);
        if ($sql === false) throw new RuntimeException('Migration SQL을 읽을 수 없습니다: ' . $file);
        $pdo->exec($sql);
    }
}

$tables = ['institution_qualifications_employee_records','institution_qualifications_audits','institution_educations_courses','institution_educations_employee_records','institution_educations_audits'];
$result = [];
foreach ($tables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $stmt->execute([':table'=>$table]);
    $exists = (bool)$stmt->fetchColumn();
    $result[$table] = ['exists'=>$exists,'rows'=>$exists?(int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn():null];
}
$columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_employees' AND COLUMN_NAME IN ('certificate_name','certificate_file') ORDER BY COLUMN_NAME")->fetchAll(PDO::FETCH_COLUMN);
$permissions = (int)$pdo->query("SELECT COUNT(*) FROM auth_permissions WHERE permission_key LIKE 'api.institution.human_resources.qualification_education.%'")->fetchColumn();
$codes = (int)$pdo->query("SELECT COUNT(*) FROM system_codes WHERE code_group IN ('QUALIFICATION_TYPE','QUALIFICATION_STATUS','EDUCATION_TYPE','EDUCATION_ATTENDANCE_STATUS','EDUCATION_COMPLETION_STATUS')")->fetchColumn();
echo json_encode(['direction'=>$direction,'tables'=>$result,'legacy_employee_columns'=>$columns,'api_permissions'=>$permissions,'codes'=>$codes],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),"\n";
