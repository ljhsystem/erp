import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import '/public/assets/js/components/trash-manager.js';

(() => {
    const tableEl = document.getElementById('voucherReviewTable');
    const countEl = document.getElementById('voucherReviewCount');
    const layoutEl = document.querySelector('.voucher-review-layout');
    const detailPanel = document.querySelector('.voucher-review-detail-panel');
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
        reorder: '/api/ledger/voucher/reorder',
        remove: '/api/ledger/voucher/delete',
        trash: '/api/ledger/voucher/trash',
        restore: '/api/ledger/voucher/restore',
        purge: '/api/ledger/voucher/purge',
        purgeAll: '/api/ledger/voucher/purge-all',
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

    function notify(type = 'info', message = '') {
        const notifier = window.AppNotify || window.AppCore?.AppNotify || window.AppCore;
        if (typeof notifier?.notify === 'function') {
            notifier.notify(type, message);
            return;
        }
        if (type === 'error') {
            console.error(message);
        }
    }

    function getVoucherSortNo(row = {}) {
        const numericSortNo = Number(String(row.sort_no ?? '').replace(/,/g, ''));
        return Number.isFinite(numericSortNo) ? numericSortNo : 0;
    }

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.journal = function (row = {}) {
        return `
            <td>${escapeHtml(row.voucher_no ?? '')}</td>
            <td>${escapeHtml(row.voucher_date ?? '')}</td>
            <td>${statusBadge(row.status ?? 'draft')}</td>
            <td class="text-end">${escapeHtml(formatNumber(row.voucher_amount ?? row.debit_total ?? 0))}</td>
            <td>${escapeHtml(row.summary_text ?? '')}</td>
            <td>${escapeHtml(row.deleted_at ?? '')}</td>
            <td>${escapeHtml(actorDisplay(row, 'deleted_by'))}</td>
            <td class="text-nowrap">
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id ?? '')}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id ?? '')}">영구삭제</button>
            </td>
        `;
    };

    function renderDragHandle() {
        return '<i class="bi bi-list"></i>';
    }

    function openTrashModal() {
        const modalEl = document.getElementById('journalTrashModal');
        if (!modalEl) {
            notify('warning', '전표 휴지통 모달을 찾을 수 없습니다.');
            return;
        }

        modalEl.dataset.listUrl = API.trash;
        modalEl.dataset.restoreUrl = API.restore;
        modalEl.dataset.deleteUrl = API.purge;
        modalEl.dataset.deleteAllUrl = API.purgeAll;

        window.bootstrap?.Modal?.getOrCreateInstance(modalEl, { focus: false })?.show();
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
        const ids = new Set(table.getSelectedIds?.().map((id) => String(id)) || []);
        const selected = [];
        table.rows().every(function () {
            const row = this.data();
            if (row?.id && ids.has(String(row.id))) {
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
        if ((table?.getSelectedIds?.() || []).length > 0) return [];

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
        if ((table?.getSelectedIds?.() || []).length > 0) return [];

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

    function closeDetailPanel() {
        layoutEl?.classList.remove('is-detail-open');
        tableEl.querySelectorAll('tbody tr').forEach((tr) => tr.classList.remove('is-selected'));
    }

    function openDetailPanel(rowEl = null) {
        layoutEl?.classList.add('is-detail-open');
        if (!rowEl) return;
        tableEl.querySelectorAll('tbody tr').forEach((tr) => tr.classList.remove('is-selected'));
        rowEl.classList.add('is-selected');
    }

    function renderEmptyDetail() {
        selectedVoucher = null;
        closeDetailPanel();
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
        layoutEl?.classList.add('is-detail-open');
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

        const linkedEvidences = Array.isArray(voucher.linked_evidences) ? voucher.linked_evidences : [];
        const linkedEvidenceText = linkedEvidences.map((evidence) => [
            evidence.source_type || '',
            evidence.evidence_date || '',
            evidence.client_name || '',
            evidence.source_key || evidence.id || '',
        ].filter(Boolean).join(' / '));
        linkedInfoEl.innerHTML = [
            voucher.linked_transaction
                ? `거래: ${escapeHtml([voucher.linked_transaction.transaction_date || '', voucher.linked_transaction.client_name || '', voucher.linked_transaction.description || ''].filter(Boolean).join(' / '))}`
                : '거래: 미연결',
            linkedEvidenceText.length
                ? `증빙: ${linkedEvidenceText.map((text) => '<span class="d-block">' + escapeHtml(text) + '</span>').join('')}`
                : '증빙: 미연결',
        ].join('<br>');

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
        });
        countEl.textContent = `총 ${table.page.info()?.recordsDisplay ?? 0}건`;
        updateActionButtons();
    }

    function initTable() {
        table = createDataTable({
            tableSelector: '#voucherReviewTable',
            api: API.list,
            columns: [
                {
                    data: null,
                    title: '<i class="bi bi-arrows-move"></i>',
                    className: 'reorder-handle no-sort no-colvis text-center no-export',
                    headerClassName: 'no-colvis text-center no-export',
                    orderable: false,
                    searchable: false,
                    defaultContent: '<i class="bi bi-list"></i>',
                    render() {
                        return renderDragHandle();
                    },
                },
                {
                    data: 'sort_no',
                    title: '순번',
                    className: 'text-center voucher-review-sort-no-cell',
                    render(data, type, row) {
                        const sortNo = getVoucherSortNo(row);
                        if (type === 'sort' || type === 'type') {
                            return sortNo;
                        }

                        return escapeHtml(data || sortNo || '');
                    },
                },
                { data: 'status', title: '전표상태', render: statusBadge },
                {
                    data: 'review_status',
                    title: '\uAC80\uD1A0\uC0C1\uD0DC',
                    render(_data, type, row) {
                        const state = reviewState(row).key;
                        if (type === 'sort' || type === 'type') {
                            return state;
                        }

                        return reviewBadge(_data, type, row);
                    },
                },
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
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '엑셀 다운로드',
                    className: 'btn btn-outline-success btn-sm',
                    title: '전표검토승인',
                    filename: '전표검토승인',
                    exportOptions: {
                        columns: ':visible:not(.no-export):not(.no-colvis)',
                    },
                },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal,
                },
            ],
            deleteApi: API.remove,
            dataSrc(json) {
                return Array.isArray(json?.data) ? json.data : [];
            },
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
        });

        table.on('draw.dt xhr.dt', syncRowsAfterDraw);
        bindRowReorder(table, {
            api: API.reorder,
            onSuccess() {
                notify('success', '전표 순번이 변경되었습니다.');
                table?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '전표 순번 변경에 실패했습니다.');
                table?.ajax.reload(null, false);
            },
        });
        bindTableHighlight('#voucherReviewTable', table);
    }

    function initSearchForm() {
        SearchForm({
            table,
            apiList: API.list,
            tableId: 'voucherReview',
            defaultSearchField: 'voucher_no',
            dateOptions: [
                { value: 'voucher_date', label: '전표일자' },
                { value: 'updated_at', label: '수정일시' },
            ],
            excludeFields: ['id'],
        });

        const searchForm = document.getElementById('voucherReviewSearchConditionsForm');
        const resetButton = document.getElementById('voucherReviewResetButton');
        searchForm?.addEventListener('submit', () => {
            table?.clearSelectedIds?.();
            renderEmptyDetail();
        });
        resetButton?.addEventListener('click', () => {
            table?.clearSelectedIds?.();
            renderEmptyDetail();
        });
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

        table?.clearSelectedIds?.();
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
            if (event.target.closest('input, .reorder-handle')) return;
            const row = event.currentTarget;
            const data = table.row(row).data();
            if (!data?.id) return;

            openDetailPanel(row);
            void loadDetail(data.id);
        });

        window.jQuery(tableEl).on('dblclick', 'tbody tr', (event) => {
            if (event.target.closest('input, .reorder-handle')) return;
            openDetailPanel(event.currentTarget);
            const data = table.row(event.currentTarget).data();
            if (data?.id) void loadDetail(data.id);
        });

        document.addEventListener('click', (event) => {
            if (!layoutEl?.classList.contains('is-detail-open')) return;
            if (event.target.closest('#voucherReviewTable tbody tr')) return;
            if (detailPanel?.contains(event.target)) return;
            renderEmptyDetail();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            if (!layoutEl?.classList.contains('is-detail-open')) return;
            renderEmptyDetail();
        });

        tableEl.addEventListener('datatable:selection-changed', () => {
            updateActionButtons();
        });

        tableEl.addEventListener('datatable:soft-delete-completed', () => {
            renderEmptyDetail();
        });

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

    document.addEventListener('trash:changed', (event) => {
        if (event.detail?.type === 'journal') {
            table?.clearSelectedIds?.();
            table?.ajax.reload(null, false);
            renderEmptyDetail();
        }
    });

    document.addEventListener('trash:detail-render', (event) => {
        const detail = event.detail || {};
        if (detail.type !== 'journal') {
            return;
        }

        const detailEl = detail.modal?.querySelector('#journal-trash-detail');
        const row = detail.data || {};
        if (!detailEl) {
            return;
        }

        detailEl.innerHTML = `
            <div class="voucher-review-trash-detail">
                <dl class="row mb-0 small">
                    <dt class="col-4">전표번호</dt>
                    <dd class="col-8">${escapeHtml(row.voucher_no ?? '-')}</dd>
                    <dt class="col-4">전표일자</dt>
                    <dd class="col-8">${escapeHtml(row.voucher_date ?? '-')}</dd>
                    <dt class="col-4">상태</dt>
                    <dd class="col-8">${statusBadge(row.status ?? 'draft')}</dd>
                    <dt class="col-4">전표금액</dt>
                    <dd class="col-8">${escapeHtml(formatNumber(row.voucher_amount ?? row.debit_total ?? 0))}</dd>
                    <dt class="col-4">전표적요</dt>
                    <dd class="col-8">${escapeHtml(row.summary_text ?? '-')}</dd>
                    <dt class="col-4">삭제일시</dt>
                    <dd class="col-8">${escapeHtml(row.deleted_at ?? '-')}</dd>
                    <dt class="col-4">삭제자</dt>
                    <dd class="col-8">${escapeHtml(actorDisplay(row, 'deleted_by'))}</dd>
                </dl>
            </div>
        `;
    });

    initTable();
    initSearchForm();
    bindEvents();
    renderEmptyDetail();
})();
