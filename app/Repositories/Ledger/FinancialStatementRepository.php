<?php

namespace App\Repositories\Ledger;

use PDO;

final class FinancialStatementRepository
{
    public function __construct(private readonly PDO $db) {}

    public function accountBalances(string $dateFrom,string $dateTo): array
    {
        $priorFrom=(new \DateTimeImmutable($dateFrom))->modify('-1 year')->format('Y-m-d');
        $priorTo=(new \DateTimeImmutable($dateTo))->modify('-1 year')->format('Y-m-d');
        $statuses=['POSTED','CLOSED'];
        $holders=implode(',',array_fill(0,count($statuses),'?'));
        $sql="SELECT a.id,a.parent_id,a.account_code,a.account_name,a.account_group,UPPER(a.normal_balance) normal_balance,a.level,a.is_posting,a.sort_no,
            COALESCE(SUM(CASE WHEN v.voucher_date<? THEN l.debit ELSE 0 END),0) opening_debit,
            COALESCE(SUM(CASE WHEN v.voucher_date<? THEN l.credit ELSE 0 END),0) opening_credit,
            COALESCE(SUM(CASE WHEN v.voucher_date BETWEEN ? AND ? THEN l.debit ELSE 0 END),0) period_debit,
            COALESCE(SUM(CASE WHEN v.voucher_date BETWEEN ? AND ? THEN l.credit ELSE 0 END),0) period_credit,
            COALESCE(SUM(CASE WHEN v.voucher_date<=? THEN l.debit ELSE 0 END),0) ending_debit,
            COALESCE(SUM(CASE WHEN v.voucher_date<=? THEN l.credit ELSE 0 END),0) ending_credit,
            COALESCE(SUM(CASE WHEN v.voucher_date BETWEEN ? AND ? THEN l.debit ELSE 0 END),0) prior_period_debit,
            COALESCE(SUM(CASE WHEN v.voucher_date BETWEEN ? AND ? THEN l.credit ELSE 0 END),0) prior_period_credit,
            COALESCE(SUM(CASE WHEN v.voucher_date<=? THEN l.debit ELSE 0 END),0) prior_ending_debit,
            COALESCE(SUM(CASE WHEN v.voucher_date<=? THEN l.credit ELSE 0 END),0) prior_ending_credit
          FROM ledger_accounts a
          LEFT JOIN ledger_voucher_lines l ON l.account_id=a.id
          LEFT JOIN ledger_vouchers v ON v.id=l.voucher_id AND v.deleted_at IS NULL AND UPPER(v.status) IN ({$holders})
          WHERE a.deleted_at IS NULL
          GROUP BY a.id,a.parent_id,a.account_code,a.account_name,a.account_group,a.normal_balance,a.level,a.is_posting,a.sort_no
          ORDER BY a.sort_no,a.account_code";
        $params=[$dateFrom,$dateFrom,$dateFrom,$dateTo,$dateFrom,$dateTo,$dateTo,$dateTo,$priorFrom,$priorTo,$priorFrom,$priorTo,$priorTo,$priorTo,...$statuses];
        $stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }
}
