<?php

namespace App\Services\Ledger;

class EvidencePayloadHelperService
{
    public function __construct(private array $callbacks = [])
    {
    }

    public function isBlankValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_array($value)) {
            return $value === [];
        }

        return trim((string) $value) === '';
    }

    public function payloadScalarForStorage(mixed $value, bool $preferId = false): mixed
    {
        if ($value === null || is_scalar($value)) {
            $text = trim((string) $value);
            if ($text === '[object Object]' || $this->call('isEmptySelectionLabel', $text)) {
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

    public function jsonEncodeForStorage(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function evidenceTypeFromPayload(array $payload): string
    {
        $candidates = ['import_type', 'data_type', 'type', 'evidence_type', 'source_type', 'seed_type'];
        foreach ($candidates as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $this->call('normalizeDataType', $value);
            }
        }

        return '';
    }

    public function seedRowIdsFromPayload(array $payload): array
    {
        $ids = [];
        if (is_array($payload['seed_row_ids'] ?? null)) {
            $ids = array_merge($ids, $payload['seed_row_ids']);
        }
        if (is_array($payload['ids'] ?? null)) {
            $ids = array_merge($ids, $payload['ids']);
        }
        foreach (['seed_row_id', 'id'] as $key) {
            if (!empty($payload[$key])) {
                $ids[] = $payload[$key];
            }
        }

        return array_values(array_unique(array_filter(array_map('strval', $ids))));
    }

    public function evidenceTotalAmountForStorage(array $payload, string $dataType): float
    {
        $dataType = $this->call('normalizeDataType', $dataType);
        if ($dataType === 'BANK_TRANSACTION') {
            $deposit = $this->call('amountOrNull', $payload['raw_deposit_amount'] ?? $payload['deposit_amount'] ?? null);
            $withdraw = $this->call('amountOrNull', $payload['raw_withdraw_amount'] ?? $payload['withdraw_amount'] ?? $payload['withdrawal_amount'] ?? null);
            if ($deposit !== null && abs((float) $deposit) > 0) {
                return (float) abs((float) $deposit);
            }
            if ($withdraw !== null && abs((float) $withdraw) > 0) {
                return (float) abs((float) $withdraw);
            }
        }
        $candidates = ['total_amount', 'amount'];
        if (in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true)) {
            $candidates = [
                'total_amount',
                'actual_billing_amount',
                'billing_amount',
                'purchase_amount_krw',
                'supply_amount',
                'foreign_amount',
                'local_amount',
                'amount',
            ];
        }

        foreach ($candidates as $field) {
            $amount = $this->call('amountOrNull', $payload[$field] ?? null);
            if ($amount !== null) {
                return (float) $amount;
            }
        }

        $supply = $this->call('amountOrNull', $payload['supply_amount'] ?? null);
        $vat = $this->call('amountOrNull', $payload['vat_amount'] ?? null);
        $service = $this->call('amountOrNull', $payload['service_amount'] ?? null);
        if ($supply !== null || $vat !== null || $service !== null) {
            return (float) ($supply ?? 0) + (float) ($vat ?? 0) + (float) ($service ?? 0);
        }

        return 0.0;
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
