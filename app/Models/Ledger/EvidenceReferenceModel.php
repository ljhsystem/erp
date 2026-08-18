<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class EvidenceReferenceModel
{
    private PDO $db;
    private EvidenceSchemaModel $schema;

    private const CONFIG = [
        'CLIENT' => ['system_clients', ['client_name', 'company_name'], ['id', 'client_name', 'company_name', 'business_number']],
        'PROJECT' => ['system_projects', ['project_name', 'construction_name', 'project_code'], ['id', 'project_name', 'project_code']],
        'EMPLOYEE' => ['user_employees', ['employee_name', 'name', 'username'], ['id', 'employee_name', 'name']],
        'ACCOUNT' => ['system_bank_accounts', ['account_name', 'bank_account_name', 'bank_name', 'account_number'], ['id', 'account_name', 'account_number', 'bank_name']],
        'CARD' => ['system_cards', ['card_name', 'card_number', 'card_company_name'], ['id', 'card_name', 'card_number']],
        'TEAM' => ['system_work_teams', ['team_name'], ['id', 'team_name']],
    ];

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
        $this->schema = new EvidenceSchemaModel($this->db);
    }

    public function findDisplayRow(string $refType, string $id): array
    {
        $config = self::CONFIG[$refType] ?? null;
        if ($config === null || !$this->schema->tableExists($config[0])) return [];
        $columns = array_values(array_filter($config[1], fn(string $c): bool => $this->schema->columnExists($config[0], $c)));
        if ($columns === []) return [];
        $deleted = $this->schema->columnExists($config[0], 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->db->prepare('SELECT ' . implode(', ', $columns) . ' FROM ' . $config[0] . ' WHERE id = :id' . $deleted . ' LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function resolveId(string $refType, string $value): ?string
    {
        $config = self::CONFIG[$refType] ?? null;
        if ($config === null || !$this->schema->tableExists($config[0])) return null;
        $columns = array_values(array_filter($config[2], fn(string $c): bool => $this->schema->columnExists($config[0], $c)));
        if ($columns === []) return null;
        $where = []; $params = [];
        foreach ($columns as $column) { $key = ':ref_' . $column; $where[] = $column . ' = ' . $key; $params[$key] = $value; }
        $deleted = $this->schema->columnExists($config[0], 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->db->prepare('SELECT id FROM ' . $config[0] . ' WHERE (' . implode(' OR ', $where) . ')' . $deleted . ' ORDER BY id ASC LIMIT 1');
        $stmt->execute($params); $id = $stmt->fetchColumn();
        return $id === false ? null : (string) $id;
    }

    public function resolveBankAccountId(string $value, string $normalized, string $digits): ?string
    {
        $columns = ['id', 'account_name', 'account_number', 'bank_name', 'account_holder'];
        $where = []; $params = [];
        foreach ($columns as $column) if ($this->schema->columnExists('system_bank_accounts', $column)) { $key=':bank_account_'.$column; $where[]=$column.' = '.$key; $params[$key]=$value; }
        if ($this->schema->columnExists('system_bank_accounts', 'account_number')) {
            if ($normalized !== '') { $where[]="REPLACE(REPLACE(account_number, '-', ''), ' ', '') = :normalized_account_number"; $params[':normalized_account_number']=$normalized; }
            if ($digits !== '' && $digits !== $normalized) { $where[]="REPLACE(REPLACE(account_number, '-', ''), ' ', '') = :account_number_digits"; $params[':account_number_digits']=$digits; }
        }
        if ($where === []) return null;
        $deleted = $this->schema->columnExists('system_bank_accounts', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt=$this->db->prepare('SELECT id FROM system_bank_accounts WHERE ('.implode(' OR ',$where).')'.$deleted.' ORDER BY id ASC LIMIT 1');
        $stmt->execute($params); $id=$stmt->fetchColumn(); return $id===false?null:(string)$id;
    }
}
