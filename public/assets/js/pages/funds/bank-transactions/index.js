import { SearchForm } from '/public/assets/js/components/search-form.js';
import { notify as commonNotify } from '/public/assets/js/common/notification.js';
import { formatAccountNumber } from '/public/assets/js/common/format.js';
import { renderSummary } from './summary.js';
import { createFundsBankTransactionTable, escapeHtml } from './table.js';
import '/public/assets/js/components/trash-manager.js';

const API = {
    list: '/api/funds/bank-transactions',
    show: '/api/funds/bank-transactions/show',
    delete: '/api/funds/bank-transactions/delete',
    restore: '/api/funds/bank-transactions/restore',
    reorder: '/api/import/evidences/reorder',
};

window.TrashColumns = window.TrashColumns || {};
window.TrashColumns.fundsBankTransaction = function fundsBankTransactionTrashColumns(row = {}) {
    const account = [row.bank_name, row.account_name].filter(Boolean).join(' ') || '-';
    return `
        <td>${escapeHtml(row.transaction_datetime || row.transaction_date || '-')}</td>
        <td>${escapeHtml(account)}</td>
        <td>${escapeHtml(row.description || row.memo || row.client_name || row.counterparty_name || '-')}</td>
        <td class="text-end">${escapeHtml(row.deposit_amount || '0')}</td>
        <td class="text-end">${escapeHtml(row.withdraw_amount || '0')}</td>
        <td>${escapeHtml(row.deleted_at || '-')}</td>
        <td>
            <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id || '')}">복구</button>
        </td>
    `;
};

function notify(type, message) {
    commonNotify(type, message);
}

async function postJson(url, payload = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok || json.success === false) {
        const error = new Error(json.message || '요청 처리에 실패했습니다.');
        error.status = response.status;
        error.code = json.code || '';
        throw error;
    }
    return json;
}

function sourceDetailHtml(row = {}) {
    const fallback = (value, defaultValue = '-') => {
        if (value === null || value === undefined || value === '') return defaultValue;
        return value;
    };
    const amount = (value, defaultValue = '0') => {
        if (value === null || value === undefined || value === '') return defaultValue;
        const numeric = Number(String(value).replace(/,/g, ''));
        if (!Number.isFinite(numeric)) return value;
        return numeric.toLocaleString('ko-KR');
    };

    const items = [
        ['거래일시', fallback(row.source_transaction_datetime || row.transaction_datetime || row.transaction_date)],
        ['출금', amount(row.source_withdraw_amount ?? row.withdraw_amount)],
        ['입금', amount(row.source_deposit_amount ?? row.deposit_amount)],
        ['거래후잔액(원본)', amount(row.source_balance_amount ?? row.original_balance_amount, '-')],
        ['자동계산 잔액(참고)', amount(row.calculated_balance_amount ?? row.balance_amount, '-')],
        ['거래내용', fallback(row.source_description || row.description)],
        ['상대계좌번호', fallback(formatAccountNumber(
            row.source_counterparty_account_number || row.counterparty_account_number,
            row.source_counterparty_bank_name || row.counterparty_bank_name
        ))],
        ['상대은행', fallback(row.source_counterparty_bank_name || row.counterparty_bank_name)],
        ['메모', fallback(row.source_memo || row.memo)],
        ['거래구분', fallback(row.source_bank_direction || row.bank_direction || row.transaction_type)],
        ['수표어음금액', amount(row.source_check_bill_amount ?? row.check_bill_amount)],
        ['CMS코드', fallback(row.source_bank_reference_no || row.bank_reference_no)],
        ['거래처명', fallback(row.client_name)],
        ['상대계좌예금주명', fallback(row.source_counterparty_name || row.counterparty_name)],
    ];

    return items.map(([label, value]) => `
        <dt class="col-4">${escapeHtml(label)}</dt>
        <dd class="col-8">${escapeHtml(value)}</dd>
    `).join('');
}

let sourceOpenToken = 0;

async function openSourceModal(id, rowHint = null) {
    if (!id) return;
    const token = ++sourceOpenToken;
    const hintedRow = rowHint ? rememberTransaction({ ...rowHint, id }) : transactionCache.get(String(id));
    const modalEl = document.getElementById('fundsBankSourceModal');
    const detail = document.getElementById('fundsBankSourceDetail');
    const editBtn = document.getElementById('fundsBankSourceEditBtn');

    const manageModalEl = document.getElementById('fundsBankManageModal');
    if (manageModalEl?.classList.contains('show')) {
        window.bootstrap?.Modal.getInstance(manageModalEl)?.hide();
    }

    if (detail) {
        detail.innerHTML = hintedRow
            ? sourceDetailHtml(hintedRow)
            : '<dd class="col-12 text-muted mb-0">원본 정보를 불러오는 중입니다.</dd>';
    }
    if (editBtn) editBtn.dataset.evidenceId = hintedRow?.evidence_id || '';
    window.bootstrap?.Modal.getOrCreateInstance(modalEl)?.show();

    try {
        const row = rememberTransaction(await fetchTransaction(id, { force: true }));
        if (token !== sourceOpenToken) return;
        if (detail) detail.innerHTML = sourceDetailHtml(row);
        if (editBtn) editBtn.dataset.evidenceId = row.evidence_id || '';
    } catch (error) {
        if (!hintedRow) throw error;
        notify('warning', '원본보기는 현재 화면의 값으로 먼저 표시했습니다. 최신 정보 갱신은 실패했습니다.');
    }
}

let evidenceEditor = null;
let evidenceEditorLoadPromise = null;
let fundsTable = null;
const transactionCache = new Map();

function rememberTransaction(row = {}) {
    const id = String(row.id || '');
    if (id === '') return row;
    const cached = transactionCache.get(id) || {};
    const merged = { ...cached, ...row };
    transactionCache.set(id, merged);
    return merged;
}

async function openEvidenceEdit(evidenceId) {
    if (!evidenceId) {
        notify('warning', '연결된 증빙원본이 없습니다.');
        return;
    }
    const editor = await ensureEvidenceEditor();
    await editor.open(evidenceId);
}

async function ensureEvidenceEditor() {
    if (evidenceEditor) {
        return evidenceEditor;
    }
    if (!fundsTable) {
        throw new Error('입출금 목록이 아직 준비되지 않았습니다.');
    }
    if (!evidenceEditorLoadPromise) {
        evidenceEditorLoadPromise = import('./evidence-editor.js')
            .then((module) => {
                evidenceEditor = module.createFundsEvidenceEditor({ table: fundsTable, notify });
                return evidenceEditor;
            })
            .catch((error) => {
                evidenceEditorLoadPromise = null;
                console.warn('[funds] evidence editor load failed', error);
                throw new Error('증빙수정 모듈을 불러오지 못했습니다. 증빙원본 화면에서 수정해주세요.');
            });
    }
    return evidenceEditorLoadPromise;
}

async function openVoucher(voucherId) {
    if (!voucherId) {
        notify('warning', '?곌껐??꾪몴媛 ?놁뒿?덈떎.');
        return;
    }
    if (!window.LedgerJournalModal?.openVoucher) {
        notify('error', '?꾪몴 ?섏젙 紐⑤떖???湲?以묒엯?덈떎. ?좎떆 ??떎???쒕룄?댁＜?몄슂.');
        return;
    }
    const manageModalEl = document.getElementById('fundsBankManageModal');
    if (manageModalEl?.classList.contains('show')) {
        window.bootstrap?.Modal.getInstance(manageModalEl)?.hide();
    }
    await window.LedgerJournalModal.openVoucher(voucherId, {
        onClosed() {
            fundsTable?.ajax?.reload(null, false);
        },
    });
}

function openVoucherCreate(evidenceId) {
    window.location.href = evidenceId
        ? `/ledger/data/create?import_type=BANK_TRANSACTION&evidence_id=${encodeURIComponent(evidenceId)}`
        : '/ledger/data/create?import_type=BANK_TRANSACTION';
}

async function fetchTransaction(id, options = {}) {
    const key = String(id || '');
    if (!options.force && transactionCache.has(key)) {
        return transactionCache.get(key);
    }
    const response = await fetch(`${API.show}?id=${encodeURIComponent(id)}`, { cache: 'no-store' });
    const json = await response.json().catch(() => ({}));
    if (!response.ok || json.success === false) {
        throw new Error(json.message || '\uC785\uCD9C\uAE08 \uC6D0\uBCF8\uC744 \uBD88\uB7EC\uC624\uC9C0 \uBABB\uD588\uC2B5\uB2C8\uB2E4.');
    }

    return rememberTransaction(json.data || {});
}

function setManageButtonState(modal, action, options = {}) {
    const button = modal?.querySelector(`[data-funds-manage-action="${action}"]`);
    if (!button) return;
    button.classList.toggle('d-none', Boolean(options.hidden));
    button.disabled = Boolean(options.disabled);
}

function renderManageSummary(row = {}) {
    const title = row.description || row.memo || row.client_name || row.counterparty_name || '-';
    const account = [row.bank_name, row.account_name].filter(Boolean).join(' ') || '-';
    const amount = Number(row.deposit_amount || 0) > 0 ? row.deposit_amount : row.withdraw_amount;
    return `
        <div class="funds-manage-summary-title">${escapeHtml(title)}</div>
        <div class="funds-manage-summary-meta">
            <span>${escapeHtml(row.transaction_datetime || row.transaction_date || '-')}</span>
            <span>${escapeHtml(account)}</span>
            <span>${escapeHtml(amount || '-')}</span>
        </div>
    `;
}

function deleteContextMessage(row = {}) {
    if (Number(row.voucher_count || 0) > 0 || row.voucher_id) {
        return `이 입출금 원본은 전표 ${row.voucher_no || ''}에 연결되어 있어 휴지통으로 이동할 수 없습니다. 전표입력에서 연결 상태를 먼저 확인해주세요.`;
    }
    if (row.deleted_at) {
        return '이미 휴지통에 있는 입출금 원본입니다. 필요하면 휴지통에서 복구해주세요.';
    }
    return '이 입출금 원본과 연결된 증빙원본을 함께 휴지통으로 이동합니다. 전표에 연결되지 않은 원본만 삭제할 수 있습니다.';
}

async function deleteBankTransaction(id) {
    const row = await fetchTransaction(id, { force: true });
    const message = deleteContextMessage(row);
    if (Number(row.voucher_count || 0) > 0 || row.voucher_id || row.deleted_at) {
        notify('warning', message);
        return false;
    }

    notify('info', message);
    if (!window.confirm(`${message}\n\n계속 진행할까요?`)) {
        notify('info', '삭제를 취소했습니다.');
        return false;
    }

    const json = await postJson(API.delete, { id });
    notify('success', json.message || '입출금 원본을 휴지통으로 이동했습니다.');
    return true;
}

(() => {
    const api = API.list;
    let activeManageRow = null;
    let manageOpenToken = 0;

    function rowFromButton(button) {
        const rowEl = button?.closest('tr');
        const row = rowEl ? table.row(rowEl).data() : null;
        return row ? rememberTransaction(row) : null;
    }

    function applyManageModalRow(modalEl, row = {}) {
        activeManageRow = rememberTransaction(row);
        const summary = document.getElementById('fundsBankManageSummary');
        if (summary) summary.innerHTML = renderManageSummary(activeManageRow);

        const hasVoucher = Boolean(activeManageRow.voucher_id);
        const deleted = Boolean(activeManageRow.deleted_at);
        setManageButtonState(modalEl, 'voucher', { disabled: !hasVoucher });
        setManageButtonState(modalEl, 'delete', { hidden: deleted });
        setManageButtonState(modalEl, 'restore', { hidden: !deleted });
    }

    async function openManageModal(id, rowHint = null) {
        if (!id) return;
        const token = ++manageOpenToken;
        const modalEl = document.getElementById('fundsBankManageModal');
        if (!modalEl) return;

        const hintedRow = rowHint ? rememberTransaction({ ...rowHint, id }) : transactionCache.get(String(id));
        if (hintedRow) {
            applyManageModalRow(modalEl, hintedRow);
        } else {
            activeManageRow = { id };
            const summary = document.getElementById('fundsBankManageSummary');
            if (summary) summary.innerHTML = '<div class="text-muted">입출금 정보를 불러오는 중입니다.</div>';
            setManageButtonState(modalEl, 'voucher', { disabled: true });
            setManageButtonState(modalEl, 'delete', { hidden: true });
            setManageButtonState(modalEl, 'restore', { hidden: true });
        }
        window.bootstrap?.Modal.getOrCreateInstance(modalEl)?.show();

        try {
            const row = await fetchTransaction(id, { force: true });
            if (token !== manageOpenToken) return;
            applyManageModalRow(modalEl, row);
        } catch (error) {
            if (!hintedRow) throw error;
            notify('warning', '관리 모달은 현재 화면의 값으로 먼저 표시했습니다. 최신 정보 갱신은 실패했습니다.');
        }
    }

    const table = createFundsBankTransactionTable({
        api,
        reorderApi: API.reorder,
        onSummary: renderSummary,
        onNotify: notify,
        async onAction(action, button) {
            const id = button.dataset.id || '';
            const evidenceId = button.dataset.evidenceId || '';
            const voucherId = button.dataset.voucherId || '';
            const rowHint = rowFromButton(button);
            try {
                if (action === 'manage') {
                    await openManageModal(id, rowHint);
                    return;
                }
                if (action === 'delete') {
                    if (await deleteBankTransaction(id)) {
                        table.ajax.reload(null, false);
                    }
                    return;
                }
                if (action === 'source') {
                    await openSourceModal(id, rowHint);
                } else if (action === 'edit-evidence') {
                    await openEvidenceEdit(evidenceId);
                } else if (action === 'voucher') {
                    await openVoucher(voucherId);
                } else if (action === 'create-voucher') {
                    openVoucherCreate(evidenceId);
                } else if (action === 'delete') {
                    if (!window.confirm('입출금 원본을 휴지통으로 이동할까요?')) return;
                    await postJson(API.delete, { id });
                    notify('success', '입출금 원본을 휴지통으로 이동했습니다.');
                    table.ajax.reload(null, false);
                } else if (action === 'restore') {
                    await postJson(API.restore, { id });
                    notify('success', '입출금 원본을 복구했습니다.');
                    table.ajax.reload(null, false);
                }
            } catch (error) {
                notify('error', error.message);
            }
        },
    });
    fundsTable = table;

    document.addEventListener('journal:voucher-saved', () => {
        fundsTable?.ajax?.reload(null, false);
    });

    document.addEventListener('trash:changed', (event) => {
        if (event.detail?.type === 'fundsBankTransaction') {
            fundsTable?.ajax?.reload(null, false);
        }
    });

    document.addEventListener('trash:detail-render', (event) => {
        const detail = event.detail || {};
        if (detail.type !== 'fundsBankTransaction') return;
        const row = detail.data || {};
        const target = detail.modal?.querySelector('#fundsBankTransaction-trash-detail');
        if (!target) return;
        const account = [row.bank_name, row.account_name].filter(Boolean).join(' ') || '-';
        target.innerHTML = `
            <div class="small">
                <dl class="row mb-0">
                    <dt class="col-4">거래일시</dt><dd class="col-8">${escapeHtml(row.transaction_datetime || row.transaction_date || '-')}</dd>
                    <dt class="col-4">계좌</dt><dd class="col-8">${escapeHtml(account)}</dd>
                    <dt class="col-4">거래내용</dt><dd class="col-8">${escapeHtml(row.description || '-')}</dd>
                    <dt class="col-4">입금</dt><dd class="col-8">${escapeHtml(row.deposit_amount || '0')}</dd>
                    <dt class="col-4">출금</dt><dd class="col-8">${escapeHtml(row.withdraw_amount || '0')}</dd>
                    <dt class="col-4">삭제일시</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
                </dl>
            </div>
        `;
    });

    document.querySelector('#fundsBankTransactionsTable tbody')?.addEventListener('dblclick', (event) => {
        if (event.target.closest('button, a, input, select, textarea, .reorder-handle')) {
            return;
        }

        const rowEl = event.target.closest('tr');
        const row = rowEl ? table.row(rowEl).data() : null;
        const id = row?.id || '';
        if (!id) {
            return;
        }

        void openManageModal(id, row).catch((error) => notify('error', error.message));
    });

    SearchForm({
        table,
        apiList: api,
        tableId: 'fundsBankTransactions',
        defaultSearchField: 'description',
        dateOptions: [
            { value: 'transaction_datetime', label: '거래일시' },
            { value: 'uploaded_at', label: '업로드일시' },
        ],
        normalizeFilters: (filters = []) => filters.map((filter) => {
            const field = String(filter.field || '');
            const value = String(filter.value || '').trim();
            if (field === 'voucher_link_status') {
                if (/미|unlinked/i.test(value)) return { ...filter, value: 'UNLINKED' };
                if (/연결|linked/i.test(value)) return { ...filter, value: 'LINKED' };
            }
            if (field === 'direction') {
                if (/입|in/i.test(value)) return { ...filter, value: 'IN' };
                if (/출|out/i.test(value)) return { ...filter, value: 'OUT' };
            }
            if (field === 'evidence_status') {
                if (/삭제|deleted/i.test(value)) return { ...filter, value: 'DELETED' };
                if (/정상|active/i.test(value)) return { ...filter, value: 'ACTIVE' };
                if (/오류|error/i.test(value)) return { ...filter, value: 'ERROR' };
                if (/중복|duplicated/i.test(value)) return { ...filter, value: 'DUPLICATED' };
            }
            if (field === 'deleted_scope') {
                if (/삭제|deleted/i.test(value)) return { ...filter, value: 'DELETED' };
                if (/전체|all/i.test(value)) return { ...filter, value: 'ALL' };
                if (/정상|active/i.test(value)) return { ...filter, value: 'ACTIVE' };
            }
            return filter;
        }),
    });

    document.getElementById('fundsBankSourceEditBtn')?.addEventListener('click', (event) => {
        void openEvidenceEdit(event.currentTarget.dataset.evidenceId || '').catch((error) => notify('error', error.message));
    });

    document.getElementById('fundsBankManageModal')?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-funds-manage-action]');
        if (!button || !activeManageRow) return;

        const action = button.dataset.fundsManageAction;
        const id = activeManageRow.id || '';
        const evidenceId = activeManageRow.evidence_id || '';
        const voucherId = activeManageRow.voucher_id || '';

        try {
            if (action === 'delete') {
                if (await deleteBankTransaction(id)) {
                    table.ajax.reload(null, false);
                    window.bootstrap?.Modal.getInstance(document.getElementById('fundsBankManageModal'))?.hide();
                }
                return;
            }
            if (action === 'source') {
                await openSourceModal(id, activeManageRow);
            } else if (action === 'edit-evidence') {
                await openEvidenceEdit(evidenceId);
            } else if (action === 'voucher') {
                await openVoucher(voucherId);
            } else if (action === 'delete') {
                if (!window.confirm('\uC785\uCD9C\uAE08 \uC6D0\uBCF8\uC744 \uD734\uC9C0\uD1B5\uC73C\uB85C \uC774\uB3D9\uD560\uAE4C\uC694?')) return;
                await postJson(API.delete, { id });
                notify('success', '\uC785\uCD9C\uAE08 \uC6D0\uBCF8\uC744 \uD734\uC9C0\uD1B5\uC73C\uB85C \uC774\uB3D9\uD588\uC2B5\uB2C8\uB2E4.');
                table.ajax.reload(null, false);
                window.bootstrap?.Modal.getInstance(document.getElementById('fundsBankManageModal'))?.hide();
            } else if (action === 'restore') {
                await postJson(API.restore, { id });
                notify('success', '\uC785\uCD9C\uAE08 \uC6D0\uBCF8\uC744 \uBCF5\uAD6C\uD588\uC2B5\uB2C8\uB2E4.');
                table.ajax.reload(null, false);
                window.bootstrap?.Modal.getInstance(document.getElementById('fundsBankManageModal'))?.hide();
            }
        } catch (error) {
            notify('error', error.message);
        }
    });
})();
