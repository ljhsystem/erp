<?php

namespace App\Repositories\System;

use PDO;

class WorkTeamDependencyRepository
{
    private const DIRECT_REFERENCES = [
        ['system_work_team_members', 'team_id', '작업팀 구성원'],
        ['ledger_evidence_bank_transaction', 'team_id', '은행거래 증빙'],
        ['ledger_evidence_card_hometax', 'team_id', '카드 홈택스 증빙'],
        ['ledger_evidence_card_statement', 'team_id', '카드 명세 증빙'],
        ['ledger_evidence_cash_receipt', 'team_id', '현금영수증 증빙'],
        ['ledger_evidence_employee_personal_expense', 'team_id', '직원 개인경비 증빙'],
        ['ledger_evidence_tax_invoice', 'team_id', '세금계산서 증빙'],
        ['ledger_evidence_tax_invoice_manual', 'team_id', '수기 세금계산서 증빙'],
        ['ledger_transactions', 'team_id', '거래'],
    ];

    private array $columnExistence = [];

    public function __construct(private readonly PDO $db)
    {
    }

    public function findReferences(string $teamId): array
    {
        $references = [];
        foreach (self::DIRECT_REFERENCES as [$table, $column, $label]) {
            if (!$this->columnExists($table, $column)) continue;
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :team_id");
            $stmt->execute([':team_id' => $teamId]);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) $references[] = ['source' => $table, 'label' => $label, 'count' => $count];
        }

        if ($this->columnExists('ledger_voucher_line_refs', 'ref_id') && $this->columnExists('ledger_voucher_line_refs', 'ref_target')) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM ledger_voucher_line_refs WHERE ref_id = :team_id AND ref_target = 'TEAM'");
            $stmt->execute([':team_id' => $teamId]);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) $references[] = ['source' => 'ledger_voucher_line_refs', 'label' => '전표 보조참조', 'count' => $count];
        }
        return $references;
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        if (array_key_exists($key, $this->columnExistence)) return $this->columnExistence[$key];
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1');
        $stmt->execute([':table' => $table, ':column' => $column]);
        return $this->columnExistence[$key] = (bool) $stmt->fetchColumn();
    }
}
