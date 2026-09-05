<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\LedgerDashboardRepository;
use DateTimeImmutable;
use PDO;

final class LedgerDashboardService
{
    public function __construct(private readonly LedgerDashboardRepository $repository) {}

    public static function fromPdo(PDO $pdo): self
    {
        return new self(new LedgerDashboardRepository($pdo));
    }

    public function summary(?string $today = null, ?int $fiscalYear = null): array
    {
        $date = new DateTimeImmutable($today ?: 'now');
        $year = $fiscalYear && $fiscalYear >= 1900 && $fiscalYear <= 9999 ? $fiscalYear : (int) $date->format('Y');
        $yearFrom = sprintf('%04d-01-01', $year);
        $yearTo = sprintf('%04d-12-31', $year);
        $statusCounts = ['DRAFT'=>0,'REVIEW_REQUESTED'=>0,'REVIEWED'=>0,'POSTED'=>0,'CLOSED'=>0];
        foreach ($this->repository->voucherStatusCounts() as $row) {
            $statusCounts[(string) $row['status_code']] = (int) $row['item_count'];
        }
        $performance = $this->repository->postedPerformance($yearFrom, $yearTo);
        $monthlyRows = $this->repository->monthlyPostedPerformance($yearFrom, $yearTo);
        $assets = $this->repository->assetSummary();
        $inventory = $this->repository->inventorySummary($year);
        $depreciation = $this->repository->depreciationSummary($year);
        $closure = $this->repository->latestClosure();
        $revenue = (float) ($performance['revenue_total'] ?? 0);
        $expense = (float) ($performance['expense_total'] ?? 0);

        $monthlyByKey = [];
        foreach ($monthlyRows as $row) {
            $monthlyByKey[(string) $row['period_key']] = $row;
        }
        $monthly = [];
        for ($month = 1; $month <= 12; $month++) {
            $key = sprintf('%04d-%02d', $year, $month);
            $row = $monthlyByKey[$key] ?? [];
            $monthRevenue = (float) ($row['revenue_total'] ?? 0);
            $monthExpense = (float) ($row['expense_total'] ?? 0);
            $monthly[] = ['year_month'=>$key,'voucher_count'=>(int)($row['voucher_count']??0),'revenue_total'=>$monthRevenue,'expense_total'=>$monthExpense,'profit_total'=>$monthRevenue-$monthExpense];
        }
        $difference = (float) ($performance['debit_total'] ?? 0) - (float) ($performance['credit_total'] ?? 0);
        $readiness = [
            ['code'=>'BALANCE','label'=>'차변·대변 일치','complete'=>abs($difference)<0.001,'href'=>'/ledger/book/journal'],
            ['code'=>'POSTING','label'=>'전표 전기','complete'=>$statusCounts['DRAFT']+$statusCounts['REVIEW_REQUESTED']+$statusCounts['REVIEWED']===0,'href'=>'/ledger/vouchers/review'],
            ['code'=>'INVENTORY','label'=>'재고 확정','complete'=>(int)($inventory['line_count']??0)>0 && (int)($inventory['confirmed_line_count']??0)===(int)($inventory['line_count']??0),'href'=>'/ledger/settings/inventory-balances'],
            ['code'=>'DEPRECIATION','label'=>'감가상각 반영','complete'=>(int)($assets['active_count']??0)===0 || (int)($depreciation['item_count']??0)>0,'href'=>'/ledger/assets/depreciation'],
            ['code'=>'CLOSURE','label'=>'회계기간 마감','complete'=>strtoupper((string)($closure['close_status_code']??''))==='CLOSED','href'=>'/ledger/closing/check'],
        ];
        $alerts = [];
        if (abs($difference)>=0.001) $alerts[]=['level'=>'BLOCK','title'=>'차변·대변 차이가 발생했습니다.','detail'=>'전기 자료의 차대변 차이를 확인해 주세요.','value'=>$difference,'href'=>'/ledger/book/journal'];
        if ($statusCounts['REVIEW_REQUESTED']>0) $alerts[]=['level'=>'ACTION','title'=>'검토 요청 전표가 있습니다.','detail'=>'검토 후 승인 또는 반려가 필요합니다.','count'=>$statusCounts['REVIEW_REQUESTED'],'href'=>'/ledger/vouchers/review'];
        if ($statusCounts['REVIEWED']>0) $alerts[]=['level'=>'ACTION','title'=>'전기할 전표가 있습니다.','detail'=>'검토 완료 전표를 장부에 반영해 주세요.','count'=>$statusCounts['REVIEWED'],'href'=>'/ledger/vouchers/review'];
        if ((int)($inventory['line_count']??0)===0) $alerts[]=['level'=>'INFO','title'=>'재고관리 자료가 없습니다.','detail'=>$year.'년 재고 증감과 기말재고를 확인해 주세요.','href'=>'/ledger/settings/inventory-balances'];

        return [
            'as_of'=>$date->format('Y-m-d H:i:s'),
            'fiscal_year'=>$year,
            'period'=>['date_from'=>$yearFrom,'date_to'=>$yearTo],
            'vouchers'=>$statusCounts + ['unposted'=>$statusCounts['DRAFT']+$statusCounts['REVIEW_REQUESTED']+$statusCounts['REVIEWED']],
            'performance'=>[
                'voucher_count'=>(int) ($performance['voucher_count'] ?? 0),
                'line_count'=>(int) ($performance['line_count'] ?? 0),
                'debit_total'=>(float) ($performance['debit_total'] ?? 0),
                'credit_total'=>(float) ($performance['credit_total'] ?? 0),
                'revenue_total'=>$revenue,
                'expense_total'=>$expense,
                'profit_total'=>$revenue-$expense,
            ],
            'assets'=>[
                'active_count'=>(int) ($assets['active_count'] ?? 0),
                'acquisition_total'=>(float) ($assets['acquisition_total'] ?? 0),
                'book_value_total'=>(float) ($assets['book_value_total'] ?? 0),
            ],
            'inventory'=>[
                'opening_total'=>(float)($inventory['opening_total']??0),
                'increase_total'=>(float)($inventory['increase_total']??0),
                'decrease_total'=>(float)($inventory['decrease_total']??0),
                'closing_total'=>(float)($inventory['closing_total']??0),
            ],
            'depreciation'=>['item_count'=>(int)($depreciation['item_count']??0),'amount_total'=>(float)($depreciation['amount_total']??0)],
            'monthly'=>$monthly,
            'readiness'=>$readiness,
            'alerts'=>$alerts,
            'closure'=>$closure,
            'recent_vouchers'=>$this->repository->recentPostedVouchers(),
        ];
    }
}
