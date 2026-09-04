<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$eligibility = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeInsuranceEligibilityService.php');
$result = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeCalculationResultService.php');
$view = file_get_contents($root . '/app/views/institution/daily-employment-income/index.php');
$routes = file_get_contents($root . '/routes/api/daily-employment-income.php');

$checks = [
    '과거 종료월만 자동 단기고용 분석' => str_contains($eligibility, "\$month < date('Y-m')")
        && str_contains($eligibility, "'automatic_short_term' => \$automaticShortTerm"),
    '인접 확정 Workday가 있으면 자동판정하지 않음' => str_contains($eligibility, 'hasAdjacentConfirmedWorkday')
        && str_contains($eligibility, "!\$hasAdjacent"),
    '프로젝트 계약일과 입찰공고일 분석' => str_contains($eligibility, 'contract_date,bid_notice_date')
        && str_contains($eligibility, 'PROJECT_TRANSITION_SOURCE_REQUIRED'),
    '관리번호 완전일치 합산' => str_contains($eligibility, "!== \$managementNumber")
        && str_contains($eligibility, 'CONSTRUCTION_MANAGEMENT_NUMBER'),
    'Snapshot은 Workday와 지급 Line 근거 보존' => str_contains($result, "'source_workday_ids'")
        && str_contains($result, "'source_payment_line_ids'"),
    '통합 사실관리 UI 제거' => !str_contains($view, '가입자격 사실관리'),
    '고용기간 직접입력 API 제거' => !str_contains($routes, 'employment-period/'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}

echo 'PASS Workday 기반 가입자격 분석 계약: ' . count($checks) . PHP_EOL;
