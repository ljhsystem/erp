<?php
use Core\Helpers\AssetHelper;
$layoutOptions=['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];
$pageStyles=AssetHelper::css('/assets/css/pages/ledger/book/purchase-sales/index.css');
$pageScripts=AssetHelper::module('/assets/js/pages/ledger/book/purchase-sales/index.js');
?>
<main class="ledger-purchase-sales" id="ledgerPurchaseSalesPage"><div class="container-fluid py-4 dt-page-shell">
 <div class="page-header"><div><h5 class="mb-0 fw-bold"><i class="bi bi-receipt-cutoff me-2"></i>매입매출장</h5><div class="text-muted small mt-1">전표와 연결된 세금계산서·현금영수증·카드 증빙의 매출·매입과 부가세를 조회합니다.</div></div><span id="purchaseSalesCount" class="text-primary page-count"></span></div>
 <?php $searchId='ledgerPurchaseSales';$dateOptions='<option value="evidence_date">증빙일자</option>';$searchFieldOptions='<option value="">선택</option>';$periodGuideTitle='매입매출장 기간 조건 안내';$periodGuideItems=['증빙 원본의 작성일·승인일을 기준으로 조회합니다.','공식 장부에는 전기완료·마감 전표와 연결된 증빙만 포함됩니다.'];$searchGuideTitle='매입매출장 검색 조건 안내';$searchGuideItems=['매출·매입, 증빙유형, 거래처, 사업자번호, 전표번호, 프로젝트로 검색할 수 있습니다.','거래와 전표는 증빙원본 연결을 통해 추적하며 원본의 공급가액·부가세·합계금액을 보존합니다.'];include PROJECT_ROOT.'/app/views/components/ui-search.php';?>
 <section class="purchase-sales-scope"><div><strong>공식 장부</strong><span>전기완료·마감 전표와 연결된 증빙만 조회합니다.</span></div></section>
 <section class="purchase-sales-summary" aria-label="매입매출장 합계"><div class="is-sales"><span>매출 공급가액</span><strong id="salesSupply">0원</strong></div><div class="is-sales"><span>매출 부가세</span><strong id="salesVat">0원</strong></div><div class="is-sales"><span>매출 합계</span><strong id="salesTotal">0원</strong></div><div class="is-purchase"><span>매입 공급가액</span><strong id="purchaseSupply">0원</strong></div><div class="is-purchase"><span>매입 부가세</span><strong id="purchaseVat">0원</strong></div><div class="is-purchase"><span>매입 합계</span><strong id="purchaseTotal">0원</strong></div></section>
 <section class="purchase-sales-panel"><?php $tableId='ledgerPurchaseSalesTable';$ajaxUrl='/api/ledger/book/purchase-sales/list';$columnsType='ledger-purchase-sales';$tableClass='table table-bordered align-middle table-cross-highlight ledger-purchase-sales-table';$enableButtons=true;$enableSearch=true;$enablePaging=true;$enableReorder=false;include PROJECT_ROOT.'/app/views/components/ui-table.php';?></section>
 <div class="purchase-sales-help text-muted small"><i class="bi bi-info-circle me-1"></i>이 화면은 조회 장부입니다. 금액·거래처·프로젝트 오류는 증빙원본을 정정한 뒤 전표 연결과 전기 상태를 다시 확인합니다.</div>
</div></main>
