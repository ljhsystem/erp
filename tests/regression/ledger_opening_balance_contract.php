<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = [
    'canonical web route' => str_contains($read('routes/web/ledger.php'), "'/ledger/settings/opening-balances', 'OpeningBalanceController@index'"),
    'legacy web route removed' => !str_contains($read('routes/web/ledger.php'), "\$router->get('/ledger/opening-balances'"),
    'api route set' => substr_count($read('routes/api/ledger.php'), '/api/ledger/opening-balance/') === 11,
    'one relation table' => str_contains($read('app/migrations/20260904_05_create_ledger_opening_balances.up.sql'), 'CREATE TABLE `ledger_opening_balances`'),
    'voucher line reuse' => str_contains($read('app/Models/Ledger/OpeningBalanceModel.php'), 'ledger_voucher_lines'),
    'no duplicate amount table' => !str_contains($read('app/migrations/20260904_05_create_ledger_opening_balances.up.sql'), 'ledger_opening_balance_lines'),
    'service uses voucher service' => str_contains($read('app/Services/Ledger/OpeningBalanceService.php'), 'new VoucherService($pdo)'),
    'outer transaction ownership' => substr_count($read('app/Services/Ledger/VoucherService.php'), '$ownsTransaction = !$this->pdo->inTransaction();') >= 2,
    'common datatable' => str_contains($read('public/assets/js/pages/ledger/opening-balances/index.js'), 'createDataTable'),
    'common actor' => str_contains($read('public/assets/js/pages/ledger/opening-balances/index.js'), "actorColumn('updated_by'"),
    'table settings' => str_contains($read('public/assets/js/pages/ledger/opening-balances/index.js'), "metaDomain: 'opening-balance'"),
    'trigger prohibited' => stripos($read('app/migrations/20260904_05_create_ledger_opening_balances.up.sql'), 'TRIGGER') === false,
];
$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['passed'=>$failed === [],'checks'=>$checks,'failed'=>$failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
