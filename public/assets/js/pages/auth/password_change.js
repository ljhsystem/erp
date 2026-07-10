document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('passwordChangeForm');
  const btnLater = document.getElementById('btnLater');
  const options = window.AUTH_PASSWORD_CHANGE || { isForceChange: false };
  const DEFAULT_ERROR_MESSAGE = '처리 중 오류가 발생했습니다.';

  if (!form) {
    return;
  }

  async function parseApiResponse(response) {
    const contentType = String(response.headers.get('content-type') || '').toLowerCase();
    const rawText = await response.text();
    let result = null;

    if (rawText !== '' && contentType.includes('application/json')) {
      try {
        result = JSON.parse(rawText);
      } catch (error) {
        result = null;
      }
    }

    if (!response.ok) {
      return {
        ok: false,
        result,
        message: result?.message || DEFAULT_ERROR_MESSAGE
      };
    }

    if (!result || typeof result !== 'object') {
      return {
        ok: false,
        result: null,
        message: DEFAULT_ERROR_MESSAGE
      };
    }

    return {
      ok: Boolean(result.success),
      result,
      message: result.message || DEFAULT_ERROR_MESSAGE
    };
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const currentPassword = document.getElementById('current_password')?.value.trim() || '';
    const newPassword = document.getElementById('new_password')?.value.trim() || '';
    const confirmPassword = document.getElementById('confirm_password')?.value.trim() || '';

    if (!newPassword || !confirmPassword || (!options.isForceChange && !currentPassword)) {
      alert('모든 항목을 입력해 주세요.');
      return;
    }

    if (newPassword !== confirmPassword) {
      alert('새 비밀번호와 확인 값이 일치하지 않습니다.');
      return;
    }

    const payload = {
      new_password: newPassword,
      confirm_password: confirmPassword
    };

    if (!options.isForceChange) {
      payload.current_password = currentPassword;
    }

    try {
      const response = await fetch('/api/auth/password/change', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(payload)
      });

      const parsed = await parseApiResponse(response);
      if (!parsed.ok) {
        alert(parsed.message || '비밀번호 변경 중 오류가 발생했습니다.');
        return;
      }

      alert(parsed.result?.message || '비밀번호가 변경되었습니다.');
      window.location.href = parsed.result?.redirect || '/dashboard';
    } catch (error) {
      alert(DEFAULT_ERROR_MESSAGE);
    }
  });

  if (!btnLater) {
    return;
  }

  btnLater.addEventListener('click', async () => {
    try {
      const response = await fetch('/api/auth/password/change-later', {
        method: 'POST',
        headers: {
          'Accept': 'application/json'
        },
        credentials: 'include'
      });

      const parsed = await parseApiResponse(response);
      if (!parsed.ok) {
        alert(parsed.message || DEFAULT_ERROR_MESSAGE);
        return;
      }

      window.location.href = parsed.result?.redirect || '/dashboard';
    } catch (error) {
      alert(DEFAULT_ERROR_MESSAGE);
    }
  });
});
