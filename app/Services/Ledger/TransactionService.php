<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionItemModel;
use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
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
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->transactionModel = new TransactionModel($pdo);
        $this->transactionItemModel = new TransactionItemModel($pdo);
        $this->transactionLinkModel = new TransactionLinkModel($pdo);
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
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
                'business_unit' => $data['business_unit'] ?? 'HQ',
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
                throw new \RuntimeException('거래 저장 중 오류가 발생했습니다.');
            }

            foreach ($items as $index => $item) {
                $itemName = trim((string) ($item['item_name'] ?? ''));
                if ($itemName === '') {
                    throw new \InvalidArgumentException(($index + 1) . '번째 거래 품목명을 입력해 주세요.');
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
                    throw new \RuntimeException(($index + 1) . '번째 거래 품목 저장 중 오류가 발생했습니다.');
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
                    'message' => '거래 정보를 찾을 수 없습니다.',
                ];
            }

            $lines = $this->normalizeMatchedLines($data['lines'] ?? []);
            $timestamp = date('Y-m-d H:i:s');

            $matchPayload = [
                'voucher_date' => $data['voucher_date'] ?? ($transaction['transaction_date'] ?? date('Y-m-d')),
                'summary' => trim((string) ($data['summary'] ?? ($data['summary_text'] ?? ''))),
                'note' => $data['note'] ?? null,
                'memo' => $data['memo'] ?? null,
                'lines' => $lines,
            ];

            $updateData = [
                'project_id' => $data['project_id'] ?? $transaction['project_id'] ?? null,
                'client_id' => $data['client_id'] ?? $transaction['client_id'] ?? null,
                'transaction_description' => $matchPayload['summary'] !== '' ? $matchPayload['summary'] : ($transaction['transaction_description'] ?? null),
                'transaction_note' => $matchPayload['note'],
                'transaction_memo' => $this->encodeMatchPayload($matchPayload),
                'match_status' => 'matched',
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionModel->update($transactionId, $updateData)) {
                return [
                    'success' => false,
                    'message' => '?饔낅챷維??????饔낅떽??????????轅붽틓????????????關??濡녹춻???? ?饔낅떽???壤??얜?裕?傭??????',
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
                    'message' => '거래 정보를 찾을 수 없습니다.',
                ];
            }

            if (!in_array($docStatus, $allowed, true)) {
                return [
                    'success' => false,
                    'message' => '???嚥싲갭큔?댁빢???? ??? ???嶺?????????됰Ŧ鍮???????戮?Ĳ??',
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
                    'message' => '???嶺?????????됰Ŧ鍮???????쇰뮛????棺堉?뤃?????? ?饔낅떽???壤??얜?裕?傭??????',
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
                    'message' => '운영 단계에서만 거래 전표를 생성할 수 있습니다.',
                ];
            }

            $transaction = $this->transactionModel->getById($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => '거래 정보를 찾을 수 없습니다.',
                ];
            }

            if (($transaction['match_status'] ?? '') !== 'matched') {
                return [
                    'success' => false,
                    'message' => '매칭이 완료된 거래만 전표를 생성할 수 있습니다.',
                ];
            }

            $existingLinks = $this->transactionLinkModel->getByTransactionId($transactionId);
            if ($existingLinks !== []) {
                return [
                    'success' => false,
                    'message' => '이미 연결된 전표가 있는 거래입니다.',
                ];
            }

            $this->validateTransactionAmounts($transaction);

            $transactionItems = $this->transactionItemModel->getByTransactionId($transactionId);
            $matchPayload = $this->decodeMatchPayload((string) ($transaction['transaction_memo'] ?? ''));
            if ($matchPayload === null) {
                return [
                    'success' => false,
                    "message" => "\u{B9E4}\u{CE6D}\u{B41C} \u{C804}\u{D45C} \u{C815}\u{BCF4}\u{AC00} \u{C5C6}\u{C5B4} \u{C804}\u{D45C}\u{B97C} \u{C0DD}\u{C131}\u{D560} \u{C218} \u{C5C6}\u{C2B5}\u{B2C8}\u{B2E4}.",
                ];
            }

            $lines = $this->normalizeMatchedVoucherLines($matchPayload['lines'] ?? []);
            $timestamp = date('Y-m-d H:i:s');
            $voucherId = UuidHelper::generate();
            $voucherSortNo = SequenceHelper::next('ledger_vouchers', 'sort_no');

            $this->pdo->beginTransaction();

            $voucherPayload = [
                'id' => $voucherId,
                'sort_no' => $voucherSortNo,
                'voucher_date' => $matchPayload['voucher_date'] ?? ($transaction['transaction_date'] ?? date('Y-m-d')),
                'status' => 'posted',
                'summary' => $matchPayload['summary'] ?? ($matchPayload['summary_text'] ?? ($transaction['transaction_description'] ?? "\u{AC70}\u{B798} \u{C804}\u{D45C}")),
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->voucherModel->insert($voucherPayload)) {
                throw new \RuntimeException("\u{C804}\u{D45C} \u{C800}\u{C7A5}\u{C5D0} \u{C2E4}\u{D328}\u{D588}\u{C2B5}\u{B2C8}\u{B2E4}.");
            }

            foreach ($lines as $index => $line) {
                $lineId = UuidHelper::generate();
                $linePayload = [
                    'id' => $lineId,
                    'sort_no' => SequenceHelper::next('ledger_voucher_lines', 'sort_no'),
                    'line_no' => $index + 1,
                    'voucher_id' => $voucherId,
                    'account_id' => $line['account_id'],
                    'ref_target' => $line['refs'][0]['ref_target'] ?? null,
                    'ref_id' => $line['refs'][0]['ref_id'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'line_summary' => $line['line_summary'],
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if (!$this->voucherLineModel->insert($linePayload)) {
                    throw new \RuntimeException(($index + 1) . '번째 분개라인 저장 중 오류가 발생했습니다.');
                }
            }

            try {
                if (!$this->transactionLinkModel->insertOrRestore($transactionId, $voucherId, null, 'MANUAL', $actor)) {
                    throw new \RuntimeException('거래와 전표 연결 저장 중 오류가 발생했습니다.');
                }
            } catch (PDOException $e) {
                if (($e->getCode() ?? '') === '23000') {
                    throw new \RuntimeException('이미 거래에 연결된 전표가 있습니다.', 0, $e);
                }
                throw $e;
            }

            if (!$this->transactionModel->update($transactionId, [
                'status' => 'completed',
                'match_status' => 'matched',
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ])) {
                throw new \RuntimeException('?饔낅챷維??????????됰Ŧ鍮???????쇰뮛????棺堉?뤃?????? ?饔낅떽???壤??얜?裕?傭??????');
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
            throw new \InvalidArgumentException('?饔낅챷維????????亦낃콛???????????????ㅼ굣塋????鰲????轅붽틓?????');
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

        return array_map(static fn(float $amount): float => round($amount, 2), $totals);
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
            throw new \InvalidArgumentException('분개라인을 1건 이상 입력해 주세요.');
        }

        $normalized = [];
        $debitSum = 0.0;
        $creditSum = 0.0;

        foreach ($lines as $index => $line) {
            $accountId = trim((string) ($line['account_id'] ?? ''));
            $refType = strtoupper(trim((string) ($line['ref_target'] ?? '')));
            $refId = trim((string) ($line['ref_id'] ?? ''));
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            $summary = trim((string) ($line['line_summary'] ?? ''));

            if ($accountId === '') {
                throw new \InvalidArgumentException(($index + 1) . '번째 분개라인의 계정과목을 선택해 주세요.');
            }

            $this->assertExists('ledger_accounts', $accountId, '선택한 계정과목을 찾을 수 없습니다.');

            if ($refType === '' && $refId !== '') {
                throw new \InvalidArgumentException('????쇰뮛????癲ル슢????鶯ㅺ동??????????????ref_target)???????諛몃마????꿔꺂??????');
            }

            if ($refType !== '' && $refId === '') {
                throw new \InvalidArgumentException('????쇰뮛????癲ル슢????鶯ㅺ동??????????ref_id)???????諛몃마????꿔꺂??????');
            }

            if ($refType !== '') {
                $this->validateRefTarget($refType, $refId);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \InvalidArgumentException(($index + 1) . '번째 분개라인의 차변 또는 대변 금액을 입력해 주세요.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException(($index + 1) . '번째 분개라인은 차변과 대변을 동시에 입력할 수 없습니다.');
            }

            $normalized[] = [
                'account_id' => $accountId,
                'ref_target' => $refType !== '' ? $refType : null,
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

    private function encodeMatchPayload(array $payload): string
    {
        return json_encode([
            'voucher_match' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalizeMatchedVoucherLines(array $lines): array
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('분개라인을 1건 이상 입력해 주세요.');
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
                throw new \InvalidArgumentException(($index + 1) . '번째 분개라인의 계정과목을 선택해 주세요.');
            }

            $this->assertExists('ledger_accounts', $accountId, '선택한 계정과목을 찾을 수 없습니다.');

            foreach ($refs as $ref) {
                $this->validateRefTarget($ref['ref_target'], $ref['ref_id']);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \InvalidArgumentException(($index + 1) . '번째 분개라인의 차변 또는 대변 금액을 입력해 주세요.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException(($index + 1) . '번째 분개라인은 차변과 대변을 동시에 입력할 수 없습니다.');
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

        if ($rawRefs === [] && (trim((string) ($line['ref_target'] ?? '')) !== '' || trim((string) ($line['ref_id'] ?? '')) !== '')) {
            $rawRefs[] = [
                'ref_target' => $line['ref_target'] ?? '',
                'ref_id' => $line['ref_id'] ?? '',
            ];
        }

        $refs = [];
        $seenTypes = [];

        foreach ($rawRefs as $ref) {
            $refType = strtoupper(trim((string) ($ref['ref_target'] ?? '')));
            $refId = trim((string) ($ref['ref_id'] ?? ''));

            if ($refType === '' && $refId === '') {
                continue;
            }

            if ($refType === '' || $refId === '') {
                throw new \InvalidArgumentException('????쇰뮛????癲ル슢????鶯ㅺ동??????ref_target/ref_id???饔낅떽????ш낄?뉔뇡?????????ㅼ굣塋??????깅짆?????꿔꺂??????');
            }

            if (isset($seenTypes[$refType])) {
                throw new \InvalidArgumentException('하나의 분개라인에 같은 유형의 보조계정을 중복 지정할 수 없습니다.');
            }

            $seenTypes[$refType] = true;
            $refs[] = [
                'ref_target' => $refType,
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
            'CONTRACT' => null,
            'ORDER' => null,
            default => throw new \InvalidArgumentException('?饔낅떽??????雅?퍔瑗?땟??????????源녾텛? ??????猿딅빟 ?饔낅떽?????????????????????戮?Ĳ??'),
        };

        if ($table === null) {
            if ($refId === '') {
                throw new \InvalidArgumentException('?饔낅떽????????ID????ル늉?? ?????諛몃마????꿔꺂??????');
            }
            return;
        }

        $this->assertExists($table, $refId, '선택한 보조계정 정보를 찾을 수 없습니다.');
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
