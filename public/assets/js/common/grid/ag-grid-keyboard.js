function keyFromEvent(event) {
    return event?.event?.key || event?.key || '';
}

function keyboardModeFromConfig(config = {}) {
    return String(config?.keyboardMode || 'legacy').trim().toLowerCase();
}

export function handleAgGridKeyboard(event, adapter, config = {}) {
    const key = keyFromEvent(event);
    if (!key || event?.event?.defaultPrevented) return;
    const keyboardMode = keyboardModeFromConfig(config);

    if (key === 'Enter' && keyboardMode !== 'excel-selection') {
        if (!adapter.isEditing()) {
            event.event?.preventDefault?.();
            adapter.startEditing(event.rowIndex, event.column?.getColId?.());
        }
        return;
    }

    if (key === 'F2' || (key === 'Enter' && event?.event?.ctrlKey)) {
        event.event?.preventDefault?.();
        adapter.startEditing(event.rowIndex, event.column?.getColId?.());
        return;
    }

    if (keyboardMode === 'excel-selection' && key === 'Enter') {
        event.event?.preventDefault?.();
        if (adapter.isEditing()) adapter.stopEditing(false);
        config.moveToAdjacentEditableCell?.(event, adapter, false);
        return;
    }

    if (!adapter.isEditing() && (key === 'Delete' || key === 'Backspace')) {
        const field = event.column?.getColId?.();
        if (!field || event.colDef?.editable !== true) return;
        event.event?.preventDefault?.();
        event.node?.setDataValue?.(field, '');
    }
}
