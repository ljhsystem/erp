<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/employee_modal_edit.php'
?>

<div class="modal fade" id="employeeEditModal" tabindex="-1" aria-labelledby="employeeEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="employee-edit-form" enctype="multipart/form-data" autocomplete="off">

        <input type="hidden" name="id" id="edit_employee_id">
        <input type="hidden" name="action" id="edit_form_action" value="save">

        <div class="modal-header">
          <h5 class="modal-title" id="employeeModalTitle">✎ 직원 정보 수정</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <!-- ==========================
               탭 메뉴
          =========================== -->
          <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#edit_tab_account" type="button">
                계정설정
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#edit_tab_group" type="button">
                조직/역할
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#edit_tab_dates" type="button">
                입/퇴사일
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#edit_tab_files" type="button">
                파일설정
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#edit_tab_bank" type="button">
                계좌정보
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#edit_tab_notify" type="button">
                2단계/알림
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#edit_tab_note" type="button">
                노트/메모
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#edit_tab_logs" type="button">
                기록(Log)
              </button>
            </li>
          </ul>

          <!-- ==========================
               탭 콘텐츠
          =========================== -->
          <div class="tab-content">

            <!-- ==========================
                 1. 계정설정
            =========================== -->
            <div class="tab-pane fade show active" id="edit_tab_account">
              <div class="row g-2">

                <div class="col-md-2">
                  <label class="form-label">코드</label>
                  <input type="text" id="edit_employee_code" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-5">
                  <label class="form-label">로그인 아이디</label>
                  <input type="text"
                    name="username"
                    id="edit_employee_username"
                    class="form-control form-control-sm"
                    required>
                </div>

                <div class="col-md-5">
                  <label class="form-label">이름</label>
                  <input type="text"
                    name="employee_name"
                    id="edit_employee_name"
                    class="form-control form-control-sm"
                    required>
                </div>

                <div class="col-md-3">
                  <label class="form-label">연락처</label>
                  <input type="text"
                    name="phone"
                    id="edit_employee_phone"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                  <label class="form-label">비상연락처</label>
                  <input type="text"
                    name="emergency_phone"
                    id="edit_employee_emergency_phone"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                  <label class="form-label">이메일</label>
                  <input type="email"
                    name="email"
                    id="edit_employee_email"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                  <label class="form-label">주민등록번호</label>
                  <div class="input-group">
                    <input type="password"
                      name="rrn"
                      id="edit_employee_rrn"
                      class="form-control form-control-sm">
                    <button type="button"
                      class="btn btn-outline-secondary btn-sm toggle-rrn"
                      data-target="#edit_employee_rrn">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-8">
                  <label class="form-label">주소</label>
                  <div class="input-group">
                    <input type="text"
                      name="address"
                      id="edit_employee_address"
                      class="form-control form-control-sm">

                    <button type="button"
                      class="btn btn-outline-secondary btn-sm"
                      data-addr-picker="1"
                      data-target="#edit_employee_address">
                      검색
                    </button>
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">상세주소</label>
                  <input type="text"
                    name="address_detail"
                    id="edit_employee_address_detail"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">비밀번호</label>
                  <div class="input-group">
                    <input type="password"
                      name="password"
                      id="edit_employee_password"
                      class="form-control form-control-sm"
                      placeholder="새 비밀번호 입력">
                    <button type="button"
                      class="btn btn-outline-secondary btn-sm toggle-password"
                      data-target="#edit_employee_password">
                      <i class="bi bi-eye-slash"></i>
                    </button>
                  </div>
                  <small class="form-text text-muted">
                    8자 이상, 영문/숫자/특수문자 포함 권장
                  </small>
                </div>

                <div class="col-md-6">
                  <label class="form-label">비밀번호 확인</label>
                  <div class="input-group">
                    <input type="password"
                      name="password_confirm"
                      id="edit_employee_password_confirm"
                      class="form-control form-control-sm"
                      placeholder="비밀번호 다시 입력">
                    <button type="button"
                      class="btn btn-outline-secondary btn-sm toggle-password"
                      data-target="#edit_employee_password_confirm">
                      <i class="bi bi-eye-slash"></i>
                    </button>
                  </div>
                  <small class="form-text text-muted">
                    동일한 비밀번호를 다시 입력하세요
                  </small>
                </div>

              </div>
            </div>

            <!-- ==========================
                 2. 부서 / 직책 / 권한 / 거래처
            =========================== -->
            <div class="tab-pane fade" id="edit_tab_group">
              <div class="row g-3">

                <div class="col-md-4">
                  <label class="form-label">부서</label>
                  <select name="department_id" id="edit_department_select" class="form-select form-select-sm"></select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">직책</label>
                  <select name="position_id" id="edit_position_select" class="form-select form-select-sm"></select>
                </div>

                <div class="col-md-4">
                  <label class="form-label">역할</label>
                  <select name="role_id" id="edit_role_select" class="form-select form-select-sm"></select>
                </div>

              </div>
            </div>

            <!-- ==========================
                 3. 입사/퇴사 일자 탭
            =========================== -->
            <div class="tab-pane fade" id="edit_tab_dates">
              <div class="row g-3">

                <div class="col-md-6">
                  <label class="form-label">문서상 입사일</label>
                  <div class="date-input-wrap">
                    <input type="text"
                      name="doc_hire_date"
                      id="edit_doc_hire_date"
                      class="form-control form-control-sm admin-date"
                      autocomplete="off"
                      placeholder="YYYY-MM-DD">
                    <button type="button" class="date-icon" tabindex="-1">
                      <i class="bi bi-calendar3"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">실제 입사일</label>
                  <div class="date-input-wrap">
                    <input type="text"
                      name="real_hire_date"
                      id="edit_real_hire_date"
                      class="form-control form-control-sm admin-date"
                      autocomplete="off"
                      placeholder="YYYY-MM-DD">
                    <button type="button" class="date-icon" tabindex="-1">
                      <i class="bi bi-calendar3"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">문서상 퇴사일</label>
                  <div class="date-input-wrap">
                    <input type="text"
                      name="doc_retire_date"
                      id="edit_doc_retire_date"
                      class="form-control form-control-sm admin-date"
                      autocomplete="off"
                      placeholder="YYYY-MM-DD">
                    <button type="button" class="date-icon" tabindex="-1">
                      <i class="bi bi-calendar3"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">실제 퇴사일</label>
                  <div class="date-input-wrap">
                    <input type="text"
                      name="real_retire_date"
                      id="edit_real_retire_date"
                      class="form-control form-control-sm admin-date"
                      autocomplete="off"
                      placeholder="YYYY-MM-DD">
                    <button type="button" class="date-icon" tabindex="-1">
                      <i class="bi bi-calendar3"></i>
                    </button>
                  </div>
                </div>

              </div>
            </div>

            <!-- ==========================
                 4. 파일설정 탭
            =========================== -->
            <div class="tab-pane fade" id="edit_tab_files">
              <div class="row g-3">

                <!-- 1) 프로필 사진 -->
                <div class="col-md-4 text-center">
                  <label class="form-label fw-semibold">프로필 사진</label>

                  <div class="employee-file-preview-box" id="profile_box" data-label="업로드">
                    <img src="/public/assets/img/default-avatar.png"
                      id="edit_profile_preview"
                      class="employee-file-preview-img"
                      alt="프로필 사진">

                    <button type="button"
                      id="edit_profile_delete_btn"
                      class="btn btn-light border employee-file-delete-btn"
                      style="display:none;">
                      <i class="bi bi-x"></i>
                    </button>
                  </div>

                  <input type="file"
                    id="edit_profile_image"
                    name="profile_image"
                    accept="image/*"
                    class="form-control form-control-sm employee-file-input"
                    data-preview="#edit_profile_preview">

                  <input type="hidden"
                    name="profile_image_delete"
                    id="edit_profile_image_delete"
                    value="0">

                  <div class="form-text small text-muted">가로형 미리보기 · JPG/PNG 권장</div>
                </div>

                <!-- 2) 신분증 -->
                <div class="col-md-4 text-center">
                  <label class="form-label fw-semibold">신분증</label>

                  <div class="employee-file-preview-box" id="id_box" data-label="업로드">
                    <img src="/public/assets/img/placeholder-id.png"
                      id="edit_id_preview"
                      class="employee-file-preview-img"
                      alt="신분증 미리보기">

                    <button type="button"
                      id="edit_id_delete_btn"
                      class="btn btn-light border employee-file-delete-btn"
                      style="display:none;">
                      <i class="bi bi-x"></i>
                    </button>
                  </div>

                  <input type="file"
                    id="edit_rrn_image"
                    name="rrn_image"
                    accept="image/*,application/pdf"
                    class="form-control form-control-sm employee-file-input"
                    data-preview="#edit_id_preview">

                  <input type="hidden"
                    name="rrn_image_delete"
                    id="edit_rrn_image_delete"
                    value="0">

                  <div class="form-text small text-muted">이미지 또는 PDF 업로드 가능</div>
                </div>

                <!-- 3) 자격증 -->
                <div class="col-md-4 text-center">
                  <label class="form-label fw-semibold">자격증</label>

                  <div class="employee-file-preview-box" id="cert_box" data-label="업로드">
                    <img src="/public/assets/img/placeholder-cert.png"
                      id="edit_cert_preview_img"
                      class="employee-file-preview-img"
                      alt="자격증 미리보기">

                    <button type="button"
                      id="edit_cert_delete_btn"
                      class="btn btn-light border employee-file-delete-btn"
                      style="display:none;">
                      <i class="bi bi-x"></i>
                    </button>
                  </div>

                  <input type="file"
                    name="certificate_file"
                    accept="image/*,application/pdf"
                    class="form-control form-control-sm"
                    id="edit_certificate_file">

                  <input type="hidden"
                    name="certificate_file_delete"
                    id="edit_certificate_file_delete"
                    value="0">

                  <div class="form-text small text-muted">
                    이미지, PDF, HWP, EXCEL 등 다양한 파일 가능
                  </div>

                  <div class="input-group employee-cert-name-group">
                    <span class="input-group-text">
                      <i class="fas fa-certificate"></i>
                    </span>
                    <input type="text"
                      name="certificate_name"
                      id="edit_certificate_name"
                      class="form-control form-control-sm"
                      placeholder="자격증 이름을 입력하세요">
                  </div>


                </div>

              </div>
            </div>
            <!-- ==========================
                5. 계좌정보 탭
            ========================== -->
            <div class="tab-pane fade" id="edit_tab_bank">

            <div class="row">

              <!-- 좌측: 계좌 정보 -->
              <div class="col-md-6">

                <div class="bank-left-wrap">

                  <!-- 은행명 -->
                  <div class="mb-3">
                    <label class="form-label">은행명</label>
                    <input type="text"
                      name="bank_name"
                      id="edit_bank_name"
                      class="form-control form-control-sm">
                  </div>

                  <!-- 계좌번호 -->
                  <div class="mb-3">
                    <label class="form-label">계좌번호</label>
                    <input type="text"
                      name="account_number"
                      id="edit_account_number"
                      class="form-control form-control-sm">
                  </div>

                  <!-- 예금주 -->
                  <div class="mb-3">
                    <label class="form-label">예금주</label>
                    <input type="text"
                      name="account_holder"
                      id="edit_account_holder"
                      class="form-control form-control-sm">
                  </div>

                </div>

              </div>

              <!-- 우측: 통장 파일 -->
              <div class="col-md-6">

                <div class="bank-right-wrap text-center">

                  <label class="form-label fw-semibold">통장 사본</label>

                  <!-- 미리보기 -->
                  <div class="employee-file-preview-box" id="bank_box" data-label="업로드">

                    <img src="/public/assets/img/placeholder-bank.png"
                      id="edit_bank_preview"
                      class="employee-file-preview-img"
                      alt="통장 사본 미리보기">

                    <button type="button"
                      id="edit_bank_delete_btn"
                      class="btn btn-light border employee-file-delete-btn"
                      style="display:none;">
                      <i class="bi bi-x"></i>
                    </button>

                  </div>

                  <!-- 파일 -->
                  <input type="file"
                    name="bank_file"
                    id="edit_bank_file"
                    accept="image/*,application/pdf"
                    class="form-control form-control-sm employee-file-input">

                  <input type="hidden"
                    name="bank_file_delete"
                    id="edit_bank_file_delete"
                    value="0">

                  <div class="form-text small text-muted">
                    이미지 또는 PDF 업로드 가능
                  </div>

                </div>

              </div>

            </div>

            </div>
            <!-- ==========================
                 6. 알림설정
            =========================== -->
            <div class="tab-pane fade" id="edit_tab_notify">
              <div class="row g-3">

                <div class="col-md-4">
                  <label class="form-label">2단계 인증</label>
                  <div class="form-check form-switch">
                    <input class="form-check-input"
                      id="edit_two_factor"
                      type="checkbox"
                      name="two_factor_enabled"
                      value="1">
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">이메일 알림</label>
                  <div class="form-check form-switch">
                    <input class="form-check-input"
                      id="edit_email_notify"
                      type="checkbox"
                      name="email_notify"
                      value="1">
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">SMS 알림</label>
                  <div class="form-check form-switch">
                    <input class="form-check-input"
                      id="edit_sms_notify"
                      type="checkbox"
                      name="sms_notify"
                      value="1">
                  </div>
                </div>

              </div>
            </div>

            <!-- ==========================
                 7. 노트/메모 탭
            =========================== -->
            <div class="tab-pane fade" id="edit_tab_note">
              <div class="row g-3">

                <div class="col-12">
                  <label class="form-label">노트</label>
                  <input type="text"
                    name="note"
                    id="edit_employee_note"
                    class="form-control form-control-sm">
                </div>

                <div class="col-12">
                  <label class="form-label">메모</label>
                  <textarea name="memo"
                    id="edit_employee_memo"
                    rows="4"
                    class="form-control form-control-sm"></textarea>
                </div>

              </div>
            </div>

            <!-- ==========================
                 8. 기록(Log) 탭
            =========================== -->
            <div class="tab-pane fade" id="edit_tab_logs">

              <div class="employee-log-section">

                <!-- 1. 상태 -->
                <div class="log-card">
                  <div class="log-title">계정 상태</div>

                  <div class="log-row">
                    <span>상태</span>
                    <span id="edit_is_active"></span>
                  </div>

                  <div class="log-row">
                    <span>가입일</span>
                    <span id="edit_created_at"></span>
                  </div>
                </div>

                <!-- 2. 승인 -->
                <div class="log-card">
                  <div class="log-title">승인 정보</div>

                  <div class="log-row">
                    <span>승인상태</span>
                    <span id="edit_approved"></span>
                  </div>

                  <div class="log-row">
                    <span>승인일</span>
                    <span id="edit_approved_at"></span>
                  </div>

                  <div class="log-row">
                    <span>승인자</span>
                    <span id="edit_approved_by"></span>
                  </div>
                </div>

                <!-- 3. 로그인 -->
                <div class="log-card">
                  <div class="log-title">로그인 정보</div>

                  <div class="log-row">
                    <span>마지막 로그인</span>
                    <span id="edit_last_login"></span>
                  </div>

                  <div class="log-row">
                    <span>IP</span>
                    <span id="edit_last_login_ip"></span>
                  </div>

                  <div class="log-row full">
                    <span>디바이스</span>
                    <span id="edit_last_login_device" class="device-box"></span>
                  </div>
                </div>

                <!-- 4. 변경 -->
                <div class="log-card">
                  <div class="log-title">변경 이력</div>

                  <div class="log-row">
                    <span>수정일</span>
                    <span id="edit_updated_at"></span>
                  </div>

                  <div class="log-row">
                    <span>수정자</span>
                    <span id="edit_updated_by"></span>
                  </div>

                  <div class="log-row">
                    <span>비밀번호 변경</span>
                    <span id="edit_password_updated_at"></span>
                  </div>
                </div>

                <!-- 5. 삭제 -->
                <div class="log-card danger">
                  <div class="log-title">삭제 정보</div>

                  <div class="log-row">
                    <span>삭제일</span>
                    <span id="edit_deleted_at"></span>
                  </div>

                  <div class="log-row">
                    <span>삭제자</span>
                    <span id="edit_deleted_by"></span>
                  </div>
                </div>

              </div>

            </div>

          </div><!-- tab-content -->

        </div><!-- modal-body -->

        <div class="modal-footer">
          <button type="submit" id="employeeEditSubmitBtn" class="btn btn-success btn-sm">저장</button>
          <button type="button" id="edit_soft_delete_btn" class="btn btn-warning btn-sm">계정 비활성화</button>
          <button type="button" id="edit_force_delete_btn" class="btn btn-danger btn-sm">영구 삭제</button>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
        </div>

      </form>
    </div>
  </div>
</div>



<!-- 원본 이미지 모달 -->
<div class="modal fade" id="originalImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">원본 이미지 보기</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="original-image-view"
                     src=""
                     class="img-fluid"
                     style="max-width:640px;">
            </div>
        </div>
    </div>
</div>
