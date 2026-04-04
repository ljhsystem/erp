<?php
// 경로: PROJECT_ROOT . '/app/Services/Calendar/QueryService.php'
namespace App\Services\Calendar;

use PDO;
use App\Services\Calendar\Time;
use Core\LoggerFactory;

class QueryService
{
    private PDO $pdo;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->logger = LoggerFactory::getLogger('service-calendar.CalendarQueryService');
    }

    /* =========================================================
     * 📅 캘린더 목록 (DB 기준 단일 진실)
     * ========================================================= */
    public function getCalendarList(
        bool $onlyActive,
        string $ownerId,
        string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT
                c.id AS calendar_id,
                c.name,
                c.href,
                c.admin_calendar_color,
                c.type,
                c.is_personal,
                c.owner_user_id
            FROM dashboard_calendar_list c
            JOIN dashboard_calendar_visibility v
                ON v.calendar_id = c.id
               AND v.synology_login_id = ?
               AND v.is_visible = 1
            WHERE c.is_active = 1
        ";
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$synologyLoginId]);
    
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $result = [];
    
        foreach ($rows as $row) {
            $href = rtrim((string)($row['href'] ?? ''), '/') . '/';
    
            $result[] = [
                'calendar_id'          => $row['calendar_id'],
                'name'                 => $row['name'],
                'href'                 => $href,
                'collection_href'      => $href,
                'admin_calendar_color' => $row['admin_calendar_color'],
                'type'                 => $row['type'],
                'is_personal'          => (int)($row['is_personal'] ?? 0),
                'owner_user_id'        => $row['owner_user_id'] ?? null,
            ];
        }
    
        return $result;
    }
    
    
    

    /* =========================================================
    * 📆 이벤트 조회 (DB)
    * ========================================================= */
    public function getEvents(
        string $calendarId,
        ?string $from,
        ?string $to,
        string $ownerId,
        ?string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT e.*
            FROM dashboard_calendar_events e
            JOIN dashboard_calendar_list c
                ON c.id = e.calendar_id
            JOIN dashboard_calendar_visibility v
                ON v.calendar_id = e.calendar_id
               AND v.synology_login_id = ?
               AND v.is_visible = 1
            WHERE e.calendar_id = ?
              AND e.synology_login_id = ?
              AND e.owner_user_id = ?
              AND e.is_active = 1
              AND (
                    (c.is_personal = 1 AND c.owner_user_id = ?)
                 OR (c.is_personal = 0)
              )
              AND (
                    (
                        e.dtstart_ymd <= ?
                        AND (
                            e.dtend_ymd IS NULL
                            OR e.dtend_ymd = ''
                            OR e.dtend_ymd >= ?
                        )
                    )
                    OR e.recurrence_json IS NOT NULL
              )
        ";
    
        $params = [
            $synologyLoginId,
            $calendarId,
            $synologyLoginId,
            $ownerId,
            $ownerId,
            str_replace('-', '', $to),
            str_replace('-', '', $from),
        ];
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    

    /* =========================================================
     * 🧾 태스크 조회 (DB)
     * ========================================================= */
    public function getTasks(
        string $calendarId,
        ?string $from,
        ?string $to,
        string $ownerId,
        ?string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT t.*
            FROM dashboard_calendar_tasks t
            JOIN dashboard_calendar_list c
                ON c.id = t.calendar_id
            JOIN dashboard_calendar_visibility v
                ON v.calendar_id = t.calendar_id
               AND v.synology_login_id = ?
               AND v.is_visible = 1
            WHERE t.calendar_id = ?
              AND t.synology_login_id = ?
              AND t.owner_user_id = ?
              AND t.is_active = 1
              AND (
                    (c.is_personal = 1 AND c.owner_user_id = ?)
                 OR (c.is_personal = 0)
              )
        ";
    
        $params = [
            $synologyLoginId,
            $calendarId,
            $synologyLoginId,
            $ownerId,
            $ownerId
        ];
    
        if ($from && $to) {
            $sql .= "
                AND (
                    t.due_ymd IS NULL
                    OR (t.due_ymd BETWEEN ? AND ?)
                )
            ";
            $params[] = str_replace('-', '', $from);
            $params[] = str_replace('-', '', $to);
        }
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    /* =========================================================
     * 📦 이벤트 + 태스크 통합 (FullCalendar)
     * ========================================================= */
    public function getEventsAndTasks(
        string $calendarId,
        ?string $from,
        ?string $to,
        string $ownerId,
        ?string $synologyLoginId
    ): array {
    
        $events = $this->getEvents(
            $calendarId,
            $from,
            $to,
            $ownerId,
            $synologyLoginId
        );
        
        $tasks = $this->getTasks(
            $calendarId,
            $from,
            $to,
            $ownerId,
            $synologyLoginId
        );
    
        // 🔥 이벤트 매핑
        $mappedEvents = array_map(
            fn($row) => $this->mapEventRow($row),
            $events
        );
    
        // 🔥 태스크 매핑 (핵심)
        $mappedTasks = array_map(
            fn($row) => $this->mapTaskRow($row),
            $tasks
        );
    
        return array_merge($mappedEvents, $mappedTasks);
    }



    public function getActiveCalendarList(
        string $ownerId,
        string $synologyLoginId
    ): array {
        return $this->getCalendarList(
            true,
            $ownerId,
            $synologyLoginId
        );
    }
    

    public function getEventsByPeriod(
        ?string $from,
        ?string $to,
        string $ownerId,
        ?string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT e.*
            FROM dashboard_calendar_events e
            JOIN dashboard_calendar_list c
                ON c.id = e.calendar_id
            JOIN dashboard_calendar_visibility v
                ON v.calendar_id = e.calendar_id
               AND v.synology_login_id = ?
               AND v.is_visible = 1
            WHERE e.synology_login_id = ?
            AND e.owner_user_id = ?
            AND e.is_active = 1
            AND (
                    (c.is_personal = 1 AND c.owner_user_id = ?)
                OR (c.is_personal = 0)
            )
              AND (
                    e.recurrence_json IS NOT NULL
                 OR (
                        e.dtstart_ymd <= ?
                        AND (
                            e.dtend_ymd IS NULL
                            OR e.dtend_ymd = ''
                            OR e.dtend_ymd >= ?
                        )
                    )
              )
        ";
    
        $params = [
            $synologyLoginId,
            $synologyLoginId,
            $ownerId,
            $ownerId,
            str_replace('-', '', $to),
            str_replace('-', '', $from),
        ];
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    
    
    public function getTasksByPeriod(
        ?string $from,
        ?string $to,
        string $ownerId,
        ?string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT t.*
            FROM dashboard_calendar_tasks t
            JOIN dashboard_calendar_list c
                ON c.id = t.calendar_id
            JOIN dashboard_calendar_visibility v
                ON v.calendar_id = t.calendar_id
               AND v.synology_login_id = ?
               AND v.is_visible = 1
            WHERE t.synology_login_id = ?
              AND t.owner_user_id = ?   
              AND t.is_active = 1           
              AND (
                    (c.is_personal = 1 AND c.owner_user_id = ?)
                 OR (c.is_personal = 0)
              )
        ";
    
        $params = [
            $synologyLoginId,
            $synologyLoginId,
            $ownerId,
            $ownerId   // 🔥 이 줄 추가
        ];
    
        if ($from && $to) {
            $sql .= "
                AND (
                    t.due_ymd IS NULL
                    OR (t.due_ymd BETWEEN ? AND ?)
                )
            ";
            $params[] = str_replace('-', '', $from);
            $params[] = str_replace('-', '', $to);
        }
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    public function getEventsByPeriodMapped(
        ?string $from,
        ?string $to,
        string $ownerId,
        ?string $synologyLoginId
    ): array {
    
        $rows = $this->getEventsByPeriod(
            $from,
            $to,
            $ownerId,
            $synologyLoginId
        );
    
        return array_map(
            fn($row) => $this->mapEventRow($row),
            $rows
        );
    }
    

    private function ymdToDateString(?string $v): ?string
    {
        if (!$v) return null;
    
        $digits = preg_replace('/[^0-9]/', '', trim((string)$v));
        if (strlen($digits) < 8) return null;
    
        $ymd = substr($digits, 0, 8); // ✅ 20260227T000000Z 도 20260227로 처리
    
        return substr($ymd, 0, 4) . '-' .
               substr($ymd, 4, 2) . '-' .
               substr($ymd, 6, 2);
    }

/**
 * DB dashboard_calendar_events row
 * → FullCalendar Event Object 변환
 */
private function mapEventRow(array $row): array
{
    $isAllDay = (int)($row['all_day'] ?? 0) === 1;

    if ($isAllDay) {
        $start = $this->ymdToDateString($row['dtstart'] ?? null);
    
        // DB는 보통 dtend를 "포함(inclusive)" 날짜로 들고 있음(예: 25~26이면 26)
        // FullCalendar는 allDay의 end를 "미포함(exclusive)"로 해석하므로 +1day 해서 내려줘야
        // 화면이 Synology처럼 25~26으로 정확히 칠해짐.
        $endInclusive = $this->ymdToDateString($row['dtend'] ?? $row['dtstart'] ?? null);
    
        $end = null;
        if ($endInclusive) {
            $end = Time::parseLocal($endInclusive)
                ->modify('+1 day')
                ->format('Y-m-d');
        } else {
            // end가 없으면 start 기준 1일짜리로 내려줌 (exclusive)
            $end = $start
                ? Time::parseLocal($start)->modify('+1 day')->format('Y-m-d')
                : null;
        }
    
    } else {
        $start = $this->toIso($row['dtstart'] ?? null);
        $end   = $this->toIso($row['dtend'] ?? $row['dtstart'] ?? null);
    }

    $recurrence = $this->safeJson($row['recurrence_json'] ?? null, []);
    $rruleObj   = null;
    $duration   = null;

    if (!empty($recurrence['rrule'])) {

        $rruleString = $recurrence['rrule'];

        preg_match('/FREQ=([^;]+)/', $rruleString, $freqMatch);
        $freq = strtolower($freqMatch[1] ?? '');

        preg_match('/BYMONTHDAY=(\d+)/', $rruleString, $dayMatch);
        $byMonthDay = isset($dayMatch[1]) ? (int)$dayMatch[1] : null;

        preg_match('/INTERVAL=(\d+)/', $rruleString, $intervalMatch);
        $interval = isset($intervalMatch[1]) ? (int)$intervalMatch[1] : 1;

        if ($freq) {

            $rruleObj = [
                'freq'     => $freq,
                'dtstart'  => $start,
                'interval' => $interval
            ];
        
            if ($byMonthDay) {
                $rruleObj['bymonthday'] = $byMonthDay;
            }
        
            // 🔥 duration 계산 (시간 이벤트 포함)
            if (!$isAllDay && $start && $end) {
        
                $startDt = Time::parseLocal($row['dtstart']);
                $endDt   = Time::parseLocal($row['dtend']);
        
                $diff = $endDt->getTimestamp() - $startDt->getTimestamp();
        
                if ($diff > 0) {
                    $duration = [
                        'seconds' => $diff
                    ];
                }
        
            } elseif ($isAllDay && $start && $end) {

                $startDt = Time::parseLocal($start);
                $endDt   = Time::parseLocal($end);
            
                $diffDays = (int)(
                    ($endDt->getTimestamp() - $startDt->getTimestamp()) / 86400
                );
            
                if ($diffDays > 0) {
                    $duration = ['days' => $diffDays];
                } else {
                    $duration = ['days' => 1];
                }
            }
        }
    }

    return [
        'id'     => $row['uid'],
        'title'  => $row['title'] ?? '(제목 없음)',

        'is_active' => (int)($row['is_active'] ?? 1),

        // 🔥 추가
        'synology_exists' => (int)($row['synology_exists'] ?? 1),

        'start'  => $rruleObj ? null : $start,
        'end'    => $rruleObj ? null : $end,
    
        'allDay' => $isAllDay,
    
        'rrule'     => $rruleObj,
        'duration'  => $duration,
    
        // 🔥 루트에도 넣어도 무방
        'href' => $row['href'] ?? null,
    
        'extendedProps' => [
            'type'        => 'VEVENT',
            'calendar_id' => $row['calendar_id'],

            'synology_exists' => (int)($row['synology_exists'] ?? 1),

            // 🔥 이 줄 추가
            'admin_event_color' => $row['admin_event_color'] ?? null,

            // 🔥 핵심
            'href'        => $row['href'] ?? null,
            'etag'        => $row['etag'] ?? null,
    
            'raw' => $this->safeJson($row['raw_json'] ?? null, []),
        ],
    ];
}



    private function toIso(?string $v): ?string
    {
        if ($v === null) return null;
    
        $v = trim((string)$v);
        if ($v === '') return null;
    
        // 🔥 CalendarTime으로 무조건 서울시간 파싱
        $dt = Time::parseLocal($v);
    
        // 🔥 Z 절대 붙이지 말 것
        return $dt->format('Y-m-d\TH:i:s');
    }


    private function safeJson(?string $json, $default)
    {
        if (!$json) return $default;
        $decoded = json_decode($json, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $default;
    }


    public function mapSynologyEventForCache(
        array $event,
        string $calendarId,
        ?string $actor
    ): array {
            // 🔥 Synology raw = VEVENT
            $vevent = is_array($event['raw'] ?? null) ? $event['raw'] : [];

            // RAW 안전 접근기
            $rawValue = static function (array $v, string $key) {
                if (!isset($v[$key])) return null;
                return $v[$key]['value'] ?? null;
            };

            // DTSTART / DTEND (🔥 NULL 방지)
            $dtstart =
                $rawValue($vevent, 'DTSTART')
                ?? $event['dtstart']
                ?? ($rawValue($vevent, 'DTEND') ?? null);

            $dtend =
                $rawValue($vevent, 'DTEND')
                ?? $event['dtend']
                ?? $dtstart; // 🔥 최소 start와 동일하게


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

            return [
                /* ===============================
                * 식별
                * =============================== */
                'uid'         => $event['uid'] ?? null,
                'calendar_id' => $realCalendarId,
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
                'alarms_json' =>
                    !empty($event['alarms'])
                        ? json_encode($event['alarms'], JSON_UNESCAPED_UNICODE)
                        : null,

                'attendees_json' =>
                    isset($vevent['ATTENDEE'])
                        ? json_encode($vevent['ATTENDEE'], JSON_UNESCAPED_UNICODE)
                        : null,

                'recurrence_json' =>
                    ($rrule || !empty($rdate) || !empty($exdate))
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

                // 🔥 Synology 원본 존재 표시
                'synology_exists' => 1,

                'actor' => $actor,
            ];
    }

    public function mapSynologyTaskForCache(
        array $task,
        string $calendarId,
        ?string $actor
    ): array {
    
        $raw = is_array($task['raw'] ?? null) ? $task['raw'] : [];
    
        $rawValue = static function (array $raw, string $key) {
            if (!isset($raw[$key])) return null;
    
            $v = $raw[$key];
    
            if (is_array($v) && array_key_exists('value', $v)) {
                return $v['value'];
            }
    
            return null;
        };
    
        /* ===============================
         * 🔥 실제 calendar_id 보존
         * =============================== */
        $realCalendarId =
            $task['__meta']['calendar_id']
            ?? $task['calendar_id']
            ?? $calendarId;
    
        /* ===============================
         * 🔥 DUE 원본 보존 (핵심)
         * =============================== */
        $dueRaw = $task['due'] ?? $rawValue($raw, 'DUE');
    
        $dueValue = null;
    
        if ($dueRaw) {
    
            $dueRaw = trim((string)$dueRaw);
    
            // 🔥 DATE 타입 (VALUE=DATE)
            if (($raw['DUE']['params']['VALUE'] ?? null) === 'DATE') {
    
                // 20260225 형태로 저장
                if (preg_match('/^\d{8}$/', $dueRaw)) {
                    $dueValue = $dueRaw;
                }
    
            }
            // 🔥 DATETIME 타입
            else {
    
                // 20260225T090000 형태면 그대로 저장
                if (preg_match('/^\d{8}T\d{6}$/', $dueRaw)) {
                    $dueValue = $dueRaw;
                }
                // 혹시 ISO 형식이면 ICS 형식으로 변환
                elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $dueRaw)) {
                    $dt = \DateTime::createFromFormat('Y-m-d\TH:i:s', $dueRaw);
                    if ($dt) {
                        $dueValue = $dt->format('Ymd\THis');
                    }
                }
                else {
                    // fallback 그대로 저장
                    $dueValue = $dueRaw;
                }
            }
        }
    
        return [
            /* ===============================
             * 식별
             * =============================== */
            'uid'         => $task['uid'] ?? null,
            'calendar_id' => $realCalendarId,
            'href'        => $task['_href'] ?? null,
            'etag'        => $task['_etag'] ?? null,
    
            /* ===============================
             * 기본 정보
             * =============================== */
            'title'       => $task['title'] ?? $rawValue($raw, 'SUMMARY'),
            'description' => $task['description'] ?? $rawValue($raw, 'DESCRIPTION'),
    
            /* ===============================
             * 🔥 DUE (원본 유지)
             * =============================== */
            'due'         => $dueValue,
    
            /* ===============================
             * 상태
             * =============================== */
            'status' => $rawValue($raw, 'STATUS'),
    
            'percent_complete' =>
                isset($raw['PERCENT-COMPLETE']['value'])
                    ? (int)$raw['PERCENT-COMPLETE']['value']
                    : null,
    
            /* ===============================
             * RAW 보존
             * =============================== */
            'raw_json' => json_encode($task, JSON_UNESCAPED_UNICODE),
    
            'actor' => $actor,
        ];
    }

    // 🔓 UI 즉시 반환용 공개 래퍼
    public function mapEventRowPublic(array $row): array
    {
        return $this->mapEventRow($row);
    }


    private function mapTaskRow(array $row): array
    {
        $due = $row['due'] ?? null;

        $start  = null;
        $allDay = false;
        $dueIso = null;

        $raw = $this->safeJson($row['raw_json'] ?? null, []);

        $isDateOnly = false;

        /**
         * 1️⃣ VALUE=DATE 정확 판단 (raw.raw.DUE)
         */
        if (
            isset($raw['raw']['DUE']['params']['VALUE'])
            && $raw['raw']['DUE']['params']['VALUE'] === 'DATE'
        ) {
            $isDateOnly = true;
        }
        
        /**
         * 2️⃣ fallback
         */
        elseif ($due && (
            preg_match('/^\d{8}$/', $due) ||
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)
        )) {
            $isDateOnly = true;
        }

        if ($due) {

            if ($isDateOnly) {
        
                // 🔥 20260305 형식
                if (preg_match('/^\d{8}$/', $due)) {
                    $start =
                        substr($due, 0, 4) . '-' .
                        substr($due, 4, 2) . '-' .
                        substr($due, 6, 2);
                }
        
                // 🔥 2026-03-05 형식
                elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
                    $start = $due;
                }
        
                $dueIso = $start;
                $allDay = true;
        
            } else {
        
                $dt = Time::parseLocal($due);
        
                $start  = $dt->format('Y-m-d\TH:i:s');
                $dueIso = $dt->format('Y-m-d\TH:i:s');
                $allDay = false;
            }
        }

        return [
            'id'    => 'task_' . $row['uid'],
            'uid'   => $row['uid'],                     // 🔥 중요
            'title' => $row['title'] ?? '(Task)',

            // 🔥 추가
            'is_active' => (int)($row['is_active'] ?? 1),
            'synology_exists' => (int)($row['synology_exists'] ?? 1),

            'start'   => $start,
            'due_iso' => $dueIso,                       // 🔥 프론트 필수
            'allDay'  => $allDay,

            // 🔥 루트에 직접 넣어줌 (프론트 안정화)
            'status'           => $row['status'] ?? null,
            'percent_complete' => $row['percent_complete'] ?? null,
            'completed_at'     => $row['completed_at'] ?? null,

            'extendedProps' => [
                'type'        => 'VTODO',
                'calendar_id' => $row['calendar_id'],
                'synology_exists' => (int)($row['synology_exists'] ?? 1),
                'status'      => $row['status'] ?? null,
                'raw'         => $raw
            ]
        ];
    }



    public function getTasksByPeriodMapped(
        ?string $from,
        ?string $to,
        string $ownerId,
        ?string $synologyLoginId
    ): array {
    
        $rows = $this->getTasksByPeriod(
            $from,
            $to,
            $ownerId,
            $synologyLoginId
        );
    
        return array_map(
            fn($row) => $this->mapTaskRow($row),
            $rows
        );
    }

    // 🔓 UI 즉시 반환용 공개 래퍼
    public function mapTaskRowPublic(array $row): array
    {
        return $this->mapTaskRow($row);
    }


    public function getAllTasksForPanel(
        string $ownerId,
        ?string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT t.*
            FROM dashboard_calendar_tasks t
            JOIN dashboard_calendar_list c
                ON c.id = t.calendar_id
            JOIN dashboard_calendar_visibility v
                ON v.calendar_id = t.calendar_id
               AND v.synology_login_id = ?
               AND v.is_visible = 1
            WHERE t.is_active = 1
              AND t.synology_login_id = ?
              AND (
                    (c.is_personal = 1 AND c.owner_user_id = ?)
                 OR (c.is_personal = 0)
              )
            ORDER BY
                CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END,
                t.due ASC
        ";
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $synologyLoginId,
            $synologyLoginId,
            $ownerId
        ]);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * 우측 패널 전용 전체 태스크 조회
     */
    public function getAllTasksMapped(
        string $ownerId,
        ?string $synologyLoginId
    ): array {
    
        $sql = "
            SELECT t.*
            FROM dashboard_calendar_tasks t
            JOIN dashboard_calendar_list c
                ON c.id = t.calendar_id
            JOIN dashboard_calendar_visibility v
                ON v.calendar_id = t.calendar_id
               AND v.synology_login_id = ?
               AND v.is_visible = 1
            WHERE t.is_active = 1
              AND t.synology_login_id = ?
              AND (
                    (c.is_personal = 1 AND c.owner_user_id = ?)
                 OR (c.is_personal = 0)
              )
            ORDER BY
                CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END,
                t.due ASC,
                t.created_at DESC
        ";
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $synologyLoginId,
            $synologyLoginId,
            $ownerId
        ]);
    
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        return array_map(
            fn($row) => $this->mapTaskRow($row),
            $rows
        );
    }
    public function getCalendarPermission(string $calendarId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT is_personal, owner_user_id
            FROM dashboard_calendar_list
            WHERE id = :id
            LIMIT 1
        ");
    
        $stmt->execute([
            ':id' => $calendarId
        ]);
    
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    
        return $row ?: null;
    }
    public function getEventCalendarId(string $uid): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT calendar_id
            FROM dashboard_calendar_events
            WHERE uid = :uid
            LIMIT 1
        ");
    
        $stmt->execute([':uid' => $uid]);
    
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    
        return $row['calendar_id'] ?? null;
    }

    public function getTaskCalendarId(string $uid): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT calendar_id
            FROM dashboard_calendar_tasks
            WHERE uid = :uid
            LIMIT 1
        ");
    
        $stmt->execute([':uid' => $uid]);
    
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    
        return $row['calendar_id'] ?? null;
    }

    public function getTaskCollectionHref(string $uid): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT collection_href
            FROM dashboard_calendar_tasks
            WHERE uid = :uid
            LIMIT 1
        ");
    
        $stmt->execute([':uid' => $uid]);
    
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    
        return $row['collection_href'] ?? null;
    }

    public function getCalendarIdByHref(string $href): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM dashboard_calendar_list
            WHERE href = :href
            LIMIT 1
        ");
    
        $stmt->execute([':href' => $href]);
    
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    
        return $row['id'] ?? null;
    }

}
