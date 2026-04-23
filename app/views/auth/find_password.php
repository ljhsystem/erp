<?php
// 경로: PROJECT_ROOT . '/app/views/auth/loginfind_passwordphp.php'
use Core\Helpers\AssetHelper;
?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <title>비밀번호 찾기 | SUKHYANG ERP</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>
  <?= AssetHelper::css('/assets/css/pages/auth/login.css') ?>
</head>

<body>
  <div class="login-wrapper">
    <div class="login-box">
      <h3 class="text-center fw-bold mb-3">🔑 비밀번호 찾기</h3>
      <form method="post" action="/find-password-result">
        <div class="mb-3">
          <input type="text" name="username" class="form-control" placeholder="아이디" required>
        </div>
        <div class="mb-3">
          <input type="email" name="email" class="form-control" placeholder="이메일" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-3">비밀번호 찾기</button>
        <div class="d-flex gap-2">
          <a href="/login" class="btn btn-outline-secondary w-50">로그인</a>
          <a href="/find-id" class="btn btn-light w-50">아이디 찾기</a>
        </div>
      </form>
    </div>
  </div>
</body>

</html>