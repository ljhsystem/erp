<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';

use App\Services\System\SystemLogService;

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'erp-system-log-' . bin2hex(random_bytes(6));
assert(mkdir($directory, 0700, true));
$file = $directory . DIRECTORY_SEPARATOR . 'service-test-domain-2026-09-04.log';
$entry = json_encode([
    'message' => '테스트 업무가 완료되었습니다.',
    'context' => ['event_code'=>'TEST_COMPLETED','result'=>'SUCCESS','request_id'=>'request-1','error'=>'노출 금지 상세'],
    'level_name' => 'INFO',
    'datetime' => '2026-09-04T10:00:00+09:00',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
assert(file_put_contents($file, $entry . PHP_EOL) !== false);

try {
    $service = new SystemLogService($directory);
    $summary = $service->summary();
    assert($summary['total_count'] === 1);
    assert($summary['files'][0]['name'] === basename($file));
    $view = $service->view(basename($file));
    assert(str_contains($view['content'], '테스트 업무가 완료되었습니다.'));
    assert(str_contains($view['content'], '사건: TEST_COMPLETED'));
    assert(!str_contains($view['content'], '노출 금지 상세'));
    assert($view['technical_content_available'] === true);
    try {$service->view('../outside.log');assert(false);}catch(InvalidArgumentException){}
} finally {
    if (is_file($file)) unlink($file);
    if (is_dir($directory)) rmdir($directory);
}

echo json_encode(['success'=>true,'summary'=>true,'safe_view'=>true,'path_guard'=>true],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
