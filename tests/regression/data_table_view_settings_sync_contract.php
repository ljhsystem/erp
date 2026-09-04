<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'settings' => $root . '/public/assets/js/common/datatable/dataTableSettings.js',
    'modal' => $root . '/public/assets/js/common/datatable/dataTableColumnSettings.js',
    'table' => $root . '/public/assets/js/common/table/data-table.js',
    'search' => $root . '/public/assets/js/common/table/search-form.js',
    'storage' => $root . '/public/assets/js/common/user-settings/systemUserSettingsStorage.js',
    'policy' => $root . '/public/assets/js/common/datatable/dataTableViewPolicy.js',
    'view' => $root . '/public/assets/js/common/datatable/dataTableViewSettings.js',
];

$source = [];
foreach ($files as $name => $path) {
    $contents = file_get_contents($path);
    if ($contents === false || $contents === '') {
        throw new RuntimeException("공용 파일을 읽을 수 없습니다: {$name}");
    }
    $source[$name] = $contents;
}

$requiredFragments = [
    'settings' => [
        "saveSettingState(context.config, nextState, context.tableState, 'TABLE')",
        "saveSettingState(context.config, nextViewState, context.viewState, 'VIEW')",
        'applyViewState?.({',
        'buildDataTableViewModalOptions',
        'normalizeBootstrapIconDisplayName',
        'renderColumnDisplayName',
        '__dtDefaultHeaderTitle',
        "SYSTEM_SELECTION_DISPLAY_NAME = '전체선택 체크박스(기능 고정)'",
    ],
    'modal' => [
        "label: '너비(px)'",
        "key: 'requirementPolicy', label: '필수구분', width: 124",
        "key: 'sortDirection', label: '정렬', width: 52",
        "requirementStarHtml(context.row.values?.requirementPolicy)",
        "sourceWrap.insertAdjacentHTML('beforeend', requirementStarHtml(nextPolicy))",
        "input.classList.add('dt-display-name-locked')",
        'dt-column-settings-state-source',
        'dt-column-settings-toolbar-controls',
        'updateStateSourceBadge',
        '테이블 활성상태 ${configurableEntries.filter',
        '/ 전체컬럼 ${configurableEntries.length}개',
        'data-dt-settings-move-up',
        'data-dt-settings-sort',
        'cycleDataTableSortSettings',
        'sortSettings.filter((item) => item.key !== key)',
        'context.row.values?.visible === false',
        'data-dt-settings-width-decrease',
        'data-dt-settings-width-increase',
        'DATA_TABLE_COLUMN_WIDTH_STEP = 1',
        "width: checkbox.checked && entry.widthResizable !== false",
        'delete columnWidths[key]',
        'data-dt-view-page-length',
        'data-dt-view-search-expanded',
        'data-dt-view-search-status',
        '검색영역-${state.viewSettings.searchFormExpanded',
        'data-dt-view-settings-reset',
        '보기 기본값 복원',
        '컬럼 기본값 복원',
    ],
    'table' => [
        'columnWidths:',
        'sortSettings:',
        'pageLength:',
        'applyViewState =',
    ],
    'search' => [
        'registerSearchFormCapability',
        'setExpanded(expanded, options = {})',
        'searchFormExpanded:',
    ],
    'policy' => [
        'DATA_TABLE_PAGE_LENGTH_OPTIONS',
        'DATA_TABLE_COLUMN_WIDTH_DEFAULT',
        'normalizeDataTableColumnWidth',
        'isDataTableColumnWidthResizable',
    ],
    'view' => [
        'viewSettingsEnabled: true',
        'buildNextDataTableViewState',
        'restoreViewDefaults',
        'entry.visible !== false',
    ],
];

foreach ($requiredFragments as $file => $fragments) {
    foreach ($fragments as $fragment) {
        if (!str_contains($source[$file], $fragment)) {
            throw new RuntimeException("공용 VIEW 계약 누락: {$file} / {$fragment}");
        }
    }
}

foreach (['applyViewSettingsImmediately', 'onViewSettingsChange'] as $forbiddenFragment) {
    if (str_contains($source['modal'], $forbiddenFragment) || str_contains($source['settings'], $forbiddenFragment)) {
        throw new RuntimeException("저장 전 VIEW 즉시 적용 경로가 남아 있습니다: {$forbiddenFragment}");
    }
}

if (str_contains($source['modal'], 'currentPage') || str_contains($source['modal'], 'searchFormState')) {
    throw new RuntimeException('TableSettings UI에 비노출 VIEW 항목이 포함됐습니다.');
}
foreach (['data-dt-view-sort-key', 'data-dt-view-sort-dir', 'type="radio"', "placeholder = '자동'"] as $forbidden) {
    if (str_contains($source['modal'], $forbidden)) {
        throw new RuntimeException("폐기된 TableSettings 입력 UI가 남아 있습니다: {$forbidden}");
    }
}
if (str_contains($source['settings'], "settingType: 'TABLE_VIEW'")
    || str_contains($source['settings'], "settingType: 'VIEW_TABLE'")) {
    throw new RuntimeException('신규 중복 설정유형이 생성됐습니다.');
}
if (!str_contains($source['storage'], "setting_type: request.settingType")) {
    throw new RuntimeException('기존 setting_type 저장 브리지가 유지되지 않았습니다.');
}

echo json_encode([
    'success' => true,
    'table_view_separated' => true,
    'view_fields' => ['columnWidths', 'sortSettings', 'pageLength', 'searchFormExpanded'],
    'hidden_view_fields' => ['currentPage', 'searchFormState'],
    'shared_policy' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
