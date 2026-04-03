<?php
// 경로: PROJECT_ROOT . '/app/services/system/ProjectService.php'
// 설명:
//  - 프로젝트(Project) 관리 서비스
//  - UUID 생성은 Service 책임
//  - code는 코드헬퍼사용
//  - DB 처리: DashboardProjectModel
//  - 모든 주요 흐름 LoggerFactory 적용
namespace App\Services\System;

use PDO;
use App\Models\System\SystemProjectModel;
use App\Models\System\SystemClientModel;
use App\Models\User\UserProfileModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;

class ProjectService
{
    private readonly PDO $pdo;
    private SystemProjectModel $model;
    private SystemClientModel $clientModel;
    private UserProfileModel $userprofileModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new SystemProjectModel($this->pdo);
        $this->clientModel  = new SystemClientModel($this->pdo);
        $this->userprofileModel  = new UserProfileModel($this->pdo);
        $this->logger = LoggerFactory::getLogger('service-system.ProjectService');
        $this->logger->info('ProjectService initialized');
    }

    /* ============================================================
     * 1. 전체 목록 조회
     * ============================================================ */
    public function getList(): array
    {
        $this->logger->info('getAll() called');

        try {
            $rows = $this->model->getAll();

            $this->logger->info('getAll() success', [
                'count' => count($rows)
            ]);

            return $rows;

        } catch (\Throwable $e) {
            $this->logger->error('getAll() failed', [
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }

    /* ============================================================
     * 2. 단건 조회 (id 기준)
     * ============================================================ */
    public function getById(string $id, bool $includeDeleted = false): ?array
    {
        $this->logger->info('getById() called', [
            'id' => $id,
            'includeDeleted' => $includeDeleted
        ]);
    
        try {         
            $row = $this->model->getById($id, $includeDeleted);
    
            if (!$row) {
                $this->logger->warning('getById() not found', [
                    'id' => $id,
                    'includeDeleted' => $includeDeleted
                ]);
                return null;
            }
    
            return $row;
    
        } catch (\Throwable $e) {
            $this->logger->error('getById() exception', [
                'id'      => $id,
                'includeDeleted' => $includeDeleted,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }


    /* ============================================================
     * 5. 검색
     * ============================================================ */
    public function search(array $filters): array
    {
        $this->logger->info('search() called', [
            'filters' => $filters
        ]);

        try {
            return $this->model->search($filters);

        } catch (\Throwable $e) {
            $this->logger->error('search() exception', [
                'filters'   => $filters,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }
    

    /* ============================================================
     * 7. 프로젝트 자동검색 (입력 자동완성)
     * ============================================================ */
    public function searchPicker(string $keyword): array
    {
        $this->logger->info('searchProject() called', [
            'keyword' => $keyword
        ]);

        try {
            return $this->model->searchPicker($keyword);

        } catch (\Throwable $e) {
            $this->logger->error('searchProject() exception', [
                'keyword'   => $keyword,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }



    /* ============================================================
     * 3. 저장 (신규 + 수정 통합)
     * ============================================================ */
    public function save(array $data, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('save() called', [
            'mode'      => !empty($data['id']) ? 'UPDATE' : 'INSERT',
            'id'        => $data['id'] ?? null,
            'code'      => $data['code'] ?? null,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);

        try {

            $id = $data['id'] ?? null;

            /* ---------------- UPDATE ---------------- */
            if ($id) {

                $before = $this->model->getById($id);

                if (!$before) {
                    return [
                        'success' => false,
                        'message' => '존재하지 않는 프로젝트입니다.'
                    ];
                }

                $data['updated_by'] = $actor;

                if (!$this->model->updateById($id, $data)) {
                    return [
                        'success' => false,
                        'message' => '프로젝트 수정 실패'
                    ];
                }

                return [
                    'success' => true,
                    'id'      => $id
                ];
            }

            /* ---------------- INSERT ---------------- */
            $newId   = UuidHelper::generate();
            $newCode = CodeHelper::generateProjectCode($this->pdo);

            $insertData = array_merge($data, [
                'id'         => $newId,
                'code'       => $newCode,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            if (!$this->model->create($insertData)) {
                return [
                    'success' => false,
                    'message' => '프로젝트 등록 실패'
                ];
            }

            return [
                'success' => true,
                'id'      => $newId,
                'code'    => $newCode
            ];

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* ============================================================
    * 4. 삭제 (id 기준으로 통일)
    * ============================================================ */
    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('delete() called', [
            'id' => $id,
            'actor' => $actor
        ]);

        try {

            $item = $this->model->getById($id);

            if (!$item) {
                return [
                    'success' => false,
                    'message' => '존재하지 않는 프로젝트입니다.'
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {
                return [
                    'success' => false,
                    'message' => '프로젝트 삭제 실패'
                ];
            }

            return ['success' => true];

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }




    /* =========================================================
     * 8. 휴지통 목록
     * ========================================================= */
    public function getTrashList(): array
    {
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

    /* =========================================================
     * 9. 복원
     * ========================================================= */
    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('restore() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
    
        try {
    
            $project = $this->model->getById($id);
    
            if (!$project) {
                return [
                    'success' => false,
                    'message' => '존재하지 않는 프로젝트입니다.'
                ];
            }
    
            $ok = $this->model->restoreById($id, $actor);
    
            return [
                'success' => $ok
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

    /* =========================================================
     * 10. 완전삭제
     * ========================================================= */
    public function purge(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('purge() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
    
        try {
    
            $project = $this->model->getById($id);
    
            if (!$project) {
                return [
                    'success' => false,
                    'message' => '존재하지 않는 프로젝트입니다.'
                ];
            }
    
            $ok = $this->model->hardDeleteById($id);
    
            return [
                'success' => $ok
            ];
    
        } catch (\Throwable $e) {
    
            $this->logger->error('purge() exception', [
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


    /* =========================================================
    선택 복원
    ========================================================= */
    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('restoreBulk() called', [
            'ids'       => $ids,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
    
        if (empty($ids)) {
            return [
                'success' => false,
                'message' => '복원할 프로젝트가 없습니다.'
            ];
        }
    
        $ok = $this->model->restoreBulkByIds($ids, $actor);
    
        return [
            'success' => $ok,
            'message' => $ok ? '선택 복원 완료' : '선택 복원 실패'
        ];
    }

    /* =========================================================
    선택 완전삭제
    ========================================================= */
    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('purgeBulk() called', [
            'ids'       => $ids,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
    
        if (empty($ids)) {
            return [
                'success' => false,
                'message' => '삭제할 프로젝트가 없습니다.'
            ];
        }
    
        $ok = $this->model->hardDeleteBulkByIds($ids);
    
        return [
            'success' => $ok,
            'message' => $ok ? '선택 삭제 완료' : '선택 삭제 실패'
        ];
    }

    /* =========================================================
    전체 완전삭제
    ========================================================= */
    public function purgeAll(string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('purgeAll() called', [
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
    
        try {
    
            $ok = $this->model->hardDeleteAllDeleted();
    
            return [
                'success' => $ok,
                'message' => $ok ? '전체 삭제 완료' : '전체 삭제 실패'
            ];
    
        } catch (\Throwable $e) {
    
            $this->logger->error('purgeAll() exception', [
                'exception' => $e->getMessage()
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }




    /* ============================================================
     * 6. 코드 순서 변경 (RowReorder)
     * ============================================================ */
    public function reorder(array $changes): bool
    {
        $this->logger->info('reorder() called', [
            'changes' => $changes
        ]);

        $this->pdo->beginTransaction();

        try {
            /* 1️⃣ 임시 코드 이동 (충돌 방지) */
            foreach ($changes as $row) {
                $tempCode = (int)$row['newCode'] + 10000;
                $this->model->updateCode($row['id'], $tempCode);
            }

            /* 2️⃣ 실제 코드 적용 */
            foreach ($changes as $row) {
                $this->model->updateCode($row['id'], (string)$row['newCode']);
            }

            $this->pdo->commit();

            $this->logger->info('reorder() success');

            return true;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('reorder() failed', [
                'exception' => $e->getMessage()
            ]);

            throw $e;
        }
    }


    /* ============================================================
    * 7. 엑셀 업로드 저장
    * ============================================================ */
    public function saveFromExcel(array $data, string $actorType = 'SYSTEM_EXCEL_UPLOAD'): bool
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('saveFromExcel() called', [
            'actorType' => $actorType,
            'actor'     => $actor,
            'data'      => $data
        ]);
    
        $data['id'] = UuidHelper::generate();
    
        if (empty($data['code'])) {
            $data['code'] = CodeHelper::generateProjectCode($this->pdo);
        }
    
        $data['created_by'] = $actor;
        $data['updated_by'] = $actor;
    
        $data['project_name'] = trim($data['project_name'] ?? '');
        if ($data['project_name'] === '') {
            throw new \Exception('프로젝트명 없음');
        }
    
        if (!empty($data['initial_contract_amount'])) {
            $data['initial_contract_amount'] = (float) str_replace(',', '', $data['initial_contract_amount']);
        }
    
        return $this->model->saveFromExcel($data);
    }

}