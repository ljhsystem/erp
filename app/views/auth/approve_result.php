<?php
declare(strict_types=1);

use Core\Helpers\AssetHelper;

$message = $message ?? '승인 요청을 처리할 수 없습니다.';
$isSuccess = $isSuccess ?? false;
$approvedBy = $approvedBy ?? null;
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>회원 승인 결과</title>
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
    </style>
</head>

<body>
    <div class="card shadow-sm" style="max-width:480px; width:90%;">
        <div class="card-header bg-primary text-white">회원 승인 결과</div>
        <div class="card-body p-4 text-center">
            <div class="fs-1 mb-3"><?= $isSuccess ? '🎉' : '⚠️' ?></div>
            <p class="fs-5 mb-3"><?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') ?></p>

            <?php if (!empty($approvedBy)): ?>
                <p class="text-muted small">승인자: <?= htmlspecialchars((string)$approvedBy, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <div class="d-grid gap-2 mt-4">
                <a href="/" class="btn btn-primary">메인으로 돌아가기</a>
                <button type="button" class="btn btn-outline-danger" onclick="window.close()">창 닫기</button>
            </div>
        </div>
    </div>
</body>

</html>
