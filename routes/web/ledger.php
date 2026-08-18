<?php

global $router;

$router->get('/ledger', 'LedgerController@webIndex', [
    'key' => 'web.ledger.dashboard',
    'page' => '회계대시보드',
    'page_description' => '회계 대시보드',
    'permission_name' => '화면조회',
    'permission_description' => '회계대시보드 화면 조회',
    'name' => '회계대시보드',
    'description' => '회계관리 > 회계 > 회계대시보드',
    'category' => '회계관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/settings/accounts', 'ChartAccountController@index', [
    'key' => 'web.ledger.settings.accounts',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '화면조회',
    'permission_description' => '계정과목 화면 조회',
    'name' => '계정과목',
    'description' => '회계관리 > 기초정보관리 > 계정과목',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/settings/opening-balances', 'LedgerController@webPlaceholder', [
    'key' => 'web.ledger.settings.opening_balances',
    'page' => '기초금액',
    'page_description' => '기초금액 관리',
    'permission_name' => '화면조회',
    'permission_description' => '기초금액 화면 조회',
    'name' => '기초금액',
    'description' => '회계관리 > 기초정보관리 > 기초금액',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/data/evidence-metadata', 'EvidenceMetadataController@index', [
    'key' => 'web.ledger.evidence_metadata',
    'page' => '증빙정책',
    'page_description' => '증빙 자료유형별 정책 관리',
    'permission_name' => '화면조회',
    'permission_description' => '증빙정책 화면 조회',
    'name' => '증빙정책',
    'description' => '회계관리 > 자료관리 > 증빙정책',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/data/raw', 'EvidenceController@webRaw', [
    'key' => 'web.ledger.data.raw',
    'page' => '원본자료',
    'page_description' => '원본자료 관리',
    'permission_name' => '화면조회',
    'permission_description' => '원본자료 화면 조회',
    'name' => '원본자료',
    'description' => '회계관리 > 자료관리 > 원본자료',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/transactions/input', 'TransactionController@webLedgerTransaction', [
    'key' => 'web.ledger.transactions.input',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '화면조회',
    'permission_description' => '거래입력 화면 조회',
    'name' => '거래입력',
    'description' => '회계관리 > 전표관리 > 거래입력',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/transactions', 'TransactionController@webLedgerCreate', [
    'key' => 'web.ledger.transactions.index',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '화면조회',
    'permission_description' => '거래입력 화면 조회',
    'name' => '거래입력',
    'description' => '회계관리 > 전표관리 > 거래입력',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/transactions/create', 'TransactionController@webLedgerCreate', [
    'key' => 'web.ledger.transactions.create',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '화면조회',
    'permission_description' => '거래입력 화면 조회',
    'name' => '거래입력',
    'description' => '회계관리 > 전표관리 > 거래입력',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/vouchers/input', 'VoucherController@webInput', [
    'key' => 'web.ledger.vouchers.input',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '화면조회',
    'permission_description' => '전표입력 화면 조회',
    'name' => '전표입력',
    'description' => '회계관리 > 전표관리 > 전표입력',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/journal', 'VoucherController@webInput', [
    'key' => 'web.ledger.journal',
    'page' => '분개장',
    'page_description' => '분개장 관리',
    'permission_name' => '화면조회',
    'permission_description' => '분개장 화면 조회',
    'name' => '분개장',
    'description' => '회계관리 > 장부관리 > 분개장',
    'category' => '회계관리 > 장부관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/transaction', 'TransactionController@webLedgerCreate', [
    'key' => 'web.ledger.transaction.index',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '화면조회',
    'permission_description' => '거래입력 화면 조회',
    'name' => '거래입력',
    'description' => '회계관리 > 전표관리 > 거래입력',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/transaction/create', 'TransactionController@webLedgerCreate', [
    'key' => 'web.ledger.transaction.create',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '화면조회',
    'permission_description' => '거래입력 화면 조회',
    'name' => '거래입력',
    'description' => '회계관리 > 전표관리 > 거래입력',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/vouchers/review', 'LedgerController@webVoucherReview', [
    'key' => 'web.ledger.vouchers.review',
    'page' => '전표검토·전기',
    'page_description' => '전표 검토 및 승인',
    'permission_name' => '화면조회',
    'permission_description' => '전표검토·전기 화면 조회',
    'name' => '전표검토·전기',
    'description' => '회계관리 > 전표관리 > 전표검토·전기',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/settings/journal-rules', 'JournalRuleController@index', [
    'key' => 'web.ledger.journal_rules',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '화면조회',
    'permission_description' => '분개규칙 화면 조회',
    'name' => '분개규칙',
    'description' => '회계관리 > 기초정보관리 > 분개규칙',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/data/upload', 'EvidenceController@webUpload', [
    'key' => 'web.ledger.data.upload',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => '화면조회',
    'permission_description' => '자료업로드 화면 조회',
    'name' => '자료업로드',
    'description' => '회계관리 > 자료관리 > 자료업로드',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/funds', 'FundsOverviewController@index', [
    'key' => 'web.ledger.funds.account_balances',
    'page' => '자금현황',
    'page_description' => '보유 자금 및 계좌별 잔액 현황',
    'permission_name' => '화면조회',
    'permission_description' => '자금현황 화면 조회',
    'name' => '자금현황',
    'description' => '회계관리 > 자금관리 > 자금현황',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/funds/account-transactions', 'BankTransactionReportController@index', [
    'key' => 'web.ledger.funds.bank_transactions',
    'page' => '계좌별거래내역',
    'page_description' => '계좌별 거래내역 관리',
    'permission_name' => '화면조회',
    'permission_description' => '계좌별거래내역 화면 조회',
    'name' => '계좌별거래내역',
    'description' => '회계관리 > 자금관리 > 계좌별거래내역',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);


$router->get('/ledger/opening-balances', 'PlaceholderController@index', [
    'key' => 'web.ledger.opening_balances',
    'page' => '기초금액',
    'page_description' => '기초금액 관리',
    'permission_name' => '화면조회',
    'permission_description' => '기초금액 화면 조회',
    'name' => '기초금액',
    'description' => '회계관리 > 기초정보관리 > 기초금액',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/book/journal', 'PlaceholderController@index', [
    'key' => 'web.ledger.book.journal',
    'page' => '분개장',
    'page_description' => '분개장 관리',
    'permission_name' => '화면조회',
    'permission_description' => '분개장 화면 조회',
    'name' => '분개장',
    'description' => '회계관리 > 장부관리 > 분개장',
    'category' => '회계관리 > 장부관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/book/account', 'PlaceholderController@index', [
    'key' => 'web.ledger.book.account',
    'page' => '총계정원장',
    'page_description' => '총계정원장 관리',
    'permission_name' => '화면조회',
    'permission_description' => '총계정원장 화면 조회',
    'name' => '총계정원장',
    'description' => '회계관리 > 장부관리 > 총계정원장',
    'category' => '회계관리 > 장부관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/book/general', 'PlaceholderController@index', [
    'key' => 'web.ledger.book.general',
    'page' => '합계잔액시산표',
    'page_description' => '합계잔액시산표 관리',
    'permission_name' => '화면조회',
    'permission_description' => '합계잔액시산표 화면 조회',
    'name' => '합계잔액시산표',
    'description' => '회계관리 > 장부관리 > 합계잔액시산표',
    'category' => '회계관리 > 장부관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/book/partner', 'PlaceholderController@index', [
    'key' => 'web.ledger.book.partner',
    'page' => '거래처원장',
    'page_description' => '거래처원장 관리',
    'permission_name' => '화면조회',
    'permission_description' => '거래처원장 화면 조회',
    'name' => '거래처원장',
    'description' => '회계관리 > 장부관리 > 거래처원장',
    'category' => '회계관리 > 장부관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/book/project', 'PlaceholderController@index', [
    'key' => 'web.ledger.book.project',
    'page' => '현장원장',
    'page_description' => '현장원장 관리',
    'permission_name' => '화면조회',
    'permission_description' => '현장원장 화면 조회',
    'name' => '현장원장',
    'description' => '회계관리 > 장부관리 > 현장원장',
    'category' => '회계관리 > 장부관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/book/daily', 'PlaceholderController@index', [
    'key' => 'web.ledger.book.daily',
    'page' => '일계표',
    'page_description' => '일계표 관리',
    'permission_name' => '화면조회',
    'permission_description' => '일계표 화면 조회',
    'name' => '일계표',
    'description' => '회계관리 > 장부관리 > 일계표',
    'category' => '회계관리 > 장부관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/book/purchase-sales', 'PlaceholderController@index', [
    'key' => 'web.ledger.book.purchase_sales',
    'page' => '매입매출장',
    'page_description' => '매입매출장 관리',
    'permission_name' => '화면조회',
    'permission_description' => '매입매출장 화면 조회',
    'name' => '매입매출장',
    'description' => '회계관리 > 장부관리 > 매입매출장',
    'category' => '회계관리 > 장부관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/book/vehicle-log', 'PlaceholderController@index', [
    'key' => 'web.ledger.book.vehicle_log',
    'page' => '차량운행일지',
    'page_description' => '차량운행일지 관리',
    'permission_name' => '화면조회',
    'permission_description' => '차량운행일지 화면 조회',
    'name' => '차량운행일지',
    'description' => '회계관리 > 장부관리 > 차량운행일지',
    'category' => '회계관리 > 장부관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/funds/daily-report', 'DailyFundsReportController@index', [
    'key' => 'web.ledger.funds.daily_report',
    'page' => '자금일보',
    'page_description' => '자금일보 관리',
    'permission_name' => '화면조회',
    'permission_description' => '자금일보 화면 조회',
    'name' => '자금일보',
    'description' => '회계관리 > 자금관리 > 자금일보',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/funds/account-balances', 'FundsOverviewController@legacyAccountBalances', [
    'key' => 'web.ledger.funds.account_balances_legacy',
    'page' => '계좌잔액현황',
    'page_description' => '자금현황 이전 주소 호환',
    'permission_name' => '화면조회',
    'permission_description' => '자금현황 이전 주소 조회',
    'name' => '계좌잔액현황',
    'description' => '회계관리 > 자금관리 > 자금현황',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => [],
    'skip_permission' => true,
    'log' => false,
]);

$router->get('/ledger/funds/payment-schedule', 'PaymentScheduleController@index', [
    'key' => 'web.ledger.funds.payment_schedule',
    'page' => '지급예정현황',
    'page_description' => '지급예정현황 관리',
    'permission_name' => '화면조회',
    'permission_description' => '지급예정현황 화면 조회',
    'name' => '지급예정현황',
    'description' => '회계관리 > 자금관리 > 지급예정현황',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/financial/trial-balance', 'PlaceholderController@index', [
    'key' => 'web.ledger.financial.trial_balance',
    'page' => '합계잔액시산표',
    'page_description' => '합계잔액시산표 관리',
    'permission_name' => '화면조회',
    'permission_description' => '합계잔액시산표 화면 조회',
    'name' => '합계잔액시산표',
    'description' => '회계관리 > 재무제표 > 합계잔액시산표',
    'category' => '회계관리 > 재무제표',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/financial/income-statement', 'PlaceholderController@index', [
    'key' => 'web.ledger.financial.income_statement',
    'page' => '손익계산서',
    'page_description' => '손익계산서 관리',
    'permission_name' => '화면조회',
    'permission_description' => '손익계산서 화면 조회',
    'name' => '손익계산서',
    'description' => '회계관리 > 재무제표 > 손익계산서',
    'category' => '회계관리 > 재무제표',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/financial/statement-position', 'PlaceholderController@index', [
    'key' => 'web.ledger.financial.statement_position',
    'page' => '재무상태표',
    'page_description' => '재무상태표 관리',
    'permission_name' => '화면조회',
    'permission_description' => '재무상태표 화면 조회',
    'name' => '재무상태표',
    'description' => '회계관리 > 재무제표 > 재무상태표',
    'category' => '회계관리 > 재무제표',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/financial/product-cost', 'PlaceholderController@index', [
    'key' => 'web.ledger.financial.product_cost',
    'page' => '공사원가명세서',
    'page_description' => '공사원가명세서 관리',
    'permission_name' => '화면조회',
    'permission_description' => '공사원가명세서 화면 조회',
    'name' => '공사원가명세서',
    'description' => '회계관리 > 재무제표 > 공사원가명세서',
    'category' => '회계관리 > 재무제표',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/financial/construction-cost', 'PlaceholderController@index', [
    'key' => 'web.ledger.financial.construction_cost',
    'page' => '공사원가명세서',
    'page_description' => '공사원가명세서 관리',
    'permission_name' => '화면조회',
    'permission_description' => '공사원가명세서 화면 조회',
    'name' => '공사원가명세서',
    'description' => '회계관리 > 재무제표 > 공사원가명세서',
    'category' => '회계관리 > 재무제표',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/financial/retained-earnings', 'PlaceholderController@index', [
    'key' => 'web.ledger.financial.retained_earnings',
    'page' => '이익잉여금처분계산서',
    'page_description' => '이익잉여금처분계산서 관리',
    'permission_name' => '화면조회',
    'permission_description' => '이익잉여금처분계산서 화면 조회',
    'name' => '이익잉여금처분계산서',
    'description' => '회계관리 > 재무제표 > 이익잉여금처분계산서',
    'category' => '회계관리 > 재무제표',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/assets/create', 'PlaceholderController@index', [
    'key' => 'web.ledger.assets.create',
    'page' => '고정자산등록',
    'page_description' => '고정자산등록 관리',
    'permission_name' => '화면조회',
    'permission_description' => '고정자산등록 화면 조회',
    'name' => '고정자산등록',
    'description' => '회계관리 > 고정자산관리 > 고정자산등록',
    'category' => '회계관리 > 고정자산관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/assets', 'PlaceholderController@index', [
    'key' => 'web.ledger.assets.index',
    'page' => '고정자산목록',
    'page_description' => '고정자산목록 관리',
    'permission_name' => '화면조회',
    'permission_description' => '고정자산목록 화면 조회',
    'name' => '고정자산목록',
    'description' => '회계관리 > 고정자산관리 > 고정자산목록',
    'category' => '회계관리 > 고정자산관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/assets/depreciation', 'PlaceholderController@index', [
    'key' => 'web.ledger.assets.depreciation',
    'page' => '감가상각',
    'page_description' => '감가상각 관리',
    'permission_name' => '화면조회',
    'permission_description' => '감가상각 화면 조회',
    'name' => '감가상각',
    'description' => '회계관리 > 고정자산관리 > 감가상각',
    'category' => '회계관리 > 고정자산관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/assets/transfer', 'PlaceholderController@index', [
    'key' => 'web.ledger.assets.transfer',
    'page' => '자산이동',
    'page_description' => '자산이동 관리',
    'permission_name' => '화면조회',
    'permission_description' => '자산이동 화면 조회',
    'name' => '자산이동',
    'description' => '회계관리 > 고정자산관리 > 자산이동',
    'category' => '회계관리 > 고정자산관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/assets/disposal', 'PlaceholderController@index', [
    'key' => 'web.ledger.assets.disposal',
    'page' => '자산처분',
    'page_description' => '자산처분 관리',
    'permission_name' => '화면조회',
    'permission_description' => '자산처분 화면 조회',
    'name' => '자산처분',
    'description' => '회계관리 > 고정자산관리 > 자산처분',
    'category' => '회계관리 > 고정자산관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/tax/trial-balance', 'PlaceholderController@index', [
    'key' => 'web.ledger.tax.trial_balance',
    'page' => '세무시산표',
    'page_description' => '세무시산표 관리',
    'permission_name' => '화면조회',
    'permission_description' => '세무시산표 화면 조회',
    'name' => '세무시산표',
    'description' => '회계관리 > 세무관리 > 세무시산표',
    'category' => '회계관리 > 세무관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/tax/income-statement', 'PlaceholderController@index', [
    'key' => 'web.ledger.tax.income_statement',
    'page' => '세무손익계산서',
    'page_description' => '세무손익계산서 관리',
    'permission_name' => '화면조회',
    'permission_description' => '세무손익계산서 화면 조회',
    'name' => '세무손익계산서',
    'description' => '회계관리 > 세무관리 > 세무손익계산서',
    'category' => '회계관리 > 세무관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/tax/statement-position', 'PlaceholderController@index', [
    'key' => 'web.ledger.tax.statement_position',
    'page' => '세무재무상태표',
    'page_description' => '세무재무상태표 관리',
    'permission_name' => '화면조회',
    'permission_description' => '세무재무상태표 화면 조회',
    'name' => '세무재무상태표',
    'description' => '회계관리 > 세무관리 > 세무재무상태표',
    'category' => '회계관리 > 세무관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/tax/cost-statement', 'PlaceholderController@index', [
    'key' => 'web.ledger.tax.cost_statement',
    'page' => '세무원가명세서',
    'page_description' => '세무원가명세서 관리',
    'permission_name' => '화면조회',
    'permission_description' => '세무원가명세서 화면 조회',
    'name' => '세무원가명세서',
    'description' => '회계관리 > 세무관리 > 세무원가명세서',
    'category' => '회계관리 > 세무관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/tax/retained-earnings', 'PlaceholderController@index', [
    'key' => 'web.ledger.tax.retained_earnings',
    'page' => '세무이익잉여금처분계산서',
    'page_description' => '세무이익잉여금처분계산서 관리',
    'permission_name' => '화면조회',
    'permission_description' => '세무이익잉여금처분계산서 화면 조회',
    'name' => '세무이익잉여금처분계산서',
    'description' => '회계관리 > 세무관리 > 세무이익잉여금처분계산서',
    'category' => '회계관리 > 세무관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/ledger/tax/comparison', 'PlaceholderController@index', [
    'key' => 'web.ledger.tax.comparison',
    'page' => '세무비교분석',
    'page_description' => '세무비교분석 관리',
    'permission_name' => '화면조회',
    'permission_description' => '세무비교분석 화면 조회',
    'name' => '세무비교분석',
    'description' => '회계관리 > 세무관리 > 세무비교분석',
    'category' => '회계관리 > 세무관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);
