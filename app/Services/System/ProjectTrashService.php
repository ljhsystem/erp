<?php

namespace App\Services\System;

use PDO;
use App\Models\System\ProjectModel;
use Core\Helpers\ActorHelper;

class ProjectTrashService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ProjectModel $model,
        private readonly mixed $logger
    ) {
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
                $this->logger->warning('delete() not found', [
                    'id' => $id,
                ]);

                return [
                    'success' => false,
                    'message' => '삭제할 프로젝트를 찾을 수 없습니다.',
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {
                $this->logger->error('delete() DB failed', [
                    'id' => $id,
                    'actor' => $actor,
                ]);

                return [
                    'success' => false,
                    'message' => '삭제 중 오류가 발생했습니다.',
                ];
            }

            $this->logger->info('delete() success', [
                'id' => $id,
            ]);

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

        try {
            $project = $this->model->getById($id);

            if (!$project) {
                $this->logger->warning('restore() not found', [
                    'id' => $id,
                ]);

                return [
                    'success' => false,
                    'message' => '복구할 프로젝트를 찾을 수 없습니다.',
                ];
            }

            if (!$this->model->restoreById($id, $actor)) {
                $this->logger->error('restore() DB failed', [
                    'id' => $id,
                    'actor' => $actor,
                ]);

                return [
                    'success' => false,
                    'message' => '복구 중 오류가 발생했습니다.',
                ];
            }

            $this->logger->info('restore() success', [
                'id' => $id,
            ]);

            return [
                'success' => true,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('restore() exception', [
                'id' => $id,
                'actor' => $actor,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('restoreBulk() called', [
            'ids' => $ids,
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        if (empty($ids)) {
            $this->logger->warning('restoreBulk() empty ids');

            return [
                'success' => false,
                'message' => '복구할 프로젝트 ID가 없습니다.',
            ];
        }

        try {
            $success = 0;

            foreach ($ids as $id) {
                if ($this->model->restoreById($id, $actor)) {
                    $success++;
                }
            }

            return [
                'success' => true,
                'message' => "선택 복구가 완료되었습니다. ({$success}건)",
            ];
        } catch (\Throwable $e) {
            $this->logger->error('restoreBulk() exception', [
                'ids' => $ids,
                'actor' => $actor,
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
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        try {
            $rows = $this->model->getDeleted();
            $success = 0;

            foreach ($rows as $row) {
                if ($this->model->restoreById($row['id'], $actor)) {
                    $success++;
                }
            }

            return [
                'success' => true,
                'message' => "전체 복구가 완료되었습니다. ({$success}건)",
            ];
        } catch (\Throwable $e) {
            $this->logger->error('restoreAll() exception', [
                'actor' => $actor,
                'exception' => $e->getMessage(),
            ]);

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

        try {
            $this->pdo->beginTransaction();

            $project = $this->model->getById($id);

            if (!$project) {
                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => '영구삭제할 프로젝트를 찾을 수 없습니다.',
                ];
            }

            $ok = $this->model->hardDeleteById($id);

            if (!$ok) {
                throw new \Exception('영구삭제 중 오류가 발생했습니다.');
            }

            $this->pdo->commit();

            $this->logger->info('purge() success', [
                'id' => $id,
            ]);

            return [
                'success' => true,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('purge() failed', [
                'id' => $id,
                'actor' => $actor,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purgeBulk() called', [
            'ids' => $ids,
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        if (empty($ids)) {
            $this->logger->warning('purgeBulk() empty ids');

            return [
                'success' => false,
                'message' => '영구삭제할 프로젝트 ID가 없습니다.',
            ];
        }

        try {
            $this->pdo->beginTransaction();
            $success = 0;

            foreach ($ids as $id) {
                if ($this->model->hardDeleteById($id)) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "선택 영구삭제가 완료되었습니다. ({$success}건)",
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('purgeBulk() failed', [
                'ids' => $ids,
                'actor' => $actor,
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

        $this->logger->info('purgeAll() called', [
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        try {
            $this->pdo->beginTransaction();

            $rows = $this->model->getDeleted();
            $count = count($rows);

            if ($count === 0) {
                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => '영구삭제할 프로젝트가 없습니다.',
                ];
            }

            $rows = $this->model->getDeleted();
            $success = 0;

            foreach ($rows as $row) {
                if ($this->model->hardDeleteById($row['id'])) {
                    $success++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "전체 영구삭제가 완료되었습니다. ({$success}건)",
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('purgeAll() failed', [
                'actor' => $actor,
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
        if ($this->logger) {
            $this->logger->info('reorder() called', [
                'changes' => $changes,
            ]);
        }

        if (empty($changes)) {
            return true;
        }

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as $row) {
                if (empty($row['id']) || !isset($row['newSortNo'])) {
                    throw new \Exception('reorder payload is invalid.');
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

            if ($this->logger) {
                $this->logger->info('reorder() success');
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($this->logger) {
                $this->logger->error('reorder() failed', [
                    'exception' => $e->getMessage(),
                    'changes' => $changes,
                ]);
            }

            throw $e;
        }
    }
}
