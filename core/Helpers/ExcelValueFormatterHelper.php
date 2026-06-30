<?php

namespace Core\Helpers;

use Core\Security\Crypto;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelValueFormatterHelper
{
    private const TEXT_KEYS = [
        'account_number',
        'account_no',
        'business_number',
        'business_no',
        'corporate_number',
        'corporate_registration_number',
        'resident_registration_number',
        'rrn',
        'phone',
        'phone_number',
        'mobile',
        'mobile_phone',
        'cellphone',
        'fax',
        'zip_code',
        'zipcode',
        'postal_code',
        'card_number',
        'approval_number',
        'customer_number',
        'management_number',
        'manager_number',
        'member_number',
        'source_key',
        'external_key',
        'rule_code',
        'account_code',
        'debit_account_code',
        'credit_account_code',
        'vat_account_code',
        'code',
        'code_group',
        'counterparty_account_number',
        'payment_account_number',
        'merchant_business_number',
        'supplier_business_number',
        'customer_business_number',
        'client_business_number',
        'own_business_number',
        'bank_reference_no',
    ];

    private const TEXT_KEY_PARTIALS = [
        'account_number',
        'business_number',
        'registration_number',
        'card_number',
        'approval_number',
        'customer_number',
        'management_number',
        'member_number',
        'phone',
        'mobile',
        'fax',
        'zip_code',
        'postal_code',
        'source_key',
        'external_key',
    ];

    private const BANK_NAME_KEYS = [
        'bank_name',
        'payment_bank_name',
        'counterparty_bank_name',
    ];

    private const BUSINESS_NUMBER_KEYS = [
        'business_number',
        'business_no',
        'merchant_business_number',
        'supplier_business_number',
        'customer_business_number',
        'client_business_number',
        'own_business_number',
    ];

    private const RRN_KEYS = [
        'rrn',
        'resident_registration_number',
        'corporate_registration_number',
        'corporate_number',
    ];

    private const MOBILE_KEYS = [
        'mobile',
        'mobile_phone',
        'cellphone',
        'ceo_phone',
        'manager_phone',
        'emergency_phone',
    ];

    private const PHONE_KEYS = [
        'phone',
        'phone_number',
        'fax',
        'merchant_phone',
    ];

    private const ACCOUNT_NUMBER_KEYS = [
        'account_number',
        'account_no',
        'counterparty_account_number',
        'payment_account_number',
        'bank_reference_no',
        'card_number',
        'approval_number',
        'customer_number',
        'management_number',
        'manager_number',
        'member_number',
    ];

    private const FALLBACK_ACCOUNT_GROUPS = [
        10 => [2, 2, 6],
        11 => [3, 2, 6],
        12 => [3, 3, 6],
        13 => [4, 3, 6],
        14 => [3, 6, 2, 3],
    ];

    private const BANK_NAME_CODE_MAP = [
        '002' => '산업은행',
        '003' => '기업은행',
        '004' => '국민은행',
        '007' => '수협은행',
        '011' => '농협은행',
        '020' => '우리은행',
        '023' => 'SC제일은행',
        '027' => '한국씨티은행',
        '031' => '대구은행',
        '032' => '부산은행',
        '034' => '광주은행',
        '035' => '제주은행',
        '037' => '전북은행',
        '039' => '경남은행',
        '045' => '새마을금고',
        '048' => '신협',
        '071' => '우체국',
        '081' => '하나은행',
        '088' => '신한은행',
        '089' => '케이뱅크',
        '090' => '카카오뱅크',
        '092' => '토스뱅크',
    ];

    public static function writeTable(
        Worksheet $sheet,
        array $headers,
        array $rows,
        string $startCell = 'A1',
        array $columnMeta = [],
        array $options = []
    ): void {
        [$startColumn, $startRow] = Coordinate::coordinateFromString($startCell);
        $startColumnIndex = Coordinate::columnIndexFromString($startColumn);

        self::writeHeaderRow($sheet, $headers, $startColumnIndex, (int) $startRow, $columnMeta, $options);
        self::writeRows(
            $sheet,
            $rows,
            Coordinate::stringFromColumnIndex($startColumnIndex) . ((int) $startRow + 1),
            $columnMeta,
            $headers
        );
    }

    public static function writeRows(
        Worksheet $sheet,
        array $rows,
        string $startCell = 'A2',
        array $columnMeta = [],
        array $headers = []
    ): void {
        [$startColumn, $startRow] = Coordinate::coordinateFromString($startCell);
        $startColumnIndex = Coordinate::columnIndexFromString($startColumn);

        foreach (array_values($rows) as $rowOffset => $rowValues) {
            $rowNumber = (int) $startRow + $rowOffset;
            self::writeDataRow($sheet, (array) $rowValues, $startColumnIndex, $rowNumber, $headers, $columnMeta);
        }
    }

    public static function sortRowsBySortNo(array $rows): array
    {
        $indexedRows = array_values(array_map(
            static fn(array $row, int $index): array => ['row' => $row, '_index' => $index],
            $rows,
            array_keys($rows)
        ));

        usort($indexedRows, static function (array $left, array $right): int {
            $leftSortNo = is_numeric($left['row']['sort_no'] ?? null) ? (int) $left['row']['sort_no'] : PHP_INT_MAX;
            $rightSortNo = is_numeric($right['row']['sort_no'] ?? null) ? (int) $right['row']['sort_no'] : PHP_INT_MAX;

            return [$leftSortNo, (int) $left['_index']] <=> [$rightSortNo, (int) $right['_index']];
        });

        return array_map(static fn(array $item): array => $item['row'], $indexedRows);
    }

    private static function writeHeaderRow(
        Worksheet $sheet,
        array $headers,
        int $startColumnIndex,
        int $rowNumber,
        array $columnMeta,
        array $options
    ): void {
        $showRequiredAsterisk = ($options['showRequiredAsterisk'] ?? false) === true;

        foreach (array_values($headers) as $offset => $header) {
            $cell = Coordinate::stringFromColumnIndex($startColumnIndex + $offset) . $rowNumber;
            $meta = self::columnMetaForOffset($offset, $headers, $columnMeta);
            $headerText = (string) $header;

            $requirementPolicy = self::requirementPolicy($meta);
            if ($showRequiredAsterisk && $requirementPolicy !== 'none' && $headerText !== '') {
                $richText = new RichText();
                $richText->createText($headerText . ' ');
                $asterisk = $richText->createTextRun('*');
                $asterisk->getFont()
                    ->setBold(true)
                    ->getColor()
                    ->setARGB($requirementPolicy === 'required' ? 'FFDC2626' : 'FF2563EB');
                $sheet->setCellValue($cell, $richText);
                continue;
            }

            $sheet->setCellValue($cell, $headerText);
        }
    }

    private static function writeDataRow(
        Worksheet $sheet,
        array $rowValues,
        int $startColumnIndex,
        int $rowNumber,
        array $headers,
        array $columnMeta
    ): void {
        foreach (array_values($rowValues) as $offset => $value) {
            $cell = Coordinate::stringFromColumnIndex($startColumnIndex + $offset) . $rowNumber;
            $meta = self::columnMetaForOffset($offset, $headers, $columnMeta);
            $resolvedValue = self::normalizeDisplayValue($value, $meta);

            if (self::shouldWriteAsString($meta)) {
                $sheet->setCellValueExplicit($cell, self::stringify($resolvedValue), DataType::TYPE_STRING);
                continue;
            }

            $sheet->setCellValue($cell, $resolvedValue);
        }
    }

    private static function columnMetaForOffset(int $offset, array $headers, array $columnMeta): array
    {
        $meta = isset($columnMeta[$offset]) && is_array($columnMeta[$offset]) ? $columnMeta[$offset] : [];

        if (!isset($meta['header']) && array_key_exists($offset, $headers)) {
            $meta['header'] = (string) $headers[$offset];
        }

        return $meta;
    }

    private static function isRequiredColumn(array $meta): bool
    {
        return ($meta['required'] ?? false) === true
            || ($meta['systemRequired'] ?? false) === true
            || (int) ($meta['is_required'] ?? 0) === 1;
    }

    private static function requirementPolicy(array $meta): string
    {
        $policy = strtolower(trim((string) ($meta['requirement_policy'] ?? '')));
        if ($policy === 'required') {
            return 'required';
        }
        if ($policy === 'optional') {
            return 'optional';
        }

        return self::isRequiredColumn($meta) ? 'required' : 'none';
    }

    private static function normalizeDisplayValue(mixed $value, array $meta): mixed
    {
        if (!is_scalar($value) && $value !== null) {
            return $value;
        }

        $resolved = self::normalizeSensitiveDisplayValue($value, $meta);

        if (!self::isBankNameColumn($meta)) {
            return $resolved;
        }

        $bankValue = trim((string) $resolved);
        if ($bankValue === '') {
            return $resolved;
        }

        return self::BANK_NAME_CODE_MAP[$bankValue] ?? $resolved;
    }

    private static function shouldWriteAsString(array $meta): bool
    {
        foreach (self::candidateTokens($meta) as $token) {
            if ($token === '') {
                continue;
            }

            if (in_array($token, self::TEXT_KEYS, true)) {
                return true;
            }

            if (str_ends_with($token, '_code') || str_ends_with($token, '_number')) {
                return true;
            }

            foreach (self::TEXT_KEY_PARTIALS as $partial) {
                if (str_contains($token, $partial)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isBankNameColumn(array $meta): bool
    {
        foreach (self::candidateTokens($meta) as $token) {
            if (in_array($token, self::BANK_NAME_KEYS, true)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeSensitiveDisplayValue(mixed $value, array $meta): mixed
    {
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return $value;
        }

        foreach (self::candidateTokens($meta) as $token) {
            if (in_array($token, self::RRN_KEYS, true)) {
                $decrypted = self::decryptResidentNumber($stringValue);
                $normalized = self::digitsOnly($decrypted !== '' ? $decrypted : $stringValue);
                return self::formatCorporateNumber($normalized);
            }

            if (in_array($token, self::BUSINESS_NUMBER_KEYS, true)) {
                return self::formatBusinessNumber(self::digitsOnly($stringValue));
            }

            if (in_array($token, self::MOBILE_KEYS, true)) {
                return self::formatMobileNumber(self::digitsOnly($stringValue));
            }

            if (in_array($token, self::PHONE_KEYS, true)) {
                return self::formatPhoneNumber(self::digitsOnly($stringValue));
            }

            if (in_array($token, self::ACCOUNT_NUMBER_KEYS, true)) {
                return self::formatAccountNumber(self::digitsOnly($stringValue));
            }
        }

        return $value;
    }

    private static function candidateTokens(array $meta): array
    {
        $tokens = [];

        foreach (['key', 'source_key', 'payload_key', 'header', 'label', 'excel_column_name', 'system_field_name'] as $field) {
            if (!isset($meta[$field])) {
                continue;
            }

            $normalized = self::normalizeToken((string) $meta[$field]);
            if ($normalized !== '') {
                $tokens[] = $normalized;
            }
        }

        return array_values(array_unique($tokens));
    }

    private static function normalizeToken(string $value): string
    {
        $normalized = strtolower(trim($value));
        return preg_replace('/[^a-z0-9_]+/', '', $normalized) ?? '';
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private static function decryptResidentNumber(string $value): string
    {
        try {
            return (new Crypto())->decryptResidentNumber($value);
        } catch (\Throwable) {
            return '';
        }
    }

    private static function formatBusinessNumber(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 3) {
            return $value;
        }

        if (strlen($value) <= 5) {
            return preg_replace('/(\d{3})(\d+)/', '$1-$2', $value) ?? $value;
        }

        return preg_replace('/(\d{3})(\d{2})(\d+)/', '$1-$2-$3', $value) ?? $value;
    }

    private static function formatCorporateNumber(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 6) {
            return $value;
        }

        return preg_replace('/(\d{6})(\d+)/', '$1-$2', $value) ?? $value;
    }

    private static function formatMobileNumber(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 3) {
            return $value;
        }

        if (strlen($value) <= 7) {
            return preg_replace('/(\d{3})(\d+)/', '$1-$2', $value) ?? $value;
        }

        return preg_replace('/(\d{3})(\d{4})(\d+)/', '$1-$2-$3', $value) ?? $value;
    }

    private static function formatPhoneNumber(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '02')) {
            if (strlen($value) <= 2) {
                return $value;
            }
            if (strlen($value) <= 5) {
                return preg_replace('/(\d{2})(\d+)/', '$1-$2', $value) ?? $value;
            }
            if (strlen($value) <= 9) {
                return preg_replace('/(\d{2})(\d{3})(\d+)/', '$1-$2-$3', $value) ?? $value;
            }

            return preg_replace('/(\d{2})(\d{4})(\d+)/', '$1-$2-$3', $value) ?? $value;
        }

        if (strlen($value) <= 3) {
            return $value;
        }
        if (strlen($value) <= 6) {
            return preg_replace('/(\d{3})(\d+)/', '$1-$2', $value) ?? $value;
        }
        if (strlen($value) <= 10) {
            return preg_replace('/(\d{3})(\d{3})(\d+)/', '$1-$2-$3', $value) ?? $value;
        }

        return preg_replace('/(\d{3})(\d{4})(\d+)/', '$1-$2-$3', $value) ?? $value;
    }

    private static function formatAccountNumber(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $groups = self::FALLBACK_ACCOUNT_GROUPS[strlen($value)] ?? null;
        if (!is_array($groups) || $groups === []) {
            return $value;
        }

        $parts = [];
        $offset = 0;
        foreach ($groups as $length) {
            if ($offset >= strlen($value)) {
                break;
            }
            $parts[] = substr($value, $offset, $length);
            $offset += $length;
        }

        if ($offset < strlen($value)) {
            $parts[] = substr($value, $offset);
        }

        return implode('-', array_filter($parts, static fn(string $part): bool => $part !== ''));
    }
}
