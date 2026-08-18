import { formatAmount } from '/public/assets/js/common/format.js';
import { createDataTable } from '/public/assets/js/common/table/data-table.js';

const identity = (row = {}) => `${String(row.import_type || '').toUpperCase()}:${String(row.evidence_id || '')}`;
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
}[char]));
const policyLabel = (value) => ({ DATA: '자료증빙', FUND: '자금증빙', BOTH: '겸용' }[String(value || '').toUpperCase()] || '-');
const isEvidenceComplete = (row = {}) => ['COMPLETED', 'READY', 'VERIFY_ONLY']
    .includes(String(row.evidence_status || '').trim().toUpperCase());
const evidenceStatusBadge = (row = {}) => {
    const complete = isEvidenceComplete(row);
    const label = complete ? '완료' : '미완료';
    const className = complete ? 'text-bg-success' : 'text-bg-secondary';
    const title = complete ? '추가할 수 있는 증빙입니다.' : '증빙원본의 필수항목을 모두 입력해야 추가할 수 있습니다.';
    return `<span class="badge ${className}" title="${title}">${label}</span>`;
};

export function registerEvidenceSelectionTable(ctx) {
    const { state } = ctx;

    ctx.ensureEvidenceSelectionTable = async () => {
        if (state.evidenceSearchTable) return state.evidenceSearchTable;

        state.evidenceSearchTable = await createDataTable({
            tableSelector: '#journal_evidence_search_table',
            api: ctx.API.evidenceSearch,
            serverSide: true,
            pageLength: 100,
            defaultOrder: [[0, 'asc']],
            searching: true,
            paging: true,
            info: true,
            scrollX: true,
            scrollY: '48vh',
            responsive: false,
            autoWidth: false,
            showColumnVisibility: false,
            showCopyButton: false,
            showSelectionMoveButtons: false,
            deleteButton: false,
            tableSettings: { enabled: false },
            selectable: true,
            isRowSelectable: isEvidenceComplete,
            rowIdField: identity,
            ajaxData(request) {
                return ctx.buildEvidenceSearchRequest(request);
            },
            dataSrc(json) {
                const pagination = json.data?.pagination || {};
                json.recordsTotal = Number(pagination.total || 0);
                json.recordsFiltered = Number(pagination.total || 0);
                const rows = Array.isArray(json.data?.items) ? json.data.items : [];
                rows.forEach((row) => state.pendingEvidenceRows.set(identity(row), row));
                return rows;
            },
            columns: [
                { data: 'evidence_date', title: '기준일', name: 'evidence_date', width: '105px', defaultContent: '-' },
                { data: 'evidence_type', title: '증빙구분', name: 'evidence_type', width: '86px', render: (value) => policyLabel(value) },
                { data: 'display_type', title: '자료유형', name: 'import_type', width: '140px', defaultContent: '-' },
                { data: 'evidence_status', title: '증빙상태', name: 'evidence_status', width: '90px', className: 'text-center text-nowrap', render: (_value, _type, row) => evidenceStatusBadge(row) },
                { data: 'client_name', title: '거래처', name: 'client_name', width: '180px', render: (value) => `<span class="text-truncate d-block" title="${escapeHtml(value || '-')}">${escapeHtml(value || '-')}</span>` },
                { data: 'display_summary', title: '적요', name: 'description', className: 'journal-evidence-summary-cell', render: (value) => `<span class="text-truncate d-block" title="${escapeHtml(value || '-')}">${escapeHtml(value || '-')}</span>` },
                { data: 'display_amount', title: '금액', name: 'display_amount', width: '125px', className: 'text-end text-nowrap', render: (value, _type, row) => `${escapeHtml(row.display_amount_sign || '')}${escapeHtml(formatAmount(value || 0))}` },
                { data: null, title: '관리', name: 'manage', width: '64px', orderable: false, searchable: false, className: 'text-center', render: (_value, _type, row) => `<button type="button" class="btn btn-outline-secondary btn-sm btn-evidence-manage" data-evidence-key="${escapeHtml(identity(row))}">수정</button>` },
            ],
        });

        const tableElement = document.getElementById('journal_evidence_search_table');
        tableElement?.addEventListener('datatable:selection-changed', () => {
            ctx.updateEvidenceSelectionCount?.();
        });
        tableElement?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-evidence-manage');
            if (!button) return;
            const row = state.pendingEvidenceRows.get(button.dataset.evidenceKey || '');
            if (row) ctx.openEvidenceSourceEditor?.(row);
        });

        return state.evidenceSearchTable;
    };

    ctx.reloadEvidenceSelectionTable = async () => (await ctx.ensureEvidenceSelectionTable())?.ajax?.reload(null, false);
}
