<?php
// 경로: PROJECT_ROOT . '/app/services/calendar/CalendarIcsService.php'
namespace App\Services\Calendar;

use App\Services\Calendar\Caldav\Ics;
use App\Services\Calendar\CalendarTime;

/**
 * =========================================================
 * CalendarIcsService
 * - ICS 문자열 전용 유틸
 * - 생성 / 파싱 / 패치
 * - ❌ CalDAV 통신 없음
 * =========================================================
 */
class CalendarIcsService
{

    private Ics $ics;

    public function __construct()
    {
        $this->ics = new Ics();
    }

    /* =============================
     * ICS 파싱은 "위임"만
     * ============================= */
    public function parseIcs(string $ics): array
    {
        return $this->ics->parseCalendarData($ics);
    }

    /* =============================
     * 이하 build / patch / extract
     * ============================= */





    /* =========================================================
     * UID / COMPONENT / SEQUENCE
     * ========================================================= */

    public function extractUid(string $ics): ?string
    {
        if (preg_match('/\nUID:(.+)\n/i', "\n" . $ics . "\n", $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function extractComponent(string $ics): string
    {
        if (stripos($ics, 'BEGIN:VTODO') !== false) return 'VTODO';
        return 'VEVENT';
    }

    public function extractSequence(string $ics): int
    {
        if (preg_match('/\nSEQUENCE:(\d+)\n/i', "\n" . $ics . "\n", $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    /* =========================================================
     * ICS 생성
     * ========================================================= */
    public function buildIcs(string $component, array $data): string
    {
        $component = strtoupper($component);
        if (!in_array($component, ['VEVENT', 'VTODO'], true)) {
            $component = 'VEVENT';
        }
    
        $uid   = $data['uid'] ?? ('uid-' . bin2hex(random_bytes(10)));
        $title = $this->escapeText($data['title'] ?? '');
        $now   = gmdate('Ymd\THis\Z');
    
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//SUKHYANG ERP//Calendar//KO',
            'CALSCALE:GREGORIAN',
        
            // 🔥 RFC 5545 정석: TZID 사용 시 반드시 VTIMEZONE 포함
            'BEGIN:VTIMEZONE',
            'TZID:Asia/Seoul',
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:+0900',
            'TZOFFSETTO:+0900',
            'TZNAME:KST',
            'END:STANDARD',
            'END:VTIMEZONE',
        
            "BEGIN:$component",
            "UID:$uid",
            "DTSTAMP:$now",
            "CREATED:$now",
            "LAST-MODIFIED:$now",
        ];
    
        if ($title !== '') {
            $lines[] = "SUMMARY:$title";
        }
    
        foreach ($data['raw_lines'] ?? [] as $line) {
            $lines[] = $line;
        }
    
        $lines[] = "END:$component";
        $lines[] = 'END:VCALENDAR';
    
        return $this->foldLines($lines);
    }

    /* =========================================================
     * STATUS / COMPLETED 패치
     * ========================================================= */

    public function patchStatus(string $ics, string $status): string
    {
        $component = $this->extractComponent($ics);
        $status = strtoupper(trim($status));

        $set = ["STATUS:$status"];
        $remove = ['STATUS'];

        if ($component === 'VTODO') {

            if ($status === 'COMPLETED') {
                $set[] = 'COMPLETED:' . gmdate('Ymd\THis\Z');
                $set[] = 'PERCENT-COMPLETE:100';
                $remove[] = 'PERCENT-COMPLETE';
            }
            elseif ($status === 'IN-PROCESS') {
                $set[] = 'PERCENT-COMPLETE:50';
                $remove[] = 'COMPLETED';
            }
            else { // NEEDS-ACTION
                $set[] = 'PERCENT-COMPLETE:0';
                $remove[] = 'COMPLETED';
            }
        }
        

        return $this->patchComponent($ics, $component, $set, $remove);
    }

    /* =========================================================
     * ICS Component Patch (공용)
     * ========================================================= */

    public function patchComponent(
        string $ics,
        string $component,
        array $setLines,
        array $removeKeys = []
    ): string {
        $icsN = str_replace(["\r\n", "\r"], "\n", $ics);

        $begin = "BEGIN:$component";
        $end   = "END:$component";

        $b = stripos($icsN, $begin);
        $e = stripos($icsN, $end);

        if ($b === false || $e === false || $e <= $b) {
            return $ics;
        }

        $before = substr($icsN, 0, $b);
        $block  = substr($icsN, $b, $e - $b + strlen($end));
        $after  = substr($icsN, $e + strlen($end));

        $lines = explode("\n", $block);

        $removeMap = [];
        foreach ($removeKeys as $k) {
            $k = strtoupper(trim($k));
            if ($k !== '') $removeMap[$k] = true;
        }

        foreach ($setLines as $ln) {
            $k = strtoupper(strtok($ln, ':') ?: '');
            if ($k !== '') $removeMap[$k] = true;
        }

        $filtered = [];
        foreach ($lines as $ln) {
            $rawKey = strtoupper(strtok(ltrim($ln), ':') ?: '');
            $key = strtoupper(strtok($rawKey, ';') ?: '');
            if ($key !== '' && isset($removeMap[$key])) continue;
            $filtered[] = $ln;
        }

        $insertAt = count($filtered) - 1;
        array_splice($filtered, $insertAt, 0, $setLines);

        return str_replace("\n", "\r\n", $before . implode("\n", $filtered) . $after);
    }

    public function patchPercent(string $ics, int $percent): string
    {
        $percent = max(0, min(100, $percent));

        return $this->patchComponent(
            $ics,
            'VTODO',
            ["PERCENT-COMPLETE:$percent"],
            ['PERCENT-COMPLETE']
        );
    }


    /* =========================================================
     * Utils
     * ========================================================= */

    private function escapeText(string $s): string
    {
        return str_replace(
            ["\\", ";", ",", "\r\n", "\r", "\n"],
            ["\\\\", "\;", "\,", "\\n", "\\n", "\\n"],
            $s
        );
    }

    private function foldLines(array $lines): string
    {
        $out = [];
        foreach ($lines as $ln) {
            while (strlen($ln) > 70) {
                $out[] = substr($ln, 0, 70);
                $ln = ' ' . substr($ln, 70);
            }
            $out[] = $ln;
        }
        return implode("\r\n", $out) . "\r\n";
    }


    public function escape(string $text): string
    {
        return $this->escapeText($text);
    }

    /**
     * RRULE / RDATE / EXDATE 추출
     */
    public function extractProperty(string $ics, string $name): ?string
    {
        if (preg_match('/^' . preg_quote($name, '/') . ':(.+)$/mi', $ics, $m)) {
            return $name . ':' . trim($m[1]);
        }

        // TZID 포함 케이스 (RDATE;TZID=Asia/Seoul:...)
        if (preg_match('/^' . preg_quote($name, '/') . ';[^:]+:(.+)$/mi', $ics, $m)) {
            return $name . ':' . trim($m[1]);
        }

        return null;
    }

    public function extractTzid(string $ics): ?string
    {
        if (preg_match('/DTSTART;TZID=([^:;]+)/', $ics, $m)) {
            return $m[1];
        }
        return null;
    }


/**
 * 🔥 create / rebuild 공용
 * payload → VEVENT raw lines 생성
 */
public function buildEventRawLines(array $payload, string $tzid = 'Asia/Seoul'): array
{
    $rawLines = [];

    // LOCATION
    if (array_key_exists('location', $payload)) {
        $rawLines[] = 'LOCATION:' . $this->escapeText($payload['location'] ?? '');
    }

    // DESCRIPTION
    if (array_key_exists('description', $payload)) {
        $rawLines[] = 'DESCRIPTION:' . $this->escapeText($payload['description'] ?? '');
    }

    $start = (string)($payload['start'] ?? '');
    $end   = (string)($payload['end'] ?? '');

    $isAllDay =
        !empty($payload['allDay']) ||
        (!empty($payload['allday'])) ||
        ($start !== '' && $end !== '' && substr($start, 0, 10) === substr($end, 0, 10));

        if ($isAllDay) {
            $baseStart = substr($start, 0, 10);
        
            // ✅ end가 없거나 start와 같으면 "하루짜리"
            $rawLines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $baseStart);
            $rawLines[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime($baseStart . ' +1 day'));
        } else {
        if ($start !== '') {
            $rawLines[] =
                'DTSTART;TZID=' . $tzid . ':' .
                CalendarTime::toIcsLocal($start);
        }
        
        if ($end !== '') {
            $rawLines[] =
                'DTEND;TZID=' . $tzid . ':' .
                CalendarTime::toIcsLocal($end);
        }
    }

    // RRULE
    if (!empty($payload['rrule'])) {
        $rr = (string)$payload['rrule'];
        if (!str_starts_with($rr, 'RRULE:')) {
            $rr = 'RRULE:' . $rr;
        }
        $rawLines[] = $rr;
    }

    return $rawLines;
}



/**
 * 🔔 Reminder → ICS TRIGGER 정규화
 *
 * 허용 입력 예:
 *  - 'at'        → TRIGGER:PT0S
 *  - '5m'        → TRIGGER:-PT5M
 *  - '10m'
 *  - '30m'
 *  - '1h'
 *  - '2h'
 *  - '1d'
 *  - '-PT15M'    (이미 ICS 포맷)
 */
public function normalizeAlarmTrigger(string $v): string
{
    $v = trim($v);

    if ($v === '' || $v === 'at') {
        return 'PT0S';
    }

    // 이미 ICS TRIGGER 형식이면 그대로
    if (preg_match('/^-?P(T?\d+[SMHD])+$/i', $v)) {
        return $v;
    }

    // 숫자 + 단위 파싱
    if (preg_match('/^(\d+)([smhd])$/i', $v, $m)) {
        $num  = (int)$m[1];
        $unit = strtoupper($m[2]);

        return match ($unit) {
            'S' => "-PT{$num}S",
            'M' => "-PT{$num}M",
            'H' => "-PT{$num}H",
            'D' => "-P{$num}D",
            default => '-PT0S',
        };
    }

    // fallback (안전)
    return 'PT0S';
}











}
