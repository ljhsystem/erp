<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$documentId = $argv[1] ?? '';
if ($documentId === '') throw new InvalidArgumentException('문서 ID를 입력해 주세요.');

$items = $db->prepare(
    "SELECT id,regular_employment_income_id,employee_id,employee_name_snapshot,sort_no,
            created_at,created_by,updated_at,updated_by,deleted_at,deleted_by
       FROM institution_regular_employment_income_items
      WHERE regular_employment_income_id=:id
      ORDER BY deleted_at IS NOT NULL,sort_no,id"
);
$items->execute([':id' => $documentId]);
$lines = $db->prepare(
    "SELECT regular_employment_income_item_id,item_type_code,COUNT(*) line_count,
            SUM(final_amount) final_total
       FROM institution_regular_employment_income_line_items
      WHERE regular_employment_income_item_id IN (
            SELECT id FROM institution_regular_employment_income_items
             WHERE regular_employment_income_id=:id
      )
      GROUP BY regular_employment_income_item_id,item_type_code
      ORDER BY regular_employment_income_item_id,item_type_code"
);
$lines->execute([':id' => $documentId]);
$audits = $db->prepare(
    "SELECT regular_employment_income_item_id,COUNT(*) audit_count,
            MIN(acted_at) first_acted_at,MAX(acted_at) last_acted_at
       FROM institution_regular_employment_income_audits
      WHERE regular_employment_income_id=:id
      GROUP BY regular_employment_income_item_id"
);
$audits->execute([':id' => $documentId]);
$schema = $db->query(
    "SELECT COLUMN_TYPE,IS_NULLABLE
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='institution_regular_employment_income_items'
        AND COLUMN_NAME='sort_no'"
)->fetch(PDO::FETCH_ASSOC);
$index = $db->query(
    "SHOW INDEX FROM institution_regular_employment_income_items
      WHERE Key_name='uk_institution_regular_employment_income_item_sort'"
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'items' => $items->fetchAll(PDO::FETCH_ASSOC),
    'lines' => $lines->fetchAll(PDO::FETCH_ASSOC),
    'audits' => $audits->fetchAll(PDO::FETCH_ASSOC),
    'sort_schema' => $schema,
    'unique_index' => $index,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
