function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
}

function updateCellLifecycle(state, rowId, previousCell, nextCell) {
    const rowCells = state.cells[rowId] || {};

    if (previousCell?.columnKey) {
        const previousState = rowCells[previousCell.columnKey] || { state: ['normal'], message: '' };
        rowCells[previousCell.columnKey] = {
            ...previousState,
            state: (previousState.state || []).filter((entry) => !['focused', 'selected'].includes(entry)),
        };
    }

    if (nextCell?.columnKey) {
        const nextState = rowCells[nextCell.columnKey] || { state: ['normal'], message: '' };
        const lifecycle = Array.from(new Set([...(nextState.state || ['normal']), 'focused', 'selected']));
        rowCells[nextCell.columnKey] = {
            ...nextState,
            state: lifecycle,
        };
    }

    state.cells[rowId] = rowCells;
}

export function createMoveCellCommand(payload = {}) {
    return {
        type: 'move-cell',
        payload,
        canExecute(context = {}) {
            const row = context.state?.rows?.[Number(payload.rowIndex)] || null;
            return Boolean(row);
        },
        execute(context = {}) {
            if (!this.canExecute(context)) {
                return { executed: false, reason: 'blocked' };
            }

            const state = context.state;
            const rowIndex = Number(payload.rowIndex);
            const row = state.rows[rowIndex];
            const previousCell = state.selection?.activeCell || null;
            const nextCell = {
                rowId: row.rowId,
                rowIndex,
                columnKey: String(payload.columnKey || ''),
            };

            updateCellLifecycle(state, row.rowId, previousCell, nextCell);
            state.selection = {
                ...(state.selection || {}),
                activeCell: nextCell,
                selectedRowIds: [row.rowId],
            };

            context.eventBus?.emit('active-cell:changed', { activeCell: cloneValue(nextCell) });
            context.eventBus?.emit('selection:changed', { selection: cloneValue(state.selection) });
            return { executed: true, activeCell: cloneValue(nextCell) };
        },
        undo() {
            return { executed: false };
        },
        redo() {
            return { executed: false };
        },
    };
}
