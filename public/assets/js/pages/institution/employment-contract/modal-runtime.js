import { notify } from '/public/assets/js/common/notification.js';
import {
    API, CONTRACT_TABLE_SETTINGS_KEY, CONTRACT_TABLE_SETTINGS_OPTIONS, EDITABLE,
    FIXED_TERM_DETAIL_REQUIRED, badge, escapeHtml, formatPickerDate,
    recommendedProjectReason, requestKey,
} from '/public/assets/js/pages/institution/employment-contract/shared.js';
import { getCachedDataTableMetaColumns, readDataTableSettingsState, resolveDataTableColumnDisplayName, resolveDataTableColumnRequirementPolicy } from '/public/assets/js/common/datatable/dataTableSettings.js';
import { getCodeOptions, getCodeName, initCodeSelectControls } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';

let AdminPicker;
let createHtmlGrid;
let normalizeHtmlGridNumberValue;
let normalizeTimeInputValue;
let compensationAmount;
let compensationSummary;
let formatCompensationAmount;
let componentCalculation;
let componentBasis;
let componentPolicyDisplay;
let usesComponentFormula;
let runDeleteProgress;
let modalRuntimePromise = null;
let modalRuntimeInitialized = false;

function loadModalRuntime() {
    if (!modalRuntimePromise) {
        modalRuntimePromise = Promise.all([
            import('/public/assets/js/common/picker/admin_picker.js'),
            import('/public/assets/js/common/html-grid/index.js'),
            import('/public/assets/js/common/html-grid/editors/number-editor.js'),
            import('/public/assets/js/common/values.js'),
            import('/public/assets/js/pages/institution/employment-contract/compensation.js'),
            import('/public/assets/js/common/delete-progress.js'),
        ]).then(([picker, htmlGrid, numberEditor, values, compensation, deleteProgress]) => {
            ({ AdminPicker } = picker);
            ({ createHtmlGrid } = htmlGrid);
            ({ normalizeHtmlGridNumberValue } = numberEditor);
            ({ normalizeTimeInputValue } = values);
            ({
                compensationAmount,
                compensationSummary,
                formatCompensationAmount,
                componentCalculation,
                componentBasis,
                componentPolicyDisplay,
                usesComponentFormula,
            } = compensation);
            ({ runDeleteProgress } = deleteProgress);
        }).catch(error => {
            modalRuntimePromise = null;
            throw error;
        });
    }
    return modalRuntimePromise;
}
const form = document.getElementById('employmentContractForm');
const modalElement = document.getElementById('employmentContractModal');
const modal = modalElement && window.bootstrap ? new window.bootstrap.Modal(modalElement) : null;
function setFormLabel(label, text, required) {
    if (!(label instanceof HTMLLabelElement) || !text) return;
    let requiredMark = label.querySelector('.text-danger');
    const requiredMarkAttributes = requiredMark
        ? Array.from(requiredMark.attributes).map(attribute => [attribute.name, attribute.value])
        : [];
    label.replaceChildren(document.createTextNode(`${text} `));
    if (!requiredMark) {
        requiredMark = document.createElement('span');
        requiredMark.className = 'text-danger';
        requiredMark.textContent = '*';
    }
    if (requiredMark) {
        requiredMarkAttributes.forEach(([name, value]) => requiredMark.setAttribute(name, value));
        requiredMark.classList.toggle('d-none', !required);
        label.append(requiredMark);
    }
}
function applyContractFormColumnMeta() {
    const metaColumns = getCachedDataTableMetaColumns({ metaDomain: 'employment-contract' });
    if (!metaColumns.length) return;
    const metaByKey = new Map(metaColumns.map(column => [String(column.key || ''), column]));
    const settingsState = readDataTableSettingsState(
        CONTRACT_TABLE_SETTINGS_KEY,
        CONTRACT_TABLE_SETTINGS_OPTIONS,
    );
    const conditionalFields = new Set([
        'contract_end_date', 'fixed_term_reason_code', 'fixed_term_reason_detail', 'project_id',
    ]);
    form.querySelectorAll('[name]').forEach(control => {
        const column = metaByKey.get(String(control.name || '').trim());
        const label = control.closest('[class*="col-"]')?.querySelector(':scope > label.form-label');
        if (!column || !label) return;
        const labelText = resolveDataTableColumnDisplayName(column, settingsState, column.label || column.key);
        const policy = resolveDataTableColumnRequirementPolicy(column, settingsState);
        const required = settingsState
            ? policy === 'required'
            : Boolean(column.required);
        control.dataset.columnMetaRequired = required ? '1' : '0';
        if (!conditionalFields.has(control.name) && !control.readOnly) {
            control.required = required;
        }
        setFormLabel(label, labelText, required);
    });
}
const trashElement = document.getElementById('employmentContractTrashModal');
const trashModal = trashElement && window.bootstrap ? new window.bootstrap.Modal(trashElement) : null;
const componentGridHost = document.getElementById('employmentComponentGrid');
const componentAddButton = document.getElementById('employmentComponentAdd');
const componentAllowanceDetails = document.getElementById('employmentComponentAllowanceDetails');
const compensationTotalLabel = document.getElementById('employmentCompensationTotalLabel');
const compensationTotalAmount = document.getElementById('employmentCompensationTotalAmount');
const compensationConvertedRow = document.getElementById('employmentCompensationConvertedRow');
const compensationConvertedLabel = document.getElementById('employmentCompensationConvertedLabel');
const compensationConvertedAmount = document.getElementById('employmentCompensationConvertedAmount');
const payComponentOptions = [];
const weekdayLabels = new Map();
const scheduleTypes = {};
const weeklyScheduleDayTypes = [];
let fixedTermContractPeriodType = '';
let modalOptionsPromise = null;
const weeklyScheduleGridHost = document.getElementById('employmentWeeklyScheduleGrid');
let current = null;
let table;
let componentGrid = null;
let weeklyScheduleGrid = null;
let weeklyScheduleExpanded = false;
let weeklyScheduleTouched = false;
let hydratingContract = false;
let lastScheduleType = '';
let componentRowSequence = 0;
let datePicker = null;
let activeDateInput = null;
const databaseMetaCache = new Map();
function isContractFormEditable() {
    const contractId = String(form?.elements?.namedItem('id')?.value || '').trim();
    if (contractId === '') return true;
    return EDITABLE.has(current?.contract_status || '');
}
function databaseMetaByKey(domain) {
    if (!databaseMetaCache.has(domain)) {
        const columns = getCachedDataTableMetaColumns({ metaDomain: domain });
        databaseMetaCache.set(domain, new Map(
            columns.map(column => [String(column.key || ''), column]),
        ));
    }
    return databaseMetaCache.get(domain);
}
function applySchedulePolicyColumnMeta() {
    const metaByKey = databaseMetaByKey('employment-contract-work-schedule-policy');
    form.querySelectorAll('[data-policy-field]').forEach(control => {
        const column = metaByKey.get(String(control.dataset.policyField || ''));
        const label = control.closest('[class*="col-"]')?.querySelector(':scope > label.form-label');
        if (!column || !label) return;
        const columnKey = String(column.key || control.dataset.policyField || '');
        const commentLabel = String(column.label || '').trim();
        if (commentLabel === '' || commentLabel === columnKey) return;
        setFormLabel(label, commentLabel, control.required || Boolean(column.required));
    });
}
function fixedTermFields() {
    return {
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
    };
}

function updateProjectReasonRecommendation() {
    const { contractPeriodType, reason, detail, project } = fixedTermFields();
    if (!contractPeriodType || contractPeriodType.value !== fixedTermContractPeriodType
        || !reason || !detail || !project || reason.value !== 'PROJECT_COMPLETION') return;
    const previous = detail.dataset.recommendedValue || '';
    const recommendation = recommendedProjectReason(project);
    if (detail.value.trim() === '' || detail.value === previous) {
        detail.value = recommendation;
        detail.dataset.recommendedValue = recommendation;
    }
}

function updateFixedTermPolicy() {
    const {
        contractPeriodType, endDateArea, area, endDate, reason, detail, project,
        endDateRequired, reasonRequired, detailRequired, warning,
    } = fixedTermFields();
    if (!contractPeriodType || !endDateArea || !area || !endDate || !reason || !detail || !project) return;
    const isFixedTerm = fixedTermContractPeriodType !== ''
        && contractPeriodType.value === fixedTermContractPeriodType;
    const reasonCode = isFixedTerm ? reason.value : '';
    const requiresDetail = FIXED_TERM_DETAIL_REQUIRED.has(reasonCode);
    const requiresProject = reasonCode === 'PROJECT_COMPLETION';
    const editable = isContractFormEditable();

    endDateArea.hidden = !isFixedTerm;
    area.hidden = !isFixedTerm;
    endDate.required = isFixedTerm;
    reason.required = isFixedTerm;
    detail.required = isFixedTerm && requiresDetail;
    project.required = project.dataset.columnMetaRequired === '1' || isFixedTerm && requiresProject;
    endDate.disabled = !isFixedTerm || !editable;
    reason.disabled = !isFixedTerm || !editable;
    detail.disabled = !isFixedTerm || !editable;
    endDateRequired?.classList.toggle('d-none', !isFixedTerm);
    reasonRequired?.classList.toggle('d-none', !isFixedTerm);
    detailRequired?.classList.toggle('d-none', !detail.required);
    const projectLabel = project.closest('[class*="col-"]')?.querySelector(':scope > label.form-label');
    const projectRequired = projectLabel?.querySelector('.text-danger');
    projectRequired?.classList.toggle('d-none', !project.required);

    if (!isFixedTerm) {
        endDate.value = '';
        reason.value = '';
        detail.value = '';
        delete detail.dataset.recommendedValue;
        window.jQuery?.(reason).trigger('change.select2');
    } else if (requiresProject) {
        updateProjectReasonRecommendation();
    } else {
        delete detail.dataset.recommendedValue;
    }

    let message = '';
    if (reasonCode === 'REVIEW_REQUIRED') {
        message = '검토 필요 사유는 임시저장만 가능합니다. 결재 요청 전에 적정한 기간제 계약 사유로 변경해 주세요.';
    } else if (reasonCode === 'SENIOR') {
        message = '고령자 적용 요건은 현재 직원정보만으로 확정할 수 없습니다. 요건을 확인할 수 없으면 검토 필요를 선택해 주세요.';
    }
    if (warning) {
        warning.textContent = message;
        warning.classList.toggle('d-none', message === '');
    }
    const submitButton = document.getElementById('employmentContractSubmit');
    if (submitButton) submitButton.disabled = reasonCode === 'REVIEW_REQUIRED';
}

function bindFixedTermPolicy() {
    const { contractPeriodType, reason, detail, project } = fixedTermFields();
    contractPeriodType?.addEventListener('change', updateFixedTermPolicy);
    reason?.addEventListener('change', updateFixedTermPolicy);
    window.jQuery?.(form).on(
        'select2:select select2:clear',
        '[name="contract_period_type"], [name="fixed_term_reason_code"]',
        updateFixedTermPolicy,
    );
    project?.addEventListener('change', () => {
        updateProjectReasonRecommendation();
        updateFixedTermPolicy();
    });
    detail?.addEventListener('input', () => {
        if (detail.value !== (detail.dataset.recommendedValue || '')) {
            delete detail.dataset.recommendedValue;
        }
    });
}


function initDatePicker() {
    const container = document.getElementById('employment-contract-date-picker');
    if (!container || datePicker) return datePicker;
    datePicker = AdminPicker.create({ type: 'today', container });
    datePicker.subscribe((_, date) => {
        if (!activeDateInput || !date) return;
        activeDateInput.value = formatPickerDate(date);
        activeDateInput.dispatchEvent(new Event('input', { bubbles: true }));
        activeDateInput.dispatchEvent(new Event('change', { bubbles: true }));
        datePicker.close();
    });
    return datePicker;
}

function openDatePicker(input) {
    const picker = initDatePicker();
    if (!picker || input.disabled) return;
    activeDateInput = input;
    if (typeof picker.clearDate === 'function') picker.clearDate();
    if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
        const [year, month, day] = input.value.split('-').map(Number);
        picker.setDate(new Date(year, month - 1, day));
    }
    picker.open({ anchor: input });
}

function bindDateInputs() {
    form.querySelectorAll('[data-employment-date]').forEach(input => {
        input.addEventListener('input', () => {
            const digits = input.value.replace(/\D/g, '').slice(0, 8);
            input.value = digits.length === 8
                ? `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6, 8)}`
                : digits;
        });
        input.addEventListener('click', () => openDatePicker(input));
        input.closest('.date-input')?.querySelector('.date-icon')?.addEventListener('click', () => openDatePicker(input));
    });
}

let timePicker = null;
let activeTimeInput = null;

function initTimePicker()
{
    const container = document.getElementById('employment-contract-time-picker');
    if (!container || timePicker) return timePicker;
    timePicker = AdminPicker.create({ type: 'time-list', container, options: { step: 1, rows: 8 } });
    timePicker.subscribe(state => {
        if (!activeTimeInput || typeof state?.hour !== 'number' || typeof state?.minute !== 'number') return;
        activeTimeInput.value = `${String(state.hour).padStart(2, '0')}:${String(state.minute).padStart(2, '0')}`;
        activeTimeInput.dispatchEvent(new Event('change', { bubbles: true }));
        timePicker.close();
    });
    return timePicker;
}

function openTimePickerForInput(input)
{
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
}

function bindTimeInputs()
{
    form.querySelectorAll('[data-employment-time]').forEach(input => {
        input.type = 'text';
        input.inputMode = 'numeric';
        input.maxLength = 5;
        input.placeholder = 'HH:MM';
        input.autocomplete = 'off';
        input.addEventListener('click', () => openTimePickerForInput(input));
        input.addEventListener('blur', () => {
            if (input.value.trim() === '') return;
            input.value = normalizeTimeInputValue(input.value);
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}

function weeklyScheduleRow(row = {}, rowState = 'created') {
    const day = Number(row.day_of_week || 0);
    const dayType = String(row.day_type || 'UNPAID_DAY_OFF');
    const workday = dayType === 'WORKDAY';
    const normal = form.elements.work_schedule_type?.value === scheduleTypes.normal;
    return {
        rowId: String(row.id || `weekly-schedule-${day}`),
        rowState,
        values: {
            id: row.id || '',
            day_of_week: day,
            day_type: dayType,
            start_time: workday ? String(row.start_time || '').slice(0, 5) : null,
            end_time: workday ? String(row.end_time || '').slice(0, 5) : null,
            end_day_offset: workday ? (normal ? 0 : row.end_day_offset ?? null) : null,
            break_minutes: workday ? row.break_minutes ?? null : null,
            break_schedules_text: workday ? (row.break_schedules || []).map(item => `${String(item.start_time || '').slice(0, 5)}-${String(item.end_time || '').slice(0, 5)}${Number(item.end_day_offset) === 1 ? '+1' : ''}`).join(', ') : '',
        },
    };
}

function defaultWeeklySchedules(type, defaults = {}) {
    return Array.from(weekdayLabels.keys()).map(day => {
        const workday = day <= 5;
        return {
            day_of_week: day,
            day_type: workday ? 'WORKDAY' : (day === 7 ? 'WEEKLY_HOLIDAY' : 'UNPAID_DAY_OFF'),
            start_time: workday ? defaults.start_time : null,
            end_time: workday ? defaults.end_time : null,
            end_day_offset: workday ? defaults.end_day_offset : null,
            break_minutes: workday ? defaults.break_minutes : null,
            break_schedules_text: '',
        };
    });
}

function createScheduleTimeEditor(context = {}) {
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'html-grid-editor';
    input.placeholder = 'HH:MM';
    input.maxLength = 5;
    input.setAttribute('data-employment-time', 'true');
    input.inputMode = 'numeric';
    input.value = String(context.value || '').slice(0, 5);
    input.disabled = context.row?.values?.day_type !== 'WORKDAY' || !isContractFormEditable();
    return {
        element: input,
        create: () => input,
        mount: host => host.append(input),
        focus: () => input.focus(),
        getValue: () => {
            if (input.disabled || input.value.trim() === '') return null;
            const normalized = normalizeTimeInputValue(input.value);
            input.value = normalized;
            return normalized || null;
        },
        destroy: () => input.remove(),
    };
}

function createEmploymentTimePickerAdapter({ editorElement } = {}) {
    if (!editorElement) return null;
    const openPicker = () => openTimePickerForInput(editorElement);
    editorElement.addEventListener('click', openPicker);
    return {
        destroy() {
            editorElement.removeEventListener('click', openPicker);
            if (activeTimeInput === editorElement) activeTimeInput = null;
        },
    };
}

function createScheduleOffsetEditor(context = {}) {
    const select = document.createElement('select');
    select.className = 'html-grid-editor';
    [{ value: '0', label: '당일' }, { value: '1', label: '익일' }].forEach(option => {
        const element = document.createElement('option');
        element.value = option.value;
        element.textContent = option.label;
        select.append(element);
    });
    const normal = form.elements.work_schedule_type?.value === scheduleTypes.normal;
    select.value = String(normal ? 0 : context.value ?? 0);
    select.disabled = context.row?.values?.day_type !== 'WORKDAY' || normal || !isContractFormEditable();
    return {
        element: select,
        create: () => select,
        mount: host => host.append(select),
        focus: () => select.focus(),
        getValue: () => context.row?.values?.day_type !== 'WORKDAY' ? null : Number(normal ? 0 : select.value),
        destroy: () => select.remove(),
    };
}

function createScheduleBreakEditor(context = {}) {
    const input = document.createElement('input');
    input.type = 'text';
    input.inputMode = 'numeric';
    input.className = 'html-grid-editor html-grid-editor-number';
    input.value = context.value ?? '';
    input.disabled = context.row?.values?.day_type !== 'WORKDAY' || !isContractFormEditable();
    return {
        element: input,
        create: () => input,
        mount: host => host.append(input),
        focus: () => input.focus(),
        getValue: () => input.disabled ? null : normalizeHtmlGridNumberValue(input.value, false),
        destroy: () => input.remove(),
    };
}

function weeklyScheduleRowErrors(row, state) {
    const values = row.values || {};
    const rows = state.rows.filter(entry => entry.rowState !== 'deleted');
    const errors = [];
    const holidayCount = rows.filter(entry => entry.values.day_type === 'WEEKLY_HOLIDAY').length;
    const workdayCount = rows.filter(entry => entry.values.day_type === 'WORKDAY').length;
    const weekdays = rows.map(entry => Number(entry.values.day_of_week));
    if (values.day_of_week === 1 && (rows.length !== 7 || new Set(weekdays).size !== 7)) {
        errors.push({ columnKey: 'day_of_week', message: '월요일부터 일요일까지 7개 요일을 중복 없이 입력해 주세요.' });
    }
    if (values.day_of_week === 1 && holidayCount !== 1) {
        errors.push({ columnKey: 'day_type', message: '유급 주휴일을 정확히 한 요일로 지정해 주세요.' });
    }
    if (values.day_of_week === 1 && workdayCount === 0) {
        errors.push({ columnKey: 'day_type', message: '근무일을 한 요일 이상 지정해 주세요.' });
    }
    if (values.day_type !== 'WORKDAY') return errors;
    if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(String(values.start_time || ''))) {
        errors.push({ columnKey: 'start_time', message: '출근시간을 HH:MM 형식으로 입력해 주세요.' });
    }
    if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(String(values.end_time || ''))) {
        errors.push({ columnKey: 'end_time', message: '퇴근시간을 HH:MM 형식으로 입력해 주세요.' });
    }
    if (values.break_minutes === null || values.break_minutes === '' || Number(values.break_minutes) < 0) {
        errors.push({ columnKey: 'break_minutes', message: '휴게시간(분)을 입력해 주세요.' });
    }
    const parsedBreaks = parseBreakSchedules(values.break_schedules_text);
    if (Number(values.break_minutes) > 0 && (parsedBreaks.length === 0 || parsedBreaks.some(item => item.end_day_offset < 0))) {
        errors.push({ columnKey: 'break_schedules_text', message: '상세 휴게구간을 12:00-13:00 형식으로 입력해 주세요. 익일 종료는 +1을 붙입니다.' });
    }
    const type = form.elements.work_schedule_type?.value;
    if (type === scheduleTypes.night && ![0, 1, '0', '1'].includes(values.end_day_offset)) {
        errors.push({ columnKey: 'end_day_offset', message: '야간근무의 퇴근일을 선택해 주세요.' });
    }
    if (type === scheduleTypes.normal && Number(values.end_day_offset) !== 0) {
        errors.push({ columnKey: 'end_day_offset', message: '일반근무는 당일 퇴근만 허용됩니다.' });
    }
    const startMatch = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(String(values.start_time || ''));
    const endMatch = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(String(values.end_time || ''));
    const offset = Number(values.end_day_offset);
    const breakMinutes = Number(values.break_minutes);
    if (startMatch && endMatch && [0, 1].includes(offset) && Number.isFinite(breakMinutes)) {
        const grossMinutes = Number(endMatch[1]) * 60 + Number(endMatch[2]) + 1440 * offset
            - Number(startMatch[1]) * 60 - Number(startMatch[2]);
        if (grossMinutes <= 0) {
            errors.push({ columnKey: 'end_time', message: '퇴근 시각은 출근 시각 이후여야 합니다.' });
        } else if (breakMinutes >= grossMinutes) {
            errors.push({ columnKey: 'break_minutes', message: '휴게시간은 출근부터 퇴근까지의 시간보다 짧아야 합니다.' });
        } else {
            const netMinutes = grossMinutes - breakMinutes;
            const requiredBreak = netMinutes >= 480 ? 60 : (netMinutes >= 240 ? 30 : 0);
            if (breakMinutes < requiredBreak) {
                errors.push({ columnKey: 'break_minutes', message: '근로시간에 필요한 최소 휴게시간을 입력해 주세요.' });
            }
        }
    }
    return errors;
}

function ensureWeeklyScheduleGrid() {
    if (weeklyScheduleGrid || !weeklyScheduleGridHost) return weeklyScheduleGrid;
    const meta = databaseMetaByKey('employment-contract-weekly-schedule');
    const column = (key, fallback, config = {}) => ({
        key,
        label: meta.get(key)?.label || fallback,
        ...config,
    });
    weeklyScheduleGrid = createHtmlGrid({
        host: weeklyScheduleGridHost,
        gridId: 'employment-contract-weekly-schedules',
        rows: [],
        commitEditorsOnChange: true,
        commitEditorsBeforeRead: true,
        columns: [
            column('day_of_week', '요일', { width: 100, formatter: 'employment-weekday', editable: false, required: true }),
            column('day_type', '근무구분', { width: 190, editor: 'select', formatter: 'employment-day-type', required: true, meta: { editorOptions: { options: weeklyScheduleDayTypes } } }),
            column('start_time', '출근시간', { width: 135, editor: 'employment-schedule-time', plugins: ['time-picker'], meta: { requiredIndicator: true } }),
            column('end_time', '퇴근시간', { width: 135, editor: 'employment-schedule-time', plugins: ['time-picker'], meta: { requiredIndicator: true } }),
            column('end_day_offset', '퇴근일구분', { width: 125, editor: 'employment-schedule-offset', meta: { requiredIndicator: true } }),
            column('break_minutes', '휴게시간(분)', { type: 'number', width: 135, editor: 'employment-schedule-break', meta: { requiredIndicator: true } }),
            column('break_schedules_text', '상세 휴게구간', { width: 210, editor: 'text', meta: { requiredIndicator: true } }),
        ],
        adapters: {
            timePicker: createEmploymentTimePickerAdapter,
        },
        editors: {
            'employment-schedule-time': createScheduleTimeEditor,
            'employment-schedule-offset': createScheduleOffsetEditor,
            'employment-schedule-break': createScheduleBreakEditor,
        },
        formatters: {
            'employment-weekday': value => weekdayLabels.get(Number(value)) || value,
            'employment-day-type': value => weeklyScheduleDayTypes.find(option => option.value === value)?.label || value,
        },
        hooks: {
            validator: { validateRow: ({ row, state }) => weeklyScheduleRowErrors(row, state) },
            serializer: { serializeRow: ({ row }) => row.rowState === 'deleted' ? null : { ...row.values } },
        },
        capabilities: {
            addRow: false, deleteRow: false, reorder: false, selection: false, footer: false,
            keyboard: true, columnResize: false, columnHide: false, columnMove: false, clipboard: false,
        },
    });
    weeklyScheduleGrid.on('cell:changed', ({ row, columnKey }) => {
        weeklyScheduleTouched = true;
        if (columnKey === 'day_type') {
            const rowIndex = weeklyScheduleGrid.getState().rows.findIndex(entry => entry.rowId === row.rowId);
            const workday = row.values.day_type === 'WORKDAY';
            weeklyScheduleGrid.updateRow(rowIndex, {
                start_time: workday ? row.values.start_time : null,
                end_time: workday ? row.values.end_time : null,
                end_day_offset: workday && form.elements.work_schedule_type.value === scheduleTypes.normal ? 0 : (workday ? null : null),
                break_minutes: workday ? row.values.break_minutes : null,
                break_schedules_text: workday ? row.values.break_schedules_text : '',
            });
        }
        updateScheduleSummary();
    });
    weeklyScheduleGrid.render({ noDataMessage: '근무형태를 선택해 주세요.' });
    return weeklyScheduleGrid;
}

function setWeeklySchedules(rows = [], rowState = 'clean') {
    const grid = ensureWeeklyScheduleGrid();
    if (!grid) return;
    const state = grid.getState();
    state.rows = [...rows]
        .sort((left, right) => Number(left.day_of_week || 0) - Number(right.day_of_week || 0))
        .map(row => weeklyScheduleRow(row, row.id ? 'clean' : rowState));
    state.cells = {};
    state.selection = { activeCell: null, range: null, selectedRowIds: [] };
    grid.setState(state);
    weeklyScheduleTouched = false;
    updateScheduleSummary();
}

function serializeWeeklySchedules() {
    const type = form.elements.work_schedule_type?.value || '';
    return (weeklyScheduleGrid?.serialize().rows || []).map(row => {
        const workday = row.day_type === 'WORKDAY';
        return {
            day_of_week: Number(row.day_of_week),
            day_type: row.day_type,
            start_time: workday ? String(row.start_time || '').slice(0, 5) : null,
            end_time: workday ? String(row.end_time || '').slice(0, 5) : null,
            end_day_offset: workday
                ? (type === scheduleTypes.normal ? 0 : Number(row.end_day_offset))
                : null,
            break_minutes: workday ? Number(row.break_minutes) : null,
            break_schedules: workday ? parseBreakSchedules(row.break_schedules_text) : [],
        };
    });
}

function parseBreakSchedules(value) {
    const text = String(value || '').trim();
    if (!text) return [];
    return text.split(',').map(part => {
        const match = part.trim().match(/^([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)(\+1)?$/);
        if (!match) return { start_time: '', end_time: '', end_day_offset: -1 };
        return { start_time: `${match[1]}:${match[2]}`, end_time: `${match[3]}:${match[4]}`, end_day_offset: match[5] ? 1 : 0 };
    });
}

function setWeeklyScheduleExpanded(expanded) {
    weeklyScheduleExpanded = Boolean(expanded);
    const type = form.elements.work_schedule_type?.value || '';
    const weekly = [scheduleTypes.normal, scheduleTypes.night, scheduleTypes.flexible, scheduleTypes.other].includes(type);
    const policyActive = Boolean(type) && ![scheduleTypes.normal, scheduleTypes.night].includes(type);
    const weeklyArea = document.getElementById('employmentWeeklyScheduleArea');
    const policyArea = document.getElementById('employmentSchedulePolicyArea');
    const toggle = document.getElementById('employmentWeeklyScheduleToggle');
    if (weeklyArea) weeklyArea.hidden = !weeklyScheduleExpanded || !weekly;
    if (policyArea) policyArea.hidden = !policyActive;
    if (toggle) {
        toggle.textContent = weeklyScheduleExpanded ? '상세 숨기기' : '상세 보기';
        toggle.setAttribute('aria-expanded', weeklyScheduleExpanded ? 'true' : 'false');
    }
}

function scheduleDefaultsForType(type) {
    if ([scheduleTypes.normal, scheduleTypes.flexible, scheduleTypes.other].includes(type)) {
        return {
            start_time: '09:00',
            end_time: '18:00',
            end_day_offset: 0,
            break_minutes: 60,
        };
    }
    if (type === scheduleTypes.night) {
        return {
            start_time: '22:00',
            end_time: '06:00',
            end_day_offset: 1,
            break_minutes: 60,
        };
    }
    return {
        start_time: null,
        end_time: null,
        end_day_offset: null,
        break_minutes: null,
    };
}

function schedulePolicyDefaultsForType(type) {
    return { settlement_period_days: null, reference_weekly_hours: null,
        selectable_start_time: null, selectable_end_time: null, core_start_time: null,
        core_end_time: null, policy_detail: '' };
}

function applyWeeklyScheduleDefaults({ confirmOverwrite = true, announce = true } = {}) {
    applyState();
    updateScheduleTypePolicy();
    const type = form.elements.work_schedule_type.value;
    if (!type) {
        notify('warning', '근무형태를 먼저 선택해 주세요.');
        form.elements.work_schedule_type.focus();
        return;
    }
    const weekly = [scheduleTypes.normal, scheduleTypes.night, scheduleTypes.flexible, scheduleTypes.other].includes(type);
    if (!weekly) {
        const hasPolicyValue = Array.from(document.querySelectorAll('[data-policy-field]'))
            .some(input => String(input.value || '').trim() !== '');
        if (confirmOverwrite && hasPolicyValue
            && !window.confirm('현재 근무형태 상세값을 기본 상태로 초기화하시겠습니까?')) return;
        setSchedulePolicy(schedulePolicyDefaultsForType(type));
        updateScheduleTypePolicy();
        if (announce) notify('success', '선택한 근무형태의 기본설정이 적용되었습니다.');
        return;
    }
    const currentRows = serializeWeeklySchedules();
    const hasScheduleValue = currentRows.some(row => row.day_type === 'WORKDAY'
        && (row.start_time || row.end_time || row.break_minutes !== null && row.break_minutes !== ''));
    if (confirmOverwrite && (weeklyScheduleTouched || hasScheduleValue)
        && !window.confirm('현재 요일별 근무조건을 기본설정으로 초기화하시겠습니까?')) return;
    setWeeklySchedules(defaultWeeklySchedules(type, scheduleDefaultsForType(type)), 'created');
    setWeeklyScheduleExpanded(true);
    updateScheduleSummary();
    if (announce) {
        const message = type === scheduleTypes.normal
            ? '일반근무 기본 일정(09:00~18:00, 휴게 60분)이 적용되었습니다.'
            : '야간근무 기본 요일 일정이 적용되었습니다. 상세표에서 시간을 입력해 주세요.';
        notify('success', message);
    }
}
function updateScheduleSummary() {
    const scheduleRows = serializeWeeklySchedules();
    const summary = document.getElementById('employmentWorkdaySummary');
    if (scheduleRows.length === 0 && current?.id) {
        summary.textContent = '저장된 주간 근무일정이 없습니다. 기본설정을 적용한 뒤 임시저장해 주세요.';
        return;
    }
    const rows = scheduleRows.filter(row => row.day_type === 'WORKDAY');
    let minutes = 0;
    let valid = rows.length > 0;
    rows.forEach(row => {
        const start = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(row.start_time || '');
        const end = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(row.end_time || '');
        const breakMinutes = Number(row.break_minutes);
        if (!start || !end || !Number.isFinite(breakMinutes)) { valid = false; return; }
        const gross = Number(end[1]) * 60 + Number(end[2]) + 1440 * Number(row.end_day_offset)
            - Number(start[1]) * 60 - Number(start[2]);
        if (gross <= breakMinutes) { valid = false; return; }
        minutes += gross - breakMinutes;
    });
    if (!valid) {
        summary.textContent = '근무일의 출퇴근·퇴근일·휴게시간을 입력하면 집계값이 표시됩니다.';
        return;
    }
    const hours = minutes / 60;
    const weeklyHoliday = scheduleRows.find(row => row.day_type === 'WEEKLY_HOLIDAY');
    const weeklyHolidayLabel = weeklyHoliday
        ? (weekdayLabels.get(Number(weeklyHoliday.day_of_week)) || `${weeklyHoliday.day_of_week}요일`)
        : '미지정';
    summary.textContent = `주근무일수 ${rows.length}일 · 주근무시간 ${hours.toFixed(2)}시간 · 평균 일근무시간 ${(hours / rows.length).toFixed(2)}시간 · 주휴일 ${weeklyHolidayLabel}`;
}
function serializeSchedulePolicy() {
    return Object.fromEntries(Array.from(document.querySelectorAll('[data-policy-field]'))
        .map(input => [input.dataset.policyField, input.value || null]));
}

function setSchedulePolicy(policy = {}) {
    document.querySelectorAll('[data-policy-field]').forEach(input => {
        const value = policy[input.dataset.policyField];
        input.value = input.hasAttribute('data-employment-time')
            ? String(value || '').slice(0, 5)
            : String(value ?? '');
    });
}

function updateScheduleTypePolicy({ applyDefaults = false } = {}) {
    const type = form.elements.work_schedule_type.value;
    const weekly = [scheduleTypes.normal, scheduleTypes.night, scheduleTypes.flexible, scheduleTypes.other].includes(type);
    const policyActive = Boolean(type) && ![scheduleTypes.normal, scheduleTypes.night].includes(type);
    const typeChanged = type !== lastScheduleType;
    const actions = document.getElementById('employmentWeeklyScheduleActions');
    const toggle = document.getElementById('employmentWeeklyScheduleToggle');
    if (actions) actions.hidden = false;
    if (toggle) toggle.hidden = false;
    if (applyDefaults && typeChanged && policyActive) {
        setSchedulePolicy(schedulePolicyDefaultsForType(type));
    }
    document.getElementById('employmentSchedulePolicyArea').hidden = !policyActive;
    document.querySelectorAll('[data-policy-types]').forEach(area => {
        area.hidden = !String(area.dataset.policyTypes || '').split(' ').includes(type);
    });
    document.querySelectorAll('[data-policy-field]').forEach(input => {
        const visible = !input.closest('[data-policy-types]')?.hidden;
        input.disabled = !policyActive || !visible || !isContractFormEditable() || input.readOnly;
        input.required = policyActive && visible && (
            input.dataset.policyField === 'policy_detail'
            || type === scheduleTypes.selective && ['reference_weekly_hours', 'selectable_start_time', 'selectable_end_time'].includes(input.dataset.policyField)
            || type === scheduleTypes.flexible && input.dataset.policyField === 'settlement_period_days');
        input.closest('[data-policy-types]')?.querySelector('.text-danger')?.classList.toggle('d-none', !input.required);
        if (input.disabled) {
            input.value = '';
            input.setCustomValidity('');
        }
    });
    if (!weekly) {
        if (serializeWeeklySchedules().length) setWeeklySchedules([]);
        if (typeChanged && type) setWeeklyScheduleExpanded(true);
        else if (!type) setWeeklyScheduleExpanded(false);
        if (applyDefaults && typeChanged && policyActive) {
            notify('info', '선택한 근무형태의 입력값이 기본 상태로 초기화되었습니다.');
        }
    } else {
        ensureWeeklyScheduleGrid();
        if (applyDefaults && typeChanged) {
            setWeeklySchedules(defaultWeeklySchedules(type, scheduleDefaultsForType(type)), 'created');
            setWeeklyScheduleExpanded(true);
            const message = type === scheduleTypes.normal
                ? '일반근무 기본 일정(09:00~18:00, 휴게 60분)이 자동 적용되었습니다.'
                : '야간근무 기본 일정(22:00~익일 06:00, 휴게 60분)이 자동 적용되었습니다.';
            notify('success', message);
        } else {
            if (typeChanged) setWeeklyScheduleExpanded(false);
            const state = weeklyScheduleGrid.getState();
            state.rows = state.rows.map(row => weeklyScheduleRow(row.values, row.rowState));
            weeklyScheduleGrid.setState(state);
        }
    }
    lastScheduleType = type;
    applySchedulePolicyColumnMeta();
    updateScheduleSummary();
}
function initBusinessSelects() {
    form.querySelectorAll('select:not([data-code-group]):not([name="employee_id"]):not([name="project_id"])').forEach(select => {
        if (!select.name) return;
        AdminPicker.select2(select, {
            width: '100%',
            dropdownParent: window.jQuery(modalElement),
            allowClear: !select.required,
        });
    });
}

function initSearchPickers() {
    const pickerOptions = {
        width: '100%',
        dropdownParent: window.jQuery(modalElement),
        minimumInputLength: 0,
        allowClear: true,
    };
    AdminPicker.select2Ajax(form.elements.employee_id, { ...pickerOptions, url: API.employeeSearch });
    AdminPicker.select2Ajax(form.elements.project_id, { ...pickerOptions, url: API.projectSearch });
}

async function prepareModalRuntime() {
    await loadModalRuntime();
    if (!modalRuntimeInitialized) {
        initBusinessSelects();
        initSearchPickers();
        bindDateInputs();
        bindTimeInputs();
        bindFixedTermPolicy();
        modalRuntimeInitialized = true;
    }
    return ensureModalOptions();
}

async function ensureModalOptions() {
    if (!modalOptionsPromise) {
        modalOptionsPromise = (async () => {
            const [payload] = await Promise.all([
                request(API.options),
                initCodeSelectControls(modalElement),
            ]);
            const options = payload.data || {};
            payComponentOptions.splice(0, payComponentOptions.length, ...(options.pay_components || []));
            weekdayLabels.clear();
            (options.weekdays || []).forEach(day => weekdayLabels.set(Number(day.value), day.label));
            Object.assign(scheduleTypes, options.work_schedule_types || {});
            weeklyScheduleDayTypes.splice(0, weeklyScheduleDayTypes.length, ...(options.weekly_schedule_day_types || []));
            fixedTermContractPeriodType = String(options.fixed_term_contract_period_type || '');
            COMPONENT_OPTIONS.work_type.splice(0, COMPONENT_OPTIONS.work_type.length, ...(options.component_input_options?.work_type || []));
            COMPONENT_OPTIONS.excess_payment_policy.splice(0, COMPONENT_OPTIONS.excess_payment_policy.length, ...(options.component_input_options?.excess_payment_policy || []));
            payComponentById.clear();
            payComponentOptions.forEach(option => payComponentById.set(String(option.value), option.meta || {}));
            ensureWeeklyScheduleGrid();
            return options;
        })().catch(error => {
            modalOptionsPromise = null;
            throw error;
        });
    }
    return modalOptionsPromise;
}
function updateCodeAvailability() {
    const missing = Array.from(form.querySelectorAll('select[data-code-group]'))
        .filter(select => !select.disabled && !Array.from(select.options).some(option => (
            option.value !== '' && option.value !== '__CODE_QUICK_ADD__'
        )))
        .map(select => select.dataset.codeGroup);
    const warning = document.getElementById('employmentContractCodeWarning');
    if (warning) {
        warning.classList.toggle('d-none', missing.length === 0);
        warning.textContent = missing.length === 0
            ? ''
            : `코드관리에 다음 코드그룹의 활성 코드를 먼저 등록해 주세요: ${missing.join(', ')}`;
    }
    return missing.length === 0;
}

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' },
        ...options,
    });
    const payload = await response.json().catch(() => ({ success: false, message: '응답을 확인할 수 없습니다.' }));
    if (!response.ok || !payload.success) throw new Error(payload.message || '처리 중 오류가 발생했습니다.');
    return payload;
}

const COMPONENT_OPTIONS = Object.freeze({
    pay_component_id: payComponentOptions,
    work_type: [],
    excess_payment_policy: [],
});
const payComponentById = new Map(payComponentOptions.map(option => [String(option.value), option.meta || {}]));

function optionLabel(optionKey, value) {
    return COMPONENT_OPTIONS[optionKey]?.find(option => String(option.value) === String(value))?.label || value || '';
}

function updateCompensationSummary() {
    const totalAmount = compensationAmount(componentGrid?.getState().rows || []);
    const salaryType = String(form.elements.salary_type?.value || '');
    const summary = compensationSummary(totalAmount, salaryType);
    if (compensationTotalLabel) compensationTotalLabel.textContent = summary.totalLabel;
    if (compensationTotalAmount) compensationTotalAmount.textContent = formatCompensationAmount(totalAmount);
    const showConverted = summary.convertedAmount !== null;
    compensationConvertedRow?.classList.toggle('d-none', !showConverted);
    if (compensationConvertedLabel) compensationConvertedLabel.textContent = summary.convertedLabel;
    if (compensationConvertedAmount) compensationConvertedAmount.textContent = showConverted ? formatCompensationAmount(summary.convertedAmount) : '';
}
function hasDuplicatePayComponent(payComponentId, currentRowId) {
    const selectedId = String(payComponentId || '');
    if (!selectedId || !componentGrid) return false;
    return componentGrid.getState().rows.some(row => (
        row.rowId !== currentRowId
        && row.rowState !== 'deleted'
        && String(row.values?.pay_component_id || '') === selectedId
    ));
}
function componentMaster(componentId) { return payComponentById.get(String(componentId || '')) || {}; }
function recalculateFormulaComponents() {
    if (!componentGrid) return;
    const state = componentGrid.getState();
    const activeRows = state.rows.filter(row => row.rowState !== 'deleted');
    const baseRow = activeRows.find(row => String(row.values.component_type || componentMaster(row.values.pay_component_id).component_type || '') === 'BASE_PAY');
    const baseRate = Number(baseRow?.values?.rate || 0);
    if (baseRow) {
        const calculation = componentCalculation(baseRow.values, componentMaster(baseRow.values.pay_component_id));
        if (calculation.calculatedAmount !== null) baseRow.values.amount = String(calculation.calculatedAmount);
    }
    activeRows.forEach(row => {
        if (row === baseRow || !usesComponentFormula(row.values, componentMaster(row.values.pay_component_id))) return;
        row.values.rate = baseRate > 0 ? baseRate : null;
        const calculation = componentCalculation(row.values, componentMaster(row.values.pay_component_id));
        if (calculation.calculatedAmount !== null) row.values.amount = String(calculation.calculatedAmount);
    });
    componentGrid.setState(state);
    syncComponentHeaderActionButton();
}
function isWorkAllowance(values = {}) { return String(values.component_type || componentMaster(values.pay_component_id).component_type || '') === 'STATUTORY_PREMIUM'; }
function normalizeComponentPolicy(values = {}) {
    const master = componentMaster(values.pay_component_id);
    const basePay = String(master.component_type || values.component_type || '') === 'BASE_PAY';
    const workAllowance = String(master.component_type || values.component_type || '') === 'STATUTORY_PREMIUM';
    const formulaComponent = usesComponentFormula(values, master);
    return {
        ...values,
        component_type: master.component_type || values.component_type || '',
        calculation_type: master.default_calculation_type || values.calculation_type || '',
        tax_type: master.default_tax_type || values.tax_type || '',
        tax_policy_code: master.tax_policy_code ?? values.tax_policy_code ?? null,
        minimum_wage_treatment: master.minimum_wage_treatment || values.minimum_wage_treatment || '',
        ordinary_wage_treatment: master.ordinary_wage_treatment || values.ordinary_wage_treatment || '',
        average_wage_treatment: master.average_wage_treatment || values.average_wage_treatment || '',
        is_fixed: String((master.default_calculation_type || values.calculation_type) === 'FIXED_AMOUNT' ? 1 : 0),
        work_type: workAllowance ? (values.work_type || '') : '',
        quantity: formulaComponent ? (values.quantity ?? null) : null,
        premium_rate: workAllowance ? (values.premium_rate ?? null) : null,
        excess_payment_policy: workAllowance ? (values.excess_payment_policy || '') : '',
        agreement_basis: workAllowance ? (values.agreement_basis || '') : '',
        note: workAllowance ? (values.note || '') : '',
    };
}
function componentGridRow(row = {}, rowState = 'clean') {
    const rowId = String(row.id || `component-${Date.now()}-${++componentRowSequence}`);
    return {
        rowId,
        rowState,
        values: normalizeComponentPolicy({
            id: row.id || '', pay_component_id: row.pay_component_id || '',
            component_name: row.component_name || row.master_component_name || '',
            component_type: row.component_type || '',
            calculation_type: row.calculation_type || '',
            amount: row.amount ?? '',
            rate: row.rate ?? null,
            quantity: row.quantity ?? null,
            tax_type: row.tax_type || '',
            tax_policy_code: row.tax_policy_code ?? null,
            work_type: row.work_type || '',
            premium_rate: row.premium_rate ?? null,
            excess_payment_policy: row.excess_payment_policy || '',
            agreement_basis: row.agreement_basis || '',
            is_fixed: String(row.is_fixed ?? 1),
            minimum_wage_treatment: row.minimum_wage_treatment || '',
            ordinary_wage_treatment: row.ordinary_wage_treatment || '',
            average_wage_treatment: row.average_wage_treatment || '',
            wage_treatment_basis: row.wage_treatment_basis || '',
            note: row.note || '',
        }),
    };
}

function createComponentDeleteEditor(context = {}) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-link btn-sm employment-component-delete';
    button.textContent = '-삭제';
    button.setAttribute('aria-label', '지급조건 삭제');
    button.title = '지급조건 삭제';
    button.addEventListener('click', () => {
        const rowIndex = componentGrid?.getState().rows.findIndex(row => row.rowId === context.row?.rowId) ?? -1;
        if (rowIndex >= 0) componentGrid.deleteRow(rowIndex);
    });
    return {
        element: button,
        create: () => button,
        mount: host => host.appendChild(button),
        getValue: () => '',
        destroy: () => button.remove(),
    };
}

function allowanceValidation(row = {}) {
    const values = row.values || {};
    const errors = [];
    if (Number(values.amount || 0) <= 0) {
        errors.push({ columnKey: 'amount', message: '계약금액은 0원보다 커야 합니다.' });
    }
    if (hasDuplicatePayComponent(values.pay_component_id, row.rowId)) {
        errors.push({ columnKey: 'pay_component_id', message: '동일한 지급항목은 한 번만 추가할 수 있습니다.' });
    }
    const formula = usesComponentFormula(values, componentMaster(values.pay_component_id));
    if (formula && Number(values.rate || 0) <= 0) {
        errors.push({ columnKey: 'formula_display', message: '기본급 계약금액과 계산시간을 먼저 입력해 주세요.' });
    }
    if (formula && Number(values.quantity || 0) <= 0) {
        errors.push({ columnKey: 'formula_display', message: '산식의 계산수량·시간을 입력해 주세요.' });
    }
    if (!isWorkAllowance(values)) return errors;
    if (!values.work_type) errors.push({ columnKey: 'pay_component_id', message: '수당 적용 구분을 선택해 주세요.' });
    if (Number(values.quantity || 0) <= 0) errors.push({ columnKey: 'pay_component_id', message: '계산수량을 입력해 주세요.' });
    if (Number(values.premium_rate || 0) <= 0) errors.push({ columnKey: 'pay_component_id', message: '가산율을 입력해 주세요.' });
    if (!COMPONENT_OPTIONS.excess_payment_policy.some(option => option.value && option.value === values.excess_payment_policy)) errors.push({ columnKey: 'pay_component_id', message: '초과근로 정산방법을 선택해 주세요.' });
    if (!String(values.agreement_basis || '').trim()) errors.push({ columnKey: 'pay_component_id', message: '산정 및 약정 근거를 입력해 주세요.' });
    return errors;
}

function renderAllowanceDetails() {
    if (!componentAllowanceDetails || !componentGrid) return;
    const rows = componentGrid.getState().rows.filter(row => row.rowState !== 'deleted'
        && usesComponentFormula(row.values, componentMaster(row.values.pay_component_id)));
    componentAllowanceDetails.replaceChildren();
    componentAllowanceDetails.classList.toggle('d-none', rows.length === 0);
    rows.forEach((row, index) => {
        const values = row.values;
        const workAllowance = isWorkAllowance(values);
        const basePay = String(values.component_type || componentMaster(values.pay_component_id).component_type || '') === 'BASE_PAY';
        const rateField = basePay
            ? `<label>기준 단가 <span class="text-danger">*</span><input class="form-control form-control-sm" inputmode="decimal" data-component-detail="rate" data-number-value value="${escapeHtml(values.rate ?? '')}"></label>`
            : `<label>기준 단가<input class="form-control form-control-sm" value="${escapeHtml(values.rate ?? '')}" readonly aria-label="기본급 기준 단가 자동 연동"></label>`;
        const calculationFields = basePay
            ? `<label>계산수량 <span class="text-danger">*</span><input class="form-control form-control-sm" inputmode="decimal" data-component-detail="quantity" data-number-value value="${escapeHtml(values.quantity ?? '')}"></label>
                ${rateField}`
            : workAllowance
            ? `<label>계산수량 <span class="text-danger">*</span><input class="form-control form-control-sm" inputmode="decimal" data-component-detail="quantity" data-number-value value="${escapeHtml(values.quantity ?? '')}"></label>
                ${rateField}
                <label>가산율(배) <span class="text-danger">*</span><input class="form-control form-control-sm" inputmode="decimal" data-component-detail="premium_rate" data-number-value value="${escapeHtml(values.premium_rate ?? '')}"></label>`
            : `<label>계산수량 <span class="text-danger">*</span><input class="form-control form-control-sm" inputmode="decimal" data-component-detail="quantity" data-number-value value="${escapeHtml(values.quantity ?? '')}"></label>
                ${rateField}`;
        const allowanceFields = workAllowance
            ? `<label>수당 적용 구분 <span class="text-danger">*</span><select class="form-select form-select-sm" data-component-detail="work_type">${COMPONENT_OPTIONS.work_type.map(option => `<option value="${escapeHtml(option.value)}"${String(option.value) === String(values.work_type) ? ' selected' : ''}>${escapeHtml(option.label)}</option>`).join('')}</select></label>
                <label>초과근로 정산방법 <span class="text-danger">*</span><select class="form-select form-select-sm" data-component-detail="excess_payment_policy">${COMPONENT_OPTIONS.excess_payment_policy.map(option => `<option value="${escapeHtml(option.value)}"${String(option.value) === String(values.excess_payment_policy) ? ' selected' : ''}>${escapeHtml(option.label)}</option>`).join('')}</select></label>
                <label class="employment-allowance-detail__wide">산정 및 약정 근거 <span class="text-danger">*</span><input class="form-control form-control-sm" data-component-detail="agreement_basis" value="${escapeHtml(values.agreement_basis || '')}"></label>`
            : '';
        const detail = document.createElement('details');
        detail.className = 'employment-allowance-detail';
        detail.open = true;
        detail.dataset.rowId = row.rowId;
        detail.innerHTML = `<summary>${escapeHtml(optionLabel('pay_component_id', values.pay_component_id) || values.component_name || `지급항목 ${index + 1}`)} 산식 세부입력</summary>
            <div class="employment-allowance-detail__fields">
                ${calculationFields}
                ${allowanceFields}
                <div class="employment-allowance-detail__wide text-muted small">${escapeHtml(componentPolicyDisplay(values, componentMaster(values.pay_component_id)))}</div>
                <label class="employment-allowance-detail__wide">임금 산입 판단근거<input class="form-control form-control-sm" data-component-detail="wage_treatment_basis" value="${escapeHtml(values.wage_treatment_basis || '')}"></label>
                <label class="employment-allowance-detail__wide">비고<input class="form-control form-control-sm" data-component-detail="note" value="${escapeHtml(values.note || '')}"></label>
            </div>`;
        componentAllowanceDetails.append(detail);
    });
}
function syncComponentHeaderActionButton() {
    const addButton = componentAddButton;
    const headerCell = componentGridHost?.querySelector('.html-grid-header-cell[data-column-key="row_action"]');
    if (!addButton || !headerCell) return;
    const headerContent = headerCell.querySelector('.html-grid-header-cell-content');
    if (!headerContent) return;
    const label = headerCell.querySelector('.html-grid-header-label');
    if (label) label.textContent = '';
    let action = headerCell.querySelector('.employment-component-header-action');
    if (!action) {
        action = document.createElement('div');
        action.className = 'employment-component-header-action';
        headerContent.append(action);
    }
    if (addButton.parentElement !== action) action.append(addButton);
}

function ensureComponentGrid() {
    if (componentGrid || !componentGridHost) return componentGrid;
    componentGrid = createHtmlGrid({
        host: componentGridHost,
        gridId: 'employment-contract-components',
        rows: [],
        commitEditorsOnChange: true,
        commitEditorsBeforeRead: true,
        columns: [
            { key: 'pay_component_id', label: '급여항목', width: 360, editor: 'select', formatter: 'employment-pay_component_id', required: true, meta: { editorOptions: { options: COMPONENT_OPTIONS.pay_component_id } } },
            { key: 'formula_display', label: '산식', width: 330, formatter: 'employment-formula', editable: false },
            { key: 'amount', label: '계약금액', type: 'number', width: 220, editor: 'number', formatter: 'number', required: true, meta: { editorOptions: { allowNegative: false, maximumFractionDigits: 2 } } },
            { key: 'row_action', label: '관리', width: 80, editor: 'employment-component-delete', editable: false },
        ],
        editors: { 'employment-component-delete': createComponentDeleteEditor },
        formatters: {
            'employment-pay_component_id': value => optionLabel('pay_component_id', value),
            'employment-formula': (_value, { row }) => componentCalculation(
                row.values,
                componentMaster(row.values.pay_component_id),
            ).display,
        },
        hooks: {
            validator: { validateRow: ({ row }) => allowanceValidation(row) },
            serializer: {
                serializeRow: ({ row }) => {
                    if (row.rowState === 'deleted') return null;
                    const values = normalizeComponentPolicy(row.values);
                    return values;
                },
            },
        },
        capabilities: {
            addRow: true, deleteRow: true, reorder: false, selection: false, footer: false,
            keyboard: true, columnResize: false, columnHide: false, columnMove: false, clipboard: false,
        },
    });
    componentGrid.on('cell:changed', ({ row, columnKey }) => {
        if (columnKey === 'pay_component_id') {
            const rowIndex = componentGrid.getState().rows.findIndex(entry => entry.rowId === row.rowId);
            if (hasDuplicatePayComponent(row.values.pay_component_id, row.rowId)) {
                if (rowIndex >= 0) componentGrid.updateCell(rowIndex, 'pay_component_id', '');
                window.alert('이미 추가된 지급항목입니다.');
                updateCompensationSummary();
                return;
            }
            if (rowIndex >= 0) componentGrid.updateRow(rowIndex, normalizeComponentPolicy(row.values));
        }
        if (['pay_component_id', 'amount'].includes(columnKey)) recalculateFormulaComponents();
        renderAllowanceDetails();
        updateCompensationSummary();
        window.requestAnimationFrame(syncComponentHeaderActionButton);
    });
    const syncComponentGridActions = () => {
        recalculateFormulaComponents();
        renderAllowanceDetails();
        updateCompensationSummary();
    };
    componentGrid.on('row:added', syncComponentGridActions);
    componentGrid.on('row:deleted', syncComponentGridActions);
    componentGrid.render({ noDataMessage: '지급조건을 추가해 주세요.' });
    updateCompensationSummary();
    syncComponentHeaderActionButton();
    return componentGrid;
}

componentAllowanceDetails?.addEventListener('change', event => {
    const control = event.target.closest('[data-component-detail]');
    const detail = control?.closest('[data-row-id]');
    if (!control || !detail || !componentGrid) return;
    const rowIndex = componentGrid.getState().rows.findIndex(row => row.rowId === detail.dataset.rowId);
    if (rowIndex < 0) return;
    const value = control.hasAttribute('data-number-value')
        ? normalizeHtmlGridNumberValue(control.value, false)
        : control.value;
    componentGrid.updateCell(rowIndex, control.dataset.componentDetail, value);
    if (['quantity', 'rate', 'premium_rate']
        .includes(control.dataset.componentDetail)) {
        recalculateFormulaComponents();
    }
    if (control.hasAttribute('data-number-value')) control.value = value;
});

function replaceComponentRows(rows = []) {
    const grid = ensureComponentGrid();
    if (!grid) return;
    const state = grid.getState();
    state.rows = [...rows]
        .map((row, index) => ({ row, index }))
        .sort((left, right) => {
            const sortNoDifference = Number(left.row.sort_no || 0) - Number(right.row.sort_no || 0);
            return sortNoDifference !== 0 ? sortNoDifference : left.index - right.index;
        })
        .map(({ row }) => componentGridRow(row, 'clean'));
    state.cells = {};
    state.selection = { activeCell: null, range: null, selectedRowIds: [] };
    grid.setState(state);
    recalculateFormulaComponents();
    renderAllowanceDetails();
    updateCompensationSummary();
}

function componentRow(row = {}) {
    ensureComponentGrid()?.addRow(componentGridRow(row, 'created'));
}
function setContractModalMode(mode) {
    const title = document.getElementById('employmentContractModalTitle');
    if (title) title.textContent = mode === 'edit' ? '근로계약 수정' : '근로계약 신규';
}

function resetForm() {
    current = { contract_status: 'DRAFT' };
    lastScheduleType = '';
    weeklyScheduleTouched = false;
    activeDateInput = null;
    activeTimeInput = null;
    datePicker?.close?.();
    timePicker?.close?.();
    form.reset();
    Array.from(form.elements).forEach(field => {
        if (!field || ['button', 'submit', 'reset'].includes(field.type)) return;
        if (field.matches('select')) {
            field.value = '';
            window.jQuery?.(field).val('').trigger('change.select2');
            return;
        }
        if (field.type === 'checkbox' || field.type === 'radio') {
            field.checked = false;
            return;
        }
        field.value = '';
        field.setCustomValidity?.('');
    });
    form.elements.namedItem('id').value = '';
    form.elements.namedItem('current_approval_request_id').value = '';
    const contractStatus = form.elements.namedItem('contract_status');
    contractStatus.value = current.contract_status;
    window.jQuery?.(contractStatus).val(current.contract_status).trigger('change.select2');
    replaceComponentRows([]);
    setWeeklySchedules([]);
    setSchedulePolicy({});
    componentRow();
    setWeeklyScheduleExpanded(false);
    setContractModalMode('create');
    applyState();
    updateScheduleTypePolicy();
    updateFixedTermPolicy();
}
function setForm(contract) {
    const selectedLabels = {
        employee_id: contract.employee_name,
        project_id: contract.project_name,
    };
    Object.entries(contract).forEach(([key, value]) => {
        const field = form.elements.namedItem(key);
        if (field) {
            if (field.matches('select') && value && selectedLabels[key]
                && !Array.from(field.options).some(option => option.value === String(value))) {
                field.appendChild(new Option(selectedLabels[key], String(value), true, true));
            }
            if (field.matches('select[data-preserve-raw-code-value="true"]')
                && value && !Array.from(field.options).some(option => option.value === String(value))) {
                const option = document.createElement('option');
                option.value = String(value);
                option.textContent = key === 'fixed_term_reason_code'
                    ? `${contract.fixed_term_reason_name || value} (비활성)`
                    : String(value);
                option.disabled = true;
                field.appendChild(option);
            }
            field.value = value ?? '';
            if (field.matches('select')) window.jQuery?.(field).trigger('change.select2');
        }
    });
}

async function openContract(id) {
    await ensureModalOptions();
    resetForm();
    const basic = await request(`${API.detail}?id=${encodeURIComponent(id)}`);
    current = basic.data.contract;
    setContractModalMode('edit');
    hydratingContract = true;
    try {
        setForm(current);
        setWeeklySchedules(basic.data.weekly_schedules || []);
        setSchedulePolicy(basic.data.work_schedule_policy || {});
    } finally {
        hydratingContract = false;
    }
    replaceComponentRows([]);
    replaceComponentRows(basic.data.components || []);
    applyState();
    updateFixedTermPolicy();
    updateScheduleTypePolicy();
    modal?.show();
}

function applyState() {
    const status = current?.contract_status || 'DRAFT';
    const editable = isContractFormEditable();
    Array.from(form.elements).forEach(field => {
        if (field.type !== 'hidden' && !['button', 'submit'].includes(field.type)) field.disabled = !editable;
    });
    if (form.elements.contract_status) form.elements.contract_status.disabled = true;
    if (form.elements.termination_reason) {
        form.elements.termination_reason.disabled = !['APPROVED', 'EFFECTIVE'].includes(status);
    }
    document.getElementById('employmentContractStatus').innerHTML = badge(status);
    document.getElementById('employmentContractSave').classList.toggle('d-none', !editable);
    document.getElementById('employmentContractSubmit').classList.toggle('d-none', !editable || !current?.id);
    const submitButton = document.getElementById('employmentContractSubmit');
    if (submitButton) {
        submitButton.disabled = (
            form.elements.contract_period_type?.value === fixedTermContractPeriodType
            && form.elements.fixed_term_reason_code?.value === 'REVIEW_REQUIRED'
        );
    }
    document.getElementById('employmentContractDelete').classList.toggle('d-none', !editable || !current?.id);
    document.getElementById('employmentContractWithdraw').classList.toggle('d-none', status !== 'APPROVAL_PENDING');
    document.getElementById('employmentContractRevise').classList.toggle('d-none', !['APPROVED', 'EFFECTIVE'].includes(status));
    document.getElementById('employmentContractTerminate').classList.toggle('d-none', !['APPROVED', 'EFFECTIVE'].includes(status));
    if (componentAddButton) componentAddButton.disabled = !editable;
    const scheduleDefaultsButton = document.getElementById('employmentWeeklyScheduleDefaults');
    if (scheduleDefaultsButton) scheduleDefaultsButton.disabled = !editable;
    if (componentGrid) {
        const gridState = componentGrid.getState();
        gridState.capabilities = {
            ...gridState.capabilities,
            addRow: editable,
            deleteRow: editable,
            keyboard: editable,
        };
        componentGrid.setState(gridState);
        syncComponentHeaderActionButton();
    }
    componentGridHost?.classList.toggle('is-readonly', !editable);
    componentGridHost?.querySelectorAll('input, select, button').forEach(control => {
        control.disabled = !editable;
    });
    componentAllowanceDetails?.querySelectorAll('input, select').forEach(control => {
        control.disabled = !editable;
    });
    if (weeklyScheduleGrid) {
        const scheduleState = weeklyScheduleGrid.getState();
        scheduleState.capabilities = { ...scheduleState.capabilities, keyboard: editable };
        weeklyScheduleGrid.setState(scheduleState);
    }
    weeklyScheduleGridHost?.classList.toggle('is-readonly', !editable);
    if (!editable) {
        weeklyScheduleGridHost?.querySelectorAll('input, select, button').forEach(control => {
            control.disabled = true;
        });
    }
    updateScheduleTypePolicy();
}

function serialize() {
    const payload = Object.fromEntries(new FormData(form).entries());
    const isFixedTerm = form.elements.contract_period_type?.value === fixedTermContractPeriodType;
    if (!isFixedTerm) {
        payload.contract_end_date = null;
        payload.fixed_term_reason_code = null;
        payload.fixed_term_reason_detail = null;
    }
    payload.components = componentGrid?.serialize().rows || [];
    const type = form.elements.work_schedule_type.value;
    payload.weekly_schedules = [scheduleTypes.normal, scheduleTypes.night, scheduleTypes.flexible, scheduleTypes.other].includes(type) ? serializeWeeklySchedules() : [];
    payload.work_schedule_policy = [scheduleTypes.normal, scheduleTypes.night].includes(type) ? {} : serializeSchedulePolicy();
    return payload;
}

function validateScheduleBeforeSave() {
    const type = form.elements.work_schedule_type.value;
    if (![scheduleTypes.normal, scheduleTypes.night, scheduleTypes.flexible, scheduleTypes.other].includes(type)) return true;
    const validation = ensureWeeklyScheduleGrid()?.validate();
    if (!validation?.hasError) return true;
    setWeeklyScheduleExpanded(true);
    document.getElementById('employmentWeeklyScheduleArea')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    weeklyScheduleGrid.focusFirstError(validation);
    window.alert(validation.messages[0] || '요일별 근무조건을 확인해 주세요.');
    return false;
}

async function save(event) {
    event.preventDefault();
    if (!updateCodeAvailability()) {
        document.getElementById('employmentContractCodeWarning')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    if (!validateScheduleBeforeSave()) return;
    const componentValidation = ensureComponentGrid()?.validate();
    if (componentValidation?.hasError) {
        componentGrid.focusFirstError(componentValidation);
        componentGridHost?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.alert(componentValidation.messages[0] || '지급조건을 확인해 주세요.');
        return;
    }
    if (!form.reportValidity()) return;
    const result = await request(API.save, { method: 'POST', body: JSON.stringify({ ...serialize(), request_key: requestKey() }) });
    table?.ajax.reload(null, false);
    await openContract(result.data.id);
}

async function action(url, body, confirmation, { softDelete = false } = {}) {
    if (confirmation && !window.confirm(confirmation)) return;
    const execute = async () => {
        const result = await request(url, { method: 'POST', body: JSON.stringify({ ...body, request_key: body.request_key || requestKey() }) });
        window.alert(result.message);
        modal?.hide();
        await new Promise(resolve => table?.ajax.reload(() => resolve(), false));
    };
    return softDelete
        ? runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '근로계약을 휴지통으로 이동 중' }, execute)
        : execute();
}

modalElement?.addEventListener('shown.bs.modal', () => {
    applyState();
    updateScheduleTypePolicy();
    updateFixedTermPolicy();
});
componentAddButton?.addEventListener('click', () => componentRow());
form?.elements.salary_type?.addEventListener('change', updateCompensationSummary);
window.jQuery?.(form).on('select2:select select2:clear', '[name="salary_type"]', updateCompensationSummary);
const handleWorkScheduleTypeChange = event => {
    if (!event.target?.matches?.('[name="work_schedule_type"]')) return;
    if (hydratingContract) return;
    updateScheduleTypePolicy({ applyDefaults: true });
};
form?.addEventListener('change', handleWorkScheduleTypeChange);
window.jQuery?.(form).on('select2:select select2:clear', '[name="work_schedule_type"]', event => {
    if (hydratingContract) return;
    updateScheduleTypePolicy({ applyDefaults: true });
});
document.getElementById('employmentWeeklyScheduleDefaults')?.addEventListener('click', applyWeeklyScheduleDefaults);
document.getElementById('employmentWeeklyScheduleToggle')?.addEventListener('click', () => {
    setWeeklyScheduleExpanded(!weeklyScheduleExpanded);
});
form?.addEventListener('submit', event => save(event).catch(error => window.alert(error.message)));
document.getElementById('employmentContractSubmit')?.addEventListener('click', () => action(API.submit, { id: current.id }, '저장된 계약을 결재 요청하시겠습니까?').catch(error => window.alert(error.message)));
document.getElementById('employmentContractDelete')?.addEventListener('click', () => action(API.delete, { id: current.id }, '이 계약을 휴지통으로 이동하시겠습니까?', { softDelete: true }).catch(error => window.alert(error.message)));
document.getElementById('employmentContractWithdraw')?.addEventListener('click', () => action(API.withdraw, { request_id: current.current_approval_request_id }, '진행 중인 기안을 회수하시겠습니까?').catch(error => window.alert(error.message)));
document.getElementById('employmentContractRevise')?.addEventListener('click', async () => {
    const reason = window.prompt('개정사유를 입력해 주세요.');
    if (reason) await action(API.revise, { id: current.id, reason });
});
document.getElementById('employmentContractTerminate')?.addEventListener('click', async () => {
    const reason = form.elements.termination_reason.value;
    if (!reason) {
        window.alert('종료사유를 선택해 주세요.');
        form.elements.termination_reason.focus();
        return;
    }
    await action(API.terminate, { id: current.id, reason }, '이 계약을 종료 또는 해지 처리하시겠습니까?');
});
document.addEventListener('datatable-settings:updated', event => {
    if (event.detail?.storageKey !== CONTRACT_TABLE_SETTINGS_KEY) return;
    applyContractFormColumnMeta();
    updateFixedTermPolicy();
});

export async function initializeModalRuntime({ table: tableInstance } = {}) {
    table = tableInstance || table;
    await prepareModalRuntime();
}

export async function openCreate({ table: tableInstance } = {}) {
    await initializeModalRuntime({ table: tableInstance });
    resetForm();
    modal?.show();
}

export async function openDetail(id, { table: tableInstance } = {}) {
    await initializeModalRuntime({ table: tableInstance });
    await openContract(id);
}
