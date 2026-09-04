<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$index = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$cards = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');
$service = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');

$checks = [
    '응답 Group을 client_key로 찾음' => str_contains($index, 'group.client_key === resultGroup.client_key'),
    '응답 Item을 client_key로 찾음' => str_contains($index, 'item.client_key === result.client_key'),
    '카드별 요청 버전 검증' => str_contains($index, 'worker.calculation_request_version !== snapshot.cardVersion'),
    '카드별 source 검증' => str_contains($index, 'workerCalculationSourceKey(liveGroup, worker) !== snapshot.sourceKey'),
    '응답 source 상관관계 검증' => str_contains($index, 'result.calculation_source_key !== snapshot.sourceKey'),
    '중복 카드 경고' => str_contains($cards, '동일 Group 작업자 중복 · 저장 불가'),
    '저장 중복 차단' => str_contains($service, '$this->assertNoDuplicateGroupWorkers($input);'),
    'Preview 중복 허용' => !str_contains($service, 'isset($seen[$itemKey])'),
    '계산 source hash 응답' => str_contains($service, "'calculation_source_hash' => hash('sha256', \$calculationSourceKey)"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

echo "daily employment income worker instance contract: ok\n";
