<?php
namespace App\Services\System;

use App\Models\System\WorkTeamModel;
use App\Models\System\ClientModel;
use App\Repositories\System\WorkTeamDependencyRepository;
use Core\Helpers\ActorHelper;
use Core\Helpers\ExcelTemplateFilenameHelper;
use Core\Helpers\ExcelValueFormatterHelper;
use Core\Helpers\ColumnPolicyRequestHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;

class WorkTeamService
{
    private const COLUMN_DEFINITIONS = [
        ['key' => 'sort_no', 'label' => '순번', 'required' => false, 'template_default' => false, 'download_default' => true, 'allow_upload' => false],
        ['key' => 'team_name', 'label' => '팀명', 'required' => true, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'team_leader_client_name', 'label' => '팀장', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true, 'source_key' => 'team_leader_client_name'],
        ['key' => 'team_leader_client_id', 'label' => '팀장 거래처 ID', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => true, 'source_key' => 'team_leader_client_id'],
        ['key' => 'note', 'label' => '비고', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'memo', 'label' => '메모', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'is_active', 'label' => '상태', 'required' => false, 'template_default' => true, 'download_default' => true, 'allow_upload' => true],
        ['key' => 'created_at', 'label' => '등록일시', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'created_by_name', 'label' => '등록자', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'updated_at', 'label' => '수정일시', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'updated_by_name', 'label' => '수정자', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'deleted_at', 'label' => '삭제일시', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
        ['key' => 'deleted_by_name', 'label' => '삭제자', 'required' => false, 'template_default' => false, 'download_default' => false, 'allow_upload' => false],
    ];

    private const SAMPLE_ROW = [
        'team_name' => '시공팀',
        'team_leader_client_name' => '홍길동 거래처',
        'team_leader_client_id' => 'client-sample-id',
        'note' => '현장 작업팀',
        'memo' => '관리자 메모',
        'is_active' => '사용',
    ];

    private readonly PDO $pdo;
    private WorkTeamModel $model;
    private ClientModel $clientModel;
    private WorkTeamDependencyRepository $dependencyRepository;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new WorkTeamModel($pdo);
        $this->clientModel = new ClientModel($pdo);
        $this->dependencyRepository = new WorkTeamDependencyRepository($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.WorkTeamService');
    }

    public function getList(array $filters = []): array
    {
        try {
            return $this->model->getList($filters);
        } catch (\Throwable $e) {
            $this->logger->error('getList() failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getById(string $id): ?array
    {
        try {
            return $this->model->getById($id);
        } catch (\Throwable $e) {
            $this->logger->error('getById() failed', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function save(array $data, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            $this->pdo->beginTransaction();

            $id = trim((string)($data['id'] ?? ''));
            $data = $this->normalize($data);
            $validationMessage = $this->validateSaveData($data);
            if ($validationMessage !== '') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => $validationMessage];
            }

            if ($id !== '') {
                $before = $this->model->getById($id);
                if (!$before) {
                    throw new \Exception('작업팀을 찾을 수 없습니다.');
                }
                $this->assertTeamLeader($data, $before);

                $data['updated_by'] = $actor;
                $data['sort_no'] = (int)($before['sort_no'] ?? 0);
                unset($data['id']);

                if (!$this->model->updateById($id, $data)) {
                    throw new \Exception('작업팀 수정에 실패했습니다.');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $id,
                    'sort_no' => $data['sort_no'] ?? ($before['sort_no'] ?? null),
                ];
            }

            $newId = UuidHelper::generate();
            $this->assertTeamLeader($data, null);
            $newSortNo = SequenceHelper::next('system_work_teams', 'sort_no');

            $insertData = array_merge($data, [
                'id' => $newId,
                'sort_no' => $newSortNo,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('작업팀 등록에 실패했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $newId,
                'sort_no' => $newSortNo,
            ];
        } catch (\DomainException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('save() failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다.',
            ];
        }
    }

    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            if (!$this->model->getById($id)) {
                return ['success' => false, 'message' => '작업팀을 찾을 수 없습니다.'];
            }

            return ['success' => $this->model->deleteById($id, $actor)];
        } catch (\Throwable $e) {
            $this->logger->error('delete() failed', ['id' => $id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => '삭제 중 오류가 발생했습니다.'];
        }
    }

    public function getTrashList(): array
    {
        try {
            return $this->model->getDeleted();
        } catch (\Throwable $e) {
            $this->logger->error('getTrashList() failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            return ['success' => $this->model->restoreById($id, $actor)];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
        $count = 0;
        try {
            $this->pdo->beginTransaction();
            foreach ($ids as $id) {
                if ($this->model->restoreById((string)$id, $actor)) $count++;
            }
            $this->pdo->commit();
            return ['success' => true, 'message' => "복원 완료 ({$count}건)"];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->logger->error('restoreBulk() failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => '복구 중 오류가 발생했습니다.'];
        }
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        return $this->restoreBulk(array_column($this->model->getDeleted(), 'id'), $actorType);
    }

    public function purge(string $id): array
    {
        return $this->purgeTeams([$id]);
    }

    public function purgeBulk(array $ids): array
    {
        return $this->purgeTeams($ids);
    }

    public function purgeAll(): array
    {
        return $this->purgeBulk(array_column($this->model->getDeleted(), 'id'));
    }

    private function purgeTeams(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn($id) => trim((string) $id), $ids))));
        $deleted = 0;
        $blocked = [];
        $deletable = [];
        foreach ($ids as $id) {
            $item = $this->model->getById($id);
            if (!$item || empty($item['deleted_at'])) continue;
            $references = $this->dependencyRepository->findReferences($id);
            if ($references !== []) {
                $blocked[] = ['id' => $id, 'name' => (string) ($item['team_name'] ?? $id), 'references' => $references];
                continue;
            }
            $deletable[] = $id;
        }

        try {
            $this->pdo->beginTransaction();
            foreach ($deletable as $id) {
                if (!$this->model->hardDeleteById($id)) throw new \RuntimeException('작업팀 영구삭제 DB 처리 실패');
                $deleted++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->logger->error('purgeTeams() failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => '영구삭제 중 오류가 발생했습니다.'];
        }

        $skipped = count($blocked);
        $message = $skipped > 0
            ? ($deleted > 0 ? "작업팀 {$deleted}건을 영구삭제했고, 사용 중인 {$skipped}건은 유지했습니다." : '다른 업무에서 사용 중인 작업팀은 영구삭제할 수 없습니다.')
            : ($deleted > 0 ? "작업팀 {$deleted}건을 영구삭제했습니다." : '영구삭제할 작업팀이 없습니다.');
        $data = ['deleted_count' => $deleted, 'skipped_count' => $skipped, 'blocked' => $blocked];
        return ['success' => true, 'message' => $message] + $data + ['data' => $data];
    }

    public function reorder(array $changes): bool
    {
        if (empty($changes)) {
            return true;
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($changes as $row) {
                $this->model->updateSortNo((string)$row['id'], (string)((int)$row['newSortNo'] + 1000000));
            }

            foreach ($changes as $row) {
                $this->model->updateSortNo((string)$row['id'], (string)(int)$row['newSortNo']);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function normalize(array $data): array
    {
        $data['team_name'] = trim((string)($data['team_name'] ?? ''));
        $data['team_leader_client_id'] = $this->blankToNull($data['team_leader_client_id'] ?? null);
        $data['note'] = $this->blankToNull($data['note'] ?? null);
        $data['memo'] = $this->blankToNull($data['memo'] ?? null);
        $activeValue = trim((string) ($data['is_active'] ?? '1'));
        $data['is_active'] = in_array($activeValue, ['0', '1'], true) ? (int) $activeValue : -1;
        return $data;
    }

    private function validateSaveData(array $data): string
    {
        $teamName = (string) ($data['team_name'] ?? '');
        if ($teamName === '') return '팀명은 필수입니다.';
        if (mb_strlen($teamName, 'UTF-8') > 100) return '팀명은 100자 이내로 입력하세요.';
        if (mb_strlen((string) ($data['note'] ?? ''), 'UTF-8') > 255) return '비고는 255자 이내로 입력하세요.';
        if (mb_strlen((string) ($data['memo'] ?? ''), 'UTF-8') > 65535) return '메모가 허용 길이를 초과했습니다.';
        if (!in_array((int) ($data['is_active'] ?? -1), [0, 1], true)) return '사용 상태 값이 올바르지 않습니다.';
        return '';
    }

    private function assertTeamLeader(array $data, ?array $before): void
    {
        $clientId = trim((string) ($data['team_leader_client_id'] ?? ''));
        if ($clientId === '') return;
        if ($before && $clientId === trim((string) ($before['team_leader_client_id'] ?? ''))) return;
        $client = $this->clientModel->getById($clientId);
        if (!$client || !empty($client['deleted_at']) || (int) ($client['is_active'] ?? 1) !== 1) {
            throw new \DomainException('사용 가능한 거래처만 팀장으로 지정할 수 있습니다.');
        }
    }

    public function downloadTemplate(?string $columnsCsv = null): void
    {
        $columns = $this->resolveColumns('template', $columnsCsv);
        $headers = $this->buildHeaders($columns);
        $rows = [$this->buildTemplateSampleRow($columns)];

        $this->writeSpreadsheet($headers, $rows, '작업팀 업로드', 'work-team-template.xlsx', $columns, true);
    }

    public function saveFromExcelFile(string $filePath, ?string $columnsCsv = null): array
    {
        try {
            $columns = $this->resolveColumns('template', $columnsCsv);
            $spreadsheet = IOFactory::load($filePath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);

            if (count($rows) < 2) {
                return ['success' => false, 'message' => '업로드할 데이터가 없습니다.'];
            }

            $headerRow = array_map(fn($value) => trim((string) $value), array_shift($rows));
            $headerMap = $this->buildHeaderIndexMap($headerRow, $columns);
            $missingRequired = $this->findMissingRequiredColumns($columns, $headerMap);
            if ($missingRequired !== []) {
                return [
                    'success' => false,
                    'message' => '필수 컬럼이 누락되었습니다: ' . implode(', ', $missingRequired),
                ];
            }
            $count = 0;
            $requiredValueErrors = [];

            foreach ($rows as $index => $row) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $payload = $this->buildUploadPayload($row, $headerMap, $columns);
                $missingRequiredValues = $this->findMissingRequiredValues($payload, $columns);
                if ($missingRequiredValues !== []) {
                    $rowNo = $index + 2;
                    foreach ($missingRequiredValues as $label) {
                        $requiredValueErrors[] = sprintf('%d행 : %s 필수', $rowNo, $label);
                    }
                    continue;
                }

                if (($payload['team_name'] ?? '') === '') {
                    continue;
                }

                $result = $this->save($payload, 'SYSTEM');
                if (!empty($result['success'])) {
                    $count++;
                } else {
                    $requiredValueErrors[] = sprintf('%d행 : %s', $index + 2, (string) ($result['message'] ?? '저장 중 오류가 발생했습니다.'));
                }
            }

            $spreadsheet->disconnectWorksheets();
            if ($requiredValueErrors !== []) {
                return [
                    'success' => false,
                    'message' => "업로드할 수 없습니다.\n\n" . implode("\n", array_values(array_unique($requiredValueErrors))),
                ];
            }

            return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function downloadExcel(?string $columnsCsv = null): void
    {
        $columns = $this->resolveColumns('download', $columnsCsv);
        $rows = ExcelValueFormatterHelper::sortRowsBySortNo($this->model->getList());
        $downloadRows = [];

        foreach ($rows as $row) {
            $downloadRows[] = $this->buildDownloadRow($row, $columns);
        }

        $this->writeSpreadsheet(
            $this->buildHeaders($columns),
            $downloadRows,
            '작업팀 목록',
            'work-team-list.xlsx',
            $columns
        );
    }

    private function resolveColumns(string $type, ?string $columnsCsv = null): array
    {
        $columnsByKey = [];
        foreach (self::COLUMN_DEFINITIONS as $column) {
            $columnsByKey[$column['key']] = $column;
        }

        $requestedKeys = $this->parseColumnsCsv($columnsCsv);
        $selectedKeys = [];

        if ($requestedKeys === []) {
            foreach (self::COLUMN_DEFINITIONS as $column) {
                if ($column['required'] || $column[$type . '_default']) {
                    $selectedKeys[] = $column['key'];
                }
            }
        } else {
            foreach ($requestedKeys as $key) {
                if (isset($columnsByKey[$key])) {
                    $selectedKeys[] = $key;
                }
            }
        }

        if ($selectedKeys === []) {
            return $this->resolveColumns($type, '');
        }

        $selectedColumns = [];
        foreach ($selectedKeys as $key) {
            $selectedColumns[] = $columnsByKey[$key];
        }

        return $this->decorateColumns($selectedColumns);
    }

    private function parseColumnsCsv(?string $columnsCsv): array
    {
        $resolved = trim((string) $columnsCsv);
        if ($resolved === '') {
            return [];
        }

        $keys = array_map('trim', explode(',', $resolved));
        $keys = array_values(array_filter($keys, static fn($key) => $key !== ''));

        return array_values(array_unique($keys));
    }

    private function decorateColumns(array $columns): array
    {
        $displayNameMap = ColumnPolicyRequestHelper::displayNameMap($_REQUEST['column_display_name'] ?? null);
        $requirementPolicyMap = ColumnPolicyRequestHelper::requirementPolicyMap($_REQUEST['column_requirement_policy'] ?? null);

        return array_map(static function (array $column) use ($displayNameMap, $requirementPolicyMap): array {
            $label = ColumnPolicyRequestHelper::displayNameForColumn($column, $displayNameMap, (string) ($column['label'] ?? ''));
            $policy = ColumnPolicyRequestHelper::requirementPolicyForColumn(
                $column,
                $requirementPolicyMap,
                !empty($column['required']) ? 'required' : 'none'
            );
            return $column + [
                'label' => $label,
                'required' => $policy === 'required',
                'requirement_policy' => $policy,
                'header' => $label,
                'source_key' => $column['source_key'] ?? $column['key'],
                'payload_key' => $column['payload_key'] ?? $column['key'],
            ];
        }, $columns);
    }

    private function buildHeaders(array $columns): array
    {
        return array_map(static fn(array $column): string => $column['header'], $columns);
    }

    private function buildTemplateSampleRow(array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $row[] = self::SAMPLE_ROW[$column['key']] ?? '';
        }

        return $row;
    }

    private function buildDownloadRow(array $record, array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $row[] = $this->exportCellValue($record, $column);
        }

        return $row;
    }

    private function exportCellValue(array $record, array $column): mixed
    {
        $sourceKey = $column['source_key'];
        $value = $record[$sourceKey] ?? $record[$column['key']] ?? '';

        if ($column['key'] === 'is_active') {
            return (string) ($value ?? '1') === '1' ? '사용' : '미사용';
        }

        return $value;
    }

    private function buildHeaderIndexMap(array $headerRow, array $columns): array
    {
        $lookup = [];
        foreach ($columns as $column) {
            $lookup[$column['header']] = $column['key'];
            $lookup[$column['label']] = $column['key'];
            if (($column['requirement_policy'] ?? 'none') !== 'none') {
                $lookup[$column['header'] . ' *'] = $column['key'];
                $lookup[$column['label'] . ' *'] = $column['key'];
            }
            $lookup[$column['key']] = $column['key'];
        }

        $indexMap = [];
        foreach ($headerRow as $index => $header) {
            $trimmed = trim((string) $header);
            if ($trimmed === '' || !isset($lookup[$trimmed])) {
                continue;
            }

            $key = $lookup[$trimmed];
            if (!array_key_exists($key, $indexMap)) {
                $indexMap[$key] = $index;
            }
        }

        return $indexMap;
    }

    private function findMissingRequiredColumns(array $columns, array $headerMap): array
    {
        $missing = [];

        foreach ($columns as $column) {
            if ($column['required'] && !array_key_exists($column['key'], $headerMap)) {
                $missing[] = $column['label'];
            }
        }

        return $missing;
    }

    private function findMissingRequiredValues(array $payload, array $columns): array
    {
        $missing = [];

        foreach ($columns as $column) {
            if (empty($column['required'])) {
                continue;
            }

            $payloadKey = (string) ($column['payload_key'] ?? $column['key'] ?? '');
            if ($payloadKey === '') {
                continue;
            }

            $value = $payload[$payloadKey] ?? null;
            if (is_array($value)) {
                if ($value === []) {
                    $missing[] = $column['label'];
                }
                continue;
            }

            if (trim((string) $value) === '') {
                $missing[] = $column['label'];
            }
        }

        return $missing;
    }

    private function buildUploadPayload(array $row, array $headerMap, array $columns): array
    {
        $payload = [];

        foreach ($columns as $column) {
            if (empty($column['allow_upload']) || !array_key_exists($column['key'], $headerMap)) {
                continue;
            }

            $rawValue = trim((string) ($row[$headerMap[$column['key']]] ?? ''));
            $payload[$column['payload_key']] = $rawValue;
        }

        $teamLeaderLookup = trim((string) ($payload['team_leader_client_id'] ?? ''));
        if ($teamLeaderLookup === '') {
            $teamLeaderLookup = trim((string) ($payload['team_leader_client_name'] ?? ''));
        }

        $payload['team_leader_client_id'] = $this->resolveTeamLeaderClientId($teamLeaderLookup);
        unset($payload['team_leader_client_name']);

        $payload['team_name'] = trim((string) ($payload['team_name'] ?? ''));
        $payload['note'] = trim((string) ($payload['note'] ?? ''));
        $payload['memo'] = trim((string) ($payload['memo'] ?? ''));
        $payload['is_active'] = $this->parseActiveValue($payload['is_active'] ?? '1');

        return $payload;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function writeSpreadsheet(
        array $headers,
        array $rows,
        string $title,
        string $filename,
        array $columns = [],
        bool $showRequiredAsterisk = false
    ): void
    {
        $filename = ExcelTemplateFilenameHelper::normalize($filename, 'work_team');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        ExcelValueFormatterHelper::writeTable($sheet, $headers, $rows, 'A1', $columns, [
            'showRequiredAsterisk' => $showRequiredAsterisk,
        ]);
        if ($showRequiredAsterisk) {
            $this->applyTemplateDropdowns($spreadsheet, $sheet, $columns);
        }

        for ($index = 1; $index <= count($headers); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    private function applyTemplateDropdowns(Spreadsheet $spreadsheet, Worksheet $sheet, array $columns): void
    {
        $dropdownOptions = [
            'team_leader_client_name' => $this->tableColumnDropdownOptions('system_clients', 'client_name'),
            'team_leader_client_id' => $this->tableColumnDropdownOptions('system_clients', 'id'),
            'is_active' => ['사용', '미사용'],
        ];

        $targets = [];
        foreach (array_values($columns) as $index => $column) {
            $key = trim((string) ($column['key'] ?? ''));
            $options = $dropdownOptions[$key] ?? [];
            if ($key === '' || $options === []) {
                continue;
            }

            $targets[] = [
                'columnIndex' => $index + 1,
                'options' => $options,
            ];
        }

        if ($targets === []) {
            return;
        }

        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle('_work_team_refs');

        foreach ($targets as $listIndex => $target) {
            $listColumn = Coordinate::stringFromColumnIndex($listIndex + 1);
            foreach (array_values($target['options']) as $rowIndex => $option) {
                $referenceSheet->setCellValue($listColumn . ($rowIndex + 1), $option);
            }

            $this->applyListValidation(
                $sheet,
                Coordinate::stringFromColumnIndex($target['columnIndex']),
                "'_work_team_refs'!$" . $listColumn . '$1:$' . $listColumn . '$' . count($target['options'])
            );
        }

        $referenceSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    private function applyListValidation(Worksheet $sheet, string $column, string $formula): void
    {
        $range = "{$column}2:{$column}1048576";
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('목록 선택 오류');
        $validation->setError('목록에 있는 값만 선택할 수 있습니다.');
        $validation->setFormula1($formula);
        $validation->setSqref($range);
        $sheet->setDataValidation($range, $validation);
    }

    private function tableColumnDropdownOptions(string $table, string $column): array
    {
        return $table === 'system_clients' ? $this->clientModel->getActiveDropdownValues($column) : [];
    }

    private function parseActiveValue(mixed $value): int
    {
        $normalized = mb_strtolower(trim((string)$value), 'UTF-8');
        return in_array($normalized, ['1', 'true', 'yes', 'y', 'use', 'active', '사용'], true) ? 1 : 0;
    }

    private function resolveTeamLeaderClientId(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $id = $this->clientModel->resolveActiveIdByIdOrName($value);
        if ($id === null) {
            throw new \DomainException('사용 가능한 거래처만 팀장으로 지정할 수 있습니다.');
        }
        return $id;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
