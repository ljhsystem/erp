<?php
// 경로: PROJECT_ROOT . '/app/Services/Calendar/Caldav/CollectionClient.php'
namespace App\Services\Calendar\Caldav;

use Core\LoggerFactory;

/**
 * =========================================================
 * CollectionClient
 * - CalDAV Calendar Collection 전용
 * - PROPFIND (calendar meta / list)
 * - PROPPATCH (calendar color)
 * =========================================================
 */
class CollectionClient
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    private HttpClient $http;
    private Parser $parser;

    public function __construct(
        HttpClient $http,
        Parser $parser
    ) {
        $this->http   = $http;
        $this->parser = $parser;

        $this->logger = LoggerFactory::getLogger('service-calendar.CollectionClient');
    }

    /* =========================================================
     * 캘린더 홈 하위 컬렉션 목록
     * ========================================================= */
    public function listCalendars(string $calendarHomeHref): array
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:"
           xmlns:c="urn:ietf:params:xml:ns:caldav"
           xmlns:cs="http://calendarserver.org/ns/"
           xmlns:apple="http://apple.com/ns/ical/">
  <d:prop>
    <d:displayname />
    <d:resourcetype />
    <d:owner />
    <d:supported-report-set />
    <c:supported-calendar-component-set />
    <cs:getctag />
    <apple:calendar-color />
    <apple:calendar-order />
  </d:prop>
</d:propfind>
XML;

        $this->logger->info('[Collection] list calendars', [
            'href' => $calendarHomeHref,
        ]);

        $res = $this->http->request(
            'PROPFIND',
            $calendarHomeHref,
            [
                'Depth: 1',
                'Content-Type: application/xml; charset=UTF-8',
            ],
            $xml
        );

        return $this->parser->parseCalendarCollections($res['body'] ?? '');
    }

    /* =========================================================
     * 단일 캘린더 메타데이터
     * ========================================================= */
    public function getCalendarMeta(string $collectionHref): array
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:"
           xmlns:cs="http://calendarserver.org/ns/"
           xmlns:apple="http://apple.com/ns/ical/">
  <d:prop>
    <d:displayname />
    <d:owner />
    <d:getlastmodified />
    <cs:getctag />
    <apple:calendar-color />
    <apple:calendar-order />
  </d:prop>
</d:propfind>
XML;

        $this->logger->debug('[Collection] get meta', [
            'href' => $collectionHref,
        ]);

        $res = $this->http->request(
            'PROPFIND',
            $collectionHref,
            [
                'Depth: 0',
                'Content-Type: application/xml; charset=UTF-8',
            ],
            $xml
        );

        return $this->parser->parseCollectionMeta($res['body'] ?? '');
    }

    /* =========================================================
     * 캘린더 색상 변경 (Synology)
     * ========================================================= */
   public function updateCalendarColor(string $collectionHref, string $color): void
    {
        // #RRGGBB → #RRGGBBAA (Synology / Apple 표준)
        $hex = strtoupper(ltrim($color, '#'));
        if (!preg_match('/^[0-9A-F]{6}$/', $hex)) {
            throw new \RuntimeException('Invalid color format');
        }

        $calendarColor = '#' . $hex . 'FF';

        $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <d:propertyupdate
        xmlns:d="DAV:"
        xmlns:apple="http://apple.com/ns/ical/">
    <d:set>
        <d:prop>
        <apple:calendar-color>{$calendarColor}</apple:calendar-color>
        </d:prop>
    </d:set>
    </d:propertyupdate>
    XML;

        $this->logger->info('[Collection] update calendar color (safe)', [
            'href'  => $collectionHref,
            'color' => $calendarColor,
        ]);

        $res = $this->http->request(
            'PROPPATCH',
            rtrim($collectionHref, '/') . '/',
            ['Content-Type: application/xml; charset=UTF-8'],
            $xml
        );

        if (($res['status'] ?? 0) >= 400) {
            throw new \RuntimeException(
                'CalDAV PROPPATCH calendar-color failed. status=' . ($res['status'] ?? 0)
            );
        }
    }

}
