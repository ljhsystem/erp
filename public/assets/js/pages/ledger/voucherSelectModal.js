const modalEl = document.getElementById('voucherSelectModal');
const filterForm = document.getElementById('voucherSelectFilterForm');
const tableBodyEl = document.getElementById('voucherSelectTableBody');
const detailSummaryEl = document.getElementById('voucherSelectDetailSummary');
const linesEl = document.getElementById('voucherSelectLines');
const confirmBtn = document.getElementById('btnConfirmVoucherSelection');
const resetBtn = document.getElementById('btnVoucherSelectReset');

let modal = null;
let vouchers = [];
let selectedVoucher = null;
let onConfirmCallback = null;
let bootstrapped = false;
let voucherDataTable = null;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('ko-KR');
}

function formatStatus(status) {
    return {
        draft: '작성중',
        confirmed: '확정',
        posted: '전기',
        closed: '마감',
    }[String(status || '').toLowerCase()] || status || '';
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        ...options,
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok || json.success === false) {
        throw new Error(json.message || '요청 처리에 실패했습니다.');
    }
    return json;
}

function getFilters() {
    const formData = new FormData(filterForm);
    return {
        date_from: String(formData.get('date_from') || '').trim(),
        date_to: String(formData.get('date_to') || '').trim(),
        status: String(formData.get('status') || '').trim(),
        client_id: String(formData.get('client_id') || '').trim(),
        keyword: String(formData.get('keyword') || '').trim(),
        min_amount: String(formData.get('min_amount') || '').trim(),
        max_amount: String(formData.get('max_amount') || '').trim(),
    };
}

function buildSearchUrl(filters = {}) {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
        if (!value) return;
        if (key === 'status') {
            params.append('status[]', value);
            return;
        }
        params.set(key, value);
    });

    return `/api/ledger/voucher/search${params.toString() ? `?${params.toString()}` : ''}`;
}

export async function loadVoucherList(filters = {}) {
    if (!tableBodyEl) return [];

    tableBodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">조회 중입니다.</td></tr>';

    try {
        const json = await fetchJson(buildSearchUrl(filters));
        vouchers = Array.isArray(json) ? json : (Array.isArray(json.data) ? json.data : []);
        renderVoucherTable();
        return vouchers;
    } catch (error) {
        tableBodyEl.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
        return [];
    }
}

export function renderVoucherTable() {
    if (!tableBodyEl) return;

    if (voucherDataTable) {
        voucherDataTable.destroy();
        voucherDataTable = null;
    }

    if (!vouchers.length) {
        tableBodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">조회된 전표가 없습니다.</td></tr>';
        return;
    }

    tableBodyEl.innerHTML = vouchers.map((voucher) => {
        const id = escapeHtml(voucher.id || '');
        const disabled = String(voucher.status || '').toLowerCase() === 'posted';
        const selected = selectedVoucher && String(selectedVoucher.id || '') === String(voucher.id || '');
        return `
            <tr data-voucher-id="${id}" class="${selected ? 'is-selected' : ''}${disabled ? ' is-disabled' : ''}">
                <td>${escapeHtml(voucher.voucher_no || voucher.id || '')}</td>
                <td>${escapeHtml(voucher.voucher_date || '')}</td>
                <td>${escapeHtml(voucher.client_name || '-')}</td>
                <td>${escapeHtml(voucher.summary_text || '')}</td>
                <td class="text-end">${formatNumber(voucher.amount)}</td>
                <td>${escapeHtml(formatStatus(voucher.status))}</td>
            </tr>
        `;
    }).join('');

    initVoucherDataTable();
}

function initVoucherDataTable() {
    if (!window.jQuery?.fn?.DataTable) return;

    voucherDataTable = window.jQuery('#voucherSelectTable').DataTable({
        destroy: true,
        dom: 't',
        paging: false,
        searching: false,
        info: false,
        ordering: true,
        autoWidth: false,
    });
}

async function loadVoucherDetail(voucherId) {
    if (!voucherId || !linesEl || !detailSummaryEl) return;

    detailSummaryEl.textContent = '전표 라인을 불러오는 중입니다.';
    linesEl.innerHTML = '';

    try {
        const json = await fetchJson(`/api/ledger/voucher/detail?id=${encodeURIComponent(voucherId)}`);
        const voucher = json.data || {};
        const lines = Array.isArray(voucher.lines) ? voucher.lines : [];
        detailSummaryEl.textContent = `${voucher.voucher_no || voucherId} / ${voucher.voucher_date || ''} / ${formatStatus(voucher.status)}`;

        if (!lines.length) {
            linesEl.innerHTML = '<div class="text-muted small">전표 라인이 없습니다.</div>';
            return;
        }

        linesEl.innerHTML = lines.map((line) => {
            const debit = Number(line.debit || 0);
            const credit = Number(line.credit || 0);
            return `
                <div class="voucher-select-line">
                    <div>
                        <div class="voucher-select-line-main">${escapeHtml(line.account_text || line.account_name || line.account_id || '')}</div>
                        <div class="voucher-select-line-sub">${escapeHtml(line.line_summary || '')}</div>
                    </div>
                    <div class="voucher-select-line-amount">
                        <div>차 ${formatNumber(debit)}</div>
                        <div>대 ${formatNumber(credit)}</div>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        detailSummaryEl.textContent = error.message;
    }
}

export function selectVoucher(voucher) {
    if (!voucher || String(voucher.status || '').toLowerCase() === 'posted') {
        selectedVoucher = null;
        if (confirmBtn) confirmBtn.disabled = true;
        renderVoucherTable();
        return;
    }

    selectedVoucher = voucher;
    if (confirmBtn) confirmBtn.disabled = false;
    renderVoucherTable();
    void loadVoucherDetail(voucher.id);
}

export function confirmSelection() {
    if (!selectedVoucher || String(selectedVoucher.status || '').toLowerCase() === 'posted') return;

    if (typeof onConfirmCallback === 'function') {
        onConfirmCallback(selectedVoucher);
    }
    modal?.hide();
}

function bindEvents() {
    if (bootstrapped || !modalEl) return;
    bootstrapped = true;

    filterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        void loadVoucherList(getFilters());
    });

    resetBtn?.addEventListener('click', () => {
        filterForm?.reset();
        selectedVoucher = null;
        if (confirmBtn) confirmBtn.disabled = true;
        if (detailSummaryEl) detailSummaryEl.textContent = '전표를 선택해 주세요.';
        if (linesEl) linesEl.innerHTML = '';
        void loadVoucherList({});
    });

    tableBodyEl?.addEventListener('click', (event) => {
        const row = event.target.closest('tr[data-voucher-id]');
        if (!row || row.classList.contains('is-disabled')) return;
        const voucher = vouchers.find((item) => String(item.id || '') === row.dataset.voucherId);
        selectVoucher(voucher);
    });

    tableBodyEl?.addEventListener('dblclick', (event) => {
        const row = event.target.closest('tr[data-voucher-id]');
        if (!row || row.classList.contains('is-disabled')) return;
        const voucher = vouchers.find((item) => String(item.id || '') === row.dataset.voucherId);
        selectVoucher(voucher);
        confirmSelection();
    });

    confirmBtn?.addEventListener('click', confirmSelection);
}

export function openVoucherModal(options = {}) {
    if (!modalEl) return;

    bindEvents();
    onConfirmCallback = options.onConfirm || null;
    selectedVoucher = null;
    if (confirmBtn) confirmBtn.disabled = true;
    if (detailSummaryEl) detailSummaryEl.textContent = '전표를 선택해 주세요.';
    if (linesEl) linesEl.innerHTML = '';

    modal = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }) : null;
    modal?.show();

    void loadVoucherList(getFilters()).then(() => {
        if (!options.selectedVoucherId) return;
        const voucher = vouchers.find((item) => String(item.id || '') === String(options.selectedVoucherId));
        if (voucher) selectVoucher(voucher);
    });
}
