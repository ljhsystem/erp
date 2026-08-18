import {
    ensureSystemUserSettingsStorage,
    peekSystemUserSettingsStorage,
    readSystemUserSettingsStorage,
    writeSystemUserSettingsStorage,
} from '/public/assets/js/common/user-settings/systemUserSettingsStorage.js';

const STORAGE_KEY = 'html-grid.settings.ledger.voucher-line-grid.columns.v1';

export function normalizeVoucherGridColumnState(columnState = null) {
    const payload = columnState && typeof columnState === 'object' && !Array.isArray(columnState)
        ? columnState
        : {};
    const normalizeKey = (key) => key === 'sort_no' ? 'line_no' : key;
    const normalizeKeys = (keys) => Array.isArray(keys)
        ? keys.map(normalizeKey).filter((key, index, values) => values.indexOf(key) === index)
        : [];
    const hidden = new Set(normalizeKeys(payload.hidden));
    hidden.delete('refs');
    const widths = payload.widths && typeof payload.widths === 'object' ? { ...payload.widths } : {};
    if (Object.prototype.hasOwnProperty.call(widths, 'sort_no')) {
        if (!Object.prototype.hasOwnProperty.call(widths, 'line_no')) widths.line_no = widths.sort_no;
        delete widths.sort_no;
    }
    return {
        order: normalizeKeys(payload.order),
        hidden: Array.from(hidden),
        pinned: normalizeKeys(payload.pinned),
        widths,
        meta: payload.meta && typeof payload.meta === 'object' ? { ...payload.meta } : {},
    };
}

export function createVoucherGridColumnState(ctx, getGrid) {
    const storageOptions = {
        settingType: 'VIEW',
        userSettingPageKey: 'ledger.voucher-line-grid',
        description: ctx.VOUCHER_PAGE_DESCRIPTION,
    };
    const read = () => {
        const payload = peekSystemUserSettingsStorage(STORAGE_KEY, storageOptions);
        if (!payload) void readSystemUserSettingsStorage(STORAGE_KEY, storageOptions).then((loaded) => (
            loaded || ensureSystemUserSettingsStorage(STORAGE_KEY, {
                order: [], hidden: [], pinned: [], widths: {}, meta: {},
            }, storageOptions)
        ));
        return payload && typeof payload === 'object' && !Array.isArray(payload)
            ? normalizeVoucherGridColumnState(payload)
            : null;
    };
    const persist = (columnState = null) => {
        const payload = normalizeVoucherGridColumnState(columnState || getGrid()?.getColumnState?.() || null);
        ctx.state.lineGridColumnState = payload;
        writeSystemUserSettingsStorage(STORAGE_KEY, payload, storageOptions);
    };
    return {
        normalize: normalizeVoucherGridColumnState,
        shouldApply: (current, next) => JSON.stringify(normalizeVoucherGridColumnState(current))
            !== JSON.stringify(normalizeVoucherGridColumnState(next)),
        read,
        persist,
    };
}
