<?php
declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/public/assets/js/pages/institution/employment-contract/modal-runtime.js';
$source = file_get_contents($path);
if ($source === false) {
    throw new RuntimeException('근로계약 Modal Runtime을 읽을 수 없습니다.');
}

$checks = [
    "const activeRows = componentGrid.getState().rows.filter(row => row.rowState !== 'deleted');" => '삭제 후 활성 지급조건 확인이 없습니다.',
    'if (activeRows.length === 0) componentRow();' => '마지막 지급조건 삭제 후 빈 입력행 복원이 없습니다.',
    'if (!grid || !isContractFormEditable()) return;' => '지급조건 추가의 편집상태 보호가 없습니다.',
    'grid.addRow(componentGridRow(row, \'created\'));' => '지급조건 추가 명령이 없습니다.',
];
foreach ($checks as $needle => $message) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException($message);
    }
}

echo "employment contract component re-add contract: OK\n";
