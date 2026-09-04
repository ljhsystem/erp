<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$reasonProjection = (string) file_get_contents($root . '/app/Services/Institution/InsuranceEligibilityReasonProjectionService.php');
$modeProjection = (string) file_get_contents($root . '/app/Services/Institution/IncomeCalculationModeProjectionService.php');
$cards = (string) file_get_contents($root . '/public/assets/js/common/income-calculation-cards.js');
$badge = (string) file_get_contents($root . '/public/assets/js/common/insurance-eligibility-badge.js');
$dailyTax = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeCalculationService.php');
$regularTax = (string) file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeCalculationService.php');
$checks = [
    '성공 판정근거 Projection' => str_contains($reasonProjection, "'decision_basis_name'") && str_contains($reasonProjection, "'passed_conditions'"),
    '상태별 안내 제목' => str_contains($badge, "'판정근거'") && str_contains($badge, "'구성요소별 판정'")
        && str_contains($badge, "'확인 필요사항'") && str_contains($badge, "'오류내용'"),
    '상태별 fallback' => str_contains($badge, '적용 판정근거 확인 필요') && str_contains($badge, '확인 필요자료를 확인할 수 없습니다.'),
    '자동계산 공용 Projection' => str_contains($modeProjection, "'display_type_code' => 'CALCULATION_MODE'") && str_contains($badge, 'bindCalculationModeBadge'),
    '세금 자동계산 Badge 렌더링' => str_contains($cards, 'line.calculationMode') && str_contains($cards, 'bindCalculationModeBadge'),
    '일용 세금 서버 Projection' => substr_count($dailyTax, 'IncomeCalculationModeProjectionService::automatic') === 2,
    '상용 세금 서버 Projection' => substr_count($regularTax, 'IncomeCalculationModeProjectionService::automatic') === 2,
];
foreach ($checks as $label => $passed) { if (!$passed) throw new RuntimeException('FAIL: ' . $label); echo 'PASS: ', $label, PHP_EOL; }
