export function registerGrid(ctx) {
    const {
        dateStringEditor,
        selectEditor,
        gridNumberFormatter,
        gridNumberParser,
        createAgGridInputAdapter,
    } = ctx;

    const LINE_COLUMNS = [
        {
            field: '__move',
            headerName: '',
            headerComponent: ctx.dragHandleHeaderComponent(),
            editable: false,
            width: 34,
            resizable: true,
            rowDrag: false,
            rowDragText: '',
            suppressSizeToFit: false,
            headerClass: 'text-center transaction-line-drag-head',
            cellClass: 'text-center transaction-line-drag-cell',
            cellRenderer: ctx.dragHandleCellRenderer,
        },
        {
            field: '__row_no',
            headerName: '순번',
            editable: false,
            width: 58,
            resizable: true,
            valueGetter: (params) => `${Number(params.node?.rowIndex || 0) + 1}`,
            cellClass: 'transaction-line-row-no-cell',
        },
        {
            field: 'item_date',
            headerName: '거래일',
            width: 100,
            resizable: true,
            ...dateStringEditor(),
        },
        {
            field: 'item_name',
            headerName: '품명',
            width: 160,
            resizable: true,
        },
        {
            field: 'specification',
            headerName: '규격',
            width: 120,
            resizable: true,
        },
        {
            field: 'unit_name',
            headerName: '단위',
            ...selectEditor([ctx.UNIT_EMPTY_LABEL]),
            valueFormatter: ({ value }) => String(value ?? ''),
            width: 70,
            resizable: true,
            cellClass: 'transaction-line-unit-cell text-center',
        },
        {
            field: 'quantity',
            headerName: '수량',
            width: 80,
            resizable: true,
            type: 'numericColumn',
            valueFormatter: gridNumberFormatter,
            valueParser: gridNumberParser,
            cellClass: 'text-end',
        },
        {
            field: 'unit_price',
            headerName: '단가',
            width: 100,
            resizable: true,
            type: 'numericColumn',
            valueFormatter: gridNumberFormatter,
            valueParser: gridNumberParser,
            cellClass: 'text-end',
        },
        {
            field: 'amount',
            headerName: '공급가액',
            width: 110,
            resizable: true,
            type: 'numericColumn',
            valueFormatter: gridNumberFormatter,
            valueParser: gridNumberParser,
            cellClass: 'text-end',
        },
        {
            field: 'description',
            headerName: '적요',
            width: 160,
            resizable: true,
        },
        {
            field: '__actions',
            headerName: '+추가',
            editable: false,
            width: 70,
            resizable: true,
            sortable: false,
            suppressHeaderMenuButton: true,
            suppressMovable: true,
            headerClass: 'transaction-line-add-head text-center',
            cellClass: 'transaction-line-action-cell text-center',
            cellRenderer: ctx.lineActionRenderer,
        },
    ];

    const SETTLEMENT_COLUMNS = [
        {
            field: '__move',
            headerName: '',
            headerComponent: ctx.dragHandleHeaderComponent(),
            editable: false,
            width: 34,
            resizable: true,
            rowDrag: false,
            rowDragText: '',
            suppressSizeToFit: false,
            headerClass: 'text-center transaction-line-drag-head',
            cellClass: 'text-center transaction-line-drag-cell',
            cellRenderer: ctx.dragHandleCellRenderer,
        },
        {
            field: '__row_no',
            headerName: '순번',
            editable: false,
            width: 58,
            resizable: true,
            valueGetter: (params) => `${Number(params.node?.rowIndex || 0) + 1}`,
            cellClass: 'transaction-line-row-no-cell',
        },
        {
            field: 'settlement_type',
            headerName: '정산유형',
            ...selectEditor(ctx.DEFAULT_SETTLEMENT_TYPE_OPTIONS.map((option) => option.label)),
            width: 140,
            resizable: true,
        },
        {
            field: 'amount_sign',
            headerName: '가감유형',
            ...selectEditor(ctx.DEFAULT_AMOUNT_SIGN_OPTIONS.map((option) => option.label)),
            width: 102,
            resizable: true,
            cellClass: 'text-center',
        },
        {
            field: 'amount',
            headerName: '정산금액',
            width: 118,
            resizable: true,
            type: 'numericColumn',
            valueFormatter: gridNumberFormatter,
            valueParser: gridNumberParser,
            cellClass: 'text-end',
        },
        {
            field: 'description',
            headerName: '적요',
            width: 210,
            resizable: true,
        },
        {
            field: '__actions',
            headerName: '+추가',
            editable: false,
            width: 70,
            resizable: true,
            sortable: false,
            suppressHeaderMenuButton: true,
            suppressMovable: true,
            headerClass: 'transaction-line-add-head text-center',
            cellClass: 'transaction-line-action-cell text-center',
            cellRenderer: ctx.lineActionRenderer,
        },
    ];

    function getLineColumns() {
        const foreignMode = ctx.usesForeignCurrency();
        const baseColumns = LINE_COLUMNS.map((column) => {
            if (column.field === 'unit_name') {
                return {
                    ...column,
            ...selectEditor(() => [ctx.UNIT_EMPTY_LABEL, ...ctx.unitOptions.map((option) => option.label), ctx.UNIT_QUICK_ADD_LABEL]),
                };
            }
            if (column.field === 'item_date') {
                return { ...column, ...dateStringEditor() };
            }
            if (column.field === 'quantity' || column.field === 'unit_price' || column.field === 'amount') {
                return { ...column };
            }
            return { ...column };
        });

        const columns = [];
        baseColumns.forEach((column) => {
            columns.push(column);
            if (column.field === 'quantity') {
                columns.push(
                    {
                        field: 'foreign_unit_price',
                        headerName: '외화단가',
                        hide: !foreignMode,
                        headerClass: 'transaction-line-foreign-head',
                        type: 'numericColumn',
                        valueFormatter: gridNumberFormatter,
                        valueParser: gridNumberParser,
                        width: 110,
                        resizable: true,
                        cellClass: 'transaction-line-foreign-cell text-end',
                    },
                    {
                        field: 'foreign_amount',
                        headerName: '외화금액',
                        hide: !foreignMode,
                        headerClass: 'transaction-line-foreign-head',
                        type: 'numericColumn',
                        valueFormatter: gridNumberFormatter,
                        valueParser: gridNumberParser,
                        width: 120,
                        resizable: true,
                        cellClass: 'transaction-line-foreign-cell text-end',
                    },
                );
            }
        });

        return columns;
    }

    function getSettlementColumns() {
        return SETTLEMENT_COLUMNS.map((column) => {
            if (column.field === 'settlement_type') {
                return {
                    ...column,
            ...selectEditor(() => (ctx.settlementTypeOptions.length > 0 ? ctx.settlementTypeOptions : ctx.DEFAULT_SETTLEMENT_TYPE_OPTIONS)
                .map((option) => option.label)),
                };
            }
            if (column.field === 'amount_sign') {
                return {
                    ...column,
            ...selectEditor(() => (ctx.amountSignOptions.length > 0 ? ctx.amountSignOptions : ctx.DEFAULT_AMOUNT_SIGN_OPTIONS)
                .map((option) => option.label)),
                };
            }
            return { ...column };
        });
    }

    function forceGridColumnWidths(api, columnDefs = []) {
        if (!api || !Array.isArray(columnDefs)) return;

        columnDefs.forEach((column) => {
            const field = String(column?.field || '');
            const width = Number(column?.width || column?.minWidth || 0);
            if (field === '' || !Number.isFinite(width) || width <= 0) return;
            if (column.hide) return;

            // api.setColumnWidth?.(field, width, false);
        });
    }

    function syncLineForeignColumns() {
        if (!ctx.lineGrid?.api) return;
        const visible = ctx.usesForeignCurrency();
        ctx.lineGrid.api.setColumnsVisible?.(['foreign_unit_price', 'foreign_amount'], visible);
        forceGridColumnWidths(ctx.lineGrid.api, getLineColumns());
    }

    function getLineHeaderRow() {
        return ctx.gridEl.querySelector('.ag-header-row')
            || ctx.gridEl.querySelector('thead tr');
    }

    function ensureFloatingLineHeader() {
        return null;
    }

    function hideFloatingLineHeader() {
        if (ctx.floatingLineHeaderEl) {
            ctx.floatingLineHeaderEl.classList.remove('is-visible');
        }
    }

    function updateFloatingLineHeader() {
        ctx.lineHeaderFrame = null;
        hideFloatingLineHeader();
    }

    function syncFloatingLineHeader() {
        if (ctx.lineHeaderFrame) return;
        ctx.lineHeaderFrame = window.requestAnimationFrame(updateFloatingLineHeader);
    }

    function bindLineHeaderStickiness() {
        hideFloatingLineHeader();
    }

    function initLineGrid() {
        if (ctx.lineGrid) return;

        const lineWidthSettings = ctx.loadAgGridColumnWidthSettings(ctx.LINE_GRID_SETTINGS_KEY, {
            userSettingPageKey: 'ledger.transaction-item-grid',
        });
        const lineColumnDefs = ctx.applyAgGridColumnWidthSettings(getLineColumns(), lineWidthSettings);

        ctx.lineGrid = createAgGridInputAdapter(ctx.gridEl, {
            rowData: [],
            columnDefs: lineColumnDefs,
            autoFitColumns: false,
            className: 'transaction-line-ag-grid ag-theme-quartz',
            keyboardMode: 'excel-selection',
            gridOptions: {
                domLayout: 'normal',
                headerHeight: 40,
                defaultColDef: {
                    suppressKeyboardEvent(params) {
                        return ctx.handleGridKeyboardAction(ctx.GRID_KIND_LINE, params);
                    },
                },
                singleClickEdit: false,
                suppressClickEdit: false,
                suppressColumnVirtualisation: true,
                stopEditingWhenCellsLoseFocus: false,
                alwaysShowHorizontalScroll: true,
                enterNavigatesVertically: true,
                enterNavigatesVerticallyAfterEdit: true,
                navigateToNextCell(params) {
                    if (params?.key === 'Enter') {
                        return ctx.resolveEnterCellPosition(ctx.GRID_KIND_LINE, params);
                    }
                    return ctx.resolveAdjacentNavigableCell(ctx.GRID_KIND_LINE, params) || params.nextCellPosition;
                },
                tabToNextCell(params) {
                    return ctx.resolveTabCellPosition(ctx.GRID_KIND_LINE, params);
                },
                onGridReady(event) {
                    const api = event?.api;
                    const defs = lineColumnDefs;
                    forceGridColumnWidths(event?.api, defs);
                    syncLineForeignColumns();
                    ctx.syncGridAutoWidth({ api: event?.api, columnDefs: defs }, ctx.gridEl);
                },
                onColumnResized(event) {
                    if (event && event.finished === false) return;
                    ctx.scheduleSaveAgGridColumnWidthSettings(ctx.LINE_GRID_SETTINGS_KEY, event?.api || ctx.lineGrid?.api);
                    ctx.syncGridAutoWidth(ctx.lineGrid, ctx.gridEl);
                },
                onDisplayedColumnsChanged() {
                    forceGridColumnWidths(ctx.lineGrid?.api, getLineColumns());
                    ctx.syncGridAutoWidth(ctx.lineGrid, ctx.gridEl);
                },
                onSortChanged() {
                    ctx.syncGridAutoWidth(ctx.lineGrid, ctx.gridEl);
                },
            },
            deleteColumnField: '__actions',
            deleteButtonSelector: '.transaction-line-delete-text',
            onDeleteRow(event) {
                ctx.removeGridRow(ctx.GRID_KIND_LINE, event.rowIndex, { logicalDelete: true });
                return true;
            },
            addHeaderColumnField: '__actions',
            focusColumnAfterAdd: 'item_date',
            startEditingAfterAdd: true,
            addRow: () => ctx.blankLine(),
                onCellEditingStarted(event) {
                    ctx.updateGridEditingCell(ctx.GRID_KIND_LINE, event.rowIndex, event.colDef?.field || event.column?.getColId?.());
                    ctx.lineGridEditing = true;
                    ctx.bindLineDateEscHandler();
                    ctx.unbindLineDateInputFormatter();

                const field = event.colDef?.field;
                const row = event.rowIndex;
                if (field === 'item_date' && ctx.isLineDateCell(row, ctx.lineGrid?.getSelectedLast?.()[1] ?? ctx.LINE_ITEM_DATE_COL)) {
                    ctx.bindLineDateInputFormatter();
                }
                const previous = event.data?.[field] ?? '';
                if (field === 'item_date' && previous) {
                    ctx.bindLineDateInputFormatter();
                }
            },
                onCellEditingStopped() {
                    ctx.clearGridEditingCell(ctx.GRID_KIND_LINE);
                    ctx.lineGridEditing = false;
                    ctx.unbindLineDateInputFormatter();
                    ctx.unbindLineDateEscHandler();
            },
            onCellValueChanged(event) {
                const field = event.colDef?.field || '';
                const row = event.rowIndex;
                ctx.ensureLineRowIdentity(event.data || {});
                if (field === 'unit_name') {
                    const normalized = ctx.normalizeUnitCellValue(event.newValue);
                    if (normalized === ctx.UNIT_QUICK_ADD_LABEL) {
                        ctx.setLineCellValue(row, 'unit_name', event.oldValue || '', 'unit-quick-add-reset');
                        ctx.openUnitQuickAdd(row, ctx.LINE_UNIT_COL);
                        return;
                    }
                    if (normalized === ctx.UNIT_EMPTY_LABEL) {
                        ctx.setLineCellValue(row, 'unit_name', '', 'unit-empty');
                    } else {
                        ctx.setLineCellValue(row, field, normalized);
                    }
                    if (!ctx.isAllowedUnitCellValue(normalized)) {
                        ctx.notifyInvalidUnitValue(normalized);
                    }
                }
                if (field === 'item_date') {
                    const formatted = formatDateInputValue(event.newValue);
                    if (formatted !== event.newValue) {
                        ctx.setLineCellValue(row, field, formatted, 'date-format');
                        return;
                    }
                }
                ctx.calculateTotals();
            },
            onCellKeyDown(event) {
                const selected = ctx.lineGrid?.getSelectedLast?.() || [];
                const row = selected[0];
                const col = selected[1];
                if (!ctx.isLineDateCell(row, col)) return;

                if (event.event && ctx.isManualDateKey(event.event)) {
                    ctx.manualLineDateEdit = true;
                    window.setTimeout(ctx.closeGridDatePicker, 0);
                }
            },
                onCellFocused(event) {
                    ctx.updateGridActiveCell(ctx.GRID_KIND_LINE, event.rowIndex, event.column?.getColId?.());
                const row = event.rowIndex;
                const col = String(event.column?.getColId?.() || '');
                const fields = getLineColumns().map((column) => column.field);
                const colIndex = fields.indexOf(col);
                if (!ctx.isLineDateCell(row, colIndex)) {
                    ctx.closeGridDatePicker();
                }
            },
            onRowDragEnd() {
                ctx.calculateTotals();
                updateLineEmptyState();
            },
            onChanged() {
                ctx.calculateTotals();
                updateLineEmptyState();
            },
        });

        updateLineEmptyState();
    }

    function initSettlementGrid() {
        if (ctx.settlementGrid) return;

        const settlementWidthSettings = ctx.loadAgGridColumnWidthSettings(ctx.SETTLEMENT_GRID_SETTINGS_KEY, {
            userSettingPageKey: 'ledger.transaction-settlement-grid',
        });
        const settlementColumnDefs = ctx.applyAgGridColumnWidthSettings(getSettlementColumns(), settlementWidthSettings);

        ctx.settlementGrid = createAgGridInputAdapter(ctx.settlementGridEl, {
            rowData: [],
            columnDefs: settlementColumnDefs,
            autoFitColumns: false,
            className: 'transaction-line-ag-grid ag-theme-quartz',
            keyboardMode: 'excel-selection',
            gridOptions: {
                domLayout: 'normal',
                headerHeight: 40,
                defaultColDef: {
                    suppressKeyboardEvent(params) {
                        return ctx.handleGridKeyboardAction(ctx.GRID_KIND_SETTLEMENT, params);
                    },
                },
                singleClickEdit: false,
                suppressClickEdit: false,
                suppressColumnVirtualisation: true,
                stopEditingWhenCellsLoseFocus: false,
                alwaysShowHorizontalScroll: true,
                enterNavigatesVertically: true,
                enterNavigatesVerticallyAfterEdit: true,
                navigateToNextCell(params) {
                    if (params?.key === 'Enter') {
                        return ctx.resolveEnterCellPosition(ctx.GRID_KIND_SETTLEMENT, params);
                    }
                    return ctx.resolveAdjacentNavigableCell(ctx.GRID_KIND_SETTLEMENT, params) || params.nextCellPosition;
                },
                tabToNextCell(params) {
                    return ctx.resolveTabCellPosition(ctx.GRID_KIND_SETTLEMENT, params);
                },
                onGridReady(event) {
                    const defs = settlementColumnDefs;
                    forceGridColumnWidths(event?.api, defs);
                    ctx.syncGridAutoWidth({ api: event?.api, columnDefs: defs }, ctx.settlementGridEl);
                },
                onColumnResized(event) {
                    if (event && event.finished === false) return;
                    ctx.scheduleSaveAgGridColumnWidthSettings(ctx.SETTLEMENT_GRID_SETTINGS_KEY, event?.api || ctx.settlementGrid?.api);
                    ctx.syncGridAutoWidth(ctx.settlementGrid, ctx.settlementGridEl);
                },
                onDisplayedColumnsChanged() {
                    forceGridColumnWidths(ctx.settlementGrid?.api, getSettlementColumns());
                    ctx.syncGridAutoWidth(ctx.settlementGrid, ctx.settlementGridEl);
                },
                onSortChanged() {
                    ctx.syncGridAutoWidth(ctx.settlementGrid, ctx.settlementGridEl);
                },
            },
            deleteColumnField: '__actions',
            deleteButtonSelector: '.transaction-line-delete-text',
            onDeleteRow(event) {
                ctx.removeGridRow(ctx.GRID_KIND_SETTLEMENT, event.rowIndex, { logicalDelete: true });
                return true;
            },
            addHeaderColumnField: '__actions',
            focusColumnAfterAdd: 'settlement_type',
            startEditingAfterAdd: true,
            addRow: () => ctx.blankSettlement(),
                onCellValueChanged(event) {
                    const field = event.colDef?.field || '';
                    const row = event.rowIndex;
                if (field === 'settlement_type') {
                    ctx.setSettlementCellValue(row, 'settlement_type', ctx.settlementTypeLabelFromCode(event.newValue));
                }
                if (field === 'amount_sign') {
                    ctx.setSettlementCellValue(row, 'amount_sign', ctx.amountSignLabelFromCode(event.newValue));
                }
                ctx.saveCurrentSettlementRows();
                ctx.calculateTotals();
            },
                onRowDragEnd() {
                    ctx.saveCurrentSettlementRows();
                    ctx.calculateTotals();
                },
                onCellEditingStarted(event) {
                    ctx.updateGridEditingCell(ctx.GRID_KIND_SETTLEMENT, event.rowIndex, event.colDef?.field || event.column?.getColId?.());
                },
                onCellEditingStopped(event) {
                    ctx.clearGridEditingCell(ctx.GRID_KIND_SETTLEMENT);
                },
                onCellFocused(event) {
                    ctx.updateGridActiveCell(ctx.GRID_KIND_SETTLEMENT, event.rowIndex, event.column?.getColId?.());
                },
                onChanged() {
                    ctx.saveCurrentSettlementRows();
                    ctx.calculateTotals();
            },
        });
    }

    function updateLineEmptyState() {
        const sourceRows = ctx.lineGrid?.getSourceData?.();
        const rowCount = Array.isArray(sourceRows)
            ? sourceRows.length
            : (ctx.lineGrid?.countRows() || 0);
        ctx.gridEl.classList.toggle('is-empty', rowCount === 0);
    }

    function bindLineActionEvents() {
        if (!ctx.lineGrid) return;
    }

    function bindLineDragEvents() {
        if (!ctx.lineGrid) return;
    }


    Object.assign(ctx, { getLineColumns, getSettlementColumns, forceGridColumnWidths, syncLineForeignColumns, getLineHeaderRow, ensureFloatingLineHeader, hideFloatingLineHeader, updateFloatingLineHeader, syncFloatingLineHeader, bindLineHeaderStickiness, initLineGrid, initSettlementGrid, updateLineEmptyState, bindLineActionEvents, bindLineDragEvents, LINE_COLUMNS, SETTLEMENT_COLUMNS });
    return ctx;
}
