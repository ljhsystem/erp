<?php
// 경로: PROJECT_ROOT . '/app/views/auth/logout.php';

// 1. 로그 파일 경로 설정
$logPath = PROJECT_ROOT . '/storage/logs/php_errors.log';
error_log('[LOGOUT PAGE] 로그아웃 페이지 진입', 3, $logPath);

// 2. Session 클래스 로드
require_once PROJECT_ROOT . '/core/Session.php';

// 3. 캐시 방지 헤더 설정 (이미 전송되지 않았을 때만)
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// 4. 세션 삭제
\Core\Session::destroy();
error_log('[LOGOUT PAGE] Session::destroy() 호출 완료', 3, $logPath);

// 5. 현재 세션 상태 로깅
error_log('[LOGOUT] $_SESSION 상태: ' . print_r($_SESSION, true), 3, $logPath);
error_log('[LOGOUT] PHPSESSID 쿠키: ' . ($_COOKIE[session_name()] ?? '없음'), 3, $logPath);

// 6. 로그인 페이지로 강제 이동
header('Location: /login', true, 303);
exit;
