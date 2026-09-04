<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$direction = $argv[1] ?? 'verify';
if (!in_array($direction, ['up', 'down', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_qualification_education_ssot_migration.php [up|down|verify]\n");
    exit(1);
}

$db = DbPdo::conn();
$newTables = [
    'institution_qualifications_types',
    'institution_qualifications_job_requirements',
    'institution_educations_job_requirements',
    'institution_qualification_education_policy_audits',
];

if ($direction !== 'verify') {
    $suffix = $direction === 'up' ? 'up' : 'down';
    $path = PROJECT_ROOT . '/app/migrations/20260821_03_strengthen_qualification_education_ssot.' . $suffix . '.sql';
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Migration SQL을 읽을 수 없습니다.');
    }
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $db->exec($sql);
}

$result = [];
foreach ($newTables as $table) {
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $statement->execute([':table' => $table]);
    $exists = (bool) $statement->fetchColumn();
    $result[$table] = ['exists' => $exists, 'rows' => $exists ? (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() : null];
}

$qualificationCount = (int) $db->query('SELECT COUNT(*) FROM institution_qualifications_employee_records')->fetchColumn();
$linkedCount = $result['institution_qualifications_types']['exists']
    ? (int) $db->query('SELECT COUNT(*) FROM institution_qualifications_employee_records WHERE qualification_type_id IS NOT NULL')->fetchColumn()
    : 0;
$courseCount = (int) $db->query('SELECT COUNT(*) FROM institution_educations_courses')->fetchColumn();
$noneCount = $result['institution_qualifications_types']['exists']
    ? (int) $db->query("SELECT COUNT(*) FROM institution_educations_courses WHERE recurrence_policy_code='NONE'")->fetchColumn()
    : 0;
$permissionStatement = $db->prepare("SELECT COUNT(*) FROM auth_permissions WHERE permission_key LIKE 'api.institution.human_resources.qualification_education.%'");
$permissionStatement->execute();
$permissionCount = (int) $permissionStatement->fetchColumn();
$excelPermissionStatement = $db->prepare("SELECT COUNT(*) FROM auth_permissions WHERE permission_key='api.institution.human_resources.qualification_education.excel'");
$excelPermissionStatement->execute();

echo json_encode([
    'direction' => $direction,
    'tables' => $result,
    'qualification_records' => $qualificationCount,
    'qualification_records_linked' => $linkedCount,
    'education_courses' => $courseCount,
    'education_courses_none' => $noneCount,
    'qualification_education_api_permissions' => $permissionCount,
    'direct_excel_permissions' => (int) $excelPermissionStatement->fetchColumn(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
