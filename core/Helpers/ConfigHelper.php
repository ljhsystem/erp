<?php
// /core/Helpers/ConfigHelper.php

namespace Core\Helpers;

use Core\Database;
use App\Services\System\SettingService;

class ConfigHelper
{
    /**
     * JSON 설정 캐시
     */
    private static ?array $config = null;

    /**
     * JSON 설정 조회 (config/appsetting.json)
     */
    public static function get(string $key, $default = null)
    {
        if (self::$config === null) {

            $path = PROJECT_ROOT . '/config/appsetting.json';

            if (!file_exists($path)) {
                return $default;
            }

            $json = file_get_contents($path);

            // 주석 제거
            $json = preg_replace('#^\s*//.*$#m', '', $json);
            $json = preg_replace('#/\*.*?\*/#s', '', $json);

            self::$config = json_decode($json, true) ?? [];
        }

        $segments = explode('.', $key);
        $value = self::$config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * DB 시스템 설정 조회
     */
    public static function system(string $key, $default = null)
    {
        $pdo = Database::getInstance()->getConnection();
        $service = new SettingService($pdo);

        return $service->get($key, $default);
    }

    /**
     * 내부 Secret 반환
     */
    public static function secret(): string
    {
        // 1. 상수 우선
        if (defined('APP_SECRET')) {
            return APP_SECRET;
        }

        // 2. JSON 설정
        $secret = self::get('InternalApiSecret');
        if (!empty($secret)) {
            return $secret;
        }

        $secret = self::get('AppSecret');
        if (!empty($secret)) {
            return $secret;
        }

        // ❌ fallback 금지
        throw new \RuntimeException('Secret key is not configured');

        // 3. fallback
        //return 'default-secret-key';
    }
}
