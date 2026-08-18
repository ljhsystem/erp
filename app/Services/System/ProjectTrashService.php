<?php

namespace App\Services\System;

use PDO;
use App\Models\System\ProjectModel;
use App\Repositories\System\ProjectDependencyRepository;
use Core\Helpers\ActorHelper;

class ProjectTrashService
{
    private readonly ProjectDependencyRepository $dependencyRepository;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ProjectModel $model,
        private readonly mixed $logger
    ) {
        $this->dependencyRepository = new ProjectDependencyRepository($pdo);
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
                'message' => '삭제 중 오류가 발생했습니다.',
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
                'message' => '복구 중 오류가 발생했습니다.',
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
                'message' => '복구 중 오류가 발생했습니다.',
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
                'message' => '복구 중 오류가 발생했습니다.',
            ];
        }
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        $result = $this->purgeProjects([$id]);
        return ($result['deleted_count'] ?? 0) > 0 ? $result : [...$result, 'success' => false];
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        return $this->purgeProjects($ids);
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        return $this->purgeProjects(array_column($this->model->getDeleted(), 'id'));
    }

    private function purgeProjects(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn($id): string => trim((string) $id), $ids))));
        $deletedCount = 0;
        $blocked = [];
        if ($ids === []) return $this->purgeResult(0, 0, []);

        $this->pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $project = $this->model->getById($id);
                if (!$project || empty($project['deleted_at'])) {
                    $blocked[] = ['id' => $id, 'references' => ['휴지통에 없는 프로젝트']];
                    continue;
                }
                $references = $this->dependencyRepository->findReferences($id);
                if ($references !== []) {
                    $blocked[] = ['id' => $id, 'references' => array_column($references, 'label')];
                    continue;
                }
                if (!$this->model->hardDeleteById($id)) throw new \RuntimeException('프로젝트 영구삭제 DB 처리 실패');
                $deletedCount++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->logger->error('purgeProjects() failed', ['exception' => $e->getMessage(), 'ids' => $ids]);
            return ['success' => false, 'message' => '영구삭제 중 오류가 발생했습니다.', 'deleted_count' => 0, 'skipped_count' => count($ids), 'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)]];
        }
        return $this->purgeResult($deletedCount, count($blocked), $blocked);
    }

    private function purgeResult(int $deletedCount, int $skippedCount, array $blocked): array
    {
        if ($deletedCount === 0 && $skippedCount > 0) {
            $labels = array_values(array_unique(array_merge(...array_column($blocked, 'references'))));
            $message = '다른 업무에서 사용 중인 프로젝트이므로 영구삭제할 수 없습니다.' . ($labels === [] ? '' : ' 참조 업무: ' . implode(', ', $labels) . '.');
        } elseif ($skippedCount > 0) $message = "프로젝트 {$deletedCount}건을 영구삭제했고, 사용 중인 {$skippedCount}건은 유지했습니다.";
        elseif ($deletedCount > 0) $message = "프로젝트 {$deletedCount}건을 영구삭제했습니다.";
        else $message = '영구삭제할 프로젝트가 없습니다.';
        $data = ['deleted_count' => $deletedCount, 'skipped_count' => $skippedCount, 'blocked' => $blocked];
        return ['success' => true, 'message' => $message, ...$data, 'data' => $data];
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
                    throw new \Exception('순서 변경 데이터가 올바르지 않습니다.');
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
