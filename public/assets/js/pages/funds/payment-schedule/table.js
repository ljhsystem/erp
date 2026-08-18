import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { formatNumber } from '/public/assets/js/common/format.js';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' })[char]);
const money = (value) => formatNumber(Number(value || 0));
const badge = (code, label) => `<span class="payment-status payment-status-${escapeHtml(String(code).toLowerCase())}">${escapeHtml(label)}</span>`;

export async function createPaymentScheduleTable(url, onDetail) {
    const table = await createDataTable({
        tableSelector: '#paymentScheduleTable',
        api: url,
        selectable: false,
        deleteButton: false,
        searching: false,
        scrollX: true,
        pageLength: 50,
        defaultOrder: [[1, 'asc']],
        serverSide: true,
        tableSettings: {
            enabled: true,
            pageKey: 'ledger.funds.payment_schedule',
            tableKey: 'payment-schedule-main',
            storageKey: 'datatable.settings.funds.payment-schedule.main.v1',
            tableLabel: '지급예정현황',
        },
        columns: [
            { data: 'sort_no', title: '순번', className: 'text-center' },
            { data: 'payment_due_date_label', title: '지급예정일', className: 'text-nowrap', defaultContent: '지급일 미정' },
            { data: 'payment_status_label', title: '지급상태', render: (value, type, row) => type === 'display' ? badge(row.payment_status_code, value) : value },
            { data: 'voucher_date', title: '전표일자', className: 'text-nowrap', defaultContent: '-' },
            { data: 'voucher_no', title: '전표번호', className: 'text-nowrap', defaultContent: '-' },
            { data: 'voucher_line_no', title: '전표라인', className: 'text-center', defaultContent: '-' },
            { data: 'obligation_account_name', title: '지급대상 계정', defaultContent: '-' },
            { data: 'client_name', title: '거래처', defaultContent: '-' },
            { data: 'project_name', title: '프로젝트', defaultContent: '-' },
            { data: 'assignee_name', title: '담당자', defaultContent: '-' },
            { data: 'payment_bank_account_name', title: '지급계좌', defaultContent: '-' },
            { data: 'scheduled_amount', title: '지급예정액', className: 'text-end', render: money },
            { data: 'paid_amount', title: '기지급액', className: 'text-end', render: money },
            { data: 'remaining_amount', title: '잔여액', className: 'text-end fw-semibold', render: money },
            { data: 'overdue_days', title: '연체일수', className: 'text-end', render: (value) => Number(value || 0) ? `${Number(value)}일` : '-' },
            { data: 'is_on_hold', title: '보류', className: 'text-center', render: (value) => Number(value) === 1 ? '보류' : '-' },
            { data: 'memo', title: '메모', defaultContent: '' },
            { data: 'last_payment_at', title: '최종지급일시', className: 'text-nowrap', defaultContent: '-' },
            { data: 'created_at', title: '등록일시', className: 'text-nowrap' },
            { data: 'updated_at', title: '수정일시', className: 'text-nowrap', defaultContent: '-' },
            { data: null, title: '관리', orderable: false, searchable: false, className: 'text-center', render: () => '<button class="btn btn-outline-primary btn-sm payment-detail-button" type="button">상세</button>' },
        ],
        dataSrc(json) {
            window.dispatchEvent(new CustomEvent('payment-schedule-loaded', { detail: json }));
            return json.data || [];
        },
    });
    document.querySelector('#paymentScheduleTable tbody')?.addEventListener('click', (event) => {
        const button = event.target.closest('.payment-detail-button');
        if (!button) return;
        const row = table.row(button.closest('tr')).data();
        if (row?.id) onDetail(row.id);
    });
    return table;
}

export { escapeHtml, money };
