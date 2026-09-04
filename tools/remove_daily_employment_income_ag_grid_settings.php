<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$params = [':page_key'=>'institution.income-data.daily-employment', ':setting_type'=>'VIEW'];
$count = $db->prepare('SELECT COUNT(*) FROM system_user_settings WHERE page_key=:page_key AND setting_type=:setting_type');
$count->execute($params);
$before = (int) $count->fetchColumn();
$delete = $db->prepare('DELETE FROM system_user_settings WHERE page_key=:page_key AND setting_type=:setting_type');
$delete->execute($params);
$count->execute($params);
$after = (int) $count->fetchColumn();
if ($after !== 0) throw new RuntimeException('일용근로소득 AG Grid 사용자설정이 남아 있습니다.');
echo json_encode(['migration'=>'20260827_13_remove_daily_income_ag_grid_table_settings','deleted_count'=>$before,'remaining_count'=>$after], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), PHP_EOL;
