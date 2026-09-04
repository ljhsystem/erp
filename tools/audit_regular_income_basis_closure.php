<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$types = ['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'EMPLOYMENT_INCOME_TAX', 'LOCAL_INCOME_TAX_WITHHOLDING'];
$placeholders = implode(',', array_fill(0, count($types), '?'));
$standards = $db->prepare(
    "SELECT id,standard_type_code,effective_from,effective_to,value_data,note,updated_at,updated_by
     FROM system_statutory_standards
     WHERE standard_type_code IN ($placeholders)
     ORDER BY standard_type_code,effective_from,id"
);
$standards->execute($types);
$rows = $standards->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rows as &$row) {
    $row['value_data'] = json_decode((string) $row['value_data'], true);
}
unset($row);

$templates = $db->prepare(
    "SELECT code,code_name,extra_data,updated_at,updated_by
     FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code IN ($placeholders)
     ORDER BY code"
);
$templates->execute($types);
$templateRows = $templates->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($templateRows as &$row) {
    $row['extra_data'] = json_decode((string) $row['extra_data'], true);
}
unset($row);

$sources = $db->prepare(
    "SELECT source_row.*,standard_row.standard_type_code,standard_row.effective_from,standard_row.effective_to
     FROM system_statutory_standard_sources source_row
     JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id
     WHERE standard_row.standard_type_code IN ($placeholders)
     ORDER BY standard_row.standard_type_code,standard_row.effective_from,source_row.sort_no,source_row.id"
);
$sources->execute($types);

$documentId = '4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6';
$items = $db->prepare(
    "SELECT item.id,item.employee_id,item.employee_name_snapshot,item.dependent_count_snapshot,
            item.national_pension_basis_snapshot,item.health_insurance_basis_snapshot,
            item.employment_insurance_basis_snapshot,item.gross_amount,item.taxable_amount,
            item.deduction_amount,item.net_payment_amount,
            line.item_code,line.calculated_amount,line.adjustment_amount,line.final_amount,
            line.adjustment_reason,line.calculation_source_code
     FROM institution_regular_employment_income_items item
     LEFT JOIN institution_regular_employment_income_line_items line
       ON line.regular_employment_income_item_id=item.id
     WHERE item.regular_employment_income_id=? AND item.deleted_at IS NULL
     ORDER BY item.sort_no,line.sort_no,line.id"
);
$items->execute([$documentId]);
$savedRows = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
$savedSummary = [];
foreach ($savedRows as $row) {
    $employee = (string) $row['employee_name_snapshot'];
    $savedSummary[$employee]['snapshots'] ??= array_intersect_key($row, array_flip([
        'id','employee_id','dependent_count_snapshot','national_pension_basis_snapshot',
        'health_insurance_basis_snapshot','employment_insurance_basis_snapshot',
        'gross_amount','taxable_amount','deduction_amount','net_payment_amount',
    ]));
    if (in_array($row['item_code'], [
        'NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE',
        'EMPLOYMENT_INCOME_TAX','LOCAL_INCOME_TAX',
    ], true)) {
        $savedSummary[$employee]['deductions'][$row['item_code']] = array_intersect_key($row, array_flip([
            'calculated_amount','adjustment_amount','final_amount','adjustment_reason','calculation_source_code',
        ]));
    }
}

$applicable = array_values(array_filter($rows, static function (array $row): bool {
    return $row['effective_from'] <= '2013-08-31'
        && ($row['effective_to'] === null || $row['effective_to'] >= '2013-08-31');
}));
$standardSummary = array_map(static fn(array $row): array => [
    'id' => $row['id'],
    'type' => $row['standard_type_code'],
    'from' => $row['effective_from'],
    'to' => $row['effective_to'],
    'values' => array_diff_key($row['value_data'], ['_schema' => true]),
], $applicable);
$templateSummary = array_map(static fn(array $row): array => [
    'code' => $row['code'],
    'fields' => array_column($row['extra_data']['fields'] ?? [], 'code'),
    'calculation_policy_fields' => array_column($row['extra_data']['calculation_policy']['fields'] ?? [], 'code'),
], $templateRows);
$sourceSummary = array_map(static fn(array $row): array => [
    'type' => $row['standard_type_code'],
    'from' => $row['effective_from'],
    'to' => $row['effective_to'],
    'organization' => $row['organization_name'],
    'source_name' => $row['source_name'],
    'law_name' => $row['law_name'],
    'source_url' => $row['source_url'],
], array_values(array_filter($sources->fetchAll(PDO::FETCH_ASSOC) ?: [], static function (array $row): bool {
    return $row['effective_from'] <= '2013-08-31'
        && ($row['effective_to'] === null || $row['effective_to'] >= '2013-08-31');
})));

echo json_encode([
    'success' => true,
    'templates' => $templateSummary,
    'standards_at_2013_08_31' => $standardSummary,
    'sources_at_2013_08_31' => $sourceSummary,
    'saved_document' => $savedSummary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
