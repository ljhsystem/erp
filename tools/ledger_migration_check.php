<?php
declare(strict_types=1);

// Read-only migration status checker for ledger legacy/new tables.
// Usage: php tools/ledger_migration_check.php

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

require_once PROJECT_ROOT . '/core/Database.php';
require_once PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$legacyTables = [
    'ledger_data_seed_batches',
    'ledger_data_seed_rows',
    'ledger_seed_batches',
    'ledger_seed_rows',
    'ledger_data_evidences',
    'ledger_data_evidence_links',
    'ledger_processing_items',
    'ledger_processing_item_actions',
    'ledger_bank_transactions',
];

$newTables = [
    'ledger_evidence_bank',
    'ledger_evidence_tax_invoice',
    'ledger_evidence_tax_invoice_items',
    'ledger_evidence_cash_receipt',
    'ledger_evidence_card_purchase',
    'ledger_evidence_links',
    'ledger_evidence_processing',
    'ledger_evidence_processing_logs',
];

function tableExists(\PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    $stmt->execute([':table_name' => $table]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function tableCount(\PDO $pdo, string $table): int
{
    $safeTable = str_replace('`', '``', $table);
    $sql = "SELECT COUNT(*) FROM `{$safeTable}`";
    return (int) $pdo->query($sql)->fetchColumn();
}

try {
    $pdo = DbPdo::conn();
} catch (\Throwable $e) {
    fwrite(STDERR, "DB CONNECT ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "[LEGACY TABLE COUNTS]" . PHP_EOL;
foreach ($legacyTables as $table) {
    if (!tableExists($pdo, $table)) {
        echo "{$table} : NOT EXISTS" . PHP_EOL;
        continue;
    }
    echo "{$table} : " . tableCount($pdo, $table) . PHP_EOL;
}

echo PHP_EOL . "[NEW TABLE COUNTS]" . PHP_EOL;
foreach ($newTables as $table) {
    if (!tableExists($pdo, $table)) {
        echo "{$table} : NOT EXISTS" . PHP_EOL;
        continue;
    }
    echo "{$table} : " . tableCount($pdo, $table) . PHP_EOL;
}
