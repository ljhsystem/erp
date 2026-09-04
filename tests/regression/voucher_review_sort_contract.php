<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$script = file_get_contents($root . '/public/assets/js/pages/ledger/voucherReview.js');
$model = file_get_contents($root . '/app/Models/Ledger/VoucherReviewQueryModel.php');
$common = file_get_contents($root . '/public/assets/js/common/table/data-table.js');
$settings = file_get_contents($root . '/public/assets/js/common/datatable/dataTableSettings.js');

if ($script === false || $model === false || $common === false || $settings === false) {
    fwrite(STDERR, "전표검토 정렬 계약 파일을 읽을 수 없습니다.\n");
    exit(1);
}

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

foreach ([
    'sort_no',
    'voucher_no',
    'voucher_date',
    'summary_account_id',
    'summary_client_id',
    'summary_employee_id',
    'summary_project_id',
    'summary_line_summary',
    'reject_reason',
    'is_reversal',
] as $field) {
    $expect(str_contains($script, "'{$field}'"), "{$field} 프런트 정렬 허용 계약이 없습니다.");
    $expect(str_contains($model, "'{$field}' =>"), "{$field} 서버 정렬 허용 계약이 없습니다.");
}

$expect(
    str_contains($script, 'orderable: VOUCHER_REVIEW_ORDERABLE_FIELDS.has(field)')
        && str_contains($script, 'orderableColumnKeys: Array.from(VOUCHER_REVIEW_ORDERABLE_FIELDS)'),
    '전표검토 컬럼의 명시적 정렬 가능 여부 설정이 없습니다.'
);
$expect(
    str_contains($script, "defaultOrder: [{ key: 'sort_no', dir: 'desc' }]") ,
    '전표검토 기본 정렬이 안정적인 컬럼 key를 사용하지 않습니다.'
);
$expect(
    str_contains($common, 'orderableColumnKeys = null')
        && str_contains($common, 'enforcedOrderableKeys.has(resolveSettingsColumnKey(column))')
        && str_contains($common, 'columnDefs: tableColumnDefs')
        && str_contains($common, 'targets: index')
        && str_contains($common, 'column?.__dtSystemCapability === false')
        && str_contains($common, 'requiresMissingRuntimeColumn'),
    'TableSettings 준비 이후 최종 DataTables 컬럼에 정렬 정책을 적용하는 공용 계약이 없습니다.'
);
$expect(!str_contains($script, 'synchronizeReviewColumnOrderability'), '화면별 사후 헤더 DOM 보정이 남아 있습니다.');
$expect(
    str_contains($settings, 'const runtimeKey = String(dataTableColumn?.mData || dataTableColumn?.sName || \'\').trim();')
        && str_contains($settings, 'const shouldShow = visibleSet.has(key);')
        && str_contains($settings, 'const requiresRuntimeSchemaRebuild = nextState.visibleColumns')
        && str_contains($settings, 'unsupportedSystemVisibilityChanged')
        && str_contains($common, 'requiresSystemCapabilityRebuild')
        && str_contains($settings, 'const handled = await table?.__dtTableSettings?.applyState?.({'),
    '공용 보기 설정이 실제 DataTables 컬럼 key를 기준으로 적용되지 않습니다.'
);

echo "전표검토 헤더 정렬 계약 검증 통과\n";
