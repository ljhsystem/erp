<?php

use Core\Helpers\AssetHelper;

$pageStyles = AssetHelper::css('/assets/css/pages/institution/employment-contract/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/institution/employment-contract/index.js');
?>
<main class="employment-contract-page" data-page="employment-contract">
    <div class="container-fluid py-4 dt-page-shell">
        <div class="mb-3">
            <div>
                <h5 class="mb-1 fw-bold"><i class="bi bi-file-earmark-person me-2"></i>근로계약관리</h5>
                <p class="text-muted small mb-0">근로계약과 지급조건을 작성하고 공용 전자결재로 승인합니다.</p>
            </div>
        </div>
        <div class="dt-content-stack">
        <?php
        $searchId = 'employmentContract';
        $dateOptions = '<option value="contract_date">계약일</option><option value="contract_start_date">계약시작일</option><option value="contract_end_date">계약종료일</option>';
        $searchFieldOptions = '<option value="keyword">계약번호·직원명·상태·계약분류</option>';
        $periodGuideTitle = '근로계약 기간 검색 안내';
        $periodGuideItems = ['계약일, 계약시작일 또는 계약종료일을 기준으로 조회합니다.'];
        $searchGuideTitle = '근로계약 검색 안내';
        $searchGuideItems = ['계약번호, 직원명, 계약상태, 계약기간·채용방식·근로시간 구분을 검색합니다.'];
        include PROJECT_ROOT . '/app/views/components/ui-search.php';

        $tableId = 'employmentContractTable';
        $tableClass = 'table table-bordered align-middle table-cross-highlight';
        $ajaxUrl = '/api/institution/human-resources/employment-contract/list';
        $columnsType = 'employment-contract';
        $enableButtons = true;
        $enableSearch = true;
        $enablePaging = true;
        $enableReorder = false;
        include PROJECT_ROOT . '/app/views/components/ui-table.php';
        ?>
        </div>
    </div>
</main>

<div class="modal fade ui-form-modal" id="employmentContractModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form id="employmentContractForm" novalidate>
                <div class="modal-header">
                    <div class="ui-form-modal__heading">
                        <h5 class="modal-title mb-0" id="employmentContractModalTitle">근로계약 신규</h5>
                        <p class="ui-form-modal__subtitle">계약 기본정보와 근무·급여조건을 확인하고 전자결재 상태를 관리합니다.</p>
                        <small id="employmentContractStatus" class="text-muted"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id">
                    <input type="hidden" name="current_approval_request_id">
                    <div class="alert alert-warning d-none" id="employmentContractCodeWarning" role="alert"></div>
                    <section class="ui-form-card ui-form-card--primary">
                        <div class="ui-form-card__header">
                            <div class="ui-form-card__heading">
                                <h6 class="ui-form-card__title">계약 기본정보</h6>
                                <p class="ui-form-card__description">계약 대상 직원과 계약유형, 기간 및 근무장소를 설정합니다.</p>
                                <p class="ui-form-card__description"><code>institution_employment_contracts</code></p>
                            </div>
                        </div>
                        <div class="ui-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">직원 <span class="text-danger">*</span></label><select class="form-select form-select-sm" name="employee_id" required><option value="">직원 검색</option></select></div>
                            <div class="col-md-4"><label class="form-label">계약종류 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentContractType" name="contract_type" data-code-group="EMPLOYMENT_CONTRACT_TYPE" data-code-searchable="true" required></select></div>
                            <div class="col-md-4"><label class="form-label">계약기간 구분 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentContractPeriodType" name="contract_period_type" data-code-group="EMPLOYMENT_CONTRACT_PERIOD_TYPE" data-code-searchable="true" required></select></div>
                            <div class="col-md-4"><label class="form-label">고용구분 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentCategory" name="employment_category" data-code-group="EMPLOYMENT_CATEGORY" data-code-searchable="true" required></select></div>
                            <div class="col-md-3"><label class="form-label">고용보험 적용여부</label><select class="form-select form-select-sm" name="employment_insurance_application_status_code"><option value="">미선택</option><option value="APPLICABLE">적용</option><option value="EXCLUDED">미적용</option></select></div>
                            <div class="col-md-3" data-insurance-reason="employment"><label class="form-label">고용보험 미적용 사유</label><input class="form-control form-control-sm" name="employment_insurance_exclusion_reason" maxlength="500"></div>
                            <div class="col-md-3"><label class="form-label">산재보험 적용여부</label><select class="form-select form-select-sm" name="industrial_accident_application_status_code"><option value="">미선택</option><option value="APPLICABLE">적용</option><option value="EXCLUDED">미적용</option></select></div>
                            <div class="col-md-3" data-insurance-reason="industrial"><label class="form-label">산재보험 미적용 사유</label><input class="form-control form-control-sm" name="industrial_accident_exclusion_reason" maxlength="500"></div>
                            <div class="col-md-4"><label class="form-label">근로시간 구분 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentWorkingTimeType" name="working_time_type" data-code-group="EMPLOYMENT_WORKING_TIME_TYPE" data-code-searchable="true" required></select></div>
                            <div class="col-md-3"><label class="form-label">계약일 <span class="text-danger">*</span></label><span class="date-input"><input class="form-control form-control-sm admin-date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" name="contract_date" data-employment-date required><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div>
                            <div class="col-md-3"><label class="form-label">계약시작일 <span class="text-danger">*</span></label><span class="date-input"><input class="form-control form-control-sm admin-date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" name="contract_start_date" data-employment-date required><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div>
                            <div class="col-md-3" id="employmentContractEndDateArea" hidden><label class="form-label">계약종료일 <span class="text-danger d-none" data-contract-end-required>*</span></label><span class="date-input"><input class="form-control form-control-sm admin-date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" name="contract_end_date" data-employment-date><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div>
                            <div class="col-12" id="employmentFixedTermReasonArea" hidden>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label" for="employmentFixedTermReason">기간제 계약 사유 <span class="text-danger d-none" data-fixed-term-required>*</span></label>
                                        <select class="form-select form-select-sm" id="employmentFixedTermReason" name="fixed_term_reason_code" data-code-group="EMPLOYMENT_CONTRACT_FIXED_TERM_REASON" data-code-searchable="true" data-preserve-raw-code-value="true"></select>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label" for="employmentFixedTermReasonDetail">상세 사유 <span class="text-danger d-none" data-fixed-term-detail-required>*</span></label>
                                        <input class="form-control form-control-sm" id="employmentFixedTermReasonDetail" name="fixed_term_reason_detail" maxlength="255">
                                    </div>
                                    <div class="col-12">
                                        <div class="alert alert-warning py-2 mb-0 d-none" id="employmentFixedTermWarning" role="alert"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3"><label class="form-label">근무장소구분 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentWorkLocationType" name="work_location_type" data-code-group="WORK_LOCATION_TYPE" data-code-searchable="true" required></select></div>
                            <div class="col-md-3">
                                <label class="form-label">특정 프로젝트</label>
                                <select class="form-select form-select-sm" name="project_id"><option value="">선택</option></select>
                                <div class="form-text">특정 프로젝트 수행을 전제로 한 계약인 경우에만 선택합니다. 일반 현장근무 직원의 실제 프로젝트 배치는 직무·배치관리에서 관리합니다.</div>
                            </div>
                            <div class="col-12"><label class="form-label">근무장소 상세</label><input class="form-control form-control-sm" name="work_location_detail" maxlength="255"></div>
                            <div class="col-md-4"><label class="form-label">계약 당시 직위·직책 <span class="text-danger">*</span></label><select class="form-select form-select-sm" name="job_title_snapshot" data-preserve-raw-code-value="true" required><option value="">선택</option></select></div>
                            <div class="col-md-8"><label class="form-label">종사업무 <span class="text-danger">*</span></label><textarea class="form-control form-control-sm" name="job_description" rows="2" required></textarea></div>
                        </div>
                        </div>
                    </section>
                    <section class="ui-form-card ui-form-card--info">
                        <div class="ui-form-card__header">
                            <div class="ui-form-card__heading">
                                <h6 class="ui-form-card__title">근무조건</h6>
                                <p class="ui-form-card__description">요일별 소정근로 일정이 기준이며 주간 집계는 자동 계산됩니다.</p>
                                <p class="ui-form-card__description"><code>institution_employment_contracts_weekly_schedules</code> · <code>institution_employment_contracts_work_schedule_policies</code></p>
                            </div>
                        </div>
                        <div class="ui-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">근무형태 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentWorkScheduleType" name="work_schedule_type" data-code-group="WORK_SCHEDULE_TYPE" data-code-searchable="true" required></select></div>
                            <div class="col-md-8 d-flex align-items-end gap-2" id="employmentWeeklyScheduleActions">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="employmentWeeklyScheduleDefaults">기본설정</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="employmentWeeklyScheduleToggle" aria-expanded="false">상세 보기</button>
                            </div>

                            <div class="col-12" id="employmentWeeklyScheduleArea" hidden>
                                <div id="employmentWeeklyScheduleGrid" class="html-grid-host employment-weekly-schedule-grid-host" aria-label="요일별 근무조건"></div>
                                <section class="employment-break-editor mt-3" id="employmentBreakScheduleEditor" hidden aria-live="polite">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                        <div><strong id="employmentBreakScheduleTitle">상세 휴게구간</strong><div class="text-muted small">선택 정보입니다. 입력하면 구간 합계가 휴게시간(분)에 자동 반영됩니다.</div></div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="employmentBreakScheduleClose">닫기</button>
                                    </div>
                                    <div id="employmentBreakScheduleRows"></div>
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="employmentBreakScheduleAdd">+ 휴게구간 추가</button>
                                </section>
                            </div>
                            <div class="col-12" id="employmentSchedulePolicyArea" hidden>
                                <h6 class="mb-3">근무형태 상세설정</h6><div class="row g-3">
                                <div class="col-md-3" data-policy-types="FLEXIBLE OTHER"><label class="form-label">정산기간(일) <span class="text-danger">*</span></label><input class="form-control form-control-sm" type="number" min="1" data-policy-field="settlement_period_days"></div>
                                <div class="col-md-3" data-policy-types="SELECTIVE FLEXIBLE SHIFT OTHER"><label class="form-label">기준 주근로시간 <span class="text-danger">*</span></label><input class="form-control form-control-sm" type="number" min="0.01" max="168" step="0.01" data-policy-field="reference_weekly_hours"></div>
                                <div class="col-md-3" data-policy-types="SELECTIVE OTHER"><label class="form-label">선택가능 시작시간 <span class="text-danger">*</span></label><input class="form-control form-control-sm" data-policy-field="selectable_start_time" data-employment-time></div>
                                <div class="col-md-3" data-policy-types="SELECTIVE OTHER"><label class="form-label">선택가능 종료시간 <span class="text-danger">*</span></label><input class="form-control form-control-sm" data-policy-field="selectable_end_time" data-employment-time></div>
                                <div class="col-md-3" data-policy-types="SELECTIVE OTHER"><label class="form-label">의무근로 시작시간</label><input class="form-control form-control-sm" data-policy-field="core_start_time" data-employment-time></div>
                                <div class="col-md-3" data-policy-types="SELECTIVE OTHER"><label class="form-label">의무근로 종료시간</label><input class="form-control form-control-sm" data-policy-field="core_end_time" data-employment-time></div>
                                <div class="col-12" data-policy-types="SELECTIVE FLEXIBLE SHIFT OTHER"><label class="form-label">근무형태 정책 상세 <span class="text-danger">*</span></label><textarea class="form-control form-control-sm" rows="3" data-policy-field="policy_detail"></textarea></div>
                            </div></div>
                            <div class="col-12"><div class="employment-workday-summary" id="employmentWorkdaySummary">근무형태를 선택해 주세요.</div></div>
                        </div>
                        </div>
                    </section>
                    <section class="ui-form-card">
                        <div class="ui-form-card__header">
                            <div class="ui-form-card__heading">
                                <h6 class="ui-form-card__title">지급조건</h6>
                                <p class="ui-form-card__description">급여 지급기준과 계약 당시 지급항목을 관리합니다.</p>
                                <p class="ui-form-card__description"><code>institution_employment_contracts</code> · <code>institution_employment_contracts_components</code></p>
                            </div>
                        </div>
                        <div class="ui-form-card__body">
                        <div id="employmentContractCompensation">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4"><label class="form-label">급여형태 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentSalaryType" name="salary_type" data-code-group="SALARY_TYPE" data-code-searchable="true" required></select></div>
                                <div class="col-md-4"><label class="form-label">급여지급일 <span class="text-danger">*</span></label><input class="form-control form-control-sm" type="text" name="payment_day" inputmode="numeric" maxlength="2" placeholder="1~31" autocomplete="off" data-employment-payment-day aria-haspopup="dialog" required></div>
                                <div class="col-md-4"><label class="form-label">지급기준 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentPaymentTiming" name="payment_timing" data-code-group="PAYMENT_TIMING" data-code-searchable="true" required></select></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2"><div class="d-flex align-items-center gap-2"><strong>지급조건</strong><button type="button" class="btn btn-outline-secondary btn-sm" id="employmentPayComponentManage">급여항목 관리</button></div><button type="button" class="btn btn-link btn-sm employment-component-add-btn" id="employmentComponentAdd">+추가</button></div>
                            <div id="employmentComponentGrid" class="html-grid-host employment-component-grid-host" aria-label="급여 지급조건"></div>
                            <div class="employment-compensation-summary" id="employmentCompensationSummary" aria-live="polite">
                                <div class="employment-compensation-summary__row employment-compensation-summary__row--total">
                                    <span id="employmentCompensationTotalLabel">지급합계</span>
                                    <strong id="employmentCompensationTotalAmount">0원</strong>
                                </div>
                                <div class="employment-compensation-summary__row d-none" id="employmentCompensationConvertedRow">
                                    <span id="employmentCompensationConvertedLabel"></span>
                                    <strong id="employmentCompensationConvertedAmount"></strong>
                                </div>
                            </div>
                            <div id="employmentComponentAllowanceDetails" class="employment-allowance-details d-none" aria-live="polite"></div>
                        </div>
                        </div>
                    </section>
                    <details class="ui-form-card ui-form-card--secondary employment-system-card" id="employmentStatutoryProjectionCard">
                        <summary class="ui-form-card__header">
                            <div class="ui-form-card__heading">
                                <h6 class="ui-form-card__title">법정기준 검증</h6>
                                <p class="ui-form-card__description">계약 적용일 당시 법정기준과 계약 Snapshot을 읽기 전용으로 비교합니다.</p>
                            </div>
                            <i class="bi bi-chevron-down employment-system-card__toggle" aria-hidden="true"></i>
                        </summary>
                        <div class="ui-form-card__body" id="employmentStatutoryProjection" aria-live="polite">
                            <div class="text-muted">계약을 저장하면 법정기준 검증결과를 확인할 수 있습니다.</div>
                        </div>
                    </details>
                    <details class="ui-form-card ui-form-card--secondary employment-system-card" id="employmentProbationNoteCard">
                        <summary class="ui-form-card__header">
                            <div class="ui-form-card__heading">
                                <h6 class="ui-form-card__title">수습·비고</h6>
                                <p class="ui-form-card__description">수습기간과 계약 관련 참고사항을 기록합니다.</p>
                                <p class="ui-form-card__description"><code>institution_employment_contracts</code></p>
                            </div>
                            <i class="bi bi-chevron-down employment-system-card__toggle" aria-hidden="true"></i>
                        </summary>
                        <div class="ui-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">수습시작일</label><span class="date-input"><input class="form-control form-control-sm admin-date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" name="probation_start_date" data-employment-date><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div>
                            <div class="col-md-3"><label class="form-label">수습종료일</label><span class="date-input"><input class="form-control form-control-sm admin-date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" name="probation_end_date" data-employment-date><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div>
                            <div class="col-md-3"><label class="form-label">수습급여율(%)</label><input class="form-control form-control-sm" type="number" name="probation_rate" min="0" max="100" step="0.01"></div>
                            <div class="col-12"><label class="form-label">비고</label><input class="form-control form-control-sm" name="note" maxlength="255"></div>
                        </div>
                        </div>
                    </details>
                    <details class="ui-form-card ui-form-card--secondary employment-system-card">
                        <summary class="ui-form-card__header">
                            <div class="ui-form-card__heading">
                                <h6 class="ui-form-card__title">시스템 처리정보</h6>
                                <p class="ui-form-card__description">계약 상태와 결재·종료 및 변경 이력을 확인합니다.</p>
                                <p class="ui-form-card__description"><code>institution_employment_contracts</code></p>
                            </div>
                            <i class="bi bi-chevron-down employment-system-card__toggle" aria-hidden="true"></i>
                        </summary>
                        <div class="ui-form-card__body">
                            <div class="row g-3">
                                <div class="col-md-3"><label class="form-label">계약번호</label><input class="form-control form-control-sm" name="contract_no" readonly></div>
                                <div class="col-md-3"><label class="form-label">이전계약</label><input class="form-control form-control-sm" name="previous_contract_id" readonly></div>
                                <div class="col-md-3"><label class="form-label">개정차수</label><input class="form-control form-control-sm" name="revision_no" readonly></div>
                                <div class="col-md-3"><label class="form-label">개정사유</label><input class="form-control form-control-sm" name="revision_reason" readonly></div>
                                <div class="col-md-3"><label class="form-label">계약상태</label><select class="form-select form-select-sm" id="employmentContractState" name="contract_status" data-code-group="EMPLOYMENT_CONTRACT_STATUS" data-code-searchable="true" data-readonly-status disabled></select></div>
                                <div class="col-md-3"><label class="form-label">승인일시</label><input class="form-control form-control-sm" name="approved_at" readonly></div>
                                <div class="col-md-3"><label class="form-label">종료일시</label><input class="form-control form-control-sm" name="terminated_at" readonly></div>
                                <div class="col-md-9"><label class="form-label">종료사유</label><select class="form-select form-select-sm" id="employmentTerminationReason" name="termination_reason" data-code-group="EMPLOYMENT_TERMINATION_REASON" data-code-searchable="true"></select></div>
                                <div class="col-md-3"><label class="form-label">생성일시</label><input class="form-control form-control-sm" name="created_at" readonly></div>
                                <div class="col-md-3"><label class="form-label">생성자</label><input class="form-control form-control-sm" name="created_by_name" readonly></div>
                                <div class="col-md-3"><label class="form-label">수정일시</label><input class="form-control form-control-sm" name="updated_at" readonly></div>
                                <div class="col-md-3"><label class="form-label">수정자</label><input class="form-control form-control-sm" name="updated_by_name" readonly></div>
                                <div class="col-md-3"><label class="form-label">삭제일시</label><input class="form-control form-control-sm" name="deleted_at" readonly></div>
                                <div class="col-md-3"><label class="form-label">삭제자</label><input class="form-control form-control-sm" name="deleted_by_name" readonly></div>
                            </div>
                        </div>
                    </details>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="employmentContractDelete">삭제</button>
                    <button type="button" class="btn btn-outline-warning btn-sm d-none" id="employmentContractWithdraw">회수</button>
                    <button type="button" class="btn btn-outline-primary btn-sm d-none" id="employmentContractRevise">계약 개정</button>
                    <button type="button" class="btn btn-outline-warning btn-sm d-none" id="employmentContractCorrect">입력누락 정정</button>
                    <button type="button" class="btn btn-outline-dark btn-sm d-none" id="employmentContractTerminate">종료·해지</button>
                    <button type="submit" class="btn btn-success btn-sm" id="employmentContractSave">임시저장</button>
                    <button type="button" class="btn btn-primary btn-sm d-none" id="employmentContractSubmit">결재요청</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="employmentPayComponentModal" tabindex="-1" aria-labelledby="employmentPayComponentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-xl-down modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="employmentPayComponentModalTitle">급여항목 관리</h5><p class="mb-0 text-muted small">근로계약과 급여 계산에서 사용할 항목명과 계산·과세 기준을 관리합니다.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button></div>
        <div class="modal-body employment-pay-component-manager">
            <section class="employment-pay-component-list-panel is-table-initializing">
                <div class="employment-pay-component-toolbar"><div><strong>등록된 급여항목</strong><span class="text-muted small" id="employmentPayComponentCount"></span></div><span class="text-muted small">검색·정렬·컬럼 설정은 표 상단에서 관리합니다.</span></div>
                <div class="table-responsive"><table class="table table-hover table-sm align-middle" id="employmentPayComponentTable"></table></div>
                <p class="employment-pay-component-list-help">항목을 선택하면 오른쪽에서 상세 정책을 수정할 수 있습니다. 삭제해도 기존 계약에 저장된 항목명과 금액은 유지됩니다.</p>
            </section>
            <button type="button" class="employment-pay-component-sidebar-backdrop" id="employmentPayComponentSidebarBackdrop" aria-label="편집창 닫기"></button>
            <aside class="employment-pay-component-editor-panel" id="employmentPayComponentEditor" aria-hidden="true"><form id="employmentPayComponentForm"><input type="hidden" name="id">
                <div class="employment-pay-component-editor-heading"><div><strong id="employmentPayComponentEditorTitle">새 급여항목</strong><p class="mb-0 text-muted small" id="employmentPayComponentEditorHint">필수 정보부터 입력해 주세요.</p></div><div class="d-flex align-items-center gap-2"><span class="badge text-bg-light" id="employmentPayComponentEditorMode">신규</span><button type="button" class="btn-close" id="employmentPayComponentEditorClose" aria-label="편집창 닫기"></button></div></div>
                <fieldset><legend>기본정보</legend><div class="row g-2">
                    <div class="col-12"><label class="form-label">항목명 <span class="text-danger">*</span></label><input class="form-control form-control-sm" name="component_name" maxlength="100" placeholder="예: 야간근로수당" required></div>
                    <div class="col-12"><label class="form-label">항목코드 <span class="text-danger">*</span></label><input class="form-control form-control-sm" name="component_code" maxlength="50" placeholder="예: NIGHT_ALLOWANCE" required><div class="form-text">영문 대문자·숫자·밑줄만 사용합니다. 저장 후 코드는 가급적 변경하지 마세요.</div></div>
                    <div class="col-6"><label class="form-label">항목 구분 <span class="text-danger">*</span></label><select class="form-select form-select-sm" name="component_type" required><option value="BASE_PAY">기본급</option><option value="ALLOWANCE">일반수당</option><option value="STATUTORY_PREMIUM">법정 가산수당</option><option value="BONUS">상여금</option><option value="OTHER_WAGE">기타 임금</option></select></div>
                    <div class="col-6"><label class="form-label">기본 계산방식 <span class="text-danger">*</span></label><select class="form-select form-select-sm" name="default_calculation_type" required><option value="FIXED_AMOUNT">월 정액</option><option value="FORMULA">산식 계산</option><option value="HOURLY_RATE">시간급 계산</option></select></div>
                </div></fieldset>
                <fieldset><legend>과세 기준</legend><div class="row g-2"><div class="col-6"><label class="form-label">기본 과세구분 <span class="text-danger">*</span></label><select class="form-select form-select-sm" name="default_tax_type" required><option value="TAXABLE">과세</option><option value="NON_TAXABLE">비과세</option><option value="POLICY_CALCULATED">정책에 따라 계산</option></select></div><div class="col-6"><label class="form-label">세무정책 코드</label><input class="form-control form-control-sm" name="tax_policy_code" maxlength="50" placeholder="정책 적용 시 입력"></div><div class="col-12 form-text">비과세 또는 정책 적용 항목은 관련 법정요건을 확인한 세무정책 코드를 함께 관리합니다.</div></div></fieldset>
                <fieldset><legend>임금 반영 기준</legend><div class="row g-2"><div class="col-4"><label class="form-label">최저임금</label><select class="form-select form-select-sm" name="minimum_wage_treatment"></select></div><div class="col-4"><label class="form-label">통상임금</label><select class="form-select form-select-sm" name="ordinary_wage_treatment"></select></div><div class="col-4"><label class="form-label">평균임금</label><select class="form-select form-select-sm" name="average_wage_treatment"></select></div></div></fieldset>
                <fieldset><legend>사용기간과 상태</legend><div class="row g-2"><div class="col-6"><label class="form-label">사용 시작일</label><span class="date-input"><input class="form-control form-control-sm admin-date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" name="effective_from" data-pay-component-date><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div><div class="col-6"><label class="form-label">사용 종료일</label><span class="date-input"><input class="form-control form-control-sm admin-date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" name="effective_to" data-pay-component-date><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div><div class="col-12"><label class="form-label">관리 메모</label><input class="form-control form-control-sm" name="note" maxlength="255" placeholder="항목 운영 시 참고할 내용을 입력하세요."></div><div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="employmentPayComponentActive" checked><label class="form-check-label" for="employmentPayComponentActive">급여항목 선택 목록에서 사용</label></div></div></div></fieldset>
                <div class="employment-pay-component-editor-actions"><button type="button" class="btn btn-outline-danger btn-sm d-none" id="employmentPayComponentDelete">이 항목 삭제</button><div class="ms-auto d-flex gap-2"><button type="button" class="btn btn-outline-secondary btn-sm" id="employmentPayComponentReset">입력 초기화</button><button type="submit" class="btn btn-success btn-sm">급여항목 저장</button></div></div>
            </form></aside>
        </div>
        <div class="modal-footer"><span class="me-auto text-muted small">변경사항은 저장 즉시 근로계약의 급여항목 목록에 반영됩니다.</span><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button></div>
    </div></div>
</div>
<div id="employment-contract-date-picker" class="admin-picker is-hidden"></div>
<div id="employment-contract-time-picker" class="admin-picker is-hidden"></div>
<div id="employment-contract-payment-day-picker" class="admin-picker is-hidden" aria-hidden="true"></div>
<div id="employment-pay-component-date-picker" class="admin-picker is-hidden" aria-hidden="true"></div>

<?php
$modalId = 'employmentContractTrashModal';
$type = 'employment-contract';
$modalTitle = '근로계약 휴지통';
$tableId = 'employment-contract-trash-table';
$tableHead = '<th>계약번호</th><th>직원명</th><th>상태</th><th>삭제자</th><th>삭제일시</th><th>관리</th>';
$emptyMessage = '휴지통에 근로계약이 없습니다.';
$purgeConfirm = '완전삭제하면 계약과 지급조건이 영구 삭제되며 복구할 수 없습니다. 계속하시겠습니까?';
$listUrl = '/api/institution/human-resources/employment-contract/trash';
$restoreUrl = '/api/institution/human-resources/employment-contract/restore';
$deleteUrl = '/api/institution/human-resources/employment-contract/purge';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
include PROJECT_ROOT . '/app/views/main/settings/system/partials/code_modal.php';
?>
