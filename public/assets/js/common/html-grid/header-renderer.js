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
    throw new Error('[html-grid] header-renderer requires document context.');
}

function createHeaderCell(documentRef, column, options = {}) {
    const th = documentRef.createElement('th');
    th.className = 'html-grid-header-cell';
    th.dataset.columnKey = column.key;
    th.scope = 'col';

    const content = documentRef.createElement('div');
    content.className = 'html-grid-header-cell-content';

    const label = documentRef.createElement('span');
    label.className = 'html-grid-header-label';
    label.textContent = column.label;
    content.appendChild(label);

    if (options.showSortUi === true) {
        const sortSlot = documentRef.createElement('span');
        sortSlot.className = 'html-grid-header-sort-slot';
        sortSlot.setAttribute('aria-hidden', 'true');
        content.appendChild(sortSlot);
    }

    if (column.required || column.meta?.requiredIndicator === true) {
        const requiredMark = documentRef.createElement('span');
        requiredMark.className = 'html-grid-header-required';
        requiredMark.textContent = '*';
        content.appendChild(requiredMark);
    }

    th.appendChild(content);

    if (options.showResizeHandle !== false) {
        th.classList.add('has-resize-handle');
        const resizeHandle = documentRef.createElement('button');
        resizeHandle.type = 'button';
        resizeHandle.className = 'html-grid-header-resize-handle';
        resizeHandle.dataset.columnKey = column.key;
        resizeHandle.setAttribute('aria-label', `${column.label} 너비 조절`);
        th.appendChild(resizeHandle);
    }

    return th;
}

export function createHeaderRenderer(config = {}) {
    const documentRef = config.document || (typeof document !== 'undefined' ? document : null);

    function renderHeader(target, columns = [], options = {}) {
        const resolvedDocument = resolveDocument(target, documentRef);
        const thead = target.tagName === 'THEAD' ? target : resolvedDocument.createElement('thead');
        thead.className = 'html-grid-header';
        thead.textContent = '';

        const row = resolvedDocument.createElement('tr');
        row.className = 'html-grid-header-row';

        columns.forEach((column) => {
            row.appendChild(createHeaderCell(resolvedDocument, column, options));
        });

        thead.appendChild(row);
        return thead;
    }

    return {
        renderHeader,
    };
}
