import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createDataTable,
    bindTableHighlight
} from '/public/assets/js/common/table/data-table.js';
import {
    getCachedDataTableMetaColumns
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.cover.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import { bindModalCardCollapses } from '/public/assets/js/common/modal-card-collapse.js';
import { createCoverYearMonthPicker } from './picker.js';
import { createCoverSystemInfo } from './system-info.js';
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
import { runDeleteProgress } from '/public/assets/js/common/delete-progress.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    let coverTable = null;
    let coverModal = null;
    let coverTrashModal = null;
    let coverTrashTable = null;
    let selectedTrashDetailId = null;
    let coverPickerModule = null;
    let coverSystemInfo = null;
    let DOM = null;
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
            modalLabel: '#coverModalLabel',
            systemInfoFields: '#coverSystemInfoFields'
        };
    }




    function bootCoverPage() {
        if (!window.jQuery) return;

        void initCoverPage(window.jQuery).catch(() => {
            window.AppCore?.notify?.('error', '커버이미지 목록을 불러오는 중 오류가 발생했습니다.');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootCoverPage);
    } else {
        bootCoverPage();
    }

    window.TrashColumns = window.TrashColumns || {};


    async function initCoverPage($) {
        DOM = resolveDOM();
        coverPickerModule = createCoverYearMonthPicker({ AdminPicker, getDateStartInput, getDateEndInput });
        coverSystemInfo = createCoverSystemInfo({
            containerSelector: DOM.systemInfoFields,
            resolveMetaColumn: resolveCoverMetaColumn,
        });

        await initDataTable($);
        initModal();
        bindSystemInfoCard();
        coverPickerModule.bind();

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

    function currentCoverMetaPolicy() {
        const metaColumns = getCachedDataTableMetaColumns({
            metaDomain: COVER_TABLE_SETTINGS_META_DOMAIN,
        });

        return metaColumns.reduce((accumulator, column) => {
            const key = String(column?.key || '').trim();
            if (!key) {
                return accumulator;
            }

            accumulator[key] = {
                label: String(column?.label || key).trim() || key,
                required: column?.required === true,
            };
            return accumulator;
        }, {});
    }

    function resolveCoverMetaColumn(key, fallback = '') {
        const normalizedKey = String(key || '').trim();
        const meta = currentCoverMetaPolicy();
        return meta[normalizedKey] || {
            label: String(fallback || normalizedKey).trim() || normalizedKey,
            required: false,
        };
    }

    function coverFieldLabel(key, fallback = '') {
        return resolveCoverMetaColumn(key, fallback).label;
    }

    function coverFieldStarMarkup(key, fallback = '') {
        const columnMeta = resolveCoverMetaColumn(key, fallback);
        if (columnMeta.required) {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
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
            const starMarkup = coverFieldStarMarkup(field.key, field.fallback);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function bindSystemInfoCard() {
        const modal = qs(DOM.modal);
        if (!modal) return;
        bindModalCardCollapses(modal, { resetOnShow: true });
    }

    function renderCoverSystemInfo(data = {}) {
        coverSystemInfo?.render(data);
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
            if (!resolveCoverMetaColumn(key, field?.fallback || key).required) {
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
        renderCoverSystemInfo();

        modalEl.addEventListener('hidden.bs.modal', () => {
            const form = qs(DOM.form);
            if (form) form.reset();

            populateCoverYearOptions();
            window.jQuery(DOM.modalIsActive).val('1');
            setCoverModalMode('create');
            renderCoverSystemInfo();
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

    }


    async function initDataTable($) {
        const columns = buildCoverColumns();
        const trashModalEl = qs(DOM.trashModal);

        coverTable = await createDataTable({
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
                    text: '신규등록',
                    className: 'btn btn-warning btn-sm',
                    action: function () {
                        const form = qs(DOM.form);
                        if (form) form.reset();

                        setCoverModalMode('create');
                        renderCoverSystemInfo();

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

                    if (config.type === 'actor' && type === 'display') return actorDisplay(row, field);

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
        renderCoverSystemInfo(rowData);

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
        $(document).on('click', DOM.modalDeleteBtn, async function () {
            const coverId = $(DOM.modalId).val();
            if (!coverId) {
                notifyMessage('warning', 'No item to delete.');
                return;
            }

            if (!confirm('Are you sure you want to delete this item?')) return;

            try {
                await runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '커버이미지를 휴지통으로 이동 중', trashChanged: true }, async () => {
                    const res = await $.post(API.DELETE, { id: coverId });
                    if (!res?.success) throw new Error(res?.message || '삭제에 실패했습니다.');
                    resetCoverAfterAction();
                    notifyMessage('success', '삭제되었습니다.');
                });
            } catch (error) {
                notifyMessage('error', error.message || '삭제 중 오류가 발생했습니다.');
            }
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
