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

        $this->logger->info('프로젝트 삭제를 시작합니다.', [
            'id' => $id,
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        try {
            $item = $this->model->getById($id);

            if (!$item) {
                $this->logger->warning('삭제할 프로젝트를 찾을 수 없습니다.', [
                    'id' => $id,
                ]);

                return [
                    'success' => false,
                    'message' => '삭제할 프로젝트를 찾을 수 없습니다.',
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {
                $this->logger->error('프로젝트 삭제 저장에 실패했습니다.', [
                    'id' => $id,
                    'actor' => $actor,
                ]);

                return [
                    'success' => false,
                    'message' => '삭제 중 오류가 발생했습니다.',
                ];
            }

            $this->logger->info('프로젝트를 삭제했습니다.', [
                'id' => $id,
            ]);

            return ['success' => true];
        } catch (\Throwable $e) {
            $this->logger->error('프로젝트 삭제 중 예외가 발생했습니다.', [
                'id' => $id,
                'error_code' => get_class($e),
                'error' => $e,
            ]);

            return [
                'success' => false,
                'message' => '삭제 중 오류가 발생했습니다.',
            ];
        }
    }

    public function getTrashList(): array
    {
        $this->logger->info('삭제된 프로젝트 목록을 조회합니다.');

        try {
            return $this->model->getDeleted();
        } catch (\Throwable $e) {
            $this->logger->error('삭제된 프로젝트 목록 조회 중 예외가 발생했습니다.', [
                'error_code' => get_class($e),
                'error' => $e,
            ]);

            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('프로젝트 복구를 시작합니다.', [
            'id' => $id,
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        try {
            $project = $this->model->getById($id);

            if (!$project) {
                $this->logger->warning('복구할 프로젝트를 찾을 수 없습니다.', [
                    'id' => $id,
                ]);

                return [
                    'success' => false,
                    'message' => '복구할 프로젝트를 찾을 수 없습니다.',
                ];
            }

            if (!$this->model->restoreById($id, $actor)) {
                $this->logger->error('프로젝트 복구 저장에 실패했습니다.', [
                    'id' => $id,
                    'actor' => $actor,
                ]);

                return [
                    'success' => false,
                    'message' => '복구 중 오류가 발생했습니다.',
                ];
            }

            $this->logger->info('프로젝트를 복구했습니다.', [
                'id' => $id,
            ]);

            return [
                'success' => true,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('프로젝트 복구 중 예외가 발생했습니다.', [
                'id' => $id,
                'actor' => $actor,
                'error_code' => get_class($e),
                'error' => $e,
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

        $this->logger->info('프로젝트 일괄복구를 시작합니다.', [
            'ids' => $ids,
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        if (empty($ids)) {
            $this->logger->warning('복구할 프로젝트를 선택하지 않았습니다.');

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
            $this->logger->error('프로젝트 일괄복구 중 예외가 발생했습니다.', [
                'ids' => $ids,
                'actor' => $actor,
                'error_code' => get_class($e),
                'error' => $e,
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

        $this->logger->info('프로젝트 전체복구를 시작합니다.', [
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
            $this->logger->error('프로젝트 전체복구 중 예외가 발생했습니다.', [
                'actor' => $actor,
                'error_code' => get_class($e),
                'error' => $e,
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
            $this->logger->error('프로젝트 영구삭제에 실패했습니다.', ['event_code' => 'PROJECT_PURGE_FAILED', 'result' => 'FAILED', 'error_code' => get_class($e), 'error' => $e]);
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
                $this->logger->info('프로젝트 정렬을 저장했습니다.');
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if ($this->logger) {
                $this->logger->error('프로젝트 정렬 저장에 실패했습니다.', [
                    'error_code' => get_class($e),
                    'error' => $e,
                    'change_count' => count($changes),
                ]);
            }

            throw $e;
        }
    }
}
