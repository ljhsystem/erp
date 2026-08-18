export const API = {
    list: '/api/funds/payment-schedule/list',
    detail: '/api/funds/payment-schedule/detail',
    save: '/api/funds/payment-schedule/save',
    hold: '/api/funds/payment-schedule/hold',
    releaseHold: '/api/funds/payment-schedule/release-hold',
    delete: '/api/funds/payment-schedule/delete',
    excel: '/api/funds/payment-schedule/excel',
    withdrawals: '/api/funds/payment-schedule/bank-withdrawals',
    allocate: '/api/funds/payment-schedule/allocate',
    releaseAllocation: '/api/funds/payment-schedule/release-allocation',
};

export async function getJson(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success === false) throw new Error(payload.message || '조회 중 오류가 발생했습니다.');
    return payload.data ?? payload;
}

export async function postJson(url, data) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(data),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success === false) throw new Error(payload.message || '요청 처리 중 오류가 발생했습니다.');
    return payload.data;
}
