<?php

$root = dirname(__DIR__, 2);
$adapter = file_get_contents($root . '/public/assets/js/common/datatable/dataTableFormSettings.js');
$agents = file_get_contents($root . '/AGENTS.md');

if ($adapter === false || $agents === false) {
    fwrite(STDERR, "검사 대상 파일을 읽지 못했습니다.\n");
    exit(1);
}

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(!str_contains($adapter, 'state?.visibleColumns'), '모달 Adapter가 DataTable 보기 설정을 읽고 있습니다.');
$expect(!str_contains($adapter, 'container.hidden ='), '모달 Adapter가 TableSettings로 필드 노출을 변경하고 있습니다.');
$expect(str_contains($adapter, 'resolveDataTableColumnDisplayName'), '모달 표시명 공용 계약이 누락되었습니다.');
$expect(str_contains($adapter, 'resolveDataTableColumnRequirementPolicy'), '모달 필수구분 공용 계약이 누락되었습니다.');
$expect(str_contains($agents, '보기 설정(`visibleColumns`)은 DataTable 목록에만 적용'), 'AGENTS.md에 Modal 보기 분리 규칙이 없습니다.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "TableSettings Modal 보기 분리 계약 검증 통과\n");
