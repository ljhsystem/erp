<?php

namespace Core\Helpers;

class ExcelTemplateFilenameHelper
{
    public static function normalize(string $filename, string $fallbackBase = 'template'): string
    {
        $resolved = trim($filename);
        if ($resolved === '') {
            return self::build($fallbackBase);
        }

        $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        $basename = pathinfo($resolved, PATHINFO_FILENAME);
        if (!preg_match('/(?:^|[_-])(?:upload[_-])?template$/i', $basename)) {
            $safeBase = strtolower($basename);
            $safeBase = preg_replace('/[^a-z0-9]+/', '_', $safeBase) ?? '';
            $safeBase = trim($safeBase, '_') ?: strtolower(trim($fallbackBase));

            return $safeBase . '.' . ($extension !== '' ? $extension : 'xlsx');
        }

        $normalizedBase = self::normalizeBase($basename, $fallbackBase);

        return $normalizedBase . '.' . ($extension !== '' ? $extension : 'xlsx');
    }

    public static function build(string $base, string $extension = 'xlsx'): string
    {
        $normalizedBase = self::normalizeBase($base, 'template');
        $normalizedExtension = strtolower(trim($extension)) ?: 'xlsx';

        return $normalizedBase . '.' . $normalizedExtension;
    }

    private static function normalizeBase(string $basename, string $fallbackBase): string
    {
        $normalized = strtolower(trim($basename));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');
        $normalized = preg_replace('/(?:_upload)?_template$/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            $normalized = strtolower(trim($fallbackBase));
            $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? 'template';
            $normalized = trim($normalized, '_');
        }

        return ($normalized !== '' ? $normalized : 'template') . '_template';
    }
}
