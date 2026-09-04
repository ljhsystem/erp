import { createRecommendationSnapshot } from './recommendation-tracking.js';
import { runDeleteProgress } from '/public/assets/js/common/delete-progress.js';

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
        selectEvidenceBtn,
        clearSelectedEvidenceBtn,
        evidenceSelectionCountEl,
        applyEvidenceSelectionBtn,
        recommendEvidenceBtn,
        recommendationPanelEl,
        recommendationListEl,
        modal,
        evidenceModal,
        evidenceModalEl,
        lineGridBridge,
        basicInfoBridge,
        escapeHtml,
        notify,
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

let modalCodeControlsPromise = null;
function ensureModalCodeControls() {
    if (!modalCodeControlsPromise) {
        modalCodeControlsPromise = Promise.resolve(initCodeSelectControls(modalEl))
            .catch((error) => {
                modalCodeControlsPromise = null;
                console.error('[ledger-journal] modal code controls load failed', error);
                notify('error', '전표 입력 항목을 불러오지 못했습니다.');
            });
    }
    return modalCodeControlsPromise;
}

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
    state.journalRecommendations = [];
    state.journalRecommendationMessages = [];
    state.journalRecommendationCoverage = null;
    state.selectedRecommendationId = '';
    renderJournalRecommendations();
    if (voucherNoDisplayEl) {
        voucherNoDisplayEl.value = '자동발번';
    }
    bindDetailCardCollapses();
    modal?.show();
    void ensureModalCodeControls();
    await setVoucherDetailMeta?.({ status: 'DRAFT' });
}

async function loadDetail(id) {
    traceVoucherStep?.('loadDetail', {
        input: {
            id,
        },
    });
    resetModal();
    state.journalRecommendations = [];
    state.journalRecommendationMessages = [];
    state.journalRecommendationCoverage = null;
    state.selectedRecommendationId = '';
    renderJournalRecommendations();
    setModalTitle('edit');
    setJournalModalLoading(true);
    bindDetailCardCollapses();
    modal?.show();
    void ensureModalCodeControls();

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
        state.releasedLinkedEvidenceKeys.clear();
        await setVoucherDetailMeta?.(data);

        if (Array.isArray(data.lines) && data.lines.length > 0) {
            await lineGridBridge?.loadLines?.(data.lines);
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
    formData.set('linked_evidences', JSON.stringify(state.linkedEvidences.map((item) => ({
        import_type: item.import_type,
        evidence_id: item.evidence_id,
    }))));

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
    const savedVoucherId = String(json.data?.id || idInput?.value || '');
    if (savedVoucherId !== '') {
        const detailJson = await fetchJson(`${API.detail}?id=${encodeURIComponent(savedVoucherId)}`);
        if (!detailJson.success || !detailJson.data) {
            throw new Error(detailJson.message || '저장된 전표 상세 정보를 다시 불러오지 못했습니다.');
        }
        setLinkedEvidence(hydrateVoucher(detailJson.data));
        state.releasedLinkedEvidenceKeys.clear();
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
    await runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '전표를 휴지통으로 이동 중', trashChanged: true }, async () => {
        const formData = new FormData();
        formData.append('id', id);
        const json = await fetchJson(API.remove, { method: 'POST', body: formData });
        if (!json.success) throw new Error(json.message || '전표 삭제에 실패했습니다.');
        notify('success', '전표가 삭제되었습니다.');
        modal?.hide();
        await reloadJournalTable();
    });
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
function updateEvidenceSelectionCount() {
    const selectedCount = state.evidenceSearchTable?.getSelectedIds?.().length || 0;
    if (evidenceSelectionCountEl) evidenceSelectionCountEl.textContent = `${selectedCount}개 선택`;
    if (applyEvidenceSelectionBtn) {
        applyEvidenceSelectionBtn.disabled = selectedCount === 0 || state.evidenceRecommendationPending;
    }
}
ctx.updateEvidenceSelectionCount = updateEvidenceSelectionCount;
const isEvidenceComplete = (row = {}) => ['COMPLETED']
    .includes(String(row.evidence_status || '').trim().toUpperCase());

let restoreEvidenceSelectionAfterEditor = false;
ctx.openEvidenceSourceEditor = async (row = {}) => {
    const importType = String(row.import_type || '').trim().toUpperCase();
    const evidenceId = String(row.evidence_id || row.id || '').trim();
    if (!importType || !evidenceId) {
        notify('warning', '증빙원본 수정 화면을 열 수 없습니다.');
        return;
    }
    const evidenceEditor = await ctx.ensureEvidenceEditor?.();
    if (!evidenceEditor?.open) {
        notify('warning', '증빙원본 수정 화면을 열 수 없습니다.');
        return;
    }
    const openEditor = async () => {
        restoreEvidenceSelectionAfterEditor = true;
        await evidenceEditor.open({ import_type: importType, evidence_id: evidenceId });
    };
    if (evidenceModalEl?.classList.contains('show')) {
        evidenceModalEl.addEventListener('hidden.bs.modal', () => void openEditor(), { once: true });
        evidenceModal?.hide();
        return;
    }
    await openEditor();
};

evidenceModalEl?.addEventListener('shown.bs.modal', () => {
    window.requestAnimationFrame(() => state.evidenceSearchTable?.columns?.adjust?.());
});

document.addEventListener('evidence:updated', () => ctx.reloadEvidenceSelectionTable?.());
document.getElementById('evidenceSeedRowEditModal')?.addEventListener('hidden.bs.modal', () => {
    if (!restoreEvidenceSelectionAfterEditor) return;
    restoreEvidenceSelectionAfterEditor = false;
    evidenceModal?.show();
});


ctx.buildEvidenceSearchRequest = (request = {}) => {
    const order = request.order?.[0] || {};
    const column = request.columns?.[Number(order.column)] || {};
    const length = Math.max(1, Number(request.length || 100));
    return {
        draw: Number(request.draw || 1),
        q: String(request.search?.value || '').trim(),
        voucher_id: form.querySelector('[name="id"]')?.value || '',
        evidence_type: 'ALL',
        import_type: 'ALL',
        exclude_evidences: JSON.stringify(state.linkedEvidences.map((item) => ({ import_type: item.import_type, evidence_id: item.evidence_id }))),
        released_evidences: JSON.stringify(Array.from(state.releasedLinkedEvidenceKeys).map((key) => {
            const separatorIndex = key.indexOf(':');
            return {
                import_type: separatorIndex >= 0 ? key.slice(0, separatorIndex) : '',
                evidence_id: separatorIndex >= 0 ? key.slice(separatorIndex + 1) : '',
            };
        }).filter((item) => item.import_type !== '' && item.evidence_id !== '')),
        page: Math.floor(Number(request.start || 0) / length) + 1,
        per_page: length,
        sort_field: column.name || column.data || 'evidence_date',
        sort_direction: String(order.dir || 'asc').toLowerCase(),
    };
};
async function loadEvidenceSearch() {
    const table = await ctx.ensureEvidenceSelectionTable?.();
    table?.ajax?.reload(null, false);
}

function recommendationSourceLabel(source = '') {
    return ({ JOURNAL_RULE: '분개규칙', RECENT_PATTERN: '최근사용', CLIENT_PATTERN: '거래처 사용패턴', CLIENT_DEFAULT_ACCOUNT: '거래처 기준계정', LEARNING_EVENT: '확정 분개학습' })[source] || source;
}

function recommendationAccountLabel(accountId = '') {
    const account = state.accountPickerById.get(String(accountId || ''));
    return account?.text || account?.account_name || '계정과목 확인 필요';
}

function recommendationRefLabel(refTarget = '') {
    return ({ CLIENT: '거래처', PROJECT: '프로젝트', BANK_ACCOUNT: '계좌', CARD: '카드', TEAM: '팀', EMPLOYEE: '직원' })[refTarget] || refTarget;
}

function renderJournalRecommendations() {
    if (!recommendationPanelEl || !recommendationListEl) return;
    const recommendations = state.journalRecommendations || [];
    const messages = state.journalRecommendationMessages || [];
    const coverage = state.journalRecommendationCoverage;
    if (recommendEvidenceBtn) {
        recommendEvidenceBtn.disabled = state.linkedEvidences.length === 0 || state.evidenceRecommendationPending;
    }
    recommendationPanelEl.hidden = recommendations.length === 0 && messages.length === 0 && !coverage;
    if (recommendations.length === 0 && messages.length === 0 && !coverage) {
        recommendationListEl.innerHTML = '';
        return;
    }
    const messageHtml = messages.map((message) => (
        `<div class="alert alert-secondary py-2 px-3 mb-2" role="status">${escapeHtml(message)}</div>`
    )).join('');
    const coverageHtml = coverage ? `<div class="alert ${coverage.status === 'COMPLETE' ? 'alert-success' : 'alert-warning'} py-2 px-3 mb-2" role="status">
        대상 증빙: ${escapeHtml(String(coverage.request_count || 0))}건 / ${escapeHtml(formatAmountValue(coverage.request_amount || 0))}원 ·
        추천 완료: ${escapeHtml(String(coverage.matched_count || 0))}건 / ${escapeHtml(formatAmountValue(coverage.matched_amount || 0))}원 ·
        미추천: ${escapeHtml(String(coverage.unmatched_count || 0))}건 / ${escapeHtml(formatAmountValue(coverage.unmatched_amount || 0))}원 ·
        Identity: ${escapeHtml(String(coverage.identity_covered_count || 0))}/${escapeHtml(String(coverage.identity_request_count || 0))} ·
        보조정보: ${escapeHtml(String(coverage.sub_account_covered_count || 0))}/${escapeHtml(String(coverage.identity_request_count || 0))} ·
        상태: ${coverage.status === 'COMPLETE' ? '전체 추천 완료' : '일부 추천 불가'}
    </div>` : '';
    const recommendationHtml = recommendations.map((candidate, index) => {
        const applicable = candidate.is_applicable === true
            && candidate.recommendation_status === 'COMPLETE'
            && candidate.is_balanced === true
            && Array.isArray(candidate.unresolved_lines)
            && candidate.unresolved_lines.length === 0;
        const sources = (candidate.source_types || []).map(recommendationSourceLabel).filter(Boolean).join(' · ');
        const lines = (candidate.lines || []).map((line) => {
            const amount = Number(line.debit || line.credit || 0);
            const side = Number(line.debit || 0) > 0 ? '차변' : '대변';
            const refs = (line.refs || []).map((ref) => `${recommendationRefLabel(ref.ref_target)}: ${ref.ref_name || ref.ref_id}`).join(' · ');
            const source = [line.summary, line.client_name, line.source_date, line.expense_category].filter(Boolean).join(' · ');
            const rule = [line.journal_rule_id, line.journal_rule_revision_no ? `Revision ${line.journal_rule_revision_no}` : ''].filter(Boolean).join(' · ');
            return `<li><span class="voucher-recommendation-side">${side}</span><span><b>${escapeHtml(recommendationAccountLabel(line.account_id))}</b>${refs ? `<small>${escapeHtml(refs)}</small>` : ''}${source ? `<small>${escapeHtml(source)}</small>` : ''}${rule ? `<small>${escapeHtml(rule)}</small>` : ''}</span><strong>${escapeHtml(formatAmountValue(amount))}원</strong></li>`;
        }).join('');
        const reason = (candidate.reasons || []).join(' ');
        return `<article class="voucher-recommendation-item${state.selectedRecommendationId === candidate.candidate_id ? ' is-selected' : ''}" data-candidate-id="${escapeHtml(candidate.candidate_id)}">
            <div class="voucher-recommendation-heading"><div><strong>추천 ${index + 1}</strong><span>${escapeHtml(sources || '추천 근거 확인 필요')}</span></div><span class="voucher-recommendation-score">${escapeHtml(String(candidate.score || 0))}점</span></div>
            <ul class="voucher-recommendation-lines">${lines}</ul>
            <div class="voucher-recommendation-footer"><p title="${escapeHtml(reason)}">${escapeHtml(reason || '추천 근거가 없습니다.')}</p><button type="button" class="btn btn-outline-primary btn-sm btn-apply-journal-recommendation"${applicable ? '' : ' disabled'}>${applicable ? '이 추천 선택' : '적용 불가'}</button></div>
        </article>`;
    }).join('');
    recommendationListEl.innerHTML = coverageHtml + messageHtml + recommendationHtml;
}

async function applyJournalRecommendation(candidateId = '') {
    const candidate = state.journalRecommendations.find((item) => item.candidate_id === candidateId);
    if (!candidate) return;
    const candidateLines = Array.isArray(candidate.lines) ? candidate.lines : [];
    const debitTotal = candidateLines.reduce((sum, line) => sum + Number(line.debit || 0), 0);
    const creditTotal = candidateLines.reduce((sum, line) => sum + Number(line.credit || 0), 0);
    const hasUnresolvedLine = candidateLines.some((line) => !line.account_id
        || (Number(line.debit || 0) <= 0 && Number(line.credit || 0) <= 0));
    const isApplicable = candidate.is_applicable === true
        && candidate.recommendation_status === 'COMPLETE'
        && candidate.is_balanced === true
        && Array.isArray(candidate.unresolved_lines)
        && candidate.unresolved_lines.length === 0
        && candidateLines.length > 0
        && !hasUnresolvedLine
        && debitTotal > 0
        && Math.abs(debitTotal - creditTotal) < 0.01;
    if (!isApplicable) {
        notify('warning', '미결정 항목이 있거나 차변과 대변이 일치하지 않아 추천을 적용할 수 없습니다.');
        return;
    }
    const currentLineCount = collectLines().length;
    const recommendedLineCount = candidateLines.length;
    if (currentLineCount > 0 && !window.confirm(
        `기존 분개라인 ${currentLineCount}개를 추천 분개라인 ${recommendedLineCount}개로 교체합니다. 적용하시겠습니까?`
    )) return;
    const journalRuleSignal = (candidate.signals || []).find((signal) => signal.source === 'JOURNAL_RULE');
    const lines = (candidate.lines || []).map((line) => {
        const recommendedLine = {
            account_id: line.account_id,
            debit: line.debit || 0,
            credit: line.credit || 0,
            line_summary: line.summary || '',
            refs: Array.isArray(line.refs) ? line.refs : [],
            source_refs: Array.isArray(line.source_refs) ? line.source_refs : [],
            journal_rule_id: line.journal_rule_id || journalRuleSignal?.source_id || '',
            is_user_modified: 0,
            recommendation_source: (candidate.source_types || []).join(','),
            recommendation_score: candidate.score,
            recommendation_reason: (candidate.reasons || []).join(' '),
        };
        recommendedLine.recommendation_snapshot = createRecommendationSnapshot(recommendedLine);
        return recommendedLine;
    });
    await lineGridBridge?.loadLines?.(lines);
    state.selectedRecommendationId = candidateId;
    calculateTotals();
    renderJournalRecommendations();
    notify('success', '선택한 추천을 분개라인에 반영했습니다.');
}

async function recommendAddedEvidences(evidences = []) {
    if (!evidences.length) {
        state.journalRecommendations = [];
        state.journalRecommendationMessages = [];
        state.journalRecommendationCoverage = null;
        state.selectedRecommendationId = '';
        renderJournalRecommendations();
        notify('info', '분개추천을 조회할 증빙이 없습니다.');
        return;
    }
    const identities = evidences.map((item) => ({
        import_type: String(item.import_type || '').toUpperCase(),
        evidence_id: String(item.evidence_id || ''),
    })).sort((a, b) => `${a.import_type}:${a.evidence_id}`.localeCompare(`${b.import_type}:${b.evidence_id}`));
    const contextToken = JSON.stringify({
        voucher_id: form.querySelector('[name="id"]')?.value || 'NEW',
        evidences: identities,
    });
    const requestSequence = state.evidenceRecommendationSequence + 1;
    state.evidenceRecommendationSequence = requestSequence;
    state.evidenceRecommendationContext = contextToken;
    state.evidenceRecommendationAbort?.abort?.();
    const abortController = typeof AbortController === 'function' ? new AbortController() : null;
    state.evidenceRecommendationAbort = abortController;
    evidences.forEach((evidence) => { evidence._recommendation_status = 'LOADING'; });
    ctx.renderLinkedEvidenceGrid?.();
    const originalText = recommendEvidenceBtn?.textContent || '분개추천 조회';
    state.evidenceRecommendationPending = true;
    updateEvidenceSelectionCount();
    renderJournalRecommendations();
    if (recommendEvidenceBtn) {
        recommendEvidenceBtn.disabled = true;
        recommendEvidenceBtn.textContent = '추천 조회 중...';
    }
    try {
        const json = await fetchJson(API.evidenceRecommendations, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                evidences: identities,
                accounting_date: voucherDateEl?.value || '',
            }),
            signal: abortController?.signal,
        });
        if (requestSequence !== state.evidenceRecommendationSequence
            || contextToken !== state.evidenceRecommendationContext) return;
        if (!json.success) throw new Error(json.message || '분개 추천을 불러오지 못했습니다.');
        const notices = [];
        for (const result of (json.data?.results || [])) {
            const linkedEvidence = state.linkedEvidences.find((item) => item.import_type === result.import_type && item.evidence_id === result.evidence_id);
            if (linkedEvidence) {
                linkedEvidence._recommendation_status = result.recommendation_status || result.status;
                linkedEvidence._recommendation_reason_code = result.reason_code || '';
                linkedEvidence._recommendation_message = result.message || '';
            }
            if (result.connection_status === 'BLOCKED') {
                notices.push(result.message || '추천에 사용할 수 없는 증빙입니다.');
            } else if (!['FULL', 'PAIRED'].includes(result.recommendation_status)) {
                notices.push(result.message || '추천할 분개규칙이 없습니다.');
            }
        }
        state.journalRecommendations = Array.isArray(json.data?.recommendations) ? json.data.recommendations.slice(0, 3) : [];
        state.journalRecommendationCoverage = json.data?.coverage || null;
        state.journalRecommendationMessages = Array.from(new Set(notices));
        state.selectedRecommendationId = '';
        renderJournalRecommendations();
        if (state.journalRecommendations.length > 0) {
            notify('success', `분개추천 ${state.journalRecommendations.length}건을 조회했습니다. 적용할 내용을 확인해 주세요.`);
        }
        ctx.renderLinkedEvidenceGrid?.();
    } catch (error) {
        if (error?.name === 'AbortError' || requestSequence !== state.evidenceRecommendationSequence) return;
        state.journalRecommendations = [];
        state.journalRecommendationMessages = ['분개추천 조회 중 오류가 발생했습니다. 기존 분개라인은 변경되지 않았습니다.'];
        state.journalRecommendationCoverage = null;
        state.selectedRecommendationId = '';
        renderJournalRecommendations();
        notify('error', '분개추천 조회 중 오류가 발생했습니다.');
    } finally {
        if (requestSequence !== state.evidenceRecommendationSequence) return;
        state.evidenceRecommendationPending = false;
        state.evidenceRecommendationAbort = null;
        if (recommendEvidenceBtn) {
            recommendEvidenceBtn.disabled = false;
            recommendEvidenceBtn.textContent = originalText;
        }
        updateEvidenceSelectionCount();
        renderJournalRecommendations();
    }
}

ctx.clearJournalRecommendations = () => {
    state.evidenceRecommendationSequence += 1;
    state.evidenceRecommendationContext = '';
    state.evidenceRecommendationAbort?.abort?.();
    state.evidenceRecommendationAbort = null;
    state.evidenceRecommendationPending = false;
    state.journalRecommendations = [];
    state.journalRecommendationMessages = [];
    state.journalRecommendationCoverage = null;
    state.selectedRecommendationId = '';
    renderJournalRecommendations();
};
ctx.syncRecommendationVisibility = renderJournalRecommendations;

function bindEvents() {
    if (state.voucherEventsBound) {
        return;
    }
    state.voucherEventsBound = true;

    bindDetailCardCollapses();
    addLineBtn?.addEventListener('click', () => {
        void addLineRow();
    });
    recommendationListEl?.addEventListener('click', (event) => {
        const button = event.target.closest('.btn-apply-journal-recommendation');
        const item = button?.closest('.voucher-recommendation-item');
        if (item?.dataset.candidateId) void applyJournalRecommendation(item.dataset.candidateId);
    });
    recommendEvidenceBtn?.addEventListener('click', () => {
        void recommendAddedEvidences(state.linkedEvidences);
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

    const openEvidenceSearch = async () => {
        if (!evidenceModal) {
            notify('warning', '증빙 추가 모달을 찾을 수 없습니다.');
            return;
        }
        if (state.evidenceSearchOpening) return;
        state.evidenceSearchOpening = true;
        if (selectEvidenceBtn) {
            selectEvidenceBtn.disabled = true;
            selectEvidenceBtn.setAttribute('aria-busy', 'true');
        }
        state.evidencePage = 1;
        state.evidenceSearchTable?.clearSelectedIds?.();
        updateEvidenceSelectionCount();
        try {
            const table = await ctx.ensureEvidenceSelectionTable?.();
            if (!table) {
                notify('warning', '증빙 추가 목록을 불러올 수 없습니다.');
                return;
            }
            await new Promise((resolve) => {
                if (!table.ajax?.reload) {
                    resolve();
                    return;
                }
                table.ajax.reload(() => resolve(), false);
            });
            evidenceModal.show();
            window.requestAnimationFrame(() => {
                table.columns?.adjust?.();
            });
        } finally {
            state.evidenceSearchOpening = false;
            if (selectEvidenceBtn) {
                selectEvidenceBtn.disabled = false;
                selectEvidenceBtn.removeAttribute('aria-busy');
            }
        }
    };
    selectEvidenceBtn?.addEventListener('click', () => {
        void openEvidenceSearch();
    });
    clearSelectedEvidenceBtn?.addEventListener('click', () => {
        if (!ctx.clearSelectedLinkedEvidences?.()) notify('info', '해제할 증빙을 선택해 주세요.');
    });

    applyEvidenceSelectionBtn?.addEventListener('click', async () => {
        if (state.evidenceRecommendationPending) return;
        const selectedRows = (state.evidenceSearchTable?.getSelectedIds?.() || [])
            .map((key) => state.pendingEvidenceRows.get(key))
            .filter(Boolean);
        if (selectedRows.length === 0) {
            updateEvidenceSelectionCount();
            return;
        }
        if (selectedRows.some((row) => !isEvidenceComplete(row))) {
            notify('warning', '증빙상태가 완료된 증빙만 추가할 수 있습니다.');
            return;
        }
        const linkedKeys = new Set(state.linkedEvidences.map((row) => `${row.import_type}:${row.evidence_id}`));
        const additions = selectedRows
            .filter((row) => !linkedKeys.has(`${row.import_type}:${row.evidence_id}`))
            .map((item) => ({ ...item, _link_state: 'PENDING' }));
        if (additions.length === 0) {
            notify('info', '선택한 증빙은 이미 이 전표에 추가되어 있습니다.');
            return;
        }
        setLinkedEvidence({ linked_evidences: [...state.linkedEvidences, ...additions] });
        additions.forEach((item) => state.releasedLinkedEvidenceKeys.delete(`${item.import_type}:${item.evidence_id}`));
        ctx.clearJournalRecommendations?.();
        state.evidenceSearchTable?.clearSelectedIds?.();
        updateEvidenceSelectionCount();
        state.evidenceSearchTable?.ajax?.reload(null, false);
        evidenceModal?.hide();
        notify('success', `증빙 ${additions.length}건을 추가했습니다.`);
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
        if (options.evidence) {
            setLinkedEvidence({ linked_evidences: [options.evidence] });
        }
    },
};

async function boot() {
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
        cancelVoucherReview,
        deleteVoucher,
        findJournalRowData,
        handleJournalAction,
        loadEvidenceSearch,
        bindEvents,
        boot
    });

    return ctx;
}
