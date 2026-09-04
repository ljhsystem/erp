<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;
use Core\Helpers\ActorHelper;

$pdo = DbPdo::conn();
$sourceDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$testDatabase = 'codex_personal_expense_code_' . bin2hex(random_bytes(6));
$created = false;
if (!preg_match('/^codex_personal_expense_code_[0-9a-f]{12}$/', $testDatabase)) {
    throw new RuntimeException('허용된 격리 DB 이름이 아닙니다.');
}
$exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:name');
$exists->execute([':name' => $testDatabase]);
if ((int) $exists->fetchColumn() !== 0) {
    throw new RuntimeException('격리 DB 이름이 이미 사용 중입니다.');
}

$execute = static function (PDO $pdo, string $file): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmedBuffer = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmedBuffer, $delimiter)) continue;
        $statement = trim(substr($trimmedBuffer, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
};

$pdo->exec("CREATE DATABASE `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$created = true;
try {
    $pdo->exec("CREATE TABLE `{$testDatabase}`.`system_codes` LIKE `{$sourceDatabase}`.`system_codes`");
    $pdo->exec("INSERT INTO `{$testDatabase}`.`system_codes` SELECT * FROM `{$sourceDatabase}`.`system_codes`");
    $pdo->exec("USE `{$testDatabase}`");
    $pdo->exec('SET @personal_expense_category_actor=' . $pdo->quote(ActorHelper::system('PERSONAL_EXPENSE_CATEGORY_CLOSURE')));
    $execute($pdo, PROJECT_ROOT . '/app/migrations/20260825_01_seed_personal_expense_fees_category.up.sql');
    $codes = $pdo->query("SELECT code,code_name,note,sort_no,is_active FROM system_codes WHERE code_group='PERSONAL_EXPENSE_CATEGORY' AND code IN ('TAXES_AND_DUES','FEES_AND_COMMISSIONS','SUPPLIES') ORDER BY sort_no")->fetchAll(PDO::FETCH_ASSOC);
    if (array_column($codes, 'code') !== ['TAXES_AND_DUES','FEES_AND_COMMISSIONS','SUPPLIES']) {
        throw new RuntimeException('공식 비용분류 정렬 검증에 실패했습니다.');
    }
    echo json_encode(['success' => true, 'codes' => $codes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    $pdo->exec("USE `{$sourceDatabase}`");
    if ($created) $pdo->exec("DROP DATABASE `{$testDatabase}`");
}
