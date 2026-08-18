<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = Core\DbPdo::conn();
$standardColumns = $db->query(
    "SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLUMN_COMMENT FROM information_schema.COLUMNS"
    . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards' ORDER BY ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
echo "--SCHEMA--", PHP_EOL;
foreach ($standardColumns as $column) {
    echo json_encode($column, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}
$templates = $db->query(
    "SELECT code,code_name,extra_data FROM system_codes"
    . " WHERE code_group='STATUTORY_STANDARD_TYPE' AND is_active=1 ORDER BY sort_no"
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($templates as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}
echo "--ROWS--", PHP_EOL;
$rows = $db->query(
    'SELECT s.id,s.standard_type_code,s.effective_from,s.effective_to,s.value_data,s.note,'
    . 'COUNT(src.id) AS source_count FROM system_statutory_standards s '
    . 'LEFT JOIN system_statutory_standard_sources src ON src.standard_id=s.id '
    . 'GROUP BY s.id ORDER BY s.standard_type_code,s.effective_from'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rows as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}
