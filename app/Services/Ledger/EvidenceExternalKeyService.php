<?php

namespace App\Services\Ledger;

final class EvidenceExternalKeyService
{
    private const UNIQUE_FIELDS = [
        'BANK_TRANSACTION' => ['bank_reference_no', 'raw_bank_reference_no', 'transaction_id', 'raw_transaction_id'],
        'TAX_INVOICE' => ['external_key', 'source_key', 'approval_number', 'approval_no'],
        'TAX_INVOICE_MANUAL' => ['external_key', 'source_key', 'approval_number', 'approval_no'],
        'CASH_RECEIPT' => ['external_key', 'source_key', 'approval_number', 'approval_no', 'receipt_number'],
        'CARD_HOMETAX' => ['external_key', 'source_key', 'transaction_id', 'approval_number', 'approval_no', 'raw_approval_number', 'raw_approval_no'],
        'CARD_STATEMENT' => ['external_key', 'source_key', 'transaction_id', 'approval_number', 'approval_no', 'raw_approval_number', 'raw_approval_no', 'raw_purchase_number', 'purchase_number'],
        'CARD_APPROVAL' => ['external_key', 'source_key', 'transaction_id', 'approval_number', 'approval_no', 'raw_approval_number', 'raw_approval_no'],
        'SHOPPING_ORDER' => ['external_key', 'source_key', 'order_detail_number', 'settlement_detail_number', 'order_number'],
        'IMPORT_INVOICE' => ['external_key', 'source_key', 'declaration_number', 'receipt_number', 'reference_no'],
        'PAYROLL_REPORT' => ['external_key'],
    ];

    private const SOURCE_FIELDS = [
        'BANK_TRANSACTION' => [
            'bank_account_id', 'source_account_id', 'raw_account_number', 'account_number',
            'raw_transaction_datetime', 'transaction_datetime', 'transaction_at',
            'raw_transaction_type', 'bank_direction', 'raw_deposit_amount', 'deposit_amount',
            'raw_withdraw_amount', 'withdraw_amount', 'withdrawal_amount', 'raw_balance_amount', 'balance_amount',
            'raw_check_bill_amount', 'check_bill_amount', 'raw_description',
            'raw_counterparty_account_number', 'counterparty_account_number',
            'raw_counterparty_bank_name', 'counterparty_bank_name', 'raw_cms_code',
        ],
        'TAX_INVOICE' => ['external_key', 'source_key', 'approval_number', 'approval_no', 'issue_date', 'supplier_business_number', 'customer_business_number', 'supply_amount', 'vat_amount', 'total_amount'],
        'TAX_INVOICE_MANUAL' => ['external_key', 'source_key', 'approval_number', 'approval_no', 'issue_date', 'supplier_business_number', 'customer_business_number', 'supply_amount', 'vat_amount', 'total_amount'],
        'CASH_RECEIPT' => ['external_key', 'source_key', 'approval_number', 'approval_no', 'receipt_number', 'transaction_datetime', 'transaction_date', 'merchant_business_number', 'total_amount'],
        'CARD_HOMETAX' => ['external_key', 'source_key', 'transaction_id', 'approval_number', 'approval_no', 'raw_approval_number', 'raw_approval_no', 'transaction_datetime', 'purchase_datetime', 'card_number', 'merchant_business_number', 'total_amount'],
        'CARD_STATEMENT' => ['external_key', 'source_key', 'transaction_id', 'approval_number', 'approval_no', 'raw_approval_number', 'raw_approval_no', 'raw_purchase_number', 'purchase_number', 'transaction_datetime', 'purchase_datetime', 'card_number', 'merchant_business_number', 'total_amount'],
        'CARD_APPROVAL' => ['external_key', 'source_key', 'transaction_id', 'approval_number', 'approval_no', 'raw_approval_number', 'raw_approval_no', 'transaction_datetime', 'approval_datetime', 'card_number', 'merchant_business_number', 'total_amount'],
    ];

    private const FORBIDDEN_FIELDS = [
        'id', 'evidence_id', 'sort_no', 'file_name', 'filename', 'row_no', '_row_no',
        '_upload_row_no', 'uploaded_at', 'uploaded_by', 'created_at', 'created_by', 'updated_at', 'updated_by',
        'deleted_at', 'deleted_by', 'evidence_status', 'status', 'process_status', 'readiness', 'correction_status',
        'client_id', 'client_name', 'employee_id', 'employee_name', 'project_id', 'project_name', 'business_unit',
        'account_id', 'account_name', 'format_id', 'transaction_status', 'voucher_status',
    ];

    public function key(array $row, string $importType): string
    {
        $type = $this->normalizeType($importType);
        if ($type === 'PAYROLL_REPORT') {
            $sourceId = $this->normalizeScalar('source_regular_employment_income_id', $row['source_regular_employment_income_id'] ?? null);
            $itemId = $this->normalizeScalar('regular_employment_income_item_id', $row['regular_employment_income_item_id'] ?? null);
            if ($sourceId === '' || $itemId === '') {
                throw new \RuntimeException('직원별 급여 증빙 원본 ID와 급여 Item ID가 필요합니다.');
            }
            return hash('sha256', $type . '|' . $sourceId . '|' . $itemId);
        }
        foreach (self::UNIQUE_FIELDS[$type] ?? [] as $field) {
            $value = $this->normalizeScalar($field, $row[$field] ?? null);
            if ($value !== '') {
                return strlen($value) <= 120 ? $value : hash('sha256', $type . '|unique|' . $field . '|' . $value);
            }
        }

        $canonical = $this->canonicalSource($row, $type);
        if ($canonical === '') {
            throw new \RuntimeException('외부 원본 식별키를 생성할 안정적인 원본값이 없습니다.');
        }

        return hash('sha256', $type . '|source|' . $canonical);
    }

    public function contentDigest(array $row, string $importType): string
    {
        return hash('sha256', $this->normalizeType($importType) . '|content|' . $this->canonicalSource($row, $this->normalizeType($importType)));
    }

    public function canonicalSource(array $row, string $importType): string
    {
        $type = $this->normalizeType($importType);
        $fields = self::SOURCE_FIELDS[$type] ?? [];
        if ($fields === []) {
            $fields = array_values(array_filter(array_keys($row), fn(string $key): bool => $this->isAllowedGenericField($key)));
            sort($fields, SORT_STRING);
        }

        $parts = [];
        foreach ($fields as $field) {
            $value = $this->normalizeScalar($field, $row[$field] ?? null);
            if ($value !== '') {
                $parts[] = $field . '=' . $value;
            }
        }

        return implode('|', $parts);
    }

    private function isAllowedGenericField(string $field): bool
    {
        if ($field === '' || str_starts_with($field, '_') || in_array($field, self::FORBIDDEN_FIELDS, true)) {
            return false;
        }
        if (preg_match('/(^|_)(id|name)$/i', $field) === 1 && !preg_match('/(approval|transaction|order|receipt|reference|declaration)/i', $field)) {
            return false;
        }
        return preg_match('/(raw_|source_|approval|transaction|purchase|issue|receipt|order|settlement|declaration|reference|business_number|amount|balance|date|time|number|code)/i', $field) === 1;
    }

    private function normalizeScalar(string $field, mixed $value): string
    {
        if ($value === null || is_array($value) || is_object($value)) return '';
        $text = str_replace(["\r\n", "\r"], "\n", trim((string) $value));
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }
        $text = preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? $text;
        if ($text === '') return '';

        if (preg_match('/(amount|balance|price|fee|tax|deposit|withdraw|supply|vat)/i', $field) === 1) {
            $number = str_replace([',', ' '], '', $text);
            if (is_numeric($number)) return rtrim(rtrim(number_format((float) $number, 6, '.', ''), '0'), '.');
        }
        if (preg_match('/(date|datetime|_at$|time$)/i', $field) === 1) {
            $timestamp = strtotime($text);
            if ($timestamp !== false) {
                return preg_match('/\d{1,2}:\d{2}/', $text) === 1 ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d', $timestamp);
            }
        }
        if (preg_match('/direction|transaction_type/i', $field) === 1) {
            return match (strtoupper($text)) {
                'IN', 'DEPOSIT', '입금' => 'DEPOSIT',
                'OUT', 'WITHDRAW', 'WITHDRAWAL', '출금' => 'WITHDRAW',
                default => strtoupper($text),
            };
        }
        return $text;
    }

    private function normalizeType(string $type): string
    {
        $type = strtoupper(trim($type));
        return $type === 'CARD' ? 'CARD_APPROVAL' : $type;
    }
}
