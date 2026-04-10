<?php
// 경로: /app/views/dashboard/settings/base-info/partials/card_modal.php
?>

<div class="modal fade" id="cardModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <form id="cardForm" enctype="multipart/form-data">

        <!-- HEADER -->
        <div class="modal-header">
          <h5 class="modal-title">카드 등록/수정</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <!-- BODY -->
        <div class="modal-body">

          <input type="hidden" name="id">

          <!-- =========================
               기본 정보
          ========================== -->
          <div class="card mb-3">
            <div class="card-header py-1 px-2">기본 정보</div>
            <div class="card-body py-2">

              <div class="row g-2">

                <div class="col-md-2">
                  <label class="form-label">코드</label>
                  <input type="text" name="code"
                    class="form-control form-control-sm"
                    readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">카드명 *</label>
                  <input type="text" name="card_name"
                    class="form-control form-control-sm" required>
                </div>

                <div class="col-md-3">
                  <label class="form-label">카드유형</label>
                  <select name="card_type"
                    class="form-select form-select-sm">
                    <option value="corporate">법인</option>
                    <option value="personal">개인</option>
                    <option value="virtual">가상</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">사용여부</label>
                  <select name="is_active"
                    class="form-select form-select-sm">
                    <option value="1">사용</option>
                    <option value="0">미사용</option>
                  </select>
                </div>

              </div>

              <div class="row g-2 mt-1">

                <div class="col-md-6">
                  <label class="form-label">카드번호</label>
                  <input type="text" name="card_number"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                  <label class="form-label">유효기간(년)</label>
                  <input type="text" name="expiry_year"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                  <label class="form-label">유효기간(월)</label>
                  <input type="text" name="expiry_month"
                    class="form-control form-control-sm">
                </div>

              </div>

            </div>
          </div>

          <!-- =========================
               결제 / 계좌
          ========================== -->
          <div class="card mb-3">
            <div class="card-header py-1 px-2">결제 및 계좌</div>
            <div class="card-body py-2">

              <div class="row g-2">

                <div class="col-md-4">
                  <label class="form-label">카드사</label>
                  <select name="client_id"
                    class="form-select form-select-sm"
                    id="cardClientSelect">
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">결제계좌</label>
                  <select name="account_id"
                    class="form-select form-select-sm"
                    id="cardAccountSelect">
                  </select>
                </div>

                <div class="col-md-2">
                  <label class="form-label">통화</label>
                  <input type="text" name="currency"
                    class="form-control form-control-sm"
                    value="KRW">
                </div>

                <div class="col-md-2">
                  <label class="form-label">한도금액</label>
                  <input type="number" name="limit_amount"
                    class="form-control form-control-sm">
                </div>

              </div>

            </div>
          </div>

          <!-- =========================
               첨부 및 비고
          ========================== -->
          <div class="card mb-3">
            <div class="card-header py-1 px-2">첨부 및 비고</div>
            <div class="card-body py-2">

              <div class="row g-2">

                <!-- 좌측 -->
                <div class="col-md-8">

                  <div class="row g-2">

                    <div class="col-md-12">
                      <label class="form-label">비고</label>
                      <input type="text" name="note"
                        class="form-control form-control-sm">
                    </div>

                    <div class="col-md-12">
                      <label class="form-label">메모</label>
                      <textarea name="memo"
                        class="form-control form-control-sm"
                        rows="6"
                        style="max-height:120px; overflow:auto;"></textarea>
                    </div>

                  </div>

                </div>

                <!-- 우측 파일 -->
                <div class="col-md-4">

                  <label class="form-label">카드 이미지</label>

                  <div class="file-upload-box" id="cardUpload">

                    <div id="cardUploadText">
                      파일 드롭 또는 클릭<br>(PDF, JPG, PNG)
                    </div>

                    <input type="file"
                      id="modal_card_file"
                      name="card_file"
                      hidden>

                    <input type="hidden"
                      name="delete_card_file"
                      id="delete_card_file"
                      value="0">

                  </div>

                  <div id="cardFileList" class="file-list mt-2"></div>

                </div>

              </div>

            </div>
          </div>

        </div>

        <!-- FOOTER -->
        <div class="modal-footer">

          <button type="button"
            id="btnDeleteCard"
            class="btn btn-danger btn-sm"
            style="display:none;">
            삭제
          </button>

          <button type="submit"
            class="btn btn-success btn-sm">
            저장
          </button>

          <button type="button"
            class="btn btn-secondary btn-sm"
            data-bs-dismiss="modal">
            닫기
          </button>

        </div>

      </form>

    </div>
  </div>
</div>