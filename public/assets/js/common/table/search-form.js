import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

const AppCore = window.AppCore = window.AppCore || {};
const AppDate = window.AppDate || {};
const AppDom = window.AppDom || {};
const AppEvents = window.AppEvents || {};

const onDocument = (type, handler, options = false) => {
    if (typeof AppEvents.onDocument === 'function') {
        return AppEvents.onDocument(type, handler, options);
    }
    document.addEventListener(type, handler, options);
    return () => document.removeEventListener(type, handler, options);
};

function formatDateValue(date) {
    if (AppDate?.formatDate) {
        return AppDate.formatDate(date, '-');
    }
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function normalizeDate(value) {
    const normalized = AppDate?.normalizeDateValue?.(value);
    if (typeof normalized === 'string') return normalized;

    const raw = String(value || '').trim();
    if (!raw) return '';
    const digits = raw.replace(/\D/g, '').slice(0, 8);
    if (digits.length !== 8) return raw;

    const year = Number(digits.slice(0, 4));
    const month = Number(digits.slice(4, 6));
    const day = Number(digits.slice(6, 8));
    const date = new Date(year, month - 1, day);

    if (
        date.getFullYear() !== year ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        return raw;
    }

    return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6, 8)}`;
}

function normalizeText(html) {
    if (typeof AppDom?.stripHtml === 'function') {
        return AppDom.stripHtml(html);
    }
    if (!html) return '';
    const node = document.createElement('div');
    node.innerHTML = html;
    return (node.textContent || '').trim();
}

export function setPeriod(type, sourceButton = null, targetForm = null) {
    const activeEl = document.activeElement;
    const btn = sourceButton
        || (activeEl && activeEl.matches?.('[onclick*=\"setPeriod\"]')
            ? activeEl
            : null)
        || window.event?.currentTarget
        || null;

    const form = targetForm || btn?.closest?.('form') || document.querySelector('form[id$=\"SearchConditionsForm\"]');
    if (!form) return;

    const today = new Date();
    const period = AppDate?.periodRange?.(type, today) || {};
    let start = period.start || today;
    let end = period.end || today;

    if (!period.start && !period.end) {
        const startDate = new Date(today);
        const endDate = new Date(today);

        switch (type) {
            case 'today':
                break;
            case 'yesterday':
                startDate.setDate(today.getDate() - 1);
                endDate.setTime(startDate.getTime());
                break;
            case '3days':
                startDate.setDate(today.getDate() - 3);
                break;
            case '7days':
                startDate.setDate(today.getDate() - 7);
                break;
            case '15days':
                startDate.setDate(today.getDate() - 15);
                break;
            case '1month':
                startDate.setMonth(today.getMonth() - 1);
                break;
            case '3months':
                startDate.setMonth(today.getMonth() - 3);
                break;
            case '6months':
                startDate.setMonth(today.getMonth() - 6);
                break;
            default:
                return;
        }

        start = startDate;
        end = endDate;
    }

    const $form = window.jQuery(form);
    $form.find('[name=\"dateStart\"]').val(formatDateValue(start));
    $form.find('[name=\"dateEnd\"]').val(formatDateValue(end));
    $form.trigger('submit');
}

if (!window.SearchForm) {
    window.SearchForm = {};
}
window.SearchForm.setPeriod = setPeriod;
AppCore.setPeriod = setPeriod;

export function SearchForm(config) {
    const {
        table,
        apiList,
        tableId,
        defaultSearchField,
        dateOptions,
        normalizeFilters,
        excludeFields = [],
        initialCollapsed = true
    } = config;

    const $ = window.jQuery;
    const MAX_CONDITION = 5;
    const formId = `#${tableId}SearchConditionsForm`;
    const conditionsId = `#${tableId}SearchConditions`;
    const addBtnId = `#${tableId}AddSearchCondition`;
    const resetBtnId = `#${tableId}ResetButton`;
    const dateTypeId = `#${tableId}DateType`;

    const containerEl = document.getElementById(`${tableId}SearchFormContainer`);
    const bodyEl = document.getElementById(`${tableId}SearchFormBody`);
    const toggleBtnEl = document.getElementById(`${tableId}ToggleSearchForm`);
    const searchTooltipLabel = document.getElementById(`${tableId}SearchLabel`);
    const searchTooltipTrigger = document.getElementById(`${tableId}TooltipTrigger`);
    const searchTooltipBox = document.getElementById(`${tableId}TooltipContainer`);
    const periodTooltipLabel = document.getElementById(`${tableId}PeriodLabel`);
    const periodTooltipTrigger = document.getElementById(`${tableId}PeriodTooltipTrigger`);
    const periodTooltipBox = document.getElementById(`${tableId}PeriodTooltipContainer`);
    const initialSearchFields = readInitialSearchFields();

    function refreshTableLayout(options = {}) {
        const draw = options?.draw === true;

        if (table?.__dtTableSettings?.refreshLayout) {
            table.__dtTableSettings.refreshLayout({ draw });
            return;
        }

        table?.columns?.adjust();
        if (draw) {
            table?.draw?.(false);
        }
    }

    applyInitialState();
    bindToggle();
    bindTooltips();
    bindSearchEvents();
    populateFirstSearchFields();
    populateDateOptions(dateOptions);
    applySavedSearchFormState();
    bindPeriodButtons();
    bindDatePicker();

    function currentApiList() {
        return typeof apiList === 'function' ? apiList() : apiList;
    }

    function getSavedExpandedState() {
        const value = table?.__dtTableSettings?.getViewState?.()?.searchFormExpanded;
        return typeof value === 'boolean' ? value : null;
    }

    function persistExpandedState(expanded) {
        if (typeof expanded !== 'boolean') {
            return;
        }

        table?.__dtTableSettings?.updateViewState?.({
            searchFormExpanded: expanded,
        });
    }

    function getSavedSearchFormState() {
        const value = table?.__dtTableSettings?.getViewState?.()?.searchFormState;
        return value && typeof value === 'object' ? value : null;
    }

    function persistSearchFormState(stateValue = null) {
        table?.__dtTableSettings?.updateViewState?.({
            searchFormState: stateValue,
            currentPage: 0,
        });
    }

    function applyInitialState() {
        const savedExpandedState = getSavedExpandedState();
        const collapsed = savedExpandedState === null
            ? initialCollapsed
            : !savedExpandedState;

        bodyEl?.classList.toggle('hidden', collapsed);
        containerEl?.classList.toggle('collapsed', collapsed);
        const nextToggleText = collapsed ? '닫힘' : '펼침';
        if (toggleBtnEl) {
            toggleBtnEl.textContent = initialCollapsed ? '닫힘' : '펼침';
        }
        if (toggleBtnEl) {
            toggleBtnEl.textContent = nextToggleText;
        }

    }

    function bindToggle() {
        if (!containerEl || !bodyEl || !toggleBtnEl || toggleBtnEl.__searchToggleBound) return;
        toggleBtnEl.__searchToggleBound = true;

        toggleBtnEl.addEventListener('click', () => {
            const hidden = !bodyEl.classList.contains('hidden');
            bodyEl.classList.toggle('hidden', hidden);
            containerEl.classList.toggle('collapsed', hidden);
            toggleBtnEl.textContent = hidden ? '닫힘' : '펼침';
            persistExpandedState(!hidden);
            requestAnimationFrame(() => requestAnimationFrame(() => refreshTableLayout({ draw: false })));
        });
    }

    function bindTooltips() {
        setupTooltip([searchTooltipTrigger, searchTooltipLabel], searchTooltipTrigger || searchTooltipLabel, searchTooltipBox);
        setupTooltip([periodTooltipTrigger, periodTooltipLabel], periodTooltipTrigger || periodTooltipLabel, periodTooltipBox);

        function setupTooltip(triggers, anchor, tooltip) {
            if (!anchor || !tooltip) return;

            const triggerList = (Array.isArray(triggers) ? triggers : [triggers]).filter(Boolean);
            if (!triggerList.length) return;

            triggerList.forEach((trigger) => {
                if (trigger.__tooltipBound) return;
                trigger.__tooltipBound = true;

                trigger.addEventListener('pointerdown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                });
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    window.__tooltipSuppressDocumentClickUntil = Date.now() + 250;
                    setTimeout(() => toggleTooltip(anchor, tooltip), 0);
                });
                trigger.addEventListener('keydown', (e) => {
                    if (e.key !== 'Enter' && e.key !== ' ') return;
                    e.preventDefault();
                    e.stopPropagation();
                    toggleTooltip(anchor, tooltip);
                });
            });

            if (!anchor.hasAttribute('tabindex')) {
                anchor.setAttribute('tabindex', '0');
            }
        }

        function toggleTooltip(anchor, tooltip) {
            const isOpen = tooltip.classList.contains('show');
            closeAllTooltips();
            if (!isOpen) {
                const rect = anchor.getBoundingClientRect();
                document.body.appendChild(tooltip);
                tooltip.style.position = 'fixed';
                tooltip.style.top = `${rect.bottom + 6}px`;
                tooltip.style.left = `${rect.left}px`;
                tooltip.style.display = 'block';
                tooltip.classList.add('show');
            }
        }

        function closeAllTooltips() {
            document.querySelectorAll('.tooltip-container').forEach((tooltip) => {
                tooltip.style.display = 'none';
                tooltip.classList.remove('show');
            });
        }

        [searchTooltipBox, periodTooltipBox].forEach((tooltip) => {
            if (!tooltip || tooltip.__tooltipClickBound) return;
            tooltip.__tooltipClickBound = true;
            tooltip.addEventListener('click', (e) => e.stopPropagation());
        });

        if (!window.__tooltipGlobalBound) {
            window.__tooltipGlobalBound = true;
            onDocument('click', () => {
                if (Date.now() < (window.__tooltipSuppressDocumentClickUntil || 0)) return;
                closeAllTooltips();
            });
            onDocument('pointerdown', (e) => {
                const target = e.target;
                if (target?.closest?.('.tooltip-container, .tooltip-trigger, .label-btn')) return;
                closeAllTooltips();
            });
            onDocument('keydown', (e) => {
                if (e.key === 'Escape') closeAllTooltips();
            });
        }
    }

    function bindSearchEvents() {
        $(formId).off('submit.searchForm').on('submit.searchForm', function (e) {
            e.preventDefault();
            const filters = collectFilters();
            if (filters === null) return;
            const normalizedFilters = applyFilterNormalizer(filters);
            persistSearchFormState(readCurrentSearchFormState());
            const url = buildFilterUrl(normalizedFilters);
            table.ajax.url(url).load(() => {
                refreshTableLayout({ draw: true });
            });
        });

        $(document).off('click.searchFormRemove').on('click.searchFormRemove', `${conditionsId} .remove-condition`, function () {
            const rows = $(`${conditionsId} .search-condition`);
            if (rows.length <= 1) {
                alert('최소 1개 검색조건은 유지해야 합니다.');
                return;
            }
            $(this).closest('.search-condition').remove();
            updateRemoveButtons();
            refreshTableLayout({ draw: false });
        });

        $(resetBtnId).off('click.searchFormReset').on('click.searchFormReset', function (e) {
            e.preventDefault();
            $(`${conditionsId} input[type=\"text\"]`).val('');
            $(`${conditionsId}`).find('.search-condition:gt(0)').remove();
            $(formId).find('input[name=\"dateStart\"]').val('');
            $(formId).find('input[name=\"dateEnd\"]').val('');

            const dateTypeEl = document.getElementById(`${tableId}DateType`);
            if (dateTypeEl && dateOptions?.length) {
                dateTypeEl.value = dateOptions[0].value;
            }
            populateFirstSearchFields();
            updateRemoveButtons();
            persistSearchFormState(null);

            table.ajax.url(currentApiList()).load(() => {
                refreshTableLayout({ draw: true });
            });
        });

        $(addBtnId).off('click.searchFormAdd').on('click.searchFormAdd', function () {
            const rows = $(`${conditionsId} .search-condition`);
            const count = rows.length;
            if (count >= MAX_CONDITION) {
                alert('검색조건은 최대 5개까지만 추가할 수 있습니다.');
                return;
            }

            const firstField = rows.first().find('select').val();
            const fields = getSearchFields();
            const baseIndex = fields.findIndex((field) => field.value === firstField);
            let nextIndex = baseIndex + count;
            if (nextIndex >= fields.length) nextIndex = Math.max(0, fields.length - 1);

            const html = `
                <div class=\"search-condition\">
                    ${renderSearchSelect(nextIndex)}
                    <input type=\"text\"
                           name=\"searchValue[]\"
                           class=\"form-control search-input\"
                           placeholder=\"검색어 입력\">
                    <button type=\"button\" class=\"btn btn-danger remove-condition\">-</button>
                </div>
            `;

            $(`${conditionsId} .search-condition:last`).after(html);
            updateRemoveButtons();
            refreshTableLayout({ draw: false });
        });
    }

    function collectFilters() {
        const filters = [];
        $(`${conditionsId} .search-condition`).each(function () {
            const field = $(this).find('select').val();
            const value = String($(this).find('input').val() || '').trim();
            if (field && value) {
                filters.push({ field, value });
            }
        });

        const dateType = $(dateTypeId).val();
        let start = normalizeDate($(formId).find('input[name=\"dateStart\"]').val());
        let end = normalizeDate($(formId).find('input[name=\"dateEnd\"]').val());

        $(formId).find('input[name=\"dateStart\"]').val(start);
        $(formId).find('input[name=\"dateEnd\"]').val(end);

        if (dateType && (start || end)) {
            if (!start) start = end;
            if (!end) end = start;
            if (start > end) {
                notifySearchError('시작일은 종료일보다 클 수 없습니다.');
                return null;
            }
            if (dateType === 'created_at' || dateType === 'updated_at') {
                start = `${start} 00:00:00`;
                end = `${end} 23:59:59`;
            }
            filters.push({ field: dateType, value: { start, end } });
        }

        return filters;
    }

    function applyFilterNormalizer(filters) {
        if (typeof normalizeFilters !== 'function') return filters;
        const normalized = normalizeFilters(filters);
        return Array.isArray(normalized) ? normalized : filters;
    }

    function buildFilterUrl(filters) {
        const url = currentApiList();
        if (!filters.length) return url;
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + 'filters=' + encodeURIComponent(JSON.stringify(filters));
    }

    function getTableColumns(tableInstance) {
        if (!tableInstance || typeof tableInstance.settings !== 'function') return [];
        const settings = tableInstance.settings()[0];
        if (!settings) return [];
        return settings.aoColumns
            .filter((column) => column.data && column.sTitle)
            .filter((column) => column.bSearchable !== false)
            .filter((column) => !excludeFields.includes(column.data))
            .map((column) => ({
                value: column.data,
                label: normalizeText(column.sTitle).trim()
            }))
            .filter((column) => column.label);
    }

    function readInitialSearchFields() {
        const select = document.querySelector(`${conditionsId} .search-condition select`);
        if (!select) return [];
        return Array.from(select.options || [])
            .map((option) => ({
                value: String(option.value || '').trim(),
                label: normalizeText(option.textContent || option.label || '').trim(),
            }))
            .filter((field) => field.value && field.label)
            .filter((field) => !excludeFields.includes(field.value));
    }

    function getSearchFields() {
        return initialSearchFields.length ? initialSearchFields : getTableColumns(table);
    }

    function escapeOptionValue(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function renderSearchSelect(selectedIndex = 0) {
        const fields = getSearchFields();
        if (!fields.length) return '';
        let html = '<select name=\"searchField[]\" class=\"form-select form-select-sm search-field\">';
        fields.forEach((field, index) => {
            const selected = index === selectedIndex ? 'selected' : '';
            html += `<option value=\"${escapeOptionValue(field.value)}\" ${selected}>${escapeOptionValue(field.label)}</option>`;
        });
        html += '</select>';
        return html;
    }

    function updateRemoveButtons() {
        const rows = $(`${conditionsId} .search-condition`);
        rows.each(function (index) {
            const btn = $(this).find('.remove-condition');
            if (index === 0) {
                btn.hide();
            } else {
                btn.show();
            }
        });
    }

    function populateFirstSearchFields() {
        const fields = getSearchFields();
        const firstSelect = document.querySelector(`${conditionsId} .search-condition select`);
        if (!firstSelect || !fields.length) return;
        firstSelect.innerHTML = '';
        fields.forEach((field) => {
            const opt = document.createElement('option');
            opt.value = field.value;
            opt.textContent = field.label;
            if (defaultSearchField && field.value === defaultSearchField) {
                opt.selected = true;
            }
            firstSelect.appendChild(opt);
        });
    }

    function ensureConditionRowCount(count = 1) {
        const rows = document.querySelectorAll(`${conditionsId} .search-condition`);
        const currentCount = rows.length;
        const targetCount = Math.max(1, Number(count) || 1);
        if (currentCount >= targetCount) {
            return;
        }

        const fields = getSearchFields();
        for (let index = currentCount; index < targetCount; index += 1) {
            let nextIndex = 0;
            if (fields.length > 0) {
                nextIndex = Math.min(index, fields.length - 1);
            }

            const html = `
                <div class="search-condition">
                    ${renderSearchSelect(nextIndex)}
                    <input type="text"
                           name="searchValue[]"
                           class="form-control search-input"
                           placeholder="검색어 입력">
                    <button type="button" class="btn btn-danger remove-condition">-</button>
                </div>
            `;

            $(`${conditionsId} .search-condition:last`).after(html);
        }
    }

    function readCurrentSearchFormState() {
        const conditions = [];
        document.querySelectorAll(`${conditionsId} .search-condition`).forEach((row) => {
            const select = row.querySelector('select[name="searchField[]"]');
            const input = row.querySelector('input[name="searchValue[]"]');
            const field = String(select?.value || '').trim();
            const value = String(input?.value || '').trim();
            if (!field || !value) {
                return;
            }
            conditions.push({ field, value });
        });

        const dateType = String(document.querySelector(dateTypeId)?.value || '').trim();
        const dateStart = String(document.querySelector(`${formId} input[name="dateStart"]`)?.value || '').trim();
        const dateEnd = String(document.querySelector(`${formId} input[name="dateEnd"]`)?.value || '').trim();

        if (conditions.length === 0 && dateType === '' && dateStart === '' && dateEnd === '') {
            return null;
        }

        return {
            conditions,
            dateType,
            dateStart,
            dateEnd,
        };
    }

    function applySavedSearchFormState() {
        const savedState = getSavedSearchFormState();
        if (!savedState) {
            updateRemoveButtons();
            return;
        }

        const conditions = Array.isArray(savedState.conditions) ? savedState.conditions : [];
        ensureConditionRowCount(conditions.length || 1);

        const rows = Array.from(document.querySelectorAll(`${conditionsId} .search-condition`));
        rows.forEach((row, index) => {
            const select = row.querySelector('select[name="searchField[]"]');
            const input = row.querySelector('input[name="searchValue[]"]');
            const condition = conditions[index] || null;
            if (select) {
                select.value = condition?.field || select.value || '';
            }
            if (input) {
                input.value = condition?.value || '';
            }
        });

        rows.slice(Math.max(conditions.length, 1)).forEach((row) => row.remove());

        const dateTypeEl = document.getElementById(`${tableId}DateType`);
        if (dateTypeEl && String(savedState.dateType || '').trim() !== '') {
            dateTypeEl.value = String(savedState.dateType || '').trim();
        }

        const dateStartEl = document.querySelector(`${formId} input[name="dateStart"]`);
        const dateEndEl = document.querySelector(`${formId} input[name="dateEnd"]`);
        if (dateStartEl) {
            dateStartEl.value = String(savedState.dateStart || '').trim();
        }
        if (dateEndEl) {
            dateEndEl.value = String(savedState.dateEnd || '').trim();
        }

        updateRemoveButtons();

        const filters = collectFilters();
        if (filters === null || filters.length === 0) {
            return;
        }

        const normalizedFilters = applyFilterNormalizer(filters);
        const url = buildFilterUrl(normalizedFilters);
        table.ajax.url(url).load(() => {
            refreshTableLayout({ draw: true });
        });
    }

    function bindPeriodButtons() {
        window.__searchFormPeriodBound = true;
    }

    function notifySearchError(message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify('warning', message);
            return;
        }
        alert(message);
    }

    function bindDatePicker() {
        const form = document.querySelector(formId);
        if (!form || form.__searchDatePickerBound) return;
        form.__searchDatePickerBound = true;

        form.querySelectorAll('.admin-date').forEach((input) => {
            input.addEventListener('input', () => {
                input.value = normalizeDate(input.value);
                normalizeDateRange(input);
            });
            input.addEventListener('blur', () => {
                input.value = normalizeDate(input.value);
                normalizeDateRange(input);
            });
        });

        form.querySelectorAll('.date-icon').forEach((icon) => {
            icon.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const wrap = icon.closest('.date-input, .date-input-wrap');
                const input = wrap ? wrap.querySelector('input.admin-date, input[name=\"dateStart\"], input[name=\"dateEnd\"]') : null;
                if (input) openDatePicker(input);
            });
        });
    }

    function openDatePicker(input) {
        const picker = getSharedDatePicker();
        if (!picker || !input) return;
        picker.__target = input;
        if (typeof picker.clearDate === 'function') picker.clearDate();
        const value = normalizeDate(input.value);
        if (value) {
            const date = new Date(value);
            if (!Number.isNaN(date.getTime()) && typeof picker.setDate === 'function') {
                picker.setDate(date);
            }
        }
        picker.open({ anchor: input });
    }

    function getSharedDatePicker() {
        if (window.__searchFormDatePicker) return window.__searchFormDatePicker;

        let container = document.getElementById('today-picker');
        if (!container) {
            container = document.createElement('div');
            container.id = 'today-picker';
            container.className = 'picker is-hidden';
            document.body.appendChild(container);
        }

        if (!AdminPicker?.create) return null;
        const picker = AdminPicker.create({
            type: 'today',
            container
        });

        picker.subscribe((_, date) => {
            const input = picker.__target;
            if (!input || !date) return;
            input.value = formatDateValue(date);
            normalizeDateRange(input);
            picker.close();
        });
        window.__searchFormDatePicker = picker;
        return picker;
    }

    function normalizeDateRange(input) {
        const form = input.closest('form');
        if (!form) return;
        const start = form.querySelector('input[name=\"dateStart\"]');
        const end = form.querySelector('input[name=\"dateEnd\"]');
        if (!start || !end || !start.value || !end.value) return;
        if (input.name === 'dateStart' && start.value > end.value) end.value = start.value;
        if (input.name === 'dateEnd' && end.value < start.value) start.value = end.value;
    }

    function populateDateOptions(options) {
        const el = document.getElementById(`${tableId}DateType`);
        if (!el || !options?.length) return;
        el.innerHTML = options.map((option) => `<option value=\"${option.value}\">${option.label}</option>`).join('');
    }
}
