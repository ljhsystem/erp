<?php

namespace App\Models\Ledger;

use PDO;

class InventoryBalanceModel
{
    public function __construct(private readonly PDO $db) {}

    public function list(array $filters = []): array
    {
        $sql = "SELECT h.*,c.company_name_ko company_name,
                       COALESCE(SUM(i.opening_amount),0) opening_amount,
                       COALESCE(SUM(i.increase_amount),0) increase_amount,
                       COALESCE(SUM(i.decrease_amount),0) decrease_amount
                  FROM ledger_inventory_balances h
                  JOIN system_company c ON c.id=h.company_id
             LEFT JOIN ledger_inventory_balance_items i ON i.inventory_balance_id=h.id
                 WHERE 1=1";
        $params=[];
        if (($filters['company_id']??'')!=='') {$sql.=' AND h.company_id=:company_id';$params[':company_id']=$filters['company_id'];}
        if (($filters['fiscal_year']??'')!=='') {$sql.=' AND h.fiscal_year=:fiscal_year';$params[':fiscal_year']=(int)$filters['fiscal_year'];}
        $sql.=' GROUP BY h.id,c.company_name_ko ORDER BY h.fiscal_year DESC,c.company_name_ko,h.id';
        $stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    public function find(string $id, bool $lock=false): ?array
    {
        $stmt=$this->db->prepare('SELECT h.*,c.company_name_ko company_name FROM ledger_inventory_balances h JOIN system_company c ON c.id=h.company_id WHERE h.id=:id'.($lock?' FOR UPDATE':''));
        $stmt->execute([':id'=>$id]);return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function findByCompanyYear(string $companyId,int $year): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM ledger_inventory_balances WHERE company_id=:company_id AND fiscal_year=:fiscal_year');
        $stmt->execute([':company_id'=>$companyId,':fiscal_year'=>$year]);return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public function items(string $id): array
    {
        $stmt=$this->db->prepare("SELECT i.*,p.project_name,bu.code_name business_unit_name FROM ledger_inventory_balance_items i LEFT JOIN system_projects p ON p.id=i.project_id LEFT JOIN system_codes bu ON bu.code_group='BUSINESS_UNIT' AND bu.code=i.business_unit WHERE i.inventory_balance_id=:id ORDER BY i.sort_no,i.id");
        $stmt->execute([':id'=>$id]);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    public function insertHeader(array $row): void { $this->execute('INSERT INTO ledger_inventory_balances (id,company_id,fiscal_year,status_code,note,created_at,created_by,updated_at,updated_by) VALUES (:id,:company_id,:fiscal_year,:status_code,:note,:created_at,:created_by,:updated_at,:updated_by)',$row); }
    public function updateHeader(string $id,array $row): void { $row[':id']=$id;$this->execute('UPDATE ledger_inventory_balances SET company_id=:company_id,fiscal_year=:fiscal_year,note=:note,updated_at=:updated_at,updated_by=:updated_by WHERE id=:id',$row); }
    public function replaceItems(string $id,array $items,string $actor,string $now): void
    {
        $this->execute('DELETE FROM ledger_inventory_balance_items WHERE inventory_balance_id=:id',[':id'=>$id]);
        foreach($items as $item){$this->execute('INSERT INTO ledger_inventory_balance_items (id,inventory_balance_id,sort_no,business_unit,project_id,inventory_category_code,item_name,opening_amount,increase_amount,decrease_amount,calculation_basis,evidence_reference,note,created_at,created_by,updated_at,updated_by) VALUES (:id,:inventory_balance_id,:sort_no,:business_unit,:project_id,:inventory_category_code,:item_name,:opening_amount,:increase_amount,:decrease_amount,:calculation_basis,:evidence_reference,:note,:created_at,:created_by,:updated_at,:updated_by)',$item+[':inventory_balance_id'=>$id,':created_at'=>$now,':created_by'=>$actor,':updated_at'=>$now,':updated_by'=>$actor]);}
    }
    public function setConfirmed(string $id,bool $confirmed,string $actor): void
    {
        $now=date('Y-m-d H:i:s');
        $this->execute('UPDATE ledger_inventory_balances SET status_code=:status,confirmed_at=:confirmed_at,confirmed_by=:confirmed_by,updated_at=:updated_at,updated_by=:updated_by WHERE id=:id',[
            ':status'=>$confirmed?'CONFIRMED':'DRAFT',
            ':confirmed_at'=>$confirmed?$now:null,
            ':confirmed_by'=>$confirmed?$actor:null,
            ':updated_at'=>$now,
            ':updated_by'=>$actor,
            ':id'=>$id,
        ]);
    }
    public function delete(string $id): void { $this->execute('DELETE FROM ledger_inventory_balances WHERE id=:id',[':id'=>$id]); }
    public function companies(): array { return $this->db->query('SELECT id,company_name_ko name FROM system_company ORDER BY company_name_ko,id')->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function projects(): array { return $this->db->query('SELECT id,project_name name FROM system_projects WHERE deleted_at IS NULL ORDER BY project_name,id')->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function businessUnits(): array { return $this->db->query("SELECT code id,code_name name FROM system_codes WHERE code_group='BUSINESS_UNIT' AND is_active=1 ORDER BY sort_no,code")->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    private function execute(string $sql,array $params): void { $stmt=$this->db->prepare($sql);$stmt->execute($params); }
}
