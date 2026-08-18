<?php

namespace App\Models\System;

use Core\Database;
use PDO;

class SequenceModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function nextMaximumValue(string $table, string $column): int
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException("Invalid table name: {$table}");
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }

        $stmt = $this->db->query("SELECT COALESCE(MAX(`{$column}`), 0) + 1 FROM `{$table}`");
        $next = (int) $stmt->fetchColumn();

        return $next > 0 ? $next : 1;
    }
}
