import { DATA_TABLE_PAGE_LENGTH_OPTIONS } from './dataTableViewPolicy.js';

function editableViewState(context = {}, source = {}, options = {}) {
    const capability = options.searchFormCapability || null;
    return {
        columnWidths: { ...(source?.columnWidths || {}) },
        sortSettings: Array.isArray(source?.sortSettings)
            ? source.sortSettings.slice(0, 1).map((item) => ({ ...item }))
            : [],
        pageLength: Number(source?.pageLength) || DATA_TABLE_PAGE_LENGTH_OPTIONS[0],
        searchFormExpanded: typeof source?.searchFormExpanded === 'boolean'
            ? source.searchFormExpanded
            : (options.useDefaults === true
                ? Boolean(capability?.defaultExpanded)
                : Boolean(capability?.getExpanded?.())),
    };
}

export function cycleDataTableSortSettings(sortSettings = [], key = '') {
    const normalizedKey = String(key || '').trim();
    const current = (Array.isArray(sortSettings) ? sortSettings : [])
        .map((item) => ({
            key: String(item?.key || '').trim(),
            dir: String(item?.dir || '').toLowerCase() === 'desc' ? 'desc' : 'asc',
        }))
        .filter((item) => item.key !== '');
    if (normalizedKey === '') return current.slice(0, 1);

    const index = current.findIndex((item) => item.key === normalizedKey);
    if (index < 0) return [{ key: normalizedKey, dir: 'asc' }];
    if (current[index].dir === 'asc') {
        return [{ key: normalizedKey, dir: 'desc' }];
    }
    return [];
}

export function buildDataTableViewModalOptions(context = {}, table = null, defaults = {}) {
    const capability = table?.__dtTableSettings?.searchFormCapability || null;
    const options = { searchFormCapability: capability };
    return {
        viewSettingsEnabled: true,
        viewSettings: editableViewState(context, context.viewState, options),
        defaultViewSettings: editableViewState(context, defaults, { ...options, useDefaults: true }),
        pageLengthOptions: DATA_TABLE_PAGE_LENGTH_OPTIONS,
        searchFormAvailable: Boolean(capability?.available),
        searchFormExpandedDefault: Boolean(capability?.defaultExpanded),
        restoreViewDefaults: () => editableViewState(context, defaults, { ...options, useDefaults: true }),
    };
}

export function buildNextDataTableViewState(context = {}, entries = [], values = {}, searchFormAvailable = false) {
    const columnWidths = { ...(context.viewState?.columnWidths || {}) };
    entries.forEach((entry) => {
        if (entry.widthResizable === false) return;
        delete columnWidths[entry.key];
        if (entry.visible !== false && Number.isFinite(Number(entry.width)) && Number(entry.width) > 0) {
            columnWidths[entry.key] = Math.round(Number(entry.width));
        }
    });

    const previousState = {
        ...(context.viewState || {}),
        columnWidths: { ...(context.viewState?.columnWidths || {}) },
        sortSettings: Array.isArray(context.viewState?.sortSettings)
            ? context.viewState.sortSettings.map((item) => ({ ...item }))
            : [],
    };
    const nextState = {
        ...(context.viewState || {}),
        columnWidths,
        sortSettings: Array.isArray(values.sortSettings)
            ? values.sortSettings.slice(0, 1).map((item) => ({ ...item }))
            : [],
        pageLength: Number(values.pageLength) || context.viewState?.pageLength,
        searchFormExpanded: searchFormAvailable
            ? values.searchFormExpanded === true
            : context.viewState?.searchFormExpanded,
    };
    return { previousState, nextState };
}
