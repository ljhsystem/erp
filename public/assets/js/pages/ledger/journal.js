import { formatNumber, onlyNumber } from '/public/assets/js/common/format.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { createJournalBasicInfoBridge } from '/public/assets/js/pages/ledger/journal.basic-info.js';
import { initCodeSelectControls, onCodeOptionsLoaded } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    const journalTableEl = document.getElementById('journal-table');
    const tableBody = document.getElementById('journal-table-body') || journalTableEl?.querySelector('tbody');
    const form = document.getElementById('journal-edit-form');
    const modalEl = document.getElementById('journalModal');
    const addLineBtn = document.getElementById('btnAddVoucherLine');
    const lineBody = document.getElementById('voucher-line-body');
    const debitTotalEl = document.getElementById('voucher_debit_total');
    const creditTotalEl = document.getElementById('voucher_credit_total');
    const balanceStatusEl = document.getElementById('voucher_balance_status');
    const voucherStatusEl = document.getElementById('voucher_status');
    const voucherStatusBadgeEl = document.getElementById('voucher_status_badge');
    const voucherNoDisplayEl = document.getElementById('voucher_no_display');
    const voucherDateEl = document.getElementById('voucher_date');
    const voucherSourceTypeEl = document.getElementById('voucher_source_type');
    const voucherSourceIdEl = document.getElementById('voucher_source_id');
    const summaryTextEl = document.getElementById('voucher_summary_text');
    const summarySuggestionsEl = document.getElementById('voucher_summary_suggestions');
    const modalTitleEl = document.getElementById('journalModalLabel');
    const modalDeleteBtn = document.getElementById('btnDeleteVoucherInModal');
    const modalAdvanceStatusBtn = document.getElementById('btnAdvanceVoucherStatus');
    const modalSaveBtn = document.getElementById('btnSaveVoucher');
    const addPaymentBtn = document.getElementById('btnAddVoucherPayment');
    const paymentBody = document.getElementById('voucher-payment-body');
    const transactionModalEl = document.getElementById('journalTransactionSearchModal');
    const transactionSearchBody = document.getElementById('journal_transaction_search_body');
    const transactionSearchKeywordEl = document.getElementById('journal_transaction_search_keyword');
    const linkedTransactionIdEl = document.getElementById('linked_transaction_id');
    const linkedTransactionSummaryEl = document.getElementById('linked_transaction_summary');
    const selectTransactionBtn = document.getElementById('btnSelectTransaction');
    const clearTransactionLinkBtn = document.getElementById('btnClearTransactionLink');
    const searchTransactionBtn = document.getElementById('btnSearchTransaction');

    if (!tableBody || !journalTableEl || !form || !modalEl || !lineBody || !voucherDateEl) {
        return;
    }

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    if (transactionModalEl && transactionModalEl.parentElement !== document.body) {
        document.body.appendChild(transactionModalEl);
    }

    const pickerLayerEl = document.getElementById('journal-today-picker');
    if (pickerLayerEl && pickerLayerEl.parentElement !== document.body) {
        document.body.appendChild(pickerLayerEl);
    }

    const API = {
        list: '/api/ledger/voucher/list',
        detail: '/api/ledger/voucher/detail',
        save: '/api/ledger/voucher/save',
        status: '/api/ledger/voucher/status',
        summarySearch: '/api/ledger/voucher/summary-search',
        remove: '/api/ledger/voucher/delete',
        transactionSearch: '/api/ledger/voucher/transaction-search',
        accountList: '/api/ledger/account/list',
        trash: '/api/ledger/voucher/trash',
        restore: '/api/ledger/voucher/restore',
        purge: '/api/ledger/voucher/purge',
        purgeAll: '/api/ledger/voucher/purge-all',
        subAccountList: '/api/account/sub-accounts',
        clientList: '/api/settings/base-info/client/list',
        projectList: '/api/settings/base-info/project/list',
        employeeList: '/api/settings/organization/employee/list',
        bankAccountList: '/api/settings/base-info/bank-account/list',
        cardList: '/api/settings/base-info/card/list',
        reorder: '/api/ledger/voucher/reorder',
    };

    const STATUS_LABELS = {
        draft: '임시저장',
        confirmed: '확정',
        posted: '전기',
        closed: '마감',
        deleted: '삭제',
    };

    const STATUS_STEPS = [
        { value: 'draft', label: '임시저장' },
        { value: 'confirmed', label: '확정' },
        { value: 'posted', label: '전기' },
        { value: 'closed', label: '마감' },
    ];

    const NEXT_STATUS = {
        draft: { value: 'confirmed', label: '확정' },
        confirmed: { value: 'posted', label: '전기' },
        posted: { value: 'closed', label: '마감' },
    };

    const SOURCE_TYPE_LABELS = {
        TAX: '홈택스',
        CARD: '카드사',
        BANK: '은행',
        MANUAL: '수기입력',
    };

    const TYPE_LABELS = {
        TRANSACTION: '거래',
        ORDER: '주문',
        VOUCHER: '전표',
        CONTRACT: '계약',
        PAYMENT: '결제',
        CLIENT: '거래처',
        PROJECT: '프로젝트',
        EMPLOYEE: '직원',
        ACCOUNT: '계좌',
        BANK_ACCOUNT: '계좌',
        CARD: '카드',
    };

    const LINKED_STATUS_LABELS = {
        linked: '연결',
        unlinked: '미연결',
    };

    const JOURNAL_DATE_OPTIONS = [
        { value: 'voucher_date', label: '전표일자' },
        { value: 'updated_at', label: '수정일시' },
    ];

    const QUICK_CREATE_ACCOUNT_VALUE = '__quick_create_account__';
    const modal = window.bootstrap ? new bootstrap.Modal(modalEl, { focus: false }) : null;
    const transactionModal = window.bootstrap && transactionModalEl
        ? new bootstrap.Modal(transactionModalEl, { focus: false })
        : null;
    const basicInfoBridge = createJournalBasicInfoBridge({ notify });

    let accountPickerItems = null;
    const accountPickerById = new Map();
    const accountPickerByCode = new Map();
    const pickerOptionCache = {};
    const accountPolicyCache = {};
    let transactionRows = [];
    let journalTable = null;
    let summaryAutocompleteTimer = null;
    let summaryAutocompleteItems = [];
    let summaryAutocompleteActiveIndex = -1;
    let summaryAutocompleteAbort = null;

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
            AppCore.notify(type, message);
            return;
        }

        console[type === 'error' ? 'error' : 'log'](message);
    }

    function formatActorName(value) {
        const actor = String(value ?? '').trim();
        if (!actor) {
            return '';
        }

        return actor.replace(/^(USER|ADMIN|SYSTEM|EMPLOYEE):/i, '').trim() || actor;
    }

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.journal = function (row = {}) {
        const deletedBy = formatActorName(row.deleted_by_name ?? row.deleted_by ?? '');
        return `
            <td>${escapeHtml(row.voucher_no ?? '')}</td>
            <td>${escapeHtml(row.voucher_date ?? '')}</td>
            <td>${escapeHtml(translateStatus(row.status ?? ''))}</td>
            <td>${escapeHtml(formatAmountValue(row.voucher_amount ?? row.debit_total ?? row.payment_total ?? 0) || '0')}</td>
            <td>${escapeHtml(row.summary_text ?? '')}</td>
            <td>${escapeHtml(row.deleted_at ?? '')}</td>
            <td>${escapeHtml(deletedBy)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id ?? '')}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id ?? '')}">영구삭제</button>
            </td>
        `;
    };

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, options);
        return response.json();
    }

    function formatDate(date) {
        if (!date) {
            return '';
        }

        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function formatDateInputValue(value) {
        const digits = onlyNumber(value).slice(0, 8);

        if (digits.length <= 4) {
            return digits;
        }
        if (digits.length <= 6) {
            return `${digits.slice(0, 4)}-${digits.slice(4)}`;
        }

        return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
    }

    function normalizeSummaryKeyword(value) {
        return String(value ?? '').replace(/\s+/g, ' ').trim();
    }

    function closeSummaryAutocomplete() {
        summaryAutocompleteItems = [];
        summaryAutocompleteActiveIndex = -1;
        if (summarySuggestionsEl) {
            summarySuggestionsEl.innerHTML = '';
            summarySuggestionsEl.classList.add('d-none');
        }
    }

    function setSummaryAutocompleteActive(index) {
        if (!summarySuggestionsEl || summaryAutocompleteItems.length === 0) {
            return;
        }

        const maxIndex = summaryAutocompleteItems.length - 1;
        summaryAutocompleteActiveIndex = index < 0 ? maxIndex : (index > maxIndex ? 0 : index);

        summarySuggestionsEl.querySelectorAll('.summary-autocomplete-item').forEach((item, itemIndex) => {
            item.classList.toggle('active', itemIndex === summaryAutocompleteActiveIndex);
        });
    }

    function applySummaryAutocompleteItem(index) {
        const item = summaryAutocompleteItems[index];
        if (!item || !summaryTextEl) {
            return;
        }

        summaryTextEl.value = item.summary_text || '';
        closeSummaryAutocomplete();
    }

    function renderSummaryAutocomplete(items = []) {
        if (!summarySuggestionsEl || !summaryTextEl || summaryTextEl.disabled || summaryTextEl.readOnly) {
            closeSummaryAutocomplete();
            return;
        }

        summaryAutocompleteItems = items.filter((item) => String(item.summary_text || '').trim() !== '');
        summaryAutocompleteActiveIndex = -1;

        if (summaryAutocompleteItems.length === 0) {
            closeSummaryAutocomplete();
            return;
        }

        summarySuggestionsEl.innerHTML = summaryAutocompleteItems.map((item, index) => `
            <button type="button"
                    class="summary-autocomplete-item"
                    role="option"
                    data-index="${index}"
                    title="${escapeHtml(item.summary_text || '')}">
                ${escapeHtml(item.summary_text || '')}
            </button>
        `).join('');
        summarySuggestionsEl.classList.remove('d-none');
    }

    async function searchSummaryAutocomplete(keyword) {
        const normalizedKeyword = normalizeSummaryKeyword(keyword);
        if (normalizedKeyword.length < 2 || !summaryTextEl || summaryTextEl.disabled || summaryTextEl.readOnly) {
            closeSummaryAutocomplete();
            return;
        }

        if (summaryAutocompleteAbort) {
            summaryAutocompleteAbort.abort();
        }

        summaryAutocompleteAbort = new AbortController();

        try {
            const json = await fetchJson(`${API.summarySearch}?q=${encodeURIComponent(normalizedKeyword)}`, {
                signal: summaryAutocompleteAbort.signal,
            });

            if (!json.success) {
                notify('error', json.message || '적요 자동완성 목록을 불러오지 못했습니다.');
                closeSummaryAutocomplete();
                return;
            }

            if (normalizeSummaryKeyword(summaryTextEl.value) !== normalizedKeyword) {
                return;
            }

            renderSummaryAutocomplete(Array.isArray(json.items) ? json.items : []);
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            console.error('[ledger-journal] summary autocomplete failed', error);
            notify('error', '적요 자동완성 목록을 불러오지 못했습니다.');
            closeSummaryAutocomplete();
        }
    }

    function queueSummaryAutocompleteSearch() {
        if (summaryAutocompleteTimer) {
            clearTimeout(summaryAutocompleteTimer);
        }

        summaryAutocompleteTimer = setTimeout(() => {
            void searchSummaryAutocomplete(summaryTextEl?.value || '');
        }, 220);
    }

    function normalizeAmountValue(value) {
        const raw = String(value ?? '')
            .replace(/,/g, '')
            .trim();
        const [integerPart = ''] = raw.split('.');

        return onlyNumber(integerPart);
    }

    function parseAmountValue(value) {
        const normalized = normalizeAmountValue(value);
        return normalized === '' ? 0 : Number(normalized);
    }

    function formatAmountValue(value) {
        const normalized = normalizeAmountValue(value);
        return normalized === '' ? '' : formatNumber(normalized);
    }

    function setAmountInputValue(input, { formatted = true } = {}) {
        if (!input) {
            return '';
        }

        const normalized = normalizeAmountValue(input.value);
        input.value = normalized === ''
            ? ''
            : (formatted ? formatNumber(normalized) : normalized);

        return normalized;
    }

    function getAmountInputTarget(target) {
        if (!(target instanceof HTMLInputElement) || !target.classList.contains('input-amount')) {
            return null;
        }

        return target;
    }

    function translateStatus(value) {
        return STATUS_LABELS[value] || value || '-';
    }

    function renderStatusFlow(status, mode = '', meta = {}) {
        if (mode === 'modal') {
            return renderModalStatusTimeline(status, meta);
        }
        if (mode === 'table') {
            return escapeHtml(translateStatus(status));
        }

        const key = String(status || 'draft').toLowerCase();
        const currentIndex = Math.max(STATUS_STEPS.findIndex((step) => step.value === key), 0);
        const modeClass = mode ? ` voucher-flow-${mode}` : '';
        const shouldPulse = mode !== 'table';
        const steps = STATUS_STEPS.map((step, index) => {
            const currentClass = shouldPulse ? 'current current-step' : 'current';
            const stateClass = index < currentIndex ? 'done' : (index === currentIndex ? currentClass : 'pending');
            const check = index < currentIndex ? '<span class="voucher-flow-check">✓</span>' : '';

            return `
                <span class="voucher-flow-step voucher-flow-step-${step.value} ${stateClass}">
                    ${check}<span class="voucher-flow-label">${escapeHtml(step.label)}</span>
                </span>
            `;
        }).join('<span class="voucher-flow-arrow">→</span>');

        return `<div class="voucher-flow${modeClass}">${steps}</div>`;
    }

    function renderModalStatusTimeline(status, meta = {}) {
        const key = String(status || 'draft').toLowerCase();
        const currentIndex = Math.max(STATUS_STEPS.findIndex((step) => step.value === key), 0);
        const dateText = String(meta.updated_at || meta.created_at || '').slice(0, 16).replace('T', ' ');
        const actorText = meta.updated_actor_label
            || meta.created_actor_label
            || formatActorName(meta.updated_by_name || meta.updated_by || meta.created_by_name || meta.created_by || '');

        const steps = STATUS_STEPS.map((step, index) => {
            const stateClass = index < currentIndex ? 'done' : (index === currentIndex ? 'current' : 'pending');
            const nodeClass = index === currentIndex ? 'voucher-timeline-node current-step' : 'voucher-timeline-node';
            const icon = index < currentIndex
                ? '✓'
                : '';
            const detail = index <= currentIndex
                ? [
                    dateText ? `<span>${escapeHtml(dateText)}</span>` : '',
                    actorText ? `<span>${escapeHtml(actorText)}</span>` : '',
                ].filter(Boolean).join('')
                : '<span>대기 중</span>';

            return `
                <div class="voucher-timeline-step voucher-timeline-${step.value} ${stateClass}">
                    <div class="${nodeClass}">${icon}</div>
                    <div class="voucher-timeline-label">${escapeHtml(step.label)}</div>
                    <div class="voucher-timeline-meta">${detail || '<span>처리 중...</span>'}</div>
                </div>
            `;
        }).join('');

        return `<div class="voucher-timeline">${steps}</div>`;
    }

    function setStatusFlow(status = 'draft', meta = {}) {
        if (!voucherStatusBadgeEl) {
            return;
        }

        voucherStatusBadgeEl.className = 'voucher-status-timeline';
        voucherStatusBadgeEl.innerHTML = renderStatusFlow(status, 'modal', meta);
    }

    function updateStatusActionButton(status = 'draft') {
        if (!modalAdvanceStatusBtn) {
            return;
        }

        const id = document.getElementById('journal_id')?.value || '';
        const next = NEXT_STATUS[String(status || '').toLowerCase()];
        modalAdvanceStatusBtn.classList.toggle('d-none', !id || !next);
        modalAdvanceStatusBtn.disabled = !id || !next;
        modalAdvanceStatusBtn.textContent = next ? next.label : '';
        modalAdvanceStatusBtn.dataset.nextStatus = next?.value || '';
    }

    function translateType(value) {
        const key = String(value || '').toUpperCase();
        return TYPE_LABELS[key] || value || '-';
    }

    function translateSourceType(value) {
        const key = String(value || '').toUpperCase();
        return SOURCE_TYPE_LABELS[key] || value || '-';
    }

    function translateLinkedStatus(value) {
        return LINKED_STATUS_LABELS[value] || value || '미연결';
    }

    function buildTransactionSummary(row = null) {
        if (!row) {
            return '연결된 거래가 없습니다.';
        }

        const date = row.transaction_date || '-';
        const client = row.client_name || row.client_id || '-';
        const amount = formatAmountValue(row.total_amount || 0) || '0';

        return `${date} / ${client} / ${amount}`;
    }

    function setLinkedTransaction(row = null) {
        if (!linkedTransactionIdEl || !linkedTransactionSummaryEl) {
            return;
        }

        linkedTransactionIdEl.value = row?.id || '';
        linkedTransactionSummaryEl.textContent = buildTransactionSummary(row);
        linkedTransactionSummaryEl.title = row
            ? `${buildTransactionSummary(row)}${row.item_summary ? ` / ${row.item_summary}` : ''}`
            : '';
    }

    function setModalTitle(mode = 'create') {
        if (!modalTitleEl) {
            return;
        }

        const icon = '<i class="bi bi-journal-check me-2"></i>';
        modalTitleEl.innerHTML = mode === 'edit'
            ? `${icon}전표 수정`
            : `${icon}전표 등록`;

        modalDeleteBtn?.classList.toggle('d-none', mode !== 'edit');
    }

    function setModalEditability(status = 'draft') {
        const normalizedStatus = String(status || 'draft').toLowerCase();
        const isDraft = normalizedStatus === 'draft';
        const isConfirmed = normalizedStatus === 'confirmed';
        const isLocked = ['posted', 'closed', 'deleted'].includes(normalizedStatus);
        const canEditHeader = isDraft;
        const canEditLines = isDraft;

        voucherDateEl.disabled = !canEditHeader;
        document.getElementById('voucher_summary_text').disabled = !canEditHeader;
        document.getElementById('voucher_note').disabled = !canEditHeader;
        document.getElementById('voucher_memo').disabled = !canEditHeader;
        if (!canEditHeader) {
            closeSummaryAutocomplete();
        }
        voucherSourceTypeEl.disabled = !isDraft;
        if (voucherSourceTypeEl && window.jQuery?.fn?.select2) {
            window.jQuery(voucherSourceTypeEl).trigger('change.select2');
        }

        addLineBtn.disabled = !canEditLines;
        addPaymentBtn.disabled = !canEditLines;
        selectTransactionBtn.disabled = !canEditLines;
        clearTransactionLinkBtn.disabled = !canEditLines;
        modalSaveBtn.disabled = !isDraft;
        modalDeleteBtn.disabled = !isDraft;
        updateStatusActionButton(normalizedStatus);

        [lineBody, paymentBody].forEach((body) => {
            body?.querySelectorAll('input, select, textarea, button').forEach((el) => {
                el.disabled = !canEditLines;
            });
        });

        if (isConfirmed) {
            setValidationBadge('ok', '확정 전표입니다. 분개라인 수정은 제한됩니다.');
        }
    }

    function renderPickerOption(data) {
        const span = document.createElement('span');
        span.textContent = data?.text || '';

        if (data?.id === QUICK_CREATE_ACCOUNT_VALUE) {
            span.className = 'journal-quick-create-option';
        }

        return span;
    }

    function renderPickerSelection(data) {
        if (!data || !data.id || data.id === QUICK_CREATE_ACCOUNT_VALUE) {
            return '계정과목 선택';
        }

        return data.text || data.id;
    }

    function setSelect2Option(selectEl, value, text) {
        if (!selectEl || !window.jQuery) {
            return;
        }

        const normalizedValue = String(value ?? '').trim();
        const $select = window.jQuery(selectEl);

        if (normalizedValue === '') {
            $select.val('').trigger('change');
            return;
        }

        $select.find(`option[value="${normalizedValue}"]`).remove();
        $select.append(new Option(text || normalizedValue, normalizedValue, true, true));
        $select.val(normalizedValue).trigger('change');
    }

    function clearSelect2Value(selectEl) {
        if (!selectEl || !window.jQuery) {
            return;
        }

        window.jQuery(selectEl).val('').trigger('change');
    }

    function buildAccountPickerItems(rows = []) {
        accountPickerById.clear();
        accountPickerByCode.clear();

        const mappedRows = rows
            .map((row) => {
                const accountId = String(row.id ?? row.account_id ?? row.value ?? '').trim();
                const accountCode = String(row.account_code ?? '').trim();
                const accountName = String(row.account_name ?? row.name ?? '').trim();

                return {
                    id: accountId,
                    text: accountCode && accountName ? `${accountCode} - ${accountName}` : accountCode,
                    account_code: accountCode,
                    account_name: accountName,
                };
            })
            .filter((item) => item.id !== '');

        mappedRows.forEach((item) => {
            accountPickerById.set(item.id, item);
            if (item.account_code) {
                accountPickerByCode.set(item.account_code, item);
            }
        });

        return [
            { id: '', text: '계정과목 선택' },
            ...mappedRows,
            { id: QUICK_CREATE_ACCOUNT_VALUE, text: '+ 빠른 등록' },
        ];
    }

    async function ensureAccountPickerItems(force = false) {
        if (!force && Array.isArray(accountPickerItems)) {
            return accountPickerItems;
        }

        try {
            const json = await fetchJson(API.accountList);
            const rows = Array.isArray(json?.data) ? json.data : [];
            accountPickerItems = buildAccountPickerItems(rows);
        } catch (error) {
            console.error('[ledger-journal] account list load failed', error);
            accountPickerItems = buildAccountPickerItems([]);
        }

        return accountPickerItems;
    }

    async function resolveAccountPickerItem(value) {
        const rawValue = String(value || '').trim();
        if (rawValue === '') {
            return null;
        }

        await ensureAccountPickerItems();

        return accountPickerById.get(rawValue)
            || accountPickerByCode.get(rawValue)
            || null;
    }

    async function resolveAccountId(value) {
        const item = await resolveAccountPickerItem(value);
        return item?.id || String(value || '').trim();
    }

    function getAccountLabelFromLine(line = {}) {
        return line.account_text
            || line.account_label
            || [line.account_code, line.account_name].filter(Boolean).join(' - ')
            || line.account_id
            || '';
    }

    const REF_PICKER_CONFIG = {
        CLIENT: {
            url: API.clientList,
            placeholder: '거래처 선택',
            label(row) {
                return row.client_name || row.business_name || row.name || row.company_name || row.id || '';
            },
        },
        PROJECT: {
            url: API.projectList,
            placeholder: '프로젝트 선택',
            label(row) {
                return row.project_name || row.name || row.project_code || row.id || '';
            },
        },
        EMPLOYEE: {
            url: API.employeeList,
            placeholder: '직원 선택',
            label(row) {
                return row.employee_name || row.name || row.user_name || row.id || '';
            },
        },
        ACCOUNT: {
            url: API.bankAccountList,
            placeholder: '계좌 선택',
            label(row) {
                return row.account_name || row.bank_name || row.account_no || row.account_number || row.id || '';
            },
        },
        BANK_ACCOUNT: {
            url: API.bankAccountList,
            placeholder: '계좌 선택',
            label(row) {
                return row.account_name || row.bank_name || row.account_no || row.account_number || row.id || '';
            },
        },
        CARD: {
            url: API.cardList,
            placeholder: '카드 선택',
            label(row) {
                return row.card_name || row.card_no || row.card_number || row.client_name || row.id || '';
            },
        },
        TRANSACTION: {
            url: API.transactionSearch,
            placeholder: '거래 선택',
            label(row) {
                return row.item_summary || row.description || row.transaction_date || row.id || '';
            },
        },
        ORDER: {
            url: API.transactionSearch,
            placeholder: '주문 선택',
            label(row) {
                return row.order_ref || row.document_no || row.item_summary || row.summary_text || row.id || '';
            },
        },
        VOUCHER: {
            url: API.list,
            placeholder: '전표 선택',
            label(row) {
                return row.voucher_no || row.summary_text || row.id || '';
            },
        },
        CONTRACT: {
            url: API.transactionSearch,
            placeholder: '계약 선택',
            label(row) {
                return row.document_no || row.item_summary || row.description || row.id || '';
            },
        },
        PAYMENT: {
            url: API.list,
            placeholder: '결제 선택',
            label(row) {
                return row.voucher_no || row.summary_text || row.id || '';
            },
        },
    };
    function normalizeRows(payload) {
        return Array.isArray(payload?.data) ? payload.data : [];
    }

    async function ensurePickerOptions(refType, force = false) {
        const type = String(refType || '').toUpperCase();
        const config = REF_PICKER_CONFIG[type];
        if (!config) {
            return [{ id: '', text: '선택' }];
        }

        if (!force && pickerOptionCache[type]) {
            return pickerOptionCache[type];
        }

        try {
            const json = await fetchJson(config.url);
            const rows = normalizeRows(json);
            pickerOptionCache[type] = [
                { id: '', text: config.placeholder },
                ...rows.map((row) => ({
                    id: String(row.id ?? row.value ?? '').trim(),
                    text: String(config.label(row)).trim(),
                })).filter((item) => item.id !== ''),
            ];
        } catch (error) {
            console.error(`[ledger-journal] ${type} picker load failed`, error);
            pickerOptionCache[type] = [{ id: '', text: config.placeholder }];
        }

        return pickerOptionCache[type];
    }

    async function initRefPicker(selectEl, refType, selectedValue = '', options = {}) {
        if (!selectEl || !window.jQuery) {
            return;
        }

        const type = String(refType || '').toUpperCase();
        const config = REF_PICKER_CONFIG[type];
        AdminPicker.select2(selectEl, {
            placeholder: config?.placeholder || '선택',
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
        });

        const items = await ensurePickerOptions(type);
        const selectItems = options.includeNone
            ? [
                { id: '__none__', text: '선택(없음)' },
                ...items.filter((item) => String(item.id ?? '') !== ''),
            ]
            : items;

        AdminPicker.reloadSelect2(selectEl, selectItems, 'id', 'text', selectedValue || '');

        window.jQuery(selectEl)
            .off('select2:select.journalRefNone')
            .on('select2:select.journalRefNone', function (event) {
                if (event.params?.data?.id === '__none__') {
                    window.jQuery(this).val('').trigger('change');
                }
            });
    }

    async function loadAccountPolicies(accountId) {
        const id = await resolveAccountId(accountId);
        if (!id) {
            return [];
        }

        if (accountPolicyCache[id]) {
            return accountPolicyCache[id];
        }

        try {
            const json = await fetchJson(`${API.subAccountList}?account_id=${encodeURIComponent(id)}`);
            accountPolicyCache[id] = normalizeRows(json)
                .map((row) => {
                    const rawRefType = String(row.ref_type || '').toUpperCase();
                    const subCode = String(row.sub_code || row.code || '').toUpperCase();
                    const refType = rawRefType === 'REF_TARGET' ? subCode : (rawRefType || subCode);

                    return {
                        ref_type: refType,
                        is_required: Number(row.is_required || 0),
                    };
                })
                .filter((row) => row.ref_type !== '');
        } catch (error) {
            console.error('[ledger-journal] account policy load failed', error);
            accountPolicyCache[id] = [];
        }

        return accountPolicyCache[id];
    }

    function updateLineSubAccountColumnVisibility() {
        const tableEl = document.getElementById('voucher-line-table');
        if (!tableEl) {
            return;
        }

        const hasSubAccounts = Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'))
            .some((row) => row.dataset.hasSubAccounts === '1');

        tableEl.classList.toggle('has-subaccounts', hasSubAccounts);
    }

    async function reloadAllAccountPickers({ selectedValue = '', selectedText = '', sourceEl = null } = {}) {
        const items = await ensureAccountPickerItems(true);

        Array.from(lineBody.querySelectorAll('.line-account-code-picker')).forEach((selectEl) => {
            const currentValue = selectEl === sourceEl
                ? selectedValue
                : String(selectEl.value || '').trim();

            AdminPicker.reloadSelect2(selectEl, items, 'id', 'text', currentValue || '');

            if (selectEl === sourceEl && selectedValue) {
                setSelect2Option(selectEl, selectedValue, selectedText || selectedValue);
            }
        });
    }

    function bindAccountQuickCreate(selectEl) {
        if (!selectEl || !window.jQuery || selectEl.dataset.quickCreateBound === 'true') {
            return;
        }

        const $select = window.jQuery(selectEl);
        $select.on('select2:select', async (event) => {
            const selectedId = String(event?.params?.data?.id || '').trim();
            if (selectedId !== QUICK_CREATE_ACCOUNT_VALUE) {
                return;
            }

            event.preventDefault();
            window.setTimeout(() => {
                clearSelect2Value(selectEl);

                basicInfoBridge.openQuickCreate('account', {
                    sourceEl: selectEl,
                    async onSaved(payload) {
                        await reloadAllAccountPickers({
                            selectedValue: payload.value,
                            selectedText: payload.text,
                            sourceEl: selectEl,
                        });
                    },
                    async onDeleted(payload) {
                        await reloadAllAccountPickers();

                        if (payload.value && String(selectEl.value || '').trim() === payload.value) {
                            clearSelect2Value(selectEl);
                        }
                    },
                });
            }, 0);
        });

        selectEl.dataset.quickCreateBound = 'true';
    }

    async function initLineAccountPicker(selectEl, selectedValue = '') {
        if (!selectEl || !window.jQuery) {
            return;
        }

        bindAccountQuickCreate(selectEl);

        AdminPicker.select2(selectEl, {
            placeholder: '계정과목 선택',
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
            templateResult: renderPickerOption,
            templateSelection: renderPickerSelection,
        });

        const items = await ensureAccountPickerItems();
        const resolvedValue = await resolveAccountId(selectedValue);
        AdminPicker.reloadSelect2(selectEl, items, 'id', 'text', resolvedValue || '');

        window.jQuery(selectEl)
            .off('change.journalLineAccount select2:select.journalLineAccount')
            .on('change.journalLineAccount select2:select.journalLineAccount', () => {
                const row = selectEl.closest('tr');
                if (row) {
                    void renderLineSubAccountControls(row);
                }
            });
    }

    function emptyLineRow() {
        return '<tr class="voucher-line-empty"><td colspan="7" class="text-center text-muted py-4">분개 라인을 추가해주세요.</td></tr>';
    }

    function syncLineNumbers() {
        Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'))
            .forEach((row, index) => {
                const numberCell = row.querySelector('.line-no');
                if (numberCell) {
                    numberCell.textContent = String(index + 1);
                }
            });
    }

    function calculateTotals() {
        const rows = Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'));

        const debit = rows.reduce((sum, row) => {
            const value = parseAmountValue(row.querySelector('.line-debit')?.value || '0');
            return sum + (Number.isFinite(value) ? value : 0);
        }, 0);

        const credit = rows.reduce((sum, row) => {
            const value = parseAmountValue(row.querySelector('.line-credit')?.value || '0');
            return sum + (Number.isFinite(value) ? value : 0);
        }, 0);

        debitTotalEl.value = formatAmountValue(debit) || '0';
        creditTotalEl.value = formatAmountValue(credit) || '0';

        if (rows.length === 0) {
            setValidationBadge('error', '분개 라인을 입력해주세요.');
            return;
        }

        if (debit === credit) {
            setValidationBadge('ok', '차변/대변 합계가 일치합니다.');
            return;
        }

        setValidationBadge('error', '차변/대변 합계가 일치하지 않습니다.');
    }

    function setValidationBadge(type = 'error', message = '') {
        if (!balanceStatusEl) {
            return;
        }

        const isOk = type === 'ok';
        balanceStatusEl.className = `voucher-validation-badge ${isOk ? 'voucher-validation-ok' : 'voucher-validation-error'}`;
        balanceStatusEl.textContent = message;
    }

    async function renderLineSubAccountControls(row, line = {}) {
        const container = row.querySelector('.journal-line-subaccounts');
        const selectedValue = row.querySelector('.line-account-code-picker')?.value?.trim()
            || line.account_id
            || line.account_code
            || '';
        const accountId = await resolveAccountId(selectedValue);
        if (!container) {
            return;
        }

        if (!accountId) {
            row.dataset.hasSubAccounts = '0';
            container.className = 'journal-line-subaccounts';
            container.textContent = '';
            updateLineSubAccountColumnVisibility();
            return;
        }

        row.dataset.hasSubAccounts = '0';
        container.className = 'journal-line-subaccounts';
        container.innerHTML = '';

        const policies = await loadAccountPolicies(accountId);
        if (!policies.length) {
            container.className = 'journal-line-subaccounts';
            container.innerHTML = '<span class="journal-subaccount-empty">보조계정 없음</span>';
            updateLineSubAccountColumnVisibility();
            return;
        }

        row.dataset.hasSubAccounts = '1';
        container.className = 'journal-line-subaccounts journal-line-subaccount-grid';
        container.innerHTML = policies.map((policy, index) => `
            <label class="journal-line-subaccount-field">
                <span>${escapeHtml(translateType(policy.ref_type))}${policy.is_required ? ' *' : ''}</span>
                <select class="form-select form-select-sm line-ref-picker"
                        data-ref-type="${escapeHtml(policy.ref_type)}"
                        data-required="${policy.is_required ? '1' : '0'}"
                        data-policy-index="${index}">
                    <option value="">선택</option>
                </select>
            </label>
        `).join('');

        const selectedRefs = Array.isArray(line.refs) && line.refs.length > 0
            ? line.refs
            : [{ ref_type: line.ref_type || '', ref_id: line.ref_id || '' }];
        const selectedMap = new Map(selectedRefs
            .map((ref) => [
                String(ref.ref_type || '').toUpperCase(),
                String(ref.ref_id || '').trim(),
            ])
            .filter(([refType, refId]) => refType !== '' && refId !== ''));
        for (const selectEl of container.querySelectorAll('.line-ref-picker')) {
            const refType = selectEl.dataset.refType || '';
            const selectedValue = selectedMap.get(refType) || '';
            await initRefPicker(selectEl, refType, selectedValue);
        }
        updateLineSubAccountColumnVisibility();
    }

    async function addLineRow(line = {}) {
        lineBody.querySelector('.voucher-line-empty')?.remove();

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-center line-no"></td>
            <td>
                <select class="form-select form-select-sm line-account-code-picker">
                    <option value="">계정과목 선택</option>
                </select>
            </td>
            <td class="line-ref-cell">
                <div class="journal-line-subaccounts"></div>
            </td>
            <td>
                <input type="text"
                       inputmode="numeric"
                       class="form-control form-control-sm line-debit input-amount"
                       value="${escapeHtml(line.debit || '')}"
                       placeholder="0">
            </td>
            <td>
                <input type="text"
                       inputmode="numeric"
                       class="form-control form-control-sm line-credit input-amount"
                       value="${escapeHtml(line.credit || '')}"
                       placeholder="0">
            </td>
            <td>
                <input type="text"
                       class="form-control form-control-sm line-summary"
                       value="${escapeHtml(line.line_summary || '')}"
                       placeholder="라인 적요">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-line">삭제</button>
            </td>
        `;

        lineBody.appendChild(row);
        setAmountInputValue(row.querySelector('.line-debit'));
        setAmountInputValue(row.querySelector('.line-credit'));
        syncLineNumbers();
        calculateTotals();

        const accountSelect = row.querySelector('.line-account-code-picker');
        const selectedAccountValue = line.account_id || line.account_code || '';
        await initLineAccountPicker(accountSelect, selectedAccountValue);

        if (selectedAccountValue && !accountSelect.value) {
            const resolvedItem = await resolveAccountPickerItem(selectedAccountValue);
            setSelect2Option(
                accountSelect,
                resolvedItem?.id || selectedAccountValue,
                resolvedItem?.text || getAccountLabelFromLine(line) || selectedAccountValue
            );
        }

        await renderLineSubAccountControls(row, line);
    }

    function resetModal() {
        form.reset();
        document.getElementById('journal_id').value = '';
        if (voucherNoDisplayEl) {
            voucherNoDisplayEl.value = '자동발번';
        }
        voucherDateEl.value = formatDate(new Date());
        voucherStatusEl.value = 'draft';
        setStatusFlow('draft');
        setVoucherSource('MANUAL', '');
        setLinkedTransaction(null);
        lineBody.innerHTML = emptyLineRow();
        if (paymentBody) {
            paymentBody.innerHTML = emptyPaymentRow();
        }
        setModalTitle('create');
        updateLineSubAccountColumnVisibility();
        setModalEditability('draft');
        calculateTotals();
    }

    function setVoucherSource(sourceType = 'MANUAL', sourceId = '') {
        const normalizedType = String(sourceType || 'MANUAL').trim().toUpperCase() || 'MANUAL';
        const normalizedId = String(sourceId || '').trim();

        if (voucherSourceTypeEl) {
            voucherSourceTypeEl.value = normalizedType;
            if (window.jQuery?.fn?.select2) {
                window.jQuery(voucherSourceTypeEl).val(normalizedType).trigger('change.select2');
            }
        }
        if (voucherSourceIdEl) {
            voucherSourceIdEl.value = normalizedId;
        }
    }

    function collectLines() {
        return Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'))
            .map((row) => {
                const accountValue = row.querySelector('.line-account-code-picker')?.value?.trim() ?? '';
                const accountItem = accountPickerById.get(accountValue) || accountPickerByCode.get(accountValue);
                return {
                    account_id: accountItem?.id || accountValue,
                    refs: getLineRefs(row),
                    debit: normalizeAmountValue(row.querySelector('.line-debit')?.value ?? '') || '0',
                    credit: normalizeAmountValue(row.querySelector('.line-credit')?.value ?? '') || '0',
                    line_summary: row.querySelector('.line-summary')?.value?.trim() ?? '',
                };
            })
            .filter((line) => line.account_id || line.refs.length > 0 || Number(line.debit) > 0 || Number(line.credit) > 0 || line.line_summary);
    }

    function getLineRefs(row) {
        return Array.from(row.querySelectorAll('.line-ref-picker'))
            .map((selectEl) => ({
                ref_type: String(selectEl.dataset.refType || '').toUpperCase(),
                ref_id: String(selectEl.value || '').trim(),
                is_primary: selectEl.dataset.policyIndex === '0' ? 1 : 0,
            }))
            .filter((item) => item.ref_type !== '' && item.ref_id !== '');
    }

    function emptyPaymentRow() {
        return '<tr class="voucher-payment-empty"><td colspan="6" class="text-center text-muted py-3">결제수단이 필요한 경우 추가해주세요.</td></tr>';
    }

    function syncPaymentNumbers() {
        Array.from(paymentBody?.querySelectorAll('tr') || [])
            .filter((row) => !row.classList.contains('voucher-payment-empty'))
            .forEach((row, index) => {
                const numberCell = row.querySelector('.payment-no');
                if (numberCell) {
                    numberCell.textContent = String(index + 1);
                }
            });
    }

    function updatePaymentDirectionColumnVisibility() {
        const tableEl = document.getElementById('voucher-payment-table');
        if (!tableEl) {
            return;
        }

        const hasAccountPayment = Array.from(paymentBody?.querySelectorAll('tr') || [])
            .filter((row) => !row.classList.contains('voucher-payment-empty'))
            .some((row) => row.querySelector('.payment-type')?.value === 'ACCOUNT');

        tableEl.classList.toggle('has-account-payment', hasAccountPayment);
    }

    async function addPaymentRow(payment = {}) {
        if (!paymentBody) {
            return;
        }

        paymentBody.querySelector('.voucher-payment-empty')?.remove();

        const row = document.createElement('tr');
        const paymentDirection = String(payment.payment_direction || payment.direction || 'OUT').toUpperCase();
        row.innerHTML = `
            <td class="text-center payment-no"></td>
            <td>
                <select class="form-select form-select-sm payment-type">
                    <option value="">선택</option>
                    <option value="ACCOUNT" ${payment.payment_type === 'ACCOUNT' ? 'selected' : ''}>계좌</option>
                    <option value="CARD" ${payment.payment_type === 'CARD' ? 'selected' : ''}>카드</option>
                </select>
            </td>
            <td class="payment-direction-cell">
                <select class="form-select form-select-sm payment-direction">
                    <option value="IN" ${paymentDirection === 'IN' ? 'selected' : ''}>입금</option>
                    <option value="OUT" ${paymentDirection === 'OUT' ? 'selected' : ''}>출금</option>
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm payment-id">
                    <option value="">결제수단 선택</option>
                </select>
            </td>
            <td>
                <input type="text"
                       inputmode="numeric"
                       class="form-control form-control-sm payment-amount input-amount"
                       value="${escapeHtml(payment.amount || '')}"
                       placeholder="0">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-payment">삭제</button>
            </td>
        `;

        paymentBody.appendChild(row);
        setAmountInputValue(row.querySelector('.payment-amount'));
        syncPaymentNumbers();
        await updatePaymentMethodPicker(row, payment.payment_id || '');
        updatePaymentDirectionColumnVisibility();
    }

    async function updatePaymentMethodPicker(row, selectedValue = '') {
        const type = row.querySelector('.payment-type')?.value || '';
        const selectEl = row.querySelector('.payment-id');
        if (!selectEl) {
            return;
        }

        if (type === 'ACCOUNT') {
            await initRefPicker(selectEl, 'ACCOUNT', selectedValue, { includeNone: true });
            updatePaymentDirectionColumnVisibility();
            return;
        }

        if (type === 'CARD') {
            await initRefPicker(selectEl, 'CARD', selectedValue, { includeNone: true });
            updatePaymentDirectionColumnVisibility();
            return;
        }

        clearSelect2Value(selectEl);
        updatePaymentDirectionColumnVisibility();
    }

    function collectPayments() {
        return Array.from(paymentBody?.querySelectorAll('tr') || [])
            .filter((row) => !row.classList.contains('voucher-payment-empty'))
            .map((row) => ({
                payment_direction: row.querySelector('.payment-direction')?.value?.trim() || 'OUT',
                payment_type: row.querySelector('.payment-type')?.value?.trim() || '',
                payment_id: row.querySelector('.payment-id')?.value?.trim() || '',
                amount: normalizeAmountValue(row.querySelector('.payment-amount')?.value ?? '') || '0',
            }))
            .filter((payment) => payment.payment_type || payment.payment_id || Number(payment.amount) > 0);
    }

    function validateBeforeSave() {
        const lines = collectLines();

        if (lines.length === 0) {
            notify('warning', '분개 라인을 1개 이상 입력해주세요.');
            return false;
        }

        let debitTotal = 0;
        let creditTotal = 0;

        for (let index = 0; index < lines.length; index += 1) {
            const line = lines[index];
            const debit = Number(line.debit || '0');
            const credit = Number(line.credit || '0');

            if (!line.account_id) {
                notify('warning', `${index + 1}번 라인의 계정과목을 선택해주세요.`);
                return false;
            }

            const row = Array.from(lineBody.querySelectorAll('tr'))
                .filter((tr) => !tr.classList.contains('voucher-line-empty'))[index];
            const refPickers = Array.from(row?.querySelectorAll('.line-ref-picker') || []);
            const requiredPickers = refPickers.filter((selectEl) => selectEl.dataset.required === '1');
            const selectedPickers = refPickers.filter((selectEl) => String(selectEl.value || '').trim());
            if (false && refPickers.length > 0 && selectedPickers.length === 0) {
                notify('warning', `${index + 1}번째 라인은 보조계정을 선택해야 합니다.`);
                refPickers[0]?.focus();
                return false;
            }
            if (false && requiredPickers.length > 1) {
                notify('warning', `${index + 1}번째 라인은 필수 보조계정이 여러 개입니다. 현재 전표라인 DB 구조는 보조계정 1개만 저장할 수 있습니다.`);
                requiredPickers[0]?.focus();
                return false;
            }
            for (const requiredPicker of requiredPickers) {
                if (!String(requiredPicker.value || '').trim()) {
                    notify('warning', `${index + 1}번째 라인의 필수 보조계정을 선택해주세요.`);
                    requiredPicker.focus();
                    return false;
                }
            }

            if (false && selectedPickers.length > 1) {
                notify('warning', `${index + 1}번째 라인은 보조계정을 1개만 선택할 수 있습니다.`);
                selectedPickers[1]?.focus();
                return false;
            }

            if (debit <= 0 && credit <= 0) {
                notify('warning', `${index + 1}번 라인의 차변 또는 대변 금액을 입력해주세요.`);
                return false;
            }

            if (debit > 0 && credit > 0) {
                notify('warning', `${index + 1}번 라인은 차변과 대변 중 하나만 입력할 수 있습니다.`);
                return false;
            }

            debitTotal += Number.isFinite(debit) ? debit : 0;
            creditTotal += Number.isFinite(credit) ? credit : 0;
        }

        if (debitTotal !== creditTotal) {
            notify('warning', '차변 합계와 대변 합계가 일치해야 합니다.');
            return false;
        }

        const payments = collectPayments();
        for (let index = 0; index < payments.length; index += 1) {
            const payment = payments[index];
            if (!payment.payment_type) {
                notify('warning', `${index + 1}번째 결제유형을 선택해주세요.`);
                return false;
            }
            if (!payment.payment_id) {
                notify('warning', `${index + 1}번째 결제수단을 선택해주세요.`);
                return false;
            }
            if (Number(payment.amount || 0) <= 0) {
                notify('warning', `${index + 1}번째 결제금액을 입력해주세요.`);
                return false;
            }
        }

        return true;
    }

    function getVoucherSortNo(row = {}) {
        const numericSortNo = Number(String(row.sort_no ?? '').replace(/,/g, ''));
        return Number.isFinite(numericSortNo) ? numericSortNo : 0;
    }

    function buildJournalColumns() {
        return [
            {
                title: '<i class="bi bi-arrows-move"></i>',
                className: 'reorder-handle no-colvis text-center',
                orderable: false,
                searchable: false,
                defaultContent: '<i class="bi bi-list"></i>',
            },
            {
                data: 'sort_no',
                title: '순서',
                className: 'text-center journal-sort-no-cell',
                render(data, type, row) {
                    const sortNo = getVoucherSortNo(row);

                    if (type === 'sort' || type === 'type') {
                        return sortNo;
                    }

                    return escapeHtml(data || '');
                },
            },
            {
                data: 'voucher_no',
                title: '전표번호',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'voucher_date',
                title: '전표일자',
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'status',
                title: '상태',
                defaultContent: '',
                render(data) {
                    return renderStatusFlow(data, 'table');
                },
            },
            {
                data: 'source_type',
                title: '자료출처',
                defaultContent: '',
                render(data) {
                    return escapeHtml(translateSourceType(data));
                },
            },
            {
                data: 'source_id',
                title: '자료원본ID',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'debit_total',
                title: '금액 (차변합계)',
                className: 'text-end',
                defaultContent: 0,
                render(data) {
                    return escapeHtml(formatAmountValue(data || 0) || '0');
                },
            },
            {
                data: 'credit_total',
                title: '대변합계',
                className: 'text-end',
                visible: false,
                defaultContent: 0,
                render(data) {
                    return escapeHtml(formatAmountValue(data || 0) || '0');
                },
            },
            {
                data: 'payment_total',
                title: '결제합계',
                className: 'text-end',
                visible: false,
                defaultContent: 0,
                render(data) {
                    return escapeHtml(formatAmountValue(data || 0) || '0');
                },
            },
            {
                data: 'line_count',
                title: '분개라인수',
                className: 'text-center',
                visible: false,
                defaultContent: 0,
                render(data) {
                    return escapeHtml(data || 0);
                },
            },
            {
                data: 'payment_count',
                title: '결제수',
                className: 'text-center',
                visible: false,
                defaultContent: 0,
                render(data) {
                    return escapeHtml(data || 0);
                },
            },
            {
                data: 'summary_text',
                title: '전표 적요',
                className: 'journal-summary-cell',
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'note',
                title: '비고',
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'memo',
                title: '메모',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'created_at',
                title: '생성일시',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'created_by',
                title: '생성자ID',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'updated_at',
                title: '수정일시',
                visible: false,
                name: 'updated_at',
                render(data, type, row) {
                    return escapeHtml(data || row.created_at || '');
                },
            },
            {
                data: 'updated_by',
                title: '수정자ID',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'deleted_at',
                title: '삭제일시',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'deleted_by',
                title: '삭제자ID',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'account_label',
                title: '계정과목',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'linked_status',
                title: '거래연결여부',
                visible: false,
                defaultContent: 'unlinked',
                render(data, type) {
                    if (type === 'sort' || type === 'type') {
                        return data || 'unlinked';
                    }

                    return escapeHtml(translateLinkedStatus(data));
                },
            },
            {
                data: 'id',
                title: 'ID',
                className: 'no-colvis',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
        ];
    }

    function updateJournalCount() {
        if (!journalTable?.page) {
            return;
        }

        const countEl = document.getElementById('journalCount');
        if (!countEl) {
            return;
        }

        const info = journalTable.page.info();
        countEl.textContent = `총 ${info?.recordsDisplay ?? 0}건`;
    }



    function bindJournalTableEvents() {
        if (!window.jQuery || !journalTable) {
            return;
        }

        const $ = window.jQuery;
        $('#journal-table tbody')
            .off('click.journalSearchFill')
            .on('click.journalSearchFill', 'td', function (event) {
                if (event.target.closest('button, .reorder-handle')) {
                    return;
                }

                const cell = journalTable.cell(this);
                const cellIndex = cell.index();
                if (!cellIndex) {
                    return;
                }

                const field = journalTable.column(cellIndex.column).dataSrc();
                if (!field || typeof field !== 'string') {
                    return;
                }

                const value = cell.data();
                const $first = $('#journalSearchConditions .search-condition').first();
                $first.find('select').val(field);
                $first.find('input').val(value ?? '');
            });

        $('#journal-table tbody')
            .off('dblclick.journalEdit')
            .on('dblclick.journalEdit', 'tr', function (event) {
                if (event.target.closest('button, .reorder-handle')) {
                    return;
                }

                const row = journalTable.row(this).data();
                const id = row?.id || '';
                if (!id) {
                    return;
                }

                resetModal();
                void loadDetail(id);
            });
    }

    function initJournalTable() {
        if (journalTable || !window.jQuery?.fn?.DataTable) {
            return Boolean(journalTable);
        }

        journalTable = createDataTable({
            tableSelector: '#journal-table',
            api: API.list,
            columns: buildJournalColumns(),
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '엑셀 다운로드',
                    className: 'btn btn-outline-success btn-sm',
                    title: '전표입력',
                    filename: '전표입력',
                    exportOptions: {
                        columns: ':visible:not(.no-export):not(.no-colvis)',
                    },
                },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal,
                },
                {
                    text: '새 전표',
                    className: 'btn btn-warning btn-sm',
                    action: function () {
                        void openCreateModal();
                    },
                },
            ],
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
        });

        bindRowReorder(journalTable, {
            api: API.reorder,
            onSuccess() {
                notify('success', '전표 순번이 변경되었습니다.');
                journalTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '전표 순번 변경에 실패했습니다.');
                journalTable?.ajax.reload(null, false);
            }
        });
        bindTableHighlight('#journal-table', journalTable);

        journalTable.on('init.dt draw.dt', updateJournalCount);

        SearchForm({
            table: journalTable,
            apiList: API.list,
            tableId: 'journal',
            defaultSearchField: 'summary_text',
            dateOptions: JOURNAL_DATE_OPTIONS,
        });
        bindJournalTableEvents();
        updateJournalCount();

        return Boolean(journalTable);
    }

    function reloadJournalTable() {
        if (journalTable?.ajax) {
            journalTable.ajax.reload(null, false);
        }
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

        bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }).show();
    }

    async function openCreateModal() {
        resetModal();
        await addLineRow();
        await addLineRow();
        modal?.show();
    }

    async function loadDetail(id) {
        try {
            setModalTitle('edit');
            const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);

            if (!json.success || !json.data) {
                notify('error', json.message || '전표 상세 정보를 불러오지 못했습니다.');
                return;
            }

            const data = json.data;
            document.getElementById('journal_id').value = data.id || '';
            if (voucherNoDisplayEl) {
                voucherNoDisplayEl.value = data.voucher_no || data.sort_no || '-';
            }
            voucherDateEl.value = data.voucher_date || '';
            voucherStatusEl.value = data.status || 'draft';
            setStatusFlow(data.status || 'draft', data);
            setVoucherSource(data.source_type || 'MANUAL', data.source_id || '');
            document.getElementById('voucher_summary_text').value = data.summary_text || '';
            document.getElementById('voucher_note').value = data.note || '';
            document.getElementById('voucher_memo').value = data.memo || '';
            setLinkedTransaction(data.linked_transaction || null);

            lineBody.innerHTML = emptyLineRow();
            if (Array.isArray(data.lines) && data.lines.length > 0) {
                for (const line of data.lines) {
                    await addLineRow(line);
                }
            } else {
                await addLineRow();
                await addLineRow();
            }

            calculateTotals();
            if (paymentBody) {
                paymentBody.innerHTML = emptyPaymentRow();
                if (Array.isArray(data.payments) && data.payments.length > 0) {
                    for (const payment of data.payments) {
                        await addPaymentRow(payment);
                    }
                }
            }
            setModalEditability(data.status || 'draft');
            modal?.show();
        } catch (error) {
            console.error('[ledger-journal] loadDetail failed', error);
            setModalTitle('create');
            notify('error', '전표 상세 정보를 불러오지 못했습니다.');
        }
    }

    async function saveVoucher() {
        const formData = new FormData(form);
        formData.delete('ref_type');
        formData.delete('ref_id');
        formData.set('lines', JSON.stringify(collectLines()));
        formData.set('payments', JSON.stringify(collectPayments()));
        formData.set('linked_transaction_id', linkedTransactionIdEl?.value || '');

        const json = await fetchJson(API.save, {
            method: 'POST',
            body: formData,
        });

        if (!json.success) {
            notify('error', json.message || '전표 저장에 실패했습니다.');
            return;
        }

        notify('success', '전표가 저장되었습니다.');
        modal?.hide();
        reloadJournalTable();
    }

    async function advanceVoucherStatus() {
        const id = document.getElementById('journal_id')?.value || '';
        const nextStatus = modalAdvanceStatusBtn?.dataset.nextStatus || '';

        if (!id || !nextStatus) {
            return;
        }

        if (!window.confirm(`전표 상태를 ${translateStatus(nextStatus)} 상태로 변경하시겠습니까?`)) {
            return;
        }

        const formData = new FormData();
        formData.set('id', id);
        formData.set('status', nextStatus);

        const json = await fetchJson(API.status, {
            method: 'POST',
            body: formData,
        });

        if (!json.success) {
            notify('error', json.message || '전표 상태 변경에 실패했습니다.');
            return;
        }

        const status = json.data?.status || nextStatus;
        voucherStatusEl.value = status;
        setStatusFlow(status, json.data || {});
        setModalEditability(status);
        reloadJournalTable();
        notify('success', json.message || '전표 상태가 변경되었습니다.');
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

    function renderTransactionSearchRows(rows = []) {
        if (!transactionSearchBody) {
            return;
        }

        if (!rows.length) {
            transactionSearchBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">선택한 거래가 없습니다.</td>
                </tr>
            `;
            return;
        }

        transactionSearchBody.innerHTML = rows.map((row, index) => `
            <tr data-index="${index}">
                <td>${escapeHtml(row.transaction_date || '')}</td>
                <td>${escapeHtml(row.client_name || '-')}</td>
                <td>${escapeHtml(row.item_summary || row.description || row.sort_no || '')}</td>
                <td class="text-end">${escapeHtml(formatAmountValue(row.total_amount || 0) || '0')}</td>
                <td class="text-center">
                    <button type="button"
                            class="btn btn-outline-primary btn-sm btn-pick-transaction">선택</button>
                </td>
            </tr>
        `).join('');
    }

    async function loadTransactionSearch() {
        if (!transactionSearchBody) {
            return;
        }

        transactionSearchBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted py-4">거래를 불러오는 중입니다.</td>
            </tr>
        `;

        try {
            const query = new URLSearchParams();
            const keyword = transactionSearchKeywordEl?.value?.trim() || '';
            if (keyword) {
                query.set('q', keyword);
            }

            const json = await fetchJson(`${API.transactionSearch}?${query.toString()}`);
            if (!json.success) {
                throw new Error(json.message || '거래 목록을 불러오지 못했습니다.');
            }

            transactionRows = Array.isArray(json.data) ? json.data : [];
            renderTransactionSearchRows(transactionRows);
        } catch (error) {
            console.error('[ledger-journal] transaction search failed', error);
            transactionRows = [];
            transactionSearchBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger py-4">거래 목록을 불러오지 못했습니다.</td>
                </tr>
            `;
        }
    }

    function bindEvents() {
        addLineBtn?.addEventListener('click', () => {
            void addLineRow();
        });

        addPaymentBtn?.addEventListener('click', () => {
            void addPaymentRow();
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
                setSummaryAutocompleteActive(summaryAutocompleteActiveIndex + 1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setSummaryAutocompleteActive(summaryAutocompleteActiveIndex - 1);
                return;
            }

            if (event.key === 'Enter' && summaryAutocompleteActiveIndex >= 0) {
                event.preventDefault();
                applySummaryAutocompleteItem(summaryAutocompleteActiveIndex);
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

            if (!validateBeforeSave()) {
                return;
            }

            void saveVoucher();
        });

        lineBody.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-remove-line');
            if (!button) {
                return;
            }

            button.closest('tr')?.remove();

            if (!lineBody.querySelector('tr')) {
                lineBody.innerHTML = emptyLineRow();
            }

            syncLineNumbers();
            updateLineSubAccountColumnVisibility();
            calculateTotals();
        });

        lineBody.addEventListener('change', (event) => {
            const accountSelect = event.target.closest('.line-account-code-picker');
            if (!accountSelect) {
                return;
            }

            const row = accountSelect.closest('tr');
            if (row) {
                void renderLineSubAccountControls(row);
            }
        });

        paymentBody?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-remove-payment');
            if (!button) {
                return;
            }

            button.closest('tr')?.remove();

            if (!paymentBody.querySelector('tr')) {
                paymentBody.innerHTML = emptyPaymentRow();
            }

            syncPaymentNumbers();
            updatePaymentDirectionColumnVisibility();
        });

        paymentBody?.addEventListener('change', (event) => {
            const typeSelect = event.target.closest('.payment-type');
            if (!typeSelect) {
                return;
            }

            const row = typeSelect.closest('tr');
            if (row) {
                void updatePaymentMethodPicker(row);
            }
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

        document.addEventListener('focusout', (event) => {
            const input = getAmountInputTarget(event.target);
            if (!input) {
                return;
            }

            setAmountInputValue(input);
            calculateTotals();
        });

        modalDeleteBtn?.addEventListener('click', () => {
            const id = document.getElementById('journal_id')?.value || '';
            if (id && window.confirm('전표를 삭제하시겠습니까?')) {
                void deleteVoucher(id);
            }
        });

        modalAdvanceStatusBtn?.addEventListener('click', () => {
            void advanceVoucherStatus();
        });

        selectTransactionBtn?.addEventListener('click', () => {
            if (!transactionModal) {
                notify('warning', '거래 선택 모달을 찾을 수 없습니다.');
                return;
            }

            if (transactionSearchKeywordEl) {
                transactionSearchKeywordEl.value = '';
            }

            transactionModal.show();
            void loadTransactionSearch();
        });

        clearTransactionLinkBtn?.addEventListener('click', () => {
            setLinkedTransaction(null);
        });

        searchTransactionBtn?.addEventListener('click', () => {
            void loadTransactionSearch();
        });

        transactionSearchKeywordEl?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                void loadTransactionSearch();
            }
        });

        transactionSearchBody?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-pick-transaction');
            if (!button) {
                return;
            }

            const rowEl = button.closest('tr');
            const index = Number(rowEl?.dataset.index ?? -1);
            const row = transactionRows[index];
            if (!row) {
                return;
            }

            setLinkedTransaction(row);
            transactionModal?.hide();
        });
    }

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

        const deletedBy = formatActorName(row.deleted_by_name ?? row.deleted_by ?? '-');
        detailEl.innerHTML = `
            <div class="journal-trash-detail">
                <dl class="row mb-0 small">
                    <dt class="col-4">전표번호</dt>
                    <dd class="col-8">${escapeHtml(row.voucher_no ?? '-')}</dd>
                    <dt class="col-4">전표일자</dt>
                    <dd class="col-8">${escapeHtml(row.voucher_date ?? '-')}</dd>
                    <dt class="col-4">상태</dt>
                    <dd class="col-8">${renderStatusFlow(row.status ?? 'draft', 'detail')}</dd>
                    <dt class="col-4">전표금액</dt>
                    <dd class="col-8">${escapeHtml(formatAmountValue(row.voucher_amount ?? row.debit_total ?? 0) || '0')}</dd>
                    <dt class="col-4">전표 적요</dt>
                    <dd class="col-8">${escapeHtml(row.summary_text ?? '-')}</dd>
                    <dt class="col-4">삭제일시</dt>
                    <dd class="col-8">${escapeHtml(row.deleted_at ?? '-')}</dd>
                    <dt class="col-4">삭제자</dt>
                    <dd class="col-8">${escapeHtml(deletedBy || '-')}</dd>
                </dl>
            </div>
        `;
    });

    async function boot() {
        await initCodeSelectControls(modalEl);
        basicInfoBridge.bindDateInputs(modalEl);
        onCodeOptionsLoaded(() => {
            journalTable?.rows().invalidate('data').draw(false);
        });
        initJournalTable();
        bindEvents();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
