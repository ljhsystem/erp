import {
    bindTableHighlight,
    createDataTable,
    refreshDataTableLayout,
} from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import {
    fetchDataTableMetaColumns,
    getCachedDataTableMetaColumns,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import { notify } from '/public/assets/js/common/notification.js';
import {
    normalizeStatus,
    statusBadge as commonStatusBadge,
} from '/public/assets/js/pages/ledger/shared/display-labels.js';

const VOUCHER_META_DOMAIN = 'voucher-header';
const VOUCHER_REVIEW_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.ledger.vouchers.review.voucher-review-table.v1';
const VOUCHER_REVIEW_TABLE_SETTINGS_PAGE_KEY = 'ledger.vouchers.review';
const VOUCHER_REVIEW_TABLE_SETTINGS_TABLE_KEY = 'voucher-review-table';
const VOUCHER_REVIEW_ORDERABLE_FIELDS = new Set([
    'sort_no',
    'voucher_no',
    'voucher_date',
    'status',
    'summary',
    'summary_text',
    'debit_total',
    'credit_total',
    'line_count',
    'summary_account_id',
    'summary_client_id',
    'summary_project_id',
    'summary_bank_account_id',
    'summary_card_id',
    'summary_employee_id',
    'summary_line_summary',
    'reject_reason',
    'is_reversal',
    'reversal_of',
    'created_at',
    'created_by',
    'updated_at',
    'updated_by',
]);

(async () => {
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
        list: '/api/ledger/voucher/review-list',
        detail: '/api/ledger/voucher/detail',
        completeReview: '/api/ledger/voucher/complete-review',
        cancelCompleteReview: '/api/ledger/voucher/cancel-complete-review',
        post: '/api/ledger/voucher/post',
        reverse: '/api/ledger/voucher/reverse',
        reject: '/api/ledger/voucher/reject',
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

    function isActiveBoolean(value) {
        const normalized = String(value ?? '').trim().toLowerCase();
        return value === true || value === 1 || normalized === '1' || normalized === 'y' || normalized === 'true';
    }

    function renderBooleanBadge(value) {
        return isActiveBoolean(value)
            ? '<span class="voucher-review-badge reversal">예</span>'
            : '<span class="voucher-review-badge reversed">아니오</span>';
    }

    function voucherHeaderMetaColumns() {
        return getCachedDataTableMetaColumns({
            metaDomain: VOUCHER_META_DOMAIN,
            storageKey: VOUCHER_REVIEW_TABLE_SETTINGS_STORAGE_KEY,
            userSettingPageKey: VOUCHER_REVIEW_TABLE_SETTINGS_PAGE_KEY,
        }).filter((column) => String(column?.column_type || 'physical') === 'physical');
    }

    function reviewColumnClassName(field = '') {
        if (['sort_no', 'voucher_date', 'line_count', 'is_reversal'].includes(field)) {
            return 'text-center';
        }
        if (field === 'status') {
            return 'text-center dt-status-column';
        }
        if (['debit_total', 'credit_total'].includes(field)) {
            return 'text-end';
        }
        return '';
    }

    function renderVoucherField(field, data, type, row = {}) {
        const value = data ?? row[field] ?? '';
        if (field === 'status') {
            return statusBadge(value, type, row);
        }
        if (['debit_total', 'credit_total'].includes(field)) {
            return type === 'sort' || type === 'type' ? Number(value || 0) : formatNumber(value || 0);
        }
        if (field === 'is_reversal') {
            return type === 'sort' || type === 'type' ? (isActiveBoolean(value) ? 1 : 0) : renderBooleanBadge(value);
        }
        if (['sort_no', 'line_count'].includes(field)) {
            return type === 'sort' || type === 'type' ? Number(value || 0) : escapeHtml(value ?? '');
        }

        const summaryNameFields = {
            summary_account_id: 'summary_account_name',
            summary_client_id: 'summary_client_name',
            summary_project_id: 'summary_project_name',
            summary_bank_account_id: 'summary_bank_account_name',
            summary_card_id: 'summary_card_name',
            summary_employee_id: 'summary_employee_name',
        };
        const displayValue = summaryNameFields[field] ? (row[summaryNameFields[field]] || value) : value;
        return type === 'sort' || type === 'type' ? displayValue : escapeHtml(displayValue || '');
    }

    function buildVoucherHeaderColumns() {
        return voucherHeaderMetaColumns().map((meta) => {
            const field = String(meta?.key || '').trim();
            if (field === '') return null;
            return {
                data: field,
                name: field,
                settingsKey: field,
                sourceField: field,
                title: String(meta?.label || field).trim() || field,
                className: reviewColumnClassName(field),
                orderable: VOUCHER_REVIEW_ORDERABLE_FIELDS.has(field),
                widthResizable: true,
                visible: meta?.settings_visible !== false,
                defaultContent: '',
                render(data, type, row) {
                    return renderVoucherField(field, data, type, row);
                },
            };
        }).filter(Boolean);
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
        const status = normalizeStatus(row.status);

        if (debit !== credit) {
            return { key: 'error', label: '오류' };
        }

        if (status === 'DRAFT') {
            return { key: 'pending', label: '검토대기' };
        }

        if (status === 'REVIEW_REQUESTED') {
            return { key: 'pending', label: '검토대기' };
        }

        if (status === 'REVIEWED') {
            return { key: 'ready', label: '검토완료' };
        }

        if (status === 'APPROVED' || status === 'POSTED') {
            return { key: 'done', label: '전기완료' };
        }

        if (status === 'CLOSED') {
            return { key: 'done', label: '마감' };
        }

        return { key: 'pending', label: '검토대기' };
    }

    function statusBadge(status, type, row = {}) {
        const normalizedStatus = normalizeStatus(status);
        if (type === 'sort' || type === 'type') {
            return normalizedStatus;
        }
        const badges = [
            commonStatusBadge(normalizedStatus),
        ];

        if (Number(row.is_reversal || 0) === 1) {
            badges.push('<span class="voucher-review-badge reversal">취소전표</span>');
        } else if (row.reversal_voucher_id) {
            badges.push('<span class="voucher-review-badge reversed">취소됨</span>');
        }

        return badges.join(' ');
    }

    function selectedRows() {
        return selectedVoucher?.id ? [selectedVoucher] : [];
    }

    function selectedIdsByStatus(statuses = []) {
        const allowed = new Set(statuses.map((status) => normalizeStatus(status)));
        return selectedRows()
            .filter((row) => !allowed.size || allowed.has(normalizeStatus(row.status)))
            .map((row) => String(row.id));
    }

    function actionIds(statuses = []) {
        const ids = selectedIdsByStatus(statuses);
        if (ids.length) return ids;

        const status = normalizeStatus(selectedVoucher?.status);
        if (!selectedVoucher?.id) return [];
        if (statuses.length && !statuses.map((value) => normalizeStatus(value)).includes(status)) return [];
        return [String(selectedVoucher.id)];
    }

    function reverseActionIds() {
        if (selectedVoucher?.id) {
            const status = normalizeStatus(selectedVoucher.status);
            if (status !== 'POSTED' || Number(selectedVoucher.is_reversal || 0) === 1) return [];
            if (selectedVoucher.reversal_voucher?.id || selectedVoucher.reversal_voucher_id) return [];

            return [String(selectedVoucher.id)];
        }

        const ids = selectedRows()
            .filter((row) => normalizeStatus(row.status) === 'POSTED')
            .filter((row) => Number(row.is_reversal || 0) !== 1)
            .filter((row) => !row.reversal_voucher_id)
            .map((row) => String(row.id));

        if (ids.length) return ids;

        return ids;
    }

    function hasUnlinkedEvidence(ids = []) {
        if (!table) return false;

        const targetIds = new Set(ids.map((id) => String(id)));
        let hasUnlinked = false;
        table.rows().every(function () {
            const row = this.data() || {};
            if (!targetIds.has(String(row.id || ''))) {
                return;
            }

            const linked = String(row.linked_status || '').toLowerCase() === 'linked';
            if (!linked) {
                hasUnlinked = true;
            }
        });

        if (!hasUnlinked && selectedVoucher?.id && targetIds.has(String(selectedVoucher.id))) {
            hasUnlinked = !Array.isArray(selectedVoucher.linked_evidences)
                || selectedVoucher.linked_evidences.length === 0;
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
        const currentStatus = normalizeStatus(selectedVoucher?.status);

        const hasReviewRequested = selected.some((row) => normalizeStatus(row.status) === 'REVIEW_REQUESTED')
            || currentStatus === 'REVIEW_REQUESTED';
        const hasReviewed = selected.some((row) => normalizeStatus(row.status) === 'REVIEWED')
            || currentStatus === 'REVIEWED';
        const hasReversiblePosted = reverseActionIds().length > 0;

        hideAllButtons();

        if (hasReviewRequested) {
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

    function setDetailPanelOpen(open, rowEl = null) {
        layoutEl?.classList.toggle('is-detail-open', open);
        tableEl.querySelectorAll('tbody tr').forEach((tr) => tr.classList.remove('is-selected'));
        if (open && rowEl) {
            rowEl.classList.add('is-selected');
        }
        if (layoutEl && table) {
            void layoutEl.offsetWidth;
            table.columns.adjust().draw(false);
        }
    }

    function closeDetailPanel() {
        setDetailPanelOpen(false);
    }

    function openDetailPanel(rowEl = null) {
        setDetailPanelOpen(true, rowEl);
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
            const reversalStatus = voucher.reversal_voucher?.status || '';
            const reversalCreatedAt = voucher.reversal_voucher?.created_at || '';
            return `
                <span class="voucher-review-badge reversed">취소전표 생성됨</span>
                <button type="button" class="btn btn-link btn-sm p-0 ms-1 voucher-review-open-reversal" data-voucher-id="${escapeHtml(voucher.reversal_voucher?.id || voucher.reversal_voucher_id || '')}">
                    ${escapeHtml(reversalNo)}
                </button>
                ${reversalStatus ? statusBadge(reversalStatus, 'display', voucher.reversal_voucher || {}) : ''}
                ${reversalCreatedAt ? `<span class="text-muted small ms-1">${escapeHtml(reversalCreatedAt)}</span>` : ''}
            `;
        }

        return '-';
    }

    function refTargetLabel(target = '') {
        return ({
            CLIENT: '거래처', CUSTOMER: '거래처', VENDOR: '거래처', COUNTERPARTY: '거래처',
            PROJECT: '프로젝트', EMPLOYEE: '직원', USER: '직원',
            ACCOUNT: '계좌', BANK: '계좌', BANK_ACCOUNT: '계좌', CARD: '카드',
        })[String(target || '').toUpperCase()] || String(target || '참조');
    }

    function renderLineRefs(line = {}) {
        const refs = Array.isArray(line.refs) ? line.refs : [];
        if (!refs.length) return '';
        return `<div class="voucher-review-line-sub">${refs.map((ref) => {
            const target = ref.ref_target || ref.line_ref_target || '';
            const value = ref.ref_label || ref.line_ref_label || ref.ref_id || ref.line_ref_id || '';
            return `<span class="me-2">${escapeHtml(refTargetLabel(target))}: ${escapeHtml(value)}</span>`;
        }).join('')}</div>`;
    }

    function renderRecommendation(line = {}) {
        const values = [];
        if (line.journal_rule_id) values.push(`분개규칙 ${escapeHtml(line.journal_rule_id)}`);
        if (line.recommend_source) values.push(`추천근거 ${escapeHtml(line.recommend_source)}`);
        if (line.recommend_confidence !== null && line.recommend_confidence !== undefined && line.recommend_confidence !== '') {
            values.push(`신뢰도 ${escapeHtml(line.recommend_confidence)}%`);
        }
        if (Number(line.is_user_modified || 0) === 1) values.push('사용자 수정');
        return values.length ? `<div class="voucher-review-line-sub">${values.join(' · ')}</div>` : '';
    }

    async function loadDetail(id) {
        if (!layoutEl?.classList.contains('is-detail-open')) {
            openDetailPanel();
        }
        const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);
        const voucher = json.data || {};
        selectedVoucher = voucher;

        titleEl.textContent = voucher.voucher_no || id;
        subEl.textContent = [voucher.voucher_date, voucher.summary_text].filter(Boolean).join(' / ');
        statusEl.className = 'voucher-review-status-badge';
        statusEl.innerHTML = statusBadge(voucher.status, 'display', voucher);

        basicInfoEl.innerHTML = `
            <dt>전표번호</dt><dd>${escapeHtml(voucher.voucher_no || '')}</dd>
            <dt>전표일자</dt><dd>${escapeHtml(voucher.voucher_date || '')}</dd>
            <dt>상태</dt><dd>${statusBadge(voucher.status, 'display', voucher)}</dd>
            <dt>취소구분</dt><dd>${renderReversalInfo(voucher)}</dd>
            <dt>적요</dt><dd>${escapeHtml(voucher.summary_text || '')}</dd>
            <dt>비고</dt><dd>${escapeHtml(voucher.note || '')}</dd>
            <dt>작성자</dt><dd>${escapeHtml(voucher.created_by_name || voucher.created_by || '-')}</dd>
            <dt>작성일시</dt><dd>${escapeHtml(voucher.created_at || '-')}</dd>
            <dt>수정자</dt><dd>${escapeHtml(voucher.updated_by_name || voucher.updated_by || '-')}</dd>
            <dt>수정일시</dt><dd>${escapeHtml(voucher.updated_at || '-')}</dd>
        `;

        const lines = Array.isArray(voucher.lines) ? voucher.lines : [];
        linesEl.innerHTML = lines.length ? lines.map((line) => `
            <div class="voucher-review-line">
                <div>
                    <div class="voucher-review-line-main">${escapeHtml(line.line_no || '')}. ${escapeHtml(line.account_text || line.account_name || line.account_id || '')}</div>
                    <div class="voucher-review-line-sub">${escapeHtml(line.line_summary || '')}</div>
                    ${renderLineRefs(line)}
                    ${renderRecommendation(line)}
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
        const originalLinkedEvidences = Array.isArray(voucher.original_linked_evidences)
            ? voucher.original_linked_evidences
            : [];
        const evidenceText = (evidences) => evidences.map((evidence) => [
            evidence.source_type || '',
            evidence.evidence_date || '',
            evidence.client_name || '',
            evidence.source_key || evidence.id || '',
        ].filter(Boolean).join(' / '));
        const linkedEvidenceText = evidenceText(linkedEvidences);
        const originalEvidenceText = evidenceText(originalLinkedEvidences);
        const evidenceSections = [];
        evidenceSections.push(linkedEvidenceText.length
            ? `<strong class="d-block mb-1">직접 연결 증빙</strong>${linkedEvidenceText.map((text) => '<span class="d-block">' + escapeHtml(text) + '</span>').join('')}`
            : '<strong class="d-block mb-1">직접 연결 증빙</strong><span class="text-muted">미연결</span>');
        if (Number(voucher.is_reversal || 0) === 1) {
            evidenceSections.push(originalEvidenceText.length
                ? `<strong class="d-block mt-2 mb-1">원전표 증빙 <span class="text-muted fw-normal">(읽기 전용)</span></strong>${originalEvidenceText.map((text) => '<span class="d-block">' + escapeHtml(text) + '</span>').join('')}`
                : '<strong class="d-block mt-2 mb-1">원전표 증빙 <span class="text-muted fw-normal">(읽기 전용)</span></strong><span class="text-muted">미연결</span>');
        }
        linkedInfoEl.innerHTML = evidenceSections.join('');

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

    async function initTable() {
        await fetchDataTableMetaColumns({
            metaDomain: VOUCHER_META_DOMAIN,
            storageKey: VOUCHER_REVIEW_TABLE_SETTINGS_STORAGE_KEY,
            userSettingPageKey: VOUCHER_REVIEW_TABLE_SETTINGS_PAGE_KEY,
        });

        table = await createDataTable({
            tableSelector: '#voucherReviewTable',
            api: API.list,
            serverSide: true,
            orderableColumnKeys: Array.from(VOUCHER_REVIEW_ORDERABLE_FIELDS),
            ajaxData(request) {
                return {
                    ...request,
                    scope: 'review',
                };
            },
            columns: buildVoucherHeaderColumns(),
            tableSettings: {
                enabled: true,
                pageKey: VOUCHER_REVIEW_TABLE_SETTINGS_PAGE_KEY,
                tableKey: VOUCHER_REVIEW_TABLE_SETTINGS_TABLE_KEY,
                storageKey: VOUCHER_REVIEW_TABLE_SETTINGS_STORAGE_KEY,
                userSettingPageKey: VOUCHER_REVIEW_TABLE_SETTINGS_PAGE_KEY,
                metaDomain: VOUCHER_META_DOMAIN,
                description: '전표 헤더 메타데이터 기준 테이블 설정',
                tableLabel: '전표검토·전기 목록',
                title: '전표검토·전기 목록 테이블 설정',
            },
            dataSrc(json) {
                return Array.isArray(json?.data) ? json.data : [];
            },
            defaultOrder: [{ key: 'sort_no', dir: 'desc' }],
            pageLength: 100,
            selectable: false,
            showCopyButton: false,
            deleteButton: false,
        });

        table.on('draw.dt xhr.dt', () => {
            syncRowsAfterDraw();
        });
        bindTableHighlight('#voucherReviewTable', table);
        refreshDataTableLayout(table, { delays: [0] });

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
            renderEmptyDetail();
        });
        resetButton?.addEventListener('click', () => {
            renderEmptyDetail();
        });
    }

    async function runAction(action, ids, payload = {}) {
        if (!ids.length) return false;

        const actionButtons = [rejectBtn, confirmBtn, cancelConfirmBtn, approveBtn, reverseBtn, confirmRejectBtn]
            .filter(Boolean);
        if (actionButtons.some((button) => button.dataset.actionPending === '1')) return false;
        actionButtons.forEach((button) => {
            button.dataset.actionPending = '1';
            button.disabled = true;
        });

        try {
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

            table.ajax.reload(null, false);
            if (selectedVoucher?.id) {
                await loadDetail(selectedVoucher.id).catch(renderEmptyDetail);
            }
            return true;
        } catch (error) {
            notify('error', error?.message || '요청 처리에 실패했습니다.');
            return false;
        } finally {
            actionButtons.forEach((button) => {
                delete button.dataset.actionPending;
                button.disabled = false;
            });
            updateActionButtons();
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
            if (await runAction('reject', pendingRejectIds, { reason })) {
                rejectModal?.hide();
            }
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

            openDetailPanel(row);
            void loadDetail(data.id);
        });

        window.jQuery(tableEl).on('dblclick', 'tbody tr', (event) => {
            if (event.target.closest('input')) return;
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

        rejectBtn?.addEventListener('click', () => openRejectModal(actionIds(['REVIEW_REQUESTED'])));
        confirmRejectBtn?.addEventListener('click', () => void confirmReject());
        rejectReasonEl?.addEventListener('input', () => {
            if (String(rejectReasonEl.value || '').trim()) {
                rejectReasonErrorEl?.classList.add('d-none');
            }
        });
        confirmBtn?.addEventListener('click', () => void runAction('completeReview', actionIds(['REVIEW_REQUESTED'])));
        cancelConfirmBtn?.addEventListener('click', () => void runAction('cancelCompleteReview', actionIds(['REVIEWED'])));
        approveBtn?.addEventListener('click', () => {
            const ids = actionIds(['REVIEWED']);
            if (!ids.length) return;
            const evidenceNotice = hasUnlinkedEvidence(ids) ? ' 증빙이 연결되지 않은 전표가 포함되어 있습니다.' : '';
            if (!window.confirm(`전기 후에는 직접 수정할 수 없으며 오류는 취소전표로 정정합니다. 회계·지급·학습 후속처리가 실행됩니다.${evidenceNotice} 계속하시겠습니까?`)) {
                return;
            }
            void runAction('post', ids);
        });
        reverseBtn?.addEventListener('click', () => {
            const ids = reverseActionIds();
            if (!ids.length) return;
            if (!window.confirm('원전표는 전기완료 상태로 유지되며, 차·대변을 반대로 한 취소전표가 작성중 상태로 생성됩니다. 계속하시겠습니까?')) return;
            void runAction('reverse', ids);
        });
        basicInfoEl?.addEventListener('click', (event) => {
            const button = event.target.closest('.voucher-review-open-reversal');
            const voucherId = String(button?.dataset?.voucherId || '').trim();
            if (voucherId !== '') {
                void loadDetail(voucherId);
            }
        });
    }

    await initTable();
    initSearchForm();
    bindEvents();
    renderEmptyDetail();
})();
