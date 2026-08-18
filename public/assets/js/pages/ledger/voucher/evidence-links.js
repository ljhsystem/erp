import { createHtmlGrid } from '/public/assets/js/common/html-grid/index.js';
import { formatAmount } from '/public/assets/js/common/format.js';

const identity = (row = {}) => `${String(row.import_type || '').toUpperCase()}:${String(row.evidence_id || '')}`;
const policyLabel = (value) => ({ DATA: '자료증빙', FUND: '자금증빙', BOTH: '겸용' }[String(value || '').toUpperCase()] || '-');
const formatSignedAmount = (value, { row } = {}) => `${row?.values?.display_amount_sign || ''}${formatAmount(value)}`;
const columns = [
    { key: 'selection', label: '선택', type: 'selection', width: 56 },
    { key: 'link_state', label: '연결변경상태', formatter: 'text', width: 110 },
    { key: 'evidence_policy', label: '증빙구분', formatter: 'text', width: 96 },
    { key: 'evidence_date', label: '증빙일자', formatter: 'date', width: 112 },
    { key: 'display_type', label: '자료유형', formatter: 'text', width: 150 },
    { key: 'client_name', label: '거래처', formatter: 'text', width: 190 },
    { key: 'display_summary', label: '적요', formatter: 'text', minWidth: 220 },
    { key: 'display_amount', label: '금액', formatter: 'signedAmount', width: 140 },
];
function gridRow(row = {}) {
    return { rowId: identity(row), rowState: 'readonly', values: {
        selection: '', link_state: row._link_state === 'PENDING' ? '추가 예정' : '연결됨',
        evidence_policy: policyLabel(row.evidence_type), evidence_date: row.evidence_date || '-',
        display_type: row.display_type || '-', client_name: row.client_name || '-',
        display_summary: row.display_summary || '-', display_amount: Number(row.display_amount || 0),
        display_amount_sign: row.display_amount_sign || '',
    }, meta: { evidence: row } };
}
export function registerEvidenceLinks(ctx) {
    const { state, linkedEvidencesGridEl } = ctx;
    if (linkedEvidencesGridEl) {
        state.linkedEvidenceGrid = createHtmlGrid({ host: linkedEvidencesGridEl, gridId: 'voucher-linked-evidences', columns, rows: [], formatters: {
            signedAmount: formatSignedAmount,
        }, capabilities: {
            addRow: false, deleteRow: false, insertRow: false, reorder: false, selection: true, multiSelection: true,
            keyboard: false, clipboard: false, footer: false, validation: false, columnHide: false, columnMove: false, columnResize: false,
        }});
        state.linkedEvidenceGrid.render({ noDataMessage: '조회된 데이터가 없습니다.' });
    }
    ctx.renderLinkedEvidenceGrid = () => state.linkedEvidenceGrid?.setState({ ...state.linkedEvidenceGrid.getState(), rows: state.linkedEvidences.map(gridRow) });
    ctx.clearSelectedLinkedEvidences = () => {
        const selected = new Set(state.linkedEvidenceGrid?.getState()?.selection?.selectedRowIds || []);
        if (!selected.size) return false;
        state.linkedEvidences = state.linkedEvidences.filter((row) => !selected.has(identity(row)));
        ctx.setLinkedEvidence({ linked_evidences: state.linkedEvidences });
        ctx.clearJournalRecommendations?.();
        return true;
    };
}
