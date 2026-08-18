<?php
use Core\Helpers\AssetHelper;
$pageStyles = AssetHelper::css('/assets/css/pages/institution/job-assignment/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/institution/job-assignment/index.js');
?>
<main class="job-assignment-page" data-page="job-assignment"><div class="container-fluid py-4 dt-page-shell">
    <div class="mb-3"><h5 class="mb-1 fw-bold"><i class="bi bi-diagram-3 me-2"></i>직무·배치관리</h5><p class="text-muted small mb-0">기간이력 SSOT를 기준일별로 조회합니다. 현재 주요 변경은 인사발령으로만 처리합니다.</p></div>
    <div class="dt-content-stack">
        <?php
        $searchId = 'jobAssignment';
        $dateOptions = '<option value="as_of_date">기준일</option>';
        $searchFieldOptions = '<option value="keyword">직원명·아이디</option><option value="employee_id">직원</option><option value="employment_status">재직상태</option><option value="department_id">부서</option><option value="position_id">직위·직책</option><option value="job_id">직무</option><option value="project_id">프로젝트</option><option value="workplace_type_code">근무지 유형</option><option value="assignment_status">배치상태</option><option value="current_only">현재 배치만 보기</option><option value="include_ended">종료된 배치 포함</option>';
        $periodGuideTitle = '기준일 안내';
        $periodGuideItems = ['기준일 한 날짜를 입력하면 해당 날짜의 직무·배치 상태를 조회합니다.', '시작일과 종료일이 다르면 종료일을 기준일로 사용합니다.'];
        include PROJECT_ROOT . '/app/views/components/ui-search.php';
        $tableId='jobAssignmentTable';$ajaxUrl='/api/institution/human-resources/job-assignment/list';$columnsType='job-assignment';$enableButtons=true;$enableSearch=true;$enablePaging=true;$enableReorder=false;
        include PROJECT_ROOT.'/app/views/components/ui-table.php';
        ?>
    </div>
</div></main>
<?php require PROJECT_ROOT . '/app/views/institution/job-assignment/assignment_form_modal.php'; ?>
<div class="modal fade" id="jobAssignmentDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h5 class="modal-title">직무·배치 상세이력</h5><small class="text-muted" id="jobAssignmentEmployeeSummary"></small></div><button class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button></div><div class="modal-body"><div id="jobAssignmentCurrentSummary" class="alert alert-light border"></div><ul class="nav nav-tabs" id="jobAssignmentTabs"></ul><div class="tab-content border border-top-0 rounded-bottom p-3" id="jobAssignmentTabContent"></div></div><div class="modal-footer"><a class="btn btn-warning btn-sm" href="/institution/human-resources/personnel-actions">인사발령에서 변경</a><button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button></div></div></div></div>
<script type="application/json" id="jobAssignmentOptions"><?= json_encode(['capabilities' => $capabilities], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?></script>
