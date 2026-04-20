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

use App\Services\Backup\DatabaseBackupService;
use Core\DbPdo;
use Core\LoggerFactory;

$logger = LoggerFactory::getLogger('cli-backup-runner');
$startedAt = date('Y-m-d H:i:s');

try {
    $service = new DatabaseBackupService(DbPdo::conn());
    $result = $service->runAutoBackup();

    $payload = [
        'started_at' => $startedAt,
        'finished_at' => date('Y-m-d H:i:s'),
        'success' => (bool)($result['success'] ?? false),
        'skipped' => (bool)($result['skipped'] ?? false),
        'message' => $result['message'] ?? '',
        'filename' => $result['filename'] ?? null,
        'time' => $result['time'] ?? null,
        'size' => $result['size'] ?? null,
        'schedule' => $result['schedule'] ?? null,
        'backup_time' => $result['backup_time'] ?? null,
        'secondary_restore' => $result['secondary_restore'] ?? null,
    ];

    $stdout = '[backup_runner] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!empty($result['success'])) {
        $logger->info('Auto backup runner completed', $payload);
        fwrite(STDOUT, $stdout . PHP_EOL);
        exit(0);
    }

    if (!empty($result['skipped'])) {
        $logger->info('Auto backup runner skipped', $payload);
        fwrite(STDOUT, $stdout . PHP_EOL);
        exit(0);
    }

    $logger->error('Auto backup runner failed', $payload);
    fwrite(STDERR, $stdout . PHP_EOL);
    exit(1);
} catch (\Throwable $e) {
    $payload = [
        'started_at' => $startedAt,
        'finished_at' => date('Y-m-d H:i:s'),
        'success' => false,
        'skipped' => false,
        'message' => $e->getMessage(),
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ];

    $logger->error('Auto backup runner crashed', $payload);
    fwrite(
        STDERR,
        '[backup_runner] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
    exit(1);
}
