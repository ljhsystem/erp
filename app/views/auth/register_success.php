<?php
// 경로: PROJECT_ROOT . '/app/views/auth/register_success.php'
use Core\Helpers\AssetHelper;
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>회원가입 완료</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>
    <style>
        body {
            background: #f4f6fb;
        }

        .success-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(60, 80, 120, 0.10);
            padding: 40px 32px 32px 32px;
            max-width: 380px;
            width: 100%;
            text-align: center;
            border: 1px solid #f0f2f5;
        }

        .success-icon {
            font-size: 54px;
            margin-bottom: 18px;
            display: block;
        }

        .success-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #222;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .success-desc {
            font-size: 1.05rem;
            color: #666;
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .btn-group {
            gap: 12px;
        }

        .btn-success {
            background: #21926b;
            border: none;
            font-weight: 500;
        }

        .btn-success:hover {
            background: #176e4f;
        }

        .btn-outline-secondary {
            font-weight: 500;
            border: 1.5px solid #d1d5db;
        }

        @media (max-width: 500px) {
            .success-card {
                padding: 28px 8px 24px 8px;
                max-width: 98vw;
            }

            .success-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>

<body>
    <div class="success-wrapper">
        <div class="success-card">
            <span class="success-icon">🎉</span>
            <div class="success-title">회원가입이<br>완료되었습니다!</div>
            <div class="success-desc">이제 로그인하여 서비스를<br>이용하실 수 있습니다.</div>
            <div class="d-flex btn-group justify-content-center">
                <a href="/login" class="btn btn-success px-4 py-2">로그인</a>
                <a href="/" class="btn btn-outline-secondary px-4 py-2">메인으로</a>
            </div>
        </div>
    </div>
    <?= AssetHelper::js('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js') ?>
</body>

</html>