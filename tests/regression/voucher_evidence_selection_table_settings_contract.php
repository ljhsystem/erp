<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$js = file_get_contents($root . '/public/assets/js/pages/ledger/voucher/evidence-selection-table.js');
$view = file_get_contents($root . '/app/views/ledger/journal/partials/journal_modal.php');
$css = file_get_contents($root . '/public/assets/css/pages/ledger/voucher/index.css');
$meta = file_get_contents($root . '/app/Services/System/DataTableColumnMetaService.php');
$repository = file_get_contents($root . '/app/Repositories/Ledger/EvidenceSourceRepository.php');
$controller = file_get_contents($root . '/app/Controllers/Ledger/VoucherController.php');

$assertions = [
    '공용 DataTable 사용' => str_contains($js, 'createDataTable({'),
    '테이블 설정 활성화' => str_contains($js, "metaDomain: EVIDENCE_SELECTION_META_DOMAIN")
        && !str_contains($js, 'tableSettings: { enabled: false }'),
    '복합 메타 도메인 등록' => str_contains($meta, "'voucher-evidence-selection' => ['composite' => 'voucher-evidence-selection']")
        && str_contains($meta, 'columnsForVoucherEvidenceSelectionDomain'),
    '직원 컬럼 기본 표시' => str_contains($js, "'employee_name'")
        && str_contains($js, "title: '직원'")
        && str_contains($view, '<th>직원</th>'),
    '직원 SSOT 표시명 조회' => str_contains($repository, 'employee_ref.employee_name')
        && str_contains($controller, "'employee_name' => 'employee_search_name'")
        && str_contains($repository, "isset(\$employeeColumns['deleted_at'])")
        && !str_contains($repository, 'employee_ref.id = e.employee_id AND employee_ref.deleted_at IS NULL'),
    '가로 스크롤 비활성화' => str_contains($js, 'scrollX: false')
        && str_contains($css, 'overflow-x: hidden !important;')
        && !str_contains($view, 'align-middle nowrap w-100'),
    '헤더 본문 단일 테이블' => str_contains($js, "scrollY: ''")
        && str_contains($css, '.journal-evidence-table-wrap { min-width: 0; flex: 1 1 auto; overflow-x: hidden; overflow-y: auto; }'),
    '도구줄과 컬럼헤더 이중 고정' => str_contains($css, 'position: sticky !important; top: 0 !important; z-index: 4;')
        && str_contains($css, 'top: var(--journal-evidence-toolbar-height, 42px)')
        && str_contains($js, '--journal-evidence-toolbar-height')
        && str_contains($js, 'new ResizeObserver(syncStickyOffset)'),
    '보기 및 직접 너비 설정 적용' => str_contains($js, "widthScopeSelector: '.journal-evidence-table-wrap'")
        && str_contains($js, 'fitColumnsToScope: true')
        && str_contains($js, 'metaDomain: EVIDENCE_SELECTION_META_DOMAIN'),
    '재정렬 API 미연결' => !str_contains($js, 'reorderApi')
        && !str_contains($js, 'rowReorderApi'),
];

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, 'FAIL: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'PASS: voucher evidence selection DataTable settings contract' . PHP_EOL;
