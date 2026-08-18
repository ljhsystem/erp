<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$direction = $argv[1] ?? 'up';
if (!in_array($direction, ['up', 'down', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_employment_weekly_holiday_migration.php [up|down|verify]\n");
    exit(1);
}

$pdo = DbPdo::conn();
if ($direction !== 'verify') {
    $path = PROJECT_ROOT . '/app/migrations/20260801_02_promote_weekly_holiday_schedule_ssot.' . $direction . '.sql';
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Migration SQL을 읽을 수 없습니다: ' . $path);
    }
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->exec($sql);
}

$verification = $pdo->query(
    "SELECT c.id, c.contract_no, c.weekly_holiday_day,
            SUM(s.day_type = 'WEEKLY_HOLIDAY') AS weekly_holiday_count,
            MAX(CASE WHEN s.day_type = 'WEEKLY_HOLIDAY' THEN s.day_of_week END) AS schedule_holiday_day
     FROM institution_employment_contracts c
     LEFT JOIN institution_employment_contracts_weekly_schedules s ON s.contract_id = c.id
     WHERE c.work_schedule_type IN ('NORMAL', 'NIGHT')
     GROUP BY c.id, c.contract_no, c.weekly_holiday_day
     ORDER BY c.contract_no"
)->fetchAll(PDO::FETCH_ASSOC);

echo "employment weekly holiday schedule SSOT migration {$direction}: OK\n";
$schemaVerification = [
    'columns' => $pdo->query(
        "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_COMMENT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'institution_employment_contracts_weekly_schedules'
         ORDER BY ORDINAL_POSITION"
    )->fetchAll(PDO::FETCH_ASSOC),
    'check_clause' => $pdo->query(
        "SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND CONSTRAINT_NAME = 'chk_contract_weekly_schedule_type'"
    )->fetchColumn(),
];
echo json_encode(
    ['contracts' => $verification, 'schema' => $schemaVerification],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
), "\n";