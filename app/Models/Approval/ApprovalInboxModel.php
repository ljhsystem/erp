<?php

namespace App\Models\Approval;

use Core\Helpers\ActorHelper;
use PDO;

class ApprovalInboxModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function page(string $userId, string $box, array $query): array
    {
        [$scope, $scopeParams] = $this->scope($box, $userId);
        $keyword = trim((string) ($query['search']['value'] ?? $query['keyword'] ?? ''));
        $where = ["r.is_active = 1", $scope];
        $params = [
            ':actionable_user_id' => $userId,
            ':participant_user_id' => $userId,
            ':eligibility_user_id' => $userId,
            ...$scopeParams,
        ];
        if ($keyword !== '') {
            $where[] = '(p.title LIKE :keyword_title OR contract.contract_no LIKE :keyword_contract
                OR personnel_action.action_no LIKE :keyword_action_no OR personnel_action.action_name LIKE :keyword_action_name
                OR contract_employee.employee_name LIKE :keyword_employee OR r.document_type LIKE :keyword_type
                OR personnel_action_targets.employee_names LIKE :keyword_action_employee
                OR requester.employee_name LIKE :keyword_requester
                OR CAST(COALESCE(p.sort_no, contract.sort_no, r.sort_no) AS CHAR) LIKE :keyword_number)';
            foreach ([
                ':keyword_title', ':keyword_contract', ':keyword_action_no', ':keyword_action_name',
                ':keyword_employee', ':keyword_action_employee',
                ':keyword_type', ':keyword_requester', ':keyword_number',
            ] as $key) {
                $params[$key] = '%' . $keyword . '%';
            }
        }
        $countFrom = $this->countFrom($keyword !== '');
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $countParams = $params;
        $count = $this->pdo->prepare('SELECT COUNT(*)' . $countFrom . $whereSql);
        $count->execute($countParams);
        $filtered = (int) $count->fetchColumn();
        $total = $filtered;
        if ($keyword !== '') {
            $totalWhere = ["r.is_active = 1", $scope];
            $totalParams = [
                ':actionable_user_id' => $userId,
                ':participant_user_id' => $userId,
                ':eligibility_user_id' => $userId,
                ...$scopeParams,
            ];
            $totalCount = $this->pdo->prepare(
                'SELECT COUNT(*)' . $this->countFrom(false) . ' WHERE ' . implode(' AND ', $totalWhere)
            );
            $totalCount->execute($totalParams);
            $total = (int) $totalCount->fetchColumn();
        }
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(500, (int) ($query['length'] ?? 100)));
        $orderBy = $this->orderBy($query);
        $from = $this->listFrom();
        $sql = "SELECT r.id request_id, r.document_type, r.document_id, r.status approval_status,
                       r.current_step, r.requested_at, r.completed_at,
                       COALESCE(r.completed_at, r.withdrawn_at, r.cancelled_at) final_processed_at,
                       COALESCE(p.sort_no, contract.sort_no, personnel_action.action_no, leave_request.request_no, r.sort_no) document_no,
                       COALESCE(p.application_date, contract.contract_start_date, personnel_action.action_date, leave_dates.leave_from, DATE(r.requested_at)) application_date,
                       COALESCE(p.title, CONCAT('근로계약 ', contract.contract_no), personnel_action.action_name, CONCAT('휴가신청 ',leave_request.request_no), r.document_type) title,
                       requester.employee_name requester_name,
                       COALESCE(applicant.employee_name, contract_employee.employee_name, personnel_action_targets.employee_names, leave_employee.employee_name) applicant_name,
                       department.dept_name department_name,
                       current_step.step_name current_step_name, current_approver.employee_name current_approver_name,
                       COALESCE(p.supply_amount, 0) supply_amount_total,
                       COALESCE(p.vat_amount, 0) vat_amount_total,
                       COALESCE(p.total_amount, contract_amount.total_amount) total_amount,
                       COALESCE(step_counts.approved_count, 0) approved_step_count,
                       COALESCE(step_counts.step_count, 0) step_count,
                       CASE WHEN actionable.id IS NULL THEN 0 ELSE 1 END can_act
                {$from}{$whereSql}
                ORDER BY {$orderBy}
                LIMIT {$start}, {$length}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total, 'filtered' => $filtered];
    }

    public function actionableNotifications(string $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT current_step.id step_id, r.id request_id, r.document_type,
                   r.document_id, COALESCE(p.sort_no, contract.sort_no, personnel_action.action_no, leave_request.request_no) document_no,
                   COALESCE(p.title, CONCAT('근로계약 ', contract.contract_no), personnel_action.action_name, CONCAT('휴가신청 ',leave_request.request_no), r.document_type) title,
                   requester.employee_name requester_name,
                   current_step.step_name current_step_name,
                   COALESCE(current_step.updated_at, current_step.created_at, r.requested_at) arrived_at,
                    COALESCE(p.total_amount, contract_amount.total_amount) total_amount
              FROM user_approval_requests r
              INNER JOIN user_approval_request_steps current_step
                ON current_step.request_id = r.id
               AND current_step.sort_no = r.current_step
               AND (
                    current_step.approver_id = :user_id
                    OR (
                        current_step.approver_id IS NULL
                        AND EXISTS (
                            SELECT 1
                              FROM auth_users eligible_user
                              INNER JOIN auth_roles eligible_role ON eligible_role.id = eligible_user.role_id
                              INNER JOIN user_employees eligible_employee ON eligible_employee.user_id = eligible_user.id
                             WHERE eligible_user.id = :role_user_id
                               AND eligible_user.role_id = current_step.role_id
                               AND eligible_user.approved = 1
                               AND eligible_user.is_active = 1
                               AND eligible_role.is_active = 1
                               AND (eligible_employee.doc_retire_date IS NULL OR eligible_employee.doc_retire_date > CURRENT_DATE())
                               AND (eligible_employee.real_retire_date IS NULL OR eligible_employee.real_retire_date > CURRENT_DATE())
                        )
                    )
               )
               AND current_step.status = 'pending'
               AND current_step.step_type IN ('APPROVAL', 'FINAL_APPROVAL')
               AND current_step.is_active = 1
              LEFT JOIN approval_personal_expenses p
                ON r.document_type = 'PERSONAL_EXPENSE'
               AND p.id = r.document_id
               AND p.current_approval_request_id = r.id
               AND p.document_status IN ('PENDING', 'IN_PROGRESS')
               AND p.deleted_at IS NULL
              LEFT JOIN institution_employment_contracts contract
                ON r.document_type = 'EMPLOYMENT_CONTRACT'
               AND contract.id = r.document_id
               AND contract.current_approval_request_id = r.id
               AND contract.contract_status = 'APPROVAL_PENDING'
               AND contract.deleted_at IS NULL
              LEFT JOIN institution_personnel_actions personnel_action
                ON r.document_type = 'PERSONNEL_ACTION'
               AND personnel_action.id = r.document_id
               AND personnel_action.current_approval_request_id = r.id
               AND personnel_action.business_status = 'APPROVAL_PENDING'
               AND personnel_action.deleted_at IS NULL
              LEFT JOIN institution_leave_requests leave_request
                ON r.document_type='LEAVE_REQUEST' AND leave_request.id=r.document_id
               AND leave_request.current_approval_request_id=r.id
               AND leave_request.business_status_code IN ('APPROVAL_PENDING','CANCEL_PENDING')
              LEFT JOIN (
                    SELECT contract_id, SUM(amount) total_amount
                    FROM institution_employment_contracts_components
                    WHERE deleted_at IS NULL
                    GROUP BY contract_id
              ) contract_amount ON contract_amount.contract_id = contract.id
              INNER JOIN user_employees requester
                ON requester.user_id = r.requester_id
             WHERE r.status IN ('pending', 'in_progress')
               AND r.is_active = 1
               AND (
                    (r.document_type = 'PERSONAL_EXPENSE' AND p.id IS NOT NULL)
                    OR (r.document_type = 'EMPLOYMENT_CONTRACT' AND contract.id IS NOT NULL)
                    OR (r.document_type = 'PERSONNEL_ACTION' AND personnel_action.id IS NOT NULL)
                    OR (r.document_type = 'LEAVE_REQUEST' AND leave_request.id IS NOT NULL)
               )
               AND NOT EXISTS (
                   SELECT 1
                     FROM user_approval_request_steps previous_step
                    WHERE previous_step.request_id = r.id
                      AND previous_step.sort_no < r.current_step
                      AND previous_step.is_active = 1
                      AND previous_step.status <> 'approved'
               )
             ORDER BY arrived_at DESC, r.sort_no DESC
        ");
        $stmt->execute([':user_id' => $userId, ':role_user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function requestDetail(string $requestId, string $userId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT r.*, COALESCE(p.sort_no, contract.sort_no, personnel_action.action_no, leave_request.request_no, r.sort_no) document_no,
                       COALESCE(p.employee_id, contract.employee_id, leave_request.employee_id) employee_id,
                       COALESCE(p.application_date, contract.contract_start_date, personnel_action.action_date, leave_dates.leave_from) application_date,
                       COALESCE(p.title, CONCAT('근로계약 ', contract.contract_no), personnel_action.action_name, CONCAT('휴가신청 ',leave_request.request_no)) title,
                       COALESCE(p.description, contract.note, personnel_action.action_reason, leave_request.reason) description,
                       p.memo memo,
                       COALESCE(p.document_status, contract.contract_status, personnel_action.business_status, leave_request.business_status_code) document_status,
                       COALESCE(p.total_amount, contract_amount.total_amount) total_amount,
                       requester.employee_name requester_name,
                       COALESCE(applicant.employee_name, contract_employee.employee_name, personnel_action_targets.employee_names, leave_employee.employee_name) applicant_name,
                       department.dept_name department_name,
                       current_step.id current_step_id, current_step.step_name current_step_name,
                       current_step.approver_id current_approver_id,
                       current_approver.employee_name current_approver_name
                FROM user_approval_requests r
                LEFT JOIN approval_personal_expenses p
                    ON r.document_type = 'PERSONAL_EXPENSE' AND p.id = r.document_id AND p.deleted_at IS NULL
                LEFT JOIN institution_employment_contracts contract
                    ON r.document_type = 'EMPLOYMENT_CONTRACT'
                   AND contract.id = r.document_id
                   AND contract.deleted_at IS NULL
                LEFT JOIN institution_personnel_actions personnel_action
                    ON r.document_type = 'PERSONNEL_ACTION'
                   AND personnel_action.id = r.document_id
                   AND personnel_action.deleted_at IS NULL
                LEFT JOIN institution_leave_requests leave_request
                    ON r.document_type='LEAVE_REQUEST' AND leave_request.id=r.document_id
                LEFT JOIN user_employees leave_employee ON leave_employee.id=leave_request.employee_id
                LEFT JOIN (SELECT leave_request_id,MIN(leave_date) leave_from FROM institution_leave_request_items GROUP BY leave_request_id) leave_dates ON leave_dates.leave_request_id=leave_request.id
                LEFT JOIN (
                    SELECT target.personnel_action_id,
                           GROUP_CONCAT(employee.employee_name ORDER BY target.sort_no SEPARATOR ', ') employee_names
                    FROM institution_personnel_actions_targets target
                    INNER JOIN user_employees employee ON employee.id = target.employee_id
                    GROUP BY target.personnel_action_id
                ) personnel_action_targets ON personnel_action_targets.personnel_action_id = personnel_action.id
                LEFT JOIN (
                    SELECT contract_id, SUM(amount) total_amount
                    FROM institution_employment_contracts_components
                    WHERE deleted_at IS NULL
                    GROUP BY contract_id
                ) contract_amount ON contract_amount.contract_id = contract.id
                INNER JOIN user_employees requester ON requester.user_id = r.requester_id
                LEFT JOIN user_departments department ON department.id = requester.department_id
                LEFT JOIN user_approval_request_steps current_step
                    ON current_step.request_id = r.id AND current_step.sort_no = r.current_step AND current_step.is_active = 1
                LEFT JOIN user_employees applicant ON applicant.id = p.employee_id
                LEFT JOIN user_employees contract_employee ON contract_employee.id = contract.employee_id
                LEFT JOIN user_employees current_approver ON current_approver.user_id = current_step.approver_id
                WHERE r.id = :request_id AND r.is_active = 1
                   AND (r.requester_id = :user_id OR EXISTS (
                       SELECT 1 FROM user_approval_request_steps access_step
                       LEFT JOIN auth_users eligible_user ON eligible_user.id = :access_role_user_id
                       LEFT JOIN auth_roles eligible_role ON eligible_role.id = eligible_user.role_id
                       LEFT JOIN user_employees eligible_employee ON eligible_employee.user_id = eligible_user.id
                       WHERE access_step.request_id = r.id
                         AND access_step.is_active = 1
                         AND (
                              access_step.approver_id = :access_user_id
                              OR (
                                  access_step.approver_id IS NULL
                                  AND access_step.role_id = eligible_user.role_id
                                  AND eligible_user.approved = 1
                                  AND eligible_user.is_active = 1
                                  AND eligible_role.is_active = 1
                                  AND (eligible_employee.doc_retire_date IS NULL OR eligible_employee.doc_retire_date > CURRENT_DATE())
                                  AND (eligible_employee.real_retire_date IS NULL OR eligible_employee.real_retire_date > CURRENT_DATE())
                              )
                         )
                   ))
                LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':request_id' => $requestId,
            ':user_id' => $userId,
            ':access_user_id' => $userId,
            ':access_role_user_id' => $userId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function items(string $documentId): array
    {
        $stmt = $this->pdo->prepare("SELECT item.*, project.project_name,
                COALESCE(client.client_name, client.company_name) client_name,
                expense_category.code_name expense_category_name,
                payment_method.code_name payment_method_name,
                receipt_type.code_name receipt_type_name
            FROM approval_personal_expense_items item
            LEFT JOIN system_projects project ON project.id = item.project_id AND project.deleted_at IS NULL
            LEFT JOIN system_clients client ON client.id = item.client_id AND client.deleted_at IS NULL
            LEFT JOIN system_codes expense_category
                ON expense_category.code_group = 'PERSONAL_EXPENSE_CATEGORY'
               AND expense_category.code = item.expense_category
            LEFT JOIN system_codes payment_method
                ON payment_method.code_group = 'PERSONAL_EXPENSE_PAYMENT_METHOD'
               AND payment_method.code = item.payment_method
            LEFT JOIN system_codes receipt_type
                ON receipt_type.code_group = 'PERSONAL_EXPENSE_RECEIPT_TYPE'
               AND receipt_type.code = item.receipt_type
            WHERE item.personal_expense_id = :document_id AND item.deleted_at IS NULL
            ORDER BY item.sort_no, item.created_at");
        $stmt->execute([':document_id' => $documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function requestByStep(string $stepId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.* FROM user_approval_request_steps s
             JOIN user_approval_requests r ON r.id = s.request_id
             WHERE s.id = :step_id AND s.is_active = 1 AND r.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':step_id' => $stepId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function totals(string $documentId): array
    {
        $stmt = $this->pdo->prepare("SELECT item_count,
                supply_amount supply_amount_total,
                vat_amount vat_amount_total,
                total_amount
            FROM approval_personal_expenses
            WHERE id = :document_id AND deleted_at IS NULL");
        $stmt->execute([':document_id' => $documentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function steps(string $requestId): array
    {
        $stmt = $this->pdo->prepare("SELECT step.*, approver.employee_name approver_name,
                       role.role_name approver_role_name
                FROM user_approval_request_steps step
                LEFT JOIN user_employees approver ON approver.user_id = step.approver_id
                LEFT JOIN auth_roles role ON role.id = step.role_id
                WHERE step.request_id = :request_id AND step.is_active = 1
                ORDER BY step.sort_no");
        $stmt->execute([':request_id' => $requestId]);
        return ActorHelper::enrichActorNames(
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ['acted_by_name' => 'acted_by']
        );
    }

    public function stepsForRequests(array $requestIds): array
    {
        $requestIds = array_values(array_unique(array_filter(array_map('strval', $requestIds))));
        if ($requestIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $stmt = $this->pdo->prepare("SELECT step.*, approver.employee_name approver_name,
                       role.role_name approver_role_name
                FROM user_approval_request_steps step
                LEFT JOIN user_employees approver ON approver.user_id = step.approver_id
                LEFT JOIN auth_roles role ON role.id = step.role_id
                WHERE step.request_id IN ({$placeholders}) AND step.is_active = 1
                ORDER BY step.request_id, step.sort_no");
        $stmt->execute($requestIds);
        $rows = ActorHelper::enrichActorNames(
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ['acted_by_name' => 'acted_by']
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['request_id']][] = $row;
        }
        return $grouped;
    }

    public function history(string $documentType, string $documentId): array
    {
        $stmt = $this->pdo->prepare("SELECT r.*, requester.employee_name requester_name
            FROM user_approval_requests r
            LEFT JOIN user_employees requester ON requester.user_id = r.requester_id
            WHERE r.document_type = :document_type AND r.document_id = :document_id AND r.is_active = 1
            ORDER BY r.requested_at DESC, r.sort_no DESC");
        $stmt->execute([':document_type' => $documentType, ':document_id' => $documentId]);
        return ActorHelper::enrichActorNames(
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ['withdrawn_by_name' => 'withdrawn_by', 'cancelled_by_name' => 'cancelled_by']
        );
    }

    private function scope(string $box, string $userId): array
    {
        return match ($box) {
            'actionable' => [
                "r.status IN ('pending','in_progress') AND actionable.id IS NOT NULL",
                [':actionable_user_id' => $userId],
            ],
            'progress' => [
                "r.status IN ('pending','in_progress') AND (r.requester_id = :scope_user_id OR participant.request_id IS NOT NULL OR role_participant.request_id IS NOT NULL)",
                [':scope_user_id' => $userId, ':participant_user_id' => $userId],
            ],
            'completed' => [
                "r.status = 'approved' AND (r.requester_id = :scope_user_id OR participant.request_id IS NOT NULL OR role_participant.request_id IS NOT NULL)",
                [':scope_user_id' => $userId, ':participant_user_id' => $userId],
            ],
            'rejected' => [
                "r.status = 'rejected' AND (r.requester_id = :scope_user_id OR participant.request_id IS NOT NULL OR role_participant.request_id IS NOT NULL)",
                [':scope_user_id' => $userId, ':participant_user_id' => $userId],
            ],
            'submitted' => ["r.requester_id = :scope_user_id", [':scope_user_id' => $userId]],
            default => throw new \InvalidArgumentException('결재함 구분이 올바르지 않습니다.'),
        };
    }

    private function orderBy(array $query): string
    {
        $order = is_array($query['order'][0] ?? null) ? $query['order'][0] : [];
        $columnIndex = isset($order['column']) ? (int) $order['column'] : -1;
        $column = $columnIndex >= 0 && is_array($query['columns'][$columnIndex] ?? null)
            ? trim((string) ($query['columns'][$columnIndex]['data'] ?? ''))
            : '';
        $columns = [
            'document_type' => 'r.document_type',
            'document_no' => 'COALESCE(p.sort_no, contract.sort_no, personnel_action.action_no, leave_request.request_no, r.sort_no)',
            'applicant_name' => 'COALESCE(applicant.employee_name, contract_employee.employee_name, personnel_action_targets.employee_names, leave_employee.employee_name)',
            'requester_name' => 'requester.employee_name',
            'application_date' => 'COALESCE(p.application_date, contract.contract_start_date, personnel_action.action_date, leave_dates.leave_from, DATE(r.requested_at))',
            'title' => "COALESCE(p.title, CONCAT('근로계약 ', contract.contract_no), personnel_action.action_name, CONCAT('휴가신청 ',leave_request.request_no), r.document_type)",
            'total_amount' => 'COALESCE(p.total_amount, contract_amount.total_amount)',
            'current_step_name' => 'current_step.step_name',
            'requested_at' => 'r.requested_at',
            'approval_status' => 'r.status',
        ];
        $expression = $columns[$column] ?? 'r.requested_at';
        $direction = strtolower((string) ($order['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        return $expression . ' ' . $direction . ', r.sort_no DESC';
    }

    private function listFrom(): string
    {
        return " FROM user_approval_requests r
            LEFT JOIN approval_personal_expenses p
                ON r.document_type = 'PERSONAL_EXPENSE' AND p.id = r.document_id AND p.deleted_at IS NULL
            LEFT JOIN institution_employment_contracts contract
                ON r.document_type = 'EMPLOYMENT_CONTRACT'
               AND contract.id = r.document_id
               AND contract.deleted_at IS NULL
            LEFT JOIN institution_personnel_actions personnel_action
                ON r.document_type = 'PERSONNEL_ACTION'
               AND personnel_action.id = r.document_id
               AND personnel_action.deleted_at IS NULL
            LEFT JOIN institution_leave_requests leave_request
                ON r.document_type='LEAVE_REQUEST' AND leave_request.id=r.document_id
            LEFT JOIN user_employees leave_employee ON leave_employee.id=leave_request.employee_id
            LEFT JOIN (SELECT leave_request_id,MIN(leave_date) leave_from FROM institution_leave_request_items GROUP BY leave_request_id) leave_dates ON leave_dates.leave_request_id=leave_request.id
            LEFT JOIN (
                SELECT target.personnel_action_id,
                       GROUP_CONCAT(employee.employee_name ORDER BY target.sort_no SEPARATOR ', ') employee_names
                FROM institution_personnel_actions_targets target
                INNER JOIN user_employees employee ON employee.id = target.employee_id
                GROUP BY target.personnel_action_id
            ) personnel_action_targets ON personnel_action_targets.personnel_action_id = personnel_action.id
            LEFT JOIN (
                SELECT contract_id, SUM(amount) total_amount
                FROM institution_employment_contracts_components
                WHERE deleted_at IS NULL
                GROUP BY contract_id
            ) contract_amount ON contract_amount.contract_id = contract.id
            INNER JOIN user_employees requester ON requester.user_id = r.requester_id
            LEFT JOIN user_employees applicant ON applicant.id = p.employee_id
            LEFT JOIN user_employees contract_employee ON contract_employee.id = contract.employee_id
            LEFT JOIN user_departments department ON department.id = requester.department_id
            LEFT JOIN user_approval_request_steps current_step
                ON current_step.request_id = r.id AND current_step.sort_no = r.current_step AND current_step.is_active = 1
            LEFT JOIN user_employees current_approver ON current_approver.user_id = current_step.approver_id
            LEFT JOIN auth_users eligible_user ON eligible_user.id = :eligibility_user_id
            LEFT JOIN auth_roles eligible_role ON eligible_role.id = eligible_user.role_id
            LEFT JOIN user_employees eligible_employee ON eligible_employee.user_id = eligible_user.id
            LEFT JOIN user_approval_request_steps actionable
                ON actionable.request_id = r.id AND actionable.sort_no = r.current_step
               AND (
                    actionable.approver_id = :actionable_user_id
                    OR (
                        actionable.approver_id IS NULL
                        AND actionable.role_id = eligible_user.role_id
                        AND eligible_user.approved = 1
                        AND eligible_user.is_active = 1
                        AND eligible_role.is_active = 1
                        AND (eligible_employee.doc_retire_date IS NULL OR eligible_employee.doc_retire_date > CURRENT_DATE())
                        AND (eligible_employee.real_retire_date IS NULL OR eligible_employee.real_retire_date > CURRENT_DATE())
                    )
               )
               AND actionable.status = 'pending'
               AND actionable.step_type IN ('APPROVAL', 'FINAL_APPROVAL')
               AND actionable.is_active = 1
            LEFT JOIN (
                SELECT DISTINCT request_id, approver_id
                FROM user_approval_request_steps
                WHERE is_active = 1
            ) participant ON participant.request_id = r.id AND participant.approver_id = :participant_user_id
            LEFT JOIN (
                SELECT DISTINCT request_id, role_id
                FROM user_approval_request_steps
                WHERE is_active = 1 AND approver_id IS NULL AND role_id IS NOT NULL
            ) role_participant
                ON role_participant.request_id = r.id
               AND role_participant.role_id = eligible_user.role_id
               AND eligible_user.approved = 1
               AND eligible_user.is_active = 1
               AND eligible_role.is_active = 1
               AND (eligible_employee.doc_retire_date IS NULL OR eligible_employee.doc_retire_date > CURRENT_DATE())
               AND (eligible_employee.real_retire_date IS NULL OR eligible_employee.real_retire_date > CURRENT_DATE())
            LEFT JOIN (
                SELECT request_id, COUNT(*) step_count,
                       SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) approved_count
                FROM user_approval_request_steps
                WHERE is_active = 1
                GROUP BY request_id
            ) step_counts ON step_counts.request_id = r.id";
    }

    private function countFrom(bool $withKeyword): string
    {
        $keywordJoins = $withKeyword ? "
            LEFT JOIN approval_personal_expenses p
                ON r.document_type = 'PERSONAL_EXPENSE' AND p.id = r.document_id AND p.deleted_at IS NULL
            LEFT JOIN institution_employment_contracts contract
                ON r.document_type = 'EMPLOYMENT_CONTRACT' AND contract.id = r.document_id AND contract.deleted_at IS NULL
            LEFT JOIN institution_personnel_actions personnel_action
                ON r.document_type = 'PERSONNEL_ACTION' AND personnel_action.id = r.document_id AND personnel_action.deleted_at IS NULL
            LEFT JOIN user_employees contract_employee ON contract_employee.id = contract.employee_id
            LEFT JOIN user_employees requester ON requester.user_id = r.requester_id
            LEFT JOIN (
                SELECT target.personnel_action_id,
                       GROUP_CONCAT(employee.employee_name ORDER BY target.sort_no SEPARATOR ', ') employee_names
                FROM institution_personnel_actions_targets target
                INNER JOIN user_employees employee ON employee.id = target.employee_id
                GROUP BY target.personnel_action_id
            ) personnel_action_targets ON personnel_action_targets.personnel_action_id = personnel_action.id" : '';
        return " FROM user_approval_requests r{$keywordJoins}
            LEFT JOIN auth_users eligible_user ON eligible_user.id = :eligibility_user_id
            LEFT JOIN auth_roles eligible_role ON eligible_role.id = eligible_user.role_id
            LEFT JOIN user_employees eligible_employee ON eligible_employee.user_id = eligible_user.id
            LEFT JOIN user_approval_request_steps actionable
                ON actionable.request_id = r.id AND actionable.sort_no = r.current_step
               AND (actionable.approver_id = :actionable_user_id OR (
                    actionable.approver_id IS NULL AND actionable.role_id = eligible_user.role_id
                    AND eligible_user.approved = 1 AND eligible_user.is_active = 1 AND eligible_role.is_active = 1
                    AND (eligible_employee.doc_retire_date IS NULL OR eligible_employee.doc_retire_date > CURRENT_DATE())
                    AND (eligible_employee.real_retire_date IS NULL OR eligible_employee.real_retire_date > CURRENT_DATE())
               ))
               AND actionable.status = 'pending'
               AND actionable.step_type IN ('APPROVAL', 'FINAL_APPROVAL') AND actionable.is_active = 1
            LEFT JOIN (
                SELECT DISTINCT request_id, approver_id FROM user_approval_request_steps WHERE is_active = 1
            ) participant ON participant.request_id = r.id AND participant.approver_id = :participant_user_id
            LEFT JOIN (
                SELECT DISTINCT request_id, role_id FROM user_approval_request_steps
                WHERE is_active = 1 AND approver_id IS NULL AND role_id IS NOT NULL
            ) role_participant ON role_participant.request_id = r.id
               AND role_participant.role_id = eligible_user.role_id
               AND eligible_user.approved = 1 AND eligible_user.is_active = 1 AND eligible_role.is_active = 1
               AND (eligible_employee.doc_retire_date IS NULL OR eligible_employee.doc_retire_date > CURRENT_DATE())
               AND (eligible_employee.real_retire_date IS NULL OR eligible_employee.real_retire_date > CURRENT_DATE())";
    }
}
