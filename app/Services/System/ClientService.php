<?php
// 경로: PROJECT_ROOT . '/app/services/system/ClientService.php'
// 설명:
//  - 거래처(Client) 관리 서비스
//  - UUID / Code 생성은 Service 책임
//  - DB 처리: DashboardClientModel
//  - 모든 주요 흐름 LoggerFactory 적용
namespace App\Services\System;

use PDO;
use App\Models\System\SystemClientModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ActorHelper;

use Core\LoggerFactory;

class ClientService
{
    private readonly PDO $pdo;
    private SystemClientModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new SystemClientModel($this->pdo);
        $this->logger = LoggerFactory::getLogger('service-system.ClientService');

        $this->logger->info('ClientService initialized');
    }
    

    /* ============================================================
     * 1. 전체 목록 조회
     * ============================================================ */
    public function getList(): array
    {
        $this->logger->info('getList() called');

        try {
            $rows = $this->model->getList();

            $this->logger->info('getList() success', [
                'count' => count($rows)
            ]);

            return $rows;

        } catch (\Throwable $e) {
            $this->logger->error('getList() failed', [
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }

    /* ============================================================
     * 2. 단건 조회 (id 기준)
     * ============================================================ */ 
    public function getById(string $id): ?array
    {
        $this->logger->info('getById() called', ['id' => $id]);

        try {

            $row = $this->model->getById($id);

            if (!$row) {
                $this->logger->warning('getById() not found', ['id' => $id]);
                return null;
            }

            return $row;

        } catch (\Throwable $e) {

            $this->logger->error('getById() exception', [
                'id'        => $id,
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
    * 8. 거래처 자동검색 (입력 자동완성)
    * ============================================================ */
    public function searchPicker(string $keyword): array
    {
        $this->logger->info('searchClient() called', [
            'keyword' => $keyword
        ]);

        try {

            return $this->model->searchPicker($keyword);

        } catch (\Throwable $e) {

            $this->logger->error('searchClient() exception', [
                'keyword'   => $keyword,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

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
    
            $data = $this->normalize($data); 
            $id = $data['id'] ?? null;
    
            /* --------------------------------------------------------
             * UPDATE
             * -------------------------------------------------------- */
            if ($id) {
    
                $before = $this->model->getById($id);
    
                if (!$before) {
                    return [
                        'success' => false,
                        'message' => '존재하지 않는 거래처입니다.'
                    ];
                }
    
                $data['updated_by'] = $actor; 

                if (!$this->model->updateById($id, $data)) {
                    return [
                        'success' => false,
                        'message' => '거래처 수정 실패'
                    ];
                }
    
                return [
                    'success' => true,
                    'id'      => $id
                ];
            }
    
            /* --------------------------------------------------------
             * INSERT
             * -------------------------------------------------------- */
            $newId   = UuidHelper::generate();
            $newCode = CodeHelper::generateClientCode($this->pdo);
    
            $insertData = array_merge($data, [
                'id'         => $newId,
                'code'       => $newCode,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);
    
            if (!$this->model->create($insertData)) {
                return [
                    'success' => false,
                    'message' => '거래처 등록 실패'
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
     * 4. 삭제
     * ============================================================ */
    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('delete() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
    
        try {
    
            $item = $this->model->getById($id);
    
            if (!$item) {
                $this->logger->warning('delete() not found', ['id' => $id]);
                return [
                    'success' => false,
                    'message' => '존재하지 않는 거래처입니다.'
                ];
            }
    
            if (!$this->model->deleteById($id, $actor)) {
    
                $this->logger->error('delete() DB failed', [
                    'id'   => $id,
                    'user' => $actor
                ]);
    
                return [
                    'success' => false,
                    'message' => '거래처 삭제 실패'
                ];
            }
    
            $this->logger->info('delete() success', ['id' => $id]);
    
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

    /* =========================================================
    휴지통 목록
    ========================================================= */

    public function getTrashList(): array
    {
        return $this->model->getDeleted();
    }




    /* =========================================================
    복원
    ========================================================= */

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('restore() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
        
        $client = $this->model->getById($id);
    
        if (!$client) {
            return [
                'success' => false,
                'message' => '존재하지 않는 거래처입니다.'
            ];
        }
    
        $ok = $this->model->restoreById($id, $actor);
    
        return [
            'success' => $ok
        ];
    }

    /* =========================================================
    완전삭제 (id 기준)
    ========================================================= */
    public function purge(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('purge() called', [
            'id'        => $id,
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
        
        $client = $this->model->getById($id);

        if (!$client) {
            return [
                'success' => false,
                'message' => '존재하지 않는 거래처입니다.'
            ];
        }

        // 파일 삭제
        if (!empty($client['business_certificate'])) {
            // FileService delete
        }

        if (!empty($client['bank_copy'])) {
            // FileService delete
        }

        $ok = $this->model->hardDeleteById($id);

        return [
            'success' => $ok
        ];
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
                'message' => '복원할 거래처가 없습니다.'
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
                'message' => '삭제할 거래처가 없습니다.'
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

                $tempCode = $row['newCode'] + 10000;

                $this->model->updateCode(
                    $row['id'],
                    $tempCode
                );

            }

            /* 2️⃣ 실제 코드 적용 */

            foreach ($changes as $row) {

                $this->model->updateCode(
                    $row['id'],
                    $row['newCode']
                );

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
            $data['code'] = CodeHelper::generateClientCode($this->pdo);
        }
    
        $data['created_by'] = $actor;
        $data['updated_by'] = $actor;
    
        return $this->model->saveFromExcel($data);
    }



    public function findByName(string $name): ?array
    {
        return $this->model->findByName($name);
    }



    private function normalize(array $data): array
    {
        // 숫자 필드
        if (isset($data['business_number'])) {
            $data['business_number'] = preg_replace('/\D/', '', $data['business_number']);
        }
    
        // 빈값 → null
        foreach ($data as $k => $v) {
            if ($v === '') {
                $data[$k] = null;
            }
        }
    
        return $data;
    }




}
