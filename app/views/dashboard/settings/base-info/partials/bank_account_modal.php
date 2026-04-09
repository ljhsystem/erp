<?php
// 경로: /app/views/dashboard/settings/base-info/partials/bank_account_modal.php
?>

<div class="modal fade" id="accountModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <!-- =========================
           HEADER
      ========================== -->
      <div class="modal-header">
        <h5 class="modal-title fw-bold">🏦 계좌 정보</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- =========================
           BODY
      ========================== -->
      <form id="accountForm" enctype="multipart/form-data">
        <div class="modal-body">

          <input type="hidden" name="id">

          <div class="row g-3">

            <!-- 별칭 -->
            <div class="col-md-6">
              <label class="form-label">별칭 <span class="text-danger">*</span></label>
              <input type="text" name="alias" class="form-control" required>
            </div>

            <!-- 계좌명 -->
            <div class="col-md-6">
              <label class="form-label">계좌명</label>
              <input type="text" name="account_name" class="form-control">
            </div>

            <!-- 은행명 -->
            <div class="col-md-6">
              <label class="form-label">은행명</label>
              <input type="text" name="bank_name" class="form-control">
            </div>

            <!-- 계좌번호 -->
            <div class="col-md-6">
              <label class="form-label">계좌번호</label>
              <input type="text" name="account_number" class="form-control">
            </div>

            <!-- 예금주 -->
            <div class="col-md-6">
              <label class="form-label">예금주</label>
              <input type="text" name="account_holder" class="form-control">
            </div>

            <!-- 통화 -->
            <div class="col-md-6">
              <label class="form-label">통화</label>
              <input type="text" name="currency" class="form-control" placeholder="KRW">
            </div>

          </div>

          <!-- =========================
               파일 영역
          ========================== -->
          <hr class="my-4">

          <h6 class="fw-bold mb-3">📄 통장사본</h6>

          <div class="row g-3 align-items-center">

            <!-- 파일 업로드 -->
            <div class="col-md-8">
              <input type="file" name="bank_book_file" class="form-control">
            </div>

            <!-- 기존 파일 -->
            <div class="col-md-4">
              <div id="bankBookPreview" class="small text-muted">
                파일 없음
              </div>
            </div>

            <!-- 삭제 체크 -->
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" name="delete_bank_book_file" value="1" class="form-check-input" id="deleteBankFile">
                <label class="form-check-label text-danger" for="deleteBankFile">
                  통장사본 삭제
                </label>
              </div>
            </div>

          </div>

        </div>

        <!-- =========================
             FOOTER
        ========================== -->
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
          <button type="submit" class="btn btn-primary">저장</button>
        </div>

      </form>

    </div>
  </div>
</div>