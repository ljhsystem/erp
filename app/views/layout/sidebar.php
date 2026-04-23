<?php
use Core\Helpers\AssetHelper;
?>
<?= AssetHelper::css('/assets/css/pages/layout/sidebar.css') ?>

<?php
$uri = trim($_SERVER['REQUEST_URI'] ?? '', '/');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$segments = $uri === '' ? [] : explode('/', $uri);
$section = $segments[0] ?? '';

$icon = static function (string $class): string {
    return '<i class="bi ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
};

$isActiveLink = static function (string $href) use ($currentPath): bool {
    return $currentPath === $href;
};

$link = static function (string $href, string $label, string $iconClass, string $extraClass = '') use ($icon, $isActiveLink): string {
    $class = trim('nav-link ' . ($isActiveLink($href) ? 'active ' : '') . $extraClass);

    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . $icon($iconClass) . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
};
?>

<div class="sidebar <?= ($ui['sidebar_default'] === 'collapsed') ? 'collapsed' : '' ?>">
    <ul class="nav nav-pills flex-column mb-auto">
        <?php if ($section === 'dashboard'): ?>
            <li><?= $link('/dashboard', '메인 대시보드', 'bi-speedometer2') ?></li>
            <li><?= $link('/dashboard/report', '통합 보고서', 'bi-bar-chart-line') ?></li>
            <li><?= $link('/dashboard/activity', '최근 활동', 'bi-activity') ?></li>
            <li><?= $link('/dashboard/notifications', '공지사항', 'bi-megaphone') ?></li>
            <li><?= $link('/dashboard/kpi', '실적 현황', 'bi-graph-up-arrow') ?></li>
            <li><?= $link('/dashboard/calendar', '일정/캘린더', 'bi-calendar3') ?></li>
            <li><?= $link('/dashboard/settings', '설정', 'bi-gear') ?></li>
        <?php elseif ($section === 'document' || $section === 'sukhyang'): ?>
            <li><?= $link('/document', '문서 대시보드', 'bi-folder2-open') ?></li>
            <li><?= $link('/document/file_register', '문서 등록', 'bi-file-earmark-plus') ?></li>
            <li><?= $link('/document/view', '문서 상세 보기', 'bi-file-earmark-text') ?></li>
            <li><?= $link('/document/edit', '문서 수정', 'bi-pencil-square') ?></li>
            <li><?= $link('/document/stats', '문서 통계', 'bi-bar-chart') ?></li>
        <?php elseif ($section === 'approval'): ?>
            <li><?= $link('/approval', '결재 목록', 'bi-check2-square') ?></li>
            <li><?= $link('/approval/write_expenditure', '지출결의서 작성', 'bi-receipt') ?></li>
            <li><?= $link('/approval/write_purchase_request', '구매요청서', 'bi-cart-plus') ?></li>
            <li><?= $link('/approval/write_leave_request', '휴가요청서', 'bi-airplane') ?></li>
            <li><?= $link('/approval/write_trip_report', '출장보고서', 'bi-briefcase') ?></li>
            <li><?= $link('/approval/write_work_report', '업무보고서', 'bi-clipboard-data') ?></li>
            <li><?= $link('/approval/write_review_request', '선행결재요청', 'bi-search') ?></li>
            <li><?= $link('/approval/write_progress_review', '기성결재요청', 'bi-list-check') ?></li>
            <li><?= $link('/approval/write_foreign_remit', '외화송금결재요청', 'bi-currency-exchange') ?></li>
            <li><?= $link('/approval/write_free_draft', '자유양식 기안문', 'bi-file-earmark-richtext') ?></li>
            <li><?= $link('/approval/status', '결재 현황', 'bi-hourglass-split') ?></li>
        <?php endif; ?>

        <?php if ($section === 'ledger'): ?>
            <li><?= $link('/ledger', '회계관리 대시보드', 'bi-journal-text') ?></li>
            <li><?= $link('/ledger/transaction/create', '거래입력', 'bi-pencil-square') ?></li>
            <li><?= $link('/ledger/transaction', '거래내역', 'bi-list') ?></li>
            <li>
                <a href="#menu-ledger-basic" class="nav-link toggle" aria-expanded="false"><?= $icon('bi-gear') ?><span>기초정보관리</span></a>
                <ul id="menu-ledger-basic" class="collapse">
                    <li><?= $link('/ledger/accounts', '계정과목', 'bi-list-ul') ?></li>
                    <li><?= $link('/ledger/description', '적요', 'bi-chat-left-text') ?></li>
                    <li><?= $link('/ledger/voucher-types', '전표유형', 'bi-tags') ?></li>
                </ul>
            </li>
            <li>
                <a href="#menu-ledger-journal" class="nav-link toggle" aria-expanded="false"><?= $icon('bi-pencil-square') ?><span>전표입력</span></a>
                <ul id="menu-ledger-journal" class="collapse">
                    <li><?= $link('/ledger/journal', '일반전표', 'bi-journal-richtext') ?></li>
                    <li><?= $link('/ledger/input/tax_invoice', '세금계산서', 'bi-receipt-cutoff') ?></li>
                    <li><?= $link('/ledger/card/corporate', '법인카드', 'bi-credit-card') ?></li>
                    <li><?= $link('/ledger/card/personal', '경비정산', 'bi-wallet2') ?></li>
                    <li><?= $link('/ledger/bank', '입출금거래', 'bi-bank') ?></li>
                    <li><?= $link('/ledger/input/withholding', '원천징수', 'bi-percent') ?></li>
                    <li><?= $link('/ledger/input/forex', '외화전표', 'bi-currency-dollar') ?></li>
                    <li><?= $link('/ledger/journal/review', '전표검토', 'bi-search') ?></li>
                </ul>
            </li>
            <li>
                <a href="#menu-ledger-book" class="nav-link toggle" aria-expanded="false"><?= $icon('bi-book') ?><span>장부관리</span></a>
                <ul id="menu-ledger-book" class="collapse">
                    <li><?= $link('/ledger/book/journal', '분개장', 'bi-journal') ?></li>
                    <li><?= $link('/ledger/book/general', '총계정원장', 'bi-bookmarks') ?></li>
                    <li><?= $link('/ledger/book/account', '계정별원장', 'bi-collection') ?></li>
                    <li><?= $link('/ledger/book/partner', '거래처원장', 'bi-people') ?></li>
                    <li><?= $link('/ledger/book/project_account', '프로젝트계정별원장', 'bi-building') ?></li>
                    <li><?= $link('/ledger/book/daily_monthly', '일계표', 'bi-calendar-week') ?></li>
                    <li><?= $link('/ledger/book/purchase_sale', '매입매출장', 'bi-cash-coin') ?></li>
                    <li><?= $link('/ledger/book/car_log', '차량운행기록부', 'bi-truck') ?></li>
                </ul>
            </li>
            <li><?= $link('/ledger/search', '전표검색', 'bi-search') ?></li>
        <?php elseif ($section === 'institution'): ?>
            <li><?= $link('/institution', '기관 대시보드', 'bi-building') ?></li>
            <li><?= $link('/institution/tax_office', '세무서 / 국세청', 'bi-receipt') ?></li>
            <li><?= $link('/institution/local_government', '지방자치단체 / 지방세관', 'bi-map') ?></li>
            <li><?= $link('/institution/welfare_corp', '근로복지공단', 'bi-shield-check') ?></li>
            <li><?= $link('/institution/health_insurance', '건강보험공단', 'bi-heart-pulse') ?></li>
            <li><?= $link('/institution/pension', '국민연금공단', 'bi-safe') ?></li>
            <li><?= $link('/institution/credit_guarantee', '신용보증기금', 'bi-patch-check') ?></li>
            <li><?= $link('/institution/construction_assoc', '전문건설협회', 'bi-buildings') ?></li>
            <li><?= $link('/institution/construction_union', '전문건설공제조합', 'bi-bank2') ?></li>
            <li><?= $link('/institution/engineer_assoc', '기술인협회', 'bi-tools') ?></li>
            <li><?= $link('/institution/construction_worker_union', '건설근로자공제회', 'bi-people-fill') ?></li>
        <?php elseif ($section === 'site'): ?>
            <li><?= $link('/site', '현장 대시보드', 'bi-speedometer2') ?></li>
            <li><?= $link('/site/estimate', '견적관리', 'bi-file-earmark-spreadsheet') ?></li>
            <li><?= $link('/site/contract', '계약관리', 'bi-file-earmark-text') ?></li>
            <li><?= $link('/site/execution', '실행관리', 'bi-play-circle') ?></li>
            <li><?= $link('/site/guarantee', '보증/보험관리', 'bi-shield-lock') ?></li>
            <li><?= $link('/site/progress', '기성예정내역', 'bi-list-task') ?></li>
            <li><?= $link('/site/construction_progress', '시공기성예정내역', 'bi-hammer') ?></li>
            <li><?= $link('/site/transaction/create', '거래입력', 'bi-pencil-square') ?></li>
            <li><?= $link('/site/transaction', '거래내역', 'bi-list') ?></li>
            <li><?= $link('/site/safety', '안전관리', 'bi-cone-striped') ?></li>
        <?php elseif ($section === 'shop'): ?>
            <li><?= $link('/shop', '쇼핑몰 대시보드', 'bi-bag') ?></li>
            <li><?= $link('/shop/products', '상품관리', 'bi-box-seam') ?></li>
            <li><?= $link('/shop/categories', '카테고리관리', 'bi-diagram-3') ?></li>
            <li><?= $link('/shop/orders', '주문관리', 'bi-receipt') ?></li>
            <li><?= $link('/shop/payments', '결제관리', 'bi-credit-card') ?></li>
            <li><?= $link('/shop/settlement', '매출/정산', 'bi-cash-coin') ?></li>
        <?php elseif ($section === 'notice'): ?>
            <li><?= $link('/notice', '공지 대시보드', 'bi-megaphone') ?></li>
            <li><?= $link('/notice/employee', '직원 공지', 'bi-person-badge') ?></li>
            <li><?= $link('/notice/department', '부서별 공지', 'bi-diagram-3') ?></li>
            <li><?= $link('/notice/all', '전체 공지', 'bi-broadcast') ?></li>
        <?php endif; ?>
    </ul>
</div>
<div class="sidebar-right-border">
    <button id="sidebar-toggle-btn" class="sidebar-toggle-btn" aria-label="사이드바 접기">
        &#60;
    </button>
</div>
