<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\Helpers\ApiErrorResponseHelper;

$required=['success','error','reason_code','message','field_errors','missing_inputs','invalid_workdays'];
$validation=ApiErrorResponseHelper::exception(new InvalidArgumentException('입력값을 확인해 주세요.'),'처리 중 오류가 발생했습니다.');
$requestKey=ApiErrorResponseHelper::exception(new RuntimeException('request_key가 기존 Payload와 다릅니다.'),'처리 중 오류가 발생했습니다.');
$database=ApiErrorResponseHelper::exception(new PDOException('SQLSTATE[42S02]: missing table'),'처리 중 오류가 발생했습니다.');
$checks=[
    'minimum_fields'=>array_diff($required,array_keys($validation))===[],
    'validation_code'=>$validation['error']==='VALIDATION_ERROR',
    'request_key_code'=>$requestKey['error']==='REQUEST_KEY_ERROR',
    'database_message_hidden'=>$database['error']==='INTERNAL_ERROR'&&!str_contains($database['message'],'SQLSTATE'),
    'field_errors_array'=>is_array($validation['field_errors']),
    'missing_inputs_array'=>is_array($validation['missing_inputs']),
    'invalid_workdays_array'=>is_array($validation['invalid_workdays']),
];
$failed=array_keys(array_filter($checks,static fn(bool $passed):bool=>!$passed));
if($failed!==[])throw new RuntimeException('API 구조화 오류 계약 실패: '.implode(', ',$failed));
echo json_encode(['success'=>true,'count'=>count($checks),'checks'=>$checks],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
