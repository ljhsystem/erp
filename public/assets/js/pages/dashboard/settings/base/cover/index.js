import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createDataTable,
    bindTableHighlight
} from '/public/assets/js/common/table/data-table.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy
} from '/public/assets/js/common/datatable/dataTableSettings.js';
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
    const COVER_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.dashboard.settings.base-info.cover.cover-table.v1';
    const COVER_TABLE_SETTINGS_META_DOMAIN = 'cover';
    const COVER_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: 'label[for="modal_year"]', key: 'year', fallback: 'Year' },
        { selector: 'label[for="modal_cover_image"]', key: 'src', fallback: 'Image' },
        { selector: 'label[for="modal_is_active"]', key: 'is_active', fallback: 'Status' },
        { selector: 'label[for="modal_title"]', key: 'title', fallback: 'Title' },
        { selector: 'label[for="modal_alt"]', key: 'alt', fallback: 'Alt' },
        { selector: 'label[for="modal_description"]', key: 'description', fallback: 'Description' },
    ]);


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
                notifyMessage('success', 'Cover image order saved.');
                coverTable?.ajax.reload(null, false);
            },
            onError(json) {
                notifyMessage('error', json?.message || 'Failed to save cover image order.');
                coverTable?.ajax.reload(null, false);
            }
        });
        bindTableEvents($);

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

    function currentCoverPolicyState() {
        return readDataTableSettingsState(COVER_TABLE_SETTINGS_STORAGE_KEY) || {};
    }

    function resolveCoverPolicyDisplayName(key, _fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentCoverPolicyState(),
            normalizedKey
        );
    }

    function resolveCoverPolicyRequirement(key) {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentCoverPolicyState()
        );
    }

    function coverFieldLabel(key, _fallback = '') {
        return resolveCoverPolicyDisplayName(key, String(key || '').trim());
    }

    function coverFieldStarMarkup(key) {
        const policy = resolveCoverPolicyRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function applyCoverModalPolicyLabels() {
        COVER_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = document.querySelector(field.selector);
            if (!labelEl) {
                return;
            }

            const displayName = coverFieldLabel(field.key, field.fallback);
            const starMarkup = coverFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function isCoverFieldVisible(selector) {
        const field = document.querySelector(selector);
        if (!field) return false;
        if (field.type === 'hidden') return false;
        if (field.disabled) return false;
        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        return true;
    }

    function validateCoverRequiredPolicies({
        coverId,
        hasFile,
        year,
        title,
        alt,
        description,
    } = {}) {
        const values = {
            year,
            src: hasFile ? '__uploaded__' : (coverId ? '__existing__' : ''),
            title,
            alt,
            description,
        };

        for (const field of COVER_MODAL_FIELD_POLICIES) {
            const key = String(field?.key || '').trim();
            const selector = String(field?.selector || '').trim();
            if (!key || !selector) {
                continue;
            }
            if (resolveCoverPolicyRequirement(key) !== 'required') {
                continue;
            }
            if (!isCoverFieldVisible(selector)) {
                continue;
            }

            if (String(values[key] ?? '').trim() === '') {
                const label = coverFieldLabel(key, field?.fallback || key);
                notifyMessage('warning', `${label} 항목은 필수입니다.`);
                return true;
            }
        }

        return false;
    }

    function validateCoverModalFields({
        year,
        title,
        alt,
        description,
        labels = {},
    } = {}) {
        if (String(year || '').trim() !== '' && !/^\d{4}$/.test(String(year || '').trim())) {
            notifyMessage('warning', `${labels.yearLabel || '연도'}는 4자리 숫자여야 합니다.`);
            return true;
        }

        if (String(title || '').trim().length > 120) {
            notifyMessage('warning', `${labels.titleLabel || '제목'}은 120자 이하로 입력해야 합니다.`);
            return true;
        }

        if (String(alt || '').trim().length > 180) {
            notifyMessage('warning', `${labels.altLabel || '대체문구'}는 180자 이하로 입력해야 합니다.`);
            return true;
        }

        if (String(description || '').trim().length > 500) {
            notifyMessage('warning', `${labels.descriptionLabel || '설명'}은 500자 이하로 입력해야 합니다.`);
            return true;
        }

        return false;
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
        applyCoverModalPolicyLabels();

        modalEl.addEventListener('hidden.bs.modal', () => {
            const form = qs(DOM.form);
            if (form) form.reset();

            populateCoverYearOptions();
            window.jQuery(DOM.modalIsActive).val('1');
            setCoverModalMode('create');
            applyCoverModalPolicyLabels();

            const preview = qs(DOM.modalImagePreview);
            if (preview) {
                preview.setAttribute('src', '');
                preview.style.display = 'none';
            }
        });

        modalEl.addEventListener('shown.bs.modal', () => {
            applyCoverModalPolicyLabels();
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

        document.addEventListener('datatable-settings:updated', (event) => {
            const detail = event?.detail || {};
            const storageKey = String(detail.storageKey || '').trim();
            const metaDomain = String(detail.metaDomain || '').trim();
            if (storageKey !== COVER_TABLE_SETTINGS_STORAGE_KEY && metaDomain !== COVER_TABLE_SETTINGS_META_DOMAIN) {
                return;
            }

            applyCoverModalPolicyLabels();
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
            tableSettings: {
                pageKey: 'dashboard.settings.base-info.cover',
                tableKey: 'cover-table',
                storageKey: 'datatable.settings.dashboard.settings.base-info.cover.cover-table.v1',
                metaDomain: 'cover',
                tableLabel: 'cover',
                title: 'Cover Table Settings',
            },
            defaultOrder: [[1, 'asc']],
            buttons: [
                {
                    text: '\ud734\uc9c0\ud1b5',
                    className: 'btn btn-danger btn-sm',
                    action: function () {
                        if (!trashModalEl) return;

                        trashModalEl.dataset.listUrl = API.TRASH;
                        trashModalEl.dataset.restoreUrl = API.RESTORE;
                        trashModalEl.dataset.deleteUrl = API.PURGE;
                        trashModalEl.dataset.deleteAllUrl = API.PURGE_ALL;

                        const modal = new bootstrap.Modal(trashModalEl);
                        modal.show();
                    }
                },
                {
                    text: '\uc0c8 \uc774\ubbf8\uc9c0',
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
                excludeFields: ['src'],
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
        el.textContent = `\ucd1d ${count ?? 0}\uac74`;
    }

    function buildCoverColumns() {
        const columns = [];

        columns.push({
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
            settingsKey: '__reorder',
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

                    if (field === 'src' && type === 'display') {
                        const previewSrc = (row && row.url) ? row.url : data;
                        return previewSrc
                            ? '<img src="' + escapeHtmlAttr(previewSrc) + '" class="table-img-preview" style="width:80px;cursor:pointer;">'
                            : '<span class="text-muted">No image</span>';
                    }

                    if (field === 'is_active' && type === 'display') {
                        const active = Number(data) === 1;
                        return '<div class="form-check form-switch d-inline-flex justify-content-center m-0">' +
                            '<input type="checkbox" ' +
                            'class="form-check-input cover-active-toggle" ' +
                            'data-id="' + escapeHtmlAttr(row.id || '') + '" ' +
                            (active ? 'checked ' : '') +
                            'aria-label="toggle status">' +
                            '</div>';
                    }

                    if (field === 'created_by' && type === 'display') {
                        return row?.created_by_name || data;
                    }

                    if (field === 'updated_by' && type === 'display') {
                        return row?.updated_by_name || data;
                    }

                    return data;
                },
                searchable: field !== 'src'
            });
        });

        columns.push({
            data: null,
            title: '\uad00\ub9ac',
            className: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render: function (_data, type, row) {
                if (type !== 'display') return '';
                return '<button type="button"'
                    + ' class="btn btn-outline-primary btn-sm cover-edit-btn"'
                    + ' data-id="' + escapeHtmlAttr(row.id || '') + '">'
                    + '\uc218\uc815'
                    + '</button>';
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

            const previewSrc = rowData.url || rowData.src || '';
            if (previewSrc) {
                window.jQuery(DOM.modalImagePreview).attr('src', previewSrc).show();
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
                    notifyMessage('success', active ? 'Cover image activated.' : 'Cover image deactivated.');
                    reloadCoverTable(false);
                    return;
                }

                if (toggleEl) {
                    toggleEl.checked = !active;
                }
                notifyMessage('error', res?.message || 'Failed to change cover image status.');
            },
            error: function () {
                if (toggleEl) {
                    toggleEl.checked = !active;
                }
                notifyMessage('error', 'Cover image status request failed.');
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
            const yearLabel = coverFieldLabel('year', 'Year');
            const imageLabel = coverFieldLabel('src', 'Image Path');
            const titleLabel = coverFieldLabel('title', 'Title');
            const altLabel = coverFieldLabel('alt', 'Alt Text');
            const descriptionLabel = coverFieldLabel('description', 'Description');

            if (validateCoverRequiredPolicies({
                coverId,
                hasFile,
                year,
                title,
                alt,
                description,
            })) {
                return;
            }

            if (validateCoverModalFields({
                year,
                title,
                alt,
                description,
                labels: {
                    yearLabel,
                    imageLabel,
                    titleLabel,
                    altLabel,
                    descriptionLabel,
                },
            })) {
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
                        notifyMessage('success', 'Saved successfully.');
                    } else {
                        notifyMessage('error', res?.message || 'Save failed.');
                    }
                },
                error: function () {
                    notifyMessage('error', 'Save request failed.');
                }
            });
        });

        $(document).off('click', DOM.modalDeleteBtn);
        $(document).on('click', DOM.modalDeleteBtn, function () {
            const coverId = $(DOM.modalId).val();
            if (!coverId) {
                notifyMessage('warning', 'No item to delete.');
                return;
            }

            if (!confirm('Are you sure you want to delete this item?')) return;

            $.post(API.DELETE, { id: coverId }, function (res) {
                if (res && res.success) {
                    resetCoverAfterAction();
                    notifyMessage('success', 'Deleted successfully.');
                } else {
                    notifyMessage('error', res?.message || 'Delete failed.');
                }
            }, 'json').fail(function () {
                notifyMessage('error', 'Delete request failed.');
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
            if (col.data === 'src') return;
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
     * Excel manager event binding
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
