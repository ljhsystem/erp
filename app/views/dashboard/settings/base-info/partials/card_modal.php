<?php
// 경로: /app/views/dashboard/settings/base-info/partials/card_modal.php
?>

<div class="modal fade" id="cardModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <!-- =========================
           HEADER
      ========================== -->
      <div class="modal-header">
        <h5 class="modal-title fw-bold">💳 카드 정보</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- =========================
           BODY
      ========================== -->
      <form id="cardForm" enctype="multipart/form-data">
        <div class="modal-body">

          <input type="hidden" name="id">

          <div class="row g-3">

            <!-- 별칭 -->
            <div class="col-md-6">
              <label class="form-label">별칭 <span class="text-danger">*</span></label>
              <input type="text" name="alias" class="form-control" required>
            </div>

            <!-- 카드명 -->
            <div class="col-md-6">
              <label class="form-label">카드명</label>
              <input type="text" name="card_name" class="form-control">
            </div>

            <!-- 카드사 -->
            <div class="col-md-6">
              <label class="form-label">카드사</label>
              <input type="text" name="card_company" class="form-control">
            </div>

            <!-- 카드번호 -->
            <div class="col-md-6">
              <label class="form-label">카드번호</label>
              <input type="text" name="card_number" class="form-control" placeholder="****-****-****-****">
            </div>

            <!-- 명의자 -->
            <div class="col-md-6">
              <label class="form-label">명의자</label>
              <input type="text" name="card_holder" class="form-control">
            </div>

            <!-- 결제일 -->
            <div class="col-md-3">
              <label class="form-label">결제일</label>
              <input type="number" name="billing_day" class="form-control" min="1" max="31">
            </div>

            <!-- 만기일 -->
            <div class="col-md-3">
              <label class="form-label">만기일</label>
              <input type="date" name="expiry_date" class="form-control">
            </div>

            <!-- 상태 -->
            <div class="col-md-6">
              <label class="form-label">상태</label>
              <select name="status" class="form-select">
                <option value="active">사용중</option>
                <option value="expired">만기</option>
                <option value="stopped">정지</option>
              </select>
            </div>

          </div>

          <!-- =========================
               파일 영역
          ========================== -->
          <hr class="my-4">

          <h6 class="fw-bold mb-3">📄 카드 사본</h6>

          <div class="row g-3 align-items-center">

            <!-- 파일 업로드 -->
            <div class="col-md-8">
              <input type="file" name="card_file" class="form-control">
            </div>

            <!-- 기존 파일 -->
            <div class="col-md-4">
              <div id="cardPreview" class="small text-muted">
                파일 없음
              </div>
            </div>

            <!-- 삭제 체크 -->
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" name="delete_card_file" value="1" class="form-check-input" id="deleteCardFile">
                <label class="form-check-label text-danger" for="deleteCardFile">
                  카드사본 삭제
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