<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBankModel;
use App\Models\Ledger\EvidenceCardPurchaseModel;
use App\Models\Ledger\EvidenceCashReceiptModel;
use App\Models\Ledger\EvidenceTaxInvoiceModel;
use Core\Helpers\SequenceHelper;
use PDO;

class EvidenceDualWriteService
{
    private PDO $pdo;
    private EvidenceBankModel $bankModel;
    private EvidenceTaxInvoiceModel $taxInvoiceModel;
    private EvidenceCashReceiptModel $cashReceiptModel;
    private EvidenceCardPurchaseModel $cardPurchaseModel;
    private array $tableExistsCache = [];
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->bankModel = new EvidenceBankModel($pdo);
        $this->taxInvoiceModel = new EvidenceTaxInvoiceModel($pdo);
        $this->cashReceiptModel = new EvidenceCashReceiptModel($pdo);
        $this->cardPurchaseModel = new EvidenceCardPurchaseModel($pdo);
    }

    public function syncByEvidenceId(string $evidenceId): array
    {
        $evidenceId = trim($evidenceId);
        if ($evidenceId === '' || !$this->tableExists('ledger_data_evidences')) {
            return [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => null,
                'message' => 'legacy evidence not available',
            ];
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ledger_data_evidences
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $evidenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!is_array($row)) {
            return [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => null,
                'message' => 'legacy evidence row not found',
            ];
        }

        return $this->syncFromLegacyRow($row);
    }

    public function syncFromLegacyRow(array $legacy): array
    {
        $sourceType = strtoupper(trim((string) ($legacy['source_type'] ?? '')));
        if ($sourceType === '') {
            return [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => null,
                'message' => 'source_type is empty',
            ];
        }

        if ($this->isBankSource($sourceType)) {
            $payload = $this->buildBankPayload($legacy);
            return $this->persistAndVerify('ledger_evidence_bank', $payload, fn(array $p): bool => $this->bankModel->upsertById($p));
        }

        if ($this->isTaxInvoiceSource($sourceType)) {
            $payload = $this->buildTaxInvoicePayload($legacy);
            return $this->persistAndVerify('ledger_evidence_tax_invoice', $payload, fn(array $p): bool => $this->taxInvoiceModel->upsertById($p));
        }

        if ($this->isCashReceiptSource($sourceType)) {
            $payload = $this->buildCashReceiptPayload($legacy);
            return $this->persistAndVerify('ledger_evidence_cash_receipt', $payload, fn(array $p): bool => $this->cashReceiptModel->upsertById($p));
        }

        if ($this->isCardPurchaseSource($sourceType)) {
            $payload = $this->buildCardPurchasePayload($legacy);
            return $this->persistAndVerify('ledger_evidence_card_purchase', $payload, fn(array $p): bool => $this->cardPurchaseModel->upsertById($p));
        }

        return [
            'dual_write_status' => 'failed',
            'dual_write_target_table' => null,
            'message' => 'source_type not mapped for phase1',
        ];
    }

    private function persistAndVerify(string $targetTable, ?array $payload, callable $writer): array
    {
        if ($payload === null) {
            $result = [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'payload build failed',
            ];
            error_log('[EvidenceDualWriteService] failed target=' . $targetTable . ' reason=' . $result['message']);
            return $result;
        }

        if (!$this->tableExists($targetTable)) {
            $result = [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'target table not exists',
            ];
            error_log('[EvidenceDualWriteService] failed target=' . $targetTable . ' reason=' . $result['message']);
            return $result;
        }

        $saved = (bool) $writer($payload);
        if (!$saved) {
            $result = [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'upsert failed',
            ];
            error_log('[EvidenceDualWriteService] failed target=' . $targetTable . ' id=' . (string) ($payload['id'] ?? '') . ' reason=' . $result['message']);
            return $result;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM `{$targetTable}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (string) ($payload['id'] ?? '')]);
        $exists = (string) ($stmt->fetchColumn() ?: '') !== '';
        if (!$exists) {
            $result = [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'post-save verify select not found',
            ];
            error_log('[EvidenceDualWriteService] failed target=' . $targetTable . ' id=' . (string) ($payload['id'] ?? '') . ' reason=' . $result['message']);
            return $result;
        }

        $result = [
            'dual_write_status' => 'success',
            'dual_write_target_table' => $targetTable,
            'message' => 'verified',
        ];
        error_log('[EvidenceDualWriteService] success target=' . $targetTable . ' id=' . (string) ($payload['id'] ?? ''));
        return $result;
    }

    private function buildBankPayload(array $legacy): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $evidenceDate = $this->dateOnly($legacy['evidence_date'] ?? ($payload['evidence_date'] ?? null));
        $transactionDate = $this->dateOnly($payload['transaction_date'] ?? ($payload['evidence_date'] ?? null));
        $bankAccountId = trim((string) ($legacy['bank_account_id'] ?? ($payload['bank_account_id'] ?? '')));
        $transactionType = trim((string) ($payload['transaction_type'] ?? ($payload['bank_direction'] ?? '')));
        if ($evidenceDate === null || $transactionDate === null || $bankAccountId === '' || $transactionType === '') {
            return null;
        }

        $deposit = $this->decimal($payload['deposit_amount'] ?? null);
        $withdraw = $this->decimal($payload['withdraw_amount'] ?? null);
        $total = $this->decimal($legacy['total_amount'] ?? null);
        if ($total === null) {
            $total = $deposit ?? $withdraw ?? 0.0;
        }

        return [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, 'ledger_evidence_bank'),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, 'ledger_evidence_bank'),
            'source_type' => (string) $legacy['source_type'],
            'external_key' => (string) ($legacy['source_key'] ?? ''),
            'evidence_date' => $evidenceDate,
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'raw_client_name' => $this->nullableString($legacy['client_name'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
            'transaction_date' => $transactionDate,
            'transaction_time' => $this->timeOnly($payload['transaction_time'] ?? null),
            'transaction_datetime' => $this->dateTime($payload['transaction_datetime'] ?? null),
            'bank_account_id' => $bankAccountId,
            'transaction_type' => $transactionType,
            'deposit_amount' => $deposit,
            'withdraw_amount' => $withdraw,
            'total_amount' => $total,
            'balance_amount' => $this->decimal($payload['balance_amount'] ?? null),
            'balance_status' => $this->nullableString($payload['balance_status'] ?? null),
            'check_bill_amount' => $this->decimal($payload['check_bill_amount'] ?? null),
            'currency_code' => $this->nullableString($payload['currency_code'] ?? ($legacy['currency'] ?? 'KRW')),
            'exchange_rate' => $this->decimal($payload['exchange_rate'] ?? null, 6),
            'description' => $this->nullableString($payload['description'] ?? null),
            'counterparty_name' => $this->nullableString($payload['counterparty_name'] ?? null),
            'counterparty_account_number' => $this->nullableString($payload['counterparty_account_number'] ?? null),
            'counterparty_bank_name' => $this->nullableString($payload['counterparty_bank_name'] ?? null),
            'memo' => $this->nullableString($payload['memo'] ?? null),
            'updated_at' => $this->dateTime($legacy['updated_at'] ?? null),
            'deleted_at' => $this->dateTime($legacy['deleted_at'] ?? null),
        ];
    }

    private function buildTaxInvoicePayload(array $legacy): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $evidenceDate = $this->dateOnly($legacy['evidence_date'] ?? ($payload['evidence_date'] ?? null));
        $transactionDate = $this->dateOnly($payload['transaction_date'] ?? ($payload['issue_date'] ?? $evidenceDate));
        $supplierBn = trim((string) ($payload['supplier_business_number'] ?? ''));
        $supplierName = trim((string) ($payload['supplier_company_name'] ?? ''));
        $customerBn = trim((string) ($payload['customer_business_number'] ?? ''));
        $customerName = trim((string) ($payload['customer_company_name'] ?? ''));
        $supplyAmount = $this->decimal($payload['supply_amount'] ?? ($legacy['supply_amount'] ?? null));
        $vatAmount = $this->decimal($payload['vat_amount'] ?? ($legacy['vat_amount'] ?? null));
        $totalAmount = $this->decimal($payload['total_amount'] ?? ($legacy['total_amount'] ?? null));

        if ($evidenceDate === null || $transactionDate === null || $supplierBn === '' || $supplierName === '' || $customerBn === '' || $customerName === '' || $supplyAmount === null || $vatAmount === null || $totalAmount === null) {
            return null;
        }

        return [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, 'ledger_evidence_tax_invoice'),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, 'ledger_evidence_tax_invoice'),
            'source_type' => (string) $legacy['source_type'],
            'external_key' => (string) ($legacy['source_key'] ?? ''),
            'evidence_date' => $evidenceDate,
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'raw_client_name' => $this->nullableString($legacy['client_name'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
            'transaction_date' => $transactionDate,
            'issue_date' => $this->dateOnly($payload['issue_date'] ?? null),
            'transmit_date' => $this->dateOnly($payload['transmit_date'] ?? null),
            'supplier_business_number' => $supplierBn,
            'supplier_branch_number' => $this->nullableString($payload['supplier_branch_number'] ?? null),
            'supplier_company_name' => $supplierName,
            'supplier_ceo_name' => $this->nullableString($payload['supplier_ceo_name'] ?? null),
            'supplier_address' => $this->nullableString($payload['supplier_address'] ?? null),
            'supplier_email' => $this->nullableString($payload['supplier_email'] ?? null),
            'customer_business_number' => $customerBn,
            'customer_branch_number' => $this->nullableString($payload['customer_branch_number'] ?? null),
            'customer_company_name' => $customerName,
            'customer_ceo_name' => $this->nullableString($payload['customer_ceo_name'] ?? null),
            'customer_address' => $this->nullableString($payload['customer_address'] ?? null),
            'customer_email_1' => $this->nullableString($payload['customer_email_1'] ?? null),
            'customer_email_2' => $this->nullableString($payload['customer_email_2'] ?? null),
            'tax_invoice_category' => $this->nullableString($payload['tax_invoice_category'] ?? null),
            'tax_invoice_type' => $this->nullableString($payload['tax_invoice_type'] ?? null),
            'issue_type' => $this->nullableString($payload['issue_type'] ?? null),
            'receipt_claim_type' => $this->nullableString($payload['receipt_claim_type'] ?? null),
            'supply_amount' => $supplyAmount,
            'vat_amount' => $vatAmount,
            'service_amount' => $this->decimal($payload['service_amount'] ?? null),
            'total_amount' => $totalAmount,
            'description' => $this->nullableString($payload['description'] ?? null),
            'memo' => $this->nullableString($payload['memo'] ?? null),
            'updated_at' => $this->dateTime($legacy['updated_at'] ?? null),
            'deleted_at' => $this->dateTime($legacy['deleted_at'] ?? null),
        ];
    }

    private function buildCashReceiptPayload(array $legacy): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $evidenceDate = $this->dateOnly($legacy['evidence_date'] ?? ($payload['evidence_date'] ?? null));
        $supplyAmount = $this->decimal($payload['supply_amount'] ?? ($legacy['supply_amount'] ?? null));
        $vatAmount = $this->decimal($payload['vat_amount'] ?? ($legacy['vat_amount'] ?? null));
        $totalAmount = $this->decimal($payload['total_amount'] ?? ($legacy['total_amount'] ?? null));
        if ($evidenceDate === null || $supplyAmount === null || $vatAmount === null || $totalAmount === null) {
            return null;
        }

        return [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, 'ledger_evidence_cash_receipt'),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, 'ledger_evidence_cash_receipt'),
            'source_type' => (string) $legacy['source_type'],
            'external_key' => (string) ($legacy['source_key'] ?? ''),
            'evidence_date' => $evidenceDate,
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'raw_client_name' => $this->nullableString($legacy['client_name'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
            'write_date' => $this->dateTime($payload['write_date'] ?? ($payload['transaction_datetime'] ?? null)),
            'issue_method' => $this->nullableString($payload['issue_method'] ?? null),
            'merchant_business_number' => $this->nullableString($payload['merchant_business_number'] ?? null),
            'merchant_company_name' => $this->nullableString($payload['merchant_company_name'] ?? null),
            'merchant_business_type' => $this->nullableString($payload['merchant_business_type'] ?? null),
            'merchant_business_category' => $this->nullableString($payload['merchant_business_category'] ?? null),
            'supply_amount' => $supplyAmount,
            'vat_amount' => $vatAmount,
            'service_amount' => $this->decimal($payload['service_amount'] ?? null),
            'total_amount' => $totalAmount,
            'memo' => $this->nullableString($payload['memo'] ?? null),
            'updated_at' => $this->dateTime($legacy['updated_at'] ?? null),
            'deleted_at' => $this->dateTime($legacy['deleted_at'] ?? null),
        ];
    }

    private function buildCardPurchasePayload(array $legacy): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $evidenceDate = $this->dateOnly($legacy['evidence_date'] ?? ($payload['evidence_date'] ?? null));
        $supplyAmount = $this->decimal($payload['supply_amount'] ?? ($legacy['supply_amount'] ?? null));
        $vatAmount = $this->decimal($payload['vat_amount'] ?? ($legacy['vat_amount'] ?? null));
        $totalAmount = $this->decimal($payload['total_amount'] ?? ($legacy['total_amount'] ?? null));
        if ($evidenceDate === null || $supplyAmount === null || $vatAmount === null || $totalAmount === null) {
            return null;
        }

        return [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, 'ledger_evidence_card_purchase'),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, 'ledger_evidence_card_purchase'),
            'source_type' => (string) $legacy['source_type'],
            'external_key' => (string) ($legacy['source_key'] ?? ''),
            'evidence_date' => $evidenceDate,
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'raw_client_name' => $this->nullableString($legacy['client_name'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
            'card_id' => $this->nullableString($legacy['card_id'] ?? ($payload['card_id'] ?? null)),
            'raw_card_name' => $this->nullableString($legacy['card_name'] ?? ($payload['card_name'] ?? null)),
            'raw_card_number' => $this->nullableString($payload['card_number'] ?? null),
            'approval_date' => $this->dateOnly($payload['approval_date'] ?? null),
            'billing_date' => $this->dateOnly($payload['billing_date'] ?? null),
            'merchant_business_number' => $this->nullableString($payload['merchant_business_number'] ?? null),
            'merchant_company_name' => $this->nullableString($payload['merchant_company_name'] ?? null),
            'merchant_business_type' => $this->nullableString($payload['merchant_business_type'] ?? null),
            'merchant_business_category' => $this->nullableString($payload['merchant_business_category'] ?? null),
            'supply_amount' => $supplyAmount,
            'vat_amount' => $vatAmount,
            'service_amount' => $this->decimal($payload['service_amount'] ?? null),
            'total_amount' => $totalAmount,
            'fee_amount' => $this->decimal($payload['fee_amount'] ?? null),
            'currency_code' => $this->nullableString($payload['currency_code'] ?? ($legacy['currency'] ?? 'KRW')),
            'exchange_rate' => $this->decimal($payload['exchange_rate'] ?? null, 6),
            'foreign_amount' => $this->decimal($payload['foreign_amount'] ?? null),
            'local_amount' => $this->decimal($payload['local_amount'] ?? null),
            'installment_period' => $this->nullableString($payload['installment_period'] ?? null),
            'installment_sequence' => $this->nullableString($payload['installment_sequence'] ?? null),
            'payment_account_number' => $this->nullableString($payload['payment_account_number'] ?? null),
            'payment_bank_name' => $this->nullableString($payload['payment_bank_name'] ?? null),
            'memo' => $this->nullableString($payload['memo'] ?? null),
            'updated_at' => $this->dateTime($legacy['updated_at'] ?? null),
            'deleted_at' => $this->dateTime($legacy['deleted_at'] ?? null),
        ];
    }

    private function mappedPayload(array $legacy): array
    {
        $json = json_decode((string) ($legacy['mapped_payload_json'] ?? ''), true);
        return is_array($json) ? $json : [];
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
            LIMIT 1
        ");
        $stmt->execute([':table_name' => $table]);
        $this->tableExistsCache[$table] = (bool) $stmt->fetchColumn();
        return $this->tableExistsCache[$table];
    }

    private function sortNo(array $legacy, string $table): int
    {
        $value = (int) ($legacy['status_sort_no'] ?? $legacy['create_sort_no'] ?? 0);
        if ($value > 0) {
            return $value;
        }
        return SequenceHelper::next($table, 'sort_no');
    }

    private function evidenceSortNo(array $legacy, string $table): int
    {
        $value = (int) ($legacy['create_sort_no'] ?? $legacy['status_sort_no'] ?? 0);
        if ($value > 0) {
            return $value;
        }
        return $this->sortNo($legacy, $table);
    }

    private function evidenceStatus(array $legacy): string
    {
        if (!empty($legacy['deleted_at'])) {
            return 'DELETED';
        }
        $status = strtoupper(trim((string) ($legacy['evidence_status'] ?? 'ACTIVE')));
        return in_array($status, ['ACTIVE', 'DELETED', 'INVALID'], true) ? $status : 'ACTIVE';
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function decimal(mixed $value, int $precision = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '', trim((string) $value));
        if (!is_numeric($normalized)) {
            return null;
        }
        return number_format((float) $normalized, $precision, '.', '');
    }

    private function dateOnly(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function dateTime(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function timeOnly(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw) === 1) {
            return strlen($raw) === 5 ? ($raw . ':00') : $raw;
        }
        $ts = strtotime($raw);
        return $ts ? date('H:i:s', $ts) : null;
    }

    private function isBankSource(string $sourceType): bool
    {
        return in_array($sourceType, ['BANK_TRANSACTION', 'BANK'], true);
    }

    private function isTaxInvoiceSource(string $sourceType): bool
    {
        return in_array($sourceType, ['TAX_INVOICE'], true);
    }

    private function isCashReceiptSource(string $sourceType): bool
    {
        return in_array($sourceType, ['CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES'], true);
    }

    private function isCardPurchaseSource(string $sourceType): bool
    {
        return in_array($sourceType, ['CARD', 'CARD_HOMETAX', 'CARD_STATEMENT', 'CARD_APPROVAL', 'CARD_PURCHASE', 'CARD_COMPANY', 'CREDIT_CARD'], true);
    }
}
