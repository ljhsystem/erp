function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
}

function deriveNextRowState(currentState) {
    const normalized = String(currentState || '').trim();
    if (normalized === 'created') {
        return 'created';
    }
    if (['readonly', 'disabled', 'locked', 'saving', 'deleted'].includes(normalized)) {
        return normalized;
    }
    return 'updated';
}

function mergeCellState(existingState = [], lifecycle = []) {
    const nextState = Array.from(new Set([...(existingState || []), ...lifecycle]));
    return nextState.filter((entry) => entry !== 'editing');
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

export function createUpdateCellCommand(payload = {}) {
    return {
        type: 'update-cell',
        payload,
        canExecute(context = {}) {
            const row = context.state?.rows?.[Number(payload.rowIndex)] || null;
            const columnKey = String(payload.columnKey || '').trim();
            if (!row || columnKey === '') {
                return false;
            }
            return !['readonly', 'disabled', 'locked', 'saving', 'deleted'].includes(String(row.rowState || '').trim());
        },
        execute(context = {}) {
            if (!this.canExecute(context)) {
                return { executed: false, reason: 'blocked' };
            }

            const state = context.state;
            const rowIndex = Number(payload.rowIndex);
            const columnKey = String(payload.columnKey || '').trim();
            const row = state.rows[rowIndex];
            const previousValue = row.values?.[columnKey] ?? '';
            const nextValue = payload.value;
            const isChanged = String(previousValue ?? '') !== String(nextValue ?? '');

            row.values = {
                ...(row.values || {}),
                [columnKey]: nextValue,
            };

            row.dirtyFields = isChanged
                ? Array.from(new Set([...(row.dirtyFields || []), columnKey]))
                : (row.dirtyFields || []).filter((field) => field !== columnKey);
            row.rowState = isChanged ? deriveNextRowState(row.rowState) : row.rowState;

            const rowCells = state.cells[row.rowId] || {};
            const existingCellState = rowCells[columnKey] || { state: ['normal'], message: '' };
            rowCells[columnKey] = {
                ...existingCellState,
                state: mergeCellState(existingCellState.state, isChanged ? ['dirty'] : ['normal']),
            };
            state.cells[row.rowId] = rowCells;
            state.selection = {
                ...(state.selection || {}),
                activeCell: { rowId: row.rowId, rowIndex, columnKey },
                selectedRowIds: [row.rowId],
            };

            requestDerivedState(context, 'update-cell', { row, rowIndex, columnKey, previousValue, nextValue });
            context.eventBus?.emit('cell:changed', {
                row: cloneValue(row),
                rowIndex,
                columnKey,
                previousValue,
                nextValue,
            });
            context.eventBus?.emit('selection:changed', { selection: cloneValue(state.selection) });
            context.eventBus?.emit('validation:changed', { validation: cloneValue(state.validation) });
            context.eventBus?.emit('footer:changed', { footer: cloneValue(state.footer) });
            return {
                executed: true,
                row: cloneValue(row),
                rowIndex,
                columnKey,
                previousValue,
                nextValue,
            };
        },
        undo() {
            return { executed: false };
        },
        redo() {
            return { executed: false };
        },
    };
}
