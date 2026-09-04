(() => {
    'use strict';

    const API = {
        SETTINGS_GET: '/api/settings/system/database/get',
        SETTINGS_SAVE: '/api/settings/system/database/save',
        BACKUP_RUN: '/api/settings/system/database/run',
        BACKUP_INFO: '/api/settings/system/database/info',
        STATUS: '/api/settings/system/database/status',
        SWITCH_ACTIVE: '/api/settings/system/database/switch-active',
        SYNC_RUN: '/api/settings/system/database/sync',
        SYNC_INFO: '/api/settings/system/database/sync-info',
        RESTORE_RUN: '/api/settings/system/database/restore',
        RESTORE_INFO: '/api/settings/system/database/restore-info',
        LOG: '/api/settings/system/database/activity-log'
    };

    const state = {
        syncPollTimer: null,
        restorePollTimer: null,
        backupResultTimer: null,
        activeTarget: null,
        switchBlockedReason: '',
        canSwitchActiveByRole: false,
        syncRequestPending: false,
        restoreRequestPending: false,
        syncActiveLabel: 'Active DB',
        syncStandbyLabel: 'Standby DB',
        restoreActiveLabel: 'Active DB',
        syncInfo: null,
        restoreInfo: null
    };

    document.addEventListener('DOMContentLoaded', () => {
        void Promise.all([
            loadDatabaseStatus(),
            loadBackupSettings(),
            loadBackupInfo(),
            loadSyncInfo(),
            loadRestoreInfo(),
            loadActivityLog()
        ]);

        bindBackupForm();
        bindBackupRun();
        bindSyncRun();
        bindRestoreRun();
        bindReloadButtons();
        bindCleanupToggle();
        bindBackupModeChange();
        bindActiveSwitch();
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
            throw new Error('서버 응답을 해석할 수 없습니다.');
        }

        if (!response.ok) {
            throw new Error(json?.message || '요청 처리 중 오류가 발생했습니다.');
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

    async function loadDatabaseStatus() {
        try {
            const json = await fetchJson(API.STATUS);
            if (!json?.success) {
                throw new Error(json?.message || '현재 DB 상태를 불러오지 못했습니다.');
            }

            const primary = json.primary || {};
            const secondary = json.secondary || {};
            const activeDb = json.active_db || {};
            const latestSwitch = json.latest_switch || {};

            setText('status-active-db', formatActiveStatus(activeDb));
            setText('status-primary-db', formatDbNode('PRIMARY', 3306, primary));
            setText('status-secondary-db', formatDbNode('SECONDARY', 3307, secondary));
            setText('status-checked-at', json.checked_at || '-');
            setText('status-switched-at', latestSwitch.executed_at || '-');
            setText('status-switched-by', latestSwitch.executed_by_name || '-');

            state.canSwitchActiveByRole = Boolean(json.can_switch_active_by_role);
            state.switchBlockedReason = String(json.switch_block_reason || '');
            renderActiveSwitchButton(activeDb, Boolean(json.can_switch_active), state.switchBlockedReason);
        } catch (error) {
            notify('error', error.message || '현재 DB 상태 조회에 실패했습니다.');
        }
    }

    function formatActiveStatus(activeDb) {
        const port = Number(activeDb?.port || 0);
        if (port > 0) {
            return `PRIMARY (${port})`;
        }
        return activeDb?.label || '-';
    }

    function formatDbNode(label, fallbackPort, data) {
        if (data?.online === true) {
            return `${label} (${data?.port || fallbackPort}) / ONLINE`;
        }
        return `${label} (${fallbackPort}) / OFFLINE`;
    }

    function renderActiveSwitchButton(activeDb, canSwitch, blockedReason = '') {
        const button = document.getElementById('switch-active-db-button');
        const wrapper = document.getElementById('switch-active-db-wrapper');
        const hint = document.getElementById('switch-active-db-hint');
        if (!button) {
            return;
        }

        const role = String(activeDb?.role || '').toUpperCase();
        const reasonText = String(blockedReason || '').trim();

        if (!state.canSwitchActiveByRole) {
            button.classList.add('d-none');
            button.disabled = true;
            button.textContent = '';
            state.activeTarget = null;
            button.removeAttribute('title');
            if (wrapper) {
                wrapper.removeAttribute('title');
            }
            if (hint) {
                hint.textContent = '';
                hint.classList.add('d-none');
            }
            return;
        }

        if (role === 'PRIMARY') {
            state.activeTarget = 'secondary';
            button.textContent = '3307을 Active DB로 전환';
        } else if (role === 'SECONDARY') {
            state.activeTarget = 'primary';
            button.textContent = '3306을 Active DB로 전환';
        } else {
            state.activeTarget = null;
            button.classList.add('d-none');
            button.disabled = true;
            button.textContent = '';
            return;
        }

        button.classList.remove('d-none');
        button.disabled = !canSwitch;
        button.title = reasonText;

        if (wrapper) {
            if (reasonText) {
                wrapper.setAttribute('title', reasonText);
            } else {
                wrapper.removeAttribute('title');
            }
        }

        if (hint) {
            hint.textContent = reasonText;
            hint.classList.toggle('d-none', !reasonText);
        }
    }

    async function loadBackupSettings() {
        try {
            const json = await fetchJson(API.SETTINGS_GET);
            if (!json?.success) {
                throw new Error(json?.message || '자동 백업 설정을 불러오지 못했습니다.');
            }

            const data = json.data || {};
            setBackupMode(data.backup_auto_trigger_mode || (String(data.backup_auto_enabled) === '1' ? 'data-change' : 'manual'));
            setRetentionDays(data.backup_retention_days || 30);
            setCheckbox('backup_cleanup_enabled', data.backup_cleanup_enabled);
            setPolicyIntervalPreview(data.backup_auto_min_interval_hours || 24);
            setPolicyEngineSummary(data.backup_auto_trigger_mode || 'manual', data.backup_auto_min_interval_hours || 24);
            syncBackupModeUi();
            syncCleanupUi();
        } catch (error) {
            notify('error', error.message || '자동 백업 설정 조회에 실패했습니다.');
        }
    }

    async function loadBackupInfo() {
        try {
            const json = await fetchJson(API.BACKUP_INFO);
            if (!json?.success) {
               throw new Error(json?.message || 'SQL 백업 정보를 불러오지 못했습니다.');
            }

            const data = json.data || {};
            renderLatestBackupInfo(data);
            renderBackupFileList(data.backup_files || []);
            renderPrimaryRestoreOptions(data.backup_files || []);
        } catch (error) {
            notify('error', error.message || 'SQL 백업 정보 조회에 실패했습니다.');
        }
    }

    function renderLatestBackupInfo(data) {
        const box = document.getElementById('latest-backup-info');
        if (!box) {
            return;
        }

        const latest = data.latest_backup || null;
        const directory = String(data.backup_directory || data.backup_directory_masked || '-');

        if (!latest) {
            box.innerHTML = `
                <div>저장 경로: <code>${escapeHtml(directory)}</code></div>
                <div class="mt-2">최신 백업 파일이 없습니다.</div>
            `;
            return;
        }

        box.innerHTML = `
            <div>저장 경로: <code>${escapeHtml(directory)}</code></div>
            <div class="mt-2">최신 백업 파일: ${escapeHtml(latest.file || '-')}</div>
            <div>생성 시각: ${escapeHtml(latest.time || '-')}</div>
            <div>용량: ${formatBytes(latest.size || 0)}</div>
        `;
    }

    function renderBackupFileList(files) {
        const box = document.getElementById('backup-file-list');
        if (!box) {
            return;
        }

        if (!Array.isArray(files) || files.length === 0) {
            box.textContent = '백업 파일이 없습니다.';
            return;
        }

        box.innerHTML = files.map((file, index) => `
            <div class="d-flex justify-content-between align-items-start gap-2 py-2 ${index === files.length - 1 ? '' : 'border-bottom'}">
                <div class="min-w-0">
                    <div class="fw-semibold text-dark text-break">${escapeHtml(file.file || '-')}</div>
                    <div class="text-muted">생성 시각: ${escapeHtml(file.time || '-')}</div>
                </div>
                <div class="text-nowrap text-muted">${formatBytes(file.size || 0)}</div>
            </div>
        `).join('');
    }

    function renderPrimaryRestoreOptions(files) {
        const select = document.getElementById('restore-primary-file');
        const button = document.getElementById('run-primary-restore');
        if (!select) {
            return;
        }

        const items = Array.isArray(files) ? files : [];
        if (items.length === 0) {
            select.innerHTML = '<option value="">복원 가능한 백업 파일이 없습니다.</option>';
            if (button) {
                button.disabled = true;
            }
            return;
        }

        select.innerHTML = items.map(file => `
            <option value="${escapeHtml(file.file || '')}">
                ${escapeHtml(file.file || '-')} / ${escapeHtml(file.time || '-')}
            </option>
        `).join('');

        if (button) {
            button.disabled = false;
        }
    }

    async function loadSyncInfo() {
        try {
            const json = await fetchJson(API.SYNC_INFO);
            if (!json?.success) {
                throw new Error(json?.message || 'DB 동기화 상태를 불러오지 못했습니다.');
            }

            const data = json.data || {};
            state.syncInfo = data;
            syncTargetSummary(data);
            renderSyncInfo(data);
            updateActionGuards();

            if (data.state === 'running') {
                startSyncPolling();
            } else {
                stopSyncPolling();
            }

            state.syncRequestPending = false;
        } catch (error) {
            notify('error', error.message || 'DB 동기화 상태 조회에 실패했습니다.');
        }
    }

    async function loadRestoreInfo() {
        try {
            const json = await fetchJson(API.RESTORE_INFO);
            if (!json?.success) {
              throw new Error(json?.message || 'DB 복원 상태를 불러오지 못했습니다.');
            }

            const data = json.data || {};
            state.restoreInfo = data;
            syncRestoreSummary(data);
            renderRestoreInfo(data);
            updateActionGuards();

            if (data.state === 'running') {
                startRestorePolling();
            } else {
                stopRestorePolling();
            }

            state.restoreRequestPending = false;
        } catch (error) {
            state.restoreRequestPending = false;
           notify('error', error.message || 'DB 복원 상태 조회에 실패했습니다.');
        }
    }

    function syncTargetSummary(data) {
        const activeLabel = formatSyncNodeLabel(data.active_db, 'Active DB');
        const standbyLabel = formatSyncNodeLabel(data.standby_db, 'Standby DB');
        state.syncActiveLabel = activeLabel;
        state.syncStandbyLabel = standbyLabel;

        setText('sync-target-summary', `현재 ${activeLabel}의 최신 백업을 ${standbyLabel}에 적용합니다.`);
        setText('sync-warning-summary', `현재 ${activeLabel}의 최신 백업을 ${standbyLabel}에 적용합니다.`);
    }

    function renderSyncInfo(data) {
        const box = document.getElementById('latest-sync-info');
        if (!box) {
            return;
        }

        const stateCode = String(data.state || 'idle').toUpperCase();
        const badgeClass = stateCode === 'SUCCESS'
            ? 'bg-success'
            : stateCode === 'FAILED'
                ? 'bg-danger'
                : stateCode === 'RUNNING'
                    ? 'bg-warning text-dark'
                    : 'bg-secondary';

        box.innerHTML = `
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge ${badgeClass}">${escapeHtml(formatSyncState(stateCode))}</span>
                <span>${escapeHtml(data.message || 'DB 동기화 이력이 없습니다.')}</span>
            </div>
            <div>현재 Active DB: ${escapeHtml(formatSyncNodeLabel(data.active_db, 'Active DB'))}</div>
            <div>현재 Standby DB: ${escapeHtml(formatSyncNodeLabel(data.standby_db, 'Standby DB'))}</div>
            <div>동기화 결과: ${escapeHtml(formatSyncResult(stateCode))}</div>
            <div>현재 단계: ${escapeHtml(formatSyncStage(data.stage_label || data.stage || '-'))}</div>
            <div>실행 statement 수: ${escapeHtml(formatCount(data.statement_count))}</div>
            <div>Snapshot 생성: ${escapeHtml(formatYesNo(data.snapshot_created))}</div>
            <div>Snapshot 파일: ${escapeHtml(data.snapshot_file || '-')}</div>
            <div>Rollback 수행: ${escapeHtml(formatYesNo(data.rollback_attempted))}</div>
            <div>Rollback 결과: ${escapeHtml(formatRollbackResult(data.rollback_attempted, data.rollback_success))}</div>
            <div>Rollback 메시지: ${escapeHtml(data.rollback_message || '-')}</div>
            <div>마지막 동기화 파일: ${escapeHtml(data.last_synced_file || '-')}</div>
            <div>마지막 동기화 시각: ${escapeHtml(data.last_synced_at || '-')}</div>
            <div>마지막 오류: ${escapeHtml(normalizeSyncError(data.last_error || '-'))}</div>
        `;
    }

    function syncRestoreSummary(data) {
        state.restoreActiveLabel = formatSyncNodeLabel(data.active_db, 'Active DB');
        setText('restore-warning-summary', `선택한 백업 파일을 ${state.restoreActiveLabel}에 복원합니다.`);
    }

    function renderRestoreInfo(data) {
        const box = document.getElementById('latest-restore-info');
        if (!box) {
            return;
        }

        const stateCode = String(data.state || 'idle').toUpperCase();
        const badgeClass = stateCode === 'SUCCESS'
            ? 'bg-success'
            : stateCode === 'FAILED'
                ? 'bg-danger'
                : stateCode === 'RUNNING'
                    ? 'bg-warning text-dark'
                    : 'bg-secondary';

        box.innerHTML = `
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge ${badgeClass}">${escapeHtml(formatRestoreState(stateCode))}</span>
                <span>${escapeHtml(data.message || 'DB 복원 이력이 없습니다.')}</span>
            </div>
            <div>현재 Active DB: ${escapeHtml(formatSyncNodeLabel(data.active_db, 'Active DB'))}</div>
            <div>현재 Standby DB: ${escapeHtml(formatSyncNodeLabel(data.standby_db, 'Standby DB'))}</div>
            <div>복원 결과: ${escapeHtml(formatRestoreResult(stateCode))}</div>
            <div>현재 단계: ${escapeHtml(formatRestoreStage(data.stage_label || data.stage || '-'))}</div>
            <div>실행 statement 수: ${escapeHtml(formatCount(data.statement_count))}</div>
            <div>마지막 복원 파일: ${escapeHtml(data.last_restored_file || '-')}</div>
            <div>마지막 복원 시각: ${escapeHtml(data.last_restored_at || '-')}</div>
            <div>마지막 오류: ${escapeHtml(normalizeSyncError(data.last_error || '-'))}</div>
        `;
    }

    function updateActionGuards() {
        const syncButton = document.getElementById('run-db-sync');
        const restoreButton = document.getElementById('run-primary-restore');
        const restoreSelect = document.getElementById('restore-primary-file');
        const switchButton = document.getElementById('switch-active-db-button');
        const switchHint = document.getElementById('switch-active-db-hint');

        const syncRunning = String(state.syncInfo?.state || '').toLowerCase() === 'running';
        const restoreRunning = String(state.restoreInfo?.state || '').toLowerCase() === 'running';
        const selectedFile = restoreSelect?.value || '';

        if (syncButton) {
            syncButton.disabled = syncRunning || restoreRunning || state.syncRequestPending;
            if (restoreRunning) {
                syncButton.title = 'DB 복원 진행 중에는 동기화를 실행할 수 없습니다.';
            } else if (syncRunning) {
                syncButton.title = 'DB 동기화가 이미 진행 중입니다.';
            } else {
                syncButton.removeAttribute('title');
            }
        }

        if (restoreButton) {
            restoreButton.disabled = syncRunning || restoreRunning || state.restoreRequestPending || !selectedFile;
            if (syncRunning) {
                restoreButton.title = 'DB 동기화 진행 중에는 복원을 실행할 수 없습니다.';
            } else if (restoreRunning) {
                restoreButton.title = 'DB 복원이 이미 진행 중입니다.';
            } else {
                restoreButton.removeAttribute('title');
            }
        }

        if (switchButton && !switchButton.classList.contains('d-none')) {
            const runtimeBlocked = syncRunning || restoreRunning;
            const runtimeMessage = syncRunning
                ? 'DB 동기화 진행 중에는 Active DB를 전환할 수 없습니다.'
                : restoreRunning
                    ? 'DB 복원 진행 중에는 Active DB를 전환할 수 없습니다.'
                    : '';

            if (runtimeBlocked) {
                switchButton.disabled = true;
                switchButton.title = runtimeMessage;
                if (switchHint) {
                    switchHint.textContent = runtimeMessage;
                    switchHint.classList.remove('d-none');
                }
            }
        }
    }

    function formatSyncNodeLabel(node, fallback) {
        if (!node || typeof node !== 'object') {
            return fallback;
        }

        const role = String(node.role || fallback);
        const port = Number(node.port || 0);
        return port > 0 ? `${role} (${port})` : role;
    }

    function formatSyncState(stateCode) {
        return ({
            RUNNING: '진행 중',
            STALE_SUSPECTED: '상태 확인 필요',
            SUCCESS: '성공',
            FAILED: '실패',
            IDLE: '대기'
        })[stateCode] || '대기';
    }

    function formatSyncResult(stateCode) {
        return ({
            RUNNING: '진행 중',
            STALE_SUSPECTED: '상태 확인 필요',
            SUCCESS: '동기화 성공',
            FAILED: '동기화 실패',
            IDLE: '실행 이력 없음'
        })[stateCode] || '실행 이력 없음';
    }

    function formatSyncStage(stageCode) {
        return ({
            'load-backup-file': 'load-backup-file',
            starting: 'starting',
            'load-standby-config': 'load-standby-config',
            'connect-standby': 'connect-standby',
            'snapshot-standby': 'snapshot-standby',
            'prepare-standby': 'prepare-standby',
            'apply-sql-by-pdo': 'apply-sql-by-pdo',
            'rollback-standby': 'rollback-standby',
            'stale-suspected': 'stale-suspected',
            completed: 'completed',
            timeout: 'timeout'
        })[String(stageCode || '')] || String(stageCode || '-');
    }

    function formatRestoreState(stateCode) {
        return ({
            RUNNING: '진행 중',
            SUCCESS: '성공',
            FAILED: '실패',
            IDLE: '대기'
        })[stateCode] || '대기';
    }

    function formatRestoreResult(stateCode) {
        return ({
            RUNNING: '진행 중',
            SUCCESS: '복원 성공',
            FAILED: '복원 실패',
            IDLE: '실행 이력 없음'
        })[stateCode] || '실행 이력 없음';
    }

    function formatRestoreStage(stageCode) {
        return ({
            'validate-backup-file': 'validate-backup-file',
            starting: 'starting',
            'prepare-active': 'prepare-active',
            'apply-sql-by-pdo': 'apply-sql-by-pdo',
            completed: 'completed',
            timeout: 'timeout'
        })[String(stageCode || '')] || String(stageCode || '-');
    }

    function normalizeSyncError(message) {
        const text = String(message || '-').replace(/^\uFEFF/, '');
        return text.replace(/\uFFFD+/g, '').trim() || '-';
    }

    function formatCount(value) {
        const number = Number(value || 0);
        return Number.isFinite(number) ? String(number) : '0';
    }

    function formatYesNo(value) {
        return value ? '예' : '아니오';
    }

    function formatRollbackResult(attempted, success) {
        if (!attempted) {
            return '미수행';
        }

        return success ? '성공' : '실패';
    }

    async function loadActivityLog() {
        try {
            const json = await fetchJson(API.LOG);
            if (!json?.success) {
                throw new Error(json?.message || '로그를 불러오지 못했습니다.');
            }

            setText('backup-log-viewer', json.data?.log || '로그가 없습니다.');
        } catch (error) {
            notify('error', error.message || '로그 조회에 실패했습니다.');
        }
    }

    function bindBackupForm() {
        const form = document.getElementById('backup-setting-form');
        const submitButton = document.getElementById('save-backup-settings');
        if (!form || !submitButton) {
            return;
        }

        form.addEventListener('submit', async event => {
            event.preventDefault();

            const retentionDays = Number(getValue('backup_retention_days'));
            if (!Number.isFinite(retentionDays) || retentionDays < 1 || retentionDays > 365) {
                notify('warning', '백업 보관 기간을 다시 확인해 주세요.');
                return;
            }

            const payload = {
                backup_auto_enabled: isAutoBackupEnabled() ? '1' : '0',
                backup_auto_trigger_mode: isAutoBackupEnabled() ? 'data-change' : 'manual',
                backup_auto_min_interval_hours: getIntervalHours(),
                backup_retention_days: retentionDays,
                backup_cleanup_enabled: isChecked('backup_cleanup_enabled') ? '1' : '0'
            };

            submitButton.disabled = true;

            try {
                const json = await fetchJson(API.SETTINGS_SAVE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (!json?.success) {
                    throw new Error(json?.message || '자동 백업 설정 저장에 실패했습니다.');
                }

                notify('success', json.message || '자동 백업 설정이 저장되었습니다.');
                await loadBackupSettings();
            } catch (error) {
               notify('error', error.message || '자동 백업 설정 저장에 실패했습니다.');
            } finally {
                submitButton.disabled = false;
            }
        });
    }

    function bindBackupRun() {
        const button = document.getElementById('run-backup-now');
        if (!button) {
            return;
        }

        button.addEventListener('click', async () => {
            button.disabled = true;
            showBackupRunResult('info', 'SQL 백업을 실행하고 있습니다.');

            try {
                const json = await fetchJson(API.BACKUP_RUN, { method: 'POST' });
                if (!json?.success) {
                    throw new Error(json?.message || 'SQL 백업 실행에 실패했습니다.');
                }

                const summary = [
                    'SQL 백업이 완료되었습니다.',
                    json.filename ? `파일: ${json.filename}` : '',
                    json.time ? `시각: ${json.time}` : ''
                ].filter(Boolean).join('\n');

                showBackupRunResult('success', summary);
                notify('success', json.message || 'SQL 백업이 완료되었습니다.');
                await Promise.all([loadBackupInfo(), loadActivityLog(), loadSyncInfo()]);
            } catch (error) {
                showBackupRunResult('error', error.message || 'SQL 백업 실행에 실패했습니다.');
                notify('error', error.message || 'SQL 백업 실행에 실패했습니다.');
            } finally {
                button.disabled = false;
            }
        });
    }

    function bindSyncRun() {
        const button = document.getElementById('run-db-sync');
        const confirmButton = document.getElementById('confirm-db-sync');
        const modalElement = document.getElementById('syncWarningModal');
        if (!button || !confirmButton || !modalElement) {
            return;
        }

        const modal = window.bootstrap?.Modal ? new window.bootstrap.Modal(modalElement) : null;

        button.addEventListener('click', () => {
            if (button.disabled) {
                return;
            }

            setText('sync-warning-summary', `현재 ${state.syncActiveLabel}의 최신 백업을 ${state.syncStandbyLabel}에 적용합니다.`);

            if (modal) {
                modal.show();
                return;
            }

            void runSync(confirmButton, null);
        });

        confirmButton.addEventListener('click', async () => {
            await runSync(confirmButton, modal);
        });
    }

    function bindRestoreRun() {
        const button = document.getElementById('run-primary-restore');
        const confirmButton = document.getElementById('confirm-db-restore');
        const modalElement = document.getElementById('restoreWarningModal');
        const select = document.getElementById('restore-primary-file');
        if (!button || !confirmButton || !modalElement || !select) {
            return;
        }

        const modal = window.bootstrap?.Modal ? new window.bootstrap.Modal(modalElement) : null;

        select.addEventListener('change', () => {
            if (!state.restoreRequestPending) {
                updateActionGuards();
            }
        });

        button.addEventListener('click', () => {
            if (button.disabled || !select.value) {
                return;
            }

            setText('restore-warning-summary', `선택한 백업 파일을 ${state.restoreActiveLabel}에 복원합니다.`);

            if (modal) {
                modal.show();
                return;
            }

            void runRestore(confirmButton, null);
        });

        confirmButton.addEventListener('click', async () => {
            await runRestore(confirmButton, modal);
        });
    }

    async function runSync(button, modal = null) {
        state.syncRequestPending = true;
        button.disabled = true;

        renderSyncInfo({
            state: 'running',
            message: 'DB 동기화를 시작하고 있습니다.',
            stage_label: 'load-backup-file',
            stage: 'load-backup-file',
            statement_count: 0,
            snapshot_created: false,
            snapshot_file: '-',
            rollback_attempted: false,
            rollback_success: false,
            rollback_message: '-',
            last_synced_file: '-',
            last_synced_at: '-',
            last_error: '-',
            active_db: buildSyncNode('Active DB', state.syncActiveLabel),
            standby_db: buildSyncNode('Standby DB', state.syncStandbyLabel)
        });
        state.syncInfo = {
            state: 'running'
        };
        updateActionGuards();
        startSyncPolling();

        try {
            const json = await fetchJson(API.SYNC_RUN, { method: 'POST' });
            if (!json?.success) {
                throw new Error(json?.message || 'DB 동기화 실행에 실패했습니다.');
            }

            if (modal) {
                modal.hide();
            }

            notify('success', json.message || 'DB 동기화가 시작되었습니다.');
            await Promise.all([loadSyncInfo(), loadActivityLog()]);
        } catch (error) {
            state.syncRequestPending = false;
            stopSyncPolling();
            state.syncInfo = {
                state: 'failed'
            };
            renderSyncInfo({
                state: 'failed',
                message: 'DB 동기화에 실패했습니다.',
                stage_label: '-',
                stage: '-',
                statement_count: 0,
                snapshot_created: false,
                snapshot_file: '-',
                rollback_attempted: false,
                rollback_success: false,
                rollback_message: '-',
                last_synced_file: '-',
                last_synced_at: '-',
                last_error: error.message || 'DB 동기화 실행에 실패했습니다.',
                active_db: buildSyncNode('Active DB', state.syncActiveLabel),
                standby_db: buildSyncNode('Standby DB', state.syncStandbyLabel)
            });
            updateActionGuards();
            notify('error', error.message || 'DB 동기화 실행에 실패했습니다.');
        } finally {
            if (!state.syncRequestPending) {
                button.disabled = false;
            }
        }
    }

    async function runRestore(button, modal = null) {
        const selectedFile = getValue('restore-primary-file');
        if (!selectedFile) {
            notify('warning', '복원할 백업 파일을 선택해 주세요.');
            return;
        }

        state.restoreRequestPending = true;
        button.disabled = true;

        renderRestoreInfo({
            state: 'running',
            message: 'DB 복원을 시작하고 있습니다.',
            stage_label: 'validate-backup-file',
            stage: 'validate-backup-file',
            statement_count: 0,
            last_restored_file: '-',
            last_restored_at: '-',
            last_error: '-',
            active_db: buildSyncNode('Active DB', state.restoreActiveLabel),
            standby_db: buildSyncNode('Standby DB', state.syncStandbyLabel)
        });
        state.restoreInfo = {
            state: 'running'
        };
        updateActionGuards();
        startRestorePolling();

        try {
            const json = await fetchJson(API.RESTORE_RUN, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file: selectedFile })
            });

            if (!json?.success) {
                throw new Error(json?.message || 'DB 복원 실행에 실패했습니다.');
            }

            if (modal) {
                modal.hide();
            }

            notify('success', json.message || 'DB 복원이 시작되었습니다.');
            await Promise.all([loadRestoreInfo(), loadActivityLog()]);
        } catch (error) {
            state.restoreRequestPending = false;
            stopRestorePolling();
            state.restoreInfo = {
                state: 'failed'
            };
            renderRestoreInfo({
                state: 'failed',
                message: 'DB 복원에 실패했습니다.',
                stage_label: '-',
                stage: '-',
                statement_count: 0,
                last_restored_file: '-',
                last_restored_at: '-',
                last_error: error.message || 'DB 복원 실행에 실패했습니다.',
                active_db: buildSyncNode('Active DB', state.restoreActiveLabel),
                standby_db: buildSyncNode('Standby DB', state.syncStandbyLabel)
            });
            updateActionGuards();
            notify('error', error.message || 'DB 복원 실행에 실패했습니다.');
        } finally {
            if (!state.restoreRequestPending) {
                button.disabled = false;
            }
        }
    }

    function buildSyncNode(defaultRole, label) {
        const match = String(label || '').match(/^(.+?) \((\d+)\)$/);
        if (!match) {
            return { role: defaultRole, label };
        }

        return {
            role: match[1],
            port: Number(match[2]),
            label
        };
    }

    function bindActiveSwitch() {
        const button = document.getElementById('switch-active-db-button');
        const confirmButton = document.getElementById('confirm-switch-active-db');
        const modalElement = document.getElementById('switchActiveDbModal');
        const summary = document.getElementById('switch-active-summary');

        if (!button || !confirmButton || !modalElement || !summary) {
            return;
        }

        const modal = window.bootstrap?.Modal ? new window.bootstrap.Modal(modalElement) : null;

        button.addEventListener('click', () => {
            if (!state.activeTarget) {
                return;
            }

            summary.textContent = state.activeTarget === 'secondary' ? '3306 → 3307' : '3307 → 3306';

            if (modal) {
                modal.show();
                return;
            }

            void switchActiveDatabase(confirmButton);
        });

        confirmButton.addEventListener('click', async () => {
            await switchActiveDatabase(confirmButton);
            if (modal) {
                modal.hide();
            }
        });
    }

    async function switchActiveDatabase(button) {
        if (!state.activeTarget) {
            return;
        }

        button.disabled = true;

        try {
            const json = await fetchJson(API.SWITCH_ACTIVE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ target: state.activeTarget })
            });

            if (!json?.success) {
                throw new Error(json?.message || 'Active DB 전환에 실패했습니다.');
            }

            notify('success', json.message || 'Active DB 전환이 완료되었습니다.');
            await Promise.all([loadDatabaseStatus(), loadSyncInfo(), loadRestoreInfo(), loadActivityLog()]);
        } catch (error) {
            notify('error', error.message || 'Active DB 전환에 실패했습니다.');
        } finally {
            button.disabled = false;
        }
    }

    function bindReloadButtons() {
        const statusButton = document.getElementById('reload-db-status');
        const logButton = document.getElementById('reload-backup-log');

        if (statusButton) {
            statusButton.addEventListener('click', () => {
                void Promise.all([loadDatabaseStatus(), loadSyncInfo()]);
            });
        }

        if (logButton) {
            logButton.addEventListener('click', () => {
                void loadActivityLog();
            });
        }
    }

    function bindCleanupToggle() {
        const cleanup = document.getElementById('backup_cleanup_enabled');
        if (!cleanup) {
            return;
        }

        cleanup.addEventListener('change', () => {
            syncCleanupUi();
        });
    }

    function bindBackupModeChange() {
        const radios = document.querySelectorAll('input[name="backup_auto_mode"]');
        radios.forEach(radio => {
            radio.addEventListener('change', () => {
                syncBackupModeUi();
                setPolicyEngineSummary(currentBackupMode(), getIntervalHours());
            });
        });

        const interval = document.getElementById('backup_auto_min_interval');
        if (interval) {
            interval.addEventListener('change', () => {
                setPolicyEngineSummary(currentBackupMode(), getIntervalHours());
            });
        }
    }

    function currentBackupMode() {
        const checked = document.querySelector('input[name="backup_auto_mode"]:checked');
        return checked?.value === 'auto' ? 'data-change' : 'manual';
    }

    function isAutoBackupEnabled() {
        return currentBackupMode() === 'data-change';
    }

    function setBackupMode(mode) {
        const normalized = mode === 'data-change' ? 'auto' : 'manual';
        const radio = document.querySelector(`input[name="backup_auto_mode"][value="${normalized}"]`);
        if (radio) {
            radio.checked = true;
        }
    }

    function syncBackupModeUi() {
        const autoEnabled = isAutoBackupEnabled();
        const interval = document.getElementById('backup_auto_min_interval');
        if (interval) {
            interval.disabled = !autoEnabled;
        }
    }

    function syncCleanupUi() {
        const help = document.getElementById('backup-cleanup-help');
        if (!help) {
            return;
        }

        help.textContent = isChecked('backup_cleanup_enabled')
            ? '보관 기간을 초과한 백업 파일은 자동으로 삭제됩니다.'
            : '자동 정리가 꺼져 있어 백업 파일이 계속 보관됩니다.';
    }

    function setRetentionDays(value) {
        const select = document.getElementById('backup_retention_days');
        if (select) {
            select.value = String(value);
        }
    }

    function setPolicyIntervalPreview(hours) {
        const select = document.getElementById('backup_auto_min_interval');
        if (select) {
            select.value = `${Number(hours) || 24}h`;
        }
    }

    function setPolicyEngineSummary(mode, hours) {
        const summary = document.getElementById('backup-policy-engine-summary');
        if (!summary) {
            return;
        }

        if (mode === 'data-change') {
            summary.textContent = `현재 자동 백업 정책: 데이터 변경 감지 후 최소 ${Number(hours) || 24}시간 간격으로 SQL 백업을 생성합니다.`;
            return;
        }

        summary.textContent = '현재 자동 백업 정책: 자동 백업이 꺼져 있으면 수동 백업만 사용합니다.';
    }

    function getIntervalHours() {
        const raw = getValue('backup_auto_min_interval');
        const match = String(raw).match(/(\d+)/);
        return match ? Number(match[1]) : 24;
    }

    function showBackupRunResult(type, message) {
        const box = document.getElementById('backup-run-result');
        if (!box) {
            return;
        }

        const className = type === 'success'
            ? 'alert alert-success'
            : type === 'error'
                ? 'alert alert-danger'
                : 'alert alert-info';

        box.innerHTML = `<div class="${className} small mb-0">${escapeHtml(message).replace(/\n/g, '<br>')}</div>`;
        box.style.maxHeight = '240px';
        box.style.opacity = '1';
        box.style.marginTop = '1rem';

        if (state.backupResultTimer) {
            window.clearTimeout(state.backupResultTimer);
        }

        if (type === 'success') {
            state.backupResultTimer = window.setTimeout(() => {
                box.style.maxHeight = '0';
                box.style.opacity = '0';
                box.style.marginTop = '0';
            }, 5000);
        }
    }

    function startSyncPolling() {
        if (state.syncPollTimer) {
            window.clearInterval(state.syncPollTimer);
            state.syncPollTimer = null;
        }

        state.syncPollTimer = window.setInterval(() => {
            void Promise.all([loadSyncInfo(), loadActivityLog()]);
        }, 1500);
    }

    function stopSyncPolling() {
        if (!state.syncPollTimer) {
            return;
        }

        window.clearInterval(state.syncPollTimer);
        state.syncPollTimer = null;
    }

    function startRestorePolling() {
        if (state.restorePollTimer) {
            window.clearInterval(state.restorePollTimer);
            state.restorePollTimer = null;
        }

        state.restorePollTimer = window.setInterval(() => {
            void Promise.all([loadRestoreInfo(), loadActivityLog()]);
        }, 1500);
    }

    function stopRestorePolling() {
        if (!state.restorePollTimer) {
            return;
        }

        window.clearInterval(state.restorePollTimer);
        state.restorePollTimer = null;
    }

    function setCheckbox(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.checked = String(value) === '1' || value === true || value === 1;
        }
    }

    function isChecked(id) {
        const element = document.getElementById(id);
        return Boolean(element?.checked);
    }

    function getValue(id) {
        const element = document.getElementById(id);
        return element?.value ?? '';
    }

    function setText(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatBytes(bytes) {
        const size = Number(bytes) || 0;
        if (size < 1024) {
            return `${size} B`;
        }
        if (size < 1024 * 1024) {
            return `${(size / 1024).toFixed(1)} KB`;
        }
        if (size < 1024 * 1024 * 1024) {
            return `${(size / (1024 * 1024)).toFixed(1)} MB`;
        }
        return `${(size / (1024 * 1024 * 1024)).toFixed(1)} GB`;
    }
})();

