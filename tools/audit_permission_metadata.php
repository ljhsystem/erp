<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Auth\RolePermissionService;
use Core\DbPdo;
use Core\Helpers\PermissionPresentationHelper;
use Core\Helpers\PermissionSourceHelper;
use Core\PermissionRegistry;
use Core\Router;

$pdo = DbPdo::conn();
$allPermissionRows = $pdo->query(
    'SELECT id, permission_key, permission_name, description, category, page_key, page, permission_source, is_active '
    . 'FROM auth_permissions ORDER BY sort_no ASC, permission_key ASC'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$permissionRows = array_values(array_filter(
    $allPermissionRows,
    static fn(array $row): bool => (int) ($row['is_active'] ?? 0) === 1
));
$pageKeys = array_fill_keys($pdo->query(
    'SELECT page_key FROM system_page_registry WHERE is_active = 1'
)->fetchAll(PDO::FETCH_COLUMN) ?: [], true);

$router = new Router();
ob_start();
require PROJECT_ROOT . '/routes/web.php';
require PROJECT_ROOT . '/routes/api.php';
ob_end_clean();
$registeredKeys = array_fill_keys(array_keys(PermissionRegistry::all()), true);
$databaseKeys = array_fill_keys(array_map(static fn(array $row): string => (string) $row['permission_key'], $permissionRows), true);
$runtimePermissions = array_values(array_filter(
    $permissionRows,
    static fn(array $row): bool => isset($registeredKeys[(string) ($row['permission_key'] ?? '')])
));
$routeWithoutDatabase = array_values(array_diff(array_keys($registeredKeys), array_keys($databaseKeys)));
$databaseWithoutRoute = array_values(array_diff(array_keys($databaseKeys), array_keys($registeredKeys)));
$physicalDeleteCandidates = array_values(array_filter(
    $allPermissionRows,
    static fn(array $row): bool => !isset($registeredKeys[(string) ($row['permission_key'] ?? '')])
));
$physicalDeleteCandidateIds = array_map(static fn(array $row): string => (string) $row['id'], $physicalDeleteCandidates);
$countMappings = static function (string $table) use ($pdo, $physicalDeleteCandidateIds): int {
    if ($physicalDeleteCandidateIds === []) return 0;
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE permission_id IN ("
        . implode(',', array_fill(0, count($physicalDeleteCandidateIds), '?'))
        . ')'
    );
    $statement->execute($physicalDeleteCandidateIds);
    return (int) $statement->fetchColumn();
};

$issues = [
    'missing_page_key' => [],
    'unknown_page_key' => [],
    'invalid_source' => [],
    'technical_original_name' => [],
    'presentation_review_required' => [],
    'missing_description' => [],
];

foreach ($runtimePermissions as $permission) {
    $key = (string) ($permission['permission_key'] ?? '');
    $pageKey = trim((string) ($permission['page_key'] ?? ''));
    $name = trim((string) ($permission['permission_name'] ?? ''));
    $description = trim((string) ($permission['description'] ?? ''));
    $presentation = PermissionPresentationHelper::decorate($permission, trim((string) ($permission['page'] ?? '')) ?: '미분류 페이지');

    if ($pageKey === '') $issues['missing_page_key'][] = $key;
    elseif (!isset($pageKeys[$pageKey])) $issues['unknown_page_key'][] = $key . ' -> ' . $pageKey;
    if (!in_array(PermissionSourceHelper::resolve($permission), ['web', 'api'], true)) $issues['invalid_source'][] = $key;
    if ($name === '' || preg_match('/^[A-Za-z0-9_.\- ]+$/', $name) === 1) $issues['technical_original_name'][] = $key . ' -> ' . $name;
    if (($presentation['metadata_status'] ?? '') === 'REVIEW_REQUIRED') $issues['presentation_review_required'][] = $key;
    if ($description === '') $issues['missing_description'][] = $key;
}

$tree = (new RolePermissionService($pdo))->getPermissionTreeForRole('');
$virtualPages = array_values(array_map(
    static fn(array $page): string => (string) ($page['page_key'] ?? ''),
    array_filter($tree, static fn(array $page): bool => str_starts_with((string) ($page['page_key'] ?? ''), 'virtual.'))
));

$exampleLimit = in_array('--full', $argv, true) ? PHP_INT_MAX : 20;
$summary = [
    'active_permissions' => count($permissionRows),
    'runtime_permissions' => count($runtimePermissions),
    'route_without_database' => count($routeWithoutDatabase),
    'database_without_route' => count($databaseWithoutRoute),
    'physical_delete_candidates' => count($physicalDeleteCandidates),
    'physical_delete_role_mappings' => $countMappings('auth_role_permissions'),
    'physical_delete_user_mappings' => $countMappings('auth_user_permissions'),
    'permission_pages' => count($tree),
    'virtual_pages' => count($virtualPages),
    'issues' => array_map('count', $issues),
    'examples' => array_map(static fn(array $rows): array => array_slice($rows, 0, $exampleLimit), $issues),
    'route_without_database_examples' => array_slice($routeWithoutDatabase, 0, 20),
    'database_without_route_examples' => array_slice($databaseWithoutRoute, 0, 20),
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(($summary['issues']['presentation_review_required'] > 0 || $summary['route_without_database'] > 0) ? 1 : 0);
