<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Models\Ledger\VoucherPaymentModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\RefTypeHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class VoucherValidationException extends \RuntimeException
{
    public function __construct(string $message, private readonly string $validationType)
    {
        parent::__construct($message);
    }

    public function getValidationType(): string
    {
        return $this->validationType;
    }
}

class VoucherService
{
    private const STATUS_VALUES = ['draft', 'confirmed', 'posted', 'closed', 'deleted'];
    private const SOURCE_TYPE_VALUES = ['TAX', 'CARD', 'BANK', 'MANUAL'];
    private const EDITABLE_STATUS_VALUES = ['draft'];

    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefModel $voucherLineRefModel;
    private VoucherPaymentModel $voucherPaymentModel;
    private TransactionLinkModel $transactionLinkModel;
    private TransactionModel $transactionModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
        $this->voucherLineRefModel = new VoucherLineRefModel($pdo);
        $this->voucherPaymentModel = new VoucherPaymentModel($pdo);
        $this->transactionLinkModel = new TransactionLinkModel($pdo);
        $this->transactionModel = new TransactionModel($pdo);
    }

    public function save(array $data): array
    {
        $actor = ActorHelper::user();
        $voucherId = trim((string) ($data['id'] ?? ''));
        $voucherDate = trim((string) ($data['voucher_date'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'draft'));
        $linkedTransactionId = trim((string) ($data['linked_transaction_id'] ?? ''));
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        $payments = is_array($data['payments'] ?? null) ? $data['payments'] : [];
        $existingVoucher = $voucherId !== '' ? $this->voucherModel->getById($voucherId) : null;
        $sourceType = $this->resolveVoucherSourceType($data, $existingVoucher);
        $sourceId = $this->resolveVoucherSourceId($sourceType, $existingVoucher);

        $validation = $this->validateVoucher(
            $voucherId,
            $voucherDate,
            $status,
            $sourceType,
            $linkedTransactionId,
            $lines,
            $payments
        );
        $normalizedLines = $validation['lines'];
        $normalizedPayments = $validation['payments'];
        $voucherAmount = $validation['voucher_amount'];
        $timestamp = date('Y-m-d H:i:s');

        try {
            $this->pdo->beginTransaction();

            if ($voucherId === '') {
                $voucherId = UuidHelper::generate();
                $voucherNo = $this->resolveVoucherNo($data, $voucherDate);

                $headerPayload = [
                    'id' => $voucherId,
                    'sort_no' => SequenceHelper::next('ledger_vouchers', 'sort_no'),
                    'voucher_no' => $voucherNo,
                    'voucher_date' => $voucherDate,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'status' => $status,
                    'summary_text' => $this->resolveSummaryText($data, $normalizedLines),
                    'note' => $this->nullableString($data['note'] ?? null),
                    'memo' => $this->nullableString($data['memo'] ?? null),
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];
                if ($this->hasColumn('ledger_vouchers', 'voucher_amount')) {
                    $headerPayload['voucher_amount'] = $voucherAmount;
                }
                $saved = $this->voucherModel->insert($headerPayload);
            } else {
                $existing = $existingVoucher;
                if (!$existing) {
                    throw new \RuntimeException('전표를 찾을 수 없습니다.');
                }

                $this->assertVoucherEditable($existing);

                $payload = [
                    'voucher_date' => $voucherDate,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'status' => $status,
                    'summary_text' => $this->resolveSummaryText($data, $normalizedLines),
                    'note' => $this->nullableString($data['note'] ?? null),
                    'memo' => $this->nullableString($data['memo'] ?? null),
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if (trim((string) ($existing['voucher_no'] ?? '')) === '') {
                    $payload['voucher_no'] = $this->resolveVoucherNo($data, $voucherDate);
                }
                if ($this->hasColumn('ledger_vouchers', 'voucher_amount')) {
                    $payload['voucher_amount'] = $voucherAmount;
                }

                $saved = $this->voucherModel->update($voucherId, $payload);
            }

            if (!$saved) {
                throw new \RuntimeException('전표 저장에 실패했습니다.');
            }

            $this->deleteVoucherChildren($voucherId);
            foreach ($normalizedLines as $line) {
                $lineId = UuidHelper::generate();
                $ok = $this->voucherLineModel->insert([
                    'id' => $lineId,
                    'sort_no' => SequenceHelper::next('ledger_voucher_lines', 'sort_no'),
                    'voucher_id' => $voucherId,
                    'line_no' => $line['line_no'],
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'line_summary' => $line['line_summary'],
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ]);

                if (!$ok) {
                    throw new \RuntimeException('분개라인 저장에 실패했습니다.');
                }

                $this->voucherLineRefModel->bulkInsert($lineId, $line['refs'], $actor, $timestamp);
            }

            foreach ($normalizedPayments as $payment) {
                $ok = $this->voucherPaymentModel->insert([
                    'id' => UuidHelper::generate(),
                    'sort_no' => SequenceHelper::next('ledger_voucher_payments', 'sort_no'),
                    'voucher_id' => $voucherId,
                    'direction' => $payment['payment_direction'],
                    'payment_direction' => $payment['payment_direction'],
                    'payment_type' => $payment['payment_type'],
                    'payment_id' => $payment['payment_id'],
                    'amount' => $payment['amount'],
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                ]);
                if (!$ok) {
                    throw new \RuntimeException('결제수단 저장에 실패했습니다.');
                }
            }

            $this->replaceManualTransactionLink($voucherId, $linkedTransactionId, $actor, $timestamp);

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $voucherId,
                'voucher_id' => $voucherId,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function reorder(array $changes): bool
    {
        if ($changes === []) {
            return true;
        }

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as $row) {
                if (empty($row['id']) || !isset($row['newSortNo'])) {
                    throw new \RuntimeException('정렬 데이터가 올바르지 않습니다.');
                }
            }

            foreach ($changes as $row) {
                $this->voucherModel->updateSortNo((string) $row['id'], (int) $row['newSortNo'] + 1000000);
            }

            foreach ($changes as $row) {
                $this->voucherModel->updateSortNo((string) $row['id'], (int) $row['newSortNo']);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function deleteVoucher(string $voucherId): void
    {
        $voucherId = trim($voucherId);
        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 없습니다.');
        }

        $voucher = $this->voucherModel->getById($voucherId);
        if (!$voucher) {
            throw new \RuntimeException('전표를 찾을 수 없습니다.');
        }

        if (($voucher['status'] ?? '') !== 'draft') {
            throw new \RuntimeException('draft 상태의 전표만 삭제할 수 있습니다.');
        }

        $actor = ActorHelper::user();

        try {
            $this->pdo->beginTransaction();

            $this->deleteVoucherChildren($voucherId);

            $this->pdo->prepare("
                UPDATE ledger_transaction_links
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by
                WHERE voucher_id = :voucher_id
                  AND deleted_at IS NULL
            ")->execute([
                ':voucher_id' => $voucherId,
                ':deleted_by' => $actor,
            ]);

            if (!$this->voucherModel->softDelete($voucherId, $actor)) {
                throw new \RuntimeException('전표 삭제에 실패했습니다.');
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function searchSummaryTexts(string $keyword, int $limit = 10): array
    {
        $keyword = $this->normalizeSummaryText($keyword) ?? '';
        if (mb_strlen($keyword, 'UTF-8') < 2) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'summary_text' => (string) ($row['summary_text'] ?? ''),
                'used_count' => (int) ($row['used_count'] ?? 0),
                'last_used_at' => $row['last_used_at'] ?? null,
            ];
        }, $this->voucherModel->searchSummaryTexts($keyword, $limit));
    }

    public function updateStatus(string $voucherId, string $nextStatus): array
    {
        $voucherId = trim($voucherId);
        $nextStatus = trim($nextStatus);
        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 없습니다.');
        }

        $voucher = $this->voucherModel->getById($voucherId);
        if (!$voucher || !empty($voucher['deleted_at'])) {
            throw new \RuntimeException('전표를 찾을 수 없습니다.');
        }

        $currentStatus = (string) ($voucher['status'] ?? '');
        $allowedNext = [
            'draft' => 'confirmed',
            'confirmed' => 'posted',
            'posted' => 'closed',
        ];

        if (($allowedNext[$currentStatus] ?? null) !== $nextStatus) {
            throw new \RuntimeException('허용되지 않는 상태 변경입니다.');
        }

        $sourceType = strtoupper(trim((string) ($voucher['source_type'] ?? '')));
        if (!in_array($sourceType, self::SOURCE_TYPE_VALUES, true)) {
            $this->validationError('자료출처가 올바르지 않습니다.', 'source_type');
        }

        $lines = $this->getPersistedVoucherLinesForValidation($voucherId);
        $this->validateVoucherBalance($lines);
        $this->validateVoucherSubAccountPolicies($lines);

        $updated = $this->voucherModel->update($voucherId, [
            'status' => $nextStatus,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => ActorHelper::user(),
        ]);

        if (!$updated) {
            throw new \RuntimeException('전표 상태 변경에 실패했습니다.');
        }

        return [
            'success' => true,
            'id' => $voucherId,
            'status' => $nextStatus,
        ];
    }

    private function validateVoucher(
        string $voucherId,
        string $voucherDate,
        string $status,
        string $sourceType,
        string $linkedTransactionId,
        array $lines,
        array $payments
    ): array {
        if ($voucherDate === '') {
            $this->validationError('전표일자를 입력해 주세요.', 'voucher_date');
        }

        if (!in_array($status, self::STATUS_VALUES, true)) {
            $this->validationError('올바른 전표 상태를 선택해 주세요.', 'status');
        }

        if ($status === 'deleted') {
            $this->validationError('삭제 상태는 저장으로 변경할 수 없습니다.', 'voucher_status');
        }

        if (!in_array($sourceType, self::SOURCE_TYPE_VALUES, true)) {
            $this->validationError('자료출처가 올바르지 않습니다.', 'source_type');
        }

        if ($voucherId !== '') {
            $existing = $this->voucherModel->getById($voucherId);
            if (!$existing) {
                $this->validationError('전표를 찾을 수 없습니다.', 'voucher_status');
            }
            if (($existing['status'] ?? '') !== 'draft' || !empty($existing['deleted_at'])) {
                $this->validationError('해당 전표는 수정할 수 없는 상태입니다.', 'voucher_status');
            }
        }

        if ($linkedTransactionId !== '' && !$this->transactionModel->getById($linkedTransactionId)) {
            $this->validationError('선택한 거래를 찾을 수 없습니다.', 'linked_transaction');
        }

        $normalizedLines = $this->normalizeVoucherLines($lines);
        $normalizedPayments = $this->normalizePayments($payments);
        $totals = $this->validateVoucherBalance($normalizedLines);
        $this->validateVoucherSubAccountPolicies($normalizedLines);

        return [
            'lines' => $normalizedLines,
            'payments' => $normalizedPayments,
            'voucher_amount' => number_format($totals['debit_sum'], 2, '.', ''),
        ];
    }

    private function validateVoucherBalance(array $lines): array
    {
        $debitSum = 0.0;
        $creditSum = 0.0;

        foreach ($lines as $line) {
            $debitSum += (float) $line['debit'];
            $creditSum += (float) $line['credit'];
        }

        if ($debitSum <= 0 || $creditSum <= 0 || round($debitSum, 2) !== round($creditSum, 2)) {
            $this->validationError('차변합계와 대변합계가 일치하지 않습니다.', 'balance');
        }

        return [
            'debit_sum' => $debitSum,
            'credit_sum' => $creditSum,
        ];
    }

    private function normalizePayments(array $payments): array
    {
        $normalized = [];

        foreach ($payments as $index => $payment) {
            $paymentDirection = strtoupper(trim((string) ($payment['payment_direction'] ?? $payment['direction'] ?? 'OUT')));
            $paymentType = strtoupper(trim((string) ($payment['payment_type'] ?? '')));
            $paymentId = trim((string) ($payment['payment_id'] ?? ''));
            $amount = round($this->parseAmount($payment['amount'] ?? 0), 2);

            if ($paymentType === '' && $paymentId === '' && $amount <= 0) {
                continue;
            }

            if (!in_array($paymentDirection, ['IN', 'OUT'], true)) {
                $this->validationError(($index + 1) . '번째 입/출금 구분이 올바르지 않습니다.', 'payment');
            }

            if (!in_array($paymentType, ['ACCOUNT', 'CARD'], true)) {
                $this->validationError(($index + 1) . '번째 결제유형이 올바르지 않습니다.', 'payment');
            }

            if ($paymentId === '') {
                $this->validationError(($index + 1) . '번째 결제수단을 선택해 주세요.', 'payment');
            }

            if ($amount <= 0) {
                $this->validationError(($index + 1) . '번째 결제금액을 입력해 주세요.', 'payment');
            }

            $this->validateRefTarget($paymentType, $paymentId);

            $normalized[] = [
                'payment_direction' => $paymentDirection,
                'payment_type' => $paymentType,
                'payment_id' => $paymentId,
                'amount' => number_format($amount, 2, '.', ''),
            ];
        }

        return $normalized;
    }

    private function normalizeVoucherLines(array $lines): array
    {
        $normalized = [];
        $lineNo = 1;

        foreach ($lines as $line) {
            $accountValue = trim((string) ($line['account_id'] ?? $line['account_code'] ?? ''));
            $refs = $this->normalizeLineRefs($line);
            $debit = round($this->parseAmount($line['debit'] ?? 0), 2);
            $credit = round($this->parseAmount($line['credit'] ?? 0), 2);
            $lineSummary = $this->nullableString($line['line_summary'] ?? null);

            if ($accountValue === '' && $refs === [] && $debit === 0.0 && $credit === 0.0 && $lineSummary === null) {
                continue;
            }

            if ($accountValue === '') {
                $this->validationError('계정과목을 선택해 주세요.', 'line_account');
            }

            $account = $this->resolveAccount($accountValue);
            if ($account === null) {
                $this->validationError('선택한 계정과목을 찾을 수 없습니다.', 'line_account');
            }

            foreach ($refs as $ref) {
                $this->validateRefTarget($ref['ref_type'], $ref['ref_id']);
            }

            if (!(($debit > 0 && $credit == 0.0) || ($debit == 0.0 && $credit > 0))) {
                $this->validationError('각 라인은 차변 또는 대변 중 하나만 입력해야 합니다.', 'line_amount');
            }

            $normalized[] = [
                'line_no' => $lineNo,
                'account_id' => (string) $account['id'],
                'account_name' => (string) $account['account_name'],
                'refs' => $refs,
                'debit' => number_format(max($debit, 0), 2, '.', ''),
                'credit' => number_format(max($credit, 0), 2, '.', ''),
                'line_summary' => $lineSummary,
            ];
            $lineNo++;
        }

        if ($normalized === []) {
            $this->validationError('분개라인을 1건 이상 입력해 주세요.', 'line');
        }

        return $normalized;
    }

    private function normalizeLineRefs(array $line): array
    {
        $rawRefs = is_array($line['refs'] ?? null) ? $line['refs'] : [];

        if ($rawRefs === [] && (trim((string) ($line['ref_type'] ?? '')) !== '' || trim((string) ($line['ref_id'] ?? '')) !== '')) {
            $rawRefs[] = [
                'ref_type' => $line['ref_type'] ?? '',
                'ref_id' => $line['ref_id'] ?? '',
            ];
        }

        $refs = [];
        $seenRefs = [];

        foreach ($rawRefs as $ref) {
            $refType = strtoupper(trim((string) ($ref['ref_type'] ?? '')));
            $refId = trim((string) ($ref['ref_id'] ?? ''));

            if ($refType === '' && $refId === '') {
                continue;
            }

            if ($refType === '') {
                $this->validationError('보조계정 유형(ref_type)이 필요합니다.', 'line_ref');
            }

            if ($refId === '') {
                $this->validationError('보조계정 대상(ref_id)이 필요합니다.', 'line_ref');
            }

            $refKey = $refType . ':' . $refId;
            if (isset($seenRefs[$refKey])) {
                $this->validationError('동일한 보조계정이 중복 입력되었습니다.', 'line_ref_duplicate');
            }

            $seenRefs[$refKey] = true;
            $refs[] = [
                'ref_type' => $refType,
                'ref_id' => $refId,
                'is_primary' => (int) ($ref['is_primary'] ?? ($refs === [] ? 1 : 0)),
            ];
        }

        return $refs;
    }
    private function validateVoucherSubAccountPolicies(array $lines): void
    {
        $policyColumns = ['ref_type', 'is_required'];
        if ($this->hasColumn('ledger_sub_accounts', 'sub_code')) {
            $policyColumns[] = 'sub_code';
        }

        $policyStmt = $this->pdo->prepare("
            SELECT " . implode(', ', $policyColumns) . "
            FROM ledger_sub_accounts
            WHERE account_id = :account_id
        ");

        foreach ($lines as $line) {
            $accountId = (string) $line['account_id'];
            $accountName = trim((string) ($line['account_name'] ?? $accountId));
            $refs = is_array($line['refs'] ?? null) ? $line['refs'] : [];

            $policyStmt->execute([':account_id' => $accountId]);
            $policies = $policyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $requiredTypes = [];
            foreach ($policies as $policy) {
                $policyRefType = $this->resolveSubAccountPolicyRefType($policy);
                if ($policyRefType === '') {
                    continue;
                }

                if ((int) ($policy['is_required'] ?? 0) === 1) {
                    $requiredTypes[] = $policyRefType;
                }
            }

            if ($requiredTypes === []) {
                continue;
            }

            $selectedMap = [];
            foreach ($refs as $ref) {
                $refType = strtoupper(trim((string) ($ref['ref_type'] ?? '')));
                $refId = trim((string) ($ref['ref_id'] ?? ''));
                if ($refType !== '' && $refId !== '') {
                    $selectedMap[$refType] = true;
                    $selectedMap[$this->normalizeRefTypeAlias($refType)] = true;
                }
            }

            $missingTypes = [];
            foreach ($requiredTypes as $requiredType) {
                if (empty($selectedMap[$requiredType]) && empty($selectedMap[$this->normalizeRefTypeAlias($requiredType)])) {
                    $missingTypes[] = $requiredType;
                }
            }

            if ($missingTypes === []) {
                continue;
            }

            if (count($requiredTypes) > 1) {
                $this->validationError(
                    $accountName . ' 계정은 ' . $this->joinRefTypeLabels($requiredTypes) . ' 모두 필수입니다.',
                    'required_ref'
                );
            }

            $this->validationError(
                '필수 보조계정이 누락되었습니다. (계정: ' . $accountName . ', 기준: ' . $this->joinRefTypeLabels($missingTypes) . ')',
                'required_ref'
            );
        }
    }

    private function resolveSubAccountPolicyRefType(array $policy): string
    {
        $refType = strtoupper(trim((string) ($policy['ref_type'] ?? '')));
        $subCode = strtoupper(trim((string) ($policy['sub_code'] ?? '')));

        if ($refType === 'REF_TARGET') {
            return $subCode;
        }

        return $refType !== '' ? $refType : $subCode;
    }

    private function normalizeRefTypeAlias(string $refType): string
    {
        return match (strtoupper(trim($refType))) {
            'BANK', 'BANK_ACCOUNT' => 'ACCOUNT',
            default => strtoupper(trim($refType)),
        };
    }

    private function joinRefTypeLabels(array $refTypes): string
    {
        $labels = array_values(array_unique(array_map(
            static fn(string $refType): string => RefTypeHelper::label($refType),
            $refTypes
        )));

        if (count($labels) <= 1) {
            return $labels[0] ?? '';
        }

        if (count($labels) === 2) {
            return $labels[0] . '와 ' . $labels[1];
        }

        $last = array_pop($labels);
        return implode(', ', $labels) . '와 ' . $last;
    }

    private function validateRefTarget(string $refType, string $refId): void
    {
        $table = match ($refType) {
            'ACCOUNT' => 'system_bank_accounts',
            'CLIENT' => 'system_clients',
            'PROJECT' => 'system_projects',
            'EMPLOYEE' => 'user_employees',
            'CARD' => 'system_cards',
            'BANK', 'BANK_ACCOUNT' => 'system_bank_accounts',
            'TRANSACTION' => 'ledger_transactions',
            'VOUCHER' => 'ledger_vouchers',
            'PAYMENT' => 'ledger_voucher_payments',
            'CONTRACT' => null,
            'ORDER' => null,
            default => false,
        };

        if ($table === false) {
            $this->validationError('지원하지 않는 보조계정 유형입니다.', 'ref_target');
        }

        if ($table === null) {
            if ($refId === '') {
                $this->validationError('보조계정 대상 ID가 필요합니다.', 'ref_target');
            }
            return;
        }

        if (!$this->existsById($table, $refId)) {
            $this->validationError('선택한 보조계정 대상을 찾을 수 없습니다.', 'ref_target');
        }
    }
    private function assertExists(string $table, string $id, string $message): void
    {
        if (!$this->existsById($table, $id)) {
            throw new \RuntimeException($message);
        }
    }

    private function existsById(string $table, string $id): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name.');
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }

    private function validationError(string $message, string $validationType): never
    {
        throw new VoucherValidationException($message, $validationType);
    }

    private function assertVoucherEditable(array $voucher): void
    {
        $status = (string) ($voucher['status'] ?? '');
        if (!in_array($status, self::EDITABLE_STATUS_VALUES, true)) {
            $this->validationError('해당 전표는 수정할 수 없는 상태입니다.', 'voucher_status');
        }

        if (!empty($voucher['deleted_at'])) {
            $this->validationError('해당 전표는 수정할 수 없는 상태입니다.', 'voucher_status');
        }
    }

    private function deleteVoucherChildren(string $voucherId): void
    {
        $this->voucherLineModel->purgeByVoucherId($voucherId);
        $this->voucherPaymentModel->purgeByVoucherId($voucherId);
    }

    private function getPersistedVoucherLinesForValidation(string $voucherId): array
    {
        $lines = $this->voucherLineModel->getByVoucherId($voucherId);
        $lineRefs = $this->voucherLineRefModel->getGroupedByVoucherLineIds(array_column($lines, 'id'));

        return array_map(static function (array $line) use ($lineRefs): array {
            return [
                'account_id' => (string) ($line['account_id'] ?? ''),
                'refs' => array_map(static fn(array $ref): array => [
                    'ref_type' => (string) ($ref['ref_type'] ?? ''),
                    'ref_id' => (string) ($ref['ref_id'] ?? ''),
                ], $lineRefs[$line['id']] ?? []),
                'debit' => (string) ($line['debit'] ?? '0'),
                'credit' => (string) ($line['credit'] ?? '0'),
            ];
        }, $lines);
    }

    private function resolveVoucherNo(array $data, string $voucherDate): string
    {
        $voucherNo = trim((string) ($data['voucher_no'] ?? ''));
        if ($voucherNo !== '') {
            return $voucherNo;
        }

        return $this->nextVoucherNo($voucherDate);
    }

    private function nextVoucherNo(string $voucherDate): string
    {
        $dateKey = preg_replace('/[^0-9]/', '', $voucherDate) ?: date('Ymd');
        $prefix = substr($dateKey, 0, 8);

        $stmt = $this->pdo->prepare("
            SELECT voucher_no
            FROM ledger_vouchers
            WHERE voucher_no LIKE :prefix
            ORDER BY voucher_no DESC
            LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix . '-%']);

        $latest = (string) ($stmt->fetchColumn() ?: '');
        $next = 1;
        if (preg_match('/-(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function resolveVoucherSourceType(array $data, ?array $existingVoucher): string
    {
        $sourceType = strtoupper(trim((string) ($data['source_type'] ?? $existingVoucher['source_type'] ?? '')));

        return $sourceType;
    }

    private function resolveVoucherSourceId(string $sourceType, ?array $existingVoucher): ?string
    {
        if ($sourceType === 'MANUAL' || $existingVoucher === null) {
            return null;
        }

        $existingSourceType = strtoupper(trim((string) ($existingVoucher['source_type'] ?? '')));
        if ($existingSourceType !== $sourceType) {
            return null;
        }

        return $this->nullableString($existingVoucher['source_id'] ?? null);
    }

    private function replaceManualTransactionLink(
        string $voucherId,
        string $transactionId,
        string $actor,
        string $timestamp
    ): void {
        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_transaction_links
            WHERE voucher_id = :voucher_id
              AND link_type = 'MANUAL'
        ");
        $stmt->execute([':voucher_id' => $voucherId]);

        if ($transactionId === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_transaction_links
            WHERE voucher_id = :voucher_id
              AND transaction_id = :transaction_id
        ");
        $stmt->execute([
            ':voucher_id' => $voucherId,
            ':transaction_id' => $transactionId,
        ]);

        $ok = $this->transactionLinkModel->insert([
            'id' => UuidHelper::generate(),
            'transaction_id' => $transactionId,
            'voucher_id' => $voucherId,
            'link_type' => 'MANUAL',
            'is_active' => 1,
            'created_at' => $timestamp,
            'created_by' => $actor,
            'updated_at' => $timestamp,
            'updated_by' => $actor,
        ]);

        if (!$ok) {
            throw new \RuntimeException('거래 연결 저장에 실패했습니다.');
        }
    }

    private function resolveAccount(string $accountValue): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                account_code,
                COALESCE(NULLIF(account_name, ''), NULLIF(account_code, ''), id) AS account_name
            FROM ledger_accounts
            WHERE deleted_at IS NULL
              AND (id = :id_value OR account_code = :code_value)
            LIMIT 1
        ");
        $stmt->execute([
            ':id_value' => $accountValue,
            ':code_value' => $accountValue,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function resolveSummaryText(array $data, array $lines): ?string
    {
        $summaryText = $this->normalizeSummaryText($data['summary_text'] ?? null);
        if ($summaryText !== null) {
            return $summaryText;
        }

        $firstLine = $lines[0] ?? null;
        if (!is_array($firstLine)) {
            return null;
        }

        $accountName = trim((string) ($firstLine['account_name'] ?? $firstLine['account_id'] ?? ''));
        if ($accountName === '') {
            return null;
        }

        $extraCount = max(count($lines) - 1, 0);

        return $extraCount > 0 ? $accountName . ' 외 ' . $extraCount . '건' : $accountName;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid table or column name.');
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
              AND column_name = :column_name
            LIMIT 1
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function parseAmount(mixed $value): float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) ($value ?? '')));

        if ($cleaned === '' || $cleaned === '-' || $cleaned === '.' || $cleaned === '-.') {
            return 0.0;
        }

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? null : $string;
    }

    private function normalizeSummaryText(mixed $value): ?string
    {
        $string = preg_replace('/\s+/u', ' ', trim((string) ($value ?? '')));

        return $string === '' ? null : $string;
    }
}



