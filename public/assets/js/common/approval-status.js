const APPROVAL_STATUS = Object.freeze({
    pending: ['결재 대기', 'text-bg-warning'],
    in_progress: ['결재 진행', 'text-bg-primary'],
    approved: ['승인 완료', 'text-bg-success'],
    rejected: ['반려', 'text-bg-danger'],
    withdrawn: ['회수', 'text-bg-secondary'],
    cancelled: ['취소', 'text-bg-secondary'],
    waiting: ['대기', 'text-bg-secondary'],
    skipped: ['건너뜀', 'text-bg-light text-dark'],
});

export function approvalStatusMeta(status) {
    const normalized = String(status || '').trim().toLowerCase();
    const [label, className] = APPROVAL_STATUS[normalized] || [status || '-', 'text-bg-secondary'];
    return { status: normalized, label, className };
}

export function approvalStatusLabel(status) {
    return approvalStatusMeta(status).label;
}

export function approvalStatusBadge(status, escapeHtml = value => String(value ?? '')) {
    const meta = approvalStatusMeta(status);
    return `<span class="badge ${meta.className}">${escapeHtml(meta.label)}</span>`;
}
