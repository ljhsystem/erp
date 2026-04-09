<?php
// 경로: PROJECT_ROOT . '/app/Services/System/CardService.php'
// 설명:
//  - 카드(Card) 관리 서비스
//  - UUID / Code 생성은 Service 책임
//  - DB 처리: CardModel
//  - 모든 주요 흐름 LoggerFactory 적용
namespace App\Services\System;

use PDO;
use App\Models\System\CardModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;

class CardService
{
    private PDO $pdo;
    private CardModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new CardModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.CardService');
    }

    /* =========================================================
     * 목록
     * ========================================================= */
    public function getList(array $filters = []): array
    {
        return $this->model->getList($filters);
    }

    /* =========================================================
     * 상세
     * ========================================================= */
    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    /* =========================================================
     * 저장
     * ========================================================= */
    public function save(array $data, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            $this->pdo->beginTransaction();

            $id = trim((string)($data['id'] ?? ''));

            /* =========================
             * UPDATE
             * ========================= */
            if ($id) {

                $before = $this->model->getById($id);

                if (!$before) {
                    throw new \Exception('존재하지 않는 카드입니다.');
                }

                $data['updated_by'] = $actor;

                unset($data['id']);

                $this->model->updateById($id, $data);

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $id,
                    'code' => $before['code']
                ];
            }

            /* =========================
             * INSERT
             * ========================= */
            $newId = UuidHelper::generate();
            $newCode = CodeHelper::generateCardCode($this->pdo);

            $insertData = array_merge($data, [
                'id' => $newId,
                'code' => $newCode,
                'created_by' => $actor,
                'updated_by' => $actor
            ]);

            $this->model->create($insertData);

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $newId,
                'code' => $newCode
            ];

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

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

        $ok = $this->model->deleteById($id, $actor);

        return ['success' => $ok];
    }

    /* =========================================================
     * 휴지통
     * ========================================================= */
    public function getTrashList(): array
    {
        return $this->model->getDeleted();
    }

    public function restore(string $id, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        return [
            'success' => $this->model->restoreById($id, $actor)
        ];
    }

    public function purge(string $id): array
    {
        $ok = $this->model->hardDeleteById($id);

        return ['success' => $ok];
    }

    /* =========================================================
     * bulk
     * ========================================================= */
    public function restoreBulk(array $ids): array
    {
        foreach ($ids as $id) {
            $this->model->restoreById($id, 'SYSTEM');
        }

        return ['success' => true];
    }

    public function purgeBulk(array $ids): array
    {
        foreach ($ids as $id) {
            $this->model->hardDeleteById($id);
        }

        return ['success' => true];
    }

    public function purgeAll(): array
    {
        $rows = $this->model->getDeleted();

        foreach ($rows as $row) {
            $this->model->hardDeleteById($row['id']);
        }

        return ['success' => true];
    }

    /* =========================================================
     * reorder
     * ========================================================= */
    public function reorder(array $changes): bool
    {
        $this->pdo->beginTransaction();

        try {

            foreach ($changes as $row) {
                $this->model->updateCode($row['id'], $row['newCode'] + 10000);
            }

            foreach ($changes as $row) {
                $this->model->updateCode($row['id'], $row['newCode']);
            }

            $this->pdo->commit();
            return true;

        } catch (\Throwable $e) {

            $this->pdo->rollBack();
            throw $e;
        }
    }
}