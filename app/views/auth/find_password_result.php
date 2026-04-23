<?php
// 경로: PROJECT_ROOT . '/app/views/auth/find_password_result.php'
use Core\Helpers\AssetHelper;
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>비밀번호 찾기 결과</title>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>
    <?= AssetHelper::css('/assets/css/pages/auth/login.css') ?>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-box">
            <h3 class="text-center fw-bold mb-3">🔑 비밀번호 찾기 결과</h3>
            <?php

            // 🔹 입력값 정리 및 기본 변수 선언
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $errorMsg = '';
            $successMsg = '';

            if ($username && $email) {
                try {
                    $db = \Core\Database::getInstance();
                    $conn = $db->getConnection();

                    // ✅ 사용자 존재 여부 확인 (auth_users 기준)
                    $stmt = $conn->prepare("
                  SELECT id
                  FROM auth_users
                  WHERE username = ? AND email = ?
                  LIMIT 1
              ");
                    $stmt->execute([$username, $email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        // ✅ 임시 비밀번호 생성 (8자리 난수)
                        $tempPassword = bin2hex(random_bytes(4));
                        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

                        // ✅ DB 업데이트
                        $update = $conn->prepare("
                      UPDATE auth_users
                      SET password = ?
                      WHERE id = ?
                  ");
                        $update->execute([$hashedPassword, $user['id']]);

                        // ✅ 결과 메시지
                        $successMsg = "임시 비밀번호가 발급되었습니다.<br>임시 비밀번호: <strong>{$tempPassword}</strong><br><small>로그인 후 반드시 새 비밀번호로 변경해 주세요.</small>";
                    } else {
                        $errorMsg = "입력하신 정보와 일치하는 계정이 없습니다.<br>아이디와 이메일을 다시 확인해 주세요.";
                    }
                } catch (Exception $e) {
                    $errorMsg = "⚠ DB 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.";
                }
            } else {
                $errorMsg = "이메일과 아이디를 모두 입력해 주세요.";
            }

            // ✅ 결과 출력
            if (!empty($successMsg)) {
                echo "<div class='alert alert-success text-center'>{$successMsg}</div>";
            } else {
                echo "<div class='alert alert-warning text-center'>{$errorMsg}</div>";
            }
            ?>

            <a href="/login" class="btn btn-primary w-100 mt-3">로그인으로 돌아가기</a>
        </div>
    </div>
</body>

</html>