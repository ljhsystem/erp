/**
 * Common DataTable adapter
 */
export function createDataTableAdapter(table, options = {}) {
    const tableIdField = options.rowIdField || options.rowId || 'id';
    const activeRowClass = options.activeRowClass || 'is-active';
    const tableApi = table;

    if (!tableApi || typeof tableApi.rows !== 'function') {
        return {
            getRows: () => [],
            getVisibleRows: () => [],
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
            getVisibleRowNodes: () => [],
            getRowNode: () => null,
            getPageInfo: () => null,
        };
    }

    const noop = () => {};
    const eventNamespace = '.table-interaction';

    function getHostElement() {
        return (
            tableApi.table?.().container?.()
            || tableApi.table?.().node?.()
            || null
        );
    }

    function getRowSource(row) {
        if (row && typeof row === 'object' && Object.prototype.hasOwnProperty.call(row, 'rowData')) {
            return row.rowData;
        }
        return row;
    }

    function getRows() {
        return tableApi.rows().data().toArray();
    }

    function getVisibleRowNodes() {
        return Array.from(tableApi.rows({ page: 'current' }).nodes().toArray());
    }

    function getVisibleRows() {
        const rows = tableApi.rows({ page: 'current' }).data().toArray();
        const nodes = getVisibleRowNodes();

        return rows.map((rowData, visibleIndex) => ({
            rowId: getRowId(rowData),
            index: visibleIndex,
            data: rowData,
            rowData,
            node: nodes[visibleIndex] || null,
        }));
    }

    function getRowId(row) {
        const source = getRowSource(row);
        return String((typeof tableIdField === 'function' ? tableIdField(source) : (source?.[tableIdField])) ?? '').trim();
    }

    function clearSelection() {
        const node = getHostElement();
        if (!node) return;
        node.querySelectorAll(`.${activeRowClass}`)
            .forEach((item) => {
                item.classList.remove(activeRowClass);
                if (item instanceof HTMLElement) {
                    item.removeAttribute('aria-selected');
                }
            });
    }

    function applyActiveRowState(target) {
        const host = getHostElement();
        if (!host) return;

        host.querySelectorAll(`.${activeRowClass}`).forEach((item) => {
            item.classList.remove(activeRowClass);
            if (item instanceof HTMLElement) {
                item.setAttribute('aria-selected', 'false');
            }
        });

        const node = target?.node || null;
        if (!node || !(node instanceof HTMLElement)) return;

        node.classList.add(activeRowClass);
        node.setAttribute('aria-selected', 'true');
        if (typeof node.scrollIntoView === 'function') {
            node.scrollIntoView({
                block: 'nearest',
                behavior: 'auto',
            });
        }
    }

    function resolveRowById(rowId) {
        const safeId = String(rowId ?? '').trim();
        if (!safeId) return null;

        const rows = getRows();
        const rowIndex = rows.findIndex((row) => getRowId(row) === safeId);
        if (rowIndex < 0) return null;

        const rowNode = tableApi.row(rowIndex).node();
        return {
            rowId: safeId,
            rowIndex,
            data: rows[rowIndex],
            rowData: rows[rowIndex],
            node: rowNode || null,
        };
    }

    function resolveRowByVisibleIndex(visibleIndex) {
        const visibleRows = getVisibleRows();
        return visibleRows[visibleIndex] || null;
    }

    function setActiveRow(rowId = null, rowIndex = null) {
        let target = null;
        if (rowIndex !== null && Number.isInteger(rowIndex)) {
            target = resolveRowByVisibleIndex(rowIndex);
        } else if (rowId !== null && rowId !== undefined) {
            target = resolveRowById(String(rowId));
            if (!target) {
                const safeRows = getVisibleRows();
                const fallbackIndex = safeRows.findIndex((row) => getRowId(row) === String(rowId));
                if (fallbackIndex >= 0) {
                    target = resolveRowByVisibleIndex(fallbackIndex);
                }
            }
        }

        if (!target) return null;

        applyActiveRowState(target);
        return target;
    }

    function redraw() {
        tableApi.draw(false);
    }

    function getPageInfo() {
        if (typeof tableApi.page?.info !== 'function') {
            return null;
        }
        return tableApi.page.info();
    }

    function getSelectedIds() {
        if (typeof tableApi.getSelectedIds !== 'function') {
            return [];
        }
        const ids = tableApi.getSelectedIds();
        return Array.isArray(ids) ? ids.filter(Boolean).map((value) => String(value)) : [];
    }

    function setSelectedIds(ids = []) {
        if (typeof tableApi.setSelectedIds !== 'function') return;
        const nextIds = Array.from(new Set(
            (Array.isArray(ids) ? ids : []).map((id) => String(id || '').trim()).filter(Boolean)
        ));
        tableApi.setSelectedIds(nextIds);
    }

    function on(eventName, handler) {
        const callback = typeof handler === 'function' ? handler : noop;
        tableApi.on(`${eventName}${eventNamespace}`, callback);
        return () => off(eventName, callback);
    }

    function off(eventName, handler) {
        if (!eventName) {
            tableApi.off(`${eventNamespace}`);
            return;
        }

        if (!handler) {
            tableApi.off(`${eventName}${eventNamespace}`);
            return;
        }

        tableApi.off(`${eventName}${eventNamespace}`, handler);
    }

    function destroy() {
        off();
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
        getRowNode: resolveRowById,
        getPageInfo,
    };
}
