<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$pdo = Core\DbPdo::conn();
$tables = [
    'ledger_journal_learning_events',
    'ledger_journal_recent_patterns',
    'ledger_journal_client_account_patterns',
    'ledger_vouchers',
    'ledger_voucher_lines',
    'ledger_evidence_links',
    'auth_permissions',
    'auth_role_permissions',
    'auth_user_permissions',
    'system_user_settings',
];

foreach ($tables as $table) {
    echo "=== {$table} ===\n";
    try {
        $statement = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $statement->fetch(PDO::FETCH_NUM);
        echo ($row[1] ?? 'MISSING') . "\n";
    } catch (Throwable $exception) {
        echo 'ERROR: ' . $exception->getMessage() . "\n";
    }
}

foreach ([
    'ledger_vouchers',
    'ledger_voucher_lines',
    'ledger_journal_learning_events',
    'ledger_journal_recent_patterns',
    'ledger_journal_client_account_patterns',
    'ledger_journal_rules',
] as $table) {
    echo $table . '=' . $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() . "\n";
}

$events = $pdo->query('SELECT * FROM ledger_journal_learning_events ORDER BY created_at, id')->fetchAll(PDO::FETCH_ASSOC);
echo 'events=' . json_encode($events, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

foreach (['ledger_journal_recent_patterns', 'ledger_journal_client_account_patterns'] as $table) {
    $rows = $pdo->query("SELECT * FROM `{$table}` ORDER BY created_at, id")->fetchAll(PDO::FETCH_ASSOC);
    echo $table . '=' . json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

$legacyMatches = $pdo->query("SELECT e.id AS event_id, e.voucher_id, e.line_no, e.final_account_id,
        e.final_line_type, e.final_amount, l.id AS matched_line_id,
        l.account_id, l.debit, l.credit
    FROM ledger_journal_learning_events e
    LEFT JOIN ledger_voucher_lines l
      ON l.voucher_id COLLATE utf8mb4_unicode_ci = e.voucher_id
     AND l.line_no = e.line_no
     AND l.account_id COLLATE utf8mb4_unicode_ci = e.final_account_id
     AND CASE WHEN e.final_line_type = 'DEBIT' THEN l.debit ELSE l.credit END = e.final_amount
    ORDER BY e.created_at, e.id")->fetchAll(PDO::FETCH_ASSOC);
echo 'legacy_matches=' . json_encode($legacyMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
