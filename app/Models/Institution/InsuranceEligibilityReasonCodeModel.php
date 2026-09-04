<?php

declare(strict_types=1);

namespace App\Models\Institution;

use PDO;

final class InsuranceEligibilityReasonCodeModel
{
    public const CODE_GROUP = 'INSURANCE_ELIGIBILITY_REASON';

    public function __construct(private readonly PDO $db)
    {
    }

    public function find(string $reasonCode): ?array
    {
        $statement = $this->db->prepare(
            'SELECT code,code_name,note,extra_data,is_active FROM system_codes '
            . 'WHERE code_group=:code_group AND code=:code LIMIT 1'
        );
        $statement->execute(['code_group' => self::CODE_GROUP, 'code' => $reasonCode]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
