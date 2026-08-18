<?php

namespace App\Models\Institution;

use PDO;

class PayComponentModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function activeForDate(string $date): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM institution_employment_contracts_pay_components
             WHERE is_active = 1 AND deleted_at IS NULL
               AND (effective_from IS NULL OR effective_from <= :date_from)
               AND (effective_to IS NULL OR effective_to >= :date_to)
             ORDER BY sort_no ASC, component_name ASC, id ASC'
        );
        $stmt->execute([':date_from' => $date, ':date_to' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findActive(string $id, string $date): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM institution_employment_contracts_pay_components
             WHERE id = :id AND is_active = 1 AND deleted_at IS NULL
               AND (effective_from IS NULL OR effective_from <= :date_from)
               AND (effective_to IS NULL OR effective_to >= :date_to) LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':date_from' => $date, ':date_to' => $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
