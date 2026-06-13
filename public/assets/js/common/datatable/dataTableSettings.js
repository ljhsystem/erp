import { openDataTableColumnSettings } from './dataTableColumnSettings.js';

const SETTINGS_VERSION = 4;

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function notify(type, message) {
    if (window.AppCore?.notify) {
        window.AppCore.notify(type, message);
        return;
    }

    const method = type === 'error' ? 'error' : (type === 'warning' ? 'warn' : 'log');
    console[method](message);
}

function tokenizeClasses(...values) {
    return values
        .flatMap((value) => String(value || '').split(/\s+/))
        .map((token) => token.trim())
        .filter(Boolean);
}

function resolveColumnKey(column = {}, index = 0) {
    if (typeof column.key === 'string' && column.key.trim() !== '') {
        return column.key.trim();
    }
    if (typeof column.settingsKey === 'string' && column.settingsKey.trim() !== '') {
        return column.settingsKey.trim();
    }
    if (typeof column.name === 'string' && column.name.trim() !== '') {
        return column.name.trim();
    }
    if (typeof column.data === 'string' && column.data.trim() !== '') {
        return column.data.trim();
    }
    return '';
}

function resolveColumnTitle(column = {}, index = 0) {
    if (typeof column.settingsTitle === 'string' && column.settingsTitle.trim() !== '') {
        return column.settingsTitle.trim();
    }

    const title = String(column.title || '').replace(/<[^>]*>/g, '').trim();
    return title || `컬럼 ${index + 1}`;
}

function isConfigurableColumn(column = {}) {
    const classes = tokenizeClasses(column.className, column.headerClassName);
    return !classes.includes('no-colvis');
}

function buildStorageKey(config = {}) {
    if (String(config.storageKey || '').trim() !== '') {
        const rawKey = String(config.storageKey).trim();
        return rawKey.startsWith('datatable.settings.')
            ? rawKey
            : `datatable.settings.${rawKey}`;
    }

    const pageKey = String(config.pageKey || 'datatable').trim();
    const tableKey = String(config.tableKey || 'table').trim();
    return `datatable.settings.${pageKey}.${tableKey}.v${SETTINGS_VERSION}`;
}

function normalizeColumnDefinitions(columns = [], config = {}) {
    const requiredSet = new Set((config.requiredColumns || []).map((key) => String(key || '').trim()).filter(Boolean));
    const defaultVisibleSet = new Set((config.defaultVisibleColumns || []).map((key) => String(key || '').trim()).filter(Boolean));

    return columns.map((column, index) => {
        const key = resolveColumnKey(column, index);
        const configurable = isConfigurableColumn(column);
        const hasStableKey = key !== '';
        const settingsConfigurable = configurable && hasStableKey;
        const required = settingsConfigurable && requiredSet.has(key);
        const visible = required
            ? true
            : (defaultVisibleSet.size > 0
                ? defaultVisibleSet.has(key)
                : column.visible !== false);

        return {
            ...column,
            key,
            settingsTitle: resolveColumnTitle(column, index),
            __dtSettingsKey: key,
            __dtSettingsConfigurable: settingsConfigurable,
            __dtSettingsRequired: required,
            visible,
        };
    });
}

function defaultState(columns = [], config = {}) {
    const configurableColumns = columns.filter((column) => column.__dtSettingsConfigurable);
    const requiredColumns = configurableColumns
        .filter((column) => column.__dtSettingsRequired)
        .map((column) => column.__dtSettingsKey);
    const normalizedPageLength = Number(config.pageLength);

    return {
        version: SETTINGS_VERSION,
        visibleColumns: configurableColumns
            .filter((column) => column.visible !== false || column.__dtSettingsRequired)
            .map((column) => column.__dtSettingsKey),
        columnOrder: configurableColumns.map((column) => column.__dtSettingsKey),
        columnWidths: {},
        searchFormExpanded: null,
        sortSettings: Array.isArray(config.defaultSortSettings) ? config.defaultSortSettings : [],
        pageLength: Number.isFinite(normalizedPageLength) && normalizedPageLength > 0 ? normalizedPageLength : null,
        requiredColumns: requiredColumns.length > 0
            ? requiredColumns
            : (config.requiredColumns || []).map((key) => String(key || '').trim()).filter(Boolean),
        updatedAt: '',
    };
}

function loadState(config = {}, columns = []) {
    const defaults = defaultState(columns, config);
    const storageKey = buildStorageKey(config);

    try {
        const raw = window.localStorage?.getItem(storageKey);
        if (!raw) {
            return defaults;
        }

        const parsed = JSON.parse(raw);
        const orderSet = new Set(defaults.columnOrder);
        const availableKeySet = new Set(
            columns
                .map((column) => String(column.__dtSettingsKey || '').trim())
                .filter(Boolean)
        );
        const requiredSet = new Set(defaults.requiredColumns);
        const visibleSet = new Set(
            (parsed.visibleColumns || [])
                .map((key) => String(key || '').trim())
                .filter((key) => orderSet.has(key))
        );

        defaults.requiredColumns.forEach((key) => visibleSet.add(key));

        const ordered = [];
        (parsed.columnOrder || []).forEach((key) => {
            const value = String(key || '').trim();
            if (orderSet.has(value) && !ordered.includes(value)) {
                ordered.push(value);
            }
        });
        defaults.columnOrder.forEach((key) => {
            if (!ordered.includes(key)) {
                ordered.push(key);
            }
        });

        const sortSettings = Array.isArray(parsed.sortSettings)
            ? parsed.sortSettings
                .map((item) => ({
                    key: String(item?.key || '').trim(),
                    dir: String(item?.dir || '').trim().toLowerCase() === 'desc' ? 'desc' : 'asc',
                }))
                .filter((item) => item.key !== '' && availableKeySet.has(item.key))
            : defaults.sortSettings;
        const columnWidths = parsed.columnWidths && typeof parsed.columnWidths === 'object'
            ? Object.entries(parsed.columnWidths).reduce((acc, [key, value]) => {
                const columnKey = String(key || '').trim();
                const widthPx = Number(value);
                if (!columnKey || !availableKeySet.has(columnKey)) {
                    return acc;
                }
                if (!Number.isFinite(widthPx) || widthPx <= 0) {
                    return acc;
                }
                acc[columnKey] = Math.round(widthPx);
                return acc;
            }, {})
            : {};

        const pageLength = Number(parsed.pageLength);
        const searchFormExpanded = typeof parsed.searchFormExpanded === 'boolean'
            ? parsed.searchFormExpanded
            : defaults.searchFormExpanded;

        return {
            version: SETTINGS_VERSION,
            visibleColumns: Array.from(visibleSet),
            columnOrder: ordered,
            columnWidths,
            searchFormExpanded,
            sortSettings,
            pageLength: Number.isFinite(pageLength) && pageLength > 0 ? pageLength : defaults.pageLength,
            requiredColumns: Array.from(requiredSet),
            updatedAt: String(parsed.updatedAt || ''),
        };
    } catch (error) {
        console.warn('[datatable-settings] load failed:', error);
        return defaults;
    }
}

function saveState(config = {}, state = {}) {
    const storageKey = buildStorageKey(config);
    const payload = {
        version: SETTINGS_VERSION,
        visibleColumns: Array.isArray(state.visibleColumns) ? state.visibleColumns : [],
        columnOrder: Array.isArray(state.columnOrder) ? state.columnOrder : [],
        columnWidths: state.columnWidths && typeof state.columnWidths === 'object'
            ? Object.entries(state.columnWidths).reduce((acc, [key, value]) => {
                const columnKey = String(key || '').trim();
                const widthPx = Number(value);
                if (!columnKey || !Number.isFinite(widthPx) || widthPx <= 0) {
                    return acc;
                }
                acc[columnKey] = Math.round(widthPx);
                return acc;
            }, {})
            : {},
        searchFormExpanded: typeof state.searchFormExpanded === 'boolean'
            ? state.searchFormExpanded
            : null,
        sortSettings: Array.isArray(state.sortSettings) ? state.sortSettings : [],
        pageLength: Number.isFinite(Number(state.pageLength)) && Number(state.pageLength) > 0
            ? Number(state.pageLength)
            : null,
        requiredColumns: Array.isArray(state.requiredColumns) ? state.requiredColumns : [],
        updatedAt: new Date().toISOString(),
    };

    window.localStorage?.setItem(storageKey, JSON.stringify(payload));
    return payload;
}

export function updateDataTableSettingsState(context = null, patch = {}) {
    if (!context?.config) {
        return null;
    }

    const nextState = {
        ...(context.state || {}),
        ...(patch || {}),
        version: SETTINGS_VERSION,
    };
    context.state = saveState(context.config, nextState);
    return context.state;
}

function reorderConfigurableColumns(columns = [], columnOrder = []) {
    const orderMap = new Map();
    columnOrder.forEach((key, index) => {
        orderMap.set(key, index);
    });

    const configurableColumns = columns.filter((column) => column.__dtSettingsConfigurable);
    const sortedConfigurable = configurableColumns.slice().sort((left, right) => {
        const leftIndex = orderMap.has(left.__dtSettingsKey) ? orderMap.get(left.__dtSettingsKey) : Number.MAX_SAFE_INTEGER;
        const rightIndex = orderMap.has(right.__dtSettingsKey) ? orderMap.get(right.__dtSettingsKey) : Number.MAX_SAFE_INTEGER;
        return leftIndex - rightIndex;
    });

    let configurableIndex = 0;
    return columns.map((column) => {
        if (!column.__dtSettingsConfigurable) {
            return { ...column };
        }

        const next = sortedConfigurable[configurableIndex++] || column;
        return { ...next };
    });
}

function applyVisibility(columns = [], visibleColumns = [], requiredColumns = []) {
    const visibleSet = new Set(visibleColumns);
    const requiredSet = new Set(requiredColumns);

    return columns.map((column) => {
        if (!column.__dtSettingsConfigurable) {
            return { ...column };
        }

        const key = column.__dtSettingsKey;
        return {
            ...column,
            visible: requiredSet.has(key) ? true : visibleSet.has(key),
        };
    });
}

function currentEntries(context = {}) {
    const visibleSet = new Set(context.state?.visibleColumns || []);
    const requiredSet = new Set(context.state?.requiredColumns || []);

    return (context.appliedColumns || [])
        .filter((column) => column.__dtSettingsConfigurable)
        .map((column) => ({
            key: column.__dtSettingsKey,
            title: column.settingsTitle || column.__dtSettingsKey,
            visible: requiredSet.has(column.__dtSettingsKey) ? true : visibleSet.has(column.__dtSettingsKey),
            required: requiredSet.has(column.__dtSettingsKey),
        }));
}

function applyVisibilityToTable(table, context = {}) {
    const visibleSet = new Set(context.state?.visibleColumns || []);
    const requiredSet = new Set(context.state?.requiredColumns || []);
    const actualColumns = Array.isArray(context.tableColumns) && context.tableColumns.length > 0
        ? context.tableColumns
        : (context.appliedColumns || []);

    actualColumns.forEach((column, index) => {
        if (!column.__dtSettingsConfigurable) {
            return;
        }

        const shouldShow = requiredSet.has(column.__dtSettingsKey) || visibleSet.has(column.__dtSettingsKey);
        table.column(index).visible(shouldShow, false);
    });

    if (table?.__dtTableSettings?.refreshLayout) {
        table.__dtTableSettings.refreshLayout({ draw: true });
        return;
    }

    table.columns.adjust().draw(false);
}

function orderChanged(currentOrder = [], nextOrder = []) {
    if (currentOrder.length !== nextOrder.length) {
        return true;
    }

    return currentOrder.some((key, index) => key !== nextOrder[index]);
}

function openSettings(table, context = {}) {
    const defaults = defaultState(context.originalColumns || [], context.config || {});
    const tableLabel = context.tableLabel || context.tableKey || '테이블';

    openDataTableColumnSettings({
        title: context.title || '테이블 설정',
        subtitle: `${tableLabel} 컬럼 표시와 순서를 설정합니다.`,
        entries: currentEntries(context),
        defaultEntries: currentEntries({
            ...context,
            state: defaults,
            appliedColumns: applyVisibility(
                reorderConfigurableColumns(context.originalColumns || [], defaults.columnOrder),
                defaults.visibleColumns,
                defaults.requiredColumns
            ),
        }),
        onSave(entries) {
            const nextState = {
                ...context.state,
                version: SETTINGS_VERSION,
                visibleColumns: entries.filter((entry) => entry.visible || entry.required).map((entry) => entry.key),
                columnOrder: entries.map((entry) => entry.key),
                requiredColumns: context.state.requiredColumns.slice(),
            };

            const requiresReload = orderChanged(context.state.columnOrder, nextState.columnOrder);
            context.state = saveState(context.config, nextState);

            if (requiresReload) {
                notify('success', '테이블 설정이 저장되었습니다. 새 컬럼 순서를 적용하기 위해 화면을 다시 불러옵니다.');
                window.location.reload();
                return;
            }

            applyVisibilityToTable(table, context);
            notify('success', '테이블 설정이 저장되었습니다.');
        },
    });
}

function ensureSettingsButton(table, context = {}) {
    const wrapper = table.table().container();
    const toolbar = wrapper?.querySelector('.dt-top');
    if (!toolbar) {
        return;
    }

    const trigger = toolbar.querySelector('.dt-table-settings-trigger');
    if (trigger) {
        return;
    }

    let button = toolbar.querySelector('.dt-table-settings-btn');
    if (!button) {
        button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary btn-sm dt-table-settings-btn';
        button.innerHTML = '<i class="bi bi-sliders me-1"></i>설정';
        const lengthNode = toolbar.querySelector('.dataTables_length');
        if (lengthNode?.parentNode === toolbar) {
            toolbar.insertBefore(button, lengthNode);
        } else {
            toolbar.appendChild(button);
        }
    }

    if (button.dataset.dtSettingsBound === 'true') {
        return;
    }

    button.dataset.dtSettingsBound = 'true';
    button.addEventListener('click', () => openSettings(table, context));
}

export function prepareDataTableSettingsColumns(columns = [], config = null) {
    const enabled = Boolean(config?.enabled);
    if (!enabled) {
        return {
            columns,
            context: null,
        };
    }

    const normalizedColumns = normalizeColumnDefinitions(columns, config);
    const state = loadState(config, normalizedColumns);
    const orderedColumns = reorderConfigurableColumns(normalizedColumns, state.columnOrder);
    const appliedColumns = applyVisibility(orderedColumns, state.visibleColumns, state.requiredColumns);

    return {
        columns: appliedColumns.map((column) => ({ ...column })),
        context: {
            config,
            tableKey: String(config.tableKey || '').trim(),
            tableLabel: String(config.tableLabel || '').trim(),
            title: String(config.title || '테이블 설정'),
            storageKey: buildStorageKey(config),
            state,
            originalColumns: normalizedColumns.map((column) => ({ ...column })),
            appliedColumns: appliedColumns.map((column) => ({ ...column })),
        },
    };
}

export function attachDataTableSettings(table, context = null) {
    if (!table || !context?.config?.enabled) {
        return;
    }

    table.__dtTableSettings = {
        storageKey: context.storageKey,
        getState: () => ({ ...context.state }),
        updateState: (patch = {}) => updateDataTableSettingsState(context, patch),
        open: () => openSettings(table, context),
    };

    ensureSettingsButton(table, context);
}
