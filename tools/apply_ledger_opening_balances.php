<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = strtolower((string) ($argv[1] ?? 'preflight'));
if (!in_array($mode, ['preflight', 'test', 'up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_ledger_opening_balances.php [preflight|test|up|verify]');
}
$db = DbPdo::conn();
$tableCount = static fn(): int => (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_opening_balances'")->fetchColumn();
$triggerCount = static fn(): int => (int) $db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
$state = static function () use ($db, $tableCount, $triggerCount): array {
    $exists = $tableCount() === 1;
    return [
        'table_exists' => $exists,
        'column_count' => $exists ? (int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_opening_balances'")->fetchColumn() : 0,
        'row_count' => $exists ? (int) $db->query('SELECT COUNT(*) FROM ledger_opening_balances')->fetchColumn() : 0,
        'trigger_count' => $triggerCount(),
        'legacy_permission_count' => (int) $db->query("SELECT COUNT(*) FROM auth_permissions WHERE permission_key='web.ledger.opening_balances'")->fetchColumn(),
        'canonical_registry_count' => (int) $db->query("SELECT COUNT(*) FROM system_page_registry WHERE page_key='ledger.settings.opening_balances' AND default_route_key='web.ledger.settings.opening_balances' AND default_route_url='/ledger/settings/opening-balances'")->fetchColumn(),
    ];
};
if ($mode === 'preflight') {
    echo json_encode(['mode'=>$mode,'state'=>$state()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
$executeMigration = static function (PDO $connection): void {
    $sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260904_05_create_ledger_opening_balances.up.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) $connection->exec($statement);
};
if ($mode === 'test') {
    $sourceDatabase = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    $temporaryDatabase = 'tmp_ledger_opening_balance_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
    if (!preg_match('/^tmp_ledger_opening_balance_[0-9]{14}_[a-f0-9]{6}$/', $temporaryDatabase)) throw new RuntimeException('격리 DB 이름 검증에 실패했습니다.');
    try {
        $db->exec("CREATE DATABASE `{$temporaryDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        foreach (['system_company','ledger_vouchers','system_page_registry','auth_permissions'] as $table) {
            $db->exec("CREATE TABLE `{$temporaryDatabase}`.`{$table}` LIKE `{$sourceDatabase}`.`{$table}`");
        }
        $db->exec("INSERT INTO `{$temporaryDatabase}`.`system_page_registry` SELECT * FROM `{$sourceDatabase}`.`system_page_registry` WHERE page_key='ledger.settings.opening_balances'");
        $db->exec("INSERT INTO `{$temporaryDatabase}`.`auth_permissions` SELECT * FROM `{$sourceDatabase}`.`auth_permissions` WHERE permission_key='web.ledger.opening_balances'");
        $db->exec("USE `{$temporaryDatabase}`");
        $executeMigration($db);
        $testState = $state();
        if (!$testState['table_exists'] || $testState['column_count'] !== 10 || $testState['legacy_permission_count'] !== 0 || $testState['canonical_registry_count'] !== 1) {
            throw new RuntimeException('격리 Migration 불변식을 충족하지 못했습니다.');
        }
        echo json_encode(['mode'=>$mode,'passed'=>true,'state'=>$testState,'temporary_database_removed'=>true], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    } finally {
        $db->exec("USE `{$sourceDatabase}`");
        $db->exec("DROP DATABASE IF EXISTS `{$temporaryDatabase}`");
    }
    exit;
}
if ($mode === 'up') {
    if ($tableCount() !== 0) throw new RuntimeException('ledger_opening_balances 테이블이 이미 존재합니다.');
    $beforeTriggers = $triggerCount();
    $executeMigration($db);
    $after = $state();
    if (!$after['table_exists'] || $after['column_count'] !== 10 || $after['trigger_count'] !== $beforeTriggers || $after['legacy_permission_count'] !== 0 || $after['canonical_registry_count'] !== 1) {
        throw new RuntimeException('기초금액 Migration 적용 후 불변식을 충족하지 못했습니다.');
    }
    echo json_encode(['mode'=>$mode,'state'=>$after], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
$current = $state();
$passed = $current['table_exists'] && $current['column_count'] === 10 && $current['legacy_permission_count'] === 0 && $current['canonical_registry_count'] === 1;
echo json_encode(['mode'=>$mode,'passed'=>$passed,'state'=>$current], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($passed ? 0 : 1);
