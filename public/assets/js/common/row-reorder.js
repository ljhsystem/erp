// Path: /assets/js/common/row-reorder.js

export function bindRowReorder(table, options = {}) {
    const {
        api,
        idField = 'id',
        sortNoField = 'sort_no',
        extraData = null,
        includeAppliedRows = false,
        onSuccess = null,
        onError = null
    } = options;

    if (!table) {
        console.error('[RowReorder] table 없음');
        return;
    }

    if (!api) {
        console.error('[RowReorder] api 없음');
        return;
    }

    const tableNode = table.table?.().node?.();
    const tableSelector = tableNode?.id ? `#${tableNode.id}` : tableNode;

    bindSortableRowReorder({
        table,
        tableSelector,
        handle: '.reorder-handle',
        api,
        requestType: 'json',
        includeAppliedRows,
        mapRow({ rowData, index }) {
            if (!rowData) return null;

            const item = {
                id: rowData[idField],
                [sortNoField]: index + 1,
                newSortNo: index + 1
            };

            if (extraData) {
                Object.assign(item, extraData(rowData));
            }

            return item;
        },
        updateRow({ row, index }) {
            const $cells = window.jQuery(row).find('td');
            const $sequenceCell = $cells.filter('.dt-sequence-column').first();
            ($sequenceCell.length ? $sequenceCell : $cells.eq(1)).text(index + 1);
        },
        buildPayload(changes) {
            return { changes };
        },
        onSuccess,
        onError
    });

    table.off('draw.rowReorderCleanup').on('draw.rowReorderCleanup', cleanupUI);
}

export function bindSortableRowReorder(options = {}) {
    const {
        table,
        tableSelector,
        handle = '.drag-handle',
        api,
        isLocked = () => false,
        lock = () => {},
        unlock = () => {},
        mapRow = null,
        updateRow = null,
        buildPayload = null,
        onSuccess = null,
        onError = null,
        onComplete = null,
        reload = null,
        requestType = 'form',
        includeAppliedRows = false
    } = options;

    const $ = window.jQuery;
    const $table = $(tableSelector);
    const $sortable = $table.find('tbody');

    if (!$sortable.length || isLocked()) return;

    if (typeof $sortable.sortable !== 'function') {
        console.error('[RowReorder] jQuery UI sortable is not available.');
        return;
    }

    if (!api) {
        console.error('[RowReorder] api 없음');
        return;
    }

    if ($sortable.data('ui-sortable')) {
        $sortable.sortable('destroy');
    }

    $sortable.sortable({
        handle,
        items: '> tr',
        axis: 'y',
        containment: 'parent',
        tolerance: 'pointer',
        forcePlaceholderSize: true,
        placeholder: 'dt-row-reorder-placeholder',
        start(_, ui) {
            const colspan = Math.max(ui.item.children('td, th').length, 1);
            ui.placeholder
                .height(ui.item.outerHeight())
                .html(`<td colspan="${colspan}"></td>`);
            ui.item.addClass('dt-row-reorder-source');
        },
        helper(_, tr) {
            const $originals = tr.children();
            const $helper = tr.clone().addClass('dt-row-reorder-helper');

            $helper.children().each(function (index) {
                $(this).width($originals.eq(index).outerWidth());
            });

            return $helper;
        },
        stop() {
            $sortable.find('.dt-row-reorder-source').removeClass('dt-row-reorder-source');
            $sortable.find('.dt-row-reorder-placeholder').remove();

            const rows = [];
            const visibleRows = [];
            const pageStart = Number(table?.page?.info?.()?.start || 0);

            $sortable.find('tr').each(function (index) {
                const rowData = table?.row(this).data();
                visibleRows.push({ row: this, rowData });

                if (typeof updateRow === 'function') {
                    updateRow({ row: this, rowData, index: pageStart + index });
                }
            });

            if (includeAppliedRows && table?.rows) {
                const allRows = table.rows({ order: 'applied', search: 'applied' }).data().toArray();
                visibleRows.forEach(({ rowData }, index) => {
                    allRows[pageStart + index] = rowData;
                });

                allRows.forEach((rowData, index) => {
                    const mapped = typeof mapRow === 'function'
                        ? mapRow({ row: null, rowData, index })
                        : { id: rowData?.id, sort_no: index + 1 };
                    if (mapped?.id) rows.push(mapped);
                });
            } else {
                visibleRows.forEach(({ row, rowData }, index) => {
                    const mapped = typeof mapRow === 'function'
                        ? mapRow({ row, rowData, index })
                        : { id: rowData?.id || $(row).data('id'), sort_no: index + 1 };
                    if (mapped?.id) rows.push(mapped);
                });
            }

            if (!rows.length) return;

            lock();

            const payload = typeof buildPayload === 'function'
                ? buildPayload(rows)
                : { changes: JSON.stringify(rows) };

            const request = requestType === 'json'
                ? fetch(api, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then((res) => res.json())
                : $.post(api, payload);

            Promise.resolve(request)
                .then((res) => {
                    if (!res?.success) {
                        if (typeof onError === 'function') {
                            onError(res);
                        }
                        return;
                    }

                    if (typeof onSuccess === 'function') {
                        onSuccess(res);
                    }
                })
                .catch((err) => {
                    if (typeof onError === 'function') {
                        onError(err);
                    } else {
                        console.error('[RowReorder] save failed:', err);
                    }
                })
                .finally(() => {
                    cleanupUI();
                    setTimeout(() => {
                        unlock();
                        if (typeof reload === 'function') {
                            reload();
                        }
                        if (typeof onComplete === 'function') {
                            onComplete();
                        }
                    }, 120);
                });
        }
    }).disableSelection();
}

function cleanupUI() {
    document.querySelectorAll('.tooltip, .tooltip-container').forEach((el) => {
        el.remove();
    });

    document.body.classList.remove('tooltip-open');
}
