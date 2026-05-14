import '/public/assets/js/components/trash-manager.js';

(() => {
    const API = {
        fields: '/api/import/fields',
        formats: '/api/import/formats',
        detail: '/api/import/format',
        save: '/api/import/format/save',
        remove: '/api/import/format/delete',
        copy: '/api/import/format/copy',
        template: '/api/import/template',
        importTypes: '/api/settings/system/code/list?code_group=IMPORT_TYPE',
    };

    const EVIDENCE_UPLOAD_TYPES = new Set([
        'TAX_INVOICE',
        'CASH_RECEIPT',
        'CASH_RECEIPT_PURCHASE',
        'CASH_RECEIPT_SALES',
        'CARD_HOMETAX',
        'CARD_STATEMENT',
        'CARD_APPROVAL',
        'BANK_TRANSACTION',
    ]);

    const LEGACY_TYPE_MAP = {
        CARD: 'CARD_STATEMENT',
        BANK: 'BANK_TRANSACTION',
        TAX: 'TAX_INVOICE',
        DATA: 'TAX_INVOICE',
    };

    const text = {
        requestFailed: '\uC694\uCCAD \uCC98\uB9AC\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.',
        dataTypeSelect: '\uC790\uB8CC\uC720\uD615\uC744 \uC120\uD0DD\uD558\uC138\uC694',
        choose: '\uC0C1\uC138 JSON \uC790\uB3D9\uC800\uC7A5',
        orderChange: '\uC21C\uC11C \uBCC0\uACBD',
        excelColumnName: '\uC5D1\uC140 \uCEEC\uB7FC\uBA85',
        remove: '-\uC0AD\uC81C',
        emptyType: '\uC790\uB8CC\uC720\uD615\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
        noFormats: '\uC120\uD0DD\uD55C \uC790\uB8CC\uC720\uD615\uC758 \uC591\uC2DD\uC774 \uC5C6\uC2B5\uB2C8\uB2E4.',
        createFirst: '\uC591\uC2DD \uCD94\uAC00\uB97C \uB20C\uB7EC \uC0AC\uC6A9\uC790 \uC591\uC2DD\uC744 \uB9CC\uB4E4\uC5B4\uC8FC\uC138\uC694.',
        defaultFormat: '\uAE30\uBCF8\uC591\uC2DD',
        cannotDeleteDefault: '\uAE30\uBCF8 \uC591\uC2DD\uC740 \uC0AD\uC81C\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.',
        selectDeleteTarget: '\uC0AD\uC81C\uD560 \uC591\uC2DD\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
        confirmDelete: '\uC815\uB9D0 \uC0AD\uC81C\uD558\uC2DC\uACA0\uC2B5\uB2C8\uAE4C?',
        deleted: '\uC591\uC2DD\uC774 \uC0AD\uC81C\uB418\uC5C8\uC2B5\uB2C8\uB2E4.',
        saved: '\uC591\uC2DD\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.',
        copied: '\uC591\uC2DD\uC774 \uBCF5\uC0AC\uB418\uC5C8\uC2B5\uB2C8\uB2E4.',
        copyTarget: '\uBCF5\uC0AC\uD560 \uC591\uC2DD\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
        downloadTarget: '\uB2E4\uC6B4\uB85C\uB4DC\uD560 \uC591\uC2DD\uC744 \uC120\uD0DD\uD558\uC138\uC694.',
        enterFormatName: '\uC591\uC2DD\uBA85\uC744 \uC785\uB825\uD558\uC138\uC694.',
        duplicateField: '\uC2DC\uC2A4\uD15C \uD544\uB4DC\uB294 \uC911\uBCF5 \uC120\uD0DD\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4',
    };

    const formatTypeFilterEl = document.getElementById('formatTypeFilter');
    const formatListEl = document.getElementById('formatList');
    const formatIdEl = document.getElementById('formatId');
    const formatNameEl = document.getElementById('formatName');
    const formatDataTypeEl = document.getElementById('formatDataType');
    const formatIsDefaultEl = document.getElementById('formatIsDefault');
    const columnBodyEl = document.getElementById('formatColumnBody');
    const formatColumnTableEl = document.getElementById('formatColumnTable');
    const columnToggleAllEls = Array.from(document.querySelectorAll('.format-column-toggle-all'));
    const newFormatBtn = document.getElementById('newFormatBtn');
    const formatTrashBtn = document.getElementById('formatTrashBtn');
    const addColumnBtn = document.getElementById('addColumnBtn');
    const saveFormatBtn = document.getElementById('saveFormatBtn');
    const deleteFormatBtn = document.getElementById('deleteFormatBtn');
    const closeFormatBtn = document.getElementById('closeFormatBtn');
    const copyFormatBtn = document.getElementById('copyFormatBtn');
    const downloadCurrentFormatBtn = document.getElementById('downloadCurrentFormatBtn');
    const newFormatModalEl = document.getElementById('newFormatModal');
    const newFormatNameEl = document.getElementById('newFormatName');
    const newFormatDataTypeEl = document.getElementById('newFormatDataType');
    const confirmNewFormatBtn = document.getElementById('confirmNewFormatBtn');
    const newFormatModal = window.bootstrap && newFormatModalEl
        ? bootstrap.Modal.getOrCreateInstance(newFormatModalEl)
        : null;

    let dataTypes = [];
    let systemFields = [];
    let formats = [];
    let activeFormatId = '';

    document.addEventListener('trash:changed', (event) => {
        if (event.detail?.type === 'dataFormat') {
            void loadFormats(activeFormatId).catch((error) => notify('error', error.message));
        }
    });

    document.addEventListener('trash:detail-render', (event) => {
        if (event.detail?.type !== 'dataFormat') return;
        const row = event.detail.data || {};
        const detailEl = event.detail.modal?.querySelector('.trash-detail');
        if (!detailEl) return;

        detailEl.innerHTML = `
            <div class="small">
                <dl class="row mb-0">
                    <dt class="col-4">\uC591\uC2DD\uBA85</dt><dd class="col-8">${escapeHtml(row.format_name || '-')}</dd>
                    <dt class="col-4">\uC790\uB8CC\uC720\uD615</dt><dd class="col-8">${escapeHtml(typeLabel(row.data_type) || row.data_type || '-')}</dd>
                    <dt class="col-4">\uAE30\uBCF8\uC591\uC2DD</dt><dd class="col-8">${Number(row.is_default || 0) === 1 ? '\uC608' : '\uC544\uB2C8\uC624'}</dd>
                    <dt class="col-4">\uC0AD\uC81C\uC77C\uC2DC</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
                    <dt class="col-4">\uC0AD\uC81C\uC790</dt><dd class="col-8">${escapeHtml(row.deleted_by || '-')}</dd>
                </dl>
            </div>
        `;
    });

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.dataFormat = function (row = {}) {
        return `
            <td>${escapeHtml(row.format_name || '')}</td>
            <td>${escapeHtml(typeLabel(row.data_type) || row.data_type || '')}</td>
            <td>${Number(row.is_default || 0) === 1 ? '\uC608' : ''}</td>
            <td>${escapeHtml(row.deleted_at || '')}</td>
            <td>${escapeHtml(row.deleted_by || '')}</td>
            <td class="text-center">
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id || '')}">\uBCF5\uC6D0</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id || '')}">\uC601\uAD6C\uC0AD\uC81C</button>
            </td>
        `;
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, { cache: 'no-store', ...options });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.success === false) {
            throw new Error(json.message || text.requestFailed);
        }
        return json;
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        console[type === 'error' ? 'error' : 'log'](message);
    }

    function normalizeTypeRows(rows) {
        return rows
            .map((row) => {
                const rawCode = String(row.code ?? row.value ?? '').trim().toUpperCase();
                const code = LEGACY_TYPE_MAP[rawCode] || rawCode;
                return {
                    code,
                    code_name: String(row.code_name ?? row.label ?? row.code ?? row.value ?? '').trim(),
                    is_active: Number(row.is_active ?? 1),
                };
            })
            .filter((row) => row.code && row.is_active === 1 && EVIDENCE_UPLOAD_TYPES.has(row.code))
            .filter((row, index, list) => list.findIndex((item) => item.code === row.code) === index);
    }

    async function loadDataTypes() {
        const json = await fetchJson(API.importTypes);
        const rows = Array.isArray(json) ? json : (json.data || []);
        dataTypes = normalizeTypeRows(rows);
        renderDataTypeSelects();
    }

    function renderDataTypeSelects() {
        [formatTypeFilterEl, formatDataTypeEl, newFormatDataTypeEl].forEach((select) => {
            if (!select) return;

            const previous = select.value;
            const emptyLabel = select.dataset.emptyLabel || text.dataTypeSelect;
            select.innerHTML = `<option value="">${escapeHtml(emptyLabel)}</option>` + dataTypes.map((type) => `
                <option value="${escapeHtml(type.code)}">${escapeHtml(type.code_name)} (${escapeHtml(type.code)})</option>
            `).join('');

            select.value = dataTypes.some((type) => type.code === previous) ? previous : '';
            enhanceDataTypeSelect(select);
        });
    }

    function enhanceDataTypeSelect(select) {
        if (!window.jQuery?.fn?.select2 || !select) return;

        const $select = window.jQuery(select);
        const modalParent = $select.closest('.modal');
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.select2({
            width: '100%',
            language: 'ko',
            placeholder: select.dataset.emptyLabel || text.dataTypeSelect,
            minimumResultsForSearch: 0,
            allowClear: true,
            dropdownParent: modalParent.length ? modalParent.first() : window.jQuery(document.body),
        });

        if (select === formatTypeFilterEl) {
            $select
                .off('change.dataFormatType select2:select.dataFormatType select2:clear.dataFormatType')
                .on('change.dataFormatType select2:select.dataFormatType select2:clear.dataFormatType', () => {
                    void loadFormats().catch((error) => notify('error', error.message));
                });
        }
    }

    function currentType() {
        return getSelectValue(formatTypeFilterEl);
    }

    function typeLabel(type) {
        const value = String(type || '').trim();
        return dataTypes.find((row) => row.code === value)?.code_name || value;
    }

    function fieldOptions(selected = '', disabledValues = new Set()) {
        const grouped = systemFields.reduce((groups, field) => {
            const group = field.group || 'Import';
            if (!groups.has(group)) groups.set(group, []);
            groups.get(group).push(field);
            return groups;
        }, new Map());

        return `
            <option value="" ${selected === '' ? 'selected' : ''}>${text.choose}</option>
        ` + Array.from(grouped.entries()).map(([group, fields]) => `
            <optgroup label="${escapeHtml(group)}">
                ${fields.map((field) => {
                    const value = String(field.value || '');
                    const disabled = value !== '' && value !== selected && disabledValues.has(value);
                    return `
                        <option value="${escapeHtml(value)}" ${value === selected ? 'selected' : ''} ${disabled ? 'disabled' : ''}>
                            ${escapeHtml(field.label)} (${escapeHtml(value)})
                        </option>
                    `;
                }).join('')}
            </optgroup>
        `).join('');
    }

    function systemFieldGroup(value = '') {
        const key = String(value || '').trim();
        return systemFields.find((field) => String(field.value || '') === key)?.group || '';
    }

    function systemFieldTone(value = '') {
        const group = systemFieldGroup(value);
        if (group === '기준정보') return 'standard';
        if (group === '기초정보') return 'basic';
        return '';
    }

    function syncSystemFieldTone(row) {
        if (!row) return;
        const tone = systemFieldTone(systemFieldValue(row.querySelector('.system-field-name')));
        row.classList.toggle('format-column-row-standard', tone === 'standard');
        row.classList.toggle('format-column-row-basic', tone === 'basic');
    }

    function syncSystemFieldTones() {
        columnBodyEl?.querySelectorAll('.format-column-row').forEach(syncSystemFieldTone);
    }

    function columnRow(column = {}, index = 0) {
        const order = Number(column.column_order || index + 1);
        const excelColumnIndex = Number(column.excel_column_index || order);
        const requirementMode = Number(column.is_required || 0);
        const requirementName = `requirement-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        const tone = systemFieldTone(column.system_field_name || '');
        const toneClass = tone ? ` format-column-row-${tone}` : '';
        return `
            <tr class="format-column-row${toneClass}">
                <td class="format-order-cell">
                    <span class="column-drag-handle" title="${text.orderChange}">
                        <i class="bi bi-grip-vertical"></i>
                    </span>
                    <span class="column-order-label">${order}</span>
                    <input type="hidden" class="column-order" value="${order}">
                    <input type="hidden" class="excel-column-index" value="${excelColumnIndex}">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm excel-column-name" value="${escapeHtml(column.excel_column_name || '')}" placeholder="${text.excelColumnName}">
                </td>
                <td>
                    <select class="form-select form-select-sm system-field-name">${fieldOptions(column.system_field_name || '')}</select>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input is-visible" ${Number(column.is_visible ?? 1) === 1 ? 'checked' : ''}>
                </td>
                <td class="text-center">
                    <div class="requirement-radio-group" role="radiogroup" aria-label="필수구분">
                        <label class="requirement-radio-label requirement-none" title="선택없음">
                            <input type="radio" class="requirement-radio" name="${requirementName}" value="0" ${requirementMode === 0 ? 'checked' : ''}>
                            <span></span>
                        </label>
                        <label class="requirement-radio-label requirement-optional" title="선택">
                            <input type="radio" class="requirement-radio" name="${requirementName}" value="2" ${requirementMode === 2 ? 'checked' : ''}>
                            <span></span>
                        </label>
                        <label class="requirement-radio-label requirement-required" title="필수">
                            <input type="radio" class="requirement-radio" name="${requirementName}" value="1" ${requirementMode === 1 ? 'checked' : ''}>
                            <span></span>
                        </label>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-link btn-sm remove-column-btn format-text-action text-danger">${text.remove}</button>
                </td>
            </tr>
        `;
    }

    function renumberColumns() {
        columnBodyEl?.querySelectorAll('tr:not(.format-fixed-column-row)').forEach((row, index) => {
            const order = index + 1;
            row.querySelector('.column-order-label').textContent = String(order);
            row.querySelector('.column-order').value = String(order);
            row.querySelector('.excel-column-index').value = String(order);
        });
    }

    function systemFieldValue(select) {
        if (!select) return '';
        if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            return String(window.jQuery(select).val() || '').trim();
        }
        return String(select.value || '').trim();
    }

    function clearDuplicateSystemFields() {
        const seen = new Set();
        let changed = false;
        Array.from(columnBodyEl?.querySelectorAll('.system-field-name') || []).forEach((select) => {
            const value = systemFieldValue(select);
            if (!value) {
                rememberSystemFieldValue(select);
                return;
            }
            if (seen.has(value)) {
                notify('warning', `${text.duplicateField}: ${duplicateSystemFieldLabel(value)}`);
                setSelectValue(select, '');
                rememberSystemFieldValue(select);
                changed = true;
                return;
            }
            seen.add(value);
            rememberSystemFieldValue(select);
        });
        return changed;
    }

    function selectedSystemFieldValues(exceptSelect = null) {
        return new Set(Array.from(columnBodyEl?.querySelectorAll('.system-field-name') || [])
            .filter((select) => select !== exceptSelect)
            .map((select) => systemFieldValue(select))
            .filter(Boolean));
    }

    function updateSystemFieldOptionLocks(select) {
        if (!select) return;
        const current = systemFieldValue(select);
        const selectedValues = selectedSystemFieldValues(select);
        Array.from(select.options || []).forEach((option) => {
            const value = String(option.value || '').trim();
            option.disabled = value !== '' && value !== current && selectedValues.has(value);
        });
    }

    function syncSystemFieldOptions() {
        clearDuplicateSystemFields();
        const rows = Array.from(columnBodyEl?.querySelectorAll('tr') || []);
        const selectedValues = selectedSystemFieldValues();

        rows.forEach((row) => {
            const select = row.querySelector('.system-field-name');
            if (!select) return;
            const current = systemFieldValue(select);
            select.innerHTML = fieldOptions(current, new Set(selectedValues));
            refreshSystemFieldSelect(select);
        });
    }

    function isSystemFieldSelectedElsewhere(value, select) {
        const selectedValue = String(value || '').trim();
        if (!selectedValue || !columnBodyEl) return false;

        return Array.from(columnBodyEl.querySelectorAll('.system-field-name')).some((fieldSelect) => (
            fieldSelect !== select
            && systemFieldValue(fieldSelect) === selectedValue
        ));
    }

    function isDuplicateSystemField(select) {
        const value = systemFieldValue(select);
        return isSystemFieldSelectedElsewhere(value, select);
    }

    function duplicateSystemFieldLabel(value) {
        return systemFields.find((field) => field.value === value)?.label || value;
    }

    function rememberSystemFieldValue(select) {
        if (!select) return;
        select.dataset.previousSystemField = systemFieldValue(select);
    }

    function restorePreviousSystemFieldValue(select) {
        const previous = select?.dataset?.previousSystemField || '';
        setSelectValue(select, previous);
    }

    function systemFieldSelect2Options(select) {
        const modalParent = window.jQuery(select).closest('.modal');
        return {
            width: '100%',
            language: 'ko',
            placeholder: text.choose,
            allowClear: true,
            minimumResultsForSearch: 0,
            dropdownAutoWidth: true,
            dropdownParent: modalParent.length ? modalParent.first() : window.jQuery(document.body),
        };
    }

    function refreshSystemFieldSelect(select) {
        if (!window.jQuery?.fn?.select2 || !select) return;
        const $select = window.jQuery(select);
        const current = $select.val();
        if (select.dataset.previousSystemField === undefined) {
            select.dataset.previousSystemField = String(current || '');
        }
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        $select.select2(systemFieldSelect2Options(select));
        $select
            .off('select2:opening.dataFormatField select2:selecting.dataFormatField select2:select.dataFormatField change.dataFormatField')
            .on('select2:opening.dataFormatField', () => {
                rememberSystemFieldValue(select);
                updateSystemFieldOptionLocks(select);
            })
            .on('select2:selecting.dataFormatField', (event) => {
                const data = event.params?.args?.data || event.params?.data || {};
                const value = String(data.id || data.element?.value || '').trim();
                if (!data.element?.disabled && !isSystemFieldSelectedElsewhere(value, select)) return;

                event.preventDefault();
                notify('warning', `${text.duplicateField}: ${duplicateSystemFieldLabel(value)}`);
            })
            .on('select2:select.dataFormatField change.dataFormatField', () => {
                window.setTimeout(() => {
                    const value = systemFieldValue(select);
                    if (!isSystemFieldSelectedElsewhere(value, select)) {
                        rememberSystemFieldValue(select);
                        return;
                    }

                    notify('warning', `${text.duplicateField}: ${duplicateSystemFieldLabel(value)}`);
                    restorePreviousSystemFieldValue(select);
                    syncSystemFieldOptions();
                }, 0);
            });
        $select.val(current || '').trigger('change.select2');
    }

    function enhanceSystemFieldSelects() {
        if (!window.jQuery?.fn?.select2 || !columnBodyEl) return;
        window.jQuery(columnBodyEl).find('.system-field-name').each(function () {
            refreshSystemFieldSelect(this);
        });
    }

    function updateColumnToggleState() {
        const rows = Array.from(columnBodyEl?.querySelectorAll('tr') || []);
        columnToggleAllEls.forEach((checkbox) => {
            const target = checkbox.dataset.target || '';
            const rowChecks = rows
                .map((row) => row.querySelector(target))
                .filter(Boolean);
            const checkedCount = rowChecks.filter((input) => input.checked).length;

            checkbox.checked = rowChecks.length > 0 && checkedCount === rowChecks.length;
            checkbox.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
            checkbox.disabled = rowChecks.length === 0;
        });
    }

    function initColumnSortable() {
        const $ = window.jQuery;
        if (!$ || typeof $.fn.sortable !== 'function' || !columnBodyEl) return;

        const $body = $(columnBodyEl);
        if ($body.data('ui-sortable')) {
            $body.sortable('destroy');
        }

        $body.sortable({
            handle: '.column-drag-handle',
            items: '> tr:not(.format-fixed-column-row)',
            axis: 'y',
            tolerance: 'pointer',
            forcePlaceholderSize: true,
            placeholder: 'format-column-placeholder',
            start(_, ui) {
                const colspan = Math.max(ui.item.children('td, th').length, 1);
                ui.placeholder.height(ui.item.outerHeight()).html(`<td colspan="${colspan}"></td>`);
                ui.item.addClass('format-column-dragging');
            },
            helper(_, tr) {
                const $originals = tr.children();
                const $helper = tr.clone().addClass('format-column-helper');
                $helper.children().each(function (index) {
                    $(this).width($originals.eq(index).outerWidth());
                });
                return $helper;
            },
            stop(_, ui) {
                ui.item.removeClass('format-column-dragging');
                renumberColumns();
                syncSystemFieldOptions();
            },
        }).disableSelection();
    }

    function findScrollParent(node) {
        let current = node?.parentElement || null;
        while (current && current !== document.body && current !== document.documentElement) {
            const style = window.getComputedStyle(current);
            if (/(auto|scroll)/.test(style.overflowY || '')) return current;
            current = current.parentElement;
        }
        return null;
    }

    function updateFormatColumnStickyTop() {
        if (!formatColumnTableEl) return;
        const nav = document.querySelector('.top-nav.fixed-top, .top-nav');
        const navBottom = nav ? nav.getBoundingClientRect().bottom : 0;
        const scrollParent = findScrollParent(formatColumnTableEl);
        const scrollTop = scrollParent ? scrollParent.getBoundingClientRect().top : 0;
        formatColumnTableEl.style.setProperty('--format-column-sticky-top', `${Math.max(0, Math.ceil(navBottom - scrollTop))}px`);
    }

    function renderColumns(columns = []) {
        if (!columnBodyEl) return;
        columnBodyEl.innerHTML = (columns.length ? columns.map(columnRow).join('') : '') + fixedDisplayColumnRows();
        renumberColumns();
        enhanceSystemFieldSelects();
        syncSystemFieldOptions();
        syncSystemFieldTones();
        initColumnSortable();
        updateFormatColumnStickyTop();
        updateColumnToggleState();
    }

    function currentColumnRows() {
        return Array.from(columnBodyEl?.querySelectorAll('tr:not(.format-fixed-column-row)') || []).map((row, index) => ({
            column_order: index + 1,
            excel_column_index: index + 1,
            excel_column_name: row.querySelector('.excel-column-name')?.value?.trim() || '',
            system_field_name: systemFieldValue(row.querySelector('.system-field-name')) || null,
            is_visible: row.querySelector('.is-visible')?.checked ? 1 : 0,
            is_required: Number(row.querySelector('.requirement-radio:checked')?.value || 0),
        })).filter((row) => row.excel_column_name !== '');
    }

    function fixedDisplayColumnRows() {
        return '';
    }

    function isDefaultFormat(format) {
        return Number(format?.is_default || 0) === 1;
    }

    function canDeleteFormat(format) {
        return !!format;
    }

    function deleteDisabledMessage(format) {
        return '';
    }

    function updateDeleteButtonState() {
        const selected = formats.find((format) => format.id === formatIdEl?.value) || null;
        if (deleteFormatBtn) {
            deleteFormatBtn.disabled = !canDeleteFormat(selected);
            deleteFormatBtn.title = deleteDisabledMessage(selected);
        }
        if (copyFormatBtn) {
            copyFormatBtn.disabled = !selected;
            copyFormatBtn.title = selected ? '' : text.copyTarget;
            copyFormatBtn.classList.toggle('btn-primary', !!selected);
            copyFormatBtn.classList.toggle('btn-outline-secondary', !selected);
        }
        if (downloadCurrentFormatBtn) {
            downloadCurrentFormatBtn.disabled = !selected;
            downloadCurrentFormatBtn.title = selected ? '' : text.downloadTarget;
        }
    }

    function renderFormats() {
        if (!formatListEl) return;

        if (!currentType()) {
            formatListEl.innerHTML = '';
            updateDeleteButtonState();
            return;
        }

        if (!formats.length) {
            formatListEl.innerHTML = `<div class="list-group-item text-muted">${text.noFormats}<br><small>${text.createFirst}</small></div>`;
            updateDeleteButtonState();
            return;
        }

        const selectedId = formatIdEl?.value || '';
        formatListEl.innerHTML = formats.map((format) => {
            const selected = format.id === selectedId;
            const disabledMessage = deleteDisabledMessage(format);
            const deleteDisabled = !canDeleteFormat(format);
            return `
                <div class="list-group-item ${selected ? 'active' : ''}" data-format-row="${escapeHtml(format.id)}">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <button type="button" class="btn btn-link p-0 text-start flex-grow-1 format-select-btn ${selected ? 'text-white' : 'text-body'}" data-id="${escapeHtml(format.id)}">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <strong>${escapeHtml(format.format_name)}</strong>
                                ${isDefaultFormat(format) ? `<span class="badge bg-primary-subtle text-primary-emphasis">${text.defaultFormat}</span>` : ''}
                                <span class="badge ${selected ? 'bg-light text-dark' : 'bg-secondary'}">${escapeHtml(typeLabel(format.data_type))}</span>
                            </div>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm format-list-delete-btn" data-delete-id="${escapeHtml(format.id)}" ${deleteDisabled ? 'disabled' : ''} title="${escapeHtml(disabledMessage)}">
                            ${text.remove.replace('-', '')}
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        updateDeleteButtonState();
    }

    function setSelectValue(select, value) {
        if (!select) return;
        select.value = value || '';
        if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).val(value || '').trigger('change.select2');
        }
    }

    function getSelectValue(select) {
        if (!select) return '';
        if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            return String(window.jQuery(select).val() || '').trim();
        }
        return String(select.value || '').trim();
    }

    function resetForm(name = '', dataType = '') {
        if (!formatIdEl) return;
        activeFormatId = '';
        formatIdEl.value = '';
        formatNameEl.value = name;
        setSelectValue(formatDataTypeEl, dataType);
        formatIsDefaultEl.checked = false;
        renderColumns([]);
        renderFormats();
        updateDeleteButtonState();
    }

    async function loadFormats(selectedId = '') {
        const dataType = currentType();

        if (!dataType) {
            formats = [];
            systemFields = [];
            resetForm('', '');
            return;
        }

        await loadFields(dataType);
        const json = await fetchJson(`${API.formats}?data_type=${encodeURIComponent(dataType)}&include_columns=1`);
        formats = json.data || [];
        renderFormats();

        if (selectedId) {
            await selectFormat(selectedId);
        } else if (formats.length > 0) {
            await selectFormat(formats[0].id);
        } else {
            resetForm('', dataType);
        }
    }

    async function loadFields(dataType = getSelectValue(formatDataTypeEl) || currentType()) {
        if (!dataType) {
            systemFields = [];
            return;
        }

        const json = await fetchJson(`${API.fields}?data_type=${encodeURIComponent(dataType)}`);
        systemFields = json.data || [];
    }

    async function selectFormat(id) {
        const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);
        const data = json.data || {};
        await loadFields(data.data_type || currentType());
        activeFormatId = data.id || id || '';
        formatIdEl.value = activeFormatId;
        formatNameEl.value = data.format_name || '';
        setSelectValue(formatDataTypeEl, data.data_type || currentType());
        formatIsDefaultEl.checked = Number(data.is_default || 0) === 1;
        renderColumns(data.columns || []);
        renderFormats();
        updateDeleteButtonState();
    }

    async function reloadFieldsForEditorType() {
        const dataType = getSelectValue(formatDataTypeEl);
        const columns = currentColumnRows();
        await loadFields(dataType);
        renderColumns(columns);
    }

    function validateUniqueSystemFields(columns) {
        const seen = new Map();
        for (const column of columns) {
            if (!column.system_field_name) continue;
            if (seen.has(column.system_field_name)) {
                const label = systemFields.find((field) => field.value === column.system_field_name)?.label || column.system_field_name;
                throw new Error(`${text.duplicateField}: ${label}`);
            }
            seen.set(column.system_field_name, true);
        }
    }

    function collectColumns() {
        renumberColumns();
        const columns = Array.from(columnBodyEl?.querySelectorAll('tr') || []).map((row, index) => ({
            column_order: index + 1,
            excel_column_index: index + 1,
            excel_column_name: row.querySelector('.excel-column-name')?.value?.trim() || '',
            system_field_name: systemFieldValue(row.querySelector('.system-field-name')) || null,
            is_visible: row.querySelector('.is-visible')?.checked ? 1 : 0,
            is_required: Number(row.querySelector('.requirement-radio:checked')?.value || 0),
        })).filter((row) => row.excel_column_name !== '');

        validateUniqueSystemFields(columns);
        return columns;
    }

    async function saveFormat() {
        const selectedId = String(formatIdEl.value || activeFormatId || '').trim();
        const payload = {
            id: selectedId,
            format_name: formatNameEl.value.trim(),
            data_type: getSelectValue(formatDataTypeEl),
            is_default: formatIsDefaultEl.checked ? 1 : 0,
            columns: collectColumns(),
        };

        if (!payload.data_type) {
            notify('warning', text.dataTypeSelect);
            return;
        }

        const json = await fetchJson(API.save, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        notify('success', text.saved);
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'data-format:saved',
                dataType: payload.data_type,
                formatId: json.id || payload.id || '',
            }, window.location.origin);
        }
        activeFormatId = json.id || payload.id || activeFormatId;
        if (formatIdEl) {
            formatIdEl.value = activeFormatId;
        }
        if (formatTypeFilterEl && formatTypeFilterEl.value !== payload.data_type) {
            setSelectValue(formatTypeFilterEl, payload.data_type);
        }
        await loadFormats(json.id || payload.id);
    }

    async function deleteFormat(id = formatIdEl.value) {
        const format = formats.find((item) => item.id === id) || null;
        const disabledMessage = deleteDisabledMessage(format);
        if (!id) {
            notify('warning', text.selectDeleteTarget);
            return;
        }
        if (disabledMessage) {
            notify('warning', disabledMessage);
            return;
        }
        if (!window.confirm(text.confirmDelete)) return;

        await fetchJson(API.remove, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        notify('success', text.deleted);
        await loadFormats();
    }

    async function copyFormat() {
        const id = formatIdEl.value;
        if (!id) {
            notify('warning', text.copyTarget);
            return;
        }
        const json = await fetchJson(API.copy, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        });
        notify('success', text.copied);
        await loadFormats(json.id || '');
    }

    function downloadCurrentFormat() {
        const id = String(formatIdEl?.value || activeFormatId || '').trim();
        if (!id) {
            notify('warning', text.downloadTarget);
            return;
        }
        window.location.href = `${API.template}?format_id=${encodeURIComponent(id)}`;
    }

    function openNewFormatModal() {
        if (newFormatNameEl) newFormatNameEl.value = '';
        setSelectValue(newFormatDataTypeEl, currentType());
        if (newFormatModal) {
            newFormatModal.show();
            setTimeout(() => newFormatNameEl?.focus(), 150);
            return;
        }
        resetForm('', currentType());
    }

    async function confirmNewFormat() {
        const name = newFormatNameEl?.value?.trim() || '';
        const dataType = newFormatDataTypeEl?.value || currentType();
        if (name === '') {
            notify('warning', text.enterFormatName);
            return;
        }
        if (!dataType) {
            notify('warning', text.dataTypeSelect);
            return;
        }
        setSelectValue(formatTypeFilterEl, dataType);
        await loadFields(dataType);
        resetForm(name, dataType);
        newFormatModal?.hide();
    }

    formatTypeFilterEl?.addEventListener('change', () => {
        void loadFormats().catch((error) => notify('error', error.message));
    });

    formatDataTypeEl?.addEventListener('change', () => {
        void reloadFieldsForEditorType().catch((error) => notify('error', error.message));
    });

    formatListEl?.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('[data-delete-id]');
        if (deleteButton) {
            void deleteFormat(deleteButton.dataset.deleteId).catch((error) => notify('error', error.message));
            return;
        }
        const button = event.target.closest('[data-id]');
        if (button) void selectFormat(button.dataset.id);
    });

    columnBodyEl?.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-column-btn');
        if (!button) return;
        button.closest('tr')?.remove();
        renumberColumns();
        syncSystemFieldOptions();
        updateColumnToggleState();
    });

    columnBodyEl?.addEventListener('change', (event) => {
        const systemFieldSelect = event.target.closest('.system-field-name');
        if (systemFieldSelect) {
            if (isDuplicateSystemField(systemFieldSelect)) {
                const value = systemFieldValue(systemFieldSelect);
                notify('warning', `${text.duplicateField}: ${duplicateSystemFieldLabel(value)}`);
                restorePreviousSystemFieldValue(systemFieldSelect);
            } else {
                rememberSystemFieldValue(systemFieldSelect);
            }
            syncSystemFieldOptions();
            syncSystemFieldTone(systemFieldSelect.closest('tr'));
        }
        if (event.target.closest('.is-visible, .requirement-radio')) {
            updateColumnToggleState();
        }
    });

    columnToggleAllEls.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const target = checkbox.dataset.target || '';
            columnBodyEl?.querySelectorAll(target).forEach((input) => {
                input.checked = checkbox.checked;
            });
            updateColumnToggleState();
        });
    });

    newFormatBtn?.addEventListener('click', openNewFormatModal);
    formatTrashBtn?.addEventListener('click', () => {
        const modalEl = document.getElementById('dataFormatTrashModal');
        if (!modalEl) return;
        bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).show();
    });
    confirmNewFormatBtn?.addEventListener('click', () => void confirmNewFormat().catch((error) => notify('error', error.message)));
    addColumnBtn?.addEventListener('click', () => {
        columnBodyEl.insertAdjacentHTML('beforeend', columnRow({}, columnBodyEl.querySelectorAll('tr').length));
        renumberColumns();
        enhanceSystemFieldSelects();
        syncSystemFieldOptions();
        syncSystemFieldTones();
        initColumnSortable();
        updateColumnToggleState();
    });
    saveFormatBtn?.addEventListener('click', () => void saveFormat().catch((error) => notify('error', error.message)));
    downloadCurrentFormatBtn?.addEventListener('click', downloadCurrentFormat);
    deleteFormatBtn?.addEventListener('click', () => void deleteFormat().catch((error) => notify('error', error.message)));
    closeFormatBtn?.addEventListener('click', () => {
        const modalEl = window.parent?.document?.getElementById('dataFormatModal');
        if (modalEl && window.parent?.bootstrap) {
            window.parent.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            return;
        }
        window.close();
    });
    copyFormatBtn?.addEventListener('click', () => void copyFormat().catch((error) => notify('error', error.message)));
    window.addEventListener('resize', updateFormatColumnStickyTop, { passive: true });

    (async () => {
        await loadDataTypes();
        await loadFormats();
        updateFormatColumnStickyTop();
    })().catch((error) => notify('error', error.message));
})();
