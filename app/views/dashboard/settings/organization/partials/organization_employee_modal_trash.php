<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/organization_employee_modal_edit.php'
?>

<div class="modal fade" id="employeeEditModal" tabindex="-1" aria-labelledby="employeeEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="employee-edit-form" enctype="multipart/form-data" autocomplete="off">

        <input type="hidden" name="id" id="edit_employee_id">
        <input type="hidden" name="action" id="edit_form_action" value="save">

        <div class="modal-header">
          <h5 class="modal-title" id="employeeEditModalLabel">✎ 직원 정보 수정</h5>
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

                <div class="col-md-4">
                  <label class="form-label">코드</label>
                  <input type="text" id="edit_employee_code" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">로그인 아이디</label>
                  <input type="text"
                    name="username"
                    id="edit_employee_username"
                    class="form-control form-control-sm"
                    required>
                </div>

                <div class="col-md-4">
                  <label class="form-label">이름</label>
                  <input type="text"
                    name="employee_name"
                    id="edit_employee_name"
                    class="form-control form-control-sm"
                    required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">연락처</label>
                  <input type="text"
                    name="phone"
                    id="edit_employee_phone"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">비상연락처</label>
                  <input type="text"
                    name="emergency_phone"
                    id="edit_employee_emergency_phone"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">이메일</label>
                  <input type="email"
                    name="email"
                    id="edit_employee_email"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">주민등록번호</label>
                  <div class="input-group">
                    <input type="password"
                      name="rrn"
                      id="edit_employee_rrn"
                      class="form-control form-control-sm">
                    <button type="button"
                      class="btn btn-outline-secondary btn-sm toggle-password"
                      data-target="#edit_employee_rrn">
                      <i class="bi bi-eye-slash"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-9">
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

                <div class="col-md-3">
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
                      class="form-control form-control-sm">
                    <button type="button"
                      class="btn btn-outline-secondary btn-sm toggle-password"
                      data-target="#edit_employee_password">
                      <i class="bi bi-eye-slash"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">비밀번호 확인</label>
                  <div class="input-group">
                    <input type="password"
                      name="password_confirm"
                      id="edit_employee_password_confirm"
                      class="form-control form-control-sm">
                    <button type="button"
                      class="btn btn-outline-secondary btn-sm toggle-password"
                      data-target="#edit_employee_password_confirm">
                      <i class="bi bi-eye-slash"></i>
                    </button>
                  </div>
                </div>

              </div>
            </div>

            <!-- ==========================
                 2. 부서 / 직책 / 권한 / 거래처
            =========================== -->
            <div class="tab-pane fade" id="edit_tab_group">
              <div class="row g-3">

                <div class="col-md-3">
                  <label class="form-label">부서</label>
                  <select name="department_id" id="edit_department_select" class="form-select form-select-sm">
                    <option value="">선택</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">직책</label>
                  <select name="position_id" id="edit_position_select" class="form-select form-select-sm">
                    <option value="">선택</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">역할</label>
                  <select name="role_id" id="edit_role_select" class="form-select form-select-sm">
                    <option value="">선택</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">거래처</label>
                  <select name="client_id" id="edit_client_select" class="form-select form-select-sm">
                    <option value="">선택</option>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label">선택된 권한의 세부 권한</label>
                  <div id="edit_role_perm_box"
                    class="border bg-light rounded p-2"
                    style="height:160px; overflow:auto; font-size:13px;">
                    <div class="text-muted small">권한을 선택하면 자동 표시됩니다.</div>
                  </div>
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
                  <input type="date"
                    name="doc_hire_date"
                    id="edit_doc_hire_date"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">실제 입사일</label>
                  <input type="date"
                    name="real_hire_date"
                    id="edit_real_hire_date"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">문서상 퇴사일</label>
                  <input type="date"
                    name="doc_retire_date"
                    id="edit_doc_retire_date"
                    class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">실제 퇴사일</label>
                  <input type="date"
                    name="real_retire_date"
                    id="edit_real_retire_date"
                    class="form-control form-control-sm">
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

                  <div class="employee-file-preview-box">
                    <img src="/assets/img/default-avatar.png"
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
                    class="form-control form-control-sm"
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

                  <div class="employee-file-preview-box">
                    <img src="/assets/img/placeholder-id.png"
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
                    class="form-control form-control-sm"
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

                  <div class="employee-file-preview-box">
                    <img src="/assets/img/placeholder-cert.png"
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

                  <div class="input-group mb-1">
                    <span class="input-group-text">
                      <i class="fas fa-certificate"></i>
                    </span>
                    <input type="text"
                      name="certificate_name"
                      id="edit_certificate_name"
                      class="form-control form-control-sm"
                      placeholder="자격증 이름을 입력하세요">
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
                </div>

              </div>
            </div>

            <!-- ==========================
                 5. 알림설정
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
                 6. 노트/메모 탭
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
                 7. 기록(Log) 탭
            =========================== -->
            <div class="tab-pane fade" id="edit_tab_logs">
              <div class="row g-2">

                <div class="col-md-4">
                  <label class="form-label">가입일</label>
                  <input type="text" id="edit_created_at" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">승인상태</label>
                  <input type="text" id="edit_approved" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">승인일</label>
                  <input type="text" id="edit_approved_at" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">승인자</label>
                  <input type="text" id="edit_approved_by" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">마지막 로그인</label>
                  <input type="text" id="edit_last_login" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">로그인 IP</label>
                  <input type="text" id="edit_last_login_ip" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">로그인 디바이스</label>
                  <input type="text" id="edit_last_login_device" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">비밀번호 변경일</label>
                  <input type="text" id="edit_password_updated_at" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">비밀번호 변경자</label>
                  <input type="text" id="edit_password_updated_by" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">수정일</label>
                  <input type="text" id="edit_updated_at" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">수정자</label>
                  <input type="text" id="edit_updated_by" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">계정 상태</label>
                  <input type="text" id="edit_is_active" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">삭제일</label>
                  <input type="text" id="edit_deleted_at" class="form-control form-control-sm" readonly>
                </div>

                <div class="col-md-4">
                  <label class="form-label">삭제자</label>
                  <input type="text" id="edit_deleted_by" class="form-control form-control-sm" readonly>
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