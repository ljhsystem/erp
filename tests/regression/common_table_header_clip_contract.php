<?php

declare(strict_types=1);

$dataTable = file_get_contents(__DIR__ . '/../../public/assets/css/components/data-table.css');
$htmlGrid = file_get_contents(__DIR__ . '/../../public/assets/css/components/html-grid.css');
$spreadsheet = file_get_contents(__DIR__ . '/../../public/assets/css/components/spreadsheet.css');

$checks = [
    'datatable_header_single_line' => str_contains($dataTable, '.dataTables_wrapper table.dataTable thead th .dt-column-title')
        && str_contains($dataTable, 'white-space: nowrap;')
        && str_contains($dataTable, 'text-overflow: clip;'),
    'datatable_fitted_header_single_line' => str_contains($dataTable, '.dataTables_wrapper.dt-viewport-fitted table.dataTable thead th')
        && str_contains($dataTable, 'white-space: nowrap !important;'),
    'html_grid_header_clips' => str_contains($htmlGrid, '.html-grid-header-label { display: inline-block; min-width: 0; text-overflow: clip; }'),
    'spreadsheet_header_clips' => str_contains($spreadsheet, '.spreadsheet-shell .ag-header-cell-text')
        && str_contains($spreadsheet, 'text-overflow: clip;'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
