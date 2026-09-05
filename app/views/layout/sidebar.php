<?php
use Core\Helpers\AssetHelper;
?>
<?= AssetHelper::css('/assets/css/pages/layout/sidebar.css') ?>

<?php
$uri = trim($_SERVER['REQUEST_URI'] ?? '', '/');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$segments = $uri === '' ? [] : explode('/', $uri);
$section = $segments[0] ?? '';
$navigationPathAliases = [
    '/ledger/data' => '/ledger/data/list',
    '/ledger/transactions' => '/ledger/transactions/input',
    '/ledger/transactions/create' => '/ledger/transactions/input',
    '/ledger/transaction' => '/ledger/transactions/input',
    '/ledger/transaction/create' => '/ledger/transactions/input',
    '/ledger/journal' => '/ledger/vouchers/input',
];
$activePath = $navigationPathAliases[$currentPath] ?? $currentPath;

$menuRoutes = [
    'menu-ledger-basic' => ['/ledger/settings'],
    'menu-ledger-data' => ['/ledger/data'],
    'menu-ledger-voucher' => ['/ledger/transactions', '/ledger/transaction', '/ledger/vouchers', '/ledger/journal'],
    'menu-ledger-funds' => ['/ledger/funds'],
    'menu-ledger-book' => ['/ledger/book'],
    'menu-ledger-financial' => ['/ledger/financial'],
    'menu-ledger-asset' => ['/ledger/assets'],
    'menu-ledger-tax' => ['/ledger/tax'],
    'menu-institution-human-resources' => ['/institution/human-resources'],
    'menu-institution-income-data' => ['/institution/income-data'],
    'menu-site-project' => ['/site/projects'],
    'menu-site-construction' => ['/site/projects/construction', '/site/transactions'],
    'menu-site-safety-quality' => ['/site/projects/safety', '/site/projects/quality'],
    'menu-site-institution' => ['/site/institution'],
];
$activeMenuId = null;
$activeMenuPrefixLength = -1;
foreach ($menuRoutes as $menuId => $prefixes) {
    foreach ($prefixes as $prefix) {
        if (($activePath === $prefix || str_starts_with($activePath, $prefix . '/'))
            && strlen($prefix) > $activeMenuPrefixLength) {
            $activeMenuId = $menuId;
            $activeMenuPrefixLength = strlen($prefix);
        }
    }
}
$menuExpanded = static fn(string $menuId): bool => $activeMenuId === $menuId;
$menuToggleClass = static fn(string $menuId): string => 'nav-link toggle' . ($menuExpanded($menuId) ? ' selected' : '');
$menuCollapseClass = static fn(string $menuId): string => 'collapse' . ($menuExpanded($menuId) ? ' show' : '');
$menuItemClass = static fn(string $menuId): string => $menuExpanded($menuId) ? 'is-expanded' : '';
$menuAriaExpanded = static fn(string $menuId): string => $menuExpanded($menuId) ? 'true' : 'false';

$icon = static function (string $class): string {
    return '<i class="bi ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
};

$isActiveLink = static function (string $href) use ($activePath): bool {
    if ($href === '/ledger/data') {
        return in_array($activePath, [
            '/ledger/data',
            '/ledger/data/list',
            '/ledger/data/bank-transactions',
            '/ledger/data/tax-invoices',
        ], true);
    }

    if ($activePath === $href) return true;
    if (in_array($href, ['/main', '/document', '/approval', '/ledger', '/institution', '/site', '/shop'], true)) {
        return false;
    }
    return str_starts_with($activePath, rtrim($href, '/') . '/');
};

$link = static function (string $href, string $label, string $iconClass, string $extraClass = '') use ($icon, $isActiveLink): string {
    $class = trim('nav-link ' . ($isActiveLink($href) ? 'active ' : '') . $extraClass);

    $current = $isActiveLink($href) ? ' aria-current="page"' : '';
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' . $current . '>' . $icon($iconClass) . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
};

$plannedLink = static function (string $label, string $iconClass) use ($icon): string {
    return '<span class="nav-link site-menu-planned" aria-disabled="true">'
        . $icon($iconClass)
        . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
        . '<small>준비 중</small>'
        . '</span>';
};
?>

<div class="sidebar sidebar-initializing <?= (($ui['sidebar_default'] ?? '') === 'collapsed') ? 'collapsed' : '' ?>"
     data-current-path="<?= htmlspecialchars($currentPath, ENT_QUOTES, 'UTF-8') ?>"
     data-current-section="<?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?>">
    <ul class="nav nav-pills flex-column mb-auto">
        <?php if ($section === 'main'): ?>
            <li><?= $link('/main', '대시보드', 'bi-speedometer2') ?></li>
            <li><?= $link('/main/report', '통합 보고서', 'bi-bar-chart-line') ?></li>
            <li><?= $link('/main/activity', '최근 활동', 'bi-activity') ?></li>
            <li><?= $link('/main/notifications', '공지사항', 'bi-megaphone') ?></li>
            <li><?= $link('/main/kpi', '실적 현황', 'bi-graph-up-arrow') ?></li>
            <li><?= $link('/main/calendar', '일정/캘린더', 'bi-calendar3') ?></li>
            <li><?= $link('/main/settings', '설정', 'bi-gear') ?></li>
        <?php elseif ($section === 'document' || $section === 'sukhyang'): ?>
            <li><?= $link('/document', '대시보드', 'bi-folder2-open') ?></li>
            <li><?= $link('/document/file_register', '문서 등록', 'bi-file-earmark-plus') ?></li>
            <li><?= $link('/document/view', '문서 상세 보기', 'bi-file-earmark-text') ?></li>
            <li><?= $link('/document/edit', '문서 수정', 'bi-pencil-square') ?></li>
            <li><?= $link('/document/stats', '문서 통계', 'bi-bar-chart') ?></li>
        <?php elseif ($section === 'approval'): ?>
            <li><?= $link('/approval', '대시보드', 'bi-check2-square') ?></li>
            <li><?= $link('/approval/personal-expense', '개인경비 신청', 'bi-wallet2') ?></li>
            <li><?= $link('/approval/leave-request', '휴가신청', 'bi-calendar2-check') ?></li>
            <li><?= $link('/approval/write_expenditure', '지출결의서 작성', 'bi-receipt') ?></li>
            <li><?= $link('/approval/write_purchase_request', '구매요청서', 'bi-cart-plus') ?></li>
            <li><?= $link('/approval/write_trip_report', '출장보고서', 'bi-briefcase') ?></li>
            <li><?= $link('/approval/write_work_report', '업무보고서', 'bi-clipboard-data') ?></li>
            <li><?= $link('/approval/status', '결재함', 'bi-inbox') ?></li>
        <?php elseif ($section === 'ledger'): ?>
            <li><?= $link('/ledger', '대시보드', 'bi-journal-text') ?></li>
            <li class="<?= $menuItemClass('menu-ledger-basic') ?>">
                <a href="#menu-ledger-basic" class="<?= $menuToggleClass('menu-ledger-basic') ?>" aria-expanded="<?= $menuAriaExpanded('menu-ledger-basic') ?>"><?= $icon('bi-gear') ?><span>기초정보관리</span></a>
                <ul id="menu-ledger-basic" class="<?= $menuCollapseClass('menu-ledger-basic') ?>">
                    <li><?= $link('/ledger/settings/accounts', '계정과목', 'bi-list-ul') ?></li>
                    <li><?= $link('/ledger/settings/journal-rules', '분개규칙', 'bi-diagram-3') ?></li>
                    <li><?= $link('/ledger/settings/opening-balances', '기초금액', 'bi-cash-stack') ?></li>
                    <li><?= $link('/ledger/settings/inventory-balances', '재고관리', 'bi-box-seam') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-ledger-data') ?>">
                <a href="#menu-ledger-data" class="<?= $menuToggleClass('menu-ledger-data') ?>" aria-expanded="<?= $menuAriaExpanded('menu-ledger-data') ?>"><?= $icon('bi-database') ?><span>자료관리</span></a>
                <ul id="menu-ledger-data" class="<?= $menuCollapseClass('menu-ledger-data') ?>">
                    <li><?= $link('/ledger/data/evidence-metadata', '증빙정책', 'bi-database-gear') ?></li>
                    <li><?= $link('/ledger/data', '증빙원본', 'bi-clipboard-data') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-ledger-voucher') ?>">
                <a href="#menu-ledger-voucher" class="<?= $menuToggleClass('menu-ledger-voucher') ?>" aria-expanded="<?= $menuAriaExpanded('menu-ledger-voucher') ?>"><?= $icon('bi-journal-richtext') ?><span>전표관리</span></a>
                <ul id="menu-ledger-voucher" class="<?= $menuCollapseClass('menu-ledger-voucher') ?>">
                    <li><?= $link('/ledger/transactions/input', '거래입력', 'bi-pencil-square') ?></li>
                    <li><?= $link('/ledger/vouchers/input', '전표입력', 'bi-pencil-square') ?></li>
                    <li><?= $link('/ledger/vouchers/review', '전표검토·전기', 'bi-check2-square') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-ledger-funds') ?>">
                <a href="#menu-ledger-funds" class="<?= $menuToggleClass('menu-ledger-funds') ?>" aria-expanded="<?= $menuAriaExpanded('menu-ledger-funds') ?>"><?= $icon('bi-bank') ?><span>자금관리</span></a>
                <ul id="menu-ledger-funds" class="<?= $menuCollapseClass('menu-ledger-funds') ?>">
                    <li><?= $link('/ledger/funds', '자금현황', 'bi-wallet2') ?></li>
                    <li><?= $link('/ledger/funds/account-transactions', '계좌별거래내역', 'bi-bank') ?></li>
                    <li><?= $link('/ledger/funds/daily-report', '자금일보', 'bi-calendar2-check') ?></li>
                    <li><?= $link('/ledger/funds/payment-schedule', '지급예정현황', 'bi-calendar-range') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-ledger-book') ?>">
                <a href="#menu-ledger-book" class="<?= $menuToggleClass('menu-ledger-book') ?>" aria-expanded="<?= $menuAriaExpanded('menu-ledger-book') ?>"><?= $icon('bi-book') ?><span>장부관리</span></a>
                <ul id="menu-ledger-book" class="<?= $menuCollapseClass('menu-ledger-book') ?>">
                    <li><?= $link('/ledger/book/journal', '분개장', 'bi-journal') ?></li>
                    <li><?= $link('/ledger/book/general', '총계정원장', 'bi-bookmarks') ?></li>
                    <li><?= $link('/ledger/book/account', '계정별원장', 'bi-collection') ?></li>
                    <li><?= $link('/ledger/book/partner', '거래처원장', 'bi-people') ?></li>
                    <li><?= $link('/ledger/book/project', '프로젝트원장', 'bi-building') ?></li>
                    <li><?= $link('/ledger/book/daily', '일계표', 'bi-calendar-week') ?></li>
                    <li><?= $link('/ledger/book/purchase-sales', '매입매출장', 'bi-cash-coin') ?></li>
                    <li><?= $link('/ledger/book/vehicle-log', '차량운행기록부', 'bi-truck') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-ledger-financial') ?>">
                <a href="#menu-ledger-financial" class="<?= $menuToggleClass('menu-ledger-financial') ?>" aria-expanded="<?= $menuAriaExpanded('menu-ledger-financial') ?>"><?= $icon('bi-file-earmark-bar-graph') ?><span>재무제표</span></a>
                <ul id="menu-ledger-financial" class="<?= $menuCollapseClass('menu-ledger-financial') ?>">
                    <li><?= $link('/ledger/financial/trial-balance', '합계잔액시산표', 'bi-calculator') ?></li>
                    <li><?= $link('/ledger/financial/income-statement', '손익계산서', 'bi-graph-up') ?></li>
                    <li><?= $link('/ledger/financial/statement-position', '재무상태표', 'bi-file-spreadsheet') ?></li>
                    <li><?= $link('/ledger/financial/product-cost', '상품원가명세서', 'bi-box-seam') ?></li>
                    <li><?= $link('/ledger/financial/construction-cost', '공사원가명세서', 'bi-building-gear') ?></li>
                    <li><?= $link('/ledger/financial/retained-earnings', '이익잉여금처분계산서', 'bi-pie-chart') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-ledger-asset') ?>">
                <a href="#menu-ledger-asset" class="<?= $menuToggleClass('menu-ledger-asset') ?>" aria-expanded="<?= $menuAriaExpanded('menu-ledger-asset') ?>"><?= $icon('bi-archive') ?><span>자산관리</span></a>
                <ul id="menu-ledger-asset" class="<?= $menuCollapseClass('menu-ledger-asset') ?>">
                    <li><?= $link('/ledger/assets/create', '자산등록', 'bi-plus-square') ?></li>
                    <li><?= $link('/ledger/assets', '자산대장', 'bi-card-list') ?></li>
                    <li><?= $link('/ledger/assets/depreciation', '감가상각', 'bi-graph-down') ?></li>
                    <li><?= $link('/ledger/assets/transfer', '자산이동', 'bi-arrow-left-right') ?></li>
                    <li><?= $link('/ledger/assets/disposal', '자산폐기', 'bi-trash3') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-ledger-closing') ?>">
                <a href="#menu-ledger-closing" class="<?= $menuToggleClass('menu-ledger-closing') ?>" aria-expanded="<?= $menuAriaExpanded('menu-ledger-closing') ?>"><?= $icon('bi-lock') ?><span>결산관리</span></a>
                <ul id="menu-ledger-closing" class="<?= $menuCollapseClass('menu-ledger-closing') ?>">
                    <li><?= $link('/ledger/closing/check', '결산점검', 'bi-clipboard-check') ?></li>
                    <li><?= $link('/ledger/closing/periods', '회계기간 마감', 'bi-lock-fill') ?></li>
                    <li><?= $link('/ledger/closing/carry-forward', '기초금액 이월', 'bi-arrow-right-circle') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-ledger-tax') ?>">
                <a href="#menu-ledger-tax" class="<?= $menuToggleClass('menu-ledger-tax') ?>" aria-expanded="<?= $menuAriaExpanded('menu-ledger-tax') ?>"><?= $icon('bi-receipt-cutoff') ?><span>세무회계(참고)</span></a>
                <ul id="menu-ledger-tax" class="<?= $menuCollapseClass('menu-ledger-tax') ?>">
                    <li><?= $link('/ledger/tax/trial-balance', '세무 시산표', 'bi-calculator') ?></li>
                    <li><?= $link('/ledger/tax/income-statement', '세무 손익계산서', 'bi-graph-up') ?></li>
                    <li><?= $link('/ledger/tax/statement-position', '세무 재무상태표', 'bi-file-spreadsheet') ?></li>
                    <li><?= $link('/ledger/tax/cost-statement', '세무 원가명세서', 'bi-file-earmark-text') ?></li>
                    <li><?= $link('/ledger/tax/retained-earnings', '세무 이익잉여금', 'bi-pie-chart') ?></li>
                    <li><?= $link('/ledger/tax/comparison', '비교/차이분석', 'bi-arrow-left-right') ?></li>
                </ul>
            </li>
        <?php elseif ($section === 'institution'): ?>
            <li><?= $link('/institution', '대시보드', 'bi-building') ?></li>
            <li class="<?= $menuItemClass('menu-institution-human-resources') ?>">
                <a href="#menu-institution-human-resources" class="<?= $menuToggleClass('menu-institution-human-resources') ?>" aria-expanded="<?= $menuAriaExpanded('menu-institution-human-resources') ?>"><?= $icon('bi-person-badge') ?><span>인사·노무관리</span></a>
                <ul id="menu-institution-human-resources" class="<?= $menuCollapseClass('menu-institution-human-resources') ?>">
                    <li><?= $link('/institution/human-resources/employment-contracts', '근로계약관리', 'bi-file-earmark-person') ?></li>
                    <li><?= $link('/institution/human-resources/personnel-actions', '인사발령관리', 'bi-arrow-left-right') ?></li>
                    <li><?= $link('/institution/human-resources/job-assignments', '직무·배치관리', 'bi-diagram-3') ?></li>
                    <li><?= $link('/institution/human-resources/attendance', '근태관리', 'bi-clock') ?></li>
                    <li><?= $link('/institution/human-resources/leave', '휴가관리', 'bi-calendar-check') ?></li>
                    <li><?= $link('/institution/human-resources/qualification-education', '자격·교육관리', 'bi-mortarboard') ?></li>
                    <li><?= $link('/institution/human-resources/performance-evaluations', '성과평가관리', 'bi-graph-up-arrow') ?></li>
                    <li><?= $link('/institution/human-resources/compensation-incentives', '보상·인센티브관리', 'bi-award') ?></li>
                    <li><?= $link('/institution/human-resources/employment-rules', '취업규칙·인사규정', 'bi-journal-check') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-institution-income-data') ?>">
                <a href="#menu-institution-income-data" class="<?= $menuToggleClass('menu-institution-income-data') ?>" aria-expanded="<?= $menuAriaExpanded('menu-institution-income-data') ?>"><?= $icon('bi-people') ?><span>소득자료관리</span></a>
                <ul id="menu-institution-income-data" class="<?= $menuCollapseClass('menu-institution-income-data') ?>">
                    <li><?= $link('/institution/income-data/regular-employment', '상용근로소득', 'bi-person-vcard') ?></li>
                    <li><?= $link('/institution/income-data/daily-employment', '일용근로소득', 'bi-calendar2-week') ?></li>
                    <li><?= $link('/institution/income-data/business-income', '사업소득', 'bi-briefcase') ?></li>
                </ul>
            </li>
            <li><?= $link('/institution/national-tax', '국세업무', 'bi-receipt') ?></li>
            <li><?= $link('/institution/local-tax', '지방세업무', 'bi-map') ?></li>
            <li><?= $link('/institution/social-insurance', '4대보험업무', 'bi-shield-check') ?></li>
            <li><?= $link('/institution/tax-agent', '세무사업무', 'bi-person-workspace') ?></li>
            <li><?= $link('/institution/filing-history', '신고이력', 'bi-clock-history') ?></li>
        <?php elseif ($section === 'site'): ?>
            <li><?= $link('/site', '대시보드', 'bi-speedometer2') ?></li>
            <li><?= $link('/site/sales', '영업관리', 'bi-bullseye') ?></li>
            <li><?= $link('/site/estimate', '견적관리', 'bi-calculator') ?></li>
            <li><?= $plannedLink('전자입찰', 'bi-broadcast-pin') ?></li>
            <li class="<?= $menuItemClass('menu-site-project') ?>">
                <a href="#menu-site-project" class="<?= $menuToggleClass('menu-site-project') ?>" aria-expanded="<?= $menuAriaExpanded('menu-site-project') ?>"><?= $icon('bi-building-gear') ?><span>프로젝트관리</span></a>
                <ul id="menu-site-project" class="<?= $menuCollapseClass('menu-site-project') ?>">
                    <li><?= $plannedLink('프로젝트 현황', 'bi-kanban') ?></li>
                    <li><?= $plannedLink('계약·변경관리', 'bi-file-earmark-text') ?></li>
                    <li><?= $plannedLink('담당자배치', 'bi-person-check') ?></li>
                    <li><?= $plannedLink('기성관리', 'bi-cash-coin') ?></li>
                    <li><?= $plannedLink('준공·정산관리', 'bi-flag-fill') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-site-institution') ?>">
                <a href="#menu-site-institution" class="<?= $menuToggleClass('menu-site-institution') ?>" aria-expanded="<?= $menuAriaExpanded('menu-site-institution') ?>"><?= $icon('bi-bank') ?><span>현장 기관업무</span></a>
                <ul id="menu-site-institution" class="<?= $menuCollapseClass('menu-site-institution') ?>">
                    <li><?= $plannedLink('보증·보험관리', 'bi-shield-check') ?></li>
                    <li><?= $plannedLink('기관업무 현황', 'bi-building') ?></li>
                    <li><?= $plannedLink('공사실적관리', 'bi-award') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-site-construction') ?>">
                <a href="#menu-site-construction" class="<?= $menuToggleClass('menu-site-construction') ?>" aria-expanded="<?= $menuAriaExpanded('menu-site-construction') ?>"><?= $icon('bi-hammer') ?><span>실행·공사관리</span></a>
                <ul id="menu-site-construction" class="<?= $menuCollapseClass('menu-site-construction') ?>">
                    <li><?= $plannedLink('실행계획·예산', 'bi-cash-stack') ?></li>
                    <li><?= $plannedLink('공종·공정관리', 'bi-list-check') ?></li>
                    <li><?= $plannedLink('작업지시·일보', 'bi-clipboard-data') ?></li>
                    <li><?= $plannedLink('자재·외주·장비', 'bi-tools') ?></li>
                    <li><?= $plannedLink('실측·도면·현장사진', 'bi-camera') ?></li>
                    <li><?= $plannedLink('현장 거래입력', 'bi-pencil-square') ?></li>
                    <li><?= $plannedLink('거래·증빙 연결현황', 'bi-link-45deg') ?></li>
                </ul>
            </li>
            <li class="<?= $menuItemClass('menu-site-safety-quality') ?>">
                <a href="#menu-site-safety-quality" class="<?= $menuToggleClass('menu-site-safety-quality') ?>" aria-expanded="<?= $menuAriaExpanded('menu-site-safety-quality') ?>"><?= $icon('bi-shield-exclamation') ?><span>안전·품질관리</span></a>
                <ul id="menu-site-safety-quality" class="<?= $menuCollapseClass('menu-site-safety-quality') ?>">
                    <li><?= $plannedLink('안전점검·위험성평가', 'bi-exclamation-triangle') ?></li>
                    <li><?= $plannedLink('안전교육', 'bi-person-video3') ?></li>
                    <li><?= $plannedLink('사고·조치내역', 'bi-bandaid') ?></li>
                    <li><?= $plannedLink('품질점검', 'bi-patch-check') ?></li>
                    <li><?= $plannedLink('펀치리스트·하자대응', 'bi-list-check') ?></li>
                </ul>
            </li>
        <?php elseif ($section === 'shop'): ?>
            <li><?= $link('/shop', '대시보드', 'bi-bag') ?></li>
            <li><?= $link('/shop/products', '상품관리', 'bi-box-seam') ?></li>
            <li><?= $link('/shop/categories', '카테고리관리', 'bi-diagram-3') ?></li>
            <li><?= $link('/shop/orders', '주문관리', 'bi-receipt') ?></li>
        <?php endif; ?>
    </ul>
</div>

<div class="sidebar-right-border" aria-hidden="false">
    <button type="button"
            id="sidebar-toggle-btn"
            class="sidebar-toggle-btn"
            aria-label="사이드바 열고 닫기">
        <i class="bi bi-chevron-left"></i>
    </button>
</div>
