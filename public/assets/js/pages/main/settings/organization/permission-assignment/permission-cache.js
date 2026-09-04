export function mergePermissionSelection(masterTree, selection = {}) {
    const mappingByPermissionId = new Map(
        (Array.isArray(selection?.mappings) ? selection.mappings : []).map(mapping => [
            String(mapping.permission_id || ''),
            mapping,
        ])
    );
    return masterTree.map(pageNode => {
        const children = (pageNode.children || []).map(permission => {
            const mapping = mappingByPermissionId.get(String(permission.permission_id || ''));
            return {
                ...permission,
                checked: Boolean(mapping),
                role_id: String(selection?.role_id || ''),
                role_permission_id: String(mapping?.mapping_id || ''),
                role_permission_created_at: mapping?.created_at || '',
                role_permission_created_by: mapping?.created_by || '',
            };
        });
        const checkedCount = children.filter(permission => permission.checked).length;
        return {
            ...pageNode,
            children,
            checked: children.length > 0 && checkedCount === children.length,
            indeterminate: checkedCount > 0 && checkedCount < children.length,
        };
    });
}

export function revealDataTable(table, cardId, bodyId) {
    table.columns.adjust();
    window.requestAnimationFrame(() => {
        document.getElementById(bodyId)?.style.removeProperty('visibility');
        document.getElementById(cardId)?.classList.remove('rp-table-pending');
    });
}
