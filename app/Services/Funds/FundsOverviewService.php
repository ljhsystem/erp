<?php

namespace App\Services\Funds;

use App\Models\Funds\BankTransactionReportModel;
use App\Models\Funds\FundsBalanceModel;
use App\Models\System\BankAccountModel;
use App\Models\System\CodeModel;
use PDO;

class FundsOverviewService
{
    private BankAccountModel $accountModel;
    private BankTransactionReportModel $transactionModel;
    private CodeModel $codeModel;
    private FundsBalanceModel $balanceModel;

    public function __construct(PDO $pdo)
    {
        $this->accountModel = new BankAccountModel($pdo);
        $this->transactionModel = new BankTransactionReportModel($pdo);
        $this->codeModel = new CodeModel($pdo);
        $this->balanceModel = new FundsBalanceModel($pdo);
    }

    public function overview(): array
    {
        $accounts = $this->accountModel->getList([
            ['field' => 'is_active', 'value' => '1'],
        ]);
        $bankNames = $this->bankNameMap();
        $transactions = $this->transactionModel->rows();
        $snapshots = $this->snapshotsByAccount($transactions);
        $accountingBalances = $this->balanceModel->accountingBalancesByBankAccount();
        $groups = $this->emptyGroups();
        $totalBalance = 0.0;
        $lastTransactionAt = null;
        $lastTransactionAccountId = null;

        foreach ($accounts as $account) {
            $accountId = trim((string) ($account['id'] ?? ''));
            if ($accountId === '') {
                continue;
            }

            $snapshot = $snapshots[$accountId] ?? [];
            $balance = (float) ($snapshot['balance'] ?? 0);
            $accountingBalance = (float) ($accountingBalances[$accountId]['balance'] ?? 0);
            $lastAt = $snapshot['last_transaction_at'] ?? null;
            $groupKey = $this->groupKey($account);
            $storedBankName = trim((string) ($account['bank_name'] ?? ''));
            $groups[$groupKey]['accounts'][] = [
                'id' => $accountId,
                'account_name' => (string) ($account['account_name'] ?? ''),
                'bank_name' => $bankNames[$storedBankName] ?? $storedBankName,
                'account_number' => (string) ($account['account_number'] ?? ''),
                'account_type' => (string) ($account['account_type'] ?? ''),
                'currency' => (string) ($account['currency'] ?? 'KRW'),
                'balance' => $balance,
                'actual_balance' => $balance,
                'accounting_balance' => $accountingBalance,
                'balance_difference' => $balance - $accountingBalance,
                'last_transaction_at' => $lastAt,
                'transaction_count' => (int) ($snapshot['transaction_count'] ?? 0),
                'transactions_url' => '/ledger/funds/account-transactions?bank_account_id=' . rawurlencode($accountId),
            ];
            $groups[$groupKey]['total_balance'] += $balance;
            $totalBalance += $balance;

            if ($lastAt !== null && ($lastTransactionAt === null || strcmp($lastAt, $lastTransactionAt) > 0)) {
                $lastTransactionAt = $lastAt;
                $lastTransactionAccountId = $accountId;
            }
        }

        foreach ($groups as &$group) {
            usort($group['accounts'], static function (array $left, array $right): int {
                $balanceOrder = (float) ($right['balance'] ?? 0) <=> (float) ($left['balance'] ?? 0);
                if ($balanceOrder !== 0) {
                    return $balanceOrder;
                }

                return strcmp((string) ($left['account_name'] ?? ''), (string) ($right['account_name'] ?? ''));
            });
        }
        unset($group);

        $cashGroup = $groups['cash'];
        unset($groups['cash']);
        $operatingAccount = null;
        foreach ($groups as $group) {
            if (($group['accounts'] ?? []) !== []) {
                $operatingAccount = $group['accounts'][0];
                break;
            }
        }
        $cashAccount = $cashGroup['accounts'][0] ?? null;

        return [
            'total_balance' => $totalBalance,
            'actual_balance' => $totalBalance,
            'accounting_balance' => array_sum(array_column($accountingBalances, 'balance')),
            'balance_difference' => $totalBalance - array_sum(array_column($accountingBalances, 'balance')),
            'account_count' => count($accounts),
            'last_transaction_at' => $lastTransactionAt,
            'cash_balance' => (float) ($cashGroup['total_balance'] ?? 0),
            'cash_account_count' => count($cashGroup['accounts'] ?? []),
            'cash_transactions_url' => $cashAccount['transactions_url'] ?? '/ledger/funds',
            'operating_transactions_url' => '/ledger/funds/account-transactions',
            'last_transaction_url' => $lastTransactionAccountId !== null
                ? '/ledger/funds/account-transactions?bank_account_id=' . rawurlencode($lastTransactionAccountId)
                : '/ledger/funds',
            'groups' => array_values(array_filter(
                $groups,
                static fn(array $group): bool => $group['accounts'] !== []
            )),
        ];
    }

    public function account(string $id): ?array
    {
        $account = $this->accountModel->getById($id);
        if (!$account || !empty($account['deleted_at']) || (int) ($account['is_active'] ?? 1) !== 1) {
            return null;
        }

        return $account;
    }

    private function snapshotsByAccount(array $transactions): array
    {
        $snapshots = [];
        foreach ($transactions as $row) {
            $accountId = trim((string) ($row['bank_account_id'] ?? ''));
            if ($accountId === '') {
                continue;
            }

            $transactionAt = trim((string) ($row['transaction_datetime'] ?? $row['transaction_date'] ?? ''));
            $depositAmount = (float) ($row['deposit_amount'] ?? 0);
            $withdrawAmount = (float) ($row['withdraw_amount'] ?? 0);

            if (!isset($snapshots[$accountId])) {
                $snapshots[$accountId] = [
                    'balance' => 0.0,
                    'last_transaction_at' => $transactionAt !== '' ? $transactionAt : null,
                    'transaction_count' => 0,
                ];
            }

            $snapshots[$accountId]['balance'] += $depositAmount - $withdrawAmount;
            $snapshots[$accountId]['transaction_count']++;
            $currentLastAt = (string) ($snapshots[$accountId]['last_transaction_at'] ?? '');
            if ($transactionAt !== '' && ($currentLastAt === '' || strcmp($transactionAt, $currentLastAt) > 0)) {
                $snapshots[$accountId]['last_transaction_at'] = $transactionAt;
            }
        }

        return $snapshots;
    }

    private function emptyGroups(): array
    {
        return [
            'demand_deposit' => ['key' => 'demand_deposit', 'label' => '입출식예금', 'icon' => 'bi-bank', 'total_balance' => 0.0, 'accounts' => []],
            'savings' => ['key' => 'savings', 'label' => '저축성예금', 'icon' => 'bi-piggy-bank', 'total_balance' => 0.0, 'accounts' => []],
            'loan' => ['key' => 'loan', 'label' => '대출', 'icon' => 'bi-building-down', 'total_balance' => 0.0, 'accounts' => []],
            'cash' => ['key' => 'cash', 'label' => '현금', 'icon' => 'bi-cash-stack', 'total_balance' => 0.0, 'accounts' => []],
        ];
    }

    private function bankNameMap(): array
    {
        $map = [];
        foreach ($this->codeModel->getByGroup('BANK') as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['code_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if ($code !== '') {
                $map[$code] = $name;
            }
            $map[$name] = $name;
        }

        return $map;
    }

    private function groupKey(array $account): string
    {
        $type = strtoupper(trim((string) ($account['account_type'] ?? '')));
        return match ($type) {
            'CASH' => 'cash',
            'LOAN' => 'loan',
            'INSTALLMENT_SAVINGS', 'TERM_DEPOSIT' => 'savings',
            'CURRENT_DEPOSIT', 'NORMAL_DEPOSIT', 'OTHER' => 'demand_deposit',
            default => 'demand_deposit',
        };
    }
}
