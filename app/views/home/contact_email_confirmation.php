<?php
// 경로: PROJECT_ROOT . '/app/views/home/contact_email_confirmation.php';
use Core\Helpers\AssetHelper;
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>Contact Email Confirmation</title>
    <?= AssetHelper::css('/assets/css/pages/_layout/contact.css') ?>
    <style>
        .confirmation-container {
            max-width: 500px;
            margin: 80px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            padding: 40px 32px 32px 32px;
            text-align: center;
        }

        .confirmation-icon {
            font-size: 48px;
            color: #4caf50;
            margin-bottom: 16px;
        }

        .confirmation-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .confirmation-message {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 28px;
        }

        .btn-main {
            padding: 10px 32px;
            font-size: 1rem;
            border-radius: 6px;
            background: #1976d2;
            color: #fff;
            border: none;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn-main:hover {
            background: #1565c0;
        }
    </style>
    <?= AssetHelper::css('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css') ?>
</head>

<body>
    <div class="confirmation-container">
        <div class="confirmation-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="confirmation-title">Contact Email Confirmation</div>
        <div class="confirmation-message">
            문의 요청이 <strong>성공적으로 제출</strong>되었습니다.<br>
            빠른 시일 내에 연락드리겠습니다.
        </div>
        <a href="/" class="btn-main"><i class="fas fa-home"></i> 메인으로</a>
    </div>
</body>

</html>