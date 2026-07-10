import {
    buildExcelColumnSettingsKey,
    buildExcelDownloadColumns,
    buildExcelTemplateColumns,
} from '../../common/column-meta/index.js';
import { deleteSystemUserSettingsStorage } from '../../common/user-settings/systemUserSettingsStorage.js';
import {
    buildDataTableDefaultMetaEntries,
} from '../../common/datatable/dataTableSettings.js';
import { loadExcelSettings, saveExcelSettings } from './storage.js';
import {
    ensureExcelSettingsLayout,
    renderExcelSettingsPanel,
    resetExcelSettingsUi,
    toggleExcelSettingsRoot,
    toggleExcelSettingsPanel,
} from './ui.js';

const STORAGE_VERSION = 4;
const REQUIREMENT = Object.freeze({
    HIDDEN: 'hidden',
    OPTIONAL: 'optional',
    REQUIRED: 'required',
});
const REQUIREMENT_POLICY = Object.freeze({
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

function normalizeRequirementPolicy(value, fallback = REQUIREMENT_POLICY.NONE) {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === REQUIREMENT_POLICY.REQUIRED) return REQUIREMENT_POLICY.REQUIRED;
    if (normalized === REQUIREMENT_POLICY.OPTIONAL) return REQUIREMENT_POLICY.OPTIONAL;
    if (normalized === REQUIREMENT_POLICY.NONE) return REQUIREMENT_POLICY.NONE;
    return fallback;
}

function resolveMetaConfig(domain, options = {}) {
    return {
        metaDomain: String(
            options?.metaDomain
            || domain
        ).trim(),
    };
}

function buildExcelColumnsFromMeta(domain, type, options = {}) {
    const metaEntries = buildDataTableDefaultMetaEntries(
        resolveMetaConfig(domain, options),
        { forceRefresh: options?.forceRefresh === true }
    );

    return metaEntries.map((entry) => ({
        key: entry.key,
        label: entry.displayName || entry.key,
        sourceLabel: entry.sourceTitle || entry.key,
        displayLabel: entry.displayName || entry.key,
        combinedLabel: (entry.displayName && entry.sourceTitle)
            ? `${entry.displayName}(${entry.sourceTitle})`
            : (entry.displayName || entry.sourceTitle || entry.key),
        required: entry.requirementPolicy === REQUIREMENT_POLICY.REQUIRED,
        defaultSelected: entry.visible === true,
        defaultRequirementPolicy: normalizeRequirementPolicy(entry.requirementPolicy),
        metaRequirementPolicy: normalizeRequirementPolicy(entry.requirementPolicy),
        type: entry.dataType || 'text',
        width: null,
        table: entry.table || '',
        ordinal_position: entry.ordinalPosition,
    }));
}

function getDefaultColumns(domain, type, options = {}) {
    const metaColumns = buildExcelColumnsFromMeta(domain, type, options);
    if (metaColumns.length > 0) {
        return metaColumns;
    }

    const columns = isTemplateType(type)
        ? buildExcelTemplateColumns(domain)
        : buildExcelDownloadColumns(domain);
    return columns.map((column) => ({
        ...column,
        label: String(column?.label || column?.key || '').trim(),
        sourceLabel: String(column?.key || column?.label || '').trim(),
        displayLabel: String(column?.displayLabel || column?.label || column?.key || '').trim(),
        combinedLabel: String(column?.combinedLabel || column?.displayLabel || column?.label || column?.key || '').trim(),
        ...(isTemplateType(type)
            ? { requirementPolicy: normalizeRequirementPolicy(column?.requirementPolicy) }
            : {}),
    }));
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

function requirementFromPolicy(policy = '', visible = true) {
    const normalizedPolicy = normalizeRequirementPolicy(policy);
    if (normalizedPolicy === REQUIREMENT_POLICY.REQUIRED) {
        return REQUIREMENT.REQUIRED;
    }

    return visible ? REQUIREMENT.OPTIONAL : REQUIREMENT.HIDDEN;
}

function isDefaultRequiredColumn(column) {
    return column?.required === true;
}

function isSystemRequiredColumn(column) {
    return column?.systemRequired === true;
}

function defaultRequirement(column, type) {
    return column.defaultSelected === true
        ? REQUIREMENT.OPTIONAL
        : REQUIREMENT.HIDDEN;
}

function defaultRequirementPolicy(column, type) {
    if (!isTemplateType(type)) {
        return undefined;
    }

    return normalizeRequirementPolicy(
        column?.defaultRequirementPolicy,
        isDefaultRequiredColumn(column)
            ? REQUIREMENT_POLICY.REQUIRED
            : REQUIREMENT_POLICY.NONE
    );
}

function createDefaultState(domain, type, options = {}) {
    const columns = getDefaultColumns(domain, type, options);
    const columnOrder = columns.map((column) => column.key);
    const visibleColumns = [];
    const columnRequirementPolicy = {};
    const states = columns.map((column, index) => {
        const visible = column.defaultSelected === true;
        const requirementPolicy = defaultRequirementPolicy(column, type);
        const requirement = isTemplateType(type)
            ? requirementFromPolicy(requirementPolicy, visible)
            : defaultRequirement(column, type);
        if (visible) {
            visibleColumns.push(column.key);
        }
        if (isTemplateType(type)) {
            columnRequirementPolicy[column.key] = normalizeRequirementPolicy(requirementPolicy);
        }

        const baseColumnState = {
            key: column.key,
            label: column.label,
            sourceLabel: column.sourceLabel || column.label,
            displayLabel: column.displayLabel || column.label,
            combinedLabel: column.combinedLabel || column.label,
            metaRequirementPolicy: normalizeRequirementPolicy(
                column.metaRequirementPolicy,
                requirementPolicy
            ),
            systemRequired: isSystemRequiredColumn(column),
            visible,
            order: index + 1,
        };

        if (isTemplateType(type)) {
            return {
                ...baseColumnState,
                requirement,
                requirementPolicy: normalizeRequirementPolicy(requirementPolicy),
            };
        }

        return baseColumnState;
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
        payload.columnRequirementPolicy = columnRequirementPolicy;
    }

    return payload;
}

function buildColumnState(defaultColumns, columnOrder, type, visibleSet, columnRequirementPolicy) {
    const orderMap = new Map(columnOrder.map((key, index) => [key, index]));

    return defaultColumns
        .map((column, index) => {
            const isVisible = visibleSet.has(column.key);
            const requirementPolicy = isTemplateType(type)
                ? normalizeRequirementPolicy(
                    columnRequirementPolicy[column.key],
                    defaultRequirementPolicy(column, type)
                )
                : undefined;
            const requirement = isTemplateType(type)
                ? requirementFromPolicy(requirementPolicy, isVisible)
                : (isVisible ? REQUIREMENT.OPTIONAL : REQUIREMENT.HIDDEN);

            const baseColumnState = {
                key: column.key,
                label: column.label,
                sourceLabel: column.sourceLabel || column.label,
                displayLabel: column.displayLabel || column.label,
                combinedLabel: column.combinedLabel || column.label,
                metaRequirementPolicy: normalizeRequirementPolicy(
                    column.metaRequirementPolicy,
                    defaultRequirementPolicy(column, 'template')
                ),
                systemRequired: isSystemRequiredColumn(column),
                visible: isVisible,
                order: orderMap.has(column.key) ? Number(orderMap.get(column.key)) + 1 : index + 1,
            };

            if (isTemplateType(type)) {
                return {
                    ...baseColumnState,
                    requirement,
                    requirementPolicy,
                };
            }

            return baseColumnState;
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
    const visibleColumns = Array.isArray(state?.visibleColumns)
        ? state.visibleColumns.map((key) => String(key || '').trim()).filter(Boolean)
        : defaults.visibleColumns;
    const visibleSet = new Set(visibleColumns);
    const columnOrder = (() => {
        const savedOrder = Array.isArray(state?.columnOrder) && state.columnOrder.length > 0
            ? state.columnOrder
            : defaults.columnOrder;
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

    const columnRequirementPolicy = {};
    defaultColumns.forEach((column) => {
        const key = column.key;

        if (!isTemplateType(type)) {
            return;
        }

        if (state?.columnRequirementPolicy && typeof state.columnRequirementPolicy === 'object' && Object.prototype.hasOwnProperty.call(state.columnRequirementPolicy, key)) {
            columnRequirementPolicy[key] = normalizeRequirementPolicy(state.columnRequirementPolicy[key]);
            return;
        }

        columnRequirementPolicy[key] = defaultRequirementPolicy(column, type);
    });

    const columns = buildColumnState(defaultColumns, columnOrder, type, visibleSet, columnRequirementPolicy);
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
        normalized.columnRequirementPolicy = columns.reduce((accumulator, column) => {
            accumulator[column.key] = normalizeRequirementPolicy(column.requirementPolicy);
            return accumulator;
        }, {});
    }

    return normalized;
}

function settingsKey(domain, type) {
    return buildExcelColumnSettingsKey(domain, type);
}

function buildStorageOptions(domain, type, options = {}) {
    return {
        domain,
        metaDomain: String(options?.metaDomain || '').trim(),
        userSettingPageKey: String(options?.userSettingPageKey || '').trim(),
        description: String(options?.description || '').trim(),
        settingType: isTemplateType(type) ? 'EXCEL_UPLOAD' : 'EXCEL_DOWNLOAD',
    };
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
        return !state.columnRequirementPolicy
            || typeof state.columnRequirementPolicy !== 'object';
    }

    return Object.prototype.hasOwnProperty.call(state, 'columnRequirementPolicy');
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
        payload.columnRequirementPolicy = { ...(state.columnRequirementPolicy || {}) };
    }

    return payload;
}

function loadState(domain, type, options = {}) {
    const fallback = serializeState(type, createDefaultState(domain, type, options));
    const key = settingsKey(domain, type);
    const loaded = loadExcelSettings(key, fallback, buildStorageOptions(domain, type, options));
    const normalized = normalizeState(domain, type, loaded, options);

    if (needsStateMigration(type, loaded)) {
        saveExcelSettings(key, serializeState(type, normalized), buildStorageOptions(domain, type, options));
    }

    return normalized;
}

function persistState(domain, type, state, options = {}) {
    const syncedState = syncStateSnapshot(type, state);
    const normalized = normalizeState(domain, type, syncedState, options);
    const payload = saveExcelSettings(
        settingsKey(domain, type),
        serializeState(type, normalized),
        buildStorageOptions(domain, type, options)
    );
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
        const isVisible = column?.visible !== false;
        const requirementPolicy = isTemplateType(type)
            ? normalizeRequirementPolicy(column?.requirementPolicy)
            : undefined;
        const requirement = isTemplateType(type)
            ? requirementFromPolicy(requirementPolicy, isVisible)
            : (isVisible ? REQUIREMENT.OPTIONAL : REQUIREMENT.HIDDEN);

        const normalizedColumn = {
            ...column,
            requirement,
            visible: isVisible,
            order: index + 1,
        };

        if (isTemplateType(type)) {
            normalizedColumn.requirementPolicy = requirementPolicy;
        } else {
            delete normalizedColumn.requirementPolicy;
        }

        return normalizedColumn;
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
        nextState.columnRequirementPolicy = normalizedColumns.reduce((accumulator, column) => {
            accumulator[column.key] = normalizeRequirementPolicy(column.requirementPolicy);
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

            return {
                ...column,
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

            return {
                ...column,
                requirementPolicy: normalizeRequirementPolicy(requirement),
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
            accumulator[column.key] = normalizeRequirementPolicy(column.requirementPolicy);
            return accumulator;
        }, {});
}

function setVisibilityAll(type, state, visible) {
    const columns = Array.isArray(state?.columns) ? state.columns : [];
    return syncStateSnapshot(type, {
        ...state,
        columns: columns.map((column) => ({
            ...column,
            visible,
        })),
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
                acc.requirementPolicy[column.key] = normalizeRequirementPolicy(column.requirementPolicy);
                return acc;
            }, { displayName: {}, requirementPolicy: {} }),
        download: (Array.isArray(states?.download?.columns) ? states.download.columns : [])
            .filter((column) => column.visible !== false)
            .reduce((acc, column) => {
                acc.displayName[column.key] = String(column.displayLabel || column.label || column.key || '').trim();
                return acc;
            }, { displayName: {} }),
    };
    delete form.__excelPreparedRequirement;
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
            deleteSystemUserSettingsStorage(
                settingsKey(domain, type),
                buildStorageOptions(domain, type, options)
            );
            states[type] = loadState(domain, type, {
                ...options,
                forceRefresh: true,
            });
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
        userSettingPageKey: String(config?.userSettingPageKey || '').trim(),
        metaDomain: String(config?.metaDomain || '').trim(),
        description: String(config?.description || '').trim(),
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
