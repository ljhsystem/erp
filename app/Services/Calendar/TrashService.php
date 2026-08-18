<?php
declare(strict_types=1);

namespace App\Services\Calendar;

use PDO;
use Core\LoggerFactory;
use App\Services\Calendar\CrudService;
use App\Services\Calendar\SyncService;
use Core\Helpers\ActorHelper;

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

    public function getDeletedEvents(string $synologyLoginId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                e.*,
                e.deleted_by AS deleted_by_name
            FROM dashboard_calendar_events e
            WHERE e.is_active = 0
              AND e.synology_login_id = :synology
            ORDER BY e.deleted_at DESC
        ");

        $stmt->execute([
            ':synology' => $synologyLoginId
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ActorHelper::enrichActorNames($rows, [
            'deleted_by_name' => 'deleted_by',
        ]);
    }

    public function getDeletedTasks(string $synologyLoginId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                t.*,
                t.deleted_by AS deleted_by_name
            FROM dashboard_calendar_tasks t
            WHERE t.is_active = 0
              AND t.synology_login_id = :synology
            ORDER BY t.deleted_at DESC
        ");

        $stmt->execute([
            ':synology' => $synologyLoginId
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ActorHelper::enrichActorNames($rows, [
            'deleted_by_name' => 'deleted_by',
        ]);
    }

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

            if (empty($row['calendar_id']) || empty($row['list_id'])) {
                throw new \RuntimeException('calendar relation missing');
            }

            if ((int)$row['calendar_active'] !== 1) {
                throw new \RuntimeException('calendar inactive');
            }

            if ((int)$row['is_active'] === 1) {
                $this->pdo->commit();
                return true;
            }

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

        $exists = false;

        try {
            $res = $this->crud->getTaskByUid($id);
            $exists = !empty($res['data']);
        } catch (\Throwable $e) {
            $exists = false;
        }

        if (!$exists) {

            if (!empty($row['raw_ics']) && !empty($row['href'])) {

                $caldav = (new \ReflectionClass($this->crud))
                    ->getMethod('createCalDavClient')
                    ->invoke($this->crud);

                $collectionPath = dirname($row['href']);
                $newHref = $collectionPath . '/' . uniqid('', true) . '.ics';

                $caldav->createObject($newHref, $row['raw_ics']);

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
 else {

                $this->sync->syncOneTaskByUid(
                    $id,
                    $synologyLoginId,
                    null
                );
            }
        }

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
