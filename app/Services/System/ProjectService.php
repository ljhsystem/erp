<?php
namespace App\Services\System;

use PDO;
use App\Models\System\ProjectModel;
use App\Models\System\ClientModel;
use App\Models\User\EmployeeModel;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ProjectService
{
    private readonly PDO $pdo;
    private ProjectModel $model;
    private ClientModel $clientModel;
    private EmployeeModel $employeeModel;
    private ProjectPayloadService $payloadService;
    private ProjectReferenceResolver $referenceResolver;
    private ProjectTrashService $trashService;
    private ProjectExcelService $excelService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->logger = LoggerFactory::getLogger('service-system.ProjectService');
        $this->model  = new ProjectModel($this->pdo);
        $this->clientModel  = new ClientModel($this->pdo);
        $this->employeeModel  = new EmployeeModel($this->pdo);
        $this->payloadService = new ProjectPayloadService();
        $this->referenceResolver = new ProjectReferenceResolver($this->pdo);
        $this->excelService = new ProjectExcelService($this->pdo, $this->model);
        $this->trashService = new ProjectTrashService($this->pdo, $this->model, $this->logger);
        $this->logger->info('ProjectService initialized');
    }

    public function getList(array $filters = []): array
    {
        $this->logger->info('getList() called', [
            'filters' => $filters
        ]);

        try {

            $rows = $this->model->getList($filters);

            $this->logger->info('getList() success', [
                'count' => count($rows)
            ]);

            return $rows;

        } catch (\Throwable $e) {

            $this->logger->error('getList() failed', [
                'filters'   => $filters,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function getById(string $id): ?array
    {
        $this->logger->info('getById() called', [
            'id' => $id
        ]);

        try {

            $row = $this->model->getById($id);

            if (!$row) {
                $this->logger->warning('getById() not found', [
                    'id' => $id
                ]);
                return null;
            }

            return $row;

        } catch (\Throwable $e) {

            $this->logger->error('getById() exception', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function searchPicker(string $keyword): array
    {
        $this->logger->info('searchPicker() called', [
            'keyword' => $keyword
        ]);

        try {

            $rows = $this->model->searchPicker($keyword, 20);

            if (empty($rows)) {
                return [];
            }

            $results = [];

            foreach ($rows as $row) {

                $text = $row['project_name'] ?? '';

                if (!empty($row['construction_name'])
                    && $row['construction_name'] !== $row['project_name']) {
                    $text .= ' / ' . $row['construction_name'];
                }

                if (!empty($row['sort_no'])) {
                    $text .= ' [' . $row['sort_no'] . ']';
                }

                $results[] = [
                    'id'   => $row['id'],
                    'text' => $text
                ];
            }

            return $results;

        } catch (\Throwable $e) {

            $this->logger->error('searchPicker() failed', [
                'keyword'   => $keyword,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function distinctValues(string $field, string $keyword = '', int $limit = 20): array
    {
        return $this->model->distinctValues($field, $keyword, $limit);
    }

    public function normalizePayload(array $input): array
    {
        return $this->payloadService->normalizePayload($input);

        return [
            'id' => $input['id'] ?? null,
            'sort_no' => $input['sort_no'] ?? null,
            'project_name' => trim((string) ($input['project_name'] ?? '')),
            'client_id' => $input['client_id'] ?? null,
            'employee_id' => $input['employee_id'] ?? null,
            'site_agent' => $input['site_agent'] ?? null,
            'contract_type' => $input['contract_type'] ?? null,
            'contract_method' => $input['contract_method'] ?? null,
            'director' => $input['director'] ?? null,
            'manager' => $input['manager'] ?? null,
            'business_type' => $input['business_type'] ?? null,
            'housing_type' => $input['housing_type'] ?? null,
            'construction_name' => $input['construction_name'] ?? null,
            'site_region_city' => $input['site_region_city'] ?? null,
            'site_region_district' => $input['site_region_district'] ?? null,
            'site_region_address' => $input['site_region_address'] ?? null,
            'site_region_address_detail' => $input['site_region_address_detail'] ?? null,
            'work_type' => $input['work_type'] ?? null,
            'work_subtype' => $input['work_subtype'] ?? null,
            'work_detail_type' => $input['work_detail_type'] ?? null,
            'contract_work_type' => $input['contract_work_type'] ?? null,
            'bid_type' => $input['bid_type'] ?? null,
            'client_name' => $input['client_name'] ?? null,
            'client_type' => $input['client_type'] ?? null,
            'permit_agency' => $input['permit_agency'] ?? null,
            'permit_date' => $input['permit_date'] ?? null,
            'contract_date' => $input['contract_date'] ?? null,
            'start_date' => $input['start_date'] ?? null,
            'completion_date' => $input['completion_date'] ?? null,
            'bid_notice_date' => $input['bid_notice_date'] ?? null,
            'initial_contract_amount' => $input['initial_contract_amount'] ?? null,
            'authorized_company_seal' => $input['authorized_company_seal'] ?? null,
            'note' => $input['note'] ?? null,
            'memo' => $input['memo'] ?? null,
            'delete_project_image' => $input['delete_project_image'] ?? '0',
            'is_active' => isset($input['is_active']) ? (int) $input['is_active'] : 1,
        ];
    }

    public function validatePayload(array $payload): array
    {
        return $this->payloadService->validatePayload($payload);

        if (($payload['project_name'] ?? '') === '') {
            return ['success' => false, 'message' => '프로젝트명을 입력해 주세요.'];
        }

        foreach (['contract_date' => '계약일', 'start_date' => '착공일', 'completion_date' => '준공일'] as $field => $label) {
            if (!empty($payload[$field]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $payload[$field])) {
                return ['success' => false, 'message' => "{$label}은 YYYY-MM-DD 형식이어야 합니다."];
            }
        }

        if (!empty($payload['start_date']) && !empty($payload['completion_date']) && (string) $payload['start_date'] > (string) $payload['completion_date']) {
            return ['success' => false, 'message' => '준공일은 착공일보다 빠를 수 없습니다.'];
        }

        if (($payload['initial_contract_amount'] ?? null) !== null && ($payload['initial_contract_amount'] ?? '') !== '' && !is_numeric((string) $payload['initial_contract_amount'])) {
            return ['success' => false, 'message' => '초기 계약 금액은 숫자여야 합니다.'];
        }

        return ['success' => true];
    }

    public function saveWithFiles(array $input, array $files = [], string $actorType = 'USER'): array
    {
        try {
            $payload = $this->normalizePayload($input);
            $validated = $this->validatePayload($payload);

            if (!$validated['success']) {
                return $validated;
            }

            return $this->save($payload, $actorType, $files);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : '저장 중 오류가 발생했습니다.',
            ];
        }
    }

    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);

        $data['client_id'] = $this->normalizeNullableId($data['client_id'] ?? null);
        $data['client_name'] = trim((string) ($data['client_name'] ?? ''));
        $data['employee_id'] = $this->normalizeNullableId($data['employee_id'] ?? null);

        if ($data['client_id'] === null && $data['client_name'] !== '') {
            $data['client_id'] = $this->resolveClientIdByName($data['client_name']);
        }

            $this->logger->info('save() called', [
                'mode'      => !empty($data['id']) ? 'UPDATE' : 'INSERT',
                'id'        => $data['id'] ?? null,
                'sort_no'      => $data['sort_no'] ?? null,
                'actorType' => $actorType,
                'actor'     => $actor,
                'file_keys' => array_keys($files)
            ]);

        try {

            if ($data['client_id'] !== null && !$this->clientModel->getById($data['client_id'])) {
                return [
                    'success' => false,
                    'message' => '선택한 거래처를 찾을 수 없습니다. 다시 선택해 주세요.'
                ];
            }

            if ($data['employee_id'] !== null && !$this->employeeModel->getById($data['employee_id'])) {
                return [
                    'success' => false,
                    'message' => '선택한 담당직원을 찾을 수 없습니다. 다시 선택해 주세요.'
                ];
            }

            $this->pdo->beginTransaction();

            $id = trim((string)($data['id'] ?? ''));

            $before = [];

            if ($id) {
                $before = $this->model->getById($id);

                if (!$before) {
                    throw new \Exception('수정할 프로젝트를 찾을 수 없습니다.');
                }
            }

            if ($id) {

                $data['updated_by'] = $actor;

                $updateData = $data;
                unset($updateData['id']);

                if (empty($updateData)) {

                    $this->pdo->commit();

                    return [
                        'success' => true,
                        'id'      => $id,
                        'sort_no'    => $before['sort_no'] ?? null,
                        'message' => '변경사항이 없습니다.'
                    ];
                }

                if (!$this->model->updateById($id, $updateData)) {
                    throw new \Exception('프로젝트 수정에 실패했습니다.');
                }

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id'      => $id,
                    'sort_no'    => $before['sort_no'] ?? null
                ];
            }

            $newId     = UuidHelper::generate();
            $newSortNo = SequenceHelper::next('system_projects', 'sort_no');

            $insertData = array_merge($data, [
                'id'         => $newId,
                'sort_no'    => $newSortNo,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('프로젝트 등록에 실패했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id'      => $newId,
                'sort_no' => $newSortNo
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('save() failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function normalizeNullableId(mixed $value): ?string
    {
        return $this->payloadService->normalizeNullableId($value);

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || strtolower($normalized) === 'null' || strtolower($normalized) === 'undefined') {
            return null;
        }

        return $normalized;
    }


    public function delete(string $id, string $actorType = 'USER'): array
    {
        return $this->trashService->delete($id, $actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('delete() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $item = $this->model->getById($id);

            if (!$item) {

                $this->logger->warning('delete() not found', [
                    'id' => $id
                ]);

                return [
                    'success' => false,
                    'message' => '삭제할 프로젝트를 찾을 수 없습니다.'
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {

                $this->logger->error('delete() DB failed', [
                    'id'    => $id,
                    'actor' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '프로젝트 삭제에 실패했습니다.'
                ];
            }

            $this->logger->info('delete() success', [
                'id' => $id
            ]);

            return ['success' => true];

        } catch (\Throwable $e) {

            $this->logger->error('delete() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    public function getTrashList(): array
    {
        return $this->trashService->getTrashList();

        $this->logger->info('getTrashList() called');

        try {

            return $this->model->getDeleted();

        } catch (\Throwable $e) {

            $this->logger->error('getTrashList() exception', [
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        return $this->trashService->restore($id, $actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restore() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $project = $this->model->getById($id);

            if (!$project) {

                $this->logger->warning('restore() not found', [
                    'id' => $id
                ]);

                return [
                    'success' => false,
                    'message' => '복구할 프로젝트를 찾을 수 없습니다.'
                ];
            }

            if (!$this->model->restoreById($id, $actor)) {

                $this->logger->error('restore() DB failed', [
                    'id'    => $id,
                    'actor' => $actor
                ]);

                return [
                    'success' => false,
                    'message' => '프로젝트 복구에 실패했습니다.'
                ];
            }

            $this->logger->info('restore() success', [
                'id' => $id
            ]);

            return [
                'success' => true
            ];

        } catch (\Throwable $e) {

            $this->logger->error('restore() exception', [
                'id'        => $id,
                'actor'     => $actor,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        return $this->trashService->restoreBulk($ids, $actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreBulk() called', [
            'ids'       => $ids,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        if (empty($ids)) {

            $this->logger->warning('restoreBulk() empty ids');

            return [
                'success' => false,
                'message' => '복구할 프로젝트 ID가 없습니다.'
            ];
        }

        try {

            $success = 0;

            foreach ($ids as $id) {

                if ($this->model->restoreById($id, $actor)) {
                    $success++;
                }
            }

            return [
                'success' => true,
                'message' => "선택 복구가 완료되었습니다. ({$success}건)"
            ];

        } catch (\Throwable $e) {

            $this->logger->error('restoreBulk() exception', [
                'ids'       => $ids,
                'actor'     => $actor,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    public function restoreAll(string $actorType = 'USER'): array
    {
        return $this->trashService->restoreAll($actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreAll() called', [
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $rows = $this->model->getDeleted();

            $success = 0;

            foreach ($rows as $row) {

                if ($this->model->restoreById($row['id'], $actor)) {
                    $success++;
                }
            }

            return [
                'success' => true,
                'message' => "전체 복구가 완료되었습니다. ({$success}건)"
            ];

        } catch (\Throwable $e) {

            $this->logger->error('restoreAll() exception', [
                'actor'     => $actor,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        return $this->trashService->purge($id, $actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purge() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $this->pdo->beginTransaction();

            $project = $this->model->getById($id);

            if (!$project) {

                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => '영구삭제할 프로젝트를 찾을 수 없습니다.'
                ];
            }

            $ok = $this->model->hardDeleteById($id);

            if (!$ok) {

                throw new \Exception('프로젝트 영구삭제에 실패했습니다.');
            }

            $this->pdo->commit();

            $this->logger->info('purge() success', [
                'id' => $id
            ]);

            return [
                'success' => true
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('purge() failed', [
                'id'    => $id,
                'actor' => $actor,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        return $this->trashService->purgeBulk($ids, $actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purgeBulk() called', [
            'ids'       => $ids,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        if (empty($ids)) {

            $this->logger->warning('purgeBulk() empty ids');

            return [
                'success' => false,
                'message' => '영구삭제할 프로젝트 ID가 없습니다.'
            ];
        }

        try {

            $this->pdo->beginTransaction();

            $success = 0;

            foreach ($ids as $id) {

                if ($this->model->hardDeleteById($id)) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "선택 영구삭제가 완료되었습니다. ({$success}건)"
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('purgeBulk() failed', [
                'ids'   => $ids,
                'actor' => $actor,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        return $this->trashService->purgeAll($actorType);

        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purgeAll() called', [
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $this->pdo->beginTransaction();

            $rows = $this->model->getDeleted();
            $count = count($rows);

            if ($count === 0) {

                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => '영구삭제할 프로젝트가 없습니다.'
                ];
            }

            $rows = $this->model->getDeleted();

            $success = 0;

            foreach ($rows as $row) {

                if ($this->model->hardDeleteById($row['id'])) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "전체 영구삭제가 완료되었습니다. ({$success}건)"
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('purgeAll() failed', [
                'actor' => $actor,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function reorder(array $changes): bool
    {
        // Reorder SSOT is ProjectTrashService.
        return $this->trashService->reorder($changes);

        $this->logger->info('reorder() called', [
            'changes' => $changes
        ]);

        if (empty($changes)) {
            return true;
        }

        try {

            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as $row) {

                if (
                    empty($row['id']) ||
                    !isset($row['newSortNo'])
                ) {
                    throw new \Exception('reorder 데이터 오류');
                }
            }

            foreach ($changes as $row) {

                $tempSortNo = (int)$row['newSortNo'] + 1000000;

                $this->model->updateSortNo(
                    $row['id'],
                    $tempSortNo
                );
            }

            foreach ($changes as $row) {

                $this->model->updateSortNo(
                    $row['id'],
                    (int)$row['newSortNo']
                );
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $this->logger->info('reorder() success');

            return true;

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('reorder() failed', [
                'exception' => $e->getMessage(),
                'changes' => $changes
            ]);

            throw $e;
        }
    }

    public function downloadTemplate(?string $columnsCsv = null): void
    {
        $this->excelService->downloadTemplate($columnsCsv);
        return;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('프로젝트 업로드');
        $headers = ['프로젝트명', '거래처명', '담당직원', '공사명', '계약일자', '착공일자', '준공일자', '계약금액', '비고'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([['샘플 프로젝트', '샘플 거래처', '홍길동', '샘플 공사', date('Y-m-d'), date('Y-m-d'), date('Y-m-d', strtotime('+1 year')), '1500000000', '']], null, 'A2');
        foreach (range('A', 'I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="project_template.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function saveFromExcelFile(string $filePath): array
    {
        return $this->excelService->saveFromExcelFile(
            $filePath,
            fn(array $payload): array => $this->save($payload, 'SYSTEM')
        );

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        if (empty($rows) || count($rows) < 2) { return ['success' => false, 'message' => '업로드할 데이터가 없습니다.']; }
        $header = array_map(fn($v) => trim((string)$v), array_shift($rows));
        $map = array_flip($header);
        $count = 0;
        foreach ($rows as $row) {
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) { continue; }
            $payload = [
                'project_name' => trim((string)($row[$map['프로젝트명'] ?? -1] ?? '')),
                'client_name' => trim((string)($row[$map['거래처명'] ?? -1] ?? '')),
                'contractor_name' => trim((string)($row[$map['담당직원'] ?? -1] ?? '')),
                'construction_name' => trim((string)($row[$map['공사명'] ?? -1] ?? '')),
                'contract_date' => trim((string)($row[$map['계약일자'] ?? -1] ?? '')) ?: null,
                'start_date' => trim((string)($row[$map['착공일자'] ?? -1] ?? '')) ?: null,
                'end_date' => trim((string)($row[$map['준공일자'] ?? -1] ?? '')) ?: null,
                'initial_contract_amount' => (float)($row[$map['계약금액'] ?? -1] ?? 0),
                'note' => trim((string)($row[$map['비고'] ?? -1] ?? '')),
                'is_active' => 1,
            ];
            if ($payload['project_name'] === '') { continue; }
            $result = $this->save($payload, 'SYSTEM');
            if (!empty($result['success'])) { $count++; }
        }
        return ['success' => true, 'message' => "{$count}건 업로드되었습니다."];
    }

    public function downloadExcel(?string $columnsCsv = null): void
    {
        $this->excelService->downloadExcel($columnsCsv);
        return;

        $projects = $this->model->getList();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('프로젝트 목록');
        $sheet->fromArray(['순번', '프로젝트명', '공사명', '거래처명', '담당직원', '착공일자', '준공일자', '계약금액', '사용여부', '비고'], null, 'A1');
        $rowNo = 2;
        foreach ($projects as $project) {
            $sheet->fromArray([[$project['sort_no'] ?? '', $project['project_name'] ?? '', $project['construction_name'] ?? '', $project['client_name'] ?? '', $project['contractor_name'] ?? '', $project['start_date'] ?? '', $project['end_date'] ?? '', $project['initial_contract_amount'] ?? '', !empty($project['is_active']) ? '진행중' : '완료됨', $project['note'] ?? '']], null, 'A' . $rowNo);
            $rowNo++;
        }
        foreach (range('A', 'J') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="project_list.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        exit;
    }

    public function downloadMigrationTemplate(?string $columnsCsv = null): void
    {
        $this->excelService->downloadMigrationTemplate($columnsCsv);
        return;
    }

    public function saveFromMigrationExcelFile(string $filePath, ?string $columnsCsv = null): array
    {
        return $this->excelService->saveFromMigrationExcelFile(
            $filePath,
            fn(array $payload): array => $this->save($payload, 'SYSTEM'),
            $columnsCsv
        );
    }

    public function downloadMigrationExcel(?string $columnsCsv = null): void
    {
        $this->excelService->downloadMigrationExcel($columnsCsv);
        return;
    }
    private function getProjectMigrationHeaders(): array
    {
        return $this->excelService->getProjectMigrationHeaders();

        return ['프로젝트명', '사용여부', '거래처명', '담당직원', '공사명', '계약일자', '착공일자', '준공일자', '계약금액', '비고'];
    }

    private function getProjectMigrationHeaderMap(): array
    {
        return $this->excelService->getProjectMigrationHeaderMap();

        return [
            '프로젝트명' => 'project_name',
            '사용여부' => 'is_active',
            '嫄곕옒泥섎챸' => 'client_name',
            '담당직원' => 'contractor_name',
            '공사명' => 'construction_name',
            '계약일자' => 'contract_date',
            '착공일자' => 'start_date',
            '준공일자' => 'end_date',
            '계약금액' => 'initial_contract_amount',
            '비고' => 'note',
            'projectname' => 'project_name',
            'isactive' => 'is_active',
            'clientname' => 'client_name',
            'contractorname' => 'contractor_name',
            'constructionname' => 'construction_name',
            'contractdate' => 'contract_date',
            'startdate' => 'start_date',
            'enddate' => 'end_date',
            'initialcontractamount' => 'initial_contract_amount',
            'note' => 'note',
        ];
    }
    private function normalizeProjectExcelDate(mixed $value): ?string
    {
        return $this->excelService->normalizeProjectExcelDate($value);

        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? $value : date('Y-m-d', $timestamp);
    }

    private function parseProjectExcelActiveValue(mixed $value): int
    {
        return $this->excelService->parseProjectExcelActiveValue($value);

        $normalized = mb_strtolower(trim((string)$value), 'UTF-8');
        return in_array($normalized, ['1', 'true', 'yes', 'use', 'active', 'y', '사용'], true) ? 1 : 0;
    }

    private function normalizeNullableProjectFields(array $data): array
    {
        return $this->payloadService->normalizeNullableProjectFields($data);

        $nullableFields = [
            'client_id',
            'employee_id',
            'site_agent',
            'contract_type',
            'contract_method',
            'director',
            'manager',
            'business_type',
            'housing_type',
            'construction_name',
            'site_region_city',
            'site_region_district',
            'site_region_address',
            'site_region_address_detail',
            'work_type',
            'work_subtype',
            'work_detail_type',
            'contract_work_type',
            'bid_type',
            'client_name',
            'client_type',
            'permit_agency',
            'permit_date',
            'contract_date',
            'start_date',
            'completion_date',
            'bid_notice_date',
            'initial_contract_amount',
            'authorized_company_seal',
            'note',
            'memo',
        ];

        foreach ($nullableFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            if ($field === 'initial_contract_amount') {
                $normalized = trim(str_replace(',', '', (string)$data[$field]));
                $data[$field] = $normalized === '' ? null : $normalized;
                continue;
            }

            $value = trim((string)$data[$field]);
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }

    private function resolveClientIdByName(string $clientName): ?string
    {
        return $this->referenceResolver->resolveClientIdByName($clientName);

        $clientName = trim($clientName);

        if ($clientName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM system_clients
            WHERE client_name = :client_name
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            'client_name' => $clientName,
        ]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (string)$id : null;
    }

    private function resolveEmployeeIdByName(string $employeeName): ?string
    {
        return $this->referenceResolver->resolveEmployeeIdByName($employeeName);

        $employeeName = trim($employeeName);

        if ($employeeName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT p.id
            FROM user_employees p
            LEFT JOIN auth_users u ON p.user_id = u.id
            WHERE p.employee_name = :employee_name
              AND COALESCE(u.is_active, 1) = 1
            LIMIT 1
        ");

        $stmt->execute([
            'employee_name' => $employeeName,
        ]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (string)$id : null;
    }


}
