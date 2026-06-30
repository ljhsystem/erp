<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBankModel;
use App\Models\Ledger\EvidenceCardHometaxModel;
use App\Models\Ledger\EvidenceCardPurchaseModel;
use App\Models\Ledger\EvidenceCashReceiptModel;
use App\Models\Ledger\EvidenceTaxInvoiceManualModel;
use App\Models\Ledger\EvidenceTaxInvoiceModel;
use Core\Helpers\SequenceHelper;
use PDO;

class EvidenceDualWriteService
{
    private const BANK_TABLE = 'ledger_evidence_bank_transaction';
    private const TAX_TABLE = 'ledger_evidence_tax_invoice';
    private const TAX_MANUAL_TABLE = 'ledger_evidence_tax_invoice_manual';
    private const CASH_TABLE = 'ledger_evidence_cash_receipt';
    private const CARD_HOMETAX_TABLE = 'ledger_evidence_card_hometax';
    private const CARD_STATEMENT_TABLE = 'ledger_evidence_card_statement';

    private PDO $pdo;
    private EvidenceBankModel $bankModel;
    private EvidenceTaxInvoiceModel $taxInvoiceModel;
    private EvidenceTaxInvoiceManualModel $taxInvoiceManualModel;
    private EvidenceCashReceiptModel $cashReceiptModel;
    private EvidenceCardHometaxModel $cardHometaxModel;
    private EvidenceCardPurchaseModel $cardPurchaseModel;
    private array $tableExistsCache = [];
    private array $tableColumnsCache = [];
    private array $existingRowCache = [];
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->bankModel = new EvidenceBankModel($pdo);
        $this->taxInvoiceModel = new EvidenceTaxInvoiceModel($pdo);
        $this->taxInvoiceManualModel = new EvidenceTaxInvoiceManualModel($pdo);
        $this->cashReceiptModel = new EvidenceCashReceiptModel($pdo);
        $this->cardHometaxModel = new EvidenceCardHometaxModel($pdo);
        $this->cardPurchaseModel = new EvidenceCardPurchaseModel($pdo);
    }

    public function syncByEvidenceId(string $evidenceId): array
    {
        $evidenceId = trim($evidenceId);
        if ($evidenceId === '' || !$this->tableExists('ledger_evidence_payloads')) {
            return [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => null,
                'message' => 'evidence payload not available',
            ];
        }

        $stmt = $this->pdo->prepare("
            SELECT
                p.evidence_id AS id,
                p.evidence_type AS source_type,
                p.source_key,
                p.format_id,
                p.raw_json,
                p.mapped_payload_json,
                p.created_at,
                p.created_by,
                p.updated_at,
                p.updated_by,
                p.deleted_at,
                p.deleted_by,
                COALESCE(pr.processing_status, 'READY') AS transaction_status,
                COALESCE(pr.review_status, 'NORMAL') AS review_status,
                pr.last_error_message AS error_message
            FROM ledger_evidence_payloads p
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type = p.evidence_type
               AND pr.evidence_id = p.evidence_id
               AND pr.deleted_at IS NULL
            WHERE p.evidence_id = :id
              AND p.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $evidenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!is_array($row)) {
            return [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => null,
                'message' => 'evidence payload row not found',
            ];
        }

        $payload = $this->mappedPayload($row);
        $row['evidence_date'] = $payload['evidence_date']
            ?? $payload['transaction_date']
            ?? $payload['purchase_date']
            ?? $payload['approval_date']
            ?? $payload['issue_date']
            ?? null;
        $row['client_id'] = $payload['client_id'] ?? null;
        $row['project_id'] = $payload['project_id'] ?? null;
        $row['employee_id'] = $payload['employee_id'] ?? null;
        $row['bank_account_id'] = $payload['bank_account_id'] ?? null;
        $row['card_id'] = $payload['card_id'] ?? null;
        $row['client_name'] = $payload['client_name'] ?? ($payload['client_company_name'] ?? null);
        $row['project_name'] = $payload['project_name'] ?? null;
        $row['employee_name'] = $payload['employee_name'] ?? null;
        $row['bank_account_name'] = $payload['bank_account_name'] ?? null;
        $row['card_name'] = $payload['card_name'] ?? null;
        $row['currency'] = $payload['currency'] ?? ($payload['currency_code'] ?? 'KRW');
        $row['supply_amount'] = $payload['supply_amount'] ?? null;
        $row['vat_amount'] = $payload['vat_amount'] ?? null;
        $row['total_amount'] = $payload['total_amount'] ?? null;
        $row['create_sort_no'] = $payload['_create_sort_no'] ?? 0;
        $row['status_sort_no'] = $payload['_status_sort_no'] ?? 0;
        $row['evidence_status'] = !empty($row['deleted_at']) ? 'DELETED' : 'ACTIVE';

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
            return $this->persistAndVerify(self::BANK_TABLE, $payload, fn(array $p): bool => $this->bankModel->upsertById($p));
        }

        if ($this->isTaxInvoiceSource($sourceType)) {
            $targetTable = $sourceType === 'TAX_INVOICE_MANUAL' ? self::TAX_MANUAL_TABLE : self::TAX_TABLE;
            $writer = $sourceType === 'TAX_INVOICE_MANUAL'
                ? fn(array $p): bool => $this->taxInvoiceManualModel->upsertById($p)
                : fn(array $p): bool => $this->taxInvoiceModel->upsertById($p);
            $payload = $this->buildTaxInvoicePayload($legacy, $targetTable);
            return $this->persistAndVerify($targetTable, $payload, $writer);
        }

        if ($this->isCashReceiptSource($sourceType)) {
            $payload = $this->buildCashReceiptPayload($legacy);
            return $this->persistAndVerify(self::CASH_TABLE, $payload, fn(array $p): bool => $this->cashReceiptModel->upsertById($p));
        }

        if ($this->isCardPurchaseSource($sourceType)) {
            if ($sourceType === 'CARD_HOMETAX') {
                $payload = $this->buildCardHometaxPayload($legacy);
                return $this->persistAndVerify(self::CARD_HOMETAX_TABLE, $payload, fn(array $p): bool => $this->cardHometaxModel->upsertById($p));
            }

            $payload = $this->buildCardStatementPayload($legacy);
            return $this->persistAndVerify(self::CARD_STATEMENT_TABLE, $payload, fn(array $p): bool => $this->cardPurchaseModel->upsertById($p));
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

        $payload = $this->filterPayloadForExistingColumns($targetTable, $payload);
        if (!isset($payload['id']) || trim((string) $payload['id']) === '') {
            $result = [
                'dual_write_status' => 'failed',
                'dual_write_target_table' => $targetTable,
                'message' => 'filtered payload missing id',
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
        $bankAccountId = trim((string) ($legacy['bank_account_id'] ?? ($payload['bank_account_id'] ?? '')));
        $transactionDirection = $this->normalizeBankTransactionDirection($payload);
        if ($transactionDirection === '') {
            return null;
        }

        $rawTransactionDateTime = $this->dateTime($payload['raw_transaction_datetime'] ?? ($payload['transaction_datetime'] ?? null));
        $deposit = $this->decimal($payload['raw_deposit_amount'] ?? ($payload['deposit_amount'] ?? null));
        $withdraw = $this->decimal($payload['raw_withdraw_amount'] ?? ($payload['withdraw_amount'] ?? null));

        return [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, self::BANK_TABLE),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, self::BANK_TABLE),
            'source_type' => $this->sourceTypeForBody((string) ($legacy['source_type'] ?? '')),
            'external_key' => (string) ($legacy['source_key'] ?? $legacy['external_key'] ?? ''),
            'import_type' => $this->importTypeForBody($payload, (string) ($legacy['source_type'] ?? '')),
            'business_unit' => $this->nullableString($payload['business_unit'] ?? null),
            'transaction_direction' => $transactionDirection,
            'transaction_type' => $this->nullableString($payload['transaction_type'] ?? null),
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'bank_account_id' => $bankAccountId !== '' ? $bankAccountId : null,
            'card_id' => $this->nullableString($legacy['card_id'] ?? ($payload['card_id'] ?? null)),
            'team_id' => $this->nullableString($legacy['team_id'] ?? ($payload['team_id'] ?? null)),
            'employee_id' => $this->nullableString($legacy['employee_id'] ?? ($payload['employee_id'] ?? null)),
            'raw_transaction_datetime' => $rawTransactionDateTime,
            'raw_deposit_amount' => $deposit,
            'raw_withdraw_amount' => $withdraw,
            'raw_balance_amount' => $this->decimal($payload['raw_balance_amount'] ?? ($payload['balance_amount'] ?? null)),
            'raw_description' => $this->nullableString($payload['raw_description'] ?? ($payload['description'] ?? null)),
            'raw_counterparty_account_number' => $this->nullableString($payload['raw_counterparty_account_number'] ?? ($payload['counterparty_account_number'] ?? null)),
            'raw_counterparty_bank_name' => $this->nullableString($payload['raw_counterparty_bank_name'] ?? ($payload['counterparty_bank_name'] ?? null)),
            'raw_memo' => $this->nullableString($payload['raw_memo'] ?? ($payload['memo'] ?? null)),
            'raw_transaction_type' => $this->nullableString($payload['raw_transaction_type'] ?? ($payload['transaction_type'] ?? null)),
            'raw_check_bill_amount' => $this->decimal($payload['raw_check_bill_amount'] ?? ($payload['check_bill_amount'] ?? null)),
            'raw_cms_code' => $this->nullableString($payload['raw_cms_code'] ?? ($payload['bank_reference_no'] ?? null)),
            'raw_counterparty_name' => $this->nullableString($payload['raw_counterparty_name'] ?? ($payload['counterparty_name'] ?? null)),
            'balance_status' => $this->nullableString($payload['balance_status'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
            'created_by' => $this->actorColumnValue($legacy['created_by'] ?? null),
            'updated_at' => $this->dateTime($legacy['updated_at'] ?? null),
            'updated_by' => $this->actorColumnValue($legacy['updated_by'] ?? null),
            'deleted_at' => $this->dateTime($legacy['deleted_at'] ?? null),
            'deleted_by' => $this->actorColumnValue($legacy['deleted_by'] ?? null),
        ];
    }

    private function buildTaxInvoicePayload(array $legacy, string $targetTable = self::TAX_TABLE): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $writtenDate = $this->dateOnly($this->firstValue($payload, ['raw_written_date', 'written_date', 'write_date', 'evidence_date']));
        $approvalNo = $this->nullableString($this->firstValue($payload, ['raw_approval_no', 'approval_number']));
        $issueDate = $this->dateOnly($this->firstValue($payload, ['raw_issue_date', 'issue_date']));
        $transmitDate = $this->dateOnly($this->firstValue($payload, ['raw_transmit_date', 'transmit_date']));
        $supplierBranchNo = $this->nullableString($this->firstValue($payload, ['raw_supplier_branch_no', 'supplier_branch_number']));
        $supplierCeoName = $this->nullableString($this->firstValue($payload, ['raw_supplier_ceo_name', 'supplier_ceo_name']));
        $supplierAddress = $this->nullableString($this->firstValue($payload, ['raw_supplier_address', 'supplier_address']));
        $supplierEmail = $this->nullableString($this->firstValue($payload, ['raw_supplier_email', 'supplier_email']));
        $customerBranchNo = $this->nullableString($this->firstValue($payload, ['raw_customer_branch_no', 'customer_branch_number']));
        $customerCeoName = $this->nullableString($this->firstValue($payload, ['raw_customer_ceo_name', 'customer_ceo_name']));
        $customerAddress = $this->nullableString($this->firstValue($payload, ['raw_customer_address', 'customer_address']));
        $customerEmail1 = $this->nullableString($this->firstValue($payload, ['raw_customer_email1', 'customer_email_1']));
        $customerEmail2 = $this->nullableString($this->firstValue($payload, ['raw_customer_email2', 'customer_email_2']));
        $invoiceCategory = $this->nullableString($this->firstValue($payload, ['raw_invoice_category', 'tax_invoice_category']));
        $invoiceKind = $this->nullableString($this->firstValue($payload, ['raw_invoice_kind', 'tax_invoice_type']));
        $issueType = $this->nullableString($this->firstValue($payload, ['raw_issue_type', 'issue_type']));
        $claimType = $this->nullableString($this->firstValue($payload, ['raw_claim_type', 'receipt_claim_type']));
        $rawSupplyAmount = $this->decimal($this->firstValue($payload, ['raw_supply_amount', 'supply_amount']) ?? ($legacy['supply_amount'] ?? null));
        $rawVatAmount = $this->decimal($this->firstValue($payload, ['raw_vat_amount', 'vat_amount']) ?? ($legacy['vat_amount'] ?? null));
        $rawTotalAmount = $this->decimal($this->firstValue($payload, ['raw_total_amount', 'total_amount']) ?? ($legacy['total_amount'] ?? null));
        $rawNote = $this->nullableString($this->firstValue($payload, ['raw_note', 'note', 'description']));
        $evidenceDate = $this->dateOnly(
            $legacy['evidence_date']
            ?? $this->firstValue($payload, ['evidence_date', 'raw_written_date', 'written_date', 'write_date', 'transaction_date', 'raw_issue_date', 'issue_date'])
        );
        $transactionDate = $this->dateOnly(
            $this->firstValue($payload, ['transaction_date', 'raw_issue_date', 'issue_date', 'raw_written_date', 'written_date'])
            ?? $evidenceDate
        );
        $supplierBn = trim((string) ($this->firstValue($payload, ['supplier_business_number', 'raw_supplier_business_number']) ?? ''));
        $supplierName = trim((string) ($this->firstValue($payload, ['supplier_company_name', 'raw_supplier_company_name']) ?? ''));
        $customerBn = trim((string) ($this->firstValue($payload, ['customer_business_number', 'raw_customer_business_number']) ?? ''));
        $customerName = trim((string) ($this->firstValue($payload, ['customer_company_name', 'raw_customer_company_name']) ?? ''));
        $rawItemDate = $this->dateOnly($this->firstValue($payload, ['raw_item_date', 'item_date']));
        $rawItemName = $this->nullableString($this->firstValue($payload, ['raw_item_name', 'item_name']));
        $rawItemSpec = $this->nullableString($this->firstValue($payload, ['raw_item_spec', 'item_spec']));
        $rawItemQuantity = $this->decimal($this->firstValue($payload, ['raw_item_quantity', 'item_qty', 'quantity']));
        $rawItemUnitPrice = $this->decimal($this->firstValue($payload, ['raw_item_unit_price', 'item_price', 'unit_price']));
        $rawItemSupplyAmount = $this->decimal($this->firstValue($payload, ['raw_item_supply_amount', 'item_supply_amount']));
        $rawItemTaxAmount = $this->decimal($this->firstValue($payload, ['raw_item_tax_amount', 'item_vat_amount']));
        $rawItemNote = $this->nullableString($this->firstValue($payload, ['raw_item_note', 'item_note']));
        $supplyAmount = $rawSupplyAmount;
        $vatAmount = $rawVatAmount;
        $totalAmount = $rawTotalAmount;

        if ($evidenceDate === null || $transactionDate === null || $supplierBn === '' || $supplierName === '' || $customerBn === '' || $customerName === '' || $supplyAmount === null || $vatAmount === null || $totalAmount === null) {
            return null;
        }

        return [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, $targetTable),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, $targetTable),
            'source_type' => $this->sourceTypeForBody((string) ($legacy['source_type'] ?? '')),
            'import_type' => $this->importTypeForBody($payload, (string) ($legacy['source_type'] ?? '')),
            'external_key' => (string) ($legacy['source_key'] ?? ''),
            'evidence_date' => $evidenceDate,
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'raw_client_name' => $this->nullableString($legacy['client_name'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
            'transaction_date' => $transactionDate,
            'raw_written_date' => $writtenDate,
            'raw_approval_no' => $approvalNo,
            'raw_issue_date' => $issueDate,
            'raw_transmit_date' => $transmitDate,
            'issue_date' => $issueDate,
            'transmit_date' => $transmitDate,
            'raw_supplier_business_number' => $this->nullableString($this->firstValue($payload, ['raw_supplier_business_number', 'supplier_business_number'])),
            'raw_supplier_branch_no' => $supplierBranchNo,
            'raw_supplier_company_name' => $this->nullableString($this->firstValue($payload, ['raw_supplier_company_name', 'supplier_company_name'])),
            'raw_supplier_ceo_name' => $supplierCeoName,
            'raw_supplier_address' => $supplierAddress,
            'raw_supplier_email' => $supplierEmail,
            'supplier_business_number' => $supplierBn,
            'supplier_branch_number' => $supplierBranchNo,
            'supplier_company_name' => $supplierName,
            'supplier_ceo_name' => $supplierCeoName,
            'supplier_address' => $supplierAddress,
            'supplier_email' => $supplierEmail,
            'raw_customer_business_number' => $this->nullableString($this->firstValue($payload, ['raw_customer_business_number', 'customer_business_number'])),
            'raw_customer_branch_no' => $customerBranchNo,
            'raw_customer_company_name' => $this->nullableString($this->firstValue($payload, ['raw_customer_company_name', 'customer_company_name'])),
            'raw_customer_ceo_name' => $customerCeoName,
            'raw_customer_address' => $customerAddress,
            'raw_customer_email1' => $customerEmail1,
            'raw_customer_email2' => $customerEmail2,
            'customer_business_number' => $customerBn,
            'customer_branch_number' => $customerBranchNo,
            'customer_company_name' => $customerName,
            'customer_ceo_name' => $customerCeoName,
            'customer_address' => $customerAddress,
            'customer_email_1' => $customerEmail1,
            'customer_email_2' => $customerEmail2,
            'raw_invoice_category' => $invoiceCategory,
            'raw_invoice_kind' => $invoiceKind,
            'raw_issue_type' => $issueType,
            'raw_claim_type' => $claimType,
            'tax_invoice_category' => $invoiceCategory,
            'tax_invoice_type' => $invoiceKind,
            'issue_type' => $issueType,
            'receipt_claim_type' => $claimType,
            'raw_supply_amount' => $rawSupplyAmount,
            'raw_vat_amount' => $rawVatAmount,
            'raw_total_amount' => $rawTotalAmount,
            'raw_item_date' => $rawItemDate,
            'raw_item_name' => $rawItemName,
            'raw_item_spec' => $rawItemSpec,
            'raw_item_quantity' => $rawItemQuantity,
            'raw_item_unit_price' => $rawItemUnitPrice,
            'raw_item_supply_amount' => $rawItemSupplyAmount,
            'raw_item_tax_amount' => $rawItemTaxAmount,
            'raw_item_note' => $rawItemNote,
            'supply_amount' => $supplyAmount,
            'vat_amount' => $vatAmount,
            'service_amount' => $this->decimal($payload['service_amount'] ?? null),
            'total_amount' => $totalAmount,
            'raw_note' => $rawNote,
            'description' => $this->nullableString($this->firstValue($payload, ['description', 'raw_note'])),
            'memo' => $this->nullableString($this->firstValue($payload, ['memo', 'raw_note'])),
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
            'sort_no' => $this->sortNo($legacy, self::CASH_TABLE),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, self::CASH_TABLE),
            'source_type' => $this->sourceTypeForBody((string) ($legacy['source_type'] ?? '')),
            'import_type' => $this->importTypeForBody($payload, (string) ($legacy['source_type'] ?? '')),
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
            'supply_amount' => $supplyAmount,
            'vat_amount' => $vatAmount,
            'service_amount' => $this->decimal($payload['service_amount'] ?? null),
            'total_amount' => $totalAmount,
            'memo' => $this->nullableString($payload['memo'] ?? null),
            'updated_at' => $this->dateTime($legacy['updated_at'] ?? null),
            'deleted_at' => $this->dateTime($legacy['deleted_at'] ?? null),
        ];
    }

    private function buildCardHometaxPayload(array $legacy): ?array
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
            'sort_no' => $this->sortNo($legacy, self::CARD_HOMETAX_TABLE),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, self::CARD_HOMETAX_TABLE),
            'source_type' => $this->sourceTypeForBody((string) ($legacy['source_type'] ?? '')),
            'import_type' => $this->importTypeForBody($payload, (string) ($legacy['source_type'] ?? '')),
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
            'supply_amount' => $supplyAmount,
            'vat_amount' => $vatAmount,
            'service_amount' => $this->decimal($payload['service_amount'] ?? null),
            'total_amount' => $totalAmount,
            'fee_amount' => $this->decimal($payload['fee_amount'] ?? null),
            'updated_at' => $this->dateTime($legacy['updated_at'] ?? null),
            'deleted_at' => $this->dateTime($legacy['deleted_at'] ?? null),
        ];
    }

    private function buildCardStatementPayload(array $legacy): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $approvalDate = $this->dateOnly($payload['approval_date'] ?? ($payload['transaction_date'] ?? ($legacy['evidence_date'] ?? null)));
        return [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, self::CARD_STATEMENT_TABLE),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, self::CARD_STATEMENT_TABLE),
            'source_type' => $this->sourceTypeForBody((string) ($legacy['source_type'] ?? '')),
            'import_type' => $this->importTypeForBody($payload, (string) ($legacy['source_type'] ?? '')),
            'external_key' => (string) ($legacy['source_key'] ?? ''),
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'bank_account_id' => $this->nullableString($legacy['bank_account_id'] ?? ($payload['bank_account_id'] ?? null)),
            'card_id' => $this->nullableString($legacy['card_id'] ?? ($payload['card_id'] ?? null)),
            'team_id' => $this->nullableString($payload['team_id'] ?? null),
            'employee_id' => $this->nullableString($legacy['employee_id'] ?? ($payload['employee_id'] ?? null)),
            'raw_receive_info_type_code' => $this->nullableString($payload['receive_info_type_code'] ?? null),
            'raw_card_number' => $this->nullableString($payload['card_number'] ?? null),
            'raw_card_type' => $this->nullableString($payload['card_type'] ?? null),
            'raw_payment_account_number' => $this->nullableString($payload['payment_account_number'] ?? null),
            'raw_payment_account_bank_name' => $this->nullableString($payload['payment_bank_name'] ?? null),
            'raw_holder_name' => $this->nullableString($payload['designee_korean_name'] ?? null),
            'raw_domestic_overseas_type' => $this->nullableString($payload['domestic_foreign_type'] ?? null),
            'raw_approval_number' => $this->nullableString($payload['approval_number'] ?? null),
            'raw_approval_date' => $approvalDate,
            'raw_sales_type' => $this->nullableString($payload['sales_type'] ?? null),
            'raw_amount_sign_code' => $this->nullableString($payload['amount_sign_code'] ?? null),
            'raw_transaction_amount_krw' => $this->decimal($payload['transaction_amount_krw'] ?? ($payload['total_amount'] ?? null)),
            'raw_vat_amount' => $this->decimal($payload['vat_amount'] ?? null),
            'raw_service_fee_amount' => $this->decimal($payload['service_amount'] ?? null),
            'raw_installment_period' => $this->nullableString($payload['installment_period'] ?? null),
            'raw_installment_round' => $this->nullableString($payload['installment_sequence'] ?? null),
            'raw_purchase_amount_krw' => $this->decimal($payload['purchase_amount_krw'] ?? null),
            'raw_billing_date' => $this->dateOnly($payload['billing_date'] ?? null),
            'raw_billing_amount' => $this->decimal($payload['billing_amount'] ?? null),
            'raw_billing_fee_amount' => $this->decimal($payload['fee_amount'] ?? null),
            'raw_actual_billing_amount' => $this->decimal($payload['actual_billing_amount'] ?? null),
            'raw_prior_notice_amount' => $this->decimal($payload['previous_notice_amount'] ?? null),
            'raw_transaction_amount_foreign' => $this->decimal($payload['foreign_amount'] ?? null),
            'raw_local_amount' => $this->decimal($payload['local_amount'] ?? null),
            'raw_local_currency_code' => $this->nullableString($payload['currency_code'] ?? null),
            'raw_exchange_rate' => $this->decimal($payload['exchange_rate'] ?? null),
            'raw_country_code' => $this->nullableString($payload['foreign_country_code'] ?? null),
            'raw_country_name' => $this->nullableString($payload['foreign_country_name'] ?? null),
            'raw_city_name' => $this->nullableString($payload['foreign_city_name'] ?? null),
            'raw_merchant_business_number' => $this->nullableString($payload['merchant_business_number'] ?? null),
            'raw_merchant_company_name' => $this->nullableString($payload['merchant_company_name'] ?? null),
            'raw_merchant_business_name' => $this->nullableString($payload['merchant_business_name'] ?? ($payload['merchant_business_category'] ?? null)),
            'raw_merchant_zip_code' => $this->nullableString($payload['merchant_zip_code'] ?? null),
            'raw_merchant_address1' => $this->nullableString($payload['merchant_address1'] ?? ($payload['merchant_address'] ?? null)),
            'raw_merchant_address2' => $this->nullableString($payload['merchant_address2'] ?? null),
            'raw_merchant_phone' => $this->nullableString($payload['merchant_phone'] ?? null),
            'raw_account_code_name' => $this->nullableString($payload['accounting_code_name'] ?? null),
            'raw_account_code' => $this->nullableString($payload['accounting_code'] ?? null),
            'raw_headquarters_name' => $this->nullableString($payload['headquarters_name'] ?? null),
            'raw_department_name' => $this->nullableString($payload['department_name'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
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

    private function filterPayloadForExistingColumns(string $table, array $payload): array
    {
        $columns = $this->tableColumns($table);
        if ($columns === []) {
            return $payload;
        }

        $filtered = array_filter(
            $payload,
            static fn(string $column): bool => isset($columns[$column]),
            ARRAY_FILTER_USE_KEY
        );

        foreach ($filtered as $column => $value) {
            $filtered[$column] = $this->normalizeValueForColumn($value, $columns[$column] ?? []);
        }

        return $filtered;
    }

    private function tableColumns(string $table): array
    {
        if (array_key_exists($table, $this->tableColumnsCache)) {
            return $this->tableColumnsCache[$table];
        }

        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $stmt->execute([':table_name' => $table]);
        $columns = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $columnName = (string) ($row['COLUMN_NAME'] ?? '');
            if ($columnName === '') {
                continue;
            }
            $columns[$columnName] = [
                'data_type' => strtolower(trim((string) ($row['DATA_TYPE'] ?? ''))),
                'max_length' => isset($row['CHARACTER_MAXIMUM_LENGTH']) ? (int) $row['CHARACTER_MAXIMUM_LENGTH'] : null,
            ];
        }
        $this->tableColumnsCache[$table] = $columns;

        return $columns;
    }

    private function normalizeValueForColumn(mixed $value, array $columnMeta): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $maxLength = isset($columnMeta['max_length']) ? (int) $columnMeta['max_length'] : 0;
        if ($maxLength > 0 && strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength);
        }

        return $value;
    }

    private function sortNo(array $legacy, string $table): int
    {
        $existing = $this->existingRow($table, (string) ($legacy['id'] ?? ''));
        $existingSortNo = (int) ($existing['sort_no'] ?? 0);
        if ($existingSortNo > 0) {
            return $existingSortNo;
        }

        $value = (int) ($legacy['status_sort_no'] ?? $legacy['create_sort_no'] ?? 0);
        if ($value > 0) {
            return $value;
        }
        return SequenceHelper::next($table, 'sort_no');
    }

    private function evidenceSortNo(array $legacy, string $table): int
    {
        $existing = $this->existingRow($table, (string) ($legacy['id'] ?? ''));
        $existingEvidenceSortNo = (int) ($existing['evidence_sort_no'] ?? 0);
        if ($existingEvidenceSortNo > 0) {
            return $existingEvidenceSortNo;
        }

        $value = (int) ($legacy['create_sort_no'] ?? $legacy['status_sort_no'] ?? 0);
        if ($value > 0) {
            return $value;
        }
        return $this->sortNo($legacy, $table);
    }

    private function existingRow(string $table, string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            return [];
        }

        $cacheKey = $table . ':' . $id;
        if (array_key_exists($cacheKey, $this->existingRowCache)) {
            return $this->existingRowCache[$cacheKey];
        }

        if (!$this->tableExists($table)) {
            $this->existingRowCache[$cacheKey] = [];
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT id, sort_no, evidence_sort_no
            FROM `{$table}`
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->existingRowCache[$cacheKey] = is_array($row) ? $row : [];

        return $this->existingRowCache[$cacheKey];
    }

    private function evidenceStatus(array $legacy): string
    {
        if (!empty($legacy['deleted_at'])) {
            return 'DELETED';
        }
        $status = strtoupper(trim((string) ($legacy['evidence_status'] ?? 'ACTIVE')));
        return in_array($status, ['ACTIVE', 'DELETED', 'INVALID'], true) ? $status : 'ACTIVE';
    }

    private function firstValue(array $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function actorColumnValue(mixed $value): ?string
    {
        $text = $this->nullableString($value);
        if ($text === null) {
            return null;
        }

        if (preg_match('/^(?:USER|SYSTEM):([0-9a-f-]{36})$/i', $text, $matches) === 1) {
            return strtolower((string) $matches[1]);
        }

        return strlen($text) > 36 ? substr($text, 0, 36) : $text;
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

    private function normalizeBodyImportType(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return match ($normalized) {
            'BANK' => 'BANK_TRANSACTION',
            'HOMETAX' => 'TAX_INVOICE',
            'CARD_COMPANY', 'CARD', 'CREDIT_CARD' => 'CARD_STATEMENT',
            default => $normalized,
        };
    }

    private function sourceTypeForBody(string $value): string
    {
        $importType = $this->normalizeBodyImportType($value);

        return match ($importType) {
            'BANK_TRANSACTION' => 'BANK',
            'TAX_INVOICE', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES', 'CARD_HOMETAX' => 'HOMETAX',
            'CARD_STATEMENT', 'CARD_APPROVAL' => 'CARD_COMPANY',
            'SHOPPING_ORDER' => 'SHOPPING',
            'IMPORT_INVOICE' => 'TRADE',
            default => strtoupper(trim($value)),
        };
    }

    private function importTypeForBody(array $payload, string $fallbackSourceType): ?string
    {
        $candidate = trim((string) ($payload['import_type'] ?? $payload['data_type'] ?? $fallbackSourceType));
        $normalized = $this->normalizeBodyImportType($candidate);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeBankTransactionDirection(array $payload): string
    {
        $direction = strtoupper(trim((string) ($payload['transaction_direction'] ?? $payload['bank_direction'] ?? '')));
        if (in_array($direction, ['IN', 'OUT'], true)) {
            return $direction;
        }

        $transactionType = strtoupper(trim((string) ($payload['transaction_type'] ?? '')));
        if (in_array($transactionType, ['DEPOSIT', 'IN'], true)) {
            return 'IN';
        }
        if (in_array($transactionType, ['WITHDRAW', 'OUT'], true)) {
            return 'OUT';
        }

        $deposit = $this->decimal($payload['raw_deposit_amount'] ?? ($payload['deposit_amount'] ?? null));
        $withdraw = $this->decimal($payload['raw_withdraw_amount'] ?? ($payload['withdraw_amount'] ?? null));
        if ($withdraw !== null && $withdraw > 0) {
            return 'OUT';
        }
        if ($deposit !== null && $deposit > 0) {
            return 'IN';
        }

        return '';
    }

    private function isTaxInvoiceSource(string $sourceType): bool
    {
        return in_array($sourceType, ['TAX_INVOICE', 'TAX_INVOICE_MANUAL'], true);
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
