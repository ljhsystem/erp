<?php

namespace App\Models\System;

use Core\Helpers\UuidHelper;
use PDO;

class StatutoryStandardSourceModel
{
    public function __construct(private PDO $db)
    {
    }

    public function replace(string $standardId, array $sources, string $actor): void
    {
        $existingStatement = $this->db->prepare(
            'SELECT id,file_path,file_name,file_size,mime_type,created_at,created_by'
            . ' FROM system_statutory_standard_sources WHERE standard_id=:id'
            . ' FOR UPDATE'
        );
        $existingStatement->execute([':id' => $standardId]);
        $existing = [];
        foreach ($existingStatement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $existing[(string) $row['id']] = $row;
        }
        if ($existing !== []) {
            throw new \LogicException('확정된 법정기준 Source는 수정하거나 삭제할 수 없습니다. 신규 Correction Revision에 Source를 등록하세요.');
        }
        foreach ($sources as $index => $source) {
            $requestedId = trim((string) ($source['id'] ?? ''));
            if ($requestedId !== '') {
                throw new \InvalidArgumentException('근거자료 연결 정보가 올바르지 않습니다.');
            }
            $uploaded = !empty($source['_uploaded']);
            $data = [
                'standard_id' => $standardId,
                'sort_no' => $index + 1,
                'organization_name' => $this->null((string) ($source['organization_name'] ?? '')),
                'source_name' => trim((string) ($source['source_name'] ?? '')),
                'law_name' => $this->null((string) ($source['law_name'] ?? '')),
                'notice_no' => $this->null((string) ($source['notice_no'] ?? '')),
                'published_at' => $this->null((string) ($source['published_at'] ?? '')),
                'source_url' => $this->null((string) ($source['source_url'] ?? '')),
                'file_path' => $uploaded ? $this->null((string) ($source['file_path'] ?? '')) : null,
                'file_name' => $uploaded ? $this->null((string) ($source['file_name'] ?? '')) : null,
                'file_size' => $uploaded ? (($source['file_size'] ?? '') === '' ? null : (int) $source['file_size']) : null,
                'mime_type' => $uploaded ? $this->null((string) ($source['mime_type'] ?? '')) : null,
                'note' => $this->null((string) ($source['note'] ?? '')),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ];
            $id = UuidHelper::generate();
            $insert = ['id' => $id] + $data + [
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $actor,
            ];
            $fields = array_keys($insert);
            $statement = $this->db->prepare(
                'INSERT INTO system_statutory_standard_sources(' . implode(',', $fields) . ')'
                . ' VALUES(:' . implode(',:', $fields) . ')'
            );
            $statement->execute(array_combine(
                array_map(static fn(string $field): string => ':' . $field, $fields),
                array_values($insert)
            ));
        }

    }

    public function find(string $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM system_statutory_standard_sources WHERE id=:id');
        $statement->execute([':id' => $id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function null(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
