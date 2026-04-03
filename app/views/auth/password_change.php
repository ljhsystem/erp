<?php
// 경로: PROJECT_ROOT . '/app/views/auth/password_change.php'
use Core\Helpers\AssetHelper;
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$message = $_SESSION['password_message'] ?? '';
unset($_SESSION['password_message']);
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>비밀번호 변경 안내 - SUKHYANG ERP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css') ?>

    <style>
        body {
            background: #f4f6fb;
            font-family: 'Pretendard', 'Noto Sans KR', sans-serif;
        }

        .wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            max-width: 520px;
            width: 100%;
            border-radius: 18px;
            border: none;
            box-shadow: 0 8px 32px rgba(60, 80, 120, 0.12);
        }

        .card-header {
            background: linear-gradient(135deg, #0d6efd, #0b5ed7);
            color: #fff;
            font-size: 1.25rem;
            font-weight: 700;
            padding: 1.2rem 1.5rem;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
        }

        .lock-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .desc {
            font-size: 0.95rem;
            color: #555;
            line-height: 1.6;
        }

        .form-label {
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 4px;
        }

        .policy {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .btn-primary {
            background: #0d6efd;
            border: none;
            font-weight: 600;
        }

        .btn-outline-secondary {
            font-weight: 500;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <div class="card">
            <div class="card-header text-center">
                🔒 비밀번호 변경 안내
            </div>

            <div class="card-body p-4">

                <div class="text-center mb-3">
                    <div class="lock-icon">🔐</div>
                    <h5 class="fw-bold mb-2">
                        소중한 개인정보 보호를 위해<br>
                        비밀번호를 지금 변경해 주세요.
                    </h5>
                    <p class="desc">
                        보안 정책에 따라 일정 기간 동안<br>
                        비밀번호를 변경하지 않은 계정은<br>
                        비밀번호 변경이 필요합니다.
                    </p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-warning text-center py-2">
                        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form id="passwordChangeForm" autocomplete="off">
                    <?php if (!$isForceChange): ?>
                        <div class="mb-3">
                            <label class="form-label">현재 비밀번호</label>
                            <input type="password" id="current_password" class="form-control">
                        </div>
                    <?php endif; ?>


                    <div class="mb-3">
                        <label class="form-label">새 비밀번호</label>
                        <input type="password" id="new_password" class="form-control" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">새 비밀번호 확인</label>
                        <input type="password" id="confirm_password" class="form-control" required>
                    </div>

                    <div class="d-grid gap-2">
                        <!-- ✔ 변경만 submit -->
                        <button type="submit" class="btn btn-primary py-2">
                            비밀번호 변경하기
                        </button>
                    </div>
                </form>

                <!-- ✔ form 밖 -->
                <div class="d-grid gap-2 mt-2">
                    <button type="button" id="btnLater" class="btn btn-outline-secondary py-2">
                        다음에 변경하기
                    </button>
                </div>



            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            const form = document.getElementById('passwordChangeForm');
            const btnLater = document.getElementById('btnLater');

            if (!form) return;

            // 🔥 현재 비밀번호 input 존재 여부로 강제 변경 판단
            const currentEl = document.getElementById('current_password');
            const isForceChange = !currentEl; // 없으면 강제 변경


            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const currentEl = document.getElementById('current_password');
                const isForceChange = !currentEl; // ⭐ 핵심

                const currentPassword = currentEl ? currentEl.value.trim() : '';
                const newPassword = document.getElementById('new_password').value.trim();
                const confirmPassword = document.getElementById('confirm_password').value.trim();

                // ✅ 필수값 검사
                if (!newPassword || !confirmPassword || (!isForceChange && !currentPassword)) {
                    return alert('모든 항목을 입력해 주세요.');
                }

                if (newPassword !== confirmPassword) {
                    return alert('새 비밀번호가 일치하지 않습니다.');
                }

                const payload = {
                    new_password: newPassword,
                    confirm_password: confirmPassword
                };

                if (!isForceChange) {
                    payload.current_password = currentPassword;
                }

                const res = await fetch('/api/auth/password/change', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    alert(data.message || '비밀번호 변경에 실패했습니다.');
                    return;
                }

                alert('비밀번호가 성공적으로 변경되었습니다.');
                window.location.href = data.redirect || '/dashboard';
            });


            // ⏳ 나중에 변경
            if (btnLater) {
                btnLater.addEventListener('click', async () => {
                    const res = await fetch('/api/auth/password/change-later', {
                        method: 'POST',
                        credentials: 'include'
                    });

                    const data = await res.json();

                    if (!data.success) {
                        alert(data.message || '처리 실패');
                        return;
                    }

                    window.location.href = data.redirect || '/dashboard';
                });
            }

        });
    </script>


</body>

</html>