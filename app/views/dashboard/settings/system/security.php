<?php
// 경로: PROJECT_ROOT/app/views/dashboard/settings/system/security.php
// 설명: 시스템설정 → 보안정책 (View 전용)
// ⚠️ DB 접근 금지 / 초기값·저장은 JS(API)에서 처리
?>

<div id="security-settings-wrapper" class="security-settings col-12 mx-auto">

    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-shield-lock me-2"></i>보안 정책
    </h4>

    <form id="security-setting-form">

        <div class="row mb-4 align-items-stretch">

            <!-- =========================
                 좌측: 인증 / 로그인 정책
            ========================= -->
            <div class="col-md-6 d-flex flex-column gap-4">

                <!-- 🔐 비밀번호 정책 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-primary">🔐 비밀번호 정책</span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="security_password_policy_enabled"
                                   name="security_password_policy_enabled"
                                   value="1">
                            <label class="form-check-label small fw-semibold">사용</label>
                        </div>
                    </div>

                    <div class="card-body policy-group" data-policy="password">

                        <!-- 최소 길이 -->
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-0">최소 길이</label>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-target="#security_password_min" data-step="-1">-</button>
                                    <input type="number"
                                           id="security_password_min"
                                           name="security_password_min"
                                           min="4" max="64"
                                           class="form-control text-center"
                                           style="width:80px;">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-target="#security_password_min" data-step="1">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- 만료 -->
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-0">비밀번호 만료(일)</label>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-target="#security_password_expire" data-step="-1">-</button>
                                    <input type="number"
                                           id="security_password_expire"
                                           name="security_password_expire"
                                           min="0" max="3650"
                                           class="form-control text-center"
                                           style="width:80px;">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-target="#security_password_expire" data-step="1">+</button>
                                </div>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">0 = 만료 없음</small>
                            </div>
                        </div>

                        <!-- 조건 -->
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="security_pw_upper"
                                   name="security_pw_upper"
                                   value="1">
                            <label class="form-check-label fw-semibold">대문자 포함 필수</label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="security_pw_number"
                                   name="security_pw_number"
                                   value="1">
                            <label class="form-check-label fw-semibold">숫자 포함 필수</label>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="security_pw_special"
                                   name="security_pw_special"
                                   value="1">
                            <label class="form-check-label fw-semibold">특수문자 포함 필수</label>
                        </div>

                    </div>
                </div>

                <!-- 🚫 로그인 실패 정책 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-primary">🚫 로그인 실패 정책</span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="security_login_fail_policy_enabled"
                                   name="security_login_fail_policy_enabled"
                                   value="1">
                            <label class="form-check-label small fw-semibold">사용</label>
                        </div>
                    </div>

                    <div class="card-body policy-group" data-policy="login-fail">

                        <!-- 실패 횟수 -->
                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-0">연속 실패 허용 횟수</label>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-target="#security_login_fail_max" data-step="-1">-</button>
                                    <input type="number"
                                           id="security_login_fail_max"
                                           name="security_login_fail_max"
                                           min="3" max="20"
                                           class="form-control text-center"
                                           style="width:80px;">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-target="#security_login_fail_max" data-step="1">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- 잠금 시간 -->
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <label class="form-label fw-semibold mb-0">잠금 시간(분)</label>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-target="#security_login_lock_minutes" data-step="-1">-</button>
                                    <input type="number"
                                           id="security_login_lock_minutes"
                                           name="security_login_lock_minutes"
                                           min="1" max="120"
                                           class="form-control text-center"
                                           style="width:80px;">
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-target="#security_login_lock_minutes" data-step="1">+</button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

           <!-- =========================
                우측: 접근 보안
            ========================= -->
            <div class="col-md-6 d-flex">
                <div class="card w-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold text-primary">🔐 접근 보안 강화</span>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input"
                                type="checkbox"
                                id="security_access_policy_enabled"
                                name="security_access_policy_enabled"
                                value="1">
                            <label class="form-check-label small fw-semibold">사용</label>
                        </div>
                    </div>

                    <div class="card-body policy-group" data-policy="access">

                        <!-- 1️⃣ 전 직원 2FA 강제 -->
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input"
                                type="checkbox"
                                id="security_force_2fa"
                                name="security_force_2fa"
                                value="1">
                            <label class="form-check-label fw-semibold">
                                전 직원 2차 인증(2FA) 강제 적용
                            </label>
                        </div>

                        <!-- 2️⃣ 신규 기기 로그인 시 추가 인증 -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input"
                                type="checkbox"
                                id="security_new_device_2fa"
                                name="security_new_device_2fa"
                                value="1">
                            <label class="form-check-label fw-semibold">
                                신규 기기 로그인 시 추가 인증
                            </label>
                        </div>

                        <hr>

                        <!-- 3️⃣ 로그인 시간대 제한 -->
                        <label class="form-label fw-semibold mb-2">
                            로그인 허용 시간대
                            <span class="text-muted small">(24시간 기준)</span>
                        </label>

                        <div class="row g-2 align-items-center mb-1">
                            <div class="col-auto">
                                <input type="time"
                                    class="form-control"
                                    id="security_login_time_start"
                                    name="security_login_time_start"
                                    step="60">
                            </div>

                            <div class="col-auto fw-semibold">~</div>

                            <div class="col-auto">
                                <input type="time"
                                    class="form-control"
                                    id="security_login_time_end"
                                    name="security_login_time_end"
                                    step="60">
                            </div>
                        </div>

                        <div class="col-12">
                            <small class="text-muted">
                                ※ 24시간 기준입니다. (예: 오후 12시는 <b>12:00</b>, 오전 12시는 <b>00:00</b>)<br>
                                설정 시간 외 로그인 시 <b>추가 인증</b> 또는 <b>차단</b>됩니다.
                            </small>
                        </div>


                        <hr>

                        <!-- 4️⃣ 장기 미사용 계정 보호 -->
                        <label class="form-label fw-semibold mb-2">
                            장기 미사용 계정 보호
                        </label>

                        <!-- 미접속 → 추가 인증 -->
                        <div class="row g-3 align-items-center mb-2">
                            <div class="col-auto">
                                <label class="form-label mb-0 fw-semibold">미접속</label>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#security_inactive_warn_days"
                                            data-step="-1">-</button>

                                    <input type="number"
                                        class="form-control text-center"
                                        id="security_inactive_warn_days"
                                        name="security_inactive_warn_days"
                                        min="1" max="365"
                                        style="width:80px;">

                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#security_inactive_warn_days"
                                            data-step="1">+</button>
                                </div>
                            </div>
                            <div class="col-auto">
                                <span class="fw-semibold">일 → 추가 인증</span>
                            </div>
                        </div>

                        <!-- 미접속 → 계정 잠금 -->
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <label class="form-label mb-0 fw-semibold">미접속</label>
                            </div>
                            <div class="col-auto">
                                <div class="input-group">
                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#security_inactive_lock_days"
                                            data-step="-1">-</button>

                                    <input type="number"
                                        class="form-control text-center"
                                        id="security_inactive_lock_days"
                                        name="security_inactive_lock_days"
                                        min="1" max="3650"
                                        style="width:80px;">

                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#security_inactive_lock_days"
                                            data-step="1">+</button>
                                </div>
                            </div>
                            <div class="col-auto">
                                <span class="fw-semibold">일 → 계정 잠금</span>
                            </div>
                        </div>


                    </div>
                </div>
            </div>


        </div>

        <!-- 저장 버튼 -->
        <div class="row">
            <div class="col-12">
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                    저장
                </button>
            </div>
        </div>

    </form>
</div>
