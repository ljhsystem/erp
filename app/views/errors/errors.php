<?php
// 경로: PROJECT_ROOT . '/app/views/errors/403.php'

$isLoggedIn = (new \App\Services\Auth\AuthSessionService())->isAuthenticated();

$code = $code ?? 404;
$message = $message ?? "요청하신 페이지를 찾을 수 없습니다.";
?>
<style>
.error-page {
    max-width: 400px;
    margin: 100px auto 0 auto;
    padding: 40px 30px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    text-align: center;
}
.error-page h1 {
    font-size: 4rem;
    margin-bottom: 16px;
    color: #ff9800;
    font-weight: bold;
}
.error-page p {
    font-size: 1.1rem;
    color: #333;
    margin-bottom: 24px;
}
</style>

<div class="error-page">
  <h1><?= $code ?></h1>
  <p><?= $message ?></p>

  <!-- ⭐ 홈으로 버튼 -->
  <a href="<?= $isLoggedIn ? '/dashboard' : '/' ?>" class="btn btn-warning">
      🔐 홈으로 돌아가기
  </a>
</div>

<?php
include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/footer.php';
?>
