<?php
use Core\Helpers\AssetHelper;
$pageStyles = AssetHelper::css('/assets/css/pages/institution/qualification-education/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/institution/qualification-education/index.js');
$json = static fn($value) => htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
?>
<main class="qualification-education-page" data-bootstrap="<?= $json(['options' => $bootstrap, 'capabilities' => $capabilities]) ?>">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
      <div><h5 class="mb-1 fw-bold"><i class="bi bi-award me-2"></i>자격·교육관리</h5><p class="text-muted small mb-0">직원 자격·교육 이력과 회사 기준 및 직무 요구조건을 관리합니다.</p></div>
    </div>
    <ul class="nav nav-tabs mb-3" id="qeTabs">
      <li class="nav-item"><button class="nav-link active" data-qe-tab="qualifications">자격 현황</button></li>
      <li class="nav-item"><button class="nav-link" data-qe-tab="sessions">교육 일정</button></li>
      <li class="nav-item"><button class="nav-link" data-qe-tab="educations">교육 이력</button></li>
      <?php if (!empty($capabilities['course_manage']) || !empty($capabilities['policy_manage'])): ?><li class="nav-item"><button class="nav-link" data-qe-tab="policies">기준 설정</button></li><?php endif; ?>
    </ul>
    <div class="dt-content-stack" id="qeListArea">
      <?php
      $searchId = 'qualificationEducation';
      $dateOptions = '<option value="valid_to">만료일</option><option value="acquired_date">취득일</option><option value="education_start_at">교육일</option>';
      $searchFieldOptions = '<option value="employee_id">직원</option><option value="qualification_type_id">자격 기준</option><option value="status_code">자격 상태</option><option value="course_id">교육과정</option><option value="completion_status_code">이수 상태</option><option value="expiry_state">만료 구분</option>';
      include PROJECT_ROOT . '/app/views/components/ui-search.php';
      $tableId = 'qualificationEducationTable'; $ajaxUrl = '/api/institution/human-resources/qualification-education/qualification/all-list'; $columnsType = 'qualificationEducation';
      $enableButtons = true; $enableSearch = true; $enablePaging = true; $enableReorder = false;
      include PROJECT_ROOT . '/app/views/components/ui-table.php';
      ?>
    </div>
  </div>
</main>

<div class="modal fade" id="qeQualificationModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <form id="qeQualificationForm" enctype="multipart/form-data"><div class="modal-header"><h5 class="modal-title">자격 정보</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="request_key"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">직원 <span class="text-danger">*</span></label><select class="form-select" name="employee_id" required></select></div>
    <div class="col-md-6"><label class="form-label">자격 기준 <span class="text-danger">*</span></label><select class="form-select" name="qualification_type_id" required></select></div>
    <div class="col-md-6"><label class="form-label">자격명 <span class="text-danger">*</span></label><input class="form-control" name="qualification_name" maxlength="150" required></div>
    <div class="col-md-6"><label class="form-label">자격번호</label><input class="form-control" name="credential_number" maxlength="100"></div>
    <div class="col-md-6"><label class="form-label">발급기관</label><input class="form-control" name="issuer_name" maxlength="150"></div>
    <div class="col-md-3"><label class="form-label">취득일</label><input type="text" class="form-control admin-date qe-date" name="acquired_date" placeholder="YYYY-MM-DD" readonly></div>
    <div class="col-md-3"><label class="form-label">상태 <span class="text-danger">*</span></label><select class="form-select" name="status_code" required></select></div>
    <div class="col-md-3"><label class="form-label">유효 시작일</label><input type="text" class="form-control admin-date qe-date" name="valid_from" placeholder="YYYY-MM-DD" readonly></div>
    <div class="col-md-3"><label class="form-label">만료일</label><input type="text" class="form-control admin-date qe-date" name="valid_to" placeholder="YYYY-MM-DD" readonly></div>
    <div class="col-md-3"><label class="form-label">갱신 예정일</label><input type="text" class="form-control admin-date qe-date" name="renewal_due_date" placeholder="YYYY-MM-DD" readonly></div>
    <div class="col-md-9"><label class="form-label">상태 사유</label><input class="form-control" name="status_reason" maxlength="255"></div>
    <div class="col-12"><label class="form-label">첨부파일</label><input type="file" class="form-control" name="attachment" accept=".pdf,.jpg,.jpeg,.png"><div class="form-text" data-current-file></div></div>
    <div class="col-12"><label class="form-label">비고</label><textarea class="form-control" name="note" rows="3"></textarea></div>
  </div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary">저장</button></div></form>
</div></div></div>

<div class="modal fade" id="qeSessionModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <form id="qeSessionForm"><div class="modal-header"><h5 class="modal-title">교육 일정</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="request_key"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">교육과정 <span class="text-danger">*</span></label><select class="form-select" name="course_id" required></select></div>
    <div class="col-md-6"><label class="form-label">교육명 <span class="text-danger">*</span></label><input class="form-control" name="title" maxlength="200" required></div>
    <div class="col-md-6"><label class="form-label">시작일시 <span class="text-danger">*</span></label><input class="form-control admin-datetime" name="starts_at" readonly required></div>
    <div class="col-md-6"><label class="form-label">종료일시 <span class="text-danger">*</span></label><input class="form-control admin-datetime" name="ends_at" readonly required></div>
    <div class="col-md-6"><label class="form-label">장소</label><input class="form-control" name="location_name" maxlength="150"></div>
    <div class="col-md-6"><label class="form-label">강사</label><input class="form-control" name="instructor_name" maxlength="150"></div>
    <div class="col-md-6"><label class="form-label">담당 직원</label><select class="form-select" name="organizer_employee_id"></select></div>
    <div class="col-12"><label class="form-label">비고</label><textarea class="form-control" name="note" rows="3"></textarea></div>
  </div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary">저장</button></div></form>
</div></div></div>

<div class="modal fade" id="qeSessionTargetModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">교육 대상자 관리</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" id="qeTargetSessionId"><div class="d-flex gap-2 mb-3"><select id="qeTargetEmployee" class="form-select"></select><button type="button" class="btn btn-primary text-nowrap" id="qeTargetAdd">대상 추가</button></div>
  <table id="qeSessionTargetTable" class="table table-bordered w-100"><thead></thead><tbody></tbody></table></div>
</div></div></div>

<div class="modal fade" id="qeEducationModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <form id="qeEducationForm" enctype="multipart/form-data"><div class="modal-header"><h5 class="modal-title">교육 이수 정보</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="request_key"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">직원 <span class="text-danger">*</span></label><select class="form-select" name="employee_id" required></select></div>
    <div class="col-md-6"><label class="form-label">교육과정 <span class="text-danger">*</span></label><select class="form-select" name="course_id" required></select></div>
    <div class="col-md-6"><label class="form-label">교육명</label><input class="form-control" name="education_name" maxlength="200"></div>
    <div class="col-md-6"><label class="form-label">교육기관</label><input class="form-control" name="institution_name" maxlength="150"></div>
    <div class="col-md-6"><label class="form-label">교육 시작 <span class="text-danger">*</span></label><input type="text" class="form-control admin-datetime qe-date" name="education_start_at" placeholder="YYYY-MM-DD HH:mm" readonly required></div>
    <div class="col-md-6"><label class="form-label">교육 종료 <span class="text-danger">*</span></label><input type="text" class="form-control admin-datetime qe-date" name="education_end_at" placeholder="YYYY-MM-DD HH:mm" readonly required></div>
    <div class="col-md-4"><label class="form-label">교육시간(분) <span class="text-danger">*</span></label><input type="number" min="1" class="form-control" name="education_minutes" required></div>
    <div class="col-md-4"><label class="form-label">참석 상태 <span class="text-danger">*</span></label><select class="form-select" name="attendance_status_code" required></select></div>
    <div class="col-md-4"><label class="form-label">이수 상태 <span class="text-danger">*</span></label><select class="form-select" name="completion_status_code" required></select></div>
    <div class="col-md-4"><label class="form-label">수료번호</label><input class="form-control" name="completion_number" maxlength="100"></div>
    <div class="col-md-4"><label class="form-label">유효 시작일</label><input type="text" class="form-control admin-date qe-date" name="valid_from" placeholder="YYYY-MM-DD" readonly></div>
    <div class="col-md-4"><label class="form-label">만료일</label><input type="text" class="form-control admin-date qe-date" name="valid_to" placeholder="YYYY-MM-DD" readonly></div>
    <div class="col-12"><label class="form-label">첨부파일</label><input type="file" class="form-control" name="attachment" accept=".pdf,.jpg,.jpeg,.png"><div class="form-text" data-current-file></div></div>
    <div class="col-12"><label class="form-label">메모</label><textarea class="form-control" name="note" rows="3"></textarea></div>
  </div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary">저장</button></div></form>
</div></div></div>

<div class="modal fade" id="qeCourseModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form id="qeCourseForm">
  <div class="modal-header"><h5 class="modal-title">교육과정 기준정보</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="request_key"><div class="row g-3">
  <div class="col-5"><label class="form-label">과정코드</label><input class="form-control" name="course_code" maxlength="50" required></div><div class="col-7"><label class="form-label">과정명</label><input class="form-control" name="course_name" maxlength="150" required></div>
  <div class="col-6"><label class="form-label">교육 종류</label><select class="form-select" name="education_type_code" required></select></div><div class="col-6"><label class="form-label">기본 기관</label><input class="form-control" name="default_institution_name"></div>
  <div class="col-6"><label class="form-label">기본 시간(분)</label><input type="number" min="0" class="form-control" name="default_minutes"></div><div class="col-6"><label class="form-label">재교육 정책</label><select class="form-select" name="recurrence_policy_code"><option value="NONE">없음</option><option value="PERIODIC">주기</option><option value="EVENT">이벤트</option><option value="STATUTORY">법정기준</option></select></div>
  <div class="col-4"><label class="form-label">주기 값</label><input type="number" min="1" class="form-control" name="recurrence_interval_value"></div><div class="col-4"><label class="form-label">주기 단위</label><select class="form-select" name="recurrence_interval_unit_code"><option value="">선택</option><option value="DAY">일</option><option value="MONTH">개월</option><option value="YEAR">년</option></select></div><div class="col-4"><label class="form-label">발생 이벤트</label><select class="form-select" name="recurrence_event_code"><option value="">선택</option><option value="HIRE">입사</option><option value="JOB_ASSIGNMENT">직무배치</option><option value="SITE_ASSIGNMENT">현장배치</option><option value="WORK_TYPE_CHANGE">작업변경</option></select></div>
  <div class="col-12"><label class="form-label">법정기준 유형</label><input class="form-control" name="statutory_standard_type_code" placeholder="등록된 법정기준 유형 코드"></div>
  <div class="col-6 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_mandatory" value="1"><label class="form-check-label">법정·필수 교육</label></div><div class="col-5 form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">사용</label></div>
  </div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button class="btn btn-primary">저장</button></div>
</form></div></div></div>

<div class="modal fade" id="qeQualificationTypeModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="qeQualificationTypeForm"><div class="modal-header"><h5 class="modal-title">자격 기준</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="request_key"><div class="row g-3"><div class="col-5"><label class="form-label">자격코드</label><input class="form-control" name="qualification_code" required></div><div class="col-7"><label class="form-label">자격명</label><input class="form-control" name="qualification_name" required></div><div class="col-6"><label class="form-label">분류</label><select class="form-select" name="category_code" required></select></div><div class="col-6"><label class="form-label">기본 발급기관</label><input class="form-control" name="default_issuer_name"></div><div class="col-6"><label class="form-label">유효기간 정책</label><select class="form-select" name="validity_policy_code"><option value="PERMANENT">영구</option><option value="FIXED_PERIOD">고정기간</option><option value="RECORD_SPECIFIC">개별기록</option></select></div><div class="col-3"><label class="form-label">기간 값</label><input class="form-control" type="number" min="1" name="validity_value"></div><div class="col-3"><label class="form-label">단위</label><select class="form-select" name="validity_unit_code"><option value="">선택</option><option value="DAY">일</option><option value="MONTH">개월</option><option value="YEAR">년</option></select></div><div class="col-6"><label class="form-label">갱신정책</label><select class="form-select" name="renewal_policy_code"><option value="NONE">없음</option><option value="RENEWAL">갱신</option><option value="CONTINUING_EDUCATION">보수교육</option></select></div><div class="col-6 form-check pt-4"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">사용</label></div><div class="col-12"><label class="form-label">비고</label><textarea class="form-control" name="note"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">취소</button><button class="btn btn-primary">저장</button></div></form></div></div>

<div class="modal fade" id="qeRequirementModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" id="qeRequirementForm"><div class="modal-header"><h5 class="modal-title">직무 요구조건</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="kind"><input type="hidden" name="request_key"><div class="row g-3"><div class="col-12"><label class="form-label">직무</label><select class="form-select" name="job_id" required></select></div><div class="col-12" data-requirement-qualification><label class="form-label">자격 기준</label><select class="form-select" name="qualification_type_id"></select></div><div class="col-12 d-none" data-requirement-education><label class="form-label">교육과정</label><select class="form-select" name="course_id"></select></div><div class="col-12"><label class="form-label">요구수준</label><select class="form-select" name="requirement_level_code"><option value="REQUIRED">필수</option><option value="PREFERRED">권장</option></select></div><div class="col-6"><label class="form-label">적용시작일</label><input class="form-control admin-date" name="effective_from" readonly required></div><div class="col-6"><label class="form-label">적용종료일</label><input class="form-control admin-date" name="effective_to" readonly></div><div class="col-12"><label class="form-label">비고</label><textarea class="form-control" name="note"></textarea></div></div></div><div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">취소</button><button class="btn btn-primary">저장</button></div></form></div></div>
<div id="qeDatePicker" class="picker admin-picker is-hidden"></div><div id="qeDateTimePicker" class="picker admin-picker is-hidden"></div>
