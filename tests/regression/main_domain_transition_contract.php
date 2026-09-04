<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$index = (string) file_get_contents($root . '/index.php');
$view = (string) file_get_contents($root . '/app/views/main/index.php');
$css = (string) file_get_contents($root . '/public/assets/css/pages/main/index.css');
$routes = (string) file_get_contents($root . '/routes/web/main.php');
$resolver = (string) file_get_contents($root . '/core/PageKeyResolver.php');
$migration = (string) file_get_contents($root . '/app/migrations/20260904_02_complete_main_domain_registry.up.sql');
$mainScripts = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public/assets/js/pages/main'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'js') $mainScripts[] = $file->getPathname();
}

$failures = [];
if (!str_contains($index, "header('Location: /main')")) $failures[] = '기본 진입 URL이 /main이 아닙니다.';
if (!str_contains($view, 'class="main-home"')) $failures[] = 'Main View 최상위 class가 main-home이 아닙니다.';
if (str_contains($css, 'dashboard-main') || str_contains($css, 'dashboard-extra-vspace')) $failures[] = 'Main CSS에 구 dashboard 소유 명칭이 남아 있습니다.';
if (str_contains($routes, "'/dashboard") || str_contains($routes, 'redirectLegacyDashboard')) {
    $failures[] = 'Main Route에 구 /dashboard 호환 경로가 남아 있습니다.';
}
foreach (['dashboard.main', 'dashboard.calendar', 'dashboard.report', 'dashboard.activity', 'dashboard.notifications', 'dashboard.kpi'] as $legacyPageKey) {
    if (str_contains($resolver, "'{$legacyPageKey}'")) {
        $failures[] = 'PageKeyResolver에 구 Main Page Key가 남아 있습니다: ' . $legacyPageKey;
    }
}
foreach ($mainScripts as $file) {
    if (str_contains(str_replace('\\', '/', $file), '/main/calendar/')) continue;
    if (str_contains((string) file_get_contents($file), 'dashboard.settings.')) {
        $failures[] = '설정 JS에 구 dashboard.settings TableSettings Key가 남아 있습니다: ' . basename($file);
    }
}
foreach ([
    "WHERE `page_key` LIKE 'dashboard.%'",
    "WHERE `menu_key` LIKE 'dashboard.%'",
    "WHERE `page_key` LIKE 'dashboard.%';",
    "WHERE `default_route_url` LIKE '/dashboard/settings%'",
    "WHERE `default_entry` LIKE '/dashboard/settings%'",
] as $requiredSql) {
    if (!str_contains($migration, $requiredSql)) $failures[] = 'Main Registry Migration 계약이 누락됐습니다: ' . $requiredSql;
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Main domain transition contract PASS\n";
