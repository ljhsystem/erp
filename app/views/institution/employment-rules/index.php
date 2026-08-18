<?php
use Core\Helpers\AssetHelper;
$pageStyles = AssetHelper::css('/assets/css/pages/institution/employment-rules/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/institution/employment-rules/index.js');
$bootstrapData = htmlspecialchars(json_encode(['options' => $options, 'capabilities' => $cap], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$tabs = ['COMPANY'=>'회사 기본규정','WORK'=>'근무규정','LEAVE'=>'휴가규정','PAYROLL'=>'급여규정','EDUCATION'=>'교육규정','QUALIFICATION'=>'자격규정','PROMOTION'=>'승진규정','REWARD'=>'포상규정','DISCIPLINE'=>'징계규정','WELFARE'=>'복리후생','OTHER'=>'기타'];
?>
<main class="employment-rules-page" data-bootstrap="<?= $bootstrapData ?>">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div><h5 class="fw-bold"><i class="bi bi-journal-check me-2"></i>취업규칙·인사규정</h5><p class="text-muted small">회사 정책과 승인된 개정·시행 이력을 관리합니다.</p></div>
      <div><?php if ($cap['save'] ?? false): ?><button class="btn btn-primary btn-sm" id="ruleAdd">규정 등록</button><?php endif; ?> <?php if ($cap['excel'] ?? false): ?><a class="btn btn-outline-success btn-sm" href="/api/institution/human-resources/employment-rules/excel">Excel 다운로드</a><?php endif; ?></div>
    </div>
    <ul class="nav nav-tabs mb-3" id="ruleTabs"><?php foreach ($tabs as $key => $label): ?><li class="nav-item"><button type="button" class="nav-link<?= $key === 'COMPANY' ? ' active' : '' ?>" data-type="<?= $key ?>"><?= $label ?></button></li><?php endforeach; ?></ul>
    <div class="dt-content-stack">
      <?php $searchId='employmentRules'; $dateOptions='<option value="effective_from">시행일</option>'; $searchFieldOptions='<option value="status_code">상태</option><option value="rule_code">규정코드</option><option value="title">규정명</option>'; include PROJECT_ROOT.'/app/views/components/ui-search.php'; ?>
      <?php $tableId='employmentRulesTable'; $ajaxUrl='/api/institution/human-resources/employment-rules/list'; $columnsType='employmentRules'; $enableButtons=true; $enableSearch=true; $enablePaging=true; $enableReorder=false; include PROJECT_ROOT.'/app/views/components/ui-table.php'; ?>
    </div>
  </div>
</main>
<div class="modal fade" id="ruleModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="ruleForm">
  <div class="modal-header"><h5 class="modal-title">규정 개정본</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="id"><input type="hidden" name="company_id"><input type="hidden" name="request_key">
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label">규정코드</label><input class="form-control" name="rule_code" required></div>
      <div class="col-md-3"><label class="form-label">종류</label><select class="form-select" name="rule_type_code" required></select></div>
      <div class="col-md-6"><label class="form-label">규정명</label><input class="form-control" name="title" required></div>
      <div class="col-md-6"><label class="form-label">개정본 제목</label><input class="form-control" name="revision_title" required></div>
      <div class="col-md-3"><label class="form-label">시행일</label><input type="date" class="form-control admin-date" name="effective_from" required></div>
      <div class="col-md-3"><label class="form-label">종료일</label><input type="date" class="form-control admin-date" name="effective_to"></div>
      <div class="col-12"><label class="form-label">개정 사유</label><input class="form-control" name="revision_reason" required></div>
      <div class="col-12"><label class="form-label">규정 본문</label><textarea class="form-control" rows="7" name="content_text"></textarea></div>
      <div class="col-12"><div class="d-flex justify-content-between"><label class="form-label">구조화 정책</label><button type="button" class="btn btn-outline-primary btn-sm" id="addPolicy">정책 추가</button></div><div id="policyRows"></div></div>
      <div class="col-12"><div class="d-flex justify-content-between"><label class="form-label">적용범위</label><button type="button" class="btn btn-outline-primary btn-sm" id="addScope">범위 추가</button></div><div id="scopeRows"></div></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">취소</button><button class="btn btn-primary">저장</button></div>
</form></div></div>
<div id="employmentRuleTimePicker" class="picker admin-picker is-hidden"></div>
