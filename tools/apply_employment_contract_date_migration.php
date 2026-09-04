<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = Core\DbPdo::conn();
$exists = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_employment_contracts' AND COLUMN_NAME='contract_date'")->fetchColumn();
if ((int) $exists === 0) {
    $sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260822_09_add_employment_contract_date.up.sql');
    $db->exec($sql);
}
$column = $db->query("SELECT IS_NULLABLE,COLUMN_TYPE,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_employment_contracts' AND COLUMN_NAME='contract_date'")->fetch(PDO::FETCH_ASSOC);
if (!$column || $column['IS_NULLABLE'] !== 'YES' || $column['COLUMN_TYPE'] !== 'date') {
    throw new RuntimeException('contract_date Migration 적용 결과가 올바르지 않습니다.');
}
echo json_encode(['success' => true, 'contract_date' => $column], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
