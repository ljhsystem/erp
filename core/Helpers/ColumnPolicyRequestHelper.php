<?php
declare(strict_types=1);

namespace Core\Helpers;

final class ColumnPolicyRequestHelper
{
    public static function displayNameMap(mixed $raw): array
    {
        return self::normalizeMap($raw);
    }

    public static function requirementPolicyMap(mixed $raw): array
    {
        $normalized = [];
        foreach (self::normalizeMap($raw) as $key => $value) {
            $normalized[$key] = self::normalizeRequirementPolicy($value);
        }

        return $normalized;
    }

    public static function displayNameForColumn(array $column, array $displayNameMap, string $fallback = ''): string
    {
        foreach (self::candidateKeys($column) as $key) {
            if (!array_key_exists($key, $displayNameMap)) {
                continue;
            }

            $displayName = trim((string) $displayNameMap[$key]);
            if ($displayName !== '') {
                return $displayName;
            }
        }

        return trim($fallback) !== ''
            ? trim($fallback)
            : trim((string) ($column['label'] ?? $column['key'] ?? ''));
    }

    public static function requirementPolicyForColumn(array $column, array $policyMap, string $fallback = 'none'): string
    {
        foreach (self::candidateKeys($column) as $key) {
            if (!array_key_exists($key, $policyMap)) {
                continue;
            }

            return self::normalizeRequirementPolicy($policyMap[$key]);
        }

        return self::normalizeRequirementPolicy($fallback);
    }

    private static function normalizeMap(mixed $raw): array
    {
        if (is_array($raw)) {
            return self::trimmedMap($raw);
        }

        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return self::trimmedMap($decoded);
        }

        return [];
    }

    private static function trimmedMap(array $map): array
    {
        $normalized = [];
        foreach ($map as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $normalized[$normalizedKey] = trim((string) $value);
        }

        return $normalized;
    }

    private static function candidateKeys(array $column): array
    {
        $keys = [
            $column['key'] ?? null,
            $column['source_key'] ?? null,
            $column['payload_key'] ?? null,
            $column['system_field_name'] ?? null,
            $column['original_column_key'] ?? null,
            $column['alias_of'] ?? null,
        ];

        $normalized = [];
        foreach ($keys as $key) {
            $text = trim((string) $key);
            if ($text === '' || in_array($text, $normalized, true)) {
                continue;
            }
            $normalized[] = $text;
        }

        return $normalized;
    }

    private static function normalizeRequirementPolicy(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'required') {
            return 'required';
        }
        if ($normalized === 'optional') {
            return 'optional';
        }

        return 'none';
    }
}
