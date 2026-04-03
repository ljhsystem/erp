<?php
// 경로: PROJECT_ROOT . '/app/views/auth/login.php'
use Core\Helpers\AssetHelper;
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$error = $_SESSION['login_message'] ?? '';
$usernameValue = $_SESSION['login_username'] ?? '';

unset($_SESSION['login_message'], $_SESSION['login_username']);
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

      <div id="login-message" class="alert text-center py-2 mb-3 d-none"></div>

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


  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const form = document.getElementById('loginForm');
      const usernameInput = document.getElementById('username');
      const passwordInput = document.getElementById('password');
      const rememberMe = document.getElementById('rememberMe');
      const msgBox = document.getElementById('login-message');
      const loading = document.getElementById('loading');
      const loadingText = document.getElementById('loadingText');
      const btnLogin = document.getElementById('btnLogin');

      // 저장된 아이디 자동입력
      const savedId = getCookie('savedId');
      if (savedId) {
        usernameInput.value = savedId;
        rememberMe.checked = true;
      }

      // 로딩 표시
      function showLoading(text = '로그인 중입니다...') {
        loadingText.textContent = text;
        loading.style.display = 'flex';
        btnLogin.disabled = true;
      }

      function hideLoading() {
        loading.style.display = 'none';
        btnLogin.disabled = false;
      }

      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const username = usernameInput.value.trim();
        const password = passwordInput.value.trim();
        // ✅ keepLogin 고정 제거
        // const keepLogin = stayLoggedIn.checked;


        if (!username || !password) {
          return showMessage('아이디와 비밀번호를 입력하세요.', 'danger');
        }

        showLoading('로그인 중입니다...');

        // 아이디 저장
        rememberMe.checked ? setCookie('savedId', username, 30) :
          setCookie('savedId', '', -1);

        try {
          // ⭐ 기존 login_api.php → 새로운 컨트롤러 라우트 '/login'
          const res = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json' // JSON 응답을 명시적으로 요청
            },
            credentials: 'include',
            // ✅ keep_login 필드 제거
            body: JSON.stringify({
              username,
              password
            })
          });

          // 안전하게 JSON 파싱 시도
          let data = null;
          try {
            data = await res.json();
          } catch (err) {
            hideLoading();
            return showMessage('서버 응답이 올바르지 않습니다.', 'danger');
          }

          // 서버가 HTTP 에러 코드를 반환한 경우(예: 4xx/5xx)
          if (!res.ok) {
            // 서버 에러 응답에 redirect가 있으면 우선 리다이렉트 (승인 대기 등)
            if (data && data.redirect) {
              window.location.href = data.redirect;
              return;
            }
            hideLoading();
            return showMessage(data.message || '로그인 실패', 'danger');
          }

          // 서버가 200 OK를 반환했지만 성공=false 이고 redirect가 있는 경우 (승인 대기 흐름)
          if (!data.success && data.redirect) {
            window.location.href = data.redirect;
            return;
          }

          // 정상 응답 처리
          if (data.success) {

            // 🔐 비밀번호 만료 → 강제 변경 페이지 (추가된 검증)
            if (data.reason === 'password_expired') {
              showLoading('비밀번호 변경이 필요합니다...');
              window.location.href = data.redirect || '/password/change';
              return;
            }

            // 🔐 서버가 redirect 를 주면 (2FA / 비밀번호만료 / 승인대기 포함)
            if (data.redirect) {
              showLoading('인증 단계로 이동합니다...');
              window.location.href = data.redirect;
              return;
            }

            // 정상 로그인
            showLoading('로그인 성공! 이동 중...');
            window.location.href = data.redirect || '/dashboard';
            return;
          }

          // 실패 응답 (성공=false, redirect 없음)
          hideLoading();
          showMessage(data.message || '로그인 실패', 'danger');

        } catch (err) {
          hideLoading();
          showMessage('⚠ 서버 통신 오류가 발생했습니다.', 'danger');
        }
      });

      function showMessage(text, type = 'info') {
        msgBox.className = 'alert alert-' + type + ' text-center py-2 mb-3';
        msgBox.textContent = text;
        msgBox.classList.remove('d-none');
        clearTimeout(msgBox._hideTimer);
        msgBox._hideTimer = setTimeout(() => msgBox.classList.add('d-none'), 3500);
      }

      function setCookie(name, value, days) {
        let expires = '';
        if (days) {
          const d = new Date();
          d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
          expires = '; expires=' + d.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) +
          expires + '; path=/; SameSite=Lax';
      }

      function getCookie(name) {
        const value = '; ' + document.cookie;
        const parts = value.split('; ' + name + '=');
        return parts.length === 2 ? decodeURIComponent(parts.pop().split(';')[0]) : '';
      }
    });
  </script>

</body>

</html>