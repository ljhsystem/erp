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
    function processStatus(row = {}) {
        return String(row.process_status || row.status || '').toUpperCase();
    }

    function updateSummary(rows = []) {
        const summary = {
            total: rows.length,
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
            'operation_type',
            'transaction_direction',
            'import_type',
            'source_type',
            'currency',
            'currency_code',
            'tax_invoice_category',
            'tax_invoice_type',
            'issue_type',
            'receipt_claim_type',
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
        updateSummary,
        renderFieldBadge,
        fieldClassification,
        fieldClassificationRank,
        compareFieldDisplayOrder,
        infoColumnTone,
    };
}
