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
    'page_registry' => "SELECT COUNT(*) FROM system_page_registry WHERE page_key IN ('approval.leave_request','web.institution.human_resources.leave') AND is_active=1",
    'permissions' => "SELECT COUNT(*) FROM auth_permissions WHERE permission_key IN ('web.approval.leave-request','web.institution.human_resources.leave') OR permission_key LIKE 'api.approval.leave-request.%' OR permission_key IN ('api.institution.human_resources.leave.status_list','api.institution.human_resources.leave.balance_list','api.institution.human_resources.leave.options','api.institution.human_resources.leave.detail','api.institution.human_resources.leave.grant','api.institution.human_resources.leave.adjust','api.institution.human_resources.leave.type_save')",
    'obsolete_permissions' => "SELECT COUNT(*) FROM auth_permissions WHERE permission_key IN ('api.institution.human_resources.leave.view_self','api.institution.human_resources.leave.view_all','api.institution.human_resources.leave.save','api.institution.human_resources.leave.submit','api.institution.human_resources.leave.withdraw','api.institution.human_resources.leave.cancel','api.institution.human_resources.leave.excel')",
    'super_admin_mappings' => "SELECT COUNT(*) FROM auth_role_permissions rp JOIN auth_roles r ON r.id=rp.role_id JOIN auth_permissions p ON p.id=rp.permission_id WHERE r.role_key='super_admin' AND (p.permission_key IN ('web.approval.leave-request','web.institution.human_resources.leave') OR p.permission_key LIKE 'api.approval.leave-request.%' OR p.permission_key IN ('api.institution.human_resources.leave.status_list','api.institution.human_resources.leave.balance_list','api.institution.human_resources.leave.options','api.institution.human_resources.leave.detail','api.institution.human_resources.leave.grant','api.institution.human_resources.leave.adjust','api.institution.human_resources.leave.type_save'))",
    'active_templates' => "SELECT COUNT(*) FROM user_approval_templates WHERE document_type='LEAVE_REQUEST' AND is_active=1",
    'template_steps' => "SELECT COUNT(*) FROM user_approval_template_steps s JOIN user_approval_templates t ON t.id=s.template_id WHERE t.document_type='LEAVE_REQUEST' AND t.is_active=1 AND s.is_active=1",
];
foreach ($checks as $label => $sql) {
    echo $label . '=' . $pdo->query($sql)->fetchColumn() . PHP_EOL;
}
$expected = ['leave_types' => 8, 'page_registry' => 2, 'permissions' => 17, 'obsolete_permissions' => 0, 'super_admin_mappings' => 17, 'active_templates' => 1, 'template_steps' => 2];
foreach ($expected as $label => $count) {
    if ((int) $pdo->query($checks[$label])->fetchColumn() !== $count) {
        throw new RuntimeException($label . ' 검증 실패');
    }
}
echo "leave phase1 schema audit passed\n";
