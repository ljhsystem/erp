function editable(colDef, params) {
    return typeof colDef?.editable === 'function' ? colDef.editable(params) === true : colDef?.editable === true;
}

function parseClipboard(text) {
    return String(text || '').replace(/\r/g, '').split('\n')
        .filter((row, index, rows) => row !== '' || index < rows.length - 1)
        .map(row => row.split('\t'));
}

function displayedColumns(api) {
    return (api?.getAllDisplayedColumns?.() || []).filter(column => {
        const definition = column.getColDef?.() || {};
        return Boolean(definition.field);
    });
}

export function attachAgGridCommunityClipboard(host, adapter, options = {}) {
    if (!host || !adapter?.api) return { destroy() {} };
    let anchor = null;
    let focus = null;

    const clearSelection = () => {
        host.querySelectorAll('.erp-ag-grid-range-selected').forEach(cell => cell.classList.remove('erp-ag-grid-range-selected'));
    };
    const paintSelection = () => {
        clearSelection();
        if (!anchor || !focus) return;
        const rowStart = Math.min(anchor.rowIndex, focus.rowIndex);
        const rowEnd = Math.max(anchor.rowIndex, focus.rowIndex);
        const columns = displayedColumns(adapter.api);
        const left = columns.findIndex(column => column.getColId() === anchor.colId);
        const right = columns.findIndex(column => column.getColId() === focus.colId);
        if (left < 0 || right < 0) return;
        const colStart = Math.min(left, right);
        const colEnd = Math.max(left, right);
        for (let rowIndex = rowStart; rowIndex <= rowEnd; rowIndex += 1) {
            for (let colIndex = colStart; colIndex <= colEnd; colIndex += 1) {
                host.querySelector(`.ag-row[row-index="${rowIndex}"] .ag-cell[col-id="${columns[colIndex].getColId()}"]`)
                    ?.classList.add('erp-ag-grid-range-selected');
            }
        }
    };
    const focused = () => adapter.api.getFocusedCell?.() || focus || anchor;
    const copy = async () => {
        if (!anchor || !focus) return;
        const columns = displayedColumns(adapter.api);
        const rowStart = Math.min(anchor.rowIndex, focus.rowIndex);
        const rowEnd = Math.max(anchor.rowIndex, focus.rowIndex);
        const left = columns.findIndex(column => column.getColId() === anchor.colId);
        const right = columns.findIndex(column => column.getColId() === focus.colId);
        const rows = [];
        for (let rowIndex = rowStart; rowIndex <= rowEnd; rowIndex += 1) {
            const node = adapter.api.getDisplayedRowAtIndex?.(rowIndex);
            rows.push(columns.slice(Math.min(left, right), Math.max(left, right) + 1)
                .map(column => node?.data?.[column.getColDef().field] ?? '').join('\t'));
        }
        await navigator.clipboard?.writeText?.(rows.join('\n'));
    };
    const paste = (text) => {
        const start = focused();
        if (!start) return;
        const matrix = parseClipboard(text);
        const columns = displayedColumns(adapter.api);
        const startColumn = columns.findIndex(column => column.getColId() === start.column?.getColId?.() || column.getColId() === start.colId);
        if (startColumn < 0) return;
        matrix.forEach((values, rowOffset) => {
            const node = adapter.api.getDisplayedRowAtIndex?.(Number(start.rowIndex) + rowOffset);
            if (!node?.data) return;
            values.forEach((rawValue, columnOffset) => {
                const column = columns[startColumn + columnOffset];
                const colDef = column?.getColDef?.() || {};
                if (!column || !editable(colDef, { data: node.data, node, column, colDef, api: adapter.api })) return;
                const value = typeof colDef.valueParser === 'function'
                    ? colDef.valueParser({ oldValue: node.data[colDef.field], newValue: rawValue, data: node.data, node, column, colDef, api: adapter.api })
                    : rawValue;
                node.setDataValue?.(colDef.field, value);
            });
        });
        adapter.api.refreshCells?.({ force: true });
        options.onChanged?.(adapter.getData(), adapter);
    };
    const onPointerDown = event => {
        const cell = event.target instanceof Element ? event.target.closest('.ag-cell') : null;
        const row = cell?.closest('.ag-row');
        if (!cell || !row) return;
        const position = { rowIndex: Number(row.getAttribute('row-index')), colId: String(cell.getAttribute('col-id') || '') };
        if (!event.shiftKey || !anchor) anchor = position;
        focus = position;
        paintSelection();
    };
    const onKeyDown = event => {
        if (!(event.ctrlKey || event.metaKey)) return;
        if (String(event.key).toLowerCase() === 'c') {
            event.preventDefault(); void copy();
        }
    };
    const onPaste = event => {
        if (options.readOnly === true) return;
        const text = event.clipboardData?.getData('text/plain');
        if (text === undefined) return;
        event.preventDefault(); paste(text);
    };
    host.addEventListener('pointerdown', onPointerDown);
    host.addEventListener('keydown', onKeyDown);
    host.addEventListener('paste', onPaste);
    return {
        destroy() {
            host.removeEventListener('pointerdown', onPointerDown);
            host.removeEventListener('keydown', onKeyDown);
            host.removeEventListener('paste', onPaste);
            clearSelection();
        },
    };
}
