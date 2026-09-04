<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\System\DataTableColumnMetaService;
use App\Services\Institution\BusinessIncomeService;
use Core\DbPdo;

$db = DbPdo::conn();
$service = new DataTableColumnMetaService($db);
$domains = ['regular-employment-income', 'daily-employment-income', 'business-income', 'client'];
$results = [];
foreach ($domains as $domain) {
    $columns = $service->columnsForDomain($domain);
    $keys = array_column($columns, 'key');
    $results[$domain] = [
        'count' => count($columns),
        'unique_keys' => count($keys) === count(array_unique($keys)),
        'qualified_business_keys' => $domain === 'business-income'
            ? array_values(array_filter($keys, static fn(string $key): bool => str_contains($key, '.')))
            : [],
        'virtual_keys' => array_values(array_column(array_filter(
            $columns,
            static fn(array $column): bool => ($column['column_type'] ?? '') === 'virtual'
        ), 'key')),
    ];
}

$business = $service->columnsForDomain('business-income');
$physical = array_values(array_filter($business, static fn(array $column): bool => ($column['column_type'] ?? '') === 'physical'));
$databasePhysicalCount = (int) $db->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_incomes'"
)->fetchColumn();
$businessKeys = array_column($business, 'key');
$businessService = new BusinessIncomeService($db);
$listResult = $businessService->page(['start' => 0, 'length' => 10]);
$trashResult = $businessService->trash();
$success = count($physical) === $databasePhysicalCount
    && $results['business-income']['qualified_business_keys'] === []
    && $results['business-income']['virtual_keys'] === ['__select', '__reorder', '__actions']
    && array_search('__select', $businessKeys, true) === 0
    && array_search('__reorder', $businessKeys, true) === 1
    && array_search('__actions', $businessKeys, true) === count($businessKeys) - 1
    && !in_array(false, array_column($results, 'unique_keys'), true)
    && ($listResult['success'] ?? false) === true
    && ($trashResult['success'] ?? false) === true;

echo json_encode([
    'success' => $success,
    'database_physical_count' => $databasePhysicalCount,
    'service_physical_count' => count($physical),
    'business_columns' => array_map(static fn(array $column): array => [
        'key' => $column['key'],
        'source_table' => $column['source_table'] ?? '',
        'source_column' => $column['source_column'] ?? '',
        'column_type' => $column['column_type'] ?? '',
        'nullable' => $column['nullable'] ?? null,
        'data_type' => $column['data_type'] ?? '',
    ], $business),
    'regression_domains' => $results,
    'list_success' => $listResult['success'] ?? false,
    'trash_success' => $trashResult['success'] ?? false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($success ? 0 : 1);
