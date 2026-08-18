<?php

namespace App\Repositories\User;

use PDO;

class PositionDependencyRepository
{
    private const REFERENCES = [
        ['user_employees', 'position_id', '직원 현재 직위·직책'],
        ['institution_job_assignments_position_histories', 'position_id', '직원 직위·직책 기간이력'],
        ['institution_personnel_actions_changes', 'before_position_id', '인사발령 변경 전 직위·직책'],
        ['institution_personnel_actions_changes', 'after_position_id', '인사발령 변경 후 직위·직책'],
        ['institution_employment_rules_scopes', 'position_id', '취업규칙 적용범위'],
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    public function findReferences(string $positionId): array
    {
        $references = [];

        foreach (self::REFERENCES as [$table, $column, $label]) {
            $statement = $this->db->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :position_id"
            );
            $statement->execute([':position_id' => $positionId]);
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
