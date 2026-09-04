import { formatAmount } from '/public/assets/js/common/format.js';
import { createDataTable } from '/public/assets/js/common/table/data-table.js';

const identity = (row = {}) => `${String(row.import_type || '').toUpperCase()}:${String(row.evidence_id || '')}`;
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
}[char]));
const policyLabel = (value) => ({ DATA: '자료증빙', FUND: '자금증빙', BOTH: '겸용' }[String(value || '').toUpperCase()] || '-');
const isEvidenceComplete = (row = {}) => ['COMPLETED']
    .includes(String(row.evidence_status || '').trim().toUpperCase());
const evidenceStatusBadge = (row = {}) => {
    const complete = isEvidenceComplete(row);
    const label = complete ? '완료' : '미완료';
    const className = complete ? 'text-bg-success' : 'text-bg-secondary';
    const title = complete ? '추가할 수 있는 증빙입니다.' : '증빙원본의 필수항목을 모두 입력해야 추가할 수 있습니다.';
    return `<span class="badge ${className}" title="${title}">${label}</span>`;
};
const textCell = (value) => escapeHtml(String(value ?? '').trim() || '-');

const EVIDENCE_SELECTION_META_DOMAIN = 'voucher-evidence-selection';
const EVIDENCE_SELECTION_STORAGE_KEY = 'datatable.settings.ledger.voucher.evidence-selection.v1';
const EVIDENCE_SELECTION_PAGE_KEY = 'ledger.voucher.evidence-selection';

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
            scrollX: false,
            scrollY: '',
            responsive: false,
            autoWidth: false,
            widthScopeSelector: '.journal-evidence-table-wrap',
            fitColumnsToScope: true,
            showColumnVisibility: false,
            showCopyButton: false,
            showSelectionMoveButtons: false,
            deleteButton: false,
            tableSettings: {
                pageKey: 'ledger.voucher',
                tableKey: 'evidence-selection',
                storageKey: EVIDENCE_SELECTION_STORAGE_KEY,
                userSettingPageKey: EVIDENCE_SELECTION_PAGE_KEY,
                metaDomain: EVIDENCE_SELECTION_META_DOMAIN,
                tableLabel: '증빙 추가 목록',
                title: '증빙 추가 테이블 설정',
                defaultVisibleColumns: [
                    '__select',
                    'evidence_date',
                    'import_type',
                    'evidence_status',
                    'employee_name',
                    'client_name',
                    'display_summary',
                    'display_amount',
                    '__actions',
                ],
            },
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
                { data: 'evidence_id', title: '증빙 ID', name: 'evidence_id', settingsKey: 'evidence_id', visible: false, render: textCell },
                { data: 'source_type', title: '원천유형', name: 'source_type', settingsKey: 'source_type', visible: false, render: textCell },
                { data: 'display_type', title: '자료유형', name: 'import_type', settingsKey: 'import_type', width: '118px', defaultContent: '-' },
                { data: 'evidence_type', title: '증빙구분', name: 'evidence_type', settingsKey: 'evidence_type', width: '78px', visible: false, render: (value) => policyLabel(value) },
                { data: 'evidence_date', title: '기준일', name: 'evidence_date', settingsKey: 'evidence_date', width: '88px', defaultContent: '-' },
                { data: 'evidence_status', title: '증빙상태', name: 'evidence_status', settingsKey: 'evidence_status', width: '76px', className: 'text-center text-nowrap', render: (_value, _type, row) => evidenceStatusBadge(row) },
                { data: 'business_unit', title: '사업구분', name: 'business_unit', settingsKey: 'business_unit', visible: false, render: textCell },
                { data: 'transaction_direction', title: '거래구분', name: 'transaction_direction', settingsKey: 'transaction_direction', visible: false, render: textCell },
                { data: 'operation_type', title: '업무유형', name: 'operation_type', settingsKey: 'operation_type', visible: false, render: textCell },
                { data: 'client_name', title: '거래처', name: 'client_name', settingsKey: 'client_name', width: '120px', render: textCell },
                { data: 'project_name', title: '프로젝트', name: 'project_name', settingsKey: 'project_name', visible: false, render: textCell },
                { data: 'employee_name', title: '직원', name: 'employee_name', settingsKey: 'employee_name', width: '92px', render: textCell },
                { data: 'bank_account_name', title: '계좌', name: 'bank_account_name', settingsKey: 'bank_account_name', visible: false, orderable: false, render: textCell },
                { data: 'card_name', title: '카드', name: 'card_name', settingsKey: 'card_name', visible: false, orderable: false, render: textCell },
                { data: 'team_name', title: '팀', name: 'team_name', settingsKey: 'team_name', visible: false, orderable: false, render: textCell },
                { data: 'display_summary', title: '적요', name: 'description', settingsKey: 'display_summary', className: 'journal-evidence-summary-cell', render: textCell },
                { data: 'display_amount', title: '금액', name: 'display_amount', settingsKey: 'display_amount', width: '108px', className: 'text-end text-nowrap', render: (value, _type, row) => `${escapeHtml(row.display_amount_sign || '')}${escapeHtml(formatAmount(value || 0))}` },
                { data: 'created_at', title: '생성일시', name: 'created_at', settingsKey: 'created_at', visible: false, render: textCell },
                { data: 'updated_at', title: '수정일시', name: 'updated_at', settingsKey: 'updated_at', visible: false, render: textCell },
                { data: null, title: '관리', name: 'manage', settingsKey: '__actions', width: '58px', orderable: false, searchable: false, className: 'text-center', render: (_value, _type, row) => `<button type="button" class="btn btn-outline-secondary btn-sm btn-evidence-manage" data-evidence-key="${escapeHtml(identity(row))}">수정</button>` },
            ],
        });

        const tableElement = document.getElementById('journal_evidence_search_table');
        const tableWrap = tableElement?.closest('.journal-evidence-table-wrap');
        const toolbar = tableWrap?.querySelector('.dataTables_wrapper .dt-top');
        const syncStickyOffset = () => {
            const toolbarHeight = Math.ceil(toolbar?.getBoundingClientRect?.().height || 0);
            tableWrap?.style.setProperty('--journal-evidence-toolbar-height', `${toolbarHeight}px`);
        };
        syncStickyOffset();
        if (tableWrap && toolbar && typeof ResizeObserver === 'function' && !state.evidenceSearchToolbarObserver) {
            state.evidenceSearchToolbarObserver = new ResizeObserver(syncStickyOffset);
            state.evidenceSearchToolbarObserver.observe(toolbar);
        }
        tableElement?.addEventListener('datatable:selection-changed', () => {
            ctx.updateEvidenceSelectionCount?.();
        });
        tableElement?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-evidence-manage');
            if (!button) return;
            event.preventDefault();
            event.stopPropagation();
            const row = state.pendingEvidenceRows.get(button.dataset.evidenceKey || '');
            if (row) void ctx.openEvidenceSourceEditor?.(row);
        });

        return state.evidenceSearchTable;
    };

    ctx.reloadEvidenceSelectionTable = async () => (await ctx.ensureEvidenceSelectionTable())?.ajax?.reload(null, false);
}
