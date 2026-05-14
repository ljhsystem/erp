import { formatNumber, parseNumber } from '/public/assets/js/common/format.js';

const API = {
    accounts: '/api/ledger/account/list',
    recommendVoucher: '/api/ledger/transaction/recommend-voucher',
    createVoucher: '/api/ledger/transaction/create-voucher',
};

const state = {
    status: 'idle',
    transactionId: '',
    transaction: {},
    accounts: [],
    originalLines: [],
    onSaved: null,
    onClosed: null,
    modal: null,
    isSaving: false,
    savedCalled: false,
    closedCalled: false,
    bound: false,
};

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function notify(type, message) {
    if (window.AppCore?.notify) {
        window.AppCore.notify(type, message);
        return;
    }
    if (window.showToast) {
        window.showToast(message, type);
        return;
    }
    if (type === 'error' || type === 'warning') {
        window.alert(message);
    }
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        cache: 'no-store',
        ...options,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
    });
    const text = await response.text();
    let json = {};
    try {
        json = text ? JSON.parse(text) : {};
    } catch (_error) {
        json = { message: text };
    }
    if (!response.ok || json.success === false) {
        throw new Error(json.message || '요청 처리 중 오류가 발생했습니다.');
    }
    return json;
}

function els() {
    const modalEl = document.getElementById('voucherRecommendationModal');
    return {
        modalEl,
        lineBody: document.getElementById('voucherRecommendationLineBody'),
        balanceText: document.getElementById('voucherRecommendationBalanceText'),
        saveBtn: document.getElementById('voucherRecommendationSaveBtn'),
        addLineBtn: document.getElementById('voucherRecommendationAddLineBtn'),
        description: document.getElementById('voucherRecommendationDescription'),
    };
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}

async function loadAccounts() {
    if (state.accounts.length > 0) return state.accounts;
    const json = await fetchJson(API.accounts);
    state.accounts = Array.isArray(json.data) ? json.data.filter((row) => {
        const postable = String(row.is_postable ?? row.is_posting ?? '').toUpperCase();
        return postable === 'Y' || postable === '1' || row.is_posting === 1;
    }) : [];
    return state.accounts;
}

function accountOptions(selectedId = '') {
    const selected = String(selectedId || '');
    return ['<option value="">계정 선택</option>'].concat(state.accounts.map((account) => {
        const id = String(account.id || '');
        const label = `${account.account_code || ''} ${account.account_name || ''}`.trim();
        return `<option value="${escapeHtml(id)}" ${id === selected ? 'selected' : ''}>${escapeHtml(label)}</option>`;
    })).join('');
}

function sourceLabel(source = '') {
    return {
        CLIENT_DEFAULT: '거래처 기본계정',
        ITEM_DEFAULT: '품목 기본계정',
        JOURNAL_RULE: '분개규칙',
        CLIENT_PATTERN: '거래처 패턴',
        RECENT_PATTERN: '최근 사용 패턴',
        LEARNING: '학습 결과',
        VAT_RULE: 'VAT 규칙',
        USER: '사용자 추가',
    }[source] || source || '-';
}

function confidenceBadge(confidence) {
    const value = Number(confidence || 0);
    const cls = value >= 90 ? 'text-bg-success' : (value >= 70 ? 'text-bg-warning' : 'text-bg-danger');
    return `<span class="badge ${cls}">${value}%</span>`;
}

function setHeader(transaction = {}) {
    setValue('voucherRecommendationClientName', transaction.client_name || '');
    setValue('voucherRecommendationProjectName', transaction.project_name || '');
    setValue('voucherRecommendationTransactionType', transaction.transaction_type || '');
    setValue('voucherRecommendationTransactionDirection', transaction.transaction_direction || '');
    setValue('voucherRecommendationSupplyAmount', formatNumber(transaction.supply_amount || 0));
    setValue('voucherRecommendationVatAmount', formatNumber(transaction.vat_amount || 0));
    setValue('voucherRecommendationTotalAmount', formatNumber(transaction.total_amount || 0));
    setValue('voucherRecommendationDescription', transaction.description || '');
}

function lineOriginalKey(line = {}) {
    return {
        line_type: String(line.line_type || 'DEBIT').toUpperCase(),
        account_id: String(line.account_id || ''),
        amount: String(Number(line.amount || 0).toFixed(2)),
    };
}

function lineTemplate(line = {}) {
    const lineType = String(line.line_type || 'DEBIT').toUpperCase();
    const amount = formatNumber(line.amount || 0);
    const source = String(line.source || '');
    const confidence = Number(line.confidence || 0);
    const reason = line.reason || sourceLabel(source);
    const original = encodeURIComponent(JSON.stringify(lineOriginalKey({ ...line, line_type: lineType })));

    return `
        <tr data-original="${original}">
            <td><select class="form-select form-select-sm voucher-recommendation-line-type"><option value="DEBIT" ${lineType === 'DEBIT' ? 'selected' : ''}>차변</option><option value="CREDIT" ${lineType === 'CREDIT' ? 'selected' : ''}>대변</option></select></td>
            <td><select class="form-select form-select-sm voucher-recommendation-account">${accountOptions(line.account_id || '')}</select></td>
            <td><input type="text" class="form-control form-control-sm voucher-recommendation-sub-account" value="${escapeHtml(line.sub_account_name || '')}"></td>
            <td><input type="text" class="form-control form-control-sm voucher-recommendation-client" value="${escapeHtml(state.transaction.client_name || '')}"></td>
            <td><input type="text" class="form-control form-control-sm voucher-recommendation-project" value="${escapeHtml(state.transaction.project_name || '')}"></td>
            <td><input type="text" class="form-control form-control-sm text-end voucher-recommendation-amount" value="${escapeHtml(amount)}"></td>
            <td><span class="voucher-recommendation-source" data-source="${escapeHtml(source)}" data-reason="${escapeHtml(reason)}" data-rule-id="${escapeHtml(line.journal_rule_id || '')}">${escapeHtml(reason)}</span></td>
            <td class="text-center" data-confidence="${confidence}">${confidenceBadge(confidence)}</td>
            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm btn-remove-voucher-recommendation-line">삭제</button></td>
        </tr>
    `;
}

function renderLines(lines = []) {
    const { lineBody } = els();
    if (!lineBody) return;
    state.originalLines = lines.map((line) => ({ ...line }));
    lineBody.innerHTML = lines.map(lineTemplate).join('');
    updateBalance();
}

function collectLines() {
    const { lineBody, description } = els();
    if (!lineBody) return [];
    return Array.from(lineBody.querySelectorAll('tr')).map((row) => {
        const lineType = row.querySelector('.voucher-recommendation-line-type')?.value || '';
        const accountId = row.querySelector('.voucher-recommendation-account')?.value || '';
        const amount = parseNumber(row.querySelector('.voucher-recommendation-amount')?.value || '0');
        const sourceEl = row.querySelector('.voucher-recommendation-source');
        const currentKey = JSON.stringify(lineOriginalKey({ line_type: lineType, account_id: accountId, amount }));
        let originalKey = '';
        let original = {};
        try {
            original = JSON.parse(decodeURIComponent(row.dataset.original || ''));
            originalKey = JSON.stringify(original);
        } catch (_error) {
            originalKey = '';
            original = {};
        }

        return {
            line_type: lineType,
            account_id: accountId,
            amount,
            recommended_line_type: original.line_type || lineType,
            recommended_account_id: original.account_id || accountId,
            recommended_amount: original.amount || amount,
            sub_account_name: row.querySelector('.voucher-recommendation-sub-account')?.value || '',
            client_name: row.querySelector('.voucher-recommendation-client')?.value || '',
            project_name: row.querySelector('.voucher-recommendation-project')?.value || '',
            client_id: state.transaction.client_id || '',
            project_id: state.transaction.project_id || '',
            source: sourceEl?.dataset.source || '',
            reason: sourceEl?.dataset.reason || '',
            journal_rule_id: sourceEl?.dataset.ruleId || '',
            confidence: Number(row.querySelector('[data-confidence]')?.dataset.confidence || 0),
            line_summary: description?.value || '',
            is_user_modified: currentKey !== originalKey,
        };
    }).filter((line) => line.account_id && line.amount > 0 && ['DEBIT', 'CREDIT'].includes(line.line_type));
}

function totals() {
    return collectLines().reduce((sum, line) => {
        if (line.line_type === 'DEBIT') sum.debit += line.amount;
        if (line.line_type === 'CREDIT') sum.credit += line.amount;
        return sum;
    }, { debit: 0, credit: 0 });
}

function updateBalance() {
    const { balanceText, saveBtn } = els();
    const sum = totals();
    const diff = Math.round((sum.debit - sum.credit) * 100) / 100;
    const balanced = sum.debit > 0 && sum.credit > 0 && diff === 0;
    if (balanceText) {
        balanceText.className = `voucher-recommendation-balance ${balanced ? 'is-balanced' : 'is-unbalanced'}`;
        balanceText.textContent = `차변 ${formatNumber(sum.debit)} / 대변 ${formatNumber(sum.credit)} / 차이 ${formatNumber(Math.abs(diff))}`;
    }
    if (saveBtn) saveBtn.disabled = !balanced || state.isSaving;
}

function cleanupBackdrop() {
    document.querySelectorAll('.modal-backdrop.voucher-recommendation-backdrop').forEach((backdrop) => {
        backdrop.classList.remove('voucher-recommendation-backdrop');
    });
}

function markBackdrop() {
    window.setTimeout(() => {
        document.querySelector('.modal-backdrop.show:last-of-type')?.classList.add('voucher-recommendation-backdrop');
    }, 0);
}

function callSavedOnce(json) {
    if (state.savedCalled) return;
    state.savedCalled = true;
    if (typeof state.onSaved === 'function') {
        try {
            state.onSaved(json);
        } catch (error) {
            console.error(error);
        }
    }
}

function resetState() {
    state.status = 'idle';
    state.transactionId = '';
    state.transaction = {};
    state.originalLines = [];
    state.onSaved = null;
    state.onClosed = null;
    state.isSaving = false;
    state.savedCalled = false;
    state.closedCalled = false;
}

function cleanupDom() {
    const { lineBody, balanceText, saveBtn } = els();
    if (lineBody) lineBody.innerHTML = '';
    if (balanceText) {
        balanceText.textContent = '';
        balanceText.className = 'voucher-recommendation-balance';
    }
    if (saveBtn) saveBtn.disabled = false;
    setHeader({});
}

function cleanupEvents() {
    // Event handlers are bound once at module level; per-open callbacks are cleared in resetState().
}

function handleHidden() {
    const onClosed = state.onClosed;
    const shouldCallClosed = !state.closedCalled;
    state.closedCalled = true;
    state.status = 'closed';
    cleanupDom();
    cleanupEvents();
    cleanupBackdrop();
    resetState();
    if (shouldCallClosed && typeof onClosed === 'function') {
        try {
            onClosed();
        } catch (error) {
            console.error(error);
        }
    }
}

function handleHide(event) {
    if (state.isSaving && state.status !== 'closing') {
        event.preventDefault();
        return;
    }
    if (state.status !== 'closing') {
        state.status = 'closing';
    }
}

function addBlankLine() {
    const { lineBody } = els();
    if (!lineBody) return;
    lineBody.insertAdjacentHTML('beforeend', lineTemplate({
        line_type: 'DEBIT',
        amount: 0,
        source: 'USER',
        confidence: 0,
        reason: sourceLabel('USER'),
    }));
    updateBalance();
}

async function saveRecommendation() {
    if (state.isSaving || !state.transactionId || state.status !== 'opened') return;

    const sum = totals();
    if (Math.round(sum.debit * 100) !== Math.round(sum.credit * 100)) {
        notify('warning', '차변 합계와 대변 합계가 일치해야 저장할 수 있습니다.');
        return;
    }

    state.isSaving = true;
    state.status = 'saving';
    updateBalance();

    try {
        const { description } = els();
        const json = await fetchJson(API.createVoucher, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                transaction_id: state.transactionId,
                header: {
                    transaction_date: state.transaction.transaction_date || '',
                    description: description?.value || '',
                },
                lines: collectLines(),
            }),
        });
        state.isSaving = false;
        callSavedOnce(json);
        state.status = 'closing';
        state.modal?.hide();
    } catch (error) {
        state.status = 'opened';
        state.isSaving = false;
        updateBalance();
        notify('error', error.message);
    }
}

function bindOnce() {
    if (state.bound) return;
    const { modalEl, lineBody, saveBtn, addLineBtn } = els();
    if (!modalEl) return;
    modalEl.addEventListener('hide.bs.modal', handleHide);
    modalEl.addEventListener('hidden.bs.modal', handleHidden);
    modalEl.addEventListener('shown.bs.modal', markBackdrop);
    saveBtn?.addEventListener('click', () => void saveRecommendation());
    addLineBtn?.addEventListener('click', addBlankLine);
    lineBody?.addEventListener('input', updateBalance);
    lineBody?.addEventListener('change', updateBalance);
    lineBody?.addEventListener('click', (event) => {
        const button = event.target.closest('.btn-remove-voucher-recommendation-line');
        if (!button) return;
        button.closest('tr')?.remove();
        updateBalance();
    });
    state.bound = true;
}

export async function openVoucherRecommendationModal({ transactionId, transaction = null, onSaved = null, onClosed = null } = {}) {
    const id = String(transactionId || '');
    const { modalEl } = els();
    if (!modalEl) {
        notify('error', '추천분개검토 모달을 찾을 수 없습니다.');
        return false;
    }
    if (!id) {
        notify('warning', '전표 추천을 위한 거래를 선택해 주세요.');
        return false;
    }
    if (['opened', 'saving', 'closing'].includes(state.status)) {
        notify('warning', '이미 추천분개검토가 진행 중입니다.');
        return false;
    }

    bindOnce();
    cleanupDom();
    cleanupBackdrop();
    state.status = 'opened';
    state.transactionId = id;
    state.transaction = transaction || {};
    state.onSaved = onSaved;
    state.onClosed = onClosed;
    state.isSaving = false;
    state.savedCalled = false;
    state.closedCalled = false;

    try {
        await loadAccounts();
        const json = await fetchJson(`${API.recommendVoucher}?transaction_id=${encodeURIComponent(id)}`);
        state.transaction = json.transaction || transaction || {};
        setHeader(state.transaction);
        renderLines(json.recommendation?.recommendations || []);
        state.modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
        state.modal.show();
        markBackdrop();
        return true;
    } catch (error) {
        cleanupDom();
        cleanupBackdrop();
        resetState();
        notify('error', error.message);
        return false;
    }
}
