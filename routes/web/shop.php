<?php

global $router;

$router->get('/shop', 'ShopController@webIndex', [
    'key' => 'web.shop.index',
    'page' => '상품관리',
    'page_description' => '상품관리 관리',
    'permission_name' => '화면조회',
    'permission_description' => '상품관리 화면 조회',
    'name' => '상품관리',
    'description' => '쇼핑몰관리 > 쇼핑몰 > 상품관리',
    'category' => '쇼핑몰관리 > 쇼핑몰',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/shop/products', 'ShopController@webProducts', [
    'key' => 'web.shop.products',
    'page' => '분류관리',
    'page_description' => '분류관리 관리',
    'permission_name' => '화면조회',
    'permission_description' => '분류관리 화면 조회',
    'name' => '분류관리',
    'description' => '쇼핑몰관리 > 쇼핑몰 > 분류관리',
    'category' => '쇼핑몰관리 > 쇼핑몰',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/shop/categories', 'ShopController@webCategories', [
    'key' => 'web.shop.categories',
    'page' => '주문관리',
    'page_description' => '주문관리 관리',
    'permission_name' => '화면조회',
    'permission_description' => '주문관리 화면 조회',
    'name' => '주문관리',
    'description' => '쇼핑몰관리 > 쇼핑몰 > 주문관리',
    'category' => '쇼핑몰관리 > 쇼핑몰',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/shop/orders', 'ShopController@webOrders', [
    'key' => 'web.shop.orders',
    'page' => '결제관리',
    'page_description' => '결제관리 관리',
    'permission_name' => '화면조회',
    'permission_description' => '결제관리 화면 조회',
    'name' => '결제관리',
    'description' => '쇼핑몰관리 > 쇼핑몰 > 결제관리',
    'category' => '쇼핑몰관리 > 쇼핑몰',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/shop/payments', 'ShopController@webPayments', [
    'key' => 'web.shop.payments',
    'page' => '정산관리',
    'page_description' => '정산관리 관리',
    'permission_name' => '화면조회',
    'permission_description' => '정산관리 화면 조회',
    'name' => '정산관리',
    'description' => '쇼핑몰관리 > 쇼핑몰 > 정산관리',
    'category' => '쇼핑몰관리 > 쇼핑몰',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/shop/settlement', 'ShopController@webSettlement', [
    'key' => 'web.shop.settlement',
    'page' => '공지',
    'page_description' => '공지 관리',
    'permission_name' => '화면조회',
    'permission_description' => '공지 화면 조회',
    'name' => '공지',
    'description' => '공지/회의 > 공지/회의 > 공지',
    'category' => '공지/회의 > 공지/회의',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

