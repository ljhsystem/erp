<?php
// 경로: PROJECT_ROOT . '/app/Services/System/CoverImageService.php'
namespace App\Services\System;

use PDO;
use App\Models\System\CoverImageModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ActorHelper;
use Core\Helpers\DataHelper;
use Core\LoggerFactory;
use function Core\storage_to_url;

class CoverImageService
{
    private readonly PDO $pdo;
    private FileService $fileService;
    private CoverImageModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->fileService = new FileService($pdo);
        $this->model       = new CoverImageModel();
        $this->logger      = LoggerFactory::getLogger('service-system.CoverImageService');

        $this->logger->info('SystemCoverImageService initialized');
    }


    /* ============================================================
     * 관리자 목록 조회
     * ============================================================ */
    public function getList(array $filters = []): array
    {
        $this->logger->info('getAll() called', [
            'filters' => $filters
        ]);

        try {
            $rows = $this->model->getList($filters);

            $result = array_map(function ($row) {
                $row['url'] = !empty($row['src']) ? storage_to_url($row['src']) : null;
                return $row;
            }, $rows);

            $this->logger->info('getAll() success', [
                'count' => count($result)
            ]);

            return $result;

        } catch (\Throwable $e) {
            $this->logger->error('getAll() failed', [
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }
    /* ============================================================
     * 공개용 목록 조회(사용자용)
     * ============================================================ */
    public function getPublicList(): array
    {
        $this->logger->info('getPublicList() called');

        $rows = $this->model->getPublicList();

        return array_map(function ($row) {
            return [
                'id'          => $row['id'] ?? null,
                'code'        => $row['code'] ?? null,
                'year'        => $row['year'],
                'title'       => $row['title'],
                'alt'         => $row['alt'],
                'description' => $row['description'],
                'url'         => storage_to_url($row['src']),
            ];
        }, $rows);
    }
    
    /* ============================================================
     * 단건 조회
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

            $row['url'] = !empty($row['src']) ? storage_to_url($row['src']) : null;

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
     * 저장 (신규 + 수정)
     * ============================================================ */
    public function save(array $data): array
    {
        $actor = ActorHelper::user();
    
        $this->logger->info('save() called', [
            'cover_id' => $data['id'] ?? null
        ]);
    
        try {
            $coverId = trim((string)($data['id'] ?? ''));
            $newSrc  = null;
    
            /* =========================
               1. 파일 업로드
            ========================= */
            if (!empty($data['file']) && (int)($data['file']['error'] ?? 4) === UPLOAD_ERR_OK) {
    
                $upload = $this->fileService->upload(
                    $data['file'],
                    'public://covers',
                    ['jpg', 'jpeg', 'png', 'webp'],
                    10 * 1024 * 1024
                );
    
                if (empty($upload['success'])) {
                    return [
                        'success' => false,
                        'message' => $upload['message'] ?? '파일 업로드 실패'
                    ];
                }
    
                $newSrc = $upload['db_path'];
            }
    
            /* =========================
               2. 신규일 경우 이미지 필수
            ========================= */
            if ($coverId === '' && !$newSrc) {
                return [
                    'success' => false,
                    'message' => '이미지는 필수입니다.'
                ];
            }
    
            /* =========================
               3. UPDATE
            ========================= */
            if ($coverId !== '') {
    
                $before = $this->model->getById($coverId);
    
                if (!$before) {
                    return [
                        'success' => false,
                        'message' => '존재하지 않는 항목입니다.'
                    ];
                }
    
                $updateData = [
                    'year'        => $data['year'] ?? null,
                    'title'       => $data['title'] ?? null,
                    'alt'         => $data['alt'] ?? null,
                    'description' => $data['description'] ?? null,
                    'src'         => $newSrc ?: ($before['src'] ?? null),
                    'updated_by'  => $actor,
                ];
    
                if (!$this->model->updateById($coverId, $updateData)) {
                    return [
                        'success' => false,
                        'message' => 'DB 업데이트 실패'
                    ];
                }
    
                // 기존 파일 삭제
                if ($newSrc && !empty($before['src'])) {
                    $this->fileService->delete($before['src']);
                }
    
                return [
                    'success' => true,
                    'id'      => $coverId
                ];
            }
    
            /* =========================
               4. INSERT
            ========================= */
            $newId   = UuidHelper::generate();
            $newCode = CodeHelper::generateHomeAboutCoverImageCode($this->pdo);
    
            $insertData = [
                'id'          => $newId,
                'code'        => $newCode,
                'year'        => $data['year'] ?? null,
                'title'       => $data['title'] ?? null,
                'alt'         => $data['alt'] ?? null,
                'description' => $data['description'] ?? null,
                'src'         => $newSrc, // 🔥 null 아님 (위에서 필수 체크함)
                'created_by'  => $actor,
                'updated_by'  => $actor,
            ];
    
            if (!$this->model->create($insertData)) {
                return [
                    'success' => false,
                    'message' => 'DB INSERT 실패'
                ];
            }
    
            return [
                'success' => true,
                'id'      => $newId
            ];
    
        } catch (\Throwable $e) {
    
            $this->logger->error('save() exception', [
                'exception' => $e->getMessage()
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* ============================================================
     * 삭제 → 휴지통 이동
     * ============================================================ */
    public function delete(string $id): array
    {
        $actor = ActorHelper::user();
    
        $this->logger->info('delete() called', [
            'id' => $id,
            'deleted_by' => $actor
        ]);
    
    
        try {
            $item = $this->model->getById($id);
    
            if (!$item) {
                return [
                    'success' => false,
                    'message' => '항목이 존재하지 않습니다.'
                ];
            }
    
            if (!empty($item['deleted_at'])) {
                return [
                    'success' => false,
                    'message' => '이미 휴지통에 있습니다.'
                ];
            }
    
            if (!$this->model->deleteById($id, $actor)) {
                return [
                    'success' => false,
                    'message' => '휴지통 이동 실패'
                ];
            }
            
            DataHelper::resequenceCoverImageCodes($this->pdo);

            return [
                'success' => true,
                'message' => '휴지통으로 이동되었습니다.'
            ];
    
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





    /* ============================================================
     * 휴지통 목록 조회
     * ============================================================ */
    public function getTrashList(): array
    {
        $this->logger->info('getTrashList() called');

        try {
            $rows = $this->model->getDeleted();

            $result = array_map(function ($row) {
                $row['url'] = !empty($row['src']) ? storage_to_url($row['src']) : null;
                return $row;
            }, $rows);

            $this->logger->info('getTrashList() success', [
                'count' => count($result)
            ]);

            return $result;

        } catch (\Throwable $e) {
            $this->logger->error('getTrashList() failed', [
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }



    /* ============================================================
     * 복원
     * ============================================================ */
    public function restore(string $id): array
    {
        $actor = ActorHelper::user();
        $this->logger->info('restore() called', ['id' => $id]);
    
        try {
            $item = $this->model->getById($id);
    
            if (!$item) {
                return [
                    'success' => false,
                    'message' => '항목이 존재하지 않습니다.'
                ];
            }
    
            if (empty($item['deleted_at'])) {
                return [
                    'success' => false,
                    'message' => '이미 복원된 항목입니다.'
                ];
            }
    
            if ($this->model->restoreById($id, $actor)) {

                return [
                    'success' => true,
                    'message' => '복원 완료'
                ];
            }
            
            return [
                'success' => false,
                'message' => '복원 실패'
            ];
    
        } catch (\Throwable $e) {
            $this->logger->error('restore() exception', [
                'id' => $id,
                'exception' => $e->getMessage()
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function restoreBulk(array $ids): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID 없음'];
        }
    
        $this->pdo->beginTransaction();
    
        try {
    
            $success = 0;
    
            foreach ($ids as $id) {
    
                $res = $this->restore($id);
    
                if ($res['success'] ?? false) {
                    $success++;
                } else {
                    throw new \Exception("복원 실패: {$id}");
                }
            }
    
            DataHelper::resequenceCoverImageCodes($this->pdo);
    
            $this->pdo->commit();
    
            return [
                'success' => true,
                'message' => "복원 완료 ({$success}건)"
            ];
    
        } catch (\Throwable $e) {
    
            $this->pdo->rollBack();
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    public function restoreAll(): array
    {
        $actor = ActorHelper::user();
    
        $this->logger->info('restoreAll() called');
    
        try {
    
            $rows = $this->model->getDeleted();
    
            $success = 0;
    
            foreach ($rows as $row) {
    
                $ok = $this->model->restoreById($row['id'], $actor);
    
                if ($ok) {
                    $success++;
                }
            }
    
            DataHelper::resequenceCoverImageCodes($this->pdo);

            return [
                'success' => true,
                'message' => "전체 복원 완료 ({$success}건)"
            ];
    
        } catch (\Throwable $e) {
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* ============================================================
     * 하드삭제
     * ============================================================ */
    public function purge(string $id): array
    {
        $this->logger->info('purge() called', ['id' => $id]);

        try {
            $item = $this->model->getById($id);

            if (!$item) {
                return [
                    'success' => false,
                    'message' => '항목이 존재하지 않습니다.'
                ];
            }

            if ($this->model->hardDeleteById($id)) {

                if (!empty($item['src'])) {
                    $this->fileService->delete($item['src']);
                }
            
                DataHelper::resequenceCoverImageCodes($this->pdo);
            
                return [
                    'success' => true,
                    'message' => '삭제 완료'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'DB 하드삭제 실패'
            ];

        } catch (\Throwable $e) {
            $this->logger->error('purge() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function purgeBulk(array $ids): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID 없음'];
        }
    
        $this->pdo->beginTransaction();
    
        try {
    
            $success = 0;
            $filesToDelete = [];
    
            foreach ($ids as $id) {
    
                $item = $this->model->getById($id);
    
                if (!$item) {
                    throw new \Exception("항목 없음: {$id}");
                }
    
                if (!$this->model->hardDeleteById($id)) {
                    throw new \Exception("삭제 실패: {$id}");
                }
    
                if (!empty($item['src'])) {
                    $filesToDelete[] = $item['src'];
                }
    
                $success++;
            }
    
            DataHelper::resequenceCoverImageCodes($this->pdo);
    
            $this->pdo->commit();
    
            // 🔥 트랜잭션 밖에서 파일 삭제
            foreach ($filesToDelete as $src) {
                $this->fileService->delete($src);
            }
    
            return [
                'success' => true,
                'message' => "삭제 완료 ({$success}건)"
            ];
    
        } catch (\Throwable $e) {
    
            $this->pdo->rollBack();
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* =========================================================
    전체 완전삭제
    ========================================================= */
    public function purgeAll(string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);
    
        $this->logger->info('cover.purgeAll() called', [
            'actorType' => $actorType,
            'actor'     => $actor
        ]);
    
        $this->pdo->beginTransaction();
    
        try {
    
            $rows = $this->model->getDeleted();
    
            foreach ($rows as $row) {
    
                // 1️⃣ 파일 삭제
                if (!empty($row['src'])) {
                    $this->fileService->delete($row['src']);
                }
    
                // 2️⃣ DB 단건 삭제 (🔥 규칙 준수)
                $this->model->hardDeleteById($row['id']);
            }
    
            $this->pdo->commit();

            DataHelper::resequenceCoverImageCodes($this->pdo);
            
            return [
                'success' => true,
                'message' => '전체 삭제 완료'
            ];
    
        } catch (\Throwable $e) {
    
            $this->pdo->rollBack();
    
            $this->logger->error('cover.purgeAll() exception', [
                'exception' => $e->getMessage()
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    
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



}