<?php
// 경로: PROJECT_ROOT . '/index.php';
use Core\Router;
use Core\Session;
use Core\Database;
use Core\PermissionRegistry;

/* ============================================================
 * 1) 프로젝트 루트 정의
 * ============================================================ */
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', __DIR__);
}

/* ============================================================
 * 2) Storage
 * ============================================================ */
require_once PROJECT_ROOT . '/core/Storage.php';

/* ============================================================
 * 3) Bootstrap
 *    - Core 필수 파일
 *    - Helpers 자동 로드
 * ============================================================ */

require_once PROJECT_ROOT . '/core/Bootstrap.php';

/* ============================================================
 * 4) 추가 Core 클래스
 * ============================================================ */


/* ============================================================
 * 5) favicon.ico 요청은 Router 이전에 직접 처리
 * ============================================================ */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_ends_with($uri, '/favicon.ico')) {

    require_once PROJECT_ROOT . '/app/Services/System/BrandService.php';

    $pdo = Database::getInstance()->getConnection();

    $brandService = new \App\Services\System\BrandService($pdo);

    $favicon = $brandService->getActive('favicon');
    $faviconPath = $favicon['db_path'] ?? null;

    if (!$faviconPath) {
        http_response_code(404);
        exit;
    }

    $faviconAbsPath = \Core\storage_resolve_abs($faviconPath);

    if (!$faviconAbsPath || !is_file($faviconAbsPath)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: image/x-icon');
    header('Content-Length: ' . filesize($faviconAbsPath));
    header('Cache-Control: public, max-age=86400');

    readfile($faviconAbsPath);
    exit;
}

/* ============================================================
 * 6) Router 초기화
 * ============================================================ */
$router = new Router();

/* ============================================================
 * 7) Session 시작
 * ============================================================ */
Session::start();
error_log("🌐 세션 시작됨: " . session_id());

/* ============================================================
 * 8) 라우트 로드
 * ============================================================ */
require_once PROJECT_ROOT . '/routes/web.php';
require_once PROJECT_ROOT . '/routes/api.php';

/* ============================================================
 * 9) 퍼미션 자동 DB 반영
 * ============================================================ */
PermissionRegistry::syncToDatabase(
    Database::getInstance()->getConnection()
);

/* ============================================================
 * 10) 라우터 실행
 * ============================================================ */
$router->resolve();