<?php
// 寃쎈줈: PROJECT_ROOT . '/routes/api.php';
error_log('[ROUTES] api.php LOADED');

global $router;

/* =========================================
 * ?몄쬆 / 怨꾩젙?좉툑 愿??API (蹂댁븞/?묎렐 ?쒖뼱)
 * -----------------------------------------
 * 濡쒓렇?? ?뚯썝媛?? 2李??몄쬆 ???몄쬆 愿??API?
 * 怨꾩젙 ?좉툑/?댁젣 ?깆쓽 蹂댁븞 湲곕뒫??泥섎━
 * ========================================= */
$router->get('/api/account/lock/status', 'AccountLockController@apiStatus', [
    'key' => 'api.auth.account_lock.status',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/account/lock/set', 'AccountLockController@apiLock', [
    'key' => 'api.auth.account_lock.lock',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/account/lock/unlock', 'AccountLockController@apiUnlock', [
    'key' => 'api.auth.account_lock.unlock',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/auth/register', 'RegisterController@apiRegister', [
    'key' => 'api.auth.register',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/api/2fa/verify', 'TwoFactorController@apiVerify', [
    'key' => 'api.auth.2fa.verify',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
    'allow_statuses' => ['2FA_PENDING'],
]);

$router->post('/api/auth/login', 'LoginController@apiLogin', [
    'key' => 'api.auth.login',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/api/auth/password/change', 'PasswordController@apiChangePassword', [
    'key' => 'api.auth.password.change',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => ['save'],
    'log' => false,
    'allow_statuses' => ['NORMAL', 'PASSWORD_EXPIRED'],
]);

$router->post('/api/auth/password/change-later', 'PasswordController@apiChangeLater', [
    'key' => 'api.auth.password.change_later',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => ['save'],
    'log' => false,
    'allow_statuses' => ['PASSWORD_EXPIRED'],
]);

$router->post('/api/contact/send', 'ContactController@apiSend', [
    'key' => 'api.contact.send',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);
// ?뚯썝媛???뱀씤 泥섎━ API
$router->post('/api/auth/approval/approve', 'UserApprovalController@apiApprove', [
    'key'             => 'api.auth.user.approve',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'            => false,
    'skip_permission' => true,
    'log'             => true,
]);

$router->post('/api/integration/biz-status', 'ExternalIntegrationController@apiBizStatus', [
    'key' => 'api.integration.biz_status',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/company/detail', 'CompanyController@apiDetail', [
    'key'         => 'api.settings.base-info.company.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/company/save', 'CompanyController@apiSave', [
    'key'         => 'api.settings.base-info.company.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/list', 'BrandController@apiList', [
    'key'         => 'api.settings.base-info.brand.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/detail', 'BrandController@apiDetail', [
    'key'         => 'api.settings.base-info.brand.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/active-type', 'BrandController@apiActiveType', [
    'key'         => 'api.settings.base-info.brand.active-type',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/save', 'BrandController@apiSave', [
    'key'         => 'api.settings.base-info.brand.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/purge', 'BrandController@apiPurge', [
    'key'         => 'api.settings.base-info.brand.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/updatestatus', 'BrandController@apiUpdateStatus', [
    'key'         => 'api.settings.base-info.brand.status',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['update'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/list', 'CodeController@apiList', [
    'key'         => 'code.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/detail', 'CodeController@apiDetail', [
    'key'         => 'code.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/groups', 'CodeController@apiGroups', [
    'key'         => 'code.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/system/code/save', 'CodeController@apiSave', [
    'key'         => 'code.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/delete', 'CodeController@apiDelete', [
    'key'         => 'code.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/trash', 'CodeController@apiTrashList', [
    'key'         => 'code.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/restore', 'CodeController@apiRestore', [
    'key'         => 'code.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/restore-bulk', 'CodeController@apiRestoreBulk', [
    'key'         => 'code.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/restore-all', 'CodeController@apiRestoreAll', [
    'key'         => 'code.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/purge', 'CodeController@apiPurge', [
    'key'         => 'code.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/purge-bulk', 'CodeController@apiPurgeBulk', [
    'key'         => 'code.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/purge-all', 'CodeController@apiPurgeAll', [
    'key'         => 'code.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/reorder', 'CodeController@apiReorder', [
    'key'         => 'code.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/template', 'CodeController@apiDownloadTemplate', [
    'key'         => 'code.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/system/code/excel', 'CodeController@apiDownloadExcel', [
    'key'         => 'code.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/excel-upload', 'CodeController@apiExcelUpload', [
    'key'         => 'code.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$codeBaseInfoCompatRoutes = [
    ['get', '/api/settings/base-info/code/list', 'CodeController@apiList', 'code.view', ['view'], true],
    ['get', '/api/settings/base-info/code/detail', 'CodeController@apiDetail', 'code.view', ['view'], true],
    ['get', '/api/settings/base-info/code/groups', 'CodeController@apiGroups', 'code.view', ['view'], false],
    ['post', '/api/settings/base-info/code/save', 'CodeController@apiSave', 'code.save', ['save'], true],
    ['post', '/api/settings/base-info/code/delete', 'CodeController@apiDelete', 'code.delete', ['delete'], true],
    ['get', '/api/settings/base-info/code/trash', 'CodeController@apiTrashList', 'code.view', ['view'], true],
    ['post', '/api/settings/base-info/code/restore', 'CodeController@apiRestore', 'code.save', ['save'], true],
    ['post', '/api/settings/base-info/code/restore-bulk', 'CodeController@apiRestoreBulk', 'code.save', ['save'], true],
    ['post', '/api/settings/base-info/code/restore-all', 'CodeController@apiRestoreAll', 'code.save', ['save'], true],
    ['post', '/api/settings/base-info/code/purge', 'CodeController@apiPurge', 'code.delete', ['delete'], true],
    ['post', '/api/settings/base-info/code/purge-bulk', 'CodeController@apiPurgeBulk', 'code.delete', ['delete'], true],
    ['post', '/api/settings/base-info/code/purge-all', 'CodeController@apiPurgeAll', 'code.delete', ['delete'], true],
    ['post', '/api/settings/base-info/code/reorder', 'CodeController@apiReorder', 'code.save', ['save'], true],
    ['get', '/api/settings/base-info/code/template', 'CodeController@apiDownloadTemplate', 'code.view', ['view'], false],
    ['get', '/api/settings/base-info/code/excel', 'CodeController@apiDownloadExcel', 'code.view', ['view'], true],
    ['post', '/api/settings/base-info/code/excel-upload', 'CodeController@apiExcelUpload', 'code.save', ['save'], true],
];

foreach ($codeBaseInfoCompatRoutes as [$method, $path, $action, $key, $permissions, $log]) {
    $router->{$method}($path, $action, [
        'key' => $key,
        'name' => 'route',
        'description' => 'route',
        'category' => 'system',
        'auth' => true,
        'permissions' => $permissions,
        'log' => $log,
    ]);
}

$router->get('/api/settings/base-info/cover/list', 'CoverController@apiList', [
    'key'         => 'api.settings.base-info.cover.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/cover/public', 'CoverController@apiPublicList', [
    'key'         => 'api.settings.base-info.cover.public',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

$router->get('/api/settings/base-info/cover/detail', 'CoverController@apiDetail', [
    'key'         => 'api.settings.base-info.cover.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/save', 'CoverController@apiSave', [
    'key'         => 'api.settings.base-info.cover.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/delete', 'CoverController@apiDelete', [
    'key'         => 'api.settings.base-info.cover.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/cover/trash', 'CoverController@apiTrashList', [
    'key'         => 'api.settings.base-info.cover.trash.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/restore', 'CoverController@apiRestore', [
    'key'         => 'api.settings.base-info.cover.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/restore-bulk', 'CoverController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.cover.restore.bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/restore-all', 'CoverController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.cover.restore.all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/purge', 'CoverController@apiPurge', [
    'key'         => 'api.settings.base-info.cover.purge',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/purge-bulk', 'CoverController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.cover.purge.bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/purge-all', 'CoverController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.cover.purge.all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/reorder', 'CoverController@apiReorder', [
    'key'         => 'api.settings.base-info.cover.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/list', 'ClientController@apiList', [
    'key'         => 'api.settings.base-info.client.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/detail', 'ClientController@apiDetail', [
    'key'         => 'api.settings.base-info.client.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/search-picker', 'ClientController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.client.search-picker',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/client/save', 'ClientController@apiSave', [
    'key'         => 'api.settings.base-info.client.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/delete', 'ClientController@apiDelete', [
    'key'         => 'api.settings.base-info.client.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/trash', 'ClientController@apiTrashList', [
    'key'         => 'api.settings.base-info.client.trash.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/restore', 'ClientController@apiRestore', [
    'key'         => 'api.settings.base-info.client.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/restore-bulk', 'ClientController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.client.restore-bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/restore-all', 'ClientController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.client.restore-all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/purge', 'ClientController@apiPurge', [
    'key'         => 'api.settings.base-info.client.purge',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/purge-bulk', 'ClientController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.client.purge-bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/purge-all', 'ClientController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.client.purge-all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/reorder', 'ClientController@apiReorder', [
    'key'         => 'api.settings.base-info.client.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/template', 'ClientController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.client.template',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/client/excel-upload', 'ClientController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.client.excel-upload',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/download', 'ClientController@apiDownload', [
    'key'         => 'api.settings.base-info.client.excel',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/list', 'ProjectController@apiList', [
    'key'         => 'api.settings.base-info.project.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/detail', 'ProjectController@apiDetail', [
    'key'         => 'api.settings.base-info.project.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/search-picker', 'ProjectController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.project.search-picker',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/project/save', 'ProjectController@apiSave', [
    'key'         => 'api.settings.base-info.project.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/delete', 'ProjectController@apiDelete', [
    'key'         => 'api.settings.base-info.project.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/trash', 'ProjectController@apiTrashList', [
    'key'         => 'api.settings.base-info.project.trash.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/restore', 'ProjectController@apiRestore', [
    'key'         => 'api.settings.base-info.project.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/restore-bulk', 'ProjectController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.project.restore-bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/restore-all', 'ProjectController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.project.restore-all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/purge', 'ProjectController@apiPurge', [
    'key'         => 'api.settings.base-info.project.purge',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/purge-bulk', 'ProjectController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.project.purge-bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/purge-all', 'ProjectController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.project.purge-all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/reorder', 'ProjectController@apiReorder', [
    'key'         => 'api.settings.base-info.project.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/template', 'ProjectController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.project.template',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/project/excel-upload', 'ProjectController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.project.excel-upload',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/download', 'ProjectController@apiDownload', [
    'key'         => 'api.settings.base-info.project.excel',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/list', 'BankAccountController@apiList', [
    'key'         => 'api.settings.base-info.bank-account.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/detail', 'BankAccountController@apiDetail', [
    'key'         => 'api.settings.base-info.bank-account.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/search-picker', 'BankAccountController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.bank-account.search-picker',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/bank-account/save', 'BankAccountController@apiSave', [
    'key'         => 'api.settings.base-info.bank-account.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/delete', 'BankAccountController@apiDelete', [
    'key'         => 'api.settings.base-info.bank-account.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/trash', 'BankAccountController@apiTrashList', [
    'key'         => 'api.settings.base-info.bank-account.trash.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/restore', 'BankAccountController@apiRestore', [
    'key'         => 'api.settings.base-info.bank-account.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/restore-bulk', 'BankAccountController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.bank-account.restore-bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/restore-all', 'BankAccountController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.bank-account.restore-all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/purge', 'BankAccountController@apiPurge', [
    'key'         => 'api.settings.base-info.bank-account.purge',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/purge-bulk', 'BankAccountController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.bank-account.purge-bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/purge-all', 'BankAccountController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.bank-account.purge-all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/reorder', 'BankAccountController@apiReorder', [
    'key'         => 'api.settings.base-info.bank-account.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/template', 'BankAccountController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.bank-account.template',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/bank-account/excel-upload', 'BankAccountController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.bank-account.excel-upload',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/download', 'BankAccountController@apiDownload', [
    'key'         => 'api.settings.base-info.bank-account.excel',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/list', 'CardController@apiList', [
    'key'         => 'api.settings.base-info.card.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/detail', 'CardController@apiDetail', [
    'key'         => 'api.settings.base-info.card.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/search-picker', 'CardController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.card.search-picker',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/card/save', 'CardController@apiSave', [
    'key'         => 'api.settings.base-info.card.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/delete', 'CardController@apiDelete', [
    'key'         => 'api.settings.base-info.card.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/trash', 'CardController@apiTrashList', [
    'key'         => 'api.settings.base-info.card.trash.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/restore', 'CardController@apiRestore', [
    'key'         => 'api.settings.base-info.card.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/restore-bulk', 'CardController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.card.restore-bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/restore-all', 'CardController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.card.restore-all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/purge', 'CardController@apiPurge', [
    'key'         => 'api.settings.base-info.card.purge',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/purge-bulk', 'CardController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.card.purge-bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/purge-all', 'CardController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.card.purge-all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/reorder', 'CardController@apiReorder', [
    'key'         => 'api.settings.base-info.card.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/template', 'CardController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.card.template',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/card/excel-upload', 'CardController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.card.excel-upload',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/download', 'CardController@apiDownload', [
    'key'         => 'api.settings.base-info.card.download',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/work-team/list', 'WorkTeamController@apiList', [
    'key'         => 'work_team.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/work-team/detail', 'WorkTeamController@apiDetail', [
    'key'         => 'work_team.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/save', 'WorkTeamController@apiSave', [
    'key'         => 'work_team.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/delete', 'WorkTeamController@apiDelete', [
    'key'         => 'work_team.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/work-team/trash', 'WorkTeamController@apiTrashList', [
    'key'         => 'work_team.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/restore', 'WorkTeamController@apiRestore', [
    'key'         => 'work_team.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/restore-bulk', 'WorkTeamController@apiRestoreBulk', [
    'key'         => 'work_team.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/restore-all', 'WorkTeamController@apiRestoreAll', [
    'key'         => 'work_team.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/purge', 'WorkTeamController@apiPurge', [
    'key'         => 'work_team.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/purge-bulk', 'WorkTeamController@apiPurgeBulk', [
    'key'         => 'work_team.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/purge-all', 'WorkTeamController@apiPurgeAll', [
    'key'         => 'work_team.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/reorder', 'WorkTeamController@apiReorder', [
    'key'         => 'work_team.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/work-team/template', 'WorkTeamController@apiDownloadTemplate', [
    'key'         => 'work_team.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/base-info/work-team/excel', 'WorkTeamController@apiDownloadExcel', [
    'key'         => 'work_team.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/excel-upload', 'WorkTeamController@apiExcelUpload', [
    'key'         => 'work_team.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/employee/list', 'EmployeeController@apiList', [
    'key'         => 'api.settings.employee.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/employee/detail', 'EmployeeController@apiDetail', [
    'key'         => 'api.settings.employee.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/employee/search-picker', 'EmployeeController@apiSearchPicker', [
    'key'         => 'api.settings.employee.search',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/employee/save', 'EmployeeController@apiSave', [
    'key'         => 'api.settings.employee.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/employee/update-status', 'EmployeeController@apiUpdateStatus', [
    'key'         => 'api.settings.employee.update-status',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/employee/delete', 'EmployeeController@apiDelete', [
    'key'         => 'api.settings.employee.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/organization/employee/reorder', 'EmployeeController@apiReorder', [
    'key'         => 'api.settings.employee.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/user/profile/detail', 'ProfileController@apiDetail', [
    'key'         => 'api.user.profile.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/user/profile/save', 'ProfileController@apiSave', [
    'key'         => 'api.user.profile.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/department/list', 'DepartmentController@apiList', [
    'key'         => 'api.settings.department.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/department/detail', 'DepartmentController@apiDetail', [
    'key'         => 'api.settings.department.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/department/save', 'DepartmentController@apiSave', [
    'key'         => 'api.settings.department.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/department/delete', 'DepartmentController@apiDelete', [
    'key'         => 'api.settings.department.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/organization/department/reorder', 'DepartmentController@apiReorder', [
    'key'         => 'api.settings.department.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/position/list', 'PositionController@apiList', [
    'key'         => 'api.settings.position.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/position/detail', 'PositionController@apiDetail', [
    'key'         => 'api.settings.position.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/position/save', 'PositionController@apiSave', [
    'key'         => 'api.settings.position.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/position/delete', 'PositionController@apiDelete', [
    'key'         => 'api.settings.position.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/organization/position/reorder', 'PositionController@apiReorder', [
    'key'         => 'api.settings.position.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/role/list', 'RoleController@apiList', [
    'key'         => 'api.settings.role.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/role/detail', 'RoleController@apiDetail', [
    'key'         => 'api.settings.role.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/role/save', 'RoleController@apiSave', [
    'key'         => 'api.settings.role.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/role/delete', 'RoleController@apiDelete', [
    'key'         => 'api.settings.role.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/organization/role/reorder', 'RoleController@apiReorder', [
    'key'         => 'api.settings.role.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/permission/list', 'PermissionController@apiList', [
    'key'         => 'api.settings.permission.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/role-permission/list', 'RolePermissionController@apiList', [
    'key'         => 'api.settings.rolepermission.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/role-permission/assign', 'RolePermissionController@apiAssign', [
    'key'         => 'api.settings.rolepermission.assign',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/role-permission/remove', 'RolePermissionController@apiRemove', [
    'key'         => 'api.settings.rolepermission.remove',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/organization/approval/template/list', 'ApprovalTemplateController@apiTemplateList', [
    'key'         => 'api.settings.approval.template.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/approval/template/list', 'ApprovalTemplateController@apiTemplateList', [
    'key'         => 'api.settings.approval.template.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/approval/template/save', 'ApprovalTemplateController@apiTemplateSave', [
    'key'         => 'api.settings.approval.template.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true
]);

$router->post('/api/settings/organization/approval/template/delete', 'ApprovalTemplateController@apiTemplateDelete', [
    'key'         => 'api.settings.approval.template.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true
]);

$router->post('/api/settings/organization/approval/template/reorder', 'ApprovalTemplateController@apiTemplateReorder', [
    'key'         => 'api.settings.approval.template.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true
]);

$router->get('/api/settings/organization/approval/step/list', 'ApprovalTemplateController@apiStepList', [
    'key'         => 'api.settings.approval.step.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false
]);

$router->post('/api/settings/organization/approval/step/list', 'ApprovalTemplateController@apiStepList', [
    'key'         => 'api.settings.approval.step.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false
]);

$router->post('/api/settings/organization/approval/step/save', 'ApprovalTemplateController@apiStepSave', [
    'key'         => 'api.settings.approval.step.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true
]);

$router->post('/api/settings/organization/approval/step/delete', 'ApprovalTemplateController@apiStepDelete', [
    'key'         => 'api.settings.approval.step.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true
]);

$router->get('/api/settings/system/site/get', 'SystemController@apiSiteGet', [
    'key'         => 'api.settings.system.site.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/site/save', 'SystemController@apiSiteSave', [
    'key'         => 'api.settings.system.site.edit',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/session/get', 'SystemController@apiSessionGet', [
    'key'         => 'api.settings.system.session.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/session/save', 'SystemController@apiSessionSave', [
    'key'         => 'api.settings.system.session.edit',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/security/get', 'SystemController@apiSecurityGet', [
    'key'         => 'api.settings.system.security.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/security/save', 'SystemController@apiSecuritySave', [
    'key'         => 'api.settings.system.security.edit',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/api/get', 'SystemController@apiApiGet', [
    'key'         => 'api.settings.system.api.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/api/save', 'SystemController@apiApiSave', [
    'key'         => 'api.settings.system.api.edit',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/external/ping', 'ExternalApiController@ping', [
    'key'         => 'api.external.ping',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
    'middleware'  => ['ApiAccessMiddleware'],
]);

$router->get('/api/external/employees/list', 'ExternalEmployeeController@list', [
    'key'         => 'api.external.employee.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
    'middleware'  => ['ApiAccessMiddleware'],
]);

$router->get('/api/external/employees', 'ExternalApiController@employees', [
    'key'         => 'api.external.employee.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
    'middleware'  => ['ApiAccessMiddleware'],
]);

$router->get('/api/settings/system/external-services/get', 'SystemController@apiExternalServicesGet', [
    'key'         => 'api.settings.system.external.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/external-services/save', 'SystemController@apiExternalServicesSave', [
    'key'         => 'api.settings.system.external.edit',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/user/external-accounts', 'ExternalAccountController@apiList', [
    'key'         => 'api.user.external_accounts.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/user/external-accounts/get', 'ExternalAccountController@apiGet', [
    'key'         => 'api.user.external_accounts.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/user/external-accounts/save', 'ExternalAccountController@apiSave', [
    'key'         => 'api.user.external_accounts.edit',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/user/external-accounts/delete', 'ExternalAccountController@apiDelete', [
    'key'         => 'api.user.external_accounts.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/file/preview', 'FileController@apiPreview', [
    'key'         => 'api.file.preview',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

$router->post('/api/file/upload-test', 'FileController@apiUploadTest', [
    'key'         => 'api.file.upload.test',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/import/template', 'ImportController@apiTemplate', [
    'key'         => 'api.import.template',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/import/fields', 'ImportController@apiFieldOptions', [
    'key' => 'api.import.fields',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/import/formats', 'ImportController@apiFormats', [
    'key' => 'api.import.formats',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/import/format', 'ImportController@apiFormatDetail', [
    'key' => 'api.import.format.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/import/format/save', 'ImportController@apiFormatSave', [
    'key' => 'api.import.format.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/format/delete', 'ImportController@apiFormatDelete', [
    'key' => 'api.import.format.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/import/format/copy', 'ImportController@apiFormatCopy', [
    'key' => 'api.import.format.copy',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/preview', 'ImportController@apiPreview', [
    'key' => 'api.import.preview',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/import/batches', 'ImportController@apiUploadBatches', [
    'key' => 'api.import.batches',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/import/batch/rows', 'ImportController@apiUploadBatchRows', [
    'key' => 'api.import.batch.rows',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/import/create-transactions', 'ImportController@apiCreateTransactions', [
    'key' => 'api.import.create_transactions',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/system/file-policies', 'FileController@apiPolicyList', [
    'key'         => 'api.settings.system.storage.policy.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/system/file-policies', 'FileController@apiPolicyCreate', [
    'key'         => 'api.settings.system.storage.policy.create',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/system/file-policies/update', 'FileController@apiPolicyUpdate', [
    'key'         => 'api.settings.system.storage.policy.edit',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/system/file-policies/delete', 'FileController@apiPolicyDelete', [
    'key'         => 'api.settings.system.storage.policy.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/system/file-policies/toggle', 'FileController@apiPolicyToggle', [
    'key'         => 'api.settings.system.storage.policy.toggle',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/system/storage/bucket-browse', 'FileController@apiBucketBrowse', [
    'key'         => 'api.settings.system.storage.browse',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/database/get', 'SystemController@apiDatabaseGet', [
    'key'         => 'api.settings.system.database.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/database/save', 'SystemController@apiDatabaseSave', [
    'key'         => 'api.settings.system.database.edit',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/database/run', 'SystemController@apiBackupRun', [
    'key'         => 'api.settings.system.database.run',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/system/notifications', 'NotificationController@apiList', [
    'key'         => 'api.system.notifications',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'skip_permission' => true,
    'log'         => false,
]);

$router->post('/api/system/notifications/read', 'NotificationController@apiRead', [
    'key'         => 'api.system.notifications.read',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'skip_permission' => true,
    'log'         => false,
]);

$router->post('/api/system/notifications/read-all', 'NotificationController@apiReadAll', [
    'key'         => 'api.system.notifications.read_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'skip_permission' => true,
    'log'         => false,
]);

$router->get('/api/settings/system/database/info', 'SystemController@apiBackupInfo', [
    'key'         => 'api.settings.system.database.info',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/database/log', 'SystemController@apiBackupLog', [
    'key'         => 'api.settings.system.database.log',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/database/replication-status', 'SystemController@apiDatabaseReplicationStatus', [
    'key'         => 'api.settings.system.database.replication',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/database/restore-secondary', 'SystemController@apiRestoreSecondary', [
    'key'         => 'api.settings.system.database.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/database/secondary-restore-info', 'SystemController@apiSecondaryRestoreInfo', [
    'key'         => 'api.settings.system.database.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/logs/view', 'SystemController@apiLogView', [
    'key'         => 'api.settings.system.logs.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/logs/delete', 'SystemController@apiLogDelete', [
    'key'         => 'api.settings.system.logs.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/system/logs/delete-all', 'SystemController@apiLogDeleteAll', [
    'key'         => 'api.settings.system.logs.delete_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/approval/request/create', 'ApprovalRequestController@apiCreate', [
    'key'         => 'api.approval.request.create',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/approval/request/detail', 'ApprovalRequestController@apiDetail', [
    'key'         => 'api.approval.request.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/approval/request/approve', 'ApprovalRequestController@apiApproveStep', [
    'key'         => 'api.approval.step.approve',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/approval/request/reject', 'ApprovalRequestController@apiRejectStep', [
    'key'         => 'api.approval.step.reject',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/approval/request/status', 'ApprovalRequestController@apiStatus', [
    'key'         => 'api.approval.request.status',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/approval/request/step/delete', 'ApprovalRequestController@apiDeleteStep', [
    'key'         => 'api.approval.step.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/dashboard/calendar/list', 'CalendarController@apiList', [
    'key'         => 'api.dashboard.calendar.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/events-all', 'CalendarController@apiEventsAll', [
    'key'         => 'api.dashboard.calendar.events_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/events', 'CalendarController@apiEvents', [
    'key'         => 'api.dashboard.calendar.events',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/dashboard/calendar/event/create', 'CalendarController@apiEventCreate', [
    'key'         => 'api.dashboard.calendar.event.create',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/event/update', 'CalendarController@apiEventUpdate', [
    'key'         => 'api.dashboard.calendar.event.update',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/event/delete', 'CalendarController@apiEventDelete', [
    'key'         => 'api.dashboard.calendar.event.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/dashboard/calendar/tasks', 'CalendarController@apiTasks', [
    'key'         => 'api.dashboard.calendar.tasks',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/tasks-all', 'CalendarController@apiTasksAll', [
    'key'         => 'api.dashboard.calendar.tasks_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/dashboard/calendar/task/create', 'CalendarController@apiTaskCreate', [
    'key'         => 'api.dashboard.calendar.task.create',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/toggle-complete', 'CalendarController@apiToggleTaskComplete', [
    'key'         => 'api.dashboard.calendar.task.toggle_complete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/update', 'CalendarController@apiTaskUpdate', [
    'key'         => 'api.dashboard.calendar.task.update',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/delete', 'CalendarController@apiTaskDelete', [
    'key'         => 'api.dashboard.calendar.task.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/collection/delete', 'CalendarController@apiCollectionDelete', [
    'key'         => 'api.dashboard.calendar.collection.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/event/hard-delete', 'CalendarController@apiEventHardDelete', [
    'key'         => 'api.dashboard.calendar.event.hard_delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/cache-rebuild', 'CalendarController@apiCacheRebuild', [
    'key'         => 'api.dashboard.calendar.cache_rebuild',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/hard-delete', 'CalendarController@apiTaskHardDelete', [
    'key'         => 'api.dashboard.calendar.task.hard_delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/update-admin-color', 'CalendarController@apiUpdateAdminColor', [
    'key'         => 'api.dashboard.calendar.update_admin_color',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/dashboard/calendar/tasks-panel', 'CalendarController@apiTasksPanel', [
    'key'         => 'api.dashboard.calendar.tasks_panel',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/events-deleted', 'CalendarController@apiEventsDeleted', [
    'key'         => 'api.dashboard.calendar.events_deleted',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/tasks-deleted', 'CalendarController@apiTasksDeleted', [
    'key'         => 'api.dashboard.calendar.tasks_deleted',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/dashboard/calendar/event/hard-delete-all', 'CalendarController@apiEventHardDeleteAll', [
    'key'         => 'api.dashboard.calendar.event.hard_delete_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/hard-delete-all', 'CalendarController@apiTaskHardDeleteAll', [
    'key'         => 'api.dashboard.calendar.task.hard_delete_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/event/restore', 'CalendarController@apiEventRestore', [
    'key'         => 'api.dashboard.calendar.event.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/restore', 'CalendarController@apiTaskRestore', [
    'key'         => 'api.dashboard.calendar.task.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/delete-bulk', 'CalendarController@apiTaskDeleteBulk', [
    'key'         => 'api.dashboard.calendar.task.delete_bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/dashboard/profile-summary', 'CalendarController@apiProfileSummary', [
    'key'         => 'api.dashboard.profile_summary',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/account/list', 'ChartAccountController@apiList', [
    'key'         => 'api.ledger.account.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/account/tree', 'ChartAccountController@apiTree', [
    'key'         => 'api.ledger.account.tree',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/account/detail', 'ChartAccountController@apiDetail', [
    'key'         => 'api.ledger.account.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/account/template', 'ChartAccountController@apiTemplate', [
    'key'         => 'api.ledger.account.template',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/account/trash', 'ChartAccountController@apiTrashList', [
    'key'         => 'api.ledger.account.trash',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/ledger/account/save', 'ChartAccountController@apiSave', [
    'key'         => 'api.ledger.account.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/soft-delete', 'ChartAccountController@apiSoftDelete', [
    'key'         => 'api.ledger.account.soft_delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/ledger/account/restore', 'ChartAccountController@apiRestore', [
    'key'         => 'api.ledger.account.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/hard-delete', 'ChartAccountController@apiHardDelete', [
    'key'         => 'api.ledger.account.hard_delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/ledger/account/template', 'ChartAccountController@apiTemplate', [
    'key'         => 'api.ledger.account.template',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 怨꾩젙怨쇰ぉ ?묒? ?ㅼ슫濡쒕뱶
// ============================================================
$router->get('/api/ledger/account/excel', 'ChartAccountController@apiDownloadAllExcel', [
    'key'         => 'api.ledger.account.excel',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/ledger/account/excel-upload', 'ChartAccountController@apiExcelUpload', [
    'key'         => 'api.ledger.account.excel_upload',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/reorder', 'ChartAccountController@apiReorder', [
    'key'         => 'api.ledger.account.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/restore-bulk', 'ChartAccountController@apiRestoreBulk', [
    'key'         => 'api.ledger.account.restore_bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/restore-all', 'ChartAccountController@apiRestoreAll', [
    'key'         => 'api.ledger.account.restore_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/hard-delete-bulk', 'ChartAccountController@apiHardDeleteBulk', [
    'key'         => 'api.ledger.account.hard_delete_bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/ledger/account/hard-delete-all', 'ChartAccountController@apiHardDeleteAll', [
    'key'         => 'api.ledger.account.hard_delete_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

// ============================================================
// 怨꾩젙怨쇰ぉ ?묒? ?ㅼ슫濡쒕뱶
// ============================================================
$router->get('/api/ledger/account/excel', 'ChartAccountController@apiDownloadAllExcel', [
    'key'         => 'api.ledger.account.excel',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/account/search', 'ChartAccountController@apiSearch', [
    'key'         => 'api.ledger.account.search',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/account/posting', 'ChartAccountController@apiPosting', [
    'key'         => 'api.ledger.account.posting',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/sub-account/list', 'SubChartAccountController@apiList', [
    'key'         => 'api.ledger.sub_account.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/account/sub-accounts', 'SubChartAccountController@apiList', [
    'key'         => 'api.account.sub_accounts.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/ledger/sub-account/save', 'SubChartAccountController@apiSave', [
    'key'         => 'api.ledger.sub_account.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/sub-account/update', 'SubChartAccountController@apiUpdate', [
    'key'         => 'api.ledger.sub_account.update',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/sub-account/delete', 'SubChartAccountController@apiDelete', [
    'key'         => 'api.ledger.sub_account.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/ledger/voucher/list', 'VoucherController@apiList', [
    'key'         => 'api.ledger.voucher.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/voucher/detail', 'VoucherController@apiDetail', [
    'key'         => 'api.ledger.voucher.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/voucher/summary-search', 'VoucherController@apiSummarySearch', [
    'key'         => 'api.ledger.voucher.summary_search',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/voucher/search', 'VoucherController@apiSearch', [
    'key'         => 'api.ledger.voucher.search',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/voucher/transaction-search', 'VoucherController@apiTransactionSearch', [
    'key'         => 'api.ledger.voucher.transaction_search',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/ledger/voucher/save', 'VoucherController@apiSave', [
    'key'         => 'api.ledger.voucher.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/reorder', 'VoucherController@apiReorder', [
    'key'         => 'api.ledger.voucher.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/status', 'VoucherController@apiUpdateStatus', [
    'key'         => 'api.ledger.voucher.status',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/confirm', 'VoucherController@apiConfirm', [
    'key'         => 'api.ledger.voucher.confirm',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/cancel-review', 'VoucherController@apiCancelReview', [
    'key'         => 'api.ledger.voucher.cancel_review',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/complete-review', 'VoucherController@apiCompleteReview', [
    'key'         => 'api.ledger.voucher.complete_review',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/cancel-complete-review', 'VoucherController@apiCancelCompleteReview', [
    'key'         => 'api.ledger.voucher.cancel_complete_review',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/post', 'VoucherController@apiPost', [
    'key'         => 'api.ledger.voucher.post',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/reverse', 'VoucherController@apiReverse', [
    'key'         => 'api.ledger.voucher.reverse',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/link-transaction', 'VoucherController@apiLinkTransaction', [
    'key'         => 'api.ledger.voucher.link_transaction',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/reject', 'VoucherController@apiReject', [
    'key'         => 'api.ledger.voucher.reject',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * ?뚭퀎愿由?> ?꾪몴 泥섎━ 諛?嫄곕옒 ?곌퀎 API
 * ========================================================= */

// ============================================================
// ?꾪몴 湲곕컲 嫄곕옒 ?앹꽦
// ============================================================
$router->post('/api/ledger/voucher/create-transaction', 'VoucherController@apiCreateTransaction', [
    'key'         => 'api.ledger.voucher.create_transaction',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/delete', 'VoucherController@apiDelete', [
    'key'         => 'api.ledger.voucher.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/ledger/voucher/trash', 'VoucherController@apiTrashList', [
    'key'         => 'api.ledger.voucher.trash',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/restore', 'VoucherController@apiRestore', [
    'key'         => 'api.ledger.voucher.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/restore-bulk', 'VoucherController@apiRestoreBulk', [
    'key'         => 'api.ledger.voucher.restore_bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/restore-all', 'VoucherController@apiRestoreAll', [
    'key'         => 'api.ledger.voucher.restore_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/purge', 'VoucherController@apiPurge', [
    'key'         => 'api.ledger.voucher.purge',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/purge-bulk', 'VoucherController@apiPurgeBulk', [
    'key'         => 'api.ledger.voucher.purge_bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/purge-all', 'VoucherController@apiPurgeAll', [
    'key'         => 'api.ledger.voucher.purge_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);







/* =========================================================
 * ?뚭퀎愿由?> 嫄곕옒 愿由?API
 * ========================================================= */

// ============================================================
// 嫄곕옒 紐⑸줉 議고쉶
// ============================================================
$router->get('/api/ledger/transaction/list', 'TransactionController@apiList', [
    'key'         => 'api.ledger.transaction.list',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/ledger/transaction/reorder', 'TransactionController@apiReorder', [
    'key'         => 'api.ledger.transaction.reorder',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

// ============================================================
// 嫄곕옒 ?곸꽭 議고쉶
// ============================================================
$router->get('/api/ledger/transaction/detail', 'TransactionController@apiDetail', [
    'key'         => 'api.ledger.transaction.detail',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 嫄곕옒 ???// ============================================================
$router->get('/api/ledger/transaction/file', 'TransactionController@apiFile', [
    'key'         => 'api.ledger.transaction.file',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/ledger/transaction/save', 'TransactionController@apiSave', [
    'key'         => 'api.ledger.transaction.save',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

// ============================================================
// 嫄곕옒 湲곕컲 ?꾪몴 ?앹꽦
// ============================================================
$router->post('/api/ledger/transaction/create-voucher', 'TransactionController@apiCreateVoucher', [
    'key'         => 'api.ledger.transaction.create_voucher',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/transaction/link-voucher', 'TransactionController@apiLinkVoucher', [
    'key'         => 'api.ledger.transaction.link_voucher',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/transaction/unlink-voucher', 'TransactionController@apiUnlinkVoucher', [
    'key'         => 'api.ledger.transaction.unlink_voucher',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/transaction/delete', 'TransactionController@apiDelete', [
    'key'         => 'api.ledger.transaction.delete',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/ledger/transaction/trash', 'TransactionController@apiTrashList', [
    'key'         => 'api.ledger.transaction.trash',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/ledger/transaction/restore', 'TransactionController@apiRestore', [
    'key'         => 'api.ledger.transaction.restore',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/transaction/restore-bulk', 'TransactionController@apiRestoreBulk', [
    'key'         => 'api.ledger.transaction.restore_bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/transaction/restore-all', 'TransactionController@apiRestoreAll', [
    'key'         => 'api.ledger.transaction.restore_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/transaction/purge', 'TransactionController@apiPurge', [
    'key'         => 'api.ledger.transaction.purge',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/ledger/transaction/purge-bulk', 'TransactionController@apiPurgeBulk', [
    'key'         => 'api.ledger.transaction.purge_bulk',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/ledger/transaction/purge-all', 'TransactionController@apiPurgeAll', [
    'key'         => 'api.ledger.transaction.purge_all',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);
