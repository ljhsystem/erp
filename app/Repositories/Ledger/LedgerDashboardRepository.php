<?php

namespace App\Repositories\Ledger;

use PDO;

final class LedgerDashboardRepository
{
    public function __construct(private readonly PDO $db) {}

    public function voucherStatusCounts(): array
    {
        $stmt = $this->db->query("SELECT UPPER(status) AS status_code, COUNT(*) AS item_count FROM ledger_vouchers WHERE deleted_at IS NULL GROUP BY UPPER(status)");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function postedPerformance(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT v.id) AS voucher_count, COUNT(l.id) AS line_count, COALESCE(SUM(l.debit),0) AS debit_total, COALESCE(SUM(l.credit),0) AS credit_total, COALESCE(SUM(CASE WHEN a.account_group='수익' THEN l.credit-l.debit ELSE 0 END),0) AS revenue_total, COALESCE(SUM(CASE WHEN a.account_group='비용' THEN l.debit-l.credit ELSE 0 END),0) AS expense_total FROM ledger_vouchers v JOIN ledger_voucher_lines l ON l.voucher_id=v.id JOIN ledger_accounts a ON a.id=l.account_id WHERE v.deleted_at IS NULL AND UPPER(v.status) IN ('POSTED','CLOSED') AND v.voucher_date BETWEEN :date_from AND :date_to");
        $stmt->execute([':date_from'=>$dateFrom, ':date_to'=>$dateTo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function monthlyPostedPerformance(string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT DATE_FORMAT(v.voucher_date,'%Y-%m') AS period_key,
                       COUNT(DISTINCT v.id) AS voucher_count,
                       COALESCE(SUM(CASE WHEN a.account_group='수익' THEN l.credit-l.debit ELSE 0 END),0) AS revenue_total,
                       COALESCE(SUM(CASE WHEN a.account_group='비용' THEN l.debit-l.credit ELSE 0 END),0) AS expense_total
                  FROM ledger_vouchers v
                  JOIN ledger_voucher_lines l ON l.voucher_id=v.id
                  JOIN ledger_accounts a ON a.id=l.account_id
                 WHERE v.deleted_at IS NULL
                   AND UPPER(v.status) IN ('POSTED','CLOSED')
                   AND v.voucher_date BETWEEN :date_from AND :date_to
                 GROUP BY DATE_FORMAT(v.voucher_date,'%Y-%m')
                 ORDER BY period_key";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':date_from'=>$dateFrom, ':date_to'=>$dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function inventorySummary(int $fiscalYear): array
    {
        $sql = "SELECT COUNT(DISTINCT h.id) AS document_count,
                       COALESCE(SUM(i.opening_amount),0) AS opening_total,
                       COALESCE(SUM(i.increase_amount),0) AS increase_total,
                       COALESCE(SUM(i.decrease_amount),0) AS decrease_total,
                       COALESCE(SUM(i.opening_amount+i.increase_amount-i.decrease_amount),0) AS closing_total,
                       SUM(CASE WHEN h.status_code='CONFIRMED' THEN 1 ELSE 0 END) AS confirmed_line_count,
                       COUNT(i.id) AS line_count
                  FROM ledger_inventory_balances h
                  LEFT JOIN ledger_inventory_balance_items i ON i.inventory_balance_id=h.id
                 WHERE h.fiscal_year=:fiscal_year";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':fiscal_year'=>$fiscalYear]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function depreciationSummary(int $fiscalYear): array
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS item_count,COALESCE(SUM(depreciation_amount),0) AS amount_total FROM ledger_asset_depreciations WHERE YEAR(depreciation_date)=:fiscal_year");
        $stmt->execute([':fiscal_year'=>$fiscalYear]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function assetSummary(): array
    {
        $sql = "SELECT COUNT(*) AS active_count, COALESCE(SUM(a.acquisition_amount),0) AS acquisition_total, COALESCE(SUM(a.acquisition_amount-COALESCE(d.accumulated_amount,0)),0) AS book_value_total FROM ledger_assets a LEFT JOIN (SELECT asset_id,MAX(accumulated_amount) AS accumulated_amount FROM ledger_asset_depreciations GROUP BY asset_id) d ON d.asset_id=a.id WHERE a.deleted_at IS NULL AND a.asset_status_code='ACTIVE'";
        return $this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function latestClosure(): ?array
    {
        $stmt = $this->db->query("SELECT c.fiscal_year,c.period_start_date,c.period_end_date,c.close_status_code,c.closed_at,s.company_name_ko AS company_name FROM ledger_period_closures c JOIN system_company s ON s.id=c.company_id ORDER BY c.fiscal_year DESC,c.updated_at DESC,c.created_at DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function recentPostedVouchers(int $limit = 8): array
    {
        $stmt = $this->db->prepare("SELECT v.id,v.voucher_date,v.voucher_no,v.summary,UPPER(v.status) AS status_code,COALESCE(SUM(l.debit),0) AS amount FROM ledger_vouchers v JOIN ledger_voucher_lines l ON l.voucher_id=v.id WHERE v.deleted_at IS NULL AND UPPER(v.status) IN ('POSTED','CLOSED') GROUP BY v.id,v.voucher_date,v.voucher_no,v.summary,v.status ORDER BY v.voucher_date DESC,v.voucher_no DESC LIMIT :row_limit");
        $stmt->bindValue(':row_limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
