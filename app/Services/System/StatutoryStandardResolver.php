<?php

namespace App\Services\System;

use App\Models\System\StatutoryStandardModel;
use PDO;

class StatutoryStandardResolver
{
    private StatutoryStandardModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new StatutoryStandardModel($db);
    }

    public function resolve(string $type, string $date): array
    {
        $standard = $this->resolveOptional($type, $date);
        if ($standard === null) {
            throw new \RuntimeException('기준일에 맞는 법정기준이 없습니다.');
        }

        return $standard;
    }

    public function resolveOptional(string $type, string $date): ?array
    {
        if ($type === '' || !$this->isDate($date)) {
            throw new \InvalidArgumentException('법정기준 종류와 기준일이 필요합니다.');
        }

        $rows = $this->model->rows(
            'SELECT * FROM system_statutory_standards WHERE standard_type_code=:type'
            . ' AND effective_from<=:date_from AND (effective_to IS NULL OR effective_to>=:date_to)',
            [':type' => $type, ':date_from' => $date, ':date_to' => $date]
        );
        if (count($rows) > 1) {
            throw new \RuntimeException('기준일이 중복되는 법정기준이 있습니다.');
        }
        if ($rows === []) {
            return null;
        }

        $rows[0]['value_data'] = json_decode((string) $rows[0]['value_data'], true) ?: [];
        return $rows[0];
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
