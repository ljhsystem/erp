<?php
// 경로: PROJECT_ROOT . '/app/views/layout/breadcrumb.php'

use Core\Router;

if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('breadcrumb_is_broken_text')) {
    function breadcrumb_is_broken_text(string $value): bool
    {
        return $value === '' || (bool) preg_match('/[?占]/u', $value);
    }
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$meta = Router::currentBreadcrumbMeta();

$pageMap = [
    '/dashboard' => [
        'category' => '메인',
        'group' => '',
        'name' => '대시보드',
    ],
    '/dashboard/report' => [
        'category' => '메인',
        'group' => '보고서',
        'name' => '통합 보고서',
    ],
    '/dashboard/activity' => [
        'category' => '메인',
        'group' => '모니터링',
        'name' => '최근 활동',
    ],
    '/dashboard/notifications' => [
        'category' => '메인',
        'group' => '모니터링',
        'name' => '공지사항',
    ],
    '/dashboard/kpi' => [
        'category' => '메인',
        'group' => '보고서',
        'name' => '실적 현황',
    ],
    '/dashboard/settings' => [
        'category' => '메인',
        'group' => '환경설정',
        'name' => '설정',
    ],
    '/document' => [
        'category' => '문서관리',
        'group' => '',
        'name' => '대시보드',
    ],
    '/approval' => [
        'category' => '전자결재',
        'group' => '',
        'name' => '대시보드',
    ],
    '/ledger' => [
        'category' => '회계관리',
        'group' => '',
        'name' => '대시보드',
    ],
    '/ledger/accounts' => [
        'category' => '회계관리',
        'group' => '기초정보관리',
        'name' => '계정과목',
    ],
    '/ledger/journal' => [
        'category' => '회계관리',
        'group' => '전표입력',
        'name' => '일반전표',
    ],
    '/institution' => [
        'category' => '대외기관업무',
        'group' => '',
        'name' => '대시보드',
    ],
    '/site' => [
        'category' => '현장관리',
        'group' => '',
        'name' => '대시보드',
    ],
    '/notice' => [
        'category' => '공지/회의',
        'group' => '',
        'name' => '대시보드',
    ],
    '/sitemap' => [
        'category' => '사이트맵',
        'group' => '',
        'name' => '대시보드',
    ],
];

$fallback = $pageMap[$path] ?? [
    'category' => '기타',
    'group' => '',
    'name' => '페이지',
];

$category = array_key_exists($path, $pageMap)
    ? $fallback['category']
    : (breadcrumb_is_broken_text($meta['category']) ? $fallback['category'] : $meta['category']);

$group = array_key_exists($path, $pageMap)
    ? $fallback['group']
    : (breadcrumb_is_broken_text($meta['group']) ? $fallback['group'] : $meta['group']);

$name = array_key_exists($path, $pageMap)
    ? $fallback['name']
    : (breadcrumb_is_broken_text($meta['name']) ? $fallback['name'] : $meta['name']);

$items = array_values(array_filter([$category, $group, $name], static fn ($item) => trim((string) $item) !== ''));

if (count($items) < 2) {
    $items = array_values(array_filter([$fallback['category'], $fallback['group'], $fallback['name']], static fn ($item) => trim((string) $item) !== ''));
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
