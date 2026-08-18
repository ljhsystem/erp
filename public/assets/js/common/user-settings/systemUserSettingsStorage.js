const DETAIL_API_URL = '/api/settings/system/user-settings/detail';
const SAVE_API_URL = '/api/settings/system/user-settings/save';
const DELETE_API_URL = '/api/settings/system/user-settings/delete';

const cachedSettings = new Map();
const pendingLoads = new Map();
const pendingSaveTimers = new Map();
const debugCounters = {
    detail: new Map(),
    save: new Map(),
    delete: new Map(),
};

function cloneValue(value) {
    if (value === null || value === undefined) {
        return value ?? null;
    }

    try {
        return JSON.parse(JSON.stringify(value));
    } catch {
        return value;
    }
}

function normalizeSettingsJson(value) {
    return value && typeof value === 'object' && !Array.isArray(value)
        ? { ...value }
        : {};
}

function buildCacheKey(pageKey, settingType) {
    return `${pageKey}::${settingType}`;
}

function incrementDebugCounter(bucket, pageKey, settingType) {
    const map = debugCounters[bucket];
    if (!(map instanceof Map)) {
        return;
    }

    const key = buildCacheKey(pageKey, settingType);
    map.set(key, Number(map.get(key) || 0) + 1);
}

function readDebugCounterBucket(bucket) {
    const map = debugCounters[bucket];
    if (!(map instanceof Map)) {
        return {};
    }

    return Array.from(map.entries()).reduce((acc, [key, value]) => {
        acc[key] = Number(value || 0);
        return acc;
    }, {});
}

function normalizePageKey(value = '') {
    const normalized = String(value || '').trim();
    return normalized;
}

function normalizeDescription(value = '') {
    return String(value || '').trim();
}

function inferDescriptionFromBreadcrumb() {
    const bodyDescription = normalizeDescription(document.body?.dataset?.pageDescription || '');
    if (bodyDescription !== '') {
        return bodyDescription;
    }

    const items = Array.from(document.querySelectorAll('.breadcrumb-list .breadcrumb-item [itemprop="name"], .breadcrumb-list .breadcrumb-item .current'))
        .map((node) => normalizeDescription(node?.textContent || ''))
        .filter(Boolean);

    return items.join(' > ');
}

function inferSettingType(storageKey = '') {
    const normalized = String(storageKey || '').trim();
    if (normalized.startsWith('excel.template.')) {
        return 'EXCEL_UPLOAD';
    }
    if (normalized.startsWith('excel.download.')) {
        return 'EXCEL_DOWNLOAD';
    }
    if (normalized !== '') {
        return 'TABLE';
    }

    return '';
}

function inferPageKeyFromStorageKey(storageKey = '') {
    const normalized = String(storageKey || '').trim().replace(/\.v\d+$/i, '');
    if (normalized === '') {
        return '';
    }

    if (normalized.startsWith('excel.template.')) {
        return normalizePageKey(normalized.slice('excel.template.'.length));
    }

    if (normalized.startsWith('excel.download.')) {
        return normalizePageKey(normalized.slice('excel.download.'.length));
    }

    if (normalized.startsWith('datatable.settings.')) {
        const body = normalized.slice('datatable.settings.'.length);
        const lastDotIndex = body.lastIndexOf('.');
        return normalizePageKey(lastDotIndex > -1 ? body.slice(0, lastDotIndex) : body);
    }

    const lastDotIndex = normalized.lastIndexOf('.');
    return normalizePageKey(lastDotIndex > -1 ? normalized.slice(0, lastDotIndex) : normalized);
}

function resolveStorageRequest(storageKey = '', options = {}) {
    const settingType = String(
        options?.settingType
        || inferSettingType(storageKey)
    ).trim().toUpperCase();
    const pageKey = normalizePageKey(
        options?.userSettingPageKey
        || options?.metaDomain
        || options?.domain
        || options?.pageKey
        || inferPageKeyFromStorageKey(storageKey)
    );

    if (pageKey === '' || settingType === '') {
        return null;
    }

    return {
        storageKey: String(storageKey || '').trim(),
        pageKey,
        settingType,
        description: normalizeDescription(options?.description || inferDescriptionFromBreadcrumb()),
    };
}

function emptyRecord() {
    return {
        exists: false,
        settingsJson: {},
    };
}

function cacheRecord(request, record) {
    cachedSettings.set(buildCacheKey(request.pageKey, request.settingType), {
        exists: record?.exists === true,
        settingsJson: normalizeSettingsJson(record?.settingsJson),
    });
}

function cachedRecord(request) {
    return cachedSettings.get(buildCacheKey(request.pageKey, request.settingType)) || null;
}

async function loadProfileRecord(request) {
    const cached = cachedRecord(request);
    if (cached) {
        return cached;
    }

    const cacheKey = buildCacheKey(request.pageKey, request.settingType);
    if (pendingLoads.has(cacheKey)) {
        return pendingLoads.get(cacheKey);
    }

    const requestUrl = `${DETAIL_API_URL}?page_key=${encodeURIComponent(request.pageKey)}&setting_type=${encodeURIComponent(request.settingType)}`;
    const pending = (async () => {
        try {
            incrementDebugCounter('detail', request.pageKey, request.settingType);
            const response = await fetch(requestUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const parsed = await response.json();
            const record = {
                exists: parsed?.data?.exists === true,
                settingsJson: normalizeSettingsJson(parsed?.data?.settings_json),
            };
            cacheRecord(request, record);
            return record;
        } catch (error) {
            console.warn('[system-user-settings] load failed:', error);
            return emptyRecord();
        } finally {
            pendingLoads.delete(cacheKey);
        }
    })();
    pendingLoads.set(cacheKey, pending);
    return pending;
}

async function saveProfile(request, settingsJson) {
    const payload = normalizeSettingsJson(settingsJson);

    try {
        incrementDebugCounter('save', request.pageKey, request.settingType);
        const response = await fetch(SAVE_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                page_key: request.pageKey,
                setting_type: request.settingType,
                description: request.description,
                settings_json: payload,
            }),
        });
        if (response.ok) {
            cacheRecord(request, {
                exists: true,
                settingsJson: payload,
            });
            return cloneValue(payload);
        }
    } catch (error) {
        console.warn('[system-user-settings] save failed:', error);
    }

    return null;
}

async function deleteProfile(request) {
    try {
        incrementDebugCounter('delete', request.pageKey, request.settingType);
        const response = await fetch(DELETE_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ page_key: request.pageKey, setting_type: request.settingType }),
        });
        if (!response.ok) return false;
    } catch (error) {
        console.warn('[system-user-settings] delete failed:', error);
        return false;
    }

    cacheRecord(request, emptyRecord());
    return true;
}

function scheduleSave(request, settingsJson) {
    const cacheKey = buildCacheKey(request.pageKey, request.settingType);
    if (pendingSaveTimers.has(cacheKey)) {
        window.clearTimeout(pendingSaveTimers.get(cacheKey));
    }

    const payload = normalizeSettingsJson(settingsJson);
    const timerId = window.setTimeout(() => {
        pendingSaveTimers.delete(cacheKey);
        incrementDebugCounter('save', request.pageKey, request.settingType);
        fetch(SAVE_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                page_key: request.pageKey,
                setting_type: request.settingType,
                description: request.description,
                settings_json: payload,
            }),
        }).catch((error) => {
            console.warn('[system-user-settings] save failed:', error);
        });
    }, 250);

    pendingSaveTimers.set(cacheKey, timerId);
}

export function isSystemUserSettingsManagedStorage(storageKey = '', options = {}) {
    return resolveStorageRequest(storageKey, options) !== null;
}

export function peekSystemUserSettingsStorage(storageKey = '', options = {}) {
    const request = resolveStorageRequest(storageKey, options);
    if (!request) return null;
    const record = cachedRecord(request);
    return record?.exists === true ? cloneValue(record.settingsJson) : null;
}

export async function readSystemUserSettingsStorage(storageKey = '', options = {}) {
    const request = resolveStorageRequest(storageKey, options);
    if (!request) {
        return null;
    }

    const record = await loadProfileRecord(request);
    if (!record.exists) {
        return null;
    }

    return cloneValue(record.settingsJson);
}

export async function ensureSystemUserSettingsStorage(storageKey = '', payload = null, options = {}) {
    const request = resolveStorageRequest(storageKey, options);
    if (!request) {
        return null;
    }

    const record = await loadProfileRecord(request);
    if (record.exists) {
        return cloneValue(record.settingsJson);
    }

    const fallback = normalizeSettingsJson(cloneValue(payload));
    cacheRecord(request, { exists: true, settingsJson: fallback });
    void saveProfile(request, fallback);
    return fallback;
}

export function writeSystemUserSettingsStorage(storageKey = '', payload = null, options = {}) {
    const request = resolveStorageRequest(storageKey, options);
    if (!request) {
        return null;
    }

    const settingsJson = normalizeSettingsJson(cloneValue(payload));
    cacheRecord(request, {
        exists: true,
        settingsJson,
    });
    scheduleSave(request, settingsJson);

    return cloneValue(settingsJson);
}

export async function deleteSystemUserSettingsStorage(storageKey = '', options = {}) {
    const request = resolveStorageRequest(storageKey, options);
    if (!request) {
        return false;
    }

    const cacheKey = buildCacheKey(request.pageKey, request.settingType);
    if (pendingSaveTimers.has(cacheKey)) {
        window.clearTimeout(pendingSaveTimers.get(cacheKey));
        pendingSaveTimers.delete(cacheKey);
    }

    return deleteProfile(request);
}

export function resetSystemUserSettingsDebugCounters() {
    Object.values(debugCounters).forEach((map) => {
        if (map instanceof Map) {
            map.clear();
        }
    });
}

export function getSystemUserSettingsDebugCounters() {
    return {
        detail: readDebugCounterBucket('detail'),
        save: readDebugCounterBucket('save'),
        delete: readDebugCounterBucket('delete'),
    };
}
