<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\Ledger\BookService;
use Core\DbPdo;

$service = new BookService(DbPdo::conn());
$read = static fn(string $path): string => (string) file_get_contents(PROJECT_ROOT . '/' . $path);
$request = ['start'=>0,'length'=>100,'order'=>[['column'=>0,'dir'=>'asc']],'columns'=>[['data'=>'voucher_date']]];
$official = $service->getJournalPage($request, []);
$checks = [
    'web route connected' => str_contains($read('routes/web/ledger.php'), "'/ledger/book/journal', 'BookController@journal'"),
    'api route connected' => str_contains($read('routes/api/ledger.php'), "'/api/ledger/book/journal/list', 'BookController@apiJournalList'"),
    'common datatable used' => str_contains($read('public/assets/js/pages/ledger/book/index.js'), 'createDataTable'),
    'common search form used' => str_contains($read('public/assets/js/pages/ledger/book/index.js'), 'SearchForm'),
    'common table settings used' => str_contains($read('public/assets/js/pages/ledger/book/index.js'), 'tableSettings:'),
    'read only ui contract' => !preg_match('/trash|deleteButton:\s*true|selectable:\s*true/', $read('public/assets/js/pages/ledger/book/index.js')),
    'only posted and closed vouchers returned' => count(array_filter($official['rows'], static fn(array $row): bool => !in_array(strtoupper((string) ($row['voucher_status'] ?? '')), ['POSTED','CLOSED'], true))) === 0,
    'debit credit balanced' => abs((float) $official['summary']['difference']) < 0.0001,
];
$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['passed'=>$failed===[],'checks'=>$checks,'official_summary'=>$official['summary'],'failed'=>$failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
