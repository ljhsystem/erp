import { formatAmount } from './format.js';
import { bindCalculationModeBadge, bindInsuranceEligibilityBadge, disposeInsuranceEligibilityPopovers, initializeInsuranceEligibilityBadges, insuranceEligibilityProjection } from './insurance-eligibility-badge.js?v=20260831-income-status-13';

const amountText = value => value === null || value === undefined ? '미확정' : `${formatAmount(value)}원`;

const EDITABLE_DOCUMENT_STATUSES = new Set(['DRAFT', 'REJECTED', 'WITHDRAWN']);

export const isIncomeCalculationEditableStatus = status =>
    EDITABLE_DOCUMENT_STATUSES.has(String(status || '').trim().toUpperCase());

export const incomeCalculationPeriodText = line => line?.standard_effective_from || line?.tax_table_effective_from
    ? `${line.standard_effective_from || line.tax_table_effective_from} ~ ${line.standard_effective_to || line.tax_table_effective_to || '계속'}`
    : '-';

export const incomeCalculationRoundingText = line => {
    const method = line?.rounding_method_code || line?.rounding_method;
    if (!method) return '공식 끝수처리 미확정';
    const label = { TRUNCATE: '절사', FLOOR: '절사', ROUND: '반올림', ROUND_UP: '올림', CEIL: '올림' }[method] || method;
    return `${label} (${Number(line?.rounding_unit) || 1}원 단위)`;
};

export const INCOME_INSTITUTION_CARDS = Object.freeze([
    { key: 'INCOME_TAX', name: '근로소득세', aliases: ['EMPLOYMENT_INCOME_TAX', 'DAILY_WORKER_INCOME_TAX'], employeeOnly: true },
    { key: 'LOCAL_INCOME_TAX', name: '지방소득세', aliases: ['LOCAL_INCOME_TAX'], employeeOnly: true },
    { key: 'NATIONAL_PENSION', name: '국민연금', aliases: ['NATIONAL_PENSION'] },
    { key: 'HEALTH_INSURANCE', name: '건강보험', aliases: ['HEALTH_INSURANCE'] },
    { key: 'LONG_TERM_CARE', name: '장기요양보험', aliases: ['LONG_TERM_CARE', 'LONG_TERM_CARE_INSURANCE'] },
    { key: 'EMPLOYMENT_INSURANCE', name: '고용보험', aliases: ['EMPLOYMENT_INSURANCE'] },
    { key: 'EMPLOYMENT_INSURANCE_VOCATIONAL', name: '고용안정·직업능력개발', aliases: ['EMPLOYMENT_INSURANCE_VOCATIONAL'], employerOnly: true },
    { key: 'INDUSTRIAL_ACCIDENT_INSURANCE', name: '산재보험', aliases: ['INDUSTRIAL_ACCIDENT_INSURANCE'], employerOnly: true },
]);

export function incomeInstitutionCardsDto(lines, options = {}) {
    const typeField = options.typeField || 'item_type_code';
    const codeField = options.codeField || 'item_code';
    const deductionType = options.deductionType || 'DEDUCTION';
    const employerType = options.employerType || 'EMPLOYER_BURDEN';
    const all = Array.isArray(lines) ? lines : [];
    return {
        lines: INCOME_INSTITUTION_CARDS.map(definition => {
            const candidates = all.filter(line => definition.aliases.includes(line?.[codeField]));
            const deduction = candidates.find(line => line?.[typeField] === deductionType);
            const employer = candidates.find(line => line?.[typeField] === employerType);
            const primary = definition.employerOnly ? employer : deduction;
            let eligibilitySource = primary?.eligibility_projection || primary?.eligibility_result || primary?.eligibility_snapshot || primary || {};
            if (typeof eligibilitySource === 'string') {
                try { eligibilitySource = JSON.parse(eligibilitySource); } catch { eligibilitySource = primary || {}; }
            }
            const eligibility = insuranceEligibilityProjection({ ...(primary || {}), ...eligibilitySource });
            const eligibilityPending = eligibility.statusCode === 'CONFIRMATION_REQUIRED';
            const eligibilityBlocksCalculation = !definition.employeeOnly
                && ['EXCLUDED', 'CONFIRMATION_REQUIRED', 'CALCULATION_ERROR'].includes(eligibility.statusCode);
            const employeeContributionApplicable = !definition.employerOnly;
            const employerContributionApplicable = !definition.employeeOnly;
            const employeeAmountEditable = Boolean(employeeContributionApplicable && !eligibilityBlocksCalculation
                && primary?.calculated_amount !== null && primary?.calculated_amount !== undefined
                && options.editable?.(primary, definition));
            const employerAmountEditable = Boolean(employerContributionApplicable && !eligibilityBlocksCalculation
                && employer?.calculated_amount !== null && employer?.calculated_amount !== undefined
                && options.editable?.(employer, definition));
            const missingMessage = definition.key === 'INDUSTRIAL_ACCIDENT_INSURANCE'
                ? '공식 업종·보험관계가 연결되지 않아 법정기준 확인이 필요합니다.'
                : '적용 여부와 법정기준을 확인해 주세요.';
            const dto = incomeCalculationLineDto(primary || {}, {
                key: definition.key,
                sourceCode: primary?.[codeField] || definition.aliases[0],
                name: definition.name,
                employeeOnly: Boolean(definition.employeeOnly),
                employerOnly: Boolean(definition.employerOnly),
                calculatedAmount: eligibility.statusCode === 'EXCLUDED' ? 0 : (primary?.calculated_amount ?? null),
                finalAmount: eligibility.statusCode === 'EXCLUDED' ? 0 : eligibilityPending || (definition.employerOnly && primary?.calculated_amount === null)
                    ? null : (primary ? (primary.final_amount ?? null) : null),
                employerAmount: eligibility.statusCode === 'EXCLUDED' ? 0 : eligibilityPending ? null : (employer ? (employer.final_amount ?? null) : null),
                employerCalculatedAmount: eligibility.statusCode === 'EXCLUDED' ? 0 : (employer?.calculated_amount ?? null),
                employerDifference: employer?.calculated_amount === null || employer?.calculated_amount === undefined
                    || employer?.final_amount === null || employer?.final_amount === undefined
                    ? null : Number(employer.final_amount) - Number(employer.calculated_amount),
                employerReason: employer?.adjustment_reason || '',
                employeeContributionApplicable,
                employerContributionApplicable,
                employeeAmountEditable,
                employerAmountEditable,
                employerEditable: employerAmountEditable,
                employerSourceCode: employer?.[codeField] || definition.aliases[0],
                employerSourceType: employer?.[typeField] || employerType,
                calculationStatus: primary?.calculation_status_code || primary?.application_status_code || '',
                applicationStatus: eligibility.statusCode,
                message: eligibility.statusCode === 'EXCLUDED' ? '' : (primary ? (primary.calculation_message || primary.business_reason || primary.message || '') : missingMessage),
                editable: employeeAmountEditable,
                sourceType: primary?.[typeField] || '',
                eligibility,
            });
            return options.mapLine ? options.mapLine(dto, primary, employer, definition, candidates) : dto;
        }),
    };
}

export function incomeCalculationLineDto(line, options = {}) {
    const calculated = line.calculated_amount ?? null;
    const finalAmount = line.final_amount ?? null;
    return {
        key: options.key || line.item_code || line.line_code,
        name: options.name || line.item_name_snapshot || line.line_name_snapshot,
        basisAmount: line.calculation_basis_amount ?? null,
        rate: line.calculation_rate ?? null,
        beforeRounding: line.calculation_before_rounding ?? null,
        calculatedAmount: calculated,
        finalAmount,
        difference: calculated === null || finalAmount === null ? null : Number(finalAmount) - Number(calculated),
        reason: line.adjustment_reason || '', message: line.calculation_message || '',
        calculationMode: line.calculation_mode_projection || null,
        ...options,
    };
}

export function renderIncomeCalculationCards(host, dto, handlers = {}) {
    disposeInsuranceEligibilityPopovers();
    host.replaceChildren();
    const grid = document.createElement('div');
    grid.className = 'income-calculation-card-grid';
    dto.lines.forEach(line => {
        const card = document.createElement('section');
        const cardKey = String(line.key || 'default').toLowerCase().replace(/[^a-z0-9_-]/g, '-');
        card.className = `income-calculation-card income-calculation-card--${cardKey}`;
        const excluded = line.applicationStatus === 'EXCLUDED';
        const status = excluded ? '적용 제외' : line.calculatedAmount === null
            ? (line.calculationStatus === 'CALCULATING' ? '계산 중' : (line.applicationStatus === 'CONFIRMATION_REQUIRED' ? '확인 필요' : (line.applicationStatus === 'APPLICABLE' ? '법정 계산 확인 필요' : '적용 여부 확인 필요')))
            : (line.finalAmount === null ? '법정기준 자동계산' : (line.difference ? '수동 적용' : '자동계산'));
        const header = document.createElement('header');
        const heading = document.createElement('h6'); heading.textContent = line.name;
        let statusBadge = document.createElement('span');
        if (INCOME_INSTITUTION_CARDS.some(definition => definition.key === line.key && !definition.employeeOnly)) {
            const disclosure = document.createElement('details');
            disclosure.className = 'insurance-eligibility-disclosure';
            statusBadge = document.createElement('summary');
            disclosure.append(statusBadge);
            bindInsuranceEligibilityBadge(statusBadge, { ...line.eligibility, name: line.name });
            header.append(heading, disclosure);
        } else if (line.calculationMode && line.calculatedAmount !== null) {
            bindCalculationModeBadge(statusBadge, {
                ...line.calculationMode,
                itemName: line.name,
                basisAmount: line.basisAmount,
                rate: line.rate,
                roundingText: line.roundingLabel,
                calculatedAmount: line.calculatedAmount,
            });
            header.append(heading, statusBadge);
        } else {
            statusBadge.className = 'income-calculation-status'; statusBadge.textContent = status;
            header.append(heading, statusBadge);
        }
        card.append(header);
        const values = document.createElement('dl');
        const entries = [
            ['계산기초', excluded ? '해당 없음' : amountText(line.basisAmount)], ['적용기준', excluded ? '적용 제외(확인 완료)' : (line.standardLabel || '확인 필요')],
            ['법정요율', excluded ? '해당 없음' : (line.rateLabel || (line.rate === null ? '미확정' : `${Number(line.rate * 100).toFixed(4).replace(/0+$/,'').replace(/\.$/,'')}%`))],
            ['계산 전 금액', excluded ? '해당 없음' : amountText(line.beforeRounding)], ['끝수처리', excluded ? '해당 없음' : (line.roundingLabel || '미확정')],
            ['자동계산액', excluded ? '0원' : amountText(line.calculatedAmount)], ['조정차이', excluded ? '0원' : amountText(line.difference)],
        ];
        entries.forEach(([label, value]) => {
            const dt = document.createElement('dt'); dt.textContent = label;
            const dd = document.createElement('dd'); dd.textContent = value;
            values.append(dt, dd);
        });
        card.append(values);
        if (!line.employerOnly && line.editable) {
            const editor = document.createElement('div');
            editor.className = 'income-calculation-editor';
            editor.innerHTML = `<label>자동계산액<strong>${amountText(line.calculatedAmount)}</strong></label><label class="income-calculation-apply-field">근로자 적용금액<input class="form-control form-control-sm" inputmode="numeric"></label><label class="income-calculation-reason">적용사유 *<input class="form-control form-control-sm" maxlength="500"></label><div class="invalid-feedback"></div>`;
            const amount = editor.querySelector('input');
            const reason = editor.querySelector('.income-calculation-reason input');
            amount.value = line.finalAmount === null || line.finalAmount === undefined ? '' : formatAmount(line.finalAmount);
            reason.value = line.reason || '';
            const reasonWrap = editor.querySelector('.income-calculation-reason');
            const restoreRow = document.createElement('div');
            const restoreButton = document.createElement('button');
            restoreRow.className = 'income-calculation-restore-row';
            restoreButton.type = 'button'; restoreButton.className = 'btn btn-link btn-sm income-calculation-restore';
            restoreButton.textContent = '계산액으로 복원'; restoreButton.setAttribute('aria-label', '근로자 적용금액을 계산액으로 복원');
            editor.querySelector('.income-calculation-apply-field').after(restoreRow);
            const appliedValue = () => Number(amount.value.replaceAll(',', '')) || 0;
            const syncRestoreAction = () => {
                const differs = Math.abs(appliedValue() - Number(line.calculatedAmount)) >= .01;
                if (differs && !restoreButton.isConnected) restoreRow.append(restoreButton);
                if (!differs && restoreButton.isConnected) restoreButton.remove();
            };
            const reasonRequired = () => line.calculatedAmount === null || line.calculatedAmount === undefined
                ? Math.abs(appliedValue()) >= .01
                : Math.abs(appliedValue() - Number(line.calculatedAmount)) >= .01;
            const syncReason = () => { const required = reasonRequired(); reasonWrap.classList.toggle('d-none', !required); if (!required) reason.value = ''; };
            const commit = () => { syncReason(); syncRestoreAction(); handlers.onChange?.(line.key, appliedValue(), reason.value.trim(), editor); };
            amount.addEventListener('input', () => { amount.value = formatAmount(amount.value.replaceAll(',', '')); commit(); });
            reason.addEventListener('input', commit);
            restoreButton.addEventListener('click', () => {
                if (line.calculatedAmount === null) return;
                amount.value = formatAmount(line.calculatedAmount); reason.value = ''; commit();
            });
            syncReason();
            syncRestoreAction();
            card.append(editor);
        } else if (!line.employerOnly) {
            const applied = document.createElement('div');
            applied.className = 'income-calculation-editor income-calculation-editor--readonly';
            applied.innerHTML = `<label>자동계산액<strong>${amountText(line.calculatedAmount)}</strong></label><label>근로자 적용금액<strong>${amountText(line.finalAmount)}</strong></label><span class="income-calculation-mode">근로자 적용방식: ${excluded ? '적용 제외' : '자동 반영'}</span>`;
            card.append(applied);
        }
        if (line.employerEditable) {
            const employerEditor = document.createElement('div');
            employerEditor.className = 'income-calculation-editor income-calculation-editor--employer';
            employerEditor.innerHTML = `<label>사용자 자동계산액<strong>${amountText(line.employerCalculatedAmount)}</strong></label><label class="income-calculation-apply-field">사용자 적용금액<input class="form-control form-control-sm" inputmode="numeric"></label><label class="income-calculation-reason">적용사유 *<input class="form-control form-control-sm" maxlength="500"></label><div class="invalid-feedback"></div>`;
            const amount = employerEditor.querySelector('input');
            const reason = employerEditor.querySelector('.income-calculation-reason input');
            const reasonWrap = employerEditor.querySelector('.income-calculation-reason');
            const restoreRow = document.createElement('div');
            const restoreButton = document.createElement('button');
            restoreRow.className = 'income-calculation-restore-row';
            restoreButton.type = 'button'; restoreButton.className = 'btn btn-link btn-sm income-calculation-restore';
            restoreButton.textContent = '계산액으로 복원'; restoreButton.setAttribute('aria-label', '사용자 적용금액을 계산액으로 복원');
            employerEditor.querySelector('.income-calculation-apply-field').after(restoreRow);
            amount.value = line.employerAmount === null || line.employerAmount === undefined ? '' : formatAmount(line.employerAmount);
            reason.value = line.employerReason || '';
            const appliedValue = () => Number(amount.value.replaceAll(',', '')) || 0;
            const syncRestoreAction = () => {
                const differs = Math.abs(appliedValue() - Number(line.employerCalculatedAmount)) >= .01;
                if (differs && !restoreButton.isConnected) restoreRow.append(restoreButton);
                if (!differs && restoreButton.isConnected) restoreButton.remove();
            };
            const reasonRequired = () => line.employerCalculatedAmount === null || line.employerCalculatedAmount === undefined
                ? Math.abs(appliedValue()) >= .01
                : Math.abs(appliedValue() - Number(line.employerCalculatedAmount)) >= .01;
            const syncReason = () => { const required = reasonRequired(); reasonWrap.classList.toggle('d-none', !required); if (!required) reason.value = ''; };
            const commit = () => { syncReason(); syncRestoreAction(); handlers.onEmployerChange?.(line.key, appliedValue(), reason.value.trim(), employerEditor); };
            amount.addEventListener('input', () => { amount.value = formatAmount(amount.value.replaceAll(',', '')); commit(); });
            reason.addEventListener('input', commit);
            restoreButton.addEventListener('click', () => {
                if (line.employerCalculatedAmount === null || line.employerCalculatedAmount === undefined) return;
                amount.value = formatAmount(line.employerCalculatedAmount); reason.value = ''; commit();
            });
            syncReason();
            syncRestoreAction();
            card.append(employerEditor);
        }
        if (line.message && !excluded) { const note = document.createElement('p'); note.className = 'income-calculation-note'; note.textContent = line.message; card.append(note); }
        const settlement = document.createElement('section');
        settlement.className = 'income-calculation-settlement';
        settlement.innerHTML = '<span>정산내역</span><strong>현재 없음</strong>';
        const final = document.createElement('section');
        final.className = 'income-calculation-final';
        final.innerHTML = `<span>${line.employerOnly ? '최종 사용자부담' : '최종 공제'}</span><strong>${amountText(line.finalAmount)}</strong><span>회사부담</span><strong>${line.employeeOnly ? '해당 없음' : amountText(line.employerAmount)}</strong>`;
        card.append(settlement, final);
        grid.append(card);
    });
    host.append(grid);
    initializeInsuranceEligibilityBadges(host);
}
