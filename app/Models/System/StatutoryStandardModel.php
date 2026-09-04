<?php

namespace App\Models\System;

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use App\Services\System\StatutoryStandardPeriodStatusProjection;
use PDO;

class StatutoryStandardModel
{
    public function __construct(private PDO $db)
    {
    }

    public function page(array $query): array
    {
        $params = [];
        $where = [];
        $dimensionSelect = $this->hasInsuranceDimensionColumns()
            ? 's.policy_component_code,s.employment_type_code,s.work_scope_code,'
            : 'NULL policy_component_code,NULL employment_type_code,NULL work_scope_code,';
        $periodStatusSql = StatutoryStandardPeriodStatusProjection::sql('s');
        $filters = json_decode((string) ($query['filters'] ?? '[]'), true);
        foreach (is_array($filters) ? $filters : [] as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $rawValue = $filter['value'] ?? '';
            if ($field === 'as_of_date' && is_array($rawValue)) {
                $startDate = substr((string) ($rawValue['start'] ?? ''), 0, 10);
                $endDate = substr((string) ($rawValue['end'] ?? ''), 0, 10);
                if ($this->isDate($startDate) && $this->isDate($endDate)) {
                    $where[] = 's.effective_from<=:as_of_end AND (s.effective_to IS NULL OR s.effective_to>=:as_of_start)';
                    $params[':as_of_start'] = $startDate;
                    $params[':as_of_end'] = $endDate;
                }
                continue;
            }
            $value = is_array($rawValue) ? '' : trim((string) $rawValue);
            if ($value === '') {
                continue;
            }
            if ($field === 'standard_type_code') {
                $where[] = '(s.standard_type_code=:standard_type_code OR c.code_name LIKE :standard_type_name)';
                $params[':standard_type_code'] = $value;
                $params[':standard_type_name'] = '%' . $value . '%';
            } elseif ($field === 'effective_year' && preg_match('/^\d{4}$/', $value)) {
                $where[] = 'YEAR(s.effective_from)=:effective_year';
                $params[':effective_year'] = (int) $value;
            } elseif ($field === 'as_of_date' && $this->isDate($value)) {
                $where[] = 's.effective_from<=:as_of_from AND (s.effective_to IS NULL OR s.effective_to>=:as_of_to)';
                $params[':as_of_from'] = $value;
                $params[':as_of_to'] = $value;
            } elseif ($field === 'period_status') {
                $status = StatutoryStandardPeriodStatusProjection::normalizeFilter($value);
                if ($status !== '') {
                    $where[] = $periodStatusSql . '=:period_status';
                    $params[':period_status'] = $status;
                }
            } elseif ($field === 'note') {
                $where[] = 's.note LIKE :note';
                $params[':note'] = '%' . $value . '%';
            }
        }
        $search = trim((string) ($query['search']['value'] ?? ''));
        if ($search !== '') {
            $where[] = '(c.code_name LIKE :search_type_name'
                . ' OR s.standard_type_code LIKE :search_type_code'
                . ' OR s.note LIKE :search_note'
                . ' OR ' . $periodStatusSql . ' LIKE :search_period_status)';
            $searchPattern = '%' . $search . '%';
            $params[':search_type_name'] = $searchPattern;
            $params[':search_type_code'] = $searchPattern;
            $params[':search_note'] = $searchPattern;
            $params[':search_period_status'] = $searchPattern;
        }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $baseFrom = ' FROM system_statutory_standards s'
            . " JOIN system_codes c ON c.code_group='STATUTORY_STANDARD_TYPE'"
            . ' AND c.code=s.standard_type_code AND c.is_active=1';
        $from = $baseFrom . $clause;
        $count = $this->db->prepare('SELECT COUNT(*)' . $from);
        $count->execute($params);
        $filtered = (int) $count->fetchColumn();
        $total = (int) $this->db->query("SELECT COUNT(*) FROM system_statutory_standards s JOIN system_codes c ON c.code_group='STATUTORY_STANDARD_TYPE' AND c.code=s.standard_type_code AND c.is_active=1")->fetchColumn();
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(200, (int) ($query['length'] ?? 50)));
        [$orderColumn, $orderDirection] = $this->resolveOrder($query, $periodStatusSql);
        $statement = $this->db->prepare(
            'SELECT s.id,s.sort_no,s.standard_type_code,' . $dimensionSelect . 'c.code_name standard_type_name,'
            . 's.effective_from,s.effective_to,s.value_data,s.note,'
            . 's.created_at,s.created_by,s.updated_at,s.updated_by,'
            . $periodStatusSql . ' period_status,'
            . 'COALESCE(src.source_count,0) source_count,'
            . 'incoming.predecessor_revision_id,outgoing.successor_revision_id,'
            . 'COALESCE(incoming.correction_reason,outgoing.correction_reason) correction_reason'
            . $baseFrom
            . ' LEFT JOIN (SELECT standard_id,COUNT(*) source_count'
            . ' FROM system_statutory_standard_sources GROUP BY standard_id) src ON src.standard_id=s.id'
            . ' LEFT JOIN system_statutory_standard_supersessions incoming ON incoming.successor_revision_id=s.id'
            . ' LEFT JOIN system_statutory_standard_supersessions outgoing ON outgoing.predecessor_revision_id=s.id'
            . $clause
            . ' ORDER BY ' . $orderColumn . ' ' . $orderDirection . ',s.sort_no DESC,s.id DESC'
            . ' LIMIT ' . $start . ',' . $length
        );
        $statement->execute($params);
        return [
            'rows' => ActorHelper::enrichActorNames($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], ['created_by', 'updated_by']),
            'total' => $total,
            'filtered' => $filtered,
        ];
    }

    private function resolveOrder(array $query, string $periodStatusSql): array
    {
        $orderMap = [
            'standard_type_code' => 's.standard_type_code',
            'standard_type_name' => 'c.code_name',
            'effective_year' => 's.effective_from',
            'effective_from' => 's.effective_from',
            'effective_to' => 's.effective_to',
            'sort_no' => 's.sort_no',
            'note' => 's.note',
            'created_at' => 's.created_at',
            'updated_at' => 's.updated_at',
            'period_status' => $periodStatusSql,
        ];
        $order = $query['order'][0] ?? null;
        if (!is_array($order)) return ['s.effective_from', 'DESC'];

        $orderIndex = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
        if ($orderIndex === false || !isset($query['columns'][$orderIndex]) || !is_array($query['columns'][$orderIndex])) {
            throw new \InvalidArgumentException('정렬 컬럼 위치가 올바르지 않습니다.');
        }
        $column = $query['columns'][$orderIndex];
        $orderKey = trim((string)($column['name'] ?? ''));
        if ($orderKey === '') $orderKey = trim((string)($column['data'] ?? ''));
        if (!isset($orderMap[$orderKey])) {
            throw new \InvalidArgumentException('지원하지 않는 정렬 컬럼입니다.');
        }
        $direction = strtolower(trim((string)($order['dir'] ?? '')));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('정렬 방향이 올바르지 않습니다.');
        }
        return [$orderMap[$orderKey], strtoupper($direction)];
    }

    private function hasInsuranceDimensionColumns(): bool
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS"
            . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards'"
            . " AND COLUMN_NAME IN('policy_component_code','employment_type_code','work_scope_code')"
        );
        $statement->execute();
        return (int)$statement->fetchColumn() === 3;
    }

    public function detail(string $id, bool $lock = false): ?array
    {
        $statement = $this->db->prepare(
            'SELECT s.*,c.code_name standard_type_name'
            . ' FROM system_statutory_standards s'
            . " JOIN system_codes c ON c.code_group='STATUTORY_STANDARD_TYPE' AND c.code=s.standard_type_code"
            . ' WHERE s.id=:id' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['sources'] = $this->rows(
            'SELECT * FROM system_statutory_standard_sources WHERE standard_id=:id ORDER BY sort_no,id',
            [':id' => $id]
        );
        $row['supersession_chain'] = (new StatutoryStandardSupersessionModel($this->db))->chain($id);
        return ActorHelper::enrichActorNamesRow($row, ['created_by', 'updated_by']);
    }

    public function supersessionEdges(string $type, ?string $component, ?string $employmentType, ?string $workScope, ?string $dimensionKey): array
    {
        $statement = $this->db->prepare(
            'SELECT relation.predecessor_revision_id,relation.successor_revision_id'
            . ' FROM system_statutory_standard_supersessions relation'
            . ' JOIN system_statutory_standards predecessor ON predecessor.id=relation.predecessor_revision_id'
            . ' WHERE predecessor.standard_type_code=:type'
            . ' AND predecessor.policy_component_code<=>:component'
            . ' AND predecessor.employment_type_code<=>:employment_type'
            . ' AND predecessor.work_scope_code<=>:work_scope'
            . ' AND predecessor.additional_dimension_key<=>:dimension_key'
        );
        $statement->execute([
            ':type' => $type,
            ':component' => $component,
            ':employment_type' => $employmentType,
            ':work_scope' => $workScope,
            ':dimension_key' => $dimensionKey,
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): string
    {
        $id = UuidHelper::generate();
        $this->insert('system_statutory_standards', ['id' => $id] + $data);
        return $id;
    }

    public function update(string $id, array $data): void
    {
        throw new \LogicException('확정된 법정기준 Revision은 수정할 수 없습니다. 신규 Correction을 등록하세요.');
    }

    public function delete(string $id): void
    {
        throw new \LogicException('확정된 법정기준 Revision은 삭제할 수 없습니다.');
    }

    public function reorder(array $changes, string $actor): void
    {
        throw new \LogicException('확정된 법정기준 Revision의 순서는 변경할 수 없습니다.');
    }

    public function overlappingPeriods(string $type, ?string $component, ?string $employmentType, ?string $workScope, ?string $dimensionKey, string $from, ?string $to, string $excludeId = ''): array
    {
        $statement = $this->db->prepare(
            'SELECT id,effective_from,effective_to FROM system_statutory_standards WHERE standard_type_code=:type'
            . ' AND policy_component_code<=>:component AND employment_type_code<=>:employment_type AND work_scope_code<=>:work_scope'
            . ' AND additional_dimension_key<=>:dimension_key'
            . ' AND id<>:exclude_id'
            . " AND effective_from<=COALESCE(:effective_to,'9999-12-31')"
            . ' AND (effective_to IS NULL OR effective_to>=:effective_from) FOR UPDATE'
        );
        $statement->execute([
            ':type' => $type,
            ':component' => $component,
            ':employment_type' => $employmentType,
            ':work_scope' => $workScope,
            ':dimension_key' => $dimensionKey,
            ':exclude_id' => $excludeId,
            ':effective_from' => $from,
            ':effective_to' => $to,
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function attendanceReferences(string $id): array
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) reference_count,MIN(work_date) first_work_date,MAX(work_date) last_work_date'
            . ' FROM institution_attendance_daily_records'
            . ' WHERE working_time_standard_id=:working_id OR public_holiday_standard_id=:holiday_id'
        );
        $statement->execute([':working_id' => $id, ':holiday_id' => $id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: ['reference_count' => 0, 'first_work_date' => null, 'last_work_date' => null];
    }

    public function options(string $group): array
    {
        $statement = $this->db->prepare(
            'SELECT code value,code_name label,extra_data FROM system_codes'
            . ' WHERE code_group=:code_group AND is_active=1 ORDER BY sort_no,code_name,id'
        );
        $statement->execute([':code_group' => $group]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function codeExists(string $group, string $code): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM system_codes WHERE code_group=:code_group AND code=:code AND is_active=1'
        );
        $statement->execute([':code_group' => $group, ':code' => $code]);
        return (bool) $statement->fetchColumn();
    }

    public function rows(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function insert(string $table, array $data): void
    {
        $fields = array_keys($data);
        $statement = $this->db->prepare(
            'INSERT INTO ' . $table . '(' . implode(',', $fields) . ') VALUES(:' . implode(',:', $fields) . ')'
        );
        $statement->execute(array_combine(array_map(static fn(string $field): string => ':' . $field, $fields), array_values($data)));
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
