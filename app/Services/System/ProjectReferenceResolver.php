<?php

namespace App\Services\System;

use PDO;

class ProjectReferenceResolver
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function resolveClientIdByName(string $clientName): ?string
    {
        $clientName = trim($clientName);

        if ($clientName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM system_clients
            WHERE client_name = :client_name
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'client_name' => $clientName,
        ]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (string) $id : null;
    }

    public function resolveEmployeeIdByName(string $employeeName): ?string
    {
        $employeeName = trim($employeeName);

        if ($employeeName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT p.id
            FROM user_employees p
            LEFT JOIN auth_users u ON p.user_id = u.id
            WHERE p.employee_name = :employee_name
              AND COALESCE(u.is_active, 1) = 1
            LIMIT 1
        ");

        $stmt->execute([
            'employee_name' => $employeeName,
        ]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (string) $id : null;
    }
}
