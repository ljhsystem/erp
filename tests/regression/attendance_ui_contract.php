<?php

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$capabilities = [
    'clock_self' => true,
    'clock_admin' => true,
    'correct' => true,
    'close' => true,
    'reopen' => true,
];

ob_start();
require PROJECT_ROOT . '/app/views/institution/attendance/index.php';
$html = (string) ob_get_clean();

$required = [
    'attendanceAdminClockModal',
    'attendanceCorrectionModal',
    'attendanceClosureModal',
    'attendanceInvalidateModal',
    'data-attendance-datetime',
    'data-attendance-date',
    'data-attendance-month',
    'attendanceTabDescription',
    'attendanceDetailTitle',
    '출퇴근 기록 보정',
];
foreach ($required as $needle) {
    if (!str_contains($html, $needle)) {
        throw new RuntimeException('근태 UI 계약 누락: ' . $needle);
    }
}

$forbidden = ['attendanceFormModal', '근무일·귀속월', 'datetime-local', '관리자 출퇴근 원본 보정'];
foreach ($forbidden as $needle) {
    if (str_contains($html, $needle)) {
        throw new RuntimeException('근태 UI 레거시 잔존: ' . $needle);
    }
}

$script = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/attendance/index.js');
if ($script === false || str_contains($script, 'registerSelfClock') || str_contains($script, "text:'출근'") || str_contains($script, "text:'퇴근'")) {
    throw new RuntimeException('관리자 근태관리 화면에 본인 출퇴근 기능이 남아 있습니다.');
}
foreach (['monthlyColumns','exceptionColumns','closureColumns','API.closures','data-month-daily','data-action-detail','출퇴근 기록 보정'] as $needle) {
    if (!str_contains($script, $needle)) throw new RuntimeException('근태 탭 책임 분리 누락: ' . $needle);
}
foreach (['searchFields:currentConfig().fields', 'resetConditionsOnInit:true'] as $needle) {
    if (!str_contains($script, $needle)) throw new RuntimeException('근태 공용 SearchForm 계약 누락: ' . $needle);
}

foreach (['configureCondition:', 'configureSearchRow', 'attendance-search-picker'] as $needle) {
    if (str_contains($script, $needle)) {
        throw new RuntimeException('근태 전용 검색값 목록 치환 로직이 남아 있습니다: ' . $needle);
    }
}
foreach (['prepareSearchForm', 'MutationObserver(configureAll)', 'bindSearchEmployeePicker'] as $needle) {
    if (str_contains($script, $needle)) throw new RuntimeException('근태 SearchForm 전용 DOM 생명주기 잔존: ' . $needle);
}
foreach (['attendance-daily','attendance-monthly','attendance-exceptions','attendance-closures','.v4','resetOnColumnSchemaChange:true'] as $needle) {
    if (!str_contains($script, $needle)) throw new RuntimeException('근태 TableSettings 독립 계약 누락: ' . $needle);
}
foreach ([
    "bindEmployeeReferenceColumn(dailyColumns,'employee_id')",
    "bindEmployeeReferenceColumn(monthlyColumns,'employee_id')",
    "bindEmployeeReferenceColumn(exceptionColumns,'daily_record_id')",
    "bindEmployeeReferenceColumn(closureColumns,'employee_id')",
] as $needle) {
    if (!str_contains($script, $needle)) {
        throw new RuntimeException('근태 참조컬럼 물리 key 렌더링 계약 누락: ' . $needle);
    }
}
if (!str_contains($script, "removeNonPhysicalColumns(dailyColumns,['username','exception_labels'])")) {
    throw new RuntimeException('일별 근태의 아이디·예외 가상컬럼 제거 계약이 누락되었습니다.');
}
$settingsScript = file_get_contents(PROJECT_ROOT . '/public/assets/js/common/datatable/dataTableSettings.js');
$settingsModal = file_get_contents(PROJECT_ROOT . '/public/assets/js/common/datatable/dataTableColumnSettings.js');
foreach (['column_type','source_role','tableStateSource'] as $needle) {
    if ($settingsScript === false || !str_contains($settingsScript, $needle)) throw new RuntimeException('공용 TableSettings metadata 계약 누락: ' . $needle);
}
foreach (['선택안함','선택','필수','data-dt-settings-requirement-policy','현재값: DB 기본값','현재값: 사용자 저장 설정'] as $needle) {
    if ($settingsModal === false || !str_contains($settingsModal, $needle)) throw new RuntimeException('공용 TableSettings 모달 표시 계약 누락: ' . $needle);
}
if (str_contains((string) $settingsModal, '>컬럼 구분<') || str_contains((string) $settingsModal, 'dt-column-settings-source-cell')) {
    throw new RuntimeException('공용 TableSettings에 컬럼 구분 전용 UI가 남아 있습니다.');
}
if ($settingsScript === false || !str_contains($settingsScript, '기준 원본:')) {
    throw new RuntimeException('공용 TableSettings 상단 원본 보조정보가 누락되었습니다.');
}
if (str_contains($script, "['monthly','closures'].includes(active)") || str_contains($script, 'columns:monthly?monthlyColumns:dailyColumns')) {
    throw new RuntimeException('월별 현황·월 마감 또는 일별·예외 공용 분기가 남아 있습니다.');
}

$adminStart = strpos($html, 'id="attendanceAdminClockForm"');
$adminEnd = $adminStart === false ? false : strpos($html, '</form>', $adminStart);
$adminForm = $adminStart === false || $adminEnd === false ? '' : substr($html, $adminStart, $adminEnd - $adminStart);
if (str_contains($adminForm, 'name="work_date"') || str_contains($adminForm, 'name="closing_month"')) {
    throw new RuntimeException('출퇴근 기록 보정 Form에 근무일 입력이 남아 있습니다.');
}

echo "attendance ui regression: OK\n";
