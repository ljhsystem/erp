<?php

namespace App\Repositories\System;

use PDO;

class ClientDependencyRepository
{
    private const DIRECT_REFERENCES = [
        ['approval_personal_expense_items', 'client_id', '개인경비'],
        ['ledger_evidence_bank_transaction', 'client_id', '은행거래 증빙'],
        ['ledger_evidence_card_hometax', 'client_id', '카드 홈택스 증빙'],
        ['ledger_evidence_card_statement', 'client_id', '카드 명세 증빙'],
        ['ledger_evidence_cash_receipt', 'client_id', '현금영수증 증빙'],
        ['ledger_evidence_employee_personal_expense', 'client_id', '직원 개인경비 증빙'],
        ['ledger_evidence_tax_invoice', 'client_id', '세금계산서 증빙'],
        ['ledger_evidence_tax_invoice_manual', 'client_id', '수기 세금계산서 증빙'],
        ['ledger_journal_client_account_patterns', 'client_id', '거래처별 분개 패턴'],
        ['ledger_journal_learning_events', 'client_id', '분개 학습 이력'],
        ['ledger_journal_recent_patterns', 'client_id', '최근 분개 패턴'],
        ['ledger_payment_schedules', 'client_id', '지급예정'],
        ['ledger_transactions', 'client_id', '거래'],
        ['ledger_vouchers', 'summary_client_id', '전표'],
        ['system_cards', 'client_id', '카드'],
        ['system_client_histories', 'client_id', '거래처 변경 이력'],
        ['system_client_name_history', 'client_id', '거래처명 이력'],
        ['system_projects', 'client_id', '프로젝트'],
        ['system_work_teams', 'team_leader_client_id', '작업팀'],
        ['system_work_team_members', 'client_id', '작업팀 구성원'],
    ];

    private const POLYMORPHIC_REFERENCES = [
        [
            'table' => 'ledger_voucher_line_refs',
            'id_column' => 'ref_id',
            'type_column' => 'ref_target',
            'types' => ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY', 'PARTNER'],
            'label' => '전표 보조참조',
        ],
    ];

    private PDO $db;
    private array $columnExistence = [];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function findReferences(string $clientId): array
    {
        $references = [];

        foreach (self::DIRECT_REFERENCES as [$table, $column, $label]) {
            if (!$this->columnExists($table, $column)) {
                continue;
            }

            $count = $this->countDirectReference($table, $column, $clientId);
            if ($count > 0) {
                $references[] = [
                    'source' => $table,
                    'label' => $label,
                    'count' => $count,
                ];
            }
        }

        foreach (self::POLYMORPHIC_REFERENCES as $definition) {
            if (
                !$this->columnExists($definition['table'], $definition['id_column'])
                || !$this->columnExists($definition['table'], $definition['type_column'])
            ) {
                continue;
            }

            $count = $this->countPolymorphicReference($definition, $clientId);
            if ($count > 0) {
                $references[] = [
                    'source' => $definition['table'],
                    'label' => $definition['label'],
                    'count' => $count,
                ];
            }
        }

        return $references;
    }

    private function countDirectReference(string $table, string $column, string $clientId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :client_id");
        $stmt->execute([':client_id' => $clientId]);
        return (int) $stmt->fetchColumn();
    }

    private function countPolymorphicReference(array $definition, string $clientId): int
    {
        $typePlaceholders = [];
        $params = [':client_id' => $clientId];

        foreach ($definition['types'] as $index => $type) {
            $placeholder = ':ref_type_' . $index;
            $typePlaceholders[] = $placeholder;
            $params[$placeholder] = $type;
        }

        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` WHERE `%s` = :client_id AND `%s` IN (%s)',
            $definition['table'],
            $definition['id_column'],
            $definition['type_column'],
            implode(', ', $typePlaceholders)
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistence)) {
            return $this->columnExistence[$key];
        }

        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1'
        );
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return $this->columnExistence[$key] = (bool) $stmt->fetchColumn();
    }
}
