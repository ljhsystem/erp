<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$db = DbPdo::conn();
$source = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$fixture = 'sukhyang_business_memo_fixture_' . date('YmdHis') . '_' . random_int(1000, 9999);
$execute = static function (PDO $connection, string $path): void {
    $delimiter=';';$buffer='';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {$delimiter=$match[1];continue;}
        $buffer.=$line."\n";$trimmed=rtrim($buffer);
        if (!str_ends_with($trimmed,$delimiter)) continue;
        $statement=trim(substr($trimmed,0,-strlen($delimiter)));
        if ($statement!=='') $connection->exec($statement);
        $buffer='';
    }
};
try {
    $db->exec("CREATE DATABASE `$fixture` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $db->exec("CREATE TABLE `$fixture`.`institution_business_incomes` LIKE `$source`.`institution_business_incomes`");
    $before=(int)$db->query("SELECT COUNT(*) FROM `$source`.`institution_business_incomes`")->fetchColumn();
    $db->exec("INSERT INTO `$fixture`.`institution_business_incomes` SELECT * FROM `$source`.`institution_business_incomes`");
    $db->exec("USE `$fixture`");
    $execute($db,PROJECT_ROOT.'/app/migrations/20260903_17_add_business_income_header_memo.up.sql');
    $column=$db->query("SELECT DATA_TYPE,IS_NULLABLE,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_incomes' AND COLUMN_NAME='memo'")->fetch(PDO::FETCH_ASSOC);
    $after=(int)$db->query('SELECT COUNT(*) FROM institution_business_incomes')->fetchColumn();
    if ($before!==$after||($column['DATA_TYPE']??'')!=='text'||($column['IS_NULLABLE']??'')!=='YES'||($column['COLUMN_COMMENT']??'')!=='메모') throw new RuntimeException('사업소득 메모 격리검증에 실패했습니다.');
    echo json_encode(['success'=>true,'mariadb_version'=>$db->query('SELECT VERSION()')->fetchColumn(),'rows_preserved'=>$after,'column'=>$column,'fixture_removed'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
} finally {
    try {$db->exec("USE `$source`");$db->exec("DROP DATABASE IF EXISTS `$fixture`");} catch (Throwable) {}
}
