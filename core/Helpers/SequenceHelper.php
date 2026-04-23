<?php

namespace Core\Helpers;

use Core\Database;
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

        $pdo = Database::getInstance()->getConnection();

        try {
            $stmt = $pdo->query("SELECT COALESCE(MAX(`{$column}`), 0) + 1 FROM `{$table}`");
            $next = (int) $stmt->fetchColumn();

            return $next > 0 ? $next : 1;
        } catch (Throwable $e) {
            error_log("[SequenceHelper] next error ({$table}.{$column}): " . $e->getMessage());
            throw $e;
        }
    }
}
