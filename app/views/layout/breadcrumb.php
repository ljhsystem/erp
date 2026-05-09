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

$pageMap = [
    '/dashboard' => ['items' => ['메인', '대시보드']],
    '/dashboard/report' => ['items' => ['메인', '통합 보고서']],
    '/dashboard/activity' => ['items' => ['메인', '최근 활동']],
    '/dashboard/notifications' => ['items' => ['메인', '공지사항']],
    '/dashboard/kpi' => ['items' => ['메인', '실적 현황']],
    '/dashboard/calendar' => ['items' => ['메인', '일정/캘린더']],
    '/dashboard/settings/system/codes' => ['items' => ['메인', '설정', '시스템설정', '기준정보']],

    '/ledger' => ['items' => ['회계관리', '대시보드']],
    '/ledger/settings/accounts' => ['items' => ['회계관리', '기초정보관리', '계정과목']],
    '/ledger/accounts' => ['items' => ['회계관리', '기초정보관리', '계정과목']],
    '/ledger/settings/journal-rules' => ['items' => ['회계관리', '기초정보관리', '분개규칙']],
    '/ledger/settings/opening-balances' => ['items' => ['회계관리', '기초정보관리', '기초금액']],
    '/ledger/opening-balances' => ['items' => ['회계관리', '기초정보관리', '기초금액']],
    '/ledger/data/formats' => ['items' => ['회계관리', '자료관리', '양식관리']],
    '/ledger/data/format' => ['items' => ['회계관리', '자료관리', '양식관리']],
    '/ledger/data/upload' => ['items' => ['회계관리', '자료관리', '자료업로드']],
    '/ledger/data/list' => ['items' => ['회계관리', '자료관리', '자료목록']],
    '/ledger/data' => ['items' => ['회계관리', '자료관리', '자료목록']],
    '/ledger/transactions/input' => ['items' => ['회계관리', '거래관리', '거래입력']],
    '/ledger/transactions' => ['items' => ['회계관리', '거래관리', '거래입력']],
    '/ledger/transactions/create' => ['items' => ['회계관리', '거래관리', '거래입력']],
    '/ledger/transaction' => ['items' => ['회계관리', '거래관리', '거래입력']],
    '/ledger/transaction/create' => ['items' => ['회계관리', '거래관리', '거래입력']],
    '/ledger/vouchers/input' => ['items' => ['회계관리', '전표관리', '전표입력']],
    '/ledger/journal' => ['items' => ['회계관리', '전표관리', '전표입력']],
    '/ledger/vouchers/review' => ['items' => ['회계관리', '전표관리', '전표검토/승인']],

    '/document' => ['items' => ['문서관리', '대시보드']],
    '/approval' => ['items' => ['전자결재', '대시보드']],
    '/institution' => ['items' => ['대관기관업무', '대시보드']],
    '/site' => ['items' => ['현장관리', '대시보드']],
    '/shop' => ['items' => ['쇼핑몰관리', '대시보드']],
    '/notice' => ['items' => ['공지/회의', '대시보드']],
    '/sitemap' => ['items' => ['사이트정보', '사이트맵']],
    '/profile' => ['items' => ['사용자정보', '프로필']],
];

$current = $pageMap[$path] ?? [];

if (!empty($current['items'])) {
    $items = $current['items'];
} else {
    $items = array_values(array_filter([
        $meta['category'] ?? '기타',
        $meta['group'] ?? '',
        $meta['name'] ?? '페이지',
    ], fn($value) => trim((string) $value) !== ''));
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
