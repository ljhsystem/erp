<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/brand-logo.php'
?>
<div id="brand-settings-wrapper" class="brand-settings">

    <h5 class="fw-bold mb-3">🏷️ 브랜드관리</h5>

    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">로고 설정</div>
        <div class="card-body">

            <!-- 메인 로고 -->
            <div class="mb-4">
    <label class="form-label fw-semibold">메인 로고 (웹)</label>

    <div class="row align-items-center g-2">

        <!-- 미리보기 -->
        <div class="col-md-1">
            <img id="preview_main_logo"
                 src=""
                 height="40"
                 class="border rounded"
                 style="display:none;">
        </div>


        <!-- 파일 선택 -->
        <div class="col-md-4">
            <input type="file" class="form-control" name="main_logo">
        </div>

        <!-- 설명 -->
        <div class="col-md-6">
            <small class="text-muted">
            헤더, 로그인 화면, 대시보드 등 웹 시스템 전반에 사용됩니다. 
            사내 직원 및 외부 사용자가 가장 먼저 보게 되는 대표 로고입니다.
            </small>
        </div>


    </div>
</div>


            <!-- 인쇄용 로고 -->
            <div class="mb-4">
    <label class="form-label fw-semibold">인쇄용 로고</label>

    <div class="row align-items-center g-2">

    <div class="col-md-1">
            <img id="preview_print_logo"
                 src=""
                 height="40"
                 class="border rounded"
                 style="display:none;">
        </div>


        <div class="col-md-4">
            <input type="file" class="form-control" name="print_logo">
        </div>

        <div class="col-md-6">
            <small class="text-muted">
            계약서, 보고서, 거래명세서 등 PDF 및 인쇄 문서 출력 시 사용됩니다. 
            고해상도 로고 사용을 권장합니다.
            </small>
        </div>


    </div>
</div>


            <!-- 파비콘 -->
            <div class="mb-4">
    <label class="form-label fw-semibold">파비콘</label>

    <div class="row align-items-center g-2">

    <div class="col-md-1">
            <img id="preview_favicon"
                 src=""
                 height="24"
                 class="border rounded"
                 style="display:none;">
        </div>
        <div class="col-md-4">
            <input type="file" class="form-control" name="favicon">
        </div>

        <div class="col-md-6">
            <small class="text-muted">
            브라우저 탭, 즐겨찾기, 주소 표시줄 등에 표시되는 아이콘입니다. 
            작은 크기에서도 식별 가능한 심플한 이미지가 적합합니다.
            </small>
        </div>


    </div>
</div>


<div class="card-footer d-flex justify-content-end bg-light">
        <button id="btn-save-brand" class="btn btn-primary w-100 py-2 fw-bold fs-5">
            저장하기
        </button>
    </div>





        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">기존 파일 목록</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>미리보기</th> <!-- 🔥 미리보기 열 추가 -->
                        <th>파일명</th>
                        <th>원본보기</th>
                        <th>업로드 날짜</th>
                        <th>업로드한 사용자</th>
                        <th>상태</th>
                        <th>액션</th>
                    </tr>
                </thead>
                <tbody id="existing-files">
                    <!-- 기존 파일 목록이 여기에 동적으로 추가됩니다 -->
                </tbody>
            </table>
        </div>
    </div>

</div>
