<?php
// 경로: PROJECT_ROOT . '/app/Controllers/Dashboard/CalendarController.php'
// 대시보드>일정/캘린더 API 컨트롤러
declare(strict_types=1);
namespace App\Controllers\Dashboard;

use Core\Session;
use Core\DbPdo;
use App\Services\User\ProfileService;
use App\Services\User\ExternalAccountService;
use App\Services\System\SettingService;
use App\Models\Dashboard\CalendarListModel;
use App\Services\Calendar\QueryService;
use App\Services\Calendar\CrudService;
use App\Services\Calendar\SyncService;
use App\Services\Calendar\TrashService;


class CalendarController
{
    private QueryService $query;
    private SyncService $sync;
    
    public function __construct()
    {
        Session::requireAuth();
        $this->query = new QueryService(DbPdo::conn());
        $this->sync = new SyncService(DbPdo::conn());
    }    

    private function hasSynology(): bool
    {
        return $this->getSynologyLoginId() !== null;
    }

    private function filterByPersonalPolicy(array $rows): array
    {
        $userId      = $_SESSION['user']['id'] ?? null;
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
        $userId     = $_SESSION['user']['id'] ?? null;
    
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

    /* ============================================================
     * 공통
     * ============================================================ */

     protected function guardApi(): void
     {
         if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
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

    /* ============================================================
     * 📅 Calendar (Synology 그대로)
     * ============================================================ */

    /**
     * 캘린더 목록
     * GET /api/dashboard/calendar/list
     */
    public function apiList(): void
    {
        $this->guardApi();
    
        $userId = $_SESSION['user']['id'] ?? null;
    
        $synologyLoginId = $this->getSynologyLoginId();
    
        // 🔥 TTL 기반 자동 Sync
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
    
        // 🔥 개인 캘린더 정책 필터
        $list = $this->filterByPersonalPolicy($list);
    
        $this->json([
            'success' => true,
            'data'    => array_values($list),
        ]);
    }


    // 클래스 내부, 다른 apiXXX 메서드들과 같은 레벨
    public function apiCacheRebuild(): void
    {
        $this->guardApi();
    
        if (ob_get_level() > 0) {
            ob_clean();
        }
    
        $actor = $_SESSION['user']['id'] ?? null;
    
        if (!$actor) {
            $this->json([
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ], 401);
        }
    
        // 🔥 외부계정 없으면 동기화 실행 안함
        if (!$this->hasSynology()) {
            $this->json([
                'success' => false,
                'message' => 'Synology 계정 연결이 필요합니다.'
            ], 403);
        }
    
        // 🔥 Synology 로그인 ID 조회 (현재 연결된 외부계정 기준)
        $synologyLoginId = $this->getSynologyLoginId();

        if (!$synologyLoginId) {
            $this->json([
                'success' => false,
                'message' => 'Synology 로그인 ID를 찾을 수 없습니다.'
            ], 400);
        }
        
        $ownerUserId = $actor;
    
        // 세션 잠금 해제
        session_write_close();
    
        // 1️⃣ 먼저 JSON 응답 출력
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
    
        echo json_encode([
            'success' => true,
            'status'  => 'started'
        ], JSON_UNESCAPED_UNICODE);
    
        // 2️⃣ 클라이언트 응답 종료
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @ob_flush();
            @flush();
        }
    
        // 3️⃣ 백그라운드 작업 실행
        $service = new SyncService(DbPdo::conn());
    
        $service->rebuildFullCache(
            $synologyLoginId,
            $ownerUserId,
            $actor
        );
    
        exit;
    }



    /* ============================================================
    * 📌 Events + Tasks (FullCalendar 표시용)
    * ============================================================ */
    public function apiEventsAll(): void
    {
        $this->guardApi(); 

        $synologyLoginId = $this->getSynologyLoginId();
        $userId = $_SESSION['user']['id'] ?? null;

        try {

            // 1️⃣ 기간 파라미터 확인
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

            // 2️⃣ DB 기준 이벤트/태스크 조회
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
           

            // 5️⃣ 병합
            $data = array_merge($events, $tasks);

            // 6️⃣ 반환
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

    /**
     * 전체 태스크 조회 (DB 캐시 기반 + Lazy Sync)
     * GET /api/dashboard/calendar/tasks-all
     */
    public function apiTasksAll(): void
    {
        $this->guardApi();

        $userId = $_SESSION['user']['id'] ?? null;

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


        // 2️⃣ 먼저 DB 기준 조회 (ERP 캐시 기준)
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

        // 5️⃣ 최종 반환
        $this->json([
            'success' => true,
            'data'    => $tasks,
        ]);
    }

    /**
     * 📋 우측 패널 전용 전체 태스크 조회 (범위 무관)
     * GET /api/dashboard/calendar/tasks-panel
     */
    public function apiTasksPanel(): void
    {
        $this->guardApi();

        $userId = $_SESSION['user']['id'] ?? null;

        try {

            $userId = $_SESSION['user']['id'];

            // 1️⃣ Synology 연결 여부 한 번만 확인
            $hasSynology = $this->hasSynology();

            // 2️⃣ DB 기준 전체 태스크 조회 (기간 무관)
            $synologyLoginId = $this->getSynologyLoginId();

            $tasks = $this->query->getAllTasksMapped(
                $userId,
                $synologyLoginId
            );

            if (!is_array($tasks)) {
                $tasks = [];
            }

            // 3️⃣ 연결이 없으면 external만 제거
            $tasks = $this->filterByPersonalPolicy($tasks);

            // 4️⃣ 반환
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
    
        // 1️⃣ payload 먼저 검증
        if (!$payload || empty($payload['calendar_id'])) {
            $this->json([
                'success' => false,
                'message' => 'Invalid payload'
            ], 400);
        }
    
        $calendarId = $payload['calendar_id'];    

        // 4️⃣ external + Synology 미연결 → 생성 차단
        $this->assertCalendarWritePermission($calendarId);
    
        try {       
            $service = new CrudService(DbPdo::conn());
            $result  = $service->createEvent($payload);

            $uid = $result['data']['uid'] ?? null;

            if ($uid && $this->hasSynology()) {
                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $_SESSION['user']['id'] ?? null;
                
                if ($uid && $synologyLoginId) {
                
                    (new SyncService(DbPdo::conn()))          
                        ->syncOneEventByUid(
                            $uid,
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

        // 1️⃣ 기본 검증
        if (empty($payload['uid'])) {
            $this->json([
                'success' => false,
                'message' => 'uid required'
            ], 400);
        }
        // 🔥 권한 체크
        $calendarId = $payload['calendar_id'] ?? null;

        if (!$calendarId) {
            $calendarId = $this->query->getEventCalendarId($payload['uid']);
        }

        if ($calendarId) {
            $this->assertCalendarWritePermission($calendarId);
        }
        try {
            
            $crud = new CrudService(DbPdo::conn());

            $res  = $crud->updateEvent($payload);

            $uid = $res['data']['uid'] ?? $payload['uid'];

            if ($uid && $this->hasSynology()) {

                $calendarId = $payload['calendar_id'] ?? null;
            
                if (!$calendarId) {
                    $calendarId = $this->query->getEventCalendarId($uid);
                }
            
                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $_SESSION['user']['id'] ?? null;
                
                if ($uid && $synologyLoginId) {
                
                    (new SyncService(DbPdo::conn()))
                        ->syncOneEventByUid(
                            $uid,
                            $synologyLoginId,
                            $actorUserId,
                            [
                                'calendar_id' => $calendarId
                            ]
                        );
                }
            }

            // 🔥 출력 오염 방지
            if (ob_get_level() > 0) {
                ob_clean();
            }

            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'Event update failed'
                ], 400);
            }

            // ✅ uid / etag 안전 회수
            $uidForReturn =
                $res['data']['uid'] ??
                $res['uid'] ??
                $payload['uid'];

            $etagForReturn =
                $res['data']['etag'] ??
                $res['etag'] ??
                null;

            $this->json([
                'success' => true,
                'data' => [
                    'uid'  => $uidForReturn,
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
    
        $uid = $payload['uid'] ?? null;
    
        // 🔥 배열 방어
        if (is_array($uid)) {
            $uid = $uid[0] ?? null;
        }
    
        if (!$uid) {
            $this->json([
                'success' => false,
                'message' => 'uid required'
            ], 400);
        }
    
        // 🔥 prefix 정규화
        $uid = (string)$uid;
        $uid = preg_replace('/^(event_|task_)/', '', $uid);
    
        // 🔥 DB 존재 확인
        $calendarId = $this->query->getEventCalendarId($uid);

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
                'uid' => $uid
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
                    'uid' => $uid
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


    /* ============================================================
     * ☑️ Tasks (VTODO)
     * ============================================================ */
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

    
        // 3️⃣ external + Synology 미연결 → 생성 차단
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
    
            $uid = $res['data']['uid'] ?? null;
    
            // 4️⃣ 단건 즉시 동기화 (Synology 연결된 경우만 의미 있음)
            if ($uid && $hasSynology) {
                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $_SESSION['user']['id'] ?? null;
                
                if ($uid && $synologyLoginId) {
                
                    (new SyncService(DbPdo::conn()))
                    
                        ->syncOneTaskByUid(
                            $uid,
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
    
            // 1️⃣ uid 정규화
            if (!empty($payload['uid'])) {
                $payload['uid'] = preg_replace('/^task_/', '', (string)$payload['uid']);
            }
    
            if (empty($payload['uid'])) {
                $this->json([
                    'success' => false,
                    'message' => 'uid required'
                ], 400);
            }
    
            // 2️⃣ calendar_id 누락 시 DB에서 보정
            if (empty($payload['calendar_id'])) {
                $calendarId = $this->query->getTaskCalendarId($payload['uid']);

                if ($calendarId) {
                    $payload['calendar_id'] = $calendarId;
                }
            }
            // 🔥 권한 체크
            if (!empty($payload['calendar_id'])) {
                $this->assertCalendarWritePermission($payload['calendar_id']);
            }
    
            // 3️⃣ DB 업데이트 (항상 허용)
            $crud =  new CrudService(DbPdo::conn());
            $res  = $crud->updateTask($payload);
    
            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'task update failed'
                ], 400);
            }
    
            $uid = $res['data']['uid'] ?? null;
    
            // 4️⃣ Synology 연결된 경우에만 동기화
            if ($uid && $this->hasSynology()) {
    
                $collectionHref = $payload['collection_href'] ?? null;
    
                if (!$collectionHref) {
                    $collectionHref = $this->query->getTaskCollectionHref($uid);
                }
                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $_SESSION['user']['id'] ?? null;
                
                if ($uid && $synologyLoginId) {
                
                    (new SyncService(DbPdo::conn()))
                    
                        ->syncOneTaskByUid(
                            $uid,
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
    
        // 1️⃣ 기본 검증
        if (!$payload || empty($payload['uid'])) {
            $this->json([
                'success' => false,
                'message' => 'uid required'
            ], 400);
        }

        // 🔥 calendar_id 조회 (Service)
        $calendarId = $this->query->getTaskCalendarId($payload['uid']);

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

            // 🔥 calendar_id 조회 (QueryService)
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
    
        if (!$payload || empty($payload['uid'])) {
            $this->json([
                'success' => false,
                'message' => 'uid required'
            ], 400);
        }
    
        // 🔥 권한체크 (QueryService)
        $calendarId = $this->query->getEventCalendarId((string)$payload['uid']);
    
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
                (string)$payload['uid'],
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
                'data'    => ['uid' => $payload['uid']]
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
    
        if (!$payload || empty($payload['uid'])) {
            $this->json([
                'success' => false,
                'message' => 'uid required'
            ], 400);
        }
    
        // 🔥 권한 체크 (QueryService)
        $calendarId = $this->query->getTaskCalendarId((string)$payload['uid']);
    
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
                (string)$payload['uid'],
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
                'data'    => ['uid' => $payload['uid']]
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
    
        // 1️⃣ uid 정규화
        $uid = isset($payload['uid'])
            ? preg_replace('/^task_/', '', (string)$payload['uid'])
            : null;
    
        $calendarId = $payload['calendar_id'] ?? null;
    
        $completed = isset($payload['completed'])
            ? (bool)$payload['completed']
            : (isset($payload['complete'])
                ? (bool)$payload['complete']
                : false);
    
        if (!$uid) {
            $this->json([
                'success' => false,
                'message' => 'uid required'
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
    
            // 2️⃣ DB 업데이트
            $crud =  new CrudService(DbPdo::conn());
            $res  = $crud->toggleTaskComplete($uid, $calendarId, $completed);
    
            if (empty($res['success'])) {
                $this->json([
                    'success' => false,
                    'message' => $res['message'] ?? 'Task update failed'
                ], 400);
            }
    
            // 3️⃣ Synology 연결된 경우에만 동기화
            if ($this->hasSynology()) {
                $synologyLoginId = $this->getSynologyLoginId();
                $actorUserId     = $_SESSION['user']['id'] ?? null;
                
                if ($synologyLoginId) {
                
                    (new SyncService(DbPdo::conn()))                    
                        ->syncOneTaskByUid(
                            $uid,
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
                    'uid'       => $uid,
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


    /* ============================================================
    * 🎨 Admin Calendar Color Update
    * POST /api/dashboard/calendar/update-admin-color
    * ============================================================ */
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
    
        // normalize
        $color = strtolower(trim((string)$color));
    
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $this->json([
                'success' => false,
                'message' => 'Invalid color format'
            ], 400);
        }
    
        // ✅ synology_login_id 필수
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
                $synologyLoginId,                  // ✅ 추가
                $color,
                $_SESSION['user']['id'] ?? null
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

    /* ============================================================
    * 🗑️ Deleted Events (휴지통)
    * GET /api/dashboard/calendar/events-deleted
    * ============================================================ */
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


    /* ============================================================
    * 🗑️ Deleted Tasks (휴지통)
    * GET /api/dashboard/calendar/tasks-deleted
    * ============================================================ */
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
                    $row['uid'],
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
    
            // 1️⃣ 휴지통 태스크 조회
            $rows = $service->getDeletedTasks($synologyLoginId);
    
            if (!is_array($rows)) {
                $rows = [];
            }
    
            // 2️⃣ 개인/공유 정책 필터
            $rows = $this->filterByPersonalPolicy($rows);
    
            $deletedCount = 0;
    
            foreach ($rows as $row) {
    
                $uid        = $row['uid'] ?? null;
                $calendarId = $row['calendar_id'] ?? null;
    
                if (!$uid || !$calendarId) {
                    continue;
                }
    
                // 3️⃣ 권한 재확인
                $this->assertCalendarWritePermission($calendarId);
    
                // 4️⃣ 실제 영구삭제
                $ok = $service->hardDeleteTask($uid, $synologyLoginId);
    
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
    /* ============================================================
    * ♻️ Restore Event
    * POST /api/dashboard/calendar/event/restore
    * ============================================================ */
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

        if (!$payload || empty($payload['uid'])) {
            $this->json([
                'success' => false,
                'message' => 'uid required'
            ], 400);
            return;
        }

        try {

            $uid = (string)$payload['uid'];

            $service = new TrashService(DbPdo::conn()); 

            // 🔥 QueryService 사용
            $calendarId = $this->query->getEventCalendarId($uid);

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

            $service->restoreEvent($uid, $synologyLoginId);

            $this->json([
                'success' => true,
                'data' => [
                    'uid' => $uid
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


    /* ============================================================
    * ♻️ Restore Task
    * POST /api/dashboard/calendar/task/restore
    * ============================================================ */
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

        if (!$payload || empty($payload['uid'])) {
            $this->json([
                'success' => false,
                'message' => 'uid required'
            ], 400);
            return;
        }

        try {

            $uid = (string)$payload['uid'];

            $service = new TrashService(DbPdo::conn()); 
            // 🔥 QueryService 사용
            $calendarId = $this->query->getTaskCalendarId($uid);

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

            $service->restoreTask($uid, $synologyLoginId);

            $this->json([
                'success' => true,
                'data' => [
                    'uid' => $uid
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

    /* ============================================================
    * 🧹 Bulk Task Delete
    * POST /api/dashboard/calendar/task/delete-bulk
    * ============================================================ */
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

            foreach ($payload['uids'] as $uid) {

                if (!$uid) continue;

                $uid = preg_replace('/^task_/', '', (string)$uid);

                // 🔥 QueryService 사용
                $calendarId = $this->query->getTaskCalendarId($uid);

                if ($calendarId) {
                    $this->assertCalendarWritePermission($calendarId);
                }

                $res = $crud->deleteTask(['uid' => $uid]);

                if (!empty($res['success'])) {
                    $deletedCount++;
                } else {
                    $failed[] = $uid;
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


    /* ============================================================
    * 👤 Profile Summary (Topbar Info)
    * GET /api/dashboard/profile-summary
    * ============================================================ */
    public function apiProfileSummary(): void
    {
        $this->guardApi();

        try {

            $userId = $_SESSION['user']['id'] ?? null;

            if (!$userId) {
                $this->json([
                    'success' => false,
                    'message' => 'unauthorized'
                ], 401);
            }

            $profileService = new ProfileService(DbPdo::conn()); 
            $settingService = new SettingService(DbPdo::conn()); 
            $externalService = new ExternalAccountService(DbPdo::conn()); 

            /* ===============================
            * 사용자
            * =============================== */
            $profile = $profileService->getDetail($userId) ?? [];
            $user    = $profileService->getDetail($userId) ?? [];

            $profileImagePath = $profile['profile_image'] ?? null;

            $profileImageUrl = $profileImagePath
                ? '/api/file/preview?path=' . rawurlencode($profileImagePath)
                : null;

            /* ===============================
            * Synology 시스템 설정
            * =============================== */
            $host       = $settingService->get('synology_host', '');
            $caldavPath = $settingService->get('synology_caldav_path', '/caldav.php/');
            $sslVerify  = (int)$settingService->get('synology_ssl_verify', 1);

            $baseUrl = null;

            if ($host && $caldavPath) {
                $baseUrl = rtrim($host, '/') . '/' . ltrim($caldavPath, '/');
            }

            /* ===============================
            * 사용자 계정 연결 여부
            * =============================== */
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