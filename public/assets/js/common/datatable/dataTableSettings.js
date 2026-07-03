import { openDataTableColumnSettings } from './dataTableColumnSettings.js';

const SETTINGS_VERSION = 5;
const META_API_URL = '/api/settings/system/data-table-columns';
const __dtMetaCache = new Map();
const COLUMN_LABEL_OVERRIDES = {
    sort_no: '\uC21C\uBC88',
};
const COLUMN_REQUIREMENT_POLICY = Object.freeze({
    NONE: 'none',
    OPTIONAL: 'optional',
    REQUIRED: 'required',
});

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

function dispatchDataTableSettingsUpdated(context = {}) {
    document.dispatchEvent(new CustomEvent('datatable-settings:updated', {
        detail: {
            storageKey: String(context?.storageKey || '').trim(),
            metaDomain: String(context?.config?.metaDomain || '').trim(),
            tableKey: String(context?.tableKey || '').trim(),
            tableLabel: String(context?.tableLabel || '').trim(),
            updatedAt: new Date().toISOString(),
        },
    }));
}

function tokenizeClasses(...values) {
    return values
        .flatMap((value) => String(value || '').split(/\s+/))
        .map((token) => token.trim())
        .filter(Boolean);
}

function normalizeColumnLabel(key = '', label = '') {
    const normalizedKey = String(key || '').trim();
    const normalizedLabel = String(label || '').trim();
    if (normalizedLabel !== '') {
        return normalizedLabel;
    }

    if (COLUMN_LABEL_OVERRIDES[normalizedKey]) {
        return COLUMN_LABEL_OVERRIDES[normalizedKey];
    }

    return normalizedKey;
}

function normalizeRequirementPolicy(value = '') {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === COLUMN_REQUIREMENT_POLICY.OPTIONAL) return COLUMN_REQUIREMENT_POLICY.OPTIONAL;
    if (normalized === COLUMN_REQUIREMENT_POLICY.REQUIRED) return COLUMN_REQUIREMENT_POLICY.REQUIRED;
    return COLUMN_REQUIREMENT_POLICY.NONE;
}

function normalizedDisplayName(value = '', fallback = '') {
    const text = String(value || '').trim();
    return text !== '' ? text : String(fallback || '').trim();
}

function normalizeMetaColumns(metaColumns = []) {
    return (Array.isArray(metaColumns) ? metaColumns : [])
        .map((column, index) => {
            const key = String(column?.key || column?.value || '').trim();
            if (!key) {
                return null;
            }

            return {
                key,
                label: normalizeColumnLabel(
                    key,
                    String(column?.label || column?.title || key).trim() || key
                ),
                source_title: String(column?.source_title || column?.sourceTitle || key).trim() || key,
                table: String(column?.table || column?.table_name || '').trim(),
                table_comment: String(column?.table_comment || column?.tableComment || '').trim(),
                ordinal_position: Number(column?.ordinal_position || column?.settings_order || index + 1) || (index + 1),
                data_type: String(column?.data_type || '').trim(),
                required: Boolean(column?.required),
                column_type: String(column?.column_type || 'physical').trim() || 'physical',
            };
        })
        .filter(Boolean)
        .sort((left, right) => {
            if (left.ordinal_position !== right.ordinal_position) {
                return left.ordinal_position - right.ordinal_position;
            }

            return left.key.localeCompare(right.key, 'en');
        });
}

function buildMetaCacheKey(config = {}) {
    const explicit = String(config.metaCacheKey || '').trim();
    if (explicit !== '') {
        return explicit;
    }

    const domain = String(config.metaDomain || '').trim();
    if (domain !== '') {
        return `domain:${domain}`;
    }

    const url = String(config.metaUrl || '').trim();
    if (url !== '') {
        return `url:${url}`;
    }

    return '';
}

export function fetchDataTableMetaColumnsSync(config = {}, options = {}) {
    const metaColumns = normalizeMetaColumns(config.metaColumns || []);
    if (metaColumns.length > 0) {
        return metaColumns;
    }

    const cacheKey = buildMetaCacheKey(config);
    const forceRefresh = options?.forceRefresh === true;
    if (!forceRefresh && cacheKey !== '' && __dtMetaCache.has(cacheKey)) {
        return __dtMetaCache.get(cacheKey);
    }

    const domain = String(config.metaDomain || '').trim();
    const rawUrl = String(config.metaUrl || '').trim();
    const requestUrl = rawUrl !== ''
        ? rawUrl
        : (domain !== '' ? `${META_API_URL}?domain=${encodeURIComponent(domain)}` : '');

    if (requestUrl === '') {
        return [];
    }

    try {
        const request = new XMLHttpRequest();
        request.open('GET', requestUrl, false);
        request.setRequestHeader('Accept', 'application/json');
        request.send(null);

        if (request.status < 200 || request.status >= 300) {
            return [];
        }

        const json = JSON.parse(request.responseText || '{}');
        const fetched = normalizeMetaColumns(json?.data || []);
        if (cacheKey !== '') {
            __dtMetaCache.set(cacheKey, fetched);
        }
        return fetched;
    } catch (error) {
        console.warn('[datatable-settings] meta fetch failed:', error);
        return [];
    }
}

function buildPhysicalMetaMap(metaColumns = []) {
    return new Map(
        metaColumns
            .filter((column) => String(column?.column_type || 'physical') === 'physical')
            .map((column) => [column.key, column])
    );
}

function buildMissingPhysicalColumns(columns = [], metaColumns = []) {
    const usedKeys = new Set(
        (Array.isArray(columns) ? columns : [])
            .map((column, index) => resolveColumnKey(column, index))
            .filter(Boolean)
    );

    return metaColumns
        .filter((column) => !usedKeys.has(column.key))
        .map((column) => ({
            key: column.key,
            data: column.key,
            title: normalizeColumnLabel(column.key, column.label),
            visible: false,
            defaultContent: '',
            settingsOrder: column.ordinal_position,
            ordinal_position: column.ordinal_position,
        }));
}

function filterColumnsByPhysicalMeta(columns = [], metaColumns = []) {
    if (!Array.isArray(columns) || metaColumns.length === 0) {
        return Array.isArray(columns) ? columns : [];
    }

    const physicalMetaKeys = new Set(
        metaColumns
            .filter((column) => String(column?.column_type || 'physical').trim() === 'physical')
            .map((column) => String(column?.key || '').trim())
            .filter(Boolean)
    );

    return columns.filter((column, index) => {
        const key = resolveColumnKey(column, index);
        if (key === '') {
            return true;
        }

        const columnKind = String(column?.__dtColumnKind || '').trim().toLowerCase();
        if (columnKind === 'virtual') {
            return true;
        }
        if (columnKind === 'physical') {
            return physicalMetaKeys.has(key);
        }

        const looksLikePhysicalColumn = String(column?.defaultContent ?? '') === ''
            && (
                String(column?.data || '').trim() === key
                || String(column?.name || '').trim() === key
                || String(column?.settingsKey || '').trim() === key
                || String(column?.sourceField || '').trim() === key
            );

        if (!looksLikePhysicalColumn) {
            return true;
        }

        return physicalMetaKeys.has(key);
    });
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
    return title || `\uCEEC\uB7FC ${index + 1}`;
}

function resolveDefaultColumnOrderValue(column = {}, index = 0) {
    const candidates = [
        column.defaultOrder,
        column.settingsOrder,
        column.column_order,
        column.ordinal_position,
        column.ordinalPosition,
    ];

    for (const candidate of candidates) {
        const value = Number(candidate);
        if (Number.isFinite(value) && value > 0) {
            return value;
        }
    }

    return index + 1;
}

function resolveColumnPolicyKeys(column = {}) {
    return Array.from(new Set([
        column.__dtSettingsKey,
        column.key,
        column.settingsKey,
        column.sourceField,
        column.system_field_name,
        column.original_column_key,
        column.name,
        column.data,
    ].map((value) => String(value || '').trim()).filter(Boolean)));
}

function firstPolicyValue(map = {}, keys = []) {
    for (const key of keys) {
        if (Object.prototype.hasOwnProperty.call(map, key)) {
            return map[key];
        }
    }

    return undefined;
}

function sortColumnsByDefaultOrder(columns = []) {
    return [...columns]
        .map((column, index) => ({
            column,
            index,
            order: resolveDefaultColumnOrderValue(column, index),
        }))
        .sort((left, right) => {
            if (left.order !== right.order) {
                return left.order - right.order;
            }

            return left.index - right.index;
        })
        .map(({ column }) => column);
}

function composeDisplayColumnTitle(title = '', key = '') {
    const normalizedTitle = String(title || '').trim();
    const normalizedKey = String(key || '').trim();
    if (normalizedKey === '' || normalizedTitle === normalizedKey) {
        return normalizedTitle || normalizedKey;
    }

    return `${normalizedTitle} (${normalizedKey})`;
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

function normalizeColumnDefinitions(columns = [], config = {}, metaColumns = []) {
    const defaultVisibleSet = new Set((config.defaultVisibleColumns || []).map((key) => String(key || '').trim()).filter(Boolean));
    const physicalMetaByKey = buildPhysicalMetaMap(metaColumns);
    const hasPhysicalMetaColumns = metaColumns.length > 0;

    return columns.map((column, index) => {
        const key = resolveColumnKey(column, index);
        const configurable = isConfigurableColumn(column);
        const hasStableKey = key !== '';
        const physicalMeta = hasStableKey ? physicalMetaByKey.get(key) : null;
        const settingsConfigurable = configurable && hasStableKey;
        const required = false;
        const visible = settingsConfigurable
            ? (defaultVisibleSet.size > 0
                ? defaultVisibleSet.has(key)
                : column.visible !== false)
            : column.visible !== false;
        const defaultOrder = Number(
            physicalMeta?.ordinal_position
            ?? column.defaultOrder
            ?? column.settingsOrder
            ?? column.column_order
            ?? column.ordinal_position
            ?? column.ordinalPosition
            ?? (index + 1)
        );
        const defaultDisplayName = physicalMeta
            ? normalizeColumnLabel(key, String(physicalMeta.label || key))
            : normalizeColumnLabel(key, resolveColumnTitle(column, index));
        const settingsTitle = defaultDisplayName;
        const sourceTitle = String(physicalMeta?.source_title || key).trim() || key;

        return {
            ...column,
            key,
            settingsTitle,
            __dtDefaultDisplayName: defaultDisplayName,
            __dtSourceTitle: sourceTitle,
            __dtSettingsKey: key,
            __dtSettingsConfigurable: settingsConfigurable,
            __dtSettingsRequired: required,
            __dtColumnKind: physicalMeta ? 'physical' : 'virtual',
            defaultOrder,
            settingsOrder: defaultOrder,
            ordinal_position: defaultOrder,
            visible,
        };
    });
}

function defaultState(columns = [], config = {}) {
    const configurableColumns = sortColumnsByDefaultOrder(
        columns.filter((column) => column.__dtSettingsConfigurable)
    );
    const normalizedPageLength = Number(config.pageLength);
    const displayNameDefaults = config.defaultColumnDisplayName && typeof config.defaultColumnDisplayName === 'object'
        ? config.defaultColumnDisplayName
        : {};
    const requirementDefaults = config.defaultColumnRequirementPolicy && typeof config.defaultColumnRequirementPolicy === 'object'
        ? config.defaultColumnRequirementPolicy
        : {};

    return {
        version: SETTINGS_VERSION,
        visibleColumns: configurableColumns
            .filter((column) => column.visible !== false)
            .map((column) => column.__dtSettingsKey),
        columnOrder: configurableColumns.map((column) => column.__dtSettingsKey),
        columnWidths: {},
        currentPage: 0,
        searchFormExpanded: null,
        searchFormState: null,
        sortSettings: Array.isArray(config.defaultSortSettings) ? config.defaultSortSettings : [],
        pageLength: Number.isFinite(normalizedPageLength) && normalizedPageLength > 0 ? normalizedPageLength : null,
        requiredColumns: [],
        columnDisplayName: configurableColumns.reduce((acc, column) => {
            const defaultDisplayName = column.__dtDefaultDisplayName || column.settingsTitle || column.__dtSettingsKey;
            const configuredDefault = column.__dtColumnKind === 'physical'
                ? undefined
                : firstPolicyValue(displayNameDefaults, resolveColumnPolicyKeys(column));
            const configured = normalizedDisplayName(
                configuredDefault,
                defaultDisplayName
            );
            acc[column.__dtSettingsKey] = configured;
            return acc;
        }, {}),
        columnRequirementPolicy: configurableColumns.reduce((acc, column) => {
            acc[column.__dtSettingsKey] = normalizeRequirementPolicy(
                firstPolicyValue(requirementDefaults, resolveColumnPolicyKeys(column))
            );
            return acc;
        }, {}),
        updatedAt: '',
    };
}

function normalizeSearchFormState(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return null;
    }

    const conditions = Array.isArray(value.conditions)
        ? value.conditions
            .map((condition) => ({
                field: String(condition?.field || '').trim(),
                value: String(condition?.value || '').trim(),
            }))
            .filter((condition) => condition.field !== '' && condition.value !== '')
        : [];
    const dateType = String(value.dateType || '').trim();
    const dateStart = String(value.dateStart || '').trim();
    const dateEnd = String(value.dateEnd || '').trim();

    if (conditions.length === 0 && dateType === '' && dateStart === '' && dateEnd === '') {
        return null;
    }

    return {
        conditions,
        dateType,
        dateStart,
        dateEnd,
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
        const currentPage = Number(parsed.currentPage);
        const searchFormExpanded = typeof parsed.searchFormExpanded === 'boolean'
            ? parsed.searchFormExpanded
            : defaults.searchFormExpanded;
        const searchFormState = normalizeSearchFormState(parsed.searchFormState);
        const columnDisplayName = { ...(defaults.columnDisplayName || {}) };
        if (parsed.columnDisplayName && typeof parsed.columnDisplayName === 'object') {
            Object.entries(parsed.columnDisplayName).forEach(([key, value]) => {
                const normalizedKey = String(key || '').trim();
                if (!normalizedKey || !availableKeySet.has(normalizedKey)) {
                    return;
                }
                columnDisplayName[normalizedKey] = normalizedDisplayName(value, columnDisplayName[normalizedKey] || normalizedKey);
            });
        }
        const columnRequirementPolicy = { ...(defaults.columnRequirementPolicy || {}) };
        if (parsed.columnRequirementPolicy && typeof parsed.columnRequirementPolicy === 'object') {
            Object.entries(parsed.columnRequirementPolicy).forEach(([key, value]) => {
                const normalizedKey = String(key || '').trim();
                if (!normalizedKey || !availableKeySet.has(normalizedKey)) {
                    return;
                }
                columnRequirementPolicy[normalizedKey] = normalizeRequirementPolicy(value);
            });
        }

        return {
            version: SETTINGS_VERSION,
            visibleColumns: Array.from(visibleSet),
            columnOrder: ordered,
            columnWidths,
            currentPage: Number.isFinite(currentPage) && currentPage >= 0 ? Math.floor(currentPage) : defaults.currentPage,
            searchFormExpanded,
            searchFormState,
            sortSettings,
            pageLength: Number.isFinite(pageLength) && pageLength > 0 ? pageLength : defaults.pageLength,
            requiredColumns: Array.from(requiredSet),
            columnDisplayName,
            columnRequirementPolicy,
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
        currentPage: Number.isFinite(Number(state.currentPage)) && Number(state.currentPage) >= 0
            ? Math.floor(Number(state.currentPage))
            : 0,
        searchFormExpanded: typeof state.searchFormExpanded === 'boolean'
            ? state.searchFormExpanded
            : null,
        searchFormState: normalizeSearchFormState(state.searchFormState),
        sortSettings: Array.isArray(state.sortSettings) ? state.sortSettings : [],
        pageLength: Number.isFinite(Number(state.pageLength)) && Number(state.pageLength) > 0
            ? Number(state.pageLength)
            : null,
        requiredColumns: Array.isArray(state.requiredColumns) ? state.requiredColumns : [],
        columnDisplayName: state.columnDisplayName && typeof state.columnDisplayName === 'object'
            ? Object.entries(state.columnDisplayName).reduce((acc, [key, value]) => {
                const normalizedKey = String(key || '').trim();
                if (!normalizedKey) {
                    return acc;
                }
                acc[normalizedKey] = normalizedDisplayName(value, normalizedKey);
                return acc;
            }, {})
            : {},
        columnRequirementPolicy: state.columnRequirementPolicy && typeof state.columnRequirementPolicy === 'object'
            ? Object.entries(state.columnRequirementPolicy).reduce((acc, [key, value]) => {
                const normalizedKey = String(key || '').trim();
                if (!normalizedKey) {
                    return acc;
                }
                acc[normalizedKey] = normalizeRequirementPolicy(value);
                return acc;
            }, {})
            : {},
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

    return columns.map((column) => {
        if (!column.__dtSettingsConfigurable) {
            return { ...column };
        }

        const key = column.__dtSettingsKey;
        return {
            ...column,
            visible: visibleSet.has(key),
        };
    });
}

function applyColumnPolicies(columns = [], state = {}) {
    const displayNameMap = state?.columnDisplayName && typeof state.columnDisplayName === 'object'
        ? state.columnDisplayName
        : {};
    const requirementPolicyMap = state?.columnRequirementPolicy && typeof state.columnRequirementPolicy === 'object'
        ? state.columnRequirementPolicy
        : {};

    return columns.map((column) => {
        if (!column.__dtSettingsConfigurable) {
            return { ...column };
        }

        const key = String(column.__dtSettingsKey || '').trim();
        const displayName = normalizedDisplayName(
            displayNameMap[key],
            column.__dtDefaultDisplayName || column.settingsTitle || column.title || key
        );
        const requirementPolicy = normalizeRequirementPolicy(requirementPolicyMap[key]);

        return {
            ...column,
            title: displayName || key,
            settingsTitle: displayName || key,
            __dtDisplayName: displayName || key,
            __dtRequirementPolicy: requirementPolicy,
        };
    });
}

function currentEntries(context = {}) {
    const visibleSet = new Set(context.state?.visibleColumns || []);

    return (context.appliedColumns || [])
        .filter((column) => column.__dtSettingsConfigurable)
        .map((column) => ({
            key: column.__dtSettingsKey,
            sourceTitle: column.__dtSourceTitle || column.__dtSettingsKey,
            title: column.__dtDisplayName || column.__dtDefaultDisplayName || column.settingsTitle || column.__dtSettingsKey,
            visible: visibleSet.has(column.__dtSettingsKey),
            displayName: column.__dtDisplayName || column.__dtDefaultDisplayName || column.settingsTitle || column.__dtSettingsKey,
            requirementPolicy: normalizeRequirementPolicy(column.__dtRequirementPolicy),
            required: false,
        }));
}

export function applyVisibilityToTable(table, context = {}) {
    const visibleSet = new Set(context.state?.visibleColumns || []);
    const actualColumns = Array.isArray(context.tableColumns) && context.tableColumns.length > 0
        ? context.tableColumns
        : (context.appliedColumns || []);

    actualColumns.forEach((column, index) => {
        if (!column.__dtSettingsConfigurable) {
            return;
        }

        const shouldShow = visibleSet.has(column.__dtSettingsKey);
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

function mapChanged(currentMap = {}, nextMap = {}) {
    const currentKeys = Object.keys(currentMap || {});
    const nextKeys = Object.keys(nextMap || {});
    if (currentKeys.length !== nextKeys.length) {
        return true;
    }

    const keys = new Set([...currentKeys, ...nextKeys]);
    for (const key of keys) {
        if (String(currentMap?.[key] ?? '') !== String(nextMap?.[key] ?? '')) {
            return true;
        }
    }

    return false;
}

function buildSettingsTitle(context = {}) {
    const explicitTitle = String(context.title || '').trim();
    if (explicitTitle !== '') {
        return explicitTitle;
    }

    const tableLabel = String(
        context.tableLabel
        || context.tableKey
        || '\uD14C\uC774\uBE14'
    ).trim() || '\uD14C\uC774\uBE14';
    return `${tableLabel} \uD14C\uC774\uBE14 \uC124\uC815`;
}

function buildSettingsSubtitle(context = {}) {
    const tableSummaries = Array.from(new Set(
        (Array.isArray(context.metaColumns) ? context.metaColumns : [])
            .map((column) => {
                const tableName = String(column?.table || '').trim();
                if (tableName === '') {
                    return '';
                }

                const tableComment = String(column?.table_comment || '').trim();
                return tableComment !== ''
                    ? `${tableComment} (${tableName})`
                    : tableName;
            })
            .filter(Boolean)
    ));

    if (tableSummaries.length > 0) {
        return tableSummaries.join(', ');
    }

    const tableLabel = String(
        context.tableLabel
        || context.tableKey
        || '\uD14C\uC774\uBE14'
    ).trim() || '\uD14C\uC774\uBE14';
    return `${tableLabel} \uCEEC\uB7FC \uD45C\uC2DC\uC640 \uC21C\uC11C\uB97C \uC124\uC815\uD569\uB2C8\uB2E4.`;
}

function buildPreparedSettingsState(context = {}, options = {}) {
    const baseConfig = context.config || {};
    const metaColumns = fetchDataTableMetaColumnsSync(baseConfig, {
        forceRefresh: options.forceRefresh === true,
    });
    const baseOriginalColumns = Array.isArray(context.originalColumns)
        ? context.originalColumns
        : [];
    const filteredOriginalColumns = filterColumnsByPhysicalMeta(baseOriginalColumns, metaColumns);
    const baseColumnsWithoutInjectedPhysical = filteredOriginalColumns.filter((column) => (
        !(
            column?.__dtColumnKind === 'physical'
            && column?.defaultContent === ''
            && column?.visible === false
        )
    ));
    const sourceColumns = metaColumns.length > 0
        ? [...baseColumnsWithoutInjectedPhysical, ...buildMissingPhysicalColumns(baseColumnsWithoutInjectedPhysical, metaColumns)]
        : baseColumnsWithoutInjectedPhysical;
    const normalizedColumns = normalizeColumnDefinitions(sourceColumns, baseConfig, metaColumns);
    const defaults = defaultState(normalizedColumns, baseConfig);
    const appliedColumns = applyColumnPolicies(
        applyVisibility(
            reorderConfigurableColumns(normalizedColumns, defaults.columnOrder),
            defaults.visibleColumns,
            defaults.requiredColumns
        ),
        defaults
    );

    return {
        defaults,
        metaColumns: metaColumns.map((column) => ({ ...column })),
        originalColumns: normalizedColumns.map((column) => ({ ...column })),
        appliedColumns: appliedColumns.map((column) => ({ ...column })),
    };
}

function openSettings(table, context = {}) {
    const preparedDefaults = buildPreparedSettingsState(context);
    context.metaColumns = preparedDefaults.metaColumns;
    context.originalColumns = preparedDefaults.originalColumns;
    const defaults = preparedDefaults.defaults;

    openDataTableColumnSettings({
        title: buildSettingsTitle(context),
        subtitle: buildSettingsSubtitle(context),
        entries: currentEntries(context),
        defaultEntries: currentEntries({
            ...context,
            state: defaults,
            appliedColumns: preparedDefaults.appliedColumns,
        }),
        restoreDefaults() {
            const restored = buildPreparedSettingsState(context, { forceRefresh: true });
            context.metaColumns = restored.metaColumns;
            context.originalColumns = restored.originalColumns;
            return currentEntries({
                ...context,
                state: restored.defaults,
                appliedColumns: restored.appliedColumns,
            });
        },
        onSave(entries) {
            const previousState = {
                ...(context.state || {}),
                visibleColumns: Array.isArray(context.state?.visibleColumns) ? [...context.state.visibleColumns] : [],
                columnOrder: Array.isArray(context.state?.columnOrder) ? [...context.state.columnOrder] : [],
                requiredColumns: Array.isArray(context.state?.requiredColumns) ? [...context.state.requiredColumns] : [],
            };
            const nextState = {
                ...context.state,
                version: SETTINGS_VERSION,
                visibleColumns: entries.filter((entry) => entry.visible || entry.required).map((entry) => entry.key),
                columnOrder: entries.map((entry) => entry.key),
                requiredColumns: context.state.requiredColumns.slice(),
                columnDisplayName: entries.reduce((acc, entry) => {
                    acc[entry.key] = normalizedDisplayName(entry.displayName, entry.title || entry.key);
                    return acc;
                }, {}),
                columnRequirementPolicy: entries.reduce((acc, entry) => {
                    acc[entry.key] = normalizeRequirementPolicy(entry.requirementPolicy);
                    return acc;
                }, {}),
            };

            const requiresReapply = orderChanged(context.state.columnOrder, nextState.columnOrder)
                || mapChanged(context.state.columnDisplayName, nextState.columnDisplayName);
            context.state = saveState(context.config, nextState);
            context.appliedColumns = applyColumnPolicies(applyVisibility(
                reorderConfigurableColumns(context.originalColumns || [], context.state.columnOrder),
                context.state.visibleColumns,
                context.state.requiredColumns
            ), context.state).map((column) => ({ ...column }));
            dispatchDataTableSettingsUpdated(context);

            if (requiresReapply) {
                const nextAppliedState = {
                    ...context.state,
                    visibleColumns: [...context.state.visibleColumns],
                    columnOrder: [...context.state.columnOrder],
                    requiredColumns: [...context.state.requiredColumns],
                };

                try {
                    const handled = table?.__dtTableSettings?.applyState?.({
                        table,
                        context,
                        previousState,
                        nextState: nextAppliedState,
                    });
                    if (handled !== false) {
                        notify('success', '\uD14C\uC774\uBE14 \uC124\uC815\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
                        return;
                    }
                } catch (error) {
                    console.warn('[datatable-settings] state apply failed:', error);
                }

                try {
                    const handled = context.config?.onOrderChange?.({
                        table,
                        context,
                        previousState,
                        nextState: nextAppliedState,
                    });
                    if (handled !== false) {
                        notify('success', '\uD14C\uC774\uBE14 \uC124\uC815\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
                        return;
                    }
                } catch (error) {
                    console.warn('[datatable-settings] order change apply failed:', error);
                }
                notify('success', '\uD14C\uC774\uBE14 \uC124\uC815\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4. \uCEEC\uB7FC \uC21C\uC11C\uB97C \uC801\uC6A9\uD558\uAE30 \uC704\uD574 \uD654\uBA74\uC744 \uB2E4\uC2DC \uBD88\uB7EC\uC635\uB2C8\uB2E4.');
                window.location.reload();
                return;
            }

            applyVisibilityToTable(table, context);
            notify('success', '\uD14C\uC774\uBE14 \uC124\uC815\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
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
        button.innerHTML = '<i class="bi bi-sliders me-1"></i>\uC124\uC815';
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

function normalizeSettingsTriggerUi(table) {
    const wrapper = table?.table?.().container?.();
    if (!wrapper) {
        return;
    }

    wrapper.querySelectorAll('.dt-table-settings-trigger, .dt-table-settings-btn').forEach((button) => {
        if (!(button instanceof HTMLElement)) {
            return;
        }
        button.innerHTML = '<i class="bi bi-gear" aria-hidden="true"></i>';
        button.setAttribute('aria-label', '\uD14C\uC774\uBE14 \uC124\uC815');
        button.setAttribute('title', '\uD14C\uC774\uBE14 \uC124\uC815');
    });
}

export function prepareDataTableSettingsColumns(columns = [], config = null) {
    const enabled = Boolean(config?.enabled);
    if (!enabled) {
        return {
            columns,
            context: null,
        };
    }

    const metaColumns = fetchDataTableMetaColumnsSync(config);
    const filteredColumns = filterColumnsByPhysicalMeta(columns, metaColumns);
    const sourceColumns = metaColumns.length > 0
        ? [...filteredColumns, ...buildMissingPhysicalColumns(filteredColumns, metaColumns)]
        : filteredColumns;
    const normalizedColumns = normalizeColumnDefinitions(sourceColumns, config, metaColumns);
    const state = loadState(config, normalizedColumns);
    const orderedColumns = reorderConfigurableColumns(normalizedColumns, state.columnOrder);
    const appliedColumns = applyColumnPolicies(
        applyVisibility(orderedColumns, state.visibleColumns, state.requiredColumns),
        state
    );

    return {
        columns: appliedColumns.map((column) => ({ ...column })),
        context: {
            config,
            tableKey: String(config.tableKey || '').trim(),
            tableLabel: String(config.tableLabel || '').trim(),
            title: String(config.title || '').trim(),
            storageKey: buildStorageKey(config),
            state,
            metaColumns: metaColumns.map((column) => ({ ...column })),
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
        applyState: null,
    };

    ensureSettingsButton(table, context);
    normalizeSettingsTriggerUi(table);
}

export function readDataTableSettingsState(storageKey = '') {
    const normalizedKey = String(storageKey || '').trim();
    if (normalizedKey === '') {
        return null;
    }

    try {
        const raw = window.localStorage?.getItem(
            normalizedKey.startsWith('datatable.settings.')
                ? normalizedKey
                : `datatable.settings.${normalizedKey}`
        );
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
        return null;
    }
}

export function resolveDataTableColumnDisplayName(column = {}, stateOrStorageKey = null, fallback = '') {
    const state = typeof stateOrStorageKey === 'string'
        ? readDataTableSettingsState(stateOrStorageKey)
        : stateOrStorageKey;
    const displayNameMap = state?.columnDisplayName && typeof state.columnDisplayName === 'object'
        ? state.columnDisplayName
        : {};

    return normalizedDisplayName(
        firstPolicyValue(displayNameMap, resolveColumnPolicyKeys(column)),
        fallback || column.__dtDefaultDisplayName || column.settingsTitle || column.title || column.label || column.key || ''
    );
}

export function resolveDataTableColumnRequirementPolicy(column = {}, stateOrStorageKey = null) {
    const state = typeof stateOrStorageKey === 'string'
        ? readDataTableSettingsState(stateOrStorageKey)
        : stateOrStorageKey;
    const requirementPolicyMap = state?.columnRequirementPolicy && typeof state.columnRequirementPolicy === 'object'
        ? state.columnRequirementPolicy
        : {};

    return normalizeRequirementPolicy(
        firstPolicyValue(requirementPolicyMap, resolveColumnPolicyKeys(column))
    );
}
