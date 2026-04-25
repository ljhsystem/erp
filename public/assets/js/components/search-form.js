// Path: /assets/js/components/search-form.js

export function SearchForm(config) {
    const {
        table,
        apiList,
        tableId,
        defaultSearchField,
        dateOptions,
        normalizeFilters,
        excludeFields = []
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

    applyInitialState();
    bindToggle();
    bindTooltips();
    bindSearchEvents();
    populateFirstSearchFields();
    populateDateOptions(dateOptions);
    bindPeriodButtons();

    function applyInitialState() {
        bodyEl?.classList.remove('hidden');
        containerEl?.classList.remove('collapsed');

        if (toggleBtnEl) {
            toggleBtnEl.textContent = '접기';
        }

        if (table && table.page.len() !== 10) {
            table.page.len(10).draw(false);
        }
    }

    function bindToggle() {
        if (!containerEl || !bodyEl || !toggleBtnEl) return;
        if (toggleBtnEl.__searchToggleBound) return;

        toggleBtnEl.__searchToggleBound = true;

        toggleBtnEl.addEventListener('click', () => {
            const hidden = !bodyEl.classList.contains('hidden');

            bodyEl.classList.toggle('hidden', hidden);
            containerEl.classList.toggle('collapsed', hidden);
            toggleBtnEl.textContent = hidden ? '열기' : '접기';

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    table?.columns.adjust();
                });
            });
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

                trigger.addEventListener('pointerdown', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                });

                trigger.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.__tooltipSuppressDocumentClickUntil = Date.now() + 250;

                    setTimeout(() => {
                        toggleTooltip(anchor, tooltip);
                    }, 0);
                });

                trigger.addEventListener('keydown', function (e) {
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
                tooltip.style.top = rect.bottom + 6 + 'px';
                tooltip.style.left = rect.left + 'px';
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

            tooltip.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        });

        if (!window.__tooltipGlobalBound) {
            window.__tooltipGlobalBound = true;

            document.addEventListener('click', function () {
                if (Date.now() < (window.__tooltipSuppressDocumentClickUntil || 0)) {
                    return;
                }

                closeAllTooltips();
            });
            document.addEventListener('pointerdown', function (e) {
                const target = e.target;
                if (target?.closest?.('.tooltip-container, .tooltip-trigger, .label-btn')) {
                    return;
                }

                closeAllTooltips();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeAllTooltips();
                }
            });
        }
    }

    function bindSearchEvents() {
        $(formId).off('submit.searchForm').on('submit.searchForm', function (e) {
            e.preventDefault();

            const filters = collectFilters();

            if (filters === null) {
                return;
            }

            const normalizedFilters = applyFilterNormalizer(filters);
            const url = buildFilterUrl(normalizedFilters);

            table.ajax.url(url).load(() => {
                table.columns.adjust();
                table.draw(false);
            });
        });

        $(document).off('click.searchFormRemove', '.remove-condition')
            .on('click.searchFormRemove', '.remove-condition', function () {
                const rows = $(`${conditionsId} .search-condition`);

                if (rows.length <= 1) {
                    alert('최소 1개의 검색조건은 유지해야 합니다.');
                    return;
                }

                $(this).closest('.search-condition').remove();
                updateRemoveButtons();
                table?.columns.adjust();
            });

        $(resetBtnId).off('click.searchFormReset').on('click.searchFormReset', function (e) {
            e.preventDefault();

            $(`${conditionsId} input[type="text"]`).val('');
            $(`${conditionsId}`).find('.search-condition:gt(0)').remove();

            $(formId).find('input[name="dateStart"]').val('');
            $(formId).find('input[name="dateEnd"]').val('');

            const dateTypeEl = document.getElementById(`${tableId}DateType`);
            if (dateTypeEl && dateOptions?.length) {
                dateTypeEl.value = dateOptions[0].value;
            }

            populateFirstSearchFields();
            updateRemoveButtons();

            table.ajax.url(apiList).load(() => {
                table.columns.adjust();
                table.draw(false);
            });
        });

        $(addBtnId).off('click.searchFormAdd').on('click.searchFormAdd', function () {
            const rows = $(`${conditionsId} .search-condition`);
            const count = rows.length;

            if (count >= MAX_CONDITION) {
                alert('검색조건은 최대 5개까지 추가할 수 있습니다.');
                return;
            }

            const firstField = rows.first().find('select').val();
            const fields = getTableColumns(table);
            const baseIndex = fields.findIndex((field) => field.value === firstField);

            let nextIndex = baseIndex + count;
            if (nextIndex >= fields.length) {
                nextIndex = fields.length - 1;
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
            updateRemoveButtons();
            table?.columns.adjust();
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
        let start = normalizeDateValue($(formId).find('input[name="dateStart"]').val());
        let end = normalizeDateValue($(formId).find('input[name="dateEnd"]').val());

        $(formId).find('input[name="dateStart"]').val(start);
        $(formId).find('input[name="dateEnd"]').val(end);

        if (dateType && (start || end)) {
            if (!start) start = end;
            if (!end) end = start;

            if (start > end) {
                notifySearchError('시작일은 종료일보다 늦을 수 없습니다.');
                return null;
            }

            if (dateType === 'created_at' || dateType === 'updated_at') {
                start = `${start} 00:00:00`;
                end = `${end} 23:59:59`;
            }

            filters.push({
                field: dateType,
                value: { start, end }
            });
        }

        return filters;
    }

    function normalizeDateValue(value) {
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

    function notifySearchError(message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify('warning', message);
            return;
        }

        alert(message);
    }

    function applyFilterNormalizer(filters) {
        if (typeof normalizeFilters !== 'function') {
            return filters;
        }

        const normalized = normalizeFilters(filters);
        return Array.isArray(normalized) ? normalized : filters;
    }

    function buildFilterUrl(filters) {
        if (!filters.length) {
            return apiList;
        }

        const separator = apiList.includes('?') ? '&' : '?';
        return apiList + separator + 'filters=' + encodeURIComponent(JSON.stringify(filters));
    }

    function getTableColumns(tableInstance) {
        if (!tableInstance || typeof tableInstance.settings !== 'function') {
            return [];
        }

        const settings = tableInstance.settings()[0];
        if (!settings) return [];

        return settings.aoColumns
            .filter((column) => column.data && column.sTitle)
            .filter((column) => column.bSearchable !== false)
            .filter((column) => !excludeFields.includes(column.data))
            .map((column) => {
                const label = stripHtml(column.sTitle).trim();
                if (!label) return null;

                return {
                    value: column.data,
                    label
                };
            })
            .filter(Boolean);
    }

    function renderSearchSelect(selectedIndex = 0) {
        const fields = getTableColumns(table);
        if (!fields.length) return '';

        let html = '<select name="searchField[]" class="form-select form-select-sm search-field">';

        fields.forEach((field, index) => {
            const selected = index === selectedIndex ? 'selected' : '';
            html += `<option value="${field.value}" ${selected}>${field.label}</option>`;
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
        const fields = getTableColumns(table);
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

    function bindPeriodButtons() {
        if (window.__searchFormPeriodBound) return;
        window.__searchFormPeriodBound = true;

        window.setPeriod = function (type) {
            const activeEl = document.activeElement;
            const btn = activeEl && activeEl.matches?.('[onclick*="setPeriod"]')
                ? activeEl
                : null;

            const form = btn?.closest('form') || document.querySelector('form[id$="SearchConditionsForm"]');
            if (!form) return;

            const today = new Date();
            let start = new Date(today);
            let end = new Date(today);

            switch (type) {
                case 'today':
                    break;
                case 'yesterday':
                    start.setDate(today.getDate() - 1);
                    end = new Date(start);
                    break;
                case '3days':
                    start.setDate(today.getDate() - 3);
                    break;
                case '7days':
                    start.setDate(today.getDate() - 7);
                    break;
                case '15days':
                    start.setDate(today.getDate() - 15);
                    break;
                case '1month':
                    start.setMonth(today.getMonth() - 1);
                    break;
                case '3months':
                    start.setMonth(today.getMonth() - 3);
                    break;
                case '6months':
                    start.setMonth(today.getMonth() - 6);
                    break;
                default:
                    return;
            }

            const format = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const $form = window.jQuery(form);
            $form.find('[name="dateStart"]').val(format(start));
            $form.find('[name="dateEnd"]').val(format(end));
            $form.trigger('submit');
        };
    }

    function stripHtml(html) {
        if (!html) return '';
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent.trim();
    }

    function populateDateOptions(options) {
        const el = document.getElementById(`${tableId}DateType`);
        if (!el || !options?.length) return;

        el.innerHTML = options.map((option) =>
            `<option value="${option.value}">${option.label}</option>`
        ).join('');
    }
}
