<?php
use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
$pageStyles = AssetHelper::css('/assets/css/pages/main/notifications.css');
$pageScripts = AssetHelper::js('/assets/js/pages/main/notifications.js');
?>
<main class="notifications-main" aria-labelledby="notificationCenterTitle">
    <div class="notifications-heading">
        <div>
            <h5 id="notificationCenterTitle" class="mb-1" translate="no"><?= htmlentities($pageTitle, ENT_QUOTES, 'UTF-8') ?></h5>
            <p class="text-muted mb-0">결재와 교육 등 내 업무 알림을 확인합니다.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="notificationReadAll">전체 읽음</button>
    </div>
    <section class="card notifications-card" aria-live="polite">
        <div class="card-header notifications-summary">
            <span>전체 <strong id="notificationTotal">0</strong>건</span>
            <span>미읽음 <strong id="notificationUnread">0</strong>건</span>
        </div>
        <div id="notificationCenterList" class="list-group list-group-flush">
            <div class="notifications-empty">알림을 불러오는 중입니다.</div>
        </div>
        <div class="card-footer notifications-pagination">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="notificationPrev">이전</button>
            <span id="notificationPage">1 / 1</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="notificationNext">다음</button>
        </div>
    </section>
</main>
