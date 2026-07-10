<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionLinkModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class VoucherCreateService
{
    public function __construct(private PDO $pdo, private array $callbacks = []) {}

    public function createVoucherFromBankPayload(string $evidenceId, array $row, string $transactionId, bool $linkExistingVoucher = false): ?string
    {
        if ($this->call('normalizeDataType', (string) ($row['import_type'] ?? $row['source_type'] ?? 'BANK_TRANSACTION')) !== 'BANK_TRANSACTION') {
            return null;
        }
        if (!$this->call('hasVoucherLinesPayload', $row)) {
            return null;
        }

        $actor = ActorHelper::user();
        try {
            $existingVoucher = $this->existingVoucherForBankPayload($evidenceId, $row);
            if ($existingVoucher) {
                $voucherId = (string) ($existingVoucher['id'] ?? '');
                if ($voucherId !== '' && $linkExistingVoucher) {
                    $this->tagCreatedVoucher($voucherId, $evidenceId, $transactionId, $actor);
                    $this->linkVoucherToEvidence($evidenceId, $voucherId, $transactionId, $actor);
                    if ($transactionId !== '') {
                        $this->linkVoucherToTransaction($voucherId, $transactionId, null, 'AUTO', $actor);
                    }
                    $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);
                    return $voucherId;
                }

                $this->updateEvidenceVoucherStatus($evidenceId, 'ERROR', $actor, '이미 생성된 전표가 있어 자동으로 새 전표를 만들지 않았습니다.');
                return null;
            }

            $lines = $this->bankVoucherLinesForSave($row['_voucher_lines'] ?? []);
            if ($lines === []) {
                $this->updateEvidenceVoucherStatus($evidenceId, 'ERROR', $actor, '전표 라인 정보가 없어 전표를 생성할 수 없습니다.');
                return null;
            }

            $lines = $this->call('applyEvidenceRefsToVoucherLines', $lines, $row);
            $result = $this->call('saveVoucher', [
                'voucher_date' => $this->call('dateValue', $row['voucher_date'] ?? $row['transaction_date'] ?? date('Y-m-d')),
                'summary' => trim((string) ($row['voucher_summary_text'] ?? $row['summary'] ?? $row['summary_text'] ?? $row['description'] ?? '')),
                'source_type' => 'BANK',
                'lines' => $lines,
            ]);

            $voucherId = (string) ($result['voucher_id'] ?? $result['id'] ?? '');
            if ($voucherId !== '') {
                $this->tagCreatedVoucher($voucherId, $evidenceId, $transactionId, $actor);
                $this->linkVoucherToEvidence($evidenceId, $voucherId, $transactionId, $actor);
                $this->updateEvidenceVoucherStatus($evidenceId, 'CREATED', $actor);
                if ($transactionId !== '') {
                    $this->call('recordBankVoucherLearning', $transactionId, $voucherId, $row, $lines, $actor);
                }
            }

            if ($voucherId === '') {
                $this->updateEvidenceVoucherStatus($evidenceId, 'ERROR', $actor, (string) ($result['message'] ?? '전표 생성 결과에서 전표 ID를 확인할 수 없습니다.'));
                return null;
            }

            return $voucherId;
        } catch (\Throwable $e) {
            $this->updateEvidenceVoucherStatus($evidenceId, 'ERROR', $actor, $e->getMessage());
            return null;
        }
    }

    public function bankVoucherLinesForSave(mixed $rawLines): array
    {
        if (!is_array($rawLines)) {
            return [];
        }

        $journalBySourceNo = [];
        $refsBySourceNo = [];
        $errors = [];
        foreach ($rawLines as $rawLine) {
            if (!is_array($rawLine)) {
                continue;
            }
            if (!$this->bankVoucherRawLineHasMeaningfulValue($rawLine)) {
                continue;
            }

            $rowType = $this->call('normalizeBankVoucherLineRowType', $rawLine['line_row_type'] ?? null);
            $sourceLineNo = (int) ($rawLine['line_no'] ?? 0);
            if ($sourceLineNo <= 0) {
                $errors[] = $rowType === 'AUX'
                    ? '보조 라인에 원본 라인 번호가 없어 전표 라인과 연결할 수 없습니다.'
                    : '전표 라인 번호가 없어 전표를 생성할 수 없습니다.';
                continue;
            }

            if ($rowType === 'AUX') {
                $ref = $this->bankVoucherLineRefForSave($rawLine);
                if ($ref !== null) {
                    $refsBySourceNo[$sourceLineNo][] = $ref;
                }
                continue;
            }

            if (array_key_exists($sourceLineNo, $journalBySourceNo)) {
                $errors[] = '중복된 전표 라인 번호가 있습니다. ' . $sourceLineNo;
                continue;
            }

            $account = $this->call('normalizeAccountInput', (string) ($rawLine['account_id'] ?? ''));
            $debit = $this->call('amountOrNull', $rawLine['debit'] ?? null);
            $credit = $this->call('amountOrNull', $rawLine['credit'] ?? null);
            if ($account === '' && ($debit === null || $debit == 0.0) && ($credit === null || $credit == 0.0)) {
                continue;
            }

            $journalBySourceNo[$sourceLineNo] = [
                'account_id' => $account,
                'debit' => $debit ?? 0,
                'credit' => $credit ?? 0,
                'line_summary' => trim((string) ($rawLine['line_summary'] ?? '')) ?: null,
                'refs' => [],
                'recommend_source' => trim((string) ($rawLine['recommend_source'] ?? $rawLine['source'] ?? '')) ?: null,
                'recommend_confidence' => $rawLine['recommend_confidence'] ?? $rawLine['confidence'] ?? null,
                'journal_rule_id' => trim((string) ($rawLine['journal_rule_id'] ?? '')) ?: null,
                'recommend_reason' => trim((string) ($rawLine['recommend_reason'] ?? $rawLine['reason'] ?? '')) ?: null,
                'recommended_account_id' => trim((string) ($rawLine['recommended_account_id'] ?? $account)) ?: $account,
                'recommended_refs' => is_array($rawLine['recommended_refs'] ?? null) ? $rawLine['recommended_refs'] : [],
                'is_user_modified' => !empty($rawLine['is_user_modified']) ? 1 : 0,
            ];
            $ref = $this->bankVoucherLineRefForSave($rawLine);
            if ($ref !== null) {
                $journalBySourceNo[$sourceLineNo]['refs'][] = $ref;
            }
        }

        foreach (array_keys($refsBySourceNo) as $sourceLineNo) {
            if (!array_key_exists($sourceLineNo, $journalBySourceNo)) {
                $errors[] = '보조 라인이 가리키는 전표 라인을 찾을 수 없습니다. 원본 라인 번호를 확인해주세요. ' . $sourceLineNo;
            }
        }

        if ($errors !== []) {
            throw new \RuntimeException(implode(', ', array_values(array_unique($errors))));
        }

        ksort($journalBySourceNo, SORT_NUMERIC);
        $lines = [];
        foreach ($journalBySourceNo as $sourceLineNo => $line) {
            foreach ($refsBySourceNo[$sourceLineNo] ?? [] as $ref) {
                $line['refs'][] = $ref;
            }
            $lines[] = $line;
        }

        return $lines;
    }

    public function bankVoucherRawLineHasMeaningfulValue(array $line): bool
    {
        foreach (['line_no', 'line_row_type', 'account_id', 'debit', 'credit', 'line_summary', 'line_ref_target', 'line_ref_id'] as $key) {
            if (trim((string) ($line[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    public function bankVoucherLineRefForSave(array $line): ?array
    {
        $refType = $this->call('normalizeVoucherRefType', (string) ($line['line_ref_target'] ?? ''));
        $refId = trim((string) ($line['line_ref_id'] ?? ''));
        if ($refType === '' || $refId === '') {
            return null;
        }

        return [
            'ref_target' => $refType,
            'ref_id' => $this->call('resolveVoucherRefId', $refType, $refId) ?? $refId,
        ];
    }

    public function bankVoucherDirectionAndAmount(array $row): array
    {
        $withdraw = $this->call('amountOrNull', $row['withdraw_amount'] ?? null);
        $deposit = $this->call('amountOrNull', $row['deposit_amount'] ?? null);
        if ($withdraw !== null && abs($withdraw) > 0) {
            return ['OUT', abs($withdraw)];
        }
        if ($deposit !== null && abs($deposit) > 0) {
            return ['IN', abs($deposit)];
        }

        return [null, null];
    }

    public function tagCreatedVoucher(string $voucherId, string $evidenceId, string $transactionId, string $actor): void
    {
        return;
    }

    public function existingVoucherForEvidenceId(string $evidenceId): ?array
    {
        if ($evidenceId === '' || !$this->call('tableExists', 'ledger_evidence_links') || !$this->call('tableExists', 'ledger_vouchers')) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                v.id,
                v.voucher_no,
                v.voucher_date,
                v.summary
            FROM ledger_evidence_links l
            INNER JOIN ledger_vouchers v
                ON v.id = l.target_id
               AND v.deleted_at IS NULL
            WHERE l.evidence_id = :evidence_id
              AND l.target_type = 'VOUCHER'
              AND l.deleted_at IS NULL
            ORDER BY v.created_at DESC, v.sort_no DESC
            LIMIT 1
        ");
        $stmt->execute([':evidence_id' => $evidenceId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existingVoucherForBankPayload(string $evidenceId, array $row): ?array
    {
        $existing = $this->existingVoucherForEvidenceId($evidenceId);
        if ($existing) {
            return $existing;
        }

        return $this->existingBankVoucherForPayloadFingerprint($row);
    }

    public function existingBankVoucherForPayloadFingerprint(array $row): ?array
    {
        if (!$this->call('tableExists', 'ledger_vouchers') || !$this->call('tableExists', 'ledger_voucher_lines')) {
            return null;
        }

        $dataType = $this->call('normalizeDataType', (string) ($row['import_type'] ?? $row['source_type'] ?? 'BANK_TRANSACTION'));
        if ($dataType !== 'BANK_TRANSACTION') {
            return null;
        }

        $voucherDate = $this->call('dateValue', $row['voucher_date'] ?? $row['transaction_date'] ?? $row['evidence_date'] ?? '');
        [, $paymentAmount] = $this->bankVoucherDirectionAndAmount($row);
        $amount = $paymentAmount ?? $this->call('amountOrNull', $row['total_amount'] ?? $row['amount'] ?? null);
        if ($voucherDate === '' || $amount === null || abs((float) $amount) <= 0) {
            return null;
        }

        $lineDeletedFilter = $this->call('tableColumnExists', 'ledger_voucher_lines', 'deleted_at') ? 'AND l.deleted_at IS NULL' : '';
        $groupBy = ['v.id', 'v.voucher_no', 'v.voucher_date', 'v.summary', 'v.created_at', 'v.sort_no'];

        $stmt = $this->pdo->prepare("
            SELECT
                v.id,
                v.voucher_no,
                v.voucher_date,
                COALESCE(v.summary, '') AS summary,
                COALESCE(SUM(l.debit), 0) AS debit_total,
                COALESCE(SUM(l.credit), 0) AS credit_total
            FROM ledger_vouchers v
            INNER JOIN ledger_voucher_lines l
                ON l.voucher_id = v.id
                {$lineDeletedFilter}
            WHERE v.deleted_at IS NULL
              AND v.voucher_date = :voucher_date
            GROUP BY " . implode(', ', $groupBy) . "
            HAVING ABS(GREATEST(debit_total, credit_total) - :amount) < 0.01
            ORDER BY v.created_at DESC, v.sort_no DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':voucher_date' => $voucherDate,
            ':amount' => abs((float) $amount),
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function evidenceRowsForExistingVoucherCheck(array $evidenceIds): array
    {
        $evidenceIds = array_values(array_filter(array_unique(array_map('strval', $evidenceIds))));
        if ($evidenceIds === []) {
            return [];
        }

        [$inSql, $params] = $this->call('placeholdersForIds', $evidenceIds, 'existing_voucher_check_evidence');
        $transactionSelect = $this->call('evidenceHasTransactionIdColumn') ? ', e.transaction_id AS transaction_id' : ", NULL AS transaction_id";
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.source_type, e.evidence_date,
                   e.client_id, e.project_id, e.employee_id, e.bank_account_id, e.card_id,
                   e.client_name, e.project_name, e.employee_name, e.bank_account_name, e.card_name,
                   e.mapped_payload_json
                   {$transactionSelect}
            FROM ledger_data_evidences e
            WHERE e.id IN ({$inSql})
              AND e.deleted_at IS NULL
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function linkVoucherToTransaction(string $voucherId, string $transactionId, mixed $matchAmount, string $linkType, string $actor): void
    {
        if ($voucherId === '' || $transactionId === '') {
            return;
        }

        (new TransactionLinkModel($this->pdo))->insertOrRestore($transactionId, $voucherId, $matchAmount, $linkType, $actor);
    }

    public function linkVoucherToEvidence(string $evidenceId, string $voucherId, string $transactionId, string $actor): void
    {
        if ($evidenceId === '' || $voucherId === '' || !$this->call('tableExists', 'ledger_evidence_links')) {
            return;
        }

        $existing = $this->pdo->prepare("
            SELECT id, deleted_at
            FROM ledger_evidence_links
            WHERE evidence_id = :evidence_id
              AND target_type = 'VOUCHER'
              AND target_id = :voucher_id
              AND link_type = 'AUTO'
            ORDER BY deleted_at IS NULL DESC, updated_at DESC, created_at DESC
            LIMIT 1
        ");
        $existing->execute([
            ':evidence_id' => $evidenceId,
            ':voucher_id' => $voucherId,
        ]);
        $row = $existing->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) {
            $this->pdo->prepare("
                UPDATE ledger_evidence_links
                SET amount = 0,
                    deleted_at = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                ':id' => (string) $row['id'],
            ]);
            return;
        }

        $this->pdo->prepare("
            INSERT INTO ledger_evidence_links
                (id, evidence_type, evidence_id, target_type, target_id, link_type, amount, created_at, updated_at)
            SELECT
                :id, e.source_type, e.id, 'VOUCHER', :voucher_id, 'AUTO', 0, NOW(), NOW()
            FROM ledger_data_evidences e
            WHERE e.id = :evidence_id
              AND e.deleted_at IS NULL
            LIMIT 1
        ")->execute([
            ':id' => UuidHelper::generate(),
            ':evidence_id' => $evidenceId,
            ':voucher_id' => $voucherId,
        ]);
    }

    public function updateEvidenceVoucherStatus(string $evidenceId, string $voucherStatus, string $actor, ?string $errorMessage = null): void
    {
        if ($evidenceId === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_data_evidences
            SET voucher_status = :voucher_status,
                error_message = :error_message,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':id' => $evidenceId,
            ':voucher_status' => $voucherStatus,
            ':error_message' => $errorMessage,
            ':updated_by' => $actor,
        ]);
    }

    private function call(string $name, mixed ...$args): mixed
    {
        $callback = $this->callbacks[$name] ?? null;
        if (!is_callable($callback)) {
            throw new \RuntimeException("VoucherCreateService callback missing: {$name}");
        }

        return $callback(...$args);
    }
}
