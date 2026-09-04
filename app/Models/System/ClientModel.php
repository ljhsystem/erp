<?php
namespace App\Models\System;

use PDO;
use Core\Helpers\ActorHelper;
use Core\Database;

class ClientModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                c.*,
                da.account_code AS default_account_code,
                da.account_name AS default_account_name,
                NULLIF(CONCAT(COALESCE(da.account_code, ''), ' - ', COALESCE(da.account_name, '')), ' - ') AS default_account_text
            FROM system_clients c
            LEFT JOIN ledger_accounts da
                ON da.id = c.default_account_id
               AND da.deleted_at IS NULL
            WHERE c.deleted_at IS NULL
        ";

        $params = [];

        $fieldMap = [

            'sort_no'              => ['col'=>'c.sort_no','type'=>'exact'],
            'client_name'       => ['col'=>'c.client_name','type'=>'like'],
            'company_name'      => ['col'=>'c.company_name','type'=>'like'],

            'business_number'   => ['col'=>'c.business_number','type'=>'like'],
            'rrn'               => ['col'=>'c.rrn','type'=>'like'],
            'business_type'     => ['col'=>'c.business_type','type'=>'like'],
            'business_category' => ['col'=>'c.business_category','type'=>'like'],
            'business_status'   => ['col'=>'c.business_status','type'=>'like'],

            'ceo_name'          => ['col'=>'c.ceo_name','type'=>'like'],
            'ceo_phone'         => ['col'=>'c.ceo_phone','type'=>'like'],
            'manager_name'      => ['col'=>'c.manager_name','type'=>'like'],
            'manager_phone'     => ['col'=>'c.manager_phone','type'=>'like'],

            'phone'             => ['col'=>'c.phone','type'=>'like'],
            'fax'               => ['col'=>'c.fax','type'=>'like'],
            'email'             => ['col'=>'c.email','type'=>'like'],
            'homepage'          => ['col'=>'c.homepage','type'=>'like'],

            'address'           => ['col'=>'c.address','type'=>'like'],
            'address_detail'    => ['col'=>'c.address_detail','type'=>'like'],

            'client_type'       => ['col'=>'c.client_type','type'=>'like'],
            'client_category'   => ['col'=>'c.client_category','type'=>'like'],
            'trade_category'    => ['col'=>'c.trade_category','type'=>'like'],
            'default_account_text' => ['col'=>"CONCAT(COALESCE(da.account_code, ''), ' ', COALESCE(da.account_name, ''))",'type'=>'like'],
            'tax_type'          => ['col'=>'c.tax_type','type'=>'like'],
            'payment_term'      => ['col'=>'c.payment_term','type'=>'like'],
            'item_category'     => ['col'=>'c.item_category','type'=>'like'],

            'bank_name'         => ['col'=>'c.bank_name','type'=>'like'],
            'account_number'    => ['col'=>'c.account_number','type'=>'like'],
            'account_holder'    => ['col'=>'c.account_holder','type'=>'like'],

            'bank_file'         => ['col'=>'c.bank_file','type'=>'like'],
            'business_certificate' => ['col'=>'c.business_certificate','type'=>'like'],

            'note'              => ['col'=>'c.note','type'=>'like'],
            'memo'              => ['col'=>'c.memo','type'=>'like'],

            'is_active'         => ['col'=>'c.is_active','type'=>'exact'],

            'registration_date' => ['col'=>'c.registration_date','type'=>'date'],
            'created_at'        => ['col'=>'c.created_at','type'=>'date'],
            'updated_at'        => ['col'=>'c.updated_at','type'=>'date'],
        ];

        $globalSearch = [];

        foreach ($filters as $f) {

            $field = $f['field'] ?? '';
            $value = $f['value'] ?? '';

            if ($value === '' || $value === null) continue;

            if ($field === '') {
                $globalSearch[] = $value;
                continue;
            }

            if (!isset($fieldMap[$field])) continue;

            $col  = $fieldMap[$field]['col'];
            $type = $fieldMap[$field]['type'];

            if ($type === 'date') {

                if (is_array($value)) {
                    $sql .= " AND DATE($col) BETWEEN ? AND ?";
                    $params[] = $value['start'];
                    $params[] = $value['end'];
                } else {
                    $sql .= " AND DATE($col) = ?";
                    $params[] = $value;
                }
                continue;
            }

            if ($type === 'like') {
                $sql .= " AND $col LIKE ?";
                $params[] = "%{$value}%";
                continue;
            }

            if ($type === 'exact') {
                $sql .= " AND $col = ?";
                $params[] = $value;
                continue;
            }
        }

        if (!empty($globalSearch)) {

            $searchCols = [

                'c.client_name','c.company_name',
                'c.business_number','c.rrn',
                'c.ceo_name','c.manager_name',
                'c.phone','c.email',
                'c.address','c.address_detail',
                'c.business_type','c.business_category',
                'c.bank_name','c.account_number',
                 'c.account_holder',
                 'da.account_code',
                 'da.account_name',
                 'c.note','c.memo'
            ];

            $sql .= " AND (";

            $first = true;

            foreach ($globalSearch as $keyword) {

                if (!$first) $sql .= " OR ";

                $sql .= "(";

                $colFirst = true;

                foreach ($searchCols as $col) {

                    if (!$colFirst) $sql .= " OR ";

                    $sql .= "$col LIKE ?";
                    $params[] = "%{$keyword}%";

                    $colFirst = false;
                }

                $sql .= ")";
                $first = false;
            }

            $sql .= ")";
        }

        $sql .= " ORDER BY c.sort_no DESC, c.registration_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,

            da.account_code AS default_account_code,
            da.account_name AS default_account_name,
            NULLIF(CONCAT(COALESCE(da.account_code, ''), ' - ', COALESCE(da.account_name, '')), ' - ') AS default_account_text

            FROM system_clients c

            LEFT JOIN ledger_accounts da
                ON da.id = c.default_account_id
               AND da.deleted_at IS NULL

            WHERE c.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
    }

    public function findIdByBusinessNumber(string $businessNumber): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $businessNumber);

        if ($digits === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM system_clients
            WHERE business_number = :business_number
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'business_number' => $digits,
        ]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (string)$id : null;
    }

    public function findActiveByBusinessNumber(string $businessNumber): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM system_clients WHERE business_number = :business_number AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':business_number' => $businessNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findActiveByNames(array $names): ?array
    {
        $names = array_values(array_filter(array_unique(array_map('strval', $names))));
        if ($names === []) return null;
        $conditions = []; $params = [];
        foreach ($names as $index => $name) {
            $conditions[] = "(client_name = :client_name_{$index} OR company_name = :company_name_{$index})";
            $params[":client_name_{$index}"] = $name;
            $params[":company_name_{$index}"] = $name;
        }
        $stmt = $this->db->prepare('SELECT * FROM system_clients WHERE deleted_at IS NULL AND (' . implode(' OR ', $conditions) . ') LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateFields(string $id, array $updates): void
    {
        $allowed = ['client_name','company_name','business_number','business_type','business_category','address','phone','email','ceo_name','bank_name','account_number','account_holder','note','memo','updated_at','updated_by'];
        $updates = array_intersect_key($updates, array_flip($allowed));
        if ($updates === []) return;
        $sets = []; $params = [':id' => $id];
        foreach ($updates as $column => $value) { $sets[] = "{$column} = :{$column}"; $params[":{$column}"] = $value; }
        $this->db->prepare('UPDATE system_clients SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    }

    public function hasDifferentActiveClientWithName(string $name, string $businessNumber = '', ?string $excludeId = null): bool
    {
        $where = ['client_name = :client_name', 'deleted_at IS NULL']; $params = [':client_name' => $name];
        if ($excludeId) { $where[] = 'id <> :exclude_id'; $params[':exclude_id'] = $excludeId; }
        if ($businessNumber !== '') { $where[] = "(business_number IS NULL OR business_number = '' OR business_number <> :business_number)"; $params[':business_number'] = $businessNumber; }
        $stmt = $this->db->prepare('SELECT 1 FROM system_clients WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insertEvidenceHistory(array $row): void
    {
        $stmt = $this->db->prepare('INSERT INTO system_client_histories
            (id, client_id, field_name, old_value, new_value, source_type, source_evidence_id, changed_at, changed_by)
            VALUES (:id, :client_id, :field_name, :old_value, :new_value, :source_type, :source_evidence_id, NOW(), :changed_by)');
        $stmt->execute($row);
    }

    public function findIdByClientName(string $clientName): ?string
    {
        $stmt = $this->db->prepare('SELECT id FROM system_clients WHERE client_name = :name AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':name' => $clientName]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (string) $id;
    }

    public function updateCompanyName(string $clientId, string $companyName, string $actor): void
    {
        $stmt = $this->db->prepare("
            UPDATE system_clients
            SET client_name = :client_name,
                company_name = :company_name,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $clientId,
            ':client_name' => $companyName,
            ':company_name' => $companyName,
            ':actor' => $actor,
        ]);
    }

    public function resolveActiveIdByIdOrName(string $value): ?string
    {
        $stmt = $this->db->prepare('SELECT id FROM system_clients WHERE deleted_at IS NULL AND (id = :id_value OR client_name = :name_value) ORDER BY CASE WHEN id = :order_value THEN 0 ELSE 1 END, sort_no ASC LIMIT 1');
        $stmt->execute([':id_value' => $value, ':name_value' => $value, ':order_value' => $value]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (string) $id;
    }

    public function getActiveDropdownValues(string $field): array
    {
        if (!in_array($field, [
            'id', 'client_name', 'bank_name', 'trade_category', 'item_category',
            'client_category', 'client_type', 'tax_type', 'payment_term', 'client_grade',
        ], true)) return [];
        $stmt = $this->db->query("SELECT DISTINCT `{$field}` AS dropdown_value FROM system_clients WHERE deleted_at IS NULL AND COALESCE(is_active, 1) = 1 ORDER BY `{$field}` ASC");
        return array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['dropdown_value'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        ), static fn(string $value): bool => $value !== '')));
    }

public function searchPicker(string $keyword = '', int $limit = 20, array $options = []): array
{
    $limit = max(1, min(100, (int)$limit));

    $keyword = trim($keyword);
    $like = '%' . $keyword . '%';
    $prefix = $keyword . '%';

    $sql = "
        SELECT
            c.id,
            c.sort_no,
            c.client_name,
            c.business_number,
            c.company_name,
            c.ceo_name,
            c.phone,
            c.email,
            c.address,
            c.address_detail,
            c.client_type,
            ct.code_name AS client_type_name,
            c.default_account_id,
            da.account_code AS default_account_code,
            da.account_name AS default_account_name,
            NULLIF(CONCAT(COALESCE(da.account_code, ''), ' - ', COALESCE(da.account_name, '')), ' - ') AS default_account_text,
            c.is_active

        FROM system_clients c

        LEFT JOIN ledger_accounts da
            ON da.id = c.default_account_id
           AND da.deleted_at IS NULL

        LEFT JOIN system_codes ct
            ON ct.code_group = 'CLIENT_TYPE'
           AND NULLIF(TRIM(c.client_type), '') IS NOT NULL
           AND ct.code = c.client_type
           AND ct.is_active = 1

        WHERE c.deleted_at IS NULL
    ";

    $params = [
        ':k1' => $like,
        ':k2' => $like,
        ':k3' => $like,
        ':k4' => $like,
        ':k5' => $like,
        ':prefix' => $prefix,
    ];

    if (!empty($options['client_type'])) {
        $sql .= " AND c.client_type = :client_type";
        $params[':client_type'] = trim((string)$options['client_type']);
    }

    if (array_key_exists('is_active', $options) && $options['is_active'] !== '' && $options['is_active'] !== null) {
        $sql .= " AND c.is_active = :is_active";
        $params[':is_active'] = (int)$options['is_active'];
    }

    $sql .= "
        AND (
            c.client_name LIKE :k1
            OR c.company_name LIKE :k2
            OR c.business_number LIKE :k3
            OR c.ceo_name LIKE :k4
            OR c.phone LIKE :k5
        )

        ORDER BY
            CASE
                WHEN c.client_name LIKE :prefix THEN 0
                ELSE 1
            END,
            c.client_name ASC

        LIMIT {$limit}
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

    public function create(array $data): bool
    {
        $sql = "
        INSERT INTO system_clients (
            id, sort_no, client_name, company_name, registration_date,
            business_number, rrn, rrn_image,
            business_type, business_category,
            business_status, business_certificate,
            address, address_detail, phone, fax, email,
            ceo_name, ceo_phone,
            manager_name, manager_phone,
            homepage,
            bank_name,
            account_number,
            account_holder,
            bank_file,
            trade_category,
            default_account_id,
            item_category,
            client_category, client_type, tax_type, payment_term, client_grade,
            note, memo, is_active,
            created_by, updated_by
        ) VALUES (
            :id, :sort_no, :client_name, :company_name, :registration_date,
            :business_number, :rrn, :rrn_image,
            :business_type, :business_category,
            :business_status, :business_certificate,
            :address, :address_detail, :phone, :fax, :email,
            :ceo_name, :ceo_phone,
            :manager_name, :manager_phone,
            :homepage,
            :bank_name,
            :account_number,
            :account_holder,
            :bank_file,
            :trade_category,
            :default_account_id,
            :item_category,
            :client_category, :client_type, :tax_type, :payment_term, :client_grade,
            :note, :memo, :is_active,
            :created_by, :updated_by
        )";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'sort_no' => $data['sort_no'] ?? null,
            'client_name' => $data['client_name'] ?? '',
            'company_name' => $data['company_name'] ?? null,
            'registration_date' => $data['registration_date'] ?? date('Y-m-d'),

            'business_number' => $data['business_number'] ?? null,
            'rrn' => $data['rrn'] ?? null,
            'rrn_image' => $data['rrn_image'] ?? null,

            'business_type' => $data['business_type'] ?? null,
            'business_category' => $data['business_category'] ?? null,

            'business_status' => $data['business_status'] ?? null,
            'business_certificate' => $data['business_certificate'] ?? null,

            'address' => $data['address'] ?? null,
            'address_detail' => $data['address_detail'] ?? null,

            'phone' => $data['phone'] ?? null,
            'fax' => $data['fax'] ?? null,
            'email' => $data['email'] ?? null,

            'ceo_name' => $data['ceo_name'] ?? null,
            'ceo_phone' => $data['ceo_phone'] ?? null,

            'manager_name' => $data['manager_name'] ?? null,
            'manager_phone' => $data['manager_phone'] ?? null,

            'homepage' => $data['homepage'] ?? null,

            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'bank_file' => $data['bank_file'] ?? null,

            'trade_category' => $data['trade_category'] ?? null,
            'default_account_id' => $data['default_account_id'] ?? null,

            'item_category' => $data['item_category'] ?? null,

            'client_category' => $data['client_category'] ?? null,
            'client_type' => $data['client_type'] ?? null,
            'tax_type' => $data['tax_type'] ?? null,
            'payment_term' => $data['payment_term'] ?? null,
            'client_grade' => $data['client_grade'] ?? null,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,
            'is_active' => (int)($data['is_active'] ?? 1),

            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'] ?? $data['created_by']
        ]);
    }

    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_clients SET
                client_name = :client_name,
                company_name = :company_name,
                registration_date = :registration_date,

                business_number = :business_number,
                rrn = :rrn, rrn_image = :rrn_image,

                business_type = :business_type,
                business_category = :business_category,
                business_status = :business_status,
                business_certificate = :business_certificate,

                ceo_name = :ceo_name,
                ceo_phone = :ceo_phone,

                manager_name = :manager_name,
                manager_phone = :manager_phone,

                phone = :phone,
                fax = :fax,
                email = :email,

                address = :address,
                address_detail = :address_detail,

                homepage = :homepage,

                client_category = :client_category,

                bank_name = :bank_name,
                account_number = :account_number,
                account_holder = :account_holder,
                bank_file = :bank_file,

                trade_category = :trade_category,
                default_account_id = :default_account_id,

                client_type = :client_type,
                tax_type = :tax_type,
                payment_term = :payment_term,

                item_category = :item_category,
                client_grade = :client_grade,

                note = :note,
                memo = :memo,
                is_active = :is_active,

                updated_by = :updated_by

            WHERE id = :id
        ";

        $params = [
            'id' => $id,
            'client_name' => trim((string)($data['client_name'] ?? '')),
            'company_name' => $data['company_name'] ?? null,
            'registration_date' => $data['registration_date'] ?? date('Y-m-d'),

            'business_number' => $data['business_number'] ?? null,
            'rrn' => $data['rrn'] ?? null,
            'rrn_image' => $data['rrn_image'] ?? null,

            'business_type' => $data['business_type'] ?? null,
            'business_category' => $data['business_category'] ?? null,
            'business_status' => $data['business_status'] ?? null,
            'business_certificate' => $data['business_certificate'] ?? null,

            'ceo_name' => $data['ceo_name'] ?? null,
            'ceo_phone' => $data['ceo_phone'] ?? null,

            'manager_name' => $data['manager_name'] ?? null,
            'manager_phone' => $data['manager_phone'] ?? null,

            'phone' => $data['phone'] ?? null,
            'fax' => $data['fax'] ?? null,
            'email' => $data['email'] ?? null,

            'address' => $data['address'] ?? null,
            'address_detail' => $data['address_detail'] ?? null,

            'homepage' => $data['homepage'] ?? null,

            'client_category' => $data['client_category'] ?? null,

            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'bank_file' => $data['bank_file'] ?? null,

            'trade_category' => $data['trade_category'] ?? null,
            'default_account_id' => $data['default_account_id'] ?? null,

            'client_type' => $data['client_type'] ?? null,
            'tax_type' => $data['tax_type'] ?? null,
            'payment_term' => $data['payment_term'] ?? null,

            'item_category' => $data['item_category'] ?? null,

            'client_grade' => $data['client_grade'] ?? null,

            'note' => $data['note'] ?? null,
            'memo' => $data['memo'] ?? null,
            'is_active' => (int)($data['is_active'] ?? 1),

            'updated_by' => $data['updated_by']
        ];

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function insertCompanyNameHistory(string $clientId, string $oldCompanyName, string $newCompanyName, ?string $actor = null): bool
    {
        if (!$this->clientNameHistoryTableExists()) {
            return true;
        }

        $stmt = $this->db->prepare("
            INSERT INTO system_client_name_history (
                id, client_id, old_company_name, new_company_name, changed_at, changed_by
            ) VALUES (
                :id, :client_id, :old_company_name, :new_company_name, NOW(), :changed_by
            )
        ");

        return $stmt->execute([
            'id' => \Core\Helpers\UuidHelper::generate(),
            'client_id' => $clientId,
            'old_company_name' => $oldCompanyName,
            'new_company_name' => $newCompanyName,
            'changed_by' => $actor,
        ]);
    }

    public function getCompanyNameHistory(string $clientId): array
    {
        if (!$this->clientNameHistoryTableExists()) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT id, client_id, old_company_name, new_company_name, changed_at, changed_by
            FROM system_client_name_history
            WHERE client_id = :client_id
            ORDER BY changed_at DESC
        ");
        $stmt->execute(['client_id' => $clientId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteCompanyNameHistory(string $historyId): bool
    {
        if (!$this->clientNameHistoryTableExists()) {
            return false;
        }

        $stmt = $this->db->prepare("
            DELETE FROM system_client_name_history
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $historyId]);

        return $stmt->rowCount() > 0;
    }

    private function clientNameHistoryTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->db->prepare("
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

    public function deleteById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_clients
            SET
                is_active = 0,
                deleted_at = NOW(),
                deleted_by = :deleted_by,
                updated_by = :updated_by
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id' => $id,
            ':deleted_by' => $actor,
            ':updated_by' => $actor,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function getDeleted(): array
    {
        $stmt = $this->db->prepare("
        SELECT
            c.*

            FROM system_clients c

            WHERE c.deleted_at IS NOT NULL
            ORDER BY c.deleted_at DESC
        ");

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
        ]);
    }

    public function restoreById(string $id, string $actor): bool
    {
        $sql = "
            UPDATE system_clients
            SET
                is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':actor' => $actor
        ]);
    }

    public function hardDeleteById(string $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_clients
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id
        ]);
    }

    public function updateSortNo(string $id, string $newSortNo): bool
    {
        $sql = "UPDATE system_clients SET sort_no = :newSortNo WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'newSortNo' => (int)$newSortNo,
            'id' => $id
        ]);
    }



}
