<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$sources = [
    'utility' => file_get_contents($root . '/app/Controllers/Ledger/Concerns/ImportControllerUtilityTrait.php'),
    'system-field' => file_get_contents($root . '/app/Services/Ledger/SystemFieldService.php'),
    'column-meta' => file_get_contents($root . '/app/Services/System/DataTableColumnMetaService.php'),
    'modal' => file_get_contents($root . '/public/assets/js/pages/ledger/evidence-list/modal.js'),
    'table' => file_get_contents($root . '/public/assets/js/pages/ledger/evidence-list/table.js'),
    'page-app' => file_get_contents($root . '/public/assets/js/pages/ledger/evidence-page-app.js'),
];

foreach ($sources as $name => $source) {
    if (!is_string($source) || $source === '') {
        throw new RuntimeException("{$name} 소스를 읽을 수 없습니다.");
    }
}

if (!str_contains($sources['utility'], "'operation_type' => '업무유형'")) {
    throw new RuntimeException('증빙 공용 필드 라벨이 업무유형이 아닙니다.');
}
if (!str_contains($sources['system-field'], "'code_group' => 'OPERATION_TYPE'")) {
    throw new RuntimeException('업무유형 시스템필드가 OPERATION_TYPE 코드그룹을 사용하지 않습니다.');
}
if (!str_contains($sources['column-meta'], "['operation_type', '업무유형'")) {
    throw new RuntimeException('증빙 테이블 메타데이터의 업무유형 계약이 없습니다.');
}
if (!str_contains($sources['modal'], "{ key: 'operation_type', label: '\\uC5C5\\uBB34\\uC720\\uD615'")) {
    throw new RuntimeException('증빙 모달의 업무유형 기본 라벨이 올바르지 않습니다.');
}
if (!str_contains($sources['page-app'], "codeGroup: 'OPERATION_TYPE'")) {
    throw new RuntimeException('증빙 모달 코드 선택기가 OPERATION_TYPE 코드 API를 사용하지 않습니다.');
}
if (!str_contains($sources['table'], "operation_type: '업무유형'")) {
    throw new RuntimeException('저장된 과거 라벨을 업무유형으로 정규화하는 목록 계약이 없습니다.');
}

echo "evidence operation type label contract: OK\n";
