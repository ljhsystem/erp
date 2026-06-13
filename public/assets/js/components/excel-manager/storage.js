export function loadExcelSettings(storageKey, fallbackState) {
    try {
        const raw = window.localStorage.getItem(storageKey);
        if (!raw) {
            return fallbackState;
        }

        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : fallbackState;
    } catch {
        return fallbackState;
    }
}

export function saveExcelSettings(storageKey, state) {
    const payload = {
        ...state,
        updatedAt: new Date().toISOString(),
    };

    window.localStorage.setItem(storageKey, JSON.stringify(payload));
    return payload;
}
