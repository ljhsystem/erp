import { getColumnMetaList } from '../registry.js';
import { actorExcel, actorNameField } from '../../actor.js';

function buildExcelColumns(domain, type) {
    const columns = getColumnMetaList(domain);
    const defaultSelectedField = type === 'template'
        ? 'excelTemplate'
        : 'excelDownload';

    return columns.map((column) => {
        const isActor = column.type === 'actor';
        return {
            key: column.key,
            label: column.label,
            required: column.required,
            defaultSelected: column.required === true || column[defaultSelectedField] === true,
            type: column.type,
            width: column.width,
            aliasOf: column.aliasOf,
            valueKey: isActor ? actorNameField(column.key) : column.key,
            fallbackKey: isActor ? column.key : null,
        };
    });
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

export { actorExcel };
