<?php
// 경로: PROJECT_ROOT . '/app/Services/Calendar/TrashService.php'
declare(strict_types=1);

namespace App\Services\Calendar;

use PDO;
use Core\LoggerFactory;
use App\Services\Calendar\CrudService;
use App\Services\Calendar\SyncService;

class TrashService
{
    private readonly PDO $pdo;
    private CrudService $crud;
    private SyncService $sync;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->crud = new CrudService($pdo);
        $this->sync = new SyncService($pdo);
        $this->logger = LoggerFactory::getLogger('service-calendar.CalendarTrashService');
    }

    /* =========================================================
     * 🗑️ Deleted List (synology_login_id 기준)
     * ========================================================= */

    public function getDeletedEvents(string $synologyLoginId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                up.employee_name AS deleted_by_name
            FROM dashboard_calendar_events e
            LEFT JOIN user_employees up
                ON up.user_id = e.deleted_by
            WHERE e.is_active = 0
              AND e.synology_login_id = :synology
            ORDER BY e.deleted_at DESC
        ");

        $stmt->execute([
            ':synology' => $synologyLoginId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDeletedTasks(string $synologyLoginId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                t.*,
                up.employee_name AS deleted_by_name
            FROM dashboard_calendar_tasks t
            LEFT JOIN user_employees up
                ON up.user_id = t.deleted_by
            WHERE t.is_active = 0
              AND t.synology_login_id = :synology
            ORDER BY t.deleted_at DESC
        ");

        $stmt->execute([
            ':synology' => $synologyLoginId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================
     * ♻️ Restore (synology_login_id 기준)
     * ========================================================= */

    public function restoreEvent(string $id, string $synologyLoginId): bool
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                 SELECT e.*, l.id AS list_id, l.is_active AS calendar_active
                 FROM dashboard_calendar_events e
                 INNER JOIN dashboard_calendar_list l
                     ON l.id = e.calendar_id
                WHERE e.id = :id
                AND e.synology_login_id = :synology
                AND e.is_active = 0
                 LIMIT 1
                 FOR UPDATE
             ");

            $stmt->execute([
                ':id'      => $id,
                ':synology' => $synologyLoginId
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new \RuntimeException('event not found');
            }

            // 🔥 캘린더 정합성 확인
            if (empty($row['calendar_id']) || empty($row['list_id'])) {
                throw new \RuntimeException('calendar relation missing');
            }

            // 🔥 비활성 캘린더면 복원 금지
            if ((int)$row['calendar_active'] !== 1) {
                throw new \RuntimeException('calendar inactive');
            }

            // 🔥 이미 활성 상태면 성공 처리
            if ((int)$row['is_active'] === 1) {
                $this->pdo->commit();
                return true;
            }

            // 🔥 DB만 즉시 복원
            $update = $this->pdo->prepare("
                 UPDATE dashboard_calendar_events
                 SET is_active = 1,
                     deleted_at = NULL,
                     deleted_by = NULL,
                     restored_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id
                   AND synology_login_id = :synology
                   AND is_active = 0
             ");

            $update->execute([
                ':id'      => $id,
                ':synology' => $synologyLoginId
            ]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function restoreTask(string $id, string $synologyLoginId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM dashboard_calendar_tasks
            WHERE id = :id
              AND synology_login_id = :synology
            LIMIT 1
        ");

        $stmt->execute([
            ':id'      => $id,
            ':synology' => $synologyLoginId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \RuntimeException('task not found');
        }

        // Synology 존재 확인
        $exists = false;

        try {
            $res = $this->crud->getTaskByUid($id);
            $exists = !empty($res['data']);
        } catch (\Throwable $e) {
            $exists = false;
        }

        // Synology에 없으면 재생성
        if (!$exists) {

            /* =====================================================
             * CASE 1 : ERP에서 생성된 이벤트 (raw_ics 존재)
             * ===================================================== */
            if (!empty($row['raw_ics']) && !empty($row['href'])) {

                $caldav = (new \ReflectionClass($this->crud))
                    ->getMethod('createCalDavClient')
                    ->invoke($this->crud);

                // 새 href 생성
                $collectionPath = dirname($row['href']);
                $newHref = $collectionPath . '/' . uniqid('', true) . '.ics';

                // Synology에 이벤트 재생성
                $caldav->createObject($newHref, $row['raw_ics']);

                // DB href 업데이트
                $this->pdo->prepare("
                    UPDATE dashboard_calendar_tasks
                    SET href = :href
                    WHERE id = :id
                      AND synology_login_id = :synology
                ")->execute([
                    ':href'     => $newHref,
                    ':id'      => $id,
                    ':synology' => $synologyLoginId
                ]);
            }
            /* =====================================================
             * CASE 2 : Synology 원본 이벤트 (raw_ics 없음)
             * ===================================================== */ else {

                // Synology에서 다시 동기화
                $this->sync->syncOneTaskByUid(
                    $id,
                    $synologyLoginId,
                    null
                );
            }
        }

        // DB 활성화 (synology_login_id 기준)
        $update = $this->pdo->prepare("
            UPDATE dashboard_calendar_tasks
            SET is_active = 1,
                deleted_at = NULL,
                deleted_by = NULL,
                restored_at = NOW()
            WHERE id = :id
            AND synology_login_id = :synology
        ");

        $update->execute([
            ':id'      => $id,
            ':synology' => $synologyLoginId
        ]);

        return $update->rowCount() > 0;
    }

    /* =========================================================
     * 💀 Hard Delete (synology_login_id 기준)
     * ========================================================= */

    public function hardDeleteEvent(string $id, string $synologyLoginId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT href, etag 
            FROM dashboard_calendar_events
            WHERE id = :id
              AND is_active = 0
              AND synology_login_id = :synology
            LIMIT 1
        ");

        $stmt->execute([
            ':id'      => $id,
            ':synology' => $synologyLoginId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \RuntimeException('event not found or no permission');
        }

        if (!empty($row['href'])) {
            $caldav = (new \ReflectionClass($this->crud))
                ->getMethod('createCalDavClient')
                ->invoke($this->crud);

            $caldav->deleteObject($row['href'], $row['etag'] ?? null);
        }

        $del = $this->pdo->prepare("
            DELETE FROM dashboard_calendar_events
            WHERE id = :id
              AND is_active = 0
              AND synology_login_id = :synology
        ");

        return $del->execute([
            ':id'      => $id,
            ':synology' => $synologyLoginId
        ]);
    }

    public function hardDeleteTask(string $id, string $synologyLoginId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT href, etag
            FROM dashboard_calendar_tasks
            WHERE id = :id
              AND synology_login_id = :synology
              AND is_active = 0
            LIMIT 1
        ");

        $stmt->execute([
            ':id'      => $id,
            ':synology' => $synologyLoginId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \RuntimeException('task not found or no permission');
        }

        if (!empty($row['href'])) {

            $caldav = (new \ReflectionClass($this->crud))
                ->getMethod('createCalDavClient')
                ->invoke($this->crud);

            $collectionHref = rtrim(dirname($row['href']), '/') . '/';

            $realHref = $this->crud->resolveTaskObjectHrefByUid(
                $caldav,
                $collectionHref,
                $id
            );

            $hrefToDelete = $realHref ?: $row['href'];

            $ifMatch = null;
            if (!empty($row['etag'])) {
                $ifMatch = '"' . trim($row['etag'], '"') . '"';
            }

            $res = $caldav->deleteObject($hrefToDelete, $ifMatch);

            if (empty($res['success'])) {
                throw new \RuntimeException('Synology delete failed');
            }
        }

        $del = $this->pdo->prepare("
            DELETE FROM dashboard_calendar_tasks
            WHERE id = :id
              AND synology_login_id = :synology
              AND is_active = 0
        ");

        return $del->execute([
            ':id'      => $id,
            ':synology' => $synologyLoginId
        ]);
    }

    /* =========================================================
     * 🧹 Bulk Hard Delete (synology_login_id 기준)
     * ========================================================= */

    public function hardDeleteAllEvents(string $synologyLoginId): bool
    {
        $this->pdo->beginTransaction();

        try {

            $stmt = $this->pdo->prepare("
                SELECT id
                FROM dashboard_calendar_events
                WHERE is_active = 0
                  AND synology_login_id = :synology
            ");

            $stmt->execute([
                ':synology' => $synologyLoginId
            ]);

            $uids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($uids as $id) {
                $this->hardDeleteEvent($id, $synologyLoginId);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function hardDeleteAllTasks(string $synologyLoginId): bool
    {
        $this->pdo->beginTransaction();

        try {

            $stmt = $this->pdo->prepare("
                SELECT id
                FROM dashboard_calendar_tasks
                WHERE is_active = 0
                  AND synology_login_id = :synology
            ");

            $stmt->execute([
                ':synology' => $synologyLoginId
            ]);

            $uids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($uids as $id) {
                $this->hardDeleteTask($id, $synologyLoginId);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
