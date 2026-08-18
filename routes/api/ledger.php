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

$router->get('/api/ledger/evidence-metadata/list', 'EvidenceMetadataController@apiList', [
    'key' => 'api.ledger.evidence_metadata.list',
    'page' => '증빙정책',
    'page_description' => '증빙정책 목록 조회',
    'permission_name' => '조회',
    'permission_description' => '증빙정책 목록 조회',
    'name' => '증빙정책 목록',
    'description' => '회계관리 > 자료관리 > 증빙정책 > 조회',
    'category' => '회계관리 > 자료관리',
    'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->get('/api/ledger/evidence/type-policies', 'EvidenceController@apiTypePolicies', [
    'key' => 'api.ledger.evidence.type_policies',
    'page' => '증빙원본',
    'page_description' => '증빙원본 자료유형 정책 조회',
    'permission_name' => '조회',
    'permission_description' => '증빙원본 자료유형 정책 조회',
    'name' => '증빙원본 자료유형 정책',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 자료유형 정책 조회',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/ledger/evidence-metadata/detail', 'EvidenceMetadataController@apiDetail', [
    'key' => 'api.ledger.evidence_metadata.detail',
    'page' => '증빙정책', 'page_description' => '증빙정책 상세 조회',
    'permission_name' => '상세조회', 'permission_description' => '증빙정책 상세 조회',
    'name' => '증빙정책 상세', 'description' => '회계관리 > 자료관리 > 증빙정책 > 상세조회',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->post('/api/ledger/evidence-metadata/save', 'EvidenceMetadataController@apiSave', [
    'key' => 'api.ledger.evidence_metadata.save',
    'page' => '증빙정책', 'page_description' => '증빙정책 등록 및 수정',
    'permission_name' => '저장', 'permission_description' => '증빙정책 등록 및 수정',
    'name' => '증빙정책 저장', 'description' => '회계관리 > 자료관리 > 증빙정책 > 저장',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['create', 'update'], 'log' => true,
]);

$router->post('/api/ledger/evidence-metadata/delete', 'EvidenceMetadataController@apiDelete', [
    'key' => 'api.ledger.evidence_metadata.delete',
    'page' => '증빙정책', 'page_description' => '증빙정책 삭제',
    'permission_name' => '삭제', 'permission_description' => '증빙정책 삭제',
    'name' => '증빙정책 삭제', 'description' => '회계관리 > 자료관리 > 증빙정책 > 삭제',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['delete'], 'log' => true,
]);

$router->get('/api/ledger/evidence-metadata/trash', 'EvidenceMetadataController@apiTrashList', [
    'key' => 'api.ledger.evidence_metadata.trash',
    'page' => '증빙정책', 'page_description' => '증빙정책 휴지통 목록',
    'permission_name' => '휴지통조회', 'permission_description' => '증빙정책 휴지통 조회',
    'name' => '증빙정책 휴지통', 'description' => '회계관리 > 자료관리 > 증빙정책 > 휴지통',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->post('/api/ledger/evidence-metadata/restore', 'EvidenceMetadataController@apiRestore', [
    'key' => 'api.ledger.evidence_metadata.restore',
    'page' => '증빙정책', 'page_description' => '증빙정책 복원',
    'permission_name' => '복원', 'permission_description' => '증빙정책 복원',
    'name' => '증빙정책 복원', 'description' => '회계관리 > 자료관리 > 증빙정책 > 복원',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['update'], 'log' => true,
]);

$router->post('/api/ledger/evidence-metadata/restore-bulk', 'EvidenceMetadataController@apiRestoreBulk', [
    'key' => 'api.ledger.evidence_metadata.restore_bulk',
    'page' => '증빙정책', 'page_description' => '증빙정책 선택 복원',
    'permission_name' => '선택복원', 'permission_description' => '선택한 증빙정책 복원',
    'name' => '증빙정책 선택복원', 'description' => '회계관리 > 자료관리 > 증빙정책 > 선택복원',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['update'], 'log' => true,
]);

$router->post('/api/ledger/evidence-metadata/restore-all', 'EvidenceMetadataController@apiRestoreAll', [
    'key' => 'api.ledger.evidence_metadata.restore_all',
    'page' => '증빙정책', 'page_description' => '증빙정책 전체 복원',
    'permission_name' => '전체복원', 'permission_description' => '휴지통 증빙정책 전체 복원',
    'name' => '증빙정책 전체복원', 'description' => '회계관리 > 자료관리 > 증빙정책 > 전체복원',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['update'], 'log' => true,
]);

$router->post('/api/ledger/evidence-metadata/purge', 'EvidenceMetadataController@apiPurge', [
    'key' => 'api.ledger.evidence_metadata.purge',
    'page' => '증빙정책', 'page_description' => '증빙정책 영구삭제',
    'permission_name' => '영구삭제', 'permission_description' => '증빙정책 영구삭제',
    'name' => '증빙정책 영구삭제', 'description' => '회계관리 > 자료관리 > 증빙정책 > 영구삭제',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['delete'], 'log' => true,
]);

$router->post('/api/ledger/evidence-metadata/purge-bulk', 'EvidenceMetadataController@apiPurgeBulk', [
    'key' => 'api.ledger.evidence_metadata.purge_bulk',
    'page' => '증빙정책', 'page_description' => '증빙정책 선택 영구삭제',
    'permission_name' => '선택영구삭제', 'permission_description' => '선택한 증빙정책 영구삭제',
    'name' => '증빙정책 선택영구삭제', 'description' => '회계관리 > 자료관리 > 증빙정책 > 선택영구삭제',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['delete'], 'log' => true,
]);

$router->post('/api/ledger/evidence-metadata/reorder', 'EvidenceMetadataController@apiReorder', [
    'key' => 'api.ledger.evidence_metadata.reorder',
    'page' => '증빙정책', 'page_description' => '증빙정책 순서 저장',
    'permission_name' => '정렬', 'permission_description' => '증빙정책 순서 저장',
    'name' => '증빙정책 정렬', 'description' => '회계관리 > 자료관리 > 증빙정책 > 정렬',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['update'], 'log' => true,
]);

$router->get('/api/ledger/evidence-metadata/source-columns', 'EvidenceMetadataController@apiSourceColumns', [
    'key' => 'api.ledger.evidence_metadata.source_columns',
    'page' => '증빙정책', 'page_description' => '실제 DB 원본테이블 컬럼 목록 조회',
    'permission_name' => '원본컬럼조회', 'permission_description' => '증빙정책 원본컬럼 목록 조회',
    'name' => '원본컬럼 목록', 'description' => '회계관리 > 자료관리 > 증빙정책 > 원본컬럼조회',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->get('/api/ledger/evidence-metadata/recommend', 'EvidenceMetadataController@apiRecommend', [
    'key' => 'api.ledger.evidence_metadata.recommend',
    'page' => '증빙정책', 'page_description' => '자료유형 기준 증빙정책 자동 추천',
    'permission_name' => '정책추천', 'permission_description' => '자료유형 기준 증빙정책 자동 추천',
    'name' => '증빙정책 추천', 'description' => '회계관리 > 자료관리 > 증빙정책 > 자동추천',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->get('/api/ledger/evidence-metadata/options', 'EvidenceMetadataController@apiOptions', [
    'key' => 'api.ledger.evidence_metadata.options',
    'page' => '증빙정책', 'page_description' => '증빙정책 선택 옵션 조회',
    'permission_name' => '옵션조회', 'permission_description' => '증빙정책 자료유형 옵션 조회',
    'name' => '증빙정책 옵션', 'description' => '회계관리 > 자료관리 > 증빙정책 > 옵션조회',
    'category' => '회계관리 > 자료관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
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

$router->post('/api/ledger/voucher/evidence-recommendations', 'VoucherController@apiEvidenceRecommendations', [
    'key' => 'api.ledger.voucher.evidence_recommendations',
    'page' => '전표입력',
    'page_description' => '전표 입력 관리',
    'permission_name' => '분개추천',
    'permission_description' => '추가 증빙 분개 추천',
    'name' => '전표 증빙 분개추천',
    'description' => '회계관리 > 전표관리 > 전표입력 > 증빙 분개추천',
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

$router->post('/api/ledger/voucher/request-review', 'VoucherController@apiRequestReview', [
    'key' => 'api.ledger.voucher.request_review',
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

$router->post('/api/ledger/voucher/cancel-review-request', 'VoucherController@apiCancelReviewRequest', [
    'key' => 'api.ledger.voucher.cancel_review_request',
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
    'page' => '전표검토·전기',
    'page_description' => '전표 검토 및 전기',
    'permission_name' => '검토완료',
    'permission_description' => '전표 검토 완료',
    'name' => '전표 검토완료',
    'description' => '회계관리 > 전표관리 > 전표검토·전기 > 검토완료',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'permission_key' => 'api.ledger.voucher.review',
    'log' => true,
]);

$router->post('/api/ledger/voucher/cancel-complete-review', 'VoucherController@apiCancelCompleteReview', [
    'key' => 'api.ledger.voucher.cancel_complete_review',
    'page' => '전표검토·전기',
    'page_description' => '전표 검토 및 전기',
    'permission_name' => '검토완료취소',
    'permission_description' => '전표 검토완료 취소',
    'name' => '전표 검토완료취소',
    'description' => '회계관리 > 전표관리 > 전표검토·전기 > 검토완료취소',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'permission_key' => 'api.ledger.voucher.review_cancel',
    'log' => true,
]);

$router->post('/api/ledger/voucher/post', 'VoucherController@apiPost', [
    'key' => 'api.ledger.voucher.post',
    'page' => '전표검토·전기',
    'page_description' => '전표 검토 및 전기',
    'permission_name' => '전기',
    'permission_description' => '전표 전기',
    'name' => '전표 전기',
    'description' => '회계관리 > 전표관리 > 전표검토·전기 > 전기',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'permission_key' => 'api.ledger.voucher.post',
    'log' => true,
]);

$router->post('/api/ledger/voucher/reverse', 'VoucherController@apiReverse', [
    'key' => 'api.ledger.voucher.reverse',
    'page' => '전표검토·전기',
    'page_description' => '전표 검토 및 전기',
    'permission_name' => '취소전표 작성',
    'permission_description' => '전표 취소전표 작성(역분개)',
    'name' => '전표 취소전표 작성',
    'description' => '회계관리 > 전표관리 > 전표검토·전기 > 취소전표 작성',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'permission_key' => 'api.ledger.voucher.reverse',
    'log' => true,
]);

$router->post('/api/ledger/voucher/reject', 'VoucherController@apiReject', [
    'key' => 'api.ledger.voucher.reject',
    'page' => '전표검토·전기',
    'page_description' => '전표 검토 및 전기',
    'permission_name' => '반려',
    'permission_description' => '전표 반려',
    'name' => '전표 반려',
    'description' => '회계관리 > 전표관리 > 전표검토·전기 > 반려',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['save'],
    'permission_key' => 'api.ledger.voucher.review',
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

$router->get('/api/ledger/transaction/evidence-search', 'TransactionController@apiEvidenceSearch', [
    'key' => 'api.ledger.transaction.evidence_search',
    'page' => '거래입력',
    'page_description' => '거래입력 자료증빙 조회',
    'permission_name' => '증빙검색',
    'permission_description' => '증빙정책에 따라 거래에 연결 가능한 자료증빙 조회',
    'name' => '거래 증빙검색',
    'description' => '회계관리 > 전표관리 > 거래입력 > 증빙검색',
    'category' => '회계관리 > 전표관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
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
$router->get('/api/funds/daily-report/report', 'DailyFundsReportController@apiReport', [
    'key' => 'api.ledger.funds.daily_report.report',
    'page' => '자금일보', 'page_description' => '자금일보 관리',
    'permission_name' => '조회', 'permission_description' => '자금일보 조회',
    'name' => '자금일보 조회', 'description' => '회계관리 > 자금관리 > 자금일보 > 조회',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => [],
    'skip_permission' => true, 'log' => false,
]);

$router->get('/api/funds/daily-report/excel', 'DailyFundsReportController@apiExcel', [
    'key' => 'api.ledger.funds.daily_report.excel',
    'page' => '자금일보', 'page_description' => '자금일보 관리',
    'permission_name' => '엑셀다운로드', 'permission_description' => '자금일보 엑셀 다운로드',
    'name' => '자금일보 엑셀', 'description' => '회계관리 > 자금관리 > 자금일보 > 엑셀다운로드',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => [],
    'skip_permission' => true, 'log' => false,
]);

$router->get('/api/funds/payment-schedule/list', 'PaymentScheduleController@apiList', [
    'key' => 'api.ledger.funds.payment_schedule.list',
    'page' => '지급예정현황', 'page_description' => '지급예정현황 관리',
    'permission_name' => '조회', 'permission_description' => '지급예정현황 조회',
    'name' => '지급예정현황 조회', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 조회',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->get('/api/funds/payment-schedule/detail', 'PaymentScheduleController@apiDetail', [
    'key' => 'api.ledger.funds.payment_schedule.detail',
    'page' => '지급예정현황', 'page_description' => '지급예정 상세',
    'permission_name' => '조회', 'permission_description' => '지급예정 상세 조회',
    'name' => '지급예정 상세', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 상세',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->post('/api/funds/payment-schedule/save', 'PaymentScheduleController@apiSave', [
    'key' => 'api.ledger.funds.payment_schedule.save',
    'page' => '지급예정현황', 'page_description' => '지급예정 저장',
    'permission_name' => '저장', 'permission_description' => '지급예정 등록 및 수정',
    'name' => '지급예정 저장', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 저장',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['save'], 'log' => true,
]);

$router->post('/api/funds/payment-schedule/hold', 'PaymentScheduleController@apiHold', [
    'key' => 'api.ledger.funds.payment_schedule.hold',
    'page' => '지급예정현황', 'page_description' => '지급보류',
    'permission_name' => '저장', 'permission_description' => '지급예정 보류',
    'name' => '지급보류', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 보류',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['save'], 'log' => true,
]);

$router->post('/api/funds/payment-schedule/release-hold', 'PaymentScheduleController@apiReleaseHold', [
    'key' => 'api.ledger.funds.payment_schedule.release_hold',
    'page' => '지급예정현황', 'page_description' => '지급보류 해제',
    'permission_name' => '저장', 'permission_description' => '지급예정 보류 해제',
    'name' => '지급보류 해제', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 보류 해제',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['save'], 'log' => true,
]);

$router->post('/api/funds/payment-schedule/delete', 'PaymentScheduleController@apiDelete', [
    'key' => 'api.ledger.funds.payment_schedule.delete',
    'page' => '지급예정현황', 'page_description' => '지급예정 삭제',
    'permission_name' => '삭제', 'permission_description' => '지급예정 삭제',
    'name' => '지급예정 삭제', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 삭제',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['delete'], 'log' => true,
]);

$router->get('/api/funds/payment-schedule/trash', 'PaymentScheduleController@apiTrashList', [
    'key' => 'api.ledger.funds.payment_schedule.trash',
    'page' => '지급예정현황', 'page_description' => '지급예정 휴지통',
    'permission_name' => '조회', 'permission_description' => '지급예정 휴지통 조회',
    'name' => '지급예정 휴지통', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 휴지통',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->post('/api/funds/payment-schedule/restore', 'PaymentScheduleController@apiRestore', [
    'key' => 'api.ledger.funds.payment_schedule.restore',
    'page' => '지급예정현황', 'page_description' => '지급예정 복원',
    'permission_name' => '저장', 'permission_description' => '지급예정 복원',
    'name' => '지급예정 복원', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 복원',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['save'], 'log' => true,
]);

$router->get('/api/funds/payment-schedule/excel', 'PaymentScheduleController@apiExcel', [
    'key' => 'api.ledger.funds.payment_schedule.excel',
    'page' => '지급예정현황', 'page_description' => '지급예정현황 엑셀',
    'permission_name' => '엑셀다운로드', 'permission_description' => '지급예정현황 엑셀 다운로드',
    'name' => '지급예정현황 엑셀', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 엑셀',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->get('/api/funds/payment-schedule/bank-withdrawals', 'PaymentScheduleController@apiBankWithdrawals', [
    'key' => 'api.ledger.funds.payment_schedule.bank_withdrawals',
    'page' => '지급예정현황', 'page_description' => '지급 연결 출금원본 검색',
    'permission_name' => '조회', 'permission_description' => '지급 연결 가능한 은행 출금원본 조회',
    'name' => '지급예정 출금원본 검색', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 출금원본 검색',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->post('/api/funds/payment-schedule/allocate', 'PaymentScheduleController@apiAllocate', [
    'key' => 'api.ledger.funds.payment_schedule.allocate',
    'page' => '지급예정현황', 'page_description' => '지급예정 실제지급 연결',
    'permission_name' => '저장', 'permission_description' => '은행 출금 원본을 지급예정에 배분 연결',
    'name' => '지급예정 실제지급 연결', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 실제지급 연결',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['save'], 'log' => true,
]);

$router->post('/api/funds/payment-schedule/release-allocation', 'PaymentScheduleController@apiReleaseAllocation', [
    'key' => 'api.ledger.funds.payment_schedule.release_allocation',
    'page' => '지급예정현황', 'page_description' => '지급예정 실제지급 연결 해제',
    'permission_name' => '저장', 'permission_description' => '지급예정의 은행 출금 배분 연결 해제',
    'name' => '지급예정 실제지급 연결 해제', 'description' => '회계관리 > 자금관리 > 지급예정현황 > 실제지급 연결 해제',
    'category' => '회계관리 > 자금관리', 'auth' => true, 'permissions' => ['save'], 'log' => true,
]);
