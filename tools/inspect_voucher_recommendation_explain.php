<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$pdo = Core\DbPdo::conn();
$queries = [
    'rules' => "SELECT id FROM ledger_journal_rules WHERE deleted_at IS NULL AND is_active=1 AND business_unit='HQ' AND operation_type='GENERAL' AND transaction_direction='OUT' AND import_type='BANK_TRANSACTION' AND (client_type='' OR client_type IS NULL)",
    'recent' => "SELECT id FROM ledger_journal_recent_patterns WHERE transaction_direction='OUT' ORDER BY usage_count DESC,last_used_at DESC LIMIT 30",
    'client' => "SELECT id FROM ledger_journal_client_account_patterns WHERE client_id='none' AND transaction_direction='OUT' ORDER BY recent_score DESC,usage_count DESC,last_used_at DESC LIMIT 20",
    'learning' => "SELECT voucher_id,COUNT(*) FROM ledger_journal_learning_events WHERE transaction_direction='OUT' AND import_type='BANK_TRANSACTION' AND (failure_type IS NULL OR failure_type='') GROUP BY voucher_id",
];
foreach ($queries as $name => $sql) {
    echo $name . ':' . $pdo->query('EXPLAIN FORMAT=JSON ' . $sql)->fetchColumn() . PHP_EOL;
}

$settings = $pdo->query("SELECT page_key, setting_type, COUNT(*) AS row_count
    FROM system_user_settings
    WHERE page_key LIKE 'ledger.voucher%'
      AND setting_type IN ('EXCEL_UPLOAD', 'EXCEL_DOWNLOAD')
    GROUP BY page_key, setting_type")->fetchAll(PDO::FETCH_ASSOC);
echo 'excel_settings:' . json_encode($settings, JSON_UNESCAPED_UNICODE) . PHP_EOL;
