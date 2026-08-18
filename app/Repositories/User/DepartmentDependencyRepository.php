<?php

namespace App\Repositories\User;

use PDO;

class DepartmentDependencyRepository
{
    private const REFERENCES = [
        ['user_employees', 'department_id', '직원 현재부서'],
        ['institution_job_assignments_department_histories', 'department_id', '직원 부서 기간이력'],
        ['institution_personnel_actions_changes', 'before_department_id', '인사발령 변경 전 부서'],
        ['institution_personnel_actions_changes', 'after_department_id', '인사발령 변경 후 부서'],
        ['institution_employment_rules', 'owner_department_id', '취업규칙 소유부서'],
        ['institution_employment_rules_scopes', 'department_id', '취업규칙 적용범위'],
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    public function findReferences(string $departmentId): array
    {
        $references = [];

        foreach (self::REFERENCES as [$table, $column, $label]) {
            $statement = $this->db->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :department_id"
            );
            $statement->execute([':department_id' => $departmentId]);
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
