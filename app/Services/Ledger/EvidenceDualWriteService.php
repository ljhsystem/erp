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
        $row['evidence_status'] = $this->storedEvidenceStatusForSync((string) ($row['source_type'] ?? ''), $evidenceId);

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

        $payload = [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, self::BANK_TABLE),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, self::BANK_TABLE),
            'source_type' => $this->sourceTypeForBody((string) ($legacy['source_type'] ?? '')),
            'external_key' => (string) ($legacy['source_key'] ?? $legacy['external_key'] ?? ''),
            'import_type' => $this->importTypeForBody($payload, (string) ($legacy['source_type'] ?? '')),
            'business_unit' => $this->nullableString($payload['business_unit'] ?? null),
            'transaction_direction' => $transactionDirection,
            'operation_type' => $this->nullableString($payload['operation_type'] ?? null),
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
            'raw_transaction_type' => $this->nullableString($payload['raw_transaction_type'] ?? null),
            'raw_check_bill_amount' => $this->decimal($payload['raw_check_bill_amount'] ?? ($payload['check_bill_amount'] ?? null)),
            'raw_cms_code' => $this->nullableString($payload['raw_cms_code'] ?? ($payload['bank_reference_no'] ?? null)),
            'raw_counterparty_name' => $this->nullableString($payload['raw_counterparty_name'] ?? ($payload['counterparty_name'] ?? null)),
            'balance_status' => $this->nullableString($payload['balance_status'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
        ];

        return $this->withAuditColumns($payload, $legacy);
    }

    private function buildTaxInvoicePayload(array $legacy, string $targetTable = self::TAX_TABLE): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $writtenDate = $this->dateOnlyWithExplicitPreference($payload, ['raw_written_date'], ['written_date', 'write_date', 'evidence_date']);
        $approvalNo = $this->nullableStringWithExplicitPreference($payload, ['raw_approval_no'], ['approval_number']);
        $issueDate = $this->dateOnlyWithExplicitPreference($payload, ['raw_issue_date'], ['issue_date']);
        $transmitDate = $this->dateOnlyWithExplicitPreference($payload, ['raw_transmit_date'], ['transmit_date']);
        $supplierBranchNo = $this->nullableStringWithExplicitPreference($payload, ['raw_supplier_branch_no'], ['supplier_branch_number']);
        $supplierCeoName = $this->nullableStringWithExplicitPreference($payload, ['raw_supplier_ceo_name'], ['supplier_ceo_name']);
        $supplierAddress = $this->nullableStringWithExplicitPreference($payload, ['raw_supplier_address'], ['supplier_address']);
        $supplierEmail = $this->nullableStringWithExplicitPreference($payload, ['raw_supplier_email'], ['supplier_email']);
        $customerBranchNo = $this->nullableStringWithExplicitPreference($payload, ['raw_customer_branch_no'], ['customer_branch_number']);
        $customerCeoName = $this->nullableStringWithExplicitPreference($payload, ['raw_customer_ceo_name'], ['customer_ceo_name']);
        $customerAddress = $this->nullableStringWithExplicitPreference($payload, ['raw_customer_address'], ['customer_address']);
        $customerEmail1 = $this->nullableStringWithExplicitPreference($payload, ['raw_customer_email1'], ['customer_email_1']);
        $customerEmail2 = $this->nullableStringWithExplicitPreference($payload, ['raw_customer_email2'], ['customer_email_2']);
        $invoiceCategory = $this->nullableStringWithExplicitPreference($payload, ['raw_invoice_category'], ['tax_invoice_category']);
        $invoiceKind = $this->nullableStringWithExplicitPreference($payload, ['raw_invoice_kind'], ['tax_invoice_type']);
        $issueType = $this->nullableStringWithExplicitPreference($payload, ['raw_issue_type'], ['issue_type']);
        $claimType = $this->nullableStringWithExplicitPreference($payload, ['raw_claim_type'], ['receipt_claim_type']);
        $rawSupplyAmount = $this->decimalWithExplicitPreference($payload, ['raw_supply_amount'], ['supply_amount'], $legacy['supply_amount'] ?? null);
        $rawVatAmount = $this->decimalWithExplicitPreference($payload, ['raw_vat_amount'], ['vat_amount'], $legacy['vat_amount'] ?? null);
        $rawTotalAmount = $this->decimalWithExplicitPreference($payload, ['raw_total_amount'], ['total_amount'], $legacy['total_amount'] ?? null);
        $rawNote = $this->nullableStringWithExplicitPreference($payload, ['raw_note'], ['note', 'description']);
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
        $rawItemDate = $this->dateOnlyWithExplicitPreference($payload, ['raw_item_date'], ['item_date']);
        $rawItemName = $this->nullableStringWithExplicitPreference($payload, ['raw_item_name'], ['item_name']);
        $rawItemSpec = $this->nullableStringWithExplicitPreference($payload, ['raw_item_spec'], ['item_spec']);
        $rawItemQuantity = $this->decimalWithExplicitPreference($payload, ['raw_item_quantity'], ['item_qty', 'quantity']);
        $rawItemUnitPrice = $this->decimalWithExplicitPreference($payload, ['raw_item_unit_price'], ['item_price', 'unit_price']);
        $rawItemSupplyAmount = $this->decimalWithExplicitPreference($payload, ['raw_item_supply_amount'], ['item_supply_amount']);
        $rawItemTaxAmount = $this->decimalWithExplicitPreference($payload, ['raw_item_tax_amount'], ['item_vat_amount']);
        $rawItemNote = $this->nullableStringWithExplicitPreference($payload, ['raw_item_note'], ['item_note']);
        $supplyAmount = $rawSupplyAmount;
        $vatAmount = $rawVatAmount;
        $totalAmount = $rawTotalAmount;

        if ($evidenceDate === null || $transactionDate === null || $supplierBn === '' || $supplierName === '' || $customerBn === '' || $customerName === '' || $supplyAmount === null || $vatAmount === null || $totalAmount === null) {
            return null;
        }

        $payload = [
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
            'raw_supplier_business_number' => $this->nullableStringWithExplicitPreference($payload, ['raw_supplier_business_number'], ['supplier_business_number']),
            'raw_supplier_branch_no' => $supplierBranchNo,
            'raw_supplier_company_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_supplier_company_name'], ['supplier_company_name']),
            'raw_supplier_ceo_name' => $supplierCeoName,
            'raw_supplier_address' => $supplierAddress,
            'raw_supplier_email' => $supplierEmail,
            'supplier_business_number' => $supplierBn,
            'supplier_branch_number' => $supplierBranchNo,
            'supplier_company_name' => $supplierName,
            'supplier_ceo_name' => $supplierCeoName,
            'supplier_address' => $supplierAddress,
            'supplier_email' => $supplierEmail,
            'raw_customer_business_number' => $this->nullableStringWithExplicitPreference($payload, ['raw_customer_business_number'], ['customer_business_number']),
            'raw_customer_branch_no' => $customerBranchNo,
            'raw_customer_company_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_customer_company_name'], ['customer_company_name']),
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
            'description' => $this->nullableStringWithExplicitPreference($payload, ['raw_note'], ['description']),
            'memo' => $this->nullableStringWithExplicitPreference($payload, ['raw_note'], ['memo']),
        ];

        return $this->withAuditColumns($payload, $legacy);
    }

    private function buildCashReceiptPayload(array $legacy): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $evidenceDate = $this->dateOnly(
            $legacy['evidence_date']
            ?? $this->firstValue($payload, [
                'purchase_datetime',
                'purchase_date',
                'raw_purchase_datetime',
                'raw_purchase_date',
                'evidence_date',
            ])
        );
        $supplyAmount = $this->decimalWithExplicitPreference(
            $payload,
            ['raw_supply_amount'],
            ['supply_amount'],
            $legacy['supply_amount'] ?? null
        );
        $vatAmount = $this->decimalWithExplicitPreference(
            $payload,
            ['raw_vat_amount'],
            ['vat_amount'],
            $legacy['vat_amount'] ?? null
        );
        $totalAmount = $this->decimalWithExplicitPreference(
            $payload,
            ['raw_total_amount'],
            ['total_amount'],
            $legacy['total_amount'] ?? null
        );
        $cashReceiptImportType = $this->cashReceiptImportTypeForBody($payload, (string) ($legacy['source_type'] ?? ''));
        $transactionDirection = $this->normalizeCashReceiptTransactionDirection($payload, $cashReceiptImportType);
        if ($evidenceDate === null || $supplyAmount === null || $vatAmount === null || $totalAmount === null) {
            return null;
        }

        $payload = [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, self::CASH_TABLE),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, self::CASH_TABLE),
            'source_type' => $this->sourceTypeForBody((string) ($legacy['source_type'] ?? '')),
            'import_type' => $cashReceiptImportType,
            'transaction_direction' => $transactionDirection,
            'external_key' => (string) ($legacy['source_key'] ?? ''),
            'evidence_date' => $evidenceDate,
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'raw_client_name' => $this->nullableString($legacy['client_name'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
            'write_date' => $this->dateTime(
                $this->firstValue($payload, [
                    'purchase_datetime',
                    'purchase_date',
                    'raw_purchase_datetime',
                    'raw_purchase_date',
                    'write_date',
                    'transaction_datetime',
                ])
            ),
            'issue_method' => $this->nullableString($payload['issue_method'] ?? null),
            'merchant_business_number' => $this->nullableString($payload['merchant_business_number'] ?? null),
            'merchant_company_name' => $this->nullableString($payload['merchant_company_name'] ?? null),
            'supply_amount' => $supplyAmount,
            'vat_amount' => $vatAmount,
            'service_amount' => $this->decimal($payload['service_amount'] ?? null),
            'total_amount' => $totalAmount,
            'memo' => $this->nullableString($payload['memo'] ?? null),
        ];

        return $this->withAuditColumns($payload, $legacy);
    }

    private function buildCardHometaxPayload(array $legacy): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $externalKeyCandidates = [
            'payload_external_key' => $payload['external_key'] ?? null,
            'raw_approval_no' => $payload['raw_approval_no'] ?? null,
            'raw_approval_number' => $payload['raw_approval_number'] ?? null,
            'approval_no' => $payload['approval_no'] ?? null,
            'approval_number' => $payload['approval_number'] ?? null,
            'legacy_external_key' => $legacy['external_key'] ?? null,
            'payload_source_key' => $payload['source_key'] ?? null,
            'legacy_source_key' => $legacy['source_key'] ?? null,
        ];
        $externalKey = $this->nullableString($this->firstValue($externalKeyCandidates, array_keys($externalKeyCandidates)));
        $evidenceDate = $this->dateOnly(
            $legacy['evidence_date']
            ?? $this->firstValue($payload, [
                'evidence_date',
                'approval_datetime',
                'approval_date',
                'approved_at',
                'approved_date',
                'purchase_datetime',
                'purchase_date',
                'raw_approval_date',
                'raw_purchase_datetime',
                'raw_purchase_date',
            ])
        );
        $approvalDate = $this->dateOnlyWithExplicitPreference(
            $payload,
            ['raw_approval_date'],
            ['approval_datetime', 'approval_date', 'approved_at', 'approved_date', 'purchase_datetime', 'purchase_date']
        );
        $billingDate = $this->dateOnlyWithExplicitPreference(
            $payload,
            ['raw_billing_date'],
            ['billing_date']
        );
        $cardNumber = $this->nullableStringWithExplicitPreference(
            $payload,
            ['raw_card_number'],
            ['card_number']
        );
        $merchantBusinessNumber = $this->nullableStringWithExplicitPreference(
            $payload,
            ['raw_merchant_business_number'],
            ['merchant_business_number']
        );
        $merchantCompanyName = $this->nullableStringWithExplicitPreference(
            $payload,
            ['raw_merchant_company_name'],
            ['merchant_company_name']
        );
        $supplyAmount = $this->decimalWithExplicitPreference(
            $payload,
            ['raw_supply_amount'],
            ['supply_amount'],
            $legacy['supply_amount'] ?? null
        );
        $vatAmount = $this->decimalWithExplicitPreference(
            $payload,
            ['raw_vat_amount'],
            ['vat_amount'],
            $legacy['vat_amount'] ?? null
        );
        $totalAmount = $this->decimalWithExplicitPreference(
            $payload,
            ['raw_total_amount'],
            ['total_amount'],
            $legacy['total_amount'] ?? null
        );
        $serviceAmount = $this->decimalWithExplicitPreference(
            $payload,
            ['raw_service_amount'],
            ['service_amount']
        );
        $feeAmount = $this->decimalWithExplicitPreference(
            $payload,
            ['raw_fee_amount'],
            ['fee_amount']
        );
        if ($evidenceDate === null || $supplyAmount === null || $vatAmount === null || $totalAmount === null) {
            return null;
        }

        $payload = [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, self::CARD_HOMETAX_TABLE),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, self::CARD_HOMETAX_TABLE),
            'source_type' => $this->sourceTypeForBody((string) ($legacy['source_type'] ?? '')),
            'import_type' => $this->importTypeForBody($payload, (string) ($legacy['source_type'] ?? '')),
            'external_key' => $externalKey,
            'evidence_date' => $evidenceDate,
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'raw_client_name' => $this->nullableString($legacy['client_name'] ?? null),
            'evidence_status' => $this->evidenceStatus($legacy),
            'card_id' => $this->nullableString($legacy['card_id'] ?? ($payload['card_id'] ?? null)),
            'raw_card_name' => $this->nullableString($legacy['card_name'] ?? ($payload['card_name'] ?? ($payload['source_card_company_name'] ?? null))),
            'raw_card_number' => $cardNumber,
            'approval_date' => $approvalDate,
            'billing_date' => $billingDate,
            'merchant_business_number' => $merchantBusinessNumber,
            'merchant_company_name' => $merchantCompanyName,
            'supply_amount' => $supplyAmount,
            'vat_amount' => $vatAmount,
            'service_amount' => $serviceAmount,
            'total_amount' => $totalAmount,
            'fee_amount' => $feeAmount,
        ];

        return $this->withAuditColumns($payload, $legacy);
    }

    private function buildCardStatementPayload(array $legacy): ?array
    {
        $payload = $this->mappedPayload($legacy);
        $approvalDate = $this->dateOnlyWithExplicitPreference(
            $payload,
            ['raw_approval_date'],
            ['approval_date', 'approved_date', 'approval_datetime', 'approved_at', 'transaction_date', 'evidence_date']
        ) ?? $this->dateOnly($legacy['evidence_date'] ?? null);
        $externalKey = $this->nullableString($this->firstValue($payload, [
            'external_key',
            'source_key',
            'raw_approval_number',
            'raw_approval_no',
            'approval_number',
            'approval_no',
            'raw_purchase_number',
            'purchase_number',
        ]) ?? ($legacy['external_key'] ?? ($legacy['source_key'] ?? '')));

        $payloadForBody = [
            'id' => (string) $legacy['id'],
            'sort_no' => $this->sortNo($legacy, self::CARD_STATEMENT_TABLE),
            'evidence_sort_no' => $this->evidenceSortNo($legacy, self::CARD_STATEMENT_TABLE),
            'source_type' => $this->sourceTypeForBody((string) ($legacy['source_type'] ?? '')),
            'import_type' => $this->importTypeForBody($payload, (string) ($legacy['source_type'] ?? '')),
            'external_key' => $externalKey,
            'evidence_date' => $approvalDate,
            'client_id' => $this->nullableString($legacy['client_id'] ?? null),
            'project_id' => $this->nullableString($legacy['project_id'] ?? null),
            'bank_account_id' => $this->nullableString($legacy['bank_account_id'] ?? ($payload['bank_account_id'] ?? null)),
            'card_id' => $this->nullableString($legacy['card_id'] ?? ($payload['card_id'] ?? null)),
            'team_id' => $this->nullableString($payload['team_id'] ?? null),
            'employee_id' => $this->nullableString($legacy['employee_id'] ?? ($payload['employee_id'] ?? null)),
            'raw_receive_info_type_code' => $this->nullableStringWithExplicitPreference($payload, ['raw_receive_info_type_code'], ['receive_info_type_code']),
            'raw_card_number' => $this->nullableStringWithExplicitPreference($payload, ['raw_card_number'], ['card_number']),
            'raw_card_type' => $this->nullableStringWithExplicitPreference($payload, ['raw_card_type'], ['card_type']),
            'raw_payment_account_number' => $this->nullableStringWithExplicitPreference($payload, ['raw_payment_account_number'], ['payment_account_number']),
            'raw_payment_account_bank_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_payment_account_bank_name'], ['payment_bank_name']),
            'raw_holder_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_holder_name'], ['designee_korean_name', 'holder_name']),
            'raw_domestic_overseas_type' => $this->nullableStringWithExplicitPreference($payload, ['raw_domestic_overseas_type'], ['domestic_foreign_type']),
            'raw_approval_number' => $this->nullableStringWithExplicitPreference($payload, ['raw_approval_number', 'raw_approval_no'], ['approval_number', 'approval_no']),
            'raw_approval_date' => $approvalDate,
            'raw_sales_type' => $this->nullableStringWithExplicitPreference($payload, ['raw_sales_type'], ['sales_type']),
            'raw_amount_sign_code' => $this->nullableStringWithExplicitPreference($payload, ['raw_amount_sign_code'], ['amount_sign_code']),
            'raw_transaction_amount_krw' => $this->decimalWithExplicitPreference($payload, ['raw_transaction_amount_krw'], ['transaction_amount_krw', 'total_amount']),
            'raw_vat_amount' => $this->decimalWithExplicitPreference($payload, ['raw_vat_amount'], ['vat_amount']),
            'raw_service_fee_amount' => $this->decimalWithExplicitPreference($payload, ['raw_service_fee_amount'], ['service_amount']),
            'raw_installment_period' => $this->nullableStringWithExplicitPreference($payload, ['raw_installment_period'], ['installment_period']),
            'raw_installment_round' => $this->nullableStringWithExplicitPreference($payload, ['raw_installment_round'], ['installment_sequence']),
            'raw_purchase_amount_krw' => $this->decimalWithExplicitPreference($payload, ['raw_purchase_amount_krw'], ['purchase_amount_krw']),
            'raw_billing_date' => $this->dateOnlyWithExplicitPreference($payload, ['raw_billing_date'], ['billing_date']),
            'raw_billing_amount' => $this->decimalWithExplicitPreference($payload, ['raw_billing_amount'], ['billing_amount']),
            'raw_billing_fee_amount' => $this->decimalWithExplicitPreference($payload, ['raw_billing_fee_amount'], ['fee_amount']),
            'raw_actual_billing_amount' => $this->decimalWithExplicitPreference($payload, ['raw_actual_billing_amount'], ['actual_billing_amount']),
            'raw_prior_notice_amount' => $this->decimalWithExplicitPreference($payload, ['raw_prior_notice_amount'], ['previous_notice_amount']),
            'raw_transaction_amount_foreign' => $this->decimalWithExplicitPreference($payload, ['raw_transaction_amount_foreign'], ['foreign_amount']),
            'raw_local_amount' => $this->decimalWithExplicitPreference($payload, ['raw_local_amount'], ['local_amount']),
            'raw_local_currency_code' => $this->nullableStringWithExplicitPreference($payload, ['raw_local_currency_code'], ['currency_code']),
            'raw_exchange_rate' => $this->decimalWithExplicitPreference($payload, ['raw_exchange_rate'], ['exchange_rate']),
            'raw_country_code' => $this->nullableStringWithExplicitPreference($payload, ['raw_country_code'], ['foreign_country_code']),
            'raw_country_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_country_name'], ['foreign_country_name']),
            'raw_city_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_city_name'], ['foreign_city_name']),
            'raw_merchant_business_number' => $this->nullableStringWithExplicitPreference($payload, ['raw_merchant_business_number'], ['merchant_business_number']),
            'raw_merchant_company_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_merchant_company_name'], ['merchant_company_name']),
            'raw_merchant_business_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_merchant_business_name'], ['merchant_business_name', 'merchant_business_category']),
            'raw_merchant_zip_code' => $this->nullableStringWithExplicitPreference($payload, ['raw_merchant_zip_code'], ['merchant_zip_code']),
            'raw_merchant_address1' => $this->nullableStringWithExplicitPreference($payload, ['raw_merchant_address1'], ['merchant_address1', 'merchant_address']),
            'raw_merchant_address2' => $this->nullableStringWithExplicitPreference($payload, ['raw_merchant_address2'], ['merchant_address2']),
            'raw_merchant_phone' => $this->nullableStringWithExplicitPreference($payload, ['raw_merchant_phone'], ['merchant_phone']),
            'raw_account_code_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_account_code_name'], ['accounting_code_name']),
            'raw_account_code' => $this->nullableStringWithExplicitPreference($payload, ['raw_account_code'], ['accounting_code']),
            'raw_headquarters_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_headquarters_name'], ['headquarters_name']),
            'raw_department_name' => $this->nullableStringWithExplicitPreference($payload, ['raw_department_name'], ['department_name']),
            'evidence_status' => $this->evidenceStatus($legacy),
        ];

        return $this->withAuditColumns($payloadForBody, $legacy);
    }

    private function mappedPayload(array $legacy): array
    {
        if (isset($legacy['current_payload']) && is_array($legacy['current_payload'])) {
            return $legacy['current_payload'];
        }

        $json = json_decode((string) ($legacy['mapped_payload_json'] ?? ''), true);
        return is_array($json) ? $json : [];
    }

    private function withAuditColumns(array $payload, array $legacy): array
    {
        $createdAt = $this->auditDateTime(
            $legacy['created_at'] ?? null,
            $legacy['updated_at'] ?? null,
            date('Y-m-d H:i:s')
        );
        $updatedAt = $this->auditDateTime(
            $legacy['updated_at'] ?? null,
            $legacy['created_at'] ?? null,
            $createdAt
        );
        $deletedAt = $this->dateTime($legacy['deleted_at'] ?? null);
        $createdBy = $this->auditActorValue(
            $legacy['created_by'] ?? null,
            $legacy['updated_by'] ?? null
        );
        $updatedBy = $this->auditActorValue(
            $legacy['updated_by'] ?? null,
            $legacy['created_by'] ?? null
        );
        $deletedBy = $deletedAt === null
            ? null
            : $this->auditActorValue($legacy['deleted_by'] ?? null, null);

        $payload['created_at'] = $createdAt;
        $payload['created_by'] = $createdBy;
        $payload['updated_at'] = $updatedAt;
        $payload['updated_by'] = $updatedBy;
        $payload['deleted_at'] = $deletedAt;
        $payload['deleted_by'] = $deletedBy;

        return $payload;
    }

    private function auditDateTime(mixed $primary, mixed $secondary = null, mixed $fallback = null): ?string
    {
        $normalized = $this->dateTime($primary);
        if ($normalized !== null) {
            return $normalized;
        }

        $normalized = $this->dateTime($secondary);
        if ($normalized !== null) {
            return $normalized;
        }

        return $this->dateTime($fallback);
    }

    private function auditActorValue(mixed $primary, mixed $secondary = null): ?string
    {
        $normalized = $this->actorColumnValue($primary);
        if ($normalized !== null && $normalized !== '') {
            return $normalized;
        }

        $normalized = $this->actorColumnValue($secondary);
        if ($normalized !== null && $normalized !== '') {
            return $normalized;
        }

        return null;
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
            SELECT id, sort_no, evidence_sort_no, evidence_status
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
        $status = strtoupper(trim((string) ($legacy['evidence_status'] ?? '')));

        return match ($status) {
            'COMPLETED', 'READY', 'VERIFY_ONLY', '완료' => 'COMPLETED',
            'CORRECTION_REQUIRED', 'NOT_READY', 'REVIEW_REQUIRED', 'INVALID', 'ERROR', '보정필요' => 'CORRECTION_REQUIRED',
            default => 'CORRECTION_REQUIRED',
        };
    }

    private function storedEvidenceStatusForSync(string $sourceType, string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return 'CORRECTION_REQUIRED';
        }

        $table = match (true) {
            $this->isBankSource($sourceType) => self::BANK_TABLE,
            $this->isTaxInvoiceSource($sourceType) => $sourceType === 'TAX_INVOICE_MANUAL' ? self::TAX_MANUAL_TABLE : self::TAX_TABLE,
            $this->isCashReceiptSource($sourceType) => self::CASH_TABLE,
            $sourceType === 'CARD_HOMETAX' => self::CARD_HOMETAX_TABLE,
            $this->isCardPurchaseSource($sourceType) => self::CARD_STATEMENT_TABLE,
            default => '',
        };

        if ($table === '') {
            return 'CORRECTION_REQUIRED';
        }

        $existing = $this->existingRow($table, $id);
        $status = strtoupper(trim((string) ($existing['evidence_status'] ?? '')));

        return $status !== '' ? $this->evidenceStatus(['evidence_status' => $status]) : 'CORRECTION_REQUIRED';
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

    private function explicitValue(array $source, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                return [
                    'found' => true,
                    'value' => $source[$key],
                ];
            }
        }

        return [
            'found' => false,
            'value' => null,
        ];
    }

    private function nullableStringWithExplicitPreference(array $source, array $preferredKeys, array $fallbackKeys = []): ?string
    {
        $explicit = $this->explicitValue($source, $preferredKeys);
        if ($explicit['found']) {
            return $this->nullableString($explicit['value']);
        }

        return $this->nullableString($this->firstValue($source, $fallbackKeys));
    }

    private function dateOnlyWithExplicitPreference(array $source, array $preferredKeys, array $fallbackKeys = []): ?string
    {
        $explicit = $this->explicitValue($source, $preferredKeys);
        if ($explicit['found']) {
            return $this->dateOnly($explicit['value']);
        }

        return $this->dateOnly($this->firstValue($source, $fallbackKeys));
    }

    private function decimalWithExplicitPreference(array $source, array $preferredKeys, array $fallbackKeys = [], mixed $default = null): ?float
    {
        $explicit = $this->explicitValue($source, $preferredKeys);
        if ($explicit['found']) {
            return $this->decimal($explicit['value']);
        }

        $fallback = $this->firstValue($source, $fallbackKeys);
        if ($fallback !== null) {
            return $this->decimal($fallback);
        }

        return $this->decimal($default);
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
            'CARD_COMPANY', 'CARD', 'CARD_PURCHASE', 'CREDIT_CARD' => 'CARD_STATEMENT',
            'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_PURCHAS', 'CASH_RECEIPT_BUY',
            'CASH_RECEIPT_SALES', 'CASH_RECEIPT_SALE', 'CASH_RECEIPT_SELL' => 'CASH_RECEIPT',
            default => $normalized,
        };
    }

    private function sourceTypeForBody(string $value): string
    {
        $importType = $this->normalizeBodyImportType($value);

        return match ($importType) {
            'BANK_TRANSACTION' => 'BANK',
            'TAX_INVOICE', 'CASH_RECEIPT', 'CARD_HOMETAX' => 'HOMETAX',
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
        if (in_array($direction, ['INCOME', 'SALES', 'SALE', 'SELL', 'OUT_SALE'], true)) {
            return 'INCOME';
        }
        if (in_array($direction, ['EXPENSE', 'PURCHASE', 'BUY', 'IN_PURCHASE'], true)) {
            return 'EXPENSE';
        }
        if (in_array($direction, ['FUND', 'IN', 'OUT'], true)) {
            return 'FUND';
        }

        $deposit = $this->decimal($payload['raw_deposit_amount'] ?? ($payload['deposit_amount'] ?? null));
        $withdraw = $this->decimal($payload['raw_withdraw_amount'] ?? ($payload['withdraw_amount'] ?? null));
        if ($withdraw !== null && $withdraw > 0) {
            return 'FUND';
        }
        if ($deposit !== null && $deposit > 0) {
            return 'FUND';
        }

        return '';
    }

    private function cashReceiptImportTypeForBody(array $payload, string $fallbackSourceType): string
    {
        return 'CASH_RECEIPT';
    }

    private function normalizeCashReceiptTransactionDirection(array $payload, string $importType): string
    {
        $direction = strtoupper(trim((string) ($payload['transaction_direction'] ?? '')));
        $normalizedDirection = match ($direction) {
            'INCOME', 'SALES', 'SALE', 'SELL', 'OUT_SALE' => 'INCOME',
            'EXPENSE', 'PURCHASE', 'BUY', 'IN_PURCHASE' => 'EXPENSE',
            default => '',
        };
        if ($normalizedDirection !== '') {
            return $normalizedDirection;
        }

        $sourceHint = strtoupper(trim((string) (
            $payload['import_type']
            ?? $payload['data_type']
            ?? $payload['source_type']
            ?? $importType
        )));
        if (in_array($sourceHint, ['CASH_RECEIPT_SALES', 'CASH_RECEIPT_SALE', 'CASH_RECEIPT_SELL'], true)) {
            return 'INCOME';
        }

        return 'EXPENSE';
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
