<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$tables = [
    'institution_regular_employment_income_line_items',
    'institution_regular_employment_income_calculation_bases',
];
$schema = [];
foreach ($tables as $table) {
    $schema[$table] = $db->query('SHOW FULL COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$lines = $db->prepare(
    "SELECT h.income_year_month,i.employee_id,i.employee_name_snapshot,l.*
       FROM institution_regular_employment_income_line_items l
       JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id
       JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id
      WHERE h.income_year_month='2013-08'
        AND l.item_type_code='DEDUCTION'
        AND l.item_code='EMPLOYMENT_INSURANCE'
      ORDER BY i.sort_no,l.sort_no"
);
$lines->execute();

$bases = $db->prepare(
    "SELECT h.income_year_month,i.employee_id,i.employee_name_snapshot,b.*
       FROM institution_regular_employment_income_calculation_bases b
       JOIN institution_regular_employment_income_items i ON i.id=b.regular_employment_income_item_id
       JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id
      WHERE h.income_year_month='2013-08'
        AND (b.basis_type_code LIKE '%EMPLOYMENT%' OR b.basis_code LIKE '%EMPLOYMENT%')
      ORDER BY i.sort_no,b.basis_type_code,b.id"
);
$bases->execute();

echo json_encode([
    'schema' => $schema,
    'employment_insurance_lines' => $lines->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'employment_insurance_bases' => $bases->fetchAll(PDO::FETCH_ASSOC) ?: [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
