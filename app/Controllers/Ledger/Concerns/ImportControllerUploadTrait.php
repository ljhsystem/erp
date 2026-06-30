<?php

namespace App\Controllers\Ledger\Concerns;

use PDO;

trait ImportControllerUploadTrait
{
    private function normalizeTransactionDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));

        return match ($direction) {
            'PURCHASE', 'BUY', '매입' => 'PURCHASE',
            'SALES', 'SALE', 'SELL', '매출' => 'SALES',
            'IN', 'DEPOSIT', 'RECEIPT', '입금' => 'IN',
            'OUT', 'WITHDRAWAL', 'PAYMENT', '출금' => 'OUT',
            default => '',
        };
    }

    private function deletableSeedRowIdsByImportDate(string $batchId): array
    {
        $batchId = trim($batchId);
        if ($batchId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM ledger_data_evidences
            WHERE DATE(latest_imported_at) = :batch_id
              AND transaction_status IN ('NONE', 'ERROR', 'DUPLICATED')
              AND deleted_at IS NULL
        ");
        $stmt->execute([':batch_id' => $batchId]);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    private function uploadBatch(string $batchId): ?array
    {
        return null;
    }

    private function refreshUploadBatchStatus(string $batchId): void
    {
        return;
    }

    private static function normalizeRequirementMode(mixed $value): int
    {
        $mode = (int) $value;

        return in_array($mode, [0, 1, 2], true) ? $mode : 0;
    }

    private static function isRequiredFormatColumn(array $column): bool
    {
        return self::normalizeRequirementMode($column['is_required'] ?? 0) === 1;
    }

    private static function isOptionalEvidenceFormatColumn(array $column): bool
    {
        $field = trim((string) ($column['system_field_name'] ?? ''));
        $label = preg_replace('/\s+/u', '', trim((string) ($column['excel_column_name'] ?? ''))) ?? '';

        return in_array($field, ['balance_amount', 'supplier_address', 'customer_address'], true)
            || in_array($label, ['거래후잔액', '잔액', '공급자주소', '공급받는자주소'], true);
    }

    private function decodeRequestMap(mixed $raw): array
    {
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

    private function requestColumnDisplayName(array $payload): array
    {
        return $this->decodeRequestMap($payload['column_display_name'] ?? null);
    }

    private function requestColumnRequirementPolicy(array $payload): array
    {
        return $this->decodeRequestMap($payload['column_requirement_policy'] ?? $payload['column_requirement'] ?? $payload['excel_template_column_requirement'] ?? null);
    }

    private function requestRequirementPolicyMode(mixed $value): int
    {
        $normalized = strtolower(trim((string) $value));
        return match ($normalized) {
            'required' => 1,
            'optional' => 2,
            'none', 'hidden' => 0,
            default => self::normalizeRequirementMode($value),
        };
    }

    private function normalizedUploadDataType(string $type): string
    {
        if (method_exists($this, 'normalizeDataType')) {
            return $this->normalizeDataType($type);
        }

        return strtoupper(trim($type));
    }

    private function uploadDataTypes(): array
    {
        $constantName = static::class . '::DATA_TYPES';

        return defined($constantName) ? (array) constant($constantName) : [];
    }

    private function uploadBusinessDataTypes(): array
    {
        $constantName = static::class . '::BUSINESS_DATA_TYPES';

        return defined($constantName) ? (array) constant($constantName) : [];
    }

    private function isAllowedDataType(string $type): bool
    {
        $type = $this->normalizedUploadDataType($type);

        return $type !== '' && in_array(
            $type,
            $this->evidenceTypePolicyService()->allowedDataTypes(
                $this->uploadDataTypes(),
                $this->uploadBusinessDataTypes()
            ),
            true
        );
    }

    private function shouldSyncTaxInvoiceEvidenceClients(string $dataType): bool
    {
        return $dataType === 'TAX_INVOICE' || $this->evidenceTypePolicyService()->isManualTaxInvoiceDataType($dataType);
    }
}
