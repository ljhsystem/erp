<?php

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$errors = [];
$expect = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) $errors[] = $message;
};

$approvalRoutes = $read('routes/api/approval.php');
$institutionRoutes = $read('routes/api/institution.php');
$approvalView = $read('app/views/approval/leave-request/index.php');
$adminView = $read('app/views/institution/leave/index.php');
$requestService = $read('app/Services/Approval/LeaveRequestService.php');
$leaveService = $read('app/Services/Institution/LeaveService.php');
$sidebar = $read('app/views/layout/sidebar.php');
$breadcrumb = $read('app/views/layout/breadcrumb.php');
$employeeJs = $read('public/assets/js/pages/approval/leave-request/index.js');
$adminJs = $read('public/assets/js/pages/institution/leave/index.js');

foreach (['list','detail','options','save','save-submit','withdraw','cancel-request'] as $suffix) {
    $expect(str_contains($approvalRoutes, "/api/approval/leave-request/$suffix"), "직원 휴가 Route 누락: $suffix");
}
foreach (['/leave/list','/leave/all-list','/leave/save','/leave/submit','/leave/withdraw','/leave/cancel'] as $legacy) {
    $expect(!str_contains($institutionRoutes, $legacy), "구 Institution 직원 Route 잔존: $legacy");
}
$expect(!str_contains($approvalView, 'employee_id'), '직원 휴가신청 View에 employee_id가 존재합니다.');
$expect(!str_contains($approvalView, '직원 선택'), '직원 휴가신청 View에 직원 Picker가 존재합니다.');
$expect(str_contains($adminView, '휴가 현황') && str_contains($adminView, '부여·잔액') && str_contains($adminView, '휴가 유형'), '관리자 3개 탭이 완성되지 않았습니다.');
$expect(!str_contains($adminView, '내 휴가') && !str_contains($adminView, '휴가 신청·이력'), '관리자 화면에 직원 신청 탭이 남아 있습니다.');
$expect(str_contains($requestService, "unset(\$input['employee_id'])"), '직원 입력 employee_id 제거가 없습니다.');
$expect(str_contains($leaveService, 'assertOwned'), 'P1 Service 소유권 검증이 없습니다.');
$expect(str_contains($leaveService, "usage_status_code'=>'ACTIVE'") && str_contains($leaveService, "'RESTORE'"), 'Usage/Ledger lifecycle가 유지되지 않았습니다.');
$expect(str_contains($sidebar, "'/approval/leave-request', '휴가신청'"), '전자결재 휴가신청 메뉴가 없습니다.');
$expect(str_contains($breadcrumb, "'/approval/leave-request' => ['items' => ['전자결재', '휴가신청']]"), '휴가신청 Breadcrumb가 일치하지 않습니다.');
$expect(str_contains($employeeJs, "pageKey:'approval.leave_request'") && str_contains($employeeJs, "tableKey:'leave-request-table'"), '직원 TableSettings domain이 일치하지 않습니다.');
foreach (['status','balances','types'] as $domain) $expect(str_contains($adminJs, "institution.human_resources.leave.\${active}"), '관리자 TableSettings domain 분리가 없습니다.');

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}
echo "leave domain split contract: OK\n";
