<?php

namespace App\Models\Institution;

use Core\Helpers\ActorHelper;
use PDO;

class EmploymentContractModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function page(array $query, bool $trash = false): array
    {
        $keyword = trim((string) ($query['search']['value'] ?? $query['keyword'] ?? ''));
        $where = [$trash ? 'c.deleted_at IS NOT NULL' : 'c.deleted_at IS NULL'];
        $params = [];
        if ($keyword !== '') {
            $where[] = '(c.contract_no LIKE :keyword_contract OR e.employee_name LIKE :keyword_employee
                OR c.contract_status LIKE :keyword_status OR c.contract_period_type LIKE :keyword_period
                OR c.employment_category LIKE :keyword_category OR c.working_time_type LIKE :keyword_working_time)';
            foreach ([
                ':keyword_contract', ':keyword_employee', ':keyword_status', ':keyword_period',
                ':keyword_category', ':keyword_working_time',
            ] as $key) {
                $params[$key] = '%' . $keyword . '%';
            }
        }
        $filterColumns = [
            'keyword' => null,
            'contract_no' => 'c.contract_no',
            'employee_name' => 'e.employee_name',
            'contract_period_type' => 'c.contract_period_type',
            'employment_category' => 'c.employment_category',
            'working_time_type' => 'c.working_time_type',
            'contract_status' => 'c.contract_status',
            'contract_start_date' => 'c.contract_start_date',
            'contract_end_date' => 'c.contract_end_date',
        ];
        $filters = json_decode((string) ($query['filters'] ?? ''), true);
        foreach (is_array($filters) ? $filters : [] as $index => $filter) {
            $field = trim((string) ($filter['field'] ?? ''));
            $value = $filter['value'] ?? null;
            if ($field === 'keyword' && is_scalar($value) && trim((string) $value) !== '') {
                $keys = [
                    ':filter_contract_' . $index,
                    ':filter_employee_' . $index,
                    ':filter_status_' . $index,
                    ':filter_period_' . $index,
                    ':filter_category_' . $index,
                    ':filter_working_time_' . $index,
                ];
                $where[] = '(c.contract_no LIKE ' . $keys[0] . ' OR e.employee_name LIKE ' . $keys[1]
                    . ' OR c.contract_status LIKE ' . $keys[2]
                    . ' OR c.contract_period_type LIKE ' . $keys[3]
                    . ' OR c.employment_category LIKE ' . $keys[4]
                    . ' OR c.working_time_type LIKE ' . $keys[5] . ')';
                foreach ($keys as $key) {
                    $params[$key] = '%' . trim((string) $value) . '%';
                }
                continue;
            }
            $column = $filterColumns[$field] ?? null;
            if ($column === null) {
                continue;
            }
            if (is_array($value)) {
                $start = trim((string) ($value['start'] ?? ''));
                $end = trim((string) ($value['end'] ?? ''));
                if ($start !== '' && $end !== '') {
                    $startKey = ':filter_start_' . $index;
                    $endKey = ':filter_end_' . $index;
                    $where[] = "{$column} BETWEEN {$startKey} AND {$endKey}";
                    $params[$startKey] = $start;
                    $params[$endKey] = $end;
                }
                continue;
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                $key = ':filter_' . $index;
                $where[] = "{$column} LIKE {$key}";
                $params[$key] = '%' . trim((string) $value) . '%';
            }
        }
        $whereSql = implode(' AND ', $where);
        $orderColumns = [
            'sort_no' => 'c.sort_no',
            'contract_no' => 'c.contract_no',
            'employee_name' => 'e.employee_name',
            'contract_period_type' => 'c.contract_period_type',
            'employment_category' => 'c.employment_category',
            'working_time_type' => 'c.working_time_type',
            'contract_start_date' => 'c.contract_start_date',
            'contract_end_date' => 'c.contract_end_date',
            'contract_status' => 'c.contract_status',
            'revision_no' => 'c.revision_no',
            'updated_by' => 'c.updated_by',
            'updated_at' => 'c.updated_at',
        ];
        $orderIndex = (int) ($query['order'][0]['column'] ?? -1);
        $orderData = $orderIndex >= 0 ? (string) ($query['columns'][$orderIndex]['data'] ?? '') : '';
        $orderColumn = $orderColumns[$orderData] ?? 'c.updated_at';
        $orderDirection = strtolower((string) ($query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $totalStmt=$this->db->prepare('SELECT COUNT(*) FROM institution_employment_contracts c WHERE '.($trash?'c.deleted_at IS NOT NULL':'c.deleted_at IS NULL'));
        $totalStmt->execute();$total=(int)$totalStmt->fetchColumn();
        $count = $this->db->prepare(
            "SELECT COUNT(*) FROM institution_employment_contracts c
             JOIN user_employees e ON e.id = c.employee_id WHERE {$whereSql}"
        );
        $count->execute($params);
        $filtered = (int) $count->fetchColumn();
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(200, (int) ($query['length'] ?? 50)));
        $stmt = $this->db->prepare(
            "SELECT c.*,e.employee_name,p.project_name,
                    previous_contract.contract_no previous_contract_no,
                    approval_request.sort_no approval_request_no,
                    status_code.code_name contract_status_name
              FROM institution_employment_contracts c
              JOIN user_employees e ON e.id = c.employee_id
              LEFT JOIN system_projects p ON p.id = c.project_id
              LEFT JOIN institution_employment_contracts previous_contract ON previous_contract.id = c.previous_contract_id
              LEFT JOIN user_approval_requests approval_request ON approval_request.id = c.current_approval_request_id
             LEFT JOIN system_codes status_code
               ON status_code.code_group = 'EMPLOYMENT_CONTRACT_STATUS'
              AND status_code.code = c.contract_status
             WHERE {$whereSql}
             ORDER BY {$orderColumn} {$orderDirection}, c.id DESC
             LIMIT {$start}, {$length}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            unset($row['employee_identifier_snapshot']);
        }
        unset($row);
        $rows = ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
        return ['rows' => $rows, 'total' => $total, 'filtered' => $filtered];
    }

    public function find(string $id, bool $includeDeleted = false, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, e.employee_name, p.project_name,
                    fixed_reason.code_name fixed_term_reason_name
             FROM institution_employment_contracts c
             JOIN user_employees e ON e.id = c.employee_id
             LEFT JOIN system_projects p ON p.id = c.project_id
             LEFT JOIN system_codes fixed_reason
               ON fixed_reason.code_group = \'EMPLOYMENT_CONTRACT_FIXED_TERM_REASON\'
              AND fixed_reason.code = c.fixed_term_reason_code
             WHERE c.id = :id' . ($includeDeleted ? '' : ' AND c.deleted_at IS NULL')
            . ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !$forUpdate) {
            unset($row['employee_identifier_snapshot']);
        }
        return $row ? ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]) : null;
    }

    public function employeeSnapshotSource(string $employeeId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT e.employee_name, e.address, e.address_detail, e.rrn, pos.position_name
             FROM user_employees e
             LEFT JOIN user_positions pos ON pos.id = e.position_id
             WHERE e.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $employeeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function nextSortNo(): int
    {
        return max(1, (int) $this->db->query(
            'SELECT COALESCE(MAX(sort_no), 0) + 1 FROM institution_employment_contracts'
        )->fetchColumn());
    }

    public function updateSortNo(string $id, int $sortNo): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE institution_employment_contracts
             SET sort_no = :sort_no
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id, ':sort_no' => $sortNo]);
        return $stmt->rowCount() === 1;
    }

    public function create(array $data): void
    {
        $columns = array_keys($data);
        $stmt = $this->db->prepare(
            'INSERT INTO institution_employment_contracts (`'
            . implode('`,`', $columns) . '`) VALUES (:'
            . implode(',:', $columns) . ')'
        );
        if (!$stmt->execute($data)) {
            throw new \RuntimeException('근로계약을 저장하지 못했습니다.');
        }
    }

    public function updateEditable(string $id, array $data): bool
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "`{$column}` = :{$column}";
        }
        $data['id'] = $id;
        $stmt = $this->db->prepare(
            'UPDATE institution_employment_contracts SET ' . implode(', ', $sets)
            . " WHERE id = :id AND contract_status = 'DRAFT'
                AND deleted_at IS NULL"
        );
        $stmt->execute($data);
        if ($stmt->rowCount() === 1) {
            return true;
        }

        $exists = $this->db->prepare(
            "SELECT COUNT(*) FROM institution_employment_contracts
             WHERE id = :id AND contract_status = 'DRAFT' AND deleted_at IS NULL"
        );
        $exists->execute([':id' => $id]);
        return (int) $exists->fetchColumn() === 1;
    }

    public function updateWorkflow(string $id, string $status, ?string $requestId, string $actor): bool
    {
        $approvedAt = $status === 'APPROVED' ? 'approved_at = NOW(),' : '';
        $stmt = $this->db->prepare(
            "UPDATE institution_employment_contracts SET
                contract_status = :status,
                current_approval_request_id = :request_id,
                {$approvedAt}
                updated_at = NOW(),
                updated_by = :actor
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([
            ':id' => $id, ':status' => $status,
            ':request_id' => $requestId, ':actor' => $actor,
        ]);
        return $stmt->rowCount() === 1;
    }

    public function terminate(string $id, string $reason, string $actor): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE institution_employment_contracts SET
                contract_status = 'TERMINATED', terminated_at = NOW(),
                termination_reason = :reason, updated_at = NOW(), updated_by = :actor
             WHERE id = :id AND contract_status = 'APPROVED' AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id, ':reason' => $reason, ':actor' => $actor]);
        return $stmt->rowCount() === 1;
    }

    public function softDelete(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE institution_employment_contracts SET
                deleted_at = NOW(), deleted_by = :deleted_actor,
                updated_at = NOW(), updated_by = :updated_actor
             WHERE id = :id AND contract_status IN ('DRAFT','CANCELLED')
               AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id, ':deleted_actor' => $actor, ':updated_actor' => $actor]);
        return $stmt->rowCount() === 1;
    }

    public function restore(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE institution_employment_contracts SET
                deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :actor
             WHERE id = :id AND deleted_at IS NOT NULL'
        );
        $stmt->execute([':id' => $id, ':actor' => $actor]);
        return $stmt->rowCount() === 1;
    }

    public function purge(string $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM institution_employment_contracts
             WHERE id = :id AND deleted_at IS NOT NULL
               AND contract_status IN ('DRAFT','CANCELLED')"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function employmentPeriodHistory(string $employeeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, previous_contract_id, revision_no, contract_start_date, contract_end_date,
                    contract_status, terminated_at, deleted_at
             FROM institution_employment_contracts
             WHERE employee_id = :employee_id
             ORDER BY revision_no DESC, created_at DESC'
        );
        $stmt->execute([':employee_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function validityCandidates(
        ?string $employeeId,
        string $start,
        string $end,
        bool $lock = false,
        array $statuses = ['APPROVED', 'TERMINATED']
    ): array {
        $statusKeys = [];
        $params = [':start_date' => $start, ':termination_start_date' => $start, ':end_date' => $end];
        foreach (array_values($statuses) as $index => $status) {
            $key = ':status_' . $index;
            $statusKeys[] = $key;
            $params[$key] = $status;
        }
        $employeeSql = '';
        if ($employeeId !== null) {
            $employeeSql = ' AND employee_id = :employee_id';
            $params[':employee_id'] = $employeeId;
        }
        $sql = 'SELECT * FROM institution_employment_contracts
                WHERE deleted_at IS NULL' . $employeeSql . '
                  AND contract_status IN (' . implode(',', $statusKeys) . ')
                  AND contract_start_date <= :end_date
                  AND COALESCE(contract_end_date, DATE(terminated_at), \'9999-12-31\') >= :start_date
                  AND (contract_status <> \'TERMINATED\' OR DATE(terminated_at) >= :termination_start_date)
                ORDER BY employee_id, revision_no DESC, created_at DESC';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

}
