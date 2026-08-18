<?php

namespace App\Repositories\Auth;

use PDO;

class RoleDependencyRepository
{
    private const REFERENCES = [
        ['auth_users', 'role_id', '사용자 현재 역할'],
        ['user_approval_template_steps', 'role_id', '결재템플릿 단계'],
        ['user_approval_request_steps', 'role_id', '결재요청 단계 이력'],
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    public function findReferences(string $roleId): array
    {
        $references = [];

        foreach (self::REFERENCES as [$table, $column, $label]) {
            $statement = $this->db->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :role_id"
            );
            $statement->execute([':role_id' => $roleId]);
            $count = (int) $statement->fetchColumn();

            if ($count > 0) {
                $references[] = [
                    'source' => $table . '.' . $column,
                    'label' => $label,
                    'count' => $count,
                ];
            }
        }

        return $references;
    }
}
