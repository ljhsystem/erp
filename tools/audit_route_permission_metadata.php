<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\Helpers\PermissionPresentationHelper;
use Core\PermissionRegistry;
use Core\Router;

$router = new Router();
require PROJECT_ROOT . '/routes/web.php';
require PROJECT_ROOT . '/routes/api.php';

$issues = [
    'missing_page' => [],
    'missing_category' => [],
    'missing_permission_name' => [],
    'missing_permission_description' => [],
    'presentation_review_required' => [],
];

foreach (PermissionRegistry::all() as $permission) {
    $key = (string) ($permission['key'] ?? '');
    if (trim((string) ($permission['page'] ?? '')) === '') $issues['missing_page'][] = $key;
    if (trim((string) ($permission['category'] ?? '')) === '') $issues['missing_category'][] = $key;
    if (trim((string) ($permission['permission_name'] ?? '')) === '') $issues['missing_permission_name'][] = $key;
    if (trim((string) ($permission['permission_description'] ?? '')) === '') $issues['missing_permission_description'][] = $key;
    $presentation = PermissionPresentationHelper::decorate($permission, (string) ($permission['page'] ?? '미분류 페이지'));
    if (($presentation['metadata_status'] ?? '') === 'REVIEW_REQUIRED') $issues['presentation_review_required'][] = $key;
}

$conflicts = PermissionRegistry::conflicts();
$summary = [
    'registered_permissions' => count(PermissionRegistry::all()),
    'duplicate_metadata_conflicts' => count($conflicts),
    'issues' => array_map('count', $issues),
    'examples' => array_map(static fn(array $rows): array => array_slice($rows, 0, 30), $issues),
    'conflicts' => array_slice($conflicts, 0, 30),
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit((count($conflicts) > 0 || array_sum($summary['issues']) > 0) ? 1 : 0);
