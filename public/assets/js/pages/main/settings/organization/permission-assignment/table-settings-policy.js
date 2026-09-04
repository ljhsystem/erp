import { readDataTableSettingsState } from '/public/assets/js/common/datatable/dataTableSettings.js';
import { writeSystemUserSettingsStorage } from '/public/assets/js/common/user-settings/systemUserSettingsStorage.js';

function sanitizeTableSettingsState(state, deprecatedKeys) {
    if (!state || typeof state !== 'object') return state;
    const deprecated = new Set(deprecatedKeys);
    const next = { ...state };
    let changed = false;
    ['currentPage', 'searchFormExpanded', 'searchFormState', 'requiredColumns', 'columnWidth'].forEach(key => {
        if (Object.hasOwn(next, key)) {
            delete next[key];
            changed = true;
        }
    });
    ['visibleColumns', 'columnOrder'].forEach(key => {
        if (!Array.isArray(next[key])) return;
        const filtered = next[key].map(String).filter(item => item && !deprecated.has(item));
        if (filtered.length !== next[key].length) {
            next[key] = filtered;
            changed = true;
        }
    });
    ['columnDisplayName', 'columnRequirementPolicy'].forEach(key => {
        if (!next[key] || typeof next[key] !== 'object') return;
        const filtered = Object.fromEntries(Object.entries(next[key]).filter(([item]) => !deprecated.has(item)));
        if (Object.keys(filtered).length !== Object.keys(next[key]).length) {
            next[key] = filtered;
            changed = true;
        }
    });
    return changed ? next : state;
}

export function bindTableSettingsPolicy({ storageKey, userSettingPageKey, deprecatedKeys, handlerKey }) {
    const normalize = () => {
        const current = readDataTableSettingsState(storageKey, { userSettingPageKey });
        const sanitized = sanitizeTableSettingsState(current, deprecatedKeys);
        if (!sanitized || sanitized === current) return;
        writeSystemUserSettingsStorage(storageKey, { ...sanitized, updatedAt: new Date().toISOString() }, {
            userSettingPageKey,
            settingType: 'TABLE',
        });
    };
    normalize();
    document.removeEventListener('datatable-settings:updated', window[handlerKey]);
    window[handlerKey] = event => {
        if (String(event?.detail?.storageKey || '').trim() === storageKey) normalize();
    };
    document.addEventListener('datatable-settings:updated', window[handlerKey]);
}
