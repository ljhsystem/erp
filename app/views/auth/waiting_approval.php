<?php
//경로: PROJECT_ROOT . '/app/views/auth/waiting_approval.php'
$message = $_SESSION['register_message'] ?? '관리자 승인 대기 중입니다.';
// 메시지 인코딩을 UTF-8로 강제 변환 (EUC-KR 등에서 넘어올 경우)
if (!mb_check_encoding($message, 'UTF-8')) {
    $message = mb_convert_encoding($message, 'UTF-8', 'auto');
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>승인 대기 중</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            font-family: 'Noto Sans KR', 'Segoe UI', 'Malgun Gothic', Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .waiting-container {
            background: #fff;
            padding: 48px 36px;
            border-radius: 18px;
            box-shadow: 0 6px 32px rgba(0, 0, 0, 0.10);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }

        .waiting-icon {
            font-size: 56px;
            margin-bottom: 18px;
            animation: pulse 1.2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.08);
            }

            100% {
                transform: scale(1);
            }
        }

        .waiting-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2d4059;
            margin-bottom: 16px;
        }

        .waiting-message {
            font-size: 1.15rem;
            color: #555;
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .info-text {
            font-size: 0.98rem;
            color: #888;
            margin-bottom: 18px;
        }

        .home-link {
            display: inline-block;
            margin-top: 10px;
            color: #30aadd;
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px dashed #30aadd;
            transition: color 0.2s, border-bottom 0.2s;
        }

        .home-link:hover {
            color: #1b7ca2;
            border-bottom: 1px solid #1b7ca2;
        }
    </style>
</head>

<body>
    <div class="waiting-container">
        <div class="waiting-icon">🕰️</div>
        <div class="waiting-title">승인 대기 중입니다</div>
        <div class="waiting-message">
            <?= htmlspecialchars($message) ?><br>
        </div>
        <div class="info-text">
            관리자의 승인이 완료되면 로그인하실 수 있습니다.<br>
            승인 후 안내 메일이 발송됩니다.<br>
            빠른 처리를 위해 잠시만 기다려 주세요.
        </div>
        <a href="/" class="home-link">메인으로 돌아가기</a>
    </div>
</body>

</html>