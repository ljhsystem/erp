<?php

namespace App\Services\Ledger;

use App\Models\System\ClientModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EvidenceClientSyncService
{
    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
    }

    public function syncTaxInvoiceEvidenceClientsFromSource(array $payload, string $evidenceId, string $dataType): array
    {
        $this->ensureClientHistoryTable();
        $direction = $this->call('transactionDirectionForStorage', (string) ($payload['transaction_direction'] ?? ''), $payload, $dataType);
        $primaryRole = $direction === 'SALES' ? 'customer' : 'supplier';
        $synced = [];
        $primaryClientId = null;

        foreach (['supplier', 'customer'] as $role) {
            $party = $this->taxInvoiceEvidenceParty($payload, $role);
            if ($party === null || $this->isOwnCompanyBusinessNumber((string) $party['business_number'])) {
                continue;
            }
            $clientId = $this->upsertEvidenceClientByBusinessNumber($party, $evidenceId, 'HOMETAX_TAX_INVOICE');
            if ($clientId === null) {
                continue;
            }
            $synced[$role] = $clientId;
            if ($role === $primaryRole) {
                $primaryClientId = $clientId;
            }
        }

        return [
            'primary_client_id' => $primaryClientId,
            'synced_client_ids' => $synced,
        ];
    }

    public function upsertEvidenceClientByBusinessNumber(array $party, string $evidenceId, string $sourceType): ?string
    {
        $businessNumber = $this->call('normalizeBusinessNumber', (string) ($party['business_number'] ?? ''));
        if ($businessNumber === '') {
            return null;
        }

        $companyName = $this->call('cleanCompanyName', (string) ($party['company_name'] ?? ''));
        $ceoName = $this->nullableCleanString($party['ceo_name'] ?? null);
        $address = $this->nullableCleanString($party['address'] ?? null);
        $stmt = $this->pdo->prepare("
            SELECT id, client_name, company_name, ceo_name, address
            FROM system_clients
            WHERE business_number = :business_number
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':business_number' => $businessNumber]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$client) {
            $clientId = UuidHelper::generate();
            $clientName = $companyName !== '' ? $companyName : $businessNumber;
            $created = (new ClientModel($this->pdo))->create([
                'id' => $clientId,
                'sort_no' => SequenceHelper::next('system_clients', 'sort_no'),
                'client_name' => $clientName,
                'company_name' => $companyName !== '' ? $companyName : null,
                'business_number' => $businessNumber,
                'ceo_name' => $ceoName,
                'address' => $address,
                'registration_date' => date('Y-m-d'),
                'client_type' => 'CLIENT',
                'is_active' => 1,
                'created_by' => ActorHelper::user(),
                'updated_by' => ActorHelper::user(),
            ]);
            if (!$created) {
                throw new \RuntimeException('Failed to create client from evidence source.');
            }
            return $clientId;
        }

        $clientId = (string) $client['id'];
        $updates = [];
        foreach ([
            'company_name' => $companyName,
            'ceo_name' => $ceoName,
            'address' => $address,
        ] as $field => $newValue) {
            $newValue = trim((string) ($newValue ?? ''));
            if ($newValue === '') {
                continue;
            }
            $oldValue = trim((string) ($client[$field] ?? ''));
            if ($oldValue !== $newValue) {
                $updates[$field] = $newValue;
                $this->insertEvidenceClientHistory($clientId, $field, $oldValue, $newValue, $sourceType, $evidenceId);
            }
        }
        if ($updates !== []) {
            $sets = [];
            $params = [
                ':id' => $clientId,
                ':actor' => ActorHelper::user(),
            ];
            foreach ($updates as $field => $value) {
                $sets[] = "{$field} = :{$field}";
                $params[':' . $field] = $value;
            }
            $sql = 'UPDATE system_clients SET ' . implode(', ', $sets) . ', updated_at = NOW(), updated_by = :actor WHERE id = :id';
            $this->pdo->prepare($sql)->execute($params);
        }

        return $clientId;
    }

    public function clientPartiesFromUploadRow(array $row, string $dataType): array
    {
        $dataType = $this->call('normalizeDataType', $dataType);

        if ($dataType === 'TAX_INVOICE' || $this->call('isManualTaxInvoiceDataType', $dataType)) {
            return [
                [
                    'role' => 'supplier',
                    'business_number' => $this->firstRowValue($row, ['supplier_business_number']),
                    'branch_number' => $this->firstRowValue($row, ['supplier_branch_number']),
                    'company_name' => $this->firstRowValue($row, ['supplier_company_name', 'supplier_name']),
                    'ceo_name' => $this->firstRowValue($row, ['supplier_ceo_name']),
                    'address' => $this->firstRowValue($row, ['supplier_address']),
                    'email' => $this->firstRowValue($row, ['supplier_email']),
                ],
                [
                    'role' => 'customer',
                    'business_number' => $this->firstRowValue($row, ['customer_business_number', 'recipient_business_number']),
                    'branch_number' => $this->firstRowValue($row, ['customer_branch_number', 'recipient_branch_number']),
                    'company_name' => $this->firstRowValue($row, ['customer_company_name', 'customer_name', 'recipient_company_name', 'recipient_name']),
                    'ceo_name' => $this->firstRowValue($row, ['customer_ceo_name', 'recipient_ceo_name']),
                    'address' => $this->firstRowValue($row, ['customer_address', 'recipient_address']),
                    'email' => $this->joinRowValues($row, ['customer_email_1', 'customer_email_2', 'recipient_email_1', 'recipient_email_2']),
                ],
            ];
        }

        if (in_array($dataType, ['CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES'], true)) {
            return [[
                'role' => 'merchant',
                'business_number' => $this->firstRowValue($row, ['merchant_business_number', 'client_business_number', 'business_number']),
                'company_name' => $this->firstRowValue($row, ['merchant_company_name', 'merchant_name', 'client_company_name', 'company_name']),
                'business_type' => $this->firstRowValue($row, ['business_type', 'merchant_business_type']),
                'business_category' => $this->firstRowValue($row, ['business_category', 'merchant_business_category', 'industry', 'merchant_industry']),
                'memo' => $this->industryMemo($row),
            ]];
        }

        if (in_array($dataType, ['CARD_STATEMENT', 'CARD_APPROVAL'], true)) {
            return [
                [
                    'role' => 'card_company',
                    'company_name' => $this->firstRowValue($row, ['card_company', 'card_company_name']),
                ],
                [
                    'role' => 'merchant',
                    'business_number' => $this->firstRowValue($row, ['merchant_business_number', 'client_business_number', 'business_number']),
                    'company_name' => $this->firstRowValue($row, ['merchant_company_name', 'merchant_name', 'client_company_name', 'company_name']),
                    'business_type' => $this->firstRowValue($row, ['business_type', 'merchant_business_type']),
                    'business_category' => $this->firstRowValue($row, ['business_category', 'merchant_business_category', 'merchant_industry', 'merchant_type']),
                    'address' => $this->joinRowValues($row, ['merchant_address', 'merchant_address1', 'merchant_address2']),
                    'phone' => $this->firstRowValue($row, ['merchant_phone', 'phone']),
                    'memo' => $this->firstRowValue($row, ['merchant_zip_code', 'merchant_postal_code']) !== ''
                        ? 'merchant_zip_code: ' . $this->firstRowValue($row, ['merchant_zip_code', 'merchant_postal_code'])
                        : null,
                ],
            ];
        }

        if ($dataType === 'BANK_TRANSACTION') {
            $row = $this->call('normalizeBankTransactionPayload', $row);
            return [[
                'role' => 'counterparty',
                'company_name' => $this->call('bankCounterpartyName', $row),
                'bank_name' => $this->firstRowValue($row, ['counterparty_bank', 'counterparty_bank_name', 'bank_name']),
                'account_number' => $this->firstRowValue($row, ['counterparty_account_number', 'counterparty_account_no', 'account_number']),
                'account_holder' => $this->firstRowValue($row, ['counterparty_account_holder_name', 'counterparty_account_holder', 'counterparty_name']),
            ]];
        }

        return [[
            'role' => 'primary',
            'business_number' => $this->firstRowValue($row, ['client_business_number', 'business_number']),
            'company_name' => $this->firstRowValue($row, ['client_company_name', 'company_name', 'counterparty_name']),
        ]];
    }

    public function normalizeImportClientParty(array $party): ?array
    {
        $party['business_number'] = $this->call('normalizeBusinessNumber', (string) ($party['business_number'] ?? ''));
        $party['company_name'] = $this->call('cleanCompanyName', (string) ($party['company_name'] ?? ''));
        foreach ([
            'branch_number',
            'ceo_name',
            'ceo_name_ko',
            'ceo_name_en',
            'company_name_ko',
            'company_name_en',
            'client_name_ko',
            'client_name_en',
            'client_company_name_ko',
            'client_company_name_en',
            'korean_name',
            'english_name',
            'company_korean_name',
            'company_english_name',
            'client_korean_name',
            'client_english_name',
            'address',
            'email',
            'phone',
            'business_type',
            'business_category',
            'bank_name',
            'account_number',
            'account_holder',
            'memo',
        ] as $key) {
            $party[$key] = $this->nullableCleanString($party[$key] ?? null);
        }
        if ($party['company_name'] === '') {
            $party['company_name'] = $this->call('cleanCompanyName', (string) (
                $party['company_name_ko']
                ?? $party['client_company_name_ko']
                ?? $party['client_name_ko']
                ?? $party['company_name_en']
                ?? $party['client_company_name_en']
                ?? $party['client_name_en']
                ?? ''
            ));
        }

        if ($party['business_number'] === '' && $party['company_name'] === '') {
            return null;
        }

        return $party;
    }

    public function upsertClientFromImportParty(array $party): ?string
    {
        $party = $this->normalizeImportClientParty($party);
        if ($party === null) {
            return null;
        }

        $businessNumber = (string) $party['business_number'];
        $companyName = (string) $party['company_name'];
        $client = $this->findClientRowForImportParty($party);
        if ($client) {
            $this->updateClientFromImportParty((string) $client['id'], $client, $party);
            return (string) $client['id'];
        }

        $clientId = UuidHelper::generate();
        $clientName = $this->uniqueClientNameFromImportParty($party);
        $created = (new ClientModel($this->pdo))->create([
            'id' => $clientId,
            'sort_no' => SequenceHelper::next('system_clients', 'sort_no'),
            'client_name' => $clientName,
            'company_name' => $companyName !== '' ? $companyName : null,
            'business_number' => $businessNumber !== '' ? $businessNumber : null,
            'registration_date' => date('Y-m-d'),
            'business_type' => $party['business_type'] ?? null,
            'business_category' => $party['business_category'] ?? null,
            'address' => $party['address'] ?? null,
            'phone' => $party['phone'] ?? null,
            'email' => $party['email'] ?? null,
            'ceo_name' => $party['ceo_name'] ?? null,
            'bank_name' => $party['bank_name'] ?? null,
            'account_number' => $party['account_number'] ?? null,
            'account_holder' => $party['account_holder'] ?? null,
            'client_type' => 'CLIENT',
            'note' => $this->clientPartyNote($party),
            'memo' => $party['memo'] ?? null,
            'is_active' => 1,
            'created_by' => ActorHelper::user(),
            'updated_by' => ActorHelper::user(),
        ]);
        if (!$created) {
            throw new \RuntimeException('????????????????????????????????????????獄쏅챶留덌┼??????????????筌롈살젔?????????????????????????????곕춴?????????????????????????癲??');
        }

        return $clientId;
    }

    public function clientNameFromImportParty(array $party): string
    {
        $businessNumber = $this->call('normalizeBusinessNumber', (string) ($party['business_number'] ?? ''));
        $koreanName = $this->internalClientBaseName($this->koreanClientNameCandidate($party));
        $englishName = $this->internalClientBaseName($this->englishClientNameCandidate($party));

        if ($koreanName !== '' && $englishName !== '' && $this->call('normalizeCompanyNameForCompare', $koreanName) !== $this->call('normalizeCompanyNameForCompare', $englishName)) {
            return $koreanName . '(' . $englishName . ')';
        }
        if ($koreanName !== '') {
            return $koreanName;
        }
        if ($englishName !== '') {
            return $englishName;
        }

        $companyName = $this->internalClientBaseName((string) ($party['company_name'] ?? ''));
        if ($companyName !== '') {
            return $companyName;
        }

        return $businessNumber;
    }

    public function findClientRowForImportParty(array $party): ?array
    {
        $businessNumber = $this->call('normalizeBusinessNumber', (string) ($party['business_number'] ?? ''));
        $companyName = $this->call('cleanCompanyName', (string) ($party['company_name'] ?? ''));

        if ($businessNumber !== '') {
            $stmt = $this->pdo->prepare('
                SELECT *
                FROM system_clients
                WHERE business_number = :business_number
                  AND deleted_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([':business_number' => $businessNumber]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($client) {
                return $client;
            }

            return null;
        }

        $candidateNames = array_values(array_unique(array_filter([
            $companyName,
            $this->clientNameFromImportParty($party),
            $this->internalClientBaseName($companyName),
            $this->internalClientBaseName($this->koreanClientNameCandidate($party)),
            $this->internalClientBaseName($this->englishClientNameCandidate($party)),
        ], static fn(string $value): bool => $value !== '')));

        if ($candidateNames !== []) {
            $conditions = [];
            $params = [];
            foreach ($candidateNames as $index => $name) {
                $clientParam = ':client_name_' . $index;
                $companyParam = ':company_name_' . $index;
                $conditions[] = "(client_name = {$clientParam} OR company_name = {$companyParam})";
                $params[$clientParam] = $name;
                $params[$companyParam] = $name;
            }

            $stmt = $this->pdo->prepare('
                SELECT *
                FROM system_clients
                WHERE deleted_at IS NULL
                  AND (' . implode(' OR ', $conditions) . ')
                LIMIT 1
            ');
            $stmt->execute($params);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($client) {
                return $client;
            }
        }

        return null;
    }

    public function updateClientFromImportParty(string $clientId, array $before, array $party): void
    {
        $updates = [];
        $companyName = $this->call('cleanCompanyName', (string) ($party['company_name'] ?? ''));
        $oldCompanyName = $this->call('cleanCompanyName', (string) ($before['company_name'] ?? ''));
        $oldClientName = $this->call('cleanCompanyName', (string) ($before['client_name'] ?? ''));

        $newClientName = $this->uniqueClientNameFromImportParty($party, $clientId);
        if ($newClientName !== '' && !$this->containsHangul($newClientName) && $this->containsHangul($oldClientName)) {
            $englishName = $this->internalClientBaseName($this->englishClientNameCandidate($party));
            $oldKoreanName = preg_replace('/\([^)]*\)$/u', '', $oldClientName) ?? $oldClientName;
            $oldKoreanName = $this->internalClientBaseName($oldKoreanName);
            if ($oldKoreanName !== '' && $englishName !== '') {
                $newClientName = $oldKoreanName . '(' . $englishName . ')';
            }
        }
        $oldGeneratedClientName = $this->clientNameFromImportParty([
            'company_name' => $oldCompanyName,
            'ceo_name' => $before['ceo_name'] ?? '',
            'business_number' => $before['business_number'] ?? '',
        ]);
        $oldGeneratedPersonName = $this->localizedPersonNameFromImportParty([
            'ceo_name' => $before['ceo_name'] ?? '',
            'ceo_name_ko' => $before['ceo_name_ko'] ?? '',
            'ceo_name_en' => $before['ceo_name_en'] ?? '',
        ]);
        $oldGeneratedClientNameWithPerson = $oldGeneratedClientName;
        if ($oldGeneratedClientName !== '' && $oldGeneratedPersonName !== '' && !str_contains($oldGeneratedClientName, $oldGeneratedPersonName)) {
            $oldGeneratedClientNameWithPerson = $oldGeneratedClientName . '-' . $oldGeneratedPersonName;
        }
        $isAutoClientName = $oldClientName === ''
            || $oldClientName === $oldCompanyName
            || $oldClientName === $oldGeneratedClientName
            || $oldClientName === $oldGeneratedClientNameWithPerson;

        if ($companyName !== '' && $oldCompanyName !== $companyName) {
            if ($oldCompanyName !== '') {
                $this->insertClientNameHistory($clientId, $oldCompanyName, $companyName);
            }
            $updates['company_name'] = $companyName;
            if ($isAutoClientName) {
                $updates['client_name'] = $newClientName;
            }
        } elseif ($newClientName !== '' && $isAutoClientName) {
            $updates['client_name'] = $newClientName;
        }

        foreach ([
            'business_number',
            'business_type',
            'business_category',
            'address',
            'phone',
            'email',
            'ceo_name',
            'bank_name',
            'account_number',
            'account_holder',
        ] as $field) {
            $value = trim((string) ($party[$field] ?? ''));
            if ($value !== '' && trim((string) ($before[$field] ?? '')) !== $value) {
                $updates[$field] = $value;
            }
        }

        $note = $this->clientPartyNote($party);
        if ($note !== null && trim((string) ($before['note'] ?? '')) === '') {
            $updates['note'] = $note;
        }
        if (($party['memo'] ?? null) !== null && trim((string) ($before['memo'] ?? '')) === '') {
            $updates['memo'] = $party['memo'];
        }

        if ($updates === []) {
            return;
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');
        $updates['updated_by'] = ActorHelper::user();

        $set = [];
        $params = [':id' => $clientId];
        foreach ($updates as $column => $value) {
            $param = ':' . $column;
            $set[] = $column . ' = ' . $param;
            $params[$param] = $value;
        }

        $stmt = $this->pdo->prepare('UPDATE system_clients SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    private function taxInvoiceEvidenceParty(array $payload, string $role): ?array
    {
        $prefix = $role === 'customer' ? 'customer' : 'supplier';
        $businessNumber = $this->call('normalizeBusinessNumber', (string) ($payload[$prefix . '_business_number'] ?? ''));
        if ($businessNumber === '') {
            return null;
        }

        $companyName = $this->call('cleanCompanyName', (string) ($payload[$prefix . '_company_name'] ?? $payload[$prefix . '_name'] ?? ''));
        return [
            'role' => $prefix,
            'business_number' => $businessNumber,
            'company_name' => $companyName,
            'ceo_name' => $this->nullableCleanString($payload[$prefix . '_ceo_name'] ?? null),
            'address' => $this->nullableCleanString($payload[$prefix . '_address'] ?? null),
        ];
    }

    private function isOwnCompanyBusinessNumber(string $businessNumber): bool
    {
        $businessNumber = $this->call('normalizeBusinessNumber', $businessNumber);
        if ($businessNumber === '') {
            return false;
        }

        $profile = $this->call('ownCompanyProfile');
        return in_array($businessNumber, $profile['business_numbers'] ?? [], true);
    }

    private function insertEvidenceClientHistory(string $clientId, string $fieldName, string $oldValue, string $newValue, string $sourceType, string $evidenceId): void
    {
        $this->ensureClientHistoryTable();
        $stmt = $this->pdo->prepare("
            INSERT INTO system_client_histories
                (id, client_id, field_name, old_value, new_value, source_type, source_evidence_id, changed_at, changed_by)
            VALUES
                (:id, :client_id, :field_name, :old_value, :new_value, :source_type, :source_evidence_id, NOW(), :changed_by)
        ");
        $stmt->execute([
            ':id' => UuidHelper::generate(),
            ':client_id' => $clientId,
            ':field_name' => $fieldName,
            ':old_value' => $oldValue,
            ':new_value' => $newValue,
            ':source_type' => $sourceType,
            ':source_evidence_id' => $evidenceId,
            ':changed_by' => ActorHelper::user(),
        ]);
    }

    private function ensureClientHistoryTable(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS system_client_histories (
                id CHAR(36) NOT NULL,
                client_id VARCHAR(36) NOT NULL,
                field_name VARCHAR(100) NOT NULL,
                old_value TEXT NULL,
                new_value TEXT NULL,
                source_type VARCHAR(80) NULL,
                source_evidence_id VARCHAR(36) NULL,
                changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                changed_by VARCHAR(100) NULL,
                PRIMARY KEY (id),
                INDEX idx_client_changed_at (client_id, changed_at),
                INDEX idx_source_evidence (source_evidence_id),
                CONSTRAINT fk_system_client_histories_client
                    FOREIGN KEY (client_id) REFERENCES system_clients (id)
                    ON UPDATE RESTRICT ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        $ensured = true;
    }

    private function uniqueClientNameFromImportParty(array $party, ?string $excludeClientId = null): string
    {
        $clientName = $this->clientNameFromImportParty($party);
        if ($clientName === '') {
            return '';
        }

        $businessNumber = $this->call('normalizeBusinessNumber', (string) ($party['business_number'] ?? ''));
        if (!$this->hasDifferentClientWithName($clientName, $businessNumber, $excludeClientId)) {
            return $clientName;
        }

        $ceoName = $this->localizedPersonNameFromImportParty($party);
        if ($ceoName !== '' && !str_contains($clientName, $ceoName)) {
            return $clientName . '-' . $ceoName;
        }

        return $clientName;
    }

    private function koreanClientNameCandidate(array $party): string
    {
        foreach ([
            'client_name_ko',
            'client_company_name_ko',
            'company_name_ko',
            'korean_name',
            'company_korean_name',
            'client_korean_name',
            'counterparty_name_ko',
            'counterparty_korean_name',
            'client_name',
            'company_name',
            'counterparty_name',
        ] as $key) {
            $value = $this->call('cleanCompanyName', (string) ($party[$key] ?? ''));
            if ($value !== '' && !$this->call('isUuid', $value) && $this->containsHangul($value)) {
                return $value;
            }
        }

        return '';
    }

    private function englishClientNameCandidate(array $party): string
    {
        foreach ([
            'client_name_en',
            'client_company_name_en',
            'company_name_en',
            'english_name',
            'company_english_name',
            'client_english_name',
            'counterparty_name_en',
            'counterparty_english_name',
            'client_name',
            'company_name',
            'counterparty_name',
        ] as $key) {
            $value = $this->call('cleanCompanyName', (string) ($party[$key] ?? ''));
            if ($value !== '' && !$this->call('isUuid', $value) && !$this->containsHangul($value) && preg_match('/[A-Za-z]/', $value)) {
                return $value;
            }
        }

        return '';
    }

    private function localizedPersonNameFromImportParty(array $party): string
    {
        $koreanName = $this->internalClientBaseName((string) ($party['ceo_name_ko'] ?? ''));
        if ($koreanName === '') {
            $ceoName = $this->call('cleanCompanyName', (string) ($party['ceo_name'] ?? ''));
            if ($this->containsHangul($ceoName)) {
                $koreanName = $this->internalClientBaseName($ceoName);
            }
        }

        $englishName = $this->internalClientBaseName((string) ($party['ceo_name_en'] ?? ''));
        if ($englishName === '') {
            $ceoName = $this->call('cleanCompanyName', (string) ($party['ceo_name'] ?? ''));
            if ($ceoName !== '' && !$this->containsHangul($ceoName) && preg_match('/[A-Za-z]/', $ceoName)) {
                $englishName = $this->internalClientBaseName($ceoName);
            }
        }

        if ($koreanName !== '' && $englishName !== '' && $this->call('normalizeCompanyNameForCompare', $koreanName) !== $this->call('normalizeCompanyNameForCompare', $englishName)) {
            return $koreanName . '(' . $englishName . ')';
        }

        return $koreanName !== '' ? $koreanName : $englishName;
    }

    private function containsHangul(string $value): bool
    {
        return preg_match('/\p{Hangul}/u', $value) === 1;
    }

    private function internalClientBaseName(string $companyName): string
    {
        return $this->normalizedInternalClientBaseName($companyName);
    }

    private function normalizedInternalClientBaseName(string $companyName): string
    {
        $name = $this->call('cleanCompanyName', $companyName);
        if ($name === '') {
            return '';
        }

        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/[[:space:]\x{00A0}]+/u', '', $name) ?? $name;

        $legalPatterns = [
            '/^\(??)/u',
            '/^\(??)/u',
            '/^??u',
            '/^????????????袁⑸즴筌?씛彛???돗?????????????????u',
            '/^???????????????????u',
            '/^???????????u',
            '/^??????????????????????u',
            '/^\(??)/u',
            '/^??????????????椰??????????u',
            '/^co\.?,?ltd\.?/iu',
            '/^corporation/iu',
            '/^corp\.?/iu',
            '/^inc\.?/iu',
            '/^ltd\.?/iu',
            '/\(??)$/u',
            '/\(??)$/u',
            '/??/u',
            '/????????????袁⑸즴筌?씛彛???돗?????????????????/u',
            '/???????????????????/u',
            '/???????????/u',
            '/??????????????????????/u',
            '/\(??)$/u',
            '/??????????????椰??????????/u',
            '/co\.?,?ltd\.?$/iu',
            '/corporation$/iu',
            '/corp\.?$/iu',
            '/inc\.?$/iu',
            '/ltd\.?$/iu',
        ];

        do {
            $before = $name;
            foreach ($legalPatterns as $pattern) {
                $name = preg_replace($pattern, '', $name) ?? $name;
            }
            $name = trim($name);
        } while ($name !== $before);

        return $name;
    }

    private function hasDifferentClientWithName(string $clientName, string $businessNumber = '', ?string $excludeClientId = null): bool
    {
        $clientName = trim($clientName);
        if ($clientName === '') {
            return false;
        }

        $where = ['client_name = :client_name', 'deleted_at IS NULL'];
        $params = [':client_name' => $clientName];

        if ($excludeClientId !== null && $excludeClientId !== '') {
            $where[] = 'id <> :exclude_client_id';
            $params[':exclude_client_id'] = $excludeClientId;
        }
        if ($businessNumber !== '') {
            $where[] = "(business_number IS NULL OR business_number = '' OR business_number <> :business_number)";
            $params[':business_number'] = $businessNumber;
        }

        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM system_clients
            WHERE ' . implode(' AND ', $where) . '
            LIMIT 1
        ');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function clientPartyNote(array $party): ?string
    {
        $parts = [];
        if (!empty($party['role'])) {
            $parts[] = 'source_role: ' . $party['role'];
        }
        if (!empty($party['branch_number'])) {
            $parts[] = 'branch_number: ' . $party['branch_number'];
        }

        return $parts !== [] ? implode(' / ', $parts) : null;
    }

    private function firstRowValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->call('payloadScalarForStorage', $row[$key] ?? null);
            if (array_key_exists($key, $row) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function joinRowValues(array $row, array $keys): string
    {
        $values = [];
        foreach ($keys as $key) {
            $value = trim((string) $this->call('payloadScalarForStorage', $row[$key] ?? null));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return implode(', ', array_values(array_unique($values)));
    }

    private function industryMemo(array $row): ?string
    {
        $industryCode = $this->firstRowValue($row, ['industry_code', 'merchant_industry_code']);
        return $industryCode !== '' ? 'industry_code: ' . $industryCode : null;
    }

    private function nullableCleanString(mixed $value): ?string
    {
        $value = trim((string) $this->call('payloadScalarForStorage', $value));
        return $value !== '' ? $value : null;
    }

    private function insertClientNameHistory(string $clientId, string $oldCompanyName, string $newCompanyName): void
    {
        if (!$this->clientNameHistoryTableExists()) {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO system_client_name_history
                (id, client_id, old_company_name, new_company_name, changed_at, changed_by)
            VALUES
                (:id, :client_id, :old_company_name, :new_company_name, NOW(), :changed_by)
        ');
        $stmt->execute([
            ':id' => UuidHelper::generate(),
            ':client_id' => $clientId,
            ':old_company_name' => $oldCompanyName,
            ':new_company_name' => $newCompanyName,
            ':changed_by' => ActorHelper::user(),
        ]);
    }

    private function clientNameHistoryTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = 'system_client_name_history'
            LIMIT 1
        ");
        $stmt->execute();
        $exists = (bool) $stmt->fetchColumn();

        return $exists;
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
