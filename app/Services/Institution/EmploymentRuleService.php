<?php

namespace App\Services\Institution;

use App\Models\Institution\EmploymentRuleModel;
use App\Services\Approval\ApprovalWorkflowService;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class EmploymentRuleService
{
    public const DOCUMENT_TYPE = 'EMPLOYMENT_RULE_REVISION';

    private EmploymentRuleModel $model;
    private ApprovalWorkflowService $approval;
    private EmploymentRuleResolver $resolver;
    private LoggerInterface $logger;

    public function __construct(private PDO $db)
    {
        $this->model = new EmploymentRuleModel($db);
        $this->approval = new ApprovalWorkflowService($db);
        $this->resolver = new EmploymentRuleResolver($db);
        $this->logger = LoggerFactory::getLogger('service-institution-employment-rule');
    }

    public function list(array $query): array
    {
        $page = $this->model->page($query);
        return ['success'=>true, 'data'=>$page['rows'], 'draw'=>(int) ($query['draw'] ?? 0), 'recordsTotal'=>$page['total'], 'recordsFiltered'=>$page['total']];
    }

    public function detail(string $revisionId): array
    {
        $row = $this->model->detail($revisionId);
        if (!$row) throw new \InvalidArgumentException('규정 개정본을 찾을 수 없습니다.');
        return ['success'=>true, 'data'=>$row];
    }

    public function history(string $ruleId): array
    {
        return ['success'=>true, 'data'=>$this->model->history($ruleId)];
    }

    public function resolve(string $companyId, string $identity, string $baseDate): array
    {
        return ['success'=>true, 'data'=>$this->resolver->resolve($companyId, $identity, $baseDate)];
    }

    public function options(): array
    {
        return ['success'=>true, 'data'=>[
            'type'=>$this->model->options('EMPLOYMENT_RULE_TYPE'),
            'status'=>$this->model->options('EMPLOYMENT_RULE_STATUS'),
            'companies'=>$this->model->rows('SELECT id value,company_name_ko label FROM system_company ORDER BY company_name_ko'),
            'departments'=>$this->model->rows('SELECT id value,dept_name label FROM user_departments WHERE is_active=1 ORDER BY sort_no'),
        ]];
    }

    public function save(array $input): array
    {
        $requestKey = $this->required($input, 'request_key', '요청 키');
        if ($existing = $this->model->findRevisionByRequestKey($requestKey)) return $this->detail($existing);
        $actor = ActorHelper::user();
        $now = date('Y-m-d H:i:s');
        $revisionId = trim((string) ($input['id'] ?? ''));
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $before = $revisionId !== '' ? $this->model->detail($revisionId, true) : null;
            if ($revisionId !== '' && !$before) throw new \InvalidArgumentException('수정할 개정본을 찾을 수 없습니다.');
            if ($before && !in_array($before['status_code'], ['DRAFT','WITHDRAWN'], true)) {
                throw new \RuntimeException('결재 또는 시행된 개정본은 직접 수정할 수 없습니다.');
            }
            $this->validateDates($input);
            if (!$before) {
                $ruleId = $this->model->create('institution_employment_rules', [
                    'company_id'=>$this->required($input, 'company_id', '회사'),
                    'regulation_code'=>$this->required($input, 'regulation_code', '규정 코드'),
                    'regulation_type_code'=>$this->code($input, 'regulation_type_code', 'TYPE'),
                    'title'=>$this->required($input, 'regulation_title', '규정명'),
                    'description'=>$this->nullable($input, 'description'),
                    'owner_department_id'=>$this->nullable($input, 'owner_department_id'),
                    'is_active'=>1, 'request_key'=>$requestKey . '-RULE',
                    'created_at'=>$now, 'created_by'=>$actor, 'updated_at'=>$now, 'updated_by'=>$actor,
                ]);
                $revisionId = $this->createRevision($ruleId, 1, $input, $requestKey, $actor, $now);
            } else {
                $ruleId = (string) $before['rule_id'];
                $this->model->update('institution_employment_rules', $ruleId, [
                    'regulation_type_code'=>$this->code($input, 'regulation_type_code', 'TYPE'),
                    'title'=>$this->required($input, 'regulation_title', '규정명'),
                    'description'=>$this->nullable($input, 'description'),
                    'owner_department_id'=>$this->nullable($input, 'owner_department_id'),
                    'updated_at'=>$now, 'updated_by'=>$actor,
                ]);
                $this->model->update('institution_employment_rules_revisions', $revisionId, $this->revisionData($input, $actor, $now));
            }
            $this->audit($ruleId, $revisionId, $before ? 'UPDATE_DRAFT' : 'CREATE', $this->required($input, 'change_reason', '변경 사유'), $requestKey, $before, $actor, $now);
            if ($ownsTransaction) $this->db->commit();
            return $this->detail($revisionId);
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            $this->logOperationException($e);
            throw $e;
        }
    }

    public function revise(array $input): array
    {
        $sourceId = $this->required($input, 'id', '원본 개정본 ID');
        $requestKey = $this->required($input, 'request_key', '요청 키');
        if ($existing = $this->model->findRevisionByRequestKey($requestKey)) return $this->detail($existing);
        $actor = ActorHelper::user();
        $now = date('Y-m-d H:i:s');
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $source = $this->model->detail($sourceId, true);
            if (!$source || !in_array($source['status_code'], ['APPROVED','SCHEDULED','EFFECTIVE','RETIRED'], true)) {
                throw new \RuntimeException('승인 또는 시행된 개정본만 개정할 수 있습니다.');
            }
            $payload = array_replace($source, $input);
            $this->validateDates($payload);
            $newId = $this->createRevision((string) $source['rule_id'], $this->model->nextRevision((string) $source['rule_id']), $payload, $requestKey, $actor, $now);
            $this->audit((string) $source['rule_id'], $newId, 'REVISE', $this->required($payload, 'change_reason', '개정 사유'), $requestKey, $source, $actor, $now);
            if ($ownsTransaction) $this->db->commit();
            return $this->detail($newId);
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            $this->logOperationException($e);
            throw $e;
        }
    }

    public function submit(string $revisionId, string $userId): array
    {
        $actor = ActorHelper::user();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $revision = $this->model->detail($revisionId, true);
            if (!$revision || !in_array($revision['status_code'], ['DRAFT','WITHDRAWN'], true)) throw new \RuntimeException('결재 요청 가능한 개정본이 아닙니다.');
            $approval = $this->approval->submit(self::DOCUMENT_TYPE, $revisionId, $userId, $actor);
            $this->model->update('institution_employment_rules_revisions', $revisionId, [
                'status_code'=>'APPROVAL_PENDING', 'approval_request_id'=>$approval['request_id'],
                'updated_at'=>date('Y-m-d H:i:s'), 'updated_by'=>$actor,
            ]);
            if ($ownsTransaction) $this->db->commit();
            return ['success'=>true, 'data'=>$approval];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            $this->logOperationException($e);
            throw $e;
        }
    }

    public function withdraw(string $requestId, string $userId): array
    {
        $actor = ActorHelper::user();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $request = $this->approval->withdraw($requestId, self::DOCUMENT_TYPE, $userId, $actor);
            $this->model->update('institution_employment_rules_revisions', (string) $request['document_id'], [
                'status_code'=>'WITHDRAWN', 'approval_request_id'=>null,
                'updated_at'=>date('Y-m-d H:i:s'), 'updated_by'=>$actor,
            ]);
            if ($ownsTransaction) $this->db->commit();
            return ['success'=>true, 'data'=>$request];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            $this->logOperationException($e);
            throw $e;
        }
    }

    public function act(string $stepId, string $decision, ?string $comment, string $userId): array
    {
        $actor = ActorHelper::user();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $result = $this->approval->act($stepId, self::DOCUMENT_TYPE, $decision, $comment, $userId, $actor);
            $revisionId = (string) $result['request']['document_id'];
            $status = $result['state'] === 'APPROVED' ? 'APPROVED' : ($result['state'] === 'REJECTED' ? 'DRAFT' : 'APPROVAL_PENDING');
            $data = ['status_code'=>$status, 'updated_at'=>date('Y-m-d H:i:s'), 'updated_by'=>$actor];
            if ($status === 'APPROVED') {
                $data['approved_at'] = date('Y-m-d H:i:s');
                $data['approved_by'] = $actor;
            }
            $this->model->update('institution_employment_rules_revisions', $revisionId, $data);
            if ($ownsTransaction) $this->db->commit();
            return ['success'=>true, 'data'=>$result];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            $this->logOperationException($e);
            throw $e;
        }
    }

    public function activate(string $revisionId): array
    {
        $actor = ActorHelper::user();
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $revision = $this->model->detail($revisionId, true);
            if (!$revision || $revision['status_code'] !== 'APPROVED') throw new \RuntimeException('승인된 개정본만 시행할 수 있습니다.');
            $ruleId = (string) $revision['rule_id'];
            $this->model->lockPublishedPeriods($ruleId, $revisionId);
            $this->model->closeOpenPublishedPeriod($ruleId, $revisionId, (string) $revision['effective_from'], $actor, $now);
            $this->assertNoOverlap($ruleId, $revisionId, (string) $revision['effective_from'], $revision['effective_to']);
            $this->model->update('institution_employment_rules_revisions', $revisionId, [
                'status_code'=>(string) $revision['effective_from'] > $today ? 'SCHEDULED' : 'EFFECTIVE',
                'published_at'=>$now, 'published_by'=>$actor, 'updated_at'=>$now, 'updated_by'=>$actor,
            ]);
            $this->audit($ruleId, $revisionId, 'PUBLISH', '규정 시행 또는 시행예약', 'PUBLISH-' . $revisionId, $revision, $actor, $now);
            if ($ownsTransaction) $this->db->commit();
            return $this->detail($revisionId);
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            $this->logOperationException($e);
            throw $e;
        }
    }

    public function retire(string $revisionId, string $effectiveTo, string $reason): array
    {
        $actor = ActorHelper::user();
        $now = date('Y-m-d H:i:s');
        $this->date($effectiveTo, '폐지일');
        if (trim($reason) === '') throw new \InvalidArgumentException('폐지 사유를 입력해 주세요.');
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $revision = $this->model->detail($revisionId, true);
            if (!$revision || !in_array($revision['status_code'], ['SCHEDULED','EFFECTIVE'], true)) throw new \RuntimeException('시행 또는 시행예정 규정만 폐지할 수 있습니다.');
            if ($effectiveTo < $revision['effective_from']) throw new \InvalidArgumentException('폐지일은 시행일보다 빠를 수 없습니다.');
            $this->model->update('institution_employment_rules_revisions', $revisionId, [
                'effective_to'=>$effectiveTo, 'status_code'=>'RETIRED', 'updated_at'=>$now, 'updated_by'=>$actor,
            ]);
            $this->audit((string) $revision['rule_id'], $revisionId, 'RETIRE', $reason, 'RETIRE-' . $revisionId . '-' . $effectiveTo, $revision, $actor, $now);
            if ($ownsTransaction) $this->db->commit();
            return $this->detail($revisionId);
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            $this->logOperationException($e);
            throw $e;
        }
    }

    public function delete(string $revisionId): array
    {
        $actor = ActorHelper::user();
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $revision = $this->model->detail($revisionId, true);
            if (!$revision || !in_array($revision['status_code'], ['DRAFT','WITHDRAWN'], true)) throw new \RuntimeException('초안 또는 회수 상태의 개정본만 삭제할 수 있습니다.');
            $this->model->softDeleteRevision($revisionId, $actor);
            if ($ownsTransaction) $this->db->commit();
            return ['success'=>true, 'message'=>'삭제했습니다.'];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            $this->logOperationException($e);
            throw $e;
        }
    }

    private function createRevision(string $ruleId, int $number, array $input, string $requestKey, string $actor, string $now): string
    {
        return $this->model->create('institution_employment_rules_revisions', ['rule_id'=>$ruleId, 'revision_no'=>$number]
            + $this->revisionData($input, $actor, $now)
            + ['status_code'=>'DRAFT', 'request_key'=>$requestKey, 'created_at'=>$now, 'created_by'=>$actor]);
    }

    private function revisionData(array $input, string $actor, string $now): array
    {
        return [
            'title'=>$this->required($input, 'title', '개정본 제목'),
            'change_reason'=>$this->required($input, 'change_reason', '개정 사유'),
            'change_summary'=>$this->nullable($input, 'change_summary'),
            'revision_date'=>$this->date($this->required($input, 'revision_date', '제정·개정일'), '제정·개정일'),
            'content_text'=>$this->nullable($input, 'content_text'),
            'effective_from'=>$this->date($this->required($input, 'effective_from', '시행일'), '시행일'),
            'effective_to'=>$this->nullableDate($input, 'effective_to', '종료일'),
            'document_file_path'=>$this->nullable($input, 'document_file_path'),
            'document_file_name'=>$this->nullable($input, 'document_file_name'),
            'updated_at'=>$now, 'updated_by'=>$actor,
        ];
    }

    private function validateDates(array $input): void
    {
        $from = $this->date($this->required($input, 'effective_from', '시행일'), '시행일');
        $to = $this->nullableDate($input, 'effective_to', '종료일');
        if ($to !== null && $to < $from) throw new \InvalidArgumentException('종료일은 시행일보다 빠를 수 없습니다.');
    }

    private function assertNoOverlap(string $ruleId, string $revisionId, string $from, ?string $to): void
    {
        foreach ($this->model->lockPublishedPeriods($ruleId, $revisionId) as $period) {
            $otherTo = $period['effective_to'] ?: '9999-12-31';
            if ($from <= $otherTo && ($to ?: '9999-12-31') >= $period['effective_from']) {
                throw new \RuntimeException('동일 규정의 시행기간이 겹칩니다.');
            }
        }
    }

    private function audit(string $ruleId, string $revisionId, string $action, string $reason, string $requestKey, ?array $before, string $actor, string $now): void
    {
        $this->model->create('institution_employment_rules_audits', [
            'rule_id'=>$ruleId, 'revision_id'=>$revisionId, 'action_type_code'=>$action, 'source_type_code'=>'ADMIN',
            'reason'=>$reason, 'request_key'=>$requestKey . '-AUDIT',
            'before_data'=>$before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_data'=>json_encode($this->model->detail($revisionId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'processed_by'=>$actor, 'processed_at'=>$now,
        ]);
        $this->logger->info('취업규칙 업무 처리를 완료했습니다.', ['event_code' => 'EMPLOYMENT_RULE_' . $action, 'result' => 'SUCCESS', 'rule_id' => $ruleId, 'revision_id' => $revisionId, 'actor' => $actor]);
    }

    private function logOperationException(\Throwable $exception): void
    {
        $failed = $exception instanceof \PDOException;
        $level = $failed ? 'error' : 'warning';
        $this->logger->{$level}($failed ? '취업규칙 업무 처리에 실패했습니다.' : '취업규칙 업무 처리가 차단되었습니다.', [
            'event_code' => $failed ? 'EMPLOYMENT_RULE_FAILED' : 'EMPLOYMENT_RULE_BLOCKED',
            'result' => $failed ? 'FAILED' : 'BLOCKED',
            'error_code' => get_class($exception),
            'error' => $exception,
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
        if (!$this->model->code('EMPLOYMENT_RULE_' . $group, $value)) throw new \InvalidArgumentException($key . ' 값이 올바르지 않습니다.');
        return $value;
    }

    private function date(string $value, string $label): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new \InvalidArgumentException($label . '을(를) 확인해 주세요.');
        return $value;
    }

    private function nullableDate(array $input, string $key, string $label): ?string
    {
        $value = $this->nullable($input, $key);
        return $value === null ? null : $this->date($value, $label);
    }
}
