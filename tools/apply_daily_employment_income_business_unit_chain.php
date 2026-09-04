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
    $statement->execute([':table_name' => $table, ':column_name' => $column]);
    return (int) $statement->fetchColumn() === 1;
};

if ($columnExists('system_work_teams', 'business_unit')
    && $columnExists('institution_daily_employment_income_items', 'business_unit')) {
    echo "이미 적용된 Migration입니다.\n";
    exit(0);
}
if ($columnExists('system_work_teams', 'business_unit')
    || $columnExists('institution_daily_employment_income_items', 'business_unit')) {
    throw new RuntimeException('사업구분 Migration이 일부만 적용된 상태입니다. 수동 점검이 필요합니다.');
}

$sql = file_get_contents(PROJECT_ROOT . '/app/migrations/20260827_08_add_daily_income_business_unit_chain.up.sql');
if ($sql === false || trim($sql) === '') {
    throw new RuntimeException('Migration 파일을 읽을 수 없습니다.');
}
$db->exec($sql);

$result = [
    'team_business_units' => $db->query(
        'SELECT business_unit, COUNT(*) AS row_count FROM system_work_teams GROUP BY business_unit ORDER BY business_unit'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'item_business_unit_column' => $columnExists('institution_daily_employment_income_items', 'business_unit'),
    'assignment_nullable' => (string) $db->query(
        "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()"
        . " AND TABLE_NAME='institution_daily_employment_income_workdays' AND COLUMN_NAME='work_team_assignment_id'"
    )->fetchColumn(),
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
