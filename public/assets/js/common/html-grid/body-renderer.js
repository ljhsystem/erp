function resolveDocument(target, fallbackDocument = null) {
    if (target?.ownerDocument) {
        return target.ownerDocument;
    }
    if (fallbackDocument) {
        return fallbackDocument;
    }
    if (typeof document !== 'undefined') {
        return document;
    }
    throw new Error('[html-grid] body-renderer requires document context.');
}

function normalizeCellState(cellState = {}) {
    const values = Array.isArray(cellState?.state) ? cellState.state : ['normal'];
    return values.map((value) => String(value || '').trim()).filter(Boolean);
}

function resolveFormatter(formatterRegistry, formatterName) {
    if (!formatterName) {
        return null;
    }

    if (formatterRegistry && typeof formatterRegistry.resolve === 'function') {
        return formatterRegistry.resolve(formatterName) || null;
    }

    if (formatterRegistry && typeof formatterRegistry === 'object') {
        return typeof formatterRegistry[formatterName] === 'function' ? formatterRegistry[formatterName] : null;
    }

    return null;
}

function resolveEditorSlot(editorRegistry, context = {}) {
    if (!editorRegistry || typeof editorRegistry.create !== 'function') {
        return null;
    }

    return editorRegistry.create(context) || null;
}

function normalizePluginNames(plugins = []) {
    return (Array.isArray(plugins) ? plugins : [plugins])
        .map((plugin) => String(plugin || '').trim())
        .filter((plugin, index, array) => plugin !== '' && array.indexOf(plugin) === index);
}

function buildCellClassNames(column, row, cellStates = []) {
    const classNames = ['html-grid-cell'];

    if (column.editor) {
        classNames.push('has-editor');
    }

    if (column.type) {
        classNames.push(`html-grid-cell-type-${column.type}`);
    }

    classNames.push(`html-grid-column-${column.key}`);
    classNames.push(`html-grid-row-state-${row.rowState}`);

    cellStates.forEach((stateName) => {
        classNames.push(`is-${stateName}`);
    });

    return classNames.join(' ');
}

function renderCellValue(cell, value) {
    cell.textContent = value == null ? '' : String(value);
}

function createBodyCell(documentRef, row, column, options = {}) {
    const cell = documentRef.createElement('td');
    cell.dataset.rowId = row.rowId;
    cell.dataset.columnKey = column.key;

    const cellState = options.cellState || {};
    const cellStates = normalizeCellState(cellState);
    cell.className = buildCellClassNames(column, row, cellStates);

    const value = row.values?.[column.key] ?? '';
    const formatter = resolveFormatter(options.formatterRegistry, column.formatter);
    const displayValue = formatter
        ? formatter(value, {
            ...(column.meta?.formatterOptions || {}),
            row,
            column,
            cellState,
        })
        : value;

    const cellContent = documentRef.createElement('div');
    cellContent.className = 'html-grid-cell-content';

    const valueSlot = documentRef.createElement('span');
    valueSlot.className = 'html-grid-cell-value';
    if (column.type === 'selection') {
        const checkbox = documentRef.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'form-check-input html-grid-row-selection';
        checkbox.dataset.rowId = row.rowId;
        checkbox.checked = (options.selectedRowIds || []).includes(row.rowId);
        checkbox.disabled = row.values?.selection_disabled === true;
        checkbox.setAttribute('aria-label', column.label || '행 선택');
        valueSlot.appendChild(checkbox);
    } else {
        renderCellValue(valueSlot, displayValue);
        if (displayValue != null && String(displayValue).trim() !== '') {
            valueSlot.title = String(displayValue);
        }
    }
    cellContent.appendChild(valueSlot);

    const shouldRenderEditorSlot = cellStates.includes('editing') || column.editor;
    if (shouldRenderEditorSlot) {
        const editorSlot = documentRef.createElement('div');
        editorSlot.className = 'html-grid-cell-editor-slot';
        editorSlot.dataset.editor = String(column.editor || '');
        editorSlot.dataset.rowId = row.rowId;
        editorSlot.dataset.columnKey = column.key;
        editorSlot.dataset.plugins = normalizePluginNames(column.plugins || []).join(',');

        const editorInstance = resolveEditorSlot(options.editorRegistry, {
            document: documentRef,
            host: editorSlot,
            row,
            column,
            cellState,
            value,
            options: column.meta?.editorOptions || {},
        });

        if (editorInstance) {
            editorSlot.__htmlGridEditor = editorInstance;
            editorInstance.create?.();
            if (typeof editorInstance.mount === 'function') {
                editorInstance.mount(editorSlot);
            } else if (editorInstance.element) {
                editorSlot.appendChild(editorInstance.element);
            }
        }

        cellContent.appendChild(editorSlot);
    }

    if (cellState?.message) {
        const messageSlot = documentRef.createElement('div');
        messageSlot.className = 'html-grid-cell-message';
        messageSlot.textContent = String(cellState.message);
        cellContent.appendChild(messageSlot);
    }

    cell.appendChild(cellContent);
    return cell;
}

function createRowElement(documentRef, row, columns, options = {}) {
    const tr = documentRef.createElement('tr');
    tr.className = `html-grid-body-row html-grid-row-state-${row.rowState}`;
    tr.dataset.rowId = row.rowId;
    if ((options.selectedRowIds || []).includes(row.rowId)) {
        tr.classList.add('is-selected');
        tr.setAttribute('aria-selected', 'true');
    }

    columns.forEach((column) => {
        const rowCellState = options.cells?.[row.rowId]?.[column.key] || null;
        tr.appendChild(createBodyCell(documentRef, row, column, {
            ...options,
            cellState: rowCellState,
        }));
    });

    return tr;
}

export function createBodyRenderer(config = {}) {
    const documentRef = config.document || (typeof document !== 'undefined' ? document : null);

    function renderBody(target, rows = [], columns = [], options = {}) {
        const resolvedDocument = resolveDocument(target, documentRef);
        const tbody = target.tagName === 'TBODY' ? target : resolvedDocument.createElement('tbody');
        tbody.className = 'html-grid-body';
        tbody.textContent = '';

        rows.forEach((row) => {
            tbody.appendChild(createRowElement(resolvedDocument, row, columns, options));
        });

        return tbody;
    }

    function appendRow(target, row, columns = [], options = {}) {
        const resolvedDocument = resolveDocument(target, documentRef);
        const rowElement = createRowElement(resolvedDocument, row, columns, options);
        target.appendChild(rowElement);
        return rowElement;
    }

    function removeRow(target, rowId) {
        const rowElement = target.querySelector(`[data-row-id="${String(rowId)}"]`);
        rowElement?.remove();
        return rowElement || null;
    }

    function renderRow(target, row, columns = [], options = {}) {
        const resolvedDocument = resolveDocument(target, documentRef);
        return createRowElement(resolvedDocument, row, columns, options);
    }

    function updateRow(target, row, columns = [], options = {}) {
        const nextRow = renderRow(target, row, columns, options);
        const currentRow = target.querySelector(`[data-row-id="${String(row.rowId)}"]`);
        if (currentRow?.replaceWith) {
            currentRow.replaceWith(nextRow);
        } else {
            currentRow?.parentNode?.insertBefore(nextRow, currentRow);
            currentRow?.remove?.();
        }
        return nextRow;
    }

    function renderCell(target, row, column, options = {}) {
        const resolvedDocument = resolveDocument(target, documentRef);
        const rowCellState = options.cells?.[row.rowId]?.[column.key] || null;
        return createBodyCell(resolvedDocument, row, column, {
            ...options,
            cellState: rowCellState,
        });
    }

    function updateCell(target, row, column, options = {}) {
        const nextCell = renderCell(target, row, column, options);
        const currentCell = target.querySelector(`[data-row-id="${String(row.rowId)}"][data-column-key="${String(column.key)}"]`);
        if (currentCell?.replaceWith) {
            currentCell.replaceWith(nextCell);
        } else {
            currentCell?.parentNode?.insertBefore(nextCell, currentCell);
            currentCell?.remove?.();
        }
        return nextCell;
    }

    return {
        renderBody,
        renderRow,
        renderCell,
        appendRow,
        removeRow,
        updateRow,
        updateCell,
    };
}
