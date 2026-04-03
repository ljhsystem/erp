<?php
// 경로: PROJECT_ROOT . '/app/views/layout/layout.php';
use Core\Helpers\AssetHelper;
use Core\Helpers\ConfigHelper;

$layoutOptions = $layoutOptions ?? [];

$layoutOptions = array_merge([
    'header'  => true,
    'navbar'  => true,
    'sidebar' => true,   // 기본은 ON
    'footer'  => true,
    'wrapper' => 'default',
], $layoutOptions);


// 세션 값들을 PHP에서 받아옵니다.
$expireTime = $expireTime
    ?? ($_SESSION['expire_time'] ?? null);

$sessionTimeout = $sessionTimeout
    ?? ($_SESSION['session_timeout'] ?? null);

$sessionAlert = $sessionAlert
    ?? ($_SESSION['session_alert'] ?? null);

$sessionSound = $sessionSound
    ?? ($_SESSION['sound'] ?? 'default.mp3');


// UI 설정 값들을 배열로 설정
$uiSettings = [
    'ui_skin'       => ConfigHelper::system('ui_skin', 'default'),
    'theme_mode'    => ConfigHelper::system('theme_mode', 'light'),
    'font_family'   => ConfigHelper::system('site_font_family', ''),
    'font_scale'    => ConfigHelper::system('font_scale', 'normal'),
    'table_density' => ConfigHelper::system('table_density', 'normal'),
    'card_density'  => ConfigHelper::system('card_density', 'normal'),
    'radius_style'  => ConfigHelper::system('radius_style', 'rounded'),
    'button_style'  => ConfigHelper::system('button_style', 'solid'),
    'row_focus'     => ConfigHelper::system('row_focus', 'normal'),
    'link_underline'=> ConfigHelper::system('link_underline', 'off'),
    'icon_scale'    => ConfigHelper::system('icon_scale', 'normal'),
    'alert_style'   => ConfigHelper::system('alert_style', 'normal'),
    'motion_mode'   => ConfigHelper::system('motion_mode', 'on'),
    'sidebar_default'=> ConfigHelper::system('sidebar_default', 'expanded'),
];
$ui = $uiSettings;

?>

<?php if ($layoutOptions['header']) : ?>
    <?php include __DIR__ . '/header.php'; ?>
<?php endif; ?>

<body data-userid="<?= htmlspecialchars($_SESSION['user']['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"

class="
  <?= ($ui['sidebar_default'] === 'collapsed') ? 'is-sidebar-collapsed' : '' ?>
  skin-<?= htmlspecialchars($ui['ui_skin'], ENT_QUOTES, 'UTF-8') ?>
  theme-<?= htmlspecialchars($ui['theme_mode'], ENT_QUOTES, 'UTF-8') ?>
  table-density-<?= htmlspecialchars($ui['table_density'], ENT_QUOTES, 'UTF-8') ?>
  font-scale-<?= htmlspecialchars($ui['font_scale'], ENT_QUOTES, 'UTF-8') ?>
  card-density-<?= htmlspecialchars($ui['card_density'], ENT_QUOTES, 'UTF-8') ?>
  radius-<?= htmlspecialchars($ui['radius_style'], ENT_QUOTES, 'UTF-8') ?>
  button-<?= htmlspecialchars($ui['button_style'], ENT_QUOTES, 'UTF-8') ?>
  row-focus-<?= htmlspecialchars($ui['row_focus'], ENT_QUOTES, 'UTF-8') ?>
  link-underline-<?= htmlspecialchars($ui['link_underline'], ENT_QUOTES, 'UTF-8') ?>
  icon-scale-<?= htmlspecialchars($ui['icon_scale'], ENT_QUOTES, 'UTF-8') ?>
  alert-style-<?= htmlspecialchars($ui['alert_style'], ENT_QUOTES, 'UTF-8') ?>
  motion-<?= htmlspecialchars($ui['motion_mode'], ENT_QUOTES, 'UTF-8') ?>

  <?= ($layoutOptions['sidebar'] === false) ? 'no-sidebar' : '' ?>  
">

<!-- ⭐ 글로벌 로딩 스피너 -->
<div id="global-loading-overlay">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<!-- 네비게이션 바 -->
<?php if ($layoutOptions['navbar']) : ?>
    <?php include __DIR__ . '/navbar.php'; ?>
<?php endif; ?>


<!-- 사이드 바 -->
<?php if ($layoutOptions['sidebar']) : ?>
<div class="d-flex">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content flex-grow-1">
        <?= $content ?? '' ?>
    </main>
</div>
<?php else : ?>
<main class="main-content single-layout">
    <?= $content ?? '' ?>
</main>
<?php endif; ?>


<!-- 푸터 -->
<?php if ($layoutOptions['footer']) : ?>
    <?php include __DIR__ . '/footer.php'; ?>
<?php endif; ?>



<!-- 세션 타이머 데이터 전달 -->
<script>
  (function () {
      var el = document.getElementById('session-timer');
      if (!el) return;

      // PHP 값 전달 시 json_encode() 사용하여 안전하게 전달
      el.setAttribute('data-expire-time', <?= json_encode($expireTime) ?>);
      el.setAttribute('data-session-timeout', <?= json_encode($sessionTimeout) ?>);
      el.setAttribute('data-session-alert', <?= json_encode($sessionAlert) ?>);  // 주석 해제 및 전달
      el.setAttribute('data-session-sound', <?= json_encode($sessionSound) ?>);
  })();
</script>

<!-- 레이아웃 공용 스크립트 -->
<?= AssetHelper::js('/assets/js/pages/layout/layout.js') ?>
<?= AssetHelper::js('/assets/js/pages/layout/sidebar.js') ?>

<!-- DataTables 에러 핸들러 -->
<?= AssetHelper::js('/assets/js/common/datatables.error.js') ?>

<!-- 페이지 개별 스크립트 -->
<?= !empty($pageScripts) ? $pageScripts : '' ?>

</body>
