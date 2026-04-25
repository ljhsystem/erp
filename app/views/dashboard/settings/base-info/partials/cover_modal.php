<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/partials/cover_modal.php'
?>

<div class="modal fade" id="coverImageModal" tabindex="-1" aria-labelledby="coverImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="cover-image-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="cover_id" id="modal_cover_id">
                <input type="hidden" name="action" id="modal_action" value="save">

                <div class="modal-header">
                    <h5 class="modal-title" id="coverImageModalLabel">커버이미지 정보</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>

                <div class="modal-body cover-modal-body">
                    <div class="row align-items-start">
                        <div class="col-md-5">
                            <div style="width:100%;max-width:350px;min-height:350px;border:4px solid #000;padding:8px;background:#fff;">
                                <div style="font-size:1.8rem;font-weight:700;">View</div>
                                <img
                                    id="modal-image-preview"
                                    src=""
                                    style="width:100%;margin-top:10px;display:none;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.12);background:#f8f8f8;"
                                    alt="커버 이미지 미리보기"
                                >
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="mb-2">
                                <label for="modal_year">해당년도</label>
                                <select name="year" id="modal_year" class="form-select form-select-sm" required>
                                    <option value="">선택하세요</option>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label for="modal_cover_image">이미지</label>
                                <input type="file" name="cover_image" id="modal_cover_image" class="form-control form-control-sm" accept="image/*">
                                <div class="form-text">이미지 파일만 업로드할 수 있습니다.</div>
                            </div>

                            <div class="mb-2">
                                <label for="modal_is_active">상태</label>
                                <select name="is_active" id="modal_is_active" class="form-select form-select-sm">
                                    <option value="1">사용</option>
                                    <option value="0">미사용</option>
                                </select>
                                <div class="form-text">미사용 상태의 이미지는 공개 페이지에 게시되지 않습니다.</div>
                            </div>

                            <div class="mb-2">
                                <label for="modal_title">타이틀</label>
                                <input type="text" name="title" id="modal_title" class="form-control form-control-sm">
                            </div>

                            <div class="mb-2">
                                <label for="modal_alt">대체문구(Alt)</label>
                                <input type="text" name="alt" id="modal_alt" class="form-control form-control-sm">
                            </div>

                            <div class="mb-2">
                                <label for="modal_description">설명(Description)</label>
                                <input type="text" name="description" id="modal_description" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-danger btn-sm" id="modal_delete_btn">삭제</button>
                    <button type="submit" class="btn btn-success btn-sm" id="modal_save_btn">저장</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="originalImageModal" tabindex="-1" aria-labelledby="originalImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="originalImageModalLabel">원본 이미지 보기</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>

            <div class="modal-body text-center">
                <img
                    id="original-image-view"
                    src=""
                    alt="원본 이미지"
                    class="img-fluid rounded shadow-sm"
                    style="max-height:70vh;"
                >
            </div>
        </div>
    </div>
</div>
