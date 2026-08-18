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

export function editableFieldNames(columnDefs = [], rowData = null) {
    return flattenGridColumns(columnDefs)
        .filter(column => {
            if (column.hide === true || column.suppressNavigable === true) return false;
            return typeof column.editable === 'function'
                ? column.editable({ data: rowData, colDef: column })
                : column.editable === true;
        })
        .map(column => column.field || column.colId)
        .filter(Boolean);
}

export function adjacentEditableCell(columnDefs, rowIndex, field, rowCount, backwards = false) {
    const fields = editableFieldNames(columnDefs);
    if (fields.length === 0) return null;
    const delta = backwards ? -1 : 1;
    let columnIndex = Math.max(0, fields.indexOf(String(field || ''))) + delta;
    let nextRow = rowIndex;
    if (columnIndex >= fields.length) {
        columnIndex = 0;
        nextRow += 1;
    } else if (columnIndex < 0) {
        columnIndex = fields.length - 1;
        nextRow -= 1;
    }
    if (nextRow < 0 || nextRow >= rowCount) return null;
    return { rowIndex: nextRow, field: fields[columnIndex] };
}
