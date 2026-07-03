<?php

namespace App\Services\System;

use App\Models\System\ClientModel;
use App\Services\File\FileService;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PDO;

class ClientTrashService
{
    private readonly PDO $pdo;
    private ClientModel $model;
    private FileService $fileService;
    private $logger;

    public function __construct(PDO $pdo, ClientModel $model, FileService $fileService)
    {
        $this->pdo = $pdo;
        $this->model = $model;
        $this->fileService = $fileService;
        $this->logger = LoggerFactory::getLogger('service-system.ClientTrashService');
    }

    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('delete() called', [
            'id' => $id,
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        try {
            $item = $this->model->getById($id);

            if (!$item) {
                $this->logger->warning('delete() not found', ['id' => $id]);
                return [
                    'success' => false,
                    'message' => '거래처 정보를 찾을 수 없습니다.',
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {
                $this->logger->error('delete() DB failed', [
                    'id' => $id,
                    'user' => $actor,
                ]);

                return [
                    'success' => false,
                    'message' => '삭제 중 오류가 발생했습니다.',
                ];
            }

            $this->logger->info('delete() success', ['id' => $id]);

            return ['success' => true];
        } catch (\Throwable $e) {
            $this->logger->error('delete() exception', [
                'id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getTrashList(): array
    {
        $this->logger->info('getTrashList() called');

        try {
            return $this->model->getDeleted();
        } catch (\Throwable $e) {
            $this->logger->error('getTrashList() exception', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restore() called', [
            'id' => $id,
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        $client = $this->model->getById($id);

        if (!$client) {
            return [
                'success' => false,
                'message' => '거래처 정보를 찾을 수 없습니다.',
            ];
        }

        $ok = $this->model->restoreById($id, $actor);

        return [
            'success' => $ok,
        ];
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreBulk() called', [
            'ids' => $ids,
            'actor' => $actor,
        ]);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID가 올바르지 않습니다.'];
        }

        $this->pdo->beginTransaction();

        try {
            $success = 0;

            foreach ($ids as $id) {
                $ok = $this->model->restoreById($id, $actor);

                if ($ok) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "복원 완료 ({$success}건)",
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('restoreBulk() failed', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreAll() called', [
            'actor' => $actor,
        ]);

        $this->pdo->beginTransaction();

        try {
            $rows = $this->model->getDeleted();
            $success = 0;

            foreach ($rows as $row) {
                $ok = $this->model->restoreById($row['id'], $actor);

                if ($ok) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "전체 복원 완료 ({$success}건)",
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purge() called', [
            'id' => $id,
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        $client = $this->model->getById($id);

        if (!$client) {
            return [
                'success' => false,
                'message' => '거래처 정보를 찾을 수 없습니다.',
            ];
        }

        $this->pdo->beginTransaction();

        try {
            if (!empty($client['business_certificate'])) {
                $this->fileService->delete($client['business_certificate']);

                $this->logger->info('business_certificate deleted', [
                    'path' => $client['business_certificate'],
                ]);
            }
            if (!empty($client['rrn_image'])) {
                $this->fileService->delete($client['rrn_image']);

                $this->logger->info('rrn_image deleted', [
                    'path' => $client['rrn_image'],
                ]);
            }
            if (!empty($client['bank_file'])) {
                $this->fileService->delete($client['bank_file']);

                $this->logger->info('bank_file deleted', [
                    'path' => $client['bank_file'],
                ]);
            }

            $ok = $this->model->hardDeleteById($id);

            if (!$ok) {
                throw new \Exception('영구삭제 중 오류가 발생했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('purge() failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '영구삭제 중 오류가 발생했습니다.',
            ];
        }
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'ID가 올바르지 않습니다.'];
        }

        $this->pdo->beginTransaction();

        try {
            $success = 0;

            foreach ($ids as $id) {
                $client = $this->model->getById($id);

                if (!$client) {
                    continue;
                }

                if (!empty($client['business_certificate'])) {
                    $this->fileService->delete($client['business_certificate']);

                    $this->logger->info('business_certificate deleted', [
                        'id' => $id,
                        'path' => $client['business_certificate'],
                    ]);
                }

                if (!empty($client['rrn_image'])) {
                    $this->fileService->delete($client['rrn_image']);

                    $this->logger->info('rrn_image deleted', [
                        'id' => $id,
                        'path' => $client['rrn_image'],
                    ]);
                }

                if (!empty($client['bank_file'])) {
                    $this->fileService->delete($client['bank_file']);

                    $this->logger->info('bank_file deleted', [
                        'id' => $id,
                        'path' => $client['bank_file'],
                    ]);
                }

                $ok = $this->model->hardDeleteById($id);

                if ($ok) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "영구삭제 완료 ({$success}건)",
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('purgeBulk() failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->pdo->beginTransaction();

        try {
            $rows = $this->model->getDeleted();
            $success = 0;

            foreach ($rows as $row) {
                if (!empty($row['business_certificate'])) {
                    $this->fileService->delete($row['business_certificate']);

                    $this->logger->info('business_certificate deleted', [
                        'id' => $row['id'],
                        'path' => $row['business_certificate'],
                    ]);
                }
                if (!empty($row['rrn_image'])) {
                    $this->fileService->delete($row['rrn_image']);

                    $this->logger->info('rrn_image deleted', [
                        'id' => $row['id'],
                        'path' => $row['rrn_image'],
                    ]);
                }
                if (!empty($row['bank_file'])) {
                    $this->fileService->delete($row['bank_file']);

                    $this->logger->info('bank_file deleted', [
                        'id' => $row['id'],
                        'path' => $row['bank_file'],
                    ]);
                }

                $ok = $this->model->hardDeleteById($row['id']);

                if ($ok) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "전체 영구삭제 완료 ({$success}건)",
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            $this->logger->error('purgeAll() failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function reorder(array $changes): bool
    {
        $this->logger->info('reorder() called', [
            'changes' => $changes,
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
                    throw new \Exception('reorder 데이터가 올바르지 않습니다.');
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
                'changes' => $changes,
            ]);

            throw $e;
        }
    }
}
