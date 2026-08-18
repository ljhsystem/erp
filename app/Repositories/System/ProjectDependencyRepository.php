<?php

namespace App\Repositories\System;

use PDO;

class ProjectDependencyRepository
{
    private const DIRECT_REFERENCES = [
        ['approval_personal_expense_items', 'project_id', '개인경비'],
        ['institution_attendance_daily_records', 'primary_project_id_snapshot', '근태 일별 스냅샷'],
        ['institution_attendance_work_segments', 'project_id', '근태 근무구간'],
        ['institution_employment_contracts', 'project_id', '근로계약'],
        ['institution_job_assignments_project_histories', 'project_id', '프로젝트 배치 이력'],
        ['institution_job_assignments_workplace_histories', 'project_id', '근무지 이력'],
        ['institution_personnel_actions_changes', 'project_id', '인사발령 프로젝트 변경'],
        ['institution_personnel_actions_changes', 'workplace_project_id', '인사발령 근무지 변경'],
        ['ledger_evidence_bank_transaction', 'project_id', '은행거래 증빙'],
        ['ledger_evidence_card_hometax', 'project_id', '카드 홈택스 증빙'],
        ['ledger_evidence_card_statement', 'project_id', '카드 명세 증빙'],
        ['ledger_evidence_cash_receipt', 'project_id', '현금영수증 증빙'],
        ['ledger_evidence_employee_personal_expense', 'project_id', '직원 개인경비 증빙'],
        ['ledger_evidence_tax_invoice', 'project_id', '세금계산서 증빙'],
        ['ledger_evidence_tax_invoice_manual', 'project_id', '수기 세금계산서 증빙'],
        ['ledger_journal_learning_events', 'project_id', '분개 학습 이력'],
        ['ledger_journal_recent_patterns', 'project_id', '최근 분개 패턴'],
        ['ledger_payment_schedules', 'project_id', '지급예정'],
        ['ledger_transactions', 'project_id', '거래'],
        ['ledger_vouchers', 'summary_project_id', '전표'],
    ];

    private const PROJECT_REF_TARGETS = ['PROJECT'];
    private array $columnExistence = [];

    public function __construct(private readonly PDO $db)
    {
    }

    public function findReferences(string $projectId): array
    {
        $references = [];
        foreach (self::DIRECT_REFERENCES as [$table, $column, $label]) {
            if (!$this->columnExists($table, $column)) continue;
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :project_id");
            $stmt->execute([':project_id' => $projectId]);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) $references[] = ['source' => $table, 'label' => $label, 'count' => $count];
        }

        if ($this->columnExists('ledger_voucher_line_refs', 'ref_id') && $this->columnExists('ledger_voucher_line_refs', 'ref_target')) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM ledger_voucher_line_refs WHERE ref_id = :project_id AND ref_target = 'PROJECT'");
            $stmt->execute([':project_id' => $projectId]);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) $references[] = ['source' => 'ledger_voucher_line_refs', 'label' => '전표 보조참조', 'count' => $count];
        }

        return $references;
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistence)) return $this->columnExistence[$key];
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1');
        $stmt->execute([':table' => $table, ':column' => $column]);
        return $this->columnExistence[$key] = (bool) $stmt->fetchColumn();
    }
}
