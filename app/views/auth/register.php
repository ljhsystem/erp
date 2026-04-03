<?php
// 경로: PROJECT_ROOT . '/app/views/auth/register.php';
use Core\Helpers\AssetHelper;
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <title>회원가입 - SUKHYANG ERP</title>

  <meta name="viewport" content="width=device-width, initial-scale=1">

  <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>

  <style>
    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
      background: #f8fafc;
      font-family: 'Pretendard', 'Noto Sans KR', sans-serif;
    }

    #loading {
      position: fixed;
      inset: 0;
      background: rgba(255, 255, 255, 0.85);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      z-index: 9999;
    }

    .register-wrapper {
      width: 100%;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .register-box {
      background: #fff;
      padding: 40px 35px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 430px;
    }
  </style>
</head>

<body>

  <div id="loading">
    <div class="spinner-border text-primary"></div>
    <p id="loading-text">페이지를 불러오는 중...</p>
  </div>

  <div id="content" style="display:none;">
    <div class="register-wrapper">
      <div class="register-box">

        <h4 class="text-center fw-bold mb-1">📝 SUKHYANG ERP 회원가입</h4>
        <p class="text-center text-muted small mb-4">ERP 시스템 이용을 위한 신규 계정을 등록하세요.</p>

        <div id="register-message" class="alert text-center py-2 mb-3 d-none"></div>

        <div id="registerForm" autocomplete="off" novalidate>

          <div class="mb-3">
            <label class="form-label small text-muted">아이디</label>
            <input type="text" class="form-control" id="username" required>
          </div>

          <div class="mb-3">
            <label class="form-label small text-muted">직원 이름</label>
            <input type="text" class="form-control" id="employee_name" required>
          </div>

          <div class="mb-3">
            <label class="form-label small text-muted">이메일</label>
            <input type="email" class="form-control" id="email" required>
          </div>

          <div class="mb-3">
            <label class="form-label small text-muted">비밀번호</label>
            <input type="password" class="form-control" id="password" required>
          </div>

          <div class="mb-4">
            <label class="form-label small text-muted">비밀번호 확인</label>
            <input type="password" class="form-control" id="confirm_password" required>
          </div>

          <button type="button" id="btnRegister" class="btn btn-success w-100 py-2 fw-bold mb-3">
            가입하기
          </button>

          <div class="d-flex gap-2">
            <a href="/login" class="btn btn-outline-secondary w-50">로그인</a>
            <a href="/" class="btn btn-light w-50">취소</a>
          </div>

        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {

      const msgBox = document.getElementById('register-message');
      const loading = document.getElementById('loading');
      const content = document.getElementById('content');
      const btnRegister = document.getElementById('btnRegister');
      const loadingText = document.getElementById('loading-text');

      const usernameEl = document.getElementById('username');
      const employeeEl = document.getElementById('employee_name');
      const emailEl = document.getElementById('email');
      const passwordEl = document.getElementById('password');
      const confirmEl = document.getElementById('confirm_password');

      /* =====================================================
       * 초기 로딩 제거
       * ===================================================== */
      setTimeout(() => {
        loading.style.display = 'none';
        content.style.display = 'block';
      }, 400);

      /* =====================================================
       * 회원가입 로직
       *  - 비밀번호 정책 / 중복 검사 전부 서버에서 처리
       * ===================================================== */
      let isSubmitting = false;
      let redirecting = false;

      async function submitRegistration() {

        if (isSubmitting) return;
        isSubmitting = true;
        btnRegister.disabled = true;

        const payload = {
          username: usernameEl.value.trim(),
          employee_name: employeeEl.value.trim(),
          email: emailEl.value.trim(),
          password: passwordEl.value.trim(),
          confirm_password: confirmEl.value.trim()
        };

        /* ===============================
         * 1. 프론트 기본 검증
         * =============================== */
        if (!payload.username || !payload.employee_name ||
          !payload.email || !payload.password || !payload.confirm_password) {
          reset();
          return showMessage('모든 필드를 입력해주세요.', 'warning');
        }

        if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(payload.email)) {
          reset();
          return showMessage('올바른 이메일 주소를 입력해주세요.', 'warning');
        }

        if (payload.password !== payload.confirm_password) {
          reset();
          return showMessage('비밀번호가 일치하지 않습니다.', 'danger');
        }

        /* ===============================
         * 2. 서버 요청 시작
         * =============================== */
        content.style.opacity = '0.5';
        loadingText.textContent = '회원가입을 처리 중입니다...';
        loading.style.display = 'flex';

        try {
          const res = await fetch('/api/auth/register', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(payload)
          });

          const raw = await res.text();

          // 🔥 JSON 안전 파싱
          let data;
          try {
            data = JSON.parse(raw);
          } catch (e) {
            console.error('[REGISTER] JSON parse failed');
            console.error('[REGISTER] RAW RESPONSE:', raw);
            throw new Error('서버 응답이 올바르지 않습니다.');
          }

          // 🔥 HTTP 오류 상태
          if (!res.ok) {
            showMessage(data.message || '회원가입 처리 중 오류가 발생했습니다.', 'danger');
            return;
          }

          // 🔥 성공
          if (data.success) {
            redirecting = true;
            window.location.replace('/register_success');
            return;
          }

          // 🔥 서버 검증 실패 (중복 ID / 이름 / 비밀번호 정책 등)
          showMessage(data.message || '회원가입 실패', 'danger');

        } catch (err) {
          console.error('[REGISTER ERROR]', err);
          showMessage(err.message || '서버 오류가 발생했습니다.', 'danger');

        } finally {
          if (!redirecting) reset();
        }
      }

      /* =====================================================
       * UI 복구
       * ===================================================== */
      function reset() {
        isSubmitting = false;
        btnRegister.disabled = false;
        content.style.opacity = '1';
        loading.style.display = 'none';
      }

      /* =====================================================
       * 이벤트 바인딩
       * ===================================================== */
      btnRegister.addEventListener('click', function(e) {
        e.preventDefault();
        submitRegistration();
      });

      /* =====================================================
       * 메시지 표시
       * ===================================================== */
      function showMessage(text, type = 'info') {
        msgBox.className = 'alert alert-' + type + ' text-center py-2 mb-3';
        msgBox.textContent = text;
        msgBox.classList.remove('d-none');

        clearTimeout(msgBox._timer);
        msgBox._timer = setTimeout(() => {
          msgBox.classList.add('d-none');
        }, 4000);
      }

    });
  </script>



</body>

</html>