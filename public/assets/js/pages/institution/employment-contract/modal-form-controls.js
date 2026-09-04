import { getCachedDataTableMetaColumns } from '/public/assets/js/common/datatable/dataTableSettings.js';
import { createDataTableFormSettings } from '/public/assets/js/common/datatable/dataTableFormSettings.js';

export function createModalFormControls({
    form,
    AdminPicker: pickerProvider,
    normalizeTimeInputValue,
    formatPickerDate,
    recommendedProjectReason,
    fixedTermDetailRequired,
    tableSettingsKey,
    tableSettingsOptions,
    getFixedTermContractPeriodType,
    isEditable,
}) {
    let datePicker = null;
    let activeDateInput = null;
    let timePicker = null;
    let activeTimeInput = null;
    const databaseMetaCache = new Map();

    const setFormLabel = (label, text, required) => {
        if (!(label instanceof HTMLLabelElement) || !text) return;
        let requiredMark = label.querySelector('.text-danger');
        const attributes = requiredMark
            ? Array.from(requiredMark.attributes).map(attribute => [attribute.name, attribute.value])
            : [];
        label.replaceChildren(document.createTextNode(`${text} `));
        if (!requiredMark) {
            requiredMark = document.createElement('span');
            requiredMark.className = 'text-danger';
            requiredMark.textContent = '*';
        }
        attributes.forEach(([name, value]) => requiredMark.setAttribute(name, value));
        requiredMark.classList.toggle('d-none', !required);
        label.append(requiredMark);
    };

    const contractFormSettings = createDataTableFormSettings({
        form,
        metaDomain: 'employment-contract',
        storageKey: tableSettingsKey,
        settingsOptions: tableSettingsOptions,
        businessPolicy: ({ key }) => {
            const locationType = String(form.elements.namedItem('work_location_type')?.value || '');
            const periodType = String(form.elements.namedItem('contract_period_type')?.value || '');
            const reasonCode = String(form.elements.namedItem('fixed_term_reason_code')?.value || '');
            const isFixedTerm = periodType === getFixedTermContractPeriodType();
            if (key === 'contract_end_date' || key === 'fixed_term_reason_code') {
                return { required: isFixedTerm };
            }
            if (key === 'fixed_term_reason_detail') {
                const required = isFixedTerm && fixedTermDetailRequired.has(reasonCode);
                return { required };
            }
            if (key === 'project_id') {
                const required = isFixedTerm && reasonCode === 'PROJECT_COMPLETION';
                return { required };
            }
            if (key === 'work_location_detail') {
                const required = locationType === 'OTHER';
                return { required };
            }
            return {};
        },
    });

    const applyContractColumnMeta = () => contractFormSettings.apply();

    const databaseMetaByKey = domain => {
        if (!databaseMetaCache.has(domain)) {
            const columns = getCachedDataTableMetaColumns({ metaDomain: domain });
            databaseMetaCache.set(domain, new Map(columns.map(column => [String(column.key || ''), column])));
        }
        return databaseMetaCache.get(domain);
    };

    const applySchedulePolicyColumnMeta = () => {
        const metaByKey = databaseMetaByKey('employment-contract-work-schedule-policy');
        form.querySelectorAll('[data-policy-field]').forEach(control => {
            const column = metaByKey.get(String(control.dataset.policyField || ''));
            const label = control.closest('[class*="col-"]')?.querySelector(':scope > label.form-label');
            if (!column || !label) return;
            const key = String(column.key || control.dataset.policyField || '');
            const text = String(column.label || '').trim();
            if (text === '' || text === key) return;
            setFormLabel(label, text, control.required || Boolean(column.required));
        });
    };

    const fixedTermFields = () => ({
        contractPeriodType: form.elements.namedItem('contract_period_type'),
        endDateArea: document.getElementById('employmentContractEndDateArea'),
        area: document.getElementById('employmentFixedTermReasonArea'),
        endDate: form.elements.namedItem('contract_end_date'),
        reason: form.elements.namedItem('fixed_term_reason_code'),
        detail: form.elements.namedItem('fixed_term_reason_detail'),
        project: form.elements.namedItem('project_id'),
        endDateRequired: document.querySelector('[data-contract-end-required]'),
        reasonRequired: document.querySelector('[data-fixed-term-required]'),
        detailRequired: document.querySelector('[data-fixed-term-detail-required]'),
        warning: document.getElementById('employmentFixedTermWarning'),
    });

    const updateProjectReasonRecommendation = () => {
        const { contractPeriodType, reason, detail, project } = fixedTermFields();
        if (!contractPeriodType || contractPeriodType.value !== getFixedTermContractPeriodType()
            || !reason || !detail || !project || reason.value !== 'PROJECT_COMPLETION') return;
        const previous = detail.dataset.recommendedValue || '';
        const recommendation = recommendedProjectReason(project);
        if (detail.value.trim() === '' || detail.value === previous) {
            detail.value = recommendation;
            detail.dataset.recommendedValue = recommendation;
        }
    };

    const updateFixedTermPolicy = () => {
        applyContractColumnMeta();
        const fields = fixedTermFields();
        const { contractPeriodType, endDateArea, area, endDate, reason, detail, project } = fields;
        if (!contractPeriodType || !endDateArea || !area || !endDate || !reason || !detail || !project) return;
        const isFixedTerm = getFixedTermContractPeriodType() !== ''
            && contractPeriodType.value === getFixedTermContractPeriodType();
        const reasonCode = isFixedTerm ? reason.value : '';
        const requiresDetail = fixedTermDetailRequired.has(reasonCode);
        const requiresProject = reasonCode === 'PROJECT_COMPLETION';
        const editable = isEditable();
        endDateArea.hidden = !isFixedTerm;
        area.hidden = !isFixedTerm;
        endDate.required = isFixedTerm;
        reason.required = isFixedTerm;
        detail.required = isFixedTerm && requiresDetail;
        project.required = project.dataset.columnMetaRequired === '1' || isFixedTerm && requiresProject;
        endDate.disabled = !isFixedTerm || !editable;
        reason.disabled = !isFixedTerm || !editable;
        detail.disabled = !isFixedTerm || !editable;
        fields.endDateRequired?.classList.toggle('d-none', !isFixedTerm);
        fields.reasonRequired?.classList.toggle('d-none', !isFixedTerm);
        fields.detailRequired?.classList.toggle('d-none', !detail.required);
        if (!isFixedTerm) {
            endDate.value = '';
            reason.value = '';
            detail.value = '';
            delete detail.dataset.recommendedValue;
            window.jQuery?.(reason).trigger('change.select2');
        } else if (requiresProject) updateProjectReasonRecommendation();
        else delete detail.dataset.recommendedValue;
        const message = reasonCode === 'REVIEW_REQUIRED'
            ? '검토 필요 사유는 임시저장만 가능합니다. 결재 요청 전에 적정한 기간제 계약 사유로 변경해 주세요.'
            : (reasonCode === 'SENIOR' ? '고령자 적용 요건은 현재 직원정보만으로 확정할 수 없습니다. 요건을 확인할 수 없으면 검토 필요를 선택해 주세요.' : '');
        if (fields.warning) {
            fields.warning.textContent = message;
            fields.warning.classList.toggle('d-none', message === '');
        }
        const submit = document.getElementById('employmentContractSubmit');
        if (submit) submit.disabled = reasonCode === 'REVIEW_REQUIRED';
    };

    const bindFixedTermPolicy = () => {
        const { contractPeriodType, reason, detail, project } = fixedTermFields();
        contractPeriodType?.addEventListener('change', updateFixedTermPolicy);
        reason?.addEventListener('change', updateFixedTermPolicy);
        window.jQuery?.(form).on('select2:select select2:clear', '[name="contract_period_type"], [name="fixed_term_reason_code"]', updateFixedTermPolicy);
        project?.addEventListener('change', () => {
            updateProjectReasonRecommendation();
            updateFixedTermPolicy();
        });
        detail?.addEventListener('input', () => {
            if (detail.value !== (detail.dataset.recommendedValue || '')) delete detail.dataset.recommendedValue;
        });
        const locationType = form.elements.namedItem('work_location_type');
        locationType?.addEventListener('change', updateFixedTermPolicy);
        window.jQuery?.(form).on('select2:select select2:clear', '[name="work_location_type"]', updateFixedTermPolicy);
    };

    const initDatePicker = () => {
        const container = document.getElementById('employment-contract-date-picker');
        if (!container || datePicker) return datePicker;
        datePicker = pickerProvider().create({ type: 'today', container });
        datePicker.subscribe((_, date) => {
            if (!activeDateInput || !date) return;
            activeDateInput.value = formatPickerDate(date);
            activeDateInput.dispatchEvent(new Event('input', { bubbles: true }));
            activeDateInput.dispatchEvent(new Event('change', { bubbles: true }));
            datePicker.close();
        });
        return datePicker;
    };

    const openDatePicker = input => {
        const picker = initDatePicker();
        if (!picker || input.disabled) return;
        activeDateInput = input;
        picker.clearDate?.();
        if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            const [year, month, day] = input.value.split('-').map(Number);
            picker.setDate(new Date(year, month - 1, day));
        }
        picker.open({ anchor: input });
    };

    const bindDateInputs = () => form.querySelectorAll('[data-employment-date]').forEach(input => {
        input.addEventListener('input', () => {
            const digits = input.value.replace(/\D/g, '').slice(0, 8);
            input.value = digits.length === 8 ? `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6, 8)}` : digits;
        });
        input.addEventListener('click', () => openDatePicker(input));
        input.closest('.date-input')?.querySelector('.date-icon')?.addEventListener('click', () => openDatePicker(input));
    });

    const initTimePicker = () => {
        const container = document.getElementById('employment-contract-time-picker');
        if (!container || timePicker) return timePicker;
        timePicker = pickerProvider().create({ type: 'time-list', container, options: { step: 1, rows: 8 } });
        timePicker.subscribe(state => {
            if (!activeTimeInput || typeof state?.hour !== 'number' || typeof state?.minute !== 'number') return;
            activeTimeInput.value = `${String(state.hour).padStart(2, '0')}:${String(state.minute).padStart(2, '0')}`;
            activeTimeInput.dispatchEvent(new Event('change', { bubbles: true }));
            timePicker.close();
        });
        return timePicker;
    };

    const openTimePicker = input => {
        if (!input || input.disabled) return;
        activeTimeInput = input;
        const picker = initTimePicker();
        if (!picker) return;
        const match = input.value.match(/^(\d{2}):(\d{2})$/);
        if (match) {
            const hour = Number(match[1]);
            picker.setTime?.({ hour, minute: Number(match[2]), meridiem: hour >= 12 ? 'PM' : 'AM' });
        }
        picker.open({ anchor: input });
    };

    const bindTimeInputs = () => form.querySelectorAll('[data-employment-time]').forEach(input => {
        input.type = 'text';
        input.inputMode = 'numeric';
        input.maxLength = 5;
        input.placeholder = 'HH:MM';
        input.autocomplete = 'off';
        input.addEventListener('click', () => openTimePicker(input));
        input.addEventListener('blur', () => {
            if (input.value.trim() === '') return;
            input.value = normalizeTimeInputValue(input.value);
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    const closePickers = () => {
        activeDateInput = null;
        activeTimeInput = null;
        datePicker?.close?.();
        timePicker?.close?.();
    };

    const clearActiveTimeInput = input => {
        if (activeTimeInput === input) activeTimeInput = null;
    };

    return {
        applyContractColumnMeta,
        applySchedulePolicyColumnMeta,
        bindDateInputs,
        bindTimeInputs,
        bindFixedTermPolicy,
        closePickers,
        clearActiveTimeInput,
        databaseMetaByKey,
        openTimePicker,
        updateFixedTermPolicy,
        updateProjectReasonRecommendation,
    };
}
