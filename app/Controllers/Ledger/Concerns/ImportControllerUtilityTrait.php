<?php

namespace App\Controllers\Ledger\Concerns;

trait ImportControllerUtilityTrait
{
    private static function clearOutputBuffers(): void
    {
        if (ob_get_length()) {
            @ob_end_clean();
        }

        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }
    }

    private function tableColumnExists(string $tableName, string $columnName): bool
    {
        static $cache = [];
        $key = $tableName . '.' . $columnName;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
                LIMIT 1
            ");
            $stmt->execute([
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]);
            $cache[$key] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    private function tableExists(string $tableName): bool
    {
        static $cache = [];
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                LIMIT 1
            ");
            $stmt->execute([':table_name' => $tableName]);
            $cache[$tableName] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            $cache[$tableName] = false;
        }

        return $cache[$tableName];
    }

    private function normalizeCompanyNameForCompare(string $companyName): string
    {
        $companyName = $this->cleanCompanyName($companyName);
        $companyName = preg_replace('/\s+/u', '', $companyName) ?? $companyName;
        return function_exists('mb_strtolower') ? mb_strtolower($companyName, 'UTF-8') : strtolower($companyName);
    }

    private static function legacyDataTypeMap(): array
    {
        $constantName = static::class . '::LEGACY_DATA_TYPE_MAP';

        return defined($constantName) ? (array) constant($constantName) : [];
    }

    private static function normalizeDataType(string $type): string
    {
        $type = strtoupper(trim($type));

        return self::legacyDataTypeMap()[$type] ?? $type;
    }

    private function requestPayload(): array
    {
        $raw = file_get_contents('php://input');
        $json = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : null;
        return is_array($json) ? array_replace_recursive($_POST, $json) : $_POST;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function fieldLabel(string $field): string
    {
        return [
            'bank_direction' => '은행 거래방향',
            'cash_receipt_transaction_type' => '현금영수증 거래유형',
            'card_transaction_type' => '카드 거래유형',
            'business_unit' => '사업부문',
            'transaction_type' => '거래유형',
            'transaction_date' => '거래일자',
            'raw_transaction_datetime' => '거래일시',
            'currency_code' => '통화',
            'exchange_rate' => '환율',
            'project_name' => '프로젝트명',
            'description' => '적요',
            'approval_number' => '승인번호',
            'issue_date' => '발급일자',
            'transmit_date' => '전송일자',
            'tax_invoice_category' => '세금계산서 분류',
            'tax_invoice_type' => '세금계산서 종류',
            'issue_type' => '발급유형',
            'receipt_claim_type' => '영수/청구구분',
            'total_amount' => '합계금액',
            'supply_amount' => '공급가액',
            'vat_amount' => '부가세',
            'service_amount' => '봉사료',
            'raw_deposit_amount' => '입금액',
            'raw_withdraw_amount' => '출금액',
            'raw_balance_amount' => '거래후잔액',
            'raw_check_bill_amount' => '수표어음금액',
            'note' => '비고',
            'supplier_business_number' => '공급자 사업자등록번호',
            'supplier_branch_number' => '공급자 종사업장번호',
            'supplier_company_name' => '공급자 상호',
            'supplier_ceo_name' => '공급자 대표자명',
            'supplier_address' => '공급자 주소',
            'supplier_email' => '공급자 이메일',
            'customer_business_number' => '공급받는자 사업자등록번호',
            'customer_branch_number' => '공급받는자 종사업장번호',
            'customer_company_name' => '공급받는자 상호',
            'customer_ceo_name' => '공급받는자 대표자명',
            'customer_address' => '공급받는자 주소',
            'customer_email_1' => '공급받는자 이메일1',
            'customer_email_2' => '공급받는자 이메일2',
            'broker_business_number' => '수탁자 사업자등록번호',
            'broker_company_name' => '수탁자 상호',
            'item_date' => '품목일자',
            'item_name' => '품목명',
            'item_spec' => '규격',
            'item_qty' => '수량',
            'item_price' => '단가',
            'item_supply_amount' => '품목공급가액',
            'item_vat_amount' => '품목세액',
            'item_note' => '품목비고',
            'raw_item_date' => '품목일자',
            'raw_item_name' => '품목명',
            'raw_item_spec' => '품목규격',
            'raw_item_quantity' => '품목수량',
            'raw_item_unit_price' => '품목단가',
            'raw_item_supply_amount' => '품목공급가액',
            'raw_item_tax_amount' => '품목세액',
            'raw_item_note' => '품목비고',
        ][$field] ?? $field;
    }

    private function cellValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        return trim((string) $value);
    }

    private function number(mixed $value): float
    {
        return (float) ($this->amountOrNull($value) ?? 0);
    }

    private function amountOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '-' || strcasecmp($text, 'NaN') === 0) {
            return null;
        }

        $text = strtr($text, [
            '??' => '-',
            '??' => '-',
            '??' => '-',
            '??' => '-',
            '??' => '-',
            '??' => '-',
        ]);

        $sign = 1;
        if (preg_match('/^\(.+\)$/', $text) === 1) {
            $sign = -1;
            $text = substr($text, 1, -1);
        }

        $normalized = preg_replace('/[^0-9.\-]/u', '', $text) ?? '';
        if (str_ends_with($normalized, '-') && strlen($normalized) > 1) {
            $sign *= -1;
            $normalized = rtrim($normalized, '-');
        }

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return is_numeric($normalized) ? ((float) $normalized * $sign) : null;
    }

    private function dateValue(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return date('Y-m-d');
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches) === 1) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $value, $matches) === 1) {
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            }
        }
        if (preg_match('/^(\d{2})(\d{2})[-\/.](\d{2})[-\/.](\d{2})$/', $value, $matches) === 1) {
            return $matches[3] . $matches[4] . '-' . $matches[1] . '-' . $matches[2];
        }
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})/', $value, $matches) === 1) {
            $first = (int) $matches[1];
            $second = (int) $matches[2];
            $month = $first > 12 && $second <= 12 ? $matches[2] : $matches[1];
            $day = $first > 12 && $second <= 12 ? $matches[1] : $matches[2];
            return $matches[3] . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $time = strtotime($value);
        return $time === false ? $value : date('Y-m-d', $time);
    }

    private function dateValueOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || $value === '-' || $value === '0000-00-00') {
            return null;
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches) === 1) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $value, $matches) === 1) {
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            }
        }
        if (preg_match('/^(\d{2})(\d{2})[-\/.](\d{2})[-\/.](\d{2})$/', $value, $matches) === 1) {
            return $matches[3] . $matches[4] . '-' . $matches[1] . '-' . $matches[2];
        }
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})/', $value, $matches) === 1) {
            $first = (int) $matches[1];
            $second = (int) $matches[2];
            $month = $first > 12 && $second <= 12 ? $matches[2] : $matches[1];
            $day = $first > 12 && $second <= 12 ? $matches[1] : $matches[2];
            return $matches[3] . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $time = strtotime($value);
        return $time === false ? null : date('Y-m-d', $time);
    }

    private function dateTimeValue(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
        }

        $time = strtotime($value);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $time);
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim($value));
    }

    private function placeholdersForIds(array $ids, string $prefix): array
    {
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':' . $prefix . '_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        return [implode(', ', $placeholders), $params];
    }

    private static function safeFilename(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('#[\\/:*?""<>|]+#u', '_', $name) ?? '';
        $name = preg_replace('#[^\p{L}\p{N}_. \-()\[\]]+#u', '_', $name) ?? '';
        $name = preg_replace('#\s+#u', '_', $name) ?? '';
        $name = trim($name, "._- \t\n\n\0\x0B");

        return $name !== '' ? $name : 'download';
    }

    private static function safeSheetTitle(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('#[\\/*?:\[\]]+#u', '_', $title) ?? '';
        $title = preg_replace('#\s+#u', ' ', $title) ?? '';
        $title = trim($title, "' ");
        if ($title === '') {
            $title = 'Sheet';
        }

        return function_exists('mb_substr') ? mb_substr($title, 0, 31) : substr($title, 0, 31);
    }
}
