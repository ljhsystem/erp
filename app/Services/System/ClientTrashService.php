<?php

namespace App\Services\System;

use App\Models\System\ClientModel;
use App\Repositories\System\ClientDependencyRepository;
use App\Services\File\FileService;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PDO;

class ClientTrashService
{
    private readonly PDO $pdo;
    private ClientModel $model;
    private FileService $fileService;
    private ClientDependencyRepository $dependencyRepository;
    private $logger;

    public function __construct(PDO $pdo, ClientModel $model, FileService $fileService)
    {
        $this->pdo = $pdo;
        $this->model = $model;
        $this->fileService = $fileService;
        $this->dependencyRepository = new ClientDependencyRepository($pdo);
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
                'message' => '복구 중 오류가 발생했습니다.',
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
                'message' => '복구 중 오류가 발생했습니다.',
            ];
        }
    }

    public function purge(string $id, string $actorType = 'USER'): array
    {
        $this->logger->info('purge() called', ['id' => $id, 'actorType' => $actorType]);

        $result = $this->purgeClients([$id]);
        if (($result['deleted_count'] ?? 0) > 0) {
            return $result;
        }

        return [
            ...$result,
            'success' => false,
        ];
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        $this->logger->info('purgeBulk() called', ['ids' => $ids, 'actorType' => $actorType]);
        return $this->purgeClients($ids);
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        $this->logger->info('purgeAll() called', ['actorType' => $actorType]);
        return $this->purgeClients(array_column($this->model->getDeleted(), 'id'));
    }

    private function purgeClients(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn($id): string => trim((string) $id),
            $ids
        ))));
        if ($ids === []) {
            return $this->purgeResult(0, 0, []);
        }

        $deletedCount = 0;
        $blocked = [];
        $filesToDelete = [];

        $this->pdo->beginTransaction();

        try {
            foreach ($ids as $id) {
                $client = $this->model->getById($id);
                if (!$client || empty($client['deleted_at'])) {
                    $blocked[] = ['id' => $id, 'references' => ['휴지통에 없는 거래처']];
                    continue;
                }

                $references = $this->dependencyRepository->findReferences($id);
                if ($references !== []) {
                    $blocked[] = [
                        'id' => $id,
                        'references' => array_column($references, 'label'),
                    ];
                    continue;
                }

                if (!$this->model->hardDeleteById($id)) {
                    throw new \RuntimeException('거래처 영구삭제 DB 처리 실패');
                }

                foreach (['business_certificate', 'rrn_image', 'bank_file'] as $fileColumn) {
                    if (!empty($client[$fileColumn])) {
                        $filesToDelete[] = (string) $client[$fileColumn];
                    }
                }
                $deletedCount++;
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('purgeClients() failed', [
                'exception' => $e->getMessage(),
                'ids' => $ids,
            ]);

            return [
                'success' => false,
                'message' => '영구삭제 중 오류가 발생했습니다.',
                'deleted_count' => 0,
                'skipped_count' => count($ids),
                'data' => [
                    'deleted_count' => 0,
                    'skipped_count' => count($ids),
                ],
            ];
        }

        foreach ($filesToDelete as $file) {
            if (!$this->fileService->delete($file)) {
                $this->logger->warning('purged client attachment cleanup failed', ['path' => $file]);
            }
        }

        return $this->purgeResult($deletedCount, count($blocked), $blocked);
    }

    private function purgeResult(int $deletedCount, int $skippedCount, array $blocked): array
    {
        if ($deletedCount === 0 && $skippedCount > 0) {
            $labels = array_values(array_unique(array_merge(...array_column($blocked, 'references'))));
            $summary = $labels === [] ? '' : ' 참조 업무: ' . implode(', ', $labels) . '.';
            $message = '다른 업무에서 사용 중인 거래처이므로 영구삭제할 수 없습니다.' . $summary;
        } elseif ($skippedCount > 0) {
            $message = "거래처 {$deletedCount}건을 영구삭제했고, 사용 중인 {$skippedCount}건은 유지했습니다.";
        } elseif ($deletedCount > 0) {
            $message = "거래처 {$deletedCount}건을 영구삭제했습니다.";
        } else {
            $message = '영구삭제할 거래처가 없습니다.';
        }

        return [
            'success' => true,
            'message' => $message,
            'deleted_count' => $deletedCount,
            'skipped_count' => $skippedCount,
            'blocked' => $blocked,
            'data' => [
                'deleted_count' => $deletedCount,
                'skipped_count' => $skippedCount,
                'blocked' => $blocked,
            ],
        ];
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
