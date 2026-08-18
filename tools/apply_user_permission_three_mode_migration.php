<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$mode = $argv[1] ?? 'verify';
$scalar = static fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();
$exists = static fn(string $table): bool => (bool) $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn();

if ($mode === 'up') {
    foreach (['auth_user_permission_overrides', 'auth_user_permission_override_audits'] as $table) {
        if (!$exists($table)) throw new RuntimeException("Legacy 테이블이 없습니다: {$table}");
        if ($scalar("SELECT COUNT(*) FROM `{$table}`") !== 0) throw new RuntimeException("Legacy 테이블에 데이터가 있어 중단합니다: {$table}");
    }
    $sql = file_get_contents(dirname(__DIR__) . '/app/migrations/20260814_05_finalize_user_permissions_three_modes.up.sql');
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', (string) $sql))) as $statement) {
        if (str_starts_with($statement, '--')) $statement = preg_replace('/^(?:--[^\n]*\n)+/', '', $statement);
        if (trim((string) $statement) !== '') $pdo->exec($statement);
    }
}

$tables = ['auth_user_permission_profiles','auth_user_permissions','auth_user_permission_audits'];
$result = [
    'legacy_exists' => array_filter(['overrides' => $exists('auth_user_permission_overrides'), 'audits' => $exists('auth_user_permission_override_audits')]),
    'new_tables' => array_map($exists, $tables),
    'users' => $scalar('SELECT COUNT(*) FROM auth_users'),
    'profiles' => $exists($tables[0]) ? $scalar('SELECT COUNT(*) FROM auth_user_permission_profiles') : null,
    'role_profiles' => $exists($tables[0]) ? $scalar("SELECT COUNT(*) FROM auth_user_permission_profiles WHERE permission_mode='ROLE'") : null,
    'user_permissions' => $exists($tables[1]) ? $scalar('SELECT COUNT(*) FROM auth_user_permissions') : null,
    'audits' => $exists($tables[2]) ? $scalar('SELECT COUNT(*) FROM auth_user_permission_audits') : null,
    'canonical_permissions' => $scalar("SELECT COUNT(*) FROM auth_permissions WHERE permission_key IN ('api.settings.user_permission.list','api.settings.user_permission.detail','api.settings.user_permission.save')"),
    'super_admin_mapping' => $scalar("SELECT COUNT(*) FROM auth_role_permissions rp JOIN auth_roles r ON r.id=rp.role_id JOIN auth_permissions p ON p.id=rp.permission_id WHERE r.role_key='super_admin' AND p.permission_key LIKE 'api.settings.user_permission.%'"),
    'admin_mapping' => $scalar("SELECT COUNT(*) FROM auth_role_permissions rp JOIN auth_roles r ON r.id=rp.role_id JOIN auth_permissions p ON p.id=rp.permission_id WHERE r.role_key='admin' AND p.permission_key LIKE 'api.settings.user_permission.%'"),
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
