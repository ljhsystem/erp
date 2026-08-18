export function compensationAmount(rows = []) {
    return rows
        .filter(row => row.rowState !== 'deleted')
        .reduce((total, row) => total + (Number(row.values?.amount) || 0), 0);
}

export function compensationSummary(totalAmount, salaryType) {
    if (salaryType === 'MONTHLY') return { totalLabel: '월 지급합계', convertedLabel: '연 환산액', convertedAmount: totalAmount * 12 };
    if (salaryType === 'ANNUAL') return { totalLabel: '연봉 합계', convertedLabel: '월 환산액', convertedAmount: totalAmount / 12 };
    if (salaryType === 'DAILY') return { totalLabel: '일 지급합계', convertedLabel: '', convertedAmount: null };
    if (salaryType === 'HOURLY') return { totalLabel: '시간급 합계', convertedLabel: '', convertedAmount: null };
    return { totalLabel: '지급합계', convertedLabel: '', convertedAmount: null };
}

export function formatCompensationAmount(value) {
    return `${Math.round((Number(value) || 0) * 100) / 100}`
        .replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '원';
}

export function usesComponentFormula(values = {}, master = {}) {
    const calculationType = String(values.calculation_type || master.default_calculation_type || '');
    const componentType = String(values.component_type || master.component_type || '');
    const componentCode = String(values.component_code || master.component_code || '');
    return calculationType === 'FORMULA'
        || ['BASE_PAY', 'STATUTORY_PREMIUM'].includes(componentType)
        || componentCode === 'ANNUAL_LEAVE_ALLOWANCE';
}

export function componentBasis(values = {}) {
    return Number(values.quantity || 0);
}

export function componentCalculation(values = {}, master = {}) {
    if (!usesComponentFormula(values, master)) return { display: '월 정액', calculatedAmount: null };

    const componentType = String(values.component_type || master.component_type || '');
    const basis = componentBasis(values);
    const wageFormula = ['STATUTORY_PREMIUM', 'OTHER_WAGE'].includes(componentType);
    const basisUnit = componentType === 'BASE_PAY' || wageFormula ? '시간' : '단위';
    const rate = Number(values.rate || 0);
    const premiumRate = Number(values.premium_rate || 0);
    const formatFactor = value => new Intl.NumberFormat('ko-KR', { maximumFractionDigits: 6 }).format(value);
    const parts = [];
    if (basis > 0) parts.push(`${formatFactor(basis)}${basisUnit}`);
    if (premiumRate > 0 && premiumRate !== 1) parts.push(`${formatFactor(premiumRate)}배`);
    if (rate > 0) parts.push(`${formatFactor(rate)}원`);
    return {
        display: parts.length ? parts.join(' × ') : '산식 정보 없음',
        calculatedAmount: basis > 0 && rate > 0
            ? Math.round(basis * rate * (premiumRate > 0 ? premiumRate : 1))
            : null,
    };
}

export function componentPolicyDisplay(values = {}, master = {}) {
    const source = { ...master, ...values };
    const label = (value, labels) => labels[String(value || '')] || String(value || '-');
    return [
        `과세 ${label(source.tax_type || source.default_tax_type, { TAXABLE: '과세', NON_TAXABLE: '비과세', POLICY_CALCULATED: '정책 판정' })}`,
        `통상임금 ${label(source.ordinary_wage_treatment, { INCLUDED: '산입', EXCLUDED: '제외', REVIEW_REQUIRED: '검토 필요' })}`,
        `평균임금 ${label(source.average_wage_treatment, { INCLUDED: '산입', EXCLUDED: '제외', REVIEW_REQUIRED: '검토 필요' })}`,
        `최저임금 ${label(source.minimum_wage_treatment, { INCLUDED: '산입', EXCLUDED: '제외', REVIEW_REQUIRED: '검토 필요' })}`,
    ].join(' · ');
}
