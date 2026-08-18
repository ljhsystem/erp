<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceLinkModel;
use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\Ledger\VoucherModel;
use Core\Helpers\ActorHelper;
use PDO;

class VoucherCreateService
{
    private EvidenceLinkModel $evidenceLinkModel;
    private EvidenceSchemaModel $evidenceSchemaModel;
    private VoucherModel $voucherModel;

    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
        $this->evidenceLinkModel = new EvidenceLinkModel($pdo);
        $this->evidenceSchemaModel = new EvidenceSchemaModel($pdo);
        $this->voucherModel = new VoucherModel($pdo);
    }

    public function createVoucherFromBankPayload(string $evidenceId, array $row, bool $linkExistingVoucher = false): ?string
    {
        if (!$this->call('hasVoucherLinesPayload', $row)) {
            return null;
        }

        $actor = ActorHelper::user();
        try {
            $existingVoucher = $this->existingVoucherForBankPayload($evidenceId, $row);
            if ($existingVoucher) {
                $voucherId = (string) ($existingVoucher['id'] ?? '');
                if ($voucherId !== '' && $linkExistingVoucher) {
                    $this->linkVoucherToEvidence($evidenceId, $voucherId, $actor, (string) ($row['import_type'] ?? $row['source_type'] ?? ''));
                    return $voucherId;
                }

                return null;
            }

            $lines = $this->bankVoucherLinesForSave($row['_voucher_lines'] ?? []);
            if ($lines === []) {
                return null;
            }

            $lines = $this->call('applyEvidenceRefsToVoucherLines', $lines, $row);
            $result = $this->call('saveVoucher', [
                'voucher_date' => $this->call('dateValue', $row['voucher_date'] ?? $row['transaction_date'] ?? date('Y-m-d')),
                'summary' => trim((string) ($row['voucher_summary_text'] ?? $row['summary'] ?? $row['summary_text'] ?? $row['description'] ?? '')),
                'source_type' => $this->call('normalizeDataType', (string) ($row['import_type'] ?? $row['source_type'] ?? '')),
                'lines' => $lines,
                'linked_evidences' => [[
                    'import_type' => $this->call('normalizeDataType', (string) ($row['import_type'] ?? $row['source_type'] ?? '')),
                    'evidence_id' => $evidenceId,
                ]],
            ]);

            $voucherId = (string) ($result['voucher_id'] ?? $result['id'] ?? '');
            if ($voucherId !== '') {
            }

            if ($voucherId === '') {
                return null;
            }

            return $voucherId;
        } catch (\Throwable $e) {
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

    public function existingVoucherForEvidenceId(string $evidenceId, string $importType = ''): ?array
    {
        if ($evidenceId === '') {
            return null;
        }

        $importType = $this->call('normalizeDataType', $importType);
        return $importType === '' ? null : $this->evidenceLinkModel->findLinkedVoucherInfo($importType, $evidenceId);
    }

    public function existingVoucherForBankPayload(string $evidenceId, array $row): ?array
    {
        $existing = $this->existingVoucherForEvidenceId($evidenceId, (string) ($row['import_type'] ?? $row['source_type'] ?? ''));
        if ($existing) {
            return $existing;
        }

        return $this->existingBankVoucherForPayloadFingerprint($row);
    }

    public function existingBankVoucherForPayloadFingerprint(array $row): ?array
    {
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

        return $this->voucherModel->findActiveByDateAndAmount(
            $voucherDate,
            (float) $amount,
            $this->evidenceSchemaModel->columnExists('ledger_voucher_lines', 'deleted_at')
        );
    }

    public function evidenceRowsForExistingVoucherCheck(array $evidenceIds): array
    {
        return [];
    }

    public function linkVoucherToEvidence(string $evidenceId, string $voucherId, string $actor, string $importType = ''): void
    {
        if ($evidenceId === '' || $voucherId === '') {
            return;
        }

        $importType = $this->call('normalizeDataType', $importType);
        if ($importType === '') {
            throw new \RuntimeException('증빙 자료유형이 없어 전표를 연결할 수 없습니다.');
        }
        $this->evidenceLinkModel->upsertAutoVoucherEvidence($importType, $evidenceId, $voucherId);
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
