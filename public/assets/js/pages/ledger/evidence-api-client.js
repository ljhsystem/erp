export function apiFailureMessage(json = {}, fallback = '요청 처리에 실패했습니다.') {
    const base = String(json?.message || fallback || '').trim();
    const errors = Array.isArray(json?.errors) ? json.errors : [];
    const details = errors
        .map((error, index) => {
            if (typeof error === 'string') return error.trim();
            if (!error || typeof error !== 'object') return '';
            const rowText = error.row ? `${error.row}행` : (error.row_id ? `ID ${error.row_id}` : `${index + 1}번`);
            const message = String(error.message || '').trim();
            return message ? `${rowText}: ${message}` : '';
        })
        .filter(Boolean);

    if (details.length === 0) return base || fallback;
    return `${base}\n${details.slice(0, 5).join('\n')}${details.length > 5 ? `\n외 ${details.length - 5}건` : ''}`;
}

export function createEvidenceApiClient() {
    const API = {
        rows: '/api/import/evidences',
        reorder: '/api/import/evidences/reorder',
        trash: '/api/import/evidences/trash',
        changeStatus: '/api/import/evidences/status',
        deleteRows: '/api/import/evidences/delete',
        restoreRows: '/api/import/evidences/restore',
        purgeRows: '/api/import/evidences/purge',
        purgeAll: '/api/import/evidences/purge-all',
        saveSeedRow: '/api/import/evidence/save',
        evidenceSummarySearch: '/api/import/evidence/summary-search',
        accountList: '/api/ledger/account/list',
        subAccountList: '/api/account/sub-accounts',
        clientList: '/api/settings/base-info/client/list',
        projectList: '/api/settings/base-info/project/list',
        employeeList: '/api/settings/organization/employee/list',
        bankAccountList: '/api/settings/base-info/bank-account/list',
        cardList: '/api/settings/base-info/card/list',
        clientSearch: '/api/settings/base-info/client/search-picker',
        projectSearch: '/api/settings/base-info/project/search-picker',
        employeeSearch: '/api/settings/organization/employee/search-picker',
        bankAccountSearch: '/api/settings/base-info/bank-account/search-picker',
        cardSearch: '/api/settings/base-info/card/search-picker',
        codeList: '/api/settings/system/code/list',
    };

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            cache: 'no-store',
            ...options,
            headers: {
                ...(options.headers || {}),
            },
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(apiFailureMessage(json, '요청 처리에 실패했습니다.'));
        }
        return json;
    }

    return {
        API,
        fetchJson,
        apiFailureMessage,
    };
}
