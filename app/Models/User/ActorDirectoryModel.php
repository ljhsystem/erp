<?php

namespace App\Models\User;

use Core\Database;
use PDO;

class ActorDirectoryModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function findEmployeeNamesByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare("
            SELECT user_id, employee_name
            FROM user_employees
            WHERE user_id IN ({$placeholders})
        ");
        $stmt->execute($userIds);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function findEmployeeNameByUserId(string $userId): string
    {
        $stmt = $this->db->prepare('SELECT employee_name FROM user_employees WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);

        return (string) ($stmt->fetchColumn() ?: '');
    }
}
