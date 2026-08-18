<?php

namespace App\Repositories\System;

use PDO;

class CardDependencyRepository
{
    private const DIRECT_REFERENCES = [
        ['ledger_evidence_bank_transaction', 'card_id', '은행거래 증빙'],
        ['ledger_evidence_card_hometax', 'card_id', '카드 홈택스 증빙'],
        ['ledger_evidence_card_statement', 'card_id', '카드 명세 증빙'],
        ['ledger_evidence_cash_receipt', 'card_id', '현금영수증 증빙'],
        ['ledger_evidence_employee_personal_expense', 'card_id', '직원 개인경비 증빙'],
        ['ledger_evidence_tax_invoice', 'card_id', '세금계산서 증빙'],
        ['ledger_evidence_tax_invoice_manual', 'card_id', '수기 세금계산서 증빙'],
        ['ledger_transactions', 'card_id', '거래'],
        ['ledger_vouchers', 'summary_card_id', '전표'],
    ];

    private array $columnExistence = [];

    public function __construct(private readonly PDO $db)
    {
    }

    public function findReferences(string $cardId): array
    {
        $references = [];

        foreach (self::DIRECT_REFERENCES as [$table, $column, $label]) {
            if (!$this->columnExists($table, $column)) {
                continue;
            }

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :card_id");
            $stmt->execute([':card_id' => $cardId]);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) {
                $references[] = ['source' => $table, 'label' => $label, 'count' => $count];
            }
        }

        if (
            $this->columnExists('ledger_voucher_line_refs', 'ref_id')
            && $this->columnExists('ledger_voucher_line_refs', 'ref_target')
        ) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM ledger_voucher_line_refs WHERE ref_id = :card_id AND ref_target = 'CARD'"
            );
            $stmt->execute([':card_id' => $cardId]);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) {
                $references[] = [
                    'source' => 'ledger_voucher_line_refs',
                    'label' => '전표 보조참조',
                    'count' => $count,
                ];
            }
        }

        return $references;
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistence)) {
            return $this->columnExistence[$key];
        }

        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1'
        );
        $stmt->execute([':table' => $table, ':column' => $column]);

        return $this->columnExistence[$key] = (bool) $stmt->fetchColumn();
    }
}
