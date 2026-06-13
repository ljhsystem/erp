import {
    filterSearchDateColumns,
    filterSearchableColumns,
    getDefaultSearchColumn,
} from '../helpers.js';
import { getColumnMetaList } from '../registry.js';

export function buildSearchFields(domain) {
    return filterSearchableColumns(getColumnMetaList(domain))
        .filter((column) => !column.searchDate)
        .map((column) => ({
            value: column.key,
            label: column.label,
            type: column.type,
            required: column.required,
            aliasOf: column.aliasOf,
        }));
}

export function buildSearchDateOptions(domain) {
    return filterSearchDateColumns(getColumnMetaList(domain)).map((column) => ({
        value: column.key,
        label: column.label,
        type: column.type,
    }));
}

export function getDefaultSearchField(domain) {
    return getDefaultSearchColumn(getColumnMetaList(domain))?.key ?? '';
}

export function buildSearchConfig(domain) {
    return {
        domain,
        defaultSearchField: getDefaultSearchField(domain),
        searchFields: buildSearchFields(domain),
        dateOptions: buildSearchDateOptions(domain),
    };
}
