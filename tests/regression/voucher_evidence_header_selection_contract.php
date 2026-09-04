<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$evidenceLinks = file_get_contents($root . '/public/assets/js/pages/ledger/voucher/evidence-links.js');
$headerRenderer = file_get_contents($root . '/public/assets/js/common/html-grid/header-renderer.js');
$grid = file_get_contents($root . '/public/assets/js/common/html-grid/index.js');
$schema = file_get_contents($root . '/public/assets/js/common/html-grid/schema.js');
$styles = file_get_contents($root . '/public/assets/css/components/html-grid.css');
$voucherStyles = file_get_contents($root . '/public/assets/css/pages/ledger/voucher/index.css');
$voucherController = file_get_contents($root . '/app/Controllers/Ledger/VoucherController.php');
$evidenceRepository = file_get_contents($root . '/app/Repositories/Ledger/EvidenceSourceRepository.php');

foreach ([$evidenceLinks, $headerRenderer, $grid, $schema, $styles, $voucherStyles, $voucherController, $evidenceRepository] as $source) {
    if ($source === false) {
        throw new RuntimeException('검증할 HTML Grid 소스 파일을 읽을 수 없습니다.');
    }
}

$contracts = [
    [$evidenceLinks, "key: 'selection', label: '', type: 'selection', headerSelection: true"],
    [$evidenceLinks, 'function sortedLinkedEvidences(rows = [])'],
    [$evidenceLinks, 'left?.evidence_date'],
    [$evidenceLinks, 'left?.client_name'],
    [$evidenceLinks, 'left?.display_summary'],
    [$evidenceLinks, 'sortedLinkedEvidences(state.linkedEvidences).map(gridRow)'],
    [$schema, 'headerSelection: column.headerSelection === true'],
    [$headerRenderer, "column.type === 'selection' && column.headerSelection === true"],
    [$headerRenderer, 'html-grid-header-selection'],
    [$grid, "checkbox.classList.contains('html-grid-header-selection')"],
    [$grid, 'checkbox.indeterminate = selectedSelectableCount > 0'],
    [$grid, 'row.values?.selection_disabled !== true'],
    [$grid, 'syncSelectionDom();'],
    [$headerRenderer, "th.classList.add('html-grid-header-cell-selection')"],
    [$styles, '.html-grid-header-cell-selection .html-grid-header-cell-content'],
    [$styles, 'justify-content: center'],
    [$styles, '.html-grid-header-selection'],
    [$styles, 'margin: 0'],
    [$voucherStyles, '#linked_evidences_grid .html-grid-header-cell-selection'],
    [$voucherStyles, 'align-items: center'],
    [$voucherStyles, 'justify-content: center'],
    [$voucherStyles, 'min-height: 42px'],
    [$voucherController, "\$row['raw_expense_date']"],
    [$evidenceRepository, "'raw_expense_date'"],
];

foreach ($contracts as [$source, $needle]) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException('증빙 전체선택 계약이 누락됐습니다: ' . $needle);
    }
}

echo "voucher evidence header selection contract: OK\n";
