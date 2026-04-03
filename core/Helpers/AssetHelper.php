<?php
// /core/Helpers/AssetHelper.php

namespace Core\Helpers;

class AssetHelper
{
    /* =========================================================
       🔥 CORE ENGINE (절대 핵심)
    ========================================================= */
    public static function url(string $path): string
    {
        // 외부 URL 그대로 반환
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        $path = '/' . ltrim($path, '/');

        $publicPath = PROJECT_ROOT . '/public' . $path;

        $version = file_exists($publicPath)
            ? filemtime($publicPath)
            : time();

        $base = ConfigHelper::get('asset_base', '/public');

        return $base . $path . '?v=' . $version;
    }


    /* =========================================================
       🔥 OUTPUT (실제 사용)
    ========================================================= */

    // CSS
    public static function css(string $path): string
    {
        return '<link rel="stylesheet" href="' . self::url($path) . '">';
    }

    // JS
    public static function js($path, $defer = true)
    {
        $attr = $defer ? ' defer' : '';
        return '<script src="' . self::url($path) . '"' . $attr . '></script>';
    }

    // JS 모듈듈
    public static function module($path)
    {
        return '<script type="module" src="' . self::url($path) . '"></script>';
    }

    // IMG
    public static function img(string $path, string $alt = '', string $class = ''): string
    {
        return '<img src="' . self::url($path) . '" alt="' . htmlspecialchars($alt) . '" class="' . $class . '">';
    }
}