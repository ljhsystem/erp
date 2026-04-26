// Path: /assets/js/components/data-table.js

const __dtAdjustState = new WeakMap();

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

            if (draw) {
                table.draw(false);
            }
        } catch (err) {
            console.error('[data-table] scheduleAdjust failed:', err);
        }
    });
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
            if (event.target.closest('a, button, input, select, textarea, .dropdown-menu, .reorder-handle')) {
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

export function createDataTable(config) {
    const {
        tableSelector,
        api,
        columns,
        buttons = [],
        defaultOrder = [[0, 'desc']],
        pageLength = 10,
        responsive = false,
        autoWidth = true,
        ajaxData = null,
        dataSrc = null,
        cellSearchFill = true,
        searchTableId = null
    } = config;

    const $ = window.jQuery;
    const tableColumns = Array.isArray(columns) ? columns : [];

    const table = $(tableSelector).DataTable({
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

        columns: tableColumns,
        order: defaultOrder,
        pageLength,
        lengthMenu: [10, 20, 30, 50, 100, 200, 300, 500, 1000],

        rowReorder: {
            selector: 'td.reorder-handle',
            dataSrc: 'sort_no'
        },

        scrollX: false,
        scrollCollapse: true,

        responsive,
        autoWidth,
        deferRender: true,
        paging: true,
        processing: true,

        dom: '<"dt-top d-flex justify-content-end align-items-center gap-2"fBl>rt<"dt-bottom d-flex justify-content-between align-items-center"ip>',

        buttons: [
            {
                extend: 'colvis',
                text: '열표시',
                className: 'btn btn-secondary btn-sm',
                popoverTitle: 'Column visibility',
                collectionLayout: 'fixed two-column',
                columns: ':not(.no-colvis)'
            },
            {
                extend: 'copy',
                text: '복사',
                className: 'btn btn-outline-secondary btn-sm'
            },
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
            api.columns.adjust();
        }
    });

    table.on('xhr.dt draw.dt', function () {
        const info = table.page.info();
        const el = document.querySelector(`${tableSelector}_wrapper .dataTables_info`);
        if (el) {
            el.innerHTML =
                `${info.page + 1} / ${info.pages || 1} 페이지 ` +
                `(총 ${info.recordsTotal}건 / 검색 ${info.recordsDisplay}건)`;
        }

    });

    table.on('column-visibility.dt responsive-resize.dt', function () {
        applyColumnHeaderClasses(table, tableColumns);
        scheduleAdjust(table, {
            draw: false
        });
    });

    bindCellSearchFill(table, tableSelector, {
        tableId: searchTableId,
        enabled: cellSearchFill
    });

    return table;
}

export function bindTableHighlight(tableSelector, tableInstance) {
    const $table = $(tableSelector);

    $table.find('tbody').on('mouseenter', 'td', function () {
        const cell = tableInstance.cell(this);
        const idx = cell.index();

        if (!idx) return;

        const colIndex = idx.column;
        const visibleIndex = tableInstance.column(colIndex).index('visible');
        const $wrapper = $table.closest('.dataTables_wrapper');

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

    $table.find('tbody').on('mouseleave', function () {
        const $wrapper = $table.closest('.dataTables_wrapper');

        $wrapper.find('tbody tr').removeClass('row-highlight');
        $wrapper.find('td, th').removeClass('col-highlight');
    });
}
