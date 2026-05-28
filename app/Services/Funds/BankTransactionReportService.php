<?php

namespace App\Services\Funds;

use App\Models\Funds\BankTransactionReportModel;
use PDO;

class BankTransactionReportService
{
    private BankTransactionReportModel $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new BankTransactionReportModel($pdo);
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
