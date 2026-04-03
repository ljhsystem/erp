<?php
// 경로: PROJECT_ROOT . '/app/views/auth/session_expired.php'
use Core\Helpers\AssetHelper;
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>세션 만료 안내 - SUKHYANG ERP</title>

    <!-- Bootstrap -->
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>

    <!-- Icons -->
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css') ?>

    <style>
        body {
            background: linear-gradient(135deg, #f6f8fc, #e9efff);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Pretendard, sans-serif;
        }

        .expired-box {
            background: #ffffff;
            padding: 45px 40px;
            border-radius: 18px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
            text-align: center;
            max-width: 420px;
            width: 92%;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            0% {
                opacity: 0;
                transform: translateY(25px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .expired-icon {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 15px;
            animation: pulse 1.6s infinite ease-in-out;
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

        .login-btn {
            background: #0d6efd;
            border: none;
            padding: 12px;
            font-size: 17px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .login-btn:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(13, 110, 253, 0.35);
        }

        .title-text {
            font-size: 1.4rem;
            font-weight: 700;
        }

        .description-text {
            font-size: 0.98rem;
            line-height: 1.55;
            color: #6c757d;
        }
    </style>
</head>

<body>

    <div class="expired-box">
        <div class="expired-icon">
            <i class="bi bi-clock-history"></i>
        </div>

        <h4 class="title-text text-danger mb-3">세션이 만료되었습니다</h4>

        <p class="description-text mb-4">
            일정 시간 동안 활동이 없어<br>
            <strong>자동으로 로그아웃</strong>되었습니다.<br>
            계속 이용하시려면 다시 로그인해 주세요.
        </p>

        <a class="btn login-btn w-100" href="/login">
            <i class="bi bi-box-arrow-in-right me-1"></i> 로그인하기
        </a>
    </div>

</body>

</html>