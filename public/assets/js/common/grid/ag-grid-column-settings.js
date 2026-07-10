import {
    ensureSystemUserSettingsStorage,
    readSystemUserSettingsStorage,
    writeSystemUserSettingsStorage,
} from '../user-settings/systemUserSettingsStorage.js';

function buildStorageOptions(storageKey, options = {}) {
    return {
        settingType: 'VIEW',
        pageKey: String(options?.pageKey || '').trim(),
        userSettingPageKey: String(options?.userSettingPageKey || '').trim(),
        metaDomain: String(options?.metaDomain || '').trim(),
        domain: String(options?.domain || '').trim(),
        description: String(options?.description || '').trim(),
    };
}

export function loadAgGridColumnWidthSettings(storageKey, options = {}) {
    if (!storageKey) {
        return null;
    }

    try {
        const payload = readSystemUserSettingsStorage(storageKey, buildStorageOptions(storageKey, options))
            || ensureSystemUserSettingsStorage(storageKey, { columnWidths: {} }, buildStorageOptions(storageKey, options));
        if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
            return null;
        }

        if (!payload.columnWidths || typeof payload.columnWidths !== 'object' || Array.isArray(payload.columnWidths)) {
            return null;
        }

        return payload;
    } catch {
        return null;
    }
}

export function collectAgGridColumns(api) {
    if (!api) {
        return [];
    }

    const candidates = [
        api.getColumns?.(),
        api.getAllGridColumns?.(),
        api.getColumnApi?.()?.getColumns?.(),
        api.getColumnApi?.()?.getAllGridColumns?.(),
    ];

    for (const columns of candidates) {
        if (Array.isArray(columns) && columns.length > 0) {
            return columns;
        }
    }

    return [];
}

export function resolveAgGridColumnKey(column) {
    const colDef = column?.getColDef?.() || {};
    const defColId = typeof colDef.colId === 'string' ? colDef.colId.trim() : '';
    const defField = typeof colDef.field === 'string' ? colDef.field.trim() : '';
    const columnColId = typeof column?.getColId === 'function' ? String(column.getColId() || '').trim() : '';
    return defField || defColId || columnColId;
}

export function saveAgGridColumnWidthSettings(storageKey, api, options = {}) {
    const minWidth = Number(options.minWidth || 0);
    if (!storageKey || !api) {
        return;
    }

    try {
        const columnWidths = {};
        collectAgGridColumns(api).forEach((column) => {
            const key = resolveAgGridColumnKey(column);
            const width = Number(column?.getActualWidth?.() || 0);
            if (!key || !Number.isFinite(width) || width < minWidth) {
                return;
            }

            columnWidths[key] = width;
        });

        writeSystemUserSettingsStorage(storageKey, {
            version: 1,
            updatedAt: new Date().toISOString(),
            columnWidths,
        }, buildStorageOptions(storageKey, options));
    } catch {}
}

export function applyAgGridColumnWidthSettings(columnDefs, settings, options = {}) {
    const minWidth = Number(options.minWidth || 0);
    const savedWidths = settings?.columnWidths;
    if (!Array.isArray(columnDefs)) {
        return [];
    }

    if (!savedWidths || typeof savedWidths !== 'object' || Array.isArray(savedWidths)) {
        return columnDefs.map((column) => ({ ...column }));
    }

    return columnDefs.map((column) => {
        const cloned = { ...column };
        const key = String(cloned.field || cloned.colId || '').trim();
        const savedWidth = Number(savedWidths[key]);
        if (!key || !Number.isFinite(savedWidth) || savedWidth < minWidth) {
            return cloned;
        }

        let nextWidth = savedWidth;
        const columnMinWidth = Number(cloned.minWidth);
        const maxWidth = Number(cloned.maxWidth);

        if (Number.isFinite(columnMinWidth) && nextWidth < columnMinWidth) {
            nextWidth = columnMinWidth;
        }

        if (Number.isFinite(maxWidth) && nextWidth > maxWidth) {
            delete cloned.maxWidth;
        }

        cloned.width = nextWidth;
        return cloned;
    });
}

export function scheduleSaveAgGridColumnWidthSettings(storageKey, api, timerStore, options = {}) {
    if (!storageKey || !api || !(timerStore instanceof Map)) {
        return;
    }

    const debounceMs = Number(options.debounceMs || 0);
    const pendingTimer = timerStore.get(storageKey);
    if (pendingTimer) {
        window.clearTimeout(pendingTimer);
    }

    const timerId = window.setTimeout(() => {
        timerStore.delete(storageKey);
        saveAgGridColumnWidthSettings(storageKey, api, options);
    }, debounceMs);

    timerStore.set(storageKey, timerId);
}
