<?php

namespace App\Services\Ledger;

use App\Models\Ledger\VoucherLineRefModel;
use PDO;

class VoucherLineRefService
{
    private VoucherLineRefModel $voucherLineRefModel;

    public function __construct(?PDO $pdo = null)
    {
        $this->voucherLineRefModel = new VoucherLineRefModel($pdo);
    }

    public function replaceForVoucherLines(array $lines, ?string $actor = null, ?string $timestamp = null): void
    {
        $refsByVoucherLineId = [];
        foreach ($lines as $line) {
            $lineId = trim((string) ($line['id'] ?? ''));
            if ($lineId === '') {
                continue;
            }
            $refsByVoucherLineId[$lineId] = is_array($line['refs'] ?? null) ? $line['refs'] : [];
        }

        $this->voucherLineRefModel->replaceByVoucherLineIds($refsByVoucherLineId, $actor, $timestamp);
    }

    public function hydrateVoucherLines(array $lines): array
    {
        $lineIds = array_values(array_filter(array_map(
            static fn(array $line): string => trim((string) ($line['id'] ?? '')),
            $lines
        )));
        $groupedRefs = $this->voucherLineRefModel->getGroupedByVoucherLineIds($lineIds);

        foreach ($lines as &$line) {
            $lineId = trim((string) ($line['id'] ?? ''));
            $line['refs'] = $this->formatRefs($groupedRefs[$lineId] ?? []);
        }
        unset($line);

        return $lines;
    }

    public function buildValidationLines(array $lines): array
    {
        return array_map(function (array $line): array {
            $refs = array_map(static function (array $ref): array {
                return [
                    'ref_target' => (string) ($ref['ref_target'] ?? ''),
                    'ref_id' => (string) ($ref['ref_id'] ?? ''),
                ];
            }, is_array($line['refs'] ?? null) ? $line['refs'] : []);

            return [
                'account_id' => (string) ($line['account_id'] ?? ''),
                'account_name' => (string) ($line['account_name'] ?? ''),
                'refs' => $refs,
                'debit' => (string) ($line['debit'] ?? '0'),
                'credit' => (string) ($line['credit'] ?? '0'),
                'line_summary' => (string) ($line['line_summary'] ?? ''),
            ];
        }, $lines);
    }

    private function formatRefs(array $rows): array
    {
        $refs = [];
        foreach (array_values($rows) as $index => $row) {
            $refTarget = strtoupper(trim((string) ($row['ref_target'] ?? '')));
            $refId = trim((string) ($row['ref_id'] ?? ''));
            if ($refTarget === '' || $refId === '') {
                continue;
            }

            $label = $this->resolveRefLabel($row);
            $refs[] = [
                'ref_target' => $refTarget,
                'ref_id' => $refId,
                'line_ref_target' => $refTarget,
                'line_ref_id' => $refId,
                'line_ref_label' => $label,
                'ref_label' => $label,
                'client_name' => (string) ($row['client_name'] ?? ''),
                'project_name' => (string) ($row['project_name'] ?? ''),
                'employee_name' => (string) ($row['employee_name'] ?? ''),
                'bank_account_name' => (string) ($row['bank_account_name'] ?? ''),
                'account_name' => (string) ($row['bank_account_name'] ?? ''),
                'card_name' => (string) ($row['card_name'] ?? ''),
                'is_primary' => $index === 0 ? 1 : 0,
            ];
        }

        return $refs;
    }

    private function resolveRefLabel(array $row): string
    {
        return match (strtoupper(trim((string) ($row['ref_target'] ?? '')))) {
            'CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY' => trim((string) ($row['client_name'] ?? '')),
            'PROJECT' => trim((string) ($row['project_name'] ?? '')),
            'EMPLOYEE', 'USER' => trim((string) ($row['employee_name'] ?? '')),
            'ACCOUNT', 'BANK', 'BANK_ACCOUNT' => trim((string) ($row['bank_account_name'] ?? '')),
            'CARD' => trim((string) ($row['card_name'] ?? '')),
            default => '',
        };
    }
}
