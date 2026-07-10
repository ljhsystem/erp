import { createHeaderRenderer } from './header-renderer.js';
import { createBodyRenderer } from './body-renderer.js';
import { createFooterRenderer } from './footer-renderer.js';
import { createEmptyRenderer } from './empty-renderer.js';

function resolveDocument(host, fallbackDocument = null) {
    if (host?.ownerDocument) {
        return host.ownerDocument;
    }
    if (fallbackDocument) {
        return fallbackDocument;
    }
    if (typeof document !== 'undefined') {
        return document;
    }
    throw new Error('[html-grid] renderer requires document context.');
}

function ensureTableStructure(host, documentRef) {
    let table = host.querySelector('.html-grid-table');
    if (!table) {
        table = documentRef.createElement('table');
        table.className = 'html-grid-table';
        host.appendChild(table);
    }

    let thead = table.querySelector('thead');
    if (!thead) {
        thead = documentRef.createElement('thead');
        table.appendChild(thead);
    }

    let tbody = table.querySelector('tbody');
    if (!tbody) {
        tbody = documentRef.createElement('tbody');
        table.appendChild(tbody);
    }

    let tfoot = table.querySelector('tfoot');
    if (!tfoot) {
        tfoot = documentRef.createElement('tfoot');
        table.appendChild(tfoot);
    }

    let empty = host.querySelector('.html-grid-empty');
    if (!empty) {
        empty = documentRef.createElement('div');
        empty.className = 'html-grid-empty';
        host.appendChild(empty);
    }

    return {
        table,
        thead,
        tbody,
        tfoot,
        empty,
    };
}

function buildRendererContext(config = {}, state) {
    const columns = typeof config.columnManager?.getVisibleColumns === 'function'
        ? config.columnManager.getVisibleColumns()
        : [];

    return {
        columns,
        rows: Array.isArray(state?.rows) ? state.rows : [],
        cells: state?.cells || {},
        footer: state?.footer || {},
        ui: state?.ui || {},
    };
}

export function createGridRenderer(config = {}) {
    const documentRef = config.document || (typeof document !== 'undefined' ? document : null);
    const headerRenderer = createHeaderRenderer({ document: documentRef });
    const bodyRenderer = createBodyRenderer({
        document: documentRef,
    });
    const footerRenderer = createFooterRenderer({ document: documentRef });
    const emptyRenderer = createEmptyRenderer({ document: documentRef });

    function render(host, state, options = {}) {
        const resolvedDocument = resolveDocument(host, documentRef);
        const structure = ensureTableStructure(host, resolvedDocument);
        const context = buildRendererContext(config, state);
        const hasRows = context.rows.length > 0;

        headerRenderer.renderHeader(structure.thead, context.columns, {
            showResizeHandle: options.showResizeHandle !== false,
            showSortUi: options.showSortUi === true,
        });

        bodyRenderer.renderBody(structure.tbody, context.rows, context.columns, {
            cells: context.cells,
            editorRegistry: config.editorRegistry || null,
            formatterRegistry: config.formatterRegistry || null,
        });

        footerRenderer.renderFooter(structure.tfoot, context.columns, context.footer, {
            showFooter: state?.capabilities?.footer !== false,
        });

        const emptyNode = emptyRenderer.renderEmpty(structure.empty, {
            loading: Boolean(context.ui.loading),
            error: Boolean(options.error),
            noData: !context.ui.loading && !hasRows,
            emptyMessage: options.emptyMessage,
            noDataMessage: options.noDataMessage,
            loadingMessage: options.loadingMessage,
            errorMessage: options.errorMessage,
        });

        structure.table.classList.toggle('is-hidden', !hasRows && !context.ui.loading && !options.error);
        emptyNode.classList.toggle('is-hidden', hasRows && !context.ui.loading && !options.error);

        return structure;
    }

    function renderHeader(host, state, options = {}) {
        const context = buildRendererContext(config, state);
        return headerRenderer.renderHeader(host, context.columns, options);
    }

    function renderBody(host, state, options = {}) {
        const context = buildRendererContext(config, state);
        return bodyRenderer.renderBody(host, context.rows, context.columns, {
            ...options,
            cells: context.cells,
            editorRegistry: config.editorRegistry || null,
            formatterRegistry: config.formatterRegistry || null,
        });
    }

    function renderFooter(host, state, options = {}) {
        const context = buildRendererContext(config, state);
        return footerRenderer.renderFooter(host, context.columns, context.footer, options);
    }

    function renderRow(host, rowId, state) {
        const context = buildRendererContext(config, state);
        const row = context.rows.find((entry) => String(entry.rowId) === String(rowId));
        if (!row) {
            return null;
        }
        return bodyRenderer.renderRow(host, row, context.columns, {
            cells: context.cells,
            editorRegistry: config.editorRegistry || null,
            formatterRegistry: config.formatterRegistry || null,
        });
    }

    function renderCell(host, rowId, columnKey, state) {
        const context = buildRendererContext(config, state);
        const row = context.rows.find((entry) => String(entry.rowId) === String(rowId));
        const column = context.columns.find((entry) => String(entry.key) === String(columnKey));
        if (!row || !column) {
            return null;
        }
        return bodyRenderer.renderCell(host, row, column, {
            cells: context.cells,
            editorRegistry: config.editorRegistry || null,
            formatterRegistry: config.formatterRegistry || null,
        });
    }

    function appendRow(host, row, state) {
        const context = buildRendererContext(config, state);
        return bodyRenderer.appendRow(host, row, context.columns, {
            cells: context.cells,
            editorRegistry: config.editorRegistry || null,
            formatterRegistry: config.formatterRegistry || null,
        });
    }

    function removeRow(host, rowId) {
        return bodyRenderer.removeRow(host, rowId);
    }

    function updateRow(host, rowId, state) {
        const context = buildRendererContext(config, state);
        const row = context.rows.find((entry) => String(entry.rowId) === String(rowId));
        if (!row) {
            return null;
        }
        return bodyRenderer.updateRow(host, row, context.columns, {
            cells: context.cells,
            editorRegistry: config.editorRegistry || null,
            formatterRegistry: config.formatterRegistry || null,
        });
    }

    function updateCell(host, rowId, columnKey, state) {
        const context = buildRendererContext(config, state);
        const row = context.rows.find((entry) => String(entry.rowId) === String(rowId));
        const column = context.columns.find((entry) => String(entry.key) === String(columnKey));
        if (!row || !column) {
            return null;
        }
        return bodyRenderer.updateCell(host, row, column, {
            cells: context.cells,
            editorRegistry: config.editorRegistry || null,
            formatterRegistry: config.formatterRegistry || null,
        });
    }

    return {
        render,
        renderHeader,
        renderBody,
        renderFooter,
        renderRow,
        renderCell,
        removeRow,
        appendRow,
        updateRow,
        updateCell,
    };
}
