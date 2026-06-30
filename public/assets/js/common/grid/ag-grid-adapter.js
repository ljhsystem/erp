import { handleAgGridKeyboard } from './ag-grid-keyboard.js';
import { fieldFromColumnRef } from './ag-grid-navigation.js';
import { defaultRowDragOptions } from './ag-grid-defaults.js';
import { selectedRowIndexes } from './ag-grid-selection.js';

function normalizeRows(rows = []) {
    return Array.isArray(rows) ? rows.map((row) => ({ ...(row || {}) })) : [];
}

function normalizeColumnDefs(columns = []) {
    if (!Array.isArray(columns)) return [];
    return columns.map((column) => {
        if (!column || typeof column !== 'object') return column;
        if (column.field) return { ...column };

        const hasData = Object.prototype.hasOwnProperty.call(column, 'data');
        if (!hasData) return { ...column };

        const { data, title, ...rest } = column;
        return {
            ...rest,
            field: String(data),
            headerName: title ?? String(data),
        };
    });
}

function resolveColumnIndex(columnDefs = [], field = null) {
    if (Number.isInteger(field) && field >= 0) return field;

    const target = String(field ?? '');
    const index = columnDefs.findIndex((column) => (
        String(column?.field ?? '') === target
        || String(column?.colId ?? '') === target
    ));
    return index >= 0 ? index : 0;
}

function setGridOption(api, key, value) {
    if (!api) return;
    if (typeof api.setGridOption === 'function') {
        api.setGridOption(key, value);
        return;
    }
    if (key === 'rowData' && typeof api.setRowData === 'function') {
        api.setRowData(value);
        return;
    }
    if (key === 'columnDefs' && typeof api.setColumnDefs === 'function') {
        api.setColumnDefs(value);
    }
}

function normalizeRowSelection(rowSelection) {
    const defaults = {
        checkboxes: false,
        headerCheckbox: false,
        enableClickSelection: true,
    };
    if (rowSelection && typeof rowSelection === 'object') return { ...defaults, ...rowSelection };
    if (rowSelection === 'multiple') return { ...defaults, mode: 'multiRow' };
    return { ...defaults, mode: 'singleRow' };
}

function measureAdapterDebugElement(element) {
    if (!element) return null;
    const rect = element.getBoundingClientRect();
    return {
        selector: element.id ? `#${element.id}` : (element.className || element.tagName || ''),
        offsetWidth: Number(element.offsetWidth || 0),
        clientWidth: Number(element.clientWidth || 0),
        scrollWidth: Number(element.scrollWidth || 0),
        rectWidth: Number(rect.width || 0),
        styleWidth: element.style?.width || '',
        computedWidth: window.getComputedStyle ? window.getComputedStyle(element).width : '',
    };
}

function collectAdapterDebugColumns(api) {
    if (!api) return [];
    const displayedColumns = api.getAllDisplayedColumns?.()
        || api.getColumnApi?.()?.getAllDisplayedColumns?.()
        || [];
    return displayedColumns.map((column) => ({
        field: String(column?.getColId?.() || column?.colId || ''),
        headerName: String(column?.getColDef?.()?.headerName || ''),
        actualWidth: Number(column?.getActualWidth?.() || 0),
        minWidth: Number(column?.getMinWidth?.() || 0),
        maxWidth: Number(column?.getMaxWidth?.() || 0),
        left: Number(column?.getLeft?.() || 0),
        visible: typeof column?.isVisible === 'function' ? column.isVisible() : true,
    }));
}

function logAdapterDebug(label, adapter, extra = {}) {
    console.group(`[AgGridAdapterDebug] ${label}`);
    console.log('host', measureAdapterDebugElement(adapter?.rootElement || null));
    console.log('columns', collectAdapterDebugColumns(adapter?.api || null));
    if (Object.keys(extra).length > 0) {
        console.log('extra', extra);
    }
    console.groupEnd();
}

export function createAgGridAdapter(host, config = {}) {
    if (!host) throw new Error('AG Grid host element is required.');
    if (!window.agGrid?.createGrid) throw new Error('AG Grid library is not loaded.');
    host.classList.remove('is-grid-ready');

    const adapter = {
        rootElement: host,
        api: null,
        resizeObserver: null,
        resizeFrame: 0,
        readOnly: Boolean(config.readOnly),
        columnDefs: normalizeColumnDefs(config.columnDefs),
        rowData: normalizeRows(config.rowData),
        focusedRowIndex: 0,
        focusedColumnId: '',
        editing: false,
        activeEditor: null,

        destroy() {
            if (this.resizeFrame) window.cancelAnimationFrame(this.resizeFrame);
            this.resizeObserver?.disconnect?.();
            this.resizeFrame = 0;
            this.resizeObserver = null;
            this.api?.destroy?.();
            this.api = null;
            this.rootElement.innerHTML = '';
        },

        loadData(rows = []) {
            this.rowData = normalizeRows(rows);
            setGridOption(this.api, 'rowData', this.rowData);
            this.refresh();
            config.onDataChanged?.(this.getData(), null, this);
        },

        getData() {
            const rows = [];
            this.api?.forEachNodeAfterFilterAndSort?.((node) => {
                if (node?.data) rows.push({ ...node.data });
            });
            if (rows.length > 0 || this.rowData.length === 0) return rows;
            return normalizeRows(this.rowData);
        },

        getSourceData() {
            return this.getData();
        },

        getSourceDataAtRow(rowIndex) {
            return this.getData()[rowIndex] || null;
        },

        countRows() {
            return this.getData().length;
        },

        addRow(row = {}, atIndex = null) {
            const rows = this.getData();
            const index = Number.isInteger(atIndex)
                ? Math.max(0, Math.min(atIndex, rows.length))
                : rows.length;
            rows.splice(index, 0, { ...row });
            this.loadData(rows);
            this.focusCell(index, 0);
        },

        removeRows(indexes = []) {
            const removeSet = new Set(
                indexes
                    .map((index) => Number(index))
                    .filter((index) => Number.isInteger(index) && index >= 0),
            );
            if (removeSet.size === 0) return;
            this.loadData(this.getData().filter((_, index) => !removeSet.has(index)));
        },

        removeSelectedRows() {
            this.removeRows(selectedRowIndexes(this.api, this.focusedRowIndex));
        },

        updateCell(rowIndex, field, value) {
            const rows = this.getData();
            if (!rows[rowIndex] || !field) return;
            rows[rowIndex][field] = value;
            this.loadData(rows);
        },

        setSourceDataAtCell(rowIndex, field, value) {
            this.updateCell(rowIndex, field, value);
        },

        setDataAtCell(rowIndex, field, value) {
            this.updateCell(rowIndex, field, value);
        },

        setDataAtRowProp(rowIndex, field, value) {
            this.updateCell(rowIndex, field, value);
        },

        getDataAtRowProp(rowIndex, field) {
            return this.getData()[rowIndex]?.[field];
        },

        refresh() {
            this.api?.refreshCells?.({ force: true });
            this.api?.redrawRows?.();
        },

        fitColumns() {
            console.group('[AgGridAdapterDebug] fitColumns');
            try {
                logAdapterDebug('fitColumns:before', this, {
                    autoFitColumns: config.autoFitColumns !== false,
                    hiddenByOffsetParent: this.rootElement.offsetParent === null,
                });
                if (!this.api || this.rootElement.offsetParent === null) return;
                if (config.autoFitColumns === false) return;
                this.api.sizeColumnsToFit?.();
                logAdapterDebug('fitColumns:after', this, {
                    autoFitColumns: config.autoFitColumns !== false,
                });
            } finally {
                console.groupEnd();
            }
        },

        scheduleFitColumns() {
            console.group('[AgGridAdapterDebug] scheduleFitColumns');
            logAdapterDebug('scheduleFitColumns:before', this, {
                resizeFrame: this.resizeFrame,
            });
            if (this.resizeFrame) window.cancelAnimationFrame(this.resizeFrame);
            this.resizeFrame = window.requestAnimationFrame(() => {
                this.resizeFrame = 0;
                logAdapterDebug('scheduleFitColumns:raf', this);
                this.fitColumns();
            });
            console.groupEnd();
        },

        render() {
            this.refreshDimensions();
        },

        refreshDimensions() {
            console.group('[AgGridAdapterDebug] refreshDimensions');
            logAdapterDebug('refreshDimensions:before', this);
            this.refresh();
            this.api?.doLayout?.();
            logAdapterDebug('refreshDimensions:after', this);
            console.groupEnd();
        },

        updateSettings(settings = {}) {
            if (Array.isArray(settings.columns)) {
                this.columnDefs = normalizeColumnDefs(settings.columns);
                setGridOption(this.api, 'columnDefs', this.columnDefs);
            }
            if (Array.isArray(settings.rowData)) {
                this.loadData(settings.rowData);
                return;
            }

            if (Object.prototype.hasOwnProperty.call(settings, 'colHeaders')) {
                this.columnDefs = this.columnDefs.map((column, index) => {
                    const header = settings.colHeaders?.[index];
                    if (!header) return column;
                    return { ...column, headerName: header };
                });
                setGridOption(this.api, 'columnDefs', this.columnDefs);
            }

            if (Object.prototype.hasOwnProperty.call(settings, 'readOnly')) {
                this.readOnly = Boolean(settings.readOnly);
            }
            this.refresh();
        },

        updateColumnDefs(columnDefs = []) {
            this.updateSettings({ columns: columnDefs });
        },

        getSettings() {
            return { readOnly: Boolean(this.readOnly) };
        },

        focusCell(rowIndex = 0, columnRef = 0) {
            const field = fieldFromColumnRef(this.columnDefs, columnRef);
            if (!field) return;
            this.focusedRowIndex = Math.max(0, Number(rowIndex) || 0);
            this.focusedColumnId = field;
            this.api?.ensureIndexVisible?.(this.focusedRowIndex);
            this.api?.ensureColumnVisible?.(field);
            this.api?.setFocusedCell?.(this.focusedRowIndex, field);
        },

        selectCell(rowIndex = 0, columnRef = 0) {
            this.focusCell(rowIndex, columnRef);
        },

        scrollViewportTo(rowIndex = 0, columnRef = 0) {
            const field = fieldFromColumnRef(this.columnDefs, columnRef);
            this.api?.ensureIndexVisible?.(Math.max(0, Number(rowIndex) || 0));
            if (field) this.api?.ensureColumnVisible?.(field);
        },

        startEditing(rowIndex = this.focusedRowIndex, columnRef = this.focusedColumnId || 0) {
            const field = fieldFromColumnRef(this.columnDefs, columnRef);
            if (!field) return;
            this.focusCell(rowIndex, field);
            this.editing = true;
            this.activeEditor = {
                TEXTAREA: null,
                finishEditing: (cancel = false) => this.stopEditing(cancel),
            };
            this.api?.startEditingCell?.({ rowIndex: Math.max(0, Number(rowIndex) || 0), colKey: field });
        },

        stopEditing(cancel = false) {
            this.api?.stopEditing?.(cancel);
            this.editing = false;
            this.activeEditor = null;
        },

        isEditing() {
            const editingCells = this.api?.getEditingCells?.() || [];
            return this.editing || editingCells.length > 0;
        },

        getActiveEditor() {
            return this.activeEditor || null;
        },

        getSelectedLast() {
            const colIndex = resolveColumnIndex(this.columnDefs, this.focusedColumnId);
            return [Number(this.focusedRowIndex) || 0, colIndex];
        },

        getCoords(cell) {
            const target = cell?.closest?.('.ag-cell') || cell;
            if (!target) return null;
            const rowEl = target.closest?.('.ag-row') || target.closest?.('[role="row"]');
            const rowIndex = Number(rowEl?.getAttribute?.('row-index')) || Number(rowEl?.getAttribute?.('data-row')) || -1;
            const colId = target.getAttribute?.('col-id');
            const col = rowEl ? Array.from(rowEl.querySelectorAll('.ag-cell')).indexOf(target) : -1;
            if (colId !== null && colId !== undefined && colId !== '') {
                const lookup = resolveColumnIndex(this.columnDefs, colId);
                return { row: rowIndex, col: lookup };
            }
            return { row: rowIndex, col };
        },

        getCell(rowIndex, col) {
            const rowEl = this.rootElement.querySelector(`.ag-row[row-index="${rowIndex}"]`);
            if (!rowEl) return null;
            const cells = rowEl.querySelectorAll('.ag-cell');
            const colIndex = resolveColumnIndex(this.columnDefs, col);
            return cells[colIndex] || null;
        },

        alter(action, rowIndex, amount = 1) {
            if (!Number.isInteger(rowIndex)) return;
            const rows = this.getData();
            const command = String(action || '').toLowerCase();

            if (command.includes('remove')) {
                this.removeRows([rowIndex]);
                return;
            }

            if (command.includes('insert')) {
                const targetIndex = Math.max(0, Math.min(rowIndex + 1, rows.length));
                for (let i = 0; i < amount; i += 1) {
                    rows.splice(targetIndex + i, 0, {});
                }
                this.loadData(rows);
            }
        },
    };

    const gridOptions = {
        rowData: adapter.rowData,
        columnDefs: adapter.columnDefs,
        defaultColDef: {
            editable: true,
            resizable: true,
            sortable: false,
            suppressMovable: true,
            ...(config.defaultColDef || {}),
        },
        rowSelection: normalizeRowSelection(config.rowSelection),
        ...defaultRowDragOptions(config),
        undoRedoCellEditing: config.undoRedoCellEditing !== false,
        suppressClipboardPaste: config.suppressClipboardPaste === true,
        enableCellTextSelection: config.enableCellTextSelection !== false,
        ensureDomOrder: true,
        stopEditingWhenCellsLoseFocus: true,
        domLayout: config.domLayout || 'autoHeight',
        ...(config.gridOptions || {}),
        onGridReady(event) {
            config.gridOptions?.onGridReady?.(event);
            window.requestAnimationFrame(() => {
                adapter.scheduleFitColumns();
                host.classList.add('is-grid-ready');
            });
        },
        onCellFocused(event) {
            adapter.focusedRowIndex = Number(event.rowIndex ?? adapter.focusedRowIndex) || 0;
            adapter.focusedColumnId = event.column?.getColId?.() || adapter.focusedColumnId;
            config.gridOptions?.onCellFocused?.(event);
            config.onCellFocused?.(event, adapter);
        },
        onCellEditingStarted(event) {
            adapter.editing = true;
            adapter.activeEditor = {
                TEXTAREA: event?.api?.getEditingCells?.()[0]?.editor?.eGui?.querySelector?.('textarea, input, [contenteditable="true"]') || null,
                finishEditing: (cancel = false) => adapter.stopEditing(cancel),
            };
            config.gridOptions?.onCellEditingStarted?.(event);
        },
        onCellEditingStopped(event) {
            adapter.editing = false;
            adapter.activeEditor = null;
            config.gridOptions?.onCellEditingStopped?.(event);
        },
        onCellValueChanged(event) {
            adapter.rowData = adapter.getData();
            config.gridOptions?.onCellValueChanged?.(event);
            config.onCellValueChanged?.(event, adapter);
            config.onDataChanged?.(adapter.getData(), event, adapter);
        },
        onRowDragEnd(event) {
            adapter.rowData = adapter.getData();
            config.gridOptions?.onRowDragEnd?.(event);
            config.onRowDragEnd?.(event, adapter);
            config.onDataChanged?.(adapter.getData(), event, adapter);
        },
        onCellClicked(event) {
            config.gridOptions?.onCellClicked?.(event);
            config.onCellClicked?.(event, adapter);
        },
        onColumnHeaderClicked(event) {
            config.gridOptions?.onColumnHeaderClicked?.(event);
            config.onColumnHeaderClicked?.(event, adapter);
        },
        onCellKeyDown(event) {
            handleAgGridKeyboard(event, adapter);
            config.gridOptions?.onCellKeyDown?.(event);
            config.onCellKeyDown?.(event, adapter);
        },
    };

    host.classList.add(
        'erp-ag-grid',
        ...String(config.className || 'erp-ag-grid-input').split(/\s+/).filter(Boolean)
    );
    console.group('[AgGridAdapterDebug] createGrid');
    console.log('host', measureAdapterDebugElement(host));
    console.log('gridOptions', {
        domLayout: gridOptions.domLayout,
        defaultColDef: gridOptions.defaultColDef,
        columnDefs: Array.isArray(gridOptions.columnDefs)
            ? gridOptions.columnDefs.map((column) => ({
                field: String(column?.field || ''),
                headerName: String(column?.headerName || ''),
                width: Number(column?.width || 0) || null,
                minWidth: Number(column?.minWidth || 0) || null,
                maxWidth: Number(column?.maxWidth || 0) || null,
                hide: Boolean(column?.hide),
            }))
            : [],
    });
    adapter.api = window.agGrid.createGrid(host, gridOptions);
    logAdapterDebug('createGrid:after', adapter);
    console.groupEnd();
    if (typeof ResizeObserver !== 'undefined') {
        adapter.resizeObserver = new ResizeObserver(() => {
            adapter.api?.doLayout?.();
        });
        adapter.resizeObserver.observe(host);
        const parent = host.parentElement;
        if (parent) adapter.resizeObserver.observe(parent);
    }

    return adapter;
}
