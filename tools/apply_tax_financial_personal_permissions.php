<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$mode = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($mode, ['up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_tax_financial_personal_permissions.php [up|verify]');
}

$db = DbPdo::conn();
if ($mode === 'up') {
    $migration = (string) file_get_contents(
        PROJECT_ROOT . '/app/migrations/20260905_08_backfill_tax_financial_personal_permissions.up.sql'
    );
    $db->exec($migration);
}

$sql = <<<'SQL'
SELECT permission.permission_key,
       SUM(profile.permission_mode='REPLACE') AS replace_user_count,
       SUM(profile.permission_mode='REPLACE' AND mapping.id IS NOT NULL) AS granted_user_count
FROM auth_permissions permission
LEFT JOIN auth_user_permission_profiles profile ON profile.permission_mode='REPLACE'
LEFT JOIN auth_user_permissions mapping
  ON mapping.user_id=profile.user_id
 AND mapping.permission_id=permission.id
WHERE permission.permission_key LIKE 'api.ledger.tax_financial.%'
GROUP BY permission.id, permission.permission_key
ORDER BY permission.permission_key
SQL;

$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$ready = count($rows) === 8;
foreach ($rows as $row) {
    if ((int) $row['replace_user_count'] !== (int) $row['granted_user_count']) {
        $ready = false;
    }
}

echo json_encode(
    ['mode' => $mode, 'permissions' => $rows, 'ready' => $ready],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
) . PHP_EOL;
exit($ready ? 0 : 1);
