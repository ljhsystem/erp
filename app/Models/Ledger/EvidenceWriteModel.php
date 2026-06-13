<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceWriteModel
{
    protected PDO $pdo;
    protected string $table = '';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsertById(array $payload): bool
    {
        $id = trim((string) ($payload['id'] ?? ''));
        if ($id === '' || $this->table === '') {
            return false;
        }

        $columns = array_keys($payload);
        $insertColumns = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));
        $insertValues = implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns));
        $updateColumns = array_values(array_filter($columns, static fn (string $c): bool => $c !== 'id'));
        $updateSql = implode(",\n                ", array_map(static fn (string $c): string => "`{$c}` = VALUES(`{$c}`)", $updateColumns));

        $sql = "
            INSERT INTO `{$this->table}` ({$insertColumns})
            VALUES ({$insertValues})
            ON DUPLICATE KEY UPDATE
                {$updateSql}
        ";

        $stmt = $this->pdo->prepare($sql);
        $params = [];
        foreach ($payload as $column => $value) {
            $params[':' . $column] = $value;
        }

        return $stmt->execute($params);
    }
}

