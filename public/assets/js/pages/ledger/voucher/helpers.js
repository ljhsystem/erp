import {
    STATUS_LABELS as COMMON_STATUS_LABELS,
    normalizeStatus,
    statusBadge as commonStatusBadge,
} from '/public/assets/js/pages/ledger/shared/display-labels.js';

export function registerHelpers(ctx) {
    const state = ctx.state;
    const {
        actorDisplay,
        formatNumber,
        onlyNumber,
        API,
        STATUS_LABELS,
        STATUS_STEPS,
        SOURCE_TYPE_LABELS,
        TYPE_LABELS,
        modalEl,
        addLineBtn,
        lineGridHostEl,
        voucherStatusEl,
        voucherStatusBadgeEl,
        rejectPanelEl,
        rejectReasonEl,
        voucherDateEl,
        summaryTextEl,
        summarySuggestionsEl,
        modalTitleEl,
        modalDeleteBtn,
        modalSaveBtn,
        modalRequestReviewBtn,
        modalCancelReviewBtn,
        selectEvidenceBtn,
        clearSelectedEvidenceBtn,
        recommendEvidenceBtn,
        modal,
        escapeHtml,
        notify,
    } = ctx;

function queueEvidenceSearch() {
    window.clearTimeout(state.evidenceSearchTimer);
    state.evidenceSearchTimer = window.setTimeout(() => {
        void ctx.loadEvidenceSearch();
    }, 250);
}

window.TrashColumns = window.TrashColumns || {};
window.TrashColumns.journal = function (row = {}) {
    const deletedBy = actorDisplay(row, 'deleted_by');
    return `
        <td>${escapeHtml(row.voucher_no ?? '')}</td>
        <td>${escapeHtml(row.voucher_date ?? '')}</td>
        <td>${commonStatusBadge(row.status)}</td>
        <td>${escapeHtml(formatAmountValue(row.debit_total ?? 0) || '0')}</td>
        <td>${escapeHtml(row.summary ?? '')}</td>
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
            message: '응답을 확인할 수 없습니다. 처리 중 오류가 발생했습니다.',
        };
    }

    if (!response.ok) {
        return {
            ...json,
            success: false,
            message: json.message || '\uC694\uCCAD \uCC98\uB9AC \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.',
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
    state.summaryAutocompleteItems = [];
    state.summaryAutocompleteActiveIndex = -1;
    if (summarySuggestionsEl) {
        summarySuggestionsEl.innerHTML = '';
        summarySuggestionsEl.classList.add('d-none');
    }
}

function setSummaryAutocompleteActive(index) {
    if (!summarySuggestionsEl || state.summaryAutocompleteItems.length === 0) {
        return;
    }

    const maxIndex = state.summaryAutocompleteItems.length - 1;
    state.summaryAutocompleteActiveIndex = index < 0 ? maxIndex : (index > maxIndex ? 0 : index);

    summarySuggestionsEl.querySelectorAll('.summary-autocomplete-item').forEach((item, itemIndex) => {
        item.classList.toggle('active', itemIndex === state.summaryAutocompleteActiveIndex);
    });
}

function applySummaryAutocompleteItem(index) {
    const item = state.summaryAutocompleteItems[index];
    if (!item || !summaryTextEl) {
        return;
    }

    summaryTextEl.value = item.summary || '';
    closeSummaryAutocomplete();
}

function renderSummaryAutocomplete(items = []) {
    if (!summarySuggestionsEl || !summaryTextEl || summaryTextEl.disabled || summaryTextEl.readOnly) {
        closeSummaryAutocomplete();
        return;
    }

    state.summaryAutocompleteItems = items.filter((item) => String(item.summary || '').trim() !== '');
    state.summaryAutocompleteActiveIndex = -1;

    if (state.summaryAutocompleteItems.length === 0) {
        closeSummaryAutocomplete();
        return;
    }

    summarySuggestionsEl.innerHTML = state.summaryAutocompleteItems.map((item, index) => `
        <button type="button"
                class="summary-autocomplete-item"
                role="option"
                data-index="${index}"
                title="${escapeHtml(item.summary || '')}">
            ${escapeHtml(item.summary || '')}
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

    if (state.summaryAutocompleteAbort) {
        state.summaryAutocompleteAbort.abort();
    }

    state.summaryAutocompleteAbort = new AbortController();

    try {
        const json = await fetchJson(`${API.summarySearch}?q=${encodeURIComponent(normalizedKeyword)}`, {
            signal: state.summaryAutocompleteAbort.signal,
        });

        if (!json.success) {
            notify('error', json.message || '\uC801\uC694 \uC790\uB3D9\uC644\uC131 \uBAA9\uB85D\uC744 \uBD88\uB7EC\uC624\uC9C0 \uBABB\uD588\uC2B5\uB2C8\uB2E4.');
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
        notify('error', '\uC801\uC694 \uC790\uB3D9\uC644\uC131 \uBAA9\uB85D\uC744 \uBD88\uB7EC\uC624\uC9C0 \uBABB\uD588\uC2B5\uB2C8\uB2E4.');
        closeSummaryAutocomplete();
    }
}

function queueSummaryAutocompleteSearch() {
    if (state.summaryAutocompleteTimer) {
        clearTimeout(state.summaryAutocompleteTimer);
    }

    state.summaryAutocompleteTimer = setTimeout(() => {
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
    const normalized = normalizeVoucherStatus(value);
    return COMMON_STATUS_LABELS[normalized] || STATUS_LABELS[normalized] || normalized || '-';
}

function normalizeVoucherStatus(value, fallback = 'DRAFT') {
    const normalized = normalizeStatus(value, fallback);
    return COMMON_STATUS_LABELS[normalized] || STATUS_LABELS[normalized] ? normalized : fallback;
}

function statusClassKey(value) {
    const normalized = normalizeVoucherStatus(value);

    return {
        DRAFT: 'draft',
        REVIEW_REQUESTED: 'review-requested',
        REVIEWED: 'reviewed',
        POSTED: 'posted',
        CLOSED: 'closed',
        DELETED: 'deleted',
    }[normalized] || 'draft';
}

function renderStatusFlow(status, mode = '', meta = {}) {
    if (mode === 'modal') {
        return renderModalStatusTimeline(status, meta);
    }
    if (mode === 'table') {
        return escapeHtml(translateStatus(status));
    }

    const normalizedStatus = normalizeVoucherStatus(status);
    const currentIndex = Math.max(STATUS_STEPS.findIndex((step) => step.value === normalizedStatus), 0);
    const modeClass = mode ? ` voucher-flow-${mode}` : '';
    const shouldPulse = mode !== 'table';
    const steps = STATUS_STEPS.map((step, index) => {
        const stepKey = String(step.classKey || '').trim() || 'draft';
        const isFinal = (normalizedStatus === 'POSTED' && step.value === 'POSTED')
            || (normalizedStatus === 'CLOSED' && step.value === 'CLOSED');
        const currentClass = shouldPulse ? 'current current-step' : 'current';
        const stateClass = isFinal
            ? `done final ${stepKey}-final`
            : (index < currentIndex ? 'done' : (index === currentIndex ? currentClass : 'pending'));
        const check = index < currentIndex || isFinal ? '<span class="voucher-flow-check">??/span>' : '';

        return `
            <span class="voucher-flow-step voucher-flow-step-${stepKey} ${stateClass}">
                ${check}<span class="voucher-flow-label">${escapeHtml(step.label)}</span>
            </span>
        `;
    }).join('<span class="voucher-flow-arrow">??/span>');

    return `<div class="voucher-flow${modeClass}">${steps}</div>`;
}
function getJournalStatusDisplay(row = {}) {
    const status = normalizeVoucherStatus(row.status);
    const rejected = hasRejectedWorkflowResult(row);

    if (rejected) {
        return {
            key: 'rejected',
            label: '반려',
            tooltip: String(row.reject_reason || '').trim(),
        };
    }

    switch (status) {
        case 'DRAFT':
            return {
                key: 'draft',
                label: '작성중',
                tooltip: '',
            };

        case 'REVIEW_REQUESTED':
            return {
                key: 'review-requested',
                label: '확정',
                tooltip: '',
            };

        case 'REVIEWED':
            return {
                key: 'reviewed',
                label: '검토완료',
                tooltip: '',
            };

        case 'POSTED':
            return {
                key: 'posted',
                label: '전표반영',
                tooltip: '',
            };

        case 'CLOSED':
            return {
                key: 'closed',
                label: '마감',
                tooltip: '',
            };

        case 'DELETED':
            return {
                key: 'deleted',
                label: '삭제',
                tooltip: '',
            };

        default:
            return {
                key: statusClassKey(status),
                label: translateStatus(status),
                tooltip: '',
            };
    }
}
function hasRejectedWorkflowResult(row = {}) {
    return String(row.reject_reason || '').trim() !== '';
}

function renderJournalStatusBadge(row = {}) {
    const state = getJournalStatusDisplay(row);
    return commonStatusBadge(state.key === 'rejected' ? 'REJECTED' : row.status);
}
function renderModalStatusTimeline(status, meta = {}) {
    const normalizedStatus = normalizeVoucherStatus(status);
    const currentIndex = Math.max(
        STATUS_STEPS.findIndex((step) => step.value === normalizedStatus),
        0
    );

    const dateText = String(meta.updated_at || meta.created_at || '')
        .slice(0, 16)
        .replace('T', ' ');

    const actorText = actorDisplay(
        meta,
        meta.updated_at ? 'updated_by' : 'created_by'
    );

    const rejectReason = String(meta.reject_reason || '').trim();

    const rejectMessage =
        hasRejectedWorkflowResult(meta) && rejectReason !== ''
            ? `
                <div class="voucher-timeline-reject-reason">
                    <span class="voucher-timeline-reject-label">반려 사유</span>
                    <p class="voucher-timeline-reject-text">${escapeHtml(rejectReason)}</p>
                </div>
            `
            : '';

    const steps = STATUS_STEPS.map((step, index) => {
        const isFinal =
            (normalizedStatus === 'POSTED' && step.value === 'POSTED') ||
            (normalizedStatus === 'CLOSED' && step.value === 'CLOSED');

        const stateClass = isFinal
            ? `done final ${step.value}-final`
            : (
                index < currentIndex
                    ? 'done'
                    : (index === currentIndex ? 'current' : 'pending')
            );

        const nodeClass =
            index === currentIndex && !isFinal
                ? 'voucher-timeline-node current-step'
                : 'voucher-timeline-node';

        const icon =
            index < currentIndex || isFinal
                ? '✓'
                : '';

        const detail =
            index <= currentIndex
                ? [
                    dateText ? `<span>${escapeHtml(dateText)}</span>` : '',
                    actorText ? `<span>${escapeHtml(actorText)}</span>` : '',
                ].filter(Boolean).join('')
                : '<span>대기 중</span>';

        return `
            <div class="voucher-timeline-step voucher-timeline-${step.value} ${stateClass}">
                <div class="${nodeClass}">${icon}</div>
                <div class="voucher-timeline-label">${escapeHtml(step.label)}</div>
                <div class="voucher-timeline-meta">
                    ${detail || '<span>처리 이력이 없습니다.</span>'}
                </div>
            </div>
        `;
    }).join('');

    return `
        <div class="voucher-timeline">
            ${steps}
        </div>
        ${rejectMessage}
    `;
}
function renderStatus(status = 'DRAFT', meta = {}) {
    if (!voucherStatusBadgeEl) {
        return;
    }

    voucherStatusBadgeEl.className = 'voucher-status-timeline';
    voucherStatusBadgeEl.innerHTML = renderStatusFlow(status, 'modal', meta);
}

function setStatusFlow(status = 'DRAFT', meta = {}) {
    renderStatus(status, meta);
}

function setRejectReason(status = 'DRAFT', reason = '') {
    void status;
    void reason;
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
        BANK_TRANSACTION: '입출금(은행)',
        TAX_INVOICE: '세금계산서매입매출(홈택스)',
        TAX_INVOICE_MANUAL: '세금계산서매입매출(수기)',
        CASH_RECEIPT: '현금영수증매입(홈택스)',
        CASH_SALES: '현금매출(쇼핑몰)',
        CASH_RECEIPT_SALES: '현금영수증매출(쇼핑몰)',
        CARD_HOMETAX: '카드매입(홈택스)',
        CARD_STATEMENT: '카드매입(카드사)',
        EMPLOYEE_EXPENSE_PERSONAL: '직원경비(개인)',
        PAYROLL_REPORT: '급여(신고)',
        BUSINESS_INCOME_REPORT: '사업소득(신고)',
        DAILY_WORK_REPORT: '일용직(신고)',
        CARD_SALES: '카드매출(쇼핑몰)',
    };

    const key = String(value || '').toUpperCase();
    return labels[key] || value || '-';
}

function importSourceLabel(value) {
    const key = String(value || '').toUpperCase();

    switch (key) {
        case 'TAX_INVOICE':
        case 'CASH_RECEIPT':
        case 'CARD_HOMETAX':
            return '홈택스';

        case 'CARD_STATEMENT':
            return '카드사';

        case 'BANK_TRANSACTION':
            return '은행';

        case 'CASH_SALES':
        case 'CASH_RECEIPT_SALES':
        case 'CARD_SALES':
            return '쇼핑몰';

        case 'EMPLOYEE_EXPENSE_PERSONAL':
        case 'PAYROLL_REPORT':
        case 'BUSINESS_INCOME_REPORT':
        case 'DAILY_WORK_REPORT':
        case 'TAX_INVOICE_MANUAL':
            return '수기입력';

        default:
            return value ? translateSourceType(value) : '-';
    }
}
function sourceTypeFromImportType(value, fallback = 'MANUAL') {
    const key = String(value || '').toUpperCase();
    const normalizedFallback = String(fallback || 'MANUAL').toUpperCase();

    switch (key) {
        case 'TAX_INVOICE':
        case 'CASH_RECEIPT':
        case 'CARD_HOMETAX':
            return 'HOMETAX';

        case 'CARD_STATEMENT':
            return 'CARD_COMPANY';

        case 'BANK_TRANSACTION':
            return 'BANK';

        case 'TAX_INVOICE_MANUAL':
        case 'CASH_SALES':
        case 'CASH_RECEIPT_SALES':
        case 'CARD_SALES':
        case 'EMPLOYEE_EXPENSE_PERSONAL':
        case 'PAYROLL_REPORT':
        case 'BUSINESS_INCOME_REPORT':
        case 'DAILY_WORK_REPORT':
            return 'MANUAL';

        default:
            return normalizedFallback;
    }
}
function normalizeSourceTypeForImport(value) {
    const source = String(value || '').toUpperCase();

    switch (source) {
        case 'HOMETAX':
        case 'TAX':
            return 'HOMETAX';

        case 'CARD_COMPANY':
        case 'CARD':
            return 'CARD_COMPANY';

        case 'BANK':
        case 'BANK_ACCOUNT':
            return 'BANK';

        case 'MANUAL':
        case 'SYSTEM':
            return 'MANUAL';

        default:
            return source;
    }
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

    switch (key) {
        case 'TAX_INVOICE':
        case 'CASH_RECEIPT':
        case 'CARD_HOMETAX':
            return 'HOMETAX';

        case 'CARD_STATEMENT':
            return 'CARD_COMPANY';

        case 'BANK_TRANSACTION':
            return 'BANK';

        case 'TAX_INVOICE_MANUAL':
        case 'CASH_SALES':
        case 'CASH_RECEIPT_SALES':
        case 'CARD_SALES':
        case 'EMPLOYEE_EXPENSE_PERSONAL':
        case 'PAYROLL_REPORT':
        case 'BUSINESS_INCOME_REPORT':
        case 'DAILY_WORK_REPORT':
            return 'MANUAL';

        default:
            return '';
    }
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

function initSourceTypeSelect2() {}

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
        READY: ['분개완료', 'journal-status-ready'],
        POSTED: ['전기완료', 'journal-status-posted'],
    };

    const [label, className] =
        map[key] || [value || '-', 'journal-status-empty'];

    return `<span class="journal-link-badge ${className}">${escapeHtml(label)}</span>`;
}

function evidenceFromVoucher(voucher = {}) {
    return evidencesFromVoucher(voucher)[0] || null;
}

function evidencesFromVoucher(voucher = {}) {
    return Array.isArray(voucher.linked_evidences) ? voucher.linked_evidences : [];
}
function buildEvidenceSummary(voucher = {}) {
    const evidences = evidencesFromVoucher(voucher);

    if (evidences.length === 0) {
        return '연결증빙: 없음';
    }

    const formatAmount = (value) => {
        const amount = Number(String(value ?? '').replace(/,/g, ''));
        return Number.isFinite(amount) && amount !== 0
            ? `${formatNumber(amount)}원`
            : '';
    };

    const labels = Array.from(
        new Set(
            evidences
                .map((evidence) =>
                    sourceTypeBadgeLabel(
                        evidence.source_type || voucher.import_type || '',
                        evidence.format_name || '',
                        { preferFormat: false }
                    )
                )
                .filter((label) => label && label !== '-')
        )
    );

    const sourceKeys = evidences
        .map((evidence) => evidence.display_key || evidence.source_key || '')
        .filter(Boolean)
        .slice(0, 3);

    const date =
        evidences[0]?.evidence_date ||
        evidences[0]?.processed_at ||
        evidences[0]?.created_at ||
        '';

    const client =
        evidences[0]?.client_name ||
        evidences[0]?.counterparty_name ||
        '';

    const summary =
        evidences[0]?.display_summary ||
        evidences[0]?.description ||
        '';

    const amount = formatAmount(
        evidences[0]?.display_amount ??
        evidences[0]?.total_amount ??
        ''
    );

    const bundleLabel =
        labels.length > 1
            ? '묶음증빙:'
            : '연결증빙';

    return [
        bundleLabel,
        labels.length > 0 ? labels.join(', ') : '자료유형',
        sourceKeys.length > 0 ? `#${sourceKeys.join(', #')}` : '',
        date,
        client,
        amount,
        summary,
    ]
        .filter(Boolean)
        .join(' ');
}

function setLinkedEvidence(voucher = {}) {
    const unique = new Map();
    evidencesFromVoucher(voucher).forEach((evidence) => {
        const importType = String(evidence.import_type || evidence.source_type || '').toUpperCase();
        const evidenceId = String(evidence.evidence_id || evidence.id || '');
        if (importType && evidenceId) unique.set(`${importType}:${evidenceId}`, {
            ...evidence,
            import_type: importType,
            evidence_id: evidenceId,
        });
    });
    state.linkedEvidences = Array.from(unique.values());

    ctx.renderLinkedEvidenceGrid?.();
    const editable = normalizeVoucherStatus(voucherStatusEl?.value || 'DRAFT') === 'DRAFT';
    if (selectEvidenceBtn) selectEvidenceBtn.disabled = !editable;
    if (clearSelectedEvidenceBtn) clearSelectedEvidenceBtn.disabled = !editable || state.linkedEvidences.length === 0;
    if (recommendEvidenceBtn) recommendEvidenceBtn.disabled = state.linkedEvidences.length === 0;
}

function setModalTitle(mode = 'create') {
    if (!modalTitleEl) {
        return;
    }

    const icon = '<i class="bi bi-journal-check me-2"></i>';

    modalTitleEl.innerHTML =
        mode === 'edit'
            ? `${icon}전표 수정`
            : `${icon}전표 등록`;

    modalDeleteBtn?.classList.toggle('d-none', mode !== 'edit');
}
function voucherEditLockMessage(status = voucherStatusEl?.value || 'DRAFT') {
    const normalizedStatus = normalizeVoucherStatus(status);
    if (normalizedStatus === 'REVIEW_REQUESTED') {
        return '\uAC80\uD1A0\uC694\uCCAD\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4. \uAC80\uD1A0\uC694\uCCAD \uCDE8\uC18C \uD6C4\uC5D0\uB9CC \uC218\uC815\uD560 \uC218 \uC788\uC2B5\uB2C8\uB2E4.';
    }
    if (normalizedStatus === 'REVIEWED') {
        return '\uAC80\uD1A0\uC644\uB8CC\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
    }
    if (normalizedStatus === 'POSTED') {
        return '\uC2B9\uC778\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
    }
    if (normalizedStatus === 'CLOSED') {
        return '\uB9C8\uAC10\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
    }
    if (normalizedStatus === 'DELETED') {
        return '\uC0AD\uC81C\uB41C \uC804\uD45C\uB294 \uC218\uC815\uD560 \uC218 \uC5C6\uC2B5\uB2C8\uB2E4.';
    }
    return '';
}

function getVoucherStatusPolicy(status = voucherStatusEl?.value || 'DRAFT') {
    const normalizedStatus = normalizeVoucherStatus(status);
    const isDraft = normalizedStatus === 'DRAFT';
    const isReviewRequested = normalizedStatus === 'REVIEW_REQUESTED';
    const readOnly = !isDraft;
    const lockMessage = readOnly ? voucherEditLockMessage(normalizedStatus) : '';

    return {
        status: normalizedStatus,
        isDraft,
        isReviewRequested,
        readOnly,
        lockMessage,
        canEditHeader: !readOnly,
        canEditLines: !readOnly,
        canLinkTransaction: !readOnly,
        canClearTransaction: !readOnly,
        canLinkEvidence: !readOnly,
        canClearEvidence: !readOnly,
        canRequestReview: isDraft,
        canCancelReview: isReviewRequested,
        canDelete: isDraft,
        validation: readOnly && lockMessage !== ''
            ? { type: 'error', message: lockMessage }
            : null,
    };
}

function renderVoucherValidation(summary = {}, status = voucherStatusEl?.value || 'DRAFT') {
    const policy = getVoucherStatusPolicy(status);
    const validation = policy.validation || summary.validation || {
        type: 'error',
        message: '\uCC28\uBCC0/\uB300\uBCC0 \uD569\uACC4\uB97C \uD655\uC778\uD574 \uC8FC\uC138\uC694.',
    };

    ctx.setValidationBadge(validation.type, validation.message);
    return validation;
}

function setModalEditability(status = 'DRAFT') {
    const policy = getVoucherStatusPolicy(status);
    const {
        status: normalizedStatus,
        canEditHeader,
        canEditLines,
        canLinkEvidence,
        canClearEvidence,
        canRequestReview,
        canCancelReview,
        canDelete,
        lockMessage,
    } = policy;

    voucherDateEl.disabled = !canEditHeader;
    document.getElementById('voucher_summary_text').disabled = !canEditHeader;
    if (!canEditHeader) {
        closeSummaryAutocomplete();
    }

    addLineBtn.disabled = !canEditLines;
    modalSaveBtn.disabled = false;
    modalSaveBtn.dataset.lockMessage = lockMessage;
    if (modalRequestReviewBtn) {
        modalRequestReviewBtn.disabled = !canRequestReview;
        modalRequestReviewBtn.classList.toggle('d-none', !canRequestReview);
    }
    if (modalCancelReviewBtn) {
        modalCancelReviewBtn.disabled = !canCancelReview;
        modalCancelReviewBtn.classList.toggle('d-none', !canCancelReview);
    }
    modalDeleteBtn.disabled = !canDelete;

    ctx.lineGridBridge?.setReadOnly?.(!canEditLines);

    if (selectEvidenceBtn) selectEvidenceBtn.disabled = !canLinkEvidence;
    if (clearSelectedEvidenceBtn) clearSelectedEvidenceBtn.disabled = !canClearEvidence || state.linkedEvidences.length === 0;
}

function applyVoucherState(status = 'DRAFT', meta = {}, options = {}) {
    const normalizedStatus = normalizeVoucherStatus(status);
    const summary = options.summary && typeof options.summary === 'object' ? options.summary : {};

    if (voucherStatusEl) {
        voucherStatusEl.value = normalizedStatus;
    }

    renderStatus(normalizedStatus, meta);
    setModalEditability(normalizedStatus);
    renderVoucherValidation(summary, normalizedStatus);

    return getVoucherStatusPolicy(normalizedStatus);
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
function isSystemMemo(value) {
    const text = String(value || '').trim();
    if (text === '' || !text.startsWith('{')) {
        return false;
    }

    try {
        const decoded = JSON.parse(text);
        return Boolean(
            decoded
            && typeof decoded === 'object'
            && (
                Object.prototype.hasOwnProperty.call(decoded, 'created_from_transaction')
                || Object.prototype.hasOwnProperty.call(decoded, 'transaction_items')
            )
        );
    } catch (error) {
        return false;
    }
}

function displayMemo(value) {
    return isSystemMemo(value) ? '' : String(value || '');
}
function renderPickerOption(data) {
    const span = document.createElement('span');
    span.textContent = data?.text || '';

    return span;
}
function renderPickerSelection(data) {
    if (!data || !data.id) {
        return data?.text || '';
    }

    return data.text || '-';
}
    return Object.assign(ctx, {
        queueEvidenceSearch,
        fetchJson,
        formatDate,
        formatDateInputValue,
        normalizeSummaryKeyword,
        closeSummaryAutocomplete,
        setSummaryAutocompleteActive,
        applySummaryAutocompleteItem,
        renderSummaryAutocomplete,
        searchSummaryAutocomplete,
        queueSummaryAutocompleteSearch,
        normalizeAmountValue,
        parseAmountValue,
        formatAmountValue,
        setAmountInputValue,
        getAmountInputTarget,
        translateStatus,
        renderStatusFlow,
        renderStatus,
        getJournalStatusDisplay,
        renderJournalStatusBadge,
        renderModalStatusTimeline,
        setStatusFlow,
        setRejectReason,
        translateType,
        refTypeAliases,
        translateSourceType,
        importTypeLabel,
        importSourceLabel,
        sourceTypeFromImportType,
        normalizeSourceTypeForImport,
        parseImportTypeSource,
        fallbackImportSourceType,
        importTypeSourceType,
        importTypeMatchesSource,
        initSourceTypeSelect2,
        renderImportOrigin,
        splitCsvValues,
        sourceTypeBadgeLabel,
        evidenceLinkLabels,
        renderEvidenceLinkedStatus,
        normalizeEvidenceLinkBadgeLabel,
        evidenceLinkBadgeItems,
        renderJournalStatusState,
        isSystemMemo,
        displayMemo,
        evidenceFromVoucher,
        evidencesFromVoucher,
        buildEvidenceSummary,
        setLinkedEvidence,
        setModalTitle,
        voucherEditLockMessage,
        getVoucherStatusPolicy,
        renderVoucherValidation,
        applyVoucherState,
        setModalEditability,
        renderPickerOption,
        renderPickerSelection
    });
}
