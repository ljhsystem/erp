<?php

namespace App\Services\Ledger;

class EvidencePayloadNormalizeService
{
    /**
     * @param callable(array):array $normalizeBusinessRefPayload
     * @param callable(mixed):?string $dateValueOrNull
     * @param callable(mixed):?string $dateTimeValue
     * @param callable(string):bool $isUuid
     * @param callable(string):bool $looksLikeBankAccountNumber
     * @param callable(string):bool $isEmptySelectionLabel
     * @param callable(array):bool $isRequiredFormatColumn
     * @param callable(string):string $fieldLabel
     */
    public function __construct(
        private $normalizeBusinessRefPayload,
        private $dateValueOrNull,
        private $dateTimeValue,
        private $isUuid,
        private $looksLikeBankAccountNumber,
        private $isEmptySelectionLabel,
        private $isRequiredFormatColumn,
        private $fieldLabel
    ) {
    }

    public function mappedPayloadForStorage(array $row): array
    {
        $mapped = [];
        $keepInternalKeys = [
            '_transaction_lines' => true,
            '_transaction_files' => true,
            '_voucher_lines' => true,
        ];

        foreach ($row as $key => $value) {
            if (str_starts_with((string) $key, '_') && !isset($keepInternalKeys[(string) $key])) {
                continue;
            }

            $mapped[$key] = isset($keepInternalKeys[(string) $key])
                ? $value
                : $this->payloadScalarForStorage($value, str_ends_with((string) $key, '_id'));
        }

        if (!isset($mapped['transaction_date']) && isset($mapped['evidence_date'])) {
            $mapped['transaction_date'] = $mapped['evidence_date'];
        }
        if (!isset($mapped['write_date']) && isset($mapped['evidence_date'])) {
            $mapped['write_date'] = $mapped['evidence_date'];
        }
        if (!isset($mapped['approval_number']) && isset($mapped['source_key'])) {
            $mapped['approval_number'] = $mapped['source_key'];
        }
        if (!isset($mapped['withdrawal_amount']) && isset($mapped['withdraw_amount'])) {
            $mapped['withdrawal_amount'] = $mapped['withdraw_amount'];
        }
        if (
            empty($mapped['transaction_datetime'])
            && !empty($mapped['transaction_date'])
            && preg_match('/\d{1,2}:\d{2}/', (string) $mapped['transaction_date'])
        ) {
            $mapped['transaction_datetime'] = $mapped['transaction_date'];
        }
        $aliases = [
            'evidence_date' => ['raw_written_date', 'written_date', 'write_date', 'raw_issue_date', 'issue_date'],
            'transaction_date' => ['raw_issue_date', 'issue_date', 'raw_written_date', 'written_date'],
            'approval_number' => ['raw_approval_no'],
            'issue_date' => ['raw_issue_date'],
            'transmit_date' => ['raw_transmit_date'],
            'supplier_business_number' => ['raw_supplier_business_number'],
            'supplier_branch_number' => ['raw_supplier_branch_no'],
            'supplier_company_name' => ['raw_supplier_company_name'],
            'supplier_ceo_name' => ['raw_supplier_ceo_name'],
            'supplier_address' => ['raw_supplier_address'],
            'supplier_email' => ['raw_supplier_email'],
            'customer_business_number' => ['raw_customer_business_number'],
            'customer_branch_number' => ['raw_customer_branch_no'],
            'customer_company_name' => ['raw_customer_company_name'],
            'customer_ceo_name' => ['raw_customer_ceo_name'],
            'customer_address' => ['raw_customer_address'],
            'customer_email_1' => ['raw_customer_email1'],
            'customer_email_2' => ['raw_customer_email2'],
            'supply_amount' => ['raw_supply_amount'],
            'vat_amount' => ['raw_vat_amount'],
            'total_amount' => ['raw_total_amount'],
            'tax_invoice_category' => ['raw_invoice_category'],
            'tax_invoice_type' => ['raw_invoice_kind'],
            'issue_type' => ['raw_issue_type'],
            'receipt_claim_type' => ['raw_claim_type'],
            'raw_item_date' => ['item_date'],
            'raw_item_name' => ['item_name'],
            'raw_item_spec' => ['item_spec'],
            'raw_item_quantity' => ['item_qty', 'quantity'],
            'raw_item_unit_price' => ['item_price', 'unit_price'],
            'raw_item_supply_amount' => ['item_supply_amount'],
            'raw_item_tax_amount' => ['item_vat_amount'],
            'raw_item_note' => ['item_note'],
            'note' => ['raw_note'],
            'description' => ['raw_note'],
        ];

        foreach ($aliases as $target => $keys) {
            if (isset($mapped[$target]) && trim((string) $this->payloadScalarForStorage($mapped[$target])) !== '') {
                continue;
            }

            foreach ($keys as $key) {
                $value = $this->payloadScalarForStorage($mapped[$key] ?? null);
                if ($value !== null && trim((string) $value) !== '') {
                    $mapped[$target] = $value;
                    break;
                }
            }
        }

        $mapped = $this->syncTaxInvoiceRawAliases($mapped);
        $mapped = $this->normalizeTaxInvoiceRawItemPayload($mapped);
        $mapped = $this->normalizeTransactionDirectionPayload($mapped);
        $mapped = $this->normalizeMappedPayloadDateValues($this->normalizeMappedClientReference($mapped));
        return ($this->normalizeBusinessRefPayload)($mapped);
    }

    public function normalizeEvidenceMappedPayloadForResponse(array $payload): array
    {
        $payload = $this->normalizeMappedClientReference($payload);
        $payload = $this->syncTaxInvoiceResponseAliases($payload);
        $aliases = [
            'client_name' => ['client_name', '거래처명', '거래처'],
            'project_name' => ['project_name', 'project_code'],
            'employee_name' => ['employee_name', 'user_name'],
            'bank_account_name' => ['bank_account_name', 'bank_account', 'account_name', 'payment_account_name', 'account_number', 'payment_account_number'],
            'card_name' => ['card_name', 'card_number', 'card_company_name'],
            'supplier_company_name' => ['supplier_company_name', 'supplier_name', '공급자 상호', '공급자명'],
            'supplier_name' => ['supplier_name', 'supplier_company_name', '공급자 상호', '공급자명'],
            'customer_company_name' => ['customer_company_name', 'customer_name', '공급받는자 상호', '공급받는자명'],
            'customer_name' => ['customer_name', 'customer_company_name', '공급받는자 상호', '공급받는자명'],
            'supplier_business_number' => ['supplier_business_number', '공급자 사업자등록번호'],
            'customer_business_number' => ['customer_business_number', '공급받는자 사업자등록번호'],
            'item_date' => ['item_date', 'raw_item_date'],
            'item_name' => ['item_name', 'raw_item_name', '품목명', '품목'],
            'item_spec' => ['item_spec', 'raw_item_spec', '품목규격', '규격'],
            'item_qty' => ['item_qty', 'raw_item_quantity', '품목수량', '수량'],
            'item_price' => ['item_price', 'raw_item_unit_price', '품목단가', '단가'],
            'item_supply_amount' => ['item_supply_amount', 'raw_item_supply_amount'],
            'item_vat_amount' => ['item_vat_amount', 'raw_item_tax_amount'],
            'item_note' => ['item_note', 'raw_item_note'],
            'issue_date' => ['issue_date', '발급일자', '발행일자'],
            'transmit_date' => ['transmit_date', '전송일자'],
            'note' => ['note', '비고'],
            'description' => ['description', '적요'],
            'counterparty_name' => ['counterparty_name', 'counterparty_account_holder_name', 'counterparty_account_holder', 'account_holder'],
            'counterparty_account_number' => ['counterparty_account_number', 'counterparty_account_no', 'account_number'],
            'counterparty_bank_name' => ['counterparty_bank_name', 'counterparty_bank', 'bank_name'],
            'counterparty_bank' => ['counterparty_bank', 'counterparty_bank_name', 'bank_name'],
            'client_company_name' => ['client_company_name', 'counterparty_name', '가맹점', '가맹점명', '사용처'],
        ];

        foreach ($aliases as $target => $keys) {
            if (isset($payload[$target]) && trim((string) $this->payloadScalarForStorage($payload[$target])) !== '') {
                $payload[$target] = $this->payloadScalarForStorage($payload[$target]);
                continue;
            }

            foreach ($keys as $key) {
                $value = $this->payloadScalarForStorage($payload[$key] ?? null);
                if ($value !== null && trim((string) $value) !== '') {
                    $payload[$target] = $value;
                    break;
                }
            }
        }

        $payload = $this->normalizeTransactionDirectionPayload($payload);

        return $this->normalizeMappedClientReference(($this->normalizeBusinessRefPayload)($payload));
    }

    private function syncTaxInvoiceResponseAliases(array $payload): array
    {
        if (!$this->isTaxInvoiceLikePayload($payload)) {
            return $payload;
        }

        $pairs = [
            'raw_written_date' => ['written_date', 'write_date', 'evidence_date'],
            'raw_issue_date' => ['issue_date', 'transaction_date'],
            'raw_transmit_date' => ['transmit_date'],
            'raw_supplier_company_name' => ['supplier_company_name', 'supplier_name'],
            'raw_customer_company_name' => ['customer_company_name', 'customer_name'],
            'raw_item_date' => ['item_date'],
            'raw_item_name' => ['item_name'],
            'raw_item_spec' => ['item_spec'],
            'raw_item_quantity' => ['item_qty', 'quantity'],
            'raw_item_unit_price' => ['item_price', 'unit_price'],
            'raw_item_supply_amount' => ['item_supply_amount'],
            'raw_item_tax_amount' => ['item_vat_amount'],
            'raw_item_note' => ['item_note'],
        ];

        foreach ($pairs as $rawKey => $normalizedKeys) {
            if (array_key_exists($rawKey, $payload)) {
                $rawValue = $this->payloadScalarForStorage($payload[$rawKey] ?? null);
                foreach ($normalizedKeys as $normalizedKey) {
                    $payload[$normalizedKey] = $rawValue ?? '';
                }
                continue;
            }

            $rawValue = $this->payloadScalarForStorage($payload[$rawKey] ?? null);
            if ($rawValue !== null && trim((string) $rawValue) !== '') {
                foreach ($normalizedKeys as $normalizedKey) {
                    $normalizedValue = $this->payloadScalarForStorage($payload[$normalizedKey] ?? null);
                    if ($normalizedValue === null || trim((string) $normalizedValue) === '') {
                        $payload[$normalizedKey] = $rawValue;
                    }
                }
                continue;
            }

            foreach ($normalizedKeys as $normalizedKey) {
                $normalizedValue = $this->payloadScalarForStorage($payload[$normalizedKey] ?? null);
                if ($normalizedValue !== null && trim((string) $normalizedValue) !== '') {
                    $payload[$rawKey] = $normalizedValue;
                    break;
                }
            }
        }

        return $payload;
    }

    private function normalizeTransactionDirectionPayload(array $payload): array
    {
        $direction = $this->normalizeTransactionDirectionValue($payload['transaction_direction'] ?? null);
        if ($direction !== null) {
            $payload['transaction_direction'] = $direction;
        }

        return $payload;
    }

    private function normalizeTransactionDirectionValue(mixed $value): ?string
    {
        $text = strtoupper(trim((string) $this->payloadScalarForStorage($value)));
        if ($text === '') {
            return null;
        }

        return match ($text) {
            'SALES', 'SALE', 'SELL', 'OUT_SALE', 'INCOME' => 'INCOME',
            'PURCHASE', 'BUY', 'IN_PURCHASE', 'EXPENSE' => 'EXPENSE',
            'IN', 'OUT', 'DEPOSIT', 'RECEIPT', 'WITHDRAW', 'WITHDRAWAL', 'PAYMENT', 'FUND' => 'FUND',
            default => $text,
        };
    }

    public function requiredFormatMissingMessages(array $payload, array $columns): array
    {
        $messages = [];
        $displayNameMap = $this->policyMapFromPayload($payload, '_column_display_name', $_REQUEST['column_display_name'] ?? null);
        $requirementPolicyMap = $this->policyMapFromPayload($payload, '_column_requirement_policy', $_REQUEST['column_requirement_policy'] ?? ($_REQUEST['column_requirement'] ?? null));
        $policyColumns = $columns !== [] ? $columns : $this->policyColumnsFromMaps($displayNameMap, $requirementPolicyMap);

        foreach ($policyColumns as $column) {
            if (!$this->isRequiredColumnByPolicy($column, $requirementPolicyMap)) {
                continue;
            }

            $field = trim((string) ($column['system_field_name'] ?? ''));
            $columnKey = trim((string) ($column['original_column_key'] ?? $field));
            $excelName = trim((string) ($column['excel_column_name'] ?? ''));
            $displayName = trim((string) ($displayNameMap[$columnKey] ?? $displayNameMap[$field] ?? ''));
            $label = $displayName !== '' ? $displayName : ($excelName !== '' ? $excelName : ($this->fieldLabel)($field));
            $value = $field !== '' ? ($payload[$field] ?? null) : ($payload[$excelName] ?? null);
            if ($this->isBlankRequiredValue($value)) {
                $messages[] = (preg_replace('/\s*\*$/u', '', $label) ?? $label) . ' ' . json_decode('"\uD544\uC218\uAC12\uC774 \uC5C6\uC2B5\uB2C8\uB2E4."');
            }
        }

        return array_values(array_unique($messages));
    }

    public function formatColumnsInOrder(array $columns): array
    {
        foreach ($columns as $index => &$column) {
            if (!is_array($column)) {
                $column = [];
            }
            $column['_original_order_index'] = $index;
        }
        unset($column);

        usort($columns, static function (array $a, array $b): int {
            $aOrder = (int) ($a['column_order'] ?? 0);
            $bOrder = (int) ($b['column_order'] ?? 0);
            $aExcel = (int) ($a['excel_column_index'] ?? 0);
            $bExcel = (int) ($b['excel_column_index'] ?? 0);
            $aPrimary = $aOrder > 0 ? $aOrder : ($aExcel > 0 ? $aExcel : ((int) ($a['_original_order_index'] ?? 0) + 1));
            $bPrimary = $bOrder > 0 ? $bOrder : ($bExcel > 0 ? $bExcel : ((int) ($b['_original_order_index'] ?? 0) + 1));

            return [$aPrimary, $aExcel, (int) ($a['_original_order_index'] ?? 0)]
                <=> [$bPrimary, $bExcel, (int) ($b['_original_order_index'] ?? 0)];
        });

        return array_map(static function (array $column): array {
            unset($column['_original_order_index']);
            return $column;
        }, $columns);
    }

    private function payloadScalarForStorage(mixed $value, bool $preferId = false): mixed
    {
        if ($value === null || is_scalar($value)) {
            $text = trim((string) $value);
            if ($text === '[object Object]' || ($this->isEmptySelectionLabel)($text)) {
                return '';
            }

            return $value;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $parts = [];
                foreach ($value as $item) {
                    $text = trim((string) $this->payloadScalarForStorage($item, $preferId));
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }

                return implode(', ', array_values(array_unique($parts)));
            }

            $candidates = $preferId
                ? ['id', 'value', 'code', 'text', 'label', 'name']
                : ['text', 'label', 'name', 'value', 'code_name', 'code', 'client_name', 'company_name', 'project_name', 'employee_name', 'account_name', 'bank_name'];
            foreach ($candidates as $key) {
                if (array_key_exists($key, $value)) {
                    $text = trim((string) $this->payloadScalarForStorage($value[$key], false));
                    if ($text !== '') {
                        return $text;
                    }
                }
            }

            return '';
        }

        return '';
    }

    private function normalizeTaxInvoiceRawItemPayload(array $mapped): array
    {
        if (!$this->isTaxInvoiceLikePayload($mapped)) {
            return $mapped;
        }

        foreach ([
            'item_date',
            'item_name',
            'item_spec',
            'item_qty',
            'item_price',
            'item_supply_amount',
            'item_vat_amount',
            'item_note',
        ] as $legacyField) {
            unset($mapped[$legacyField]);
        }

        return $mapped;
    }

    private function syncTaxInvoiceRawAliases(array $mapped): array
    {
        if (!$this->isTaxInvoiceLikePayload($mapped)) {
            return $mapped;
        }

        $rawPriorityMap = [
            'raw_written_date' => ['written_date', 'write_date', 'evidence_date'],
            'raw_issue_date' => ['issue_date', 'transaction_date'],
            'raw_transmit_date' => ['transmit_date'],
            'raw_supplier_business_number' => ['supplier_business_number'],
            'raw_supplier_branch_no' => ['supplier_branch_number'],
            'raw_supplier_company_name' => ['supplier_company_name', 'supplier_name'],
            'raw_supplier_ceo_name' => ['supplier_ceo_name'],
            'raw_supplier_address' => ['supplier_address'],
            'raw_supplier_email' => ['supplier_email'],
            'raw_customer_business_number' => ['customer_business_number'],
            'raw_customer_branch_no' => ['customer_branch_number'],
            'raw_customer_company_name' => ['customer_company_name', 'customer_name'],
            'raw_customer_ceo_name' => ['customer_ceo_name'],
            'raw_customer_address' => ['customer_address'],
            'raw_customer_email1' => ['customer_email_1'],
            'raw_customer_email2' => ['customer_email_2'],
            'raw_supply_amount' => ['supply_amount'],
            'raw_vat_amount' => ['vat_amount'],
            'raw_total_amount' => ['total_amount'],
            'raw_invoice_category' => ['tax_invoice_category'],
            'raw_invoice_kind' => ['tax_invoice_type'],
            'raw_issue_type' => ['issue_type'],
            'raw_claim_type' => ['receipt_claim_type'],
            'raw_item_date' => ['item_date'],
            'raw_item_name' => ['item_name'],
            'raw_item_spec' => ['item_spec'],
            'raw_item_quantity' => ['item_qty', 'quantity'],
            'raw_item_unit_price' => ['item_price', 'unit_price'],
            'raw_item_supply_amount' => ['item_supply_amount'],
            'raw_item_tax_amount' => ['item_vat_amount'],
            'raw_item_note' => ['item_note'],
            'raw_note' => ['note', 'description'],
        ];

        foreach ($rawPriorityMap as $rawKey => $targetKeys) {
            if (array_key_exists($rawKey, $mapped)) {
                $rawValue = $this->payloadScalarForStorage($mapped[$rawKey] ?? null);
                foreach ($targetKeys as $targetKey) {
                    $mapped[$targetKey] = $rawValue ?? '';
                }
                continue;
            }

            $rawValue = $this->payloadScalarForStorage($mapped[$rawKey] ?? null);
            if ($rawValue === null || trim((string) $rawValue) === '') {
                continue;
            }

            foreach ($targetKeys as $targetKey) {
                $mapped[$targetKey] = $rawValue;
            }
        }

        return $mapped;
    }

    private function isTaxInvoiceLikePayload(array $payload): bool
    {
        foreach (['data_type', 'import_type', 'source_type'] as $key) {
            $type = strtoupper(trim((string) ($payload[$key] ?? '')));
            if ($type === 'TAX_INVOICE' || $this->isManualTaxInvoiceTypeCode($type)) {
                return true;
            }
        }

        return false;
    }

    private function isManualTaxInvoiceTypeCode(string $type): bool
    {
        if ($type === '') {
            return false;
        }

        return in_array($type, [
            'TAX_INVOICE_MANUAL',
            'MANUAL_TAX_INVOICE',
            'TAX_INVOICE_PURCHASE_SALES_MANUAL',
            'TAX_INVOICE_BUY_SELL_MANUAL',
        ], true);
    }

    private function policyMapFromPayload(array $payload, string $payloadKey, mixed $requestRaw = null): array
    {
        $raw = $payload[$payloadKey] ?? $requestRaw;
        if (is_array($raw)) {
            return $raw;
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $parsed = json_decode($text, true);
        return is_array($parsed) ? $parsed : [];
    }

    private function policyColumnsFromMaps(array $displayNameMap, array $requirementPolicyMap): array
    {
        $keys = array_values(array_unique(array_filter(array_merge(array_keys($displayNameMap), array_keys($requirementPolicyMap)), static fn($key): bool => trim((string) $key) !== '')));

        return array_map(static function ($key): array {
            $name = trim((string) $key);
            return [
                'original_column_key' => $name,
                'system_field_name' => $name,
                'excel_column_name' => $name,
                'is_required' => 0,
            ];
        }, $keys);
    }

    private function isRequiredColumnByPolicy(array $column, array $requirementPolicyMap): bool
    {
        $field = trim((string) ($column['system_field_name'] ?? ''));
        $columnKey = trim((string) ($column['original_column_key'] ?? $field));
        $policy = strtolower(trim((string) ($requirementPolicyMap[$columnKey] ?? $requirementPolicyMap[$field] ?? '')));
        if ($policy !== '') {
            return $policy === 'required';
        }

        return (($this->isRequiredFormatColumn)($column));
    }

    private function normalizeMappedClientReference(array $mapped): array
    {
        if (!isset($mapped['client_id'])) {
            return $mapped;
        }

        $clientId = trim((string) $mapped['client_id']);
        if ($clientId === '' || ($this->isUuid)($clientId)) {
            return $mapped;
        }

        $label = trim((string) $this->payloadScalarForStorage($mapped['client_name'] ?? ''));
        if ($label === '') {
            $label = $clientId;
        }

        $mapped['client_name'] = $label;
        $mapped['client_company_name'] = trim((string) $this->payloadScalarForStorage($mapped['client_company_name'] ?? $label));
        $mapped['client_id'] = '';

        return $mapped;
    }

    private function normalizeMappedPayloadDateValues(array $mapped): array
    {
        foreach ($mapped as $key => $value) {
            if (!is_scalar($value) || $value === null) {
                continue;
            }

            if ($this->isMappedDateTimeKey((string) $key)) {
                $normalized = $this->mappedDateTimeValueOrNull($value);
                if ($normalized !== null) {
                    $mapped[$key] = $normalized;
                }
                continue;
            }

            if (str_ends_with((string) $key, '_date')) {
                $normalized = ($this->dateValueOrNull)($value);
                if ($normalized !== null) {
                    $mapped[$key] = $normalized;
                }
            }
        }

        return $mapped;
    }

    private function isMappedDateTimeKey(string $key): bool
    {
        return in_array($key, ['transaction_datetime', 'approved_at', 'approval_datetime'], true);
    }

    private function mappedDateTimeValueOrNull(mixed $value): ?string
    {
        $normalized = ($this->dateTimeValue)($value);
        if ($normalized !== null) {
            return $normalized;
        }

        $date = ($this->dateValueOrNull)($value);
        return $date !== null ? $date . ' 00:00:00' : null;
    }

    private function isBlankRequiredValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        $text = trim((string) ($value ?? ''));
        return $text === '' || ($this->isEmptySelectionLabel)($text);
    }
}
