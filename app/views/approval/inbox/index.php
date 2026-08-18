<?php

use Core\Helpers\AssetHelper;

$pageStyles = AssetHelper::css('/assets/css/pages/approval/inbox/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/approval/inbox/index.js');
?>
<main class="approval-inbox-page">
    <div class="container-fluid py-4">
        <div class="approval-inbox-header">
            <div>
                <h5 class="mb-1 fw-bold d-flex align-items-center">
                    <i class="bi bi-inbox me-2"></i>결재함
                    <span id="approvalInboxCount" class="ms-2 text-primary fw-semibold fs-6"></span>
                </h5>
                <p class="mb-0 text-muted">현재 처리 상태와 과거 결재요청 이력을 구분하여 확인합니다.</p>
            </div>
        </div>

        <ul class="nav nav-tabs approval-inbox-tabs" id="approvalInboxTabs">
            <li class="nav-item"><button class="nav-link active" data-box="actionable" type="button">결재할 문서</button></li>
            <li class="nav-item"><button class="nav-link" data-box="progress" type="button">결재 진행 중</button></li>
            <li class="nav-item"><button class="nav-link" data-box="completed" type="button">승인 완료</button></li>
            <li class="nav-item"><button class="nav-link" data-box="rejected" type="button">반려 이력</button></li>
            <li class="nav-item"><button class="nav-link" data-box="submitted" type="button">상신 이력</button></li>
        </ul>

        <p id="approvalInboxScopeDescription" class="mb-3 text-muted small">
            현재 로그인 사용자가 승인 또는 반려해야 할 결재요청입니다.
        </p>

        <div class="approval-inbox-table-card">
            <table id="approvalInboxTable" class="table table-bordered align-middle w-100">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal fade" id="approvalInboxDetailModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">결재문서 상세</h5>
                    <p class="mb-0 text-muted small" id="approvalInboxModalSubtitle"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <section class="approval-detail-card">
                    <div class="approval-detail-card-header"><h6>신청서 기본정보</h6></div>
                    <div id="approvalInboxHeaderFields" class="approval-detail-grid"></div>
                </section>

                <section class="approval-detail-card">
                    <div class="approval-detail-card-header">
                        <div><h6 id="approvalInboxDetailSectionTitle">문서 상세내용</h6><p>서버에서 조회한 전체 문서 상세내용입니다.</p></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle approval-item-table">
                            <thead><tr id="approvalInboxItemHead"></tr></thead>
                            <tbody id="approvalInboxItemBody"></tbody>
                        </table>
                    </div>
                    <div id="approvalInboxTotals" class="approval-detail-totals"></div>
                </section>

                <section class="approval-detail-card">
                    <div class="approval-detail-card-header"><h6>첨부파일·영수증</h6></div>
                    <p id="approvalInboxAttachments" class="mb-0 text-muted small"></p>
                </section>

                <section class="approval-detail-card">
                    <div class="approval-detail-card-header"><h6>현재 결재 진행단계</h6></div>
                    <div id="approvalInboxSteps" class="approval-step-list"></div>
                </section>

                <section class="approval-detail-card">
                    <button type="button" class="approval-history-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#approvalInboxHistory">
                        <span><strong>과거 상신 이력</strong><small>재상신 전 요청과 처리단계를 확인합니다.</small></span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div id="approvalInboxHistory" class="collapse">
                        <div id="approvalInboxHistoryList" class="approval-history-list"></div>
                    </div>
                </section>

                <section id="approvalInboxDecisionArea" class="approval-detail-card d-none">
                    <div class="approval-detail-card-header"><h6>결재 의견</h6></div>
                    <label class="form-label" for="approvalInboxComment">의견 또는 반려사유</label>
                    <textarea id="approvalInboxComment" class="form-control" rows="3" maxlength="1000"></textarea>
                    <div class="form-text">반려 시 사유를 반드시 입력해야 합니다.</div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger d-none" id="approvalInboxReject">반려</button>
                <button type="button" class="btn btn-primary d-none" id="approvalInboxApprove">승인</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>
