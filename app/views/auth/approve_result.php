<?php
// 경로: PROJECT_ROOT . '/app/views/auth/approve_result.php'
declare(strict_types=1);

use Core\Helpers\AssetHelper;

ob_start();
header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/storage/logs/php_errors.log');

// 컨트롤러가 결과를 세션에 남겼으면 그 것을 우선 사용하고 DB 업데이트는 건너뜀
$message    = $_SESSION['approve_message'] ?? '';
$isSuccess  = false;
$skipUpdate = false;

if (!empty($message)) {
    // 컨트롤러가 "승인이 완료되었습니다." 같은 메시지를 남겼을 경우 성공으로 간주
    $isSuccess = (mb_strpos($message, '완료') !== false);
    $skipUpdate = true;
    // 뷰 렌더 후 메시지 제거
    unset($_SESSION['approve_message']);
}

try {
    $pdo = \Core\Database::getInstance()->getConnection();
} catch (Throwable $e) {
    if (!$skipUpdate) {
        http_response_code(500);
        exit("<h3>DB 연결 실패</h3><p>{$e->getMessage()}</p>");
    }
    // DB 필요 없을 때는 계속해서 결과 표시
}

// 뷰 자체에서 업데이트를 수행해야 할 경우 (컨트롤러가 처리하지 않은 경우)
if (!$skipUpdate) {
    // code는 POST 또는 GET 둘 다 허용 (메일/직접접근 모두 대응)
    $userCode = trim((string)($_POST['code'] ?? $_GET['code'] ?? ''));

    // 승인 작업 허용 여부 (토큰 검증이 성공해야 true)
    $canApprove = true;

    // approve_token (메일로 전달된 토큰)이 있어야만 승인자 이메일을 사용
    $approveToken = trim((string)($_POST['approve_token'] ?? $_GET['approve_token'] ?? ''));
    $approvedByFromToken = null;

    if ($approveToken === '') {
        $message = '❌ 승인 토큰이 없습니다. 메일의 승인 링크로 접근해 주세요.';
        error_log("[approve_result] approve_token 누락: code=" . ($userCode ?: '[empty]'));
        $canApprove = false;
    } else {
        // 비밀키 우선: APP_SECRET 상수 -> config.AppSecret -> InternalApiSecret
        // ✅ ApprovalService::loadSecret() 과 동일한 규칙으로 통일
        $secret = '';
        if (\defined('APP_SECRET')) {
            $val = \constant('APP_SECRET');
            if (is_string($val) && $val !== '') {
                $secret = $val;
            }
        }

        if ($secret === '') {
            $configFile = PROJECT_ROOT . '/config/appsetting.json';
            if (file_exists($configFile)) {
                $raw = file_get_contents($configFile);
                // 간단한 주석 제거(허용된 경우)
                $raw = preg_replace('#^\s*//.*$#m', '', $raw);
                $raw = preg_replace('#/\*.*?\*/#s', '', $raw);
                $cfg = json_decode($raw, true);
                if (is_array($cfg)) {
                    if (!empty($cfg['AppSecret'])) {
                        $secret = $cfg['AppSecret'];
                    } elseif (!empty($cfg['InternalApiSecret'])) {
                        $secret = $cfg['InternalApiSecret'];
                        error_log("[approve_result] AppSecret 없음 - InternalApiSecret 사용");
                    }
                }
            }
        }

        if ($secret === '') {
            $message = '❌ 승인 토큰 검증용 시크릿이 설정되어 있지 않습니다.';
            error_log("[approve_result] 시크릿 없음 - 토큰 검증 불가");
            $canApprove = false;
        } else {
            // ✅ 디버그: 시크릿 존재 여부 + 토큰 앞부분 로그
            error_log("[approve_result] secret loaded, has_secret=1, token_short=" . substr($approveToken, 0, 16));

            // MailToken 검증 유틸 사용(중앙화)
            // ❌ 기존: /app/services/Mail/MailToken.php  (대문자 M)
            // ✅ 수정: /app/services/mail/MailToken.php  (실제 폴더명과 동일)
            $mailTokenFile = PROJECT_ROOT . '/app/Services/Mail/MailToken.php';
            if (file_exists($mailTokenFile)) {
                require_once $mailTokenFile;
                try {
                    $data = \App\Services\Mail\MailToken::verify($approveToken, $secret);
                    if (is_array($data)) {
                        // 토큰의 admin(관리자 이메일)이 있어야만 승인자에 반영
                        $approvedByFromToken = trim((string)($data['admin'] ?? ''));
                        // user_code 불일치 시 거부
                        if (!empty($data['user_code']) && $userCode !== '' && $data['user_code'] !== $userCode) {
                            error_log("[approve_result] 토큰의 user_code 불일치: token_user_code={$data['user_code']} form_code={$userCode}");
                            $message = '❌ 토큰의 사용자 코드와 요청 코드가 불일치합니다.';
                            $canApprove = false;
                        } elseif ($approvedByFromToken === '') {
                            error_log("[approve_result] 토큰에 admin 값 없음");
                            $message = '❌ 토큰에 승인자 정보가 없습니다.';
                            $canApprove = false;
                        } else {
                            // ✅ 여기에서 user_id 를 얻는다
                            $userIdFromToken = null;

                            // 1) 토큰에 user_id 가 직접 들어온 경우
                            if (!empty($data['user_id'])) {
                                $userIdFromToken = (string)$data['user_id'];
                            }
                            // 2) user_id 가 없고 user_code 만 있을 경우 DB 에서 조회
                            elseif (!empty($data['user_code'])) {
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT id FROM auth_users
                                         WHERE code = :code
                                        LIMIT 1
                                    ");
                                    $stmt->execute([':code' => $data['user_code']]);
                                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                                    if ($row && !empty($row['id'])) {
                                        $userIdFromToken = (string)$row['id'];
                                    }
                                } catch (\Throwable $e) {
                                    error_log('[approve_result] user_id 조회 예외: ' . $e->getMessage());
                                }
                            }

                            // userIdFromToken 을 끝까지 못 구하면 승인 차단
                            if (empty($userIdFromToken)) {
                                error_log('[approve_result] 토큰/코드로 user_id 를 찾지 못함');
                                $message = '❌ 승인 대상 사용자를 찾을 수 없습니다.';
                                $canApprove = false;
                            }
                        }
                    } else {
                        error_log("[approve_result] MailToken::verify 실패 또는 만료");
                        $message = '❌ 승인 토큰이 유효하지 않거나 만료되었습니다.';
                        $canApprove = false;
                    }
                } catch (Throwable $e) {
                    error_log("[approve_result] MailToken::verify 예외: " . $e->getMessage());
                    $message = '❌ 토큰 검증 중 오류가 발생했습니다.';
                    $canApprove = false;
                }
            } else {
                // MailToken 유틸 파일 없음 — 실패 처리
                error_log("[approve_result] MailToken 유틸 파일 없음");
                $message = '❌ 서버 구성 오류: MailToken 유틸 없음';
                $canApprove = false;
            }
        }
    }

    // 반드시 토큰에서 온 관리자 이메일만 승인자로 사용
    $approvedBy = $approvedByFromToken;


    // 디버그 로그: 실제 값 기록 (php 에러로그로 출력)
    error_log("[approve_result] userCode=" . ($userCode === '' ? '[empty]' : $userCode) . " approvedByFromToken=" . ($approvedByFromToken ?? '[null]') . " canApprove=" . ($canApprove ? '1' : '0') . " userIdFromToken=" . ($userIdFromToken ?? '[null]'));

    // 승인 수행 (토큰 검증 실패 시 업데이트하지 않음)
    if ($canApprove) {
        require_once PROJECT_ROOT . '/app/Services/Auth/ApprovalService.php';

        // ✅ FQCN으로 직접 사용
        $pdo = \Core\Database::getInstance()->getConnection();
        $approvalService = new \App\Services\Auth\ApprovalService($pdo);

        // ✅ 여기서 userIdFromToken 은 항상 문자열 또는 null이므로, 위에서 null 인 경우 이미 canApprove=false 로 막음
        $ok = $approvalService->approveUser($userIdFromToken, $approvedByFromToken);

        if ($ok) {
            $message   = '✅ 회원 승인 완료되었습니다.';
            $isSuccess = true;
        } else {
            $message = '⚠️ 이미 승인되었거나 존재하지 않는 사용자입니다.';
        }
    } else {
        // 검증 실패한 경우 업데이트 차단 — 뷰에 메시지만 보여줌
        if ($message === '') {
            $message = '❌ 승인 토큰 검증 실패로 처리가 중단되었습니다.';
        }
    }
} else {
    // 컨트롤러에서 approved_by를 전달해둔 경우 뷰에 반영 (단, 이 코드 경로는 컨트롤러가 처리한 상황)
    $approvedBy = $_GET['approved_by'] ?? $_POST['approved_by'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>회원 승인 완료</title>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css') ?>
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Pretendard', 'Noto Sans KR', sans-serif;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            background: #fff;
            text-align: center;
        }

        .card-header {
            background: linear-gradient(135deg, #007bff, #00bfff);
            color: #fff;
            font-size: 1.3rem;
            font-weight: 600;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            padding: 1rem 1.5rem;
        }

        .emoji {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff, #00bfff);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 0.6rem 1.4rem;
            transition: 0.3s;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0069d9, #00a0e0);
            transform: translateY(-1px);
        }

        .btn-outline-danger {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.6rem 1.4rem;
        }

        .info-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 8px;
        }

        /* 자동닫기 실패 시 안내 박스 */
        #close-help {
            display: none;
            margin-top: 12px;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="card" style="max-width:480px; width:90%;">
        <div class="card-header">회원 승인 결과</div>
        <div class="card-body p-4">
            <div class="emoji"><?= $isSuccess ? '🎉' : '⚠️' ?></div>
            <p class="fs-5 mb-3"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

            <?php if (!empty($approvedBy)): ?>
                <p class="info-text">승인자: <?= htmlspecialchars($approvedBy, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <div class="d-grid gap-2 mt-4">
                <a href="/" class="btn btn-primary">🏠 메인으로 돌아가기</a>
                <button type="button" class="btn btn-outline-danger" onclick="tryClose()">❌ 창 닫기</button>
            </div>

            <div id="close-help">브라우저 정책으로 인해 자동으로 닫히지 않는 경우가 있습니다. 이 창을 수동으로 닫아주세요.</div>
        </div>
    </div>

    <script>
        function tryClose() {
            try {
                // 먼저 닫기 시도 (팝업으로 열었을 때 동작)
                window.close();

                // 닫히지 않으면 사용자에게 안내만 보여주고 리다이렉트하지 않음
                setTimeout(function() {
                    if (!window.closed) {
                        // 안내 텍스트 표시
                        var help = document.getElementById('close-help');
                        if (help) help.style.display = 'block';
                        // 추가 팝업 시도 (대부분 브라우저에서 차단됨)
                        try {
                            window.open('', '_self');
                            window.close();
                        } catch (e) {
                            /* ignore */ }
                    }
                }, 200);
            } catch (e) {
                // 예외 발생 시 안내만 출력
                var help = document.getElementById('close-help');
                if (help) help.style.display = 'block';
            }
        }
    </script>
</body>

</html>