<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$stmt = $pdo->prepare(
    "SELECT settings_json
       FROM system_user_settings
      WHERE page_key = 'institution.human_resources.employment_contracts'
        AND setting_type = 'TABLE'
      ORDER BY updated_at DESC"
);
$stmt->execute();
$settingsRows = [];
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $json) {
    $settings = json_decode((string) $json, true);
    if (!is_array($settings)) continue;
    $settingsRows[] = [
        'visibleColumns' => array_values($settings['visibleColumns'] ?? []),
        'columnOrder' => array_values($settings['columnOrder'] ?? []),
        'columnDisplayName' => $settings['columnDisplayName'] ?? [],
    ];
}

echo json_encode([
    'success' => true,
    'settings_count' => count($settingsRows),
    'settings' => $settingsRows,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
