import { formatNumber } from '/public/assets/js/common/format.js';

function countText(value) {
    return `${Number(value || 0).toLocaleString('ko-KR')}건`;
}

function amountText(value) {
    if (value === null || value === undefined || value === '') return '-';
    const number = Number(String(value).replace(/,/g, ''));
    if (!Number.isFinite(number)) return formatNumber(value);
    return formatNumber(Number.isInteger(number) ? String(number) : String(number).replace(/0+$/, '').replace(/\.$/, ''));
}

export function renderSummary(summary = {}) {
    const renderers = {
        deposit_total: amountText,
        withdraw_total: amountText,
        ending_balance: amountText,
        unlinked_count: countText,
        voucher_linked_count: countText,
    };

    Object.entries(renderers).forEach(([key, renderer]) => {
        const el = document.querySelector(`[data-funds-summary="${key}"]`);
        if (!el) return;
        el.textContent = renderer(summary[key]);
    });
}
