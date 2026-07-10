import {
    applyAgGridColumnWidthSettings,
    collectAgGridColumns,
    loadAgGridColumnWidthSettings,
    resolveAgGridColumnKey,
    saveAgGridColumnWidthSettings,
    scheduleSaveAgGridColumnWidthSettings,
} from '/public/assets/js/common/grid/ag-grid-column-settings.js';

export function registerStorage(ctx) {
    function gridUserSettingPageKey(storageKey = '') {
        if (storageKey === ctx.LINE_GRID_SETTINGS_KEY) {
            return 'ledger.transaction-item-grid';
        }

        if (storageKey === ctx.SETTLEMENT_GRID_SETTINGS_KEY) {
            return 'ledger.transaction-settlement-grid';
        }

        return '';
    }

    function refreshLineGridDimensions() {
        window.requestAnimationFrame(() => {
            ctx.lineGrid?.refreshDimensions?.();
            ctx.settlementGrid?.refreshDimensions?.();
            syncGridAutoWidth(ctx.lineGrid, ctx.gridEl);
            syncGridAutoWidth(ctx.settlementGrid, ctx.settlementGridEl);
        });
    }

    function syncGridAutoWidth(gridAdapter, host) {
        const resolvedAdapter = gridAdapter?.api ? gridAdapter : { api: gridAdapter };
        if (!resolvedAdapter.api || !host) return;
    }

    function getDisplayedColumnActualWidths(gridAdapter) {
        const resolvedAdapter = gridAdapter?.api ? gridAdapter : { api: gridAdapter };
        const columns = resolvedAdapter.api?.getAllDisplayedColumns?.()
            || resolvedAdapter.api?.getColumnApi?.()?.getAllDisplayedColumns?.()
            || [];
        if (Array.isArray(columns) && columns.length > 0) {
            return columns.map((column) => Number(column?.getActualWidth?.() || column?.getMinWidth?.() || 0));
        }

        const defs = resolvedAdapter.columnDefs || [];
        return Array.isArray(defs)
            ? defs.map((column) => Number(column?.width || column?.minWidth || 0))
            : [];
    }

    Object.assign(ctx, {
        refreshLineGridDimensions,
        syncGridAutoWidth,
        getDisplayedColumnActualWidths,
        loadAgGridColumnWidthSettings,
        collectAgGridColumns,
        resolveAgGridColumnKey,
        saveAgGridColumnWidthSettings(storageKey, api) {
            return saveAgGridColumnWidthSettings(storageKey, api, {
                userSettingPageKey: gridUserSettingPageKey(storageKey),
                description: ctx.TRANSACTION_PAGE_DESCRIPTION,
                minWidth: ctx.AG_GRID_MIN_SAVED_WIDTH,
            });
        },
        applyAgGridColumnWidthSettings(columnDefs, settings) {
            return applyAgGridColumnWidthSettings(columnDefs, settings, {
                minWidth: ctx.AG_GRID_MIN_SAVED_WIDTH,
            });
        },
        scheduleSaveAgGridColumnWidthSettings(storageKey, api) {
            return scheduleSaveAgGridColumnWidthSettings(storageKey, api, ctx.agGridWidthSaveTimers, {
                debounceMs: ctx.AG_GRID_WIDTH_SAVE_DEBOUNCE_MS,
                userSettingPageKey: gridUserSettingPageKey(storageKey),
                description: ctx.TRANSACTION_PAGE_DESCRIPTION,
                minWidth: ctx.AG_GRID_MIN_SAVED_WIDTH,
            });
        },
    });
    return ctx;
}
