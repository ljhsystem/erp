const STATUS = Object.freeze({
    APPLICABLE: { name: '적용', className: 'is-applicable' },
    PARTIALLY_APPLICABLE: { name: '일부 적용', className: 'is-partial' },
    EXCLUDED: { name: '적용 제외', className: 'is-excluded' },
    CONFIRMATION_REQUIRED: { name: '확인 필요', className: 'is-confirmation' },
    CALCULATION_ERROR: { name: '계산 오류', className: 'is-error' },
});

const BADGE_SELECTOR = '[data-income-status-trigger="true"]';
const POPOVER_CLASS = 'insurance-eligibility-popover';
let openedBadge = null;
let popover = null;
let closeTimer = 0;
const initializedHosts = new WeakSet();

const normalizedStatus = value => ({
    ELIGIBLE: 'APPLICABLE',
    PARTIALLY_ELIGIBLE: 'PARTIALLY_APPLICABLE',
    NOT_ELIGIBLE: 'EXCLUDED',
    APPLICABLE: 'APPLICABLE',
    PARTIALLY_APPLICABLE: 'PARTIALLY_APPLICABLE',
    EXCLUDED: 'EXCLUDED',
    CONFIRMATION_REQUIRED: 'CONFIRMATION_REQUIRED',
    NEEDS_CONFIRMATION: 'CONFIRMATION_REQUIRED',
    CALCULATION_ERROR: 'CALCULATION_ERROR',
}[String(value || '').trim().toUpperCase()] || 'CONFIRMATION_REQUIRED');

const text = value => String(value ?? '').trim();

const arrayValue = value => {
    if (Array.isArray(value)) return value;
    if (typeof value !== 'string' || value.trim() === '') return [];
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};

export function insuranceEligibilityProjection(source = {}) {
    const statusCode = normalizedStatus(source.statusCode || source.eligibility_status_code || source.result_code || source.application_status_code || source.status_code);
    const insuranceCode = text(source.insuranceCode || source.insurance_type_code || source.code);
    const insuranceName = text(source.insuranceName || source.insurance_type_name || source.name);
    const suppliedReasonName = text(source.reasonName || source.reason_name || source.eligibility_reason_name);
    const decisionBasisName = text(source.decisionBasisName || source.decision_basis_name);
    let reasonDetail = text(source.reasonDetail || source.reason_detail || source.eligibility_reason_detail);
    return {
        insuranceCode,
        insuranceName,
        statusCode,
        statusName: text(source.statusName || source.eligibility_status_name) || STATUS[statusCode].name,
        reasonName: suppliedReasonName || (statusCode === 'APPLICABLE' ? '적용 판정근거 확인 필요'
            : (statusCode === 'CONFIRMATION_REQUIRED'
                ? '확인 필요자료를 확인할 수 없습니다.'
                : '판정 사유 확인 필요')),
        decisionBasisCode: text(source.decisionBasisCode || source.decision_basis_code),
        decisionBasisName: decisionBasisName || (statusCode === 'APPLICABLE' ? suppliedReasonName : ''),
        decisionBasisDetail: text(source.decisionBasisDetail || source.decision_basis_detail),
        reasonDetail,
        coverageStatusName: text(source.coverageStatusName || source.coverage_status_name || source.coverage_confirmation_status_name),
        effectiveStartDate: text(source.effectiveStartDate || source.effective_start_date),
        effectiveEndDate: text(source.effectiveEndDate || source.effective_end_date),
        failedConditions: arrayValue(source.failedConditions || source.failed_conditions),
        missingFacts: arrayValue(source.missingFacts || source.missing_facts || source.missing_inputs),
        componentResults: arrayValue(source.componentResults || source.component_results),
        passedConditions: arrayValue(source.passedConditions || source.passed_conditions),
        eligibilityRevisionId: text(source.eligibilityRevisionId || source.eligibility_revision_id),
        premiumRevisionId: text(source.premiumRevisionId || source.premium_revision_id),
        calculatedAt: text(source.calculatedAt || source.calculated_at),
        decisionSourceCode: text(source.decisionSourceCode || source.decision_source_code),
        decisionSourceName: text(source.decisionSourceName || source.decision_source_name),
        manualSettingReason: text(source.manualSettingReason || source.manual_setting_reason),
        companyBurdenName: text(source.companyBurdenName || source.company_burden_name),
        burdenSourceCode: text(source.burdenSourceCode || source.burden_source_code),
        burdenSourceName: text(source.burdenSourceName || source.burden_source_name),
        setByName: text(source.setByName || source.set_by_name),
        setAt: text(source.setAt || source.set_at),
    };
}

export function calculationModeProjection(source = {}) {
    return {
        displayTypeCode: 'CALCULATION_MODE',
        itemName: text(source.itemName || source.item_name),
        calculationMethodName: text(source.calculationMethodName || source.calculation_method_name) || '법정기준 자동계산',
        calculationBasisName: text(source.calculationBasisName || source.calculation_basis_name) || '자동계산 기준을 확인할 수 없습니다.',
        standardName: text(source.standardName || source.standard_name),
        effectiveFrom: text(source.effectiveFrom || source.effective_from),
        effectiveTo: text(source.effectiveTo || source.effective_to),
        basisAmount: source.basisAmount ?? source.basis_amount ?? null,
        rate: source.rate ?? null,
        roundingText: text(source.roundingText || source.rounding_text),
        calculatedAmount: source.calculatedAmount ?? source.calculated_amount ?? null,
        detail: text(source.detail),
    };
}

function projectionFromBadge(badge) {
    if (badge.__incomeStatusProjection?.displayTypeCode === 'CALCULATION_MODE') {
        return badge.__incomeStatusProjection;
    }
    return insuranceEligibilityProjection({
        insuranceCode: badge.dataset.insuranceCode,
        insuranceName: badge.dataset.insuranceName,
        statusCode: badge.dataset.statusCode,
        statusName: badge.dataset.statusName,
        reasonName: badge.dataset.reasonName,
        reasonDetail: badge.dataset.reasonDetail,
        decisionBasisCode: badge.dataset.decisionBasisCode,
        decisionBasisName: badge.dataset.decisionBasisName,
        decisionBasisDetail: badge.dataset.decisionBasisDetail,
        coverageStatusName: badge.dataset.coverageStatusName,
        effectiveStartDate: badge.dataset.effectiveStartDate,
        effectiveEndDate: badge.dataset.effectiveEndDate,
        failedConditions: badge.dataset.failedConditions,
        missingFacts: badge.dataset.missingFacts,
        componentResults: badge.dataset.componentResults,
        passedConditions: badge.dataset.passedConditions,
        eligibilityRevisionId: badge.dataset.eligibilityRevisionId,
        premiumRevisionId: badge.dataset.premiumRevisionId,
        calculatedAt: badge.dataset.calculatedAt,
        decisionSourceCode: badge.dataset.decisionSourceCode,
        decisionSourceName: badge.dataset.decisionSourceName,
        manualSettingReason: badge.dataset.manualSettingReason,
        companyBurdenName: badge.dataset.companyBurdenName,
        burdenSourceCode: badge.dataset.burdenSourceCode,
        burdenSourceName: badge.dataset.burdenSourceName,
        setByName: badge.dataset.setByName,
        setAt: badge.dataset.setAt,
    });
}

function ensurePopover() {
    if (popover?.isConnected) return popover;
    popover = document.createElement('aside');
    popover.className = POPOVER_CLASS;
    popover.setAttribute('role', 'tooltip');
    popover.hidden = true;
    document.body.append(popover);
    return popover;
}

function activeModal() {
    const modals = [...document.querySelectorAll('.modal.show')];
    return modals.at(-1) || null;
}

function numericZIndex(element) {
    if (!(element instanceof Element)) return 0;
    const value = Number.parseInt(getComputedStyle(element).zIndex, 10);
    return Number.isFinite(value) ? value : 0;
}

function syncOverlayLayer(overlay) {
    const modal = activeModal();
    const backdrops = [...document.querySelectorAll('.modal-backdrop.show')];
    const highestLayer = Math.max(numericZIndex(modal), ...backdrops.map(numericZIndex), 1055);
    overlay.style.zIndex = String(highestLayer + 10);
}

function appendRow(list, label, value) {
    if (!text(value)) return;
    const row = document.createElement('div');
    const heading = document.createElement('strong');
    const content = document.createElement('span');
    heading.textContent = label;
    content.textContent = value;
    row.append(heading, content);
    list.append(row);
}

function buildDetailList(projection) {
    const list = document.createElement('div');
    list.className = 'insurance-eligibility-popover-list';
    if (projection.displayTypeCode === 'CALCULATION_MODE') {
        appendRow(list, '항목', projection.itemName);
        appendRow(list, '계산방식', projection.calculationMethodName);
        appendRow(list, '계산근거', projection.calculationBasisName);
        appendRow(list, '적용 법정기준', projection.standardName);
        appendRow(list, '적용기간', projection.effectiveFrom ? `${projection.effectiveFrom} ~ ${projection.effectiveTo || '계속'}` : '');
        appendRow(list, '계산기초', projection.basisAmount === null ? '' : `${Number(projection.basisAmount).toLocaleString('ko-KR')}원`);
        appendRow(list, '적용요율', projection.rate === null ? '' : `${Number(projection.rate) * 100}%`);
        appendRow(list, '끝수처리', projection.roundingText);
        appendRow(list, '계산결과', projection.calculatedAmount === null ? '미확정' : `${Number(projection.calculatedAmount).toLocaleString('ko-KR')}원`);
        appendRow(list, '설명', projection.detail);
        return list;
    }
    appendRow(list, '보험', projection.insuranceName);
    appendRow(list, '적용상태', projection.statusName);
    if (['DAILY_GROUP_MANUAL_SETTING', 'BUSINESS_DIVISION_POLICY'].includes(projection.decisionSourceCode)) {
        appendRow(list, '회사부담', projection.companyBurdenName);
        appendRow(list, '부담설정 출처', projection.burdenSourceName || projection.decisionSourceName);
        if (projection.statusCode === 'EXCLUDED') appendRow(list, '안내', '해당 고용·산재 보험료를 우리 회사 부담액으로 계산하지 않습니다.');
    } else if (projection.decisionSourceCode === 'GROUP_MANUAL_SETTING') {
        appendRow(list, '설정방식', projection.decisionSourceName || 'Group 수동 설정');
        appendRow(list, '설정사유', projection.manualSettingReason || '설정사유 없음');
        appendRow(list, '안내', '과거 Group 설정에 따른 회사부담 계산 이력입니다.');
        appendRow(list, '설정자', projection.setByName);
        appendRow(list, '설정일시', projection.setAt);
    } else if (projection.decisionSourceCode === 'EMPLOYMENT_CONTRACT_SETTING') {
        appendRow(list, '설정방식', projection.decisionSourceName || '근로계약 설정');
        appendRow(list, '설정사유', projection.manualSettingReason);
        appendRow(list, '계약 적용기간', projection.effectiveStartDate
            ? `${projection.effectiveStartDate} ~ ${projection.effectiveEndDate || '계속'}` : '');
    } else if (projection.decisionSourceCode === 'COVERAGE_RECORD') {
        appendRow(list, '확인근거', projection.decisionSourceName || '가입정보');
        appendRow(list, '가입정보', projection.coverageStatusName);
        appendRow(list, 'Coverage 적용기간', projection.effectiveStartDate
            ? `${projection.effectiveStartDate} ~ ${projection.effectiveEndDate || '계속'}` : '');
    } else if (projection.decisionSourceName) {
        appendRow(list, '판정 출처', projection.decisionSourceName);
    }
    const heading = projection.statusCode === 'APPLICABLE' ? '판정근거'
        : (projection.statusCode === 'PARTIALLY_APPLICABLE' ? '구성요소별 판정'
            : (projection.statusCode === 'CONFIRMATION_REQUIRED' ? '확인 필요사항'
                : (projection.statusCode === 'CALCULATION_ERROR' ? '오류내용' : '판정사유')));
    appendRow(list, heading, projection.statusCode === 'APPLICABLE'
        ? (projection.decisionBasisName || projection.reasonName)
        : projection.reasonName);
    appendRow(list, '추가 설명', projection.statusCode === 'APPLICABLE'
        ? (projection.decisionBasisDetail || projection.reasonDetail)
        : projection.reasonDetail);
    appendRow(list, '신고상태', projection.coverageStatusName);
    projection.componentResults.forEach(component => {
        const name = text(component.component_name || component.stage_name);
        const reason = text(component.reason_name);
        if (name && reason) appendRow(list, name, reason);
    });
    return list;
}

function positionPopover(badge, overlay) {
    const badgeRect = badge.getBoundingClientRect();
    const overlayRect = overlay.getBoundingClientRect();
    const gap = 7;
    const edge = 12;
    const left = Math.min(Math.max(edge, badgeRect.left + (badgeRect.width - overlayRect.width) / 2), window.innerWidth - overlayRect.width - edge);
    const fitsAbove = badgeRect.top >= overlayRect.height + gap + edge;
    const top = fitsAbove ? badgeRect.top - overlayRect.height - gap : badgeRect.bottom + gap;
    overlay.style.left = `${Math.round(left)}px`;
    overlay.style.top = `${Math.max(edge, Math.round(top))}px`;
}

function verifyPopoverVisibility(overlay) {
    const style = getComputedStyle(overlay);
    const rect = overlay.getBoundingClientRect();
    if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0 || rect.width <= 0 || rect.height <= 0) return false;
    const x = Math.min(window.innerWidth - 1, Math.max(0, rect.left + rect.width / 2));
    const y = Math.min(window.innerHeight - 1, Math.max(0, rect.top + rect.height / 2));
    const topElement = document.elementFromPoint(x, y);
    return topElement === overlay || overlay.contains(topElement);
}

function showPopover(badge) {
    window.clearTimeout(closeTimer);
    const overlay = ensurePopover();
    const projection = projectionFromBadge(badge);
    const list = buildDetailList(projection);
    overlay.replaceChildren(list);
    overlay.hidden = false;
    syncOverlayLayer(overlay);
    openedBadge?.removeAttribute('aria-describedby');
    openedBadge?.setAttribute('aria-expanded', 'false');
    openedBadge = badge;
    if (!overlay.id) overlay.id = 'insuranceEligibilityPopover';
    badge.setAttribute('aria-describedby', overlay.id);
    badge.setAttribute('aria-expanded', 'true');
    positionPopover(badge, overlay);
    const visible = verifyPopoverVisibility(overlay);
    badge.closest('.insurance-eligibility-disclosure')?.classList.toggle('is-popover-visible', visible);
    overlay.dataset.displayVerified = visible ? 'true' : 'false';
    if (!visible) {
        overlay.remove();
        popover = null;
        openedBadge = null;
        badge.removeAttribute('aria-describedby');
        badge.setAttribute('aria-expanded', 'false');
    }
    return visible;
}

function closePopover({ restoreFocus = false } = {}) {
    window.clearTimeout(closeTimer);
    const badge = openedBadge;
    badge?.closest('.insurance-eligibility-disclosure')?.classList.remove('is-popover-visible');
    badge?.removeAttribute('aria-describedby');
    badge?.setAttribute('aria-expanded', 'false');
    openedBadge = null;
    popover?.remove();
    popover = null;
    if (restoreFocus && badge?.isConnected) badge.focus();
}

function closeLater() {
    window.clearTimeout(closeTimer);
    closeTimer = window.setTimeout(() => {
        if (!openedBadge?.matches(':hover, :focus') && !popover?.matches(':hover')) closePopover();
    }, 120);
}

export function bindInsuranceEligibilityBadge(element, source = {}) {
    if (!(element instanceof HTMLElement)) return null;
    const projection = insuranceEligibilityProjection(source);
    const definition = STATUS[projection.statusCode];
    element.className = `insurance-eligibility-badge ${definition.className}`;
    const label = document.createElement('span');
    const icon = document.createElement('i');
    label.textContent = projection.statusName;
    icon.className = 'bi bi-info-circle insurance-eligibility-badge-icon';
    icon.setAttribute('aria-hidden', 'true');
    element.replaceChildren(label, icon);
    element.setAttribute('role', 'button');
    element.setAttribute('tabindex', '0');
    element.setAttribute('aria-haspopup', 'true');
    element.setAttribute('aria-expanded', 'false');
    element.dataset.incomeStatusTrigger = 'true';
    element.setAttribute('aria-label', `${text(source.insurance_type_name || source.name || '보험')} ${projection.statusName} 판정내용`);
    Object.assign(element.dataset, {
        statusCode: projection.statusCode,
        insuranceCode: projection.insuranceCode,
        insuranceName: projection.insuranceName || text(source.insurance_type_name || source.name),
        statusName: projection.statusName,
        reasonName: projection.reasonName,
        reasonDetail: projection.reasonDetail,
        decisionBasisCode: projection.decisionBasisCode,
        decisionBasisName: projection.decisionBasisName,
        decisionBasisDetail: projection.decisionBasisDetail,
        coverageStatusName: projection.coverageStatusName,
        effectiveStartDate: projection.effectiveStartDate,
        effectiveEndDate: projection.effectiveEndDate,
        failedConditions: JSON.stringify(projection.failedConditions),
        missingFacts: JSON.stringify(projection.missingFacts),
        componentResults: JSON.stringify(projection.componentResults),
        passedConditions: JSON.stringify(projection.passedConditions),
        eligibilityRevisionId: projection.eligibilityRevisionId,
        premiumRevisionId: projection.premiumRevisionId,
        calculatedAt: projection.calculatedAt,
        decisionSourceCode: projection.decisionSourceCode,
        decisionSourceName: projection.decisionSourceName,
        manualSettingReason: projection.manualSettingReason,
        companyBurdenName: projection.companyBurdenName,
        burdenSourceCode: projection.burdenSourceCode,
        burdenSourceName: projection.burdenSourceName,
        setByName: projection.setByName,
        setAt: projection.setAt,
    });
    const disclosure = element.closest('.insurance-eligibility-disclosure');
    if (disclosure) {
        let fallback = disclosure.querySelector('.insurance-eligibility-fallback');
        if (!fallback) {
            fallback = document.createElement('div');
            fallback.className = 'insurance-eligibility-fallback';
            disclosure.append(fallback);
        }
        fallback.replaceChildren(buildDetailList(projection));
    }
    return projection;
}

export function bindCalculationModeBadge(element, source = {}) {
    if (!(element instanceof HTMLElement)) return null;
    const projection = calculationModeProjection(source);
    element.className = 'insurance-eligibility-badge is-calculation-mode';
    const label = document.createElement('span');
    const icon = document.createElement('i');
    label.textContent = '자동계산';
    icon.className = 'bi bi-info-circle insurance-eligibility-badge-icon';
    icon.setAttribute('aria-hidden', 'true');
    element.replaceChildren(label, icon);
    element.setAttribute('role', 'button');
    element.setAttribute('tabindex', '0');
    element.setAttribute('aria-haspopup', 'true');
    element.setAttribute('aria-expanded', 'false');
    element.setAttribute('aria-label', `${projection.itemName || '세금'} 자동계산 기준`);
    element.dataset.incomeStatusTrigger = 'true';
    element.__incomeStatusProjection = projection;
    return projection;
}

export function initializeInsuranceEligibilityBadges(host) {
    if (!(host instanceof HTMLElement) || initializedHosts.has(host)) return;
    initializedHosts.add(host);
    host.dataset.insuranceEligibilityBadgeReady = 'true';
    host.addEventListener('pointerover', event => {
        const badge = event.target.closest?.(BADGE_SELECTOR);
        if (badge && host.contains(badge)) showPopover(badge);
    }, true);
    host.addEventListener('pointerout', event => {
        if (event.target.closest?.(BADGE_SELECTOR)) closeLater();
    }, true);
    host.addEventListener('focusin', event => {
        const badge = event.target.closest?.(BADGE_SELECTOR);
        if (badge && host.contains(badge)) showPopover(badge);
    }, true);
    host.addEventListener('focusout', event => {
        if (event.target.closest?.(BADGE_SELECTOR)) closeLater();
    }, true);
    host.addEventListener('click', event => {
        const badge = event.target.closest?.(BADGE_SELECTOR);
        if (!badge || !host.contains(badge)) return;
        openedBadge === badge && !popover?.hidden ? closePopover() : showPopover(badge);
    }, true);
    host.addEventListener('keydown', event => {
        const badge = event.target.closest?.(BADGE_SELECTOR);
        if (badge && host.contains(badge) && ['Enter', ' '].includes(event.key)) {
            event.preventDefault();
            openedBadge === badge && !popover?.hidden ? closePopover() : showPopover(badge);
        }
        if (event.key === 'Escape' && openedBadge) {
            event.preventDefault();
            closePopover({ restoreFocus: true });
            host.querySelectorAll('.insurance-eligibility-disclosure[open]').forEach(detail => detail.removeAttribute('open'));
        }
    }, true);
}

export function disposeInsuranceEligibilityPopovers() {
    closePopover();
}

document.addEventListener('pointerover', event => {
    if (event.target.closest?.(`.${POPOVER_CLASS}`)) window.clearTimeout(closeTimer);
});
document.addEventListener('click', event => {
    if (event.target.closest?.(BADGE_SELECTOR) || event.target.closest?.(`.${POPOVER_CLASS}`)) return;
    closePopover();
    document.querySelectorAll('.insurance-eligibility-disclosure[open]').forEach(detail => detail.removeAttribute('open'));
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && openedBadge) {
        event.preventDefault();
        closePopover({ restoreFocus: true });
    }
});
document.addEventListener('hidden.bs.modal', disposeInsuranceEligibilityPopovers);
window.addEventListener('resize', () => {
    if (!openedBadge || !popover) return;
    syncOverlayLayer(popover);
    positionPopover(openedBadge, popover);
    if (!verifyPopoverVisibility(popover)) closePopover();
});
document.addEventListener('scroll', closePopover, true);
