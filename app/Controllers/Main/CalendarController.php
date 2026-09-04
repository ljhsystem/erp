<?php
declare(strict_types=1);

namespace App\Controllers\Main;

use Core\DbPdo;
use App\Services\Auth\AuthSessionService;
use App\Services\User\ProfileService;
use App\Services\User\ExternalAccountService;
use App\Services\System\SettingService;
use App\Models\Main\CalendarListModel;
use App\Services\Calendar\QueryService;
use App\Services\Calendar\CrudService;
use App\Services\Calendar\SyncService;
use App\Services\Calendar\TrashService;


class CalendarController
{
    private QueryService $query;
    private SyncService $sync;
    private AuthSessionService $authSession;

    public function __construct()
    {
        $this->query = new QueryService(DbPdo::conn());
        $this->sync = new SyncService(DbPdo::conn());
        $this->authSession = new AuthSessionService();
    }

    private function currentUserId(): ?string
    {
        return $this->authSession->getCurrentUserId();
    }

    private function hasSynology(): bool
    {
        return $this->getSynologyLoginId() !== null;
    }

    private function filterByPersonalPolicy(array $rows): array
    {
        $userId      = $this->currentUserId();
        $hasSynology = $this->hasSynology();

        return array_values(array_filter($rows, function ($row) use ($userId, $hasSynology) {

            $isPersonal = (int)($row['is_personal'] ?? 0);
            $ownerId    = $row['owner_user_id'] ?? null;

            if ($isPersonal === 1) {

                if (!$hasSynology) {
                    return false;
                }

                if ($ownerId !== $userId) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function assertCalendarWritePermission(string $calendarId): void
    {
        $cal = $this->query->getCalendarPermission($calendarId);

        if (!$cal) {
            $this->json([
                'success' => false,
                'message' => 'Invalid calendar'
            ], 400);
        }

        $isPersonal = (int)($cal['is_personal'] ?? 0);
        $ownerId    = $cal['owner_user_id'] ?? null;
        $userId     = $this->currentUserId();

        if ($isPersonal === 1) {

            if (!$this->hasSynology()) {
                $this->json([
                    'success' => false,
                    'message' => '캘린더 사용을 위해 Synology 계정 연결이 필요합니다.'
                ], 403);
            }

            if ($ownerId !== $userId) {
                $this->json([
                    'success' => false,
                    'message' => '권한이 없습니다.'
                ], 403);
            }
        }
    }

    private ?string $synologyLoginId = null;
    private bool $synologyLoaded = false;

    private function getSynologyLoginId(): ?string
    {
        if ($this->synologyLoaded) {
            return $this->synologyLoginId;
        }

        $externalService = new ExternalAccountService(DbPdo::conn());
        $this->sync = new SyncService(DbPdo::conn());
        $account = $externalService->getMyAccount('synology');

        $this->synologyLoginId =
            $account['external_login_id'] ?? null;

        $this->synologyLoaded = true;

        return $this->synologyLoginId;
    }

    protected function guardApi(): void
    {
        if (!$this->authSession->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Forbidden'], 403);
            exit; // ✅ 명시적 종료
        }
    }


    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function apiList(): void
    {
        $this->guardApi();

        $userId = $this->currentUserId();

        $synologyLoginId = $this->getSynologyLoginId();

        if ($synologyLoginId) {

            $this->sync->syncIfNeeded(
                $synologyLoginId,
                $userId,
                $userId
            );
        }




        $list = $this->query->getActiveCalendarList(
            $userId,
            $synologyLoginId
        );

        if (!is_array($list)) {
            $list = [];
        }

        $list = $this->filterByPersonalPolicy($list);

        $this->json([
            'success' => true,
            'data'    => array_values($list),
        ]);
    }

    public function apiCacheRebuild(): void
    {
        $this->guardApi();

        if (ob_get_level() > 0) {
            ob_clean();
        }

        $actor = $this->currentUserId();

        if (!$actor) {
            $this->json([
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ], 401);
        }

        if (!$this->hasSynology()) {
            $this->json([
                'success' => false,
                'message' => 'Synology 계정 연결이 필요합니다.'
            ], 403);
        }

        $synologyLoginId = $this->getSynologyLoginId();

        if (!$synologyLoginId) {
            $this->json([
                'success' => false,
                'message' => 'Synology 로그인 ID를 찾을 수 없습니다.'
            ], 400);
        }

        $ownerUserId = $actor;

        session_write_close();

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => true,
            'status'  => 'started'
        ], JSON_UNESCAPED_UNICODE);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @ob_flush();
            @flush();
        }

        $service = new SyncService(DbPdo::conn());

        $service->rebuildFullCache(
            $synologyLoginId,
            $ownerUserId,
            $actor
        );

        exit;
    }

    public function apiEventsAll(): void
    {
        $this->guardApi();

        $synologyLoginId = $this->getSynologyLoginId();
        $userId = $this->currentUserId();

        try {

            $from = isset($_GET['start'])
                ? date('Y-m-d', strtotime($_GET['start']))
                : null;

            $to = isset($_GET['end'])
                ? date('Y-m-d', strtotime($_GET['end']))
                : null;

            if (!$from || !$to) {
                $this->json([
                    'success' => false,
                    'message' => 'start/end required'
                ], 400);
            }

            $events = $this->query->getEventsByPeriodMapped(
                $from,
                $to,
                $userId,
                $synologyLoginId
            );
            if (!is_array($events)) {
                $events = [];
            }


            $tasks = $this->query->getTasksByPeriodMapped(
                $from,
                $to,
                $userId,
                $synologyLoginId
            );
            if (!is_array($tasks)) {
                $tasks = [];
            }

            $data = array_merge($events, $tasks);

            $this->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiTasksAll(): void
    {
        $this->guardApi();

        $userId = $this->currentUserId();

        $synologyLoginId = $this->getSynologyLoginId();


        $from = isset($_GET['start'])
            ? date('Y-m-d', strtotime($_GET['start']))
            : null;

        $to = isset($_GET['end'])
            ? date('Y-m-d', strtotime($_GET['end']))
            : null;

        if (!$from || !$to) {
            $this->json([
                'success' => false,
                'message' => 'start/end required'
            ], 400);
        }

        $tasks = $this->query->getTasksByPeriodMapped(
            $from,
            $to,
            $userId,
            $synologyLoginId
        );
        if (!is_array($tasks)) {
            $tasks = [];
        }

        $tasks = $this->filterByPersonalPolicy($tasks);

        $this->json([
            'success' => true,
            'data'    => $tasks,
        ]);
    }

    public function apiTasksPanel(): void
    {
        $this->guardApi();

        $userId = $this->currentUserId();

        try {

            $userId = $this->currentUserId();

            $hasSynology = $this->hasSynology();

            $synologyLoginId = $this->getSynologyLoginId();

            $tasks = $this->query->getAllTasksMapped(
                $userId,
                $synologyLoginId
            );

            if (!is_array($tasks)) {
                $tasks = [];
            }

            $tasks = $this->filterByPersonalPolicy($tasks);

            $this->json([
                'success' => true,
                'data'    => array_values($tasks),
            ]);
        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiEventCreate(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || empty($payload['calendar_id'])) {
            $this->json([
                'success' => false,
                'message' => 'Invalid payload'
            ], 400);
        }

        $calendarId = $payload['calendar_id'];

        $this->assertCalendarWritePermission($calendarId);

        try {
            $service = new CrudService(DbPdo::conn());
            $result  = $service->createEvent($payload);

            $id = $result['data']['id'] ?? null;

            if ($id && $this->hasSynology()) {
                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $this->currentUserId();

                if ($id && $synologyLoginId) {

                    (new SyncService(DbPdo::conn()))
                        ->syncOneEventByUid(
                            $id,
                            $synologyLoginId,
                            $actorUserId,
                            [
                                'calendar_id' => $calendarId
                            ]
                        );
                }
            }

            if (empty($result['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Event create failed'
                ], 500);
            }

            $this->json([
                'success' => true,
                'data'    => $result['data'] ?? null,
            ]);
        } catch (\Throwable $e) {

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiEventUpdate(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($payload['id'])) {
            $this->json([
                'success' => false,
                'message' => 'id required'
            ], 400);
        }

        $calendarId = $payload['calendar_id'] ?? null;

        if (!$calendarId) {
            $calendarId = $this->query->getEventCalendarId($payload['id']);
        }

        if ($calendarId) {
            $this->assertCalendarWritePermission($calendarId);
        }
        try {

            $crud = new CrudService(DbPdo::conn());

            $res  = $crud->updateEvent($payload);

            $id = $res['data']['id'] ?? $payload['id'];

            if ($id && $this->hasSynology()) {

                $calendarId = $payload['calendar_id'] ?? null;

                if (!$calendarId) {
                    $calendarId = $this->query->getEventCalendarId($id);
                }

                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $this->currentUserId();

                if ($id && $synologyLoginId) {

                    (new SyncService(DbPdo::conn()))
                        ->syncOneEventByUid(
                            $id,
                            $synologyLoginId,
                            $actorUserId,
                            [
                                'calendar_id' => $calendarId
                            ]
                        );
                }
            }

            if (ob_get_level() > 0) {
                ob_clean();
            }

            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'Event update failed'
                ], 400);
            }

            $uidForReturn =
                $res['data']['id'] ??
                $res['id'] ??
                $payload['id'];

            $etagForReturn =
                $res['data']['etag'] ??
                $res['etag'] ??
                null;

            $this->json([
                'success' => true,
                'data' => [
                    'id'  => $uidForReturn,
                    'etag' => $etagForReturn,
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiEventDelete(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->json([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        $id = $payload['id'] ?? null;

        if (is_array($id)) {
            $id = $id[0] ?? null;
        }

        if (!$id) {
            $this->json([
                'success' => false,
                'message' => 'id required'
            ], 400);
        }

        $id = (string)$id;
        $id = preg_replace('/^(event_|task_)/', '', $id);

        // 🔥 DB 존재 확인
        $calendarId = $this->query->getEventCalendarId($id);

        if (!$calendarId) {
            $this->json([
                'success' => false,
                'message' => 'event not found'
            ], 404);
        }

        $this->assertCalendarWritePermission($calendarId);

        try {

            $crud = new CrudService(DbPdo::conn());

            $res = $crud->deleteComponent([
                'id' => $id
            ]);

            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'delete failed'
                ], 400);
            }

            $this->json([
                'success' => true,
                'data' => [
                    'id' => $id
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiTaskCreate(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        // 1️⃣ payload 검증 먼저
        if (!$payload || empty($payload['calendar_id'])) {
            $this->json([
                'success' => false,
                'message' => 'Invalid payload'
            ], 400);
        }

        $calendarId  = $payload['calendar_id'];
        $hasSynology = $this->hasSynology();

        $this->assertCalendarWritePermission($calendarId);

        try {

            $crud =  new CrudService(DbPdo::conn());
            $res  = $crud->createTask($payload);

            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'Task create failed'
                ], 500);
            }

            $id = $res['data']['id'] ?? null;

            if ($id && $hasSynology) {
                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $this->currentUserId();

                if ($id && $synologyLoginId) {

                    (new SyncService(DbPdo::conn()))

                        ->syncOneTaskByUid(
                            $id,
                            $synologyLoginId,
                            $actorUserId,
                            [
                                'calendar_id' => $calendarId
                            ]
                        );
                }
            }

            $this->json([
                'success' => true,
                'data'    => $res['data'] ?? null,
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiTaskUpdate(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];

        try {

            if (!empty($payload['id'])) {
                $payload['id'] = preg_replace('/^task_/', '', (string)$payload['id']);
            }

            if (empty($payload['id'])) {
                $this->json([
                    'success' => false,
                    'message' => 'id required'
                ], 400);
            }

            if (empty($payload['calendar_id'])) {
                $calendarId = $this->query->getTaskCalendarId($payload['id']);

                if ($calendarId) {
                    $payload['calendar_id'] = $calendarId;
                }
            }

            if (!empty($payload['calendar_id'])) {
                $this->assertCalendarWritePermission($payload['calendar_id']);
            }

            $crud =  new CrudService(DbPdo::conn());
            $res  = $crud->updateTask($payload);

            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'task update failed'
                ], 400);
            }

            $id = $res['data']['id'] ?? null;

            if ($id && $this->hasSynology()) {

                $collectionHref = $payload['collection_href'] ?? null;

                if (!$collectionHref) {
                    $collectionHref = $this->query->getTaskCollectionHref($id);
                }
                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $this->currentUserId();

                if ($id && $synologyLoginId) {

                    (new SyncService(DbPdo::conn()))

                        ->syncOneTaskByUid(
                            $id,
                            $synologyLoginId,
                            $actorUserId,
                            [
                                'calendar_id'     => $payload['calendar_id'],
                                'collection_href' => $collectionHref
                            ]
                        );
                }
            }

            $this->json([
                'success' => true,
                'data'    => $res['data'] ?? null,
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiTaskDelete(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || empty($payload['id'])) {
            $this->json([
                'success' => false,
                'message' => 'id required'
            ], 400);
        }

        $calendarId = $this->query->getTaskCalendarId($payload['id']);

        if ($calendarId) {
            $this->assertCalendarWritePermission($calendarId);
        }

        try {

            $crud = new CrudService(DbPdo::conn());

            $res  = $crud->deleteTask($payload);

            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'task delete failed'
                ], 400);
            }

            $this->json([
                'success' => true,
                'data'    => $res['data'] ?? null,
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function apiCollectionDelete(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || empty($payload['collection_href'])) {
            $this->json([
                'success' => false,
                'message' => 'collection_href required'
            ], 400);
        }

        try {

            $crud =  new CrudService(DbPdo::conn());

            $calendarId = $this->query->getCalendarIdByHref($payload['collection_href']);

            if ($calendarId) {
                $this->assertCalendarWritePermission($calendarId);
            }

            $res = $crud->deleteCollection($payload['collection_href']);

            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'delete failed'
                ], 400);
            }

            $this->json([
                'success' => true,
                'data'    => $res['data'] ?? null,
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiEventHardDelete(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || empty($payload['id'])) {
            $this->json([
                'success' => false,
                'message' => 'id required'
            ], 400);
        }

        $calendarId = $this->query->getEventCalendarId((string)$payload['id']);

        if ($calendarId) {
            $this->assertCalendarWritePermission($calendarId);
        }

        try {
            $service =  new TrashService(DbPdo::conn());

            $synologyLoginId = $this->getSynologyLoginId();

            if (!$synologyLoginId) {
                $this->json([
                    'success' => false,
                    'message' => 'Synology 계정 연결이 필요합니다.'
                ], 403);
            }

            $ok = $service->hardDeleteEvent(
                (string)$payload['id'],
                $synologyLoginId
            );

            if (!$ok) {
                $this->json([
                    'success' => false,
                    'message' => 'event hard delete failed'
                ], 400);
            }

            $this->json([
                'success' => true,
                'data'    => ['id' => $payload['id']]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function apiTaskHardDelete(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || empty($payload['id'])) {
            $this->json([
                'success' => false,
                'message' => 'id required'
            ], 400);
        }

        $calendarId = $this->query->getTaskCalendarId((string)$payload['id']);

        if ($calendarId) {
            $this->assertCalendarWritePermission($calendarId);
        }

        try {

            $service =  new TrashService(DbPdo::conn());
            $synologyLoginId = $this->getSynologyLoginId();

            if (!$synologyLoginId) {
                $this->json([
                    'success' => false,
                    'message' => 'Synology 계정 연결이 필요합니다.'
                ], 403);
            }

            $ok = $service->hardDeleteTask(
                (string)$payload['id'],
                $synologyLoginId
            );

            if (!$ok) {
                $this->json([
                    'success' => false,
                    'message' => 'task hard delete failed'
                ], 400);
            }

            $this->json([
                'success' => true,
                'data'    => ['id' => $payload['id']]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiToggleTaskComplete(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->json([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        $id = isset($payload['id'])
            ? preg_replace('/^task_/', '', (string)$payload['id'])
            : null;

        $calendarId = $payload['calendar_id'] ?? null;

        $completed = isset($payload['completed'])
            ? (bool)$payload['completed']
            : (isset($payload['complete'])
                ? (bool)$payload['complete']
                : false);

        if (!$id) {
            $this->json([
                'success' => false,
                'message' => 'id required'
            ], 400);
        }

        if (!$calendarId) {
            $this->json([
                'success' => false,
                'message' => 'calendar_id required'
            ], 400);
        }

        $this->assertCalendarWritePermission($calendarId);
        try {

            $crud =  new CrudService(DbPdo::conn());
            $res  = $crud->toggleTaskComplete($id, $calendarId, $completed);

            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'Task update failed'
                ], 400);
            }

            if ($this->hasSynology()) {
                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $this->currentUserId();

                if ($synologyLoginId) {

                    (new SyncService(DbPdo::conn()))
                        ->syncOneTaskByUid(
                            $id,
                            $synologyLoginId,
                            $actorUserId,
                            [
                                'calendar_id' => $calendarId
                            ]
                        );
                }
            }

            $this->json([
                'success' => true,
                'data'    => [
                    'id'       => $id,
                    'completed' => $completed
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function apiUpdateAdminColor(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->json([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ], 400);
        }

        $calendarId = $payload['calendar_id'] ?? null;
        $color      = $payload['admin_calendar_color'] ?? null;

        if (!$calendarId || !$color) {
            $this->json([
                'success' => false,
                'message' => 'calendar_id and admin_calendar_color required'
            ], 400);
        }

        $color = strtolower(trim((string)$color));

        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $this->json([
                'success' => false,
                'message' => 'Invalid color format'
            ], 400);
        }

        $synologyLoginId = $this->getSynologyLoginId();
        if (!$synologyLoginId) {
            $this->json([
                'success' => false,
                'message' => 'Synology account not connected'
            ], 403);
        }

        try {
            $model = new CalendarListModel(DbPdo::conn());

            $model->updateAdminColor(
                $calendarId,
                $synologyLoginId,
                $color,
                $this->currentUserId()
            );

            $this->json([
                'success' => true,
                'data'    => [
                    'calendar_id' => $calendarId,
                    'admin_calendar_color' => $color
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function apiEventsDeleted(): void
    {
        $this->guardApi();

        try {

            $synologyLoginId = $this->getSynologyLoginId();

            if (!$synologyLoginId) {
                $this->json([
                    'success' => true,
                    'data'    => []
                ]);
            }

            $service = new TrashService(DbPdo::conn());
            $rows    = $service->getDeletedEvents($synologyLoginId);

            if (!is_array($rows)) {
                $rows = [];
            }

            $this->json([
                'success' => true,
                'data'    => array_values($rows),
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiTasksDeleted(): void
    {
        $this->guardApi();

        try {

            $synologyLoginId = $this->getSynologyLoginId();

            if (!$synologyLoginId) {
                $this->json([
                    'success' => true,
                    'data'    => []
                ]);
            }

            $service = new TrashService(DbPdo::conn());
            $rows    = $service->getDeletedTasks($synologyLoginId);

            if (!is_array($rows)) {
                $rows = [];
            }

            $rows = $this->filterByPersonalPolicy($rows);

            $this->json([
                'success' => true,
                'data'    => array_values($rows),
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiEventHardDeleteAll(): void
    {
        $this->guardApi();

        try {
            $service = new TrashService(DbPdo::conn());
            $synologyLoginId = $this->getSynologyLoginId();

            if (!$synologyLoginId) {
                $this->json([
                    'success' => true,
                    'data' => ['deleted_count' => 0]
                ]);
            }

            $rows = $service->getDeletedEvents($synologyLoginId);

            $deletedCount = 0;

            foreach ($rows as $row) {

                if (!empty($row['calendar_id'])) {
                    $this->assertCalendarWritePermission($row['calendar_id']);
                }

                $service->hardDeleteEvent(
                    $row['id'],
                    $synologyLoginId
                );

                $deletedCount++;
            }

            $this->json([
                'success' => true,
                'data' => [
                    'deleted_count' => $deletedCount
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function apiTaskHardDeleteAll(): void
    {
        $this->guardApi();

        try {

            $service = new TrashService(DbPdo::conn());
            $synologyLoginId = $this->getSynologyLoginId();

            if (!$synologyLoginId) {
                $this->json([
                    'success' => true,
                    'data' => ['deleted_count' => 0]
                ]);
            }

            $rows = $service->getDeletedTasks($synologyLoginId);

            if (!is_array($rows)) {
                $rows = [];
            }

            $rows = $this->filterByPersonalPolicy($rows);

            $deletedCount = 0;

            foreach ($rows as $row) {

                $id        = $row['id'] ?? null;
                $calendarId = $row['calendar_id'] ?? null;

                if (!$id || !$calendarId) {
                    continue;
                }

                $this->assertCalendarWritePermission($calendarId);

                $ok = $service->hardDeleteTask($id, $synologyLoginId);

                if ($ok) {
                    $deletedCount++;
                }
            }

            $this->json([
                'success' => true,
                'data'    => [
                    'deleted_count' => $deletedCount
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiEventRestore(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->json([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ], 400);
            return;
        }

        if (!$payload || empty($payload['id'])) {
            $this->json([
                'success' => false,
                'message' => 'id required'
            ], 400);
            return;
        }

        try {

            $id = (string)$payload['id'];

            $service = new TrashService(DbPdo::conn());

            $calendarId = $this->query->getEventCalendarId($id);

            if ($calendarId) {
                $this->assertCalendarWritePermission($calendarId);
            }

            $synologyLoginId = $this->getSynologyLoginId();

            if (!$synologyLoginId) {
                $this->json([
                    'success' => false,
                    'message' => 'Synology 계정 연결이 필요합니다.'
                ], 403);
            }

            $service->restoreEvent($id, $synologyLoginId);

            $this->json([
                'success' => true,
                'data' => [
                    'id' => $id
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiTaskRestore(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->json([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ], 400);
            return;
        }

        if (!$payload || empty($payload['id'])) {
            $this->json([
                'success' => false,
                'message' => 'id required'
            ], 400);
            return;
        }

        try {

            $id = (string)$payload['id'];

            $service = new TrashService(DbPdo::conn());

            $calendarId = $this->query->getTaskCalendarId($id);

            if ($calendarId) {
                $this->assertCalendarWritePermission($calendarId);
            }

            $synologyLoginId = $this->getSynologyLoginId();

            if (!$synologyLoginId) {
                $this->json([
                    'success' => false,
                    'message' => 'Synology 계정 연결이 필요합니다.'
                ], 403);
            }

            $service->restoreTask($id, $synologyLoginId);

            $this->json([
                'success' => true,
                'data' => [
                    'id' => $id
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiTaskDeleteBulk(): void
    {
        $this->guardApi();

        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || empty($payload['uids']) || !is_array($payload['uids'])) {
            $this->json([
                'success' => false,
                'message' => 'uids array required'
            ], 400);
        }

        try {

            $crud = new CrudService(DbPdo::conn());
            $deletedCount = 0;
            $failed       = [];

            foreach ($payload['uids'] as $id) {

                if (!$id) continue;

                $id = preg_replace('/^task_/', '', (string)$id);

                // 🔥 QueryService 사용
                $calendarId = $this->query->getTaskCalendarId($id);

                if ($calendarId) {
                    $this->assertCalendarWritePermission($calendarId);
                }

                $res = $crud->deleteTask(['id' => $id]);

                if (!empty($res['success'])) {
                    $deletedCount++;
                } else {
                    $failed[] = $id;
                }
            }

            $this->json([
                'success' => true,
                'data'    => [
                    'deleted_count' => $deletedCount,
                    'failed'        => $failed
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiProfileSummary(): void
    {
        $this->guardApi();

        try {

            $userId = $this->currentUserId();

            if (!$userId) {
                $this->json([
                    'success' => false,
                    'message' => 'unauthorized'
                ], 401);
            }

            $profileService = new ProfileService(DbPdo::conn());
            $settingService = new SettingService(DbPdo::conn());
            $externalService = new ExternalAccountService(DbPdo::conn());

            $profile = $profileService->getById($userId) ?? [];
            $user    = $profileService->getById($userId) ?? [];



            $profileImagePath = $profile['profile_image'] ?? null;

            $profileImageUrl = $profileImagePath
                ? '/api/file/preview?path=' . rawurlencode($profileImagePath)
                : null;

            $host       = $settingService->get('synology_host', '');
            $caldavPath = $settingService->get('synology_caldav_path', '/caldav.php/');
            $sslVerify  = (int)$settingService->get('synology_ssl_verify', 1);

            $baseUrl = null;

            if ($host && $caldavPath) {
                $baseUrl = rtrim($host, '/') . '/' . ltrim($caldavPath, '/');
            }

            $external = $externalService->getMyAccount('synology');

            $connected       = false;
            $loginId         = null;
            $internalFullUrl = null;

            if ($external && !empty($external['external_login_id'])) {

                $connected = true;
                $loginId   = $external['external_login_id'];

                if ($baseUrl) {
                    $internalFullUrl =
                        rtrim($baseUrl, '/') .
                        '/' . $loginId .
                        '/home/';
                }
            }

            $this->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'name'  => $profile['employee_name']
                            ?? ($user['username'] ?? ''),
                        'email' => $user['email'] ?? '',
                        'profile_image_url' => $profileImageUrl
                    ],
                    'synology' => [
                        'connected'         => $connected,
                        'login_id'          => $loginId,
                        'host'              => $host,
                        'caldav_path'       => $caldavPath,
                        'base_url'          => $baseUrl,
                        'internal_full_url' => $internalFullUrl,
                        'ssl_verify'        => $sslVerify
                    ]
                ]
            ]);
        } catch (\Throwable $e) {

            if (ob_get_level() > 0) {
                ob_clean();
            }

            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
