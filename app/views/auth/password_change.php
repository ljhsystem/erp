<?php
// 경로: PROJECT_ROOT . '/app/views/auth/password_change.php'
use Core\Helpers\AssetHelper;
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$message = $message ?? '';
$isForceChange = $isForceChange ?? false;
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
                <?php if ($isForceChange): ?>
                    <div class="d-grid gap-2 mt-2">
                        <button type="button" id="btnLater" class="btn btn-outline-secondary py-2">
                            다음에 변경하기
                        </button>
                    </div>
                <?php endif; ?>



            </div>
        </div>
    </div>
    <script>
        window.AUTH_PASSWORD_CHANGE = {
            isForceChange: <?= $isForceChange ? 'true' : 'false' ?>
        };
    </script>
    <?= AssetHelper::js('/assets/js/pages/auth/password_change.js') ?>


</body>

</html>
