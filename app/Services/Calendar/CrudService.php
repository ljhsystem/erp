<?php
// 경로: PROJECT_ROOT . '/app/services/calendar/CrudService.php'
declare(strict_types=1);

namespace App\Services\Calendar;

use PDO;
use App\Services\Calendar\CalDavClient;
// use App\Services\Calendar\Caldav\HttpClient;
// use App\Services\Calendar\Caldav\CollectionClient;
// use App\Services\Calendar\Caldav\ObjectClient;
// use App\Services\Calendar\Caldav\Parser;
// use App\Services\Calendar\Caldav\Ics;
use App\Models\System\SettingConfigModel;
use App\Models\User\ExternalAccountModel;
use App\Services\User\ExternalAccountService;
use App\Services\Calendar\IcsService;
use App\Services\Calendar\SyncService;
use App\Services\Calendar\Time;
use Core\LoggerFactory;


/**
 * =========================================================
 * CalendarCrudService
 * - is_connected 값은 진입조건 ❌
 * - 실제 사용 결과로 상태 갱신 ⭕
 * =========================================================
 */
class CrudService
{
    private readonly PDO $pdo;

    private SettingConfigModel $systemConfig;
    private ExternalAccountModel $externalAccount;
    private ExternalAccountService $accountService;
    
    private IcsService $ics;
    private ?SyncService $sync = null;
    private ?CalDavClient $caldavClient = null;
    private $logger;

    private function sync(): SyncService
    {
        if ($this->sync === null) {
            $this->sync = new SyncService($this->pdo);
        }
        return $this->sync;
    }

    public function __construct(PDO $pdo)
    {
        $this->pdo             = $pdo;
        $this->systemConfig    = new SettingConfigModel($pdo);
        $this->externalAccount = new ExternalAccountModel($pdo);
        $this->accountService  = new ExternalAccountService($pdo);
        $this->ics             = new IcsService();    
        $this->logger = LoggerFactory::getLogger(
            'service-calendar.CalendarCrudService'
        );
    }

    private function caldav(): CalDavClient
    {
        if ($this->caldavClient === null) {
            $this->caldavClient = $this->createCalDavClient();
        }
    
        return $this->caldavClient;
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    /* =========================================================
     * 🔌 CalDAV Client 생성
     * ========================================================= */
    private function createCalDavClient(): CalDavClient
    {
        [$userId, $synologyLoginId] = $this->resolveSyncIdentity();
        if (!$userId) {
            throw new \RuntimeException('Invalid user session');
        }
    
        // 시스템 설정
        $host = rtrim((string)$this->systemConfig->get('synology_host'), '/');
        $path = trim((string)$this->systemConfig->get('synology_caldav_path'), '/');
    
        if ($host === '' || $path === '') {
            throw new \RuntimeException('Synology CalDAV not configured');
        }
    
        $baseUrl = $host . '/' . $path;
    
        // 사용자 계정
        $account = $this->externalAccount->getByUserAndService($userId, 'synology');
    
        if (
            !$account ||
            empty($account['external_login_id']) ||
            empty($account['external_password'])
        ) {
            throw new \RuntimeException('Synology account not registered');
        }
    
        $p = parse_url($baseUrl);
        $origin = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':' . $p['port'] : '');
    
        return new CalDavClient([
            'base_url' => rtrim($baseUrl, '/'),
            'origin'   => $origin,
            'username' => $account['external_login_id'],
            'password' => $account['external_password'],
        ]);
    }
    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /* =========================================================
     * 📅 Calendar List
     * ========================================================= */

     /**
     * ⚠️ INTERNAL USE ONLY
     * Synology Calendar List Fetch
     * - Sync 전용
     * - UI/API 호출 금지
     */
    public function fetchRemoteCalendars(): array
    {
        try {
            $caldav = $this->caldav();
            $home   = $caldav->getCalendarHomeSetFromRoot();
    
            if (!$home) {
                throw new \RuntimeException('calendar-home-set not found');
            }
    
            // 🔥 home 포함 전체 수집
            $data = $caldav->listCalendarsFromHome($home);
            $data = is_array($data) ? $data : [];
    
            foreach ($data as &$c) {
                $href = $this->normalizeCollectionHref((string)($c['href'] ?? ''));
                $c['href'] = $href;
    
                $id = $this->hrefToId($href);
                $c['id'] = $id;
                $c['calendar_id'] = $id;
    
                // 🔥 type 판별은 여기서 하지 말고 SyncService에서
                // (calendar / task)
    
                // 색상은 그대로
                $color = $c['calendar_color'] ?? null;
                if (is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    $c['calendar_color'] = strtoupper($color);
                } else {
                    $c['calendar_color'] = null;
                }
            }
            unset($c);
    
            $this->accountService->markSuccess('synology');
    
            return ['success' => true, 'data' => $data];
    
        } catch (\Throwable $e) {
            $this->accountService->markFailure('synology', $e->getMessage());
            $this->logger->error('[listCalendars]', ['error' => $e->getMessage()]);
    
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /* =========================================================
     * 📌 Events
     * ========================================================= */
    /**
     * ⚠️ INTERNAL USE ONLY
     * Synology VEVENT Fetch
     * - SyncService / Write-back 전용
     * - ❌ Controller / API / UI 호출 금지
     */
    public function getEvents(string $collectionHref, ?string $from, ?string $to): array
    {
        try {
            $hrefNorm = $this->normalizeCollectionHref($collectionHref);
            $data = $this->caldav()->getEvents($hrefNorm, $from, $to);
            $data = is_array($data) ? $data : [];
            
            $calId = $this->hrefToId($hrefNorm);
            
            foreach ($data as &$ev) {
                $ev['calendar_id'] = $calId;
            
                // ✅ 가공 중 유실 대비: meta에도 넣어둠
                if (!isset($ev['__meta']) || !is_array($ev['__meta'])) $ev['__meta'] = [];
                $ev['__meta']['calendar_id'] = $calId;
                $ev['__meta']['collection_href'] = $hrefNorm;
            
                if (!isset($ev['extendedProps']) || !is_array($ev['extendedProps'])) $ev['extendedProps'] = [];
                $ev['extendedProps']['calendar_id'] = $calId;
                $ev['extendedProps']['collection_href'] = $hrefNorm;
            }
            unset($ev);
            
            $this->accountService->markSuccess('synology');

            return ['success' => true, 'data' => $data];

        } catch (\Throwable $e) {
            $this->accountService->markFailure('synology', $e->getMessage());
            $this->logger->error('[getEvents]', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getAllTasks(?string $from, ?string $to): array
    {
        $caldav = $this->caldav();
    
        $home = $caldav->getCalendarHomeSetFromRoot();
        if (!$home) {
            throw new \RuntimeException('calendar-home-set not found');
        }
    
        $calendars = $caldav->listCalendarsFromHome($home);
    
        $tasks = [];
    
        foreach ($calendars as $cal) {
            if (($cal['type'] ?? '') !== 'task') continue;
        
            $href = $cal['href'] ?? '';
            if (!$href) continue;
        
            $hrefNorm = $this->normalizeCollectionHref((string)$href);
            $calId    = $this->hrefToId($hrefNorm);
        
            $rows = $caldav->getTodos($hrefNorm, $from, $to);
            if (is_array($rows)) {
                foreach ($rows as &$t) {
                    $t['calendar_id'] = $calId;
                    $t['__meta'] = ($t['__meta'] ?? []);
                    $t['__meta']['calendar_id'] = $calId;
                    $t['__meta']['collection_href'] = $hrefNorm;
                    if (!isset($t['extendedProps']) || !is_array($t['extendedProps'])) {
                        $t['extendedProps'] = [];
                    }
                    $t['extendedProps']['calendar_id'] = $calId;
                }
                unset($t);
        
                $tasks = array_merge($tasks, $rows);
            }
        }
        
    
        return $tasks;
    }


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /* =========================================================
     * ☑️ Tasks
     * ========================================================= */
    /**
     * ⚠️ INTERNAL USE ONLY
     * Synology VTODO Fetch
     * - SyncService / Write-back 전용
     * - ❌ Controller / API / UI 호출 금지
     */
    public function getTasks(string $collectionHref, ?string $from, ?string $to): array
    {
        try {
            $hrefNorm = $this->normalizeCollectionHref($collectionHref);
            $data = $this->caldav()->getTodos($hrefNorm, $from, $to);
            $data = is_array($data) ? $data : [];
            
            $calId = $this->hrefToId($hrefNorm);
            
            foreach ($data as &$t) {
                $t['calendar_id'] = $calId;
            
                if (!isset($t['__meta']) || !is_array($t['__meta'])) $t['__meta'] = [];
                $t['__meta']['calendar_id'] = $calId;
                $t['__meta']['collection_href'] = $hrefNorm;
            
                if (!isset($t['extendedProps']) || !is_array($t['extendedProps'])) $t['extendedProps'] = [];
                $t['extendedProps']['calendar_id'] = $calId;
                $t['extendedProps']['collection_href'] = $hrefNorm;
            }
            unset($t);            

            $this->accountService->markSuccess('synology');

            $this->logger->info('[TASK RAW RESPONSE]', [
                'collection' => $hrefNorm,
                'count'      => count($data)
            ]);            

            return ['success' => true, 'data' => $data];

        } catch (\Throwable $e) {
            $this->accountService->markFailure('synology', $e->getMessage());
            $this->logger->error('[getTasks]', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /* =========================================================
     * ✍ Create / Update / Delete
     * ========================================================= */
    private function runAndTrack(callable $fn, string $logTag): array
    {
        try {
            $result = $fn();
            $this->accountService->markSuccess('synology');
            return ['success' => true] + $result;
        } catch (\Throwable $e) {
            $this->accountService->markFailure('synology', $e->getMessage());
            $this->logger->error($logTag, ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

  


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /* =========================================================
     * ✍ ATTENDEE 라인 만들기 유틸
     * ========================================================= */
    private function buildAttendeeLines(array $guests): array
    {
        $lines = [];
        foreach ($guests as $g) {
            $email = trim((string)$g);
            if ($email === '') continue;
    
            // mailto: 강제
            if (!str_starts_with(strtolower($email), 'mailto:')) {
                $email = 'mailto:' . $email;
            }
    
            // Synology에서 잘 먹는 기본 파라미터 셋
            $lines[] =
                'ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:' .
                $email;
        }
        return $lines;
    }
    

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////





    /* =========================================================
    * 🗑️ Calendar / Task Collection Delete
    * ========================================================= */
    public function deleteCollection(string $collectionHref): array
    {
        return $this->runAndTrack(function () use ($collectionHref) {
            $caldav = $this->caldav();
            $caldav->deleteCollection($collectionHref);

            return ['message' => 'collection deleted'];
        }, '[deleteCollection]');
    }


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////




    /* =========================================================
    * ✍ Event CRUD (stub)
    * ========================================================= */
    public function createEvent(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {

            $caldav = $this->caldav();

            /* -------------------------------------------------
            * 1️⃣ Calendar 정보 (DB 단일 진실)
            * ------------------------------------------------- */
            
                $collectionHref = $payload['collection_href']
                    ?? throw new \RuntimeException('collection_href required');
                
                $collectionHref = $this->normalizeCollectionHref($collectionHref);
                
                // 🔥 DB에서 존재 여부만 체크 (개인/공유 구분 없이)
                $calendarId = $this->hrefToId($collectionHref);
                
                $this->assertCalendarWritePermission($calendarId);
                
                $stmt = $this->pdo->prepare("
                    SELECT id
                    FROM dashboard_calendar_list
                    WHERE id = :id
                    AND type = 'calendar'
                    AND is_active = 1
                    LIMIT 1
                ");
                
                $stmt->execute([
                    ':id' => $calendarId
                ]);
                
                if (!$stmt->fetch()) {
                    throw new \RuntimeException('calendar not registered or inactive');
                }
                
                // 🔥 이제 여기서 calendar_id 계산
                $calendarId = $this->hrefToId($collectionHref);

                $this->assertCalendarWritePermission($calendarId);
                
                $stmt = $this->pdo->prepare("
                    SELECT id
                    FROM dashboard_calendar_list
                    WHERE id = :id
                    AND type = 'calendar'
                    AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([
                    ':id' => $calendarId
                ]);
                
                if (!$stmt->fetch()) {
                    throw new \RuntimeException('calendar not registered or inactive');
                }
                       


                // createEvent() 초반
                if (!empty($payload['uid'])) {
                    throw new \RuntimeException('createEvent called with uid');
                }



            /* -------------------------------------------------
            * 2️⃣ UID / href 생성
            * ------------------------------------------------- */
            $uid = gmdate('Ymd\THis')
            . '-' . bin2hex(random_bytes(6))
            . '@cal.synology.com';
       
            $href = $collectionHref . rawurlencode($uid) . '.ics';
            

            /* -------------------------------------------------
            * 3️⃣ ICS 생성 (FIXED)
            * ------------------------------------------------- */

            $startRaw = (string)($payload['start'] ?? '');
            $endRaw   = (string)($payload['end']   ?? '');

            $isAllDay =
            !empty($payload['allDay']) ||
            (
                $startRaw !== '' &&
                $endRaw   !== '' &&
                strlen($startRaw) === 10 &&   // YYYY-MM-DD
                strlen($endRaw)   === 10
            );
        

            $guests = is_array($payload['guests'] ?? null) ? $payload['guests'] : [];
            $attendeeLines = $this->buildAttendeeLines($guests);

            $rawLines = [];

            // LOCATION
            if (!empty($payload['location'])) {
                $rawLines[] = 'LOCATION:' . $this->ics->escape($payload['location']);
            }

            // DESCRIPTION
            if (array_key_exists('description', $payload)) {
                $rawLines[] = 'DESCRIPTION:' . $this->ics->escape($payload['description']);
            }

            // ATTENDEE
            $rawLines = array_merge($rawLines, $attendeeLines);
            $tzid = (string)($this->systemConfig->get('timezone') ?: 'Asia/Seoul');  

            // STATUS
            if (!empty($payload['status'])) {
                $rawLines[] = 'STATUS:' . strtoupper($payload['status']);
            }

            // PRIORITY
            if (!empty($payload['priority'])) {
                $rawLines[] = 'PRIORITY:' . (int)$payload['priority'];
            }

            // TRANSP
            $transp = strtoupper($payload['transp'] ?? 'OPAQUE');
            $rawLines[] = 'TRANSP:' . $transp;

            // AllDAY
            if ($isAllDay) {

                if (empty($payload['start'])) {
                    throw new \RuntimeException('start required');
                }

                $baseStart = substr((string)$payload['start'], 0, 10);
                $baseEnd   = !empty($payload['end'])
                    ? substr((string)$payload['end'], 0, 10)
                    : $baseStart;

                // DTSTART
                $dtstartYmd = str_replace('-', '', $baseStart);
                $rawLines[] = 'DTSTART;VALUE=DATE:' . $dtstartYmd;

                // 🔥 RRULE 여부와 상관없이 항상 DTEND 생성
                $dtendYmd = Time::parseLocal($baseEnd)
                    ->modify('+1 day')   // exclusive
                    ->format('Ymd');

                $rawLines[] = 'DTEND;VALUE=DATE:' . $dtendYmd;

            } else {

                if ($startRaw === '') {
                    throw new \RuntimeException('start required');
                }

                // 🔥 start/end 반드시 서울시간 기준 정규화
                $startLocal = Time::parseLocal($startRaw);

                $endLocal = $endRaw
                    ? Time::parseLocal($endRaw)
                    : (clone $startLocal)->modify('+1 hour');

                $rawLines[] = 'DTSTART;TZID=' . $tzid . ':' .
                    $startLocal->format('Ymd\THis');

                $rawLines[] = 'DTEND;TZID=' . $tzid . ':' .
                    $endLocal->format('Ymd\THis');
            }

            // RRULE (공통)
            if (!empty($payload['rrule'])) {

                $rr = preg_replace('/^RRULE:/', '', (string)$payload['rrule']);

                if (str_contains($rr, 'FREQ=MONTHLY')) {

                    if (empty($payload['start'])) {
                        throw new \RuntimeException('MONTHLY requires start date');
                    }

                    $day = (int)substr($payload['start'], 8, 2);

                    // 기존 BYMONTHDAY 제거
                    $rr = preg_replace('/;?BYMONTHDAY=\d+/', '', $rr);

                    $rr .= ';BYMONTHDAY=' . $day;
                }

                $rawLines[] = 'RRULE:' . $rr;   // 🔥 반드시 rawLines에 추가
            }

            // 🔔 VALARM (CREATE 시 필수)
            if (!empty($payload['alarms']) && is_array($payload['alarms'])) {
                foreach ($payload['alarms'] as $a) {

                    // 🔥 알람 값 정규화 (array → string)
                    if (is_array($a)) {
                        // value / trigger / minutes 등 대응
                        $a =
                            $a['value']
                            ?? $a['trigger']
                            ?? (
                                isset($a['minutes'])
                                    ? '-' . (int)$a['minutes'] . 'M'
                                    : null
                            );
                    }

                    if (!$a) continue;

                    $rawLines[] = 'BEGIN:VALARM';
                    $rawLines[] = 'ACTION:DISPLAY';
                    $rawLines[] = 'DESCRIPTION:Reminder';
                    $rawLines[] = 'TRIGGER:' . $this->ics->normalizeAlarmTrigger((string)$a);
                    $rawLines[] = 'END:VALARM';
                }
            }



            // ✅ buildIcs는 여기서 딱 1번
            $ics = $this->ics->buildIcs('VEVENT', [
                'uid'       => $uid,
                'title'     => $payload['title'] ?? '',
                'raw_lines' => $rawLines,
            ]);



            /* -------------------------------------------------
            * 4️⃣ Synology CalDAV PUT (ETag는 선택)
            * ------------------------------------------------- */
            $caldav->createObject($href, $ics);

            /**
             * 🔥 Synology CalDAV 특성
             * - PUT 성공 = 생성 성공
             * - ETag는 즉시 안 내려올 수 있음 (정상)
             */
            $etag = null;

            // 있으면 가져온다 (옵션)
            $get = $caldav->request('GET', $href);
            $originIcs =
            is_array($get) && array_key_exists('body', $get)
                ? $get['body']
                : null;
        
            
            $headers = $get['headers'] ?? [];

            $etag = null;
            foreach (['ETag','etag'] as $k) {
                if (!empty($headers[$k][0])) {
                    $etag = trim($headers[$k][0], '"');
                    break;
                }
            }            

            
            if (!$originIcs) {
                throw new \RuntimeException('ICS not returned after create');
            }

            if ($isAllDay) {
                $baseStart = substr($payload['start'], 0, 10);
                $baseEnd   = $payload['end']
                    ? substr($payload['end'], 0, 10)
                    : $baseStart;
            
                // 🔥 payload end를 그대로 믿지 말고
                // DTSTART 기준으로 DTEND를 계산
                $dtstart = str_replace('-', '', $baseStart);
                $dtend = Time::parseLocal($baseEnd)
                        ->modify('+1 day')
                        ->format('Ymd');
            } else {
                // ✅ DB에는 local time 그대로 저장 (Asia/Seoul 기준)
                $dtstart = $payload['start'];
                $dtend   = $payload['end'] ?? $payload['start'];
            }
            
            if (!isset($calendarId) || $calendarId === '') {
                throw new \RuntimeException('resolved calendar_id missing');
            }
            $this->logger->debug('[CREATE EVENT BIND]', [
                'calendar_id' => $calendarId,
                'dtstart' => $dtstart,
                'dtend' => $dtend,
            ]);
            
            
            
        
            $rawForStore = $payload;
            unset($rawForStore['collection_href']); // ❌ 제거
            $rawForStore['collection_href'] = $collectionHref; // ✅ 실제 collection
                

       
        // 🔥 Sync 호출
        [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

        if (!$userId) {
            throw new \RuntimeException('Invalid session');
        }
                
        $syncResult = $this->sync()->syncOneEventByUid(
            $uid,
            $synologyLoginId,
            $userId,
            [
                'calendar_id'       => $calendarId,
                'admin_event_color' => $payload['admin_event_color'] ?? null
            ]
        );
            
            $eventRow = $syncResult['event'] ?? null;
            
            $etagForReturn =
                (is_array($eventRow) ? ($eventRow['etag'] ?? null) : null)
                ?? (is_array($eventRow) ? ($eventRow['extendedProps']['etag'] ?? null) : null)
                ?? (is_array($eventRow) ? ($eventRow['extendedProps']['raw']['_etag'] ?? null) : null)
                ?? ($etag ?? null);
            
            if (is_string($etagForReturn)) {
                $etagForReturn = trim($etagForReturn);
                $etagForReturn = trim($etagForReturn, '"');
                $etagForReturn = trim($etagForReturn, '"');
            }
            
            return [
                'success' => true,
                'data' => [
                    'uid'  => $uid,
                    'etag' => $etagForReturn
                ],
                'event' => $eventRow
            ];
                       
        }, '[createEvent]');
    }








    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function rebuildEvent(array $row, array $payload): array
    {
        $caldav = $this->caldav();

        $uid  = $row['uid'];
        $href = $row['href'];
        $etag = $row['etag'];

        if (!$href) {
            throw new \RuntimeException('rebuildEvent: href missing');
        }

        /* -------------------------------------------------
        * 1️⃣ 기존 값 보강
        * ------------------------------------------------- */
        $title       = $payload['title']       ?? $row['title'];
        $description = $payload['description'] ?? $row['description'];
        $location    = $payload['location']    ?? $row['location'];
        $rrule       = $payload['rrule']       ?? null;

        /* -------------------------------------------------
        * 2️⃣ 날짜 계산
        * ------------------------------------------------- */
        $isAllDay =
            array_key_exists('allDay', $payload)
                ? (bool)$payload['allDay']
                : ((int)$row['all_day'] === 1);

        $rawLines = [];

        if ($location !== null) {
            $rawLines[] = 'LOCATION:' . $this->ics->escape($location);
        }

        if ($description !== null) {
            $rawLines[] = 'DESCRIPTION:' . $this->ics->escape($description);
        }

        if ($isAllDay) {

            $startRaw = $payload['start'] ?? $row['dtstart'];
            $endRaw   = $payload['end']   ?? $row['dtend'];
        
            $startDate = substr($startRaw, 0, 10);
            $endDate   = substr($endRaw,   0, 10);
        
            $dtstartYmd = str_replace('-', '', $startDate);
        
            $dtendYmd = Time::parseLocal($endDate)
                ->modify('+1 day')
                ->format('Ymd');
        
            $rawLines[] = 'DTSTART;VALUE=DATE:' . $dtstartYmd;
            $rawLines[] = 'DTEND;VALUE=DATE:'   . $dtendYmd;
        
        } else {
        
            $tzid = (string)($this->systemConfig->get('timezone') ?: Time::TZID);
        
            $startRaw = $payload['start'] ?? $row['dtstart'];
            $endRaw   = $payload['end']   ?? $row['dtend'];
        
            if (!$startRaw || !$endRaw) {
                throw new \RuntimeException('DTSTART/DTEND missing');
            }
        
            // 🔥 반드시 서울시간으로 정규화
            $startLocal = Time::parseLocal($startRaw);
            $endLocal   = Time::parseLocal($endRaw);
        
            $rawLines[] = 'DTSTART;TZID=' . $tzid . ':' .
                $startLocal->format('Ymd\THis');
        
            $rawLines[] = 'DTEND;TZID=' . $tzid . ':' .
                $endLocal->format('Ymd\THis');
        }

        if (!empty($rrule)) {
            if (!str_starts_with($rrule, 'RRULE:')) {
                $rrule = 'RRULE:' . $rrule;
            }
            $rawLines[] = $rrule;
        }

        /* -------------------------------------------------
        * 3️⃣ ICS 생성 (🔥 UID 유지)
        * ------------------------------------------------- */
        $ics = $this->ics->buildIcs('VEVENT', [
            'uid'       => $uid,      // 🔥 기존 UID 유지
            'title'     => $title,
            'raw_lines' => $rawLines,
        ]);

        /* -------------------------------------------------
        * 4️⃣ DELETE ❌ → PUT overwrite ⭕
        * ------------------------------------------------- */
        $caldav->updateObject($href, $ics, $etag);

        /* -------------------------------------------------
        * 5️⃣ Sync
        * ------------------------------------------------- */
        [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

        if (!$userId) {
            throw new \RuntimeException('Invalid session');
        }
                
        // 🔥 Sync 호출 (정식 4인자)
        $syncResult = $this->sync()->syncOneEventByUid(
            $uid,
            $synologyLoginId,   // 2️⃣ synology_login_id
            $userId,            // 3️⃣ actor (ERP user id)
            [
                'calendar_id'       => $row['calendar_id'],
                'admin_event_color' => $payload['admin_event_color'] ?? null
            ]
        );
        
        $eventRow = $syncResult['event'] ?? null;
        
        // ✅ etag 다중 경로 추출
        $etagForReturn =
            (is_array($eventRow) ? ($eventRow['etag'] ?? null) : null)
            ?? (is_array($eventRow) ? ($eventRow['extendedProps']['etag'] ?? null) : null)
            ?? (is_array($eventRow) ? ($eventRow['extendedProps']['raw']['_etag'] ?? null) : null)
            ?? ($syncResult['etag'] ?? null) // 혹시 syncResult 최상단에 있을 수도
            ?? ($etag ?? null);
        
        if (is_string($etagForReturn)) {
            $etagForReturn = trim($etagForReturn);
            $etagForReturn = trim($etagForReturn, '"');
            $etagForReturn = trim($etagForReturn, '"');
        }
        
        return [
            'success' => true,
            'data' => [
                'uid'  => $uid,
                'etag' => $etagForReturn
            ],
            'event' => $eventRow
        ];
    }


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function updateEvent(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {

            // 🔥 payload 중첩 방어 (프론트 실수 대비)
            if (isset($payload['uid']) && is_array($payload['uid']) && isset($payload['uid']['uid'])) {
                $payload = $payload['uid'];
            }

            $this->logger->debug('[UPDATE PAYLOAD]', $payload);

            $scope = $payload['scope'] ?? 'all';
            $recurrenceId = $payload['recurrence_id'] ?? null;

            /* -------------------------------------------------
            * 1️⃣ 필수값
            * ------------------------------------------------- */
            // 1️⃣ ERP 사용자 ID
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();
            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }

            // 3️⃣ UID 확보 (🔥 이게 빠져있었다)
            $uid = $payload['uid'] ?? null;
            if (!$uid) {
                throw new \RuntimeException('uid required');
            }
            /* -------------------------------------------------
            * 2️⃣ DB에서 기존 이벤트 조회
            * ------------------------------------------------- */
          // 🔥 1. uid OR href 기준으로 먼저 찾는다
          $stmt = $this->pdo->prepare("
                SELECT *
                FROM dashboard_calendar_events
                WHERE uid = :uid
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([
                ':uid' => $uid
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                throw new \RuntimeException('event not found');
            }
            // 🔐 Synology 로그인 일치 여부 확인
            if ($row['synology_login_id'] !== $synologyLoginId) {
                throw new \RuntimeException('Synology account mismatch');
            }
            $this->assertCalendarWritePermission($row['calendar_id']);
            


            /* =========================================================
            * 🔁 반복 이벤트 "단건 수정" 처리 (EXDATE + RECURRENCE-ID)
            * ========================================================= */
            $scope = $payload['scope'] ?? null;                 // 'single' | 'all'
            $recurrenceId = $payload['recurrence_id'] ?? null;  // 'YYYYMMDD' 기대

            if ($scope === 'single') {
                if (!$recurrenceId) {
                    throw new \RuntimeException('recurrence_id required for scope=single');
                }

                // ✅ 1) 원본 ICS GET
                $caldav = $this->caldav();
                $res = $caldav->request('GET', $row['href']);
                $originIcs = is_array($res) && array_key_exists('body', $res) ? $res['body'] : null;
                if (!$originIcs) {
                    throw new \RuntimeException('ICS not found on CalDAV');
                }

                // ✅ 2) 원본에 EXDATE 추가 (해당 인스턴스 제외)
                // - 종일: EXDATE;VALUE=DATE:YYYYMMDD
                // - 시간형: EXDATE;TZID=...:YYYYMMDDTHHMMSS (지금은 종일 위주로 시작)
                $exdateLine = 'EXDATE;VALUE=DATE:' . preg_replace('/[^0-9]/', '', $recurrenceId);
                if (strpos($originIcs, 'EXDATE') === false) {
                    // RRULE 다음에 끼워넣기 (없으면 DTSTART 다음)
                    if (preg_match('/\r\nRRULE:.*\r\n/', $originIcs)) {
                        $originIcs = preg_replace('/(\r\nRRULE:.*\r\n)/', "$1{$exdateLine}\r\n", $originIcs, 1);
                    } else {
                        $originIcs = preg_replace('/(\r\nDTSTART[^\r\n]*\r\n)/', "$1{$exdateLine}\r\n", $originIcs, 1);
                    }
                } else {
                    // 기존 EXDATE 라인이 있으면 뒤에 추가(간단 구현)
                    $originIcs = preg_replace('/(\r\nEXDATE[^\r\n]*:\s*)([0-9,]+)/', '$1$2,' . preg_replace('/[^0-9]/', '', $recurrenceId), $originIcs, 1);
                }

                // ✅ 3) override 인스턴스 VEVENT 생성 (RECURRENCE-ID 포함)
                // - 같은 UID 사용
                // - DTSTART/DTEND는 payload 기준으로 생성
                $uid = $row['uid'];

                // SEQUENCE 증가
                $seq = (int)($this->ics->extractSequence($originIcs) ?? 0) + 1;

                // DTSTART/DTEND 라인 생성 (종일 기준)
                $isAllDay = !empty($payload['allDay']) || ((int)$row['all_day'] === 1);
                if (!$isAllDay) {
                    throw new \RuntimeException('scope=single currently supports all-day first (extend later)');
                }

                $baseStart = substr((string)($payload['start'] ?? ''), 0, 10);
                $baseEnd   = substr((string)($payload['end']   ?? ''), 0, 10);
                if ($baseStart === '') {
                    throw new \RuntimeException('start required for scope=single');
                }
                if ($baseEnd === '') $baseEnd = $baseStart;

                $dtstartYmd = str_replace('-', '', $baseStart);
                $dtendYmd = Time::parseLocal($baseEnd)
                        ->modify('+1 day')
                        ->format('Ymd');

                // override VEVENT만 뽑기 위해 buildIcs로 만들고 VEVENT 블록만 추출
                $overrideIcs = $this->ics->buildIcs('VEVENT', [
                    'uid'   => $uid,
                    'title' => $payload['title'] ?? ($row['title'] ?? ''),
                    'raw_lines' => [
                        'SEQUENCE:' . $seq,
                        'RECURRENCE-ID;VALUE=DATE:' . preg_replace('/[^0-9]/', '', $recurrenceId),
                        'DTSTART;VALUE=DATE:' . $dtstartYmd,
                        'DTEND;VALUE=DATE:' . $dtendYmd,
                        'DESCRIPTION:' . $this->ics->escape((string)($payload['description'] ?? ($row['description'] ?? ''))),
                        'LOCATION:' . $this->ics->escape((string)($payload['location'] ?? ($row['location'] ?? ''))),
                    ],
                ]);

                if (!preg_match('/BEGIN:VEVENT\r\n[\s\S]*?END:VEVENT\r\n/', $overrideIcs, $m)) {
                    throw new \RuntimeException('failed to build override VEVENT block');
                }
                $overrideVeventBlock = $m[0];

                // ✅ 4) 원본 VCALENDAR에 override VEVENT를 추가
                if (strpos($originIcs, $overrideVeventBlock) === false) {
                    $originIcs = preg_replace('/END:VCALENDAR\r\n?/i', $overrideVeventBlock . "END:VCALENDAR\r\n", $originIcs, 1);
                }

                // ✅ 5) PUT with If-Match (원본 href에 overwrite)
                $caldav->updateObject($row['href'], $originIcs, $row['etag'] ?? null);

                // ✅ 6) Sync
                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

                if (!$userId) {
                    throw new \RuntimeException('Invalid session');
                }

                return $this->sync()->syncOneEventByUid(
                    $uid,
                    $synologyLoginId,   // 2️⃣ synology_login_id
                    $userId,            // 3️⃣ actor (ERP user id)
                    [
                        'calendar_id' => $row['calendar_id'],
                        'admin_event_color' => $payload['admin_event_color'] ?? null
                    ]
                );
            }
            elseif ($scope === 'future') {
            
            
            }
            
            elseif ($scope === 'all') {
                
            }
    

            

            // 🔥 2️⃣ 이제 row 사용
            $wasAllDay = (int)$row['all_day'] === 1;

            // payload 값
            $start = (string)($payload['start'] ?? '');
            $end   = (string)($payload['end']   ?? '');

            $payloadIsAllDay =
                array_key_exists('allDay', $payload)
                    ? (bool)$payload['allDay']
                    : $wasAllDay;

            // 🔥 3️⃣ 종일 ↔ 시간 변경이면 rebuild
            if ($wasAllDay !== $payloadIsAllDay) {
                return $this->rebuildEvent($row, $payload);
            }

                        


            // 🔥 object href 최종 확정 (collection 절대 금지)
            $href = $row['href'] ?? null;

            if (!$href && !empty($payload['href'])) {
                $href = trim($payload['href']);
            }

            if (!$href || !str_ends_with($href, '.ics')) {
                throw new \RuntimeException('updateEvent resolved href missing');
            }

            // 🔥 payload 기본값 정규화 (JSON 깨짐 방지)
            $payload = array_merge([
                'allDay' => null,
                'etag'   => null,
                'rrule'  => null,
                'alarms' => [],
            ], $payload);

            // 🔥 href 결정 로직 (DB 우선, payload 보정)
            $href = $row['href'] ?: ($payload['href'] ?? null);
            $etag = $row['etag'] ?? null;

            if (!$href) {
                throw new \RuntimeException('updateEvent resolved href missing');
            }

            // 🔥 DB에 href가 없던 경우 → 즉시 보정 저장
            if (empty($row['href']) && !empty($payload['href'])) {
                $this->logger->warning('[UPDATE] fixing missing href in DB', [
                    'uid'  => $uid,
                    'href' => $payload['href']
                ]);

                $fix = $this->pdo->prepare("
                    UPDATE dashboard_calendar_events
                    SET href = :href
                    WHERE uid = :uid
                    LIMIT 1
                ");
                $fix->execute([
                    ':href' => $payload['href'],
                    ':uid'  => $uid
                ]);
            }



            /* -------------------------------------------------
            * 3️⃣ 기존 ICS 조회
            * ------------------------------------------------- */
            $caldav = $this->caldav();
            $res = $caldav->request('GET', $href);
            $originIcs = is_array($res) && array_key_exists('body', $res)
                ? $res['body']
                : null;
            

            if (!$originIcs) {
                throw new \RuntimeException('ICS not found on CalDAV');
            }

            // 🔥 payload 표준화
            if (isset($payload['allday']) && !isset($payload['allDay'])) {
                $payload['allDay'] = $payload['allday'];
            }

            // 🔥 TZID 감지
            $tzid = $this->ics->extractTzid($originIcs);



            // 🔒 start / end 정규화 (프론트 방어)
            if (!isset($payload['start'])) {
                if (!empty($payload['start_date'])) {
                    if (!empty($payload['allDay'])) {
                        $payload['start'] = $payload['start_date'];
                    } else {
                        $payload['start'] =
                            $payload['start_date'] . ' ' . ($payload['start_time'] ?? '00:00');
                    }
                }
            }

            if (!isset($payload['end'])) {
                if (!empty($payload['end_date'])) {
                    if (!empty($payload['allDay'])) {
                        $payload['end'] = $payload['end_date'];
                    } else {
                        $payload['end'] =
                            $payload['end_date'] . ' ' . ($payload['end_time'] ?? '01:00');
                    }
                }
            }





        /* =========================================================
        * 🔁 RRULE / RDATE / EXDATE 단일 파이프라인
        * ========================================================= */

        // 1️⃣ 기존 반복 상태 추출 (딱 1번만)
        $rruleFromDb  = $this->ics->extractProperty($originIcs, 'RRULE');
        $rdateFromDb  = $this->ics->extractProperty($originIcs, 'RDATE');
        $exdateFromDb = $this->ics->extractProperty($originIcs, 'EXDATE');

        // 작업용 변수
        $rrule  = $rruleFromDb;
        $rdate  = $rdateFromDb;
        $exdate = $exdateFromDb;

        // 2️⃣ 반복 있음 → 반복 없음
        if (
            $rruleFromDb !== null &&
            array_key_exists('rrule', $payload) &&
            empty($payload['rrule'])
        ) {
            $this->logger->debug('[RRULE REMOVE]', ['uid' => $uid]);

            $rrule  = null;
            $rdate  = null;
            $exdate = null;
        }

        // 3️⃣ 반복 수정 (🔥 MONTHLY 보정 포함)
        if (!empty($payload['rrule'])) {

            $rr = preg_replace('/^RRULE:/', '', (string)$payload['rrule']);
        
            if (str_contains($rr, 'FREQ=MONTHLY')) {
        
                if (empty($payload['start'])) {
                    throw new \RuntimeException('MONTHLY requires start date');
                }
        
                $day = (int)substr($payload['start'], 8, 2);
        
                // 기존 BYMONTHDAY 제거
                $rr = preg_replace('/;?BYMONTHDAY=\d+/', '', $rr);
        
                $rr .= ';BYMONTHDAY=' . $day;
            }
        
            $rrule = 'RRULE:' . $rr;
        }
           

            // 🔥 allDay 여부 먼저 선언 (반드시 위에!)
            $isAllDay =
            !empty($payload['allDay']) ||
            !empty($payload['allday']);
        
            // 🔥 allDay + RRULE 일 때 UNTIL 형식 보정
            if ($isAllDay && $rrule) {
                $rrule = preg_replace(
                    '/UNTIL=(\d{4})-(\d{2})-(\d{2})/',
                    'UNTIL=$1$2$3',
                    $rrule
                );
            }
           


            if ($payloadIsAllDay) {

                $startRaw = $payload['start'] ?? $row['dtstart'];
                $endRaw   = $payload['end']   ?? $row['dtend'];
            
                if (!$startRaw) {
                    throw new \RuntimeException('DTSTART missing for all-day event');
                }
            
                $baseStart = substr($startRaw, 0, 10);
                $baseEnd   = $endRaw ? substr($endRaw, 0, 10) : $baseStart;
            
                $dtstartYmd = str_replace('-', '', $baseStart);
            
                // 🔥 반드시 CalendarTime 사용 (서버 TZ 차단)
                $dtendYmd = Time::parseLocal($baseEnd)
                    ->modify('+1 day')
                    ->format('Ymd');
            
                $setLines[] = 'DTSTART;VALUE=DATE:' . $dtstartYmd;
                $setLines[] = 'DTEND;VALUE=DATE:'   . $dtendYmd;
            
            } else {
            
                $tzid = $tzid ?: (string)($this->systemConfig->get('timezone') ?: Time::TZID);
            
                $startRaw = $payload['start'] ?? $row['dtstart'];
                $endRaw   = $payload['end']   ?? $row['dtend'];
            
                if (!$startRaw || !$endRaw) {
                    throw new \RuntimeException('DTSTART/DTEND missing for timed event');
                }
            
                // 🔥 서울시간 기준 정규화 (핵심)
                $startLocal = Time::parseLocal($startRaw);
                $endLocal   = Time::parseLocal($endRaw);
            
                $setLines[] = 'DTSTART;TZID=' . $tzid . ':' .
                    $startLocal->format('Ymd\THis');
            
                $setLines[] = 'DTEND;TZID=' . $tzid . ':' .
                    $endLocal->format('Ymd\THis');
            }







            /* -------------------------------------------------
            * 4️⃣ ICS 패치
            * ------------------------------------------------- */
            $seq = (int)($this->ics->extractSequence($originIcs) ?? 0);

            // 🔥 여기서 먼저 초기화
            $setLines = [];

            $setLines[] = 'SUMMARY:' . $this->ics->escape($payload['title'] ?? $row['title']);
            $setLines[] = 'SEQUENCE:' . ($seq + 1);

            if ($payloadIsAllDay) {

                $startRaw = $payload['start'] ?? null;
                $endRaw   = $payload['end']   ?? null;
            
                if (!$startRaw) {
                    throw new \RuntimeException('DTSTART missing for all-day event');
                }
            
                $baseStart = substr($startRaw, 0, 10);
                $baseEnd   = $endRaw ? substr($endRaw, 0, 10) : $baseStart;
            
                $dtstartYmd = str_replace('-', '', $baseStart);
            
                // 🔥 서버 TZ 영향 완전 차단
                $dtendYmd = Time::parseLocal($baseEnd)
                    ->modify('+1 day')
                    ->format('Ymd');
            
                $setLines[] = 'DTSTART;VALUE=DATE:' . $dtstartYmd;
                $setLines[] = 'DTEND;VALUE=DATE:'   . $dtendYmd;
            
            } else {
            
                $tzid = $tzid ?: Time::TZID;
            
                $startRaw = $payload['start'] ?? null;
                $endRaw   = $payload['end']   ?? null;
            
                if (!$startRaw || !$endRaw) {
                    throw new \RuntimeException('DTSTART/DTEND missing for timed event');
                }
            
                // 🔥 반드시 서울시간 기준 정규화
                $startLocal = Time::parseLocal($startRaw);
                $endLocal   = Time::parseLocal($endRaw);
            
                $setLines[] = 'DTSTART;TZID=' . $tzid . ':' .
                    $startLocal->format('Ymd\THis');
            
                $setLines[] = 'DTEND;TZID=' . $tzid . ':' .
                    $endLocal->format('Ymd\THis');
            }



            if ($rrule) {
                if (!str_starts_with($rrule, 'RRULE:')) {
                    $rrule = 'RRULE:' . $rrule;
                }
                $setLines[] = $rrule;
            }            
            if ($rdate)  $setLines[] = $rdate;
            if ($exdate) {
                $setLines[] = $exdate;
            }

            if (array_key_exists('location', $payload)) {
                $setLines[] = 'LOCATION:' . $this->ics->escape(
                    $payload['location'] ?? ''
                );
            }   

            if (!empty($payload['guests']) && is_array($payload['guests'])) {
                $attendees = $this->buildAttendeeLines($payload['guests']);
                foreach ($attendees as $line) {
                    $setLines[] = $line;
                }
            }
            





            // ❌ RRULE 제거만으로는 rebuild 금지
            $rruleRemoved =
                $rruleFromDb !== null &&
                array_key_exists('rrule', $payload) &&
                empty($payload['rrule']);

            // 날짜 변경 + RRULE 제거 아닌 경우만 rebuild
            $isUiAction = ($payload['__source'] ?? null) === 'ui';

            // ❌ UI edit에서는 rebuild 절대 금지
            $explicitDateChange =
            array_key_exists('start', $payload) ||
            array_key_exists('end', $payload);
        
            if (
                !$isUiAction &&
                $explicitDateChange &&
                !$rruleRemoved &&
                !$payloadIsAllDay && // 🔥 종일 이벤트는 rebuild 금지
                (
                    substr($payload['start'] ?? '', 0, 10) !== substr($row['dtstart'], 0, 10) ||
                    substr($payload['end']   ?? '', 0, 10) !== substr($row['dtend'],   0, 10)
                )
            ) {
                return $this->rebuildEvent($row, $payload);
            }
            
            
            
            if (array_key_exists('description', $payload)) {
                $setLines[] = 'DESCRIPTION:' . $this->ics->escape(
                    (string)$payload['description']
                );
            }
            
            if (array_key_exists('transp', $payload)) {
                $setLines[] = 'TRANSP:' . strtoupper($payload['transp']);
            }
            
            $patchedIcs = $this->ics->patchComponent(
                $originIcs,
                'VEVENT',
                $setLines,
                [
                    'SUMMARY',
                    'DESCRIPTION',
                    'LOCATION',
                    'RRULE',
                    'RDATE',
                    'EXDATE',
                    'DTSTART',
                    'DTEND',
                    'TRANSP'
                ]
            );
            
            // 🔥 기존 VALARM 제거
            $patchedIcs = preg_replace(
                '/BEGIN:VALARM[\s\S]*?END:VALARM\s*/i',
                '',
                $patchedIcs
            );
            
            // 🔥 새 알람 삽입
            if (!empty($payload['alarms'])) {
            
                $alarmBlock = '';
            
                foreach ($payload['alarms'] as $a) {
            
                    if (is_array($a)) {
                        $a = $a['trigger'] ?? $a['value'] ?? null;
                    }
            
                    if (!$a) continue;
            
                    $alarmBlock .=
                        "BEGIN:VALARM\r\n" .
                        "ACTION:DISPLAY\r\n" .
                        "DESCRIPTION:Reminder\r\n" .
                        "TRIGGER:" . $this->ics->normalizeAlarmTrigger((string)$a) . "\r\n" .
                        "END:VALARM\r\n";
                }
            
                if ($alarmBlock !== '') {
                    $patchedIcs = preg_replace(
                        '/END:VEVENT/i',
                        $alarmBlock . "END:VEVENT",
                        $patchedIcs,
                        1
                    );
                }
            }
            
            $this->logger->debug('[PATCHED ICS]', ['ics' => $patchedIcs]);

            /* -------------------------------------------------
            * 5️⃣ CalDAV PUT (If-Match)
            * ------------------------------------------------- */
            // PUT 완료
            $res = $caldav->updateObject($href, $patchedIcs, $etag);

            // 🔥 서버 기준 ICS 다시 가져오기
            $get = $caldav->request('GET', $href);

            $serverIcs = $get['body'] ?? null;
            if (!$serverIcs) {
                throw new \RuntimeException('failed to fetch ICS after update');
            }



            // ✅ PUT 성공 + GET 성공이면 업데이트 성공으로 본다
            // Synology CalDAV는 PUT 성공이 곧 진실이다



            // 🔥 Synology는 ETag를 안 줄 수 있음 → HEAD로 보완
            $newEtag = $res['etag'] ?? null;

            if (!$newEtag) {
                $head = $caldav->request('HEAD', $href);
            
                if (is_array($head)) {
                    $headers = $head['headers'] ?? [];
                    foreach (['ETag','etag'] as $k) {
                        if (!empty($headers[$k][0])) {
                            $newEtag = trim($headers[$k][0], '"');
                            break;
                        }
                    }
                }
            }
            
            // 그래도 없으면 기존 etag 유지
            if (!$newEtag) {
                $newEtag = $etag;
            }
            

            /* -------------------------------------------------
            * 6️⃣ 
            * ------------------------------------------------- */


            $calendarId = $row['calendar_id'] ?? null;

            if (!$calendarId) {
                throw new \RuntimeException(
                    'updateEvent: calendar_id missing in DB for uid: ' . $uid
                );
            }
            
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }
            
            
            $syncResult = $this->sync()->syncOneEventByUid(
                $uid,
                $synologyLoginId,   // 2️⃣ synology_login_id
                $userId,            // 3️⃣ actor (ERP user id)
                [
                    'calendar_id'       => $calendarId,
                    'admin_event_color' => $payload['admin_event_color'] ?? null
                ]
            );
            
            $eventRow = $syncResult['event'] ?? null;
            
            $etagForReturn =
                (is_array($eventRow) ? ($eventRow['etag'] ?? null) : null)
                ?? (is_array($eventRow) ? ($eventRow['extendedProps']['etag'] ?? null) : null)
                ?? (is_array($eventRow) ? ($eventRow['extendedProps']['raw']['_etag'] ?? null) : null)
                ?? ($newEtag ?? null)
                ?? ($etag ?? null);
            
            if (is_string($etagForReturn)) {
                $etagForReturn = trim($etagForReturn);
                $etagForReturn = trim($etagForReturn, '"');
                $etagForReturn = trim($etagForReturn, '"');
            }
            
            return [
                'success' => true,
                'data' => [
                    'uid'  => $uid,
                    'etag' => $etagForReturn
                ],
                'event' => $eventRow
            ];
        }, '[updateEvent]');

    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        
    public function deleteComponent(array $payload): array
    {
        if (isset($payload['uid']) && is_array($payload['uid'])) {
            $payload = $payload['uid'];
        }

        return $this->runAndTrack(function () use ($payload) {

            /* -------------------------------------------------
            * 1️⃣ UID 확인
            * ------------------------------------------------- */
            $uid = $payload['uid']
                ?? throw new \RuntimeException('uid required');

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            /* -------------------------------------------------
            * 2️⃣ DB 조회
            * ------------------------------------------------- */
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM dashboard_calendar_events
                WHERE uid = :uid
                AND synology_login_id = :synology
                LIMIT 1
            ");

            $stmt->execute([
                ':uid' => $uid,
                ':synology' => $synologyLoginId
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new \RuntimeException('event not found');
            }

            /* -------------------------------------------------
            * 3️⃣ 권한 검사
            * ------------------------------------------------- */
            $this->assertCalendarWritePermission($row['calendar_id']);

            /* -------------------------------------------------
            * 4️⃣ 이미 휴지통이면 성공 처리
            * ------------------------------------------------- */
            if ((int)$row['is_active'] === 0) {
                return [
                    'success' => true,
                    'data' => [
                        'uid' => $uid,
                        'deleted' => 'already'
                    ]
                ];
            }

            /* -------------------------------------------------
            * 5️⃣ scope 처리
            * ------------------------------------------------- */
            $scope = $payload['scope'] ?? 'all';
            $recurrenceId = $payload['recurrence_id'] ?? null;

            if ($scope === 'single' && $recurrenceId) {

                $caldav = $this->caldav();

                $res = $caldav->request('GET', $row['href']);
                $originIcs = $res['body'] ?? null;

                if (!$originIcs) {
                    throw new \RuntimeException('ICS not found');
                }

                $existingExdate = $this->ics->extractProperty($originIcs, 'EXDATE');

                $cleanRecurrenceId = preg_replace('/[^0-9]/', '', $recurrenceId);
                $newExdateLine = 'EXDATE;VALUE=DATE:' . $cleanRecurrenceId;

                if ($existingExdate) {
                    $setLines = [$existingExdate . ',' . $cleanRecurrenceId];
                } else {
                    $setLines = [$newExdateLine];
                }

                $patchedIcs = $this->ics->patchComponent(
                    $originIcs,
                    'VEVENT',
                    $setLines,
                    ['EXDATE']
                );

                $caldav->updateObject($row['href'], $patchedIcs, $row['etag']);

                return $this->sync()->syncOneEventByUid(
                    $uid,
                    $synologyLoginId,
                    $userId
                );
            }

            /* -------------------------------------------------
            * 6️⃣ Soft Delete
            * ------------------------------------------------- */
            $stmt = $this->pdo->prepare("
                UPDATE dashboard_calendar_events
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :user
                WHERE uid = :uid
                AND synology_login_id = :synology
            ");

            $stmt->execute([
                ':uid' => $uid,
                ':synology' => $synologyLoginId,
                ':user' => $userId
            ]);

            return [
                'success' => true,
                'data' => [
                    'uid' => $uid,
                    'deleted' => 'soft'
                ]
            ];

        }, '[deleteComponent]');
    }


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function createTask(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {
            $this->logger->debug('[CREATE TASK] Received payload', $payload);

            $caldav = $this->caldav();

            // 필수값 확인
            $calendarId = $payload['calendar_id']
            ?? throw new \RuntimeException('calendar_id required');
            
            $stmt = $this->pdo->prepare("
                SELECT href
                FROM dashboard_calendar_list
                WHERE id = :id
                AND type = 'task'
                AND is_active = 1
                LIMIT 1
            ");
            
            $stmt->execute([':id' => $calendarId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row || empty($row['href'])) {
                throw new \RuntimeException('task calendar not registered or inactive');
            }

            $this->assertCalendarWritePermission($calendarId);

            $collectionHref = $this->normalizeCollectionHref($row['href']);
            $this->logger->debug('[CREATE TASK] Collection href: ' . $collectionHref);

            $calendarId = $this->hrefToId($collectionHref);
            $this->logger->debug('[CREATE TASK] Calendar ID: ' . $calendarId);

            // DB에서 캘린더 확인
            $stmt = $this->pdo->prepare("SELECT id FROM dashboard_calendar_list WHERE id = :id AND type = 'task' AND is_active = 1 LIMIT 1");
            $stmt->execute([':id' => $calendarId]);
            if (!$stmt->fetch()) {
                throw new \RuntimeException('task calendar not registered or inactive');
            }

            $this->logger->debug('[CREATE TASK] Task calendar is active');

            // UID 및 href 생성
            $uid  = bin2hex(random_bytes(16));
            $href = $collectionHref . $uid . '.ics';
            $this->logger->debug('[CREATE TASK] Generated UID: ' . $uid);
            $this->logger->debug('[CREATE TASK] Generated href: ' . $href);

            // `etag` 초기화 (null로 설정)
            $etag = null;

            // ICS 생성
            $rawLines = [];
            if (!empty($payload['description'])) {
                $rawLines[] = 'DESCRIPTION:' . $this->ics->escape($payload['description']);
                $this->logger->debug('[CREATE TASK] Added DESCRIPTION');
            }

            // DUE 처리 (시간 포함 여부 확인) - 서울시간 -> UTC 변환
            if (!empty($payload['due'])) {
                $this->logger->debug('[CREATE TASK] Processing DUE');
                $dueRaw = (string)$payload['due'];

                // Determine if the event is all-day
                // 🔥 날짜 형식이면 자동으로 종일 처리
                $dueRaw = (string)$payload['due'];

                $dueIsDateOnly =
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueRaw) ||
                    preg_match('/^\d{8}$/', $dueRaw);

                $isAllDay =
                    $dueIsDateOnly ||
                    !empty($payload['allDay']);

                // Pass both arguments to the processDueTime method
                $dueData = $this->processDueTime($dueRaw, $isAllDay);

                // Continue with the rest of your code...
                $rawLines = array_merge($rawLines, $dueData['rawLines'] ?? []);
                $payloadForDb = $dueData['payloadForDb'];
                $this->logger->debug('[CREATE TASK] Processed DUE: ' . json_encode($dueData));
            }

            // STATUS
            $status = strtoupper($payload['status'] ?? 'NEEDS-ACTION');
            $rawLines[] = 'STATUS:' . $status;

            // PERCENT
            $percent = isset($payload['percent'])
                ? max(0, min(100, (int)$payload['percent']))
                : ($status === 'COMPLETED' ? 100 : 0);

            $rawLines[] = 'PERCENT-COMPLETE:' . $percent;

            // PRIORITY
            if (isset($payload['priority']) && $payload['priority'] !== '') {
                $rawLines[] = 'PRIORITY:' . (int)$payload['priority'];
            }

            $this->logger->debug('[CREATE TASK] Added status, percent, and priority');

            // 알람 처리 (단순히 ICS 내용에 알람을 추가하는 방식)
            if (!empty($payload['alarms'])) {
                $alarmBlock = '';

                // 알람 데이터를 VTODO ICS 형식에 맞게 추가
                foreach ($payload['alarms'] as $a) {
                    $trigger = $a['trigger'] ?? null;
                    if (!$trigger) continue;

                    // 알람 내용 추가
                    $alarmBlock .= "BEGIN:VALARM\r\n" . "ACTION:DISPLAY\r\n" . "DESCRIPTION:Reminder\r\n" . "TRIGGER:" . $trigger . "\r\n" . "END:VALARM\r\n";
                }

                // 기존 ICS 내용에 알람을 추가
                $ics = $this->ics->buildIcs('VTODO', [
                    'uid'       => $uid,
                    'title'     => $payload['title'] ?? '',
                    'raw_lines' => array_merge($rawLines, [$alarmBlock]),  // 알람 블록 추가
                ]);
                $this->logger->debug('[CREATE TASK] Generated ICS content with alarms');
            } else {
                // 알람이 없으면 기존 생성 로직으로 진행
                $ics = $this->ics->buildIcs('VTODO', [
                    'uid'       => $uid,
                    'title'     => $payload['title'] ?? '',
                    'raw_lines' => $rawLines,
                ]);
                $this->logger->debug('[CREATE TASK] Generated ICS content without alarms');
            }

            // CalDAV PUT 요청
            try {
                $res = $caldav->createObject($href, $ics);
                $etag = $res['etag'] ?? null;
                $this->logger->debug('[CREATE TASK] CalDAV task PUT successful');
            } catch (\Throwable $e) {
                $this->logger->error('[CREATE TASK] CalDAV task PUT failed', ['error' => $e->getMessage()]);
                throw new \RuntimeException('CalDAV task PUT failed');
            }



            // 동기화
            $get = $caldav->request('GET', $href);
            $originIcs = $get['body'] ?? null;
            
            if (!$originIcs) {
                throw new \RuntimeException('ICS not returned after create');
            }
            
            // 🔥 Sync
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }

            $this->sync()->syncOneTaskByUid(
                $uid,
                $synologyLoginId,   // 2️⃣ synology_login_id
                $userId,            // 3️⃣ actor (ERP user id)
                [
                    'calendar_id'     => $calendarId,
                    'collection_href' => $collectionHref,
                    'force_href'      => $href
                ]
            );

            // 🔥 최신 전체 Task 다시 조회 (패널용)
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $tasks = (new QueryService($this->pdo))
                ->getAllTasksMapped($userId, $synologyLoginId);

            return [
                'success' => true,
                'data' => [
                    'uid'   => $uid,
                    'tasks' => $tasks
                ]
            ];

        }, '[createTask]');
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function updateTask(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {
            /* -------------------------------------------------
            * 1️⃣ uid 필수
            * ------------------------------------------------- */
            $uid = $payload['uid'] ?? throw new \RuntimeException('uid required');
            $this->logger->debug('[UPDATE TASK] Received UID: ' . $uid);

            /* -------------------------------------------------
            * 2️⃣ 기존 Task 조회
            * ------------------------------------------------- */
            $calendarId = $payload['calendar_id'] ?? null;

            if (!$calendarId) {
                throw new \RuntimeException('calendar_id required');
            }

            $this->logger->debug('[UPDATE TASK] Calendar ID: ' . $calendarId);

            $stmt = $this->pdo->prepare("
                SELECT * FROM dashboard_calendar_tasks
                WHERE uid = :uid
                AND synology_login_id = :synology_login_id
                AND is_active = 1
                LIMIT 1
            ");

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':uid' => $uid,
                ':synology_login_id' => $synologyLoginId
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
            
                $this->logger->debug('[UPDATE TASK] Task not found, syncing...');
            
                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();
            
                $sync = new SyncService($this->pdo);
                $sync->syncOneTaskByUid(
                    $uid,
                    $synologyLoginId,
                    $userId
                );
            
                $stmt->execute([
                    ':uid' => $uid,
                    ':synology_login_id' => $synologyLoginId
                ]);
            
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
                if (!$row) {
                    throw new \RuntimeException('task not cached after sync');
                }
            }
            
            $calendarId = (string)$row['calendar_id'];
            $this->assertCalendarWritePermission($calendarId);

            $this->logger->debug('[UPDATE TASK] Task found in DB, continuing update...');

            $href = $row['href'] ?? null;
            $etag = $row['etag'] ?? null;

            $caldav = $this->caldav();

            $collectionHref =
                $payload['collection_href']
                ?? (!empty($href) ? (dirname($href) . '/') : null);

            $collectionHref = $collectionHref
                ? $this->normalizeCollectionHref((string)$collectionHref)
                : null;

            /* =========================================================
            * 🔥 href 최종 보정
            * - DB href가 정상 .ics 이면 그대로 사용
            * - 없거나 비정상이면 그때만 collection에서 UID 검색
            * ========================================================= */
            if (!$href || !str_ends_with($href, '.ics')) {

                if (!$collectionHref) {
                    throw new \RuntimeException('task href missing/invalid');
                }

                $realHref = $this->resolveTaskObjectHrefByUid(
                    $caldav,
                    $collectionHref,
                    $uid
                );

                if (!$realHref) {
                    throw new \RuntimeException('task href missing/invalid');
                }

                $href = $realHref;

                $fix = $this->pdo->prepare("
                    UPDATE dashboard_calendar_tasks
                    SET href = :href
                    WHERE uid = :uid
                    LIMIT 1
                ");
                $fix->execute([
                    ':href' => $href,
                    ':uid'  => $uid
                ]);
            }

            $this->logger->debug('[UPDATE TASK] Task href: ' . $href);
            $this->logger->debug('[UPDATE TASK] Task etag: ' . $etag);

            /* -------------------------------------------------
            * 3️⃣ 기존 ICS 로드
            * ------------------------------------------------- */
            /* -------------------------------------------------
            * 3️⃣ 기존 ICS 로드 (GET 필수)
            * ------------------------------------------------- */
            $res = $caldav->request('GET', $href);
            $originIcs = $res['body'] ?? null;

            /**
             * ✅ href가 stale이면 Synology에서 GET이 실패할 수 있음
             * → Sync로 DB href/etag 갱신 후 1회 재시도
             */
            if (!$originIcs) {

                $this->logger->warning('[UPDATE TASK] ICS not found, trying sync refresh', [
                    'uid'  => $uid,
                    'href' => $href,
                    'calendar_id' => $calendarId,
                ]);

                $sync = new SyncService($this->pdo);

                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();
                
                if (!$userId) {
                    throw new \RuntimeException('Invalid session');
                }
             
                $sync->syncOneTaskByUid(
                    $uid,
                    $synologyLoginId,   // 2️⃣ synology_login_id
                    $userId,            // 3️⃣ actor (ERP user id)
                    [
                        'calendar_id' => $calendarId,
                        'collection_href' => $collectionHref ?? (dirname($href) . '/')
                    ]
                );

                // 최신 href/etag 다시 읽기
                $stmt2 = $this->pdo->prepare("
                    SELECT * FROM dashboard_calendar_tasks
                    WHERE uid = :uid
                    AND synology_login_id = :synology_login_id
                    AND is_active = 1
                    LIMIT 1
                ");
                
                $stmt2->execute([
                    ':uid' => $uid,
                    ':synology_login_id' => $synologyLoginId
                ]);
                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

                if ($row2 && !empty($row2['href'])) {
                    $href = (string)$row2['href'];
                    $etag = $row2['etag'] ?? $etag;
                }

                // 다시 GET
                $res = $caldav->request('GET', $href);
                $originIcs = $res['body'] ?? null;

                if (!$originIcs) {
                    $this->logger->error('[UPDATE TASK] ICS still not found after sync', [
                        'uid'  => $uid,
                        'href' => $href
                    ]);
                    throw new \RuntimeException('ICS not found');
                }
            }

            $this->logger->debug('[UPDATE TASK] Loaded ICS content');

            // 최신 etag로 덮어쓰기 (DB용: 따옴표 제거)
            $latestEtag = null;
            $headers = $res['headers'] ?? [];
            foreach (['ETag', 'etag'] as $k) {
                if (!empty($headers[$k][0])) {
                    $latestEtag = trim((string)$headers[$k][0]);
                    $latestEtag = trim($latestEtag, '"'); // DB에는 따옴표 없는 형태로 저장
                    break;
                }
            }

            if ($latestEtag) {
                $etag = $latestEtag; // 이후 로직 전체는 "따옴표 없는 etag"가 기준
            }

            $this->logger->debug('[UPDATE TASK] ETag updated: ' . $etag);

            /* -------------------------------------------------
            * 4️⃣ 상태, 퍼센트, 타이틀, 설명, due 업데이트 (🔥 실제 ICS PATCH)
            * ------------------------------------------------- */

            // SEQUENCE 증가
            $seq = (int)($this->ics->extractSequence($originIcs) ?? 0);

            // patch에 넣을 라인들
            $setLines = [
                'SEQUENCE:' . ($seq + 1),
                // Synology 안정화용(권장)
                'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            ];

            // TITLE
            if (array_key_exists('title', $payload)) {
                $setLines[] = 'SUMMARY:' . $this->ics->escape((string)$payload['title']);
            }

            // DESCRIPTION
            if (array_key_exists('description', $payload)) {
                $setLines[] = 'DESCRIPTION:' . $this->ics->escape((string)$payload['description']);
            }

            /**
             * ✅ DUE
             * - payload가 YYYYMMDD(8자리)면 무조건 VALUE=DATE로 저장
             * - 그렇지 않으면 기존 정책(allDay/row 기반) 적용
             */
            $payloadForDb = []; // (DB용 파생값 필요시 대비)

            if (!empty($payload['due'])) {
                $dueRaw = (string)$payload['due'];

                // 🔥 핵심: YYYYMMDD면 무조건 날짜-only로 강제
                $dueIsDateOnly = (bool)preg_match('/^\d{8}$/', $dueRaw);

                // 🔥 기존 DB all_day는 절대 신뢰하지 말 것
                // 수정 시에는 payload 기준으로만 판단
                
                $isAllDay =
                    $dueIsDateOnly ||
                    (!empty($payload['allDay']) && $payload['allDay'] === true);

                $dueData = $this->processDueTime($dueRaw, $isAllDay);
                $payloadForDb = $dueData['payloadForDb'] ?? [];

                // processDueTime()이 만든 DUE 라인만 setLines에 삽입
                $dueLine = null;
                foreach (($dueData['rawLines'] ?? []) as $line) {
                    if (is_string($line) && str_starts_with($line, 'DUE')) {
                        $dueLine = $line;
                        break;
                    }
                }
                if ($dueLine) {
                    $setLines[] = $dueLine;
                }
            }

            // STATUS / PERCENT (단순 정책)
            if (array_key_exists('status', $payload)) {
                $status = strtoupper((string)$payload['status']);
                if ($status === 'COMPLETED') {
                    $setLines[] = 'STATUS:COMPLETED';
                    $setLines[] = 'PERCENT-COMPLETE:100';
                    $setLines[] = 'COMPLETED:' . gmdate('Ymd\THis\Z');
                } else {
                    $setLines[] = 'STATUS:NEEDS-ACTION';
                    $setLines[] = 'PERCENT-COMPLETE:0';
                    // COMPLETED 제거
                    $setLines[] = 'COMPLETED:';
                }
            }

            // percent 직접 지정 케이스(있으면 우선 반영)
            if (array_key_exists('percent', $payload)) {
                $p = max(0, min(100, (int)$payload['percent']));
                $setLines[] = 'PERCENT-COMPLETE:' . $p;
            }

            // PRIORITY
            if (array_key_exists('priority', $payload) && $payload['priority'] !== null && $payload['priority'] !== '') {
                $setLines[] = 'PRIORITY:' . (int)$payload['priority'];
            }

            // ✅ 여기서 진짜로 ICS를 patch 해야 한다
            $patchedIcs = $this->ics->patchComponent(
                $originIcs,
                'VTODO',
                $setLines,
                [
                    'SEQUENCE',
                    'DTSTAMP',
                    'SUMMARY',
                    'DESCRIPTION',
                    'DUE',
                    'STATUS',
                    'PERCENT-COMPLETE',
                    'COMPLETED',
                    'PRIORITY',
                ]
            );

            // 🔔 알람 처리 (UPDATE) — alarms 키가 오면 “기존 알람 제거 후 재삽입”
            if (array_key_exists('alarms', $payload)) {

                // 기존 알람 제거
                $patchedIcs = preg_replace(
                    '/BEGIN:VALARM[\s\S]*?END:VALARM\r?\n?/i',
                    '',
                    $patchedIcs
                );

                // 새 알람 있으면 추가
                if (!empty($payload['alarms']) && is_array($payload['alarms'])) {

                    $alarmBlock = '';

                    foreach ($payload['alarms'] as $a) {
                        $trigger = $a['trigger'] ?? $a['value'] ?? null;
                        if (!$trigger) continue;

                        $alarmBlock .=
                            "BEGIN:VALARM\r\n" .
                            "ACTION:DISPLAY\r\n" .
                            "DESCRIPTION:Reminder\r\n" .
                            "TRIGGER:" . $this->ics->normalizeAlarmTrigger((string)$trigger) . "\r\n" .
                            "END:VALARM\r\n";
                    }

                    if ($alarmBlock !== '') {
                        $patchedIcs = preg_replace(
                            '/END:VTODO\r?\n?/i',
                            $alarmBlock . "END:VTODO\r\n",
                            $patchedIcs,
                            1
                        );
                    }
                }
            }

            /* -------------------------------------------------
            * 5️⃣ CalDAV PUT
            * ------------------------------------------------- */
            // If-Match 전송용(따옴표 포함) ETag 구성
            $ifMatch = $etag ? ('"' . trim($etag, '"') . '"') : null;

            $put = $caldav->updateObject($href, $patchedIcs, $ifMatch);
            $this->logger->debug('[UPDATE TASK] PUT request sent to CalDAV');

            // PUT 응답 확인
            if (isset($put['status']) && $put['status'] === 412) {
                $this->logger->debug('[UPDATE TASK] ETag mismatch, re-fetching...');
                $head = $caldav->request('HEAD', $href);
                $headers = $head['headers'] ?? [];
                $fresh = null;
                foreach (['ETag', 'etag'] as $k) {
                    if (!empty($headers[$k][0])) {
                        $fresh = trim((string)$headers[$k][0]);
                        $fresh = trim($fresh, '"');
                        break;
                    }
                }

                if ($fresh) {
                    $etag = $fresh;
                    $ifMatch = '"' . $etag . '"';
                    $put = $caldav->updateObject($href, $patchedIcs, $ifMatch);
                    $this->logger->debug('[UPDATE TASK] PUT request retried with fresh ETag');
                }
            }

            $this->logger->debug('[UPDATE TASK] PUT response: ' . json_encode($put));

            // 최종 실패 처리
            if (!is_array($put) || (isset($put['success']) && $put['success'] === false)) {
                $this->logger->error('[TASK PUT FAILED]', ['uid' => $uid, 'href' => $href, 'etag_used' => $etag, 'response' => $put]);
                throw new \RuntimeException('CalDAV PUT failed');
            }

            // 새 ETag 확보
            $newEtag = $etag;
            if (is_array($put)) {
                $headers = $put['headers'] ?? [];
                foreach (['ETag', 'etag'] as $k) {
                    if (!empty($headers[$k][0])) {
                        $tmp = trim((string)$headers[$k][0]);
                        $newEtag = trim($tmp, '"');
                        break;
                    }
                }
            }

            // DB에 ETag 갱신
            if ($newEtag && $newEtag !== $etag) {
                $fix = $this->pdo->prepare("UPDATE dashboard_calendar_tasks SET etag = :etag WHERE uid = :uid AND calendar_id = :calendar_id LIMIT 1");
                $fix->execute([':etag' => $newEtag, ':uid' => $uid, ':calendar_id' => $calendarId]);
                $this->logger->debug('[UPDATE TASK] ETag updated in DB');
            }

            // Sync
            $collectionHref = dirname($href) . '/';

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }

            $syncResult = $this->sync()->syncOneTaskByUid(
                $uid,
                $synologyLoginId,   // 2️⃣ synology_login_id
                $userId,            // 3️⃣ actor (ERP user id)
                [
                    'calendar_id'    => $calendarId,
                    'collection_href'=> $collectionHref
                ]
            );

            $taskRow = $syncResult['task'] ?? null;

            // 🔥 최신 전체 Task 다시 조회 (패널용)
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $tasks = (new QueryService($this->pdo))
                ->getAllTasksMapped($userId, $synologyLoginId);

            return [
                'success' => true,
                'data' => [
                    'uid'   => $uid,
                    'etag'  => $taskRow['etag'] ?? null,
                    'tasks' => $tasks
                ]
            ];

        }, '[updateTask]');
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function updateTaskComplete(string $uid, string $calendarId, bool $completed): array
    {
        return $this->runAndTrack(function () use ($uid, $calendarId, $completed) {
            /* -------------------------------------------------
            * 1️⃣ uid 필수
            * ------------------------------------------------- */
            $this->logger->debug('[UPDATE TASK COMPLETE] Received UID: ' . $uid);

            /* -------------------------------------------------
            * 2️⃣ 기존 Task 조회
            * ------------------------------------------------- */
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt = $this->pdo->prepare("
                SELECT * FROM dashboard_calendar_tasks
                WHERE uid = :uid
                AND calendar_id = :calendar_id
                AND synology_login_id = :synology_login_id
                LIMIT 1
            ");
            
            $stmt->execute([
                ':uid' => $uid,
                ':calendar_id' => $calendarId,
                ':synology_login_id' => $synologyLoginId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->logger->debug('[UPDATE TASK COMPLETE] Task not found');
                throw new \RuntimeException('task not found');
            }

            $this->logger->debug('[UPDATE TASK COMPLETE] Task found in DB, continuing update...');

            $href = $row['href'] ?? null;
            $etag = $row['etag'] ?? null;

            if (!$href || !str_ends_with($href, '.ics')) {
                throw new \RuntimeException('task href missing/invalid');
            }

            $this->logger->debug('[UPDATE TASK COMPLETE] Task href: ' . $href);
            $this->logger->debug('[UPDATE TASK COMPLETE] Task etag: ' . $etag);

            /* -------------------------------------------------
            * 3️⃣ 기존 ICS 로드
            * ------------------------------------------------- */
            $caldav = $this->caldav();
            $res = $caldav->request('GET', $href);
            $originIcs = $res['body'] ?? null;
            
            // ✅ href가 stale이면 Synology에서 못 찾는다 → Sync로 href/etag 갱신 후 재시도
            if (!$originIcs) {
            
                $this->logger->warning('[UPDATE TASK] ICS not found, trying sync refresh', [
                    'uid'  => $uid,
                    'href' => $href,
                    'calendar_id' => $calendarId,
                ]);
            
                // 1) Sync로 DB 캐시(href/etag) 갱신
                $sync = new SyncService($this->pdo);

                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

                if (!$userId) {
                    throw new \RuntimeException('Invalid session');
                }
    
                $sync->syncOneTaskByUid(
                    $uid,
                    $synologyLoginId,   // 2️⃣ synology_login_id
                    $userId,            // 3️⃣ actor (ERP user id)
                    [
                        'calendar_id'     => $calendarId,
                        'collection_href' => (dirname($href) . '/')
                    ]
                );
            
                // 2) DB에서 최신 href/etag 다시 읽기
                $stmt2 = $this->pdo->prepare("
                    SELECT * FROM dashboard_calendar_tasks
                    WHERE uid = :uid 
                    AND is_active = 1
                    LIMIT 1
                ");
                $stmt2->execute([':uid' => $uid]);
                $row2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
            
                if ($row2 && !empty($row2['href'])) {
                    $href = (string)$row2['href'];
                    $etag = $row2['etag'] ?? $etag;
                }
            
                // 3) 다시 GET
                $res = $caldav->request('GET', $href);
                $originIcs = $res['body'] ?? null;
            
                if (!$originIcs) {
                    $this->logger->error('[UPDATE TASK] ICS still not found after sync', [
                        'uid'  => $uid,
                        'href' => $href
                    ]);
                    throw new \RuntimeException('ICS not found');
                }
            }

            $this->logger->debug('[UPDATE TASK COMPLETE] Loaded ICS content');

            // 최신 etag로 덮어쓰기 (DB용: 따옴표 제거)
            $latestEtag = null;
            $headers = $res['headers'] ?? [];
            foreach (['ETag', 'etag'] as $k) {
                if (!empty($headers[$k][0])) {
                    $latestEtag = trim((string)$headers[$k][0]);
                    $latestEtag = trim($latestEtag, '"'); // DB에는 따옴표 없는 형태로 저장
                    break;
                }
            }

            if ($latestEtag) {
                $etag = $latestEtag; // 이후 로직 전체는 "따옴표 없는 etag"가 기준
            }

            $this->logger->debug('[UPDATE TASK COMPLETE] ETag updated: ' . $etag);

            /* -------------------------------------------------
            * 4️⃣ 상태 업데이트 (양방향)
            * ------------------------------------------------- */
            $setLines = [];

            // SEQUENCE 증가
            $seq = (int)($this->ics->extractSequence($originIcs) ?? 0);
            $setLines[] = 'SEQUENCE:' . ($seq + 1);
            $this->logger->debug('[UPDATE TASK COMPLETE] Sequence incremented to: ' . ($seq + 1));

            // 상태에 따른 PERCENT 설정
            if ($completed) {
                // `NEEDS-ACTION` -> `COMPLETED`로 변경
                $setLines[] = 'PERCENT-COMPLETE:100'; // 완료 상태로 100%
                $setLines[] = 'STATUS:COMPLETED';
                $setLines[] = 'COMPLETED:' . gmdate('Ymd\THis\Z');
                $this->logger->debug('[UPDATE TASK COMPLETE] Task marked as COMPLETED');
            } else {
                // `COMPLETED` -> `NEEDS-ACTION`으로 변경
                $setLines[] = 'PERCENT-COMPLETE:0'; // 다시 0으로 설정
                $setLines[] = 'STATUS:NEEDS-ACTION';
                $setLines[] = 'COMPLETED:'; // COMPLETED 제거
                $this->logger->debug('[UPDATE TASK COMPLETE] Task marked as NEEDS-ACTION');
            }

            // Synology 완료 반영 필수
            $setLines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $this->logger->debug('[UPDATE TASK COMPLETE] DTSTAMP updated');

            // ICS 업데이트
            $updatedIcs = $this->ics->patchComponent($originIcs, 'VTODO', $setLines, ['STATUS', 'PERCENT-COMPLETE', 'DTSTAMP', 'COMPLETED']);
            $this->logger->debug('[UPDATE TASK COMPLETE] ICS patched with updated status');

            /* -------------------------------------------------
            * 5️⃣ CalDAV PUT 요청
            * ------------------------------------------------- */
            // If-Match 전송용(따옴표 포함) ETag 구성
            $ifMatch = $etag ? ('"' . trim($etag, '"') . '"') : null;

            $put = $caldav->updateObject($href, $updatedIcs, $ifMatch);
            $this->logger->debug('[UPDATE TASK COMPLETE] PUT request sent to CalDAV');

            // PUT 응답 확인
            if (isset($put['status']) && $put['status'] === 412) {
                $this->logger->debug('[UPDATE TASK COMPLETE] ETag mismatch, re-fetching...');
                $head = $caldav->request('HEAD', $href);
                $headers = $head['headers'] ?? [];
                $fresh = null;
                foreach (['ETag', 'etag'] as $k) {
                    if (!empty($headers[$k][0])) {
                        $fresh = trim((string)$headers[$k][0]);
                        $fresh = trim($fresh, '"');
                        break;
                    }
                }

                if ($fresh) {
                    $etag = $fresh;
                    $ifMatch = '"' . $etag . '"';
                    $put = $caldav->updateObject($href, $updatedIcs, $ifMatch);
                    $this->logger->debug('[UPDATE TASK COMPLETE] PUT request retried with fresh ETag');
                }
            }

            $this->logger->debug('[UPDATE TASK COMPLETE] PUT response: ' . json_encode($put));

            // 최종 실패 처리
            if (!is_array($put) || (isset($put['success']) && $put['success'] === false)) {
                $this->logger->error('[TASK PUT FAILED]', ['uid' => $uid, 'href' => $href, 'etag_used' => $etag, 'response' => $put]);
                throw new \RuntimeException('CalDAV PUT failed');
            }

            // 새 ETag 확보
            $newEtag = $etag;
            if (is_array($put)) {
                $headers = $put['headers'] ?? [];
                foreach (['ETag', 'etag'] as $k) {
                    if (!empty($headers[$k][0])) {
                        $tmp = trim((string)$headers[$k][0]);
                        $newEtag = trim($tmp, '"');
                        break;
                    }
                }
            }

            // DB에 ETag 갱신
            if ($newEtag && $newEtag !== $etag) {
                $fix = $this->pdo->prepare("UPDATE dashboard_calendar_tasks SET etag = :etag WHERE uid = :uid AND calendar_id = :calendar_id LIMIT 1");
                $fix->execute([':etag' => $newEtag, ':uid' => $uid, ':calendar_id' => $calendarId]);
                $this->logger->debug('[UPDATE TASK COMPLETE] ETag updated in DB');
            }

            // Sync
            $collectionHref = dirname($href) . '/';

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }

            return $this->sync()->syncOneTaskByUid(
                $uid,
                $synologyLoginId,   // 2️⃣ synology_login_id
                $userId,            // 3️⃣ actor (ERP user id)
                [
                    'calendar_id'    => $calendarId,
                    'collection_href'=> $collectionHref
                ]
            );

        }, '[updateTaskComplete]');
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function toggleTaskComplete(string $uid, string $calendarId, bool $completed)
    {
        $uid = preg_replace('/^task_/', '', $uid);
        
        // 상태 값을 'COMPLETED' 또는 'NEEDS-ACTION'으로 처리
        if ($completed) {
            $status = 'COMPLETED';
            $percent = 100;
        } else {
            $status = 'NEEDS-ACTION';
            $percent = 0;
        }

        // 이제 updateTaskComplete를 호출하여 COMPLETED 상태만 처리
        try {
            return $this->updateTaskComplete($uid, $calendarId, $completed);
        } catch (\RuntimeException $e) {
            $this->logger->error('[TOGGLE TASK COMPLETE] Failed to update task: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function deleteTask(array $payload): array
    {
            // 🔥 uid 중첩 방어
            if (isset($payload['uid']) && is_array($payload['uid'])) {
                $payload = $payload['uid'];
            }

        return $this->runAndTrack(function () use ($payload) {
    
            /* -------------------------------------------------
            * 1️⃣ UID 확인
            * ------------------------------------------------- */
            $uid = $payload['uid']
                ?? throw new \RuntimeException('uid required');
    
            /* -------------------------------------------------
            * 2️⃣ DB에서 Task 조회
            * ------------------------------------------------- */
            $stmt = $this->pdo->prepare("
                    SELECT * FROM dashboard_calendar_tasks
                    WHERE uid = :uid
                    AND synology_login_id = :synology_login_id
                    LIMIT 1
            ");
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':uid' => $uid,
                ':synology_login_id' => $synologyLoginId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$row) {
                throw new \RuntimeException('task not found or already deleted');
            }
            
            $this->assertCalendarWritePermission($row['calendar_id']);

            /* -------------------------------------------------
            * 3️⃣ Soft Delete (DB only)
            * ------------------------------------------------- */
            $stmt = $this->pdo->prepare("
                UPDATE dashboard_calendar_tasks
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :user
                WHERE uid = :uid
            ");
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':uid'  => $uid,
                ':user' => $userId
            ]);

            /* -------------------------------------------------
            * 4️⃣ 최신 전체 Task 다시 조회 (패널용)
            * ------------------------------------------------- */
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $tasks = (new QueryService($this->pdo))
                ->getAllTasksMapped($userId, $synologyLoginId);

            return [
                'success' => true,
                'data' => [
                    'uid'     => $uid,
                    'deleted' => 'soft',
                    'tasks'   => $tasks
                ]
            ];

        }, '[deleteTask]');
    }
    

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function hardDeleteTask(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {



            $uid = $payload['uid']
                ?? throw new \RuntimeException('uid required');

            // 🔥 서버에서 최종 정규화
            $uid = preg_replace('/^(task_|event_)/', '', $uid);
            $uid = trim($uid);

            if ($uid === '') {
                throw new \RuntimeException('uid empty');
            }

            /* =====================================================
            * 0️⃣ 진입 로그
            * ===================================================== */
            $this->logger->info('[hardDeleteTask] START', [
                'payload' => $payload
            ]);

            $uid = $payload['uid']
            ?? throw new \RuntimeException('uid required');
        
            $uid = preg_replace('/^task_/', '', $uid);
            
            $this->logger->info('[hardDeleteTask] UID normalized', [
                'uid' => $uid
            ]);

            /* =====================================================
            * 1️⃣ DB 조회
            * ===================================================== */
            $stmt = $this->pdo->prepare("
                    SELECT * FROM dashboard_calendar_tasks
                    WHERE uid = :uid
                    AND synology_login_id = :synology_login_id
                    LIMIT 1
            ");

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':uid' => $uid,
                ':synology_login_id' => $synologyLoginId
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logger->info('[hardDeleteTask] DB fetch result', [
                'found' => (bool)$row,
                'row'   => $row
            ]);

            if (!$row) {
                $this->logger->warning('[hardDeleteTask] Task not found in DB', [
                    'uid' => $uid
                ]);

                return ['deleted' => 'already-removed'];
            }

            $this->assertCalendarWritePermission($row['calendar_id']);

            /* =====================================================
            * 2️⃣ CalDAV 삭제
            * ===================================================== */
            if (!empty($row['href'])) {

                $this->logger->info('[hardDeleteTask] CalDAV delete attempt', [
                    'href' => $row['href'],
                    'etag' => $row['etag']
                ]);

                $caldav = $this->caldav();

                try {

                    $res = $caldav->deleteObject(
                        $row['href'],
                        $row['etag']
                    );

                    $this->logger->info('[hardDeleteTask] CalDAV delete response', [
                        'response' => $res
                    ]);

                    if (empty($res['success'])) {
                        $this->logger->error('[hardDeleteTask] CalDAV delete returned failure', [
                            'response' => $res
                        ]);
                        throw new \RuntimeException('Synology delete failed');
                    }

                } catch (\Throwable $e) {

                    $this->logger->error('[hardDeleteTask] CalDAV delete exception', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    throw $e; // runAndTrack에서 잡힘
                }
            } else {
                $this->logger->warning('[hardDeleteTask] href empty, skipping CalDAV delete', [
                    'uid' => $uid
                ]);
            }

            /* =====================================================
            * 3️⃣ DB 완전 삭제
            * ===================================================== */
            $stmt = $this->pdo->prepare("
                DELETE FROM dashboard_calendar_tasks
                WHERE uid = :uid
            ");

            $stmt->execute([':uid' => $uid]);

            $affected = $stmt->rowCount();

            $this->logger->info('[hardDeleteTask] DB delete executed', [
                'uid' => $uid,
                'affected_rows' => $affected
            ]);

            /* =====================================================
            * 4️⃣ 완료
            * ===================================================== */
            $this->logger->info('[hardDeleteTask] SUCCESS', [
                'uid' => $uid
            ]);

            // 🔥 현재 ERP 사용자 + 현재 Synology 로그인 계정 식별
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            // 🔥 해당 Synology 계정 기준으로만 Task 재조회
            $tasks = (new QueryService($this->pdo))
                ->getAllTasksMapped($userId, $synologyLoginId);

            return [
                'success' => true,
                'data' => [
                    'uid'     => $uid,
                    'deleted' => 'hard',
                    'tasks'   => $tasks
                ]
            ];

        }, '[hardDeleteTask]');
    }


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /* =========================================================
    * 🔧 Helpers: href normalize + id
    * ========================================================= */
    private function normalizeCollectionHref(string $href): string
    {
        $href = trim($href);
        if ($href === '') return '';
        return rtrim($href, '/') . '/';
    }
    private function normalizeObjectHref(string $href): string
    {
        return trim($href); // 절대 '/' 붙이지 말 것
    }   

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Collection href → calendar_id 생성
     * ⚠️ object(.ics) 절대 전달 금지
     */
    private function hrefToId(string $collectionHref): string
    {
        $n = $this->normalizeCollectionHref($collectionHref);
        return md5($n);
    }
    

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////




        public function hardDeleteEvent(array $payload): array
        {
            // 🔥 uid 중첩 방어
            if (isset($payload['uid']) && is_array($payload['uid'])) {
                $payload = $payload['uid'];
            }

            return $this->runAndTrack(function () use ($payload) {
        
                $uid = $payload['uid']
                    ?? throw new \RuntimeException('uid required');
                
                $uid = preg_replace('/^(event_)/', '', $uid);
                $uid = trim($uid);
                // 1️⃣ DB에서 이벤트 조회
                $stmt = $this->pdo->prepare("
                    SELECT * FROM dashboard_calendar_events
                    WHERE uid = :uid
                    AND synology_login_id = :synology_login_id
                    LIMIT 1
                ");
                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

                $stmt->execute([
                    ':uid' => $uid,
                    ':synology_login_id' => $synologyLoginId
                ]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$row) {
                    throw new \RuntimeException('event not found');
                }
                
                if ($row['synology_login_id'] !== $synologyLoginId) {
                    throw new \RuntimeException('Synology account mismatch');
                }
        
                // 2️⃣ Synology CalDAV 완전 삭제
                $caldav = $this->caldav();
                $caldav->deleteObject($row['href'], $row['etag']);
        
                // 3️⃣ ERP DB 완전 삭제
                $stmt = $this->pdo->prepare("
                    DELETE FROM dashboard_calendar_events
                    WHERE uid = :uid
                    AND synology_login_id = :synology_login_id
                ");

                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

                $stmt->execute([
                    ':uid' => $uid,
                    ':synology_login_id' => $synologyLoginId
                ]);
        
                return [
                    'data' => [
                        'uid'     => $uid,
                        'deleted' => 'hard'
                    ]
                ];
            }, '[hardDeleteEvent]');
        }
        

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



        public function getEventByUid(string $uid): array
        {
            try {
                $caldav = $this->caldav();
                $data   = $caldav->getEventByUid($uid);
        
                if (!$data) {
                    return ['success' => false, 'message' => 'event not found'];
                }
        
                // 🔥 collection href에서 calendar_id 복원
                $collectionHref =
                    $data['__meta']['collection_href']
                    ?? $data['collection_href']
                    ?? null;
        
                if ($collectionHref) {
                    $collectionHref = $this->normalizeCollectionHref($collectionHref);
                    $calendarId     = $this->hrefToId($collectionHref);
        
                    $data['calendar_id'] = $calendarId;
        
                    if (!isset($data['__meta']) || !is_array($data['__meta'])) {
                        $data['__meta'] = [];
                    }
        
                    $data['__meta']['calendar_id'] = $calendarId;
                }
        
                return [
                    'success' => true,
                    'data'    => $data,
                ];
        
            } catch (\Throwable $e) {
                $this->logger->error('[getEventByUid]', ['error' => $e->getMessage()]);
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }
        
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



            
        public function getTaskByUid(
            string $uid,
            ?string $collectionHref = null,
            array $extra = []
        ): array
        {
            try {

                $href = null;

                // 1️⃣ force_href 최우선
                if (!empty($extra['force_href'])) {
                    $href = (string)$extra['force_href'];
                }

                // 2️⃣ DB href 조회
                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

                $stmt = $this->pdo->prepare("
                    SELECT href
                    FROM dashboard_calendar_tasks
                    WHERE uid = :uid
                    AND synology_login_id = :synology_login_id
                    LIMIT 1
                ");

                $stmt->execute([
                    ':uid' => $uid,
                    ':synology_login_id' => $synologyLoginId
                ]);

                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$href && $row && !empty($row['href'])) {
                    $href = (string)$row['href'];
                }

                $caldav = $this->caldav();

                // 3️⃣ 마지막 fallback: collection 에서 UID로 실제 href 찾기
                if (!$href) {

                    if (!$collectionHref) {
                        return [
                            'success' => false,
                            'message' => 'task href missing (no collection_href provided)'
                        ];
                    }

                    $collectionHref = $this->normalizeCollectionHref($collectionHref);

                    $realHref = $this->resolveTaskObjectHrefByUid(
                        $caldav,
                        $collectionHref,
                        $uid
                    );

                    if (!$realHref) {
                        return [
                            'success' => false,
                            'message' => 'task not found on remote collection'
                        ];
                    }

                    $href = $realHref;

                    $fix = $this->pdo->prepare("
                        UPDATE dashboard_calendar_tasks
                        SET href = :href
                        WHERE uid = :uid
                        LIMIT 1
                    ");
                    $fix->execute([
                        ':href' => $href,
                        ':uid'  => $uid
                    ]);
                }

                // 3) href로 단건 GET
                $data = $caldav->getTaskByHref($href);

                // ✅ 단건 GET 파서가 VALARM을 놓칠 수 있음 → alarms 비면 컬렉션(getTodos)로 보정
                if (is_array($data)) {
                    $alarms = $data['alarms'] ?? null;
                    $hasAlarmArray = is_array($alarms) && count($alarms) > 0;

                    if (!$hasAlarmArray) {
                        // collectionHref 없으면 href 기준으로 복원
                        $fallbackCollection = $collectionHref
                            ? $this->normalizeCollectionHref($collectionHref)
                            : $this->normalizeCollectionHref(dirname($href));

                        try {
                            $rows = $caldav->getTodos($fallbackCollection, null, null);

                            if (is_array($rows)) {
                                foreach ($rows as $t) {
                                    $tUid =
                                        $t['uid'] ??
                                        ($t['raw']['uid'] ?? null) ??
                                        ($t['UID'] ?? null);

                                    if ((string)$tUid === (string)$uid) {
                                        // list 결과가 alarms까지 포함한 “완전한 task”
                                        $data = $t;

                                        // href/etag/meta는 단건 href 기준으로 확정
                                        $data['_href'] = $href;
                                        if (!isset($data['__meta']) || !is_array($data['__meta'])) {
                                            $data['__meta'] = [];
                                        }
                                        $data['__meta']['collection_href'] = $fallbackCollection;
                                        break;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            // fallback 실패해도 원래 data는 유지
                            $this->logger->warning('[getTaskByUid] alarm fallback failed', [
                                'uid' => $uid,
                                'href' => $href,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                if (is_array($data)) {
                    if (!isset($data['__meta']) || !is_array($data['__meta'])) {
                        $data['__meta'] = [];
                    }
                    $data['__meta']['collection_href'] = $this->normalizeCollectionHref(dirname($href));
                    $data['_href'] = $href;
                }

                return [
                    'success' => true,
                    'data'    => $data,
                ];

            } catch (\Throwable $e) {
                $this->logger->error('[getTaskByUid]', [
                    'error' => $e->getMessage()
                ]);

                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function processDueTime(string $dueRaw, bool $isAllDay): array
    {
        $rawLines = [];
        $payloadForDb = [];

        $dt = Time::parseLocal($dueRaw);

        if ($isAllDay) {

            $rawLines[] = 'DUE;VALUE=DATE:' .
                Time::toIcsDate($dueRaw);
        
            $payloadForDb['due'] =
                Time::parseLocal($dueRaw)->format('Y-m-d');
        
        } else {
        
            $rawLines[] = 'DUE;TZID=' . Time::TZID . ':' .
                Time::toIcsLocal($dueRaw);
        
            $payloadForDb['due'] =
                Time::toDbLocal($dueRaw);
        }

        return [
            'rawLines' => $rawLines,
            'payloadForDb' => $payloadForDb
        ];
    }






    // ✅ UID로 "진짜 object href(.ics)" 찾기 (Synology 원본 href 보정용)
    public  function resolveTaskObjectHrefByUid(CalDavClient $caldav, string $collectionHref, string $uid): ?string
    {
        $collectionHref = $this->normalizeCollectionHref($collectionHref);
        $rows = $caldav->getTodos($collectionHref, null, null);

        if (!is_array($rows)) return null;

        foreach ($rows as $t) {
            $tUid =
                $t['uid'] ??
                ($t['raw']['uid'] ?? null) ??
                ($t['UID'] ?? null);

            if (!$tUid) continue;
            if ((string)$tUid !== (string)$uid) continue;

            $href =
                $t['href'] ??
                ($t['__meta']['href'] ?? null);

            if (is_string($href) && $href !== '') {
                return $href;
            }
        }

        return null;
    }


    private function assertCalendarWritePermission(string $calendarId): void
    {
        [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

        if (!$userId) {
            throw new \RuntimeException('Invalid session');
        }

        $stmt = $this->pdo->prepare("
            SELECT id, is_personal, owner_user_id
            FROM dashboard_calendar_list
            WHERE id = :id
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':id' => $calendarId]);
        $calendar = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$calendar) {
            throw new \RuntimeException('Calendar not found');
        }

        // 개인 캘린더
        if ((int)$calendar['is_personal'] === 1) {

            if ($calendar['owner_user_id'] !== $userId) {
                throw new \RuntimeException('Permission denied (personal)');
            }

            return;
        }

        // 부서/공유 캘린더는 현재 전체 쓰기 허용
        return;
    }

    private function resolveSyncIdentity(): array
    {
        $userId = $_SESSION['user']['id'] ?? null;
    
        if (!$userId) {
            throw new \RuntimeException('Invalid session');
        }
    
        $synologyLoginId = $this->sync()->resolveSynologyLoginId($userId);
    
        return [$userId, $synologyLoginId];
    }


}
