export function registerKeyboard(ctx) {
    const gridCellStates = ctx.gridCellStates;
    const PRINTABLE_CONTROL_KEYS = new Set([
        'Enter',
        'Tab',
        'Escape',
        'Backspace',
        'Delete',
        'Insert',
        'Home',
        'End',
        'PageUp',
        'PageDown',
        'ArrowUp',
        'ArrowDown',
        'ArrowLeft',
        'ArrowRight',
        'F2',
    ]);

    function gridState(kind) {
        return gridCellStates[kind] || null;
    }

    function gridAdapter(kind) {
        return kind === ctx.GRID_KIND_SETTLEMENT ? ctx.settlementGrid : ctx.lineGrid;
    }

    function gridRowFactory(kind) {
        return kind === ctx.GRID_KIND_SETTLEMENT ? ctx.blankSettlement : ctx.blankLine;
    }

    function deletedRowsStore(kind) {
        return kind === ctx.GRID_KIND_SETTLEMENT ? ctx.settlementDeletedRows : ctx.lineDeletedRows;
    }

    function setClipboardSelection(kind, selection = {}) {
        const state = gridState(kind);
        if (!state) {
            return;
        }

        state.clipboardSelection = {
            mode: 'cell',
            ...selection,
        };
    }

    function updateGridActiveCell(kind, rowIndex, colId) {
        const state = gridState(kind);
        if (!state) {
            return;
        }

        const nextRowIndex = Number.isInteger(rowIndex) ? rowIndex : -1;
        const nextColId = String(colId || '').trim();
        state.activeCell = nextRowIndex >= 0 && nextColId !== ''
            ? { rowIndex: nextRowIndex, colId: nextColId }
            : null;
        state.selectedRow = nextRowIndex;
        state.clipboardSelection = nextRowIndex >= 0 && nextColId !== ''
            ? { mode: 'cell', rowIndex: nextRowIndex, colId: nextColId }
            : { mode: 'cell' };
    }

    function updateGridEditingCell(kind, rowIndex, colId) {
        const state = gridState(kind);
        if (!state) {
            return;
        }

        const nextRowIndex = Number.isInteger(rowIndex) ? rowIndex : -1;
        const nextColId = String(colId || '').trim();
        state.editingCell = nextRowIndex >= 0 && nextColId !== ''
            ? { rowIndex: nextRowIndex, colId: nextColId }
            : null;
        updateGridActiveCell(kind, rowIndex, colId);
    }

    function clearGridEditingCell(kind) {
        const state = gridState(kind);
        if (!state) {
            return;
        }

        state.editingCell = null;
    }

    function gridFocusField(kind) {
        return kind === ctx.GRID_KIND_SETTLEMENT ? 'settlement_type' : 'item_date';
    }

    function gridDefaultEndField(kind) {
        return kind === ctx.GRID_KIND_SETTLEMENT ? 'description' : 'description';
    }

    function gridDataRows(kind) {
        if (kind === ctx.GRID_KIND_SETTLEMENT) {
            return (ctx.settlementGrid?.getSourceData() || []).map((row) => ctx.normalizeSettlement(row));
        }

        return ctx.getLineRows();
    }

    function gridColumnId(column) {
        const colDef = column?.getColDef?.() || {};
        return String(colDef.field || colDef.colId || column?.getColId?.() || '').trim();
    }

    function getDisplayedInputColumns(kind) {
        const adapter = gridAdapter(kind);
        const api = adapter?.api;
        const displayedColumns = api?.getAllDisplayedColumns?.()
            || api?.getColumnApi?.()?.getAllDisplayedColumns?.()
            || [];

        return displayedColumns.filter((column) => {
            const colDef = column?.getColDef?.() || {};
            const colId = gridColumnId(column);
            if (!colId || colId.startsWith('__')) {
                return false;
            }

            if (colDef.hide || colDef.suppressNavigable === true) {
                return false;
            }

            if (typeof colDef.editable === 'function') {
                try {
                    if (colDef.editable({ column, colDef }) === false) {
                        return false;
                    }
                } catch (_error) {
                    return false;
                }
            } else if (colDef.editable === false) {
                return false;
            }

            return true;
        });
    }

    function getDisplayedRowColumns(kind) {
        const adapter = gridAdapter(kind);
        const api = adapter?.api;
        const displayedColumns = api?.getAllDisplayedColumns?.()
            || api?.getColumnApi?.()?.getAllDisplayedColumns?.()
            || [];

        return displayedColumns.filter((column) => {
            const colDef = column?.getColDef?.() || {};
            const colId = gridColumnId(column);
            if (!colId) {
                return false;
            }

            if (colDef.hide || colDef.suppressNavigable === true) {
                return false;
            }

            return true;
        });
    }

    function getDisplayedInputColumnIds(kind) {
        return getDisplayedInputColumns(kind).map((column) => gridColumnId(column));
    }

    function getDisplayedRowColumnIds(kind) {
        return getDisplayedRowColumns(kind).map((column) => gridColumnId(column));
    }

    function normalizeFocusedCell(kind, rowIndex = null, colId = '') {
        const state = gridState(kind);
        const fallback = state?.activeCell || null;
        const nextRowIndex = Number.isInteger(rowIndex) ? rowIndex : Number(fallback?.rowIndex ?? -1);
        const nextColId = String(colId || fallback?.colId || '').trim();

        return {
            rowIndex: nextRowIndex >= 0 ? nextRowIndex : 0,
            colId: nextColId || gridFocusField(kind),
        };
    }

    function focusGridCell(kind, rowIndex, colId, options = {}) {
        const adapter = gridAdapter(kind);
        const api = adapter?.api;
        const targetRowIndex = Math.max(0, Number(rowIndex) || 0);
        const targetColId = String(colId || '').trim();

        if (!api || targetColId === '') {
            return;
        }

        updateGridActiveCell(kind, targetRowIndex, targetColId);
        api.ensureIndexVisible?.(targetRowIndex);
        api.ensureColumnVisible?.(targetColId);
        api.setFocusedCell?.(targetRowIndex, targetColId);

        if (options.startEditing === true) {
            const startEditing = () => {
                api.startEditingCell?.({
                    rowIndex: targetRowIndex,
                    colKey: targetColId,
                    keyPress: options.initialKey || undefined,
                    charPress: options.initialKey || undefined,
                });
            };

            if (options.defer === false) {
                startEditing();
            } else {
                window.requestAnimationFrame(startEditing);
            }
        }
    }

    function scheduleGridEditing(kind, rowIndex, colId, initialKey = '') {
        window.requestAnimationFrame(() => {
            focusGridCell(kind, rowIndex, colId, {
                startEditing: true,
                initialKey,
            });
        });
    }

    function startGridEditingImmediately(kind, rowIndex, colId, initialKey = '') {
        focusGridCell(kind, rowIndex, colId, {
            startEditing: true,
            initialKey,
            defer: false,
        });
    }

    function isImeCompositionTrigger(event, key = '') {
        return Boolean(
            event?.isComposing
            || key === 'Process'
            || Number(event?.keyCode) === 229
            || Number(event?.which) === 229
        );
    }

    function createKeyboardRow(kind) {
        return kind === ctx.GRID_KIND_SETTLEMENT
            ? ctx.normalizeSettlement(gridRowFactory(kind)())
            : ctx.ensureLineRowIdentity(gridRowFactory(kind)());
    }

    function markRowDeleted(row = {}, extraFields = []) {
        const nextRow = { ...(row || {}) };
        const dirtyFields = Array.isArray(nextRow.dirty_fields) ? nextRow.dirty_fields.slice() : [];
        nextRow.row_state = 'deleted';
        if (!dirtyFields.includes('row_state')) {
            dirtyFields.push('row_state');
        }
        extraFields.forEach((field) => {
            if (!dirtyFields.includes(field)) {
                dirtyFields.push(field);
            }
        });
        nextRow.dirty_fields = dirtyFields;
        return nextRow;
    }

    function syncGridAfterDataMutation(kind, rowIndex = -1, options = {}) {
        if (kind === ctx.GRID_KIND_SETTLEMENT) {
            ctx.saveCurrentSettlementRows();
            ctx.calculateTotals();
            return;
        }

        ctx.updateLineEmptyState();

        if (options.skipTotals !== true) {
            ctx.calculateTotals();
        }
    }

    function insertGridRow(kind, afterRowIndex, options = {}) {
        const adapter = gridAdapter(kind);
        if (!adapter) {
            return null;
        }

        const currentRows = gridDataRows(kind);
        const insertIndex = Math.max(0, Math.min(Number(afterRowIndex) + 1, currentRows.length));
        currentRows.splice(insertIndex, 0, createKeyboardRow(kind));
        adapter.loadData(currentRows);
        syncGridAfterDataMutation(kind, insertIndex);

        const focusField = String(options.focusField || gridFocusField(kind)).trim();
        focusGridCell(kind, insertIndex, focusField, { startEditing: options.startEditing === true });
        return { rowIndex: insertIndex, colId: focusField };
    }

    function appendGridRow(kind, options = {}) {
        const currentRows = gridDataRows(kind);
        return insertGridRow(kind, currentRows.length - 1, options);
    }

    function removeGridRow(kind, rowIndex, options = {}) {
        const adapter = gridAdapter(kind);
        if (!adapter || rowIndex < 0) {
            return null;
        }

        const currentRows = gridDataRows(kind);
        const removedRow = currentRows[rowIndex];
        if (!removedRow) {
            return null;
        }
        const nextRows = currentRows.slice();

        if (options.logicalDelete === true) {
            deletedRowsStore(kind).push(markRowDeleted(removedRow));
        }

        nextRows.splice(rowIndex, 1);

        adapter.loadData(nextRows);
        const nextRowIndex = nextRows.length > 0
            ? Math.min(rowIndex, Math.max(nextRows.length - 1, 0))
            : -1;
        syncGridAfterDataMutation(kind, nextRowIndex);

        if (nextRowIndex >= 0) {
            const nextColId = normalizeFocusedCell(kind, nextRowIndex).colId;
            focusGridCell(kind, nextRowIndex, nextColId);
            return { rowIndex: nextRowIndex, colId: nextColId };
        }

        clearGridEditingCell(kind);
        updateGridActiveCell(kind, -1, '');
        if (kind === ctx.GRID_KIND_LINE) {
            ctx.updateLineEmptyState();
        }
        return { rowIndex: -1, colId: '' };
    }

    function clearGridCell(kind, rowIndex, colId) {
        if (rowIndex < 0 || !colId || String(colId).startsWith('__')) {
            return;
        }

        if (kind === ctx.GRID_KIND_SETTLEMENT) {
            ctx.setSettlementCellValue(rowIndex, colId, '', 'keyboard-clear');
            ctx.saveCurrentSettlementRows();
            ctx.calculateTotals();
            focusGridCell(kind, rowIndex, colId);
            return;
        }

        ctx.setLineCellValue(rowIndex, colId, '', 'keyboard-clear');
        ctx.calculateTotals();
        focusGridCell(kind, rowIndex, colId);
    }

    function selectAllGridCells(kind) {
        const adapter = gridAdapter(kind);
        const api = adapter?.api;
        const columns = getDisplayedInputColumns(kind);
        const rows = gridDataRows(kind);

        if (!api || columns.length === 0 || rows.length === 0) {
            return;
        }

        api.clearRangeSelection?.();
        api.addCellRange?.({
            rowStartIndex: 0,
            rowEndIndex: rows.length - 1,
            columns: columns.map((column) => gridColumnId(column)),
        });
        setClipboardSelection(kind, {
            mode: 'all',
            rowStartIndex: 0,
            rowEndIndex: rows.length - 1,
            columns: columns.map((column) => gridColumnId(column)),
        });
        focusGridCell(kind, 0, gridColumnId(columns[0]));
    }

    function extractRangeSelection(kind) {
        const adapter = gridAdapter(kind);
        const api = adapter?.api;
        const ranges = api?.getCellRanges?.() || [];
        const firstRange = Array.isArray(ranges) && ranges.length > 0 ? ranges[0] : null;
        if (!firstRange) {
            return null;
        }

        const startRow = Number(firstRange.startRow?.rowIndex ?? firstRange.rowStartIndex ?? -1);
        const endRow = Number(firstRange.endRow?.rowIndex ?? firstRange.rowEndIndex ?? -1);
        const columns = (firstRange.columns || [])
            .map((column) => gridColumnId(column))
            .filter(Boolean);
        if (startRow < 0 || endRow < 0 || columns.length === 0) {
            return null;
        }

        return {
            mode: 'range',
            rowStartIndex: Math.min(startRow, endRow),
            rowEndIndex: Math.max(startRow, endRow),
            columns,
        };
    }

    function gridSelectionMatrix(kind) {
        const state = gridState(kind);
        const rows = gridDataRows(kind);
        const inputColumnIds = getDisplayedInputColumnIds(kind);
        const rangeSelection = extractRangeSelection(kind);
        const selection = rangeSelection || state?.clipboardSelection || { mode: 'cell' };

        if (selection.mode === 'all') {
            return rows.map((row) => inputColumnIds.map((columnId) => String(row?.[columnId] ?? '')));
        }

        if (selection.mode === 'range') {
            const matrix = [];
            for (let rowIndex = selection.rowStartIndex; rowIndex <= selection.rowEndIndex; rowIndex += 1) {
                const row = rows[rowIndex] || {};
                matrix.push(selection.columns.map((columnId) => String(row?.[columnId] ?? '')));
            }
            return matrix;
        }

        const activeCell = normalizeFocusedCell(kind);
        const row = rows[activeCell.rowIndex] || {};
        return [[String(row?.[activeCell.colId] ?? '')]];
    }

    async function copyGridSelection(kind) {
        const matrix = gridSelectionMatrix(kind);
        const text = matrix.map((row) => row.join('\t')).join('\n');
        if (!navigator.clipboard?.writeText) {
            return false;
        }
        await navigator.clipboard.writeText(text);
        return true;
    }

    function normalizeClipboardCellValue(kind, field, rawValue) {
        const value = String(rawValue ?? '').trim();
        if (kind === ctx.GRID_KIND_LINE) {
            if (field === 'item_date') {
                return value === '' ? '' : ctx.formatDateInputValue(value);
            }
            if (['quantity', 'unit_price', 'foreign_unit_price', 'foreign_amount', 'amount'].includes(field)) {
                return value === '' ? '' : ctx.numberValue(value);
            }
            if (field === 'unit_name') {
                const normalized = ctx.normalizeUnitCellValue(value);
                return normalized === ctx.UNIT_EMPTY_LABEL ? '' : normalized;
            }
            if (field === 'tax_type') {
                return value === '' ? ctx.defaultLineTaxTypeLabel() : ctx.normalizeTaxTypeCellValue(value);
            }
            return value;
        }

        if (field === 'amount') {
            return value === '' ? '' : ctx.numberValue(value);
        }
        if (field === 'settlement_type') {
            return value === '' ? '' : ctx.settlementTypeLabelFromCode(value);
        }
        if (field === 'amount_sign') {
            return value === '' ? '' : ctx.amountSignLabelFromCode(value);
        }
        return value;
    }

    function loadGridRows(kind, rows = []) {
        const adapter = gridAdapter(kind);
        if (!adapter) {
            return;
        }

        const normalizedRows = kind === ctx.GRID_KIND_SETTLEMENT
            ? rows.map((row) => ctx.normalizeSettlement(row))
            : rows.map((row) => ctx.ensureLineRowIdentity(ctx.calculateLine(ctx.normalizeLine(row))));
        adapter.loadData(normalizedRows);
    }

    async function pasteGridSelection(kind) {
        if (!navigator.clipboard?.readText) {
            return false;
        }

        const clipboardText = await navigator.clipboard.readText();
        if (String(clipboardText || '').trim() === '') {
            return true;
        }

        const matrix = String(clipboardText)
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .split('\n')
            .filter((row, index, rows) => row !== '' || index < rows.length - 1)
            .map((row) => row.split('\t'));
        if (matrix.length === 0) {
            return true;
        }

        const inputColumnIds = getDisplayedInputColumnIds(kind);
        if (inputColumnIds.length === 0) {
            return true;
        }

        let activeCell = normalizeFocusedCell(kind);
        if (gridDataRows(kind).length === 0) {
            const inserted = appendGridRow(kind, {
                focusField: gridFocusField(kind),
                startEditing: false,
            });
            if (inserted) {
                activeCell = inserted;
            }
        }

        const startColumnIndex = Math.max(0, inputColumnIds.indexOf(activeCell.colId));
        const rows = gridDataRows(kind).map((row) => ({ ...(row || {}) }));
        const requiredRowCount = activeCell.rowIndex + matrix.length;
        while (rows.length < requiredRowCount) {
            rows.push(createKeyboardRow(kind));
        }

        let lastRowIndex = activeCell.rowIndex;
        let lastColId = inputColumnIds[startColumnIndex];

        matrix.forEach((cells, rowOffset) => {
            const targetRowIndex = activeCell.rowIndex + rowOffset;
            const targetRow = rows[targetRowIndex] || createKeyboardRow(kind);
            cells.forEach((cellValue, columnOffset) => {
                const targetColumnId = inputColumnIds[startColumnIndex + columnOffset];
                if (!targetColumnId) {
                    return;
                }

                targetRow[targetColumnId] = normalizeClipboardCellValue(kind, targetColumnId, cellValue);
                lastRowIndex = targetRowIndex;
                lastColId = targetColumnId;
            });
            rows[targetRowIndex] = targetRow;
        });

        loadGridRows(kind, rows);
        syncGridAfterDataMutation(kind, lastRowIndex);
        focusGridCell(kind, lastRowIndex, lastColId);
        return true;
    }

    function moveGridToRowBoundary(kind, rowIndex, toEnd = false) {
        const columnIds = getDisplayedRowColumnIds(kind);
        if (columnIds.length === 0) {
            return;
        }

        const targetColId = toEnd ? columnIds[columnIds.length - 1] : columnIds[0];
        focusGridCell(kind, rowIndex, targetColId);
    }

    function resolveAdjacentNavigableCell(kind, params = {}) {
        const previousCell = params.previousCellPosition || {};
        const nextCell = params.nextCellPosition || {};
        const key = String(params.key || '').trim();
        const previousRowIndex = Number(previousCell.rowIndex);
        const nextRowIndex = Number(nextCell.rowIndex);
        const previousColId = String(previousCell.column?.getColId?.() || '').trim();
        const nextColId = String(nextCell.column?.getColId?.() || '').trim();
        const displayedColumns = getDisplayedRowColumns(kind);
        const displayedColumnIds = displayedColumns.map((column) => gridColumnId(column));

        if (displayedColumns.length === 0) {
            return null;
        }

        if (nextRowIndex < 0) {
            return params.nextCellPosition || null;
        }

        const isVerticalMove = previousColId !== '' && nextColId === previousColId;
        if (isVerticalMove) {
            const currentColumn = displayedColumns.find((column) => gridColumnId(column) === previousColId) || displayedColumns[0];
            return currentColumn ? {
                rowIndex: nextRowIndex,
                rowPinned: nextCell.rowPinned ?? null,
                column: currentColumn,
            } : null;
        }

        const backwards = key === 'ArrowLeft'
            ? true
            : key === 'ArrowRight'
                ? false
                : nextRowIndex < previousRowIndex;
        const rowCount = gridDataRows(kind).length;
        const previousIndex = displayedColumnIds.indexOf(previousColId);
        if (previousIndex < 0) {
            return params.nextCellPosition || null;
        }

        let targetRowIndex = previousRowIndex;
        let targetColumnIndex = previousIndex + (backwards ? -1 : 1);

        if (backwards && targetColumnIndex < 0) {
            targetRowIndex = Math.max(previousRowIndex - 1, 0);
            targetColumnIndex = displayedColumns.length - 1;
        } else if (!backwards && targetColumnIndex >= displayedColumns.length) {
            targetRowIndex = Math.min(previousRowIndex + 1, Math.max(rowCount - 1, 0));
            targetColumnIndex = 0;
        }

        const targetColumn = displayedColumns[targetColumnIndex] || null;
        if (!targetColumn) {
            return null;
        }

        updateGridActiveCell(kind, targetRowIndex, gridColumnId(targetColumn));
        return {
            rowIndex: targetRowIndex,
            rowPinned: null,
            column: targetColumn,
        };
    }

    function resolveTabCellPosition(kind, params = {}) {
        const previousCell = params.previousCellPosition || {};
        const previousRowIndex = Number(previousCell.rowIndex);
        const previousColId = String(previousCell.column?.getColId?.() || '').trim();
        const inputColumns = getDisplayedInputColumns(kind);
        const inputColumnIds = inputColumns.map((column) => gridColumnId(column));
        const currentColumnIndex = inputColumnIds.indexOf(previousColId);

        if (previousRowIndex < 0 || currentColumnIndex < 0 || inputColumnIds.length === 0) {
            return params.nextCellPosition || null;
        }

        const backwards = Boolean(params.backwards);
        const lastColumnIndex = inputColumnIds.length - 1;
        let targetRowIndex = previousRowIndex;
        let targetColumnIndex = currentColumnIndex + (backwards ? -1 : 1);

        if (backwards && targetColumnIndex < 0) {
            targetRowIndex = Math.max(previousRowIndex - 1, 0);
            targetColumnIndex = targetRowIndex === previousRowIndex ? 0 : lastColumnIndex;
        } else if (!backwards && targetColumnIndex > lastColumnIndex) {
            targetRowIndex = previousRowIndex + 1;
            targetColumnIndex = 0;
            if (targetRowIndex >= gridDataRows(kind).length) {
                insertGridRow(kind, previousRowIndex, { focusField: inputColumnIds[0], startEditing: false });
                scheduleGridEditing(kind, targetRowIndex, inputColumnIds[0]);
            }
        }

        const resolvedColumns = getDisplayedInputColumns(kind);
        const resolvedColumn = resolvedColumns[targetColumnIndex] || resolvedColumns[0];
        if (!resolvedColumn) {
            return null;
        }

        updateGridActiveCell(kind, targetRowIndex, gridColumnId(resolvedColumn));
        return {
            rowIndex: targetRowIndex,
            rowPinned: null,
            column: resolvedColumn,
        };
    }

    function resolveEnterCellPosition(kind, params = {}) {
        const previousCell = params.previousCellPosition || {};
        const previousRowIndex = Number(previousCell.rowIndex);
        const previousColumn = previousCell.column || null;
        const previousColId = String(previousColumn?.getColId?.() || '').trim();

        if (previousRowIndex < 0 || !previousColumn || previousColId === '') {
            return params.nextCellPosition || null;
        }

        let targetRowIndex = previousRowIndex + 1;
        if (targetRowIndex >= gridDataRows(kind).length) {
            insertGridRow(kind, previousRowIndex, { focusField: previousColId, startEditing: false });
            scheduleGridEditing(kind, targetRowIndex, previousColId);
        }

        updateGridActiveCell(kind, targetRowIndex, previousColId);
        return {
            rowIndex: targetRowIndex,
            rowPinned: null,
            column: previousColumn,
        };
    }

    function handleGridKeyboardAction(kind, params = {}) {
        const domEvent = params.event;
        if (!domEvent) {
            return false;
        }

        const key = String(domEvent.key || '');
        const rowIndex = Number(params.rowIndex);
        const colId = String(params.column?.getColId?.() || '').trim();
        const adapter = gridAdapter(kind);
        if (!adapter) {
            return false;
        }

        if (domEvent.ctrlKey && key.toLowerCase() === 'a') {
            domEvent.preventDefault();
            selectAllGridCells(kind);
            return true;
        }

        if (domEvent.ctrlKey && key.toLowerCase() === 'c') {
            domEvent.preventDefault();
            void copyGridSelection(kind);
            return true;
        }

        if (domEvent.ctrlKey && key.toLowerCase() === 'v') {
            domEvent.preventDefault();
            void pasteGridSelection(kind);
            return true;
        }

        if (domEvent.ctrlKey && key === 'Delete') {
            domEvent.preventDefault();
            removeGridRow(kind, rowIndex, { logicalDelete: true });
            return true;
        }

        if (key === 'Insert') {
            domEvent.preventDefault();
            insertGridRow(kind, rowIndex, { focusField: gridFocusField(kind), startEditing: true });
            return true;
        }

        if (adapter.isEditing()) {
            return false;
        }

        if (!domEvent.ctrlKey && !domEvent.altKey && !domEvent.metaKey) {
            const isPrintableKey = key.length === 1 && !PRINTABLE_CONTROL_KEYS.has(key);
            const isImeKey = isImeCompositionTrigger(domEvent, key);

            if ((isPrintableKey || isImeKey) && rowIndex >= 0 && colId !== '') {
                startGridEditingImmediately(kind, rowIndex, colId);
                return true;
            }
        }

        if (!domEvent.ctrlKey && !domEvent.altKey && !domEvent.metaKey && key.length === 1 && !PRINTABLE_CONTROL_KEYS.has(key)) {
            return false;
        }

        if (key === 'Delete') {
            domEvent.preventDefault();
            clearGridCell(kind, rowIndex, colId);
            return true;
        }

        if (key === 'Home') {
            domEvent.preventDefault();
            moveGridToRowBoundary(kind, rowIndex, false);
            return true;
        }

        if (key === 'End') {
            domEvent.preventDefault();
            moveGridToRowBoundary(kind, rowIndex, true);
            return true;
        }

        return false;
    }

    function focusInitialLineGridCell(options = {}) {
        ctx.initLineGrid?.();

        const rows = ctx.getLineRows();
        if (!Array.isArray(rows) || rows.length === 0) {
            clearGridEditingCell(ctx.GRID_KIND_LINE);
            updateGridActiveCell(ctx.GRID_KIND_LINE, -1, '');
            ctx.updateLineEmptyState?.();
            return;
        }

        const target = normalizeFocusedCell(
            ctx.GRID_KIND_LINE,
            0,
            String(options.colId || gridFocusField(ctx.GRID_KIND_LINE)).trim()
        );

        focusGridCell(
            ctx.GRID_KIND_LINE,
            target.rowIndex,
            target.colId,
            { startEditing: options.startEditing === true }
        );
    }

    Object.assign(ctx, { gridState, gridAdapter, gridRowFactory, updateGridActiveCell, updateGridEditingCell, clearGridEditingCell, gridFocusField, gridDefaultEndField, gridDataRows, gridColumnId, getDisplayedInputColumns, getDisplayedInputColumnIds, getDisplayedRowColumns, getDisplayedRowColumnIds, normalizeFocusedCell, focusGridCell, scheduleGridEditing, startGridEditingImmediately, createKeyboardRow, syncGridAfterDataMutation, insertGridRow, appendGridRow, removeGridRow, clearGridCell, selectAllGridCells, copyGridSelection, pasteGridSelection, moveGridToRowBoundary, resolveAdjacentNavigableCell, resolveTabCellPosition, resolveEnterCellPosition, handleGridKeyboardAction, focusInitialLineGridCell });
    return ctx;
}
