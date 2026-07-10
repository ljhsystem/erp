<?php

namespace App\Models\Ledger;

use Core\Helpers\UuidHelper;
use PDO;

class EvidenceMetadataColumnModel
{
    private const TABLE = 'ledger_evidence_metadata_columns';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getByMetadataId(string $metadataId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE
            . ' WHERE metadata_id = :metadata_id ORDER BY sort_no ASC, semantic_key ASC'
        );
        $stmt->execute([':metadata_id' => $metadataId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function replace(string $metadataId, array $mappings, string $actor, string $timestamp): void
    {
        $this->deleteByMetadataId($metadataId);
        $insert = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (
                id, sort_no, metadata_id, semantic_key, physical_column, adjustment_direction,
                is_required, remark, created_at, created_by, updated_at, updated_by
            ) VALUES (
                :id, :sort_no, :metadata_id, :semantic_key, :physical_column, :adjustment_direction,
                :is_required, :remark, :created_at, :created_by, :updated_at, :updated_by
            )
        ");
        foreach (array_values($mappings) as $index => $mapping) {
            $insert->execute([
                ':id' => UuidHelper::generate(),
                ':sort_no' => $index + 1,
                ':metadata_id' => $metadataId,
                ':semantic_key' => (string) ($mapping['semantic_key'] ?? ''),
                ':physical_column' => (string) ($mapping['physical_column'] ?? ''),
                ':adjustment_direction' => $mapping['adjustment_direction'] ?? null,
                ':is_required' => (string) ($mapping['is_required'] ?? 'N'),
                ':remark' => $mapping['remark'] ?? null,
                ':created_at' => $timestamp,
                ':created_by' => $actor,
                ':updated_at' => $timestamp,
                ':updated_by' => $actor,
            ]);
        }
    }

    public function deleteByMetadataId(string $metadataId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE metadata_id = :metadata_id');
        $stmt->execute([':metadata_id' => $metadataId]);
    }
}
