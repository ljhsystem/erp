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

function canDeleteRowLifecycle(rowState) {
    return !['deleted', 'readonly', 'disabled', 'locked', 'saving'].includes(String(rowState || '').trim());
}

function updateSelectionAfterDelete(state, nextIndex = -1) {
    const nextRow = nextIndex >= 0 ? state.rows[nextIndex] || null : null;
    state.selection = {
        ...(state.selection || {}),
        activeCell: nextRow
            ? {
                rowId: nextRow.rowId,
                rowIndex: nextIndex,
                columnKey: state.selection?.activeCell?.columnKey || '',
            }
            : null,
        selectedRowIds: nextRow ? [nextRow.rowId] : [],
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

export function createDeleteRowCommand(payload = {}) {
    return {
        type: 'delete-row',
        payload,
        canExecute(context = {}) {
            const row = context.state?.rows?.[Number(payload.rowIndex)] || null;
            return Boolean(row) && isCapabilityEnabled(context.state, 'deleteRow') && canDeleteRowLifecycle(row.rowState);
        },
        execute(context = {}) {
            const state = context.state;
            const rowIndex = Number(payload.rowIndex);
            const row = state?.rows?.[rowIndex] || null;

            if (!this.canExecute(context) || !row) {
                return { executed: false, reason: 'blocked' };
            }

            if (row.rowState === 'created') {
                state.rows.splice(rowIndex, 1);
                delete state.cells[row.rowId];
                updateSelectionAfterDelete(state, state.rows.length > 0 ? Math.min(rowIndex, state.rows.length - 1) : -1);
                requestDerivedState(context, 'delete-created-row', { row, rowIndex });
                context.eventBus?.emit('row:deleted', { row: cloneValue(row), rowIndex, mode: 'removed' });
                context.eventBus?.emit('selection:changed', { selection: cloneValue(state.selection) });
                return { executed: true, row: cloneValue(row), rowIndex, mode: 'removed' };
            }

            row.rowState = 'deleted';
            row.dirtyFields = Array.from(new Set([...(row.dirtyFields || []), 'rowState']));
            const rowCells = state.cells[row.rowId] || {};
            Object.keys(rowCells).forEach((columnKey) => {
                rowCells[columnKey] = {
                    ...(rowCells[columnKey] || {}),
                    state: ['readonly', 'disabled'],
                    message: rowCells[columnKey]?.message || '',
                };
            });
            state.cells[row.rowId] = rowCells;
            updateSelectionAfterDelete(state, rowIndex);
            requestDerivedState(context, 'delete-row', { row, rowIndex });
            context.eventBus?.emit('row:deleted', { row: cloneValue(row), rowIndex, mode: 'lifecycle' });
            context.eventBus?.emit('selection:changed', { selection: cloneValue(state.selection) });
            return { executed: true, row: cloneValue(row), rowIndex, mode: 'lifecycle' };
        },
        undo() {
            return { executed: false };
        },
        redo() {
            return { executed: false };
        },
    };
}
