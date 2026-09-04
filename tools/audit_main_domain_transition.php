<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$pdo = DbPdo::conn();

$scalar = static function (string $sql, array $params = []) use ($pdo): int {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
};
$column = static function (string $sql) use ($pdo): array {
    return array_map('strval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: []);
};

$summary = [
    'dashboard_named_tables' => $scalar(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'dashboard\\_%'"
    ),
    'main_named_tables' => $scalar(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'main\\_%'"
    ),
    'legacy_page_registry_rows' => $scalar(
        "SELECT COUNT(*) FROM system_page_registry WHERE module_key = 'dashboard' OR page_key LIKE 'dashboard.%' OR menu_key LIKE 'dashboard.%' OR default_route_key LIKE '%.dashboard.%' OR default_route_url LIKE '/dashboard%'"
    ),
    'canonical_main_page_registry_rows' => $scalar(
        "SELECT COUNT(*) FROM system_page_registry WHERE module_key = 'main' OR page_key LIKE 'main.%'"
    ),
    'legacy_menu_registry_rows' => $scalar(
        "SELECT COUNT(*) FROM system_menu_registry WHERE module_key = 'dashboard' OR menu_key LIKE 'dashboard.%' OR page_key LIKE 'dashboard.%' OR default_entry LIKE '/dashboard%'"
    ),
    'legacy_permission_keys' => $scalar(
        "SELECT COUNT(*) FROM auth_permissions WHERE permission_key LIKE 'web.dashboard.%' OR permission_key LIKE 'api.dashboard.%'"
    ),
    'legacy_permission_page_keys' => $scalar(
        "SELECT COUNT(*) FROM auth_permissions WHERE page_key LIKE 'dashboard.%'"
    ),
    'legacy_user_setting_page_keys' => $scalar(
        "SELECT COUNT(*) FROM system_user_settings WHERE page_key LIKE 'dashboard.%'"
    ),
    'legacy_notification_page_keys' => $scalar(
        "SELECT COUNT(*) FROM system_notification_recipients WHERE action_page_key LIKE 'dashboard.%' OR action_url_fallback LIKE '/dashboard%'"
    ),
    'legacy_top_level_page_keys' => $column(
        "SELECT page_key FROM system_page_registry WHERE page_key LIKE 'dashboard.%' ORDER BY page_key"
    ),
    'legacy_permission_page_key_values' => $column(
        "SELECT CONCAT(COALESCE(page_key, ''), ' = ', COUNT(*)) FROM auth_permissions WHERE page_key LIKE 'dashboard.%' GROUP BY page_key ORDER BY page_key"
    ),
    'legacy_user_setting_page_key_values' => $column(
        "SELECT CONCAT(page_key, ' = ', COUNT(*)) FROM system_user_settings WHERE page_key LIKE 'dashboard.%' GROUP BY page_key ORDER BY page_key"
    ),
];

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
