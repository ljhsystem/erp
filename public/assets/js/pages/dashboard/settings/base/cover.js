/**
 * 커버이미지 설정 JS
 * 경로: /public/assets/js/pages/dashboard/settings/base/cover.js
 *
 * 목표:
 * - 기존 기능 100% 유지
 * - client.js / project.js 스타일로 구조 재정리
 * - 공용 search/table 구조와도 호환
 */

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createDataTable,
    updateTableHeight,
    forceTableHeightSync,
    bindTableHighlight
} from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.cover.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    console.log('[base-cover.js] loaded');

    /* =========================================================
    * API 정의 (🔥 라우터 100% 일치)
    * ========================================================= */
    const API = {

        /* 목록 */
        LIST: "/api/settings/base-info/cover/list",

        /* 오픈목록 */
        PUBLIC_LIST: "/api/settings/base-info/cover/public",

        /* 단건 */
        DETAIL: "/api/settings/base-info/cover/detail",

        /* 저장 */
        SAVE: "/api/settings/base-info/cover/save",

        /* 삭제 (소프트) */
        DELETE: "/api/settings/base-info/cover/delete",

        /* 휴지통 */
        TRASH: "/api/settings/base-info/cover/trash",

        /* 복원 */
        RESTORE: "/api/settings/base-info/cover/restore",
        RESTORE_BULK: "/api/settings/base-info/cover/restore-bulk",
        RESTORE_ALL: "/api/settings/base-info/cover/restore-all",

        /* 영구삭제 */
        PURGE: "/api/settings/base-info/cover/purge",
        PURGE_BULK: "/api/settings/base-info/cover/purge-bulk",
        PURGE_ALL: "/api/settings/base-info/cover/purge-all",

        /* 순서 */
        REORDER: "/api/settings/base-info/cover/reorder"
    };

    /* =========================================================
       컬럼맵
    ========================================================= */
    const COVER_COLUMN_MAP = {
        code:        { label: "코드", visible: true },
        url:         { label: "이미지(View)", visible: true },
        year:        { label: "해당년도(Year)", visible: true },
        title:       { label: "타이틀(Title)", visible: true },
        alt:         { label: "이미지문구(Alt)", visible: true },
        description: { label: "설명(Description)", visible: true }
    };

    const DATE_OPTIONS = [
        { value: 'year', label: '년도' }
    ];

    let coverTable = null;
    let coverModal = null;
    let coverTrashModal = null;
    let coverTrashTable = null;
    let todayPicker = null;
    let selectedTrashDetailId = null;
    let globalBound = false;

    /* =========================================================
       DOM 호환 셀렉터
       - 구형 cover.php / 공용 ui-search, ui-table 둘 다 대응
    ========================================================= */
    const DOM = {
        table: pickSelector(['#cover-table', '#cover-image-table']),
        modal: pickSelector(['#coverModal', '#coverImageModal']),
        form: pickSelector(['#cover-form', '#cover-image-form']),

        searchForm: pickSelector(['#coverSearchConditionsForm', '#searchConditionsForm']),
        toggleSearch: pickSelector(['#coverToggleSearchForm', '#toggleSearchForm']),
        searchContainer: pickSelector(['#coverSearchFormContainer', '#searchFormContainer']),
        searchBody: pickSelector(['#coverSearchFormBody', '#searchFormBody']),
        searchConditions: pickSelector(['#coverSearchConditions', '#searchConditions']),
        addCondition: pickSelector(['#coverAddSearchCondition', '#addSearchCondition']),
        resetButton: pickSelector(['#coverResetButton', '#resetButton']),
        searchButton: pickSelector(['#coverSearchButton', '#searchButton']),
        dateType: pickSelector(['#coverDateType', '#dateType']),

        tooltipTrigger: pickSelector(['#coverTooltipTrigger', '#tooltipTrigger']),
        tooltipContainer: pickSelector(['#coverTooltipContainer', '#tooltipContainer']),
        periodTooltipTrigger: pickSelector(['#coverPeriodTooltipTrigger', '#periodTooltipTrigger']),
        periodTooltipContainer: pickSelector(['#coverPeriodTooltipContainer', '#periodTooltipContainer']),

        trashModal: '#coverTrashModal',
        trashTable: '#cover-trash-table',
        trashCheckAll: '#coverTrashCheckAll',
        trashDetail: '#cover-trash-detail',

        originalImageModal: '#originalImageModal',
        originalImageView: '#original-image-view',

        modalId: '#modal_cover_id',
        modalYear: '#modal_year',
        modalTitle: '#modal_title',
        modalAlt: '#modal_alt',
        modalDescription: '#modal_description',
        modalImageFile: '#modal_cover_image',
        modalImagePreview: '#modal-image-preview',
        modalDeleteBtn: '#modal_delete_btn',
        modalSaveBtn: '#modal_save_btn',
        modalLabel: '#coverImageModalLabel'
    };



    /* =========================================================
       DOM READY
    ========================================================= */
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('jQuery not loaded');
            return;
        }

        const $ = window.jQuery;
        initCoverPage($);
    });

    window.TrashColumns = window.TrashColumns || {};

    /* =========================================================
       PAGE INIT
    ========================================================= */
    function initCoverPage($) {
        initModal();
        initAdminDatePicker();
        initDataTable($);
        initTrashColumns();
    
        bindRowReorder(coverTable, { api: API.REORDER });  // 행 드래그 정렬
        bindTableEvents($);
        bindModalEvents($);
        bindSearchCollapseEvents();

        bindTableLayoutEvents(coverTable, DOM.table);
    
        bindTooltipEvents();
        bindTrashEvents();
        bindExcelEvents();
    
        populateCoverYearOptions();
    }

    /* =========================================================
       공통 유틸
    ========================================================= */
    function pickSelector(list) {
        for (const selector of list) {
            if (document.querySelector(selector)) {
                return selector;
            }
        }
        return list[0];
    }

    function qs(selector) {
        return document.querySelector(selector);
    }

    function qsa(selector) {
        return Array.from(document.querySelectorAll(selector));
    }

    function getSearchFieldSelector() {
        return `${DOM.searchConditions} .search-condition:first select, ${DOM.searchConditions} .search-condition:first .search-field`;
    }

    function getFirstSearchCondition() {
        return window.jQuery(`${DOM.searchConditions} .search-condition`).first();
    }

    function getDateStartInput() {
        return document.querySelector(`${DOM.searchForm} input[name="dateStart"]`)
            || document.querySelector(`input[name="dateStart"]`);
    }

    function getDateEndInput() {
        return document.querySelector(`${DOM.searchForm} input[name="dateEnd"]`)
            || document.querySelector(`input[name="dateEnd"]`);
    }

    /* =========================================================
       모달
    ========================================================= */
    function initModal() {
        const modalEl = qs(DOM.modal);
        if (!modalEl) return;

        coverModal = new bootstrap.Modal(modalEl, {
            focus: false,
            keyboard: true
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            const form = qs(DOM.form);
            if (form) form.reset();

            populateCoverYearOptions();
            setCoverModalMode('create');

            const preview = qs(DOM.modalImagePreview);
            if (preview) {
                preview.setAttribute('src', '');
                preview.style.display = 'none';
            }
        });

        const trashModalEl = qs(DOM.trashModal);
        if (trashModalEl) {
            coverTrashModal = new bootstrap.Modal(trashModalEl, {
                focus: false,
                keyboard: true
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (!modalEl) return;

            const isShown =
                modalEl.classList.contains('show') ||
                modalEl.getAttribute('aria-modal') === 'true' ||
                modalEl.style.display === 'block';

            if (!isShown) return;

            e.preventDefault();
            e.stopPropagation();

            if (coverModal) {
                coverModal.hide();
            }
        });
    }

    /* =========================================================
       년도 picker
    ========================================================= */
    function initAdminDatePicker() {
        if (todayPicker) return todayPicker;

        const container = document.getElementById('today-picker');
        if (!container) return null;

        todayPicker = AdminPicker.create({
            type: 'today',
            container
        });

        todayPicker.subscribe((_, date) => {
            const input = todayPicker.__target;
            if (!input || !date) return;

            input.value = String(date.getFullYear());

            normalizeStartEnd(
                input.name === 'dateStart' ? 'start' : 'end'
            );

            todayPicker.close();
        });

        return todayPicker;
    }



    /* =========================================================
       DataTable
    ========================================================= */
    function initDataTable($) {
        const columns = buildCoverColumns();

        coverTable = createDataTable({
            tableSelector: DOM.table,
            api: API.LIST,
            columns,
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            buttons: [
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: function () {
                        const trashModalEl = document.getElementById('coverTrashModal');
                        if (!trashModalEl) return;
                
                        /* 🔥 핵심: JS에서 API 세팅 (통일 규칙) */
                        trashModalEl.dataset.listUrl      = API.TRASH;
                        trashModalEl.dataset.restoreUrl   = API.RESTORE;
                        
                        trashModalEl.dataset.deleteUrl    = API.PURGE;
                        trashModalEl.dataset.deleteAllUrl = API.PURGE_ALL;
                
                        const modal = new bootstrap.Modal(trashModalEl);
                        modal.show();
                    }
                },
                {
                    text: '새 커버사진',
                    className: 'btn btn-warning btn-sm',
                    action: function () {
                        const form = qs(DOM.form);
                        if (form) form.reset();

                        populateCoverYearOptions();
                        setCoverModalMode('create');

                        const preview = qs(DOM.modalImagePreview);
                        if (preview) {
                            preview.setAttribute('src', '');
                            preview.style.display = 'none';
                        }

                        if (coverModal) {
                            coverModal.show();
                        }
                    }
                }
            ],
            ajaxData: function(d){
                const filters = window.__lastFilters || [];
                d.filters = filters;
            },
            dataSrc: json => json?.data ?? []
        });

        window.coverTable = coverTable;

        if (coverTable) {
            SearchForm({
                table: coverTable,
                apiList: API.LIST,
                tableId: 'cover',
                defaultSearchField: 'title',
                dateOptions: DATE_OPTIONS
            });

            bindTableHighlight(DOM.table, coverTable);
            updateTableHeight(coverTable, DOM.table);

            coverTable.on('init.dt', function () {
                syncSearchFieldOptionsFromTable();
            });
        }
    }

    function buildCoverColumns() {
        const columns = [];

        columns.push({
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
            width: '40px',
            className: 'reorder-handle no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>'
        });

        Object.entries(COVER_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                defaultContent: '',
                render: function (data, type) {
                    if (data === null || data === undefined) return '';

                    if (field === 'url' && type === 'display') {
                        return data
                            ? `<img src="${escapeHtmlAttr(data)}" class="table-img-preview" style="width:80px;cursor:pointer;">`
                            : `<span class="text-muted">이미지 없음</span>`;
                    }

                    return data;
                }
            });
        });

        return columns;
    }

    /* =========================================================
       테이블 이벤트
    ========================================================= */
    function bindTableEvents($) {
        let clickTimer = null;

        $(DOM.table + ' tbody').on('click', '.table-img-preview', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (clickTimer) {
                clearTimeout(clickTimer);
                clickTimer = null;
            }

            const src = $(this).attr('src');
            if (!src) return;

            $(DOM.originalImageView).attr('src', src);
            new bootstrap.Modal(qs(DOM.originalImageModal)).show();
        });

        $(DOM.table + ' tbody').on('click', 'td', function (e) {
            if ($(e.target).closest('.table-img-preview').length) return;
            if ($(this).hasClass('reorder-handle')) return;

            const td = this;
            const $tr = $(td).closest('tr');

            if ($tr.hasClass('child')) return;

            if (clickTimer) {
                clearTimeout(clickTimer);
                clickTimer = null;
            }

            clickTimer = setTimeout(() => {
                const rowData = coverTable.row($tr).data();
                if (!rowData) return;

                const cell = coverTable.cell(td);
                const idx = cell.index();
                if (!idx) return;

                const colIndex = idx.column;
                const column = coverTable.settings()[0].aoColumns[colIndex];
                const field = column?.data;

                if (!field || field === 'url') return;

                const value = rowData[field] ?? '';
                const $first = getFirstSearchCondition();
                if (!$first.length) return;

                $first.find('select, .search-field').val(field);
                $first.find('input[type="text"], .search-input').val(String(value).trim());
            }, 220);
        });

        $(DOM.table + ' tbody').on('dblclick', 'tr', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $tr = $(this);

            if ($tr.hasClass('child')) return;

            if (clickTimer) {
                clearTimeout(clickTimer);
                clickTimer = null;
            }

            const rowData = coverTable.row($tr).data();
            if (!rowData) return;

            const yearValue = String(rowData.year ?? '').trim();

            $(DOM.modalId).val(rowData.id || '');
            populateCoverYearOptions(yearValue);
            $(DOM.modalTitle).val(rowData.title || '');
            $(DOM.modalAlt).val(rowData.alt || '');
            $(DOM.modalDescription).val(rowData.description || '');

            if (rowData.url) {
                $(DOM.modalImagePreview).attr('src', rowData.url).show();
            } else {
                $(DOM.modalImagePreview).attr('src', '').hide();
            }

            setCoverModalMode('edit');

            if (coverModal) {
                coverModal.show();
            }

            setTimeout(() => {
                $(DOM.modalYear).val(yearValue).trigger('change');
            }, 0);
        });
    }

    /* =========================================================
       모달 이벤트
    ========================================================= */
    function bindModalEvents($) {
        $(document).off('change', DOM.modalImageFile);
        $(document).on('change', DOM.modalImageFile, function (e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = evt => {
                $(DOM.modalImagePreview)
                    .attr('src', evt.target.result)
                    .show();
            };
            reader.readAsDataURL(file);
        });

        $(document).off('submit', DOM.form);
        $(document).on('submit', DOM.form, function (e) {
            e.preventDefault();           

            const fileInput = qs(DOM.modalImageFile);
            const hasFile = !!(fileInput && fileInput.files && fileInput.files.length > 0);
            const coverId = $(DOM.modalId).val();

            if (!hasFile && !coverId) {
                alert('이미지 파일을 선택하세요.');
                return;
            }

            const fd = new FormData(this);

            $.ajax({
                url: API.SAVE,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (res) {
                    if (res && res.success) {
                        resetCoverAfterAction();

                        if (window.AppCore?.notify) {
                            AppCore.notify('success', '저장 완료');
                        }
                    } else {
                        alert('저장 실패: ' + (res?.message || '알 수 없는 오류'));
                    }
                },
                error: function (xhr) {
                    alert('저장 요청 실패: ' + xhr.status);
                }
            });
        });

        $(document).off('click', DOM.modalDeleteBtn);
        $(document).on('click', DOM.modalDeleteBtn, function () {
            const coverId = $(DOM.modalId).val();

            if (!coverId) {
                alert('삭제할 항목이 없습니다.');
                return;
            }

            if (!confirm('정말 삭제하시겠습니까?')) return;

            $.post(
                API.DELETE,
                { id: coverId },
                function (res) {
                    if (res && res.success) {
                        resetCoverAfterAction();

                        if (window.AppCore?.notify) {
                            AppCore.notify('success', '삭제 완료');
                        }
                    } else {
                        alert(res?.message || '삭제 실패');
                    }
                },
                'json'
            );
        });
    }

    /* =========================================================
       검색폼
    ========================================================= */
    function bindSearchCollapseEvents() {
        const container = qs(DOM.searchContainer);
        const body = qs(DOM.searchBody);
        const btn = qs(DOM.toggleSearch);

        if (!container || !body || !btn) return;

        btn.addEventListener('click', () => {
            body.classList.toggle('hidden');
            container.classList.toggle('collapsed');

            const hidden = body.classList.contains('hidden');
            btn.textContent = hidden ? '열기' : '접기';

            if (coverTable) {
                coverTable.page.len(hidden ? 100 : 10).draw(false);
                updateTableHeight(coverTable, DOM.table);
                coverTable.columns.adjust().draw(false);
            }

            animateSearchFormRelayout(320);

            setTimeout(() => {
                if (coverTable) {
                    forceTableHeightSync(coverTable, DOM.table);
                }
            }, 340);
        });
    }

    function syncSearchFieldOptionsFromTable() {
        const fields = getTableColumns();
        const $select = window.jQuery(getSearchFieldSelector()).first();
        if (!$select.length) return;

        $select.empty();

        fields.forEach(f => {
            const selected = (f.value === 'title') ? 'selected' : '';
            $select.append(`<option value="${f.value}" ${selected}>${f.label}</option>`);
        });
    }

    function getTableColumns() {
        const fields = [];
        if (!coverTable) return fields;

        const cols = coverTable.settings()[0].aoColumns;

        cols.forEach(col => {
            if (col.data === null) return;
            if (col.data === 'url') return;

            const label = window.jQuery(col.nTh).text().trim();
            if (!label) return;

            fields.push({
                value: col.data,
                label: label
            });
        });

        return fields;
    }

    function renderSearchSelect(selectedIndex = 0) {
        const fields = getTableColumns();
        if (!fields.length) return '';

        let html = `<select name="searchField[]" class="form-select form-select-sm search-field">`;

        fields.forEach((f, i) => {
            const sel = (i === selectedIndex) ? 'selected' : '';
            html += `<option value="${f.value}" ${sel}>${f.label}</option>`;
        });

        html += `</select>`;
        return html;
    }


    /* =========================================================
       레이아웃
    ========================================================= */
    function bindTableLayoutEvents(table, tableSelector) {
        if (!table) return;

        window.addEventListener('resize', () => {
            updateTableHeight(table, tableSelector);

            if (table) {
                table.columns.adjust().draw(false);
            }
        });

        document.addEventListener('sidebar:toggled', () => {
            updateTableHeight(table, tableSelector);

            if (table) {
                table.columns.adjust().draw(false);
            }

            setTimeout(() => {
                forceTableHeightSync(table, tableSelector);
            }, 340);
        });
    }

    function animateSearchFormRelayout(duration = 320) {
        if (!coverTable) return;

        const startedAt = performance.now();

        function frame(now) {
            updateTableHeight(coverTable, DOM.table);

            if (coverTable) {
                coverTable.columns.adjust();
            }

            if (now - startedAt < duration) {
                requestAnimationFrame(frame);
            }
        }

        requestAnimationFrame(frame);
    }

    /* =========================================================
       툴팁
    ========================================================= */
    function bindTooltipEvents() {
        setupTooltip(DOM.tooltipTrigger, DOM.tooltipContainer);
        setupTooltip(DOM.periodTooltipTrigger, DOM.periodTooltipContainer);
    }

    function setupTooltip(triggerSelector, tooltipSelector) {
        const trigger = qs(triggerSelector);
        const tooltip = qs(tooltipSelector);

        if (!trigger || !tooltip) return;

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();

            const isOpen = tooltip.style.display === 'block';

            qsa('.tooltip-container').forEach(t => {
                t.style.display = 'none';
            });

            tooltip.style.display = isOpen ? 'none' : 'block';
        });

        tooltip.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        document.addEventListener('click', function () {
            qsa('.tooltip-container').forEach(t => {
                t.style.display = 'none';
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                qsa('.tooltip-container').forEach(t => {
                    t.style.display = 'none';
                });
            }
        });
    }

    /* =========================================================
       휴지통
    ========================================================= */
    function bindTrashEvents() {

        // document.addEventListener('click', function (e) {

        //     const modal = e.target.closest('.modal');
        //     if (!modal) return;
        
        //     const layout = modal.querySelector('.trash-layout');
        //     const detail = modal.querySelector('.trash-right');
        //     const table  = modal.querySelector('.trash-table');
        
        //     if (!layout || !detail || !table) return;
        
        //     const row = e.target.closest('tbody tr');
        //     if (!row) return;
        
        //     if (!table.contains(row)) return;
        
        //     console.log('🔥 강제 열기 테스트');
        
        //     /* 🔥 무조건 열기 */
        //     layout.classList.add('open');
        //     detail.style.display = 'block';
        
        //     /* 🔥 내용 하드코딩 */
        //     detail.innerHTML = `
        //         <div style="padding:20px;">
        //             <h4>테스트 성공</h4>
        //             <p>사이드바 열림 확인</p>
        //         </div>
        //     `;
        
        // });




        document.addEventListener('trash:detail-render', function (e) {
            const { data, modal, type } = e.detail || {};
            if (type !== 'cover') return;
            if (!modal || modal.dataset.type !== 'cover') return;
    
            const detailBox = modal.querySelector('.trash-detail');
            if (!detailBox) return;
    
            detailBox.innerHTML = `
                <div class="cover-trash-detail-card">
                    <div class="cover-trash-detail-head">
                        <h6 class="cover-trash-detail-title">휴지통 상세정보</h6>
                        <button type="button" class="cover-trash-detail-close" id="btnCloseCoverTrashDetail">×</button>
                    </div>
    
                    <div class="cover-trash-preview">
                        ${
                            data?.url
                                ? `<img src="${escapeHtmlAttr(data.url)}" alt="cover preview">`
                                : `<div class="cover-trash-empty-preview">이미지 없음</div>`
                        }
                    </div>
    
                    <div class="cover-trash-detail-grid">
                        <div class="label">코드</div><div class="value">${escapeHtml(data?.code ?? '')}</div>
                        <div class="label">년도</div><div class="value">${escapeHtml(data?.year ?? '')}</div>
                        <div class="label">제목</div><div class="value">${escapeHtml(data?.title ?? '')}</div>
                        <div class="label">Alt</div><div class="value">${escapeHtml(data?.alt ?? '')}</div>
                        <div class="label">설명</div><div class="value">${escapeHtml(data?.description ?? '')}</div>
                        <div class="label">삭제일시</div><div class="value">${escapeHtml(data?.deleted_at ?? '')}</div>
                        <div class="label">삭제자</div><div class="value">${escapeHtml(data?.deleted_by_name || data?.deleted_by || '')}</div>
                    </div>
                </div>
            `;
        });
    
        document.addEventListener('trash:changed', (e) => {
            const { type } = e.detail || {};
            if (type === 'cover' && coverTable) {
                coverTable.ajax.reload(null, false);
            }
        });
    
        window.jQuery(document).off('click', '#btnCloseCoverTrashDetail');
        window.jQuery(document).on('click', '#btnCloseCoverTrashDetail', function (e) {
            e.preventDefault();
            e.stopPropagation();
    
            const modal = document.querySelector(DOM.trashModal);
            const layout = modal?.querySelector('.trash');
            const detail = modal?.querySelector('.trash-detail');
    
            if (layout) layout.classList.remove('open');
            if (detail) {
                detail.innerHTML = '';
                detail.style.display = 'none';
            }
    
            window.jQuery(`${DOM.trashTable} tbody tr`).removeClass('active');
        });
    }
























    

    function initTrashColumns() {
        window.TrashColumns = window.TrashColumns || {};
    
        window.TrashColumns.cover = function(row) {
            return `
                <td>${row.code ?? ''}</td>
                <td>
                    ${
                        row.url
                            ? `<img src="${escapeHtmlAttr(row.url)}" style="width:60px;height:60px;object-fit:cover;">`
                            : '<span class="text-muted">없음</span>'
                    }
                </td>
                <td>${row.year ?? ''}</td>
                <td>${row.title ?? ''}</td>
                <td>${row.deleted_at ?? ''}</td>
                <td>${row.deleted_by_name ?? ''}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">영구삭제</button>
                </td>
            `;
        };
    }




    
    /* =========================================================
       기타
    ========================================================= */
    function bindExcelEvents() {
        document.addEventListener('excel:uploaded', () => {
            if (coverTable) {
                coverTable.ajax.reload(null, false);
            }
        });
    }

    function reloadCoverTable(resetPaging = false) {
        if (!coverTable) return;

        coverTable.ajax.reload(() => {
            setTimeout(() => {
                updateTableHeight(coverTable, DOM.table);
                forceTableHeightSync(coverTable, DOM.table);

                try {
                    coverTable.columns.adjust().draw(false);
                } catch (e) {
                    console.warn('[cover] columns.adjust 경고', e);
                }
            }, 80);
        }, resetPaging);
    }

    function clearCoverSearchConditions() {
        window.jQuery(`${DOM.searchConditions} input[type='text']`).val('');

        window.jQuery(DOM.searchConditions)
            .find('.search-condition:gt(0)')
            .remove();

        const start = getDateStartInput();
        const end = getDateEndInput();

        if (start) start.value = '';
        if (end) end.value = '';

        const $firstSelect = window.jQuery(getSearchFieldSelector()).first();
        if ($firstSelect.length) {
            $firstSelect.val('title');
        }

        window.jQuery(`${DOM.searchConditions} .search-condition`).each(function (index) {
            const $btn = window.jQuery(this).find('.remove-condition');
            if (index === 0) {
                $btn.hide();
            } else {
                $btn.show();
            }
        });
    }
    function resetCoverAfterAction() {
        clearCoverSearchConditions();
        populateCoverYearOptions();
    
        if (coverModal) {
            coverModal.hide();
        }
    
        if (coverTable) {
            reloadCoverTable(true);
        }
    }

    function setCoverModalMode(mode = 'create') {
        const $title = window.jQuery(DOM.modalLabel);

        if (mode === 'edit') {
            $title.text('커버사진 수정');
            window.jQuery(DOM.modalSaveBtn).text('수정');
            window.jQuery(DOM.modalDeleteBtn).show();
            window.jQuery(DOM.modalImageFile).prop('required', false);
        } else {
            $title.text('새 커버사진 등록');
            window.jQuery(DOM.modalSaveBtn).text('등록');
            window.jQuery(DOM.modalDeleteBtn).hide();
            window.jQuery(DOM.modalImageFile).prop('required', true);
            window.jQuery(DOM.modalImagePreview).attr('src', '').hide();
            window.jQuery(DOM.modalId).val('');
        }
    }

    function populateCoverYearOptions(selectedYear = '') {
        const $select = window.jQuery(DOM.modalYear);
        if (!$select.length) return;

        const currentYear = new Date().getFullYear();
        const normalizedYear = String(selectedYear ?? '').trim();

        let startYear = currentYear - 50;
        let endYear = currentYear + 5;

        if (/^\d{4}$/.test(normalizedYear)) {
            const selectedNum = parseInt(normalizedYear, 10);

            if (selectedNum < startYear) {
                startYear = selectedNum;
            }

            if (selectedNum > endYear) {
                endYear = selectedNum;
            }
        }

        $select.empty();
        $select.append('<option value="">선택하세요</option>');

        for (let year = endYear; year >= startYear; year--) {
            const selected = String(year) === normalizedYear ? 'selected' : '';
            $select.append(`<option value="${year}" ${selected}>${year}</option>`);
        }

        $select.val(normalizedYear);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeHtmlAttr(value) {
        return escapeHtml(value);
    }
    
})();