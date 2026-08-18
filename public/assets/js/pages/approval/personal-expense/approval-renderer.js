function approvalStepState(status = '') {
    const normalized = String(status || '').trim().toLowerCase();
    if (['approved', 'completed'].includes(normalized)) return { className: 'done', icon: '✓', label: '승인 완료' };
    if (normalized === 'pending') return { className: 'current', icon: '', label: '결재 진행중' };
    if (normalized === 'rejected') return { className: 'rejected', icon: '!', label: '반려' };
    if (['cancelled', 'withdrawn'].includes(normalized)) return { className: 'cancelled', icon: '−', label: '취소' };
    return { className: 'pending', icon: '', label: '대기' };
}

export function createApprovalRenderer({ escapeHtml, statusBadge }) {
    function approvalStepTimeline(steps = []) {
        if (!Array.isArray(steps) || steps.length === 0) {
            return '<p class="expense-approval-empty">결재요청 후 결재 템플릿의 단계가 표시됩니다.</p>';
        }
        const timeline = steps.map((step, index) => {
            const state = approvalStepState(step.status);
            const finalClass = state.className === 'done' && index === steps.length - 1 ? ' final' : '';
            const actionAt = String(step.timeline_action_at || '').slice(0, 16).replace('T', ' ');
            const userName = String(step.timeline_user_name || '-').trim() || '-';
            const userLabel = String(step.timeline_user_label || '처리자').trim() || '처리자';
            const resultLabel = String(step.timeline_result || state.label).trim() || state.label;
            const comment = String(step.comment || '').trim();
            return `<div class="expense-approval-timeline-step ${state.className}${finalClass}">
                <div class="expense-approval-timeline-node">${state.icon}</div>
                <div class="expense-approval-timeline-label">${escapeHtml(step.sort_no)}단계 ${escapeHtml(step.step_name || '-')}</div>
                <div class="expense-approval-timeline-state">${escapeHtml(resultLabel)}</div>
                <div class="expense-approval-timeline-meta">
                    <span><strong>${escapeHtml(userLabel)}</strong> ${escapeHtml(userName)}</span>
                    ${actionAt ? `<span>${escapeHtml(actionAt)}</span>` : ''}
                    ${comment ? `<span>${escapeHtml(comment)}</span>` : ''}
                </div>
            </div>`;
        }).join('');
        return `<div class="expense-approval-timeline"
                     style="--approval-step-count:${steps.length};--approval-timeline-min-width:${Math.max(steps.length * 150, 320)}px">
            ${timeline}
        </div>`;
    }

    function approvalRequestAction(action = null) {
        if (!action || typeof action !== 'object') return '';
        const result = String(action.result || '').trim();
        if (!result) return '';
        const actorName = String(action.actor_name || '-').trim() || '-';
        const actedAt = String(action.acted_at || '').trim();
        return `<div class="expense-approval-request-action">
            <strong>${escapeHtml(result)}</strong>
            <span><b>처리자</b> ${escapeHtml(actorName)}</span>
            ${actedAt ? `<span>${escapeHtml(actedAt)}</span>` : ''}
        </div>`;
    }

    return function renderApprovalProgress(data = {}) {
        document.getElementById('expenseApprovalSteps').innerHTML = `
            ${approvalRequestAction(data.approval?.request_action)}
            ${approvalStepTimeline(data.approval_steps || [])}
        `;
        const rejectionElement = document.getElementById('expenseApprovalRejection');
        const rejectionReason = String(data.approval?.rejection_reason || '').trim();
        rejectionElement.classList.toggle('d-none', rejectionReason === '');
        rejectionElement.innerHTML = rejectionReason === ''
            ? ''
            : `<span class="expense-approval-rejection-label">반려 사유</span><p>${escapeHtml(rejectionReason)}</p>`;
        const history = (data.approval_history || []).filter(request => !request.is_current);
        document.getElementById('expenseApprovalHistory').innerHTML = history.length
            ? history.map(request => `<article class="expense-approval-history-entry">
                <div class="d-flex justify-content-between"><strong>${escapeHtml(request.requested_at || '-')} 상신</strong>${statusBadge(request.status)}</div>
                ${approvalRequestAction(request.request_action)}
                <div class="expense-approval-timeline-wrap">${approvalStepTimeline(request.steps || [])}</div>
            </article>`).join('')
            : '<p class="mb-0 text-muted small">과거 상신 이력이 없습니다.</p>';
    };
}
