export async function requestEmploymentContract(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' },
        ...options,
    });
    const payload = await response.json().catch(() => ({ success: false, message: '응답을 확인할 수 없습니다.' }));
    if (!response.ok || !payload.success) throw new Error(payload.message || '처리 중 오류가 발생했습니다.');
    return payload;
}
