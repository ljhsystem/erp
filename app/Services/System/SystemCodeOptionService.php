<?php

declare(strict_types=1);

namespace App\Services\System;

use PDO;

final class SystemCodeOptionService
{
    private array $optionsCache = [];

    public function __construct(private PDO $db) {}

    public function options(string $codeGroup, array $allowedCodes, bool $includeInactive = false): array
    {
        $codeGroup = strtoupper(trim($codeGroup));
        $allowedCodes = array_values(array_unique(array_map(
            static fn(mixed $code): string => strtoupper(trim((string)$code)), $allowedCodes
        )));
        if ($codeGroup === '' || $allowedCodes === []) {
            throw new \InvalidArgumentException('코드그룹과 허용 코드가 필요합니다.');
        }
        $cacheKey = $codeGroup . '|' . implode(',', $allowedCodes) . '|' . ($includeInactive ? '1' : '0');
        if (array_key_exists($cacheKey, $this->optionsCache)) {
            return $this->optionsCache[$cacheKey];
        }
        $placeholders = implode(',', array_fill(0, count($allowedCodes), '?'));
        $statement = $this->db->prepare(
            "SELECT code value,code_name label,is_active FROM system_codes WHERE code_group=?"
            . " AND code IN ({$placeholders})" . ($includeInactive ? '' : ' AND is_active=1')
            . ' ORDER BY sort_no,code_name,id'
        );
        $statement->execute([$codeGroup, ...$allowedCodes]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $missing = array_values(array_diff($allowedCodes, array_map('strval', array_column($rows, 'value'))));
        if ($missing !== []) {
            throw new \RuntimeException($codeGroup . ' 코드가 등록되지 않았습니다: ' . implode(', ', $missing));
        }
        return $this->optionsCache[$cacheKey] = array_map(static fn(array $row): array => [
            'value'=>(string)$row['value'], 'label'=>(string)$row['label'],
            'is_active'=>(int)$row['is_active'] === 1, 'disabled'=>(int)$row['is_active'] !== 1,
        ], $rows);
    }

    public function isActiveAllowed(string $codeGroup, array $allowedCodes, string $value): bool
    {
        $value = strtoupper(trim($value));
        if ($value === '' || !in_array($value, array_map('strval', $allowedCodes), true)) return false;
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM system_codes WHERE code_group=:code_group AND code=:code AND is_active=1'
        );
        $statement->execute([':code_group'=>strtoupper(trim($codeGroup)), ':code'=>$value]);
        return (int)$statement->fetchColumn() === 1;
    }
}
