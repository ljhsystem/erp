<?php

use Core\Helpers\AssetHelper;

$pageStyles = AssetHelper::css('/assets/css/pages/institution/personnel-action/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/institution/personnel-action/index.js');
?>
<main class="personnel-action-page" data-page="personnel-action">
    <div class="container-fluid py-4">
        <div class="mb-3">
            <h5 class="mb-1 fw-bold"><i class="bi bi-person-lines-fill me-2"></i>인사발령관리</h5>
            <p class="text-muted small mb-0">직원의 입사·조직·직위·직책·재직상태 변경을 공식 발령으로 등록하고 결재·적용 이력을 관리합니다.</p>
        </div>
        <div class="dt-content-stack">
            <?php
            $searchId = 'personnelAction';
            $dateOptions = '<option value="issued_date">발령일</option><option value="action_date">효력일</option>';
            $searchFieldOptions = '<option value="keyword">발령번호·직원명·발령제목·발령사유</option><option value="action_type_code">발령유형</option><option value="business_status">발령상태</option><option value="employee_id">직원</option><option value="department_id">부서</option><option value="position_id">직위·직책</option><option value="job_id">직무</option><option value="approval_status">결재상태</option>';
            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            $tableId='personnelActionTable';$ajaxUrl='/api/institution/human-resources/personnel-action/list';$columnsType='personnel-action';$enableButtons=true;$enableSearch=true;$enablePaging=true;$enableReorder=false;
            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>

<div class="modal fade ui-form-modal" id="personnelActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><form id="personnelActionForm" novalidate>
        <div class="modal-header"><div class="ui-form-modal__heading"><h5 class="modal-title mb-0" id="personnelActionModalTitle">인사발령 신규등록</h5><p class="ui-form-modal__subtitle">발령 기본정보와 대상자별 변경항목을 확인하고 전자결재·적용 상태를 관리합니다.</p><small id="personnelActionStatus" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button></div>
        <div class="modal-body">
            <input type="hidden" name="id"><input type="hidden" name="current_approval_request_id">
            <section class="ui-form-card ui-form-card--primary">
                <div class="ui-form-card__header"><div class="ui-form-card__heading"><h6 class="ui-form-card__title">발령 기본정보</h6><p class="ui-form-card__description">발령유형과 제목, 발령일·효력일 및 사유를 입력합니다.</p><p class="ui-form-card__description"><code>institution_personnel_actions</code></p></div></div>
                <div class="ui-form-card__body"><div class="row g-3">
                <div class="col-md-3"><label class="form-label">발령유형</label><select class="form-select form-select-sm" name="action_type_code" required></select></div>
                <div class="col-md-9"><label class="form-label">발령제목</label><input class="form-control form-control-sm" name="action_name" required maxlength="150"></div>
                <div class="col-md-3"><label class="form-label">발령일</label><span class="date-input"><input type="text" class="form-control form-control-sm admin-date" name="issued_date" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" autocomplete="off" data-personnel-date required><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div>
                <div class="col-md-3"><label class="form-label">효력일</label><span class="date-input"><input type="text" class="form-control form-control-sm admin-date" name="action_date" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" autocomplete="off" data-personnel-date required><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></div>
                <div class="col-md-8"><label class="form-label">발령사유</label><textarea class="form-control form-control-sm" name="action_reason" rows="2"></textarea></div>
                <div class="col-md-4"><label class="form-label">비고</label><textarea class="form-control form-control-sm" name="note" rows="2" maxlength="500"></textarea></div>
                </div></div>
            </section>
            <section class="ui-form-card ui-form-card--info">
                <div class="ui-form-card__header"><div class="ui-form-card__heading"><h6 class="ui-form-card__title">발령 대상 직원</h6><p class="ui-form-card__description">대상자별 변경 전 값은 저장 시 서버가 현재 SSOT에서 다시 생성합니다.</p><p class="ui-form-card__description"><code>institution_personnel_actions_targets</code> · <code>institution_personnel_actions_changes</code></p></div><button type="button" class="btn btn-outline-primary btn-sm align-self-start" id="personnelTargetAdd">+ 대상자 추가</button></div>
                <div class="ui-form-card__body"><div id="personnelTargets"></div></div>
            </section>
            <details class="ui-form-card ui-form-card--secondary personnel-system-card">
                <summary class="ui-form-card__header"><div class="ui-form-card__heading"><h6 class="ui-form-card__title">시스템 처리정보</h6><p class="ui-form-card__description">발령 상태와 결재·적용·감사 정보를 확인합니다.</p><p class="ui-form-card__description"><code>institution_personnel_actions</code></p></div><i class="bi bi-chevron-down personnel-system-card__toggle" aria-hidden="true"></i></summary>
                <div class="ui-form-card__body"><div class="row g-3">
                    <div class="col-md-3"><label class="form-label">발령번호</label><input class="form-control form-control-sm" name="action_no" readonly placeholder="서버 자동생성"></div>
                    <div class="col-md-3"><label class="form-label">발령상태</label><input class="form-control form-control-sm" name="business_status" readonly></div>
                    <div class="col-md-3"><label class="form-label">결재요청 순번</label><input class="form-control form-control-sm" name="approval_request_no" readonly></div>
                    <div class="col-md-3"><label class="form-label">원본 발령번호</label><input class="form-control form-control-sm" name="original_action_no" readonly></div>
                    <div class="col-md-3"><label class="form-label">원본처리구분</label><input class="form-control form-control-sm" name="correction_kind" readonly></div>
                    <div class="col-md-3"><label class="form-label">승인일시</label><input class="form-control form-control-sm" name="approved_at" readonly></div>
                    <div class="col-md-3"><label class="form-label">적용일시</label><input class="form-control form-control-sm" name="applied_at" readonly></div>
                    <div class="col-md-3"><label class="form-label">취소일시</label><input class="form-control form-control-sm" name="cancelled_at" readonly></div>
                    <div class="col-md-6"><label class="form-label">취소사유</label><input class="form-control form-control-sm" name="cancelled_reason" readonly></div>
                    <div class="col-md-3"><label class="form-label">생성일시</label><input class="form-control form-control-sm" name="created_at" readonly></div>
                    <div class="col-md-3"><label class="form-label">생성자</label><input class="form-control form-control-sm" name="created_by_name" readonly></div>
                    <div class="col-md-3"><label class="form-label">수정일시</label><input class="form-control form-control-sm" name="updated_at" readonly></div>
                    <div class="col-md-3"><label class="form-label">수정자</label><input class="form-control form-control-sm" name="updated_by_name" readonly></div>
                    <div class="col-md-3"><label class="form-label">삭제일시</label><input class="form-control form-control-sm" name="deleted_at" readonly></div>
                    <div class="col-md-3"><label class="form-label">삭제자</label><input class="form-control form-control-sm" name="deleted_by_name" readonly></div>
                </div>
                <div id="personnelApprovalSteps" class="small mt-3"></div>
                </div>
            </details>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-danger btn-sm d-none" id="personnelActionDelete">삭제</button><button type="button" class="btn btn-outline-warning btn-sm d-none" id="personnelActionWithdraw">회수</button><button type="button" class="btn btn-outline-success btn-sm d-none" id="personnelActionApply">적용</button><button type="submit" class="btn btn-success btn-sm" id="personnelActionSave">임시저장</button><button type="button" class="btn btn-primary btn-sm d-none" id="personnelActionSubmit">결재요청</button><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button></div>
    </form></div></div>
</div>
<div id="personnel-action-date-picker" class="admin-picker is-hidden"></div>

<template id="personnelTargetTemplate"><article class="personnel-target border rounded p-3 mb-3"><div class="d-flex justify-content-between"><strong>대상자</strong><button type="button" class="btn btn-outline-danger btn-sm target-remove">대상자 삭제</button></div><div class="row g-2 mt-1"><div class="col-md-5"><label class="form-label">직원 <span class="text-danger" title="필수 입력">*</span></label><select class="form-select form-select-sm target-employee" required></select></div><div class="col-md-7"><label class="form-label">개별 발령사유 <span class="text-primary" title="선택 입력">*</span></label><input class="form-control form-control-sm target-reason" maxlength="500"></div></div><div class="current-info small text-muted mt-2"></div><div class="d-flex justify-content-between mt-3"><strong>변경항목</strong><button type="button" class="btn btn-outline-secondary btn-sm change-add">+ 변경항목</button></div><div class="changes mt-2"></div></article></template>
<template id="personnelChangeTemplate"><div class="personnel-change border rounded p-2 mb-2"><div class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">변경구분 <span class="text-danger" title="필수 입력">*</span></label><select class="form-select form-select-sm change-type" required><option value="">발령유형을 먼저 선택해 주세요.</option></select></div><div class="col-md-3"><label class="form-label">변경 전 <span class="text-primary" title="선택 입력">*</span></label><input class="form-control form-control-sm before-label" readonly></div><div class="col-md-4 change-value"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm change-remove">삭제</button></div></div></div></template>

<?php
$modalId='personnelActionTrashModal';$type='personnel-action';$modalTitle='인사발령 휴지통';$tableId='personnel-action-trash-table';$tableHead='<th>발령번호</th><th>발령제목</th><th>상태</th><th>삭제일시</th><th>관리</th>';$emptyMessage='휴지통에 인사발령이 없습니다.';$purgeConfirm='완전삭제하면 인사발령과 대상자·변경항목이 영구 삭제되며 복구할 수 없습니다. 계속하시겠습니까?';$listUrl='/api/institution/human-resources/personnel-action/trash';$restoreUrl='/api/institution/human-resources/personnel-action/restore';$deleteUrl='/api/institution/human-resources/personnel-action/purge';include PROJECT_ROOT.'/app/views/components/ui-modal-trash.php';
?>
