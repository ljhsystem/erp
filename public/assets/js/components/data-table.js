// 경로: /assets/js/components/data-table.js

/* =========================================================
   DataTable 생성 (단일 기준 / 안정화 버전)
   - 핵심:
     1) columns.adjust() 남발 금지
     2) draw(false) 연속 호출 금지
     3) scroll 높이 변경과 column width 재계산 분리
     4) animation 중에는 높이만 갱신, 마지막에 1번만 adjust
========================================================= */

const __dtAdjustState = new WeakMap();

/* =========================================================
   내부 유틸: adjust 스케줄링
========================================================= */
function scheduleAdjust(table, options = {}) {
    if (!table) return;

    const {
        draw = false,
        delay = 40
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
            } catch (err) {
                console.error('[data-table] scheduleAdjust failed:', err);
            }
        });
    }, delay);
}

/* =========================================================
   내부 유틸: scroll body 높이만 반영
   - width 재계산(columns.adjust)은 여기서 직접 하지 않음
========================================================= */
function applyScrollHeight(table, tableSelector) {
    if (!table) return;

    const height = getTableHeight(tableSelector);
    const settings = table.settings()?.[0];
    if (!settings) return;

    settings.oScroll.sY = height + 'px';

    const wrapper = table.table().container();
    if (!wrapper) return;

    const scrollBody = wrapper.querySelector('.dataTables_scrollBody');
    if (scrollBody) {
        scrollBody.style.height = height + 'px';
        scrollBody.style.maxHeight = height + 'px';
    }

    const tableParent = table.table().node()?.parentNode;
    if (tableParent) {
        tableParent.style.height = height + 'px';
    }
}

/* =========================================================
   DataTable 생성
========================================================= */
export function createDataTable(config){

    const {
        tableSelector,
        api,
        columns,
        buttons = [],
        defaultOrder = [[1, "asc"]],
        pageLength = 10
    } = config;

    const $ = window.jQuery;

    const table = $(tableSelector).DataTable({

        ajax: {
            url: api,
            type: "GET",
            cache: false,
            dataSrc: function(json){
                //console.log("🔥 raw json:", json);
                return json.data ?? [];
            }
        },

        columns,
        order: defaultOrder,
        pageLength,
        lengthMenu: [5,10,20,50,100],

        rowReorder: {
            selector: 'td.reorder-handle',
            dataSrc: 'code'
        },

        scrollY: getTableHeight(tableSelector),
        scrollX: true,
        scrollCollapse: true,

        responsive: true,
        autoWidth: false,
        deferRender: true,
        paging: true,
        processing: true,

        /* 🔥 ERP 표준 구조 */
        dom: '<"dt-top d-flex justify-content-end align-items-center gap-2"fBl>rt<"dt-bottom d-flex justify-content-between align-items-center"ip>',

        buttons: [
            {
                extend: "colvis",
                text: "열표시",
                className: "btn btn-secondary btn-sm",
                popoverTitle: 'Column visibility',
                collectionLayout: 'fixed two-column',
                columns: ':not(.no-colvis)'
            },
            {
                extend: "copy",
                text: "복사",
                className: "btn btn-outline-secondary btn-sm"
            },
            ...buttons
        ],

        language: {
            lengthMenu: "페이지당 _MENU_ 개씩 보기",
            zeroRecords: "데이터 없음",
            info: "_PAGE_ / _PAGES_ 페이지",
            infoEmpty: "데이터 없음",
            infoFiltered: "",
            search: "검색",
            paginate: {
                next: "다음",
                previous: "이전"
            }
        },

        /* =====================================================
           최초 렌더 완료 후 한 번만 안정화
        ===================================================== */
        initComplete: function () {
            const api = this.api();

            applyScrollHeight(api, tableSelector);
            scheduleAdjust(api, {
                draw: false,
                delay: 60
            });
        }
    });

    /* =========================================================
       정보 영역 커스터마이징
    ========================================================= */
    table.on('xhr.dt draw.dt', function(){

        const info = table.page.info();
        const el = document.querySelector(`${tableSelector}_wrapper .dataTables_info`);
        if(!el) return;

        el.innerHTML =
            `${info.page + 1} / ${info.pages || 1} 페이지 ` +
            `(총 ${info.recordsTotal}건 / 검색 ${info.recordsDisplay}건)`;
    });

    /* =========================================================
       컬럼 보이기/숨기기 후 안정화
    ========================================================= */
    table.on('column-visibility.dt responsive-resize.dt', function () {
        applyScrollHeight(table, tableSelector);
        scheduleAdjust(table, {
            draw: false,
            delay: 50
        });
    });

    return table;
}

/* =========================================================
   높이 자동조절 (layout 통합됨)
========================================================= */
export function getTableHeight(tableSelector){

    const table = document.querySelector(tableSelector);
    if(!table) return 300;

    const box = table.closest('.table-box');
    if(!box) return 300;

    const rect = box.getBoundingClientRect();

    const searchContainer = box
        .closest('.content-area')
        ?.querySelector('.search-form-container');

    const isCollapsed = searchContainer?.classList.contains('collapsed');

    const bottomGap = isCollapsed ? 187 : 187;

    return window.innerHeight - rect.top - bottomGap;
}

/* =========================================================
   높이 변경
   - 높이만 반영
   - adjust는 debounce 처리
========================================================= */
export function updateTableHeight(table, tableSelector){

    if(!table) return;

    applyScrollHeight(table, tableSelector);

    scheduleAdjust(table, {
        draw: false,
        delay: 50
    });
}

/* =========================================================
   강제 동기화
   - 외부 레이아웃 변화 후 최종 1회 보정
========================================================= */
export function forceTableHeightSync(table, tableSelector){

    if(!table) return;

    applyScrollHeight(table, tableSelector);

    scheduleAdjust(table, {
        draw: false,
        delay: 80
    });
}

/* =========================================================
   검색폼 애니메이션 대응
   - 애니메이션 중에는 높이만 갱신
   - 마지막에 한 번만 adjust
========================================================= */
export function animateSearchFormRelayout(table, tableSelector, duration = 320){

    if(!table) return;

    const start = performance.now();

    function frame(now){

        applyScrollHeight(table, tableSelector);

        if(now - start < duration){
            requestAnimationFrame(frame);
            return;
        }

        scheduleAdjust(table, {
            draw: false,
            delay: 60
        });
    }

    requestAnimationFrame(frame);
}

/* =========================================================
   행/열 하이라이트
========================================================= */
export function bindTableHighlight(tableSelector, tableInstance){

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