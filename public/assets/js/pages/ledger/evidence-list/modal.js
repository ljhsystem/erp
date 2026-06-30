import { openClientQuickCreate } from '/public/assets/js/pages/dashboard/settings/base/client.js';
import { WORK_TEAM_API } from '/public/assets/js/pages/dashboard/settings/base/work-team/api.js';
import { createWorkTeamModalModule } from '/public/assets/js/pages/dashboard/settings/base/work-team/modal.js';

export function createEvidenceModalModule({
    state,
    API,
    notify,
    updateSummary,
    refreshEvidenceTypeCounts,
    ensureActiveFormat,
    selectedTypeLabel,
    evidenceTypeDisplayName,
    normalizedStatus,
    escapeHtml,
    valueText,
    mapped,
    firstPayloadText,
    renderFieldBadge,
    requirementStar,
    compareFieldDisplayOrder,
    isDeprecatedFormatColumn,
    infoColumnTone,
    editFieldKey,
    editInputType,
    editFieldValue,
    businessRefPickerForColumn,
    bankCodePickerForColumn,
    codeValueForField,
    codeDisplayName,
    isDateTimeColumn,
    normalizeDateInputValue,
    formatNumber,
    formatBizNumber,
    formatPhone,
    parseCommonNumber,
    bindCommonNumberInput,
    formatPickerDateTime,
    formatPickerDate,
    applyDateToPicker,
    AdminPicker,
    EVIDENCE_REF_PICKERS,
    initEvidenceRefSelect,
    initCodeSelectControls,
    selectValueForSave,
    selectTextForSave,
    validateBusinessProjectRule,
    evidenceStatusTableSettingsStorageKey,
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
    normalizeEvidenceType,
    evidenceTypePolicy,
    defaultEvidenceTypeCode,
}) {
    const COMMON_BUSINESS_MODAL_FIELDS = Object.freeze([
        { key: 'business_unit', label: '\uC0AC\uC5C5\uAD6C\uBD84', group: 'business', data_type: 'varchar' },
        { key: 'transaction_direction', label: '\uAC70\uB798\uAD6C\uBD84', group: 'business', data_type: 'varchar' },
        { key: 'transaction_type', label: '\uAC70\uB798\uC720\uD615', group: 'business', data_type: 'varchar' },
        { key: 'client_id', label: '\uAC70\uB798\uCC98', group: 'business', data_type: 'varchar' },
        { key: 'project_id', label: '\uD504\uB85C\uC81D\uD2B8', group: 'business', data_type: 'varchar' },
        { key: 'bank_account_id', label: '\uACC4\uC88C', group: 'business', data_type: 'varchar' },
        { key: 'card_id', label: '\uCE74\uB4DC', group: 'business', data_type: 'varchar' },
        { key: 'team_id', label: '\uD300', group: 'business', data_type: 'varchar' },
        { key: 'employee_id', label: '\uC9C1\uC6D0', group: 'business', data_type: 'varchar' },
    ]);
    const BANK_LIKE_MODAL_FIELDS = Object.freeze([
        { key: 'raw_transaction_datetime', label: '\uAC70\uB798\uC77C\uC2DC', group: 'raw', data_type: 'datetime', is_required: 1 },
        ...COMMON_BUSINESS_MODAL_FIELDS,
        { key: 'raw_deposit_amount', label: '\uC785\uAE08', group: 'raw', data_type: 'decimal' },
        { key: 'raw_withdraw_amount', label: '\uCD9C\uAE08', group: 'raw', data_type: 'decimal' },
        { key: 'raw_balance_amount', label: '\uAC70\uB798\uD6C4\uC794\uC561', group: 'raw', data_type: 'decimal' },
        { key: 'raw_description', label: '\uAC70\uB798\uB0B4\uC6A9', group: 'raw', data_type: 'varchar' },
        { key: 'raw_counterparty_account_number', label: '\uC0C1\uB300\uACC4\uC88C\uBC88\uD638', group: 'raw', data_type: 'varchar' },
        { key: 'raw_counterparty_bank_name', label: '\uC0C1\uB300\uC740\uD589', group: 'raw', data_type: 'varchar' },
        { key: 'raw_memo', label: '\uBA54\uBAA8', group: 'raw', data_type: 'varchar' },
        { key: 'raw_transaction_type', label: '\uC6D0\uBCF8\uAC70\uB798\uAD6C\uBD84', group: 'raw', data_type: 'varchar' },
        { key: 'raw_check_bill_amount', label: '\uC218\uD45C\uC5B4\uC74C\uAE08\uC561', group: 'raw', data_type: 'decimal' },
        { key: 'raw_cms_code', label: 'CMS\uCF54\uB4DC', group: 'raw', data_type: 'varchar' },
        { key: 'raw_counterparty_name', label: '\uC0C1\uB300\uACC4\uC88C\uC608\uAE08\uC8FC\uBA85', group: 'raw', data_type: 'varchar' },
    ]);
    const BANK_LIKE_MODAL_GROUPS = Object.freeze([
        {
            key: 'business',
            title: '\uC5C5\uBB34 \uBD84\uB958\uC815\uBCF4',
            description: '\uAC70\uB798 \uBC0F \uC804\uD45C \uC790\uB3D9 \uBC1C\uD589\uC5D0 \uC0AC\uC6A9\uD558\uB294 \uD575\uC2EC \uBD84\uB958 \uC815\uBCF4\uC785\uB2C8\uB2E4.',
            collapsible: false,
        },
        {
            key: 'raw',
            title: '\uC6D0\uBCF8 \uC815\uBCF4',
            description: '\uC6D0\uBCF8 \uD30C\uC77C\uC5D0\uC11C \uAC00\uC838\uC628 \uB370\uC774\uD130\uC785\uB2C8\uB2E4. \uAC70\uB798 \uC0DD\uC131 \uC804\uC5D0 \uD544\uC694\uD55C \uACBD\uC6B0\uC5D0\uB9CC \uBCF4\uC644\uD558\uC138\uC694.',
            collapsible: false,
        },
        {
            key: 'system',
            title: '\uC2DC\uC2A4\uD15C \uCC98\uB9AC\uC815\uBCF4',
            description: '\uCC98\uB9AC \uC0C1\uD0DC \uBC0F \uB0B4\uBD80 \uC2DD\uBCC4 \uC815\uBCF4\uC785\uB2C8\uB2E4. \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.',
            collapsible: true,
            default_open: false,
        },
    ]);
    const BUSINESS_ONLY_MODAL_FIELDS = Object.freeze([
        ...COMMON_BUSINESS_MODAL_FIELDS,
    ]);
    const BUSINESS_ONLY_MODAL_GROUPS = Object.freeze([
        {
            key: 'business',
            title: '\uC5C5\uBB34 \uBD84\uB958\uC815\uBCF4',
            description: '\uAC70\uB798 \uBC0F \uC804\uD45C \uC790\uB3D9 \uBC1C\uD589\uC5D0 \uC0AC\uC6A9\uD558\uB294 \uD575\uC2EC \uBD84\uB958 \uC815\uBCF4\uC785\uB2C8\uB2E4.',
        },
        {
            key: 'raw',
            title: '\uC6D0\uBCF8 \uC815\uBCF4',
            description: '\uC6D0\uBCF8 \uD30C\uC77C\uC5D0\uC11C \uAC00\uC838\uC628 \uC99D\uBE59 \uB370\uC774\uD130\uC785\uB2C8\uB2E4. \uD544\uC694\uD55C \uACBD\uC6B0\uC5D0\uB9CC \uBCF4\uC644\uD558\uC138\uC694.',
        },
        {
            key: 'system',
            title: '\uC2DC\uC2A4\uD15C \uCC98\uB9AC\uC815\uBCF4',
            description: '\uCC98\uB9AC \uC0C1\uD0DC \uBC0F \uB0B4\uBD80 \uC2DD\uBCC4 \uC815\uBCF4\uC785\uB2C8\uB2E4. \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.',
            collapsible: true,
            default_open: false,
        },
    ]);
    const CANONICAL_MODAL_SYSTEM_FIELD_LABELS = Object.freeze({
        source_type: '\uC790\uB8CC\uCD9C\uCC98',
        import_type: '\uC790\uB8CC\uC720\uD615',
        evidence_type: '\uC99D\uBE59\uC720\uD615',
    });
    let workTeamModalBridge = null;

    function ensureWorkTeamModalBridge() {
        if (workTeamModalBridge) {
            return workTeamModalBridge;
        }

        workTeamModalBridge = createWorkTeamModalModule({
            AdminPicker,
            api: WORK_TEAM_API,
            notify,
            openClientQuickCreate(defaultName = '') {
                openClientQuickCreate({
                    select: document.getElementById('modal-work-team-team-leader-client-id'),
                    initialValues: { client_name: defaultName },
                    getOptionText(values) {
                        return values.client_name || '';
                    },
                });
            },
            reloadTable() {},
        });
        workTeamModalBridge.initModal();
        workTeamModalBridge.bindModalEvents();
        workTeamModalBridge.preloadModalControls();
        workTeamModalBridge.bindAdminDateInputs();
        workTeamModalBridge.bindDateIconPicker();

        return workTeamModalBridge;
    }

    function setSelect2Option(select, id, text) {
        if (!select) return;
        const value = String(id || '').trim();
        const label = String(text || value).trim();
        if (value === '' || label === '') return;

        if (window.jQuery?.fn?.select2) {
            const $select = window.jQuery(select);
            $select.find(`option[value="${value.replace(/"/g, '\\"')}"]`).remove();
            $select.append(new Option(label, value, true, true));
            $select.val(value).trigger('change');
            return;
        }

        select.value = value;
    }

    function openQuickAddForRef(select, config) {
        if (!select || !config) return;

        if (['client', 'supplierCompany', 'customerCompany'].includes(config.picker)) {
            openClientQuickCreate({
                select,
                getOptionText(values, saved) {
                    return values.company_name || values.client_name || saved?.client_name || '';
                },
            });
            return;
        }

        if (config.picker === 'team') {
            ensureWorkTeamModalBridge().openCreateModal({
                onSaved(result, values) {
                    setSelect2Option(select, result?.id, values?.team_name || '');
                },
            });
        }
    }

    function bindRefQuickAdd(select, config) {
        if (!select || !config?.quickAddEnabled) return;

        select.removeEventListener?.('picker:add', select.__evidenceRefPickerAdd);
        select.__evidenceRefPickerAdd = () => {
            if (window.jQuery?.fn?.select2) {
                window.jQuery(select).val(null).trigger('change');
                window.jQuery(select).select2('close');
            }
            openQuickAddForRef(select, config);
        };
        select.addEventListener('picker:add', select.__evidenceRefPickerAdd);
    }

    function currentPolicyState(type = state.currentType) {
        const fallbackType = defaultEvidenceTypeCode();
        const storageKey = evidenceStatusTableSettingsStorageKey(normalizeEvidenceType(type || state.currentType || fallbackType));
        return readDataTableSettingsState(storageKey) || {};
    }

    function currentModalPolicy(row = state.editingRow) {
        const type = normalizeEvidenceType(
            row?.import_type
            || row?.source_type
            || state.currentType
            || state.activeFormat?.import_type
            || state.activeFormat?.format_name
            || defaultEvidenceTypeCode()
        );
        return evidenceTypePolicy(type) || {};
    }

    function currentModalPreset(row = state.editingRow) {
        return String(currentModalPolicy(row).modalPreset || 'default').trim().toLowerCase();
    }

    function isModalPreset(row, preset) {
        return currentModalPreset(row) === String(preset || '').trim().toLowerCase();
    }

    function resolvePolicyDisplayName(column = {}, fallback = '') {
        const key = String(column.system_field_name || column.original_column_key || editFieldKey(column) || column.key || '').trim();
        if (Object.prototype.hasOwnProperty.call(CANONICAL_MODAL_SYSTEM_FIELD_LABELS, key)) {
            return CANONICAL_MODAL_SYSTEM_FIELD_LABELS[key];
        }
        return resolveDataTableColumnDisplayName(
            { key, system_field_name: key, original_column_key: key },
            currentPolicyState(state.currentType),
            fallback || column.excel_column_name || column.label || key
        );
    }

    function resolvePolicyRequirementMode(column = {}) {
        const key = String(column.system_field_name || column.original_column_key || editFieldKey(column) || column.key || '').trim();
        const policyState = currentPolicyState(state.currentType);
        const requirementPolicyMap = policyState.columnRequirementPolicy && typeof policyState.columnRequirementPolicy === 'object'
            ? policyState.columnRequirementPolicy
            : {};
        const hasExplicitPolicy = Object.prototype.hasOwnProperty.call(requirementPolicyMap, key);
        const policy = resolveDataTableColumnRequirementPolicy(
            { key, system_field_name: key, original_column_key: key },
            policyState
        );
        if (!hasExplicitPolicy && Object.prototype.hasOwnProperty.call(CANONICAL_MODAL_SYSTEM_FIELD_LABELS, key)) {
            return 0;
        }
        if (hasExplicitPolicy && policy === 'none') return 0;
        if (policy === 'required') return 1;
        if (policy === 'optional') return 2;
        return Number(column.requirement_mode || column.is_required || 0);
    }

    function currentColumnPolicyPayload() {
        const policyState = currentPolicyState(state.currentType);
        return {
            column_display_name: policyState.columnDisplayName && typeof policyState.columnDisplayName === 'object'
                ? policyState.columnDisplayName
                : {},
            column_requirement_policy: policyState.columnRequirementPolicy && typeof policyState.columnRequirementPolicy === 'object'
                ? policyState.columnRequirementPolicy
                : {},
        };
    }

    function fallbackSpecialModalColumn(field = {}, options = {}) {
        const key = String(field.key || '').trim();
        const sourceDomain = String(options.sourceDomain || '').trim();
        const sourceTable = sourceDomain !== ''
            ? `ledger_${sourceDomain.replace(/^evidence-/, 'evidence_').replace(/-/g, '_')}`
            : '';
        return {
            original_column_key: key,
            system_field_name: key,
            excel_column_name: String(field.label || key).trim() || key,
            system_field_group: field.group || options.group || 'business',
            source_table: sourceTable,
            source_column: key,
            code_group: key === 'transaction_type'
                ? 'TRANSACTION_TYPE'
                : (key === 'transaction_direction' ? 'TRANSACTION_DIRECTION' : ''),
            data_type: String(field.data_type || 'varchar').trim().toLowerCase(),
            is_nullable: Number(field.is_required || 0) === 1 ? 'NO' : 'YES',
            is_required: Number(field.is_required || 0) === 1 ? 1 : 0,
            is_reference_column: 0,
            display_order: 0,
            column_order: 0,
            excel_column_index: 0,
            is_visible: 1,
        };
    }

    function isBusinessModalColumnKey(key = '') {
        const target = String(key || '').trim();
        return COMMON_BUSINESS_MODAL_FIELDS.some((field) => field.key === target);
    }

    function isRawModalColumnKey(key = '') {
        return /^raw_/i.test(String(key || '').trim());
    }

    function isRawModalColumn(column = {}) {
        const group = String(column.system_field_group || column.group || '').trim();
        return isRawModalColumnKey(editFieldKey(column)) || group.includes('\uC6D0\uBCF8');
    }

    function isSystemProcessingColumnKey(key = '') {
        const target = String(key || '').trim();
        return target !== '' && !isBusinessModalColumnKey(target) && !isRawModalColumnKey(target);
    }

    function isSystemProcessingColumn(column = {}) {
        const target = editFieldKey(column);
        return target !== '' && !isBusinessModalColumnKey(target) && !isRawModalColumn(column);
    }

    function compareColumnDatabaseOrder(a = {}, b = {}) {
        const resolveOrder = (column = {}, fallback = Number.MAX_SAFE_INTEGER) => {
            const candidates = [
                column.column_order,
                column.ordinal_position,
                column.ordinalPosition,
                column.excel_column_index,
                column.display_order,
            ];

            for (const candidate of candidates) {
                const value = Number(candidate);
                if (Number.isFinite(value) && value > 0) {
                    return value;
                }
            }

            return fallback;
        };

        const orderA = resolveOrder(a);
        const orderB = resolveOrder(b);
        if (orderA !== orderB) return orderA - orderB;

        return editFieldKey(a).localeCompare(editFieldKey(b), 'ko-KR', { numeric: true, sensitivity: 'base' });
    }

    function specialModalReadOnly(column = {}, resolver = null) {
        if (typeof resolver === 'function' && resolver(column) === true) {
            return true;
        }

        return isSystemProcessingColumn(column);
    }

    function decorateBankModalColumnPolicy(column = {}) {
        const key = editFieldKey(column);
        const displayName = resolvePolicyDisplayName(
            column,
            column.excel_column_name || column.label || column.system_field_name || key
        );
        const requirementMode = resolvePolicyRequirementMode(column);

        return {
            ...column,
            key,
            original_column_key: String(column.original_column_key || key).trim(),
            system_field_name: String(column.system_field_name || key).trim(),
            excel_column_name: displayName,
            label: displayName,
            requirement_mode: requirementMode,
            is_required: requirementMode === 1 ? 1 : 0,
        };
    }

    function bankModalColumns() {
        const columns = Array.isArray(state.activeFormat?.columns) ? state.activeFormat.columns : [];
        const visibleColumns = columns
            .filter((column) => !isDeprecatedFormatColumn(column))
            .filter((column) => editFieldKey(column) !== '');
        const policy = currentModalPolicy();
        const byKey = new Map(visibleColumns.map((column) => [editFieldKey(column), column]));
        const businessColumns = COMMON_BUSINESS_MODAL_FIELDS.map((field, index) => {
            const matched = byKey.get(field.key);
            if (matched) {
                return decorateBankModalColumnPolicy({
                    ...matched,
                    display_order: index + 1,
                });
            }
            return decorateBankModalColumnPolicy({
                ...fallbackSpecialModalColumn(field, {
                    group: 'business',
                    sourceDomain: policy.metaDomain,
                }),
                display_order: index + 1,
            });
        });
        const rawColumns = visibleColumns
            .filter((column) => isRawModalColumn(column))
            .sort(compareColumnDatabaseOrder)
            .map((column, index) => decorateBankModalColumnPolicy({
                ...column,
                display_order: businessColumns.length + index + 1,
            }));
        const systemColumns = visibleColumns
            .filter((column) => isSystemProcessingColumn(column))
            .sort(compareColumnDatabaseOrder)
            .map((column, index) => decorateBankModalColumnPolicy({
                ...column,
                display_order: businessColumns.length + rawColumns.length + index + 1,
                editable: 0,
            }));

        return [...businessColumns, ...rawColumns, ...systemColumns];
    }

    const BANK_LIKE_MODAL_FIELD_META = Object.freeze(
        BANK_LIKE_MODAL_FIELDS.reduce((accumulator, field) => {
            accumulator[field.key] = field;
            return accumulator;
        }, {})
    );
    function bankModalFieldMeta(key = '') {
        return BANK_LIKE_MODAL_FIELD_META[String(key || '').trim()] || null;
    }

    function bankModalGroupKeyForColumn(column = {}) {
        const key = editFieldKey(column);
        const meta = bankModalFieldMeta(key);
        if (meta?.group) return meta.group;
        return isSystemProcessingColumn(column) ? 'system' : 'business';
    }

    function bankModalIsReadOnly(column = {}) {
        return bankModalFieldMeta(editFieldKey(column))?.read_only === true;
    }

    function taxInvoiceModalFieldMeta(key = '') {
        return BUSINESS_ONLY_MODAL_FIELDS.find((field) => field.key === String(key || '').trim()) || null;
    }

    function taxInvoiceModalGroupKeyForColumn(column = {}) {
        const key = editFieldKey(column);
        return taxInvoiceModalFieldMeta(key)?.group || (isRawModalColumn(column) ? 'raw' : 'system');
    }

    function taxInvoiceModalColumns() {
        const columns = Array.isArray(state.activeFormat?.columns) ? state.activeFormat.columns : [];
        const visibleColumns = columns
            .filter((column) => !isDeprecatedFormatColumn(column))
            .filter((column) => editFieldKey(column) !== '');
        const policy = currentModalPolicy();
        const byKey = new Map(visibleColumns.map((column) => [editFieldKey(column), column]));
        const businessColumns = BUSINESS_ONLY_MODAL_FIELDS.map((field, index) => {
            const matched = byKey.get(field.key);
            if (matched) {
                return decorateBankModalColumnPolicy({
                    ...matched,
                    display_order: index + 1,
                });
            }
            return decorateBankModalColumnPolicy({
                ...fallbackSpecialModalColumn(field, {
                    group: field.group || 'business',
                    sourceDomain: policy.metaDomain,
                }),
                display_order: index + 1,
            });
        });
        const rawColumns = visibleColumns
            .filter((column) => isRawModalColumn(column))
            .sort(compareColumnDatabaseOrder)
            .map((column, index) => decorateBankModalColumnPolicy({
                ...column,
                display_order: businessColumns.length + index + 1,
            }));
        const systemColumns = visibleColumns
            .filter((column) => isSystemProcessingColumn(column))
            .sort(compareColumnDatabaseOrder)
            .map((column, index) => decorateBankModalColumnPolicy({
                ...column,
                display_order: businessColumns.length + rawColumns.length + index + 1,
                editable: 0,
            }));

        return [...businessColumns, ...rawColumns, ...systemColumns];
    }

    function renderEditField(column = {}, value = '', options = {}) {
        const key = editFieldKey(column);
        const title = String(column.excel_column_name || key);
        const systemField = String(column.system_field_name || '').trim();
        const cleanTitle = title.replace(/\s*\*$/u, '');
        const displayStar = requirementStar(column);
        const infoTone = infoColumnTone(column);
        const infoToneClass = infoTone ? ` evidence-edit-field-${infoTone}` : '';
        const readOnlyClass = options.readOnly ? ' evidence-edit-field-readonly' : '';
        const titleText = escapeHtml(cleanTitle);
        const systemFieldText = escapeHtml(systemField || key);
        const metaTitle = escapeHtml(options.hideSystemKey ? cleanTitle : `${cleanTitle} (${systemField || key})`);
        const systemFieldHtml = options.hideSystemKey
            ? ''
            : `<span class="text-muted evidence-edit-field-key" title="${systemFieldText}">${systemFieldText}</span>`;

        return `
            <label class="evidence-edit-field${infoToneClass}${readOnlyClass}" title="${metaTitle}">
                <span class="form-label small mb-1 d-flex align-items-center gap-2">
                    <span class="evidence-edit-title">
                        <span class="evidence-edit-title-text" title="${titleText}">${titleText}</span>
                        ${displayStar}
                    </span>
                    ${renderFieldBadge(column)}
                    ${systemFieldHtml}
                </span>
                ${renderEditInput(column, value, options)}
            </label>
        `;
    }

    function renderRefSelect(column = {}, value = '') {
        const config = businessRefPickerForColumn(column);
        const key = editFieldKey(column);
        const payload = mapped(state.editingRow);
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
        const selectedId = firstPayloadText(payload, [config.idKey]) || valueText(state.editingRow?.[config.idKey]);
        const displayField = displayFieldByPicker[config.picker] || config.nameKey || key;
        const payloadText = firstPayloadText(payload, [config.nameKey, ...config.keys, key]);
        const rowDisplayText = valueText(state.editingRow?.[displayField])
            || (
                displayField !== key
                    ? valueText(state.editingRow?.[key])
                    : ''
            );
        const fallbackValueText = valueText(value);
        const selectedText = rowDisplayText
            || (
                payloadText !== ''
                && payloadText !== selectedId
                && !looksLikeUuid(payloadText)
                    ? payloadText
                    : ''
            )
            || (
                fallbackValueText !== ''
                && fallbackValueText !== selectedId
                && !looksLikeUuid(fallbackValueText)
                    ? fallbackValueText
                    : ''
            );
        const optionValue = selectedId || selectedText;
        const textOnly = selectedId === '' && selectedText !== '';
        const option = optionValue !== ''
            ? `<option value="${escapeHtml(optionValue)}" selected>${escapeHtml(selectedText || optionValue)}</option>`
            : '';

        return `
            <select class="form-select form-select-sm evidence-edit-input evidence-edit-ref"
                data-key="${escapeHtml(key)}"
                data-value-kind="ref"
                data-ref-picker="${escapeHtml(config.picker)}"
                data-ref-id-key="${escapeHtml(config.idKey)}"
                data-ref-name-key="${escapeHtml(config.nameKey)}"
                data-ref-allow-text="${config.allowText ? '1' : '0'}"
                data-ref-current-text="${escapeHtml(selectedText)}"
                data-ref-current-text-only="${textOnly ? '1' : '0'}">
                <option value=""></option>
                ${option}
            </select>
        `;
    }

    function renderCodeSelect(column = {}, value = '') {
        const config = bankCodePickerForColumn(column);
        const key = editFieldKey(column);
        const selectedValue = codeValueForField(key, value);
        const selectedText = codeDisplayName(key, selectedValue);

        return `
            <select class="form-select form-select-sm evidence-edit-input evidence-edit-code"
                data-key="${escapeHtml(key)}"
                data-code-group="${escapeHtml(config.codeGroup)}"
                data-empty-label="${escapeHtml(config.emptyLabel)}"
                data-code-searchable="true">
                ${selectedValue !== '' ? `<option value="${escapeHtml(selectedValue)}" selected>${escapeHtml(selectedText || selectedValue)}</option>` : ''}
            </select>
        `;
    }

    function renderEditInput(column = {}, value = '', options = {}) {
        const key = editFieldKey(column);
        const type = editInputType(column, value);
        const safeKey = escapeHtml(key);
        const keepTime = isDateTimeColumn(column);
        const displayValue = type === 'date'
            ? normalizeDateInputValue(value, keepTime)
            : type === 'number'
                ? formatNumber(value)
                : type === 'business_number'
                    ? formatBizNumber(value)
                    : type === 'phone'
                        ? formatPhone(value)
                        : valueText(value);
        const safeValue = escapeHtml(displayValue === '-' ? '' : displayValue);
        const required = Number(column.is_required || 0) === 1 ? 'required' : '';
        const readOnlyText = options.readOnly ? 'readonly data-bank-readonly="1"' : '';
        const readOnlySelect = options.readOnly ? 'disabled data-bank-readonly="1"' : '';

        if (type === 'textarea') {
            return `<textarea class="form-control form-control-sm evidence-edit-input" rows="2" data-key="${safeKey}" ${required} ${readOnlyText}>${safeValue}</textarea>`;
        }
        if (type === 'ref') {
            return renderRefSelect(column, value).replace('<select ', `<select ${readOnlySelect} `);
        }
        if (type === 'code') {
            return renderCodeSelect(column, value).replace('<select ', `<select ${readOnlySelect} `);
        }
        if (type === 'number') {
            return `<input type="text" inputmode="decimal" class="form-control form-control-sm evidence-edit-input evidence-edit-number" data-key="${safeKey}" data-value-kind="number" value="${safeValue}" ${required} ${readOnlyText}>`;
        }
        if (type === 'phone') {
            return `<input type="text" inputmode="tel" class="form-control form-control-sm evidence-edit-input evidence-edit-phone" data-key="${safeKey}" data-value-kind="phone" value="${safeValue}" ${required} ${readOnlyText}>`;
        }
        if (type === 'business_number') {
            return `<input type="text" inputmode="numeric" class="form-control form-control-sm evidence-edit-input evidence-edit-business-number" data-key="${safeKey}" data-value-kind="business_number" value="${safeValue}" ${required} ${readOnlyText}>`;
        }
        if (type === 'date') {
            return `
                <div class="evidence-edit-date-wrap">
                    <input type="text" inputmode="numeric" class="form-control form-control-sm evidence-edit-input evidence-edit-date" data-key="${safeKey}" data-value-kind="date" data-keep-time="${keepTime ? '1' : '0'}" value="${safeValue}" placeholder="${keepTime ? 'YYYY-MM-DD HH:mm:ss' : 'YYYY-MM-DD'}" ${required} ${readOnlyText}>
                    <button type="button" class="btn btn-outline-secondary btn-sm evidence-edit-date-btn" aria-label="${keepTime ? '?? ??' : '?? ??'}">
                        <i class="bi bi-calendar3"></i>
                    </button>
                </div>
            `;
        }
        return `<input type="${type}" class="form-control form-control-sm evidence-edit-input" data-key="${safeKey}" value="${safeValue}" ${required} ${readOnlyText}>`;
    }

    function renderSpecialModalGroup(group = {}, columns = [], row = {}, options = {}) {
        if (!columns.length) return '';
        const fieldsHtml = columns
            .map((column) => renderEditField(column, editFieldValue(row, column), {
                readOnly: specialModalReadOnly(column, options.readOnlyResolver),
                hideSystemKey: true,
            }))
            .join('');

        if (group.collapsible) {
            const collapseId = `evidenceSpecialModalGroup-${group.key}`;
            const expanded = group.default_open === true;
            return `
                <section class="evidence-edit-group-card evidence-edit-group-card-${escapeHtml(group.key)}">
                    <button
                        type="button"
                        class="evidence-edit-group-toggle ${expanded ? '' : 'collapsed'}"
                        data-group-toggle="collapse"
                        data-group-target="#${escapeHtml(collapseId)}"
                        aria-expanded="${expanded ? 'true' : 'false'}"
                        aria-controls="${escapeHtml(collapseId)}">
                        <span class="evidence-edit-group-heading">
                            <span class="evidence-edit-group-title">${escapeHtml(group.title || '')}</span>
                            ${group.description ? `<span class="evidence-edit-group-description">${escapeHtml(group.description)}</span>` : ''}
                        </span>
                        <i class="bi bi-chevron-down evidence-edit-group-icon" aria-hidden="true"></i>
                    </button>
                    <div id="${escapeHtml(collapseId)}" class="collapse${expanded ? ' show' : ''}">
                        <div class="evidence-edit-group-fields">${fieldsHtml}</div>
                    </div>
                </section>
            `;
        }

        return `
            <section class="evidence-edit-group-card evidence-edit-group-card-${escapeHtml(group.key)}">
                <div class="evidence-edit-group-header">
                    <h6 class="evidence-edit-group-title">${escapeHtml(group.title || '')}</h6>
                    ${group.description ? `<p class="evidence-edit-group-description">${escapeHtml(group.description)}</p>` : ''}
                </div>
                <div class="evidence-edit-group-fields">${fieldsHtml}</div>
            </section>
        `;
    }

    function renderBankModalGroup(group = {}, columns = [], row = {}) {
        return renderSpecialModalGroup(group, columns, row, {
            readOnlyResolver: bankModalIsReadOnly,
        });
    }

    function renderBankModalFields(row = {}) {
        const editableColumns = bankModalColumns();
        if (editableColumns.length === 0) {
            state.refs.editFields.innerHTML = '<div class="text-muted text-center py-4">\uC218\uC815 \uAC00\uB2A5\uD55C \uC99D\uBE59 \uD56D\uBAA9\uC774 \uC5C6\uC2B5\uB2C8\uB2E4. \uD615\uC2DD \uC124\uC815\uC744 \uD655\uC778\uD574 \uC8FC\uC138\uC694.</div>';
            return;
        }

        const groups = new Map(BANK_LIKE_MODAL_GROUPS.map((group) => [group.key, []]));
        editableColumns.forEach((column) => {
            const groupKey = bankModalGroupKeyForColumn(column);
            if (!groups.has(groupKey)) groups.set(groupKey, []);
            groups.get(groupKey).push(column);
        });

        state.refs.editFields.innerHTML = `
            <div class="evidence-edit-groups evidence-edit-groups-bank">
                ${BANK_LIKE_MODAL_GROUPS
                    .map((group) => renderBankModalGroup(group, groups.get(group.key) || [], row))
                    .join('')}
            </div>
        `;
    }

    function renderTaxInvoiceModalGroup(group = {}, columns = [], row = {}) {
        return renderSpecialModalGroup(group, columns, row);
    }

    function renderTaxInvoiceModalFields(row = {}) {
        const editableColumns = taxInvoiceModalColumns();
        if (editableColumns.length === 0) {
            state.refs.editFields.innerHTML = '<div class="text-muted text-center py-4">\uC218\uC815 \uAC00\uB2A5\uD55C \uC99D\uBE59 \uD56D\uBAA9\uC774 \uC5C6\uC2B5\uB2C8\uB2E4. \uD615\uC2DD \uC124\uC815\uC744 \uD655\uC778\uD574 \uC8FC\uC138\uC694.</div>';
            return;
        }

        const groups = new Map(BUSINESS_ONLY_MODAL_GROUPS.map((group) => [group.key, []]));
        editableColumns.forEach((column) => {
            const groupKey = taxInvoiceModalGroupKeyForColumn(column);
            if (!groups.has(groupKey)) groups.set(groupKey, []);
            groups.get(groupKey).push(column);
        });

        state.refs.editFields.innerHTML = `
            <div class="evidence-edit-groups evidence-edit-groups-tax-invoice">
                ${BUSINESS_ONLY_MODAL_GROUPS
                    .map((group) => renderTaxInvoiceModalGroup(group, groups.get(group.key) || [], row))
                    .join('')}
            </div>
        `;
    }

    function renderDefaultModalFields(row = {}) {
        const editableColumns = defaultModalColumns();

        if (editableColumns.length === 0) {
            state.refs.editFields.innerHTML = '<div class="text-muted text-center py-4">\uC218\uC815 \uAC00\uB2A5\uD55C \uC99D\uBE59 \uD56D\uBAA9\uC774 \uC5C6\uC2B5\uB2C8\uB2E4. \uD615\uC2DD \uC124\uC815\uC744 \uD655\uC778\uD574 \uC8FC\uC138\uC694.</div>';
            return;
        }

        state.refs.editFields.innerHTML = editableColumns.map((column) => {
            const key = editFieldKey(column);
            const title = String(column.excel_column_name || key);
            const systemField = String(column.system_field_name || '').trim();
            const value = editFieldValue(row, column);
            const cleanTitle = title.replace(/\s*\*$/u, '');
            const displayStar = requirementStar(column);
            const infoTone = infoColumnTone(column);
            const infoToneClass = infoTone ? ` evidence-edit-field-${infoTone}` : '';
            const titleText = escapeHtml(cleanTitle);
            const systemFieldText = escapeHtml(systemField || key);
            const metaTitle = escapeHtml(`${cleanTitle} (${systemField || key})`);
            return `
                <label class="evidence-edit-field${infoToneClass}" title="${metaTitle}">
                    <span class="form-label small mb-1 d-flex align-items-center gap-2">
                        <span class="evidence-edit-title">
                            <span class="evidence-edit-title-text" title="${titleText}">${titleText}</span>
                            ${displayStar}
                        </span>
                        ${renderFieldBadge(column)}
                        <span class="text-muted evidence-edit-field-key" title="${systemFieldText}">${systemFieldText}</span>
                    </span>
                    ${renderEditInput(column, value)}
                </label>
            `;
        }).join('');
    }

    function defaultModalColumns() {
        const columns = Array.isArray(state.activeFormat?.columns) ? state.activeFormat.columns : [];
        return columns
            .slice()
            .sort(compareFieldDisplayOrder)
            .filter((column) => !isDeprecatedFormatColumn(column))
            .filter((column) => editFieldKey(column) !== '');
    }

    function editableColumnsForRow(row = {}) {
        if (isModalPreset(row, 'bank_like')) {
            return bankModalColumns();
        }
        if (isModalPreset(row, 'business_only')) {
            return taxInvoiceModalColumns();
        }
        return defaultModalColumns();
    }

    function renderEditFieldsByType(row = {}) {
        if (!state.refs.editFields) return;
        clearEditPickerLayers();
        if (isModalPreset(row, 'bank_like')) {
            renderBankModalFields(row);
            return;
        }
        if (isModalPreset(row, 'business_only')) {
            renderTaxInvoiceModalFields(row);
            return;
        }
        renderDefaultModalFields(row);
    }

    function bulkEditableColumns() {
        const columns = Array.isArray(state.activeFormat?.columns) ? state.activeFormat.columns : [];
        return columns
            .slice()
            .sort(compareFieldDisplayOrder)
            .filter((column) => !isDeprecatedFormatColumn(column))
            .filter((column) => editFieldKey(column) !== '')
            .filter((column) => ['basic', 'normalized', 'code', 'raw'].includes(infoColumnTone(column)));
    }

    function renderBulkInput(column = {}) {
        const type = editInputType(column, '');
        const key = editFieldKey(column);
        const safeKey = escapeHtml(key);
        const keepTime = isDateTimeColumn(column);

        if (type === 'ref') {
            const config = businessRefPickerForColumn(column);
            if (!config) return `<input type="text" class="form-control form-control-sm evidence-bulk-input" data-key="${safeKey}" disabled>`;
            return `
                <select class="form-select form-select-sm evidence-bulk-input evidence-bulk-ref"
                    data-key="${safeKey}"
                    data-value-kind="ref"
                    data-ref-picker="${escapeHtml(config.picker)}"
                    data-ref-id-key="${escapeHtml(config.idKey)}"
                    data-ref-name-key="${escapeHtml(config.nameKey)}"
                    data-ref-allow-text="${config.allowText ? '1' : '0'}"
                    disabled>
                    <option value=""></option>
                </select>
            `;
        }

        if (type === 'code') {
            const config = bankCodePickerForColumn(column);
            return `
                <select class="form-select form-select-sm evidence-bulk-input evidence-bulk-code"
                    data-key="${safeKey}"
                    data-code-group="${escapeHtml(config?.codeGroup || '')}"
                    data-empty-label="${escapeHtml(config?.emptyLabel || '선택하세요')}"
                    data-code-searchable="true"
                    disabled></select>
            `;
        }

        if (type === 'number') {
            return `<input type="text" inputmode="decimal" class="form-control form-control-sm evidence-bulk-input evidence-bulk-number" data-key="${safeKey}" data-value-kind="number" disabled>`;
        }
        if (type === 'business_number') {
            return `<input type="text" inputmode="numeric" class="form-control form-control-sm evidence-bulk-input evidence-bulk-business-number" data-key="${safeKey}" data-value-kind="business_number" disabled>`;
        }
        if (type === 'phone') {
            return `<input type="text" inputmode="tel" class="form-control form-control-sm evidence-bulk-input evidence-bulk-phone" data-key="${safeKey}" data-value-kind="phone" disabled>`;
        }
        if (type === 'date') {
            return `
                <div class="evidence-edit-date-wrap">
                    <input type="text" inputmode="numeric" class="form-control form-control-sm evidence-bulk-input evidence-bulk-date evidence-edit-date" data-key="${safeKey}" data-value-kind="date" data-keep-time="${keepTime ? '1' : '0'}" placeholder="${keepTime ? 'YYYY-MM-DD HH:mm:ss' : 'YYYY-MM-DD'}" disabled>
                    <button type="button" class="btn btn-outline-secondary btn-sm evidence-edit-date-btn" aria-label="${keepTime ? '?? ??' : '?? ??'}" disabled>
                        <i class="bi bi-calendar3"></i>
                    </button>
                </div>
            `;
        }
        if (type === 'textarea') {
            return `<textarea class="form-control form-control-sm evidence-bulk-input" rows="2" data-key="${safeKey}" disabled></textarea>`;
        }
        return `<input type="text" class="form-control form-control-sm evidence-bulk-input" data-key="${safeKey}" disabled>`;
    }

    function renderBulkFields() {
        if (!state.refs.bulkFields) return;
        const columns = bulkEditableColumns();
        if (columns.length === 0) {
            state.refs.bulkFields.innerHTML = '<div class="evidence-bulk-empty">일괄 수정할 수 있는 필드가 없습니다. 화면 설정과 선택 항목을 다시 확인해 주세요.</div>';
            return;
        }

        state.refs.bulkFields.innerHTML = columns.map((column) => {
            const key = editFieldKey(column);
            const tone = infoColumnTone(column);
            const title = String(column.excel_column_name || column.system_field_name || key || '항목');
            return `
                <div class="evidence-bulk-field evidence-bulk-field-${escapeHtml(tone)}">
                    <label class="evidence-bulk-check" title="${escapeHtml(title)}">
                        <input type="checkbox" class="evidence-bulk-toggle" data-key="${escapeHtml(key)}">
                        <span>${escapeHtml(title)} ${requirementStar(column)} ${renderFieldBadge(column)}</span>
                    </label>
                    <div class="evidence-bulk-control">
                        ${renderBulkInput(column)}
                    </div>
                </div>
            `;
        }).join('');
    }

    function initBulkRefSelect(select) {
        if (!select || select.dataset.refSelectBound === 'true') return;
        if (!window.jQuery?.fn?.select2) return;

        const config = Object.values(EVIDENCE_REF_PICKERS).find((item) => item.picker === select.dataset.refPicker);
        if (!config) return;
        const url = {
            supplierCompany: API.clientSearch,
            customerCompany: API.clientSearch,
            client: API.clientSearch,
            project: API.projectSearch,
            employee: API.employeeSearch,
            team: API.workTeamList,
            bankAccount: API.bankAccountSearch,
            card: API.cardSearch,
        }[config.picker];
        if (!url) return;

        AdminPicker.select2Ajax(select, {
            url,
            placeholder: config.placeholder,
            allowClear: true,
            tags: !!config.allowText,
            quickAddEnabled: config.quickAddEnabled === true,
            minimumInputLength: 0,
            dropdownParent: window.jQuery(state.refs.bulkModal),
            width: '100%',
            templateSelection(item) {
                return item.selectionText || item.refText || item.text || '';
            },
            createTag(params) {
                if (!config.allowText) return null;
                const term = String(params.term || '').trim();
                if (term === '') return null;
                return { id: term, text: term, isNew: true };
            },
            insertTag(data, tag) {
                data.unshift(tag);
            },
            dataBuilder(params) {
                if (typeof config.dataBuilder === 'function') {
                    return config.dataBuilder(params);
                }
                return { q: params.term || '', limit: 20, is_active: 1 };
            },
            processResults(data) {
                const rows = typeof config.processResults === 'function'
                    ? config.processResults(data)
                    : (data?.results ?? data?.data ?? []);
                return {
                    results: [
                        { id: '', text: '선택하세요' },
                        ...rows.map((row) => {
                            const text = typeof config.listText === 'function' ? config.listText(row) : config.label(row);
                            const refText = typeof config.saveText === 'function' ? config.saveText(row) : text;
                            return {
                                id: String(row.id ?? text ?? ''),
                                text,
                                refText,
                                selectionText: refText,
                                ...(typeof config.result === 'function' ? config.result(row) : {}),
                            };
                        }).filter((item) => item.id !== '' && item.text !== ''),
                        ...(config.quickAddEnabled === true ? [{ id: '__add__', text: '+추가' }] : []),
                    ],
                };
            },
        });

        window.jQuery(select)
            .off('select2:select.evidenceBulkRef')
            .on('select2:select.evidenceBulkRef', function (event) {
                const data = event.params?.data || {};
                this.dataset.refSelectedText = data.refText || data.selectionText || data.text || '';
            })
            .off('select2:clear.evidenceBulkRef')
            .on('select2:clear.evidenceBulkRef', function () {
                this.dataset.refSelectedText = '';
            });

        bindRefQuickAdd(select, config);

        select.dataset.refSelectBound = 'true';
    }

    function bindBulkFieldBehaviors() {
        state.refs.bulkFields?.querySelectorAll('.evidence-bulk-number').forEach((input) => {
            bindCommonNumberInput(input);
        });
        state.refs.bulkFields?.querySelectorAll('.evidence-bulk-business-number').forEach((input) => {
            if (input.dataset.businessNumberFormatBound === 'true') return;
            const apply = () => { input.value = formatBizNumber(input.value); };
            input.addEventListener('input', apply);
            input.addEventListener('blur', apply);
            input.dataset.businessNumberFormatBound = 'true';
        });
        state.refs.bulkFields?.querySelectorAll('.evidence-bulk-phone').forEach((input) => {
            if (input.dataset.phoneFormatBound === 'true') return;
            const apply = () => { input.value = formatPhone(input.value); };
            input.addEventListener('input', apply);
            input.addEventListener('blur', apply);
            input.dataset.phoneFormatBound = 'true';
        });
        state.refs.bulkFields?.querySelectorAll('.evidence-edit-date-wrap').forEach((wrap) => {
            bindDateEditInput(
                wrap.querySelector('.evidence-bulk-date'),
                wrap.querySelector('.evidence-edit-date-btn')
            );
        });
        state.refs.bulkFields?.querySelectorAll('.evidence-bulk-ref').forEach((select) => {
            initBulkRefSelect(select);
        });
        if (state.refs.bulkFields?.querySelector('select.evidence-bulk-code')) {
            void initCodeSelectControls(state.refs.bulkFields);
        }
    }

    function toggleBulkField(toggle) {
        const field = toggle.closest('.evidence-bulk-field');
        field?.querySelectorAll('.evidence-bulk-input, .evidence-edit-date-btn').forEach((control) => {
            control.disabled = !toggle.checked;
            if (window.jQuery?.fn?.select2 && control.matches?.('select')) {
                window.jQuery(control).prop('disabled', !toggle.checked).trigger('change.select2');
            }
        });
    }

    function openBulkEditModal() {
        const ids = state.table?.getSelectedIds?.() || Array.from(state.selectedIds);
        if (ids.length === 0) {
            notify('warning', '일괄 수정할 대상을 먼저 선택해 주세요.');
            return;
        }
        if (!state.activeFormat || !Array.isArray(state.activeFormat.columns) || state.activeFormat.columns.length === 0) {
            notify('warning', '자료유형에 대한 형식 정보를 불러오지 못했습니다. 다시 시도해 주세요.');
            return;
        }
        if (state.refs.bulkSubtitle) {
            state.refs.bulkSubtitle.textContent = `${selectedTypeLabel() || state.currentType} ${ids.length.toLocaleString('ko-KR')}건 선택`;
        }
        renderBulkFields();
        bindBulkFieldBehaviors();
        state.refs.bulkFields?.querySelectorAll('.evidence-bulk-toggle').forEach((toggle) => toggleBulkField(toggle));
        state.bulkModal = bootstrap.Modal.getOrCreateInstance(state.refs.bulkModal, { focus: false });
        state.bulkModal.show();
    }

    function escapeSelectorValue(value) {
        const text = String(value ?? '');
        if (window.CSS?.escape) return window.CSS.escape(text);
        return text.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function collectBulkPayload() {
        const patch = {};
        state.refs.bulkFields?.querySelectorAll('.evidence-bulk-toggle:checked').forEach((toggle) => {
            const key = toggle.dataset.key || '';
            const input = state.refs.bulkFields.querySelector(`.evidence-bulk-input[data-key="${escapeSelectorValue(key)}"]`);
            if (!key || !input) return;

            if (input.dataset.valueKind === 'ref') {
                const idKey = input.dataset.refIdKey || '';
                const nameKey = input.dataset.refNameKey || key;
                const allowsText = input.dataset.refAllowText === '1';
                const selectedId = selectValueForSave(input);
                const selectedText = selectTextForSave(input);
                const isFreeTextSelection = allowsText && selectedId !== '' && selectedText !== '' && selectedId === selectedText;
                patch[key] = selectedId === '' ? '' : selectedText;
                if (nameKey) patch[nameKey] = selectedId === '' ? '' : selectedText;
                if (idKey) patch[idKey] = selectedId !== '' && !isFreeTextSelection ? selectedId : '';
                return;
            }

            if (input.dataset.valueKind === 'number') {
                patch[key] = input.value === '' ? '' : String(parseCommonNumber(input.value));
                return;
            }
            if (input.dataset.valueKind === 'date') {
                patch[key] = normalizeDateInputValue(input.value, input.dataset.keepTime === '1');
                return;
            }
            if (input.dataset.valueKind === 'business_number') {
                patch[key] = formatBizNumber(input.value);
                return;
            }
            if (input.dataset.valueKind === 'phone') {
                patch[key] = formatPhone(input.value);
                return;
            }
            patch[key] = input.matches('select') ? selectValueForSave(input) : input.value;
        });
        return patch;
    }

    async function saveBulkEdit() {
        const ids = state.table?.getSelectedIds?.() || Array.from(state.selectedIds);
        const patch = collectBulkPayload();
        if (ids.length === 0) {
            notify('warning', '일괄 수정할 대상을 먼저 선택해 주세요.');
            return;
        }
        if (Object.keys(patch).length === 0) {
            notify('warning', '하나 이상 수정할 항목을 입력해 주세요.');
            return;
        }
        const mode = state.refs.bulkModal?.querySelector('input[name="evidenceBulkMode"]:checked')?.value || 'fill_blank';
        const button = state.refs.bulkSaveBtn;
        const originalText = button?.textContent || '일괄 수정 저장';
        if (button) {
            button.disabled = true;
            button.textContent = '저장 중...';
        }
        try {
            const json = await fetch(API.bulkSaveSeedRows, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    evidence_ids: ids,
                    parsed_patch: patch,
                    mode,
                }),
            }).then(async (response) => {
                const body = await response.json().catch(() => ({}));
                if (!response.ok || body.success === false) {
                    throw new Error(body.message || '일괄 수정 저장 중 오류가 발생했습니다.');
                }
                return body;
            });
            notify('success', json.message || '일괄 수정 저장이 완료되었습니다.');
            state.bulkModal?.hide();
            state.table?.clearSelectedIds?.();
            state.table?.ajax.reload(() => updateSummary(state.lastRows), false);
            void refreshEvidenceTypeCounts().catch(() => {});
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    }

    function renderEditFields(row = {}) {
        if (!state.refs.editFields) return;
        clearEditPickerLayers();
        renderEditFieldsByType(row);
    }

    function clearEditPickerLayers() {
        state.editPickerLayers.forEach((layer) => layer.remove());
        state.editPickerLayers = [];
    }

    function bindDateEditInput(input, button) {
        if (!input || input.dataset.dateFormatBound === 'true') return;

        const keepTime = input.dataset.keepTime === '1';
        const normalize = () => {
            input.value = normalizeDateInputValue(input.value, keepTime);
        };

        input.addEventListener('change', normalize);
        input.addEventListener('blur', normalize);

        if (!button) {
            input.dataset.dateFormatBound = 'true';
            return;
        }

        const layer = document.createElement('div');
        layer.className = 'picker is-hidden evidence-edit-picker-layer';
        document.body.appendChild(layer);
        state.editPickerLayers.push(layer);

        const picker = AdminPicker.create({ type: keepTime ? 'datetime' : 'today', container: layer });
        let primingPicker = false;

        picker.subscribe((pickerState, finalDate) => {
            if (primingPicker) return;
            if (!(finalDate instanceof Date)) return;

            input.value = keepTime && pickerState?.timeEnabled
                ? formatPickerDateTime(finalDate, /:\d{2}:\d{2}/.test(String(input.value || '')))
                : formatPickerDate(finalDate);
            input.dispatchEvent(new Event('change', { bubbles: true }));
            if (!keepTime) {
                picker.close?.();
            }
        });

        input.addEventListener('focus', () => {
            picker.close?.();
        });

        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (input.disabled) return;
            primingPicker = true;
            applyDateToPicker(picker, input.value, keepTime);
            requestAnimationFrame(() => {
                primingPicker = false;
            });
            picker.open?.({ anchor: input });
        });

        input.dataset.dateFormatBound = 'true';
    }

    function editInputByKey(key) {
        if (!state.refs.editFields || !key) return null;
        return state.refs.editFields.querySelector(`.evidence-edit-input[data-key="${escapeSelectorValue(key)}"]`);
    }

    function formatValueForEditInput(input, value) {
        if (input?.dataset?.valueKind === 'business_number') return formatBizNumber(value);
        if (input?.dataset?.valueKind === 'phone') return formatPhone(value);
        return valueText(value);
    }

    function applyEditValueIfBlank(key, value, options = {}) {
        const input = editInputByKey(key);
        const nextValue = formatValueForEditInput(input, value);
        if (!input || nextValue === '') return;
        if (!options.overwrite && String(input.value ?? '').trim() !== '') return;

        input.value = nextValue;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function applyRefAutofill(config = {}, data = {}) {
        if (!config.autofill) return;
        Object.entries(config.autofill).forEach(([targetKey, sourceKey]) => {
            applyEditValueIfBlank(targetKey, data[sourceKey], { overwrite: true });
        });
    }

    function initEditRefSelect(select) {
        const config = Object.values(EVIDENCE_REF_PICKERS).find((item) => item.picker === select.dataset.refPicker);
        if (!config) return;
        initEvidenceRefSelect(select, {
            modal: state.refs.editModal,
            api: API,
            onSelect(_select, data) {
                applyRefAutofill(config, data);
            },
        });
        bindRefQuickAdd(select, config);
    }

    function bindEditFieldBehaviors() {
        state.refs.editFields?.querySelectorAll('.evidence-edit-group-toggle[data-group-target]').forEach((button) => {
            if (button.dataset.groupToggleBound === 'true') return;

            button.addEventListener('click', (event) => {
                event.preventDefault();
                const selector = button.dataset.groupTarget || '';
                if (!selector) return;
                const target = state.refs.editFields?.querySelector(selector);
                if (!target) return;

                const expanded = button.getAttribute('aria-expanded') === 'true';
                const nextExpanded = !expanded;
                button.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
                button.classList.toggle('collapsed', !nextExpanded);
                target.classList.toggle('show', nextExpanded);
            });

            button.dataset.groupToggleBound = 'true';
        });

        state.refs.editFields?.querySelectorAll('.evidence-edit-number').forEach((input) => {
            bindCommonNumberInput(input);
        });

        state.refs.editFields?.querySelectorAll('.evidence-edit-business-number').forEach((input) => {
            if (input.dataset.businessNumberFormatBound === 'true') return;

            const apply = () => {
                input.value = formatBizNumber(input.value);
            };

            input.addEventListener('input', apply);
            input.addEventListener('blur', apply);
            apply();
            input.dataset.businessNumberFormatBound = 'true';
        });

        state.refs.editFields?.querySelectorAll('.evidence-edit-phone').forEach((input) => {
            if (input.dataset.phoneFormatBound === 'true') return;

            const apply = () => {
                input.value = formatPhone(input.value);
            };

            input.addEventListener('input', apply);
            input.addEventListener('blur', apply);
            apply();
            input.dataset.phoneFormatBound = 'true';
        });

        state.refs.editFields?.querySelectorAll('.evidence-edit-date-wrap').forEach((wrap) => {
            bindDateEditInput(
                wrap.querySelector('.evidence-edit-date'),
                wrap.querySelector('.evidence-edit-date-btn')
            );
        });

        state.refs.editFields?.querySelectorAll('.evidence-edit-ref').forEach((select) => {
            initEditRefSelect(select);
        });
        if (state.refs.editFields?.querySelector('select.evidence-edit-code')) {
            void initCodeSelectControls(state.refs.editFields);
        }
    }

    function applyBankModalLockState(isEditable) {
        if (!['bank_like', 'business_only'].includes(currentModalPreset())) return;
        state.refs.editFields?.querySelectorAll('.evidence-edit-input[data-bank-readonly="1"]').forEach((input) => {
            if (input.matches('select')) {
                input.disabled = true;
                if (window.jQuery?.fn?.select2) {
                    window.jQuery(input).prop('disabled', true).trigger('change.select2');
                }
                return;
            }
            input.readOnly = true;
            input.disabled = !isEditable;
            input.classList.toggle('evidence-edit-input-locked', true);
        });
        state.refs.editFields?.querySelectorAll('.evidence-edit-date-btn').forEach((button) => {
            const wrapper = button.closest('.evidence-edit-date-wrap');
            const input = wrapper?.querySelector('.evidence-edit-input[data-bank-readonly="1"]');
            if (input) {
                button.disabled = true;
            }
        });
    }

    function collectEditPayload() {
        const next = { ...mapped(state.editingRow) };
        state.refs.editFields?.querySelectorAll('.evidence-edit-input').forEach((input) => {
            const key = input.dataset.key || '';
            if (!key) return;
            if (input.dataset.bankReadonly === '1') return;
            if (input.dataset.valueKind === 'ref') {
                const idKey = input.dataset.refIdKey || '';
                const nameKey = input.dataset.refNameKey || key;
                const allowsText = input.dataset.refAllowText === '1';
                const selectedId = selectValueForSave(input);
                let selectedText = selectTextForSave(input, { includeCurrentText: true });
                const isTextOnlyInitialValue = input.dataset.refCurrentTextOnly === '1'
                    && selectedId !== ''
                    && selectedId === String(input.dataset.refCurrentText || '').trim();
                const isFreeTextSelection = allowsText && selectedId !== '' && selectedText !== '' && selectedId === selectedText;
                if (selectedId === '') {
                    selectedText = '';
                }

                next[key] = selectedText;
                if (nameKey) {
                    next[nameKey] = selectedText;
                }
                if (idKey) {
                    next[idKey] = selectedId !== '' && !isTextOnlyInitialValue && !isFreeTextSelection ? selectedId : '';
                }
                return;
            }
            if (input.dataset.valueKind === 'number') {
                next[key] = input.value === '' ? '' : String(parseCommonNumber(input.value));
                return;
            }
            if (input.dataset.valueKind === 'date') {
                const keepTime = input.dataset.keepTime === '1';
                const normalized = normalizeDateInputValue(input.value, keepTime);
                next[key] = normalized;
                if (keepTime && /transaction_(date|time|datetime|at)$/i.test(key)) {
                    next.raw_transaction_datetime = normalized;
                }
                return;
            }
            if (input.dataset.valueKind === 'business_number') {
                next[key] = formatBizNumber(input.value);
                return;
            }
            if (input.dataset.valueKind === 'phone') {
                next[key] = formatPhone(input.value);
                return;
            }
            next[key] = valueText(input.matches('select') ? selectValueForSave(input) : input.value);
        });
        Object.keys(next).forEach((key) => {
            if (key.startsWith('_')) return;
            const value = next[key];
            if (value && typeof value === 'object') {
                next[key] = valueText(value);
            }
            if (next[key] === '[object Object]') {
                next[key] = '';
            }
        });
        return next;
    }

    function requiredEditColumns() {
        if (isModalPreset(null, 'bank_like')) {
            return bankModalColumns().filter((column) => resolvePolicyRequirementMode(column) === 1
                && editFieldKey(column) !== 'raw_balance_amount'
                && !specialModalReadOnly(column, bankModalIsReadOnly));
        }
        if (isModalPreset(null, 'business_only')) {
            return taxInvoiceModalColumns().filter((column) => resolvePolicyRequirementMode(column) === 1
                && editFieldKey(column) !== 'raw_balance_amount'
                && !specialModalReadOnly(column));
        }
        const columns = Array.isArray(state.activeFormat?.columns) ? state.activeFormat.columns : [];
        return columns.filter((column) => resolvePolicyRequirementMode(column) === 1 && editFieldKey(column) !== 'raw_balance_amount');
    }

    function validateRequiredEditFields() {
        const missing = [];
        let firstInput = null;
        requiredEditColumns().forEach((column) => {
            const key = editFieldKey(column);
            if (!key) return;
            const input = editInputByKey(key);
            const value = input
                ? (input.dataset.valueKind === 'ref'
                    ? (selectTextForSave(input, { includeCurrentText: true }) || selectValueForSave(input))
                    : (input.matches('select') ? selectValueForSave(input) : String(input.value ?? '').trim()))
                : '';
            input?.classList.remove('is-invalid');
            if (value !== '') return;
            input?.classList.add('is-invalid');
            firstInput = firstInput || input;
            missing.push(String(resolvePolicyDisplayName(column, column.excel_column_name || column.label || column.system_field_name || key)).replace(/\s*\*$/u, ''));
        });

        if (missing.length === 0) return true;
        firstInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInput?.focus?.();
        notify('warning', `?? ???? ??? ???. ${missing.slice(0, 5).join(', ')}${missing.length > 5 ? ` ? ${missing.length - 5}?` : ''}`);
        return false;
    }

    async function saveEditingRow() {
        if (!state.editingRow) return;
        if (!validateRequiredEditFields()) return;
        const payload = collectEditPayload();
        const policyPayload = currentColumnPolicyPayload();
        if (!validateBusinessProjectRule(payload)) return;
        const isNew = state.editingRow.__isNew === true;
        const json = await fetch(isNew ? API.createEvidence : API.saveSeedRow, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: state.editingRow.id || '',
                import_type: state.currentType,
                parsed_json: payload,
                column_display_name: policyPayload.column_display_name,
                column_requirement_policy: policyPayload.column_requirement_policy,
            }),
        }).then(async (response) => {
            const body = await response.json().catch(() => ({}));
            if (!response.ok || body.success === false) {
                throw new Error(body.message || '저장 중 오류가 발생했습니다.');
            }
            return body;
        });
        notify('success', json.message || (isNew ? '새 증빙이 저장되었습니다.' : '증빙이 수정되었습니다.'));
        state.editModal?.hide();
        state.editingRow = null;
        state.table?.ajax.reload(() => updateSummary(state.lastRows), false);
        void refreshEvidenceTypeCounts().catch(() => {});
    }

    async function openEvidenceEditModalLatest(row = {}) {
        if (!row?.id || !state.refs.editModal) return;
        await ensureActiveFormat(row.import_type || row.source_type || state.currentType);
        if (!state.activeFormat || !Array.isArray(state.activeFormat.columns) || state.activeFormat.columns.length === 0) {
            notify('warning', '자료유형에 대한 형식 정보를 불러오지 못했습니다. 다시 시도해 주세요.');
            return;
        }
        state.editingRow = row;
        state.refs.editId.value = row.id;
        const typeLabel = evidenceTypeDisplayName(row);
        if (state.refs.editTitle) {
            state.refs.editTitle.textContent = `${typeLabel} 증빙 수정`;
        }
        if (state.refs.editSubtitle) {
            state.refs.editSubtitle.textContent = [
                `순번 ${row.sort_no || '-'}`,
                typeLabel,
                normalizedStatus(row) || '-',
            ].join(' / ');
        }
        renderEditFieldsByType(row);
        bindEditFieldBehaviors();
        const isEditable = normalizedStatus(row) !== 'PROCESSED' && normalizedStatus(row) !== 'DELETED';
        state.refs.editFields?.querySelectorAll('.evidence-edit-input').forEach((input) => {
            input.disabled = !isEditable;
            if (input.classList.contains('evidence-edit-ref') && window.jQuery?.fn?.select2) {
                window.jQuery(input).prop('disabled', !isEditable).trigger('change.select2');
            }
        });
        state.refs.editFields?.querySelectorAll('.evidence-edit-date-btn').forEach((button) => {
            button.disabled = !isEditable;
        });
        applyBankModalLockState(isEditable);
        if (state.refs.editSaveBtn) state.refs.editSaveBtn.disabled = !isEditable;
        state.editModal = bootstrap.Modal.getOrCreateInstance(state.refs.editModal, { focus: false });
        state.editModal.show();
    }

    async function openEvidenceNewModalLatest() {
        if (!state.refs.editModal) return;
        await ensureActiveFormat(state.currentType);
        if (!state.activeFormat || !Array.isArray(state.activeFormat.columns) || state.activeFormat.columns.length === 0) {
            notify('warning', '자료유형에 대한 형식 정보를 불러오지 못했습니다. 다시 시도해 주세요.');
            return;
        }

        state.editingRow = {
            __isNew: true,
            id: '',
            import_type: state.currentType,
            source_type: state.currentType,
            import_type_name: selectedTypeLabel() || state.currentType,
            source_type_name: selectedTypeLabel() || state.currentType,
        };
        state.refs.editId.value = '';
        const typeLabel = evidenceTypeDisplayName(state.editingRow);
        if (state.refs.editTitle) {
            state.refs.editTitle.textContent = `${typeLabel} 새 증빙`;
        }
        if (state.refs.editSubtitle) {
            state.refs.editSubtitle.textContent = [
                typeLabel,
                '신규 입력',
            ].join(' / ');
        }
        renderEditFieldsByType(state.editingRow);
        bindEditFieldBehaviors();
        state.refs.editFields?.querySelectorAll('.evidence-edit-input').forEach((input) => {
            input.disabled = false;
            if (input.classList.contains('evidence-edit-ref') && window.jQuery?.fn?.select2) {
                window.jQuery(input).prop('disabled', false).trigger('change.select2');
            }
        });
        state.refs.editFields?.querySelectorAll('.evidence-edit-date-btn').forEach((button) => {
            button.disabled = false;
        });
        applyBankModalLockState(true);
        if (state.refs.editSaveBtn) state.refs.editSaveBtn.disabled = false;
        state.editModal = bootstrap.Modal.getOrCreateInstance(state.refs.editModal, { focus: false });
        state.editModal.show();
    }

    async function openEditModal(row = {}) {
        return openEvidenceEditModalLatest(row);
    }

    async function openNewEvidenceModal() {
        return openEvidenceNewModalLatest();
    }

    return {
        renderRefSelect,
        renderCodeSelect,
        renderEditInput,
        editableColumnsForRow,
        bulkEditableColumns,
        renderBulkInput,
        renderBulkFields,
        initBulkRefSelect,
        bindBulkFieldBehaviors,
        toggleBulkField,
        openBulkEditModal,
        collectBulkPayload,
        saveBulkEdit,
        renderEditFields: renderEditFieldsByType,
        clearEditPickerLayers,
        bindDateEditInput,
        editInputByKey,
        formatValueForEditInput,
        initEditRefSelect,
        bindEditFieldBehaviors,
        collectEditPayload,
        requiredEditColumns,
        validateRequiredEditFields,
        saveEditingRow,
        openEvidenceEditModalLatest,
        openEvidenceNewModalLatest,
        openEditModal,
        openNewEvidenceModal,
    };
}
