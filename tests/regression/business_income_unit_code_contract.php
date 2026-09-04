<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$page = (string) file_get_contents($root . '/public/assets/js/pages/institution/business-income/index.js');
$service = (string) file_get_contents($root . '/app/Services/Institution/BusinessIncomeService.php');
$model = (string) file_get_contents($root . '/app/Models/Institution/BusinessIncomeModel.php');

$checks = [
    'UNIT 코드관리 선택지 사용' => str_contains($page, "getCodeOptions('UNIT')"),
    '공용 HTML Grid Select Editor 사용' => str_contains($page, "key:'item_unit_name',label:'단위',editor:'select'"),
    '공용 Select2 Plugin 사용' => str_contains($page, "plugins:['select2']")
        && str_contains($page, 'PickerSelect2.create(editorElement'),
    '활성 UNIT 서버 조회' => str_contains($model, "code_group='UNIT' AND is_active=1"),
    '서버 표준 단위명 정규화' => str_contains($service, '$this->model->activeUnitNames()')
        && str_contains($service, '단위는 코드관리의 활성 단위에서 선택해야 합니다.'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
