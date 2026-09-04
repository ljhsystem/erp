<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\System\StatutoryStandardPeriodStatusProjection;

$sql = StatutoryStandardPeriodStatusProjection::sql('s', "DATE '2026-08-31'");
$assertions = [
    '시작일 이전 예정' => str_contains($sql, "s.effective_from>DATE '2026-08-31' THEN 'SCHEDULED'"),
    '시작일 당일 포함' => str_contains($sql, "s.effective_from<=DATE '2026-08-31'"),
    '종료일 당일 포함' => str_contains($sql, "s.effective_to>=DATE '2026-08-31'"),
    '종료일 NULL 적용중' => str_contains($sql, 's.effective_to IS NULL'),
    '그 외 종료' => str_contains($sql, "ELSE 'ENDED' END"),
    '검색 별칭 호환' => StatutoryStandardPeriodStatusProjection::normalizeFilter('ACTIVE') === 'CURRENT'
        && StatutoryStandardPeriodStatusProjection::normalizeFilter('EXPIRED') === 'ENDED',
    '코드 미등록 한글 표시' => array_column(StatutoryStandardPeriodStatusProjection::displayOptions(), 'label', 'value') === [
        'SCHEDULED'=>'적용 예정', 'CURRENT'=>'현재 적용', 'ENDED'=>'적용 종료',
    ],
    '코드관리 표시명 우선' => (StatutoryStandardPeriodStatusProjection::displayOptions([
        ['value'=>'CURRENT','label'=>'현재 적용(코드관리)','extra_data'=>null],
    ])[1]['label'] ?? '') === '현재 적용(코드관리)',
];
foreach ($assertions as $label => $passed) {
    if (!$passed) throw new RuntimeException($label . ' 검증에 실패했습니다.');
}
echo json_encode(['success' => true, 'assertions' => array_keys($assertions)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
