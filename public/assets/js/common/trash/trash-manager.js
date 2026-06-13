(function () {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};
    const AppAjax = window.AppAjax || window.AppCore.AppAjax || {};
    const AppEvents = window.AppEvents || {};
    const AppModal = window.AppModal || {};
    const AppNotify = window.AppNotify || window.AppCore.AppNotify || {};
    const AppLoading = window.AppLoading || window.AppCore.AppLoading || {};

    const onDocument = AppEvents.onDocument || ((type, handler, options = false) => {
        document.addEventListener(type, handler, options);
        return () => document.removeEventListener(type, handler, options);
    });

    const onWindow = AppEvents.onWindow || ((type, handler, options = false) => {
        window.addEventListener(type, handler, options);
        return () => window.removeEventListener(type, handler, options);
    });

    const onShown = AppModal.onShown || ((handler, options = false) => {
        return onDocument('shown.bs.modal', handler, options);
    });

    function notify(type, message) {
        if (AppCore.notify) {
            AppCore.notify(type, message);
            return;
        }
        if (AppNotify.notify) {
            AppNotify.notify(type, message);
            return;
        }
        if (type === 'error') {
            console.error(message);
        } else {
            console.warn(message);
        }
    }

    function showGlobalLoading(message = '처리 중입니다...') {
        if (AppLoading?.showLoading) {
            return AppLoading.showLoading(message);
        }
        if (AppCore.showLoading) {
            return AppCore.showLoading(message);
        }
        const overlay = document.getElementById('global-loading-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
            return () => {};
        }
        return () => {};
    }

    function hideGlobalLoading(token = 'manual') {
        if (AppLoading?.hideLoading) {
            AppLoading.hideLoading(token);
            return;
        }
        if (AppCore.hideLoading) {
            AppCore.hideLoading(token);
            return;
        }
        const overlay = document.getElementById('global-loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    async function fetchJson(url, options = {}) {
        if (AppAjax.fetchJson) {
            return AppAjax.fetchJson(url, options);
        }

        const response = await fetch(url, options);
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || `요청 실패 (${response.status})`);
        }
        return json;
    }

    async function postJson(url, payload = {}) {
        if (AppAjax.postJson) {
            return AppAjax.postJson(url, payload);
        }

        return fetchJson(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });
    }

    async function postFormJson(url, payload = {}) {
        return fetchJson(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: new URLSearchParams(payload),
        });
    }

    function changedCount(json, key, fallback = 0) {
        const value = Number(json?.data?.[key] ?? fallback);
        return Number.isFinite(value) ? value : fallback;
    }

    function notifyActionResult(json, {
        successType = 'success',
        emptyType = 'warning',
        defaultMessage = '처리 완료',
        emptyMessage = '처리된 항목이 없습니다.',
        countKey = '',
    } = {}) {
        const count = countKey ? changedCount(json, countKey, json?.success === true ? 1 : 0) : 1;
        const message = json?.message || (count > 0 ? defaultMessage : emptyMessage);
        notify(count > 0 ? successType : emptyType, message);
        return count;
    }

    const trashCacheMap = new Map();
    const busyTrashModals = new WeakSet();
    const trashRowDataMap = new WeakMap();

    const scheduleInitialTrashButtonRefresh = () => {
        void refreshAllTrashButtons();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleInitialTrashButtonRefresh, { once: true });
    } else {
        window.setTimeout(scheduleInitialTrashButtonRefresh, 0);
    }

    onShown((event) => {
        const modal = event.target;
        if (!modal.classList?.contains('modal')) return;
        if (!modal.dataset.listUrl) return;

        const layout = modal.querySelector('.trash');
        const detail = modal.querySelector('.trash-detail');
        const tbody = modal.querySelector('.trash-table tbody');
        updateTrashModalTitle(modal);

        if (layout) layout.classList.remove('open');
        if (detail) detail.style.display = 'none';
        if (tbody) tbody.innerHTML = '';

        modal.querySelectorAll('.trash-check').forEach((cb) => {
            cb.checked = false;
        });

        const checkAll = modal.querySelector('.trash-check-all');
        if (checkAll) checkAll.checked = false;

        void loadTrash(modal);
    });

    onDocument('click', (event) => {
        const btn = event.target.closest('[data-bs-target]');
        if (!btn) return;

        const modal = document.querySelector(btn.getAttribute('data-bs-target'));
        if (!modal || !modal.dataset.listUrl) return;

        void preloadTrash(modal);
    });

    onDocument('click', (event) => {
        const modal = event.target.closest('.modal');
        if (!modal) return;

        const layout = modal.querySelector('.trash');
        const detail = modal.querySelector('.trash-detail');
        const table = modal.querySelector('.trash-table');

        if (!layout || !detail || !table) return;

        const row = event.target.closest('tr');
        const btn = event.target.closest('button');
        const cb = event.target.closest('.trash-check, .trash-check-all');

        if (btn || cb) return;
        if (event.target.closest('.trash-detail')) return;

        if (row && row.closest('.trash-table')) {
            table.querySelectorAll('tbody tr').forEach((node) => node.classList.remove('active'));
            row.classList.add('active');
            layout.classList.add('open');
            detail.style.display = 'block';

            const data = trashRowDataMap.get(row)
                || (row.dataset.row ? decodeRowData(row.dataset.row) : {});

            document.dispatchEvent(new CustomEvent('trash:detail-render', {
                detail: { data, modal, type: modal.dataset.type },
            }));
            return;
        }

        layout.classList.remove('open');
        detail.style.display = 'none';
    });

    onWindow('keydown', (event) => {
        if (event.key !== 'Escape') return;

        const modal = document.querySelector('.modal.show');
        if (!modal) return;

        const layout = modal.querySelector('.trash');
        const detail = modal.querySelector('.trash-detail');

        if (layout) layout.classList.remove('open');
        if (detail) detail.style.display = 'none';
    });

    onDocument('click', async (event) => {
        const modal = event.target.closest('.modal');
        if (!modal) return;
        if (busyTrashModals.has(modal)) {
            event.preventDefault();
            event.stopPropagation();
            return;
        }

        const restoreUrl = modal.dataset.restoreUrl;
        const deleteUrl = modal.dataset.deleteUrl;
        const deleteAllUrl = modal.dataset.deleteAllUrl;

        const restoreBtn = event.target.closest('.btn-restore');
        if (restoreBtn) {
            event.preventDefault();
            event.stopPropagation();

            const id = restoreBtn.dataset.id;
            if (!id || !confirm('복원하시겠습니까?')) return;
            await runTrashAction(modal, '복원 처리 중입니다...', async () => {
                const json = await postFormJson(restoreUrl, actionPayload(modal, { id }));
                if (json.success === false) {
                    throw new Error(json.message || '복원 실패');
                }
                notifyActionResult(json, {
                    defaultMessage: '복원 완료',
                    emptyMessage: '복원된 항목이 없습니다.',
                    countKey: 'restored_count',
                });
                await triggerChange(modal);
            });
            return;
        }

        const deleteBtn = event.target.closest('.btn-purge');
        if (deleteBtn) {
            event.preventDefault();
            event.stopPropagation();

            const id = deleteBtn.dataset.id;
            if (!id || !confirm('영구삭제하시겠습니까?')) return;
            await runTrashAction(modal, '영구삭제 처리 중입니다...', async () => {
                const json = await postFormJson(deleteUrl, actionPayload(modal, { id }));
                if (json.success === false) {
                    throw new Error(json.message || '영구삭제 실패');
                }
                notifyActionResult(json, {
                    defaultMessage: '영구삭제 완료',
                    emptyMessage: '영구삭제된 항목이 없습니다.',
                    countKey: 'deleted_count',
                });
                await triggerChange(modal);
            });
            return;
        }

        if (event.target.closest('.btn-restore-selected')) {
            const ids = getSelectedIds(modal, 'restore');
            if (!ids.length) {
                notify('warning', '선택된 항목이 없습니다.');
                return;
            }
            if (!confirm('선택 항목을 복원하시겠습니까?')) return;

            await runTrashAction(modal, `선택 복원 처리 중입니다... (${ids.length}건)`, async () => {
                const json = await postJson(actionUrl(modal, 'restoreBulk'), actionPayload(modal, { ids }));
                if (json.success === false) {
                    throw new Error(json.message || '선택 복원 실패');
                }
                notifyActionResult(json, {
                    defaultMessage: '선택 복원 완료',
                    emptyMessage: '복원된 항목이 없습니다.',
                    countKey: 'restored_count',
                });
                await triggerChange(modal);
            });
            return;
        }

        if (event.target.closest('.btn-delete-selected')) {
            const ids = getSelectedIds(modal, 'delete');
            if (!ids.length) {
                notify('warning', '선택된 항목이 없습니다.');
                return;
            }
            if (!confirm('선택 항목을 영구삭제하시겠습니까?')) return;

            await runTrashAction(modal, `선택 영구삭제 처리 중입니다... (${ids.length}건)`, async () => {
                const json = await postJson(actionUrl(modal, 'deleteBulk'), actionPayload(modal, { ids }));
                if (json.success === false) {
                    throw new Error(json.message || '선택 영구삭제 실패');
                }
                notifyActionResult(json, {
                    defaultMessage: '선택 영구삭제 완료',
                    emptyMessage: '영구삭제된 항목이 없습니다.',
                    countKey: 'deleted_count',
                });
                await triggerChange(modal);
            });
            return;
        }

        if (event.target.closest('.btn-restore-all')) {
            if (!confirm('전체 항목을 복원하시겠습니까?')) return;

            await runTrashAction(modal, '전체 복원 처리 중입니다...', async () => {
                const json = await postJson(actionUrl(modal, 'restoreAll'), actionPayload(modal));
                if (json.success === false) {
                    throw new Error(json.message || '전체 복원 실패');
                }
                notifyActionResult(json, {
                    defaultMessage: '전체 복원 완료',
                    emptyMessage: '복원된 항목이 없습니다.',
                    countKey: 'restored_count',
                });
                await triggerChange(modal);
            });
            return;
        }

        if (event.target.closest('.btn-delete-all')) {
            if (!confirm('전체 항목을 영구삭제하시겠습니까?')) return;

            await runTrashAction(modal, '전체 영구삭제 처리 중입니다...', async () => {
                const json = await postJson(actionUrl(modal, 'deleteAll'), actionPayload(modal));
                if (json.success === false) {
                    throw new Error(json.message || '전체 영구삭제 실패');
                }
                notifyActionResult(json, {
                    defaultMessage: '전체 영구삭제 완료',
                    emptyMessage: '영구삭제된 항목이 없습니다.',
                    countKey: 'deleted_count',
                });
                await triggerChange(modal);
            });
        }
    });

    onDocument('change', (event) => {
        const checkAll = event.target.closest('.trash-check-all');
        if (!checkAll) return;

        const modal = event.target.closest('.modal');
        if (!modal) return;
        if (busyTrashModals.has(modal)) {
            checkAll.checked = !checkAll.checked;
            return;
        }

        modal.querySelectorAll('.trash-check').forEach((checkbox) => {
            checkbox.checked = checkAll.checked;
        });
    });

    onDocument('trash:changed', (event) => {
        const { listUrl } = event.detail || {};
        if (listUrl) {
            trashCacheMap.delete(listUrl);
        }
        void refreshAllTrashButtons({ force: true });
    });

    onDocument('datatable:soft-delete-completed', () => {
        markTrashButtonsHasData();
        void refreshAllTrashButtons({ force: true });
    });

    function updateTrashModalTitle(modal) {
        const title = modal?.querySelector('.modal-title');
        if (!title) return;
        if (!modal.dataset.baseTitle) {
            modal.dataset.baseTitle = title.textContent.trim();
        }
        const scopedTitle = String(modal.dataset.trashTitle || '').trim();
        title.textContent = scopedTitle || modal.dataset.baseTitle;
    }

    function actionPayload(modal, extra = {}) {
        const payload = { ...extra };
        const importType = String(modal?.dataset.importType || '').trim();
        if (importType) {
            payload.import_type = importType;
            payload.data_type = importType;
        }
        return payload;
    }

    function actionUrl(modal, action) {
        const restoreUrl = modal.dataset.restoreUrl || '';
        const deleteUrl = modal.dataset.deleteUrl || '';
        const urls = {
            restoreBulk: modal.dataset.restoreBulkUrl || (restoreUrl ? `${restoreUrl}-bulk` : ''),
            restoreAll: modal.dataset.restoreAllUrl || (restoreUrl ? `${restoreUrl}-all` : ''),
            deleteBulk: modal.dataset.deleteBulkUrl || (deleteUrl ? `${deleteUrl}-bulk` : ''),
            deleteAll: modal.dataset.deleteAllUrl || (deleteUrl ? `${deleteUrl}-all` : ''),
        };
        return urls[action] || '';
    }

    function setTrashBusy(modal, busy = false) {
        if (!modal) return;
        if (busy) {
            busyTrashModals.add(modal);
        } else {
            busyTrashModals.delete(modal);
        }

        modal.classList.toggle('trash-action-busy', busy);
        modal.querySelectorAll('button, .trash-check, .trash-check-all').forEach((node) => {
            node.disabled = busy;
            node.classList.toggle('disabled', busy);
            node.setAttribute('aria-disabled', busy ? 'true' : 'false');
        });
    }

    async function runTrashAction(modal, message, callback) {
        if (!modal || busyTrashModals.has(modal)) return null;

        const releaseLoading = showGlobalLoading(message);
        setTrashBusy(modal, true);

        try {
            return await callback();
        } catch (error) {
            console.error(error);
            notify('error', error?.message || '요청 처리 중 오류가 발생했습니다.');
            return null;
        } finally {
            if (typeof releaseLoading === 'function') {
                releaseLoading();
            } else {
                hideGlobalLoading('manual');
            }
            setTrashBusy(modal, false);
        }
    }

    async function loadTrash(modal) {
        const listUrl = modal.dataset.listUrl;
        const tbody = modal.querySelector('.trash-table tbody');
        if (!tbody || !listUrl) return;

        const cached = trashCacheMap.get(listUrl);
        if (cached) {
            renderRows(tbody, cached, modal);
            updateTrashButtons(modal, cached);
        } else {
            tbody.innerHTML = `<tr><td colspan="${getColumnCount(modal)}">로딩 중입니다...</td></tr>`;
        }

        try {
            const json = await fetchJson(listUrl);
            const rows = json.success ? (json.data || []) : [];
            trashCacheMap.set(listUrl, rows);
            renderRows(tbody, rows, modal);
            updateTrashButtons(modal, rows);
        } catch (error) {
            console.error(error);
            tbody.innerHTML = `<tr><td colspan="${getColumnCount(modal)}">네트워크 오류</td></tr>`;
        }
    }

    async function preloadTrash(modal) {
        const listUrl = modal.dataset.listUrl;
        if (!listUrl) return [];

        try {
            const json = await fetchJson(listUrl);
            if (json.success) {
                const rows = json.data || [];
                trashCacheMap.set(listUrl, rows);
                updateTrashButtons(modal, rows);
                return rows;
            }
        } catch (error) {
            console.error(error);
        }
        return [];
    }

    function decodeRowData(raw) {
        if (!raw) return {};
        try {
            return JSON.parse(decodeURIComponent(raw));
        } catch (_error) {
            return {};
        }
    }

    function triggerChange(modal) {
        document.dispatchEvent(new CustomEvent('trash:changed', {
            detail: {
                type: modal.dataset.type,
                listUrl: modal.dataset.listUrl,
            },
        }));
        return loadTrash(modal);
    }

    function getSelectedIds(modal, mode) {
        const selector = mode === 'restore' ? '.btn-restore' : '.btn-purge';
        return [...modal.querySelectorAll('.trash-check:checked')]
            .map((checkbox) => checkbox.closest('tr')?.querySelector(selector)?.dataset.id)
            .filter(Boolean);
    }

    async function refreshAllTrashButtons({ force = false } = {}) {
        const modals = Array.from(document.querySelectorAll('.modal[data-list-url]'));
        for (const modal of modals) {
            const listUrl = modal.dataset.listUrl;
            if (!listUrl) continue;
            if (force) {
                trashCacheMap.delete(listUrl);
            }
            const rows = await preloadTrash(modal);
            updateTrashButtons(modal, rows);
        }
    }

    function markTrashButtonsHasData(count = 1) {
        findTrashButtons().forEach((button) => {
            setTrashButtonState(button, true, count);
        });
    }

    function updateTrashButtons(modal, rows = []) {
        const hasTrash = rows.length > 0;
        findTrashButtons(modal).forEach((button) => {
            setTrashButtonState(button, hasTrash, rows.length);
        });
    }

    function setTrashButtonState(button, hasTrash, count = 0) {
        if (!button) return;
        button.classList.toggle('btn-trash-has-data', hasTrash);
        button.setAttribute('aria-label', hasTrash ? `휴지통 ${count}건` : '휴지통');
        button.title = hasTrash ? `휴지통 ${count}건` : '휴지통';
    }

    function findTrashButtons(modal) {
        const selectors = ['.dt-trash-btn', '[class*="trash-btn"]'];
        if (modal?.id) {
            selectors.push(`[data-bs-target="#${CSS.escape(modal.id)}"]`, `[data-trash-modal="#${CSS.escape(modal.id)}"]`);
        }
        if (modal?.dataset?.type) {
            selectors.push(`.${CSS.escape(modal.dataset.type)}-trash-btn`);
        }

        const matches = Array.from(document.querySelectorAll(selectors.join(',')));
        const fallbackMatches = Array.from(document.querySelectorAll('.dt-button, button, a')).filter((node) => {
            return node instanceof HTMLElement && String(node.textContent || '').trim() === '휴지통';
        });

        return Array.from(new Set([...matches, ...fallbackMatches])).filter((node) => node instanceof HTMLElement);
    }

    function renderRows(tbody, data, modal) {
        tbody.innerHTML = '';
        if (!data.length) {
            tbody.innerHTML = `<tr><td colspan="${getColumnCount(modal)}">항목이 없습니다.</td></tr>`;
            return;
        }

        const fragment = document.createDocumentFragment();
        data.forEach((row) => {
            const tr = document.createElement('tr');
            trashRowDataMap.set(tr, row);
            tr.innerHTML = `<td><input type="checkbox" class="trash-check"></td>${getColumns(row, modal)}`;
            fragment.appendChild(tr);
        });
        tbody.appendChild(fragment);
    }

    function getColumns(row, modal) {
        const fn = window.TrashColumns?.[modal.dataset.type];
        return typeof fn === 'function' ? fn(row) : `<td>${row.id}</td>`;
    }

    function getColumnCount(modal) {
        return modal.querySelectorAll('.trash-table thead th').length || 1;
    }

    window.TrashManager = window.TrashManager || {};
    window.TrashManager.loadTrash = loadTrash;
    window.TrashManager.preloadTrash = preloadTrash;
    window.TrashManager.cachedRows = (listUrl) => trashCacheMap.get(listUrl) || [];
    window.TrashManager.refreshButtons = refreshAllTrashButtons;
    window.TrashManager.markButtonsHasData = markTrashButtonsHasData;
})();
