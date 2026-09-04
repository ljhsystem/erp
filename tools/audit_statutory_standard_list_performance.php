<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Services\System\StatutoryStandardService;
$db = Core\Database::getInstance()->getConnection();
$questions = static function () use ($db): int {
    $row = $db->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(PDO::FETCH_ASSOC) ?: [];
    return (int)($row['Value'] ?? 0);
};
$hash = static function (string $table) use ($db): string {
    $rows = $db->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};
$columns = [
    ['data' => '__select', 'name' => '__select'],
    ['data' => '__reorder', 'name' => '__reorder'],
    ['data' => 'sort_no', 'name' => 'sort_no'],
    ['data' => 'standard_combination_name', 'name' => 'standard_type_code'],
    ['data' => 'effective_from', 'name' => 'effective_from'],
    ['data' => 'effective_to', 'name' => 'effective_to'],
    ['data' => 'value_summary', 'name' => 'value_summary'],
    ['data' => 'source_count', 'name' => 'source_count'],
    ['data' => 'note', 'name' => 'note'],
    ['data' => 'created_by', 'name' => 'created_by'],
    ['data' => 'created_at', 'name' => 'created_at'],
    ['data' => 'updated_by', 'name' => 'updated_by'],
    ['data' => 'updated_at', 'name' => 'updated_at'],
    ['data' => 'period_status', 'name' => 'period_status'],
    ['data' => '__actions', 'name' => '__actions'],
];
$query = [
    'draw' => 1,
    'start' => 0,
    'length' => 200,
    'filters' => '[]',
    'search' => ['value' => ''],
    'columns' => $columns,
    'order' => [['column' => 3, 'dir' => 'asc']],
];

$before = $questions();
$started = hrtime(true);
$result = (new StatutoryStandardService($db))->list($query);
$elapsedMs = (hrtime(true) - $started) / 1_000_000;
$after = $questions();
$rows = (array)($result['data'] ?? []);
$summaries = array_map(static fn(array $row): string => (string)($row['value_summary'] ?? ''), $rows);

echo json_encode([
    'read_only' => true,
    'database' => $db->query('SELECT DATABASE()')->fetchColumn(),
    'row_count' => count($rows),
    'summary_key_count' => count(array_filter($rows, static fn(array $row): bool => array_key_exists('value_summary', $row))),
    'summary_empty_count' => count(array_filter($summaries, static fn(string $value): bool => trim($value) === '')),
    'summary_dash_count' => count(array_filter($summaries, static fn(string $value): bool => trim($value) === '-')),
    'summary_numeric_one_count' => count(array_filter($summaries, static fn(string $value): bool => $value === '1')),
    'service_time_ms' => round($elapsedMs, 3),
    'sql_question_count' => max(0, $after - $before - 1),
    'response_bytes' => strlen(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    'hashes' => [
        'standards' => $hash('system_statutory_standards'),
        'sources' => $hash('system_statutory_standard_sources'),
        'calculation_results' => $hash('institution_daily_employment_income_calculation_results'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
