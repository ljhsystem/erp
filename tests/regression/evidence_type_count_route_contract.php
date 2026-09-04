<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$route = file_get_contents($root . '/routes/api/materials.php');
$search = file_get_contents($root . '/public/assets/js/pages/ledger/evidence-list/search.js');

if (!is_string($route) || !is_string($search)) {
    throw new RuntimeException('증빙원본 탭 카운트 계약 파일을 읽을 수 없습니다.');
}

$checks = [
    'route_uses_post' => str_contains(
        $route,
        "\$router->post('/api/import/evidences', 'EvidenceListController@apiList'"
    ),
    'count_request_uses_official_route' => str_contains(
        $search,
        'fetch(`${API.seedRows}?type_counts=1`'
    ),
    'count_request_uses_post' => preg_match(
        '/fetch\(`\$\{API\.seedRows\}\?type_counts=1`,\s*\{\s*method:\s*\'POST\'/s',
        $search
    ) === 1,
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode([
    'success' => $failed === [],
    'checks' => $checks,
    'failed' => $failed,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

exit($failed === [] ? 0 : 1);
