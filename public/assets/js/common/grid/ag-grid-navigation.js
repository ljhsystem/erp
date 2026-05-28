export function flattenGridColumns(columnDefs = []) {
    return columnDefs.flatMap((column) => (
        Array.isArray(column.children) ? flattenGridColumns(column.children) : [column]
    ));
}

export function fieldFromColumnRef(columnDefs = [], columnRef = 0) {
    if (typeof columnRef === 'number') {
        const column = flattenGridColumns(columnDefs)[columnRef];
        return column?.field || column?.colId || '';
    }
    return String(columnRef || '').trim();
}

export function fieldNamesFromColumnDefs(columnDefs = []) {
    return flattenGridColumns(columnDefs).map((column) => column.field || column.colId).filter(Boolean);
}
