import { actorDisplay } from '/public/assets/js/common/actor.js';

export function createEvidenceTableModule({
    state,
    API,
    SearchForm,
    createDataTable,
    bindRowReorder,
    normalizeEvidenceType,
    evidenceTypePolicy,
    defaultEvidenceTypeCode,
    evidenceMetaDomain,
    evidenceMetaColumnsCache,
    fetchDataTableMetaColumns,
    valueText,
    formatNumber,
    escapeHtml,
    evidenceFieldValue,
    codeDisplayName,
    mapped,
    columnAliasKeys,
    firstPayloadValue,
    formatDateInputValue,
    parseCommonNumber,
    notify,
    textColumn,
    moneyColumn,
    badge,
    clientName,
    amount,
    processStatus,
    updateSummary,
    selectedTypeLabel,
    currentConfig,
    DEFAULT_DATE_OPTION,
    compareFieldDisplayOrder,
    compareFormatColumns,
    formatEvidenceColumnDisplayLabel,
    formatValue,
    isAmountColumn,
    isDateColumn,
    isDateTimeColumn,
    formatDateValue,
    formatDateTimeValue,
    normalizeDateInputValue,
    evidenceStatusTableSettingsStorageKey,
    handleEvidenceStatusTableOrderChange,
    refreshEvidenceTypeCounts,
    openEvidenceNewModalLatest,
    openEvidenceTrash,
    renderEvidenceTypeTabs,
    showModal,
    syncExcelManager,
    readDataTableSettingsState,
}) {
    let rebuildSequence = 0;
    let rebuildQueue = Promise.resolve();
    function isBankTransactionEvidenceRow(row = {}) {
        const payload = mapped(row);
        return String(row.import_type || row.seed_source_type || row.source_type || payload.import_type || '').trim().toUpperCase() === 'BANK_TRANSACTION';
    }

    function currentTableSettingsStorageKey(type = state.currentType) {
        return evidenceStatusTableSettingsStorageKey(type);
    }

    function normalizeToolbarButtonText(value = '') {
        return String(value || '').replace(/\s+/g, '').trim();
    }

    function arrangeEvidenceToolbar(table) {
        const wrapper = table?.table?.().container?.();
        const toolbar = wrapper?.querySelector?.('.dt-top');
        const buttonWrap = toolbar?.querySelector?.('.dt-buttons');
        if (!toolbar || !buttonWrap) {
            return;
        }

        buttonWrap.querySelectorAll('.buttons-colvis, .buttons-collection').forEach((button) => button.remove());

        const orderMap = new Map([
            ['↑', 1],
            ['↓', 2],
            ['복사', 3],
            ['삭제', 4],
            ['휴지통', 5],
            ['엑셀관리', 6],
            ['신규등록', 7],
        ]);

        const orderedButtons = Array.from(buttonWrap.children)
            .filter((node) => node instanceof HTMLElement)
            .sort((left, right) => {
                const leftOrder = orderMap.get(normalizeToolbarButtonText(left.textContent)) ?? 999;
                const rightOrder = orderMap.get(normalizeToolbarButtonText(right.textContent)) ?? 999;
                return leftOrder - rightOrder;
            });

        orderedButtons.forEach((button) => buttonWrap.appendChild(button));
    }

    function currentColumnPolicyState(type = state.currentType) {
        const userSettingPageKey = evidenceMetaDomain(type);
        return readDataTableSettingsState(currentTableSettingsStorageKey(type), {
            metaDomain: userSettingPageKey,
            userSettingPageKey,
        }) || {};
    }

    function currentColumnDisplayNameMap(type = state.currentType) {
        const stateValue = currentColumnPolicyState(type);
        const displayNames = stateValue.columnDisplayName && typeof stateValue.columnDisplayName === 'object'
            ? stateValue.columnDisplayName
            : {};
        if (String(displayNames.operation_type || '').replace(/\s+/g, '') === '입출금유형') {
            return { ...displayNames, operation_type: '업무유형' };
        }
        return displayNames;
    }

    function currentColumnRequirementPolicyMap(type = state.currentType) {
        const stateValue = currentColumnPolicyState(type);
        return stateValue.columnRequirementPolicy && typeof stateValue.columnRequirementPolicy === 'object'
            ? stateValue.columnRequirementPolicy
            : {};
    }

    function resolveDisplayName(key, fallback, type = state.currentType) {
        const configured = currentColumnDisplayNameMap(type)[String(key || '').trim()];
        if (String(key || '').trim() === 'operation_type'
            && String(configured || fallback || '').replace(/\s+/g, '') === '입출금유형') {
            return '업무유형';
        }
        return String(configured || fallback || key || '').trim() || String(key || '').trim();
    }

    function defaultColumnDisplayNameMap() {
        const map = {};
        const activeColumns = Array.isArray(state.activeFormat?.columns) ? state.activeFormat.columns : [];
        activeColumns.forEach((column) => {
            const key = String(column.system_field_name || column.original_column_key || '').trim();
            if (!key) return;
            const label = String(column.excel_column_name || column.label || key).trim() || key;
            map[key] = key === 'operation_type' && label.replace(/\s+/g, '') === '입출금유형'
                ? '업무유형'
                : label;
        });
        return map;
    }

    function defaultColumnRequirementPolicyMap() {
        const map = {};
        const activeColumns = Array.isArray(state.activeFormat?.columns) ? state.activeFormat.columns : [];
        activeColumns.forEach((column) => {
            const key = String(column.system_field_name || column.original_column_key || '').trim();
            if (!key) return;
            map[key] = Number(column.requirement_mode || column.is_required || 0) === 1 ? 'required' : 'none';
        });
        return map;
    }
    async function loadEvidenceMetaColumns(type = state.currentType) {
            const normalizedType = normalizeEvidenceType(type || '');
            const domain = evidenceMetaDomain(normalizedType);
            if (!normalizedType || !domain) {
                return [];
            }
            if (evidenceMetaColumnsCache.has(domain)) {
                return evidenceMetaColumnsCache.get(domain) || [];
            }
    
            const data = await fetchDataTableMetaColumns({ metaDomain: domain });
    
            const normalized = data
                .map((column, index) => {
                    const key = String(column?.key || column?.value || '').trim();
                    if (!key) return null;
    
                    return {
                        key,
                        label: String(column?.label || column?.title || key).trim() || key,
                        dataType: String(column?.data_type || '').trim().toLowerCase(),
                        ordinalPosition: Number(column?.ordinal_position || column?.settings_order || index + 1) || (index + 1),
                    };
                })
                .filter(Boolean)
                .sort((left, right) => {
                    if (left.ordinalPosition !== right.ordinalPosition) {
                        return left.ordinalPosition - right.ordinalPosition;
                    }
    
                    return left.key.localeCompare(right.key, 'en');
                });
    
            evidenceMetaColumnsCache.set(domain, normalized);
            return normalized;
        }

    async function resolveEvidenceDateOptions(type = state.currentType) {
            const metaColumns = await loadEvidenceMetaColumns(type);
            const normalizedType = normalizeEvidenceType(type || state.currentType || '');
            const policy = evidenceTypePolicy(normalizedType);
            const physicalDateColumns = metaColumns.filter((column) => metaDateColumn(column));
            const metaByKey = new Map(
                physicalDateColumns.map((column) => [String(column.key || '').trim(), column])
            );
            const candidateKeys = Array.isArray(policy?.dateCandidateKeys) && policy.dateCandidateKeys.length > 0
                ? policy.dateCandidateKeys
                : ['evidence_date', 'transaction_date', 'created_at', 'updated_at'];
    
            const options = [];
            const added = new Set();
            candidateKeys.forEach((key) => {
                const meta = metaByKey.get(key);
                if (!meta || added.has(key)) return;
                added.add(key);
                options.push({
                    value: key,
                    label: String(meta.label || key).trim() || key,
                });
            });
    
            if (options.length === 0) {
                physicalDateColumns.forEach((column) => {
                    const key = String(column.key || '').trim();
                    if (!key || added.has(key)) return;
                    added.add(key);
                    options.push({
                        value: key,
                        label: String(column.label || key).trim() || key,
                    });
                });
            }
    
            return options.length > 0 ? options : [DEFAULT_DATE_OPTION];
        }

    function metaNumericColumn(column = {}) {
            const key = String(column?.key || '').trim().toLowerCase();
            const label = String(column?.label || '').trim();
            const dataType = String(column?.dataType || '').trim().toLowerCase();

            return ['int', 'integer', 'decimal', 'numeric', 'float', 'double', 'bigint'].includes(dataType)
                || /(^|_)(amount|price|total|vat|tax|fee|duty|qty|quantity|supply|deposit|withdraw|withdrawal|settlement|gross|balance|customs|rate)(_|$)/.test(key)
                || /(금액|합계|부가세|공급가|수량|단가|관세|수수료|입금|출금|잔액|정산|원천세)/.test(label);
        }

    function metaDateColumn(column = {}) {
            const key = String(column?.key || '').trim().toLowerCase();
            const label = String(column?.label || '').trim();
            const dataType = String(column?.dataType || '').trim().toLowerCase();

            return ['date', 'datetime', 'timestamp'].includes(dataType)
                || /(date|datetime|time|_at|_on)$/i.test(key)
                || /(일시|일자|날짜|시간)/.test(label);
        }

    function metaDateTimeColumn(column = {}) {
            const key = String(column?.key || '').trim().toLowerCase();
            const dataType = String(column?.dataType || '').trim().toLowerCase();

            return ['datetime', 'timestamp'].includes(dataType)
                || /(datetime|time|_at)$/i.test(key);
        }

    function codeFieldDisplayText(field, value) {
            const text = valueText(value).trim();
            if (text === '') return '';
            return codeDisplayName(field, text) || text;
        }

    function directionDisplayText(value) {
            return codeFieldDisplayText('transaction_direction', value);
        }

    function decorateRowDisplayFields(row = {}) {
            if (!row || typeof row !== 'object') return row;

            const mappedPayload = row.mapped_payload && typeof row.mapped_payload === 'object'
                ? row.mapped_payload
                : {};
            const directionSource = valueText(
                row.transaction_direction
                ?? mappedPayload.transaction_direction
                ?? mappedPayload.bank_transaction_transaction_direction
                ?? ''
            );
            const directionLabel = valueText(row.transaction_direction_name) || directionDisplayText(directionSource);
            if (directionLabel !== '') {
                row.__transaction_direction_label = directionLabel;
            }

            const businessUnitSource = valueText(
                row.business_unit
                ?? mappedPayload.business_unit
                ?? ''
            );
            const businessUnitLabel = valueText(row.business_unit_name) || codeFieldDisplayText('business_unit', businessUnitSource);
            if (businessUnitLabel !== '') {
                row.__business_unit_label = businessUnitLabel;
            }

            const operationTypeSource = valueText(
                row.operation_type
                ?? mappedPayload.operation_type
                ?? ''
            );
            const operationTypeLabel = valueText(row.operation_type_name) || codeFieldDisplayText('operation_type', operationTypeSource);
            if (operationTypeLabel !== '') {
                row.__operation_type_label = operationTypeLabel;
            }

            return row;
        }

    function evidenceStatusDisplay(value) {
            const raw = valueText(value).trim().toUpperCase();
            if (raw === '') return { text: '-', className: 'text-bg-light' };
            if (raw === 'COMPLETED') {
                return { text: '완료', className: 'text-bg-success' };
            }
            if (raw === 'CORRECTION_REQUIRED') {
                return { text: '보정필요', className: 'text-bg-warning' };
            }
            return { text: raw, className: 'text-bg-light' };
        }

    function isTransactionDirectionColumn(column = {}) {
            const keys = [
                column.system_field_name,
                column.original_column_key,
                column.key,
                column.data,
            ].map((value) => String(value || '').trim().toLowerCase());
            const title = String(column.excel_column_name || column.label || column.title || '').replace(/\s+/g, '');

            return keys.includes('transaction_direction')
                || keys.includes('bank_transaction_transaction_direction')
                || title === '거래구분'
                || title === '거래방향';
        }

    function isEvidenceStatusColumn(column = {}) {
            const keys = [
                column.system_field_name,
                column.original_column_key,
                column.key,
                column.data,
            ].map((value) => String(value || '').trim().toLowerCase());
            const title = String(column.excel_column_name || column.label || column.title || '').replace(/\s+/g, '');

            return keys.includes('evidence_status') || title === '증빙상태';
        }

    function displayCodeFieldForColumn(column = {}) {
            const keys = [
                column.system_field_name,
                column.original_column_key,
                column.key,
                column.data,
            ].map((value) => String(value || '').trim().toLowerCase());
            const isBankTransaction = String(state.currentType || '').trim().toUpperCase() === 'BANK_TRANSACTION';

            if (keys.includes('business_unit')) return 'business_unit';
            if (keys.includes('operation_type')) return 'operation_type';
            if (keys.includes('transaction_direction') || keys.includes('bank_transaction_transaction_direction')) return 'transaction_direction';
            if (keys.includes('source_type')) return 'source_type';
            if (keys.includes('import_type')) return 'import_type';

            return '';
        }

    function codeColumnDisplayValue(row = {}, field = '', fallback = '') {
            const preparedLabels = {
                business_unit: row.__business_unit_label,
                operation_type: row.__operation_type_label,
                transaction_direction: row.__transaction_direction_label,
            };
            return valueText(preparedLabels[field])
                || valueText(row?.[`${field}_name`])
                || codeFieldDisplayText(field, fallback)
                || valueText(fallback);
        }

    function actorFieldForColumn(column = {}) {
            return [column.system_field_name, column.original_column_key, column.key, column.data]
                .map((value) => String(value || '').trim().toLowerCase())
                .find((key) => key.endsWith('_by')) || '';
        }

    function actorColumnDisplayValue(row = {}, field = '') {
            if (!field || (valueText(row?.[field]) === '' && valueText(row?.[`${field}_name`]) === '')) {
                return '';
            }
            return actorDisplay(row, field);
        }

    const referenceDisplayFieldMap = {
        source_type: 'source_type_name',
        import_type: 'import_type_name',
        };

    function referenceDisplayFieldForColumn(column = {}) {
            const keys = [
                column.system_field_name,
                column.original_column_key,
                column.key,
                column.data,
            ].map((value) => String(value || '').trim().toLowerCase());

            for (const key of keys) {
                if (referenceDisplayFieldMap[key]) {
                    return referenceDisplayFieldMap[key];
                }
                if (key.endsWith('_id') && key !== 'id') {
                    return `${key.slice(0, -3)}_name`;
                }
            }

            return '';
        }

    function referenceDisplayValue(row = {}, column = {}, fallback = '') {
            const displayField = referenceDisplayFieldForColumn(column);
            if (displayField === '') {
                return valueText(fallback);
            }

            const payload = mapped(row);
            return valueText(row?.[displayField]) || valueText(payload?.[displayField]) || valueText(fallback);
        }

    function buildEvidencePhysicalColumns(metaColumns = []) {
            return (Array.isArray(metaColumns) ? metaColumns : [])
                .filter((column) => String(column?.key || '').trim() !== '' && String(column?.key || '').trim() !== 'sort_no')
                .map((column) => {
                    const key = String(column.key || '').trim();
                    const title = resolveDisplayName(key, String(column.label || key).trim() || key, state.currentType);
                    const numeric = metaNumericColumn(column);
                    const dateLike = metaDateColumn(column);
                    const alignmentClass = numeric ? 'text-end' : 'text-start';
    
                    return {
                        key,
                        data: key,
                        name: key,
                        settingsKey: key,
                        settingsTitle: title,
                        sourceField: key,
                        title,
                        defaultContent: '',
                        className: `evidence-data-column text-nowrap ${alignmentClass}`,
                        headerClassName: `evidence-data-column text-nowrap ${alignmentClass}`,
                        settingsOrder: Number(column.ordinalPosition || 0) || 0,
                        ordinal_position: Number(column.ordinalPosition || 0) || 0,
                        render: (_value, type, row) => {
                            const rawValue = evidenceFieldValue(row, key);
                            const codeField = displayCodeFieldForColumn(column);
                            const referenceField = referenceDisplayFieldForColumn(column);
                            const actorField = actorFieldForColumn(column);
                            if (type === 'sort' || type === 'type') {
                                if (actorField !== '') {
                                    return actorColumnDisplayValue(row, actorField) || valueText(rawValue);
                                }
                                if (referenceField !== '') {
                                    return referenceDisplayValue(row, column, rawValue);
                                }
                                if (codeField !== '') {
                                    return codeColumnDisplayValue(row, codeField, rawValue);
                                }
                                if (isEvidenceStatusColumn(column)) {
                                    return valueText(evidenceStatusDisplay(rawValue).text);
                                }
                                if (numeric) {
                                    return parseCommonNumber(rawValue);
                                }
                                if (dateLike) {
                                    return normalizeDateInputValue(rawValue, metaDateTimeColumn(column)) || '';
                                }
                                return valueText(rawValue);
                            }
                            if (actorField !== '') {
                                return escapeHtml(actorColumnDisplayValue(row, actorField) || '-');
                            }
                            if (codeField !== '') {
                                return escapeHtml(codeColumnDisplayValue(row, codeField, rawValue) || '-');
                            }
                            if (referenceField !== '') {
                                return escapeHtml(referenceDisplayValue(row, column, rawValue) || '-');
                            }
                            if (isEvidenceStatusColumn(column)) {
                                const status = evidenceStatusDisplay(rawValue);
                                return badge(status.text, status.className);
                            }
                            if (numeric) {
                                return formatNumber(rawValue);
                            }
                            if (dateLike) {
                                return escapeHtml(valueText(rawValue) || '-');
                            }
                            return escapeHtml(valueText(rawValue) || '-');
                        },
                    };
                });
        }

    async function resolveEvidenceTableColumns(config) {
            const utilityColumns = commonColumns({ evidenceColumns: [] });
            const prefixColumns = utilityColumns.slice(0, 2);
            const suffixColumns = utilityColumns.slice(2);
            const metaColumns = await loadEvidenceMetaColumns(state.currentType);
            const visibleEvidenceColumns = buildEvidencePhysicalColumns(metaColumns);
    
            return [
                ...prefixColumns,
                ...visibleEvidenceColumns,
                ...suffixColumns,
            ];
        }

    function formatColumnsFromTemplate(format) {
            const columns = Array.isArray(format?.columns) ? format.columns : [];
            return columns
                .slice()
                .sort(compareFieldDisplayOrder)
                .map((column) => {
                    const field = String(column.system_field_name || '');
                    const isAmount = isAmountColumn(column);
                    const isDate = isDateColumn(column);
                    const fallbackTitle = String(column.excel_column_name || field || '컬럼');
                    const dataField = field !== '' ? field : fallbackTitle;
                    const title = resolveDisplayName(field || column.original_column_key || dataField, fallbackTitle, state.currentType);
                    const displayTitle = formatEvidenceColumnDisplayLabel(title, field);

                    return {
                        data: dataField,
                        title: displayTitle,
                        name: field || title,
                        settingsTitle: displayTitle,
                        sourceField: field,
                        excelColumnName: title,
                        visible: Number(column.is_visible ?? 1) === 1,
                        className: `evidence-data-column ${isAmount ? 'text-end' : 'text-start'} text-nowrap`,
                        headerClassName: 'evidence-data-column text-start text-nowrap',
                        render: (_value, type, row) => {
                            const value = formatValue(row, column);
                            const referenceColumn = {
                                ...column,
                                key: dataField,
                                data: dataField,
                            };
                            const referenceField = referenceDisplayFieldForColumn(referenceColumn);
                            const actorField = actorFieldForColumn(referenceColumn);
                            if (type === 'sort' || type === 'type') {
                                if (actorField !== '') return actorColumnDisplayValue(row, actorField) || valueText(value);
                                if (referenceField !== '') return referenceDisplayValue(row, referenceColumn, value);
                                if (isDate) return normalizeDateInputValue(value, isDateTimeColumn(column)) || '';
                                if (isAmount) return parseCommonNumber(value);
                                return valueText(value);
                            }
                            const codeField = displayCodeFieldForColumn(column);
                            if (actorField !== '') {
                                return escapeHtml(actorColumnDisplayValue(row, actorField) || '-');
                            }
                            if (codeField !== '') {
                                return escapeHtml(codeColumnDisplayValue(row, codeField, value) || '-');
                            }
                            if (referenceField !== '') {
                                return escapeHtml(referenceDisplayValue(row, referenceColumn, value) || '-');
                            }
                            if (isEvidenceStatusColumn(column)) {
                                const status = evidenceStatusDisplay(value);
                                return badge(status.text, status.className);
                            }
                            if (isDate) {
                                return escapeHtml(isDateTimeColumn(column) ? formatDateTimeValue(value) : formatDateValue(value));
                            }
                            return isAmount && value !== '-' ? escapeHtml(formatNumber(value)) : escapeHtml(value || '-');
                        },
                    };
                });
        }

    function normalizeSortText(value) {
            return valueText(value)
                .trim()
                .toLowerCase()
                .replace(/[\s_()/-]/g, '');
        }

    function defaultOrderForConfig() {
            return [[1, 'asc']];
        }

    function commonColumns(config) {
            const evidenceColumns = Array.isArray(config?.evidenceColumns) ? config.evidenceColumns : [];
            return [
                {
                    key: '__reorder',
                    data: null,
                    title: '<i class="bi bi-arrows-move"></i>',
                    orderable: false,
                    searchable: false,
                    className: 'reorder-handle no-sort no-colvis text-center',
                    headerClassName: 'no-colvis text-center',
                    width: '36px',
                    widthResizable: true,
                    render: (_value, type) => {
                        if (type !== 'display') return '';
                        return '<i class="bi bi-list"></i>';
                    },
                },
                {
                    key: 'sort_no',
                    data: 'sort_no',
                    title: '순번',
                    className: 'text-center text-nowrap',
                    headerClassName: 'text-center text-nowrap',
                    width: '56px',
                    render(value, type, row, meta) {
                        const display = value || (meta.row + meta.settings._iDisplayStart + 1);
                        if (type === 'sort' || type === 'type') {
                            return String(display)
                                .split('-')
                                .reduce((total, part, index) => total + (Number(part) || 0) / Math.pow(1000, index), 0);
                        }
                        if (type !== 'display') return escapeHtml(display);
                        return escapeHtml(display);
                    },
                },
                ...evidenceColumns,
                {
                    key: '__actions',
                    settingsKey: '__actions',
                    settingsTitle: '관리',
                    settingsVirtualType: 'system',
                    __dtColumnKind: 'virtual',
                    data: null,
                    title: '관리',
                    className: 'dt-action-column text-center no-colvis',
                    headerClassName: 'dt-action-column text-center no-colvis',
                    orderable: false,
                    searchable: false,
                    width: '64px',
                    widthResizable: true,
                    render: (_value, type, row) => {
                        if (type !== 'display') return '';
                        return `
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm dt-manage-edit-btn evidence-edit-row-btn"
                                    data-id="${escapeHtml(row?.id || '')}">
                                ${evidenceTypePolicy(state.currentType)?.readOnly ? '상세' : '수정'}
                            </button>
                        `;
                    },
                },
            ];
        }

    function genericEvidenceColumns() {
            return [
                textColumn('client_name', '거래처', (row) => clientName(row) || '-'),
                textColumn('approval_number', '증빙번호', (row) => row.approval_number || row.approval_no || row.declaration_number || row.order_number || '-'),
                moneyColumn('supply_amount', '공급가', (row) => amount(row.supply_amount)),
                moneyColumn('vat_amount', '부가세', (row) => amount(row.vat_amount)),
                moneyColumn('total_amount', '합계', (row) => amount(row.total_amount || row.amount)),
            ];
        }

    function rebuildTable() {
        const sequence = ++rebuildSequence;
        rebuildQueue = rebuildQueue
            .catch(() => {})
            .then(() => performTableRebuild(sequence));
        return rebuildQueue;
    }

    async function performTableRebuild(sequence) {
        if (sequence !== rebuildSequence) return;
        const config = currentConfig();
        const typeStorageKey = normalizeEvidenceType(state.currentType || defaultEvidenceTypeCode()) || defaultEvidenceTypeCode();
        const typeTableKey = `evidence-status-${String(typeStorageKey || 'tax-invoice').trim().toLowerCase()}`;

            if (state.refs.excelLabel) {
                state.refs.excelLabel.textContent = `${config.label} / 자료유형 기준`;
            }
            syncExcelManager(config);
    
            const selector = '#evidenceStatusTable';
            const $ = window.jQuery;
            state.selectedIds = new Set();
    
            if ($?.fn?.DataTable?.isDataTable(selector)) {
                $(selector).DataTable().destroy();
                $(selector).empty();
                document.querySelector(selector).innerHTML = '<thead><tr></tr></thead><tbody></tbody>';
            }
            document.querySelector(selector)?.classList.add('evidence-status-table', 'nowrap');
    
            const columns = await resolveEvidenceTableColumns(config);
            if (sequence !== rebuildSequence) return;
    
        state.table = await createDataTable({
                tableSelector: selector,
                api: config.api,
                ajaxData(request = {}) {
                    return {
                        ...(request && typeof request === 'object' ? request : {}),
                        column_display_name: JSON.stringify(currentColumnDisplayNameMap(state.currentType)),
                        column_requirement_policy: JSON.stringify(currentColumnRequirementPolicyMap(state.currentType)),
                    };
                },
                pageLength: 25,
                defaultOrder: defaultOrderForConfig(config),
                scrollX: false,
                autoWidth: false,
                paging: true,
                searching: true,
                info: true,
                serverSide: true,
                pageLoading: false,
                showColumnVisibility: false,
                redrawAfterInitialVisibility: false,
                showCopyButton: true,
                searchTableId: typeTableKey,
                selectable: true,
                rowIdField: 'id',
                deleteButton: !evidenceTypePolicy(state.currentType)?.readOnly,
                deleteApi: API.deleteRows,
                bulkDelete: true,
                deletePayload: () => ({
                    import_type: state.currentType,
                    data_type: state.currentType,
                }),
                columns,
                tableSettings: {
                    enabled: true,
                    pageKey: 'ledger.data.status',
                    tableKey: typeTableKey,
                    storageKey: evidenceStatusTableSettingsStorageKey(typeStorageKey),
                    userSettingPageKey: evidenceMetaDomain(typeStorageKey),
                    metaDomain: evidenceMetaDomain(typeStorageKey),
                    tableLabel: config.label,
                    title: '테이블 설정',
                    columns,
                    requiredColumns: [],
                    defaultColumnDisplayName: defaultColumnDisplayNameMap(),
                    defaultColumnRequirementPolicy: defaultColumnRequirementPolicyMap(),
                    resetOnColumnSchemaChange: true,
                    onOrderChange: handleEvidenceStatusTableOrderChange,
                },
                dataSrc(json) {
                    state.lastRows = Array.isArray(json.data)
                        ? json.data.map((row) => decorateRowDisplayFields(row))
                        : [];
                    updateSummary(state.lastRows);
                    return state.lastRows;
                },
                buttons: [
                    {
                        text: '휴지통',
                        className: evidenceTypePolicy(state.currentType)?.readOnly
                            ? 'd-none'
                            : 'btn btn-outline-danger btn-sm evidence-status-trash-btn',
                        action: openEvidenceTrash,
                    },
                    {
                        text: '엑셀관리',
                        className: evidenceTypePolicy(state.currentType)?.readOnly
                            || evidenceTypePolicy(state.currentType)?.excelManagerMode === 'none'
                            ? 'd-none'
                            : 'btn btn-outline-dark btn-sm',
                        action: () => showModal('dataExcelModal'),
                    },
                    {
                        text: '신규등록',
                        className: evidenceTypePolicy(state.currentType)?.readOnly ? 'd-none' : 'btn btn-outline-primary btn-sm evidence-new-btn',
                        action: () => { void openEvidenceNewModalLatest(); },
                    },
                ],
        });
        arrangeEvidenceToolbar(state.table);
            if (!evidenceTypePolicy(state.currentType)?.readOnly) bindRowReorder(state.table, {
                api: API.reorder,
                sortNoField: 'sort_no',
                includeAppliedRows: true,
                changedRowsOnly: false,
                sortableItems: '> tr',
                extraData: () => ({
                    scope: 'status',
                    import_type: state.currentType,
                    data_type: state.currentType,
                }),
                onSuccess(json) {
                    notify('success', json?.message || '증빙원본 순서가 변경되었습니다.');
                    state.table?.ajax.reload(null, false);
                },
                onError(json) {
                    notify('error', json?.message || '증빙원본 순서 변경에 실패했습니다.');
                    state.table?.ajax.reload(null, false);
                },
            });
    
            const dateOptions = await resolveEvidenceDateOptions(state.currentType);
            if (sequence !== rebuildSequence) return;
            SearchForm({
                table: state.table,
                apiList: config.api,
                tableId: 'evidenceStatus',
                defaultSearchField: 'client_name',
                dateOptions,
                excludeFields: ['', 'voucher_status', 'review_status', 'recommend_status', 'user_modified'],
            });
        }

    return {
        loadEvidenceMetaColumns,
        resolveEvidenceDateOptions,
        isEvidenceMetaNumericColumn: metaNumericColumn,
        isEvidenceMetaDateColumn: metaDateColumn,
        buildEvidencePhysicalColumns,
        resolveEvidenceTableColumns,
        formatColumnsFromTemplate,
        normalizeSortText,
        defaultOrderForConfig,
        commonColumns,
        genericEvidenceColumns,
        rebuildTable,
    };
}
