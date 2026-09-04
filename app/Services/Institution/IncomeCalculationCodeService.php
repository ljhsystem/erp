<?php

declare(strict_types=1);

namespace App\Services\Institution;

use PDO;

final class IncomeCalculationCodeService
{
    public const STATUTORY_SOURCE_GROUP = 'INCOME_STATUTORY_CALCULATION_SOURCE';
    public const ACTUAL_SOURCE_GROUP = 'INCOME_ACTUAL_APPLICATION_SOURCE';
    public const PAYMENT_STATUS_GROUP = 'INCOME_PAYMENT_CONFIRMATION_STATUS';
    public const STATUTORY_STATUS_GROUP = 'INCOME_STATUTORY_CALCULATION_STATUS';

    public function __construct(private readonly PDO $db)
    {
    }

    public function id(string $group, string $code): string
    {
        $statement = $this->db->prepare(
            'SELECT id FROM system_codes WHERE code_group=:code_group AND code=:code AND is_active=1 LIMIT 1'
        );
        $statement->execute([':code_group' => $group, ':code' => $code]);
        $id = $statement->fetchColumn();
        if (!is_string($id) || $id === '') {
            throw new \RuntimeException('소득계산 공용코드를 확인할 수 없습니다.');
        }
        return $id;
    }

    public function assertIdInGroup(?string $id, string $group): void
    {
        if ($id === null) return;
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM system_codes WHERE id=:id AND code_group=:code_group AND is_active=1'
        );
        $statement->execute([':id' => $id, ':code_group' => $group]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new \InvalidArgumentException('소득계산 원천코드의 공용 코드그룹이 일치하지 않습니다.');
        }
    }
}
