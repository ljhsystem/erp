<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$render = static function (string $path): string {
    $_SERVER['REQUEST_URI'] = $path;
    $ui = ['sidebar_default' => 'expanded'];
    ob_start();
    require PROJECT_ROOT . '/app/views/layout/sidebar.php';
    return (string) ob_get_clean();
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$benchmarkStart = hrtime(true);
$humanResources = '';
for ($iteration = 0; $iteration < 200; $iteration++) {
    $humanResources = $render('/institution/human-resources/employment-contracts');
}
$renderAverageMs = ((hrtime(true) - $benchmarkStart) / 1_000_000) / 200;
$assert(str_contains($humanResources, 'id="menu-institution-human-resources" class="collapse show"'), '인사·노무관리 카테고리가 SSR에서 펼쳐지지 않았습니다.');
$assert(str_contains($humanResources, 'href="#menu-institution-human-resources" class="nav-link toggle selected" aria-expanded="true"'), '인사·노무관리 토글의 최초 접근성 상태가 잘못되었습니다.');
$assert(str_contains($humanResources, 'href="/institution/human-resources/employment-contracts" class="nav-link active" aria-current="page"'), '근로계약 현재 메뉴가 SSR에서 활성화되지 않았습니다.');

$ledger = $render('/ledger/settings/journal-rules');
$assert(str_contains($ledger, 'id="menu-ledger-basic" class="collapse show"'), '회계 기초정보 카테고리가 SSR에서 펼쳐지지 않았습니다.');
$assert(str_contains($ledger, 'href="/ledger/settings/journal-rules" class="nav-link active" aria-current="page"'), '분개규칙 현재 메뉴가 SSR에서 활성화되지 않았습니다.');

$ledgerAlias = $render('/ledger/journal');
$assert(str_contains($ledgerAlias, 'id="menu-ledger-voucher" class="collapse show"'), '전표관리 별칭 경로의 카테고리가 SSR에서 펼쳐지지 않았습니다.');
$assert(str_contains($ledgerAlias, 'href="/ledger/vouchers/input" class="nav-link active" aria-current="page"'), '전표관리 별칭 경로가 SSR에서 활성화되지 않았습니다.');

$income = $render('/institution/income-data/regular-employment');
$assert(str_contains($income, 'id="menu-institution-income-data" class="collapse show"'), '소득자료관리 카테고리가 SSR에서 펼쳐지지 않았습니다.');
$assert(str_contains($income, 'href="/institution/income-data/regular-employment" class="nav-link active" aria-current="page"'), '상용근로소득 현재 메뉴가 SSR에서 활성화되지 않았습니다.');

$script = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/layout/sidebar.js') ?: '';
$css = file_get_contents(PROJECT_ROOT . '/public/assets/css/pages/layout/sidebar.css') ?: '';
$assert(!str_contains($script, 'routeMenuId('), 'JS 하드코딩 메뉴 매핑이 남아 있습니다.');
$assert(!str_contains($script, "setMenuOpen(sidebar, menu, menu === activeMenu)"), 'JS 초기 collapse→expand 경로가 남아 있습니다.');
$assert(str_contains($script, "if(!activeMenu.classList.contains('show')"), '정상 SSR 상태의 무변경 hydration guard가 없습니다.');
$assert(str_contains($script, "sidebar.classList.remove('sidebar-initializing')"), '초기 transition 해제 시점이 없습니다.');
$assert(str_contains($css, '.sidebar.sidebar-initializing'), '초기 transition 차단 CSS가 없습니다.');

echo json_encode([
    'success' => true,
    'average_render_ms' => round($renderAverageMs, 4),
    'html_bytes' => strlen($humanResources),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
