<?php
use Core\Helpers\AssetHelper;

$alertClass = isset($alertClass) ? (string)$alertClass : 'alert-warning';
$resultHtml = isset($resultHtml) ? (string)$resultHtml : '결과를 확인할 수 없습니다.';
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>비밀번호 찾기 결과</title>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>
    <?= AssetHelper::css('/assets/css/pages/auth/login.css') ?>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-box">
            <h3 class="text-center fw-bold mb-3">비밀번호 찾기 결과</h3>
            <div class="alert <?= htmlspecialchars($alertClass, ENT_QUOTES, 'UTF-8') ?> text-center"><?= $resultHtml ?></div>

            <a href="/login" class="btn btn-primary w-100 mt-3">로그인으로 돌아가기</a>
        </div>
    </div>
</body>

</html>
