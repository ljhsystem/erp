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

export function buildPermissionSearchText(pageNode, childNode = null) {
    const values = childNode
        ? [pageNode.page, pageNode.category, pageNode.permission_name, pageNode.description,
            String(childNode.permission_source || '').toUpperCase(), childNode.permission_name,
            childNode.description, childNode.permission_source]
        : [pageNode.page, pageNode.category, pageNode.permission_name, pageNode.description,
            ...(pageNode.children || []).flatMap((node) => [node.permission_name, node.description, node.permission_source])];
    return values.map((value) => String(value || '').trim()).filter(Boolean).join(' ');
}

export function getPermissionSourcePriority(source = '') {
    const normalized = String(source || '').trim().toLowerCase();
    if (normalized === 'web') return 0;
    if (normalized === 'api') return 1;
    return 9;
}

export function sortPermissionChildrenForDisplay(children = []) {
    return [...children].sort((left, right) => {
        const sourceCompare = getPermissionSourcePriority(left?.permission_source) - getPermissionSourcePriority(right?.permission_source);
        if (sourceCompare !== 0) return sourceCompare;
        const sortCompare = Number(left?.sort_no || 0) - Number(right?.sort_no || 0);
        if (sortCompare !== 0) return sortCompare;
        return String(left?.permission_name || '').localeCompare(String(right?.permission_name || ''), 'ko');
    });
}
