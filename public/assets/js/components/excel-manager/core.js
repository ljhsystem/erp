import {
    buildExcelColumnSettingsKey,
    buildExcelDownloadColumns,
    buildExcelTemplateColumns,
} from '../../common/column-meta/index.js';
import {
    fetchDataTableMetaColumnsSync,
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '../../common/datatable/dataTableSettings.js';
import { loadExcelSettings, saveExcelSettings } from './storage.js';
import {
    ensureExcelSettingsLayout,
    renderExcelSettingsPanel,
    resetExcelSettingsUi,
    toggleExcelSettingsRoot,
    toggleExcelSettingsPanel,
} from './ui.js';

const STORAGE_VERSION = 3;
const REQUIREMENT = Object.freeze({
    HIDDEN: 'hidden',
    OPTIONAL: 'optional',
    REQUIRED: 'required',
});
const TABLE_REQUIREMENT_POLICY = Object.freeze({
    NONE: 'none',
    OPTIONAL: 'optional',
    REQUIRED: 'required',
});

function notify(type, message) {
    if (window.AppCore?.notify) {
        window.AppCore.notify(type, message);
    }
}

function isTemplateType(type) {
    return String(type || '').trim().toLowerCase() === 'template';
}

function normalizeTableRequirementPolicy(value = '') {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === TABLE_REQUIREMENT_POLICY.REQUIRED) return TABLE_REQUIREMENT_POLICY.REQUIRED;
    if (normalized === TABLE_REQUIREMENT_POLICY.OPTIONAL) return TABLE_REQUIREMENT_POLICY.OPTIONAL;
    return TABLE_REQUIREMENT_POLICY.NONE;
}

function normalizePolicyState(state) {
    return state && typeof state === 'object' ? state : {};
}

function findBoundTableSettingsState(targetDomain = '') {
    const normalizedTargetDomain = String(targetDomain || '').trim();
    if (!normalizedTargetDomain) {
        return null;
    }

    const tableElements = Array.from(document.querySelectorAll('table'));
    for (const tableElement of tableElements) {
        const table = tableElement?.__dtCurrentInstance;
        const context = table?.__dtTableSettings?.context;
        const metaDomain = String(context?.config?.metaDomain || '').trim();
        if (metaDomain !== normalizedTargetDomain) {
            continue;
        }
        return normalizePolicyState(context?.state);
    }

    return null;
}

function resolvePolicyState(domain, form, options = {}) {
    if (typeof options?.getTableSettingsState === 'function') {
        return normalizePolicyState(options.getTableSettingsState());
    }

    const configuredStorageKey = String(
        options?.tableSettingsStorageKey
        || form?.dataset?.tableSettingsStorageKey
        || ''
    ).trim();
    if (configuredStorageKey !== '') {
        return normalizePolicyState(readDataTableSettingsState(configuredStorageKey));
    }

    const metaDomain = String(
        options?.tableSettingsMetaDomain
        || form?.dataset?.tableSettingsMetaDomain
        || domain
    ).trim();
    return findBoundTableSettingsState(metaDomain) || {};
}

function applyPolicyToColumn(column = {}, policyState = {}) {
    const key = String(column?.key || '').trim();
    const sourceLabel = key || String(column?.sourceLabel || column?.label || '').trim();
    const displayFallback = String(column?.displayLabel || column?.label || key).trim() || key;
    const displayLabel = resolveDataTableColumnDisplayName(
        { key, source_key: key, payload_key: key, system_field_name: key },
        policyState,
        displayFallback
    );
    const requirementPolicy = normalizeTableRequirementPolicy(
        resolveDataTableColumnRequirementPolicy(
            { key, source_key: key, payload_key: key, system_field_name: key },
            policyState
        )
    );
    const combinedLabel = displayLabel !== '' && sourceLabel !== '' && displayLabel !== sourceLabel
        ? `${displayLabel} (${sourceLabel})`
        : (displayLabel || sourceLabel || key);

    return {
        ...column,
        label: displayLabel || sourceLabel || key,
        displayLabel: displayLabel || sourceLabel || key,
        sourceLabel: sourceLabel || key,
        combinedLabel,
        requirementPolicy,
    };
}

function resolveMetaConfig(domain, form, options = {}) {
    return {
        metaColumns: Array.isArray(options?.metaColumns) ? options.metaColumns : [],
        metaCacheKey: String(options?.metaCacheKey || '').trim(),
        metaUrl: String(options?.metaUrl || form?.dataset?.tableSettingsMetaUrl || '').trim(),
        metaDomain: String(
            options?.tableSettingsMetaDomain
            || form?.dataset?.tableSettingsMetaDomain
            || options?.metaDomain
            || domain
        ).trim(),
    };
}

function buildExcelColumnsFromMeta(domain, type, options = {}) {
    const form = options?.form || null;
    const metaColumns = fetchDataTableMetaColumnsSync(resolveMetaConfig(domain, form, options))
        .filter((column) => String(column?.column_type || 'physical') === 'physical');

    if (metaColumns.length === 0) {
        return [];
    }

    const policyState = resolvePolicyState(domain, form, options);
    return metaColumns.map((column) => applyPolicyToColumn({
        key: column.key,
        label: column.label || column.key,
        sourceLabel: column.key,
        required: false,
        defaultSelected: type === 'download',
        type: column.data_type || 'text',
        width: null,
        table: column.table || '',
        ordinal_position: column.ordinal_position,
    }, policyState));
}

function getDefaultColumns(domain, type, options = {}) {
    const metaColumns = buildExcelColumnsFromMeta(domain, type, options);
    if (metaColumns.length > 0) {
        return metaColumns;
    }

    const policyState = resolvePolicyState(domain, options?.form || null, options);
    if (typeof options?.getColumns === 'function') {
        const columns = options.getColumns(type, domain);
        if (Array.isArray(columns)) {
            return columns.map((column) => applyPolicyToColumn({
                ...column,
                sourceLabel: String(column?.key || column?.sourceLabel || column?.label || '').trim(),
            }, policyState));
        }
    }

    const columns = isTemplateType(type)
        ? buildExcelTemplateColumns(domain)
        : buildExcelDownloadColumns(domain);
    return columns.map((column) => applyPolicyToColumn({
        ...column,
        sourceLabel: String(column?.key || column?.label || '').trim(),
    }, policyState));
}

function normalizeRequirement(value, fallback = REQUIREMENT.OPTIONAL) {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === REQUIREMENT.HIDDEN) return REQUIREMENT.HIDDEN;
    if (normalized === REQUIREMENT.REQUIRED) return REQUIREMENT.REQUIRED;
    if (normalized === REQUIREMENT.OPTIONAL) return REQUIREMENT.OPTIONAL;
    return fallback;
}

function editableRequirement(value) {
    return normalizeRequirement(value, REQUIREMENT.OPTIONAL);
}

function visibleForRequirement(requirement) {
    return normalizeRequirement(requirement) !== REQUIREMENT.HIDDEN;
}

function isSystemRequiredColumn(column) {
    return column?.systemRequired === true || column?.required === true;
}

function defaultRequirement(column, type) {
    if (isTemplateType(type) && isSystemRequiredColumn(column)) {
        return REQUIREMENT.REQUIRED;
    }

    return column.defaultSelected === true
        ? REQUIREMENT.OPTIONAL
        : REQUIREMENT.HIDDEN;
}

function createDefaultState(domain, type, options = {}) {
    const columns = getDefaultColumns(domain, type, options);
    const columnOrder = columns.map((column) => column.key);
    const visibleColumns = [];
    const columnRequirement = {};
    const states = columns.map((column, index) => {
        const requirement = defaultRequirement(column, type);
        const visible = visibleForRequirement(requirement);
        if (visible) {
            visibleColumns.push(column.key);
        }
        if (isTemplateType(type)) {
            columnRequirement[column.key] = requirement;
        }

        return {
            key: column.key,
            label: column.label,
            sourceLabel: column.sourceLabel || column.label,
            displayLabel: column.displayLabel || column.label,
            combinedLabel: column.combinedLabel || column.label,
            systemRequired: isSystemRequiredColumn(column),
            requirementPolicy: normalizeTableRequirementPolicy(column.requirementPolicy),
            visible,
            requirement,
            order: index + 1,
        };
    });

    const payload = {
        version: STORAGE_VERSION,
        domain,
        type,
        updatedAt: '',
        visibleColumns,
        columnOrder,
        columns: states,
    };

    if (isTemplateType(type)) {
        payload.columnRequirement = columnRequirement;
    }

    return payload;
}

function buildColumnState(defaultColumns, columnOrder, type, visibleSet, columnRequirement) {
    const orderMap = new Map(columnOrder.map((key, index) => [key, index]));

    return defaultColumns
        .map((column, index) => {
            const requirement = isTemplateType(type)
                ? (
                    isSystemRequiredColumn(column)
                        ? REQUIREMENT.REQUIRED
                        : editableRequirement(columnRequirement[column.key] ?? defaultRequirement(column, type))
                )
                : (visibleSet.has(column.key) ? REQUIREMENT.OPTIONAL : REQUIREMENT.HIDDEN);

            return {
                key: column.key,
                label: column.label,
                sourceLabel: column.sourceLabel || column.label,
                displayLabel: column.displayLabel || column.label,
                combinedLabel: column.combinedLabel || column.label,
                systemRequired: isSystemRequiredColumn(column),
                requirementPolicy: normalizeTableRequirementPolicy(column.requirementPolicy),
                requirement,
                visible: isTemplateType(type) && isSystemRequiredColumn(column) ? true : visibleForRequirement(requirement),
                order: orderMap.has(column.key) ? Number(orderMap.get(column.key)) + 1 : index + 1,
            };
        })
        .sort((left, right) => Number(left.order || 0) - Number(right.order || 0))
        .map((column, index) => ({
            ...column,
            order: index + 1,
        }));
}

function normalizeState(domain, type, state, options = {}) {
    const defaults = createDefaultState(domain, type, options);
    const defaultColumns = getDefaultColumns(domain, type, options);
    const savedColumns = Array.isArray(state?.columns) ? state.columns : [];
    const savedColumnMap = new Map(
        savedColumns
            .map((column) => [String(column?.key || '').trim(), column])
            .filter(([key]) => key !== '')
    );
    const legacyVisibleColumns = savedColumns
        .filter((column) => column?.visible !== false)
        .map((column) => String(column?.key || '').trim())
        .filter(Boolean);
    const legacyColumnOrder = [...savedColumns]
        .sort((left, right) => Number(left?.order || 0) - Number(right?.order || 0))
        .map((column) => String(column?.key || '').trim())
        .filter(Boolean);
    const visibleColumns = Array.isArray(state?.visibleColumns)
        ? state.visibleColumns.map((key) => String(key || '').trim()).filter(Boolean)
        : (legacyVisibleColumns.length > 0 ? legacyVisibleColumns : defaults.visibleColumns);
    const legacyStateDetected = savedColumns.length > 0
        || Array.isArray(state?.visibleColumns)
        || Array.isArray(state?.columnOrder);
    const visibleSet = new Set(visibleColumns);
    const columnOrder = (() => {
        const savedOrder = Array.isArray(state?.columnOrder) && state.columnOrder.length > 0
            ? state.columnOrder
            : legacyColumnOrder;
        const ordered = [];
        const seen = new Set();

        savedOrder.forEach((key) => {
            const normalizedKey = String(key || '').trim();
            if (!normalizedKey || seen.has(normalizedKey) || !defaultColumns.some((column) => column.key === normalizedKey)) {
                return;
            }
            seen.add(normalizedKey);
            ordered.push(normalizedKey);
        });

        defaults.columnOrder.forEach((key) => {
            if (seen.has(key)) return;
            seen.add(key);
            ordered.push(key);
        });

        return ordered;
    })();

    const columnRequirement = {};
    defaultColumns.forEach((column) => {
        const key = column.key;
        const savedColumn = savedColumnMap.get(key);

        if (!isTemplateType(type)) {
            columnRequirement[key] = visibleSet.has(key) ? REQUIREMENT.OPTIONAL : REQUIREMENT.HIDDEN;
            return;
        }

        if (state?.columnRequirement && typeof state.columnRequirement === 'object' && Object.prototype.hasOwnProperty.call(state.columnRequirement, key)) {
            columnRequirement[key] = editableRequirement(state.columnRequirement[key]);
            return;
        }

        if (savedColumn && typeof savedColumn.requirement === 'string') {
            columnRequirement[key] = editableRequirement(savedColumn.requirement);
            return;
        }

        if (legacyStateDetected) {
            columnRequirement[key] = visibleSet.has(key)
                ? REQUIREMENT.OPTIONAL
                : REQUIREMENT.HIDDEN;
            return;
        }

        columnRequirement[key] = editableRequirement(defaultRequirement(column, type));
    });

    const columns = buildColumnState(defaultColumns, columnOrder, type, visibleSet, columnRequirement);
    const normalized = {
        version: STORAGE_VERSION,
        domain,
        type,
        updatedAt: String(state?.updatedAt || ''),
        visibleColumns: columns.filter((column) => column.visible).map((column) => column.key),
        columnOrder: columns.map((column) => column.key),
        columns,
    };

    if (isTemplateType(type)) {
        normalized.columnRequirement = columns.reduce((accumulator, column) => {
            accumulator[column.key] = editableRequirement(column.requirement);
            return accumulator;
        }, {});
    }

    return normalized;
}

function settingsKey(domain, type) {
    return buildExcelColumnSettingsKey(domain, type);
}

function needsStateMigration(type, state) {
    if (!state || typeof state !== 'object') {
        return false;
    }

    if (Number(state.version || 0) < STORAGE_VERSION) {
        return true;
    }

    if (!Array.isArray(state.visibleColumns) || !Array.isArray(state.columnOrder)) {
        return true;
    }

    if (isTemplateType(type)) {
        return !state.columnRequirement || typeof state.columnRequirement !== 'object';
    }

    return Object.prototype.hasOwnProperty.call(state, 'columnRequirement')
        || Array.isArray(state.columns);
}

function serializeState(type, state) {
    const payload = {
        version: STORAGE_VERSION,
        domain: state.domain,
        type: state.type,
        updatedAt: String(state.updatedAt || ''),
        visibleColumns: Array.isArray(state.visibleColumns) ? [...state.visibleColumns] : [],
        columnOrder: Array.isArray(state.columnOrder) ? [...state.columnOrder] : [],
    };

    if (isTemplateType(type)) {
        payload.columnRequirement = { ...(state.columnRequirement || {}) };
    }

    return payload;
}

function loadState(domain, type, options = {}) {
    const fallback = serializeState(type, createDefaultState(domain, type, options));
    const key = settingsKey(domain, type);
    const loaded = loadExcelSettings(key, fallback);
    const normalized = normalizeState(domain, type, loaded, options);

    if (needsStateMigration(type, loaded)) {
        saveExcelSettings(key, serializeState(type, normalized));
    }

    return normalized;
}

function persistState(domain, type, state, options = {}) {
    const syncedState = syncStateSnapshot(type, state);
    const normalized = normalizeState(domain, type, syncedState, options);
    const payload = saveExcelSettings(settingsKey(domain, type), serializeState(type, normalized));
    return normalizeState(domain, type, payload, options);
}

function reorderColumns(state, draggedKey, targetKey, dropPosition = 'after') {
    const columns = Array.isArray(state?.columns) ? [...state.columns] : [];
    const sourceIndex = columns.findIndex((column) => column.key === draggedKey);
    const targetIndex = columns.findIndex((column) => column.key === targetKey);

    if (sourceIndex < 0 || targetIndex < 0 || sourceIndex === targetIndex) {
        return state;
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

    return syncStateSnapshot(state?.type || 'template', {
        ...state,
        columns: columns.map((column, index) => ({
            ...column,
            order: index + 1,
        })),
    });
}

function syncStateSnapshot(type, state) {
    const columns = Array.isArray(state?.columns) ? [...state.columns] : [];
    const normalizedColumns = columns.map((column, index) => {
        const requirement = isTemplateType(type)
            ? (
                isSystemRequiredColumn(column)
                    ? REQUIREMENT.REQUIRED
                    : editableRequirement(column?.requirement)
            )
            : (column?.visible === false ? REQUIREMENT.HIDDEN : REQUIREMENT.OPTIONAL);

        return {
            ...column,
            requirement,
            visible: isTemplateType(type) && isSystemRequiredColumn(column)
                ? true
                : visibleForRequirement(requirement),
            order: index + 1,
        };
    });

    const nextState = {
        ...state,
        columns: normalizedColumns,
        visibleColumns: normalizedColumns
            .filter((column) => column.visible !== false)
            .map((column) => column.key),
        columnOrder: normalizedColumns.map((column) => column.key),
    };

    if (isTemplateType(type)) {
        nextState.columnRequirement = normalizedColumns.reduce((accumulator, column) => {
            accumulator[column.key] = editableRequirement(column.requirement);
            return accumulator;
        }, {});
    }

    return nextState;
}

function setVisibility(type, state, key, visible) {
    return syncStateSnapshot(type, {
        ...state,
        columns: state.columns.map((column) => {
            if (column.key !== key) {
                return column;
            }

            if (!isTemplateType(type)) {
                return {
                    ...column,
                    requirement: visible ? REQUIREMENT.OPTIONAL : REQUIREMENT.HIDDEN,
                    visible: visible === true,
                };
            }

            if (isSystemRequiredColumn(column)) {
                return {
                    ...column,
                    requirement: REQUIREMENT.REQUIRED,
                    visible: true,
                };
            }

            const currentRequirement = editableRequirement(column.requirement);
            return {
                ...column,
                requirement: visible
                    ? (currentRequirement === REQUIREMENT.HIDDEN ? REQUIREMENT.OPTIONAL : currentRequirement)
                    : REQUIREMENT.HIDDEN,
                visible: visible === true,
            };
        }),
    });
}

function setRequirement(state, key, requirement) {
    return syncStateSnapshot('template', {
        ...state,
        columns: state.columns.map((column) => {
            if (column.key !== key) {
                return column;
            }

            if (isSystemRequiredColumn(column)) {
                return {
                    ...column,
                    requirement: REQUIREMENT.REQUIRED,
                    visible: true,
                };
            }

            const nextRequirement = editableRequirement(requirement);
            return {
                ...column,
                requirement: nextRequirement,
                visible: visibleForRequirement(nextRequirement),
            };
        }),
    });
}

function buildSelectedColumns(state) {
    return (Array.isArray(state?.columns) ? state.columns : [])
        .filter((column) => column.visible !== false)
        .sort((a, b) => Number(a.order || 0) - Number(b.order || 0))
        .map((column) => column.key);
}

function buildColumnRequirement(state) {
    return (Array.isArray(state?.columns) ? state.columns : [])
        .sort((a, b) => Number(a.order || 0) - Number(b.order || 0))
        .reduce((accumulator, column) => {
            accumulator[column.key] = editableRequirement(column.requirement);
            return accumulator;
        }, {});
}

function setVisibilityAll(type, state, visible) {
    const columns = Array.isArray(state?.columns) ? state.columns : [];
    return syncStateSnapshot(type, {
        ...state,
        columns: columns.map((column) => {
            if (isTemplateType(type) && isSystemRequiredColumn(column)) {
                return {
                    ...column,
                    requirement: REQUIREMENT.REQUIRED,
                    visible: true,
                };
            }

            if (!isTemplateType(type)) {
                return {
                    ...column,
                    requirement: visible ? REQUIREMENT.OPTIONAL : REQUIREMENT.HIDDEN,
                    visible,
                };
            }

            const currentRequirement = editableRequirement(column.requirement);
            const nextRequirement = visible
                ? (currentRequirement === REQUIREMENT.HIDDEN ? REQUIREMENT.OPTIONAL : currentRequirement)
                : REQUIREMENT.HIDDEN;

            return {
                ...column,
                requirement: nextRequirement,
                visible: visibleForRequirement(nextRequirement),
            };
        }),
    });
}

function syncPreparedColumns(form, states) {
    if (!form) {
        return;
    }

    const templateColumns = buildSelectedColumns(states.template);
    const downloadColumns = buildSelectedColumns(states.download);

    form.dataset.excelTemplateColumns = JSON.stringify(templateColumns);
    form.dataset.excelDownloadColumns = JSON.stringify(downloadColumns);
    delete form.dataset.excelTemplateColumnRequirement;
    delete form.dataset.excelDownloadColumnRequirement;
    form.__excelPreparedColumns = {
        template: templateColumns,
        download: downloadColumns,
    };
    form.__excelPreparedPolicy = {
        template: (Array.isArray(states?.template?.columns) ? states.template.columns : [])
            .filter((column) => column.visible !== false)
            .reduce((acc, column) => {
                acc.displayName[column.key] = String(column.displayLabel || column.label || column.key || '').trim();
                acc.requirementPolicy[column.key] = normalizeTableRequirementPolicy(column.requirementPolicy);
                return acc;
            }, { displayName: {}, requirementPolicy: {} }),
        download: (Array.isArray(states?.download?.columns) ? states.download.columns : [])
            .filter((column) => column.visible !== false)
            .reduce((acc, column) => {
                acc.displayName[column.key] = String(column.displayLabel || column.label || column.key || '').trim();
                acc.requirementPolicy[column.key] = normalizeTableRequirementPolicy(column.requirementPolicy);
                return acc;
            }, { displayName: {}, requirementPolicy: {} }),
    };
    delete form.__excelPreparedRequirement;
}

function applyCurrentPolicyState(domain, form, state, options = {}) {
    const policyState = resolvePolicyState(domain, form, options);
    const columns = Array.isArray(state?.columns) ? state.columns : [];

    return {
        ...state,
        columns: columns.map((column) => applyPolicyToColumn(column, policyState)),
    };
}

function clearDropIndicators(form, type = '') {
    const selector = type
        ? `[data-excel-settings-panel="${type}"] [data-excel-column-row]`
        : '[data-excel-column-row]';
    form?.querySelectorAll(selector).forEach((row) => {
        row.classList.remove('is-drop-before', 'is-drop-after', 'is-dragging');
        delete row.dataset.dropPosition;
    });
}

function render(form, domain, states, options = {}) {
    states.template = applyCurrentPolicyState(domain, form, states.template, options);
    states.download = applyCurrentPolicyState(domain, form, states.download, options);
    renderExcelSettingsPanel(form, 'template', states.template.columns);
    renderExcelSettingsPanel(form, 'download', states.download.columns);
    syncPreparedColumns(form, states);
}

function bindEvents(form, domain, states, options = {}) {
    form.__excelSettingsCoreCleanup?.();
    const modal = form.closest('.modal');

    const handleClick = (event) => {
        const rootToggleButton = event.target.closest('[data-excel-settings-root-toggle]');
        if (rootToggleButton) {
            toggleExcelSettingsRoot(form);
            return;
        }

        const toggleButton = event.target.closest('[data-excel-settings-toggle]');
        if (toggleButton) {
            toggleExcelSettingsPanel(form, toggleButton.dataset.excelSettingsToggle || '');
            return;
        }

        const actionButton = event.target.closest('[data-excel-settings-action]');
        if (!actionButton) {
            return;
        }

        const type = actionButton.dataset.type || '';
        const action = actionButton.dataset.excelSettingsAction || '';

        if (action === 'reset') {
            states[type] = createDefaultState(domain, type, options);
            render(form, domain, states, options);
            return;
        }

        if (action === 'save') {
            states[type] = persistState(domain, type, states[type], options);
            render(form, domain, states, options);
            notify('success', type === 'template' ? '업로드 양식설정을 저장했습니다.' : '다운로드설정을 저장했습니다.');
        }
    };

    const handleChange = (event) => {
        const toggleAll = event.target.closest('[data-excel-column-toggle-all]');
        if (toggleAll) {
            const type = toggleAll.dataset.type || '';
            states[type] = setVisibilityAll(type, states[type], toggleAll.checked);
            render(form, domain, states, options);
            return;
        }

        const toggle = event.target.closest('[data-excel-column-toggle]');
        if (toggle) {
            const type = toggle.dataset.type || '';
            const key = toggle.dataset.key || '';
            states[type] = setVisibility(type, states[type], key, toggle.checked);
            render(form, domain, states, options);
            return;
        }

        const requirementSelect = event.target.closest('[data-excel-column-requirement]');
        if (!requirementSelect) {
            return;
        }

        const type = requirementSelect.dataset.type || '';
        if (!isTemplateType(type)) {
            return;
        }

        const key = requirementSelect.dataset.key || '';
        states[type] = setRequirement(states[type], key, requirementSelect.value);
        render(form, domain, states, options);
    };

    const handleDragStart = (event) => {
        const row = event.target.closest('[data-excel-column-row]');
        if (!row) {
            return;
        }

        const type = row.dataset.type || '';
        const key = row.dataset.key || '';
        form.__excelDragState = { type, key };
        row.classList.add('is-dragging');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 
                type + ':' + key
            );
        }
    };

    const handleDragOver = (event) => {
        const row = event.target.closest('[data-excel-column-row]');
        const dragState = form.__excelDragState;
        if (!row || !dragState) {
            return;
        }

        const type = row.dataset.type || '';
        const key = row.dataset.key || '';
        if (dragState.type !== type || dragState.key === key) {
            return;
        }

        event.preventDefault();
        const rect = row.getBoundingClientRect();
        const position = event.clientY < rect.top + (rect.height / 2) ? 'before' : 'after';
        clearDropIndicators(form, type);
        row.dataset.dropPosition = position;
        row.classList.add(position === 'before' ? 'is-drop-before' : 'is-drop-after');
    };

    const handleDrop = (event) => {
        const row = event.target.closest('[data-excel-column-row]');
        const dragState = form.__excelDragState;
        if (!row || !dragState) {
            return;
        }

        const type = row.dataset.type || '';
        const key = row.dataset.key || '';
        if (dragState.type !== type || dragState.key === key) {
            return;
        }

        event.preventDefault();
        const dropPosition = row.dataset.dropPosition || 'after';
        states[type] = reorderColumns(states[type], dragState.key, key, dropPosition);
        form.__excelDragState = null;
        clearDropIndicators(form, type);
        render(form, domain, states, options);
    };

    const handleDragEnd = () => {
        form.__excelDragState = null;
        clearDropIndicators(form);
    };

    const handleDragLeave = (event) => {
        const row = event.target.closest('[data-excel-column-row]');
        if (!row || row.contains(event.relatedTarget)) {
            return;
        }

        row.classList.remove('is-drop-before', 'is-drop-after');
        delete row.dataset.dropPosition;
    };

    const reloadFromTableSettings = () => {
        states.template = loadState(domain, 'template', options);
        states.download = loadState(domain, 'download', options);
        render(form, domain, states, options);
    };

    const handleBeforePrepareAction = () => {
        syncPreparedColumns(form, states);
    };

    const handleModalHidden = () => {
        form.__excelDragState = null;
        clearDropIndicators(form);
        resetExcelSettingsUi(form);
    };

    form.addEventListener('click', handleClick);
    form.addEventListener('change', handleChange);
    form.addEventListener('dragstart', handleDragStart);
    form.addEventListener('dragover', handleDragOver);
    form.addEventListener('drop', handleDrop);
    form.addEventListener('dragend', handleDragEnd);
    form.addEventListener('dragleave', handleDragLeave);
    form.addEventListener('excel:before-prepare-action', handleBeforePrepareAction);
    document.addEventListener('datatable-settings:updated', reloadFromTableSettings);
    modal?.addEventListener('hidden.bs.modal', handleModalHidden);

    form.__excelSettingsCoreBound = true;
    form.__excelSettingsCoreCleanup = () => {
        form.removeEventListener('click', handleClick);
        form.removeEventListener('change', handleChange);
        form.removeEventListener('dragstart', handleDragStart);
        form.removeEventListener('dragover', handleDragOver);
        form.removeEventListener('drop', handleDrop);
        form.removeEventListener('dragend', handleDragEnd);
        form.removeEventListener('dragleave', handleDragLeave);
        form.removeEventListener('excel:before-prepare-action', handleBeforePrepareAction);
        document.removeEventListener('datatable-settings:updated', reloadFromTableSettings);
        modal?.removeEventListener('hidden.bs.modal', handleModalHidden);
        form.__excelSettingsCoreBound = false;
        form.__excelDragState = null;
    };
}

export function createExcelManagerSettingsCore(config) {
    const domain = String(config?.domain || '').trim();
    const formSelector = String(config?.formSelector || '').trim();
    const form = document.querySelector(formSelector);
    const stateOptions = {
        getColumns: typeof config?.getColumns === 'function' ? config.getColumns : null,
        form,
        tableSettingsStorageKey: String(config?.tableSettingsStorageKey || '').trim(),
        tableSettingsMetaDomain: String(config?.tableSettingsMetaDomain || '').trim(),
        getTableSettingsState: typeof config?.getTableSettingsState === 'function' ? config.getTableSettingsState : null,
    };

    if (!domain || !form) {
        return null;
    }

    const states = {
        template: loadState(domain, 'template', stateOptions),
        download: loadState(domain, 'download', stateOptions),
    };

    ensureExcelSettingsLayout(form);
    render(form, domain, states, stateOptions);
    bindEvents(form, domain, states, stateOptions);
    window.setTimeout(() => {
        states.template = loadState(domain, 'template', stateOptions);
        states.download = loadState(domain, 'download', stateOptions);
        render(form, domain, states, stateOptions);
    }, 0);

    return {
        domain,
        form,
        getState(type) {
            return states[type] || null;
        },
        getSelectedColumns(type) {
            return buildSelectedColumns(states[type] || null);
        },
        getColumnRequirement(type) {
            return isTemplateType(type) ? buildColumnRequirement(states[type] || null) : {};
        },
        reload() {
            states.template = loadState(domain, 'template', stateOptions);
            states.download = loadState(domain, 'download', stateOptions);
            render(form, domain, states, stateOptions);
        },
        destroy() {
            form.__excelSettingsCoreCleanup?.();
        },
    };
}
