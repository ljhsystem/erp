<?php

namespace App\Services\Ledger;

use App\Models\Ledger\JournalRuleRevisionModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class JournalRuleRevisionService
{
    private JournalRuleRevisionModel $model;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new JournalRuleRevisionModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger-journal-rule-revision');
    }

    public function change(string $companyId, string $ruleId, string $actionCode, callable $mutator, ?string $reason = null, ?int $policyRevision = null): array
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $before = $this->model->lockRule($companyId, $ruleId);
            if ($before === null) {
                throw new \RuntimeException('분개규칙을 찾을 수 없습니다.');
            }
            $nextRevision = (int) ($before['revision_no'] ?? 0) + 1;
            $mutator($before, $nextRevision);
            $after = $this->model->lockRule($companyId, $ruleId);
            if ($after === null) {
                throw new \RuntimeException('변경된 분개규칙을 다시 조회하지 못했습니다.');
            }
            $actor = ActorHelper::user();
            $after['revision_no'] = $nextRevision;
            $this->model->insertRevision([
                ':id' => UuidHelper::generate(),
                ':company_id' => $companyId,
                ':rule_id' => $ruleId,
                ':revision_no' => $nextRevision,
                ':action_code' => strtoupper($actionCode),
                ':before_snapshot' => $this->snapshot($before),
                ':after_snapshot' => $this->snapshot($after),
                ':change_reason' => $reason,
                ':policy_revision' => $policyRevision,
                ':created_by' => $actor,
            ]);
            $this->model->setRevisionNo($companyId, $ruleId, $nextRevision, $actor);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            $this->logger->warning('분개규칙 Revision이 변경되었습니다.',['event_code'=>'JOURNAL_RULE_REVISION_CHANGED','result'=>'SUCCESS','service'=>self::class,'action'=>strtolower($actionCode),'actor'=>$actor,'target_id'=>$ruleId,'company_id'=>$companyId,'revision_no'=>$nextRevision]);
            return $after;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('분개규칙 Revision 변경에 실패했습니다.',['event_code'=>'JOURNAL_RULE_REVISION_CHANGE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>strtolower($actionCode),'actor'=>ActorHelper::user(),'target_id'=>$ruleId,'company_id'=>$companyId,'error_code'=>get_class($exception),'error'=>$exception]);
            throw $exception;
        }
    }

    private function snapshot(array $rule): string
    {
        ksort($rule);
        return json_encode($rule, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
