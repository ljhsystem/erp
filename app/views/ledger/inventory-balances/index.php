<?php
use Core\Helpers\AssetHelper;
$pageTitle='재고관리';
$layoutOptions=['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];
$pageStyles=AssetHelper::css('/assets/css/pages/ledger/inventory-balances/index.css');
$pageScripts=AssetHelper::module('/assets/js/pages/ledger/inventory-balances/index.js');
?>
<main id="inventoryBalancePage" class="container-fluid py-4 dt-page-shell inventory-balance-page">
  <div class="page-header d-flex align-items-center justify-content-between mb-3">
    <div>
      <h5 class="mb-1 fw-bold"><i class="bi bi-box-seam me-2"></i>재고관리</h5>
      <div class="text-muted small">기초재고와 당기 재고가액 증감의 산출근거·증거자료를 기록하여 기말재고를 관리합니다.</div>
    </div>
  </div>
  <div class="content-area">
    <?php
    $tableId='inventory-balance-table';
    $ajaxUrl='/api/ledger/inventory-balance/list';
    $columnsType='inventoryBalance';
    $enableButtons=true;
    $enableSearch=false;
    $enablePaging=true;
    $enableReorder=false;
    include PROJECT_ROOT.'/app/views/components/ui-table.php';
    ?>
  </div>
</main>
<div class="modal fade" id="inventoryBalanceModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-fullscreen-xl-down modal-xl"><div class="modal-content">
  <div class="modal-header"><div><h5 class="modal-title">재고관리 작성</h5><div id="inventoryBalanceStatus" class="small text-muted">상태: 작성중 · 기초재고 + 당기 증가 - 당기 감소 = 기말재고</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" id="inventoryBalanceId">
    <section class="card mb-3"><div class="card-header">문서정보</div><div class="card-body"><div class="row g-2">
      <div class="col-md-4"><label class="form-label">회사 <span class="text-danger">*</span></label><select id="inventoryCompany" class="form-select"></select></div>
      <div class="col-md-3"><label class="form-label">회계연도 <span class="text-danger">*</span></label><input id="inventoryYear" type="number" class="form-control" min="1900" max="9999"></div>
      <div class="col-md-5"><label class="form-label">비고</label><input id="inventoryNote" class="form-control"></div>
    </div></div></section>
    <section class="card"><div class="card-header d-flex align-items-center justify-content-between"><span>재고가액 증감 근거</span><button id="btnAddInventoryItem" type="button" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg"></i> 행 추가</button></div>
      <div class="table-responsive"><table class="table table-sm inventory-entry-table mb-0"><thead><tr><th>순번</th><th>사업구분*</th><th>프로젝트</th><th>재고구분*</th><th>품명*</th><th>기초재고</th><th>당기증가</th><th>당기감소</th><th>기말재고</th><th>산출근거*</th><th>증거자료*</th><th>관리</th></tr></thead><tbody id="inventoryItems"></tbody><tfoot><tr><th colspan="5">합계</th><th id="sumOpening">0원</th><th id="sumIncrease">0원</th><th id="sumDecrease">0원</th><th id="sumEnding">0원</th><th colspan="3"></th></tr></tfoot></table></div>
    </section>
    <div class="alert alert-warning small mt-3 mb-0">수량 수불부를 대신하는 화면이 아닙니다. 각 재고가액의 증가·감소 사유, 산출근거와 확인 가능한 증거자료를 반드시 기록합니다.</div>
  </div>
  <div class="modal-footer justify-content-between"><button id="btnDeleteInventory" type="button" class="btn btn-outline-danger">삭제</button><div class="d-flex gap-2"><button id="btnConfirmInventory" type="button" class="btn btn-outline-primary">기말재고 확정</button><button id="btnSaveInventory" type="button" class="btn btn-success">저장</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button></div></div>
</div></div></div>
