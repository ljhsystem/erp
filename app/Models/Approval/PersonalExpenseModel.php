<?php

namespace App\Models\Approval;

use Core\Helpers\ActorHelper;
use PDO;

class PersonalExpenseModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function lockEmployee(string $employeeId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM user_employees WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $employeeId]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException('로그인 사용자와 연결된 직원 정보를 찾을 수 없습니다.');
        }
    }

    public function nextSortNo(string $employeeId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_no), 0) + 1 FROM approval_personal_expenses WHERE employee_id = :employee_id');
        $stmt->execute([':employee_id' => $employeeId]);
        return max(1, (int) $stmt->fetchColumn());
    }

    public function pageForEmployee(string $employeeId, array $query = [], array $filters = []): array
    {
        $where = ['p.employee_id = :employee_id', 'p.deleted_at IS NULL'];
        $params = [':employee_id' => $employeeId];
        foreach ($filters as $index => $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? null;
            if ($field === 'keyword' && trim((string) $value) !== '') {
                $key = ':keyword_' . $index;
                $where[] = '(p.title LIKE ' . $key . ' OR p.description LIKE ' . $key . ' OR p.memo LIKE ' . $key . ')';
                $params[$key] = '%' . trim((string) $value) . '%';
            } elseif ($field === 'application_date' && is_array($value)) {
                if (!empty($value['start'])) {
                    $where[] = 'p.application_date >= :date_start';
                    $params[':date_start'] = $value['start'];
                }
                if (!empty($value['end'])) {
                    $where[] = 'p.application_date <= :date_end';
                    $params[':date_end'] = $value['end'];
                }
            }
        }
        $baseFrom = " FROM approval_personal_expenses p
            INNER JOIN user_employees e ON e.id = p.employee_id
            LEFT JOIN user_approval_requests pr
                ON pr.id = p.current_approval_request_id
               AND pr.document_type = 'PERSONAL_EXPENSE'
               AND pr.document_id = p.id
               AND pr.is_active = 1
            LEFT JOIN user_approval_request_steps current_step
                ON current_step.request_id = pr.id
               AND current_step.sort_no = pr.current_step
               AND current_step.is_active = 1
            LEFT JOIN user_employees assigned_approver
                ON assigned_approver.user_id = current_step.approver_id
            LEFT JOIN auth_roles assigned_role
                ON assigned_role.id = current_step.role_id
            LEFT JOIN user_approval_request_steps last_action
                ON last_action.id = (
                    SELECT action_step.id
                    FROM user_approval_request_steps action_step
                    WHERE action_step.request_id = pr.id
                      AND action_step.status IN ('approved', 'rejected')
                      AND action_step.acted_by IS NOT NULL
                      AND action_step.action_at IS NOT NULL
                      AND action_step.is_active = 1
                    ORDER BY action_step.action_at DESC, action_step.sort_no DESC, action_step.id DESC
                    LIMIT 1
                )
            LEFT JOIN user_employees last_action_actor
                ON last_action_actor.user_id = last_action.acted_by
            LEFT JOIN user_employees withdrawn_actor
                ON withdrawn_actor.user_id = pr.withdrawn_by
            LEFT JOIN user_employees cancelled_actor
                ON cancelled_actor.user_id = pr.cancelled_by";
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $totalStmt = $this->pdo->prepare('SELECT COUNT(*) FROM approval_personal_expenses WHERE employee_id = :employee_id AND deleted_at IS NULL');
        $totalStmt->execute([':employee_id' => $employeeId]);
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM approval_personal_expenses p' . $whereSql);
        $countStmt->execute($params);
        $columnMap = [
            'sort_no' => 'p.sort_no', 'application_date' => 'p.application_date', 'title' => 'p.title',
            'employee_id' => 'e.employee_name', 'employee_name' => 'e.employee_name', 'item_count' => 'p.item_count',
            'supply_amount' => 'p.supply_amount', 'vat_amount' => 'p.vat_amount',
            'total_amount' => 'p.total_amount', 'document_status' => 'pr.status', 'approval_status' => 'pr.status',
            'current_approval_request_id' => 'current_step.step_name',
            'created_at' => 'p.created_at', 'updated_at' => 'p.updated_at',
        ];
        $orderIndex = (int) ($query['order'][0]['column'] ?? 0);
        $orderField = trim((string) ($query['columns'][$orderIndex]['data'] ?? ''));
        $orderColumn = $columnMap[$orderField] ?? 'p.sort_no';
        $direction = strtolower((string) ($query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(500, (int) ($query['length'] ?? 100)));
        $sql = "SELECT p.*, e.employee_name,
                    pr.id latest_request_id, COALESCE(pr.status, 'draft') approval_status,
                    pr.requested_at, pr.completed_at,
                    pr.withdrawn_at, pr.cancelled_at,
                    pr.current_step,
                    current_step.step_name current_step_name,
                    assigned_approver.employee_name assigned_approver_name,
                    assigned_role.role_name assigned_role_name,
                    last_action.status last_action_status,
                    last_action.action_at last_action_at,
                    last_action_actor.employee_name last_action_actor_name,
                    withdrawn_actor.employee_name withdrawn_actor_name,
                    cancelled_actor.employee_name cancelled_actor_name,
                    (SELECT s.comment FROM user_approval_request_steps s WHERE s.request_id = pr.id AND s.status = 'rejected' AND s.is_active = 1 ORDER BY s.action_at DESC LIMIT 1) rejection_reason,
                    (SELECT s.action_at FROM user_approval_request_steps s WHERE s.request_id = pr.id AND s.status = 'rejected' AND s.is_active = 1 ORDER BY s.action_at DESC LIMIT 1) rejected_at
                {$baseFrom}{$whereSql}
                ORDER BY {$orderColumn} {$direction}, p.sort_no DESC
                LIMIT {$start}, {$length}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = ActorHelper::enrichActorNames($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], [
            'created_by_name' => 'created_by', 'updated_by_name' => 'updated_by', 'deleted_by_name' => 'deleted_by',
        ]);
        return ['rows' => $rows, 'total' => (int) $totalStmt->fetchColumn(), 'filtered' => (int) $countStmt->fetchColumn()];
    }

    public function findOwned(string $id, string $employeeId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT p.*, e.employee_name, pr.id latest_request_id,
                       COALESCE(pr.status, 'draft') approval_status, pr.requested_at,
                       pr.completed_at,
                       pr.withdrawn_at, pr.cancelled_at,
                       pr.current_step,
                       current_step.step_name current_step_name,
                       (SELECT s.comment FROM user_approval_request_steps s WHERE s.request_id = pr.id AND s.status = 'rejected' AND s.is_active = 1 ORDER BY s.action_at DESC LIMIT 1) rejection_reason,
                       (SELECT s.action_at FROM user_approval_request_steps s WHERE s.request_id = pr.id AND s.status = 'rejected' AND s.is_active = 1 ORDER BY s.action_at DESC LIMIT 1) rejected_at
                FROM approval_personal_expenses p
                INNER JOIN user_employees e ON e.id = p.employee_id
                LEFT JOIN user_approval_requests pr
                    ON pr.id = p.current_approval_request_id
                   AND pr.document_type = 'PERSONAL_EXPENSE'
                   AND pr.document_id = p.id
                   AND pr.is_active = 1
                LEFT JOIN user_approval_request_steps current_step
                    ON current_step.request_id = pr.id
                   AND current_step.sort_no = pr.current_step
                   AND current_step.is_active = 1
                WHERE p.id = :id AND p.employee_id = :employee_id
                LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':employee_id' => $employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $row ? ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by', 'updated_by_name' => 'updated_by', 'deleted_by_name' => 'deleted_by',
        ]) : null;
    }

    public function findById(string $id, bool $forUpdate = false): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, employee.employee_name
             FROM approval_personal_expenses p
             INNER JOIN user_employees employee ON employee.id = p.employee_id
             WHERE p.id = :id AND p.deleted_at IS NULL LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $row ? ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]) : null;
    }

    public function trashForEmployee(string $employeeId): array
    {
        $stmt = $this->pdo->prepare("SELECT p.*, e.employee_name,
                    COALESCE(pr.status, 'draft') approval_status
                FROM approval_personal_expenses p
                INNER JOIN user_employees e ON e.id = p.employee_id
                LEFT JOIN user_approval_requests pr
                  ON pr.id = p.current_approval_request_id
                 AND pr.document_type = 'PERSONAL_EXPENSE'
                 AND pr.document_id = p.id
                 AND pr.is_active = 1
                WHERE p.employee_id = :employee_id AND p.deleted_at IS NOT NULL
                ORDER BY p.deleted_at DESC, p.sort_no DESC");
        $stmt->execute([':employee_id' => $employeeId]);
        return ActorHelper::enrichActorNames($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], [
            'created_by_name' => 'created_by', 'updated_by_name' => 'updated_by', 'deleted_by_name' => 'deleted_by',
        ]);
    }

    public function approvalHistoryStatuses(string $id): array
    {
        $stmt = $this->pdo->prepare("SELECT status
            FROM user_approval_requests
            WHERE document_type = 'PERSONAL_EXPENSE' AND document_id = :id AND is_active = 1
            ORDER BY sort_no");
        $stmt->execute([':id' => $id]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function purge(string $id, string $employeeId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM approval_personal_expenses WHERE id = :id AND employee_id = :employee_id AND deleted_at IS NOT NULL');
        $stmt->execute([':id' => $id, ':employee_id' => $employeeId]);
        return $stmt->rowCount();
    }

    public function insert(array $data): void
    {
        $columns = ['id', 'sort_no', 'employee_id', 'application_date', 'title', 'description', 'memo', 'created_by', 'updated_by'];
        $stmt = $this->pdo->prepare('INSERT INTO approval_personal_expenses (' . implode(',', $columns) . ') VALUES (' . implode(',', array_map(static fn ($column) => ':' . $column, $columns)) . ')');
        $params = [];
        foreach ($columns as $column) {
            $params[':' . $column] = $data[$column] ?? null;
        }
        $stmt->execute($params);
    }

    public function update(string $id, string $employeeId, array $data): int
    {
        $columns = ['application_date', 'title', 'description', 'memo', 'updated_by'];
        $sets = array_map(static fn ($column) => "{$column} = :{$column}", $columns);
        $stmt = $this->pdo->prepare('UPDATE approval_personal_expenses SET ' . implode(',', $sets) . ', updated_at = NOW() WHERE id = :id AND employee_id = :employee_id AND deleted_at IS NULL');
        $params = [':id' => $id, ':employee_id' => $employeeId];
        foreach ($columns as $column) {
            $params[':' . $column] = $data[$column] ?? null;
        }
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function updateAggregates(string $id, array $aggregate, string $actor): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE approval_personal_expenses
                SET item_count = :item_count,
                    supply_amount = :supply_amount,
                    vat_amount = :vat_amount,
                    total_amount = :total_amount,
                    updated_at = NOW(),
                    updated_by = :updated_by
              WHERE id = :id'
        );
        $stmt->execute([
            ':item_count' => (int) ($aggregate['item_count'] ?? 0),
            ':supply_amount' => $aggregate['supply_amount'] ?? '0.00',
            ':vat_amount' => $aggregate['vat_amount'] ?? '0.00',
            ':total_amount' => $aggregate['total_amount'] ?? '0.00',
            ':updated_by' => $actor,
            ':id' => $id,
        ]);
        return $stmt->rowCount();
    }

    public function updateSortNo(string $id, string $employeeId, int $sortNo): int
    {
        $stmt = $this->pdo->prepare('UPDATE approval_personal_expenses SET sort_no = :sort_no WHERE id = :id AND employee_id = :employee_id AND deleted_at IS NULL');
        $stmt->execute([':sort_no' => $sortNo, ':id' => $id, ':employee_id' => $employeeId]);
        return $stmt->rowCount();
    }

    public function updateWorkflow(string $id, string $documentStatus, ?string $requestId, string $actor): int
    {
        $stmt = $this->pdo->prepare('UPDATE approval_personal_expenses SET document_status = :document_status, current_approval_request_id = :request_id, updated_at = NOW(), updated_by = :updated_by WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            ':document_status' => $documentStatus,
            ':request_id' => $requestId,
            ':updated_by' => $actor,
            ':id' => $id,
        ]);
        return $stmt->rowCount();
    }

    public function softDelete(string $id, string $employeeId, string $actor): int
    {
        $stmt = $this->pdo->prepare('UPDATE approval_personal_expenses SET deleted_at = NOW(), deleted_by = :deleted_by, updated_at = NOW(), updated_by = :updated_by WHERE id = :id AND employee_id = :employee_id AND deleted_at IS NULL');
        $stmt->execute([':deleted_by' => $actor, ':updated_by' => $actor, ':id' => $id, ':employee_id' => $employeeId]);
        return $stmt->rowCount();
    }

    public function restore(string $id, string $employeeId, string $actor): int
    {
        $stmt = $this->pdo->prepare('UPDATE approval_personal_expenses SET deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :actor WHERE id = :id AND employee_id = :employee_id AND deleted_at IS NOT NULL');
        $stmt->execute([':actor' => $actor, ':id' => $id, ':employee_id' => $employeeId]);
        return $stmt->rowCount();
    }
}
