const evidenceKey = (row = {}) => `${String(row.import_type || '').toUpperCase()}:${String(row.evidence_id || row.id || '')}`;

export function registerTransactionRecommendation(ctx) {
    const labels = { client_id: '거래처', project_id: '프로젝트', bank_account_id: '계좌', card_id: '카드', team_id: '팀', employee_id: '직원', transaction_date: '거래일', business_unit: '사업구분', transaction_direction: '거래구분', operation_type: '업무유형', description: '적요', supply_amount: '공급가액', settlement_amount: '정산금액', final_amount: '합계금액' };
    const names = { client_id: 'client_name', project_id: 'project_name', bank_account_id: 'bank_account_name', card_id: 'card_name', team_id: 'team_name', employee_id: 'employee_name' };
    const text = (value) => String(value ?? '').trim();
    const safe = (value) => ctx.escapeHtml?.(text(value)) ?? text(value);
    const elementIds = { description: 'transaction_description', supply_amount: 'transaction_supply_amount', settlement_amount: 'transaction_settlement_amount', final_amount: 'transaction_final_amount' };
    const current = (field) => text(document.getElementById(elementIds[field] || field)?.value);
    const candidates = (section, field) => {
        const values = new Map();
        ctx.linkedEvidences.forEach((evidence) => {
            const source = evidence[section] || {};
            const value = text(source[field]);
            if (!value || Number(value) === 0) return;
            values.set(value, { value, label: text(source[names[field]]) || value, source: evidenceKey(evidence) });
        });
        return [...values.values()];
    };
    const fields = (section, fieldNames) => fieldNames.map((field) => {
        const choices = candidates(section, field);
        const value = current(field);
        const status = choices.length > 1 ? 'CONFLICT' : choices.length === 0 ? 'NONE' : value === choices[0].value ? 'SAME' : value === '' || Number(value) === 0 ? 'NEW' : 'CONFLICT';
        return { field, label: labels[field], current: value, candidates: choices, status };
    }).filter((item) => item.status !== 'NONE');
    const build = () => ({
        generated_at: new Date().toISOString(),
        evidence_count: ctx.linkedEvidences.length,
        business: fields('business_recommendation', Object.keys(names)),
        overview: fields('overview_recommendation', ['transaction_date', 'business_unit', 'transaction_direction', 'operation_type', 'description', 'supply_amount', 'settlement_amount', 'final_amount']),
        items: ctx.linkedEvidences.map((row) => row.transaction_item ? { ...row.transaction_item, evidence_identity: { import_type: row.import_type, evidence_id: row.evidence_id } } : null).filter(Boolean),
        settlements: ctx.linkedEvidences.flatMap((row) => row.transaction_settlements || []),
    });
    const renderRows = (rows) => rows.map((item) => `<tr><th>${safe(item.label)}</th><td>${safe(item.current || '비어 있음')}</td><td>${item.candidates.map((choice) => safe(choice.label)).join(', ') || '-'}</td><td>${item.status === 'SAME' ? '동일' : item.status === 'NEW' ? '신규 추천' : '확인 필요'}</td></tr>`).join('');
    function refreshRecommendation() {
        const recommendation = build();
        ctx.transactionRecommendation = recommendation;
        ctx.recommendationCardEl?.classList.toggle('d-none', recommendation.evidence_count === 0);
        if (ctx.recommendationSummaryEl) ctx.recommendationSummaryEl.textContent = `연결 증빙 ${recommendation.evidence_count}건 · 업무분류 ${recommendation.business.length}건 · 거래개요 ${recommendation.overview.length}건 · 거래내역 ${recommendation.items.length}줄 · 거래정산 ${recommendation.settlements.length}줄`;
        const conflict = [...recommendation.business, ...recommendation.overview].some((item) => item.status === 'CONFLICT');
        if (ctx.recommendationStatusEl) ctx.recommendationStatusEl.textContent = conflict ? '재검토 필요' : '추천 생성됨';
        if (ctx.recommendationDetailsEl) ctx.recommendationDetailsEl.innerHTML = `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>항목</th><th>현재값</th><th>추천값</th><th>상태</th></tr></thead><tbody>${renderRows([...recommendation.business, ...recommendation.overview])}</tbody></table></div><p>거래내역 ${recommendation.items.length}줄 / 거래정산 ${recommendation.settlements.length}줄</p>`;
    }
    function applyFields(rows) {
        const setters = { client_id: ctx.setClientSelectValue, project_id: ctx.setProjectSelectValue, bank_account_id: ctx.setBankAccountValue, card_id: ctx.setCardValue, team_id: ctx.setTeamValue, employee_id: ctx.setEmployeeValue };
        rows.forEach((item) => {
            if (item.candidates.length !== 1) return;
            const choice = item.candidates[0];
            if (setters[item.field]) setters[item.field]?.(choice.value, choice.label);
            else if (item.field === 'business_unit') ctx.setBusinessUnitValue(choice.value);
            else if (item.field === 'transaction_direction') ctx.setTransactionDirectionValue(choice.value);
            else if (item.field === 'operation_type') ctx.setOperationTypeValue(choice.value);
            else { const el = document.getElementById(elementIds[item.field] || item.field); if (el) el.value = choice.value; }
        });
    }
    const lineKey = (row) => `${row.item_name || ''}:${Number(row.supply_amount || 0)}:${row.description || ''}`;
    const settlementKey = (row) => `${row.settlement_type || ''}:${row.amount_sign || ''}:${Number(row.amount || 0)}:${row.description || row.settlement_description || ''}`;
    const mergeUnique = (currentRows, recommendedRows, key) => { const keys = new Set(currentRows.map(key)); return [...currentRows, ...recommendedRows.filter((row) => !keys.has(key(row)))]; };
    ctx.recommendationCardEl?.addEventListener('click', (event) => {
        const action = event.target.closest('[data-recommendation-action]')?.dataset.recommendationAction;
        if (!action) return;
        if (action === 'toggle') { ctx.recommendationDetailsEl?.classList.toggle('d-none'); return; }
        if (action === 'ignore') { ctx.recommendationCardEl.classList.add('d-none'); return; }
        const button = event.target.closest('[data-recommendation-action]');
        if (!ctx.transactionRecommendation) refreshRecommendation();
        try {
            button.disabled = true;
            if (action === 'business' || action === 'all') applyFields(ctx.transactionRecommendation.business);
            if (action === 'overview' || action === 'all') applyFields(ctx.transactionRecommendation.overview);
            if (action === 'items' || action === 'all') ctx.setLines(mergeUnique(ctx.lineGrid?.getSourceData() || [], ctx.transactionRecommendation.items, lineKey));
            if (action === 'settlements' || action === 'all') ctx.setSettlements(mergeUnique(ctx.settlementRows || [], ctx.transactionRecommendation.settlements, settlementKey));
            ctx.calculateTotals?.();
            refreshRecommendation();
            ctx.notify?.('success', action === 'all' ? '거래추천 전체를 적용했습니다.' : '선택한 거래추천을 적용했습니다.');
        } catch (_error) {
            ctx.notify?.('error', '거래추천 적용 중 오류가 발생했습니다.');
        } finally {
            button.disabled = false;
        }
    });
    Object.assign(ctx, { refreshRecommendation });
}
