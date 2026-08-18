<?php

global $router;

$router->get('/error', 'ErrorController@error403', [
    'key' => 'web.system.error.forbidden',
    'page' => '오류페이지',
    'page_description' => '시스템 오류 페이지',
    'permission_name' => '접근거부',
    'permission_description' => '권한 없음 페이지',
    'name' => '접근거부',
    'description' => '시스템 > 오류페이지 > 접근거부',
    'category' => '시스템',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/', 'HomeController@webRoot', [
    'key' => 'web.public.home.root',
    'page' => '홈',
    'page_description' => '메인 홈페이지',
    'permission_name' => '화면조회',
    'permission_description' => '홈 화면 조회',
    'name' => '홈',
    'description' => '공개사이트 > 홈 > 홈',
    'category' => '공개사이트',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/home', 'HomeController@webIndex', [
    'key' => 'web.public.home.index',
    'page' => '회사소개',
    'page_description' => '회사소개',
    'permission_name' => '화면조회',
    'permission_description' => '회사소개 화면 조회',
    'name' => '회사소개',
    'description' => '공개사이트 > 소개 > 회사소개',
    'category' => '공개사이트',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/about', 'AboutController@webAbout', [
    'key' => 'web.public.about',
    'page' => '비전',
    'page_description' => '회사 비전',
    'permission_name' => '화면조회',
    'permission_description' => '비전 화면 조회',
    'name' => '비전',
    'description' => '공개사이트 > 소개 > 비전',
    'category' => '공개사이트',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/vision', 'HomeController@webVision', [
    'key' => 'web.public.vision',
    'page' => '문의하기',
    'page_description' => '문의하기',
    'permission_name' => '화면조회',
    'permission_description' => '문의하기 화면 조회',
    'name' => '문의하기',
    'description' => '공개사이트 > 문의 > 문의하기',
    'category' => '공개사이트',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/contact', 'HomeController@webContact', [
    'key' => 'web.public.contact',
    'page' => '개인정보처리방침',
    'page_description' => '개인정보처리방침',
    'permission_name' => '화면조회',
    'permission_description' => '개인정보처리방침 화면 조회',
    'name' => '개인정보처리방침',
    'description' => '공개사이트 > 정보 > 개인정보처리방침',
    'category' => '공개사이트',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/privacy', 'HomeController@webPrivacy', [
    'key' => 'web.public.privacy',
    'page' => '사이트맵',
    'page_description' => '사이트맵',
    'permission_name' => '화면조회',
    'permission_description' => '사이트맵 화면 조회',
    'name' => '사이트맵',
    'description' => '사이트정보 > 사이트맵 > 사이트맵',
    'category' => '사이트정보',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/sitemap', 'HomeController@webSitemap', [
    'key' => 'web.public.sitemap',
    'page' => '사이트맵',
    'page_description' => '사이트맵',
    'permission_name' => '화면조회',
    'permission_description' => '사이트맵 화면 조회',
    'name' => '사이트맵',
    'description' => '사이트정보 > 사이트맵 > 사이트맵',
    'category' => '사이트정보',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/find-id', 'PasswordController@webFindId', [
    'key' => 'web.auth.find_id',
    'page' => '아이디찾기',
    'page_description' => '아이디 찾기',
    'permission_name' => '화면조회',
    'permission_description' => '아이디찾기 화면 조회',
    'name' => '아이디찾기',
    'description' => '인증 > 계정복구 > 아이디찾기',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/find-id/result', 'PasswordController@webFindIdResult', [
    'key' => 'web.auth.find_id_result',
    'page' => '아이디찾기',
    'page_description' => '아이디 찾기',
    'permission_name' => '결과조회',
    'permission_description' => '아이디찾기 결과 조회',
    'name' => '아이디찾기 결과',
    'description' => '인증 > 계정복구 > 아이디찾기결과',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/find-id/result', 'PasswordController@webFindIdResult', [
    'key' => 'web.auth.find_id_result.post',
    'page' => '아이디찾기',
    'page_description' => '아이디 찾기',
    'permission_name' => '결과조회',
    'permission_description' => '아이디찾기 결과 조회',
    'name' => '아이디찾기 결과',
    'description' => '인증 > 계정복구 > 아이디찾기결과',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/find-password', 'PasswordController@webFindPassword', [
    'key' => 'web.auth.find_password',
    'page' => '비밀번호찾기',
    'page_description' => '비밀번호 찾기',
    'permission_name' => '화면조회',
    'permission_description' => '비밀번호찾기 화면 조회',
    'name' => '비밀번호찾기',
    'description' => '인증 > 계정복구 > 비밀번호찾기',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/find-password/result', 'PasswordController@webFindPasswordResult', [
    'key' => 'web.auth.find_password_result',
    'page' => '비밀번호찾기',
    'page_description' => '비밀번호 찾기',
    'permission_name' => '결과조회',
    'permission_description' => '비밀번호찾기 결과 조회',
    'name' => '비밀번호찾기 결과',
    'description' => '인증 > 계정복구 > 비밀번호찾기결과',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/find-password/result', 'PasswordController@webFindPasswordResult', [
    'key' => 'web.auth.find_password_result.post',
    'page' => '비밀번호찾기',
    'page_description' => '비밀번호 찾기',
    'permission_name' => '결과조회',
    'permission_description' => '비밀번호찾기 결과 조회',
    'name' => '비밀번호찾기 결과',
    'description' => '인증 > 계정복구 > 비밀번호찾기결과',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/register', 'RegisterController@webRegisterPage', [
    'key' => 'web.auth.register',
    'page' => '회원가입',
    'page_description' => '회원가입',
    'permission_name' => '화면조회',
    'permission_description' => '회원가입 화면 조회',
    'name' => '회원가입',
    'description' => '인증 > 회원가입 > 회원가입',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/register_success', 'RegisterController@webRegisterSuccess', [
    'key' => 'web.auth.register_success',
    'page' => '가입승인',
    'page_description' => '가입 승인',
    'permission_name' => '승인대기',
    'permission_description' => '가입 승인 대기',
    'name' => '승인대기',
    'description' => '인증 > 가입승인 > 승인대기',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/waiting_approval', 'RegisterController@webWaitingApproval', [
    'key' => 'web.auth.waiting_approval',
    'page' => '가입승인',
    'page_description' => '가입 승인',
    'permission_name' => '승인대기',
    'permission_description' => '가입 승인 대기',
    'name' => '승인대기',
    'description' => '인증 > 가입승인 > 승인대기',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/2fa', 'TwoFactorController@webTwoFactor', [
    'key' => 'web.auth.2fa',
    'page' => '2차인증',
    'page_description' => '2차 인증',
    'permission_name' => '화면조회',
    'permission_description' => '2차인증 화면 조회',
    'name' => '2차인증',
    'description' => '인증 > 계정보안 > 2차인증',
    'category' => '인증',
    'auth' => false,
    'allow_statuses' => ['2FA_PENDING'],
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/password/change', 'PasswordController@webChangePassword', [
    'key' => 'web.auth.password_change',
    'page' => '비밀번호변경',
    'page_description' => '비밀번호 변경',
    'permission_name' => '화면조회',
    'permission_description' => '비밀번호변경 화면 조회',
    'name' => '비밀번호변경',
    'description' => '인증 > 계정보안 > 비밀번호변경',
    'category' => '인증',
    'auth' => false,
    'allow_statuses' => ['NORMAL', 'PASSWORD_EXPIRED'],
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/site/entry', 'TransactionController@webTransaction', [
    'key' => 'web.site.entry.index',
    'page' => '거래입력',
    'page_description' => '거래입력 관리',
    'permission_name' => '화면조회',
    'permission_description' => '거래입력 화면 조회',
    'name' => '거래입력',
    'description' => '현장관리 > 현장 > 거래입력',
    'category' => '현장관리 > 현장',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/site/entry/create', 'TransactionController@webCreate', [
    'key' => 'web.site.entry.create',
    'page' => '거래입력',
    'page_description' => '거래입력 관리',
    'permission_name' => '화면조회',
    'permission_description' => '거래입력 화면 조회',
    'name' => '거래입력',
    'description' => '현장관리 > 현장 > 거래입력',
    'category' => '현장관리 > 현장',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/site', 'SiteController@dashboard', [
    'key' => 'web.site.dashboard',
    'page' => '쇼핑몰 대시보드',
    'page_description' => '쇼핑몰 대시보드 관리',
    'permission_name' => '화면조회',
    'permission_description' => '쇼핑몰 대시보드 화면 조회',
    'name' => '쇼핑몰 대시보드',
    'description' => '쇼핑몰관리 > 쇼핑몰 > 쇼핑몰 대시보드',
    'category' => '쇼핑몰관리 > 쇼핑몰',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/notice', 'NoticeController@webIndex', [
    'key' => 'web.notice.index',
    'page' => '내정보 관리',
    'page_description' => '내정보 관리',
    'permission_name' => '화면조회',
    'permission_description' => '내정보 관리 화면 조회',
    'name' => '내정보 관리',
    'description' => '내정보 > 프로필 > 내정보 관리',
    'category' => '내정보 > 프로필',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);
