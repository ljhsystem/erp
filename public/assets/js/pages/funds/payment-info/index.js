import { SearchForm } from '/public/assets/js/components/search-form.js';
import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { labelBadgeRenderer } from '/public/assets/js/common/table/renderers/index.js';
import { formatNumber } from '/public/assets/js/common/format.js';

const API = {
    list: '/api/funds/payment-info',
};

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function money(value) {
    const number = Number(value || 0);
    if (!Number.isFinite(number) || number === 0) return '-';
    return formatNumber(number);
}

function statusBadge(text, tone = 'secondary') {
    return `<span class="badge text-bg-${tone}">${escapeHtml(text)}</span>`;
}

function directionBadge(row = {}) {
    return row.payment_direction === 'IN'
        ? statusBadge('입금', 'success')
        : statusBadge('출금', 'warning');
}

function typeBadge(row = {}) {
    return labelBadgeRenderer(row.payment_type_label || row.payment_type || '-');
}

function matchBadge(row = {}) {
    return row.match_status === 'MATCHED'
        ? statusBadge(row.match_status_label || '매칭완료', 'primary')
        : statusBadge(row.match_status_label || '은행입출금 미연결', 'secondary');
}

function renderSummary(summary = {}) {
    const setText = (key, value) => {
        const el = document.querySelector(`[data-payment-summary="${key}"]`);
        if (el) el.textContent = value;
    };

    setText('payment_count', `${Number(summary.payment_count || 0).toLocaleString('ko-KR')}건`);
    setText('in_total', money(summary.in_total));
    setText('out_total', money(summary.out_total));
}

function actionButtons(row = {}) {
    const voucherId = escapeHtml(row.voucher_id || '');
    return `
        <div class="payment-row-actions">
            <a class="btn btn-outline-primary btn-sm ${voucherId ? '' : 'disabled'}"
               href="${voucherId ? `/ledger/vouchers/review?voucher_id=${voucherId}` : '#'}">
                전표보기
            </a>
            <a class="btn btn-outline-secondary btn-sm" href="/ledger/funds/account-transactions">
                입출금조회
            </a>
        </div>
    `;
}

function createPaymentTable() {
    const table = createDataTable({
        tableSelector: '#fundsPaymentInfoTable',
        api: API.list,
        density: 'compact',
        pageLength: 100,
        defaultOrder: [[0, 'desc']],
        searchTableId: 'fundsPaymentInfo',
        scrollX: true,
        rowIdField: 'id',
        columns: [
            { data: 'voucher_date', title: '전표일자', className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
            { data: 'voucher_no', title: '전표번호', className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
            { data: 'summary_text', title: '전표적요', render: (value) => `<span title="${escapeHtml(value || '')}">${escapeHtml(value || '-')}</span>` },
            { data: 'payment_direction', title: '입출금', className: 'text-nowrap', render: (_value, _type, row) => directionBadge(row) },
            { data: 'payment_type', title: '결제유형', className: 'text-nowrap', render: (_value, _type, row) => typeBadge(row) },
            {
                data: 'payment_name',
                title: '결제수단',
                className: 'text-nowrap',
                render(value, _type, row) {
                    const bank = row.bank_name ? `${row.bank_name} ` : '';
                    return escapeHtml(`${bank}${value || '-'}`.trim());
                },
            },
            { data: 'payment_number_masked', title: '번호', className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
            { data: 'amount', title: '금액', className: 'text-end text-nowrap', render: money },
            { data: 'journal_status', title: '전표상태', className: 'text-nowrap', render: (value) => labelBadgeRenderer(value || '-') },
            { data: 'match_status', title: '은행입출금', className: 'text-nowrap', render: (_value, _type, row) => matchBadge(row) },
            { data: 'created_at', title: '등록일시', className: 'text-nowrap', render: (value) => escapeHtml(value || '-') },
            {
                data: null,
                title: '관리',
                orderable: false,
                searchable: false,
                className: 'text-nowrap payment-manage-cell',
                render: (_value, _type, row) => actionButtons(row),
            },
        ],
    });

    table.on('xhr.dt', (_event, _settings, json) => {
        renderSummary(json?.summary || {});
        const countEl = document.getElementById('fundsPaymentInfoCount');
        if (countEl) {
            countEl.textContent = `${(json?.data || []).length.toLocaleString('ko-KR')}건`;
        }
    });

    return table;
}

function collectDetailFilters() {
    const filters = [];
    const add = (field, id) => {
        const value = document.getElementById(id)?.value?.trim() || '';
        if (value !== '') filters.push({ field, value });
    };

    add('payment_direction', 'paymentFilterDirection');
    add('payment_type', 'paymentFilterType');
    add('payment_name', 'paymentFilterName');
    add('voucher_no', 'paymentFilterVoucherNo');
    add('summary_text', 'paymentFilterSummary');

    return filters;
}

function resetDetailFilters() {
    [
        'paymentFilterDirection',
        'paymentFilterType',
        'paymentFilterName',
        'paymentFilterVoucherNo',
        'paymentFilterSummary',
    ].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
}

(() => {
    const table = createPaymentTable();

    SearchForm({
        table,
        apiList: API.list,
        tableId: 'fundsPaymentInfo',
        defaultSearchField: 'payment_name',
        dateOptions: [
            { value: 'voucher_date', label: '전표일자' },
            { value: 'created_at', label: '결제등록일시' },
        ],
    });

    document.getElementById('paymentFilterApplyBtn')?.addEventListener('click', () => {
        const query = new URLSearchParams();
        query.set('filters', JSON.stringify(collectDetailFilters()));
        table.ajax.url(`${API.list}?${query.toString()}`).load();
    });

    document.getElementById('paymentFilterResetBtn')?.addEventListener('click', () => {
        resetDetailFilters();
        table.ajax.url(API.list).load();
    });
})();
