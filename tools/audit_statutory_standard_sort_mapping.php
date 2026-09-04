<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Models\System\StatutoryStandardModel;

$db = Core\Database::getInstance()->getConnection();
$model = new StatutoryStandardModel($db);
$settingStatement = $db->prepare(
    "SELECT setting_type,settings_json FROM system_user_settings"
    . " WHERE page_key='dashboard.settings.statutory-standards'"
    . " AND setting_type='VIEW' ORDER BY updated_at DESC"
);
$settingStatement->execute();
$savedSortSettings = [];
foreach ($settingStatement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $settingRow) {
    $settingData = json_decode((string)$settingRow['settings_json'], true);
    $savedSortSettings[] = (array)($settingData['sortSettings'] ?? []);
}
$columns = [
    ['data'=>null,'name'=>''],
    ['data'=>null,'name'=>''],
    ['data'=>'sort_no','name'=>''],
    ['data'=>'standard_combination_name','name'=>'standard_type_code'],
    ['data'=>'effective_from','name'=>''],
    ['data'=>'effective_to','name'=>''],
    ['data'=>'value_summary','name'=>'value_summary'],
    ['data'=>'source_count','name'=>''],
    ['data'=>'note','name'=>''],
    ['data'=>'created_by','name'=>''],
    ['data'=>'created_at','name'=>''],
    ['data'=>'updated_by','name'=>''],
    ['data'=>'updated_at','name'=>''],
    ['data'=>'period_status','name'=>'period_status'],
    ['data'=>null,'name'=>''],
];
$cases = [
    ['standard_type_code','asc',3,'s.standard_type_code'],
    ['standard_type_code','desc',3,'s.standard_type_code'],
    ['effective_from','asc',4,'s.effective_from'],
    ['effective_from','desc',4,'s.effective_from'],
    ['updated_at','asc',12,'s.updated_at'],
    ['updated_at','desc',12,'s.updated_at'],
];
$results = [];
foreach ($cases as [$key,$direction,$index,$sqlField]) {
    $page = $model->page([
        'start'=>0,'length'=>20,'filters'=>'[]','search'=>['value'=>''],
        'columns'=>$columns,'order'=>[['column'=>$index,'dir'=>$direction]],
    ]);
    $actualIds = array_column($page['rows'], 'id');
    $expectedIds = $db->query(
        "SELECT s.id FROM system_statutory_standards s"
        . " JOIN system_codes c ON c.code_group='STATUTORY_STANDARD_TYPE'"
        . " AND c.code=s.standard_type_code AND c.is_active=1"
        . ' ORDER BY ' . $sqlField . ' ' . strtoupper($direction) . ',s.sort_no DESC,s.id DESC LIMIT 20'
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($actualIds !== $expectedIds) throw new RuntimeException("{$key}/{$direction} 실제 SQL 정렬 결과가 다릅니다.");
    $results[] = [
        'table_settings'=>['key'=>$key,'direction'=>$direction],
        'datatable'=>['index'=>$index,'data'=>$columns[$index]['data'],'name'=>$columns[$index]['name']],
        'api'=>['field'=>$columns[$index]['name'] ?: $columns[$index]['data'],'direction'=>$direction],
        'sql'=>'ORDER BY ' . $sqlField . ' ' . strtoupper($direction) . ', s.sort_no DESC, s.id DESC',
        'first_ids'=>array_slice($actualIds,0,3),
    ];
}

echo json_encode([
    'read_only'=>true,
    'database'=>$db->query('SELECT DATABASE()')->fetchColumn(),
    'saved_view_sort_settings'=>$savedSortSettings,
    'results'=>$results,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
