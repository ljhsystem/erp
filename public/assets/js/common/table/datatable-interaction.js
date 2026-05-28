import {
    createInteractionEventPayload,
    dispatchInteractionEvent,
    TABLE_INTERACTION_EVENTS,
} from './interaction-events.js';
import { createInteractionState } from './interaction-state.js';
import { createAdapterFor } from './adapter-factory.js';

function normalizeBoolean(value, fallback) {
    return typeof value === 'boolean' ? value : fallback;
}

function isInteractionDebugEnabled(optionDebug) {
    if (optionDebug === true) {
        return true;
    }

    if (optionDebug === false) {
        return false;
    }

    if (typeof window === 'undefined' || !window) {
        return false;
    }

    if (window.__dtInteractionDebug === true) {
        return true;
    }

    try {
        const params = new URLSearchParams(window.location.search || '');
        return params.get('dtInteractionDebug') === '1' || params.get('interactionDebug') === '1';
    } catch {
        return false;
    }
}

function toStringIds(values = []) {
    return Array.from(new Set((Array.isArray(values) ? values : [])
        .map((value) => String(value ?? '').trim())
        .filter(Boolean)));
}

function toRowId(adapter, row) {
    return String(adapter.getRowId?.(row) || '').trim();
}

export function createTableInteraction(options = {}) {
    const {
        table,
        adapter,
        enabled = false,
        rowIdField = 'id',
        onRequestEdit = () => {},
        onActiveRowChange = () => {},
        onSelectionChange = () => {},
        onRedraw = () => {},
        onDestroy = () => {},
        activeRowClass = 'is-active',
        rowClick = true,
        rowClickActive = true,
        selectionSync = true,
        selectionRestore = true,
        selectionBridgeMode = 'sync-only',
        debug = false,
    } = options;

    const noop = () => {};
    const isEnabled = enabled === true;

    if (!table || !isEnabled) {
        return {
            selectRow: noop,
            setActiveRow: noop,
            setSelectionIds: noop,
            clearActiveRow: noop,
            requestEditCurrent: noop,
            suspend: noop,
            resume: noop,
            redraw: noop,
            destroy: noop,
            getState: () => ({}),
            getActiveRow: () => ({ id: null, index: -1 }),
        };
    }

    const effectiveSelectionBridgeMode = selectionBridgeMode === 'full' ? 'full' : 'sync-only';
    const effectiveDebug = isInteractionDebugEnabled(debug);
    const HOVER_CLASS = 'is-hover';

    function logEvent(type, payload = {}) {
        if (!effectiveDebug) {
            return;
        }
        if (typeof console !== 'undefined' && console.debug) {
            console.debug(`[table-interaction] ${type}`, payload);
        }
    }

    const state = createInteractionState({
        rowIdField,
        activeRowClass,
        selectedRowIds: new Set(),
        selectionBridgeMode: effectiveSelectionBridgeMode,
    });

    const interactionAdapter = createAdapterFor(table, {
        adapter,
        rowIdField,
        activeRowClass,
    });

    const host = interactionAdapter?.getHostElement?.() || null;
    const unlisteners = [];
    let restoreSeq = 0;
    let isSelectionSyncing = false;
    let redrawSeq = 0;
    let redrawFrame = 0;
    let lastActiveRowSignature = '';
    let lastSelectionSignature = '';
    let isRestoring = false;

    function createStateSnapshot() {
        return {
            activeRowId: state.activeRowId,
            activeRowIndex: state.activeRowIndex,
            selectedRowIds: Array.from(state.selectedRowIds),
            hoverRowId: state.hoverRowId,
            hoverRowIndex: state.hoverRowIndex,
            redrawSeq,
            suspended: state.suspended,
        };
    }

    function getVisibleRows() {
        return interactionAdapter?.getVisibleRows?.() || [];
    }

    function getVisibleRowNodes() {
        if (typeof interactionAdapter?.getVisibleRowNodes === 'function') {
            return interactionAdapter.getVisibleRowNodes();
        }

        if (!host) {
            return [];
        }

        return Array.from(host.querySelectorAll('tbody tr'));
    }

    function getRowIndexById(rows, rowId) {
        const safeRowId = String(rowId ?? '').trim();
        if (!safeRowId) return -1;

        for (let index = 0; index < rows.length; index += 1) {
            if (toRowId(interactionAdapter, rows[index]) === safeRowId) {
                return index;
            }
        }

        return -1;
    }

    function clearActiveVisual() {
        if (!host) {
            return;
        }

        host.querySelectorAll(`.${activeRowClass}`).forEach((node) => {
            node.classList.remove(activeRowClass);
            if (node instanceof HTMLElement) {
                node.setAttribute('aria-selected', 'false');
            }
        });
    }

    function clearHoverVisualRow() {
        if (!host) {
            return;
        }

        host.querySelectorAll(`.${HOVER_CLASS}`).forEach((node) => {
            node.classList.remove(HOVER_CLASS);
        });
    }

    function clearActiveRow() {
        clearActiveVisual();
        clearHoverVisualRow();

        state.activeRowId = null;
        state.activeRowIndex = -1;
        state.hoverRowId = null;
        state.hoverRowIndex = -1;
        state.focusedCell = null;
        lastActiveRowSignature = '';

        logEvent('clear-active-row', {
            rowId: state.activeRowId,
            rowIndex: state.activeRowIndex,
        });

        const payload = createInteractionEventPayload({
            rowId: null,
            rowIndex: -1,
            reason: 'clear',
            source: 'interaction',
            event: null,
        });
        onActiveRowChange(payload);
        dispatchInteractionEvent(host, TABLE_INTERACTION_EVENTS.activeRowChange, payload);
    }

    function syncSelectionState(ids = []) {
        const nextIds = toStringIds(ids);
        const changed = nextIds.length !== state.selectedRowIds.size
            || nextIds.some((id) => !state.selectedRowIds.has(id));

        if (!changed) {
            return;
        }

        state.selectedRowIds = new Set(nextIds);
        const signature = nextIds.join(',');
        if (signature === lastSelectionSignature) {
            return;
        }

        lastSelectionSignature = signature;
        const payload = createInteractionEventPayload({
            rowId: state.activeRowId,
            rowIndex: state.activeRowIndex,
            reason: 'selectionSync',
            source: 'interaction',
            event: null,
        });
        payload.selectedRowIds = nextIds;

        onSelectionChange(payload, getVisibleRows());
        dispatchInteractionEvent(host, TABLE_INTERACTION_EVENTS.selectionChange, payload);
        logEvent('selection-change', payload);
    }

    function setSelectionIds(ids = []) {
        const nextIds = toStringIds(ids);
        syncSelectionState(nextIds);

        if (!selectionSync || effectiveSelectionBridgeMode !== 'full') {
            return;
        }

        isSelectionSyncing = true;
        try {
            interactionAdapter.setSelectedIds?.(nextIds);
        } finally {
            isSelectionSyncing = false;
        }
    }

    function restoreSelectionFromAdapter() {
        if (!selectionSync) {
            return;
        }

        const selectedIds = interactionAdapter.getSelectedIds?.() || [];
        syncSelectionState(selectedIds);
    }

    function cleanupStaleState() {
        const allRows = interactionAdapter.getRows?.() || [];
        const validRowIds = new Set(allRows.map((row) => toRowId(interactionAdapter, row)).filter(Boolean));

        if (state.selectedRowIds.size > 0) {
            const nextIds = Array.from(state.selectedRowIds).filter((id) => validRowIds.has(id));
            if (nextIds.length !== state.selectedRowIds.size) {
                syncSelectionState(nextIds);
            }
        }

        if (state.activeRowId && !validRowIds.has(state.activeRowId)) {
            state.activeRowId = null;
            state.activeRowIndex = -1;
        }

        if (state.activeRowId) {
            state.pendingRestore = {
                rowId: state.activeRowId,
                rowIndex: state.activeRowIndex,
            };
        }
    }

    function setActiveRow(rowId, rowIndex = null, metadata = {}) {
        const rows = getVisibleRows();
        let nextId = rowId != null ? String(rowId).trim() : null;
        let nextIndex = Number.isInteger(rowIndex) ? rowIndex : -1;
        let selectedRow = null;

        if (nextIndex < 0 && nextId) {
            nextIndex = getRowIndexById(rows, nextId);
        }

        if (nextIndex < 0 && nextId) {
            const resolved = interactionAdapter.resolveRowById?.(nextId);
            if (resolved) {
                nextId = String(resolved.rowId || '').trim();
                nextIndex = Number.isInteger(resolved.rowIndex) ? resolved.rowIndex : -1;
            }
        }

        if (nextIndex < 0) {
            nextIndex = rows.length ? 0 : -1;
        }

        if (nextIndex >= 0 && rows[nextIndex]) {
            selectedRow = rows[nextIndex];
            nextId = String(selectedRow.rowId || '').trim() || nextId;
        }

        if (!nextId || nextIndex < 0) {
            return null;
        }

        const signature = `${nextId}|${nextIndex}`;

        const target = metadata.shouldSelect !== false
            ? interactionAdapter.setActiveRow?.(nextId, nextIndex)
            : null;

        if (target && target.rowId) {
            state.activeRowId = String(target.rowId).trim();
            state.activeRowIndex = Number.isInteger(target.rowIndex) ? target.rowIndex : nextIndex;
        } else {
            state.activeRowId = nextId;
            state.activeRowIndex = nextIndex;
        }

        state.pendingRestore = null;

        if (signature === lastActiveRowSignature) {
            return {
                ...selectedRow,
                rowId: state.activeRowId,
                rowIndex: state.activeRowIndex,
            };
        }

        lastActiveRowSignature = signature;

        const payload = createInteractionEventPayload({
            rowId: state.activeRowId,
            rowIndex: state.activeRowIndex,
            reason: metadata.reason || 'setActiveRow',
            source: metadata.source || 'interaction',
            event: metadata.event || null,
        });

        onActiveRowChange(payload, rows[state.activeRowIndex]);
        dispatchInteractionEvent(host, TABLE_INTERACTION_EVENTS.activeRowChange, payload);
        logEvent('active-row-change', payload);

        return {
            ...selectedRow,
            rowId: state.activeRowId,
            rowIndex: state.activeRowIndex,
        };
    }

    function requestEditCurrent(rowId = null, event = null) {
        const target = rowId || state.activeRowId;
        if (!target) {
            return;
        }

        const rows = getVisibleRows();
        const index = getRowIndexById(rows, target);
        const rowData = index >= 0 ? rows[index] : null;

        const payload = createInteractionEventPayload({
            rowId: target,
            rowIndex: index >= 0 ? index : state.activeRowIndex,
            reason: 'requestEdit',
            source: 'interaction',
            event,
        });
        payload.rowData = rowData?.rowData || rowData?.data || null;

        onRequestEdit(payload);
        dispatchInteractionEvent(host, TABLE_INTERACTION_EVENTS.requestEdit, payload);
        logEvent('request-edit', payload);
    }

    function resolveRowFromEventTarget(target) {
        if (!target || !(target instanceof Element)) {
            return null;
        }

        const row = target.closest('tr');
        if (!row) {
            return null;
        }

        const visibleRows = getVisibleRows();
        const visibleNodes = getVisibleRowNodes();
        const rowIndex = visibleNodes.indexOf(row);
        if (rowIndex < 0 || rowIndex >= visibleRows.length) {
            return null;
        }

        const rowData = visibleRows[rowIndex];
        return {
            row,
            index: rowIndex,
            rowId: String(rowData?.rowId || row.getAttribute('data-row-id') || '').trim(),
            data: rowData?.data,
            rowData: rowData?.rowData || rowData?.data || null,
        };
    }

    function isIgnoredRowTarget(target) {
        return target.closest?.('a, button, input, select, textarea, .dropdown-menu, .dt-select-all, .dt-row-select, .select2-container, [data-action], [data-modal-trigger], .drag-handle, .reorder-handle, .dt-reorder-handle');
    }

    function onClick(event) {
        if (!normalizeBoolean(rowClick, true)) {
            return;
        }

        const target = event?.target;
        if (!target || isIgnoredRowTarget(target)) {
            return;
        }

        const row = resolveRowFromEventTarget(target);
        if (!row || !row.rowId) {
            return;
        }

        if (rowClickActive) {
            setActiveRow(row.rowId, row.index, {
                shouldSelect: true,
                source: 'click',
                reason: 'rowClick',
                event,
            });
        }
    }

    function onDblClick(event) {
        const target = event?.target;
        if (!target || isIgnoredRowTarget(target)) {
            return;
        }

        const row = resolveRowFromEventTarget(target);
        if (!row || !row.rowId) {
            return;
        }

        setActiveRow(row.rowId, row.index, {
            source: 'dblclick',
            reason: 'rowDblclick',
            shouldSelect: true,
            event,
        });
        requestEditCurrent(row.rowId, event);
    }

    function onMouseOver(event) {
        const target = event?.target;
        if (!target || isIgnoredRowTarget(target)) {
            return;
        }

        const row = resolveRowFromEventTarget(target);
        if (!row || !row.rowId) {
            return;
        }

        if (state.hoverRowId === row.rowId && state.hoverRowIndex === row.index) {
            return;
        }

        if (host) {
            clearHoverVisualRow();
            row.row.classList.add(HOVER_CLASS);
        }

        state.hoverRowId = row.rowId;
        state.hoverRowIndex = row.index;
    }

    function onMouseOut(event) {
        const target = event?.target;
        const row = resolveRowFromEventTarget(target);
        if (!row) {
            clearHoverVisualRow();
            state.hoverRowId = null;
            state.hoverRowIndex = -1;
            return;
        }

        if (host) {
            row.row.classList.remove(HOVER_CLASS);
        }

        state.hoverRowId = null;
        state.hoverRowIndex = -1;
    }

    function onSelectionChangedFromTable(event) {
        if (isSelectionSyncing || !selectionSync) {
            return;
        }

        const payloadIds = event?.detail?.ids;
        if (Array.isArray(payloadIds)) {
            syncSelectionState(payloadIds);
            return;
        }

        const adapterIds = interactionAdapter.getSelectedIds?.() || [];
        syncSelectionState(adapterIds);
    }

    function resolveRestoreIndex() {
        const visibleRows = getVisibleRows();
        if (!visibleRows.length) {
            return { index: -1 };
        }

        if (state.activeRowId) {
            const index = getRowIndexById(visibleRows, state.activeRowId);
            if (index >= 0) {
                return { index, reason: 'activeRestore' };
            }
        }

        if (state.pendingRestore && state.pendingRestore.rowIndex >= 0) {
            const fallback = Math.min(state.pendingRestore.rowIndex, visibleRows.length - 1);
            if (fallback >= 0) {
                return { index: fallback, reason: 'pendingRestore' };
            }
        }

        return { index: 0, reason: 'defaultFirst' };
    }

    function performRedrawRestore(seq, reason = 'redraw') {
        if (!state.activeRowId && state.pendingRestore == null) {
            return;
        }

        if (!selectionRestore || !host || isRestoring || selectionBridgeMode === 'full') {
            return;
        }

        const resolved = resolveRestoreIndex();
        if (resolved.index < 0) {
            return;
        }

        const visibleRows = getVisibleRows();
        const nextRow = visibleRows[resolved.index];
        if (!nextRow) {
            return;
        }

        isRestoring = true;
        setActiveRow(nextRow.rowId, nextRow.index, {
            source: reason,
            reason: resolved.reason,
            shouldSelect: false,
            event: null,
        });
        isRestoring = false;

        const payload = createInteractionEventPayload({
            rowId: state.activeRowId,
            rowIndex: state.activeRowIndex,
            reason: resolved.reason,
            source: reason,
            event: null,
        });
        onRedraw(payload, getVisibleRows());
        dispatchInteractionEvent(host, TABLE_INTERACTION_EVENTS.redraw, payload);
        logEvent('redraw-restore', {
            reason: resolved.reason,
            rowId: state.activeRowId,
            rowIndex: state.activeRowIndex,
            seq,
        });
    }

    function onRedrawRestore() {
        redrawSeq += 1;
        const seq = redrawSeq;

        cleanupStaleState();
        if (redrawFrame) {
            cancelAnimationFrame(redrawFrame);
        }

        redrawFrame = requestAnimationFrame(() => {
            redrawFrame = 0;
            if (isRestoring) {
                return;
            }

            performRedrawRestore(seq, 'redraw');
        });
    }

    function onPageSearchOrderRestore() {
        if (!selectionRestore) {
            return;
        }

        const delay = Number(window?.requestAnimationFrame ? 0 : 16);
        window.setTimeout(() => {
            onRedrawRestore();
        }, delay);
    }

    function suspend() {
        state.suspended = true;
        state.modalDepth += 1;
        logEvent('suspend', { modalDepth: state.modalDepth });
    }

    function resume() {
        if (state.modalDepth > 0) {
            state.modalDepth -= 1;
        }

        if (state.modalDepth <= 0) {
            state.suspended = false;
            onRedrawRestore();
            logEvent('resume', { modalDepth: state.modalDepth });
        }
    }

    function redraw() {
        interactionAdapter.redraw?.();
        onRedrawRestore();
    }

    function attach() {
        if (!host) {
            return;
        }

        host.addEventListener('click', onClick);
        host.addEventListener('dblclick', onDblClick);
        host.addEventListener('mouseover', onMouseOver);
        host.addEventListener('mouseout', onMouseOut);
        host.addEventListener('datatable:selection-changed', onSelectionChangedFromTable);

        unlisteners.push(() => {
            host.removeEventListener('click', onClick);
            host.removeEventListener('dblclick', onDblClick);
            host.removeEventListener('mouseover', onMouseOver);
            host.removeEventListener('mouseout', onMouseOut);
            host.removeEventListener('datatable:selection-changed', onSelectionChangedFromTable);
        });

        if (typeof interactionAdapter.on === 'function') {
            unlisteners.push(interactionAdapter.on('draw', onRedrawRestore));
            unlisteners.push(interactionAdapter.on('page', onPageSearchOrderRestore));
            unlisteners.push(interactionAdapter.on('search', onPageSearchOrderRestore));
            unlisteners.push(interactionAdapter.on('order', onPageSearchOrderRestore));
            unlisteners.push(interactionAdapter.on('xhr', onPageSearchOrderRestore));
            unlisteners.push(interactionAdapter.on('destroy', onDestroyInternal));
        }

        if (!state.activeRowId) {
            const pending = state.pendingRestore;
            if (pending?.rowId) {
                setActiveRow(pending.rowId, pending.rowIndex, {
                    shouldSelect: false,
                    reason: 'bootstrap',
                    source: 'interaction',
                    event: null,
                });
            }
        }

        restoreSelectionFromAdapter();
    }

    function onDestroyInternal() {
        const payload = createInteractionEventPayload({
            rowId: state.activeRowId,
            rowIndex: state.activeRowIndex,
            reason: 'destroy',
            source: 'interaction',
            event: null,
        });
        onDestroy(payload);
        dispatchInteractionEvent(host, TABLE_INTERACTION_EVENTS.destroy, payload);
        logEvent('destroy', payload);
    }

    function destroy() {
        detach();
    }

    function detach() {
        while (unlisteners.length > 0) {
            const fn = unlisteners.pop();
            try {
                fn();
            } catch {
                // no-op
            }
        }

        onDestroyInternal();
        state.activeRowId = null;
        state.activeRowIndex = -1;
        state.selectedRowIds = new Set();
        state.hoverRowId = null;
        state.hoverRowIndex = -1;
        state.pendingRestore = null;
        clearActiveVisual();
        clearHoverVisualRow();
    }

    attach();
    restoreSelectionFromAdapter();

    return {
        selectRow: setActiveRow,
        setActiveRow,
        setSelectionIds,
        clearActiveRow,
        requestEditCurrent,
        suspend,
        resume,
        redraw,
        destroy,
        getState: createStateSnapshot,
        getActiveRow: () => ({
            id: state.activeRowId,
            index: state.activeRowIndex,
        }),
    };
}
