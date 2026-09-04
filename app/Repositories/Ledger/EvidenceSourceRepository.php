<?php

namespace App\Repositories\Ledger;

use App\Models\Ledger\DailyEmploymentIncomeEvidenceReadModel;
use App\Models\Ledger\EvidenceSchemaModel;
use PDO;

class EvidenceSourceRepository
{
    private array $columnCache = [];
    private array $codeNameCache = [];
    private array $semanticColumnCache = [];
    private array $metadataCache = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function metadata(string $importType): ?array
    {
        $importType = strtoupper(trim($importType));
        if (array_key_exists($importType, $this->metadataCache)) {
            return $this->metadataCache[$importType];
        }
        $stmt = $this->pdo->prepare(
            'SELECT import_type, source_table, evidence_type FROM ledger_evidence_metadata'
            . ' WHERE import_type = :import_type AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':import_type' => $importType]);

        $this->metadataCache[$importType] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $this->metadataCache[$importType];
    }

    public function activeMetadataOptions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT import_type, evidence_type FROM ledger_evidence_metadata'
            . ' WHERE deleted_at IS NULL ORDER BY sort_no, import_type'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function semanticValues(string $importType, array $row): array
    {
        $importType = strtoupper(trim($importType));
        if (!array_key_exists($importType, $this->semanticColumnCache)) {
            $stmt = $this->pdo->prepare(
                'SELECT mc.semantic_key, mc.physical_column, mc.adjustment_direction FROM ledger_evidence_metadata m'
                . ' INNER JOIN ledger_evidence_metadata_columns mc ON mc.metadata_id = m.id'
                . ' WHERE m.import_type = :import_type AND m.deleted_at IS NULL ORDER BY mc.sort_no'
            );
            $stmt->execute([':import_type' => $importType]);
            $this->semanticColumnCache[$importType] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $values = [];
        foreach ($this->semanticColumnCache[$importType] as $mapping) {
            $semantic = strtoupper(trim((string) ($mapping['semantic_key'] ?? '')));
            $column = trim((string) ($mapping['physical_column'] ?? ''));
            if ($semantic !== '' && $column !== '' && array_key_exists($column, $row)) {
                $values[$semantic][] = $row[$column];
            }
        }
        return $values;
    }

    public function semanticEntries(string $importType, array $row): array
    {
        $this->semanticValues($importType, $row);
        $entries = [];
        foreach ($this->semanticColumnCache[strtoupper(trim($importType))] ?? [] as $mapping) {
            $column = trim((string) ($mapping['physical_column'] ?? ''));
            if ($column === '' || !array_key_exists($column, $row)) {
                continue;
            }
            $entries[] = [
                'semantic_key' => strtoupper(trim((string) ($mapping['semantic_key'] ?? ''))),
                'physical_column' => $column,
                'adjustment_direction' => strtoupper(trim((string) ($mapping['adjustment_direction'] ?? ''))),
                'value' => $row[$column],
            ];
        }
        return $entries;
    }

    public function standardDateField(string $importType): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT mc.physical_column FROM ledger_evidence_metadata m'
            . ' INNER JOIN ledger_evidence_metadata_columns mc ON mc.metadata_id = m.id'
            . " WHERE m.import_type = :import_type AND m.deleted_at IS NULL AND mc.semantic_key = 'BASE_DATE'"
            . ' ORDER BY mc.sort_no ASC LIMIT 1'
        );
        $stmt->execute([':import_type' => strtoupper(trim($importType))]);
        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    public function systemCodeName(string $codeGroup, string $code): string
    {
        $codeGroup = strtoupper(trim($codeGroup));
        $code = strtoupper(trim($code));
        if ($codeGroup === '' || $code === '') return '';
        $key = $codeGroup . ':' . $code;
        if (!array_key_exists($key, $this->codeNameCache)) {
            $stmt = $this->pdo->prepare(
                'SELECT code_name FROM system_codes WHERE code_group = :code_group AND code = :code'
                . ' AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([':code_group' => $codeGroup, ':code' => $code]);
            $this->codeNameCache[$key] = trim((string) ($stmt->fetchColumn() ?: ''));
        }
        return $this->codeNameCache[$key];
    }

    public function find(string $importType, string $evidenceId): ?array
    {
        if (in_array(strtoupper(trim($importType)), ['DAILY_EMPLOYMENT_INCOME', 'DAILY_WORK_REPORT', 'PAYROLL_WITHHOLDING'], true)) {
            return $this->findDailyEmploymentIncomeEvidence($evidenceId);
        }
        $metadata = $this->metadata($importType);
        if (!$metadata || trim($evidenceId) === '') {
            return null;
        }

        $table = $this->trustedTable((string) $metadata['source_table']);
        $columns = $this->columns($table);
        if (!isset($columns['id'])) {
            return null;
        }

        $importTypeWhere = isset($columns['import_type']) ? ' AND e.import_type = :import_type' : '';
        $stmt = $this->pdo->prepare(
            'SELECT ' . $this->selectList($table, $metadata)
            . " FROM `{$table}` e WHERE e.id = :evidence_id"
            . (isset($columns['deleted_at']) ? ' AND e.deleted_at IS NULL' : '')
            . $importTypeWhere
            . ' LIMIT 1'
        );
        $params = [':evidence_id' => $evidenceId];
        if ($importTypeWhere !== '') {
            $params[':import_type'] = (string) $metadata['import_type'];
        }
        $stmt->execute($params);
        $body = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($body) {
            return $this->enrichReferenceDisplayNames([$body])[0] ?? $body;
        }

        return null;
    }

    public function findMany(array $identities): array
    {
        $grouped = [];
        foreach ($identities as $identity) {
            $importType = strtoupper(trim((string) ($identity['import_type'] ?? '')));
            $evidenceId = trim((string) ($identity['evidence_id'] ?? ''));
            if ($importType !== '' && $evidenceId !== '') {
                $grouped[$importType][$evidenceId] = true;
            }
        }

        $result = [];
        foreach ($grouped as $importType => $ids) {
            if (in_array($importType, ['DAILY_EMPLOYMENT_INCOME', 'DAILY_WORK_REPORT', 'PAYROLL_WITHHOLDING'], true)) {
                foreach (array_keys($ids) as $evidenceId) {
                    $row = $this->findDailyEmploymentIncomeEvidence($evidenceId);
                    if ($row !== null) {
                        $result[$importType . "\0" . $evidenceId] = $row;
                    }
                }
                continue;
            }
            $metadata = $this->metadata($importType);
            if (!$metadata) continue;
            $table = $this->trustedTable((string) $metadata['source_table']);
            $columns = $this->columns($table);
            if (!isset($columns['id'])) continue;
            $values = array_keys($ids);
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $where = "e.id IN ({$placeholders})";
            $params = $values;
            if (isset($columns['deleted_at'])) $where .= ' AND e.deleted_at IS NULL';
            if (isset($columns['import_type'])) {
                $where .= ' AND e.import_type = ?';
                $params[] = $importType;
            }
            $stmt = $this->pdo->prepare('SELECT ' . $this->selectList($table, $metadata) . " FROM `{$table}` e WHERE {$where}");
            $stmt->execute($params);
            foreach ($this->enrichReferenceDisplayNames($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                $id = trim((string) ($row['evidence_id'] ?? $row['id'] ?? ''));
                if ($id !== '') $result[$importType . "\0" . $id] = $row;
            }
        }
        return $result;
    }

    private function findDailyEmploymentIncomeEvidence(string $evidenceId): ?array
    {
        if (trim($evidenceId) === '') return null;
        return (new DailyEmploymentIncomeEvidenceReadModel(
            $this->pdo,
            new EvidenceSchemaModel($this->pdo)
        ))->findById($evidenceId);

    }

    public function search(string $query, array $policyTypes, int $limit = 100): array
    {
        $policyTypes = array_values(array_intersect(['DATA', 'FUND', 'BOTH'], array_unique($policyTypes)));
        if ($policyTypes === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($policyTypes as $index => $policyType) {
            $key = ':policy_' . $index;
            $placeholders[] = $key;
            $params[$key] = $policyType;
        }
        $stmt = $this->pdo->prepare(
            'SELECT import_type, source_table, evidence_type FROM ledger_evidence_metadata'
            . ' WHERE deleted_at IS NULL AND evidence_type IN (' . implode(', ', $placeholders) . ')'
            . ' ORDER BY sort_no, import_type'
        );
        $stmt->execute($params);
        $metadataRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rows = [];
        foreach ($metadataRows as $index => $metadata) {
            $table = $this->trustedTable((string) $metadata['source_table']);
            $columns = $this->columns($table);
            if (!isset($columns['id'])) {
                continue;
            }
            $where = isset($columns['deleted_at']) ? 'e.deleted_at IS NULL' : '1=1';
            if (isset($columns['import_type'])) {
                $where .= " AND e.import_type = '" . str_replace("'", "''", (string) $metadata['import_type']) . "'";
            }
            if ($query !== '') {
                $searchColumns = array_values(array_intersect(
                    [
                        'id', 'source_key', 'evidence_date', 'transaction_date', 'client_name', 'description', 'summary', 'memo',
                        'raw_description', 'raw_counterparty_name', 'raw_merchant_company_name', 'raw_supplier_company_name',
                        'raw_customer_company_name', 'raw_approval_number', 'raw_approval_no', 'raw_transaction_amount_krw',
                        'raw_total_amount', 'raw_deposit_amount', 'raw_withdraw_amount',
                    ],
                    array_keys($columns)
                ));
                if ($searchColumns !== []) {
                    $searchClauses = [];
                    $queryParams = [];
                    foreach ($searchColumns as $columnIndex => $column) {
                        $key = ':query_' . $index . '_' . $columnIndex;
                        $searchClauses[] = "CAST(e.`{$column}` AS CHAR) LIKE {$key}";
                        $queryParams[$key] = '%' . $query . '%';
                    }
                    $where .= ' AND (' . implode(' OR ', $searchClauses) . ')';
                }
            }
            $sql = 'SELECT ' . $this->selectList($table, $metadata)
                . " FROM `{$table}` e WHERE {$where}"
                . ' ORDER BY ' . (isset($columns['created_at']) ? 'e.created_at DESC' : 'e.id DESC')
                . ' LIMIT ' . max(1, min($limit, 200));
            $search = $this->pdo->prepare($sql);
            $search->execute($queryParams ?? []);
            array_push($rows, ...($search->fetchAll(PDO::FETCH_ASSOC) ?: []));
            unset($queryParams, $key);
        }
        usort($rows, static function (array $left, array $right): int {
            $leftDate = (string) ($left['evidence_date'] ?? $left['created_at'] ?? '');
            $rightDate = (string) ($right['evidence_date'] ?? $right['created_at'] ?? '');
            return $rightDate <=> $leftDate;
        });
        $rows = array_slice($rows, 0, max(1, min($limit, 200)));
        return $this->enrichReferenceDisplayNames(array_slice($rows, 0, max(1, min($limit, 200))));
    }

    public function pagedProjections(array $criteria): array
    {
        $metadataRows = $this->activeMetadataRows($criteria);
        if ($metadataRows === []) {
            return ['records_total' => 0, 'records_filtered' => 0, 'projections' => []];
        }

        [$unionSql, $unionParams] = $this->identityUnion($metadataRows, $criteria);
        $recordsTotal = $this->identityCount($unionSql, $unionParams, '');
        [$filterSql, $filterParams] = $this->identityFilter($criteria);
        $recordsFiltered = $filterSql === ''
            ? $recordsTotal
            : $this->identityCount($unionSql, [...$unionParams, ...$filterParams], $filterSql);
        $identities = $this->pageIdentities($unionSql, [...$unionParams, ...$filterParams], $filterSql, $criteria);
        if ($identities === []) {
            return ['records_total' => $recordsTotal, 'records_filtered' => $recordsFiltered, 'projections' => []];
        }

        $includeDeleted = strtoupper(trim((string) ($criteria['status'] ?? ''))) === 'DELETED';
        $bodies = $this->bodyRowsByIdentity($identities, $includeDeleted);
        $links = $this->linkRowsByIdentity($identities);
        $projections = [];
        foreach ($identities as $identity) {
            $identityKey = $this->identityKey((string) $identity['import_type'], (string) $identity['evidence_id']);
            $body = $bodies[$identityKey] ?? null;
            if (!is_array($body)) {
                continue;
            }
            $body['import_type'] = (string) $identity['import_type'];
            $body['source_table'] = (string) $identity['source_table'];
            $body['standard_date_field'] = (string) ($identity['standard_date_field'] ?? '');
            $body['standard_date'] = $identity['standard_date'] ?? null;
            $projectionIdentity = array_intersect_key($identity, array_flip([
                'import_type',
                'evidence_id',
                'source_table',
                'sort_no',
                'source_type',
                'evidence_status',
                'evidence_type',
                'display_amount',
                'standard_date_field',
                'standard_date',
            ]));
            $projections[] = [
                'identity' => $projectionIdentity,
                'body' => $body,
                'links' => $links[$identityKey] ?? [],
            ];
        }

        return [
            'records_total' => $recordsTotal,
            'records_filtered' => $recordsFiltered,
            'projections' => $projections,
        ];
    }

    private function activeMetadataRows(array $criteria): array
    {
        $types = array_values(array_filter(array_unique(array_map(
            static fn(mixed $value): string => strtoupper(trim((string) $value)),
            is_array($criteria['import_types'] ?? null) ? $criteria['import_types'] : []
        ))));
        $sql = "SELECT m.import_type, m.source_table, m.evidence_type,
                    (SELECT mc.physical_column
                     FROM ledger_evidence_metadata_columns mc
                     WHERE mc.metadata_id = m.id AND mc.semantic_key = 'BASE_DATE'
                     ORDER BY mc.sort_no ASC LIMIT 1) AS standard_date_field,
                    (SELECT mc.physical_column
                     FROM ledger_evidence_metadata_columns mc
                     WHERE mc.metadata_id = m.id AND mc.semantic_key = 'DESCRIPTION'
                     ORDER BY mc.sort_no ASC LIMIT 1) AS description_field
                FROM ledger_evidence_metadata m WHERE m.deleted_at IS NULL";
        $params = [];
        $evidenceTypes = array_values(array_intersect(['DATA', 'FUND', 'BOTH'], array_map(
            static fn(mixed $value): string => strtoupper(trim((string) $value)),
            is_array($criteria['evidence_types'] ?? null) ? $criteria['evidence_types'] : []
        )));
        if ($types !== []) {
            $placeholders = [];
            foreach ($types as $index => $type) {
                $key = ':metadata_type_' . $index;
                $placeholders[] = $key;
                $params[$key] = $type;
            }
            $sql .= ' AND m.import_type IN (' . implode(', ', $placeholders) . ')';
        }
        if ($evidenceTypes !== []) {
            $placeholders = [];
            foreach ($evidenceTypes as $index => $type) {
                $key = ':metadata_evidence_type_' . $index;
                $placeholders[] = $key;
                $params[$key] = $type;
            }
            $sql .= ' AND m.evidence_type IN (' . implode(', ', $placeholders) . ')';
        }
        $sql .= ' ORDER BY m.sort_no, m.import_type';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function identityUnion(array $metadataRows, array $criteria): array
    {
        $parts = [];
        $params = [];
        $status = strtoupper(trim((string) ($criteria['status'] ?? '')));
        $requestedId = trim((string) ($criteria['id'] ?? ''));
        $identityFields = [
            'import_type', 'evidence_id', 'source_table', 'evidence_type', 'sort_no', 'source_type',
            'business_unit', 'operation_type', 'transaction_direction', 'evidence_status', 'client_id',
            'project_id', 'standard_date_field', 'standard_date', 'client_search_name', 'project_search_name',
            'employee_search_name', 'description',
            'display_amount', 'created_at', 'updated_at',
        ];
        $projectionFields = array_values(array_filter(array_unique(array_map(
            static fn(mixed $field): string => trim((string) $field),
            is_array($criteria['projection_fields'] ?? null) ? $criteria['projection_fields'] : []
        )), static fn(string $field): bool => preg_match('/^[a-z][a-z0-9_]*$/', $field) === 1
            && !in_array($field, $identityFields, true)));
        foreach ($metadataRows as $index => $metadata) {
            $table = $this->trustedTable((string) $metadata['source_table']);
            $columns = $this->columns($table);
            if (!isset($columns['id'])) {
                continue;
            }
            $value = static function (array $candidates, string $fallback = 'NULL') use ($columns): string {
                foreach ($candidates as $column) {
                    if (isset($columns[$column])) return "e.`{$column}`";
                }
                return $fallback;
            };
            $where = isset($columns['deleted_at'])
                ? ($status === 'DELETED' ? 'e.deleted_at IS NOT NULL' : 'e.deleted_at IS NULL')
                : '1=1';
            if (isset($columns['import_type'])) {
                $key = ':identity_import_type_' . $index;
                $where .= " AND e.import_type = {$key}";
                $params[$key] = (string) $metadata['import_type'];
            }
            if ($requestedId !== '') {
                $key = ':identity_id_' . $index;
                $where .= " AND e.id = {$key}";
                $params[$key] = $requestedId;
            }
            if ($status !== '' && $status !== 'DELETED' && isset($columns['evidence_status'])) {
                $key = ':identity_status_' . $index;
                $where .= " AND e.evidence_status = {$key}";
                $params[$key] = $status;
            }
            $literal = static fn(string $text): string => "'" . str_replace("'", "''", $text) . "'";
            $standardDateField = trim((string) ($metadata['standard_date_field'] ?? ''));
            $standardDateExpression = $standardDateField !== '' && isset($columns[$standardDateField])
                ? "e.`{$standardDateField}`"
                : $value(['evidence_date', 'transaction_date', 'write_date', 'approval_datetime', 'purchase_datetime'], 'NULL');
            $descriptionField = trim((string) ($metadata['description_field'] ?? ''));
            $descriptionExpression = $descriptionField !== '' && isset($columns[$descriptionField])
                ? "e.`{$descriptionField}`"
                : "''";
            $clientColumns = $this->tableExists('system_clients') ? $this->columns('system_clients') : [];
            $clientJoin = isset($columns['client_id'], $clientColumns['id'])
                ? ' LEFT JOIN system_clients client_ref ON client_ref.id = e.client_id'
                    . (isset($clientColumns['deleted_at']) ? ' AND client_ref.deleted_at IS NULL' : '')
                : '';
            $clientNameExpression = $value(['client_name', 'supplier_name', 'merchant_name', 'raw_counterparty_name'], "''");
            if ($clientJoin !== '') {
                $clientNameExpression = "COALESCE(NULLIF({$clientNameExpression}, ''), client_ref.client_name, '')";
            }
            $projectColumns = $this->tableExists('system_projects') ? $this->columns('system_projects') : [];
            $projectJoin = isset($columns['project_id'], $projectColumns['id'])
                ? ' LEFT JOIN system_projects project_ref ON project_ref.id = e.project_id'
                    . (isset($projectColumns['deleted_at']) ? ' AND project_ref.deleted_at IS NULL' : '')
                : '';
            $projectNameExpression = $value(['project_name'], "''");
            if ($projectJoin !== '') {
                $projectNameExpression = "COALESCE(NULLIF({$projectNameExpression}, ''), project_ref.project_name, '')";
            }
            $employeeColumns = $this->tableExists('user_employees') ? $this->columns('user_employees') : [];
            $employeeJoin = isset($columns['employee_id'], $employeeColumns['id'])
                ? ' LEFT JOIN user_employees employee_ref ON employee_ref.id = e.employee_id'
                    . (isset($employeeColumns['deleted_at']) ? ' AND employee_ref.deleted_at IS NULL' : '')
                : '';
            $employeeNameExpression = $value(['employee_name'], "''");
            if ($employeeJoin !== '') {
                $employeeNameExpression = "COALESCE(NULLIF({$employeeNameExpression}, ''), employee_ref.employee_name, '')";
            }
            $dynamicProjection = [];
            foreach ($projectionFields as $field) {
                $dynamicProjection[] = isset($columns[$field])
                    ? "e.`{$field}` AS `{$field}`"
                    : "NULL AS `{$field}`";
            }
            $parts[] = 'SELECT '
                . $literal((string) $metadata['import_type']) . ' AS import_type, '
                . 'e.id AS evidence_id, '
                . $literal($table) . ' AS source_table, '
                . $literal((string) $metadata['evidence_type']) . ' AS evidence_type, '
                . $value(['sort_no'], '0') . ' AS sort_no, '
                . $value(['source_type'], $literal((string) $metadata['import_type'])) . ' AS source_type, '
                . $value(['business_unit'], "''") . ' AS business_unit, '
                . $value(['operation_type'], "''") . ' AS operation_type, '
                . $value(['transaction_direction'], "''") . ' AS transaction_direction, '
                . $value(['evidence_status', 'status'], "''") . ' AS evidence_status, '
                . $value(['client_id'], "''") . ' AS client_id, '
                . $value(['project_id'], "''") . ' AS project_id, '
                . $literal($standardDateField) . ' AS standard_date_field, '
                . $standardDateExpression . ' AS standard_date, '
                . $clientNameExpression . ' AS client_search_name, '
                . $projectNameExpression . ' AS project_search_name, '
                . $employeeNameExpression . ' AS employee_search_name, '
                . $descriptionExpression . ' AS description, '
                . $value(['total_amount', 'raw_total_amount', 'raw_transaction_amount_krw', 'raw_actual_billing_amount', 'raw_billing_amount', 'raw_deposit_amount', 'raw_withdraw_amount', 'amount', 'transaction_amount', 'billing_amount', 'approved_amount', 'supply_amount'], '0') . ' AS display_amount, '
                . $value(['created_at'], 'NULL') . ' AS created_at, '
                . $value(['updated_at'], 'NULL') . ' AS updated_at'
                . ($dynamicProjection !== [] ? ', ' . implode(', ', $dynamicProjection) : '') . ' '
                . "FROM `{$table}` e{$clientJoin}{$projectJoin}{$employeeJoin} WHERE {$where}";
        }
        return [implode(' UNION ALL ', $parts), $params];
    }

    private function identityFilter(array $criteria): array
    {
        $filters = $criteria['filters'] ?? [];
        if (is_string($filters)) {
            $decoded = json_decode($filters, true);
            $filters = is_array($decoded) ? $decoded : [];
        }
        $allowed = [
            'import_type', 'source_type', 'business_unit', 'operation_type', 'transaction_direction',
            'evidence_status', 'client_id', 'project_id', 'standard_date', 'client_search_name',
            'project_search_name', 'employee_search_name', 'description', 'sort_no',
            'evidence_type', 'display_amount',
        ];
        foreach (is_array($criteria['projection_fields'] ?? null) ? $criteria['projection_fields'] : [] as $field) {
            $field = trim((string) $field);
            if (preg_match('/^[a-z][a-z0-9_]*$/', $field) === 1) {
                $allowed[] = $field;
            }
        }
        $allowed = array_values(array_unique($allowed));
        $clauses = [];
        $params = [];
        $keyword = trim((string) ($criteria['keyword'] ?? ''));
        if ($keyword !== '') {
            $keywordFields = ['import_type', 'source_type', 'standard_date', 'client_search_name', 'project_search_name', 'employee_search_name', 'description'];
            $keywordClauses = [];
            foreach ($keywordFields as $index => $field) {
                $key = ':identity_keyword_' . $index;
                $keywordClauses[] = "CAST(identity_rows.`{$field}` AS CHAR) LIKE {$key}";
                $params[$key] = '%' . $keyword . '%';
            }
            $clauses[] = '(' . implode(' OR ', $keywordClauses) . ')';
        }
        if (($criteria['unlinked_voucher_only'] ?? false) === true) {
            $currentVoucherId = trim((string) ($criteria['current_voucher_id'] ?? ''));
            $releasedClauses = [];
            foreach (is_array($criteria['released_voucher_evidences'] ?? null) ? $criteria['released_voucher_evidences'] : [] as $index => $evidence) {
                if (!is_array($evidence)) continue;
                $importType = strtoupper(trim((string) ($evidence['import_type'] ?? '')));
                $evidenceId = trim((string) ($evidence['evidence_id'] ?? ''));
                if ($currentVoucherId === '' || $importType === '' || $evidenceId === '') continue;
                $typeKey = ':released_voucher_type_' . $index;
                $idKey = ':released_voucher_evidence_id_' . $index;
                $releasedClauses[] = "(identity_rows.import_type = {$typeKey} AND identity_rows.evidence_id = {$idKey})";
                $params[$typeKey] = $importType;
                $params[$idKey] = $evidenceId;
            }
            $currentVoucherReleaseSql = '';
            if ($releasedClauses !== []) {
                $params[':released_current_voucher_id'] = $currentVoucherId;
                $currentVoucherReleaseSql = ' AND NOT (linked_voucher.id = :released_current_voucher_id AND ('
                    . implode(' OR ', $releasedClauses) . '))';
            }
            $clauses[] = "NOT EXISTS (SELECT 1 FROM ledger_evidence_links voucher_link"
                . " INNER JOIN ledger_vouchers linked_voucher ON linked_voucher.id = voucher_link.target_id"
                . " AND linked_voucher.deleted_at IS NULL"
                . " WHERE voucher_link.evidence_type = identity_rows.import_type"
                . " AND voucher_link.evidence_id = identity_rows.evidence_id"
                . " AND voucher_link.target_type = 'VOUCHER' AND voucher_link.deleted_at IS NULL"
                . $currentVoucherReleaseSql . ')';
        }
        if (($criteria['unlinked_transaction_only'] ?? false) === true) {
            $clauses[] = "NOT EXISTS (SELECT 1 FROM ledger_evidence_links transaction_link"
                . " INNER JOIN ledger_transactions linked_transaction ON linked_transaction.id = transaction_link.target_id"
                . " AND linked_transaction.deleted_at IS NULL"
                . " WHERE transaction_link.evidence_type = identity_rows.import_type"
                . " AND transaction_link.evidence_id = identity_rows.evidence_id"
                . " AND transaction_link.target_type = 'TRANSACTION' AND transaction_link.deleted_at IS NULL)";
        }
        foreach (is_array($criteria['exclude_evidences'] ?? null) ? $criteria['exclude_evidences'] : [] as $index => $evidence) {
            if (!is_array($evidence)) continue;
            $importType = strtoupper(trim((string) ($evidence['import_type'] ?? '')));
            $evidenceId = trim((string) ($evidence['evidence_id'] ?? ''));
            if ($importType === '' || $evidenceId === '') continue;
            $typeKey = ':exclude_type_' . $index;
            $idKey = ':exclude_id_' . $index;
            $clauses[] = "NOT (identity_rows.import_type = {$typeKey} AND identity_rows.evidence_id = {$idKey})";
            $params[$typeKey] = $importType;
            $params[$idKey] = $evidenceId;
        }
        foreach (is_array($filters) ? $filters : [] as $index => $filter) {
            if (!is_array($filter)) continue;
            $field = trim((string) ($filter['field'] ?? ''));
            $field = match ($field) {
                'client_name' => 'client_search_name',
                'project_name' => 'project_search_name',
                'employee_name' => 'employee_search_name',
                'evidence_date' => 'standard_date',
                default => $field,
            };
            $value = $filter['value'] ?? '';
            if (!in_array($field, $allowed, true) || $value === '' || $value === null) continue;
            if (is_array($value)) {
                $start = trim((string) ($value['start'] ?? ''));
                $end = trim((string) ($value['end'] ?? ''));
                if ($start !== '') {
                    $startKey = ':identity_filter_start_' . $index;
                    $clauses[] = "identity_rows.`{$field}` >= {$startKey}";
                    $params[$startKey] = $start;
                }
                if ($end !== '') {
                    $endKey = ':identity_filter_end_' . $index;
                    $clauses[] = "identity_rows.`{$field}` <= {$endKey}";
                    $params[$endKey] = $end;
                }
                continue;
            }
            $key = ':identity_filter_' . $index;
            $clauses[] = "CAST(identity_rows.`{$field}` AS CHAR) LIKE {$key}";
            $params[$key] = '%' . (string) $value . '%';
        }
        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function identityCount(string $unionSql, array $params, string $filterSql): int
    {
        if ($unionSql === '') return 0;
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM (' . $unionSql . ') identity_rows' . $filterSql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function pageIdentities(string $unionSql, array $params, string $filterSql, array $criteria): array
    {
        $start = max(0, (int) ($criteria['start'] ?? 0));
        $length = max(1, min(5000, (int) ($criteria['length'] ?? 100)));
        $allowedOrderFields = [
            'sort_no', 'standard_date', 'source_type', 'import_type',
            'business_unit', 'operation_type', 'transaction_direction', 'evidence_status',
            'client_search_name', 'description', 'created_at', 'updated_at',
            'project_search_name', 'employee_search_name',
            'display_amount',
        ];
        foreach (is_array($criteria['projection_fields'] ?? null) ? $criteria['projection_fields'] : [] as $field) {
            $field = trim((string) $field);
            if (preg_match('/^[a-z][a-z0-9_]*$/', $field) === 1) $allowedOrderFields[] = $field;
        }
        $requestedOrderField = (string) ($criteria['order_field'] ?? '');
        $orderField = in_array($requestedOrderField, $allowedOrderFields, true) ? $requestedOrderField : 'sort_no';
        $orderDirection = strtolower((string) ($criteria['order_direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $sql = 'SELECT * FROM (' . $unionSql . ') identity_rows' . $filterSql
            . " ORDER BY `{$orderField}` {$orderDirection}, import_type ASC, evidence_id ASC"
            . " LIMIT {$length} OFFSET {$start}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function bodyRowsByIdentity(array $identities, bool $includeDeleted = false): array
    {
        $groups = [];
        foreach ($identities as $identity) {
            $groups[(string) $identity['source_table']][] = $identity;
        }
        $result = [];
        foreach ($groups as $tableName => $group) {
            $table = $this->trustedTable($tableName);
            $ids = array_values(array_unique(array_column($group, 'evidence_id')));
            [$inSql, $params] = $this->placeholders($ids, 'body_id');
            $deletedScope = isset($this->columns($table)['deleted_at'])
                ? ($includeDeleted ? ' AND deleted_at IS NOT NULL' : ' AND deleted_at IS NULL')
                : '';
            $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE id IN ({$inSql})" . $deletedScope);
            $stmt->execute($params);
            $rowsById = [];
            $bodyRows = $this->enrichReferenceDisplayNames($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
            foreach ($bodyRows as $row) $rowsById[(string) $row['id']] = $row;
            foreach ($group as $identity) {
                $id = (string) $identity['evidence_id'];
                if (isset($rowsById[$id])) $result[$this->identityKey((string) $identity['import_type'], $id)] = $rowsById[$id];
            }
        }
        return $result;
    }

    private function enrichReferenceDisplayNames(array $rows): array
    {
        $references = [
            ['id' => 'client_id', 'name' => 'client_name', 'table' => 'system_clients', 'columns' => ['client_name', 'company_name']],
            ['id' => 'project_id', 'name' => 'project_name', 'table' => 'system_projects', 'columns' => ['project_name', 'construction_name', 'project_code']],
            ['id' => 'bank_account_id', 'name' => 'bank_account_name', 'table' => 'system_bank_accounts', 'columns' => ['account_name', 'bank_name', 'account_number']],
            ['id' => 'card_id', 'name' => 'card_name', 'table' => 'system_cards', 'columns' => ['card_name', 'card_number', 'card_company_name']],
            ['id' => 'work_team_id', 'name' => 'work_team_name', 'table' => 'system_work_teams', 'columns' => ['team_name']],
            ['id' => 'team_id', 'name' => 'team_name', 'table' => 'system_work_teams', 'columns' => ['team_name']],
            ['id' => 'employee_id', 'name' => 'employee_name', 'table' => 'user_employees', 'columns' => ['employee_name', 'name', 'username']],
            ['id' => 'source_personal_expense_item_id', 'name' => 'source_personal_expense_item_name', 'table' => 'approval_personal_expense_items', 'columns' => ['item_name', 'merchant_name', 'expense_category']],
            ['id' => 'source_regular_employment_income_id', 'name' => 'source_regular_employment_income_name', 'table' => 'institution_regular_employment_incomes', 'columns' => ['title']],
            ['id' => 'approval_request_id', 'name' => 'approval_request_name', 'table' => 'user_approval_requests', 'columns' => ['sort_no']],
            ['id' => 'regular_employment_income_item_id', 'name' => 'regular_employment_income_item_name', 'table' => 'institution_regular_employment_income_items', 'columns' => ['employee_name_snapshot']],
        ];
        foreach ($references as $reference) {
            $ids = array_values(array_unique(array_filter(array_map(
                static fn(array $row): string => trim((string) ($row[$reference['id']] ?? '')),
                $rows
            ))));
            if ($ids === [] || !$this->tableExists($reference['table'])) continue;
            $tableColumns = $this->columns($reference['table']);
            $nameColumns = array_values(array_filter(
                $reference['columns'],
                static fn(string $column): bool => isset($tableColumns[$column])
            ));
            if (!isset($tableColumns['id']) || $nameColumns === []) continue;
            [$inSql, $params] = $this->placeholders($ids, 'reference_' . $reference['id']);
            $deleted = isset($tableColumns['deleted_at']) ? ' AND deleted_at IS NULL' : '';
            $stmt = $this->pdo->prepare(
                'SELECT id, ' . implode(', ', $nameColumns)
                . " FROM `{$reference['table']}` WHERE id IN ({$inSql}){$deleted}"
            );
            $stmt->execute($params);
            $names = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $master) {
                foreach ($nameColumns as $column) {
                    $name = trim((string) ($master[$column] ?? ''));
                    if ($name !== '') {
                        $names[(string) $master['id']] = $name;
                        break;
                    }
                }
            }
            foreach ($rows as &$row) {
                $currentName = trim((string) ($row[$reference['name']] ?? ''));
                $id = trim((string) ($row[$reference['id']] ?? ''));
                if ($currentName === '' && isset($names[$id])) {
                    $row[$reference['name']] = $names[$id];
                }
            }
            unset($row);
        }
        return $rows;
    }

    private function linkRowsByIdentity(array $identities): array
    {
        if (!$this->tableExists('ledger_evidence_links')) return [];
        $clauses = [];
        $params = [];
        foreach ($identities as $index => $identity) {
            $clauses[] = "(evidence_type = :link_type_{$index} AND evidence_id = :link_id_{$index})";
            $params[":link_type_{$index}"] = (string) $identity['import_type'];
            $params[":link_id_{$index}"] = (string) $identity['evidence_id'];
        }
        $stmt = $this->pdo->prepare(
            "SELECT l.*, CASE
                WHEN l.target_type = 'TRANSACTION' AND EXISTS (
                    SELECT 1 FROM ledger_transactions t
                    WHERE t.id = l.target_id AND t.deleted_at IS NULL
                ) THEN 1
                WHEN l.target_type = 'VOUCHER' AND EXISTS (
                    SELECT 1 FROM ledger_vouchers v
                    WHERE v.id = l.target_id AND v.deleted_at IS NULL
                ) THEN 1 ELSE 0 END AS target_active
                , CASE WHEN l.target_type = 'VOUCHER' THEN (
                    SELECT COUNT(*)
                    FROM ledger_evidence_links bundle_link
                    WHERE bundle_link.target_type = 'VOUCHER'
                      AND bundle_link.target_id = l.target_id
                      AND bundle_link.deleted_at IS NULL
                ) ELSE 0 END AS voucher_evidence_count
             FROM ledger_evidence_links l
             WHERE l.deleted_at IS NULL AND (" . implode(' OR ', $clauses) . ')'
        );
        $stmt->execute($params);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[$this->identityKey((string) $row['evidence_type'], (string) $row['evidence_id'])][] = $row;
        }
        return $result;
    }

    private function placeholders(array $values, string $prefix): array
    {
        $sql = [];
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $key = ':' . $prefix . '_' . $index;
            $sql[] = $key;
            $params[$key] = $value;
        }
        return [implode(', ', $sql), $params];
    }

    private function identityKey(string $importType, string $evidenceId): string
    {
        return strtoupper(trim($importType)) . ':' . trim($evidenceId);
    }

    private function selectList(string $table, array $metadata): string
    {
        $columns = $this->columns($table);
        $value = static function (array $candidates, string $alias) use ($columns): string {
            foreach ($candidates as $column) {
                if (isset($columns[$column])) {
                    return "e.`{$column}` AS `{$alias}`";
                }
            }
            return "NULL AS `{$alias}`";
        };
        $literal = static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'";

        return implode(', ', [
            'e.*',
            'e.id AS evidence_id',
            'e.id AS id',
            $literal((string) $metadata['import_type']) . ' AS import_type',
            $literal((string) $metadata['import_type']) . ' AS source_type',
            $literal((string) $metadata['evidence_type']) . ' AS evidence_type',
            $literal($table) . ' AS source_table',
            $value(['source_key', 'transaction_id', 'approval_no'], 'source_key'),
            $value(['evidence_date', 'raw_expense_date', 'transaction_date', 'issue_date', 'written_date', 'purchase_date'], 'evidence_date'),
            $value(['client_name', 'supplier_name', 'merchant_name', 'counterparty_name'], 'client_name'),
            $value(['total_amount', 'amount', 'transaction_amount', 'billing_amount'], 'total_amount'),
            $value(['description', 'summary', 'memo', 'item_name'], 'display_summary'),
            $value(['created_at'], 'created_at'),
            $value(['updated_at'], 'updated_at'),
        ]);
    }

    private function trustedTable(string $table): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
            throw new \RuntimeException('증빙정책의 원본테이블 설정이 올바르지 않습니다.');
        }
        return $table;
    }

    private function columns(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }
        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute([':table_name' => $table]);
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $column) {
            $columns[(string) $column] = true;
        }
        return $this->columnCache[$table] = $columns;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name LIMIT 1'
        );
        $stmt->execute([':table_name' => $table]);
        return (bool) $stmt->fetchColumn();
    }
}
