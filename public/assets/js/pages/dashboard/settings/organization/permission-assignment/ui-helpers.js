export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

export function notify(type, message) {
    window.AppCore?.notify?.(type, message);
}

export function buildStatusBadge(value) {
    return String(value) === '1'
        ? '<span class="badge bg-success">사용</span>'
        : '<span class="badge bg-secondary">미사용</span>';
}

export function responseRows(response) {
    return Array.isArray(response?.data) ? response.data : [];
}
