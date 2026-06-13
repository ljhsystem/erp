import { COLUMN_META_DEFAULTS } from './defaults.js';

function assertColumnKey(key) {
    if (typeof key !== 'string' || key.trim() === '') {
        throw new Error('ColumnMetaRegistry column key is required.');
    }
}

export function defineColumnMeta(meta = {}) {
    const key = String(meta.key ?? '').trim();
    assertColumnKey(key);

    return Object.freeze({
        ...COLUMN_META_DEFAULTS,
        ...meta,
        key,
        label: String(meta.label ?? COLUMN_META_DEFAULTS.label),
        width: Number(meta.width ?? COLUMN_META_DEFAULTS.width),
    });
}

export function defineDomainRegistry(domain, columns = []) {
    const resolvedDomain = String(domain ?? '').trim();
    if (resolvedDomain === '') {
        throw new Error('ColumnMetaRegistry domain is required.');
    }

    const resolvedColumns = columns.map((column) => defineColumnMeta(column));
    return Object.freeze({
        domain: resolvedDomain,
        columns: Object.freeze(resolvedColumns),
    });
}

export function createColumnMap(columns = []) {
    return Object.freeze(
        columns.reduce((map, column) => {
            map[column.key] = column;
            return map;
        }, {})
    );
}

export function filterTableColumns(columns = [], options = {}) {
    const visibleOnly = options.visibleOnly === true;
    return columns.filter((column) => (visibleOnly ? column.tableVisible : true));
}

export function filterExcelTemplateColumns(columns = []) {
    return columns.filter((column) => column.excelTemplate);
}

export function filterExcelDownloadColumns(columns = []) {
    return columns.filter((column) => column.excelDownload);
}

export function filterSearchableColumns(columns = []) {
    return columns.filter((column) => column.searchable);
}

export function filterSearchDateColumns(columns = []) {
    return columns.filter((column) => column.searchDate);
}

export function getDefaultSearchColumn(columns = []) {
    return columns.find((column) => column.searchDefault) ?? null;
}
