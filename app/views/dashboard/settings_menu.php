<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings_menu.php'
// 설명: 좌측 설정 메뉴 (기초정보 / 직원관리 / 시스템설정)
//
// settings.php에서 사용하는 파라미터는 cat / sub 구조이므로
// 본 메뉴도 tab → cat 으로 통일하여 동작하도록 수정함.
// -----------------------------------------------------------

// 메뉴 목록 (외부에서 주입 가능)
$tabMap = (isset($tabMap) && is_array($tabMap))
    ? $tabMap
    : [
        'base-info' => '기초정보 관리',
        'organization'  => '조직 관리',
        'system'    => '시스템 설정'
    ];

// 현재 활성화된 cat (외부에서 $activeCat 전달 시 우선 적용)
$activeCat = $activeCat ?? ($_GET['cat'] ?? 'employee');
?>
<div class="col-md-3 setting-menu">
  <div class="list-group" id="settings-cat">
    <?php foreach ($tabMap as $key => $label): ?>
      <a class="list-group-item list-group-item-action <?= ($activeCat === $key) ? 'active' : '' ?>"
         href="/dashboard/settings?cat=<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
         id="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>-cat">
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
