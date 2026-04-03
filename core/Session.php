<?php
// 경로: PROJECT_ROOT . '/core/Session.php'
namespace Core;

use function Core\storage_system_path;
use Core\LoggerFactory;

class Session
{
    private static bool $initialized = false;
    private static $logger;

    // Logger 초기화
    private static function logInit()
    {
        if (!self::$logger) {
            self::$logger = LoggerFactory::getLogger('core-Session');
        }
    }

    // ============================================
    // 1. DB의 시스템 설정값 읽기
    // ============================================
    public static function getSystemConfig(string $key, $default = null)
    {
        self::logInit();

        static $cache = [];
        if (isset($cache[$key])) {
            self::$logger->info("⚙ config cache hit", ['key' => $key, 'value' => $cache[$key]]);
            return $cache[$key];
        }

        try {
            require_once PROJECT_ROOT . '/core/Database.php';
            $pdo = Database::getInstance()->getConnection();

            $stmt = $pdo->prepare("SELECT config_value FROM system_settings_config WHERE config_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row && isset($row['config_value'])) {
                $cache[$key] = $row['config_value'];
                self::$logger->info("⚙ config loaded", ['key' => $key, 'value' => $row['config_value']]);
                return $row['config_value'];
            }
        } catch (\Throwable $e) {

            self::$logger->error("❌ config read error", [
                'key'   => $key,
                'error' => $e->getMessage()
            ]);
        }

        self::$logger->warning("⚠ config not found → default 사용", [
            'key'     => $key,
            'default' => $default
        ]);

        return $default;
    }

    // ============================================
    // 2. 세션 시작
    // ============================================
    public static function start(): void
    {
        self::logInit();

        if (self::$initialized) {
            self::$logger->info("🔄 Session already initialized");
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {

            $isHttps = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            );

            // 저장 경로    
            $savePath = storage_system_path('sessions');
            
            if (!$savePath) {
                throw new \RuntimeException('Session storage path not configured');
            }
            
            if (!is_dir($savePath)) {
                mkdir($savePath, 0777, true);
            }
            


            

            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $cookieDomain = preg_replace('/:\d+$/', '', $host);

            // 쿠키 설정
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => $cookieDomain,
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_name('SUKHYANG_ERP');
            session_save_path($savePath);
            session_start();

            self::$initialized = true;

            self::$logger->info("🚀 Session started", [
                'session_id' => session_id(),
                'https'      => $isHttps,
                'domain'     => $cookieDomain
            ]);
        }

        // 타임아웃 적용
        $timeout = (int) self::getSystemConfig('session_timeout', 30);

        if (empty($_SESSION['expire_time'])) {
            $_SESSION['expire_time'] = time() + ($timeout * 60);

            self::$logger->info("⏱ expire_time initialized", [
                'expire_time' => $_SESSION['expire_time'],
                'timeout_min' => $timeout
            ]);
        }
    }

    // ============================================
    // 3. 세션 저장(write)
    // ============================================
    public static function write(): void
    {
        self::logInit();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        session_write_close();
        self::$initialized = false;

        self::$logger->info("💾 Session written & closed", [
            'session_id' => session_id()
        ]);
    }

    // ============================================
    // 4. 세션 연장
    // ============================================
    public static function extend(): int
    {
        self::logInit();

        if (session_status() === PHP_SESSION_NONE) {
            self::start();
        }

        $timeout = (int) self::getSystemConfig('session_timeout', 30);
        $_SESSION['expire_time'] = time() + ($timeout * 60);

        self::$logger->info("⏫ Session extended", [
            'expire_time' => $_SESSION['expire_time'],
            'timeout_min' => $timeout
        ]);

        return $_SESSION['expire_time'];
    }

    // ============================================
    // 5. 세션 제거
    // ============================================
    public static function destroy(): void
    {
        self::logInit();

        if (session_status() === PHP_SESSION_NONE) return;

        $oldId = session_id();

        $_SESSION = [];
        session_unset();
        session_destroy();

        self::$logger->warning("🔥 Session destroyed", [
            'old_session_id' => $oldId
        ]);
    }

    // ============================================
    // 6. 인증 여부 확인
    // ============================================
    public static function isAuthenticated(): bool
    {
        self::logInit();

        if (session_status() === PHP_SESSION_NONE) self::start();

        if (empty($_SESSION['user'])) {
            self::$logger->info("🔓 Not authenticated (no user)");
            return false;
        }

        if (empty($_SESSION['expire_time']) || $_SESSION['expire_time'] < time()) {
            self::$logger->warning("⛔ Session expired", [
                'expire_time' => $_SESSION['expire_time'],
                'now'         => time()
            ]);
            self::destroy();
            return false;
        }

        self::$logger->info("🔐 Authenticated", [
            'user_id' => $_SESSION['user']['id'] ?? null
        ]);

        return true;
    }

    // ============================================
    // 7. 인증 필요 페이지 접근 제한
    // ============================================
    public static function requireAuth(): void
    {
        self::logInit();

        if (!self::isAuthenticated()) {

            $isAjax =
                (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                (!empty($_SERVER['HTTP_ACCEPT']) &&
                    strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

            self::$logger->warning("🚫 requireAuth() → 인증 실패", [
                'ajax' => $isAjax,
                'path' => $_SERVER['REQUEST_URI']
            ]);

            if ($isAjax) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Session expired']);
                exit;
            }

            header('Location: /login');
            exit;
        }
    }

    // ============================================
    // 8. 기타 설정값 조회
    // ============================================
    public static function getExpireTime(): int
    {
        return $_SESSION['expire_time'] ?? time() + 1800;
    }

    public static function getAlertTime(): int
    {
        return (int) self::getSystemConfig('session_alert', 5);
    }

    public static function getAlertSound(): string
    {
        return (string) self::getSystemConfig('session_sound', 'alert1.mp3');
    }
}
