import { createDataTableAdapter } from './datatable-adapter.js';
import { createAgGridAdapter } from './aggrid-adapter.js';

const ADAPTER_MAP = {
    datatable: createDataTableAdapter,
    aggrid: createAgGridAdapter,
};

function toNoopAdapter() {
    return {
        getRows: () => [],
        getVisibleRows: () => [],
        resolveRowById: () => null,
        setActiveRow: () => null,
        clearSelection: () => {},
        redraw: () => {},
        on: () => () => {},
        off: () => {},
        destroy: () => {},
        getHostElement: () => null,
    };
}

function isDataTableAdapterLike(adapter) {
    return adapter
        && typeof adapter?.table === 'function'
        && typeof adapter?.rows === 'function'
        && typeof adapter?.column === 'function';
}

function isAgGridAdapterLike(adapter) {
    return adapter
        && typeof adapter?.setFocusedCell === 'function'
        && typeof adapter?.getDisplayedRowAtIndex === 'function'
        && typeof adapter?.refreshCells === 'function';
}

export function normalizeAdapter(adapter) {
    if (typeof adapter === 'string') {
        const key = adapter.toLowerCase();
        if (Object.prototype.hasOwnProperty.call(ADAPTER_MAP, key)) {
            return key;
        }
    }

    if (typeof adapter === 'object' && adapter) {
        return 'custom';
    }

    return 'datatable';
}

export function createAdapterFor(target, options = {}) {
    const adapterName = normalizeAdapter(options.adapter || 'auto');
    const createAdapter = ADAPTER_MAP[adapterName];

    if (createAdapter) {
        if (adapterName === 'datatable') {
            if (isDataTableAdapterLike(target)) {
                return createAdapter(target, options);
            }
            return toNoopAdapter();
        }

        if (adapterName === 'aggrid') {
            return createAdapter(target, options);
        }
    }

    if (typeof options.adapter === 'function') {
        return options.adapter(target, options);
    }

    if (isDataTableAdapterLike(target) || (typeof target === 'string' ? false : false)) {
        return createDataTableAdapter(target, options);
    }

    if (isAgGridAdapterLike(target)) {
        return createAgGridAdapter(target, options);
    }

    return toNoopAdapter();
}
