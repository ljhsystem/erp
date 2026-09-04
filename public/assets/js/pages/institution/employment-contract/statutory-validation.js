export function createStatutoryValidation({ apiUrl, optionsUrl, request, escapeHtml, modalElement }) {
    let generation = 0;
    let activeContractId = '';
    let pending = null;
    let minimumWageGuide = null;

    const host = () => document.getElementById('employmentStatutoryProjection');
    const render = (projection = null, loading = false) => {
        const element = host();
        if (!element) return;
        if (loading) {
            element.innerHTML = '<div class="text-muted">계약 적용일 기준 법정기준을 확인하고 있습니다.</div>';
            return;
        }
        if (!projection) {
            element.innerHTML = '<div class="text-muted">계약을 저장하면 법정기준 검증결과를 확인할 수 있습니다.</div>';
            return;
        }
        const number = value => value === null || value === undefined
            ? '-'
            : Number(value).toLocaleString('ko-KR', { maximumFractionDigits: 4 });
        const statusClass = projection.status === 'COMPLIANT'
            ? 'text-bg-success'
            : (projection.status === 'WARNING' ? 'text-bg-warning' : 'text-bg-secondary');
        const sourceRows = (projection.standard?.sources || []).map(source => `
            <li>${escapeHtml(source.organization_name || '-')} · ${escapeHtml(source.source_name || '-')}
                ${source.law_name ? ` · ${escapeHtml(source.law_name)}` : ''}
                ${source.source_url ? ` · <a href="${escapeHtml(source.source_url)}" target="_blank" rel="noopener">공식자료</a>` : ''}</li>`).join('');
        const unavailableChecks = Object.entries(projection.checks || {})
            .filter(([key]) => key !== 'minimum_wage')
            .map(([, check]) => `<li>${escapeHtml(check.message)}</li>`).join('');
        element.innerHTML = `
            <div class="employment-statutory-grid">
                <span>적용 기준일</span><strong>${escapeHtml(projection.basis_date || '-')}</strong>
                <span>당시 최저임금</span><strong>${number(projection.minimum_wage)}원/시간</strong>
                <span>${escapeHtml(projection.contract_calculation_rate_label)}</span><strong>${number(projection.contract_calculation_rate)}원</strong>
                <span>최저임금 검증</span><strong><span class="badge ${statusClass}">${escapeHtml(projection.status_label)}</span></strong>
                <span>차이</span><strong>${number(projection.difference)}원/시간</strong>
            </div>
            <p class="employment-statutory-message mb-2">${escapeHtml(projection.message)}</p>
            <p class="mb-2"><strong>${escapeHtml(projection.snapshot_notice)}</strong></p>
            <details>
                <summary>Revision / Source 및 미검증 항목 상세</summary>
                <div class="small mt-2">Revision ${escapeHtml(projection.standard?.revision || '-')} · ${escapeHtml(projection.standard?.effective_from || '-')} ~ ${escapeHtml(projection.standard?.effective_to || '계속')}</div>
                <ul class="small mb-1">${sourceRows || '<li>연결된 공식 Source가 없습니다.</li>'}</ul>
                <ul class="small text-muted mb-0">${unavailableChecks}</ul>
            </details>`;
    };

    const reset = () => {
        generation += 1;
        activeContractId = '';
        pending = null;
        minimumWageGuide = null;
        render();
    };

    const minimumWageGuideHtml = () => minimumWageGuide?.status === 'READY'
        ? `<div class="alert alert-info py-2 px-3 mb-3" role="note"><strong>기준단가 입력 참고</strong><br>${escapeHtml(minimumWageGuide.effective_from)} 기준 시간급 최저임금은 <strong>${escapeHtml(Number(minimumWageGuide.hourly_wage).toLocaleString('ko-KR'))}원</strong>입니다. 계약 단가는 근로시간과 해당 급여항목의 최저임금 산입 여부를 함께 확인해 입력하세요. <a href="/main/settings/standard/statutory-standards" target="_blank" rel="noopener">법정기준관리</a></div>`
        : `<div class="alert alert-secondary py-2 px-3 mb-3" role="note"><strong>기준단가 입력 참고</strong><br>${escapeHtml(minimumWageGuide?.message || '계약 시작일을 입력하면 적용 최저임금을 확인할 수 있습니다.')}</div>`;

    const loadMinimumWageGuide = async ({ date, currentDate, onChange }) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            minimumWageGuide = null;
            onChange();
            return;
        }
        try {
            const payload = await request(`${optionsUrl}?minimum_wage_only=1&contract_date=${encodeURIComponent(date)}`);
            if (date !== currentDate()) return;
            minimumWageGuide = payload.data?.minimum_wage_guide || null;
        } catch (error) {
            if (date !== currentDate()) return;
            minimumWageGuide = { status: 'UNAVAILABLE', message: error.message || '최저임금 기준을 불러오지 못했습니다.' };
        }
        onChange();
    };

    const load = contractId => {
        const normalizedId = String(contractId || '').trim();
        if (normalizedId === '') {
            reset();
            return Promise.resolve(null);
        }
        if (activeContractId === normalizedId && pending) return pending;
        const requestGeneration = ++generation;
        activeContractId = normalizedId;
        render(null, true);
        pending = request(`${apiUrl}?id=${encodeURIComponent(normalizedId)}`)
            .then(payload => {
                if (generation !== requestGeneration || activeContractId !== normalizedId) return null;
                render(payload.data || null);
                return payload.data || null;
            })
            .catch(error => {
                if (generation !== requestGeneration || activeContractId !== normalizedId) return null;
                const element = host();
                if (element) element.innerHTML = `<div class="alert alert-warning mb-0">${escapeHtml(error.message || '법정기준 검증결과를 불러오지 못했습니다.')}</div>`;
                return null;
            })
            .finally(() => {
                if (generation === requestGeneration) pending = null;
            });
        return pending;
    };

    modalElement?.addEventListener('hidden.bs.modal', reset);
    return { load, loadMinimumWageGuide, minimumWageGuideHtml, reset };
}
