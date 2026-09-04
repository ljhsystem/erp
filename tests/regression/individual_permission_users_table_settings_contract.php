<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Services/System/DataTableColumnMetaService.php');
$page = file_get_contents($root . '/public/assets/js/pages/main/settings/organization/permission-assignment/user-permission.js');
$repository = file_get_contents($root . '/app/Repositories/Auth/UserPermissionRepository.php');
$settings = file_get_contents($root . '/public/assets/js/common/datatable/dataTableSettings.js');
$rolePermissionPage = file_get_contents($root . '/public/assets/js/pages/main/settings/organization/permission-assignment.js');

if ($service === false || $page === false || $repository === false || $settings === false || $rolePermissionPage === false) {
    fwrite(STDERR, "검사 대상 파일을 읽지 못했습니다.\n");
    exit(1);
}

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$methodStart = strpos($service, 'private function columnsForIndividualPermissionUsersDomain');
$methodEnd = $methodStart === false
    ? false
    : strpos($service, 'private function columnsForPersonalExpenseItemDomain', $methodStart);
$method = $methodStart !== false && $methodEnd !== false
    ? substr($service, $methodStart, $methodEnd - $methodStart)
    : '';

$expect($method !== '', '개인권한 사용자목록 metadata 메서드를 찾지 못했습니다.');
foreach (['user_employees'] as $table) {
    $expect(str_contains($method, "'{$table}'"), "실제 원본 테이블 누락: {$table}");
}
foreach (['auth_users', 'auth_roles', 'auth_user_permission_profiles', 'auth_user_permissions'] as $table) {
    $expect(!str_contains($method, "'{$table}'"), "JOIN 참조 테이블이 원본 metadata에 포함됨: {$table}");
}
$expect(str_contains($method, '], [], true);'), '복합 metadata가 table.column key를 사용하지 않습니다.');
$expect(!str_contains($method, '$definitions'), '일부 컬럼 선별 방식이 남아 있습니다.');

foreach ([
    "settingsKey:'user_employees.sort_no'",
    "settingsKey:'user_employees.user_id'",
    "settingsKey:'user_employees.employee_name'",
    "settingsKey:'user_employees.employment_status'",
] as $contract) {
    $expect(str_contains($page, $contract), "화면 컬럼과 물리 metadata 연결 누락: {$contract}");
}
$virtualColumns = [
    "settingsKey:'role_name',settingsVirtualType:'other'",
    "settingsKey:'account_status',settingsVirtualType:'other'",
    "settingsKey:'permission_mode',settingsVirtualType:'other'",
    "settingsKey:'user_permission_count',settingsVirtualType:'calculated'",
];
foreach ($virtualColumns as $contract) {
    $expect(str_contains($page, $contract), "조회 가상컬럼 설정 연결 누락: {$contract}");
}
$expect(str_contains($page, 'individual-users.v7'), 'metadata schema 변경용 저장 버전이 갱신되지 않았습니다.');
$expect(str_contains($page, "tableDescription:'직원(user_employees) + 역할·계정·권한 조회 가상컬럼'"), '직원·가상컬럼 안내가 일치하지 않습니다.');
$expect(str_contains($repository, 'SELECT e.*,u.username'), '직원 전체 물리컬럼을 사용자목록 API가 반환하지 않습니다.');
$expect(str_contains($repository, 'FROM user_employees e JOIN auth_users u'), '사용자목록 조회가 직원 1행 기준이 아닙니다.');
$expect(str_contains($settings, 'const singlePhysicalSource = sourceTables.size === 1;'), '단일 원본 물리컬럼 값 연결 Guard가 없습니다.');
$expect(str_contains($settings, 'String(column?.source_column || column.key).trim()'), '단일 원본 placeholder가 실제 물리컬럼 값을 읽지 않습니다.');
$expect(str_contains($settings, 'const hasSystemHeaderControl = /<(?:i|input)\\b/i.test(originalHeaderTitle);'), '관리컬럼 표시명이 하드코딩 텍스트 헤더보다 우선하는 Guard가 없습니다.');
$expect(str_contains($settings, 'displayName === defaultDisplayName && hasSystemHeaderControl'), '아이콘·체크박스 헤더에만 원본 시스템 헤더를 유지하지 않습니다.');
$expect(!str_contains($page, "settingsKey:'handle'"), '개인별 권한목록에 페이지 전용 드래그핸들 key가 남아 있습니다.');
$expect(!str_contains($page, "settingsKey:'user_permission_id'"), '개인별 권한 관리 열이 별도 가상 key로 남아 있습니다.');
$expect(str_contains($page, "settingsKey:'__reorder'"), '개인별 권한목록 드래그핸들이 공용 __reorder에 연결되지 않았습니다.');
$expect(str_contains($page, "settingsKey:'__actions',settingsVirtualType:'system'"), '개인별 권한 관리 열이 공용 __actions에 연결되지 않았습니다.');
$expect(str_contains($page, 'individual-permissions.v8'), '개인별 권한목록 설정 schema 버전이 갱신되지 않았습니다.');
$expect(!str_contains($rolePermissionPage, "settingsKey: 'handle'"), '역할별 권한목록에 페이지 전용 드래그핸들 key가 남아 있습니다.');
$expect(!str_contains($rolePermissionPage, "settingsKey: 'role_permission_id'"), '역할별 권한 관리 열이 별도 가상 key로 남아 있습니다.');
$expect(str_contains($rolePermissionPage, "settingsKey: '__reorder'"), '역할별 권한목록 드래그핸들이 공용 __reorder에 연결되지 않았습니다.');
$expect(str_contains($rolePermissionPage, "settingsKey: '__actions'"), '역할별 권한 관리 열이 공용 __actions에 연결되지 않았습니다.');
$expect(str_contains($rolePermissionPage, 'permission-matrix.flat.v5'), '역할별 권한목록 설정 schema 버전이 갱신되지 않았습니다.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "개인권한 사용자목록 TableSettings 원본 테이블 계약 검증 통과\n");
