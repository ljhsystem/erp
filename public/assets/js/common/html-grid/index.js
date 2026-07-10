import { createGridApi } from './api.js';
import { createColumnManager } from './column-manager.js';
import { createEditorRegistry } from './editor-registry.js';
import { createGridEventBus } from './event-bus.js';
import { createGridFooter } from './footer.js';
import {
    createFormatterRegistry,
    formatCurrency,
    formatDate,
    formatNumber,
    formatPercent,
    formatText,
} from './formatter.js';
import { createKeyboardController } from './keyboard.js';
import { createPluginRegistry } from './plugin-registry.js';
import { createGridRenderer } from './renderer.js';
import { createReorderController } from './reorder.js';
import { createResizeController } from './resize.js';
import { createRuntimeSchema } from './schema.js';
import { createSelectionController } from './selection.js';
import { createGridSerializer } from './serializer.js';
import { createInitialGridState } from './state.js';
import { createGridValidator } from './validator.js';
import { createAddRowCommand } from './commands/add-row-command.js';
import { createDeleteRowCommand } from './commands/delete-row-command.js';
import { createInsertRowCommand } from './commands/insert-row-command.js';
import { createMoveCellCommand } from './commands/move-cell-command.js';
import { createMoveRowCommand } from './commands/move-row-command.js';
import { createUpdateCellCommand } from './commands/update-cell-command.js';
import { createUpdateRowCommand } from './commands/update-row-command.js';
import { createDateEditor } from './editors/date-editor.js';
import { createNumberEditor } from './editors/number-editor.js';
import { createSelectEditor } from './editors/select-editor.js';
import { createTextEditor } from './editors/text-editor.js';
import { createAccountPickerPlugin } from './plugins/account-picker.js';
import { createCodePickerPlugin } from './plugins/code-picker.js';
import { createCurrencyPlugin } from './plugins/currency.js';
import { createDatepickerPlugin } from './plugins/datepicker.js';
import { createNumberPlugin } from './plugins/number.js';
import { createSelect2Plugin } from './plugins/select2.js';

function resolveDocument(config = {}) {
    if (config.document) {
        return config.document;
    }

    if (config.host?.ownerDocument) {
        return config.host.ownerDocument;
    }

    if (typeof document !== 'undefined') {
        return document;
    }

    return null;
}

function cloneValue(value) {
    if (Array.isArray(value)) {
        return value.map(cloneValue);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, entry]) => [key, cloneValue(entry)]));
    }

    return value;
}

function createBuiltinFormatterMap(customFormatters = {}) {
    return {
        text: formatText,
        number: formatNumber,
        currency: formatCurrency,
        date: formatDate,
        percent: formatPercent,
        ...(customFormatters && typeof customFormatters === 'object' ? customFormatters : {}),
    };
}

function createBuiltinEditorMap(customEditors = {}) {
    return {
        text: createTextEditor,
        number: createNumberEditor,
        date: createDateEditor,
        select: createSelectEditor,
        ...(customEditors && typeof customEditors === 'object' ? customEditors : {}),
    };
}

function createBuiltinPluginMap(customPlugins = {}) {
    return {
        select2: createSelect2Plugin,
        datepicker: createDatepickerPlugin,
        number: createNumberPlugin,
        currency: createCurrencyPlugin,
        'code-picker': createCodePickerPlugin,
        'account-picker': createAccountPickerPlugin,
        ...(customPlugins && typeof customPlugins === 'object' ? customPlugins : {}),
    };
}

function buildInitialStateConfig(config = {}, schema) {
    const columns = schema.columns;
    const baseState = config.state && typeof config.state === 'object' ? config.state : {};
    const baseColumns = baseState.columns && typeof baseState.columns === 'object' ? baseState.columns : {};

    return {
        gridId: config.gridId || baseState.gridId || '',
        rows: Array.isArray(baseState.rows) ? baseState.rows : (Array.isArray(config.rows) ? config.rows : []),
        cells: baseState.cells || config.cells || {},
        capabilities: baseState.capabilities || config.capabilities || {},
        selection: baseState.selection || config.selection || {},
        ui: baseState.ui || config.ui || {},
        meta: baseState.meta || config.meta || {},
        validation: baseState.validation || config.validation || {},
        footer: baseState.footer || config.footer || {},
        columns: {
            order: Array.isArray(baseColumns.order) && baseColumns.order.length > 0
                ? baseColumns.order
                : columns.map((column) => column.key),
            hidden: Array.isArray(baseColumns.hidden)
                ? baseColumns.hidden
                : columns.filter((column) => column.visible === false).map((column) => column.key),
            pinned: Array.isArray(baseColumns.pinned)
                ? baseColumns.pinned
                : columns.filter((column) => Boolean(column.pinned)).map((column) => column.key),
            widths: {
                ...Object.fromEntries(columns.filter((column) => column.width != null).map((column) => [column.key, column.width])),
                ...(baseColumns.widths && typeof baseColumns.widths === 'object' ? baseColumns.widths : {}),
            },
            meta: baseColumns.meta && typeof baseColumns.meta === 'object' ? baseColumns.meta : {},
        },
    };
}

function destroyEditorTree(root) {
    if (!root?.querySelectorAll) {
        return;
    }

    Array.from(root.querySelectorAll('.html-grid-cell-editor-slot')).forEach((slot) => {
        slot.__htmlGridEditor?.destroy?.();
        delete slot.__htmlGridEditor;
    });
}

function applyColumnWidths(host, columns = [], columnState = {}) {
    if (!host?.querySelectorAll) {
        return;
    }

    const widths = columnState.widths && typeof columnState.widths === 'object' ? columnState.widths : {};
    const visibleColumns = Array.isArray(columns) ? columns : [];

    visibleColumns.forEach((column) => {
        const width = widths[column.key] ?? column.width ?? null;
        if (width == null) {
            return;
        }

        const selector = `[data-column-key="${String(column.key)}"]`;
        Array.from(host.querySelectorAll(selector)).forEach((element) => {
            if (element?.style) {
                element.style.width = `${width}px`;
                element.style.minWidth = `${width}px`;
                element.style.maxWidth = `${width}px`;
            }
        });
    });
}

function createCommandContext(dependencies = {}) {
    return {
        state: dependencies.state,
        schema: dependencies.schema,
        eventBus: dependencies.eventBus,
        rowNumberField: dependencies.rowNumberField || '',
        createRow: dependencies.hooks?.createRow || null,
        normalizeRow: dependencies.hooks?.normalizeRow || null,
        getDefaultColumnKey: dependencies.hooks?.getDefaultColumnKey || null,
        defaultColumnKey: dependencies.defaultColumnKey || '',
        getVisibleColumns: () => dependencies.columnManager.getVisibleColumns(),
        runValidation(payload = {}) {
            return dependencies.validator.validate({
                ...payload,
                state: dependencies.state,
            });
        },
        runFooter(payload = {}) {
            return dependencies.footer.compute({
                ...payload,
                state: dependencies.state,
            });
        },
        hooks: dependencies.hooks || {},
    };
}

export function createHtmlGrid(config = {}) {
    const documentRef = resolveDocument(config);
    const builtinFormatters = createBuiltinFormatterMap(config.formatters);
    const builtinEditors = createBuiltinEditorMap(config.editors);
    const builtinPlugins = createBuiltinPluginMap(config.plugins);
    const schemaInput = Array.isArray(config.columns)
        ? config.columns
        : (Array.isArray(config.schema?.columns) ? config.schema.columns : (Array.isArray(config.schema) ? config.schema : []));
    const schema = createRuntimeSchema(schemaInput, {
        formatters: builtinFormatters,
        editors: builtinEditors,
        plugins: builtinPlugins,
    });
    const state = createInitialGridState(buildInitialStateConfig(config, schema));
    const columnManager = createColumnManager({ schema, state });
    const eventBus = createGridEventBus();
    const formatterRegistry = createFormatterRegistry(builtinFormatters);
    const editorRegistry = createEditorRegistry();
    Object.entries(builtinEditors).forEach(([name, factory]) => {
        editorRegistry.register(name, factory);
    });

    const pluginRegistry = createPluginRegistry();
    Object.entries(builtinPlugins).forEach(([name, factory]) => {
        pluginRegistry.register(name, factory);
    });

    const renderer = createGridRenderer({
        document: documentRef,
        columnManager,
        editorRegistry,
        formatterRegistry,
    });
    const validator = createGridValidator({
        schema,
        hooks: config.hooks?.validator || {},
    });
    const footer = createGridFooter({
        definitions: config.footerDefinitions || [],
        hooks: config.hooks?.footer || {},
    });
    const serializer = createGridSerializer({
        version: Number.isInteger(config.version) ? config.version : 1,
        schema,
        hooks: config.hooks?.serializer || {},
    });

    const commandContext = createCommandContext({
        state,
        schema,
        eventBus,
        columnManager,
        validator,
        footer,
        hooks: config.hooks || {},
        rowNumberField: config.rowNumberField || config.hooks?.rowNumberField || '',
        defaultColumnKey: config.defaultColumnKey || schema.columnKeys[0] || '',
    });

    state.validation = validator.validate({ state, reason: 'boot' });
    state.footer = footer.compute({ state, reason: 'boot' });

    const selection = createSelectionController({
        api: {
            getRows: () => state.rows,
            getState: () => state,
            setState(nextState) {
                state.selection = nextState.selection || state.selection;
                return state;
            },
            getCapabilities: () => state.capabilities,
        },
        commandContext,
        getVisibleColumns: () => columnManager.getVisibleColumns(),
    });

    let rootHost = config.host || null;
    let renderedStructure = null;
    let destroyed = false;
    let keyboardUnbind = null;
    const deferredRowRenderIds = new Set();
    let deferredRowRenderQueued = false;
    let keyboard = null;

    function syncColumnStateToState() {
        state.columns = {
            ...state.columns,
            ...columnManager.getState(),
        };
    }

    function getPluginContext() {
        return {
            adapters: config.adapters || {},
            window: typeof window !== 'undefined' ? window : null,
            grid: publicApi,
            state: cloneValue(state),
        };
    }

    function cleanupRoot(target) {
        if (!target) {
            return;
        }

        pluginRegistry.destroy(target, getPluginContext());
        destroyEditorTree(target);
    }

    function renderFull(options = {}) {
        if (!rootHost) {
            return null;
        }

        syncColumnStateToState();
        cleanupRoot(rootHost);
        renderedStructure = renderer.render(rootHost, state, options);
        applyColumnWidths(rootHost, columnManager.getVisibleColumns(), columnManager.getState());
        pluginRegistry.mount(rootHost, getPluginContext());
        pluginRegistry.update(rootHost, getPluginContext());
        return renderedStructure;
    }

    function syncStructureVisibility(options = {}) {
        if (!renderedStructure?.table || !renderedStructure?.empty) {
            return;
        }

        const hasRows = Array.isArray(state.rows) && state.rows.length > 0;
        const isLoading = Boolean(state.ui?.loading);
        const hasError = Boolean(options.error);

        renderedStructure.table.classList.toggle('is-hidden', !hasRows && !isLoading && !hasError);
        renderedStructure.empty.classList.toggle('is-hidden', hasRows && !isLoading && !hasError);
    }

    function renderBodyOnly() {
        if (!renderedStructure?.tbody) {
            return renderFull();
        }

        syncColumnStateToState();
        cleanupRoot(renderedStructure.tbody);
        renderer.renderBody(renderedStructure.tbody, state, {});
        applyColumnWidths(rootHost, columnManager.getVisibleColumns(), columnManager.getState());
        pluginRegistry.mount(renderedStructure.tbody, getPluginContext());
        pluginRegistry.update(renderedStructure.tbody, getPluginContext());
        syncStructureVisibility();
        return renderedStructure.tbody;
    }

    function renderFooterOnly() {
        if (!renderedStructure?.tfoot) {
            return renderFull();
        }

        syncColumnStateToState();
        renderer.renderFooter(renderedStructure.tfoot, state, {
            showFooter: state.capabilities?.footer !== false,
        });
        applyColumnWidths(rootHost, columnManager.getVisibleColumns(), columnManager.getState());
        return renderedStructure.tfoot;
    }

    function renderHeaderOnly() {
        if (!renderedStructure?.thead) {
            return renderFull();
        }

        syncColumnStateToState();
        renderer.renderHeader(renderedStructure.thead, state, {
            showResizeHandle: true,
            showSortUi: false,
        });
        applyColumnWidths(rootHost, columnManager.getVisibleColumns(), columnManager.getState());
        return renderedStructure.thead;
    }

    function updateRowPartial(rowId) {
        if (!renderedStructure?.tbody) {
            return renderFull();
        }

        syncColumnStateToState();
        const currentRow = renderedStructure.tbody.querySelector?.(`[data-row-id="${String(rowId)}"]`) || null;
        if (currentRow) {
            cleanupRoot(currentRow);
        }
        const nextRow = renderer.updateRow(renderedStructure.tbody, rowId, state);
        if (nextRow) {
            pluginRegistry.mount(nextRow, getPluginContext());
            pluginRegistry.update(nextRow, getPluginContext());
        }
        applyColumnWidths(rootHost, columnManager.getVisibleColumns(), columnManager.getState());
        return nextRow;
    }

    function flushDeferredRowRenders() {
        deferredRowRenderQueued = false;
        const rowIds = Array.from(deferredRowRenderIds);
        deferredRowRenderIds.clear();
        rowIds.forEach((rowId) => {
            updateRowPartial(rowId);
        });
    }

    function deferRowPartial(rowId) {
        const normalizedRowId = String(rowId || '');
        if (!normalizedRowId) {
            return;
        }

        deferredRowRenderIds.add(normalizedRowId);
        if (deferredRowRenderQueued) {
            return;
        }

        deferredRowRenderQueued = true;
        queueMicrotask(flushDeferredRowRenders);
    }

    function updateCellPartial(rowId, columnKey) {
        if (!renderedStructure?.tbody) {
            return renderFull();
        }

        syncColumnStateToState();
        const currentCell = renderedStructure.tbody.querySelector?.(`[data-row-id="${String(rowId)}"][data-column-key="${String(columnKey)}"]`) || null;
        if (currentCell) {
            cleanupRoot(currentCell);
        }
        const nextCell = renderer.updateCell(renderedStructure.tbody, rowId, columnKey, state);
        if (nextCell) {
            pluginRegistry.mount(nextCell, getPluginContext());
            pluginRegistry.update(nextCell, getPluginContext());
        }
        applyColumnWidths(rootHost, columnManager.getVisibleColumns(), columnManager.getState());
        return nextCell;
    }

    function removeRowPartial(rowId) {
        if (!renderedStructure?.tbody) {
            return renderFull();
        }

        const currentRow = renderedStructure.tbody.querySelector?.(`[data-row-id="${String(rowId)}"]`) || null;
        if (currentRow) {
            cleanupRoot(currentRow);
        }
        const removedRow = renderer.removeRow(renderedStructure.tbody, rowId);
        syncStructureVisibility();
        return removedRow;
    }

    function executeCommand(command) {
        const result = command?.execute?.(commandContext) || { executed: false };
        return result;
    }

    const resize = createResizeController({
        columnManager,
        eventBus,
    });

    const reorder = createReorderController({
        commandContext,
        executeCommand,
    });

    function validateState() {
        state.validation = validator.validate({ state, reason: 'api-validate' });
        eventBus.emit('validation:changed', { validation: cloneValue(state.validation) });
        return cloneValue(state.validation);
    }

    function serializeState() {
        return serializer.serialize({ state, reason: 'api-serialize' });
    }

    const api = createGridApi({
        state,
        eventBus,
        handlers: {
            addRow: ({ row }) => executeCommand(createAddRowCommand({ row })),
            insertRow: ({ rowIndex, row }) => executeCommand(createInsertRowCommand({ rowIndex, row })),
            deleteRow: ({ rowIndex }) => executeCommand(createDeleteRowCommand({ rowIndex })),
            updateRow: ({ rowIndex, patch, options }) => executeCommand(createUpdateRowCommand({ rowIndex, patch, options })),
            updateCell: ({ rowIndex, columnKey, value }) => executeCommand(createUpdateCellCommand({ rowIndex, columnKey, value })),
            focusCell: ({ rowIndex, columnKey }) => executeCommand(createMoveCellCommand({ rowIndex, columnKey })),
            selectRow: ({ rowIndex }) => selection.selectRow(rowIndex),
            clearSelection: () => selection.clearSelection(),
            validate: () => validateState(),
            render: ({ options }) => renderFull(options),
            refresh: ({ options }) => {
                if (options?.scope === 'header') {
                    return renderHeaderOnly();
                }
                if (options?.scope === 'body') {
                    return renderBodyOnly();
                }
                if (options?.scope === 'footer') {
                    return renderFooterOnly();
                }
                return renderFull(options);
            },
            beginEdit: ({ rowIndex, columnKey }) => {
                state.ui.editing = { rowIndex, columnKey };
                renderBodyOnly();
                return { executed: true, rowIndex, columnKey };
            },
            endEdit: ({ options }) => {
                state.ui.editing = null;
                if (!options?.skipRender) {
                    renderBodyOnly();
                }
                return { executed: true, ...(options || {}) };
            },
            execute: ({ command }) => executeCommand(command),
            destroy: () => {
                publicApi.destroy();
                return { executed: true };
            },
        },
    });

    const keyboardApi = {
        ...api,
        insertRow: (rowIndex, row) => api.insertRow(rowIndex, row),
        deleteRow: (rowIndex) => api.deleteRow(rowIndex),
        beginEdit: (rowIndex, columnKey) => api.beginEdit(rowIndex, columnKey),
        endEdit: (options) => api.endEdit(options),
    };
    keyboard = createKeyboardController({
        api: keyboardApi,
        selection,
        getVisibleColumns: () => columnManager.getVisibleColumns(),
        onCopy: config.hooks?.onCopy || null,
        onPaste: config.hooks?.onPaste || null,
        onSelectAll: config.hooks?.onSelectAll || null,
    });

    function bindKeyboard() {
        if (!rootHost?.addEventListener || keyboardUnbind) {
            return;
        }

        const handler = (event) => {
            if (state.capabilities?.keyboard === false) {
                return;
            }
            keyboard.handleKeyDown(event);
        };
        rootHost.addEventListener('keydown', handler);
        keyboardUnbind = () => {
            rootHost?.removeEventListener?.('keydown', handler);
            keyboardUnbind = null;
        };
    }

    eventBus.on('row:added', () => {
        renderBodyOnly();
        renderFooterOnly();
    });
    eventBus.on('row:inserted', () => {
        renderBodyOnly();
        renderFooterOnly();
    });
    eventBus.on('row:moved', () => {
        renderBodyOnly();
        renderFooterOnly();
    });
    eventBus.on('row:updated', ({ row, deferRowRender }) => {
        if (deferRowRender) {
            deferRowPartial(row?.rowId || '');
        } else {
            updateRowPartial(row?.rowId || '');
        }
        renderFooterOnly();
    });
    eventBus.on('row:deleted', ({ row, mode }) => {
        if (mode === 'removed') {
            removeRowPartial(row?.rowId || '');
        } else {
            updateRowPartial(row?.rowId || '');
        }
        renderFooterOnly();
    });
    eventBus.on('cell:changed', ({ row, columnKey }) => {
        updateCellPartial(row?.rowId || '', columnKey || '');
    });
    eventBus.on('validation:changed', () => {
        // Validation updates must not recreate active editors; row/cell events own body rendering.
        renderFooterOnly();
    });
    eventBus.on('footer:changed', () => {
        renderFooterOnly();
    });
    eventBus.on('column:resized', () => {
        syncColumnStateToState();
        applyColumnWidths(rootHost, columnManager.getVisibleColumns(), columnManager.getState());
    });

    function destroy() {
        if (destroyed) {
            return { executed: true };
        }

        keyboardUnbind?.();
        cleanupRoot(rootHost);
        eventBus.clear();
        if (rootHost) {
            rootHost.textContent = '';
        }
        renderedStructure = null;
        destroyed = true;
        return { executed: true };
    }

    const publicApi = {
        addRow(row) {
            return api.addRow(row);
        },
        insertRow(rowIndex, row) {
            return api.insertRow(rowIndex, row);
        },
        deleteRow(rowIndex) {
            return api.deleteRow(rowIndex);
        },
        updateRow(rowIndex, patch, options = {}) {
            return api.updateRow(rowIndex, patch, options);
        },
        updateCell(rowIndex, columnKey, value) {
            return api.updateCell(rowIndex, columnKey, value);
        },
        render(options = {}) {
            if (options.host) {
                rootHost = options.host;
            }
            bindKeyboard();
            return api.render(options);
        },
        refresh(options = {}) {
            return api.refresh(options);
        },
        validate() {
            return api.validate();
        },
        serialize() {
            return serializeState();
        },
        destroy,
        on(eventName, handler) {
            return api.on(eventName, handler);
        },
        off(eventName, handler) {
            return api.off(eventName, handler);
        },
        execute(command) {
            return api.execute(command);
        },
        getState() {
            syncColumnStateToState();
            return api.getState();
        },
        setState(nextState) {
            api.setState(nextState);
            syncColumnStateToState();
            state.validation = validator.validate({ state, reason: 'set-state' });
            state.footer = footer.compute({ state, reason: 'set-state' });
            renderFull();
            return api.getState();
        },
        getColumnState() {
            return columnManager.getState();
        },
        setColumnState(nextState) {
            columnManager.replaceState(nextState);
            syncColumnStateToState();
            renderHeaderOnly();
            renderBodyOnly();
            renderFooterOnly();
            return columnManager.getState();
        },
        focusCell(rowIndex, columnKey) {
            return api.focusCell(rowIndex, columnKey);
        },
        selectRow(rowIndex, options = {}) {
            return selection.selectRow(rowIndex, options);
        },
        beginEdit(rowIndex, columnKey) {
            return api.beginEdit(rowIndex, columnKey);
        },
        endEdit(options = {}) {
            return api.endEdit(options);
        },
        resizeColumn(columnKey, width) {
            resize.beginResize(columnKey);
            return resize.endResize(columnKey, width);
        },
        reorderRow(fromIndex, toIndex) {
            const dragResult = reorder.beginDrag({ handle: 'drag-handle', rowIndex: fromIndex });
            if (!dragResult.executed) {
                return dragResult;
            }
            return reorder.drop({ rowIndex: toIndex });
        },
    };

    if (rootHost) {
        bindKeyboard();
    }

    return publicApi;
}
