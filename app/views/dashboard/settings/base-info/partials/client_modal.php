<!-- 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/partials/client_modal.php' -->
<!-- 거래처 등록/수정 모달 -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content client-modal-content">
      <form method="post" id="client-edit-form" enctype="multipart/form-data">

        <div class="modal-header">
          <h5 class="modal-title" id="clientModalLabel">거래처 등록/수정</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
        </div>

        <div class="modal-body client-modal-body">
          <input type="hidden" name="id" id="modal_client_id">

          <div class="card mb-3">
            <div class="card-header py-1 px-2">기본 정보</div>
            <div class="card-body py-2">
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">거래처명 (내부명) *</label>
                  <input type="text"
                         name="client_name"
                         id="modal_client_name"
                         class="form-control form-control-sm"
                         required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">상호 (사업자 기준)</label>
                  <input type="text"
                         name="company_name"
                         id="modal_company_name"
                         class="form-control form-control-sm">
                </div>

              </div>
              <div class="row g-2">

                <div class="col-md-4">
                  <label class="form-label">등록일자</label>
                  <div class="date-input-wrap">
                    <input type="text"
                           id="modal_registration_date"
                           name="registration_date"
                           class="form-control form-control-sm admin-date"
                           placeholder="YYYY-MM-DD"
                           autocomplete="off">
                    <span class="date-icon"><i class="bi bi-calendar3"></i></span>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">거래처등급</label>
                  <select name="client_grade"
                          id="modal_client_grade"
                          class="form-select form-select-sm">
                    <option value="">선택</option>
                    <option value="1">1등급</option>
                    <option value="2">2등급</option>
                    <option value="3">3등급</option>
                    <option value="4">4등급</option>
                    <option value="5">5등급</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">상태</label>
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

          <div class="card mb-3 d-none" id="clientCompanyHistoryCard">
            <div class="card-header py-1 px-2">상호 변경 이력</div>
            <div class="card-body py-2">
              <div id="clientCompanyHistoryList" class="small text-muted"></div>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-header py-1 px-2">사업자 정보</div>
            <div class="card-body py-2">
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label">사업자등록번호</label>
                  <div class="input-group">
                    <input type="text"
                           name="business_number"
                           id="modal_business_number"
                           data-format="biz"
                           class="form-control form-control-sm">
                    <button type="button"
                            class="btn btn-outline-primary btn-sm"
                            id="btnCheckBizStatus">
                      상태확인
                    </button>
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">법인/주민등록번호</label>
                  <div class="input-group">
                    <input type="text"
                           name="rrn"
                           id="modal_rrn"
                           data-format="corp"
                           class="form-control form-control-sm">
                    <button type="button" class="btn btn-outline-secondary btn-sm toggle-rrn">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">거래유형</label>
                  <select name="client_type"
                          id="modal_client_type"
                          class="form-select form-select-sm"
                          data-code-group="CLIENT_TYPE">
                    <option value=""></option>
                  </select>
                </div>
              </div>

              <div class="row g-2 mt-2">
                <div class="col-md-4">
                  <label class="form-label">업태</label>
                  <input type="text"
                         name="business_type"
                         id="modal_business_type"
                         class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                  <label class="form-label">업종</label>
                  <input type="text"
                         name="business_category"
                         id="modal_business_category"
                         class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                  <label class="form-label">사업자등록 상태</label>
                  <select name="business_status"
                          id="modal_business_status"
                          class="form-select form-select-sm">
                    <option value="">선택</option>
                    <option value="정상">정상</option>
                    <option value="휴업">휴업</option>
                    <option value="폐업">폐업</option>
                  </select>
                </div>
              </div>

              <div class="row g-2 mt-2">
                <div class="col-md-6">
                  <label class="form-label">사업자등록증</label>
                  <div id="dropZoneBiz"
                       class="border rounded p-3 text-center"
                       style="background:#f8f9fa; cursor:pointer;">
                    <span id="dropZoneTextBiz">
                      파일 드롭 또는 클릭<br>(PDF, JPG, PNG)
                    </span>
                    <input type="file"
                           name="business_certificate"
                           id="modal_business_certificate"
                           accept=".pdf,.jpg,.jpeg,.png"
                           style="display:none;">
                    <input type="hidden"
                           name="delete_business_certificate"
                           id="delete_business_certificate"
                           value="0">
                    <span id="certStatusIcon" class="ms-2"></span>
                  </div>
                  <div id="bizCertList" class="file-list mt-2"></div>
                  <small class="text-muted">PDF, JPG, PNG 파일만 업로드할 수 있습니다.</small>
                </div>

                <div class="col-md-6">
                  <label class="form-label">신분증</label>
                  <div id="dropZoneRrn"
                       class="border rounded p-3 text-center"
                       style="background:#f8f9fa; cursor:pointer;">
                    <span id="dropZoneTextRrn">
                      파일 드롭 또는 클릭<br>(JPG, PNG)
                    </span>
                    <input type="file"
                           name="rrn_image"
                           id="modal_rrn_image"
                           accept=".jpg,.jpeg,.png"
                           style="display:none;">
                    <input type="hidden"
                           name="delete_rrn_image"
                           id="delete_rrn_image"
                           value="0">
                    <span id="rrnStatusIcon" class="ms-2"></span>
                  </div>
                  <div id="rrnImageList" class="file-list mt-2"></div>
                  <small class="text-muted">JPG, PNG 파일만 업로드할 수 있습니다.</small>
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-header py-1 px-2">연락처 및 담당자</div>
            <div class="card-body py-2">
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label">대표자</label>
                  <input type="text" name="ceo_name" id="modal_ceo_name" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                  <label class="form-label">대표자 연락처</label>
                  <input type="text" name="ceo_phone" id="modal_ceo_phone" data-format="mobile" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                  <label class="form-label">담당자</label>
                  <input type="text" name="manager_name" id="modal_manager_name" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                  <label class="form-label">담당자 연락처</label>
                  <input type="text" name="manager_phone" id="modal_manager_phone" data-format="mobile" class="form-control form-control-sm">
                </div>
              </div>

              <div class="row g-2 mt-2">
                <div class="col-md-2">
                  <label class="form-label">전화번호</label>
                  <input type="text" name="phone" id="modal_phone" data-format="phone" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                  <label class="form-label">팩스</label>
                  <input type="text" name="fax" id="modal_fax" data-format="fax" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                  <label class="form-label">이메일</label>
                  <input type="email" name="email" id="modal_email" class="form-control form-control-sm">
                </div>
                <div class="col-md-5">
                  <label class="form-label">주소</label>
                  <div class="input-group mb-1">
                    <input type="text"
                           name="address"
                           id="modal_address"
                           class="form-control form-control-sm"
                           placeholder="기본주소">
                    <button type="button"
                            class="btn btn-outline-primary btn-sm"
                            data-addr-picker
                            data-target="#modal_address">
                      주소검색
                    </button>
                  </div>
                  <input type="text"
                         name="address_detail"
                         id="modal_address_detail"
                         class="form-control form-control-sm"
                         placeholder="상세주소">
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-header py-1 px-2">거래 및 정산 정보</div>
            <div class="card-body py-2">
              <div class="row g-2">
                <div class="col-md-8">
                  <label class="form-label">홈페이지</label>
                  <input type="text"
                         name="homepage"
                         id="modal_homepage"
                         class="form-control form-control-sm"
                         placeholder="https://">
                </div>

                <div class="col-md-4">
                  <label class="form-label">거래처분류</label>
                  <select name="client_category"
                          id="modal_client_category"
                          class="form-select form-select-sm">
                    <option value="">선택</option>
                    <option value="발주처">발주처(건축주)</option>
                    <option value="제공자">제공자/외주</option>
                    <option value="자재업체">자재업체</option>
                    <option value="시공업체">시공업체</option>
                    <option value="장비업체">장비업체</option>
                    <option value="운송업체">운송업체</option>
                    <option value="기타">기타</option>
                  </select>
                </div>
              </div>

              <div class="row g-2 mt-1">
                <div class="col-md-9">
                  <div class="row g-2">
                    <div class="col-md-4">
                      <label class="form-label">은행명</label>
                      <input type="text" name="bank_name" id="modal_bank_name" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">계좌번호</label>
                      <input type="text" name="account_number" id="modal_account_number" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">예금주</label>
                      <input type="text" name="account_holder" id="modal_account_holder" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">거래구분</label>
                      <select name="trade_category" id="modal_trade_category" class="form-select form-select-sm" data-code-group="TRADE_CATEGORY">
                        <option value=""></option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">과세구분</label>
                      <select name="tax_type" id="modal_tax_type" class="form-select form-select-sm" data-code-group="TAX_TYPE">
                        <option value=""></option>
                      </select>
                    </div>

                    <div class="col-md-4">
                      <label class="form-label">결제조건</label>
                      <select name="payment_term" id="modal_payment_term" class="form-select form-select-sm" data-code-group="PAYMENT_TERM">
                        <option value=""></option>
                      </select>
                    </div>
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">통장사본</label>
                  <div class="file-upload-box" id="bankCopyUpload">
                    <div id="bankCopyText">
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
                </div>
              </div>

              <div class="row g-2 mt-1">
                <div class="col-md-12">
                  <label class="form-label">취급품목</label>
                  <input type="text"
                         name="item_category"
                         id="modal_item_category"
                         class="form-control form-control-sm"
                         placeholder="예: 철근, 목재, 설비자재, 건축자재 등">
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-header py-1 px-2">비고 및 메모</div>
            <div class="card-body py-2">
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">비고</label>
                  <textarea name="note" id="modal_note" class="form-control form-control-sm" rows="5"></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">메모</label>
                  <textarea name="memo" id="modal_memo" class="form-control form-control-sm" rows="5"></textarea>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" id="btnDeleteClient" class="btn btn-danger btn-sm" style="display:none;">삭제</button>
          <button type="submit" id="btnSaveClient" name="client_save" class="btn btn-success btn-sm">저장</button>
          <button type="button" id="btnCloseClient" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="clientQuickModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form data-role="quick-create-form">
        <div class="modal-header">
          <h5 class="modal-title" data-role="quick-create-title">거래처 빠른 등록</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
        </div>
        <div class="modal-body" data-role="quick-create-body"></div>
        <div class="modal-footer">
          <div class="me-auto text-danger small" data-role="quick-create-message"></div>
          <button type="button" class="btn btn-outline-primary btn-sm" data-role="quick-create-detail">상세입력</button>
          <button type="submit" class="btn btn-success btn-sm" data-role="quick-create-submit">저장</button>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
        </div>
      </form>
    </div>
  </div>
</div>
