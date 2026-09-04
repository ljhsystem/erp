<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$mode = strtolower((string) ($argv[1] ?? 'preflight'));
if (!in_array($mode, ['preflight', 'up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_daily_income_line_backfill_audit_detachment.php [preflight|up|verify]');
}

$db = Core\Database::getInstance()->getConnection();
$documentId = 'e8650425-ef60-4bbb-bd5e-88deeeff7f48';
$itemId = '0900efdb-a7cc-4bb9-9ee1-5f47dd8eb2d8';
$migration = PROJECT_ROOT . '/app/migrations/20260829_09_detach_daily_income_line_backfill_audit_from_mutable_lines.up.sql';
$hashRows = static function (PDO $connection, string $sql, array $params = []): string {
    $statement = $connection->prepare($sql);
    $statement->execute($params);
    return hash('sha256', json_encode($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};
$state = static function () use ($db, $documentId, $itemId, $hashRows): array {
    $auditSql = 'SELECT id,migration_id,daily_employment_income_line_id,previous_snapshot,new_snapshot,decision_rule_code,decision_basis_id,payload_hash,verification_status_code,executed_at,executed_by FROM institution_daily_employment_income_line_backfill_audits ORDER BY id';
    $lineSql = 'SELECT id,daily_employment_income_item_id,daily_employment_income_workday_id,line_type_code,line_code,calculated_amount,final_amount,application_status_code FROM institution_daily_employment_income_lines ORDER BY id';
    $target = $db->prepare('SELECT h.status_code,h.total_gross_amount,h.total_deduction_amount,h.total_net_payment_amount,i.total_work_days,i.total_gross_amount AS item_gross_amount,i.total_deduction_amount AS item_deduction_amount,i.total_net_payment_amount AS item_net_amount FROM institution_daily_employment_incomes h JOIN institution_daily_employment_income_groups g ON g.daily_employment_income_id=h.id JOIN institution_daily_employment_income_items i ON i.daily_employment_income_group_id=g.id WHERE h.id=:document AND i.id=:item');
    $target->execute(['document' => $documentId, 'item' => $itemId]);
    return [
        'database' => $db->query('SELECT DATABASE()')->fetchColumn(),
        'version' => $db->query('SELECT VERSION()')->fetchColumn(),
        'fk_count' => (int) $db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_income_line_backfill_audits' AND CONSTRAINT_NAME='fk_daily_income_line_backfill_line' AND REFERENCED_TABLE_NAME='institution_daily_employment_income_lines' AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='CASCADE'")->fetchColumn(),
        'audit_count' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_line_backfill_audits')->fetchColumn(),
        'audit_hash' => $hashRows($db, $auditSql),
        'line_count' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn(),
        'line_hash' => $hashRows($db, $lineSql),
        'calculation_revision_count' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_calculation_revisions')->fetchColumn(),
        'calculation_result_count' => (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_calculation_results')->fetchColumn(),
        'target' => $target->fetch(PDO::FETCH_ASSOC) ?: null,
    ];
};
$executeSql = static function (PDO $connection, string $path): void {
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

$before = $state();
$expectedTarget = [
    'status_code' => 'DRAFT',
    'total_gross_amount' => '452940.00',
    'total_deduction_amount' => '2940.00',
    'total_net_payment_amount' => '450000.00',
    'total_work_days' => '5.00',
    'item_gross_amount' => '452940.00',
    'item_deduction_amount' => '2940.00',
    'item_net_amount' => '450000.00',
];
$ready = $before['database'] === 'sukhyang'
    && str_starts_with((string) $before['version'], '10.11.11-MariaDB')
    && $before['fk_count'] === 1
    && $before['audit_count'] === 32
    && $before['line_count'] === 32
    && $before['calculation_revision_count'] === 0
    && $before['calculation_result_count'] === 0
    && $before['target'] === $expectedTarget;
if ($mode === 'up' && !$ready) throw new RuntimeException('운영 Preflight가 일치하지 않아 Migration을 적용하지 않았습니다.');
if ($mode === 'up') $executeSql($db, $migration);
$after = $state();
if (in_array($mode, ['up', 'verify'], true)) {
    if ($after['fk_count'] !== 0
        || $before['audit_count'] !== $after['audit_count']
        || $before['audit_hash'] !== $after['audit_hash']
        || $before['line_count'] !== $after['line_count']
        || $before['line_hash'] !== $after['line_hash']
        || $before['target'] !== $after['target']) {
        throw new RuntimeException('Migration 적용 후 FK 또는 업무자료 불변성 검증에 실패했습니다.');
    }
}
echo json_encode(['success' => true, 'mode' => $mode, 'ready' => $ready, 'before' => $before, 'after' => $after], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
