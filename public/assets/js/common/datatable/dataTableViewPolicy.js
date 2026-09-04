export const DATA_TABLE_PAGE_LENGTH_OPTIONS = Object.freeze([
    100, 200, 300, 500, 1000, 2000, 3000, 5000, 10000,
]);

export const DATA_TABLE_COLUMN_WIDTH_MIN = 32;
export const DATA_TABLE_COLUMN_WIDTH_MAX = 2000;
export const DATA_TABLE_COLUMN_WIDTH_DEFAULT = 120;

export function resolveDataTableColumnWidthKey(column = {}) {
    return String(
        column?.__dtSettingsKey
        || column?.key
        || column?.settingsKey
        || column?.name
        || (typeof column?.data === 'string' ? column.data : '')
        || ''
    ).trim();
}

export function isDataTableColumnWidthResizable(column = {}) {
    const key = resolveDataTableColumnWidthKey(column);
    if (key === '' || column?.widthResizable === false) return false;
    if (column?.widthResizable === true) return true;
    if (column?.isSelectionColumn === true) return false;
    return !['__select', '__reorder', '__actions'].includes(key);
}

export function normalizeDataTableColumnWidth(value, options = {}) {
    if (value === '' || value === null || value === undefined) return null;
    const width = Number(value);
    if (!Number.isFinite(width)) return Number.NaN;

    const minimum = Math.max(
        DATA_TABLE_COLUMN_WIDTH_MIN,
        Number(options?.minimum) || DATA_TABLE_COLUMN_WIDTH_MIN
    );
    const maximum = Math.max(
        minimum,
        Number(options?.maximum) || DATA_TABLE_COLUMN_WIDTH_MAX
    );
    if (width < minimum || width > maximum) return Number.NaN;
    return Math.round(width);
}

export function normalizeDataTablePageLength(value, fallback = null) {
    const normalized = Number(value);
    return DATA_TABLE_PAGE_LENGTH_OPTIONS.includes(normalized)
        ? normalized
        : fallback;
}
