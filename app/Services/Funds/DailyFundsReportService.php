<?php

namespace App\Services\Funds;

use App\Models\Funds\BankTransactionReportModel;
use App\Models\Funds\PaymentScheduleModel;
use App\Models\Funds\FundsBalanceModel;
use App\Models\System\BankAccountModel;
use App\Models\System\CodeModel;
use App\Models\System\CompanyModel;
use App\Repositories\Funds\InternalTransferRepository;
use Core\Helpers\ExcelValueFormatterHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PDO;

class DailyFundsReportService
{
    private BankTransactionReportModel $transactions;
    private BankAccountModel $accounts;
    private CodeModel $codes;
    private CompanyModel $company;
    private PaymentScheduleModel $paymentSchedules;
    private InternalTransferRepository $internalTransfers;
    private FundsBalanceModel $fundBalances;

    public function __construct(PDO $pdo)
    {
        $this->transactions = new BankTransactionReportModel($pdo);
        $this->accounts = new BankAccountModel($pdo);
        $this->codes = new CodeModel($pdo);
        $this->company = new CompanyModel($pdo);
        $this->paymentSchedules = new PaymentScheduleModel($pdo);
        $this->internalTransfers = new InternalTransferRepository($pdo);
        $this->fundBalances = new FundsBalanceModel($pdo);
    }

    public function report(array $request): array
    {
        $filters = $this->filters($request);
        $date = $filters['report_date'];
        $rows = $this->transactions->rows([[
            'field' => 'transaction_datetime',
            'value' => ['start' => '1900-01-01', 'end' => $date],
        ]]);
        $accountRows = $this->accounts->getList([['field' => 'is_active', 'value' => '1']]);
        $bankNames = $this->bankNames();
        $instruments = [];
        $confirmedTransfers = $this->internalTransfers->confirmedEvidenceMap();
        $accountingBalances = $this->fundBalances->accountingBalancesByBankAccount($date);
        $unassigned = [
            'count' => 0,
            'deposit_amount' => 0.0,
            'withdraw_amount' => 0.0,
            'net_amount' => 0.0,
        ];

        foreach ($accountRows as $account) {
            $id = trim((string) ($account['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $storedBank = trim((string) ($account['bank_name'] ?? ''));
            $instruments[$id] = [
                'bank_account_id' => $id,
                'fund_type' => $this->isCash($account) ? '현금' : '은행',
                'bank_name' => $this->isCash($account) ? '현금보관' : ($bankNames[$storedBank] ?? $storedBank),
                'account_name' => (string) ($account['account_name'] ?? ''),
                'account_number' => (string) ($account['account_number'] ?? ''),
                'opening_balance' => 0.0,
                'deposit_total' => 0.0,
                'withdraw_total' => 0.0,
                'internal_deposit' => 0.0,
                'internal_withdraw' => 0.0,
                'ending_balance' => 0.0,
                'accounting_balance' => (float) ($accountingBalances[$id]['balance'] ?? 0),
                'balance_difference' => 0.0,
                'unlinked_amount' => 0.0,
                'scheduled_payment' => 0.0,
                'available_balance' => 0.0,
                'last_transaction_at' => null,
            ];
        }

        $dailyRows = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['bank_account_id'] ?? ''));
            if ($id === '') {
                $unassigned['count']++;
                $unassigned['deposit_amount'] += (float) ($row['deposit_amount'] ?? 0);
                $unassigned['withdraw_amount'] += (float) ($row['withdraw_amount'] ?? 0);
                $unassigned['net_amount'] = $unassigned['deposit_amount'] - $unassigned['withdraw_amount'];
                continue;
            }
            if (!isset($instruments[$id])) {
                continue;
            }
            $transactionDate = substr((string) ($row['transaction_datetime'] ?? $row['transaction_date'] ?? ''), 0, 10);
            $deposit = (float) ($row['deposit_amount'] ?? 0);
            $withdraw = (float) ($row['withdraw_amount'] ?? 0);
            if ($transactionDate < $date) {
                $instruments[$id]['opening_balance'] += $deposit - $withdraw;
                continue;
            }
            if ($transactionDate !== $date) {
                continue;
            }
            $transfer = $confirmedTransfers[(string) ($row['id'] ?? '')] ?? null;
            if ($transfer !== null && $deposit > 0) {
                $instruments[$id]['internal_deposit'] += $deposit;
            } elseif ($transfer !== null && $withdraw > 0) {
                $instruments[$id]['internal_withdraw'] += $withdraw;
            } else {
                $instruments[$id]['deposit_total'] += $deposit;
                $instruments[$id]['withdraw_total'] += $withdraw;
            }
            if (($row['voucher_link_status'] ?? '') !== 'LINKED') {
                $instruments[$id]['unlinked_amount'] += $deposit + $withdraw;
            }
            $at = (string) ($row['transaction_datetime'] ?? '');
            if ($at !== '' && ($instruments[$id]['last_transaction_at'] === null || $at > $instruments[$id]['last_transaction_at'])) {
                $instruments[$id]['last_transaction_at'] = $at;
            }
            $dailyRow = $this->dailyRow($row, $instruments[$id]);
            $dailyRow['is_internal_transfer'] = $transfer !== null;
            $dailyRows[] = $dailyRow;
        }

        foreach ($instruments as &$instrument) {
            $instrument['ending_balance'] = $instrument['opening_balance']
                + $instrument['deposit_total']
                - $instrument['withdraw_total']
                + $instrument['internal_deposit']
                - $instrument['internal_withdraw'];
            $instrument['available_balance'] = $instrument['ending_balance'];
            $instrument['balance_difference'] = $instrument['ending_balance'] - $instrument['accounting_balance'];
            $query = ['date_start' => $date, 'date_end' => $date];
            if ($instrument['bank_account_id'] !== '') {
                $query['bank_account_id'] = $instrument['bank_account_id'];
            }
            $instrument['transactions_url'] = '/ledger/funds/account-transactions?' . http_build_query($query);
        }
        unset($instrument);

        $paymentProjection = $this->paymentProjection($date);
        foreach ($instruments as &$instrument) {
            $bankAccountId = (string) $instrument['bank_account_id'];
            $scheduled = (float) ($paymentProjection['by_account'][$bankAccountId] ?? 0);
            $instrument['scheduled_payment'] = $scheduled;
            $instrument['available_balance'] = $instrument['ending_balance'] - $scheduled;
        }
        unset($instrument);

        $instrumentRows = array_values($instruments);
        $instrumentRows = array_values(array_filter($instrumentRows, fn(array $row): bool => $this->matchesInstrument($row, $filters)));
        $dailyRows = array_values(array_filter($dailyRows, fn(array $row): bool => $this->matchesTransaction($row, $filters)));
        usort($dailyRows, static fn(array $a, array $b): int => ((float) $b['amount']) <=> ((float) $a['amount']));

        $summary = [
            'opening_balance' => array_sum(array_column($instrumentRows, 'opening_balance')),
            'deposit_total' => array_sum(array_column($instrumentRows, 'deposit_total')),
            'withdraw_total' => array_sum(array_column($instrumentRows, 'withdraw_total')),
            'ending_balance' => array_sum(array_column($instrumentRows, 'ending_balance')),
            'accounting_balance' => array_sum(array_column($instrumentRows, 'accounting_balance')),
            'balance_difference' => array_sum(array_column($instrumentRows, 'balance_difference')),
            'scheduled_payment' => array_sum(array_column($instrumentRows, 'scheduled_payment')),
            'available_funds' => array_sum(array_column($instrumentRows, 'available_balance')),
            'internal_deposit' => array_sum(array_column($instrumentRows, 'internal_deposit')),
            'internal_withdraw' => array_sum(array_column($instrumentRows, 'internal_withdraw')),
        ];
        $unlinked = $this->unlinkedSummary($dailyRows);
        $integrity = abs(
            $summary['ending_balance']
            - (
                $summary['opening_balance']
                + $summary['deposit_total']
                - $summary['withdraw_total']
                + $summary['internal_deposit']
                - $summary['internal_withdraw']
            )
        ) < 0.01;

        return [
            'filters' => $filters,
            'summary' => $summary,
            'instruments' => $instrumentRows,
            'transactions' => $dailyRows,
            'unlinked' => $unlinked,
            'unassigned' => $unassigned,
            'payment_schedule' => $paymentProjection['summary'],
            'internal_transfer' => [
                'available' => true,
                'confirmed_evidence_count' => count($confirmedTransfers),
                'message' => '사용자가 확정한 내부이체만 별도 집계하며 미확정 후보는 일반 입출금으로 유지합니다.',
            ],
            'integrity' => ['valid' => $integrity],
            'company_name' => (string) (($this->company->getOne()['company_name_ko'] ?? '') ?: ''),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function filterOptions(): array
    {
        return ['accounts' => $this->accounts->getList([['field' => 'is_active', 'value' => '1']])];
    }

    public function spreadsheet(array $request): Spreadsheet
    {
        $report = $this->report($request);
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('자금일보');
        $s = $report['summary'];
        $sheet->fromArray([
            [$report['company_name']], ['자금일보'], ['기준일', $report['filters']['report_date'], '출력일시', $report['generated_at']],
            ['전일 마감잔액', $s['opening_balance'], '당일 입금', $s['deposit_total'], '당일 출금', $s['withdraw_total']],
            ['당일 마감잔액', $s['ending_balance'], '지급예정액', $s['scheduled_payment'], '지급 후 가용자금', $s['available_funds']],
            [],
        ], null, 'A1');
        $headers = ['자금구분', '금융기관/보관처', '계좌명/자금수단', '계좌번호', '전일 실제잔액', '당일 실제입금', '당일 실제출금', '당일 실제잔액', '당일 회계잔액', '실제·회계 차이', '미연결금액', '지급예정액', '지급 후 가용잔액', '최종거래일시'];
        $rows = array_map(static fn(array $r): array => [
            $r['fund_type'], $r['bank_name'], $r['account_name'], $r['account_number'], $r['opening_balance'],
            $r['deposit_total'], $r['withdraw_total'], $r['ending_balance'], $r['accounting_balance'], $r['balance_difference'], $r['unlinked_amount'],
            $r['scheduled_payment'], $r['available_balance'], $r['last_transaction_at'],
        ], $report['instruments']);
        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A7');
        $start = 9 + count($rows);
        ExcelValueFormatterHelper::writeTable($sheet, ['거래일시', '자금수단', '구분', '거래내용', '거래처', '프로젝트', '입금', '출금', '연결상태', '전표번호', '메모'], array_map(static fn(array $r): array => [
            $r['transaction_datetime'], $r['account_name'], $r['direction_label'], $r['description'], $r['client_name'],
            $r['project_name'], $r['deposit_amount'], $r['withdraw_amount'], $r['link_label'], $r['voucher_no'], $r['memo'],
        ], $report['transactions']), 'A' . $start);
        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        return $book;
    }

    public function filters(array $request): array
    {
        $date = trim((string) ($request['report_date'] ?? date('Y-m-d')));
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            $date = date('Y-m-d');
        }
        return [
            'report_date' => $date,
            'fund_type' => strtoupper(trim((string) ($request['fund_type'] ?? ''))),
            'bank_account_id' => trim((string) ($request['bank_account_id'] ?? '')),
            'bank_name' => trim((string) ($request['bank_name'] ?? '')),
            'business_unit' => trim((string) ($request['business_unit'] ?? '')),
            'project' => trim((string) ($request['project'] ?? '')),
            'direction' => strtoupper(trim((string) ($request['direction'] ?? ''))),
            'unlinked_only' => (string) ($request['unlinked_only'] ?? '') === '1',
            'q' => trim((string) ($request['q'] ?? '')),
        ];
    }

    private function dailyRow(array $row, array $instrument): array
    {
        $deposit = (float) ($row['deposit_amount'] ?? 0);
        $withdraw = (float) ($row['withdraw_amount'] ?? 0);
        return [
            'id' => (string) ($row['id'] ?? ''), 'bank_account_id' => (string) ($row['bank_account_id'] ?? ''),
            'fund_type' => $instrument['fund_type'], 'bank_name' => $instrument['bank_name'],
            'transaction_datetime' => (string) ($row['transaction_datetime'] ?? ''),
            'account_name' => (string) ($row['bank_account_name'] ?? ''), 'direction' => $deposit > 0 ? 'IN' : 'OUT',
            'direction_label' => $deposit > 0 ? '입금' : '출금', 'description' => (string) ($row['description'] ?? ''),
            'client_name' => (string) ($row['client_name'] ?? ''), 'project_name' => (string) ($row['project_name'] ?? ''),
            'business_unit' => (string) ($row['business_unit'] ?? ''),
            'deposit_amount' => $deposit, 'withdraw_amount' => $withdraw, 'amount' => max($deposit, $withdraw),
            'is_internal_transfer' => null, 'link_status' => (string) ($row['voucher_link_status'] ?? 'UNLINKED'),
            'link_label' => ($row['voucher_link_status'] ?? '') === 'LINKED' ? '연결' : '미연결',
            'voucher_no' => (string) ($row['voucher_no'] ?? ''), 'memo' => (string) ($row['memo'] ?? ''),
        ];
    }

    private function unlinkedSummary(array $rows): array
    {
        $result = ['deposit_count' => 0, 'deposit_amount' => 0.0, 'withdraw_count' => 0, 'withdraw_amount' => 0.0, 'total_count' => 0, 'total_amount' => 0.0];
        foreach ($rows as $row) {
            if ($row['link_status'] === 'LINKED') continue;
            $key = $row['direction'] === 'IN' ? 'deposit' : 'withdraw';
            $result[$key . '_count']++;
            $result[$key . '_amount'] += (float) $row['amount'];
            $result['total_count']++;
            $result['total_amount'] += (float) $row['amount'];
        }
        return $result;
    }

    private function matchesInstrument(array $row, array $filters): bool
    {
        return ($filters['fund_type'] === '' || $filters['fund_type'] === ($row['fund_type'] === '현금' ? 'CASH' : 'BANK'))
            && ($filters['bank_account_id'] === '' || $filters['bank_account_id'] === $row['bank_account_id'])
            && ($filters['bank_name'] === '' || str_contains($row['bank_name'], $filters['bank_name']));
    }

    private function matchesTransaction(array $row, array $filters): bool
    {
        if ($filters['fund_type'] !== '' && $filters['fund_type'] !== ($row['fund_type'] === '현금' ? 'CASH' : 'BANK')) return false;
        if ($filters['bank_account_id'] !== '' && $filters['bank_account_id'] !== $row['bank_account_id']) return false;
        if ($filters['bank_name'] !== '' && !str_contains($row['bank_name'], $filters['bank_name'])) return false;
        if ($filters['direction'] !== '' && $filters['direction'] !== $row['direction']) return false;
        if ($filters['unlinked_only'] && $row['link_status'] === 'LINKED') return false;
        if ($filters['business_unit'] !== '' && $filters['business_unit'] !== $row['business_unit']) return false;
        if ($filters['project'] !== '' && !str_contains($row['project_name'], $filters['project'])) return false;
        if ($filters['q'] === '') return true;
        return str_contains(implode(' ', [$row['description'], $row['client_name'], $row['project_name'], $row['memo'], $row['voucher_no']]), $filters['q']);
    }

    private function bankNames(): array
    {
        $map = [];
        foreach ($this->codes->getByGroup('BANK') as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['code_name'] ?? ''));
            if ($code !== '' && $name !== '') $map[$code] = $name;
        }
        return $map;
    }

    private function isCash(array $account): bool
    {
        return strtoupper(trim((string) ($account['account_type'] ?? ''))) === 'CASH';
    }

    private function paymentProjection(string $reportDate): array
    {
        $summary = [
            'available' => true,
            'today' => 0.0,
            'within_7_days' => 0.0,
            'within_30_days' => 0.0,
            'overdue' => 0.0,
            'applied_total' => 0.0,
        ];
        $byAccount = [];
        $sevenDays = date('Y-m-d', strtotime($reportDate . ' +7 days'));
        $thirtyDays = date('Y-m-d', strtotime($reportDate . ' +30 days'));
        foreach ($this->paymentSchedules->reportProjection($reportDate) as $row) {
            if ((int) $row['is_on_hold'] === 1) {
                continue;
            }
            $remaining = (float) $row['remaining_amount'];
            if ($remaining <= 0) {
                continue;
            }
            $dueDate = (string) $row['payment_due_date'];
            $accountId = trim((string) ($row['payment_bank_account_id'] ?? ''));
            if ($accountId !== '') {
                $byAccount[$accountId] = ($byAccount[$accountId] ?? 0) + $remaining;
            }
            $summary['applied_total'] += $remaining;
            if ($dueDate < $reportDate) {
                $summary['overdue'] += $remaining;
            }
            if ($dueDate === $reportDate) {
                $summary['today'] += $remaining;
            }
            if ($dueDate >= $reportDate && $dueDate <= $sevenDays) {
                $summary['within_7_days'] += $remaining;
            }
            if ($dueDate >= $reportDate && $dueDate <= $thirtyDays) {
                $summary['within_30_days'] += $remaining;
            }
        }
        return ['summary' => $summary, 'by_account' => $byAccount];
    }
}
