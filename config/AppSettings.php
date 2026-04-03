<?php
// 경로: PROJECT_ROOT/config/AppSettings.php
function loadAppSettings($path = PROJECT_ROOT . '/config/appsetting.json') {
    static $cached = null;
    if ($cached !== null) return $cached;

    if (!file_exists($path)) {
        throw new Exception("설정 파일이 존재하지 않습니다: $path");
    }

    $json = file_get_contents($path);
    $cached = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON 파싱 에러: " . json_last_error_msg());
    }

    return $cached;
}
