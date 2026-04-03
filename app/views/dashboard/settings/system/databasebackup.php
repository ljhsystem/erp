<?php
// 경로: PROJECT_ROOT/app/views/dashboard/settings/system/databasebackup.php
// 설명: 시스템설정 → 데이터베이스백업 (View 전용)
?>

<div id="backup-settings-wrapper" class="backup-settings col-12 mx-auto">

    <h4 class="fw-bold mb-3 text-dark">
        <i class="bi bi-hdd-network me-2"></i>데이터베이스 관리
    </h4>

    <form id="backup-setting-form">

        <!-- ==================================================
             1. 수동 백업 / 자동 백업
        ================================================== -->
        <div class="row g-3 mb-4">

            <!-- 🔹 수동 백업 -->
            <div class="col-lg-6 col-md-12">
                <div class="card h-100">
                    <div class="card-header fw-semibold text-primary">
                        💾 수동 백업
                    </div>
                    <div class="card-body">

                        <div class="mb-2">
                            <label class="fw-semibold d-block mb-1">
                                백업 저장 경로
                            </label>
                            <div class="p-2 bg-light rounded border small">
                                <code id="backup-directory">로딩 중...</code>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="fw-semibold d-block mb-1">
                                최신 백업
                            </label>
                            <div id="latest-backup-info"
                                class="p-2 bg-light rounded border small"
                                style="min-height:100px;">
                                확인 중...
                            </div>
                        </div>


                        

                        <button type="button"
                                id="run-backup-now"
                                class="btn btn-primary fw-bold w-100">
                            <i class="bi bi-cloud-arrow-down me-1"></i>
                            지금 백업 실행
                        </button>

                        <div class="form-text text-muted small mt-2">
                            ⚠️ <b>수동 백업 실행</b> 시 생성된 <br>최신 백업 파일을
                            <b>저장경로</b>에 수동으로 <b>저장</b>합니다.
                            </div>


                        <div id="backup-run-result" class="mt-2"></div>
                    </div>
                </div>
            </div>





                    <!-- 🔹 자동 백업 -->
            <div class="col-lg-6 col-md-12">
                <div class="card h-100">
                    <div class="card-header fw-semibold text-primary">
                        ⏱ 자동 백업
                    </div>

                    <div class="card-body">

                        <!-- A️⃣ 자동 백업 활성화 -->
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                    type="checkbox"
                                    id="backup_auto_enabled"
                                    name="backup_auto_enabled"
                                    value="1">
                                <label class="form-check-label fw-semibold">
                                    자동 백업 사용
                                </label>
                            </div>
                        </div>

                        <!-- B️⃣ 핵심 실행 설정 -->
                        <div class="row align-items-end g-3 mb-4">

                            <!-- 실행 주기 -->
                            <div class="col-auto">
                                <label class="form-label fw-semibold">
                                    실행 주기
                                </label>
                                <select name="backup_schedule"
                                        id="backup_schedule"
                                        class="form-select"
                                        style="min-width:160px;">
                                    <option value="daily">매일</option>
                                    <option value="weekly">매주</option>
                                    <option value="monthly">매월</option>
                                </select>
                            </div>

                            <!-- 백업 보관 기간 -->
                            <div class="col-auto">
                                <label class="form-label fw-semibold">
                                    백업 보관 기간
                                </label>
                                <div class="input-group">
                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#backup_retention_days"
                                            data-step="-1">-</button>

                                    <input type="number"
                                        min="1" max="365"
                                        id="backup_retention_days"
                                        name="backup_retention_days"
                                        class="form-control text-center"
                                        style="width:70px;">

                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-target="#backup_retention_days"
                                            data-step="1">+</button>

                                    <span class="input-group-text">일</span>
                                </div>
                            </div>

                        </div>

                        <!-- C️⃣ 부가 옵션 -->
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                    type="checkbox"
                                    id="backup_cleanup_enabled"
                                    name="backup_cleanup_enabled"
                                    value="1">
                                <label class="form-check-label fw-semibold">
                                    오래된 백업 자동 정리
                                </label>
                            </div>
                        </div>

                        <!-- D️⃣ Secondary DB 자동 복원 (종속 기능) -->
                        <div class="border rounded p-3 bg-light-subtle">

                            <div class="d-flex flex-wrap align-items-start gap-3">

                                <!-- 스위치 -->
                                <div class="form-check form-switch mt-1">
                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="backup_restore_secondary_enabled"
                                        name="backup_restore_secondary_enabled"
                                        value="1">
                                    <label class="form-check-label fw-semibold">
                                        Secondary DB 자동 복원
                                    </label>
                                </div>

                                <!-- 상태 박스 -->
                                <div class="flex-grow-1" style="min-width:240px;">
                                    <label class="fw-semibold d-block mb-1">
                                        최신 복원
                                    </label>
                                    <div id="latest-secondary-restore-info"
                                        class="p-2 bg-light rounded border small">
                                        복원 기록 없음
                                    </div>
                                </div>
                            </div>
                            <button type="button"
                                    id="run-secondary-restore"
                                    class="btn btn-outline-primary btn-sm mt-2">
                                최신 백업으로 Secondary DB 복원
                            </button>
                            <div class="form-text text-muted small mt-2">
                            ⚠️ <b>자동 백업 실행</b> 시 생성된 최신 백업 파일을
                            <b>Secondary DB</b>에 자동으로 <b>복원</b>합니다.
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ==================================================
            2. 이중화 상태 / 백업 로그 (좌우 배치)
        ================================================== -->
        <div class="row g-3 mb-4">

            <!-- 🔁 DB 이중화 상태 -->
            <div class="col-lg-6 col-md-12">
                <div class="card h-100">
                    <div class="card-header fw-semibold text-primary">
                        🔁 데이터베이스 이중화 상태
                    </div>
                    <div class="card-body">

                        <table class="table table-sm align-middle mb-3">
                            <tr>
                                <th style="width:150px;">Primary DB</th>
                                <td>
                                    <span id="primary-status">-</span>
                                    <span id="primary-badge"
                                        class="badge bg-secondary ms-2">
                                        UNKNOWN
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <th>Secondary DB</th>
                                <td>
                                    <span id="secondary-status">-</span>
                                    <span id="secondary-badge"
                                        class="badge bg-secondary ms-2">
                                        UNKNOWN
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <th>동기화 상태</th>
                                <td>
                                    <span id="replication-sync"
                                        class="badge bg-secondary">
                                        -
                                    </span>
                                </td>
                            </tr>

                            <tr>
                                <th>Replication Lag</th>
                                <td id="replication-lag">-</td>
                            </tr>

                            <tr>
                                <th>마지막 확인</th>
                                <td id="replication-checked-at">-</td>
                            </tr>
                        </table>

                        <div class="alert alert-light small mb-3">
                            ⚠️ 본 기능은 <b>백업과 별도</b>이며<br>
                            장애 발생 여부 및 Failover 판단을 위한
                            <b>상태 모니터링 UI</b>입니다.
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    onclick="loadReplicationStatus()">
                                상태 새로고침
                            </button>

                            <button type="button"
                                    class="btn btn-outline-danger btn-sm"
                                    disabled
                                    title="수동 승격은 향후 지원 예정">
                                Secondary → Primary 승격
                            </button>
                        </div>

                    </div>
                </div>
            </div>


            <!-- 📄 백업 로그 -->
            <div class="col-lg-6 col-md-12">
                <div class="card h-100">
                    <div class="card-header fw-semibold text-primary">
                        📄 백업 로그
                    </div>
                    <div class="card-body d-flex flex-column">

                        <pre id="backup-log-viewer"
                             class="bg-light border rounded p-2 small flex-grow-1"
                             style="overflow:auto;">
로그 불러오는 중...
                        </pre>

                        <button type="button"
                                class="btn btn-outline-secondary btn-sm mt-2 align-self-start"
                                id="reload-backup-log">
                            로그 새로고침
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- 저장 -->
        <button type="submit"
                class="btn btn-primary w-100 py-2 fw-bold">
            <i class="bi bi-save me-1"></i>
            설정 저장
        </button>

    </form>
</div>
