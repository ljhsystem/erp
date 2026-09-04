<?php

namespace App\Models\System;

use Core\Database;
use PDO;

final class AttachmentModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function insert(array $row): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO system_attachments '
            . '(id,original_file_name,mime_type,file_size,sha256_hash,storage_object_key,created_by,updated_by) '
            . 'VALUES (:id,:original_file_name,:mime_type,:file_size,:sha256_hash,:storage_object_key,:created_by,:updated_by)'
        );
        $statement->execute([
            ':id' => $row['id'],
            ':original_file_name' => $row['original_file_name'],
            ':mime_type' => $row['mime_type'],
            ':file_size' => $row['file_size'],
            ':sha256_hash' => $row['sha256_hash'],
            ':storage_object_key' => $row['storage_object_key'],
            ':created_by' => $row['created_by'],
            ':updated_by' => $row['updated_by'],
        ]);
    }

    public function find(string $id, bool $forUpdate = false): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM system_attachments WHERE id=:id' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute([':id' => $id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function softDelete(string $id, string $actor): bool
    {
        $statement = $this->db->prepare(
            'UPDATE system_attachments SET deleted_at=NOW(),deleted_by=:actor,updated_by=:actor '
            . 'WHERE id=:id AND deleted_at IS NULL '
            . 'AND NOT EXISTS ('
            . "SELECT 1 FROM institution_daily_income_non_tax_revision_attachments link_row "
            . "WHERE link_row.attachment_id=system_attachments.id AND link_row.link_status_code IN ('DRAFT','LOCKED')"
            . ')'
        );
        $statement->execute([':id' => $id, ':actor' => $actor]);
        return $statement->rowCount() === 1;
    }

    public function restore(string $id, string $actor): bool
    {
        $statement = $this->db->prepare(
            'UPDATE system_attachments SET deleted_at=NULL,deleted_by=NULL,restored_at=NOW(),restored_by=:actor,updated_by=:actor '
            . 'WHERE id=:id AND deleted_at IS NOT NULL'
        );
        $statement->execute([':id' => $id, ':actor' => $actor]);
        return $statement->rowCount() === 1;
    }

    public function linkDraft(array $row): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO institution_daily_income_non_tax_revision_attachments '
            . '(id,non_taxable_revision_id,attachment_id,sort_no,link_status_code,linked_at,linked_by) '
            . "VALUES (:id,:revision_id,:attachment_id,:sort_no,'DRAFT',NOW(),:actor)"
        );
        $statement->execute([
            ':id' => $row['id'], ':revision_id' => $row['non_taxable_revision_id'],
            ':attachment_id' => $row['attachment_id'], ':sort_no' => $row['sort_no'], ':actor' => $row['linked_by'],
        ]);
    }

    public function releaseDraftLink(string $linkId, string $actor): bool
    {
        $statement = $this->db->prepare(
            "UPDATE institution_daily_income_non_tax_revision_attachments "
            . "SET link_status_code='RELEASED',released_at=NOW(),released_by=:actor "
            . "WHERE id=:id AND link_status_code='DRAFT'"
        );
        $statement->execute([':id' => $linkId, ':actor' => $actor]);
        return $statement->rowCount() === 1;
    }

    public function lockRevisionLinks(string $revisionId): int
    {
        $statement = $this->db->prepare(
            "UPDATE institution_daily_income_non_tax_revision_attachments SET link_status_code='LOCKED' "
            . "WHERE non_taxable_revision_id=:revision_id AND link_status_code='DRAFT'"
        );
        $statement->execute([':revision_id' => $revisionId]);
        return $statement->rowCount();
    }
}
