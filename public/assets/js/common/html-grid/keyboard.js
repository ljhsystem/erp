function isRowBlocked(row = {}) {
    return ['readonly', 'disabled', 'locked', 'saving', 'deleted'].includes(String(row.rowState || '').trim());
}

function normalizeKey(event = {}) {
    return String(event.key || '').trim();
}

function buildCellPosition(row, rowIndex, columnKey) {
    return {
        rowId: row?.rowId || '',
        rowIndex,
        columnKey: String(columnKey || ''),
    };
}

export function createKeyboardController(config = {}) {
    const api = config.api;
    const selection = config.selection;
    if (!api || !selection) {
        throw new Error('[html-grid] keyboard requires api and selection.');
    }

    function getRows() {
        return Array.isArray(api.getRows?.()) ? api.getRows() : [];
    }

    function getVisibleColumns() {
        if (typeof config.getVisibleColumns === 'function') {
            return config.getVisibleColumns();
        }
        return [];
    }

    function getActiveCellOrFallback() {
        const activeCell = selection.getActiveCell?.() || null;
        if (activeCell) {
            return activeCell;
        }

        const rows = getRows();
        const columns = getVisibleColumns();
        if (rows.length === 0 || columns.length === 0) {
            return null;
        }

        return buildCellPosition(rows[0], 0, columns[0].key);
    }

    function moveByOffset(rowOffset, columnOffset) {
        const activeCell = getActiveCellOrFallback();
        if (!activeCell) {
            return { executed: false, reason: 'no-active-cell' };
        }

        const rows = getRows();
        const columns = getVisibleColumns();
        const currentColumnIndex = columns.findIndex((column) => column.key === activeCell.columnKey);
        if (currentColumnIndex < 0) {
            return { executed: false, reason: 'missing-column' };
        }

        const nextRowIndex = Math.max(0, Math.min(activeCell.rowIndex + rowOffset, rows.length - 1));
        const nextColumnIndex = Math.max(0, Math.min(currentColumnIndex + columnOffset, columns.length - 1));
        return selection.focusCell(nextRowIndex, columns[nextColumnIndex].key);
    }

    function duplicateCurrentRow() {
        const activeCell = getActiveCellOrFallback();
        if (!activeCell) {
            return { executed: false, reason: 'no-active-cell' };
        }

        const rows = getRows();
        const row = rows[activeCell.rowIndex] || null;
        if (!row || isRowBlocked(row)) {
            return { executed: false, reason: 'blocked-row' };
        }

        const cloneValues = JSON.parse(JSON.stringify(row.values || {}));
        return api.insertRow?.(activeCell.rowIndex + 1, {
            values: cloneValues,
            rowState: 'created',
        });
    }

    function handleKeyDown(event = {}) {
        const key = normalizeKey(event);
        const capabilities = api.getCapabilities?.() || {};

        if (key === 'Tab') {
            event.preventDefault?.();
            return moveByOffset(0, event.shiftKey ? -1 : 1);
        }

        if (key === 'Enter') {
            event.preventDefault?.();
            return moveByOffset(event.shiftKey ? -1 : 1, 0);
        }

        if (key === 'ArrowUp') {
            event.preventDefault?.();
            return moveByOffset(-1, 0);
        }

        if (key === 'ArrowDown') {
            event.preventDefault?.();
            return moveByOffset(1, 0);
        }

        if (key === 'ArrowLeft') {
            event.preventDefault?.();
            return moveByOffset(0, -1);
        }

        if (key === 'ArrowRight') {
            event.preventDefault?.();
            return moveByOffset(0, 1);
        }

        if (key === 'Delete') {
            event.preventDefault?.();
            const activeCell = getActiveCellOrFallback();
            if (!activeCell) {
                return { executed: false, reason: 'no-active-cell' };
            }
            return api.deleteRow?.(activeCell.rowIndex);
        }

        if (key === 'Insert') {
            event.preventDefault?.();
            const activeCell = getActiveCellOrFallback();
            if (!activeCell) {
                return { executed: false, reason: 'no-active-cell' };
            }
            return api.insertRow?.(activeCell.rowIndex + 1);
        }

        if ((event.ctrlKey || event.metaKey) && key.toUpperCase() === 'D') {
            event.preventDefault?.();
            return duplicateCurrentRow();
        }

        if ((event.ctrlKey || event.metaKey) && key.toUpperCase() === 'C') {
            event.preventDefault?.();
            return typeof config.onCopy === 'function' ? config.onCopy({ event, api, selection }) : { executed: false, reason: 'copy-hook-missing' };
        }

        if ((event.ctrlKey || event.metaKey) && key.toUpperCase() === 'V') {
            event.preventDefault?.();
            return typeof config.onPaste === 'function' ? config.onPaste({ event, api, selection }) : { executed: false, reason: 'paste-hook-missing' };
        }

        if ((event.ctrlKey || event.metaKey) && key.toUpperCase() === 'A') {
            event.preventDefault?.();
            if (capabilities.multiSelection === false) {
                return selection.selectRow(0);
            }
            return typeof config.onSelectAll === 'function' ? config.onSelectAll({ event, api, selection }) : { executed: false, reason: 'select-all-hook-missing' };
        }

        if (key === 'Escape') {
            event.preventDefault?.();
            return api.endEdit?.({ cancel: true });
        }

        if (key === 'F2') {
            event.preventDefault?.();
            const activeCell = getActiveCellOrFallback();
            if (!activeCell) {
                return { executed: false, reason: 'no-active-cell' };
            }
            return api.beginEdit?.(activeCell.rowIndex, activeCell.columnKey);
        }

        return { executed: false, reason: 'unhandled-key' };
    }

    return {
        handleKeyDown,
    };
}
