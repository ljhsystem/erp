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

export function createUpdateRowCommand(payload = {}) {
    return {
        type: 'update-row',
        payload,
        canExecute(context = {}) {
            const row = context.state?.rows?.[Number(payload.rowIndex)] || null;
            return Boolean(row) && payload.patch && typeof payload.patch === 'object' && !Array.isArray(payload.patch);
        },
        execute(context = {}) {
            if (!this.canExecute(context)) {
                return { executed: false, reason: 'blocked' };
            }

            const state = context.state;
            const rowIndex = Number(payload.rowIndex);
            const row = state.rows[rowIndex];
            const patch = payload.patch || {};
            const options = payload.options && typeof payload.options === 'object' ? payload.options : {};
            const dirtyFields = new Set(row.dirtyFields || []);

            Object.entries(patch).forEach(([field, value]) => {
                row.values = {
                    ...(row.values || {}),
                    [field]: value,
                };
                dirtyFields.add(field);
                const rowCells = state.cells[row.rowId] || {};
                const existingCellState = rowCells[field] || { state: ['normal'], message: '' };
                rowCells[field] = {
                    ...existingCellState,
                    state: Array.from(new Set([...(existingCellState.state || []), 'dirty'])).filter((entry) => entry !== 'editing'),
                };
                state.cells[row.rowId] = rowCells;
            });

            row.dirtyFields = Array.from(dirtyFields);
            row.rowState = deriveNextRowState(row.rowState);
            state.selection = {
                ...(state.selection || {}),
                activeCell: {
                    rowId: row.rowId,
                    rowIndex,
                    columnKey: Object.keys(patch)[0] || state.selection?.activeCell?.columnKey || '',
                },
                selectedRowIds: [row.rowId],
            };

            const updatePayload = {
                row: cloneValue(row),
                rowIndex,
                patch: cloneValue(patch),
                options: cloneValue(options),
                deferRowRender: options.deferRowRender === true,
            };

            requestDerivedState(context, 'update-row', { row, rowIndex, patch });
            context.eventBus?.emit('row:updated', updatePayload);
            context.eventBus?.emit('selection:changed', { selection: cloneValue(state.selection) });
            context.eventBus?.emit('validation:changed', { validation: cloneValue(state.validation) });
            context.eventBus?.emit('footer:changed', { footer: cloneValue(state.footer) });
            return { executed: true, ...updatePayload };
        },
        undo() {
            return { executed: false };
        },
        redo() {
            return { executed: false };
        },
    };
}
