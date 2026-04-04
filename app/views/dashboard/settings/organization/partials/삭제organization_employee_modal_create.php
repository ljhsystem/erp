<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/organization_employee_modal_create.php'
?>

<!-- 동일한 미리보기 스타일 사용 (수정모달과 통일) -->
<style>
  .employee-file-preview-box {
    width: 100%;
    max-width: 280px;
    height: 150px;
    margin: 0 auto 0.5rem;
    border-radius: 8px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
  }

  .employee-file-preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .employee-file-delete-btn {
    position: absolute;
    top: 6px;
    right: 6px;
    z-index: 5;
    padding: 0.15rem 0.4rem;
    font-size: 0.75rem;
  }
</style>

<div class="modal fade" id="employeeCreateModal" tabindex="-1" aria-labelledby="employeeCreateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form id="employee-create-form" enctype="multipart/form-data" autocomplete="off">

        <input type="hidden" name="action" value="create">

        <div class="modal-header">
          <h5 class="modal-title">+ 새 직원 등록</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <!-- ========================== 탭 메뉴 =========================== -->
          <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#create_tab_account">계정설정</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#create_tab_group">조직/역할</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#create_tab_dates">입/퇴사일</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#create_tab_files">파일설정</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#create_tab_notify">2단계/알림</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#create_tab_note">노트/메모</button>
            </li>
          </ul>

          <!-- ========================== 탭 콘텐츠 =========================== -->
          <div class="tab-content">

            <!-- ========================== 1. 계정 설정 =========================== -->
            <div class="tab-pane fade show active" id="create_tab_account">
              <div class="row g-2">

                <div class="col-md-6">
                  <label class="form-label">로그인 아이디</label>
                  <input type="text" name="username" class="form-control form-control-sm" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">이름</label>
                  <input type="text" name="employee_name" class="form-control form-control-sm" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label">연락처</label>
                  <input type="text" name="phone" class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">비상연락처</label>
                  <input type="text" name="emergency_phone" class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">이메일</label>
                  <input type="email" name="email" class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">주민등록번호</label>
                  <div class="input-group">
                    <input type="password" name="rrn" id="create_rrn" class="form-control form-control-sm">
                    <button type="button" class="btn btn-outline-secondary btn-sm toggle-password" data-target="#create_rrn">
                      <i class="bi bi-eye-slash"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-9">
                  <label class="form-label">주소</label>
                  <div class="input-group">
                    <input type="text" name="address" id="create_address" class="form-control form-control-sm">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-addr-picker="1" data-target="#create_address">검색</button>
                  </div>
                </div>

                <div class="col-md-3">
                  <label class="form-label">상세주소</label>
                  <input type="text" name="address_detail" class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">비밀번호</label>
                  <div class="input-group">
                    <input type="password" name="password" id="create_password" class="form-control form-control-sm" required>
                    <button type="button" class="btn btn-outline-secondary btn-sm toggle-password" data-target="#create_password">
                      <i class="bi bi-eye-slash"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">비밀번호 확인</label>
                  <div class="input-group">
                    <input type="password" name="password_confirm" id="create_password_confirm" class="form-control form-control-sm" required>
                    <button type="button" class="btn btn-outline-secondary btn-sm toggle-password" data-target="#create_password_confirm">
                      <i class="bi bi-eye-slash"></i>
                    </button>
                  </div>
                </div>

              </div>
            </div>

            <!-- ========================== 2. 조직/역할 =========================== -->
            <div class="tab-pane fade" id="create_tab_group">
              <div class="row g-3">

                <div class="col-md-3">
                  <label class="form-label">부서</label>
                  <select name="department_id" id="create_department_select" class="form-select form-select-sm">
                    <option value="">선택</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">직책</label>
                  <select name="position_id" id="create_position_select" class="form-select form-select-sm">
                    <option value="">선택</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">역할</label>
                  <select name="role_id" id="create_role_select" class="form-select form-select-sm">
                    <option value="">선택</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">거래처</label>
                  <select name="client_id" id="create_client_select" class="form-select form-select-sm">
                    <option value="">선택</option>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label">선택된 권한의 세부 권한</label>
                  <div id="create_role_perm_box" class="border bg-light rounded p-2" style="height:160px; overflow:auto; font-size:13px;">
                    <div class="text-muted small">권한을 선택하면 자동 표시됩니다.</div>
                  </div>
                </div>

              </div>
            </div>

            <!-- ========================== 3. 입/퇴사일 =========================== -->
            <div class="tab-pane fade" id="create_tab_dates">
              <div class="row g-3">

                <div class="col-md-6">
                  <label class="form-label">문서상 입사일</label>
                  <input type="date" name="doc_hire_date" class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">실제 입사일</label>
                  <input type="date" name="real_hire_date" class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">문서상 퇴사일</label>
                  <input type="date" name="doc_retire_date" class="form-control form-control-sm">
                </div>

                <div class="col-md-6">
                  <label class="form-label">실제 퇴사일</label>
                  <input type="date" name="real_retire_date" class="form-control form-control-sm">
                </div>

              </div>
            </div>

            <!-- ========================== 4. 파일 설정 =========================== -->
            <div class="tab-pane fade" id="create_tab_files">
              <div class="row g-3">

                <!-- ⭐ 프로필 -->
                <div class="col-md-4 text-center">
                  <label class="form-label fw-semibold">프로필 사진</label>

                  <div class="employee-file-preview-box">
                    <img src="/assets/img/default-avatar.png" id="create_profile_preview" class="employee-file-preview-img">
                  </div>

                  <input type="file" name="profile_image" accept="image/*" class="form-control form-control-sm" data-preview="#create_profile_preview">

                  <div class="form-text small text-muted">가로형 미리보기 · JPG/PNG 권장</div>
                </div>

                <!-- ⭐ 신분증 -->
                <div class="col-md-4 text-center">
                  <label class="form-label fw-semibold">신분증</label>

                  <div class="employee-file-preview-box">
                    <img src="/assets/img/placeholder-id.png" id="create_id_preview" class="employee-file-preview-img">
                  </div>

                  <input type="file" name="rrn_image" accept="image/*,application/pdf" class="form-control form-control-sm" data-preview="#create_id_preview">

                  <div class="form-text small text-muted">이미지 및 PDF 지원</div>
                </div>

                <!-- ⭐ 자격증 -->
                <div class="col-md-4 text-center">
                  <label class="form-label fw-semibold">자격증</label>

                  <div class="employee-file-preview-box">
                    <img src="/assets/img/placeholder-cert.png" id="create_cert_preview" class="employee-file-preview-img">
                  </div>

                  <div class="input-group mb-1">
                    <span class="input-group-text"><i class="fas fa-certificate"></i></span>
                    <input type="text" name="certificate_name" class="form-control form-control-sm" placeholder="자격증명 입력">
                  </div>

                  <input type="file" name="certificate_file" accept="image/*,application/pdf" class="form-control form-control-sm">

                  <div class="form-text small text-muted">이미지/PDF/HWP/EXCEL 지원</div>
                </div>

              </div>
            </div>

            <!-- ========================== 5. 알림 설정 =========================== -->
            <div class="tab-pane fade" id="create_tab_notify">
              <div class="row g-3">

                <div class="col-md-4">
                  <label class="form-label">2단계 인증</label>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="two_factor_enabled" value="1" checked>
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">이메일 알림</label>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="email_notify" value="1" checked>
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">SMS 알림</label>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="sms_notify" value="1" checked>
                  </div>
                </div>

              </div>
            </div>

            <!-- ========================== 6. 노트/메모 =========================== -->
            <div class="tab-pane fade" id="create_tab_note">
              <div class="row g-3">

                <div class="col-12">
                  <label class="form-label">노트</label>
                  <input type="text" name="note" class="form-control form-control-sm">
                </div>

                <div class="col-12">
                  <label class="form-label">메모</label>
                  <textarea name="memo" rows="4" class="form-control form-control-sm"></textarea>
                </div>

              </div>
            </div>

          </div><!-- tab-content -->

        </div><!-- modal-body -->

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary btn-sm">등록</button>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
        </div>

      </form>
    </div>
  </div>
</div>