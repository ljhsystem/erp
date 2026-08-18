<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Backup\DatabaseBackupService;

$mode = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($mode, ['preflight', 'backup', 'up', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_voucher_feedback_migration.php [preflight|backup|up|verify]\n");
    exit(1);
}

$pdo = Core\DbPdo::conn();

if ($mode === 'backup') {
    $result = (new DatabaseBackupService($pdo))->backupDatabase();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(!empty($result['success']) ? 0 : 1);
}

$snapshot = static function () use ($pdo): array {
    $counts = [];
    foreach (['ledger_vouchers', 'ledger_voucher_lines', 'ledger_journal_learning_events', 'ledger_journal_recent_patterns', 'ledger_journal_client_account_patterns', 'ledger_journal_rules'] as $table) {
        $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    $counts['posted_vouchers'] = (int) $pdo->query("SELECT COUNT(*) FROM ledger_vouchers WHERE status = 'posted'")->fetchColumn();
    $counts['reversal_events'] = (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_learning_events e INNER JOIN ledger_vouchers v ON v.id COLLATE utf8mb4_unicode_ci = e.voucher_id WHERE v.is_reversal = 1")->fetchColumn();
    $counts['excel_permissions'] = (int) $pdo->query("SELECT COUNT(*) FROM auth_permissions WHERE permission_key IN ('api.ledger.voucher.template','api.ledger.voucher.excel','api.ledger.voucher.excel_upload')")->fetchColumn();
    $counts['excel_role_mappings'] = (int) $pdo->query("SELECT COUNT(*) FROM auth_role_permissions rp INNER JOIN auth_permissions p ON p.id = rp.permission_id WHERE p.permission_key IN ('api.ledger.voucher.template','api.ledger.voucher.excel','api.ledger.voucher.excel_upload')")->fetchColumn();
    $counts['excel_user_mappings'] = (int) $pdo->query("SELECT COUNT(*) FROM auth_user_permissions up INNER JOIN auth_permissions p ON p.id = up.permission_id WHERE p.permission_key IN ('api.ledger.voucher.template','api.ledger.voucher.excel','api.ledger.voucher.excel_upload')")->fetchColumn();
    $counts['excel_settings'] = (int) $pdo->query("SELECT COUNT(*) FROM system_user_settings WHERE page_key IN ('ledger.voucher','ledger.vouchers') AND setting_type IN ('EXCEL_UPLOAD','EXCEL_DOWNLOAD')")->fetchColumn();
    return $counts;
};

if ($mode === 'preflight') {
    $integrity = [
        'orphan_event_vouchers' => (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_learning_events e LEFT JOIN ledger_vouchers v ON v.id COLLATE utf8mb4_unicode_ci = e.voucher_id WHERE e.voucher_id IS NOT NULL AND e.voucher_id <> '' AND v.id IS NULL")->fetchColumn(),
        'legacy_events_without_safe_line_match' => (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_learning_events WHERE voucher_line_id IS NULL")->fetchColumn(),
    ];
    echo json_encode(['snapshot' => $snapshot(), 'integrity' => $integrity], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

if ($mode === 'up') {
    foreach (['20260815_05_add_journal_feedback_integrity.up.sql', '20260815_06_remove_voucher_excel_permissions.up.sql'] as $file) {
        $sql = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $file);
        $delimiter = ';';
        $buffer = '';
        foreach (preg_split('/\R/', $sql) as $line) {
            if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
                $delimiter = $match[1];
                continue;
            }
            $buffer .= $line . "\n";
            if (str_ends_with(rtrim($buffer), $delimiter)) {
                $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
                if ($statement !== '') $pdo->exec($statement);
                $buffer = '';
            }
        }
        echo $file . ": OK\n";
    }
}

$columns = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND ((TABLE_NAME='ledger_journal_learning_events' AND COLUMN_NAME='event_type') OR (TABLE_NAME='ledger_voucher_lines' AND COLUMN_NAME IN ('recommended_account_id','recommended_line_type','recommended_amount')) OR (TABLE_NAME IN ('ledger_journal_recent_patterns','ledger_journal_client_account_patterns') AND COLUMN_NAME LIKE 'legacy_%')) ORDER BY TABLE_NAME,COLUMN_NAME")->fetchAll(PDO::FETCH_ASSOC);
$duplicates = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT voucher_line_id,event_type,COUNT(*) c FROM ledger_journal_learning_events WHERE voucher_line_id IS NOT NULL AND event_type IS NOT NULL GROUP BY voucher_line_id,event_type HAVING c>1) d")->fetchColumn();
$integrity = [
    'duplicate_event_keys' => $duplicates,
    'new_event_orphan_lines' => (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_learning_events e LEFT JOIN ledger_voucher_lines l ON l.id = e.voucher_line_id WHERE e.event_type='POSTED_CONFIRMATION' AND l.id IS NULL")->fetchColumn(),
    'new_event_invalid_accounts' => (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_learning_events e LEFT JOIN ledger_accounts a ON a.id COLLATE utf8mb4_unicode_ci = e.final_account_id WHERE e.event_type='POSTED_CONFIRMATION' AND a.id IS NULL")->fetchColumn(),
    'new_reversal_events' => (int) $pdo->query("SELECT COUNT(*) FROM ledger_journal_learning_events e INNER JOIN ledger_vouchers v ON v.id COLLATE utf8mb4_unicode_ci = e.voucher_id WHERE e.event_type='POSTED_CONFIRMATION' AND v.is_reversal=1")->fetchColumn(),
    'recent_duplicate_keys' => (int) $pdo->query("SELECT COUNT(*) FROM (SELECT pattern_hash,COUNT(*) c FROM ledger_journal_recent_patterns GROUP BY pattern_hash HAVING c>1) d")->fetchColumn(),
    'client_duplicate_keys' => (int) $pdo->query("SELECT COUNT(*) FROM (SELECT client_id,transaction_direction,line_type,account_id,COUNT(*) c FROM ledger_journal_client_account_patterns GROUP BY client_id,transaction_direction,line_type,account_id HAVING c>1) d")->fetchColumn(),
];
echo json_encode(['snapshot' => $snapshot(), 'columns' => $columns, 'integrity' => $integrity], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
