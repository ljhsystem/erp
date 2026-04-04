<?php
// 경로: PROJECT_ROOT . '/app/Services/Calendar/Caldav/Parser.php'
namespace App\Services\Calendar\Caldav;

/**
 * =========================================================
 * Parser
 * - CalDAV XML 파싱 전담
 * - PROPFIND / REPORT 결과 해석
 * =========================================================
 */
class Parser
{
    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;

    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }

    /* =========================================================
     * 공용: XPath 생성
     * ========================================================= */
    private function newXPath(string $xml): ?\DOMXPath
    {
        $xml = trim($xml);
        if ($xml === '') return null;

        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$ok) return null;

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('d', 'DAV:');
        $xp->registerNamespace('c', 'urn:ietf:params:xml:ns:caldav');
        $xp->registerNamespace('cs', 'http://calendarserver.org/ns/');
        $xp->registerNamespace('apple', 'http://apple.com/ns/ical/');
        return $xp;
    }

    private function decode(string $v): string
    {
        return html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /* =========================================================
     * principals
     * ========================================================= */
    public function parsePrincipalList(string $xml): array
    {
        $xp = $this->newXPath($xml);
        if (!$xp) return [];

        $nodes = $xp->query('//d:response');
        if (!$nodes) return [];

        $out = [];
        foreach ($nodes as $r) {
            $href = trim($xp->evaluate('string(d:href)', $r));
            if ($href === '') continue;

            $name = trim($this->decode(
                (string)$xp->evaluate('string(d:propstat/d:prop/d:displayname)', $r)
            ));

            $out[] = [
                'href' => $href,
                'name' => $name,
            ];
        }

        return $out;
    }

    /* =========================================================
     * calendar-home-set
     * ========================================================= */
    public function parseCalendarHomeSet(string $xml): ?string
    {
        $xp = $this->newXPath($xml);
        if (!$xp) return null;

        $href = trim($xp->evaluate('string(//c:calendar-home-set/d:href)'));
        if ($href !== '') return $href;

        return trim($xp->evaluate(
            'string(//*[local-name()="calendar-home-set"]/*[local-name()="href"])'
        )) ?: null;
    }

    /* =========================================================
     * calendar collections (Depth:1)
     * ========================================================= */
    public function parseCalendarCollections(string $xml): array
    {
        $xp = $this->newXPath($xml);
        if (!$xp) return [];

        $nodes = $xp->query('//d:response');
        if (!$nodes) return [];

        $out = [];
        foreach ($nodes as $r) {
            $href = trim($xp->evaluate('string(d:href)', $r));
            if ($href === '') continue;

            $isCalendarResource = (bool)$xp->evaluate(
                'boolean(d:propstat/d:prop/d:resourcetype//*[local-name()="calendar"])',
                $r
            );
            
            if (!$isCalendarResource) continue;
            
            $components = $this->extractComponents($xp, $r);
            
            if (!$components) continue;
            
            $type = null;
            if (in_array('VEVENT', $components, true)) {
                $type = 'calendar';
            } elseif (in_array('VTODO', $components, true)) {
                $type = 'task';
            } else {
                $type = 'unknown';
            }
            

            $name = trim($this->decode(
                (string)$xp->evaluate('string(d:propstat/d:prop/d:displayname)', $r)
            ));

            $color = $this->extractCalendarColor($xp, $r);


            $out[] = [
                'href' => $href,
                'name' => $name ?: '(이름 없음)',
                'calendar_color' => $color,
                'type'       => $type,        // ⭐ 이 줄
                'components' => $components,  // ⭐ 이 줄 (강력 추천)
            ];
        }

        return $out;
    }

    /* =========================================================
     * collection meta (Depth:0)
     * ========================================================= */
    public function parseCollectionMeta(string $xml): array
    {
        $xp = $this->newXPath($xml);
        if (!$xp) return [];

        return [
            'name'  => trim($this->decode(
                (string)$xp->evaluate('string(//d:displayname)')
            )) ?: null,
            'color' => trim($xp->evaluate(
                'string(//apple:calendar-color)'
            )) ?: null,
            'ctag'  => trim($xp->evaluate(
                'string(//cs:getctag)'
            )) ?: null,
        ];
    }

    /* =========================================================
     * REPORT calendar-query
     * ========================================================= */
    public function parseCalendarQuery(string $xml): array
    {
        $xp = $this->newXPath($xml);
        if (!$xp) return [];

        $nodes = $xp->query('//d:response');
        if (!$nodes) return [];

        $out = [];
        foreach ($nodes as $r) {
            $href = trim($xp->evaluate('string(d:href)', $r));
            $etag = trim($xp->evaluate(
                'string(d:propstat/d:prop/d:getetag)', $r
            ));

            $ics = trim($this->decode(
                (string)$xp->evaluate(
                    'string(d:propstat/d:prop/*[local-name()="calendar-data"])',
                    $r
                )
            ));

            if ($ics === '') continue;

            $out[] = [
                'href' => $href,
                'etag' => $etag,
                'ics'  => $ics,
            ];
        }

        return $out;
    }

    /* =========================================================
     * actor helpers
     * ========================================================= */
    public function extractCreatorDisplayName(string $xml): string
    {
        $xp = $this->newXPath($xml);
        if (!$xp) return '';

        return trim($this->decode(
            (string)$xp->evaluate('string(//d:creator-displayname)')
        ));
    }

    public function extractOwnerHref(string $xml): string
    {
        $xp = $this->newXPath($xml);
        if (!$xp) return '';

        return trim($xp->evaluate('string(//d:owner/d:href)'));
    }

    public function extractDisplayName(string $xml): string
    {
        $xp = $this->newXPath($xml);
        if (!$xp) return '';

        return trim($this->decode(
            (string)$xp->evaluate('string(//d:displayname)')
        ));
    }

/* =========================================================
 * Synology calendar color extractor
 * ========================================================= */
private function extractCalendarColor(\DOMXPath $xp, \DOMNode $r): ?string
{
    $queries = [
        'string(d:propstat/d:prop/cs:calendar-color)',
        'string(d:propstat/d:prop/apple:calendar-color)',
        'string(d:propstat/d:prop/*[local-name()="calendar-color"])',
    ];

    foreach ($queries as $q) {
        $v = trim((string)$xp->evaluate($q, $r));
        if ($v !== '') {
            // #RRGGBB 또는 #RRGGBBAA → #RRGGBB 로 정규화
            if ($v[0] === '#') {
                return strtoupper(substr($v, 0, 7));
            }
        }
    }

    return null;
}

private function extractComponents(\DOMXPath $xp, \DOMNode $r): array
{
    $nodes = $xp->query(
        'd:propstat/d:prop/c:supported-calendar-component-set/c:comp',
        $r
    );

    if (!$nodes) return [];

    $out = [];
    foreach ($nodes as $n) {
        if (!$n instanceof \DOMElement) continue; // ⭐ 핵심

        $name = strtoupper(trim($n->getAttribute('name')));
        if ($name !== '') {
            $out[] = $name;
        }
    }

    return array_values(array_unique($out));
}


/* =========================================================
 * 🔍 Single VTODO ICS 파서 (GET 단건 조회용)
 * ========================================================= */
public function parseSingleVtodo(string $ics): ?array
{
    $ics = trim($ics);
    if ($ics === '') return null;

    // UID
    if (!preg_match('/^UID:(.+)$/mi', $ics, $m)) {
        return null;
    }

    $uid = trim($m[1]);

    // STATUS
    $status = null;
    if (preg_match('/^STATUS:(.+)$/mi', $ics, $m)) {
        $status = strtoupper(trim($m[1]));
    }

    // PERCENT-COMPLETE
    $percent = null;
    if (preg_match('/^PERCENT-COMPLETE:(\d+)/mi', $ics, $m)) {
        $percent = (int)$m[1];
    }

    // COMPLETED
    $completed = null;
    if (preg_match('/^COMPLETED:(.+)$/mi', $ics, $m)) {
        $completed = trim($m[1]);
    }

    // SUMMARY
    $title = null;
    if (preg_match('/^SUMMARY:(.+)$/mi', $ics, $m)) {
        $title = trim($m[1]);
    }

    // DESCRIPTION
    $description = null;
    if (preg_match('/^DESCRIPTION:(.+)$/mi', $ics, $m)) {
        $description = trim($m[1]);
    }

    // DUE
    $due = null;
    if (preg_match('/^DUE(:|;[^:]+:)(.+)$/mi', $ics, $m)) {
        $due = trim($m[2]);
    }

    return [
        'uid'       => $uid,
        'status'    => $status,
        'percent'   => $percent,
        'completed' => $completed,
        'title'     => $title,
        'description'=> $description,
        'due'       => $due,
        'ics'       => $ics, // 원본 보관
    ];
}







    
}
