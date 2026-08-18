<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\System\DataTableColumnMetaService;
use Core\DbPdo;

$expected = [
    ['auth_users', 'id', 'user_id'],
    ['auth_users', 'username', 'username'],
    ['auth_users', 'approved', 'approved'],
    ['auth_users', 'role_id', 'role_id'],
    ['auth_users', 'is_active', 'is_active'],
    ['auth_roles', 'role_key', 'role_key'],
    ['auth_roles', 'role_name', 'role_name'],
    ['auth_roles', 'is_active', 'role_active'],
    ['user_employees', 'sort_no', 'sort_no'],
    ['user_employees', 'employee_name', 'employee_name'],
    ['user_employees', 'employment_status', 'employment_status'],
    ['user_employees', 'doc_retire_date', 'doc_retire_date'],
    ['user_employees', 'real_retire_date', 'real_retire_date'],
    ['auth_user_permission_profiles', 'permission_mode', 'permission_mode'],
    ['auth_user_permissions', 'id', 'user_permission_count'],
];

$columns = (new DataTableColumnMetaService(DbPdo::conn()))
    ->columnsForDomain('individual-permission-users');
$actual = array_map(
    static fn(array $column): array => [
        (string) ($column['table'] ?? ''),
        (string) ($column['source_column'] ?? ''),
        (string) ($column['key'] ?? ''),
    ],
    $columns
);
$errors = $actual === $expected ? [] : ['개인권한 사용자목록의 테이블·컬럼·순서가 조회 계약과 다릅니다.'];

echo json_encode([
    'expected_count' => count($expected),
    'actual_count' => count($actual),
    'tables' => array_values(array_unique(array_column($actual, 0))),
    'expected' => $expected,
    'actual' => $actual,
    'errors' => $errors,
    'success' => $errors === [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if ($errors !== []) {
    exit(1);
}
