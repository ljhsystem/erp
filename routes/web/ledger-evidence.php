<?php

global $router;

$evidencePageMeta = [
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '화면조회',
    'permission_description' => '증빙원본 화면 조회',
    'name' => '증빙원본',
    'description' => '회계관리 > 자료관리 > 증빙원본',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
];

$registerEvidencePage = static function (string $path, string $key, bool $skipPermission = true) use ($router, $evidencePageMeta): void {
    $router->get($path, 'EvidenceController@webTypePage', array_merge($evidencePageMeta, [
        'key' => $key,
        'skip_permission' => $skipPermission,
    ]));
};

$router->get('/ledger/data', 'EvidenceController@webIndex', array_merge($evidencePageMeta, [
    'key' => 'web.ledger.data.index',
]));

$router->get('/ledger/data/list', 'EvidenceController@webList', array_merge($evidencePageMeta, [
    'key' => 'web.ledger.data.list',
]));

$registerEvidencePage('/ledger/data/bank-transactions', 'web.ledger.data.bank-transactions');
$registerEvidencePage('/ledger/data/tax-invoices', 'web.ledger.data.tax-invoices');
$registerEvidencePage('/ledger/data/manual-tax-invoices', 'web.ledger.data.manual-tax-invoices');
$registerEvidencePage('/ledger/data/card-hometax', 'web.ledger.data.card-hometax');
$registerEvidencePage('/ledger/data/card-approvals', 'web.ledger.data.card-approvals');
$registerEvidencePage('/ledger/data/card-statements', 'web.ledger.data.card-statements');
$registerEvidencePage('/ledger/data/cash-receipts', 'web.ledger.data.cash-receipts');
$registerEvidencePage('/ledger/data/cash-receipt-purchases', 'web.ledger.data.cash-receipt-purchases');
$registerEvidencePage('/ledger/data/cash-receipt-sales', 'web.ledger.data.cash-receipt-sales');
$registerEvidencePage('/ledger/data/import-invoices', 'web.ledger.data.import-invoices');
$registerEvidencePage('/ledger/data/shopping-orders', 'web.ledger.data.shopping-orders');
$registerEvidencePage('/ledger/data/payroll-withholdings', 'web.ledger.data.payroll-withholdings');
$registerEvidencePage('/ledger/data/business-data', 'web.ledger.data.business-data');
$registerEvidencePage('/ledger/data/payroll', 'web.ledger.data.payroll');
$registerEvidencePage('/ledger/data/business-income', 'web.ledger.data.business-income');
$registerEvidencePage('/ledger/data/employee-expenses', 'web.ledger.data.employee-expenses');
$registerEvidencePage('/ledger/data/construction', 'web.ledger.data.construction');
