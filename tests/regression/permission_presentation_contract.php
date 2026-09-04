<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Core\Helpers\PermissionPresentationHelper;

$cases = [
    ['api.example.page.list', 'list', '업무 list', '목록 조회', '조회'],
    ['api.example.page.excel_upload_preview', 'excel-upload-preview', '업무 excel-upload-preview', '엑셀 업로드 사전검증', '엑셀'],
    ['api.auth.account_lock.lock', '잠금해제', '계정잠금 해제', '계정 잠금', '계정관리'],
];

foreach ($cases as [$key, $name, $description, $expectedName, $expectedGroup]) {
    $result = PermissionPresentationHelper::decorate([
        'permission_key' => $key,
        'permission_name' => $name,
        'description' => $description,
    ], '테스트 페이지');

    if ($result['permission_name'] !== $expectedName || $result['capability_group'] !== $expectedGroup) {
        fwrite(STDERR, "권한 표시 계약 불일치: {$key}\n");
        exit(1);
    }
}

echo "Permission presentation contract PASS\n";
