<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
$schema=(string)($argv[1]??'');
if(!preg_match('/^tmp_daily_employment_income_[a-f0-9]{12}$/',$schema))throw new InvalidArgumentException('정확한 일용근로소득 tmp Schema명이 필요합니다.');
$token=substr($schema,-12);
$load=static function(string $path):?array{if(!is_file($path))return null;$value=require $path;return is_array($value)?$value:null;};
$topology=$load(PROJECT_ROOT.'/../secure-config/db_replication.php');$legacy=$load(PROJECT_ROOT.'/../secure-config/db_config.php');
$target=strtolower((string)($topology['active_target']??''));$node=is_array($topology[$target]??null)?$topology[$target]:$legacy;
if(!is_array($node))throw new RuntimeException('활성 MariaDB 연결설정을 찾을 수 없습니다.');
$host=(string)($node['host']??'');$port=(int)($node['port']??3306);$user=(string)($node['user']??'');$pass=(string)($node['pass']??'');
$db=new PDO("mysql:host={$host};port={$port};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$statement=$db->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:schema');$statement->execute(['schema'=>$schema]);
$exists=(int)$statement->fetchColumn()===1;
if($exists){
    $table=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema AND TABLE_NAME='_test_schema_ownership'");
    $table->execute(['schema'=>$schema]);
    if((int)$table->fetchColumn()!==1)throw new RuntimeException('소유권 Marker가 없는 Schema는 삭제할 수 없습니다.');
    $marker=$db->query('SELECT test_tool_code,unique_token,cleanup_required,source_script FROM `'.$schema.'`._test_schema_ownership LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if(($marker['test_tool_code']??'')!=='DAILY_EMPLOYMENT_INCOME_RUNTIME'||($marker['unique_token']??'')!==$token
        ||(int)($marker['cleanup_required']??0)!==1||($marker['source_script']??'')!=='test_daily_income_tmp_migrations.php'){
        throw new RuntimeException('소유권 Marker가 Cleanup 요청과 일치하지 않습니다.');
    }
    $db->exec('DROP DATABASE `'.$schema.'`');
}
echo json_encode(['schema'=>$schema,'existed'=>$exists,'cleanup'=>$exists?'DROPPED':'ALREADY_ABSENT'],JSON_UNESCAPED_UNICODE),PHP_EOL;
