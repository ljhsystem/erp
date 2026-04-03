<?php
// 경로: PROJECT_ROOT/app/views/dashboard/settings/system/api.php
// 설명: 시스템설정 → 외부연동(API) 설정 (View 전용)
// ⚠️ DB 접근 금지 / 초기값·저장은 JS(API)에서 처리
?>

<div id="api-settings-wrapper" class="api-settings col-12 mx-auto">

    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-plug me-2"></i>외부 연동 (API)
    </h4>

    <form id="api-setting-form">

        <!-- ==================================================
             카드 영역 (Row 1)
        ================================================== -->
        <div class="row mb-4 align-items-stretch">

            <!-- =========================
                 좌측: API 기본 설정
            ========================= -->
            <div class="col-md-6 d-flex flex-column gap-4">

                <!-- API 활성화 / 인증 -->
                <div class="card">
                    <div class="card-header fw-semibold text-primary">
                        🔑 API 기본 설정
                    </div>
                    <div class="card-body">

                        <!-- API 사용 여부 -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="api_enabled"
                                   name="api_enabled"
                                   value="1">
                            <label class="form-check-label fw-semibold">
                                외부 API 사용 활성화
                            </label>
                        </div>

                        <!-- API Key -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">API Key</label>
                            <div class="input-group">
                                <input type="text"
                                       class="form-control"
                                       id="api_key"
                                       name="api_key"
                                       readonly>
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        id="api_key_regenerate">
                                    재발급
                                </button>
                            </div>
                        </div>

                        <!-- API Secret -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">API Secret</label>
                            <div class="input-group">
                                <input type="password"
                                    class="form-control"
                                    id="api_secret"
                                    name="api_secret"
                                    readonly>

                                <!-- 👁️ 보기/숨김 토글 버튼 -->
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        id="api_secret_toggle"
                                        title="표시 / 숨기기">
                                    👁️
                                </button>

                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        id="api_secret_regenerate">
                                    재발급
                                </button>
                            </div>

                            <small class="text-muted">
                                보안을 위해 기본적으로 숨김 처리됩니다.
                            </small>
                        </div>

                    </div>
                </div>

                <!-- 토큰 / 제한 -->
                <div class="card">
                    <div class="card-header fw-semibold text-primary">
                        ⏱️ 토큰 · 요청 제한
                    </div>
                    <div class="card-body">

                        <!-- Access Token TTL -->
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-0">
                                    Access Token 만료(초)
                                </label>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#api_token_ttl"
                                            data-step="-300">-</button>

                                    <input type="number"
                                           id="api_token_ttl"
                                           name="api_token_ttl"
                                           min="300" max="604800"
                                           class="form-control text-center"
                                           style="width:100px;">

                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#api_token_ttl"
                                            data-step="300">+</button>
                                </div>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">300초 ~ 7일</small>
                            </div>
                        </div>

                        <!-- Rate Limit -->
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-0">
                                    요청 제한 (분당)
                                </label>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#api_ratelimit"
                                            data-step="-10">-</button>

                                    <input type="number"
                                           id="api_ratelimit"
                                           name="api_ratelimit"
                                           min="1" max="10000"
                                           class="form-control text-center"
                                           style="width:100px;">

                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#api_ratelimit"
                                            data-step="10">+</button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- =========================
                 우측: 접근 / 연동 제한
            ========================= -->
            <div class="col-md-6 d-flex">
                <div class="card w-100">
                    <div class="card-header fw-semibold text-primary">
                        🌐 접근 제어 / 연동 정보
                    </div>
                    <div class="card-body">

                        <!-- IP 화이트리스트 -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                외부 API 호출 허용 IP 화이트리스트
                            </label>
                            <textarea name="api_ip_list"
                                      id="api_ip_list"
                                      class="form-control"
                                      rows="2"
                                      placeholder="예: 123.123.123.1, 123.123.123.2"></textarea>
                            <small class="text-muted">
                                쉼표 또는 줄바꿈으로 여러 IP 입력 가능
                            </small>
                        </div>

                        <!-- Callback URL -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Callback URL (Webhook)
                            </label>
                            <input type="url"
                                   class="form-control"
                                   id="api_callback"
                                   name="api_callback"
                                   placeholder="https://example.com/api/webhook">
                        </div>

                        <!-- ==================================================
                             외부 API 연결 테스트 (Ping)
                        ================================================== -->
                        <div class="border-top pt-3 mt-4">
                        <label class="form-label fw-semibold">
                            외부 API 연결 테스트
                        </label>

                        <p class="text-muted small mb-2">
                            현재 설정값(API Key / Secret)을 테스트용 입력칸에 복사한 뒤
                            외부 API 연결 가능 여부를 확인합니다.
                        </p>

                        <div class="mb-2">
                            <input type="text"
                                id="ping_api_key"
                                class="form-control form-control-sm"
                                placeholder="테스트용 API Key">
                        </div>

                        <div class="mb-2">
                            <input type="password"
                                id="ping_api_secret"
                                class="form-control form-control-sm"
                                placeholder="테스트용 API Secret">
                        </div>

                        <div class="d-flex align-items-center gap-3">
                        <button type="button"
                                    id="btn-copy-api-to-ping"
                                    class="btn btn-outline-secondary btn-sm">
                                🔁 설정값 복사
                            </button>

                            <button type="button"
                                    id="btn-api-ping"
                                    class="btn btn-outline-primary btn-sm">
                                🔌 핑 테스트
                            </button>

                            <span id="api-ping-result"
                                class="small text-muted">
                                테스트 전
                            </span>
                        </div>
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
                <button type="submit"
                        class="btn btn-primary w-100 py-2 fw-bold">
                    저장
                </button>
            </div>
        </div>

    </form>
</div>
