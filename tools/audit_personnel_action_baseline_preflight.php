<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$employeeSql = <<<'SQL'
SELECT e.id, e.employee_name, e.employment_status, e.department_id, e.position_id,
       COALESCE(
           (SELECT h.effective_date
              FROM institution_job_assignments_employment_status_histories h
             WHERE h.employee_id = e.id
               AND h.ended_date IS NULL
             ORDER BY h.effective_date DESC
             LIMIT 1),
           e.real_hire_date,
           e.doc_hire_date
       ) AS baseline_start_date,
       CASE
           WHEN e.employment_status = 'RETIRED' THEN COALESCE(
               e.real_retire_date,
               e.doc_retire_date,
               (SELECT MAX(h.ended_date)
                  FROM institution_job_assignments_employment_status_histories h
                 WHERE h.employee_id = e.id)
           )
           ELSE NULL
       END AS baseline_end_date
  FROM user_employees e
 ORDER BY e.sort_no, e.id
SQL;

$employees = $pdo->query($employeeSql)->fetchAll(PDO::FETCH_ASSOC);
$approvalTemplates = $pdo->query(
    "SELECT t.id, t.template_key, t.template_name, t.document_type, t.is_active,
            COUNT(s.id) AS step_count
       FROM user_approval_templates t
       LEFT JOIN user_approval_template_steps s ON s.template_id = t.id AND s.is_active = 1
      WHERE t.document_type = 'PERSONNEL_ACTION'
      GROUP BY t.id
      ORDER BY t.sort_no, t.id"
)->fetchAll(PDO::FETCH_ASSOC);
$approvalSteps = $pdo->query(
    "SELECT s.id, s.template_id, s.sort_no, s.step_name, s.step_type,
            s.role_id, r.role_name, r.is_active AS role_is_active,
            s.approver_id, e.employee_name AS approver_name,
            u.approved AS approver_approved, u.is_active AS approver_is_active,
            s.is_active
       FROM user_approval_template_steps s
       JOIN user_approval_templates t ON t.id = s.template_id
       LEFT JOIN auth_roles r ON r.id = s.role_id
       LEFT JOIN auth_users u ON u.id = s.approver_id
       LEFT JOIN user_employees e ON e.user_id = u.id
      WHERE t.document_type = 'PERSONNEL_ACTION'
      ORDER BY t.sort_no, s.sort_no, s.id"
)->fetchAll(PDO::FETCH_ASSOC);
$counts = [];
foreach ([
    'institution_personnel_actions',
    'institution_personnel_actions_targets',
    'institution_personnel_actions_changes',
] as $table) {
    $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

echo json_encode([
    'counts' => $counts,
    'approval_templates' => $approvalTemplates,
    'approval_steps' => $approvalSteps,
    'approval_integrity' => [
        'active_template_count' => count(array_filter($approvalTemplates, static fn(array $row): bool => (int) $row['is_active'] === 1)),
        'invalid_role_refs' => count(array_filter($approvalSteps, static fn(array $row): bool => $row['role_id'] !== null && ((string) ($row['role_name'] ?? '') === '' || (int) ($row['role_is_active'] ?? 0) !== 1))),
        'invalid_approver_refs' => count(array_filter($approvalSteps, static fn(array $row): bool => $row['approver_id'] !== null && ((string) ($row['approver_name'] ?? '') === '' || (int) ($row['approver_approved'] ?? 0) !== 1 || (int) ($row['approver_is_active'] ?? 0) !== 1))),
    ],
    'employees' => $employees,
    'missing_start_date' => array_values(array_filter(
        $employees,
        static fn(array $row): bool => empty($row['baseline_start_date'])
    )),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
