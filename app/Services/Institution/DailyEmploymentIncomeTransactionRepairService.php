<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Repositories\Ledger\TransactionProjectionRepairRepository;
use App\Models\Ledger\TransactionSettlementModel;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

final class DailyEmploymentIncomeTransactionRepairService
{
    public const APPROVED_TRANSACTION_ID='2d315f38-bfa7-4ca6-8d6d-fb9bbaa50b7c';
    public const APPROVED_EVIDENCE_ID='0f07686f-fb23-4939-8a67-6b7860f192f3';
    private TransactionProjectionRepairRepository $repairs;
    private LoggerInterface $logger;

    public function __construct(private PDO $db)
    {
        $this->repairs=new TransactionProjectionRepairRepository($db);
        $this->logger=LoggerFactory::getLogger('service-institution-daily-employment-income-repair');
    }

    public function dryRun(string $transactionId,string $evidenceId,string $reason): array
    {
        $this->assertInput($transactionId,$evidenceId,$reason);
        $state=$this->loadState($transactionId,$evidenceId,false);
        $target=$this->target($state);
        $requestKey=$this->requestKey($state,$target);
        $audit=$this->repairs->findCompletedByRequestKey($requestKey);
        if ($audit !== null) {
            if (!$this->isTargetState($state,$target)) throw new \RuntimeException('REPAIR_STATE_DIVERGED');
            return $this->result('ALREADY_REPAIRED',$state,$target,0);
        }
        $this->assertPreflight($state,$target);
        return $this->result('READY',$state,$target,0);
    }

    public function execute(string $transactionId,string $evidenceId,string $reason,string $actor): array
    {
        $this->assertInput($transactionId,$evidenceId,$reason);
        if ($this->db->inTransaction()) throw new \RuntimeException('REPAIR_TRANSACTION_ALREADY_ACTIVE');
        $this->db->beginTransaction();
        try {
            $state=$this->loadState($transactionId,$evidenceId,true);
            $target=$this->target($state);
            $requestKey=$this->requestKey($state,$target);
            $audit=$this->repairs->findCompletedByRequestKey($requestKey);
            if ($audit !== null) {
                if (!$this->isTargetState($state,$target)) throw new \RuntimeException('REPAIR_STATE_DIVERGED');
                $this->db->rollBack();
                $this->logger->info('일용근로소득 거래 정정은 이미 완료된 상태입니다.',['event_code'=>'DAILY_INCOME_TRANSACTION_REPAIR_ALREADY_COMPLETED','result'=>'SUCCESS','transaction_id'=>$transactionId,'evidence_id'=>$evidenceId]);
                return $this->result('ALREADY_REPAIRED',$state,$target,0);
            }
            $this->assertPreflight($state,$target);
            $before=$this->snapshot($state);
            $now=(new \DateTimeImmutable('now'))->format('Y-m-d H:i:s.u');
            $item=$state['items'][0];
            $stmt=$this->db->prepare('UPDATE ledger_transactions SET operation_type=:operation_type,transaction_supply_amount=:supply,transaction_settlement_amount=:settlement,transaction_final_amount=:final,updated_at=:now,updated_by=:actor WHERE id=:id');
            $stmt->execute([':operation_type'=>'DAILY_WORKER',':supply'=>$target['gross'],':settlement'=>-$target['deduction'],':final'=>$target['net'],':now'=>$now,':actor'=>$actor,':id'=>$transactionId]);
            if ($stmt->rowCount() !== 1) throw new \RuntimeException('TRANSACTION_UPDATE_FAILED');
            $stmt=$this->db->prepare('UPDATE ledger_transaction_items SET item_unit_price=:unit_price,item_supply_amount=:supply_amount,updated_at=:now,updated_by=:actor WHERE id=:id AND transaction_id=:transaction_id');
            $stmt->execute([':unit_price'=>$target['gross'],':supply_amount'=>$target['gross'],':now'=>$now,':actor'=>$actor,':id'=>$item['id'],':transaction_id'=>$transactionId]);
            if ($stmt->rowCount() !== 1) throw new \RuntimeException('TRANSACTION_ITEM_UPDATE_FAILED');
            $settlementId=$this->uuid();
            $sortNo=(int)$this->db->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM ledger_transaction_settlements')->fetchColumn();
            $meta=json_encode(['source_calculation_line_id'=>$target['source_line_id'],'calculation_revision_id'=>$state['evidence']['calculation_revision_id'],'source_hash'=>$state['evidence']['source_hash']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            $settlementModel=new TransactionSettlementModel($this->db);
            $inserted=$settlementModel->insert(['id'=>$settlementId,'sort_no'=>$sortNo,'transaction_id'=>$transactionId,'transaction_item_id'=>$item['id'],'settlement_type'=>'EMPLOYMENT_INSURANCE','amount_sign'=>'MINUS','amount'=>$target['deduction'],'currency'=>'KRW','settlement_description'=>'고용보험 근로자부담','meta_json'=>$meta,'created_at'=>$now,'created_by'=>$actor,'updated_at'=>$now,'updated_by'=>$actor]);
            if (!$inserted) throw new \RuntimeException('TRANSACTION_SETTLEMENT_INSERT_FAILED');
            $afterState=$this->loadState($transactionId,$evidenceId,true);
            if (!$this->isTargetState($afterState,$target)) throw new \RuntimeException('REPAIR_POST_VERIFY_FAILED');
            $after=$this->snapshot($afterState);
            $this->repairs->insertCompleted([
                'id'=>$this->uuid(),'request_key'=>$requestKey,'transaction_id'=>$transactionId,'evidence_id'=>$evidenceId,
                'approval_request_id'=>$state['evidence']['approval_request_id'],'source_revision_id'=>$state['evidence']['calculation_revision_id'],
                'repair_type'=>'DAILY_INCOME_TRANSACTION_PROJECTION','reason_code'=>'APPROVAL_PROJECTION_CORRECTION','reason_text'=>$reason,
                'source_hash'=>$state['evidence']['source_hash'],'before_snapshot'=>$this->json($before),'after_snapshot'=>$this->json($after),
                'changed_fields_json'=>$this->json(['transaction.operation_type','transaction.transaction_supply_amount','transaction.transaction_settlement_amount','item.item_unit_price','item.item_supply_amount','settlement.insert']),
                'result_status'=>'COMPLETED','repaired_by'=>$actor,'repaired_at'=>$now,'created_at'=>$now,
            ]);
            $this->db->commit();
            $this->logger->info('일용근로소득 거래 정정을 완료했습니다.',['event_code'=>'DAILY_INCOME_TRANSACTION_REPAIR_COMPLETED','result'=>'SUCCESS','transaction_id'=>$transactionId,'evidence_id'=>$evidenceId,'changed_row_count'=>4]);
            return $this->result('COMPLETED',$afterState,$target,4);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $level=$e instanceof \PDOException?'error':'warning';
            $this->logger->{$level}('일용근로소득 거래 정정이 완료되지 않았습니다.',['event_code'=>'DAILY_INCOME_TRANSACTION_REPAIR_FAILED','result'=>$level==='error'?'FAILED':'BLOCKED','transaction_id'=>$transactionId,'evidence_id'=>$evidenceId,'error_code'=>get_class($e),'error'=>$e]);
            throw $e;
        }
    }

    private function loadState(string $transactionId,string $evidenceId,bool $lock): array
    {
        $suffix=$lock?' FOR UPDATE':'';
        $one=fn(string $sql,array $params)=>$this->fetchOne($sql.$suffix,$params);
        $all=fn(string $sql,array $params)=>$this->fetchAll($sql.$suffix,$params);
        $transaction=$one('SELECT * FROM ledger_transactions WHERE id=:id',[':id'=>$transactionId]);
        $evidence=$one('SELECT * FROM ledger_evidence_daily_employment_income WHERE id=:id',[':id'=>$evidenceId]);
        if (!$transaction || !$evidence) throw new \RuntimeException('REPAIR_TARGET_NOT_FOUND');
        $items=$all('SELECT * FROM ledger_transaction_items WHERE transaction_id=:id ORDER BY sort_no,id',[':id'=>$transactionId]);
        $settlements=$all('SELECT * FROM ledger_transaction_settlements WHERE transaction_id=:id ORDER BY sort_no,id',[':id'=>$transactionId]);
        $links=$all("SELECT * FROM ledger_evidence_links WHERE evidence_id=:evidence_id AND target_type='TRANSACTION' AND target_id=:transaction_id AND deleted_at IS NULL ORDER BY id",[':evidence_id'=>$evidenceId,':transaction_id'=>$transactionId]);
        $rawLines=$all('SELECT * FROM ledger_evidence_daily_employment_income_lines WHERE evidence_id=:id ORDER BY sort_no,id',[':id'=>$evidenceId]);
        $deductions=array_values(array_filter($rawLines,fn(array $r)=>strtoupper((string)$r['line_type_code'])==='DEDUCTION'&&strtoupper((string)$r['line_code'])==='EMPLOYMENT_INSURANCE'&&strtoupper((string)$r['application_status_code'])==='APPLICABLE'&&(float)$r['raw_final_amount']>0));
        $downstream=$this->downstreamCount($transactionId);
        return compact('transaction','evidence','items','settlements','links','rawLines','deductions','downstream');
    }

    private function target(array $state): array
    {
        $e=$state['evidence'];
        return ['gross'=>(float)$e['raw_gross_payment_amount'],'deduction'=>(float)$e['raw_worker_deduction_amount'],'net'=>(float)$e['raw_net_payment_amount'],'burden'=>(float)$e['raw_employer_burden_amount'],'source_line_id'=>(string)($state['deductions'][0]['source_calculation_line_id']??'')];
    }

    private function assertPreflight(array $s,array $t): void
    {
        $tr=$s['transaction'];
        $ok=count($s['items'])===1 && count($s['settlements'])===0 && count($s['links'])===1 && count($s['rawLines'])===35 && count($s['deductions'])===1 && $s['downstream']===0
            && $tr['operation_type']==='PAYROLL' && $tr['transaction_direction']==='EXPENSE'
            && $this->money($tr['transaction_final_amount'])===$this->money($t['net']) && $this->money($s['items'][0]['item_supply_amount'])===45000000
            && $this->money($t['gross'])===45294000 && $this->money($t['deduction'])===294000 && $this->money($t['net'])===45000000 && $this->money($t['burden'])===2082000
            && $this->money($t['gross']-$t['deduction'])===$this->money($t['net']) && $t['source_line_id']!=='';
        if (!$ok) throw new \RuntimeException('REPAIR_PREFLIGHT_MISMATCH');
    }

    private function isTargetState(array $s,array $t): bool
    {
        return $s['transaction']['operation_type']==='DAILY_WORKER' && $s['transaction']['transaction_direction']==='EXPENSE'
            && $this->money($s['transaction']['transaction_supply_amount'])===$this->money($t['gross'])
            && $this->money($s['transaction']['transaction_settlement_amount'])===-$this->money($t['deduction'])
            && count($s['items'])===1 && $this->money($s['items'][0]['item_unit_price'])===$this->money($t['gross']) && $this->money($s['items'][0]['item_supply_amount'])===$this->money($t['gross'])
            && count($s['settlements'])===1 && $s['settlements'][0]['settlement_type']==='EMPLOYMENT_INSURANCE' && $s['settlements'][0]['amount_sign']==='MINUS'
            && $this->money($s['settlements'][0]['amount'])===$this->money($t['deduction']) && (string)$s['settlements'][0]['transaction_item_id']===(string)$s['items'][0]['id']
            && $this->money($s['transaction']['transaction_final_amount'])===$this->money($t['net']) && count($s['links'])===1 && count($s['rawLines'])===35 && $s['downstream']===0;
    }

    private function snapshot(array $s): array
    {
        $pick=static fn(array $r,array $keys): array=>array_intersect_key($r,array_flip($keys));
        return ['transaction'=>$pick($s['transaction'],['id','operation_type','transaction_direction','status','transaction_final_amount']),
            'items'=>array_map(fn($r)=>$pick($r,['id','sort_no','item_unit_price','item_supply_amount']),$s['items']),
            'settlements'=>array_map(fn($r)=>$pick($r,['id','sort_no','settlement_type','amount_sign','amount']),$s['settlements']),
            'evidence_link_id'=>$s['links'][0]['id']??null,'source_revision_id'=>$s['evidence']['calculation_revision_id'],'source_hash'=>$s['evidence']['source_hash']];
    }

    private function requestKey(array $s,array $t): string { return hash('sha256',$this->json(['repair_type'=>'DAILY_INCOME_TRANSACTION_PROJECTION','transaction_id'=>$s['transaction']['id'],'evidence_id'=>$s['evidence']['id'],'source_revision_id'=>$s['evidence']['calculation_revision_id'],'source_hash'=>$s['evidence']['source_hash'],'operation_type'=>'DAILY_WORKER','item_amount'=>$t['gross'],'settlement'=>['EMPLOYMENT_INSURANCE','MINUS',$t['deduction']]])); }
    private function downstreamCount(string $id): int
    {
        $refs=$this->fetchAll("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN('transaction_id','source_transaction_id','target_transaction_id','ledger_transaction_id') AND TABLE_NAME NOT IN('ledger_transactions','ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_links','institution_daily_employment_income_accounting_links','ledger_transaction_projection_repairs')",[]);
        $count=0;
        foreach($refs as $r){$table=str_replace('`','',(string)$r['TABLE_NAME']);$column=str_replace('`','',(string)$r['COLUMN_NAME']);$stmt=$this->db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}`=:id");$stmt->execute([':id'=>$id]);$count+=(int)$stmt->fetchColumn();}
        return $count;
    }
    private function assertInput(string $t,string $e,string $r): void { if($t!==self::APPROVED_TRANSACTION_ID||$e!==self::APPROVED_EVIDENCE_ID) throw new \RuntimeException('UNAPPROVED_REPAIR_TARGET'); if(trim($r)==='') throw new \RuntimeException('REPAIR_REASON_REQUIRED'); }
    private function result(string $status,array $s,array $t,int $changes): array { return ['status'=>$status,'database'=>(string)$this->db->query('SELECT DATABASE()')->fetchColumn(),'transaction_id'=>$s['transaction']['id'],'evidence_id'=>$s['evidence']['id'],'item_id'=>$s['items'][0]['id']??null,'item_count'=>count($s['items']),'settlement_count'=>count($s['settlements']),'evidence_link_count'=>count($s['links']),'raw_line_count'=>count($s['rawLines']),'repair_audit_count'=>count($this->repairs->historyByTransactionId((string)$s['transaction']['id'])),'downstream_reference_count'=>$s['downstream'],'operation_type'=>$s['transaction']['operation_type'],'item_amount'=>(float)($s['items'][0]['item_supply_amount']??0),'signed_settlement_amount'=>array_sum(array_map(fn($r)=>$r['amount_sign']==='MINUS'?-(float)$r['amount']:(float)$r['amount'],$s['settlements'])),'final_amount'=>(float)$s['transaction']['transaction_final_amount'],'target_item_amount'=>$t['gross'],'target_signed_settlement'=>-$t['deduction'],'target_final_amount'=>$t['net'],'changed_row_count'=>$changes]; }
    private function fetchOne(string $sql,array $params): ?array {$s=$this->db->prepare($sql);$s->execute($params);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
    private function fetchAll(string $sql,array $params): array {$s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    private function money(mixed $v): int {return (int)round((float)$v*100);}
    private function json(mixed $v): string {return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION);}
    private function uuid(): string {$d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
