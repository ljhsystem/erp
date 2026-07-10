import { cloneGridState, replaceGridState } from './state.js';

function normalizeRowIndex(rowIndex) {
    const parsed = Number(rowIndex);
    return Number.isInteger(parsed) ? parsed : -1;
}

function createNotImplementedResult(methodName) {
    throw new Error(`[html-grid] ${methodName} is not implemented yet.`);
}

export function createGridApi(core = {}) {
    const {
        state,
        eventBus,
        handlers = {},
    } = core;

    if (!state || !eventBus) {
        throw new Error('[html-grid] createGridApi requires state and eventBus.');
    }

    function callHandler(handlerName, fallback) {
        const handler = handlers[handlerName];
        if (typeof handler === 'function') {
            return handler;
        }
        if (typeof fallback === 'function') {
            return fallback;
        }
        return () => createNotImplementedResult(handlerName);
    }

    function addRow(row = null) {
        return callHandler('addRow')({ row, api });
    }

    function insertRow(rowIndex, row = null) {
        return callHandler('insertRow')({ rowIndex: normalizeRowIndex(rowIndex), row, api });
    }

    function deleteRow(rowIndex) {
        return callHandler('deleteRow')({ rowIndex: normalizeRowIndex(rowIndex), api });
    }

    function updateRow(rowIndex, patch = {}, options = {}) {
        return callHandler('updateRow')({ rowIndex: normalizeRowIndex(rowIndex), patch, options, api });
    }

    function updateCell(rowIndex, columnKey, value) {
        return callHandler('updateCell')({
            rowIndex: normalizeRowIndex(rowIndex),
            columnKey: String(columnKey || ''),
            value,
            api,
        });
    }

    function getRow(rowIndex) {
        const normalizedRowIndex = normalizeRowIndex(rowIndex);
        return state.rows[normalizedRowIndex] ?? null;
    }

    function getRows() {
        return state.rows;
    }

    function getState() {
        return cloneGridState(state);
    }

    function setState(nextState) {
        replaceGridState(state, nextState);
        eventBus.emit('state:changed', { state: getState(), api });
        return getState();
    }

    function getCapabilities() {
        return { ...(state.capabilities || {}) };
    }

    function setCapabilities(nextCapabilities = {}) {
        state.capabilities = {
            ...(state.capabilities || {}),
            ...(nextCapabilities && typeof nextCapabilities === 'object' ? nextCapabilities : {}),
        };
        eventBus.emit('capabilities:changed', { capabilities: getCapabilities(), api });
        return getCapabilities();
    }

    function getRowState(rowIndex) {
        return getRow(rowIndex)?.rowState || null;
    }

    function setRowState(rowIndex, rowState) {
        return callHandler('setRowState')({
            rowIndex: normalizeRowIndex(rowIndex),
            rowState,
            api,
        });
    }

    function getCellState(rowIndex, columnKey) {
        return callHandler('getCellState', ({ rowIndex: nextRowIndex, columnKey: nextColumnKey }) => {
            const row = getRow(nextRowIndex);
            if (!row?.rowId) {
                return null;
            }
            return state.cells?.[row.rowId]?.[nextColumnKey] ?? null;
        })({
            rowIndex: normalizeRowIndex(rowIndex),
            columnKey: String(columnKey || ''),
            api,
        });
    }

    function setCellState(rowIndex, columnKey, cellState) {
        return callHandler('setCellState')({
            rowIndex: normalizeRowIndex(rowIndex),
            columnKey: String(columnKey || ''),
            cellState,
            api,
        });
    }

    function validate() {
        return callHandler('validate', () => ({
            valid: true,
            rowErrors: {},
            cellErrors: {},
        }))({ api });
    }

    function render(options = {}) {
        return callHandler('render')({ options, api });
    }

    function refresh(options = {}) {
        return callHandler('refresh')({ options, api });
    }

    function focusCell(rowIndex, columnKey) {
        return callHandler('focusCell')({
            rowIndex: normalizeRowIndex(rowIndex),
            columnKey: String(columnKey || ''),
            api,
        });
    }

    function selectRow(rowIndex) {
        return callHandler('selectRow')({
            rowIndex: normalizeRowIndex(rowIndex),
            api,
        });
    }

    function clearSelection() {
        return callHandler('clearSelection')({ api });
    }

    function getActiveCell() {
        return state.selection?.activeCell ?? null;
    }

    function beginEdit(rowIndex, columnKey) {
        return callHandler('beginEdit')({
            rowIndex: normalizeRowIndex(rowIndex),
            columnKey: String(columnKey || ''),
            api,
        });
    }

    function endEdit(options = {}) {
        return callHandler('endEdit')({ options, api });
    }

    function execute(command) {
        return callHandler('execute')({ command, api });
    }

    function on(eventName, handler) {
        return eventBus.on(eventName, handler);
    }

    function off(eventName, handler) {
        return eventBus.off(eventName, handler);
    }

    function emit(eventName, payload = {}) {
        return eventBus.emit(eventName, { ...payload, api });
    }

    function destroy() {
        return callHandler('destroy')({ api });
    }

    const api = {
        addRow,
        insertRow,
        deleteRow,
        updateRow,
        updateCell,
        getRow,
        getRows,
        getState,
        setState,
        getCapabilities,
        setCapabilities,
        getRowState,
        setRowState,
        getCellState,
        setCellState,
        validate,
        render,
        refresh,
        focusCell,
        selectRow,
        clearSelection,
        getActiveCell,
        beginEdit,
        endEdit,
        execute,
        on,
        off,
        emit,
        destroy,
    };

    return api;
}
