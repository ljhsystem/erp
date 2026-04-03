<!-- 경로: PROJECT_ROOT . '/app/views/ledger/account/_modal_account_edit.php' -->

<div class="modal fade"
     id="accountModal"
     tabindex="-1"
     aria-labelledby="accountModalLabel"
     aria-hidden="true">

  <div class="modal-dialog modal-lg">

    <div class="modal-content account-modal-content">

      <form id="account-edit-form" method="post">

        <div class="modal-header">

          <h5 class="modal-title"
              id="accountModalLabel">
              계정과목 등록 / 수정
          </h5>

          <button type="button"
                  class="btn-close"
                  data-bs-dismiss="modal"></button>

        </div>


        <div class="modal-body">

        <input type="hidden"
               name="id"
               id="modal_account_id">

        <!-- 기본정보 -->
        <div class="card mb-3">

          <div class="card-header py-1 px-2">
          기본 정보
          </div>

          <div class="card-body py-2">

            <div class="row g-2">

              <div class="col-md-1">
                    <label class="form-label">순번</label>
                    <input type="text" name="code" id="modal_code"
                          class="form-control form-control-sm"
                          placeholder="비워두면 자동생성" readonly>
                  </div>


              <div class="col-md-3">

                <label class="form-label">
                계정코드
                </label>

                <input type="text"
                       name="account_code"
                       id="modal_account_code"
                       class="form-control form-control-sm">

              </div>


              <div class="col-md-4">

                <label class="form-label">
                계정과목명
                </label>

                <input type="text"
                       name="account_name"
                       id="modal_account_name"
                       class="form-control form-control-sm"
                       required>

              </div>


              <div class="col-md-4">

                <label class="form-label">보조계정 허용</label>

                <select name="allow_sub_account"
                        id="modal_allow_sub_account"
                        class="form-select form-select-sm">

                  <option value="1">허용</option>
                  <option value="0">미사용</option>

                </select>

                </div>

            </div>

          </div>

        </div>


        <!-- 계정 분류 -->
        <div class="card mb-3">

          <div class="card-header py-1 px-2">
          계정 분류
          </div>

          <div class="card-body py-2">

            <div class="row g-2">

            <div class="col-md-4">

              <label class="form-label">상위계정</label>

              <div class="input-group input-group-sm">

                <input type="hidden" name="parent_id" id="modal_parent_id">

                <input type="text"
                      id="modal_parent_name"
                      class="form-control"
                      placeholder="상위계정 선택"
                      readonly>

                <button type="button"
                        class="btn btn-outline-secondary"
                        id="btnSelectParent">
                  선택
                </button>

                <!-- 🔥 추가 -->
                <button type="button"
                        class="btn btn-outline-danger"
                        id="btnClearParent">
                  ✕
                </button>

                </div>

            </div>


              <div class="col-md-4">

                <label class="form-label">
                계정구분
                </label>

                <select name="account_group"
                        id="modal_account_group"
                        class="form-select form-select-sm"
                        required>

                  <option value="">선택</option>
                  <option value="자산">자산</option>
                  <option value="부채">부채</option>
                  <option value="자본">자본</option>
                  <option value="수익">수익</option>
                  <option value="비용">비용</option>

                </select>

              </div>

            </div>

          </div>

        </div>


        <!-- 설정 -->
        <div class="card mb-3">

          <div class="card-header py-1 px-2">
          계정 설정
          </div>

          <div class="card-body py-2">

            <div class="row g-2">

              <div class="col-md-3">

                <label class="form-label">
                전표입력 가능
                </label>

                <select name="is_posting"
                        id="modal_is_posting"
                        class="form-select form-select-sm">

                  <option value="1">가능</option>
                  <option value="0">불가</option>

                </select>

              </div>


              <div class="col-md-3">

                <label class="form-label">
                사용여부
                </label>

                <select name="is_active"
                        id="modal_is_active"
                        class="form-select form-select-sm">

                  <option value="1">사용</option>
                  <option value="0">미사용</option>

                </select>

              </div>

            </div>

          </div>

        </div>


        <!-- 비고 -->
        <div class="card mb-3">

          <div class="card-header py-1 px-2">
          비고 / 메모
          </div>

          <div class="card-body py-2">

            <div class="row g-2">

            <div class="col-md-6">

              <label class="form-label">
              비고
              </label>

              <textarea name="note"
                        id="modal_note"
                        class="form-control form-control-sm"
                        rows="5"></textarea>

            </div>


              <div class="col-md-6">

                <label class="form-label">
                메모
                </label>

                <textarea name="memo"
                          id="modal_memo"
                          class="form-control form-control-sm"
                          rows="5"></textarea>

              </div>

            </div>

          </div>

        </div>


        </div>


        <div class="modal-footer">

          <button type="button"
                  id="btnDeleteAccount"
                  class="btn btn-danger btn-sm">
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