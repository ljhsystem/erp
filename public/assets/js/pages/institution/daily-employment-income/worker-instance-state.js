const amount = value => Number(String(value ?? 0).replaceAll(',', '')) || 0;

const calculationWorkday = day => ({
    work_date: day.work_date || '',
    actual_work_minutes: day.actual_work_minutes ?? null,
    daily_rate_amount: amount(day.daily_rate_amount),
    taxable_additional_amount: amount(day.taxable_additional_amount ?? day.allowance_amount),
    non_taxable_additional_amount: amount(day.non_taxable_additional_amount ?? day.non_taxable_amount),
    non_taxable_reason: day.non_taxable_reason ?? null,
});

export function workerCalculationSourceKey(group, item) {
    return JSON.stringify({
        group_key: group.client_key,
        business_unit: group.business_unit || '',
        project_id: group.project_id || null,
        work_team_id: group.work_team_id || null,
        worker_key: item.client_key,
        worker_client_id: item.worker_client_id || '',
        work_type_code: item.work_type_code || '',
        work_description: item.work_description || '',
        workdays: [...item.workdays.values()].map(calculationWorkday).sort((left, right) => left.work_date.localeCompare(right.work_date)),
        institution_line_overrides: (item.institution_line_overrides || []).map(row => ({
            line_code: row.line_code, final_amount: amount(row.final_amount),
            adjustment_reason: row.adjustment_reason || null,
            actual_application_source_code: row.actual_application_source_code || null,
        })).sort((left, right) => String(left.line_code).localeCompare(String(right.line_code))),
    });
}

export function copyWorkerInstance(item, createKey) {
    const workdays = new Map([...item.workdays.entries()].map(([date, day]) => [date, {
        client_key: createKey(),
        work_date: day.work_date,
        actual_work_minutes: day.actual_work_minutes ?? null,
        work_quantity: day.work_quantity ?? 1,
        daily_rate_amount: day.daily_rate_amount ?? null,
        taxable_additional_amount: day.taxable_additional_amount ?? day.allowance_amount ?? null,
        non_taxable_additional_amount: day.non_taxable_additional_amount ?? day.non_taxable_amount ?? null,
        non_taxable_reason: day.non_taxable_reason ?? null,
        calculation_note: day.calculation_note ?? null,
    }]));
    return {
        client_key: createKey(), sort_no: 0,
        worker_client_id: item.worker_client_id || '', worker_name: item.worker_name || '',
        worker_client_type: item.worker_client_type || '', worker_client_type_name: item.worker_client_type_name || '',
        work_type_code: item.work_type_code || '', work_type_name: item.work_type_name || '',
        work_description: item.work_description || '', daily_rate_amount: item.daily_rate_amount ?? null,
        workdays, calculation: null, calculation_source_key: '', calculation_request_version: 0,
        calculation_state: 'idle', calculation_error: '',
        institution_line_overrides: (item.institution_line_overrides || []).map(row => ({ ...row })),
    };
}

export function resetWorkerCalculationState(item, { resetDraftAdjustments = false } = {}) {
    item.calculation = null;
    item.calculation_source_key = '';
    item.calculation_state = 'idle';
    item.calculation_error = '';
    item.calculation_request_version = (item.calculation_request_version || 0) + 1;
    [
        'coverage_id', 'social_insurance_workplace_id', 'insurance_application_amounts',
        'insurance_overrides', 'settlement_lines', 'actual_application_source_code_id',
        'calculation_revision_id',
    ].forEach(key => { delete item[key]; });
    item.workdays.forEach(day => {
        ['coverage_id', 'social_insurance_workplace_id', 'insurance_results', 'insurance_overrides', 'settlement_lines'].forEach(key => { delete day[key]; });
        if (resetDraftAdjustments) day.institution_line_overrides = [];
    });
    if (resetDraftAdjustments) item.institution_line_overrides = [];
}

export function duplicateWorkerSummary(groups) {
    let duplicateItemCount = 0;
    groups.forEach(group => {
        const counts = new Map();
        group.items.forEach(item => {
            const workerId = String(item.worker_client_id || '');
            if (workerId === '') return;
            counts.set(workerId, (counts.get(workerId) || 0) + 1);
        });
        counts.forEach(count => { duplicateItemCount += Math.max(0, count - 1); });
    });
    return duplicateItemCount;
}

export function calculationTotals(groups) {
    const totals = {
        item_count: 0, unique_worker_count: 0, duplicate_item_count: duplicateWorkerSummary(groups),
        total_work_days: 0, total_gross_amount: 0, total_deduction_amount: 0,
        total_net_payment_amount: 0, total_employer_burden_amount: 0, unresolved_item_count: 0,
    };
    const workers = new Set();
    groups.forEach(group => group.items.forEach(item => {
        totals.item_count += 1;
        if (item.worker_client_id) workers.add(String(item.worker_client_id));
        const summary = item.calculation?.summary;
        if (!summary) { totals.unresolved_item_count += 1; return; }
        ['total_work_days', 'total_gross_amount', 'total_deduction_amount', 'total_net_payment_amount', 'total_employer_burden_amount']
            .forEach(key => { totals[key] += amount(summary[key]); });
    }));
    totals.unique_worker_count = workers.size;
    return totals;
}

export function selectionAfterDelete(items, deletedIndex) {
    return items[deletedIndex - 1] || items[deletedIndex] || null;
}
