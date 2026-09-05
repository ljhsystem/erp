<?php
use Core\Helpers\AssetHelper;
$pageStyles=AssetHelper::css('/assets/css/pages/site/sales/index.css');
$pageScripts=AssetHelper::module('/assets/js/pages/site/sales/index.js');
$layoutOptions=['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];
?>
<main id="siteSalesPage" class="container-fluid py-3 dt-page-shell">
    <div class="page-header mb-3">
        <h5 class="fw-bold"><i class="bi bi-bullseye me-2"></i>영업관리</h5>
        <div class="text-muted small">거래처와 독립적으로 업체·인물 관계를 축적하고, 다음 영업활동과 수주 가능성을 관리합니다.</div>
    </div>
    <section class="sales-kpi-grid mb-3" aria-label="영업관리 현황">
        <article><span>관리 업체</span><strong id="salesOrganizationCount">0건</strong></article>
        <article><span>관리 인물</span><strong id="salesPeopleCount">0명</strong></article>
        <article><span>진행 기회</span><strong id="salesOpportunityCount">0건</strong></article>
        <article class="is-warning"><span>기한 지난 활동</span><strong id="salesOverdueCount">0건</strong></article>
        <article><span>예상 수주금액</span><strong id="salesExpectedAmount">0원</strong></article>
    </section>
    <div class="content-area">
        <?php $searchId='siteSales';$tableId='site-sales-table';$ajaxUrl='/api/site/sales/list';$columnsType='site-sales';$enableButtons=true;$enableSearch=true;$enablePaging=true;$enableReorder=false;include PROJECT_ROOT.'/app/views/components/ui-table.php';?>
    </div>
</main>

<div class="modal fade" id="salesOrganizationModal" tabindex="-1" aria-labelledby="salesOrganizationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="salesOrganizationModalTitle">영업대상 등록</h5><small id="salesAnalysisHeadline" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button></div>
        <div class="modal-body">
            <ul class="nav nav-tabs sales-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#salesBasicPane" type="button">업체정보</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#salesPeoplePane" type="button">인물·명함</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#salesActivityPane" type="button">활동이력</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#salesOpportunityPane" type="button">영업기회</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#salesFollowupPane" type="button">다음 활동</button></li>
            </ul>
            <div class="tab-content pt-3">
                <section id="salesBasicPane" class="tab-pane fade show active">
                    <form id="salesOrganizationForm" class="row g-2">
                        <input type="hidden" id="salesOrganizationId">
                        <div class="col-md-4"><label class="form-label">업체명 *</label><input id="salesOrganizationName" class="form-control" maxlength="150" required></div>
                        <div class="col-md-2"><label class="form-label">구분 *</label><select id="salesOrganizationType" class="form-select"><option value="COMPANY">업체</option><option value="INSTITUTION">기관</option><option value="OTHER">기타</option></select></div>
                        <div class="col-md-3"><label class="form-label">영업담당자 *</label><select id="salesOwnerEmployee" class="form-select" required></select></div>
                        <div class="col-md-3"><label class="form-label">관계수준 *</label><select id="salesRelationshipLevel" class="form-select"><option value="NEW">신규</option><option value="COLD">관계낮음</option><option value="WARM">관계형성</option><option value="HOT">적극협의</option><option value="TRUSTED">신뢰관계</option></select></div>
                        <div class="col-md-3"><label class="form-label">업종·주력분야</label><input id="salesIndustryName" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">영업지역</label><input id="salesBusinessArea" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">대표자</label><input id="salesRepresentativeName" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">대표 연락처</label><input id="salesMainPhone" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">주소</label><input id="salesAddress" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">알게 된 경로</label><input id="salesIntroductionSource" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">다음 활동일</label><input id="salesNextActionDate" type="date" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">다음 활동</label><input id="salesNextActionSummary" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">관리상태</label><select id="salesStatus" class="form-select"><option value="ACTIVE">관리중</option><option value="HOLD">보류</option><option value="CLOSED">종료</option></select></div>
                        <div class="col-12"><label class="form-label">참고사항</label><textarea id="salesOrganizationNote" class="form-control" rows="2"></textarea></div>
                    </form>
                    <div class="sales-analysis-box mt-3"><strong>시스템 분석</strong><p id="salesAnalysisReason" class="mb-0">업체를 저장하고 활동을 기록하면 판단근거가 표시됩니다.</p></div>
                </section>
                <section id="salesPeoplePane" class="tab-pane fade"><div class="sales-split"><form id="salesPersonForm" class="sales-entry-card"><h6>인물 등록</h6><div class="row g-2"><div class="col-md-4"><label>이름 *</label><input id="salesPersonName" class="form-control"></div><div class="col-md-4"><label>부서</label><input id="salesPersonDepartment" class="form-control"></div><div class="col-md-4"><label>직책</label><input id="salesPersonPosition" class="form-control"></div><div class="col-md-4"><label>휴대전화</label><input id="salesPersonMobile" class="form-control"></div><div class="col-md-4"><label>이메일</label><input id="salesPersonEmail" type="email" class="form-control"></div><div class="col-md-4"><label>영향역할</label><select id="salesPersonInfluence" class="form-select"><option value="DECISION_MAKER">의사결정자</option><option value="PRACTITIONER">실무자</option><option value="INTRODUCER">소개자</option><option value="INFLUENCER">영향자</option><option value="OTHER">기타</option></select></div><div class="col-md-4"><label>선호 연락방식</label><select id="salesPersonContact" class="form-select"><option value="">선택안함</option><option value="PHONE">전화</option><option value="MESSAGE">문자·메신저</option><option value="EMAIL">이메일</option><option value="VISIT">방문</option></select></div><div class="col-md-4"><label>연락하기 좋은 시간</label><input id="salesPersonContactTime" class="form-control"></div><div class="col-md-4"><label>담당자 *</label><select id="salesPersonOwner" class="form-select"></select></div><div class="col-md-6"><label>관심·선호사항</label><input id="salesPersonInterests" class="form-control"></div><div class="col-md-6"><label>주의·약속사항</label><input id="salesPersonCautions" class="form-control"></div></div><button type="button" id="salesAddPerson" class="btn btn-primary btn-sm mt-2">인물 등록</button></form><div id="salesPeopleList" class="sales-record-list"></div></div></section>
                <section id="salesActivityPane" class="tab-pane fade"><div class="sales-split"><form class="sales-entry-card"><h6>활동 기록</h6><div class="row g-2"><div class="col-md-3"><label>활동유형 *</label><select id="salesActivityType" class="form-select"><option value="PHONE">전화</option><option value="MESSAGE">문자·메신저</option><option value="EMAIL">이메일</option><option value="VISIT">방문</option><option value="MEETING">회의</option><option value="MEAL">식사</option><option value="SITE">현장동행</option><option value="OTHER">기타</option></select></div><div class="col-md-3"><label>일시 *</label><input id="salesActivityAt" type="datetime-local" class="form-control"></div><div class="col-md-3"><label>만난 인물</label><select id="salesActivityPerson" class="form-select"></select></div><div class="col-md-3"><label>장소</label><input id="salesActivityLocation" class="form-control"></div><div class="col-12"><label>수행내용 *</label><textarea id="salesActivitySummary" class="form-control" rows="2"></textarea></div><div class="col-md-6"><label>상대방 요청</label><input id="salesCustomerRequest" class="form-control"></div><div class="col-md-6"><label>우리의 약속</label><input id="salesOurCommitment" class="form-control"></div></div><button type="button" id="salesAddActivity" class="btn btn-primary btn-sm mt-2">활동 기록</button></form><div id="salesActivityList" class="sales-record-list"></div></div></section>
                <section id="salesOpportunityPane" class="tab-pane fade"><div class="sales-split"><form class="sales-entry-card"><h6>영업기회 등록</h6><div class="row g-2"><div class="col-md-6"><label>예상 공사·기회명 *</label><input id="salesOpportunityName" class="form-control"></div><div class="col-md-3"><label>관련 인물</label><select id="salesOpportunityPerson" class="form-select"></select></div><div class="col-md-3"><label>진행단계 *</label><select id="salesOpportunityStage" class="form-select"><option value="DISCOVERY">가능성 발견</option><option value="CONTACT">접촉</option><option value="CONSULTING">상담</option><option value="ESTIMATE_READY">견적예정</option><option value="ESTIMATE">견적진행</option><option value="NEGOTIATION">협의</option><option value="WON">수주</option><option value="LOST">실주</option><option value="HOLD">보류</option></select></div><div class="col-md-4"><label>예상 공종</label><input id="salesOpportunityWorkType" class="form-control"></div><div class="col-md-4"><label>예상금액</label><input id="salesOpportunityAmount" type="number" min="0" class="form-control"></div><div class="col-md-4"><label>담당자 가능률(%)</label><input id="salesOpportunityRate" type="number" min="0" max="100" value="0" class="form-control"></div><div class="col-md-6"><label>가능성 판단근거</label><input id="salesOpportunityReason" class="form-control"></div><div class="col-md-6"><label>경쟁상황</label><input id="salesCompetitorNote" class="form-control"></div></div><button type="button" id="salesAddOpportunity" class="btn btn-primary btn-sm mt-2">영업기회 등록</button></form><div id="salesOpportunityList" class="sales-record-list"></div></div></section>
                <section id="salesFollowupPane" class="tab-pane fade"><div class="sales-split"><form class="sales-entry-card"><h6>다음 활동 등록</h6><div class="row g-2"><div class="col-md-4"><label>담당자 *</label><select id="salesFollowupOwner" class="form-select"></select></div><div class="col-md-4"><label>처리기한 *</label><input id="salesFollowupDueAt" type="datetime-local" class="form-control"></div><div class="col-md-4"><label>관련 인물</label><select id="salesFollowupPerson" class="form-select"></select></div><div class="col-12"><label>다음 활동 *</label><input id="salesFollowupSummary" class="form-control"></div></div><button type="button" id="salesAddFollowup" class="btn btn-primary btn-sm mt-2">다음 활동 등록</button></form><div id="salesFollowupList" class="sales-record-list"></div></div></section>
            </div>
        </div>
        <div class="modal-footer"><button type="button" id="salesSaveOrganization" class="btn btn-success">저장</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button></div>
    </div></div>
</div>
