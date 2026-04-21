document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('twoFactorForm');
  const codeInput = document.getElementById('code');
  const autoNote = document.getElementById('autoNote');

  if (!form || !codeInput) {
    return;
  }

  try {
    const params = new URLSearchParams(window.location.search);
    const code = params.get('code');
    if (code && /^[0-9]{4,8}$/.test(code)) {
      codeInput.value = code;
      autoNote.style.display = 'block';
    }
  } catch (error) {
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const code = codeInput.value.trim();
    if (!code) {
      alert('인증 코드를 입력하세요.');
      return;
    }

    try {
      const response = await fetch('/api/2fa/verify', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ code })
      });

      const result = await response.json();
      if (!response.ok || !result.success) {
        alert(result.message || '인증에 실패했습니다.');
        if (result.redirect) {
          window.location.href = result.redirect;
        }
        return;
      }

      window.location.href = result.redirect || '/dashboard';
    } catch (error) {
      alert('서버 통신 오류가 발생했습니다.');
    }
  });
});
