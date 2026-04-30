<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class VoucherLineRefModel
{
    protected string $table = 'ledger_voucher_line_refs';

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getByVoucherLineId(string $voucherLineId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE voucher_line_id = :voucher_line_id
            ORDER BY created_at ASC, ref_type ASC
        ");
        $stmt->execute([':voucher_line_id' => $voucherLineId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getGroupedByVoucherLineIds(array $voucherLineIds): array
    {
        $ids = array_values(array_filter(array_map(static fn($id) => trim((string) $id), $voucherLineIds)));
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':id' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM {$this->table}
            WHERE voucher_line_id IN (" . implode(', ', $placeholders) . ")
            ORDER BY voucher_line_id ASC, created_at ASC, ref_type ASC
        ");
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $lineId = (string) ($row['voucher_line_id'] ?? '');
            if ($lineId === '') {
                continue;
            }
            $grouped[$lineId][] = $row;
        }

        return $grouped;
    }

    public function insert(array $data): bool
    {
        $allowed = [
            'id',
            'voucher_line_id',
            'ref_type',
            'ref_id',
            'is_primary',
            'created_at',
            'created_by',
        ];

        $payload = $this->filterData($data, $allowed);
        if (!isset($payload['id'], $payload['voucher_line_id'], $payload['ref_type'], $payload['ref_id'])) {
            return false;
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn(string $column) => ':' . $column, $columns);

        $stmt = $this->db->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        ));

        return $stmt->execute($this->bindParams($payload));
    }

    public function bulkInsert(string $voucherLineId, array $refs, ?string $actor = null, ?string $createdAt = null): void
    {
        $timestamp = $createdAt ?: date('Y-m-d H:i:s');

        foreach ($refs as $ref) {
            $ok = $this->insert([
                'id' => \Core\Helpers\UuidHelper::generate(),
                'voucher_line_id' => $voucherLineId,
                'ref_type' => $ref['ref_type'],
                'ref_id' => $ref['ref_id'],
                'is_primary' => (int) ($ref['is_primary'] ?? 0),
                'created_at' => $timestamp,
                'created_by' => $actor,
            ]);

            if (!$ok) {
                throw new \RuntimeException('분개라인 보조계정 저장에 실패했습니다.');
            }
        }
    }

    public function deleteByVoucherLineId(string $voucherLineId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE voucher_line_id = :voucher_line_id
        ");
        $stmt->execute([':voucher_line_id' => $voucherLineId]);
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
