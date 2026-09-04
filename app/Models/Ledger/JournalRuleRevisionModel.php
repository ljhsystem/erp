<?php

namespace App\Models\Ledger;

use PDO;

class JournalRuleRevisionModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function lockRule(string $companyId, string $ruleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ledger_journal_rules WHERE company_id=:company_id AND id=:id FOR UPDATE');
        $stmt->execute([':company_id' => $companyId, ':id' => $ruleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertRevision(array $row): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO ledger_journal_rule_revisions
            (id,company_id,rule_id,revision_no,action_code,before_snapshot,after_snapshot,change_reason,policy_revision,created_at,created_by)
            VALUES (:id,:company_id,:rule_id,:revision_no,:action_code,:before_snapshot,:after_snapshot,:change_reason,:policy_revision,NOW(),:created_by)');
        $stmt->execute($row);
    }

    public function setRevisionNo(string $companyId, string $ruleId, int $revisionNo, string $actor): void
    {
        $stmt = $this->pdo->prepare('UPDATE ledger_journal_rules SET revision_no=:revision_no,updated_at=NOW(),updated_by=:actor WHERE company_id=:company_id AND id=:id');
        $stmt->execute([':revision_no' => $revisionNo, ':actor' => $actor, ':company_id' => $companyId, ':id' => $ruleId]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('분개규칙 Revision 번호를 갱신하지 못했습니다.');
        }
    }
}
