<?php

namespace App\Models\Institution;

use Core\Helpers\ActorHelper;
use PDO;

class RegularEmploymentIncomeModel
{
    public function __construct(private readonly PDO $db) {}

    public function page(array $query): array
    {
        $where = ['h.deleted_at IS NULL'];
        $params = [];
        foreach (json_decode((string) ($query['filters'] ?? '[]'), true) ?: [] as $index => $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = trim((string) ($filter['value'] ?? ''));
            if ($value === '') continue;
            if ($field === 'income_year_month') { $where[] = 'h.income_year_month=:month'; $params[':month'] = $value; }
            elseif ($field === 'title') { $where[] = 'h.title LIKE :title'; $params[':title'] = '%' . $value . '%'; }
            elseif ($field === 'document_status') { $where[] = 'h.document_status=:status'; $params[':status'] = strtoupper($value); }
        }
        $search = trim((string) ($query['search']['value'] ?? ''));
        if ($search !== '') { $where[] = '(h.title LIKE :search OR h.income_year_month LIKE :search)'; $params[':search'] = '%' . $search . '%'; }
        $from = " FROM institution_regular_employment_incomes h
            LEFT JOIN ledger_evidence_salary_report ev ON ev.source_regular_employment_income_id=h.id AND ev.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx ON tx.evidence_type='PAYROLL_REPORT' AND tx.evidence_id=ev.id AND tx.target_type='TRANSACTION' AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx ON vx.evidence_type='PAYROLL_REPORT' AND vx.evidence_id=ev.id AND vx.target_type='VOUCHER' AND vx.deleted_at IS NULL
            WHERE " . implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(DISTINCT h.id)' . $from); $count->execute($params);
        $start=max(0,(int)($query['start']??0));$length=max(1,min(500,(int)($query['length']??100)));
        $sql="SELECT h.*,ev.id evidence_id,
            MAX(tx.target_id) transaction_id,MAX(vx.target_id) voucher_id
            {$from} GROUP BY h.id ORDER BY h.income_year_month DESC,h.sort_no DESC LIMIT {$start},{$length}";
        $stmt=$this->db->prepare($sql);$stmt->execute($params);
        $rows=ActorHelper::enrichActorNames($stmt->fetchAll(PDO::FETCH_ASSOC)?:[],['created_by_name'=>'created_by','updated_by_name'=>'updated_by','deleted_by_name'=>'deleted_by']);
        $total=(int)$count->fetchColumn();
        return ['rows'=>$rows,'total'=>$total,'filtered'=>$total];
    }

    public function find(string $id, bool $lock=false): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM institution_regular_employment_incomes WHERE id=:id AND deleted_at IS NULL LIMIT 1'.($lock?' FOR UPDATE':''));
        $stmt->execute([':id'=>$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
        return $row?ActorHelper::enrichActorNamesRow($row,['created_by_name'=>'created_by','updated_by_name'=>'updated_by']):null;
    }
    public function items(string $id, bool $lock=false): array
    {
        $stmt=$this->db->prepare('SELECT * FROM institution_regular_employment_income_items WHERE regular_employment_income_id=:id AND deleted_at IS NULL ORDER BY sort_no'.($lock?' FOR UPDATE':''));
        $stmt->execute([':id'=>$id]);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }
    public function itemsIncludingDeleted(string $id, bool $lock=false): array
    {
        $stmt=$this->db->prepare('SELECT * FROM institution_regular_employment_income_items WHERE regular_employment_income_id=:id ORDER BY sort_no,id'.($lock?' FOR UPDATE':''));
        $stmt->execute([':id'=>$id]);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }
    public function itemsByEmployeeIncludingDeleted(string $documentId,string $employeeId,bool $lock=false):array
    {
        $stmt=$this->db->prepare('SELECT * FROM institution_regular_employment_income_items WHERE regular_employment_income_id=:document_id AND employee_id=:employee_id ORDER BY deleted_at IS NOT NULL,id'.($lock?' FOR UPDATE':''));
        $stmt->execute([':document_id'=>$documentId,':employee_id'=>$employeeId]);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }
    public function findItemById(string $id, bool $lock=false): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM institution_regular_employment_income_items WHERE id=:id LIMIT 1'.($lock?' FOR UPDATE':''));
        $stmt->execute([':id'=>$id]);return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function lineItems(string $detailId): array
    {
        $stmt=$this->db->prepare('SELECT line_row.*,standard_row.effective_from AS standard_effective_from,standard_row.effective_to AS standard_effective_to
            FROM institution_regular_employment_income_line_items line_row
            LEFT JOIN system_statutory_standards standard_row ON standard_row.id=line_row.statutory_standard_id
            WHERE line_row.regular_employment_income_item_id=:id ORDER BY line_row.sort_no,line_row.id');
        $stmt->execute([':id'=>$detailId]);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }
    public function calculationBases(string $detailId): array
    {
        $stmt=$this->db->prepare('SELECT * FROM institution_regular_employment_income_calculation_bases WHERE regular_employment_income_item_id=:id ORDER BY basis_type_code,effective_from,id');
        $stmt->execute([':id'=>$detailId]);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }
    public function latestInsuranceOverrideLines(array $employeeIds,string $beforeMonth):array
    {
        if($employeeIds===[])return[];
        $placeholders=implode(',',array_fill(0,count($employeeIds),'?'));
        $stmt=$this->db->prepare("SELECT h.income_year_month,i.employee_id,l.*
            FROM institution_regular_employment_income_line_items l
            JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id AND i.deleted_at IS NULL
            JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id AND h.deleted_at IS NULL
            WHERE i.employee_id IN ($placeholders) AND h.income_year_month<?
              AND l.item_type_code='DEDUCTION'
              AND l.item_code IN ('NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE')
              AND (l.source_key LIKE 'INSURANCE_OVERRIDE|%' OR l.source_key LIKE 'INSURANCE_OVERRIDE_RESET|%'
                   OR (ABS(COALESCE(l.adjustment_amount,0))>=0.01 AND COALESCE(l.adjustment_reason,'')<>''))
            ORDER BY h.income_year_month DESC,l.created_at DESC,l.id DESC");
        $stmt->execute([...array_values($employeeIds),$beforeMonth]);$result=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as$row){$key=$row['employee_id'].':'.$row['item_code'];if(!isset($result[$key]))$result[$key]=$row;}
        return$result;
    }
    public function replaceLineItems(string $detailId,array $rows):void
    {
        $existing=[];
        foreach($this->lineItems($detailId) as $line){
            $existing[$line['item_type_code'].':'.$line['item_code']]=$line;
        }
        $this->db->prepare('DELETE FROM institution_regular_employment_income_line_items WHERE regular_employment_income_item_id=:id')->execute([':id'=>$detailId]);
        foreach($rows as$row){
            $previous=$existing[$row['item_type_code'].':'.$row['item_code']]??null;
            if($previous&&!array_key_exists('adjustment_amount',$row)&&(float)$previous['adjustment_amount']!==0.0){
                $row['adjustment_amount']=(float)$previous['adjustment_amount'];
                $row['final_amount']=round((float)$row['calculated_amount']+(float)$previous['adjustment_amount'],2);
                $row['adjustment_reason']=$previous['adjustment_reason'];
                $row['calculation_source_code']='MANUAL';
                $row['updated_at']=$previous['updated_at'];
                $row['updated_by']=$previous['updated_by'];
            }
            $this->insert('institution_regular_employment_income_line_items',$row);
        }
    }
    public function replaceCalculationBases(string $detailId,array $rows):void
    {
        $this->db->prepare('DELETE FROM institution_regular_employment_income_calculation_bases WHERE regular_employment_income_item_id=:id')->execute([':id'=>$detailId]);
        foreach($rows as$row)$this->insert('institution_regular_employment_income_calculation_bases',$row);
    }
    public function insertAudit(array $row):void{$this->insert('institution_regular_employment_income_audits',$row);}
    public function findAuditByRequestKey(string $documentId, string $requestKey): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM institution_regular_employment_income_audits
             WHERE regular_employment_income_id=:document_id AND request_key=:request_key
             ORDER BY acted_at,id LIMIT 1'
        );
        $statement->execute([':document_id' => $documentId, ':request_key' => $requestKey]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function accountingLinks(string $documentId,bool $lock=false):array
    {
        $stmt=$this->db->prepare('SELECT * FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=:id ORDER BY regular_employment_income_item_id'.($lock?' FOR UPDATE':''));
        $stmt->execute([':id'=>$documentId]);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }
    public function insertAccountingLink(array $row):void{$this->insert('institution_regular_employment_income_accounting_links',$row);}
    public function eligibleEmployees(string $month, array $contractIds): array
    {
        if ($contractIds === []) return [];
        $from=$month.'-01';$to=date('Y-m-t',strtotime($from));
        $contractKeys=[];$contractParams=[];foreach(array_values($contractIds)as$i=>$id){$key=':contract_'.$i;$contractKeys[]=$key;$contractParams[$key]=$id;}
        $stmt=$this->db->prepare("SELECT e.id employee_id,e.employee_name,e.rrn employee_identifier_snapshot,
                c.id employment_contract_id,c.project_id,d.dept_name department_name,
                COALESCE(NULLIF(c.job_title_snapshot,''),p.position_name) position_name,
                esh.status_code employment_status,j.id job_id,j.job_name,
                pra.project_id assignment_project_id,pr.project_name,
                wa.id workplace_assignment_id,COALESCE(wa.workplace_name_snapshot,wp.project_name) workplace_name
            FROM institution_employment_contracts c JOIN user_employees e ON e.id=c.employee_id
            LEFT JOIN institution_job_assignments_employment_status_histories esh
              ON esh.employee_id=e.id AND esh.effective_date<=:period_to_status
             AND (esh.ended_date IS NULL OR esh.ended_date>=:period_from_status)
            LEFT JOIN institution_job_assignments_department_histories dh
              ON dh.employee_id=e.id AND dh.effective_from<=:period_to_department
             AND (dh.effective_to IS NULL OR dh.effective_to>=:period_from_department)
            LEFT JOIN user_departments d ON d.id=dh.department_id
            LEFT JOIN institution_job_assignments_position_histories ph
              ON ph.employee_id=e.id AND ph.effective_from<=:period_to_position
             AND (ph.effective_to IS NULL OR ph.effective_to>=:period_from_position)
            LEFT JOIN user_positions p ON p.id=ph.position_id
            LEFT JOIN institution_job_assignments_job_histories jh
              ON jh.employee_id=e.id AND jh.status_code<>'CANCELLED' AND jh.start_date<=:period_to_job
             AND (jh.end_date IS NULL OR jh.end_date>=:period_from_job)
            LEFT JOIN institution_job_assignments_jobs j ON j.id=jh.job_id
            LEFT JOIN institution_job_assignments_project_histories pra
              ON pra.employee_id=e.id AND pra.status_code<>'CANCELLED' AND pra.start_date<=:period_to_project
             AND (pra.end_date IS NULL OR pra.end_date>=:period_from_project)
            LEFT JOIN system_projects pr ON pr.id=pra.project_id
            LEFT JOIN institution_job_assignments_workplace_histories wa
              ON wa.employee_id=e.id AND wa.status_code<>'CANCELLED' AND wa.start_date<=:period_to_workplace
             AND (wa.end_date IS NULL OR wa.end_date>=:period_from_workplace)
            LEFT JOIN system_projects wp ON wp.id=wa.project_id
            WHERE c.id IN (".implode(',',$contractKeys).")
              AND COALESCE(esh.status_code,e.employment_status) NOT IN ('RETIRED','TERMINATED')
            ORDER BY e.sort_no,e.employee_name,e.id,c.revision_no DESC");
        $stmt->execute($contractParams+[
            ':period_from_status'=>$from, ':period_to_status'=>$to,
            ':period_from_department'=>$from, ':period_to_department'=>$to,
            ':period_from_position'=>$from, ':period_to_position'=>$to,
            ':period_from_job'=>$from, ':period_to_job'=>$to,
            ':period_from_project'=>$from, ':period_to_project'=>$to,
            ':period_from_workplace'=>$from, ':period_to_workplace'=>$to,
        ]);
        $seen=[];$rows=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row){if(isset($seen[$row['employee_id']]))continue;$seen[$row['employee_id']]=true;$rows[]=$row;}return $rows;
    }
    public function employmentCandidatesForPeriod(string $from, string $to): array
    {
        $stmt=$this->db->prepare(
            "SELECT e.id employee_id,e.employee_name,e.employment_status,
                    COALESCE(e.real_hire_date,e.doc_hire_date) hire_date,
                    COALESCE(e.real_retire_date,e.doc_retire_date) retire_date
               FROM user_employees e
              WHERE COALESCE(e.real_hire_date,e.doc_hire_date,'0001-01-01')<=:period_to
                AND COALESCE(e.real_retire_date,e.doc_retire_date,'9999-12-31')>=:period_from
                AND (
                    EXISTS(
                        SELECT 1
                          FROM institution_job_assignments_employment_status_histories esh
                         WHERE esh.employee_id=e.id
                           AND esh.effective_date<=:status_to
                           AND COALESCE(esh.ended_date,'9999-12-31')>=:status_from
                           AND esh.status_code IN ('ACTIVE','ON_LEAVE')
                    )
                    OR (
                        NOT EXISTS(
                            SELECT 1
                              FROM institution_job_assignments_employment_status_histories any_status
                             WHERE any_status.employee_id=e.id
                        )
                        AND e.employment_status IN ('ACTIVE','ON_LEAVE')
                    )
                )
              ORDER BY e.sort_no,e.employee_name,e.id"
        );
        $stmt->execute([
            ':period_from'=>$from,':period_to'=>$to,
            ':status_from'=>$from,':status_to'=>$to,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }
    public function nextSortNo(): int{return max(1,(int)$this->db->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM institution_regular_employment_incomes')->fetchColumn());}
    public function insertHeader(array $d): void{$this->insert('institution_regular_employment_incomes',$d);}
    public function insertItem(array $d): void{$this->insert('institution_regular_employment_income_items',$d);}
    private function insert(string $table,array $d):void{$cols=array_keys($d);$s=$this->db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',$cols).'`) VALUES (:'.implode(',:',$cols).')');$s->execute(array_combine(array_map(fn($k)=>':'.$k,$cols),array_values($d)));}
    public function updateHeader(string $id,array $d):void{$sets=[];$p=[':id'=>$id];foreach($d as$k=>$v){$sets[]='`'.$k.'`=:'.$k;$p[':'.$k]=$v;}$this->db->prepare('UPDATE institution_regular_employment_incomes SET '.implode(',',$sets).' WHERE id=:id')->execute($p);}
    public function updateItem(string $id,array $d):void{$sets=[];$p=[':id'=>$id];foreach($d as$k=>$v){$sets[]='`'.$k.'`=:'.$k;$p[':'.$k]=$v;}$this->db->prepare('UPDATE institution_regular_employment_income_items SET '.implode(',',$sets).' WHERE id=:id')->execute($p);}
    public function replaceItems(string $id,array $items):void{$this->db->prepare('DELETE FROM institution_regular_employment_income_items WHERE regular_employment_income_id=:id')->execute([':id'=>$id]);foreach($items as$item)$this->insertItem($item);}
    public function syncHistoricalItems(string $documentId,array $items,string $actor):void
    {
        $stored=[];$existing=[];
        foreach($this->itemsIncludingDeleted($documentId,true)as$row){
            $itemId=(string)$row['id'];
            if(isset($stored[$itemId]))throw new \RuntimeException('동일 직원 계산행 ID가 중복되어 저장할 수 없습니다.');
            $stored[$itemId]=$row;if($row['deleted_at']===null)$existing[$itemId]=$row;
        }
        $seenIds=[];$seenEmployees=[];
        foreach(array_values($items)as$index=>$item){
            $itemId=trim((string)($item['id']??''));$employeeId=trim((string)($item['employee_id']??''));
            if($itemId===''||isset($seenIds[$itemId]))throw new \InvalidArgumentException('직원 계산행 ID가 없거나 중복되었습니다.');
            if($employeeId===''||isset($seenEmployees[$employeeId]))throw new \InvalidArgumentException('동일 직원은 한 번만 저장할 수 있습니다.');
            if((string)($item['regular_employment_income_id']??'')!==$documentId)throw new \InvalidArgumentException('다른 문서의 직원 계산행은 저장할 수 없습니다.');
            if((int)($item['sort_no']??0)!==$index+1)throw new \InvalidArgumentException('직원 순번은 1부터 연속되어야 합니다.');
            $seenIds[$itemId]=true;$seenEmployees[$employeeId]=true;
        }
        $allSorts=array_map(static fn(array$row):int=>(int)$row['sort_no'],array_values($stored));$maxSort=$allSorts===[]?0:max($allSorts);
        $temporaryBase=$maxSort+count($existing)+1;
        if($temporaryBase+count($existing)>4294967295)throw new \RuntimeException('직원 순번 임시영역을 안전하게 확보할 수 없습니다.');
        foreach(array_values($existing)as$index=>$row)$this->updateItem((string)$row['id'],['sort_no'=>$temporaryBase+$index]);
        $kept=[];
        foreach($items as$item){
            $itemId=(string)$item['id'];$kept[$itemId]=true;
            if(isset($stored[$itemId])){$update=$item;unset($update['id'],$update['created_at'],$update['created_by']);$update['deleted_at']=null;$update['deleted_by']=null;$this->updateItem($itemId,$update);}
            else$this->insertItem($item);
        }
        foreach($existing as$itemId=>$row)if(!isset($kept[$itemId]))$this->updateItem($itemId,['deleted_at'=>date('Y-m-d H:i:s'),'deleted_by'=>$actor,'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor]);
    }
    public function updateWorkflow(string $id,string $status,?string $requestId,string $actor):void{$this->db->prepare('UPDATE institution_regular_employment_incomes SET document_status=:status,current_approval_request_id=:request_id,approved_at=IF(:approved_status="APPROVED",NOW(),approved_at),updated_at=NOW(),updated_by=:actor WHERE id=:id')->execute([':status'=>$status,':approved_status'=>$status,':request_id'=>$requestId,':actor'=>$actor,':id'=>$id]);}
    public function softDelete(string$id,string$actor):void{$this->db->prepare("UPDATE institution_regular_employment_incomes SET deleted_at=NOW(),deleted_by=:a WHERE id=:id AND document_status IN ('DRAFT','REJECTED','WITHDRAWN')")->execute([':id'=>$id,':a'=>$actor]);}
    public function trash():array
    {
        $stmt=$this->db->query("SELECT id,income_year_month,title,document_status,deleted_at,deleted_by FROM institution_regular_employment_incomes WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC,id DESC");
        return ActorHelper::enrichActorNames($stmt->fetchAll(PDO::FETCH_ASSOC)?:[],['deleted_by_name'=>'deleted_by']);
    }
    public function trashIds():array{return array_map('strval',$this->db->query('SELECT id FROM institution_regular_employment_incomes WHERE deleted_at IS NOT NULL ORDER BY deleted_at,id')->fetchAll(PDO::FETCH_COLUMN)?:[]);}
    public function restore(string$id,string$actor):bool
    {
        $stmt=$this->db->prepare('UPDATE institution_regular_employment_incomes SET deleted_at=NULL,deleted_by=NULL,updated_at=NOW(),updated_by=:actor WHERE id=:id AND deleted_at IS NOT NULL');
        $stmt->execute([':id'=>$id,':actor'=>$actor]);return$stmt->rowCount()===1;
    }
    public function purge(string$id):bool
    {
        $stmt=$this->db->prepare("SELECT id FROM institution_regular_employment_incomes h WHERE h.id=:id AND h.deleted_at IS NOT NULL AND h.document_status IN ('DRAFT','REJECTED','WITHDRAWN') AND NOT EXISTS(SELECT 1 FROM ledger_evidence_salary_report e WHERE e.source_regular_employment_income_id=h.id) AND NOT EXISTS(SELECT 1 FROM institution_regular_employment_income_accounting_links a WHERE a.regular_employment_income_id=h.id) AND NOT EXISTS(SELECT 1 FROM institution_regular_employment_incomes c WHERE c.correction_of_id=h.id) FOR UPDATE");
        $stmt->execute([':id'=>$id]);if(!$stmt->fetchColumn())return false;
        $this->db->prepare('DELETE FROM institution_regular_employment_income_audits WHERE regular_employment_income_id=:id')->execute([':id'=>$id]);
        $delete=$this->db->prepare('DELETE FROM institution_regular_employment_incomes WHERE id=:id AND deleted_at IS NOT NULL');$delete->execute([':id'=>$id]);return$delete->rowCount()===1;
    }
}
