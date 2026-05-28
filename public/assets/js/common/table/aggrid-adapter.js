export function createAgGridAdapter(gridApi, options = {}) {
    const rowIdField = options.rowIdField || options.rowId || 'id';
    const noop = () => {};
    const listeners = new Map();
    const eventNamespace = '.table-interaction';

    if (!gridApi || typeof gridApi.getDisplayedRowCount !== 'function') {
        return {
            getRows: () => [],
            getVisibleRows: () => [],
            getVisibleRowNodes: () => [],
            resolveRowById: () => null,
            setActiveRow: () => null,
            clearSelection: () => {},
            redraw: () => {},
            getSelectedIds: () => [],
            setSelectedIds: () => {},
            on: () => () => {},
            off: () => {},
            destroy: () => {},
            getHostElement: () => null,
            getRowId: () => null,
            getRowNode: () => null,
            getPageInfo: () => null,
        };
    }

    function getRows() {
        return gridApi.getModel?.().getType() === 'normal'
            ? gridApi.getModel().rowsToDisplay || []
            : [];
    }

    function getVisibleRows() {
        return getRows();
    }

    function getVisibleRowNodes() {
        return getRows()
            .map((node) => node?.rowNode?.domWrapper || node?.domWrapper)
            .filter(Boolean);
    }

    function getRowId(row) {
        return String((typeof rowIdField === 'function' ? rowIdField(row.data || row) : (row?.data?.[rowIdField] ?? row?.[rowIdField] ?? '')) || '').trim();
    }

    function resolveRowById(rowId) {
        const targetId = String(rowId ?? '').trim();
        if (!targetId) return null;
        const rowNode = getRows().find((node) => getRowId(node.data || node) === targetId);
        if (!rowNode) return null;

        return {
            rowId: targetId,
            rowIndex: rowNode.rowIndex ?? -1,
            rowData: rowNode.data || rowNode,
            rowNode,
        };
    }

    function resolveRowByVisibleIndex(visibleIndex) {
        const rows = getVisibleRows();
        const node = rows[visibleIndex];
        if (!node) return null;
        return {
            rowId: getRowId(node.data || node),
            rowIndex: node.rowIndex ?? visibleIndex,
            rowData: node.data || node,
            rowNode: node,
        };
    }

    function setActiveRow(rowId = null, rowIndex = null) {
        if (rowIndex !== null && Number.isInteger(rowIndex)) {
            return resolveRowByVisibleIndex(rowIndex);
        }

        if (rowId === null || rowId === undefined) return null;
        return resolveRowById(String(rowId));
    }

    function clearSelection() {
        if (typeof gridApi.deselectAll === 'function') {
            gridApi.deselectAll();
        }
        if (typeof gridApi.clearFocusedCell === 'function') {
            gridApi.clearFocusedCell();
        }
    }

    function redraw() {
        if (typeof gridApi.redrawRows === 'function') {
            gridApi.redrawRows();
        }
        if (typeof gridApi.refreshCells === 'function') {
            gridApi.refreshCells({ force: true });
        }
    }

    function getPageInfo() {
        if (typeof gridApi.paginationGetCurrentPage !== 'function' || typeof gridApi.paginationGetPageSize !== 'function') {
            return null;
        }

        const page = gridApi.paginationGetCurrentPage?.();
        const size = gridApi.paginationGetPageSize?.();
        const rowCount = Number.isInteger(gridApi.getDisplayedRowCount?.()) ? gridApi.getDisplayedRowCount() : 0;
        if (!Number.isInteger(page) || !Number.isInteger(size)) return null;

        const start = page * Math.max(size, 0);
        const length = size;
        return {
            page,
            length,
            start,
            recordsTotal: rowCount,
            recordsDisplay: rowCount,
        };
    }

    function getSelectedIds() {
        if (!gridApi) return [];
        const selectedNodes = gridApi.getSelectedNodes?.() || [];
        return Array.isArray(selectedNodes)
            ? selectedNodes.map((node) => getRowId(node?.data || {})).filter(Boolean)
            : [];
    }

    function setSelectedIds(ids = []) {
        if (typeof gridApi.deselectAll === 'function') {
            gridApi.deselectAll();
        }
        if (typeof gridApi.selectNode === 'function' && Array.isArray(ids)) {
            const idSet = new Set(ids.map((id) => String(id || '').trim()).filter(Boolean));
            getRows().forEach((node) => {
                if (!node?.data) return;
                const rowId = getRowId(node.data);
                if (idSet.has(rowId)) {
                    gridApi.selectNode(node, true, true);
                }
            });
        }
    }

    function on(eventName, handler) {
        if (typeof handler !== 'function' || typeof eventName !== 'string') {
            return noop;
        }

        const key = `${eventName}${eventNamespace}`;
        const wrap = (event) => handler(event, key);
        gridApi.addEventListener(eventName, wrap);
        listeners.set(wrap, { eventName });
        return () => off(eventName, wrap);
    }

    function off(eventName, handler) {
        if (typeof handler === 'function') {
            gridApi.removeEventListener(eventName, handler);
            return;
        }

        if (typeof eventName !== 'string') return;
        listeners.forEach((entry, storedHandler) => {
            if (entry?.eventName === eventName || `${entry?.eventName}${eventNamespace}` === eventName) {
                gridApi.removeEventListener(eventName, storedHandler);
                listeners.delete(storedHandler);
            }
        });
    }

    function destroy() {
        listeners.forEach((entry, storedHandler) => {
            gridApi.removeEventListener(entry?.eventName, storedHandler);
        });
        listeners.clear();
    }

    function getHostElement() {
        return gridApi.gridPanel?.eGridDiv || null;
    }

    function getRowNode(rowIndex) {
        if (!Number.isInteger(rowIndex) || rowIndex < 0) return null;
        return gridApi.getDisplayedRowAtIndex(rowIndex);
    }

    return {
        getRows,
        getVisibleRows,
        getVisibleRowNodes,
        resolveRowById,
        setActiveRow,
        clearSelection,
        redraw,
        getSelectedIds,
        setSelectedIds,
        on,
        off,
        destroy,
        getHostElement,
        getRowId,
        getRowNode,
        getPageInfo,
    };
}
