<?php
define('PROJECT_ROOT', getcwd());
require 'core/Database.php';
$pdo = Core\Database::getInstance()->getConnection();
$dbName = $pdo->query("SELECT DATABASE() AS dbname")->fetch(PDO::FETCH_ASSOC)['dbname'] ?? '';
$col = $pdo->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_evidence_bank' AND COLUMN_NAME = 'transaction_type'")->fetch(PDO::FETCH_ASSOC);
$code = $pdo->query("SELECT COUNT(*) AS cnt FROM system_codes WHERE code_group = 'TRANSACTION_TYPE'")->fetch(PDO::FETCH_ASSOC);
echo json_encode([
  'database' => $dbName,
  'ledger_evidence_bank_transaction_type_column_count' => (int)($col['cnt'] ?? 0),
  'transaction_type_code_group_row_count' => (int)($code['cnt'] ?? 0)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
