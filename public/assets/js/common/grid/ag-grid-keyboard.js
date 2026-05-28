function keyFromEvent(event) {
    return event?.event?.key || event?.key || '';
}

export function handleAgGridKeyboard(event, adapter) {
    const key = keyFromEvent(event);
    if (!key || event?.event?.defaultPrevented) return;

    if (key === 'Enter') {
        if (!adapter.isEditing()) {
            event.event?.preventDefault?.();
            adapter.startEditing(event.rowIndex, event.column?.getColId?.());
        }
        return;
    }

    if (key === 'F2') {
        event.event?.preventDefault?.();
        adapter.startEditing(event.rowIndex, event.column?.getColId?.());
        return;
    }

    if (key === 'Escape' && adapter.isEditing()) {
        event.event?.preventDefault?.();
        adapter.stopEditing(true);
    }
}
