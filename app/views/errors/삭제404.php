<?php
// 경로: PROJECT_ROOT . '/app/views/error/404.php';

require_once PROJECT_ROOT . '/core/Session.php';
$isLoggedIn = \Core\Session::isAuthenticated();

$pageTitle = "404 - 페이지를 찾을 수 없습니다";

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
    color: #1976d2;
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
  <h1>404</h1>
  <p>찾고 계신 페이지를 발견할 수 없습니다.<br>입력한 주소를 다시 확인해 주세요.</p>
  <a href="<?= $isLoggedIn ? '/dashboard' : '/' ?>" class="btn btn-primary">🏠 홈으로 돌아가기</a>
</div>
<?php
$content = ob_get_clean();

if ($isLoggedIn) {
    include PROJECT_ROOT . '/app/views/layout/header.php';
    echo $content;
    include PROJECT_ROOT . '/app/views/layout/footer.php';
} else {    
    include PROJECT_ROOT . '/app/views/_layout/_layout.php';
}
?>
