<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Services/System/DataTableColumnMetaService.php');
$model = file_get_contents($root . '/app/Models/Institution/JobAssignmentModel.php');
$page = file_get_contents($root . '/public/assets/js/pages/institution/job-assignment/index.js');
$settings = file_get_contents($root . '/public/assets/js/common/datatable/dataTableSettings.js');
$modal = file_get_contents($root . '/public/assets/js/common/datatable/dataTableColumnSettings.js');
$dataTableCss = file_get_contents($root . '/public/assets/css/components/data-table.css');

if ($service === false || $model === false || $page === false || $settings === false || $modal === false || $dataTableCss === false) {
    fwrite(STDERR, "검사 대상 파일을 읽지 못했습니다.\n");
    exit(1);
}

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$methodStart = strpos($service, 'private function columnsForJobAssignmentDomain');
$methodEnd = $methodStart === false
    ? false
    : strpos($service, 'private function columnsForPermissionAssignmentDomain', $methodStart);
$jobMethod = $methodStart !== false && $methodEnd !== false
    ? substr($service, $methodStart, $methodEnd - $methodStart)
    : '';

$physicalTables = [
    'user_employees',
    'institution_job_assignments_employment_status_histories',
    'institution_job_assignments_department_histories',
    'institution_job_assignments_position_histories',
    'institution_job_assignments_job_histories',
    'institution_job_assignments_project_histories',
    'institution_job_assignments_workplace_histories',
];

$expect($jobMethod !== '', '직무·배치 metadata 메서드를 찾을 수 없습니다.');
foreach ($physicalTables as $table) {
    $expect(str_contains($jobMethod, "'{$table}'"), "직무·배치 원본 테이블 누락: {$table}");
}
$expect(!str_contains($jobMethod, "'auth_users'"), 'JOIN 전용 auth_users가 직무·배치 metadata에 포함되어 있습니다.');
$expect(str_contains($jobMethod, '], [], true);'), '직무·배치 복합 metadata가 qualified key 모드를 사용하지 않습니다.');
$expect(
    str_contains($service, '$resolvedTableName . \'.\' . $sourceColumn'),
    '복합 metadata의 table.column key 생성 계약이 없습니다.'
);
$expect(str_contains($service, 'ordinalPosition: $sequence++'), '복합 테이블 누적 순번 계약이 없습니다.');
$expect(str_contains($service, 'strtoupper($isNullable) === \'NO\''), 'DB NULL 허용 기반 필수구분 계약이 없습니다.');
$expect(str_contains($service, '(string) ($row[\'COLUMN_COMMENT\'] ?? \'\')'), 'DB 컬럼 COMMENT 기반 기본 표시명 계약이 없습니다.');

$expect(!preg_match("/\{\s*data\s*:\s*'username'/", $page), '직무·배치 DataTable에 JOIN 아이디 Projection이 남아 있습니다.');
$settingsKeys = [
    'user_employees.sort_no',
    'user_employees.employee_name',
    'institution_job_assignments_employment_status_histories.status_code',
    'institution_job_assignments_department_histories.department_id',
    'institution_job_assignments_position_histories.position_id',
    'institution_job_assignments_job_histories.job_id',
    'institution_job_assignments_project_histories.project_id',
    'institution_job_assignments_workplace_histories.workplace_name_snapshot',
];
foreach ($settingsKeys as $key) {
    $expect(str_contains($page, "settingsKey:'{$key}'"), "물리컬럼 settingsKey 연결 누락: {$key}");
}
$expect(!preg_match("/data:'(?:other_project_summary|updated_at)'/", $page), '기타 프로젝트 또는 대표 수정일시 Projection이 직무·배치 DataTable에 남아 있습니다.');
$expect(str_contains($model, 'private function listOrderSql(array $query): string'), '직무·배치 서버 정렬 허용목록이 없습니다.');
$expect(!str_contains($model, "'other_project_summary' =>"), '제외된 기타 프로젝트 서버 정렬 매핑이 남아 있습니다.');
$expect(!str_contains($model, "'updated_at' =>"), '제외된 대표 수정일시 서버 정렬 매핑이 남아 있습니다.');
$expect(str_contains($model, 'ORDER BY {$orderSql} LIMIT'), '직무·배치 목록이 TableSettings 정렬 SQL을 적용하지 않습니다.');
$virtualResolverStart = strpos($settings, 'function resolveSettingsVirtualType');
$virtualResolverEnd = $virtualResolverStart === false
    ? false
    : strpos($settings, 'function buildStorageKey', $virtualResolverStart);
$virtualResolver = $virtualResolverStart !== false && $virtualResolverEnd !== false
    ? substr($settings, $virtualResolverStart, $virtualResolverEnd - $virtualResolverStart)
    : '';
$expect(!str_contains($virtualResolver, 'column.__dtVirtualType'), '내부 가상유형이 명시적 페이지 선언으로 재승격됩니다.');
$expect(str_contains($page, 'job-assignment.main.v2'), '직무·배치 metadata schema 변경용 storage version이 갱신되지 않았습니다.');
$expect(str_contains($page, 'resetOnColumnSchemaChange:true'), '직무·배치 schema 변경 시 기존 설정 정리 Guard가 없습니다.');

$expect(str_contains($settings, '__dtMetaOrdinalPosition'), '공용 설정 Adapter가 metadata 누적 순번을 보존하지 않습니다.');
$expect(str_contains($settings, 'ordinalPosition: Number(column.__dtMetaOrdinalPosition'), 'TableSettings 행에 누적 순번이 전달되지 않습니다.');
$expect(str_contains($settings, "key: '__select'"), '누락된 체크박스 시스템컬럼의 공용 placeholder가 없습니다.');
$expect(str_contains($settings, "key: '__reorder'"), '누락된 드래그핸들 시스템컬럼의 공용 placeholder가 없습니다.');
$expect(str_contains($settings, "key: '__actions'"), '누락된 관리 시스템컬럼의 공용 placeholder가 없습니다.');
$expect(str_contains($settings, "['관리', '수정'].includes(title)"), '기존 관리컬럼을 __actions로 선인식하는 공용 규칙이 없습니다.');
$expect(str_contains($settings, "if (normalizedKey === '__actions') return '관리';"), '관리 시스템컬럼의 기본 사용컬럼명이 한글 관리가 아닙니다.');
$expect(str_contains($settings, "['bi bi-gear', 'bi bi-gear-fill']"), '과거 자동 gear 기본명의 한글 관리 정규화가 없습니다.');
$expect(substr_count($settings, '__dtSystemCapability: false') >= 3, '기능 미지원 시스템컬럼의 비활성 capability 계약이 없습니다.');
$expect(substr_count($settings, '__dtSettingsMovable: true') >= 4, '공용 TableSettings 전체 컬럼 이동 계약이 없습니다.');
$expect(!str_contains($settings, 'moveColumnsImmediatelyBeforeActions'), '저장 순서를 다시 배치하는 강제 위치 보정이 남아 있습니다.');
$expect(substr_count($settings, 'widthResizable: true') >= 3, '보기 ON 시스템 placeholder의 너비 변경 capability가 없습니다.');
$expect(str_contains($settings, "width: '36px'"), '체크박스 시스템 placeholder의 공용 기본너비가 없습니다.');
$expect(str_contains($settings, '__dtSettingsHideable: true'), '시스템 가상컬럼의 공용 보기 변경 capability가 없습니다.');
$expect(
    preg_match('/systemColumn\s*\?\s*safeColumn\.visible !== false/', $settings) === 1,
    '시스템 가상컬럼 기본 보기가 페이지별 defaultVisibleColumns에 종속됩니다.'
);
$expect(str_contains($settings, 'requirementPolicyEditable: !isSystemColumnKey'), '시스템 가상컬럼 필수구분 잠금 계약이 없습니다.');
$expect(str_contains($modal, 'requirementPolicyEditable === false'), '필수구분 Editor가 시스템 가상컬럼 잠금을 적용하지 않습니다.');
$expect(
    substr_count($settings, 'ensureRequiredSettingsVirtualColumns(') >= 3,
    '초기 준비와 Modal 진입에서 시스템 가상컬럼을 이중 보장하지 않습니다.'
);
$expect(str_contains($settings, 'return settingsColumns'), 'Modal 목록이 시스템컬럼 보충 결과를 사용하지 않습니다.');
$expect(str_contains($modal, 'let physicalDisplayOrder = 0;') && str_contains($modal, '? ++physicalDisplayOrder'), 'TableSettings 순번이 현재 컬럼 정렬순서를 표시하지 않습니다.');
$expect(
    str_contains($modal, '`${tableInfo.number}-${entry.sourceOrdinalPosition}.${sourceColumnName}`'),
    '원본컬럼명이 테이블번호-DB원본순번.컬럼명 형식이 아닙니다.'
);
$expect(str_contains($modal, "sourceText.match(/^(\\d+)-(\\d+)\\.(.+)$/u)"), '원본컬럼명 접두사와 컬럼명 분리 Renderer가 없습니다.');
$expect(str_contains($modal, 'dt-column-settings-source-prefix'), '원본컬럼명 접두사 nowrap 요소가 없습니다.');
$expect(str_contains($dataTableCss, '.dt-column-settings-source-prefix'), '원본컬럼명 접두사 줄바꿈 방지 CSS가 없습니다.');
$expect(
    str_contains($dataTableCss, '.dataTables_wrapper table.dataTable tbody > tr > td:last-child'),
    '공용 DataTable 마지막 body 셀의 우측 테두리 규칙이 없습니다.'
);
$expect(
    str_contains($dataTableCss, 'border-right: 1px solid var(--color-border, #dbe4ee) !important;'),
    '공용 DataTable 마지막 컬럼 우측 경계선이 유지되지 않습니다.'
);
$expect(str_contains($modal, 'data-dt-settings-column-search'), '공용 TableSettings 컬럼 검색 입력이 없습니다.');
$searchPosition = strpos($modal, 'data-dt-settings-column-search');
$searchFormPosition = strpos($modal, 'data-dt-view-search-status');
$expect(
    $searchPosition !== false && $searchFormPosition !== false && $searchPosition < $searchFormPosition,
    '컬럼 검색이 검색영역 상태 왼쪽에 배치되지 않았습니다.'
);
$expect(
    preg_match('/dt-column-settings-title-row[\s\S]*modal-title[\s\S]*data-dt-settings-state-source/', $modal) === 1,
    '현재값 상태 Badge가 Table Settings 제목 오른쪽에 배치되지 않았습니다.'
);
$expect(str_contains($modal, 'function applyColumnSearch'), '공용 TableSettings 컬럼 검색 필터가 없습니다.');
$expect(str_contains($modal, 'searchText.includes(normalizedToken)'), '컬럼 검색이 %검색어% 부분일치 방식이 아닙니다.');
$expect(str_contains($modal, "replace(/[\\s._-]+/g, '')"), '컬럼 검색이 공백·구분자 차이를 정규화하지 않습니다.');
$expect(str_contains($modal, 'entry?.sourceTable'), '컬럼 검색에서 원본 테이블명을 검색하지 않습니다.');
$expect(!str_contains($modal, 'viewSettings.columnSearchQuery'), '컬럼 검색어가 VIEW 저장상태에 혼입되었습니다.');
$expect(str_contains($dataTableCss, '#dtColumnSettingsModal .dt-column-settings-shell,'), 'TableSettings Modal의 공용 flex 높이 체인이 없습니다.');
$expect(str_contains($dataTableCss, 'font-size: 10px;'), '현재값 상태 Badge가 Modal 제목보다 작은 글씨를 사용하지 않습니다.');
$expect(str_contains($dataTableCss, '.modal-dialog-scrollable .modal-content'), '검색 중 TableSettings Modal 높이 유지 규칙이 없습니다.');
$expect(str_contains($dataTableCss, '.dt-column-settings-search-field:focus-within'), '컬럼 검색 flex control의 focus 표시가 없습니다.');
$expect(str_contains($dataTableCss, 'position: static;'), '컬럼 검색 돋보기가 input과 겹치지 않는 독립 영역으로 배치되지 않았습니다.');
$expect(str_contains($dataTableCss, '#dtColumnSettingsModal .modal-header .btn-close'), 'TableSettings 닫기 X의 우측 상단 정렬 규칙이 없습니다.');
$expect(str_contains($dataTableCss, 'align-items: flex-start;'), 'TableSettings Modal header가 상단정렬되지 않습니다.');
$expect(str_contains($dataTableCss, 'max-height: none;'), 'TableSettings Grid에 viewport 추정 max-height가 남아 있습니다.');
$expect(substr_count($dataTableCss, 'min-height: 0;') >= 2, 'TableSettings 본문과 Grid가 footer 상단까지 축소되는 min-height Guard가 없습니다.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "직무·배치 TableSettings 물리 metadata 계약 검사 통과\n");
