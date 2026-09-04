<?php

namespace App\Models\Institution;

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EmploymentRuleModel
{
    public function __construct(private PDO $db) {}

    public function page(array $query): array
    {
        $params = [];
        $where = ['r.deleted_at IS NULL', 'v.deleted_at IS NULL'];
        $filters = json_decode((string) ($query['filters'] ?? '[]'), true);
        foreach (is_array($filters) ? $filters : [] as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $rawValue = $filter['value'] ?? '';
            $value = is_array($rawValue) ? '' : trim((string) $rawValue);
            if ($value === '' && !is_array($rawValue)) continue;
            if ($field === 'regulation_type_code') {
                $where[] = 'r.regulation_type_code=:type';
                $params[':type'] = $value;
            } elseif ($field === 'status_code') {
                $where[] = 'v.status_code=:status';
                $params[':status'] = $value;
            } elseif ($field === 'effective_from') {
                if (is_array($rawValue)) {
                    $where[] = 'v.effective_from BETWEEN :date_from AND :date_to';
                    $params[':date_from'] = (string) ($rawValue['start'] ?? '');
                    $params[':date_to'] = (string) ($rawValue['end'] ?? '');
                }
            } elseif ($field === 'revision_date') {
                if (is_array($rawValue)) {
                    $where[] = 'v.revision_date BETWEEN :revision_from AND :revision_to';
                    $params[':revision_from'] = (string) ($rawValue['start'] ?? '');
                    $params[':revision_to'] = (string) ($rawValue['end'] ?? '');
                }
            } elseif ($field === 'regulation_code') {
                $where[] = 'r.regulation_code LIKE :regulation_code';
                $params[':regulation_code'] = '%' . $value . '%';
            } elseif ($field === 'title') {
                $where[] = '(r.title LIKE :title OR v.title LIKE :title)';
                $params[':title'] = '%' . $value . '%';
            } elseif ($field === 'is_current') {
                $today = date('Y-m-d');
                $predicate = "v.status_code IN ('SCHEDULED','EFFECTIVE','RETIRED') AND v.effective_from<=:today_from AND (v.effective_to IS NULL OR v.effective_to>=:today_to)";
                $where[] = $value === '1' ? '(' . $predicate . ')' : 'NOT (' . $predicate . ')';
                $params[':today_from'] = $today;
                $params[':today_to'] = $today;
            }
        }
        $search = trim((string) ($query['search']['value'] ?? ''));
        if ($search !== '') {
            $where[] = '(r.regulation_code LIKE :search OR r.title LIKE :search OR v.title LIKE :search OR v.change_summary LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $from = " FROM institution_employment_rules r
                   JOIN institution_employment_rules_revisions v ON v.rule_id=r.id
                   LEFT JOIN system_codes tc ON tc.code_group='EMPLOYMENT_RULE_TYPE' AND tc.code=r.regulation_type_code
                  WHERE " . implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(*)' . $from);
        $count->execute($params);
        $sortMap = [
            'regulation_type_code'=>'r.regulation_type_code', 'regulation_type_name'=>'tc.code_name',
            'regulation_code'=>'r.regulation_code', 'regulation_title'=>'r.title', 'revision_no'=>'v.revision_no',
            'revision_date'=>'v.revision_date', 'effective_from'=>'v.effective_from', 'effective_to'=>'v.effective_to',
            'status_code'=>'v.status_code', 'updated_at'=>'v.updated_at',
        ];
        $start = max(0, (int) ($query['start'] ?? 0));
        $length = max(1, min(200, (int) ($query['length'] ?? 50)));
        $sql = "SELECT r.id rule_id,r.company_id,r.regulation_code,r.regulation_type_code,tc.code_name regulation_type_name,
                       r.title regulation_title,r.description,r.owner_department_id,r.is_active,v.*,
                       CASE WHEN v.status_code IN ('SCHEDULED','EFFECTIVE','RETIRED')
                         AND v.effective_from<=CURRENT_DATE AND (v.effective_to IS NULL OR v.effective_to>=CURRENT_DATE)
                         THEN 1 ELSE 0 END is_current"
            . $from . ' ORDER BY ' . $this->orderSql($query, $sortMap) . ',v.id ASC LIMIT ' . $start . ',' . $length;
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return [
            'rows'=>ActorHelper::enrichActorNames($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], ['created_by','updated_by','approved_by','published_by']),
            'total'=>(int) $count->fetchColumn(),
        ];
    }

    public function detail(string $revisionId, bool $lock = false): ?array
    {
        $sql = "SELECT r.id rule_id,r.company_id,r.regulation_code,r.regulation_type_code,r.title regulation_title,
                       r.description,r.owner_department_id,r.is_active,v.*
                  FROM institution_employment_rules r
                  JOIN institution_employment_rules_revisions v ON v.rule_id=r.id
                 WHERE v.id=:id AND r.deleted_at IS NULL AND v.deleted_at IS NULL" . ($lock ? ' FOR UPDATE' : '');
        $statement = $this->db->prepare($sql);
        $statement->execute([':id'=>$revisionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ? ActorHelper::enrichActorNamesRow($row, ['created_by','updated_by','approved_by','published_by']) : null;
    }

    public function history(string $ruleId): array
    {
        return ActorHelper::enrichActorNames($this->rows(
            'SELECT * FROM institution_employment_rules_revisions WHERE rule_id=:id AND deleted_at IS NULL ORDER BY revision_no DESC',
            [':id'=>$ruleId]
        ), ['created_by','updated_by','approved_by','published_by']);
    }

    public function resolve(string $companyId, string $identity, string $baseDate): ?array
    {
        $statement = $this->db->prepare(
            "SELECT r.id rule_id,r.regulation_code,r.regulation_type_code,r.title regulation_title,v.*
               FROM institution_employment_rules r
               JOIN institution_employment_rules_revisions v ON v.rule_id=r.id
              WHERE r.company_id=:company AND (r.id=:identity_id OR r.regulation_code=:identity_code)
                AND r.is_active=1 AND r.deleted_at IS NULL AND v.deleted_at IS NULL
                AND v.status_code IN ('SCHEDULED','EFFECTIVE','RETIRED')
                AND v.effective_from<=:date_from AND (v.effective_to IS NULL OR v.effective_to>=:date_to)
              ORDER BY v.effective_from DESC,v.revision_no DESC LIMIT 2"
        );
        $statement->execute([':company'=>$companyId, ':identity_id'=>$identity, ':identity_code'=>$identity, ':date_from'=>$baseDate, ':date_to'=>$baseDate]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) > 1) throw new \RuntimeException('동일 기준일에 유효한 규정 개정본이 중복됩니다.');
        return $rows[0] ?? null;
    }

    public function findRevisionByRequestKey(string $requestKey): ?string
    {
        $statement = $this->db->prepare('SELECT id FROM institution_employment_rules_revisions WHERE request_key=:key AND deleted_at IS NULL LIMIT 1');
        $statement->execute([':key'=>$requestKey]);
        $id = $statement->fetchColumn();
        return $id ? (string) $id : null;
    }

    public function create(string $table, array $data): string
    {
        $id = UuidHelper::generate();
        $data = ['id'=>$id] + $data;
        $fields = array_keys($data);
        $statement = $this->db->prepare('INSERT INTO ' . $table . '(' . implode(',', $fields) . ') VALUES(:' . implode(',:', $fields) . ')');
        $statement->execute(array_combine(array_map(static fn($field) => ':' . $field, $fields), array_values($data)));
        return $id;
    }

    public function update(string $table, string $id, array $data): void
    {
        $params = [':id'=>$id];
        $set = [];
        foreach ($data as $field=>$value) {
            $set[] = $field . '=:' . $field;
            $params[':' . $field] = $value;
        }
        $statement = $this->db->prepare('UPDATE ' . $table . ' SET ' . implode(',', $set) . ' WHERE id=:id');
        $statement->execute($params);
    }

    public function nextRevision(string $ruleId): int
    {
        $statement = $this->db->prepare('SELECT COALESCE(MAX(revision_no),0)+1 FROM institution_employment_rules_revisions WHERE rule_id=:id FOR UPDATE');
        $statement->execute([':id'=>$ruleId]);
        return (int) $statement->fetchColumn();
    }

    public function lockPublishedPeriods(string $ruleId, string $exceptId): array
    {
        return $this->rows(
            "SELECT id,effective_from,effective_to,status_code FROM institution_employment_rules_revisions
              WHERE rule_id=:rule AND id<>:id AND deleted_at IS NULL
                AND status_code IN ('SCHEDULED','EFFECTIVE','RETIRED') FOR UPDATE",
            [':rule'=>$ruleId, ':id'=>$exceptId]
        );
    }

    public function closeOpenPublishedPeriod(string $ruleId, string $exceptId, string $newStart, string $actor, string $now): void
    {
        $statement = $this->db->prepare(
            "UPDATE institution_employment_rules_revisions
                SET effective_to=DATE_SUB(:start,INTERVAL 1 DAY),updated_at=:now,updated_by=:actor
              WHERE rule_id=:rule AND id<>:id AND deleted_at IS NULL
                AND status_code IN ('SCHEDULED','EFFECTIVE') AND effective_from<:start_compare
                AND (effective_to IS NULL OR effective_to>=:start_end)"
        );
        $statement->execute([':start'=>$newStart, ':now'=>$now, ':actor'=>$actor, ':rule'=>$ruleId, ':id'=>$exceptId, ':start_compare'=>$newStart, ':start_end'=>$newStart]);
    }

    public function softDeleteRevision(string $id, string $actor): void
    {
        $statement = $this->db->prepare('UPDATE institution_employment_rules_revisions SET deleted_at=NOW(),deleted_by=:deleted_by,updated_at=NOW(),updated_by=:updated_by WHERE id=:id');
        $statement->execute([':deleted_by'=>$actor, ':updated_by'=>$actor, ':id'=>$id]);
    }

    public function code(string $group, string $code): bool
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM system_codes WHERE code_group=:group AND code=:code AND is_active=1');
        $statement->execute([':group'=>$group, ':code'=>$code]);
        return (bool) $statement->fetchColumn();
    }

    public function options(string $group): array
    {
        $statement = $this->db->prepare('SELECT code value,code_name label FROM system_codes WHERE code_group=:group AND is_active=1 ORDER BY sort_no');
        $statement->execute([':group'=>$group]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function rows(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function orderSql(array $query, array $sortMap): string
    {
        $order = $query['order'][0] ?? [];
        $index = isset($order['column']) ? (int) $order['column'] : -1;
        $key = (string) ($query['columns'][$index]['data'] ?? '');
        $expression = $sortMap[$key] ?? 'r.title';
        $direction = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        return $expression . ' ' . $direction;
    }
}
