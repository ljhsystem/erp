<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Models\Institution\BusinessIncomeModel;
use App\Models\Ledger\EvidenceLinkModel;
use App\Services\Ledger\TransactionCrudService;
use Core\Helpers\UuidHelper;
use PDO;

final class BusinessIncomeTransactionGenerationService
{
    public const EVIDENCE_TYPE = 'BUSINESS_INCOME_REPORT';

    private BusinessIncomeModel $model;

    public function __construct(private readonly PDO $db, private $failureInjector = null)
    {
        $this->model = new BusinessIncomeModel($db);
    }

    public function generate(string $documentId, string $approvalRequestId, string $actor): array
    {
        if (!$this->db->inTransaction()) {
            throw new \LogicException('사업소득 승인 후속처리는 바깥 DB Transaction 안에서만 실행해야 합니다.');
        }
        $header = $this->model->detail($documentId, true);
        if (!$header || empty($header['current_calculation_revision_id'])) {
            throw new \RuntimeException('승인할 사업소득 계산 Revision을 찾을 수 없습니다.');
        }
        $statement = $this->db->prepare('SELECT * FROM institution_business_income_closures WHERE business_income_id=:document_id AND approval_request_id=:approval_id LIMIT 1 FOR UPDATE');
        $statement->execute([':document_id' => $documentId, ':approval_id' => $approvalRequestId]);
        $closure = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($closure && (string) $closure['status'] === 'COMPLETED') {
            return $this->completedResult((string) $closure['id'], true);
        }
        if ($closure) {
            throw new \RuntimeException('동일 승인 Closure가 처리 중입니다.');
        }

        $closureId = UuidHelper::generate();
        $this->model->insert('institution_business_income_closures', [
            'id' => $closureId, 'business_income_id' => $documentId,
            'approval_request_id' => $approvalRequestId, 'status' => 'PROCESSING',
            'processing_token' => bin2hex(random_bytes(32)), 'started_at' => date('Y-m-d H:i:s'),
            'processed_by' => $actor,
        ]);

        foreach ($header['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $this->materializeItem($header, $group, $item, $approvalRequestId, $closureId, $actor);
            }
        }
        $this->checkpoint('before_closure_complete');
        $this->db->prepare("UPDATE institution_business_income_closures SET status='COMPLETED',completed_at=NOW() WHERE id=:id")
            ->execute([':id' => $closureId]);
        $this->model->update('institution_business_incomes', $documentId, [
            'document_status' => 'APPROVED', 'approval_status' => 'APPROVED',
            'payment_status' => 'CREATED', 'updated_by' => $actor,
        ]);
        return $this->completedResult($closureId, false);
    }

    private function materializeItem(array $header, array $group, array $item, string $approvalId, string $closureId, string $actor): void
    {
        $known = $this->db->prepare('SELECT id FROM institution_business_income_artifact_links WHERE business_income_item_id=:item_id LIMIT 1 FOR UPDATE');
        $known->execute([':item_id' => $item['id']]);
        if ($known->fetchColumn()) {
            throw new \RuntimeException('완료 전 Closure에 이미 생성 산출물이 존재합니다.');
        }
        $lines = $this->model->calculationLines((string) $header['current_calculation_revision_id'], (string) $item['id']);
        if ($lines === []) throw new \RuntimeException('승인 계산 Line이 없습니다.');

        $profile = json_decode((string) $item['recipient_tax_snapshot_json'], true) ?: [];
        $snapshot = ['source_business_income_id' => $header['id'], 'group' => $group, 'item' => $item, 'calculation_lines' => $lines];
        unset($snapshot['group']['items'], $snapshot['item']['recipient_tax_snapshot_json']);
        $sourceHash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        $businessKeyHash = hash('sha256', implode('|', [$header['id'], $group['id'], $item['id'], $approvalId]));
        $evidenceId = UuidHelper::generate();
        $now = date('Y-m-d H:i:s');
        $evidence = [
            'id' => $evidenceId, 'sort_no' => $item['sort_no'],
            'source_type' => 'INTERNAL_APPROVAL', 'import_type' => self::EVIDENCE_TYPE,
            'business_unit' => $group['business_unit'], 'transaction_direction' => 'EXPENSE', 'operation_type' => 'BUSINESS_INCOME',
            'external_key' => self::EVIDENCE_TYPE . ':' . $businessKeyHash, 'evidence_date' => $item['transaction_date'],
            'client_id' => $item['client_id'], 'employee_id' => null, 'project_id' => $group['project_id'], 'work_team_id' => $group['work_team_id'],
            'raw_client_name' => $profile['client_name'] ?? '', 'evidence_status' => 'COMPLETED',
            'raw_income_year_month' => $header['income_year_month'],
            'raw_withholding_date' => $header['withholding_date'],
            'raw_recipient_name' => $profile['client_name'] ?? '', 'raw_service_type_code' => $item['service_type_code'],
            'raw_service_description' => $item['service_description'], 'raw_business_unit' => $group['business_unit'],
            'raw_project_id' => $group['project_id'], 'raw_work_team_id' => $group['work_team_id'],
            'raw_gross_payment_amount' => $item['gross_payment_amount'], 'raw_income_tax_amount' => $item['income_tax_amount'],
            'raw_local_income_tax_amount' => $item['local_income_tax_amount'],
            'raw_total_deduction_amount' => $item['total_deduction_amount'], 'raw_net_payment_amount' => $item['net_payment_amount'],
            'source_business_income_id' => $header['id'], 'business_income_group_id' => $group['id'], 'business_income_item_id' => $item['id'],
            'approval_request_id' => $approvalId, 'calculation_revision_id' => $header['current_calculation_revision_id'],
            'approved_at' => $now, 'approved_by' => $actor,
            'snapshot_json' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'snapshot_version' => 1, 'source_hash' => $sourceHash, 'business_key_hash' => $businessKeyHash,
            'created_by' => $actor, 'updated_by' => $actor,
        ];
        (new BusinessIncomeEvidenceCanonicalPolicy())->assert($evidence);
        $this->model->insert('ledger_evidence_business_income', $evidence);
        foreach($item['work_lines'] as $workLine){
            $this->model->insert('ledger_evidence_business_income_work_lines',[
                'id'=>UuidHelper::generate(),'evidence_id'=>$evidenceId,'source_work_line_id'=>$workLine['id'],
                'raw_item_name'=>$workLine['item_name'],'raw_item_specification'=>$workLine['item_specification'],'raw_item_unit_name'=>$workLine['item_unit_name'],
                'raw_item_quantity'=>$workLine['item_quantity'],'raw_item_unit_price'=>$workLine['item_unit_price'],'raw_calculated_amount'=>$workLine['calculated_amount'],
                'raw_adjustment_amount'=>$workLine['adjustment_amount'],'raw_adjustment_reason'=>$workLine['adjustment_reason'],
                'raw_final_amount'=>$workLine['final_amount'],'raw_sort_no'=>$workLine['sort_no'],
                'source_hash'=>hash('sha256',json_encode($workLine,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION)),'created_by'=>$actor,
            ]);
        }
        foreach ($lines as $line) {
            $this->model->insert('ledger_evidence_business_income_raw_lines', [
                'id' => UuidHelper::generate(), 'evidence_id' => $evidenceId, 'source_calculation_line_id' => $line['id'],
                'calculation_revision_id' => $line['calculation_revision_id'], 'raw_line_type' => $line['line_type'],
                'raw_line_code' => $line['line_code'], 'raw_line_name' => $line['line_name'],
                'raw_applicability_status' => $line['applicability_status'], 'raw_calculation_base_amount' => $line['calculation_base_amount'],
                'raw_applied_rate' => $line['applied_rate'], 'raw_amount_before_rounding' => $line['amount_before_rounding'],
                'raw_rounding_method' => $line['rounding_method'], 'raw_rounding_unit' => $line['rounding_unit'],
                'raw_calculated_amount' => $line['calculated_amount'], 'raw_statutory_standard_revision_id' => $line['statutory_standard_revision_id'],
                'raw_sort_no' => $line['sort_no'], 'source_hash' => hash('sha256', json_encode($line, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)),
                'created_by' => $actor,
            ]);
            $this->checkpoint('after_raw_line');
        }
        $this->checkpoint('after_evidence');

        $result = (new TransactionCrudService($this->db, $this->failureInjector))->save($this->transactionPayload($header, $group, $item, $lines, $profile));
        if (empty($result['success'])) throw new \RuntimeException((string) ($result['message'] ?? '거래 생성에 실패했습니다.'));
        $transactionId = (string) $result['id'];
        (new EvidenceLinkModel($this->db))->upsertAutoTransactionEvidence(self::EVIDENCE_TYPE, $evidenceId, $transactionId);
        $this->checkpoint('after_link');
        $this->model->insert('institution_business_income_artifact_links', [
            'id' => UuidHelper::generate(), 'closure_id' => $closureId, 'business_income_id' => $header['id'],
            'business_income_item_id' => $item['id'], 'evidence_id' => $evidenceId, 'transaction_id' => $transactionId,
            'generation_status' => 'COMPLETED', 'result_hash' => hash('sha256', $evidenceId . '|' . $transactionId),
            'processed_by' => $actor, 'completed_at' => $now,
        ]);
        $this->checkpoint('after_artifact_registry');
    }

    private function transactionPayload(array $header, array $group, array $item, array $lines, array $profile): array
    {
        $settlements = [];
        foreach ($lines as $line) {
            if ((string) $line['line_type'] !== 'DEDUCTION' || (float) $line['calculated_amount'] <= 0) continue;
            $settlements[] = [
                'settlement_type' => $line['line_code'], 'amount_sign' => 'MINUS', 'amount' => $line['calculated_amount'],
                'currency' => 'KRW', 'settlement_description' => $line['line_name'],
                'meta_json' => ['source_business_income_id' => $header['id'], 'source_item_id' => $item['id'],
                    'source_line_id' => $line['id'], 'calculation_revision_id' => $header['current_calculation_revision_id']],
            ];
        }
        return [
            'business_unit' => $group['business_unit'], 'transaction_direction' => 'EXPENSE', 'operation_type' => 'BUSINESS_INCOME',
            'client_id' => $item['client_id'], 'project_id' => $group['project_id'], 'team_id' => $group['work_team_id'],
            'employee_id' => null, 'transaction_date' => $item['transaction_date'],
            'transaction_description' => $header['title'] . ' - ' . ($profile['client_name'] ?? '소득자'), 'status' => 'completed',
            'items' => array_map(static fn(array $workLine):array=>[
                'item_date'=>$item['transaction_date'],'item_name'=>$workLine['item_name'],'item_specification'=>$workLine['item_specification'],
                'item_quantity'=>$workLine['item_quantity'],'item_unit_name'=>$workLine['item_unit_name'],'item_unit_price'=>$workLine['item_unit_price'],
                'item_supply_amount'=>$workLine['final_amount'],'item_description'=>$workLine['adjustment_reason']?('증감: '.$workLine['adjustment_reason']):null,
            ],$item['work_lines']),
            'settlements' => $settlements,
        ];
    }

    private function completedResult(string $closureId, bool $replayed): array
    {
        $statement = $this->db->prepare('SELECT evidence_id,transaction_id FROM institution_business_income_artifact_links WHERE closure_id=:id AND generation_status=\'COMPLETED\' ORDER BY business_income_item_id');
        $statement->execute([':id' => $closureId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) throw new \RuntimeException('완료 Closure의 Evidence·Transaction 연결을 찾을 수 없습니다.');
        foreach($rows as $row){
            $audit=$this->db->prepare("SELECT e.raw_gross_payment_amount,e.raw_total_deduction_amount,e.raw_net_payment_amount,t.transaction_supply_amount,t.transaction_settlement_amount,t.transaction_final_amount,(SELECT COUNT(*) FROM ledger_transaction_items i WHERE i.transaction_id=t.id) item_count,(SELECT COUNT(*) FROM ledger_evidence_business_income_work_lines w WHERE w.evidence_id=e.id) work_line_count,(SELECT COALESCE(SUM(w.raw_final_amount),0) FROM ledger_evidence_business_income_work_lines w WHERE w.evidence_id=e.id) work_line_amount,(SELECT COALESCE(SUM(CASE WHEN s.amount_sign='MINUS' THEN -s.amount ELSE s.amount END),0) FROM ledger_transaction_settlements s WHERE s.transaction_id=t.id) signed_settlement,(SELECT COUNT(*) FROM ledger_evidence_links l WHERE l.evidence_type='BUSINESS_INCOME_REPORT' AND l.evidence_id=e.id AND l.target_type='TRANSACTION' AND l.target_id=t.id AND l.deleted_at IS NULL) link_count FROM ledger_evidence_business_income e JOIN ledger_transactions t ON t.id=:transaction_id WHERE e.id=:evidence_id");
            $audit->execute([':transaction_id'=>$row['transaction_id'],':evidence_id'=>$row['evidence_id']]);$amounts=$audit->fetch(PDO::FETCH_ASSOC);
            if(!$amounts||(int)$amounts['item_count']<1||(int)$amounts['item_count']!==(int)$amounts['work_line_count']||(int)$amounts['link_count']!==1
                ||round((float)$amounts['work_line_amount'],2)!==round((float)$amounts['raw_gross_payment_amount'],2)
                ||round((float)$amounts['raw_gross_payment_amount']-(float)$amounts['raw_total_deduction_amount'],2)!==round((float)$amounts['raw_net_payment_amount'],2)
                ||round((float)$amounts['raw_gross_payment_amount'],2)!==round((float)$amounts['transaction_supply_amount'],2)
                ||round(-(float)$amounts['raw_total_deduction_amount'],2)!==round((float)$amounts['signed_settlement'],2)
                ||round((float)$amounts['transaction_supply_amount']+(float)$amounts['signed_settlement'],2)!==round((float)$amounts['transaction_final_amount'],2)
                ||round((float)$amounts['raw_net_payment_amount'],2)!==round((float)$amounts['transaction_final_amount'],2)){
                throw new \RuntimeException('완료 Closure의 Evidence·Transaction·Settlement 대사가 일치하지 않습니다.');
            }
        }
        return ['status' => $replayed ? 'ALREADY_PROCESSED' : 'PROCESSED', 'closure_id' => $closureId,
            'evidence_ids' => array_column($rows, 'evidence_id'), 'transaction_ids' => array_column($rows, 'transaction_id')];
    }

    private function checkpoint(string $name): void
    {
        if ($this->failureInjector !== null) ($this->failureInjector)('business_income_closure.' . $name);
    }
}
