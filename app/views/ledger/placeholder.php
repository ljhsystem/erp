<?php
use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$layoutOptions = [
    'header'  => true,
    'navbar'  => true,
    'sidebar' => true,
    'footer'  => true,
    'wrapper' => 'single',
];
$pageStyles = $pageStyles ?? '';
$pageScripts = $pageScripts ?? '';
?>
<main class="dashboard-main">
    <div class="card">
        <div class="card-body">
            <h5 class="mb-2"><?= htmlentities($pageTitle ?? '회계관리', ENT_NOQUOTES, 'UTF-8', false) ?></h5>
            <p class="text-muted mb-0">해당 회계관리 화면은 메뉴 구조 확정에 맞춰 연결되었으며, 세부 기능은 순차적으로 제공됩니다.</p>
        </div>
    </div>
</main>
