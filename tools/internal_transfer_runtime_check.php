<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\Funds\BankTransactionReportModel;
use App\Repositories\Funds\InternalTransferRepository;
use App\Services\Funds\BankTransactionReportService;
use App\Services\Funds\DailyFundsReportService;
use Core\DbPdo;

$pdo = DbPdo::conn();
$tableStatement = $pdo->query("
    SELECT COUNT(*) AS table_count, MAX(CREATE_TIME) AS created_at
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'ledger_fund_transfer_links'
");
$table = $tableStatement->fetch(PDO::FETCH_ASSOC) ?: [];
$tableExists = (int) ($table['table_count'] ?? 0) === 1;
$legacyCount = $tableExists
    ? (int) $pdo->query('SELECT COUNT(*) FROM ledger_fund_transfer_links')->fetchColumn()
    : null;

$confirmedEvidenceMap = (new InternalTransferRepository($pdo))->confirmedEvidenceMap();
$twoEvidenceCandidates = $pdo->query("
    SELECT voucher.id,
           voucher.status,
           MAX(evidence.raw_transaction_datetime) AS latest_transaction_at
    FROM ledger_vouchers voucher
    INNER JOIN ledger_evidence_links evidence_link
      ON evidence_link.target_type = 'VOUCHER'
     AND evidence_link.target_id = voucher.id
     AND evidence_link.evidence_type = 'BANK_TRANSACTION'
     AND evidence_link.deleted_at IS NULL
    INNER JOIN ledger_evidence_bank_transaction evidence
      ON evidence.id = evidence_link.evidence_id
     AND evidence.deleted_at IS NULL
    GROUP BY voucher.id, voucher.status
    HAVING COUNT(DISTINCT evidence.id) = 2
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$recentCandidateCount = count(array_filter(
    $twoEvidenceCandidates,
    static fn(array $row): bool => (string) ($row['latest_transaction_at'] ?? '') >= '2026-01-30'
));
$bankRows = (new BankTransactionReportModel($pdo))->rows([]);
$confirmedBankRows = array_filter(
    $bankRows,
    static fn(array $row): bool => ($row['internal_transfer_status'] ?? '') === 'CONFIRMED'
);
$bankService = new BankTransactionReportService($pdo);
$apiRows = $bankService->rows([]);
$apiSummary = $bankService->summary([]);
$dailyReport = (new DailyFundsReportService($pdo))->report(['report_date' => '2013-07-24']);
$dailyInstruments = is_array($dailyReport['instruments'] ?? null) ? $dailyReport['instruments'] : [];

echo json_encode([
    'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
    'legacy_table_exists' => $tableExists,
    'legacy_table_rows' => $legacyCount,
    'legacy_table_created_at' => $table['created_at'] ?? null,
    'two_bank_evidence_voucher_candidates' => count($twoEvidenceCandidates),
    'recent_six_month_candidate_count' => $recentCandidateCount,
    'candidate_statuses' => array_count_values(array_column($twoEvidenceCandidates, 'status')),
    'confirmed_evidence_count' => count($confirmedEvidenceMap),
    'bank_transaction_rows' => count($bankRows),
    'confirmed_bank_transaction_rows' => count($confirmedBankRows),
    'api_service_rows' => count($apiRows),
    'api_summary_fields' => array_keys($apiSummary),
    'daily_report_date' => '2013-07-24',
    'daily_internal_deposit' => array_sum(array_column($dailyInstruments, 'internal_deposit')),
    'daily_internal_withdraw' => array_sum(array_column($dailyInstruments, 'internal_withdraw')),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
