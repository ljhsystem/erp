<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');

$checks = [
    'Preview에서는 Workday 조정사유 검증을 유예한다' => str_contains(
        $service,
        "if (\$changed && \$reason === '' && \$requireDecisionReason)"
    ),
    '보험 적용금액 조정사유는 저장 경로에서만 필수다' => str_contains(
        $service,
        "\$reason = \$requireActualAmount\n                    ? \$this->lineContract->adjustmentReason"
    ),
    'Preview는 기본적으로 조정사유를 요구하지 않는다' => str_contains(
        $service,
        'public function calculate(array $input, bool $requireDecisionReason = false): array'
    ),
    '저장은 동일 계산 경로에 엄격 검증을 요청한다' => str_contains($service, '$this->calculate($input, true)'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ', $label, PHP_EOL;
}
