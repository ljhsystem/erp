<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$month = $argv[1] ?? '2013-08';
$document = $db->prepare(
    "SELECT * FROM institution_regular_employment_incomes
      WHERE income_year_month=:month AND deleted_at IS NULL
      ORDER BY created_at,id"
);
$document->execute([':month' => $month]);
$headers = $document->fetchAll(PDO::FETCH_ASSOC);
$result = ['month' => $month, 'headers' => $headers, 'items' => [], 'lines' => []];

foreach ($headers as $header) {
    $items = $db->prepare(
        "SELECT id,employee_id,employee_name_snapshot,gross_amount,income_tax_amount,
                local_income_tax_amount,national_pension_amount,health_insurance_amount,
                long_term_care_amount,employment_insurance_amount,other_deduction_amount,
                deduction_amount,net_payment_amount
           FROM institution_regular_employment_income_items
          WHERE regular_employment_income_id=:id AND deleted_at IS NULL
          ORDER BY sort_no,id"
    );
    $items->execute([':id' => $header['id']]);
    $result['items'] = array_merge($result['items'], $items->fetchAll(PDO::FETCH_ASSOC));

    $lines = $db->prepare(
        "SELECT i.employee_id,i.employee_name_snapshot,l.sort_no,l.item_type_code,l.item_code,
                l.item_name_snapshot,l.calculated_amount,l.adjustment_amount,l.final_amount,
                l.source_key
           FROM institution_regular_employment_income_items i
           JOIN institution_regular_employment_income_line_items l
             ON l.regular_employment_income_item_id=i.id
          WHERE i.regular_employment_income_id=:id AND i.deleted_at IS NULL
          ORDER BY i.sort_no,l.sort_no,l.id"
    );
    $lines->execute([':id' => $header['id']]);
    $result['lines'] = array_merge($result['lines'], $lines->fetchAll(PDO::FETCH_ASSOC));
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
