<?php
namespace App\Services\Auth;

use PDO;
use App\Models\Auth\RoleModel;
use App\Models\Auth\RolePermissionModel;
use App\Repositories\Auth\RoleDependencyRepository;
use App\Services\System\UserSettingService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;

class RoleService
{
    private const PROTECTED_ROLE_KEYS = ['super_admin', 'admin'];

    private readonly PDO $pdo;
    private RoleModel $model;
    private RolePermissionModel $rolePermissionModel;
    private RoleDependencyRepository $dependencyRepository;
    private UserSettingService $userSettings;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new RoleModel($pdo);
        $this->rolePermissionModel = new RolePermissionModel($pdo);
        $this->dependencyRepository = new RoleDependencyRepository($pdo);
        $this->userSettings = new UserSettingService($pdo);
        $this->logger = LoggerFactory::getLogger('service-auth.RoleService');
    }

    public function getAll(array $filters = []): array
    {
        return $this->model->getAll($filters);
    }

    public function getById(string $id): ?array
    {
        return $this->model->getById(trim($id));
    }

    public function create(array $data): array
    {
        $data = $this->validateSaveData($data);

        if ($this->model->existsKey($data['role_key'])) {
            return [
                'success' => false,
                'message' => '이미 등록된 역할 키입니다.'
            ];
        }

        $data['id']         = UuidHelper::generate();
        $data['sort_no']       = SequenceHelper::next('auth_roles', 'sort_no');
        $data['created_by'] = ActorHelper::user();

        $ok = $this->model->create($data);

        return [
            'success' => $ok,
            'message' => $ok
                ? '역할이 생성되었습니다.'
                : '저장 중 오류가 발생했습니다.'
        ];
    }

    public function update(string $id, array $data): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('역할 ID가 필요합니다.');
        }

        $current = $this->model->getById($id);
        if (!$current) {
            return ['success' => false, 'message' => '역할 정보를 찾을 수 없습니다.'];
        }

        $data = $this->validateSaveData($data);
        $currentKey = trim((string) ($current['role_key'] ?? ''));
        if (in_array($currentKey, self::PROTECTED_ROLE_KEYS, true)) {
            if ($data['role_key'] !== $currentKey) {
                throw new \InvalidArgumentException('핵심 시스템 역할의 역할 키는 변경할 수 없습니다.');
            }
            if ($data['is_active'] !== 1) {
                throw new \InvalidArgumentException('핵심 시스템 역할은 비활성화할 수 없습니다.');
            }
        }

        if ($this->model->existsKey($data['role_key'], $id)) {

            return [
                'success' => false,
                'message' => '이미 등록된 역할 키입니다.'
            ];
        }

        $data['updated_by'] = ActorHelper::user();

        $ok = $this->model->update($id, $data);

        return [
            'success' => $ok,
            'message' => $ok ? '역할이 수정되었습니다.' : '수정 중 오류가 발생했습니다.'
        ];
    }

    public function delete(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('역할 ID가 필요합니다.');
        }

        $current = $this->model->getById($id);
        if (!$current) {
            return ['success' => false, 'message' => '역할 정보를 찾을 수 없습니다.'];
        }
        if (in_array(trim((string) ($current['role_key'] ?? '')), self::PROTECTED_ROLE_KEYS, true)) {
            return ['success' => false, 'message' => '핵심 시스템 역할은 삭제할 수 없습니다.'];
        }
        if ($this->dependencyRepository->findReferences($id) !== []) {
            return ['success' => false, 'message' => '사용 중인 역할이므로 삭제할 수 없습니다.'];
        }

        $isOuter = !$this->pdo->inTransaction();
        try {
            if ($isOuter) {
                $this->pdo->beginTransaction();
            }
            $this->rolePermissionModel->deleteByRoleId($id);
            if (!$this->model->delete($id)) {
                throw new \RuntimeException('role row was not deleted');
            }
            if ($isOuter) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => '역할이 영구삭제되었습니다.'];
        } catch (\Throwable $e) {
            if ($isOuter && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('role hard delete failed', [
                'role_id' => $id,
                'exception' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => '사용 중인 역할이므로 삭제할 수 없습니다.'];
        }
    }

    public function findByIdOrKey(string $value): ?array
    {
        return $this->model->findByIdOrKey($value);
    }

    public function reorder(array $changes): bool
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

    private function validateSaveData(array $data): array
    {
        $roleKey = trim((string) ($data['role_key'] ?? ''));
        if ($roleKey === '') {
            throw new \InvalidArgumentException('역할 키는 필수입니다.');
        }
        if (mb_strlen($roleKey) > 50) {
            throw new \InvalidArgumentException('역할 키는 50자 이하로 입력해 주세요.');
        }

        $roleName = trim((string) ($data['role_name'] ?? ''));
        if ($roleName === '') {
            throw new \InvalidArgumentException('역할명은 필수입니다.');
        }
        if (mb_strlen($roleName) > 100) {
            throw new \InvalidArgumentException('역할명은 100자 이하로 입력해 주세요.');
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            throw new \InvalidArgumentException('설명은 255자 이하로 입력해 주세요.');
        }

        $isActive = filter_var($data['is_active'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($isActive, [0, 1], true)) {
            throw new \InvalidArgumentException('상태 값이 올바르지 않습니다.');
        }

        $normalized = [
            'role_key' => $roleKey,
            'role_name' => $roleName,
            'description' => $description !== '' ? $description : null,
            'is_active' => $isActive,
        ];
        $this->validateRequiredFieldPolicies($normalized);
        return $normalized;
    }

    private function validateRequiredFieldPolicies(array $data): void
    {
        $settings = $this->userSettings->detail('role', 'TABLE')['settings_json'] ?? [];
        $policies = is_array($settings['columnRequirementPolicy'] ?? null)
            ? $settings['columnRequirementPolicy']
            : [];
        $displayNames = is_array($settings['columnDisplayName'] ?? null)
            ? $settings['columnDisplayName']
            : [];
        $labels = [
            'role_key' => '역할 키',
            'role_name' => '역할명',
            'description' => '설명',
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
