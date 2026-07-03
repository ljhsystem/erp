<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\VoucherModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class TransactionVoucherService
{
    private TransactionCrudService $transactionCrudService;
    private TransactionLinkModel $transactionLinkModel;
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefModel $voucherLineRefModel;
    private JournalLearningService $journalLearningService;

    public function __construct(private readonly PDO $pdo)
    {
        $this->transactionCrudService = new TransactionCrudService($pdo);
        $this->transactionLinkModel = new TransactionLinkModel($pdo);
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
        $this->voucherLineRefModel = new VoucherLineRefModel($pdo);
        $this->journalLearningService = new JournalLearningService($pdo);
    }

    public function appendLinkedVouchers(array $transaction): array
    {
        if ($transaction === [] || empty($transaction['id'])) {
            return $transaction;
        }

        $transaction['linked_vouchers'] = $this->fetchLinkedVouchers((string) $transaction['id']);

        return $transaction;
    }

    public function linkedVouchers(string $transactionId): array
    {
        return $this->fetchLinkedVouchers($transactionId);
    }

    public function recommendVoucherDraft(string $transactionId): array
    {
        $transaction = $this->transactionCrudService->getById($transactionId);
        if (!$transaction || !empty($transaction['deleted_at'])) {
            throw new \InvalidArgumentException('거래를 찾을 수 없습니다.');
        }

        if ($this->fetchLinkedVouchers($transactionId) !== []) {
            throw new \RuntimeException('이미 연결된 전표가 있습니다.');
        }

        return [
            'success' => true,
            'transaction' => $this->voucherWizardTransaction($transaction),
            'recommendation' => (new JournalRecommendationService($this->pdo))->recommendForTransaction($transactionId),
        ];
    }

    public function createDraftVoucher(string $transactionId, array $payload = []): array
    {
        $transaction = $this->transactionCrudService->getById($transactionId);
        if (!$transaction || !empty($transaction['deleted_at'])) {
            throw new \InvalidArgumentException('거래를 찾을 수 없습니다.');
        }

        if ($this->fetchLinkedVouchers($transactionId) !== []) {
            throw new \RuntimeException('이미 연결된 전표가 있습니다.');
        }

        $lines = $this->normalizeWizardLines($payload['lines'] ?? []);
        $this->assertBalancedVoucherLines($lines);

        $header = is_array($payload['header'] ?? null) ? $payload['header'] : [];
        $actor = ActorHelper::user();
        $timestamp = date('Y-m-d H:i:s');
        $voucherId = UuidHelper::generate();
        $voucherDate = trim((string) ($header['transaction_date'] ?? $transaction['transaction_date'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $voucherNo = $this->nextVoucherNo($voucherDate);
        $items = is_array($transaction['items'] ?? null) ? $transaction['items'] : [];
        $firstItemName = trim((string) ($items[0]['item_name'] ?? ''));
        $totalAmount = array_reduce($items, static function (float $sum, array $item): float {
            return $sum + (float) ($item['item_total_amount'] ?? 0);
        }, 0.0);

        $this->pdo->beginTransaction();

        if (!$this->voucherModel->insert([
            'id' => $voucherId,
            'sort_no' => SequenceHelper::next('ledger_vouchers', 'sort_no'),
            'voucher_no' => $voucherNo,
            'voucher_date' => $voucherDate,
            'source_type' => 'TRANSACTION',
            'source_id' => $transactionId,
            'status' => 'draft',
            'summary_text' => trim((string) ($header['transaction_description'] ?? $transaction['transaction_description'] ?? '')) ?: ($firstItemName ?: null),
            'note' => $transaction['transaction_note'] ?? null,
            'created_at' => $timestamp,
            'created_by' => $actor,
            'updated_at' => $timestamp,
            'updated_by' => $actor,
        ])) {
            throw new \RuntimeException('전표 생성에 실패했습니다.');
        }

        if (!$this->transactionLinkModel->insertOrRestore(
            $transactionId,
            $voucherId,
            $transaction['transaction_total_amount'] ?? $totalAmount,
            'AUTO',
            $actor
        )) {
            throw new \RuntimeException('전표 연결 저장에 실패했습니다.');
        }

        $lineNo = 1;
        $learningLines = [];
        foreach ($lines as $line) {
            $lineId = UuidHelper::generate();
            if (!$this->voucherLineModel->insert([
                'id' => $lineId,
                'sort_no' => SequenceHelper::next('ledger_voucher_lines', 'sort_no'),
                'voucher_id' => $voucherId,
                'line_no' => $lineNo++,
                'account_id' => (string) $line['account_id'],
                'debit' => $line['line_type'] === 'DEBIT' ? number_format((float) $line['amount'], 2, '.', '') : '0.00',
                'credit' => $line['line_type'] === 'CREDIT' ? number_format((float) $line['amount'], 2, '.', '') : '0.00',
                'line_summary' => $line['line_summary'] ?? ($transaction['transaction_description'] ?? null),
                'recommend_source' => $line['source'] ?? null,
                'recommend_confidence' => $line['confidence'] ?? null,
                'journal_rule_id' => $line['journal_rule_id'] ?? null,
                'recommend_reason' => $line['reason'] ?? null,
                'is_user_modified' => !empty($line['is_user_modified']) ? 1 : 0,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ])) {
                throw new \RuntimeException('전표 라인 저장에 실패했습니다.');
            }

            $refs = [];
            if (!empty($line['client_id'])) {
                $refs[] = ['ref_type' => 'CLIENT', 'ref_id' => $line['client_id'], 'is_primary' => 1];
            }
            if (!empty($line['project_id'])) {
                $refs[] = ['ref_type' => 'PROJECT', 'ref_id' => $line['project_id'], 'is_primary' => 0];
            }
            if ($refs !== []) {
                $this->voucherLineRefModel->bulkInsert($lineId, $refs, $actor, $timestamp);
            }

            $line['voucher_line_id'] = $lineId;
            $line['line_no'] = $lineNo - 1;
            $learningLines[] = $line;
        }

        $this->journalLearningService->recordVoucherDraft($transaction, $voucherId, $learningLines, $actor);
        $this->syncEvidenceVoucherCreation($transactionId, $voucherId, $actor);
        $this->transactionCrudService->recalculateMatchStatus($transactionId, $actor);
        $this->pdo->commit();

        return [
            'success' => true,
            'message' => '전표 초안이 저장되었습니다.',
            'voucher_id' => $voucherId,
            'voucher_no' => $voucherNo,
            'data' => $this->appendLinkedVouchers($this->transactionCrudService->getById($transactionId) ?? []),
        ];
    }

    public function linkVoucher(string $transactionId, string $voucherId): array
    {
        $transactionId = trim($transactionId);
        $voucherId = trim($voucherId);
        if ($transactionId === '' || $voucherId === '') {
            throw new \InvalidArgumentException('거래와 전표를 선택해 주세요.');
        }

        $transaction = $this->transactionCrudService->getById($transactionId);
        if (!$transaction || !empty($transaction['deleted_at'])) {
            throw new \InvalidArgumentException('거래를 찾을 수 없습니다.');
        }

        $voucher = $this->voucherModel->getById($voucherId);
        if (!$voucher || !empty($voucher['deleted_at'])) {
            throw new \InvalidArgumentException('전표를 찾을 수 없습니다.');
        }

        $this->assertVoucherLinkEditable($voucher);
        $actor = ActorHelper::user();

        $this->pdo->beginTransaction();
        if (!$this->transactionLinkModel->insertOrRestore(
            $transactionId,
            $voucherId,
            $transaction['transaction_total_amount'] ?? null,
            'MANUAL',
            $actor
        )) {
            throw new \RuntimeException('전표 연결 저장에 실패했습니다.');
        }

        $this->transactionCrudService->recalculateMatchStatus($transactionId, $actor);
        $this->pdo->commit();

        return [
            'success' => true,
            'message' => '전표가 연결되었습니다.',
            'data' => $this->appendLinkedVouchers($this->transactionCrudService->getById($transactionId) ?? []),
        ];
    }

    public function unlinkVoucher(string $transactionId, string $voucherId = ''): array
    {
        $transactionId = trim($transactionId);
        $voucherId = trim($voucherId);
        if ($transactionId === '') {
            throw new \InvalidArgumentException('거래를 선택해 주세요.');
        }

        $links = $this->transactionLinkModel->getByTransactionId($transactionId);
        foreach ($links as $link) {
            if ($voucherId !== '' && (string) ($link['voucher_id'] ?? '') !== $voucherId) {
                continue;
            }

            $voucher = $this->voucherModel->getById((string) ($link['voucher_id'] ?? ''));
            if ($voucher) {
                $this->assertVoucherLinkEditable($voucher);
            }
        }

        $actor = ActorHelper::user();
        $this->pdo->beginTransaction();
        foreach ($links as $link) {
            $linkedVoucherId = (string) ($link['voucher_id'] ?? '');
            if ($linkedVoucherId === '' || ($voucherId !== '' && $linkedVoucherId !== $voucherId)) {
                continue;
            }

            $this->transactionLinkModel->softDeleteByTransactionAndVoucher($transactionId, $linkedVoucherId, $actor);
        }
        $this->transactionCrudService->recalculateMatchStatus($transactionId, $actor);
        $this->pdo->commit();

        return [
            'success' => true,
            'message' => '전표 연결이 해제되었습니다.',
            'data' => $this->appendLinkedVouchers($this->transactionCrudService->getById($transactionId) ?? []),
        ];
    }

    public function createPostedVoucherFromMatchedTransaction(string $transactionId): array
    {
        return (new TransactionService($this->pdo))->createVoucherFromTransaction($transactionId);
    }

    private function fetchLinkedVouchers(string $transactionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                l.id AS link_id,
                l.link_type,
                v.id,
                v.sort_no,
                v.voucher_no,
                v.voucher_date,
                v.status,
                v.summary_text
            FROM ledger_transaction_links l
            INNER JOIN ledger_vouchers v
                ON v.id = l.voucher_id
            WHERE l.transaction_id = :transaction_id
              AND l.deleted_at IS NULL
              AND l.is_active = 1
              AND v.deleted_at IS NULL
            ORDER BY v.voucher_date DESC, v.sort_no DESC
        ");
        $stmt->execute([':transaction_id' => $transactionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function voucherWizardTransaction(array $transaction): array
    {
        return [
            'id' => (string) ($transaction['id'] ?? ''),
            'transaction_date' => (string) ($transaction['transaction_date'] ?? ''),
            'client_id' => (string) ($transaction['client_id'] ?? ''),
            'client_name' => (string) ($transaction['client_name'] ?? ''),
            'project_id' => (string) ($transaction['project_id'] ?? ''),
            'project_name' => (string) ($transaction['project_name'] ?? ''),
            'business_unit' => (string) ($transaction['business_unit'] ?? ''),
            'transaction_direction' => (string) ($transaction['transaction_direction'] ?? ''),
            'transaction_supply_amount' => (string) ($transaction['transaction_supply_amount'] ?? '0'),
            'transaction_vat_amount' => (string) ($transaction['transaction_vat_amount'] ?? '0'),
            'transaction_total_amount' => (string) ($transaction['transaction_total_amount'] ?? '0'),
            'transaction_description' => (string) ($transaction['transaction_description'] ?? ''),
        ];
    }

    private function normalizeWizardLines(mixed $lines): array
    {
        if (!is_array($lines)) {
            throw new \InvalidArgumentException('전표 라인 형식이 올바르지 않습니다.');
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $lineType = strtoupper(trim((string) ($line['line_type'] ?? '')));
            $accountId = trim((string) ($line['account_id'] ?? ''));
            $amount = round((float) str_replace(',', '', (string) ($line['amount'] ?? 0)), 2);

            if ($lineType === '' && isset($line['debit'], $line['credit'])) {
                $lineType = (float) $line['debit'] > 0 ? 'DEBIT' : 'CREDIT';
                $amount = max((float) $line['debit'], (float) $line['credit']);
            }

            if (!in_array($lineType, ['DEBIT', 'CREDIT'], true) || $accountId === '' || $amount <= 0) {
                continue;
            }

            $normalized[] = [
                'line_type' => $lineType,
                'account_id' => $accountId,
                'amount' => $amount,
                'line_summary' => trim((string) ($line['line_summary'] ?? '')) ?: null,
                'client_id' => trim((string) ($line['client_id'] ?? '')) ?: null,
                'project_id' => trim((string) ($line['project_id'] ?? '')) ?: null,
                'source' => trim((string) ($line['source'] ?? '')) ?: null,
                'confidence' => isset($line['confidence']) ? (int) $line['confidence'] : null,
                'journal_rule_id' => trim((string) ($line['journal_rule_id'] ?? '')) ?: null,
                'reason' => trim((string) ($line['reason'] ?? '')) ?: null,
                'recommended_line_type' => strtoupper(trim((string) ($line['recommended_line_type'] ?? $lineType))) ?: $lineType,
                'recommended_account_id' => trim((string) ($line['recommended_account_id'] ?? $accountId)) ?: $accountId,
                'recommended_amount' => round((float) str_replace(',', '', (string) ($line['recommended_amount'] ?? $amount)), 2),
                'is_user_modified' => !empty($line['is_user_modified'])
                    || strtoupper(trim((string) ($line['recommended_line_type'] ?? $lineType))) !== $lineType
                    || trim((string) ($line['recommended_account_id'] ?? $accountId)) !== $accountId
                    || round((float) str_replace(',', '', (string) ($line['recommended_amount'] ?? $amount)), 2) !== $amount,
            ];
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException('저장할 전표 라인이 없습니다.');
        }

        return $normalized;
    }

    private function assertBalancedVoucherLines(array $lines): void
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            if (($line['line_type'] ?? '') === 'DEBIT') {
                $debit += (float) $line['amount'];
            } elseif (($line['line_type'] ?? '') === 'CREDIT') {
                $credit += (float) $line['amount'];
            }
        }

        if ($debit <= 0 || $credit <= 0 || round($debit, 2) !== round($credit, 2)) {
            throw new \InvalidArgumentException('차변과 대변이 일치해야 전표를 저장할 수 있습니다.');
        }
    }

    private function assertVoucherLinkEditable(array $voucher): void
    {
        if (($voucher['status'] ?? '') === 'posted') {
            throw new \RuntimeException('posted 상태의 전표는 연결을 변경할 수 없습니다.');
        }
    }

    private function syncEvidenceVoucherCreation(string $transactionId, string $voucherId, string $actor): void
    {
        if ($transactionId === '' || $voucherId === '' || !$this->tableExists('ledger_data_evidences')) {
            return;
        }

        $evidenceIds = [];
        if ($this->tableColumnExists('ledger_data_evidences', 'transaction_id')) {
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM ledger_data_evidences
                WHERE transaction_id = :transaction_id
                  AND deleted_at IS NULL
            ");
            $stmt->execute([':transaction_id' => $transactionId]);
            $evidenceIds = array_merge($evidenceIds, array_map(
                static fn(array $row): string => trim((string) ($row['id'] ?? '')),
                $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
            ));
        }

        if ($this->tableExists('ledger_evidence_links')) {
            $stmt = $this->pdo->prepare("
                SELECT e.id
                FROM ledger_evidence_links l
                INNER JOIN ledger_data_evidences e
                    ON e.id = l.evidence_id
                   AND e.deleted_at IS NULL
                WHERE l.target_type = 'TRANSACTION'
                  AND l.target_id = :transaction_id
                  AND l.deleted_at IS NULL
            ");
            $stmt->execute([':transaction_id' => $transactionId]);
            $evidenceIds = array_merge($evidenceIds, array_map(
                static fn(array $row): string => trim((string) ($row['id'] ?? '')),
                $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
            ));
        }

        $evidenceIds = array_values(array_filter(array_unique($evidenceIds)));

        foreach ($evidenceIds as $evidenceId) {
            $this->linkVoucherToEvidence($evidenceId, $voucherId);
            $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);
        }
    }

    private function linkVoucherToEvidence(string $evidenceId, string $voucherId): void
    {
        if ($evidenceId === '' || $voucherId === '' || !$this->tableExists('ledger_evidence_links')) {
            return;
        }

        $existing = $this->pdo->prepare("
            SELECT id
            FROM ledger_evidence_links
            WHERE evidence_id = :evidence_id
              AND target_type = 'VOUCHER'
              AND target_id = :voucher_id
              AND link_type = 'AUTO'
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $existing->execute([
            ':evidence_id' => $evidenceId,
            ':voucher_id' => $voucherId,
        ]);
        if ($existing->fetchColumn()) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ledger_evidence_links
                (id, evidence_type, evidence_id, target_type, target_id, link_type, amount, created_at, updated_at)
            SELECT
                :id, e.source_type, e.id, 'VOUCHER', :voucher_id, 'AUTO', 0, NOW(), NOW()
            FROM ledger_data_evidences e
            WHERE e.id = :evidence_id
              AND e.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => UuidHelper::generate(),
            ':evidence_id' => $evidenceId,
            ':voucher_id' => $voucherId,
        ]);
    }

    private function updateEvidenceVoucherStatus(string $evidenceId, string $voucherStatus, string $actor): void
    {
        if ($evidenceId === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET voucher_status = :voucher_status,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $evidenceId,
            ':voucher_status' => $voucherStatus,
            ':updated_by' => $actor,
        ]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
            LIMIT 1
        ");
        $stmt->execute([':table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
            LIMIT 1
        ");
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function nextVoucherNo(string $voucherDate): string
    {
        $prefix = preg_replace('/[^0-9]/', '', $voucherDate);
        if ($prefix === '') {
            $prefix = date('Ymd');
        }

        $stmt = $this->pdo->prepare("
            SELECT voucher_no
            FROM ledger_vouchers
            WHERE voucher_no LIKE :prefix
            ORDER BY voucher_no DESC
            LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix . '-%']);

        $last = (string) ($stmt->fetchColumn() ?: '');
        $next = 1;
        if (preg_match('/-(\d+)$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}
