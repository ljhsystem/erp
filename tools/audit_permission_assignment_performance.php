<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;
$pdo = DbPdo::conn();
$result = ['indexes' => [], 'explain' => []];

foreach (['auth_user_permission_profiles', 'auth_user_permissions', 'auth_role_permissions', 'auth_permissions'] as $table) {
    $result['indexes'][$table] = array_map(
        static fn(array $row): array => [
            'key' => $row['Key_name'],
            'sequence' => (int) $row['Seq_in_index'],
            'column' => $row['Column_name'],
            'non_unique' => (int) $row['Non_unique'],
        ],
        $pdo->query('SHOW INDEX FROM ' . $table)->fetchAll(\PDO::FETCH_ASSOC) ?: []
    );
}

$queries = [
    'user_list' => "SELECT u.id,u.username,r.role_key,e.employee_name,
            COALESCE(pr.permission_mode,'ROLE') permission_mode,COALESCE(pc.permission_count,0) permission_count
        FROM auth_users u
        LEFT JOIN auth_roles r ON r.id=u.role_id
        LEFT JOIN user_employees e ON e.user_id=u.id
        LEFT JOIN auth_user_permission_profiles pr ON pr.user_id=u.id
        LEFT JOIN (SELECT user_id,COUNT(*) permission_count FROM auth_user_permissions GROUP BY user_id) pc ON pc.user_id=u.id",
    'user_permission_set' => "SELECT permission_id FROM auth_user_permissions
        WHERE user_id='00000000-0000-0000-0000-000000000000'",
    'role_permission_set' => "SELECT rp.permission_id FROM auth_role_permissions rp
        JOIN auth_permissions p ON p.id=rp.permission_id AND p.is_active=1
        WHERE rp.role_id='00000000-0000-0000-0000-000000000000'",
];

foreach ($queries as $name => $query) {
    $result['explain'][$name] = $pdo->query('EXPLAIN ' . $query)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
