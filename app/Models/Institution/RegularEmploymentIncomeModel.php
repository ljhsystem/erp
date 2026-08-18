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
            LEFT JOIN user_approval_requests ar ON ar.id=h.current_approval_request_id
            LEFT JOIN ledger_evidence_salary_report ev ON ev.source_regular_employment_income_id=h.id AND ev.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx ON tx.evidence_type='PAYROLL_REPORT' AND tx.evidence_id=ev.id AND tx.target_type='TRANSACTION' AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx ON vx.evidence_type='PAYROLL_REPORT' AND vx.evidence_id=ev.id AND vx.target_type='VOUCHER' AND vx.deleted_at IS NULL
            WHERE " . implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(DISTINCT h.id)' . $from); $count->execute($params);
        $start=max(0,(int)($query['start']??0));$length=max(1,min(500,(int)($query['length']??100)));
        $sql="SELECT h.*,COALESCE(ar.status,'draft') approval_status,ev.id evidence_id,
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
    public function eligibleEmployees(string $month, array $contractIds): array
    {
        if ($contractIds === []) return [];
        $from=$month.'-01';$to=date('Y-m-t',strtotime($from));
        $contractKeys=[];$contractParams=[];foreach(array_values($contractIds)as$i=>$id){$key=':contract_'.$i;$contractKeys[]=$key;$contractParams[$key]=$id;}
        $stmt=$this->db->prepare("SELECT e.id employee_id,e.employee_name,e.rrn employee_identifier_snapshot,
                c.id employment_contract_id,c.project_id,d.dept_name department_name,p.position_name,
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
            ORDER BY e.employee_name,c.revision_no DESC");
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
    public function nextSortNo(): int{return max(1,(int)$this->db->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM institution_regular_employment_incomes')->fetchColumn());}
    public function insertHeader(array $d): void{$this->insert('institution_regular_employment_incomes',$d);}
    public function insertItem(array $d): void{$this->insert('institution_regular_employment_income_items',$d);}
    private function insert(string $table,array $d):void{$cols=array_keys($d);$s=$this->db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',$cols).'`) VALUES (:'.implode(',:',$cols).')');$s->execute(array_combine(array_map(fn($k)=>':'.$k,$cols),array_values($d)));}
    public function updateHeader(string $id,array $d):void{$sets=[];$p=[':id'=>$id];foreach($d as$k=>$v){$sets[]='`'.$k.'`=:'.$k;$p[':'.$k]=$v;}$this->db->prepare('UPDATE institution_regular_employment_incomes SET '.implode(',',$sets).' WHERE id=:id')->execute($p);}
    public function replaceItems(string $id,array $items):void{$this->db->prepare('DELETE FROM institution_regular_employment_income_items WHERE regular_employment_income_id=:id')->execute([':id'=>$id]);foreach($items as$item)$this->insertItem($item);}
    public function updateWorkflow(string $id,string $status,?string $requestId,string $actor):void{$this->db->prepare('UPDATE institution_regular_employment_incomes SET document_status=:status,current_approval_request_id=:request_id,approved_at=IF(:approved_status="APPROVED",NOW(),approved_at),updated_at=NOW(),updated_by=:actor WHERE id=:id')->execute([':status'=>$status,':approved_status'=>$status,':request_id'=>$requestId,':actor'=>$actor,':id'=>$id]);}
    public function softDelete(string$id,string$actor):void{$this->db->prepare("UPDATE institution_regular_employment_incomes SET deleted_at=NOW(),deleted_by=:a WHERE id=:id AND document_status IN ('DRAFT','REJECTED','WITHDRAWN')")->execute([':id'=>$id,':a'=>$actor]);}
}
