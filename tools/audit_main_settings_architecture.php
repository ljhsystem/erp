<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$scanFiles = static function (array $directories, string $extension) use ($root): array {
    $files = [];
    foreach ($directories as $directory) {
        $path = $root . '/' . $directory;
        if (!is_dir($path)) continue;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === $extension) $files[] = $file->getPathname();
        }
    }
    return $files;
};
$relative = static fn(string $path): string => str_replace('\\', '/', substr($path, strlen($root) + 1));

$controllerFiles = $scanFiles(['app/Controllers/Main/Settings'], 'php');
$serviceFiles = $scanFiles(['app/Services/System', 'app/Services/Auth'], 'php');
$modelFiles = $scanFiles(['app/Models/System', 'app/Models/Auth'], 'php');
$viewFiles = $scanFiles(['app/views/main/settings'], 'php');
$jsFiles = $scanFiles(['public/assets/js/pages/main/settings'], 'js');

$sqlPattern = '/(?:\$this->(?:db|pdo)|\$(?:db|pdo))->(?:query|prepare|exec)\s*\(/i';
foreach (array_merge($controllerFiles, $serviceFiles, $viewFiles) as $file) {
    if (preg_match($sqlPattern, (string) file_get_contents($file))) $failures[] = 'Model/Repository 외 SQL: ' . $relative($file);
}
$logPattern = '/(?:LoggerFactory|error_log\s*\(|->(?:debug|info|warning|error|critical)\s*\()/';
foreach (array_merge($controllerFiles, $modelFiles, $viewFiles) as $file) {
    if (preg_match($logPattern, (string) file_get_contents($file))) $failures[] = 'Service 외 로그: ' . $relative($file);
}
foreach ([[$controllerFiles, 1500, 'Controller'], [$serviceFiles, 1500, 'Service'], [$modelFiles, 1000, 'Model'], [$jsFiles, 1500, 'JS']] as [$files, $limit, $label]) {
    foreach ($files as $file) {
        $lines = count(file($file) ?: []);
        if ($lines > $limit) $failures[] = "{$label} 라인 한도 초과({$lines}>{$limit}): " . $relative($file);
    }
}
foreach ($jsFiles as $file) {
    $content = (string) file_get_contents($file);
    if (str_contains($content, 'createDataTable(')
        && !str_contains($content, '/common/table/data-table.js')
        && preg_match('/\bcreateDataTable\s*,/', $content) !== 1) {
        $failures[] = '비공용 DataTable 진입: ' . $relative($file);
    }
    if (preg_match('/\b(?:localStorage|sessionStorage)\b/', $content)) $failures[] = '브라우저 저장소 직접 사용: ' . $relative($file);
    if (preg_match('/\b(?:eval|Function)\s*\(/', $content) || str_contains($content, 'String.raw')) $failures[] = '문자열 실행 기반 JS: ' . $relative($file);
}

$legacyFiles = [
    'app/views/main/settings/base-info/brand-logo.php', 'app/views/main/settings/base-info/clients.php',
    'app/views/main/settings/base-info/projects.php', 'app/views/main/settings/base-info/cards.php',
    'app/views/main/settings/organization/employees.php', 'app/views/main/settings/organization/approval.php',
    'public/assets/js/pages/main/settings/organization/employees.js',
    'public/assets/js/pages/main/settings/organization/approval.templates.js',
    'public/assets/css/pages/main/settings/brand-logo.css', 'public/assets/css/pages/main/settings/approval.css',
];
foreach ($legacyFiles as $file) if (is_file($root . '/' . $file)) $failures[] = '복제 Alias 파일: ' . $file;

$routeContent = (string) file_get_contents($root . '/routes/web/settings.php');
foreach (['brand-logo','base-info/clients','base-info/projects','base-info/bank-accounts','base-info/cards','base-info/work-teams','organization/employees','organization/departments','organization/positions','organization/approval\''] as $legacyRoute) {
    if (str_contains($routeContent, $legacyRoute)) $failures[] = '복제 Settings Route: ' . $legacyRoute;
}

$result = [
    'passed' => $failures === [],
    'counts' => [
        'controllers' => count($controllerFiles), 'services' => count($serviceFiles),
        'models' => count($modelFiles), 'views' => count($viewFiles), 'javascript' => count($jsFiles),
        'violations' => count($failures),
    ],
    'violations' => $failures,
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($failures === [] ? 0 : 1);
