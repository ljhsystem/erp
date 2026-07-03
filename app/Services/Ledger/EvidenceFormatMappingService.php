<?php

namespace App\Services\Ledger;

class EvidenceFormatMappingService
{
    public function __construct(private array $callbacks = [])
    {
    }

    public function format(string $id): ?array
    {
        return null;
    }

    public function formatWithColumns(string $id): ?array
    {
        return null;
    }

    public function columns(string $formatId): array
    {
        return [];
    }

    public function legacySystemFieldToDbColumn(string $dataType, string $field): ?string
    {
        $field = trim($field);
        if ($field === '') {
            return null;
        }

        $dataType = $this->call('normalizeDataType', $dataType);
        if ($dataType === 'BANK_TRANSACTION') {
            return [
                'transaction_date' => 'transaction_date',
                'transaction_datetime' => 'transaction_datetime',
                'transaction_at' => 'transaction_datetime',
                'transaction_time' => 'transaction_datetime',
                'bank_account_id' => 'bank_account_name',
                'bank_account' => 'bank_account_name',
                'bank_account_name' => 'bank_account_name',
                'account_name' => 'bank_account_name',
                'payment_account_name' => 'bank_account_name',
                'operation_type' => 'operation_type',
                'bank_direction' => 'bank_direction',
                'transaction_direction' => 'transaction_direction',
                'deposit_amount' => 'deposit_amount',
                'withdraw_amount' => 'withdraw_amount',
                'withdrawal_amount' => 'withdraw_amount',
                'balance_amount' => 'balance_amount',
                'check_bill_amount' => 'check_bill_amount',
                'check_amount' => 'check_bill_amount',
                'bill_amount' => 'check_bill_amount',
                'currency' => 'currency_code',
                'currency_code' => 'currency_code',
                'exchange_rate' => 'exchange_rate',
                'description' => 'description',
                'counterparty_name' => 'counterparty_name',
                'client_name' => 'counterparty_name',
                'counterparty_account_holder_name' => 'counterparty_name',
                'counterparty_account_holder' => 'counterparty_name',
                'account_holder' => 'counterparty_name',
                'counterparty_account_number' => 'counterparty_account_number',
                'counterparty_account_no' => 'counterparty_account_number',
                'account_number' => 'counterparty_account_number',
                'counterparty_bank_name' => 'counterparty_bank_name',
                'counterparty_bank' => 'counterparty_bank_name',
                'bank_name' => 'counterparty_bank_name',
                'bank_reference_no' => 'bank_reference_no',
                'approval_number' => 'bank_reference_no',
                'approval_no' => 'bank_reference_no',
                'source_key' => 'bank_reference_no',
                'memo' => 'memo',
                'note' => 'memo',
                'debit_account_id' => 'debit_account_id',
                'debit_amount' => 'debit_amount',
                'debit_line_summary' => 'debit_line_summary',
                'credit_account_id' => 'credit_account_id',
                'credit_amount' => 'credit_amount',
                'credit_line_summary' => 'credit_line_summary',
            ][$field] ?? null;
        }

        return [
            'source_key' => 'source_key',
            'approval_number' => 'source_key',
            'approval_no' => 'source_key',
            'transaction_date' => 'evidence_date',
            'write_date' => 'evidence_date',
            'evidence_date' => 'evidence_date',
            'client_id' => 'client_id',
            'project_id' => 'project_id',
            'bank_account_id' => 'bank_account_name',
            'bank_account' => 'bank_account_name',
            'bank_account_name' => 'bank_account_name',
            'account_name' => 'bank_account_name',
            'payment_account_name' => 'bank_account_name',
            'currency' => 'currency',
            'currency_code' => 'currency',
            'supply_amount' => 'supply_amount',
            'vat_amount' => 'vat_amount',
            'total_amount' => 'total_amount',
            'amount' => 'total_amount',
        ][$field] ?? null;
    }

    public function canonicalSystemFieldForFormatColumn(string $dataType, string $excelColumnName, string $systemField): string
    {
        $dataType = $this->call('normalizeDataType', $dataType);
        $cleanExcelName = preg_replace('/\s+/u', '', trim($excelColumnName)) ?? trim($excelColumnName);

        if ($dataType === 'BANK_TRANSACTION'
            && in_array($cleanExcelName, ['거래일시', '거래일자', '거래시간'], true)
            && $systemField === 'transaction_time'
        ) {
            return 'transaction_datetime';
        }
        if ($dataType === 'BANK_TRANSACTION'
            && in_array($cleanExcelName, ['거래구분', '원본거래구분'], true)
            && in_array($systemField, ['operation_type', 'transaction_direction'], true)
        ) {
            return 'bank_direction';
        }
        if (in_array($dataType, ['CARD_HOMETAX', 'CARD_STATEMENT', 'CARD_APPROVAL', 'CASH_RECEIPT'], true)
            && $cleanExcelName === '거래처명'
            && $systemField === 'client_id'
        ) {
            return 'merchant_company_name';
        }
        if ($dataType === 'CARD_HOMETAX'
            && in_array($cleanExcelName, ['카드사명', '카드사', '카드회사'], true)
            && in_array($systemField, ['client_id', 'client_name', 'card_name', 'card_company_name'], true)
        ) {
            return 'source_card_company_name';
        }

        return $systemField;
    }

    public function templateFieldGroup(string $field, string $dataType): string
    {
        $field = trim($field);
        if ($field === '') {
            return '';
        }

        $option = $this->call('systemFieldOptionsByValue', $dataType)[$field] ?? null;

        return is_array($option) ? (string) ($option['group'] ?? '') : '';
    }

    public function isStandardInfoTemplateColumn(string $field, string $header, string $dataType): bool
    {
        $group = $this->templateFieldGroup($field, $dataType);
        if ($group !== '') {
            return str_contains($group, '기준정보');
        }

        return in_array(trim($field), [
            'source_type',
            'import_type',
            'data_type',
            'evidence_type',
            'business_unit',
            'operation_type',
            'transaction_direction',
            'bank_direction',
            'currency',
            'currency_code',
            'exchange_rate',
        ], true);
    }

    public function isBasicInfoTemplateColumn(string $field, string $header, string $dataType = ''): bool
    {
        $field = trim($field);
        $group = $this->templateFieldGroup($field, $dataType);
        if ($group !== '') {
            return str_contains($group, '기초정보');
        }
        if (in_array($field, [
            'client_id',
            'client_name',
            'client_company_name',
            'project_id',
            'project_name',
            'employee_id',
            'employee_name',
            'bank_account_id',
            'bank_account_name',
            'account_name',
            'card_id',
            'card_name',
            'team_id',
            'team_name',
            'supplier_company_name',
            'customer_company_name',
        ], true)) {
            return true;
        }

        return false;
    }

    public function isVoucherTemplateColumn(string $field, string $header, string $dataType = ''): bool
    {
        $field = trim($field);
        if ($this->call('normalizeDataType', $dataType) === 'CARD_HOMETAX' && $field === 'note') {
            return false;
        }
        if (in_array($field, [
            'voucher_date',
            'voucher_no',
            'summary_text',
            'note',
            'voucher_memo',
            'header_row_no',
            'line_no',
            'line_row_type',
            'account_id',
            'debit',
            'credit',
            'line_summary',
            'line_ref_type',
            'line_ref_id',
        ], true)) {
            return true;
        }

        return false;
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
