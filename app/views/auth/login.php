<?php
// 경로: PROJECT_ROOT . '/app/views/auth/login.php'
use Core\Helpers\AssetHelper;
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$message = $message ?? '';
$usernameValue = $usernameValue ?? '';
?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <title>SUKHYANG ERP 로그인</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>
  <?= AssetHelper::css('/assets/css/pages/auth/login.css') ?>
  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Pretendard', 'Noto Sans KR', sans-serif;
    }

    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    #loading {
      position: fixed;
      inset: 0;
      background: rgba(255, 255, 255, 0.85);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      display: none;
    }

    #loading .spinner-border {
      width: 3rem;
      height: 3rem;
    }

    #loading p {
      margin-top: 1rem;
      font-weight: 500;
      color: #333;
    }

    .login-box {
      max-width: 380px;
      width: 100%;
      transition: all 0.3s ease;
    }

    /* ✅ 로그인 상태 유지 강조 스타일 */
    .stay-login-label {
      font-weight: 600;
      color: #0d6efd;
    }
  </style>
</head>

<body>

  <div id="loading">
    <div class="spinner-border text-primary"></div>
    <p id="loadingText">로그인 중입니다...</p>
  </div>

  <div class="d-flex flex-column align-items-center justify-content-center vh-100">
    <div class="login-box shadow-sm p-4 bg-white rounded">
      <h4 class="text-center fw-bold mb-2">🔐 SUKHYANG ERP 로그인</h4>
      <p class="text-center text-muted small mb-4">당신의 스마트한 업무가 지금 시작됩니다.</p>

      <div id="login-message" class="alert text-center py-2 mb-3 <?= $message !== '' ? '' : 'd-none' ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
      </div>

      <form id="loginForm" autocomplete="off">
        <div class="mb-3">
          <input type="text" name="username" id="username" class="form-control"
            placeholder="아이디" required autocomplete="username"
            value="<?= htmlspecialchars($usernameValue, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="mb-3">
          <input type="password" name="password" id="password" class="form-control"
            placeholder="비밀번호" required autocomplete="current-password">
        </div>

        <div class="d-flex justify-content-between mb-3">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="rememberMe">
            <label class="form-check-label" for="rememberMe">아이디 저장</label>
          </div>
          <!-- ✅ 로그인 상태 유지 체크박스 삭제 -->
          <!-- <div class="form-check">
          <input type="checkbox" class="form-check-input" id="stayLoggedIn">
          <label class="form-check-label stay-login-label" for="stayLoggedIn">로그인 상태 유지</label>
        </div> -->
        </div>

        <button type="submit" id="btnLogin" class="btn btn-primary w-100 mb-3">
          로그인
        </button>

        <div class="d-flex justify-content-center gap-3 mb-3 small text-muted">
          <a href="/find-id">아이디 찾기</a>
          <a href="/find-password">비밀번호 찾기</a>
        </div>

        <div class="d-flex gap-2">
          <a href="/register" class="btn btn-outline-secondary w-50">회원가입</a>
          <a href="/" class="btn btn-light w-50">취소</a>
        </div>
      </form>
    </div>
  </div>


  <?= AssetHelper::js('/assets/js/pages/auth/login.js') ?>

</body>

</html>
