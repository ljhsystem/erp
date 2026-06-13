<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceTypePolicyService
{
    /**
     * @param callable(string):string $normalizeDataType
     * @param array<string,string> $legacyDataTypeMap
     */
    public function __construct(
        private $normalizeDataType,
        private array $legacyDataTypeMap,
        private ?PDO $pdo = null,
        private array $callbacks = []
    ) {
    }

    public function normalizeImportSourceType(string $sourceType): string
    {
        $sourceType = strtoupper(trim($sourceType));

        return match ($sourceType) {
            'HOMETAX' => 'TAX',
            'CARD_COMPANY' => 'CARD',
            'BANK_ACCOUNT' => 'BANK',
            'SHOPPING_MALL' => 'SHOPPING',
            'TRADE_IMPORT', 'IMPORT' => 'TRADE',
            default => $sourceType,
        };
    }

    public function sourceTypeForDataType(string $dataType): string
    {
        return match ($this->normalizeDataType($dataType)) {
            'TAX_INVOICE', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => 'HOMETAX',
            'CARD_HOMETAX' => 'HOMETAX',
            'CARD_STATEMENT', 'CARD_APPROVAL' => 'CARD_COMPANY',
            'BANK_TRANSACTION' => 'BANK',
            'SHOPPING_ORDER' => 'SHOPPING',
            'IMPORT_INVOICE' => 'TRADE',
            default => 'MANUAL',
        };
    }

    public function importTypesForSourceType(string $sourceType): array
    {
        return match ($this->normalizeImportSourceType($sourceType)) {
            'TAX' => ['TAX_INVOICE', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES', 'CARD_HOMETAX'],
            'CARD' => ['CARD_STATEMENT', 'CARD_APPROVAL'],
            'BANK' => ['BANK_TRANSACTION'],
            'SHOPPING', 'TRADE' => [],
            default => [],
        };
    }

    public function sourceTypeLabel(string $sourceType): string
    {
        return match ($this->normalizeImportSourceType($sourceType)) {
            'TAX' => 'Tax',
            'CARD', 'CARD_COMPANY' => 'Card',
            'BANK' => 'Bank',
            'SHOPPING' => 'Shopping Mall',
            'TRADE' => 'Trade/Import',
            'MANUAL' => 'Manual',
            default => '',
        };
    }

    public function importTypeLabel(string $importType): string
    {
        return match ($this->normalizeDataType($importType)) {
            'TAX_INVOICE' => 'Tax Invoice',
            'CASH_RECEIPT' => 'Cash Receipt',
            'CASH_RECEIPT_PURCHASE' => 'Cash Receipt (Purchase)',
            'CASH_RECEIPT_SALES' => 'Cash Receipt (Sales)',
            'CARD_HOMETAX' => 'Card (Hometax)',
            'CARD_STATEMENT' => 'Card (Statement)',
            'CARD_APPROVAL' => 'Card (Approval)',
            'BANK_TRANSACTION' => 'Bank Transaction',
            'SHOPPING_ORDER' => 'Shopping Order',
            'IMPORT_INVOICE' => 'Import Invoice',
            default => '',
        };
    }

    public function sourceTypeSql(string $column): string
    {
        return "CASE {$column}
            WHEN 'HOMETAX' THEN 'HOMETAX'
            WHEN 'TAX' THEN 'HOMETAX'
            WHEN 'TAX_INVOICE' THEN 'HOMETAX'
            WHEN 'CASH_RECEIPT' THEN 'HOMETAX'
            WHEN 'CASH_RECEIPT_PURCHASE' THEN 'HOMETAX'
            WHEN 'CASH_RECEIPT_SALES' THEN 'HOMETAX'
            WHEN 'CARD_HOMETAX' THEN 'HOMETAX'
            WHEN 'CARD' THEN 'CARD_COMPANY'
            WHEN 'CARD_COMPANY' THEN 'CARD_COMPANY'
            WHEN 'CREDIT_CARD' THEN 'CARD_COMPANY'
            WHEN 'CARD_STATEMENT' THEN 'CARD_COMPANY'
            WHEN 'CARD_APPROVAL' THEN 'CARD_COMPANY'
            WHEN 'BANK' THEN 'BANK'
            WHEN 'BANK_ACCOUNT' THEN 'BANK'
            WHEN 'BANK_TRANSACTION' THEN 'BANK'
            WHEN 'SHOPPING_ORDER' THEN 'SHOPPING'
            WHEN 'IMPORT_INVOICE' THEN 'TRADE'
            ELSE 'MANUAL'
        END";
    }

    public function queryDataTypes(string $type): array
    {
        $types = [$type];
        foreach ($this->legacyDataTypeMap as $legacy => $current) {
            if ($current === $type) {
                $types[] = $legacy;
            }
        }

        return array_values(array_unique($types));
    }

    public function transactionDirectionForStorage(string $direction, array $row, string $dataType): string
    {
        $direction = strtoupper(trim($direction));
        $dataType = $this->normalizeDataType($dataType);

        if ($dataType === 'BANK_TRANSACTION') {
            $bankDirection = strtoupper(trim((string) (
                $row['bank_direction']
                ?? $row['deposit_withdrawal_type']
                ?? $row['transaction_direction']
                ?? ''
            )));
            if (in_array($bankDirection, ['IN', 'DEPOSIT', 'RECEIPT', 'DEPOSIT'], true)) {
                return 'IN';
            }
            if (in_array($bankDirection, ['OUT', 'WITHDRAWAL', 'PAYMENT', 'WITHDRAW'], true)) {
                return 'OUT';
            }
        }

        if ($dataType === 'BANK_TRANSACTION') {
            $deposit = $this->amountOrNull($row['deposit_amount'] ?? null);
            $withdraw = $this->amountOrNull($row['withdraw_amount'] ?? $row['withdrawal_amount'] ?? null);
            if ($withdraw !== null && $withdraw > 0) {
                return 'OUT';
            }
            if ($deposit !== null && $deposit > 0) {
                return 'IN';
            }
            if (in_array($direction, ['IN', 'OUT'], true)) {
                return $direction;
            }
        }

        if ($direction === '') {
            $direction = match ($dataType) {
                'CASH_RECEIPT_PURCHASE', 'CARD_STATEMENT', 'CARD_APPROVAL', 'CASH_RECEIPT' => 'PURCHASE',
                'CASH_RECEIPT_SALES' => 'SALES',
                default => '',
            };
        }

        return match ($direction) {
            'SALES', 'SALE', 'SELL', 'OUT' => 'SALES',
            'PURCHASE', 'BUY', 'IN' => 'PURCHASE',
            'DEPOSIT' => 'IN',
            'WITHDRAWAL', 'PAYMENT' => 'OUT',
            default => $direction !== '' ? $direction : ($dataType === 'BANK_TRANSACTION' ? 'IN' : 'GENERAL'),
        };
    }

    public function isManualTaxInvoiceDataType(string $dataType): bool
    {
        $type = $this->normalizeDataType($dataType);
        if (in_array($type, [
            'TAX_INVOICE_MANUAL',
            'MANUAL_TAX_INVOICE',
            'TAX_INVOICE_PURCHASE_SALES_MANUAL',
            'TAX_INVOICE_BUY_SELL_MANUAL',
        ], true)) {
            return true;
        }

        $compact = preg_replace('/[\s_\-()]+/u', '', $type) ?? $type;

        return (
            str_contains($type, 'TAX')
            && str_contains($type, 'INVOICE')
            && str_contains($type, 'MANUAL')
        ) || (
            str_contains($compact, 'TAXINVOICE')
            && str_contains($compact, 'MANUAL')
        );
    }

    public function processingPlanForDataType(string $dataType): array
    {
        $dataType = $this->normalizeDataType($dataType);
        if ($this->isManualTaxInvoiceDataType($dataType)) {
            return [
                'type' => 'TRANSACTION',
                'target' => 'TRANSACTION_HEADER',
                'objects' => ['TRANSACTION_HEADER'],
                'label' => 'Voucher create + transaction create',
            ];
        }

        return match ($dataType) {
            'TAX_INVOICE', 'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => [
                'type' => 'TRANSACTION',
                'target' => 'TRANSACTION_HEADER',
                'objects' => ['TRANSACTION_HEADER'],
                'label' => 'Voucher create + transaction create',
            ],
            'CARD_STATEMENT', 'CARD_APPROVAL' => [
                'type' => 'TRANSACTION',
                'target' => 'TRANSACTION_AND_VOUCHER',
                'objects' => ['TRANSACTION_HEADER', 'TRANSACTION_LINE', 'VOUCHER_HEADER', 'VOUCHER_LINE'],
                'label' => 'Voucher create + transaction create + voucher line automation',
            ],
            'CARD_HOMETAX' => [
                'type' => 'VERIFY_ONLY',
                'target' => 'VERIFY_ONLY',
                'objects' => ['TAX_VERIFY', 'RECONCILIATION'],
                'label' => 'Verification-only upload for tax reconciliation',
            ],
            'BANK_TRANSACTION' => [
                'type' => 'BANK_FLOW',
                'target' => 'RECONCILIATION_ONLY',
                'objects' => ['BANK_FLOW', 'RECONCILIATION'],
                'label' => 'Bank flow load and reconciliation',
            ],
            'BUSINESS_DATA', 'SHOPPING_ORDER', 'PAYROLL', 'PAYROLL_WITHHOLDING', 'BUSINESS_INCOME', 'EMPLOYEE_EXPENSE', 'IMPORT_INVOICE', 'CONSTRUCTION' => [
                'type' => 'BUSINESS_DATA',
                'target' => 'BUSINESS_DATA',
                'objects' => ['BUSINESS_SYSTEM'],
                'label' => 'Load as business system data only',
            ],
            default => [
                'type' => 'UNSUPPORTED',
                'target' => 'UNSUPPORTED',
                'objects' => [],
                'label' => 'Voucher create + transaction create',
            ],
        };
    }

    public function businessUnitForUpload(array $row, string $dataType): string
    {
        $value = strtoupper(trim((string) ($row['business_unit'] ?? $row['business_unit_code'] ?? '')));
        if ($value !== '') {
            return $value;
        }

        return match ($this->normalizeDataType($dataType)) {
            'SHOPPING_ORDER' => 'ECOMMERCE',
            default => 'HQ',
        };
    }

    public function isTransactionProcessingType(string $dataType): bool
    {
        return $this->processingPlanForDataType($dataType)['type'] === 'TRANSACTION';
    }

    public function transactionProcessingDataTypes(array $dataTypes): array
    {
        $types = array_values(array_filter($dataTypes, fn(string $type): bool => $this->isTransactionProcessingType($type)));
        $types[] = 'BANK_TRANSACTION';

        return array_values(array_unique($types));
    }

    public function allowedDataTypes(array $dataTypes, array $businessDataTypes): array
    {
        static $types = null;
        if ($types !== null) {
            return $types;
        }

        $types = array_values(array_unique(array_merge($dataTypes, $businessDataTypes)));
        if (!$this->systemCodesTableExists()) {
            return $types;
        }

        $stmt = $this->pdo->prepare("
            SELECT code
            FROM system_codes
            WHERE deleted_at IS NULL
              AND is_active = 1
              AND code_group = 'IMPORT_TYPE'
            ORDER BY sort_no ASC, code ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $codeTypes = array_values(array_filter(array_map(
            fn($code): string => $this->normalizeDataType((string) $code),
            $rows
        )));

        return array_values(array_unique(array_merge($types, $codeTypes)));
    }

    private function normalizeDataType(string $type): string
    {
        return ($this->normalizeDataType)($type);
    }

    private function amountOrNull(mixed $value): ?float
    {
        if (!isset($this->callbacks['amountOrNull'])) {
            throw new \RuntimeException('Missing callback: amountOrNull');
        }

        return ($this->callbacks['amountOrNull'])($value);
    }

    private function systemCodesTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        if ($this->pdo === null) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'system_codes'
            LIMIT 1
        ");
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }
}
