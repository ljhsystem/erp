<?php

namespace App\Models\Ledger;

use PDO;

class OpeningBalanceModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function list(array $filters = []): array
    {
        $sql = "SELECT ob.*,c.company_name_ko AS company_name,v.voucher_no,v.status,
                       v.debit_total,v.credit_total,v.line_count,v.created_by AS voucher_created_by,
                       v.updated_by AS voucher_updated_by
                  FROM ledger_opening_balances ob
                  JOIN system_company c ON c.id=ob.company_id
             LEFT JOIN ledger_vouchers v ON v.id=ob.voucher_id AND v.deleted_at IS NULL
                 WHERE 1=1";
        $params = [];
        if (!empty($filters['company_id'])) {
            $sql .= ' AND ob.company_id=:company_id';
            $params[':company_id'] = $filters['company_id'];
        }
        if (!empty($filters['fiscal_year'])) {
            $sql .= ' AND ob.fiscal_year=:fiscal_year';
            $params[':fiscal_year'] = (int) $filters['fiscal_year'];
        }
        $sql .= ' ORDER BY ob.fiscal_year DESC,c.company_name_ko ASC,ob.id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(string $id, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare("SELECT ob.*,c.company_name_ko AS company_name,v.voucher_no,v.voucher_date,v.status,
                    v.debit_total,v.credit_total,v.line_count,v.created_by AS voucher_created_by,v.updated_by AS voucher_updated_by
               FROM ledger_opening_balances ob
               JOIN system_company c ON c.id=ob.company_id
          LEFT JOIN ledger_vouchers v ON v.id=ob.voucher_id
              WHERE ob.id=:id" . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByCompanyYear(string $companyId, int $year): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ledger_opening_balances WHERE company_id=:company_id AND fiscal_year=:fiscal_year LIMIT 1');
        $stmt->execute([':company_id' => $companyId, ':fiscal_year' => $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(array $row): bool
    {
        $stmt = $this->db->prepare('INSERT INTO ledger_opening_balances
            (id,company_id,fiscal_year,opening_date,period_end_date,voucher_id,note,created_at,created_by,updated_at,updated_by)
            VALUES (:id,:company_id,:fiscal_year,:opening_date,:period_end_date,:voucher_id,:note,:created_at,:created_by,:updated_at,:updated_by)');
        return $stmt->execute($row);
    }

    public function update(string $id, array $row): bool
    {
        $row[':id'] = $id;
        $stmt = $this->db->prepare('UPDATE ledger_opening_balances SET company_id=:company_id,fiscal_year=:fiscal_year,
            opening_date=:opening_date,period_end_date=:period_end_date,voucher_id=:voucher_id,note=:note,updated_at=:updated_at,updated_by=:updated_by WHERE id=:id');
        return $stmt->execute($row);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM ledger_opening_balances WHERE id=:id');
        return $stmt->execute([':id' => $id]);
    }

    public function companies(): array
    {
        return $this->db->query('SELECT id,company_name_ko AS name FROM system_company ORDER BY company_name_ko ASC,id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function lines(string $voucherId): array
    {
        if ($voucherId === '') {
            return [];
        }
        $stmt = $this->db->prepare("SELECT l.*,a.account_code,a.account_name,a.normal_balance,
                    (SELECT GROUP_CONCAT(CONCAT(r.ref_target,':',r.ref_id) ORDER BY r.ref_target SEPARATOR '|')
                       FROM ledger_voucher_line_refs r WHERE r.voucher_line_id=l.id) AS ref_tokens
               FROM ledger_voucher_lines l
               JOIN ledger_accounts a ON a.id=l.account_id
              WHERE l.voucher_id=:voucher_id
           ORDER BY l.line_no ASC");
        $stmt->execute([':voucher_id' => $voucherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
