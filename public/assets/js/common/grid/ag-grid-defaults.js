export function defaultRowDragOptions(config = {}) {
    return {
        rowDragManaged: config.rowDragManaged !== false,
        animateRows: config.animateRows !== false,
    };
}
