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
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        try {
            $this->validateTransactionAmounts($data);

            $this->pdo->beginTransaction();

            $transactionId = (string) ($data['id'] ?? UuidHelper::generate());
            $transactionSortNo = null;
            $timestamp = date('Y-m-d H:i:s');

            $transactionPayload = [
                'id' => $transactionId,
                'sort_no' => SequenceHelper::next('ledger_transactions', 'sort_no'),
                'business_unit' => $data['business_unit'] ?? 'HQ',
                'transaction_type' => $data['transaction_type'] ?? 'ETC',
                'transaction_date' => $data['transaction_date'] ?? date('Y-m-d'),
                'client_id' => $data['client_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'currency' => $data['currency'] ?? 'KRW',
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'order_ref' => $data['order_ref'] ?? null,
                'tax_type' => $data['tax_type'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'match_status' => 'none',
                'note' => $data['note'] ?? null,
                'memo' => $data['memo'] ?? null,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionModel->insert($transactionPayload)) {
                throw new \RuntimeException('?耀붾굝梨루땟?????????????뗫떔????????⑤슣??????????????');
            }

            foreach ($items as $index => $item) {
                $itemName = trim((string) ($item['item_name'] ?? ''));
                if ($itemName === '') {
                    throw new \InvalidArgumentException(($index + 1) . '???????轅몄뫅?????젺????傭?????????????????????????????곸죩.');
                }

                $itemPayload = [
                    'id' => (string) ($item['id'] ?? UuidHelper::generate()),
                    'transaction_id' => $transactionId,
                    'sort_no' => (int) ($item['sort_no'] ?? ($index + 1)),
                    'item_date' => $item['item_date'] ?? ($data['transaction_date'] ?? date('Y-m-d')),
                    'item_name' => $itemName,
                    'specification' => $item['specification'] ?? null,
                    'unit_name' => $item['unit_name'] ?? null,
                    'quantity' => $item['quantity'] ?? 0,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'foreign_unit_price' => $item['foreign_unit_price'] ?? null,
                    'foreign_amount' => $item['foreign_amount'] ?? null,
                    'supply_amount' => $item['supply_amount'] ?? 0,
                    'vat_amount' => $item['vat_amount'] ?? 0,
                    'total_amount' => $item['total_amount'] ?? 0,
                    'tax_type' => $item['tax_type'] ?? null,
                    'description' => $item['description'] ?? null,
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if (!$this->transactionItemModel->insert($itemPayload)) {
                    throw new \RuntimeException(($index + 1) . '???耀붾굝梨루땟???????????????????뗫떔????????⑤슣??????????????');
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
                    'message' => '?꿸쑨????亦껋꺀?좉괴??꿔깴??????????????',
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
                'description' => $matchPayload['summary_text'] !== '' ? $matchPayload['summary_text'] : ($transaction['description'] ?? null),
                'note' => $matchPayload['note'],
                'memo' => $this->encodeMatchPayload($matchPayload),
                'match_status' => 'matched',
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->transactionModel->update($transactionId, $updateData)) {
                return [
                    'success' => false,
                    'message' => '?꿸쑨??????꿔꺂????????????臾롫뜦??????곌숯??????????딅젩.',
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
                    'message' => '?轅몄뫅????雅?퍔爰?醫됯눼??轅붽틓?????????????욱룏???????낆젵.',
                ];
            }

            if (!in_array($docStatus, $allowed, true)) {
                return [
                    'success' => false,
                    'message' => '???濚밸Ŧ援앾㎘??? ??? ?轅붽틓?節됰쑏???몡??????釉먮빱???????뽯쨦??',
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
                    'message' => '?꿔꺂?ｉ뜮?뚮쑏?????揶????⑤슢堉??嚥▲굧????????????????????',
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
                    'message' => '????????????影?쀫븸??꿸쑨?????????? ????????꾩룆?????????????????????',
                ];
            }

            $transaction = $this->transactionModel->getById($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => '?轅몄뫅????雅?퍔爰?醫됯눼??轅붽틓?????????????욱룏???????낆젵.',
                ];
            }

            if (($transaction['match_status'] ?? '') !== 'matched') {
                return [
                    'success' => false,
                    'message' => '?轅붽틓????????????밸븶??????釉먮빱????轅몄뫅????雅???????袁ｋ쨨??????袁⑸즴?????????????????낆젵.',
                ];
            }

            $existingLinks = $this->transactionLinkModel->getByTransactionId($transactionId);
            if ($existingLinks !== []) {
                return [
                    'success' => false,
                    'message' => '???? ????????????븍갭夷?? ????⑥ろ맖 ???? ???꾩룆???????????욱룏???????낆젵.',
                ];
            }

            $this->validateTransactionAmounts($transaction);

            $transactionItems = $this->transactionItemModel->getByTransactionId($transactionId);
            $matchPayload = $this->decodeMatchPayload((string) ($transaction['memo'] ?? ''));
            if ($matchPayload === null) {
                return [
                    'success' => false,
                    'message' => '????袁ｋ쨨?????袁⑸즴?????????밸븶????轅붽틓?????????꿔꺂??????쒐춯誘↔데鸚????쎛 ?????욱룏???????낆젵.',
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
                'summary_text' => $matchPayload['summary_text'] ?? $transaction['description'] ?? '嫄곕옒 ?꾪몴',
                'note' => $matchPayload['note'] ?? $transaction['note'] ?? null,
                'memo' => $matchPayload['memo'] ?? null,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->voucherModel->insert($voucherPayload)) {
                throw new \RuntimeException('????袁ｋ쨨??????獄쏅챷? ??????얜∥???????怨뚯댅???????????낆젵.');
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
                    throw new \RuntimeException(($index + 1) . '踰덉㎏ ?꾪몴?쇱씤 ??μ뿉 ?ㅽ뙣?덉뒿?덈떎.');
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
                    throw new \RuntimeException('??????嚥▲굧??????轅명땽????????얜∥???????怨뚯댅???????????낆젵.');
                }
            }

            try {
                if (!$this->transactionLinkModel->insertOrRestore($transactionId, $voucherId, null, 'MANUAL', $actor)) {
                    throw new \RuntimeException('?轅몄뫅?????????袁ｋ쨨???????곕츣????????얜∥???????怨뚯댅???????????낆젵.');
                }
            } catch (PDOException $e) {
                if (($e->getCode() ?? '') === '23000') {
                    throw new \RuntimeException('??? ??⑤슡???濾곌쑨????熬곥굥??????', 0, $e);
                }
                throw $e;
            }

            if (!$this->transactionModel->update($transactionId, [
                'status' => 'approved',
                'match_status' => 'matched',
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ])) {
                throw new \RuntimeException('?꿸쑨????????揶????⑤슢堉??嚥▲굧????????????????????');
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
            throw new \InvalidArgumentException('?耀붾굝梨루땟????????雅?굛肄???????????????源낆┰?????????곸죩.');
        }
    }

    private function normalizeMatchedLines(array $lines): array
    {
        if (!is_array($lines) || $lines === []) {
            throw new \InvalidArgumentException('?????ш내?℡ㅇ?????關?쒎첎?嫄?????饔낅떽????????癒?븸亦껋꼦??怨덊닧??????쎛 ??????源낆┰?????????곸죩.');
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
                throw new \InvalidArgumentException(($index + 1) . '?????μ떜媛?걫????account_id?????ル뒌?? ??????源낆┰?????????곸죩.');
            }

            $this->assertExists('ledger_accounts', $accountId, '?좏깮??怨꾩젙怨쇰ぉ??李얠쓣 ???놁뒿?덈떎.');

            if ($refType === '' && $refId !== '') {
                throw new \InvalidArgumentException('蹂댁“怨꾩젙 ?좏삎(ref_type)???꾩슂?⑸땲??');
            }

            if ($refType !== '' && $refId === '') {
                throw new \InvalidArgumentException('蹂댁“怨꾩젙 ???ref_id)???꾩슂?⑸땲??');
            }

            if ($refType !== '') {
                $this->validateRefTarget($refType, $refId);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \InvalidArgumentException(($index + 1) . '?????關?쒎첎?嫄???????雅?굛肄???????????????源낆┰?????????곸죩.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException(($index + 1) . '?????μ떜媛?걫???? ?饔낅떽??癰귥옕???節뗪텤????????????곕츥?????????????????????쇨덫櫻??????????源낆┰?????????곸죩.');
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
            throw new \InvalidArgumentException('?耀붾굝????곌램?????? ????筌??? ???????? ????筌??汝???? ???μ떜媛?걫??類㏃춹??? ?????????????곸죩.');
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
                throw new \InvalidArgumentException(($index + 1) . '????汝뷴젆?琉??????饔낅떽????????癒?븸亦껋꼦??怨덊닧??????쎛 ??????? ?????????????곸죩.');
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
            throw new \InvalidArgumentException('?꾪몴 遺꾧컻?쇱씤??1嫄??댁긽 ?낅젰?댁＜?몄슂.');
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
                throw new \InvalidArgumentException(($index + 1) . '踰덉㎏ ?쇱씤??account_id媛 ?꾩슂?⑸땲??');
            }

            $this->assertExists('ledger_accounts', $accountId, '?좏깮??怨꾩젙怨쇰ぉ??李얠쓣 ???놁뒿?덈떎.');

            foreach ($refs as $ref) {
                $this->validateRefTarget($ref['ref_type'], $ref['ref_id']);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \InvalidArgumentException(($index + 1) . '踰덉㎏ ?쇱씤? 李⑤? ?먮뒗 ?蹂 湲덉븸???꾩슂?⑸땲??');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException(($index + 1) . '踰덉㎏ ?쇱씤? 李⑤? ?먮뒗 ?蹂 以??섎굹留??낅젰?????덉뒿?덈떎.');
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
            throw new \InvalidArgumentException('李⑤? ?⑷퀎? ?蹂 ?⑷퀎媛 ?쇱튂?댁빞 ?⑸땲??');
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
                throw new \InvalidArgumentException('蹂댁“怨꾩젙? ref_type/ref_id瑜??④퍡 ?꾨떖?댁빞 ?⑸땲??');
            }

            if (isset($seenTypes[$refType])) {
                throw new \InvalidArgumentException('媛숈? 蹂댁“怨꾩젙 ?좏삎? ???쇱씤??以묐났 ??ν븷 ???놁뒿?덈떎.');
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
            default => throw new \InvalidArgumentException('吏?먰븯吏 ?딅뒗 李몄“ ?좏삎?낅땲??'),
        };

        if ($table === null) {
            if ($refId === '') {
                throw new \InvalidArgumentException('李몄“ ID媛 ?꾩슂?⑸땲??');
            }
            return;
        }

        $this->assertExists($table, $refId, '?좏깮??李몄“ ??곸쓣 李얠쓣 ???놁뒿?덈떎.');
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
