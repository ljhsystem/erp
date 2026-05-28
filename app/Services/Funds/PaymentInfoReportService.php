<?php

namespace App\Services\Funds;

use App\Models\Funds\PaymentInfoReportModel;
use PDO;

class PaymentInfoReportService
{
    private PaymentInfoReportModel $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new PaymentInfoReportModel($pdo);
    }

    public function rows(array $filters = []): array
    {
        return $this->model->rows($filters);
    }

    public function summary(array $filters = []): array
    {
        return $this->model->summary($filters);
    }
}
