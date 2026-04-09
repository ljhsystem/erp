<?php
// 경로: PROJECT_ROOT . '/app/Services/System/BankAccountService.php'

namespace App\Services\System;

use PDO;
use Exception;

use App\Models\System\BankAccountModel;
use Core\LoggerFactory;
use Core\Helpers\ActorHelper;

class BankAccountService
{
    private PDO $pdo;
    private BankAccountModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new BankAccountModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.BankAccountService');

        $this->logger->info('BankAccountService initialized');
    }

    /* =========================================================
     * 목록
     * ========================================================= */
    public function getList(array $filters = []): array
    {
        $this->logger->info('getList() called', ['filters' => $filters]);

        $rows = $this->model->getList($filters);

        return [
            'success' => true,
            'data'    => $rows
        ];
    }

    /* =========================================================
     * 상세
     * ========================================================= */
    public function getById(string $id): array
    {
        $this->logger->info('getById()', ['id' => $id]);

        $row = $this->model->getById($id);

        if (!$row) {
            return ['success' => false, 'message' => '데이터 없음'];
        }

        return ['success' => true, 'data' => $row];
    }

    /* =========================================================
     * 저장 (생성 + 수정)
     * ========================================================= */
    public function save(array $data, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $id       = trim((string)($data['id'] ?? ''));
        $isCreate = ($id === '');

        $this->logger->info('save()', [
            'mode' => $isCreate ? 'CREATE' : 'UPDATE',
            'id'   => $id,
            'actor'=> $actor
        ]);

        try {
            $this->pdo->beginTransaction();

            if ($isCreate) {
                $id = $this->model->insert($data, $actor);
            } else {
                $this->model->update($id, $data, $actor);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '저장 완료',
                'id'      => $id
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            $this->logger->error('save() error', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* =========================================================
     * 삭제 (soft)
     * ========================================================= */
    public function delete(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('delete()', ['id' => $id]);

        $ok = $this->model->softDelete($id, $actor);

        return [
            'success' => $ok,
            'message' => $ok ? '삭제 완료' : '삭제 실패'
        ];
    }

    /* =========================================================
     * 휴지통
     * ========================================================= */
    public function getTrashList(): array
    {
        $rows = $this->model->getTrashList();

        return [
            'success' => true,
            'data'    => $rows
        ];
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $ok = $this->model->restore($id, $actor);

        return [
            'success' => $ok,
            'message' => $ok ? '복원 완료' : '복원 실패'
        ];
    }

    public function restoreBulk(array $ids, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $success = 0;

        foreach ($ids as $id) {
            if ($this->model->restore($id, $actor)) {
                $success++;
            }
        }

        return [
            'success' => true,
            'message' => "복원 완료 ({$success}건)"
        ];
    }

    public function restoreAll(string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $count = $this->model->restoreAll($actor);

        return [
            'success' => true,
            'message' => "전체 복원 ({$count}건)"
        ];
    }

    /* =========================================================
     * 완전삭제
     * ========================================================= */
    public function purge(string $id): array
    {
        $ok = $this->model->hardDelete($id);

        return [
            'success' => $ok,
            'message' => $ok ? '완전삭제 완료' : '실패'
        ];
    }

    public function purgeBulk(array $ids): array
    {
        $success = 0;

        foreach ($ids as $id) {
            if ($this->model->hardDelete($id)) {
                $success++;
            }
        }

        return [
            'success' => true,
            'message' => "완전삭제 ({$success}건)"
        ];
    }

    public function purgeAll(): array
    {
        $count = $this->model->hardDeleteAll();

        return [
            'success' => true,
            'message' => "전체 삭제 ({$count}건)"
        ];
    }

    /* =========================================================
     * 순서 변경
     * ========================================================= */
    public function reorder(array $items): array
    {
        try {

            $this->pdo->beginTransaction();

            // 1차 밀어내기
            foreach ($items as $row) {
                $this->model->updateCodeTemp($row['id'], $row['newCode'] + 10000);
            }

            // 2차 적용
            foreach ($items as $row) {
                $this->model->updateCode($row['id'], $row['newCode']);
            }

            $this->pdo->commit();

            return ['success' => true, 'message' => '순서 변경 완료'];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /* =========================================================
     * 엑셀 업로드
     * ========================================================= */
    public function saveFromExcel(array $rows): array
    {
        $actor = ActorHelper::system('EXCEL_UPLOAD');

        $this->pdo->beginTransaction();

        try {

            $count = 0;

            foreach ($rows as $row) {

                $this->model->upsertFromExcel($row, $actor);
                $count++;
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "엑셀 저장 ({$count}건)"
            ];

        } catch (\Throwable $e) {

            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}