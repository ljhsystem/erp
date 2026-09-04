<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Repositories\Ledger\EvidenceSourceRepository;
use Core\DbPdo;

$repository = new EvidenceSourceRepository(DbPdo::conn());
try {
    $result = $repository->pagedProjections([
        'import_types' => [],
        'keyword' => '',
        'unlinked_voucher_only' => true,
        'exclude_evidences' => [],
        'start' => 0,
        'length' => 10,
        'order_field' => 'standard_date',
        'order_direction' => 'DESC',
        'filters' => [],
    ]);
    echo json_encode(['success' => true, 'count' => count($result['rows'] ?? []), 'total' => $result['total'] ?? null], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
