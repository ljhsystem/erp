export function selectedRowIndexes(api, fallbackIndex = 0) {
    const selectedNodes = api?.getSelectedNodes?.() || [];
    if (selectedNodes.length > 0) {
        return selectedNodes
            .map((node) => node.rowIndex)
            .filter((index) => Number.isInteger(index) && index >= 0);
    }
    return [Math.max(0, Number(fallbackIndex) || 0)];
}
