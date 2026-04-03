<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/base-info_cover_modal_edit.php'
// 설명: 커버이미지 등록/수정 + 원본 이미지 보기 모달
?>

<!-- ============================================================
     커버사진 등록 / 수정 모달
============================================================ -->
<div class="modal fade" id="coverImageModal" tabindex="-1" aria-labelledby="coverImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <form id="cover-image-form" method="post" enctype="multipart/form-data">

                <input type="hidden" name="cover_id" id="modal_cover_id">
                <input type="hidden" name="action" id="modal_action" value="save">

                <div class="modal-header">
                    <h5 class="modal-title" id="coverImageModalLabel">커버사진 정보</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body cover-modal-body">
                    <div class="row align-items-start">

                        <div class="col-md-5">
                            <div style="width:100%;max-width:350px;min-height:350px;border:4px solid #000;padding:8px;background:#fff;">
                                <div style="font-size:1.8rem;font-weight:700;">View</div>
                                <img id="modal-image-preview"
                                     src=""
                                     style="width:100%;margin-top:10px;display:none;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.12);background:#f8f8f8;">
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="mb-2">
                                <label for="modal_year">해당년도(Year)</label>
                                <select name="year" id="modal_year" class="form-select form-select-sm" required>
                                    <option value="">선택하세요</option>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label>이미지(View)</label>
                                <input type="file" name="cover_image" id="modal_cover_image" class="form-control form-control-sm" accept="image/*">
                                <div class="form-text">파일 업로드만 허용됩니다.</div>
                            </div>

                            <div class="mb-2">
                                <label>타이틀(Title)</label>
                                <input type="text" name="title" id="modal_title" class="form-control form-control-sm">
                            </div>

                            <div class="mb-2">
                                <label>이미지문구(Alt)</label>
                                <input type="text" name="alt" id="modal_alt" class="form-control form-control-sm">
                            </div>

                            <div class="mb-2">
                                <label>설명(Description)</label>
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

<!-- ============================================================
     원본 이미지 보기 모달
============================================================ -->
<div class="modal fade" id="originalImageModal" tabindex="-1" aria-labelledby="originalImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="originalImageModalLabel">원본 이미지 보기</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center">
                <img id="original-image-view"
                     src=""
                     style="max-width:100%;max-height:70vh;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.15);">
            </div>

        </div>
    </div>
</div>