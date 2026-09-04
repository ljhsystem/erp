<?php
namespace App\Services\User;

use App\Services\Concerns\LogsServiceOperations;
use PDO;
use App\Models\User\DepartmentModel;
use App\Repositories\User\DepartmentDependencyRepository;
use App\Services\System\UserSettingService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;

class DepartmentService
{
    use LogsServiceOperations;
    private readonly PDO $pdo;
    private DepartmentModel $model;
    private DepartmentDependencyRepository $dependencyRepository;
    private UserSettingService $userSettings;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new DepartmentModel($pdo);
        $this->dependencyRepository = new DepartmentDependencyRepository($pdo);
        $this->userSettings = new UserSettingService($pdo);
        $this->logger = LoggerFactory::getLogger('service-user.DepartmentService');
    }

    public function getAll(array $filters = []): array
    {
        return $this->model->getAll($filters);
    }

    public function getList(array $filters = []): array
    {
        return $this->getAll($filters);
    }

    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    public function create(array $data): array
    {
        return $this->logged('DEPARTMENT_CREATE','create',[],fn():array=>$this->createInternal($data));
    }

    private function createInternal(array $data): array
    {
        $data = $this->validateSaveData($data);

        if ($this->model->existsByName($data['dept_name'])) {
            return ['success' => false, 'message' => '이미 등록된 부서명입니다.'];
        }

        $data['id'] = UuidHelper::generate();

        $data['sort_no'] = SequenceHelper::next('user_departments', 'sort_no');

        $data['created_by'] = ActorHelper::user();

        $created = $this->model->create($data);
        return [
            'success' => $created,
            'message' => $created ? '부서가 등록되었습니다.' : '저장 중 오류가 발생했습니다.',
        ];
    }

    public function update(string $id, array $data): array
    {
        return $this->logged('DEPARTMENT_UPDATE','update',['department_id'=>$id],fn():array=>$this->updateInternal($id,$data));
    }

    private function updateInternal(string $id, array $data): array
    {
        if (trim($id) === '') {
            throw new \InvalidArgumentException('부서 ID가 필요합니다.');
        }

        $current = $this->model->getById($id);
        if (!$current) {
            return ['success' => false, 'message' => '부서 정보를 찾을 수 없습니다.'];
        }

        $data = $this->validateSaveData($data, $current);

        if (!empty($data['dept_name'])) {
            if ($this->model->existsByName($data['dept_name'], $id)) {
                return ['success' => false, 'message' => '이미 등록된 부서명입니다.'];
            }
        }

        $data['updated_by'] = ActorHelper::user();

        $updated = $this->model->update($id, $data);
        return [
            'success' => $updated,
            'message' => $updated ? '부서가 수정되었습니다.' : '수정 중 오류가 발생했습니다.',
        ];
    }

    public function delete(string $id): array
    {
        return $this->logged('DEPARTMENT_DELETE','delete',['department_id'=>$id],fn():array=>$this->deleteInternal($id));
    }

    private function deleteInternal(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('부서 ID가 필요합니다.');
        }

        if (!$this->model->getById($id)) {
            return ['success' => false, 'message' => '부서 정보를 찾을 수 없습니다.'];
        }

        $references = $this->dependencyRepository->findReferences($id);
        if ($references !== []) {
            return [
                'success' => false,
                'message' => '사용 중인 부서이므로 삭제할 수 없습니다.',
            ];
        }

        try {
            $deleted = $this->model->delete($id);
            return [
                'success' => $deleted,
                'message' => $deleted ? '부서가 영구삭제되었습니다.' : '부서 정보를 찾을 수 없습니다.',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '삭제 중 오류가 발생했습니다.'];
        }
    }

    public function reorder(array $changes): bool
    {
        return $this->runLoggedOperation($this->logger,'부서','DEPARTMENT_REORDER','reorder',['change_count'=>count($changes)],fn():bool=>$this->reorderInternal($changes));
    }

    private function reorderInternal(array $changes): bool
    {
        if (empty($changes)) {
            return true;
        }

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as &$row) {
                $sortNo = $row['newSortNo'] ?? $row['sort_no'] ?? null;

                if (empty($row['id']) || $sortNo === null) {
                    throw new \Exception('reorder 데이터 오류');
                }

                $row['_sort_no'] = (int) $sortNo;
            }
            unset($row);

            foreach ($changes as $row) {
                $this->model->updateSortNo($row['id'], $row['_sort_no'] + 1000000);
            }

            foreach ($changes as $row) {
                $this->model->updateSortNo($row['id'], $row['_sort_no']);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function logged(string $event,string $action,array $context,callable $operation):array
    {
        return $this->runLoggedOperation($this->logger,'부서',$event,$action,$context,$operation,'info',true,static function(array$result):string{if(!empty($result['success']))return'SUCCESS';return str_contains((string)($result['message']??''),'오류')?'FAILED':'BLOCKED';});
    }

    private function validateSaveData(array $data, ?array $current = null): array
    {
        $deptName = trim((string) ($data['dept_name'] ?? ''));
        if ($deptName === '') {
            throw new \InvalidArgumentException('부서명은 필수입니다.');
        }
        if (mb_strlen($deptName) > 100) {
            throw new \InvalidArgumentException('부서명은 100자 이하로 입력해 주세요.');
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            throw new \InvalidArgumentException('부서 설명은 255자 이하로 입력해 주세요.');
        }

        $isActive = filter_var($data['is_active'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($isActive, [0, 1], true)) {
            throw new \InvalidArgumentException('상태 값이 올바르지 않습니다.');
        }

        $managerId = trim((string) ($data['manager_id'] ?? ''));
        $currentManagerId = trim((string) ($current['manager_id'] ?? ''));
        if (
            $managerId !== ''
            && $managerId !== $currentManagerId
            && !$this->model->isSelectableManager($managerId)
        ) {
            throw new \InvalidArgumentException('현재 선택 가능한 직원을 부서장으로 지정해 주세요.');
        }

        $normalized = [
            'dept_name' => $deptName,
            'manager_id' => $managerId !== '' ? $managerId : null,
            'description' => $description !== '' ? $description : null,
            'is_active' => $isActive,
        ];
        $this->validateRequiredFieldPolicies($normalized);
        return $normalized;
    }

    private function validateRequiredFieldPolicies(array $data): void
    {
        $settings = $this->userSettings->detail('department', 'TABLE')['settings_json'] ?? [];
        $policies = is_array($settings['columnRequirementPolicy'] ?? null)
            ? $settings['columnRequirementPolicy']
            : [];
        $displayNames = is_array($settings['columnDisplayName'] ?? null)
            ? $settings['columnDisplayName']
            : [];
        $labels = [
            'dept_name' => '부서명',
            'manager_id' => '부서장',
            'description' => '부서 설명',
            'is_active' => '상태',
        ];

        foreach ($labels as $key => $fallback) {
            if (strtolower(trim((string) ($policies[$key] ?? 'none'))) !== 'required') {
                continue;
            }
            if (trim((string) ($data[$key] ?? '')) === '') {
                $label = trim((string) ($displayNames[$key] ?? '')) ?: $fallback;
                throw new \InvalidArgumentException($label . ' 항목은 필수입니다.');
            }
        }
    }
}
