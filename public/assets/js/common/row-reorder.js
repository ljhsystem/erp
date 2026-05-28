// Path: /assets/js/common/row-reorder.js

export function bindRowReorder(table, options = {}) {
    const {
        api,
        idField = 'id',
        selectionIdField = 'id',
        sortNoField = 'sort_no',
        extraData = null,
        includeAppliedRows = false,
        changedRowsOnly = includeAppliedRows,
        isReorderableRow = () => true,
        sortableItems = '> tr',
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
        items: sortableItems,
        api,
        requestType: 'json',
        includeAppliedRows,
        changedRowsOnly,
        isReorderableRow,
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

    bindSelectedRowMove({
        table,
        tableNode,
        api,
        idField,
        selectionIdField,
        sortNoField,
        extraData,
        includeAppliedRows,
        changedRowsOnly,
        isReorderableRow,
        onSuccess,
        onError
    });

    table.off('draw.rowReorderCleanup').on('draw.rowReorderCleanup', cleanupUI);
}

function bindSelectedRowMove(options = {}) {
    const {
        table,
        tableNode,
        api,
        idField = 'id',
        selectionIdField = 'id',
        sortNoField = 'sort_no',
        extraData = null,
        includeAppliedRows = false,
        changedRowsOnly = includeAppliedRows,
        isReorderableRow = () => true,
        onSuccess = null,
        onError = null
    } = options;

    if (!tableNode || !api) return;

    if (tableNode.__dtSelectedMoveHandler) {
        tableNode.removeEventListener('datatable:move-selected', tableNode.__dtSelectedMoveHandler);
    }
    const state = tableNode.__dtSelectedMoveState || {
        rows: null,
        timer: null,
        version: 0,
        saving: false
    };
    tableNode.__dtSelectedMoveState = state;

    const buildChanges = (rows = [], beforeKeys = null) => rows.map((rowData, index) => {
        if (changedRowsOnly && Array.isArray(beforeKeys) && beforeKeys[index] === selectedMoveKey(rowData, selectionIdField)) {
            return null;
        }

        const item = {
            id: selectedMoveKey(rowData, idField),
            [sortNoField]: index + 1,
            newSortNo: index + 1
        };

        if (extraData) {
            Object.assign(item, extraData(rowData));
        }

        return item;
    }).filter((item) => item?.id);

    const savePendingRows = async (version) => {
        if (state.saving) {
            scheduleSave();
            return;
        }

        const changes = buildChanges(state.rows || [], state.beforeKeys || null);
        if (!changes.length) {
            state.rows = null;
            state.beforeKeys = null;
            return;
        }

        state.saving = true;
        try {
            const response = await fetch(api, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ changes })
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok || json.success === false) {
                if (version === state.version) {
                    state.rows = null;
                    state.beforeKeys = null;
                    if (typeof onError === 'function') {
                        onError(json);
                    }
                }
                return;
            }

            if (version === state.version) {
                state.rows = null;
                state.beforeKeys = null;
                if (typeof onSuccess === 'function') {
                    onSuccess(json);
                }
            }
        } catch (error) {
            if (version === state.version) {
                state.rows = null;
                state.beforeKeys = null;
                if (typeof onError === 'function') {
                    onError(error);
                } else {
                    console.error('[RowReorder] selected move save failed:', error);
                }
            }
        } finally {
            state.saving = false;
            if (state.rows && version !== state.version) {
                scheduleSave();
            }
        }
    };

    function scheduleSave() {
        window.clearTimeout(state.timer);
        const version = state.version;
        state.timer = window.setTimeout(() => {
            void savePendingRows(version);
        }, 450);
    }

    tableNode.__dtSelectedMoveHandler = (event) => {
        event.preventDefault();

        const direction = event.detail?.direction === 'down' ? 'down' : 'up';
        const selectedIds = new Set((event.detail?.ids || table.getSelectedIds?.() || [])
            .map((id) => String(id ?? '').trim())
            .filter(Boolean));

        if (selectedIds.size === 0) {
            return;
        }

        const rows = (state.rows || selectedMoveRows(table, includeAppliedRows))
            .filter((rowData) => isReorderableRow(rowData));
        const beforeKeys = rows.map((row) => selectedMoveKey(row, selectionIdField));
        const selectedKeys = new Set(beforeKeys.filter((key) => selectedIds.has(key)));

        if (selectedKeys.size === 0) {
            if (typeof onError === 'function') {
                onError({ success: false, message: '선택한 행 중 순서를 변경할 수 있는 행이 없습니다.' });
            }
            return;
        }

        const nextRows = moveSelectedRows(rows, selectedKeys, selectionIdField, direction);
        const afterKeys = nextRows.map((row) => selectedMoveKey(row, selectionIdField));
        const changed = beforeKeys.some((key, index) => key !== afterKeys[index]);

        if (!changed) {
            if (typeof onError === 'function') {
                onError({ success: false, message: direction === 'up' ? '선택한 행이 이미 위쪽에 있습니다.' : '선택한 행이 이미 아래쪽에 있습니다.' });
            }
            return;
        }

        nextRows.forEach((rowData, index) => {
            rowData[sortNoField] = index + 1;
            rowData.sort_no = index + 1;
            rowData.newSortNo = index + 1;
        });
        state.rows = nextRows;
        state.beforeKeys = beforeKeys;
        state.version++;
        applySelectedMoveView(table, nextRows, selectedKeys, selectionIdField, direction, includeAppliedRows);
        table.setSelectedIds?.(Array.from(selectedIds));
        scheduleSave();
    };
    tableNode.addEventListener('datatable:move-selected', tableNode.__dtSelectedMoveHandler);
}

function selectedMoveRows(table, includeAppliedRows = false) {
    if (table?.rows) {
        return table.rows({ order: 'applied', search: 'applied' }).data().toArray();
    }

    return table.rows({ page: 'current', order: 'applied', search: 'applied' }).data().toArray();
}

function selectedMoveKey(row = {}, field = 'id') {
    if (!row || typeof row !== 'object') return '';
    if (typeof field === 'function') {
        return String(field(row) ?? '').trim();
    }
    return String(row[field] ?? row.id ?? '').trim();
}

function moveSelectedRows(rows = [], selectedKeys = new Set(), selectionIdField = 'id', direction = 'up') {
    const nextRows = rows.slice();
    const isSelected = (row) => selectedKeys.has(selectedMoveKey(row, selectionIdField));

    if (direction === 'down') {
        for (let index = nextRows.length - 2; index >= 0; index--) {
            if (!isSelected(nextRows[index]) || isSelected(nextRows[index + 1])) {
                continue;
            }
            [nextRows[index], nextRows[index + 1]] = [nextRows[index + 1], nextRows[index]];
        }
        return nextRows;
    }

    for (let index = 1; index < nextRows.length; index++) {
        if (!isSelected(nextRows[index]) || isSelected(nextRows[index - 1])) {
            continue;
        }
        [nextRows[index - 1], nextRows[index]] = [nextRows[index], nextRows[index - 1]];
    }
    return nextRows;
}

function applySelectedMoveView(table, rows = [], selectedKeys = new Set(), selectionIdField = 'id', direction = 'up', includeAppliedRows = false) {
    if (table?.clear && table?.rows?.add && rows.length > 0) {
        const targetPage = selectedMoveTargetPage(table, rows, selectedKeys, selectionIdField, direction);
        table.clear();
        table.rows.add(rows);
        if (Number.isFinite(targetPage)) {
            table.page(targetPage);
        }
        table.draw(false);
        requestAnimationFrame(() => {
            updateVisibleSequenceCells(table);
            ensureSelectedRowsInView(table, direction);
        });
        return;
    }

    moveSelectedDomRows(table, selectedKeys, selectionIdField, direction);
    updateVisibleSequenceCells(table);
    ensureSelectedRowsInView(table, direction);
}

function selectedMoveTargetPage(table, rows = [], selectedKeys = new Set(), selectionIdField = 'id', direction = 'up') {
    const pageInfo = table?.page?.info?.();
    if (!pageInfo) return NaN;

    const indexes = rows
        .map((row, index) => selectedKeys.has(selectedMoveKey(row, selectionIdField)) ? index : -1)
        .filter((index) => index >= 0);
    if (!indexes.length) return pageInfo.page || 0;

    const focusIndex = direction === 'down' ? Math.max(...indexes) : Math.min(...indexes);
    const pageLength = Math.max(Number(pageInfo.length || 0), 1);
    const page = Math.floor(focusIndex / pageLength);
    const maxPage = Math.max(Number(pageInfo.pages || 1) - 1, 0);

    return Math.max(0, Math.min(page, maxPage));
}

function moveSelectedDomRows(table, selectedKeys = new Set(), selectionIdField = 'id', direction = 'up') {
    const tbody = table?.table?.().body?.();
    if (!tbody) return;

    const rowNodes = Array.from(tbody.querySelectorAll('tr'));
    const isSelectedNode = (node) => {
        const rowData = table.row(node).data();
        return selectedKeys.has(selectedMoveKey(rowData, selectionIdField));
    };

    if (direction === 'down') {
        for (let index = rowNodes.length - 2; index >= 0; index--) {
            const current = rowNodes[index];
            const next = rowNodes[index + 1];
            if (!isSelectedNode(current) || isSelectedNode(next)) {
                continue;
            }
            tbody.insertBefore(next, current);
            [rowNodes[index], rowNodes[index + 1]] = [rowNodes[index + 1], rowNodes[index]];
        }
        return;
    }

    for (let index = 1; index < rowNodes.length; index++) {
        const previous = rowNodes[index - 1];
        const current = rowNodes[index];
        if (!isSelectedNode(current) || isSelectedNode(previous)) {
            continue;
        }
        tbody.insertBefore(current, previous);
        [rowNodes[index - 1], rowNodes[index]] = [rowNodes[index], rowNodes[index - 1]];
    }
}

function updateVisibleSequenceCells(table) {
    const tbody = table?.table?.().body?.();
    if (!tbody) return;

    const pageStart = Number(table?.page?.info?.()?.start || 0);
    Array.from(tbody.querySelectorAll('tr')).forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        const sequenceCell = row.querySelector('td.dt-sequence-column') || cells[1];
        if (sequenceCell) {
            sequenceCell.textContent = pageStart + index + 1;
        }
    });
}

function ensureSelectedRowsInView(table, direction = 'up') {
    const wrapper = table?.table?.().container?.();
    const tbody = table?.table?.().body?.();
    if (!wrapper || !tbody) return;

    const selectedRows = Array.from(tbody.querySelectorAll('tr'))
        .filter((row) => row.querySelector('.dt-row-select:checked'));
    if (!selectedRows.length) return;

    const targetRow = direction === 'down'
        ? selectedRows[selectedRows.length - 1]
        : selectedRows[0];
    const scrollContainer = selectedMoveScrollContainer(wrapper);
    const containerRect = scrollContainer === window
        ? { top: 0, bottom: window.innerHeight }
        : scrollContainer.getBoundingClientRect();
    const targetRect = targetRow.getBoundingClientRect();
    const header = wrapper.querySelector('.dataTables_scrollHead, thead');
    const footer = wrapper.querySelector('.dt-bottom');
    const headerBottom = header ? header.getBoundingClientRect().bottom : 0;
    const footerTop = footer ? footer.getBoundingClientRect().top : containerRect.bottom;
    const margin = 10;
    const topLimit = Math.max(containerRect.top, headerBottom + margin);
    const bottomLimit = Math.min(containerRect.bottom, footerTop - margin);

    if (targetRect.top < topLimit) {
        selectedMoveScrollBy(scrollContainer, targetRect.top - topLimit);
        return;
    }

    if (targetRect.bottom > bottomLimit) {
        selectedMoveScrollBy(scrollContainer, targetRect.bottom - bottomLimit);
    }
}

function selectedMoveScrollContainer(node) {
    let current = node?.parentElement || null;
    while (current && current !== document.body && current !== document.documentElement) {
        const style = window.getComputedStyle(current);
        if (/(auto|scroll)/.test(style.overflowY || '') && current.scrollHeight > current.clientHeight) {
            return current;
        }
        current = current.parentElement;
    }

    return window;
}

function selectedMoveScrollBy(container, top) {
    if (!Number.isFinite(top) || Math.abs(top) < 1) return;
    if (container === window) {
        window.scrollBy({ top, behavior: 'auto' });
        return;
    }
    container.scrollTop += top;
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
        includeAppliedRows = false,
        changedRowsOnly = includeAppliedRows,
        isReorderableRow = () => true,
        items = '> tr'
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
        items,
        axis: 'y',
        containment: 'parent',
        tolerance: 'pointer',
        forcePlaceholderSize: true,
        placeholder: 'dt-row-reorder-placeholder',
        start(_, ui) {
            if (changedRowsOnly && includeAppliedRows && table?.rows) {
                const beforeKeys = table.rows({ order: 'applied', search: 'applied' })
                    .data()
                    .toArray()
                    .filter((rowData) => isReorderableRow(rowData))
                    .map((rowData, index) => {
                        if (typeof mapRow === 'function') {
                            return String(mapRow({ row: null, rowData, index })?.id ?? '').trim();
                        }
                        return String(rowData?.id ?? '').trim();
                    });
                $sortable.data('row-reorder-before-keys', beforeKeys);
            } else {
                $sortable.removeData('row-reorder-before-keys');
            }

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
            let visibleReorderIndex = pageStart;

            $sortable.find('tr').each(function () {
                const rowData = table?.row(this).data();
                visibleRows.push({ row: this, rowData });

                if (typeof updateRow === 'function' && isReorderableRow(rowData)) {
                    updateRow({ row: this, rowData, index: visibleReorderIndex });
                }
                if (isReorderableRow(rowData)) {
                    visibleReorderIndex++;
                }
            });

            if (includeAppliedRows && table?.rows) {
                const beforeKeys = changedRowsOnly
                    ? ($sortable.data('row-reorder-before-keys') || [])
                    : [];
                const allRows = table.rows({ order: 'applied', search: 'applied' }).data().toArray();
                visibleRows.forEach(({ rowData }, index) => {
                    allRows[pageStart + index] = rowData;
                });

                allRows.filter((rowData) => isReorderableRow(rowData)).forEach((rowData, index) => {
                    const mapped = typeof mapRow === 'function'
                        ? mapRow({ row: null, rowData, index })
                        : { id: rowData?.id, sort_no: index + 1 };
                    if (mapped?.id && (!changedRowsOnly || beforeKeys[index] !== String(mapped.id).trim())) {
                        rows.push(mapped);
                    }
                });
            } else {
                visibleRows.filter(({ rowData }) => isReorderableRow(rowData)).forEach(({ row, rowData }, index) => {
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
                }).then(async (res) => {
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok && json && typeof json === 'object') {
                        json.success = false;
                    }
                    return json;
                })
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
