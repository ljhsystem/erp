<?php
// 경로: PROJECT_ROOT . '/app/views/auth/find_id_result.php'
use Core\Helpers\AssetHelper;


// 1. POST 체크 (직접 접근 방지)
$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');

if (!$name || !$email) {
  header('Location: /find-id');
  exit;
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <title>아이디 찾기 결과</title>
  <?= AssetHelper::css('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css') ?>
  <?= AssetHelper::css('/assets/css/pages/auth/login.css') ?>
</head>

<body>
  <div class="login-wrapper">
    <div class="login-box">
      <h3 class="text-center fw-bold mb-3">🔎 아이디 찾기 결과</h3>

      <?php
      $found = false;
      $username = '';

      try {
        $db = \Core\Database::getInstance();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("
              SELECT a.username
              FROM auth_users AS a
              INNER JOIN user_employees AS p ON a.id = p.user_id
              WHERE p.employee_name = ? AND a.email = ?
              LIMIT 1
          ");
        $stmt->execute([$name, $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
          $found = true;
          $username = htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8');
        }
      } catch (Exception $e) {
        echo '<div class="alert alert-danger text-center">DB 오류: ' . htmlspecialchars($e->getMessage()) . '</div>';
      }
      ?>

      <?php if ($found): ?>
        <div class="alert alert-success text-center">
          <strong>아이디:</strong> <?= $username ?>
        </div>
      <?php else: ?>
        <div class="alert alert-warning text-center">
          입력하신 정보와 일치하는 아이디가 없습니다.<br>
          이름과 이메일을 다시 확인해 주세요.
        </div>
      <?php endif; ?>

      <a href="/login" class="btn btn-primary w-100 mt-3">로그인으로 돌아가기</a>
    </div>
  </div>
</body>

</html>