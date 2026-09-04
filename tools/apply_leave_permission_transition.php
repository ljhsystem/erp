<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$direction = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($direction, ['up', 'verify'], true)) {
    throw new InvalidArgumentException('up 또는 verify만 사용할 수 있습니다.');
}
if ($direction === 'up') {
    $sql = file_get_contents(PROJECT_ROOT . '/app/migrations/20260821_01_transition_leave_page_permissions.up.sql');
    if ($sql === false) throw new RuntimeException('Migration SQL을 읽을 수 없습니다.');
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->exec($sql);
}

$employeeKeys = [
    'web.approval.leave-request',
    'api.approval.leave-request.list', 'api.approval.leave-request.options',
    'api.approval.leave-request.detail', 'api.approval.leave-request.save',
    'api.approval.leave-request.save-submit', 'api.approval.leave-request.submit',
    'api.approval.leave-request.withdraw', 'api.approval.leave-request.cancel-request',
];
$adminKeys = [
    'web.institution.human_resources.leave',
    'api.institution.human_resources.leave.status_list',
    'api.institution.human_resources.leave.balance_list',
    'api.institution.human_resources.leave.options',
    'api.institution.human_resources.leave.detail',
    'api.institution.human_resources.leave.grant',
    'api.institution.human_resources.leave.adjust',
    'api.institution.human_resources.leave.type_save',
];
$obsoleteKeys = [
    'api.institution.human_resources.leave.view_self',
    'api.institution.human_resources.leave.view_all',
    'api.institution.human_resources.leave.save',
    'api.institution.human_resources.leave.submit',
    'api.institution.human_resources.leave.withdraw',
    'api.institution.human_resources.leave.cancel',
    'api.institution.human_resources.leave.excel',
];
$countKeys = static function (PDO $pdo, array $keys): int {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM auth_permissions WHERE permission_key IN (' . implode(',', array_fill(0, count($keys), '?')) . ') AND is_active=1');
    $statement->execute($keys);
    return (int) $statement->fetchColumn();
};
$pageCount = (int) $pdo->query("SELECT COUNT(*) FROM system_page_registry WHERE page_key='approval.leave_request' AND default_route_url='/approval/leave-request' AND is_active=1")->fetchColumn();
$adminPageCount = (int) $pdo->query("SELECT COUNT(*) FROM system_page_registry WHERE page_key='web.institution.human_resources.leave' AND default_route_url='/institution/human-resources/leave' AND is_active=1")->fetchColumn();
$employeeCount = $countKeys($pdo, $employeeKeys);
$adminCount = $countKeys($pdo, $adminKeys);
$obsoleteCount = $countKeys($pdo, $obsoleteKeys);
$superAdminCount = (int) $pdo->query("SELECT COUNT(*) FROM auth_role_permissions rp JOIN auth_roles r ON r.id=rp.role_id JOIN auth_permissions p ON p.id=rp.permission_id WHERE r.role_key='super_admin' AND p.permission_key IN ('" . implode("','", array_merge($employeeKeys, $adminKeys)) . "')")->fetchColumn();
if ($pageCount !== 1 || $adminPageCount !== 1 || $employeeCount !== count($employeeKeys) || $adminCount !== count($adminKeys) || $obsoleteCount !== 0 || $superAdminCount !== count(array_unique(array_merge($employeeKeys, $adminKeys)))) {
    throw new RuntimeException('휴가 Page Registry/Permission 전환 검증에 실패했습니다.');
}
echo json_encode([
    'page_registry' => $pageCount,
    'admin_page_registry' => $adminPageCount,
    'employee_permissions' => $employeeCount,
    'admin_permissions' => $adminCount,
    'obsolete_permissions' => $obsoleteCount,
    'super_admin_mappings' => $superAdminCount,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
