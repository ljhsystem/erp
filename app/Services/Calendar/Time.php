<?php
// 경로: PROJECT_ROOT . '/app/Services/Calendar/Time.php'
declare(strict_types=1);

namespace App\Services\Calendar;

final class Time
{
    public const TZID = 'Asia/Seoul';

    public static function tz(): \DateTimeZone
    {
        return new \DateTimeZone(self::TZID);
    }

    /**
     * 다양한 입력을 "서울시간 DateTimeImmutable"로 통일 파싱
     * 지원:
     * - 2026-02-25
     * - 2026-02-25 09:00
     * - 2026-02-25 09:00:00
     * - 2026-02-25T09:00
     * - 20260225
     * - 20260225T090000
     */
    public static function parseLocal(string $s): \DateTimeImmutable
    {
        $s = trim($s);
        if ($s === '') {
            throw new \RuntimeException('CalendarTime.parseLocal: empty');
        }
    
        // 1️⃣ YYYYMMDDTHHMMSSZ (UTC)
        if (preg_match('/^(\d{8}T\d{6})Z$/', $s, $m)) {
            $utc = \DateTimeImmutable::createFromFormat(
                'Ymd\THis',
                $m[1],
                new \DateTimeZone('UTC')
            );
            if (!$utc) {
                throw new \RuntimeException('CalendarTime.parseLocal: invalid UTC Z format: ' . $s);
            }
            return $utc->setTimezone(self::tz());
        }
    
        // 2️⃣ YYYY-MM-DDTHH:MM:SS
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $s)) {
            $s = str_replace('T', ' ', $s);
        }
    
        $tz = self::tz();

        // YYYYMMDDTHHMMSS
        if (preg_match('/^\d{8}T\d{6}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd\THis', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid YmdTHis: ' . $s);
            return $dt;
        }

        // YYYYMMDD
        if (preg_match('/^\d{8}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid Ymd: ' . $s);
            return $dt;
        }

        // YYYY-MM-DDTHH:MM  → YYYY-MM-DD HH:MM
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $s)) {
            $s = str_replace('T', ' ', $s) . ':00';
        }

        // YYYY-MM-DD (date only)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid Y-m-d: ' . $s);
            return $dt->setTime(0, 0, 0);
        }

        // YYYY-MM-DD HH:MM
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid Y-m-d H:i: ' . $s);
            return $dt;
        }

        // YYYY-MM-DD HH:MM:SS
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid Y-m-d H:i:s: ' . $s);
            return $dt;
        }

        // 마지막 fallback (그래도 TZ는 서울로 강제)
        $dt = new \DateTimeImmutable($s, $tz);
        return $dt;
    }

    /** ICS: YYYYMMDD */
    public static function toIcsDate(string $localAny): string
    {
        return self::parseLocal($localAny)->format('Ymd');
    }

    /** ICS local datetime: YYYYMMDDTHHMMSS (TZID=Asia/Seoul와 함께 사용) */
    public static function toIcsLocal(string $localAny): string
    {
        return self::parseLocal($localAny)->format('Ymd\THis');
    }

    /** DB 저장용 local: Y-m-d H:i:s */
    public static function toDbLocal(string $localAny): string
    {
        return self::parseLocal($localAny)->format('Y-m-d H:i:s');
    }
}