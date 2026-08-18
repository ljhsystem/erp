<?php
declare(strict_types=1);

namespace App\Services\Calendar;

final class Time
{
    public const TZID = 'Asia/Seoul';

    public static function tz(): \DateTimeZone
    {
        return new \DateTimeZone(self::TZID);
    }

    public static function parseLocal(string $s): \DateTimeImmutable
    {
        $s = trim($s);
        if ($s === '') {
            throw new \RuntimeException('CalendarTime.parseLocal: empty');
        }

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

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $s)) {
            $s = str_replace('T', ' ', $s);
        }

        $tz = self::tz();

        if (preg_match('/^\d{8}T\d{6}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd\THis', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid YmdTHis: ' . $s);
            return $dt;
        }

        if (preg_match('/^\d{8}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Ymd', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid Ymd: ' . $s);
            return $dt;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $s)) {
            $s = str_replace('T', ' ', $s) . ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid Y-m-d: ' . $s);
            return $dt->setTime(0, 0, 0);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid Y-m-d H:i: ' . $s);
            return $dt;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $s)) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $s, $tz);
            if (!$dt) throw new \RuntimeException('CalendarTime.parseLocal: invalid Y-m-d H:i:s: ' . $s);
            return $dt;
        }

        $dt = new \DateTimeImmutable($s, $tz);
        return $dt;
    }

    public static function toIcsDate(string $localAny): string
    {
        return self::parseLocal($localAny)->format('Ymd');
    }

    public static function toIcsLocal(string $localAny): string
    {
        return self::parseLocal($localAny)->format('Ymd\THis');
    }

    public static function toDbLocal(string $localAny): string
    {
        return self::parseLocal($localAny)->format('Y-m-d H:i:s');
    }
}
