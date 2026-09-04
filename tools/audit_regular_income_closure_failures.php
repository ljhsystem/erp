<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;
$db = DbPdo::conn();
$column = $db->query(
    "SELECT COLUMN_NAME,COLUMN_TYPE,CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE,COLUMN_DEFAULT
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='institution_regular_employment_incomes'
        AND COLUMN_NAME='calculation_version'"
)->fetch(\PDO::FETCH_ASSOC);
$versions = $db->query(
    'SELECT calculation_version,COUNT(*) row_count,MAX(CHAR_LENGTH(calculation_version)) max_length
       FROM institution_regular_employment_incomes
      GROUP BY calculation_version
      ORDER BY calculation_version'
)->fetchAll(\PDO::FETCH_ASSOC);
$documents = $db->query(
    'SELECT id,document_status,calculation_version,updated_at
       FROM institution_regular_employment_incomes
      WHERE deleted_at IS NULL
      ORDER BY id'
)->fetchAll(\PDO::FETCH_ASSOC);
$rollbackFixtureCount = (int) $db->query(
    "SELECT COUNT(*) FROM institution_regular_employment_incomes WHERE income_year_month='2013-09'"
)->fetchColumn();
$overrideLines = $db->query(
    "SELECT h.income_year_month,i.employee_id,l.id,l.item_code,l.calculated_amount,l.adjustment_amount,
            l.final_amount,l.adjustment_reason,l.source_reference_id,l.source_key,l.business_source_code
       FROM institution_regular_employment_income_line_items l
       JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id
       JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id
      WHERE l.item_type_code='DEDUCTION'
        AND l.item_code IN ('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE')
      ORDER BY h.income_year_month,i.employee_id,l.item_code"
)->fetchAll(\PDO::FETCH_ASSOC);

echo json_encode(compact('column', 'versions', 'documents', 'rollbackFixtureCount', 'overrideLines'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
