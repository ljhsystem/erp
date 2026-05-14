// Path: /assets/js/components/data-table.js

const __dtAdjustState = new WeakMap();
const DEFAULT_PAGE_LENGTH = 100;
const PAGE_LENGTH_MENU = [100, 200, 300, 500, 1000, 2000, 3000, 5000, 10000];
const DELETE_PROGRESS_CHUNK_SIZE = 500;

const UTILITY_COLUMN_WIDTHS = {
    select: '44px',
    reorder: '44px',
    sequence: '56px',
    status: '72px',
    action: '64px',
};

function tokenizeClasses(...values) {
    return values
        .flatMap((value) => String(value || '').split(/\s+/))
        .map((token) => token.trim())
        .filter(Boolean);
}

function applyColumnHeaderClasses(table, columns = []) {
    if (!table || !Array.isArray(columns) || columns.length === 0) {
        return;
    }

    const originalHeaders = Array.from(table.table().header()?.querySelectorAll('th') || []);
    const wrapper = table.table().container();
    const scrollHeaders = Array.from(wrapper?.querySelectorAll('.dataTables_scrollHead th') || []);

    columns.forEach((column, index) => {
        const classes = tokenizeClasses(column.className, column.headerClassName);
        if (classes.length === 0) {
            return;
        }

        [originalHeaders[index], scrollHeaders[index]].forEach((headerNode) => {
            if (!headerNode) return;
            headerNode.classList.add(...classes);
        });
    });
}

function joinClasses(...values) {
    return Array.from(new Set(tokenizeClasses(...values))).join(' ');
}

function isReorderColumn(column = {}) {
    const classes = tokenizeClasses(column.className, column.headerClassName);
    return classes.includes('reorder-handle') || classes.includes('drag-handle') || classes.includes('col-reorder');
}

function isSequenceColumn(column = {}) {
    const title = String(column.title || '').trim();
    return column.data === 'sort_no' || title === '순번';
}

function isStatusColumn(column = {}) {
    const title = String(column.title || '').trim();
    return column.data === 'is_active' || title === '상태' || title === '진행상황';
}

function isActionColumn(column = {}) {
    const title = String(column.title || '').trim();
    return column.data == null && (title === '관리' || title === '수정');
}

function withUtilityColumnDefaults(column = {}) {
    const next = { ...column };
    let utilityClass = '';
    let width = '';

    if (next.isSelectionColumn === true || tokenizeClasses(next.className, next.headerClassName).includes('dt-select-column')) {
        utilityClass = 'dt-select-column';
        width = UTILITY_COLUMN_WIDTHS.select;
    } else if (isReorderColumn(next)) {
        utilityClass = 'dt-reorder-column';
        width = UTILITY_COLUMN_WIDTHS.reorder;
    } else if (isSequenceColumn(next)) {
        utilityClass = 'dt-sequence-column';
        width = UTILITY_COLUMN_WIDTHS.sequence;
    } else if (isStatusColumn(next)) {
        utilityClass = 'dt-status-column';
        width = UTILITY_COLUMN_WIDTHS.status;
    } else if (isActionColumn(next)) {
        utilityClass = 'dt-action-column';
        width = UTILITY_COLUMN_WIDTHS.action;
        next.orderable = false;
        next.searchable = false;
    }

    if (!utilityClass) {
        return next;
    }

    next.width = width;
    next.className = joinClasses(next.className, utilityClass, 'text-center');
    next.headerClassName = joinClasses(next.headerClassName, utilityClass, 'text-center');

    return next;
}

function normalizeUtilityColumns(columns = []) {
    return columns.map(withUtilityColumnDefaults);
}

async function postFormJson(url, data = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: new URLSearchParams(data),
    });

    return response.json();
}

async function postBulkDeleteJson(url, ids = []) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            ids,
            seed_row_ids: ids,
            evidence_ids: ids,
        }),
    });

    return response.json();
}

function showGlobalLoading(message = '처리 중입니다...') {
    if (window.AppCore?.showLoading) {
        window.AppCore.showLoading(message);
        return;
    }

    const overlay = document.getElementById('global-loading-overlay');
    if (overlay) {
        overlay.style.display = 'flex';
    }
}

function hideGlobalLoading() {
    if (window.AppCore?.hideLoading) {
        window.AppCore.hideLoading();
        return;
    }

    const overlay = document.getElementById('global-loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function ensureDeleteProgressPanel() {
    let panel = document.getElementById('dt-delete-progress-panel');
    if (panel) {
        return panel;
    }

    panel = document.createElement('div');
    panel.id = 'dt-delete-progress-panel';
    panel.className = 'dt-delete-progress-panel';
    panel.innerHTML = `
        <div class="dt-delete-progress-card" role="status" aria-live="polite">
            <div class="dt-delete-progress-head">
                <strong data-dt-delete-title>삭제 처리 중</strong>
                <span data-dt-delete-percent>0%</span>
            </div>
            <div class="dt-delete-progress-bar" aria-hidden="true">
                <span data-dt-delete-bar></span>
            </div>
            <div class="dt-delete-progress-meta">
                <span data-dt-delete-count>0 / 0건</span>
                <span data-dt-delete-step>준비 중</span>
            </div>
        </div>
    `;
    document.body.appendChild(panel);

    if (!document.getElementById('dt-delete-progress-style')) {
        const style = document.createElement('style');
        style.id = 'dt-delete-progress-style';
        style.textContent = `
            .dt-delete-progress-panel {
                position: fixed;
                inset: 0;
                z-index: 2100;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: rgba(15, 23, 42, 0.28);
            }
            .dt-delete-progress-panel.is-active {
                display: flex;
            }
            .dt-delete-progress-card {
                width: min(420px, 100%);
                border: 1px solid #d9e2ef;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.22);
                padding: 18px 20px;
                color: #111827;
            }
            .dt-delete-progress-head,
            .dt-delete-progress-meta {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }
            .dt-delete-progress-head strong {
                font-size: 16px;
                font-weight: 700;
            }
            .dt-delete-progress-head span {
                font-size: 18px;
                font-weight: 700;
                color: #2563eb;
            }
            .dt-delete-progress-bar {
                height: 10px;
                margin: 14px 0 10px;
                overflow: hidden;
                border-radius: 999px;
                background: #e5e7eb;
            }
            .dt-delete-progress-bar span {
                display: block;
                width: 0%;
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb, #10b981);
                transition: width 160ms ease;
            }
            .dt-delete-progress-meta {
                font-size: 13px;
                color: #4b5563;
            }
        `;
        document.head.appendChild(style);
    }

    return panel;
}

function updateDeleteProgress({ total = 0, processed = 0, step = '처리 중' } = {}) {
    const panel = ensureDeleteProgressPanel();
    const safeTotal = Math.max(0, Number(total) || 0);
    const safeProcessed = Math.min(safeTotal, Math.max(0, Number(processed) || 0));
    const percent = safeTotal > 0 ? Math.round((safeProcessed / safeTotal) * 100) : 0;

    panel.classList.add('is-active');
    panel.querySelector('[data-dt-delete-percent]').textContent = `${percent}%`;
    panel.querySelector('[data-dt-delete-count]').textContent = `${safeProcessed.toLocaleString('ko-KR')} / ${safeTotal.toLocaleString('ko-KR')}건`;
    panel.querySelector('[data-dt-delete-step]').textContent = step;
    panel.querySelector('[data-dt-delete-bar]').style.width = `${percent}%`;
}

function hideDeleteProgress() {
    const panel = document.getElementById('dt-delete-progress-panel');
    if (!panel) return;
    panel.classList.remove('is-active');
}

function setDataTableDeleteBusy(table, busy = false, buttonNode = null) {
    const wrapper = table?.table?.().container?.();
    const tableNode = table?.table?.().node?.();
    const selectedCount = typeof table?.getSelectedIds === 'function' ? table.getSelectedIds().length : 0;
    if (tableNode) {
        tableNode.dataset.dtDeleting = busy ? 'true' : 'false';
    }
    wrapper?.classList.toggle('dt-action-busy', busy);

    const deleteButtons = wrapper ? Array.from(wrapper.querySelectorAll('.dt-soft-delete-btn')) : [];
    const checkboxes = wrapper ? Array.from(wrapper.querySelectorAll('.dt-row-select, .dt-select-all')) : [];
    const controls = [...deleteButtons, ...checkboxes, ...(buttonNode ? [buttonNode] : [])];

    controls.forEach((control) => {
        if (!control) return;
        control.disabled = busy;
        if (control.classList.contains('dt-soft-delete-btn')) {
            const disabled = busy || selectedCount === 0;
            control.classList.toggle('disabled', disabled);
            control.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            return;
        }
        control.classList.toggle('disabled', busy);
        control.setAttribute('aria-disabled', busy ? 'true' : 'false');
    });
}

function reloadDataTable(table) {
    if (!table?.ajax?.reload) {
        table?.draw?.(false);
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        table.ajax.reload(() => resolve(), false);
    });
}

async function softDeleteSelectedRows({ deleteApi, ids, table, selectedIds, buttonNode, bulkDelete = false }) {
    if (!deleteApi) {
        return false;
    }

    if (ids.length === 0) {
        const message = '삭제할 행을 선택하세요.';
        window.AppCore?.notify?.('error', message);
        return true;
    }

    if (!window.confirm(`선택한 ${ids.length}건을 삭제하시겠습니까?`)) {
        return true;
    }

    setDataTableDeleteBusy(table, true, buttonNode);
    updateDeleteProgress({ total: ids.length, processed: 0, step: '삭제 요청 준비 중' });

    try {
        if (bulkDelete) {
            let processed = 0;
            const chunks = [];
            for (let index = 0; index < ids.length; index += DELETE_PROGRESS_CHUNK_SIZE) {
                chunks.push(ids.slice(index, index + DELETE_PROGRESS_CHUNK_SIZE));
            }

            for (const [index, chunk] of chunks.entries()) {
                updateDeleteProgress({
                    total: ids.length,
                    processed,
                    step: `${index + 1} / ${chunks.length} 묶음 처리 중`,
                });
                const result = await postBulkDeleteJson(deleteApi, chunk);
                if (!result?.success) {
                    window.AppCore?.notify?.('error', result?.message || '삭제에 실패했습니다.');
                    return true;
                }
                processed += chunk.length;
                updateDeleteProgress({
                    total: ids.length,
                    processed,
                    step: `${processed.toLocaleString('ko-KR')}건 처리 완료`,
                });
            }
        } else {
            for (const [index, id] of ids.entries()) {
                updateDeleteProgress({
                    total: ids.length,
                    processed: index,
                    step: `${index + 1}번째 행 처리 중`,
                });
                const result = await postFormJson(deleteApi, { id });
                if (!result?.success) {
                    window.AppCore?.notify?.('error', result?.message || `삭제 실패 (${index + 1}번째)`);
                    return true;
                }
                updateDeleteProgress({
                    total: ids.length,
                    processed: index + 1,
                    step: `${index + 1}건 처리 완료`,
                });
            }
        }

        updateDeleteProgress({ total: ids.length, processed: ids.length, step: '목록 새로고침 중' });
        selectedIds.clear();
        table.table?.().node?.()?.dispatchEvent(new CustomEvent('datatable:selection-changed', {
            bubbles: true,
            detail: {
                table,
                ids: [],
                selectedIds,
            },
        }));
        await reloadDataTable(table);
        table.table?.().node?.()?.dispatchEvent(new CustomEvent('datatable:soft-delete-completed', {
            bubbles: true,
            detail: {
                table,
                ids,
                selectedIds,
            },
        }));
        window.AppCore?.notify?.('success', `삭제 완료 (${ids.length}건)`);
        return true;
    } catch (error) {
        console.error(error);
        window.AppCore?.notify?.('error', error?.message || '삭제 중 오류가 발생했습니다.');
        return true;
    } finally {
        hideDeleteProgress();
        setDataTableDeleteBusy(table, false, buttonNode);
    }
}

function isColumnVisibleInColvis(columns, index, node) {
    const column = columns[index] || {};
    const classes = tokenizeClasses(column.className, column.headerClassName);

    return !classes.includes('no-colvis') && !node?.classList?.contains('no-colvis');
}

function ensureTableHeader(tableSelector, columns = []) {
    const table = document.querySelector(tableSelector);
    if (!table || !Array.isArray(columns) || columns.length === 0) {
        return;
    }

    let thead = table.querySelector('thead');
    if (!thead) {
        thead = document.createElement('thead');
        table.insertBefore(thead, table.firstChild);
    }

    let row = thead.querySelector('tr');
    if (!row) {
        row = document.createElement('tr');
        thead.appendChild(row);
    }

    const headers = row.querySelectorAll('th');
    if (headers.length === columns.length) {
        return;
    }

    row.innerHTML = columns.map((column) => {
        const classes = tokenizeClasses(column.className, column.headerClassName);
        const classAttr = classes.length ? ` class="${classes.join(' ')}"` : '';
        return `<th${classAttr}>${column.title ?? ''}</th>`;
    }).join('');
}

function hasSelectionColumn(columns = []) {
    const first = columns[0] || {};
    const title = String(first.title || '');
    const className = tokenizeClasses(first.className, first.headerClassName);

    return first.isSelectionColumn === true
        || className.includes('dt-select-column')
        || title.includes('type="checkbox"');
}

function escapeAttr(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

function createSelectionColumn(tableSelector, selectedIds, rowIdField) {
    const tableId = String(tableSelector || '').replace(/^#/, '') || 'dataTable';
    const checkAllId = `${tableId}SelectAll`;

    return {
        data: null,
        title: `<input type="checkbox" class="form-check-input dt-select-all" id="${escapeAttr(checkAllId)}" aria-label="전체 선택">`,
        className: 'dt-select-column no-colvis text-center',
        headerClassName: 'dt-select-column no-colvis text-center',
        orderable: false,
        searchable: false,
        defaultContent: '',
        isSelectionColumn: true,
        render: (_value, _type, row) => {
            const id = getRowId(row, rowIdField);
            const checked = id && selectedIds.has(id) ? ' checked' : '';
            const disabled = id ? '' : ' disabled';
            return `<input type="checkbox" class="form-check-input dt-row-select" value="${escapeAttr(id)}"${checked}${disabled} aria-label="행 선택">`;
        },
    };
}

function getRowId(row = {}, rowIdField = 'id') {
    if (!row || typeof row !== 'object') return '';

    if (typeof rowIdField === 'function') {
        return String(rowIdField(row) ?? '').trim();
    }

    return String(row[rowIdField] ?? row.id ?? '').trim();
}

function shiftOrderForSelection(defaultOrder = [], shouldShift = false) {
    if (!shouldShift || !Array.isArray(defaultOrder)) {
        return defaultOrder;
    }

    return defaultOrder.map((item) => {
        if (!Array.isArray(item) || typeof item[0] !== 'number') {
            return item;
        }

        return [item[0] + 1, ...item.slice(1)];
    });
}

function scheduleAdjust(table, options = {}) {
    if (!table) return;

    const {
        draw = false
    } = options;

    const node = table.table().node();
    if (!node) return;

    let state = __dtAdjustState.get(node);
    if (!state) {
        state = {
            raf: null
        };
        __dtAdjustState.set(node, state);
    }

    if (state.raf) {
        cancelAnimationFrame(state.raf);
        state.raf = null;
    }

    state.raf = requestAnimationFrame(() => {
        try {
            table.columns.adjust();
            syncScrollHeadWidth(table);

            if (draw) {
                table.draw(false);
                syncScrollHeadWidth(table);
            }
        } catch (err) {
            console.error('[data-table] scheduleAdjust failed:', err);
        }
    });
}

function syncScrollHeadWidth(table) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper) return;

    const bodyTable = wrapper.querySelector('.dataTables_scrollBody table.dataTable');
    const headInner = wrapper.querySelector('.dataTables_scrollHeadInner');
    const headTable = wrapper.querySelector('.dataTables_scrollHead table.dataTable');
    if (!bodyTable || !headInner || !headTable) return;

    const width = Math.ceil(bodyTable.getBoundingClientRect().width);
    if (!Number.isFinite(width) || width <= 0) return;

    headInner.style.width = `${width}px`;
    headInner.style.paddingRight = '0px';
    headTable.style.width = `${width}px`;
}

function findScrollParent(node) {
    let current = node?.parentElement || null;
    while (current && current !== document.body && current !== document.documentElement) {
        const style = window.getComputedStyle(current);
        if (/(auto|scroll)/.test(style.overflowY || '')) {
            return current;
        }
        current = current.parentElement;
    }

    return null;
}

function updateStickyHeaderOffset(table) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper) return;

    const nav = document.querySelector('.top-nav.fixed-top, .top-nav');
    const navBottom = nav ? nav.getBoundingClientRect().bottom : 0;
    const scrollParent = findScrollParent(wrapper);
    const scrollTop = scrollParent ? scrollParent.getBoundingClientRect().top : 0;
    const offset = Math.max(0, Math.ceil(navBottom - scrollTop));
    const toolbar = wrapper.querySelector('.dt-top');
    const toolbarHeight = toolbar ? Math.ceil(toolbar.getBoundingClientRect().height) : 0;

    wrapper.style.setProperty('--dt-sticky-top', `${offset}px`);
    wrapper.style.setProperty('--dt-sticky-header-top', `${offset + toolbarHeight}px`);
}

function toCamelCase(value) {
    return String(value || '').replace(/-([a-z0-9])/g, (_, ch) => ch.toUpperCase());
}

function stripHtml(value) {
    const text = String(value ?? '');
    if (!text.includes('<')) {
        return text.trim();
    }

    const div = document.createElement('div');
    div.innerHTML = text;
    return div.textContent.trim();
}

function findTargetSearchCondition(tableNode, explicitTableId) {
    const tableDomId = tableNode?.id || '';
    const baseId = tableDomId.replace(/-table$/, '');
    const candidates = [
        explicitTableId,
        baseId,
        toCamelCase(baseId)
    ].filter(Boolean);

    for (const id of candidates) {
        const container = document.getElementById(`${id}SearchConditions`);
        const condition = getTargetConditionFromContainer(container);
        if (condition) return condition;
    }

    const scope = tableNode?.closest?.('.content-area, .card-body, main, body') || document;
    return getTargetConditionFromContainer(scope);
}

function getTargetConditionFromContainer(container) {
    if (!container) return null;

    const conditions = Array.from(container.querySelectorAll('.search-condition'));
    if (!conditions.length) return null;

    const active = document.activeElement?.closest?.('.search-condition');
    if (active && conditions.includes(active)) {
        return active;
    }

    const empty = conditions.find((condition) => {
        const input = condition.querySelector('input[type="text"], .search-input, input[name="searchValue[]"]');
        return input && String(input.value || '').trim() === '';
    });

    return empty || conditions[conditions.length - 1];
}

function bindCellSearchFill(table, tableSelector, options = {}) {
    if (!table || options === false) return;
    if (typeof options === 'object' && options.enabled === false) return;

    const $ = window.jQuery;
    const $table = $(tableSelector);
    const tableNode = $table.get(0);
    const explicitTableId = typeof options === 'object' ? options.tableId : null;

    $table.find('tbody')
        .off('click.dtCellSearchFill')
        .on('click.dtCellSearchFill', 'td', function (event) {
            if (event.target.closest('a, button, input, select, textarea, .dropdown-menu, .reorder-handle, .drag-handle')) {
                return;
            }

            const cell = table.cell(this);
            const index = cell.index();
            if (!index) return;

            const field = table.column(index.column).dataSrc();
            if (!field || typeof field !== 'string') return;

            const column = table.settings()?.[0]?.aoColumns?.[index.column];
            if (column?.bSearchable === false) return;

            const condition = findTargetSearchCondition(tableNode, explicitTableId);
            if (!condition) return;

            const select = condition.querySelector('select');
            const input = condition.querySelector('input[type="text"], .search-input, input[name="searchValue[]"]');
            if (!select || !input) return;

            const hasOption = Array.from(select.options).some((option) => option.value === field);
            if (!hasOption) return;

            select.value = field;
            input.value = stripHtml(cell.data());
        });
}

function bindSelectionColumn(table, tableSelector, selectedIds, rowIdField) {
    const $ = window.jQuery;
    const $table = $(tableSelector);
    const tableNode = $table.get(0);

    function updateDeleteButton() {
        const wrapper = table.table().container();
        wrapper?.querySelectorAll('.dt-soft-delete-btn').forEach((button) => {
            button.classList.toggle('disabled', selectedIds.size === 0);
            button.setAttribute('aria-disabled', selectedIds.size === 0 ? 'true' : 'false');
        });
    }

    function syncVisibleCheckboxes() {
        const visibleRows = table.rows({ page: 'current' }).nodes().toArray();
        const checkboxes = visibleRows
            .map((row) => row.querySelector('.dt-row-select'))
            .filter(Boolean);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = checkbox.value !== '' && selectedIds.has(checkbox.value);
        });

        const checkAll = table.table().header()?.querySelector('.dt-select-all');
        if (checkAll) {
            const enabled = checkboxes.filter((checkbox) => !checkbox.disabled);
            const checkedCount = enabled.filter((checkbox) => checkbox.checked).length;
            checkAll.checked = enabled.length > 0 && checkedCount === enabled.length;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < enabled.length;
        }

        updateDeleteButton();
    }

    $table.find('tbody')
        .off('change.dtSelectionColumn')
        .on('change.dtSelectionColumn', '.dt-row-select', function () {
            if (tableNode?.dataset.dtDeleting === 'true') {
                this.checked = this.value !== '' && selectedIds.has(this.value);
                return;
            }
            if (!this.value) return;

            if (this.checked) {
                selectedIds.add(this.value);
            } else {
                selectedIds.delete(this.value);
            }

            tableNode?.dispatchEvent(new CustomEvent('datatable:selection-changed', {
                bubbles: true,
                detail: {
                    table,
                    tableSelector,
                    ids: Array.from(selectedIds),
                    selectedIds,
                },
            }));

            syncVisibleCheckboxes();
        });

    $(table.table().header())
        .off('change.dtSelectionColumn')
        .on('change.dtSelectionColumn', '.dt-select-all', function () {
            if (tableNode?.dataset.dtDeleting === 'true') {
                syncVisibleCheckboxes();
                return;
            }
            const checked = this.checked;
            table.rows({ page: 'current' }).every(function () {
                const row = this.data();
                const id = getRowId(row, rowIdField);
                if (!id) return;

                if (checked) {
                    selectedIds.add(id);
                } else {
                    selectedIds.delete(id);
                }
            });

            tableNode?.dispatchEvent(new CustomEvent('datatable:selection-changed', {
                bubbles: true,
                detail: {
                    table,
                    tableSelector,
                    ids: Array.from(selectedIds),
                    selectedIds,
                },
            }));

            syncVisibleCheckboxes();
        });

    table.on('draw.dt xhr.dt', syncVisibleCheckboxes);
    syncVisibleCheckboxes();

    table.getSelectedIds = () => Array.from(selectedIds);
    table.clearSelectedIds = () => {
        selectedIds.clear();
        syncVisibleCheckboxes();
    };
    table.setSelectedIds = (ids = []) => {
        selectedIds.clear();
        ids.forEach((id) => {
            const value = String(id ?? '').trim();
            if (value) selectedIds.add(value);
        });
        syncVisibleCheckboxes();
        tableNode?.dispatchEvent(new CustomEvent('datatable:selection-changed', {
            bubbles: true,
            detail: {
                table,
                tableSelector,
                ids: Array.from(selectedIds),
                selectedIds,
            },
        }));
    };
}

export function createDataTable(config) {
    const {
        tableSelector,
        api,
        columns,
        buttons = [],
        defaultOrder = [[0, 'desc']],
        pageLength = DEFAULT_PAGE_LENGTH,
        responsive = false,
        autoWidth = true,
        ajaxData = null,
        dataSrc = null,
        cellSearchFill = true,
        searchTableId = null,
        rowReorder = false,
        initialData = null,
        density = null,
        scrollX = false,
        paging = true,
        searching = true,
        info = true,
        showColumnVisibility = true,
        showCopyButton = true,
        selectable = true,
        rowIdField = 'id',
        deleteButton = true,
        deleteApi = null,
        bulkDelete = false
    } = config;

    const $ = window.jQuery;
    const normalizedPageLength = PAGE_LENGTH_MENU.includes(Number(pageLength))
        ? Number(pageLength)
        : DEFAULT_PAGE_LENGTH;
    const selectedIds = new Set();
    const sourceColumns = Array.isArray(columns) ? columns : [];
    const shouldAddSelectionColumn = selectable !== false && !hasSelectionColumn(sourceColumns);
    const tableColumns = normalizeUtilityColumns(shouldAddSelectionColumn
        ? [createSelectionColumn(tableSelector, selectedIds, rowIdField), ...sourceColumns]
        : sourceColumns);
    ensureTableHeader(tableSelector, tableColumns);

    const dataTableConfig = {
        ...(api ? {
            ajax: {
            url: api,
            type: 'GET',
            cache: false,
            data: function (request) {
                if (typeof ajaxData === 'function') {
                    const result = ajaxData(request);

                    if (result && typeof result === 'object') {
                        return result;
                    }
                }

                return request;
            },
            dataSrc: function (json) {
                if (typeof dataSrc === 'function') {
                    const rows = dataSrc(json);
                    return Array.isArray(rows) ? rows : [];
                }

                return json.data ?? [];
            }
            },
        } : {
            data: Array.isArray(initialData) ? initialData : [],
        }),

        columns: tableColumns,
        order: shiftOrderForSelection(defaultOrder, shouldAddSelectionColumn),
        pageLength: normalizedPageLength,
        lengthMenu: PAGE_LENGTH_MENU,

        rowReorder: rowReorder ? {
            selector: 'td.reorder-handle',
            dataSrc: 'sort_no'
        } : false,

        scrollX,
        scrollCollapse: true,

        responsive,
        autoWidth,
        deferRender: true,
        paging,
        searching,
        info,
        processing: true,

        dom: '<"dt-top d-flex justify-content-end align-items-center gap-2"fBl>rt<"dt-bottom d-flex justify-content-between align-items-center"ip>',

        buttons: [
            ...(showColumnVisibility === false ? [] : [{
                extend: 'colvis',
                text: '열표시',
                className: 'btn btn-secondary btn-sm',
                popoverTitle: 'Column visibility',
                collectionLayout: 'fixed two-column',
                columns: function (index, data, node) {
                    return isColumnVisibleInColvis(tableColumns, index, node);
                }
            }]),
            ...(showCopyButton === false ? [] : [{
                extend: 'copy',
                text: '복사',
                className: 'btn btn-outline-secondary btn-sm'
            }]),
            ...(deleteButton === false ? [] : [{
                text: '삭제',
                className: 'btn btn-outline-danger btn-sm dt-soft-delete-btn',
                action: async function (_event, _dt, buttonNode) {
                    const ids = Array.from(selectedIds);
                    if (ids.length === 0) {
                        window.AppCore?.notify?.('warning', '삭제할 행을 선택하세요.');
                        return;
                    }

                    const handled = await softDeleteSelectedRows({
                        deleteApi,
                        ids,
                        table,
                        selectedIds,
                        buttonNode: buttonNode?.get?.(0) || buttonNode?.[0] || null,
                        bulkDelete,
                    });

                    if (handled) {
                        return;
                    }

                    const tableNode = document.querySelector(tableSelector);

                    tableNode?.dispatchEvent(new CustomEvent('datatable:delete-selected', {
                        bubbles: true,
                        detail: {
                            table,
                            tableSelector,
                            ids,
                            selectedIds,
                        },
                    }));
                },
            }]),
            ...buttons
        ],

        language: {
            lengthMenu: '페이지당 _MENU_ 개씩 보기',
            zeroRecords: '데이터 없음',
            info: '_PAGE_ / _PAGES_ 페이지',
            infoEmpty: '데이터 없음',
            infoFiltered: '',
            search: '검색',
            paginate: {
                next: '다음',
                previous: '이전'
            }
        },

        initComplete: function () {
            const api = this.api();

            applyColumnHeaderClasses(api, tableColumns);
            updateStickyHeaderOffset(api);
            api.columns.adjust();
            syncScrollHeadWidth(api);
        }
    };

    const table = $(tableSelector).DataTable(dataTableConfig);
    updateStickyHeaderOffset(table);
    syncScrollHeadWidth(table);

    tableColumns.forEach((column, index) => {
        if (column?.visible === false) {
            table.column(index).visible(false, false);
        }
    });
    if (tableColumns.some((column) => column?.visible === false)) {
        table.columns.adjust().draw(false);
    }

    if (density) {
        table.table().container()?.classList.add(`dt-density-${density}`);
    }

    table.on('xhr.dt draw.dt', function () {
        updateStickyHeaderOffset(table);
        syncScrollHeadWidth(table);
        const info = table.page.info();
        const el = document.querySelector(`${tableSelector}_wrapper .dataTables_info`);
        if (el) {
            el.innerHTML =
                `${info.page + 1} / ${info.pages || 1} 페이지 ` +
                `(총 ${info.recordsTotal}건 / 검색 ${info.recordsDisplay}건)`;
        }

    });

    table.on('column-visibility.dt responsive-resize.dt', function () {
        updateStickyHeaderOffset(table);
        applyColumnHeaderClasses(table, tableColumns);
        scheduleAdjust(table, {
            draw: false
        });
    });

    window.addEventListener('resize', () => {
        updateStickyHeaderOffset(table);
        scheduleAdjust(table, { draw: false });
    }, { passive: true });

    bindCellSearchFill(table, tableSelector, {
        tableId: searchTableId,
        enabled: cellSearchFill
    });

    if (shouldAddSelectionColumn) {
        bindSelectionColumn(table, tableSelector, selectedIds, rowIdField);
    }

    bindTableHighlight(tableSelector, table);

    return table;
}

export function bindTableHighlight(tableSelector, tableInstance) {
    const $table = $(tableSelector);
    $table.addClass('table-cross-highlight');
    const $wrapper = $table.closest('.dataTables_wrapper');

    $table.find('tbody')
        .off('click.dtTableHighlight')
        .on('click.dtTableHighlight', 'tr', function (event) {
        if (event.target.closest('a, button, input, select, textarea, .dropdown-menu, .reorder-handle, .drag-handle')) {
            return;
        }

        setTableSelectedRow(tableSelector, this);
    });

    $table.find('tbody')
        .off('mouseenter.dtTableHighlight')
        .on('mouseenter.dtTableHighlight', 'td', function () {
        const cell = tableInstance.cell(this);
        const idx = cell.index();

        if (!idx) return;

        const colIndex = idx.column;
        const visibleIndex = tableInstance.column(colIndex).index('visible');
        $wrapper.find('tbody tr').removeClass('row-highlight');
        $(this).closest('tr').addClass('row-highlight');

        $wrapper.find('td, th').removeClass('col-highlight');

        tableInstance.cells(null, colIndex).nodes().each(function (node) {
            $(node).addClass('col-highlight');
        });

        const headerNode = tableInstance.column(colIndex).header();
        $(headerNode).addClass('col-highlight');

        $wrapper
            .find('.dataTables_scrollHead th')
            .eq(visibleIndex)
            .addClass('col-highlight');
    });

    $table.find('tbody')
        .off('mouseleave.dtTableHighlight')
        .on('mouseleave.dtTableHighlight', function () {
        const $wrapper = $table.closest('.dataTables_wrapper');

        $wrapper.find('tbody tr').removeClass('row-highlight');
        $wrapper.find('td, th').removeClass('col-highlight');
    });
}

export function setTableSelectedRow(tableSelector, row) {
    const $table = $(tableSelector);
    const $wrapper = $table.closest('.dataTables_wrapper');

    $wrapper.find('tbody tr').removeClass('dt-row-selected table-active');

    if (row) {
        $(row).addClass('dt-row-selected');
    }
}

export function clearTableSelectedRows(tableSelector) {
    setTableSelectedRow(tableSelector, null);
}
