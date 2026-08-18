<div class="modal fade ui-form-modal" id="jobAssignmentFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="jobAssignmentForm" autocomplete="off">
                <div class="modal-header">
                    <div class="ui-form-modal__heading">
                        <h5 class="modal-title" id="jobAssignmentFormTitle">직무·배치 등록</h5>
                        <p class="ui-form-modal__subtitle mb-0" id="jobAssignmentFormDescription">직접 처리 허용 범위만 등록합니다.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <section class="ui-form-card ui-form-card--primary">
                        <div class="ui-form-card__header"><div class="ui-form-card__heading"><h6 class="ui-form-card__title">처리 대상</h6><p class="ui-form-card__description">직원과 직무 또는 프로젝트, 적용 기간을 입력합니다.</p></div></div>
                        <div class="ui-form-card__body">
                            <input type="hidden" name="operation">
                            <input type="hidden" name="assignment_domain">
                            <input type="hidden" name="assignment_id">
                            <input type="hidden" name="request_key">
                            <div class="row g-3">
                                <div class="col-md-6" data-field="employee_id"><label class="form-label">직원 <span class="text-danger">*</span></label><select class="form-select form-select-sm" name="employee_id"></select></div>
                                <div class="col-md-6 d-none" data-field="job_id"><label class="form-label">직무 <span class="text-danger">*</span></label><select class="form-select form-select-sm" name="job_id"></select></div>
                                <div class="col-md-6 d-none" data-field="project_id"><label class="form-label">프로젝트 <span class="text-danger">*</span></label><select class="form-select form-select-sm" name="project_id"></select></div>
                                <div class="col-md-6 d-none" data-field="assignment_role"><label class="form-label">프로젝트 역할</label><input class="form-control form-control-sm" name="assignment_role" maxlength="100"></div>
                                <div class="col-md-6" data-field="start_date"><label class="form-label">시작일 <span class="text-danger">*</span></label><div class="input-group input-group-sm"><input class="form-control" name="start_date" inputmode="numeric"><button class="btn btn-outline-secondary date-picker-trigger" type="button" aria-label="시작일 선택"><i class="bi bi-calendar3"></i></button></div></div>
                                <div class="col-md-6" data-field="end_date"><label class="form-label">종료일 <span class="text-danger" data-required-end>*</span></label><div class="input-group input-group-sm"><input class="form-control" name="end_date" inputmode="numeric"><button class="btn btn-outline-secondary date-picker-trigger" type="button" aria-label="종료일 선택"><i class="bi bi-calendar3"></i></button></div></div>
                            </div>
                        </div>
                    </section>
                    <section class="ui-form-card mt-3">
                        <div class="ui-form-card__header"><div class="ui-form-card__heading"><h6 class="ui-form-card__title">감사 정보</h6><p class="ui-form-card__description">출처와 처리 사유는 감사 증적으로 보존됩니다.</p></div></div>
                        <div class="ui-form-card__body"><div class="row g-3"><div class="col-md-6"><label class="form-label">처리 출처 <span class="text-danger">*</span></label><select class="form-select form-select-sm" name="source_type"></select></div><div class="col-12"><label class="form-label">처리 사유 <span class="text-danger">*</span></label><textarea class="form-control form-control-sm" name="reason" rows="3" maxlength="1000"></textarea></div></div></div>
                    </section>
                    <div class="alert alert-warning small mt-3 mb-0" id="jobAssignmentFormPolicy"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary btn-sm" id="jobAssignmentFormSubmit">저장</button></div>
            </form>
        </div>
    </div>
</div>
