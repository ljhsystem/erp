<?php

namespace App\Services\Ledger;

class EvidenceSortHelperService
{
    public function evidencePayloadSortNo(array $row, string $key): int
    {
        $column = $key === 'sort_no' ? 'sort_no' : '';
        if ($column !== '' && array_key_exists($column, $row) && is_numeric($row[$column])) {
            return max(0, (int) $row[$column]);
        }
        $payload = is_array($row['mapped_payload'] ?? null) ? $row['mapped_payload'] : [];
        $value = $payload[$key] ?? 0;
        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    public function ensureEvidenceSortColumns(): void
    {
        return;
    }
}
