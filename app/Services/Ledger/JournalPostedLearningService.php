<?php

namespace App\Services\Ledger;

use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;
use PDOException;

class JournalPostedLearningService
{
    private JournalLearningPolicyService $policyService;

    public function __construct(private readonly PDO $pdo)
    {
        $this->policyService = new JournalLearningPolicyService($pdo);
    }

    public function record(string $companyId, string $voucherId, string $postingIdentity): array
    {
        $voucher = $this->voucher($voucherId);
        if ($voucher === null || !in_array(strtoupper((string) $voucher['status']), ['POSTED','CLOSED'], true) || (int) ($voucher['is_reversal'] ?? 0) === 1) {
            return ['created_ids' => [], 'ignored' => true];
        }
        $policySnapshot = $this->policyService->snapshot($companyId);
        $refs = $this->sourceRefs($companyId, $voucherId);
        $created = [];
        foreach ($refs as $ref) {
            $eventKey = hash('sha256', implode('|', ['POSTED_CONFIRMATION',$voucherId,$ref['voucher_line_id'],$ref['id'],$postingIdentity]));
            $eventId = UuidHelper::generate();
            try {
                $stmt = $this->pdo->prepare('INSERT INTO ledger_journal_learning_events
                    (id,company_id,event_type,event_key,learning_status,decision_code,voucher_id,voucher_line_id,voucher_line_source_ref_id,
                     transaction_id,business_unit,transaction_direction,import_type,line_no,line_type,final_line_type,final_account_id,
                     final_amount,recommend_source,journal_rule_id,is_user_modified,source_payload,policy_revision,policy_snapshot,created_at,created_by)
                    VALUES (:id,:company_id,\'POSTED_CONFIRMATION\',:event_key,\'PENDING\',NULL,:voucher_id,:voucher_line_id,:source_ref_id,
                            NULL,NULL,NULL,:import_type,0,:line_type,:line_type,:account_id,:amount,:recommend_source,:rule_id,0,:source_payload,
                            :policy_revision,:policy_snapshot,NOW(),:actor)');
                $stmt->execute([
                    ':id' => $eventId, ':company_id' => $companyId, ':event_key' => $eventKey, ':voucher_id' => $voucherId,
                    ':voucher_line_id' => $ref['voucher_line_id'], ':source_ref_id' => $ref['id'], ':import_type' => $ref['evidence_type'],
                    ':line_type' => $ref['debit_credit'], ':account_id' => $ref['account_id'], ':amount' => $ref['allocated_amount'],
                    ':recommend_source' => $ref['recommendation_source_code'], ':rule_id' => $ref['journal_rule_id'],
                    ':source_payload' => json_encode(['source_ref_key' => $ref['source_ref_key']], JSON_THROW_ON_ERROR),
                    ':policy_revision' => $policySnapshot['policy_revision'],
                    ':policy_snapshot' => json_encode($policySnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ':actor' => ActorHelper::system('JOURNAL_POSTED_LEARNING'),
                ]);
                $created[] = $eventId;
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }
            }
        }
        return ['created_ids' => $created, 'event_count' => count($refs), 'ignored' => false];
    }

    private function voucher(string $voucherId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id,status,is_reversal FROM ledger_vouchers WHERE id=:id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $voucherId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function sourceRefs(string $companyId, string $voucherId): array
    {
        $stmt = $this->pdo->prepare('SELECT ref.*,line.account_id FROM ledger_voucher_line_source_refs ref INNER JOIN ledger_voucher_lines line ON line.id=ref.voucher_line_id WHERE ref.company_id=:company_id AND ref.voucher_id=:voucher_id ORDER BY ref.id');
        $stmt->execute([':company_id' => $companyId, ':voucher_id' => $voucherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
