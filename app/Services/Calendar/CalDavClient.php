<?php
// 경로: PROJECT_ROOT . '/app/Services/Calendar/CalendarCrudService.php'
declare(strict_types=1);

namespace App\Services\Calendar;

use App\Services\Calendar\Caldav\HttpClient;
use App\Services\Calendar\Caldav\CollectionClient;
use App\Services\Calendar\Caldav\ObjectClient;
use App\Services\Calendar\Caldav\Parser;
use App\Services\Calendar\Caldav\Ics;
use App\Services\Calendar\Time;
use Core\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * =========================================================
 * CalDavClient (Facade)
 * - VEVENT + VTODO (태스크) 지원
 * - CalDAV 단일 진입점
 * =========================================================
 */
class CalDavClient
{
    private HttpClient $http;
    private Parser $parser;
    private Ics $ics;
    private CollectionClient $collections;
    private ObjectClient $objects;
    private LoggerInterface $logger;

    private array $cfg;
    private string $baseRoot;
    private string $principalsRoot;

    public function __construct(array $cfg)
    {
        $this->logger = LoggerFactory::getLogger('service-calendar.CalDavClient');
        $this->cfg = $cfg;

        $this->baseRoot = $this->normalizeBaseRoot($cfg['base_url']);
        $this->principalsRoot = rtrim($this->baseRoot, '/') . '/principals/';

        $this->http   = new HttpClient($cfg);
        $this->parser = new Parser($this->logger);
        $this->ics    = new Ics();

        $this->collections = new CollectionClient($this->http, $this->parser);
        $this->objects     = new ObjectClient($this->http, $this->ics, $this->parser);
    }

    /* =========================================================
     * 🔹 EVENTS / TASKS (READ)
     * ========================================================= */

    /** VEVENT */
    public function getEvents(string $collectionHref, ?string $from, ?string $to): array
    {
        return $this->objects->getEvents($collectionHref, $from, $to);
    }

    /** VTODO */
    public function getTodos(string $collectionHref, ?string $from, ?string $to): array
    {
        return $this->objects->getTodos($collectionHref, $from, $to);
    }

    /** VEVENT + VTODO */
    public function getEventsAndTodos(string $collectionHref, ?string $from, ?string $to): array
    {
        return $this->objects->getEventsAndTodos($collectionHref, $from, $to);
    }

    /* =========================================================
     * 🔹 RAW CRUD (VEVENT / VTODO 공통)
     * ========================================================= */
    public function createObject(string $href, string $ics): array
    {
        $res = $this->request(
            'PUT',
            $href,
            [
                'Content-Type: text/calendar; charset=utf-8'
            ],
            $ics
        );
    
        $etag = null;
        if (!empty($res['headers']['etag'][0])) {
            $etag = trim($res['headers']['etag'][0], '"');
        }
    
        return [
            'success' => ($res['status'] ?? 0) < 300,
            'etag'    => $etag,
            'status'  => $res['status'] ?? 0,
            'href'    => $href,
        ];
    }

    public function updateObject(string $href, string $ics, ?string $etag = null): array
    {
        $headers = [
            'Content-Type: text/calendar; charset=utf-8'
        ];
    
        if ($etag) {
            $etag = trim($etag, '"');
            $headers[] = 'If-Match: "' . $etag . '"';
        }
    
        return $this->request('PUT', $href, $headers, $ics);
    }
     
     

     public function deleteObject(string $href, ?string $etag = null): array
     {
         $href = trim($href);
         if ($href === '') {
             throw new \RuntimeException('deleteObject: href required');
         }
     
         $headers = [];
     
         if ($etag) {
             // 🔥 따옴표 제거 후 다시 정확히 감싸기
             $etag = trim($etag, '"');
             $headers[] = 'If-Match: "' . $etag . '"';
         }
     
         $this->logger->warning('[CalDAV] DELETE object', [
             'href' => $href,
             'etag' => $etag,
         ]);
     
         $res = $this->request('DELETE', $href, $headers);
     
         $status = (int)($res['status'] ?? 0);
     
         // ✅ 성공 코드만 성공 처리
         if ($status === 200 || $status === 204) {
             return [
                 'success' => true,
                 'status'  => $status,
             ];
         }
     
         // 이미 삭제된 경우
         if ($status === 404) {
             return [
                 'success' => true,
                 'status'  => $status,
             ];
         }
     
         // ❌ 나머지는 전부 실패
         throw new \RuntimeException(
             'CalDAV delete failed (HTTP ' . $status . ')'
         );
     }
     
     


    /* =========================================================
     * 🔹 COLLECTION
     * ========================================================= */

    public function listCalendarsFromHome(string $homeHref): array
    {
        return $this->collections->listCalendars($homeHref);
    }

    public function updateCalendarColor(string $collectionHref, string $color): void
    {
        $this->collections->updateCalendarColor($collectionHref, $color);
    }


    /* =========================================================
    * 🔥 COLLECTION DELETE
    *  - 캘린더 / 작업목록 (컬렉션) 삭제 전용
    *  - Inbox / 시스템 컬렉션은 서버에서 거부됨
    * ========================================================= */
    public function deleteCollection(string $collectionHref): array
    {
        $collectionHref = trim($collectionHref);
        if ($collectionHref === '') {
            throw new \RuntimeException('collection href required');
        }

        // ⚠️ 컬렉션은 반드시 trailing slash 필요
        $url = rtrim($collectionHref, '/') . '/';

        $this->logger->warning('[CalDAV] DELETE collection', [
            'href' => $url,
        ]);

        return $this->request('DELETE', $url);
    }



    /* =========================================================
     * 🔹 REQUEST (Raw)
     * ========================================================= */

    public function request(string $method, string $hrefOrUrl, array $headers = [], ?string $body = null): array
    {
        return $this->http->request($method, $hrefOrUrl, $headers, $body);
    }




    /**
     * base_url이 어떤 형태로 들어오든 caldav.php/까지만 잘라 root로 통일
     * 예) https://host:20003/caldav.php/xxxx -> https://host:20003/caldav.php/
     */
    private function normalizeBaseRoot(string $baseUrl): string
    {
        $p = parse_url($baseUrl);
        if (!$p || empty($p['scheme']) || empty($p['host'])) {
            throw new \RuntimeException('Invalid CalDAV base_url: ' . $baseUrl);
        }

        $scheme = $p['scheme'];
        $host   = $p['host'];
        $port   = isset($p['port']) ? ':' . $p['port'] : '';
        $path   = $p['path'] ?? '/';

        $idx = stripos($path, '/caldav.php');
        if ($idx !== false) {
            $path = substr($path, 0, $idx + strlen('/caldav.php'));
        } else {
            // base_url에 caldav.php가 없다면(설정 실수 가능)
            // 그래도 root로는 path 그대로 쓰되, 끝에 / 붙여둠
            $path = rtrim($path, '/');
        }

        $path = rtrim($path, '/') . '/';

        return $scheme . '://' . $host . $port . $path;
    }

    public function getUsername(): string
    {
        return $this->cfg['username'];
    }

    public function getBaseRoot(): string
    {
        return $this->baseRoot;
    }

    /* =========================================================
     * URL helpers
     * ========================================================= */

    /**
     * href(/caldav.php/...) 또는 absolute url을 CalDAV 서버 기준 absolute url로 변환
     */
    private function toAbsoluteUrl(string $hrefOrUrl): string
    {
        $hrefOrUrl = trim($hrefOrUrl);
        if ($hrefOrUrl === '') return '';

        // 이미 절대 URL
        if (preg_match('#^https?://#i', $hrefOrUrl)) return $hrefOrUrl;

        $path = $hrefOrUrl;
        if ($path[0] !== '/') $path = '/' . ltrim($path, '/');

        // /caldav.php/... 는 origin + path
        if (stripos($path, '/caldav.php/') === 0) {
            return $this->cfg['origin'] . $path;
        }

        // 그 외는 base_url 기준
        return $this->cfg['base_url'] . $path;
    }



    /* =========================================================
     * principals
     * ========================================================= */

    public function listPrincipals(): array
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:">
  <d:prop>
    <d:displayname />
    <d:resourcetype />
  </d:prop>
</d:propfind>
XML;

        $res = $this->http->request(
            'PROPFIND',
            $this->principalsRoot,
            ['Depth: 1'],
            $xml
        );

        return $this->parser->parsePrincipalList($res['body'] ?? '');
    }

    /* =========================================================
     * calendar-home-set
     * ========================================================= */

    public function getCalendarHomeSet(string $principalHref): ?string
    {
        $principalHref = trim($principalHref);
        if ($principalHref === '') return null;

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <c:calendar-home-set />
  </d:prop>
</d:propfind>
XML;

        $res = $this->http->request(
            'PROPFIND',
            $principalHref,
            ['Depth: 0'],
            $xml
        );

        return $this->parser->parseCalendarHomeSet($res['body'] ?? '');
    }

    /**
     * Synology 전용 fallback: root에서 calendar-home-set 조회
     */
    public function getCalendarHomeSetFromRoot(): ?string
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <c:calendar-home-set />
  </d:prop>
</d:propfind>
XML;

        $res = $this->http->request(
            'PROPFIND',
            $this->baseRoot,
            ['Depth: 0'],
            $xml
        );

        return $this->parser->parseCalendarHomeSet($res['body'] ?? '');
    }

    /* =========================================================
     * calendar collections
     * ========================================================= */



    public function getCalendarMeta(string $collectionHref): array
    {
        $collectionHref = trim($collectionHref);
        return $collectionHref !== '' ? $this->collections->getCalendarMeta($collectionHref) : [];
    }


    /* =========================================================
     * events / todos
     * ========================================================= */



    public function getEventActorDisplayName(string $href): string
    {
        // 필요해지면 구현 (지금은 빈 문자열 유지)
        return '';
    }



    public function toCalDavUtc(string $dt): string
    {
        $dt = trim($dt);
        if ($dt === '') return '';
    
        // CalendarTime은 "서울시간 DateTimeImmutable"로 파싱
        $local = Time::parseLocal($dt);
    
        // CalDAV query용 UTC(Z) 문자열로 변환
        return $local
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');
    }


    public function getObject(string $href): ?string
    {
        $res = $this->request('GET', $href);
        return $res['body'] ?? null;
    }
    

    public function propfind(string $href, int $depth = 1): array
    {
        $href = rtrim($href, '/') . '/';
    
        $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
      <d:prop>
        <d:resourcetype />
        <d:displayname />
      </d:prop>
    </d:propfind>
    XML;
    
        // ✅ requestRaw ❌ → request ✅
        $res = $this->request(
            'PROPFIND',
            $href,
            [
                'Depth: ' . $depth,
                'Content-Type: application/xml; charset=utf-8',
            ],
            $xml
        );
    
        if (($res['status'] ?? 0) >= 300 || empty($res['body'])) {
            return ['success' => false];
        }
    
        return [
            'success' => true,
            'data'    => $this->parsePropfindResponse($res['body']),
        ];
    }
    
    

    private function parsePropfindResponse(string $xml): array
    {
        $out = [];
    
        $dom = new \DOMDocument();
        @$dom->loadXML($xml);
    
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('d', 'DAV:');
    
        foreach ($xp->query('//d:response') as $r) {
            $href = urldecode($xp->evaluate('string(d:href)', $r));
    
            $types = [];
            foreach ($xp->query('.//d:resourcetype/*', $r) as $t) {
                $types[] = $t->localName;
            }
    
            $out[] = [
                'href' => $href,
                'resourcetype' => $types,
            ];
        }
    
        return $out;
    }
    

/**
 * 🔥 /home/ (calendar-home-set) → 실제 calendar collection href 찾기
 */
public function resolvePersonalCalendarCollection(string $homeHref): ?string
{
    $homeHref = rtrim($homeHref, '/') . '/';

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <d:resourcetype />
    <d:displayname />
  </d:prop>
</d:propfind>
XML;

    $res = $this->request(
        'PROPFIND',
        $homeHref,
        ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'],
        $xml
    );

    if (empty($res['body'])) {
        return null;
    }

    $dom = new \DOMDocument();
    @$dom->loadXML($res['body']);

    $xp = new \DOMXPath($dom);
    $xp->registerNamespace('d', 'DAV:');

    foreach ($xp->query('//d:response') as $r) {
        $href = urldecode($xp->evaluate('string(d:href)', $r));
        $href = rtrim($href, '/') . '/';

        // 자기 자신(home) 스킵
        if ($href === $homeHref) continue;

        // resourcetype 안에 <calendar/>
        if ($xp->query('.//d:resourcetype/*[local-name()="calendar"]', $r)->length > 0) {
            return $href; // 🎯 이게 진짜 개인 캘린더
        }
    }

    return null;
}

/* =========================================================
 * 🔍 UID 기반 단건 조회 (EVENT)
 * ========================================================= */
public function getEventByUid(string $uid): ?array
{
    $home = $this->getCalendarHomeSetFromRoot();
    if (!$home) return null;

    $calendars = $this->collections->listCalendars($home);

    foreach ($calendars as $cal) {
        if (($cal['type'] ?? '') !== 'calendar') continue;

        $href = rtrim($cal['href'], '/') . '/';
        $events = $this->objects->getEvents($href, null, null);

        foreach ($events as $ev) {
            if (($ev['uid'] ?? null) === $uid) {
                return $ev;
            }
        }
    }

    return null;
}


/* =========================================================
 * 🔍 UID 기반 단건 조회 (TASK)
 * ========================================================= */
public function getTaskByHref(string $href): ?array
{
    $res = $this->request('GET', $href);

    if (($res['status'] ?? 0) === 404) {
        return null;
    }

    return $this->parser->parseSingleVtodo($res['body']);
}





}
