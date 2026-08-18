import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { formatNumber } from '/public/assets/js/common/format.js';

const API = { report: '/api/funds/daily-report/report', excel: '/api/funds/daily-report/excel' };
const form = document.getElementById('dailyReportFilter');
const money = (value) => formatNumber(Number(value || 0));
const params = () => new URLSearchParams(new FormData(form));
const escapeHtml = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');

const instruments = await createDataTable({
    tableSelector: '#dailyInstrumentTable', initialData: [], selectable: false, deleteButton: false, searching: false, paging: false, scrollX: true,
    tableSettings: { enabled: true, pageKey: 'ledger.funds.daily_report', tableKey: 'daily-funds-instruments', storageKey: 'datatable.settings.funds.daily-report.instruments.v1', tableLabel: '자금일보 자금수단별 현황' },
    columns: [
        { data: 'fund_type', title: '자금구분' }, { data: 'bank_name', title: '금융기관/보관처' },
        { data: 'account_name', title: '계좌명/자금수단', render: (v, t, r) => t === 'display' ? `<a href="${escapeHtml(r.transactions_url)}">${escapeHtml(v)}</a>` : v },
        { data: 'account_number', title: '계좌번호' },
        ...['opening_balance','deposit_total','withdraw_total','internal_deposit','internal_withdraw','ending_balance','accounting_balance','balance_difference','unlinked_amount','scheduled_payment','available_balance'].map((data) => ({ data, title: ({opening_balance:'전일 실제잔액',deposit_total:'당일 실제입금',withdraw_total:'당일 실제출금',internal_deposit:'내부입금',internal_withdraw:'내부출금',ending_balance:'당일 실제잔액',accounting_balance:'당일 회계잔액',balance_difference:'실제·회계 차이',unlinked_amount:'미연결금액',scheduled_payment:'지급예정액',available_balance:'지급 후 가용잔액'})[data], className: 'text-end text-nowrap', render: money })),
        { data: 'last_transaction_at', title: '최종거래일시', className: 'text-nowrap', defaultContent: '-' },
    ],
});
const transactions = await createDataTable({
    tableSelector: '#dailyTransactionTable', initialData: [], selectable: false, deleteButton: false, searching: false, scrollX: true, pageLength: 100,
    tableSettings: { enabled: true, pageKey: 'ledger.funds.daily_report', tableKey: 'daily-funds-transactions', storageKey: 'datatable.settings.funds.daily-report.transactions.v1', tableLabel: '자금일보 당일 입출금' },
    columns: [
        { data: 'transaction_datetime', title: '거래일시', className: 'text-nowrap' }, { data: 'account_name', title: '자금수단' },
        { data: 'direction_label', title: '구분' }, { data: 'description', title: '거래내용' }, { data: 'client_name', title: '거래처' },
        { data: 'project_name', title: '프로젝트' }, { data: 'deposit_amount', title: '입금', className: 'text-end', render: money },
        { data: 'withdraw_amount', title: '출금', className: 'text-end', render: money },
        { data: 'is_internal_transfer', title: '내부이체', render: (v) => v === true ? '예' : (v === false ? '아니오' : '미분류') },
        { data: 'link_label', title: '연결상태' }, { data: 'voucher_no', title: '전표번호' }, { data: 'memo', title: '메모' },
    ],
});

async function loadReport() {
    const response = await fetch(`${API.report}?${params()}`, { headers: { Accept: 'application/json' } });
    const payload = await response.json();
    if (!response.ok || payload.success !== true) throw new Error(payload.message || '자금일보 조회 중 오류가 발생했습니다.');
    const data = payload.data;
    document.querySelectorAll('[data-summary]').forEach((el) => { el.textContent = money(data.summary[el.dataset.summary]); });
    document.querySelectorAll('[data-unlinked]').forEach((el) => { const key = el.dataset.unlinked; el.textContent = key.endsWith('_count') ? `${Number(data.unlinked[key] || 0).toLocaleString('ko-KR')}건` : money(data.unlinked[key]); });
    document.querySelectorAll('[data-payment]').forEach((el) => { el.textContent = money(data.payment_schedule[el.dataset.payment]); });
    document.getElementById('dailyReportIntegrity')?.classList.toggle('d-none', data.integrity.valid === true);
    document.getElementById('dailyReportDateLabel').textContent = data.filters.report_date;
    document.getElementById('dailyUnlinkedLink').href = `/ledger/funds/account-transactions?link_status=UNLINKED&date_start=${encodeURIComponent(data.filters.report_date)}&date_end=${encodeURIComponent(data.filters.report_date)}`;
    document.getElementById('dailyPaymentLink').href = `/ledger/funds/payment-schedule?date_from=${encodeURIComponent(data.filters.report_date)}`;
    instruments.clear().rows.add(data.instruments).draw();
    transactions.clear().rows.add(data.transactions).draw();
}
form.addEventListener('submit', (event) => { event.preventDefault(); loadReport().catch((error) => window.alert(error.message)); });
form.addEventListener('reset', () => window.setTimeout(() => loadReport().catch((error) => window.alert(error.message)), 0));
document.getElementById('dailyReportExcel').addEventListener('click', () => { window.location.href = `${API.excel}?${params()}`; });
document.getElementById('dailyReportPrint').addEventListener('click', () => window.print());
loadReport().catch((error) => window.alert(error.message));
