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
    throw new Error('[html-grid] footer-renderer requires document context.');
}

function createFooterSummaryCell(documentRef, column, value = '') {
    const td = documentRef.createElement('td');
    td.className = 'html-grid-footer-cell';
    td.dataset.columnKey = column.key;

    const content = documentRef.createElement('div');
    content.className = 'html-grid-footer-cell-content';
    content.textContent = value == null ? '' : String(value);
    td.appendChild(content);

    return td;
}

export function createFooterRenderer(config = {}) {
    const documentRef = config.document || (typeof document !== 'undefined' ? document : null);

    function renderFooter(target, columns = [], footerState = {}, options = {}) {
        const resolvedDocument = resolveDocument(target, documentRef);
        const tfoot = target.tagName === 'TFOOT' ? target : resolvedDocument.createElement('tfoot');
        tfoot.className = 'html-grid-footer';
        tfoot.textContent = '';

        if (options.showFooter === false) {
            return tfoot;
        }

        const summaryRow = resolvedDocument.createElement('tr');
        summaryRow.className = 'html-grid-footer-row';

        columns.forEach((column, index) => {
            const summaryValue = footerState.values?.[column.key] ?? (index === 0 ? footerState.label || '' : '');
            summaryRow.appendChild(createFooterSummaryCell(resolvedDocument, column, summaryValue));
        });

        tfoot.appendChild(summaryRow);

        const messages = Array.isArray(footerState.messages) ? footerState.messages.filter(Boolean) : [];
        if (messages.length > 0) {
            const validationRow = resolvedDocument.createElement('tr');
            validationRow.className = 'html-grid-footer-validation-row';

            const validationCell = resolvedDocument.createElement('td');
            validationCell.className = 'html-grid-footer-validation-cell';
            validationCell.colSpan = Math.max(columns.length, 1);
            validationCell.textContent = messages.join(' | ');
            validationRow.appendChild(validationCell);
            tfoot.appendChild(validationRow);
        }

        return tfoot;
    }

    return {
        renderFooter,
    };
}
