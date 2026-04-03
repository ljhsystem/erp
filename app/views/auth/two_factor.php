<?php
// 경로: PROJECT_ROOT . '/app/views/auth/two_factor.php';
use Core\Helpers\AssetHelper;

//require_once $_SERVER['DOCUMENT_ROOT'] . '/core/Bootstrap.php';
//require_once PROJECT_ROOT . '/core/Session.php';

// ------------------------------------------------------------
// 2FA 세션 검증
// ------------------------------------------------------------
$pending = $_SESSION['pending_2fa'] ?? null;

if (
  !$pending ||
  empty($pending['user']) ||
  empty($pending['user']['id'])
) {
  header('Location: /login');
  exit;
}

$user    = $pending['user'];
$email   = $user['email'] ?? null;
$reasons = $pending['reasons'] ?? [];

// 메시지 (컨트롤러에서 전달)
$message = $_SESSION['two_factor_message'] ?? '';
unset($_SESSION['two_factor_message']);

// ------------------------------------------------------------
// 2FA 사유 한글 매핑
// ------------------------------------------------------------
$reasonLabels = [
  'force_2fa'      => '보안 정책에 따라 전 직원 2단계 인증이 필요합니다.',
  'user_2fa'       => '계정에 2단계 인증이 활성화되어 있습니다.',
  'new_device_2fa' => '새로운 기기에서 로그인 시도가 감지되었습니다.',
  'time_window'    => '허용되지 않은 시간대의 로그인 시도입니다.',
  'inactive_guard' => '장기간 미사용 계정 보호를 위해 추가 인증이 필요합니다.',
];

// 활성화된 사유만 추출
$activeReasons = [];
foreach ($reasons as $key => $enabled) {
  if (!empty($enabled) && isset($reasonLabels[$key])) {
    $activeReasons[] = $reasonLabels[$key];
  }
}
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

  <script>
    (function() {
      const codeInput = document.getElementById('code');
      const autoNote = document.getElementById('autoNote');

      // URL code 자동 입력
      try {
        // eslint-disable-next-line
        const qs = new URLSearchParams(location.search);
        const code = qs.get('code');
        if (code && /^[0-9]{4,8}$/.test(code)) {
          codeInput.value = code;
          autoNote.style.display = 'block';
        }
      } catch (e) {}

      document.getElementById('twoFactorForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const code = codeInput.value.trim();
        if (!code) {
          alert('인증 코드를 입력하세요.');
          return;
        }

        try {
          const res = await fetch('/api/2fa/verify', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              code
            })
          });

          const text = await res.text();
          let json = null;
          try {
            json = JSON.parse(text);
          } catch (e) {}

          if (!res.ok || !json) {
            alert('서버 오류가 발생했습니다.');
            return;
          }

          if (!json.success) {
            alert(json.message || '인증 실패');
            if (json.redirect) location.href = json.redirect;
            return;
          }

          location.href = json.redirect || '/dashboard';

        } catch (err) {
          console.error(err);
          alert('서버 통신 오류가 발생했습니다.');
        }
      });
    })();
  </script>

</body>

</html>