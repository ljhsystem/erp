<?php
?>
<div id="brand-settings-wrapper" class="brand-settings">
    <h5 class="fw-bold mb-3">브랜드 관리</h5>

    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">로고 설정</div>
        <div class="card-body">
            <div class="mb-4">
                <label class="form-label fw-semibold">메인 로고</label>
                <div class="row align-items-center g-2">
                    <div class="col-md-1">
                        <img id="preview_main_logo" src="" height="40" class="border rounded" style="display:none;">
                    </div>
                    <div class="col-md-4">
                        <input type="file" class="form-control" name="main_logo" accept=".png,.jpg,.jpeg,.svg,.webp">
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">
                            헤더, 로그인 화면, 대시보드 등 일반 화면에 사용하는 기본 로고입니다.
                        </small>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">인쇄용 로고</label>
                <div class="row align-items-center g-2">
                    <div class="col-md-1">
                        <img id="preview_print_logo" src="" height="40" class="border rounded" style="display:none;">
                    </div>
                    <div class="col-md-4">
                        <input type="file" class="form-control" name="print_logo" accept=".png,.jpg,.jpeg,.svg,.webp">
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">
                            계약서, 보고서, PDF, 인쇄 문서 출력에 사용하는 로고입니다.
                        </small>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">파비콘</label>
                <div class="row align-items-center g-2">
                    <div class="col-md-1">
                        <img id="preview_favicon" src="" height="24" class="border rounded" style="display:none;">
                    </div>
                    <div class="col-md-4">
                        <input type="file" class="form-control" name="favicon" accept=".png,.ico,.svg">
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">
                            브라우저 탭과 즐겨찾기에 표시되는 작은 아이콘입니다.
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end bg-light">
            <button id="btn-save-brand" class="btn btn-primary w-100 py-2 fw-bold fs-5">저장하기</button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold text-primary">기존 파일 목록</div>
        <div class="card-body">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>미리보기</th>
                        <th>구분</th>
                        <th>파일명</th>
                        <th>업로드일</th>
                        <th>업로드 사용자</th>
                        <th>상태</th>
                        <th>작업</th>
                    </tr>
                </thead>
                <tbody id="existing-files">
                    <tr>
                        <td colspan="7" class="text-center text-muted">등록된 브랜드 파일을 불러오는 중입니다.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
