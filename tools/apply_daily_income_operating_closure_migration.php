<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$allowed = [
    '20260831_09_close_daily_employment_income_approval_accounting.up.sql',
    '20260831_10_widen_daily_income_line_application_status.up.sql',
    '20260831_11_close_daily_calculation_result_group_worker_grain.up.sql',
];
$fileName = (string) ($argv[1] ?? '');
if (!in_array($fileName, $allowed, true)) {
    throw new InvalidArgumentException('승인된 운영 Migration 파일명을 지정해야 합니다.');
}

$db = DbPdo::conn();
$database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$version = (string) $db->query('SELECT VERSION()')->fetchColumn();
if ($database !== 'sukhyang' || !str_starts_with($version, '10.11.11')) {
    throw new RuntimeException('승인된 운영 DB 또는 MariaDB 버전과 일치하지 않습니다.');
}

$path = PROJECT_ROOT . '/app/migrations/' . $fileName;
$sql = file_get_contents($path);
if ($sql === false) {
    throw new RuntimeException('Migration SQL을 읽을 수 없습니다.');
}

$startedAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
$started = hrtime(true);
$delimiter = ';';
$buffer = '';
$statementCount = 0;

try {
    foreach (preg_split('/\r\n|\n|\r/', $sql) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $matches)) {
            $delimiter = $matches[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) {
            continue;
        }
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') {
            $db->exec($statement);
            $statementCount++;
        }
        $buffer = '';
    }
    if (trim($buffer) !== '') {
        throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
    }
} catch (Throwable $exception) {
    $endedAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
    echo json_encode([
        'success' => false,
        'migration' => $fileName,
        'database' => $database,
        'mariadb_version' => $version,
        'started_at' => $startedAt->format(DATE_ATOM),
        'ended_at' => $endedAt->format(DATE_ATOM),
        'elapsed_ms' => round((hrtime(true) - $started) / 1_000_000, 3),
        'executed_statement_count' => $statementCount,
        'error' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

$endedAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
echo json_encode([
    'success' => true,
    'migration' => $fileName,
    'database' => $database,
    'mariadb_version' => $version,
    'started_at' => $startedAt->format(DATE_ATOM),
    'ended_at' => $endedAt->format(DATE_ATOM),
    'elapsed_ms' => round((hrtime(true) - $started) / 1_000_000, 3),
    'executed_statement_count' => $statementCount,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
