<?php

use Core\Helpers\AssetHelper;

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
    ['label' => '거래관리 (transaction)', 'url' => '/site/transaction'],
    ['label' => '회계관리 (ledger)', 'url' => '/ledger'],
    ['label' => '전자결재 (approval)', 'url' => '/approval'],
];

$modules = [
    [
        'title' => '메인',
        'summary' => '전사 공통 대시보드와 운영 관리 화면',
        'items' => [
            ['name' => '대시보드', 'url' => '/dashboard', 'status' => 'full'],
            ['name' => '통합보고서', 'url' => '/dashboard/report', 'status' => 'ui_only'],
            ['name' => '일정/캘린더', 'url' => '/dashboard/calendar', 'status' => 'ui_only'],
            ['name' => '최근활동', 'url' => '/dashboard/activity', 'status' => 'ui_only'],
            ['name' => '알림', 'url' => '/dashboard/notifications', 'status' => 'ui_only'],
            ['name' => 'KPI', 'url' => '/dashboard/kpi', 'status' => 'ui_only'],
            ['name' => '설정', 'url' => '/dashboard/settings', 'status' => 'ui_only'],
        ],
    ],
    [
        'title' => '내부문서',
        'summary' => '내부문서 관리 및 조회 화면',
        'items' => [
            ['name' => '대시보드', 'url' => '/document', 'status' => 'ui_only'],
            ['name' => '문서 등록', 'url' => '/document/file_register', 'status' => 'ui_only'],
            ['name' => '문서 상세', 'url' => '/document/view', 'status' => 'ui_only'],
            ['name' => '문서 수정', 'url' => '/document/edit', 'status' => 'ui_only'],
            ['name' => '문서 통계', 'url' => '/document/stats', 'status' => 'ui_only'],
        ],
    ],
    [
        'title' => '전자결재',
        'summary' => '결재 작성, 진행, 상태 추적 영역',
        'items' => [
            ['name' => '전자결재 메인', 'url' => '/approval', 'status' => 'ui_only'],
            ['name' => '지출결의서', 'url' => '/approval/write_expenditure', 'status' => 'ui_only'],
            ['name' => '구매요청서', 'url' => '/approval/write_purchase_request', 'status' => 'ui_only'],
            ['name' => '휴가요청서', 'url' => '/approval/write_leave_request', 'status' => 'ui_only'],
            ['name' => '출장보고서', 'url' => '/approval/write_trip_report', 'status' => 'ui_only'],
            ['name' => '업무보고서', 'url' => '/approval/write_work_report', 'status' => 'ui_only'],
            ['name' => '실행검토요청', 'url' => '/approval/write_review_request', 'status' => 'ui_only'],
            ['name' => '기성검토요청', 'url' => '/approval/write_progress_review', 'status' => 'ui_only'],
            ['name' => '외화송금결재', 'url' => '/approval/write_foreign_remit', 'status' => 'ui_only'],
            ['name' => '자유양식기안문', 'url' => '/approval/write_free_draft', 'status' => 'ui_only'],
            ['name' => '결재현황', 'url' => '/approval/status', 'status' => 'ui_only'],
            ['name' => '결재진행 추적', 'url' => '/approval/request/detail', 'status' => 'empty'],
        ],
    ],
    [
        'title' => '회계관리',
        'summary' => '계정과목, 전표입력, 장부/결산 기반 영역',
        'items' => [
            ['name' => '회계 대시보드', 'url' => '/ledger', 'status' => 'ui_only'],
            ['name' => '계정과목', 'url' => '/ledger/accounts', 'status' => 'ui_only'],
            ['name' => '전표입력', 'url' => '/ledger/journal', 'status' => 'ui_only'],
            ['name' => '전표 검색', 'url' => '/ledger/search', 'status' => 'empty'],
            ['name' => '관리 부가 메뉴', 'url' => '/ledger', 'status' => 'ui_only'],
        ],
    ],
    [
        'title' => '대외기관업무',
        'summary' => '기관별 신고 및 접수 업무 허브',
        'items' => [
            ['name' => '대시보드', 'url' => '/institution', 'status' => 'ui_only'],
            ['name' => '세무서', 'url' => '/institution/tax_office', 'status' => 'empty'],
            ['name' => '지방자치단체', 'url' => '/institution/local_government', 'status' => 'empty'],
            ['name' => '근로복지공단', 'url' => '/institution/welfare_corp', 'status' => 'empty'],
            ['name' => '건강보험공단', 'url' => '/institution/health_insurance', 'status' => 'empty'],
            ['name' => '국민연금공단', 'url' => '/institution/pension', 'status' => 'empty'],
            ['name' => '신용보증기금', 'url' => '/institution/credit_guarantee', 'status' => 'empty'],
            ['name' => '건설협회', 'url' => '/institution/construction_assoc', 'status' => 'empty'],
            ['name' => '전문건설공제조합', 'url' => '/institution/construction_union', 'status' => 'empty'],
            ['name' => '기술인협회', 'url' => '/institution/engineer_assoc', 'status' => 'empty'],
            ['name' => '건설근로자공제회', 'url' => '/institution/construction_worker_union', 'status' => 'empty'],
        ],
    ],
    [
        'title' => '현장관리',
        'summary' => '현장 운영과 거래 입력 기반 화면',
        'items' => [
            ['name' => '대시보드', 'url' => '/site', 'status' => 'ui_only'],
            ['name' => '견적관리', 'url' => '/site/estimate', 'status' => 'empty'],
            ['name' => '계약관리', 'url' => '/site/contract', 'status' => 'empty'],
            ['name' => '실행관리', 'url' => '/site/execution', 'status' => 'empty'],
            ['name' => '보증/보험관리', 'url' => '/site/guarantee', 'status' => 'empty'],
            ['name' => '기성확정내역', 'url' => '/site/progress', 'status' => 'empty'],
            ['name' => '시공기성확정내역', 'url' => '/site/construction_progress', 'status' => 'empty'],
            [
                'name' => '거래관리',
                'url' => '/site/transaction',
                'status' => 'full',
                'children' => [
                    ['name' => '거래입력', 'url' => '/site/transaction/create', 'status' => 'full'],
                    ['name' => '거래내역', 'url' => '/site/transaction', 'status' => 'api_only'],
                ],
            ],
            ['name' => '안전관리', 'url' => '/site/safety', 'status' => 'empty'],
        ],
    ],
    [
        'title' => '공지/회의',
        'summary' => '사내 공지와 회의 게시 영역',
        'items' => [
            ['name' => '공지/회의 메인', 'url' => '/notice', 'status' => 'ui_only'],
            ['name' => '직원별공지', 'url' => '/notice/employee', 'status' => 'empty'],
            ['name' => '부서별공지', 'url' => '/notice/department', 'status' => 'empty'],
            ['name' => '전체공지', 'url' => '/notice/all', 'status' => 'empty'],
        ],
    ],
    [
        'title' => '기타',
        'summary' => '공통 진입 및 보조 페이지',
        'items' => [
            ['name' => '홈', 'url' => '/home', 'status' => 'ui_only'],
            ['name' => '프로필', 'url' => '/profile', 'status' => 'ui_only'],
            ['name' => '사이트맵', 'url' => '/sitemap', 'status' => 'ui_only'],
        ],
    ],
];

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
            <p>현장관리에서 입력된 거래가 회계관리와 전자결재로 이어지는 핵심 흐름입니다.</p>
        </div>

        <div class="erp-sitemap__flow-track" aria-label="현장관리에서 전자결재까지 데이터 흐름">
            <?php foreach ($flowSteps as $index => $step): ?>
                <a class="erp-sitemap__flow-node" href="<?= htmlspecialchars($step['url'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="erp-sitemap__flow-name"><?= htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <code class="erp-sitemap__flow-url"><?= htmlspecialchars($step['url'], ENT_QUOTES, 'UTF-8') ?></code>
                </a>
                <?php if ($index < count($flowSteps) - 1): ?>
                    <span class="erp-sitemap__flow-arrow" aria-hidden="true">↓</span>
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
