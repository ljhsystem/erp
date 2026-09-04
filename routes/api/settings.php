<?php

global $router;

$statutoryStandardRoutes = [
    ['POST', 'list', 'apiList', 'view', 'view'],
    ['GET', 'detail', 'apiDetail', 'detail', 'detail'],
    ['GET', 'options', 'apiOptions', 'view', 'options'],
    ['POST', 'save', 'apiSave', 'save', 'save'],
    ['GET', 'resolve', 'apiResolve', 'resolve', 'resolve'],
    ['GET', 'source-file', 'apiSourceFile', 'detail', 'source_file'],
    ['POST', 'correct-revision', 'apiCorrectRevision', 'save', 'correct_revision'],
    ['GET', 'revision-chain', 'apiRevisionChain', 'detail', 'revision_chain'],
];
foreach ($statutoryStandardRoutes as [$method, $suffix, $action, $permission, $routeKey]) {
    $router->{strtolower($method)}(
        '/api/settings/statutory-standards/' . $suffix,
        'StatutoryStandardController@' . $action,
        [
            'key' => 'api.settings.statutory_standards.' . $routeKey,
            'permission_key' => in_array($routeKey, ['reorder', 'correct_revision'], true)
                ? 'api.settings.statutory_standards.save'
                : ($routeKey === 'revision_chain' ? 'api.settings.statutory_standards.detail' : null),
            'page' => '법정기준관리',
            'page_description' => '법정 세율·요율·계산기준관리',
            'permission_name' => $permission,
            'permission_description' => '법정기준 ' . $permission,
            'name' => '법정기준 ' . $permission,
            'description' => '설정 > 기준관리 > 법정기준관리 > ' . $permission,
            'category' => '설정 > 기준관리',
            'auth' => true,
            'permissions' => [$permission],
            'log' => $method !== 'GET' && $action !== 'apiList',
        ]
    );
}

$router->get('/api/settings/base-info/company/detail', 'CompanyController@apiDetail', [
    'key' => 'api.settings.base-info.company.view',
    'page' => '회사정보',
    'page_description' => '회사정보 관리',
    'permission_name' => '조회',
    'permission_description' => '회사정보 조회',
    'name' => '회사정보 조회',
    'description' => '설정 > 기준정보관리 > 회사정보 > 조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/company/save', 'CompanyController@apiSave', [
    'key' => 'api.settings.base-info.company.save',
    'page' => '회사정보',
    'page_description' => '회사정보 관리',
    'permission_name' => '저장',
    'permission_description' => '회사정보 저장',
    'name' => '회사정보 저장',
    'description' => '설정 > 기준정보관리 > 회사정보 > 저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/brand/list', 'BrandController@apiList', [
    'key' => 'api.settings.base-info.brand.list',
    'page' => '브랜드관리',
    'page_description' => '브랜드 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '브랜드 목록 조회',
    'name' => '브랜드 목록조회',
    'description' => '설정 > 기준정보관리 > 브랜드관리 > 목록조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/brand/detail', 'BrandController@apiDetail', [
    'key' => 'api.settings.base-info.brand.detail',
    'page' => '브랜드관리',
    'page_description' => '브랜드 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '브랜드 상세 조회',
    'name' => '브랜드 상세조회',
    'description' => '설정 > 기준정보관리 > 브랜드관리 > 상세조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/brand/active-type', 'BrandController@apiActiveType', [
    'key' => 'api.settings.base-info.brand.active-type',
    'page' => '브랜드관리',
    'page_description' => '브랜드 정보관리',
    'permission_name' => '활성유형조회',
    'permission_description' => '브랜드 활성유형 조회',
    'name' => '브랜드 활성유형조회',
    'description' => '설정 > 기준정보관리 > 브랜드관리 > 활성유형조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/brand/save', 'BrandController@apiSave', [
    'key' => 'api.settings.base-info.brand.save',
    'page' => '브랜드관리',
    'page_description' => '브랜드 정보관리',
    'permission_name' => '저장',
    'permission_description' => '브랜드 저장',
    'name' => '브랜드 저장',
    'description' => '설정 > 기준정보관리 > 브랜드관리 > 저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/brand/purge', 'BrandController@apiPurge', [
    'key' => 'api.settings.base-info.brand.delete',
    'page' => '브랜드관리',
    'page_description' => '브랜드 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '브랜드 삭제',
    'name' => '브랜드 삭제',
    'description' => '설정 > 기준정보관리 > 브랜드관리 > 삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/brand/updatestatus', 'BrandController@apiUpdateStatus', [
    'key' => 'api.settings.base-info.brand.status',
    'page' => '브랜드관리',
    'page_description' => '브랜드 정보관리',
    'permission_name' => '상태변경',
    'permission_description' => '브랜드 상태 변경',
    'name' => '브랜드 상태변경',
    'description' => '설정 > 기준정보관리 > 브랜드관리 > 상태변경',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['update'],
    'log' => true,
]);

$router->get('/api/settings/base-info/cover/list', 'CoverController@apiList', [
    'key' => 'api.settings.base-info.cover.list',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '목록조회',
    'permission_description' => '커버이미지 목록 조회',
    'name' => '커버이미지 목록조회',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 목록조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/cover/public', 'CoverController@apiPublicList', [
    'key' => 'api.settings.base-info.cover.public',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '공개조회',
    'permission_description' => '커버이미지 공개 조회',
    'name' => '커버이미지 공개조회',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 공개조회',
    'category' => '설정 > 기준정보관리',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/api/settings/base-info/cover/detail', 'CoverController@apiDetail', [
    'key' => 'api.settings.base-info.cover.detail',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '상세조회',
    'permission_description' => '커버이미지 상세 조회',
    'name' => '커버이미지 상세조회',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 상세조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/cover/save', 'CoverController@apiSave', [
    'key' => 'api.settings.base-info.cover.save',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '저장',
    'permission_description' => '커버이미지 저장',
    'name' => '커버이미지 저장',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/cover/delete', 'CoverController@apiDelete', [
    'key' => 'api.settings.base-info.cover.delete',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '삭제',
    'permission_description' => '커버이미지 삭제',
    'name' => '커버이미지 삭제',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/settings/base-info/cover/trash', 'CoverController@apiTrashList', [
    'key' => 'api.settings.base-info.cover.trash.list',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '커버이미지 휴지통 조회',
    'name' => '커버이미지 휴지통조회',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 휴지통조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/cover/restore', 'CoverController@apiRestore', [
    'key' => 'api.settings.base-info.cover.restore',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '복구',
    'permission_description' => '커버이미지 복구',
    'name' => '커버이미지 복구',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/cover/restore-bulk', 'CoverController@apiRestoreBulk', [
    'key' => 'api.settings.base-info.cover.restore.bulk',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '일괄복구',
    'permission_description' => '커버이미지 일괄 복구',
    'name' => '커버이미지 일괄복구',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 일괄복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/cover/restore-all', 'CoverController@apiRestoreAll', [
    'key' => 'api.settings.base-info.cover.restore.all',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '전체복구',
    'permission_description' => '커버이미지 전체 복구',
    'name' => '커버이미지 전체복구',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 전체복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/cover/purge', 'CoverController@apiPurge', [
    'key' => 'api.settings.base-info.cover.purge',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '영구삭제',
    'permission_description' => '커버이미지 영구 삭제',
    'name' => '커버이미지 영구삭제',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/cover/purge-bulk', 'CoverController@apiPurgeBulk', [
    'key' => 'api.settings.base-info.cover.purge.bulk',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '커버이미지 일괄 영구 삭제',
    'name' => '커버이미지 일괄영구삭제',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 일괄영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/cover/purge-all', 'CoverController@apiPurgeAll', [
    'key' => 'api.settings.base-info.cover.purge.all',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '커버이미지 전체 영구 삭제',
    'name' => '커버이미지 전체영구삭제',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 전체영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/cover/reorder', 'CoverController@apiReorder', [
    'key' => 'api.settings.base-info.cover.reorder',
    'page' => '커버이미지',
    'page_description' => '커버이미지 관리',
    'permission_name' => '정렬저장',
    'permission_description' => '커버이미지 정렬 저장',
    'name' => '커버이미지 정렬저장',
    'description' => '설정 > 기준정보관리 > 커버이미지 > 정렬저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/client/list', 'ClientController@apiList', [
    'key' => 'api.settings.base-info.client.list',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '거래처 목록 조회',
    'name' => '거래처 목록조회',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 목록조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/client/detail', 'ClientController@apiDetail', [
    'key' => 'api.settings.base-info.client.detail',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '거래처 상세 조회',
    'name' => '거래처 상세조회',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 상세조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/client/search-picker', 'ClientController@apiSearchPicker', [
    'key' => 'api.settings.base-info.client.search-picker',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '검색선택',
    'permission_description' => '거래처 검색 선택',
    'name' => '거래처 검색선택',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 검색선택',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/base-info/client/save', 'ClientController@apiSave', [
    'key' => 'api.settings.base-info.client.save',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '저장',
    'permission_description' => '거래처 저장',
    'name' => '거래처 저장',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/client/delete', 'ClientController@apiDelete', [
    'key' => 'api.settings.base-info.client.delete',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '거래처 삭제',
    'name' => '거래처 삭제',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/client/company-name-history/delete', 'ClientController@apiDeleteCompanyNameHistory', [
    'key' => 'api.settings.base-info.client.company-name-history.delete',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '회사명이력삭제',
    'permission_description' => '거래처 회사명 이력 삭제',
    'name' => '거래처 회사명이력삭제',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 회사명이력삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/settings/base-info/client/trash', 'ClientController@apiTrashList', [
    'key' => 'api.settings.base-info.client.trash.list',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '거래처 휴지통 조회',
    'name' => '거래처 휴지통조회',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 휴지통조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/client/restore', 'ClientController@apiRestore', [
    'key' => 'api.settings.base-info.client.restore',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '복구',
    'permission_description' => '거래처 복구',
    'name' => '거래처 복구',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/client/restore-bulk', 'ClientController@apiRestoreBulk', [
    'key' => 'api.settings.base-info.client.restore-bulk',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '일괄복구',
    'permission_description' => '거래처 일괄 복구',
    'name' => '거래처 일괄복구',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 일괄복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/client/restore-all', 'ClientController@apiRestoreAll', [
    'key' => 'api.settings.base-info.client.restore-all',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '전체복구',
    'permission_description' => '거래처 전체 복구',
    'name' => '거래처 전체복구',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 전체복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/client/purge', 'ClientController@apiPurge', [
    'key' => 'api.settings.base-info.client.purge',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '영구삭제',
    'permission_description' => '거래처 영구 삭제',
    'name' => '거래처 영구삭제',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/client/purge-bulk', 'ClientController@apiPurgeBulk', [
    'key' => 'api.settings.base-info.client.purge-bulk',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '거래처 일괄 영구 삭제',
    'name' => '거래처 일괄영구삭제',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 일괄영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/client/purge-all', 'ClientController@apiPurgeAll', [
    'key' => 'api.settings.base-info.client.purge-all',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '거래처 전체 영구 삭제',
    'name' => '거래처 전체영구삭제',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 전체영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/client/reorder', 'ClientController@apiReorder', [
    'key' => 'api.settings.base-info.client.reorder',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '정렬저장',
    'permission_description' => '거래처 정렬 저장',
    'name' => '거래처 정렬저장',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 정렬저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/client/template', 'ClientController@apiDownloadTemplate', [
    'key' => 'api.settings.base-info.client.template',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '양식다운로드',
    'permission_description' => '거래처 양식 다운로드',
    'name' => '거래처 양식다운로드',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 양식다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/base-info/client/excel-upload', 'ClientController@apiSaveFromExcel', [
    'key' => 'api.settings.base-info.client.excel-upload',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '엑셀업로드',
    'permission_description' => '거래처 엑셀 업로드',
    'name' => '거래처 엑셀업로드',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 엑셀업로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/client/download', 'ClientController@apiDownload', [
    'key' => 'api.settings.base-info.client.excel',
    'page' => '거래처관리',
    'page_description' => '거래처 정보관리',
    'permission_name' => '엑셀다운로드',
    'permission_description' => '거래처 엑셀 다운로드',
    'name' => '거래처 엑셀다운로드',
    'description' => '설정 > 기준정보관리 > 거래처관리 > 엑셀다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/project/list', 'ProjectController@apiList', [
    'key' => 'api.settings.base-info.project.list',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '프로젝트 목록 조회',
    'name' => '프로젝트 목록조회',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 목록조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/project/detail', 'ProjectController@apiDetail', [
    'key' => 'api.settings.base-info.project.detail',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '프로젝트 상세 조회',
    'name' => '프로젝트 상세조회',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 상세조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/project/search-picker', 'ProjectController@apiSearchPicker', [
    'key' => 'api.settings.base-info.project.search-picker',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '검색선택',
    'permission_description' => '프로젝트 검색 선택',
    'name' => '프로젝트 검색선택',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 검색선택',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/base-info/project/distinct-values', 'ProjectController@apiDistinctValues', [
    'key' => 'api.settings.base-info.project.distinct-values',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '고유값조회',
    'permission_description' => '프로젝트 고유값 조회',
    'name' => '프로젝트 고유값조회',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 고유값조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/base-info/project/save', 'ProjectController@apiSave', [
    'key' => 'api.settings.base-info.project.save',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '저장',
    'permission_description' => '프로젝트 저장',
    'name' => '프로젝트 저장',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/project/delete', 'ProjectController@apiDelete', [
    'key' => 'api.settings.base-info.project.delete',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '프로젝트 삭제',
    'name' => '프로젝트 삭제',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/settings/base-info/project/trash', 'ProjectController@apiTrashList', [
    'key' => 'api.settings.base-info.project.trash.list',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '프로젝트 휴지통 조회',
    'name' => '프로젝트 휴지통조회',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 휴지통조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/project/restore', 'ProjectController@apiRestore', [
    'key' => 'api.settings.base-info.project.restore',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '복구',
    'permission_description' => '프로젝트 복구',
    'name' => '프로젝트 복구',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/project/restore-bulk', 'ProjectController@apiRestoreBulk', [
    'key' => 'api.settings.base-info.project.restore-bulk',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '일괄복구',
    'permission_description' => '프로젝트 일괄 복구',
    'name' => '프로젝트 일괄복구',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 일괄복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/project/restore-all', 'ProjectController@apiRestoreAll', [
    'key' => 'api.settings.base-info.project.restore-all',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '전체복구',
    'permission_description' => '프로젝트 전체 복구',
    'name' => '프로젝트 전체복구',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 전체복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/project/purge', 'ProjectController@apiPurge', [
    'key' => 'api.settings.base-info.project.purge',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '영구삭제',
    'permission_description' => '프로젝트 영구 삭제',
    'name' => '프로젝트 영구삭제',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/project/purge-bulk', 'ProjectController@apiPurgeBulk', [
    'key' => 'api.settings.base-info.project.purge-bulk',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '프로젝트 일괄 영구 삭제',
    'name' => '프로젝트 일괄영구삭제',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 일괄영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/project/purge-all', 'ProjectController@apiPurgeAll', [
    'key' => 'api.settings.base-info.project.purge-all',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '프로젝트 전체 영구 삭제',
    'name' => '프로젝트 전체영구삭제',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 전체영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/project/reorder', 'ProjectController@apiReorder', [
    'key' => 'api.settings.base-info.project.reorder',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '정렬저장',
    'permission_description' => '프로젝트 정렬 저장',
    'name' => '프로젝트 정렬저장',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 정렬저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/project/template', 'ProjectController@apiDownloadTemplate', [
    'key' => 'api.settings.base-info.project.template',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '양식다운로드',
    'permission_description' => '프로젝트 양식 다운로드',
    'name' => '프로젝트 양식다운로드',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 양식다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/base-info/project/excel-upload', 'ProjectController@apiSaveFromExcel', [
    'key' => 'api.settings.base-info.project.excel-upload',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '엑셀업로드',
    'permission_description' => '프로젝트 엑셀 업로드',
    'name' => '프로젝트 엑셀업로드',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 엑셀업로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/project/download', 'ProjectController@apiDownload', [
    'key' => 'api.settings.base-info.project.excel',
    'page' => '프로젝트관리',
    'page_description' => '프로젝트 정보관리',
    'permission_name' => '엑셀다운로드',
    'permission_description' => '프로젝트 엑셀 다운로드',
    'name' => '프로젝트 엑셀다운로드',
    'description' => '설정 > 기준정보관리 > 프로젝트관리 > 엑셀다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/bank-account/list', 'BankAccountController@apiList', [
    'key' => 'api.settings.base-info.bank-account.list',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '계좌 목록 조회',
    'name' => '계좌 목록조회',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 목록조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/bank-account/detail', 'BankAccountController@apiDetail', [
    'key' => 'api.settings.base-info.bank-account.detail',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '계좌 상세 조회',
    'name' => '계좌 상세조회',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 상세조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/bank-account/search-picker', 'BankAccountController@apiSearchPicker', [
    'key' => 'api.settings.base-info.bank-account.search-picker',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '검색선택',
    'permission_description' => '계좌 검색 선택',
    'name' => '계좌 검색선택',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 검색선택',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/base-info/bank-account/save', 'BankAccountController@apiSave', [
    'key' => 'api.settings.base-info.bank-account.save',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '저장',
    'permission_description' => '계좌 저장',
    'name' => '계좌 저장',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/bank-account/delete', 'BankAccountController@apiDelete', [
    'key' => 'api.settings.base-info.bank-account.delete',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '계좌 삭제',
    'name' => '계좌 삭제',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/settings/base-info/bank-account/trash', 'BankAccountController@apiTrashList', [
    'key' => 'api.settings.base-info.bank-account.trash.list',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '계좌 휴지통 조회',
    'name' => '계좌 휴지통조회',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 휴지통조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/bank-account/restore', 'BankAccountController@apiRestore', [
    'key' => 'api.settings.base-info.bank-account.restore',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '복구',
    'permission_description' => '계좌 복구',
    'name' => '계좌 복구',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/bank-account/restore-bulk', 'BankAccountController@apiRestoreBulk', [
    'key' => 'api.settings.base-info.bank-account.restore-bulk',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '일괄복구',
    'permission_description' => '계좌 일괄 복구',
    'name' => '계좌 일괄복구',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 일괄복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/bank-account/restore-all', 'BankAccountController@apiRestoreAll', [
    'key' => 'api.settings.base-info.bank-account.restore-all',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '전체복구',
    'permission_description' => '계좌 전체 복구',
    'name' => '계좌 전체복구',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 전체복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/bank-account/purge', 'BankAccountController@apiPurge', [
    'key' => 'api.settings.base-info.bank-account.purge',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '영구삭제',
    'permission_description' => '계좌 영구 삭제',
    'name' => '계좌 영구삭제',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/bank-account/purge-bulk', 'BankAccountController@apiPurgeBulk', [
    'key' => 'api.settings.base-info.bank-account.purge-bulk',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '계좌 일괄 영구 삭제',
    'name' => '계좌 일괄영구삭제',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 일괄영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/bank-account/purge-all', 'BankAccountController@apiPurgeAll', [
    'key' => 'api.settings.base-info.bank-account.purge-all',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '계좌 전체 영구 삭제',
    'name' => '계좌 전체영구삭제',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 전체영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/bank-account/reorder', 'BankAccountController@apiReorder', [
    'key' => 'api.settings.base-info.bank-account.reorder',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '정렬저장',
    'permission_description' => '계좌 정렬 저장',
    'name' => '계좌 정렬저장',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 정렬저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/bank-account/template', 'BankAccountController@apiDownloadTemplate', [
    'key' => 'api.settings.base-info.bank-account.template',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '양식다운로드',
    'permission_description' => '계좌 양식 다운로드',
    'name' => '계좌 양식다운로드',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 양식다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/base-info/bank-account/excel-upload', 'BankAccountController@apiSaveFromExcel', [
    'key' => 'api.settings.base-info.bank-account.excel-upload',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '엑셀업로드',
    'permission_description' => '계좌 엑셀 업로드',
    'name' => '계좌 엑셀업로드',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 엑셀업로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/bank-account/download', 'BankAccountController@apiDownload', [
    'key' => 'api.settings.base-info.bank-account.excel',
    'page' => '계좌관리',
    'page_description' => '계좌 정보관리',
    'permission_name' => '엑셀다운로드',
    'permission_description' => '계좌 엑셀 다운로드',
    'name' => '계좌 엑셀다운로드',
    'description' => '설정 > 기준정보관리 > 계좌관리 > 엑셀다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/card/list', 'CardController@apiList', [
    'key' => 'api.settings.base-info.card.list',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '카드 목록 조회',
    'name' => '카드 목록조회',
    'description' => '설정 > 기준정보관리 > 카드관리 > 목록조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/card/detail', 'CardController@apiDetail', [
    'key' => 'api.settings.base-info.card.detail',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '카드 상세 조회',
    'name' => '카드 상세조회',
    'description' => '설정 > 기준정보관리 > 카드관리 > 상세조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/card/search-picker', 'CardController@apiSearchPicker', [
    'key' => 'api.settings.base-info.card.search-picker',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '검색선택',
    'permission_description' => '카드 검색 선택',
    'name' => '카드 검색선택',
    'description' => '설정 > 기준정보관리 > 카드관리 > 검색선택',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/base-info/card/save', 'CardController@apiSave', [
    'key' => 'api.settings.base-info.card.save',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '저장',
    'permission_description' => '카드 저장',
    'name' => '카드 저장',
    'description' => '설정 > 기준정보관리 > 카드관리 > 저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/card/delete', 'CardController@apiDelete', [
    'key' => 'api.settings.base-info.card.delete',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '카드 삭제',
    'name' => '카드 삭제',
    'description' => '설정 > 기준정보관리 > 카드관리 > 삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/settings/base-info/card/trash', 'CardController@apiTrashList', [
    'key' => 'api.settings.base-info.card.trash.list',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '카드 휴지통 조회',
    'name' => '카드 휴지통조회',
    'description' => '설정 > 기준정보관리 > 카드관리 > 휴지통조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/card/restore', 'CardController@apiRestore', [
    'key' => 'api.settings.base-info.card.restore',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '복구',
    'permission_description' => '카드 복구',
    'name' => '카드 복구',
    'description' => '설정 > 기준정보관리 > 카드관리 > 복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/card/restore-bulk', 'CardController@apiRestoreBulk', [
    'key' => 'api.settings.base-info.card.restore-bulk',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '일괄복구',
    'permission_description' => '카드 일괄 복구',
    'name' => '카드 일괄복구',
    'description' => '설정 > 기준정보관리 > 카드관리 > 일괄복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/card/restore-all', 'CardController@apiRestoreAll', [
    'key' => 'api.settings.base-info.card.restore-all',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '전체복구',
    'permission_description' => '카드 전체 복구',
    'name' => '카드 전체복구',
    'description' => '설정 > 기준정보관리 > 카드관리 > 전체복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/card/purge', 'CardController@apiPurge', [
    'key' => 'api.settings.base-info.card.purge',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '영구삭제',
    'permission_description' => '카드 영구 삭제',
    'name' => '카드 영구삭제',
    'description' => '설정 > 기준정보관리 > 카드관리 > 영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/card/purge-bulk', 'CardController@apiPurgeBulk', [
    'key' => 'api.settings.base-info.card.purge-bulk',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '카드 일괄 영구 삭제',
    'name' => '카드 일괄영구삭제',
    'description' => '설정 > 기준정보관리 > 카드관리 > 일괄영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/card/purge-all', 'CardController@apiPurgeAll', [
    'key' => 'api.settings.base-info.card.purge-all',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '카드 전체 영구 삭제',
    'name' => '카드 전체영구삭제',
    'description' => '설정 > 기준정보관리 > 카드관리 > 전체영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/card/reorder', 'CardController@apiReorder', [
    'key' => 'api.settings.base-info.card.reorder',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '정렬저장',
    'permission_description' => '카드 정렬 저장',
    'name' => '카드 정렬저장',
    'description' => '설정 > 기준정보관리 > 카드관리 > 정렬저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/card/template', 'CardController@apiDownloadTemplate', [
    'key' => 'api.settings.base-info.card.template',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '양식다운로드',
    'permission_description' => '카드 양식 다운로드',
    'name' => '카드 양식다운로드',
    'description' => '설정 > 기준정보관리 > 카드관리 > 양식다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/base-info/card/excel-upload', 'CardController@apiSaveFromExcel', [
    'key' => 'api.settings.base-info.card.excel-upload',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '엑셀업로드',
    'permission_description' => '카드 엑셀 업로드',
    'name' => '카드 엑셀업로드',
    'description' => '설정 > 기준정보관리 > 카드관리 > 엑셀업로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/card/download', 'CardController@apiDownload', [
    'key' => 'api.settings.base-info.card.download',
    'page' => '카드관리',
    'page_description' => '카드 정보관리',
    'permission_name' => '엑셀다운로드',
    'permission_description' => '카드 엑셀 다운로드',
    'name' => '카드 엑셀다운로드',
    'description' => '설정 > 기준정보관리 > 카드관리 > 엑셀다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/work-team/list', 'WorkTeamController@apiList', [
    'key' => 'work_team.view',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '팀 목록 조회',
    'name' => '팀 목록조회',
    'description' => '설정 > 기준정보관리 > 팀 > 목록조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/base-info/work-team/detail', 'WorkTeamController@apiDetail', [
    'key' => 'work_team.view',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '팀 상세 조회',
    'name' => '팀 상세조회',
    'description' => '설정 > 기준정보관리 > 팀 > 상세조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/save', 'WorkTeamController@apiSave', [
    'key' => 'work_team.save',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '저장',
    'permission_description' => '팀 저장',
    'name' => '팀 저장',
    'description' => '설정 > 기준정보관리 > 팀 > 저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/delete', 'WorkTeamController@apiDelete', [
    'key' => 'work_team.delete',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '팀 삭제',
    'name' => '팀 삭제',
    'description' => '설정 > 기준정보관리 > 팀 > 삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/settings/base-info/work-team/trash', 'WorkTeamController@apiTrashList', [
    'key' => 'work_team.view',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '팀 휴지통 조회',
    'name' => '팀 휴지통조회',
    'description' => '설정 > 기준정보관리 > 팀 > 휴지통조회',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/restore', 'WorkTeamController@apiRestore', [
    'key' => 'work_team.save',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '복구',
    'permission_description' => '팀 복구',
    'name' => '팀 복구',
    'description' => '설정 > 기준정보관리 > 팀 > 복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/restore-bulk', 'WorkTeamController@apiRestoreBulk', [
    'key' => 'work_team.save',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '일괄복구',
    'permission_description' => '팀 일괄 복구',
    'name' => '팀 일괄복구',
    'description' => '설정 > 기준정보관리 > 팀 > 일괄복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/restore-all', 'WorkTeamController@apiRestoreAll', [
    'key' => 'work_team.save',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '전체복구',
    'permission_description' => '팀 전체 복구',
    'name' => '팀 전체복구',
    'description' => '설정 > 기준정보관리 > 팀 > 전체복구',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/purge', 'WorkTeamController@apiPurge', [
    'key' => 'work_team.delete',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '영구삭제',
    'permission_description' => '팀 영구 삭제',
    'name' => '팀 영구삭제',
    'description' => '설정 > 기준정보관리 > 팀 > 영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/purge-bulk', 'WorkTeamController@apiPurgeBulk', [
    'key' => 'work_team.delete',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '팀 일괄 영구 삭제',
    'name' => '팀 일괄영구삭제',
    'description' => '설정 > 기준정보관리 > 팀 > 일괄영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/purge-all', 'WorkTeamController@apiPurgeAll', [
    'key' => 'work_team.delete',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '팀 전체 영구 삭제',
    'name' => '팀 전체영구삭제',
    'description' => '설정 > 기준정보관리 > 팀 > 전체영구삭제',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/reorder', 'WorkTeamController@apiReorder', [
    'key' => 'work_team.save',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '정렬저장',
    'permission_description' => '팀 정렬 저장',
    'name' => '팀 정렬저장',
    'description' => '설정 > 기준정보관리 > 팀 > 정렬저장',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/work-team/template', 'WorkTeamController@apiDownloadTemplate', [
    'key' => 'work_team.view',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '양식다운로드',
    'permission_description' => '팀 양식 다운로드',
    'name' => '팀 양식다운로드',
    'description' => '설정 > 기준정보관리 > 팀 > 양식다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/base-info/work-team/excel', 'WorkTeamController@apiDownloadExcel', [
    'key' => 'work_team.view',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '엑셀다운로드',
    'permission_description' => '팀 엑셀 다운로드',
    'name' => '팀 엑셀다운로드',
    'description' => '설정 > 기준정보관리 > 팀 > 엑셀다운로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/base-info/work-team/excel-upload', 'WorkTeamController@apiExcelUpload', [
    'key' => 'work_team.save',
    'page' => '팀',
    'page_description' => '팀 정보관리',
    'permission_name' => '엑셀업로드',
    'permission_description' => '팀 엑셀 업로드',
    'name' => '팀 엑셀업로드',
    'description' => '설정 > 기준정보관리 > 팀 > 엑셀업로드',
    'category' => '설정 > 기준정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/organization/employee/list', 'EmployeeController@apiList', [
    'key' => 'api.settings.employee.list',
    'page' => '직원관리',
    'page_description' => '직원 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '직원 목록 조회',
    'name' => '직원 목록조회',
    'description' => '설정 > 조직관리 > 직원관리 > 목록조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/organization/employee/detail', 'EmployeeController@apiDetail', [
    'key' => 'api.settings.employee.detail',
    'page' => '직원관리',
    'page_description' => '직원 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '직원 상세 조회',
    'name' => '직원 상세조회',
    'description' => '설정 > 조직관리 > 직원관리 > 상세조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/organization/employee/search-picker', 'EmployeeController@apiSearchPicker', [
    'key' => 'api.settings.employee.search',
    'page' => '직원관리',
    'page_description' => '직원 정보관리',
    'permission_name' => '검색선택',
    'permission_description' => '직원 검색 선택',
    'name' => '직원 검색선택',
    'description' => '설정 > 조직관리 > 직원관리 > 검색선택',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/organization/employee/representative-qualifications', 'EmployeeController@apiRepresentativeQualifications', [
    'key' => 'api.settings.organization.employee.representative_qualifications',
    'permission_key' => 'api.settings.organization.employee.detail',
    'page' => '직원관리',
    'page_description' => '직원 대표자격 후보 조회',
    'permission_name' => '상세조회',
    'permission_description' => '직원 상세조회',
    'name' => '대표자격 후보 조회',
    'description' => '직원의 검증 완료된 유효 대표자격 후보 조회',
    'category' => '설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/employee/save', 'EmployeeController@apiSave', [
    'key' => 'api.settings.employee.save',
    'page' => '직원관리',
    'page_description' => '직원 정보관리',
    'permission_name' => '저장',
    'permission_description' => '직원 저장',
    'name' => '직원 저장',
    'description' => '설정 > 조직관리 > 직원관리 > 저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/employee/update-status', 'EmployeeController@apiUpdateStatus', [
    'key' => 'api.settings.employee.update-status',
    'page' => '직원관리',
    'page_description' => '직원 정보관리',
    'permission_name' => '상태변경',
    'permission_description' => '직원 상태 변경',
    'name' => '직원 상태변경',
    'description' => '설정 > 조직관리 > 직원관리 > 상태변경',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/employee/delete', 'EmployeeController@apiDelete', [
    'key' => 'api.settings.employee.delete',
    'page' => '직원관리',
    'page_description' => '직원 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '직원 삭제',
    'name' => '직원 삭제',
    'description' => '설정 > 조직관리 > 직원관리 > 삭제',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/organization/employee/reorder', 'EmployeeController@apiReorder', [
    'key' => 'api.settings.employee.reorder',
    'page' => '직원관리',
    'page_description' => '직원 정보관리',
    'permission_name' => '정렬저장',
    'permission_description' => '직원 정렬 저장',
    'name' => '직원 정렬저장',
    'description' => '설정 > 조직관리 > 직원관리 > 정렬저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/organization/department/list', 'DepartmentController@apiList', [
    'key' => 'api.settings.department.list',
    'page' => '부서관리',
    'page_description' => '부서 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '부서 목록 조회',
    'name' => '부서 목록조회',
    'description' => '설정 > 조직관리 > 부서관리 > 목록조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/organization/department/detail', 'DepartmentController@apiDetail', [
    'key' => 'api.settings.department.detail',
    'page' => '부서관리',
    'page_description' => '부서 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '부서 상세 조회',
    'name' => '부서 상세조회',
    'description' => '설정 > 조직관리 > 부서관리 > 상세조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/department/save', 'DepartmentController@apiSave', [
    'key' => 'api.settings.department.save',
    'page' => '부서관리',
    'page_description' => '부서 정보관리',
    'permission_name' => '저장',
    'permission_description' => '부서 저장',
    'name' => '부서 저장',
    'description' => '설정 > 조직관리 > 부서관리 > 저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/department/delete', 'DepartmentController@apiDelete', [
    'key' => 'api.settings.department.delete',
    'page' => '부서관리',
    'page_description' => '부서 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '부서 삭제',
    'name' => '부서 삭제',
    'description' => '설정 > 조직관리 > 부서관리 > 삭제',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/organization/department/reorder', 'DepartmentController@apiReorder', [
    'key' => 'api.settings.department.reorder',
    'page' => '부서관리',
    'page_description' => '부서 정보관리',
    'permission_name' => '정렬저장',
    'permission_description' => '부서 정렬 저장',
    'name' => '부서 정렬저장',
    'description' => '설정 > 조직관리 > 부서관리 > 정렬저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/organization/position/list', 'PositionController@apiList', [
    'key' => 'api.settings.position.list',
    'page' => '직책관리',
    'page_description' => '직책 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '직책 목록 조회',
    'name' => '직책 목록조회',
    'description' => '설정 > 조직관리 > 직책관리 > 목록조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/organization/position/detail', 'PositionController@apiDetail', [
    'key' => 'api.settings.position.detail',
    'page' => '직책관리',
    'page_description' => '직책 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '직책 상세 조회',
    'name' => '직책 상세조회',
    'description' => '설정 > 조직관리 > 직책관리 > 상세조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/position/save', 'PositionController@apiSave', [
    'key' => 'api.settings.position.save',
    'page' => '직책관리',
    'page_description' => '직책 정보관리',
    'permission_name' => '저장',
    'permission_description' => '직책 저장',
    'name' => '직책 저장',
    'description' => '설정 > 조직관리 > 직책관리 > 저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/position/delete', 'PositionController@apiDelete', [
    'key' => 'api.settings.position.delete',
    'page' => '직책관리',
    'page_description' => '직책 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '직책 삭제',
    'name' => '직책 삭제',
    'description' => '설정 > 조직관리 > 직책관리 > 삭제',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/organization/position/reorder', 'PositionController@apiReorder', [
    'key' => 'api.settings.position.reorder',
    'page' => '직책관리',
    'page_description' => '직책 정보관리',
    'permission_name' => '정렬저장',
    'permission_description' => '직책 정렬 저장',
    'name' => '직책 정렬저장',
    'description' => '설정 > 조직관리 > 직책관리 > 정렬저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/organization/approval/template/list', 'ApprovalTemplateController@apiTemplateList', [
    'key' => 'api.settings.approval.template.list',
    'page' => '결재템플릿',
    'page_description' => '결재템플릿 관리',
    'permission_name' => '목록조회',
    'permission_description' => '결재템플릿 목록 조회',
    'name' => '결재템플릿 목록조회',
    'description' => '설정 > 조직관리 > 결재템플릿 > 목록조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/approval/template/save', 'ApprovalTemplateController@apiTemplateSave', [
    'key' => 'api.settings.approval.template.save',
    'page' => '결재템플릿',
    'page_description' => '결재템플릿 관리',
    'permission_name' => '저장',
    'permission_description' => '결재템플릿 저장',
    'name' => '결재템플릿 저장',
    'description' => '설정 > 조직관리 > 결재템플릿 > 저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/approval/template/delete', 'ApprovalTemplateController@apiTemplateDelete', [
    'key' => 'api.settings.approval.template.delete',
    'page' => '결재템플릿',
    'page_description' => '결재템플릿 관리',
    'permission_name' => '삭제',
    'permission_description' => '결재템플릿 삭제',
    'name' => '결재템플릿 삭제',
    'description' => '설정 > 조직관리 > 결재템플릿 > 삭제',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/organization/approval/template/reorder', 'ApprovalTemplateController@apiTemplateReorder', [
    'key' => 'api.settings.approval.template.reorder',
    'page' => '결재템플릿',
    'page_description' => '결재템플릿 관리',
    'permission_name' => '정렬저장',
    'permission_description' => '결재템플릿 정렬 저장',
    'name' => '결재템플릿 정렬저장',
    'description' => '설정 > 조직관리 > 결재템플릿 > 정렬저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/organization/approval/step/list', 'ApprovalTemplateController@apiStepList', [
    'key' => 'api.settings.approval.step.list',
    'page' => '결재템플릿',
    'page_description' => '결재템플릿 관리',
    'permission_name' => '결재단계목록조회',
    'permission_description' => '결재단계 목록 조회',
    'name' => '결재단계 목록조회',
    'description' => '설정 > 조직관리 > 결재템플릿 > 결재단계 목록조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/approval/step/save', 'ApprovalTemplateController@apiStepSave', [
    'key' => 'api.settings.approval.step.save',
    'page' => '결재템플릿',
    'page_description' => '결재템플릿 관리',
    'permission_name' => '결재단계저장',
    'permission_description' => '결재단계 저장',
    'name' => '결재단계 저장',
    'description' => '설정 > 조직관리 > 결재템플릿 > 결재단계 저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/approval/step/reorder', 'ApprovalTemplateController@apiStepReorder', [
    'key' => 'api.settings.approval.step.reorder',
    'page' => '결재템플릿',
    'page_description' => '결재템플릿 관리',
    'permission_name' => '결재단계정렬저장',
    'permission_description' => '결재단계 정렬 저장',
    'name' => '결재단계 정렬저장',
    'description' => '설정 > 조직관리 > 결재템플릿 > 결재단계 정렬저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/approval/step/delete', 'ApprovalTemplateController@apiStepDelete', [
    'key' => 'api.settings.approval.step.delete',
    'page' => '결재템플릿',
    'page_description' => '결재템플릿 관리',
    'permission_name' => '결재단계삭제',
    'permission_description' => '결재단계 삭제',
    'name' => '결재단계 삭제',
    'description' => '설정 > 조직관리 > 결재템플릿 > 결재단계 삭제',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);
