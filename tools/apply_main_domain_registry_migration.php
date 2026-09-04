<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = $argv[1] ?? 'preflight';
if (!in_array($mode, ['preflight', 'test', 'up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_main_domain_registry_migration.php [preflight|test|up|verify]');
}

$pdo = DbPdo::conn();
$migration = PROJECT_ROOT . '/app/migrations/20260904_02_complete_main_domain_registry.up.sql';
$execute = static function (PDO $connection, string $path): void {
    $sql = (string) file_get_contents($path);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $connection->exec($statement);
    }
};
$count = static function (PDO $connection, string $sql): int {
    return (int) $connection->query($sql)->fetchColumn();
};
$snapshot = static function () use ($pdo, $count): array {
    return [
        'dashboard_tables' => $count($pdo, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'dashboard\\_%'"),
        'main_tables' => $count($pdo, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'main\\_%'"),
        'dashboard_pages' => $count($pdo, "SELECT COUNT(*) FROM system_page_registry WHERE page_key LIKE 'dashboard.%'"),
        'main_pages' => $count($pdo, "SELECT COUNT(*) FROM system_page_registry WHERE page_key IN ('main.dashboard','main.report','main.activity','main.notifications','main.kpi','main.calendar','main.settings')"),
        'dashboard_menus' => $count($pdo, "SELECT COUNT(*) FROM system_menu_registry WHERE menu_key LIKE 'dashboard.%' OR page_key LIKE 'dashboard.%'"),
        'dashboard_permission_pages' => $count($pdo, "SELECT COUNT(*) FROM auth_permissions WHERE page_key LIKE 'dashboard.%'"),
        'dashboard_user_settings' => $count($pdo, "SELECT COUNT(*) FROM system_user_settings WHERE page_key LIKE 'dashboard.%'"),
        'dashboard_registry_urls' => $count($pdo, "SELECT (SELECT COUNT(*) FROM system_page_registry WHERE default_route_url LIKE '/dashboard%') + (SELECT COUNT(*) FROM system_menu_registry WHERE default_entry LIKE '/dashboard%')"),
    ];
};

if ($mode === 'preflight') {
    echo json_encode(['mode' => $mode, 'state' => $snapshot()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

if ($mode === 'test') {
    $pdo->beginTransaction();
    try {
        $before = $snapshot();
        $execute($pdo, $migration);
        $after = $snapshot();
        $pdo->rollBack();
        echo json_encode(['mode' => $mode, 'before' => $before, 'after' => $after, 'rolled_back' => true], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    exit;
}

if ($mode === 'up') {
    $pdo->beginTransaction();
    try {
        $execute($pdo, $migration);
        $after = $snapshot();
        if ($after['dashboard_pages'] !== 0 || $after['main_pages'] !== 7 || $after['dashboard_menus'] !== 0 || $after['dashboard_permission_pages'] !== 0 || $after['dashboard_user_settings'] !== 0 || $after['dashboard_registry_urls'] !== 0) {
            throw new RuntimeException('Main 도메인 Registry 적용 후 불변식을 충족하지 못했습니다.');
        }
        $pdo->commit();
        echo json_encode(['mode' => $mode, 'state' => $after], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    exit;
}

$state = $snapshot();
$passed = $state['dashboard_tables'] === 0 && $state['main_tables'] === 5
    && $state['dashboard_pages'] === 0 && $state['main_pages'] === 7
    && $state['dashboard_menus'] === 0 && $state['dashboard_permission_pages'] === 0
    && $state['dashboard_user_settings'] === 0 && $state['dashboard_registry_urls'] === 0;
echo json_encode(['mode' => $mode, 'passed' => $passed, 'state' => $state], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($passed ? 0 : 1);
