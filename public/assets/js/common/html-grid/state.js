const DEFAULT_CAPABILITIES = Object.freeze({
    addRow: true,
    deleteRow: true,
    insertRow: true,
    reorder: true,
    resize: true,
    keyboard: true,
    clipboard: false,
    footer: true,
    validation: true,
    selection: true,
    multiSelection: false,
    columnHide: true,
    columnMove: true,
    columnResize: true,
    stickyHeader: true,
});

const ROW_LIFECYCLE_VALUES = Object.freeze([
    'created',
    'clean',
    'updated',
    'deleted',
    'readonly',
    'disabled',
    'locked',
    'saving',
    'error',
]);

const CELL_LIFECYCLE_VALUES = Object.freeze([
    'normal',
    'editing',
    'dirty',
    'invalid',
    'readonly',
    'disabled',
    'focused',
    'selected',
]);

function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
}

export function normalizeGridCapabilities(capabilities = {}) {
    return {
        ...DEFAULT_CAPABILITIES,
        ...(capabilities && typeof capabilities === 'object' ? capabilities : {}),
    };
}

export function normalizeRowLifecycle(rowState = 'clean') {
    const normalized = String(rowState || '').trim().toLowerCase();
    return ROW_LIFECYCLE_VALUES.includes(normalized) ? normalized : 'clean';
}

export function normalizeCellLifecycle(cellStates = []) {
    const values = Array.isArray(cellStates) ? cellStates : [cellStates];
    const normalized = values
        .map((value) => String(value || '').trim().toLowerCase())
        .filter((value, index, list) => CELL_LIFECYCLE_VALUES.includes(value) && list.indexOf(value) === index);

    return normalized.length > 0 ? normalized : ['normal'];
}

export function createGridRow(row = {}, options = {}) {
    const values = row.values && typeof row.values === 'object' && !Array.isArray(row.values)
        ? cloneValue(row.values)
        : {};

    return {
        rowId: String(row.rowId || options.rowId || ''),
        rowState: normalizeRowLifecycle(row.rowState || options.rowState || 'clean'),
        dirtyFields: Array.isArray(row.dirtyFields) ? row.dirtyFields.map((field) => String(field || '')) : [],
        values,
        meta: row.meta && typeof row.meta === 'object' && !Array.isArray(row.meta) ? cloneValue(row.meta) : {},
        errorReason: row.errorReason ?? null,
    };
}

export function createGridCellState(cellState = {}) {
    return {
        state: normalizeCellLifecycle(cellState.state || cellState.states || ['normal']),
        message: String(cellState.message || ''),
    };
}

export function createInitialGridState(config = {}) {
    const rows = Array.isArray(config.rows) ? config.rows.map((row, index) => createGridRow(row, { rowId: `row-${index + 1}` })) : [];
    const cells = config.cells && typeof config.cells === 'object' && !Array.isArray(config.cells)
        ? cloneValue(config.cells)
        : {};

    return {
        gridId: String(config.gridId || ''),
        capabilities: normalizeGridCapabilities(config.capabilities),
        rows,
        cells,
        columns: {
            order: Array.isArray(config.columns?.order) ? [...config.columns.order] : [],
            hidden: Array.isArray(config.columns?.hidden) ? [...config.columns.hidden] : [],
            pinned: Array.isArray(config.columns?.pinned) ? [...config.columns.pinned] : [],
            widths: config.columns?.widths && typeof config.columns.widths === 'object' ? { ...config.columns.widths } : {},
            meta: config.columns?.meta && typeof config.columns.meta === 'object' ? cloneValue(config.columns.meta) : {},
        },
        selection: {
            activeCell: config.selection?.activeCell ?? null,
            range: config.selection?.range ?? null,
            selectedRowIds: Array.isArray(config.selection?.selectedRowIds) ? [...config.selection.selectedRowIds] : [],
        },
        ui: {
            loading: Boolean(config.ui?.loading),
            empty: Boolean(config.ui?.empty),
            editing: config.ui?.editing ?? null,
        },
        validation: {
            hasError: Boolean(config.validation?.hasError),
            rowErrors: config.validation?.rowErrors && typeof config.validation.rowErrors === 'object'
                ? cloneValue(config.validation.rowErrors)
                : {},
            cellErrors: config.validation?.cellErrors && typeof config.validation.cellErrors === 'object'
                ? cloneValue(config.validation.cellErrors)
                : {},
            messages: Array.isArray(config.validation?.messages) ? [...config.validation.messages] : [],
        },
        footer: {
            values: config.footer?.values && typeof config.footer.values === 'object' ? cloneValue(config.footer.values) : {},
            messages: Array.isArray(config.footer?.messages) ? [...config.footer.messages] : [],
            hasDifference: Boolean(config.footer?.hasDifference),
        },
        meta: {
            pluginState: config.meta?.pluginState && typeof config.meta.pluginState === 'object'
                ? cloneValue(config.meta.pluginState)
                : {},
            renderVersion: Number(config.meta?.renderVersion || 0),
        },
    };
}

export function cloneGridState(state = {}) {
    return createInitialGridState(cloneValue(state));
}

export function replaceGridState(targetState, nextState = {}) {
    const normalized = createInitialGridState(nextState);
    Object.keys(targetState).forEach((key) => {
        delete targetState[key];
    });
    Object.assign(targetState, normalized);
    return targetState;
}

export { DEFAULT_CAPABILITIES, ROW_LIFECYCLE_VALUES, CELL_LIFECYCLE_VALUES };
