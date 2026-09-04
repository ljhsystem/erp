<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeService.php');
$model = file_get_contents($root . '/app/Models/Institution/RegularEmploymentIncomeModel.php');
$javascript = file_get_contents($root . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$overlaps = static fn(string $start, ?string $end): bool =>
    $start <= '2013-07-31' && ($end === null || $end >= '2013-07-01');

$assertions = [
    '재직기간 배치 조회' => str_contains($model, 'employmentCandidatesForPeriod'),
    '입퇴사일 월 교집합' => str_contains($model, "COALESCE(e.real_hire_date,e.doc_hire_date,'0001-01-01')<=:period_to")
        && str_contains($model, "COALESCE(e.real_retire_date,e.doc_retire_date,'9999-12-31')>=:period_from"),
    '재직상태 이력 교집합' => str_contains($model, "esh.status_code IN ('ACTIVE','ON_LEAVE')"),
    '계약 누락 사유' => str_contains($service, 'NO_VALID_EMPLOYMENT_CONTRACT'),
    '계약 중복 사유' => str_contains($service, 'MULTIPLE_VALID_EMPLOYMENT_CONTRACTS'),
    '후보 제외 요약 계약' => str_contains($service, "'candidates'")
        && str_contains($service, "'excluded'")
        && str_contains($service, "'summary'"),
    '후보와 계산 분리' => str_contains($javascript, 'if(recalculationTargets.length&&paymentDateInput.value)')
        && str_contains($javascript, 'API.CALCULATE'),
    '0건 사용자 알림' => str_contains($javascript, '대상 직원이 없습니다.'),
    '정상 조회 제외 수 기본 숨김' => !str_contains($javascript, '조회 제외 ${excluded.length}명'),
    '대상직원 employee_id 병합' => str_contains($javascript, 'const currentByEmployee=new Map()')
        && str_contains($javascript, 'const candidateByEmployee=new Map()')
        && str_contains($javascript, 'candidateByEmployee.delete(key)'),
    '기존 직원 PK와 Line 보존' => str_contains($javascript, 'return{...candidate,...existing')
        && str_contains($javascript, '기존 작성내용 보호를 위해 문서에 유지했습니다.'),
    '불러오기 사용자 안내' => str_contains($javascript, '새로 추가되거나 변경된 직원은 없습니다.')
        && str_contains($javascript, '급여정보를 갱신')
        && str_contains($javascript, '새 직원'),
    '실제 계산기초 변경 판정' => str_contains($javascript, 'calculationBasisFingerprint')
        && str_contains($javascript, 'beforeByEmployee.get(String(item.employee_id))!==calculationBasisFingerprint(item)'),
    '대상 이탈 기존 직원만 경고' => str_contains($javascript, 'retainedIneligible.length')
        && str_contains($javascript, 'notify(\'warning\',`${eligibleLoadMessage'),
    '서버 PK와 직원 방어' => str_contains($service, 'matchRequestedItems')
        && str_contains($service, '다른 문서의 직원 계산행 ID는 사용할 수 없습니다.'),
    '서버 2단계 순번 재배치' => str_contains($model, '$temporaryBase')
        && str_contains($model, '[\'sort_no\'=>$temporaryBase+$index]'),
    '귀속연월 변경 무효화' => str_contains($javascript, '귀속연월이 변경되어 기존 직원 내역을 초기화했습니다.'),
    '계산 차단사유 Grid 표시' => str_contains($javascript, "key:'calculation_message',label:'확인사항'"),
    'Scenario A 월중 입사 포함' => $overlaps('2013-07-19', null),
    'Scenario B 월중 퇴사 포함' => $overlaps('2013-01-01', '2013-07-31'),
    'Scenario C 익월 입사 제외' => !$overlaps('2013-08-01', null),
    'Scenario D 전월 퇴사 제외' => !$overlaps('2013-01-01', '2013-06-30'),
    'Scenario E 계약 없음 사유' => str_contains($service, 'NO_VALID_EMPLOYMENT_CONTRACT'),
    'Scenario F 사회보험은 후보 SQL에서 제외하지 않음' => !str_contains($model, 'institution_social_insurance_coverages'),
    'Scenario G 근태는 후보 SQL에서 제외하지 않음' => !str_contains($model, 'institution_attendance'),
    'Scenario H 0명 안내' => str_contains($javascript, '대상 직원이 없습니다.'),
];

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'assertions' => $assertions, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
