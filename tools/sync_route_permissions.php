<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;
use Core\PermissionRegistry;
use Core\Router;

if (!in_array('--apply', $argv, true)) {
    fwrite(STDERR, "실행하려면 --apply 옵션이 필요합니다.\n");
    exit(2);
}

$pdo = DbPdo::conn();
$router = new Router();
ob_start();
require PROJECT_ROOT . '/routes/web.php';
require PROJECT_ROOT . '/routes/api.php';
ob_end_clean();

$registeredCount = count(PermissionRegistry::all());
$conflicts = PermissionRegistry::conflicts();
if ($registeredCount < 600 || $conflicts !== []) {
    fwrite(STDERR, json_encode([
        'success' => false,
        'message' => '전체 Route가 안전하게 로드되지 않아 Permission 동기화를 중단했습니다.',
        'registered_permissions' => $registeredCount,
        'metadata_conflicts' => count($conflicts),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}

$beforeCount = (int) $pdo->query('SELECT COUNT(*) FROM auth_permissions')->fetchColumn();
putenv('ERP_PERMISSION_ROUTE_HARD_DELETE_ENABLED=1');
PermissionRegistry::syncToDatabase($pdo);
putenv('ERP_PERMISSION_ROUTE_HARD_DELETE_ENABLED');
$afterCount = (int) $pdo->query('SELECT COUNT(*) FROM auth_permissions')->fetchColumn();

echo json_encode([
    'success' => true,
    'registered_permissions' => $registeredCount,
    'before_permissions' => $beforeCount,
    'after_permissions' => $afterCount,
    'deleted_permissions' => $beforeCount - $afterCount,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
