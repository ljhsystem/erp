<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$calculation = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeCalculationService.php');
$service = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');

$checks = [
    '과세금액 0원 하한' => str_contains($calculation, '$taxable = max(0.0, $grossAmount - $nonTaxableAmount);'),
    '일용근로소득공제는 과세금액을 초과하지 않음' => str_contains($calculation, '$deductionAmount = min($taxable, max(0.0,'),
    '과세표준 0원 하한' => str_contains($calculation, '$calculationBasis = max(0.0,'),
    '산출세액 0원 하한' => str_contains($calculation, '$beforeCredit = max(0.0,'),
    '세액공제 후 세액 0원 하한' => str_contains($calculation, '$afterCredit = max(0.0,'),
    '결정 소득세 0원 하한' => str_contains($calculation, '$incomeTax = max(0.0,'),
    '지방소득세 0원 하한' => str_contains($calculation, '$localIncomeTax = max(0.0,'),
    '법정 세율과 공제율 범위 검증' => str_contains($calculation, "daily_income_tax_credit_rate'] > 1"),
    '공제합계 0원 하한' => str_contains($service, '$deduction = max(0.0,'),
    '실지급액이 총지급액을 넘지 않음' => str_contains($service, 'if ($net > $gross || $net < 0)'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo "PASS: 일용근로소득 세액·공제 비음수 불변조건\n";
