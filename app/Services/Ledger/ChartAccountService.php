<?php
// 경로: PROJECT_ROOT . '/app/Services/Ledger/ChartAccountService.php'
// 설명:
//  - 회계 계정과목 서비스
//  - 계정 생성 / 수정 / 삭제 / 조회
//  - 비즈니스 로직 담당
namespace App\Services\Ledger;

use PDO;
use App\Models\Ledger\ChartAccountModel;
use App\Models\Ledger\SubChartAccountModel;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;

class ChartAccountService
{
    private readonly PDO $pdo;
    private ChartAccountModel $model;
    private SubChartAccountModel $subModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new ChartAccountModel($this->pdo);
        $this->subModel = new SubChartAccountModel($this->pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.ChartAccountService');
        $this->logger->info('ChartAccountService initialized');
    }

    /* =========================================================
     * 전체 계정 조회
     * ========================================================= */

    public function getAll(): array
    {
        try {

            $rows = $this->model->getAll();

            return $rows;

        } catch (\Throwable $e) {

            $this->logger->error('getAll failed', [
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    /* =========================================================
     * 계정 트리 조회
     * ========================================================= */

    public function getTree(): array
    {
        try {

            $rows = $this->model->getTree();

            return $rows;

        } catch (\Throwable $e) {

            $this->logger->error('getTree failed', [
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    /* =========================================================
     * 단건 조회
     * ========================================================= */

    public function getById(string $id): ?array
    {
        try {

            return $this->model->getById($id);

        } catch (\Throwable $e) {

            $this->logger->error('getById failed', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }

    /* =========================================================
     * 계정코드 조회
     * ========================================================= */

    public function getByAccountCode(string $accountCode): ?array
    {
        try {

            return $this->model->getByAccountCode($accountCode);

        } catch (\Throwable $e) {

            $this->logger->error('getByAccountCode failed', [
                'account_code' => $accountCode,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }

    /* =========================================================
     * 계정 생성
     * ========================================================= */

     public function create(array $data): array
     {
         try {
     
             // 🔥 계정코드 중복 체크
             $exists = $this->model->getByAccountCode($data['account_code']);
     
             if ($exists) {
                 return [
                     'success' => false,
                     'message' => '이미 존재하는 계정코드입니다.'
                 ];
             }
     
             // 🔥 부모 계정 존재 체크
             if (!empty($data['parent_id'])) {
                 $parent = $this->model->getById($data['parent_id']);
     
                 if (!$parent) {
                     return [
                         'success' => false,
                         'message' => '상위 계정이 존재하지 않습니다.'
                     ];
                 }
             }
     
             $data['id'] = UuidHelper::generate();
     
             if (!$this->model->create($data)) {
                 return [
                     'success' => false,
                     'message' => '계정 생성 실패'
                 ];
             }
     
             return [
                 'success' => true,
                 'id' => $data['id']
             ];
     
         } catch (\Throwable $e) {
     
             $this->logger->error('create failed', [
                 'data' => $data,
                 'exception' => $e->getMessage()
             ]);
     
             return [
                 'success' => false,
                 'message' => '계정 저장 중 오류가 발생했습니다.'
             ];
         }
     }

    /* =========================================================
     * 계정 수정
     * ========================================================= */

     public function update(string $id, array $data): array
     {
         try {
     
             // 🔥 기존 데이터 조회 (핵심)
             $existing = $this->model->getById($id);
     
             if (!$existing) {
                 return [
                     'success' => false,
                     'message' => '계정을 찾을 수 없습니다.'
                 ];
             }
     
            // 🔥 기존 데이터 + 입력 데이터 병합
            $data = array_merge($existing, $data);

            // 🔥 updated_by 강제 보정 (핵심)
            if (empty($data['updated_by'])) {
                throw new \Exception('updated_by 값이 없습니다.');
            }
     
             // 🔥 이후부터 기존 로직 그대로 사용 가능
     
             $exists = $this->model->getByAccountCode($data['account_code']);
     
             if ($exists && $exists['id'] !== $id) {
                 return [
                     'success' => false,
                     'message' => '이미 존재하는 계정코드입니다.'
                 ];
             }
     
             if (!empty($data['parent_id']) && $data['parent_id'] === $id) {
                 return [
                     'success' => false,
                     'message' => '자기 자신을 상위 계정으로 설정할 수 없습니다.'
                 ];
             }
     
             if (!empty($data['parent_id'])) {
                 $parent = $this->model->getById($data['parent_id']);
     
                 if (!$parent) {
                     return [
                         'success' => false,
                         'message' => '상위 계정이 존재하지 않습니다.'
                     ];
                 }
             }
     
             if (!$this->model->update($id, $data)) {
                 return [
                     'success' => false,
                     'message' => '계정 수정 실패'
                 ];
             }
     
             return [
                 'success' => true
             ];
     
         } catch (\Throwable $e) {
     
             $this->logger->error('update failed', [
                 'id' => $id,
                 'data' => $data,
                 'exception' => $e->getMessage()
             ]);
     
             return [
                 'success' => false,
                 'message' => '계정 수정 중 오류가 발생했습니다.'
             ];
         }
     }

    /* =========================================================
     * 계정 삭제
     * ========================================================= */
    public function softDelete(string $id, string $actor): array
    {
        if ($this->model->hasChildren($id)) {
            return [
                'success' => false,
                'message' => '하위 계정이 존재하여 삭제할 수 없습니다.'
            ];
        }
    
        $ok = $this->model->softDelete($id, $actor);
    
        return ['success' => $ok];
    }
    public function restore(string $id, string $actor): array
    {
        return [
            'success' => $this->model->restore($id, $actor)
        ];
    }


    public function getTrashList(): array
    {
        return $this->model->getTrashList();
    }
    
    public function hardDelete(string $id, string $actor): array
    {
        if ($this->model->hasChildren($id)) {
            return [
                'success' => false,
                'message' => '하위 계정 존재 → 완전삭제 불가'
            ];
        }
    
        return [
            'success' => $this->model->hardDelete($id, $actor)
        ];
    }

    /* =========================================================
    * 하위 계정 존재 여부
    * ========================================================= */

    public function hasChildren(string $id): bool
    {
        return $this->model->hasChildren($id);
    }

    /* =========================================================
    * 트리 구조 변환 (핵심)
    * ========================================================= */
    public function getTreeStructured(): array
    {
        $rows = $this->model->getTree();
    
        $map = [];
        $tree = [];
    
        // 1. 맵 구성
        foreach ($rows as &$row) {
            $row['children'] = [];
            $map[$row['id']] = &$row;
        }
    
        // 2. 트리 구성
        foreach ($rows as &$row) {
    
            if (!empty($row['parent_id']) && isset($map[$row['parent_id']])) {
                $map[$row['parent_id']]['children'][] = &$row;
            } else {
                $tree[] = &$row;
            }
        }
    
        return $tree;
    }


    public function findByCode(string $code)
    {
        return $this->model->findByCode($code);
    }


    public function createSubAccount(array $data): array
    {
        try {

            $accountId = $data['account_id'] ?? null;
            $subName   = trim((string)($data['sub_name'] ?? ''));
            if (empty($data['created_by'])) {
                throw new \Exception('created_by 값 없음');
            }
            
            $createdBy = $data['created_by'];

            if (!$accountId || $subName === '') {
                return [
                    'success' => false,
                    'message' => '보조계정 생성값 부족'
                ];
            }

            // 🔥 중복 체크 → Model
            $exists = $this->subModel->findByAccountAndName($accountId, $subName);

            if ($exists) {
                return [
                    'success' => true,
                    'message' => '이미 존재',
                    'id' => $exists['id']
                ];
            }

            // 🔥 코드 생성 → Model
            $subCode = $this->subModel->getNextSubCode($accountId);

            $id = UuidHelper::generate();

            // 🔥 insert → Model
            $ok = $this->subModel->create([
                'id'         => $id,
                'account_id' => $accountId,
                'sub_code'   => $subCode,
                'sub_name'   => $subName,
                'created_by' => $createdBy,
                'updated_by' => $createdBy
            ]);

            return [
                'success' => $ok,
                'id' => $id
            ];

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    public function getList(array $filters = []): array
    {
        return $this->model->getList($filters);
    }
    // 순서변경
    public function reorder(array $changes): void
    {
        foreach ($changes as $row) {
            $this->model->updateOrder(
                $row['id'],
                (int)$row['newCode']
            );
        }
    }

    public function getDetailByAccountCode(string $accountCode): ?array
    {
        return $this->model->getDetailByAccountCode($accountCode);
    }

    public function restoreBulk(array $ids): void
    {
        if(empty($ids)) return;

        $in = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            UPDATE ledger_accounts
            SET deleted_at = NULL,
                deleted_by = NULL
            WHERE id IN ($in)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);
    }

    public function hardDeleteAll(): void
    {
        $sql = "DELETE FROM ledger_accounts WHERE deleted_at IS NOT NULL";
        $this->pdo->exec($sql);
    }


}