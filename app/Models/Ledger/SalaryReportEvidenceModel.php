<?php
namespace App\Models\Ledger;
use PDO;
class SalaryReportEvidenceModel extends EvidenceWriteModel
{
    protected string $table = 'ledger_evidence_salary_report';
    private ?array $writableColumns = null;

    public function __construct(private readonly PDO $db){parent::__construct($db);}
    public function findBySource(string $id, bool $lock = false): array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=:id ORDER BY sort_no,id'
            . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute([':id' => $id]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findBySourceItem(string $sourceId, string $itemId, bool $lock = false): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=:source_id AND regular_employment_income_item_id=:item_id LIMIT 1'
            . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute([':source_id' => $sourceId, ':item_id' => $itemId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function nextSortNo():int{return max(1,(int)$this->db->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM ledger_evidence_salary_report')->fetchColumn());}
    public function insert(array $data): void
    {
        $data = array_intersect_key($data, array_flip($this->writableColumns()));
        if ($data === []) {
            throw new \RuntimeException('급여 증빙에 저장할 수 있는 컬럼이 없습니다.');
        }
        $columns = array_keys($data);
        $statement = $this->db->prepare(
            'INSERT INTO ledger_evidence_salary_report (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')'
        );
        $statement->execute(array_combine(array_map(static fn(string $column): string => ':' . $column, $columns), array_values($data)));
    }

    public function insertLine(array $data): void
    {
        $columns = array_keys($data);
        $statement = $this->db->prepare(
            'INSERT INTO ledger_evidence_salary_report_lines (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')'
        );
        $statement->execute(array_combine(array_map(static fn(string $column): string => ':' . $column, $columns), array_values($data)));
    }

    private function writableColumns(): array
    {
        if ($this->writableColumns !== null) {
            return $this->writableColumns;
        }
        $statement = $this->db->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name'
        );
        $statement->execute([':table_name' => $this->table]);
        return $this->writableColumns = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
