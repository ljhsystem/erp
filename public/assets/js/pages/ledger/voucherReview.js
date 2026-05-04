import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/components/data-table.js';

(() => {
    const tableEl = document.getElementById('voucherReviewTable');
    const filterForm = document.getElementById('voucherReviewFilterForm');
    const countEl = document.getElementById('voucherReviewCount');
    const resetFilterBtn = document.getElementById('btnResetVoucherReview');
    const rejectBtn = document.getElementById('rejectBtn');
    const confirmBtn = document.getElementById('confirmBtn');
    const cancelConfirmBtn = document.getElementById('cancelConfirmBtn');
    const approveBtn = document.getElementById('approveBtn');
    const reverseBtn = document.getElementById('reverseBtn');
    const rejectModalEl = document.getElementById('voucherRejectModal');
    const rejectReasonEl = document.getElementById('voucherRejectReason');
    const rejectReasonErrorEl = document.getElementById('voucherRejectReasonError');
    const confirmRejectBtn = document.getElementById('btnConfirmRejectVoucher');
    const titleEl = document.getElementById('voucherReviewDetailTitle');
    const subEl = document.getElementById('voucherReviewDetailSub');
    const statusEl = document.getElementById('voucherReviewDetailStatus');
    const basicInfoEl = document.getElementById('voucherReviewBasicInfo');
    const linesEl = document.getElementById('voucherReviewLines');
    const totalEl = document.getElementById('voucherReviewTotal');
    const linkedInfoEl = document.getElementById('voucherReviewLinkedInfo');

    if (!tableEl || !window.jQuery?.fn?.DataTable) return;

    const API = {
        list: '/api/ledger/voucher/list',
        detail: '/api/ledger/voucher/detail',
        confirm: '/api/ledger/voucher/confirm',
        completeReview: '/api/ledger/voucher/complete-review',
        cancelCompleteReview: '/api/ledger/voucher/cancel-complete-review',
        post: '/api/ledger/voucher/post',
        reverse: '/api/ledger/voucher/reverse',
        reject: '/api/ledger/voucher/reject',
    };

    const STATUS_LABELS = {
        draft: '임시저장',
        confirmed: '검토요청',
        reviewed: '검토완료',
        posted: '승인',
        closed: '마감',
    };

    let table = null;
    let selectedVoucher = null;
    let pendingRejectIds = [];
    const checkedIds = new Set();
    const rejectModal = rejectModalEl && window.bootstrap?.Modal
        ? new window.bootstrap.Modal(rejectModalEl)
        : null;

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

    function currentFilters() {
        const formData = new FormData(filterForm);
        return {
            date_from: String(formData.get('date_from') || '').trim(),
            date_to: String(formData.get('date_to') || '').trim(),
            status: String(formData.get('status') || '').trim(),
            review_status: String(formData.get('review_status') || '').trim(),
            keyword: String(formData.get('keyword') || '').trim(),
        };
    }

    function defaultDateRange() {
        return {
            date_from: '',
            date_to: '',
        };
    }

    function resetFilters() {
        const defaults = defaultDateRange();
        filterForm.elements.date_from.value = defaults.date_from;
        filterForm.elements.date_to.value = defaults.date_to;
        filterForm.elements.status.value = '';
        filterForm.elements.review_status.value = '';
        filterForm.elements.keyword.value = '';

        checkedIds.clear();
        renderEmptyDetail();
        table.ajax.reload();
    }

    function reviewState(row = {}) {
        const debit = Number(row.debit_total || 0);
        const credit = Number(row.credit_total || 0);
        const status = String(row.status || 'draft').toLowerCase();

        if (debit !== credit) {
            return { key: 'error', label: '오류' };
        }

        if (status === 'draft') {
            return { key: 'pending', label: '검토대기' };
        }

        if (status === 'confirmed') {
            return { key: 'pending', label: '검토대기' };
        }

        if (status === 'reviewed') {
            return { key: 'ready', label: '검토완료' };
        }

        if (status === 'posted') {
            return { key: 'done', label: '승인완료' };
        }

        if (status === 'closed') {
            return { key: 'done', label: '마감' };
        }

        return { key: 'pending', label: '검토대기' };
    }

    function statusBadge(status, _type, row = {}) {
        const key = String(status || 'draft').toLowerCase();
        const badges = [
            `<span class="voucher-review-badge ${escapeHtml(key)}">${escapeHtml(STATUS_LABELS[key] || key)}</span>`,
        ];

        if (Number(row.is_reversal || 0) === 1) {
            badges.push('<span class="voucher-review-badge reversal">취소전표</span>');
        } else if (row.reversal_voucher_id) {
            badges.push('<span class="voucher-review-badge reversed">취소됨</span>');
        }

        return badges.join(' ');
    }

    function reviewBadge(row) {
        const state = reviewState(row);
        return `<span class="voucher-review-badge ${state.key}">${state.label}</span>`;
    }

    function selectedRows() {
        if (!table) return [];
        const selected = [];
        table.rows().every(function () {
            const row = this.data();
            if (row?.id && checkedIds.has(String(row.id))) {
                selected.push(row);
            }
        });
        return selected;
    }

    function selectedIdsByStatus(statuses = []) {
        const allowed = new Set(statuses);
        return selectedRows()
            .filter((row) => !allowed.size || allowed.has(String(row.status || '').toLowerCase()))
            .map((row) => String(row.id));
    }

    function actionIds(statuses = []) {
        const ids = selectedIdsByStatus(statuses);
        if (ids.length) return ids;
        if (checkedIds.size > 0) return [];

        const status = String(selectedVoucher?.status || '').toLowerCase();
        if (!selectedVoucher?.id) return [];
        if (statuses.length && !statuses.includes(status)) return [];
        return [String(selectedVoucher.id)];
    }

    function reverseActionIds() {
        const ids = selectedRows()
            .filter((row) => String(row.status || '').toLowerCase() === 'posted')
            .filter((row) => Number(row.is_reversal || 0) !== 1)
            .filter((row) => !row.reversal_voucher_id)
            .map((row) => String(row.id));

        if (ids.length) return ids;
        if (checkedIds.size > 0) return [];

        const status = String(selectedVoucher?.status || '').toLowerCase();
        if (!selectedVoucher?.id || status !== 'posted') return [];
        if (Number(selectedVoucher.is_reversal || 0) === 1 || selectedVoucher.reversal_voucher?.id) return [];

        return [String(selectedVoucher.id)];
    }

    function hasUnlinkedVoucher(ids = []) {
        if (!table) return false;

        const targetIds = new Set(ids.map((id) => String(id)));
        let hasUnlinked = false;
        table.rows().every(function () {
            const row = this.data() || {};
            if (!targetIds.has(String(row.id || ''))) {
                return;
            }

            const linked = String(row.transaction_id || '').trim() !== ''
                || String(row.linked_status || '').toLowerCase() === 'linked';
            if (!linked) {
                hasUnlinked = true;
            }
        });

        if (!hasUnlinked && selectedVoucher?.id && targetIds.has(String(selectedVoucher.id))) {
            hasUnlinked = !selectedVoucher.linked_transaction;
        }

        return hasUnlinked;
    }

    function hideAllButtons() {
        [rejectBtn, confirmBtn, cancelConfirmBtn, approveBtn, reverseBtn].forEach((button) => {
            button?.classList.add('d-none');
            button?.removeAttribute('disabled');
        });
    }

    function showButton(button) {
        button?.classList.remove('d-none');
        button?.removeAttribute('disabled');
    }

    function updateActionButtons() {
        const selected = selectedRows();
        const currentStatus = String(selectedVoucher?.status || '').toLowerCase();

        const hasConfirmed = selected.some((row) => String(row.status || '').toLowerCase() === 'confirmed')
            || currentStatus === 'confirmed';
        const hasReviewed = selected.some((row) => String(row.status || '').toLowerCase() === 'reviewed')
            || currentStatus === 'reviewed';
        const hasReversiblePosted = reverseActionIds().length > 0;

        hideAllButtons();

        if (hasConfirmed) {
            showButton(rejectBtn);
            showButton(confirmBtn);
            showButton(approveBtn);
            approveBtn.disabled = true;
            return;
        }

        if (hasReviewed) {
            showButton(cancelConfirmBtn);
            showButton(approveBtn);
            approveBtn.disabled = false;
            return;
        }

        if (hasReversiblePosted) {
            showButton(reverseBtn);
        }
    }

    function renderEmptyDetail() {
        selectedVoucher = null;
        titleEl.textContent = '전표를 선택해 주세요.';
        subEl.textContent = '목록에서 전표를 클릭하면 상세가 표시됩니다.';
        statusEl.textContent = '-';
        statusEl.className = 'voucher-review-status-badge';
        basicInfoEl.innerHTML = '';
        linesEl.innerHTML = '';
        totalEl.innerHTML = '';
        linkedInfoEl.textContent = '연결 정보를 불러오지 않았습니다.';
        updateActionButtons();
    }

    function renderReversalInfo(voucher = {}) {
        if (Number(voucher.is_reversal || 0) === 1) {
            const originalNo = voucher.original_voucher?.voucher_no || voucher.original_voucher_no || voucher.reversal_of || '';
            return `<span class="voucher-review-badge reversal">취소전표</span> ${escapeHtml(originalNo)}`;
        }

        if (voucher.reversal_voucher?.id || voucher.reversal_voucher_id) {
            const reversalNo = voucher.reversal_voucher?.voucher_no || voucher.reversal_voucher_no || voucher.reversal_voucher_id || '';
            return `<span class="voucher-review-badge reversed">취소됨</span> ${escapeHtml(reversalNo)}`;
        }

        return '-';
    }

    async function loadDetail(id) {
        const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);
        const voucher = json.data || {};
        selectedVoucher = voucher;

        titleEl.textContent = voucher.voucher_no || id;
        subEl.textContent = [voucher.voucher_date, voucher.summary_text].filter(Boolean).join(' / ');
        statusEl.className = `voucher-review-status-badge voucher-review-badge ${escapeHtml(voucher.status || 'draft')}`;
        statusEl.textContent = STATUS_LABELS[voucher.status] || voucher.status || 'draft';

        basicInfoEl.innerHTML = `
            <dt>전표번호</dt><dd>${escapeHtml(voucher.voucher_no || '')}</dd>
            <dt>전표일자</dt><dd>${escapeHtml(voucher.voucher_date || '')}</dd>
            <dt>상태</dt><dd>${escapeHtml(voucher.status || '')}</dd>
            <dt>취소구분</dt><dd>${renderReversalInfo(voucher)}</dd>
            <dt>적요</dt><dd>${escapeHtml(voucher.summary_text || '')}</dd>
            <dt>비고</dt><dd>${escapeHtml(voucher.note || '')}</dd>
        `;

        const lines = Array.isArray(voucher.lines) ? voucher.lines : [];
        linesEl.innerHTML = lines.length ? lines.map((line) => `
            <div class="voucher-review-line">
                <div>
                    <div class="voucher-review-line-main">${escapeHtml(line.account_text || line.account_name || line.account_id || '')}</div>
                    <div class="voucher-review-line-sub">${escapeHtml(line.line_summary || '')}</div>
                </div>
                <div class="voucher-review-line-amount">
                    <div>차 ${formatNumber(line.debit)}</div>
                    <div>대 ${formatNumber(line.credit)}</div>
                </div>
            </div>
        `).join('') : '<div class="text-muted small">전표 라인이 없습니다.</div>';

        const debit = lines.reduce((sum, line) => sum + Number(line.debit || 0), 0);
        const credit = lines.reduce((sum, line) => sum + Number(line.credit || 0), 0);
        totalEl.innerHTML = `
            <div class="voucher-review-total-row"><span>차변합계</span><strong>${formatNumber(debit)}</strong></div>
            <div class="voucher-review-total-row"><span>대변합계</span><strong>${formatNumber(credit)}</strong></div>
            <div class="voucher-review-total-row"><span>차이</span><strong>${formatNumber(debit - credit)}</strong></div>
        `;

        linkedInfoEl.textContent = voucher.linked_transaction
            ? `${voucher.linked_transaction.transaction_date || ''} / ${voucher.linked_transaction.client_name || ''} / ${voucher.linked_transaction.description || ''}`
            : '⚠ 미연결';

        updateActionButtons();
    }

    function rowClass(row = {}) {
        return reviewState(row).key === 'error' ? 'has-error' : '';
    }

    function syncRowsAfterDraw() {
        table.rows().every(function () {
            const row = this.data();
            const node = this.node();
            const id = String(row?.id || '');
            node.dataset.voucherId = id;
            node.classList.toggle('has-error', rowClass(row) === 'has-error');
            node.classList.toggle('is-selected', selectedVoucher?.id && id === String(selectedVoucher.id));
            const checkbox = node.querySelector('.voucher-review-check');
            if (checkbox) checkbox.checked = checkedIds.has(id);
        });
        const checkAll = document.getElementById('voucherReviewCheckAll');
        if (checkAll) {
            const currentRows = table.rows({ page: 'current' }).data().toArray();
            checkAll.checked = currentRows.length > 0 && currentRows.every((row) => checkedIds.has(String(row.id || '')));
        }
        countEl.textContent = `총 ${table.page.info()?.recordsDisplay ?? 0}건`;
        updateActionButtons();
    }

    function initTable() {
        table = createDataTable({
            tableSelector: '#voucherReviewTable',
            api: API.list,
            columns: [
                {
                    data: 'id',
                    title: '<input type="checkbox" id="voucherReviewCheckAll">',
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render(id) {
                        const value = escapeHtml(id || '');
                        return `<input type="checkbox" class="voucher-review-check" value="${value}">`;
                    },
                },
                { data: 'status', title: '전표상태', render: statusBadge },
                { data: null, title: '검토상태', render: reviewBadge },
                { data: 'voucher_no', title: '전표번호', defaultContent: '' },
                { data: 'voucher_date', title: '전표일자', defaultContent: '' },
                {
                    data: 'summary_text',
                    title: '적요',
                    defaultContent: '',
                    className: 'voucher-review-summary-cell',
                    render(data) {
                        return escapeHtml(data || '');
                    },
                },
                {
                    data: 'debit_total',
                    title: '차변합계',
                    className: 'text-end',
                    render(data, _, row) {
                        return formatNumber(data || row.voucher_amount || 0);
                    },
                },
                {
                    data: 'credit_total',
                    title: '대변합계',
                    className: 'text-end',
                    render(data) {
                        return formatNumber(data || 0);
                    },
                },
            ],
            ajaxData(request) {
                const filters = currentFilters();
                Object.entries(filters).forEach(([key, value]) => {
                    if (key !== 'review_status' && value) request[key] = value;
                });
                return request;
            },
            dataSrc(json) {
                const rows = Array.isArray(json?.data) ? json.data : [];
                const reviewStatus = currentFilters().review_status;
                return reviewStatus
                    ? rows.filter((row) => reviewState(row).key === reviewStatus)
                    : rows;
            },
            defaultOrder: [[4, 'desc']],
            pageLength: 20,
        });

        table.on('draw.dt xhr.dt', syncRowsAfterDraw);
        bindTableHighlight('#voucherReviewTable', table);
    }

    async function runAction(action, ids, payload = {}) {
        if (!ids.length) return;

        for (const id of ids) {
            await fetchJson(API[action], {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ id, ...payload }),
            });
        }

        checkedIds.clear();
        table.ajax.reload(null, false);
        if (selectedVoucher?.id) {
            await loadDetail(selectedVoucher.id).catch(renderEmptyDetail);
        }
    }

    function openRejectModal(ids) {
        pendingRejectIds = ids;
        if (!pendingRejectIds.length) return;

        if (!rejectModal || !rejectReasonEl) {
            return;
        }

        rejectReasonEl.value = '';
        rejectReasonErrorEl?.classList.add('d-none');
        rejectModal.show();
        setTimeout(() => rejectReasonEl.focus(), 150);
    }

    async function confirmReject() {
        const reason = String(rejectReasonEl?.value || '').trim();
        if (!reason) {
            rejectReasonErrorEl?.classList.remove('d-none');
            rejectReasonEl?.focus();
            return;
        }

        rejectReasonErrorEl?.classList.add('d-none');
        confirmRejectBtn.disabled = true;

        try {
            await runAction('reject', pendingRejectIds, { reason });
            rejectModal?.hide();
        } finally {
            confirmRejectBtn.disabled = false;
        }
    }

    function bindEvents() {
        window.jQuery(tableEl).on('click', 'tbody tr', (event) => {
            if (event.target.closest('input')) return;
            const row = event.currentTarget;
            const data = table.row(row).data();
            if (!data?.id) return;

            tableEl.querySelectorAll('tbody tr').forEach((tr) => tr.classList.remove('is-selected'));
            row.classList.add('is-selected');
            void loadDetail(data.id);
        });

        window.jQuery(tableEl).on('dblclick', 'tbody tr', (event) => {
            const data = table.row(event.currentTarget).data();
            if (data?.id) void loadDetail(data.id);
        });

        tableEl.addEventListener('change', (event) => {
            const checkbox = event.target.closest('.voucher-review-check');
            if (!checkbox) return;
            if (checkbox.checked) {
                checkedIds.add(String(checkbox.value));
            } else {
                checkedIds.delete(String(checkbox.value));
            }
            syncRowsAfterDraw();
        });

        tableEl.addEventListener('change', (event) => {
            if (event.target.id !== 'voucherReviewCheckAll') return;
            const checked = event.target.checked;
            table.rows({ page: 'current' }).every(function () {
                const id = String(this.data()?.id || '');
                if (!id) return;
                if (checked) checkedIds.add(id);
                else checkedIds.delete(id);
            });
            syncRowsAfterDraw();
        });

        filterForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            checkedIds.clear();
            renderEmptyDetail();
            table.ajax.reload();
        });

        resetFilterBtn?.addEventListener('click', resetFilters);
        rejectBtn?.addEventListener('click', () => openRejectModal(actionIds(['confirmed'])));
        confirmRejectBtn?.addEventListener('click', () => void confirmReject());
        rejectReasonEl?.addEventListener('input', () => {
            if (String(rejectReasonEl.value || '').trim()) {
                rejectReasonErrorEl?.classList.add('d-none');
            }
        });
        confirmBtn?.addEventListener('click', () => void runAction('completeReview', actionIds(['confirmed'])));
        cancelConfirmBtn?.addEventListener('click', () => void runAction('cancelCompleteReview', actionIds(['reviewed'])));
        approveBtn?.addEventListener('click', () => {
            const ids = actionIds(['reviewed']);
            if (!ids.length) return;
            if (hasUnlinkedVoucher(ids) && !window.confirm('거래가 연결되지 않은 전표가 있습니다. 승인 후에도 거래 연결은 가능하지만 회계에는 영향이 없습니다. 계속 승인하시겠습니까?')) {
                return;
            }
            void runAction('post', ids);
        });
        reverseBtn?.addEventListener('click', () => {
            const ids = reverseActionIds();
            if (!ids.length) return;
            if (!window.confirm('정말 취소하시겠습니까?')) return;
            void runAction('reverse', ids);
        });
    }

    initTable();
    bindEvents();
    renderEmptyDetail();
})();
