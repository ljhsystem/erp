<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionItemModel;
use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Models\Ledger\VoucherPaymentModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\RefTypeHelper;
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
    private VoucherPaymentModel $voucherPaymentModel;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->transactionModel = new TransactionModel($pdo);
        $this->transactionItemModel = new TransactionItemModel($pdo);
        $this->transactionLinkModel = new TransactionLinkModel($pdo);
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
        $this->voucherPaymentModel = new VoucherPaymentModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.TransactionService');
    }

    public function createTransaction(array $data): array
    {
        $actor = ActorHelper::user();
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        try {
            $this->validateTransactionAmounts($data);

            $this->pdo->beginTransaction();

            $transactionId = (string) ($data['id'] ?? UuidHelper::generate());
            $transactionSortNo = null;
            $timestamp = date('Y-m-d H:i:s');

            $transactionPayload = [
                'id' => $transactionId,
                'sort_no' => $transactionSortNo,
                'source_type' => $data['source_type'] ?? 'MANUAL',
                'transaction_type' => $data['transaction_type'] ?? 'ETC',
                'transaction_date' => $data['transaction_date'] ?? date('Y-m-d'),
                'project_id' => $data['project_id'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'order_ref' => $data['order_ref'] ?? null,
                'document_type' => $data['document_type'] ?? null,
                'document_no' => $data['document_no'] ?? null,
                'tax_type' => $data['tax_type'] ?? null,
                'item_summary' => $data['item_summary'] ?? null,
                'description' => $data['description'] ?? null,
                'specification' => $data['specification'] ?? null,
                'unit_name' => $data['unit_name'] ?? null,
                'quantity' => $data['quantity'] ?? null,
                'unit_price' => $data['unit_price'] ?? null,
                'supply_amount' => $data['supply_amount'] ?? 0,
                'vat_amount' => $data['vat_amount'] ?? 0,
                'total_amount' => $data['total_amount'] ?? 0,
                'currency' => $data['currency'] ?? 'KRW',
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'status' => 'unposted',
                'doc_status' => 'draft',
                'match_status' => 'none',
                'acct_status' => 'unposted',
                'is_active' => $data['is_active'] ?? 1,
                'evidence_file_path' => $data['evidence_file_path'] ?? null,
                'note' => $data['note'] ?? null,
                'memo' => $data['memo'] ?? null,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionModel->insert($transactionPayload)) {
                throw new \RuntimeException('?轅몄뫅???????????얜∥???????곌숯???????????젩.');
            }

            foreach ($items as $index => $item) {
                $itemName = trim((string) ($item['item_name'] ?? ''));
                if ($itemName === '') {
                    throw new \InvalidArgumentException(($index + 1) . '??????癲꾧퀗?답펺?щ퉲?壤????꺏???????룸??????????????낆젵.');
                }

                $itemPayload = [
                    'id' => (string) ($item['id'] ?? UuidHelper::generate()),
                    'sort_no' => null,
                    'transaction_id' => $transactionId,
                    'line_no' => (int) ($item['line_no'] ?? ($index + 1)),
                    'item_name' => $itemName,
                    'specification' => $item['specification'] ?? null,
                    'unit_name' => $item['unit_name'] ?? null,
                    'quantity' => $item['quantity'] ?? 0,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'supply_amount' => $item['supply_amount'] ?? 0,
                    'vat_amount' => $item['vat_amount'] ?? 0,
                    'total_amount' => $item['total_amount'] ?? 0,
                    'tax_type' => $item['tax_type'] ?? null,
                    'description' => $item['description'] ?? null,
                    'is_active' => $item['is_active'] ?? 1,
                    'note' => $item['note'] ?? null,
                    'memo' => $item['memo'] ?? null,
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if (!$this->transactionItemModel->insert($itemPayload)) {
                    throw new \RuntimeException(($index + 1) . '???轅몄뫅?????????癲???????얜∥???????곌숯???????????젩.');
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
                    'message' => '濾곌쑨??誘λご?嶺≪??????곷????뼄.',
                ];
            }

            $lines = $this->normalizeMatchedLines($data['lines'] ?? []);
            $payments = $this->normalizePayments($data['payments'] ?? []);
            $timestamp = date('Y-m-d H:i:s');

            $matchPayload = [
                'voucher_date' => $data['voucher_date'] ?? ($transaction['transaction_date'] ?? date('Y-m-d')),
                'summary_text' => trim((string) ($data['summary_text'] ?? '')),
                'note' => $data['note'] ?? $null,
                'memo' => $data['memo'] ?? $null,
                'lines' => $lines,
                'payments' => $payments,
            ];

            $updateData = [
                'project_id' => $data['project_id'] ?? $transaction['project_id'] ?? $null,
                'client_id' => $data['client_id'] ?? $transaction['client_id'] ?? $null,
                'bank_account_id' => $data['bank_account_id'] ?? $transaction['bank_account_id'] ?? $null,
                'card_id' => $data['card_id'] ?? $transaction['card_id'] ?? $null,
                'item_summary' => $matchPayload['summary_text'] !== '' ? $matchPayload['summary_text'] : ($transaction['item_summary'] ?? $null),
                'note' => $matchPayload['note'],
                'memo' => $this->encodeMatchPayload($matchPayload),
                'match_status' => 'matched',
                'acct_status' => 'drafted',
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionModel->update($transactionId, $updateData)) {
                return [
                    'success' => false,
                    'message' => '濾곌쑨???嶺뚮씞?됭눧?????쒑굢????덉넮???곕????덈펲.',
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
                    'message' => '癲꾧퀗???沃샩삠걫?癲ル슓??젆???????⑤８?????덊렡.',
                ];
            }

            if (!in_array($docStatus, $allowed, true)) {
                return [
                    'success' => false,
                    'message' => '???源낅츛??? ??? 癲ル슣鍮섌뜮?????ㅺ컼?????낇돲??',
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
                    'message' => '嶺뚯빘鍮?????객???곌떠??롪퍔??굢?????넮????????펲.',
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
                    'message' => '???⑤ ???????ｇ춯?濾곌??????옇?? ??곥????諛댁??????????????????펲.',
                ];
            }

            $transaction = $this->transactionModel->getById($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => '癲꾧퀗???沃샩삠걫?癲ル슓??젆???????⑤８?????덊렡.',
                ];
            }

            if (($transaction['match_status'] ?? '') !== 'matched') {
                return [
                    'success' => false,
                    'message' => '癲ル슢????닱???ш끽維?????ㅺ컼???癲꾧퀗???沃?異???ш낄援?????獄쏅똻????????怨?????덊렡.',
                ];
            }

            $existingLinks = $this->transactionLinkModel->getByTransactionId($transactionId);
            if ($existingLinks !== []) {
                return [
                    'success' => false,
                    'message' => '???? ???슡?????곥?붹뤆?? ???곗꽑 ??⑤? ??諛댁????????⑤８?????덊렡.',
                ];
            }

            $this->validateTransactionAmounts($transaction);

            $transactionItems = $this->transactionItemModel->getByTransactionId($transactionId);
            $matchPayload = $this->decodeMatchPayload((string) ($transaction['memo'] ?? ''));
            if ($matchPayload === $null) {
                return [
                    'success' => false,
                    'message' => '??ш낄援????獄쏅똻?????ш끽維???癲ル슢????닱??嶺뚮㉡?€쾮戮る쨬??쎛 ???⑤８?????덊렡.',
                ];
            }

            $lines = $this->normalizeMatchedLines($matchPayload['lines'] ?? []);
            $payments = $this->normalizePayments($matchPayload['payments'] ?? []);
            $timestamp = date('Y-m-d H:i:s');
            $voucherId = UuidHelper::generate();
            $voucherSortNo = null;

            $this->pdo->beginTransaction();

            $voucherPayload = [
                'id' => $voucherId,
                'sort_no' => $voucherSortNo,
                'voucher_date' => $matchPayload['voucher_date'] ?? ($transaction['transaction_date'] ?? date('Y-m-d')),
                'ref_type' => $this->resolveVoucherRefType($transaction),
                'ref_id' => $this->resolveVoucherRefId($transaction),
                'status' => 'posted',
                'summary_text' => $matchPayload['summary_text'] ?? $transaction['item_summary'] ?? $transaction['description'] ?? '거래 전표',
                'note' => $matchPayload['note'] ?? $transaction['note'] ?? $null,
                'memo' => $this->buildVoucherMemo($matchPayload['memo'] ?? $null, $transactionItems),
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->voucherModel->insert($voucherPayload)) {
                throw new \RuntimeException('??ш낄援??????밸쭬 ?????묎덩?????됰꽡???怨?????덊렡.');
            }

            foreach ($lines as $index => $line) {
                $linePayload = [
                    'id' => UuidHelper::generate(),
                    'sort_no' => null,
                    'voucher_id' => $voucherId,
                    'line_no' => $index + 1,
                    'account_code' => $line['account_code'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'line_summary' => $line['line_summary'],
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if (!$this->voucherLineModel->insert($linePayload)) {
                    throw new \RuntimeException(($index + 1) . '????ш낄援????繹먮끏???????묎덩?????됰꽡???怨?????덊렡.');
                }
            }

            foreach ($payments as $payment) {
                $paymentPayload = [
                    'id' => UuidHelper::generate(),
                    'voucher_id' => $voucherId,
                    'payment_type' => $payment['payment_type'],
                    'payment_id' => $payment['payment_id'],
                    'amount' => $payment['amount'],
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                ];

                if (!$this->voucherPaymentModel->insert($paymentPayload)) {
                    throw new \RuntimeException('??곥???롪퍒????▲꺂???????묎덩?????됰꽡???怨?????덊렡.');
                }
            }

            $linkPayload = [
                'id' => UuidHelper::generate(),
                'sort_no' => null,
                'transaction_id' => $transactionId,
                'voucher_id' => $voucherId,
                'link_type' => 'MANUAL',
                'is_active' => 1,
                'note' => $null,
                'memo' => $null,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if ($this->transactionLinkModel->existsLink($transactionId, $voucherId)) {
                throw new \RuntimeException('??? ?곌껐??嫄곕???꾪몴??땲??');
            }

            try {
                if (!$this->transactionLinkModel->insert($linkPayload)) {
                    throw new \RuntimeException('癲꾧퀗??????ш낄援?????ㅼ뒦???????묎덩?????됰꽡???怨?????덊렡.');
                }
            } catch (PDOException $e) {
                if (($e->getCode() ?? '') === '23000') {
                    throw new \RuntimeException('??? ?곌껐??嫄곕???꾪몴??땲??', 0, $e);
                }
                throw $e;
            }

            if (!$this->transactionModel->update($transactionId, [
                'status' => 'posted',
                'match_status' => 'matched',
                'acct_status' => 'posted',
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ])) {
                throw new \RuntimeException('濾곌???????객???곌떠??롪퍔??굢?????넮????????펲.');
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
        $supplyAmount = (float) ($data['supply_amount'] ?? 0);
        $vatAmount = (float) ($data['vat_amount'] ?? 0);
        $totalAmount = (float) ($data['total_amount'] ?? 0);

        if ($supplyAmount <= 0 && $vatAmount <= 0 && $totalAmount <= 0) {
            throw new \InvalidArgumentException('?轅몄뫅????????沅걔?????????????욱룏???????낆젵.');
        }
    }

    private function normalizeMatchedLines(array $lines): array
    {
        if (!is_array($lines) || $lines === []) {
            throw new \InvalidArgumentException('????袁ｋ쨨????嚥싲갭큔?????꿔꺂??????쒐춯誘↔데鸚????쎛 ?????욱룏???????낆젵.');
        }

        $normalized = [];
        $debitSum = 0.0;
        $creditSum = 0.0;

        foreach ($lines as $index => $line) {
            $accountCode = trim((string) ($line['account_code'] ?? ''));
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            $summary = trim((string) ($line['line_summary'] ?? ''));

            if ($accountCode === '') {
                throw new \InvalidArgumentException(($index + 1) . '????濚밸Ŧ????account_code???ル봿?? ?????욱룏???????낆젵.');
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \InvalidArgumentException(($index + 1) . '????嚥싲갭큔???????沅걔?????????????욱룏???????낆젵.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException(($index + 1) . '????濚밸Ŧ???? ?꿔꺂?볟젆?④낮釉?????????ㅼ뒧??????????덈Ъ???????⑤챷竊?????????욱룏???????낆젵.');
            }

            $normalized[] = [
                'account_code' => $accountCode,
                'debit' => $debit,
                'credit' => $credit,
                'line_summary' => $summary !== '' ? $summary : null,
            ];

            $debitSum += $debit;
            $creditSum += $credit;
        }

        if (round($debitSum, 2) !== round($creditSum, 2)) {
            throw new \InvalidArgumentException('?轅붽틓?蹂잛젂?④?? ????명??? ?????슢?? ????명??β???? ??濚밸Ŧ?얕留??? ???????????낆젵.');
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
                throw new \InvalidArgumentException(($index + 1) . '????β뼯援??????꿔꺂??????쒐춯誘↔데鸚????쎛 ????癲?? ???????????낆젵.');
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

    private function buildVoucherMemo(?string $memo, array $transactionItems): ?string
    {
        $payload = [];

        if ($memo !== null && $memo !== '') {
            $payload['memo'] = $memo;
        }

        if ($transactionItems !== []) {
            $payload['transaction_items'] = array_map(static function (array $item): array {
                return [
                    'line_no' => $item['line_no'] ?? null,
                    'item_name' => $item['item_name'] ?? null,
                    'supply_amount' => $item['supply_amount'] ?? null,
                    'vat_amount' => $item['vat_amount'] ?? null,
                    'total_amount' => $item['total_amount'] ?? null,
                ];
            }, $transactionItems);
        }

        if ($payload === []) {
            return null;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function resolveVoucherRefType(array $transaction): ?string
    {
        $refType = strtoupper(trim((string) ($transaction['ref_type'] ?? '')));
        if (RefTypeHelper::isValid($refType) && $refType !== '') {
            return $refType;
        }

        if (!empty($transaction['project_id'])) {
            return 'PROJECT';
        }

        if (!empty($transaction['order_ref'])) {
            return 'ORDER';
        }

        return null;
    }

    private function resolveVoucherRefId(array $transaction): ?string
    {
        $refType = strtoupper(trim((string) ($transaction['ref_type'] ?? '')));

        return match ($refType) {
            'PROJECT' => $transaction['project_id'] ?? null,
            'ORDER' => $transaction['order_ref'] ?? null,
            'EXPENSE', 'PAYMENT', 'TAX', 'CUSTOMS' => $transaction['id'] ?? null,
            default => $transaction['project_id']
                ?? $transaction['order_ref']
                ?? $transaction['id']
                ?? null,
        };
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
