<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceReferenceResolverService
{
    private array $voucherRefIdCache = [];
    private array $voucherRefNameCache = [];
    private array $bankAccountIdCache = [];

    /**
     * @param callable(string):bool $tableExists
     * @param callable(string,string):bool $tableColumnExists
     * @param callable(string):bool $isUuid
     * @param callable(string):string $normalizeVoucherRefType
     * @param callable(mixed):string $normalizeBusinessNumber
     */
    public function __construct(
        private PDO $pdo,
        private $tableExists,
        private $tableColumnExists,
        private $isUuid,
        private $normalizeVoucherRefType,
        private $normalizeBusinessNumber
    ) {
    }

    public function businessRefNameById(string $refType, string $id): ?string
    {
        $refType = $this->normalizeVoucherRefType($refType);
        $id = trim($id);
        if ($id === '' || !$this->isUuid($id)) {
            return null;
        }

        $cacheKey = $refType . '|' . $id;
        if (array_key_exists($cacheKey, $this->voucherRefNameCache)) {
            return $this->voucherRefNameCache[$cacheKey];
        }

        $table = match ($refType) {
            'CLIENT' => 'system_clients',
            'PROJECT' => 'system_projects',
            'EMPLOYEE' => 'user_employees',
            'ACCOUNT' => 'system_bank_accounts',
            'CARD' => 'system_cards',
            default => null,
        };
        if ($table === null || !$this->tableExists($table) || !$this->tableColumnExists($table, 'id')) {
            $this->voucherRefNameCache[$cacheKey] = null;
            return null;
        }

        $columns = match ($refType) {
            'CLIENT' => ['client_name', 'company_name'],
            'PROJECT' => ['project_name', 'construction_name', 'project_code'],
            'EMPLOYEE' => ['employee_name', 'name', 'username'],
            'ACCOUNT' => ['account_name', 'bank_account_name', 'bank_name', 'account_number'],
            'CARD' => ['card_name', 'card_number', 'card_company_name'],
            default => [],
        };
        $selects = [];
        foreach ($columns as $column) {
            if ($this->tableColumnExists($table, $column)) {
                $selects[] = $column;
            }
        }
        if ($selects === []) {
            $this->voucherRefNameCache[$cacheKey] = null;
            return null;
        }

        $deleted = $this->tableColumnExists($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare('SELECT ' . implode(', ', $selects) . " FROM {$table} WHERE id = :id{$deleted} LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($selects as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '' && !$this->isUuid($value)) {
                $this->voucherRefNameCache[$cacheKey] = $value;
                return $value;
            }
        }

        $this->voucherRefNameCache[$cacheKey] = null;
        return null;
    }

    public function resolveBankAccountId(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '' || !$this->tableExists('system_bank_accounts')) {
            return null;
        }

        $cacheKey = 'ACCOUNT|' . $value;
        if (array_key_exists($cacheKey, $this->bankAccountIdCache)) {
            return $this->bankAccountIdCache[$cacheKey];
        }

        $where = [];
        $params = [];
        foreach (['id', 'account_name', 'account_number', 'bank_name', 'account_holder'] as $column) {
            if ($this->tableColumnExists('system_bank_accounts', $column)) {
                $param = ':bank_account_' . $column;
                $where[] = $column . ' = ' . $param;
                $params[$param] = $value;
            }
        }

        $normalized = preg_replace('/[\s-]+/u', '', $value) ?? $value;
        $digits = preg_replace('/\D+/u', '', $value) ?? '';
        if ($this->tableColumnExists('system_bank_accounts', 'account_number')) {
            if ($normalized !== '') {
                $where[] = "REPLACE(REPLACE(account_number, '-', ''), ' ', '') = :normalized_account_number";
                $params[':normalized_account_number'] = $normalized;
            }
            if ($digits !== '' && $digits !== $normalized) {
                $where[] = "REPLACE(REPLACE(account_number, '-', ''), ' ', '') = :account_number_digits";
                $params[':account_number_digits'] = $digits;
            }
        }

        if ($where === []) {
            return null;
        }

        $deleted = $this->tableColumnExists('system_bank_accounts', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM system_bank_accounts
            WHERE (" . implode(' OR ', $where) . ")
              {$deleted}
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        $resolved = $id !== false ? (string) $id : null;
        $this->bankAccountIdCache[$cacheKey] = $resolved;

        return $resolved;
    }

    public function resolveVoucherRefId(string $refType, string $value): ?string
    {
        $refType = $this->normalizeVoucherRefType($refType);
        $value = trim($value);
        if ($value === '' || $this->isUuid($value)) {
            return $value !== '' ? $value : null;
        }

        $cacheKey = $refType . '|' . $value;
        if (array_key_exists($cacheKey, $this->voucherRefIdCache)) {
            return $this->voucherRefIdCache[$cacheKey];
        }

        $table = match ($refType) {
            'CLIENT' => 'system_clients',
            'PROJECT' => 'system_projects',
            'ACCOUNT' => 'system_bank_accounts',
            'CARD' => 'system_cards',
            'EMPLOYEE' => 'user_employees',
            default => null,
        };
        if ($table === null || !$this->tableExists($table)) {
            $this->voucherRefIdCache[$cacheKey] = null;
            return null;
        }

        $columns = $this->refLookupColumns($table);
        if ($columns === []) {
            $this->voucherRefIdCache[$cacheKey] = null;
            return null;
        }

        $where = [];
        $params = [];
        foreach ($columns as $column) {
            if ($this->tableColumnExists($table, $column)) {
                $param = ':ref_' . $column;
                $where[] = $column . ' = ' . $param;
                $params[$param] = $value;
            }
        }
        if ($where === []) {
            $this->voucherRefIdCache[$cacheKey] = null;
            return null;
        }

        $deleted = $this->tableColumnExists($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM {$table}
            WHERE (" . implode(' OR ', $where) . ")
              {$deleted}
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        $resolved = $id !== false ? (string) $id : null;
        $this->voucherRefIdCache[$cacheKey] = $resolved;

        return $resolved;
    }

    public function findClientId(string $clientName): ?string
    {
        $clientName = trim($clientName);
        if ($clientName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM system_clients WHERE client_name = :name AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':name' => $clientName]);

        return $stmt->fetchColumn() ?: null;
    }

    public function existingClientIdByBusinessNumber(string $businessNumber): ?string
    {
        $businessNumber = $this->normalizeBusinessNumber($businessNumber);
        if ($businessNumber === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM system_clients
            WHERE business_number = :business_number
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':business_number' => $businessNumber]);
        $id = $stmt->fetchColumn();

        return $id ? (string) $id : null;
    }

    public function findProjectId(string $projectName): ?string
    {
        $projectName = trim($projectName);
        if ($projectName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id FROM system_projects WHERE project_name = :name AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':name' => $projectName]);

        return $stmt->fetchColumn() ?: null;
    }

    private function normalizeVoucherRefType(string $value): string
    {
        return ($this->normalizeVoucherRefType)($value);
    }

    private function normalizeBusinessNumber(mixed $value): string
    {
        return ($this->normalizeBusinessNumber)($value);
    }

    private function tableExists(string $tableName): bool
    {
        return ($this->tableExists)($tableName);
    }

    private function tableColumnExists(string $tableName, string $columnName): bool
    {
        return ($this->tableColumnExists)($tableName, $columnName);
    }

    private function isUuid(string $value): bool
    {
        return ($this->isUuid)($value);
    }

    private function refLookupColumns(string $table): array
    {
        return match ($table) {
            'system_clients' => ['id', 'client_name', 'company_name', 'business_number'],
            'system_projects' => ['id', 'project_name', 'project_code'],
            'system_bank_accounts' => ['id', 'account_name', 'account_number', 'bank_name'],
            'system_cards' => ['id', 'card_name', 'card_number'],
            'user_employees' => ['id', 'employee_name', 'name'],
            default => ['id'],
        };
    }
}
