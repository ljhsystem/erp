<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class EvidenceDropdownModel
{
    private PDO $db;

    private const REF_CONFIG = [
        'CLIENT' => ['system_clients', ['client_name'], ['client_name']],
        'PROJECT' => ['system_projects', ['project_name'], ['sort_no', 'project_name']],
        'EMPLOYEE' => ['user_employees', ['employee_name', 'name'], ['employee_name', 'name']],
        'ACCOUNT' => ['system_bank_accounts', ['account_name'], ['account_name']],
        'CARD' => ['system_cards', ['card_name'], ['card_name']],
        'TEAM' => ['system_work_teams', ['team_name'], ['team_name']],
    ];

    public function __construct(?PDO $pdo = null) { $this->db = $pdo ?? Database::getInstance()->getConnection(); }

    public function distinctValues(string $table, string $column, bool $hasDeletedAt, bool $hasIsActive): array
    {
        $this->assertEvidenceIdentifier($table); $this->assertIdentifier($column);
        $where=[]; if($hasDeletedAt)$where[]='deleted_at IS NULL'; if($hasIsActive)$where[]='COALESCE(is_active, 1) = 1';
        $id='`'.$column.'`';
        $stmt=$this->db->query('SELECT DISTINCT '.$id.' AS dropdown_value FROM `'.$table.'`'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY '.$id.' ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function accountRows(bool $hasStatus, bool $hasIsPostable, bool $hasIsPosting): array
    {
        $where="deleted_at IS NULL";
        if($hasStatus)$where.=" AND COALESCE(status, 'active') <> 'deleted'";
        if($hasIsPostable)$where.=" AND COALESCE(is_postable, 'Y') = 'Y'"; elseif($hasIsPosting)$where.=" AND COALESCE(is_posting, 1) = 1";
        $stmt=$this->db->query('SELECT account_code, account_name FROM ledger_accounts WHERE '.$where.' ORDER BY account_code ASC, account_name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function businessReferenceRows(string $refType, array $availableColumns, bool $hasDeletedAt): array
    {
        $config=self::REF_CONFIG[$refType]??null; if($config===null)return[];
        [$table,$labels,$orders]=$config;
        $selects=array_values(array_intersect($labels,$availableColumns)); if($selects===[])return[];
        $orderBy=array_map(fn(string $c):string=>$c.' ASC',array_values(array_intersect($orders,$availableColumns)));
        $stmt=$this->db->query('SELECT '.implode(', ',$selects).' FROM '.$table.($hasDeletedAt?' WHERE deleted_at IS NULL':'').($orderBy?' ORDER BY '.implode(', ',$orderBy):''));
        return ['columns'=>$selects,'rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    private function assertEvidenceIdentifier(string $table): void
    {
        $this->assertIdentifier($table);
        if(!str_starts_with($table,'ledger_') && !in_array($table,['system_codes','system_clients','system_projects','system_bank_accounts','system_cards','system_work_teams','user_employees'],true)) throw new \InvalidArgumentException('Unsupported dropdown table');
    }
    private function assertIdentifier(string $value): void { if(preg_match('/^[A-Za-z0-9_]+$/',$value)!==1)throw new \InvalidArgumentException('Invalid dropdown identifier'); }
}
