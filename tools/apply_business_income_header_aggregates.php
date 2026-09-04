<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$mode = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($mode, ['preflight', 'up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_business_income_header_aggregates.php [preflight|up|verify]');
}

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$migration = PROJECT_ROOT . '/app/migrations/20260903_15_add_business_income_header_aggregates.up.sql';
$aggregateColumns = [
    'group_count',
    'item_count',
    'total_gross_payment_amount',
    'total_income_tax_amount',
    'total_local_income_tax_amount',
    'total_other_deduction_amount',
    'total_deduction_amount',
    'total_net_payment_amount',
];

$scalar = static fn(PDO $connection, string $sql): int => (int) $connection->query($sql)->fetchColumn();
$snapshot = static function (PDO $connection) use ($aggregateColumns, $scalar): array {
    $quotedColumns = implode(',', array_map([$connection, 'quote'], $aggregateColumns));
    $columnRows = $connection->query(
        "SELECT COLUMN_NAME,COLUMN_COMMENT,ORDINAL_POSITION FROM information_schema.COLUMNS "
        . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_incomes' "
        . "AND COLUMN_NAME IN ($quotedColumns) ORDER BY ORDINAL_POSITION"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $checks = $connection->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS "
        . "WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_incomes' "
        . "AND CONSTRAINT_TYPE='CHECK' AND CONSTRAINT_NAME LIKE 'chk_business_income_header_%' ORDER BY CONSTRAINT_NAME"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    return [
        'headers' => $scalar($connection, 'SELECT COUNT(*) FROM institution_business_incomes'),
        'groups' => $scalar($connection, 'SELECT COUNT(*) FROM institution_business_income_groups'),
        'items' => $scalar($connection, 'SELECT COUNT(*) FROM institution_business_income_items'),
        'physical_columns' => $scalar($connection, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_business_incomes'"),
        'aggregate_columns' => $columnRows,
        'checks' => $checks,
        'triggers' => $scalar($connection, 'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()'),
    ];
};
$execute = static function (PDO $connection, string $path): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $connection->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};

$before = $snapshot($db);
if ($mode === 'preflight' && $before['aggregate_columns'] !== []) {
    throw new RuntimeException('사업소득 Header 집계컬럼이 이미 존재합니다.');
}
if ($mode === 'up') {
    if ($before['aggregate_columns'] !== []) throw new RuntimeException('이미 적용된 Migration입니다.');
    $execute($db, $migration);
}
$after = $snapshot($db);

if ($mode !== 'preflight') {
    if (count($after['aggregate_columns']) !== count($aggregateColumns)) throw new RuntimeException('사업소득 Header 집계컬럼이 완전하지 않습니다.');
    if (count($after['checks']) !== 3) throw new RuntimeException('사업소득 Header CHECK 제약이 완전하지 않습니다.');
    if ($before['triggers'] !== $after['triggers']) throw new RuntimeException('Trigger 수가 변경됐습니다.');
}

echo json_encode([
    'success' => true,
    'mode' => $mode,
    'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
    'mariadb_version' => (string) $db->query('SELECT VERSION()')->fetchColumn(),
    'migration' => basename($migration),
    'sql_sha256' => hash_file('sha256', $migration),
    'before' => $before,
    'after' => $after,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
