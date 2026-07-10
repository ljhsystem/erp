import { createMoveCellCommand } from './commands/move-cell-command.js';

function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
}

function normalizeIndex(value, fallback = -1) {
    const parsed = Number(value);
    return Number.isInteger(parsed) ? parsed : fallback;
}

function uniqueStrings(values = []) {
    return values
        .map((value) => String(value || '').trim())
        .filter((value, index, array) => value !== '' && array.indexOf(value) === index);
}

function isSelectionEnabled(api) {
    return api.getCapabilities?.().selection !== false;
}

function executeCommand(context, command) {
    if (typeof context.executeCommand === 'function') {
        return context.executeCommand(command);
    }

    return command.execute(context.commandContext || context);
}

function buildRangePayload(rows, visibleColumns, startCell, endCell) {
    if (!startCell || !endCell) {
        return null;
    }

    const startRowIndex = Math.min(startCell.rowIndex, endCell.rowIndex);
    const endRowIndex = Math.max(startCell.rowIndex, endCell.rowIndex);
    const startColumnIndex = Math.min(
        visibleColumns.findIndex((column) => column.key === startCell.columnKey),
        visibleColumns.findIndex((column) => column.key === endCell.columnKey)
    );
    const endColumnIndex = Math.max(
        visibleColumns.findIndex((column) => column.key === startCell.columnKey),
        visibleColumns.findIndex((column) => column.key === endCell.columnKey)
    );

    if (startColumnIndex < 0 || endColumnIndex < 0) {
        return null;
    }

    return {
        start: cloneValue(startCell),
        end: cloneValue(endCell),
        rowIds: rows.slice(startRowIndex, endRowIndex + 1).map((row) => row.rowId),
        columnKeys: visibleColumns.slice(startColumnIndex, endColumnIndex + 1).map((column) => column.key),
    };
}

export function createSelectionController(config = {}) {
    const api = config.api;
    if (!api) {
        throw new Error('[html-grid] selection requires api.');
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

    function getState() {
        return api.getState?.() || {};
    }

    function setSelectionPatch(patch = {}) {
        const currentState = getState();
        return api.setState?.({
            ...currentState,
            selection: {
                ...(currentState.selection || {}),
                ...patch,
            },
        });
    }

    function focusCell(rowIndex, columnKey) {
        if (!isSelectionEnabled(api)) {
            return { executed: false, reason: 'capability-blocked' };
        }

        const rows = getRows();
        const normalizedRowIndex = normalizeIndex(rowIndex);
        const row = rows[normalizedRowIndex] || null;
        if (!row) {
            return { executed: false, reason: 'missing-row' };
        }

        const command = createMoveCellCommand({
            rowIndex: normalizedRowIndex,
            columnKey,
        });
        return executeCommand(config, command);
    }

    function selectRow(rowIndex, options = {}) {
        if (!isSelectionEnabled(api)) {
            return { executed: false, reason: 'capability-blocked' };
        }

        const rows = getRows();
        const normalizedRowIndex = normalizeIndex(rowIndex);
        const row = rows[normalizedRowIndex] || null;
        if (!row) {
            return { executed: false, reason: 'missing-row' };
        }

        const currentSelection = getState().selection || {};
        const selectedRowIds = options.append === true
            ? uniqueStrings([...(currentSelection.selectedRowIds || []), row.rowId])
            : [row.rowId];

        setSelectionPatch({
            activeCell: currentSelection.activeCell
                ? {
                    ...currentSelection.activeCell,
                    rowId: row.rowId,
                    rowIndex: normalizedRowIndex,
                }
                : null,
            selectedRowIds,
        });

        return {
            executed: true,
            rowId: row.rowId,
            rowIndex: normalizedRowIndex,
            selectedRowIds,
        };
    }

    function setRange(startCell, endCell) {
        if (!isSelectionEnabled(api)) {
            return { executed: false, reason: 'capability-blocked' };
        }

        const range = buildRangePayload(getRows(), getVisibleColumns(), startCell, endCell);
        setSelectionPatch({
            range,
            selectedRowIds: range?.rowIds || [],
        });

        return {
            executed: Boolean(range),
            range,
        };
    }

    function clearRange() {
        setSelectionPatch({
            range: null,
        });

        return { executed: true };
    }

    function clearSelection() {
        setSelectionPatch({
            activeCell: null,
            range: null,
            selectedRowIds: [],
        });

        return { executed: true };
    }

    function getActiveCell() {
        return getState().selection?.activeCell || null;
    }

    function getSelectedRows() {
        const selectedIds = new Set(getState().selection?.selectedRowIds || []);
        return getRows().filter((row) => selectedIds.has(row.rowId));
    }

    function getRange() {
        return getState().selection?.range || null;
    }

    return {
        focusCell,
        selectRow,
        setRange,
        clearRange,
        clearSelection,
        getActiveCell,
        getSelectedRows,
        getRange,
    };
}
