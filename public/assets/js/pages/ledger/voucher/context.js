import { createVoucherState } from './state.js';

export function createVoucherContext(deps = {}) {
    const ctx = {
        ...deps,
        state: createVoucherState(),
    };

    const journalTableEl = document.getElementById('journal-table');
    const tableBody = document.getElementById('journal-table-body') || journalTableEl?.querySelector('tbody');
    const form = document.getElementById('journal-edit-form');
    const modalEl = document.getElementById('journalModal');
    const addLineBtn = document.getElementById('btnAddVoucherLine');
    const legacyLineBody = document.getElementById('voucher-line-body');
    const lineGridHostEl = document.getElementById('voucher-line-grid-host')
        || legacyLineBody?.closest('.journal-lines-table-wrap')
        || document.getElementById('voucher-line-table')?.parentElement
        || null;
    const debitTotalEl = document.getElementById('voucher_debit_total');
    const creditTotalEl = document.getElementById('voucher_credit_total');
    const balanceStatusEl = document.getElementById('voucher_balance_status');
    const voucherStatusEl = document.getElementById('voucher_status');
    const voucherStatusBadgeEl = document.getElementById('voucher_status_badge');
    const rejectPanelEl = document.getElementById('journalRejectPanel');
    const rejectReasonEl = document.getElementById('journalRejectReason');
    const voucherNoDisplayEl = document.getElementById('voucher_no_display');
    const voucherDateEl = document.getElementById('voucher_date');
    const systemInfoFieldsEl = document.getElementById('voucher_system_info_fields');
    const summaryTextEl = document.getElementById('voucher_summary_text');
    const summarySuggestionsEl = document.getElementById('voucher_summary_suggestions');
    const modalTitleEl = document.getElementById('journalModalLabel');
    const modalDeleteBtn = document.getElementById('btnDeleteVoucherInModal');
    const modalSaveBtn = document.getElementById('btnSaveVoucher');
    const modalRequestReviewBtn = document.getElementById('btnRequestVoucherReview');
    const modalCancelReviewBtn = document.getElementById('btnCancelVoucherReview');
    const evidenceModalEl = document.getElementById('journalEvidenceSearchModal');
    const evidenceSearchBody = document.getElementById('journal_evidence_search_body');
    const evidenceSearchKeywordEl = document.getElementById('journal_evidence_search_keyword');
    const linkedEvidenceIdEl = document.getElementById('linked_evidence_id');
    const linkedEvidenceSummaryEl = document.getElementById('linked_evidence_summary');
    const linkedEvidenceOriginEl = document.getElementById('linked_evidence_origin');
    const selectEvidenceBtn = document.getElementById('btnSelectEvidence');
    const clearEvidenceLinkBtn = document.getElementById('btnClearEvidenceLink');
    const searchEvidenceBtn = document.getElementById('btnSearchEvidence');
    const pickerLayerEl = document.getElementById('journal-today-picker');

    if (!form || !modalEl || !lineGridHostEl || !voucherDateEl) {
        ctx.isReady = false;
        return ctx;
    }

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    if (evidenceModalEl && evidenceModalEl.parentElement !== document.body) {
        document.body.appendChild(evidenceModalEl);
    }
    if (pickerLayerEl && pickerLayerEl.parentElement !== document.body) {
        document.body.appendChild(pickerLayerEl);
    }

    const modal = window.bootstrap ? new bootstrap.Modal(modalEl, { focus: false }) : null;
    const evidenceModal = window.bootstrap && evidenceModalEl
        ? new bootstrap.Modal(evidenceModalEl, { focus: false })
        : null;
    let journalModalLayoutFrame = 0;

    function scheduleJournalModalLayoutUpdate() {
        if (!modal || typeof modal.handleUpdate !== 'function') {
            return;
        }
        if (journalModalLayoutFrame) {
            return;
        }

        journalModalLayoutFrame = window.requestAnimationFrame(() => {
            journalModalLayoutFrame = 0;
            modal.handleUpdate();
        });
    }

    function cancelJournalModalLayoutUpdate() {
        if (journalModalLayoutFrame) {
            window.cancelAnimationFrame(journalModalLayoutFrame);
            journalModalLayoutFrame = 0;
        }
    }

    modalEl.addEventListener('shown.bs.modal', () => {
        scheduleJournalModalLayoutUpdate();
    });
    modalEl.addEventListener('hidden.bs.modal', cancelJournalModalLayoutUpdate);

    const API = {
        list: '/api/ledger/voucher/list',
        detail: '/api/ledger/voucher/detail',
        save: '/api/ledger/voucher/save',
        linkEvidence: '/api/ledger/voucher/link-evidence',
        unlinkEvidence: '/api/ledger/voucher/unlink-evidence',
        summarySearch: '/api/ledger/voucher/summary-search',
        remove: '/api/ledger/voucher/delete',
        requestReview: '/api/ledger/voucher/request-review',
        cancelReviewRequest: '/api/ledger/voucher/cancel-review-request',
        transactionSearch: '/api/ledger/voucher/transaction-search',
        evidenceSearch: '/api/ledger/voucher/evidence-search',
        systemTableColumns: '/api/settings/system/data-table-columns',
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
        DRAFT: '\uC791\uC131\uC911',
        REVIEW_REQUESTED: '\uAC80\uD1A0\uC694\uCCAD',
        REVIEWED: '\uAC80\uD1A0\uC644\uB8CC',
        POSTED: '\uC804\uD45C\uC2B9\uC778',
        CLOSED: '\uB9C8\uAC10',
        DELETED: '\uC0AD\uC81C',
    };

    const STATUS_STEPS = [
        { value: 'DRAFT', classKey: 'draft', label: '\uC791\uC131\uC911' },
        { value: 'REVIEW_REQUESTED', classKey: 'review-requested', label: '\uAC80\uD1A0\uC694\uCCAD' },
        { value: 'REVIEWED', classKey: 'reviewed', label: '\uAC80\uD1A0\uC644\uB8CC' },
        { value: 'POSTED', classKey: 'posted', label: '\uC804\uD45C\uC2B9\uC778' },
        { value: 'CLOSED', classKey: 'closed', label: '\uB9C8\uAC10' },
    ];

    const SOURCE_TYPE_LABELS = {
        TAX: '\uC138\uAE08\uACC4\uC0B0\uC11C',
        CARD: '\uCE74\uB4DC',
        BANK: '\uACC4\uC88C',
        MANUAL: '\uC218\uAE30\uC785\uB825',
        TRANSACTION: '\uAC70\uB798',
        SYSTEM: '\uC2DC\uC2A4\uD15C',
    };

    const TYPE_LABELS = {
        TRANSACTION: '\uAC70\uB798',
        ORDER: '\uBC1C\uC8FC',
        VOUCHER: '\uC804\uD45C',
        CONTRACT: '\uACC4\uC57D',
        PAYMENT: '\uACB0\uC81C',
        CLIENT: '\uAC70\uB798\uCC98',
        PROJECT: '\uD504\uB85C\uC81D\uD2B8',
        EMPLOYEE: '\uC9C1\uC6D0',
        ACCOUNT: '\uACC4\uC815',
        BANK_ACCOUNT: '\uACC4\uC88C',
        CARD: '\uCE74\uB4DC',
    };

    const JOURNAL_DATE_OPTIONS = [
        { value: 'voucher_date', label: '\uC804\uD45C\uC77C\uC790' },
        { value: 'updated_at', label: '\uC218\uC815\uC77C\uC2DC' },
    ];

    Object.assign(ctx, {
        VOUCHER_PAGE_DESCRIPTION: '\uD68C\uACC4\uAD00\uB9AC > \uC804\uD45C\uAD00\uB9AC > \uC804\uD45C\uC785\uB825',
        journalTableEl,
        tableBody,
        form,
        modalEl,
        addLineBtn,
        lineGridHostEl,
        debitTotalEl,
        creditTotalEl,
        balanceStatusEl,
        voucherStatusEl,
        voucherStatusBadgeEl,
        rejectPanelEl,
        rejectReasonEl,
        voucherNoDisplayEl,
        voucherDateEl,
        systemInfoFieldsEl,
        summaryTextEl,
        summarySuggestionsEl,
        modalTitleEl,
        modalDeleteBtn,
        modalSaveBtn,
        modalRequestReviewBtn,
        modalCancelReviewBtn,
        evidenceModalEl,
        evidenceSearchBody,
        evidenceSearchKeywordEl,
        linkedEvidenceIdEl,
        linkedEvidenceSummaryEl,
        linkedEvidenceOriginEl,
        selectEvidenceBtn,
        clearEvidenceLinkBtn,
        searchEvidenceBtn,
        pickerLayerEl,
        API,
        STATUS_LABELS,
        STATUS_STEPS,
        SOURCE_TYPE_LABELS,
        TYPE_LABELS,
        JOURNAL_DATE_OPTIONS,
        modal,
        evidenceModal,
        scheduleJournalModalLayoutUpdate,
        isReady: true,
    });

    return ctx;
}
