<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tableScript = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/table.js');
$editorScript = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/editors.js');

if ($tableScript === false || $editorScript === false) {
    fwrite(STDERR, "거래입력 스크립트를 읽을 수 없습니다.\n");
    exit(1);
}

$assertions = [
    '목록의 적요 데이터는 기존 API 호환 별칭을 사용한다' => str_contains($tableScript, "data: 'description'"),
    '목록의 적요 설정키는 실제 컬럼을 사용한다' => str_contains($tableScript, "settingsKey: 'transaction_description'"),
    '적요는 가상컬럼으로 선언하지 않는다' => !preg_match("/data:\\s*'description'[\\s\\S]{0,160}__dtColumnKind:\\s*'virtual'/", $tableScript),
    '모달 적요 라벨도 실제 컬럼 설정을 사용한다' => str_contains(
        $editorScript,
        "{ field: 'description', selector: '#transaction_description', settingsKey: 'transaction_description' }"
    ),
    '목록과 모달은 동일한 TableSettings 저장소를 사용한다' => str_contains(
        $editorScript,
        "const TRANSACTION_TABLE_SETTINGS_KEY = 'datatable.settings.ledger.transaction.transaction-table.v2';"
    ),
    '거래처는 실제 client_id 설정 하나로 표시한다' => str_contains($tableScript, "data: 'client_id'")
        && str_contains($tableScript, "settingsKey: 'client_id'")
        && !str_contains($tableScript, "data: 'client_name'"),
    '프로젝트는 실제 project_id 설정 하나로 표시한다' => str_contains($tableScript, "data: 'project_id'")
        && str_contains($tableScript, "settingsKey: 'project_id'")
        && !str_contains($tableScript, "data: 'project_name'"),
];

foreach ($assertions as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: 거래입력 적요가 실제 컬럼 TableSettings 계약을 사용합니다.\n");
