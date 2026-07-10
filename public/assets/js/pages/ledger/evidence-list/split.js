export function createEvidenceSplitModule({
    state,
    API,
    notify,
    updateSummary,
    escapeHtml,
    mapped,
    valueText,
    compareFormatColumns,
    editFieldKey,
    editFieldValue,
    editableColumnsForRow,
    renderEditInput,
    renderFieldBadge,
    isAmountColumn,
    infoColumnTone,
    editInputType,
    requirementMode,
    requirementStar,
    businessRefPickerForColumn,
    bankCodePickerForColumn,
    DISPLAY_CODE_FIELDS,
    codeValueForField,
    normalizeCodeKey,
    CODE_NAME_ALIASES,
    codeDisplayName,
    normalizeTimeInputValue,
    explicitDateTimeValueForColumn,
    normalizeDateInputValue,
    isDateTimeColumn,
    formatNumber,
    amount,
    firstPayloadText,
    bindCommonNumberInput,
    bindDateEditInput,
    initEvidenceRefSelect,
    initCodeSelectControls,
    selectValueForSave,
    selectTextForSave,
    businessProjectRuleMessage,
    pad2,
    AdminPicker,
    evidenceTypePolicy,
}) {
    let splitColumnsCache = [];

    function currentSplitPolicy() {
        return evidenceTypePolicy(state.currentType || '') || {};
    }

    function splitAliasKey(key = '') {
        const aliases = currentSplitPolicy().splitKeyAliases || {};
        return aliases[String(key || '').trim()] || String(key || '').trim();
    }

    function splitFallbackValue(columnKey, row = {}, payload = {}) {
        const fallbacks = currentSplitPolicy().splitValueFallbacks || {};
        const keys = Array.isArray(fallbacks[columnKey]) ? fallbacks[columnKey] : [];
        for (const key of keys) {
            const value = row[key] ?? payload[key] ?? '';
            if (valueText(value) !== '') {
                return value;
            }
        }
        return '';
    }

    function splitRowsForEvidence(row = {}) {
        const evidenceId = String(row?.evidence_id || row?.id || '').trim();
        if (Array.isArray(row?.processing_children)) {
            const parent = { ...row };
            delete parent.processing_children;
            const children = row.processing_children
                .filter((item) => item && typeof item === 'object')
                .sort((a, b) => String(a.processing_display_path || a.sort_no || '').localeCompare(String(b.processing_display_path || b.sort_no || ''), 'ko-KR', { numeric: true }));
            return { evidenceId, parent, children };
        }
        const rows = state.lastRows.filter((item) => String(item.evidence_id || item.id || '').trim() === evidenceId);
        const parent = rows.find((item) => item.processing_has_children)
            || rows.find((item) => !item.processing_is_child)
            || row;
        const children = rows
            .filter((item) => item.processing_is_child)
            .sort((a, b) => String(a.processing_display_path || a.sort_no || '').localeCompare(String(b.processing_display_path || b.sort_no || ''), 'ko-KR', { numeric: true }));
        return { evidenceId, parent, children };
    }

    function splitColumnKey(column = {}) {
        const key = editFieldKey(column);
        return splitAliasKey(key);
    }

    function splitModalColumns(row = null) {
        const sourceRow = row && typeof row === 'object' ? row : {};
        const columns = editableColumnsForRow(sourceRow)
            .map((column) => {
                const key = splitColumnKey(column);
                const title = String(column.excel_column_name || column.label || column.system_field_name || key).trim();
                const value = editFieldValue({}, column);
                return {
                    key,
                    title,
                    type: editInputType(column, value),
                    amount: isAmountColumn(column),
                    tone: infoColumnTone(column),
                    column,
                };
            })
            .filter((column) => column.key)
            .sort((a, b) => compareFormatColumns(a.column || {}, b.column || {}));
        if (row && typeof row === 'object') {
            splitColumnsCache = columns;
        }
        return columns.length > 0 ? columns : splitColumnsCache;
    }

    function splitAmountKeys() {
        return splitAmountColumns().map((column) => column.key).filter(Boolean);
    }

    function splitPayloadWithAmounts(payload = {}, amountKeys = [], value = 0) {
        const next = { ...payload };
        amountKeys.forEach((key) => {
            next[key] = value;
        });
        return next;
    }

    function splitAmountSummary(modal) {
        const parentPayload = modal._splitParentPayload && typeof modal._splitParentPayload === 'object'
            ? modal._splitParentPayload
            : {};
        return splitAmountColumns().map((column) => {
            const parentValue = amount(parentPayload[column.key]);
            let childValue = 0;
            modal.querySelectorAll('tbody tr[data-split-role="child"]').forEach((tr) => {
                const input = Array.from(tr.querySelectorAll('.evidence-split-field'))
                    .find((field) => field.dataset.key === column.key);
                childValue += amount(input?.value || 0);
            });
            return {
                title: column.title,
                parentValue,
                childValue,
                valid: Math.abs(parentValue - childValue) <= 0.01,
            };
        });
    }

    function splitRequiredColumns() {
        return splitModalColumns().filter((column) => requirementMode(column.column || {}) === 1);
    }

    function splitSelectOption(value = '', text = '') {
        const optionValue = valueText(value);
        const optionText = valueText(text) || optionValue;
        return optionValue !== ''
            ? `<option value="${escapeHtml(optionValue)}" selected>${escapeHtml(optionText || optionValue)}</option>`
            : '';
    }

    function splitCodeValue(column, value) {
        const raw = valueText(value);
        if (raw === '') return '';
        const group = bankCodePickerForColumn(column.column || {})?.codeGroup || DISPLAY_CODE_FIELDS[column.key] || '';
        if (group === '') return codeValueForField(column.key, raw);
        const normalized = normalizeCodeKey(raw);
        const found = (state.codeOptions[group] || []).find((row) => (
            normalizeCodeKey(row.code) === normalized
            || String(row.code_name ?? '').trim() === raw
        ));
        return found?.code || CODE_NAME_ALIASES[column.key]?.[raw] || raw;
    }

    function splitCodeDisplayName(column, value) {
        const code = normalizeCodeKey(value);
        if (code === '') return '';
        const group = bankCodePickerForColumn(column.column || {})?.codeGroup || DISPLAY_CODE_FIELDS[column.key] || '';
        if (group === '') return codeDisplayName(column.key, value);
        const found = (state.codeOptions[group] || []).find((row) => normalizeCodeKey(row.code) === code);
        return found?.code_name || valueText(value);
    }

    function splitLooksLikeUuid(candidate) {
        return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(candidate || '').trim());
    }

    function splitValueForColumn(column, row = {}, payload = {}) {
        let rawValue = row[column.key] ?? payload[column.key] ?? '';
        if (valueText(rawValue) === '') {
            rawValue = splitFallbackValue(column.key, row, payload);
        }
        if (column.type === 'date') {
            const explicitDateTime = explicitDateTimeValueForColumn(column, row, payload);
            if (explicitDateTime !== '') {
                return normalizeDateInputValue(explicitDateTime, true);
            }
            const raw = valueText(rawValue);
            const hasInlineTime = /\d{1,2}:\d{2}/.test(raw);
            const timeValue = splitTimeForColumn(column, row, payload);
            const keepTime = isDateTimeColumn(column.column || {}) || hasInlineTime || timeValue !== '';
            const source = keepTime && !hasInlineTime && timeValue !== '' ? `${raw} ${timeValue}` : raw;
            return normalizeDateInputValue(source, keepTime);
        }
        if (column.type === 'time') {
            return normalizeTimeInputValue(rawValue);
        }
        if (column.type === 'code') {
            return splitCodeValue(column, rawValue);
        }
        if (column.amount && rawValue !== '') {
            return formatNumber(rawValue);
        }
        return valueText(rawValue);
    }

    function splitCellHtml(column, child = {}, payload = {}) {
        const splitRenderRow = {
            ...payload,
            ...child,
        };
        const splitToneClass = column.tone ? ` evidence-split-cell-${escapeHtml(column.tone)}` : '';
        const splitRenderValue = editFieldValue(splitRenderRow, column.column || {});
        const previousEditingRow = state.editingRow;
        state.editingRow = splitRenderRow;
        let splitControl = renderEditInput(column.column || {}, splitRenderValue, { hideSystemKey: true });
        state.editingRow = previousEditingRow;
        splitControl = splitControl
            .replaceAll('evidence-edit-input', 'evidence-edit-input evidence-split-field')
            .replaceAll('evidence-edit-number', 'evidence-edit-number evidence-split-amount')
            .replaceAll('evidence-edit-ref', 'evidence-edit-ref evidence-split-ref')
            .replaceAll('evidence-edit-code', 'evidence-edit-code evidence-split-code')
            .replaceAll('evidence-edit-date"', 'evidence-edit-date evidence-split-date"')
            .replaceAll('evidence-edit-date-btn"', 'evidence-edit-date-btn evidence-split-date-btn"');
        return `
            <td class="${splitToneClass}">
                ${splitControl}
            </td>
        `;
    }

    function splitDisplayCellHtml(column, row = {}, payload = {}) {
        const splitDisplayRow = {
            ...payload,
            ...row,
        };
        const refConfig = businessRefPickerForColumn(column.column || {});
        const looksLikeUuid = (candidate) => /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(candidate || '').trim());
        const displayFieldByPicker = {
            client: 'client_name',
            project: 'project_name',
            employee: 'employee_name',
            team: 'team_name',
            bankAccount: 'bank_account_name',
            card: 'card_name',
            supplierCompany: 'supplier_company_name',
            customerCompany: 'customer_company_name',
        };
        const refDisplayField = refConfig ? (displayFieldByPicker[refConfig.picker] || refConfig.nameKey || column.key) : '';
        const refSelectedId = refConfig ? firstPayloadText(splitDisplayRow, [refConfig.idKey]) : '';
        const refPayloadText = refConfig ? firstPayloadText(splitDisplayRow, [refConfig.nameKey, ...(refConfig.keys || []), column.key]) : '';
        const refRowText = refDisplayField
            ? (valueText(splitDisplayRow?.[refDisplayField]) || (refDisplayField !== column.key ? valueText(splitDisplayRow?.[column.key]) : ''))
            : '';
        const splitDisplayValueRaw = editFieldValue(splitDisplayRow, column.column || {});
        const splitDisplayValue = refConfig
            ? (refRowText || (refPayloadText !== refSelectedId ? refPayloadText : '') || splitDisplayValueRaw)
            : column.type === 'code'
                ? splitCodeDisplayName(column, splitDisplayValueRaw)
                : splitDisplayValueRaw;
        const splitDisplayToneClass = column.tone ? ` evidence-split-cell-${escapeHtml(column.tone)}` : '';
        return `
            <td class="${splitDisplayToneClass}" data-parent-key="${escapeHtml(column.key)}">
                <span class="evidence-split-parent-value ${column.amount ? 'text-end' : ''}">${escapeHtml(splitDisplayValue)}</span>
            </td>
        `;
    }

    function splitRowHtml(child = {}) {
        const columns = splitModalColumns();
        const payload = mapped(child);
        const sortNo = child.sort_no || '';
        return `
            <tr data-id="${escapeHtml(child.processing_item_id || child.id || '')}" data-split-role="child">
                <td class="evidence-split-drag text-center"><span class="bi bi-list" title="\uD589 \uC774\uB3D9"></span></td>
                <td class="text-center"><span class="evidence-split-sort">${escapeHtml(sortNo)}</span></td>
                ${columns.map((column) => splitCellHtml(column, child, payload)).join('')}
                <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm evidence-split-remove">\uC0AD\uC81C</button></td>
            </tr>
        `;
    }

    function bindSplitModalHorizontalWheel(modal) {
        const scroll = modal.querySelector('.evidence-split-scroll');
        if (!scroll || scroll.dataset.horizontalWheelBound === 'true') return;
        scroll.addEventListener('wheel', (event) => {
            if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;
            if (scroll.scrollWidth <= scroll.clientWidth) return;
            event.preventDefault();
            scroll.scrollLeft += event.deltaY;
        }, { passive: false });
        scroll.dataset.horizontalWheelBound = 'true';
    }

    function bindSplitTimeInput(input) {
        if (!input || input.dataset.timeInputBound === 'true') return;
        const normalize = () => {
            input.value = normalizeTimeInputValue(input.value);
        };
        input.addEventListener('change', normalize);
        input.addEventListener('blur', normalize);
        input.dataset.timeInputBound = 'true';
    }

    function bindSplitTimePicker(button) {
        if (!button || button.dataset.timePickerBound === 'true') return;

        button.addEventListener('click', (event) => {
            event.preventDefault();
            const input = button.closest('.evidence-edit-date-wrap')?.querySelector('.evidence-split-time');
            if (!input || input.disabled) return;

            const layer = document.createElement('div');
            layer.className = 'picker is-hidden evidence-edit-picker-layer';
            document.body.appendChild(layer);
            state.editPickerLayers.push(layer);

            const picker = AdminPicker.create({ type: 'time-list', container: layer, options: { step: 10, rows: 8 } });
            const currentTime = normalizeTimeInputValue(input.value);
            if (currentTime) {
                const [hour, minute] = currentTime.split(':').map((item) => Number(item));
                picker.setTime?.({ hour, minute, meridiem: hour >= 12 ? 'PM' : 'AM' });
            }
            const closePicker = picker.close?.bind(picker);
            picker.close = () => {
                closePicker?.();
                window.setTimeout(() => layer.remove(), 0);
            };
            picker.subscribe((pickerState) => {
                if (typeof pickerState?.hour !== 'number' || typeof pickerState?.minute !== 'number') return;
                input.value = `${pad2(pickerState.hour)}:${pad2(pickerState.minute)}`;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                picker.close?.();
            });
            picker.open?.({ anchor: input });
        });

        button.dataset.timePickerBound = 'true';
    }

    function bindSplitModalInputs(modal) {
        modal.querySelectorAll('.evidence-split-field[data-value-kind="number"]').forEach((input) => bindCommonNumberInput(input));
        modal.querySelectorAll('.evidence-edit-date-wrap').forEach((wrap) => {
            bindDateEditInput(
                wrap.querySelector('.evidence-split-field[data-value-kind="date"]'),
                wrap.querySelector('.evidence-edit-date-btn')
            );
        });
        modal.querySelectorAll('.evidence-split-time').forEach((input) => bindSplitTimeInput(input));
        modal.querySelectorAll('.evidence-split-time-btn').forEach((button) => bindSplitTimePicker(button));
        modal.querySelectorAll('.evidence-split-field[data-value-kind="ref"]').forEach((select) => {
            initEvidenceRefSelect(select, {
                modal,
                api: API,
                onSelect: () => syncSplitParentDisplayFromInputs(modal),
                onClear: () => syncSplitParentDisplayFromInputs(modal),
            });
            if (select.dataset.parentSyncBound === 'true') return;
            const sync = () => window.setTimeout(() => syncSplitParentDisplayFromInputs(modal), 0);
            select.addEventListener('change', sync);
            if (window.jQuery) {
                window.jQuery(select)
                    .off('change.evidenceSplitParentSync select2:select.evidenceSplitParentSync select2:clear.evidenceSplitParentSync')
                    .on('change.evidenceSplitParentSync select2:select.evidenceSplitParentSync select2:clear.evidenceSplitParentSync', sync);
            }
            select.dataset.parentSyncBound = 'true';
        });
        if (modal.querySelector('select.evidence-edit-code')) {
            void initCodeSelectControls(modal);
        }
        window.setTimeout(() => syncSplitParentDisplayFromInputs(modal), 0);
        window.setTimeout(() => syncSplitParentDisplayFromInputs(modal), 300);
        window.setTimeout(() => syncSplitParentDisplayFromInputs(modal), 1000);
    }

    function syncSplitParentDisplayFromInputs(modal) {
        const parentRow = modal.querySelector('tbody tr[data-split-role="parent"]');
        const childRows = Array.from(modal.querySelectorAll('tbody tr[data-split-role="child"]'));
        if (!parentRow || childRows.length === 0) return;

        const parentCells = Array.from(parentRow.querySelectorAll('td[data-parent-key]'));
        const displayByKey = new Map();

        childRows.forEach((row) => {
            row.querySelectorAll('.evidence-split-field[data-value-kind="ref"]').forEach((input) => {
                const key = String(input.dataset.key || '').trim();
                if (key === '' || displayByKey.has(key)) return;
                const text = selectTextForSave(input, { includeCurrentText: true });
                if (text !== '') {
                    displayByKey.set(key, text);
                }
            });
        });

        displayByKey.forEach((text, key) => {
            const cell = parentCells.find((td) => String(td.dataset.parentKey || '').trim() === key)
                ?.querySelector('.evidence-split-parent-value');
            if (cell) {
                cell.textContent = text;
            }
        });
    }

    function bindSplitModalReorder(modal) {
        const tbody = modal.querySelector('.evidence-split-table tbody');
        if (!tbody || !window.jQuery || typeof window.jQuery(tbody).sortable !== 'function') return;
        const $tbody = window.jQuery(tbody);
        if ($tbody.data('ui-sortable')) {
            $tbody.sortable('destroy');
        }
        $tbody.sortable({
            handle: '.evidence-split-drag',
            items: '> tr[data-split-role="child"]',
            axis: 'y',
            containment: 'parent',
            tolerance: 'pointer',
            stop: () => refreshSplitModalRows(modal),
        });
    }

    function collectSplitChildPayload(tr) {
        const payload = {};
        tr.querySelectorAll('.evidence-split-field').forEach((input) => {
            const key = input.dataset.key || '';
            if (!key) return;
            if (input.dataset.valueKind === 'ref') {
                const idKey = input.dataset.refIdKey || '';
                const nameKey = input.dataset.refNameKey || key;
                const selectedId = selectValueForSave(input);
                const selectedText = selectTextForSave(input);
                payload[key] = selectedText;
                if (nameKey) {
                    payload[nameKey] = selectedText;
                }
                if (idKey) {
                    payload[idKey] = selectedId && selectedId !== selectedText ? selectedId : '';
                }
                return;
            }
            if (input.dataset.valueKind === 'date') {
                const keepTime = input.dataset.keepTime === '1';
                const normalized = normalizeDateInputValue(input.value, keepTime);
                payload[key] = normalized;
                if (keepTime && /transaction_(date|time|datetime|at)$/i.test(key)) {
                    payload.raw_transaction_datetime = normalized;
                }
                return;
            }
            payload[key] = input.dataset.valueKind === 'number'
                ? amount(input.value || 0)
                : (input.matches('select') ? selectValueForSave(input) : input.value);
        });
        return payload;
    }

    function splitChildFromRow(tr, index = 0) {
        const payload = collectSplitChildPayload(tr);
        const child = {
            id: tr.dataset.id || '',
            sort_no: Number(tr.querySelector('.evidence-split-sort')?.textContent || index + 1),
            description: payload.raw_description || payload.raw_memo || payload.description || payload.memo || '',
            ...payload,
        };
        ['quantity', 'unit_price', 'supply_amount', 'vat_amount', 'total_amount', 'raw_deposit_amount', 'raw_withdraw_amount', 'withdrawal_amount'].forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(payload, key)) {
                child[key] = payload[key];
            }
        });
        return child;
    }

    function splitDisplayCellHtml(column, row = {}, payload = {}) {
        const splitDisplayRow = {
            ...payload,
            ...row,
        };
        const refConfig = businessRefPickerForColumn(column.column || {});
        const looksLikeUuid = (candidate) => /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(candidate || '').trim());
        const displayFieldByPicker = {
            client: 'client_name',
            project: 'project_name',
            employee: 'employee_name',
            team: 'team_name',
            bankAccount: 'bank_account_name',
            card: 'card_name',
            supplierCompany: 'supplier_company_name',
            customerCompany: 'customer_company_name',
        };
        const selectedId = firstPayloadText(splitDisplayRow, [refConfig?.idKey]) || valueText(splitDisplayRow?.[refConfig?.idKey]);
        const displayField = refConfig ? (displayFieldByPicker[refConfig.picker] || refConfig.nameKey || column.key) : '';
        const payloadText = refConfig ? firstPayloadText(splitDisplayRow, [refConfig.nameKey, ...(refConfig.keys || []), column.key]) : '';
        const rowDisplayText = displayField
            ? (
                valueText(splitDisplayRow?.[displayField])
                || (
                    displayField !== column.key
                        ? valueText(splitDisplayRow?.[column.key])
                        : ''
                )
            )
            : '';
        const rawValue = editFieldValue(splitDisplayRow, column.column || {});
        const fallbackValueText = valueText(rawValue);
        const configuredText = refConfig
            ? [
                typeof refConfig.saveText === 'function' ? refConfig.saveText(splitDisplayRow) : '',
                typeof refConfig.label === 'function' ? refConfig.label(splitDisplayRow) : '',
                typeof refConfig.listText === 'function' ? refConfig.listText(splitDisplayRow) : '',
            ].map((item) => valueText(item)).find((text) => text !== '' && text !== selectedId && !looksLikeUuid(text)) || ''
            : '';
        const normalizedAmountText = column.amount ? formatNumber(rawValue) : fallbackValueText;
        const splitDisplayValue = refConfig
            ? (
                rowDisplayText
                || configuredText
                || (
                    payloadText !== ''
                    && payloadText !== selectedId
                    && !looksLikeUuid(payloadText)
                        ? payloadText
                        : ''
                )
                || (
                    normalizedAmountText !== ''
                    && normalizedAmountText !== selectedId
                    && !looksLikeUuid(normalizedAmountText)
                        ? normalizedAmountText
                        : ''
                )
                || selectedId
                || normalizedAmountText
            )
            : column.type === 'code'
                ? splitCodeDisplayName(column, rawValue)
                : (column.amount ? formatNumber(rawValue) : rawValue);
        const splitDisplayToneClass = column.tone ? ` evidence-split-cell-${escapeHtml(column.tone)}` : '';
        return `
            <td class="${splitDisplayToneClass}" data-parent-key="${escapeHtml(column.key)}">
                <span class="evidence-split-parent-value ${column.amount ? 'text-end' : ''}">${escapeHtml(splitDisplayValue)}</span>
            </td>
        `;
    }

    function ensureSplitModal() {
        let modal = document.getElementById('evidenceProcessingSplitModal');
        if (modal) return modal;
        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="evidenceProcessingSplitModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content evidence-split-modal">
                        <div class="modal-header">
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h5 class="modal-title mb-0">\uBD84\uD560 \uC99D\uBE59 \uC0C1\uC138</h5>
                                    <span class="small text-muted">ledger_processing_items</span>
                                </div>
                                <div class="small text-muted evidence-split-subtitle"></div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="\uB2EB\uAE30"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info py-2 small">\uC6D0\uBCF8 \uAE08\uC561\uACFC \uD558\uC704 \uC99D\uBE59 \uD569\uACC4\uAC00 \uC77C\uCE58\uD558\uB3C4\uB85D \uBD84\uD560 \uD56D\uBAA9\uC744 \uC785\uB825\uD574 \uC8FC\uC138\uC694.</div>
                            <div class="table-responsive evidence-split-scroll">
                                <table class="table table-sm align-middle evidence-split-table mb-2">
                                    <thead>
                                        <tr>
                                            <th style="width:72px;">\uC21C\uBC88</th>
                                            <th>\uC6D0\uBCF8</th>
                                            <th>\uBD84\uD560 \uC99D\uBE59</th>
                                            <th style="width:70px;">\uC0AD\uC81C</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm evidence-split-add">+ \uD589 \uCD94\uAC00</button>
                        </div>
                        <div class="modal-footer">
                            <span class="me-auto small evidence-split-total"></span>
                            <button type="button" class="btn btn-primary btn-sm evidence-split-save">\uC800\uC7A5</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">\uB2EB\uAE30</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        modal = document.getElementById('evidenceProcessingSplitModal');
        const splitInfo = modal.querySelector('.alert-info');
        if (splitInfo) {
            splitInfo.textContent = '\uC6D0\uBCF8 \uAE08\uC561\uACFC \uD558\uC704 \uC99D\uBE59 \uD569\uACC4\uAC00 \uC77C\uCE58\uD558\uB3C4\uB85D \uBD84\uD560 \uD56D\uBAA9\uC744 \uC785\uB825\uD574 \uC8FC\uC138\uC694.';
        }
        const headerCells = modal.querySelectorAll('thead th');
        if (headerCells.length >= 4) {
            headerCells[0].textContent = '\uC21C\uBC88';
            headerCells[1].textContent = '\uC6D0\uBCF8';
            headerCells[2].textContent = '\uBD84\uD560 \uC99D\uBE59';
            headerCells[3].textContent = '\uC0AD\uC81C';
        }
        modal.querySelector('.evidence-split-add')?.addEventListener('click', () => {
            const tbody = modal.querySelector('tbody');
            const parentPayload = modal._splitParentPayload && typeof modal._splitParentPayload === 'object'
                ? { ...modal._splitParentPayload }
                : {};
            const amountKeys = splitAmountKeys();
            const childCount = tbody?.querySelectorAll('tr[data-split-role="child"]').length || 0;
            tbody?.insertAdjacentHTML('beforeend', splitRowHtml({
                sort_no: childCount + 1,
                ...splitPayloadWithAmounts(parentPayload, amountKeys, 0),
            }));
            bindSplitModalInputs(modal);
            refreshSplitModalRows(modal);
        });
        modal.querySelector('tbody')?.addEventListener('click', (event) => {
            const removeButton = event.target.closest('.evidence-split-remove');
            if (!removeButton) return;
            const row = removeButton.closest('tr[data-split-role="child"]');
            row?.remove();
            refreshSplitModalRows(modal);
        });
        modal.querySelector('.evidence-split-save')?.addEventListener('click', () => saveSplitModal(modal));
        bindSplitModalHorizontalWheel(modal);
        return modal;
    }

    function isSplitExcludedColumn(column = {}) {
        const key = String(column.key || column.column?.system_field_name || '').toLowerCase();
        const title = String(column.title || column.column?.excel_column_name || '').trim();
        const text = `${key} ${title}`;
        if (/raw_balance_amount|raw_check_bill_amount|balance|\uC794\uC561|\uC218\uD45C\uBC1B\uC74C\uAE08\uC561/.test(text)) {
            return true;
        }
        const allowedAmountKeys = Array.isArray(currentSplitPolicy().splitAllowedAmountKeys)
            ? currentSplitPolicy().splitAllowedAmountKeys
            : [];
        if (allowedAmountKeys.length > 0 && column.amount) {
            return !allowedAmountKeys.includes(String(column.key || column.column?.system_field_name || '').trim());
        }
        return false;
    }

    function splitAmountColumns() {
        return splitModalColumns().filter((column) => {
            const field = String(column.key || column.column?.system_field_name || '').toLowerCase();
            const title = String(column.title || column.column?.excel_column_name || '').trim();
            const text = `${field} ${title}`;
            if (/unit_price|foreign_unit_price|exchange_rate|rate|quantity|qty|balance_amount|\uC218\uB7C9|\uC794\uC561/.test(text)) {
                return false;
            }
            return /amount|total|vat|tax|fee|charge|duty|deposit|withdraw|withdrawal|supply|settlement|gross|withholding|\uACF5\uAE09\uAC00\uC561|\uD569\uACC4|\uBD80\uAC00\uC138|\uC785\uAE08|\uCD9C\uAE08/.test(text);
        });
    }

    function renderSplitModalHeader(modal) {
        const headerRow = modal.querySelector('.evidence-split-table thead tr');
        if (!headerRow) return;
        headerRow.innerHTML = `
            <th style="width:36px;"></th>
            <th style="width:72px;">\uC21C\uBC88</th>
            ${splitModalColumns().map((column) => `<th><span class="d-inline-flex align-items-center gap-1">${escapeHtml(column.title)}${requirementStar(column.column || {})}${renderFieldBadge(column.column || {})}</span></th>`).join('')}
            <th style="width:70px;">\uC0AD\uC81C</th>
        `;
    }

    function splitTimeForColumn(column, row = {}, payload = {}) {
        const key = String(column.key || '');
        const keys = Array.from(new Set([
            key.replace(/datetime/i, 'time'),
            key.replace(/date/i, 'time'),
            key.replace(/\uAC70\uB798\uC77C\uC2DC/g, '\uAC70\uB798\uC2DC\uAC04'),
            key.replace(/\uC2B9\uC778\uC77C\uC2DC/g, '\uC2B9\uC778\uC2DC\uAC04'),
            'transaction_time',
            'approval_time',
            'purchase_time',
            'time',
            '\uAC70\uB798\uC2DC\uAC04',
            '\uC2B9\uC778\uC2DC\uAC04',
            '\uAD6C\uB9E4\uC2DC\uAC04',
        ].filter(Boolean)));
        for (const candidate of keys) {
            const value = row[candidate] ?? payload[candidate];
            const normalized = normalizeTimeInputValue(value);
            if (normalized !== '') return normalized;
        }
        return '';
    }

    function splitMissingRequiredForRow(tr, index = 0) {
        const payload = collectSplitChildPayload(tr);
        const missing = [];
        splitRequiredColumns().forEach((column) => {
            if (valueText(payload[column.key]) === '') {
                missing.push(`${index + 1}\uD589 ${column.title}`);
            }
        });
        return missing;
    }

    async function saveSplitModal(modal) {
        const evidenceId = String(modal.dataset.evidenceId || '').trim();
        const processingItemId = String(modal.dataset.parentProcessingItemId || '').trim();
        const missing = [];
        const children = Array.from(modal.querySelectorAll('tbody tr[data-split-role="child"]')).map((tr, index) => {
            missing.push(...splitMissingRequiredForRow(tr, index));
            return splitChildFromRow(tr, index);
        });
        const ruleMessages = children
            .map((child, index) => {
                const message = businessProjectRuleMessage(child);
                return message ? `${index + 1}\uD589 ${message}` : '';
            })
            .filter(Boolean);
        if (ruleMessages.length > 0) {
            notify('warning', ruleMessages.slice(0, 5).join(' '));
            return;
        }
        if (missing.length > 0) {
            notify('warning', `\uD544\uC218 \uD56D\uBAA9\uC774 \uBE44\uC5B4 \uC788\uC2B5\uB2C8\uB2E4. \uB2E4\uC74C \uAC12\uC744 \uD655\uC778\uD574 \uC8FC\uC138\uC694: ${missing.slice(0, 5).join(', ')}${missing.length > 5 ? ` \uC678 ${missing.length - 5}\uAC74` : ''}`);
            return;
        }
        const response = await fetch(API.splitChild, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: evidenceId,
                evidence_id: evidenceId,
                processing_item_id: processingItemId,
                children,
            }),
        });
        const text = await response.text();
        let json = {};
        try {
            json = text ? JSON.parse(text) : {};
        } catch (error) {
            const cleaned = text
                .replace(/<style[\s\S]*?<\/style>/gi, ' ')
                .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                .replace(/<[^>]+>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
            throw new Error(/Fatal error|Call to undefined method|Parse error|Exception/i.test(cleaned)
                ? '\uC11C\uBC84 \uCC98\uB9AC \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4. \uAD00\uB9AC\uC790\uC5D0\uAC8C \uBB38\uC758\uD558\uC138\uC694.'
                : (cleaned || '\uC5C5\uB85C\uB4DC \uCC98\uB9AC \uC911 \uC11C\uBC84 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.').slice(0, 240));
        }
        if (!response.ok || json.success === false) {
            notify('error', json.message || '\uBD84\uD560 \uC99D\uBE59 \uC800\uC7A5 \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
            return;
        }
        notify('success', json.message || '\uBD84\uD560 \uC99D\uBE59 \uC800\uC7A5\uC774 \uC644\uB8CC\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
        bootstrap.Modal.getInstance(modal)?.hide();
        state.table?.ajax.reload(() => {
            updateSummary(state.lastRows);
        }, false);
    }

    function splitParentRowHtml(parent = {}, parentPayload = {}) {
        const columns = splitModalColumns();
        const displayNo = parent.processing_display_path || parent.sort_no || '-';
        return `
            <tr class="evidence-split-parent-row" data-split-role="parent">
                <td class="text-center"><span class="evidence-split-parent-label">\uC6D0\uBCF8</span></td>
                <td class="text-center"><span>${escapeHtml(displayNo)}</span></td>
                ${columns.map((column) => splitDisplayCellHtml(column, parent, parentPayload)).join('')}
                <td class="text-center">-</td>
            </tr>
        `;
    }

    function refreshSplitModalRows(modal) {
        const rows = Array.from(modal.querySelectorAll('tbody tr[data-split-role="child"]'));
        rows.forEach((tr, index) => {
            const sortNode = tr.querySelector('.evidence-split-sort');
            if (sortNode) sortNode.textContent = String(index + 1);
        });
        const total = modal.querySelector('.evidence-split-total');
        if (total) {
            const summaries = splitAmountSummary(modal);
            total.textContent = summaries.length > 0
                ? summaries.map((item) => `${item.title} \uC6D0\uBCF8 ${formatNumber(item.parentValue)} / \uD558\uC704 \uD569\uACC4 ${formatNumber(item.childValue)}`).join(' | ')
                : '\uBD84\uD560 \uD569\uACC4\uB97C \uACC4\uC0B0\uD560 \uC218 \uC788\uB294 \uAE08\uC561 \uD56D\uBAA9\uC774 \uC5C6\uC2B5\uB2C8\uB2E4.';
            total.classList.toggle('text-danger', summaries.some((item) => !item.valid));
        }
    }

    function openProcessingSplitModal(row = {}) {
        const modal = ensureSplitModal();
        const { evidenceId, parent, children } = splitRowsForEvidence(row);
        const parentPayload = mapped(parent);
        modal._splitParentPayload = { ...parentPayload };
        modal.dataset.evidenceId = evidenceId;
        modal.dataset.parentProcessingItemId = parent.processing_item_id || row.processing_parent_item_id || row.processing_item_id || '';
        const subtitle = modal.querySelector('.evidence-split-subtitle');
        if (subtitle) {
            subtitle.textContent = `\uC6D0\uBCF8 ${parent.processing_display_path || parent.sort_no || '-'}`;
        }
        const amountKeys = splitAmountKeys();
        const initialChildren = children.length > 0 ? children : [
            { sort_no: 1, ...parentPayload },
            { sort_no: 2, ...splitPayloadWithAmounts(parentPayload, amountKeys, 0) },
        ];
        splitColumnsCache = splitModalColumns(parent);
        renderSplitModalHeader(modal);
        bindSplitModalReorder(modal);
        const tbody = modal.querySelector('tbody');
        if (tbody) {
            const childRows = initialChildren.map((child, index) => splitRowHtml({
                ...child,
                sort_no: child.sort_no || index + 1,
            })).join('');
            tbody.innerHTML = splitParentRowHtml(parent, parentPayload) + childRows;
        }
        bindSplitModalInputs(modal);
        refreshSplitModalRows(modal);
        bootstrap.Modal.getOrCreateInstance(modal, { focus: false }).show();
    }
    return {
        splitRowsForEvidence,
        ensureSplitModal,
        splitModalColumns,
        splitColumnKey,
        isSplitExcludedColumn,
        splitAmountColumns,
        splitAmountKeys,
        splitPayloadWithAmounts,
        splitAmountSummary,
        splitRequiredColumns,
        renderSplitModalHeader,
        splitSelectOption,
        splitCodeValue,
        splitCodeDisplayName,
        splitTimeForColumn,
        splitValueForColumn,
        splitCellHtml,
        splitDisplayCellHtml,
        splitParentRowHtml,
        splitRowHtml,
        refreshSplitModalRows,
        openProcessingSplitModal,
        bindSplitModalHorizontalWheel,
        bindSplitTimeInput,
        bindSplitTimePicker,
        bindSplitModalInputs,
        bindSplitModalReorder,
        collectSplitChildPayload,
        splitChildFromRow,
        splitMissingRequiredForRow,
        saveSplitModal,
    };
}
