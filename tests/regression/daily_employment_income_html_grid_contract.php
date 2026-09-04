<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = (string) file_get_contents($root . '/app/views/institution/daily-employment-income/index.php');
$page = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$cards = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');

$checks = [
    '일용 전용 AG Grid 자산이 없다' => !str_contains($view, 'ag-grid-community') && !is_file($root . '/public/assets/js/pages/institution/daily-employment-income/group-grid.js'),
    '날짜별 지급내역은 공용 HTML Grid를 사용한다' => str_contains($cards, "createHtmlGrid } from '/public/assets/js/common/html-grid/index.js'"),
    '작업자별 카드 생명주기를 관리한다' => str_contains($cards, 'DailyIncomeWorkerCardRegistry') && str_contains($cards, 'destroyAll()'),
    '실제 근무일 선택으로 Workday를 생성 삭제한다' => str_contains($cards, 'item.workdays.delete(date)') && str_contains($cards, 'item.workdays.set(date'),
    '공용 숫자 Formatter를 사용한다' => preg_match("/formatter:\\s*'number'/", $cards) === 1,
    '월 전체 날짜영역과 선택 Workday Grid를 분리한다' => str_contains($cards, 'daily-income-workday-calendar-scroll') && str_contains($cards, 'daily-income-workday-grid'),
    '근무일수는 Workday Map 크기로 표시한다' => str_contains($cards, '근무일수 ${item.workdays.size}일'),
    '입력된 Workday 해제 전 확인한다' => str_contains($cards, 'hasWorkdayInput(day)') && str_contains($cards, 'confirmRemoveWorkday'),
    '평년 윤년 30일 31일은 귀속월 말일로 Projection한다' => str_contains($page, 'new Date(year, monthNumber, 0).getDate()'),
    '귀속연월 변경 시 기존 Workday 영향확인을 한다' => str_contains($page, '귀속연월 변경 영향 확인') && str_contains($page, 'affected.length'),
    '서버는 실제 존재하는 귀속연월 날짜를 검증한다' => str_contains((string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php'), '!$this->isDate($workDate)') && str_contains((string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php'), 'substr($workDate, 0, 7) !== $month'),
    '우측 선택 작업자 계산결과를 표시한다' => str_contains($view, 'dailyIncomeWorkerResult') && str_contains($page, 'renderWorkerResult'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ', $label, PHP_EOL;
}
