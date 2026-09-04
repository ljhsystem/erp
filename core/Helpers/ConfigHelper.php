<?php
// /core/Helpers/ConfigHelper.php

namespace Core\Helpers;

use App\Services\System\SettingService;
use Core\Security\SecretResolver;

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
        $service = new SettingService();

        return $service->get($key, $default);
    }

    /**
     * 내부 Secret 반환
     */
    public static function secret(): string
    {
        return (new SecretResolver())->resolve('ERP_APP_MAIN', 'secret');
    }
}
