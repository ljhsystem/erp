<?php
// 경로: PROJECT_ROOT . '/app/services/calendar/caldav/ObjectClient.php'
namespace App\Services\Calendar\Caldav;

use Core\LoggerFactory;
use App\Services\Calendar\Caldav\HttpClient;
use App\Services\Calendar\Caldav\Ics;
use App\Services\Calendar\Caldav\Parser;

class ObjectClient
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    private HttpClient $http;
    private Ics $ics;
    private Parser $parser;

    // ✅ (HttpClient, Ics, Parser) 순서로 "고정"
    public function __construct(HttpClient $http, Ics $ics, Parser $parser)
    {
        $this->http   = $http;
        $this->ics    = $ics;
        $this->parser = $parser;

        $this->logger = LoggerFactory::getLogger('service-calendar.ObjectClient');
    }

    /* =========================================================
     * EVENTS / TODOS
     * ========================================================= */
    public function getEvents(string $collectionHref, ?string $from, ?string $to): array
    {
        return $this->getObjects($collectionHref, $from, $to, ['VEVENT']);
    }

    public function getTodos(string $collectionHref, ?string $from, ?string $to): array
    {
        return $this->getObjects($collectionHref, $from, $to, ['VTODO']);
    }

    public function getEventsAndTodos(string $collectionHref, ?string $from, ?string $to): array
    {
        return $this->getObjects($collectionHref, $from, $to, ['VEVENT', 'VTODO']);
    }

    public function getObjects(string $collectionHref, ?string $from, ?string $to, array $components): array
    {
        $timeRange = '';
    
        // 🔥🔥🔥 핵심 수정
        $hasVtodo = in_array('VTODO', $components, true);
    
        if (!$hasVtodo && ($from || $to)) {
            $attrs = [];
        
            if ($from) {
                $start = \App\Services\Calendar\CalendarTime::parseLocal($from)
                    ->format('Ymd\THis');
                $attrs[] = 'start="' . $start . '"';
            }
        
            if ($to) {
                $end = \App\Services\Calendar\CalendarTime::parseLocal($to)
                    ->format('Ymd\THis');
                $attrs[] = 'end="' . $end . '"';
            }
        
            $timeRange = '<c:time-range ' . implode(' ', $attrs) . ' />';
        }
    
        $filters = '';
        foreach ($components as $c) {
            $c = strtoupper(trim((string)$c));
            if ($c === '') continue;
    
            // 🔥 VTODO는 time-range 제거
            if ($c === 'VTODO') {
                $filters .= "<c:comp-filter name=\"VTODO\" />";
            } else {
                $filters .= "<c:comp-filter name=\"{$c}\">{$timeRange}</c:comp-filter>";
            }
        }
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <d:getetag />
    <c:calendar-data />
  </d:prop>
  <c:filter>
    <c:comp-filter name="VCALENDAR">
      {$filters}
    </c:comp-filter>
  </c:filter>
</c:calendar-query>
XML;

        $res = $this->http->request(
            'REPORT',
            $collectionHref,
            [
                'Depth: 1',
                'Content-Type: application/xml; charset=UTF-8',
            ],
            $xml
        );

        // ✅ Parser는 report 결과에서 (href, etag, ical)을 뽑는다
        $items = $this->parser->parseCalendarQuery($res['body'] ?? '');

        // ✅ Ics는 ical 텍스트를 events/todos로 파싱한다
        $out = [];
        foreach ($items as $it) {
            $parsed = $this->ics->parseCalendarData($it['ics'] ?? '');
        
            foreach ($parsed['events'] ?? [] as $ev) {
                $ev['_href'] = $it['href'] ?? '';
                $ev['_etag'] = $it['etag'] ?? '';
                $out[] = $ev;
            }
            foreach ($parsed['todos'] ?? [] as $td) {
                $td['_href'] = $it['href'] ?? '';
                $td['_etag'] = $it['etag'] ?? '';
                $td['_component'] = 'VTODO';
                $out[] = $td;
            }
        }        

        return $out;
    }

    /* =========================================================
     * ETag
     * ========================================================= */
    public function headEtag(string $resourceHref): ?string
    {
        $res = $this->http->request('HEAD', $resourceHref);
        if (($res['status'] ?? 0) >= 400) return null;

        $etag = $res['headers']['etag'] ?? null;
        return is_string($etag) && trim($etag) !== '' ? trim($etag) : null;
    }



/* =========================================================
 * CREATE (VEVENT / VTODO)
 * - Synology 필수: If-None-Match: *
 * ========================================================= */
public function createObject(string $objectHref, string $ics): array
{
    $this->logger->debug('[ObjectClient] CREATE', [
        'href' => $objectHref,
    ]);

    $res = $this->http->request(
        'PUT',
        $objectHref,
        [
            'Content-Type: text/calendar; charset=utf-8',
            'If-None-Match: *', // 🔥🔥🔥 Synology 필수
        ],
        $ics
    );

    $etag = null;
    if (!empty($res['headers']['etag'][0])) {
        $etag = trim($res['headers']['etag'][0], '"');
    }

    return [
        'status' => $res['status'] ?? 0,
        'etag'   => $etag,
        'href'   => $objectHref,
    ];
}






}
