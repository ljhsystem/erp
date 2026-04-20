(() => {
    'use strict';

    const API = {
        GET: '/api/settings/system/database/get',
        SAVE: '/api/settings/system/database/save',
        RUN: '/api/settings/system/database/run',
        INFO: '/api/settings/system/database/info',
        LOG: '/api/settings/system/database/log',
        REPLICATION: '/api/settings/system/database/replication-status',
        RESTORE: '/api/settings/system/database/restore-secondary',
        RESTORE_INFO: '/api/settings/system/database/secondary-restore-info'
    };

    let restorePollTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
        loadBackupSettings();
        loadBackupInfo();
        loadBackupLog();
        loadReplicationStatus();
        loadSecondaryRestoreInfo();
        bindBackupForm();
        bindRunBackup();
        bindRetentionButtons();
        bindReloadBackupLog();
        bindReplicationReload();
        bindRunSecondaryRestore();

        const autoToggle = document.getElementById('backup_auto_enabled');
        if (autoToggle) {
            autoToggle.addEventListener('change', toggleAutoBackupOptions);
        }
    });

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        console[type === 'error' ? 'error' : 'log'](message);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options
        });

        const text = normalizeResponseText(await response.text());
        let json = {};

        try {
            json = text ? JSON.parse(text) : {};
        } catch (_) {
            throw new Error('??뺤쒔 ?臾먮뼗????곴퐤??? 筌륁궢六??щ빍??');
        }

        if (!response.ok) {
            throw new Error(json?.message || '?遺욧퍕 筌ｌ꼶??餓???살첒揶쎛 獄쏆뮇源??됰뮸??덈뼄.');
        }

        return json;
    }

    function normalizeResponseText(text) {
        const normalized = String(text || '').replace(/^\uFEFF/, '').trim();
        if (!normalized) {
            return '';
        }

        if (normalized.startsWith('<')) {
            const match = normalized.match(/(\{[\s\S]*\}|\[[\s\S]*\])\s*$/);
            if (match) {
                return match[1];
            }
        }

        return normalized;
    }

    async function loadBackupSettings() {
        try {
            const json = await fetchJson(API.GET);
            if (!json?.success) {
                throw new Error(json?.message || '獄쏄퉮毓???쇱젟???븍뜄???? 筌륁궢六??щ빍??');
            }

            const data = json.data || {};
            setCheckbox('backup_auto_enabled', data.backup_auto_enabled);
            setValue('backup_schedule', data.backup_schedule || 'daily');
            setValue('backup_retention_days', data.backup_retention_days || 30);
            setCheckbox('backup_cleanup_enabled', data.backup_cleanup_enabled);
            setCheckbox('backup_restore_secondary_enabled', data.backup_restore_secondary_enabled);
            setValue('backup_time', data.backup_time || '02:00');
            toggleAutoBackupOptions();
        } catch (error) {
            notify('error', error.message || '獄쏄퉮毓???쇱젟 鈺곌퀬?????쎈솭??됰뮸??덈뼄.');
        }
    }

    function bindBackupForm() {
        const form = document.getElementById('backup-setting-form');
        const submitButton = document.getElementById('save-backup-settings');
        if (!form) return;

        form.addEventListener('submit', async event => {
            event.preventDefault();

            const payload = {
                backup_auto_enabled: isChecked('backup_auto_enabled') ? '1' : '0',
                backup_schedule: getValue('backup_schedule'),
                backup_time: getValue('backup_time'),
                backup_retention_days: getValue('backup_retention_days'),
                backup_cleanup_enabled: isChecked('backup_cleanup_enabled') ? '1' : '0',
                backup_restore_secondary_enabled: isChecked('backup_restore_secondary_enabled') ? '1' : '0'
            };

            if (!['daily', 'weekly', 'monthly'].includes(payload.backup_schedule)) {
                notify('warning', '?癒?짗 獄쏄퉮毓???쎈뻬 雅뚯눊由곁몴??類ㅼ뵥??곻폒?紐꾩뒄.');
                return;
            }

            if (!/^(2[0-3]|[01]\d):([0-5]\d)$/.test(payload.backup_time || '')) {
                notify('warning', '?癒?짗 獄쏄퉮毓???쎈뻬 ??볦퍢???類ㅼ뵥??곻폒?紐꾩뒄.');
                return;
            }

            const retentionDays = Number(payload.backup_retention_days);
            if (!Number.isFinite(retentionDays) || retentionDays < 1 || retentionDays > 365) {
                notify('warning', '獄쏄퉮毓?癰귣떯? 疫꿸퀗而?? 1??깅퓠??365???????鍮???몃빍??');
                return;
            }

            try {
                if (submitButton) {
                    submitButton.disabled = true;
                }

                const json = await fetchJson(API.SAVE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (!json?.success) {
                    throw new Error(json?.message || '獄쏄퉮毓???쇱젟 ???關肉???쎈솭??됰뮸??덈뼄.');
                }

                notify('success', '獄쏄퉮毓???쇱젟?????貫由??됰뮸??덈뼄.');
            } catch (error) {
                notify('error', error.message || '獄쏄퉮毓???쇱젟 ???關肉???쎈솭??됰뮸??덈뼄.');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        });
    }

    function bindRunBackup() {
        const button = document.getElementById('run-backup-now');
        const resultBox = document.getElementById('backup-run-result');
        if (!button || !resultBox) return;

        button.addEventListener('click', async () => {
            try {
                button.disabled = true;
                button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>獄쏄퉮毓?餓?..';

                const json = await fetchJson(API.RUN, { method: 'POST' });
                if (!json?.success) {
                    throw new Error(json?.message || '??롫짗 獄쏄퉮毓???쎈뻬????쎈솭??됰뮸??덈뼄.');
                }

                resultBox.innerHTML = `
                    <div class="alert alert-success mb-0">
                        <div class="fw-semibold mb-1">Primary DB 獄쏄퉮毓???袁⑥┷??뤿???щ빍??</div>
                        <div>???뵬: ${escapeHtml(json.filename || '-')}</div>
                        <div>??볦퍢: ${escapeHtml(json.time || '-')}</div>
                    </div>
                `;

                notify('success', 'Primary DB 獄쏄퉮毓???袁⑥┷??뤿???щ빍??');
                loadBackupInfo();
                loadBackupLog();
            } catch (error) {
                resultBox.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        ${escapeHtml(error.message || '??롫짗 獄쏄퉮毓?餓???살첒揶쎛 獄쏆뮇源??됰뮸??덈뼄.')}
                    </div>
                `;
                notify('error', error.message || '??롫짗 獄쏄퉮毓???쎈뻬????쎈솭??됰뮸??덈뼄.');
            } finally {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-cloud-arrow-down me-1"></i>筌왖疫?獄쏄퉮毓???쎈뻬';
            }
        });
    }

    function bindRetentionButtons() {
        document.querySelectorAll('[data-target][data-step]').forEach(button => {
            button.addEventListener('click', () => {
                const target = document.querySelector(button.dataset.target);
                if (!target) return;

                const step = Number(button.dataset.step || 0);
                let value = Number(target.value || 0);
                value = Number.isFinite(value) ? value + step : 1;
                value = Math.max(1, Math.min(365, value));
                target.value = value;
            });
        });
    }

    async function loadBackupInfo() {
        try {
            const json = await fetchJson(API.INFO);
            if (!json?.success) {
                throw new Error(json?.message || '獄쏄퉮毓??類ｋ궖???븍뜄???? 筌륁궢六??щ빍??');
            }

            const data = json.data || {};
            const dirBox = document.getElementById('backup-directory');
            const latestBox = document.getElementById('latest-backup-info');

            if (dirBox) {
                dirBox.textContent = data.backup_directory_masked || '-';
            }

            if (latestBox) {
                if (data.latest_backup) {
                    latestBox.innerHTML = `
                        ???뵬: ${escapeHtml(data.latest_backup.file || '-')}<br>
                        ??볦퍢: ${escapeHtml(data.latest_backup.time || '-')}<br>
                        ??由? ${formatBytes(data.latest_backup.size || 0)}
                    `;
                } else {
                    latestBox.textContent = '獄쏄퉮毓????????곷뮸??덈뼄.';
                }
            }
        } catch (error) {
            notify('error', error.message || '獄쏄퉮毓??類ｋ궖 鈺곌퀬?????쎈솭??됰뮸??덈뼄.');
        }
    }

    async function loadBackupLog() {
        try {
            const json = await fetchJson(API.LOG);
            if (!json?.success) {
                throw new Error(json?.message || '獄쏄퉮毓?嚥≪뮄?뉒몴??븍뜄???? 筌륁궢六??щ빍??');
            }

            const logViewer = document.getElementById('backup-log-viewer');
            if (logViewer) {
                logViewer.textContent = json.data?.log || '嚥≪뮄?뉐첎? ??곷뮸??덈뼄.';
            }
        } catch (error) {
            notify('error', error.message || '獄쏄퉮毓?嚥≪뮄??鈺곌퀬?????쎈솭??됰뮸??덈뼄.');
        }
    }

    async function loadReplicationStatus() {
        try {
            const json = await fetchJson(API.REPLICATION);
            if (!json?.success) {
                throw new Error(json?.message || '癰귣벊???怨밴묶???븍뜄???? 筌륁궢六??щ빍??');
            }

            const primary = json.primary || {};
            const secondary = json.secondary || {};

            setReplicationRow('primary', primary.online === true, primary.host, primary.port, primary.read_only);
            setReplicationRow('secondary', secondary.online === true, null, null, null, secondary);

            const syncEl = document.getElementById('replication-sync');
            if (syncEl) {
                if (!secondary.online) {
                    syncEl.className = 'badge bg-danger';
                    syncEl.textContent = 'OFFLINE';
                } else if (secondary.replication === false) {
                    syncEl.className = 'badge bg-secondary';
                    syncEl.textContent = 'STANDBY';
                } else if (secondary.io_running && secondary.sql_running) {
                    syncEl.className = 'badge bg-success';
                    syncEl.textContent = 'SYNC';
                } else {
                    syncEl.className = 'badge bg-warning';
                    syncEl.textContent = 'DEGRADED';
                }
            }

            const lagEl = document.getElementById('replication-lag');
            if (lagEl) {
                lagEl.textContent = secondary.lag !== undefined && secondary.lag !== null
                    ? `${secondary.lag} sec`
                    : '-';
            }

            const checkedAt = document.getElementById('replication-checked-at');
            if (checkedAt) {
                checkedAt.textContent = json.checked_at || '-';
            }
        } catch (error) {
            notify('error', error.message || '癰귣벊???怨밴묶 鈺곌퀬?????쎈솭??됰뮸??덈뼄.');
        }
    }

    function setReplicationRow(type, online, host, port, readOnly, slaveStatus = null) {
        const statusEl = document.getElementById(`${type}-status`);
        const badgeEl = document.getElementById(`${type}-badge`);
        if (!statusEl || !badgeEl) return;

        if (!online) {
            badgeEl.className = 'badge bg-danger';
            badgeEl.textContent = 'OFFLINE';
            statusEl.textContent = '?怨뚭퍙 ??쎈솭';
            return;
        }

        badgeEl.className = 'badge bg-success';
        badgeEl.textContent = 'ONLINE';

        if (type === 'primary') {
            statusEl.textContent = `${host}:${port}` + (readOnly ? ' (READ ONLY)' : '');
            return;
        }

        if (slaveStatus?.io_running && slaveStatus?.sql_running) {
            statusEl.textContent = '정상 동기화';
            return;
        }

        if (slaveStatus?.replication === false) {
            badgeEl.className = 'badge bg-secondary';
            badgeEl.textContent = 'STANDBY';
            statusEl.textContent = '??疫??怨밴묶 (Replication 沃섎㈇???';
            return;
        }

        badgeEl.className = 'badge bg-warning';
        badgeEl.textContent = 'DEGRADED';
        statusEl.textContent = slaveStatus?.last_error || '?怨밴묶 ??곴맒';
    }

    function toggleAutoBackupOptions() {
        const enabled = isChecked('backup_auto_enabled');
        [
            'backup_cleanup_enabled',
            'backup_restore_secondary_enabled',
            'backup_schedule',
            'backup_retention_days',
            'backup_time'
        ].forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.disabled = !enabled;
            }
        });
    }

    function bindReloadBackupLog() {
        document.getElementById('reload-backup-log')?.addEventListener('click', loadBackupLog);
    }

    function bindReplicationReload() {
        document.getElementById('reload-replication-status')?.addEventListener('click', loadReplicationStatus);
    }

    async function loadSecondaryRestoreInfo() {
        try {
            const json = await fetchJson(API.RESTORE_INFO);
            const box = document.getElementById('latest-secondary-restore-info');
            if (!box) return;

            const data = json?.data || {};
            const state = data.state || 'idle';
            const message = data.message || '복원 이력이 없습니다.';
            const isStaleFailure = state === 'failed' && data.stale === true;

            const badgeClass = state === 'success'
                ? 'bg-success'
                : state === 'failed'
                    ? 'bg-danger'
                    : state === 'running'
                        ? 'bg-warning text-dark'
                        : 'bg-secondary';

            box.innerHTML = `
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge ${badgeClass}">${escapeHtml(state.toUpperCase())}</span>
                    <span>${escapeHtml(message)}</span>
                </div>
                <div>파일: ${escapeHtml(data.file || '-')}</div>
                <div>시작: ${escapeHtml(data.started_at || '-')}</div>
                <div>종료: ${escapeHtml(data.finished_at || '-')}</div>
                ${data.stage ? `<div>단계: ${escapeHtml(data.stage)}</div>` : ''}
                ${data.updated_at ? `<div>최근 갱신: ${escapeHtml(data.updated_at)}</div>` : ''}
                ${data.rollback_attempted ? `<div>롤백: ${data.rollback_success ? '성공' : '실패'}</div>` : ''}
                ${data.rollback_message ? `<div class="text-muted small mt-1">${escapeHtml(data.rollback_message)}</div>` : ''}
                ${isStaleFailure ? `<div class="text-danger small mt-2">복원 프로세스 응답이 오래 없어 실패로 전환되었습니다. 로그를 확인한 뒤 다시 시도해주세요.</div>` : ''}
                ${data.warning ? `<div class="text-muted small mt-2">${escapeHtml(data.warning)}</div>` : ''}
            `;

            if (state === 'running') {
                startRestorePolling();
            } else {
                stopRestorePolling();
            }
        } catch (error) {
            notify('error', error.message || '복원 상태 조회에 실패했습니다.');
        }
    }

    function bindRunSecondaryRestore() {
        const runButton = document.getElementById('run-secondary-restore');
        const confirmButton = document.getElementById('confirm-secondary-restore');
        const modalElement = document.getElementById('restoreWarningModal');
        if (!runButton || !confirmButton || !modalElement) return;

        const modal = new bootstrap.Modal(modalElement);

        runButton.addEventListener('click', () => modal.show());

        confirmButton.addEventListener('click', async () => {
            modal.hide();
            runButton.disabled = true;
            confirmButton.disabled = true;
            const originalText = runButton.textContent;
            runButton.textContent = '癰귣벊???遺욧퍕 餓?..';

            startRestorePolling();

            try {
                const response = await fetch(API.RESTORE, {
                    method: 'POST',
                    credentials: 'same-origin'
                });

                if (!response.ok && response.status !== 202) {
                    const text = normalizeResponseText(await response.text());
                    let message = 'Secondary DB 癰귣벊???遺욧퍕????쎈솭??됰뮸??덈뼄.';

                    if (text) {
                        try {
                            const json = JSON.parse(text);
                            message = json?.message || message;
                        } catch (_) {
                            message = text;
                        }
                    }

                    throw new Error(message);
                }

                notify('info', 'Secondary DB 癰귣벊???遺욧퍕???臾믩땾??됰뮸??덈뼄. ?袁⑥삋 癰귣벊???怨밴묶???類ㅼ뵥??뤾쉭??');
            } catch (error) {
                try {
                    const statusJson = await fetchJson(API.RESTORE_INFO);
                    const state = statusJson?.data?.state || '';

                    if (state === 'running') {
                        notify('info', '癰귣벊???臾믩씜?? ??뽰삂??뤿???щ빍?? ?袁⑥삋 癰귣벊???怨밴묶???類ㅼ뵥??뤾쉭??');
                    } else {
                        notify('error', error.message || 'Secondary DB 癰귣벊???遺욧퍕????쎈솭??됰뮸??덈뼄.');
                    }
                } catch (_) {
                    notify('error', error.message || 'Secondary DB 癰귣벊???遺욧퍕????쎈솭??됰뮸??덈뼄.');
                }
            } finally {
                runButton.disabled = false;
                confirmButton.disabled = false;
                runButton.textContent = originalText;
                await loadSecondaryRestoreInfo();
            }
        });
    }

    function startRestorePolling() {
        stopRestorePolling();
        restorePollTimer = setInterval(loadSecondaryRestoreInfo, 2000);
    }

    function stopRestorePolling() {
        if (restorePollTimer) {
            clearInterval(restorePollTimer);
            restorePollTimer = null;
        }
    }

    function getValue(id) {
        const element = document.getElementById(id);
        return element ? element.value : '';
    }

    function setValue(id, value) {
        const element = document.getElementById(id);
        if (element && value !== undefined && value !== null) {
            element.value = value;
        }
    }

    function isChecked(id) {
        const element = document.getElementById(id);
        return element ? element.checked : false;
    }

    function setCheckbox(id, value) {
        const element = document.getElementById(id);
        if (!element) return;
        element.checked = String(value) === '1' || value === true;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatBytes(size) {
        const numericSize = Number(size || 0);
        if (numericSize <= 0) return '0 B';
        if (numericSize < 1024) return `${numericSize} B`;
        if (numericSize < 1024 * 1024) return `${(numericSize / 1024).toFixed(1)} KB`;
        if (numericSize < 1024 * 1024 * 1024) return `${(numericSize / 1024 / 1024).toFixed(1)} MB`;
        return `${(numericSize / 1024 / 1024 / 1024).toFixed(1)} GB`;
    }
})();
