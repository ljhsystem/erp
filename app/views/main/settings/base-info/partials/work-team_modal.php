<?php
// Path: PROJECT_ROOT . '/app/views/main/settings/base-info/partials/work-team_modal.php'
?>

<div class="modal fade" id="workTeamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="workTeamForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="workTeamModalLabel">팀</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body work-team-modal-body">
                    <input type="hidden" name="id" id="modal-work-team-id">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">사업구분 <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="business_unit" id="modal-work-team-business-unit" data-code-group="BUSINESS_UNIT" required>
                                <option value="">사업구분 선택</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">팀명<span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="team_name" id="modal-work-team-team-name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">상태</label>
                            <select class="form-select form-select-sm" name="is_active" id="modal-work-team-is-active">
                                <option value="1">사용</option>
                                <option value="0">미사용</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">팀장</label>
                            <select class="form-select form-select-sm"
                                    name="team_leader_client_id"
                                    id="modal-work-team-team-leader-client-id">
                                <option value=""></option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">비고</label>
                            <input type="text" class="form-control form-control-sm" name="note" id="modal-work-team-note">
                        </div>
                        <div class="col-12">
                            <label class="form-label">메모</label>
                            <textarea class="form-control form-control-sm" name="memo" id="modal-work-team-memo" rows="3"></textarea>
                        </div>
                    </div>
                    <section class="ui-form-card work-team-system-card mt-3" aria-label="시스템 처리 정보">
                        <button type="button" class="ui-form-card__toggle collapsed"
                                data-ui-modal-card-collapse data-bs-target="#workTeamSystemInfoCollapse"
                                aria-expanded="false" aria-controls="workTeamSystemInfoCollapse">
                            <span class="ui-form-card__title">시스템 처리 정보</span>
                            <i class="bi bi-chevron-down ui-form-card__toggle-icon" aria-hidden="true"></i>
                        </button>
                        <div id="workTeamSystemInfoCollapse" class="collapse">
                            <div class="ui-form-card__body work-team-system-info-grid" id="workTeamSystemInfoFields"></div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btnDeleteWorkTeam" class="btn btn-danger btn-sm" style="display:none;">삭제</button>
                    <button type="submit" id="btnSaveWorkTeam" name="work-team-save" class="btn btn-success btn-sm">저장</button>
                    <button type="button" id="btnCloseWorkTeam" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="workTeamQuickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form id="workTeamQuickForm">
                <div class="modal-header">
                    <h5 class="modal-title">팀 빠른 등록</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">사업구분 <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="business_unit" data-code-group="BUSINESS_UNIT" required>
                            <option value="">사업구분 선택</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">팀명 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="team_name" maxlength="100" required>
                    </div>
                    <div class="text-danger small mt-2" data-role="quick-message"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-role="quick-detail">상세입력</button>
                    <button type="submit" class="btn btn-success btn-sm" data-role="quick-submit">저장</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>
