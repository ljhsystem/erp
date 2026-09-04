<?php

declare(strict_types=1);

use App\Models\System\StatutoryStandardModel;

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$model = (new ReflectionClass(StatutoryStandardModel::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(StatutoryStandardModel::class, 'resolveOrder');
$columns = [
    ['data'=>null, 'name'=>''],
    ['data'=>null, 'name'=>''],
    ['data'=>'sort_no', 'name'=>''],
    ['data'=>'standard_combination_name', 'name'=>'standard_type_code'],
    ['data'=>'effective_from', 'name'=>''],
    ['data'=>'effective_to', 'name'=>''],
    ['data'=>'value_summary', 'name'=>'value_summary'],
    ['data'=>'source_count', 'name'=>''],
    ['data'=>'note', 'name'=>''],
    ['data'=>'created_by', 'name'=>''],
    ['data'=>'created_at', 'name'=>''],
    ['data'=>'updated_by', 'name'=>''],
    ['data'=>'updated_at', 'name'=>''],
    ['data'=>'period_status', 'name'=>'period_status'],
    ['data'=>null, 'name'=>''],
];
$cases = [
    ['standard_type_code','asc',3,'s.standard_type_code','ASC'],
    ['standard_type_code','desc',3,'s.standard_type_code','DESC'],
    ['effective_from','asc',4,'s.effective_from','ASC'],
    ['effective_from','desc',4,'s.effective_from','DESC'],
    ['updated_at','asc',12,'s.updated_at','ASC'],
    ['updated_at','desc',12,'s.updated_at','DESC'],
];
foreach ($cases as [$key,$direction,$index,$sql,$sqlDirection]) {
    [$actualSql,$actualDirection] = $method->invoke($model, [
        'columns'=>$columns,
        'order'=>[['column'=>$index,'dir'=>$direction]],
    ], 'PERIOD_STATUS_SQL');
    if ($actualSql !== $sql || $actualDirection !== $sqlDirection) {
        throw new RuntimeException("{$key}/{$direction} 정렬 매핑이 다릅니다.");
    }
}

$invalidBlocked = 0;
foreach ([
    ['columns'=>$columns,'order'=>[['column'=>99,'dir'=>'asc']]],
    ['columns'=>$columns,'order'=>[['column'=>0,'dir'=>'asc']]],
    ['columns'=>$columns,'order'=>[['column'=>3,'dir'=>'']]],
] as $query) {
    try {
        $method->invoke($model, $query, 'PERIOD_STATUS_SQL');
    } catch (InvalidArgumentException) {
        $invalidBlocked++;
    }
}
if ($invalidBlocked !== 3) throw new RuntimeException('잘못된 정렬값의 조용한 fallback이 남아 있습니다.');

$reorderedColumns = [$columns[0],$columns[1],$columns[6],$columns[12],$columns[3],$columns[4],$columns[13]];
[$reorderedSql,$reorderedDirection] = $method->invoke($model, [
    'columns'=>$reorderedColumns,
    'order'=>[['column'=>4,'dir'=>'desc']],
], 'PERIOD_STATUS_SQL');
if ($reorderedSql !== 's.standard_type_code' || $reorderedDirection !== 'DESC') {
    throw new RuntimeException('가상·숨김·순서변경 후 안정 key 정렬이 유지되지 않습니다.');
}

[$defaultSql,$defaultDirection] = $method->invoke($model, ['columns'=>$columns], 'PERIOD_STATUS_SQL');
if ($defaultSql !== 's.effective_from' || $defaultDirection !== 'DESC') {
    throw new RuntimeException('정렬 설정이 없는 경우의 기존 기본정렬이 변경됐습니다.');
}

echo "법정기준 정렬 key/name/direction 계약: PASS\n";
