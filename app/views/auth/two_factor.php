<?php
// 경로: PROJECT_ROOT . '/app/views/auth/two_factor.php';
use Core\Helpers\AssetHelper;
$message = $message ?? '';
$email = $email ?? null;
$activeReasons = $activeReasons ?? [];
?>
<!doctype html>
<html lang="ko">

<head>
  <meta charset="utf-8">
  <title>2단계 인증</title>

  <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>

  <style>
    body {
      background: #f6f7fb;
    }

    .auto-note {
      color: #198754;
      display: none;
    }

    .reason-box {
      background: #f8f9fa;
      border-left: 4px solid #0d6efd;
      padding: 12px 14px;
      font-size: 0.9rem;
      border-radius: 6px;
    }
  </style>
</head>

<body class="d-flex align-items-center justify-content-center vh-100">

  <div class="card shadow-sm p-4" style="max-width:520px;width:100%;">
    <h5 class="mb-3">🔐 2단계 인증</h5>

    <?php if ($message): ?>
      <div class="alert alert-warning">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- 2FA 사유 -->
    <?php if (!empty($activeReasons)): ?>
      <div class="reason-box mb-3">
        <strong class="d-block mb-1">인증이 필요한 이유</strong>
        <ul class="mb-0 ps-3">
          <?php foreach ($activeReasons as $text): ?>
            <li><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- 코드 발송 안내 -->
    <div class="mb-3 text-muted small">
      <?php if ($email): ?>
        등록된 이메일
        <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></strong>
        로 6자리 인증 코드를 발송했습니다.
      <?php else: ?>
        인증 코드를 받을 수 없습니다. 로그인 화면으로 돌아가 주세요.
      <?php endif; ?>
    </div>

    <!-- 인증 코드 입력 -->
    <form id="twoFactorForm" autocomplete="off">
      <div class="mb-3">
        <label class="form-label">인증 코드</label>
        <input
          id="code"
          name="code"
          class="form-control"
          maxlength="6"
          inputmode="numeric"
          pattern="[0-9]{6}"
          placeholder="6자리 숫자 코드 입력"
          required>
        <div id="autoNote" class="form-text auto-note mt-2">
          URL에서 코드를 자동으로 불러왔습니다.
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100">
        인증 확인
      </button>
    </form>

    <div class="mt-3 text-center">
      <a href="/login" class="btn btn-link">로그인 화면으로 돌아가기</a>
    </div>
  </div>

  <?= AssetHelper::js('/assets/js/pages/auth/two_factor.js') ?>

</body>

</html>
