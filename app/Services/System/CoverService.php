<?php
namespace App\Services\System;

use PDO;
use App\Models\System\CoverModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\ActorHelper;
use Core\Helpers\DataHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;
use function Core\storage_to_url;

class CoverService
{
    private readonly PDO $pdo;
    private FileService $fileService;
    private CoverModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->fileService = new FileService($pdo);
        $this->model       = new CoverModel();
        $this->logger      = LoggerFactory::getLogger('service-system.CoverService');

        $this->logger->info('SystemCoverService initialized');
    }

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

    public function getPublicList(): array
    {
        $this->logger->info('getPublicList() called');

        $rows = $this->model->getPublicList();

        return array_map(function ($row) {
            return [
                'id'          => $row['id'] ?? null,
                'sort_no'     => $row['sort_no'] ?? null,
                'year'        => $row['year'],
                'title'       => $row['title'],
                'alt'         => $row['alt'],
                'description' => $row['description'],
                'url'         => storage_to_url($row['src']),
            ];
        }, $rows);
    }

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

    public function save(array $data): array
    {
        $actor = ActorHelper::user();

        $this->logger->info('save() called', [
            'cover_id' => $data['id'] ?? null
        ]);

        try {
            $coverId = trim((string) ($data['id'] ?? ''));
            $year = trim((string) ($data['year'] ?? ''));
            $title = trim((string) ($data['title'] ?? ''));
            $alt = trim((string) ($data['alt'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));
            $isActive = ((int) ($data['is_active'] ?? 1)) === 1 ? 1 : 0;
            $newSrc  = null;

            if (!preg_match('/^\d{4}$/', $year)) {
                return [
                    'success' => false,
                    'message' => '연도는 4자리 숫자로 입력해주세요.'
                ];
            }

            if ($title === '') {
                return [
                    'success' => false,
                    'message' => '제목을 입력해주세요.'
                ];
            }

            if ($alt === '') {
                return [
                    'success' => false,
                    'message' => '대체 텍스트(Alt)를 입력해주세요.'
                ];
            }

            if (mb_strlen($title) > 120) {
                return [
                    'success' => false,
                    'message' => '제목은 120자 이하로 입력해주세요.'
                ];
            }

            if (mb_strlen($alt) > 180) {
                return [
                    'success' => false,
                    'message' => '대체 텍스트(Alt)는 180자 이하로 입력해주세요.'
                ];
            }

            if ($description !== '' && mb_strlen($description) > 500) {
                return [
                    'success' => false,
                    'message' => '설명은 500자 이하로 입력해주세요.'
                ];
            }

            if (!empty($data['file']) && (int) ($data['file']['error'] ?? 4) === UPLOAD_ERR_OK) {

                $upload = $this->fileService->upload(
                    $data['file'],
                    'public://covers',
                    ['jpg', 'jpeg', 'png', 'webp'],
                    10 * 1024 * 1024
                );

                if (empty($upload['success'])) {
                    return [
                        'success' => false,
                        'message' => $upload['message'] ?? '파일 업로드 중 오류가 발생했습니다.'
                    ];
                }

                $newSrc = $upload['db_path'];
            }

            if ($coverId === '' && !$newSrc) {
                return [
                    'success' => false,
                    'message' => '이미지를 등록해주세요.'
                ];
            }

            if ($coverId !== '') {

                $before = $this->model->getById($coverId);

                if (!$before) {
                    return [
                        'success' => false,
                        'message' => '대상 데이터를 찾을 수 없습니다.'
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
                        'message' => '수정 중 오류가 발생했습니다.'
                    ];
                }

                if ($newSrc && !empty($before['src'])) {
                    $this->fileService->delete($before['src']);
                }

                return [
                    'success' => true,
                    'id'      => $coverId
                ];
            }

            $newId   = UuidHelper::generate();
            $newSortNo = SequenceHelper::next('system_coverimage_assets', 'sort_no');

            $insertData = [
                'id'          => $newId,
                'sort_no'     => $newSortNo,
                'year'        => $year,
                'title'       => $title,
                'alt'         => $alt,
                'description' => $description,
                'src'         => $newSrc, // 신규 등록에서는 파일이 없으면 null이 들어간다.
                'is_active'   => $isActive,
                'created_by'  => $actor,
                'updated_by'  => $actor,
            ];

            if (!$this->model->create($insertData)) {
                return [
                    'success' => false,
                    'message' => '저장 중 오류가 발생했습니다.'
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
                    'message' => '대상 데이터를 찾을 수 없습니다.'
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
                    'message' => '삭제 중 오류가 발생했습니다.'
                ];
            }

            DataHelper::resequenceCoverImageCodes($this->pdo);

            return [
                'success' => true,
                'message' => '휴지통으로 이동했습니다.'
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

    public function restore(string $id): array
    {
        $actor = ActorHelper::user();
        $this->logger->info('restore() called', ['id' => $id]);

        try {
            $item = $this->model->getById($id);

            if (!$item) {
                return [
                    'success' => false,
                    'message' => '복구할 대상을 찾을 수 없습니다.'
                ];
            }

            if (empty($item['deleted_at'])) {
                return [
                    'success' => false,
                    'message' => '이미 복구된 상태입니다.'
                ];
            }

            if ($this->model->restoreById($id, $actor)) {

                return [
                    'success' => true,
                    'message' => '복구되었습니다.'
                ];
            }

            return [
                'success' => false,
                'message' => '복구 중 오류가 발생했습니다.'
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
            return ['success' => false, 'message' => 'ID가 비어 있습니다.'];
        }

        $this->pdo->beginTransaction();

        try {

            $success = 0;

            foreach ($ids as $id) {

                $res = $this->restore($id);

                if ($res['success'] ?? false) {
                    $success++;
                } else {
                    throw new \Exception("복구 중 오류가 발생했습니다.: {$id}");
                }
            }

            DataHelper::resequenceCoverImageCodes($this->pdo);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "복구되었습니다. ({$success}건)"
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
                'message' => "전체 복구 완료 ({$success}건)"
            ];

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function purge(string $id): array
    {
        $this->logger->info('purge() called', ['id' => $id]);

        try {
            $item = $this->model->getById($id);

            if (!$item) {
                return [
                    'success' => false,
                    'message' => '대상 데이터를 찾을 수 없습니다.'
                ];
            }

            if ($this->model->hardDeleteById($id)) {

                if (!empty($item['src'])) {
                    $this->fileService->delete($item['src']);
                }

                DataHelper::resequenceCoverImageCodes($this->pdo);

                return [
                    'success' => true,
                    'message' => '영구삭제되었습니다.'
                ];
            }

            return [
                'success' => false,
                'message' => '영구삭제 중 오류가 발생했습니다.'
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
            return ['success' => false, 'message' => 'ID가 비어 있습니다.'];
        }

        $this->pdo->beginTransaction();

        try {

            $success = 0;
            $filesToDelete = [];

            foreach ($ids as $id) {

                $item = $this->model->getById($id);

                if (!$item) {
                    throw new \Exception("대상 데이터가 없습니다.: {$id}");
                }

                if (!$this->model->hardDeleteById($id)) {
                    throw new \Exception("영구삭제 중 오류가 발생했습니다.: {$id}");
                }

                if (!empty($item['src'])) {
                    $filesToDelete[] = $item['src'];
                }

                $success++;
            }

            DataHelper::resequenceCoverImageCodes($this->pdo);

            $this->pdo->commit();

            foreach ($filesToDelete as $src) {
                $this->fileService->delete($src);
            }

            return [
                'success' => true,
                'message' => "영구삭제되었습니다. ({$success}건)"
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

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

                if (!empty($row['src'])) {
                    $this->fileService->delete($row['src']);
                }

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
                    throw new \Exception('reorder 입력값 오류');
                }
            }

            foreach ($changes as $row) {

                $tempSortNo = (int) $row['newSortNo'] + 1000000;

                $this->model->updateSortNo(
                    $row['id'],
                    $tempSortNo
                );
            }

            foreach ($changes as $row) {

                $this->model->updateSortNo(
                    $row['id'],
                    (int) $row['newSortNo']
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
