<?php
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

$pdo = Core\DbPdo::conn();
foreach ([
    'page_total' => 'SELECT COUNT(*) FROM system_page_registry',
    'permission_total' => 'SELECT COUNT(*) FROM auth_permissions',
    'leave_permission_total' => "SELECT COUNT(*) FROM auth_permissions WHERE permission_key LIKE '%leave%'",
    'leave_role_mapping_total' => "SELECT COUNT(*) FROM auth_role_permissions rp JOIN auth_permissions p ON p.id=rp.permission_id WHERE p.permission_key LIKE '%leave%'",
    'leave_user_mapping_total' => "SELECT COUNT(*) FROM auth_user_permissions up JOIN auth_permissions p ON p.id=up.permission_id WHERE p.permission_key LIKE '%leave%'",
    'leave_super_admin_mapping_total' => "SELECT COUNT(*) FROM auth_role_permissions rp JOIN auth_roles r ON r.id=rp.role_id JOIN auth_permissions p ON p.id=rp.permission_id WHERE r.role_key='super_admin' AND p.permission_key LIKE '%leave%'",
] as $label => $sql) echo "SNAPSHOT:$label=" . $pdo->query($sql)->fetchColumn() . PHP_EOL;
$tables = ['system_page_registry', 'auth_permissions', 'auth_role_permissions', 'auth_user_permissions', 'auth_user_permission_profiles'];
foreach ($tables as $table) {
    echo "TABLE:$table\n";
    $statement = $pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLUMN_KEY,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $statement->execute([$table]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) echo json_encode($row) . PHP_EOL;
}
foreach ([
    'LEAVE_PAGES' => "SELECT * FROM system_page_registry WHERE page_key LIKE '%leave%' OR default_route_url LIKE '%leave%'",
    'APPROVAL_PAGES' => "SELECT * FROM system_page_registry WHERE page_key LIKE 'approval.%' ORDER BY page_key",
    'LEAVE_PERMISSIONS' => "SELECT id,permission_key,permission_name,page_key,is_active FROM auth_permissions WHERE permission_key LIKE '%leave%' ORDER BY permission_key",
    'ROLE_MAPPINGS' => "SELECT r.role_key,p.permission_key,COUNT(*) mapping_count FROM auth_role_permissions rp JOIN auth_roles r ON r.id=rp.role_id JOIN auth_permissions p ON p.id=rp.permission_id WHERE p.permission_key LIKE '%leave%' GROUP BY r.role_key,p.permission_key ORDER BY r.role_key,p.permission_key",
    'USER_MAPPINGS' => "SELECT up.user_id,p.permission_key,pr.permission_mode FROM auth_user_permissions up JOIN auth_permissions p ON p.id=up.permission_id LEFT JOIN auth_user_permission_profiles pr ON pr.user_id=up.user_id WHERE p.permission_key LIKE '%leave%' ORDER BY up.user_id,p.permission_key",
] as $label => $sql) {
    echo "$label\n";
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
