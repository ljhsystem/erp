<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$columnExists = static function (string $table, string $column) use ($db): bool {
    $statement = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS'
        . ' WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND COLUMN_NAME=:column_name'
    );
    $statement->execute([':table_name'=>$table,':column_name'=>$column]);
    return (int)$statement->fetchColumn() === 1;
};

$targets = [
    ['system_projects','business_unit'],
    ['institution_daily_employment_income_items','work_team_scope_key'],
    ['institution_social_insurance_workplaces','business_unit'],
];
$applied = array_map(static fn(array $target): bool => $columnExists($target[0], $target[1]), $targets);
if (count(array_filter($applied)) !== 0 && count(array_filter($applied)) !== count($targets)) {
    throw new RuntimeException('일용근로소득 그룹 SSOT Migration이 일부만 적용된 상태입니다.');
}
if (!in_array(false, $applied, true)) {
    echo "이미 적용된 Migration입니다.\n";
} else {
    $sql=file_get_contents(PROJECT_ROOT.'/app/migrations/20260827_09_align_daily_income_group_ssot.up.sql');
    if ($sql===false || trim($sql)==='') throw new RuntimeException('Migration 파일을 읽을 수 없습니다.');
    $db->exec($sql);
}

$result = [
    'business_unit_policies'=>$db->query("SELECT code,code_name,JSON_EXTRACT(extra_data,'$.daily_employment_income') policy FROM system_codes WHERE code_group='BUSINESS_UNIT' ORDER BY sort_no,code")->fetchAll(PDO::FETCH_ASSOC)?:[],
    'project_business_units'=>$db->query('SELECT business_unit,COUNT(*) row_count FROM system_projects GROUP BY business_unit ORDER BY business_unit')->fetchAll(PDO::FETCH_ASSOC)?:[],
    'item_columns'=>array_values(array_filter(['business_unit','scope_project_key','work_team_scope_key'],static fn(string $column):bool=>$columnExists('institution_daily_employment_income_items',$column))),
    'work_team_nullable'=>(string)$db->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_items' AND COLUMN_NAME='work_team_id'")->fetchColumn(),
    'insurance_business_unit_column'=>$columnExists('institution_social_insurance_workplaces','business_unit'),
];
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),PHP_EOL;
