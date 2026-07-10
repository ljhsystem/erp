<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceMetadataModel;
use App\Models\Ledger\EvidenceMetadataColumnModel;
use App\Repositories\Ledger\EvidenceMetadataRepository;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class EvidenceMetadataService
{
    private const ACTOR_FIELDS = [
        'created_by_name' => 'created_by',
        'updated_by_name' => 'updated_by',
        'deleted_by_name' => 'deleted_by',
    ];
    private const EVIDENCE_TYPES = ['DATA', 'FUND', 'BOTH'];
    private const PROCESS_ROLES = ['TRANSACTION_SSOT', 'REPORT_SSOT', 'TRANSACTION_REPORT_SSOT', 'REFERENCE'];
    private const SEMANTIC_KEYS = [
        'BASE_DATE',
        'IN_AMOUNT', 'OUT_AMOUNT', 'PRE_TAX_AMOUNT', 'ADJUST_AMOUNT', 'POST_TAX_AMOUNT',
        'SUPPLY_AMOUNT', 'SERVICE_AMOUNT',
        'VAT_AMOUNT', 'INCOME_TAX_AMOUNT', 'LOCAL_INCOME_TAX_AMOUNT', 'BUSINESS_INCOME_TAX_AMOUNT',
        'NATIONAL_PENSION_AMOUNT', 'HEALTH_INSURANCE_AMOUNT', 'LONG_TERM_CARE_AMOUNT',
        'EMPLOYMENT_INSURANCE_AMOUNT',
        'DESCRIPTION', 'MEMO',
    ];
    private const BASIS_FIELD_DEFINITIONS = [
        ['semantic_key' => 'BASE_DATE', 'group' => '날짜', 'label' => '기준일', 'description' => '증빙과 후속 회계처리의 기준 날짜'],
        ['semantic_key' => 'IN_AMOUNT', 'group' => '금액', 'label' => '입금금액', 'description' => '자금이 유입된 금액'],
        ['semantic_key' => 'OUT_AMOUNT', 'group' => '금액', 'label' => '출금금액', 'description' => '자금이 유출된 금액'],
        ['semantic_key' => 'PRE_TAX_AMOUNT', 'group' => '금액', 'label' => '세전금액', 'description' => '세금과 가감 전 금액'],
        ['semantic_key' => 'POST_TAX_AMOUNT', 'group' => '금액', 'label' => '세후금액', 'description' => '세금과 가감 반영 후 최종 금액'],
    ];
    private const SEMANTIC_CANDIDATES = [
        'BASE_DATE' => ['base_date', 'transaction_date', 'transaction_datetime', 'purchase_datetime', 'evidence_date', 'written_date', 'issue_date', 'approval_date', 'billing_date', 'date'],
        'IN_AMOUNT' => ['deposit_amount', 'income_amount', 'in_amount', 'credit_amount'],
        'OUT_AMOUNT' => ['withdraw_amount', 'expense_amount', 'out_amount', 'debit_amount'],
        'PRE_TAX_AMOUNT' => ['pre_tax_amount', 'supply_amount', 'transaction_amount', 'transaction_amount_krw', 'purchase_amount_krw', 'amount'],
        'POST_TAX_AMOUNT' => ['post_tax_amount', 'total_amount', 'final_amount', 'billing_amount', 'actual_billing_amount', 'grand_total'],
    ];
    private const ADJUSTMENT_CANDIDATES = [
        'ADD' => ['service_amount', 'service_fee_amount', 'service_charge_amount', 'surcharge_amount', 'additional_amount'],
        'DEDUCT' => ['card_fee', 'card_fee_amount', 'pg_fee', 'pg_fee_amount', 'delivery_fee', 'delivery_fee_amount', 'billing_fee_amount', 'fee_amount', 'commission_amount', 'discount_amount'],
    ];

    private EvidenceMetadataModel $model;
    private EvidenceMetadataColumnModel $columnModel;
    private EvidenceMetadataRepository $repository;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new EvidenceMetadataModel($pdo);
        $this->columnModel = new EvidenceMetadataColumnModel($pdo);
        $this->repository = new EvidenceMetadataRepository($pdo);
    }

    public function getList(array $filters = []): array
    {
        return ActorHelper::enrichActorNames($this->model->getList($filters), self::ACTOR_FIELDS);
    }

    public function getTrashList(): array
    {
        return ActorHelper::enrichActorNames($this->model->getList([], true), self::ACTOR_FIELDS);
    }

    public function getById(string $id): ?array
    {
        $row = $this->model->getById($id);
        if (!$row) {
            return null;
        }
        $mappings = $this->columnModel->getByMetadataId($id);
        $row['mappings'] = $mappings;
        return ActorHelper::enrichActorNamesRow($row, self::ACTOR_FIELDS);
    }

    public function sourceColumns(string $tableName): array
    {
        $tableName = $this->normalizeIdentifier($tableName, '원본테이블을 선택해 주세요.');
        if (!$this->repository->tableExists($tableName)) {
            throw new \InvalidArgumentException('실제 DB에 존재하는 원본테이블을 선택해 주세요.');
        }
        return $this->repository->sourceColumns($tableName);
    }

    public function recommend(string $importType): array
    {
        $importType = strtoupper(trim($importType));
        if ($importType === '' || !$this->repository->activeImportTypeExists($importType)) {
            throw new \InvalidArgumentException('공용 자료유형 코드에서 자료유형을 선택해 주세요.');
        }
        $sourceTable = $this->repository->recommendSourceTable($importType);
        if ($sourceTable === null) {
            throw new \InvalidArgumentException('자료유형에 대응하는 증빙 원본테이블을 찾을 수 없습니다.');
        }
        $columns = $this->repository->sourceColumns($sourceTable);
        $columnNames = array_column($columns, 'name');
        $mappings = [];
        foreach ($this->basisSemanticKeys() as $semanticKey) {
            $physicalColumn = $this->recommendPhysicalColumn($semanticKey, $columnNames);
            if ($physicalColumn !== null) {
                $mappings[] = [
                    'semantic_key' => $semanticKey,
                    'physical_column' => $physicalColumn,
                    'is_required' => 'N',
                    'remark' => '실제 컬럼명 기준 자동 추천',
                ];
            }
        }
        foreach (self::ADJUSTMENT_CANDIDATES as $direction => $candidates) {
            foreach ($this->recommendPhysicalColumns($candidates, $columnNames) as $physicalColumn) {
                $mappings[] = [
                    'semantic_key' => 'ADJUST_AMOUNT',
                    'physical_column' => $physicalColumn,
                    'adjustment_direction' => $direction,
                    'is_required' => 'N',
                    'remark' => '실제 컬럼명 기준 자동 추천',
                ];
            }
        }
        $mappedKeys = array_fill_keys(array_column($mappings, 'semantic_key'), true);
        $hasFundAmount = isset($mappedKeys['IN_AMOUNT']) || isset($mappedKeys['OUT_AMOUNT']);
        $hasTaxAmount = $this->hasColumnMeaning($columnNames, [
            'vat_amount', 'income_tax_amount', 'local_income_tax_amount', 'business_income_tax_amount',
        ]);
        $hasTransactionAmount = isset($mappedKeys['PRE_TAX_AMOUNT']) || isset($mappedKeys['POST_TAX_AMOUNT']) || $hasFundAmount;

        return [
            'import_type' => $importType,
            'source_table' => $sourceTable,
            'evidence_type' => $hasFundAmount ? 'FUND' : 'DATA',
            'process_role' => $hasTaxAmount && $hasTransactionAmount
                ? 'TRANSACTION_REPORT_SSOT'
                : ($hasTransactionAmount ? 'TRANSACTION_SSOT' : 'REFERENCE'),
            'columns' => $columns,
            'mappings' => $mappings,
        ];
    }

    public function policyOptions(): array
    {
        $registeredRows = [...$this->model->getList(), ...$this->model->getList([], true)];
        $registered = array_fill_keys(array_column($registeredRows, 'import_type'), true);
        $importTypes = [];
        foreach ($this->repository->activeImportTypes() as $row) {
            $sourceTable = $this->repository->recommendSourceTable((string) ($row['code'] ?? ''));
            if ($sourceTable === null) {
                continue;
            }
            $row['source_table'] = $sourceTable;
            $row['is_registered'] = isset($registered[(string) ($row['code'] ?? '')]);
            $importTypes[] = $row;
        }
        return ['import_types' => $importTypes, 'basis_fields' => self::BASIS_FIELD_DEFINITIONS];
    }

    public function save(array $payload): array
    {
        $id = trim((string) ($payload['id'] ?? ''));
        $existing = $id !== '' ? $this->model->getById($id) : null;
        if ($id !== '' && !$existing) {
            throw new \InvalidArgumentException('증빙정책을 찾을 수 없습니다.');
        }
        if ($existing) {
            $requestedImportType = strtoupper(trim((string) ($payload['import_type'] ?? '')));
            $requestedSourceTable = trim((string) ($payload['source_table'] ?? ''));
            if ($requestedImportType !== (string) $existing['import_type']
                || $requestedSourceTable !== (string) $existing['source_table']) {
                throw new \InvalidArgumentException('자료유형과 원본테이블은 수정할 수 없습니다.');
            }
        }
        $normalized = $this->normalizePayload($payload);
        $data = $normalized['header'];
        $mappings = $normalized['mappings'];

        if ($this->model->importTypeExists($data['import_type'], $id !== '' ? $id : null)) {
            throw new \InvalidArgumentException('이미 등록된 자료유형입니다.');
        }

        $actor = ActorHelper::user();
        $timestamp = date('Y-m-d H:i:s');
        $data['updated_at'] = $timestamp;
        $data['updated_by'] = $actor;

        try {
            $this->pdo->beginTransaction();
            $created = $id === '';
            if ($created) {
                $id = UuidHelper::generate();
                $data = ['id' => $id, 'sort_no' => SequenceHelper::next('ledger_evidence_metadata', 'sort_no'), ...$data,
                    'created_at' => $timestamp, 'created_by' => $actor];
                $ok = $this->model->create($data);
            } else {
                $ok = $this->model->update($id, $data);
            }
            if (!$ok) {
                throw new \RuntimeException($created ? '저장 중 오류가 발생했습니다.' : '수정 중 오류가 발생했습니다.');
            }
            if (!$created) {
                foreach ($this->columnModel->getByMetadataId($id) as $existingMapping) {
                    $semanticKey = (string) ($existingMapping['semantic_key'] ?? '');
                    if (in_array($semanticKey, $this->controlledSemanticKeys(), true)) {
                        continue;
                    }
                    if (in_array($semanticKey, self::SEMANTIC_KEYS, true)) {
                        $existingMapping['adjustment_direction'] = null;
                        $mappings[] = $existingMapping;
                    }
                }
            }
            $this->validateMappingSet($mappings);
            $this->columnModel->replace($id, $mappings, $actor, $timestamp);
            $this->pdo->commit();
            return ['success' => true, 'data' => ['id' => $id],
                'message' => $created ? '증빙정책이 등록되었습니다.' : '증빙정책이 수정되었습니다.'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(string $id): array
    {
        $ok = $this->model->delete($id, ActorHelper::user());
        return [
            'success' => $ok,
            'data' => ['deleted_count' => $ok ? 1 : 0],
            'message' => $ok ? '증빙정책이 휴지통으로 이동되었습니다.' : '삭제할 증빙정책을 찾을 수 없습니다.',
        ];
    }

    public function deleteBulk(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        $count = $this->model->deleteByIds($ids, ActorHelper::user());
        return $this->countResult('deleted_count', $count, count($ids), '선택한 증빙정책이 휴지통으로 이동되었습니다.', '삭제할 증빙정책이 없습니다.');
    }

    public function restore(string $id): array
    {
        $ok = $this->model->restore($id, ActorHelper::user());
        return [
            'success' => $ok,
            'data' => ['restored_count' => $ok ? 1 : 0],
            'message' => $ok ? '증빙정책이 복원되었습니다.' : '복원할 증빙정책을 찾을 수 없습니다.',
        ];
    }

    public function restoreBulk(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        $count = $this->model->restoreByIds($ids, ActorHelper::user());
        return $this->countResult('restored_count', $count, count($ids), '선택한 증빙정책이 복원되었습니다.', '복원할 증빙정책이 없습니다.');
    }

    public function restoreAll(): array
    {
        $count = $this->model->restoreAll(ActorHelper::user());
        return $this->countResult('restored_count', $count, $count, '휴지통의 증빙정책이 모두 복원되었습니다.', '복원할 증빙정책이 없습니다.');
    }

    public function purge(string $id): array
    {
        $ok = $this->model->purge($id);
        return [
            'success' => $ok,
            'data' => ['deleted_count' => $ok ? 1 : 0],
            'message' => $ok ? '증빙정책이 영구삭제되었습니다.' : '영구삭제할 증빙정책을 찾을 수 없습니다.',
        ];
    }

    public function purgeBulk(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        $count = $this->model->purgeByIds($ids);
        return $this->countResult('deleted_count', $count, count($ids), '선택한 증빙정책이 영구삭제되었습니다.', '영구삭제할 증빙정책이 없습니다.');
    }

    public function purgeAll(): array
    {
        $count = $this->model->purgeAll();
        return $this->countResult('deleted_count', $count, $count, '휴지통의 증빙정책이 모두 영구삭제되었습니다.', '영구삭제할 증빙정책이 없습니다.');
    }

    public function reorder(array $changes): array
    {
        foreach ($changes as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $sortNo = (int) ($row['newSortNo'] ?? $row['sort_no'] ?? 0);
            if ($id !== '' && $sortNo > 0) {
                $this->model->updateOrder($id, $sortNo);
            }
        }
        return ['success' => true, 'message' => '증빙정책 순서가 저장되었습니다.'];
    }

    private function normalizePayload(array $payload): array
    {
        $importType = strtoupper(trim((string) ($payload['import_type'] ?? '')));
        $sourceTable = $this->normalizeIdentifier((string) ($payload['source_table'] ?? ''), '원본테이블은 필수입니다.');
        $evidenceType = strtoupper(trim((string) ($payload['evidence_type'] ?? '')));
        $processRole = strtoupper(trim((string) ($payload['process_role'] ?? '')));

        if ($importType === '' || !$this->repository->activeImportTypeExists($importType)) {
            throw new \InvalidArgumentException('공용 자료유형 코드에서 자료유형을 선택해 주세요.');
        }
        if (!$this->repository->tableExists($sourceTable)) {
            throw new \InvalidArgumentException('실제 DB에 존재하는 원본테이블을 선택해 주세요.');
        }
        if ($this->repository->recommendSourceTable($importType) !== $sourceTable) {
            throw new \InvalidArgumentException('자료유형에 연결된 원본테이블이 올바르지 않습니다.');
        }
        if (!in_array($evidenceType, self::EVIDENCE_TYPES, true)) {
            throw new \InvalidArgumentException('증빙유형 값이 올바르지 않습니다.');
        }
        if (!in_array($processRole, self::PROCESS_ROLES, true)) {
            throw new \InvalidArgumentException('처리역할 값이 올바르지 않습니다.');
        }
        $columnSet = array_fill_keys(array_column($this->repository->sourceColumns($sourceTable), 'name'), true);
        $header = [
            'import_type' => $importType,
            'source_table' => $sourceTable,
            'evidence_type' => $evidenceType,
            'process_role' => $processRole,
        ];

        $mappingPayload = is_array($payload['mappings'] ?? null) ? $payload['mappings'] : [];
        $unexpectedSemanticKeys = array_diff(array_map('strval', array_keys($mappingPayload)), $this->basisSemanticKeys());
        if ($unexpectedSemanticKeys !== []) {
            throw new \InvalidArgumentException('허용되지 않은 컬럼 의미가 포함되어 있습니다.');
        }
        $mappings = [];
        foreach ($this->basisSemanticKeys() as $semanticKey) {
            $column = trim((string) ($mappingPayload[$semanticKey] ?? ''));
            if ($column !== '' && !isset($columnSet[$column])) {
                throw new \InvalidArgumentException("{$semanticKey}에 실제 원본테이블 컬럼을 선택해 주세요.");
            }
            if ($column !== '') {
                $mappings[] = [
                    'semantic_key' => $semanticKey,
                    'physical_column' => $column,
                    'adjustment_direction' => null,
                    'is_required' => 'N',
                    'remark' => null,
                ];
            }
        }

        $adjustments = is_array($payload['adjustments'] ?? null) ? $payload['adjustments'] : [];
        foreach ($adjustments as $adjustment) {
            if (!is_array($adjustment)) {
                throw new \InvalidArgumentException('가감항목 형식이 올바르지 않습니다.');
            }
            $column = trim((string) ($adjustment['physical_column'] ?? ''));
            $direction = strtoupper(trim((string) ($adjustment['adjustment_direction'] ?? '')));
            if ($column === '' && $direction === '') {
                continue;
            }
            if ($column === '' || !isset($columnSet[$column])) {
                throw new \InvalidArgumentException('가감항목에 실제 원본테이블 컬럼을 선택해 주세요.');
            }
            if (!in_array($direction, ['ADD', 'DEDUCT'], true)) {
                throw new \InvalidArgumentException('가감구분은 추가 또는 차감을 선택해 주세요.');
            }
            $mappings[] = [
                'semantic_key' => 'ADJUST_AMOUNT',
                'physical_column' => $column,
                'adjustment_direction' => $direction,
                'is_required' => 'N',
                'remark' => null,
            ];
        }

        $this->validateMappingSet($mappings);

        return ['header' => $header, 'mappings' => $mappings];
    }

    private function normalizeIdentifier(string $value, string $message): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z0-9_]+$/', $value) !== 1) {
            throw new \InvalidArgumentException($message);
        }
        return $value;
    }

    private function recommendPhysicalColumn(string $semanticKey, array $columnNames): ?string
    {
        $normalizedColumns = [];
        foreach ($columnNames as $columnName) {
            $normalized = strtolower((string) $columnName);
            $normalizedColumns[$normalized] = (string) $columnName;
            if (str_starts_with($normalized, 'raw_')) {
                $normalizedColumns[substr($normalized, 4)] = (string) $columnName;
            }
        }
        foreach (self::SEMANTIC_CANDIDATES[$semanticKey] ?? [$semanticKey] as $candidate) {
            $candidate = strtolower($candidate);
            if (isset($normalizedColumns[$candidate])) {
                return $normalizedColumns[$candidate];
            }
        }
        return null;
    }

    private function recommendPhysicalColumns(array $candidates, array $columnNames): array
    {
        $normalizedColumns = [];
        foreach ($columnNames as $columnName) {
            $normalized = strtolower((string) $columnName);
            $normalizedColumns[$normalized] = (string) $columnName;
            if (str_starts_with($normalized, 'raw_')) {
                $normalizedColumns[substr($normalized, 4)] = (string) $columnName;
            }
        }

        $matched = [];
        foreach ($candidates as $candidate) {
            $column = $normalizedColumns[strtolower((string) $candidate)] ?? null;
            if ($column !== null) {
                $matched[$column] = $column;
            }
        }
        return array_values($matched);
    }

    private function basisSemanticKeys(): array
    {
        return array_column(self::BASIS_FIELD_DEFINITIONS, 'semantic_key');
    }

    private function controlledSemanticKeys(): array
    {
        return [...$this->basisSemanticKeys(), 'ADJUST_AMOUNT'];
    }

    private function validateMappingSet(array $mappings): void
    {
        $physicalColumns = [];
        $compositeKeys = [];
        foreach ($mappings as $mapping) {
            $semanticKey = strtoupper(trim((string) ($mapping['semantic_key'] ?? '')));
            $physicalColumn = trim((string) ($mapping['physical_column'] ?? ''));
            $direction = $mapping['adjustment_direction'] ?? null;
            $direction = $direction === null ? null : strtoupper(trim((string) $direction));

            if (!in_array($semanticKey, self::SEMANTIC_KEYS, true)) {
                throw new \InvalidArgumentException('허용되지 않은 컬럼 의미가 포함되어 있습니다.');
            }
            if ($semanticKey === 'ADJUST_AMOUNT') {
                if (!in_array($direction, ['ADD', 'DEDUCT'], true)) {
                    throw new \InvalidArgumentException('가감금액에는 추가 또는 차감 구분이 필요합니다.');
                }
            } elseif ($direction !== null && $direction !== '') {
                throw new \InvalidArgumentException('가감구분은 가감금액에만 설정할 수 있습니다.');
            }

            if (isset($physicalColumns[$physicalColumn])) {
                throw new \InvalidArgumentException('동일한 원본컬럼을 여러 의미에 중복 등록할 수 없습니다.');
            }
            $physicalColumns[$physicalColumn] = true;

            $compositeKey = $semanticKey . "\0" . $physicalColumn;
            if (isset($compositeKeys[$compositeKey])) {
                throw new \InvalidArgumentException('동일한 컬럼 의미와 원본컬럼 조합이 중복되었습니다.');
            }
            $compositeKeys[$compositeKey] = true;
        }
    }

    private function hasColumnMeaning(array $columnNames, array $candidates): bool
    {
        foreach ($columnNames as $columnName) {
            $normalized = strtolower((string) $columnName);
            if (str_starts_with($normalized, 'raw_')) {
                $normalized = substr($normalized, 4);
            }
            if (in_array($normalized, $candidates, true)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn(mixed $id): string => trim((string) $id),
            $ids
        ))));
    }

    private function countResult(string $countKey, int $count, int $requested, string $successMessage, string $emptyMessage): array
    {
        return [
            'success' => true,
            'message' => $count > 0 ? $successMessage : $emptyMessage,
            'data' => [
                $countKey => $count,
                'skipped_count' => max(0, $requested - $count),
            ],
        ];
    }

}
