export function selectEditor(values = []) {
    return {
        cellEditor: 'agSelectCellEditor',
        cellEditorParams: () => {
            const resolved = typeof values === 'function' ? values() : values;
            return {
                values: (Array.isArray(resolved) ? resolved : []).map((value) => String(value ?? '')),
            };
        },
    };
}

export function dateStringEditor() {
    return {
        cellEditor: 'agDateStringCellEditor',
    };
}
