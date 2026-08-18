<?php

namespace App\Services\Funds;

use App\Models\Funds\BankTransactionReportModel;
use App\Models\System\BankAccountModel;
use PDO;

class BankTransactionReportService
{
    private BankTransactionReportModel $model;
    private BankAccountModel $accountModel;

    public function __construct(PDO $pdo)
    {
        $this->model = new BankTransactionReportModel($pdo);
        $this->accountModel = new BankAccountModel($pdo);
    }

    public function account(string $id): ?array
    {
        $account = $this->accountModel->getById($id);
        if (!$account || !empty($account['deleted_at']) || (int) ($account['is_active'] ?? 1) !== 1) {
            return null;
        }

        return $account;
    }

    public function rows(array $filters = []): array
    {
        return $this->model->rows($filters);
    }

    public function summary(array $filters = []): array
    {
        return $this->model->summary($filters);
    }

    public function find(string $id, bool $includeDeleted = false): ?array
    {
        return $this->model->find($id, $includeDeleted);
    }

    public function hasVoucherLink(string $id): bool
    {
        return $this->model->hasVoucherLink($id);
    }

    public function softDelete(string $id, string $actor): bool
    {
        return $this->model->softDelete($id, $actor);
    }

    public function restore(string $id, string $actor): bool
    {
        return $this->model->restore($id, $actor);
    }
}
