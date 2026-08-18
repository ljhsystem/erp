<?php
namespace App\Services\Calendar;

use App\Services\Calendar\Caldav\Ics;
use App\Services\Calendar\Time;

class IcsService
{

    private Ics $ics;

    public function __construct()
    {
        $this->ics = new Ics();
    }

    public function parseIcs(string $ics): array
    {
        return $this->ics->parseCalendarData($ics);
    }

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

    public function buildIcs(string $component, array $data): string
    {
        $component = strtoupper($component);
        if (!in_array($component, ['VEVENT', 'VTODO'], true)) {
            $component = 'VEVENT';
        }

        $id   = $data['id'] ?? ('id-' . bin2hex(random_bytes(10)));
        $title = $this->escapeText($data['title'] ?? '');
        $now   = gmdate('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//SUKHYANG ERP//Calendar//KO',
            'CALSCALE:GREGORIAN',

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
            "id:$id",
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
            } elseif ($status === 'IN-PROCESS') {
                $set[] = 'PERCENT-COMPLETE:50';
                $remove[] = 'COMPLETED';
            } else { // NEEDS-ACTION
                $set[] = 'PERCENT-COMPLETE:0';
                $remove[] = 'COMPLETED';
            }
        }


        return $this->patchComponent($ics, $component, $set, $remove);
    }


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

    public function extractProperty(string $ics, string $name): ?string
    {
        if (preg_match('/^' . preg_quote($name, '/') . ':(.+)$/mi', $ics, $m)) {
            return $name . ':' . trim($m[1]);
        }

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

    public function buildEventRawLines(array $payload, string $tzid = 'Asia/Seoul'): array
    {
        $rawLines = [];

        if (array_key_exists('location', $payload)) {
            $rawLines[] = 'LOCATION:' . $this->escapeText($payload['location'] ?? '');
        }

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

            $rawLines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $baseStart);
            $rawLines[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime($baseStart . ' +1 day'));
        } else {
            if ($start !== '') {
                $rawLines[] =
                    'DTSTART;TZID=' . $tzid . ':' .
                    Time::toIcsLocal($start);
            }

            if ($end !== '') {
                $rawLines[] =
                    'DTEND;TZID=' . $tzid . ':' .
                    Time::toIcsLocal($end);
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

    public function normalizeAlarmTrigger(string $v): string
    {
        $v = trim($v);

        if ($v === '' || $v === 'at') {
            return 'PT0S';
        }
        if (preg_match('/^-?P(T?\d+[SMHD])+$/i', $v)) {
            return $v;
        }

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

        return 'PT0S';
    }
}
