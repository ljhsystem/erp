<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionItemModel;
use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Models\Ledger\VoucherPaymentModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use PDOException;

class TransactionService
{
    public const STAGE_MIGRATION = 'migration';
    public const STAGE_OPERATIONAL = 'operational';
    private TransactionModel $transactionModel;
    private TransactionItemModel $transactionItemModel;
    private TransactionLinkModel $transactionLinkModel;
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefModel $voucherLineRefModel;
    private VoucherPaymentModel $voucherPaymentModel;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->transactionModel = new TransactionModel($pdo);
        $this->transactionItemModel = new TransactionItemModel($pdo);
        $this->transactionLinkModel = new TransactionLinkModel($pdo);
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
        $this->voucherLineRefModel = new VoucherLineRefModel($pdo);
        $this->voucherPaymentModel = new VoucherPaymentModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.TransactionService');
    }

    public function createTransaction(array $data): array
    {
        $actor = ActorHelper::user();
        $items = $this->normalizeTransactionItems(is_array($data['items'] ?? null) ? $data['items'] : [], $data);
        $totals = $this->resolveTransactionTotals($data, $this->calculateTransactionLineTotals($items));

        try {
            $this->validateTransactionAmounts($totals);

            $this->pdo->beginTransaction();

            $transactionId = (string) ($data['id'] ?? UuidHelper::generate());
            $transactionSortNo = null;
            $timestamp = date('Y-m-d H:i:s');

            $transactionPayload = [
                'id' => $transactionId,
                'sort_no' => SequenceHelper::next('ledger_transactions', 'sort_no'),
                'source_type' => $data['source_type'] ?? null,
                'import_type' => $data['import_type'] ?? $data['source_type'] ?? null,
                'business_unit' => $data['business_unit'] ?? 'HQ',
                'transaction_type' => $data['transaction_type'] ?? 'ETC',
                'transaction_direction' => $data['transaction_direction'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? date('Y-m-d'),
                'client_id' => $data['client_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'currency' => $data['currency'] ?? 'KRW',
                'transaction_exchange_rate' => $data['transaction_exchange_rate'] ?? null,
                'transaction_supply_amount' => $totals['transaction_supply_amount'],
                'transaction_settlement_amount' => $totals['transaction_settlement_amount'],
                'transaction_final_amount' => $totals['transaction_final_amount'],
                'transaction_description' => $data['transaction_description'] ?? null,
                'status' => 'draft',
                'match_status' => 'none',
                'transaction_note' => $data['transaction_note'] ?? null,
                'transaction_memo' => $data['transaction_memo'] ?? null,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionModel->insert($transactionPayload)) {
                throw new \RuntimeException('거래 저장에 실패했습니다.');
            }

            foreach ($items as $index => $item) {
                $itemName = trim((string) ($item['item_name'] ?? ''));
                if ($itemName === '') {
                    throw new \InvalidArgumentException(($index + 1) . '번째 거래 항목명을 입력해주세요.');
                }

                $itemPayload = [
                    'id' => (string) ($item['id'] ?? UuidHelper::generate()),
                    'transaction_id' => $transactionId,
                    'sort_no' => (int) ($item['sort_no'] ?? ($index + 1)),
                    'item_date' => $item['item_date'] ?? ($data['transaction_date'] ?? date('Y-m-d')),
                    'item_name' => $itemName,
                    'item_specification' => $item['item_specification'] ?? null,
                    'item_unit_name' => $item['item_unit_name'] ?? null,
                    'item_quantity' => $item['item_quantity'] ?? 0,
                    'item_unit_price' => $item['item_unit_price'] ?? 0,
                    'item_foreign_unit_price' => $item['item_foreign_unit_price'] ?? null,
                    'item_foreign_amount' => $item['item_foreign_amount'] ?? null,
                    'item_supply_amount' => $item['item_supply_amount'] ?? 0,
                    'item_tax_type' => $item['item_tax_type'] ?? null,
                    'item_description' => $item['item_description'] ?? null,
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if (!$this->transactionItemModel->insert($itemPayload)) {
                    throw new \RuntimeException(($index + 1) . '번째 거래 항목 저장에 실패했습니다.');
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $transactionId,
                'sort_no' => $transactionSortNo,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('createTransaction failed', [
                'exception' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getUnpostedTransactions(): array
    {
        try {
            return $this->transactionModel->getUnpostedList();
        } catch (\Throwable $e) {
            $this->logger->error('getUnpostedTransactions failed', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function matchTransaction(string $transactionId, array $data): array
    {
        $actor = ActorHelper::user();

        try {
            $transaction = $this->transactionModel->getById($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => '거래 내역을 찾을 수 없습니다.',
                ];
            }

            $lines = $this->normalizeMatchedLines($data['lines'] ?? []);
            $payments = $this->normalizePayments($data['payments'] ?? []);
            $timestamp = date('Y-m-d H:i:s');

            $matchPayload = [
                'voucher_date' => $data['voucher_date'] ?? ($transaction['transaction_date'] ?? date('Y-m-d')),
                'summary_text' => trim((string) ($data['summary_text'] ?? '')),
                'note' => $data['note'] ?? null,
                'memo' => $data['memo'] ?? null,
                'lines' => $lines,
                'payments' => $payments,
            ];

            $updateData = [
                'project_id' => $data['project_id'] ?? $transaction['project_id'] ?? null,
                'client_id' => $data['client_id'] ?? $transaction['client_id'] ?? null,
                'transaction_description' => $matchPayload['summary_text'] !== '' ? $matchPayload['summary_text'] : ($transaction['transaction_description'] ?? null),
                'transaction_note' => $matchPayload['note'],
                'transaction_memo' => $this->encodeMatchPayload($matchPayload),
                'match_status' => 'matched',
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionModel->update($transactionId, $updateData)) {
                return [
                    'success' => false,
                    'message' => '거래 매칭 정보를 저장하지 못했습니다.',
                ];
            }

            return [
                'success' => true,
                'status' => 'matched',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('matchTransaction failed', [
                'transaction_id' => $transactionId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function updateDocumentStatus(string $transactionId, string $docStatus): array
    {
        $actor = ActorHelper::user();
        $allowed = ['draft', 'statement_ok', 'tax_missing', 'tax_ok'];

        try {
            $transaction = $this->transactionModel->getById($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => '거래 문서를 찾을 수 없습니다.',
                ];
            }

            if (!in_array($docStatus, $allowed, true)) {
                return [
                    'success' => false,
                    'message' => '허용되지 않은 문서 상태입니다.',
                ];
            }

            $updated = $this->transactionModel->update($transactionId, [
                'doc_status' => $docStatus,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ]);

            if (!$updated) {
                return [
                    'success' => false,
                    'message' => '문서 상태를 변경하지 못했습니다.',
                ];
            }

            return [
                'success' => true,
                'doc_status' => $docStatus,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('updateDocumentStatus failed', [
                'transaction_id' => $transactionId,
                'doc_status' => $docStatus,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function createVoucherFromTransaction(string $transactionId): array
    {
        $actor = ActorHelper::user();

        try {
            if ($this->resolveOperationStage() !== self::STAGE_OPERATIONAL) {
                return [
                    'success' => false,
                    'message' => '운영 단계에서만 거래 내역으로 전표를 생성할 수 있습니다.',
                ];
            }

            $transaction = $this->transactionModel->getById($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => '거래 문서를 찾을 수 없습니다.',
                ];
            }

            if (($transaction['match_status'] ?? '') !== 'matched') {
                return [
                    'success' => false,
                    'message' => '거래 문서가 매칭되지 않아 전표를 생성할 수 없습니다.',
                ];
            }

            $existingLinks = $this->transactionLinkModel->getByTransactionId($transactionId);
            if ($existingLinks !== []) {
                return [
                    'success' => false,
                    'message' => '이미 연결된 전표가 있어 새 전표를 생성할 수 없습니다.',
                ];
            }

            $this->validateTransactionAmounts($transaction);

            $transactionItems = $this->transactionItemModel->getByTransactionId($transactionId);
            $matchPayload = $this->decodeMatchPayload((string) ($transaction['transaction_memo'] ?? ''));
            if ($matchPayload === null) {
                return [
                    'success' => false,
                    'message' => '매칭된 전표 정보가 없어 전표를 생성할 수 없습니다.',
                ];
            }

            $lines = $this->normalizeMatchedVoucherLines($matchPayload['lines'] ?? []);
            $payments = $this->normalizePayments($matchPayload['payments'] ?? []);
            $timestamp = date('Y-m-d H:i:s');
            $voucherId = UuidHelper::generate();
            $voucherSortNo = SequenceHelper::next('ledger_vouchers', 'sort_no');

            $this->pdo->beginTransaction();

            $voucherPayload = [
                'id' => $voucherId,
                'sort_no' => $voucherSortNo,
                'voucher_date' => $matchPayload['voucher_date'] ?? ($transaction['transaction_date'] ?? date('Y-m-d')),
                'source_type' => 'TRANSACTION',
                'source_id' => $transaction['id'] ?? null,
                'status' => 'posted',
                'summary_text' => $matchPayload['summary_text'] ?? $transaction['transaction_description'] ?? '거래 전표',
                'note' => $matchPayload['note'] ?? $transaction['transaction_note'] ?? null,
                'memo' => $matchPayload['memo'] ?? null,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->voucherModel->insert($voucherPayload)) {
                throw new \RuntimeException('전표 저장에 실패했습니다.');
            }

            foreach ($lines as $index => $line) {
                $lineId = UuidHelper::generate();
                $linePayload = [
                    'id' => $lineId,
                    'sort_no' => SequenceHelper::next('ledger_voucher_lines', 'sort_no'),
                    'voucher_id' => $voucherId,
                    'line_no' => $index + 1,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'line_summary' => $line['line_summary'],
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if (!$this->voucherLineModel->insert($linePayload)) {
                    throw new \RuntimeException(($index + 1) . '번째 전표 라인 저장에 실패했습니다.');
                }

                $this->voucherLineRefModel->bulkInsert($lineId, $line['refs'], $actor, $timestamp);
            }

            foreach ($payments as $payment) {
                $paymentPayload = [
                    'id' => UuidHelper::generate(),
                    'sort_no' => SequenceHelper::next('ledger_voucher_payments', 'sort_no'),
                    'voucher_id' => $voucherId,
                    'payment_type' => $payment['payment_type'],
                    'payment_id' => $payment['payment_id'],
                    'amount' => $payment['amount'],
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                ];

                if (!$this->voucherPaymentModel->insert($paymentPayload)) {
                    throw new \RuntimeException('지급 정보 저장에 실패했습니다.');
                }
            }

            try {
                if (!$this->transactionLinkModel->insertOrRestore($transactionId, $voucherId, null, 'MANUAL', $actor)) {
                    throw new \RuntimeException('거래와 전표 연결 저장에 실패했습니다.');
                }
            } catch (PDOException $e) {
                if (($e->getCode() ?? '') === '23000') {
                    throw new \RuntimeException('이미 연결된 거래-전표 관계입니다.', 0, $e);
                }
                throw $e;
            }

            if (!$this->transactionModel->update($transactionId, [
                'status' => 'completed',
                'match_status' => 'matched',
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ])) {
                throw new \RuntimeException('거래 상태를 변경하지 못했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'voucher_id' => $voucherId,
                'voucher_sort_no' => $voucherSortNo,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('createVoucherFromTransaction failed', [
                'transaction_id' => $transactionId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function validateTransactionAmounts(array $data): void
    {
        $supplyAmount = (float) ($data['transaction_supply_amount'] ?? 0);
        $vatAmount = (float) ($data['transaction_vat_amount'] ?? 0);
        $totalAmount = (float) ($data['transaction_total_amount'] ?? 0);

        if (abs($supplyAmount) <= 0 && abs($vatAmount) <= 0 && abs($totalAmount) <= 0) {
            throw new \InvalidArgumentException('거래 금액을 입력해주세요.');
        }
    }

    private function normalizeTransactionItems(array $items, array $data): array
    {
        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemQuantity = (float) ($this->numericOrNull($item['item_quantity'] ?? 0) ?? 0);
            $exchangeRate = (float) ($this->numericOrNull($data['transaction_exchange_rate'] ?? null) ?? 0);
            $itemForeignUnitPrice = $this->numericOrNull($item['item_foreign_unit_price'] ?? null);
            $itemForeignAmount = $this->numericOrNull($item['item_foreign_amount'] ?? null);
            $usesForeignAmount = $exchangeRate > 0 && ($itemForeignUnitPrice !== null || $itemForeignAmount !== null);
            if ($usesForeignAmount && $itemForeignAmount === null) {
                $itemForeignAmount = round($itemQuantity * (float) $itemForeignUnitPrice, 2);
            }

            $itemUnitPrice = $usesForeignAmount && $itemQuantity > 0
                ? round(((float) $itemForeignAmount * $exchangeRate) / $itemQuantity, 2)
                : (float) ($item['item_unit_price'] ?? 0);
            $itemTaxType = $this->normalizeTaxType($item['item_tax_type'] ?? null)
                ?? ($usesForeignAmount ? 'ZERO' : 'TAXABLE');
            $supplyAmount = $usesForeignAmount
                ? round((float) $itemForeignAmount * $exchangeRate, 2)
                : round($itemQuantity * $itemUnitPrice, 2);
            $vatAmount = $itemTaxType === 'TAXABLE' ? round($supplyAmount * 0.1, 2) : 0.0;
            $givenAmount = $this->numericOrNull($item['item_total_amount'] ?? null);
            $lineAmount = round((float) ($givenAmount ?? $supplyAmount), 2);
            $supplyAmount = $lineAmount;
            $vatAmount = 0.0;
            $item['item_quantity'] = $itemQuantity;
            $item['item_unit_price'] = $itemUnitPrice;
            $item['item_foreign_unit_price'] = $usesForeignAmount ? (float) ($itemForeignUnitPrice ?? 0) : null;
            $item['item_foreign_amount'] = $usesForeignAmount ? (float) ($itemForeignAmount ?? 0) : null;
            $item['item_supply_amount'] = $supplyAmount;
            $item['item_vat_amount'] = $vatAmount;
            $item['item_total_amount'] = $lineAmount;
            $item['item_tax_type'] = $itemTaxType;
            $rows[] = $item;
        }

        return $rows;
    }

    private function calculateTransactionLineTotals(array $items): array
    {
        $totals = [
            'transaction_supply_amount' => 0.0,
            'transaction_vat_amount' => 0.0,
            'transaction_total_amount' => 0.0,
        ];

        foreach ($items as $item) {
            $totals['transaction_supply_amount'] += (float) ($item['item_supply_amount'] ?? 0);
            $totals['transaction_vat_amount'] += (float) ($item['item_vat_amount'] ?? 0);
            $totals['transaction_total_amount'] += (float) ($item['item_total_amount'] ?? 0);
        }

        return array_map(static fn (float $amount): float => round($amount, 2), $totals);
    }

    private function resolveTransactionTotals(array $data, array $lineTotals): array
    {
        if (abs((float) ($lineTotals['transaction_total_amount'] ?? 0)) > 0) {
            return $lineTotals;
        }

        $supplyAmount = (float) ($this->numericOrNull($data['transaction_supply_amount'] ?? null) ?? 0);
        $vatAmount = (float) ($this->numericOrNull($data['transaction_vat_amount'] ?? null) ?? 0);
        $totalAmount = (float) ($this->numericOrNull($data['transaction_total_amount'] ?? null) ?? 0);
        if (abs($totalAmount) <= 0 && (abs($supplyAmount) > 0 || abs($vatAmount) > 0)) {
            $totalAmount = $supplyAmount + $vatAmount;
        }

        return [
            'transaction_supply_amount' => round($supplyAmount, 2),
            'transaction_vat_amount' => round($vatAmount, 2),
            'transaction_total_amount' => round($totalAmount, 2),
        ];
    }

    private function normalizeTaxType(mixed $value): ?string
    {
        $taxType = strtoupper(trim((string) ($value ?? '')));
        return preg_match('/^[A-Z0-9_]+$/', $taxType) ? $taxType : null;
    }

    private function normalizeCurrencyCode(mixed $value): string
    {
        $currency = strtoupper(trim((string) ($value ?? 'KRW')));
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'KRW';
    }

    private function numericOrNull(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    private function normalizeMatchedLines(array $lines): array
    {
        if (!is_array($lines) || $lines === []) {
            throw new \InvalidArgumentException('매칭할 전표 라인을 하나 이상 입력해주세요.');
        }

        $normalized = [];
        $debitSum = 0.0;
        $creditSum = 0.0;

        foreach ($lines as $index => $line) {
            $accountId = trim((string) ($line['account_id'] ?? ''));
            $refType = strtoupper(trim((string) ($line['ref_type'] ?? '')));
            $refId = trim((string) ($line['ref_id'] ?? ''));
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            $summary = trim((string) ($line['line_summary'] ?? ''));

            if ($accountId === '') {
                throw new \InvalidArgumentException(($index + 1) . '번째 라인의 account_id를 입력해주세요.');
            }

            $this->assertExists('ledger_accounts', $accountId, '선택한 계정과목을 찾을 수 없습니다.');

            if ($refType === '' && $refId !== '') {
                throw new \InvalidArgumentException('보조계정 유형(ref_type)이 필요합니다.');
            }

            if ($refType !== '' && $refId === '') {
                throw new \InvalidArgumentException('보조계정 대상(ref_id)이 필요합니다.');
            }

            if ($refType !== '') {
                $this->validateRefTarget($refType, $refId);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \InvalidArgumentException(($index + 1) . '번째 라인의 차변 또는 대변 금액을 입력해주세요.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException(($index + 1) . '번째 라인은 차변과 대변 금액을 동시에 입력할 수 없습니다.');
            }

            $normalized[] = [
                'account_id' => $accountId,
                'ref_type' => $refType !== '' ? $refType : null,
                'ref_id' => $refId !== '' ? $refId : null,
                'debit' => $debit,
                'credit' => $credit,
                'line_summary' => $summary !== '' ? $summary : null,
            ];

            $debitSum += $debit;
            $creditSum += $credit;
        }

        if (round($debitSum, 2) !== round($creditSum, 2)) {
            throw new \InvalidArgumentException('차변 합계와 대변 합계가 일치해야 합니다.');
        }

        return $normalized;
    }

    private function normalizePayments(array $payments): array
    {
        if (!is_array($payments) || $payments === []) {
            return [];
        }

        $normalized = [];

        foreach ($payments as $index => $payment) {
            $paymentType = trim((string) ($payment['payment_type'] ?? ''));
            $paymentId = trim((string) ($payment['payment_id'] ?? ''));
            $amount = (float) ($payment['amount'] ?? 0);

            if ($paymentType === '' || $paymentId === '' || $amount <= 0) {
                throw new \InvalidArgumentException(($index + 1) . '번째 지급 정보의 필수값을 입력해주세요.');
            }

            $normalized[] = [
                'payment_type' => $paymentType,
                'payment_id' => $paymentId,
                'amount' => $amount,
            ];
        }

        return $normalized;
    }

    private function encodeMatchPayload(array $payload): string
    {
        return json_encode([
            'voucher_match' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalizeMatchedVoucherLines(array $lines): array
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('전표 라인을 1건 이상 입력해야 합니다.');
        }

        $normalized = [];
        $debitSum = 0.0;
        $creditSum = 0.0;

        foreach ($lines as $index => $line) {
            $accountId = trim((string) ($line['account_id'] ?? ''));
            $refs = $this->normalizeVoucherLineRefs($line);
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            $summary = trim((string) ($line['line_summary'] ?? ''));

            if ($accountId === '') {
                throw new \InvalidArgumentException(($index + 1) . '번째 라인의 account_id가 필요합니다.');
            }

            $this->assertExists('ledger_accounts', $accountId, '선택한 계정과목을 찾을 수 없습니다.');

            foreach ($refs as $ref) {
                $this->validateRefTarget($ref['ref_type'], $ref['ref_id']);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \InvalidArgumentException(($index + 1) . '번째 라인은 차변 또는 대변 금액이 필요합니다.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException(($index + 1) . '번째 라인은 차변과 대변 금액을 동시에 입력할 수 없습니다.');
            }

            $normalized[] = [
                'account_id' => $accountId,
                'refs' => $refs,
                'debit' => $debit,
                'credit' => $credit,
                'line_summary' => $summary !== '' ? $summary : null,
            ];

            $debitSum += $debit;
            $creditSum += $credit;
        }

        if (round($debitSum, 2) !== round($creditSum, 2)) {
            throw new \InvalidArgumentException('차변 합계와 대변 합계가 일치해야 합니다.');
        }

        return $normalized;
    }

    private function normalizeVoucherLineRefs(array $line): array
    {
        $rawRefs = is_array($line['refs'] ?? null) ? $line['refs'] : [];

        if ($rawRefs === [] && (trim((string) ($line['ref_type'] ?? '')) !== '' || trim((string) ($line['ref_id'] ?? '')) !== '')) {
            $rawRefs[] = [
                'ref_type' => $line['ref_type'] ?? '',
                'ref_id' => $line['ref_id'] ?? '',
            ];
        }

        $refs = [];
        $seenTypes = [];

        foreach ($rawRefs as $ref) {
            $refType = strtoupper(trim((string) ($ref['ref_type'] ?? '')));
            $refId = trim((string) ($ref['ref_id'] ?? ''));

            if ($refType === '' && $refId === '') {
                continue;
            }

            if ($refType === '' || $refId === '') {
                throw new \InvalidArgumentException('보조계정 ref_type/ref_id를 모두 입력해야 합니다.');
            }

            if (isset($seenTypes[$refType])) {
                throw new \InvalidArgumentException('같은 보조계정 유형은 한 라인에서 중복 지정할 수 없습니다.');
            }

            $seenTypes[$refType] = true;
            $refs[] = [
                'ref_type' => $refType,
                'ref_id' => $refId,
            ];
        }

        return $refs;
    }

    private function decodeMatchPayload(string $memo): ?array
    {
        if ($memo === '') {
            return null;
        }

        $decoded = json_decode($memo, true);
        if (!is_array($decoded)) {
            return null;
        }

        $payload = $decoded['voucher_match'] ?? null;

        return is_array($payload) ? $payload : null;
    }

    private function resolveVoucherSourceType(array $transaction): string
    {
        $sourceType = strtoupper(trim((string) ($transaction['source_type'] ?? 'MANUAL')));

        return in_array($sourceType, ['TAX', 'CARD', 'BANK', 'MANUAL'], true) ? $sourceType : 'MANUAL';
    }

    private function validateRefTarget(string $refType, string $refId): void
    {
        $table = match ($refType) {
            'ACCOUNT' => 'system_bank_accounts',
            'CLIENT' => 'system_clients',
            'PROJECT' => 'system_projects',
            'EMPLOYEE' => 'user_employees',
            'CARD' => 'system_cards',
            'TRANSACTION' => 'ledger_transactions',
            'VOUCHER' => 'ledger_vouchers',
            'PAYMENT' => 'ledger_voucher_payments',
            'CONTRACT' => null,
            'ORDER' => null,
            default => throw new \InvalidArgumentException('지원하지 않는 참조 유형입니다.'),
        };

        if ($table === null) {
            if ($refId === '') {
                throw new \InvalidArgumentException('참조 ID가 필요합니다.');
            }
            return;
        }

        $this->assertExists($table, $refId, '선택한 참조 대상을 찾을 수 없습니다.');
    }

    private function assertExists(string $table, string $id, string $message): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name.');
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetchColumn()) {
            throw new \InvalidArgumentException($message);
        }
    }

    private function resolveOperationStage(): string
    {
        $stage = strtolower(trim((string) (
            getenv('APP_STAGE')
            ?: ($_ENV['APP_STAGE'] ?? '')
            ?: ($_SERVER['APP_STAGE'] ?? '')
        )));

        return $stage === self::STAGE_MIGRATION
            ? self::STAGE_MIGRATION
            : self::STAGE_OPERATIONAL;
    }
}
