<?php

declare(strict_types=1);

use Core\Database;
use Core\Helpers\ActorHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Bootstrap.php';

const EVIDENCE_STATUS_BASELINE_ACTOR_CONTEXT = 'EVIDENCE_STATUS_BASELINE_20260825';
const EVIDENCE_STATUS_BASELINE_TYPES = [
    'BANK_TRANSACTION' => 'ledger_evidence_bank_transaction',
    'TAX_INVOICE' => 'ledger_evidence_tax_invoice',
    'TAX_INVOICE_MANUAL' => 'ledger_evidence_tax_invoice_manual',
    'CASH_RECEIPT' => 'ledger_evidence_cash_receipt',
    'CARD_HOMETAX' => 'ledger_evidence_card_hometax',
    'CARD_STATEMENT' => 'ledger_evidence_card_statement',
    'EMPLOYEE_EXPENSE_PERSONAL' => 'ledger_evidence_employee_personal_expense',
    'PAYROLL_REPORT' => 'ledger_evidence_salary_report',
];

function evidenceRowsSql(): string
{
    $parts = [];
    foreach (EVIDENCE_STATUS_BASELINE_TYPES as $type => $table) {
        $parts[] = "SELECT '{$type}' evidence_type, '{$table}' body_table, id evidence_id,
            evidence_status, deleted_at
            FROM `{$table}`";
    }

    return implode("\nUNION ALL\n", $parts);
}

function evidenceStatusBaselineRows(PDO $pdo): array
{
    $sql = "SELECT evidence.*,
        EXISTS(
            SELECT 1
            FROM ledger_evidence_links link
            INNER JOIN ledger_transactions transaction_row
                ON transaction_row.id = link.target_id
               AND transaction_row.deleted_at IS NULL
               AND LOWER(transaction_row.status) NOT IN
                   ('cancelled','canceled','invalid','void','voided','discarded','deleted')
            WHERE link.evidence_type = evidence.evidence_type
              AND link.evidence_id = evidence.evidence_id
              AND link.target_type = 'TRANSACTION'
              AND link.deleted_at IS NULL
        ) active_transaction_link,
        EXISTS(
            SELECT 1
            FROM ledger_evidence_links link
            INNER JOIN ledger_vouchers voucher
                ON voucher.id = link.target_id
               AND voucher.deleted_at IS NULL
               AND LOWER(voucher.status) <> 'deleted'
               AND COALESCE(voucher.is_reversal, 0) = 0
            WHERE link.evidence_type = evidence.evidence_type
              AND link.evidence_id = evidence.evidence_id
              AND link.target_type = 'VOUCHER'
              AND link.deleted_at IS NULL
        ) active_voucher_link
        FROM (" . evidenceRowsSql() . ") evidence
        ORDER BY evidence.evidence_type, evidence.evidence_id";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function evidenceStatusBaselineLinkAudit(PDO $pdo): array
{
    $bodyExistsCases = [];
    foreach (EVIDENCE_STATUS_BASELINE_TYPES as $type => $table) {
        $bodyExistsCases[] = "WHEN link.evidence_type = '{$type}' THEN EXISTS(SELECT 1 FROM `{$table}` body WHERE body.id = link.evidence_id)";
    }
    $bodyExistsSql = 'CASE ' . implode(' ', $bodyExistsCases) . ' ELSE 0 END';
    $typeMarks = implode(',', array_fill(0, count(EVIDENCE_STATUS_BASELINE_TYPES), '?'));
    $sql = "SELECT link.evidence_type,
        SUM(link.deleted_at IS NOT NULL) deleted_link_count,
        SUM(link.deleted_at IS NULL AND (
            ({$bodyExistsSql}) = 0
            OR (link.target_type = 'TRANSACTION' AND transaction_row.id IS NULL)
            OR (link.target_type = 'VOUCHER' AND voucher.id IS NULL)
        )) orphan_link_count,
        SUM(link.deleted_at IS NULL AND (
            (link.target_type = 'TRANSACTION' AND transaction_row.id IS NOT NULL AND (
                transaction_row.deleted_at IS NOT NULL
                OR LOWER(transaction_row.status) IN ('cancelled','canceled','invalid','void','voided','discarded','deleted')
            ))
            OR (link.target_type = 'VOUCHER' AND voucher.id IS NOT NULL AND (
                voucher.deleted_at IS NOT NULL OR LOWER(voucher.status) = 'deleted' OR COALESCE(voucher.is_reversal, 0) = 1
            ))
        )) inactive_link_count
        FROM ledger_evidence_links link
        LEFT JOIN ledger_transactions transaction_row
          ON link.target_type = 'TRANSACTION' AND transaction_row.id = link.target_id
        LEFT JOIN ledger_vouchers voucher
          ON link.target_type = 'VOUCHER' AND voucher.id = link.target_id
        WHERE link.evidence_type IN ({$typeMarks})
          AND link.target_type IN ('TRANSACTION','VOUCHER')
        GROUP BY link.evidence_type";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_keys(EVIDENCE_STATUS_BASELINE_TYPES));
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $result[(string) $row['evidence_type']] = [
            'deleted_link_count' => (int) $row['deleted_link_count'],
            'orphan_link_count' => (int) $row['orphan_link_count'],
            'inactive_link_count' => (int) $row['inactive_link_count'],
        ];
    }

    return $result;
}

function evidenceStatusBaselineReport(PDO $pdo): array
{
    $report = [];
    foreach (EVIDENCE_STATUS_BASELINE_TYPES as $type => $table) {
        $report[$type] = [
            'body_table' => $table,
            'total_count' => 0,
            'completed_count' => 0,
            'correction_required_count' => 0,
            'other_status_count' => 0,
            'active_transaction_link_count' => 0,
            'active_voucher_link_count' => 0,
            'transaction_voucher_overlap_count' => 0,
            'excluded_unique_count' => 0,
            'change_target_count' => 0,
            'unlinked_correction_noop_count' => 0,
            'linked_correction_anomaly_count' => 0,
            'deleted_evidence_count' => 0,
            'deleted_link_count' => 0,
            'orphan_link_count' => 0,
            'inactive_link_count' => 0,
            'expected_completed_count' => 0,
            'expected_correction_required_count' => 0,
            'expected_other_status_count' => 0,
            'change_target_ids' => [],
            'linked_correction_anomalies' => [],
        ];
    }

    foreach (evidenceStatusBaselineRows($pdo) as $row) {
        $type = (string) $row['evidence_type'];
        $status = strtoupper(trim((string) $row['evidence_status']));
        $deleted = $row['deleted_at'] !== null;
        $transactionLinked = (bool) $row['active_transaction_link'];
        $voucherLinked = (bool) $row['active_voucher_link'];
        $linked = $transactionLinked || $voucherLinked;
        $item =& $report[$type];
        $item['total_count']++;
        if ($status === 'COMPLETED') $item['completed_count']++;
        elseif ($status === 'CORRECTION_REQUIRED') $item['correction_required_count']++;
        else $item['other_status_count']++;
        if ($transactionLinked) $item['active_transaction_link_count']++;
        if ($voucherLinked) $item['active_voucher_link_count']++;
        if ($transactionLinked && $voucherLinked) $item['transaction_voucher_overlap_count']++;
        if ($linked) $item['excluded_unique_count']++;
        if ($deleted) $item['deleted_evidence_count']++;
        if ($linked && $status === 'CORRECTION_REQUIRED') {
            $item['linked_correction_anomaly_count']++;
            $item['linked_correction_anomalies'][] = [
                'evidence_id' => (string) $row['evidence_id'],
                'transaction_linked' => $transactionLinked,
                'voucher_linked' => $voucherLinked,
            ];
        }
        if (!$deleted && !$linked && $status !== 'CORRECTION_REQUIRED') {
            $item['change_target_count']++;
            $item['change_target_ids'][] = (string) $row['evidence_id'];
        }
        if (!$deleted && !$linked && $status === 'CORRECTION_REQUIRED') {
            $item['unlinked_correction_noop_count']++;
        }
    }

    $linkAudit = evidenceStatusBaselineLinkAudit($pdo);
    foreach ($report as $type => &$item) {
        foreach ($linkAudit[$type] ?? [] as $key => $value) $item[$key] = $value;
        $item['expected_completed_count'] = $item['completed_count'] - $item['change_target_count'];
        $item['expected_correction_required_count'] = $item['correction_required_count'] + $item['change_target_count'];
        $item['expected_other_status_count'] = $item['other_status_count'];
        sort($item['change_target_ids']);
    }
    unset($item);

    return $report;
}

function evidenceStatusBaselineDigest(array $report): string
{
    $payload = [];
    foreach ($report as $type => $item) {
        $payload[$type] = [
            'total_count' => $item['total_count'],
            'completed_count' => $item['completed_count'],
            'correction_required_count' => $item['correction_required_count'],
            'deleted_evidence_count' => $item['deleted_evidence_count'],
            'active_transaction_link_count' => $item['active_transaction_link_count'],
            'active_voucher_link_count' => $item['active_voucher_link_count'],
            'change_target_ids' => $item['change_target_ids'],
        ];
    }

    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function evidenceStatusBaselineInvariants(PDO $pdo): array
{
    $tables = ['ledger_transactions', 'ledger_vouchers', 'ledger_evidence_links'];
    $result = [];
    foreach ($tables as $table) {
        $rows = $pdo->query("SELECT * FROM `{$table}` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result[$table] = [
            'count' => count($rows),
            'hash' => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
    }
    foreach (EVIDENCE_STATUS_BASELINE_TYPES as $type => $table) {
        $columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}'
              AND COLUMN_NAME NOT IN ('evidence_status','updated_at','updated_by')
            ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
        $select = implode(',', array_map(static fn(string $column): string => "`{$column}`", $columns));
        $rows = $pdo->query("SELECT {$select} FROM `{$table}` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result[$type] = [
            'count' => count($rows),
            'hash' => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
    }

    return $result;
}

function assertEvidenceStatusBaselineReconciled(array $report): void
{
    foreach ($report as $type => $item) {
        if ($item['total_count'] !== $item['completed_count'] + $item['correction_required_count'] + $item['other_status_count']) {
            throw new RuntimeException("{$type} 상태별 전체 건수가 일치하지 않습니다.");
        }
        if ($item['excluded_unique_count'] !== $item['active_transaction_link_count'] + $item['active_voucher_link_count'] - $item['transaction_voucher_overlap_count']) {
            throw new RuntimeException("{$type} 연결 제외 고유건수가 일치하지 않습니다.");
        }
        if ($item['expected_completed_count'] < 0 || $item['expected_correction_required_count'] < 0) {
            throw new RuntimeException("{$type} 예상 전환 건수가 올바르지 않습니다.");
        }
    }
}

function evidenceStatusBaselineSummary(array $report): array
{
    $summary = [];
    foreach ($report as $item) {
        foreach ($item as $key => $value) {
            if (is_int($value)) $summary[$key] = ($summary[$key] ?? 0) + $value;
        }
    }

    return $summary;
}

$options = getopt('', ['apply', 'snapshot:', 'output:']);
$apply = array_key_exists('apply', $options);
$snapshotPath = isset($options['snapshot']) ? trim((string) $options['snapshot']) : '';
$pdo = Database::getInstance()->getConnection();
$beforeReport = evidenceStatusBaselineReport($pdo);
assertEvidenceStatusBaselineReconciled($beforeReport);
$beforeDigest = evidenceStatusBaselineDigest($beforeReport);
$beforeInvariants = evidenceStatusBaselineInvariants($pdo);

if (!$apply) {
    $output = [
        'mode' => 'DRY_RUN',
        'digest' => $beforeDigest,
        'generated_at' => date('c'),
        'types' => $beforeReport,
        'summary' => evidenceStatusBaselineSummary($beforeReport),
        'invariants' => $beforeInvariants,
    ];
    $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $outputPath = isset($options['output']) ? trim((string) $options['output']) : '';
    if ($outputPath !== '') {
        $resolvedDirectory = realpath(dirname($outputPath));
        $allowedDirectory = realpath(PROJECT_ROOT . '/storage');
        if ($resolvedDirectory === false || $allowedDirectory === false || !str_starts_with($resolvedDirectory, $allowedDirectory)) {
            throw new RuntimeException('Snapshot 출력경로는 storage 하위의 기존 디렉터리여야 합니다.');
        }
        file_put_contents($outputPath, $json, LOCK_EX);
    }
    echo $json;
    exit(0);
}

if ($snapshotPath !== '') {
    $snapshot = json_decode((string) file_get_contents($snapshotPath), true);
    if (!is_array($snapshot) || ($snapshot['digest'] ?? '') !== $beforeDigest) {
        throw new RuntimeException('Dry-run Snapshot과 현재 적용대상이 일치하지 않습니다.');
    }
}

$actor = ActorHelper::system(EVIDENCE_STATUS_BASELINE_ACTOR_CONTEXT);
$changedByType = [];
$pdo->beginTransaction();
try {
    foreach ($beforeReport as $type => $item) {
        $ids = $item['change_target_ids'];
        if ($ids === []) {
            $changedByType[$type] = 0;
            continue;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE `{$item['body_table']}`
            SET evidence_status='CORRECTION_REQUIRED', updated_at=NOW(), updated_by=?
            WHERE id IN ({$marks})
              AND deleted_at IS NULL
              AND evidence_status <> 'CORRECTION_REQUIRED'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$actor], $ids));
        $changedByType[$type] = $stmt->rowCount();
        if ($changedByType[$type] !== count($ids)) {
            throw new RuntimeException("{$type} 실제 변경건수가 Dry-run 대상과 일치하지 않습니다.");
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

$afterReport = evidenceStatusBaselineReport($pdo);
assertEvidenceStatusBaselineReconciled($afterReport);
$afterInvariants = evidenceStatusBaselineInvariants($pdo);
if ($beforeInvariants !== $afterInvariants) {
    throw new RuntimeException('Evidence 원본 또는 거래·전표·Link 불변 검증에 실패했습니다.');
}
foreach ($afterReport as $type => $item) {
    if ($item['change_target_count'] !== 0) throw new RuntimeException("{$type} 멱등 재검증에 실패했습니다.");
}

echo json_encode([
    'mode' => 'APPLY',
    'actor' => $actor,
    'before_digest' => $beforeDigest,
    'changed_by_type' => $changedByType,
    'changed_count' => array_sum($changedByType),
    'before' => $beforeReport,
    'after' => $afterReport,
    'invariants' => $afterInvariants,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
