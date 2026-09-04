<?php

global $router;

$routes = [
    ['POST', 'list', 'apiList', ['view'], false],
    ['GET', 'detail', 'apiDetail', ['view'], false],
    ['GET', 'options', 'apiOptions', ['view'], false],
    ['POST', 'calculate', 'apiCalculate', ['save'], false],
    ['GET', 'preflight', 'apiPreflight', ['view'], false],
    ['POST', 'save', 'apiSave', ['save'], true],
    ['POST', 'submit', 'apiSubmit', ['save'], true],
    ['POST', 'withdraw', 'apiWithdraw', ['save'], true],
    ['POST', 'delete', 'apiDelete', ['delete'], true],
    ['GET', 'trash', 'apiTrashList', ['view'], false],
    ['POST', 'restore', 'apiRestore', ['save'], true],
    ['POST', 'restore-bulk', 'apiRestoreBulk', ['save'], true],
    ['POST', 'restore-all', 'apiRestoreAll', ['save'], true],
    ['POST', 'purge', 'apiPurge', ['delete'], true],
    ['POST', 'purge-bulk', 'apiPurgeBulk', ['delete'], true],
    ['POST', 'purge-all', 'apiPurgeAll', ['delete'], true],
    ['GET', 'template', 'apiTemplate', ['view'], false],
    ['POST', 'excel', 'apiExcel', ['view'], false],
    ['POST', 'excel-upload-preview', 'apiExcelUploadPreview', ['save'], false],
];
foreach ($routes as [$method, $suffix, $action, $permissions, $log]) {
    $presentation = \Core\Helpers\PermissionPresentationHelper::decorate([
        'permission_key' => 'api.institution.income_data.daily_employment.' . str_replace(['-','/'], '_', $suffix),
        'permission_name' => $suffix,
        'description' => '일용근로소득 ' . $suffix,
    ], '일용근로소득');
    $router->{strtolower($method)}('/api/institution/income-data/daily-employment/' . $suffix, 'DailyEmploymentIncomeController@' . $action, [
        'key' => 'api.institution.income_data.daily_employment.' . str_replace(['-','/'], '_', $suffix),
        'page_key' => 'web.institution.income_data.daily_employment',
        'page' => '일용근로소득',
        'page_description' => '일용근로소득 작성·계산·상신 사전검증',
        'permission_name' => $presentation['permission_name'],
        'permission_description' => $presentation['description'],
        'name' => '일용근로소득 ' . $presentation['permission_name'],
        'description' => '대외기관업무 > 소득자료관리 > 일용근로소득 > ' . $presentation['permission_name'],
        'category' => '대외기관업무 > 소득자료관리',
        'auth' => true,
        'permissions' => $permissions,
        'log' => $log,
    ]);
}
