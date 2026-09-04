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
    $router->{strtolower($method)}('/api/institution/income-data/daily-employment/' . $suffix, 'DailyEmploymentIncomeController@' . $action, [
        'key' => 'api.institution.income_data.daily_employment.' . str_replace(['-','/'], '_', $suffix),
        'page_key' => 'web.institution.income_data.daily_employment',
        'page' => '일용근로소득',
        'page_description' => '일용근로소득 작성·계산·상신 사전검증',
        'permission_name' => $suffix,
        'permission_description' => '일용근로소득 ' . $suffix,
        'name' => '일용근로소득 ' . $suffix,
        'description' => '대외기관업무 > 소득자료관리 > 일용근로소득 > ' . $suffix,
        'category' => '대외기관업무',
        'auth' => true,
        'permissions' => $permissions,
        'log' => $log,
    ]);
}
