<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;
$db = DbPdo::conn();
$transactionId = '2d315f38-bfa7-4ca6-8d6d-fb9bbaa50b7c';
$rows = static function (string $sql, array $params = []) use ($db): array {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
};

$transaction = $rows('SELECT * FROM ledger_transactions WHERE id=:id', [':id' => $transactionId]);
$items = $rows('SELECT * FROM ledger_transaction_items WHERE transaction_id=:id ORDER BY sort_no,id', [':id' => $transactionId]);
$settlements = $rows('SELECT * FROM ledger_transaction_settlements WHERE transaction_id=:id ORDER BY sort_no,id', [':id' => $transactionId]);
$evidenceLinks = $rows("SELECT * FROM ledger_evidence_links WHERE target_type='TRANSACTION' AND target_id=:id ORDER BY id", [':id' => $transactionId]);
$accountingRegistry = $rows('SELECT * FROM institution_daily_employment_income_accounting_links WHERE transaction_id=:id ORDER BY id', [':id' => $transactionId]);
$possibleLinkTables = $rows(
    "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()"
    . " AND COLUMN_NAME IN('transaction_id','source_transaction_id')"
    . " AND (TABLE_NAME LIKE 'ledger_journal%' OR TABLE_NAME LIKE '%voucher%' OR TABLE_NAME LIKE '%payment%')"
    . ' ORDER BY TABLE_NAME,COLUMN_NAME'
);
$downstream = [];
foreach ($possibleLinkTables as $linkTable) {
    $table = (string) $linkTable['TABLE_NAME'];
    $column = (string) $linkTable['COLUMN_NAME'];
    $downstream[$table . '.' . $column] = $rows(
        "SELECT * FROM `{$table}` WHERE `{$column}`=:id LIMIT 20",
        [':id' => $transactionId]
    );
}

echo json_encode([
    'read_only' => true,
    'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
    'tmp_schema_count' => (int) $db->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'tmp_daily_income_approval_%'")->fetchColumn(),
    'transaction' => $transaction,
    'items' => $items,
    'settlements' => $settlements,
    'evidence_links' => $evidenceLinks,
    'accounting_registry' => $accountingRegistry,
    'downstream_links' => $downstream,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
