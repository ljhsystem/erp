<?php
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
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;

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

    private function createCalDavClient(): CalDavClient
    {
        [$userId, $synologyLoginId] = $this->resolveSyncIdentity();
        if (!$userId) {
            throw new \RuntimeException('Invalid user session');
        }

        $host = rtrim((string)$this->systemConfig->get('synology_host'), '/');
        $path = trim((string)$this->systemConfig->get('synology_caldav_path'), '/');

        if ($host === '' || $path === '') {
            throw new \RuntimeException('Synology CalDAV not configured');
        }

        $baseUrl = $host . '/' . $path;

        $account = $this->externalAccount->getByUserAndService($userId, 'synology');

        if (
            !$account ||
            empty($account['external_login_id']) ||
            empty($account['external_password'])
        ) {
            throw new \RuntimeException('Synology account not registered');
        }

        $p = parse_url($baseUrl);
        $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');

        return new CalDavClient([
            'base_url' => rtrim($baseUrl, '/'),
            'origin'   => $origin,
            'username' => $account['external_login_id'],
            'password' => $account['external_password'],
        ]);
    }

    public function fetchRemoteCalendars(): array
    {
        try {
            $caldav = $this->caldav();
            $home   = $caldav->getCalendarHomeSetFromRoot();

            if (!$home) {
                throw new \RuntimeException('calendar-home-set not found');
            }

            $data = $caldav->listCalendarsFromHome($home);
            $data = is_array($data) ? $data : [];

            foreach ($data as &$c) {
                $href = $this->normalizeCollectionHref((string)($c['href'] ?? ''));
                $c['href'] = $href;

                $id = $this->hrefToId($href);
                $c['id'] = $id;
                $c['calendar_id'] = $id;

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

    public function deleteCollection(string $collectionHref): array
    {
        return $this->runAndTrack(function () use ($collectionHref) {
            $caldav = $this->caldav();
            $caldav->deleteCollection($collectionHref);

            return ['message' => 'collection deleted'];
        }, '[deleteCollection]');
    }

    public function createEvent(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {

            $caldav = $this->caldav();

            $collectionHref = $payload['collection_href']
                ?? throw new \RuntimeException('collection_href required');

            $collectionHref = $this->normalizeCollectionHref($collectionHref);


            $calendarId = $this->hrefToId($collectionHref);

            $this->assertCalendarWritePermission($calendarId);

            $stmt = $this->pdo->prepare("
                    SELECT id
                    FROM main_calendar_list
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

            $calendarId = $this->hrefToId($collectionHref);

            $this->assertCalendarWritePermission($calendarId);

            $stmt = $this->pdo->prepare("
                    SELECT id
                    FROM main_calendar_list
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

            if (!empty($payload['id'])) {
                throw new \RuntimeException('createEvent called with id');
            }

            $id = gmdate('Ymd\THis')
                . '-' . bin2hex(random_bytes(6))
                . '@cal.synology.com';

            $href = $collectionHref . rawurlencode($id) . '.ics';

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

            if (!empty($payload['location'])) {
                $rawLines[] = 'LOCATION:' . $this->ics->escape($payload['location']);
            }

            if (array_key_exists('description', $payload)) {
                $rawLines[] = 'DESCRIPTION:' . $this->ics->escape($payload['description']);
            }

            $rawLines = array_merge($rawLines, $attendeeLines);
            $tzid = (string)($this->systemConfig->get('timezone') ?: 'Asia/Seoul');

            if (!empty($payload['status'])) {
                $rawLines[] = 'STATUS:' . strtoupper($payload['status']);
            }

            if (!empty($payload['priority'])) {
                $rawLines[] = 'PRIORITY:' . (int)$payload['priority'];
            }

            $transp = strtoupper($payload['transp'] ?? 'OPAQUE');
            $rawLines[] = 'TRANSP:' . $transp;

            if ($isAllDay) {

                if (empty($payload['start'])) {
                    throw new \RuntimeException('start required');
                }

                $baseStart = substr((string)$payload['start'], 0, 10);
                $baseEnd   = !empty($payload['end'])
                    ? substr((string)$payload['end'], 0, 10)
                    : $baseStart;

                $dtstartYmd = str_replace('-', '', $baseStart);
                $rawLines[] = 'DTSTART;VALUE=DATE:' . $dtstartYmd;

                $dtendYmd = Time::parseLocal($baseEnd)
                    ->modify('+1 day')
                    ->format('Ymd');

                $rawLines[] = 'DTEND;VALUE=DATE:' . $dtendYmd;
            } else {

                if ($startRaw === '') {
                    throw new \RuntimeException('start required');
                }

                $startLocal = Time::parseLocal($startRaw);

                $endLocal = $endRaw
                    ? Time::parseLocal($endRaw)
                    : (clone $startLocal)->modify('+1 hour');

                $rawLines[] = 'DTSTART;TZID=' . $tzid . ':' .
                    $startLocal->format('Ymd\THis');

                $rawLines[] = 'DTEND;TZID=' . $tzid . ':' .
                    $endLocal->format('Ymd\THis');
            }

            if (!empty($payload['rrule'])) {

                $rr = preg_replace('/^RRULE:/', '', (string)$payload['rrule']);

                if (str_contains($rr, 'FREQ=MONTHLY')) {

                    if (empty($payload['start'])) {
                        throw new \RuntimeException('MONTHLY requires start date');
                    }

                    $day = (int)substr($payload['start'], 8, 2);

                    $rr = preg_replace('/;?BYMONTHDAY=\d+/', '', $rr);

                    $rr .= ';BYMONTHDAY=' . $day;
                }

                $rawLines[] = 'RRULE:' . $rr;
            }

            if (!empty($payload['alarms']) && is_array($payload['alarms'])) {
                foreach ($payload['alarms'] as $a) {

                    if (is_array($a)) {
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

            $ics = $this->ics->buildIcs('VEVENT', [
                'id'       => $id,
                'title'     => $payload['title'] ?? '',
                'raw_lines' => $rawLines,
            ]);

            $caldav->createObject($href, $ics);

            $etag = null;

            $get = $caldav->request('GET', $href);
            $originIcs =
                is_array($get) && array_key_exists('body', $get)
                ? $get['body']
                : null;


            $headers = $get['headers'] ?? [];

            $etag = null;
            foreach (['ETag', 'etag'] as $k) {
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

                $dtstart = str_replace('-', '', $baseStart);
                $dtend = Time::parseLocal($baseEnd)
                    ->modify('+1 day')
                    ->format('Ymd');
            } else {

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
            unset($rawForStore['collection_href']);
            $rawForStore['collection_href'] = $collectionHref;

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }

            $syncResult = $this->sync()->syncOneEventByUid(
                $id,
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
                    'id'  => $id,
                    'etag' => $etagForReturn
                ],
                'event' => $eventRow
            ];
        }, '[createEvent]');
    }

    private function rebuildEvent(array $row, array $payload): array
    {
        $caldav = $this->caldav();

        $id  = $row['id'];
        $href = $row['href'];
        $etag = $row['etag'];

        if (!$href) {
            throw new \RuntimeException('rebuildEvent: href missing');
        }

        $title       = $payload['title']       ?? $row['title'];
        $description = $payload['description'] ?? $row['description'];
        $location    = $payload['location']    ?? $row['location'];
        $rrule       = $payload['rrule']       ?? null;

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

        $ics = $this->ics->buildIcs('VEVENT', [
            'id'       => $id,      // 🔥 기존 id 유지
            'title'     => $title,
            'raw_lines' => $rawLines,
        ]);

        $caldav->updateObject($href, $ics, $etag);

        [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

        if (!$userId) {
            throw new \RuntimeException('Invalid session');
        }

        $syncResult = $this->sync()->syncOneEventByUid(
            $id,
            $synologyLoginId,
            $userId,
            [
                'calendar_id'       => $row['calendar_id'],
                'admin_event_color' => $payload['admin_event_color'] ?? null
            ]
        );

        $eventRow = $syncResult['event'] ?? null;

        $etagForReturn =
            (is_array($eventRow) ? ($eventRow['etag'] ?? null) : null)
            ?? (is_array($eventRow) ? ($eventRow['extendedProps']['etag'] ?? null) : null)
            ?? (is_array($eventRow) ? ($eventRow['extendedProps']['raw']['_etag'] ?? null) : null)
            ?? ($syncResult['etag'] ?? null)
            ?? ($etag ?? null);

        if (is_string($etagForReturn)) {
            $etagForReturn = trim($etagForReturn);
            $etagForReturn = trim($etagForReturn, '"');
            $etagForReturn = trim($etagForReturn, '"');
        }

        return [
            'success' => true,
            'data' => [
                'id'  => $id,
                'etag' => $etagForReturn
            ],
            'event' => $eventRow
        ];
    }

    public function updateEvent(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {

            if (isset($payload['id']) && is_array($payload['id']) && isset($payload['id']['id'])) {
                $payload = $payload['id'];
            }

            $this->logger->debug('[UPDATE PAYLOAD]', $payload);

            $scope = $payload['scope'] ?? 'all';
            $recurrenceId = $payload['recurrence_id'] ?? null;

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();
            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }

            $id = $payload['id'] ?? null;
            if (!$id) {
                throw new \RuntimeException('id required');
            }

            $stmt = $this->pdo->prepare("
                SELECT *
                FROM main_calendar_events
                WHERE id = :id
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([
                ':id' => $id
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new \RuntimeException('event not found');
            }

            if ($row['synology_login_id'] !== $synologyLoginId) {
                throw new \RuntimeException('Synology account mismatch');
            }
            $this->assertCalendarWritePermission($row['calendar_id']);

            $scope = $payload['scope'] ?? null;
            $recurrenceId = $payload['recurrence_id'] ?? null;

            if ($scope === 'single') {
                if (!$recurrenceId) {
                    throw new \RuntimeException('recurrence_id required for scope=single');
                }

                $caldav = $this->caldav();
                $res = $caldav->request('GET', $row['href']);
                $originIcs = is_array($res) && array_key_exists('body', $res) ? $res['body'] : null;
                if (!$originIcs) {
                    throw new \RuntimeException('ICS not found on CalDAV');
                }

                $exdateLine = 'EXDATE;VALUE=DATE:' . preg_replace('/[^0-9]/', '', $recurrenceId);
                if (strpos($originIcs, 'EXDATE') === false) {
                    if (preg_match('/\r\nRRULE:.*\r\n/', $originIcs)) {
                        $originIcs = preg_replace('/(\r\nRRULE:.*\r\n)/', "$1{$exdateLine}\r\n", $originIcs, 1);
                    } else {
                        $originIcs = preg_replace('/(\r\nDTSTART[^\r\n]*\r\n)/', "$1{$exdateLine}\r\n", $originIcs, 1);
                    }
                } else {
                    $originIcs = preg_replace('/(\r\nEXDATE[^\r\n]*:\s*)([0-9,]+)/', '$1$2,' . preg_replace('/[^0-9]/', '', $recurrenceId), $originIcs, 1);
                }

                $id = $row['id'];

                $seq = (int)($this->ics->extractSequence($originIcs) ?? 0) + 1;

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

                $overrideIcs = $this->ics->buildIcs('VEVENT', [
                    'id'   => $id,
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

                if (strpos($originIcs, $overrideVeventBlock) === false) {
                    $originIcs = preg_replace('/END:VCALENDAR\r\n?/i', $overrideVeventBlock . "END:VCALENDAR\r\n", $originIcs, 1);
                }

                $caldav->updateObject($row['href'], $originIcs, $row['etag'] ?? null);

                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

                if (!$userId) {
                    throw new \RuntimeException('Invalid session');
                }

                return $this->sync()->syncOneEventByUid(
                    $id,
                    $synologyLoginId,
                    $userId,
                    [
                        'calendar_id' => $row['calendar_id'],
                        'admin_event_color' => $payload['admin_event_color'] ?? null
                    ]
                );
            } elseif ($scope === 'future') {
            } elseif ($scope === 'all') {
            }

            $wasAllDay = (int)$row['all_day'] === 1;

            $start = (string)($payload['start'] ?? '');
            $end   = (string)($payload['end']   ?? '');

            $payloadIsAllDay =
                array_key_exists('allDay', $payload)
                ? (bool)$payload['allDay']
                : $wasAllDay;

            if ($wasAllDay !== $payloadIsAllDay) {
                return $this->rebuildEvent($row, $payload);
            }

            $href = $row['href'] ?? null;

            if (!$href && !empty($payload['href'])) {
                $href = trim($payload['href']);
            }

            if (!$href || !str_ends_with($href, '.ics')) {
                throw new \RuntimeException('updateEvent resolved href missing');
            }

            $payload = array_merge([
                'allDay' => null,
                'etag'   => null,
                'rrule'  => null,
                'alarms' => [],
            ], $payload);

            $href = $row['href'] ?: ($payload['href'] ?? null);
            $etag = $row['etag'] ?? null;

            if (!$href) {
                throw new \RuntimeException('updateEvent resolved href missing');
            }

            if (empty($row['href']) && !empty($payload['href'])) {
                $this->logger->warning('[UPDATE] fixing missing href in DB', [
                    'id'  => $id,
                    'href' => $payload['href']
                ]);

                $fix = $this->pdo->prepare("
                    UPDATE main_calendar_events
                    SET href = :href
                    WHERE id = :id
                    LIMIT 1
                ");
                $fix->execute([
                    ':href' => $payload['href'],
                    ':id'  => $id
                ]);
            }

            $caldav = $this->caldav();
            $res = $caldav->request('GET', $href);
            $originIcs = is_array($res) && array_key_exists('body', $res)
                ? $res['body']
                : null;


            if (!$originIcs) {
                throw new \RuntimeException('ICS not found on CalDAV');
            }

            if (isset($payload['allday']) && !isset($payload['allDay'])) {
                $payload['allDay'] = $payload['allday'];
            }

            $tzid = $this->ics->extractTzid($originIcs);

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

            $rruleFromDb  = $this->ics->extractProperty($originIcs, 'RRULE');
            $rdateFromDb  = $this->ics->extractProperty($originIcs, 'RDATE');
            $exdateFromDb = $this->ics->extractProperty($originIcs, 'EXDATE');

            $rrule  = $rruleFromDb;
            $rdate  = $rdateFromDb;
            $exdate = $exdateFromDb;

            if (
                $rruleFromDb !== null &&
                array_key_exists('rrule', $payload) &&
                empty($payload['rrule'])
            ) {
                $this->logger->debug('[RRULE REMOVE]', ['id' => $id]);

                $rrule  = null;
                $rdate  = null;
                $exdate = null;
            }

            if (!empty($payload['rrule'])) {

                $rr = preg_replace('/^RRULE:/', '', (string)$payload['rrule']);

                if (str_contains($rr, 'FREQ=MONTHLY')) {

                    if (empty($payload['start'])) {
                        throw new \RuntimeException('MONTHLY requires start date');
                    }

                    $day = (int)substr($payload['start'], 8, 2);

                    $rr = preg_replace('/;?BYMONTHDAY=\d+/', '', $rr);

                    $rr .= ';BYMONTHDAY=' . $day;
                }

                $rrule = 'RRULE:' . $rr;
            }

            $isAllDay =
                !empty($payload['allDay']) ||
                !empty($payload['allday']);

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

                $startLocal = Time::parseLocal($startRaw);
                $endLocal   = Time::parseLocal($endRaw);

                $setLines[] = 'DTSTART;TZID=' . $tzid . ':' .
                    $startLocal->format('Ymd\THis');

                $setLines[] = 'DTEND;TZID=' . $tzid . ':' .
                    $endLocal->format('Ymd\THis');
            }

            $seq = (int)($this->ics->extractSequence($originIcs) ?? 0);

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

            $rruleRemoved =
                $rruleFromDb !== null &&
                array_key_exists('rrule', $payload) &&
                empty($payload['rrule']);

            $isUiAction = ($payload['__source'] ?? null) === 'ui';


            $explicitDateChange =
                array_key_exists('start', $payload) ||
                array_key_exists('end', $payload);

            if (
                !$isUiAction &&
                $explicitDateChange &&
                !$rruleRemoved &&
                !$payloadIsAllDay &&
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

            $patchedIcs = preg_replace(
                '/BEGIN:VALARM[\s\S]*?END:VALARM\s*/i',
                '',
                $patchedIcs
            );

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

            $res = $caldav->updateObject($href, $patchedIcs, $etag);

            $get = $caldav->request('GET', $href);

            $serverIcs = $get['body'] ?? null;
            if (!$serverIcs) {
                throw new \RuntimeException('failed to fetch ICS after update');
            }

            $newEtag = $res['etag'] ?? null;

            if (!$newEtag) {
                $head = $caldav->request('HEAD', $href);

                if (is_array($head)) {
                    $headers = $head['headers'] ?? [];
                    foreach (['ETag', 'etag'] as $k) {
                        if (!empty($headers[$k][0])) {
                            $newEtag = trim($headers[$k][0], '"');
                            break;
                        }
                    }
                }
            }

            if (!$newEtag) {
                $newEtag = $etag;
            }

            $calendarId = $row['calendar_id'] ?? null;

            if (!$calendarId) {
                throw new \RuntimeException(
                    'updateEvent: calendar_id missing in DB for id: ' . $id
                );
            }

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }


            $syncResult = $this->sync()->syncOneEventByUid(
                $id,
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
                    'id'  => $id,
                    'etag' => $etagForReturn
                ],
                'event' => $eventRow
            ];
        }, '[updateEvent]');
    }

    public function deleteComponent(array $payload): array
    {
        if (isset($payload['id']) && is_array($payload['id'])) {
            $payload = $payload['id'];
        }

        return $this->runAndTrack(function () use ($payload) {

            $id = $payload['id']
                ?? throw new \RuntimeException('id required');

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt = $this->pdo->prepare("
                SELECT *
                FROM main_calendar_events
                WHERE id = :id
                AND synology_login_id = :synology
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $id,
                ':synology' => $synologyLoginId
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new \RuntimeException('event not found');
            }

            $this->assertCalendarWritePermission($row['calendar_id']);

            if ((int)$row['is_active'] === 0) {
                return [
                    'success' => true,
                    'data' => [
                        'id' => $id,
                        'deleted' => 'already'
                    ]
                ];
            }

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
                    $id,
                    $synologyLoginId,
                    $userId
                );
            }

            $stmt = $this->pdo->prepare("
                UPDATE main_calendar_events
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :user
                WHERE id = :id
                AND synology_login_id = :synology
            ");

            $stmt->execute([
                ':id' => $id,
                ':synology' => $synologyLoginId,
                ':user' => $userId
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $id,
                    'deleted' => 'soft'
                ]
            ];
        }, '[deleteComponent]');
    }

    public function createTask(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {
            $this->logger->debug('[CREATE TASK] Received payload', $payload);

            $caldav = $this->caldav();

            $calendarId = $payload['calendar_id']
                ?? throw new \RuntimeException('calendar_id required');

            $stmt = $this->pdo->prepare("
                SELECT href
                FROM main_calendar_list
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

            $stmt = $this->pdo->prepare("SELECT id FROM main_calendar_list WHERE id = :id AND type = 'task' AND is_active = 1 LIMIT 1");
            $stmt->execute([':id' => $calendarId]);
            if (!$stmt->fetch()) {
                throw new \RuntimeException('task calendar not registered or inactive');
            }

            $this->logger->debug('[CREATE TASK] Task calendar is active');

            $id  = bin2hex(random_bytes(16));
            $href = $collectionHref . $id . '.ics';
            $this->logger->debug('[CREATE TASK] Generated id: ' . $id);
            $this->logger->debug('[CREATE TASK] Generated href: ' . $href);

            $etag = null;

            $rawLines = [];
            if (!empty($payload['description'])) {
                $rawLines[] = 'DESCRIPTION:' . $this->ics->escape($payload['description']);
                $this->logger->debug('[CREATE TASK] Added DESCRIPTION');
            }

            if (!empty($payload['due'])) {
                $this->logger->debug('[CREATE TASK] Processing DUE');
                $dueRaw = (string)$payload['due'];

                $dueRaw = (string)$payload['due'];

                $dueIsDateOnly =
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueRaw) ||
                    preg_match('/^\d{8}$/', $dueRaw);

                $isAllDay =
                    $dueIsDateOnly ||
                    !empty($payload['allDay']);

                $dueData = $this->processDueTime($dueRaw, $isAllDay);

                $rawLines = array_merge($rawLines, $dueData['rawLines'] ?? []);
                $payloadForDb = $dueData['payloadForDb'];
                $this->logger->debug('[CREATE TASK] Processed DUE: ' . json_encode($dueData));
            }

            $status = strtoupper($payload['status'] ?? 'NEEDS-ACTION');
            $rawLines[] = 'STATUS:' . $status;

            $percent = isset($payload['percent'])
                ? max(0, min(100, (int)$payload['percent']))
                : ($status === 'COMPLETED' ? 100 : 0);

            $rawLines[] = 'PERCENT-COMPLETE:' . $percent;

            if (isset($payload['priority']) && $payload['priority'] !== '') {
                $rawLines[] = 'PRIORITY:' . (int)$payload['priority'];
            }

            $this->logger->debug('[CREATE TASK] Added status, percent, and priority');

            if (!empty($payload['alarms'])) {
                $alarmBlock = '';

                foreach ($payload['alarms'] as $a) {
                    $trigger = $a['trigger'] ?? null;
                    if (!$trigger) continue;

                    $alarmBlock .= "BEGIN:VALARM\r\n" . "ACTION:DISPLAY\r\n" . "DESCRIPTION:Reminder\r\n" . "TRIGGER:" . $trigger . "\r\n" . "END:VALARM\r\n";
                }

                $ics = $this->ics->buildIcs('VTODO', [
                    'id'       => $id,
                    'title'     => $payload['title'] ?? '',
                    'raw_lines' => array_merge($rawLines, [$alarmBlock]),
                ]);
                $this->logger->debug('[CREATE TASK] Generated ICS content with alarms');
            } else {
                $ics = $this->ics->buildIcs('VTODO', [
                    'id'       => $id,
                    'title'     => $payload['title'] ?? '',
                    'raw_lines' => $rawLines,
                ]);
                $this->logger->debug('[CREATE TASK] Generated ICS content without alarms');
            }

            try {
                $res = $caldav->createObject($href, $ics);
                $etag = $res['etag'] ?? null;
                $this->logger->debug('[CREATE TASK] CalDAV task PUT successful');
            } catch (\Throwable $e) {
                $this->logger->error('[CREATE TASK] CalDAV task PUT failed', ['error' => $e->getMessage()]);
                throw new \RuntimeException('CalDAV task PUT failed');
            }

            $get = $caldav->request('GET', $href);
            $originIcs = $get['body'] ?? null;

            if (!$originIcs) {
                throw new \RuntimeException('ICS not returned after create');
            }

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }

            $this->sync()->syncOneTaskByUid(
                $id,
                $synologyLoginId,
                $userId,
                [
                    'calendar_id'     => $calendarId,
                    'collection_href' => $collectionHref,
                    'force_href'      => $href
                ]
            );

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $tasks = (new QueryService($this->pdo))
                ->getAllTasksMapped($userId, $synologyLoginId);

            return [
                'success' => true,
                'data' => [
                    'id'   => $id,
                    'tasks' => $tasks
                ]
            ];
        }, '[createTask]');
    }

    public function updateTask(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {

            $id = $payload['id'] ?? throw new \RuntimeException('id required');
            $this->logger->debug('[UPDATE TASK] Received id: ' . $id);

            $calendarId = $payload['calendar_id'] ?? null;

            if (!$calendarId) {
                throw new \RuntimeException('calendar_id required');
            }

            $this->logger->debug('[UPDATE TASK] Calendar ID: ' . $calendarId);

            $stmt = $this->pdo->prepare("
                SELECT * FROM main_calendar_tasks
                WHERE id = :id
                AND synology_login_id = :synology_login_id
                AND is_active = 1
                LIMIT 1
            ");

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':id' => $id,
                ':synology_login_id' => $synologyLoginId
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {

                $this->logger->debug('[UPDATE TASK] Task not found, syncing...');

                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

                $sync = new SyncService($this->pdo);
                $sync->syncOneTaskByUid(
                    $id,
                    $synologyLoginId,
                    $userId
                );

                $stmt->execute([
                    ':id' => $id,
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

            if (!$href || !str_ends_with($href, '.ics')) {

                if (!$collectionHref) {
                    throw new \RuntimeException('task href missing/invalid');
                }

                $realHref = $this->resolveTaskObjectHrefByUid(
                    $caldav,
                    $collectionHref,
                    $id
                );

                if (!$realHref) {
                    throw new \RuntimeException('task href missing/invalid');
                }

                $href = $realHref;

                $fix = $this->pdo->prepare("
                    UPDATE main_calendar_tasks
                    SET href = :href
                    WHERE id = :id
                    LIMIT 1
                ");
                $fix->execute([
                    ':href' => $href,
                    ':id'  => $id
                ]);
            }

            $this->logger->debug('[UPDATE TASK] Task href: ' . $href);
            $this->logger->debug('[UPDATE TASK] Task etag: ' . $etag);

            $res = $caldav->request('GET', $href);
            $originIcs = $res['body'] ?? null;

            if (!$originIcs) {

                $this->logger->warning('[UPDATE TASK] ICS not found, trying sync refresh', [
                    'id'  => $id,
                    'href' => $href,
                    'calendar_id' => $calendarId,
                ]);

                $sync = new SyncService($this->pdo);

                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

                if (!$userId) {
                    throw new \RuntimeException('Invalid session');
                }

                $sync->syncOneTaskByUid(
                    $id,
                    $synologyLoginId,
                    $userId,
                    [
                        'calendar_id' => $calendarId,
                        'collection_href' => $collectionHref ?? (dirname($href) . '/')
                    ]
                );

                $stmt2 = $this->pdo->prepare("
                    SELECT * FROM main_calendar_tasks
                    WHERE id = :id
                    AND synology_login_id = :synology_login_id
                    AND is_active = 1
                    LIMIT 1
                ");

                $stmt2->execute([
                    ':id' => $id,
                    ':synology_login_id' => $synologyLoginId
                ]);
                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

                if ($row2 && !empty($row2['href'])) {
                    $href = (string)$row2['href'];
                    $etag = $row2['etag'] ?? $etag;
                }

                $res = $caldav->request('GET', $href);
                $originIcs = $res['body'] ?? null;

                if (!$originIcs) {
                    $this->logger->error('[UPDATE TASK] ICS still not found after sync', [
                        'id'  => $id,
                        'href' => $href
                    ]);
                    throw new \RuntimeException('ICS not found');
                }
            }

            $this->logger->debug('[UPDATE TASK] Loaded ICS content');

            $latestEtag = null;
            $headers = $res['headers'] ?? [];
            foreach (['ETag', 'etag'] as $k) {
                if (!empty($headers[$k][0])) {
                    $latestEtag = trim((string)$headers[$k][0]);
                    $latestEtag = trim($latestEtag, '"');
                    break;
                }
            }

            if ($latestEtag) {
                $etag = $latestEtag;
            }

            $this->logger->debug('[UPDATE TASK] ETag updated: ' . $etag);

            $seq = (int)($this->ics->extractSequence($originIcs) ?? 0);

            $setLines = [
                'SEQUENCE:' . ($seq + 1),

                'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            ];

            if (array_key_exists('title', $payload)) {
                $setLines[] = 'SUMMARY:' . $this->ics->escape((string)$payload['title']);
            }

            if (array_key_exists('description', $payload)) {
                $setLines[] = 'DESCRIPTION:' . $this->ics->escape((string)$payload['description']);
            }

            $payloadForDb = [];
            if (!empty($payload['due'])) {
                $dueRaw = (string)$payload['due'];

                $dueIsDateOnly = (bool)preg_match('/^\d{8}$/', $dueRaw);

                $isAllDay =
                    $dueIsDateOnly ||
                    (!empty($payload['allDay']) && $payload['allDay'] === true);

                $dueData = $this->processDueTime($dueRaw, $isAllDay);
                $payloadForDb = $dueData['payloadForDb'] ?? [];

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

            if (array_key_exists('status', $payload)) {
                $status = strtoupper((string)$payload['status']);
                if ($status === 'COMPLETED') {
                    $setLines[] = 'STATUS:COMPLETED';
                    $setLines[] = 'PERCENT-COMPLETE:100';
                    $setLines[] = 'COMPLETED:' . gmdate('Ymd\THis\Z');
                } else {
                    $setLines[] = 'STATUS:NEEDS-ACTION';
                    $setLines[] = 'PERCENT-COMPLETE:0';

                    $setLines[] = 'COMPLETED:';
                }
            }

            if (array_key_exists('percent', $payload)) {
                $p = max(0, min(100, (int)$payload['percent']));
                $setLines[] = 'PERCENT-COMPLETE:' . $p;
            }

            if (array_key_exists('priority', $payload) && $payload['priority'] !== null && $payload['priority'] !== '') {
                $setLines[] = 'PRIORITY:' . (int)$payload['priority'];
            }

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

            if (array_key_exists('alarms', $payload)) {

                $patchedIcs = preg_replace(
                    '/BEGIN:VALARM[\s\S]*?END:VALARM\r?\n?/i',
                    '',
                    $patchedIcs
                );

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

            $ifMatch = $etag ? ('"' . trim($etag, '"') . '"') : null;

            $put = $caldav->updateObject($href, $patchedIcs, $ifMatch);
            $this->logger->debug('[UPDATE TASK] PUT request sent to CalDAV');

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

            if (!is_array($put) || (isset($put['success']) && $put['success'] === false)) {
                $this->logger->error('[TASK PUT FAILED]', ['id' => $id, 'href' => $href, 'etag_used' => $etag, 'response' => $put]);
                throw new \RuntimeException('CalDAV PUT failed');
            }

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

            if ($newEtag && $newEtag !== $etag) {
                $fix = $this->pdo->prepare("UPDATE main_calendar_tasks SET etag = :etag WHERE id = :id AND calendar_id = :calendar_id LIMIT 1");
                $fix->execute([':etag' => $newEtag, ':id' => $id, ':calendar_id' => $calendarId]);
                $this->logger->debug('[UPDATE TASK] ETag updated in DB');
            }

            $collectionHref = dirname($href) . '/';

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }

            $syncResult = $this->sync()->syncOneTaskByUid(
                $id,
                $synologyLoginId,
                $userId,
                [
                    'calendar_id'    => $calendarId,
                    'collection_href' => $collectionHref
                ]
            );

            $taskRow = $syncResult['task'] ?? null;

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $tasks = (new QueryService($this->pdo))
                ->getAllTasksMapped($userId, $synologyLoginId);

            return [
                'success' => true,
                'data' => [
                    'id'   => $id,
                    'etag'  => $taskRow['etag'] ?? null,
                    'tasks' => $tasks
                ]
            ];
        }, '[updateTask]');
    }
    public function updateTaskComplete(string $id, string $calendarId, bool $completed): array
    {
        return $this->runAndTrack(function () use ($id, $calendarId, $completed) {

            $this->logger->debug('[UPDATE TASK COMPLETE] Received id: ' . $id);

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt = $this->pdo->prepare("
                SELECT * FROM main_calendar_tasks
                WHERE id = :id
                AND calendar_id = :calendar_id
                AND synology_login_id = :synology_login_id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $id,
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

            $caldav = $this->caldav();
            $res = $caldav->request('GET', $href);
            $originIcs = $res['body'] ?? null;

            if (!$originIcs) {

                $this->logger->warning('[UPDATE TASK] ICS not found, trying sync refresh', [
                    'id'  => $id,
                    'href' => $href,
                    'calendar_id' => $calendarId,
                ]);

                $sync = new SyncService($this->pdo);

                [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

                if (!$userId) {
                    throw new \RuntimeException('Invalid session');
                }

                $sync->syncOneTaskByUid(
                    $id,
                    $synologyLoginId,
                    $userId,
                    [
                        'calendar_id'     => $calendarId,
                        'collection_href' => (dirname($href) . '/')
                    ]
                );

                $stmt2 = $this->pdo->prepare("
                    SELECT * FROM main_calendar_tasks
                    WHERE id = :id
                    AND is_active = 1
                    LIMIT 1
                ");
                $stmt2->execute([':id' => $id]);
                $row2 = $stmt2->fetch(\PDO::FETCH_ASSOC);

                if ($row2 && !empty($row2['href'])) {
                    $href = (string)$row2['href'];
                    $etag = $row2['etag'] ?? $etag;
                }

                $res = $caldav->request('GET', $href);
                $originIcs = $res['body'] ?? null;

                if (!$originIcs) {
                    $this->logger->error('[UPDATE TASK] ICS still not found after sync', [
                        'id'  => $id,
                        'href' => $href
                    ]);
                    throw new \RuntimeException('ICS not found');
                }
            }

            $this->logger->debug('[UPDATE TASK COMPLETE] Loaded ICS content');

            $latestEtag = null;
            $headers = $res['headers'] ?? [];
            foreach (['ETag', 'etag'] as $k) {
                if (!empty($headers[$k][0])) {
                    $latestEtag = trim((string)$headers[$k][0]);
                    $latestEtag = trim($latestEtag, '"');
                    break;
                }
            }

            if ($latestEtag) {
                $etag = $latestEtag;
            }

            $this->logger->debug('[UPDATE TASK COMPLETE] ETag updated: ' . $etag);

            $setLines = [];

            $seq = (int)($this->ics->extractSequence($originIcs) ?? 0);
            $setLines[] = 'SEQUENCE:' . ($seq + 1);
            $this->logger->debug('[UPDATE TASK COMPLETE] Sequence incremented to: ' . ($seq + 1));

            if ($completed) {

                $setLines[] = 'PERCENT-COMPLETE:100';
                $setLines[] = 'STATUS:COMPLETED';
                $setLines[] = 'COMPLETED:' . gmdate('Ymd\THis\Z');
                $this->logger->debug('[UPDATE TASK COMPLETE] Task marked as COMPLETED');
            } else {

                $setLines[] = 'PERCENT-COMPLETE:0';
                $setLines[] = 'STATUS:NEEDS-ACTION';
                $setLines[] = 'COMPLETED:';
                $this->logger->debug('[UPDATE TASK COMPLETE] Task marked as NEEDS-ACTION');
            }

            $setLines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $this->logger->debug('[UPDATE TASK COMPLETE] DTSTAMP updated');

            $updatedIcs = $this->ics->patchComponent($originIcs, 'VTODO', $setLines, ['STATUS', 'PERCENT-COMPLETE', 'DTSTAMP', 'COMPLETED']);
            $this->logger->debug('[UPDATE TASK COMPLETE] ICS patched with updated status');

            $ifMatch = $etag ? ('"' . trim($etag, '"') . '"') : null;

            $put = $caldav->updateObject($href, $updatedIcs, $ifMatch);
            $this->logger->debug('[UPDATE TASK COMPLETE] PUT request sent to CalDAV');

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

            if (!is_array($put) || (isset($put['success']) && $put['success'] === false)) {
                $this->logger->error('[TASK PUT FAILED]', ['id' => $id, 'href' => $href, 'etag_used' => $etag, 'response' => $put]);
                throw new \RuntimeException('CalDAV PUT failed');
            }

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

            if ($newEtag && $newEtag !== $etag) {
                $fix = $this->pdo->prepare("UPDATE main_calendar_tasks SET etag = :etag WHERE id = :id AND calendar_id = :calendar_id LIMIT 1");
                $fix->execute([':etag' => $newEtag, ':id' => $id, ':calendar_id' => $calendarId]);
                $this->logger->debug('[UPDATE TASK COMPLETE] ETag updated in DB');
            }

            $collectionHref = dirname($href) . '/';

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            if (!$userId) {
                throw new \RuntimeException('Invalid session');
            }

            return $this->sync()->syncOneTaskByUid(
                $id,
                $synologyLoginId,
                $userId,
                [
                    'calendar_id'    => $calendarId,
                    'collection_href' => $collectionHref
                ]
            );
        }, '[updateTaskComplete]');
    }

    public function toggleTaskComplete(string $id, string $calendarId, bool $completed)
    {
        $id = preg_replace('/^task_/', '', $id);

        if ($completed) {
            $status = 'COMPLETED';
            $percent = 100;
        } else {
            $status = 'NEEDS-ACTION';
            $percent = 0;
        }

        try {
            return $this->updateTaskComplete($id, $calendarId, $completed);
        } catch (\RuntimeException $e) {
            $this->logger->error('[TOGGLE TASK COMPLETE] Failed to update task: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteTask(array $payload): array
    {

        if (isset($payload['id']) && is_array($payload['id'])) {
            $payload = $payload['id'];
        }

        return $this->runAndTrack(function () use ($payload) {

            $id = $payload['id']
                ?? throw new \RuntimeException('id required');

            $stmt = $this->pdo->prepare("
                    SELECT * FROM main_calendar_tasks
                    WHERE id = :id
                    AND synology_login_id = :synology_login_id
                    LIMIT 1
            ");
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':id' => $id,
                ':synology_login_id' => $synologyLoginId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new \RuntimeException('task not found or already deleted');
            }

            $this->assertCalendarWritePermission($row['calendar_id']);

            $stmt = $this->pdo->prepare("
                UPDATE main_calendar_tasks
                SET is_active = 0,
                    deleted_at = NOW(),
                    deleted_by = :user
                WHERE id = :id
            ");
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':id'  => $id,
                ':user' => $userId
            ]);

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $tasks = (new QueryService($this->pdo))
                ->getAllTasksMapped($userId, $synologyLoginId);

            return [
                'success' => true,
                'data' => [
                    'id'     => $id,
                    'deleted' => 'soft',
                    'tasks'   => $tasks
                ]
            ];
        }, '[deleteTask]');
    }

    public function hardDeleteTask(array $payload): array
    {
        return $this->runAndTrack(function () use ($payload) {



            $id = $payload['id']
                ?? throw new \RuntimeException('id required');

            // 🔥 서버에서 최종 정규화
            $id = preg_replace('/^(task_|event_)/', '', $id);
            $id = trim($id);

            if ($id === '') {
                throw new \RuntimeException('id empty');
            }

            $this->logger->info('[hardDeleteTask] START', [
                'payload' => $payload
            ]);

            $id = $payload['id']
                ?? throw new \RuntimeException('id required');

            $id = preg_replace('/^task_/', '', $id);

            $this->logger->info('[hardDeleteTask] id normalized', [
                'id' => $id
            ]);

            $stmt = $this->pdo->prepare("
                    SELECT * FROM main_calendar_tasks
                    WHERE id = :id
                    AND synology_login_id = :synology_login_id
                    LIMIT 1
            ");

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':id' => $id,
                ':synology_login_id' => $synologyLoginId
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logger->info('[hardDeleteTask] DB fetch result', [
                'found' => (bool)$row,
                'row'   => $row
            ]);

            if (!$row) {
                $this->logger->warning('[hardDeleteTask] Task not found in DB', [
                    'id' => $id
                ]);

                return ['deleted' => 'already-removed'];
            }

            $this->assertCalendarWritePermission($row['calendar_id']);

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
                    'id' => $id
                ]);
            }

            $stmt = $this->pdo->prepare("
                DELETE FROM main_calendar_tasks
                WHERE id = :id
            ");

            $stmt->execute([':id' => $id]);

            $affected = $stmt->rowCount();

            $this->logger->info('[hardDeleteTask] DB delete executed', [
                'id' => $id,
                'affected_rows' => $affected
            ]);

            $this->logger->info('[hardDeleteTask] SUCCESS', [
                'id' => $id
            ]);

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $tasks = (new QueryService($this->pdo))
                ->getAllTasksMapped($userId, $synologyLoginId);

            return [
                'success' => true,
                'data' => [
                    'id'     => $id,
                    'deleted' => 'hard',
                    'tasks'   => $tasks
                ]
            ];
        }, '[hardDeleteTask]');
    }

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

    private function hrefToId(string $collectionHref): string
    {
        $n = $this->normalizeCollectionHref($collectionHref);
        return md5($n);
    }

    public function hardDeleteEvent(array $payload): array
    {

        if (isset($payload['id']) && is_array($payload['id'])) {
            $payload = $payload['id'];
        }

        return $this->runAndTrack(function () use ($payload) {

            $id = $payload['id']
                ?? throw new \RuntimeException('id required');

            $id = preg_replace('/^(event_)/', '', $id);
            $id = trim($id);

            $stmt = $this->pdo->prepare("
                    SELECT * FROM main_calendar_events
                    WHERE id = :id
                    AND synology_login_id = :synology_login_id
                    LIMIT 1
                ");
            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':id' => $id,
                ':synology_login_id' => $synologyLoginId
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                throw new \RuntimeException('event not found');
            }

            if ($row['synology_login_id'] !== $synologyLoginId) {
                throw new \RuntimeException('Synology account mismatch');
            }

            $caldav = $this->caldav();
            $caldav->deleteObject($row['href'], $row['etag']);

            $stmt = $this->pdo->prepare("
                    DELETE FROM main_calendar_events
                    WHERE id = :id
                    AND synology_login_id = :synology_login_id
                ");

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt->execute([
                ':id' => $id,
                ':synology_login_id' => $synologyLoginId
            ]);

            return [
                'data' => [
                    'id'     => $id,
                    'deleted' => 'hard'
                ]
            ];
        }, '[hardDeleteEvent]');
    }


    public function getEventByUid(string $id): array
    {
        try {
            $caldav = $this->caldav();
            $data   = $caldav->getEventByUid($id);

            if (!$data) {
                return ['success' => false, 'message' => 'event not found'];
            }

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

    public function getTaskByUid(
        string $id,
        ?string $collectionHref = null,
        array $extra = []
    ): array {
        try {

            $href = null;

            if (!empty($extra['force_href'])) {
                $href = (string)$extra['force_href'];
            }

            [$userId, $synologyLoginId] = $this->resolveSyncIdentity();

            $stmt = $this->pdo->prepare("
                    SELECT href
                    FROM main_calendar_tasks
                    WHERE id = :id
                    AND synology_login_id = :synology_login_id
                    LIMIT 1
                ");

            $stmt->execute([
                ':id' => $id,
                ':synology_login_id' => $synologyLoginId
            ]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$href && $row && !empty($row['href'])) {
                $href = (string)$row['href'];
            }

            $caldav = $this->caldav();

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
                    $id
                );

                if (!$realHref) {
                    return [
                        'success' => false,
                        'message' => 'task not found on remote collection'
                    ];
                }

                $href = $realHref;

                $fix = $this->pdo->prepare("
                        UPDATE main_calendar_tasks
                        SET href = :href
                        WHERE id = :id
                        LIMIT 1
                    ");
                $fix->execute([
                    ':href' => $href,
                    ':id'  => $id
                ]);
            }

            $data = $caldav->getTaskByHref($href);

            if (is_array($data)) {
                $alarms = $data['alarms'] ?? null;
                $hasAlarmArray = is_array($alarms) && count($alarms) > 0;

                if (!$hasAlarmArray) {

                    $fallbackCollection = $collectionHref
                        ? $this->normalizeCollectionHref($collectionHref)
                        : $this->normalizeCollectionHref(dirname($href));

                    try {
                        $rows = $caldav->getTodos($fallbackCollection, null, null);

                        if (is_array($rows)) {
                            foreach ($rows as $t) {
                                $tUid =
                                    $t['id'] ??
                                    ($t['raw']['id'] ?? null) ??
                                    ($t['id'] ?? null);

                                if ((string)$tUid === (string)$id) {

                                    $data = $t;

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

                        $this->logger->warning('[getTaskByUid] alarm fallback failed', [
                            'id' => $id,
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

    public  function resolveTaskObjectHrefByUid(CalDavClient $caldav, string $collectionHref, string $id): ?string
    {
        $collectionHref = $this->normalizeCollectionHref($collectionHref);
        $rows = $caldav->getTodos($collectionHref, null, null);

        if (!is_array($rows)) return null;

        foreach ($rows as $t) {
            $tUid =
                $t['id'] ??
                ($t['raw']['id'] ?? null) ??
                ($t['id'] ?? null);

            if (!$tUid) continue;
            if ((string)$tUid !== (string)$id) continue;

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
            FROM main_calendar_list
            WHERE id = :id
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':id' => $calendarId]);
        $calendar = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$calendar) {
            throw new \RuntimeException('Calendar not found');
        }

        if ((int)$calendar['is_personal'] === 1) {

            if ($calendar['owner_user_id'] !== $userId) {
                throw new \RuntimeException('Permission denied (personal)');
            }

            return;
        }

        return;
    }

    private function resolveSyncIdentity(): array
    {
        $actor = ActorHelper::parse(ActorHelper::user());
        $userId = $actor['id'] ?? null;

        if (!$userId) {
            throw new \RuntimeException('Invalid session');
        }

        $synologyLoginId = $this->sync()->resolveSynologyLoginId($userId);

        return [$userId, $synologyLoginId];
    }
}
