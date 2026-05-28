export function selectEditor(values = []) {
    return {
        cellEditor: 'agSelectCellEditor',
        cellEditorParams: {
            values: values.map((value) => String(value ?? '')),
        },
    };
}

export function dateStringEditor() {
    return {
        cellEditor: 'agDateStringCellEditor',
    };
}
