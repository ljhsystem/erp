// Path: /assets/js/components/data-table.js

const __dtAdjustState = new WeakMap();
const __dtHeaderSyncState = new WeakMap();

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

function syncHeaderToBodyWidths(table) {
    if (!table) return;

    const wrapper = table.table().container();
    if (!wrapper) return;

    const scrollHeadInner = wrapper.querySelector('.dataTables_scrollHeadInner');
    const scrollHeadTable = wrapper.querySelector('.dataTables_scrollHead table.dataTable');
    const allScrollHeadCols = Array.from(wrapper.querySelectorAll('.dataTables_scrollHead colgroup col'));
    const allScrollHeaders = Array.from(wrapper.querySelectorAll('.dataTables_scrollHead thead th'));
    const visibleColumnIndexes = table.columns(':visible').indexes().toArray();
    const scrollHeaders = allScrollHeaders.filter(isVisibleElement);
    const scrollHeadCols = allScrollHeadCols.length === visibleColumnIndexes.length
        ? allScrollHeadCols
        : visibleColumnIndexes.map((index) => allScrollHeadCols[index]).filter(Boolean);
    const bodyTable = wrapper.querySelector('.dataTables_scrollBody table.dataTable');
    const firstBodyRow = wrapper.querySelector('.dataTables_scrollBody tbody tr:not(.child)');

    if (!scrollHeadTable || !bodyTable || !firstBodyRow) return;

    const bodyCells = Array.from(firstBodyRow.children)
        .filter((cell) => cell && cell.offsetParent !== null);

    if (bodyCells.length === 0 || bodyCells.length !== scrollHeaders.length) return;

    const widths = bodyCells.map((cell) => cell.getBoundingClientRect().width);
    const bodyTableWidth = bodyTable.getBoundingClientRect().width;

    if (!Number.isFinite(bodyTableWidth) || bodyTableWidth <= 0) return;

    scrollHeadTable.style.width = bodyTableWidth + 'px';

    if (scrollHeadInner) {
        scrollHeadInner.style.width = bodyTableWidth + 'px';
    }

    widths.forEach((width, index) => {
        if (!Number.isFinite(width) || width <= 0) return;

        const px = width + 'px';
        const header = scrollHeaders[index];
        const col = scrollHeadCols[index];

        if (header) {
            header.style.width = px;
            header.style.minWidth = px;
            header.style.maxWidth = px;
        }

        if (col) {
            col.style.width = px;
        }
    });
}

function isVisibleElement(element) {
    if (!element) return false;

    const style = window.getComputedStyle(element);
    return style.display !== 'none' && style.visibility !== 'hidden';
}

function scheduleHeaderBodySync(table) {
    if (!table) return;

    const node = table.table().node();
    if (!node) return;

    const prev = __dtHeaderSyncState.get(node);
    if (prev) {
        cancelAnimationFrame(prev);
    }

    const raf = requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            try {
                syncHeaderToBodyWidths(table);
            } catch (err) {
                console.error('[data-table] syncHeaderToBodyWidths failed:', err);
            }
        });
    });

    __dtHeaderSyncState.set(node, raf);
}

function scheduleAdjust(table, options = {}) {
    if (!table) return;

    const {
        draw = false,
        delay = 40,
        repeatDelays = []
    } = options;

    const node = table.table().node();
    if (!node) return;

    let state = __dtAdjustState.get(node);
    if (!state) {
        state = {
            timer: null,
            raf: null
        };
        __dtAdjustState.set(node, state);
    }

    if (state.timer) {
        clearTimeout(state.timer);
        state.timer = null;
    }

    if (state.raf) {
        cancelAnimationFrame(state.raf);
        state.raf = null;
    }

    state.timer = setTimeout(() => {
        state.raf = requestAnimationFrame(() => {
            try {
                table.columns.adjust();

                if (draw) {
                    table.draw(false);
                }

                scheduleHeaderBodySync(table);
            } catch (err) {
                console.error('[data-table] scheduleAdjust failed:', err);
            }
        });
    }, delay);

    repeatDelays.forEach((repeatDelay) => {
        setTimeout(() => {
            try {
                table.columns.adjust();

                if (draw) {
                    table.draw(false);
                }

                scheduleHeaderBodySync(table);
            } catch (err) {
                console.error('[data-table] repeated scheduleAdjust failed:', err);
            }
        }, repeatDelay);
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
        dataSrc = null
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
        lengthMenu: [10, 20, 30, 50],

        rowReorder: {
            selector: 'td.reorder-handle',
            dataSrc: 'sort_no'
        },

        scrollX: true,
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
            scheduleAdjust(api, {
                draw: false,
                delay: 40,
                repeatDelays: [120, 260]
            });
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

        scheduleAdjust(table, {
            draw: false,
            delay: 30,
            repeatDelays: [120]
        });
    });

    table.on('column-visibility.dt responsive-resize.dt', function () {
        applyColumnHeaderClasses(table, tableColumns);
        scheduleAdjust(table, {
            draw: false,
            delay: 40,
            repeatDelays: [120, 260, 500]
        });
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
