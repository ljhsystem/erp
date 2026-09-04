<?php
?>

<div id="backup-settings-wrapper" class="backup-settings col-12 mx-auto">
    <h4 class="fw-bold mb-3 text-dark">
        <i class="bi bi-hdd-network me-2"></i>데이터베이스 백업 관리
    </h4>

    <form id="backup-setting-form">
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card border-primary-subtle">
                    <div class="card-header fw-semibold text-primary">현재 DB 상태</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded bg-light p-3 h-100">
                                    <div class="text-muted small mb-1">현재 Active DB</div>
                                    <div id="status-active-db" class="fw-bold fs-5">확인 중...</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded bg-light p-3 h-100">
                                    <div class="text-muted small mb-1">PRIMARY (3306)</div>
                                    <div id="status-primary-db" class="fw-semibold">확인 중...</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded bg-light p-3 h-100">
                                    <div class="text-muted small mb-1">SECONDARY (3307)</div>
                                    <div id="status-secondary-db" class="fw-semibold">확인 중...</div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <div class="border rounded bg-light p-3 h-100">
                                    <div class="text-muted small mb-1">마지막 확인 시각</div>
                                    <div id="status-checked-at" class="fw-semibold">확인 중...</div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6">
                                <div class="border rounded bg-light p-3 h-100">
                                    <div class="text-muted small mb-1">마지막 전환 시각</div>
                                    <div id="status-switched-at" class="fw-semibold">-</div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6">
                                <div class="border rounded bg-light p-3 h-100">
                                    <div class="text-muted small mb-1">마지막 전환 사용자</div>
                                    <div id="status-switched-by" class="fw-semibold">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="reload-db-status">
                                상태 새로고침
                            </button>
                            <span id="switch-active-db-wrapper" class="d-inline-flex">
                                <button type="button" class="btn btn-warning btn-sm d-none" id="switch-active-db-button"></button>
                            </span>
                        </div>
                        <div id="switch-active-db-hint" class="small text-danger mt-2 d-none"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header fw-semibold text-primary">SQL 백업</div>
                    <div class="card-body">
                        <div class="alert alert-light small mb-3">
                            현재 Active DB를 SQL 파일로 저장합니다.
                        </div>

                        <div class="border rounded bg-light p-3 small mb-3" id="latest-backup-info">
                            확인 중...
                        </div>

                        <button type="button" id="run-backup-now" class="btn btn-primary fw-bold">
                            <i class="bi bi-cloud-arrow-down me-1"></i>지금 백업 실행
                        </button>

                        <div
                            id="backup-run-result"
                            class="overflow-hidden"
                            style="max-height: 0; opacity: 0; margin-top: 0; transition: max-height .25s ease, opacity .25s ease, margin-top .25s ease;"
                        ></div>

                        <hr class="my-4">

                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                            <div>
                                <div class="fw-semibold text-dark">자동 백업 설정</div>
                                <div class="text-muted small">
                                    데이터 변경이 발생하면 자동 백업 후보 상태가 되고, 설정한 최소 간격이 지나면 SQL 백업 파일을 생성합니다.
                                </div>
                            </div>
                            <span class="badge bg-light text-dark border">정책 설정</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold d-block">백업 생성 방식</label>
                                <div class="border rounded p-3">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="backup_auto_mode" id="backup_mode_manual" value="manual">
                                        <label class="form-check-label" for="backup_mode_manual">수동</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="backup_auto_mode" id="backup_mode_auto" value="auto">
                                        <label class="form-check-label" for="backup_mode_auto">자동(데이터 변경 감지)</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold" for="backup_auto_min_interval">자동 백업 최소 간격</label>
                                <select id="backup_auto_min_interval" class="form-select" disabled>
                                    <option value="12h">12시간</option>
                                    <option value="24h">24시간</option>
                                    <option value="48h">48시간</option>
                                </select>
                                <div class="form-text">현재 로직은 데이터 변경 감지 기준으로 자동 백업 시점을 판단합니다.</div>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold" for="backup_retention_days">백업 보관 기간</label>
                                <select id="backup_retention_days" name="backup_retention_days" class="form-select">
                                    <option value="7">7일</option>
                                    <option value="30">30일</option>
                                    <option value="90">90일</option>
                                </select>
                                <div class="form-text" id="backup-retention-help">보관 기간은 자동 정리가 켜진 경우에만 적용됩니다.</div>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label fw-semibold d-block">자동 정리</label>
                                <div class="border rounded p-3 h-100 d-flex flex-column justify-content-center">
                                    <div class="form-check form-switch d-flex align-items-center gap-2 mb-2">
                                        <input class="form-check-input mt-0" type="checkbox" id="backup_cleanup_enabled" name="backup_cleanup_enabled" value="1">
                                        <label class="form-check-label fw-semibold mb-0" for="backup_cleanup_enabled">오래된 백업 자동 정리</label>
                                    </div>
                                    <div class="form-text mb-0" id="backup-cleanup-help">보관 기간을 초과한 백업 파일은 자동으로 삭제됩니다.</div>
                                </div>
                            </div>
                        </div>

                        <div class="small text-muted mt-3" id="backup-policy-engine-summary">현재 자동 백업 정책을 불러오는 중입니다.</div>

                        <button type="submit" id="save-backup-settings" class="btn btn-primary mt-3">
                            <i class="bi bi-save me-1"></i>자동 백업 설정 저장
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header fw-semibold text-primary">DB 동기화</div>
                    <div class="card-body">
                        <div class="alert alert-light small mb-2" id="sync-target-summary">
                            현재 Active DB의 최신 백업을 대기 DB에 적용합니다.
                        </div>
                        <div class="text-muted small mb-3">
                            동기화 실행 시 대기 DB는 최신 백업 기준으로 갱신되며, 실패 시 자동 롤백됩니다.
                        </div>

                        <div id="latest-sync-info" class="border rounded bg-light p-3 small mb-3">
                            확인 중...
                        </div>

                        <button type="button" id="run-db-sync" class="btn btn-outline-primary btn-sm">
                            지금 동기화 실행
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header fw-semibold text-primary">백업 파일 목록</div>
                    <div class="card-body">
                        <div id="backup-file-list" class="border rounded bg-light small p-2 backup-file-list-box">
                            파일 목록을 불러오는 중...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header fw-semibold text-primary">DB 복원</div>
                    <div class="card-body">
                        <div class="alert alert-light small mb-3">
                            선택한 백업 파일을 현재 Active DB에 복원합니다.
                        </div>

                        <label class="form-label fw-semibold" for="restore-primary-file">복원 대상 파일</label>
                        <select id="restore-primary-file" class="form-select mb-2">
                            <option value="">백업 파일을 불러오는 중...</option>
                        </select>

                        <div class="form-text mb-3">
                            선택한 백업 파일을 현재 Active DB에 복원합니다.
                        </div>

                        <div id="latest-restore-info" class="border rounded bg-light p-3 small mb-3">
                            확인 중...
                        </div>

                        <button type="button" id="run-primary-restore" class="btn btn-outline-danger btn-sm">
                            복원 실행
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header fw-semibold text-primary">로그</div>
                    <div class="card-body d-flex flex-column">
                        <pre id="backup-log-viewer" class="bg-light border rounded p-2 small flex-grow-1" style="overflow:auto;">로그를 불러오는 중...</pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2 align-self-start" id="reload-backup-log">
                            로그 새로고침
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="restoreWarningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">DB 복원</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="restore-warning-summary">선택한 백업 파일을 현재 Active DB에 복원합니다.</p>
                <p class="mb-0">복원 실행 후에는 상태 카드에서 진행 상황을 계속 확인할 수 있습니다. 계속 진행하시겠습니까?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-danger" id="confirm-db-restore">복원 실행</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="syncWarningModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">DB 동기화</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="sync-warning-summary">현재 Active DB의 최신 백업을 대기 DB에 적용합니다.</p>
                <p class="mb-0">동기화 실행 후에는 상태 카드에서 진행 상황을 계속 확인할 수 있습니다. 계속 진행하시겠습니까?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-danger" id="confirm-db-sync">동기화 실행</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="switchActiveDbModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Active DB 전환</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">현재 Active DB를 변경합니다.</p>
                <p class="mb-2 fw-semibold" id="switch-active-summary">3306 → 3307</p>
                <p class="mb-0">모든 신규 요청은 전환 후 Active DB로 연결됩니다. 계속 진행하시겠습니까?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-warning" id="confirm-switch-active-db">전환 실행</button>
            </div>
        </div>
    </div>
</div>
