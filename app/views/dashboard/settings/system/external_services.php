<?php
// Path: PROJECT_ROOT/app/views/dashboard/settings/system/external_services.php
?>

<div id="external-service-wrapper" class="external-service-settings col-12 mx-auto">
    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-diagram-3 me-2"></i>외부 서비스 연동
    </h4>

    <form id="external-service-form">
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-primary">
                            Synology Calendar
                        </span>

                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="synology_enabled"
                                   name="synology_enabled"
                                   value="1">
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="synology_host">
                                서버 주소
                            </label>
                            <input type="url"
                                   class="form-control"
                                   id="synology_host"
                                   name="synology_host"
                                   placeholder="https://nas.example.com">
                            <small class="text-muted">
                                Synology NAS 기본 주소를 입력합니다.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="synology_caldav_path">
                                CalDAV 경로
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="synology_caldav_path"
                                   name="synology_caldav_path"
                                   placeholder="/caldav.php/">
                            <small class="text-muted">
                                예: `/caldav.php/`
                            </small>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="synology_ssl_verify"
                                   name="synology_ssl_verify"
                                   value="1">
                            <label class="form-check-label" for="synology_ssl_verify">
                                SSL 인증서 검증 사용
                            </label>
                        </div>

                        <div class="alert alert-secondary small mb-0">
                            사용자별 Synology 로그인 계정(ID / 비밀번호)은 별도 화면에서 관리되며,
                            이 페이지는 공통 연결 경로만 설정합니다.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100 opacity-50">
                    <div class="card-header fw-semibold">
                        추가 연동(준비중)
                    </div>

                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div class="text-muted text-center">
                            이후 연동 항목이 추가될 예정입니다.
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
