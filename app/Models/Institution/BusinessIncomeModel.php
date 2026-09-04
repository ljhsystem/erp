<?php

declare(strict_types=1);

namespace App\Models\Institution;

use Core\Helpers\ActorHelper;
use PDO;

final class BusinessIncomeModel
{
    public function __construct(private readonly PDO $db) {}

    public function page(array $request): array
    {
        $search = trim((string)($request['search']['value'] ?? $request['search'] ?? ''));
        $start = max(0, (int)($request['start'] ?? 0));
        $length = min(100, max(1, (int)($request['length'] ?? 20)));
        $where = ' WHERE header.deleted_at IS NULL';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (header.income_year_month LIKE :search OR header.title LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $total = (int)$this->db->query('SELECT COUNT(*) FROM institution_business_incomes WHERE deleted_at IS NULL')->fetchColumn();
        $count = $this->db->prepare('SELECT COUNT(*) FROM institution_business_incomes header' . $where);
        $count->execute($params);
        $filtered = (int)$count->fetchColumn();
        $sql = 'SELECT header.* FROM institution_business_incomes header' . $where . ' ORDER BY header.income_year_month DESC,header.sort_no,header.id LIMIT ' . $length . ' OFFSET ' . $start;
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $rows=ActorHelper::enrichActorNames($statement->fetchAll(PDO::FETCH_ASSOC) ?: [],['created_by_name'=>'created_by','updated_by_name'=>'updated_by','deleted_by_name'=>'deleted_by']);
        return ['draw'=>(int)($request['draw'] ?? 0),'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$rows];
    }

    public function detail(string $id, bool $lock = false): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM institution_business_incomes WHERE id=:id AND deleted_at IS NULL' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':id'=>$id]);
        $header = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$header) return null;
        $groups = $this->db->prepare('SELECT * FROM institution_business_income_groups WHERE business_income_id=:id AND deleted_at IS NULL ORDER BY sort_no,id');
        $groups->execute([':id'=>$id]);
        $header['groups'] = $groups->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = $this->db->prepare("SELECT item.*,client.client_name,client.client_type,COALESCE(client_type.code_name,client.client_type,'') AS client_type_name FROM institution_business_income_items item JOIN institution_business_income_groups business_group ON business_group.id=item.group_id LEFT JOIN system_clients client ON client.id=item.client_id LEFT JOIN system_codes client_type ON client_type.code_group='CLIENT_TYPE' AND client_type.code=client.client_type AND client_type.is_active=1 WHERE business_group.business_income_id=:id AND item.deleted_at IS NULL ORDER BY business_group.sort_no,item.sort_no,item.id");
        $items->execute([':id'=>$id]);
        $itemRows=$items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $workLines=$this->db->prepare("SELECT work_line.* FROM institution_business_income_work_lines work_line JOIN institution_business_income_items item ON item.id=work_line.business_income_item_id JOIN institution_business_income_groups business_group ON business_group.id=item.group_id WHERE business_group.business_income_id=:id AND work_line.deleted_at IS NULL ORDER BY business_group.sort_no,item.sort_no,work_line.sort_no,work_line.id");
        $workLines->execute([':id'=>$id]);
        $byItem=[];
        foreach($workLines->fetchAll(PDO::FETCH_ASSOC) ?: [] as $workLine)$byItem[$workLine['business_income_item_id']][]=$workLine;
        $calculationByItem=[];
        if(!empty($header['current_calculation_revision_id']))foreach($this->calculationLines((string)$header['current_calculation_revision_id']) as $line)$calculationByItem[$line['business_income_item_id']][]=$line;
        $byGroup=[];
        foreach ($itemRows as $item){$item['work_lines']=$byItem[$item['id']]??[];$item['lines']=$calculationByItem[$item['id']]??[];$byGroup[$item['group_id']][]=$item;}
        foreach ($header['groups'] as &$group) $group['items']=$byGroup[$group['id']] ?? [];
        unset($group);
        return ActorHelper::enrichActorNamesRow($header,['created_by_name'=>'created_by','updated_by_name'=>'updated_by','deleted_by_name'=>'deleted_by']);
    }

    public function insert(string $table, array $row): void
    {
        $columns=array_keys($row);
        $statement=$this->db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$columns).'`) VALUES (:'.implode(',:',$columns).')');
        $params=[]; foreach($row as $key=>$value) $params[':'.$key]=$value;
        $statement->execute($params);
    }

    public function update(string $table, string $id, array $row): void
    {
        $sets=[];$params=[':id'=>$id];
        foreach($row as $key=>$value){$sets[]='`'.$key.'`=:'.$key;$params[':'.$key]=$value;}
        $this->db->prepare('UPDATE `'.$table.'` SET '.implode(',',$sets).' WHERE id=:id')->execute($params);
    }

    public function nextRevisionNo(string $headerId): int
    {
        $statement=$this->db->prepare('SELECT COALESCE(MAX(revision_no),0)+1 FROM institution_business_income_calculation_revisions WHERE business_income_id=:id FOR UPDATE');
        $statement->execute([':id'=>$headerId]);
        return (int)$statement->fetchColumn();
    }

    public function calculationLines(string $revisionId, ?string $itemId = null): array
    {
        $sql='SELECT * FROM institution_business_income_calculation_lines WHERE calculation_revision_id=:revision_id';
        $params=[':revision_id'=>$revisionId];
        if($itemId!==null){$sql.=' AND business_income_item_id=:item_id';$params[':item_id']=$itemId;}
        $statement=$this->db->prepare($sql.' ORDER BY business_income_item_id,sort_no,id');$statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function options(array $input=[]):array
    {
        $type=strtolower(trim((string)($input['option_type']??'')));
        if($type!=='')return $this->optionSearch($input);
        return [
            'business_units'=>$this->db->query("SELECT code AS id,code_name AS name,sort_no,extra_data FROM system_codes WHERE code_group='BUSINESS_UNIT' AND is_active=1 ORDER BY sort_no,code")->fetchAll(PDO::FETCH_ASSOC)?:[],
            'projects'=>$this->db->query('SELECT id,project_name AS name,business_unit,start_date,completion_date,sort_no FROM system_projects WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no,project_name,id')->fetchAll(PDO::FETCH_ASSOC)?:[],
            'work_teams'=>$this->db->query('SELECT id,team_name AS name,business_unit,sort_no FROM system_work_teams WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no,team_name,id')->fetchAll(PDO::FETCH_ASSOC)?:[],
            'work_types'=>$this->db->query("SELECT code AS id,code_name AS name,sort_no FROM system_codes WHERE code_group='WORK_TYPE' AND is_active=1 ORDER BY sort_no,code_name,code")->fetchAll(PDO::FETCH_ASSOC)?:[],
            'recipients'=>$this->db->query("SELECT c.id,c.client_name AS name,c.client_type,COALESCE(ct.code_name,c.client_type,'') AS client_type_name,c.sort_no FROM system_clients c LEFT JOIN system_codes ct ON ct.code_group='CLIENT_TYPE' AND ct.code=c.client_type AND ct.is_active=1 WHERE c.is_active=1 AND c.deleted_at IS NULL ORDER BY c.sort_no,c.client_name,c.id")->fetchAll(PDO::FETCH_ASSOC)?:[],
        ];
    }

    public function activeUnitNames(): array
    {
        $rows = $this->db->query("SELECT code,code_name FROM system_codes WHERE code_group='UNIT' AND is_active=1 ORDER BY sort_no,code_name,code")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $units = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['code_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $units[$name] = $name;
            if ($code !== '') {
                $units[$code] = $name;
            }
        }
        return $units;
    }

    public function optionSearch(array $input):array
    {
        $type=strtolower(trim((string)($input['option_type']??'')));$q='%'.trim((string)($input['q']??'')).'%';$page=max(1,(int)($input['page']??1));$offset=($page-1)*20;$businessUnit=strtoupper(trim((string)($input['business_unit']??'')));
        if($type==='business_unit'){$statement=$this->db->prepare("SELECT code id,code_name text,sort_no,extra_data FROM system_codes WHERE code_group='BUSINESS_UNIT' AND is_active=1 AND (code LIKE :q1 OR code_name LIKE :q2) ORDER BY sort_no,code LIMIT 21 OFFSET {$offset}");$statement->execute([':q1'=>$q,':q2'=>$q]);}
        elseif($type==='project'&&$businessUnit!==''){$statement=$this->db->prepare("SELECT id,project_name text,sort_no,business_unit FROM system_projects WHERE is_active=1 AND deleted_at IS NULL AND business_unit=:business_unit AND project_name LIKE :q ORDER BY sort_no,project_name,id LIMIT 21 OFFSET {$offset}");$statement->execute([':business_unit'=>$businessUnit,':q'=>$q]);}
        elseif($type==='work_team'&&$businessUnit!==''){$statement=$this->db->prepare("SELECT id,team_name text,sort_no,business_unit FROM system_work_teams WHERE is_active=1 AND deleted_at IS NULL AND business_unit=:business_unit AND team_name LIKE :q ORDER BY sort_no,team_name,id LIMIT 21 OFFSET {$offset}");$statement->execute([':business_unit'=>$businessUnit,':q'=>$q]);}
        elseif($type==='work_type'){$statement=$this->db->prepare("SELECT code AS id,code_name AS text,sort_no FROM system_codes WHERE code_group='WORK_TYPE' AND is_active=1 AND (code LIKE :q1 OR code_name LIKE :q2) ORDER BY sort_no,code_name,code LIMIT 21 OFFSET {$offset}");$statement->execute([':q1'=>$q,':q2'=>$q]);}
        elseif($type==='recipient'){$statement=$this->db->prepare("SELECT c.id,c.client_name text,c.client_name,c.client_type,COALESCE(ct.code_name,c.client_type,'') AS client_type_name,c.sort_no FROM system_clients c LEFT JOIN system_codes ct ON ct.code_group='CLIENT_TYPE' AND ct.code=c.client_type AND ct.is_active=1 WHERE c.is_active=1 AND c.deleted_at IS NULL AND c.client_name LIKE :q ORDER BY c.sort_no,c.client_name,c.id LIMIT 21 OFFSET {$offset}");$statement->execute([':q'=>$q]);}
        else return ['results'=>[],'has_more'=>false,'page'=>$page];
        $rows=$statement->fetchAll(PDO::FETCH_ASSOC)?:[];return ['results'=>array_slice($rows,0,20),'has_more'=>count($rows)>20,'page'=>$page];
    }

    public function assertReferences(string $businessUnit,?string $projectId,?string $workTeamId,string $transactionDate):array
    {
        $statement=$this->db->prepare("SELECT code,extra_data FROM system_codes WHERE code_group='BUSINESS_UNIT' AND code=:code AND is_active=1");$statement->execute([':code'=>$businessUnit]);$row=$statement->fetch(PDO::FETCH_ASSOC);if(!$row)throw new \InvalidArgumentException('사업구분을 확인해 주세요.');
        if($projectId!==null){$check=$this->db->prepare('SELECT COUNT(*) FROM system_projects WHERE id=:id AND business_unit=:unit AND is_active=1 AND deleted_at IS NULL AND (start_date IS NULL OR start_date<=:from_date) AND (completion_date IS NULL OR completion_date>=:to_date)');$check->execute([':id'=>$projectId,':unit'=>$businessUnit,':from_date'=>$transactionDate,':to_date'=>$transactionDate]);if((int)$check->fetchColumn()!==1)throw new \InvalidArgumentException('거래일에 유효한 프로젝트를 선택해 주세요.');}
        if($workTeamId!==null){$check=$this->db->prepare('SELECT COUNT(*) FROM system_work_teams WHERE id=:id AND business_unit=:unit AND is_active=1 AND deleted_at IS NULL');$check->execute([':id'=>$workTeamId,':unit'=>$businessUnit]);if((int)$check->fetchColumn()!==1)throw new \InvalidArgumentException('선택한 사업구분의 작업팀을 선택해 주세요.');}
        return $row;
    }
}
