import * as NumberFormat from '/public/assets/js/common/format.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { createJournalBasicInfoBridge } from '/public/assets/js/pages/ledger/journal.basic-info.js';
import { initCodeSelectControls, onCodeOptionsLoaded } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { resolveDisplayText } from '/public/assets/js/pages/ledger/shared/utils.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    const onlyNumber = NumberFormat.onlyNumber || ((value) => String(value ?? '').replace(/\D/g, ''));
    const { formatNumber } = NumberFormat;
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
    const rejectPanelEl = document.getElementById('journalRejectPanel');
    const rejectReasonEl = document.getElementById('journalRejectReason');
    const voucherNoDisplayEl = document.getElementById('voucher_no_display');
    const voucherDateEl = document.getElementById('voucher_date');
    const voucherSourceTypeEl = document.getElementById('voucher_source_type');
    const voucherImportTypeEl = document.getElementById('voucher_import_type');
    const voucherSourceIdEl = document.getElementById('voucher_source_id');
    const voucherTransactionIdEl = document.getElementById('voucher_transaction_id');
    const voucherSourceTransactionInfoEl = document.getElementById('voucher_source_transaction_info');
    const summaryTextEl = document.getElementById('voucher_summary_text');
    const summarySuggestionsEl = document.getElementById('voucher_summary_suggestions');
    const modalTitleEl = document.getElementById('journalModalLabel');
    const modalDeleteBtn = document.getElementById('btnDeleteVoucherInModal');
    const modalSaveBtn = document.getElementById('btnSaveVoucher');
    const modalRequestReviewBtn = document.getElementById('btnRequestVoucherReview');
    const modalCancelReviewBtn = document.getElementById('btnCancelVoucherReview');
    const addPaymentBtn = document.getElementById('btnAddVoucherPayment');
    const paymentBody = document.getElementById('voucher-payment-body');
    const transactionModalEl = document.getElementById('journalTransactionSearchModal');
    const transactionSearchBody = document.getElementById('journal_transaction_search_body');
    const transactionSearchKeywordEl = document.getElementById('journal_transaction_search_keyword');
    const evidenceModalEl = document.getElementById('journalEvidenceSearchModal');
    const evidenceSearchBody = document.getElementById('journal_evidence_search_body');
    const evidenceSearchKeywordEl = document.getElementById('journal_evidence_search_keyword');
    const linkedTransactionIdEl = document.getElementById('linked_transaction_id');
    const linkedTransactionSummaryEl = document.getElementById('linked_transaction_summary');
    const linkedEvidenceIdEl = document.getElementById('linked_evidence_id');
    const linkedEvidenceSummaryEl = document.getElementById('linked_evidence_summary');
    const linkedEvidenceOriginEl = document.getElementById('linked_evidence_origin');
    const selectTransactionBtn = document.getElementById('btnSelectTransaction');
    const clearTransactionLinkBtn = document.getElementById('btnClearTransactionLink');
    const searchTransactionBtn = document.getElementById('btnSearchTransaction');
    const selectEvidenceBtn = document.getElementById('btnSelectEvidence');
    const clearEvidenceLinkBtn = document.getElementById('btnClearEvidenceLink');
    const searchEvidenceBtn = document.getElementById('btnSearchEvidence');
    const transactionNoticeEl = document.getElementById('journalTransactionNotice');

    if (!form || !modalEl || !lineBody || !voucherDateEl) {
        return;
    }

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    if (transactionModalEl && transactionModalEl.parentElement !== document.body) {
        document.body.appendChild(transactionModalEl);
    }

    if (evidenceModalEl && evidenceModalEl.parentElement !== document.body) {
        document.body.appendChild(evidenceModalEl);
    }

    const pickerLayerEl = document.getElementById('journal-today-picker');
    if (pickerLayerEl && pickerLayerEl.parentElement !== document.body) {
        document.body.appendChild(pickerLayerEl);
    }

    const API = {
        list: '/api/ledger/voucher/list',
        detail: '/api/ledger/voucher/detail',
        save: '/api/ledger/voucher/save',
        changeNumber: '/api/ledger/voucher/number',
        linkTransaction: '/api/ledger/voucher/link-transaction',
        linkEvidence: '/api/ledger/voucher/link-evidence',
        unlinkEvidence: '/api/ledger/voucher/unlink-evidence',
        summarySearch: '/api/ledger/voucher/summary-search',
        remove: '/api/ledger/voucher/delete',
        confirm: '/api/ledger/voucher/confirm',
        cancelReview: '/api/ledger/voucher/cancel-review',
        transactionSearch: '/api/ledger/voucher/transaction-search',
        evidenceSearch: '/api/ledger/voucher/evidence-search',
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
        confirmed: '검토요청',
        reviewed: '검토완료',
        posted: '승인',
        closed: '마감',
        deleted: '삭제',
    };

    const STATUS_STEPS = [
        { value: 'draft', label: '임시저장' },
        { value: 'confirmed', label: '검토요청' },
        { value: 'reviewed', label: '검토완료' },
        { value: 'posted', label: '승인' },
        { value: 'closed', label: '마감' },
    ];

    const SOURCE_TYPE_LABELS = {
        TAX: '세금계산서',
        CARD: '카드',
        BANK: '은행',
        MANUAL: '수기입력',
        TRANSACTION: '거래',
        SYSTEM: '시스템',
    };

    const TYPE_LABELS = {
        TRANSACTION: '거래',
        ORDER: '발주',
        VOUCHER: '전표',
        CONTRACT: '계약',
        PAYMENT: '결제',
        CLIENT: '거래처',
        PROJECT: '프로젝트',
        EMPLOYEE: '직원',
        ACCOUNT: '계정',
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

    const VOUCHER_NUMBER_PATTERN = /^\d{8}-\d{4}$/;
    const VOUCHER_NUMBER_LOCKED_STATUSES = new Set(['posted', 'closed']);
    const modal = window.bootstrap ? new bootstrap.Modal(modalEl, { focus: false }) : null;
    const transactionModal = window.bootstrap && transactionModalEl
        ? new bootstrap.Modal(transactionModalEl, { focus: false })
        : null;
    const evidenceModal = window.bootstrap && evidenceModalEl
        ? new bootstrap.Modal(evidenceModalEl, { focus: false })
        : null;
    const basicInfoBridge = createJournalBasicInfoBridge({ notify });

    let accountPickerItems = null;
    const accountPickerById = new Map();
    const accountPickerByCode = new Map();
    const pickerOptionCache = {};
    const accountPolicyCache = {};
    let transactionRows = [];
    let evidenceRows = [];
    let journalTable = null;
    let summaryAutocompleteTimer = null;
    let summaryAutocompleteItems = [];
    let summaryAutocompleteActiveIndex = -1;
    let summaryAutocompleteAbort = null;
    let importTypeRows = [];
    let transactionSearchTimer = null;
    let evidenceSearchTimer = null;

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

    function queueTransactionSearch() {
        window.clearTimeout(transactionSearchTimer);
        transactionSearchTimer = window.setTimeout(() => {
            void loadTransactionSearch();
        }, 250);
    }

    function queueEvidenceSearch() {
        window.clearTimeout(evidenceSearchTimer);
        evidenceSearchTimer = window.setTimeout(() => {
            void loadEvidenceSearch();
        }, 250);
    }

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.journal = function (row = {}) {
        const deletedBy = actorDisplay(row, 'deleted_by');
        return `
            <td>${escapeHtml(row.voucher_no ?? '')}</td>
            <td>${escapeHtml(row.voucher_date ?? '')}</td>
            <td>${escapeHtml(translateStatus(row.status ?? ''))}</td>
            <td>${escapeHtml(formatAmountValue(row.voucher_amount ?? row.debit_total ?? row.payment_total ?? 0) || '0')}</td>
            <td>${escapeHtml(row.summary_text ?? '')}</td>
            <td>${escapeHtml(row.deleted_at ?? '')}</td>
            <td>${escapeHtml(deletedBy)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id ?? '')}">복구</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id ?? '')}">영구삭제</button>
            </td>
        `;
    };

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, options);
        const text = await response.text();
        let json = {};

        try {
            json = text ? JSON.parse(text) : {};
        } catch (error) {
            return {
                success: false,
                message: '서버 응답을 해석하지 못했습니다.',
            };
        }

        if (!response.ok) {
            return {
                ...json,
                success: false,
                message: json.message || '요청 처리에 실패했습니다.',
            };
        }

        return json;
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
            const isFinal = (key === 'posted' && step.value === 'posted')
                || (key === 'closed' && step.value === 'closed');
            const currentClass = shouldPulse ? 'current current-step' : 'current';
            const stateClass = isFinal
                ? `done final ${step.value}-final`
                : (index < currentIndex ? 'done' : (index === currentIndex ? currentClass : 'pending'));
            const check = index < currentIndex || isFinal ? '<span class="voucher-flow-check">✓</span>' : '';

            return `
                <span class="voucher-flow-step voucher-flow-step-${step.value} ${stateClass}">
                    ${check}<span class="voucher-flow-label">${escapeHtml(step.label)}</span>
                </span>
            `;
        }).join('<span class="voucher-flow-arrow">→</span>');

        return `<div class="voucher-flow${modeClass}">${steps}</div>`;
    }

    function getJournalStatusDisplay(row = {}) {
        const status = String(row.status || 'draft').toLowerCase();
        const rejectReason = String(row.reject_reason || '').trim();

        if (status === 'draft' && rejectReason !== '') {
            return {
                key: 'rejected',
                label: '반려',
                tooltip: rejectReason,
            };
        }

        if (status === 'draft') {
            return { key: 'draft', label: '임시저장', tooltip: '' };
        }

        if (status === 'confirmed') {
            return { key: 'confirmed', label: '검토요청', tooltip: '' };
        }

        if (status === 'reviewed') {
            return { key: 'reviewed', label: '검토완료', tooltip: '' };
        }

        if (status === 'posted') {
            return { key: 'posted', label: '승인완료', tooltip: '' };
        }

        if (status === 'closed') {
            return { key: 'closed', label: '마감', tooltip: '' };
        }

        return { key: status || 'draft', label: translateStatus(status), tooltip: '' };
    }

    function renderJournalStatusBadge(row = {}) {
        const state = getJournalStatusDisplay(row);
        const tooltip = state.tooltip
            ? ` title="${escapeHtml(state.tooltip)}"`
            : '';

        return `<span class="journal-status-badge journal-status-${escapeHtml(state.key)}"${tooltip}>${escapeHtml(state.label)}</span>`;
    }

    function renderModalStatusTimeline(status, meta = {}) {
        const key = String(status || 'draft').toLowerCase();
        const currentIndex = Math.max(STATUS_STEPS.findIndex((step) => step.value === key), 0);
        const dateText = String(meta.updated_at || meta.created_at || '').slice(0, 16).replace('T', ' ');
        const actorText = actorDisplay(meta, meta.updated_at ? 'updated_by' : 'created_by');

        const steps = STATUS_STEPS.map((step, index) => {
            const isFinal = (key === 'posted' && step.value === 'posted')
                || (key === 'closed' && step.value === 'closed');
            const stateClass = isFinal
                ? `done final ${step.value}-final`
                : (index < currentIndex ? 'done' : (index === currentIndex ? 'current' : 'pending'));
            const nodeClass = index === currentIndex && !isFinal
                ? 'voucher-timeline-node current-step'
                : 'voucher-timeline-node';
            const icon = index < currentIndex || isFinal
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
                    <div class="voucher-timeline-meta">${detail || '<span>아직 없음</span>'}</div>
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

    function setRejectReason(status = 'draft', reason = '') {
        const shouldShow = String(status || '').toLowerCase() === 'draft'
            && String(reason || '').trim() !== '';

        rejectPanelEl?.classList.toggle('d-none', !shouldShow);
        if (rejectReasonEl) {
            rejectReasonEl.textContent = shouldShow ? String(reason).trim() : '';
        }
    }

    function translateType(value) {
        const key = String(value || '').toUpperCase();
        return TYPE_LABELS[key] || value || '-';
    }

    function refTypeAliases(value) {
        const key = String(value || '').toUpperCase();
        return {
            CLIENT: ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'],
            CUSTOMER: ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'],
            VENDOR: ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'],
            COUNTERPARTY: ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'],
            ACCOUNT: ['ACCOUNT', 'BANK', 'BANK_ACCOUNT'],
            BANK: ['ACCOUNT', 'BANK', 'BANK_ACCOUNT'],
            BANK_ACCOUNT: ['ACCOUNT', 'BANK', 'BANK_ACCOUNT'],
            CARD: ['CARD'],
            PROJECT: ['PROJECT'],
            EMPLOYEE: ['EMPLOYEE'],
        }[key] || [key];
    }

    function translateSourceType(value) {
        const key = String(value || '').toUpperCase();
        return SOURCE_TYPE_LABELS[key] || value || '-';
    }

    function importTypeLabel(value) {
        const labels = {
            TAX_INVOICE: '세금계산서',
            CASH_RECEIPT: '현금영수증',
            CARD_APPROVAL: '카드',
            BANK_TRANSACTION: '입출금',
            SHOPPING_ORDER: '발주서',
            IMPORT_INVOICE: '수입신고',
        };
        const key = String(value || '').toUpperCase();
        return labels[key] || value || '-';
    }

    function importSourceLabel(value) {
        const key = String(value || '').toUpperCase();
        if (['TAX_INVOICE', 'CASH_RECEIPT'].includes(key)) return '세금';
        if (key === 'CARD_APPROVAL') return '카드';
        if (key === 'BANK_TRANSACTION') return '은행';
        if (key === 'SHOPPING_ORDER') return '발주';
        if (key === 'IMPORT_INVOICE') return '수입/무역';
        return value ? translateSourceType(value) : '-';
    }

    function sourceTypeFromImportType(value, fallback = 'MANUAL') {
        const key = String(value || '').toUpperCase();
        const normalizedFallback = String(fallback || 'MANUAL').toUpperCase();
        if (['TAX_INVOICE', 'CASH_RECEIPT'].includes(key)) return 'TAX';
        if (key === 'CARD_APPROVAL') return 'CARD';
        if (key === 'BANK_TRANSACTION') return 'BANK';
        if (key === 'SHOPPING_ORDER') return 'SHOPPING';
        if (key === 'IMPORT_INVOICE') return 'TRADE';
        return normalizedFallback;
    }

    function normalizeSourceTypeForImport(value) {
        const source = String(value || '').toUpperCase();
        if (['HOMETAX', 'TAX'].includes(source)) return 'TAX';
        if (['CARD_COMPANY', 'CARD'].includes(source)) return 'CARD';
        if (['BANK', 'BANK_ACCOUNT'].includes(source)) return 'BANK';
        if (['SHOPPING', 'SHOPPING_MALL'].includes(source)) return 'SHOPPING';
        if (['TRADE', 'TRADE_IMPORT', 'IMPORT'].includes(source)) return 'TRADE';
        if (['MANUAL', 'SYSTEM'].includes(source)) return source;
        return source;
    }

    function parseImportTypeSource(row = {}) {
        const candidates = [
            row.source_type,
            row.parent_code,
            row.parent,
            row.parent_code_value,
            row.extra_json,
            row.extra,
            row.metadata,
            row.meta_json,
            row.memo,
            row.note,
        ];

        for (const candidate of candidates) {
            if (!candidate) continue;
            if (typeof candidate === 'object') {
                const value = candidate.source_type || candidate.source || candidate.parent_source_type;
                if (value) return normalizeSourceTypeForImport(value);
                continue;
            }

            const text = String(candidate).trim();
            if (!text) continue;
            if (text.startsWith('{')) {
                try {
                    const decoded = JSON.parse(text);
                    const value = decoded?.source_type || decoded?.source || decoded?.parent_source_type;
                    if (value) return normalizeSourceTypeForImport(value);
                } catch (error) {
                    // Ignore free-text notes.
                }
            }
        }

        return '';
    }

    function fallbackImportSourceType(importType) {
        const key = String(importType || '').toUpperCase();
        if (['TAX_INVOICE', 'CASH_RECEIPT'].includes(key)) return 'TAX';
        if (key === 'CARD_APPROVAL') return 'CARD';
        if (key === 'BANK_TRANSACTION') return 'BANK';
        if (key === 'SHOPPING_ORDER') return 'SHOPPING';
        if (key === 'IMPORT_INVOICE') return 'TRADE';
        return '';
    }

    function importTypeSourceType(row = {}) {
        return parseImportTypeSource(row) || fallbackImportSourceType(row.code || row.value || row.import_type);
    }

    function importTypeMatchesSource(rowOrCode, sourceType) {
        const key = typeof rowOrCode === 'string'
            ? String(rowOrCode || '').toUpperCase()
            : String(rowOrCode?.code || rowOrCode?.value || rowOrCode?.import_type || '').toUpperCase();
        const source = String(sourceType || '').toUpperCase();
        if (!key || !source) return true;
        if (key.startsWith('__')) return true;
        const normalizedSource = normalizeSourceTypeForImport(source);
        if (normalizedSource === 'TRANSACTION') {
            return true;
        }
        if (['MANUAL', 'SYSTEM'].includes(normalizedSource)) {
            return false;
        }
        const childSource = typeof rowOrCode === 'string'
            ? fallbackImportSourceType(key)
            : importTypeSourceType(rowOrCode);
        return childSource === normalizedSource;
    }

    function initSourceTypeSelect2() {
        if (!voucherSourceTypeEl || !window.jQuery?.fn?.select2) {
            return;
        }

        const $select = window.jQuery(voucherSourceTypeEl);
        if (!$select.hasClass('select2-hidden-accessible')) {
            $select.select2({
                dropdownParent: window.jQuery(modalEl),
                width: '100%',
                minimumResultsForSearch: Infinity,
            });
        }
    }

    function renderImportOrigin(row = {}) {
        const importType = String(row.import_type || '').trim();
        if (importType !== '') {
            return `${importSourceLabel(importType)} / ${importTypeLabel(importType)}`;
        }
        if (String(row.source_type || '').toUpperCase() === 'TRANSACTION') {
            return '-';
        }
        return translateSourceType(row.source_type);
    }

    function splitCsvValues(value = '') {
        return String(value || '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);
    }

    function sourceTypeBadgeLabel(sourceType = '', formatName = '', { preferFormat = true } = {}) {
        const formatLabel = String(formatName || '').trim();
        if (preferFormat && formatLabel !== '') {
            return formatLabel;
        }

        const type = String(sourceType || '').trim().toUpperCase();
        const label = importTypeLabel(type);
        const source = importSourceLabel(type);
        if (type === 'BANK_TRANSACTION') {
            return label;
        }
        if (source && source !== '-' && source !== label) {
            return `${label}(${source})`;
        }

        return label || '-';
    }

    function evidenceLinkLabels(row = {}) {
        const formatNames = splitCsvValues(row.evidence_format_names);
        const sourceTypes = splitCsvValues(row.evidence_source_types);
        const max = Math.max(formatNames.length, sourceTypes.length);
        const labels = [];

        for (let index = 0; index < max; index += 1) {
            labels.push(sourceTypeBadgeLabel(sourceTypes[index] || row.import_type || '', formatNames[index] || '', {
                preferFormat: false,
            }));
        }

        if (labels.length === 0 && row.import_type) {
            labels.push(sourceTypeBadgeLabel(row.import_type, '', { preferFormat: false }));
        }

        return Array.from(new Set(labels.filter((label) => label && label !== '-')));
    }

    function translateLinkedStatus(value) {
        return LINKED_STATUS_LABELS[value] || value || '미연결';
    }

    function renderJournalLinkedStatus(row = {}) {
        const hasTransaction = String(row.transaction_id || '').trim() !== ''
            || String(row.linked_status || '').toLowerCase() === 'linked';
        const matchStatus = String(row.match_status || '').toLowerCase();

        if (!hasTransaction) {
            return '<span class="journal-link-badge journal-link-unlinked">미연결</span>';
        }

        if (matchStatus === 'matched') {
            return '<span class="journal-link-badge journal-link-matched">대사완료</span>';
        }

        return '<span class="journal-link-badge journal-link-linked">연결</span>';
    }

    function renderEvidenceLinkedStatus(row = {}) {
        const linked = String(row.evidence_link_status || '').toLowerCase() === 'linked'
            || String(row.evidence_id || '').trim() !== '';
        if (!linked) {
            return '<span class="journal-link-badge journal-link-unlinked">미연결</span>';
        }

        const labels = evidenceLinkLabels(row);
        const label = labels.length > 0 ? `연결(${labels.join(',')})` : '연결';
        return `<span class="journal-link-badge journal-link-linked">${escapeHtml(label)}</span>`;
    }

    function normalizeEvidenceLinkBadgeLabel(label = '', sourceType = '') {
        const type = String(sourceType || '').trim().toUpperCase();
        const text = `${type} ${String(label || '').trim()}`.toUpperCase();

        if (type === 'BANK_TRANSACTION' || text.includes('BANK') || text.includes('\uC785\uCD9C\uAE08')) {
            return '\uC785\uCD9C\uAE08';
        }
        if (type.includes('TAX_INVOICE') || text.includes('TAX_INVOICE') || text.includes('\uC138\uAE08\uACC4\uC0B0\uC11C')) {
            return '\uC138\uAE08\uACC4\uC0B0\uC11C';
        }
        if (type.includes('CASH_RECEIPT') || text.includes('CASH_RECEIPT') || text.includes('\uD604\uAE08\uC601\uC218\uC99D')) {
            return '\uD604\uAE08\uC601\uC218\uC99D';
        }
        if (type.includes('CARD') || text.includes('CARD') || text.includes('\uCE74\uB4DC')) {
            return '\uCE74\uB4DC\uB9E4\uC785';
        }

        return String(label || '').trim() || '\uC99D\uBE59';
    }

    function evidenceLinkBadgeItems(row = {}) {
        const formatNames = splitCsvValues(row.evidence_format_names);
        const sourceTypes = splitCsvValues(row.evidence_source_types);
        const max = Math.max(formatNames.length, sourceTypes.length, 1);
        const labels = [];

        for (let index = 0; index < max; index += 1) {
            const sourceType = sourceTypes[index] || row.import_type || '';
            const rawLabel = sourceTypeBadgeLabel(sourceType, formatNames[index] || '', {
                preferFormat: false,
            });
            labels.push(normalizeEvidenceLinkBadgeLabel(rawLabel, sourceType));
        }

        return Array.from(new Set(labels.filter((label) => label && label !== '-')));
    }

    function renderJournalLinkedStatus(row = {}) {
        const hasTransaction = String(row.transaction_id || '').trim() !== ''
            || String(row.linked_status || '').toLowerCase() === 'linked';
        const matchStatus = String(row.match_status || '').toLowerCase();

        if (!hasTransaction) {
            return '<span class="journal-link-badge journal-link-unlinked">\uBBF8\uC5F0\uACB0</span>';
        }

        if (matchStatus === 'matched') {
            return '<span class="journal-link-badge journal-link-matched">\uB300\uC0AC\uC644\uB8CC</span>';
        }

        return '<span class="journal-link-badge journal-link-linked">\uC5F0\uACB0</span>';
    }

    function renderEvidenceLinkedStatus(row = {}) {
        const linked = String(row.evidence_link_status || '').toLowerCase() === 'linked'
            || String(row.evidence_id || '').trim() !== '';
        if (!linked) {
            return '<span class="journal-link-badge journal-link-unlinked">\uBBF8\uC5F0\uACB0</span>';
        }

        const labels = evidenceLinkBadgeItems(row);
        if (labels.length === 0) {
            return '<span class="journal-link-badge journal-link-linked">\uC5F0\uACB0</span>';
        }

        return `<span class="journal-evidence-link-badges">${labels.map((label) => `<span class="journal-link-badge journal-link-linked">${escapeHtml(label)}</span>`).join('')}</span>`;
    }

    function renderJournalStatusState(value) {
        const key = String(value || 'EMPTY').toUpperCase();
        const map = {
            EMPTY: ['분개없음', 'journal-status-empty'],
            UNBALANCED: ['차변/대변 불일치', 'journal-status-unbalanced'],
            READY: ['완료', 'journal-status-ready'],
            POSTED: ['승인완료', 'journal-status-posted'],
        };
        const [label, className] = map[key] || [value || '-', 'journal-status-empty'];
        return `<span class="journal-link-badge ${className}">${escapeHtml(label)}</span>`;
    }

    function isSystemMemo(value) {
        const text = String(value || '').trim();
        if (text === '' || !text.startsWith('{')) {
            return false;
        }

        try {
            const decoded = JSON.parse(text);
            return !!decoded
                && typeof decoded === 'object'
                && (
                    Object.prototype.hasOwnProperty.call(decoded, 'created_from_transaction')
                    || Object.prototype.hasOwnProperty.call(decoded, 'transaction_items')
                    || Object.prototype.hasOwnProperty.call(decoded, 'voucher_match')
                );
        } catch (error) {
            return false;
        }
    }

    function displayMemo(value) {
        return isSystemMemo(value) ? '' : String(value || '');
    }

    function buildSourceTransactionSummary(row = null, fallbackId = '') {
        if (!row) {
            return fallbackId ? `원본 거래 ${fallbackId}` : '';
        }

        const sortNo = row.sort_no ? `#${row.sort_no}` : (fallbackId || '-');
        const date = row.transaction_date || '-';
        const description = row.description || row.client_name || '';

        return ['원본 거래', sortNo, date, description].filter(Boolean).join(' ');
    }

    function buildLinkedTransactionSummary(row = null) {
        if (!row) {
            return '연결거래: 없음';
        }

        const sortNo = row.sort_no ? `TX-${row.sort_no}` : (row.transaction_no || '');
        const date = row.transaction_date || '';
        const description = row.description || row.client_name || '';

        return ['연결거래:', sortNo, date, description].filter(Boolean).join(' ');
    }

    function buildOriginalSourceSummary(voucher = {}) {
        const transaction = voucher.source_transaction || voucher.linked_transaction || null;
        const importType = String(voucher.import_type || '').trim();
        const seed = voucher.seed_source || transaction?.seed_source || null;

        if (seed) {
            const seedNo = seed.row_no || seed.id || '';
            return seedNo ? `원본자료: Seed #${seedNo}` : '원본자료: Seed';
        }

        if (false) {
            if (sourceType === 'TRANSACTION') {
                return buildSourceTransactionSummary(transaction || null, sourceId);
            }
            return `원본자료: ${sourceId}`;
        }

        if (transaction && importType !== '') {
            return `원본자료: Seed ${importTypeLabel(importType)}`;
        }

        return '원본자료: 없음';
    }

    function setSourceTransactionInfo(voucher = {}) {
        if (!voucherSourceTransactionInfoEl) {
            return;
        }

        const text = buildOriginalSourceSummary(voucher);

        voucherSourceTransactionInfoEl.textContent = text;
        voucherSourceTransactionInfoEl.classList.remove('d-none');
    }

    function evidenceFromVoucher(voucher = {}) {
        return voucher.linked_evidence
            || voucher.evidence_link
            || voucher.seed_source
            || null;
    }

    function evidencesFromVoucher(voucher = {}) {
        const rows = Array.isArray(voucher.linked_evidences) ? voucher.linked_evidences : [];
        if (rows.length > 0) {
            return rows;
        }

        const evidence = evidenceFromVoucher(voucher);
        return evidence ? [evidence] : [];
    }

    function buildEvidenceSummary(voucher = {}) {
        const evidences = evidencesFromVoucher(voucher);
        if (evidences.length === 0) {
            return '연결증빙: 없음';
        }

        const formatAmount = (value) => {
            const amount = Number(String(value ?? '').replace(/,/g, ''));
            return Number.isFinite(amount) && amount !== 0 ? `${formatNumber(amount)}원` : '';
        };
        const labels = Array.from(new Set(evidences.map((evidence) => sourceTypeBadgeLabel(
            evidence.source_type || voucher.import_type || '',
            evidence.format_name || '',
            { preferFormat: false }
        )).filter((label) => label && label !== '-')));
        const sourceKeys = evidences
            .map((evidence) => evidence.display_key || evidence.source_key || '')
            .filter(Boolean)
            .slice(0, 3);
        const date = evidences[0]?.evidence_date || evidences[0]?.processed_at || evidences[0]?.created_at || '';
        const client = evidences[0]?.client_name || evidences[0]?.counterparty_name || '';
        const summary = evidences[0]?.display_summary || evidences[0]?.description || '';
        const amount = formatAmount(evidences[0]?.display_amount ?? evidences[0]?.total_amount ?? '');
        const bundleLabel = labels.length > 1 ? '묶음증빙:' : '연결증빙';

        return [
            bundleLabel,
            labels.length > 0 ? labels.join(', ') : '증빙',
            sourceKeys.length > 0 ? `#${sourceKeys.join(', #')}` : '',
            date,
            client,
            amount,
            summary,
        ].filter(Boolean).join(' ');
    }

    function setLinkedEvidence(voucher = {}) {
        if (!linkedEvidenceIdEl || !linkedEvidenceSummaryEl) {
            return;
        }

        const evidence = evidenceFromVoucher(voucher);
        const evidenceId = evidence?.id || voucher.evidence_id || '';
        linkedEvidenceIdEl.value = evidenceId;
        linkedEvidenceSummaryEl.textContent = buildEvidenceSummary(voucher);
        linkedEvidenceSummaryEl.title = evidenceId ? buildEvidenceSummary(voucher) : '';
        if (clearEvidenceLinkBtn) {
            const status = String(voucherStatusEl?.value || 'draft').toLowerCase();
            clearEvidenceLinkBtn.disabled = evidenceId === '' || !['draft', 'posted'].includes(status);
        }

        if (linkedEvidenceOriginEl) {
            const evidences = evidencesFromVoucher(voucher);
            const originText = Array.from(new Set(evidences.map((item) => sourceTypeBadgeLabel(
                item.source_type || voucher.import_type || '',
                item.format_name || ''
            )).filter(Boolean))).join(', ');
            linkedEvidenceOriginEl.textContent = originText;
            linkedEvidenceOriginEl.classList.toggle('d-none', originText === '');
        }
    }

    function buildTransactionSummary(row = null) {
        return buildLinkedTransactionSummary(row);
    }

    function setLinkedTransaction(row = null) {
        if (!linkedTransactionIdEl || !linkedTransactionSummaryEl) {
            return;
        }

        linkedTransactionIdEl.value = row?.id || '';
        if (voucherTransactionIdEl) {
            voucherTransactionIdEl.value = row?.id || '';
        }
        linkedTransactionSummaryEl.textContent = buildTransactionSummary(row);
        linkedTransactionSummaryEl.title = row
            ? `${buildTransactionSummary(row)}${row.item_summary ? ` / ${row.item_summary}` : ''}`
            : '';

        if (row?.id) {
            const importType = row.import_type || '';
            setSourceTransactionInfo({
                import_type: importType,
                source_transaction: row,
                linked_transaction: row,
                seed_source: row.seed_source || null,
            });
        } else {
            setSourceTransactionInfo({});
        }
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

    function voucherEditLockMessage(status = voucherStatusEl?.value || 'draft') {
        const normalizedStatus = String(status || 'draft').toLowerCase();
        if (normalizedStatus === 'confirmed') {
            return '\uAC80\uD1A0\uC694\uCCAD\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4. \uAC80\uD1A0\uC694\uCCAD \uCDE8\uC18C \uD6C4\uC5D0\uB9CC \uC218\uC815\uD560 \uC218 \uC788\uC2B5\uB2C8\uB2E4.';
        }
        if (normalizedStatus === 'reviewed') {
            return '\uAC80\uD1A0\uC644\uB8CC\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
        }
        if (normalizedStatus === 'posted') {
            return '\uC2B9\uC778\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
        }
        if (normalizedStatus === 'closed') {
            return '\uB9C8\uAC10\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
        }
        if (normalizedStatus === 'deleted') {
            return '\uC0AD\uC81C\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
        }
        return '';
    }

    function setModalEditability(status = 'draft') {
        const normalizedStatus = String(status || 'draft').toLowerCase();
        const isDraft = normalizedStatus === 'draft';
        const isConfirmed = normalizedStatus === 'confirmed';
        const canEditHeader = isDraft;
        const canEditLines = isDraft;
        const canLinkTransaction = isDraft;
        const canClearTransaction = isDraft;
        const canLinkEvidence = canLinkTransaction;
        const canClearEvidence = canClearTransaction;

        voucherDateEl.disabled = !canEditHeader;
        document.getElementById('voucher_summary_text').disabled = !canEditHeader;
        document.getElementById('voucher_note').disabled = !canEditHeader;
        document.getElementById('voucher_memo').disabled = !canEditHeader;
        if (!canEditHeader) {
            closeSummaryAutocomplete();
        }
        voucherSourceTypeEl.disabled = !canLinkTransaction;
        if (voucherSourceTypeEl && window.jQuery?.fn?.select2) {
            window.jQuery(voucherSourceTypeEl).trigger('change.select2');
        }
        if (voucherImportTypeEl) {
            voucherImportTypeEl.disabled = !canLinkTransaction;
            if (window.jQuery?.fn?.select2) {
                window.jQuery(voucherImportTypeEl).trigger('change.select2');
            }
            void refreshImportTypeOptions(voucherImportTypeEl.value || '', { autoSelectSingle: false });
        }

        addLineBtn.disabled = !canEditLines;
        addPaymentBtn.disabled = !canEditLines;
        modalSaveBtn.disabled = false;
        modalSaveBtn.dataset.lockMessage = isDraft ? '' : voucherEditLockMessage(normalizedStatus);
        if (modalRequestReviewBtn) {
            modalRequestReviewBtn.disabled = !isDraft;
            modalRequestReviewBtn.classList.toggle('d-none', !isDraft);
        }
        if (modalCancelReviewBtn) {
            modalCancelReviewBtn.disabled = !isConfirmed;
            modalCancelReviewBtn.classList.toggle('d-none', !isConfirmed);
        }
        modalDeleteBtn.disabled = !isDraft;

        [lineBody, paymentBody].forEach((body) => {
            body?.querySelectorAll('input, select, textarea, button').forEach((el) => {
                el.disabled = !canEditLines;
            });
        });

        selectTransactionBtn.disabled = !canLinkTransaction;
        clearTransactionLinkBtn.disabled = !canClearTransaction;
        if (selectEvidenceBtn) {
            selectEvidenceBtn.disabled = !canLinkEvidence;
        }
        if (clearEvidenceLinkBtn) {
            clearEvidenceLinkBtn.disabled = !canClearEvidence || !(linkedEvidenceIdEl?.value || '');
        }

        if (isConfirmed) {
            setValidationBadge('ok', '검토요청된 전표는 내용을 수정할 수 없습니다.');
        }

        if (!isDraft) {
            const lockMessage = voucherEditLockMessage(normalizedStatus);
            if (lockMessage) {
                setValidationBadge('error', lockMessage);
            }
        }

        if (transactionNoticeEl) {
            const message = isDraft ? '' : voucherEditLockMessage(normalizedStatus);
            /*
                ? '거래 연결은 가능하지만 합계에는 영향이 없습니다.'
                : (isClosed ? '마감 상태입니다. 거래 연결은 가능하지만 연결 해제는 할 수 없습니다.' : '');
            */
            transactionNoticeEl.textContent = message;
            transactionNoticeEl.classList.toggle('d-none', message === '');
        }
    }

    function renderPickerOption(data) {
        const span = document.createElement('span');
        span.textContent = data?.text || '';

        return span;
    }

    function renderPickerSelection(data) {
        if (!data || !data.id) {
            return '계정과목 선택';
        }

        return data.text || '-';
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
        $select.append(new Option(text || '-', normalizedValue, true, true));
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
            .filter((row) => (
                Number(row.is_active ?? 1) === 1
                && String(row.is_postable ?? (Number(row.is_posting ?? 1) === 1 ? 'Y' : 'N')).toUpperCase() === 'Y'
            ))
            .map((row) => {
                const accountId = String(row.id ?? row.account_id ?? row.value ?? '').trim();
                const accountCode = String(row.account_code ?? '').trim();
                const accountName = String(row.account_name ?? row.name ?? '').trim();
                const accountPath = String(row.full_path ?? '').trim();

                return {
                    id: accountId,
                    text: accountPath ? `[${accountPath}]` : (accountCode && accountName ? `${accountCode} - ${accountName}` : accountCode),
                    account_code: accountCode,
                    account_name: accountName,
                    full_path: accountPath,
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
            { id: '', text: '계정과목을 선택' },
            ...mappedRows,
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
        return (
            resolveDisplayText({
                display_name: line.account_text || line.account_label || '',
                account_name: line.account_name || '',
                bank_account_name: line.bank_account_name || '',
                card_name: line.card_name || '',
                project_name: line.project_name || '',
                client_name: line.client_name || '',
                name: line.name || '',
                text: line.account_code || '',
                code_name: line.code_name || '',
            })
            || [line.account_code, line.account_name].filter(Boolean).join(' - ')
            || '-'
        );
    }

    const REF_PICKER_CONFIG = {
        CLIENT: {
            url: API.clientList,
            placeholder: '거래처를 선택',
            label(row) {
                return resolveDisplayText(row)
                    || row.client_name || row.business_name || row.name || row.company_name || '-';
            },
        },
        PROJECT: {
            url: API.projectList,
            placeholder: '프로젝트 선택',
            label(row) {
                return resolveDisplayText(row)
                    || row.project_name || row.name || row.project_code || '-';
            },
        },
        EMPLOYEE: {
            url: API.employeeList,
            placeholder: '직원 선택',
            label(row) {
                return resolveDisplayText(row)
                    || row.employee_name || row.name || row.user_name || '-';
            },
        },
        ACCOUNT: {
            url: API.bankAccountList,
            placeholder: '계좌 선택',
            label(row) {
                return resolveDisplayText(row)
                    || row.account_name || row.bank_name || row.account_no || row.account_number || '-';
            },
        },
        BANK_ACCOUNT: {
            url: API.bankAccountList,
            placeholder: '계좌 선택',
            label(row) {
                return resolveDisplayText(row)
                    || row.account_name || row.bank_name || row.account_no || row.account_number || '-';
            },
        },
        CARD: {
            url: API.cardList,
            placeholder: '카드 선택',
            label(row) {
                return resolveDisplayText(row)
                    || row.card_name || row.card_no || row.card_number || row.client_name || '-';
            },
        },
        TRANSACTION: {
            url: API.transactionSearch,
            placeholder: '거래를 선택',
            label(row) {
                return row.item_summary || row.description || row.transaction_date || '-';
            },
        },
        ORDER: {
            url: API.transactionSearch,
            placeholder: '주문을 선택',
            label(row) {
                return row.order_ref || row.document_no || row.item_summary || row.summary_text || '-';
            },
        },
        VOUCHER: {
            url: API.list,
            placeholder: '전표 선택',
            label(row) {
                return row.voucher_no || row.summary_text || '-';
            },
        },
        CONTRACT: {
            url: API.transactionSearch,
            placeholder: '계약을 선택',
            label(row) {
                return row.document_no || row.item_summary || row.description || '-';
            },
        },
        PAYMENT: {
            url: API.list,
            placeholder: '결제를 선택',
            label(row) {
                return row.voucher_no || row.summary_text || '-';
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
        AdminPicker.reloadSelect2(selectEl, items, 'id', 'text', selectedValue || '');
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
                setSelect2Option(selectEl, selectedValue, selectedText);
            }
        });
    }

    async function initLineAccountPicker(selectEl, selectedValue = '') {
        if (!selectEl || !window.jQuery) {
            return;
        }

        AdminPicker.select2(selectEl, {
            placeholder: '계정과목을 선택',
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
        return '<tr class="voucher-line-empty"><td colspan="7" class="text-center text-muted py-4">분개 라인을 추가해 주세요.</td></tr>';
    }

    function syncLineNumbers() {
        Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'))
            .forEach((row, index) => {
                const numberCell = row.querySelector('.line-no');
                if (numberCell) {
                    let displayNo = numberCell.querySelector('.journal-line-display-no');
                    if (!displayNo) {
                        numberCell.innerHTML = `
                            <span class="journal-line-order-cell">
                                <button type="button"
                                        class="journal-line-drag-handle"
                                        aria-label="순서 이동"
                                        title="순서 이동">
                                    <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                                </button>
                                <span class="journal-line-display-no"></span>
                            </span>
                        `;
                        displayNo = numberCell.querySelector('.journal-line-display-no');
                    }
                    displayNo.textContent = String(index + 1);
                }
            });
    }

    function initJournalLineReorder() {
        if (!lineBody || lineBody.dataset.reorderReady === '1') {
            return;
        }

        lineBody.dataset.reorderReady = '1';
        let draggingRow = null;

        const getLineRow = (target) => target?.closest?.('tr:not(.voucher-line-empty)') || null;
        const getAfterElement = (container, y) => {
            const rows = Array.from(container.querySelectorAll('tr:not(.voucher-line-empty)'))
                .filter((row) => row !== draggingRow);

            return rows.reduce((closest, row) => {
                const box = row.getBoundingClientRect();
                const offset = y - box.top - (box.height / 2);
                if (offset < 0 && offset > closest.offset) {
                    return { offset, element: row };
                }
                return closest;
            }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
        };

        lineBody.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest('.journal-line-drag-handle');
            if (!handle) {
                return;
            }

            const row = getLineRow(handle);
            if (row) {
                row.draggable = true;
                row.dataset.dragHandleActive = '1';
            }
        });

        lineBody.addEventListener('pointerup', () => {
            if (draggingRow) {
                return;
            }
            lineBody.querySelectorAll('tr[data-drag-handle-active="1"]').forEach((row) => {
                row.draggable = false;
                delete row.dataset.dragHandleActive;
            });
        });

        lineBody.addEventListener('dragstart', (event) => {
            const row = getLineRow(event.target);
            if (!row || row.dataset.dragHandleActive !== '1') {
                event.preventDefault();
                return;
            }

            draggingRow = row;
            row.classList.add('journal-line-is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
        });

        lineBody.addEventListener('dragover', (event) => {
            if (!draggingRow) {
                return;
            }

            event.preventDefault();
            const afterElement = getAfterElement(lineBody, event.clientY);
            if (afterElement) {
                lineBody.insertBefore(draggingRow, afterElement);
            } else {
                lineBody.appendChild(draggingRow);
            }
        });

        const finishDrag = () => {
            if (!draggingRow) {
                return;
            }

            draggingRow.classList.remove('journal-line-is-dragging');
            draggingRow.draggable = false;
            delete draggingRow.dataset.dragHandleActive;
            draggingRow = null;
            syncLineNumbers();
            calculateTotals();
        };

        lineBody.addEventListener('drop', (event) => {
            if (draggingRow) {
                event.preventDefault();
            }
            finishDrag();
        });
        lineBody.addEventListener('dragend', finishDrag);
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
            setValidationBadge('error', '분개 라인을 먼저 입력해 주세요.');
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
                <span>${escapeHtml(translateType(policy.ref_type))}${policy.is_required ? ' <b class="journal-line-subaccount-required">*</b>' : ''}</span>
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
        const selectedMap = new Map();
        selectedRefs.forEach((ref) => {
            const refType = String(ref.ref_type || ref.line_ref_type || '').toUpperCase();
            const refId = String(ref.ref_id || ref.line_ref_id || '').trim();
            if (refType === '' || refId === '') {
                return;
            }
            refTypeAliases(refType).forEach((alias) => {
                if (alias && !selectedMap.has(alias)) {
                    selectedMap.set(alias, refId);
                }
            });
        });
        for (const selectEl of container.querySelectorAll('.line-ref-picker')) {
            const refType = selectEl.dataset.refType || '';
            const selectedValue = refTypeAliases(refType)
                .map((alias) => selectedMap.get(alias))
                .find((value) => String(value || '').trim() !== '')
                || '';
            await initRefPicker(selectEl, refType, selectedValue);
        }
        updateLineSubAccountColumnVisibility();
    }

    async function addLineRow(line = {}) {
        lineBody.querySelector('.voucher-line-empty')?.remove();

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-center line-no">
                <span class="journal-line-order-cell">
                    <button type="button"
                            class="journal-line-drag-handle"
                            aria-label="순서 이동"
                            title="순서 이동">
                        <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                    </button>
                    <span class="journal-line-display-no"></span>
                </span>
            </td>
            <td>
                <select class="form-select form-select-sm line-account-code-picker">
                    <option value="">계정과목을 선택</option>
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
        setJournalModalLoading(false);
        if (voucherNoDisplayEl) {
            voucherNoDisplayEl.value = '새 전표';
        }
        voucherDateEl.value = formatDate(new Date());
        voucherStatusEl.value = 'draft';
        setStatusFlow('draft');
        setRejectReason('draft', '');
        setVoucherSource('MANUAL', '');
        setLinkedTransaction(null);
        setSourceTransactionInfo({});
        setLinkedEvidence({});
        lineBody.innerHTML = emptyLineRow();
        if (paymentBody) {
            paymentBody.innerHTML = emptyPaymentRow();
        }
        setModalTitle('create');
        updateLineSubAccountColumnVisibility();
        setModalEditability('draft');
        calculateTotals();
    }

    function setJournalModalLoading(isLoading = false) {
        const loading = Boolean(isLoading);
        modalEl.classList.toggle('is-loading-detail', loading);
        modalEl.setAttribute('aria-busy', loading ? 'true' : 'false');
        if (!loading) {
            return;
        }
        [modalSaveBtn, modalRequestReviewBtn, modalDeleteBtn].forEach((button) => {
            if (button) {
                button.disabled = true;
            }
        });
    }

    function ensureSourceTypeOption(sourceType) {
        const normalizedType = String(sourceType || '').trim().toUpperCase();
        if (!voucherSourceTypeEl || normalizedType === '') {
            return;
        }

        if (!Array.from(voucherSourceTypeEl.options).some((option) => option.value === normalizedType)) {
            const option = document.createElement('option');
            option.value = normalizedType;
            option.textContent = translateSourceType(normalizedType);
            voucherSourceTypeEl.appendChild(option);
        }
    }

    async function loadImportTypeRows() {
        if (importTypeRows.length > 0) {
            return importTypeRows;
        }

        try {
            const rows = await fetchJson('/api/settings/system/code/list?code_group=IMPORT_TYPE');
            importTypeRows = Array.isArray(rows) ? rows : (Array.isArray(rows?.data) ? rows.data : []);
        } catch (error) {
            console.error('[ledger-journal] import type options failed', error);
            importTypeRows = [];
        }

        return importTypeRows;
    }

    function rebuildImportTypeOptions(selectedValue = '', { autoSelectSingle = true } = {}) {
        if (!voucherImportTypeEl) {
            return;
        }

        const sourceType = String(voucherSourceTypeEl?.value || 'MANUAL').toUpperCase();
        const rows = importTypeRows.filter((row) => importTypeMatchesSource(row, sourceType));
        const sourceLocked = Boolean(voucherSourceTypeEl?.disabled);

        voucherImportTypeEl.innerHTML = '';
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = voucherImportTypeEl.dataset.emptyLabel || '선택';
        voucherImportTypeEl.appendChild(emptyOption);

        rows.forEach((row) => {
            const option = document.createElement('option');
            option.value = row.code || row.value || '';
            option.textContent = row.code_name || row.name || row.label || row.code || '';
            voucherImportTypeEl.appendChild(option);
        });

        const normalizedSelected = String(selectedValue || '').toUpperCase();
        if (normalizedSelected && rows.some((row) => String(row.code || '').toUpperCase() === normalizedSelected)) {
            voucherImportTypeEl.value = normalizedSelected;
        } else if (autoSelectSingle && rows.length === 1) {
            voucherImportTypeEl.value = rows[0].code || '';
        } else {
            voucherImportTypeEl.value = '';
        }

        voucherImportTypeEl.disabled = sourceLocked || rows.length === 0;

        if (window.jQuery?.fn?.select2) {
            const $select = window.jQuery(voucherImportTypeEl);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2({
                dropdownParent: window.jQuery(modalEl),
                width: '100%',
            });
            $select.trigger('change.select2');
        }
    }

    async function refreshImportTypeOptions(selectedValue = '', options = {}) {
        await loadImportTypeRows();
        rebuildImportTypeOptions(selectedValue, options);
    }

    function setVoucherImportType(importType = '') {
        const normalizedImportType = String(importType || '').trim().toUpperCase();
        if (!voucherImportTypeEl) {
            return;
        }

        if (normalizedImportType && !importTypeRows.some((row) => String(row.code || '').toUpperCase() === normalizedImportType)) {
            const option = document.createElement('option');
            option.value = normalizedImportType;
            option.textContent = importTypeLabel(normalizedImportType);
            voucherImportTypeEl.appendChild(option);
        }

        void refreshImportTypeOptions(normalizedImportType, { autoSelectSingle: false });
    }

    function setVoucherSource(sourceType = 'MANUAL', sourceId = '', importType = '') {
        const normalizedImportType = String(importType || '').trim().toUpperCase();
        const requestedType = String(sourceType || 'MANUAL').toUpperCase();
        const normalizedType = requestedType === 'TRANSACTION'
            ? 'TRANSACTION'
            : (sourceTypeFromImportType(normalizedImportType, requestedType) || 'MANUAL');
        const normalizedId = String(sourceId || '').trim();

        if (voucherSourceTypeEl) {
            ensureSourceTypeOption(normalizedType);
            voucherSourceTypeEl.value = normalizedType;
            if (window.jQuery?.fn?.select2) {
                window.jQuery(voucherSourceTypeEl).val(normalizedType).trigger('change.select2');
            }
        }
        if (voucherSourceIdEl) {
            voucherSourceIdEl.value = normalizedId;
        }
        setVoucherImportType(normalizedImportType);
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
        return '<tr class="voucher-payment-empty"><td colspan="6" class="text-center text-muted py-3">결제수단은 필요할 경우 추가해 주세요.</td></tr>';
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

    function validateBeforeSave({ requireJournalReady = false } = {}) {
        const lines = collectLines();

        if (requireJournalReady && lines.length === 0) {
            notify('warning', '분개 라인을 1개 이상 입력해 주세요.');
            return false;
        }

        let debitTotal = 0;
        let creditTotal = 0;

        for (let index = 0; index < lines.length; index += 1) {
            const line = lines[index];
            const debit = Number(line.debit || '0');
            const credit = Number(line.credit || '0');

            if (!line.account_id) {
                notify('warning', `${index + 1}번째 라인의 계정과목을 선택해 주세요.`);
                return false;
            }

            const row = Array.from(lineBody.querySelectorAll('tr'))
                .filter((tr) => !tr.classList.contains('voucher-line-empty'))[index];
            const refPickers = Array.from(row?.querySelectorAll('.line-ref-picker') || []);
            const requiredPickers = refPickers.filter((selectEl) => selectEl.dataset.required === '1');
            const selectedPickers = refPickers.filter((selectEl) => String(selectEl.value || '').trim());
            if (false && refPickers.length > 0 && selectedPickers.length === 0) {
                notify('warning', `${index + 1}번째 라인의 보조계정을 선택해 주세요.`);
                refPickers[0]?.focus();
                return false;
            }
            if (false && requiredPickers.length > 1) {
                notify('warning', `${index + 1}번째 라인에는 필수 보조계정이 여러 개 있습니다. 현재 전표라인 DB 구조상 보조계정은 1개만 저장할 수 있습니다.`);
                requiredPickers[0]?.focus();
                return false;
            }
            for (const requiredPicker of requiredPickers) {
                if (!String(requiredPicker.value || '').trim()) {
                    notify('warning', `${index + 1}번째 라인의 필수 보조계정을 선택해 주세요.`);
                    requiredPicker.focus();
                    return false;
                }
            }

            if (false && selectedPickers.length > 1) {
                notify('warning', `${index + 1}번째 라인에는 보조계정을 1개만 선택할 수 있습니다.`);
                selectedPickers[1]?.focus();
                return false;
            }

            if (debit <= 0 && credit <= 0) {
                notify('warning', `${index + 1}번째 라인의 차변 또는 대변 금액을 입력해 주세요.`);
                return false;
            }

            if (debit > 0 && credit > 0) {
                notify('warning', `${index + 1}번째 라인은 차변과 대변 중 하나만 입력할 수 있습니다.`);
                return false;
            }

            debitTotal += Number.isFinite(debit) ? debit : 0;
            creditTotal += Number.isFinite(credit) ? credit : 0;
        }

        if (requireJournalReady && debitTotal !== creditTotal) {
            notify('warning', '차변 합계와 대변 합계가 일치해야 합니다.');
            return false;
        }

        const payments = collectPayments();
        for (let index = 0; index < payments.length; index += 1) {
            const payment = payments[index];
            if (!payment.payment_type) {
                notify('warning', `${index + 1}번째 결제유형을 선택해 주세요.`);
                return false;
            }
            if (!payment.payment_id) {
                notify('warning', `${index + 1}번째 결제수단을 선택해 주세요.`);
                return false;
            }
            if (Number(payment.amount || 0) <= 0) {
                notify('warning', `${index + 1}번째 결제금액을 입력해 주세요.`);
                return false;
            }
        }

        return true;
    }

    function getVoucherSortNo(row = {}) {
        const numericSortNo = Number(String(row.sort_no ?? '').replace(/,/g, ''));
        return Number.isFinite(numericSortNo) ? numericSortNo : 0;
    }

    function renderDragHandle() {
        return '<i class="bi bi-list"></i>';
    }

    function renderVoucherNumberButton(row = {}) {
        const voucherNo = String(row.voucher_no || '').trim();
        const id = String(row.id || '').trim();
        const label = voucherNo || '-';

        return `<button type="button" class="btn btn-link btn-sm p-0 journal-voucher-no-btn" data-journal-action="change-number" data-id="${escapeHtml(id)}" title="\uC804\uD45C\uBC88\uD638 \uBCC0\uACBD">${escapeHtml(label)}</button>`;
    }

    function renderEllipsisText(value = '') {
        const text = String(value ?? '').trim();
        if (!text) {
            return '';
        }

        return `<span class="journal-ellipsis" title="${escapeHtml(text)}">${escapeHtml(text)}</span>`;
    }

    function renderSourceText(value = '') {
        const text = String(value ?? '').trim();
        if (!text) {
            return '';
        }

        return `<span class="journal-source-text" title="${escapeHtml(text)}">${escapeHtml(text)}</span>`;
    }

    function sourceLabel(row = {}) {
        const formatNames = splitCsvValues(row.evidence_format_names);
        if (formatNames.length > 0) {
            return formatNames.join(', ');
        }

        const importType = String(row.import_type || row.source_type || '').trim().toUpperCase();
        const origin = String(renderImportOrigin(row) || '').trim();

        if (['BANK', 'BANK_TRANSACTION', 'ACCOUNT_TRANSACTION'].includes(importType)) {
            return '입출금';
        }
        if (['TAX', 'TAX_INVOICE', 'HOMETAX', 'ETAX'].includes(importType)) {
            return '세금계산서';
        }
        if (['CARD', 'CORP_CARD', 'CREDIT_CARD'].includes(importType)) {
            return '카드매입';
        }
        if (importType === 'MANUAL' || (!importType && (!origin || origin === '-'))) {
            return '수기입력';
        }

        return origin || importType || '수기입력';
    }

    function renderAmountCell(value = 0, extraClass = '') {
        const amount = Number(value || 0);
        const classes = ['journal-money'];
        if (extraClass) {
            classes.push(extraClass);
        }
        if (amount < 0) {
            classes.push('journal-money-negative');
        }

        return `<span class="${classes.join(' ')}">${escapeHtml(formatAmountValue(amount) || '0')}</span>`;
    }

    function renderDiffCell(row = {}) {
        const diff = Number(row.debit_total || 0) - Number(row.credit_total || 0);
        return renderAmountCell(diff, diff === 0 ? 'journal-diff-zero' : 'journal-diff-nonzero');
    }

    function renderManageActions(row = {}) {
        const id = escapeHtml(row?.id ?? '');
        return `
            <button type="button"
                    class="btn btn-outline-primary btn-sm seed-row-edit-btn journal-edit-row-btn"
                    data-id="${id}"
                    data-journal-action="edit">
                수정
            </button>
        `;
    }

    function buildJournalColumns() {
        return [
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
                className: 'journal-voucher-no-cell',
                render(data, type, row) {
                    if (type === 'sort' || type === 'type') {
                        return String(data || '');
                    }

                    return renderVoucherNumberButton(row);
                },
            },
            {
                data: 'voucher_date',
                className: 'journal-voucher-date-cell',
                title: '전표일자',
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'status',
                className: 'text-center journal-voucher-status-cell',
                title: '전표상태',
                defaultContent: '',
                render(data, type, row) {
                    if (type === 'sort' || type === 'type') {
                        return String(data || 'draft').toLowerCase();
                    }

                    return renderJournalStatusBadge(row);
                },
            },
            {
                data: 'transaction_id',
                title: '거래 연결',
                className: 'text-center journal-transaction-link-cell',
                defaultContent: '',
                render(data, type, row) {
                    if (type === 'sort' || type === 'type') {
                        return String(row.match_status || row.linked_status || data || 'unlinked');
                    }

                    return renderJournalLinkedStatus(row);
                },
            },
            {
                data: 'evidence_link_status',
                title: '증빙 연결',
                className: 'journal-evidence-link-cell',
                defaultContent: '',
                render(data, type, row) {
                    if (type === 'sort' || type === 'type') {
                        return String(data || row.evidence_id || 'unlinked');
                    }

                    return renderEvidenceLinkedStatus(row);
                },
            },
            {
                data: null,
                className: 'journal-source-cell',
                title: '원본 구분',
                defaultContent: '',
                render(_data, type, row) {
                    const label = sourceLabel(row);
                    if (type === 'sort' || type === 'type') {
                        return label;
                    }

                    return renderSourceText(label);
                },
            },
            {
                data: 'journal_status',
                title: '분개상태',
                className: 'text-center journal-balance-status-cell',
                defaultContent: 'EMPTY',
                render(data, type) {
                    if (type === 'sort' || type === 'type') {
                        return String(data || 'EMPTY').toUpperCase();
                    }
                    return renderJournalStatusState(data);
                },
            },
            {
                data: 'source_id',
                title: '원본자료ID',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'debit_total',
                title: '금액(차변합계)',
                className: 'text-end journal-amount-cell',
                defaultContent: 0,
                render(data, type) {
                    if (type === 'sort' || type === 'type') {
                        return Number(data || 0);
                    }

                    return renderAmountCell(data || 0);
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
                data: null,
                title: '차액',
                className: 'text-end journal-diff-cell',
                defaultContent: 0,
                render(_data, type, row) {
                    const diff = Number(row.debit_total || 0) - Number(row.credit_total || 0);
                    if (type === 'sort' || type === 'type') {
                        return diff;
                    }
                    return renderDiffCell(row);
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
                title: '전표적요',
                className: 'journal-summary-cell',
                defaultContent: '',
                render(data) {
                    return renderEllipsisText(data || '');
                },
            },
            {
                data: 'note',
                title: '비고',
                className: 'journal-note-cell',
                visible: false,
                defaultContent: '',
                render(data, _type, row) {
                    return renderEllipsisText(data || displayMemo(row.memo) || '');
                },
            },
            {
                data: null,
                title: '관리',
                className: 'text-center journal-manage-cell no-export',
                headerClassName: 'text-center journal-manage-cell no-export',
                orderable: false,
                searchable: false,
                defaultContent: '',
                render(_data, _type, row) {
                    return renderManageActions(row);
                },
            },
            {
                data: 'memo',
                title: '메모',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(displayMemo(data));
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
                data: 'created_by_name',
                title: '생성자',
                visible: false,
                defaultContent: '',
                render(_data, _type, row) {
                    return escapeHtml(actorDisplay(row, 'created_by'));
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
                data: 'updated_by_name',
                title: '수정자',
                visible: false,
                defaultContent: '',
                render(_data, _type, row) {
                    return escapeHtml(actorDisplay(row, 'updated_by'));
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
                data: 'deleted_by_name',
                title: '삭제자',
                visible: false,
                defaultContent: '',
                render(_data, _type, row) {
                    return escapeHtml(actorDisplay(row, 'deleted_by'));
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

    function takeJournalColumn(columns, predicate) {
        const index = columns.findIndex(predicate);
        if (index < 0) {
            return null;
        }

        return columns.splice(index, 1)[0];
    }

    function orderJournalColumns(columns = []) {
        const rest = [...columns];
        const ordered = [
            takeJournalColumn(rest, (column) => String(column.className || '').includes('reorder-handle')),
            takeJournalColumn(rest, (column) => column.data === 'sort_no'),
            takeJournalColumn(rest, (column) => column.data === 'status'),
            takeJournalColumn(rest, (column) => column.data === 'journal_status'),
            takeJournalColumn(rest, (column) => column.data === 'voucher_no'),
            takeJournalColumn(rest, (column) => column.data === 'voucher_date'),
            takeJournalColumn(rest, (column) => column.data === 'summary_text'),
            takeJournalColumn(rest, (column) => column.data === 'debit_total'),
            takeJournalColumn(rest, (column) => column.data === null
                && column.defaultContent === 0
                && String(column.className || '').includes('text-end')),
            takeJournalColumn(rest, (column) => column.data === null
                && column.defaultContent === ''
                && !String(column.className || '').includes('journal-manage-cell')),
            takeJournalColumn(rest, (column) => column.data === 'evidence_link_status'),
            takeJournalColumn(rest, (column) => column.data === 'transaction_id'),
            takeJournalColumn(rest, (column) => String(column.className || '').includes('journal-manage-cell')),
        ].filter(Boolean);

        return [...ordered, ...rest];
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
                if (event.target.closest('button, .dropdown-menu, .journal-manage-menu, .reorder-handle')) {
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
            .off('click.journalAction')
            .on('click.journalAction', '[data-journal-action]', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const button = this;
                const action = String(button.dataset.journalAction || '').trim();
                const row = findJournalRowData(button);
                if (!row) {
                    notify('error', '전표 행 정보를 확인할 수 없습니다.');
                    return;
                }

                void handleJournalAction(action, row, button);
            });

        $('#journal-table tbody')
            .off('dblclick.journalEdit')
            .on('dblclick.journalEdit', 'tr', function (event) {
                if (event.target.closest('button, .dropdown-menu, .journal-manage-menu, .reorder-handle')) {
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
        if (journalTable || !journalTableEl || !tableBody || !window.jQuery?.fn?.DataTable) {
            return Boolean(journalTable);
        }

        journalTable = createDataTable({
            tableSelector: '#journal-table',
            api: API.list,
            columns: orderJournalColumns(buildJournalColumns()),
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '엑셀 다운로드',
                    className: 'btn btn-outline-success btn-sm',
                    title: '전표 목록',
                    filename: '전표 목록',
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
                    text: '신규전표',
                    className: 'btn btn-warning btn-sm',
                    action: function () {
                        void openCreateModal();
                    },
                },
            ],
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            deleteApi: API.remove,
        });

        bindRowReorder(journalTable, {
            api: API.reorder,
            onSuccess() {
                notify('success', '전표 순번이 저장되었습니다.');
                journalTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '전표 순번 저장에 실패했습니다.');
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
        modal?.show();
        await addLineRow();
        await addLineRow();
    }

    async function loadDetail(id) {
        setModalTitle('edit');
        setJournalModalLoading(true);
        modal?.show();

        try {
            const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);

            if (!json.success || !json.data) {
                notify('error', json.message || '전표 상세 정보를 불러오지 못했습니다.');
                modal?.hide();
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
            setRejectReason(data.status || 'draft', data.reject_reason || '');
            if (voucherTransactionIdEl) {
                voucherTransactionIdEl.value = data.transaction_id || '';
            }
            document.getElementById('voucher_summary_text').value = data.summary_text || '';
            document.getElementById('voucher_note').value = data.note || '';
            document.getElementById('voucher_memo').value = displayMemo(data.memo);
            setSourceTransactionInfo(data);
            setLinkedTransaction(data.linked_transaction || data.source_transaction || null);
            setLinkedEvidence(data);

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
        } catch (error) {
            console.error('[ledger-journal] loadDetail failed', error);
            notify('error', '전표 상세 정보를 불러오지 못했습니다.');
            modal?.hide();
        } finally {
            setJournalModalLoading(false);
        }
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
        formData.delete('ref_type');
        formData.delete('ref_id');
        formData.set('lines', JSON.stringify(collectLines()));
        formData.set('payments', JSON.stringify(collectPayments()));
        formData.set('linked_transaction_id', linkedTransactionIdEl?.value || '');
        formData.set('linked_evidence_id', linkedEvidenceIdEl?.value || '');
        formData.set('transaction_id', voucherTransactionIdEl?.value || linkedTransactionIdEl?.value || '');

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

            const json = await fetchJson(API.confirm, {
                method: 'POST',
                body: formData,
            });

            if (!json.success) {
                notify('error', json.message || '검토요청 처리에 실패했습니다.');
                return;
            }

            notify('success', '검토요청이 완료되었습니다.');
            modal?.hide();
            reloadJournalTable();
        } catch (error) {
            console.error('[ledger-journal] request review failed', error);
            notify('error', error.message || '검토요청 처리에 실패했습니다.');
        }
    }

    async function saveTransactionLinkOnly() {
        const voucherId = form.querySelector('[name="id"]')?.value || '';
        if (!voucherId) {
            notify('error', '거래를 연결할 전표 ID를 확인할 수 없습니다.');
            return null;
        }

        const formData = new FormData();
        formData.append('id', voucherId);
        formData.append('linked_transaction_id', linkedTransactionIdEl?.value || '');
        formData.append('transaction_id', voucherTransactionIdEl?.value || linkedTransactionIdEl?.value || '');

        const json = await fetchJson(API.linkTransaction, {
            method: 'POST',
            body: formData,
        });

        if (!json.success) {
            notify('error', json.message || '거래 연결 저장에 실패했습니다.');
            return null;
        }

        notify('success', '거래 연결이 저장되었습니다.');
        reloadJournalTable();
        return json.data || null;
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
        formData.append('linked_transaction_id', linkedTransactionIdEl?.value || '');

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

    function confirmUnlink(type) {
        const voucherNo = voucherNoEl?.value || form.querySelector('[name="voucher_no"]')?.value || '현재 전표';
        if (type === 'transaction') {
            const summary = linkedTransactionSummaryEl?.textContent?.trim() || '연결된 거래';
            return window.confirm(
                `${voucherNo}에 연결된 거래를 해제합니다.\n\n현재 연결: ${summary}\n\n해제하면 이 전표는 거래 미연결 상태가 되며, 필요 시 다시 거래를 선택해야 합니다. 계속 해제하시겠습니까?`
            );
        }

        const summary = linkedEvidenceSummaryEl?.textContent?.trim() || '연결된 증빙';
        return window.confirm(
            `${voucherNo}에 연결된 증빙을 해제합니다.\n\n현재 연결: ${summary}\n\n해제하면 이 전표는 증빙 미연결 상태가 되며, 필요 시 다시 증빙을 선택해야 합니다. 계속 해제하시겠습니까?`
        );
    }

    async function clearEvidenceLinkOnly() {
        const voucherId = form.querySelector('[name="id"]')?.value || '';
        const evidenceId = linkedEvidenceIdEl?.value || '';
        if (evidenceId && !confirmUnlink('evidence')) {
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

        const json = await fetchJson(API.cancelReview, {
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
        if (!journalTable || !element) {
            return null;
        }

        const tr = element.closest('tr');
        const row = tr ? journalTable.row(tr).data() : null;
        if (row) {
            return row;
        }

        const id = String(element.dataset.id || '').trim();
        if (!id) {
            return null;
        }

        return journalTable
            .rows()
            .data()
            .toArray()
            .find((item) => String(item.id || '') === id) || null;
    }
    async function changeVoucherNumber(row = {}) {
        const id = String(row.id || '').trim();
        if (!id) {
            notify('error', '전표 ID를 확인할 수 없습니다.');
            return;
        }

        const status = String(row.status || '').toLowerCase();
        if (VOUCHER_NUMBER_LOCKED_STATUSES.has(status)) {
            notify('warning', '승인 또는 마감 상태의 전표번호는 변경할 수 없습니다.');
            return;
        }

        const currentNo = String(row.voucher_no || '').trim();
        const nextNo = window.prompt('변경할 전표번호를 입력해 주세요. 예: 20260522-0001', currentNo);
        if (nextNo === null) {
            return;
        }

        const normalizedNo = String(nextNo || '').trim();
        if (!VOUCHER_NUMBER_PATTERN.test(normalizedNo)) {
            notify('warning', '전표번호는 YYYYMMDD-XXXX 형식으로 입력해 주세요.');
            return;
        }

        if (normalizedNo === currentNo) {
            notify('info', '변경할 전표번호가 없습니다.');
            return;
        }

        const json = await fetchJson(API.changeNumber, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                id,
                voucher_no: normalizedNo,
            }),
        });

        if (!json.success) {
            notify('error', json.message || '전표번호 변경에 실패했습니다.');
            return;
        }

        notify('success', json.message || '전표번호가 변경되었습니다.');
        reloadJournalTable();
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

        if (action === 'change-number') {
            await changeVoucherNumber(row);
            return;
        }

        if (action === 'copy') {
            notify('info', '전표 복사는 전표 상세 화면에서 저장 후 새 행으로 연결되도록 별도 구현이 필요합니다.');
            return;
        }

        if (action === 'evidence') {
            const evidenceId = String(button?.dataset.evidenceId || row.evidence_id || row.source_id || '').trim();
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
    function renderTransactionSearchRows(rows = []) {
        if (!transactionSearchBody) {
            return;
        }

        if (!rows.length) {
            transactionSearchBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">선택할 거래가 없습니다.</td>
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

        transactionSearchBody.innerHTML = rows.map((row, index) => `
            <tr data-index="${index}"${linkedRowClass(row)}>
                <td>${escapeHtml(row.transaction_date || '')}</td>
                <td>${escapeHtml(row.display_type || '-')}</td>
                <td>${escapeHtml(row.client_name || row.project_name || '-')}</td>
                <td class="text-end">${escapeHtml(formatAmountValue(row.display_amount ?? row.total_amount ?? 0) || '0')}</td>
                <td>
                    <div class="journal-search-main">${escapeHtml(row.display_summary || row.item_summary || row.description || '-')}</div>
                    ${linkedBadge(row)}
                </td>
                <td class="text-center">
                    <button type="button"
                            class="btn btn-outline-primary btn-sm btn-pick-transaction">${row.is_linked_to_current_voucher ? '선택됨' : '선택'}</button>
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
                <td colspan="6" class="text-center text-muted py-4">거래 목록을 불러오는 중입니다.</td>
            </tr>
        `;

        try {
            const query = new URLSearchParams();
            const keyword = transactionSearchKeywordEl?.value?.trim() || '';
            if (keyword) {
                query.set('q', keyword);
            }
            const voucherId = form.querySelector('[name="id"]')?.value || '';
            if (voucherId) {
                query.set('voucher_id', voucherId);
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
                    <td colspan="6" class="text-center text-danger py-4">거래 목록을 불러오지 못했습니다.</td>
                </tr>
            `;
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

            evidenceRows = Array.isArray(json.data) ? json.data : [];
            renderEvidenceSearchRows(evidenceRows);
        } catch (error) {
            console.error('[ledger-journal] evidence search failed', error);
            evidenceRows = [];
            evidenceRows = [];
            evidenceSearchBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger py-4">증빙 목록을 불러오지 못했습니다.</td>
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

        voucherSourceTypeEl?.addEventListener('change', () => {
            void refreshImportTypeOptions('', { autoSelectSingle: true });
        });

        voucherImportTypeEl?.addEventListener('change', () => {
            const importType = voucherImportTypeEl.value || '';
            if (String(importType).startsWith('__')) {
                return;
            }
            if (importType && voucherSourceTypeEl && String(voucherSourceTypeEl.value || '').toUpperCase() !== 'TRANSACTION') {
                const sourceType = sourceTypeFromImportType(importType, voucherSourceTypeEl.value || 'MANUAL');
                ensureSourceTypeOption(sourceType);
                voucherSourceTypeEl.value = sourceType;
                if (window.jQuery?.fn?.select2) {
                    window.jQuery(voucherSourceTypeEl).val(sourceType).trigger('change.select2');
                }
                void refreshImportTypeOptions(importType, { autoSelectSingle: false });
            }
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

            const currentStatus = String(voucherStatusEl?.value || 'draft').toLowerCase();
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
        modalDeleteBtn?.addEventListener('click', () => {
            const id = document.getElementById('journal_id')?.value || '';
            if (id && window.confirm('전표를 삭제하시겠습니까?')) {
                void deleteVoucher(id);
            }
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
            if ((linkedTransactionIdEl?.value || '') && !confirmUnlink('transaction')) {
                return;
            }
            setLinkedTransaction(null);
            if (form.querySelector('[name="id"]')?.value) {
                void saveTransactionLinkOnly();
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

        searchTransactionBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            void loadTransactionSearch();
        });

        searchEvidenceBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            void loadEvidenceSearch();
        });

        transactionSearchKeywordEl?.addEventListener('input', () => {
            queueTransactionSearch();
        });

        transactionSearchKeywordEl?.addEventListener('search', () => {
            queueTransactionSearch();
        });

        evidenceSearchKeywordEl?.addEventListener('input', () => {
            queueEvidenceSearch();
        });

        evidenceSearchKeywordEl?.addEventListener('search', () => {
            queueEvidenceSearch();
        });

        transactionSearchKeywordEl?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                void loadTransactionSearch();
            }
        });

        evidenceSearchKeywordEl?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                void loadEvidenceSearch();
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
            if (row.is_linked_to_other_voucher) {
                const voucherNo = row.linked_voucher?.voucher_no || '다른 전표';
                const ok = window.confirm(
                    `이 거래는 이미 ${voucherNo}에 연결되어 있습니다.\n\n현재 전표로 연결을 변경하려면 기존 연결 상태를 먼저 확인하는 것이 좋습니다. 그래도 선택하시겠습니까?`
                );
                if (!ok) return;
            }
            setLinkedTransaction(row);
            transactionModal?.hide();
            if (form.querySelector('[name="id"]')?.value) {
                void saveTransactionLinkOnly();
            }
        });

        evidenceSearchBody?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-pick-evidence');
            if (!button) {
                return;
            }

            const rowEl = button.closest('tr');
            const index = Number(rowEl?.dataset.index ?? -1);
            const row = evidenceRows[index];
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
        initSourceTypeSelect2();
        await loadImportTypeRows();
        rebuildImportTypeOptions('', { autoSelectSingle: false });
        basicInfoBridge.bindDateInputs(modalEl);
        onCodeOptionsLoaded(() => {
            importTypeRows = [];
            void refreshImportTypeOptions(voucherImportTypeEl?.value || '', { autoSelectSingle: false });
            journalTable?.rows().invalidate('data').draw(false);
        });
        initJournalTable();
        initJournalLineReorder();
        bindEvents();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
