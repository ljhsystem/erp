<?php
namespace App\Services\Calendar;

use PDO;
use App\Models\Main\CalendarListModel;
use App\Models\Main\CalendarEventModel;
use App\Models\Main\CalendarTaskModel;
use App\Models\Main\CalendarVisibilityModel;
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

        $this->logger->info('[CTOR] SyncService constructed', [
            'sapi' => PHP_SAPI,
            'pid'  => function_exists('getmypid') ? getmypid() : null,
        ]);
    }

    public function isSyncRunning(string $synologyLoginId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT is_running, started_at
            FROM main_calendar_sync_state
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
                UPDATE main_calendar_sync_state
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
            INSERT INTO main_calendar_sync_state
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

            $calendarList = $this->syncCalendarList(
                $synologyLoginId,
                $ownerUserId,
                $actor
            );
            $this->logger->info('[SYNCALL] calendar list ready', [
                'count' => count($calendarList),
            ]);

            if (empty($calendarList)) {
                $this->logger->error('[SYNCALL] aborted - calendar list is empty');
                return;
            }

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

        if (!$eventsRes['success']) {
            return;
        }

        $events = $eventsRes['data'] ?? [];

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

            $existing = $this->eventModel->findAnyByUid(
                $event['id'],
                $realSynologyLoginId
            );

            if ($existing && (int)$existing['is_active'] === 0) {

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

            if ((int)$row['is_active'] === 0) {
                continue;
            }

            if (!empty($row['restored_at'])) {

                $restored = strtotime($row['restored_at']);

                if ($restored && (time() - $restored) < 300) {
                    continue;
                }
            }

            if (!empty($row['raw_ics'])) {
                continue;
            }

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

        if ((int)$calendarRow['is_personal'] === 1) {
            if ($calendarRow['synology_login_id'] !== $synologyLoginId) {
                return;
            }
        }

        $realSynologyLoginId = $calendarRow['synology_login_id'];

        foreach ($tasks as $task) {

            if (empty($task['id'])) {
                continue;
            }

            $realSynologyLoginId = $calendarRow['synology_login_id'];

            $existing = $this->taskModel->findAnyByUid(
                $task['id'],
                $realSynologyLoginId
            );

            if ($existing && (int)$existing['is_active'] === 0) {
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

            if ((int)$row['is_active'] === 0) {
                continue;
            }

            if (!empty($row['restored_at'])) {

                $restored = strtotime($row['restored_at']);

                if ($restored && (time() - $restored) < 300) {
                    continue;
                }
            }

            if (!empty($row['raw_ics'])) {
                continue;
            }

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

    private function mapSynologyEvent(
        array $event,
        string $calendarId,
        ?string $realSynologyLoginId,
        ?string $actor
    ): array {

        $vevent = is_array($event['raw'] ?? null) ? $event['raw'] : [];


        $rawValue = static function (array $v, string $key) {
            if (!isset($v[$key])) return null;
            return $v[$key]['value'] ?? null;
        };


        $dtstartRaw = $rawValue($vevent, 'DTSTART') ?? $event['dtstart'] ?? null;
        $dtendRaw   = $rawValue($vevent, 'DTEND')   ?? $event['dtend']   ?? null;

        $allDay = (($vevent['DTSTART']['params']['VALUE'] ?? null) === 'DATE') ? 1 : 0;

        if ($allDay && $dtstartRaw && $dtendRaw) {

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

        $allDay = (($vevent['DTSTART']['params']['VALUE'] ?? null) === 'DATE') ? 1 : 0;

        $rrule  = $rawValue($vevent, 'RRULE') ?? ($event['rrule'] ?? null);
        $rdate  = $vevent['RDATE']  ?? [];
        $exdate = $vevent['EXDATE'] ?? [];

        $realCalendarId =
            $event['__meta']['calendar_id']
            ?? $event['calendar_id']
            ?? $calendarId;

        if (!$realCalendarId) {
            throw new \RuntimeException('calendar_id missing from event');
        }
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

            'id'         => $event['id'] ?? null,
            'calendar_id' => $realCalendarId,
            'owner_user_id' => $actor,
            'synology_login_id' => $realSynologyLoginId,
            'calendar_type' => 'external',
            'href'        => $event['_href'] ?? null,
            'etag'        => $event['_etag'] ?? null,
            'type'        => 'VEVENT',

            'sequence'      => isset($event['sequence']) ? (int)$event['sequence'] : null,
            'dtstamp'       => $rawValue($vevent, 'DTSTAMP'),
            'created'       => $rawValue($vevent, 'CREATED'),
            'last_modified' => $rawValue($vevent, 'LAST-MODIFIED'),

            'title'       => $rawValue($vevent, 'SUMMARY'),
            'description' => $rawValue($vevent, 'DESCRIPTION'),
            'location'    => $rawValue($vevent, 'LOCATION'),

            'dtstart' => $dtstart,
            'dtend'   => $dtend,
            'all_day' => $allDay,

            'event_color' =>
            $rawValue($vevent, 'X-SYNO-EVT-COLOR')
                ?? $rawValue($vevent, 'COLOR'),

            'admin_event_color' =>
            $existing['admin_event_color'] ?? null,

            'status'   => $rawValue($vevent, 'STATUS'),
            'priority' => isset($vevent['PRIORITY']['value'])
                ? (int)$vevent['PRIORITY']['value']
                : null,
            'transp'   => $rawValue($vevent, 'TRANSP'),

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

            'raw_json' => json_encode($event, JSON_UNESCAPED_UNICODE),
        ];
    }

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

        $existing = null;
        if (!empty($task['id'])) {
            $existing = $this->taskModel->findAnyByUid(
                $task['id'],
                $realSynologyLoginId
            );
        }

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

        $dueRaw   = $task['due']   ?? $rawValue($raw, 'DUE');
        $startRaw = $task['start'] ?? $rawValue($raw, 'DTSTART');

        $dueForDb = null;
        $dueYmd   = null;
        $startYmd = null;

        $dueIsDateParam   = (((($raw['DUE'] ?? [])['params']['VALUE'] ?? null) === 'DATE'));
        $startIsDateParam = (((($raw['DTSTART'] ?? [])['params']['VALUE'] ?? null) === 'DATE'));

        if ($dueRaw) {

            if ($dueIsDateParam || preg_match('/^\d{8}$/', (string)$dueRaw)) {

                $dt = Time::parseLocal((string)$dueRaw);
                $dueYmd   = $dt->format('Ymd');
                $dueForDb = $dt->format('Y-m-d');
            } else {

                $dt = Time::parseLocal((string)$dueRaw);
                $dueYmd   = $dt->format('Ymd');
                $dueForDb = $dt->format('Y-m-d H:i:s');
            }
        }

        if ($startRaw) {

            $dt = Time::parseLocal((string)$startRaw);
            $startYmd = $dt->format('Ymd');
        } elseif (is_array($existing) && !empty($existing['start_ymd'])) {

            $startYmd = $existing['start_ymd'];
        }

        $allDay = ($dueIsDateParam || $startIsDateParam) ? 1 : 0;

        $alarmsJson = null;

        if (!empty($task['alarms']) && is_array($task['alarms'])) {
            $alarmsJson = json_encode($task['alarms'], JSON_UNESCAPED_UNICODE);
        }

        if ($alarmsJson === null && is_array($existing) && array_key_exists('alarms_json', $existing)) {
            $alarmsJson = $existing['alarms_json'];
        }

        $href = $task['_href'] ?? null;
        $etag = $task['_etag'] ?? null;

        if (!$href && is_array($existing)) {
            $href = $existing['href'] ?? null;
        }
        if (!$etag && is_array($existing)) {
            $etag = $existing['etag'] ?? null;
        }

        $collectionHref = null;

        if ($href) {
            $collectionHref = rtrim(dirname($href), '/') . '/';
        }

        if (!$collectionHref && is_array($existing)) {
            $collectionHref = $existing['collection_href'] ?? null;
        }

        $status = $rawValue($raw, 'STATUS');

        if (!$status) {

            if (!empty($existing['status'])) {
                $status = $existing['status'];
            } else {

                $status = 'NEEDS-ACTION';
            }
        }

        $status = strtoupper(trim($status));

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

    public function syncOneEventByUid(
        string $id,
        string $arg2,
        ?string $actor = null,
        array $extra = []
    ): array {

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

        $calendarRow = $this->listModel->findByIdAndLogin(
            $calendarId,
            $synologyLoginId
        );

        if (!$calendarRow) {
            return ['status' => 'noop', 'event' => null];
        }

        if ((int)$calendarRow['is_personal'] === 1) {
            if ($calendarRow['synology_login_id'] !== $synologyLoginId) {
                return ['status' => 'forbidden', 'event' => null];
            }
        }

        $realSynologyLoginId = $calendarRow['synology_login_id'];

        $payload = $this->mapSynologyEvent(
            $event,
            $calendarId,
            $realSynologyLoginId,
            $actor
        );

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

        if (!$task) {

            if (!empty($extra['force_href'])) {
                return ['status' => 'pending', 'task' => null];
            }

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

        $calendarRow = $this->listModel->findByIdAndLogin(
            $calendarId,
            $synologyLoginId
        );

        if (!$calendarRow) {
            return ['status' => 'noop', 'task' => null];
        }

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

        $existing = $this->taskModel->findAnyByUid(
            $id,
            $realSynologyLoginId
        );

        if ($existing && (int)$existing['is_active'] === 0) {
            return ['status' => 'skipped-soft-deleted', 'task' => $existing];
        }

        $this->taskModel->upsert($payload);

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

            $calendarList = $this->syncCalendarList(
                $synologyLoginId,
                $ownerUserId,
                $actor
            );

            if (empty($calendarList)) {
                $this->logger->error('[REBUILD] calendar list empty');
                return;
            }

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
            UPDATE main_calendar_sync_state
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

        $this->pdo->prepare("
            INSERT IGNORE INTO main_calendar_sync_state
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

        $this->syncCalendarList(
            $synologyLoginId,
            $ownerUserId,
            $actor
        );

        $this->logger->info('[QUICK SYNC] done');
    }
}
