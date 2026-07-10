<?php

namespace App\Models\Ledger;

use Core\Database;
use Core\Helpers\UuidHelper;
use PDO;

class VoucherLineRefModel
{
    protected string $table = 'ledger_voucher_line_refs';

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getGroupedByVoucherLineIds(array $voucherLineIds): array
    {
        $ids = array_values(array_filter(array_map(
            static fn($id): string => trim((string) $id),
            $voucherLineIds
        )));
        if ($ids === []) {
            return [];
        }

        [$placeholders, $params] = $this->buildInClause($ids, 'line_id_');
        $stmt = $this->db->prepare("
            SELECT
                r.*,
                sc.client_name,
                sp.project_name,
                ue.employee_name,
                ba.account_name AS bank_account_name,
                sca.card_name
            FROM {$this->table} r
            LEFT JOIN system_clients sc
                ON sc.id = r.ref_id
               AND r.ref_target IN ('CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY')
            LEFT JOIN system_projects sp
                ON sp.id = r.ref_id
               AND r.ref_target = 'PROJECT'
            LEFT JOIN user_employees ue
                ON ue.id = r.ref_id
               AND r.ref_target IN ('EMPLOYEE', 'USER')
            LEFT JOIN system_bank_accounts ba
                ON ba.id = r.ref_id
               AND r.ref_target IN ('ACCOUNT', 'BANK', 'BANK_ACCOUNT')
            LEFT JOIN system_cards sca
                ON sca.id = r.ref_id
               AND r.ref_target = 'CARD'
            WHERE r.voucher_line_id IN ({$placeholders})
            ORDER BY r.voucher_line_id ASC, r.created_at ASC, r.ref_target ASC
        ");
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $lineId = trim((string) ($row['voucher_line_id'] ?? ''));
            if ($lineId === '') {
                continue;
            }
            $grouped[$lineId][] = $row;
        }

        return $grouped;
    }

    public function replaceByVoucherLineIds(array $refsByVoucherLineId, ?string $actor = null, ?string $timestamp = null): void
    {
        $lineIds = array_values(array_filter(array_map(
            static fn($lineId): string => trim((string) $lineId),
            array_keys($refsByVoucherLineId)
        )));
        if ($lineIds === []) {
            return;
        }

        $timestamp = $timestamp ?: date('Y-m-d H:i:s');
        $this->deleteByVoucherLineIds($lineIds);

        foreach ($refsByVoucherLineId as $voucherLineId => $refs) {
            $voucherLineId = trim((string) $voucherLineId);
            if ($voucherLineId === '') {
                continue;
            }

            foreach ($refs as $ref) {
                $payload = [
                    'id' => UuidHelper::generate(),
                    'voucher_line_id' => $voucherLineId,
                    'ref_target' => trim((string) ($ref['ref_target'] ?? $ref['ref_type'] ?? '')),
                    'ref_id' => trim((string) ($ref['ref_id'] ?? '')),
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if ($payload['ref_target'] === '' || $payload['ref_id'] === '') {
                    continue;
                }

                if (!$this->insert($payload)) {
                    throw new \RuntimeException('전표라인 보조계정 저장 중 오류가 발생했습니다.');
                }
            }
        }
    }

    public function deleteByVoucherLineIds(array $voucherLineIds): void
    {
        $ids = array_values(array_filter(array_map(
            static fn($id): string => trim((string) $id),
            $voucherLineIds
        )));
        if ($ids === []) {
            return;
        }

        [$placeholders, $params] = $this->buildInClause($ids, 'delete_line_id_');
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE voucher_line_id IN ({$placeholders})
        ");
        $stmt->execute($params);
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'voucher_line_id',
            'ref_target',
            'ref_id',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
        ];

        $payload = $this->filterData($data, $allowed);
        if (!isset($payload['id'], $payload['voucher_line_id'], $payload['ref_target'], $payload['ref_id'])) {
            return false;
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $stmt = $this->db->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        ));

        return $stmt->execute($this->bindParams($payload));
    }

    private function buildInClause(array $values, string $prefix): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $key = ':' . $prefix . $index;
            $placeholders[] = $key;
            $params[$key] = $value;
        }

        return [implode(', ', $placeholders), $params];
    }

    private function filterData(array $data, array $allowed): array
    {
        $payload = [];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $payload[$column] = $data[$column];
            }
        }

        return $payload;
    }

    private function bindParams(array $data): array
    {
        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $params;
    }
}
