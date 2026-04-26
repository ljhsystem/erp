<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Services/System/CoverImageService.php'
namespace App\Services\System;

use PDO;
use App\Models\System\CoverImageModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
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
     * 愿由ъ옄 紐⑸줉 議고쉶
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
     * 공개??紐⑸줉 議고쉶(?ъ슜?먯슜)
     * ============================================================ */
    public function getPublicList(): array
    {
        $this->logger->info('getPublicList() called');

        $rows = $this->model->getPublicList();

        return array_map(function ($row) {
            return [
                'id'          => $row['id'] ?? null,
                'sort_no'        => $row['sort_no'] ?? null,
                'year'        => $row['year'],
                'title'       => $row['title'],
                'alt'         => $row['alt'],
                'description' => $row['description'],
                'url'         => storage_to_url($row['src']),
            ];
        }, $rows);
    }

    /* ============================================================
     * ?④굔 議고쉶
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
     * ???(?좉퇋 + ?섏젙)
     * ============================================================ */
    public function save(array $data): array
    {
        $actor = ActorHelper::user();

        $this->logger->info('save() called', [
            'cover_id' => $data['id'] ?? null
        ]);

        try {
            $coverId = trim((string)($data['id'] ?? ''));
            $year = trim((string)($data['year'] ?? ''));
            $title = trim((string)($data['title'] ?? ''));
            $alt = trim((string)($data['alt'] ?? ''));
            $description = trim((string)($data['description'] ?? ''));
            $isActive = ((int)($data['is_active'] ?? 1)) === 1 ? 1 : 0;
            $newSrc  = null;

            if (!preg_match('/^\d{4}$/', $year)) {
                return [
                    'success' => false,
                    'message' => '?대떦?꾨룄??4?먮━ ?도??력?주?요.'
                ];
            }

            if ($title === '') {
                return [
                    'success' => false,
                    'message' => '?쒕ぉ???낅젰?댁＜?몄슂.'
                ];
            }

            if ($alt === '') {
                return [
                    'success' => false,
                    'message' => '?대?吏 臾멸뎄(Alt)瑜??낅젰?댁＜?몄슂.'
                ];
            }

            if (mb_strlen($title) > 120) {
                return [
                    'success' => false,
                    'message' => '?쒕ぉ? 120???하??력?주?요.'
                ];
            }

            if (mb_strlen($alt) > 180) {
                return [
                    'success' => false,
                    'message' => '?대?吏 臾멸뎄(Alt)??180???하??력?주?요.'
                ];
            }

            if ($description !== '' && mb_strlen($description) > 500) {
                return [
                    'success' => false,
                    'message' => '?ㅻ챸? 500???하??력?주?요.'
                ];
            }

            /* =========================
               1. ?뚯씪 ?낅줈??
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
                        'message' => $upload['message'] ?? '?뚯씪 ?낅줈???ㅽ뙣'
                    ];
                }

                $newSrc = $upload['db_path'];
            }

            /* =========================
               2. ?좉퇋??寃쎌슦 ?대?吏 ?수
            ========================= */
            if ($coverId === '' && !$newSrc) {
                return [
                    'success' => false,
                    'message' => '?대?吏???수?니??'
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
                        'message' => '議댁옱?섏? ?딅뒗 ??ぉ?낅땲??'
                    ];
                }

                $updateData = [
                    'year'        => $year,
                    'title'       => $title,
                    'alt'         => $alt,
                    'description' => $description,
                    'src'         => $newSrc ?: ($before['src'] ?? null),
                    'is_active'   => $isActive,
                    'updated_by'  => $actor,
                ];

                if (!$this->model->updateById($coverId, $updateData)) {
                    return [
                        'success' => false,
                        'message' => 'DB ?낅뜲?댄듃 ?ㅽ뙣'
                    ];
                }

                // 湲곗〈 ?뚯씪 ??젣
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
            $newSortNo = $this->getNextSortNo();

            $insertData = [
                'id'          => $newId,
                'sort_no'        => $newSortNo,
                'year'        => $year,
                'title'       => $title,
                'alt'         => $alt,
                'description' => $description,
                'src'         => $newSrc, // ?뵦 null ?님 (?에???수 泥댄겕??
                'is_active'   => $isActive,
                'created_by'  => $actor,
                'updated_by'  => $actor,
            ];

            if (!$this->model->create($insertData)) {
                return [
                    'success' => false,
                    'message' => 'DB INSERT ?ㅽ뙣'
                ];
            }

            DataHelper::resequenceCoverImageCodes($this->pdo);

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

    private function getNextSortNo(): int
    {
        $stmt = $this->pdo->query("
            SELECT COALESCE(MAX(sort_no), 0) + 1
            FROM system_coverimage_assets
        ");

        return (int)($stmt?->fetchColumn() ?: 1);
    }

    /* ============================================================
     * ??젣 ???댁????대룞
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
                    'message' => '??ぉ??議댁옱?섏? ?딆뒿?덈떎.'
                ];
            }

            if (!empty($item['deleted_at'])) {
                return [
                    'success' => false,
                    'message' => '?대? ?댁??듭뿉 ?덉뒿?덈떎.'
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {
                return [
                    'success' => false,
                    'message' => '?댁????대룞 ?ㅽ뙣'
                ];
            }

            DataHelper::resequenceCoverImageCodes($this->pdo);

            return [
                'success' => true,
                'message' => '?댁??듭쑝濡??대룞?섏뿀?듬땲??'
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
     * ?댁???紐⑸줉 議고쉶
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
     * 蹂듭썝
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
                    'message' => '??ぉ??議댁옱?섏? ?딆뒿?덈떎.'
                ];
            }

            if (empty($item['deleted_at'])) {
                return [
                    'success' => false,
                    'message' => '?대? 蹂듭썝????ぉ?낅땲??'
                ];
            }

            if ($this->model->restoreById($id, $actor)) {

                return [
                    'success' => true,
                    'message' => '蹂듭썝 ?꾨즺'
                ];
            }

            return [
                'success' => false,
                'message' => '蹂듭썝 ?ㅽ뙣'
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
            return ['success' => false, 'message' => 'ID ?놁쓬'];
        }

        $this->pdo->beginTransaction();

        try {

            $success = 0;

            foreach ($ids as $id) {

                $res = $this->restore($id);

                if ($res['success'] ?? false) {
                    $success++;
                } else {
                    throw new \Exception("蹂듭썝 ?ㅽ뙣: {$id}");
                }
            }

            DataHelper::resequenceCoverImageCodes($this->pdo);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "蹂듭썝 ?꾨즺 ({$success}嫄?"
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
                'message' => "?체 복원 ?료 ({$success}嫄?"
            ];

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* ============================================================
     * ?섎뱶??젣
     * ============================================================ */
    public function purge(string $id): array
    {
        $this->logger->info('purge() called', ['id' => $id]);

        try {
            $item = $this->model->getById($id);

            if (!$item) {
                return [
                    'success' => false,
                    'message' => '??ぉ??議댁옱?섏? ?딆뒿?덈떎.'
                ];
            }

            if ($this->model->hardDeleteById($id)) {

                if (!empty($item['src'])) {
                    $this->fileService->delete($item['src']);
                }

                DataHelper::resequenceCoverImageCodes($this->pdo);

                return [
                    'success' => true,
                    'message' => '??젣 ?꾨즺'
                ];
            }

            return [
                'success' => false,
                'message' => 'DB ?섎뱶??젣 ?ㅽ뙣'
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
            return ['success' => false, 'message' => 'ID ?놁쓬'];
        }

        $this->pdo->beginTransaction();

        try {

            $success = 0;
            $filesToDelete = [];

            foreach ($ids as $id) {

                $item = $this->model->getById($id);

                if (!$item) {
                    throw new \Exception("??ぉ ?놁쓬: {$id}");
                }

                if (!$this->model->hardDeleteById($id)) {
                    throw new \Exception("??젣 ?ㅽ뙣: {$id}");
                }

                if (!empty($item['src'])) {
                    $filesToDelete[] = $item['src'];
                }

                $success++;
            }

            DataHelper::resequenceCoverImageCodes($this->pdo);

            $this->pdo->commit();

            // ?뵦 ?몃옖??뀡 諛뽰뿉???뚯씪 ??젣
            foreach ($filesToDelete as $src) {
                $this->fileService->delete($src);
            }

            return [
                'success' => true,
                'message' => "??젣 ?꾨즺 ({$success}嫄?"
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
    ?체 ?전??
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

                // 1截뤴깵 ?뚯씪 ??젣
                if (!empty($row['src'])) {
                    $this->fileService->delete($row['src']);
                }

                // 2截뤴깵 DB ?④굔 ??젣 (?뵦 洹쒖튃 以??
                $this->model->hardDeleteById($row['id']);
            }

            $this->pdo->commit();

            DataHelper::resequenceCoverImageCodes($this->pdo);

            return [
                'success' => true,
                'message' => '?체 ?? ?료'
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



    /* ============================================================
    * 肄붾뱶 ?쒖꽌 蹂寃?(RowReorder)
    * ============================================================ */
    public function reorder(array $changes): bool
    {
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

            /* 1截뤴깵 ?낅젰媛?寃利?*/
            foreach ($changes as $row) {

                if (
                    empty($row['id']) ||
                    !isset($row['newSortNo'])
                ) {
                    throw new \Exception('reorder ?곗씠???ㅻ쪟');
                }
            }

            /* 2截뤴깵 temp ?대룞 (異⑸룎 諛⑹?) */
            foreach ($changes as $row) {

                // ?몛 ?됰꼮?섍쾶 (?덈? 異⑸룎 ?덈굹寃?
                $tempSortNo = (int)$row['newSortNo'] + 1000000;

                $this->model->updateSortNo(
                    $row['id'],
                    $tempSortNo
                );
            }

            /* 3截뤴깵 ?ㅼ젣 肄붾뱶 ?곸슜 */
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



}
