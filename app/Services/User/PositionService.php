<?php

namespace App\Services\User;

use App\Services\Concerns\LogsServiceOperations;
use PDO;
use App\Models\User\PositionModel;
use App\Repositories\User\PositionDependencyRepository;
use App\Services\System\UserSettingService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;

class PositionService
{
    use LogsServiceOperations;
    private readonly PDO $pdo;
    private PositionModel $model;
    private PositionDependencyRepository $dependencyRepository;
    private UserSettingService $userSettings;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new PositionModel($pdo);
        $this->dependencyRepository = new PositionDependencyRepository($pdo);
        $this->userSettings = new UserSettingService($pdo);
        $this->logger = LoggerFactory::getLogger('service-user.PositionService');
    }

    public function getAll(array $filters = []): array
    {
        return $this->model->getAll($filters);
    }

    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    public function create(array $data): array
    {
        return $this->logged('POSITION_CREATE','create',[],fn():array=>$this->createInternal($data));
    }

    private function createInternal(array $data): array
    {
        $data = $this->validateSaveData($data);
        $name = $data['position_name'];

        if ($this->model->existsByName($name)) {
            return ['success' => false, 'message' => '이미 등록된 직책명입니다.'];
        }

        $data['id'] = UuidHelper::generate();
        $data['sort_no'] = SequenceHelper::next('user_positions', 'sort_no');

        $data['created_by'] = ActorHelper::user();

        $ok = $this->model->create($data);

        return [
            'success' => $ok,
            'message' => $ok ? '직책이 등록되었습니다.' : '저장 중 오류가 발생했습니다.'
        ];
    }

    public function update(string $id, array $data): array
    {
        return $this->logged('POSITION_UPDATE','update',['position_id'=>$id],fn():array=>$this->updateInternal($id,$data));
    }

    private function updateInternal(string $id, array $data): array
    {
        if (trim($id) === '') {
            throw new \InvalidArgumentException('직책 ID가 필요합니다.');
        }

        if (!$this->model->getById($id)) {
            return ['success' => false, 'message' => '직책 정보를 찾을 수 없습니다.'];
        }

        $data = $this->validateSaveData($data);
        $name = $data['position_name'];
        if ($this->model->existsByName($name, $id)) {
            return ['success' => false, 'message' => '이미 등록된 직책명입니다.'];
        }

        $data['updated_by'] = ActorHelper::user();

        $ok = $this->model->update($id, $data);

        return [
            'success' => $ok,
            'message' => $ok ? '직책이 수정되었습니다.' : '수정 중 오류가 발생했습니다.'
        ];
    }

    public function delete(string $id): array
    {
        return $this->logged('POSITION_DELETE','delete',['position_id'=>$id],fn():array=>$this->deleteInternal($id));
    }

    private function deleteInternal(string $id): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('직책 ID가 필요합니다.');
        }

        if (!$this->model->getById($id)) {
            return ['success' => false, 'message' => '직책 정보를 찾을 수 없습니다.'];
        }

        if ($this->dependencyRepository->findReferences($id) !== []) {
            return ['success' => false, 'message' => '사용 중인 직책이므로 삭제할 수 없습니다.'];
        }

        try {
            $deleted = $this->model->delete($id);
            return [
                'success' => $deleted,
                'message' => $deleted ? '직책이 영구삭제되었습니다.' : '직책 정보를 찾을 수 없습니다.',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '삭제 중 오류가 발생했습니다.'];
        }
    }

    public function reorder(array $changes): bool
    {
        return $this->runLoggedOperation($this->logger,'직책','POSITION_REORDER','reorder',['change_count'=>count($changes)],fn():bool=>$this->reorderInternal($changes));
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
        return $this->runLoggedOperation($this->logger,'직책',$event,$action,$context,$operation,'info',true,static function(array$result):string{if(!empty($result['success']))return'SUCCESS';return str_contains((string)($result['message']??''),'오류')?'FAILED':'BLOCKED';});
    }

    private function validateSaveData(array $data): array
    {
        $name = trim((string) ($data['position_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('직책명은 필수입니다.');
        }
        if (mb_strlen($name) > 50) {
            throw new \InvalidArgumentException('직책명은 50자 이하로 입력해 주세요.');
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            throw new \InvalidArgumentException('직책 설명은 255자 이하로 입력해 주세요.');
        }

        $isActive = filter_var($data['is_active'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($isActive, [0, 1], true)) {
            throw new \InvalidArgumentException('상태 값이 올바르지 않습니다.');
        }

        $levelRankValue = trim((string) ($data['level_rank'] ?? ''));
        $levelRank = null;
        if ($levelRankValue !== '') {
            $levelRank = filter_var($levelRankValue, FILTER_VALIDATE_INT);
            if ($levelRank === false) {
                throw new \InvalidArgumentException('레벨은 정수로 입력해 주세요.');
            }
        }

        $normalized = [
            'position_name' => $name,
            'level_rank' => $levelRank,
            'description' => $description !== '' ? $description : null,
            'is_active' => $isActive,
        ];
        $this->validateRequiredFieldPolicies($normalized);
        return $normalized;
    }

    private function validateRequiredFieldPolicies(array $data): void
    {
        $settings = $this->userSettings->detail('position', 'TABLE')['settings_json'] ?? [];
        $policies = is_array($settings['columnRequirementPolicy'] ?? null)
            ? $settings['columnRequirementPolicy']
            : [];
        $displayNames = is_array($settings['columnDisplayName'] ?? null)
            ? $settings['columnDisplayName']
            : [];
        $labels = [
            'position_name' => '직책명',
            'level_rank' => '레벨',
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
