import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createDataTable,
    bindTableHighlight
} from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.cover.js';
import { API, COVER_COLUMN_MAP, DATE_OPTIONS } from './api.js';
import {
    setCoverModalMode as applyCoverModalMode,
    populateCoverYearOptions as applyCoverYearOptions
} from './modal.js';
import {
    clearCoverSearchConditions as clearCoverFilters,
    resetCoverAfterAction as resetAfterAction
} from './form.js';
import { bindExcelEvents as attachExcelEvents } from './excel.js';
import {
    normalizeCoverFilters as normalizeCoverFiltersByModule,
    normalizeActiveValue as normalizeActiveValueByModule,
    extractYear as extractYearByModule
} from './table.js';
import {
    initTrashColumns as registerTrashColumns,
    bindTrashEvents as attachTrashEvents
} from './trash.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    console.log('[base-cover.js] loaded');


    let coverTable = null;
    let coverModal = null;
    let coverTrashModal = null;
    let coverTrashTable = null;
    let yearMonthPicker = null;
    let selectedTrashDetailId = null;
    let globalBound = false;
    let yearMonthOpenTimer = null;
    let DOM = null;


    function resolveDOM() {
        return {
            table: '#cover-table',
            modal: '#coverModal',
            form: '#cover-form',

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
            modalLabel: '#coverModalLabel'
        };
    }




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

    function deferCoverModalWork(callback) {
        if (typeof callback !== 'function') return;

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(() => window.setTimeout(callback, 0));
            return;
        }

        window.setTimeout(callback, 0);
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


    function initDataTable($) {
        const columns = buildCoverColumns();
        const trashModalEl = qs(DOM.trashModal);

        coverTable = createDataTable({
            tableSelector: DOM.table,
            api: API.LIST,
            deleteApi: API.DELETE,
            columns,
            defaultOrder: [[1, 'asc']],
            buttons: [
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: function () {

                        if (!trashModalEl) return;


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

                        setCoverModalMode('create');

                        if (coverModal) {
                            coverModal.show();
                        }

                        deferCoverModalWork(() => {
                            populateCoverYearOptions();
                            window.jQuery(DOM.modalIsActive).val('1');

                            const preview = qs(DOM.modalImagePreview);
                            if (preview) {
                                preview.setAttribute('src', '');
                                preview.style.display = 'none';
                            }
                        });
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
                        return `
                            <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                                <input type="checkbox"
                                       class="form-check-input cover-active-toggle"
                                       data-id="${escapeHtmlAttr(row.id || '')}"
                                       ${active ? 'checked' : ''}
                                       aria-label="상태 변경">
                            </div>
                        `;
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

        columns.push({
            data: null,
            title: '관리',
            className: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render: function (_data, type, row) {
                if (type !== 'display') return '';
                return `
                    <button type="button"
                            class="btn btn-outline-primary btn-sm cover-edit-btn"
                            data-id="${escapeHtmlAttr(row.id || '')}">
                        수정
                    </button>
                `;
            }
        });

        return columns;
    }

    
    function normalizeCoverFilters(filters) {
        return normalizeCoverFiltersByModule(filters);
    }

    function normalizeActiveValue(value) {
        return normalizeActiveValueByModule(value);
    }

    function extractYear(value) {
        return extractYearByModule(value);
    }


    function openCoverEditModal(rowData) {
        if (!rowData) return;

        const yearValue = String(rowData.year ?? '').trim();
        const form = qs(DOM.form);
        if (form) form.reset();

        window.jQuery(DOM.modalId).val(rowData.id || '');
        window.jQuery(DOM.modalImagePreview).attr('src', '').hide();
        setCoverModalMode('edit');

        if (coverModal) {
            coverModal.show();
        }

        deferCoverModalWork(() => {
            populateCoverYearOptions(yearValue);
            window.jQuery(DOM.modalTitle).val(rowData.title || '');
            window.jQuery(DOM.modalAlt).val(rowData.alt || '');
            window.jQuery(DOM.modalDescription).val(rowData.description || '');
            window.jQuery(DOM.modalIsActive).val(String(Number(rowData.is_active ?? 1) === 1 ? 1 : 0));

            if (rowData.url) {
                window.jQuery(DOM.modalImagePreview).attr('src', rowData.url).show();
            } else {
                window.jQuery(DOM.modalImagePreview).attr('src', '').hide();
            }

            window.jQuery(DOM.modalYear).val(yearValue).trigger('change');
        });
    }

    function updateCoverActive(rowData, active, toggleEl) {
        if (!rowData?.id) return;

        const fd = new FormData();
        fd.set('id', rowData.id);
        fd.set('year', rowData.year || '');
        fd.set('title', rowData.title || '');
        fd.set('alt', rowData.alt || '');
        fd.set('description', rowData.description || '');
        fd.set('is_active', active ? '1' : '0');

        if (toggleEl) {
            toggleEl.disabled = true;
        }

        window.jQuery.ajax({
            url: API.SAVE,
            type: 'POST',
            data: fd,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function (res) {
                if (res && res.success) {
                    notifyMessage('success', active ? '사용으로 변경되었습니다.' : '미사용으로 변경되었습니다.');
                    reloadCoverTable(false);
                    return;
                }

                if (toggleEl) {
                    toggleEl.checked = !active;
                }
                notifyMessage('error', res?.message || '상태 변경에 실패했습니다.');
            },
            error: function () {
                if (toggleEl) {
                    toggleEl.checked = !active;
                }
                notifyMessage('error', '상태 변경 요청에 실패했습니다.');
            },
            complete: function () {
                if (toggleEl) {
                    toggleEl.disabled = false;
                }
            }
        });
    }

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

        $(DOM.table + ' tbody').on('change', '.cover-active-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const rowData = coverTable.row($(this).closest('tr')).data();
            updateCoverActive(rowData, this.checked, this);
        });

        $(DOM.table + ' tbody').on('click', '.cover-edit-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const rowData = coverTable.row($(this).closest('tr')).data();
            openCoverEditModal(rowData);
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
            openCoverEditModal(rowData);
        });
    }


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

            if (!coverId && !hasFile) {
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
                        notifyMessage('success', '저장이 완료되었습니다.');
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

            $.post(API.DELETE, { id: coverId }, function (res) {
                if (res && res.success) {
                        resetCoverAfterAction();
                        notifyMessage('success', '삭제가 완료되었습니다.');
                } else {
                    notifyMessage('error', res?.message || '삭제에 실패했습니다.');
                }
            }, 'json').fail(function () {
                notifyMessage('error', '삭제 요청에 실패했습니다.');
            });
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

        return html;
    }





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




    function bindTrashEvents() {
        attachTrashEvents({
            DOM,
            qsa,
            escapeHtml,
            escapeHtmlAttr,
            getCoverTable: () => coverTable,
        });
    }



    function initTrashColumns() {
        registerTrashColumns({
            escapeHtml,
            escapeHtmlAttr,
        });
    }

    /* =========================================================
     * 엑셀 업로드 연동
    ========================================================= */
    function bindExcelEvents() {
        attachExcelEvents({
            getCoverTable: () => coverTable,
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
        clearCoverFilters({
            DOM,
            jQuery: window.jQuery,
            getDateStartInput,
            getDateEndInput,
            getSearchFieldSelector,
        });
    }

    function resetCoverAfterAction() {
        resetAfterAction({
            clearCoverSearchConditions,
            populateCoverYearOptions,
            getCoverModal: () => coverModal,
            reloadCoverTable,
        });
    }

    function setCoverModalMode(mode = 'create') {
        applyCoverModalMode({
            DOM,
            jQuery: window.jQuery,
        }, mode);
    }

    function populateCoverYearOptions(selectedYear = '') {
        applyCoverYearOptions({
            DOM,
            jQuery: window.jQuery,
        }, selectedYear);
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
