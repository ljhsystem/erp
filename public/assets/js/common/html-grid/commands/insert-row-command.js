import { createGridRow } from '../state.js';

function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
}

function isCapabilityEnabled(state, capabilityName) {
    return state?.capabilities?.[capabilityName] !== false;
}

function normalizeRowForInsert(context, row = {}, rowIndex = 0) {
    const createRow = typeof context.createRow === 'function'
        ? context.createRow
        : (typeof context.hooks?.createRow === 'function' ? context.hooks.createRow : null);
    const normalizeRow = typeof context.normalizeRow === 'function'
        ? context.normalizeRow
        : (typeof context.hooks?.normalizeRow === 'function' ? context.hooks.normalizeRow : null);

    const nextRow = row && typeof row === 'object' ? cloneValue(row) : {};
    const createdRow = createRow ? createRow({ row: nextRow, rowIndex, state: context.state }) : nextRow;
    const normalizedRow = normalizeRow ? normalizeRow(createdRow, { rowIndex, state: context.state }) : createdRow;

    return createGridRow(normalizedRow, {
        rowId: normalizedRow?.rowId || `row-${rowIndex + 1}`,
        rowState: normalizedRow?.rowState || 'created',
    });
}

function updateSelectionAfterInsert(context, row, rowIndex) {
    const state = context.state;
    const defaultColumnKey = typeof context.getDefaultColumnKey === 'function'
        ? context.getDefaultColumnKey(row, rowIndex, state)
        : String(context.defaultColumnKey || '').trim();

    state.selection = {
        ...(state.selection || {}),
        activeCell: defaultColumnKey
            ? { rowId: row.rowId, rowIndex, columnKey: defaultColumnKey }
            : { rowId: row.rowId, rowIndex, columnKey: '' },
        selectedRowIds: [row.rowId],
    };
}

function requestDerivedState(context, reason, payload = {}) {
    if (typeof context.runValidation === 'function') {
        const nextValidation = context.runValidation({ state: context.state, reason, ...payload });
        if (nextValidation && typeof nextValidation === 'object') {
            context.state.validation = nextValidation;
        }
    }

    if (typeof context.runFooter === 'function') {
        const nextFooter = context.runFooter({ state: context.state, reason, ...payload });
        if (nextFooter && typeof nextFooter === 'object') {
            context.state.footer = nextFooter;
        }
    }
}

export function createInsertRowCommand(payload = {}) {
    return {
        type: 'insert-row',
        payload,
        canExecute(context = {}) {
            return Boolean(context.state) && isCapabilityEnabled(context.state, 'insertRow');
        },
        execute(context = {}) {
            if (!this.canExecute(context)) {
                return { executed: false, reason: 'capability-blocked' };
            }

            const state = context.state;
            const requestedIndex = Number(payload.rowIndex);
            const rowIndex = Number.isInteger(requestedIndex)
                ? Math.max(0, Math.min(requestedIndex, state.rows.length))
                : state.rows.length;
            const row = normalizeRowForInsert(context, payload.row || {}, rowIndex);
            state.rows.splice(rowIndex, 0, row);
            state.cells[row.rowId] = state.cells[row.rowId] || {};
            updateSelectionAfterInsert(context, row, rowIndex);
            requestDerivedState(context, 'insert-row', { row, rowIndex });
            context.eventBus?.emit('row:inserted', { row, rowIndex });
            context.eventBus?.emit('selection:changed', { selection: cloneValue(state.selection) });
            return { executed: true, row, rowIndex };
        },
        undo() {
            return { executed: false };
        },
        redo() {
            return { executed: false };
        },
    };
}
