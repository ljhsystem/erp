<?php

use Core\Helpers\AssetHelper;
use Core\Router;

$pageTitle = 'ERP 사이트맵';
$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => false,
    'footer' => true,
    'wrapper' => 'single',
];
$pageStyles = AssetHelper::css('/assets/css/pages/sitemap/index.css');

$statusMap = [
    'full' => ['label' => 'API + UI 완료', 'icon' => '🟢', 'class' => 'full'],
    'api_only' => ['label' => 'API 완료 / UI 미완', 'icon' => '🟡', 'class' => 'api-only'],
    'ui_only' => ['label' => 'UI 있음 / API 없음', 'icon' => '🟠', 'class' => 'ui-only'],
    'empty' => ['label' => '없음', 'icon' => '🔴', 'class' => 'empty'],
];

$flowSteps = [
    ['label' => '현장관리', 'url' => '/site'],
    ['label' => '거래관리(transaction)', 'url' => '/site/transaction'],
    ['label' => '회계관리(ledger)', 'url' => '/ledger'],
    ['label' => '전자결재(approval)', 'url' => '/approval'],
];

$moduleSummaries = [
    '대시보드' => '전사 공통 대시보드와 운영 관리 화면',
    '자료관리' => '문서 및 자료 관리와 조회 화면',
    '전자결재' => '결재 작성, 진행, 상태 추적 영역',
    '회계관리' => '계정과목, 전표입력, 장부/결산 기반 영역',
    '대외기관업무' => '직원 SSOT를 기반으로 인사·노무, 소득자료, 기관별 신고업무와 신고이력을 연결하는 허브',
    '현장관리' => '현장 운영과 거래 입력 기반 화면',
    '공지/회의' => '사내 공지와 회의 게시 영역',
    '쇼핑몰관리' => '쇼핑몰 운영과 주문/결제 관리 화면',
    '설정' => '기준정보, 조직정보, 시스템설정 관리 화면',
    '공개사이트' => '공개 사이트와 회사 소개 화면',
    '사용자정보' => '프로필 및 사용자 개인 설정 화면',
    '기타' => '공통 진입 및 보조 페이지',
];

$moduleOrder = [
    '대시보드',
    '설정',
    '회계관리',
    '전자결재',
    '자료관리',
    '쇼핑몰관리',
    '현장관리',
    '공지/회의',
    '대외기관업무',
    '공개사이트',
    '사용자정보',
    '기타',
];

if (!function_exists('sitemapLoadWebRoutes')) {
    function sitemapLoadWebRoutes(): array
    {
        $catalogRouter = new Router();
        $previousRouter = $GLOBALS['router'] ?? null;
        $GLOBALS['router'] = $catalogRouter;

        require PROJECT_ROOT . '/routes/web.php';

        if ($previousRouter !== null) {
            $GLOBALS['router'] = $previousRouter;
        } else {
            unset($GLOBALS['router']);
        }

        $routes = (function () {
            return $this->routes;
        })->call($catalogRouter);

        return $routes['GET'] ?? [];
    }
}

if (!function_exists('sitemapShouldIncludeWebRoute')) {
    function sitemapShouldIncludeWebRoute(string $path, array $meta): bool
    {
        $key = trim((string) ($meta['key'] ?? ''));
        if ($key === '' || !str_starts_with($key, 'web.')) {
            return false;
        }

        $name = trim((string) ($meta['name'] ?? ''));
        $description = trim((string) ($meta['description'] ?? ''));
        if ($name === '' || $name === 'route' || $description === '' || $description === 'route') {
            return false;
        }

        if (!empty($meta['skip_permission'])) {
            return false;
        }

        if (preg_match('#^/(error|403|404|500|autologout|login|logout|auth/logout|find-id|find-password|register|waiting-approval|2fa|password-change)#', $path)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('sitemapBuildModulesFromRouteMeta')) {
    function sitemapBuildModulesFromRouteMeta(array $routes, array $moduleSummaries, array $moduleOrder): array
    {
        $modules = [];

        foreach ($routes as $path => $route) {
            $meta = $route['permission'] ?? [];
            if (!sitemapShouldIncludeWebRoute((string) $path, $meta)) {
                continue;
            }

            $description = trim((string) ($meta['description'] ?? ''));
            $parts = array_values(array_filter(array_map(
                static fn($value) => trim((string) $value),
                explode('>', $description)
            ), static fn($value) => $value !== ''));

            if (count($parts) < 3) {
                continue;
            }

            [$moduleLabel, $menuLabel, $pageLabel] = array_slice($parts, 0, 3);
            if ($moduleLabel === '' || $menuLabel === '' || $pageLabel === '') {
                continue;
            }

            if (!isset($modules[$moduleLabel])) {
                $modules[$moduleLabel] = [
                    'title' => $moduleLabel,
                    'summary' => $moduleSummaries[$moduleLabel] ?? ($moduleLabel . ' 화면 목록'),
                    'items' => [],
                    '_menus' => [],
                ];
            }

            if (!isset($modules[$moduleLabel]['_menus'][$menuLabel])) {
                $modules[$moduleLabel]['_menus'][$menuLabel] = count($modules[$moduleLabel]['items']);
                $modules[$moduleLabel]['items'][] = [
                    'name' => $menuLabel,
                    'url' => (string) $path,
                    'status' => 'ui_only',
                    'children' => [],
                    '_pages' => [],
                ];
            }

            $menuIndex = $modules[$moduleLabel]['_menus'][$menuLabel];
            $pageKey = $pageLabel;
            if (isset($modules[$moduleLabel]['items'][$menuIndex]['_pages'][$pageKey])) {
                continue;
            }

            $modules[$moduleLabel]['items'][$menuIndex]['_pages'][$pageKey] = true;
            $modules[$moduleLabel]['items'][$menuIndex]['children'][] = [
                'name' => $pageLabel,
                'url' => (string) $path,
                'status' => 'ui_only',
            ];
        }

        $orderedModules = [];
        foreach ($moduleOrder as $moduleLabel) {
            if (isset($modules[$moduleLabel])) {
                $orderedModules[$moduleLabel] = $modules[$moduleLabel];
            }
        }

        foreach ($modules as $moduleLabel => $module) {
            if (!isset($orderedModules[$moduleLabel])) {
                $orderedModules[$moduleLabel] = $module;
            }
        }

        return array_values(array_map(static function (array $module) {
            unset($module['_menus']);
            foreach ($module['items'] as &$item) {
                unset($item['_pages']);
            }
            unset($item);
            return $module;
        }, $orderedModules));
    }
}

if (!function_exists('renderSitemapNodes')) {
    function renderSitemapNodes(array $items, array $statusMap): void
    {
        echo '<ul class="erp-sitemap__list">';

        foreach ($items as $item) {
            $status = $statusMap[$item['status']] ?? $statusMap['empty'];

            echo '<li class="erp-sitemap__item">';
            echo '<div class="erp-sitemap__row">';
            echo '<span class="erp-sitemap__status erp-sitemap__status--' . htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8') . '">';
            echo htmlspecialchars($status['icon'], ENT_QUOTES, 'UTF-8');
            echo '</span>';
            echo '<div class="erp-sitemap__body">';
            echo '<div class="erp-sitemap__meta">';
            echo '<a class="erp-sitemap__link" href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '">';
            echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
            echo '</a>';
            echo '<span class="erp-sitemap__phase">' . htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</div>';
            echo '<code class="erp-sitemap__url">' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '</code>';

            if (!empty($item['children']) && is_array($item['children'])) {
                echo '<div class="erp-sitemap__children">';
                renderSitemapNodes($item['children'], $statusMap);
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
            echo '</li>';
        }

        echo '</ul>';
    }
}

$webRoutes = sitemapLoadWebRoutes();
$modules = sitemapBuildModulesFromRouteMeta($webRoutes, $moduleSummaries, $moduleOrder);
?>

<main class="erp-sitemap">
    <div class="erp-sitemap__canvas"></div>

    <section class="erp-sitemap__hero">
        <div class="erp-sitemap__hero-copy">
            <span class="erp-sitemap__eyebrow">Developer Overview</span>
            <h1>ERP 전체 구조 / 개발 단계 맵</h1>
            <p>이 페이지는 메뉴 구조만 보여주는 사이트맵이 아니라, 각 모듈의 실제 URL과 개발 단계를 함께 확인하는 관리용 화면입니다.</p>
        </div>

        <div class="erp-sitemap__legend">
            <?php foreach ($statusMap as $status): ?>
                <span class="erp-sitemap__legend-chip erp-sitemap__legend-chip--<?= htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($status['icon'] . ' ' . $status['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="erp-sitemap__flow">
        <div class="erp-sitemap__flow-head">
            <span class="erp-sitemap__flow-label">데이터 흐름</span>
            <h2>현장 입력 이후 처리 흐름</h2>
            <p>현장관리에서 입력된 거래가 회계관리와 전자결재로 이어지는 대표 흐름입니다.</p>
        </div>

        <div class="erp-sitemap__flow-track" aria-label="현장관리에서 전자결재까지 데이터 흐름">
            <?php foreach ($flowSteps as $index => $step): ?>
                <a class="erp-sitemap__flow-node" href="<?= htmlspecialchars($step['url'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="erp-sitemap__flow-name"><?= htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <code class="erp-sitemap__flow-url"><?= htmlspecialchars($step['url'], ENT_QUOTES, 'UTF-8') ?></code>
                </a>
                <?php if ($index < count($flowSteps) - 1): ?>
                    <span class="erp-sitemap__flow-arrow" aria-hidden="true">→</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="erp-sitemap__grid">
        <?php foreach ($modules as $module): ?>
            <article class="erp-sitemap__card">
                <header class="erp-sitemap__card-head">
                    <h2><?= htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars($module['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                </header>
                <?php renderSitemapNodes($module['items'], $statusMap); ?>
            </article>
        <?php endforeach; ?>
    </section>
</main>
