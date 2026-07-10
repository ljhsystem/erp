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
    ensureActiveFormat,
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
    updateTrashButtonState,
    openEvidenceNewModalLatest,
    openEvidenceTrash,
    renderEvidenceTypeTabs,
    showModal,
    syncExcelManager,
    renderSeedStatus,
    renderTransactionStatus,
    renderVoucherStatus,
    renderReviewStatus,
    renderRecommendStatus,
    renderUserModified,
    renderWorkflowStatus,
    renderTransactionWorkflowStatus,
    renderVoucherWorkflowStatus,
    readDataTableSettingsState,
}) {
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
            ['새증빙', 7],
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
        return stateValue.columnDisplayName && typeof stateValue.columnDisplayName === 'object'
            ? stateValue.columnDisplayName
            : {};
    }

    function currentColumnRequirementPolicyMap(type = state.currentType) {
        const stateValue = currentColumnPolicyState(type);
        return stateValue.columnRequirementPolicy && typeof stateValue.columnRequirementPolicy === 'object'
            ? stateValue.columnRequirementPolicy
            : {};
    }

    function resolveDisplayName(key, fallback, type = state.currentType) {
        const configured = currentColumnDisplayNameMap(type)[String(key || '').trim()];
        return String(configured || fallback || key || '').trim() || String(key || '').trim();
    }

    function defaultColumnDisplayNameMap() {
        const map = {};
        const activeColumns = Array.isArray(state.activeFormat?.columns) ? state.activeFormat.columns : [];
        activeColumns.forEach((column) => {
            const key = String(column.system_field_name || column.original_column_key || '').trim();
            if (!key) return;
            map[key] = String(column.excel_column_name || column.label || key).trim() || key;
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
    
            let data = [];
            try {
                const response = await fetch(`${API.dataTableColumns}?domain=${encodeURIComponent(domain)}`, {
                    credentials: 'include',
                });
                const json = await response.json().catch(() => ({}));
                data = response.ok && json?.success && Array.isArray(json.data) ? json.data : [];
            } catch (_error) {
                data = [];
            }
    
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
            const directionLabel = directionDisplayText(directionSource);
            if (directionLabel !== '') {
                row.__transaction_direction_label = directionLabel;
            }

            const businessUnitSource = valueText(
                row.business_unit
                ?? mappedPayload.business_unit
                ?? ''
            );
            const businessUnitLabel = codeFieldDisplayText('business_unit', businessUnitSource);
            if (businessUnitLabel !== '') {
                row.__business_unit_label = businessUnitLabel;
            }

            const operationTypeSource = valueText(
                row.operation_type
                ?? mappedPayload.operation_type
                ?? ''
            );
            const operationTypeLabel = codeFieldDisplayText('operation_type', operationTypeSource);
            if (operationTypeLabel !== '') {
                row.__operation_type_label = operationTypeLabel;
            }

            return row;
        }

    function evidenceStatusDisplay(value) {
            const raw = valueText(value).trim().toUpperCase();
            if (raw === '') return { text: '-', className: 'text-bg-light' };
            if (['COMPLETED', 'READY', 'VERIFY_ONLY'].includes(raw)) {
                return { text: '완료', className: 'text-bg-success' };
            }
            if (['CORRECTION_REQUIRED', 'NOT_READY', 'REVIEW_REQUIRED'].includes(raw)) {
                return { text: '보정필요', className: 'text-bg-warning' };
            }

            if (raw === 'ACTIVE') return { text: '활성', className: 'text-bg-success' };
            if (raw === 'DELETED') return { text: '삭제', className: 'text-bg-secondary' };
            if (raw === 'ERROR') return { text: '오류', className: 'text-bg-danger' };

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

            return '';
        }

    const referenceDisplayFieldMap = {
            client_id: 'client_name',
            project_id: 'project_name',
            employee_id: 'employee_name',
            bank_account_id: 'bank_account_name',
            card_id: 'card_name',
            team_id: 'team_name',
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
            }

            return '';
        }

    function referenceDisplayValue(row = {}, column = {}, fallback = '') {
            const displayField = referenceDisplayFieldForColumn(column);
            if (displayField === '') {
                return valueText(fallback);
            }

            return valueText(row?.[displayField]) || valueText(fallback);
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
                            if (type === 'sort' || type === 'type') {
                                if (referenceField !== '') {
                                    return referenceDisplayValue(row, column, rawValue);
                                }
                                if (codeField === 'transaction_direction') {
                                    return valueText(row.__transaction_direction_label) || directionDisplayText(rawValue) || valueText(rawValue);
                                }
                                if (codeField === 'business_unit') {
                                    return valueText(row.__business_unit_label) || codeFieldDisplayText('business_unit', rawValue) || valueText(rawValue);
                                }
                                if (codeField === 'operation_type') {
                                    return valueText(row.__operation_type_label) || codeFieldDisplayText('operation_type', rawValue) || valueText(rawValue);
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
                            if (codeField === 'transaction_direction') {
                                return escapeHtml(valueText(row.__transaction_direction_label) || directionDisplayText(rawValue) || '-');
                            }
                            if (codeField === 'business_unit') {
                                return escapeHtml(valueText(row.__business_unit_label) || codeFieldDisplayText('business_unit', rawValue) || '-');
                            }
                            if (codeField === 'operation_type') {
                                return escapeHtml(valueText(row.__operation_type_label) || codeFieldDisplayText('operation_type', rawValue) || '-');
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
                            if (type === 'sort' || type === 'type') {
                                if (referenceField !== '') return referenceDisplayValue(row, referenceColumn, value);
                                if (isDate) return normalizeDateInputValue(value, isDateTimeColumn(column)) || '';
                                if (isAmount) return parseCommonNumber(value);
                                return valueText(value);
                            }
                            const codeField = displayCodeFieldForColumn(column);
                            if (codeField === 'transaction_direction') {
                                return escapeHtml(valueText(row.__transaction_direction_label) || directionDisplayText(value) || '-');
                            }
                            if (codeField === 'business_unit') {
                                return escapeHtml(valueText(row.__business_unit_label) || codeFieldDisplayText('business_unit', value) || '-');
                            }
                            if (codeField === 'operation_type') {
                                return escapeHtml(valueText(row.__operation_type_label) || codeFieldDisplayText('operation_type', value) || '-');
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
                    render: (_value, type, row) => {
                        if (type !== 'display') return '';
                        if (row?.processing_is_child) {
                            return '<i class="bi bi-arrow-return-right processing-child-branch" title="자식행"></i>';
                        }
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
                        const display = value || row?.processing_display_path || (meta.row + meta.settings._iDisplayStart + 1);
                        if (type === 'sort' || type === 'type') {
                            return String(display)
                                .split('-')
                                .reduce((total, part, index) => total + (Number(part) || 0) / Math.pow(1000, index), 0);
                        }
                        if (type !== 'display') return escapeHtml(display);
                        const depth = Math.max(1, Number(row?.processing_level || 1));
                        return `<span class="processing-row-no depth-${depth}">${escapeHtml(display)}</span>`;
                    },
                },
                ...evidenceColumns,
                {
                    key: '__manage',
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
                                수정
                            </button>
                        `;
                    },
                },
                {
                    key: '__add',
                    data: null,
                    title: '추가',
                    className: 'dt-action-column text-center no-colvis',
                    headerClassName: 'dt-action-column text-center no-colvis',
                    orderable: false,
                    searchable: false,
                    width: '52px',
                    widthResizable: true,
                    render: (_value, type, row) => {
                        if (type !== 'display') return '';
                        if (row?.processing_is_child) {
                            return `
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm evidence-delete-child-row-btn"
                                        data-processing-item-id="${escapeHtml(row?.processing_item_id || '')}"
                                        title="자식행 삭제"
                                        aria-label="자식행 삭제">
                                    -
                                </button>
                            `;
                        }
                        return `
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm evidence-add-child-row-btn"
                                    data-id="${escapeHtml(row?.id || '')}"
                                    title="자식행 추가"
                                    aria-label="자식행 추가">
                                +
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

    function applyProcessingRowState() {
            if (!state.table) return;
            const body = state.table.table?.().body?.();
            if (!body) return;
            const renderContext = processingChildRenderContext();
            const renderedParentKeys = new Set();
            Array.from(body.rows || []).forEach((tr) => {
                if (tr.classList.contains('processing-child-display-row')) {
                    return;
                }
                const row = state.table.row(tr).data();
                tr.classList.toggle('processing-child-row', Boolean(row?.processing_is_child));
                tr.classList.toggle('processing-parent-row', Boolean(row?.processing_has_children));
                syncProcessingChildRows(tr, row, renderContext, renderedParentKeys);
            });
            cleanupStaleProcessingChildRows(body, renderedParentKeys);
        }

    function processingChildRenderContext() {
            const settings = state.table?.settings?.()[0];
            const columns = settings?.aoColumns || [];
            const visibleIndexes = columns
                .map((column, index) => (column?.bVisible === false ? null : index))
                .filter((index) => index !== null);

            return {
                columns,
                visibleIndexes,
                columnSignature: visibleIndexes.join(','),
            };
        }

    function processingParentRowKey(row = {}) {
            return String(row?.processing_item_id || row?.id || '').trim();
        }

    function processingChildRowKey(child = {}, index = 0) {
            return String(child?.processing_item_id || child?.id || child?.processing_display_path || child?.sort_no || `child-${index}`).trim();
        }

    function processingChildRowSignature(child = {}, renderContext = null) {
            return JSON.stringify({
                columnSignature: renderContext?.columnSignature || '',
                child,
            });
        }

    function collectProcessingChildRows(parentTr, parentKey) {
            const rows = [];
            let cursor = parentTr?.nextElementSibling || null;
            while (cursor && cursor.classList.contains('processing-child-display-row')) {
                if (String(cursor.dataset.processingParentId || '').trim() !== parentKey) {
                    break;
                }
                rows.push(cursor);
                cursor = cursor.nextElementSibling;
            }
            return rows;
        }

    function createProcessingChildRow(parentKey, childKey) {
            const tr = document.createElement('tr');
            tr.className = 'processing-child-row processing-child-display-row';
            tr.dataset.processingParentId = parentKey;
            tr.dataset.processingChildKey = childKey;
            return tr;
        }

    function syncProcessingChildRows(parentTr, row = {}, renderContext = null, renderedParentKeys = null) {
            const parentKey = processingParentRowKey(row);
            const desiredChildren = row?.processing_has_children && Array.isArray(row?.processing_children)
                ? row.processing_children
                : [];

            if (parentKey && renderedParentKeys) {
                renderedParentKeys.add(parentKey);
            }

            const existingRows = parentKey ? collectProcessingChildRows(parentTr, parentKey) : [];
            if (!parentKey || desiredChildren.length === 0) {
                existingRows.forEach((childRow) => childRow.remove());
                return;
            }

            const existingByKey = new Map();
            existingRows.forEach((childRow, index) => {
                existingByKey.set(String(childRow.dataset.processingChildKey || `existing-${index}`).trim(), childRow);
            });

            const usedRows = new Set();
            let anchor = parentTr;
            desiredChildren.forEach((child, index) => {
                const childKey = processingChildRowKey(child, index);
                let childRow = existingByKey.get(childKey);
                if (!childRow) {
                    childRow = createProcessingChildRow(parentKey, childKey);
                }

                if (anchor.nextElementSibling !== childRow) {
                    anchor.insertAdjacentElement('afterend', childRow);
                }

                syncProcessingChildRowCells(childRow, child, renderContext, childKey, parentKey);
                usedRows.add(childRow);
                anchor = childRow;
            });

            existingRows.forEach((childRow) => {
                if (!usedRows.has(childRow)) {
                    childRow.remove();
                }
            });
        }

    function syncProcessingChildRowCells(childRow, child = {}, renderContext = null, childKey = '', parentKey = '') {
            const columns = renderContext?.columns || [];
            const visibleIndexes = renderContext?.visibleIndexes || [];
            const nextSignature = processingChildRowSignature(child, renderContext);

            childRow.dataset.processingParentId = parentKey;
            childRow.dataset.processingChildKey = childKey;
            childRow.__processingRowData = child;

            if (childRow.dataset.processingRenderSignature === nextSignature && childRow.children.length === visibleIndexes.length) {
                return;
            }

            while (childRow.children.length > visibleIndexes.length) {
                childRow.removeChild(childRow.lastElementChild);
            }

            visibleIndexes.forEach((columnIndex, visibleIndex) => {
                const column = columns[columnIndex] || {};
                let td = childRow.children[visibleIndex];
                if (!td) {
                    td = document.createElement('td');
                    childRow.appendChild(td);
                }
                const className = String(column.sClass || '').trim();
                td.className = className;
                td.innerHTML = renderProcessingChildCell(column, child, columnIndex);
            });

            childRow.dataset.processingRenderSignature = nextSignature;
        }

    function cleanupStaleProcessingChildRows(body, renderedParentKeys = null) {
            Array.from(body.rows || []).forEach((tr) => {
                if (!tr.classList.contains('processing-child-display-row')) {
                    return;
                }
                const parentKey = String(tr.dataset.processingParentId || '').trim();
                if (!parentKey || !renderedParentKeys?.has(parentKey)) {
                    tr.remove();
                }
            });
        }

    function dataTableColumnValue(row = {}, dataSrc = null) {
            if (typeof dataSrc === 'function') return dataSrc(row, 'display', row);
            if (dataSrc === null || dataSrc === undefined) return row;
            if (typeof dataSrc !== 'string' || dataSrc === '') return row[dataSrc] ?? '';
            return dataSrc.split('.').reduce((value, key) => (
                value && typeof value === 'object' ? value[key] : undefined
            ), row);
        }

    function renderProcessingChildCell(column = {}, row = {}, columnIndex = 0) {
            const data = dataTableColumnValue(row, column.mData);
            const meta = {
                row: 0,
                col: columnIndex,
                settings: state.table?.settings?.()[0] || {},
            };
            if (typeof column.mRender === 'function') {
                return column.mRender(data, 'display', row, meta);
            }
            if (typeof column.mRender === 'object' && typeof column.mRender?.display === 'function') {
                return column.mRender.display(data, 'display', row, meta);
            }
            return escapeHtml(data ?? '');
        }

    function insertProcessingChildRows(parentTr, children = []) {
            const parentKey = processingParentRowKey(state.table?.row(parentTr).data() || {});
            if (!parentTr || !parentKey) return;
            syncProcessingChildRows(parentTr, {
                processing_item_id: parentKey,
                processing_has_children: Array.isArray(children) && children.length > 0,
                processing_children: Array.isArray(children) ? children : [],
            }, processingChildRenderContext());
        }

    function rowDataFromTableNode(rowNode) {
            if (!rowNode || !state.table) return null;
            if (rowNode.__processingRowData) {
                return rowNode.__processingRowData;
            }
            return state.table.row(rowNode).data() || null;
        }

    async function rebuildTable() {
        const config = currentConfig();
        const typeStorageKey = normalizeEvidenceType(state.currentType || defaultEvidenceTypeCode()) || defaultEvidenceTypeCode();
        const typeTableKey = `evidence-status-${String(typeStorageKey || 'tax-invoice').trim().toLowerCase()}`;
     
            state.activeFormat = await ensureActiveFormat(state.currentType);
    
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
    
        state.table = createDataTable({
                tableSelector: selector,
                api: config.api,
                ajaxData(request = {}) {
                    return {
                        ...(request && typeof request === 'object' ? request : {}),
                        column_display_name: JSON.stringify(currentColumnDisplayNameMap(state.currentType)),
                        column_requirement_policy: JSON.stringify(currentColumnRequirementPolicyMap(state.currentType)),
                    };
                },
                pageLength: 100,
                defaultOrder: defaultOrderForConfig(config),
                scrollX: false,
                autoWidth: false,
                paging: true,
                searching: true,
                info: true,
                showColumnVisibility: false,
                showCopyButton: true,
                searchTableId: typeTableKey,
                selectable: true,
                rowIdField: (row) => (row?.processing_is_child ? '' : row?.id),
                deleteButton: true,
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
                    defaultVisibleColumns: [],
                    defaultColumnDisplayName: defaultColumnDisplayNameMap(),
                    defaultColumnRequirementPolicy: defaultColumnRequirementPolicyMap(),
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
                        className: 'btn btn-outline-danger btn-sm evidence-status-trash-btn',
                        action: openEvidenceTrash,
                    },
                    {
                        text: '엑셀관리',
                        className: 'btn btn-outline-dark btn-sm',
                        action: () => showModal('dataExcelModal'),
                    },
                    {
                        text: '새 증빙',
                        className: 'btn btn-outline-primary btn-sm evidence-new-btn',
                        action: () => { void openEvidenceNewModalLatest(); },
                    },
                ],
        });
        arrangeEvidenceToolbar(state.table);
            void updateTrashButtonState();
            state.table.on('draw.dt', () => {
                applyProcessingRowState();
            });
            state.table.on('column-visibility.dt responsive-resize.dt', () => {
                window.setTimeout(applyProcessingRowState, 0);
            });
            applyProcessingRowState();
    
            bindRowReorder(state.table, {
                api: API.reorder,
                sortNoField: 'sort_no',
                includeAppliedRows: true,
                changedRowsOnly: false,
                sortableItems: '> tr:not(.processing-child-row)',
                isReorderableRow: (row) => !row?.processing_is_child,
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
        applyProcessingRowState,
        dataTableColumnValue,
        renderProcessingChildCell,
        insertProcessingChildRows,
        rowDataFromTableNode,
        rebuildTable,
    };
}
