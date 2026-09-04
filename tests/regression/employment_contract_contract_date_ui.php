<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = (string) file_get_contents($root . '/app/views/institution/employment-contract/index.php');
$table = (string) file_get_contents($root . '/public/assets/js/pages/institution/employment-contract/table.js');
$runtime = (string) file_get_contents($root . '/public/assets/js/pages/institution/employment-contract/modal-runtime.js');
$service = (string) file_get_contents($root . '/app/Services/Institution/EmploymentContractService.php');

$checks = [
    'modal_contract_date' => str_contains($view, 'name="contract_date" data-employment-date required'),
    'list_contract_date' => str_contains($table, "data: 'contract_date', title: '계약일'"),
    'search_contract_date' => str_contains($table, "dateOptions: ['contract_date', 'contract_start_date', 'contract_end_date']"),
    'today_suggestion' => str_contains($runtime, "namedItem('contract_date').value = formatPickerDate(new Date())"),
    'number_from_contract_date' => str_contains($service, "contractNo((string) \$data['contract_date'])"),
    'number_changes_only_with_date' => str_contains($service, 'if ($contractDateChanged)')
        && str_contains($service, "\$data['contract_no'] = \$this->contractNo"),
    'correction_preserves_date' => str_contains($service, "\$sourceContractDate ?? \$requestedContractDate"),
];
$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
if ($failed !== []) throw new RuntimeException('계약일 UI/계약 누락: ' . implode(', ', $failed));
echo json_encode(['success' => true, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
