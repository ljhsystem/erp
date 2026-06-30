(function () {
    'use strict';

    const AppCore = window.AppCore = window.AppCore || {};

    if (AppCore.AppAjax) {
        window.AppAjax = AppCore.AppAjax;
        return;
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
        });

        const json = await response.json().catch(() => ({}));

        if (!response.ok || json.success === false) {
            throw new Error(json.message || `Request failed (${response.status})`);
        }

        return json;
    }

    async function fetchJson(url, options = {}) {
        return requestJson(url, options);
    }

    async function postForm(url, data = {}) {
        return requestJson(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams(data),
        });
    }

    async function postJson(url, payload = {}) {
        return requestJson(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });
    }

    async function postBulkJson(url, payload = []) {
        const normalizedPayload = Array.isArray(payload)
            ? {
                ids: payload,
                seed_row_ids: payload,
                evidence_ids: payload,
            }
            : (payload && typeof payload === 'object'
                ? {
                    ...payload,
                    ids: Array.isArray(payload.ids) ? payload.ids : [],
                    seed_row_ids: Array.isArray(payload.seed_row_ids) ? payload.seed_row_ids : (Array.isArray(payload.ids) ? payload.ids : []),
                    evidence_ids: Array.isArray(payload.evidence_ids) ? payload.evidence_ids : (Array.isArray(payload.ids) ? payload.ids : []),
                }
                : {
                    ids: [],
                    seed_row_ids: [],
                    evidence_ids: [],
                });

        return postJson(url, normalizedPayload);
    }

    const AppAjax = {
        requestJson,
        fetchJson,
        postJson,
        postForm,
        postBulkJson,
    };

    AppCore.fetchJson = fetchJson;
    AppCore.requestJson = requestJson;
    AppCore.postJson = postJson;
    AppCore.postForm = postForm;
    AppCore.postBulkJson = postBulkJson;
    AppCore.AppAjax = AppAjax;
    window.AppAjax = AppAjax;
})();
