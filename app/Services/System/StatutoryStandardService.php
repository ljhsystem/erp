<?php

namespace App\Services\System;

use App\Models\System\StatutoryStandardModel;
use App\Models\System\StatutoryStandardSourceModel;
use App\Models\System\StatutoryStandardSupersessionModel;
use App\Services\File\FileService;
use App\Services\Concerns\LogsServiceOperations;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;
use PDO;

class StatutoryStandardService
{
    use LogsServiceOperations;
    private const SOURCE_UPLOAD_POLICY_KEY = 'public_document';
    private const INSURANCE_TYPES = ['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE','INDUSTRIAL_ACCIDENT'];

    private StatutoryStandardModel $model;
    private StatutoryStandardSourceModel $sources;
    private StatutoryStandardSupersessionModel $supersessions;
    private StatutoryStandardTemplateService $templates;
    private DataTableColumnMetaService $columnMeta;
    private StatutoryStandardValueSummaryService $valueSummary;
    private FileService $files;
    private ?string $writeActor;
    private $logger;

    public function __construct(private PDO $db, ?string $writeActor = null)
    {
        $this->writeActor = $writeActor;
        $this->model = new StatutoryStandardModel($db);
        $this->sources = new StatutoryStandardSourceModel($db);
        $this->supersessions = new StatutoryStandardSupersessionModel($db);
        $this->templates = new StatutoryStandardTemplateService($db);
        $this->columnMeta = new DataTableColumnMetaService($db);
        $this->valueSummary = new StatutoryStandardValueSummaryService();
        $this->files = new FileService($db);
        $this->logger = LoggerFactory::getLogger('service-system-statutory-standard');
    }

    public function list(array $query): array
    {
        $page = $this->model->page($query);
        $summaryFields = $this->templates->summaryFields();
        foreach ($page['rows'] as &$row) {
            $values = $this->decode((string) $row['value_data']);
            $type = (string)($row['standard_type_code'] ?? '');
            $summaryKey = $this->templates->summaryFieldKey(
                $type,
                in_array($type, self::INSURANCE_TYPES, true) ? (string)($row['policy_component_code'] ?? '') : null,
                in_array($type, self::INSURANCE_TYPES, true) ? (string)($row['employment_type_code'] ?? '') : null,
                in_array($type, self::INSURANCE_TYPES, true) ? (string)($row['work_scope_code'] ?? '') : null
            );
            $firstField = $summaryFields[$summaryKey] ?? null;
            $row += $this->valueSummary->project($row, $values, is_array($firstField) ? $firstField : null);
            $row['standard_combination_name'] = $this->combinationName($row);
            unset($row['value_data']);
        }
        unset($row);
        return ['success' => true, 'data' => $page['rows'], 'draw' => (int) ($query['draw'] ?? 0),
            'recordsTotal' => $page['total'], 'recordsFiltered' => $page['filtered']];
    }

    public function detail(string $id): array
    {
        if ($id === '') {
            throw new \InvalidArgumentException('법정기준 ID가 필요합니다.');
        }
        $row = $this->model->detail($id);
        if (!$row) {
            throw new \RuntimeException('법정기준을 찾을 수 없습니다.');
        }
        $row['value_data'] = $this->decode((string) $row['value_data']);
        $row['standard_combination_name'] = $this->combinationName($row);
        return ['success' => true, 'data' => $row];
    }

    public function revisionChain(string $id): array
    {
        if ($id === '') {
            throw new \InvalidArgumentException('법정기준 Revision ID가 필요합니다.');
        }
        return ['success' => true, 'data' => $this->supersessions->chain($id)];
    }

    public function createRevisionCorrection(array $input, array $sourceFiles = []): array
    {
        return $this->loggedStandardMutation('법정기준 Revision 정정','STATUTORY_STANDARD_CORRECTION','correct',fn():array=>$this->createRevisionCorrectionInternal($input,$sourceFiles));
    }

    private function createRevisionCorrectionInternal(array $input, array $sourceFiles = []): array
    {
        $predecessorId = trim((string)($input['supersedes_revision_id'] ?? ''));
        $reason = trim((string)($input['correction_reason'] ?? ''));
        if ($predecessorId === '' || $reason === '') {
            throw new \InvalidArgumentException('정정 대상 Revision과 정정 사유가 필요합니다.');
        }
        if (trim((string)($input['id'] ?? '')) !== '') {
            throw new \InvalidArgumentException('Revision 정정은 기존 행 수정이 아니라 신규 Revision 생성으로 처리해야 합니다.');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $predecessor = $this->model->detail($predecessorId, true);
            if (!$predecessor) {
                throw new \InvalidArgumentException('정정 대상 법정기준 Revision을 찾을 수 없습니다.');
            }
            $result = $this->saveInternal($input + ['_allow_supersession_overlap' => '1'], $sourceFiles);
            $successorId = (string)($result['data']['id'] ?? '');
            $this->supersessions->create(
                $predecessorId,
                $successorId,
                $reason,
                $this->writeActor ?? ActorHelper::user()
            );
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return ['success' => true, 'data' => ['id' => $successorId], 'message' => 'Revision 정정을 등록했습니다.'];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function combinationName(array $row): string
    {
        $typeName = (string)($row['standard_type_name'] ?? $row['standard_type_code'] ?? '');
        if (!in_array((string)($row['standard_type_code'] ?? ''), self::INSURANCE_TYPES, true)) return $typeName;
        $component = (string)($row['policy_component_code'] ?? '');
        $employment = (string)($row['employment_type_code'] ?? '');
        $scope = (string)($row['work_scope_code'] ?? '');
        $componentName = $component === 'PREMIUM' ? '보험료' : match ($employment) {
            'REGULAR'=>'상용 가입자격',
            'DAILY'=>$scope === 'CONSTRUCTION_SITE' ? '건설 일용 가입자격' : '일반 일용 가입자격',
            default=>'가입자격',
        };
        $scopeName = match ($scope) {
            'HEAD_OFFICE'=>'본사',
            'CONSTRUCTION_SITE'=>'건설현장',
            default=>'전체',
        };
        return $typeName . ' · ' . $componentName . ' · ' . $scopeName;
    }

    public function options(): array
    {
        $sourceUploadPolicy = null;
        foreach ($this->files->listPolicies() as $policy) {
            if (($policy['policy_key'] ?? '') === self::SOURCE_UPLOAD_POLICY_KEY) {
                $sourceUploadPolicy = [
                    'policy_key' => (string) $policy['policy_key'],
                    'allowed_ext' => (string) ($policy['allowed_ext'] ?? ''),
                    'allowed_mime' => (string) ($policy['allowed_mime'] ?? ''),
                    'max_size_mb' => (int) ($policy['max_size_mb'] ?? 0),
                    'is_active' => (int) ($policy['is_active'] ?? 0) === 1,
                ];
                break;
            }
        }
        return ['success' => true, 'data' => [
            'standardTypes' => $this->templates->all(),
            'roundingMethods' => $this->model->options('STATUTORY_ROUNDING_METHOD'),
            'policyComponents' => $this->model->options('STATUTORY_POLICY_COMPONENT'),
            'statutoryEmploymentTypes' => $this->model->options('STATUTORY_EMPLOYMENT_TYPE'),
            'statutoryWorkScopes' => $this->model->options('STATUTORY_WORK_SCOPE'),
            'periodStatuses' => StatutoryStandardPeriodStatusProjection::displayOptions(
                $this->model->options('STATUTORY_STANDARD_PERIOD_STATUS')
            ),
            'standardColumns' => $this->columnMeta->columnsForDomain('statutory-standard'),
            'sourceColumns' => $this->columnMeta->columnsForDomain('statutory-standard-source'),
            'sourceUploadPolicy' => $sourceUploadPolicy,
        ]];
    }

    public function reorder(array $changes): array
    {
        throw new \LogicException('확정된 법정기준 Revision의 순서는 변경할 수 없습니다.');
        /*
        if ($changes === []) {
            throw new \InvalidArgumentException('변경할 순서 정보가 없습니다.');
        }
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->model->reorder($changes, $this->writeActor ?? ActorHelper::user());
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return ['success' => true, 'message' => '순서가 저장되었습니다.'];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
        */
    }

    public function deleteMany(array $ids): array
    {
        return $this->loggedStandardMutation('법정기준 일괄삭제','STATUTORY_STANDARD_DELETE_MANY','delete-many',static function (): array { throw new \LogicException('확정된 법정기준 Revision은 삭제할 수 없습니다.'); });
        /*
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            throw new \InvalidArgumentException('삭제할 법정기준을 선택해 주세요.');
        }
        $paths = [];
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            foreach ($ids as $id) {
                $detail = $this->model->detail($id, true);
                if (!$detail) {
                    throw new \RuntimeException('삭제할 법정기준을 찾을 수 없습니다.');
                }
                $this->assertDeletable($detail);
                array_push($paths, ...array_values(array_filter(array_column($detail['sources'], 'file_path'))));
            }
            foreach ($ids as $id) {
                $this->model->delete($id);
            }
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            foreach (array_unique($paths) as $path) {
                $this->files->delete((string) $path);
            }
        }
        return ['success' => true, 'data' => ['deleted_count' => count($ids), 'skipped_count' => 0], 'message' => '삭제되었습니다.'];
        */
    }

    public function save(array $input, array $sourceFiles = []): array
    {
        return $this->loggedStandardMutation('법정기준 저장','STATUTORY_STANDARD_SAVE','save',fn():array=>$this->saveInternal($input,$sourceFiles));
    }

    private function saveInternal(array $input, array $sourceFiles = []): array
    {
        $id = trim((string) ($input['id'] ?? ''));
        $allowSupersessionOverlap = $id === '' && (string)($input['_allow_supersession_overlap'] ?? '') === '1';
        $type = trim((string) ($input['standard_type_code'] ?? ''));
        $component = $this->null(strtoupper(trim((string)($input['policy_component_code'] ?? ''))));
        $employmentType = $this->null(strtoupper(trim((string)($input['employment_type_code'] ?? ''))));
        $workScope = $this->null(strtoupper(trim((string)($input['work_scope_code'] ?? ''))));
        if (in_array($type, self::INSURANCE_TYPES, true)) {
            if ($component === null || $employmentType === null || $workScope === null) {
                throw new \InvalidArgumentException('보험 법정기준의 정책 구성요소·고용형태·업무 Scope는 필수입니다.');
            }
            if (!$this->model->codeExists('STATUTORY_POLICY_COMPONENT', (string)$component)
                || !$this->model->codeExists('STATUTORY_EMPLOYMENT_TYPE', (string)$employmentType)
                || !$this->model->codeExists('STATUTORY_WORK_SCOPE', (string)$workScope)) {
                throw new \InvalidArgumentException('보험 법정기준의 정책 구성요소·고용형태·업무 Scope가 올바르지 않습니다.');
            }
            $this->assertInsuranceDimensionCombination((string)$component, (string)$employmentType, (string)$workScope);
        } else {
            $component = $employmentType = $workScope = null;
        }
        $additionalDimensions = in_array($type, self::INSURANCE_TYPES, true)
            ? $this->jsonInput($input['additional_dimension_data'] ?? [])
            : [];
        ksort($additionalDimensions, SORT_STRING);
        $additionalDimensionJson = in_array($type, self::INSURANCE_TYPES, true)
            ? json_encode($additionalDimensions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $additionalDimensionKey = $additionalDimensionJson === null ? null : hash('sha256', $additionalDimensionJson);
        $from = trim((string) ($input['effective_from'] ?? ''));
        $to = $this->null((string) ($input['effective_to'] ?? ''));
        $template = $this->templates->find($type, $component, $employmentType, $workScope);
        $requirementPolicy = $this->jsonInput($input['column_requirement_policy'] ?? []);
        $this->validatePhysicalRequired($input, $this->columnMeta->columnsForDomain('statutory-standard'), [
            'standard_type_code', 'policy_component_code', 'employment_type_code', 'work_scope_code',
            'effective_from', 'effective_to', 'note',
        ], $requirementPolicy);
        if (!$this->isDate($from) || ($to !== null && !$this->isDate($to))) {
            throw new \InvalidArgumentException('날짜 형식이 올바르지 않습니다.');
        }
        if ($to !== null && $to < $from) {
            throw new \InvalidArgumentException($this->metaLabel('statutory-standard', 'effective_to') . '은 '
                . $this->metaLabel('statutory-standard', 'effective_from') . '보다 빠를 수 없습니다.');
        }
        $values = $this->jsonInput($input['value_data'] ?? []);
        if ($component === 'ELIGIBILITY') {
            $values['insurance_type_code'] = $type;
            $values['employment_type_code'] = $employmentType;
            $values['work_scope_code'] = $workScope;
        }
        $valueTemplate = $this->valueTemplate($template, $id);
        $values = $this->normalizeNullableValues($valueTemplate, $values);
        $values = $this->normalizeRateValues($valueTemplate, $values);
        $values = $this->normalizeStructuredValues($valueTemplate, $values);
        $policyTemplate = ['fields' => (array) ($valueTemplate['calculation_policy']['fields'] ?? [])];
        $policyValues = $this->normalizeRateValues($policyTemplate, (array) ($values['calculation_policy'] ?? []));
        if ($policyTemplate['fields'] === []) {
            unset($values['calculation_policy']);
        } else {
            $values['calculation_policy'] = $policyValues;
        }
        $sourceRows = $this->jsonInput($input['sources'] ?? []);
        $this->validateValues($valueTemplate, $values);
        $this->validateValues($policyTemplate, $policyValues);
        $this->validateStructuredRelations($valueTemplate, $values);
        $this->validateAttendanceStandard($type, $values);
        if (!empty($template['preserve_schema_in_value']) && $component !== 'ELIGIBILITY') {
            $values['_schema'] = [
                'version' => 1,
                'fields' => $valueTemplate['fields'],
                'calculation_policy' => ['fields' => $policyTemplate['fields']],
            ];
        }
        if ($component === 'ELIGIBILITY') {
            (new \App\Services\Institution\InsuranceEligibilityPolicyValidator())->validate($values);
        }
        $this->validateSources($sourceRows);
        $uploadedPaths = [];
        foreach ($sourceFiles as $index => $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (!isset($sourceRows[$index])) {
                throw new \InvalidArgumentException('근거자료 파일의 연결 정보가 올바르지 않습니다.');
            }
            $upload = $this->files->uploadByPolicyKey($file, self::SOURCE_UPLOAD_POLICY_KEY);
            if (empty($upload['success'])) {
                foreach ($uploadedPaths as $path) {
                    $this->files->delete($path);
                }
                $this->logger->error('법정기준 근거자료 업로드에 실패했습니다.', [
                    'event_code' => 'STATUTORY_STANDARD_SOURCE_UPLOAD_FAILED',
                    'result' => 'FAILED',
                    'service' => self::class,
                    'action' => 'statutory_standard.source_upload',
                    'error_code' => (string) ($upload['error_code'] ?? 'SOURCE_UPLOAD_FAILED'),
                ]);
                throw new \InvalidArgumentException('파일을 처리할 수 없습니다.');
            }
            $sourceRows[$index]['file_path'] = $upload['db_path'];
            $sourceRows[$index]['file_name'] = basename(str_replace('\\', '/', (string) ($file['name'] ?? '')));
            $sourceRows[$index]['file_size'] = $upload['size'] ?? ($file['size'] ?? null);
            $sourceRows[$index]['mime_type'] = $upload['mime'] ?? ($file['type'] ?? null);
            $sourceRows[$index]['_uploaded'] = true;
            $uploadedPaths[] = (string) $upload['db_path'];
        }
        $actor = $this->writeActor ?? ActorHelper::user();
        $now = date('Y-m-d H:i:s');
        $removedSourcePaths = [];
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $overlaps = $this->model->overlappingPeriods($type, $component, $employmentType, $workScope, $additionalDimensionKey, $from, $to, $id);
            if ($overlaps !== [] && !$allowSupersessionOverlap) {
                $hasOpenPeriod = false;
                foreach ($overlaps as $overlap) {
                    if ($overlap['effective_to'] === null) {
                        $hasOpenPeriod = true;
                        break;
                    }
                }
                throw new \InvalidArgumentException($id === '' && $hasOpenPeriod
                    ? "기존 현행 기준의 적용종료일을 먼저 저장해 주세요.\n종료일 확정 후 새로운 적용기간 기준을 등록할 수 있습니다."
                    : ($id === ''
                        ? "기존 법정기준의 적용기간과 중복됩니다.\n기존 기준의 적용종료일과 새 기준의 적용시작일을 확인해 주세요."
                        : '같은 종류의 적용기간이 겹칩니다.'));
            }
            $data = [
                'standard_type_code' => $type,
                'policy_component_code' => $component,
                'employment_type_code' => $employmentType,
                'work_scope_code' => $workScope,
                'additional_dimension_data' => $additionalDimensionJson,
                'additional_dimension_key' => $additionalDimensionKey,
                'effective_from' => $from,
                'effective_to' => $to,
                'value_data' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'note' => $this->null((string) ($input['note'] ?? '')),
                'updated_at' => $now,
                'updated_by' => $actor,
            ];
            if ($id === '') {
                $id = $this->model->create($data + [
                    'sort_no' => SequenceHelper::next('system_statutory_standards', 'sort_no'),
                    'created_at' => $now,
                    'created_by' => $actor,
                ]);
            } else {
                $existing = $this->model->detail($id, true);
                if (!$existing) {
                    throw new \RuntimeException('수정할 법정기준을 찾을 수 없습니다.');
                }
                throw new \InvalidArgumentException('확정된 법정기준 Revision은 직접 수정할 수 없습니다. Revision 정정을 등록해 주세요.');
                $existingSources = [];
                foreach ($existing['sources'] as $existingSource) {
                    $existingSources[(string) $existingSource['id']] = $existingSource;
                }
                $retainedPaths = [];
                foreach ($sourceRows as $source) {
                    $path = !empty($source['_uploaded'])
                        ? (string) ($source['file_path'] ?? '')
                        : (string) ($existingSources[(string) ($source['id'] ?? '')]['file_path'] ?? '');
                    if ($path !== '') {
                        $retainedPaths[] = $path;
                    }
                }
                $removedSourcePaths = array_values(array_filter(array_map(
                    static fn(array $source): string => (string) ($source['file_path'] ?? ''),
                    $existing['sources']
                ), static fn(string $path): bool => !in_array($path, $retainedPaths, true)));
                $this->model->update($id, $data);
            }
            $this->sources->replace($id, $sourceRows, $actor);
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            foreach ($uploadedPaths as $path) {
                $this->files->delete($path);
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            foreach ($removedSourcePaths as $path) {
                $this->files->delete($path);
            }
        }
        return ['success' => true, 'data' => ['id' => $id], 'message' => '저장했습니다.'];
    }

    private function assertInsuranceDimensionCombination(string $component, string $employmentType, string $workScope): void
    {
        $allowed = $component === 'PREMIUM'
            ? [['ALL', 'ALL']]
            : ($component === 'ELIGIBILITY' ? [
                ['REGULAR', 'HEAD_OFFICE'],
                ['DAILY', 'HEAD_OFFICE'],
                ['DAILY', 'CONSTRUCTION_SITE'],
            ] : []);
        if (!in_array([$employmentType, $workScope], $allowed, true)) {
            throw new \InvalidArgumentException('선택한 정책 구성요소에서 지원하지 않는 고용형태·업무 Scope 조합입니다.');
        }
    }

    public function delete(string $id): array
    {
        return $this->loggedStandardMutation('법정기준 삭제','STATUTORY_STANDARD_DELETE','delete',static function (): array { throw new \LogicException('확정된 법정기준 Revision은 삭제할 수 없습니다.'); });
        /*
        if ($id === '') {
            throw new \InvalidArgumentException('삭제할 법정기준 ID가 필요합니다.');
        }
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $detail = $this->model->detail($id, true);
            if (!$detail) {
                throw new \RuntimeException('삭제할 법정기준을 찾을 수 없습니다.');
            }
            $this->assertDeletable($detail);
            $paths = array_values(array_filter(array_column($detail['sources'], 'file_path')));
            $this->model->delete($id);
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            foreach ($paths as $path) {
                $this->files->delete((string) $path);
            }
        }
        return ['success' => true, 'message' => '영구삭제했습니다.'];
        */
    }

    private function assertDeletable(array $detail): void
    {
        $references = $this->model->attendanceReferences((string) ($detail['id'] ?? ''));
        if ((int) ($references['reference_count'] ?? 0) > 0) {
            throw new \InvalidArgumentException('근태가 참조하는 법정기준은 삭제할 수 없습니다.');
        }
        $today = date('Y-m-d');
        $from = (string) ($detail['effective_from'] ?? '');
        $to = (string) ($detail['effective_to'] ?? '');
        if ($from <= $today && ($to === '' || $to >= $today)) {
            throw new \InvalidArgumentException(
                '현재 적용 중인 법정기준은 삭제할 수 없습니다. 적용기간을 종료하거나 대체 기준을 먼저 등록해 주세요.'
            );
        }
    }

    private function assertAttendanceRevisionImmutable(array $existing, array $data, array $sourceRows): void
    {
        if (!in_array((string) ($existing['standard_type_code'] ?? ''), ['WORKING_TIME_STANDARD', 'PUBLIC_HOLIDAY_CALENDAR'], true)) {
            return;
        }
        $references = $this->model->attendanceReferences((string) $existing['id']);
        if ((int) ($references['reference_count'] ?? 0) === 0) {
            return;
        }

        $immutableChanged = (string) $existing['standard_type_code'] !== (string) $data['standard_type_code']
            || (string) $existing['effective_from'] !== (string) $data['effective_from']
            || (string) $existing['value_data'] !== (string) $data['value_data']
            || (string) ($existing['note'] ?? '') !== (string) ($data['note'] ?? '');
        $lastReferencedDate = (string) ($references['last_work_date'] ?? '');
        $newEffectiveTo = (string) ($data['effective_to'] ?? '');
        $periodInvalid = $newEffectiveTo !== '' && $lastReferencedDate !== '' && $newEffectiveTo < $lastReferencedDate;
        $existingSourceIds = array_values(array_filter(array_map(
            static fn(array $source): string => (string) ($source['id'] ?? ''),
            (array) ($existing['sources'] ?? [])
        )));
        $submittedSourceIds = array_values(array_filter(array_map(
            static fn(array $source): string => (string) ($source['id'] ?? ''),
            $sourceRows
        )));
        sort($existingSourceIds);
        sort($submittedSourceIds);
        $sourceChanged = false;
        $existingSources = [];
        foreach ((array) ($existing['sources'] ?? []) as $source) {
            $existingSources[(string) ($source['id'] ?? '')] = $source;
        }
        foreach ($sourceRows as $source) {
            $sourceId = (string) ($source['id'] ?? '');
            if (!empty($source['_uploaded']) || $sourceId === '' || !isset($existingSources[$sourceId])) {
                $sourceChanged = true;
                break;
            }
            foreach (['source_type_code', 'source_name', 'source_url', 'published_at', 'note'] as $field) {
                if (array_key_exists($field, $source)
                    && (string) ($source[$field] ?? '') !== (string) ($existingSources[$sourceId][$field] ?? '')) {
                    $sourceChanged = true;
                    break 2;
                }
            }
        }

        if ($immutableChanged || $periodInvalid || $sourceChanged || $existingSourceIds !== $submittedSourceIds) {
            throw new \InvalidArgumentException('근태가 참조하는 법정기준은 직접 수정할 수 없습니다. 기존 row를 보존하고 새 Revision/Correction을 등록해 주세요.');
        }
    }

    public function sourceFile(string $id): array
    {
        $source = $this->sources->find($id);
        if (!$source || empty($source['file_path'])) {
            throw new \RuntimeException('근거자료 파일을 찾을 수 없습니다.');
        }
        $path = $this->files->resolveAbsolute((string) $source['file_path']);
        if (!is_file($path)) {
            throw new \RuntimeException('근거자료 파일을 찾을 수 없습니다.');
        }
        return ['path' => $path, 'name' => (string) ($source['file_name'] ?: basename($path)),
            'mime' => (string) ($source['mime_type'] ?: 'application/octet-stream'), 'size' => filesize($path) ?: 0];
    }

    public function resolve(array $query): array
    {
        return ['success' => true, 'data' => (new StatutoryStandardResolver($this->db))->resolve(
            (string) ($query['standard_type_code'] ?? $query['standard_code'] ?? ''),
            (string) ($query['date'] ?? '')
        )];
    }

    private function loggedStandardMutation(string $label,string $eventCode,string $action,callable $operation): array
    {
        return $this->runLoggedOperation($this->logger,$label,$eventCode,$action,[],$operation,'info',false,
            static fn(array $result):string=>!empty($result['success'])?'SUCCESS':'BLOCKED');
    }

    private function validateValues(array $template, array $values): void
    {
        $allowed = [
            '_schema'=>true,
            'calculation_policy'=>true,
            'insurance_type_code'=>true,
            'employment_type_code'=>true,
            'work_scope_code'=>true,
        ];
        foreach ($template['fields'] as $field) {
            $key = (string) ($field['code'] ?? '');
            $path = (string)($field['value_path'] ?? $key);
            $root = explode('.', $path, 2)[0] ?? '';
            if ($root !== '') $allowed[$root] = true;
            $value = $this->pathValue($values, $path);
            if ($key !== '' && !empty($field['required']) && ($value === null || $value === '' || $value === [])) {
                throw new \InvalidArgumentException((string) ($field['name'] ?? $key) . ' 값이 필요합니다.');
            }
            if ($value === null || $value === '') {
                continue;
            }
            $type = (string) ($field['type'] ?? 'text');
            if (in_array($type, ['amount', 'rate', 'number'], true) && !is_numeric($value)) {
                throw new \InvalidArgumentException((string) ($field['name'] ?? $key) . ' 값은 숫자여야 합니다.');
            }
            if (in_array($type, ['amount', 'rate', 'number'], true) && is_numeric($value)
                && empty($field['allow_negative']) && (float) $value < 0) {
                throw new \InvalidArgumentException((string) ($field['name'] ?? $key) . ' 값은 음수일 수 없습니다.');
            }
            if (is_numeric($value) && isset($field['min']) && (float) $value < (float) $field['min']) {
                throw new \InvalidArgumentException((string) ($field['name'] ?? $key) . ' 최솟값이 올바르지 않습니다.');
            }
            if (is_numeric($value) && isset($field['max']) && (float) $value > (float) $field['max']) {
                throw new \InvalidArgumentException((string) ($field['name'] ?? $key) . ' 최댓값이 올바르지 않습니다.');
            }
            if ($type === 'json' && !is_array($value)) {
                throw new \InvalidArgumentException((string) ($field['name'] ?? $key) . ' 값은 JSON 배열 또는 객체여야 합니다.');
            }
            if (in_array($type, ['matrix', 'bracket'], true)) {
                $this->validateMatrixValue($field, $value);
            }
            if ($type === 'rounding' && !$this->model->codeExists('STATUTORY_ROUNDING_METHOD', (string) $value)) {
                throw new \InvalidArgumentException('끝수처리 방식이 올바르지 않습니다.');
            }
            if ($type === 'select' && !$this->templates->isActiveSelectValue($field, (string)$value)) {
                throw new \InvalidArgumentException((string) ($field['name'] ?? $key) . ' 선택값이 올바르지 않습니다.');
            }
            if ($type === 'boolean' && !is_bool($value)) {
                throw new \InvalidArgumentException((string) ($field['name'] ?? $key) . ' 값은 예 또는 아니오여야 합니다.');
            }
        }
        foreach (array_keys($values) as $key) {
            if (!isset($allowed[(string) $key])) {
                throw new \InvalidArgumentException((string) $key . ' 값은 선택한 적용 법정기준에서 사용하지 않습니다.');
            }
        }
    }

    private function valueTemplate(array $template, string $id): array
    {
        $currentFields = (array) ($template['fields'] ?? []);
        $currentPolicyFields = (array) ($template['calculation_policy']['fields'] ?? []);
        if (empty($template['preserve_schema_in_value'])) {
            return $template;
        }
        if ($id === '') {
            return $template;
        }
        $existing = $this->model->detail($id, true);
        $storedValues = $existing ? $this->decode((string) ($existing['value_data'] ?? '')) : [];
        $snapshotFields = $storedValues['_schema']['fields'] ?? null;
        if (!is_array($snapshotFields) || $snapshotFields === []) {
            return $template;
        }
        $snapshotPolicyFields = $storedValues['_schema']['calculation_policy']['fields'] ?? null;
        return array_replace($template, [
            'fields' => $this->mergeTemplatePresentation($snapshotFields, $currentFields),
            'calculation_policy' => ['fields' => is_array($snapshotPolicyFields)
                ? $this->mergeTemplatePresentation($snapshotPolicyFields, $currentPolicyFields)
                : $currentPolicyFields],
        ]);
    }

    private function mergeTemplatePresentation(array $snapshotFields, array $currentFields): array
    {
        $currentByCode = array_column($currentFields, null, 'code');
        return array_map(static function (array $field) use ($currentByCode): array {
            $current = $currentByCode[(string) ($field['code'] ?? '')] ?? null;
            if (!is_array($current)) return $field;
            if (isset($current['ui'])) $field['ui'] = $current['ui'];
            $currentColumns = array_column((array) ($current['columns'] ?? []), null, 'code');
            $field['columns'] = array_map(static function (array $column) use ($currentColumns): array {
                $presentation = $currentColumns[(string) ($column['code'] ?? '')] ?? null;
                if (!is_array($presentation)) return $column;
                foreach (['name', 'hidden', 'default_value'] as $key) {
                    if (array_key_exists($key, $presentation)) $column[$key] = $presentation[$key];
                }
                return $column;
            }, (array) ($field['columns'] ?? []));
            return $field;
        }, $snapshotFields);
    }

    private function normalizeStructuredValues(array $template, array $values): array
    {
        foreach ((array) ($template['fields'] ?? []) as $field) {
            $key = (string) ($field['code'] ?? '');
            if (!in_array(($field['type'] ?? ''), ['matrix', 'bracket'], true)
                || !isset($values[$key]) || !is_array($values[$key])) continue;
            $columns = (array) ($field['columns'] ?? []);
            $objectStorage = (array) ($field['object_storage'] ?? []);
            $dimension = (array) ($field['dynamic_dimension'] ?? []);
            $rowsKey = (string) ($objectStorage['rows_key'] ?? 'rows');
            $matrixValue = $values[$key];
            $rows = $objectStorage ? (array) ($matrixValue[$rowsKey] ?? []) : $matrixValue;
            if ($dimension) {
                $dimensionKey = (string) ($dimension['key'] ?? 'dimensions');
                $dimensionValues = array_values(array_unique(array_map('strval', (array) ($matrixValue[$dimensionKey] ?? []))));
                $mapColumn = (array) ($dimension['column'] ?? []);
                $mapKey = (string) ($dimension['row_map_key'] ?? 'values');
                foreach ($dimensionValues as $dimensionValue) {
                    $columns[] = $mapColumn + ['code' => $mapKey . '.' . $dimensionValue,
                        'name' => str_replace('{value}', $dimensionValue, (string) ($mapColumn['name_pattern'] ?? $dimensionValue))];
                }
            }
            $normalizedRows = array_map(static function (array $row) use ($columns): array {
                $normalized = [];
                foreach ($columns as $column) {
                    $columnKey = (string) ($column['code'] ?? '');
                    if (str_contains($columnKey, '.')) {
                        [$mapKey, $mapValue] = explode('.', $columnKey, 2);
                        $value = $row[$mapKey][$mapValue] ?? ($column['default_value'] ?? '');
                    } else {
                        $value = $row[$columnKey] ?? ($column['default_value'] ?? '');
                    }
                    if ((!empty($column['dash_as_zero']) || !empty($column['blank_as_zero'])) && trim((string) $value) === '-') {
                        $value = 0;
                    } elseif (!empty($column['blank_as_zero']) && trim((string) $value) === '') {
                        $value = 0;
                    } elseif (!empty($column['nullable']) && trim((string) $value) === '') {
                        $value = null;
                    }
                    if (in_array($column['type'] ?? '', ['amount', 'rate', 'number'], true) && $value !== '' && is_numeric($value)) {
                        $value = (float) $value;
                    }
                    if (str_contains($columnKey, '.')) {
                        [$mapKey, $mapValue] = explode('.', $columnKey, 2);
                        $normalized[$mapKey][$mapValue] = $value;
                    } else {
                        $normalized[$columnKey] = $value;
                    }
                }
                return $normalized;
            }, array_values(array_filter($rows, 'is_array')));
            $sortColumns = array_values(array_filter($columns, static fn(array $column): bool => isset($column['sort_order'])));
            usort($sortColumns, static fn(array $a, array $b): int => (int) $a['sort_order'] <=> (int) $b['sort_order']);
            if ($sortColumns) usort($normalizedRows, static function (array $a, array $b) use ($sortColumns): int {
                foreach ($sortColumns as $column) {
                    $code = (string) $column['code'];
                    $comparison = ($a[$code] ?? '') <=> ($b[$code] ?? '');
                    if ($comparison !== 0) return $comparison;
                }
                return 0;
            });
            if ($objectStorage) {
                $matrixValue[$rowsKey] = $normalizedRows;
                $values[$key] = $matrixValue;
            } else {
                $values[$key] = $normalizedRows;
            }
        }
        return $values;
    }

    private function validateMatrixValue(array $field, mixed $value): void
    {
        if (!is_array($value)) throw new \InvalidArgumentException((string) ($field['name'] ?? '표') . ' 값은 표 형식이어야 합니다.');
        $columns = (array) ($field['columns'] ?? []);
        $objectStorage = (array) ($field['object_storage'] ?? []);
        $dimension = (array) ($field['dynamic_dimension'] ?? []);
        if ($objectStorage) {
            $rowsKey = (string) ($objectStorage['rows_key'] ?? 'rows');
            $dimensionKey = (string) ($dimension['key'] ?? 'dimensions');
            $dimensionValues = array_values(array_unique(array_map('strval', (array) ($value[$dimensionKey] ?? []))));
            if ($dimension && $dimensionValues === []) {
                throw new \InvalidArgumentException((string) ($dimension['name'] ?? '동적 열') . ' 값이 필요합니다.');
            }
            $mapColumn = (array) ($dimension['column'] ?? []);
            $mapKey = (string) ($dimension['row_map_key'] ?? 'values');
            foreach ($dimensionValues as $dimensionValue) {
                $columns[] = $mapColumn + ['code' => $mapKey . '.' . $dimensionValue,
                    'name' => str_replace('{value}', $dimensionValue, (string) ($mapColumn['name_pattern'] ?? $dimensionValue))];
            }
            $value = (array) ($value[$rowsKey] ?? []);
        }
        if (!empty($field['required']) && $value === []) {
            throw new \InvalidArgumentException((string) ($field['name'] ?? '표') . '은(는) 최소 1행 이상 필요합니다.');
        }
        $from = null; $to = null; $groupColumns = [];
        foreach ($columns as $column) {
            if (($column['range_role'] ?? '') === 'from') $from = $column;
            if (($column['range_role'] ?? '') === 'to') $to = $column;
            if (!empty($column['group_key'])) $groupColumns[] = $column;
        }
        $seen = []; $lastEnds = [];
        foreach ($value as $index => $row) {
            if (!is_array($row)) throw new \InvalidArgumentException(($index + 1) . '행 표 데이터가 올바르지 않습니다.');
            foreach ($columns as $column) {
                $code = (string) ($column['code'] ?? '');
                if (str_contains($code, '.')) {
                    [$mapKey, $mapValue] = explode('.', $code, 2);
                    $cell = $row[$mapKey][$mapValue] ?? '';
                } else {
                    $cell = $row[$code] ?? '';
                }
                if (!empty($column['required']) && ($cell === '' || $cell === null)) {
                    throw new \InvalidArgumentException(($index + 1) . '행 ' . ($column['name'] ?? $code) . ' 값이 필요합니다.');
                }
                if ($cell !== '' && in_array($column['type'] ?? '', ['amount', 'rate', 'number'], true) && !is_numeric($cell)) {
                    throw new \InvalidArgumentException(($index + 1) . '행 ' . ($column['name'] ?? $code) . ' 값은 숫자여야 합니다.');
                }
                if ($cell !== '' && $cell !== null && in_array($column['type'] ?? '', ['amount', 'rate', 'number'], true)
                    && empty($column['allow_negative']) && (float) $cell < 0) {
                    throw new \InvalidArgumentException(($index + 1) . '행 ' . ($column['name'] ?? $code) . ' 값은 음수일 수 없습니다.');
                }
                if ($cell !== '' && $cell !== null && isset($column['min']) && (float) $cell < (float) $column['min']) {
                    throw new \InvalidArgumentException(($index + 1) . '행 ' . ($column['name'] ?? $code) . ' 최솟값이 올바르지 않습니다.');
                }
                if ($cell !== '' && $cell !== null && isset($column['max']) && (float) $cell > (float) $column['max']) {
                    throw new \InvalidArgumentException(($index + 1) . '행 ' . ($column['name'] ?? $code) . ' 최댓값이 올바르지 않습니다.');
                }
                if (($column['type'] ?? '') === 'select' && $cell !== ''
                    && !$this->templates->isActiveSelectValue($column, (string)$cell)) {
                    throw new \InvalidArgumentException(($index + 1) . '행 ' . ($column['name'] ?? $code) . ' 선택값이 올바르지 않습니다.');
                }
            }
            $key = json_encode(array_map(static fn(array $column): mixed => $row[$column['code']] ?? '', array_filter($columns, static fn(array $column): bool => !empty($column['key_part']))));
            if (isset($seen[$key])) throw new \InvalidArgumentException(($index + 1) . '행 기준 구간이 중복되었습니다.');
            $seen[$key] = true;
            if ($from && $to && ($row[$to['code']] ?? null) !== null && ($row[$to['code']] ?? '') !== ''
                && (empty($from['allow_equal_to'])
                    ? (float) $row[$from['code']] >= (float) $row[$to['code']]
                    : (float) $row[$from['code']] > (float) $row[$to['code']])) {
                throw new \InvalidArgumentException(($index + 1) . '행 구간 시작값은 종료값보다 작아야 합니다.');
            }
            if ($from && $to) {
                $group = json_encode(array_map(static fn(array $column): mixed => $row[$column['code']] ?? '', $groupColumns));
                if (isset($lastEnds[$group]) && (float) $row[$from['code']] < $lastEnds[$group]) {
                    throw new \InvalidArgumentException(($index + 1) . '행 급여 구간이 앞 행과 겹칩니다.');
                }
                if (!empty($field['ui']['strict_contiguous']) && isset($lastEnds[$group])
                    && (float) $row[$from['code']] !== $lastEnds[$group]) {
                    throw new \InvalidArgumentException(($index + 1) . '행의 구간 시작금액은 이전 구간 종료금액과 같아야 합니다.');
                }
                if (($row[$to['code']] ?? null) === null && $index !== count($value) - 1) {
                    throw new \InvalidArgumentException(($index + 1) . '행의 종료값 없음은 마지막 구간에서만 허용됩니다.');
                }
                if (($row[$to['code']] ?? null) !== null && ($row[$to['code']] ?? '') !== '') {
                    $lastEnds[$group] = (float) $row[$to['code']];
                }
            }
        }
    }

    private function validateStructuredRelations(array $template, array $values): void
    {
        foreach ((array) ($template['fields'] ?? []) as $field) {
            $connection = $field['connects_after'] ?? null;
            if (!is_array($connection)) continue;
            $sourceRows = $values[(string) ($connection['field'] ?? '')] ?? [];
            $sourceRowsKey = (string) ($connection['rows_key'] ?? '');
            if ($sourceRowsKey !== '' && is_array($sourceRows)) $sourceRows = $sourceRows[$sourceRowsKey] ?? [];
            $ruleRows = $values[(string) ($field['code'] ?? '')] ?? [];
            if (!is_array($sourceRows) || !is_array($ruleRows) || $sourceRows === [] || $ruleRows === []) continue;
            $sourceColumn = (string) ($connection['column'] ?? '');
            $ruleColumn = (string) ($connection['rule_column'] ?? '');
            $sourceEnds = array_values(array_filter(array_column($sourceRows, $sourceColumn), static fn(mixed $value): bool => $value !== null && $value !== ''));
            if ($sourceEnds === [] || (float) max($sourceEnds) !== (float) ($ruleRows[0][$ruleColumn] ?? -1)) {
                throw new \InvalidArgumentException((string) ($field['name'] ?? '구조화 규칙') . '의 첫 구간은 표의 최종 상한과 연결되어야 합니다.');
            }
        }
    }

    private function normalizeRateValues(array $template, array $values): array
    {
        foreach ((array) ($template['fields'] ?? []) as $field) {
            if (!is_array($field) || ($field['type'] ?? '') !== 'rate') {
                continue;
            }
            $key = (string)($field['value_path'] ?? $field['code'] ?? '');
            $value = $this->pathValue($values, $key);
            if ($key === '' || $value === null || $value === '' || !is_numeric($value)) {
                continue;
            }
            $this->setPathValue($values, $key, round((float)$value, 12));
        }
        return $values;
    }

    private function normalizeNullableValues(array $template, array $values): array
    {
        foreach ((array)($template['fields'] ?? []) as $field) {
            if (empty($field['nullable'])) continue;
            $path = (string)($field['value_path'] ?? $field['code'] ?? '');
            if ($path !== '' && $this->pathValue($values, $path) === '') $this->setPathValue($values, $path, null);
        }
        return $values;
    }

    private function pathValue(array $values, string $path): mixed
    {
        $current = $values;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) return null;
            $current = $current[$segment];
        }
        return $current;
    }

    private function setPathValue(array &$values, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current =& $values;
        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $current[$segment] = $value;
                return;
            }
            if (!isset($current[$segment]) || !is_array($current[$segment])) $current[$segment] = [];
            $current =& $current[$segment];
        }
    }

    private function validateSources(array $sources): void
    {
        $metadata = $this->columnMeta->columnsForDomain('statutory-standard-source');
        foreach ($sources as $source) {
            $this->validatePhysicalRequired($source, $metadata, [
                'organization_name', 'law_name', 'notice_no', 'source_name', 'source_url', 'published_at', 'note',
            ]);
            $publishedAt = trim((string) ($source['published_at'] ?? ''));
            if ($publishedAt !== '' && !$this->isDate($publishedAt)) {
                throw new \InvalidArgumentException($this->metaLabel('statutory-standard-source', 'published_at') . ' 형식이 올바르지 않습니다.');
            }
            $url = trim((string) ($source['source_url'] ?? ''));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new \InvalidArgumentException($this->metaLabel('statutory-standard-source', 'source_url') . ' 형식이 올바르지 않습니다.');
            }
        }
    }

    private function validatePhysicalRequired(array $input, array $metadata, array $inputColumns, array $requirementPolicy = []): void
    {
        $allowed = array_fill_keys($inputColumns, true);
        foreach ($metadata as $column) {
            $key = (string) ($column['key'] ?? '');
            $required = !empty($column['required']) || (($requirementPolicy[$key] ?? '') === 'required');
            if ($key === '' || !isset($allowed[$key]) || !$required) {
                continue;
            }
            $value = $input[$key] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                throw new \InvalidArgumentException((string) ($column['label'] ?? $key) . ' 항목은 필수입니다.');
            }
        }
    }

    private function metaLabel(string $domain, string $key): string
    {
        foreach ($this->columnMeta->columnsForDomain($domain) as $column) {
            if (($column['key'] ?? '') === $key) {
                return (string) ($column['label'] ?? $key);
            }
        }
        return $key;
    }

    private function jsonInput(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('입력 데이터 형식이 올바르지 않습니다.');
        }
        return $decoded;
    }

    private function decode(string $value): array
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function null(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validateAttendanceStandard(string $type, array $values): void
    {
        if ($type === 'WORKING_TIME_STANDARD') {
            foreach (['daily_legal_work_seconds','weekly_legal_work_seconds'] as $field) if ((int)($values[$field]??0)<=0) throw new \InvalidArgumentException('법정 근로시간은 0보다 큰 초 단위 값이어야 합니다.');
            if (!in_array((int)($values['week_start_day']??0),range(1,7),true)) throw new \InvalidArgumentException('주 시작요일을 확인해 주세요.');
            foreach (['night_start_time','night_end_time'] as $field) if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',(string)($values[$field]??''))) throw new \InvalidArgumentException('야간근로 시작·종료시각을 HH:MM 형식으로 입력해 주세요.');
        }
        if ($type === 'PUBLIC_HOLIDAY_CALENDAR') {
            $dates=[];foreach((array)($values['holidays']??[]) as $holiday){$date=(string)($holiday['date']??'');if(!$this->isDate($date))throw new \InvalidArgumentException('공휴일 날짜를 YYYY-MM-DD 형식으로 입력해 주세요.');if(isset($dates[$date]))throw new \InvalidArgumentException('같은 날짜의 공휴일을 중복 등록할 수 없습니다.');if(!in_array((string)($holiday['holiday_type']??''),['PUBLIC_HOLIDAY','SUBSTITUTE_PUBLIC_HOLIDAY'],true))throw new \InvalidArgumentException('공휴일 유형을 확인해 주세요.');$dates[$date]=true;}
        }
    }
}
