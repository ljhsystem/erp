<?php

global $router;

$router->get('/api/institution/human-resources/pay-component/options', 'PayComponentController@apiOptions', [
    'key' => 'api.institution.human_resources.pay_component.options',
    'page' => '급여항목',
    'page_description' => '근로계약과 상용근로소득이 공유하는 적용일 기준 급여항목 선택옵션',
    'permission_name' => '급여항목 옵션조회',
    'permission_description' => '활성 급여항목 선택옵션 조회',
    'name' => '급여항목 옵션조회',
    'description' => '대외기관업무 > 인사·노무관리 > 급여항목 옵션조회',
    'category' => '대외기관업무',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);
$payComponentManagementRoutes = [
    ['GET','/api/institution/human-resources/pay-component/list','apiList','list','목록조회',['view']],
    ['GET','/api/institution/human-resources/pay-component/detail','apiDetail','detail','상세조회',['view']],
    ['POST','/api/institution/human-resources/pay-component/save','apiSave','save','저장',['save']],
    ['POST','/api/institution/human-resources/pay-component/delete','apiDelete','delete','삭제',['delete']],
    ['POST','/api/institution/human-resources/pay-component/reorder','apiReorder','reorder','순서변경',['save']],
];
foreach ($payComponentManagementRoutes as [$method,$url,$action,$suffix,$permissionName,$permissions]) {
    $router->{strtolower($method)}($url, 'PayComponentController@'.$action, [
        'key'=>'api.institution.human_resources.pay_component.'.$suffix,
        'page_key'=>'web.institution.human_resources.employment_contracts',
        'page'=>'급여항목 관리','page_description'=>'근로계약 지급조건 급여항목 마스터 관리',
        'permission_name'=>$permissionName,'permission_description'=>'급여항목 '.$permissionName,
        'name'=>'급여항목 '.$permissionName,'description'=>'대외기관업무 > 인사·노무관리 > 근로계약관리 > 급여항목 '.$permissionName,
        'category'=>'대외기관업무','auth'=>true,'permissions'=>$permissions,'log'=>$method!=='GET',
    ]);
}

$regularEmploymentIncomeRoutes = [
    ['POST','/api/institution/income-data/regular-employment/list','apiList','list','목록조회',['view']],
    ['GET','/api/institution/income-data/regular-employment/detail','apiDetail','detail','상세조회',['view']],
    ['GET','/api/institution/income-data/regular-employment/eligible-employees','apiEligibleEmployees','eligible_employees','대상직원조회',['view']],
    ['POST','/api/institution/income-data/regular-employment/calculate','apiCalculate','calculate','자동계산',['save']],
    ['POST','/api/institution/income-data/regular-employment/adjust','apiAdjust','adjust','항목조정',['save']],
    ['GET','/api/institution/income-data/regular-employment/report-dataset','apiReportDataset','report_dataset','급여대장데이터셋',['view']],
    ['POST','/api/institution/income-data/regular-employment/save','apiSave','save','저장',['save']],
    ['POST','/api/institution/income-data/regular-employment/submit','apiSubmit','submit','결재요청',['save']],
    ['POST','/api/institution/income-data/regular-employment/withdraw','apiWithdraw','withdraw','기안회수',['save']],
    ['POST','/api/institution/income-data/regular-employment/delete','apiDelete','delete','삭제',['delete']],
    ['GET','/api/institution/income-data/regular-employment/trash','apiTrashList','trash','휴지통조회',['view']],
    ['POST','/api/institution/income-data/regular-employment/restore','apiRestore','restore','복원',['save']],
    ['POST','/api/institution/income-data/regular-employment/restore-bulk','apiRestoreBulk','restore_bulk','선택복원',['save']],
    ['POST','/api/institution/income-data/regular-employment/restore-all','apiRestoreAll','restore_all','전체복원',['save']],
    ['POST','/api/institution/income-data/regular-employment/purge','apiPurge','purge','완전삭제',['delete']],
    ['POST','/api/institution/income-data/regular-employment/purge-bulk','apiPurgeBulk','purge_bulk','선택완전삭제',['delete']],
    ['POST','/api/institution/income-data/regular-employment/purge-all','apiPurgeAll','purge_all','전체완전삭제',['delete']],
];
foreach($regularEmploymentIncomeRoutes as[$method,$url,$action,$suffix,$permissionName,$permissions]){$router->{strtolower($method)}($url,'RegularEmploymentIncomeController@'.$action,['key'=>'api.institution.income_data.regular_employment.'.$suffix,'page'=>'상용근로소득','page_description'=>'귀속월별 상용근로소득 작성·결재·회계연결','permission_name'=>$permissionName,'permission_description'=>'상용근로소득 '.$permissionName,'name'=>'상용근로소득 '.$permissionName,'description'=>'대외기관업무 > 소득자료관리 > 상용근로소득 > '.$permissionName,'category'=>'대외기관업무','auth'=>true,'permissions'=>$permissions,'log'=>$method!=='GET'&&$action!=='apiList']);}

$employmentRuleRoutes=[['POST','/api/institution/human-resources/employment-rules/list','apiList','view'],['GET','/api/institution/human-resources/employment-rules/detail','apiDetail','detail'],['GET','/api/institution/human-resources/employment-rules/history','apiHistory','history'],['GET','/api/institution/human-resources/employment-rules/options','apiOptions','options'],['POST','/api/institution/human-resources/employment-rules/save','apiSave','save'],['POST','/api/institution/human-resources/employment-rules/revise','apiRevise','revise'],['POST','/api/institution/human-resources/employment-rules/submit','apiSubmit','submit'],['POST','/api/institution/human-resources/employment-rules/withdraw','apiWithdraw','withdraw'],['POST','/api/institution/human-resources/employment-rules/activate','apiActivate','activate'],['POST','/api/institution/human-resources/employment-rules/delete','apiDelete','delete']];foreach($employmentRuleRoutes as[$m,$u,$a,$k])$router->{strtolower($m)}($u,'EmploymentRuleController@'.$a,['key'=>'api.institution.human_resources.employment_rules.'.$k,'page_key'=>'web.institution.human_resources.employment_rules','page'=>'취업규칙·인사규정','page_description'=>'회사 공식 규정문서 및 개정 관리','permission_name'=>$k,'permission_description'=>'규정 '.$k,'name'=>'규정 '.$k,'description'=>'규정 '.$k,'category'=>'대외기관업무','auth'=>true,'permissions'=>[$k],'log'=>$m!=='GET'&&$a!=='apiList']);

$qualificationEducationRoutes = [
 ['GET','/api/institution/human-resources/qualification-education/options','apiOptions','options','선택옵션조회',['view']],
 ['POST','/api/institution/human-resources/qualification-education/qualification/list','apiQualificationList','qualification_list','본인자격조회',['view']],
 ['POST','/api/institution/human-resources/qualification-education/qualification/all-list','apiQualificationList','qualification_all_list','전체자격조회',['view']],
 ['GET','/api/institution/human-resources/qualification-education/qualification/detail','apiQualificationDetail','qualification_detail','자격상세조회',['view']],
 ['POST','/api/institution/human-resources/qualification-education/qualification/save','apiQualificationSave','save','자격등록수정',['save']],
 ['POST','/api/institution/human-resources/qualification-education/qualification/verify','apiQualificationVerify','verify','자격검증',['save']],
 ['POST','/api/institution/human-resources/qualification-education/qualification/renew','apiQualificationRenew','renew','자격갱신',['save']],
 ['POST','/api/institution/human-resources/qualification-education/qualification/invalidate','apiQualificationInvalidate','qualification_invalidate','자격무효화',['save']],
 ['POST','/api/institution/human-resources/qualification-education/qualification/delete','apiQualificationDelete','delete','자격삭제',['save']],
 ['POST','/api/institution/human-resources/qualification-education/education/list','apiEducationList','education_list','본인교육조회',['view']],
 ['POST','/api/institution/human-resources/qualification-education/education/all-list','apiEducationList','education_all_list','전체교육조회',['view']],
 ['GET','/api/institution/human-resources/qualification-education/education/detail','apiEducationDetail','education_detail','교육상세조회',['view']],
 ['POST','/api/institution/human-resources/qualification-education/education/save','apiEducationSave','education_manage','교육이수등록수정',['save']],
 ['POST','/api/institution/human-resources/qualification-education/education/delete','apiEducationDelete','education_delete','교육이력삭제',['save']],
 ['POST','/api/institution/human-resources/qualification-education/education/invalidate','apiEducationInvalidate','education_invalidate','교육이력무효화',['save']],
 ['GET','/api/institution/human-resources/qualification-education/qualification-type/list','apiQualificationTypeList','qualification_type_list','자격기준조회',['view']],
 ['POST','/api/institution/human-resources/qualification-education/qualification-type/save','apiQualificationTypeSave','policy_manage','자격기준관리',['save']],
 ['GET','/api/institution/human-resources/qualification-education/course/list','apiCourseList','course_list','교육과정조회',['view']],
 ['POST','/api/institution/human-resources/qualification-education/course/save','apiCourseSave','course_manage','교육과정관리',['save']],
 ['GET','/api/institution/human-resources/qualification-education/requirement/list','apiRequirementList','requirement_list','직무요건조회',['view']],
 ['POST','/api/institution/human-resources/qualification-education/requirement/save','apiRequirementSave','requirement_manage','직무요건관리',['save']],
 ['POST','/api/institution/human-resources/qualification-education/policy/reorder','apiPolicyReorder','policy_reorder','기준순서변경',['save']],
];
foreach($qualificationEducationRoutes as[$method,$url,$action,$suffix,$permissionName,$permissions]){$router->{strtolower($method)}($url,'QualificationEducationController@'.$action,['key'=>'api.institution.human_resources.qualification_education.'.$suffix,'page'=>'자격·교육관리','page_description'=>'직원 자격·교육·만료·갱신 관리','permission_name'=>$permissionName,'permission_description'=>'자격·교육관리 '.$permissionName,'name'=>'자격·교육관리 '.$permissionName,'description'=>'대외기관업무 > 인사·노무관리 > 자격·교육관리 > '.$permissionName,'category'=>'대외기관업무','auth'=>true,'permissions'=>$permissions,'log'=>$method!=='GET']);}

$educationSessionRoutes = [
 ['POST','/api/institution/human-resources/qualification-education/session/list','apiList','session_list','교육일정조회','api.institution.human_resources.qualification_education.education_all_list',['view']],
 ['GET','/api/institution/human-resources/qualification-education/session/detail','apiDetail','session_detail','교육일정상세','api.institution.human_resources.qualification_education.education_all_list',['view']],
 ['POST','/api/institution/human-resources/qualification-education/session/save','apiSave','session_save','교육일정저장','api.institution.human_resources.qualification_education.education_manage',['save']],
 ['POST','/api/institution/human-resources/qualification-education/session/transition','apiTransition','session_transition','교육일정상태변경','api.institution.human_resources.qualification_education.education_manage',['save']],
 ['POST','/api/institution/human-resources/qualification-education/session-target/list','apiTargetList','session_target_list','교육대상조회','api.institution.human_resources.qualification_education.education_all_list',['view']],
 ['POST','/api/institution/human-resources/qualification-education/session-target/add','apiTargetAdd','session_target_add','교육대상추가','api.institution.human_resources.qualification_education.education_manage',['save']],
 ['POST','/api/institution/human-resources/qualification-education/session-target/remove','apiTargetRemove','session_target_remove','교육대상제외','api.institution.human_resources.qualification_education.education_manage',['save']],
 ['POST','/api/institution/human-resources/qualification-education/session-target/outcome','apiTargetOutcome','session_target_outcome','참석이수처리','api.institution.human_resources.qualification_education.education_manage',['save']],
 ['POST','/api/institution/human-resources/qualification-education/session-target/acknowledge','apiTargetAcknowledge','session_target_acknowledge','교육내용확인','api.institution.human_resources.qualification_education.education_list',['view']],
];
foreach($educationSessionRoutes as[$method,$url,$action,$suffix,$permissionName,$permissionKey,$permissions]){$router->{strtolower($method)}($url,'EducationSessionController@'.$action,['key'=>'api.institution.human_resources.qualification_education.'.$suffix,'permission_key'=>$permissionKey,'page'=>'자격·교육관리','page_description'=>'교육 일정·대상·참석·이수 운영','permission_name'=>$permissionName,'permission_description'=>'자격·교육관리 '.$permissionName,'name'=>'자격·교육관리 '.$permissionName,'description'=>'대외기관업무 > 인사·노무관리 > 자격·교육관리 > '.$permissionName,'category'=>'대외기관업무','auth'=>true,'permissions'=>$permissions,'log'=>$method!=='GET']);}

$jobAssignmentRoutes = [
    ['POST', '/api/institution/human-resources/job-assignment/list', 'apiList', 'list', '목록조회', ['view']],
    ['GET', '/api/institution/human-resources/job-assignment/detail', 'apiDetail', 'detail', '상세조회', ['view']],
    ['GET', '/api/institution/human-resources/job-assignment/options', 'apiOptions', 'options', '입력옵션조회', ['view']],
    ['POST', '/api/institution/human-resources/job-assignment/history-save', 'apiHistorySave', 'history_save', '과거직무이력등록', ['save']],
    ['POST', '/api/institution/human-resources/job-assignment/project-save', 'apiProjectSave', 'project_save', '프로젝트배치등록', ['save']],
    ['POST', '/api/institution/human-resources/job-assignment/end', 'apiEnd', 'end', '프로젝트배치종료', ['save']],
    ['POST', '/api/institution/human-resources/job-assignment/correct', 'apiCorrect', 'correct', '관리자정정', ['save']],
];

$attendanceRoutes = [
    ['POST','/api/institution/human-resources/attendance/daily-list','apiDailyList','daily_list','목록조회',['view']],
    ['POST','/api/institution/human-resources/attendance/monthly-list','apiMonthlyList','monthly_list','월별조회',['view']],
    ['POST','/api/institution/human-resources/attendance/closure-list','apiClosureList','closure_list','월마감목록조회',['view']],
    ['POST','/api/institution/human-resources/attendance/exception-list','apiExceptionList','exception_list','이상근태조회',['view']],
    ['GET','/api/institution/human-resources/attendance/detail','apiDetail','detail','상세조회',['view']],
    ['GET','/api/institution/human-resources/attendance/closure-histories','apiClosureHistories','closure_histories','마감이력조회',['view']],
    ['GET','/api/institution/human-resources/attendance/options','apiOptions','options','선택옵션조회',['view']],
    ['GET','/api/institution/human-resources/attendance/scope/all','apiScope','view_all','전체직원조회',['view']],
    ['GET','/api/institution/human-resources/attendance/scope/self','apiScope','view_self','본인조회',['view']],
    ['POST','/api/institution/human-resources/attendance/clock/self','apiClockSelf','clock_self','본인출퇴근등록',['save']],
    ['POST','/api/institution/human-resources/attendance/clock/admin','apiClockAdmin','clock_admin','관리자출퇴근등록',['save']],
    ['POST','/api/institution/human-resources/attendance/recalculate','apiRecalculate','recalculate','일별재계산',['save']],
    ['POST','/api/institution/human-resources/attendance/correct','apiCorrect','correct','관리자정정',['save']],
    ['POST','/api/institution/human-resources/attendance/clock/invalidate','apiClockInvalidate','clock_invalidate','출퇴근원본무효화',['save']],
    ['POST','/api/institution/human-resources/attendance/close','apiClose','close','월마감',['save']],
    ['POST','/api/institution/human-resources/attendance/reopen','apiReopen','reopen','월마감해제',['save']],
];
$leaveRoutes = [
    ['POST','/api/institution/human-resources/leave/status-list','apiStatusList','status_list','전체현황조회',['view']],
    ['POST','/api/institution/human-resources/leave/balance-list','apiBalanceList','balance_list','부여잔액조회',['view']],
    ['GET','/api/institution/human-resources/leave/options','apiOptions','options','선택항목조회',['view']],
    ['GET','/api/institution/human-resources/leave/detail','apiDetail','detail','상세조회',['view']],
    ['POST','/api/institution/human-resources/leave/grant','apiGrant','grant','휴가부여',['save']],
    ['POST','/api/institution/human-resources/leave/adjust','apiAdjust','adjust','잔액조정',['save']],
    ['POST','/api/institution/human-resources/leave/type-save','apiTypeSave','type_save','정책저장',['save']],
];
foreach($leaveRoutes as[$method,$url,$action,$suffix,$permissionName,$permissions]){$router->{strtolower($method)}($url,'LeaveController@'.$action,['key'=>'api.institution.human_resources.leave.'.$suffix,'page'=>'휴가관리','page_description'=>'휴가 신청·부여·잔액·원장 관리','permission_name'=>$permissionName,'permission_description'=>'휴가 '.$permissionName,'name'=>'휴가 '.$permissionName,'description'=>'대외기관업무 > 인사·노무관리 > 휴가관리 > '.$permissionName,'category'=>'대외기관업무','auth'=>true,'permissions'=>$permissions,'log'=>$method!=='GET']);}
foreach($attendanceRoutes as [$method,$url,$action,$suffix,$permissionName,$permissions]){
    $router->{strtolower($method)}($url,'AttendanceController@'.$action,[
        'key'=>'api.institution.human_resources.attendance.'.$suffix,'page'=>'근태관리','page_description'=>'실제 출퇴근·일별 근태·월 마감 관리',
        'permission_name'=>$permissionName,'permission_description'=>'근태 '.$permissionName,'name'=>'근태 '.$permissionName,
        'description'=>'대외기관업무 > 인사·노무관리 > 근태관리 > '.$permissionName,'category'=>'대외기관업무','auth'=>true,'permissions'=>$permissions,'log'=>$method!=='GET'&&!str_ends_with($action,'List'),
    ]);
}
foreach ($jobAssignmentRoutes as [$method, $url, $action, $suffix, $permissionName, $permissions]) {
    $router->{strtolower($method)}($url, 'JobAssignmentController@' . $action, [
        'key' => 'api.institution.human_resources.job_assignment.' . $suffix,
        'page' => '직무·배치관리', 'page_description' => '직원 직무·배치 현재상태 및 기간이력 조회',
        'permission_name' => $permissionName, 'permission_description' => '직무·배치 ' . $permissionName,
        'name' => '직무·배치 ' . $permissionName, 'description' => '대외기관업무 > 인사·노무관리 > 직무·배치관리 > ' . $permissionName,
        'category' => '대외기관업무', 'auth' => true, 'permissions' => $permissions, 'log' => $method !== 'GET' && $action !== 'apiList',
    ]);
}

$personnelActionRoutes = [
    ['POST', '/api/institution/human-resources/personnel-action/list', 'apiList', 'list', '목록조회', ['view']],
    ['GET', '/api/institution/human-resources/personnel-action/detail', 'apiDetail', 'detail', '상세조회', ['view']],
    ['GET', '/api/institution/human-resources/personnel-action/options', 'apiOptions', 'options', '입력옵션조회', ['view']],
    ['POST', '/api/institution/human-resources/personnel-action/save', 'apiSave', 'save', '저장', ['save']],
    ['POST', '/api/institution/human-resources/personnel-action/reorder', 'apiReorder', 'reorder', '순서변경', ['save']],
    ['POST', '/api/institution/human-resources/personnel-action/submit', 'apiSubmit', 'submit', '결재요청', ['save']],
    ['POST', '/api/institution/human-resources/personnel-action/withdraw', 'apiWithdraw', 'withdraw', '기안회수', ['save']],
    ['POST', '/api/institution/human-resources/personnel-action/apply', 'apiApply', 'apply', '발령적용', ['save']],
    ['POST', '/api/institution/human-resources/personnel-action/delete', 'apiDelete', 'delete', '삭제', ['delete']],
    ['GET', '/api/institution/human-resources/personnel-action/trash', 'apiTrashList', 'trash', '휴지통조회', ['view']],
    ['POST', '/api/institution/human-resources/personnel-action/restore', 'apiRestore', 'restore', '복원', ['save']],
    ['POST', '/api/institution/human-resources/personnel-action/purge', 'apiPurge', 'purge', '완전삭제', ['delete']],
    ['POST', '/api/institution/human-resources/personnel-action/purge-bulk', 'apiPurgeBulk', 'purge-bulk', '선택완전삭제', ['delete']],
    ['POST', '/api/institution/human-resources/personnel-action/purge-all', 'apiPurgeAll', 'purge-all', '전체완전삭제', ['delete']],
];
foreach ($personnelActionRoutes as [$method,$url,$action,$suffix,$permissionName,$permissions]) {
    $router->{strtolower($method)}($url,'PersonnelActionController@'.$action,[
        'key'=>'api.institution.human_resources.personnel_action.'.$suffix,
        'page'=>'인사발령관리','page_description'=>'인사발령 기안·결재·적용 관리',
        'permission_name'=>$permissionName,'permission_description'=>'인사발령 '.$permissionName,
        'name'=>'인사발령 '.$permissionName,'description'=>'대외기관업무 > 인사·노무관리 > 인사발령관리 > '.$permissionName,
        'category'=>'대외기관업무','auth'=>true,'permissions'=>$permissions,'log'=>$method!=='GET'&&$action!=='apiList',
    ]);
}

$employmentContractRoutes = [
    ['POST', '/api/institution/human-resources/employment-contract/list', 'apiList', 'list', '목록조회', ['view']],
    ['GET', '/api/institution/human-resources/employment-contract/detail', 'apiDetail', 'detail', '상세조회', ['view']],
    ['GET', '/api/institution/human-resources/employment-contract/options', 'apiOptions', 'options', '입력옵션조회', ['view']],
    ['GET', '/api/institution/human-resources/employment-contract/statutory-projection', 'apiStatutoryProjection', 'statutory_projection', '법정기준검증조회', ['view']],
    ['POST', '/api/institution/human-resources/employment-contract/save', 'apiSave', 'save', '저장', ['save']],
    ['POST', '/api/institution/human-resources/employment-contract/reorder', 'apiReorder', 'reorder', '순서변경', ['save']],
    ['POST', '/api/institution/human-resources/employment-contract/submit', 'apiSubmit', 'submit', '결재요청', ['save']],
    ['POST', '/api/institution/human-resources/employment-contract/withdraw', 'apiWithdraw', 'withdraw', '기안회수', ['save']],
    ['POST', '/api/institution/human-resources/employment-contract/revise', 'apiRevise', 'revise', '계약개정', ['save', 'salary']],
    ['POST', '/api/institution/human-resources/employment-contract/terminate', 'apiTerminate', 'terminate', '종료해지', ['save']],
    ['POST', '/api/institution/human-resources/employment-contract/delete', 'apiDelete', 'delete', '삭제', ['delete']],
    ['GET', '/api/institution/human-resources/employment-contract/trash', 'apiTrashList', 'trash', '휴지통조회', ['view']],
    ['POST', '/api/institution/human-resources/employment-contract/restore', 'apiRestore', 'restore', '복구', ['save']],
    ['POST', '/api/institution/human-resources/employment-contract/purge', 'apiPurge', 'purge', '완전삭제', ['delete']],
];

foreach ($employmentContractRoutes as [$method, $url, $action, $suffix, $permissionName, $permissions]) {
    $router->{strtolower($method)}($url, 'EmploymentContractController@' . $action, [
        'key' => 'api.institution.human_resources.employment_contract.' . $suffix,
        'page' => '근로계약관리',
        'page_description' => '근로계약 작성 및 결재 관리',
        'permission_name' => $permissionName,
        'permission_description' => '근로계약 ' . $permissionName,
        'name' => '근로계약 ' . $permissionName,
        'description' => '대외기관업무 > 인사·노무관리 > 근로계약관리 > ' . $permissionName,
        'category' => '대외기관업무',
        'auth' => true,
        'permissions' => $permissions,
        'log' => $method !== 'GET' && $action !== 'apiList',
    ]);
}
