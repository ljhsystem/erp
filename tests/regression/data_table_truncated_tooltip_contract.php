<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$script = file_get_contents($root . '/public/assets/js/common/table/data-table.js');

if ($script === false) {
    fwrite(STDERR, "공용 DataTable 파일을 읽을 수 없습니다.\n");
    exit(1);
}

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$expect(str_contains($script, 'function disposeTruncatedTableTooltips(wrapper)'), '공용 줄임표 Tooltip 정리 함수가 없습니다.');
$expect(str_contains($script, "tbody.addEventListener('pointerdown'"), '본문 클릭 전 Tooltip 정리 계약이 없습니다.');
$expect(str_contains($script, "wrapper.addEventListener('scroll'"), '테이블 스크롤 Tooltip 정리 계약이 없습니다.');
$expect(str_contains($script, "table.on('preDraw.dt.dtTruncatedTooltip'"), 'DataTable 재그리기 전 Tooltip 정리 계약이 없습니다.');
$expect(substr_count($script, 'animation: false') >= 2, 'Header/Cell manual Tooltip transition 비활성 계약이 없습니다.');

echo "공용 DataTable 줄임표 Tooltip 정리 계약 검증 통과\n";
