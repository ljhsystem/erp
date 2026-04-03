<?php
// 📄 /app/views/error/403.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/Session.php';
$isLoggedIn = \Core\Session::isAuthenticated();

$pageTitle = "403 - 접근이 거부되었습니다";
ob_start();
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
    line-height: 1.6;
}
.error-page .btn {
    padding: 10px 24px;
    font-size: 1rem;
    border-radius: 6px;
}
</style>

<div class="error-page">
  <h1>403</h1>
  <p>접근 권한이 없습니다.<br>관리자에게 문의하세요.</p>
  <a href="<?= $isLoggedIn ? '/dashboard' : '/' ?>" class="btn btn-warning">🔐 홈으로</a>
</div>

<?php
$content = ob_get_clean();

// ⭐ 로그인 여부 상관없이 같은 레이아웃을 사용해야 정상 동작
include __DIR__ . '/../layout/header.php';
echo $content;
include __DIR__ . '/../layout/footer.php';
