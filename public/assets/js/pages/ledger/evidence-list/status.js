export function createEvidenceStatusModule({
    state,
    escapeHtml,
    badge,
    mapped,
    editFieldKey,
    businessRefPickerForColumn,
    bankCodePickerForColumn,
    compareFormatColumns,
    normalizeEvidenceType,
    evidenceTypePolicy,
}) {
    function transactionWorkflowRequired(row = {}) {
        const type = normalizeEvidenceType(row.import_type || row.source_type || state.currentType || '');
        return evidenceTypePolicy(type)?.transactionWorkflowRequired !== false;
    }

    function processStatus(row = {}) {
        return String(row.process_status || row.status || '').toUpperCase();
    }

    function renderSeedStatus(row = {}) {
        const status = processStatus(row);
        const map = {
            READY: 'text-bg-primary',
            PROCESSED: 'text-bg-success',
            ERROR: 'text-bg-danger',
            DUPLICATED: 'text-bg-warning',
            DELETED: 'text-bg-secondary',
        };
        return badge(status || '-', map[status] || 'text-bg-light');
    }

    function renderTransactionStatus(row = {}) {
        if (String(row.transaction_id || '').trim() !== '') {
            return badge('생성됨', 'text-bg-success');
        }
        if (processStatus(row) === 'ERROR') {
            return badge('생성오류', 'text-bg-danger');
        }
        return badge('미생성', 'text-bg-primary');
    }

    function renderVoucherStatus(row = {}) {
        const status = String(row.voucher_status || '').trim().toUpperCase();
        if (['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'].includes(status)) {
            return badge('전표생성완료', 'text-bg-success');
        }
        if (status === 'READY') {
            return badge('분개라인확인', 'text-bg-primary');
        }
        if (['WAITING', 'NONE', ''].includes(status)) {
            return badge('전표생성대기', 'text-bg-warning');
        }
        if (['ERROR', 'FAILED'].includes(status)) {
            return badge('전표오류', 'text-bg-danger');
        }
        if (['DUPLICATED', 'DUPLICATE'].includes(status)) {
            return badge('중복', 'text-bg-secondary');
        }
        return badge(row.voucher_status || '전표생성대기', 'text-bg-info');
    }

    function renderReviewStatus(row = {}) {
        const status = processStatus(row);
        if (status === 'ERROR') return badge('검토필요', 'text-bg-danger');
        if (status === 'DUPLICATED') return badge('중복검토', 'text-bg-warning');
        return badge('정상', 'text-bg-success');
    }

    function renderRecommendStatus(row = {}) {
        if (String(row.transaction_id || '').trim() !== '') {
            return badge('추천 처리', 'text-bg-success');
        }
        if (processStatus(row) === 'READY') {
            return badge('추천대기', 'text-bg-primary');
        }
        return badge('확인필요', 'text-bg-secondary');
    }

    function renderUserModified(row = {}) {
        const payload = mapped(row);
        return payload.is_user_modified || payload.user_modified_at
            ? badge('있음', 'text-bg-warning')
            : badge('없음', 'text-bg-light');
    }

    function workflowStateBadge(label, status) {
        const cls = status === '생성'
            ? 'text-bg-success'
            : (status === 'READY' ? 'text-bg-primary' : 'text-bg-secondary');
        return `<span class="badge ${cls}">${escapeHtml(label)}(${escapeHtml(status)})</span>`;
    }

    function workflowStatusBadge(status) {
        const cls = status === '생성'
            ? 'text-bg-success'
            : (status === 'READY' ? 'text-bg-primary' : (status === 'NOT_REQUIRED' ? 'text-bg-light text-dark border' : 'text-bg-secondary'));
        return `<span class="badge ${cls}">${escapeHtml(status)}</span>`;
    }

    function transactionWorkflowState(row = {}) {
        if (!transactionWorkflowRequired(row)) {
            return 'NOT_REQUIRED';
        }
        const transactionStatus = String(row.transaction_status || row.process_status || '').trim().toUpperCase();
        if (String(row.transaction_id || '').trim() !== ''
            || ['CREATED', 'PROCESSED', 'DONE', 'COMPLETED'].includes(transactionStatus)) {
            return '생성';
        }
        if (['READY', 'NONE', ''].includes(transactionStatus) && !row.error_message && processStatus(row) !== 'ERROR') {
            return 'READY';
        }
        return 'NOT_READY';
    }

    function voucherWorkflowState(row = {}) {
        const status = String(row.voucher_status || '').trim().toUpperCase();
        if (['CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'].includes(status)) {
            return '생성';
        }
        if (status === 'READY') {
            return 'READY';
        }
        return 'NOT_READY';
    }

    function renderWorkflowStatus(row = {}) {
        return `
            <div class="d-inline-flex align-items-center gap-1 flex-nowrap">
                ${workflowStateBadge('거래', transactionWorkflowState(row))}
                <span class="text-muted">+</span>
                ${workflowStateBadge('전표', voucherWorkflowState(row))}
            </div>
        `;
    }

    function renderTransactionWorkflowStatus(row = {}) {
        return workflowStatusBadge(transactionWorkflowState(row));
    }

    function renderVoucherWorkflowStatus(row = {}) {
        return workflowStatusBadge(voucherWorkflowState(row));
    }

    function splitGenerationRows(rows = []) {
        return rows.filter((row) => row?.processing_is_child || !row?.processing_has_children);
    }

    function updateSummary(rows = []) {
        const generationRows = splitGenerationRows(rows);
        const summary = {
            total: rows.length,
            transactionPending: generationRows.filter((row) => String(row.transaction_id || '').trim() === '').length,
            transactionCreated: generationRows.filter((row) => String(row.transaction_id || '').trim() !== '').length,
            voucherReview: generationRows.filter((row) => String(row.transaction_id || '').trim() !== '').length,
            errors: rows.filter((row) => processStatus(row) === 'ERROR' || row.error_message).length,
            duplicates: rows.filter((row) => processStatus(row) === 'DUPLICATED').length,
        };

        Object.entries(summary).forEach(([key, value]) => {
            const el = document.querySelector(`[data-summary="${key}"]`);
            if (el) el.textContent = value.toLocaleString('ko-KR');
        });
    }

    function renderFieldBadge(column = {}) {
        const meta = fieldClassification(column);
        return `<span class="evidence-field-badge evidence-field-badge-${escapeHtml(meta.tone)}" title="${escapeHtml(meta.tooltip)}">${escapeHtml(meta.label)}</span>`;
    }

    function fieldClassification(column = {}) {
        const key = editFieldKey(column);
        const field = String(column.system_field_name || '').trim();
        const group = String(column.system_field_group || '').trim();
        const sourceTable = String(column.source_table || '').trim();
        const codeGroup = String(column.code_group || '').trim();

        if (field === 'transaction_date' || key === 'transaction_date' || sourceTable === 'standard_date') {
            return {
                tone: 'normalized',
                label: '정규화정보',
                tooltip: '정규화필드(transaction_date)',
            };
        }

        const codeFields = new Set([
            'business_unit',
            'transaction_type',
            'transaction_direction',
            'import_type',
            'source_type',
            'currency',
            'currency_code',
            'tax_invoice_category',
            'tax_invoice_type',
            'issue_type',
            'receipt_claim_type',
            'cash_receipt_transaction_type',
            'card_transaction_type',
            'deduction_status',
            'issue_method',
            'merchant_type',
            'income_type',
            'construction_type',
            'cost_type',
            'client_type',
            'payment_term',
        ]);
        if (codeGroup !== '' || codeFields.has(field) || !!bankCodePickerForColumn(column)) {
            return {
                tone: 'code',
                label: '코드관리',
                tooltip: codeGroup ? `system_codes(${codeGroup})` : 'system_codes',
            };
        }

        const basicFields = new Set([
            'client_name',
            'project_name',
            'employee_name',
            'bank_account_name',
            'card_name',
        ]);
        if (group.includes('기초정보') || basicFields.has(field) || (Number(column.is_reference_column || 0) === 1 && businessRefPickerForColumn(column)) || !!businessRefPickerForColumn(column)) {
            return {
                tone: 'basic',
                label: '기초정보',
                tooltip: '마스터 참조 필드',
            };
        }

        return {
            tone: 'raw',
            label: '원본정보',
            tooltip: '실제 업로드 원본 데이터',
        };
    }

    function fieldClassificationRank(column = {}) {
        const tone = fieldClassification(column).tone;
        if (tone === 'code') return 0;
        if (tone === 'basic') return 1;
        if (tone === 'normalized' || tone === 'raw') return 2;
        return 3;
    }

    function compareFieldDisplayOrder(a, b) {
        const rankCompare = fieldClassificationRank(a) - fieldClassificationRank(b);
        if (rankCompare !== 0) return rankCompare;
        return compareFormatColumns(a, b);
    }

    function infoColumnTone(column = {}) {
        return fieldClassification(column).tone;
    }

    return {
        processStatus,
        renderSeedStatus,
        renderTransactionStatus,
        renderVoucherStatus,
        renderReviewStatus,
        renderRecommendStatus,
        renderUserModified,
        workflowStateBadge,
        workflowStatusBadge,
        transactionWorkflowState,
        voucherWorkflowState,
        renderWorkflowStatus,
        renderTransactionWorkflowStatus,
        renderVoucherWorkflowStatus,
        splitGenerationRows,
        updateSummary,
        renderFieldBadge,
        fieldClassification,
        fieldClassificationRank,
        compareFieldDisplayOrder,
        infoColumnTone,
    };
}
