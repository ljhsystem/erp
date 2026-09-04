<?php

namespace App\Services\Ledger;

use PDO;

final class TransactionReferenceValidatorService
{
    private array $columnCache = [];
    private const MASTERS = [
        'client_id' => ['system_clients', '거래처', null],
        'project_id' => ['system_projects', '프로젝트', null],
        'employee_id' => ['user_employees', '직원', "employment_status = 'ACTIVE'"],
        'bank_account_id' => ['system_bank_accounts', '계좌', null],
        'card_id' => ['system_cards', '카드', null],
        'team_id' => ['system_work_teams', '팀', null],
    ];

    private const CODES = [
        'business_unit' => ['BUSINESS_UNIT', '사업구분', true],
        'transaction_direction' => ['TRANSACTION_DIRECTION', '거래구분', true],
        'operation_type' => ['OPERATION_TYPE', '업무유형', true],
        'currency' => ['CURRENCY', '통화', true],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function validate(array $payload, ?array $existing = null, array $context = []): void
    {
        foreach (self::MASTERS as $field => [$table, $label, $extraActive]) {
            $value = trim((string) ($payload[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $isUnchanged = $existing !== null && hash_equals((string) ($existing[$field] ?? ''), $value);
            if ($field === 'employee_id' && ($context['employee_policy'] ?? '') === 'REGULAR_EMPLOYMENT_INCOME_EFFECTIVE_SNAPSHOT') {
                $this->validateRegularEmploymentIncomeEmployee($value, $context);
                continue;
            }
            $this->validateMaster($table, $label, $value, $isUnchanged, $extraActive);
        }

        foreach (self::CODES as $field => [$group, $label, $required]) {
            $value = strtoupper(trim((string) ($payload[$field] ?? '')));
            if ($value === '') {
                if ($required) {
                    throw new \InvalidArgumentException($label . '은(는) 필수입니다.');
                }
                continue;
            }
            $isUnchanged = $existing !== null && hash_equals(strtoupper((string) ($existing[$field] ?? '')), $value);
            $this->validateCode($group, $label, $value, $isUnchanged);
        }
    }

    private function validateRegularEmploymentIncomeEmployee(string $employeeId, array $context): void
    {
        $documentId = trim((string) ($context['source_document_id'] ?? ''));
        $itemId = trim((string) ($context['source_item_id'] ?? ''));
        $contractId = trim((string) ($context['employment_contract_id'] ?? ''));
        $periodFrom = trim((string) ($context['period_from'] ?? ''));
        $periodTo = trim((string) ($context['period_to'] ?? ''));
        if ($documentId === '' || $itemId === '' || $contractId === ''
            || !$this->isDate($periodFrom) || !$this->isDate($periodTo) || $periodFrom > $periodTo) {
            throw new \InvalidArgumentException('급여 원천과 유효기간 검증 정보를 확인해 주세요.');
        }

        $statement = $this->pdo->prepare(
            'SELECT 1
               FROM institution_regular_employment_income_items item
               JOIN institution_regular_employment_incomes income
                 ON income.id = item.regular_employment_income_id
                AND income.deleted_at IS NULL
               JOIN institution_employment_contracts contract
                 ON contract.id = item.employment_contract_id
                AND contract.employee_id = item.employee_id
                AND contract.deleted_at IS NULL
                AND contract.approved_at IS NOT NULL
                AND contract.contract_start_date <= :contract_period_to
                AND (contract.contract_end_date IS NULL OR contract.contract_end_date >= :contract_period_from)
               JOIN user_employees employee
                 ON employee.id = item.employee_id
              WHERE income.id = :document_id
                AND item.id = :item_id
                AND item.employee_id = :employee_id
                AND item.employment_contract_id = :contract_id
                AND COALESCE(income.payroll_period_start_date, CONCAT(income.income_year_month, "-01")) = :period_from
                AND COALESCE(income.payroll_period_end_date, LAST_DAY(CONCAT(income.income_year_month, "-01"))) = :period_to
                AND item.deleted_at IS NULL
              LIMIT 1'
        );
        $statement->execute([
            ':document_id' => $documentId,
            ':item_id' => $itemId,
            ':employee_id' => $employeeId,
            ':contract_id' => $contractId,
            ':contract_period_from' => $periodFrom,
            ':contract_period_to' => $periodTo,
            ':period_from' => $periodFrom,
            ':period_to' => $periodTo,
        ]);
        if (!$statement->fetchColumn()) {
            throw new \InvalidArgumentException('급여 귀속기간에 유효한 승인 계약과 직원 원천 스냅샷을 확인해 주세요.');
        }
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public function validateGroupedIds(array $idsByTarget): void
    {
        $targetFields = [
            'CLIENT' => 'client_id', 'CUSTOMER' => 'client_id', 'VENDOR' => 'client_id', 'COUNTERPARTY' => 'client_id',
            'PROJECT' => 'project_id', 'EMPLOYEE' => 'employee_id', 'USER' => 'employee_id',
            'ACCOUNT' => 'bank_account_id', 'BANK' => 'bank_account_id', 'BANK_ACCOUNT' => 'bank_account_id',
            'CARD' => 'card_id', 'TEAM' => 'team_id', 'WORK_TEAM' => 'team_id',
        ];
        foreach ($idsByTarget as $target => $ids) {
            $field = $targetFields[strtoupper((string) $target)] ?? null;
            if ($field === null || !isset(self::MASTERS[$field])) {
                throw new \InvalidArgumentException('지원하지 않는 보조계정 유형이 포함되어 있습니다.');
            }
            [$table, $label, $extraActive] = self::MASTERS[$field];
            $values = array_values(array_unique(array_filter(array_map('strval', (array) $ids))));
            if ($values === []) continue;
            $columns = $this->columns($table);
            $where = ['id IN (' . implode(',', array_fill(0, count($values), '?')) . ')'];
            if (isset($columns['deleted_at'])) $where[] = 'deleted_at IS NULL';
            if (isset($columns['is_active'])) $where[] = 'is_active = 1';
            if ($extraActive !== null) $where[] = $extraActive;
            $stmt = $this->pdo->prepare(sprintf('SELECT id FROM `%s` WHERE %s', $table, implode(' AND ', $where)));
            $stmt->execute($values);
            $found = array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), true);
            foreach ($values as $id) {
                if (!isset($found[$id])) {
                    throw new \InvalidArgumentException('현재 사용할 수 있는 ' . $label . ' 참조가 아닙니다.');
                }
            }
        }
    }

    private function validateMaster(string $table, string $label, string $id, bool $allowInactive, ?string $extraActive): void
    {
        $columns = $this->columns($table);
        $where = ['id = :id'];
        if (!$allowInactive) {
            if (isset($columns['deleted_at'])) {
                $where[] = 'deleted_at IS NULL';
            }
            if (isset($columns['is_active'])) {
                $where[] = 'is_active = 1';
            }
            if ($extraActive !== null) {
                $where[] = $extraActive;
            }
        }
        $stmt = $this->pdo->prepare(sprintf('SELECT 1 FROM `%s` WHERE %s LIMIT 1', $table, implode(' AND ', $where)));
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetchColumn()) {
            throw new \InvalidArgumentException($allowInactive
                ? $label . ' 정보를 찾을 수 없습니다.'
                : '현재 사용할 수 있는 ' . $label . '을(를) 선택해 주세요.');
        }
    }

    private function validateCode(string $group, string $label, string $code, bool $allowInactive): void
    {
        $sql = 'SELECT 1 FROM system_codes WHERE code_group = :code_group AND code = :code';
        if (!$allowInactive) {
            $sql .= ' AND is_active = 1';
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute([':code_group' => $group, ':code' => $code]);
        if (!$stmt->fetchColumn()) {
            throw new \InvalidArgumentException($allowInactive
                ? $label . ' 코드를 찾을 수 없습니다.'
                : '현재 사용할 수 있는 ' . $label . '을(를) 선택해 주세요.');
        }
    }

    private function columns(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }
        $stmt = $this->pdo->query(sprintf('SHOW COLUMNS FROM `%s`', $table));
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(string) $row['Field']] = true;
        }
        return $this->columnCache[$table] = $result;
    }
}
