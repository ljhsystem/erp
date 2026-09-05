<?php
global $router;
$router->get('/site/sales','SalesController@index',[
    'key'=>'web.site.sales.index','page_key'=>'site.sales','page'=>'영업관리','page_description'=>'업체·인물 관계와 영업활동·수주 가능성 관리',
    'permission_name'=>'화면조회','permission_description'=>'현장관리 영업관리 화면 조회','name'=>'영업관리','description'=>'현장관리 > 영업관리','category'=>'현장관리','auth'=>true,'permissions'=>['view'],'log'=>false,
]);
$router->get('/site/estimate','EstimateController@index',['key'=>'web.site.estimate.index','page_key'=>'site.estimate','page'=>'견적관리','page_description'=>'실행견적·제출견적·네고·최종견적 관리','permission_name'=>'화면조회','permission_description'=>'현장관리 견적관리 화면 조회','name'=>'견적관리','description'=>'현장관리 > 견적관리','category'=>'현장관리','auth'=>true,'permissions'=>['view'],'log'=>false]);
