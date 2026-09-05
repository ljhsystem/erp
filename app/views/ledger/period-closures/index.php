<?php
use Core\Helpers\AssetHelper;
$layoutOptions=['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];
$pageStyles=AssetHelper::css('/assets/css/pages/ledger/period-closures/index.css');
$pageScripts=AssetHelper::module('/assets/js/pages/ledger/period-closures/index.js');
$titles=['check'=>'결산점검','periods'=>'회계기간 마감','carry-forward'=>'기초금액 이월'];$title=$titles[$periodClosurePageMode]??'결산관리';
?>
<main id="periodClosurePage" class="container-fluid py-4 dt-page-shell" data-mode="<?= htmlspecialchars($periodClosurePageMode,ENT_QUOTES,'UTF-8') ?>">
 <div class="page-header d-flex justify-content-between align-items-center mb-3"><div><h5 class="fw-bold mb-1"><i class="bi bi-lock me-2"></i><?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?></h5><div class="small text-muted">전기 완료 자료를 점검하고 회계기간을 마감한 뒤 차기 기초금액으로 이월합니다.</div></div></div>
 <section class="card mb-3"><div class="card-body"><div class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label">회사 <span class="text-danger">*</span></label><select id="closureCompany" class="form-select"></select></div><div class="col-md-3"><label class="form-label">회계연도 <span class="text-danger">*</span></label><input id="closureYear" type="number" min="1900" max="9998" class="form-control"></div><div class="col-md-5 d-flex gap-2"><button id="btnClosureCheck" class="btn btn-outline-primary">결산점검</button><button id="btnClosePeriod" class="btn btn-danger">회계기간 마감</button><button id="btnReopenPeriod" class="btn btn-outline-danger">재개방</button><button id="btnCarryForward" class="btn btn-success">차기 기초금액 이월</button></div></div></div></section>
 <section id="closureReadiness" class="row g-3 mb-3 d-none"></section>
 <section class="content-area"><?php $tableId='period-closure-table';$ajaxUrl='/api/ledger/period-closure/list';$columnsType='periodClosure';$enableButtons=true;$enableSearch=false;$enablePaging=true;$enableReorder=false;include PROJECT_ROOT.'/app/views/components/ui-table.php';?></section>
</main>
<div class="modal fade" id="closureReasonModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 id="closureReasonTitle" class="modal-title">처리 사유</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">사유 <span class="text-danger">*</span></label><textarea id="closureReason" class="form-control" rows="4" maxlength="500"></textarea><div class="alert alert-warning small mt-3 mb-0">마감 후 해당 기간의 전표는 변경할 수 없습니다. 재개방도 감사이력으로 영구 보존됩니다.</div></div><div class="modal-footer"><button id="btnSubmitClosure" class="btn btn-danger">처리</button><button class="btn btn-secondary" data-bs-dismiss="modal">닫기</button></div></div></div></div>
