<?php

global $router;

$router->get('/api/user/profile/detail', 'ProfileController@apiDetail', [
    'key' => 'api.user.profile.detail',
    'page' => '내정보관리',
    'page_description' => '내정보 관리',
    'permission_name' => '상세조회',
    'permission_description' => '내정보 상세 조회',
    'name' => '내정보 상세조회',
    'description' => '내정보 > 프로필 > 내정보관리 > 상세조회',
    'category' => '내정보 > 프로필',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/user/profile/save', 'ProfileController@apiSave', [
    'key' => 'api.user.profile.save',
    'page' => '내정보관리',
    'page_description' => '내정보 관리',
    'permission_name' => '저장',
    'permission_description' => '내정보 저장',
    'name' => '내정보 저장',
    'description' => '내정보 > 프로필 > 내정보관리 > 저장',
    'category' => '내정보 > 프로필',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/file/preview', 'FileController@apiPreview', [
    'key' => 'api.file.preview',
    'page' => '파일관리',
    'page_description' => '파일 관리',
    'permission_name' => '미리보기',
    'permission_description' => '파일 미리보기',
    'name' => '파일 미리보기',
    'description' => '설정 > 시스템설정 > 파일관리 > 미리보기',
    'category' => '설정 > 시스템설정',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/api/file/upload-test', 'FileController@apiUploadTest', [
    'key' => 'api.file.upload.test',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => '양식다운로드',
    'permission_description' => '자료업로드 양식 다운로드',
    'name' => '자료업로드 양식다운로드',
    'description' => '회계관리 > 자료관리 > 자료업로드 > 양식다운로드',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/import/template', 'EvidenceImportController@apiTemplate', [
    'key' => 'api.import.template',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => '필드조회',
    'permission_description' => '자료업로드 필드 조회',
    'name' => '자료업로드 필드조회',
    'description' => '회계관리 > 자료관리 > 자료업로드 > 필드조회',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/import/fields', 'EvidenceImportController@apiFieldOptions', [
    'key' => 'api.import.fields',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => '미리보기',
    'permission_description' => '자료업로드 미리보기',
    'name' => '자료업로드 미리보기',
    'description' => '회계관리 > 자료관리 > 자료업로드 > 미리보기',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/import/preview', 'EvidenceImportController@apiPreview', [
    'key' => 'api.import.preview',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => '원본업로드',
    'permission_description' => '자료업로드 원본 업로드',
    'name' => '자료업로드 원본업로드',
    'description' => '회계관리 > 자료관리 > 자료업로드 > 원본업로드',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/import/seed-upload', 'EvidenceUploadController@apiUpload', [
    'key' => 'api.import.seed_upload',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => '증빙업로드',
    'permission_description' => '자료업로드 증빙 업로드',
    'name' => '자료업로드 증빙업로드',
    'description' => '회계관리 > 자료관리 > 자료업로드 > 증빙업로드',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/evidence-upload', 'EvidenceUploadController@apiUpload', [
    'key' => 'api.import.evidence_upload',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => '증빙업로드취소',
    'permission_description' => '자료업로드 증빙 업로드 취소',
    'name' => '자료업로드 증빙업로드취소',
    'description' => '회계관리 > 자료관리 > 자료업로드 > 증빙업로드취소',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/evidence-upload/cancel', 'EvidenceUploadController@apiUploadCancel', [
    'key' => 'api.import.evidence_upload_cancel',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => '증빙업로드취소',
    'permission_description' => '자료업로드 증빙 업로드 취소',
    'name' => '자료업로드 증빙업로드취소',
    'description' => '회계관리 > 자료관리 > 자료업로드 > 증빙업로드취소',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => false,
]);

$router->get('/api/import/batches', 'EvidenceUploadController@apiBatchList', [
    'key' => 'api.import.batches',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => '배치조회',
    'permission_description' => '업로드 배치 조회',
    'name' => '배치 조회',
    'description' => '회계관리 > 자료관리 > 자료업로드 > 배치조회',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/import/evidences', 'EvidenceListController@apiList', [
    'key' => 'api.import.evidences',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '조회',
    'permission_description' => '증빙원본 조회',
    'name' => '증빙원본 조회',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 조회',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/import/evidences/download', 'EvidenceDownloadController@apiDownload', [
    'key' => 'api.import.evidences.download',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '다운로드',
    'permission_description' => '증빙 다운로드',
    'name' => '증빙 다운로드',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 다운로드',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/import/evidences/trash', 'EvidenceLifecycleController@apiTrashList', [
    'key' => 'api.import.evidences.trash',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '휴지통조회',
    'permission_description' => '증빙 휴지통 조회',
    'name' => '증빙 휴지통조회',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 휴지통조회',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/import/batch/rows', 'EvidenceUploadController@apiBatchRows', [
    'key' => 'api.import.batch.rows',
    'page' => '자료업로드',
    'page_description' => '자료 업로드',
    'permission_name' => 'Rows조회',
    'permission_description' => '업로드 Rows 조회',
    'name' => '자료업로드 Rows조회',
    'description' => '회계관리 > 자료관리 > 자료업로드 > Rows조회',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/import/evidence/save', 'EvidenceSaveController@apiSave', [
    'key' => 'api.import.evidence.save',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '저장',
    'permission_description' => '증빙 저장',
    'name' => '증빙 저장',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 저장',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/evidence/create', 'EvidenceSaveController@apiCreate', [
    'key' => 'api.import.evidence.create',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '생성',
    'permission_description' => '증빙 생성',
    'name' => '증빙 생성',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 생성',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/import/evidence/summary-search', 'EvidenceDownloadController@apiSearchSummary', [
    'key' => 'api.import.evidence.summary_search',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '요약검색',
    'permission_description' => '증빙 요약 검색',
    'name' => '증빙 요약검색',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 요약검색',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/import/evidences/bulk-save', 'EvidenceSaveController@apiBulkSave', [
    'key' => 'api.import.evidences.bulk_save',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '일괄저장',
    'permission_description' => '증빙 일괄 저장',
    'name' => '증빙 일괄저장',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 일괄저장',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/evidences/status', 'EvidenceStatusController@apiUpdateStatus', [
    'key' => 'api.import.evidences.status',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '상태변경',
    'permission_description' => '증빙 상태 변경',
    'name' => '증빙 상태변경',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 상태변경',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/evidences/reorder', 'EvidenceStatusController@apiReorder', [
    'key' => 'api.import.evidences.reorder',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '정렬저장',
    'permission_description' => '증빙 정렬 저장',
    'name' => '증빙 정렬저장',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 정렬저장',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/evidences/delete', 'EvidenceLifecycleController@apiDelete', [
    'key' => 'api.import.evidences.delete',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '삭제',
    'permission_description' => '증빙 삭제',
    'name' => '증빙 삭제',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 삭제',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/import/evidences/restore', 'EvidenceLifecycleController@apiRestore', [
    'key' => 'api.import.evidences.restore',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '복구',
    'permission_description' => '증빙 복구',
    'name' => '증빙 복구',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 복구',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/evidences/restore-bulk', 'EvidenceLifecycleController@apiRestore', [
    'key' => 'api.import.evidences.restore_bulk',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '일괄복구',
    'permission_description' => '증빙 일괄 복구',
    'name' => '증빙 일괄복구',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 일괄복구',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/evidences/restore-all', 'EvidenceLifecycleController@apiRestoreAll', [
    'key' => 'api.import.evidences.restore_all',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '전체복구',
    'permission_description' => '증빙 전체 복구',
    'name' => '증빙 전체복구',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 전체복구',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/import/evidences/purge', 'EvidenceLifecycleController@apiPurge', [
    'key' => 'api.import.evidences.purge',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '영구삭제',
    'permission_description' => '증빙 영구 삭제',
    'name' => '증빙 영구삭제',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 영구삭제',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/import/evidences/purge-bulk', 'EvidenceLifecycleController@apiPurge', [
    'key' => 'api.import.evidences.purge_bulk',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '일괄영구삭제',
    'permission_description' => '증빙 일괄 영구 삭제',
    'name' => '증빙 일괄영구삭제',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 일괄영구삭제',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/import/evidences/purge-all', 'EvidenceLifecycleController@apiPurgeAll', [
    'key' => 'api.import.evidences.purge_all',
    'page' => '증빙원본',
    'page_description' => '증빙원본 관리',
    'permission_name' => '전체영구삭제',
    'permission_description' => '증빙 전체 영구 삭제',
    'name' => '증빙 전체영구삭제',
    'description' => '회계관리 > 자료관리 > 증빙원본 > 전체영구삭제',
    'category' => '회계관리 > 자료관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/system/file-policies', 'FileController@apiPolicyList', [
    'key' => 'api.settings.system.storage.policy.view',
    'page' => '저장소정책',
    'page_description' => '저장소 정책 관리',
    'permission_name' => '조회',
    'permission_description' => '저장소정책 조회',
    'name' => '저장소정책 조회',
    'description' => '설정 > 시스템설정 > 저장소정책 > 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/system/file-policies', 'FileController@apiPolicyCreate', [
    'key' => 'api.settings.system.storage.policy.create',
    'page' => '저장소정책',
    'page_description' => '저장소 정책 관리',
    'permission_name' => '등록',
    'permission_description' => '저장소정책 등록',
    'name' => '저장소정책 등록',
    'description' => '설정 > 시스템설정 > 저장소정책 > 등록',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/system/file-policies/update', 'FileController@apiPolicyUpdate', [
    'key' => 'api.settings.system.storage.policy.edit',
    'page' => '저장소정책',
    'page_description' => '저장소 정책 관리',
    'permission_name' => '수정',
    'permission_description' => '저장소정책 수정',
    'name' => '저장소정책 수정',
    'description' => '설정 > 시스템설정 > 저장소정책 > 수정',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/system/file-policies/delete', 'FileController@apiPolicyDelete', [
    'key' => 'api.settings.system.storage.policy.delete',
    'page' => '저장소정책',
    'page_description' => '저장소 정책 관리',
    'permission_name' => '삭제',
    'permission_description' => '저장소정책 삭제',
    'name' => '저장소정책 삭제',
    'description' => '설정 > 시스템설정 > 저장소정책 > 삭제',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/system/file-policies/toggle', 'FileController@apiPolicyToggle', [
    'key' => 'api.settings.system.storage.policy.toggle',
    'page' => '저장소정책',
    'page_description' => '저장소 정책 관리',
    'permission_name' => '전환',
    'permission_description' => '저장소정책 전환',
    'name' => '저장소정책 전환',
    'description' => '설정 > 시스템설정 > 저장소정책 > 전환',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/system/storage/bucket-browse', 'FileController@apiBucketBrowse', [
    'key' => 'api.settings.system.storage.browse',
    'page' => '저장소정책',
    'page_description' => '저장소 정책 관리',
    'permission_name' => '버킷조회',
    'permission_description' => '저장소 버킷 조회',
    'name' => '저장소 버킷조회',
    'description' => '설정 > 시스템설정 > 저장소정책 > 버킷조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);
