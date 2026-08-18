<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$tables = [
    'institution_employment_contracts_break_schedules',
    'institution_leave_types', 'institution_leave_grants', 'institution_leave_requests',
    'institution_leave_request_items', 'institution_leave_usages',
    'institution_leave_ledger_entries', 'institution_leave_audits',
];
foreach ($tables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
    $stmt->execute([':table' => $table]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException($table . ' 테이블이 없습니다.');
    }
    echo $table . ' rows=' . $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() . PHP_EOL;
}
$checks = [
    'leave_types' => "SELECT COUNT(*) FROM institution_leave_types",
    'permissions' => "SELECT COUNT(*) FROM auth_permissions WHERE permission_key='web.institution.human_resources.leave' OR permission_key LIKE 'api.institution.human_resources.leave.%'",
    'super_admin_mappings' => "SELECT COUNT(*) FROM auth_role_permissions rp JOIN auth_roles r ON r.id=rp.role_id JOIN auth_permissions p ON p.id=rp.permission_id WHERE r.role_key='super_admin' AND (p.permission_key='web.institution.human_resources.leave' OR p.permission_key LIKE 'api.institution.human_resources.leave.%')",
    'active_templates' => "SELECT COUNT(*) FROM user_approval_templates WHERE document_type='LEAVE_REQUEST' AND is_active=1",
    'template_steps' => "SELECT COUNT(*) FROM user_approval_template_steps s JOIN user_approval_templates t ON t.id=s.template_id WHERE t.document_type='LEAVE_REQUEST' AND t.is_active=1 AND s.is_active=1",
];
foreach ($checks as $label => $sql) {
    echo $label . '=' . $pdo->query($sql)->fetchColumn() . PHP_EOL;
}
$expected = ['leave_types' => 8, 'permissions' => 12, 'super_admin_mappings' => 12, 'active_templates' => 1, 'template_steps' => 2];
foreach ($expected as $label => $count) {
    if ((int) $pdo->query($checks[$label])->fetchColumn() !== $count) {
        throw new RuntimeException($label . ' 검증 실패');
    }
}
echo "leave phase1 schema audit passed\n";
