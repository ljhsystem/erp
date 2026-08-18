import {
    ensureSystemUserSettingsStorage,
    readSystemUserSettingsStorage,
    writeSystemUserSettingsStorage,
} from '../../common/user-settings/systemUserSettingsStorage.js';

export async function loadExcelSettings(storageKey, fallbackState, options = {}) {
    try {
        const savedFromDb = await readSystemUserSettingsStorage(storageKey, options);
        if (savedFromDb && typeof savedFromDb === 'object') {
            return savedFromDb;
        }

        return await ensureSystemUserSettingsStorage(storageKey, fallbackState, options) || fallbackState;
    } catch {
        return fallbackState;
    }
}

export function saveExcelSettings(storageKey, state, options = {}) {
    const payload = {
        ...state,
        updatedAt: new Date().toISOString(),
    };

    writeSystemUserSettingsStorage(storageKey, payload, options);
    return payload;
}
