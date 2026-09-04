<?php

global $router;

$institutionRoutes = [
    [
        '/institution',
        'web.institution.dashboard',
        '대시보드',
        '대외기관업무 > 대시보드',
        'InstitutionController@webIndex',
    ],
    [
        '/institution/human-resources/employment-contracts',
        'web.institution.human_resources.employment_contracts',
        '근로계약관리',
        '대외기관업무 > 인사·노무관리 > 근로계약관리',
        'EmploymentContractController@webIndex',
    ],
    [
        '/institution/human-resources/personnel-actions',
        'web.institution.human_resources.personnel_actions',
        '인사발령관리',
        '대외기관업무 > 인사·노무관리 > 인사발령관리',
        'PersonnelActionController@webIndex',
    ],
    [
        '/institution/human-resources/job-assignments',
        'web.institution.human_resources.job_assignments',
        '직무·배치관리',
        '대외기관업무 > 인사·노무관리 > 직무·배치관리',
        'JobAssignmentController@webIndex',
    ],
    [
        '/institution/human-resources/attendance',
        'web.institution.human_resources.attendance',
        '근태관리',
        '대외기관업무 > 인사·노무관리 > 근태관리',
        'AttendanceController@webIndex',
    ],
    [
        '/institution/human-resources/leave',
        'web.institution.human_resources.leave',
        '휴가관리',
        '대외기관업무 > 인사·노무관리 > 휴가관리',
        'LeaveController@webIndex',
    ],
    [
        '/institution/human-resources/qualification-education',
        'web.institution.human_resources.qualification_education',
        '자격·교육관리',
        '대외기관업무 > 인사·노무관리 > 자격·교육관리',
        'QualificationEducationController@webIndex',
    ],
    [
        '/institution/human-resources/performance-evaluations',
        'web.institution.human_resources.performance_evaluations',
        '성과평가관리',
        '대외기관업무 > 인사·노무관리 > 성과평가관리',
        'InstitutionController@webPlaceholder',
    ],
    [
        '/institution/human-resources/compensation-incentives',
        'web.institution.human_resources.compensation_incentives',
        '보상·인센티브관리',
        '대외기관업무 > 인사·노무관리 > 보상·인센티브관리',
        'InstitutionController@webPlaceholder',
    ],
    [
        '/institution/human-resources/employment-rules',
        'web.institution.human_resources.employment_rules',
        '취업규칙·인사규정',
        '대외기관업무 > 인사·노무관리 > 취업규칙·인사규정',
        'EmploymentRuleController@webIndex',
    ],
    [
        '/institution/income-data/regular-employment',
        'web.institution.income_data.regular_employment',
        '상용근로소득',
        '대외기관업무 > 소득자료관리 > 상용근로소득',
        'RegularEmploymentIncomeController@webIndex',
    ],
    [
        '/institution/income-data/daily-employment',
        'web.institution.income_data.daily_employment',
        '일용근로소득',
        '대외기관업무 > 소득자료관리 > 일용근로소득',
        'DailyEmploymentIncomeController@webIndex',
    ],
    [
        '/institution/income-data/business-income',
        'web.institution.income_data.business_income',
        '사업소득',
        '대외기관업무 > 소득자료관리 > 사업소득',
        'BusinessIncomeController@webIndex',
    ],
    [
        '/institution/national-tax',
        'web.institution.national_tax',
        '국세업무',
        '대외기관업무 > 국세업무',
        'InstitutionController@webPlaceholder',
    ],
    [
        '/institution/local-tax',
        'web.institution.local_tax',
        '지방세업무',
        '대외기관업무 > 지방세업무',
        'InstitutionController@webPlaceholder',
    ],
    [
        '/institution/social-insurance',
        'web.institution.social_insurance',
        '4대보험업무',
        '대외기관업무 > 4대보험업무',
        'InstitutionController@webPlaceholder',
        '메뉴 구조가 연결되었습니다. 4대보험 신고·납부·정산 업무 기능은 추후 단계에서 제공됩니다.',
    ],
    [
        '/institution/tax-agent',
        'web.institution.tax_agent',
        '세무사업무',
        '대외기관업무 > 세무사업무',
        'InstitutionController@webPlaceholder',
    ],
    [
        '/institution/filing-history',
        'web.institution.filing_history',
        '신고이력',
        '대외기관업무 > 신고이력',
        'InstitutionController@webPlaceholder',
    ],
];

foreach ($institutionRoutes as $route) {
    [$path, $key, $name, $description, $handler, $pageNotice] = array_pad($route, 6, null);
    $router->get($path, $handler, [
        'key' => $key,
        'page_key' => $key === 'web.institution.dashboard' ? 'institution.dashboard' : $key,
        'page' => $name,
        'page_description' => "{$name} 화면",
        'permission_name' => '화면조회',
        'permission_description' => "{$name} 화면 조회",
        'name' => $name,
        'description' => $description,
        'category' => '대외기관업무',
        'auth' => true,
        'permissions' => ['view'],
        'log' => false,
        'page_notice' => $pageNotice,
    ]);
}
