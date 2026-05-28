function defaultEscapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

export function manageButtonRenderer(row = {}, options = {}) {
    const escape = typeof options.escapeHtml === 'function'
        ? options.escapeHtml
        : defaultEscapeHtml;
    const id = escape(row?.id ?? '');
    return `
        <button type="button"
                class="btn btn-outline-primary btn-sm seed-row-edit-btn"
                data-id="${id}">
            \\uC218\\uC815
        </button>
    `;
}

export const manageButton = manageButtonRenderer;
