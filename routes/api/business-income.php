<?php

global $router;

$routes=[
    ['POST','list','apiList',['view'],false,'목록 조회','사업소득 문서 목록을 조회합니다.'],
    ['GET','detail','apiDetail',['view'],false,'상세 조회','사업소득 문서와 소득자별 작업내역을 조회합니다.'],
    ['GET','options','apiOptions',['view'],false,'선택항목 조회','사업소득 작성에 필요한 소득자·공종·단위 선택항목을 조회합니다.'],
    ['POST','calculate','apiCalculate',['save'],false,'금액 계산','외주 작업내역과 법정기준으로 원천징수액과 최종지급액을 계산합니다.'],
    ['GET','preflight','apiPreflight',['view'],false,'처리 전 검증','사업소득 저장·결재요청에 필요한 조건을 사전 검증합니다.'],
    ['POST','save','apiSave',['save'],true,'저장','사업소득 문서와 외주 작업내역을 저장하거나 수정합니다.'],
    ['POST','submit','apiSubmit',['save'],true,'결재 요청','작성한 사업소득 문서를 결재 요청합니다.'],
    ['POST','withdraw','apiWithdraw',['save'],true,'결재 요청 회수','진행 중인 사업소득 결재 요청을 회수합니다.'],
    ['POST','delete','apiDelete',['delete'],true,'삭제','사업소득 문서를 휴지통으로 이동합니다.'],
    ['GET','trash','apiTrashList',['view'],false,'휴지통 조회','삭제된 사업소득 문서를 조회합니다.'],
    ['POST','restore','apiRestore',['save'],true,'복구','삭제된 사업소득 문서를 복구합니다.'],
    ['POST','purge','apiPurge',['delete'],true,'영구삭제','사업소득 문서를 복구할 수 없도록 영구삭제합니다.'],
    ['GET','template','apiTemplate',['view'],false,'엑셀 양식 다운로드','사업소득 외주 작업내역 업로드용 엑셀 양식을 내려받습니다.'],
    ['POST','excel','apiExcel',['view'],false,'엑셀 다운로드','사업소득 자료를 엑셀 파일로 내려받습니다.'],
    ['POST','excel-upload-preview','apiExcelUploadPreview',['save'],false,'엑셀 업로드 사전검증','사업소득 엑셀 자료를 저장하기 전에 오류와 반영 내용을 확인합니다.'],
];
foreach($routes as[$method,$suffix,$action,$permissions,$log,$permissionName,$permissionDescription]){
    $router->{strtolower($method)}('/api/institution/income-data/business-income/'.$suffix,'BusinessIncomeController@'.$action,[
        'key'=>'api.institution.income_data.business_income.'.str_replace('-','_',$suffix),
        'page_key'=>'web.institution.income_data.business_income','page'=>'사업소득','page_description'=>'사업소득 작성·계산·결재 관리',
        'permission_name'=>$permissionName,'permission_description'=>$permissionDescription,'name'=>'사업소득 '.$permissionName,
        'description'=>'대외기관업무 > 소득자료관리 > 사업소득 > '.$permissionName,'category'=>'대외기관업무 > 소득자료관리','auth'=>true,'permissions'=>$permissions,'log'=>$log,
    ]);
}
