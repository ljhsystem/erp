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
                throw new \RuntimeException('?饔낅챷維????????????쒋닪???????怨뚯댅?????????????');
            }

            foreach ($items as $index => $item) {
                $itemName = trim((string) ($item['item_name'] ?? ''));
                if ($itemName === '') {
                    throw new \InvalidArgumentException(($index + 1) . '???????꿸쑨???듯렭????鶯????爰???????猷???????????????놁졄.');
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
                    throw new \RuntimeException(($index + 1) . '???饔낅챷維??????????????????쒋닪???????怨뚯댅?????????????');
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
                    'message' => '癲꾧퀗???沃샩삠걫?癲モ돦??????怨????堉?',
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
                'bank_account_id' => $data['bank_account_id'] ?? $transaction['bank_account_id'] ?? null,
                'card_id' => $data['card_id'] ?? $transaction['card_id'] ?? null,
                'item_summary' => $matchPayload['summary_text'] !== '' ? $matchPayload['summary_text'] : ($transaction['item_summary'] ?? null),
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
                    'message' => '癲꾧퀗????癲ル슢????닱??????묎덩?????됰꽡???怨?????덊렡.',
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
                    'message' => '?꿸쑨????亦껋꺀?좉괴??꿔꺂????????????ㅿ폍??????딅젩.',
                ];
            }

            if (!in_array($docStatus, $allowed, true)) {
                return [
                    'success' => false,
                    'message' => '???繹먮굝痢??? ??? ?꿔꺂?ｉ뜮?뚮쑏??????븐뻤??????뉖뤁??',
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
                    'message' => '癲ル슣鍮섌뜮?????媛???怨뚮뼚??濡ろ뜑??援????????????????',
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
                    'message' => '????????????節뉗땡?癲꾧퀗????????? ??怨????獄쏅똻????????????????????',
                ];
            }

            $transaction = $this->transactionModel->getById($transactionId);
            if (!$transaction) {
                return [
                    'success' => false,
                    'message' => '?꿸쑨????亦껋꺀?좉괴??꿔꺂????????????ㅿ폍??????딅젩.',
                ];
            }

            if (($transaction['match_status'] ?? '') !== 'matched') {
                return [
                    'success' => false,
                    'message' => '?꿔꺂???????????썹땟??????븐뻤????꿸쑨????亦???????꾣뤃??????꾩룆????????????????딅젩.',
                ];
            }

            $existingLinks = $this->transactionLinkModel->getByTransactionId($transactionId);
            if ($existingLinks !== []) {
                return [
                    'success' => false,
                    'message' => '???? ?????????怨?遺밸쨬?? ???怨쀪퐨 ???? ??獄쏅똻?????????ㅿ폍??????딅젩.',
                ];
            }

            $this->validateTransactionAmounts($transaction);

            $transactionItems = $this->transactionItemModel->getByTransactionId($transactionId);
            $matchPayload = $this->decodeMatchPayload((string) ($transaction['memo'] ?? ''));
            if ($matchPayload === null) {
                return [
                    'success' => false,
                    'message' => '????꾣뤃?????꾩룆????????썹땟????꿔꺂????????癲ル슢???ъ쒜筌믡굥夷???쎛 ????ㅿ폍??????딅젩.',
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
                'source_type' => $this->resolveVoucherSourceType($transaction),
                'source_id' => $transaction['id'] ?? null,
                'status' => 'posted',
                'summary_text' => $matchPayload['summary_text'] ?? $transaction['item_summary'] ?? $transaction['description'] ?? '嫄곕옒 ?꾪몴',
                'note' => $matchPayload['note'] ?? $transaction['note'] ?? null,
                'memo' => $this->buildVoucherMemo($matchPayload['memo'] ?? null, $transactionItems),
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if (!$this->voucherModel->insert($voucherPayload)) {
                throw new \RuntimeException('????꾣뤃??????諛몄? ?????臾롫뜦??????곌숯??????????딅젩.');
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
                    throw new \RuntimeException(($index + 1) . '번째 전표라인 저장에 실패했습니다.');
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
                    throw new \RuntimeException('??怨???濡ろ뜏?????꿸틓???????臾롫뜦??????곌숯??????????딅젩.');
                }
            }

            $linkPayload = [
                'id' => UuidHelper::generate(),
                'sort_no' => null,
                'transaction_id' => $transactionId,
                'voucher_id' => $voucherId,
                'link_type' => 'MANUAL',
                'is_active' => 1,
                'note' => null,
                'memo' => null,
                'created_at' => $timestamp,
                'created_by' => $actor,
                'updated_at' => $timestamp,
                'updated_by' => $actor,
            ];

            if ($this->transactionLinkModel->existsLink($transactionId, $voucherId)) {
                throw new \RuntimeException('??? ?怨뚭퍙??椰꾧퀡???袁るご?????');
            }

            try {
                if (!$this->transactionLinkModel->insert($linkPayload)) {
                    throw new \RuntimeException('?꿸쑨?????????꾣뤃??????쇰뮚???????臾롫뜦??????곌숯??????????딅젩.');
                }
            } catch (PDOException $e) {
                if (($e->getCode() ?? '') === '23000') {
                    throw new \RuntimeException('??? ?怨뚭퍙??椰꾧퀡???袁るご?????', 0, $e);
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
                throw new \RuntimeException('癲꾧퀗???????媛???怨뚮뼚??濡ろ뜑??援????????????????');
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
            throw new \InvalidArgumentException('?饔낅챷維????????亦낃콛???????????????깅즽????????놁졄.');
        }
    }

    private function normalizeMatchedLines(array $lines): array
    {
        if (!is_array($lines) || $lines === []) {
            throw new \InvalidArgumentException('????熬곻퐢夷?????μ떜媛?걫?????轅붽틓???????먯땡沃섃넄?곈툣?????쎛 ??????깅즽????????놁졄.');
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
                throw new \InvalidArgumentException(($index + 1) . '????嚥싲갭큔????account_id????ル늉?? ??????깅즽????????놁졄.');
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
                throw new \InvalidArgumentException(($index + 1) . '?????μ떜媛?걫???????亦낃콛???????????????깅즽????????놁졄.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException(($index + 1) . '????嚥싲갭큔???? ?轅붽틓?蹂잛젂??ｋ궙???????????쇰뮛???????????댭????????ㅼ굣塋??????????깅즽????????놁졄.');
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
            throw new \InvalidArgumentException('?饔낅떽??癰귥옕????? ????紐??? ???????? ????紐??棺???? ??嚥싲갭큔??뺧쭕??? ????????????놁졄.');
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
                throw new \InvalidArgumentException(($index + 1) . '????棺堉?뤃??????轅붽틓???????먯땡沃섃넄?곈툣?????쎛 ??????? ????????????놁졄.');
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
            throw new \InvalidArgumentException('전표 분개라인을 1건 이상 입력해주세요.');
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
                throw new \InvalidArgumentException(($index + 1) . '번째 라인은 차변 또는 대변 중 하나만 입력할 수 있습니다.');
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
                throw new \InvalidArgumentException('보조계정은 ref_type/ref_id를 함께 전달해야 합니다.');
            }

            if (isset($seenTypes[$refType])) {
                throw new \InvalidArgumentException('같은 보조계정 유형은 한 라인에 중복 저장할 수 없습니다.');
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
