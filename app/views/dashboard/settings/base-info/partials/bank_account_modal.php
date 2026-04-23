<?php
// 경로: /app/views/dashboard/settings/base-info/partials/bank_account_modal.php
?>

<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <form id="accountForm" enctype="multipart/form-data">

        <div class="modal-header">
          <h5 class="modal-title">계좌 정보</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
        </div>

        <div class="modal-body">

          <input type="hidden" name="id" id="modal_account_id">

          <div class="card mb-3">
            <div class="card-header py-1 px-2">기본 정보</div>
            <div class="card-body py-2">

              <div class="row g-2">

                <div class="col-md-2">
                  <label class="form-label">순번</label>
                  <input type="text"
                         name="sort_no"
                         id="modal_sort_no"
                         class="form-control form-control-sm"
                         placeholder="자동 생성"
                         readonly>
                </div>

                <div class="col-md-3">
                  <label class="form-label">계좌명 *</label>
                  <input type="text"
                         name="account_name"
                         class="form-control form-control-sm"
                         required>
                </div>

                <div class="col-md-3">
                  <label class="form-label">은행명</label>
                  <input type="text"
                         name="bank_name"
                         class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                  <label class="form-label">계좌번호</label>
                  <input type="text"
                         name="account_number"
                         class="form-control form-control-sm">
                </div>

              </div>

              <div class="row g-2 mt-1">

                <div class="col-md-3">
                  <label class="form-label">예금주</label>
                  <input type="text"
                         name="account_holder"
                         class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                  <label class="form-label">계좌유형</label>
                  <select name="account_type" class="form-select form-select-sm">
                    <option value="">선택</option>
                    <option value="보통예금">보통예금</option>
                    <option value="정기예금">정기예금</option>
                    <option value="적금">적금</option>
                    <option value="대출">대출</option>
                    <option value="외화예금">외화예금</option>
                  </select>
                </div>

                <div class="col-md-2">
                  <label class="form-label">통화</label>
                  <input type="text"
                         name="currency"
                         class="form-control form-control-sm"
                         placeholder="KRW"
                         value="KRW">
                </div>

                <div class="col-md-4">
                  <label class="form-label">사용여부</label>
                  <select name="is_active" class="form-select form-select-sm">
                    <option value="1">사용</option>
                    <option value="0">미사용</option>
                  </select>
                </div>

              </div>

            </div>
          </div>

          <div class="card mb-3">
            <div class="card-header py-1 px-2">첨부 및 메모</div>
            <div class="card-body py-2">

              <div class="row g-2">

                <div class="col-md-8">
                  <div class="row g-2">

                    <div class="col-md-12">
                      <label class="form-label">비고</label>
                      <input type="text"
                             name="note"
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

                <div class="col-md-4">
                  <label class="form-label">통장사본</label>

                  <div class="file-upload-box" id="bankBookUpload">
                    <div id="bankBookText">
                      파일 드롭 또는 클릭<br>(PDF, JPG, PNG)
                    </div>

                    <input type="file"
                           id="modal_bank_file"
                           name="bank_file"
                           accept=".pdf,.jpg,.jpeg,.png"
                           hidden>

                    <input type="hidden"
                           name="delete_bank_file"
                           id="delete_bank_file"
                           value="0">
                  </div>

                  <div id="bankBookList" class="file-list mt-2"></div>

                </div>

              </div>

            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button"
                  id="btnDeleteAccount"
                  class="btn btn-danger btn-sm"
                  style="display:none;">
            삭제
          </button>

          <button type="submit" class="btn btn-success btn-sm">
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
