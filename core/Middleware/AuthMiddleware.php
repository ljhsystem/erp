<?php
// 경로: PROJECT_ROOT . '/core/Middleware/AuthMiddleware.php';
namespace Core\Middleware;

use Core\LoggerFactory;

class AuthMiddleware
{
    /** ---------------------------------------------------------
     *  Autologout: 로그인 여부 상관없이 항상 허용되는 경로
     * --------------------------------------------------------- */
    private static array $autoLogoutRoutes = [
        'autologout/keepalive',
        'autologout/extend',
        'autologout/expired',
    ];

    /** ---------------------------------------------------------
     *  비회원 접근 허용 페이지
     * --------------------------------------------------------- */
    private static array $publicRoutes = [
        '',
        '/',
        'home',
        'about',
        'contact',
        'privacy',
        'login',
        'logout',
        'register',
        'register_success',
        'approve_request',
        'approve_result',
        'waiting_approval',
        'find-id',
        'find-password',
        'find-id-result',
        'find-password-result',
        '2fa',
        'password',
        'password/change',
    ];

    /** ---------------------------------------------------------
     *  로그인 필요 prefix 그룹
     * --------------------------------------------------------- */
    private static array $protectedPrefixes = [
        'dashboard',
        'approval',
        'ledger',
        'institution',
        'site',
        'notice',
        'backup',
    ];

    /** ---------------------------------------------------------
     *  미들웨어 실행
     * --------------------------------------------------------- */
    public static function handle()
    {
        $logger = LoggerFactory::getLogger('core-middleware.AuthMiddleware');

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $controller = explode('/', $path)[0];
        $loggedIn = !empty($_SESSION['user']['id']);

        $logger->info("AUTH MIDDLEWARE → 요청 감지", [
            'path'       => $path,
            'controller' => $controller,
            'logged_in'  => $loggedIn,
        ]);

        /** -----------------------------------------------------
         * ⭐ 1) Autologout 예외 (인증 필요 없음)
         * ----------------------------------------------------- */
        if (in_array($path, self::$autoLogoutRoutes, true)) {
            $logger->info("PASS → Autologout 경로 예외 허용됨", [
                'path' => $path
            ]);
            return;
        }

        /** -----------------------------------------------------
         * ⭐ 2) 루트("/") 항상 허용
         * ----------------------------------------------------- */
        if ($path === '') {
            $logger->info("PASS → 루트('/') 요청 허용");
            return;
        }

        /** -----------------------------------------------------
         * ⭐ 3) API 요청 예외 처리 (인증 불필요)
         * ----------------------------------------------------- */
        if (strpos($path, 'api/') === 0) {
            $logger->info("PASS → API 요청 인증 예외 처리됨");
            return;
        }

        /** -----------------------------------------------------
         * ⭐ 4) 비밀번호 변경 전용 예외 처리
         * ----------------------------------------------------- */
        if ($controller === 'password') {

            // 정상 로그인 사용자
            if (!empty($_SESSION['user']['id'])) {
                $logger->info("PASS → password 접근 (로그인 사용자)");
                return;
            }

            // 비밀번호 만료로 인한 임시 접근
            if (!empty($_SESSION['password_expired_user']['user_id'])) {
                $logger->info("PASS → password 접근 (비밀번호 만료 상태)", [
                    'user_id' => $_SESSION['password_expired_user']['user_id']
                ]);
                return;
            }

            // 그 외는 차단
            $logger->warning("BLOCK → password 접근 차단 (인증 없음)", [
                'path' => $path
            ]);
            header('Location: /login');
            exit;
        }



        /** -----------------------------------------------------
         * ⭐ 4) 로그인 상태에서 /login 접근 → /dashboard
         * ----------------------------------------------------- */
        if ($loggedIn && $controller === 'login') {
            $logger->info("REDIRECT → 로그인한 사용자가 /login 접근 → /dashboard 이동");
            header('Location: /dashboard');
            exit;
        }

        /** -----------------------------------------------------
         * ⭐ 5) 공개 라우트 허용
         * ----------------------------------------------------- */
        if (in_array($controller, self::$publicRoutes, true)) {
            $logger->info("PASS → 공개 라우트 접근 허용됨");
            return;
        }

        /** -----------------------------------------------------
         * ⭐ 6) 보호 prefix → 로그인 필요
         * ----------------------------------------------------- */
        foreach (self::$protectedPrefixes as $prefix) {
            if (strpos($path, $prefix) === 0) {

                if (!$loggedIn) {
                    $logger->warning("BLOCK → 보호된 prefix 접근. 로그인 필요.", [
                        'prefix' => $prefix,
                        'path'   => $path
                    ]);
                    header('Location: /login');
                    exit;
                }

                $logger->info("PASS → 보호된 prefix 접근 (로그인됨)", [
                    'prefix' => $prefix
                ]);
                return;
            }
        }

        /** -----------------------------------------------------
         * ⭐ 7) 그 외는 기본 허용
         * ----------------------------------------------------- */
        $logger->info("PASS → 특별 규칙 없음. 요청 허용됨.", [
            'path' => $path
        ]);
    }
}
