<?php
use Core\Helpers\AssetHelper;
$pageStyles = AssetHelper::css('/assets/css/pages/institution/employment-rules/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/institution/employment-rules/index.js');
$bootstrapData = htmlspecialchars(json_encode(['options'=>$options, 'capabilities'=>$cap], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
?>
<main class="employment-rules-page" data-bootstrap="<?= $bootstrapData ?>">
  <div class="container-fluid py-4">
    <div class="mb-3">
      <h5 class="fw-bold"><i class="bi bi-journal-check me-2"></i>취업규칙·인사규정</h5>
      <p class="text-muted small mb-0">회사 공식 규정문서와 개정·시행 이력을 관리합니다.</p>
    </div>
    <div class="dt-content-stack">
      <?php
      $searchId='employmentRules';
      $dateOptions='<option value="effective_from">시행일</option><option value="revision_date">제정·개정일</option>';
      $searchFieldOptions='<option value="regulation_type_code">규정종류</option><option value="status_code">상태</option><option value="is_current">현재 유효 여부(1/0)</option><option value="regulation_code">규정코드</option><option value="title">규정명</option>';
      $dateInputAttrs='readonly autocomplete="off"';
      include PROJECT_ROOT.'/app/views/components/ui-search.php';
      ?>
      <?php $tableId='employmentRulesTable'; $ajaxUrl='/api/institution/human-resources/employment-rules/list'; $columnsType='employmentRules'; $enableButtons=true; $enableSearch=true; $enablePaging=true; $enableReorder=false; include PROJECT_ROOT.'/app/views/components/ui-table.php'; ?>
    </div>
  </div>
</main>

<div class="modal fade" id="ruleModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="ruleForm">
  <div class="modal-header"><div><h5 class="modal-title">규정 개정본</h5><p class="small text-muted mb-0" data-rule-mode-label>신규 규정 또는 초안을 작성합니다.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="company_id"><input type="hidden" name="request_key"><input type="hidden" name="document_file_path"><input type="hidden" name="document_file_name">
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label">규정종류</label><select class="form-select" name="regulation_type_code" required></select></div>
      <div class="col-md-3"><label class="form-label">규정코드</label><input class="form-control" name="regulation_code" required></div>
      <div class="col-md-6"><label class="form-label">규정명</label><input class="form-control" name="regulation_title" required></div>
      <div class="col-md-6"><label class="form-label">개정본 제목</label><input class="form-control" name="title" required></div>
      <div class="col-md-3"><label class="form-label">제정·개정일</label><span class="date-input"><input type="text" class="form-control admin-date" name="revision_date" placeholder="YYYY-MM-DD" readonly required><i class="fa fa-calendar-days date-icon"></i></span></div>
      <div class="col-md-3"><label class="form-label">소관부서</label><select class="form-select" name="owner_department_id"></select></div>
      <div class="col-md-3"><label class="form-label">시행일</label><span class="date-input"><input type="text" class="form-control admin-date" name="effective_from" placeholder="YYYY-MM-DD" readonly required><i class="fa fa-calendar-days date-icon"></i></span></div>
      <div class="col-md-3"><label class="form-label">종료일</label><span class="date-input"><input type="text" class="form-control admin-date" name="effective_to" placeholder="YYYY-MM-DD" readonly><i class="fa fa-calendar-days date-icon"></i></span></div>
      <div class="col-md-6"><label class="form-label">변경요약</label><input class="form-control" name="change_summary"></div>
      <div class="col-md-6"><label class="form-label">변경사유</label><input class="form-control" name="change_reason" required></div>
      <div class="col-12"><label class="form-label">설명</label><input class="form-control" name="description"></div>
      <div class="col-12"><label class="form-label">규정 본문</label><textarea class="form-control" rows="12" name="content_text"></textarea></div>
      <div class="col-12"><div class="border rounded p-3 bg-light"><strong>원본파일</strong><div class="small text-muted mt-1" data-file-metadata>공용 파일관리 연계 전까지 기존 파일 metadata만 조회합니다.</div></div></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary me-auto" id="ruleHistory">개정이력</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button><button class="btn btn-primary" data-save-rule>저장</button></div>
</form></div></div>

<div class="modal fade" id="ruleHistoryModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">개정이력</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr><th>Revision</th><th>제목</th><th>개정일</th><th>시행기간</th><th>상태</th><th>변경요약</th></tr></thead><tbody id="ruleHistoryRows"></tbody></table></div></div>
</div></div></div>

<div id="employmentRuleDatePicker" class="picker admin-picker is-hidden"></div>
