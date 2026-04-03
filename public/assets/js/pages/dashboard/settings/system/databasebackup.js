/**
 * /public/assets/js/pages/dashboard/settings/system/databasebackup.js
 * 시스템 설정 > 데이터베이스 백업
 * 파일: system.databasebackup.js
 */
console.log('[SYSTEM DATABASEBACKUP] JS LOADED');
document.addEventListener('DOMContentLoaded', () => {
    loadBackupSettings();// 설정 로딩
    loadBackupInfo();   // 최신 백업 표시
    loadBackupLog();    // 백업 로그 표시
    loadReplicationStatus(); // 이중화 상태
    bindBackupForm(); // 설정 저장
    bindRunBackup(); // 수동 백업
    bindRetentionButtons(); // + / -
    bindReloadBackupLog();
    loadSecondaryRestoreInfo();
    bindRunSecondaryRestore();
    const autoToggle = document.getElementById('backup_auto_enabled');
    if (autoToggle) {
        autoToggle.addEventListener('change', toggleAutoBackupOptions);
    }
});

/* =========================================================
 * 1. 백업 설정 불러오기
 * ========================================================= */
function loadBackupSettings() {
    fetch('/api/settings/system/database/get')
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert(res.message || '백업 설정 조회 실패');
                return;
            }

            const data = res.data || {};

            // 자동 백업 사용
            setCheckbox('backup_auto_enabled', data.backup_auto_enabled);

            // 실행 주기
            setValue('backup_schedule', data.backup_schedule || 'daily');

            // 보관 기간
            setValue('backup_retention_days', data.backup_retention_days || 30);

            // 오래된 백업 정리
            setCheckbox('backup_cleanup_enabled', data.backup_cleanup_enabled);
            
            //세컨 DB 자동복원
            setCheckbox('backup_restore_secondary_enabled', data.backup_restore_secondary_enabled);
            toggleAutoBackupOptions();

            // (선택) 실행 시간
            if (data.backup_time) {
                setValue('backup_time', data.backup_time);
            }

        })
        .catch(err => {
            console.error(err);
            alert('백업 설정 로딩 중 오류 발생');
        });
}


/* =========================================================
 * 2. 백업 설정 저장
 * ========================================================= */
function bindBackupForm() {
    const form = document.getElementById('backup-setting-form');
    if (!form) return;

    form.addEventListener('submit', e => {
        e.preventDefault();

        const payload = {
            backup_auto_enabled: isChecked('backup_auto_enabled') ? '1' : '0',
            backup_schedule: getValue('backup_schedule'),
            backup_retention_days: getValue('backup_retention_days'),
            backup_cleanup_enabled: isChecked('backup_cleanup_enabled') ? '1' : '0',
            backup_restore_secondary_enabled: isChecked('backup_restore_secondary_enabled') ? '1' : '0',
        };

        fetch('/api/settings/system/database/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert(res.message || '백업 설정 저장 실패');
                return;
            }

            alert('백업 설정이 저장되었습니다.');
        })
        .catch(err => {
            console.error(err);
            alert('백업 설정 저장 중 오류 발생');
        });
    });
}

/* =========================================================
 * 3. 수동 백업 실행
 * ========================================================= */
function bindRunBackup() {
    const btn = document.getElementById('run-backup-now');
    const resultBox = document.getElementById('backup-run-result');

    if (!btn) return;

    btn.addEventListener('click', () => {
        if (!confirm('지금 데이터베이스 백업을 실행하시겠습니까?')) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> 백업 중...';

        fetch('/api/settings/system/database/run', {
            method: 'POST'
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                resultBox.innerHTML =
                    `<div class="alert alert-danger">${res.message || '백업 실패'}</div>`;
                return;
            }

            resultBox.innerHTML = `
                <div class="alert alert-success">
                    백업 완료<br>
                    <small class="text-muted">
                        파일: ${res.filename || ''}
                    </small>
                </div>
            `;
        })
        .catch(err => {
            console.error(err);
            resultBox.innerHTML =
                `<div class="alert alert-danger">백업 실행 중 오류 발생</div>`;
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML =
                '<i class="bi bi-cloud-arrow-down me-1"></i> 지금 백업 실행';
        });
    });
}

/* =========================================================
 * 4. 보관 기간 + / - 버튼 처리
 * ========================================================= */
function bindRetentionButtons() {
    document.querySelectorAll('[data-target][data-step]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.querySelector(btn.dataset.target);
            if (!target) return;

            const step = parseInt(btn.dataset.step, 10);
            let value = parseInt(target.value || 0, 10);

            value = isNaN(value) ? 0 : value + step;
            if (value < 1) value = 1;
            if (value > 365) value = 365;

            target.value = value;
        });
    });
}

/* =========================================================
 * 유틸 함수
 * ========================================================= */
function getValue(id) {
    const el = document.getElementById(id);
    return el ? el.value : null;
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el !== null && value !== undefined) {
        el.value = value;
    }
}

function isChecked(id) {
    const el = document.getElementById(id);
    return el ? el.checked : false;
}

function setCheckbox(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.checked = String(value) === '1';
}
function loadBackupInfo() {
    fetch('/api/settings/system/database/info')
        .then(res => res.json())
        .then(res => {
            if (!res.success) return;

            const data = res.data || {};

            // ✅ 저장 경로
            const dirBox = document.getElementById('backup-directory');
            if (dirBox) {
                dirBox.textContent = data.backup_directory || '-';
            }

            // ✅ 최신 백업
            const latestBox = document.getElementById('latest-backup-info');
            if (latestBox) {
                if (data.latest_backup) {
                    latestBox.innerHTML = `
                        파일: ${data.latest_backup.file}<br>
                        시간: ${data.latest_backup.time}
                    `;
                } else {
                    latestBox.textContent = '백업 기록 없음';
                }
            }
        });
}

function loadBackupLog() {
    fetch('/api/settings/system/database/log')
        .then(res => res.json())
        .then(res => {
            if (!res.success) return;

            const logViewer = document.getElementById('backup-log-viewer');
            if (logViewer) {
                logViewer.textContent = res.data?.log || '로그 없음';
            }
        });
}

function loadReplicationStatus() {
    fetch('/api/settings/system/database/replication-status')
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                console.warn('[REPLICATION] API failed', res);
                return;
            }

            const data = res;
            const p = data.primary || {};
            const s = data.secondary || {};

            /* ===============================
             * Primary DB
             * =============================== */
            setReplicationRow(
                'primary',
                p.online === true,
                p.host,
                p.port,
                p.read_only
            );

            /* ===============================
             * Secondary DB
             * =============================== */
            setReplicationRow(
                'secondary',
                s.online === true,
                null,
                null,
                null,
                s
            );

            /* ===============================
             * 동기화 상태 Badge
             * =============================== */
            const syncEl = document.getElementById('replication-sync');
            if (syncEl) {
                if (!s.online) {
                    syncEl.className = 'badge bg-danger';
                    syncEl.textContent = 'OFFLINE';
                } else if (s.replication === false) {
                    syncEl.className = 'badge bg-secondary';
                    syncEl.textContent = 'STANDBY';
                } else if (s.io_running && s.sql_running) {
                    syncEl.className = 'badge bg-success';
                    syncEl.textContent = 'SYNC';
                } else {
                    syncEl.className = 'badge bg-warning';
                    syncEl.textContent = 'DEGRADED';
                }
            }

            /* ===============================
             * Replication Lag
             * =============================== */
            const lagEl = document.getElementById('replication-lag');
            if (lagEl) {
                lagEl.textContent =
                    s.lag !== undefined && s.lag !== null
                        ? `${s.lag} sec`
                        : '-';
            }

            /* ===============================
             * 마지막 확인 시간
             * =============================== */
            const checkedEl = document.getElementById('replication-checked-at');
            if (checkedEl) {
                checkedEl.textContent = data.checked_at || '-';
            }
        })
        .catch(err => {
            console.error('[REPLICATION] JS error', err);
        });
}



function setReplicationRow(type, online, host, port, readOnly, slaveStatus = null) {
    const statusEl = document.getElementById(`${type}-status`);
    const badgeEl  = document.getElementById(`${type}-badge`);

    if (!statusEl || !badgeEl) return;

    if (!online) {
        badgeEl.className = 'badge bg-danger';
        badgeEl.textContent = 'OFFLINE';
        statusEl.textContent = '연결 실패';
        return;
    }

    badgeEl.className = 'badge bg-success';
    badgeEl.textContent = 'ONLINE';

    if (type === 'primary') {
        statusEl.textContent =
            `${host}:${port}` + (readOnly ? ' (READ ONLY)' : '');
    } else {
        if (slaveStatus?.io_running && slaveStatus?.sql_running) {
            statusEl.textContent = '정상 동기화';
        } else {
            if (slaveStatus?.replication === false) {
                badgeEl.className = 'badge bg-secondary';
                badgeEl.textContent = 'STANDBY';
                statusEl.textContent = '대기 상태 (Replication 미구성)';
            } else {
                badgeEl.className = 'badge bg-warning';
                badgeEl.textContent = 'DEGRADED';
                statusEl.textContent = slaveStatus?.last_error || '동기화 이상';
            }
        }
        
    }
}


function toggleAutoBackupOptions() {
    const enabled = isChecked('backup_auto_enabled');

    [
        'backup_cleanup_enabled',
        'backup_restore_secondary_enabled',
        'backup_schedule',
        'backup_retention_days'
    ].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = !enabled;
    });
}
function bindReloadBackupLog() {
    const btn = document.getElementById('reload-backup-log');
    if (!btn) return;

    btn.addEventListener('click', () => {
        loadBackupLog();
    });
}




function loadSecondaryRestoreInfo() {
    fetch('/api/settings/system/database/secondary-restore-info')
        .then(res => res.json())
        .then(res => {
            const box = document.getElementById('latest-secondary-restore-info');
            if (!box) return;

            if (!res.success || !res.data) {
                box.textContent = '복원 기록 없음';
                return;
            }

            box.innerHTML = `
                파일: ${res.data.file}<br>
                시간: ${res.data.time}
            `;
        })
        .catch(err => {
            console.error('[SECONDARY RESTORE INFO]', err);
        });
}


function bindRunSecondaryRestore() {
    const btn = document.getElementById('run-secondary-restore');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        if (!confirm(
            '최신 백업으로 Secondary DB를 복원하시겠습니까?\n\n' +
            '⚠ 기존 Secondary DB 데이터는 모두 덮어씁니다.'
        )) {
            return;
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = '복원 요청 중...';

        try {
            const res = await fetch(
                '/api/settings/system/database/restore-secondary',
                { method: 'POST' }
            );

            // 🔑 JSON 가정 ❌ → 먼저 text 로 받는다
            const rawText = await res.text();

            // HTTP 에러 처리
            if (!res.ok) {
                throw {
                    type: 'http',
                    status: res.status,
                    body: rawText
                };
            }

            // JSON 파싱 시도
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                throw {
                    type: 'parse',
                    body: rawText
                };
            }

            if (!data.success) {
                alert(data.message || 'Secondary DB 복원 실패');
                return;
            }

            alert('Secondary DB 복원이 완료되었습니다.');
            loadSecondaryRestoreInfo(); // ✅ 최신 복원 기록 즉시 반영

        } catch (err) {
            console.error('[SECONDARY RESTORE ERROR]', err);

            // ===============================
            // 사용자에게 의미 있는 오류 표시
            // ===============================
            if (err.type === 'http') {
                if (err.status === 504) {
                    alert(
                        '⏱ 복원 시간 초과 (504 Gateway Timeout)\n\n' +
                        '원인 가능성:\n' +
                        '• 데이터베이스 용량이 큼\n' +
                        '• SQL 실행 시간이 김\n\n' +
                        '👉 웹 UI 복원은 제한이 있으므로\n' +
                        'CLI(서버 직접 실행) 복원을 권장합니다.'
                    );
                } else if (err.status === 500) {
                    alert(
                        '🚫 서버 내부 오류 (500)\n\n' +
                        '복원 중 서버에서 오류가 발생했습니다.\n' +
                        '관리자 로그를 확인하세요.'
                    );
                } else {
                    alert(
                        `서버 오류 (${err.status}) 발생\n\n` +
                        '자세한 내용은 로그를 확인하세요.'
                    );
                }
            }
            else if (err.type === 'parse') {
                alert(
                    '⚠ 서버 응답 형식 오류\n\n' +
                    'JSON이 아닌 응답을 받았습니다.\n' +
                    '대개 PHP 타임아웃 또는 서버 에러 페이지입니다.'
                );
            }
            else {
                alert(
                    '복원 중 알 수 없는 오류가 발생했습니다.\n' +
                    '콘솔 로그를 확인하세요.'
                );
            }

        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
}
