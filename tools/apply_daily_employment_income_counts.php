<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$mode = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($mode, ['up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_daily_employment_income_counts.php [up|verify]');
}

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$lockName = 'erp:daily-employment-income:physical-counts';
$locked = (int) $db->query('SELECT GET_LOCK(' . $db->quote($lockName) . ', 10)')->fetchColumn() === 1;
if (!$locked) throw new RuntimeException('일용근로소득 집계컬럼 Migration 잠금을 획득하지 못했습니다.');

try {
    $before = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn();
    if ($mode === 'up') {
        $sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260827_06_add_daily_employment_income_counts.up.sql');
        if (!preg_match('/DELIMITER \$\$(.*?)\$\$\s*DELIMITER ;/s', $sql, $matches)) {
            throw new RuntimeException('일용근로소득 집계컬럼 Migration 구문을 해석할 수 없습니다.');
        }
        foreach (preg_split('/\$\$\s*/', trim($matches[1])) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement !== '') $db->exec($statement);
        }
        $tail = preg_replace('/.*?DELIMITER ;/s', '', $sql, 1);
        foreach (array_filter(array_map('trim', explode(';', (string) $tail))) as $statement) $db->exec($statement);
    }

    $after = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_incomes')->fetchColumn();
    $columns = (int) $db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_incomes'
           AND COLUMN_NAME IN ('worker_count','work_team_count')"
    )->fetchColumn();
    $mismatches = $columns === 2 ? (int) $db->query(
        'SELECT COUNT(*) FROM institution_daily_employment_incomes h
         WHERE h.worker_count<>(SELECT COUNT(DISTINCT i.worker_client_id) FROM institution_daily_employment_income_items i WHERE i.daily_employment_income_id=h.id)
            OR h.work_team_count<>(SELECT COUNT(DISTINCT i.work_team_id) FROM institution_daily_employment_income_items i WHERE i.daily_employment_income_id=h.id)'
    )->fetchColumn() : -1;
    if ($before !== $after || $columns !== 2 || $mismatches !== 0) {
        throw new RuntimeException('일용근로소득 집계컬럼 Migration 검증에 실패했습니다.');
    }
    echo json_encode(['success'=>true,'mode'=>$mode,'before_count'=>$before,'after_count'=>$after,'physical_columns'=>$columns,'aggregate_mismatches'=>$mismatches], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} finally {
    $db->query('SELECT RELEASE_LOCK(' . $db->quote($lockName) . ')');
}
