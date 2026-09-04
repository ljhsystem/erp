<?php

namespace App\Services\Institution;

use App\Models\Institution\EmploymentRuleModel;
use PDO;

final class EmploymentRuleResolver
{
    private EmploymentRuleModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new EmploymentRuleModel($db);
    }

    public function resolve(string $companyId, string $regulationIdOrCode, string $baseDate): ?array
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $baseDate);
        if (!$date || $date->format('Y-m-d') !== $baseDate) {
            throw new \InvalidArgumentException('기준일을 확인해 주세요.');
        }
        return $this->model->resolve($companyId, $regulationIdOrCode, $baseDate);
    }
}
