<?php
// 경로: PROJECT_ROOT/app/views/dashboard/settings/system/external_services.php
// 설명: 시스템설정 → 외부 서비스 연동 (View 전용)
// ⚠️ DB 접근 금지 / 저장·로드는 JS(API)에서 처리
?>

<div id="external-service-wrapper" class="external-service-settings col-12 mx-auto">

    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-diagram-3 me-2"></i>외부 서비스 연동
    </h4>

    <form id="external-service-form">

        <!-- ==================================================
             Row 1 : 외부 서비스 카드
        ================================================== -->
        <div class="row g-4 mb-4">

            <!-- ==================================================
                 📅 Synology Calendar
            ================================================== -->
            <div class="col-md-6">
                <div class="card h-100">

                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-primary">
                            📅 Synology Calendar
                        </span>

                        <!-- 활성화 스위치 -->
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="synology_enabled"
                                   name="synology_enabled"
                                   value="1">
                        </div>
                    </div>

                    <div class="card-body">

                        <!-- 서버 주소 -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                서버 주소
                            </label>
                            <input type="url"
                                   class="form-control"
                                   id="synology_host"
                                   name="synology_host"
                                   placeholder="https://nas.example.com">
                            <small class="text-muted">
                                Synology NAS 주소 (관리자 설정)
                            </small>
                        </div>

                        <!-- CalDAV 경로 -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                CalDAV 경로
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="synology_caldav_path"
                                   name="synology_caldav_path"
                                   placeholder="/caldav.php/">
                        </div>

                        <!-- SSL 옵션 -->
                        <div class="form-check mb-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="synology_ssl_verify"
                                   name="synology_ssl_verify"
                                   value="1">
                            <label class="form-check-label">
                                SSL 인증서 검증
                            </label>
                        </div>

                        <div class="alert alert-secondary small mb-0">
                            👤 사용자 계정(ID / 비밀번호)은<br>
                            <strong>내 정보 → 외부 서비스 계정</strong>에서 별도로 설정합니다.
                        </div>

                    </div>
                </div>
            </div>

            <!-- ==================================================
                 🧾 홈택스 (확장 예정)
            ================================================== -->
            <div class="col-md-6">
                <div class="card h-100 opacity-50">

                    <div class="card-header fw-semibold">
                        🧾 홈택스 (준비중)
                    </div>

                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div class="text-muted text-center">
                            향후 연동 예정입니다.
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- ==================================================
             저장 버튼
        ================================================== -->
        <div class="row">
            <div class="col-12">
                <button type="button"
                        id="btn-save-external-service"
                        class="btn btn-primary w-100 py-2 fw-bold">
                    저장
                </button>
            </div>
        </div>

    </form>

</div>
