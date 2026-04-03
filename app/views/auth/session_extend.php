<?php
// 경로: PROJECT_ROOT . '/app/views/auth/session_extend.php'
use Core\Helpers\AssetHelper;
// 컨트롤러에서 전달된 $alertSound, $alertTime 사용
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>세션 연장 안내</title>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>
    <?= AssetHelper::css('/assets/css/pages/layout/session_extend.css') ?>
    <script>
        window.onload = function() {
            window.focus();
            var audio = document.getElementById('session-alert-audio');
            if (audio) {
                audio.play().catch(function() {});
            }
        };

        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'logout') {
                window.close();
            }
        });
    </script>
</head>

<body class="d-flex flex-column justify-content-center align-items-center" style="height:100vh;">
    <audio id="session-alert-audio" src="/public/sounds/<?= htmlspecialchars($alertSound) ?>" autoplay></audio>
    <div class="card p-4" style="min-width:320px;max-width:400px;">
        <h5 class="mb-3 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>세션 만료 안내</h5>
        <p class="mb-4">
            세션이 <span class="fw-bold"><?= htmlspecialchars($alertTime) ?>분</span> 후 만료됩니다.<br>
            연장 버튼을 눌러주세요.
        </p>
        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-primary px-4" onclick="extendSession()">연장</button>
            <button class="btn btn-secondary px-4" onclick="window.close()">닫기</button>
        </div>
    </div>
    <script>
        function extendSession() {
            fetch('/autologout/keepalive', {
                    credentials: 'include'
                })
                .then(response => {
                    if (!response.ok) throw new Error('HTTP 상태: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        if (window.opener) {
                            window.opener.postMessage({
                                type: 'sessionExtended',
                                expireTime: data.expire_time
                            }, '*');
                        }
                        window.close();
                    } else {
                        alert("세션 연장에 실패했습니다. 다시 로그인 해주세요.");
                        window.opener.location.href = '/auth/logout';
                        window.close();
                    }
                })
                .catch(error => {
                    alert("서버와 통신에 문제가 있습니다.\n" + error.message);
                });
        }
    </script>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css') ?>
</body>

</html>