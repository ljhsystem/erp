/**
 * 기초정보관리 커버이미지 관리 페이지 JS
 * 경로: /public/assets/js/pages/dashboard/settings/base/cover.js
 *
 * 주요 역할
 * - 커버이미지 목록 DataTable 초기화
 * - 검색폼, 엑셀 업로드, 휴지통 공통 컴포넌트 연동
 * - 커버이미지 등록/수정/삭제 및 순서 변경 처리
 */

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createDataTable,
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
    * API 엔드포인트
    * ========================================================= */
    const API = {

        /* 커버이미지 목록 */
        LIST: "/api/settings/base-info/cover/list",

        /* 공개 커버이미지 목록 */
        PUBLIC_LIST: "/api/settings/base-info/cover/public",

        /* 커버이미지 상세 */
        DETAIL: "/api/settings/base-info/cover/detail",

        /* 저장 */
        SAVE: "/api/settings/base-info/cover/save",

        /* 삭제(휴지통 이동) */
        DELETE: "/api/settings/base-info/cover/delete",

        /* 휴지통 목록 */
        TRASH: "/api/settings/base-info/cover/trash",

        /* 복원 */
        RESTORE: "/api/settings/base-info/cover/restore",
        RESTORE_BULK: "/api/settings/base-info/cover/restore-bulk",
        RESTORE_ALL: "/api/settings/base-info/cover/restore-all",

        /* 영구 삭제 */
        PURGE: "/api/settings/base-info/cover/purge",
        PURGE_BULK: "/api/settings/base-info/cover/purge-bulk",
        PURGE_ALL: "/api/settings/base-info/cover/purge-all",

        /* 순서 변경 */
        REORDER: "/api/settings/base-info/cover/reorder"
    };

    /* =========================================================
       DataTable 컬럼 정의
    ========================================================= */
    const COVER_COLUMN_MAP = {
        sort_no:        { label: "순번", visible: true },
        url:         { label: "\uC774\uBBF8\uC9C0(View)", visible: true },
        year:        { label: "\uD574\uB2F9\uB144\uB3C4(Year)", visible: true },
        title:       { label: "\uD0C0\uC774\uD2C0(Title)", visible: true },
        alt:         { label: "\uC774\uBBF8\uC9C0\uBB38\uAD6C(Alt)", visible: true },
        description: { label: "\uC124\uBA85(Description)", visible: true },
        is_active:   { label: "\uC0C1\uD0DC", visible: true },
        created_at:  { label: "\uB4F1\uB85D\uC77C\uC2DC", visible: false },
        created_by:  { label: "\uB4F1\uB85D\uC790", visible: false },
        updated_at:  { label: "\uC218\uC815\uC77C\uC2DC", visible: false },
        updated_by:  { label: "\uC218\uC815\uC790", visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'year', label: '\uB144\uB3C4' }
    ];

    let coverTable = null;
    let coverModal = null;
    let coverTrashModal = null;
    let coverTrashTable = null;
    let yearMonthPicker = null;
    let selectedTrashDetailId = null;
    let globalBound = false;
    let yearMonthOpenTimer = null;
    let DOM = null;

    /* =========================================================
       DOM 선택자 매핑
       - cover.php와 공통 ui-search, ui-table 구조를 모두 지원한다.
    ========================================================= */
    function resolveDOM() {
        return {
            table: pickSelector(['#cover-table', '#cover-image-table']),
            modal: pickSelector(['#coverModal', '#coverImageModal']),
            form: pickSelector(['#cover-form', '#cover-image-form']),

            searchForm: pickSelector(['#coverSearchConditionsForm', '#searchConditionsForm']),
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
            modalIsActive: '#modal_is_active',
            modalImageFile: '#modal_cover_image',
            modalImagePreview: '#modal-image-preview',
            modalDeleteBtn: '#modal_delete_btn',
            modalSaveBtn: '#modal_save_btn',
            modalLabel: '#coverImageModalLabel'
        };
    }



    /* =========================================================
       DOM READY
    ========================================================= */
    function bootCoverPage() {
        if (!window.jQuery) return;

        initCoverPage(window.jQuery);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootCoverPage);
    } else {
        bootCoverPage();
    }

    window.TrashColumns = window.TrashColumns || {};

    /* =========================================================
       PAGE INIT
    ========================================================= */
    function initCoverPage($) {
        console.log('[cover] initCoverPage start');

        DOM = resolveDOM();
        console.log('[cover] resolved DOM', DOM);

        initDataTable($);
        initModal();
        bindYearMonthInputs();

        initTrashColumns();

        bindRowReorder(coverTable, {
            api: API.REORDER,
            onSuccess() {
                notifyMessage('success', '커버이미지 순번이 저장되었습니다.');
                coverTable?.ajax.reload(null, false);
            },
            onError(json) {
                notifyMessage('error', json?.message || '커버이미지 순번 저장에 실패했습니다.');
                coverTable?.ajax.reload(null, false);
            }
        });  // 드래그 순서 변경 저장
        bindTableEvents($);
        bindModalEvents($);

        bindTooltipEvents();
        bindTrashEvents();
        bindExcelEvents();

        populateCoverYearOptions();

        console.log('[cover] initCoverPage done');
    }

    /* =========================================================
       공통 유틸리티
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

    function notifyMessage(type, message) {
        if (window.AppCore?.notify) {
            AppCore.notify(type, message);
            return;
        }

        window.alert(message);
    }

    function getSearchFieldSelector() {
        return `${DOM.searchConditions} .search-condition:first select, ${DOM.searchConditions} .search-condition:first .search-field`;
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
       모달 초기화
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
            window.jQuery(DOM.modalIsActive).val('1');
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
       연월 선택 Picker
    ========================================================= */
    function initYearMonthPicker() {
        if (yearMonthPicker) return yearMonthPicker;

        const container = ensureYearMonthPickerContainer();
        if (!container) return null;

        yearMonthPicker = AdminPicker.create({
            type: 'year-month',
            container,
            options: {
                yearMin: new Date().getFullYear() - 50,
                yearMax: new Date().getFullYear() + 5
            }
        });

        yearMonthPicker.subscribe(() => {
            const input = yearMonthPicker.__target;
            if (!input) return;

            const selected = yearMonthPicker.getState?.().date;

            if (!(selected instanceof Date) || Number.isNaN(selected.getTime())) return;

            input.value = formatYearMonth(selected);

            normalizeStartEnd(
                input.name === 'dateStart' ? 'start' : 'end'
            );

            yearMonthPicker.close();
        });

        yearMonthPicker.onClear = () => {
            const input = yearMonthPicker.__target;
            if (!input) return;

            input.value = '';
            yearMonthPicker.close();
        };

        return yearMonthPicker;
    }

    function ensureYearMonthPickerContainer() {
        let container = document.getElementById('year-month-picker');
        if (container) {
            return container;
        }

        let root = document.querySelector('.picker-root');
        if (!root) {
            root = document.createElement('div');
            root.className = 'picker-root';
            document.body.appendChild(root);
        }

        container = document.createElement('div');
        container.id = 'year-month-picker';
        container.className = 'picker is-hidden';
        root.appendChild(container);

        return container;
    }

    function bindYearMonthInputs() {
        if (globalBound) return;
        globalBound = true;

        document
            .querySelectorAll('input.year-input, input[name="dateStart"], input[name="dateEnd"]')
            .forEach(input => {
                input.setAttribute('autocomplete', 'off');
                input.setAttribute('inputmode', 'none');
                input.readOnly = true;
            });

        document.addEventListener('pointerdown', function (e) {
            const input = e.target.closest(
                'input.year-input, input[name="dateStart"], input[name="dateEnd"]'
            );

            if (input) {
                e.preventDefault();
                e.stopPropagation();
                scheduleOpenYearMonthPicker(input);
                return;
            }

            const icon = e.target.closest('.date-icon');
            if (icon) {
                const wrap = icon.closest('.date-input');
                const targetInput = wrap?.querySelector('input');

                if (targetInput) {
                    e.preventDefault();
                    e.stopPropagation();
                    scheduleOpenYearMonthPicker(targetInput);
                }
            }
        }, true);

        document.addEventListener('click', function (e) {
            const input = e.target.closest(
                'input.year-input, input[name="dateStart"], input[name="dateEnd"]'
            );

            if (input) {
                e.preventDefault();
                e.stopPropagation();
                scheduleOpenYearMonthPicker(input);
                return;
            }

            const icon = e.target.closest('.date-icon');
            if (icon) {
                e.preventDefault();
                e.stopPropagation();

                const wrap = icon.closest('.date-input');
                const targetInput = wrap?.querySelector('input');

                if (targetInput) {
                    scheduleOpenYearMonthPicker(targetInput);
                }
            }
        }, true);

        document.addEventListener('focusin', function (e) {
            const input = e.target.closest(
                'input.year-input, input[name="dateStart"], input[name="dateEnd"]'
            );

            if (input) {
                scheduleOpenYearMonthPicker(input);
            }
        });
    }

    function scheduleOpenYearMonthPicker(input) {
        openYearMonthPicker(input);
    }

    function openYearMonthPicker(input) {
        try {
            if (!input) return;

            input.setAttribute('autocomplete', 'off');
            input.setAttribute('inputmode', 'none');
            input.readOnly = true;

            const picker = initYearMonthPicker();
            if (!picker) return;

            picker.__target = input;

            const parsed = parseYearMonth(input.value);
            if (parsed) {
                if (typeof picker.setYearMonth === 'function') {
                    picker.setYearMonth(parsed);
                } else {
                    picker.setView(parsed.getFullYear(), parsed.getMonth());
                }
            } else if (typeof picker.clearDate === 'function') {
                picker.clearDate();
            }

            picker.open({ anchor: input });
        } catch (err) {
            console.error('[cover] year-month picker open failed:', err);
        }
    }

    function parseYearMonth(value) {
        const raw = String(value || '').trim();

        const ym = raw.match(/^(\d{4})-(\d{1,2})(?:-\d{1,2})?$/);
        if (ym) {
            const year = parseInt(ym[1], 10);
            const month = parseInt(ym[2], 10) - 1;
            if (month >= 0 && month <= 11) {
                return new Date(year, month, 1);
            }
        }

        const y = raw.match(/^(\d{4})$/);
        if (y) {
            return new Date(parseInt(y[1], 10), 0, 1);
        }

        return null;
    }

    function formatYearMonth(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
    }

    function normalizeStartEnd(type) {
        const start = getDateStartInput();
        const end = getDateEndInput();

        if (!start || !end) return;
        if (!start.value || !end.value) return;

        const startDate = parseYearMonth(start.value);
        const endDate = parseYearMonth(end.value);

        if (!startDate || !endDate) return;

        if (type === 'start' && startDate > endDate) {
            end.value = start.value;
        }

        if (type === 'end' && endDate < startDate) {
            start.value = end.value;
        }
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

                        /* 공통 휴지통 컴포넌트에 커버이미지 API를 전달한다. */
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
                        window.jQuery(DOM.modalIsActive).val('1');
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
            dataSrc: json => {
                const rows = json?.data ?? [];
                updateCoverCount(Array.isArray(rows) ? rows.length : 0);
                return rows;
            }
        });

        window.coverTable = coverTable;

        if (coverTable) {
            SearchForm({
                table: coverTable,
                apiList: API.LIST,
                tableId: 'cover',
                defaultSearchField: 'title',
                dateOptions: DATE_OPTIONS,
                excludeFields: ['url'],
                normalizeFilters: normalizeCoverFilters
            });

            bindTableHighlight(DOM.table, coverTable);

            coverTable.on('init.dt', function () {
                syncSearchFieldOptionsFromTable();
                updateCoverCount(coverTable.page.info()?.recordsDisplay ?? 0);
            });

            coverTable.on('draw.dt', function () {
                updateCoverCount(coverTable.page.info()?.recordsDisplay ?? 0);
            });
        }
    }

    function updateCoverCount(count) {
        const el = document.getElementById('coverCount');
        if (!el) return;
        el.textContent = `총 ${count ?? 0}건`;
    }

    function buildCoverColumns() {
        const columns = [];

        columns.push({
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
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
                render: function (data, type, row) {
                    if (data === null || data === undefined) return '';

                    if (field === 'url' && type === 'display') {
                        return data
                            ? `<img src="${escapeHtmlAttr(data)}" class="table-img-preview" style="width:80px;cursor:pointer;">`
                            : `<span class="text-muted">이미지 없음</span>`;
                    }

                    if (field === 'is_active' && type === 'display') {
                        const active = Number(data) === 1;
                        return active
                            ? '<span class="badge bg-success">사용</span>'
                            : '<span class="badge bg-secondary">미사용</span>';
                    }

                    if (field === 'created_by' && type === 'display') {
                        return row?.created_by_name || data;
                    }

                    if (field === 'updated_by' && type === 'display') {
                        return row?.updated_by_name || data;
                    }

                    return data;
                },
                searchable: field !== 'url'
            });
        });

        return columns;
    }

    function normalizeCoverFilters(filters) {
        const normalized = [];

        filters.forEach((filter) => {
            if (filter?.field === 'year' && filter.value && typeof filter.value === 'object') {
                const startYear = extractYear(filter.value.start);
                const endYear = extractYear(filter.value.end);

                if (startYear) {
                    normalized.push({ field: 'year_start', value: startYear });
                }

                if (endYear) {
                    normalized.push({ field: 'year_end', value: endYear });
                }

                return;
            }

            if (filter?.field === 'is_active') {
                const normalizedStatus = normalizeActiveValue(filter.value);
                if (normalizedStatus !== '') {
                    normalized.push({ field: 'is_active', value: normalizedStatus });
                }
                return;
            }

            normalized.push(filter);
        });

        return normalized;
    }

    function normalizeActiveValue(value) {
        const raw = String(value ?? '').trim().toLowerCase();
        if (['1', '사용', '사용중', '활성', 'active', 'y', 'yes', 'true'].includes(raw)) return '1';
        if (['0', '미사용', '비활성', 'inactive', 'n', 'no', 'false'].includes(raw)) return '0';
        return '';
    }

    function extractYear(value) {
        const match = String(value || '').match(/^(\d{4})/);
        return match ? match[1] : '';
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
            $(DOM.modalIsActive).val(String(Number(rowData.is_active ?? 1) === 1 ? 1 : 0));

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
       커버이미지 모달 이벤트
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
            const year = String($(DOM.modalYear).val() || '').trim();
            const title = String($(DOM.modalTitle).val() || '').trim();
            const alt = String($(DOM.modalAlt).val() || '').trim();
            const description = String($(DOM.modalDescription).val() || '').trim();
            const isActive = String($(DOM.modalIsActive).val() || '1');

            if (!hasFile && !coverId) {
                notifyMessage('warning', '커버 이미지를 선택하세요.');
                return;
            }

            if (!/^\d{4}$/.test(year)) {
                notifyMessage('warning', '해당년도는 4자리 숫자로 입력하세요.');
                return;
            }

            if (!title) {
                notifyMessage('warning', '타이틀을 입력하세요.');
                return;
            }

            if (!alt) {
                notifyMessage('warning', '이미지 문구(Alt)를 입력하세요.');
                return;
            }

            if (title.length > 120) {
                notifyMessage('warning', '타이틀은 120자 이하로 입력하세요.');
                return;
            }

            if (alt.length > 180) {
                notifyMessage('warning', '이미지 문구(Alt)는 180자 이하로 입력하세요.');
                return;
            }

            if (description.length > 500) {
                notifyMessage('warning', '설명은 500자 이하로 입력하세요.');
                return;
            }

            const fd = new FormData(this);
            fd.set('is_active', isActive === '1' ? '1' : '0');

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

                        notifyMessage('success', '저장 완료');
                    } else {
                        notifyMessage('error', res?.message || '저장에 실패했습니다.');
                    }
                },
                error: function () {
                    notifyMessage('error', '저장 요청에 실패했습니다.');
                }
            });
        });

        $(document).off('click', DOM.modalDeleteBtn);
        $(document).on('click', DOM.modalDeleteBtn, function () {
            const coverId = $(DOM.modalId).val();

            if (!coverId) {
                notifyMessage('warning', '삭제할 항목이 없습니다.');
                return;
            }

            if (!confirm('정말 삭제하시겠습니까?')) return;

            $.post(
                API.DELETE,
                { id: coverId },
                function (res) {
                    if (res && res.success) {
                        resetCoverAfterAction();

                        notifyMessage('success', '삭제 완료');
                    } else {
                        notifyMessage('error', res?.message || '삭제에 실패했습니다.');
                    }
                },
                'json'
            );
        });
    }

    /* =========================================================
       검색 조건 컬럼 연동
    ========================================================= */
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
            if (col.bSearchable === false) return;

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
       도움말 툴팁
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
       휴지통 이벤트
    ========================================================= */
    function bindTrashEvents() {
        document.addEventListener('trash:detail-render', function (e) {
            const { data, modal, type } = e.detail || {};
            if (type !== 'cover') return;
            if (!modal || modal.dataset.type !== 'cover') return;

            const detailBox = modal.querySelector('.trash-detail');
            if (!detailBox) return;

            detailBox.innerHTML = `
                <div class="cover-trash-detail-card">
                    <div class="cover-trash-detail-head">
                        <h6 class="cover-trash-detail-title">삭제된 커버사진 상세</h6>
                        <button type="button" class="cover-trash-detail-close" id="btnCloseCoverTrashDetail">닫기</button>
                    </div>

                    <div class="cover-trash-preview">
                        ${
                            data?.url
                                ? `<img src="${escapeHtmlAttr(data.url)}" alt="cover preview">`
                                : `<div class="cover-trash-empty-preview">이미지 없음</div>`
                        }
                    </div>

                    <div class="cover-trash-detail-grid">
                        <div class="label">순번</div><div class="value">${escapeHtml(data?.sort_no ?? '')}</div>
                        <div class="label">해당년도</div><div class="value">${escapeHtml(data?.year ?? '')}</div>
                        <div class="label">타이틀</div><div class="value">${escapeHtml(data?.title ?? '')}</div>
                        <div class="label">Alt</div><div class="value">${escapeHtml(data?.alt ?? '')}</div>
                        <div class="label">설명</div><div class="value">${escapeHtml(data?.description ?? '')}</div>
                        <div class="label">삭제일</div><div class="value">${escapeHtml(data?.deleted_at ?? '')}</div>
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






    /* =========================================================
       휴지통 컬럼 렌더러
    ========================================================= */
    function initTrashColumns() {
        window.TrashColumns = window.TrashColumns || {};

        window.TrashColumns.cover = function(row) {
            return `
                <td>${row.sort_no ?? ''}</td>
                <td>
                    ${
                        row.url
                            ? `<img src="${escapeHtmlAttr(row.url)}" style="width:60px;height:60px;object-fit:cover;">`
                            : '<span class="text-muted">이미지 없음</span>'
                    }
                </td>
                <td>${row.year ?? ''}</td>
                <td>${row.title ?? ''}</td>
                <td>${row.deleted_at ?? ''}</td>
                <td>${row.deleted_by_name ?? ''}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">삭제</button>
                </td>
            `;
        };
    }

    /* =========================================================
       엑셀 업로드 연동
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

                try {
                    coverTable.columns.adjust().draw(false);
                } catch (e) {
                    console.warn('[cover] columns.adjust warning', e);
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
            $title.text('\uCEE4\uBC84\uC0AC\uC9C4 \uC218\uC815');
            window.jQuery(DOM.modalSaveBtn).text('\uC218\uC815');
            window.jQuery(DOM.modalDeleteBtn).show();
            window.jQuery(DOM.modalImageFile).prop('required', false);
        } else {
            $title.text('\uC0C8 \uCEE4\uBC84\uC0AC\uC9C4 \uB4F1\uB85D');
            window.jQuery(DOM.modalSaveBtn).text('\uB4F1\uB85D');
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
            $select.append(`<option value="${year}" ${selected}>${year}년</option>`);
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
