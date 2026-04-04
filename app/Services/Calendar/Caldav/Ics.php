<?php
// 경로: PROJECT_ROOT . '/app/Services/Calendar/Caldav/caldav/Ics.php'
namespace App\Services\Calendar\Caldav;

class Ics
{
    public function parseCalendarData(string $ics): array
    {
        $ics = trim((string)$ics);
        if ($ics === '') return ['events' => [], 'todos' => []];

        $ics = str_replace(["\r\n", "\r"], "\n", $ics);

        // line folding 펼치기
        $lines = preg_split("/\n/", $ics);
        $unfolded = [];
        foreach ($lines as $line) {
            if ($line === '' || $line === "\n") continue;
            if (!empty($unfolded) && (str_starts_with($line, ' ') || str_starts_with($line, "\t"))) {
                $unfolded[count($unfolded) - 1] .= ltrim($line);
            } else {
                $unfolded[] = rtrim($line, "\n");
            }
        }

        $blocks = $this->splitBlocks($unfolded, ['VEVENT', 'VTODO']);

        $out = ['events' => [], 'todos' => []];
        foreach ($blocks as $b) {
            $type = $b['type'];
            $data = $this->parseBlock($type, $b['lines']);
            if (!$data) continue;

            if ($type === 'VEVENT') $out['events'][] = $data;
            if ($type === 'VTODO')  $out['todos'][]  = $data;
        }

        return $out;
    }

    private function splitBlocks(array $lines, array $types): array
    {
        $targets = array_flip($types);
        $blocks = [];
        $curType = null;
        $cur = [];

        foreach ($lines as $line) {
            if (preg_match('/^BEGIN:(.+)$/', $line, $m)) {
                $t = strtoupper(trim($m[1]));
                if (isset($targets[$t])) {
                    $curType = $t;
                    $cur = [];
                }
            }

            if ($curType) $cur[] = $line;

            if (preg_match('/^END:(.+)$/', $line, $m)) {
                $t = strtoupper(trim($m[1]));
                if ($curType === $t) {
                    $blocks[] = ['type' => $curType, 'lines' => $cur];
                    $curType = null;
                    $cur = [];
                }
            }
        }

        return $blocks;
    }

    private function parseBlock(string $type, array $lines): ?array
    {
        $props = [];
        $alarms = [];

        $inAlarm = false;
        $alarmLines = [];

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VALARM') {
                $inAlarm = true;
                $alarmLines = [];
                continue;
            }
            if ($line === 'END:VALARM') {
                $inAlarm = false;
                $alarms[] = $this->parseAlarm($alarmLines);
                $alarmLines = [];
                continue;
            }
            if ($inAlarm) {
                $alarmLines[] = $line;
                continue;
            }

            if (!str_contains($line, ':')) continue;
            [$left, $value] = explode(':', $line, 2);

            $leftParts = explode(';', $left);
            $key = strtoupper(trim($leftParts[0]));
            if ($key === 'BEGIN' || $key === 'END') continue;

            $params = [];
            for ($i = 1; $i < count($leftParts); $i++) {
                if (!str_contains($leftParts[$i], '=')) continue;
                [$pk, $pv] = explode('=', $leftParts[$i], 2);
                $params[strtoupper(trim($pk))] = trim($pv);
            }

            $value = $this->unescapeText($value);

            $multiKeys = ['ATTENDEE','CATEGORIES','COMMENT','EXDATE','RDATE','RELATED-TO','ATTACH'];
            if (in_array($key, $multiKeys, true)) {
                $props[$key][] = ['value' => $value, 'params' => $params];
            } else {
                $props[$key] = ['value' => $value, 'params' => $params];
            }
        }

        // 최소 조건
        if ($type === 'VEVENT') {
            if (empty($props['DTSTART']['value'])) return null;
        }
        if ($type === 'VTODO') {
            if (empty($props['DUE']['value']) && empty($props['DTSTART']['value'])) return null;
        }

        $uid     = $props['UID']['value'] ?? null;
        $summary = $props['SUMMARY']['value'] ?? '';

        $dtstart = $props['DTSTART']['value'] ?? null;
        $dtend   = $props['DTEND']['value'] ?? null;
        $due     = $props['DUE']['value'] ?? null;

        $startIso = $dtstart ? $this->normalizeDateTime($dtstart) : null;
        $endIso   = $dtend   ? $this->normalizeDateTime($dtend)   : null;
        $dueIso   = $due     ? $this->normalizeDateTime($due)     : null;

        $attendees = [];
        if (!empty($props['ATTENDEE'])) {
            foreach ($props['ATTENDEE'] as $a) {
                $attendees[] = [
                    'value'    => $a['value'],
                    'cn'       => $a['params']['CN'] ?? null,
                    'role'     => $a['params']['ROLE'] ?? null,
                    'partstat' => $a['params']['PARTSTAT'] ?? null,
                    'rsvp'     => $a['params']['RSVP'] ?? null,
                ];
            }
        }

        $categories = [];
        if (!empty($props['CATEGORIES'])) {
            foreach ($props['CATEGORIES'] as $c) {
                $categories = array_merge($categories, array_map('trim', explode(',', (string)$c['value'])));
            }
            $categories = array_values(array_filter(array_unique($categories)));
        }

        $comments = [];
        if (!empty($props['COMMENT'])) {
            foreach ($props['COMMENT'] as $c) $comments[] = (string)$c['value'];
        }

        $rdate = [];
        if (!empty($props['RDATE'])) foreach ($props['RDATE'] as $c) $rdate[] = (string)$c['value'];

        $exdate = [];
        if (!empty($props['EXDATE'])) foreach ($props['EXDATE'] as $c) $exdate[] = (string)$c['value'];

        $relatedTo = [];
        if (!empty($props['RELATED-TO'])) foreach ($props['RELATED-TO'] as $c) $relatedTo[] = (string)$c['value'];

        $data = [
            'type' => $type,
        
            'uid'           => $uid,
            'sequence'      => $props['SEQUENCE']['value'] ?? null,
            'dtstamp'       => $props['DTSTAMP']['value'] ?? null,
            'created'       => $props['CREATED']['value'] ?? null,
            'last_modified' => $props['LAST-MODIFIED']['value'] ?? null,
            'creator'       => $props['CREATOR']['value'] ?? null,
            'x_syno_creator'  => $props['X-SYNO-CREATOR']['value'] ?? null,
            'x_syno_modifier' => $props['X-SYNO-MODIFIER']['value'] ?? null,
        
            'dtstart' => $dtstart,
            'dtend'   => $dtend,
            'due'     => $due,
            'start'   => $startIso,
            'end'     => $endIso,
            'due_iso' => $dueIso,
        
            'summary'     => $summary,
            'title'       => $summary,
            'description' => $props['DESCRIPTION']['value'] ?? null,
            'location'    => $props['LOCATION']['value'] ?? null,
            'categories'  => $categories,
            'status'      => $props['STATUS']['value'] ?? null,
            'priority'    => $props['PRIORITY']['value'] ?? null,
        
            'class'   => $props['CLASS']['value'] ?? null,
            'transp'  => $props['TRANSP']['value'] ?? null,
            'url'     => $props['URL']['value'] ?? null,
            'comment' => $comments,
            'contact' => $props['CONTACT']['value'] ?? null,
        
            'rrule'         => $props['RRULE']['value'] ?? null,
            'rdate'         => $rdate,
            'exdate'        => $exdate,
            'recurrence_id' => $props['RECURRENCE-ID']['value'] ?? null,
            'exrule'        => $props['EXRULE']['value'] ?? null,
        
            'related_to' => $relatedTo,
        
            'organizer' => $props['ORGANIZER']['value'] ?? null,
            'attendees' => $attendees,
        
            'alarms' => $alarms,
        
            'raw' => $props,
        ];
        
        if ($type === 'VTODO') {
            $data['completed'] = $props['COMPLETED']['value'] ?? null;
            $data['status']    = $props['STATUS']['value'] ?? 'NEEDS-ACTION';
            $data['percent']   = isset($props['PERCENT-COMPLETE'])
                ? (int)$props['PERCENT-COMPLETE']['value']
                : 0;
        }
        
        return $data;

    }

    private function parseAlarm(array $lines): array
    {
        $a = [];
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) continue;
            [$k, $v] = explode(':', $line, 2);

            $k = strtoupper(trim(explode(';', $k)[0]));
            $v = $this->unescapeText($v);

            $a[$k][] = $v;
        }

        return [
            'trigger'     => $a['TRIGGER'][0] ?? null,
            'action'      => $a['ACTION'][0] ?? null,
            'description' => $a['DESCRIPTION'][0] ?? null,
            'repeat'      => $a['REPEAT'][0] ?? null,
            'duration'    => $a['DURATION'][0] ?? null,
            'attach'      => $a['ATTACH'][0] ?? null,
            'raw'         => $a,
        ];
    }

    private function normalizeDateTime(string $v): string
    {
        $v = trim($v);
    
        if (preg_match('/^\d{8}$/', $v)) {
            return substr($v, 0, 4) . '-' .
                   substr($v, 4, 2) . '-' .
                   substr($v, 6, 2);
        }
    
        if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?(Z)?$/', $v, $m)) {
    
            $y  = $m[1];
            $mo = $m[2];
            $d  = $m[3];
            $h  = $m[4];
            $mi = $m[5];
            $s  = ($m[6] ?? '') !== '' ? $m[6] : '00';
            $isUtc = ($m[7] ?? '') === 'Z';
    
            if ($isUtc) {
                // 🔥 UTC → 서울 변환
                $dt = new \DateTime("{$y}-{$mo}-{$d} {$h}:{$mi}:{$s}", new \DateTimeZone('UTC'));
                $dt->setTimezone(new \DateTimeZone('Asia/Seoul'));
                return $dt->format('Y-m-d\TH:i:s');
            }
    
            // 🔥 TZID 있는 경우는 이미 로컬이므로 그대로
            return "{$y}-{$mo}-{$d}T{$h}:{$mi}:{$s}";
        }
    
        return $v;
    }

    private function unescapeText(string $s): string
    {
        $s = html_entity_decode((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $s = str_replace(['\\n', '\\N'], "\n", $s);
        $s = str_replace(['\\,', '\\;', '\\\\'], [',', ';', '\\'], $s);
        return $s;
    }
}
