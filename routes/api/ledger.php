<?php

global $router;

$router->get('/api/funds/bank-transactions', 'BankTransactionReportController@apiList', [
    'key' => 'api.funds.bank_transactions.list',
    'page' => '계좌별거래내역',
    'page_description' => '계좌별 거래내역 관리',
    'permission_name' => '조회',
    'permission_description' => '계좌별거래내역 조회',
    'name' => '계좌별거래내역 조회',
    'description' => '회계관리 > 자금관리 > 계좌별거래내역 > 조회',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/funds/bank-transactions/summary', 'BankTransactionReportController@apiSummary', [
    'key' => 'api.funds.bank_transactions.summary',
    'page' => '계좌별거래내역',
    'page_description' => '계좌별 거래내역 관리',
    'permission_name' => '요약조회',
    'permission_description' => '계좌별거래내역 요약 조회',
    'name' => '계좌별거래내역 요약조회',
    'description' => '회계관리 > 자금관리 > 계좌별거래내역 > 요약조회',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/funds/bank-transactions/show', 'BankTransactionReportController@apiShow', [
    'key' => 'api.funds.bank_transactions.show',
    'page' => '계좌별거래내역',
    'page_description' => '계좌별 거래내역 관리',
    'permission_name' => '상세조회',
    'permission_description' => '계좌별거래내역 상세 조회',
    'name' => '계좌별거래내역 상세조회',
    'description' => '회계관리 > 자금관리 > 계좌별거래내역 > 상세조회',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/funds/bank-transactions/trash', 'BankTransactionReportController@apiTrashList', [
    'key' => 'api.funds.bank_transactions.trash',
    'page' => '계좌별거래내역',
    'page_description' => '계좌별 거래내역 관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '계좌별거래내역 휴지통 조회',
    'name' => '계좌별거래내역 휴지통조회',
    'description' => '회계관리 > 자금관리 > 계좌별거래내역 > 휴지통조회',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/funds/bank-transactions/delete', 'BankTransactionReportController@apiDelete', [
    'key' => 'api.funds.bank_transactions.delete',
    'page' => '계좌별거래내역',
    'page_description' => '계좌별 거래내역 관리',
    'permission_name' => '삭제',
    'permission_description' => '계좌별거래내역 삭제',
    'name' => '계좌별거래내역 삭제',
    'description' => '회계관리 > 자금관리 > 계좌별거래내역 > 삭제',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/funds/bank-transactions/restore', 'BankTransactionReportController@apiRestore', [
    'key' => 'api.funds.bank_transactions.restore',
    'page' => '계좌별거래내역',
    'page_description' => '계좌별 거래내역 관리',
    'permission_name' => '복구',
    'permission_description' => '계좌별거래내역 복구',
    'name' => '계좌별거래내역 복구',
    'description' => '회계관리 > 자금관리 > 계좌별거래내역 > 복구',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/funds/bank-transactions/restore-bulk', 'BankTransactionReportController@apiRestoreBulk', [
    'key' => 'api.funds.bank_transactions.restore_bulk',
    'page' => '계좌별거래내역',
    'page_description' => '계좌별 거래내역 관리',
    'permission_name' => '일괄복구',
    'permission_description' => '계좌별거래내역 일괄 복구',
    'name' => '계좌별거래내역 일괄복구',
    'description' => '회계관리 > 자금관리 > 계좌별거래내역 > 일괄복구',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/funds/bank-transactions/restore-all', 'BankTransactionReportController@apiRestoreAll', [
    'key' => 'api.funds.bank_transactions.restore_all',
    'page' => '계좌별거래내역',
    'page_description' => '계좌별 거래내역 관리',
    'permission_name' => '전체복구',
    'permission_description' => '계좌별거래내역 전체 복구',
    'name' => '계좌별거래내역 전체복구',
    'description' => '회계관리 > 자금관리 > 계좌별거래내역 > 전체복구',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/funds/payment-info', 'PaymentInfoReportController@apiList', [
    'key' => 'api.funds.payment_info.list',
    'page' => '결제정보',
    'page_description' => '결제정보 관리',
    'permission_name' => '조회',
    'permission_description' => '결제정보 조회',
    'name' => '결제정보 조회',
    'description' => '회계관리 > 자금관리 > 결제정보 > 조회',
    'category' => '회계관리 > 자금관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/account/list', 'ChartAccountController@apiList', [
    'key' => 'api.ledger.account.list',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '조회',
    'permission_description' => '계정과목 조회',
    'name' => '계정과목 조회',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/ledger/account/tree', 'ChartAccountController@apiTree', [
    'key' => 'api.ledger.account.tree',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '트리조회',
    'permission_description' => '계정과목 트리 조회',
    'name' => '계정과목 트리조회',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 트리조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/account/detail', 'ChartAccountController@apiDetail', [
    'key' => 'api.ledger.account.detail',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '상세조회',
    'permission_description' => '계정과목 상세 조회',
    'name' => '계정과목 상세조회',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 상세조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/ledger/account/template', 'ChartAccountController@apiTemplate', [
    'key' => 'api.ledger.account.template',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '양식다운로드',
    'permission_description' => '계정과목 양식 다운로드',
    'name' => '계정과목 양식다운로드',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 양식다운로드',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/account/trash', 'ChartAccountController@apiTrashList', [
    'key' => 'api.ledger.account.trash',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '계정과목 휴지통 조회',
    'name' => '계정과목 휴지통조회',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 휴지통조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/ledger/account/save', 'ChartAccountController@apiSave', [
    'key' => 'api.ledger.account.save',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '저장',
    'permission_description' => '계정과목 저장',
    'name' => '계정과목 저장',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 저장',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/account/status', 'ChartAccountController@apiStatus', [
    'key' => 'api.ledger.account.status',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '상태변경',
    'permission_description' => '계정과목 상태 변경',
    'name' => '계정과목 상태변경',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 상태변경',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/account/delete', 'ChartAccountController@apiDelete', [
    'key' => 'api.ledger.account.delete',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '삭제',
    'permission_description' => '계정과목 삭제',
    'name' => '계정과목 삭제',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/account/soft-delete', 'ChartAccountController@apiSoftDelete', [
    'key' => 'api.ledger.account.soft_delete',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '삭제',
    'permission_description' => '계정과목 삭제',
    'name' => '계정과목 삭제',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/account/restore', 'ChartAccountController@apiRestore', [
    'key' => 'api.ledger.account.restore',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '복구',
    'permission_description' => '계정과목 복구',
    'name' => '계정과목 복구',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 복구',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/account/hard-delete', 'ChartAccountController@apiHardDelete', [
    'key' => 'api.ledger.account.hard_delete',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '영구삭제',
    'permission_description' => '계정과목 영구 삭제',
    'name' => '계정과목 영구삭제',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 영구삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/ledger/account/excel', 'ChartAccountController@apiDownloadAllExcel', [
    'key' => 'api.ledger.account.excel',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '엑셀다운로드',
    'permission_description' => '계정과목 엑셀 다운로드',
    'name' => '계정과목 엑셀다운로드',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 엑셀다운로드',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/ledger/account/excel-upload', 'ChartAccountController@apiExcelUpload', [
    'key' => 'api.ledger.account.excel_upload',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '엑셀업로드',
    'permission_description' => '계정과목 엑셀 업로드',
    'name' => '계정과목 엑셀업로드',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 엑셀업로드',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/account/reorder', 'ChartAccountController@apiReorder', [
    'key' => 'api.ledger.account.reorder',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '정렬저장',
    'permission_description' => '계정과목 정렬 저장',
    'name' => '계정과목 정렬저장',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 정렬저장',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/account/purge', 'ChartAccountController@apiPurge', [
    'key' => 'api.ledger.account.purge',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '영구삭제',
    'permission_description' => '계정과목 영구 삭제',
    'name' => '계정과목 영구삭제',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 영구삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/account/purge-bulk', 'ChartAccountController@apiPurgeBulk', [
    'key' => 'api.ledger.account.purge_bulk',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '계정과목 일괄 영구 삭제',
    'name' => '계정과목 일괄영구삭제',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 일괄영구삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/account/purge-all', 'ChartAccountController@apiPurgeAll', [
    'key' => 'api.ledger.account.purge_all',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '계정과목 전체 영구 삭제',
    'name' => '계정과목 전체영구삭제',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 전체영구삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/account/restore-bulk', 'ChartAccountController@apiRestoreBulk', [
    'key' => 'api.ledger.account.restore_bulk',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '일괄복구',
    'permission_description' => '계정과목 일괄 복구',
    'name' => '계정과목 일괄복구',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 일괄복구',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/account/restore-all', 'ChartAccountController@apiRestoreAll', [
    'key' => 'api.ledger.account.restore_all',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '전체복구',
    'permission_description' => '계정과목 전체 복구',
    'name' => '계정과목 전체복구',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 전체복구',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/account/hard-delete-bulk', 'ChartAccountController@apiHardDeleteBulk', [
    'key' => 'api.ledger.account.hard_delete_bulk',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '계정과목 일괄 영구 삭제',
    'name' => '계정과목 일괄영구삭제',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 일괄영구삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/account/hard-delete-all', 'ChartAccountController@apiHardDeleteAll', [
    'key' => 'api.ledger.account.hard_delete_all',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '계정과목 전체 영구 삭제',
    'name' => '계정과목 전체영구삭제',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 전체영구삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/ledger/account/search', 'ChartAccountController@apiSearch', [
    'key' => 'api.ledger.account.search',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '검색',
    'permission_description' => '계정과목 검색',
    'name' => '계정과목 검색',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 검색',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/account/posting', 'ChartAccountController@apiPosting', [
    'key' => 'api.ledger.account.posting',
    'page' => '계정과목',
    'page_description' => '계정과목 관리',
    'permission_name' => '전기조회',
    'permission_description' => '계정과목 전기 조회',
    'name' => '계정과목 전기조회',
    'description' => '회계관리 > 기초정보관리 > 계정과목 > 전기조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/journal-rules/list', 'JournalRuleController@apiList', [
    'key' => 'api.ledger.journal_rules.list',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '조회',
    'permission_description' => '분개규칙 조회',
    'name' => '분개규칙 조회',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/ledger/journal-rules/detail', 'JournalRuleController@apiDetail', [
    'key' => 'api.ledger.journal_rules.detail',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '상세조회',
    'permission_description' => '분개규칙 상세 조회',
    'name' => '분개규칙 상세조회',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 상세조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/save', 'JournalRuleController@apiSave', [
    'key' => 'api.ledger.journal_rules.save',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '저장',
    'permission_description' => '분개규칙 저장',
    'name' => '분개규칙 저장',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 저장',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/status', 'JournalRuleController@apiStatus', [
    'key' => 'api.ledger.journal_rules.status',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '상태변경',
    'permission_description' => '분개규칙 상태 변경',
    'name' => '분개규칙 상태변경',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 상태변경',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/reorder', 'JournalRuleController@apiReorder', [
    'key' => 'api.ledger.journal_rules.reorder',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '정렬저장',
    'permission_description' => '분개규칙 정렬 저장',
    'name' => '분개규칙 정렬저장',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 정렬저장',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/delete', 'JournalRuleController@apiDelete', [
    'key' => 'api.ledger.journal_rules.delete',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '삭제',
    'permission_description' => '분개규칙 삭제',
    'name' => '분개규칙 삭제',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/ledger/journal-rules/trash', 'JournalRuleController@apiTrashList', [
    'key' => 'api.ledger.journal_rules.trash',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '분개규칙 휴지통 조회',
    'name' => '분개규칙 휴지통조회',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 휴지통조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/ledger/journal-rules/template', 'JournalRuleController@apiTemplate', [
    'key' => 'api.ledger.journal_rules.template',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '양식다운로드',
    'permission_description' => '분개규칙 양식 다운로드',
    'name' => '분개규칙 양식다운로드',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 양식다운로드',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/journal-rules/excel', 'JournalRuleController@apiDownloadExcel', [
    'key' => 'api.ledger.journal_rules.excel',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '엑셀다운로드',
    'permission_description' => '분개규칙 엑셀 다운로드',
    'name' => '분개규칙 엑셀다운로드',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 엑셀다운로드',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/ledger/journal-rules/excel-upload', 'JournalRuleController@apiExcelUpload', [
    'key' => 'api.ledger.journal_rules.excel_upload',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '엑셀업로드',
    'permission_description' => '분개규칙 엑셀 업로드',
    'name' => '분개규칙 엑셀업로드',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 엑셀업로드',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/restore', 'JournalRuleController@apiRestore', [
    'key' => 'api.ledger.journal_rules.restore',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '복구',
    'permission_description' => '분개규칙 복구',
    'name' => '분개규칙 복구',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 복구',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/restore-bulk', 'JournalRuleController@apiRestoreBulk', [
    'key' => 'api.ledger.journal_rules.restore_bulk',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '일괄복구',
    'permission_description' => '분개규칙 일괄 복구',
    'name' => '분개규칙 일괄복구',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 일괄복구',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/restore-all', 'JournalRuleController@apiRestoreAll', [
    'key' => 'api.ledger.journal_rules.restore_all',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '전체복구',
    'permission_description' => '분개규칙 전체 복구',
    'name' => '분개규칙 전체복구',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 전체복구',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/purge', 'JournalRuleController@apiPurge', [
    'key' => 'api.ledger.journal_rules.purge',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '영구삭제',
    'permission_description' => '분개규칙 영구 삭제',
    'name' => '분개규칙 영구삭제',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 영구삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/purge-bulk', 'JournalRuleController@apiPurgeBulk', [
    'key' => 'api.ledger.journal_rules.purge_bulk',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '분개규칙 일괄 영구 삭제',
    'name' => '분개규칙 일괄영구삭제',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 일괄영구삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/journal-rules/purge-all', 'JournalRuleController@apiPurgeAll', [
    'key' => 'api.ledger.journal_rules.purge_all',
    'page' => '분개규칙',
    'page_description' => '분개규칙 관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '분개규칙 전체 영구 삭제',
    'name' => '분개규칙 전체영구삭제',
    'description' => '회계관리 > 기초정보관리 > 분개규칙 > 전체영구삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/ledger/sub-account/list', 'SubChartAccountController@apiList', [
    'key' => 'api.ledger.sub_account.list',
    'page' => '보조계정',
    'page_description' => '보조계정 관리',
    'permission_name' => '관리화면 조회',
    'permission_description' => '보조계정 관리 화면에서 계정별 보조계정 목록 조회',
    'name' => '보조계정 조회',
    'description' => '회계관리 > 기초정보관리 > 보조계정 > 조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/ledger/sub-account/save', 'SubChartAccountController@apiSave', [
    'key' => 'api.ledger.sub_account.save',
    'page' => '보조계정',
    'page_description' => '보조계정 관리',
    'permission_name' => '저장',
    'permission_description' => '보조계정 저장',
    'name' => '보조계정 저장',
    'description' => '회계관리 > 기초정보관리 > 보조계정 > 저장',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/sub-account/update', 'SubChartAccountController@apiUpdate', [
    'key' => 'api.ledger.sub_account.update',
    'page' => '보조계정',
    'page_description' => '보조계정 관리',
    'permission_name' => '수정',
    'permission_description' => '보조계정 수정',
    'name' => '보조계정 수정',
    'description' => '회계관리 > 기초정보관리 > 보조계정 > 수정',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/sub-account/delete', 'SubChartAccountController@apiDelete', [
    'key' => 'api.ledger.sub_account.delete',
    'page' => '보조계정',
    'page_description' => '보조계정 관리',
    'permission_name' => '삭제',
    'permission_description' => '보조계정 삭제',
    'name' => '보조계정 삭제',
    'description' => '회계관리 > 기초정보관리 > 보조계정 > 삭제',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/ledger/voucher/list', 'VoucherController@apiList', [
    'key' => 'api.ledger.voucher.list',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '조회',
    'permission_description' => '전표 조회',
    'name' => '전표 조회',
    'description' => '회계관리 > 전표관리 > 전표입력 > 조회',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/ledger/voucher/detail', 'VoucherController@apiDetail', [
    'key' => 'api.ledger.voucher.detail',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '상세조회',
    'permission_description' => '전표 상세 조회',
    'name' => '전표 상세조회',
    'description' => '회계관리 > 전표관리 > 전표입력 > 상세조회',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/ledger/voucher/summary-search', 'VoucherController@apiSummarySearch', [
    'key' => 'api.ledger.voucher.summary_search',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '요약검색',
    'permission_description' => '전표 요약 검색',
    'name' => '전표 요약검색',
    'description' => '회계관리 > 전표관리 > 전표입력 > 요약검색',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/voucher/search', 'VoucherController@apiSearch', [
    'key' => 'api.ledger.voucher.search',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '검색',
    'permission_description' => '전표 검색',
    'name' => '전표 검색',
    'description' => '회계관리 > 전표관리 > 전표입력 > 검색',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/voucher/transaction-search', 'VoucherController@apiTransactionSearch', [
    'key' => 'api.ledger.voucher.transaction_search',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '거래검색',
    'permission_description' => '전표 거래 검색',
    'name' => '전표 거래검색',
    'description' => '회계관리 > 전표관리 > 전표입력 > 거래검색',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/voucher/evidence-search', 'VoucherController@apiEvidenceSearch', [
    'key' => 'api.ledger.voucher.evidence_search',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '증빙검색',
    'permission_description' => '전표 증빙 검색',
    'name' => '전표 증빙검색',
    'description' => '회계관리 > 전표관리 > 전표입력 > 증빙검색',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/ledger/voucher/save', 'VoucherController@apiSave', [
    'key' => 'api.ledger.voucher.save',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '저장',
    'permission_description' => '전표 저장',
    'name' => '전표 저장',
    'description' => '회계관리 > 전표관리 > 전표입력 > 저장',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/reorder', 'VoucherController@apiReorder', [
    'key' => 'api.ledger.voucher.reorder',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '정렬저장',
    'permission_description' => '전표 정렬 저장',
    'name' => '전표 정렬저장',
    'description' => '회계관리 > 전표관리 > 전표입력 > 정렬저장',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/status', 'VoucherController@apiUpdateStatus', [
    'key' => 'api.ledger.voucher.status',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '상태변경',
    'permission_description' => '전표 상태 변경',
    'name' => '전표 상태변경',
    'description' => '회계관리 > 전표관리 > 전표입력 > 상태변경',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/confirm', 'VoucherController@apiConfirm', [
    'key' => 'api.ledger.voucher.confirm',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '확인',
    'permission_description' => '전표 확인',
    'name' => '전표 확인',
    'description' => '회계관리 > 전표관리 > 전표입력 > 확인',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/cancel-review', 'VoucherController@apiCancelReview', [
    'key' => 'api.ledger.voucher.cancel_review',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '검토취소',
    'permission_description' => '전표 검토 취소',
    'name' => '전표 검토취소',
    'description' => '회계관리 > 전표관리 > 전표입력 > 검토취소',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/complete-review', 'VoucherController@apiCompleteReview', [
    'key' => 'api.ledger.voucher.complete_review',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '검토완료',
    'permission_description' => '전표 검토 완료',
    'name' => '전표 검토완료',
    'description' => '회계관리 > 전표관리 > 전표입력 > 검토완료',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/cancel-complete-review', 'VoucherController@apiCancelCompleteReview', [
    'key' => 'api.ledger.voucher.cancel_complete_review',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '검토완료취소',
    'permission_description' => '전표 검토완료 취소',
    'name' => '전표 검토완료취소',
    'description' => '회계관리 > 전표관리 > 전표입력 > 검토완료취소',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/post', 'VoucherController@apiPost', [
    'key' => 'api.ledger.voucher.post',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '전기',
    'permission_description' => '전표 전기',
    'name' => '전표 전기',
    'description' => '회계관리 > 전표관리 > 전표입력 > 전기',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/reverse', 'VoucherController@apiReverse', [
    'key' => 'api.ledger.voucher.reverse',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '역분개',
    'permission_description' => '전표 역분개',
    'name' => '전표 역분개',
    'description' => '회계관리 > 전표관리 > 전표입력 > 역분개',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/link-transaction', 'VoucherController@apiLinkTransaction', [
    'key' => 'api.ledger.voucher.link_transaction',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '거래연결',
    'permission_description' => '전표 거래 연결',
    'name' => '전표 거래연결',
    'description' => '회계관리 > 전표관리 > 전표입력 > 거래연결',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/link-evidence', 'VoucherController@apiLinkEvidence', [
    'key' => 'api.ledger.voucher.link_evidence',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '증빙연결',
    'permission_description' => '전표 증빙 연결',
    'name' => '전표 증빙연결',
    'description' => '회계관리 > 전표관리 > 전표입력 > 증빙연결',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/unlink-evidence', 'VoucherController@apiUnlinkEvidence', [
    'key' => 'api.ledger.voucher.unlink_evidence',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '증빙연결해제',
    'permission_description' => '전표 증빙 연결 해제',
    'name' => '전표 증빙연결해제',
    'description' => '회계관리 > 전표관리 > 전표입력 > 증빙연결해제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/reject', 'VoucherController@apiReject', [
    'key' => 'api.ledger.voucher.reject',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '반려',
    'permission_description' => '전표 반려',
    'name' => '전표 반려',
    'description' => '회계관리 > 전표관리 > 전표입력 > 반려',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/create-transaction', 'VoucherController@apiCreateTransaction', [
    'key' => 'api.ledger.voucher.create_transaction',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '거래생성',
    'permission_description' => '전표 거래 생성',
    'name' => '전표 거래생성',
    'description' => '회계관리 > 전표관리 > 전표입력 > 거래생성',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/delete', 'VoucherController@apiDelete', [
    'key' => 'api.ledger.voucher.delete',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '삭제',
    'permission_description' => '전표 삭제',
    'name' => '전표 삭제',
    'description' => '회계관리 > 전표관리 > 전표입력 > 삭제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/ledger/voucher/trash', 'VoucherController@apiTrashList', [
    'key' => 'api.ledger.voucher.trash',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '전표 휴지통 조회',
    'name' => '전표 휴지통조회',
    'description' => '회계관리 > 전표관리 > 전표입력 > 휴지통조회',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/restore', 'VoucherController@apiRestore', [
    'key' => 'api.ledger.voucher.restore',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '복구',
    'permission_description' => '전표 복구',
    'name' => '전표 복구',
    'description' => '회계관리 > 전표관리 > 전표입력 > 복구',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/restore-bulk', 'VoucherController@apiRestoreBulk', [
    'key' => 'api.ledger.voucher.restore_bulk',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '일괄복구',
    'permission_description' => '전표 일괄 복구',
    'name' => '전표 일괄복구',
    'description' => '회계관리 > 전표관리 > 전표입력 > 일괄복구',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/restore-all', 'VoucherController@apiRestoreAll', [
    'key' => 'api.ledger.voucher.restore_all',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '전체복구',
    'permission_description' => '전표 전체 복구',
    'name' => '전표 전체복구',
    'description' => '회계관리 > 전표관리 > 전표입력 > 전체복구',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/purge', 'VoucherController@apiPurge', [
    'key' => 'api.ledger.voucher.purge',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '영구삭제',
    'permission_description' => '전표 영구 삭제',
    'name' => '전표 영구삭제',
    'description' => '회계관리 > 전표관리 > 전표입력 > 영구삭제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/purge-bulk', 'VoucherController@apiPurgeBulk', [
    'key' => 'api.ledger.voucher.purge_bulk',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '전표 일괄 영구 삭제',
    'name' => '전표 일괄영구삭제',
    'description' => '회계관리 > 전표관리 > 전표입력 > 일괄영구삭제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/voucher/purge-all', 'VoucherController@apiPurgeAll', [
    'key' => 'api.ledger.voucher.purge_all',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '전표 전체 영구 삭제',
    'name' => '전표 전체영구삭제',
    'description' => '회계관리 > 전표관리 > 전표입력 > 전체영구삭제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/ledger/transaction/list', 'TransactionController@apiList', [
    'key' => 'api.ledger.transaction.list',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '조회',
    'permission_description' => '거래 조회',
    'name' => '거래 조회',
    'description' => '회계관리 > 전표관리 > 거래입력 > 조회',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/ledger/transaction/reorder', 'TransactionController@apiReorder', [
    'key' => 'api.ledger.transaction.reorder',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '정렬저장',
    'permission_description' => '거래 정렬 저장',
    'name' => '거래 정렬저장',
    'description' => '회계관리 > 전표관리 > 거래입력 > 정렬저장',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/ledger/transaction/detail', 'TransactionController@apiDetail', [
    'key' => 'api.ledger.transaction.detail',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '상세조회',
    'permission_description' => '거래 상세 조회',
    'name' => '거래 상세조회',
    'description' => '회계관리 > 전표관리 > 거래입력 > 상세조회',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/transaction/file', 'TransactionController@apiFile', [
    'key' => 'api.ledger.transaction.file',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '파일조회',
    'permission_description' => '거래 파일 조회',
    'name' => '거래 파일조회',
    'description' => '회계관리 > 전표관리 > 거래입력 > 파일조회',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/ledger/transaction/save', 'TransactionController@apiSave', [
    'key' => 'api.ledger.transaction.save',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '저장',
    'permission_description' => '거래 저장',
    'name' => '거래 저장',
    'description' => '회계관리 > 전표관리 > 거래입력 > 저장',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/create-voucher', 'TransactionController@apiCreateVoucher', [
    'key' => 'api.ledger.transaction.create_voucher',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '전표생성',
    'permission_description' => '거래 전표 생성',
    'name' => '거래 전표생성',
    'description' => '회계관리 > 전표관리 > 거래입력 > 전표생성',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/ledger/transaction/recommend-voucher', 'TransactionController@apiRecommendVoucher', [
    'key' => 'api.ledger.transaction.recommend_voucher',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '전표추천',
    'permission_description' => '거래 전표 추천',
    'name' => '거래 전표추천',
    'description' => '회계관리 > 전표관리 > 거래입력 > 전표추천',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/link-voucher', 'TransactionController@apiLinkVoucher', [
    'key' => 'api.ledger.transaction.link_voucher',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '전표연결',
    'permission_description' => '거래 전표 연결',
    'name' => '거래 전표연결',
    'description' => '회계관리 > 전표관리 > 거래입력 > 전표연결',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/unlink-voucher', 'TransactionController@apiUnlinkVoucher', [
    'key' => 'api.ledger.transaction.unlink_voucher',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '전표연결해제',
    'permission_description' => '거래 전표 연결 해제',
    'name' => '거래 전표연결해제',
    'description' => '회계관리 > 전표관리 > 거래입력 > 전표연결해제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/delete', 'TransactionController@apiDelete', [
    'key' => 'api.ledger.transaction.delete',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '삭제',
    'permission_description' => '거래 삭제',
    'name' => '거래 삭제',
    'description' => '회계관리 > 전표관리 > 거래입력 > 삭제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/ledger/transaction/trash', 'TransactionController@apiTrashList', [
    'key' => 'api.ledger.transaction.trash',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '거래 휴지통 조회',
    'name' => '거래 휴지통조회',
    'description' => '회계관리 > 전표관리 > 거래입력 > 휴지통조회',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/restore', 'TransactionController@apiRestore', [
    'key' => 'api.ledger.transaction.restore',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '복구',
    'permission_description' => '거래 복구',
    'name' => '거래 복구',
    'description' => '회계관리 > 전표관리 > 거래입력 > 복구',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/restore-bulk', 'TransactionController@apiRestoreBulk', [
    'key' => 'api.ledger.transaction.restore_bulk',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '일괄복구',
    'permission_description' => '거래 일괄 복구',
    'name' => '거래 일괄복구',
    'description' => '회계관리 > 전표관리 > 거래입력 > 일괄복구',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/restore-all', 'TransactionController@apiRestoreAll', [
    'key' => 'api.ledger.transaction.restore_all',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '전체복구',
    'permission_description' => '거래 전체 복구',
    'name' => '거래 전체복구',
    'description' => '회계관리 > 전표관리 > 거래입력 > 전체복구',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/purge', 'TransactionController@apiPurge', [
    'key' => 'api.ledger.transaction.purge',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '영구삭제',
    'permission_description' => '거래 영구 삭제',
    'name' => '거래 영구삭제',
    'description' => '회계관리 > 전표관리 > 거래입력 > 영구삭제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/purge-bulk', 'TransactionController@apiPurgeBulk', [
    'key' => 'api.ledger.transaction.purge_bulk',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '거래 일괄 영구 삭제',
    'name' => '거래 일괄영구삭제',
    'description' => '회계관리 > 전표관리 > 거래입력 > 일괄영구삭제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/ledger/transaction/purge-all', 'TransactionController@apiPurgeAll', [
    'key' => 'api.ledger.transaction.purge_all',
    'page' => '거래입력',
    'page_description' => '거래 입력 관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '거래 전체 영구 삭제',
    'name' => '거래 전체영구삭제',
    'description' => '회계관리 > 전표관리 > 거래입력 > 전체영구삭제',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);
