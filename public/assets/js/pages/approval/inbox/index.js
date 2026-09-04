import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { formatAmount, formatDateDisplay } from '/public/assets/js/common/format.js';
import { approvalStatusBadge } from '/public/assets/js/common/approval-status.js';
import { notify as showNotification } from '/public/assets/js/common/notification.js';

const API = {
    list: '/api/approval/inbox/list',
    detail: '/api/approval/inbox/detail',
    act: '/api/approval/inbox/act',
};
const BOX_DESCRIPTIONS = {
    actionable: '현재 로그인 사용자가 승인 또는 반려해야 할 결재요청입니다.',
    progress: '현재 결재 절차가 진행 중인 결재요청입니다.',
    completed: '최종 승인까지 완료된 결재요청입니다.',
    rejected: '반려로 종료된 과거 결재요청 이력입니다. 같은 문서를 재상신하면 여러 이력이 표시될 수 있습니다.',
    submitted: '내가 상신한 모든 결재요청 이력입니다. 진행·승인·반려·회수 요청을 함께 표시합니다.',
};
const MONEY_FIELDS = new Set(['item_unit_price', 'item_supply_amount', 'item_vat_amount', 'item_total_amount', 'amount']);
const modalElement = document.getElementById('approvalInboxDetailModal');
const modal = modalElement && window.bootstrap ? new window.bootstrap.Modal(modalElement) : null;
let table;
let box = 'actionable';
let detail = null;
let acting = false;
const initialParams = new URLSearchParams(window.location.search);

const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
})[character]);
const statusBadge = status => approvalStatusBadge(status, escapeHtml);
const notify = (type, message) => showNotification(type, message);

async function request(url, options = {}) {
    const response = await fetch(url, { headers: { 'Content-Type': 'application/json' }, ...options });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.success === false) {
        const correlationId = String(result.correlation_id || '').trim();
        const baseMessage = result.user_message || result.message || '요청 처리 중 오류가 발생했습니다.';
        const error = new Error(correlationId ? `${baseMessage} 오류 추적번호: ${correlationId}` : baseMessage);
        const systemError = response.status >= 500 || ['APPROVAL_PROCESSING_FAILED', 'APPROVAL_SYSTEM_ERROR'].includes(result.result_code);
        error.notificationType = systemError ? 'error' : 'warning';
        error.resultCode = result.result_code || result.error_code || null;
        error.correlationId = correlationId || null;
        throw error;
    }
    return result;
}

function columns() {
    return [
        {
            data: 'document_type',
            title: '문서종류',
            render: (_value, _type, row) => escapeHtml(row.document_type_name || row.document_type || '-'),
        },
        { data: 'document_no', title: '신청번호', className: 'text-center', render: value => `#${escapeHtml(value)}` },
        { data: 'applicant_name', title: '신청 대상 직원', defaultContent: '-' },
        { data: 'requester_name', title: '발의자' },
        { data: 'application_date', title: '신청일자', render: value => escapeHtml(formatDateDisplay(value) || value || '-') },
        { data: 'title', title: '제목', render: value => escapeHtml(value || '-') },
        {
            data: 'total_amount',
            title: '총금액',
            className: 'text-end',
            render: (value, type, row) => {
                if (value === null || value === undefined || value === '') return '-';
                const formatted = `${formatAmount(value)}원`;
                return type === 'display'
                    ? `<span title="${escapeHtml(`${row.primary_amount_label || '총금액'} ${formatted}`)}">${escapeHtml(formatted)}</span>`
                    : value;
            },
        },
        { data: 'current_step_name', title: '현재 결재단계', defaultContent: '-' },
        { data: 'requested_at', title: '상신일시', defaultContent: '-' },
        { data: 'approval_status', title: '결재상태', className: 'text-center', render: statusBadge },
        {
            data: null,
            settingsKey: 'progress',
            title: '진행률',
            className: 'text-center',
            render: (_value, _type, row) => {
                const total = Number(row.step_count || 0);
                const approved = Number(row.approved_step_count || 0);
                return total > 0 ? `${approved}/${total} (${Math.round(approved / total * 100)}%)` : '-';
            },
        },
        {
            data: null,
            settingsKey: '__actions',
            title: '관리',
            className: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            render: (_value, type, row) => type === 'display'
                ? `<button type="button" class="btn btn-outline-primary btn-sm approval-detail-button" data-request-id="${escapeHtml(row.request_id)}">상세</button>`
                : '',
        },
    ];
}

async function initTable() {
    const tableColumns = columns();
    table = await createDataTable({
        tableSelector: '#approvalInboxTable',
        api: `${API.list}?box=${box}`,
        columns: tableColumns,
        serverSide: true,
        pageLength: 100,
        defaultOrder: [[8, 'desc']],
        tableSettings: {
            enabled: true,
            pageKey: 'approval.inbox',
            userSettingPageKey: 'approval.inbox',
            tableKey: 'approval-inbox-table',
            storageKey: 'datatable.settings.approval.inbox.v1',
            columns: tableColumns,
            resetOnColumnSchemaChange: true,
            tableLabel: '결재함',
            title: '결재함 보기 설정',
        },
        selectable: false,
        deleteButton: false,
        showSelectionMoveButtons: false,
        showCopyButton: false,
    });
    bindTableHighlight('#approvalInboxTable', table);
    table.on('draw.dt xhr.dt', () => {
        const info = table.page.info();
        document.getElementById('approvalInboxCount').textContent = `총 ${info.recordsDisplay || 0}건`;
    });
    const body = document.querySelector('#approvalInboxTable tbody');
    body?.addEventListener('click', event => {
        const button = event.target.closest('.approval-detail-button');
        if (button) void openDetail(button.dataset.requestId);
    });
    window.jQuery?.('#approvalInboxTable tbody').on('dblclick', 'tr', function () {
        const row = table.row(this).data();
        if (row?.request_id) void openDetail(row.request_id);
    });
}

function bindTabs() {
    document.getElementById('approvalInboxTabs')?.addEventListener('click', event => {
        const button = event.target.closest('[data-box]');
        if (!button || button.dataset.box === box) return;
        box = button.dataset.box;
        document.querySelectorAll('#approvalInboxTabs .nav-link').forEach(tab => tab.classList.toggle('active', tab === button));
        document.getElementById('approvalInboxScopeDescription').textContent = BOX_DESCRIPTIONS[box] || '';
        table.ajax.url(`${API.list}?box=${encodeURIComponent(box)}`).load();
    });
}

function field(label, value, html = false) {
    return `<dl class="approval-detail-field"><dt>${escapeHtml(label)}</dt><dd>${html ? value : escapeHtml(value ?? '-')}</dd></dl>`;
}

function renderHeader(data) {
    const header = data.document.header;
    document.getElementById('approvalInboxModalSubtitle').textContent = `${data.document.type_name} · 요청 ${header.request_id}`;
    document.getElementById('approvalInboxHeaderFields').innerHTML = [
        field('신청번호', `#${header.document_no}`),
        field('신청 대상 직원', header.applicant_name || '-'),
        field('발의자', header.requester_name),
        field('소속', header.department_name || '-'),
        field('신청일자', formatDateDisplay(header.application_date) || header.application_date),
        field('신청제목', header.title),
        field('비고', header.description || '-'),
        field('메모', header.memo || '-'),
        field('현재 결재상태', statusBadge(header.status), true),
    ].join('');
}

function renderItems(documentData) {
    const metadata = documentData.ui || {};
    const columns = Array.isArray(metadata.item_columns) ? metadata.item_columns : [];
    document.getElementById('approvalInboxDetailSectionTitle').textContent = metadata.detail_section_title || '문서 상세내용';
    document.getElementById('approvalInboxItemHead').innerHTML = columns.map(([, label]) => `<th>${escapeHtml(label)}</th>`).join('');
    if (!documentData.detail_supported) {
        document.getElementById('approvalInboxItemBody').innerHTML = `<tr><td colspan="${Math.max(columns.length, 1)}" class="text-center text-muted py-4">이 문서유형의 상세 어댑터가 아직 연결되지 않았습니다.</td></tr>`;
        document.getElementById('approvalInboxTotals').innerHTML = '';
        document.getElementById('approvalInboxAttachments').textContent = '이 문서유형의 첨부파일 어댑터가 아직 연결되지 않았습니다.';
        return;
    }
    document.getElementById('approvalInboxItemBody').innerHTML = (documentData.items || []).length
        ? documentData.items.map(item => `<tr>${
        columns.map(([key]) => {
            const isMoney = MONEY_FIELDS.has(key) || key.endsWith('_amount');
            const value = isMoney ? formatAmount(item[key] || 0) : (item[key] ?? '-');
            return `<td class="${isMoney ? 'text-end' : ''}">${escapeHtml(value)}</td>`;
        }).join('')
    }</tr>`).join('')
        : `<tr><td colspan="${Math.max(columns.length, 1)}" class="text-center text-muted py-4">표시할 상세내용이 없습니다.</td></tr>`;
    const totals = documentData.totals || {};
    document.getElementById('approvalInboxTotals').innerHTML = (metadata.total_fields || []).map(([key, label, format]) => {
        const raw = totals[key] ?? 0;
        const value = format === 'amount' ? `${formatAmount(raw)}원` : format === 'minutes' ? `${raw}분` : `${raw}건`;
        return `<span>${escapeHtml(label)} <strong>${escapeHtml(value)}</strong></span>`;
    }).join('');
    document.getElementById('approvalInboxAttachments').textContent = documentData.attachment_supported
        ? '등록된 첨부파일이 없습니다.'
        : '공용 첨부파일 저장 구조가 아직 확정되지 않아 첨부파일을 제공하지 않습니다.';
}

function stepRows(steps) {
    const stepActor = step => {
        if (['approved', 'rejected'].includes(String(step.status || '').toLowerCase())) {
            return step.acted_by_name || '-';
        }
        if (step.approver_name) return step.approver_name;
        if (step.approver_role_name) return `${step.approver_role_name} 결재대기`;
        return '-';
    };
    return steps.map(step => `<div class="approval-step-row ${step.status === 'rejected' ? 'is-rejected' : ''}">
        <span>${escapeHtml(step.sort_no)}단계</span>
        <strong>${escapeHtml(step.step_name)}</strong>
        <span>${escapeHtml(stepActor(step))}</span>
        <span>${statusBadge(step.status)}</span>
        <span>${escapeHtml(step.action_at || '-')}</span>
        <span>${escapeHtml(step.comment || '-')}</span>
    </div>`).join('');
}

function renderSteps(data) {
    document.getElementById('approvalInboxSteps').innerHTML = stepRows(data.steps);
    const previous = data.history.filter(request => !request.is_current);
    document.getElementById('approvalInboxHistoryList').innerHTML = previous.length
        ? previous.map(request => `<article class="approval-history-entry">
            <div class="d-flex justify-content-between mb-2">
                <strong>${escapeHtml(request.requested_at)} 상신</strong>${statusBadge(request.status)}
            </div>
            <div class="approval-step-list">${stepRows(request.steps || [])}</div>
        </article>`).join('')
        : '<p class="mb-0 text-muted small">과거 상신 이력이 없습니다.</p>';
}

function renderActions(data) {
    const canAct = Boolean(data.actions?.can_act);
    document.getElementById('approvalInboxDecisionArea').classList.toggle('d-none', !canAct);
    document.getElementById('approvalInboxApprove').classList.toggle('d-none', !canAct);
    document.getElementById('approvalInboxReject').classList.toggle('d-none', !canAct);
    document.getElementById('approvalInboxComment').value = '';
}

function setActing(value) {
    acting = value;
    document.getElementById('approvalInboxApprove').disabled = value;
    document.getElementById('approvalInboxReject').disabled = value;
}

async function openDetail(requestId) {
    try {
        const result = await request(`${API.detail}?request_id=${encodeURIComponent(requestId)}`);
        detail = result.data;
        renderHeader(detail);
        renderItems(detail.document);
        renderSteps(detail);
        renderActions(detail);
        modal?.show();
    } catch (error) {
        notify('error', error.message);
    }
}

async function act(decision) {
    if (acting || !detail?.actions?.can_act) return;
    const comment = document.getElementById('approvalInboxComment').value.trim();
    if (decision === 'rejected' && comment === '') {
        notify('warning', '반려사유를 입력해 주세요.');
        document.getElementById('approvalInboxComment').focus();
        return;
    }
    const current = detail.steps.find(step => String(step.id) === String(detail.actions.step_id));
    const isFinal = current && Number(current.sort_no) === Math.max(...detail.steps.map(step => Number(step.sort_no)));
    const message = decision === 'rejected'
        ? '이 문서를 반려하시겠습니까?'
        : isFinal
            ? (detail.document.ui?.final_approval_message || '이 문서를 최종 승인하시겠습니까?')
            : '현재 결재단계를 승인하시겠습니까?';
    if (!window.confirm(message)) return;
    setActing(true);
    try {
        const result = await request(API.act, {
            method: 'POST',
            body: JSON.stringify({ step_id: detail.actions.step_id, decision, comment }),
        });
        modal?.hide();
        table.ajax.reload(null, false);
        document.dispatchEvent(new CustomEvent('approval:changed'));
        notify('success', result.user_message || result.message || '결재 처리가 완료되었습니다.');
    } catch (error) {
        notify(error.notificationType || 'error', error.message);
        if (detail?.request?.id) await openDetail(detail.request.id);
    } finally {
        setActing(false);
    }
}

document.getElementById('approvalInboxApprove')?.addEventListener('click', () => void act('approved'));
document.getElementById('approvalInboxReject')?.addEventListener('click', () => void act('rejected'));
await initTable();
bindTabs();
const initialRequestId = initialParams.get('request_id');
if (initialRequestId) {
    void openDetail(initialRequestId);
}
