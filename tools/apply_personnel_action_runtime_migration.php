<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$mode = $argv[1] ?? 'verify';
$base = PROJECT_ROOT . '/app/migrations/20260804_04_extend_personnel_action_runtime_baseline';

/** @return list<string> */
function statements(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Migration 파일을 읽을 수 없습니다.');
    }
    $delimiter = ';';
    $buffer = '';
    $result = [];
    foreach ($lines as $line) {
        if ($buffer === '' && (trim($line) === '' || str_starts_with(ltrim($line), '--'))) {
            continue;
        }
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match) === 1) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) {
            continue;
        }
        $sql = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($sql !== '') {
            $result[] = $sql;
        }
        $buffer = '';
    }
    if (trim($buffer) !== '') {
        throw new RuntimeException('종료되지 않은 SQL 문장이 있습니다.');
    }
    return $result;
}

function verify(PDO $pdo): array
{
    $tableCounts = [];
    foreach (['institution_job_assignments_department_histories', 'institution_job_assignments_position_histories'] as $table) {
        $tableCounts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    return [
        'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'issued_date' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_personnel_actions' AND COLUMN_NAME='issued_date'")->fetchColumn(),
        'applied_by' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_personnel_actions_targets' AND COLUMN_NAME='applied_by'")->fetchColumn(),
        'counts' => $tableCounts,
        'department_orphans' => (int) $pdo->query('SELECT COUNT(*) FROM institution_job_assignments_department_histories a LEFT JOIN user_employees e ON e.id=a.employee_id LEFT JOIN user_departments d ON d.id=a.department_id WHERE e.id IS NULL OR d.id IS NULL')->fetchColumn(),
        'position_orphans' => (int) $pdo->query('SELECT COUNT(*) FROM institution_job_assignments_position_histories a LEFT JOIN user_employees e ON e.id=a.employee_id LEFT JOIN user_positions p ON p.id=a.position_id WHERE e.id IS NULL OR p.id IS NULL')->fetchColumn(),
        'reversed_periods' => (int) $pdo->query('SELECT (SELECT COUNT(*) FROM institution_job_assignments_department_histories WHERE effective_to < effective_from) + (SELECT COUNT(*) FROM institution_job_assignments_position_histories WHERE effective_to < effective_from)')->fetchColumn(),
        'active_department_mismatch' => (int) $pdo->query("SELECT COUNT(*) FROM user_employees e LEFT JOIN institution_job_assignments_department_histories a ON a.employee_id=e.id AND a.effective_to IS NULL WHERE e.employment_status <> 'RETIRED' AND NOT (e.department_id <=> a.department_id)")->fetchColumn(),
        'active_position_mismatch' => (int) $pdo->query("SELECT COUNT(*) FROM user_employees e LEFT JOIN institution_job_assignments_position_histories a ON a.employee_id=e.id AND a.effective_to IS NULL WHERE e.employment_status <> 'RETIRED' AND NOT (e.position_id <=> a.position_id)")->fetchColumn(),
    ];
}

try {
    if ($mode === 'parse') {
        echo json_encode(['up' => count(statements($base . '.up.sql')), 'down' => count(statements($base . '.down.sql'))], JSON_PRETTY_PRINT), PHP_EOL;
        exit;
    }
    $pdo = DbPdo::conn();
    if ($mode === 'up' || $mode === 'down') {
        foreach (statements($base . '.' . $mode . '.sql') as $sql) {
            $pdo->exec($sql);
        }
    } elseif ($mode !== 'verify') {
        throw new InvalidArgumentException('사용법: php tools/apply_personnel_action_runtime_migration.php [parse|up|verify|down]');
    }
    echo json_encode(verify($pdo), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
