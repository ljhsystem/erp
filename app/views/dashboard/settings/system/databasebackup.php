<?php
?>

<div id="backup-settings-wrapper" class="backup-settings col-12 mx-auto">
    <h4 class="fw-bold mb-3 text-dark">
        <i class="bi bi-hdd-network me-2"></i>데이터 백업 관리
    </h4>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="fw-semibold text-dark mb-1">백업</div>
                    <div class="text-muted small">
                        Primary DB를 로컬 백업 저장소에 SQL 파일로 저장합니다.
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="fw-semibold text-dark mb-1">복원</div>
                    <div class="text-muted small">
                        Secondary DB 복원은 별도 작업입니다. 수동 백업만으로 자동 반영되지는 않습니다.
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="fw-semibold text-dark mb-1">복제 상태</div>
                    <div class="text-muted small">
                        Primary/Secondary 연결 상태와 Replication 구성 여부를 점검하는 모니터링 영역입니다.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="backup-setting-form">
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold text-primary">
                        수동 백업
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="fw-semibold d-block mb-1">백업 저장소</label>
                            <div class="p-2 bg-light rounded border small">
                                <code id="backup-directory">확인 중...</code>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="fw-semibold d-block mb-1">최신 백업 파일</label>
                            <div id="latest-backup-info" class="p-2 bg-light rounded border small" style="min-height:100px;">
                                확인 중...
                            </div>
                        </div>

                        <button type="button" id="run-backup-now" class="btn btn-primary fw-bold w-100">
                            <i class="bi bi-cloud-arrow-down me-1"></i>지금 백업 실행
                        </button>

                        <div class="alert alert-light small mt-3 mb-0">
                            수동 백업은 <b>Primary DB 백업만 수행</b>합니다.<br>
                            백업 파일을 다른 저장소로 옮기거나 Secondary DB에 반영하는 작업은 별도로 진행해야 합니다.
                        </div>

                        <div id="backup-run-result" class="mt-3"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between fw-semibold text-primary">
                        <span>자동 백업 설정</span>
                        <span class="badge bg-secondary">사전 설정</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning small mb-4">
                            현재 이 영역은 <b>자동 실행 조건을 저장하는 사전 설정</b>입니다.<br>
                            실제 자동 백업/자동 복원은 시놀로지 작업 스케줄러 또는 외부 cron에
                            <code>/usr/local/bin/php /volume1/web/erp/cli/backup_runner.php</code>
                            가 등록되어 있어야 동작합니다.
                        </div>

                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="backup_auto_enabled" name="backup_auto_enabled" value="1">
                                <label class="form-check-label fw-semibold" for="backup_auto_enabled">
                                    자동 백업 사용
                                </label>
                            </div>
                            <div class="form-text">
                                스케줄러가 주기적으로 CLI 러너를 실행할 때, 이 설정값을 기준으로 실제 백업 실행 여부를 판단합니다.
                            </div>
                        </div>

                        <div class="row align-items-end g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="backup_schedule">실행 주기</label>
                                <select name="backup_schedule" id="backup_schedule" class="form-select">
                                    <option value="daily">매일</option>
                                    <option value="weekly">매주</option>
                                    <option value="monthly">매월</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="backup_time">실행 시간</label>
                                <input type="time" name="backup_time" id="backup_time" class="form-control" value="02:00">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="backup_retention_days">백업 보관 기간</label>
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary" data-target="#backup_retention_days" data-step="-1">-</button>
                                    <input type="number" min="1" max="365" id="backup_retention_days" name="backup_retention_days" class="form-control text-center">
                                    <button type="button" class="btn btn-outline-secondary" data-target="#backup_retention_days" data-step="1">+</button>
                                    <span class="input-group-text">일</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="backup_cleanup_enabled" name="backup_cleanup_enabled" value="1">
                                <label class="form-check-label fw-semibold" for="backup_cleanup_enabled">
                                    오래된 백업 자동 정리
                                </label>
                            </div>
                        </div>

                        <div class="border rounded p-3 bg-light-subtle">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="backup_restore_secondary_enabled" name="backup_restore_secondary_enabled" value="1">
                                <label class="form-check-label fw-semibold" for="backup_restore_secondary_enabled">
                                    자동 백업 후 Secondary DB 복원
                                </label>
                            </div>
                            <div class="form-text mb-3">
                                이 옵션은 <b>CLI 자동 백업이 실제 실행될 때만</b> 적용됩니다. 스케줄러 미등록 상태에서는 저장만 됩니다.
                            </div>

                            <label class="fw-semibold d-block mb-1">복원 상태</label>
                            <div id="latest-secondary-restore-info" class="p-2 bg-light rounded border small">
                                복원 이력 없음
                            </div>

                            <button type="button" id="run-secondary-restore" class="btn btn-outline-primary btn-sm mt-3">
                                최신 백업으로 Secondary DB 복원
                            </button>

                            <div class="alert alert-warning small mt-3 mb-0">
                                이 작업은 <b>Secondary DB 데이터를 교체</b>합니다.<br>
                                복원 실패 시 롤백을 시도하지만, Secondary DB는 언제든 초기화 가능한 테스트/복제 환경이어야 합니다.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold text-primary">
                        DB 복제 상태
                    </div>
                    <div class="card-body">
                        <table class="table table-sm align-middle mb-3">
                            <tr>
                                <th style="width:150px;">Primary DB</th>
                                <td>
                                    <span id="primary-status">-</span>
                                    <span id="primary-badge" class="badge bg-secondary ms-2">UNKNOWN</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Secondary DB</th>
                                <td>
                                    <span id="secondary-status">-</span>
                                    <span id="secondary-badge" class="badge bg-secondary ms-2">UNKNOWN</span>
                                </td>
                            </tr>
                            <tr>
                                <th>동기화 상태</th>
                                <td><span id="replication-sync" class="badge bg-secondary">-</span></td>
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
                            이 영역은 백업 기능과 별개로, Primary/Secondary DB 연결 상태와 Replication 구성 여부를 보여주는 모니터링 영역입니다.
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="reload-replication-status">
                                상태 새로고침
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" disabled title="향후 수동 승격 기능 추가 예정">
                                Secondary → Primary 승격
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold text-primary">
                        백업 로그
                    </div>
                    <div class="card-body d-flex flex-column">
                        <pre id="backup-log-viewer" class="bg-light border rounded p-2 small flex-grow-1" style="overflow:auto;">로그를 불러오는 중...</pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2 align-self-start" id="reload-backup-log">
                            로그 새로고침
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" id="save-backup-settings" class="btn btn-primary w-100 py-2 fw-bold">
            <i class="bi bi-save me-1"></i>설정 저장
        </button>
    </form>
</div>

<div class="modal fade" id="restoreWarningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Secondary DB 복원</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                최신 백업 파일로 Secondary DB를 복원합니다.<br><br>
                이 작업은 기존 Secondary DB 데이터를 교체하며, 실패 시 롤백을 시도합니다.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-danger" id="confirm-secondary-restore">복원 실행</button>
            </div>
        </div>
    </div>
</div>
