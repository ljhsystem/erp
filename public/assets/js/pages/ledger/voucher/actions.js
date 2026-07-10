export function registerActions(ctx) {
    const state = ctx.state;
    const {
        actorDisplay,
        initCodeSelectControls,
        onCodeOptionsLoaded,
        API,
        form,
        modalEl,
        addLineBtn,
        voucherStatusEl,
        voucherNoDisplayEl,
        voucherDateEl,
        summaryTextEl,
        summarySuggestionsEl,
        modalDeleteBtn,
        modalRequestReviewBtn,
        modalCancelReviewBtn,
        evidenceSearchBody,
        evidenceSearchKeywordEl,
        linkedEvidenceIdEl,
        linkedEvidenceSummaryEl,
        selectEvidenceBtn,
        clearEvidenceLinkBtn,
        searchEvidenceBtn,
        modal,
        evidenceModal,
        lineGridBridge,
        basicInfoBridge,
        escapeHtml,
        notify,
        queueEvidenceSearch,
        fetchJson,
        formatDateInputValue,
        closeSummaryAutocomplete,
        setSummaryAutocompleteActive,
        applySummaryAutocompleteItem,
        queueSummaryAutocompleteSearch,
        parseAmountValue,
        formatAmountValue,
        setAmountInputValue,
        getAmountInputTarget,
        renderStatusFlow,
        setRejectReason,
        setLinkedEvidence,
        setVoucherDetailMeta,
        setModalTitle,
        voucherEditLockMessage,
        applyVoucherState,
        initJournalLineReorder,
        calculateTotals,
        addLineRow,
        resetModal,
        setJournalModalLoading,
        collectLines,
        validateBeforeSave,
        initJournalTable,
        reloadJournalTable,
        traceVoucherStep,
    } = ctx;

function bindDetailCardCollapses() {
    if (!modalEl || !window.bootstrap?.Collapse) {
        return;
    }

    modalEl.querySelectorAll('.journal-card-toggle[data-bs-target]').forEach((button) => {
        if (button.dataset.collapseBound === '1') {
            return;
        }

        const targetSelector = String(button.getAttribute('data-bs-target') || '').trim();
        if (targetSelector === '' || !targetSelector.startsWith('#')) {
            return;
        }

        const targetEl = modalEl.querySelector(targetSelector);
        if (!targetEl) {
            return;
        }

        const collapse = bootstrap.Collapse.getOrCreateInstance(targetEl, { toggle: false });

        const syncState = () => {
            const expanded = targetEl.classList.contains('show');
            button.classList.toggle('collapsed', !expanded);
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            window.requestAnimationFrame(() => {
                modal?.handleUpdate?.();
            });
        };

        button.removeAttribute('data-bs-toggle');

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (targetEl.classList.contains('show')) {
                collapse.hide();
                return;
            }

            collapse.show();
        });

        targetEl.addEventListener('shown.bs.collapse', syncState);
        targetEl.addEventListener('hidden.bs.collapse', syncState);
        syncState();
        button.dataset.collapseBound = '1';
    });
}

async function openCreateModal() {
    resetModal();
    if (voucherNoDisplayEl) {
        voucherNoDisplayEl.value = '자동발번';
    }
    await setVoucherDetailMeta?.({ status: 'DRAFT' });
    bindDetailCardCollapses();
    modal?.show();
    await addLineRow();
    await addLineRow();
}

async function loadDetail(id) {
    traceVoucherStep?.('loadDetail', {
        input: {
            id,
        },
    });
    resetModal();
    setModalTitle('edit');
    setJournalModalLoading(true);
    bindDetailCardCollapses();
    modal?.show();

    try {
        const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);

        if (!json.success || !json.data) {
            notify('error', json.message || '전표 상세 정보를 불러오지 못했습니다.');
            modal?.hide();
            return;
        }

        const data = hydrateVoucher(json.data);
        traceVoucherStep?.('renderVoucher', {
            input: data,
        });
        document.getElementById('journal_id').value = data.id || '';
        if (voucherNoDisplayEl) {
            voucherNoDisplayEl.value = data.voucher_no || '-';
        }
        voucherDateEl.value = data.voucher_date || '';
        voucherStatusEl.value = data.status || 'DRAFT';
        setRejectReason(data.status || 'DRAFT', data.reject_reason || '');
        document.getElementById('voucher_summary_text').value = data.summary || '';
        setLinkedEvidence(data);
        await setVoucherDetailMeta?.(data);

        if (Array.isArray(data.lines) && data.lines.length > 0) {
            await lineGridBridge?.loadLines?.(data.lines);
        } else {
            await addLineRow();
            await addLineRow();
        }

        const summary = calculateTotals();
        applyVoucherState(data.status || 'DRAFT', data, { summary });
        traceVoucherStep?.('renderVoucher', {
            input: data,
            output: {
                status: voucherStatusEl?.value || '',
                lineCount: Array.isArray(data.lines) ? data.lines.length : 0,
            },
        });
        traceVoucherStep?.('loadDetail', {
            input: {
                id,
            },
            output: data,
        });
    } catch (error) {
        console.error('[ledger-journal] loadDetail failed', error);
        traceVoucherStep?.('loadDetail', {
            input: {
                id,
            },
            output: {
                error: error?.message || 'load-detail-failed',
            },
        });
        notify('error', '전표 상세 정보를 불러오지 못했습니다.');
        modal?.hide();
    } finally {
        setJournalModalLoading(false);
    }
}

function hydrateVoucher(data = {}) {
    const hydrated = {
        ...data,
        id: data.id || '',
        voucher_no: data.voucher_no || data.sort_no || '-',
        voucher_date: data.voucher_date || '',
        status: data.status || 'DRAFT',
        summary: data.summary || '',
        reject_reason: data.reject_reason || '',
        lines: Array.isArray(data.lines) ? data.lines : [],
    };

    traceVoucherStep?.('hydrateVoucher', {
        input: data,
        output: hydrated,
    });
    return hydrated;
}

async function saveVoucher(options = {}) {
    const closeModal = options.closeModal !== false;
    const shouldReload = options.reload !== false;
    const shouldNotify = options.notify !== false;
    const successMessage = options.successMessage || '전표가 저장되었습니다.';
    const lockMessage = voucherEditLockMessage();
    if (lockMessage !== '') {
        notify('warning', lockMessage);
        return null;
    }
    const formData = new FormData(form);
    formData.set('lines', JSON.stringify(collectLines()));
    formData.set('linked_evidence_id', linkedEvidenceIdEl?.value || '');

    const json = await fetchJson(API.save, {
        method: 'POST',
        body: formData,
    });

    if (!json.success) {
        notify('error', json.message || '전표 저장에 실패했습니다.');
        return null;
    }

    const idInput = form.querySelector('[name="id"]');
    if (json.data?.id && idInput) {
        idInput.value = json.data.id;
    }

    if (shouldNotify) {
        notify('success', successMessage);
    }
    if (closeModal) {
        modal?.hide();
    }
    if (shouldReload) {
        reloadJournalTable();
    }
    document.dispatchEvent(new CustomEvent('journal:voucher-saved', {
        detail: { data: json.data || null },
    }));
    return json.data || null;
}

async function requestVoucherReview() {
    try {
        calculateTotals();

        if (!validateBeforeSave({ requireJournalReady: true })) {
            return;
        }

        const saved = await saveVoucher({
            closeModal: false,
            reload: false,
            notify: false,
            successMessage: '전표가 임시저장되었습니다.',
        });
        if (!saved) {
            return;
        }

        const voucherId = saved?.id || form.querySelector('[name="id"]')?.value || '';
        if (!voucherId) {
            notify('error', '검토요청할 전표 ID를 확인할 수 없습니다.');
            return;
        }

        const formData = new FormData();
        formData.append('id', voucherId);

        const json = await fetchJson(API.requestReview, {
            method: 'POST',
            body: formData,
        });

        if (!json.success) {
            notify('error', json.message || '검토요청 처리에 실패했습니다.');
            return;
        }

        notify('success', '검토요청이 완료되었습니다.');
        await loadDetail(voucherId);
        reloadJournalTable();
    } catch (error) {
        console.error('[ledger-journal] request review failed', error);
        notify('error', error.message || '검토요청 처리에 실패했습니다.');
    }
}

async function saveEvidenceLinkOnly() {
    const voucherId = form.querySelector('[name="id"]')?.value || '';
    if (!voucherId) {
        notify('error', '증빙을 연결할 전표 ID를 확인할 수 없습니다.');
        return null;
    }

    const formData = new FormData();
    formData.append('id', voucherId);
    formData.append('linked_evidence_id', linkedEvidenceIdEl?.value || '');

    const json = await fetchJson(API.linkEvidence, {
        method: 'POST',
        body: formData,
    });

    if (!json.success) {
        notify('error', json.message || '증빙 연결 저장에 실패했습니다.');
        return null;
    }

    setLinkedEvidence({
        linked_evidence: json.data?.linked_evidence || null,
        evidence_id: json.data?.evidence_id || linkedEvidenceIdEl?.value || '',
    });
    notify('success', '증빙 연결이 저장되었습니다.');
    reloadJournalTable();
    return json.data || null;
}

function confirmEvidenceUnlink() {
    const voucherNo = voucherNoEl?.value || form.querySelector('[name="voucher_no"]')?.value || '현재 전표';
    const summary = linkedEvidenceSummaryEl?.textContent?.trim() || '연결된 증빙';
    return window.confirm(
        `${voucherNo}에 연결된 증빙을 해제합니다.\n\n현재 연결: ${summary}\n\n해제하면 이 전표는 증빙 미연결 상태가 되며, 필요 시 다시 증빙을 선택해야 합니다. 계속 해제하시겠습니까?`
    );
}

async function clearEvidenceLinkOnly() {
    const voucherId = form.querySelector('[name="id"]')?.value || '';
    const evidenceId = linkedEvidenceIdEl?.value || '';
    if (evidenceId && !confirmEvidenceUnlink()) {
        return null;
    }
    setLinkedEvidence({});
    if (!voucherId || !evidenceId) {
        return null;
    }

    const formData = new FormData();
    formData.append('id', voucherId);
    formData.append('linked_evidence_id', evidenceId);

    const json = await fetchJson(API.unlinkEvidence, {
        method: 'POST',
        body: formData,
    });

    if (!json.success) {
        notify('error', json.message || '증빙 연결 해제에 실패했습니다.');
        return null;
    }

    notify('success', '증빙 연결이 해제되었습니다.');
    reloadJournalTable();
    return json.data || null;
}

async function cancelVoucherReview() {
    const voucherId = form.querySelector('[name="id"]')?.value || '';
    if (!voucherId) {
        notify('error', '검토요청 취소할 전표 ID를 확인할 수 없습니다.');
        return;
    }

    const formData = new FormData();
    formData.append('id', voucherId);

        const json = await fetchJson(API.cancelReviewRequest, {
        method: 'POST',
        body: formData,
    });

    if (!json.success) {
        notify('error', json.message || '검토요청 취소에 실패했습니다.');
        return;
    }

    notify('success', '검토요청이 취소되었습니다.');
    reloadJournalTable();
    await loadDetail(voucherId);
}

async function deleteVoucher(id) {
    const formData = new FormData();
    formData.append('id', id);

    const json = await fetchJson(API.remove, {
        method: 'POST',
        body: formData,
    });

    if (!json.success) {
        notify('error', json.message || '전표 삭제에 실패했습니다.');
        return;
    }

    notify('success', '전표가 삭제되었습니다.');
    modal?.hide();
    reloadJournalTable();
}

function findJournalRowData(element) {
    if (!state.journalTable || !element) {
        return null;
    }

    const tr = element.closest('tr');
    const row = tr ? state.journalTable.row(tr).data() : null;
    if (row) {
        return row;
    }

    const id = String(element.dataset.id || '').trim();
    if (!id) {
        return null;
    }

    return state.journalTable
        .rows()
        .data()
        .toArray()
        .find((item) => String(item.id || '') === id) || null;
}
async function handleJournalAction(action, row = {}, button = null) {
    const id = String(row.id || button?.dataset.id || '').trim();
    if (!id) {
        notify('error', '전표 ID를 확인할 수 없습니다.');
        return;
    }

    if (action === 'edit' || action === 'view') {
        resetModal();
        await loadDetail(id);
        return;
    }


    if (action === 'copy') {
        notify('info', '전표 복사는 전표 상세 화면에서 저장 후 새 행으로 연결되도록 별도 구현이 필요합니다.');
        return;
    }

    if (action === 'evidence') {
        const evidenceId = String(button?.dataset.evidenceId || row.evidence_id || '').trim();
        if (!evidenceId) {
            notify('warning', '연결된 원본증빙을 찾을 수 없습니다.');
            return;
        }

        notify('info', '원본증빙 바로 연결은 추후 공용 모달 또는 상세 화면에서 지원될 예정입니다.');
        return;
    }

    if (action === 'delete') {
        if (!window.confirm('선택한 전표를 삭제하시겠습니까?')) {
            return;
        }
        await deleteVoucher(id);
    }
}
function renderEvidenceSearchRows(rows = []) {
    if (!evidenceSearchBody) {
        return;
    }

    if (!rows.length) {
        evidenceSearchBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted py-4">선택할 증빙이 없습니다.</td>
            </tr>
        `;
        return;
    }

    const linkedRowClass = (row) => {
        if (row.is_linked_to_current_voucher) return ' class="journal-search-row-linked-current"';
        if (row.is_linked_to_other_voucher) return ' class="journal-search-row-linked-other"';
        return '';
    };
    const linkedBadge = (row) => {
        const voucher = row.linked_voucher || {};
        const voucherNo = voucher.voucher_no || '';
        if (row.is_linked_to_current_voucher) {
            return '<span class="journal-search-link-badge current">현재 전표 연결</span>';
        }
        if (row.is_linked_to_other_voucher) {
            return `<span class="journal-search-link-badge other">다른 전표 연결${voucherNo ? `: ${escapeHtml(voucherNo)}` : ''}</span>`;
        }
        return '';
    };

    evidenceSearchBody.innerHTML = rows.map((row, index) => `
        <tr data-index="${index}"${linkedRowClass(row)}>
            <td>${escapeHtml(row.evidence_date || row.processed_at || row.created_at || '')}</td>
            <td>${escapeHtml(row.display_type || row.format_name || importTypeLabel(row.source_type || ''))}</td>
            <td>${escapeHtml(row.client_name || row.counterparty_name || '-')}</td>
            <td class="text-end">${escapeHtml(formatAmountValue(row.display_amount || 0) || '0')}</td>
            <td>
                <div class="journal-search-main">${escapeHtml(row.display_summary || '-')}</div>
                ${row.display_key ? `<div class="journal-search-sub">${escapeHtml(row.display_key)}</div>` : ''}
                ${linkedBadge(row)}
            </td>
            <td class="text-center">
                <button type="button"
                        class="btn btn-outline-primary btn-sm btn-pick-evidence">${row.is_linked_to_current_voucher ? '선택됨' : '선택'}</button>
            </td>
        </tr>
    `).join('');
}

async function loadEvidenceSearch() {
    if (!evidenceSearchBody) {
        return;
    }

    evidenceSearchBody.innerHTML = `
        <tr>
            <td colspan="6" class="text-center text-muted py-4">증빙 목록을 불러오는 중입니다.</td>
        </tr>
    `;

    try {
        const query = new URLSearchParams();
        const keyword = evidenceSearchKeywordEl?.value?.trim() || '';
        if (keyword) {
            query.set('q', keyword);
        }
        const voucherId = form.querySelector('[name="id"]')?.value || '';
        if (voucherId) {
            query.set('voucher_id', voucherId);
        }

        const json = await fetchJson(`${API.evidenceSearch}?${query.toString()}`);
        if (!json.success) {
            throw new Error(json.message || '증빙 목록을 불러오지 못했습니다.');
        }

        state.evidenceRows = Array.isArray(json.data) ? json.data : [];
        renderEvidenceSearchRows(state.evidenceRows);
    } catch (error) {
        console.error('[ledger-journal] evidence search failed', error);
        state.evidenceRows = [];
        state.evidenceRows = [];
        evidenceSearchBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger py-4">증빙 목록을 불러오지 못했습니다.</td>
            </tr>
        `;
    }
}

function bindEvents() {
    if (state.voucherEventsBound) {
        return;
    }
    state.voucherEventsBound = true;

    bindDetailCardCollapses();

    addLineBtn?.addEventListener('click', () => {
        void addLineRow();
    });

    voucherDateEl.addEventListener('input', () => {
        voucherDateEl.value = formatDateInputValue(voucherDateEl.value);
    });

    summaryTextEl?.addEventListener('input', () => {
        if (summaryTextEl.disabled || summaryTextEl.readOnly) {
            closeSummaryAutocomplete();
            return;
        }
        queueSummaryAutocompleteSearch();
    });

    summaryTextEl?.addEventListener('focus', () => {
        queueSummaryAutocompleteSearch();
    });

    summaryTextEl?.addEventListener('keydown', (event) => {
        if (!summarySuggestionsEl || summarySuggestionsEl.classList.contains('d-none')) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setSummaryAutocompleteActive(state.summaryAutocompleteActiveIndex + 1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setSummaryAutocompleteActive(state.summaryAutocompleteActiveIndex - 1);
            return;
        }

        if (event.key === 'Enter' && state.summaryAutocompleteActiveIndex >= 0) {
            event.preventDefault();
            applySummaryAutocompleteItem(state.summaryAutocompleteActiveIndex);
            return;
        }

        if (event.key === 'Escape') {
            closeSummaryAutocomplete();
        }
    });

    summarySuggestionsEl?.addEventListener('mousedown', (event) => {
        event.preventDefault();
        const item = event.target.closest('.summary-autocomplete-item');
        if (!item) {
            return;
        }

        applySummaryAutocompleteItem(Number(item.dataset.index || -1));
    });

    document.addEventListener('mousedown', (event) => {
        if (!summarySuggestionsEl || summarySuggestionsEl.classList.contains('d-none')) {
            return;
        }

        if (event.target.closest('.summary-autocomplete-wrap')) {
            return;
        }

        closeSummaryAutocomplete();
    });

    modalEl.addEventListener('hidden.bs.modal', closeSummaryAutocomplete);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        calculateTotals();

        const currentStatus = String(voucherStatusEl?.value || 'DRAFT').trim();
        const lockMessage = voucherEditLockMessage(currentStatus);
        if (lockMessage !== '') {
            notify('warning', lockMessage);
            return;
        }

        if (!validateBeforeSave()) {
            return;
        }

        void saveVoucher();
    });

    modalRequestReviewBtn?.addEventListener('click', () => {
        void requestVoucherReview();
    });

    modalCancelReviewBtn?.addEventListener('click', () => {
        void cancelVoucherReview();
    });

    document.addEventListener('input', (event) => {
        const input = getAmountInputTarget(event.target);
        if (!input) {
            return;
        }

        setAmountInputValue(input);
        if (input.classList.contains('line-debit') && parseAmountValue(input.value) > 0) {
            const credit = input.closest('tr')?.querySelector('.line-credit');
            if (credit) credit.value = '';
        }
        if (input.classList.contains('line-credit') && parseAmountValue(input.value) > 0) {
            const debit = input.closest('tr')?.querySelector('.line-debit');
            if (debit) debit.value = '';
        }
        calculateTotals();
    });

    document.addEventListener('focusin', (event) => {
        const input = getAmountInputTarget(event.target);
        if (!input) {
            return;
        }

        setAmountInputValue(input, { formatted: false });
    });
    modalDeleteBtn?.addEventListener('click', () => {
        const id = document.getElementById('journal_id')?.value || '';
        if (id && window.confirm('전표를 삭제하시겠습니까?')) {
            void deleteVoucher(id);
        }
    });

    selectEvidenceBtn?.addEventListener('click', () => {
        if (!evidenceModal) {
            notify('warning', '증빙 선택 모달을 찾을 수 없습니다.');
            return;
        }

        if (evidenceSearchKeywordEl) {
            evidenceSearchKeywordEl.value = '';
        }

        evidenceModal.show();
        void loadEvidenceSearch();
    });


    clearEvidenceLinkBtn?.addEventListener('click', () => {
        void clearEvidenceLinkOnly();
    });

    searchEvidenceBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        void loadEvidenceSearch();
    });

    evidenceSearchKeywordEl?.addEventListener('input', () => {
        queueEvidenceSearch();
    });

    evidenceSearchKeywordEl?.addEventListener('search', () => {
        queueEvidenceSearch();
    });

    evidenceSearchKeywordEl?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            void loadEvidenceSearch();
        }
    });

    evidenceSearchBody?.addEventListener('click', (event) => {
        const button = event.target.closest('.btn-pick-evidence');
        if (!button) {
            return;
        }

        const rowEl = button.closest('tr');
        const index = Number(rowEl?.dataset.index ?? -1);
        const row = state.evidenceRows[index];
        if (!row) {
            return;
        }
        if (row.is_linked_to_other_voucher) {
            const voucherNo = row.linked_voucher?.voucher_no || '다른 전표';
            const ok = window.confirm(
                `이 증빙은 이미 ${voucherNo}에 연결되어 있습니다.\n\n현재 전표로 연결을 변경하려면 기존 연결 상태를 먼저 확인하는 것이 좋습니다. 그래도 선택하시겠습니까?`
            );
            if (!ok) return;
        }

        setLinkedEvidence({
            linked_evidence: row,
            evidence_id: row.id || '',
        });
        evidenceModal?.hide();
        if (form.querySelector('[name="id"]')?.value) {
            void saveEvidenceLinkOnly();
        }
    });
}

if (!state.voucherTrashEventsBound) {
    state.voucherTrashEventsBound = true;

document.addEventListener('trash:changed', (event) => {
    if (event.detail?.type === 'journal') {
        reloadJournalTable();
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

    const deletedBy = actorDisplay(row, 'deleted_by');
    detailEl.innerHTML = `
        <div class="journal-trash-detail">
            <dl class="row mb-0 small">
                <dt class="col-4">전표번호</dt>
                <dd class="col-8">${escapeHtml(row.voucher_no ?? '-')}</dd>
                <dt class="col-4">전표일자</dt>
                <dd class="col-8">${escapeHtml(row.voucher_date ?? '-')}</dd>
                <dt class="col-4">상태</dt>
                <dd class="col-8">${renderStatusFlow(row.status ?? 'DRAFT', 'detail')}</dd>
                <dt class="col-4">전표금액</dt>
                <dd class="col-8">${escapeHtml(formatAmountValue(row.debit_total ?? 0) || '0')}</dd>
                <dt class="col-4">전표 적요</dt>
                <dd class="col-8">${escapeHtml(row.summary ?? '-')}</dd>
                <dt class="col-4">삭제일시</dt>
                <dd class="col-8">${escapeHtml(row.deleted_at ?? '-')}</dd>
                <dt class="col-4">삭제자</dt>
                <dd class="col-8">${escapeHtml(deletedBy || '-')}</dd>
            </dl>
        </div>
    `;
});
}

window.LedgerJournalModal = {
    async openVoucher(id, options = {}) {
        const voucherId = String(id || '').trim();
        if (!voucherId) {
            notify('warning', '열 전표 ID가 전달되지 않았습니다.');
            return;
        }
        if (typeof options.onClosed === 'function') {
            modalEl.addEventListener('hidden.bs.modal', options.onClosed, { once: true });
        }
        resetModal();
        await loadDetail(voucherId);
    },
    async openCreate(options = {}) {
        if (typeof options.onClosed === 'function') {
            modalEl.addEventListener('hidden.bs.modal', options.onClosed, { once: true });
        }
        await openCreateModal();
    },
};

async function boot() {
    await initCodeSelectControls(modalEl);
    basicInfoBridge.bindDateInputs(modalEl);
    onCodeOptionsLoaded(() => {
        state.journalTable?.rows().invalidate('data').draw(false);
    });
    initJournalTable();
    initJournalLineReorder();
    bindDetailCardCollapses();
    bindEvents();
}



    Object.assign(ctx, {
        openCreateModal,
        loadDetail,
        saveVoucher,
        requestVoucherReview,
        saveEvidenceLinkOnly,
        confirmEvidenceUnlink,
        clearEvidenceLinkOnly,
        cancelVoucherReview,
        deleteVoucher,
        findJournalRowData,
        handleJournalAction,
        renderEvidenceSearchRows,
        loadEvidenceSearch,
        bindEvents,
        boot
    });

    return ctx;
}
