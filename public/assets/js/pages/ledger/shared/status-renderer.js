export function processingType(row) {
    const explicit = String(row?.processing_type || '').trim().toUpperCase();
    if (explicit) return explicit;
    const type = String(row?.import_type || row?.source_type || '').trim().toUpperCase();
    if (type === 'CARD_HOMETAX') return 'VERIFY_ONLY';
    if (['CARD_STATEMENT', 'CARD_APPROVAL'].includes(type)) return 'TRANSACTION';
    if (type === 'BANK_TRANSACTION') return 'BANK_FLOW';
    return 'TRANSACTION';
}

export function processingLabel(row) {
        const explicit = String(row?.processing_label || '').trim();
        if (explicit) return explicit;
        return {
            TRANSACTION: '거래 생성',
            RECONCILIATION: '카드/계좌 대사',
            VERIFY_ONLY: '원본 확인',
            BANK_FLOW: '은행 흐름',
            VOUCHER: '전표 생성',
        }[processingType(row)] || '-';
    }

export function generationTarget(row) {
    const explicit = String(row?.generation_target || '').trim().toUpperCase();
    if (explicit) return explicit;
    const type = String(row?.import_type || row?.source_type || '').trim().toUpperCase();
    if (type === 'CARD_STATEMENT' || type === 'CARD_APPROVAL') return 'TRANSACTION_AND_VOUCHER';
    if (type === 'CARD_HOMETAX') return 'VERIFY_ONLY';
    if (type === 'BANK_TRANSACTION') return 'RECONCILIATION_ONLY';
    if (processingType(row) === 'TRANSACTION') return 'TRANSACTION_HEADER';
    return processingType(row);
}

export function generationObjects(row) {
    const objects = Array.isArray(row?.generation_objects)
        ? row.generation_objects
        : (Array.isArray(row?.processing_objects) ? row.processing_objects : []);
    if (objects.length > 0) return objects;
    return {
        TRANSACTION_HEADER: ['TRANSACTION_HEADER'],
        TRANSACTION_FULL: ['TRANSACTION_HEADER', 'TRANSACTION_LINE'],
        VOUCHER_HEADER: ['VOUCHER_HEADER'],
        VOUCHER_FULL: ['VOUCHER_HEADER', 'VOUCHER_LINE'],
        TRANSACTION_AND_VOUCHER: ['TRANSACTION_HEADER', 'TRANSACTION_LINE', 'VOUCHER_HEADER', 'VOUCHER_LINE'],
        RECONCILIATION_ONLY: ['RECONCILIATION'],
        VERIFY_ONLY: ['TAX_VERIFY', 'RECONCILIATION'],
    }[generationTarget(row)] || [];
}

export function generationLabel(row) {
        const explicit = String(row?.generation_label || '').trim();
        if (explicit) return explicit;
        return {
            TRANSACTION_HEADER: '거래',
            TRANSACTION_FULL: '거래',
            VOUCHER_HEADER: '전표',
            VOUCHER_FULL: '전표',
            TRANSACTION_AND_VOUCHER: '거래 + 전표',
            RECONCILIATION_ONLY: '대사 전용',
            VERIFY_ONLY: '확인 전용',
            BUSINESS_DATA: '업무정보',
            UNSUPPORTED: '미지원',
        }[generationTarget(row)] || processingLabel(row);
    }

    function generationObjectText(row) {
        return Array.from(new Set(generationObjects(row).map((object) => ({
            TRANSACTION_HEADER: '거래',
            TRANSACTION_LINE: '거래',
            VOUCHER_HEADER: '전표',
            VOUCHER_LINE: '전표',
            RECONCILIATION: '대사',
            TAX_VERIFY: '원본검증',
            BANK_FLOW: '은행',
        }[object] || object)))).join(' / ') || '-';
    }

export function createStatusRenderers(deps = {}) {
    const {
        readinessMessages = () => [],
        readinessStatus = () => '',
        transactionCreated = () => false,
        transactionCreateState = () => 'NOT_READY',
        voucherCreateState = () => 'NOT_READY',
        correctionIssueItems = () => [],
        evidenceRequiredMissing = () => [],
        derivedMissingFields = () => [],
        readinessFieldLabel = (value) => value,
        readinessCorrectionFieldSet = () => new Set(),
        escapeHtml = (value) => String(value ?? ''),
    } = deps;

    function transactionCreateStatusText(row) {
        if (transactionCreated(row)) return '생성됨';
        const status = String(row?.transaction_status || '').trim().toUpperCase();
        if (status === 'PROCESSING') return '처리중';
        if (status === 'ERROR') return '오류';
        if (status === 'DUPLICATED') return '중복';
        return '대기';
    }

    function transactionStateBadge(state) {
        if (state === 'CREATED') return '<span class="badge seed-status-badge seed-status-transaction">생성</span>';
        if (state === 'NOT_REQUIRED') return '<span class="badge text-bg-light text-dark border">해당없음</span>';
        return '<span class="badge text-bg-secondary">미생성</span>';
    }

    function voucherStateBadge(state) {
        if (state === 'CREATED') return '<span class="badge seed-status-badge seed-status-voucher">발행</span>';
        return '<span class="badge text-bg-primary">준비</span>';
    }

    function isBundledVoucher(row = {}) {
        return row?.is_bundled_voucher === true
            || String(row?.is_bundled_voucher || '').trim() === '1'
            || String(row?.voucher_import_type || row?.import_type || '').trim().toUpperCase() === 'BUNDLED_EVIDENCE'
            || String(row?.bundled_voucher_id || '').trim() !== '';
    }

    function bundledVoucherTitle(row = {}) {
        const voucherNo = String(row?.bundled_voucher_no || row?.voucher_no || '').trim();
        const sourceCount = Number(row?.bundled_voucher_source_count || 0);
        const summary = String(row?.bundled_voucher_summary || '').trim();
        const parts = ['묶음 전표'];
        if (voucherNo) parts.push(voucherNo);
        if (sourceCount > 0) parts.push(`${sourceCount.toLocaleString('ko-KR')}건 연결`);
        return [parts.join(' · '), summary].filter(Boolean).join('\n');
    }

    function bundledVoucherBadge(row = {}) {
        if (!isBundledVoucher(row)) return '';
        const label = String(row?.bundled_voucher_label || '').trim();
        const text = label ? `묶음 ${label}` : '묶음';
        return `<span class="badge seed-status-badge seed-status-bundled" title="${escapeHtml(bundledVoucherTitle(row))}">${escapeHtml(text)}</span>`;
    }

    function voucherCreateStatusText(row) {
        const state = voucherCreateState(row);
        if (state === 'CREATED') return '전표 생성 완료';
        if (state === 'READY') return '전표 생성 가능';
        return '전표 생성 대기';
    }

    function transactionCreateStatusBadge(row) {
        const state = transactionCreateState(row);
        const reason = state === 'NOT_READY' ? readinessMessages(row).join('\n') : '';
        return `<span title="${escapeHtml(reason)}">${transactionStateBadge(state)}</span>`;
    }

    function voucherCreateStatusBadge(row) {
        const state = voucherCreateState(row);
        const raw = String(row?.voucher_status || '').trim();
        const reason = state === 'NOT_READY'
            ? (raw || (readinessStatus(row) === 'READY' ? '분개라인 미확정' : readinessMessages(row).join('\n')))
            : raw;
        const title = [reason, isBundledVoucher(row) ? bundledVoucherTitle(row) : '']
            .map((item) => String(item || '').trim())
            .filter(Boolean)
            .join('\n');
        return `<span class="seed-voucher-status-stack" title="${escapeHtml(title)}">${voucherStateBadge(state)}${bundledVoucherBadge(row)}</span>`;
    }

    function correctionMissingSummary(row) {
        const issues = correctionIssueItems(row);
        const issueCount = issues.length;
        if (issueCount === 0) {
            return '<span class="badge seed-status-badge seed-status-evidence">완료</span>';
        }
        if (issueCount === 1 && issues[0]?.field === 'line_no') {
            return '<span class="badge text-bg-info">분개미확정</span>';
        }
        return `<span class="badge text-bg-warning">보정필요 ${issueCount.toLocaleString('ko-KR')}건</span>`;

        const reasons = [];
        reasons.push(...evidenceRequiredMissing(row));
        if (transactionCreateState(row) === 'NOT_READY') {
            reasons.push(...readinessMessages(row));
            derivedMissingFields(row).forEach((field) => reasons.push(readinessFieldLabel(field)));
        }
        if (voucherCreateState(row) === 'NOT_READY') {
            const voucherStatus = String(row?.voucher_status || '').trim().toUpperCase();
            if (readinessStatus(row) !== 'READY') {
                reasons.push(...readinessMessages(row));
            } else if (['', 'WAITING', 'NONE'].includes(voucherStatus)) {
                reasons.push('분개라인 미확정');
            } else if (['ERROR', 'FAILED'].includes(voucherStatus)) {
                reasons.push(row?.error_message || '전표 생성 오류');
            } else {
                reasons.push(row?.voucher_status || '전표 생성 상태');
            }
        }

        const uniqueReasons = Array.from(new Set(reasons.map((item) => String(item || '').trim()).filter(Boolean)));
        if (uniqueReasons.length === 0) {
            return '<span class="badge text-bg-success">완료</span>';
        }
        const text = uniqueReasons.join(', ');
        return `<span class="seed-missing-summary-wrap" title="${escapeHtml(text)}"><span class="badge text-bg-warning">보정필요</span><span class="seed-missing-summary-text">${escapeHtml(text)}</span></span>`;
    }

    function evidenceStatusBadge(row = {}) {
        const statusIssues = correctionIssueItems(row).map((item) => item.message);
        if (statusIssues.length === 0) {
            return '<span class="badge seed-status-badge seed-status-evidence">완료</span>';
        }
        const statusTitle = statusIssues.join(', ');
        return `<span class="badge text-bg-secondary" title="${escapeHtml(statusTitle)}">미완료</span>`;

        const missing = correctionIssueItems(row).map((item) => item.message);
        if (missing.length === 0) {
            return '<span class="badge text-bg-success">완료</span>';
        }

        const title = missing.join(', ');
        return `<span class="badge text-bg-secondary" title="${escapeHtml(title)}">미완료</span>`;
    }

    function manageButton(row = {}) {
        const rowId = row.id || row.processing_item_id || '';
        return `<button type="button" class="btn btn-outline-primary btn-sm seed-row-edit-btn" data-id="${escapeHtml(rowId)}">수정</button>`;
    }
    function correctionIssueLinksHtml(row = {}) {
        return correctionIssueItems(row).map((item) => `
            <li>
                <button type="button"
                        class="readiness-correction-link"
                        data-correction-field="${escapeHtml(item.field || '')}">
                    ${escapeHtml(item.message)}
                </button>
            </li>
        `).join('');
    }

    function readinessStageBadge(row, stage) {
        if (stage?.workspace) return '';
        const missing = readinessCorrectionFieldSet(row);
        const targetFields = Array.isArray(stage.requiredFields) && stage.requiredFields.length > 0
            ? stage.requiredFields
            : stage.fields;
        const count = targetFields.filter((field) => missing.has(field)).length;
        if (count > 0) return `<span class="badge text-bg-warning ms-1">${count}</span>`;
        return '<span class="badge text-bg-success ms-1">OK</span>';
    }

    return {
        transactionCreateStatusText,
        transactionStateBadge,
        voucherStateBadge,
        voucherCreateStatusText,
        transactionCreateStatusBadge,
        voucherCreateStatusBadge,
        correctionMissingSummary,
        evidenceStatusBadge,
        manageButton,
        correctionIssueLinksHtml,
        readinessStageBadge,
    };
}

export { createStatusRenderers as createBadgeRenderers };
