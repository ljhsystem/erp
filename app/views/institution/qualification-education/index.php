<?php
use Core\Helpers\AssetHelper;
$pageStyles = AssetHelper::css('/assets/css/pages/institution/qualification-education/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/institution/qualification-education/index.js');
$json = static fn($value) => htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
?>
<main class="qualification-education-page" data-bootstrap="<?= $json(['options' => $bootstrap, 'capabilities' => $capabilities]) ?>">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
      <div><h5 class="mb-1 fw-bold"><i class="bi bi-award me-2"></i>자격·교육관리</h5><p class="text-muted small mb-0">직원 자격과 교육 이수, 만료·갱신 대상을 하나의 원장으로 관리합니다.</p></div>
      <div class="d-flex gap-2 flex-wrap justify-content-end">
        <?php if (!empty($capabilities['save'])): ?><button type="button" class="btn btn-primary btn-sm" data-qe-action="qualification-add">자격 등록</button><?php endif; ?>
        <?php if (!empty($capabilities['education_manage'])): ?><button type="button" class="btn btn-outline-primary btn-sm" data-qe-action="education-add">교육 등록</button><?php endif; ?>
        <?php if (!empty($capabilities['excel'])): ?><a id="qeExcel" class="btn btn-outline-success btn-sm" href="/api/institution/human-resources/qualification-education/excel">Excel 다운로드</a><?php endif; ?>
      </div>
    </div>
    <ul class="nav nav-tabs mb-3" id="qeTabs">
      <li class="nav-item"><button class="nav-link active" data-qe-tab="qualifications">자격증 현황</button></li>
      <li class="nav-item"><button class="nav-link" data-qe-tab="educations">교육 이수현황</button></li>
      <li class="nav-item"><button class="nav-link" data-qe-tab="expiring">만료·갱신 대상</button></li>
      <?php if (!empty($capabilities['course_manage'])): ?><li class="nav-item"><button class="nav-link" data-qe-tab="courses">자격증·교육 기준정보</button></li><?php endif; ?>
    </ul>
    <div class="dt-content-stack" id="qeListArea">
      <?php
      $searchId = 'qualificationEducation';
      $dateOptions = '<option value="valid_to">만료일</option><option value="acquired_date">취득일</option><option value="education_start_at">교육일</option>';
      $searchFieldOptions = '<option value="employee_id">직원</option><option value="qualification_type_code">자격 종류</option><option value="status_code">자격 상태</option><option value="course_id">교육과정</option><option value="completion_status_code">이수 상태</option><option value="expiry_state">만료 구분</option>';
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
    <div class="col-md-6"><label class="form-label">자격 종류 <span class="text-danger">*</span></label><select class="form-select" name="qualification_type_code" required></select></div>
    <div class="col-md-6"><label class="form-label">자격명 <span class="text-danger">*</span></label><input class="form-control" name="qualification_name" maxlength="150" required></div>
    <div class="col-md-6"><label class="form-label">자격번호</label><input class="form-control" name="credential_number" maxlength="100"></div>
    <div class="col-md-6"><label class="form-label">발급기관</label><input class="form-control" name="issuer_name" maxlength="150"></div>
    <div class="col-md-3"><label class="form-label">취득일</label><input type="date" class="form-control admin-date qe-date" name="acquired_date"></div>
    <div class="col-md-3"><label class="form-label">상태 <span class="text-danger">*</span></label><select class="form-select" name="status_code" required></select></div>
    <div class="col-md-3"><label class="form-label">유효 시작일</label><input type="date" class="form-control admin-date qe-date" name="valid_from"></div>
    <div class="col-md-3"><label class="form-label">만료일</label><input type="date" class="form-control admin-date qe-date" name="valid_to"></div>
    <div class="col-md-3"><label class="form-label">갱신 예정일</label><input type="date" class="form-control admin-date qe-date" name="renewal_due_date"></div>
    <div class="col-md-9"><label class="form-label">상태 사유</label><input class="form-control" name="status_reason" maxlength="255"></div>
    <div class="col-12"><label class="form-label">첨부파일</label><input type="file" class="form-control" name="attachment" accept=".pdf,.jpg,.jpeg,.png"><div class="form-text" data-current-file></div></div>
    <div class="col-12"><label class="form-label">비고</label><textarea class="form-control" name="note" rows="3"></textarea></div>
  </div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary">저장</button></div></form>
</div></div></div>

<div class="modal fade" id="qeEducationModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <form id="qeEducationForm" enctype="multipart/form-data"><div class="modal-header"><h5 class="modal-title">교육 이수 정보</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="request_key"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">직원 <span class="text-danger">*</span></label><select class="form-select" name="employee_id" required></select></div>
    <div class="col-md-6"><label class="form-label">교육과정 <span class="text-danger">*</span></label><select class="form-select" name="course_id" required></select></div>
    <div class="col-md-6"><label class="form-label">교육명</label><input class="form-control" name="education_name" maxlength="200"></div>
    <div class="col-md-6"><label class="form-label">교육기관</label><input class="form-control" name="institution_name" maxlength="150"></div>
    <div class="col-md-6"><label class="form-label">교육 시작 <span class="text-danger">*</span></label><input type="datetime-local" class="form-control admin-date qe-date" name="education_start_at" required></div>
    <div class="col-md-6"><label class="form-label">교육 종료 <span class="text-danger">*</span></label><input type="datetime-local" class="form-control admin-date qe-date" name="education_end_at" required></div>
    <div class="col-md-4"><label class="form-label">교육시간(분) <span class="text-danger">*</span></label><input type="number" min="1" class="form-control" name="education_minutes" required></div>
    <div class="col-md-4"><label class="form-label">참석 상태 <span class="text-danger">*</span></label><select class="form-select" name="attendance_status_code" required></select></div>
    <div class="col-md-4"><label class="form-label">이수 상태 <span class="text-danger">*</span></label><select class="form-select" name="completion_status_code" required></select></div>
    <div class="col-md-4"><label class="form-label">수료번호</label><input class="form-control" name="completion_number" maxlength="100"></div>
    <div class="col-md-4"><label class="form-label">유효 시작일</label><input type="date" class="form-control admin-date qe-date" name="valid_from"></div>
    <div class="col-md-4"><label class="form-label">만료일</label><input type="date" class="form-control admin-date qe-date" name="valid_to"></div>
    <div class="col-12"><label class="form-label">첨부파일</label><input type="file" class="form-control" name="attachment" accept=".pdf,.jpg,.jpeg,.png"><div class="form-text" data-current-file></div></div>
    <div class="col-12"><label class="form-label">메모</label><textarea class="form-control" name="note" rows="3"></textarea></div>
  </div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary">저장</button></div></form>
</div></div></div>

<div class="modal fade" id="qeCourseModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form id="qeCourseForm">
  <div class="modal-header"><h5 class="modal-title">교육과정 기준정보</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="request_key"><div class="row g-3">
  <div class="col-5"><label class="form-label">과정코드</label><input class="form-control" name="course_code" maxlength="50" required></div><div class="col-7"><label class="form-label">과정명</label><input class="form-control" name="course_name" maxlength="150" required></div>
  <div class="col-6"><label class="form-label">교육 종류</label><select class="form-select" name="education_type_code" required></select></div><div class="col-6"><label class="form-label">기본 기관</label><input class="form-control" name="default_institution_name"></div>
  <div class="col-6"><label class="form-label">기본 시간(분)</label><input type="number" min="1" class="form-control" name="default_minutes"></div><div class="col-6"><label class="form-label">유효기간(개월)</label><input type="number" min="0" class="form-control" name="validity_months"></div>
  <div class="col-6 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_mandatory" value="1"><label class="form-check-label">법정·필수 교육</label></div><div class="col-5 form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">사용</label></div>
  </div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button class="btn btn-primary">저장</button></div>
</form></div></div></div>
