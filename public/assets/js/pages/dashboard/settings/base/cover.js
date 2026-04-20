/**
 * ??影?れ쉠??????꿔꺂??? ???繹먮냱??JS
 * ?嚥▲굧???뚪뜮? /public/assets/js/pages/dashboard/settings/base/cover.js
 *
 * ?꿔꺂??袁ㅻ븶?猷뱀떻?
 * - ???뚯???????뚯????100% ???
 * - client.js / project.js ???????Β??????源??????域뱄퐢??
 * - ????댁벖??search/table ????源??????癲ル슢?뤸뤃??
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
    * API ?癲ル슢캉???┛?(?????濚밸Ŧ?뤺짆??100% ??濚밸Ŧ遊얕맱?
    * ========================================================= */
    const API = {

        /* ?꿔꺂??袁ㅻ븶筌믠뫀萸?*/
        LIST: "/api/settings/base-info/cover/list",

        /* ?????꾨Щ?꿔꺂??袁ㅻ븶筌믠뫀萸?*/
        PUBLIC_LIST: "/api/settings/base-info/cover/public",

        /* ??壤굿??뺥떑 */
        DETAIL: "/api/settings/base-info/cover/detail",

        /* ????*/
        SAVE: "/api/settings/base-info/cover/save",

        /* ????(????ㅻ쿋??? */
        DELETE: "/api/settings/base-info/cover/delete",

        /* ?????*/
        TRASH: "/api/settings/base-info/cover/trash",

        /* ??⑤슢?뽫뵓??*/
        RESTORE: "/api/settings/base-info/cover/restore",
        RESTORE_BULK: "/api/settings/base-info/cover/restore-bulk",
        RESTORE_ALL: "/api/settings/base-info/cover/restore-all",

        /* ?????????*/
        PURGE: "/api/settings/base-info/cover/purge",
        PURGE_BULK: "/api/settings/base-info/cover/purge-bulk",
        PURGE_ALL: "/api/settings/base-info/cover/purge-all",

        /* ??嶺?筌?*/
        REORDER: "/api/settings/base-info/cover/reorder"
    };

    /* =========================================================
       ??嚥▲꺂??嶸←빊?
    ========================================================= */
    const COVER_COLUMN_MAP = {
        code:        { label: "\uCF54\uB4DC", visible: true },
        url:         { label: "\uC774\uBBF8\uC9C0(View)", visible: true },
        year:        { label: "\uD574\uB2F9\uB144\uB3C4(Year)", visible: true },
        title:       { label: "\uD0C0\uC774\uD2C0(Title)", visible: true },
        alt:         { label: "\uC774\uBBF8\uC9C0\uBB38\uAD6C(Alt)", visible: true },
        description: { label: "\uC124\uBA85(Description)", visible: true }
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
       DOM ?癲ル슢?뤸뤃??????耀붾굝梨??
       - ???????cover.php / ????댁벖??ui-search, ui-table ????????
    ========================================================= */
    function resolveDOM() {
    return {
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

        bindRowReorder(coverTable, { api: API.REORDER });  // ????嶺뚮Ĳ?됪뤃???癲ル슢??節??
        bindTableEvents($);
        bindModalEvents($);
        bindSearchCollapseEvents();

        bindTableLayoutEvents(coverTable, DOM.table);

        bindTooltipEvents();
        bindTrashEvents();
        bindExcelEvents();

        populateCoverYearOptions();

        console.log('[cover] initCoverPage done');
    }

    /* =========================================================
       ????댁봺??????ｋ??
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
       ?꿔꺂??袁ㅻ븶???
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
       ????썹땟?嶺?picker
    ========================================================= */
    function initYearMonthPicker() {
        console.log('[cover] initYearMonthPicker called');

        if (yearMonthPicker) return yearMonthPicker;

        const container = ensureYearMonthPickerContainer();
        console.log('[cover] year-month container', container);
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
            console.log('[cover] yearMonthPicker subscribe state.date', selected);

            if (!(selected instanceof Date) || Number.isNaN(selected.getTime())) return;

            input.value = formatYearMonth(selected);

            normalizeStartEnd(
                input.name === 'dateStart' ? 'start' : 'end'
            );

            yearMonthPicker.close();
        });

        return yearMonthPicker;
    }

    function ensureYearMonthPickerContainer() {
        let container = document.getElementById('year-month-picker');
        if (container) {
            console.log('[cover] found #year-month-picker');
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

        console.log('[cover] created #year-month-picker');

        return container;
    }

    function bindYearMonthInputs() {
        console.log('[cover] bindYearMonthInputs called', { globalBound });

        if (globalBound) return;
        globalBound = true;

        document
            .querySelectorAll('input.year-input, input[name="dateStart"], input[name="dateEnd"]')
            .forEach(input => {
                console.log('[cover] prepare year-month input', input);
                input.setAttribute('autocomplete', 'off');
                input.setAttribute('inputmode', 'none');
                input.readOnly = true;
            });

        document.addEventListener('pointerdown', function (e) {
            console.log('[cover] pointerdown captured', e.target);

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
            console.log('[cover] click captured', e.target);

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

    // function scheduleOpenYearMonthPicker(input) {
    //     console.log('[cover] scheduleOpenYearMonthPicker START', input);

    //     if (yearMonthOpenTimer) {
    //         clearTimeout(yearMonthOpenTimer);
    //     }

    //     yearMonthOpenTimer = setTimeout(() => {
    //         console.log('[cover] scheduleOpenYearMonthPicker FIRE');
    //         yearMonthOpenTimer = null;
    //         openYearMonthPicker(input);
    //     }, 0);
    // }

    function scheduleOpenYearMonthPicker(input) {
        console.log('[cover] direct open');
        openYearMonthPicker(input);
    }

    function openYearMonthPicker(input) {
        try {
            console.log('[cover] openYearMonthPicker', input);

            if (!input) return;

            input.setAttribute('autocomplete', 'off');
            input.setAttribute('inputmode', 'none');
            input.readOnly = true;

            const picker = initYearMonthPicker();
            console.log('[cover] picker instance', picker);
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
            console.log('[cover] picker.open called');
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
                
                        /* ???????? JS?????API ?癲ル슢?뤸뤃??(??????????? */
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
                dateOptions: DATE_OPTIONS
            });

            bindTableHighlight(DOM.table, coverTable);
            updateTableHeight(coverTable, DOM.table);

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
                            : `<span class="text-muted">?????꿔꺂??? ????ㅼ굡??/span>`;
                    }

                    return data;
                }
            });
        });

        return columns;
    }

    /* =========================================================
       ?????????嚥??
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
       ?꿔꺂??袁ㅻ븶??????嚥??
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

            if (!hasFile && !coverId) {
                notifyMessage('warning', '????癲ル슣?? ?????????ャ뀕????⑥궢猷?嶺뚮ㅎ???');
                return;
            }

            if (!/^\\d{4}$/.test(year)) {
                notifyMessage('warning', '??????ш끽維筌??4????????ㅻ쿋筌앷엥??????곸죷???⑥궢猷?嶺뚮ㅎ???');
                return;
            }

            if (!title) {
                notifyMessage('warning', '??筌먯룄肄??????곸죷???⑥궢猷?嶺뚮ㅎ???');
                return;
            }

            if (!alt) {
                notifyMessage('warning', '????癲ル슣?? ???뚮듋??Alt)??????곸죷???⑥궢猷?嶺뚮ㅎ???');
                return;
            }

            if (title.length > 120) {
                notifyMessage('warning', '??筌먯룄肄?? 120????熬곣뫀???????곸죷???⑥궢猷?嶺뚮ㅎ???');
                return;
            }

            if (alt.length > 180) {
                notifyMessage('warning', '????癲ル슣?? ???뚮듋??Alt)??180????熬곣뫀???????곸죷???⑥궢猷?嶺뚮ㅎ???');
                return;
            }

            if (description.length > 500) {
                notifyMessage('warning', '????용럡?? 500????熬곣뫀???????곸죷???⑥궢猷?嶺뚮ㅎ???');
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

                        notifyMessage('success', '????꾨즺');
                    } else {
                        notifyMessage('error', res?.message || '??μ뿉 ?ㅽ뙣?덉뒿?덈떎.');
                    }
                },
                error: function () {
                    notifyMessage('error', '????붿껌???ㅽ뙣?덉뒿?덈떎.');
                }
            });
        });

        $(document).off('click', DOM.modalDeleteBtn);
        $(document).on('click', DOM.modalDeleteBtn, function () {
            const coverId = $(DOM.modalId).val();

            if (!coverId) {
                notifyMessage('warning', '??젣????ぉ???놁뒿?덈떎.');
                return;
            }

            if (!confirm('?뺣쭚 ??젣?섏떆寃좎뒿?덇퉴?')) return;

            $.post(
                API.DELETE,
                { id: coverId },
                function (res) {
                    if (res && res.success) {
                        resetCoverAfterAction();

                        notifyMessage('success', '??젣 ?꾨즺');
                    } else {
                        notifyMessage('error', res?.message || '??젣???ㅽ뙣?덉뒿?덈떎.');
                    }
                },
                'json'
            );
        });
    }

    /* =========================================================
       ?嚥▲굧??????勇?
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
            btn.textContent = hidden ? '\uC5F4\uAE30' : '\uC811\uAE30';

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
       ???繹먮굟瑗????썹땟??
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
       ???ш끽維곫틦?
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
       ?????
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
        
        //     console.log('?????醫딆┫???????源낇꺙 ??????);
        
        //     /* ??????類ㅺ퉻??嚥???????源낇꺙 */
        //     layout.classList.add('open');
        //     detail.style.display = 'block';
        
        //     /* ???????ㅼ굡獒????β뼯爰???ш끽維???*/
        //     detail.innerHTML = `
        //         <div style="padding:20px;">
        //             <h4>???????嚥싲갭횧?蹂좎쒜?/h4>
        //             <p>?????嶺뚮Ĳ?????????癲ル슢캉????/p>
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
                        <h6 class="cover-trash-detail-title">?????????노듋??癲ル슢???ъ쒜?/h6>
                        <button type="button" class="cover-trash-detail-close" id="btnCloseCoverTrashDetail">??/button>
                    </div>
    
                    <div class="cover-trash-preview">
                        ${
                            data?.url
                                ? `<img src="${escapeHtmlAttr(data.url)}" alt="cover preview">`
                                : `<div class="cover-trash-empty-preview">?????꿔꺂??? ????ㅼ굡??/div>`
                        }
                    </div>
    
                    <div class="cover-trash-detail-grid">
                        <div class="label">??ш끽維???/div><div class="value">${escapeHtml(data?.code ?? '')}</div>
                        <div class="label">????썹땟?嶺?/div><div class="value">${escapeHtml(data?.year ?? '')}</div>
                        <div class="label">??嶺뚮Ŋ猷꾥굜?/div><div class="value">${escapeHtml(data?.title ?? '')}</div>
                        <div class="label">Alt</div><div class="value">${escapeHtml(data?.alt ?? '')}</div>
                        <div class="label">?????⑸윞</div><div class="value">${escapeHtml(data?.description ?? '')}</div>
                        <div class="label">?????濚밸Ŧ援앾쭛?/div><div class="value">${escapeHtml(data?.deleted_at ?? '')}</div>
                        <div class="label">?????/div><div class="value">${escapeHtml(data?.deleted_by_name || data?.deleted_by || '')}</div>
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
                            : '<span class="text-muted">????ㅼ굡??/span>'
                    }
                </td>
                <td>${row.year ?? ''}</td>
                <td>${row.title ?? ''}</td>
                <td>${row.deleted_at ?? ''}</td>
                <td>${row.deleted_by_name ?? ''}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">??⑤슢?뽫뵓??/button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">?????????/button>
                </td>
            `;
        };
    }




    
    /* =========================================================
       ???뚯???
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
        $select.append('<option value="">????ｋ???嶺뚮슣堉???/option>');

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
