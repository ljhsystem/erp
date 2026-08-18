<?php

namespace Core\Helpers;

use App\Models\System\SequenceModel;
use Throwable;

class SequenceHelper
{
    public static function next(string $table, string $column = 'code'): int
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException("Invalid table name: {$table}");
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }

        try {
            return (new SequenceModel())->nextMaximumValue($table, $column);
        } catch (Throwable $e) {
            error_log("[SequenceHelper] next error ({$table}.{$column}): " . $e->getMessage());
            throw $e;
        }
    }
}
