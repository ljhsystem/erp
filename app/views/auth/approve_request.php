<?php
// 경로: PROJECT_ROOT . '/app/views/auth/approve_request.php'
declare(strict_types=1);

use Core\Helpers\AssetHelper;

ob_start();
header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/storage/logs/php_errors.log');

try {
    $pdo = \Core\Database::getInstance()->getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    exit("<h3>DB 연결 실패</h3><p>{$e->getMessage()}</p>");
}

$userCode = $_GET['code'] ?? '';
$message  = '';
$user     = null;

try {
    if (!$userCode) {
        throw new Exception('잘못된 접근입니다. (code 파라미터 없음)');
    }

    $sql = "
        SELECT u.id, u.code, u.username, u.approved, u.created_at,
               p.employee_name, p.profile_image
          FROM auth_users u
     LEFT JOIN user_employees p ON p.user_id = u.id
         WHERE u.code = ?
         LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userCode]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $createdAt = $user['created_at'] ?? null;
    $formattedDate = '';
    if ($createdAt) {
        $dt = new DateTime($createdAt);
        $weekdays = ['일', '월', '화', '수', '목', '금', '토'];
        $dayName = $weekdays[(int)$dt->format('w')];
        $formattedDate = $dt->format("Y-m-d") . "({$dayName}) " . $dt->format("H:i:s");
    }

    if (!$user) {
        throw new Exception('해당 사용자를 찾을 수 없습니다.');
    }

    // approved 값 보정 (NULL, 공백 방지)
    $user['approved'] = (int)($user['approved'] ?? 0);

    if ($user['approved'] === 1) {
        $message = '✅ 이미 승인된 사용자입니다.';
    } else {
        $message = '🟢 승인 대기중인 사용자입니다.';
    }
} catch (Throwable $e) {
    $message = '❌ 오류 발생: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>회원 승인 요청</title>
    <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css') ?>
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Pretendard', 'Noto Sans KR', sans-serif;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            background: #fff;
        }

        .card-header {
            background: linear-gradient(135deg, #007bff, #00bfff);
            color: #fff;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            font-size: 1.3rem;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }

        .profile-img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
            margin-bottom: 10px;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            font-weight: 600;
            border-radius: 8px;
            transition: 0.3s;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #218838, #17a589);
            transform: translateY(-1px);
        }

        .btn-secondary,
        .btn-outline-danger {
            border-radius: 8px;
        }
    </style>
</head>

<body>
    <div class="card text-center" style="max-width:480px; width:90%;">
        <div class="card-header">회원 승인 요청</div>
        <div class="card-body p-4">

            <?php if ($user): ?>
                <div class="alert <?= $user['approved'] === 1 ? 'alert-info' : 'alert-warning' ?> mb-3">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>

                <?php if (!empty($user['profile_image'])): ?>
                    <img src="<?= htmlspecialchars($user['profile_image']) ?>" class="profile-img" alt="프로필">
                <?php endif; ?>

                <h5 class="mb-2"><strong><?= htmlspecialchars($user['employee_name'] ?: '이름 없음') ?></strong></h5>
                <p class="text-muted mb-2">아이디: <?= htmlspecialchars($user['username']) ?></p>
                <p class="text-muted">가입일: <?= htmlspecialchars($formattedDate) ?></p>


                <!-- action을 컨트롤러 승인 엔드포인트로 변경: /approve_user -->
                <form method="post" action="<?= 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/approve_user' ?>" class="mt-4">
                    <input type="hidden" name="code" value="<?= htmlspecialchars((string)$user['code'], ENT_QUOTES, 'UTF-8') ?>">
                    <!-- 클라이언트에서 승인자 이메일을 보내지 않음 (서버에서 토큰으로 결정) -->
                    <input type="hidden" name="approve_token" value="<?= htmlspecialchars($_GET['approve_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="d-grid gap-2">
                        <?php if ($user['approved'] == 0): ?>
                            <button type="submit" class="btn btn-success">✅ 승인하기</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-success" disabled>✅ 이미 승인됨</button>
                        <?php endif; ?>
                        <a href="/" class="btn btn-secondary">🏠 메인으로 가기</a>
                        <button type="button" class="btn btn-outline-danger" onclick="window.close()">❌ 창 닫기</button>
                    </div>
                </form>

            <?php else: ?>
                <div class="alert alert-danger mb-3"><?= htmlspecialchars($message ?: '사용자 정보를 찾을 수 없습니다.', ENT_QUOTES, 'UTF-8') ?></div>
                <a href="/" class="btn btn-secondary">메인으로</a>
            <?php endif; ?>

        </div>
    </div>
</body>

</html>