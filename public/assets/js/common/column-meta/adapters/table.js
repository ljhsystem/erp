import { getColumnMetaList } from '../registry.js';

export function buildTableColumnMeta(domain) {
    return getColumnMetaList(domain)
        .filter((column) => column.key !== 'id')
        .map((column) => ({
            key: column.key,
            title: column.label,
            visible: column.tableVisible,
            defaultVisible: column.tableDefault,
            searchable: column.searchable,
            sortable: column.sortable,
            width: column.width,
            type: column.type,
            required: column.required,
            aliasOf: column.aliasOf,
            codeGroup: column.codeGroup,
        }));
}

export function buildTableVisibilityDefaults(domain) {
    return buildTableColumnMeta(domain).map((column) => ({
        key: column.key,
        visible: column.defaultVisible,
    }));
}

export function buildTableColumnOrder(domain) {
    return buildTableColumnMeta(domain).map((column) => column.key);
}
