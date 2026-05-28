export const TABLE_INTERACTION_EVENTS = {
    activeRowChange: 'table:activeRowChange',
    selectionChange: 'table:selectionChange',
    requestEdit: 'table:requestEdit',
    redraw: 'table:redraw',
    destroy: 'table:destroy',
};

export function createInteractionEventPayload({
    rowId = null,
    rowIndex = -1,
    reason = 'default',
    source = 'unknown',
    event = null,
} = {}) {
    return {
        rowId,
        rowIndex,
        reason,
        source,
        event,
    };
}

export function dispatchInteractionEvent(target, type, payload = {}) {
    if (!target || !type) {
        return;
    }

    const customEvent = new CustomEvent(type, {
        bubbles: true,
        detail: payload,
    });

    target.dispatchEvent(customEvent);
}
