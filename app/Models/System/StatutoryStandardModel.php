<?php

namespace App\Models\System;

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
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
                $status = strtoupper($value);
                $status = match ($status) {
                    'CURRENT', '현재 적용', '현행' => 'CURRENT',
                    'ENDED', '종료' => 'ENDED',
                    'SCHEDULED', '적용 예정', '예정' => 'SCHEDULED',
                    default => '',
                };
                if ($status === 'CURRENT') {
                    $where[] = 's.effective_from<=CURRENT_DATE AND (s.effective_to IS NULL OR s.effective_to>=CURRENT_DATE)';
                } elseif ($status === 'ENDED') {
                    $where[] = 's.effective_to IS NOT NULL AND s.effective_to<CURRENT_DATE';
                } elseif ($status === 'SCHEDULED') {
                    $where[] = 's.effective_from>CURRENT_DATE';
                }
            } elseif ($field === 'note') {
                $where[] = 's.note LIKE :note';
                $params[':note'] = '%' . $value . '%';
            }
        }
        $search = trim((string) ($query['search']['value'] ?? ''));
        if ($search !== '') {
            $where[] = '(c.code_name LIKE :query OR s.standard_type_code LIKE :query OR s.note LIKE :query)';
            $params[':query'] = '%' . $search . '%';
        }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $baseFrom = ' FROM system_statutory_standards s'
            . " JOIN system_codes c ON c.code_group='STATUTORY_STANDARD_TYPE'"
            . ' AND c.code=s.standard_type_code';
        $from = $baseFrom . $clause;
        $count = $this->db->prepare('SELECT COUNT(*)' . $from);
        $count->execute($params);
        $filtered = (int) $count->fetchColumn();
        $total = (int) $this->db->query('SELECT COUNT(*) FROM system_statutory_standards')->fetchColumn();
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(200, (int) ($query['length'] ?? 50)));
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
        ];
        $orderIndex = (int) ($query['order'][0]['column'] ?? -1);
        $orderKey = (string) ($query['columns'][$orderIndex]['data'] ?? '');
        $orderColumn = $orderMap[$orderKey] ?? 's.effective_from';
        $orderDirection = strtolower((string) ($query['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $statement = $this->db->prepare(
            'SELECT s.id,s.sort_no,s.standard_type_code,c.code_name standard_type_name,'
            . 's.effective_from,s.effective_to,s.value_data,s.note,'
            . 's.created_at,s.created_by,s.updated_at,s.updated_by,'
            . "CASE WHEN s.effective_from>CURRENT_DATE THEN 'SCHEDULED'"
            . " WHEN s.effective_to IS NULL OR s.effective_to>=CURRENT_DATE THEN 'CURRENT'"
            . " ELSE 'ENDED' END period_status,"
            . 'COALESCE(src.source_count,0) source_count'
            . $baseFrom
            . ' LEFT JOIN (SELECT standard_id,COUNT(*) source_count'
            . ' FROM system_statutory_standard_sources GROUP BY standard_id) src ON src.standard_id=s.id'
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
        return ActorHelper::enrichActorNamesRow($row, ['created_by', 'updated_by']);
    }

    public function create(array $data): string
    {
        $id = UuidHelper::generate();
        $this->insert('system_statutory_standards', ['id' => $id] + $data);
        return $id;
    }

    public function update(string $id, array $data): void
    {
        $params = [':id' => $id];
        $sets = [];
        foreach ($data as $field => $value) {
            $sets[] = $field . '=:' . $field;
            $params[':' . $field] = $value;
        }
        $statement = $this->db->prepare('UPDATE system_statutory_standards SET ' . implode(',', $sets) . ' WHERE id=:id');
        $statement->execute($params);
    }

    public function delete(string $id): void
    {
        $statement = $this->db->prepare('DELETE FROM system_statutory_standards WHERE id=:id');
        $statement->execute([':id' => $id]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('삭제할 법정기준을 찾을 수 없습니다.');
        }
    }

    public function reorder(array $changes, string $actor): void
    {
        $statement = $this->db->prepare(
            'UPDATE system_statutory_standards'
            . ' SET sort_no=:sort_no,updated_at=:updated_at,updated_by=:updated_by WHERE id=:id'
        );
        $now = date('Y-m-d H:i:s');
        foreach ($changes as $change) {
            $id = trim((string) ($change['id'] ?? ''));
            $sortNo = (int) ($change['newSortNo'] ?? 0);
            if ($id === '' || $sortNo < 1) {
                throw new \InvalidArgumentException('순서 변경 정보가 올바르지 않습니다.');
            }
            $statement->execute([
                ':id' => $id,
                ':sort_no' => $sortNo,
                ':updated_at' => $now,
                ':updated_by' => $actor,
            ]);
            if ($statement->rowCount() > 1) {
                throw new \RuntimeException('순서 저장 중 오류가 발생했습니다.');
            }
        }
    }

    public function overlappingPeriods(string $type, string $from, ?string $to, string $excludeId = ''): array
    {
        $statement = $this->db->prepare(
            'SELECT id,effective_from,effective_to FROM system_statutory_standards WHERE standard_type_code=:type'
            . ' AND id<>:exclude_id'
            . " AND effective_from<=COALESCE(:effective_to,'9999-12-31')"
            . ' AND (effective_to IS NULL OR effective_to>=:effective_from) FOR UPDATE'
        );
        $statement->execute([
            ':type' => $type,
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
            . ' WHERE code_group=:code_group AND is_active=1 ORDER BY sort_no'
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
