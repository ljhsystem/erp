import { createTableInteraction } from './index.js';
import { applyVisibilityToTable, attachDataTableSettings, prepareDataTableSettingsColumns, updateDataTableViewState } from '../datatable/dataTableSettings.js';
import { applyActorDataTableColumn } from '../actor.js';
import { hideDeleteProgress, updateDeleteProgress } from '../delete-progress.js';
import { markTrashButtonsHasData } from '../trash/trash-button-state.js';
import {
    DATA_TABLE_COLUMN_WIDTH_MAX,
    DATA_TABLE_COLUMN_WIDTH_MIN,
    DATA_TABLE_PAGE_LENGTH_OPTIONS,
    isDataTableColumnWidthResizable,
    normalizeDataTableColumnWidth,
    normalizeDataTablePageLength,
    resolveDataTableColumnWidthKey,
} from '../datatable/dataTableViewPolicy.js';

const __dtAdjustState = new WeakMap();
const __dtInstances = new Set();
const __dtResizeState = new WeakMap();
const __dtContainerResizeState = new WeakMap();
const DEFAULT_PAGE_LENGTH = 100;
const PAGE_LENGTH_MENU = DATA_TABLE_PAGE_LENGTH_OPTIONS;
const DELETE_PROGRESS_CHUNK_SIZE = 500;
const LAYOUT_RESIZE_SETTLE_MS = 120;
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

function resolvePlainColumnTitle(column = {}) {
    const explicitTitle = String(column.tooltipText ?? column.plainTitle ?? column.settingsTitle ?? '').trim();
    if (explicitTitle !== '') return explicitTitle;

    const host = document.createElement('div');
    host.innerHTML = String(column.title ?? '');
    if (host.querySelector('input, button, select, textarea, a, svg, i')) return '';
    return String(host.textContent || '').replace(/\s+/g, ' ').trim();
}

function resolveHeaderColumnIndex(table, cell, columns = [], visibleIndex = -1) {
    const dataIndexes = String(cell?.dataset?.dtColumn || '')
        .split(',')
        .map((value) => Number.parseInt(value.trim(), 10))
        .filter(Number.isInteger);
    if (dataIndexes.length === 1 && columns[dataIndexes[0]]) return dataIndexes[0];

    const originalIndex = columns.findIndex((_column, index) => table.column(index).header() === cell);
    if (originalIndex >= 0) return originalIndex;

    const visibleIndexes = table.columns(':visible').indexes().toArray();
    const runtimeIndex = visibleIndexes[visibleIndex];
    if (Number.isInteger(runtimeIndex) && columns[runtimeIndex]) return runtimeIndex;

    const connectedKey = String(cell?.dataset?.dtColumnKey || '').trim();
    return connectedKey === ''
        ? -1
        : columns.findIndex((column) => resolveSettingsColumnKeys(column).includes(connectedKey));
}

function applyColumnHeaderMetadata(table, columns = []) {
    if (!table || !Array.isArray(columns)) return;
    const headers = [
        table.table().header(),
        table.table().container()?.querySelector('.dataTables_scrollHead thead'),
        table.table().container()?.querySelector('.fixedHeader-floating thead'),
        table.table().container()?.querySelector('.fixedHeader-locked thead'),
    ].filter(Boolean);

    headers.forEach((header) => {
        Array.from(header.querySelectorAll('th')).forEach((cell, visibleIndex) => {
            const index = resolveHeaderColumnIndex(table, cell, columns, visibleIndex);
            const title = resolvePlainColumnTitle(columns[index] || {});
            const key = index >= 0 ? resolveSettingsColumnKey(columns[index]) : '';
            if (title === '' || key === '') {
                disposeCellTooltip(cell);
                cell.removeAttribute('data-dt-column-key');
                cell.removeAttribute('data-dt-full-title');
                cell.removeAttribute('data-dt-virtual-column');
                cell.removeAttribute('aria-label');
                return;
            }
            if (cell.dataset.dtColumnKey !== key || cell.dataset.dtFullTitle !== title) {
                disposeCellTooltip(cell);
            }
            cell.dataset.dtColumnKey = key;
            cell.dataset.dtFullTitle = title;
            cell.dataset.dtVirtualColumn = columns[index]?.__dtColumnKind === 'virtual' ? 'true' : 'false';
            cell.setAttribute('aria-label', title);
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
    if (typeof column?.settingsKey === 'string' && column.settingsKey.trim() !== '') {
        return column.settingsKey.trim();
    }
    if (typeof column?.key === 'string' && column.key.trim() !== '') {
        return column.key.trim();
    }
    if (typeof column?.name === 'string' && column.name.trim() !== '') {
        return column.name.trim();
    }
    if (typeof column?.data === 'string' && column.data.trim() !== '') {
        return column.data.trim();
    }
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
    const utilityTitle = {
        __select: '\uC120\uD0DD',
        __reorder: '\uC21C\uC11C \uC774\uB3D9',
        __actions: '\uAD00\uB9AC',
    }[utilitySettingsKey] || '';
    if (utilityTitle !== '' && String(next.settingsTitle || '').trim() === '') {
        next.settingsTitle = utilityTitle;
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

function normalizeActorColumns(columns = []) {
    return columns.map(applyActorDataTableColumn);
}

function parseJsonResponse(response) {
    return response.text().then((text) => {
        if (!text) {
            return {};
        }

        try {
            return JSON.parse(text);
        } catch (_error) {
            return {
                success: false,
                message: text.includes('<')
                    ? 'Request failed: server response is not JSON.'
                    : text,
            };
        }
    });
}

function toArrayValue(value = []) {
    if (Array.isArray(value)) {
        return value;
    }

    if (value === null || value === undefined) {
        return [];
    }

    return [value];
}

function buildDeletePayloadFromContext(deletePayload, context = {}) {
    if (typeof deletePayload === 'function') {
        const built = deletePayload(context);
        return (built && typeof built === 'object') ? { ...built } : {};
    }

    if (deletePayload && typeof deletePayload === 'object') {
        return { ...deletePayload };
    }

    return {};
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

    return parseJsonResponse(response);
}

async function postBulkDeleteJson(url, payload = []) {
    if (typeof AppAjax.postBulkJson === 'function') {
        const normalizedPayload = (payload && typeof payload === 'object' && !Array.isArray(payload))
            ? payload
            : {
                ids: toArrayValue(payload),
                seed_row_ids: toArrayValue(payload),
                evidence_ids: toArrayValue(payload),
            };

        return AppAjax.postBulkJson(url, normalizedPayload);
    }

    const normalizedPayload = (payload && typeof payload === 'object' && !Array.isArray(payload))
        ? { ...payload }
        : {
            ids: toArrayValue(payload),
            seed_row_ids: toArrayValue(payload),
            evidence_ids: toArrayValue(payload),
        };

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            ...normalizedPayload,
            ids: toArrayValue(normalizedPayload.ids),
            seed_row_ids: toArrayValue(normalizedPayload.seed_row_ids || normalizedPayload.ids),
            evidence_ids: toArrayValue(normalizedPayload.evidence_ids || normalizedPayload.ids),
        }),
    });

    return parseJsonResponse(response);
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

// Release the module-level page-loading hold after the script is initialized.
// Table-specific loading handles still cover actual DataTable creation.
if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
    window.requestAnimationFrame(() => {
        dataTableModuleLoadingHandle.release();
    });
} else {
    dataTableModuleLoadingHandle.release();
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

async function softDeleteSelectedRows({
    deleteApi,
    ids,
    table,
    selectedIds,
    buttonNode,
    bulkDelete = false,
    deletePayload = null,
}) {
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
                const payload = buildDeletePayloadFromContext(deletePayload, {
                    ids: chunk,
                    bulk: true,
                });
                const result = await postBulkDeleteJson(deleteApi, {
                    ...payload,
                    ids: toArrayValue(chunk),
                    seed_row_ids: toArrayValue(payload.seed_row_ids || chunk),
                    evidence_ids: toArrayValue(payload.evidence_ids || chunk),
                });
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
                const payload = buildDeletePayloadFromContext(deletePayload, {
                    id,
                    bulk: false,
                });
                const result = await postFormJson(deleteApi, {
                    ...payload,
                    id,
                });
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
        if (deletedCount > 0) markTrashButtonsHasData(deletedCount);
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
        key: settingsKey,
        data: null,
        title: `<input type="checkbox" class="form-check-input dt-select-all" id="${escapeAttr(checkAllId)}" aria-label="\uC804\uCCB4 \uC120\uD0DD">`,
        tooltipText: '\uC120\uD0DD',
        settingsTitle: '\uC120\uD0DD',
        className: 'dt-select-column no-colvis text-center',
        headerClassName: 'dt-select-column no-colvis text-center',
        orderable: false,
        searchable: false,
        defaultContent: '',
        isSelectionColumn: true,
        settingsKey,
        __dtColumnKind: 'virtual',
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
        if (!Array.isArray(item)) {
            return item;
        }

        const columnIndex = normalizeColumnIndex(item[0]);
        if (columnIndex === null || columnIndex < insertIndex) {
            return item;
        }

        return [columnIndex + 1, ...item.slice(1)];
    });
}

function normalizeSortSettingsKey(value = '') {
    return String(value || '').trim();
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

function normalizeColumnIndex(value) {
    const index = Number(value);
    return Number.isInteger(index) && index >= 0 ? index : null;
}

function resolveSettingsColumnKeys(column = {}) {
    return Array.from(new Set([
        column?.__dtSettingsKey,
        column?.settingsKey,
        column?.key,
        column?.sourceField,
        column?.system_field_name,
        column?.original_column_key,
        column?.name,
        column?.data,
    ].map((value) => String(value || '').trim()).filter(Boolean)));
}

function normalizeSortSettingsInput(sortSettings = []) {
    return (Array.isArray(sortSettings) ? sortSettings : [])
        .map((item) => {
            if (Array.isArray(item)) {
                const key = normalizeSortSettingsKey(item[0]);
                if (key === '') {
                    return null;
                }

                return {
                    key,
                    dir: normalizeSortDirection(item[1]),
                };
            }

            const key = normalizeSortSettingsKey(item?.key);
            if (key === '') {
                return null;
            }

            return {
                key,
                dir: normalizeSortDirection(item?.dir),
            };
        })
        .filter(Boolean);
}

function buildOrderFromSortSettings(sortSettings = [], tableColumns = []) {
    const indexByKey = new Map();
    tableColumns.forEach((column, index) => {
        resolveSettingsColumnKeys(column).forEach((key) => {
            if (!indexByKey.has(key)) {
                indexByKey.set(key, index);
            }
        });
    });

    return normalizeSortSettingsInput(sortSettings)
        .map((item) => {
            const key = normalizeSortSettingsKey(item?.key);
            if (key === '' || !indexByKey.has(key)) {
                return null;
            }

            return [indexByKey.get(key), normalizeSortDirection(item?.dir)];
        })
        .filter(Array.isArray);
}

function sortSettingsFromLegacyDefaultOrder(defaultOrder = [], referenceColumns = [], shouldShift = false, insertIndex = 0) {
    const shiftedOrder = shiftOrderForSelection(defaultOrder, shouldShift, insertIndex);

    return (Array.isArray(shiftedOrder) ? shiftedOrder : [])
        .map((item) => {
            if (!Array.isArray(item)) {
                return null;
            }

            const columnIndex = normalizeColumnIndex(item[0]);
            if (columnIndex === null) {
                return null;
            }

            const column = referenceColumns[columnIndex];
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

function buildOrderFromDefaultOrder(
    defaultOrder = [],
    tableColumns = [],
    referenceColumns = [],
    shouldShift = false,
    insertIndex = 0
) {
    const normalizedDefaultSortSettings = normalizeSortSettingsInput(defaultOrder);
    if (normalizedDefaultSortSettings.length > 0) {
        return buildOrderFromSortSettings(normalizedDefaultSortSettings, tableColumns);
    }

    const legacyDefaultSortSettings = sortSettingsFromLegacyDefaultOrder(
        defaultOrder,
        referenceColumns,
        shouldShift,
        insertIndex
    );

    if (legacyDefaultSortSettings.length > 0) {
        return buildOrderFromSortSettings(legacyDefaultSortSettings, tableColumns);
    }

    return shiftOrderForSelection(defaultOrder, shouldShift, insertIndex);
}

function extractSortSettingsFromTable(table, tableColumns = []) {
    if (!table?.order) {
        return [];
    }

    return (table.order() || [])
        .map((item) => {
            if (!Array.isArray(item)) {
                return null;
            }

            const columnIndex = normalizeColumnIndex(item[0]);
            if (columnIndex === null) {
                return null;
            }

            const column = tableColumns[columnIndex];
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

function resolveColumnWidthKey(column = {}, index = 0) {
    return resolveDataTableColumnWidthKey(column, index);
}

function isColumnWidthConfigurable(column = {}) {
    return isDataTableColumnWidthResizable(column);
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

function isValidDataTableColumnIndex(table, index) {
    if (!Number.isInteger(index) || index < 0) {
        return false;
    }

    const settings = table?.settings?.()?.[0];
    const columns = Array.isArray(settings?.aoColumns) ? settings.aoColumns : [];
    return index < columns.length;
}

function getHeaderNodesByIndex(table, index) {
    if (!isValidDataTableColumnIndex(table, index)) {
        return [];
    }

    return [
        table?.column?.(index)?.header?.() || null,
        getScrollHeaderNodeByIndex(table, index),
    ].filter(Boolean);
}

function getBodyCellNodesByIndex(table, index) {
    if (!isValidDataTableColumnIndex(table, index)) {
        return [];
    }

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
    if (!isValidDataTableColumnIndex(table, index)) {
        return -1;
    }

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
    const normalizedWidth = normalizeDataTableColumnWidth(widthPx);
    if (!Number.isFinite(normalizedWidth) || normalizedWidth <= 0) {
        return;
    }

    const widthValue = `${normalizedWidth}px`;
    const applyWidth = (node) => {
        if (!node?.style) {
            return;
        }
        const widthMatches = node.style.getPropertyValue('width') === widthValue
            && node.style.getPropertyPriority('width') === 'important';
        const minWidthMatches = node.style.getPropertyValue('min-width') === widthValue
            && node.style.getPropertyPriority('min-width') === 'important';
        const maxWidthMatches = node.style.getPropertyValue('max-width') === widthValue
            && node.style.getPropertyPriority('max-width') === 'important';
        const boxSizingMatches = node.style.getPropertyValue('box-sizing') === 'border-box';

        if (!widthMatches) {
            node.style.setProperty('width', widthValue, 'important');
        }
        if (!minWidthMatches) {
            node.style.setProperty('min-width', widthValue, 'important');
        }
        if (!maxWidthMatches) {
            node.style.setProperty('max-width', widthValue, 'important');
        }
        if (!boxSizingMatches) {
            node.style.setProperty('box-sizing', 'border-box');
        }
    };

    getHeaderNodesByIndex(table, index).forEach(applyWidth);
    getBodyCellNodesByIndex(table, index).forEach(applyWidth);
    getColgroupNodesByIndex(table, index).forEach(applyWidth);
}

function clearAppliedColumnWidths(table, tableColumns = []) {
    tableColumns.forEach((_column, index) => {
        [
            ...getHeaderNodesByIndex(table, index),
            ...getBodyCellNodesByIndex(table, index),
            ...getColgroupNodesByIndex(table, index),
        ].forEach((node) => {
            if (!node?.style) {
                return;
            }
            node.style.removeProperty('width');
            node.style.removeProperty('min-width');
            node.style.removeProperty('max-width');
        });
    });
}

function readExplicitNodeWidth(node) {
    if (!node?.style) {
        return 0;
    }

    const candidates = [
        parseFloat(node.style.width || '0'),
        parseFloat(node.style.minWidth || '0'),
        parseFloat(node.style.maxWidth || '0'),
    ];

    for (const value of candidates) {
        if (Number.isFinite(value) && value > 0) {
            return value;
        }
    }

    return 0;
}

function measureAppliedColumnWidth(table, index) {
    const colgroupNodes = getColgroupNodesByIndex(table, index);
    const headerNodes = getHeaderNodesByIndex(table, index);
    const bodyNodes = getBodyCellNodesByIndex(table, index);
    const candidates = [
        ...colgroupNodes,
        ...headerNodes,
        ...bodyNodes,
    ];

    for (const node of candidates) {
        const explicitWidth = readExplicitNodeWidth(node);
        if (Number.isFinite(explicitWidth) && explicitWidth > 0) {
            return Math.round(explicitWidth);
        }
    }

    const measuredWidth = candidates.reduce((maxWidth, node) => {
        if (!node) {
            return maxWidth;
        }

        const rectWidth = Math.ceil(node.getBoundingClientRect?.().width || 0);
        const nextWidth = Math.max(
            Number.isFinite(rectWidth) ? rectWidth : 0
        );

        return nextWidth > maxWidth ? nextWidth : maxWidth;
    }, 0);

    return Math.ceil(measuredWidth);
}

function freezeVisibleColumnWidths(table, tableColumns = []) {
    tableColumns.forEach((column, index) => {
        if (!isValidDataTableColumnIndex(table, index)) {
            return;
        }
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
    const columnWidths = settingsContext?.viewState?.columnWidths;
    const wrapper = table?.table?.().container?.();
    if (
        !table
        || wrapper?.classList.contains('dt-fixed-layout')
        || !columnWidths
        || typeof columnWidths !== 'object'
    ) {
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

function fitVisibleColumnWidthsToScope(table, tableColumns = [], settingsContext = null) {
    const wrapper = table?.table?.().container?.();
    if (wrapper?.dataset.dtFitColumnsToScope === 'false') {
        wrapper.classList.remove('dt-viewport-fitted');
        return;
    }
    const scope = resolveDataTableResizeScope(wrapper);
    const availableWidth = Math.floor(Math.min(
        wrapper?.clientWidth || Number.POSITIVE_INFINITY,
        scope?.clientWidth || scope?.getBoundingClientRect?.().width || Number.POSITIVE_INFINITY
    ));
    if (!wrapper || wrapper.classList.contains('dt-fixed-layout') || !Number.isFinite(availableWidth) || availableWidth <= 0) {
        return;
    }

    const widths = collectVisibleColumnWidths(table, tableColumns, settingsContext);
    const totalWidth = widths.reduce((sum, item) => sum + item.width, 0);
    const targetWidth = Math.max(0, availableWidth - 2);
    if (!widths.length || totalWidth <= targetWidth) {
        wrapper.classList.remove('dt-viewport-fitted');
        return;
    }

    const minimumFor = (item) => {
        const column = tableColumns[item.index] || {};
        return column.__dtColumnKind === 'virtual' || /reorder|select|checkbox|manage|action/i.test(
            `${column.settingsKey || ''} ${column.className || ''}`
        ) ? 32 : 40;
    };
    const fitted = widths.map(item => ({ ...item, fittedWidth: minimumFor(item) }));
    let remaining = targetWidth - fitted.reduce((sum, item) => sum + item.fittedWidth, 0);
    const flexibleTotal = fitted.reduce((sum, item) => sum + Math.max(0, item.width - item.fittedWidth), 0);
    if (remaining > 0 && flexibleTotal > 0) {
        fitted.forEach(item => {
            const flexible = Math.max(0, item.width - item.fittedWidth);
            const addition = Math.floor(remaining * (flexible / flexibleTotal));
            item.fittedWidth += addition;
        });
        remaining = targetWidth - fitted.reduce((sum, item) => sum + item.fittedWidth, 0);
        for (let index = 0; remaining > 0 && fitted.length; index = (index + 1) % fitted.length) {
            fitted[index].fittedWidth += 1;
            remaining -= 1;
        }
    }

    fitted.forEach(item => applyColumnWidthPx(table, item.index, item.fittedWidth));
    wrapper.classList.add('dt-viewport-fitted');
}

function reapplyResponsiveColumnWidths(table, tableColumns = [], settingsContext = null) {
    const wrapper = table?.table?.().container?.();
    if (wrapper?.classList.contains('dt-viewport-fitted')) {
        clearAppliedColumnWidths(table, tableColumns);
        wrapper.classList.remove('dt-viewport-fitted');
    }
    applyConfiguredColumnWidths(table, tableColumns, settingsContext);
    fitVisibleColumnWidthsToScope(table, tableColumns, settingsContext);
}

function collectVisibleColumnWidths(table, tableColumns = [], settingsContext = null) {
    const configuredWidths = settingsContext?.viewState?.columnWidths && typeof settingsContext.viewState.columnWidths === 'object'
        ? settingsContext.viewState.columnWidths
        : {};

    return tableColumns.reduce((acc, column, index) => {
        if (!isValidDataTableColumnIndex(table, index)) {
            return acc;
        }
        if (table.column(index).visible() === false) {
            return acc;
        }

        const key = resolveColumnWidthKey(column, index);
        const configuredWidth = Number(configuredWidths?.[key]);
        const width = Number.isFinite(configuredWidth) && configuredWidth > 0
            ? configuredWidth
            : measureAppliedColumnWidth(table, index);
        if (!Number.isFinite(width) || width <= 0) {
            return acc;
        }

        acc.push({
            index,
            key,
            width,
            configured: Number.isFinite(configuredWidth) && configuredWidth > 0,
        });
        return acc;
    }, []);
}

function scheduleApplyConfiguredColumnWidths(table, tableColumns = [], settingsContext = null, delay = 0) {
    window.setTimeout(() => {
        window.requestAnimationFrame(() => {
            reconcileDataTableLayout(table, tableColumns, settingsContext);
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
            handle.setAttribute('title', '드래그하여 열 너비 조정 · 더블클릭하여 내용에 맞춤');
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
        handle: null,
    };
    __dtResizeState.set(wrapper, dragState);
    const RESIZE_HOVER_BOUNDARY_PX = 4;

    const clearResizeHoverState = () => {
        wrapper.querySelectorAll('.dt-column-resizable.dt-column-resize-hover, .dt-column-resizable.dt-column-resize-active')
            .forEach((headerNode) => {
                headerNode.classList.remove('dt-column-resize-hover', 'dt-column-resize-active');
            });
    };

    const resolveResizeHeader = (event) => {
        const header = event.target?.closest?.('thead th.dt-column-resizable');
        if (!header || !wrapper.contains(header)) {
            return null;
        }

        const handle = header.querySelector('.dt-column-resizer');
        if (!handle) {
            return null;
        }

        const rect = header.getBoundingClientRect?.();
        if (!rect || !Number.isFinite(rect.right) || !Number.isFinite(rect.left)) {
            return null;
        }

        const distanceFromRight = rect.right - event.clientX;
        if (distanceFromRight < -RESIZE_HOVER_BOUNDARY_PX || distanceFromRight > RESIZE_HOVER_BOUNDARY_PX) {
            return null;
        }

        return { header, handle };
    };

    const syncResizeHoverState = (event) => {
        if (dragState.active) {
            return dragState.handle;
        }

        clearResizeHoverState();
        const resolved = resolveResizeHeader(event);
        if (!resolved) {
            return null;
        }

        resolved.header.classList.add('dt-column-resize-hover');
        return resolved.handle;
    };

    const stopEvent = (event) => {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation?.();
    };

    const measureCellContentWidth = (cell, includeHeaderControls = false) => {
        if (!cell) return 0;

        const style = window.getComputedStyle(cell);
        const canvas = measureCellContentWidth.canvas
            || (measureCellContentWidth.canvas = document.createElement('canvas'));
        const context = canvas.getContext('2d');
        if (!context) return Math.ceil(cell.scrollWidth || 0);

        context.font = style.font || [
            style.fontStyle,
            style.fontVariant,
            style.fontWeight,
            style.fontSize,
            style.fontFamily,
        ].filter(Boolean).join(' ');
        const text = String(cell.innerText || cell.textContent || '')
            .replace(/\s+/g, ' ')
            .trim();
        const horizontalBox = ['paddingLeft', 'paddingRight', 'borderLeftWidth', 'borderRightWidth']
            .reduce((sum, property) => sum + (parseFloat(style[property]) || 0), 0);
        const childWidth = Array.from(cell.children || []).reduce((maxWidth, child) => {
            if (child.classList?.contains('dt-column-resizer')) return maxWidth;
            return Math.max(maxWidth, Math.ceil(child.scrollWidth || child.getBoundingClientRect?.().width || 0));
        }, 0);
        const textWidth = Math.ceil(context.measureText(text).width);
        const controlAllowance = includeHeaderControls ? 32 : 8;
        return Math.ceil(Math.max(textWidth, childWidth) + horizontalBox + controlAllowance);
    };

    const measureAutoFitWidth = (index, column = {}) => {
        if (!isValidDataTableColumnIndex(table, index)) return 0;

        const headerNodes = getHeaderNodesByIndex(table, index);
        const bodyNodes = getBodyCellNodesByIndex(table, index);
        const naturalWidth = Math.max(
            ...headerNodes.map(node => measureCellContentWidth(node, true)),
            ...bodyNodes.map(node => measureCellContentWidth(node, false)),
            0
        );
        const minWidth = Math.max(DATA_TABLE_COLUMN_WIDTH_MIN, Number(column.autoFitMinWidth) || 48);
        const maxWidth = Math.min(
            DATA_TABLE_COLUMN_WIDTH_MAX,
            Math.max(minWidth, Number(column.autoFitMaxWidth) || 640)
        );
        return Math.min(maxWidth, Math.max(minWidth, naturalWidth));
    };

    const persistColumnWidth = (index, key, widthPx) => {
        const normalizedWidth = normalizeDataTableColumnWidth(widthPx);
        if (!Number.isFinite(normalizedWidth) || !key) return;

        applyColumnWidthPx(table, index, normalizedWidth);
        updateDataTableViewState(settingsContext, {
            columnWidths: {
                ...(settingsContext.viewState?.columnWidths || {}),
                [key]: normalizedWidth,
            },
        });
        scheduleAdjust(table, {
            draw: false,
            tableColumns,
            settingsContext,
        });
    };

    const finishResize = () => {
        if (!dragState.active) {
            return;
        }

        dragState.active = false;
        dragState.pointerId = null;
        dragState.handle = null;
        document.body.classList.remove('dt-column-resizing');
        clearResizeHoverState();

        if (!dragState.key || dragState.index < 0) {
            return;
        }

        const widthPx = measureAppliedColumnWidth(table, dragState.index);
        if (!Number.isFinite(widthPx) || widthPx <= 0) {
            return;
        }

        persistColumnWidth(dragState.index, dragState.key, widthPx);
    };

    const onPointerMove = (event) => {
        if (!dragState.active) {
            syncResizeHoverState(event);
            return;
        }

        if (typeof event.buttons === 'number' && event.buttons === 0) {
            finishResize();
            return;
        }

        stopEvent(event);
        const nextWidth = Math.min(
            DATA_TABLE_COLUMN_WIDTH_MAX,
            Math.max(DATA_TABLE_COLUMN_WIDTH_MIN, dragState.startWidth + (event.clientX - dragState.startX))
        );
        applyColumnWidthPx(table, dragState.index, nextWidth);
    };

    const onPointerUp = (event) => {
        if (!dragState.active) {
            return;
        }
        stopEvent(event);
        finishResize();
    };

    wrapper.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('.dt-column-resizer') || syncResizeHoverState(event);
        if (!handle) {
            return;
        }

        const headerNode = handle.closest('th.dt-column-resizable');
        if (!headerNode) {
            return;
        }

        const index = Number(handle.dataset.columnIndex);
        const column = tableColumns[index];
        const key = resolveColumnWidthKey(column, index);
        if (!isValidDataTableColumnIndex(table, index) || !key || !indexMap.has(key)) {
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
        dragState.handle = handle;
        document.body.classList.add('dt-column-resizing');
        clearResizeHoverState();
        headerNode.classList.add('dt-column-resize-active');
        handle.setPointerCapture?.(event.pointerId);
    }, true);

    wrapper.addEventListener('click', (event) => {
        if (event.target.closest('.dt-column-resizer')) {
            stopEvent(event);
        }
    }, true);

    wrapper.addEventListener('dblclick', (event) => {
        const handle = event.target.closest('.dt-column-resizer') || syncResizeHoverState(event);
        if (!handle) return;

        const index = Number(handle.dataset.columnIndex);
        const column = tableColumns[index];
        const key = resolveColumnWidthKey(column, index);
        if (!isValidDataTableColumnIndex(table, index) || !key || !indexMap.has(key)) return;

        stopEvent(event);
        if (dragState.active) finishResize();
        if (wrapper.querySelector('.dataTables_scroll')) {
            freezeVisibleColumnWidths(table, tableColumns);
        }
        persistColumnWidth(index, key, measureAutoFitWidth(index, column));
    }, true);

    wrapper.addEventListener('pointermove', syncResizeHoverState, true);
    wrapper.addEventListener('pointerleave', clearResizeHoverState, true);
    wrapper.addEventListener('pointercancel', clearResizeHoverState, true);

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
            if (draw) {
                table.draw(false);
            }
            reconcileDataTableLayout(table, tableColumns, settingsContext);
        } catch (err) {
            console.error('[data-table] scheduleAdjust failed:', err);
        }
    });
}

function reconcileDataTableLayout(table, tableColumns = [], settingsContext = null) {
    if (!table) {
        return;
    }

    const wrapper = table.table?.().container?.();
    if (wrapper?.classList.contains('dt-viewport-fitted')) clearAppliedColumnWidths(table, tableColumns);
    wrapper?.classList.remove('dt-viewport-fitted');
    table.columns.adjust();
    applyConfiguredColumnWidths(table, tableColumns, settingsContext);
    fitVisibleColumnWidthsToScope(table, tableColumns, settingsContext);
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
    const draw = options?.draw === true;
    const tableColumns = table.__dtTableSettings?.tableColumns || [];
    const settingsContext = table.__dtTableSettings?.context || null;
    delays.forEach((delay, index) => {
        window.setTimeout(() => {
            try {
                updateStickyHeaderOffset(table);
                scheduleAdjust(table, {
                    draw: draw && index === 0,
                    tableColumns,
                    settingsContext,
                });
            } catch (err) {
                console.error('[data-table] refreshDataTableLayout failed:', err);
            }
        }, Math.max(0, Number(delay) || 0));
    });
}

export function setDataTableFixedLayout(table, enabled) {
    const wrapper = table?.table?.().container?.();
    if (!table || !wrapper) {
        return;
    }

    const fixed = enabled === true;
    const tableColumns = table.__dtTableSettings?.tableColumns || [];
    wrapper.classList.toggle('dt-fixed-layout', fixed);
    if (fixed) {
        clearAppliedColumnWidths(table, tableColumns);
    }
    refreshDataTableLayout(table, { draw: false, delays: [0] });
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

function resolveDataTableResizeScope(wrapper) {
    if (!wrapper) return null;

    const configuredSelector = String(wrapper.dataset.dtWidthScopeSelector || '').trim();
    if (configuredSelector) {
        try {
            const configuredScope = wrapper.closest(configuredSelector);
            if (configuredScope) return configuredScope;
        } catch (_error) {
            // 잘못된 화면별 selector가 공용 폭 감지를 중단시키지 않도록 기본 scope를 사용한다.
        }
    }

    return wrapper.closest('.table-box, .dt-content-stack, .content-area, .dt-page-shell')
        || wrapper.parentElement;
}

function markDataTablePageShell(wrapper) {
    if (!wrapper) return;

    const shell = wrapper.closest('.dt-page-shell, .dt-content-stack, .content-area, .settings-main')
        || wrapper.closest('main')
        || wrapper.parentElement;
    shell?.classList.add('dt-responsive-page-shell');

    const titledShell = wrapper.closest('.dt-page-shell, [class$="-page"], [class*="-page "]');
    titledShell?.classList.add('dt-responsive-page-shell');
}

function bindDataTableContainerResize(table) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper || __dtContainerResizeState.has(wrapper)) return;

    markDataTablePageShell(wrapper);
    const scope = resolveDataTableResizeScope(wrapper);
    let settleTimer = null;
    let lastWidth = Math.round(scope?.getBoundingClientRect?.().width || 0);
    const refresh = ({ immediate = false } = {}) => {
        if (settleTimer) {
            clearTimeout(settleTimer);
            settleTimer = null;
        }

        const run = () => {
            settleTimer = null;
            const nextWidth = Math.round(scope?.getBoundingClientRect?.().width || 0);
            if (nextWidth > 0) lastWidth = nextWidth;
            refreshDataTableLayout(table, { draw: false, delays: [0] });
        };

        if (immediate) {
            requestAnimationFrame(run);
            return;
        }

        settleTimer = window.setTimeout(run, LAYOUT_RESIZE_SETTLE_MS);
    };
    const onViewportResize = () => refresh();
    const onSidebarToggled = () => refresh({ immediate: true });
    const observer = typeof ResizeObserver === 'function' && scope
        ? new ResizeObserver((entries) => {
            const width = Math.round(entries[0]?.contentRect?.width || scope.getBoundingClientRect().width || 0);
            if (width === lastWidth) return;
            lastWidth = width;
            refresh();
        })
        : null;

    observer?.observe(scope);
    if (!observer) {
        window.addEventListener('resize', onViewportResize, { passive: true });
    }
    document.addEventListener('sidebar:toggled', onSidebarToggled);
    __dtContainerResizeState.set(wrapper, {
        scope,
        destroy() {
            if (settleTimer) clearTimeout(settleTimer);
            observer?.disconnect();
            if (!observer) window.removeEventListener('resize', onViewportResize);
            document.removeEventListener('sidebar:toggled', onSidebarToggled);
        },
    });
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
        metaDomain: String(baseConfig.metaDomain || '').trim(),
        userSettingPageKey: String(baseConfig.userSettingPageKey || '').trim(),
        metaUrl: String(baseConfig.metaUrl || '').trim(),
        metaCacheKey: String(baseConfig.metaCacheKey || '').trim(),
        metaColumns: Array.isArray(baseConfig.metaColumns) ? baseConfig.metaColumns : [],
        pageLength: Number(baseConfig.pageLength || pageLength) || null,
        defaultSortSettings: Array.isArray(baseConfig.defaultSortSettings) ? baseConfig.defaultSortSettings : [],
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
        createDataTableTooltip(trigger, {
            container: 'body',
            placement: 'bottom',
            trigger: 'hover focus',
        });
    }
}

function createDataTableTooltip(element, config) {
    if (!(element instanceof HTMLElement) || !element.isConnected || !window.bootstrap?.Tooltip) {
        return null;
    }

    try {
        return window.bootstrap.Tooltip.getOrCreateInstance(element, config);
    } catch (_error) {
        try {
            window.bootstrap.Tooltip.getInstance(element)?.dispose();
        } catch (_disposeError) {
            // 이미 분리된 Tooltip 인스턴스는 추가 처리 없이 정리한다.
        }
        element.removeAttribute('data-bs-toggle');
        element.removeAttribute('data-bs-original-title');
        return null;
    }
}

function showDataTableTooltip(element, config) {
    const tooltip = createDataTableTooltip(element, config);
    if (!tooltip) {
        return;
    }

    try {
        tooltip.show();
    } catch (_error) {
        disposeCellTooltip(element);
    }
}

function isCellTextTruncated(cell) {
    if (!(cell instanceof HTMLElement)) {
        return false;
    }

    return Math.ceil(cell.scrollWidth) > Math.ceil(cell.clientWidth + 1);
}

function isBodyTextCell(cell, column = {}) {
    if (!(cell instanceof HTMLElement)
        || column.truncate === false
        || column.multiline === true
        || column.tooltip === false) return false;

    const excludedClasses = [
        'dt-select-column', 'dt-reorder-column', 'reorder-handle', 'dt-action-column',
        'dt-status-column', 'dt-no-truncate', 'text-wrap', 'text-break',
    ];
    if (excludedClasses.some((className) => cell.classList.contains(className))) return false;

    return !cell.querySelector([
        'input', 'select', 'textarea', 'button', '.dropdown-menu', '.badge', '.form-switch',
        '.progress', 'img', 'svg', 'canvas', 'video', '[role="progressbar"]',
    ].join(','));
}

function syncBodyTextCellClasses(table, columns = []) {
    const body = table?.table?.().body?.();
    if (!body) return;
    Array.from(body.rows || []).forEach((row) => {
        Array.from(row.cells || []).forEach((cell) => {
            const index = table.cell(cell).index()?.column;
            const column = Number.isInteger(index) ? (columns[index] || {}) : {};
            cell.classList.toggle('dt-text-truncate', isBodyTextCell(cell, column));
        });
    });
}

function resolveCellTooltipText(cell) {
    if (!(cell instanceof HTMLElement)) {
        return '';
    }

    return String(cell.textContent || '').replace(/\s+/g, ' ').trim();
}

function resolveBodyTooltipText(table, cell, column = {}) {
    const displayed = resolveCellTooltipText(cell);
    const row = table.row(cell.closest('tr')).data();
    if (typeof column.tooltipText === 'function') {
        return String(column.tooltipText(row, displayed) ?? '').replace(/\s+/g, ' ').trim();
    }

    const raw = table.cell(cell).data();
    if (['string', 'number', 'bigint'].includes(typeof raw)) {
        const rawText = String(raw).replace(/\s+/g, ' ').trim();
        if (!/[<>]/.test(rawText) && rawText === displayed) return rawText;
    }
    return displayed;
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
    if (cell.dataset.dtFullTitle) {
        cell.setAttribute('aria-label', cell.dataset.dtFullTitle);
    } else {
        cell.removeAttribute('aria-label');
    }
}

function disposeTruncatedTableTooltips(wrapper) {
    if (!(wrapper instanceof HTMLElement)) {
        return;
    }

    wrapper.querySelectorAll('th[data-bs-trigger="manual"], td[data-bs-trigger="manual"]')
        .forEach((cell) => disposeCellTooltip(cell));
}

function bindTruncatedHeaderTooltips(table) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper || wrapper.dataset.dtHeaderTooltipBound === 'true') {
        return;
    }

    wrapper.dataset.dtHeaderTooltipBound = 'true';
    const scrollHead = wrapper.querySelector('.dataTables_scrollHead');
    const headerScope = scrollHead || wrapper.querySelector('thead');
    if (!headerScope) {
        return;
    }

    const showTooltip = (event) => {
        const cell = event.target.closest('th');
        if (!cell || !headerScope.contains(cell)) {
            return;
        }

        if (event.target.closest('.dt-column-resizer')
            || cell.classList.contains('dt-column-resize-active')) {
            disposeCellTooltip(cell);
            return;
        }

        if (cell.querySelector('input, select, textarea, button, a, .dropdown-menu')) {
            disposeCellTooltip(cell);
            return;
        }

        const text = String(cell.dataset.dtFullTitle || '').trim();
        if (text === '') {
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

        showDataTableTooltip(cell, {
            container: 'body',
            placement: 'top',
            trigger: 'manual',
            animation: false,
        });
    };

    const hideTooltip = (event) => {
        const cell = event.target.closest('th');
        if (!cell || !headerScope.contains(cell)) {
            return;
        }

        disposeCellTooltip(cell);
    };

    scrollHead?.addEventListener?.('mouseover', showTooltip, true);
    scrollHead?.addEventListener?.('focusin', showTooltip, true);
    scrollHead?.addEventListener?.('mouseout', hideTooltip, true);
    scrollHead?.addEventListener?.('focusout', hideTooltip, true);

    if (scrollHead === null) {
        const tableNode = wrapper.querySelector('table');
        tableNode?.addEventListener?.('mouseover', showTooltip, true);
        tableNode?.addEventListener?.('focusin', showTooltip, true);
        tableNode?.addEventListener?.('mouseout', hideTooltip, true);
        tableNode?.addEventListener?.('focusout', hideTooltip, true);
    }
}

function bindTruncatedCellTooltips(table, tableSelector, columns = []) {
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

        const columnIndex = table.cell(cell).index()?.column;
        const column = Number.isInteger(columnIndex) ? (columns[columnIndex] || {}) : {};
        if (!isBodyTextCell(cell, column)) {
            disposeCellTooltip(cell);
            return;
        }

        const text = resolveBodyTooltipText(table, cell, column);
        if (text === '' || text === '-' || !isCellTextTruncated(cell)) {
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

        showDataTableTooltip(cell, {
            container: 'body',
            placement: 'top',
            trigger: 'manual',
            animation: false,
        });
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
    tbody.addEventListener('pointerdown', () => disposeTruncatedTableTooltips(wrapper), true);
    wrapper.addEventListener('scroll', () => disposeTruncatedTableTooltips(wrapper), true);
    table.on('preDraw.dt.dtTruncatedTooltip', () => disposeTruncatedTableTooltips(wrapper));
    table.on('draw.dt.dtTruncatedTooltip column-visibility.dt.dtTruncatedTooltip column-reorder.dt.dtTruncatedTooltip responsive-resize.dt.dtTruncatedTooltip', () => {
        disposeTruncatedTableTooltips(wrapper);
        syncBodyTextCellClasses(table, columns);
        applyColumnHeaderMetadata(table, columns);
    });
    syncBodyTextCellClasses(table, columns);
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

function syncDataTableApiReference(targetTable, sourceTable) {
    if (!targetTable || !sourceTable || targetTable === sourceTable) {
        return;
    }

    Reflect.ownKeys(sourceTable).forEach((key) => {
        try {
            targetTable[key] = sourceTable[key];
        } catch (_error) {
            // Ignore read-only DataTables API properties.
        }
    });

    if (sourceTable.context) {
        targetTable.context = sourceTable.context;
    }
    if (sourceTable.selector) {
        targetTable.selector = sourceTable.selector;
    }
}

async function rebuildDataTableFromSettings(table, config = {}) {
    const tableSelector = String(config?.tableSelector || '').trim();
    const tableElement = tableSelector !== '' ? document.querySelector(tableSelector) : null;
    const $ = window.jQuery;
    if (!tableSelector || !tableElement || !$?.fn?.dataTable) {
        return false;
    }

    const savedScrollTop = window.scrollY || window.pageYOffset || 0;
    const preservedTbody = tableElement.tBodies?.[0] || tableElement.querySelector('tbody');

    try {
        if ($.fn.dataTable.isDataTable(tableElement)) {
            table.destroy();
        }

        preservedTbody?.replaceChildren();
        tableElement.replaceChildren(...(preservedTbody ? [preservedTbody] : []));
        const rebuiltTable = await createDataTable({ ...config });
        syncDataTableApiReference(table, rebuiltTable);
        tableElement.__dtCurrentInstance = rebuiltTable;
        window.scrollTo(0, savedScrollTop);
        return true;
    } catch (error) {
        console.warn('[datatable] settings rebuild failed:', error);
        return false;
    }
}

export async function createDataTable(config) {
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
        cellSearchFill = false,
        searchTableId = null,
        rowReorder = false,
        initialData = null,
        density = null,
        scrollX = false,
        scrollY = '',
        paging = true,
        searching = true,
        info = true,
        showColumnVisibility = false,
        showCopyButton = true,
        selectable = true,
        showSelectionMoveButtons = true,
        selectionColumnIndex = 0,
        selectionColumn = null,
        isRowSelectable = () => true,
        rowIdField = 'id',
        deleteButton = true,
        deleteApi = null,
        bulkDelete = false,
        deletePayload = null,
        interaction = false,
        pageLoading = true,
        tableSettings = null,
        widthScopeSelector = null,
        fitColumnsToScope = true,
        fixedLayout = false,
        serverSide = false,
        orderableColumnKeys = null,
        redrawAfterInitialVisibility = true,
    } = config;
    const isClientTableDiagnostic = tableSelector === '#client-table'
        || api === '/api/settings/base-info/client/list';

    const $ = window.jQuery;
    const normalizedPageLength = PAGE_LENGTH_MENU.includes(Number(pageLength))
        ? Number(pageLength)
        : DEFAULT_PAGE_LENGTH;
    // DataTables server-side mode must retain its native global search input.
    // Domain SearchForm filters may coexist, but must not replace search[value].
    const resolvedSearching = serverSide === true ? true : searching !== false;
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
    const preparedTableSettings = await prepareDataTableSettingsColumns(sourceColumnsWithSelection, resolvedTableSettings);
    const savedPageLength = Number(preparedTableSettings.context?.viewState?.pageLength);
    const savedCurrentPage = Number(preparedTableSettings.context?.viewState?.currentPage);
    const resolvedColumns = preparedTableSettings.columns || sourceColumnsWithSelection;
    const runtimeColumns = resolvedColumns.filter((column) => !(
        column?.__dtSystemCapability === false
        && column?.visible === false
    ));
    const resolvedAutoWidth = hasExplicitAutoWidth
        ? autoWidth
        : (resolvedTableSettings?.enabled ? false : autoWidth);
    const enforcedOrderableKeys = Array.isArray(orderableColumnKeys)
        ? new Set(orderableColumnKeys.map((key) => String(key || '').trim()).filter(Boolean))
        : null;
    const tableColumns = normalizeUtilityColumns(normalizeActorColumns(runtimeColumns))
        .map((column) => enforcedOrderableKeys === null
            ? column
            : {
                ...column,
                orderable: enforcedOrderableKeys.has(resolveSettingsColumnKey(column)),
            });
    const tableColumnDefs = tableColumns.map((column, index) => ({
        targets: index,
        orderable: column?.orderable !== false,
        searchable: column?.searchable !== false,
    }));
    if (preparedTableSettings.context) {
        preparedTableSettings.context.tableColumns = tableColumns.map((column) => ({ ...column }));
    }
    const savedSortSettings = Array.isArray(preparedTableSettings.context?.viewState?.sortSettings)
        ? preparedTableSettings.context.viewState.sortSettings
        : [];
    const resolvedInitialOrder = buildOrderFromSortSettings(savedSortSettings, tableColumns);
    const hasPersistedSortSettings = savedSortSettings.length > 0;
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
    const refreshTableSettingsLayout = ({ draw = false } = {}) => refreshDataTableLayout(table, {
        draw,
        delays: [0],
    });
    const dataTableConfig = {
        ...(api ? {
            ajax: {
            url: api,
            type: serverSide === true ? 'POST' : 'GET',
            cache: false,
            data: function (request) {
                if (typeof ajaxData === 'function') {
                    const result = ajaxData(request);

                    if (result && typeof result === 'object') {
                        // Keep the complete DataTables request contract (draw,
                        // paging, columns, order and native search) while adding
                        // domain-specific request values.
                        return {
                            ...request,
                            ...result,
                            search: result.search && typeof result.search === 'object'
                                ? { ...(request.search || {}), ...result.search }
                                : request.search,
                        };
                    }
                }

                return request;
            },
            dataSrc: function (json) {
                if (typeof dataSrc === 'function') {
                    const rows = dataSrc(json);
                    return Array.isArray(rows) ? rows : [];
                }

                const rows = json.data ?? [];
                return rows;
            }
            },
        } : {
            data: Array.isArray(initialData) ? initialData : [],
        }),

        columns: tableColumns,
        // TableSettings can hide or move leading virtual columns. Apply the final
        // policy by the DataTables runtime index so a hidden utility column cannot
        // shift its non-orderable policy onto the following business column.
        columnDefs: tableColumnDefs,
        order: resolvedInitialOrder.length > 0
            ? resolvedInitialOrder
            : (
                hasPersistedSortSettings
                    ? []
                    : buildOrderFromDefaultOrder(
                        defaultOrder,
                        tableColumns,
                        sourceColumnsWithSelection,
                        shouldAddSelectionColumn,
                        selectionInsertIndex
                    )
            ),
        pageLength: resolvedInitialPageLength,
        displayStart: Number.isFinite(savedCurrentPage) && savedCurrentPage > 0
            ? savedCurrentPage * resolvedInitialPageLength
            : 0,
        lengthMenu: PAGE_LENGTH_MENU,

        rowReorder: rowReorder ? {
            selector: 'td.reorder-handle',
            dataSrc: 'sort_no'
        } : false,

        scrollX,
        ...(scrollY ? { scrollY } : {}),
        scrollCollapse: true,

        responsive,
        autoWidth: resolvedAutoWidth,
        deferRender: true,
        paging,
        searching: resolvedSearching,
        info,
        processing: true,
        serverSide: serverSide === true,

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
            ...(selectable === false || showSelectionMoveButtons === false ? [] : [{
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
                        deletePayload,
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
            ...buttons,
            ...(resolvedTableSettings?.enabled ? [{
                text: '<i class="bi bi-gear me-1"></i>설정',
                className: 'dt-table-settings-trigger',
                titleAttr: '\uD14C\uC774\uBE14 \uC124\uC815',
                action: function () {
                    table?.__dtTableSettings?.open?.();
                },
            }] : []),
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
                wrapper.dataset.dtFitColumnsToScope = fitColumnsToScope === false ? 'false' : 'true';
                wrapper.classList.toggle('dt-fixed-layout', fixedLayout === true);
            }

            applyColumnHeaderClasses(api, tableColumns);
            applyColumnHeaderMetadata(api, tableColumns);
            updateStickyHeaderOffset(api);
            api.columns.adjust();
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
        wrapper.dataset.dtFitColumnsToScope = fitColumnsToScope === false ? 'false' : 'true';
        wrapper.classList.toggle('dt-fixed-layout', fixedLayout === true);
    }
    __dtInstances.add(table);
    table.on('destroy.dt', () => {
        __dtInstances.delete(table);
        __dtContainerResizeState.get(wrapper)?.destroy?.();
        __dtContainerResizeState.delete(wrapper);
    });
    $(tableSelector).one('error.dt', () => {
        dataTableModuleLoadingHandle.release();
        releasePageLoading();
    });
    bindStickyHeaderOffsetUpdates(table);
    bindDataTableContainerResize(table);
    updateStickyHeaderOffset(table);
    renderColumnResizeHandles(table, tableColumns);
    bindColumnResize(table, tableColumns, preparedTableSettings.context);
    scheduleApplyConfiguredColumnWidths(table, tableColumns, preparedTableSettings.context, 0);

    tableColumns.forEach((column, index) => {
        if (column?.visible === false && isValidDataTableColumnIndex(table, index)) {
            table.column(index).visible(false, false);
        }
    });
    if (tableColumns.some((column) => column?.visible === false)) {
        scheduleAdjust(table, {
            draw: redrawAfterInitialVisibility !== false,
            tableColumns,
            settingsContext: preparedTableSettings.context,
        });
    }

    if (density) {
        table.table().container()?.classList.add(`dt-density-${density}`);
    }

    table.on('xhr.dt draw.dt', function () {
        updateStickyHeaderOffset(table);
        renderColumnResizeHandles(table, tableColumns);
        reapplyResponsiveColumnWidths(table, tableColumns, preparedTableSettings.context);
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

        const normalizedLength = normalizeDataTablePageLength(nextLength);
        if (!normalizedLength) {
            return;
        }

        updateDataTableViewState(preparedTableSettings.context, {
            pageLength: normalizedLength,
            currentPage: 0,
        });
    });

    table.on('page.dt', function () {
        if (!preparedTableSettings.context) {
            return;
        }

        const pageInfo = table.page.info?.();
        const currentPage = Number(pageInfo?.page);
        updateDataTableViewState(preparedTableSettings.context, {
            currentPage: Number.isFinite(currentPage) && currentPage >= 0 ? currentPage : 0,
        });
    });

    table.on('order.dt', function () {
        if (!preparedTableSettings.context) {
            return;
        }

        updateDataTableViewState(preparedTableSettings.context, {
            sortSettings: extractSortSettingsFromTable(table, tableColumns),
        });
    });

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
        table.__dtTableSettings.getCurrentViewState = () => ({
            columnWidths: tableColumns.reduce((widths, column, index) => {
                const key = resolveDataTableColumnWidthKey(column);
                const width = measureAppliedColumnWidth(table, index);
                if (key !== '' && Number.isFinite(width) && width > 0) widths[key] = width;
                return widths;
            }, { ...(preparedTableSettings.context.viewState?.columnWidths || {}) }),
            sortSettings: extractSortSettingsFromTable(table, tableColumns),
            pageLength: Number(table.page.len()),
            searchFormExpanded: table.__dtTableSettings.searchFormCapability?.available
                ? Boolean(table.__dtTableSettings.searchFormCapability.getExpanded?.())
                : preparedTableSettings.context.viewState?.searchFormExpanded,
        });
        table.__dtTableSettings.applyViewState = ({ previousState = {}, nextState = {} } = {}) => {
            const previousPageLength = Number(previousState?.pageLength);
            const nextPageLength = normalizeDataTablePageLength(nextState?.pageLength, previousPageLength);
            const previousSort = normalizeSortSettingsInput(previousState?.sortSettings);
            const nextSort = normalizeSortSettingsInput(nextState?.sortSettings);
            const pageLengthChanged = Number.isFinite(nextPageLength) && nextPageLength !== previousPageLength;
            const sortChanged = JSON.stringify(previousSort) !== JSON.stringify(nextSort);

            clearAppliedColumnWidths(table, tableColumns);
            applyConfiguredColumnWidths(table, tableColumns, preparedTableSettings.context);

            if (pageLengthChanged) {
                table.page.len(nextPageLength);
                table.page(0);
            }
            if (sortChanged) {
                table.order(buildOrderFromSortSettings(nextSort, tableColumns));
            }
            if (pageLengthChanged || sortChanged) {
                table.draw(false);
            } else {
                scheduleAdjust(table, {
                    draw: false,
                    tableColumns,
                    settingsContext: preparedTableSettings.context,
                });
            }

            if (typeof nextState?.searchFormExpanded === 'boolean') {
                table.__dtTableSettings.searchFormCapability?.setExpanded?.(
                    nextState.searchFormExpanded,
                    { persist: false }
                );
            }
            return true;
        };
        table.__dtTableSettings.applyState = ({ previousState, nextState } = {}) => {
            const currentOrder = Array.isArray(previousState?.columnOrder)
                ? previousState.columnOrder
                : [];
            const targetOrder = Array.isArray(nextState?.columnOrder)
                ? nextState.columnOrder
                : [];
            const currentVisible = Array.isArray(previousState?.visibleColumns)
                ? previousState.visibleColumns
                : [];
            const targetVisible = Array.isArray(nextState?.visibleColumns)
                ? nextState.visibleColumns
                : [];
            const currentDisplayName = previousState?.columnDisplayName && typeof previousState.columnDisplayName === 'object'
                ? previousState.columnDisplayName
                : {};
            const targetDisplayName = nextState?.columnDisplayName && typeof nextState.columnDisplayName === 'object'
                ? nextState.columnDisplayName
                : {};
            const visibilityChanged = currentVisible.length !== targetVisible.length
                || currentVisible.some((key, index) => key !== targetVisible[index]);
            const displayNameChanged = (() => {
                const currentKeys = Object.keys(currentDisplayName);
                const targetKeys = Object.keys(targetDisplayName);
                if (currentKeys.length !== targetKeys.length) {
                    return true;
                }

                const keys = new Set([...currentKeys, ...targetKeys]);
                for (const key of keys) {
                    if (String(currentDisplayName[key] ?? '') !== String(targetDisplayName[key] ?? '')) {
                        return true;
                    }
                }

                return false;
            })();

            const orderUnchanged = currentOrder.length === targetOrder.length
                && currentOrder.every((key, index) => key === targetOrder[index]);
            const runtimeColumnKeys = new Set(
                (preparedTableSettings.context?.tableColumns || [])
                    .map((column) => resolveSettingsColumnKey(column))
                    .filter(Boolean)
            );
            const requiresMissingRuntimeColumn = targetVisible.some((key) => !runtimeColumnKeys.has(String(key || '').trim()));
            const unsupportedSystemKeys = new Set(
                (preparedTableSettings.context?.originalColumns || [])
                    .filter((column) => column?.__dtSystemCapability === false)
                    .map((column) => resolveSettingsColumnKey(column))
                    .filter(Boolean)
            );
            const currentVisibleSet = new Set(currentVisible);
            const targetVisibleSet = new Set(targetVisible);
            const requiresSystemCapabilityRebuild = Array.from(unsupportedSystemKeys)
                .some((key) => currentVisibleSet.has(key) !== targetVisibleSet.has(key));

            if (orderUnchanged
                && !displayNameChanged
                && visibilityChanged
                && !requiresMissingRuntimeColumn
                && !requiresSystemCapabilityRebuild) {
                applyVisibilityToTable(table, preparedTableSettings.context);
                refreshTableSettingsLayout({ draw: true });
                return true;
            }

            if (orderUnchanged
                && !visibilityChanged
                && !displayNameChanged) {
                refreshTableSettingsLayout({ draw: true });
                return true;
            }

            if (resolvedTableSettings?.deferSchemaRebuild === true) {
                const settingsApi = table.__dtTableSettings;
                if (settingsApi?.pendingSchemaRebuildTimer) {
                    window.clearTimeout(settingsApi.pendingSchemaRebuildTimer);
                }
                settingsApi.pendingSchemaRebuildTimer = window.setTimeout(async () => {
                    settingsApi.pendingSchemaRebuildTimer = null;
                    await rebuildDataTableFromSettings(table, config);
                }, 0);
                return true;
            }

            return rebuildDataTableFromSettings(table, config);
        };
    }
    positionTableSettingsTrigger(table);
    bindTableSettingsTooltip(table);
    applyColumnHeaderMetadata(table, tableColumns);
    bindTruncatedCellTooltips(table, tableSelector, tableColumns);
    bindTruncatedHeaderTooltips(table);

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
