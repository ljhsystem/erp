<?php
// 경로: PROJECT_ROOT . '/app/Services/Calendar/SyncService.php'
namespace App\Services\Calendar;

use PDO;
use App\Models\Dashboard\CalendarListModel;
use App\Models\Dashboard\CalendarEventModel;
use App\Models\Dashboard\CalendarTaskModel;
use App\Models\Dashboard\CalendarVisibilityModel;
use App\Models\User\ExternalAccountModel;
use App\Services\Calendar\CrudService;
use App\Services\Calendar\Time;
use Core\LoggerFactory;

class SyncService
{
    private readonly PDO $pdo;
    private CrudService $crud;
    private CalendarListModel $listModel;
    private CalendarEventModel $eventModel;
    private CalendarTaskModel $taskModel;
    private CalendarVisibilityModel $visibilityModel;
    private ExternalAccountModel $externalAccount;

    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo        = $pdo;
        $this->crud      = new CrudService($pdo);
        $this->listModel  = new CalendarListModel($pdo);
        $this->eventModel = new CalendarEventModel($pdo);
        $this->taskModel = new CalendarTaskModel($pdo);
        $this->externalAccount = new ExternalAccountModel($pdo);
        $this->visibilityModel = new CalendarVisibilityModel($pdo);
        $this->logger     = LoggerFactory::getLogger('service-calendar.SyncService');

        // ✅ 생성자 진입 로그 (이게 안 찍히면 로거/권한 문제)
        $this->logger->info('[CTOR] SyncService constructed', [
            'sapi' => PHP_SAPI,
            'pid'  => function_exists('getmypid') ? getmypid() : null,
        ]);
    }

    public function isSyncRunning(string $synologyLoginId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT is_running, started_at
            FROM dashboard_calendar_sync_state
            WHERE synology_login_id = :login
            LIMIT 1
        ");

        $stmt->execute([':login' => $synologyLoginId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return false;
        if ((int)$row['is_running'] !== 1) return false;

        $started = strtotime($row['started_at'] ?? '');

        if ($started && (time() - $started) > 300) {
            $this->logger->warning('[SYNC] stale lock - force unlock');

            $this->pdo->prepare("
                UPDATE dashboard_calendar_sync_state
                SET is_running = 0,
                    started_at = NULL,
                    actor = NULL
                WHERE synology_login_id = :login
            ")->execute([':login' => $synologyLoginId]);

            return false;
        }

        return true;
    }

    private function setSyncRunning(
        string $synologyLoginId,
        bool $state,
        ?string $actor = null
    ): void {

        $sql = "
            INSERT INTO dashboard_calendar_sync_state
                (synology_login_id, is_running, started_at, actor)
            VALUES
                (:login, :running, :started_at, :actor)
            ON DUPLICATE KEY UPDATE
                is_running = VALUES(is_running),
                started_at = VALUES(started_at),
                actor = VALUES(actor)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':login'      => $synologyLoginId,
            ':running'    => $state ? 1 : 0,
            ':started_at' => $state ? date('Y-m-d H:i:s') : null,
            ':actor'      => $state ? $actor : null,
        ]);
    }

    /* =========================================================
     * 🚀 전체 동기화 엔트리 (★ 이것만 호출)
     * ========================================================= */
    public function syncAllForSynologyLogin(
        string $synologyLoginId,
        string $ownerUserId,
        string $actor
    ): void {
        $this->logger->info('[SYNCALL] start', [
            'actor' => $actor,
            'synology_login_id' => $synologyLoginId,
            'owner_user_id' => $ownerUserId
        ]);

        try {

            // 1️⃣ 캘린더 리스트 동기화
            $calendarList = $this->syncCalendarList(
                $synologyLoginId,
                $ownerUserId,   // 🔥 추가
                $actor
            );
            $this->logger->info('[SYNCALL] calendar list ready', [
                'count' => count($calendarList),
            ]);

            if (empty($calendarList)) {
                $this->logger->error('[SYNCALL] aborted - calendar list is empty');
                return;
            }

            // 2️⃣ 각 캘린더 동기화
            foreach ($calendarList as $cal) {

                $id   = (string)$cal['id'];
                $href = (string)$cal['href'];
                $type = $cal['type'];

                if ($id === '' || $href === '') {
                    continue;
                }

                if ($type === 'calendar') {
                    $this->syncEventCalendar(
                        $id,
                        $href,
                        $synologyLoginId,
                        $actor
                    );
                }

                if ($type === 'task') {
                    $this->syncTaskCalendar(
                        $id,
                        $href,
                        $synologyLoginId,
                        $actor
                    );
                }
            }

            $this->logger->info('[SYNCALL] done');
        } catch (\Throwable $e) {

            $this->logger->error('[SYNCALL] failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            throw $e;
        }
    }

    private function syncCalendarList(
        string $synologyLoginId,
        string $ownerUserId,
        ?string $actor
    ): array {
        $this->logger->info('[LIST] sync start', ['actor' => $actor]);

        if (!$actor) {
            return [];
        }

        $res = $this->crud->fetchRemoteCalendars();
        if (!$res['success'] || empty($res['data'])) {
            return [];
        }

        $remoteList = $res['data'];
        $activeIds  = [];

        $this->pdo->beginTransaction();

        try {

            foreach ($remoteList as $cal) {

                $href = rtrim((string)($cal['href'] ?? ''), '/') . '/';
                $name = trim((string)($cal['name'] ?? ''));

                if ($href === '' || $name === '') {
                    continue;
                }

                $loginPrincipal = $synologyLoginId;
                $hrefParts = explode('/', trim($href, '/'));
                $ownerPrincipal = $hrefParts[1] ?? $loginPrincipal;

                $isPersonal = ($loginPrincipal === $ownerPrincipal);
                $localId = md5($href);

                $rawType = strtolower((string)($cal['type'] ?? ''));
                $type = (str_contains($rawType, 'task') || str_contains($rawType, 'vtodo'))
                    ? 'task'
                    : 'calendar';

                // 1️⃣ list upsert
                $this->listModel->upsert([
                    'id' => $localId,
                    'name' => $name,
                    'href' => $href,
                    'type' => $type,
                    'owner_user_id' => $ownerUserId,
                    'synology_login_id' => $loginPrincipal,
                    'synology_owner_principal' => $ownerPrincipal,
                    'synology_login_principal' => $loginPrincipal,
                    'is_personal' => $isPersonal ? 1 : 0,
                ], $actor);

                // 🔥 2️⃣ visibility upsert (여기로 이동)
                $this->visibilityModel->upsert([
                    'calendar_id' => $localId,
                    'synology_login_id' => $loginPrincipal,
                    'owner_user_id' => $ownerUserId,
                    'is_visible' => 1,
                ]);

                $activeIds[] = $localId;
            }


            $this->listModel->markVisibilityInactiveMissing(
                $activeIds,
                $synologyLoginId,
                $ownerUserId,
                $actor
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->listModel->getActiveListBySynology($synologyLoginId);
    }


    private function syncEventCalendar(
        string $calendarId,
        string $calendarHref,
        string $synologyLoginId,
        ?string $actor
    ): void {

        $this->logger->info('[EVENT SYNC ENTER]', [
            'calendar_id' => $calendarId
        ]);

        $calendarRow = $this->listModel->findByIdAndLogin(
            $calendarId,
            $synologyLoginId
        );

        if (!$calendarRow) {
            return;
        }

        // 🔐 개인 캘린더 보호
        if ((int)$calendarRow['is_personal'] === 1) {
            if ($calendarRow['synology_login_id'] !== $synologyLoginId) {
                return;
            }
        }

        $realSynologyLoginId = $calendarRow['synology_login_id'];

        $eventsRes = $this->crud->getEvents(
            $calendarHref,
            date('Y-m-d', strtotime('-1 year')),
            date('Y-m-d', strtotime('+2 years'))
        );


        // 🔒 Remote fetch 실패 시 삭제 판단 절대 금지
        if (!$eventsRes['success']) {
            return;
        }

        $events = $eventsRes['data'] ?? [];

        /* =====================================================
        * 1️⃣ UPSERT (Soft Delete 보호 포함)
        * ===================================================== */
        foreach ($events as $event) {

            if (empty($event['id'])) {
                continue;
            }

            $realCalendarId =
                $event['__meta']['calendar_id']
                ?? $event['calendar_id']
                ?? $calendarId;

            if (!$realCalendarId) {
                continue;
            }

            // 🔥 1. 기존 레코드 조회
            $existing = $this->eventModel->findAnyByUid(
                $event['id'],
                $realSynologyLoginId
            );

            // 🔥 2. Soft delete 보호
            if ($existing && (int)$existing['is_active'] === 0) {
                // 휴지통에 있는 것은 절대 복구 금지
                continue;
            }

            $payload = $this->mapSynologyEvent(
                $event,
                $calendarId,
                $realSynologyLoginId,
                $actor
            );

            $this->eventModel->upsert($payload);
        }

        /* =====================================================
        * 2️⃣ 삭제 감지
        * ===================================================== */

        $remoteUids = [];

        foreach ($events as $event) {
            if (!empty($event['id'])) {
                $remoteUids[] = $event['id'];
            }
        }

        $dbUids = $this->eventModel->getActiveUidsByCalendar(
            $calendarId,
            $realSynologyLoginId
        );

        $missing = array_diff($dbUids, $remoteUids);

        foreach ($missing as $id) {

            $row = $this->eventModel->findAnyByUid(
                $id,
                $realSynologyLoginId
            );

            if (!$row) {
                continue;
            }

            // 이미 휴지통이면 skip
            if ((int)$row['is_active'] === 0) {
                continue;
            }

            // 복원 보호
            if (!empty($row['restored_at'])) {

                $restored = strtotime($row['restored_at']);

                if ($restored && (time() - $restored) < 300) {
                    continue;
                }
            }

            // ERP 생성 이벤트 보호
            if (!empty($row['raw_ics'])) {
                continue;
            }

            // 🔥 Synology 원본 삭제 상태 기록
            $this->eventModel->markSynologyMissing(
                $id,
                $calendarId,
                $realSynologyLoginId
            );
        }

        $this->logger->debug('[EVENT SYNC] deletion check skipped', [
            'calendar_id' => $calendarId,
        ]);
    }






    private function syncTaskCalendar(
        string $calendarId,
        string $calendarHref,
        string $synologyLoginId,
        ?string $actor
    ): void {

        $this->logger->info('[TASK] sync start', [
            'calendar_id' => $calendarId,
            'href'        => $calendarHref,
        ]);

        $tasksRes = $this->crud->getTasks($calendarHref, null, null);

        // 🔒 fetch 실패 시 삭제 판단 금지
        if (!$tasksRes['success']) {
            $this->logger->error('[TASK] fetch failed', [
                'calendar_id' => $calendarId,
            ]);
            return;
        }

        $tasks = $tasksRes['data'] ?? [];

        $this->logger->info('[TASK] fetched', [
            'calendar_id' => $calendarId,
            'count'       => count($tasks),
        ]);

        $calendarRow = $this->listModel->findByIdAndLogin(
            $calendarId,
            $synologyLoginId
        );

        if (!$calendarRow) {
            return;
        }

        // 🔐 개인 캘린더 보호
        if ((int)$calendarRow['is_personal'] === 1) {
            if ($calendarRow['synology_login_id'] !== $synologyLoginId) {
                return;
            }
        }

        $realSynologyLoginId = $calendarRow['synology_login_id'];

        /* ===============================
        * 1️⃣ UPSERT
        * =============================== */
        foreach ($tasks as $task) {

            if (empty($task['id'])) {
                continue;
            }

            // 🔥 soft-deleted 보호
            $realSynologyLoginId = $calendarRow['synology_login_id'];

            $existing = $this->taskModel->findAnyByUid(
                $task['id'],
                $realSynologyLoginId
            );

            if ($existing && (int)$existing['is_active'] === 0) {
                // 이미 soft delete 된 것은 절대 복구 금지
                continue;
            }

            $payload = $this->mapSynologyTask(
                $task,
                $calendarId,
                $realSynologyLoginId,
                $actor
            );

            $this->taskModel->upsert($payload);
        }


        /* ===============================
         * 2️⃣ 삭제 판단 (데이터 있을 때만)
         * =============================== */

        $remoteUids = [];

        foreach ($tasks as $task) {

            if (empty($task['id'])) {
                continue;
            }

            $id = preg_replace('/^task_/', '', $task['id']);

            $remoteUids[] = $id;
        }

        $dbUids = $this->taskModel->getActiveUidsByCalendar(
            $calendarId,
            $realSynologyLoginId
        );

        $missing = array_diff($dbUids, $remoteUids);

        foreach ($missing as $id) {

            $row = $this->taskModel->findAnyByUid(
                $id,
                $realSynologyLoginId
            );

            if (!$row) {
                continue;
            }

            // 이미 휴지통이면 skip
            if ((int)$row['is_active'] === 0) {
                continue;
            }

            // 복원 보호
            if (!empty($row['restored_at'])) {

                $restored = strtotime($row['restored_at']);

                if ($restored && (time() - $restored) < 300) {
                    continue;
                }
            }

            // ERP 생성 task 보호
            if (!empty($row['raw_ics'])) {
                continue;
            }

            // 🔥 Synology 원본 삭제 상태 기록
            $this->taskModel->markSynologyMissing(
                $id,
                $calendarId,
                $realSynologyLoginId
            );
        }

        $this->logger->info('[TASK] sync done', [
            'calendar_id' => $calendarId,
            'count'       => count($tasks),
        ]);
    }



    /* =========================================================
    * 🔧 Synology Event → DB Payload (REAL FIX)
    * ========================================================= */
    private function mapSynologyEvent(
        array $event,
        string $calendarId,
        ?string $realSynologyLoginId,
        ?string $actor
    ): array {
        // 🔥 Synology raw = VEVENT
        $vevent = is_array($event['raw'] ?? null) ? $event['raw'] : [];

        // RAW 안전 접근기
        $rawValue = static function (array $v, string $key) {
            if (!isset($v[$key])) return null;
            return $v[$key]['value'] ?? null;
        };

        // DTSTART / DTEND
        $dtstartRaw = $rawValue($vevent, 'DTSTART') ?? $event['dtstart'] ?? null;
        $dtendRaw   = $rawValue($vevent, 'DTEND')   ?? $event['dtend']   ?? null;

        $allDay = (($vevent['DTSTART']['params']['VALUE'] ?? null) === 'DATE') ? 1 : 0;

        if ($allDay && $dtstartRaw && $dtendRaw) {

            // Synology 종일은 DTEND가 +1 day 이므로
            $startDate = substr($dtstartRaw, 0, 8);
            $endDate   = substr($dtendRaw,   0, 8);

            $dtstart = $startDate;

            $dtend = Time::parseLocal($endDate)
                ->modify('-1 day')
                ->format('Ymd');
        } else {

            $dtstart = $dtstartRaw;
            $dtend   = $dtendRaw;
        }

        // ALL DAY
        $allDay = (($vevent['DTSTART']['params']['VALUE'] ?? null) === 'DATE') ? 1 : 0;

        // RECURRENCE
        $rrule  = $rawValue($vevent, 'RRULE') ?? ($event['rrule'] ?? null);
        $rdate  = $vevent['RDATE']  ?? [];
        $exdate = $vevent['EXDATE'] ?? [];

        // 🔥 단일 진실: 이벤트 자체 calendar_id
        $realCalendarId =
            $event['__meta']['calendar_id']
            ?? $event['calendar_id']
            ?? $calendarId;

        if (!$realCalendarId) {
            throw new \RuntimeException('calendar_id missing from event');
        }
        /* =========================================================
        * 🔥 ERP 확장필드 보존 (admin_event_color)
        * - Synology에는 없는 값이므로 Sync 때 DB값을 유지해야 함
        * - 없으면 null
        * ========================================================= */
        $existingAdminColor = null;

        $uidForLookup = (string)($event['id'] ?? '');
        if ($uidForLookup !== '') {
            try {
                $existingRow = $this->eventModel->findByUidAndCalendar(
                    $uidForLookup,
                    $realCalendarId,
                    $realSynologyLoginId
                );
                if (is_array($existingRow) && array_key_exists('admin_event_color', $existingRow)) {
                    $existingAdminColor = $existingRow['admin_event_color'];
                }
            } catch (\Throwable $e) {
                // lookup 실패해도 sync는 계속
                $existingAdminColor = null;
            }
        }

        $existing = null;

        if (!empty($event['id'])) {
            $existing = $this->eventModel->findByUidAndCalendar(
                $event['id'],
                $realCalendarId,
                $realSynologyLoginId
            );
        }

        $alarmsJson =
            !empty($event['alarms'])
            ? json_encode($event['alarms'], JSON_UNESCAPED_UNICODE)
            : ($existing['alarms_json'] ?? null);

        return [
            /* ===============================
            * 식별
            * =============================== */
            'id'         => $event['id'] ?? null,
            'calendar_id' => $realCalendarId,
            'owner_user_id' => $actor,
            'synology_login_id' => $realSynologyLoginId,
            'calendar_type' => 'external',
            'href'        => $event['_href'] ?? null,
            'etag'        => $event['_etag'] ?? null,
            'type'        => 'VEVENT',

            /* ===============================
            * 메타
            * =============================== */
            'sequence'      => isset($event['sequence']) ? (int)$event['sequence'] : null,
            'dtstamp'       => $rawValue($vevent, 'DTSTAMP'),
            'created'       => $rawValue($vevent, 'CREATED'),
            'last_modified' => $rawValue($vevent, 'LAST-MODIFIED'),

            /* ===============================
            * 기본 정보
            * =============================== */
            'title'       => $rawValue($vevent, 'SUMMARY'),
            'description' => $rawValue($vevent, 'DESCRIPTION'),
            'location'    => $rawValue($vevent, 'LOCATION'),

            /* ===============================
            * 날짜
            * =============================== */
            'dtstart' => $dtstart,
            'dtend'   => $dtend,
            'all_day' => $allDay,

            /* ===============================
            * 색상
            * =============================== */
            'event_color' =>
            $rawValue($vevent, 'X-SYNO-EVT-COLOR')
                ?? $rawValue($vevent, 'COLOR'),

            // ✅ ERP 확장필드 (DB 보존)
            'admin_event_color' =>
            $existing['admin_event_color'] ?? null,

            /* ===============================
            * 상태
            * =============================== */
            'status'   => $rawValue($vevent, 'STATUS'),
            'priority' => isset($vevent['PRIORITY']['value'])
                ? (int)$vevent['PRIORITY']['value']
                : null,
            'transp'   => $rawValue($vevent, 'TRANSP'),

            /* ===============================
            * JSON
            * =============================== */
            'alarms_json' => $alarmsJson,

            'attendees_json' =>
            isset($vevent['ATTENDEE'])
                ? json_encode($vevent['ATTENDEE'], JSON_UNESCAPED_UNICODE)
                : null,

            'recurrence_json' => ($rrule || !empty($rdate) || !empty($exdate))
                ? json_encode([
                    'rrule'  => $rrule,
                    'rdate'  => $rdate,
                    'exdate' => $exdate,
                ], JSON_UNESCAPED_UNICODE)
                : null,

            'categories_json' =>
            isset($vevent['CATEGORIES'])
                ? json_encode($vevent['CATEGORIES'], JSON_UNESCAPED_UNICODE)
                : null,

            'comments_json' =>
            isset($vevent['COMMENT'])
                ? json_encode($vevent['COMMENT'], JSON_UNESCAPED_UNICODE)
                : null,

            'attachments_json' =>
            isset($vevent['ATTACH'])
                ? json_encode($vevent['ATTACH'], JSON_UNESCAPED_UNICODE)
                : null,

            /* ===============================
            * RAW
            * =============================== */
            'raw_json' => json_encode($event, JSON_UNESCAPED_UNICODE),
        ];
    }




    /* =========================================================
    * 🔧 Synology Task (VTODO) → DB Payload (STABLE FINAL)
    * ========================================================= */
    private function mapSynologyTask(
        array $task,
        string $calendarId,
        ?string $realSynologyLoginId,
        ?string $actor
    ): array {
        $raw = is_array($task['raw'] ?? null) ? $task['raw'] : [];

        $rawValue = static function (array $raw, string $key) {
            if (!isset($raw[$key])) return null;
            $v = $raw[$key];
            return is_array($v) && array_key_exists('value', $v)
                ? $v['value']
                : null;
        };

        /* ===============================
        * 🔥 기존 DB 값 조회 (보존용)
        * =============================== */
        $existing = null;
        if (!empty($task['id'])) {
            $existing = $this->taskModel->findAnyByUid(
                $task['id'],
                $realSynologyLoginId
            );
        }

        /* ===============================
        * 제목 / 설명
        * =============================== */
        $title =
            $task['title']
            ?? $rawValue($raw, 'SUMMARY')
            ?? ($existing['title'] ?? null);

        $rawDescription = $rawValue($raw, 'DESCRIPTION');

        if ($rawDescription !== null && trim($rawDescription) !== '') {
            $description = $rawDescription;
        } elseif (!empty($task['description'])) {
            $description = $task['description'];
        } elseif (!empty($existing['description'])) {
            $description = $existing['description'];
        } else {
            $description = null;
        }

        /* ===============================
        * 🔥 날짜 처리 (CalendarTime 통일 버전)
        * - DateTime/DateTimeZone 직접 사용 금지
        * - CalendarTime::parseLocal() 단일화
        * =============================== */

        $dueRaw   = $task['due']   ?? $rawValue($raw, 'DUE');
        $startRaw = $task['start'] ?? $rawValue($raw, 'DTSTART');

        $dueForDb = null;
        $dueYmd   = null;
        $startYmd = null;

        /**
         * VALUE=DATE 판정
         * - raw 파서 구조가 없을 수도 있으니 ?? []로 방어
         */
        $dueIsDateParam   = (((($raw['DUE'] ?? [])['params']['VALUE'] ?? null) === 'DATE'));
        $startIsDateParam = (((($raw['DTSTART'] ?? [])['params']['VALUE'] ?? null) === 'DATE'));

        /* ===============================
        * DUE 처리
        * =============================== */
        if ($dueRaw) {

            // ✅ DATE 타입: 날짜만 저장 (시간 삭제)
            if ($dueIsDateParam || preg_match('/^\d{8}$/', (string)$dueRaw)) {

                $dt = Time::parseLocal((string)$dueRaw);
                $dueYmd   = $dt->format('Ymd');
                $dueForDb = $dt->format('Y-m-d');
            } else {

                // ✅ 시간 타입: 서울시간으로 normalize
                $dt = Time::parseLocal((string)$dueRaw);
                $dueYmd   = $dt->format('Ymd');
                $dueForDb = $dt->format('Y-m-d H:i:s');
            }
        }

        /* ===============================
        * DTSTART 처리
        * =============================== */
        if ($startRaw) {

            // ✅ DTSTART도 parseLocal로 통일
            $dt = Time::parseLocal((string)$startRaw);
            $startYmd = $dt->format('Ymd');
        } elseif (is_array($existing) && !empty($existing['start_ymd'])) {

            // 🔥 기존 DB 값 유지
            $startYmd = $existing['start_ymd'];
        }

        /* ===============================
        * all_day 판정 (단일 기준)
        * - DUE 또는 DTSTART 중 하나라도 VALUE=DATE면 all_day
        * =============================== */
        $allDay = ($dueIsDateParam || $startIsDateParam) ? 1 : 0;

        /* ===============================
        * 🔥 알람 보존
        * =============================== */
        $alarmsJson = null;

        if (!empty($task['alarms']) && is_array($task['alarms'])) {
            $alarmsJson = json_encode($task['alarms'], JSON_UNESCAPED_UNICODE);
        }

        if ($alarmsJson === null && is_array($existing) && array_key_exists('alarms_json', $existing)) {
            $alarmsJson = $existing['alarms_json'];
        }


        /* ===============================
        * 🔥 href / collection / etag (SAFE FINAL)
        * =============================== */

        $href = $task['_href'] ?? null;
        $etag = $task['_etag'] ?? null;

        /* 🔥 기존값 보호 */
        if (!$href && is_array($existing)) {
            $href = $existing['href'] ?? null;
        }
        if (!$etag && is_array($existing)) {
            $etag = $existing['etag'] ?? null;
        }

        /* 🔥 collection 추출 (절대 안정형) */
        $collectionHref = null;

        if ($href) {
            $collectionHref = rtrim(dirname($href), '/') . '/';
        }

        if (!$collectionHref && is_array($existing)) {
            $collectionHref = $existing['collection_href'] ?? null;
        }



        /* =========================================================
        * 🔥 STATUS 처리 (Synology → ERP 안정화 로직)
        *
        * 1️⃣ Synology VTODO의 STATUS 값을 우선 사용
        *    - NEEDS-ACTION
        *    - COMPLETED
        *    - CANCELLED
        *
        * 2️⃣ Synology 응답에 STATUS가 없는 경우:
        *    - 기존 DB에 저장된 값을 유지
        *    - (Sync 중 STATUS 누락 보호 목적)
        *
        * 3️⃣ 기존 값도 없다면 기본값 NEEDS-ACTION 적용
        *
        * 👉 목적:
        *    - Sync 중 STATUS가 null로 덮어써지는 것 방지
        *    - Synology 응답 누락/부분 응답 대응
        *    - ERP 단일 상태 일관성 유지
        *
        * ========================================================= */

        // 🔹 Synology raw STATUS 값 읽기
        $status = $rawValue($raw, 'STATUS');

        if (!$status) {

            // 🔹 Synology에서 STATUS가 안 온 경우
            //     → 기존 DB 값 유지
            if (!empty($existing['status'])) {
                $status = $existing['status'];
            } else {

                // 🔹 기존 값도 없으면 기본값
                $status = 'NEEDS-ACTION';
            }
        }

        // 🔥 상태값은 항상 대문자로 정규화
        //    (DB 일관성 유지 / 비교 안정성 확보)
        $status = strtoupper(trim($status));

        /* =========================================================
        * 🔎 디버그 로그
        * - Sync 중 상태가 어떻게 결정되었는지 추적
        * - STATUS 누락 문제 분석용
        * ========================================================= */
        $this->logger->debug('[MAP TASK STATUS]', [
            'id' => $task['id'] ?? null,
            'raw_status' => $rawValue($raw, 'STATUS'),
            'existing_status' => $existing['status'] ?? null,
            'final_status' => $status,
        ]);

        return [
            'id'         => $task['id'] ?? null,
            'calendar_id' => $calendarId,
            'owner_user_id' => $actor,
            'synology_login_id' => $realSynologyLoginId,
            'calendar_type' => 'external',
            'href'        => $href,
            'collection_href' => $collectionHref,
            'etag'        => $etag,

            'title'       => $title,
            'description' => $description,

            // 🔥 여기 수정
            'due'        => $dueForDb,
            'start'      => $startRaw,
            'due_ymd'    => $dueYmd,
            'start_ymd'  => $startYmd,
            'all_day' => $allDay,

            'status' => $status,

            'percent_complete' => isset($raw['PERCENT-COMPLETE']['value'])
                ? (int)$raw['PERCENT-COMPLETE']['value']
                : 0,

            'completed' => $rawValue($raw, 'COMPLETED') ? 1 : 0,

            'priority' => isset($raw['PRIORITY']['value'])
                ? (int)$raw['PRIORITY']['value']
                : null,

            'alarms_json' => $alarmsJson,

            'raw_json' => json_encode($task, JSON_UNESCAPED_UNICODE),
            'synology_exists' => 1,

            'created_by' => $existing['created_by'] ?? $actor,
            'updated_by' => $actor,
        ];
    }


    /* =========================================================
    * 🚀 단일 이벤트 동기화 (Synology → DB 캐시 ONLY)
    * - UI 보호 ❌
    * - 삭제 판단 ❌
    * - 무조건 UPSERT
    * ========================================================= */
    public function syncOneEventByUid(
        string $id,
        string $arg2,
        ?string $actor = null,
        array $extra = []
    ): array {
        /*
        ------------------------------------------------------------
        🔥 하위 호환 처리
        ------------------------------------------------------------
        예전 방식:
            syncOneEventByUid($id, $userId, $extra)

        새 방식:
            syncOneEventByUid($id, $synologyLoginId, $actor, $extra)
        ------------------------------------------------------------
        */

        // 🔥 3번째 인자가 array이면 → 예전 호출 방식
        if (is_array($actor)) {
            $extra = $actor;
            $actor = $arg2; // userId
            $synologyLoginId = $this->resolveSynologyLoginId($actor);
        } else {
            // 🔥 신형 호출
            $synologyLoginId = $arg2;
        }

        if ($id === '') {
            return ['status' => 'noop', 'event' => null];
        }

        $res = $this->crud->getEventByUid($id);

        if (!$res['success'] || empty($res['data'])) {

            // 🔥 Synology 응답 지연 보호
            $row = $this->eventModel->findAnyByUid(
                $id,
                $synologyLoginId
            );

            if ($row) {
                return ['status' => 'pending', 'event' => $row];
            }

            return ['status' => 'noop', 'event' => null];
        }

        $event = $res['data'];

        // 🔥 1순위: create에서 전달된 calendar_id
        $calendarId =
            $extra['calendar_id']
            ?? $event['__meta']['calendar_id']
            ?? $event['calendar_id']
            ?? null;

        if (!$calendarId) {
            throw new \RuntimeException(
                'calendar_id missing for id: ' . $id
            );
        }

        // 🔄 Synology → DB
        $calendarRow = $this->listModel->findByIdAndLogin(
            $calendarId,
            $synologyLoginId
        );

        if (!$calendarRow) {
            return ['status' => 'noop', 'event' => null];
        }

        // 🔐 개인 보호
        if ((int)$calendarRow['is_personal'] === 1) {
            if ($calendarRow['synology_login_id'] !== $synologyLoginId) {
                return ['status' => 'forbidden', 'event' => null];
            }
        }


        // 🔥 Synology → DB payload 생성
        $realSynologyLoginId = $calendarRow['synology_login_id'];

        $payload = $this->mapSynologyEvent(
            $event,
            $calendarId,
            $realSynologyLoginId,
            $actor
        );

        // 🔥 ERP 확장 필드 병합
        if (array_key_exists('admin_event_color', $extra)) {
            $payload['admin_event_color'] = $extra['admin_event_color'];
        }

        if (!empty($extra['force_update_id'])) {

            $this->eventModel->updateById(
                (int)$extra['force_update_id'],
                $payload
            );
        } else {

            $this->eventModel->upsert($payload);
        }


        $row = $this->eventModel->findByUidAndCalendar(
            $id,
            $calendarId,
            $realSynologyLoginId
        );

        return [
            'status' => 'synced',
            'event'  => $row,
        ];
    }




    /* =========================================================
    * 🚀 단일 태스크 동기화 (Synology → DB 캐시 ONLY)
    * - UI 보호 ❌
    * - 삭제 판단 ❌
    * - 무조건 UPSERT
    * ========================================================= */
    public function syncOneTaskByUid(
        string $id,
        string $synologyLoginId,
        ?string $actor = null,
        array $extra = []
    ): array {
        $id = preg_replace('/^task_/', '', $id);

        if ($id === '') {
            return ['status' => 'noop', 'task' => null];
        }

        $task = null;

        /**
         * =========================================================
         * 🔥 1️⃣ force_href 있으면 즉시 direct GET
         * =========================================================
         */
        if (!empty($extra['force_href'])) {

            try {
                $direct = $this->crud->getTaskByUid(
                    $id,
                    $extra['collection_href'] ?? null,
                    $extra   // 🔥 force_href 전달
                );

                if ($direct['success'] && !empty($direct['data'])) {
                    $task = $direct['data'];
                }
            } catch (\Throwable $e) {
                return ['status' => 'error', 'task' => null];
            }
        } else {

            /**
             * =========================================================
             * 🔥 2️⃣ 일반 sync 경로
             * =========================================================
             */
            $res = $this->crud->getTaskByUid(
                $id,
                $extra['collection_href'] ?? null,
                $extra
            );

            if (!$res['success']) {
                return ['status' => 'error', 'task' => null];
            }

            if (!empty($res['data'])) {
                $task = $res['data'];
            }
        }

        /**
         * =========================================================
         * 🔥 3️⃣ 여전히 못 찾았을 경우
         * - force 상황이면 삭제 처리 ❌
         * - 일반 sync면 삭제 처리 ⭕
         * =========================================================
         */
        if (!$task) {

            // 🔥 create 직후 보호
            if (!empty($extra['force_href'])) {
                return ['status' => 'pending', 'task' => null];
            }

            // 🔥 owner를 모를 수 있으므로 owner 없이 조회
            $existing = $this->taskModel->findAnyByUid(
                $id,
                $synologyLoginId
            );

            if ($existing && (int)$existing['synology_exists'] === 0) {
                return [
                    'status' => 'skipped-synology-deleted',
                    'task' => $existing
                ];
            }

            if ($existing) {

                // 🔥 ERP 생성 task 보호
                if (!empty($existing['raw_ics'])) {
                    return ['status' => 'erp-only', 'task' => $existing];
                }

                $this->taskModel->markInactive(
                    $id,
                    $existing['calendar_id'],
                    $existing['owner_user_id'],
                    $actor
                );
            }

            return ['status' => 'deleted', 'task' => null];
        }

        /**
         * =========================================================
         * 🔥 4️⃣ calendar_id 결정
         * =========================================================
         */
        $calendarId =
            $extra['calendar_id']
            ?? $task['__meta']['calendar_id']
            ?? $task['calendar_id']
            ?? null;

        if (!$calendarId) {
            throw new \RuntimeException(
                'calendar_id missing for task id: ' . $id
            );
        }

        /**
         * =========================================================
         * 🔥 5️⃣ UPSERT
         * =========================================================
         */
        $calendarRow = $this->listModel->findByIdAndLogin(
            $calendarId,
            $synologyLoginId
        );

        if (!$calendarRow) {
            return ['status' => 'noop', 'task' => null];
        }

        // 🔐 개인 보호
        if ((int)$calendarRow['is_personal'] === 1) {
            if ($calendarRow['synology_login_id'] !== $synologyLoginId) {
                return ['status' => 'forbidden', 'task' => null];
            }
        }

        $realSynologyLoginId = $calendarRow['synology_login_id'];

        $payload = $this->mapSynologyTask(
            $task,
            $calendarId,
            $realSynologyLoginId,
            $actor
        );

        // 🔥 기존 존재 여부 확인
        $existing = $this->taskModel->findAnyByUid(
            $id,
            $realSynologyLoginId
        );

        // 🔥 soft delete 보호
        if ($existing && (int)$existing['is_active'] === 0) {
            return ['status' => 'skipped-soft-deleted', 'task' => $existing];
        }

        // 🔥 UPSERT
        $this->taskModel->upsert($payload);

        // 🔥 재조회
        $row = $this->taskModel->findByUidAndCalendar(
            $id,
            $calendarId,
            $realSynologyLoginId
        );

        return [
            'status' => 'synced',
            'task'   => $row,
        ];
    }




    /* =========================================================
    * 🔥 FULL CACHE REBUILD (강제 전체 재동기화)
    * - TTL 없음
    * - SyncState 없음
    * - DB 캐시 완전 재구성
    * ========================================================= */
    public function rebuildFullCache(
        string $synologyLoginId,
        string $ownerUserId,
        string $actor
    ): void {
        $this->logger->warning('[REBUILD] FULL CACHE REBUILD START', [
            'actor' => $actor,
        ]);

        if (!$actor) {
            throw new \RuntimeException('actor required for rebuild');
        }


        if (!$actor) {
            throw new \RuntimeException('actor required for rebuild');
        }


        if ($this->isSyncRunning($synologyLoginId)) {
            $this->logger->warning('[REBUILD] skipped - already running');
            return;
        }

        $this->setSyncRunning($synologyLoginId, true, $actor);

        try {

            // 1️⃣ 캘린더 리스트 최신화
            $calendarList = $this->syncCalendarList(
                $synologyLoginId,
                $ownerUserId,   // 🔥 ERP user id
                $actor
            );

            if (empty($calendarList)) {
                $this->logger->error('[REBUILD] calendar list empty');
                return;
            }

            // 2️⃣ 이벤트 / 태스크 전체 재동기화
            foreach ($calendarList as $cal) {

                $calendarId   = (string)$cal['id'];
                $calendarHref = (string)$cal['href'];
                $type         = $cal['type'];

                if ($calendarId === '' || $calendarHref === '') {
                    continue;
                }

                if ($type === 'calendar') {
                    $this->syncEventCalendar(
                        $calendarId,
                        $calendarHref,
                        $synologyLoginId,
                        $actor
                    );
                }

                if ($type === 'task') {
                    $this->syncTaskCalendar(
                        $calendarId,
                        $calendarHref,
                        $synologyLoginId,
                        $actor
                    );
                }
            }
            $this->setSyncRunning($synologyLoginId, false);
            $this->logger->warning('[REBUILD] FULL CACHE REBUILD DONE');
        } catch (\Throwable $e) {

            $this->setSyncRunning($synologyLoginId, false);
            $this->logger->error('[REBUILD] FAILED', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            throw $e;
        }
    }

    public function resolveSynologyLoginId(string $userId): string
    {
        $external = $this->externalAccount
            ->getByUserAndService($userId, 'synology');

        if (!$external || empty($external['external_login_id'])) {
            throw new \RuntimeException('Synology account not found');
        }

        return $external['external_login_id'];
    }


    public function trySyncLock(string $loginId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE dashboard_calendar_sync_state
            SET started_at = NOW()
            WHERE synology_login_id = :login
            AND (
                started_at IS NULL
                OR started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            )
        ");

        $stmt->execute([':login' => $loginId]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        // 최초 row 없으면 생성
        $this->pdo->prepare("
            INSERT IGNORE INTO dashboard_calendar_sync_state
            (synology_login_id, started_at)
            VALUES (:login, NOW())
        ")->execute([':login' => $loginId]);

        return true;
    }

    public function syncIfNeeded(
        string $synologyLoginId,
        ?string $ownerUserId,
        ?string $actor
    ): void {

        if (!$this->trySyncLock($synologyLoginId)) {
            return;
        }

        $this->quickSync(
            $synologyLoginId,
            $ownerUserId,
            $actor
        );
    }
    public function quickSync(
        string $synologyLoginId,
        ?string $ownerUserId,
        ?string $actor
    ): void {

        $this->logger->info('[QUICK SYNC] start');

        // 캘린더 목록만 동기화
        $this->syncCalendarList(
            $synologyLoginId,
            $ownerUserId,
            $actor
        );

        $this->logger->info('[QUICK SYNC] done');
    }
}
