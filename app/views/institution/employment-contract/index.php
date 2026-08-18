<?php

use Core\Helpers\AssetHelper;

$pageStyles = AssetHelper::css('/assets/css/components/html-grid.css')
    . AssetHelper::css('/assets/css/pages/institution/employment-contract/index.css');
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
        $dateOptions = '<option value="contract_start_date">계약시작일</option><option value="contract_end_date">계약종료일</option>';
        $searchFieldOptions = '<option value="keyword">계약번호·직원명·상태·계약분류</option>';
        $periodGuideTitle = '근로계약 기간 검색 안내';
        $periodGuideItems = ['계약시작일 또는 계약종료일을 기준으로 조회합니다.'];
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
                            <div class="col-md-4"><label class="form-label">근로시간 구분 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentWorkingTimeType" name="working_time_type" data-code-group="EMPLOYMENT_WORKING_TIME_TYPE" data-code-searchable="true" required></select></div>
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
                            <div class="col-md-3"><label class="form-label">프로젝트</label><select class="form-select form-select-sm" name="project_id"><option value="">프로젝트 검색</option></select></div>
                            <div class="col-12"><label class="form-label">근무장소 상세</label><input class="form-control form-control-sm" name="work_location_detail" maxlength="255"></div>
                            <div class="col-md-6"><label class="form-label">직위·직책</label><input class="form-control form-control-sm" name="job_title_snapshot" readonly></div>
                            <div class="col-md-6"><label class="form-label">종사업무 <span class="text-danger">*</span></label><textarea class="form-control form-control-sm" name="job_description" rows="2" required></textarea></div>
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
                                <div class="col-md-4"><label class="form-label">급여지급일 <span class="text-danger">*</span></label><input class="form-control form-control-sm" type="number" name="payment_day" min="1" max="31" required></div>
                                <div class="col-md-4"><label class="form-label">지급기준 <span class="text-danger">*</span></label><select class="form-select form-select-sm" id="employmentPaymentTiming" name="payment_timing" data-code-group="PAYMENT_TIMING" data-code-searchable="true" required></select></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2"><strong>지급조건</strong><button type="button" class="btn btn-link btn-sm employment-component-add-btn" id="employmentComponentAdd">+추가</button></div>
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
                    <section class="ui-form-card ui-form-card--secondary">
                        <div class="ui-form-card__header">
                            <div class="ui-form-card__heading">
                                <h6 class="ui-form-card__title">수습·비고</h6>
                                <p class="ui-form-card__description">수습기간과 계약 관련 참고사항을 기록합니다.</p>
                                <p class="ui-form-card__description"><code>institution_employment_contracts</code></p>
                            </div>
                        </div>
                        <div class="ui-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">수습시작일</label><span class="date-input"><input class="form-control form-control-sm admin-date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" name="probation_start_date" data-employment-date><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div>
                            <div class="col-md-3"><label class="form-label">수습종료일</label><span class="date-input"><input class="form-control form-control-sm admin-date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" name="probation_end_date" data-employment-date><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div>
                            <div class="col-md-3"><label class="form-label">수습급여율(%)</label><input class="form-control form-control-sm" type="number" name="probation_rate" min="0" max="100" step="0.01"></div>
                            <div class="col-12"><label class="form-label">비고</label><input class="form-control form-control-sm" name="note" maxlength="255"></div>
                        </div>
                        </div>
                    </section>
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
                    <button type="button" class="btn btn-outline-dark btn-sm d-none" id="employmentContractTerminate">종료·해지</button>
                    <button type="submit" class="btn btn-success btn-sm" id="employmentContractSave">임시저장</button>
                    <button type="button" class="btn btn-primary btn-sm d-none" id="employmentContractSubmit">결재요청</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div id="employment-contract-date-picker" class="admin-picker is-hidden"></div>
<div id="employment-contract-time-picker" class="admin-picker is-hidden"></div>

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
include PROJECT_ROOT . '/app/views/dashboard/settings/system/partials/code_modal.php';
?>
