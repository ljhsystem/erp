<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/system/session.php'
?>

<div class="session-wrapper col-12 mx-auto">
    <h4 class="fw-bold mb-4 text-dark">
        <i class="bi bi-clock-history me-2"></i>세션 유효 시간 설정
    </h4>

    <form id="session-setting-form">
        <div class="row g-3 align-items-center mb-3">
            <div class="col-auto">
                <label class="form-label fw-semibold mb-0" for="session_timeout">
                    세션 유지 시간(분)
                </label>
            </div>

            <div class="col-auto">
                <div class="input-group">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-target="#session_timeout"
                            data-step="-1">-</button>

                    <input type="number"
                           min="1"
                           max="1440"
                           name="session_timeout"
                           id="session_timeout"
                           class="form-control text-center"
                           style="width:80px;">

                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-target="#session_timeout"
                            data-step="1">+</button>
                </div>
            </div>

            <div class="col-auto">
                <small class="form-text text-muted">1~1440분 사이에서 설정할 수 있습니다.</small>
            </div>
        </div>

        <div class="row g-3 align-items-center mb-3">
            <div class="col-auto">
                <label class="form-label fw-semibold mb-0" for="session_alert">
                    만료 전 알림 시간(분)
                </label>
            </div>

            <div class="col-auto">
                <div class="input-group">
                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-target="#session_alert"
                            data-step="-1">-</button>

                    <input type="number"
                           min="1"
                           max="1440"
                           name="session_alert"
                           id="session_alert"
                           class="form-control text-center"
                           style="width:80px;">

                    <button type="button"
                            class="btn btn-outline-secondary"
                            data-target="#session_alert"
                            data-step="1">+</button>
                </div>
            </div>

            <div class="col-auto">
                <small class="form-text text-muted">세션 만료 전에 미리 알림을 표시합니다.</small>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold" for="session_sound">만료 알림 사운드</label>
            <div class="input-group">
                <select name="session_sound" id="session_sound" class="form-select">
                    <option value="default.mp3">기본음</option>
                    <option value="alert1.mp3">알림음 1</option>
                    <option value="alert2.mp3">알림음 2</option>
                    <option value="alert3.mp3">알림음 3</option>
                </select>
                <button type="button" class="btn btn-outline-secondary" id="session-sound-preview-btn">미리듣기</button>
            </div>
            <small class="form-text text-muted d-block mt-2" id="session-sound-help">
                선택한 사운드를 재생해서 바로 확인할 수 있습니다.
            </small>
            <audio id="sound-preview" preload="auto"></audio>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
            저장
        </button>
    </form>
</div>
