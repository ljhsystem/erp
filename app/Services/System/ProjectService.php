<?php

namespace App\Services\System;

use App\Models\System\ClientModel;
use App\Models\System\ProjectModel;
use App\Models\User\EmployeeModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class ProjectService
{
    private readonly ProjectModel $model;
    private readonly ClientModel $clientModel;
    private readonly EmployeeModel $employeeModel;
    private readonly ProjectPayloadService $payloadService;
    private readonly ProjectReferenceResolver $referenceResolver;
    private readonly ProjectTrashService $trashService;
    private readonly ProjectExcelService $excelService;
    private mixed $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->logger = LoggerFactory::getLogger('service-system.ProjectService');
        $this->model = new ProjectModel($pdo);
        $this->clientModel = new ClientModel($pdo);
        $this->employeeModel = new EmployeeModel($pdo);
        $this->payloadService = new ProjectPayloadService();
        $this->referenceResolver = new ProjectReferenceResolver($pdo);
        $this->excelService = new ProjectExcelService($pdo, $this->model);
        $this->trashService = new ProjectTrashService($pdo, $this->model, $this->logger);
    }

    public function getList(array $filters = []): array
    {
        return $this->model->getList($filters);
    }

    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    public function searchPicker(string $keyword): array
    {
        return array_map(static function (array $row): array {
            $text = (string) ($row['project_name'] ?? '');
            if (!empty($row['construction_name']) && $row['construction_name'] !== $row['project_name']) {
                $text .= ' / ' . $row['construction_name'];
            }
            if (!empty($row['sort_no'])) $text .= ' [' . $row['sort_no'] . ']';
            return ['id' => $row['id'], 'text' => $text];
        }, $this->model->searchPicker($keyword, 20));
    }

    public function distinctValues(string $field, string $keyword = '', int $limit = 20): array
    {
        return $this->model->distinctValues($field, $keyword, $limit);
    }

    public function normalizePayload(array $input): array
    {
        return $this->payloadService->normalizePayload($input);
    }

    public function validatePayload(array $payload): array
    {
        return $this->payloadService->validatePayload($payload);
    }

    public function saveWithFiles(array $input, array $files = [], string $actorType = 'USER'): array
    {
        try {
            $payload = $this->normalizePayload($input);
            $validation = $this->validatePayload($payload);
            return empty($validation['success']) ? $validation : $this->save($payload, $actorType, $files);
        } catch (\Throwable $e) {
            $this->logger->error('saveWithFiles() failed', ['exception' => $e->getMessage()]);
            return ['success' => false, 'message' => '저장 중 오류가 발생했습니다.'];
        }
    }

    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);
        $data = $this->payloadService->normalizeNullableProjectFields($data);
        $data['client_id'] = $this->payloadService->normalizeNullableId($data['client_id'] ?? null);
        $data['employee_id'] = $this->payloadService->normalizeNullableId($data['employee_id'] ?? null);
        $data['site_agent'] = $this->payloadService->normalizeNullableId($data['site_agent'] ?? null);

        $employeeName = trim((string) ($data['employee_name'] ?? ''));
        if ($data['employee_id'] === null && $employeeName !== '') {
            $data['employee_id'] = $this->referenceResolver->resolveEmployeeIdByName($employeeName);
        }
        $linkedClientName = trim((string) ($data['linked_client_name'] ?? ''));
        if ($data['client_id'] === null && $linkedClientName !== '') {
            $data['client_id'] = $this->referenceResolver->resolveClientIdByName($linkedClientName);
        }
        if ($data['site_agent'] !== null && !$this->employeeModel->getById($data['site_agent'])) {
            $data['site_agent'] = $this->referenceResolver->resolveEmployeeIdByName($data['site_agent']);
        }
        unset($data['employee_name'], $data['linked_client_name']);

        if ($data['client_id'] !== null && !$this->clientModel->getById($data['client_id'])) {
            return ['success' => false, 'message' => '선택한 거래처를 찾을 수 없습니다. 다시 선택해 주세요.'];
        }
        if ($data['employee_id'] !== null && !$this->employeeModel->getById($data['employee_id'])) {
            return ['success' => false, 'message' => '선택한 담당직원을 찾을 수 없습니다. 다시 선택해 주세요.'];
        }
        if ($data['site_agent'] !== null && !$this->employeeModel->getById($data['site_agent'])) {
            return ['success' => false, 'message' => '선택한 현장대리인을 찾을 수 없습니다. 다시 선택해 주세요.'];
        }

        try {
            $this->pdo->beginTransaction();
            $id = trim((string) ($data['id'] ?? ''));
            if ($id !== '') {
                $before = $this->model->getById($id);
                if (!$before) throw new \RuntimeException('수정할 프로젝트를 찾을 수 없습니다.');
                $data['updated_by'] = $actor;
                unset($data['id'], $data['sort_no']);
                if (!$this->model->updateById($id, $data)) throw new \RuntimeException('프로젝트 수정 실패');
                $this->pdo->commit();
                return ['success' => true, 'id' => $id, 'sort_no' => $before['sort_no'] ?? null];
            }

            $id = UuidHelper::generate();
            $sortNo = SequenceHelper::next('system_projects', 'sort_no');
            unset($data['id']);
            $data = array_merge($data, ['id' => $id, 'sort_no' => $sortNo, 'created_by' => $actor, 'updated_by' => $actor]);
            if (!$this->model->create($data)) throw new \RuntimeException('프로젝트 등록 실패');
            $this->pdo->commit();
            return ['success' => true, 'id' => $id, 'sort_no' => $sortNo];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->logger->error('save() failed', ['exception' => $e->getMessage(), 'file_keys' => array_keys($files)]);
            return ['success' => false, 'message' => '저장 중 오류가 발생했습니다.'];
        }
    }

    public function delete(string $id, string $actorType = 'USER'): array { return $this->trashService->delete($id, $actorType); }
    public function getTrashList(): array { return $this->trashService->getTrashList(); }
    public function restore(string $id, string $actorType = 'USER'): array { return $this->trashService->restore($id, $actorType); }
    public function restoreBulk(array $ids, string $actorType = 'USER'): array { return $this->trashService->restoreBulk($ids, $actorType); }
    public function restoreAll(string $actorType = 'USER'): array { return $this->trashService->restoreAll($actorType); }
    public function purge(string $id, string $actorType = 'USER'): array { return $this->trashService->purge($id, $actorType); }
    public function purgeBulk(array $ids, string $actorType = 'USER'): array { return $this->trashService->purgeBulk($ids, $actorType); }
    public function purgeAll(string $actorType = 'USER'): array { return $this->trashService->purgeAll($actorType); }
    public function reorder(array $changes): bool { return $this->trashService->reorder($changes); }

    public function downloadTemplate(?string $columnsCsv = null): void { $this->excelService->downloadTemplate($columnsCsv); }
    public function saveFromExcelFile(string $filePath): array
    {
        return $this->excelService->saveFromExcelFile($filePath, fn(array $payload): array => $this->save($payload, 'SYSTEM'));
    }
    public function downloadExcel(?string $columnsCsv = null): void { $this->excelService->downloadExcel($columnsCsv); }
    public function downloadMigrationTemplate(?string $columnsCsv = null): void { $this->excelService->downloadMigrationTemplate($columnsCsv); }
    public function saveFromMigrationExcelFile(string $filePath, ?string $columnsCsv = null): array
    {
        return $this->excelService->saveFromMigrationExcelFile($filePath, fn(array $payload): array => $this->save($payload, 'SYSTEM'), $columnsCsv);
    }
    public function downloadMigrationExcel(?string $columnsCsv = null): void { $this->excelService->downloadMigrationExcel($columnsCsv); }
}
