/**
 * Common DataTable core factory
 * - Creates and initializes shared DataTable instances.
 * - Keeps the legacy file name while centralizing table behavior.
 */
// Path: /assets/js/common/table/data-table.js
import { createTableInteraction } from './index.js';
import { attachDataTableSettings, prepareDataTableSettingsColumns, updateDataTableSettingsState } from '../datatable/dataTableSettings.js';

const __dtAdjustState = new WeakMap();
const __dtInstances = new Set();
const __dtResizeState = new WeakMap();
const DEFAULT_PAGE_LENGTH = 100;
const PAGE_LENGTH_MENU = [100, 200, 300, 500, 1000, 2000, 3000, 5000, 10000];
const DELETE_PROGRESS_CHUNK_SIZE = 500;
const AppCore = window.AppCore = window.AppCore || {};
const AppAjax = window.AppAjax || window.AppCore.AppAjax || {};
const AppNotify = window.AppNotify || window.AppCore.AppNotify || {};
const AppLoading = window.AppLoading || window.AppCore.AppLoading || {};
const AppDom = window.AppDom || {};
const AppEvents = window.AppEvents || {};

const onWindow = AppEvents.onWindow || ((type, handler, options = false) => {
    window.addEventListener(type, handler, options);
    return () => window.removeEventListener(type, handler, options);
});

const UTILITY_COLUMN_WIDTHS = {
    select: '36px',
    reorder: '36px',
    sequence: '48px',
    status: '72px',
    action: '56px',
};

function tokenizeClasses(...values) {
    return values
        .flatMap((value) => String(value || '').split(/\s+/))
        .map((token) => token.trim())
        .filter(Boolean);
}

function applyColumnHeaderClasses(table, columns = []) {
    if (!table || !Array.isArray(columns) || columns.length === 0) {
        return;
    }

    const originalHeaders = Array.from(table.table().header()?.querySelectorAll('th') || []);

    columns.forEach((column, index) => {
        const classes = tokenizeClasses(column.className, column.headerClassName);
        if (classes.length === 0) {
            return;
        }

        [originalHeaders[index], getScrollHeaderNodeByIndex(table, index)].forEach((headerNode) => {
            if (!headerNode) return;
            headerNode.classList.add(...classes);
        });
    });
}

function joinClasses(...values) {
    return Array.from(new Set(tokenizeClasses(...values))).join(' ');
}

function bindButtonCollectionLayerRoot() {
    if (window.__erpDtButtonLayerRootBound) {
        return;
    }

    window.__erpDtButtonLayerRootBound = true;

    const shouldMoveToBody = (node) => {
        if (!(node instanceof HTMLElement)) {
            return false;
        }

        if (node.matches('div.dt-button-background')) {
            return true;
        }

        return node.matches('div.dt-button-collection.fixed');
    };

    const moveLayersToBody = () => {
        if (!document.body) {
            return;
        }

        document.querySelectorAll('div.dt-button-background, div.dt-button-collection.fixed').forEach((node) => {
            if (shouldMoveToBody(node) && node.parentElement !== document.body) {
                document.body.appendChild(node);
            }
        });
    };

    const scheduleMove = () => {
        window.requestAnimationFrame(moveLayersToBody);
        window.setTimeout(moveLayersToBody, 0);
        window.setTimeout(moveLayersToBody, 50);
    };

    document.addEventListener('click', (event) => {
        if (event.target?.closest?.('.buttons-collection, .buttons-colvis, div.dt-button-collection, div.dt-button-background')) {
            scheduleMove();
        }
    }, true);

    const observer = new MutationObserver((mutations) => {
        if (mutations.some((mutation) => Array.from(mutation.addedNodes || []).some(shouldMoveToBody))) {
            scheduleMove();
        }
    });

    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            observer.observe(document.body, { childList: true, subtree: true });
        }, { once: true });
    }
}

bindButtonCollectionLayerRoot();

function isReorderColumn(column = {}) {
    const classes = tokenizeClasses(column.className, column.headerClassName);
    return classes.includes('reorder-handle') || classes.includes('drag-handle') || classes.includes('col-reorder');
}

function isSequenceColumn(column = {}) {
    const title = String(column.title || '').trim();
    return column.data === 'sort_no' || title === '\uC21C\uBC88';
}

function isStatusColumn(column = {}) {
    const title = String(column.title || '').trim();
    return column.data === 'is_active' || title === '\uC0C1\uD0DC' || title === '\uC9C4\uD589\uC0C1\uD669';
}

function isActionColumn(column = {}) {
    const title = String(column.title || '').trim();
    return column.data == null && (title === '\uAD00\uB9AC' || title === '\uC218\uC815');
}

function getUtilityColumnSettingsKey(column = {}) {
    if (column.isSelectionColumn === true || tokenizeClasses(column.className, column.headerClassName).includes('dt-select-column')) {
        return '__select';
    }
    if (isReorderColumn(column)) {
        return '__reorder';
    }
    if (isActionColumn(column)) {
        return '__actions';
    }

    return '';
}

function withUtilityColumnDefaults(column = {}) {
    const next = { ...column };
    let utilityClass = '';
    let width = '';

    if (next.isSelectionColumn === true || tokenizeClasses(next.className, next.headerClassName).includes('dt-select-column')) {
        utilityClass = 'dt-select-column';
        width = UTILITY_COLUMN_WIDTHS.select;
    } else if (isReorderColumn(next)) {
        utilityClass = 'dt-reorder-column';
        width = UTILITY_COLUMN_WIDTHS.reorder;
    } else if (isSequenceColumn(next)) {
        utilityClass = 'dt-sequence-column';
        width = UTILITY_COLUMN_WIDTHS.sequence;
    } else if (isStatusColumn(next)) {
        utilityClass = 'dt-status-column';
        width = UTILITY_COLUMN_WIDTHS.status;
    } else if (isActionColumn(next)) {
        utilityClass = 'dt-action-column';
        width = UTILITY_COLUMN_WIDTHS.action;
        next.orderable = false;
        next.searchable = false;
    }

    if (!utilityClass) {
        return next;
    }

    next.width = width;
    const utilitySettingsKey = getUtilityColumnSettingsKey(next);
    if (utilitySettingsKey !== '' && typeof next.settingsKey !== 'string') {
        next.settingsKey = utilitySettingsKey;
    }
    if (typeof next.widthResizable !== 'boolean') {
        next.widthResizable = true;
    }
    next.className = joinClasses(next.className, utilityClass, 'text-center');
    next.headerClassName = joinClasses(next.headerClassName, utilityClass, 'text-center');

    return next;
}

function normalizeUtilityColumns(columns = []) {
    return columns.map(withUtilityColumnDefaults);
}

async function postFormJson(url, data = {}) {
    if (typeof AppAjax.postForm === 'function') {
        return AppAjax.postForm(url, data);
    }

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams(data),
    });

    return response.json();
}

async function postBulkDeleteJson(url, ids = []) {
    if (typeof AppAjax.postBulkJson === 'function') {
        return AppAjax.postBulkJson(url, ids);
    }

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            ids,
            seed_row_ids: ids,
            evidence_ids: ids,
        }),
    });

    return response.json();
}

function showGlobalLoading(message = '\uCC98\uB9AC \uC911\uC785\uB2C8\uB2E4...') {
    if (typeof AppLoading.show === 'function') {
        return AppLoading.show(message);
    }

    if (typeof AppCore.showLoading === 'function') {
        return AppCore.showLoading(message);
    }

    if (typeof AppCore.show === 'function') {
        return AppCore.show(message);
    }

    const overlay = document.getElementById('global-loading-overlay');
    if (overlay) {
        overlay.style.display = 'flex';
    }
}

function hideGlobalLoading() {
    if (typeof AppLoading.hide === 'function') {
        return AppLoading.hide();
    }

    if (typeof AppCore.hideLoading === 'function') {
        return AppCore.hideLoading();
    }

    if (typeof AppCore.hide === 'function') {
        return AppCore.hide();
    }

    const overlay = document.getElementById('global-loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function notify(type = 'info', message = '') {
    if (typeof AppCore.notify === 'function') {
        return AppCore.notify(type, message);
    }

    if (typeof AppNotify.notify === 'function') {
        return AppNotify.notify(type, message);
    }

    if (type === 'error') {
        console.error(message);
    } else {
        console.warn(message);
    }
}

function ensureDeleteProgressPanel() {
    let panel = document.getElementById('dt-delete-progress-panel');
    if (panel) {
        return panel;
    }

    panel = document.createElement('div');
    panel.id = 'dt-delete-progress-panel';
    panel.className = 'dt-delete-progress-panel';
    panel.innerHTML = `
        <div class="dt-delete-progress-card" role="status" aria-live="polite">
            <div class="dt-delete-progress-head">
                <strong data-dt-delete-title>\uC0AD\uC81C \uCC98\uB9AC \uC911</strong>
                <span data-dt-delete-percent>0%</span>
            </div>
            <div class="dt-delete-progress-bar" aria-hidden="true">
                <span data-dt-delete-bar></span>
            </div>
            <div class="dt-delete-progress-meta">
                <span data-dt-delete-count>0 / 0\uAC74</span>
                <span data-dt-delete-step>\uC900\uBE44 \uC911</span>
            </div>
        </div>
    `;
    document.body.appendChild(panel);

    if (!document.getElementById('dt-delete-progress-style')) {
        const style = document.createElement('style');
        style.id = 'dt-delete-progress-style';
        style.textContent = `
            .dt-delete-progress-panel {
                position: fixed;
                inset: 0;
                z-index: 2100;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: rgba(15, 23, 42, 0.28);
            }
            .dt-delete-progress-panel.is-active {
                display: flex;
            }
            .dt-delete-progress-card {
                width: min(420px, 100%);
                border: 1px solid #d9e2ef;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.22);
                padding: 18px 20px;
                color: #111827;
            }
            .dt-delete-progress-head,
            .dt-delete-progress-meta {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }
            .dt-delete-progress-head strong {
                font-size: 16px;
                font-weight: 700;
            }
            .dt-delete-progress-head span {
                font-size: 18px;
                font-weight: 700;
                color: #2563eb;
            }
            .dt-delete-progress-bar {
                height: 10px;
                margin: 14px 0 10px;
                overflow: hidden;
                border-radius: 999px;
                background: #e5e7eb;
            }
            .dt-delete-progress-bar span {
                display: block;
                width: 0%;
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb, #10b981);
                transition: width 160ms ease;
            }
            .dt-delete-progress-meta {
                font-size: 13px;
                color: #4b5563;
            }
        `;
        document.head.appendChild(style);
    }

    return panel;
}

function createPageLoadingHandle({ enabled = true, selector = '', message = '' } = {}) {
    if (!enabled || typeof window.PageLoadingSpinner?.hold !== 'function') {
        return {
            release() {
                return undefined;
            },
        };
    }

    const holdToken = `datatable:${selector}:${Date.now()}`;
    let releaseHold = null;
    let isHeld = false;
    let isReleased = false;

    const released = window.PageLoadingSpinner.hold(holdToken, message);
    isHeld = true;
    releaseHold = typeof released === 'function' ? released : null;
    if (!isHeld || typeof releaseHold !== 'function') {
        isHeld = false;
    }

    const release = () => {
        if (isReleased) return;

        isReleased = true;

        if (!isHeld || typeof releaseHold !== 'function') return;

        isHeld = false;
        releaseHold();
        releaseHold = null;
    };

    return { release };
}

const dataTableModuleLoadingHandle = createPageLoadingHandle({
    enabled: true,
    selector: 'module',
    message: '\uB370\uC774\uD130\uB97C \uBD88\uB7EC\uC624\uB294 \uC911\uC785\uB2C8\uB2E4...',
});

function updateDeleteProgress({ total = 0, processed = 0, step = '\uCC98\uB9AC \uC911' } = {}) {
    const panel = ensureDeleteProgressPanel();
    const safeTotal = Math.max(0, Number(total) || 0);
    const safeProcessed = Math.min(safeTotal, Math.max(0, Number(processed) || 0));
    const percent = safeTotal > 0 ? Math.round((safeProcessed / safeTotal) * 100) : 0;

    panel.classList.add('is-active');
    panel.querySelector('[data-dt-delete-percent]').textContent = `${percent}%`;
    panel.querySelector('[data-dt-delete-count]').textContent = `${safeProcessed.toLocaleString('ko-KR')} / ${safeTotal.toLocaleString('ko-KR')}\uAC74`;
    panel.querySelector('[data-dt-delete-step]').textContent = step;
    panel.querySelector('[data-dt-delete-bar]').style.width = `${percent}%`;
}

function hideDeleteProgress() {
    const panel = document.getElementById('dt-delete-progress-panel');
    if (!panel) return;
    panel.classList.remove('is-active');
}

function setDataTableDeleteBusy(table, busy = false, buttonNode = null) {
    const wrapper = table?.table?.().container?.();
    const tableNode = table?.table?.().node?.();
    const selectedCount = typeof table?.getSelectedIds === 'function' ? table.getSelectedIds().length : 0;
    if (tableNode) {
        tableNode.dataset.dtDeleting = busy ? 'true' : 'false';
    }
    wrapper?.classList.toggle('dt-action-busy', busy);

    const deleteButtons = wrapper ? Array.from(wrapper.querySelectorAll('.dt-soft-delete-btn')) : [];
    const checkboxes = wrapper ? Array.from(wrapper.querySelectorAll('.dt-row-select, .dt-select-all')) : [];
    const controls = [...deleteButtons, ...checkboxes, ...(buttonNode ? [buttonNode] : [])];

    controls.forEach((control) => {
        if (!control) return;
        control.disabled = busy;
        if (control.classList.contains('dt-soft-delete-btn')) {
            const disabled = busy || selectedCount === 0;
            control.classList.toggle('disabled', disabled);
            control.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            return;
        }
        control.classList.toggle('disabled', busy);
        control.setAttribute('aria-disabled', busy ? 'true' : 'false');
    });
}

function reloadDataTable(table) {
    if (!table?.ajax?.reload) {
        table?.draw?.(false);
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        table.ajax.reload(() => resolve(), false);
    });
}

async function softDeleteSelectedRows({ deleteApi, ids, table, selectedIds, buttonNode, bulkDelete = false }) {
    if (!deleteApi) {
        return false;
    }

    if (ids.length === 0) {
        const message = '\uC0AD\uC81C\uD560 \uD589\uC744 \uC120\uD0DD\uD558\uC138\uC694.';
        notify('error', message);
        return true;
    }

    if (!window.confirm(`\uC120\uD0DD\uD55C ${ids.length}\uAC74\uC744 \uC0AD\uC81C\uD558\uC2DC\uACA0\uC2B5\uB2C8\uAE4C?`)) {
        return true;
    }

    setDataTableDeleteBusy(table, true, buttonNode);
    updateDeleteProgress({ total: ids.length, processed: 0, step: '\uC0AD\uC81C \uC694\uCCAD \uC900\uBE44 \uC911' });
    let deletedCount = 0;
    let skippedCount = 0;
    let lastMessage = '';

    try {
        if (bulkDelete) {
            let processed = 0;
            const chunks = [];
            for (let index = 0; index < ids.length; index += DELETE_PROGRESS_CHUNK_SIZE) {
                chunks.push(ids.slice(index, index + DELETE_PROGRESS_CHUNK_SIZE));
            }

            for (const [index, chunk] of chunks.entries()) {
                updateDeleteProgress({
                    total: ids.length,
                    processed,
                    step: `${index + 1} / ${chunks.length} \uBB36\uC74C \uCC98\uB9AC \uC911`,
                });
                const result = await postBulkDeleteJson(deleteApi, chunk);
                if (!result?.success) {
                    notify('error', result?.message || '\uC0AD\uC81C\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
                    return true;
                }
                const data = result?.data || {};
                const chunkDeleted = Number(data.deleted_count ?? (data.skipped_count ? 0 : chunk.length)) || 0;
                const chunkSkipped = Number(data.skipped_count ?? 0) || 0;
                deletedCount += chunkDeleted;
                skippedCount += chunkSkipped;
                if (result?.message) {
                    lastMessage = result.message;
                }
                processed += chunk.length;
                updateDeleteProgress({
                    total: ids.length,
                    processed,
                    step: `${processed.toLocaleString('ko-KR')}\uAC74 \uCC98\uB9AC \uC644\uB8CC`,
                });
            }
        } else {
            for (const [index, id] of ids.entries()) {
                updateDeleteProgress({
                    total: ids.length,
                    processed: index,
                    step: `${index + 1}\uBC88\uC9F8 \uD589 \uCC98\uB9AC \uC911`,
                });
                const result = await postFormJson(deleteApi, { id });
                if (!result?.success) {
                    notify('error', result?.message || `\uC0AD\uC81C \uC2E4\uD328 (${index + 1}\uBC88\uC9F8)`);
                    return true;
                }
                const data = result?.data || {};
                const rowDeleted = Number(data.deleted_count ?? (data.skipped_count ? 0 : 1)) || 0;
                const rowSkipped = Number(data.skipped_count ?? 0) || 0;
                deletedCount += rowDeleted;
                skippedCount += rowSkipped;
                if (result?.message) {
                    lastMessage = result.message;
                }
                updateDeleteProgress({
                    total: ids.length,
                    processed: index + 1,
                    step: `${index + 1}\uAC74 \uCC98\uB9AC \uC644\uB8CC`,
                });
            }
        }

        updateDeleteProgress({ total: ids.length, processed: ids.length, step: '\uBAA9\uB85D \uC0C8\uB85C\uACE0\uCE68 \uC911' });
        selectedIds.clear();
        table.table?.().node?.()?.dispatchEvent(new CustomEvent('datatable:selection-changed', {
            bubbles: true,
            detail: {
                table,
                ids: [],
                selectedIds,
            },
        }));
        await reloadDataTable(table);
        table.table?.().node?.()?.dispatchEvent(new CustomEvent('datatable:soft-delete-completed', {
            bubbles: true,
            detail: {
                table,
                ids,
                selectedIds,
            },
        }));
        const summaryMessage = skippedCount > 0
            ? `\uC0AD\uC81C \uC644\uB8CC (${deletedCount.toLocaleString('ko-KR')}\uAC74 / \uC81C\uC678 ${skippedCount.toLocaleString('ko-KR')}\uAC74)`
            : `\uC0AD\uC81C \uC644\uB8CC (${deletedCount.toLocaleString('ko-KR')}\uAC74)`;
        notify(deletedCount > 0 ? 'success' : 'warning', lastMessage || summaryMessage);
        return true;
    } catch (error) {
        console.error(error);
        notify('error', error?.message || '\uC0AD\uC81C \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
        return true;
    } finally {
        hideDeleteProgress();
        setDataTableDeleteBusy(table, false, buttonNode);
    }
}

function isColumnVisibleInColvis(columns, index, node) {
    const column = columns[index] || {};
    const classes = tokenizeClasses(column.className, column.headerClassName);

    return !classes.includes('no-colvis') && !node?.classList?.contains('no-colvis');
}

function ensureTableHeader(tableSelector, columns = []) {
    const table = document.querySelector(tableSelector);
    if (!table || !Array.isArray(columns) || columns.length === 0) {
        return;
    }

    let thead = table.querySelector('thead');
    if (!thead) {
        thead = document.createElement('thead');
        table.insertBefore(thead, table.firstChild);
    }

    let row = thead.querySelector('tr');
    if (!row) {
        row = document.createElement('tr');
        thead.appendChild(row);
    }

    const headers = Array.from(row.querySelectorAll('th'));
    const shouldRewrite = headers.length !== columns.length || columns.some((column, index) => {
        const expectedTitle = String(column?.title ?? '').trim();
        const actualTitle = String(headers[index]?.textContent ?? '').trim();
        return expectedTitle !== '' && actualTitle === '';
    });
    if (!shouldRewrite) {
        return;
    }

    row.innerHTML = columns.map((column) => {
        const classes = tokenizeClasses(column.className, column.headerClassName);
        const classAttr = classes.length ? ` class="${classes.join(' ')}"` : '';
        return `<th${classAttr}>${column.title ?? ''}</th>`;
    }).join('');
}

function hasSelectionColumn(columns = []) {
    return columns.some((column = {}) => {
        const title = String(column.title || '');
        const className = tokenizeClasses(column.className, column.headerClassName);

        return column.isSelectionColumn === true
            || className.includes('dt-select-column')
            || title.includes('type="checkbox"');
    });
}

function insertColumnAt(columns = [], column = {}, index = 0) {
    const insertIndex = Math.max(0, Math.min(Number(index) || 0, columns.length));
    return [
        ...columns.slice(0, insertIndex),
        column,
        ...columns.slice(insertIndex),
    ];
}

function escapeAttr(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

function createSelectionColumn(tableSelector, selectedIds, rowIdField, isRowSelectable = () => true, options = {}) {
    const tableId = String(tableSelector || '').replace(/^#/, '') || 'dataTable';
    const checkAllId = `${tableId}SelectAll`;
    const width = String(options?.width || '').trim();
    const widthResizable = options?.widthResizable !== false;
    const settingsKey = String(options?.settingsKey || '__select').trim() || '__select';

    return {
        data: null,
        title: `<input type="checkbox" class="form-check-input dt-select-all" id="${escapeAttr(checkAllId)}" aria-label="\uC804\uCCB4 \uC120\uD0DD">`,
        className: 'dt-select-column no-colvis text-center',
        headerClassName: 'dt-select-column no-colvis text-center',
        orderable: false,
        searchable: false,
        defaultContent: '',
        isSelectionColumn: true,
        settingsKey,
        ...(width !== '' ? { width } : {}),
        ...(widthResizable ? { widthResizable: true } : {}),
        render: (_value, _type, row) => {
            const id = getRowId(row, rowIdField);
            const selectable = Boolean(id) && isRowSelectable(row);
            const checked = id && selectedIds.has(id) ? ' checked' : '';
            const disabled = selectable ? '' : ' disabled';
            return `<input type="checkbox" class="form-check-input dt-row-select" value="${escapeAttr(id)}"${checked}${disabled} aria-label="\uD589 \uC120\uD0DD">`;
        },
    };
}

function getRowId(row = {}, rowIdField = 'id') {
    if (!row || typeof row !== 'object') return '';

    if (typeof rowIdField === 'function') {
        return String(rowIdField(row) ?? '').trim();
    }

    return String(row[rowIdField] ?? row.id ?? '').trim();
}

function shiftOrderForSelection(defaultOrder = [], shouldShift = false, insertIndex = 0) {
    if (!shouldShift || !Array.isArray(defaultOrder)) {
        return defaultOrder;
    }

    return defaultOrder.map((item) => {
        if (!Array.isArray(item) || typeof item[0] !== 'number') {
            return item;
        }

        if (item[0] < insertIndex) {
            return item;
        }

        return [item[0] + 1, ...item.slice(1)];
    });
}

function resolveSettingsColumnKey(column = {}) {
    if (typeof column?.__dtSettingsKey === 'string' && column.__dtSettingsKey.trim() !== '') {
        return column.__dtSettingsKey.trim();
    }
    if (typeof column?.key === 'string' && column.key.trim() !== '') {
        return column.key.trim();
    }
    if (typeof column?.settingsKey === 'string' && column.settingsKey.trim() !== '') {
        return column.settingsKey.trim();
    }
    if (typeof column?.name === 'string' && column.name.trim() !== '') {
        return column.name.trim();
    }
    if (typeof column?.data === 'string' && column.data.trim() !== '') {
        return column.data.trim();
    }
    return '';
}

function normalizeSortDirection(value = '') {
    return String(value || '').trim().toLowerCase() === 'desc' ? 'desc' : 'asc';
}

function buildOrderFromSortSettings(sortSettings = [], tableColumns = []) {
    const indexByKey = new Map();
    tableColumns.forEach((column, index) => {
        const key = resolveSettingsColumnKey(column);
        if (key !== '' && !indexByKey.has(key)) {
            indexByKey.set(key, index);
        }
    });

    return (Array.isArray(sortSettings) ? sortSettings : [])
        .map((item) => {
            const key = String(item?.key || '').trim();
            if (key === '' || !indexByKey.has(key)) {
                return null;
            }

            return [indexByKey.get(key), normalizeSortDirection(item?.dir)];
        })
        .filter(Array.isArray);
}

function extractSortSettingsFromTable(table, tableColumns = []) {
    if (!table?.order) {
        return [];
    }

    return (table.order() || [])
        .map((item) => {
            if (!Array.isArray(item) || typeof item[0] !== 'number') {
                return null;
            }

            const column = tableColumns[item[0]];
            const key = resolveSettingsColumnKey(column);
            if (key === '') {
                return null;
            }

            return {
                key,
                dir: normalizeSortDirection(item[1]),
            };
        })
        .filter(Boolean);
}

function getDataTableWidthScope(wrapper) {
    const customSelector = String(wrapper?.dataset?.dtWidthScopeSelector || '').trim();
    if (customSelector !== '') {
        const scoped = wrapper?.closest(customSelector) || document.querySelector(customSelector);
        if (scoped) {
            return scoped;
        }
    }

    return wrapper?.closest('.table-box, .content-area, .card-body, .card, main') || wrapper?.parentElement || wrapper;
}

function getDataTableContainerWidth(table) {
    const wrapper = table?.table?.().container?.();
    const widthScope = getDataTableWidthScope(wrapper);
    if (!widthScope) {
        return 0;
    }

    return Math.ceil(
        widthScope.clientWidth
        || widthScope.getBoundingClientRect?.().width
        || wrapper?.getBoundingClientRect?.().width
        || 0
    );
}

function resolveColumnWidthKey(column = {}, index = 0) {
    if (typeof column.__dtSettingsKey === 'string' && column.__dtSettingsKey.trim() !== '') {
        return column.__dtSettingsKey.trim();
    }
    if (typeof column.key === 'string' && column.key.trim() !== '') {
        return column.key.trim();
    }
    if (typeof column.settingsKey === 'string' && column.settingsKey.trim() !== '') {
        return column.settingsKey.trim();
    }
    if (typeof column.name === 'string' && column.name.trim() !== '') {
        return column.name.trim();
    }
    if (typeof column.data === 'string' && column.data.trim() !== '') {
        return column.data.trim();
    }
    return '';
}

function isButtonColumn(column = {}) {
    return column.data == null && typeof column.render === 'function';
}

function isColumnWidthConfigurable(column = {}) {
    if (!column) {
        return false;
    }

    if (column.widthResizable === false) {
        return false;
    }

    if (column.widthResizable === true) {
        return resolveColumnWidthKey(column) !== '';
    }

    if (column.isSelectionColumn === true) {
        return false;
    }

    if (isReorderColumn(column) || isActionColumn(column) || isButtonColumn(column)) {
        return false;
    }

    return resolveColumnWidthKey(column) !== '';
}

function getColumnWidthIndexMap(tableColumns = []) {
    const indexMap = new Map();
    tableColumns.forEach((column, index) => {
        const key = resolveColumnWidthKey(column, index);
        if (key) {
            indexMap.set(key, index);
        }
    });
    return indexMap;
}

function getHeaderNodesByIndex(table, index) {
    return [
        table?.column?.(index)?.header?.() || null,
        getScrollHeaderNodeByIndex(table, index),
    ].filter(Boolean);
}

function getBodyCellNodesByIndex(table, index) {
    const cells = [];
    table?.cells?.(null, index, { page: 'current' })?.every?.(function () {
        const node = this.node?.();
        if (node) {
            cells.push(node);
        }
    });
    return cells;
}

function getVisibleColumnIndex(table, index) {
    const visibleIndex = table?.column?.(index)?.index?.('visible');
    return Number.isInteger(visibleIndex) && visibleIndex >= 0 ? visibleIndex : -1;
}

function getScrollHeaderNodeByIndex(table, index) {
    const wrapper = table?.table?.().container?.();
    const visibleIndex = getVisibleColumnIndex(table, index);
    if (!wrapper || visibleIndex < 0) {
        return null;
    }

    return wrapper.querySelectorAll?.('.dataTables_scrollHead th')?.[visibleIndex] || null;
}

function getColgroupNodesByIndex(table, index) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper) {
        return [];
    }

    const visibleIndex = getVisibleColumnIndex(table, index);
    const colgroups = Array.from(wrapper.querySelectorAll(
        '.dataTables_scrollHead colgroup, .dataTables_scrollBody colgroup, table.dataTable > colgroup'
    ));
    const resolved = [];
    const seen = new Set();

    colgroups.forEach((colgroup) => {
        const cols = Array.from(colgroup.querySelectorAll('col'));
        if (!cols.length) {
            return;
        }

        let targetIndex = -1;
        if (visibleIndex >= 0 && visibleIndex < cols.length) {
            targetIndex = visibleIndex;
        } else if (index >= 0 && index < cols.length) {
            targetIndex = index;
        }

        if (targetIndex < 0) {
            return;
        }

        const node = cols[targetIndex];
        if (!node || seen.has(node)) {
            return;
        }

        seen.add(node);
        resolved.push(node);
    });

    return resolved;
}

function applyColumnWidthPx(table, index, widthPx) {
    const normalizedWidth = Math.max(32, Math.round(Number(widthPx) || 0));
    if (!Number.isFinite(normalizedWidth) || normalizedWidth <= 0) {
        return;
    }

    const widthValue = `${normalizedWidth}px`;
    const applyWidth = (node) => {
        if (!node?.style) {
            return;
        }
        node.style.setProperty('width', widthValue, 'important');
        node.style.setProperty('min-width', widthValue, 'important');
        node.style.setProperty('max-width', widthValue, 'important');
        node.style.setProperty('box-sizing', 'border-box');
    };

    getHeaderNodesByIndex(table, index).forEach(applyWidth);
    getBodyCellNodesByIndex(table, index).forEach(applyWidth);
    getColgroupNodesByIndex(table, index).forEach(applyWidth);
}

function freezeVisibleColumnWidths(table, tableColumns = []) {
    tableColumns.forEach((column, index) => {
        if (table.column(index).visible() === false) {
            return;
        }

        const header = table.column(index).header?.();
        const currentWidth = Math.ceil(
            header?.getBoundingClientRect?.().width
            || table.column(index).nodes?.()?.to$?.()?.first?.()?.outerWidth?.()
            || 0
        );
        if (!Number.isFinite(currentWidth) || currentWidth <= 0) {
            return;
        }
        applyColumnWidthPx(table, index, currentWidth);
    });
}

function applyConfiguredColumnWidths(table, tableColumns = [], settingsContext = null) {
    const columnWidths = settingsContext?.state?.columnWidths;
    if (!table || !columnWidths || typeof columnWidths !== 'object') {
        return;
    }

    const indexMap = getColumnWidthIndexMap(tableColumns);
    Object.entries(columnWidths).forEach(([key, value]) => {
        const index = indexMap.get(String(key || '').trim());
        const widthPx = Number(value);
        if (index === undefined || !Number.isFinite(widthPx) || widthPx <= 0) {
            return;
        }
        applyColumnWidthPx(table, index, widthPx);
    });
}

function syncRenderedColumnWidths(table, tableColumns = [], settingsContext = null) {
    if (!table) {
        return;
    }

    const configuredWidths = settingsContext?.state?.columnWidths && typeof settingsContext.state.columnWidths === 'object'
        ? settingsContext.state.columnWidths
        : {};
    const configuredKeys = new Set(
        Object.keys(configuredWidths)
            .map((key) => String(key || '').trim())
            .filter(Boolean)
    );

    tableColumns.forEach((column, index) => {
        if (table.column(index).visible() === false) {
            return;
        }

        const key = resolveColumnWidthKey(column, index);
        if (key && configuredKeys.has(key)) {
            return;
        }

        const headerNode = table.column(index).header?.();
        const bodyNode = table.column(index).nodes?.()?.to$?.()?.first?.()?.get?.(0) || null;
        const measuredWidth = Math.ceil(Math.max(
            headerNode?.getBoundingClientRect?.().width || 0,
            bodyNode?.getBoundingClientRect?.().width || 0
        ));
        if (!Number.isFinite(measuredWidth) || measuredWidth <= 0) {
            return;
        }

        applyColumnWidthPx(table, index, measuredWidth);
    });
}

function scheduleApplyConfiguredColumnWidths(table, tableColumns = [], settingsContext = null, delay = 0) {
    window.setTimeout(() => {
        window.requestAnimationFrame(() => {
            applyConfiguredColumnWidths(table, tableColumns, settingsContext);
        });
    }, Math.max(0, Number(delay) || 0));
}

function renderColumnResizeHandles(table, tableColumns = []) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper) {
        return;
    }

    const headers = Array.from(wrapper.querySelectorAll('thead th'));
    headers.forEach((headerNode) => {
        headerNode.classList.remove('dt-column-resizable');
        headerNode.querySelectorAll('.dt-column-resizer').forEach((node) => node.remove());
    });

    const rendered = new Set();
    tableColumns.forEach((column, index) => {
        if (!isColumnWidthConfigurable(column) || rendered.has(index)) {
            return;
        }

        getHeaderNodesByIndex(table, index).forEach((headerNode) => {
            if (!headerNode || headerNode.querySelector('.dt-column-resizer')) {
                return;
            }
            headerNode.classList.add('dt-column-resizable');
            const handle = document.createElement('span');
            handle.className = 'dt-column-resizer';
            handle.dataset.columnIndex = String(index);
            handle.setAttribute('role', 'presentation');
            headerNode.appendChild(handle);
        });
        rendered.add(index);
    });
}

function bindColumnResize(table, tableColumns = [], settingsContext = null) {
    if (!table || !settingsContext?.config?.enabled) {
        return;
    }

    const wrapper = table.table().container();
    if (!wrapper || wrapper.dataset.dtColumnResizeBound === 'true') {
        return;
    }

    wrapper.dataset.dtColumnResizeBound = 'true';
    const indexMap = getColumnWidthIndexMap(tableColumns);
    const dragState = {
        active: false,
        pointerId: null,
        index: -1,
        key: '',
        startX: 0,
        startWidth: 0,
    };
    __dtResizeState.set(wrapper, dragState);

    const stopEvent = (event) => {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    };

    const finishResize = () => {
        if (!dragState.active) {
            return;
        }

        dragState.active = false;
        dragState.pointerId = null;
        document.body.classList.remove('dt-column-resizing');

        if (!dragState.key || dragState.index < 0) {
            return;
        }

        const header = table.column(dragState.index).header?.();
        const widthPx = Math.ceil(header?.getBoundingClientRect?.().width || 0);
        if (!Number.isFinite(widthPx) || widthPx <= 0) {
            return;
        }

        const nextColumnWidths = {
            ...(settingsContext.state?.columnWidths || {}),
            [dragState.key]: widthPx,
        };
        updateDataTableSettingsState(settingsContext, {
            columnWidths: nextColumnWidths,
        });
        scheduleAdjust(table, {
            draw: false,
            tableColumns,
            settingsContext,
        });
    };

    const onPointerMove = (event) => {
        if (!dragState.active) {
            return;
        }

        if (typeof event.buttons === 'number' && event.buttons === 0) {
            finishResize();
            return;
        }

        stopEvent(event);
        const nextWidth = Math.max(32, dragState.startWidth + (event.clientX - dragState.startX));
        applyColumnWidthPx(table, dragState.index, nextWidth);
        syncScrollHeadWidth(table);
    };

    const onPointerUp = (event) => {
        if (!dragState.active) {
            return;
        }
        stopEvent(event);
        finishResize();
    };

    wrapper.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('.dt-column-resizer');
        if (!handle) {
            return;
        }

        const index = Number(handle.dataset.columnIndex);
        const column = tableColumns[index];
        const key = resolveColumnWidthKey(column, index);
        if (!Number.isInteger(index) || index < 0 || !key || !indexMap.has(key)) {
            return;
        }

        stopEvent(event);
        if (wrapper.querySelector('.dataTables_scroll')) {
            freezeVisibleColumnWidths(table, tableColumns);
        }

        const header = table.column(index).header?.();
        const startWidth = Math.ceil(header?.getBoundingClientRect?.().width || 0);
        if (!Number.isFinite(startWidth) || startWidth <= 0) {
            return;
        }

        dragState.active = true;
        dragState.pointerId = event.pointerId;
        dragState.index = index;
        dragState.key = key;
        dragState.startX = event.clientX;
        dragState.startWidth = startWidth;
        document.body.classList.add('dt-column-resizing');
        handle.setPointerCapture?.(event.pointerId);
    }, true);

    wrapper.addEventListener('click', (event) => {
        if (event.target.closest('.dt-column-resizer')) {
            stopEvent(event);
        }
    }, true);

    wrapper.addEventListener('dblclick', (event) => {
        if (event.target.closest('.dt-column-resizer')) {
            stopEvent(event);
        }
    }, true);

    window.addEventListener('pointermove', onPointerMove, true);
    window.addEventListener('pointerup', onPointerUp, true);
    window.addEventListener('pointercancel', finishResize, true);
    window.addEventListener('blur', finishResize, true);
}

function scheduleAdjust(table, options = {}) {
    if (!table) return;

    const {
        draw = false,
        tableColumns = [],
        settingsContext = null,
    } = options;

    const node = table.table().node();
    if (!node) return;

    let state = __dtAdjustState.get(node);
    if (!state) {
        state = {
            raf: null
        };
        __dtAdjustState.set(node, state);
    }

    if (state.raf) {
        cancelAnimationFrame(state.raf);
        state.raf = null;
    }

    state.raf = requestAnimationFrame(() => {
        try {
            table.columns.adjust();
            syncScrollHeadWidth(table);
            applyConfiguredColumnWidths(table, tableColumns, settingsContext);
            syncRenderedColumnWidths(table, tableColumns, settingsContext);
            syncScrollHeadWidth(table);

            if (draw) {
                table.draw(false);
                syncScrollHeadWidth(table);
                applyConfiguredColumnWidths(table, tableColumns, settingsContext);
                syncRenderedColumnWidths(table, tableColumns, settingsContext);
                syncScrollHeadWidth(table);
            }
        } catch (err) {
            console.error('[data-table] scheduleAdjust failed:', err);
        }
    });
}

function isBackgroundTableDuringModal(table) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper || !document.body.classList.contains('modal-open')) {
        return false;
    }

    const ownerModal = wrapper.closest('.modal');
    return !ownerModal || !ownerModal.classList.contains('show');
}

function syncScrollHeadWidth(table) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper) return;

    const widthScope = getDataTableWidthScope(wrapper);
    const wrapperWidth = Math.ceil(widthScope.clientWidth || widthScope.getBoundingClientRect().width || wrapper.getBoundingClientRect().width);
    if (Number.isFinite(wrapperWidth) && wrapperWidth > 0) {
        wrapper.style.maxWidth = `${wrapperWidth}px`;
    }

    // 일반 화면은 여기까지가 핵심이다.
    // - SearchForm 과 같은 컨테이너 폭을 기준으로 wrapper 폭을 제한한다.
    // - scrollX 구조가 없는 화면은 아래 분기 전에 종료된다.
    const bodyTable = wrapper.querySelector('.dataTables_scrollBody table.dataTable');
    const scroll = wrapper.querySelector('.dataTables_scroll');
    const scrollHead = wrapper.querySelector('.dataTables_scrollHead');
    const scrollBody = wrapper.querySelector('.dataTables_scrollBody');
    const headInner = wrapper.querySelector('.dataTables_scrollHeadInner');
    const headTable = wrapper.querySelector('.dataTables_scrollHead table.dataTable');
    if (!bodyTable || !headInner || !headTable) return;

    // scrollX 화면은 head/body 폭 동기화가 필요하다.
    // - wrapperWidth 는 뷰포트 기준 폭
    // - contentWidth 는 현재 body table 이 실제로 차지하는 폭
    // - 더 큰 값을 사용해 scroll head 와 scroll body 를 같은 폭으로 맞춘다.
    const explicitContentWidth = Number(wrapper.dataset.dtContentWidth || 0);
    const colgroupWidth = Array.from(wrapper.querySelectorAll('.dataTables_scrollHead colgroup col'))
        .reduce((sum, col) => {
            const width = Math.ceil(
                parseFloat(col.style.width || '0')
                || parseFloat(col.style.minWidth || '0')
                || col.getBoundingClientRect?.().width
                || 0
            );
            return sum + (Number.isFinite(width) && width > 0 ? width : 0);
        }, 0);
    const contentWidth = Math.max(
        explicitContentWidth,
        colgroupWidth,
        Math.ceil(bodyTable.scrollWidth || 0),
        Math.ceil(bodyTable.getBoundingClientRect().width)
    );
    const configuredWidths = table?.__dtTableSettings?.context?.state?.columnWidths;
    const hasConfiguredWidths = configuredWidths
        && typeof configuredWidths === 'object'
        && Object.keys(configuredWidths).some((key) => {
            const widthPx = Number(configuredWidths[key]);
            return String(key || '').trim() !== '' && Number.isFinite(widthPx) && widthPx > 0;
        });
    const width = hasConfiguredWidths ? contentWidth : Math.max(wrapperWidth || 0, contentWidth);
    if (!Number.isFinite(width) || width <= 0) return;

    if (Number.isFinite(wrapperWidth) && wrapperWidth > 0) {
        [scroll, scrollHead, scrollBody].forEach((node) => {
            if (!node) return;
            node.style.width = '100%';
            node.style.maxWidth = `${wrapperWidth}px`;
        });
    }

    headInner.style.width = `${width}px`;
    headInner.style.minWidth = `${width}px`;
    headInner.style.maxWidth = 'none';
    headInner.style.paddingRight = '0px';
    headTable.style.width = `${width}px`;
    headTable.style.minWidth = `${width}px`;
    headTable.style.tableLayout = 'fixed';
    bodyTable.style.width = `${width}px`;
    bodyTable.style.minWidth = `${width}px`;
    bodyTable.style.tableLayout = 'fixed';
}

function findScrollParent(node) {
    let current = node?.parentElement || null;
    while (current && current !== document.body && current !== document.documentElement) {
        const style = window.getComputedStyle(current);
        if (/(auto|scroll)/.test(style.overflowY || '')) {
            return current;
        }
        current = current.parentElement;
    }

    return null;
}

function updateStickyHeaderOffset(table) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper) return;

    const nav = document.querySelector('.top-nav.fixed-top, .top-nav');
    const navRect = nav?.getBoundingClientRect();
    const navBottom = Math.max(0, Math.ceil(navRect?.bottom ?? navRect?.height ?? 0));
    const scrollParent = findScrollParent(wrapper);
    const scrollParentTop = scrollParent
        ? Math.ceil(scrollParent.getBoundingClientRect().top || 0)
        : 0;
    const offset = scrollParent
        ? Math.max(0, navBottom - scrollParentTop)
        : navBottom;
    const toolbar = wrapper.querySelector('.dt-top');
    const toolbarHeight = toolbar ? Math.ceil(toolbar.offsetHeight || toolbar.getBoundingClientRect().height) : 0;

    wrapper.style.setProperty('--dt-sticky-top', `${offset}px`);
    wrapper.style.setProperty('--dt-sticky-header-top', `${offset + toolbarHeight}px`);
}

export function refreshDataTableLayout(table, options = {}) {
    if (!table) return;

    const delays = Array.isArray(options.delays) ? options.delays : [0, 80, 180];
    delays.forEach((delay) => {
        window.setTimeout(() => {
            try {
                updateStickyHeaderOffset(table);
                table.columns.adjust();
                syncScrollHeadWidth(table);
                applyConfiguredColumnWidths(table, table.__dtTableSettings?.tableColumns || [], table.__dtTableSettings?.context || null);
            } catch (err) {
                console.error('[data-table] refreshDataTableLayout failed:', err);
            }
        }, Math.max(0, Number(delay) || 0));
    });
}

export function refreshAllDataTableLayouts() {
    __dtInstances.forEach((table) => refreshDataTableLayout(table));
}

function bindStickyHeaderOffsetUpdates(table) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper || wrapper.dataset.stickyOffsetBound === 'true') return;

    let raf = null;
    const update = () => {
        if (raf) return;
        raf = requestAnimationFrame(() => {
            raf = null;
            updateStickyHeaderOffset(table);
        });
    };

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    const scrollParent = findScrollParent(wrapper);
    if (scrollParent && scrollParent !== window) {
        scrollParent.addEventListener('scroll', update, { passive: true });
    }
    wrapper.dataset.stickyOffsetBound = 'true';
}

function toCamelCase(value) {
    return String(value || '').replace(/-([a-z0-9])/g, (_, ch) => ch.toUpperCase());
}

function stripHtml(value) {
    if (typeof AppDom?.stripHtml === 'function') {
        return AppDom.stripHtml(value);
    }

    const text = String(value ?? '');
    if (!text.includes('<')) {
        return text.trim();
    }
    const div = document.createElement('div');
    div.innerHTML = text;
    return (div.textContent || '').trim();
}

function findTargetSearchCondition(tableNode, explicitTableId) {
    const tableDomId = tableNode?.id || '';
    const baseId = tableDomId.replace(/-table$/, '');
    const candidates = [
        explicitTableId,
        baseId,
        toCamelCase(baseId)
    ].filter(Boolean);

    for (const id of candidates) {
        const container = document.getElementById(`${id}SearchConditions`);
        const condition = getTargetConditionFromContainer(container);
        if (condition) return condition;
    }

    const scope = tableNode?.closest?.('.content-area, .card-body, main, body') || document;
    return getTargetConditionFromContainer(scope);
}

function getTargetConditionFromContainer(container) {
    if (!container) return null;

    const conditions = Array.from(container.querySelectorAll('.search-condition'));
    if (!conditions.length) return null;

    const active = document.activeElement?.closest?.('.search-condition');
    if (active && conditions.includes(active)) {
        return active;
    }

    const empty = conditions.find((condition) => {
        const input = condition.querySelector('input[type="text"], .search-input, input[name="searchValue[]"]');
        return input && String(input.value || '').trim() === '';
    });

    return empty || conditions[conditions.length - 1];
}

function bindCellSearchFill(table, tableSelector, options = {}) {
    if (!table || options === false) return;
    if (typeof options === 'object' && options.enabled === false) return;

    const $ = window.jQuery;
    const $table = $(tableSelector);
    const tableNode = $table.get(0);
    const explicitTableId = typeof options === 'object' ? options.tableId : null;
    const fieldMap = typeof options === 'object' ? options.fieldMap : null;
    const valueMap = typeof options === 'object' ? options.valueMap : null;

    function resolveMappedValue(mapper, context, fallback) {
        if (typeof mapper === 'function') {
            const mapped = mapper(context);
            return mapped === undefined ? fallback : mapped;
        }
        if (mapper && typeof mapper === 'object' && Object.prototype.hasOwnProperty.call(mapper, context.field)) {
            return mapper[context.field];
        }
        return fallback;
    }

    $table.find('tbody')
        .off('click.dtCellSearchFill')
        .on('click.dtCellSearchFill', 'td', function (event) {
            if (event.target.closest('a, button, input, select, textarea, .dropdown-menu, .reorder-handle, .drag-handle')) {
                return;
            }

            const cell = table.cell(this);
            const index = cell.index();
            if (!index) return;

            const sourceField = table.column(index.column).dataSrc();
            if (!sourceField || typeof sourceField !== 'string') return;

            const column = table.settings()?.[0]?.aoColumns?.[index.column];
            if (column?.bSearchable === false) return;
            const row = table.row(index.row).data() || {};
            const context = {
                field: sourceField,
                row,
                cell,
                column,
                cellNode: this,
                event,
            };
            const field = resolveMappedValue(fieldMap, context, sourceField);
            if (!field || typeof field !== 'string') return;

            const condition = findTargetSearchCondition(tableNode, explicitTableId);
            if (!condition) return;

            const select = condition.querySelector('select');
            const input = condition.querySelector('input[type="text"], .search-input, input[name="searchValue[]"]');
            if (!select || !input) return;

            const hasOption = Array.from(select.options).some((option) => option.value === field);
            if (!hasOption) return;

            select.value = field;
            const value = resolveMappedValue(valueMap, context, stripHtml(cell.data()));
            input.value = String(value ?? '');
        });
}

function bindSelectionColumn(table, tableSelector, selectedIds, rowIdField, isRowSelectable = () => true) {
    const $ = window.jQuery;
    const $table = $(tableSelector);
    const tableNode = $table.get(0);
    const wrapper = table.table().container();

    function updateSelectionButtons() {
        const wrapper = table.table().container();
        wrapper?.querySelectorAll('.dt-soft-delete-btn, .dt-selected-move-btn').forEach((button) => {
            button.classList.toggle('disabled', selectedIds.size === 0);
            button.setAttribute('aria-disabled', selectedIds.size === 0 ? 'true' : 'false');
        });
    }

    function syncVisibleCheckboxes() {
        const visibleRows = table.rows({ page: 'current' }).nodes().toArray();
        const checkboxes = visibleRows
            .map((row) => row.querySelector('.dt-row-select'))
            .filter(Boolean);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = checkbox.value !== '' && selectedIds.has(checkbox.value);
        });

        const enabled = checkboxes.filter((checkbox) => !checkbox.disabled);
        const checkedCount = enabled.filter((checkbox) => checkbox.checked).length;
        wrapper?.querySelectorAll('.dt-select-all').forEach((checkAll) => {
            checkAll.checked = enabled.length > 0 && checkedCount === enabled.length;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < enabled.length;
        });

        updateSelectionButtons();
    }

    $table.find('tbody')
        .off('change.dtSelectionColumn')
        .on('change.dtSelectionColumn', '.dt-row-select', function () {
            if (tableNode?.dataset.dtDeleting === 'true') {
                this.checked = this.value !== '' && selectedIds.has(this.value);
                return;
            }
            if (!this.value) return;

            if (this.checked) {
                selectedIds.add(this.value);
            } else {
                selectedIds.delete(this.value);
            }

            tableNode?.dispatchEvent(new CustomEvent('datatable:selection-changed', {
                bubbles: true,
                detail: {
                    table,
                    tableSelector,
                    ids: Array.from(selectedIds),
                    selectedIds,
                },
            }));

            syncVisibleCheckboxes();
        });

    $(wrapper)
        .off('change.dtSelectionColumnHeader')
        .on('change.dtSelectionColumnHeader', '.dt-select-all', function () {
            if (tableNode?.dataset.dtDeleting === 'true') {
                syncVisibleCheckboxes();
                return;
            }
            const checked = this.checked;
            table.rows({ page: 'current' }).every(function () {
                const row = this.data();
                const id = getRowId(row, rowIdField);
                if (!id || !isRowSelectable(row)) return;

                if (checked) {
                    selectedIds.add(id);
                } else {
                    selectedIds.delete(id);
                }
            });

            tableNode?.dispatchEvent(new CustomEvent('datatable:selection-changed', {
                bubbles: true,
                detail: {
                    table,
                    tableSelector,
                    ids: Array.from(selectedIds),
                    selectedIds,
                },
            }));

            syncVisibleCheckboxes();
        });

    table.on('draw.dt xhr.dt column-visibility.dt responsive-resize.dt', syncVisibleCheckboxes);
    syncVisibleCheckboxes();

    table.getSelectedIds = () => Array.from(selectedIds);
    table.clearSelectedIds = () => {
        selectedIds.clear();
        syncVisibleCheckboxes();
    };
    table.setSelectedIds = (ids = []) => {
        selectedIds.clear();
        ids.forEach((id) => {
            const value = String(id ?? '').trim();
            if (value) selectedIds.add(value);
        });
        syncVisibleCheckboxes();
        tableNode?.dispatchEvent(new CustomEvent('datatable:selection-changed', {
            bubbles: true,
            detail: {
                table,
                tableSelector,
                ids: Array.from(selectedIds),
                selectedIds,
            },
        }));
    };
}

function inferTableSettingsPageKey(rawPageKey = '') {
    const explicit = String(rawPageKey || '').trim();
    if (explicit !== '') {
        return explicit;
    }

    const pathname = String(window.location?.pathname || '')
        .trim()
        .replace(/^\/+|\/+$/g, '')
        .replace(/\//g, '.');

    return pathname || 'datatable';
}

function inferTableSettingsTableKey(rawTableKey = '', searchTableId = null, tableSelector = '') {
    const explicit = String(rawTableKey || '').trim();
    if (explicit !== '') {
        return explicit;
    }

    const fromSearchTableId = String(searchTableId || '').trim();
    if (fromSearchTableId !== '') {
        return fromSearchTableId;
    }

    return String(tableSelector || '')
        .trim()
        .replace(/^#/, '')
        .replace(/[^a-zA-Z0-9._-]+/g, '-')
        || 'table';
}

function normalizeTableSettingsConfig(tableSettings, {
    tableSelector = '',
    searchTableId = null,
    columns = [],
    pageLength = null,
} = {}) {
    if (tableSettings?.enabled === false) {
        return {
            ...(tableSettings && typeof tableSettings === 'object' ? tableSettings : {}),
            enabled: false,
        };
    }

    const baseConfig = (tableSettings && typeof tableSettings === 'object') ? tableSettings : {};
    const pageKey = inferTableSettingsPageKey(baseConfig.pageKey);
    const tableKey = inferTableSettingsTableKey(baseConfig.tableKey, searchTableId, tableSelector);
    const storageKey = String(baseConfig.storageKey || `${pageKey}.${tableKey}.v1`).trim();

    return {
        ...baseConfig,
        enabled: true,
        pageKey,
        tableKey,
        storageKey,
        tableLabel: String(baseConfig.tableLabel || tableKey || 'Table').trim(),
        title: String(baseConfig.title || 'Table Settings').trim(),
        columns: Array.isArray(baseConfig.columns) ? baseConfig.columns : columns,
        pageLength: Number(baseConfig.pageLength || pageLength) || null,
        requiredColumns: Array.isArray(baseConfig.requiredColumns) ? baseConfig.requiredColumns : [],
        defaultVisibleColumns: Array.isArray(baseConfig.defaultVisibleColumns) ? baseConfig.defaultVisibleColumns : [],
    };
}

function bindTableSettingsTooltip(table) {
    const trigger = table?.table?.().container?.()?.querySelector?.('.dt-table-settings-trigger');
    if (!trigger) {
        return;
    }

    trigger.setAttribute('title', '테이블 설정');
    trigger.setAttribute('data-bs-toggle', 'tooltip');
    trigger.setAttribute('data-bs-placement', 'bottom');
    trigger.setAttribute('aria-label', '테이블 설정');

    if (window.bootstrap?.Tooltip) {
        window.bootstrap.Tooltip.getOrCreateInstance(trigger, {
            container: 'body',
            placement: 'bottom',
            trigger: 'hover focus',
        });
    }
}

function isCellTextTruncated(cell) {
    if (!(cell instanceof HTMLElement)) {
        return false;
    }

    return Math.ceil(cell.scrollWidth) > Math.ceil(cell.clientWidth + 1);
}

function resolveCellTooltipText(cell) {
    if (!(cell instanceof HTMLElement)) {
        return '';
    }

    return String(cell.textContent || '').replace(/\s+/g, ' ').trim();
}

function disposeCellTooltip(cell) {
    if (!(cell instanceof HTMLElement) || !window.bootstrap?.Tooltip) {
        return;
    }

    const instance = window.bootstrap.Tooltip.getInstance(cell);
    instance?.dispose();
    cell.removeAttribute('data-bs-toggle');
    cell.removeAttribute('data-bs-placement');
    cell.removeAttribute('data-bs-trigger');
    cell.removeAttribute('data-bs-original-title');
    cell.removeAttribute('title');
    cell.removeAttribute('aria-label');
}

function bindTruncatedCellTooltips(table, tableSelector) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper || wrapper.dataset.dtCellTooltipBound === 'true') {
        return;
    }

    wrapper.dataset.dtCellTooltipBound = 'true';
    const tbody = wrapper.querySelector('tbody');
    if (!tbody) {
        return;
    }

    const showTooltip = (event) => {
        const cell = event.target.closest('td');
        if (!cell || !tbody.contains(cell)) {
            return;
        }

        if (cell.querySelector('input, select, textarea, button, a, .dropdown-menu')) {
            disposeCellTooltip(cell);
            return;
        }

        const text = resolveCellTooltipText(cell);
        if (text === '' || !isCellTextTruncated(cell)) {
            disposeCellTooltip(cell);
            return;
        }

        if (!window.bootstrap?.Tooltip) {
            return;
        }

        cell.setAttribute('title', text);
        cell.setAttribute('aria-label', text);
        cell.setAttribute('data-bs-toggle', 'tooltip');
        cell.setAttribute('data-bs-placement', 'top');
        cell.setAttribute('data-bs-trigger', 'manual');

        const tooltip = window.bootstrap.Tooltip.getOrCreateInstance(cell, {
            container: 'body',
            placement: 'top',
            trigger: 'manual',
        });
        tooltip.show();
    };

    const hideTooltip = (event) => {
        const cell = event.target.closest('td');
        if (!cell) {
            return;
        }
        disposeCellTooltip(cell);
    };

    tbody.addEventListener('mouseover', showTooltip, true);
    tbody.addEventListener('focusin', showTooltip, true);
    tbody.addEventListener('mouseout', hideTooltip, true);
    tbody.addEventListener('focusout', hideTooltip, true);
}

function positionTableSettingsTrigger(table) {
    const container = table?.table?.().container?.();
    const trigger = container?.querySelector?.('.dt-table-settings-trigger');
    const toolbar = container?.querySelector?.('.dt-top');
    const lengthNode = toolbar?.querySelector?.('.dataTables_length');
    if (!trigger || !toolbar || !lengthNode) {
        return;
    }

    if (trigger.parentElement?.classList?.contains('dt-buttons')) {
        const buttonsWrap = trigger.parentElement;
        if (buttonsWrap.childElementCount <= 1) {
            buttonsWrap.remove();
        }
    }

    toolbar.appendChild(trigger);
}

export function createDataTable(config) {
    const hasExplicitAutoWidth = Object.prototype.hasOwnProperty.call(config || {}, 'autoWidth');
    const {
        tableSelector,
        api,
        columns,
        buttons = [],
        defaultOrder = [[0, 'desc']],
        pageLength = DEFAULT_PAGE_LENGTH,
        responsive = false,
        autoWidth = true,
        ajaxData = null,
        dataSrc = null,
        cellSearchFill = true,
        searchTableId = null,
        rowReorder = false,
        initialData = null,
        density = null,
        scrollX = false,
        paging = true,
        searching = true,
        info = true,
        showColumnVisibility = false,
        showCopyButton = true,
        selectable = true,
        selectionColumnIndex = 0,
        selectionColumn = null,
        isRowSelectable = () => true,
        rowIdField = 'id',
        deleteButton = true,
        deleteApi = null,
        bulkDelete = false,
        interaction = false,
        pageLoading = true,
        tableSettings = null,
        widthScopeSelector = null,
        fixedLayout = false,
    } = config;
    const isClientTableDiagnostic = tableSelector === '#client-table'
        || api === '/api/settings/base-info/client/list';

    const $ = window.jQuery;
    const normalizedPageLength = PAGE_LENGTH_MENU.includes(Number(pageLength))
        ? Number(pageLength)
        : DEFAULT_PAGE_LENGTH;
    const selectedIds = new Set();
    const sourceColumns = Array.isArray(columns) ? columns : [];
    const shouldAddSelectionColumn = selectable !== false && !hasSelectionColumn(sourceColumns);
    const selectionInsertIndex = Math.max(0, Math.min(Number(selectionColumnIndex) || 0, sourceColumns.length));
    const resolvedSelectionColumn = (selectionColumn && typeof selectionColumn === 'object') ? selectionColumn : {};
    const sourceColumnsWithSelection = shouldAddSelectionColumn
        ? insertColumnAt(sourceColumns, createSelectionColumn(tableSelector, selectedIds, rowIdField, isRowSelectable, resolvedSelectionColumn), selectionInsertIndex)
        : sourceColumns;
    const resolvedTableSettings = normalizeTableSettingsConfig(tableSettings, {
        tableSelector,
        searchTableId,
        columns: sourceColumnsWithSelection,
        pageLength: normalizedPageLength,
    });
    const preparedTableSettings = prepareDataTableSettingsColumns(sourceColumnsWithSelection, resolvedTableSettings);
    const savedPageLength = Number(preparedTableSettings.context?.state?.pageLength);
    const resolvedColumns = preparedTableSettings.columns || sourceColumnsWithSelection;
    const resolvedAutoWidth = hasExplicitAutoWidth
        ? autoWidth
        : (resolvedTableSettings?.enabled ? false : autoWidth);
    const tableColumns = normalizeUtilityColumns(resolvedColumns);
    if (preparedTableSettings.context) {
        preparedTableSettings.context.tableColumns = tableColumns.map((column) => ({ ...column }));
    }
    const resolvedInitialOrder = buildOrderFromSortSettings(preparedTableSettings.context?.state?.sortSettings || [], tableColumns);
    const resolvedInitialPageLength = PAGE_LENGTH_MENU.includes(savedPageLength)
        ? savedPageLength
        : normalizedPageLength;
    ensureTableHeader(tableSelector, tableColumns);
    const initialLoadingHandle = createPageLoadingHandle({
        enabled: pageLoading !== false,
        selector: tableSelector,
        message: '\uB370\uC774\uD130\uB97C \uBD88\uB7EC\uC624\uB294 \uC911\uC785\uB2C8\uB2E4...',
    });
    const releasePageLoading = () => initialLoadingHandle.release();
    const refreshTableSettingsLayout = ({ draw = false } = {}) => {
        scheduleAdjust(table, {
            draw,
            tableColumns,
            settingsContext: preparedTableSettings.context,
        });
    };
    const dataTableConfig = {
        ...(api ? {
            ajax: {
            url: api,
            type: 'GET',
            cache: false,
            data: function (request) {
                if (typeof ajaxData === 'function') {
                    const result = ajaxData(request);

                    if (result && typeof result === 'object') {
                        return result;
                    }
                }

                return request;
            },
            dataSrc: function (json) {
                if (typeof dataSrc === 'function') {
                    const rows = dataSrc(json);
                    if (isClientTableDiagnostic) {
                        console.log('[CLIENT] response', json);
                        console.log('[CLIENT] count', json?.data?.length);
                        console.log('[CLIENT] rows', rows?.length);
                    }
                    return Array.isArray(rows) ? rows : [];
                }

                const rows = json.data ?? [];
                if (isClientTableDiagnostic) {
                    console.log('[CLIENT] response', json);
                    console.log('[CLIENT] count', json?.data?.length);
                    console.log('[CLIENT] rows', rows?.length);
                }
                return rows;
            }
            },
        } : {
            data: Array.isArray(initialData) ? initialData : [],
        }),

        columns: tableColumns,
        order: resolvedInitialOrder.length > 0
            ? resolvedInitialOrder
            : shiftOrderForSelection(defaultOrder, shouldAddSelectionColumn, selectionInsertIndex),
        pageLength: resolvedInitialPageLength,
        lengthMenu: PAGE_LENGTH_MENU,

        rowReorder: rowReorder ? {
            selector: 'td.reorder-handle',
            dataSrc: 'sort_no'
        } : false,

        scrollX,
        scrollCollapse: true,

        responsive,
        autoWidth: resolvedAutoWidth,
        deferRender: true,
        paging,
        searching,
        info,
        processing: true,

        dom: '<"dt-top d-flex justify-content-end align-items-center gap-2"fBl>rt<"dt-bottom d-flex justify-content-between align-items-center"ip>',

        buttons: [
            ...(showColumnVisibility === false ? [] : [{
                extend: 'colvis',
                text: '\uC5F4\uD45C\uC2DC',
                className: 'btn btn-secondary btn-sm',
                popoverTitle: 'Column visibility',
                collectionLayout: 'fixed two-column',
                columns: function (index, data, node) {
                    return isColumnVisibleInColvis(tableColumns, index, node);
                }
            }]),
            ...(resolvedTableSettings?.enabled ? [{
                text: '<i class="bi bi-gear"></i>',
                className: 'dt-table-settings-trigger',
                titleAttr: '\uD14C\uC774\uBE14 \uC124\uC815',
                action: function () {
                    table?.__dtTableSettings?.open?.();
                },
            }] : []),
            ...(selectable === false ? [] : [{
                text: '\u2191',
                titleAttr: '\uC120\uD0DD\uD55C \uD589 \uC704\uB85C \uC774\uB3D9',
                className: 'btn btn-outline-secondary btn-sm dt-selected-move-btn dt-selected-move-up-btn',
                action: function () {
                    const ids = Array.from(selectedIds);
                    if (ids.length === 0) {
                        notify('warning', '\uC774\uB3D9\uD560 \uD589\uC744 \uC120\uD0DD\uD558\uC138\uC694.');
                        return;
                    }

                    const tableNode = document.querySelector(tableSelector);
                    const event = new CustomEvent('datatable:move-selected', {
                        bubbles: true,
                        cancelable: true,
                        detail: {
                            table,
                            tableSelector,
                            direction: 'up',
                            ids,
                            selectedIds,
                        },
                    });
                    tableNode?.dispatchEvent(event);
                    if (!event.defaultPrevented) {
                        notify('warning', '\uC774 \uD14C\uC774\uBE14\uC740 \uC21C\uC11C \uBCC0\uACBD \uC800\uC7A5 \uC124\uC815\uC774 \uC5C6\uC2B5\uB2C8\uB2E4.');
                    }
                },
            }, {
                text: '\u2193',
                titleAttr: '\uC120\uD0DD\uD55C \uD589 \uC544\uB798\uB85C \uC774\uB3D9',
                className: 'btn btn-outline-secondary btn-sm dt-selected-move-btn dt-selected-move-down-btn',
                action: function () {
                    const ids = Array.from(selectedIds);
                    if (ids.length === 0) {
                        notify('warning', '\uC774\uB3D9\uD560 \uD589\uC744 \uC120\uD0DD\uD558\uC138\uC694.');
                        return;
                    }

                    const tableNode = document.querySelector(tableSelector);
                    const event = new CustomEvent('datatable:move-selected', {
                        bubbles: true,
                        cancelable: true,
                        detail: {
                            table,
                            tableSelector,
                            direction: 'down',
                            ids,
                            selectedIds,
                        },
                    });
                    tableNode?.dispatchEvent(event);
                    if (!event.defaultPrevented) {
                        notify('warning', '\uC774 \uD14C\uC774\uBE14\uC740 \uC21C\uC11C \uBCC0\uACBD \uC800\uC7A5 \uC124\uC815\uC774 \uC5C6\uC2B5\uB2C8\uB2E4.');
                    }
                },
            }]),
            ...(showCopyButton === false ? [] : [{
                extend: 'copy',
                text: '\uBCF5\uC0AC',
                className: 'btn btn-outline-secondary btn-sm'
            }]),
            ...(deleteButton === false ? [] : [{
                text: '\uC0AD\uC81C',
                className: 'btn btn-outline-danger btn-sm dt-soft-delete-btn',
                action: async function (_event, _dt, buttonNode) {
                    const ids = Array.from(selectedIds);
                    if (ids.length === 0) {
                        notify('warning', '\uC0AD\uC81C\uD560 \uD589\uC744 \uC120\uD0DD\uD558\uC138\uC694.');
                        return;
                    }

                    const handled = await softDeleteSelectedRows({
                        deleteApi,
                        ids,
                        table,
                        selectedIds,
                        buttonNode: buttonNode?.get?.(0) || buttonNode?.[0] || null,
                        bulkDelete,
                    });

                    if (handled) {
                        return;
                    }

                    const tableNode = document.querySelector(tableSelector);

                    tableNode?.dispatchEvent(new CustomEvent('datatable:delete-selected', {
                        bubbles: true,
                        detail: {
                            table,
                            tableSelector,
                            ids,
                            selectedIds,
                        },
                    }));
                },
            }]),
            ...buttons
        ],

        language: {
            lengthMenu: '\uD398\uC774\uC9C0\uB2F9 _MENU_ \uAC1C\uC529 \uBCF4\uAE30',
            zeroRecords: '\uB370\uC774\uD130 \uC5C6\uC74C',
            info: '_PAGE_ / _PAGES_ \uD398\uC774\uC9C0',
            infoEmpty: '\uB370\uC774\uD130 \uC5C6\uC74C',
            infoFiltered: '',
            search: '\uAC80\uC0C9',
            paginate: {
                next: '\uB2E4\uC74C',
                previous: '\uC774\uC804'
            }
        },

        initComplete: function () {
            const api = this.api();
            const wrapper = api.table?.().container?.();
            if (wrapper && typeof widthScopeSelector === 'string' && widthScopeSelector.trim() !== '') {
                wrapper.dataset.dtWidthScopeSelector = widthScopeSelector.trim();
            }
            if (wrapper) {
                wrapper.classList.toggle('dt-fixed-layout', fixedLayout === true);
            }

            applyColumnHeaderClasses(api, tableColumns);
            updateStickyHeaderOffset(api);
            api.columns.adjust();
            syncScrollHeadWidth(api);
            syncRenderedColumnWidths(api, tableColumns, preparedTableSettings.context);
            syncScrollHeadWidth(api);
            renderColumnResizeHandles(api, tableColumns);
            scheduleApplyConfiguredColumnWidths(api, tableColumns, preparedTableSettings.context, 0);
            dataTableModuleLoadingHandle.release();
            releasePageLoading();
        }
    };

    const table = $(tableSelector).DataTable(dataTableConfig);
    const wrapper = table.table?.().container?.();
    if (wrapper && typeof widthScopeSelector === 'string' && widthScopeSelector.trim() !== '') {
        wrapper.dataset.dtWidthScopeSelector = widthScopeSelector.trim();
    }
    if (wrapper) {
        wrapper.classList.toggle('dt-fixed-layout', fixedLayout === true);
    }
    __dtInstances.add(table);
    table.on('destroy.dt', () => {
        __dtInstances.delete(table);
    });
    $(tableSelector).one('error.dt', () => {
        dataTableModuleLoadingHandle.release();
        releasePageLoading();
    });
    bindStickyHeaderOffsetUpdates(table);
    updateStickyHeaderOffset(table);
    syncScrollHeadWidth(table);
    syncRenderedColumnWidths(table, tableColumns, preparedTableSettings.context);
    syncScrollHeadWidth(table);
    renderColumnResizeHandles(table, tableColumns);
    bindColumnResize(table, tableColumns, preparedTableSettings.context);
    scheduleApplyConfiguredColumnWidths(table, tableColumns, preparedTableSettings.context, 0);

    tableColumns.forEach((column, index) => {
        if (column?.visible === false) {
            table.column(index).visible(false, false);
        }
    });
    if (tableColumns.some((column) => column?.visible === false)) {
        scheduleAdjust(table, {
            draw: true,
            tableColumns,
            settingsContext: preparedTableSettings.context,
        });
    }

    if (density) {
        table.table().container()?.classList.add(`dt-density-${density}`);
    }

    table.on('xhr.dt draw.dt', function () {
        updateStickyHeaderOffset(table);
        syncScrollHeadWidth(table);
        syncRenderedColumnWidths(table, tableColumns, preparedTableSettings.context);
        syncScrollHeadWidth(table);
        renderColumnResizeHandles(table, tableColumns);
        applyConfiguredColumnWidths(table, tableColumns, preparedTableSettings.context);
        const info = table.page.info();
        const el = document.querySelector(`${tableSelector}_wrapper .dataTables_info`);
        if (el) {
            el.innerHTML =
                `${info.page + 1} / ${info.pages || 1} \uD398\uC774\uC9C0 ` +
                `(\uCD1D ${info.recordsTotal}\uAC74 / \uAC80\uC0C9 ${info.recordsDisplay}\uAC74)`;
        }

    });

    table.on('column-visibility.dt responsive-resize.dt', function () {
        updateStickyHeaderOffset(table);
        applyColumnHeaderClasses(table, tableColumns);
        renderColumnResizeHandles(table, tableColumns);
        scheduleAdjust(table, {
            draw: false,
            tableColumns,
            settingsContext: preparedTableSettings.context,
        });
    });

    table.on('length.dt', function (_event, _settings, nextLength) {
        if (!preparedTableSettings.context) {
            return;
        }

        const normalizedLength = Number(nextLength);
        if (!PAGE_LENGTH_MENU.includes(normalizedLength)) {
            return;
        }

        updateDataTableSettingsState(preparedTableSettings.context, {
            pageLength: normalizedLength,
        });
    });

    table.on('order.dt', function () {
        if (!preparedTableSettings.context) {
            return;
        }

        updateDataTableSettingsState(preparedTableSettings.context, {
            sortSettings: extractSortSettingsFromTable(table, tableColumns),
        });
    });

    onWindow('resize', () => {
        if (isBackgroundTableDuringModal(table)) {
            return;
        }

        updateStickyHeaderOffset(table);
        scheduleAdjust(table, {
            draw: false,
            tableColumns,
            settingsContext: preparedTableSettings.context,
        });
    }, { passive: true });

    bindCellSearchFill(table, tableSelector, {
        ...(cellSearchFill && typeof cellSearchFill === 'object' ? cellSearchFill : {}),
        tableId: searchTableId,
        enabled: cellSearchFill
    });

    if (shouldAddSelectionColumn) {
        bindSelectionColumn(table, tableSelector, selectedIds, rowIdField, isRowSelectable);
    }

    bindTableHighlight(tableSelector, table);
    const interactionConfig = interaction === true
        ? {}
        : (interaction && typeof interaction === 'object' ? interaction : null);
    const resolvedDblclickToEdit = interactionConfig?.dblclickToEdit !== undefined ? interactionConfig.dblclickToEdit : true;
    const resolvedRowClick = interactionConfig?.rowClick !== undefined ? interactionConfig.rowClick : true;
    const resolvedRowClickActive = interactionConfig?.rowClickActive !== undefined ? interactionConfig.rowClickActive : resolvedRowClick;
    const resolvedSelectionBridgeMode = interactionConfig?.selectionBridgeMode || 'sync-only';
    const resolvedSelectionSync = interactionConfig?.selectionSync !== undefined ? interactionConfig.selectionSync : true;
    if (interactionConfig) {
        table.__tableInteraction = createTableInteraction({
            table,
            adapter: 'datatable',
            enabled: true,
            rowIdField,
            activeRowClass: interactionConfig.activeRowClass || 'is-active',
            dblclickToEdit: resolvedDblclickToEdit !== false,
            rowClick: resolvedRowClick,
            rowClickActive: resolvedRowClickActive,
            selectionSync: resolvedSelectionSync,
            selectionBridgeMode: resolvedSelectionBridgeMode,
            debug: interactionConfig.debug === true,
            onRequestEdit: interactionConfig.onRequestEdit,
            onActiveRowChange: interactionConfig.onActiveRowChange,
            onSelectionChange: interactionConfig.onSelectionChange,
            onRedraw: interactionConfig.onRedraw,
            onDestroy: interactionConfig.onDestroy,
        });

        table.on('destroy.dt', () => {
            table.__tableInteraction?.destroy();
        });
    }

    attachDataTableSettings(table, preparedTableSettings.context);
    if (table.__dtTableSettings) {
        table.__dtTableSettings.refreshLayout = (options = {}) => refreshTableSettingsLayout(options);
        table.__dtTableSettings.tableColumns = tableColumns;
        table.__dtTableSettings.context = preparedTableSettings.context;
    }
    positionTableSettingsTrigger(table);
    bindTableSettingsTooltip(table);
    bindTruncatedCellTooltips(table, tableSelector);

    return table;
}

document.addEventListener('hidden.bs.modal', refreshAllDataTableLayouts);

export function bindTableHighlight(tableSelector, tableInstance) {
    const $table = $(tableSelector);
    $table.addClass('table-cross-highlight');
    const $wrapper = $table.closest('.dataTables_wrapper');

    $table.find('tbody')
        .off('click.dtTableHighlight')
        .on('click.dtTableHighlight', 'tr', function (event) {
        if (event.target.closest('a, button, input, select, textarea, .dropdown-menu, .reorder-handle, .drag-handle')) {
            return;
        }

        setTableSelectedRow(tableSelector, this);
    });

    $table.find('tbody')
        .off('mouseenter.dtTableHighlight')
        .on('mouseenter.dtTableHighlight', 'td', function () {
        const cell = tableInstance.cell(this);
        const idx = cell.index();

        if (!idx) return;

        const colIndex = idx.column;
        const visibleIndex = tableInstance.column(colIndex).index('visible');
        $wrapper.find('tbody tr').removeClass('row-highlight');
        $(this).closest('tr').addClass('row-highlight');

        $wrapper.find('td, th').removeClass('col-highlight');

        tableInstance.cells(null, colIndex).nodes().each(function (node) {
            $(node).addClass('col-highlight');
        });

        const headerNode = tableInstance.column(colIndex).header();
        $(headerNode).addClass('col-highlight');

        $wrapper
            .find('.dataTables_scrollHead th')
            .eq(visibleIndex)
            .addClass('col-highlight');
    });

    $table.find('tbody')
        .off('mouseleave.dtTableHighlight')
        .on('mouseleave.dtTableHighlight', function () {
        const $wrapper = $table.closest('.dataTables_wrapper');

        $wrapper.find('tbody tr').removeClass('row-highlight');
        $wrapper.find('td, th').removeClass('col-highlight');
    });
}

export function setTableSelectedRow(tableSelector, row) {
    const $table = $(tableSelector);
    const $wrapper = $table.closest('.dataTables_wrapper');

    $wrapper.find('tbody tr').removeClass('dt-row-selected table-active');

    if (row) {
        $(row).addClass('dt-row-selected');
    }
}

export function clearTableSelectedRows(tableSelector) {
    setTableSelectedRow(tableSelector, null);
}
