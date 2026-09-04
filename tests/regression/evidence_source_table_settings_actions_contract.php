<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$table = file_get_contents($root . '/public/assets/js/pages/ledger/evidence-list/table.js');
$app = file_get_contents($root . '/public/assets/js/pages/ledger/evidence-page-app.js');
$reorder = file_get_contents($root . '/public/assets/js/pages/ledger/evidence-list/reorder.js');
$generic = file_get_contents($root . '/public/assets/js/pages/ledger/evidence-list.js');
$bank = file_get_contents($root . '/public/assets/js/pages/ledger/evidence-bank-transaction/index.js');
$tax = file_get_contents($root . '/public/assets/js/pages/ledger/evidence-tax-invoice/index.js');

if (in_array(false, [$table, $app, $reorder, $generic, $bank, $tax], true)) {
    fwrite(STDERR, "검사 대상 파일을 읽지 못했습니다.\n");
    exit(1);
}

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(!str_contains($table, "key: '__manage'"), '증빙원본에 페이지 전용 관리 key가 남아 있습니다.');
$expect(str_contains($table, "key: '__actions'"), '증빙원본 실제 관리 열이 공용 __actions에 연결되지 않았습니다.');
$expect(str_contains($table, "settingsKey: '__actions'"), '증빙원본 관리 열의 TableSettings key가 없습니다.');
$expect(str_contains($table, "settingsVirtualType: 'system'"), '증빙원본 관리 열이 시스템 가상컬럼으로 선언되지 않았습니다.');
$expect(str_contains($table, "settingsTitle: '관리'"), '증빙원본 관리 열 기본 사용컬럼명이 관리가 아닙니다.');
$expect(str_contains($table, 'evidence-edit-row-btn'), '증빙원본 관리 열 본문의 수정 버튼이 없습니다.');
$expect(str_contains($table, 'resetOnColumnSchemaChange: true'), '증빙원본 관리 열 schema 변경 초기화 Guard가 없습니다.');
$expect(str_contains($app, 'evidence-status.db-physical.v4'), '증빙원본 공용 설정 schema 버전이 갱신되지 않았습니다.');
$expect(str_contains($reorder, 'evidence-status.db-physical.v4'), '증빙원본 순서변경 모듈의 설정 key가 일치하지 않습니다.');

foreach ([$generic, $bank, $tax] as $entry) {
    $expect(str_contains($entry, 'evidence-page-app.js'), '증빙원본 페이지가 공용 관리 열 모듈을 사용하지 않습니다.');
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "증빙원본 전체 관리컬럼 TableSettings 계약 검증 통과\n");
