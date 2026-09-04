<?php

declare(strict_types=1);

namespace App\Models\Institution;

use PDO;

final class DailyEmploymentIncomeCalculationResultModel
{
    public function __construct(private readonly PDO $db) {}

    public function available(): bool
    {
        $statement=$this->db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() "
            . "AND TABLE_NAME IN('institution_daily_employment_income_calculation_revisions','institution_daily_employment_income_calculation_results')"
        );
        return (int)$statement->fetchColumn() === 2;
    }

    public function latestRevision(string $documentId, bool $forUpdate = false): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM institution_daily_employment_income_calculation_revisions '
            . 'WHERE daily_employment_income_id=:document_id ORDER BY revision_no DESC LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute(['document_id' => $documentId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function markStale(string $id, string $actor): int
    {
        $statement = $this->db->prepare(
            "UPDATE institution_daily_employment_income_calculation_revisions "
            . "SET status_code='STALE',updated_at=NOW(),updated_by=:actor "
            . "WHERE id=:id AND status_code='CALCULATED'"
        );
        $statement->execute(['id' => $id, 'actor' => $actor]);
        return $statement->rowCount();
    }

    public function latestWithResults(string $documentId): ?array
    {
        $revision = $this->latestRevision($documentId);
        if ($revision === null) return null;
        $statement = $this->db->prepare(
            'SELECT result_row.*,workplace_row.workplace_name AS workplace_name,eligibility_standard.value_data AS eligibility_policy_value_data '
            . 'FROM institution_daily_employment_income_calculation_results result_row '
            . 'LEFT JOIN institution_social_insurance_workplaces workplace_row ON workplace_row.id=result_row.social_insurance_workplace_id '
            . 'LEFT JOIN system_statutory_standards eligibility_standard ON eligibility_standard.id=result_row.eligibility_revision_id '
            . 'WHERE result_row.calculation_revision_id=:revision_id ORDER BY result_row.result_type_code,result_row.id'
        );
        $statement->execute(['revision_id' => $revision['id']]);
        $revision['results'] = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $revision;
    }

    public function insertRevision(array $row): void
    {
        $this->insert('institution_daily_employment_income_calculation_revisions', $row);
    }

    public function insertResult(array $row): void
    {
        $this->insert('institution_daily_employment_income_calculation_results', $row);
    }

    private function insert(string $table, array $row): void
    {
        $columns = array_keys($row);
        $statement = $this->db->prepare(
            'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) '
            . 'VALUES (:' . implode(',:', $columns) . ')'
        );
        $statement->execute(array_combine(
            array_map(static fn(string $column): string => ':' . $column, $columns),
            array_values($row)
        ));
    }
}
