import { openDataTableColumnSettings } from './dataTableColumnSettings.js';
import {
    deleteSystemUserSettingsStorage,
    ensureSystemUserSettingsStorage,
    peekSystemUserSettingsStorage,
    readSystemUserSettingsStorage,
    writeSystemUserSettingsStorage,
} from '../user-settings/systemUserSettingsStorage.js';

const SETTINGS_VERSION = 5;
const META_API_URL = '/api/settings/system/data-table-columns';
const __dtMetaCache = new Map();
const __dtMetaPending = new Map();
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
                source_ordinal_position: Number(column?.source_ordinal_position || column?.ordinal_position || index + 1) || (index + 1),
                data_type: String(column?.data_type || '').trim(),
                required: Boolean(column?.required),
                settings_visible: column?.settings_visible !== false,
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

export async function fetchDataTableMetaColumns(config = {}, options = {}) {
    const metaColumns = normalizeMetaColumns(config.metaColumns || []);
    if (metaColumns.length > 0) {
        return metaColumns;
    }

    const cacheKey = buildMetaCacheKey(config);
    const forceRefresh = options?.forceRefresh === true;
    if (!forceRefresh && cacheKey !== '' && __dtMetaCache.has(cacheKey)) {
        return __dtMetaCache.get(cacheKey);
    }
    if (!forceRefresh && cacheKey !== '' && __dtMetaPending.has(cacheKey)) {
        return __dtMetaPending.get(cacheKey);
    }

    const domain = String(config.metaDomain || '').trim();
    const rawUrl = String(config.metaUrl || '').trim();
    const requestUrl = rawUrl !== ''
        ? rawUrl
        : (domain !== '' ? `${META_API_URL}?domain=${encodeURIComponent(domain)}` : '');

    if (requestUrl === '') {
        return [];
    }

    const pending = (async () => {
        try {
            const response = await fetch(requestUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return [];
            const json = await response.json();
            const fetched = normalizeMetaColumns(json?.data || []);
            if (cacheKey !== '') __dtMetaCache.set(cacheKey, fetched);
            return fetched;
        } catch (error) {
            console.warn('[datatable-settings] meta fetch failed:', error);
            return [];
        } finally {
            if (cacheKey !== '') __dtMetaPending.delete(cacheKey);
        }
    })();
    if (cacheKey !== '') __dtMetaPending.set(cacheKey, pending);
    return pending;
}

export function getCachedDataTableMetaColumns(config = {}) {
    const inline = normalizeMetaColumns(config.metaColumns || []);
    if (inline.length > 0) return inline;
    return __dtMetaCache.get(buildMetaCacheKey(config)) || [];
}

export async function buildDataTableDefaultMetaEntries(config = {}, options = {}) {
    return (await fetchDataTableMetaColumns(config, options))
        .filter((column) => String(column?.column_type || 'physical') === 'physical')
        .map((column) => ({
            key: String(column?.key || '').trim(),
            sourceTitle: String(column?.source_title || column?.key || '').trim() || String(column?.key || '').trim(),
            title: normalizedDisplayName(
                String(column?.label || column?.key || '').trim(),
                String(column?.key || '').trim()
            ),
            displayName: normalizedDisplayName(
                String(column?.label || column?.key || '').trim(),
                String(column?.key || '').trim()
            ),
            visible: column?.settings_visible !== false,
            requirementPolicy: normalizeRequirementPolicy(
                column?.required ? COLUMN_REQUIREMENT_POLICY.REQUIRED : COLUMN_REQUIREMENT_POLICY.NONE
            ),
            ordinalPosition: Number(column?.ordinal_position || 0) || 0,
            table: String(column?.table || '').trim(),
            tableComment: String(column?.table_comment || '').trim(),
            dataType: String(column?.data_type || '').trim(),
            required: Boolean(column?.required),
        }))
        .filter((entry) => entry.key !== '');
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

        // Keep utility columns such as selection and reorder handles out of DB physical filtering.
        if (!isConfigurableColumn(column)) {
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

function buildColumnPolicyAliasMap(columns = []) {
    const aliasMap = new Map();

    (Array.isArray(columns) ? columns : []).forEach((column) => {
        const canonicalKey = String(column?.__dtSettingsKey || '').trim();
        if (canonicalKey === '') {
            return;
        }

        resolveColumnPolicyKeys(column).forEach((key) => {
            if (!aliasMap.has(key)) {
                aliasMap.set(key, canonicalKey);
            }
        });
    });

    return aliasMap;
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
        const safeColumn = column && typeof column === 'object' ? column : {};
        const key = resolveColumnKey(safeColumn, index);
        const configurable = isConfigurableColumn(safeColumn);
        const hasStableKey = key !== '';
        const physicalMeta = hasStableKey ? physicalMetaByKey.get(key) : null;
        const settingsConfigurable = hasStableKey
            && (hasPhysicalMetaColumns ? Boolean(physicalMeta) : configurable);
        const required = Boolean(physicalMeta?.required);
        const visible = settingsConfigurable
            ? (defaultVisibleSet.size > 0
                ? defaultVisibleSet.has(key)
                : safeColumn.visible !== false)
            : safeColumn.visible !== false;
        const defaultOrder = Number(
            physicalMeta?.ordinal_position
            ?? safeColumn.defaultOrder
            ?? safeColumn.settingsOrder
            ?? safeColumn.column_order
            ?? safeColumn.ordinal_position
            ?? safeColumn.ordinalPosition
            ?? (index + 1)
        );
        const defaultDisplayName = physicalMeta
            ? normalizeColumnLabel(key, String(physicalMeta.label || key))
            : normalizeColumnLabel(key, resolveColumnTitle(safeColumn, index));
        const settingsTitle = defaultDisplayName;
        const sourceTitle = String(physicalMeta?.source_title || key).trim() || key;

        return {
            ...safeColumn,
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
        columns.filter((column) => column && column.__dtSettingsConfigurable)
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
            const configuredPolicy = firstPolicyValue(requirementDefaults, resolveColumnPolicyKeys(column));
            if (column.__dtColumnKind === 'physical') {
                acc[column.__dtSettingsKey] = normalizeRequirementPolicy(
                    configuredPolicy !== undefined
                        ? configuredPolicy
                        : (column.__dtSettingsRequired ? COLUMN_REQUIREMENT_POLICY.REQUIRED : COLUMN_REQUIREMENT_POLICY.NONE)
                );
                return acc;
            }

            acc[column.__dtSettingsKey] = normalizeRequirementPolicy(configuredPolicy);
            return acc;
        }, {}),
        updatedAt: '',
    };
}

function defaultTableState(columns = [], config = {}) {
    const defaults = defaultState(columns, config);
    return {
        version: defaults.version,
        visibleColumns: [...defaults.visibleColumns],
        columnOrder: [...defaults.columnOrder],
        requiredColumns: [...defaults.requiredColumns],
        columnDisplayName: { ...(defaults.columnDisplayName || {}) },
        columnRequirementPolicy: { ...(defaults.columnRequirementPolicy || {}) },
        updatedAt: String(defaults.updatedAt || ''),
    };
}

function defaultViewState(columns = [], config = {}) {
    const defaults = defaultState(columns, config);
    return {
        version: defaults.version,
        columnWidths: { ...(defaults.columnWidths || {}) },
        currentPage: defaults.currentPage,
        searchFormExpanded: defaults.searchFormExpanded,
        searchFormState: defaults.searchFormState,
        sortSettings: Array.isArray(defaults.sortSettings) ? [...defaults.sortSettings] : [],
        pageLength: defaults.pageLength,
        updatedAt: String(defaults.updatedAt || ''),
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

function pickPersistedStateKeys(source = {}, keys = []) {
    const normalized = source && typeof source === 'object' ? source : {};
    return keys.reduce((acc, key) => {
        if (Object.prototype.hasOwnProperty.call(normalized, key)) {
            acc[key] = normalized[key];
        }
        return acc;
    }, {});
}

function buildDataTableStatePayload(state = {}, settingType = 'TABLE', options = {}) {
    const payload = pickPersistedStateKeys(
        createPersistedStatePayload(state, options),
        ['version', 'updatedAt', ...(settingType === 'VIEW'
            ? ['columnWidths', 'currentPage', 'searchFormExpanded', 'searchFormState', 'sortSettings', 'pageLength']
            : ['visibleColumns', 'columnOrder', 'columnDisplayName', 'columnRequirementPolicy'])]
    );

    if (!Object.prototype.hasOwnProperty.call(payload, 'version')) {
        payload.version = SETTINGS_VERSION;
    }
    if (!Object.prototype.hasOwnProperty.call(payload, 'updatedAt')) {
        payload.updatedAt = '';
    }

    return payload;
}

function buildUserSettingOptions(config = {}, settingType = 'TABLE') {
    return {
        settingType,
        pageKey: String(config.pageKey || '').trim(),
        metaDomain: String(config.metaDomain || '').trim(),
        userSettingPageKey: String(config.userSettingPageKey || '').trim(),
        description: String(config.description || '').trim(),
        resetOnColumnSchemaChange: config.resetOnColumnSchemaChange === true,
    };
}

function normalizeLoadedTableState(parsed = {}, defaults = {}, columns = [], resetOnColumnSchemaChange = false) {
    const orderSet = new Set(defaults.columnOrder || []);
    const parsedKeys = [
        ...(Array.isArray(parsed.columnOrder) ? parsed.columnOrder : []),
        ...(Array.isArray(parsed.visibleColumns) ? parsed.visibleColumns : []),
        ...Object.keys(parsed.columnDisplayName && typeof parsed.columnDisplayName === 'object' ? parsed.columnDisplayName : {}),
        ...Object.keys(parsed.columnRequirementPolicy && typeof parsed.columnRequirementPolicy === 'object' ? parsed.columnRequirementPolicy : {}),
    ].map((key) => String(key || '').trim()).filter(Boolean);
    if (resetOnColumnSchemaChange && parsedKeys.some((key) => !orderSet.has(key))) {
        parsed = {};
    }
    const availableKeySet = new Set(
        columns
            .map((column) => String(column.__dtSettingsKey || '').trim())
            .filter(Boolean)
    );
    const requiredSet = new Set(defaults.requiredColumns || []);
    const knownConfiguredKeys = new Set([
        ...(Array.isArray(parsed.columnOrder) ? parsed.columnOrder : []),
        ...(Array.isArray(parsed.visibleColumns) ? parsed.visibleColumns : []),
        ...Object.keys(parsed.columnDisplayName && typeof parsed.columnDisplayName === 'object' ? parsed.columnDisplayName : {}),
        ...Object.keys(parsed.columnRequirementPolicy && typeof parsed.columnRequirementPolicy === 'object' ? parsed.columnRequirementPolicy : {}),
    ].map((key) => String(key || '').trim()).filter((key) => key !== '' && orderSet.has(key)));
    const visibleSet = new Set(
        (parsed.visibleColumns || [])
            .map((key) => String(key || '').trim())
            .filter((key) => orderSet.has(key))
    );

    (defaults.requiredColumns || []).forEach((key) => visibleSet.add(key));
    (defaults.visibleColumns || []).forEach((key) => {
        if (!knownConfiguredKeys.has(key)) {
            visibleSet.add(key);
        }
    });

    const ordered = [];
    (parsed.columnOrder || []).forEach((key) => {
        const value = String(key || '').trim();
        if (orderSet.has(value) && !ordered.includes(value)) {
            ordered.push(value);
        }
    });
    (defaults.columnOrder || []).forEach((key) => {
        if (!ordered.includes(key)) {
            ordered.push(key);
        }
    });

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
        requiredColumns: Array.from(requiredSet),
        columnDisplayName,
        columnRequirementPolicy,
        updatedAt: String(parsed.updatedAt || ''),
    };
}

function normalizeLoadedViewState(parsed = {}, defaults = {}, columns = []) {
    const aliasMap = buildColumnPolicyAliasMap(columns);
    const availableKeySet = new Set(aliasMap.values());
    const sortSettings = Array.isArray(parsed.sortSettings)
        ? parsed.sortSettings
            .map((item) => ({
                key: aliasMap.get(String(item?.key || '').trim()) || String(item?.key || '').trim(),
                dir: String(item?.dir || '').trim().toLowerCase() === 'desc' ? 'desc' : 'asc',
            }))
            .filter((item) => item.key !== '')
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

    return {
        version: SETTINGS_VERSION,
        columnWidths,
        currentPage: Number.isFinite(currentPage) && currentPage >= 0 ? Math.floor(currentPage) : defaults.currentPage,
        searchFormExpanded,
        searchFormState: normalizeSearchFormState(parsed.searchFormState),
        sortSettings,
        pageLength: Number.isFinite(pageLength) && pageLength > 0 ? pageLength : defaults.pageLength,
        updatedAt: String(parsed.updatedAt || ''),
    };
}

async function loadState(config = {}, columns = [], preloaded = {}) {
    const tableDefaults = defaultTableState(columns, config);
    const viewDefaults = defaultViewState(columns, config);
    const storageKey = buildStorageKey(config);

    try {
        const [loadedTableState, loadedViewState] = await Promise.all([
            preloaded.table || readSystemUserSettingsStorage(storageKey, buildUserSettingOptions(config, 'TABLE')),
            preloaded.view || readSystemUserSettingsStorage(storageKey, buildUserSettingOptions(config, 'VIEW')),
        ]);
        const savedTableState = loadedTableState
            || await ensureSystemUserSettingsStorage(storageKey, tableDefaults, buildUserSettingOptions(config, 'TABLE')) || {};
        const savedViewState = loadedViewState
            || await ensureSystemUserSettingsStorage(storageKey, viewDefaults, buildUserSettingOptions(config, 'VIEW')) || {};

        const normalizedTableState = normalizeLoadedTableState(
            savedTableState,
            tableDefaults,
            columns,
            config.resetOnColumnSchemaChange === true
        );
        const normalizedViewState = normalizeLoadedViewState(savedViewState, viewDefaults, columns);

        return {
            tableState: hasSettingStateChanged(savedTableState, normalizedTableState, 'TABLE')
                ? saveSettingState(config, normalizedTableState, savedTableState, 'TABLE')
                : normalizedTableState,
            viewState: hasSettingStateChanged(savedViewState, normalizedViewState, 'VIEW')
                ? saveSettingState(config, normalizedViewState, savedViewState, 'VIEW')
                : normalizedViewState,
        };
    } catch (error) {
        console.warn('[datatable-settings] load failed:', error);
        return {
            tableState: tableDefaults,
            viewState: viewDefaults,
        };
    }
}

function createPersistedStatePayload(state = {}, options = {}) {
    const refreshTimestamp = options.refreshTimestamp === true;
    const existingUpdatedAt = String(state.updatedAt || '').trim();

    return {
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
        updatedAt: refreshTimestamp || existingUpdatedAt === ''
            ? new Date().toISOString()
            : existingUpdatedAt,
    };
}

function createComparableStatePayload(state = {}, settingType = 'TABLE') {
    return {
        ...buildDataTableStatePayload(state, settingType, { refreshTimestamp: false }),
        updatedAt: '',
    };
}

function hasSettingStateChanged(previousState = null, nextState = null, settingType = 'TABLE') {
    if (!previousState || !nextState) {
        return true;
    }

    return JSON.stringify(createComparableStatePayload(previousState, settingType))
        !== JSON.stringify(createComparableStatePayload(nextState, settingType));
}

function saveSettingState(config = {}, state = {}, previousState = null, settingType = 'TABLE') {
    const storageKey = buildStorageKey(config);
    if (!hasSettingStateChanged(previousState, state, settingType)) {
        return buildDataTableStatePayload(previousState, settingType, { refreshTimestamp: false });
    }

    const payload = buildDataTableStatePayload(state, settingType, { refreshTimestamp: true });
    const savedState = writeSystemUserSettingsStorage(
        storageKey,
        payload,
        buildUserSettingOptions(config, settingType)
    );

    return savedState ?? payload;
}

export function updateDataTableTableState(context = null, patch = {}) {
    if (!context?.config) {
        return null;
    }

    const nextState = {
        ...(context.tableState || {}),
        ...(patch || {}),
        version: SETTINGS_VERSION,
    };
    context.tableState = saveSettingState(context.config, nextState, context.tableState, 'TABLE');
    return context.tableState;
}

export function updateDataTableViewState(context = null, patch = {}) {
    if (!context?.config) {
        return null;
    }

    const nextState = {
        ...(context.viewState || {}),
        ...(patch || {}),
        version: SETTINGS_VERSION,
    };
    context.viewState = saveSettingState(context.config, nextState, context.viewState, 'VIEW');
    return context.viewState;
}

function reorderConfigurableColumns(columns = [], columnOrder = []) {
    const orderMap = new Map();
    columnOrder.forEach((key, index) => {
        orderMap.set(key, index);
    });

    const configurableColumns = columns.filter((column) => column && column.__dtSettingsConfigurable);
    const sortedConfigurable = configurableColumns.slice().sort((left, right) => {
        const leftIsAction = String(left?.__dtSettingsKey || '') === '__actions';
        const rightIsAction = String(right?.__dtSettingsKey || '') === '__actions';
        if (leftIsAction !== rightIsAction) {
            return leftIsAction ? 1 : -1;
        }

        const leftIndex = orderMap.has(left.__dtSettingsKey) ? orderMap.get(left.__dtSettingsKey) : Number.MAX_SAFE_INTEGER;
        const rightIndex = orderMap.has(right.__dtSettingsKey) ? orderMap.get(right.__dtSettingsKey) : Number.MAX_SAFE_INTEGER;
        return leftIndex - rightIndex;
    });

    let configurableIndex = 0;
    return columns.map((column) => {
        if (!column || typeof column !== 'object') {
            return {};
        }
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
        if (!column || typeof column !== 'object') {
            return {};
        }
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
        if (!column || typeof column !== 'object') {
            return {};
        }
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

function moveActionColumnsToEnd(columns = []) {
    const leadingColumns = [];
    const actionColumns = [];

    columns.forEach((column) => {
        const copiedColumn = { ...column };
        if (String(copiedColumn?.__dtSettingsKey || '').trim() === '__actions') {
            actionColumns.push(copiedColumn);
            return;
        }

        leadingColumns.push(copiedColumn);
    });

    return [...leadingColumns, ...actionColumns];
}

function moveColumnsImmediatelyBeforeActions(columns = [], keys = []) {
    const trailingKeys = new Set((Array.isArray(keys) ? keys : [])
        .map((key) => String(key || '').trim())
        .filter(Boolean));
    if (trailingKeys.size === 0) {
        return moveActionColumnsToEnd(columns);
    }

    const leadingColumns = [];
    const trailingColumns = [];
    const actionColumns = [];
    columns.forEach((column) => {
        const copiedColumn = { ...column };
        const key = String(copiedColumn?.__dtSettingsKey || copiedColumn?.settingsKey || '').trim();
        if (key === '__actions') {
            actionColumns.push(copiedColumn);
        } else if (trailingKeys.has(key)) {
            trailingColumns.push(copiedColumn);
        } else {
            leadingColumns.push(copiedColumn);
        }
    });
    return [...leadingColumns, ...trailingColumns, ...actionColumns];
}

function currentEntries(context = {}) {
    const visibleSet = new Set(context.tableState?.visibleColumns || []);

    return (context.appliedColumns || [])
        .filter((column) => column && typeof column === 'object')
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
    const visibleSet = new Set(context.tableState?.visibleColumns || []);
    const actualColumns = Array.isArray(context.tableColumns) && context.tableColumns.length > 0
        ? context.tableColumns
        : (context.appliedColumns || []);

    actualColumns.forEach((column, index) => {
        if (!column || typeof column !== 'object') {
            return;
        }
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

async function buildPreparedSettingsState(context = {}, options = {}) {
    const baseConfig = context.config || {};
    const metaColumns = await fetchDataTableMetaColumns(baseConfig, {
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
    const defaults = defaultTableState(normalizedColumns, baseConfig);
    const appliedColumns = moveColumnsImmediatelyBeforeActions(
        applyColumnPolicies(
            applyVisibility(
                reorderConfigurableColumns(normalizedColumns, defaults.columnOrder),
                defaults.visibleColumns,
                defaults.requiredColumns
            ),
            defaults
        ),
        baseConfig.columnsImmediatelyBeforeActions
    );

    return {
        defaults,
        metaColumns: metaColumns.map((column) => ({ ...column })),
        originalColumns: normalizedColumns.map((column) => ({ ...column })),
        appliedColumns: appliedColumns.map((column) => ({ ...column })),
    };
}

async function openSettings(table, context = {}) {
    const preparedDefaults = await buildPreparedSettingsState(context);
    context.metaColumns = preparedDefaults.metaColumns;
    context.originalColumns = preparedDefaults.originalColumns;
    const defaults = preparedDefaults.defaults;

    openDataTableColumnSettings({
        title: buildSettingsTitle(context),
        subtitle: buildSettingsSubtitle(context),
        entries: currentEntries(context),
        defaultEntries: currentEntries({
            ...context,
            tableState: defaults,
            appliedColumns: preparedDefaults.appliedColumns,
        }),
        async restoreDefaults() {
            await deleteSystemUserSettingsStorage(context.storageKey, buildUserSettingOptions(context.config, 'TABLE'));
            const restored = await buildPreparedSettingsState(context, { forceRefresh: true });
            context.metaColumns = restored.metaColumns;
            context.originalColumns = restored.originalColumns;
            return currentEntries({
                ...context,
                tableState: restored.defaults,
                appliedColumns: restored.appliedColumns,
            });
        },
        onSave(entries) {
            const existingRequiredColumns = Array.isArray(context.tableState?.requiredColumns)
                ? [...context.tableState.requiredColumns]
                : [];
            const previousState = {
                ...(context.tableState || {}),
                visibleColumns: Array.isArray(context.tableState?.visibleColumns) ? [...context.tableState.visibleColumns] : [],
                columnOrder: Array.isArray(context.tableState?.columnOrder) ? [...context.tableState.columnOrder] : [],
                requiredColumns: existingRequiredColumns,
            };
            const nextState = {
                ...context.tableState,
                version: SETTINGS_VERSION,
                visibleColumns: entries.filter((entry) => entry.visible || entry.required).map((entry) => entry.key),
                columnOrder: entries.map((entry) => entry.key),
                requiredColumns: existingRequiredColumns,
                columnDisplayName: entries.reduce((acc, entry) => {
                    acc[entry.key] = normalizedDisplayName(entry.displayName, entry.title || entry.key);
                    return acc;
                }, {}),
                columnRequirementPolicy: entries.reduce((acc, entry) => {
                    acc[entry.key] = normalizeRequirementPolicy(entry.requirementPolicy);
                    return acc;
                }, {}),
            };

            const requiresReapply = orderChanged(context.tableState.columnOrder, nextState.columnOrder)
                || mapChanged(context.tableState.columnDisplayName, nextState.columnDisplayName);
            context.tableState = saveSettingState(context.config, nextState, context.tableState, 'TABLE');
            context.appliedColumns = moveColumnsImmediatelyBeforeActions(
                applyColumnPolicies(applyVisibility(
                    reorderConfigurableColumns(context.originalColumns || [], context.tableState.columnOrder),
                    context.tableState.visibleColumns,
                    context.tableState.requiredColumns
                ), context.tableState),
                context.config?.columnsImmediatelyBeforeActions
            ).map((column) => ({ ...column }));
            dispatchDataTableSettingsUpdated(context);

            if (requiresReapply) {
                const nextAppliedState = {
                    ...context.tableState,
                    visibleColumns: [...context.tableState.visibleColumns],
                    columnOrder: [...context.tableState.columnOrder],
                    requiredColumns: Array.isArray(context.tableState?.requiredColumns) ? [...context.tableState.requiredColumns] : [],
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

export async function prepareDataTableSettingsColumns(columns = [], config = null) {
    const enabled = Boolean(config?.enabled);
    if (!enabled) {
        return {
            columns,
            context: null,
        };
    }

    const storageKey = buildStorageKey(config);
    const preloaded = {
        table: readSystemUserSettingsStorage(storageKey, buildUserSettingOptions(config, 'TABLE')),
        view: readSystemUserSettingsStorage(storageKey, buildUserSettingOptions(config, 'VIEW')),
    };
    const metaColumns = await fetchDataTableMetaColumns(config);
    const filteredColumns = filterColumnsByPhysicalMeta(columns, metaColumns);
    const sourceColumns = metaColumns.length > 0
        ? [...filteredColumns, ...buildMissingPhysicalColumns(filteredColumns, metaColumns)]
        : filteredColumns;
    const normalizedColumns = normalizeColumnDefinitions(sourceColumns, config, metaColumns);
    const loadedState = await loadState(config, normalizedColumns, preloaded);
    const tableState = loadedState.tableState;
    const viewState = loadedState.viewState;
    const orderedColumns = reorderConfigurableColumns(normalizedColumns, tableState.columnOrder);
    const appliedColumns = moveColumnsImmediatelyBeforeActions(
        applyColumnPolicies(
            applyVisibility(orderedColumns, tableState.visibleColumns, tableState.requiredColumns),
            tableState
        ),
        config.columnsImmediatelyBeforeActions
    );

    return {
        columns: appliedColumns.map((column) => ({ ...column })),
        context: {
            config,
            tableKey: String(config.tableKey || '').trim(),
            tableLabel: String(config.tableLabel || '').trim(),
            title: String(config.title || '').trim(),
            storageKey: buildStorageKey(config),
            tableState,
            viewState,
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
        getTableState: () => ({ ...(context.tableState || {}) }),
        getViewState: () => ({ ...(context.viewState || {}) }),
        updateTableState: (patch = {}) => updateDataTableTableState(context, patch),
        updateViewState: (patch = {}) => updateDataTableViewState(context, patch),
        open: () => openSettings(table, context),
        applyState: null,
    };

    ensureSettingsButton(table, context);
    normalizeSettingsTriggerUi(table);
}

export function readDataTableSettingsState(storageKey = '', options = {}) {
    const normalizedKey = String(storageKey || '').trim();
    if (normalizedKey === '') {
        return null;
    }

    const resolvedKey = normalizedKey.startsWith('datatable.settings.')
        ? normalizedKey
        : `datatable.settings.${normalizedKey}`;

    return peekSystemUserSettingsStorage(resolvedKey, {
        ...options,
        settingType: 'TABLE',
    });
}

export async function loadDataTableSettingsState(storageKey = '', options = {}) {
    const normalizedKey = String(storageKey || '').trim();
    if (normalizedKey === '') return null;
    const resolvedKey = normalizedKey.startsWith('datatable.settings.')
        ? normalizedKey
        : `datatable.settings.${normalizedKey}`;
    return readSystemUserSettingsStorage(resolvedKey, { ...options, settingType: 'TABLE' });
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

export async function openStandaloneDataTableSettings(config = {}) {
    const storageKey = String(config.storageKey || '').trim();
    const settingsOptions = buildUserSettingOptions(config, 'TABLE');
    const defaults = await buildDataTableDefaultMetaEntries(config, { forceRefresh: config.forceRefresh === true });
    const saved = await loadDataTableSettingsState(storageKey, settingsOptions) || {};
    const entries = defaults.map(entry => ({
        ...entry,
        visible: Array.isArray(saved.visibleColumns) ? saved.visibleColumns.includes(entry.key) : entry.visible,
        displayName: normalizedDisplayName(saved.columnDisplayName?.[entry.key], entry.displayName),
        requirementPolicy: normalizeRequirementPolicy(
            saved.columnRequirementPolicy?.[entry.key] ?? entry.requirementPolicy
        ),
    }));

    return openDataTableColumnSettings({
        title: String(config.title || '테이블 설정'),
        subtitle: String(config.subtitle || '컬럼 표시, 순서, 사용컬럼명, 필수구분을 설정합니다.'),
        entries,
        defaultEntries: defaults,
        async restoreDefaults() {
            await deleteSystemUserSettingsStorage(storageKey, settingsOptions);
            return buildDataTableDefaultMetaEntries(config, { forceRefresh: true });
        },
        onSave(nextEntries) {
            const nextState = {
                version: SETTINGS_VERSION,
                updatedAt: new Date().toISOString(),
                visibleColumns: nextEntries.filter(entry => entry.visible).map(entry => entry.key),
                columnOrder: nextEntries.map(entry => entry.key),
                columnDisplayName: Object.fromEntries(nextEntries.map(entry => [
                    entry.key, normalizedDisplayName(entry.displayName, entry.title || entry.key),
                ])),
                columnRequirementPolicy: Object.fromEntries(nextEntries.map(entry => [
                    entry.key, normalizeRequirementPolicy(entry.requirementPolicy),
                ])),
            };
            writeSystemUserSettingsStorage(
                storageKey,
                buildDataTableStatePayload(nextState, 'TABLE'),
                settingsOptions
            );
            dispatchDataTableSettingsUpdated({
                storageKey,
                tableKey: String(config.tableKey || '').trim(),
                tableLabel: String(config.tableLabel || '').trim(),
                config,
            });
            notify('success', '테이블 설정이 저장되었습니다.');
        },
    });
}
