<?php
namespace App\Services\Institution;

use App\Models\Institution\EmploymentRuleModel;
use App\Services\Approval\ApprovalWorkflowService;
use Core\Helpers\ActorHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EmploymentRuleService
{
    public const DOCUMENT_TYPE = 'EMPLOYMENT_RULE_REVISION';

    private EmploymentRuleModel $model;
    private ApprovalWorkflowService $approval;

    public function __construct(private PDO $db)
    {
        $this->model = new EmploymentRuleModel($db);
        $this->approval = new ApprovalWorkflowService($db);
    }

    public function list(array $query): array
    {
        $page = $this->model->page($query);
        return [
            'success' => true,
            'data' => $page['rows'],
            'draw' => (int) ($query['draw'] ?? 0),
            'recordsTotal' => $page['total'],
            'recordsFiltered' => $page['total'],
        ];
    }

    public function detail(string $revisionId): array
    {
        $row = $this->model->detail($revisionId);
        if (!$row) {
            throw new \InvalidArgumentException('규정 개정본을 찾을 수 없습니다.');
        }
        return ['success' => true, 'data' => $row];
    }

    public function history(string $ruleId): array
    {
        return ['success' => true, 'data' => $this->model->history($ruleId)];
    }

    public function options(): array
    {
        $options = [];
        foreach (['TYPE', 'STATUS', 'POLICY', 'VALUE_TYPE', 'SCOPE_TYPE', 'OPERATOR', 'UNIT'] as $group) {
            $options[strtolower($group)] = $this->model->options('EMPLOYMENT_RULE_' . $group);
        }
        $options['companies'] = $this->model->rows('SELECT id value, company_name_ko label FROM system_company ORDER BY company_name_ko');
        $options['departments'] = $this->model->rows('SELECT id value, dept_name label FROM user_departments WHERE is_active=1 ORDER BY sort_no');
        $options['positions'] = $this->model->rows('SELECT id value, position_name label FROM user_positions WHERE is_active=1 ORDER BY sort_no');
        $options['jobs'] = $this->model->rows('SELECT id value, job_name label FROM institution_job_assignments_jobs WHERE is_active=1 ORDER BY sort_no');
        $options['employment_categories'] = $this->model->options('EMPLOYMENT_CATEGORY');
        return ['success' => true, 'data' => $options];
    }

    public function save(array $input): array
    {
        $requestKey = $this->required($input, 'request_key', '요청 키');
        $existing = $this->model->findRevisionByRequestKey($requestKey);
        if ($existing) {
            return $this->detail($existing);
        }

        $actor = ActorHelper::user();
        $now = date('Y-m-d H:i:s');
        $revisionId = trim((string) ($input['id'] ?? ''));
        $this->db->beginTransaction();
        try {
            $before = $revisionId !== '' ? $this->model->detail($revisionId, true) : null;
            if ($revisionId !== '' && !$before) {
                throw new \InvalidArgumentException('수정할 개정본을 찾을 수 없습니다.');
            }
            if ($before && !in_array($before['status_code'], ['DRAFT', 'WITHDRAWN'], true)) {
                throw new \RuntimeException('승인되었거나 시행된 개정본은 수정할 수 없습니다.');
            }

            if (!$before) {
                $ruleId = $this->model->create('institution_employment_rules', [
                    'company_id' => $this->required($input, 'company_id', '회사'),
                    'rule_code' => $this->required($input, 'rule_code', '규정 코드'),
                    'rule_type_code' => $this->code($input, 'rule_type_code', 'TYPE'),
                    'title' => $this->required($input, 'title', '규정명'),
                    'description' => $this->nullable($input, 'description'),
                    'owner_department_id' => $this->nullable($input, 'owner_department_id'),
                    'is_active' => 1,
                    'request_key' => $requestKey . '-RULE',
                    'created_at' => $now,
                    'created_by' => $actor,
                    'updated_at' => $now,
                    'updated_by' => $actor,
                ]);
                $revisionId = $this->createRevision($ruleId, 1, $input, $requestKey, $actor, $now);
            } else {
                $ruleId = (string) $before['rule_id'];
                $this->model->update('institution_employment_rules', $ruleId, [
                    'rule_type_code' => $this->code($input, 'rule_type_code', 'TYPE'),
                    'title' => $this->required($input, 'title', '규정명'),
                    'description' => $this->nullable($input, 'description'),
                    'owner_department_id' => $this->nullable($input, 'owner_department_id'),
                    'updated_at' => $now,
                    'updated_by' => $actor,
                ]);
                $this->model->update('institution_employment_rules_revisions', $revisionId, [
                    'revision_title' => $this->required($input, 'revision_title', '개정본 제목'),
                    'revision_reason' => $this->required($input, 'revision_reason', '개정 사유'),
                    'content_text' => $this->nullable($input, 'content_text'),
                    'effective_from' => $this->required($input, 'effective_from', '시행일'),
                    'effective_to' => $this->nullable($input, 'effective_to'),
                    'updated_at' => $now,
                    'updated_by' => $actor,
                ]);
                $this->model->deleteChildren($revisionId);
            }

            $this->saveChildren($revisionId, $input, $actor, $now);
            $this->audit($ruleId, $revisionId, $before ? 'UPDATE' : 'CREATE', $input, $requestKey, $before, $actor, $now);
            $this->db->commit();
            return $this->detail($revisionId);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function revise(array $input): array
    {
        $sourceId = $this->required($input, 'id', '원본 개정본 ID');
        $requestKey = $this->required($input, 'request_key', '요청 키');
        $existing = $this->model->findRevisionByRequestKey($requestKey);
        if ($existing) {
            return $this->detail($existing);
        }
        $actor = ActorHelper::user();
        $now = date('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $source = $this->model->detail($sourceId, true);
            if (!$source || !in_array($source['status_code'], ['APPROVED', 'EFFECTIVE', 'EXPIRED'], true)) {
                throw new \RuntimeException('승인된 개정본만 새 개정본으로 복제할 수 있습니다.');
            }
            $payload = array_replace($source, $input);
            $payload['revision_title'] = $this->required($input, 'revision_title', '개정본 제목');
            $payload['revision_reason'] = $this->required($input, 'revision_reason', '개정 사유');
            $payload['effective_from'] = $this->required($input, 'effective_from', '시행일');
            $newId = $this->createRevision(
                (string) $source['rule_id'],
                $this->model->nextRevision((string) $source['rule_id']),
                $payload,
                $requestKey,
                $actor,
                $now
            );
            $this->saveChildren($newId, $payload, $actor, $now);
            $this->audit((string) $source['rule_id'], $newId, 'REVISE', $payload, $requestKey, $source, $actor, $now);
            $this->db->commit();
            return $this->detail($newId);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function submit(string $revisionId, string $userId): array
    {
        $actor = ActorHelper::user();
        $this->db->beginTransaction();
        try {
            $revision = $this->model->detail($revisionId, true);
            if (!$revision || !in_array($revision['status_code'], ['DRAFT', 'WITHDRAWN'], true)) {
                throw new \RuntimeException('결재 요청 가능한 개정본이 아닙니다.');
            }
            if (empty($revision['items']) || empty($revision['scopes'])) {
                throw new \RuntimeException('정책 항목과 적용범위를 한 건 이상 등록해야 합니다.');
            }
            $approval = $this->approval->submit(self::DOCUMENT_TYPE, $revisionId, $userId, $actor);
            $this->model->update('institution_employment_rules_revisions', $revisionId, [
                'status_code' => 'APPROVAL_PENDING',
                'current_approval_request_id' => $approval['request_id'],
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ]);
            $this->db->commit();
            return ['success' => true, 'data' => $approval];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function withdraw(string $requestId, string $userId): array
    {
        $actor = ActorHelper::user();
        $this->db->beginTransaction();
        try {
            $request = $this->approval->withdraw($requestId, self::DOCUMENT_TYPE, $userId, $actor);
            $this->model->update('institution_employment_rules_revisions', (string) $request['document_id'], [
                'status_code' => 'WITHDRAWN',
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ]);
            $this->db->commit();
            return ['success' => true, 'data' => $request];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function act(string $stepId, string $decision, ?string $comment, string $userId): array
    {
        $actor = ActorHelper::user();
        $this->db->beginTransaction();
        try {
            $result = $this->approval->act($stepId, self::DOCUMENT_TYPE, $decision, $comment, $userId, $actor);
            $revisionId = (string) $result['request']['document_id'];
            $status = $result['state'] === 'APPROVED' ? 'APPROVED' : ($result['state'] === 'REJECTED' ? 'DRAFT' : 'APPROVAL_PENDING');
            $data = ['status_code' => $status, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $actor];
            if ($status === 'APPROVED') {
                $data['approved_at'] = date('Y-m-d H:i:s');
                $data['approved_by'] = $actor;
            }
            $this->model->update('institution_employment_rules_revisions', $revisionId, $data);
            $this->db->commit();
            return ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function activate(string $revisionId): array
    {
        $actor = ActorHelper::user();
        $now = date('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $revision = $this->model->detail($revisionId, true);
            if (!$revision || $revision['status_code'] !== 'APPROVED') {
                throw new \RuntimeException('승인된 개정본만 시행할 수 있습니다.');
            }
            $this->model->expireCurrentRevision((string) $revision['rule_id'], $revisionId, (string) $revision['effective_from'], $actor, $now);
            $this->model->update('institution_employment_rules_revisions', $revisionId, [
                'status_code' => 'EFFECTIVE',
                'published_at' => $now,
                'published_by' => $actor,
                'updated_at' => $now,
                'updated_by' => $actor,
            ]);
            $this->model->update('institution_employment_rules', (string) $revision['rule_id'], [
                'current_revision_id' => $revisionId,
                'updated_at' => $now,
                'updated_by' => $actor,
            ]);
            $this->db->commit();
            return $this->detail($revisionId);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(string $revisionId): array
    {
        $actor = ActorHelper::user();
        $revision = $this->model->detail($revisionId, true);
        if (!$revision || !in_array($revision['status_code'], ['DRAFT', 'WITHDRAWN'], true)) {
            throw new \RuntimeException('초안 또는 회수 상태의 개정본만 삭제할 수 있습니다.');
        }
        $this->model->softDeleteRevision($revisionId, $actor);
        return ['success' => true, 'message' => '삭제했습니다.'];
    }

    public function excel(array $query): void
    {
        $query['start'] = 0;
        $query['length'] = 100000;
        $rows = $this->model->page($query)['rows'];
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        ExcelValueFormatterHelper::writeTable(
            $sheet,
            $rows ? array_keys($rows[0]) : ['조회 결과'],
            $rows ? array_map('array_values', $rows) : [['없음']],
            'A1'
        );
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="employment-rules.xlsx"');
        (new Xlsx($book))->save('php://output');
    }

    private function createRevision(string $ruleId, int $number, array $input, string $requestKey, string $actor, string $now): string
    {
        return $this->model->create('institution_employment_rules_revisions', [
            'rule_id' => $ruleId,
            'revision_no' => $number,
            'revision_title' => $this->required($input, 'revision_title', '개정본 제목'),
            'revision_reason' => $this->required($input, 'revision_reason', '개정 사유'),
            'content_text' => $this->nullable($input, 'content_text'),
            'effective_from' => $this->required($input, 'effective_from', '시행일'),
            'effective_to' => $this->nullable($input, 'effective_to'),
            'status_code' => 'DRAFT',
            'request_key' => $requestKey,
            'created_at' => $now,
            'created_by' => $actor,
            'updated_at' => $now,
            'updated_by' => $actor,
        ]);
    }

    private function saveChildren(string $revisionId, array $input, string $actor, string $now): void
    {
        $items = (array) ($input['items'] ?? []);
        if (!$items) throw new \InvalidArgumentException('정책 항목을 한 건 이상 등록해 주세요.');
        foreach ($items as $index => $item) {
            $valueType = $this->code($item, 'value_type_code', 'VALUE_TYPE');
            $value = $item['value'] ?? $item['value_text'] ?? null;
            $typed = $this->typedValue($valueType, $value);
            $this->model->create('institution_employment_rules_items', array_merge([
                'revision_id' => $revisionId,
                'policy_code' => $this->code($item, 'policy_code', 'POLICY'),
                'value_type_code' => $valueType,
                'unit_code' => $this->optionalCode($item, 'unit_code', 'UNIT'),
                'operator_code' => $this->code($item, 'operator_code', 'OPERATOR'),
                'note' => $this->nullable($item, 'note'),
                'sort_no' => $index + 1,
                'created_at' => $now,
                'created_by' => $actor,
                'updated_at' => $now,
                'updated_by' => $actor,
            ], $typed));
        }
        $scopes = (array) ($input['scopes'] ?? []);
        if (!$scopes) throw new \InvalidArgumentException('적용범위를 한 건 이상 등록해 주세요.');
        foreach ($scopes as $scope) {
            $this->model->create('institution_employment_rules_scopes', [
                'revision_id' => $revisionId,
                'scope_type_code' => $this->code($scope, 'scope_type_code', 'SCOPE_TYPE'),
                'department_id' => $this->nullable($scope, 'department_id'),
                'position_id' => $this->nullable($scope, 'position_id'),
                'job_id' => $this->nullable($scope, 'job_id'),
                'employment_category_code' => $this->employmentCategory($scope),
                'created_at' => $now,
                'created_by' => $actor,
                'updated_at' => $now,
                'updated_by' => $actor,
            ]);
        }
    }

    private function typedValue(string $type, mixed $value): array
    {
        $columns = ['value_text' => null, 'value_number' => null, 'value_boolean' => null, 'value_date' => null, 'value_time' => null, 'value_minutes' => null, 'value_json' => null];
        if ($type === 'TEXT') $columns['value_text'] = trim((string) $value);
        elseif (in_array($type, ['NUMBER', 'PERCENT'], true)) {
            if (!is_numeric($value)) throw new \InvalidArgumentException('숫자 정책값을 확인해 주세요.');
            $columns['value_number'] = (string) $value;
        } elseif ($type === 'BOOLEAN') $columns['value_boolean'] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (int) $value;
        elseif ($type === 'DATE') $columns['value_date'] = trim((string) $value);
        elseif ($type === 'TIME') $columns['value_time'] = trim((string) $value);
        elseif ($type === 'MINUTES') {
            if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new \InvalidArgumentException('분 정책값은 정수여야 합니다.');
            $columns['value_minutes'] = (int) $value;
        } elseif ($type === 'JSON') {
            json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
            $columns['value_json'] = (string) $value;
        }
        if (!array_filter($columns, static fn($v) => $v !== null && $v !== '')) {
            throw new \InvalidArgumentException('정책값을 입력해 주세요.');
        }
        return $columns;
    }

    private function audit(string $ruleId, string $revisionId, string $action, array $input, string $requestKey, ?array $before, string $actor, string $now): void
    {
        $this->model->create('institution_employment_rules_audits', [
            'rule_id' => $ruleId,
            'revision_id' => $revisionId,
            'action_type_code' => $action,
            'source_type_code' => 'ADMIN',
            'reason' => $this->required($input, 'revision_reason', '개정 사유'),
            'request_key' => $requestKey . '-AUDIT',
            'before_data' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_data' => json_encode($this->model->detail($revisionId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'processed_by' => $actor,
            'processed_at' => $now,
        ]);
    }

    private function required(array $input, string $key, string $label): string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value === '') throw new \InvalidArgumentException($label . '은(는) 필수입니다.');
        return $value;
    }

    private function nullable(array $input, string $key): ?string
    {
        $value = trim((string) ($input[$key] ?? ''));
        return $value === '' ? null : $value;
    }

    private function code(array $input, string $key, string $group): string
    {
        $value = $this->required($input, $key, $key);
        if (!$this->model->code('EMPLOYMENT_RULE_' . $group, $value)) {
            throw new \InvalidArgumentException($key . ' 값이 올바르지 않습니다.');
        }
        return $value;
    }

    private function optionalCode(array $input, string $key, string $group): ?string
    {
        $value = $this->nullable($input, $key);
        if ($value !== null && !$this->model->code('EMPLOYMENT_RULE_' . $group, $value)) {
            throw new \InvalidArgumentException($key . ' 값이 올바르지 않습니다.');
        }
        return $value;
    }

    private function employmentCategory(array $input): ?string
    {
        $value = $this->nullable($input, 'employment_category_code');
        if ($value !== null && !$this->model->code('EMPLOYMENT_CATEGORY', $value)) {
            throw new \InvalidArgumentException('고용구분 값이 올바르지 않습니다.');
        }
        return $value;
    }
}
