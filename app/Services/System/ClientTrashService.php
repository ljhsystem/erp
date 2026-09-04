<?php

namespace App\Services\System;

use App\Models\System\ClientModel;
use App\Repositories\System\ClientDependencyRepository;
use App\Services\File\FileService;
use App\Services\Concerns\LogsServiceOperations;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PDO;

class ClientTrashService
{
    use LogsServiceOperations;
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
        return $this->loggedTrashMutation('거래처 삭제','CLIENT_DELETE','delete',fn():array=>$this->deleteInternal($id,$actorType));
    }

    private function deleteInternal(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('거래처 삭제를 시작합니다.', [
            'id' => $id,
            'actorType' => $actorType,
            'actor' => $actor,
        ]);

        try {
            $item = $this->model->getById($id);

            if (!$item) {
                $this->logger->warning('삭제할 거래처를 찾을 수 없습니다.', ['id' => $id]);
                return [
                    'success' => false,
                    'message' => '거래처 정보를 찾을 수 없습니다.',
                ];
            }

            if (!$this->model->deleteById($id, $actor)) {
                $this->logger->error('거래처 삭제 저장에 실패했습니다.', [
                    'id' => $id,
                    'user' => $actor,
                ]);

                return [
                    'success' => false,
                    'message' => '삭제 중 오류가 발생했습니다.',
                ];
            }

            $this->logger->info('거래처를 삭제했습니다.', ['id' => $id]);

            return ['success' => true];
        } catch (\Throwable $e) {
            $this->logger->error('거래처 삭제 중 예외가 발생했습니다.', [
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
        $this->logger->info('삭제된 거래처 목록을 조회합니다.');

        try {
            return $this->model->getDeleted();
        } catch (\Throwable $e) {
            $this->logger->error('삭제된 거래처 목록 조회 중 예외가 발생했습니다.', [
                'error_code' => get_class($e),
                'error' => $e,
            ]);

            return [];
        }
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        return $this->loggedTrashMutation('거래처 복구','CLIENT_RESTORE','restore',fn():array=>$this->restoreInternal($id,$actorType));
    }

    private function restoreInternal(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('거래처 복구를 시작합니다.', [
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
        return $this->loggedTrashMutation('거래처 일괄복구','CLIENT_RESTORE_BULK','restore-bulk',fn():array=>$this->restoreBulkInternal($ids,$actorType));
    }

    private function restoreBulkInternal(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('거래처 일괄복구를 시작합니다.', [
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

            $this->logger->error('거래처 일괄복구에 실패했습니다.', [
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
        return $this->loggedTrashMutation('거래처 전체복구','CLIENT_RESTORE_ALL','restore-all',fn():array=>$this->restoreAllInternal($actorType));
    }

    private function restoreAllInternal(string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('거래처 전체복구를 시작합니다.', [
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
        return $this->loggedTrashMutation('거래처 영구삭제','CLIENT_PURGE','purge',function()use($id):array{
            $result=$this->purgeClients([$id]);
            return ($result['deleted_count']??0)>0?$result:[...$result,'success'=>false];
        });
    }

    public function purgeBulk(array $ids, string $actorType = 'USER'): array
    {
        return $this->loggedTrashMutation('거래처 일괄 영구삭제','CLIENT_PURGE_BULK','purge-bulk',fn():array=>$this->purgeClients($ids));
    }

    public function purgeAll(string $actorType = 'USER'): array
    {
        return $this->loggedTrashMutation('거래처 전체 영구삭제','CLIENT_PURGE_ALL','purge-all',fn():array=>$this->purgeClients(array_column($this->model->getDeleted(),'id')));
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
            $this->logger->error('거래처 영구삭제에 실패했습니다.', [
                'error_code' => get_class($e),
                'error' => $e,
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
                $this->logger->warning('영구삭제된 거래처 첨부파일 정리에 실패했습니다.', ['event_code' => 'CLIENT_FILE_CLEANUP_FAILED', 'result' => 'FAILED']);
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
        return $this->runLoggedOperation($this->logger,'거래처 정렬 저장','CLIENT_REORDER','reorder',['change_count'=>count($changes)],fn():bool=>$this->reorderInternal($changes),'info',false,static fn(bool $result):string=>$result?'SUCCESS':'BLOCKED');
    }

    private function reorderInternal(array $changes): bool
    {
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

            $this->logger->info('거래처 정렬을 저장했습니다.');

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('거래처 정렬 저장에 실패했습니다.', [
                'error_code' => get_class($e),
                'error' => $e,
                'change_count' => count($changes),
            ]);

            throw $e;
        }
    }
    private function loggedTrashMutation(string $label,string $eventCode,string $action,callable $operation): array
    {
        return $this->runLoggedOperation($this->logger,$label,$eventCode,$action,[],$operation,'info',false,
            static fn(array $result):string=>!empty($result['success'])?'SUCCESS':(str_contains((string)($result['message']??''),'오류')?'FAILED':'BLOCKED'));
    }
}
