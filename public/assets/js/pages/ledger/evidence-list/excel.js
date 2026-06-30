export function createEvidenceExcelModule({
    state,
    API,
    createExcelManagerSettingsCore,
    evidenceFieldOptionsCache,
    fieldOptionToModalColumn,
    sortEvidenceExcelColumns,
    normalizeEvidenceType,
    evidenceTypePolicy,
    defaultEvidenceTypeCode,
    formatEvidenceColumnDisplayLabel,
    escapeHtml,
    notify,
    currentConfig,
    evidenceStatusTableSettingsStorageKey,
    evidenceMetaDomain,
}) {
    const EVIDENCE_EXCEL_STORAGE_VERSION = 5;
    const EVIDENCE_EXCEL_REQUIREMENT = Object.freeze({
        HIDDEN: 'hidden',
        OPTIONAL: 'optional',
        REQUIRED: 'required',
    });

    function resolveEvidenceType(type = state.currentType) {
        const fallbackType = defaultEvidenceTypeCode();
        return normalizeEvidenceType(type || fallbackType) || fallbackType;
    }

    function currentEvidenceTypePolicy(type = state.currentType) {
        return evidenceTypePolicy(resolveEvidenceType(type)) || {};
    }

    function evidenceExcelManagerColumns(type) {
        const normalizedType = normalizeEvidenceType(type);
        const activeType = normalizeEvidenceType(state.activeFormat?.import_type || state.activeFormat?.format_name || '');
        if (activeType === normalizedType && Array.isArray(state.activeFormat?.columns) && state.activeFormat.columns.length > 0) {
            return sortEvidenceExcelColumns(state.activeFormat.columns).map((column) => ({
                key: sourceColumnKeyForType(normalizedType, column.original_column_key || column.system_field_name || column.key),
                label: String(column.excel_column_name || column.label || column.system_field_name || '').trim(),
                defaultSelected: Number(column.is_visible ?? 1) !== 0,
                required: Number(column.is_required || 0) === 1,
            })).filter((column) => column.key !== '' && column.label !== '');
        }

        const cachedFieldOptions = evidenceFieldOptionsCache.get(normalizedType) || [];
        if (cachedFieldOptions.length > 0) {
            return sortEvidenceExcelColumns(cachedFieldOptions)
                .map((field, index) => fieldOptionToModalColumn(field, index))
                .filter(Boolean)
                .map((column) => ({
                    key: sourceColumnKeyForType(
                        normalizedType,
                        column.original_column_key || column.system_field_name || column.key
                    ),
                    label: String(column.excel_column_name || column.label || column.system_field_name || '').trim(),
                    defaultSelected: Number(column.is_visible ?? 1) !== 0,
                    required: Number(column.is_required || 0) === 1,
                }))
                .filter((column) => column.key !== '' && column.label !== '');
        }

        return [];
    }

    function evidenceExcelManagerDomain(type = state.currentType) {
        const normalizedType = resolveEvidenceType(type);
        return `excel.manager.evidence.${normalizedType}`;
    }

    function evidenceExcelManagerStorageKey(kind, type = state.currentType) {
        return `excel.${kind}.${evidenceExcelManagerDomain(type)}.v1`;
    }

    function usesEvidenceExcelManagerCore(type = state.currentType) {
        return currentEvidenceTypePolicy(type).excelManagerMode === 'core';
    }

    function evidenceExcelManagerCoreDomain(type = state.currentType) {
        const policy = currentEvidenceTypePolicy(type);
        if (String(policy.excelManagerDomain || '').trim() !== '') {
            return String(policy.excelManagerDomain).trim();
        }
        const normalizedType = resolveEvidenceType(type);
        return `evidence-${String(normalizedType).trim().toLowerCase().replace(/_/g, '-')}`;
    }

    function sourceColumnKeyForType(type, key) {
        const normalizedKey = String(key || '').trim();
        const aliases = currentEvidenceTypePolicy(type).sourceKeyAliases || {};
        return aliases[normalizedKey] || normalizedKey;
    }

    function evidenceExcelIsTemplate(kind) {
        return String(kind || '').trim().toLowerCase() === 'template';
    }

    function normalizeEvidenceExcelRequirement(value, fallback = EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL) {
        const normalized = String(value || '').trim().toLowerCase();
        if (normalized === EVIDENCE_EXCEL_REQUIREMENT.HIDDEN) return EVIDENCE_EXCEL_REQUIREMENT.HIDDEN;
        if (normalized === EVIDENCE_EXCEL_REQUIREMENT.REQUIRED) return EVIDENCE_EXCEL_REQUIREMENT.REQUIRED;
        if (normalized === EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL) return EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL;
        return fallback;
    }

    function normalizeEditableEvidenceExcelRequirement(value) {
        return normalizeEvidenceExcelRequirement(value, EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL);
    }

    function evidenceExcelRequirementVisible(requirement) {
        return normalizeEvidenceExcelRequirement(requirement) !== EVIDENCE_EXCEL_REQUIREMENT.HIDDEN;
    }

    function isEvidenceExcelSystemRequiredColumn(column) {
        return column?.systemRequired === true || column?.required === true;
    }

    function defaultEvidenceExcelRequirement(column, kind) {
        if (evidenceExcelIsTemplate(kind) && isEvidenceExcelSystemRequiredColumn(column)) {
            return EVIDENCE_EXCEL_REQUIREMENT.REQUIRED;
        }

        return column.defaultSelected === true
            ? EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL
            : EVIDENCE_EXCEL_REQUIREMENT.HIDDEN;
    }

    function serializeEvidenceExcelManagerState(kind, excelState) {
        const payload = {
            version: EVIDENCE_EXCEL_STORAGE_VERSION,
            domain: excelState.domain,
            type: excelState.type,
            updatedAt: String(excelState.updatedAt || ''),
            visibleColumns: Array.isArray(excelState.visibleColumns) ? [...excelState.visibleColumns] : [],
            columnOrder: Array.isArray(excelState.columnOrder) ? [...excelState.columnOrder] : [],
        };

        if (evidenceExcelIsTemplate(kind)) {
            payload.columnRequirement = { ...(excelState.columnRequirement || {}) };
        }

        return payload;
    }

    function defaultEvidenceExcelManagerState(type, kind) {
        const columns = evidenceExcelManagerColumns(type);
        const columnOrder = columns.map((column) => String(column.key || '').trim()).filter(Boolean);
        const visibleColumns = [];
        const columnRequirement = {};
        const states = columns.map((column, index) => {
            const key = String(column.key || '').trim();
            const requirement = defaultEvidenceExcelRequirement(column, kind);
            const visible = evidenceExcelRequirementVisible(requirement);
            if (visible) {
                visibleColumns.push(key);
            }
            if (evidenceExcelIsTemplate(kind) && key !== '') {
                columnRequirement[key] = requirement;
            }

            return {
                key,
                label: String(column.label || column.key || '').trim(),
                systemRequired: isEvidenceExcelSystemRequiredColumn(column),
                requirement,
                visible,
                order: index + 1,
            };
        });

        const payload = {
            version: EVIDENCE_EXCEL_STORAGE_VERSION,
            domain: evidenceExcelManagerDomain(type),
            type: kind,
            updatedAt: '',
            visibleColumns,
            columnOrder,
            columns: states,
        };

        if (evidenceExcelIsTemplate(kind)) {
            payload.columnRequirement = columnRequirement;
        }

        return payload;
    }

    function normalizedEvidenceExcelBaseColumns(type, excelState) {
        const defaultColumns = evidenceExcelManagerColumns(type).map((column) => ({
            key: String(column.key || '').trim(),
            label: String(column.label || column.key || '').trim(),
            required: isEvidenceExcelSystemRequiredColumn(column),
            defaultSelected: column.defaultSelected === true,
        })).filter((column) => column.key !== '');
        const normalizedColumns = [];
        const seen = new Set();

        defaultColumns.forEach((column) => {
            if (seen.has(column.key)) {
                return;
            }
            seen.add(column.key);
            normalizedColumns.push(column);
        });

        const runtimeColumns = Array.isArray(excelState?.columns) ? excelState.columns : [];
        runtimeColumns.forEach((column) => {
            const key = sourceColumnKeyForType(type, column?.key);
            if (!key || seen.has(key)) {
                return;
            }
            seen.add(key);
            normalizedColumns.push({
                key,
                label: String(column?.label || column?.key || '').trim() || key,
                required: isEvidenceExcelSystemRequiredColumn(column),
                defaultSelected: column?.visible !== false,
            });
        });

        return normalizedColumns;
    }

    function normalizeEvidenceExcelManagerState(type, kind, excelState) {
        const defaults = defaultEvidenceExcelManagerState(type, kind);
        const defaultColumns = normalizedEvidenceExcelBaseColumns(type, excelState);
        const savedColumns = Array.isArray(excelState?.columns) ? excelState.columns : [];
        const savedMap = new Map(
            savedColumns
                .map((column) => [sourceColumnKeyForType(type, column?.key), column])
                .filter(([key]) => key !== '')
        );
        const savedRequirementMap = new Map(
            Object.entries(excelState?.columnRequirement && typeof excelState.columnRequirement === 'object' ? excelState.columnRequirement : {})
                .map(([key, value]) => [sourceColumnKeyForType(type, key), value])
                .filter(([key]) => key !== '')
        );
        const legacyVisibleColumns = savedColumns
            .filter((column) => column?.visible !== false)
            .map((column) => sourceColumnKeyForType(type, column?.key))
            .filter(Boolean);
        const legacyColumnOrder = [...savedColumns]
            .sort((left, right) => Number(left?.order || 0) - Number(right?.order || 0))
            .map((column) => sourceColumnKeyForType(type, column?.key))
            .filter(Boolean);
        const visibleColumns = Array.isArray(excelState?.visibleColumns)
            ? excelState.visibleColumns.map((key) => sourceColumnKeyForType(type, key)).filter(Boolean)
            : (legacyVisibleColumns.length > 0 ? legacyVisibleColumns : defaults.visibleColumns);
        const visibleSet = new Set(visibleColumns);
        const legacyStateDetected = savedColumns.length > 0
            || Array.isArray(excelState?.visibleColumns)
            || Array.isArray(excelState?.columnOrder);
        const orderedKeys = (() => {
            const sourceOrder = Array.isArray(excelState?.columnOrder) && excelState.columnOrder.length > 0
                ? excelState.columnOrder
                : legacyColumnOrder;
            const keys = [];
            const seen = new Set();

            sourceOrder.forEach((key) => {
                const normalizedKey = sourceColumnKeyForType(type, key);
                if (!normalizedKey || seen.has(normalizedKey) || !defaultColumns.some((column) => column.key === normalizedKey)) {
                    return;
                }
                seen.add(normalizedKey);
                keys.push(normalizedKey);
            });

            defaults.columnOrder.forEach((key) => {
                if (seen.has(key)) return;
                seen.add(key);
                keys.push(key);
            });

            return keys;
        })();
        const columnRequirement = {};

        defaultColumns.forEach((column) => {
            const saved = savedMap.get(column.key);

            if (!evidenceExcelIsTemplate(kind)) {
                columnRequirement[column.key] = visibleSet.has(column.key)
                    ? EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL
                    : EVIDENCE_EXCEL_REQUIREMENT.HIDDEN;
                return;
            }

            if (savedRequirementMap.has(column.key)) {
                columnRequirement[column.key] = normalizeEditableEvidenceExcelRequirement(savedRequirementMap.get(column.key));
                return;
            }

            if (saved && typeof saved.requirement === 'string') {
                columnRequirement[column.key] = normalizeEditableEvidenceExcelRequirement(saved.requirement);
                return;
            }

            if (legacyStateDetected) {
                columnRequirement[column.key] = visibleSet.has(column.key)
                    ? EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL
                    : EVIDENCE_EXCEL_REQUIREMENT.HIDDEN;
                return;
            }

            columnRequirement[column.key] = normalizeEditableEvidenceExcelRequirement(defaultEvidenceExcelRequirement(column, kind));
        });

        const orderMap = new Map(orderedKeys.map((key, index) => [key, index]));
        const columns = defaultColumns
            .map((column, index) => {
                const requirement = evidenceExcelIsTemplate(kind)
                    ? (
                        column.required === true
                            ? EVIDENCE_EXCEL_REQUIREMENT.REQUIRED
                            : normalizeEditableEvidenceExcelRequirement(columnRequirement[column.key])
                    )
                    : (visibleSet.has(column.key) ? EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL : EVIDENCE_EXCEL_REQUIREMENT.HIDDEN);

                return {
                    key: column.key,
                    label: column.label,
                    systemRequired: column.required === true,
                    requirement,
                    visible: column.required === true ? true : evidenceExcelRequirementVisible(requirement),
                    order: orderMap.has(column.key) ? Number(orderMap.get(column.key)) + 1 : index + 1,
                };
            })
            .sort((left, right) => Number(left.order || 0) - Number(right.order || 0))
            .map((column, index) => ({
                ...column,
                order: index + 1,
            }));

        const normalized = {
            version: EVIDENCE_EXCEL_STORAGE_VERSION,
            domain: defaults.domain,
            type: kind,
            updatedAt: String(excelState?.updatedAt || ''),
            visibleColumns: columns.filter((column) => column.visible).map((column) => column.key),
            columnOrder: columns.map((column) => column.key),
            columns,
        };

        if (evidenceExcelIsTemplate(kind)) {
            normalized.columnRequirement = columns.reduce((accumulator, column) => {
                accumulator[column.key] = isEvidenceExcelSystemRequiredColumn(column)
                    ? EVIDENCE_EXCEL_REQUIREMENT.REQUIRED
                    : normalizeEditableEvidenceExcelRequirement(column.requirement);
                return accumulator;
            }, {});
        }

        return normalized;
    }

    function loadEvidenceExcelManagerState(type, kind) {
        const storageKey = evidenceExcelManagerStorageKey(kind, type);
        try {
            const raw = window.localStorage?.getItem(storageKey) || '';
            if (!raw) {
                return defaultEvidenceExcelManagerState(type, kind);
            }

            const parsed = JSON.parse(raw);
            const normalized = normalizeEvidenceExcelManagerState(type, kind, parsed);
            const needsMigration = parsed
                && typeof parsed === 'object'
                && (
                    Number(parsed.version || 0) < EVIDENCE_EXCEL_STORAGE_VERSION
                    || !Array.isArray(parsed.visibleColumns)
                    || !Array.isArray(parsed.columnOrder)
                    || (evidenceExcelIsTemplate(kind) && (!parsed.columnRequirement || typeof parsed.columnRequirement !== 'object'))
                    || (!evidenceExcelIsTemplate(kind) && (Object.prototype.hasOwnProperty.call(parsed, 'columnRequirement') || Array.isArray(parsed.columns)))
                    || (Array.isArray(parsed.columns) && parsed.columns.some((column) => typeof column?.requirement !== 'string'))
                );

            if (needsMigration) {
                window.localStorage?.setItem(storageKey, JSON.stringify({
                    ...serializeEvidenceExcelManagerState(kind, normalized),
                    updatedAt: normalized.updatedAt || new Date().toISOString(),
                }));
            }

            return normalized;
        } catch {
            return defaultEvidenceExcelManagerState(type, kind);
        }
    }

    function saveEvidenceExcelManagerState(type, kind, excelState) {
        const syncedState = syncEvidenceExcelStateSnapshot(kind, excelState);
        const normalized = normalizeEvidenceExcelManagerState(type, kind, syncedState);
        const payload = {
            ...serializeEvidenceExcelManagerState(kind, normalized),
            updatedAt: new Date().toISOString(),
        };

        try {
            window.localStorage?.setItem(evidenceExcelManagerStorageKey(kind, type), JSON.stringify(payload));
        } catch {
            // localStorage unavailable; keep runtime state only
        }

        return normalizeEvidenceExcelManagerState(type, kind, payload);
    }

    function selectedEvidenceExcelColumns(excelState) {
        return (Array.isArray(excelState?.columns) ? excelState.columns : [])
            .filter((column) => column.visible !== false)
            .sort((a, b) => Number(a.order || 0) - Number(b.order || 0))
            .map((column) => column.key);
    }

    function selectedEvidenceExcelRequirement(excelState) {
        return (Array.isArray(excelState?.columns) ? excelState.columns : [])
            .sort((a, b) => Number(a.order || 0) - Number(b.order || 0))
            .reduce((accumulator, column) => {
                accumulator[column.key] = isEvidenceExcelSystemRequiredColumn(column)
                    ? EVIDENCE_EXCEL_REQUIREMENT.REQUIRED
                    : normalizeEditableEvidenceExcelRequirement(column.requirement);
                return accumulator;
            }, {});
    }

    function ensureEvidenceExcelManagerState(type = state.currentType) {
        const normalizedType = resolveEvidenceType(type);
        if (
            state.excelManagerSettingsType !== normalizedType
            || !state.excelManagerSettingsState.template
            || !state.excelManagerSettingsState.download
        ) {
            state.excelManagerSettingsType = normalizedType;
            state.excelManagerSettingsState = {
                template: loadEvidenceExcelManagerState(normalizedType, 'template'),
                download: loadEvidenceExcelManagerState(normalizedType, 'download'),
            };
        }

        return state.excelManagerSettingsState;
    }

    function syncEvidenceExcelPreparedColumns(type = state.currentType, statesOverride = null) {
        if (!state.refs.excelForm) return;

        const normalizedType = resolveEvidenceType(type);
        const states = statesOverride || ensureEvidenceExcelManagerState(normalizedType);
        const templateColumns = selectedEvidenceExcelColumns(states.template);
        const downloadColumns = selectedEvidenceExcelColumns(states.download);

        state.refs.excelForm.dataset.excelTemplateColumns = JSON.stringify(templateColumns);
        state.refs.excelForm.dataset.excelDownloadColumns = JSON.stringify(downloadColumns);
        delete state.refs.excelForm.dataset.excelTemplateColumnRequirement;
        delete state.refs.excelForm.dataset.excelDownloadColumnRequirement;
        state.refs.excelForm.dataset.evidenceExcelDomain = evidenceExcelManagerDomain(normalizedType);
        state.refs.excelForm.__excelPreparedColumns = {
            template: templateColumns,
            download: downloadColumns,
        };
        delete state.refs.excelForm.__excelPreparedRequirement;
    }

    function renderEvidenceExcelManagerPanel(kind, excelState) {
        const panel = state.refs.excelForm?.querySelector(`[data-excel-settings-panel="${kind}"]`);
        if (!panel) return;

        const columns = Array.isArray(excelState?.columns) ? excelState.columns : [];
        const selectedCount = columns.filter((column) => column.visible !== false).length;
        const description = kind === 'template'
            ? '업로드용 양식에 포함할 컬럼과 필수구분을 설정하세요.'
            : '데이터 다운로드에 포함할 컬럼, 순서, 필수구분을 설정하세요.';

        panel.innerHTML = `
            <div class="excel-settings-panel-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div class="text-muted small">${escapeHtml(description)}</div>
                    <div class="text-muted small">선택 ${selectedCount}개 / 전체 ${columns.length}개</div>
                </div>
                <div class="excel-settings-grid" role="table" aria-label="${kind === 'template' ? '양식 설정' : '다운로드 설정'}">
                    <div class="excel-settings-grid-header" role="row">
                        <div class="excel-settings-grid-head excel-settings-grid-cell-handle">드래그</div>
                        <div class="excel-settings-grid-head excel-settings-grid-cell-usage">사용</div>
                        <div class="excel-settings-grid-head excel-settings-grid-cell-order">순번</div>
                        <div class="excel-settings-grid-head excel-settings-grid-cell-label">컬럼명</div>
                        <div class="excel-settings-grid-head excel-settings-grid-cell-requirement">필수구분</div>
                    </div>
                    <div class="excel-settings-grid-body">
                        ${columns.map((column, index) => {
                            const checked = column.visible !== false ? 'checked' : '';
                            const requirement = normalizeEvidenceExcelRequirement(column.requirement, EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL);
                            const requirementText = requirement === EVIDENCE_EXCEL_REQUIREMENT.REQUIRED
                                ? '필수'
                                : (requirement === EVIDENCE_EXCEL_REQUIREMENT.HIDDEN ? '선택안함' : '선택');

                            return `
                                <div class="excel-settings-grid-row"
                                     draggable="true"
                                     data-evidence-excel-row
                                     data-kind="${kind}"
                                     data-key="${escapeHtml(column.key)}">
                                    <div class="excel-settings-grid-cell excel-settings-grid-cell-handle">
                                        <button type="button"
                                                class="excel-settings-drag-handle"
                                                aria-label="${escapeHtml(column.label)} 순서 변경"
                                                tabindex="-1"
                                                data-evidence-excel-drag-handle>
                                            <i class="bi bi-list" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="excel-settings-grid-cell excel-settings-grid-cell-usage">
                                        <input class="form-check-input mt-0"
                                               type="checkbox"
                                               data-evidence-excel-toggle
                                               data-kind="${kind}"
                                               data-key="${escapeHtml(column.key)}"
                                               ${checked}>
                                    </div>
                                    <div class="excel-settings-grid-cell excel-settings-grid-cell-order">
                                        <span class="excel-settings-order-value">${index + 1}</span>
                                    </div>
                                    <div class="excel-settings-grid-cell excel-settings-grid-cell-label">
                                        <div class="excel-settings-column-label">${escapeHtml(formatEvidenceColumnDisplayLabel(column.label, column.key))}</div>
                                    </div>
                                    <div class="excel-settings-grid-cell excel-settings-grid-cell-requirement">
                                        <select class="form-select form-select-sm"
                                                data-evidence-excel-requirement
                                                data-kind="${kind}"
                                                data-key="${escapeHtml(column.key)}"
                                                aria-label="${escapeHtml(column.label)} 필수구분">
                                            <option value="hidden" ${requirement === EVIDENCE_EXCEL_REQUIREMENT.HIDDEN ? 'selected' : ''}>선택안함</option>
                                            <option value="optional" ${requirement === EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL ? 'selected' : ''}>선택</option>
                                            <option value="required" ${requirement === EVIDENCE_EXCEL_REQUIREMENT.REQUIRED ? 'selected' : ''}>필수</option>
                                        </select>
                                        <span class="excel-settings-requirement-text">${requirementText}</span>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            data-evidence-excel-action="reset"
                            data-kind="${kind}">기본값 복원</button>
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-evidence-excel-action="save"
                            data-kind="${kind}">저장</button>
                </div>
            </div>
        `;

        panel.querySelectorAll('[data-indeterminate="true"]').forEach((checkbox) => {
            checkbox.indeterminate = true;
        });
    }

    function renderEvidenceExcelManagerPanelV2(kind, excelState) {
        const panel = state.refs.excelForm?.querySelector(`[data-excel-settings-panel="${kind}"]`);
        if (!panel) return;

        const columns = Array.isArray(excelState?.columns) ? excelState.columns : [];
        const selectedCount = columns.filter((column) => column.visible !== false).length;
        const isTemplate = evidenceExcelIsTemplate(kind);
        const description = isTemplate
            ? '\uC5C5\uB85C\uB4DC \uC591\uC2DD\uC5D0 \uD3EC\uD568\uD560 \uCEEC\uB7FC\uACFC \uC21C\uC11C\uB97C \uC124\uC815\uD574 \uC8FC\uC138\uC694.'
            : '\uB2E4\uC6B4\uB85C\uB4DC \uD30C\uC77C\uC5D0 \uD3EC\uD568\uD560 \uCEEC\uB7FC\uACFC \uC21C\uC11C\uB97C \uC124\uC815\uD574 \uC8FC\uC138\uC694.';

        panel.innerHTML = `
            <div class="excel-settings-panel-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div class="text-muted small">${escapeHtml(description)}</div>
                    <div class="text-muted small">\uC120\uD0DD ${selectedCount}\uAC1C / \uC804\uCCB4 ${columns.length}\uAC1C</div>
                </div>
                <div class="excel-settings-grid ${isTemplate ? 'is-template' : 'is-download'}" role="table" aria-label="${isTemplate ? '\uC5C5\uB85C\uB4DC \uC591\uC2DD \uC124\uC815' : '\uB2E4\uC6B4\uB85C\uB4DC \uC124\uC815'}">
                    <div class="excel-settings-grid-header ${isTemplate ? 'excel-settings-grid-header-template' : 'excel-settings-grid-header-download'}" role="row">
                        <div class="excel-settings-grid-head excel-settings-grid-cell-handle">${renderEvidenceExcelDragHeader()}</div>
                        <div class="excel-settings-grid-head excel-settings-grid-cell-usage">${renderEvidenceExcelUsageHeader(kind, columns)}</div>
                        <div class="excel-settings-grid-head excel-settings-grid-cell-order">\uC21C\uBC88</div>
                        <div class="excel-settings-grid-head excel-settings-grid-cell-label">\uCEEC\uB7FC\uBA85</div>
                    </div>
                    <div class="excel-settings-grid-body">
                        ${columns.map((column, index) => {
                            const systemRequired = isEvidenceExcelSystemRequiredColumn(column);
                            const checked = column.visible !== false ? 'checked' : '';
                            return `
                                <div class="excel-settings-grid-row"
                                     draggable="true"
                                     data-evidence-excel-row
                                     data-kind="${kind}"
                                     data-key="${escapeHtml(column.key)}">
                                    <div class="excel-settings-grid-cell excel-settings-grid-cell-handle">
                                        <button type="button"
                                                class="excel-settings-drag-handle"
                                                aria-label="${escapeHtml(column.label)} \uC21C\uC11C \uBCC0\uACBD"
                                                tabindex="-1"
                                                data-evidence-excel-drag-handle>
                                            <i class="bi bi-list" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    <div class="excel-settings-grid-cell excel-settings-grid-cell-usage">
                                        <input class="form-check-input mt-0"
                                               type="checkbox"
                                               data-evidence-excel-toggle
                                               data-kind="${kind}"
                                               data-key="${escapeHtml(column.key)}"
                                               ${systemRequired ? 'disabled' : ''}
                                               ${checked}>
                                    </div>
                                    <div class="excel-settings-grid-cell excel-settings-grid-cell-order">
                                        <span class="excel-settings-order-value">${index + 1}</span>
                                    </div>
                                    <div class="excel-settings-grid-cell excel-settings-grid-cell-label">
                                        <div class="excel-settings-column-label">${escapeHtml(formatEvidenceColumnDisplayLabel(column.label, column.key))}</div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            data-evidence-excel-action="reset"
                            data-kind="${kind}">\uAE30\uBCF8\uAC12 \uBCF5\uC6D0</button>
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-evidence-excel-action="save"
                            data-kind="${kind}">\uC800\uC7A5</button>
                </div>
            </div>
        `;
    }

    function renderEvidenceExcelManager(type = state.currentType, statesOverride = null) {
        const normalizedType = resolveEvidenceType(type);
        const states = statesOverride || ensureEvidenceExcelManagerState(normalizedType);

        renderEvidenceExcelManagerPanelV2('template', states.template);
        renderEvidenceExcelManagerPanelV2('download', states.download);
        syncEvidenceExcelPreparedColumns(normalizedType, states);
    }

    function reorderEvidenceExcelColumn(excelState, draggedKey, targetKey, dropPosition = 'after') {
        const columns = Array.isArray(excelState?.columns) ? [...excelState.columns] : [];
        const sourceIndex = columns.findIndex((column) => column.key === draggedKey);
        const targetIndex = columns.findIndex((column) => column.key === targetKey);
        if (sourceIndex < 0 || targetIndex < 0 || sourceIndex === targetIndex) {
            return excelState;
        }

        const [dragged] = columns.splice(sourceIndex, 1);
        let insertIndex = targetIndex;
        if (sourceIndex < targetIndex) {
            insertIndex -= 1;
        }
        if (dropPosition === 'after') {
            insertIndex += 1;
        }
        insertIndex = Math.max(0, Math.min(columns.length, insertIndex));
        columns.splice(insertIndex, 0, dragged);

        return syncEvidenceExcelStateSnapshot(excelState?.type || 'template', {
            ...excelState,
            columns: columns.map((column, order) => ({
                ...column,
                order: order + 1,
            })),
        });
    }

    function syncEvidenceExcelStateSnapshot(kind, excelState) {
        const columns = Array.isArray(excelState?.columns) ? [...excelState.columns] : [];
        const normalizedColumns = columns
            .map((column, index) => {
                const requirement = evidenceExcelIsTemplate(kind)
                    ? (
                        isEvidenceExcelSystemRequiredColumn(column)
                            ? EVIDENCE_EXCEL_REQUIREMENT.REQUIRED
                            : normalizeEditableEvidenceExcelRequirement(column?.requirement)
                    )
                    : (column?.visible === false ? EVIDENCE_EXCEL_REQUIREMENT.HIDDEN : EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL);

                return {
                    ...column,
                    requirement,
                    visible: isEvidenceExcelSystemRequiredColumn(column)
                        ? true
                        : evidenceExcelRequirementVisible(requirement),
                    order: index + 1,
                };
            });

        const nextState = {
            ...excelState,
            columns: normalizedColumns,
            visibleColumns: normalizedColumns
                .filter((column) => column.visible !== false)
                .map((column) => column.key),
            columnOrder: normalizedColumns.map((column) => column.key),
        };

        if (evidenceExcelIsTemplate(kind)) {
            nextState.columnRequirement = normalizedColumns.reduce((accumulator, column) => {
                accumulator[column.key] = isEvidenceExcelSystemRequiredColumn(column)
                    ? EVIDENCE_EXCEL_REQUIREMENT.REQUIRED
                    : normalizeEditableEvidenceExcelRequirement(column.requirement);
                return accumulator;
            }, {});
        }

        return nextState;
    }

    function setEvidenceExcelVisibility(excelState, key, visible) {
        return {
            ...excelState,
            columns: excelState.columns.map((column) => {
                if (column.key !== key) {
                    return column;
                }

                const requirement = visible
                    ? normalizeEvidenceExcelRequirement(column.requirement, EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL) === EVIDENCE_EXCEL_REQUIREMENT.HIDDEN
                        ? EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL
                        : normalizeEvidenceExcelRequirement(column.requirement, EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL)
                    : EVIDENCE_EXCEL_REQUIREMENT.HIDDEN;

                return {
                    ...column,
                    requirement,
                    visible: evidenceExcelRequirementVisible(requirement),
                };
            }),
        };
    }

    function setEvidenceExcelRequirement(excelState, key, requirement) {
        return {
            ...excelState,
            columns: excelState.columns.map((column) => {
                if (column.key !== key) {
                    return column;
                }

                const nextRequirement = normalizeEvidenceExcelRequirement(requirement, EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL);
                return {
                    ...column,
                    requirement: nextRequirement,
                    visible: evidenceExcelRequirementVisible(nextRequirement),
                };
            }),
        };
    }

    function setEvidenceExcelVisibilityV2(kind, excelState, key, visible) {
        return syncEvidenceExcelStateSnapshot(kind, {
            ...excelState,
            columns: excelState.columns.map((column) => {
                if (column.key !== key) {
                    return column;
                }

                if (isEvidenceExcelSystemRequiredColumn(column)) {
                    return {
                        ...column,
                        requirement: EVIDENCE_EXCEL_REQUIREMENT.REQUIRED,
                        visible: true,
                    };
                }

                const currentRequirement = normalizeEvidenceExcelRequirement(column.requirement, EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL);
                const requirement = visible
                    ? (currentRequirement === EVIDENCE_EXCEL_REQUIREMENT.HIDDEN
                        ? EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL
                        : currentRequirement)
                    : EVIDENCE_EXCEL_REQUIREMENT.HIDDEN;
                return {
                    ...column,
                    requirement,
                    visible: evidenceExcelRequirementVisible(requirement),
                };
            }),
        });
    }

    function setEvidenceExcelRequirementV2(excelState, key, requirement) {
        return syncEvidenceExcelStateSnapshot('template', {
            ...excelState,
            columns: excelState.columns.map((column) => {
                if (column.key !== key) {
                    return column;
                }

                if (isEvidenceExcelSystemRequiredColumn(column)) {
                    return {
                        ...column,
                        requirement: EVIDENCE_EXCEL_REQUIREMENT.REQUIRED,
                        visible: true,
                    };
                }

                const nextRequirement = normalizeEditableEvidenceExcelRequirement(requirement);
                return {
                    ...column,
                    requirement: nextRequirement,
                    visible: evidenceExcelRequirementVisible(nextRequirement),
                };
            }),
        });
    }

    function setEvidenceExcelVisibilityAllV2(kind, excelState, visible) {
        return syncEvidenceExcelStateSnapshot(kind, {
            ...excelState,
            columns: excelState.columns.map((column) => {
                if (isEvidenceExcelSystemRequiredColumn(column)) {
                    return {
                        ...column,
                        requirement: EVIDENCE_EXCEL_REQUIREMENT.REQUIRED,
                        visible: true,
                    };
                }

                if (!evidenceExcelIsTemplate(kind)) {
                    return {
                        ...column,
                        requirement: visible ? EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL : EVIDENCE_EXCEL_REQUIREMENT.HIDDEN,
                        visible,
                    };
                }

                const currentRequirement = normalizeEvidenceExcelRequirement(column.requirement, EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL);
                const nextRequirement = visible
                    ? (currentRequirement === EVIDENCE_EXCEL_REQUIREMENT.HIDDEN
                        ? EVIDENCE_EXCEL_REQUIREMENT.OPTIONAL
                        : currentRequirement)
                    : EVIDENCE_EXCEL_REQUIREMENT.HIDDEN;

                return {
                    ...column,
                    requirement: nextRequirement,
                    visible: evidenceExcelRequirementVisible(nextRequirement),
                };
            }),
        });
    }

    function renderEvidenceExcelDragHeader() {
        return `
            <span class="excel-settings-head-icon" aria-hidden="true">
                <i class="bi bi-arrows-move"></i>
            </span>
            <span class="visually-hidden">드래그</span>
        `;
    }

    function renderEvidenceExcelUsageHeader(kind, columns) {
        const editableColumns = (Array.isArray(columns) ? columns : []).filter((column) => !evidenceExcelIsTemplate(kind) || !isEvidenceExcelSystemRequiredColumn(column));
        const checkedCount = editableColumns.filter((column) => column.visible !== false).length;
        const allChecked = editableColumns.length > 0 && checkedCount === editableColumns.length;
        const partiallyChecked = checkedCount > 0 && checkedCount < editableColumns.length;

        return `
            <label class="excel-settings-head-toggle" aria-label="전체 선택">
                <input class="form-check-input mt-0"
                       type="checkbox"
                       data-evidence-excel-toggle-all
                       data-kind="${escapeHtml(kind)}"
                       ${allChecked ? 'checked' : ''}
                       ${partiallyChecked ? 'data-indeterminate="true"' : ''}
                       ${editableColumns.length === 0 ? 'disabled' : ''}>
            </label>
        `;
    }

    function ensureEvidenceExcelManagerCore(type = state.currentType) {
        if (!state.refs.excelForm) return null;

        const normalizedType = resolveEvidenceType(type || state.currentType);

        if (usesEvidenceExcelManagerCore(normalizedType)) {
            const evidenceExcelDomain = evidenceExcelManagerCoreDomain(normalizedType);

            if (!state.excelManagerSettingsCore || state.excelManagerSettingsCore.domain !== evidenceExcelDomain) {
                state.excelManagerSettingsCore?.destroy?.();
                state.excelManagerSettingsCore = createExcelManagerSettingsCore({
                    domain: evidenceExcelDomain,
                    formSelector: '#dataExcelForm',
                    getColumns: () => evidenceExcelManagerColumns(normalizedType),
                    tableSettingsStorageKey: evidenceStatusTableSettingsStorageKey(normalizedType),
                    tableSettingsMetaDomain: evidenceMetaDomain(normalizedType),
                });
            } else {
                state.excelManagerSettingsCore.reload?.();
            }

            return state.excelManagerSettingsCore;
        }

        renderEvidenceExcelManager(normalizedType);
        return state.excelManagerSettingsCore;
    }

    function bindExcelEvents() {
        state.refs.excelForm?.addEventListener('click', (event) => {
            const actionButton = event.target.closest('[data-evidence-excel-action]');
            if (actionButton) {
                if (usesEvidenceExcelManagerCore(state.currentType)) {
                    return;
                }
                event.preventDefault();
                const kind = String(actionButton.dataset.kind || '').trim();
                const normalizedType = resolveEvidenceType(state.currentType);
                const states = ensureEvidenceExcelManagerState(normalizedType);

                if (actionButton.dataset.evidenceExcelAction === 'reset') {
                    states[kind] = defaultEvidenceExcelManagerState(normalizedType, kind);
                    renderEvidenceExcelManager(normalizedType, states);
                    return;
                }

                if (actionButton.dataset.evidenceExcelAction === 'save') {
                    states[kind] = saveEvidenceExcelManagerState(normalizedType, kind, states[kind]);
                    renderEvidenceExcelManager(normalizedType, states);
                    notify('success', kind === 'template' ? '양식 설정이 저장되었습니다.' : '다운로드 설정이 저장되었습니다.');
                }
            }
        });

        state.refs.excelForm?.addEventListener('change', (event) => {
            if (usesEvidenceExcelManagerCore(state.currentType)) {
                return;
            }
            const toggleAll = event.target.closest('[data-evidence-excel-toggle-all]');
            const normalizedType = resolveEvidenceType(state.currentType);
            const states = ensureEvidenceExcelManagerState(normalizedType);

            if (toggleAll) {
                const kind = String(toggleAll.dataset.kind || '').trim();
                states[kind] = setEvidenceExcelVisibilityAllV2(kind, states[kind], toggleAll.checked);
                renderEvidenceExcelManager(normalizedType, states);
                return;
            }

            const toggle = event.target.closest('[data-evidence-excel-toggle]');
            if (toggle) {
                const kind = String(toggle.dataset.kind || '').trim();
                const key = String(toggle.dataset.key || '').trim();
                states[kind] = setEvidenceExcelVisibilityV2(kind, states[kind], key, toggle.checked);
                renderEvidenceExcelManager(normalizedType, states);
                return;
            }

            const requirementSelect = event.target.closest('[data-evidence-excel-requirement]');
            if (!requirementSelect) return;

            const kind = String(requirementSelect.dataset.kind || '').trim();
            const key = String(requirementSelect.dataset.key || '').trim();
            states[kind] = setEvidenceExcelRequirementV2(states[kind], key, requirementSelect.value);
            renderEvidenceExcelManager(normalizedType, states);
        });

        state.refs.excelForm?.addEventListener('dragstart', (event) => {
            if (usesEvidenceExcelManagerCore(state.currentType)) return;
            const row = event.target.closest('[data-evidence-excel-row]');
            if (!row) return;

            state.refs.excelForm.__evidenceExcelDragState = {
                kind: String(row.dataset.kind || '').trim(),
                key: String(row.dataset.key || '').trim(),
            };
            row.classList.add('is-dragging');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', `${row.dataset.kind || ''}:${row.dataset.key || ''}`);
            }
        });

        state.refs.excelForm?.addEventListener('dragover', (event) => {
            if (usesEvidenceExcelManagerCore(state.currentType)) return;
            const row = event.target.closest('[data-evidence-excel-row]');
            const dragState = state.refs.excelForm?.__evidenceExcelDragState;
            if (!row || !dragState) return;

            const kind = String(row.dataset.kind || '').trim();
            const key = String(row.dataset.key || '').trim();
            if (dragState.kind !== kind || dragState.key === key) return;

            event.preventDefault();
            const rect = row.getBoundingClientRect();
            const dropPosition = event.clientY < rect.top + (rect.height / 2) ? 'before' : 'after';
            state.refs.excelForm?.querySelectorAll(`[data-evidence-excel-row][data-kind="${kind}"]`).forEach((item) => {
                item.classList.remove('is-drop-before', 'is-drop-after');
                delete item.dataset.dropPosition;
            });
            row.dataset.dropPosition = dropPosition;
            row.classList.add(dropPosition === 'before' ? 'is-drop-before' : 'is-drop-after');
        });

        state.refs.excelForm?.addEventListener('drop', (event) => {
            if (usesEvidenceExcelManagerCore(state.currentType)) return;
            const row = event.target.closest('[data-evidence-excel-row]');
            const dragState = state.refs.excelForm?.__evidenceExcelDragState;
            if (!row || !dragState) return;

            const kind = String(row.dataset.kind || '').trim();
            const key = String(row.dataset.key || '').trim();
            if (dragState.kind !== kind || dragState.key === key) return;

            event.preventDefault();
            const normalizedType = resolveEvidenceType(state.currentType);
            const states = ensureEvidenceExcelManagerState(normalizedType);
            states[kind] = reorderEvidenceExcelColumn(states[kind], dragState.key, key, row.dataset.dropPosition || 'after');
            state.refs.excelForm.__evidenceExcelDragState = null;
            state.refs.excelForm?.querySelectorAll(`[data-evidence-excel-row][data-kind="${kind}"]`).forEach((item) => {
                item.classList.remove('is-drop-before', 'is-drop-after', 'is-dragging');
                delete item.dataset.dropPosition;
            });
            renderEvidenceExcelManager(normalizedType, states);
        });

        state.refs.excelForm?.addEventListener('dragend', () => {
            if (usesEvidenceExcelManagerCore(state.currentType)) return;
            state.refs.excelForm.__evidenceExcelDragState = null;
            state.refs.excelForm?.querySelectorAll('[data-evidence-excel-row]').forEach((row) => {
                row.classList.remove('is-drop-before', 'is-drop-after', 'is-dragging');
                delete row.dataset.dropPosition;
            });
        });
    }

    function syncExcelManager(config = currentConfig()) {
        if (!state.refs.excelForm) return;

        state.refs.excelForm.dataset.templateUrl = `/api/import/template?type=${encodeURIComponent(resolveEvidenceType(state.currentType))}`;
        state.refs.excelForm.dataset.downloadUrl = state.currentType
            ? `${API.download}?import_type=${encodeURIComponent(state.currentType)}`
            : '';
        state.refs.excelForm.dataset.uploadUrl = API.upload;
        state.refs.excelForm.dataset.importType = state.currentType || '';
        ensureEvidenceExcelManagerCore(state.currentType);

        const subtitle = state.refs.excelModal?.querySelector('.excel-modal-subtitle');
        if (subtitle) {
            subtitle.textContent = `${config.label} / 자료유형 기준`;
        }

        const templateBtn = state.refs.excelModal?.querySelector('.btn-template-download');
        if (templateBtn) {
            templateBtn.disabled = !state.currentType;
        }

        const downloadBtn = state.refs.excelModal?.querySelector('.btn-download-all');
        if (downloadBtn) {
            downloadBtn.disabled = !state.currentType;
            downloadBtn.title = '';
        }

        const uploadBtn = state.refs.excelModal?.querySelector('.btn-upload-excel');
        if (uploadBtn) {
            uploadBtn.disabled = !state.currentType;
            uploadBtn.title = '';
        }
    }

    return {
        bindExcelEvents,
        syncExcelManager,
    };
}
