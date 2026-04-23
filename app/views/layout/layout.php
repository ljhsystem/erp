<?php

use Core\Helpers\AssetHelper;
use Core\Helpers\ConfigHelper;

$layoutOptions = $layoutOptions ?? [];
$layoutOptions = array_merge([
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'default',
], $layoutOptions);

$expireTime = $expireTime ?? null;
$sessionTimeout = $sessionTimeout ?? null;
$sessionAlert = $sessionAlert ?? null;
$sessionSound = $sessionSound ?? 'default.mp3';
$userId = $userId ?? '';

$uiSettings = [
    'ui_skin' => ConfigHelper::system('ui_skin', 'default'),
    'theme_mode' => ConfigHelper::system('theme_mode', 'light'),
    'primary_color' => ConfigHelper::system('primary_color', '#0d6efd'),
    'sidebar_color' => ConfigHelper::system('sidebar_color', '#ffffff'),
    'text_color' => ConfigHelper::system('text_color', '#212529'),
    'font_family' => ConfigHelper::system('site_font_family', ''),
    'ui_density' => ConfigHelper::system('ui_density', 'normal'),
    'font_scale' => ConfigHelper::system('font_scale', 'normal'),
    'table_density' => ConfigHelper::system('table_density', 'normal'),
    'card_density' => ConfigHelper::system('card_density', 'normal'),
    'radius_style' => ConfigHelper::system('radius_style', 'rounded'),
    'button_style' => ConfigHelper::system('button_style', 'solid'),
    'row_focus' => ConfigHelper::system('row_focus', 'normal'),
    'link_underline' => ConfigHelper::system('link_underline', 'off'),
    'icon_scale' => ConfigHelper::system('icon_scale', 'normal'),
    'alert_style' => ConfigHelper::system('alert_style', 'normal'),
    'motion_mode' => ConfigHelper::system('motion_mode', 'on'),
    'sidebar_default' => ConfigHelper::system('sidebar_default', 'expanded'),
];
$ui = $uiSettings;
?>

<?php if ($layoutOptions['header']) : ?>
    <?php include __DIR__ . '/header.php'; ?>
<?php endif; ?>

<body data-userid="<?= htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8') ?>"
data-density="<?= htmlspecialchars($ui['ui_density'], ENT_QUOTES, 'UTF-8') ?>"
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

<div id="global-loading-overlay">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<?php if ($layoutOptions['navbar']) : ?>
    <?php include __DIR__ . '/navbar.php'; ?>
<?php endif; ?>

<?php if ($layoutOptions['sidebar']) : ?>
<div class="d-flex app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content flex-grow-1">
        <?php include __DIR__ . '/breadcrumb.php'; ?>
        <?= $content ?? '' ?>
    </main>
</div>
<?php else : ?>
<main class="main-content single-layout">
    <?php include __DIR__ . '/breadcrumb.php'; ?>
    <?= $content ?? '' ?>
</main>
<?php endif; ?>

<?php if ($layoutOptions['footer']) : ?>
    <?php include __DIR__ . '/footer.php'; ?>
<?php endif; ?>

<script>
  (function () {
      var el = document.getElementById('session-timer');
      if (!el) return;

      el.setAttribute('data-expire-time', <?= json_encode($expireTime) ?>);
      el.setAttribute('data-session-timeout', <?= json_encode($sessionTimeout) ?>);
      el.setAttribute('data-session-alert', <?= json_encode($sessionAlert) ?>);
      el.setAttribute('data-session-sound', <?= json_encode($sessionSound) ?>);
  })();
</script>

<?= AssetHelper::js('/assets/js/pages/layout/layout.js') ?>
<?= AssetHelper::js('/assets/js/components/navbar.js') ?>
<?= AssetHelper::js('/assets/js/pages/layout/sidebar.js') ?>
<?= AssetHelper::js('/assets/js/common/datatables.error.js') ?>
<?= !empty($pageScripts) ? $pageScripts : '' ?>

</body>
