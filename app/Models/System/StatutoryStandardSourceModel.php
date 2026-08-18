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
        $retainedIds = [];
        foreach ($sources as $index => $source) {
            $requestedId = trim((string) ($source['id'] ?? ''));
            $previous = $existing[$requestedId] ?? null;
            if ($requestedId !== '' && !$previous) {
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
                'file_path' => $uploaded ? $this->null((string) ($source['file_path'] ?? '')) : ($previous['file_path'] ?? null),
                'file_name' => $uploaded ? $this->null((string) ($source['file_name'] ?? '')) : ($previous['file_name'] ?? null),
                'file_size' => $uploaded ? (($source['file_size'] ?? '') === '' ? null : (int) $source['file_size']) : ($previous['file_size'] ?? null),
                'mime_type' => $uploaded ? $this->null((string) ($source['mime_type'] ?? '')) : ($previous['mime_type'] ?? null),
                'note' => $this->null((string) ($source['note'] ?? '')),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ];
            if ($previous) {
                $retainedIds[] = $requestedId;
                $params = [':id' => $requestedId];
                $sets = [];
                foreach ($data as $field => $value) {
                    $sets[] = $field . '=:' . $field;
                    $params[':' . $field] = $value;
                }
                $statement = $this->db->prepare(
                    'UPDATE system_statutory_standard_sources SET ' . implode(',', $sets) . ' WHERE id=:id'
                );
                $statement->execute($params);
                continue;
            }

            $id = UuidHelper::generate();
            $retainedIds[] = $id;
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

        $removedIds = array_values(array_diff(array_keys($existing), $retainedIds));
        if ($removedIds !== []) {
            $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
            $statement = $this->db->prepare(
                'DELETE FROM system_statutory_standard_sources WHERE standard_id=? AND id IN (' . $placeholders . ')'
            );
            $statement->execute([$standardId, ...$removedIds]);
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
