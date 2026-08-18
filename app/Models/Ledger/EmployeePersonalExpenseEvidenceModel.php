<?php

namespace App\Models\Ledger;

use PDO;

class EmployeePersonalExpenseEvidenceModel
{
    private const TABLE = 'ledger_evidence_employee_personal_expense';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findBySourceItemId(string $sourceItemId, bool $forUpdate = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE source_personal_expense_item_id = :source_item_id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $stmt->execute([':source_item_id' => $sourceItemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function nextSortNo(): int
    {
        return max(1, (int) $this->pdo->query('SELECT COALESCE(MAX(sort_no), 0) + 1 FROM ' . self::TABLE)->fetchColumn());
    }

    public function insert(array $data): void
    {
        $columns = ['id','sort_no','external_key','source_type','import_type','source_personal_expense_item_id','business_unit','transaction_direction','operation_type','client_id','project_id','bank_account_id','card_id','team_id','employee_id','raw_expense_date','raw_expense_category','raw_payment_method','raw_receipt_type','raw_merchant_company_name','raw_merchant_business_number','raw_merchant_representative','raw_merchant_address','raw_merchant_address_detail','raw_merchant_phone','raw_item_name','raw_specification','raw_unit','raw_quantity','raw_unit_price','raw_supply_amount','raw_vat_amount','raw_total_amount','raw_description','raw_memo','evidence_status','created_by','updated_by'];
        $stmt = $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_map(static fn($c) => ':' . $c, $columns)) . ')');
        $params=[]; foreach($columns as $column) $params[':' . $column] = $data[$column] ?? null;
        $stmt->execute($params);
    }
}
