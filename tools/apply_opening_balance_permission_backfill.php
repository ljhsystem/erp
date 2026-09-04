<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = strtolower((string) ($argv[1] ?? 'preflight'));
if (!in_array($mode, ['preflight','up','verify'], true)) throw new InvalidArgumentException('사용법: php tools/apply_opening_balance_permission_backfill.php [preflight|up|verify]');
$db = DbPdo::conn();
$candidateSql = "SELECT COUNT(*) FROM auth_user_permission_profiles profile
 JOIN auth_user_permissions web_map ON web_map.user_id=profile.user_id
 JOIN auth_permissions web_permission ON web_permission.id=web_map.permission_id AND web_permission.permission_key='web.ledger.settings.opening_balances'
 CROSS JOIN auth_permissions api_permission
 LEFT JOIN auth_user_permissions existing ON existing.user_id=profile.user_id AND existing.permission_id=api_permission.id
 WHERE profile.permission_mode='REPLACE' AND api_permission.permission_key LIKE 'api.ledger.opening_balance.%' AND existing.id IS NULL";
$state = static fn(): array => [
    'api_permission_count'=>(int)$db->query("SELECT COUNT(*) FROM auth_permissions WHERE permission_key LIKE 'api.ledger.opening_balance.%'")->fetchColumn(),
    'missing_personal_links'=>(int)$db->query($candidateSql)->fetchColumn(),
    'backfill_links'=>(int)$db->query("SELECT COUNT(*) FROM auth_user_permissions WHERE created_by='SYSTEM:MIGRATION:OPENING_BALANCE_PERMISSION_BACKFILL'")->fetchColumn(),
];
if ($mode === 'preflight') { echo json_encode(['mode'=>$mode,'state'=>$state()],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL; exit; }
if ($mode === 'up') {
    $sql=(string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260904_06_backfill_opening_balance_personal_permissions.up.sql');
    foreach(array_filter(array_map('trim',explode(';',$sql))) as $statement)$db->exec($statement);
}
$current=$state();$passed=$current['api_permission_count']===11&&$current['missing_personal_links']===0;
echo json_encode(['mode'=>$mode,'passed'=>$passed,'state'=>$current],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
exit($passed?0:1);
