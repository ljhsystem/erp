export function createInteractionState(initialState = {}) {
    return {
        activeRowId: null,
        activeRowIndex: -1,
        hoverRowId: null,
        hoverRowIndex: -1,
        selectedRowIds: new Set(),
        focusedCell: null,
        editing: false,
        suspended: false,
        modalDepth: 0,
        pendingRestore: null,
        redrawSeq: 0,
        ...initialState,
    };
}

export function cloneInteractionState(state = createInteractionState()) {
    return {
        ...state,
        selectedRowIds: new Set(state.selectedRowIds || []),
    };
}
