<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT',dirname(__DIR__));require PROJECT_ROOT.'/vendor/autoload.php';
$db=DbPdo::conn();$db->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
$exists=static function(string $table,string $column='')use($db):bool{$sql='SELECT COUNT(*) FROM information_schema.'.($column===''?'TABLES':'COLUMNS').' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'.($column===''?'':' AND COLUMN_NAME=:column_name');$statement=$db->prepare($sql);$params=[':table_name'=>$table];if($column!=='')$params[':column_name']=$column;$statement->execute($params);return(int)$statement->fetchColumn()===1;};
if($exists('institution_daily_employment_income_groups')&&$exists('institution_daily_employment_income_items','daily_employment_income_group_id')){echo "이미 적용된 Migration입니다.\n";exit(0);}
if($exists('institution_daily_employment_income_groups')||$exists('institution_daily_employment_income_items','daily_employment_income_group_id'))throw new RuntimeException('근무그룹 Migration이 일부 적용된 상태입니다.');
$before=['headers'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn(),'items'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_items')->fetchColumn(),'workdays'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_workdays')->fetchColumn(),'lines'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn()];
$sql=(string)file_get_contents(PROJECT_ROOT.'/app/migrations/20260827_10_create_daily_employment_income_groups.up.sql');
$parts=preg_split('/DELIMITER \$\$|\$\$\s*DELIMITER ;/',$sql);if(!is_array($parts)||count($parts)!==3)throw new RuntimeException('Migration 구문을 해석할 수 없습니다.');
foreach(array_filter(array_map('trim',explode(';',$parts[0])))as$statement)$db->exec($statement);
$db->exec(trim($parts[1]));
foreach(array_filter(array_map('trim',explode(';',$parts[2])))as$statement)$db->exec($statement);
$after=['headers'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn(),'groups'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_groups')->fetchColumn(),'items'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_items')->fetchColumn(),'workdays'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_workdays')->fetchColumn(),'lines'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn(),'unlinked_items'=>(int)$db->query('SELECT COUNT(*) FROM institution_daily_employment_income_items WHERE daily_employment_income_group_id IS NULL')->fetchColumn()];
foreach(['headers','items','workdays','lines']as$key)if($before[$key]!==$after[$key])throw new RuntimeException('Migration 전후 원천 건수가 일치하지 않습니다: '.$key);
echo json_encode(['migration'=>'20260827_10_create_daily_employment_income_groups','before'=>$before,'after'=>$after],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
