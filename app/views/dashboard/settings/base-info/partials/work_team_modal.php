<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/partials/work_team_modal.php'
?>

<div class="modal fade" id="workTeamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="workTeamForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="workTeamModalLabel">작업팀</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body work-team-modal-body">
                    <input type="hidden" name="id" id="modal_work_team_id">

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">팀명 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="team_name" id="modal_work_team_team_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">상태</label>
                            <select class="form-select form-select-sm" name="is_active" id="modal_work_team_is_active">
                                <option value="1">사용</option>
                                <option value="0">미사용</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">팀장</label>
                            <select class="form-select form-select-sm"
                                    name="team_leader_client_id"
                                    id="modal_work_team_team_leader_client_id">
                                <option value=""></option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">비고</label>
                            <input type="text" class="form-control form-control-sm" name="note" id="modal_work_team_note">
                        </div>
                        <div class="col-12">
                            <label class="form-label">메모</label>
                            <textarea class="form-control form-control-sm" name="memo" id="modal_work_team_memo" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btnDeleteWorkTeam" class="btn btn-danger btn-sm" style="display:none;">삭제</button>
                    <button type="submit" id="btnSaveWorkTeam" name="work_team_save" class="btn btn-success btn-sm">저장</button>
                    <button type="button" id="btnCloseWorkTeam" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>
