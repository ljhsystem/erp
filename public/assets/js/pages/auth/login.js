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
        if (result.mail_error) {
          showMailError(result.mail_error);
        } else {
          showMessage(result.message || '로그인 실패', 'danger');
        }
        return;
      }

      showLoading(result.reason === 'password_expired' ? '비밀번호 변경이 필요합니다...' : '이동 중입니다...');
      window.location.href = result.redirect || '/main';
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

  function showMailError(error) {
    msgBox.className = 'alert alert-danger text-start py-3 mb-3';
    msgBox.replaceChildren();

    const title = document.createElement('strong');
    title.className = 'd-block mb-2';
    title.textContent = error.title || '인증 메일을 발송하지 못했습니다.';
    msgBox.appendChild(title);

    const detail = document.createElement('div');
    detail.textContent = error.detail || '메일 발송 설정에 문제가 있습니다. 관리자에게 문의해 주세요.';
    msgBox.appendChild(detail);

    if (error.notice) {
      const notice = document.createElement('div');
      notice.className = 'small mt-2';
      notice.textContent = error.notice;
      msgBox.appendChild(notice);
    }

    if (error.can_manage_google_app_password && error.management_url) {
      const actions = document.createElement('div');
      actions.className = 'd-flex flex-wrap gap-2 mt-3';

      const managementLink = document.createElement('a');
      managementLink.className = 'btn btn-sm btn-outline-danger';
      managementLink.href = error.management_url;
      managementLink.target = '_blank';
      managementLink.rel = 'noopener noreferrer';
      managementLink.textContent = 'Google 앱 비밀번호 관리';
      actions.appendChild(managementLink);

      const retryButton = document.createElement('button');
      retryButton.type = 'button';
      retryButton.className = 'btn btn-sm btn-danger';
      retryButton.textContent = '다시 시도';
      retryButton.addEventListener('click', () => form.requestSubmit());
      actions.appendChild(retryButton);

      msgBox.appendChild(actions);
    }

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
