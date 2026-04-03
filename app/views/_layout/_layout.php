<?php
// 경로: PROJECT_ROOT . '/app/views/_layout/_layout.php'
use Core\Helpers\AssetHelper;
// 1. 프로젝트 루트 상수 정의
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 2));
}

// 2. 페이지 제목 설정
$pageTitle = $pageTitle ?? 'SUKHYANG ERP';

?>

<?php include PROJECT_ROOT . '/app/views/_layout/_header.php'; ?>

<body>

  <!-- 네비게이션 바 -->
  <nav class="navbar navbar-expand-sm navbar-light bg-white border-bottom shadow-sm fixed-top">
      <?php include PROJECT_ROOT . '/app/views/_layout/_navbar.php'; ?>
  </nav>

  <!-- 본문 콘텐츠 -->
  <main class="main-content">
      <?= $content ?? '' ?>
  </main>

  <!-- 고정 푸터 -->
  <footer class="fixed-bottom bg-white border-top footer text-center py-2 small text-muted">
      <?php include PROJECT_ROOT . '/app/views/_layout/_footer.php'; ?>
  </footer>

  <!-- Bootstrap JS -->
  <?= AssetHelper::js('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js') ?>

  <!-- 페이지 전용 스크립트 -->
  <?php if (!empty($pageScripts)) echo $pageScripts; ?>

</body>
</html>
