<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only');
}

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

require_once PROJECT_ROOT . '/core/Storage.php';
require_once PROJECT_ROOT . '/core/Bootstrap.php';

use App\Services\Backup\DatabaseSyncService;
use Core\DbPdo;
use Core\LoggerFactory;
use function Core\storage_system_path;

$logger = LoggerFactory::getLogger('cli-sync-runner');
$startedAt = date('Y-m-d H:i:s');
$backupDir = storage_system_path('db_backup');
$lockPath = $backupDir ? rtrim(str_replace('\\', '/', $backupDir), '/') . '/secondary_restore_runner.lock' : null;

$writeLock = static function (string $state, array $extra = []) use ($lockPath, $startedAt): void {
    if (!$lockPath) {
        return;
    }

    $payload = array_merge([
        'pid' => getmypid(),
        'state' => $state,
        'started_at' => $startedAt,
        'heartbeat_at' => date('Y-m-d H:i:s'),
    ], $extra);

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        return;
    }

    @file_put_contents($lockPath, $json);
};

try {
    $writeLock('running');

    $service = new DatabaseSyncService(DbPdo::conn());
    $result = $service->runLatestBackupSync('sync');

    $payload = [
        'started_at' => $startedAt,
        'finished_at' => date('Y-m-d H:i:s'),
        'success' => (bool) ($result['success'] ?? false),
        'state' => $result['state'] ?? null,
        'message' => $result['message'] ?? '',
        'file' => $result['file'] ?? null,
        'stage' => $result['stage'] ?? null,
        'active_db' => $result['active_db']['label'] ?? null,
        'standby_db' => $result['standby_db']['label'] ?? null,
        'statement_count' => $result['statement_count'] ?? null,
    ];

    $output = '[sync_runner] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!empty($result['success'])) {
        $writeLock('completed', ['finished_at' => date('Y-m-d H:i:s')]);
        $logger->info('Sync runner completed', $payload);
        fwrite(STDOUT, $output . PHP_EOL);
        if ($lockPath && is_file($lockPath)) {
            @unlink($lockPath);
        }
        exit(0);
    }

    $writeLock('failed', ['finished_at' => date('Y-m-d H:i:s')]);
    $logger->error('Sync runner failed', $payload);
    fwrite(STDERR, $output . PHP_EOL);
    if ($lockPath && is_file($lockPath)) {
        @unlink($lockPath);
    }
    exit(1);
} catch (\Throwable $e) {
    $payload = [
        'started_at' => $startedAt,
        'finished_at' => date('Y-m-d H:i:s'),
        'success' => false,
        'message' => $e->getMessage(),
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];

    $writeLock('failed', ['finished_at' => date('Y-m-d H:i:s'), 'error' => $e->getMessage()]);
    $logger->error('Sync runner crashed', $payload);
    fwrite(
        STDERR,
        '[sync_runner] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );

    if ($lockPath && is_file($lockPath)) {
        @unlink($lockPath);
    }

    exit(1);
}
