<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/app/Services/Institution/DailyEmploymentIncomeService.php';
$source = file_get_contents($path);
$root = dirname(__DIR__, 2);
$viewSource = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$excelSource = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeExcelService.php');
$policySource = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeGroupInsurancePolicyService.php');
if ($source === false) {
    fwrite(STDERR, "일용근로소득 Service를 읽을 수 없습니다.\n");
    exit(1);
}

$checks = [
    'Group 고용보험 상태 전달' => str_contains($source, "'employment_insurance_application_status_code'=>\$groupInsurance"),
    'Group 산재보험 상태 전달' => str_contains($source, "'industrial_accident_application_status_code'=>\$groupInsurance"),
    '고용보험 미적용 공제 제외' => str_contains($source, "if (\$employmentStatus === 'EXCLUDED')"),
    'Group 적용상태 2값 제한' => str_contains($policySource, "if (!in_array(\$status, ['APPLICABLE', 'EXCLUDED'], true))"),
    '사용자부담 상태별 분기' => str_contains($source, "['INDUSTRIAL_ACCIDENT_INSURANCE', '산재보험 사용자부담', \$industrialStatus"),
    '미적용 Line 0원 보존' => str_contains($source, "'calculation_status_code' => 'EXCLUDED'"),
    '미적용 Line 근로자 실지급 미영향' => str_contains($source, "'line_type_code' => \$lineType")
        && str_contains($source, "'final_amount' => 0.0"),
    '건설 미선택 상태 Preview·저장 분리' => str_contains($policySource, "'COMPANY_BURDEN_SETTING_REQUIRED'")
        && str_contains($policySource, "if (\$requireComplete)"),
    '모든 사업구분 보험 UI 표시' => !str_contains($viewSource, "if (policy?.uses_project && group.project_id)")
        && str_contains($viewSource, "[['employment_insurance', '고용보험 회사부담'], ['industrial_accident', '산재보험 회사부담']].forEach"),
    '본사 근로계약 안내 제거' => !str_contains($viewSource, '본사는 작업자별 근로계약' . '·보험 적용정보를 사용합니다.'),
    '본사 보험 Payload 보존' => str_contains($viewSource, 'employment_insurance_application_status_code: group.employment_insurance_application_status_code || null')
        && str_contains($viewSource, 'industrial_accident_application_status_code: group.industrial_accident_application_status_code || null'),
    'Group Header 보험 요약' => str_contains($viewSource, '고용 ${insuranceStatusLabel')
        && str_contains($viewSource, '산재 ${insuranceStatusLabel'),
    '신규 Group 보험 미선택 기본값' => substr_count($viewSource, "_application_status_code: source?.") >= 2
        && !str_contains($viewSource, "|| 'EXCLUDED'"),
    '보험 Select 회사부담 2값 전용' => str_contains($viewSource, "{ id: 'APPLICABLE', name: '우리 회사 부담' }")
        && str_contains($viewSource, "{ id: 'EXCLUDED', name: '우리 회사 미부담' }")
        && !str_contains($viewSource, "{ id: 'CONFIRMATION_REQUIRED', name: '확인 필요' }"),
    '보험 Select 미선택 옵션 제공' => str_contains($viewSource, "'선택해 주세요'"),
    '회사부담 사유입력 제거' => !str_contains($viewSource, "reason.required = status.value === 'EXCLUDED'")
        && !str_contains($excelSource, '고용보험 설정사유'),
    '사업구분·수동 출처 분리' => str_contains($policySource, "DAILY_GROUP_MANUAL_SETTING")
        && str_contains($policySource, "BUSINESS_DIVISION_POLICY")
        && str_contains($viewSource, "'DAILY_GROUP_MANUAL_SETTING'")
        && str_contains($viewSource, "'BUSINESS_DIVISION_POLICY'"),
    '본사·통신판매 자동 회사부담' => str_contains($policySource, "['HQ', 'ECOMMERCE']")
        && str_contains($viewSource, "businessUnit === 'HQ' || businessUnit === 'ECOMMERCE'"),
    '사업·프로젝트 변경 시 수동판정 유지' => !str_contains($viewSource, 'resetGroupInsuranceDecision'),
    '서버 프로젝트 없는 Group 보험판정 허용' => !str_contains($source, '$projectScope = !empty($policy')
        && !str_contains($source, '$employmentStatus = $projectScope'),
    'Excel 본사 보험판정 허용' => !str_contains($excelSource, '본사 Group에는 현장 보험판정을' . ' 입력할 수 없습니다.'),
    'Excel 회사부담 2값 제한' => str_contains($excelSource, "['APPLICABLE','EXCLUDED']")
        && str_contains($excelSource, '고용보험 회사부담')
        && !str_contains($excelSource, "['APPLICABLE','EXCLUDED','CONFIRMATION_REQUIRED']"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode([
    'success' => $failed === [],
    'checks' => $checks,
    'failed' => $failed,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

exit($failed === [] ? 0 : 1);
