<?php
// 경로: PROJECT_ROOT . '/core/Middleware/ApiAccessMiddleware.php';
namespace Core\Middleware;

//require_once PROJECT_ROOT . '/app/services/system/SettingService.php';

use App\Services\System\SettingService;
use Core\Database;
use Core\LoggerFactory;

class ApiAccessMiddleware
{
    public static function handle()
    {
        $logger = LoggerFactory::getLogger('core.middleware.ApiAccess');

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        // 외부 API만 검사
        if (strpos($path, 'api/external/') !== 0) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');

        $pdo = Database::getInstance()->getConnection();
        $settings = new SettingService($pdo);

        /* =====================================================
         * 1. API 활성화 여부
         * ===================================================== */
        if (!$settings->getBool('api_enabled', false)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => '외부 API가 비활성화되어 있습니다.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        /* =====================================================
         * 2. API Key / Secret 검사
         * ===================================================== */
        $clientKey    = $_SERVER['HTTP_X_API_KEY']    ?? null;
        $clientSecret = $_SERVER['HTTP_X_API_SECRET'] ?? null;

        $serverKey    = trim((string)$settings->get('api_key'));
        $serverSecret = trim((string)$settings->get('api_secret'));

        if (
            !$clientKey || !$clientSecret ||
            $clientKey !== $serverKey ||
            $clientSecret !== $serverSecret
        ) {
            $logger->warning('외부 API 인증 실패', [
                'path'   => $path,
                'key'    => $clientKey ? substr($clientKey, 0, 6) . '***' : null,
            ]);

            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => '유효하지 않은 API 인증 정보'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $logger->info('외부 API 접근 허용', [
            'path' => $path
        ]);
    }
}
