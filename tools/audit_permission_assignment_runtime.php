<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$keys = [
    'web.settings.organization.permission-assignment',
    'api.settings.rolepermission.list',
    'api.settings.rolepermission.assign',
    'api.settings.user_permission_override.list',
    'api.settings.user_permission_override.detail',
    'api.settings.user_permission_override.save',
];
$placeholders = implode(',', array_fill(0, count($keys), '?'));
$statement = $pdo->prepare("SELECT r.role_key, r.role_name, p.permission_key,
        CASE WHEN rp.permission_id IS NULL THEN 0 ELSE 1 END AS assigned
    FROM auth_roles r
    CROSS JOIN auth_permissions p
    LEFT JOIN auth_role_permissions rp ON rp.role_id = r.id AND rp.permission_id = p.id
    WHERE r.is_active = 1 AND p.permission_key IN ({$placeholders})
    ORDER BY r.sort_no, p.permission_key");
$statement->execute($keys);
$matrix = [];
foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $matrix[(string) $row['role_key']][(string) $row['permission_key']] = (int) $row['assigned'];
}

$users = $pdo->query("SELECT u.username, u.approved, u.is_active, r.role_key, r.role_name
    FROM auth_users u LEFT JOIN auth_roles r ON r.id = u.role_id
    ORDER BY u.username")->fetchAll(PDO::FETCH_ASSOC) ?: [];

echo json_encode(['permission_matrix' => $matrix, 'users' => $users], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
