function clampWidth(width, minWidth = 0, maxWidth = Number.POSITIVE_INFINITY) {
    return Math.max(minWidth, Math.min(maxWidth, width));
}

export function createResizeController(config = {}) {
    const columnManager = config.columnManager;
    const eventBus = config.eventBus;
    if (!columnManager || !eventBus) {
        throw new Error('[html-grid] resize requires columnManager and eventBus.');
    }

    const sessionState = {
        columnKey: '',
        startWidth: 0,
    };

    function resolveColumn(columnKey) {
        return columnManager.getState().order
            .map((key) => columnManager.getOrderedColumns().find((column) => column.key === key))
            .find((column) => column?.key === String(columnKey || '').trim()) || null;
    }

    function beginResize(columnKey) {
        const column = resolveColumn(columnKey);
        if (!column) {
            return { executed: false, reason: 'missing-column' };
        }

        sessionState.columnKey = column.key;
        sessionState.startWidth = Number(columnManager.getState().widths[column.key] || column.width || 0);
        return { executed: true, columnKey: column.key, startWidth: sessionState.startWidth };
    }

    function updateWidth(columnKey, requestedWidth) {
        const column = resolveColumn(columnKey);
        if (!column) {
            return { executed: false, reason: 'missing-column' };
        }

        const nextWidth = clampWidth(
            Math.max(0, Number(requestedWidth) || 0),
            Number(column.minWidth || 0),
            Number(column.maxWidth || Number.POSITIVE_INFINITY)
        );

        const state = columnManager.setWidth(column.key, nextWidth);
        return { executed: true, columnKey: column.key, width: nextWidth, state };
    }

    function endResize(columnKey, requestedWidth) {
        const result = updateWidth(columnKey, requestedWidth);
        if (!result.executed) {
            return result;
        }

        eventBus.emit('column:resized', {
            columnKey: result.columnKey,
            width: result.width,
        });

        sessionState.columnKey = '';
        sessionState.startWidth = 0;
        return result;
    }

    return {
        beginResize,
        updateWidth,
        endResize,
    };
}
