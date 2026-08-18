<?php

use Core\Helpers\AssetHelper;

$layoutOptions = ['header' => true, 'navbar' => true, 'sidebar' => true, 'footer' => true, 'wrapper' => 'single'];
$pageStyles = AssetHelper::css('/assets/css/pages/ledger/evidence-metadata.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/evidenceMetadata.js');

?>

<main class="evidence-metadata-page">
    <div class="container-fluid py-4 dt-page-shell">
        <div class="page-header">
            <h5 class="mb-1 fw-bold"><i class="bi bi-database-gear me-2"></i>증빙정책</h5>
            <span id="evidenceMetadataCount" class="text-primary"></span>
        </div>

        <div class="content-area dt-content-stack">
            <?php
            $searchId = 'evidenceMetadata';
            $dateOptions = '';
            $searchFieldOptions = '
                <option value="import_type">자료유형</option>
                <option value="source_table">원본테이블</option>
                <option value="evidence_type">증빙유형</option>
            ';
            include PROJECT_ROOT . '/app/views/components/ui-search.php';

            $tableId = 'evidence-metadata-table';
            $ajaxUrl = '/api/ledger/evidence-metadata/list';
            $columnsType = 'evidenceMetadata';
            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = false;
            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>

    <div class="modal fade" id="evidenceMetadataModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" id="evidenceMetadataForm">
                <div class="modal-header">
                    <h5 class="modal-title">증빙정책</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="evidenceMetadataId">
                    <div class="alert alert-info py-2 small" role="note">
                        선택된 자료유형과 현재 DB 구조를 기준으로 정책과 컬럼 의미를 자동 추천합니다. 추천 결과를 검토한 뒤 필요한 항목만 조정해 주세요.
                    </div>

                    <section class="metadata-section">
                        <h6>증빙유형 설정</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="evidenceMetadataImportTypeDisplay">자료유형(import_type) <span class="text-danger">*</span></label>
                                <input type="hidden" name="import_type" id="evidenceMetadataImportType">
                                <input type="text" class="form-control form-control-sm" id="evidenceMetadataImportTypeDisplay" readonly aria-readonly="true">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="evidenceMetadataSourceTable">원본테이블 <span class="text-danger">*</span></label>
                                <input type="hidden" name="source_table" id="evidenceMetadataSourceTable">
                                <input type="text" class="form-control form-control-sm" id="evidenceMetadataSourceTableDisplay" readonly aria-readonly="true">
                                <div class="form-text">자료유형과 실제 DB 구조를 기준으로 자동 추천됩니다.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="evidenceMetadataEvidenceType">사용영역(evidence_type) <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" name="evidence_type" id="evidenceMetadataEvidenceType" required>
                                    <option value="DATA">자료증빙</option>
                                    <option value="FUND">자금증빙</option>
                                    <option value="BOTH">자료·자금 공통</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="metadata-section mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">기준 설정</h6>
                            <small class="text-muted">표준 회계 의미별 추천 결과를 검토하며 실제 원본 컬럼만 선택할 수 있습니다.</small>
                        </div>
                        <div id="evidenceMetadataMappingFields"></div>
                        <div class="mapping-group metadata-adjustment-group">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mapping-group-title mb-1">가감금액</h6>
                                    <div class="mapping-description">추가·차감 방향과 원본컬럼을 필요한 만큼 설정합니다.</div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="evidenceMetadataAddAdjustment">
                                    <i class="bi bi-plus-lg me-1"></i>가감항목 추가
                                </button>
                            </div>
                            <div class="metadata-adjustment-header" aria-hidden="true">
                                <span>가감구분</span>
                                <span>원본컬럼</span>
                                <span></span>
                            </div>
                            <div id="evidenceMetadataAdjustmentRows"></div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="evidenceMetadataDeleteBtn">삭제</button>
                    <button type="submit" class="btn btn-primary btn-sm">저장</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="evidencePolicyTypeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">자료유형 선택</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="evidencePolicyTypeSelect">등록할 자료유형(import_type) <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="evidencePolicyTypeSelect"></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-sm" id="evidencePolicyTypeConfirm">계속</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    $modalId = 'evidenceMetadataTrashModal';
    $type = 'evidenceMetadata';
    $modalTitle = '증빙정책 휴지통';
    $tableId = 'evidence-metadata-trash-table';
    $listUrl = '/api/ledger/evidence-metadata/trash';
    $restoreUrl = '/api/ledger/evidence-metadata/restore';
    $deleteUrl = '/api/ledger/evidence-metadata/purge';
    $deleteAllUrl = '';
    $enableDeleteAll = false;
    $tableHead = '
        <th>자료유형</th>
        <th>원본테이블</th>
        <th>증빙유형</th>
        <th>삭제일시</th>
        <th>삭제자</th>
        <th width="150">작업</th>
    ';
    $emptyMessage = '휴지통에 증빙정책이 없습니다.';
    include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
    ?>
</main>
