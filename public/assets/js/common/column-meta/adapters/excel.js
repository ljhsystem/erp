import { getColumnMetaList } from '../registry.js';

function buildExcelColumns(domain, type) {
    const columns = getColumnMetaList(domain);
    const defaultSelectedField = type === 'template'
        ? 'excelTemplate'
        : 'excelDownload';

    return columns.map((column) => ({
        key: column.key,
        label: column.label,
        required: column.required,
        defaultSelected: column.required === true || column[defaultSelectedField] === true,
        type: column.type,
        width: column.width,
        aliasOf: column.aliasOf,
    }));
}

export function buildExcelTemplateColumns(domain) {
    return buildExcelColumns(domain, 'template');
}

export function buildExcelDownloadColumns(domain) {
    return buildExcelColumns(domain, 'download');
}

export function buildExcelColumnSettingsKey(domain, type) {
    const resolvedDomain = String(domain ?? '').trim();
    const resolvedType = String(type ?? '').trim().toLowerCase();

    if (!['template', 'download'].includes(resolvedType)) {
        throw new Error(`Unsupported excel column settings type: ${resolvedType}`);
    }

    return `excel.${resolvedType}.${resolvedDomain}.v1`;
}
