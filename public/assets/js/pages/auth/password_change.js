document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('passwordChangeForm');
  const btnLater = document.getElementById('btnLater');
  const options = window.AUTH_PASSWORD_CHANGE || { isForceChange: false };

  if (!form) {
    return;
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
      alert('새 비밀번호가 일치하지 않습니다.');
      return;
    }

    const payload = {
      new_password: newPassword,
      confirm_password: confirmPassword
    };

    if (!options.isForceChange) {
      payload.current_password = currentPassword;
    }

    const response = await fetch('/api/auth/password/change', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    if (!response.ok || !result.success) {
      alert(result.message || '비밀번호 변경에 실패했습니다.');
      return;
    }

    alert(result.message || '비밀번호가 변경되었습니다.');
    window.location.href = result.redirect || '/dashboard';
  });

  if (!btnLater) {
    return;
  }

  btnLater.addEventListener('click', async () => {
    const response = await fetch('/api/auth/password/change-later', {
      method: 'POST',
      credentials: 'include'
    });

    const result = await response.json();
    if (!response.ok || !result.success) {
      alert(result.message || '처리에 실패했습니다.');
      return;
    }

    window.location.href = result.redirect || '/dashboard';
  });
});
