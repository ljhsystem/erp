document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('loginForm');
  const usernameInput = document.getElementById('username');
  const passwordInput = document.getElementById('password');
  const rememberMe = document.getElementById('rememberMe');
  const msgBox = document.getElementById('login-message');
  const loading = document.getElementById('loading');
  const loadingText = document.getElementById('loadingText');
  const btnLogin = document.getElementById('btnLogin');

  if (!form) {
    return;
  }

  const savedId = getCookie('savedId');
  if (savedId) {
    usernameInput.value = savedId;
    rememberMe.checked = true;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const username = usernameInput.value.trim();
    const password = passwordInput.value.trim();

    if (!username || !password) {
      showMessage('아이디와 비밀번호를 입력하세요.', 'danger');
      return;
    }

    showLoading('로그인 중입니다...');
    if (rememberMe.checked) {
      setCookie('savedId', username, 30);
    } else {
      setCookie('savedId', '', -1);
    }

    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify({ username, password })
      });

      const result = await response.json();
      if (!response.ok || !result.success) {
        if (result.redirect) {
          window.location.href = result.redirect;
          return;
        }

        hideLoading();
        showMessage(result.message || '로그인 실패', 'danger');
        return;
      }

      showLoading(result.reason === 'password_expired' ? '비밀번호 변경이 필요합니다...' : '이동 중입니다...');
      window.location.href = result.redirect || '/dashboard';
    } catch (error) {
      hideLoading();
      showMessage('서버 통신 오류가 발생했습니다.', 'danger');
    }
  });

  function showLoading(text) {
    loadingText.textContent = text;
    loading.style.display = 'flex';
    btnLogin.disabled = true;
  }

  function hideLoading() {
    loading.style.display = 'none';
    btnLogin.disabled = false;
  }

  function showMessage(text, type) {
    msgBox.className = `alert alert-${type} text-center py-2 mb-3`;
    msgBox.textContent = text;
    msgBox.classList.remove('d-none');
  }

  function setCookie(name, value, days) {
    let expires = '';
    if (days) {
      const date = new Date();
      date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
      expires = '; expires=' + date.toUTCString();
    }

    document.cookie = `${name}=${encodeURIComponent(value)}${expires}; path=/; SameSite=Lax`;
  }

  function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    return parts.length === 2 ? decodeURIComponent(parts.pop().split(';')[0]) : '';
  }
});
