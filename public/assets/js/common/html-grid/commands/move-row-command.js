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

function resequenceRows(state, context = {}) {
    const sequenceField = String(context.rowNumberField || '').trim();
    state.rows.forEach((row, index) => {
        if (sequenceField) {
            row.values = {
                ...(row.values || {}),
                [sequenceField]: index + 1,
            };
            row.dirtyFields = Array.from(new Set([...(row.dirtyFields || []), sequenceField]));
        }
    });
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

export function createMoveRowCommand(payload = {}) {
    return {
        type: 'move-row',
        payload,
        canExecute(context = {}) {
            const state = context.state;
            const fromIndex = Number(payload.fromIndex);
            const toIndex = Number(payload.toIndex);
            return Boolean(state)
                && isCapabilityEnabled(state, 'reorder')
                && Number.isInteger(fromIndex)
                && Number.isInteger(toIndex)
                && fromIndex >= 0
                && toIndex >= 0
                && fromIndex < state.rows.length
                && toIndex < state.rows.length
                && fromIndex !== toIndex;
        },
        execute(context = {}) {
            if (!this.canExecute(context)) {
                return { executed: false, reason: 'blocked' };
            }

            const state = context.state;
            const fromIndex = Number(payload.fromIndex);
            const toIndex = Number(payload.toIndex);
            const [row] = state.rows.splice(fromIndex, 1);
            state.rows.splice(toIndex, 0, row);
            resequenceRows(state, context);
            state.selection = {
                ...(state.selection || {}),
                activeCell: row
                    ? {
                        rowId: row.rowId,
                        rowIndex: toIndex,
                        columnKey: state.selection?.activeCell?.columnKey || '',
                    }
                    : null,
                selectedRowIds: row ? [row.rowId] : [],
            };
            requestDerivedState(context, 'move-row', { row, fromIndex, toIndex });
            context.eventBus?.emit('row:moved', { row: cloneValue(row), fromIndex, toIndex });
            context.eventBus?.emit('selection:changed', { selection: cloneValue(state.selection) });
            return { executed: true, row: cloneValue(row), fromIndex, toIndex };
        },
        undo() {
            return { executed: false };
        },
        redo() {
            return { executed: false };
        },
    };
}
