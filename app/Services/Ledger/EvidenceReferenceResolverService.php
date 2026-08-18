<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceReferenceModel;
use App\Models\System\ClientModel;
use App\Models\System\ProjectModel;
use PDO;

class EvidenceReferenceResolverService
{
    private array $voucherRefIdCache = [];
    private array $voucherRefNameCache = [];
    private array $bankAccountIdCache = [];
    private EvidenceReferenceModel $referenceModel;
    private ClientModel $clientModel;
    private ProjectModel $projectModel;

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
        $this->referenceModel = new EvidenceReferenceModel($pdo);
        $this->clientModel = new ClientModel($pdo);
        $this->projectModel = new ProjectModel($pdo);
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
            'TEAM' => 'system_work_teams',
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
            'TEAM' => ['team_name'],
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

        $row = $this->referenceModel->findDisplayRow($refType, $id);

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

        $normalized = preg_replace('/[\s-]+/u', '', $value) ?? $value;
        $digits = preg_replace('/\D+/u', '', $value) ?? '';
        $resolved = $this->referenceModel->resolveBankAccountId($value, $normalized, $digits);
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
            'TEAM' => 'system_work_teams',
            default => null,
        };
        if ($table === null || !$this->tableExists($table)) {
            $this->voucherRefIdCache[$cacheKey] = null;
            return null;
        }

        $resolved = $this->referenceModel->resolveId($refType, $value);
        $this->voucherRefIdCache[$cacheKey] = $resolved;

        return $resolved;
    }

    public function findClientId(string $clientName): ?string
    {
        $clientName = trim($clientName);
        if ($clientName === '') {
            return null;
        }

        return $this->clientModel->findIdByClientName($clientName);
    }

    public function existingClientIdByBusinessNumber(string $businessNumber): ?string
    {
        $businessNumber = $this->normalizeBusinessNumber($businessNumber);
        if ($businessNumber === '') {
            return null;
        }

        return $this->clientModel->findIdByBusinessNumber($businessNumber);
    }

    public function findProjectId(string $projectName): ?string
    {
        $projectName = trim($projectName);
        if ($projectName === '') {
            return null;
        }

        return $this->projectModel->findIdByProjectName($projectName);
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
            'system_work_teams' => ['id', 'team_name'],
            default => ['id'],
        };
    }
}
