<?php

use Core\Router;

if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$meta = Router::currentBreadcrumbMeta();

$routeDescriptionItems = array_values(array_filter(array_map(
    static fn($value) => trim((string) $value),
    explode('>', (string) ($meta['description'] ?? ''))
), static fn($value) => $value !== ''));

$pageMap = [
    '/main' => ['items' => ['메인', '대시보드']],
    '/main/report' => ['items' => ['메인', '통합 보고서']],
    '/main/activity' => ['items' => ['메인', '최근 활동']],
    '/main/notifications' => ['items' => ['메인', '공지사항']],
    '/main/kpi' => ['items' => ['메인', '실적 현황']],
    '/main/calendar' => ['items' => ['메인', '일정/캘린더']],
    '/main/settings/standard/code' => ['items' => ['메인', '설정', '기준관리', '코드관리']],
    '/main/settings/standard/statutory-standards' => ['items' => ['메인', '설정', '기준관리', '법정기준관리']],

    '/ledger' => ['items' => ['회계관리', '대시보드']],
    '/ledger/settings/accounts' => ['items' => ['회계관리', '기초정보관리', '계정과목']],
    '/ledger/settings/journal-rules' => ['items' => ['회계관리', '기초정보관리', '분개규칙']],
    '/ledger/data/evidence-metadata' => ['items' => ['회계관리', '자료관리', '증빙정책']],
    '/ledger/settings/opening-balances' => ['items' => ['회계관리', '기초정보관리', '기초금액']],
    '/ledger/opening-balances' => ['items' => ['회계관리', '기초정보관리', '기초금액']],
    '/ledger/data/upload' => ['items' => ['회계관리', '자료관리', '자료업로드']],
    '/ledger/data/list' => ['items' => ['회계관리', '자료관리', '증빙원본']],
    '/ledger/data/bank-transactions' => ['items' => ['회계관리', '자료관리', '입출금(은행)']],
    '/ledger/data/daily-employment-incomes' => ['items' => ['회계관리', '자료관리', '일용직(신고)']],
    '/ledger/data/tax-invoices' => ['items' => ['회계관리', '자료관리', '세금계산서매입매출(홈택스)']],
    '/ledger/data/raw' => ['items' => ['회계관리', '자료관리', '원본자료']],
    '/ledger/data' => ['items' => ['회계관리', '자료관리', '증빙원본']],
    '/ledger/transactions/input' => ['items' => ['회계관리', '전표관리', '거래입력']],
    '/ledger/transactions' => ['items' => ['회계관리', '전표관리', '거래입력']],
    '/ledger/transactions/create' => ['items' => ['회계관리', '전표관리', '거래입력']],
    '/ledger/transaction' => ['items' => ['회계관리', '전표관리', '거래입력']],
    '/ledger/transaction/create' => ['items' => ['회계관리', '전표관리', '거래입력']],
    '/ledger/vouchers/input' => ['items' => ['회계관리', '전표관리', '전표입력']],
    '/ledger/journal' => ['items' => ['회계관리', '전표관리', '전표입력']],
    '/ledger/vouchers/review' => ['items' => ['회계관리', '전표관리', '전표검토·전기']],
    '/ledger/funds' => ['items' => ['회계관리', '자금관리', '자금현황']],
    '/ledger/funds/account-transactions' => ['items' => ['회계관리', '자금관리', '계좌별거래내역']],
    '/ledger/funds/daily-report' => ['items' => ['회계관리', '자금관리', '자금일보']],
    '/ledger/funds/account-balances' => ['items' => ['회계관리', '자금관리', '자금현황']],
    '/ledger/funds/payment-schedule' => ['items' => ['회계관리', '자금관리', '지급예정현황']],

    '/document' => ['items' => ['문서관리', '대시보드']],
    '/approval' => ['items' => ['전자결재', '대시보드']],
    '/approval/leave-request' => ['items' => ['전자결재', '휴가신청']],
    '/institution' => ['items' => ['대외기관업무', '대시보드']],
    '/institution/human-resources/employment-contracts' => ['items' => ['대외기관업무', '인사·노무관리', '근로계약관리']],
    '/institution/human-resources/personnel-actions' => ['items' => ['대외기관업무', '인사·노무관리', '인사발령관리']],
    '/institution/human-resources/job-assignments' => ['items' => ['대외기관업무', '인사·노무관리', '직무·배치관리']],
    '/institution/human-resources/attendance' => ['items' => ['대외기관업무', '인사·노무관리', '근태관리']],
    '/institution/human-resources/leave' => ['items' => ['대외기관업무', '인사·노무관리', '휴가관리']],
    '/institution/human-resources/qualification-education' => ['items' => ['대외기관업무', '인사·노무관리', '자격·교육관리']],
    '/institution/human-resources/performance-evaluations' => ['items' => ['대외기관업무', '인사·노무관리', '성과평가관리']],
    '/institution/human-resources/compensation-incentives' => ['items' => ['대외기관업무', '인사·노무관리', '보상·인센티브관리']],
    '/institution/human-resources/employment-rules' => ['items' => ['대외기관업무', '인사·노무관리', '취업규칙·인사규정']],
    '/institution/income-data/regular-employment' => ['items' => ['대외기관업무', '소득자료관리', '상용근로소득']],
    '/institution/income-data/daily-employment' => ['items' => ['대외기관업무', '소득자료관리', '일용근로소득']],
    '/institution/income-data/business-income' => ['items' => ['대외기관업무', '소득자료관리', '사업소득']],
    '/institution/national-tax' => ['items' => ['대외기관업무', '국세업무']],
    '/institution/local-tax' => ['items' => ['대외기관업무', '지방세업무']],
    '/institution/social-insurance' => ['items' => ['대외기관업무', '4대보험업무']],
    '/institution/tax-agent' => ['items' => ['대외기관업무', '세무사업무']],
    '/institution/filing-history' => ['items' => ['대외기관업무', '신고이력']],
    '/site' => ['items' => ['현장관리', '대시보드']],
    '/shop' => ['items' => ['쇼핑몰관리', '대시보드']],
    '/notice' => ['items' => ['공지/회의', '대시보드']],
    '/sitemap' => ['items' => ['사이트정보', '사이트맵']],
    '/profile' => ['items' => ['사용자정보', '프로필']],
];

$current = $pageMap[$path] ?? [];

if (!empty($routeDescriptionItems)) {
    $items = $routeDescriptionItems;
} elseif (!empty($current['items'])) {
    $items = $current['items'];
} else {
    $items = array_values(array_filter([
        $meta['category'] ?? '기타',
        $meta['group'] ?? '',
        $meta['name'] ?? '페이지',
    ], static fn($value) => trim((string) $value) !== ''));
}
?>

<div class="breadcrumb-row breadcrumb-row-right">
    <nav class="breadcrumb-nav" aria-label="breadcrumb" itemscope itemtype="https://schema.org/BreadcrumbList">
        <ol class="breadcrumb-list breadcrumb-list-compact">
            <?php foreach ($items as $index => $item): ?>
                <?php $position = $index + 1; ?>
                <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                    <?php if ($position === count($items)): ?>
                        <span class="current" aria-current="page" itemprop="name"><?= e($item) ?></span>
                    <?php else: ?>
                        <span itemprop="name"><?= e($item) ?></span>
                    <?php endif; ?>
                    <meta itemprop="position" content="<?= $position ?>">
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
</div>
