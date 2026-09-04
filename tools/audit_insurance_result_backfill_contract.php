<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db = Core\Database::getInstance()->getConnection();
$resultIds = [
    'd4b2ce27-73c0-4d93-9286-523bc286560a',
    '4992fec8-d2a5-4618-ac31-604eab26dde8',
    'cd5820dc-849c-45fd-a7c2-7e7e9157212f',
];
$placeholders = implode(',', array_fill(0, count($resultIds), '?'));
$statement = $db->prepare("SELECT * FROM institution_daily_employment_income_calculation_results WHERE id IN ({$placeholders}) ORDER BY result_type_code,id");
$statement->execute($resultIds);
$rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

$canonicalize = static function (mixed $value) use (&$canonicalize): mixed {
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map($canonicalize, $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = $canonicalize($item);
    }
    return $value;
};

foreach ($rows as &$row) {
    $snapshot = json_decode((string)($row['eligibility_snapshot'] ?? ''), true);
    $row['eligibility_snapshot_decoded'] = $snapshot;
    $row['eligibility_snapshot_canonical_hash'] = hash('sha256', json_encode($canonicalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    $row['row_canonical_hash'] = hash('sha256', json_encode($canonicalize($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
}
unset($row);

echo json_encode([
    'read_only' => true,
    'database' => $db->query('SELECT DATABASE()')->fetchColumn(),
    'version' => $db->query('SELECT VERSION()')->fetchColumn(),
    'result_count' => count($rows),
    'results' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
