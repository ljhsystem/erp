import { formatAmount } from '/public/assets/js/common/format.js';

const identity = (row = {}) => `${String(row.import_type || '').toUpperCase()}:${String(row.evidence_id || '')}`;
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
}[char]));
const policyLabel = (value) => ({ DATA: '자료증빙', BOTH: '겸용' }[String(value || '').toUpperCase()] || '-');
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

const EVIDENCE_SELECTION_META_DOMAIN = 'transaction-evidence-selection';
const EVIDENCE_SELECTION_STORAGE_KEY = 'datatable.settings.ledger.transaction.evidence-selection.v1';
const EVIDENCE_SELECTION_PAGE_KEY = 'ledger.transaction.evidence-selection';

export function registerTransactionEvidenceSelectionTable(ctx) {
    ctx.ensureTransactionEvidenceSelectionTable = async () => {
        if (ctx.evidenceSearchTable) return ctx.evidenceSearchTable;

        ctx.evidenceSearchTable = await ctx.createDataTable({
            tableSelector: '#transaction_evidence_search_table',
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
            widthScopeSelector: '.transaction-evidence-table-wrap',
            fitColumnsToScope: true,
            showColumnVisibility: false,
            showCopyButton: false,
            showSelectionMoveButtons: false,
            deleteButton: false,
            tableSettings: {
                pageKey: 'ledger.transaction',
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
                const order = request.order?.[0] || {};
                const orderedColumn = request.columns?.[Number(order.column)] || {};
                return {
                    q: String(request.search?.value || '').trim(),
                    start: Number(request.start || 0),
                    length: Number(request.length || 100),
                    sort_field: orderedColumn.name || 'evidence_date',
                    sort_direction: String(order.dir || 'asc'),
                    exclude_evidences: JSON.stringify(ctx.linkedEvidences.map((item) => ({
                        import_type: item.import_type,
                        evidence_id: item.evidence_id || item.id,
                    }))),
                };
            },
            dataSrc(json) {
                const pagination = json.data?.pagination || {};
                json.recordsTotal = Number(pagination.records_total || pagination.total || 0);
                json.recordsFiltered = Number(pagination.total || 0);
                const rows = Array.isArray(json.data?.items) ? json.data.items : [];
                ctx.evidenceSearchRows = rows;
                rows.forEach((row) => ctx.pendingEvidenceRows.set(identity(row), row));
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
                { data: 'display_summary', title: '적요', name: 'description', settingsKey: 'display_summary', className: 'transaction-evidence-summary-cell', render: textCell },
                { data: 'display_amount', title: '금액', name: 'display_amount', settingsKey: 'display_amount', width: '108px', className: 'text-end text-nowrap', render: (value) => escapeHtml(formatAmount(value || 0)) },
                { data: 'created_at', title: '생성일시', name: 'created_at', settingsKey: 'created_at', visible: false, render: textCell },
                { data: 'updated_at', title: '수정일시', name: 'updated_at', settingsKey: 'updated_at', visible: false, render: textCell },
                { data: null, title: '관리', name: 'manage', settingsKey: '__actions', width: '58px', orderable: false, searchable: false, className: 'text-center', render: (_value, _type, row) => `<button type="button" class="btn btn-outline-secondary btn-sm btn-transaction-evidence-manage" data-evidence-key="${escapeHtml(identity(row))}">수정</button>` },
            ],
        });

        const tableElement = document.getElementById('transaction_evidence_search_table');
        const tableWrap = tableElement?.closest('.transaction-evidence-table-wrap');
        const toolbar = tableWrap?.querySelector('.dataTables_wrapper .dt-top');
        const syncStickyOffset = () => {
            const toolbarHeight = Math.ceil(toolbar?.getBoundingClientRect?.().height || 0);
            tableWrap?.style.setProperty('--transaction-evidence-toolbar-height', `${toolbarHeight}px`);
        };
        syncStickyOffset();
        if (tableWrap && toolbar && typeof ResizeObserver === 'function' && !ctx.evidenceSearchToolbarObserver) {
            ctx.evidenceSearchToolbarObserver = new ResizeObserver(syncStickyOffset);
            ctx.evidenceSearchToolbarObserver.observe(toolbar);
        }
        tableElement?.addEventListener('datatable:selection-changed', (event) => {
            ctx.pendingEvidenceKeys = new Set(event.detail?.ids || []);
            ctx.updateEvidenceSelectionCount?.();
        });
        tableElement?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-transaction-evidence-manage');
            if (!button) return;
            const row = ctx.evidenceSearchRows.find((item) => identity(item) === button.dataset.evidenceKey);
            if (row) ctx.openEvidenceSourceEditor?.(row);
        });
        return ctx.evidenceSearchTable;
    };

    ctx.reloadTransactionEvidenceSelectionTable = async () => (await ctx.ensureTransactionEvidenceSelectionTable())?.ajax?.reload(null, false);
}
