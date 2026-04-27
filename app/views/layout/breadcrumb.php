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
        'items' => ['메인', '대시보드'],
    ],
    '/dashboard/report' => [
        'items' => ['메인', '통합 보고서'],
    ],
    '/dashboard/activity' => [
        'items' => ['메인', '최근 활동'],
    ],
    '/dashboard/notifications' => [
        'items' => ['메인', '공지사항'],
    ],
    '/dashboard/kpi' => [
    'items' => ['메인', '실적 현황'],
    ],
    '/dashboard/calendar' => [
    'items' => ['메인', '일정/캘린더'],
    ],
    '/dashboard/settings' => [
        'items' => ['메인', '설정', '기초정보관리', '회사정보'],
    ],
    '/dashboard/settings/base-info/company' => [
        'items' => ['메인', '설정', '기초정보관리', '회사정보'],
    ],
    '/dashboard/settings/base-info/brand' => [
        'items' => ['메인', '설정', '기초정보관리', '브랜드'],
    ],
    '/dashboard/settings/base-info/cover' => [
        'items' => ['메인', '설정', '기초정보관리', '커버이미지'],
    ],
    '/dashboard/settings/base-info/codes' => [
        'items' => ['메인', '설정', '기초정보관리', '기준정보'],
    ],
    '/dashboard/settings/base-info/clients' => [
        'items' => ['메인', '설정', '기초정보관리', '거래처'],
    ],
    '/dashboard/settings/base-info/projects' => [
        'items' => ['메인', '설정', '기초정보관리', '프로젝트'],
    ],
    '/dashboard/settings/base-info/bank-accounts' => [
        'items' => ['메인', '설정', '기초정보관리', '계좌'],
    ],
    '/dashboard/settings/base-info/cards' => [
        'items' => ['메인', '설정', '기초정보관리', '카드'],
    ],
    '/dashboard/settings/base-info/work-teams' => [
        'items' => ['메인', '설정', '기초정보관리', '팀'],
    ],
    '/dashboard/settings/organization/employees' => [
        'items' => ['메인', '설정', '조직관리', '직원'],
    ],
    '/dashboard/settings/organization/departments' => [
        'items' => ['메인', '설정', '조직관리', '부서'],
    ],
    '/dashboard/settings/organization/positions' => [
        'items' => ['메인', '설정', '조직관리', '직책'],
    ],
    '/dashboard/settings/organization/roles' => [
        'items' => ['메인', '설정', '조직관리', '역할'],
    ],
    '/dashboard/settings/organization/role_permissions' => [
        'items' => ['메인', '설정', '조직관리', '권한부여'],
    ],
    '/dashboard/settings/organization/approval' => [
        'items' => ['메인', '설정', '조직관리', '결재템플릿'],
    ],
    '/dashboard/settings/system/site' => [
        'items' => ['메인', '설정', '시스템설정', '사이트정보'],
    ],
    '/dashboard/settings/system/session' => [
        'items' => ['메인', '설정', '시스템설정', '세션관리'],
    ],
    '/dashboard/settings/system/security' => [
        'items' => ['메인', '설정', '시스템설정', '보안정책'],
    ],
    '/dashboard/settings/system/codes' => [
        'items' => ['메인', '설정', '시스템설정', '기준정보'],
    ],
    '/dashboard/settings/system/api' => [
        'items' => ['메인', '설정', '시스템설정', '외부연동(API)'],
    ],
    '/dashboard/settings/system/external_services' => [
        'items' => ['메인', '설정', '시스템설정', '외부서비스연동'],
    ],
    '/dashboard/settings/system/storage' => [
        'items' => ['메인', '설정', '시스템설정', '파일저장소'],
    ],
    '/dashboard/settings/system/databasebackup' => [
        'items' => ['메인', '설정', '시스템설정', '데이터백업'],
    ],
    '/dashboard/settings/system/logs' => [
        'items' => ['메인', '설정', '시스템설정', '시스템로그'],
    ],

    '/document' => [
        'items' => ['내부문서', '대시보드'],
    ],
    '/approval' => [
        'items' => ['전자결재', '대시보드'],
    ],
    '/ledger' => [
        'items' => ['회계관리', '대시보드'],
    ],
    '/ledger/accounts' => [
        'items' => ['회계관리', '기초정보관리', '계정과목'],
    ],
    '/ledger/journal' => [
        'items' => ['회계관리', '전표입력', '일반전표'],
    ],
    '/institution' => [
        'items' => ['대외기관업무', '대시보드'],
    ],
    '/site' => [
        'items' => ['현장관리', '대시보드'],
    ],
    '/shop' => [
        'items' => ['쇼핑몰관리', '대시보드'],
    ],
    '/notice' => [
        'items' => ['공지/회의', '대시보드'],
    ],
    '/sitemap' => [
        'items' => ['사이트정보', '사이트맵'],
    ],
    '/profile' => [
        'items' => ['사용자정보', '프로필'],
    ],
];

$current = $pageMap[$path] ?? [];


/* =========================
   🔥 핵심: items 우선
========================= */
if (!empty($current['items'])) {
    $items = $current['items'];
} else {

    $fallback = [
        'category' => '기타',
        'group' => '',
        'name' => '페이지',
    ];

    $category = $meta['category'] ?? $fallback['category'];
    $group    = $meta['group'] ?? $fallback['group'];
    $name     = $meta['name'] ?? $fallback['name'];

    $items = array_values(array_filter([
        $category,
        $group,
        $name
    ], fn($v) => trim((string)$v) !== ''));

    if (empty($items)) {
        $items = ['기타', '페이지'];
    }
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
