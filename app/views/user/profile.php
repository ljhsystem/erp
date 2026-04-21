<?php
// 경로: PROJECT_ROOT . '/app/views/user/profile.php'
use Core\Helpers\AssetHelper;
// 페이지 캐싱 방지
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}
$layoutOptions = [
  'header'  => true,
  'navbar'  => true,
  'sidebar' => false,
  'footer'  => true,
  'wrapper' => 'single'
];
$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';
$pageStyles = AssetHelper::css('/assets/css/pages/auth/profile.css');
$pageScripts =
    AssetHelper::js('/assets/js/common/address.js') .
    AssetHelper::module('/assets/js/pages/auth/profile.js');
?>
<main class="container py-4" style="max-width:720px;">
  <div class="profile-card bg-white shadow-sm rounded-4 p-4">

    <!-- 로딩 -->
    <div id="profile-loading" class="text-center py-5 text-muted small">로딩 중...</div>

    <!-- 본문 -->
    <div id="profile-content" style="display:none;">

      <!-- 👤 프로필 헤더 -->
      <div class="d-flex align-items-center mb-4">
        <div class="position-relative me-3" style="width:90px;height:90px;">
          <img id="profile-image"
            src="/public/assets/img/default-avatar.png"
            class="rounded-circle border"
            style="width:100%;height:100%;object-fit:cover;">
          <button id="btn-change-image"
            class="btn btn-light btn-sm rounded-circle shadow-sm position-absolute"
            style="bottom:0; right:0; width:28px; height:28px;">
            <i class="bi bi-camera-fill text-primary"></i>
          </button>
          <input type="file" id="profile-image-input" accept="image/*" hidden>
        </div>

        <div>
          <h5 id="profile-username" class="fw-bold mb-1">-</h5>
          <div id="profile-email" class="text-muted small">-</div>
        </div>
      </div>

      <!-- 🔖 탭 -->
      <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link active" data-tab="account">계정설정</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="password">보안설정</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="notify">알림설정</button></li>
        <li class="nav-item"><button class="nav-link" data-tab="external">외부 서비스</button></li>
      </ul>

      <!-- 🧾 계정 설정 -->
      <div id="tab-account" class="tab-section">

        <!-- 이름 + 이메일 -->
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label">이름</label>
            <input id="employee_name" class="form-control form-control-sm">
          </div>
          <div class="col-6">
            <label class="form-label">이메일</label>
            <input id="email" class="form-control form-control-sm">
          </div>
        </div>

        <!-- 연락처 + 비상연락처 -->
        <div class="row g-2 mb-2">
          <div class="col-6">
            <label class="form-label">연락처</label>
            <input id="phone" class="form-control form-control-sm">
          </div>
          <div class="col-6">
            <label class="form-label">비상 연락처</label>
            <input id="emergency_phone" class="form-control form-control-sm">
          </div>
        </div>

        <!-- 주소 -->
        <div class="mb-2">
          <label class="form-label">주소</label>
          <div class="input-group input-group-sm mb-1">
            <input id="address" class="form-control" readonly>
            <button type="button"
              class="btn btn-outline-secondary"
              data-addr-picker
              data-target="#address">주소검색</button>
          </div>
          <input id="address_detail" class="form-control form-control-sm" placeholder="상세주소">
        </div>

        <!-- 대표 자격증 -->
        <div class="mt-3">
          <label class="form-label">대표 자격증</label>
          <div class="d-flex gap-3 align-items-start">
            <div class="text-center">
              <img id="profile_cert_preview"
                src="/public/assets/img/placeholder-cert.png"
                class="border rounded"
                style="width:120px;height:120px;object-fit:contain;cursor:pointer;">
              <div class="small text-muted mt-1">자격증 미리보기</div>
            </div>

            <div class="flex-fill">
              <input id="certificate_name"
                class="form-control form-control-sm mb-2"
                placeholder="예: 건축기사, 방수기능사">

              <input type="file"
                id="certificate_file"
                accept=".pdf,.jpg,.jpeg,.png"
                hidden>

              <div class="d-flex gap-2 align-items-center">
                <button type="button"
                  id="certificate_file_btn"
                  class="btn btn-outline-secondary btn-sm">
                  파일 선택
                </button>
                <span id="certificate_file_label"
                  class="small text-muted">선택된 파일 없음</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 2FA -->
        <div class="d-flex justify-content-end mt-3">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="two_factor_enabled">
            <label class="form-check-label ms-1">2단계 인증</label>
          </div>
        </div>

        <button id="btn-save-account" class="btn btn-primary btn-sm w-100 mt-3">저장</button>
      </div>

      <!-- 🔐 비밀번호 변경 -->
      <div id="tab-password" class="tab-section" style="display:none;">

        <?php
        $pwFields = [
          ['id' => 'current_password', 'label' => '현재 비밀번호', 'auto' => 'current-password'],
          ['id' => 'new_password', 'label' => '새 비밀번호', 'auto' => 'new-password'],
          ['id' => 'confirm_password', 'label' => '비밀번호 확인', 'auto' => 'new-password'],
        ];
        foreach ($pwFields as $f): ?>
          <div class="mb-2">
            <label class="form-label"><?= $f['label'] ?></label>
            <div class="input-group input-group-sm">
              <input type="password"
                id="<?= $f['id'] ?>"
                autocomplete="<?= $f['auto'] ?>"
                class="form-control">
              <button type="button"
                class="btn btn-outline-secondary password-toggle"
                data-target="<?= $f['id'] ?>">👁</button>
            </div>
          </div>
        <?php endforeach; ?>

        <button id="btn-change-password"
          class="btn btn-primary btn-sm w-100 mt-2">변경</button>
      </div>

      <!-- 🔔 알림 설정 -->
      <div id="tab-notify" class="tab-section" style="display:none;">
        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" id="email_notify">
          <label class="form-check-label">이메일 알림 수신</label>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="sms_notify">
          <label class="form-check-label">SMS 알림 수신</label>
        </div>
        <button id="btn-save-notify"
          class="btn btn-primary btn-sm w-100">저장</button>
      </div>




      <!-- 🔗 외부 서비스 연동 -->
      <div id="tab-external" class="tab-section" style="display:none;">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">External services</h6>
          <button id="btn-add-external"
            class="btn btn-sm btn-outline-primary">
            + 추가
          </button>
        </div>
        <hr />
        <div class="row">
          <!-- 🔹 왼쪽: 외부 서비스 계정 목록 -->
          <div class="col-md-4">
            <div class="list-group" id="external-account-list">
              <!-- JS 렌더링 -->
            </div>
          </div>

          <!-- 🔹 오른쪽: 선택 계정 상세 -->
          <div class="col-md-8">
            <div id="external-account-editor" class="card d-none">
              <div class="card-body" id="external-account-form">
                <!-- JS 렌더링 -->
              </div>
            </div>
          </div>
        </div>
      </div>









    </div>
  </div>
</main>



<!-- 원본 이미지 모달 -->
<div class="modal fade" id="profileImageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"
    style="width: 500px; max-width: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">원본 이미지 보기</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <img
          id="profile-image-modal-img"
          src=""
          class="img-fluid"
          style="max-height: 80vh; width: 500px; height: auto; object-fit: contain;">
      </div>
    </div>
  </div>
</div>
