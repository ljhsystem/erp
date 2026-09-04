<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$page = file_get_contents($root . '/public/assets/js/pages/main/settings/organization/approval-template.js');
$settings = file_get_contents($root . '/public/assets/js/common/datatable/dataTableSettings.js');

if ($page === false || $settings === false) {
    fwrite(STDERR, "검사 대상 파일을 읽지 못했습니다.\n");
    exit(1);
}

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(str_contains($page, 'template-list.v2'), '결재템플릿 목록 설정 schema 버전이 갱신되지 않았습니다.');
$expect(str_contains($page, "settingsKey: '__actions'"), '실제 관리 열이 공용 __actions에 연결되지 않았습니다.');
$expect(str_contains($page, "settingsVirtualType: 'system'"), '관리 열이 시스템 가상컬럼으로 선언되지 않았습니다.');
$expect(str_contains($page, "settingsTitle: '\\uAD00\\uB9AC'"), '관리 열 기본 사용컬럼명이 관리가 아닙니다.');
$expect(str_contains($page, 'ap-template-edit-btn'), '관리 열 본문의 수정 버튼이 없습니다.');
$expect(str_contains($page, "openTemplateModal('edit', row)"), '수정 버튼이 기존 수정 Modal에 연결되지 않았습니다.');
$expect(str_contains($page, 'step-list.v2'), '결재단계 구성 설정 schema 버전이 갱신되지 않았습니다.');
$expect(str_contains($page, 'ap-step-edit-btn'), '결재단계 관리 열 본문의 수정 버튼이 없습니다.');
$expect(str_contains($page, "openStepModal('edit', row)"), '결재단계 수정 버튼이 기존 수정 Modal에 연결되지 않았습니다.');
$expect(substr_count($page, "settingsKey: '__actions'") >= 2, '결재템플릿·결재단계 실제 관리 열이 모두 공용 __actions에 연결되지 않았습니다.');
$expect(str_contains($page, 'resetOnColumnSchemaChange: true'), '관리 열 schema 변경 초기화 Guard가 없습니다.');
$expect(str_contains($settings, 'displayName === defaultDisplayName && hasSystemHeaderControl'), '관리 열 헤더가 저장된 사용컬럼명을 우선하지 않습니다.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "결재템플릿 관리컬럼 TableSettings 계약 검증 통과\n");
