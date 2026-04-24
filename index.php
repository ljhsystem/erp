<?php //phpinfo(); ?>
<?php
// 경로: PROJECT_ROOT . '/index.php';
//주석테스트2
use Core\Router;
use Core\Session;
use Core\Database;
use Core\PermissionRegistry;
use App\Services\Auth\AuthSessionService;
use App\Services\System\SessionConfigService;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

set_error_handler(function($severity, $message, $file, $line) {
    echo "<pre>🔥 PHP ERROR:\n$message\n$file:$line</pre>";
    exit;
});

set_exception_handler(function($e) {
    echo "<pre>🔥 EXCEPTION:\n" . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "</pre>";
    exit;
});










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
$sessionConfigService = new SessionConfigService(Database::getInstance()->getConnection());
Session::start($sessionConfigService->getTimeoutMinutes());
error_log("🌐 세션 시작됨: " . session_id());

$normalizedUri = $uri !== '/' ? rtrim((string)$uri, '/') : '/';
if ($normalizedUri === '') {
    $normalizedUri = '/';
}

$authSessionService = new AuthSessionService();
$isAuthenticated = $authSessionService->isAuthenticated();
error_log('Auth check: ' . ($isAuthenticated ? 'YES' : 'NO') . ' uri=' . $normalizedUri);

if ($isAuthenticated && in_array($normalizedUri, ['/index', '/index.php'], true)) {
    header('Location: /home');
    exit;
}

if (!$isAuthenticated && in_array($normalizedUri, ['/index', '/index.php'], true)) {
    header('Location: /home');
    exit;
}

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
