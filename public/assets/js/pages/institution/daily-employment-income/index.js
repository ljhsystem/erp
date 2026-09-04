import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { formatYearMonthValue, normalizeYearMonthInputValue, parseYearMonthValue } from '/public/assets/js/common/picker/picker.yearmonth.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { actorColumn } from '/public/assets/js/common/actor.js';
import { formatAmount, formatDateDisplay, formatDateInputValue } from '/public/assets/js/common/format.js';
import { notify } from '/public/assets/js/common/notification.js';
import { confirmDialog } from '/public/assets/js/common/confirm-dialog.js';
import { bindModalCardCollapses } from '/public/assets/js/common/modal-card-collapse.js';
import { createDataTableFormSettings } from '/public/assets/js/common/datatable/dataTableFormSettings.js';
import { getCachedDataTableMetaColumns, readDataTableSettingsState, resolveDataTableColumnDisplayName, resolveDataTableColumnRequirementPolicy } from '/public/assets/js/common/datatable/dataTableSettings.js';
import { PickerSelect2 } from '/public/assets/js/common/picker/picker.select2.js';
import { openCodeQuickModal } from '/public/assets/js/pages/main/settings/system/code-select.js';
import { openWorkTeamQuickCreate } from '/public/assets/js/pages/main/settings/base/work-team/quick-modal.js';
import { DailyIncomeWorkerCardRegistry } from './worker-cards.js?v=20260831-reactive-preview-2';
import {
    calculationTotals, copyWorkerInstance, resetWorkerCalculationState,
    selectionAfterDelete, workerCalculationSourceKey,
} from './worker-instance-state.js';
import { createDailyEmploymentIncomeExcelProvider } from './excel-provider.js';
import { INCOME_INSTITUTION_CARDS, incomeCalculationPeriodText, incomeCalculationRoundingText, incomeInstitutionCardsDto, isIncomeCalculationEditableStatus, renderIncomeCalculationCards } from '/public/assets/js/common/income-calculation-cards.js?v=20260831-income-cards-14';
import '/public/assets/js/common/core/AppAjax.js';
import '/public/assets/js/components/trash-manager.js';
import { incomeWithholdingDate, INCOME_WITHHOLDING_RULES, isIncomeWithholdingDate } from '/public/assets/js/common/income-withholding-date.js';

const API = {
    LIST: '/api/institution/income-data/daily-employment/list',
    DETAIL: '/api/institution/income-data/daily-employment/detail',
    OPTIONS: '/api/institution/income-data/daily-employment/options',
    CALCULATE: '/api/institution/income-data/daily-employment/calculate',
    PREFLIGHT: '/api/institution/income-data/daily-employment/preflight',
    SAVE: '/api/institution/income-data/daily-employment/save',
    SUBMIT: '/api/institution/income-data/daily-employment/submit',
    WITHDRAW: '/api/institution/income-data/daily-employment/withdraw',
    DELETE: '/api/institution/income-data/daily-employment/delete',
    EXCEL_TEMPLATE: '/api/institution/income-data/daily-employment/template',
    EXCEL_DOWNLOAD: '/api/institution/income-data/daily-employment/excel',
    EXCEL_PREVIEW: '/api/institution/income-data/daily-employment/excel-upload-preview',
};
const TABLE_SETTINGS_KEY = 'institution.income-data.daily-employment.daily-income-table.v1';
const TABLE_SETTINGS_OPTIONS = Object.freeze({ pageKey: 'institution.income-data.daily-employment', metaDomain: 'daily-employment-income' });
const DEFAULT_WORKDAY_MINUTES = 8 * 60;
const root = document.querySelector('.daily-income-page');
const token = () => globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
const numberValue = value => Number(String(value ?? 0).replaceAll(',', '')) || 0;
const workdayGross = day => numberValue(day.daily_rate_amount)
    + numberValue(day.taxable_additional_amount ?? day.allowance_amount)
    + numberValue(day.non_taxable_additional_amount ?? day.non_taxable_amount);
const escapeHtml = value => { const node = document.createElement('div'); node.textContent = String(value ?? ''); return node.innerHTML; };

if (root) {
    const modalElement = document.getElementById('dailyIncomeModal');
    const form = document.getElementById('dailyIncomeForm');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement, { focus: false });
    const yearMonthValue = form.elements.income_year_month;
    const yearMonthDisplay = document.getElementById('dailyIncomeYearMonthDisplay');
    const groupsHost = document.getElementById('dailyIncomeWorkers');
    const documentSummary = document.getElementById('dailyIncomeDocumentSummary');
    const workerResult = document.getElementById('dailyIncomeWorkerResult');
    const systemInfoCard = document.getElementById('dailyIncomeSystemCard');
    const systemInfoHost = document.getElementById('dailyIncomeSystemInfo');
    const yearMonthPicker = AdminPicker.create({ type: 'year-month', container: document.getElementById('dailyIncomeYearMonthPicker') });
    const withholdingDateInput = form.elements.withholding_date;
    const withholdingDatePicker = AdminPicker.create({ type: 'today', container: document.getElementById('dailyIncomeWithholdingDatePicker') });
    let referenceOptions = null;
    let workGroups = [];
    let readOnly = false;
    let selectedWorkerKey = '';
    let committedYearMonth = '';
    let calculationTimer = null;
    let calculationRequestVersion = 0;
    let calculationAbortController = null;
    let lastPreviewPayloadKey = '';
    let documentStatus = 'DRAFT';
    let currentApprovalRequestId = '';
    let currentSystemInfoData = null;
    const systemStatusLabel = value => ({
        DRAFT: '작성 중', PENDING: '결재 진행', APPROVED: '승인 완료',
        REJECTED: '반려', WITHDRAWN: '회수',
    }[String(value || '').trim().toUpperCase()] || value || '-');
    const systemInfoFields = [
        { key: 'id', label: '문서 ID' },
        { key: 'company_id', label: '회사 ID' },
        { key: 'sort_no', label: '정렬순서' },
        { key: 'status_code', label: '문서상태', type: 'status' },
        { key: 'worker_count', label: '작업자 수', type: 'count', suffix: '명' },
        { key: 'work_team_count', label: '작업팀 수', type: 'count', suffix: '개' },
        { key: 'group_count', label: '근무그룹 수', type: 'count', suffix: '개' },
        { key: 'total_work_days', label: '총 근무일수', type: 'count', suffix: '일' },
        { key: 'total_taxable_income_amount', label: '총 과세소득', type: 'amount' },
        { key: 'total_non_taxable_income_amount', label: '총 비과세소득', type: 'amount' },
        { key: 'total_gross_amount', label: '총 지급액(세전)', type: 'amount' },
        { key: 'total_income_tax_amount', label: '총 소득세', type: 'amount' },
        { key: 'total_local_income_tax_amount', label: '총 지방소득세', type: 'amount' },
        { key: 'total_employee_contribution_amount', label: '근로자부담 합계', type: 'amount' },
        { key: 'total_deduction_amount', label: '총 공제액', type: 'amount' },
        { key: 'total_net_payment_amount', label: '총 실지급액', type: 'amount' },
        { key: 'total_employer_burden_amount', label: '사용자부담 합계', type: 'amount' },
        { key: 'calculation_status_code', label: '계산상태', type: 'status' },
        { key: 'calculation_policy_version', label: '계산정책 버전' },
        { key: 'calculation_revision_id', label: '계산 Revision ID' },
        { key: 'approval_request_id', label: '결재요청 ID' },
        { key: 'approved_by_name', fallbackKey: 'approved_by', label: '승인자' },
        { key: 'approved_at', label: '승인일시', type: 'date' },
        { key: 'created_by_name', fallbackKey: 'created_by', label: '등록자' },
        { key: 'created_at', label: '등록일시', type: 'date' },
        { key: 'updated_by_name', fallbackKey: 'updated_by', label: '수정자' },
        { key: 'updated_at', label: '수정일시', type: 'date' },
        { key: 'deleted_by_name', fallbackKey: 'deleted_by', label: '삭제자' },
        { key: 'deleted_at', label: '삭제일시', type: 'date' },
    ];
    const systemInfoValue = (data, field) => {
        const value = data?.[field.key] ?? (field.fallbackKey ? data?.[field.fallbackKey] : null);
        if (value === null || value === undefined || value === '') return '-';
        if (field.type === 'amount') return `${formatAmount(value)}원`;
        if (field.type === 'count') return `${formatAmount(value)}${field.suffix || ''}`;
        if (field.type === 'date') return formatDateDisplay(value) || '-';
        if (field.type === 'status') return systemStatusLabel(value);
        return String(value);
    };
    const systemInfoColumnPolicy = field => {
        const metaKey = field.fallbackKey || field.key;
        const column = getCachedDataTableMetaColumns({ metaDomain: 'daily-employment-income' })
            .find(candidate => String(candidate.key || '') === metaKey);
        if (!column) return { label: field.label, policy: 'none' };
        const state = readDataTableSettingsState(TABLE_SETTINGS_KEY, TABLE_SETTINGS_OPTIONS);
        const policy = resolveDataTableColumnRequirementPolicy(column, state);
        return {
            label: resolveDataTableColumnDisplayName(column, state, column.label || field.label),
            policy: ['required', 'optional'].includes(policy) ? policy : 'none',
        };
    };
    const renderSystemInfo = data => {
        currentSystemInfoData = data;
        if (!systemInfoCard || !systemInfoHost) return;
        systemInfoHost.replaceChildren(...systemInfoFields.map(field => {
            const columnPolicy = systemInfoColumnPolicy(field);
            const item = document.createElement('div');
            item.className = `daily-income-system-info-field${['amount', 'count'].includes(field.type) ? ' is-numeric' : ''}`;
            const label = document.createElement('span');
            const value = document.createElement('span');
            label.className = 'daily-income-system-info-label';
            value.className = 'daily-income-system-info-value';
            label.append(document.createTextNode(columnPolicy.label));
            if (columnPolicy.policy !== 'none') {
                const marker = document.createElement('span');
                marker.className = `column-policy-star is-${columnPolicy.policy}`;
                marker.setAttribute('aria-label', columnPolicy.policy === 'required' ? '필수' : '선택');
                marker.textContent = '*';
                label.append(marker);
            }
            value.textContent = systemInfoValue(data, field);
            value.title = value.textContent;
            item.append(label, value);
            return item;
        }));
    };
    const groupGridRegistry = new DailyIncomeWorkerCardRegistry({
        createItem: () => createWorker(),
        onChanged: ({ calculationChanged = true, immediate = false } = {}) => { renderSummary(); if (calculationChanged) scheduleAutoCalculation({ immediate }); },
        onValidationError: message => notify('warning', message),
    });
    bindModalCardCollapses(modalElement, { resetOnShow: true });
    renderSystemInfo(null);

    const request = (url, options = {}) => window.AppCore.fetchJson(url, {
        ...options,
        headers: {
            ...(options.body === undefined || options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            ...(options.headers || {}),
        },
    });
    const syncWorkflowActions = () => {
        const editable = ['DRAFT', 'REJECTED', 'WITHDRAWN'].includes(documentStatus);
        const hasDocument = String(form.elements.id.value || '').trim() !== '';
        const workMinuteBlocked = invalidWorkMinuteWorkdays().length > 0;
        const withholdingDateBlocked = !isIncomeWithholdingDate(withholdingDateInput.value);
        const missingInputBlocked = documentMissingInputs().length > 0;
        const submit = document.getElementById('dailyIncomeSubmit');
        const withdraw = document.getElementById('dailyIncomeWithdraw');
        const remove = document.getElementById('dailyIncomeDelete');
        if (submit) {
            submit.disabled = !editable || !hasDocument || workMinuteBlocked || withholdingDateBlocked || missingInputBlocked;
            submit.title = withholdingDateBlocked ? '원천징수일을 입력하면 해당 날짜의 법정기준으로 자동 계산됩니다.' : (missingInputBlocked ? documentMissingInputs().join(' ') : (workMinuteBlocked ? '근로시간이 누락되었거나 유효하지 않은 Workday를 먼저 수정해 주세요.' : ''));
        }
        const withdrawable = documentStatus === 'PENDING' && currentApprovalRequestId !== '';
        if (withdraw) {
            withdraw.disabled = !withdrawable;
            withdraw.classList.toggle('d-none', !withdrawable);
        }
        if (remove) remove.disabled = !editable || !hasDocument;
        const save = form.querySelector('button[type="submit"]');
        if (save) save.disabled = !editable || withholdingDateBlocked || missingInputBlocked || workMinuteBlocked;
    };
    const validDate = value => {
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return false;
        const date = new Date(+match[1], +match[2] - 1, +match[3]);
        return date.getFullYear() === +match[1] && date.getMonth() === +match[2] - 1 && date.getDate() === +match[3];
    };
    const setYearMonth = value => {
        const date = parseYearMonthValue(value);
        yearMonthValue.value = date ? formatYearMonthValue(date) : '';
        yearMonthDisplay.value = yearMonthValue.value;
        if (date) yearMonthPicker.setYearMonth(date);
        else yearMonthPicker.state.date = null;
    };
    const applyDefaultWithholdingDate = () => {
        withholdingDateInput.value = incomeWithholdingDate(yearMonthValue.value, INCOME_WITHHOLDING_RULES.MONTH_END);
        withholdingDateInput.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const normalizeYearMonth = () => {
        const normalized = normalizeYearMonthInputValue(yearMonthDisplay.value);
        yearMonthDisplay.value = normalized;
        const date = parseYearMonthValue(normalized);
        yearMonthValue.value = date ? formatYearMonthValue(date) : '';
        if (date) yearMonthPicker.setYearMonth(date);
        return Boolean(date);
    };
    const monthDates = month => {
        if (!/^\d{4}-\d{2}$/.test(month)) return [];
        const [year, monthNumber] = month.split('-').map(Number);
        return Array.from({ length: new Date(year, monthNumber, 0).getDate() }, (_, index) => `${month}-${String(index + 1).padStart(2, '0')}`);
    };
    const createWorker = () => ({
        client_key: token(), sort_no: 0, worker_client_id: '', worker_name: '', worker_client_type: '', worker_client_type_name: '', daily_rate_amount: null,
        work_type_code: '', work_type_name: '', work_description: '',
        workdays: new Map(), calculation: null, calculation_source_key: '', calculation_request_version: 0,
        calculation_state: 'idle', calculation_error: '',
        institution_line_overrides: [],
    });
    const createGroup = source => ({
        client_key: token(), business_unit: source?.business_unit || '', project_id: source?.project_id || '',
        work_team_id: source?.work_team_id || '', work_description: source?.work_description || '',
        employment_insurance_application_status_code: source?.employment_insurance_application_status_code || '',
        employment_insurance_decision_reason: source?.employment_insurance_decision_reason || '',
        employment_insurance_decision_source_code_id: source?.employment_insurance_decision_source_code_id || '',
        employment_insurance_set_by: source?.employment_insurance_set_by || source?.updated_by || '',
        employment_insurance_set_at: source?.employment_insurance_set_at || source?.updated_at || '',
        industrial_accident_application_status_code: source?.industrial_accident_application_status_code || '',
        industrial_accident_decision_reason: source?.industrial_accident_decision_reason || '',
        industrial_accident_decision_source_code_id: source?.industrial_accident_decision_source_code_id || '',
        industrial_accident_set_by: source?.industrial_accident_set_by || source?.updated_by || '',
        industrial_accident_set_at: source?.industrial_accident_set_at || source?.updated_at || '',
        project_name: source?.project_name || '', work_team_name: source?.team_name || source?.work_team_name || '',
        policy_request_version: 0, policy_status: source?.business_unit ? 'ready' : 'idle', policy_error: '',
        collapsed: false, items: [createWorker()],
    });
    const loadOptions = async (force = false) => {
        if (referenceOptions && !force) return referenceOptions;
        const response = await request(API.OPTIONS);
        referenceOptions = response.data || { business_units: [], projects: [], work_teams: [], workers: [] };
        return referenceOptions;
    };
    const referenceKey = value => String(value || '').trim().toUpperCase();
    const businessUnitOption = value => (referenceOptions?.business_units || [])
        .find(row => referenceKey(row.id) === referenceKey(value)) || null;
    const invalidateGroupCalculation = group => {
        group.items.forEach(item => { resetWorkerCalculationState(item, { resetDraftAdjustments: true }); });
        calculationRequestVersion += 1;
    };
    const invalidateDocumentCalculation = ({ resetDraftAdjustments = true } = {}) => {
        workGroups.forEach(group => group.items.forEach(item => {
            resetWorkerCalculationState(item, { resetDraftAdjustments });
        }));
        calculationRequestVersion += 1;
    };
    const insuranceStatusLabel = status => status === 'EXCLUDED' ? '우리 회사 미부담' : (status === 'APPLICABLE' ? '우리 회사 부담' : '미선택');
    const applyGroupBurdenPolicy = group => {
        const businessUnit = referenceKey(group.business_unit);
        const automatic = businessUnit === 'HQ' || businessUnit === 'ECOMMERCE';
        ['employment_insurance', 'industrial_accident'].forEach(prefix => {
            if (automatic) group[`${prefix}_application_status_code`] = 'APPLICABLE';
            group[`${prefix}_decision_reason`] = '';
            group[`${prefix}_decision_source_code_id`] = automatic ? 'BUSINESS_DIVISION_POLICY' : (group[`${prefix}_application_status_code`] ? 'DAILY_GROUP_MANUAL_SETTING' : '');
            group[`${prefix}_decision_source_code`] = automatic ? 'BUSINESS_DIVISION_POLICY' : (group[`${prefix}_application_status_code`] ? 'DAILY_GROUP_MANUAL_SETTING' : null);
        });
        return automatic;
    };
    const groupItems = () => workGroups.flatMap(group => group.items);
    const selectedWorker = () => workGroups
        .filter(group => !group.collapsed)
        .flatMap(group => group.items)
        .find(item => item.client_key === selectedWorkerKey) || null;
    const syncWorkerCalculationIndicator = item => {
        const card = groupsHost.querySelector(`[data-daily-worker-key="${CSS.escape(item.client_key)}"]`);
        const header = card?.querySelector('.daily-income-worker-card__header');
        if (!header) return;
        header.querySelector('.daily-income-worker-calculation-state')?.remove();
        if (!['loading', 'error'].includes(item.calculation_state)) return;
        const indicator = document.createElement('span');
        indicator.className = `daily-income-worker-calculation-state is-${item.calculation_state}`;
        if (item.calculation_state === 'loading') indicator.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>계산 중</span>';
        else { indicator.textContent = item.calculation_error || '계산 오류'; indicator.title = indicator.textContent; }
        header.append(indicator);
    };
    const renderWorkerResult = () => {
        const item = selectedWorker();
        if (!item) { workerResult.innerHTML = '<h6>선택 작업자 계산 결과</h6><div class="daily-income-result-empty">작업자 카드를 선택해 주세요.</div>'; return; }
        if (!isIncomeWithholdingDate(withholdingDateInput.value)) { workerResult.innerHTML = '<h6>선택 작업자 계산 결과</h6><div class="daily-income-result-empty">원천징수일을 입력하면 해당 날짜의 법정기준으로 자동 계산됩니다.</div>'; return; }
        const workerName = (referenceOptions?.workers || []).find(row => String(row.id) === String(item.worker_client_id))?.name || '작업자 미선택';
        const groupIndex = workGroups.findIndex(group => group.items.includes(item));
        const workerIndex = groupIndex < 0 ? -1 : workGroups[groupIndex].items.indexOf(item);
        const workTypeName = (referenceOptions?.work_types || []).find(row => String(row.id) === String(item.work_type_code))?.name || item.work_type_name || item.work_type_code || '공종 미선택';
        const resultTitleHtml = `그룹 ${groupIndex + 1} <span class="daily-income-result-title-separator">·</span> ${workerIndex + 1}.${escapeHtml(workerName)} <span class="daily-income-result-title-separator">·</span> <span class="badge text-bg-secondary">${escapeHtml(workTypeName)}</span>`;
        const summary = item.calculation?.summary || {};
        const days = [...item.workdays.values()];
        const incomeTax = days.reduce((sum, day) => sum + numberValue(day.income_tax_amount), 0);
        const localTax = days.reduce((sum, day) => sum + numberValue(day.local_income_tax_amount), 0);
        const status = item.calculation_state === 'loading'
            ? '<div class="daily-income-result-status is-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>계산 중</span></div>'
            : (item.calculation_state === 'error' ? `<div class="daily-income-result-status is-error">${escapeHtml(item.calculation_error || '계산 중 오류가 발생했습니다.')}</div>` : '');
        const row = (label, value, strong = false, className = '') => `<div class="${className}"><span>${label}</span><${strong ? 'strong' : 'b'}>${value}</${strong ? 'strong' : 'b'}></div>`;
        const itemLines = item.calculation?.lines || [];
        const institutionLineOrder = line => {
            const index = INCOME_INSTITUTION_CARDS.findIndex(definition => definition.aliases.includes(line.line_code));
            return index < 0 ? INCOME_INSTITUTION_CARDS.length : index;
        };
        const sortInstitutionLines = lines => lines.map((line, index) => ({ line, index }))
            .sort((left, right) => institutionLineOrder(left.line) - institutionLineOrder(right.line) || left.index - right.index)
            .map(entry => entry.line);
        const deductionLines = sortInstitutionLines(itemLines.filter(line => line.line_type_code === 'DEDUCTION'
            && !['DAILY_WORKER_INCOME_TAX','LOCAL_INCOME_TAX'].includes(line.line_code)));
        const employerLines = sortInstitutionLines(itemLines.filter(line => line.line_type_code === 'EMPLOYER_BURDEN'));
        const employmentEmployerLines = employerLines.filter(line => ['EMPLOYMENT_INSURANCE','EMPLOYMENT_INSURANCE_VOCATIONAL'].includes(line.line_code));
        const employmentEmployerAmount = !employmentEmployerLines.length || employmentEmployerLines.some(line => line.final_amount === null || line.final_amount === undefined)
            ? null
            : employmentEmployerLines.reduce((total, line) => total + numberValue(line.final_amount), 0);
        const employerSummaryDefinitions = INCOME_INSTITUTION_CARDS.filter(definition => !definition.employeeOnly && definition.key !== 'EMPLOYMENT_INSURANCE_VOCATIONAL');
        const employerSummaryLines = employerSummaryDefinitions.map(definition => {
            if (definition.key === 'EMPLOYMENT_INSURANCE') return { line_code: 'EMPLOYMENT_INSURANCE', line_name_snapshot: '고용보험 사용자부담', final_amount: employmentEmployerAmount };
            const source = employerLines.find(line => definition.aliases.includes(line.line_code));
            return { ...(source || {}), line_code: source?.line_code || definition.aliases[0], line_name_snapshot: `${definition.name} 사용자부담`, final_amount: source?.final_amount ?? null };
        });
        const itemEmployer = employerLines.reduce((sum, line) => sum + numberValue(line.final_amount), 0);
        const workMinutes = days.reduce((sum, day) => sum + numberValue(day.actual_work_minutes), 0);
        const enteredRates = days.filter(day => day.daily_rate_amount !== null && day.daily_rate_amount !== undefined && day.daily_rate_amount !== '').map(day => numberValue(day.daily_rate_amount));
        const averageRate = enteredRates.length ? Math.round(enteredRates.reduce((sum, rate) => sum + rate, 0) / enteredRates.length) : 0;
        const basePay = days.reduce((sum, day) => sum + numberValue(day.daily_rate_amount) * numberValue(day.work_quantity || 1), 0);
        const taxableAdditional = days.reduce((sum, day) => sum + numberValue(day.taxable_additional_amount), 0);
        const nonTaxableAdditional = days.reduce((sum, day) => sum + numberValue(day.non_taxable_additional_amount), 0);
        const adjustmentAmount = taxableAdditional + nonTaxableAdditional;
        const remainingMinutes = workMinutes % 60;
        const workTimeLabel = `${Math.floor(workMinutes / 60)}시간${remainingMinutes ? `${String(remainingMinutes).padStart(2, '0')}분` : ''}`;
        const workSummaryLabel = `${days.length}일/${workTimeLabel}/${formatAmount(averageRate)}원`;
        const lineRows = lines => lines.length
            ? lines.map(line => row(line.line_name_snapshot || line.line_code || '보험료', line.final_amount === null ? '미확정' : `${formatAmount(line.final_amount)}원`)).join('')
            : row('보험료', '미확정');
        workerResult.innerHTML = `<h6>${resultTitleHtml}</h6>${status}<section class="daily-income-result-section"><div class="daily-income-result-section-title">근무·지급</div><div class="daily-income-result-values">${row('근무일수/근로시간/단가', workSummaryLabel)}${row('기본 지급액', `${formatAmount(basePay)}원`)}${row('증감액', `${formatAmount(adjustmentAmount)}원`)}${row('지급액(세전)', `${formatAmount(summary.total_gross_amount || 0)}원`, true)}</div></section><section class="daily-income-result-section"><div class="daily-income-result-section-title">세금·근로자 부담</div><div class="daily-income-result-values">${row('근로소득세', `${formatAmount(incomeTax)}원`)}${row('지방소득세', `${formatAmount(localTax)}원`)}${lineRows(deductionLines)}${row('총 원천징수', `${formatAmount(summary.total_deduction_amount || 0)}원`, true)}</div></section><section class="daily-income-result-section"><div class="daily-income-result-section-title">사용자 부담</div><div class="daily-income-result-values">${lineRows(employerSummaryLines)}${row('사용자부담 합계', employerLines.length ? `${formatAmount(itemEmployer)}원` : '미확정', true)}</div></section><section class="daily-income-result-section is-final"><div class="daily-income-result-section-title">최종 지급</div><div class="daily-income-result-values">${row('지급액(세전)', `${formatAmount(summary.total_gross_amount || 0)}원`)}${row('공제 합계', `${formatAmount(summary.total_deduction_amount || 0)}원`)}${row('실지급액(세후)', `${formatAmount(summary.total_net_payment_amount || 0)}원`, true, 'is-net-payment')}</div></section>`;
    };
    const renderInstitutionDetails = (item, host) => {
        host.replaceChildren();
        const dayLines = [...item.workdays.values()].flatMap(day => (day.lines || []).map(line => ({ ...line, work_date: day.work_date })));
        const itemLines = item.calculation?.lines || [];
        const editableStatus = isIncomeCalculationEditableStatus(documentStatus) && !readOnly;
        const dto = incomeInstitutionCardsDto([...dayLines, ...itemLines], {
            typeField: 'line_type_code', codeField: 'line_code',
            editable: line => editableStatus && (itemLines.includes(line) || dayLines.includes(line)),
            mapLine: (result, line, employer, definition, candidates) => {
                const workdayTaxLines = candidates.filter(candidate => candidate.work_date
                    && ['DAILY_WORKER_INCOME_TAX','LOCAL_INCOME_TAX'].includes(candidate.line_code));
                if (!workdayTaxLines.length) return { ...result,
                    roundingLabel: incomeCalculationRoundingText(line),
                    standardLabel: incomeCalculationPeriodText(line),
                };
                const sum = field => workdayTaxLines.reduce((total, candidate) => total + numberValue(candidate[field]), 0);
                return { ...result,
                    basisAmount: sum('calculation_basis_amount'), beforeRounding: sum('calculation_before_rounding'),
                    calculatedAmount: sum('calculated_amount'), finalAmount: sum('final_amount'),
                    difference: sum('final_amount') - sum('calculated_amount'), editable: editableStatus,
                    roundingLabel: incomeCalculationRoundingText(line), standardLabel: incomeCalculationPeriodText(line),
                    reason: workdayTaxLines.find(candidate => candidate.adjustment_reason)?.adjustment_reason || '',
                };
            },
        });
        const guidance = document.createElement('p');
        guidance.className = 'daily-income-institution-guidance';
        guidance.textContent = '법정기준 예상액입니다. 당시 실제 지급자료에 맞는 적용금액을 확인해 주세요.';
        host.append(guidance);
        const cardsHost = document.createElement('div');
        host.append(cardsHost);
        const applyItemLineOverride = (line, finalAmount, reason, editor) => {
            if (!line) return;
            const calculated = line.calculated_amount;
            const changed = calculated === null ? Math.abs(finalAmount) >= .01 : Math.abs(finalAmount - numberValue(calculated)) >= .01;
            const error = changed && !reason ? '자동계산액과 다른 적용금액에는 적용사유가 필요합니다.' : '';
            editor.classList.toggle('is-invalid', Boolean(error)); editor.querySelector('.invalid-feedback').textContent = error;
            item.institution_line_overrides = (item.institution_line_overrides || []).filter(row => !(row.line_type_code === line.line_type_code && row.line_code === line.line_code));
            item.institution_line_overrides.push({ line_type_code: line.line_type_code, line_code: line.line_code, final_amount: finalAmount, adjustment_reason: reason || null,
                actual_application_source_code: calculated === null ? 'HISTORICAL_ACTUAL' : (changed ? 'MANUAL_OVERRIDE' : 'AUTO_APPLIED') });
            line.final_amount = finalAmount; line.adjustment_reason = reason || null;
            scheduleAutoCalculation();
        };
        renderIncomeCalculationCards(cardsHost, dto, { onChange: (code, finalAmount, reason, editor) => {
            const dtoLine = dto.lines.find(row => row.key === code);
            if (['DAILY_WORKER_INCOME_TAX','LOCAL_INCOME_TAX'].includes(dtoLine?.sourceCode)) {
                const taxLines = [...item.workdays.values()].map(day => ({
                    day, line: (day.lines || []).find(row => row.line_code === dtoLine.sourceCode),
                })).filter(entry => entry.line).sort((left, right) => left.day.work_date.localeCompare(right.day.work_date));
                const automaticTotal = taxLines.reduce((sum, entry) => sum + numberValue(entry.line.calculated_amount), 0);
                const changed = Math.abs(finalAmount - automaticTotal) >= .01;
                const error = changed && !reason ? '자동계산액과 다른 적용금액에는 적용사유가 필요합니다.' : '';
                editor.classList.toggle('is-invalid', Boolean(error)); editor.querySelector('.invalid-feedback').textContent = error;
                const applied = taxLines.map(entry => numberValue(entry.line.calculated_amount));
                let difference = finalAmount - automaticTotal;
                if (difference >= 0 && applied.length) applied[applied.length - 1] += difference;
                if (difference < 0) {
                    let reduction = Math.abs(difference);
                    for (let index = applied.length - 1; index >= 0 && reduction > 0; index -= 1) {
                        const amount = Math.min(applied[index], reduction); applied[index] -= amount; reduction -= amount;
                    }
                }
                taxLines.forEach((entry, index) => {
                    const calculated = numberValue(entry.line.calculated_amount), value = applied[index];
                    entry.day.institution_line_overrides = (entry.day.institution_line_overrides || []).filter(row => row.line_code !== dtoLine.sourceCode);
                    if (Math.abs(value - calculated) >= .01) entry.day.institution_line_overrides.push({
                        line_code: dtoLine.sourceCode, final_amount: value, adjustment_reason: reason || null,
                        actual_application_source_code: 'HISTORICAL_ACTUAL',
                    });
                    entry.line.final_amount = value; entry.line.adjustment_reason = Math.abs(value - calculated) >= .01 ? (reason || null) : null;
                });
                scheduleAutoCalculation(); return;
            }
            const line = itemLines.find(row => row.line_type_code === dtoLine?.sourceType && row.line_code === dtoLine?.sourceCode);
            applyItemLineOverride(line, finalAmount, reason, editor);
        }, onEmployerChange: (code, finalAmount, reason, editor) => {
            const dtoLine = dto.lines.find(row => row.key === code);
            const line = itemLines.find(row => row.line_type_code === dtoLine?.employerSourceType && row.line_code === dtoLine?.employerSourceCode);
            applyItemLineOverride(line, finalAmount, reason, editor);
        }});
    };
    const refreshInstitutionDetails = () => groupItems().forEach(item => {
        const card = groupsHost.querySelector(`[data-daily-worker-key="${CSS.escape(item.client_key)}"]`);
        const host = card?.querySelector('.daily-income-institution-detail');
        if (!host) return;
        host.replaceChildren();
        if (item.worker_client_id && item.workdays.size) renderInstitutionDetails(item, host);
    });
    const nativeSelect = (rows, value, placeholder) => {
        const select = document.createElement('select');
        select.className = 'form-select form-select-sm';
        if (placeholder) select.append(new Option(placeholder, '', false, !value));
        rows.forEach(row => select.append(new Option(row.name, row.id, false, String(row.id) === String(value))));
        select.value = value == null ? '' : String(value);
        return select;
    };
    const groupPicker = (select, kind, group, quickAddEnabled = false, showCommonAdd = quickAddEnabled, afterCreate = null) => window.setTimeout(() => {
        const common = { includeCommonAdd: showCommonAdd, quickAddEnabled, minimumInputLength: 0 };
        const picker = PickerSelect2.createAjax(select, {
            ...common,
            placeholder: '선택(없음)',
            url: API.OPTIONS,
            dataBuilder: params => ({ option_type: kind, q: params.term || '', page: params.page || 1, business_unit: group.business_unit || '', project_id: group.project_id || '', income_year_month: yearMonthValue.value || '' }),
            processResults: data => ({ results: data.data?.results || [], pagination: { more: data.data?.has_more === true } }),
        });
        if (typeof afterCreate === 'function') afterCreate(picker);
    }, 0);
    const renderSummary = () => {
        const totals = calculationTotals(workGroups);
        const ready = isIncomeWithholdingDate(withholdingDateInput.value) && totals.unresolved_item_count === 0;
        const calculated = value => ready ? `${formatAmount(value)}원` : '미확정';
        documentSummary.innerHTML = `<span>근무그룹 <strong>${workGroups.length}개</strong></span><span>작업항목 <strong>${totals.item_count}건</strong></span><span>고유 작업자 <strong>${totals.unique_worker_count}명</strong></span><span>중복 작업항목 <strong>${totals.duplicate_item_count}건</strong></span><span>총 근무일수 <strong>${totals.total_work_days}일</strong></span><span>총 지급액(세전) <strong>${ready ? `${formatAmount(totals.total_gross_amount)}원` : '미확정'}</strong></span><span>총 원천징수 <strong>${calculated(totals.total_deduction_amount)}</strong></span><span>총 실지급액 <strong>${calculated(totals.total_net_payment_amount)}</strong></span><span>사용자부담 합계 <strong>${calculated(totals.total_employer_burden_amount)}</strong></span>`;
        renderWorkerResult();
    };
    const copyWorker = item => copyWorkerInstance(item, token);
    const copyGroup = group => {
        const copy = { ...createGroup(group), items: group.items.map(copyWorker) };
        workGroups.splice(workGroups.indexOf(group) + 1, 0, copy);
        selectedWorkerKey = copy.items[0]?.client_key || '';
        renderGroups();
        scheduleAutoCalculation();
    };
    const renderAgGroups = () => {
        groupsHost.querySelectorAll('select.select2-hidden-accessible').forEach(select => {
            select.dataset.pickerReady = 'false';
            PickerSelect2.destroy(select);
        });
        groupGridRegistry.destroyAll();
        groupsHost.replaceChildren();
        if (workGroups.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'alert alert-light border';
            empty.textContent = '근무그룹을 추가해 주세요.';
            groupsHost.append(empty);
            renderSummary();
            return;
        }
        workGroups.forEach((group, groupIndex) => {
            const automaticCompanyBurden = applyGroupBurdenPolicy(group);
            const card = document.createElement('article');
            card.className = 'ui-form-card daily-income-group-card';
            card.dataset.businessUnitCode = group.business_unit || '';
            card.dataset.projectId = group.project_id || '';
            card.dataset.workTeamId = group.work_team_id || '';
            card.dataset.policyStatus = group.policy_status || 'idle';
            const header = document.createElement('div');
            header.className = 'daily-income-worker-header';
            const businessName = businessUnitOption(group.business_unit)?.name
                || (group.business_unit ? '유효하지 않은 사업구분' : '사업구분 미선택');
            const groupPolicy = businessUnitOption(group.business_unit);
            const projectName = !groupPolicy?.uses_project
                ? '프로젝트 미적용'
                : ((referenceOptions.projects || []).find(row => String(row.id) === String(group.project_id))?.name
                    || group.project_name || (group.project_id ? '프로젝트 확인 필요' : '프로젝트 미선택'));
            const teamName = !groupPolicy?.uses_work_team
                ? '작업팀 미적용'
                : ((referenceOptions.work_teams || []).find(row => String(row.id) === String(group.work_team_id))?.name
                    || group.work_team_name || (group.work_team_id ? '작업팀 확인 필요' : '작업팀 미선택'));
            const groupTotals = calculationTotals([group]);
            const insuranceSummary = `고용 ${insuranceStatusLabel(group.employment_insurance_application_status_code)} · 산재 ${insuranceStatusLabel(group.industrial_accident_application_status_code)}`;
            header.innerHTML = `<div class="daily-income-group-title"><strong>그룹 ${groupIndex + 1} · ${escapeHtml(businessName)} / ${escapeHtml(projectName)} / ${escapeHtml(teamName)}</strong><small>${escapeHtml(insuranceSummary)} · 작업항목 ${groupTotals.item_count}건 · 고유 작업자 ${groupTotals.unique_worker_count}명 · 근무 ${groupTotals.total_work_days}일 · 지급액(세전) ${formatAmount(groupTotals.total_gross_amount)}원 · 원천징수 ${formatAmount(groupTotals.total_deduction_amount)}원 · 실지급액 ${formatAmount(groupTotals.total_net_payment_amount)}원</small></div>`;
            const actions = document.createElement('div');
            actions.className = 'd-flex gap-1';
            const addWorker = document.createElement('button');
            addWorker.type = 'button'; addWorker.className = 'btn btn-outline-primary btn-sm'; addWorker.textContent = '+ 작업자 추가'; addWorker.disabled = readOnly;
            addWorker.addEventListener('click', () => { group.items.forEach(item => { item.collapsed = true; }); const item = createWorker(); group.items.push(item); selectedWorkerKey = item.client_key; renderAgGroups(); });
            actions.append(addWorker);
            const duplicateGroup = document.createElement('button');
            duplicateGroup.type = 'button'; duplicateGroup.className = 'btn btn-outline-secondary btn-sm'; duplicateGroup.textContent = '그룹 복제'; duplicateGroup.disabled = readOnly;
            duplicateGroup.addEventListener('click', () => copyGroup(group)); actions.append(duplicateGroup);
            const remove = document.createElement('button');
            remove.type = 'button'; remove.className = 'btn btn-outline-danger btn-sm'; remove.textContent = '삭제'; remove.disabled = readOnly;
            remove.addEventListener('click', () => {
                groupGridRegistry.destroy(group.client_key);
                group.items.forEach(item => { item.calculation_request_version = (item.calculation_request_version || 0) + 1; });
                calculationRequestVersion += 1;
                workGroups = workGroups.filter(candidate => candidate !== group);
                renderAgGroups();
            });
            actions.append(remove);
            const collapse = document.createElement('button');
            collapse.type = 'button'; collapse.className = 'btn btn-outline-secondary btn-sm';
            collapse.textContent = group.collapsed ? '펼치기' : '접기';
            collapse.addEventListener('click', () => {
                group.collapsed = !group.collapsed;
                card.classList.toggle('is-collapsed', group.collapsed);
                collapse.textContent = group.collapsed ? '펼치기' : '접기';
                if (!group.collapsed) groupGridRegistry.refresh(group.client_key);
                renderWorkerResult();
            });
            actions.append(collapse);
            header.append(actions); card.append(header);

            const body = document.createElement('div'); body.className = 'ui-form-card__body';
            const fields = document.createElement('div'); fields.className = 'daily-income-group-fields';
            const addField = (label, control) => {
                const wrapper = document.createElement('label'); wrapper.className = 'daily-income-field';
                const caption = document.createElement('span'); caption.textContent = label;
                wrapper.append(caption, control); fields.append(wrapper); return wrapper;
            };
            const business = nativeSelect((referenceOptions.business_units || []).filter(row => referenceKey(row.id) === referenceKey(group.business_unit)), group.business_unit, '선택(없음)');
            business.disabled = readOnly;
            business.id = `daily-income-business-${group.client_key}`; business.dataset.codeGroup = 'BUSINESS_UNIT';
            const commitBusinessUnit = async rawValue => {
                if (rawValue === '__add__') return;
                const nextValue = rawValue === '__none__' ? '' : referenceKey(rawValue);
                if (group.business_unit === nextValue && group.policy_status === 'loading') return;
                if (group.business_unit === nextValue && group.policy_status === 'ready') return;
                group.business_unit = nextValue;
                group.project_id = ''; group.work_team_id = '';
                group.project_name = ''; group.work_team_name = '';
                group.policy_error = '';
                applyGroupBurdenPolicy(group);
                const requestVersion = ++group.policy_request_version;
                invalidateDocumentCalculation();
                if (!nextValue) { group.policy_status = 'idle'; renderAgGroups(); scheduleAutoCalculation({ immediate: true }); return; }
                if (businessUnitOption(nextValue)) {
                    group.policy_status = 'ready';
                    renderAgGroups();
                    scheduleAutoCalculation({ immediate: true });
                    return;
                }
                group.policy_status = 'loading';
                renderAgGroups();
                try {
                    await loadOptions(true);
                    if (requestVersion !== group.policy_request_version) return;
                    if (!businessUnitOption(nextValue)) throw new Error('유효하지 않은 사업구분입니다.');
                    group.policy_status = 'ready';
                } catch (error) {
                    if (requestVersion !== group.policy_request_version) return;
                    group.policy_status = 'failed';
                    group.policy_error = error.message || '사업구분 정책 조회에 실패했습니다.';
                    notify('error', group.policy_error);
                }
                if (requestVersion !== group.policy_request_version) return;
                renderAgGroups(); scheduleAutoCalculation({ immediate: true });
            };
            business.addEventListener('change', () => void commitBusinessUnit(business.value));
            business.addEventListener('picker:add', () => openCodeQuickModal({ codeGroup: 'BUSINESS_UNIT', targetSelectId: business.id }));
            const businessField = addField('사업구분 *', business);
            groupPicker(business, 'business_unit', group, true, true, picker => {
                picker?.off('select2:select.dailyIncomeBusiness').on('select2:select.dailyIncomeBusiness', event => {
                    const selectedId = String(event.params?.data?.id ?? '').trim();
                    if (selectedId !== '__add__') void commitBusinessUnit(selectedId);
                });
            });
            if (group.policy_status === 'failed') {
                const policyStatus = document.createElement('small');
                policyStatus.className = 'text-danger';
                policyStatus.textContent = '사업구분 정책 조회 실패';
                businessField.append(policyStatus);
            }
            const policy = businessUnitOption(group.business_unit);
            const selectedProjects = (referenceOptions.projects || []).filter(row => String(row.id) === String(group.project_id));
            if (group.project_id && selectedProjects.length === 0) selectedProjects.push({ id: group.project_id, name: group.project_name || '과거 프로젝트' });
            const project = nativeSelect(selectedProjects, group.project_id, '선택(없음)');
            project.disabled = readOnly || group.policy_status !== 'ready' || !policy?.uses_project;
            project.addEventListener('change', () => { group.project_id = project.value; group.project_name = project.value ? (project.selectedOptions[0]?.textContent || '') : ''; group.work_team_id = ''; group.work_team_name = ''; invalidateGroupCalculation(group); renderAgGroups(); scheduleAutoCalculation({ immediate: true }); });
            addField(policy?.requires_project ? '프로젝트 *' : '프로젝트', project); groupPicker(project, 'project', group, false, true);
            const selectedTeams = (referenceOptions.work_teams || []).filter(row => String(row.id) === String(group.work_team_id));
            if (group.work_team_id && selectedTeams.length === 0) selectedTeams.push({ id: group.work_team_id, name: group.work_team_name || '과거 작업팀' });
            const team = nativeSelect(selectedTeams, group.work_team_id, '선택(없음)');
            team.disabled = readOnly || group.policy_status !== 'ready' || !policy?.uses_work_team;
            team.addEventListener('change', () => { if (team.value !== '__add__') { group.work_team_id = team.value; group.work_team_name = team.value ? (team.selectedOptions[0]?.textContent || '') : ''; invalidateGroupCalculation(group); renderAgGroups(); scheduleAutoCalculation({ immediate: true }); } });
            team.addEventListener('picker:add', () => void openWorkTeamQuickCreate({
                initialValues: { business_unit: group.business_unit }, notify,
                onSuccess: async result => {
                    const created = result.data || result;
                    const candidateId = String(created.id || '').trim();
                    const response = await request(`${API.OPTIONS}?${new URLSearchParams({ option_type: 'work_team', q: created.team_name || '', page: '1', business_unit: group.business_unit || '', project_id: group.project_id || '', income_year_month: yearMonthValue.value || '' })}`);
                    const allowed = (response.data?.results || []).find(row => String(row.id) === candidateId);
                    if (!allowed) { notify('warning', '등록한 팀을 현재 사업구분의 팀 목록에서 확인할 수 없어 바로 선택할 수 없습니다.'); return; }
                    await loadOptions(true); group.work_team_id = candidateId; group.work_team_name = allowed.text || '';
                    invalidateGroupCalculation(group); renderAgGroups(); scheduleAutoCalculation({ immediate: true });
                },
            }));
            addField(policy?.requires_work_team ? '팀 *' : '팀', team); groupPicker(team, 'work_team', group, true);
            const groupDescription = document.createElement('input'); groupDescription.className = 'form-control form-control-sm'; groupDescription.value = group.work_description || ''; groupDescription.disabled = readOnly;
            groupDescription.addEventListener('input', () => {
                group.work_description = groupDescription.value;
                renderCalculationReadiness();
                scheduleAutoCalculation({ immediate: true });
            });
            addField('그룹 작업내용 *', groupDescription);
            [['employment_insurance', '고용보험 회사부담'], ['industrial_accident', '산재보험 회사부담']].forEach(([prefix, label]) => {
                    if (automaticCompanyBurden) {
                        const value = document.createElement('input');
                        value.className = 'form-control form-control-sm';
                        value.value = '우리 회사 부담'; value.disabled = true;
                        addField(label, value);
                        return;
                    }
                    const status = nativeSelect([
                        { id: 'APPLICABLE', name: '우리 회사 부담' },
                        { id: 'EXCLUDED', name: '우리 회사 미부담' },
                    ], group[`${prefix}_application_status_code`] || '', '선택해 주세요');
                    status.disabled = readOnly;
                    status.addEventListener('change', () => {
                        group[`${prefix}_application_status_code`] = status.value;
                        group[`${prefix}_decision_source_code_id`] = status.value ? 'DAILY_GROUP_MANUAL_SETTING' : null;
                        group[`${prefix}_decision_source_code`] = status.value ? 'DAILY_GROUP_MANUAL_SETTING' : null;
                        group[`${prefix}_set_by`] = '';
                        group[`${prefix}_set_at`] = '';
                        invalidateGroupCalculation(group); renderAgGroups(); scheduleAutoCalculation({ immediate: true });
                    });
                    addField(`${label} *`, status);
                });
            body.append(fields);

            const wrap = document.createElement('div'); wrap.className = 'daily-income-group-grid-wrap';
            const host = document.createElement('div'); host.className = 'daily-income-group-grid';
            wrap.append(host); body.append(wrap); card.append(body); groupsHost.append(card);
            card.classList.toggle('is-collapsed', group.collapsed);
            groupGridRegistry.mount(host, group, {
                month: yearMonthValue.value, dates: monthDates(yearMonthValue.value), workers: referenceOptions.workers || [],
                workTypes: referenceOptions.work_types || [], readOnly, copyItem: copyWorker,
                defaultWorkMinutes: DEFAULT_WORKDAY_MINUTES,
                confirmClearWorkdays: count => confirmDialog({
                    title: '전체 근무일 선택 해제',
                    message: `선택된 근무일 ${count}일의 근로시간·단가·증감·근거자료가 모두 제거됩니다. 계속하시겠습니까?`,
                    confirmText: '전체선택해제',
                    confirmClass: 'btn-danger',
                }),
                onSelect: (_group, item) => { selectedWorkerKey = item.client_key; renderWorkerResult(); if (!item.calculation) scheduleAutoCalculation(); },
                renderInstitutionDetails: (item, host) => {
                    if (item.worker_client_id && item.workdays.size) renderInstitutionDetails(item, host);
                },
                onWorkerChanged: item => {
                    resetWorkerCalculationState(item, { resetDraftAdjustments: true });
                    selectedWorkerKey = item.client_key;
                    window.setTimeout(() => renderGroups(), 0);
                },
                onCalculationInputChanged: item => {
                    resetWorkerCalculationState(item, { resetDraftAdjustments: true });
                    syncWorkerCalculationIndicator(item);
                    selectedWorkerKey = item.client_key;
                },
                isDuplicateWorker: (candidateGroup, candidateItem) => candidateItem.worker_client_id
                    && candidateGroup.items.filter(row => String(row.worker_client_id) === String(candidateItem.worker_client_id)).length > 1,
                onDelete: (deletedGroup, deletedIndex, deletedItem) => {
                    deletedItem.calculation_request_version = (deletedItem.calculation_request_version || 0) + 1;
                    calculationRequestVersion += 1;
                    if (selectedWorkerKey === deletedItem.client_key) {
                        const next = selectionAfterDelete(deletedGroup.items, deletedIndex) || groupItems()[0] || null;
                        selectedWorkerKey = next?.client_key || '';
                    }
                    renderSummary(); scheduleAutoCalculation();
                },
            });
        });
        renderSummary();
    };
    const renderGroups = () => renderAgGroups();
    const payloadGroups = () => workGroups.map(group => {
        if (!group.business_unit) throw new Error('사업구분을 선택해 주세요.');
        const policy = businessUnitOption(group.business_unit);
        if (!policy) throw new Error('유효하지 않은 사업구분입니다.');
        if (group.policy_status === 'failed') throw new Error('사업구분 정책 조회에 실패했습니다. 다시 선택해 주세요.');
        if (policy.requires_project && !group.project_id) throw new Error('선택한 사업구분에는 프로젝트가 필요합니다.');
        if (policy.requires_work_team && !group.work_team_id) throw new Error('선택한 사업구분에는 작업팀이 필요합니다.');
        return {
        client_key: group.client_key, business_unit: policy.id,
        project_id: policy?.uses_project ? (group.project_id || null) : null,
        work_team_id: policy?.uses_work_team ? (group.work_team_id || null) : null,
        work_description: group.work_description,
        employment_insurance_application_status_code: group.employment_insurance_application_status_code || null,
        employment_insurance_decision_reason: group.employment_insurance_decision_reason || null,
        employment_insurance_decision_source_code: group.employment_insurance_decision_source_code || null,
        employment_insurance_decision_source_code_id: group.employment_insurance_decision_source_code_id && group.employment_insurance_decision_source_code_id !== 'GROUP_MANUAL_SETTING' ? group.employment_insurance_decision_source_code_id : null,
        employment_insurance_set_by: group.employment_insurance_set_by || null,
        employment_insurance_set_at: group.employment_insurance_set_at || null,
        industrial_accident_application_status_code: group.industrial_accident_application_status_code || null,
        industrial_accident_decision_reason: group.industrial_accident_decision_reason || null,
        industrial_accident_decision_source_code: group.industrial_accident_decision_source_code || null,
        industrial_accident_decision_source_code_id: group.industrial_accident_decision_source_code_id && group.industrial_accident_decision_source_code_id !== 'GROUP_MANUAL_SETTING' ? group.industrial_accident_decision_source_code_id : null,
        industrial_accident_set_by: group.industrial_accident_set_by || null,
        industrial_accident_set_at: group.industrial_accident_set_at || null,
        items: group.items.map((worker, workerIndex) => {
            const days = [...worker.workdays.values()].sort((a, b) => a.work_date.localeCompare(b.work_date));
            return {
                client_key: worker.client_key, sort_no: workerIndex + 1, worker_client_id: worker.worker_client_id,
                work_type_code: worker.work_type_code,
                work_description: worker.work_description,
                daily_rate_amount: numberValue(worker.daily_rate_amount),
                institution_line_overrides: worker.institution_line_overrides || [],
                workdays: days.map(day => ({
                    ...day, work_quantity: 1,
                    taxable_additional_amount: numberValue(day.taxable_additional_amount),
                    non_taxable_additional_amount: numberValue(day.non_taxable_additional_amount),
                })),
            };
        }),
    };});
    const calculateDocument = async ({ render = true } = {}) => {
        const requestVersion = ++calculationRequestVersion;
        if (!normalizeYearMonth()) throw new Error('귀속연월을 입력해 주세요.');
        if (!isIncomeWithholdingDate(withholdingDateInput.value)) throw new Error('원천징수일을 입력하면 해당 날짜의 법정기준으로 자동 계산됩니다.');
        if (workGroups.length === 0) throw new Error('근무그룹을 한 개 이상 추가해 주세요.');
        const invalidWorkdays = invalidWorkMinuteWorkdays();
        if (invalidWorkdays.length > 0) {
            renderCalculationReadiness();
            throw new Error('실제근로시간이 누락되었거나 유효하지 않은 Workday가 있습니다. 1~1,440분 정수로 입력해 주세요.');
        }
        const requestGroups = payloadGroups();
        const cardRequests = new Map();
        workGroups.forEach(group => group.items.forEach(item => {
            const sourceKey = workerCalculationSourceKey(group, item);
            const cardVersion = (item.calculation_request_version || 0) + 1;
            item.calculation_request_version = cardVersion;
            item.calculation_state = 'loading';
            item.calculation_error = '';
            cardRequests.set(`${group.client_key}|${item.client_key}`, { item, sourceKey, cardVersion });
            syncWorkerCalculationIndicator(item);
        }));
        renderWorkerResult();
        renderCalculationReadiness();
        requestGroups.forEach(group => group.items.forEach(item => {
            const snapshot = cardRequests.get(`${group.client_key}|${item.client_key}`);
            if (snapshot) item.calculation_source_key = snapshot.sourceKey;
        }));
        const requestPayload = { id: form.elements.id.value || null, income_year_month: yearMonthValue.value, withholding_date: withholdingDateInput.value, groups: requestGroups };
        const payloadKey = JSON.stringify(requestPayload);
        if (!render && payloadKey === lastPreviewPayloadKey) return [];
        calculationAbortController?.abort();
        calculationAbortController = new AbortController();
        let response;
        try {
            response = await request(API.CALCULATE, { method: 'POST', signal: calculationAbortController.signal, body: payloadKey });
        } catch (error) {
            if (error?.name === 'AbortError') return [];
            if (requestVersion === calculationRequestVersion) {
                cardRequests.forEach(snapshot => {
                    if (snapshot.item.calculation_request_version !== snapshot.cardVersion) return;
                    snapshot.item.calculation_state = 'error';
                    snapshot.item.calculation_error = error.message || '계산 중 오류가 발생했습니다.';
                    syncWorkerCalculationIndicator(snapshot.item);
                });
                renderWorkerResult();
                renderCalculationReadiness();
            }
            throw error;
        }
        if (requestVersion !== calculationRequestVersion) return [];
        lastPreviewPayloadKey = payloadKey;
        (response.data?.groups || []).forEach(resultGroup => {
            const liveGroup = workGroups.find(group => group.client_key === resultGroup.client_key);
            if (!liveGroup) return;
            (resultGroup.items || []).forEach(result => {
                const worker = liveGroup.items.find(item => item.client_key === result.client_key);
                const snapshot = cardRequests.get(`${resultGroup.client_key}|${result.client_key}`);
                if (!worker || !snapshot || snapshot.item !== worker) return;
                if (worker.calculation_request_version !== snapshot.cardVersion) return;
                if (result.calculation_source_key !== snapshot.sourceKey) return;
                if (workerCalculationSourceKey(liveGroup, worker) !== snapshot.sourceKey) return;
                worker.calculation = result;
                worker.calculation_source_key = snapshot.sourceKey;
                worker.calculation_state = 'complete';
                worker.calculation_error = '';
                syncWorkerCalculationIndicator(worker);
                worker.workdays = new Map((result.workdays || []).map(day => {
                    const currentDay = worker.workdays.get(day.work_date);
                    return [day.work_date, {
                        ...day,
                        client_key: currentDay?.client_key || token(),
                        lines: (day.lines || []).map(line => ({ ...line, client_key: line.client_key || token() })),
                    }];
                }));
            });
        });
        if (render) renderGroups();
        else { groupGridRegistry.refreshTotals(workGroups); renderSummary(); refreshInstitutionDetails(); }
        renderCalculationReadiness();
        return response.data?.groups || [];
    };
    function invalidWorkMinuteWorkdays() {
        const invalid = [];
        workGroups.forEach(group => group.items.forEach(item => item.workdays.forEach(day => {
            const raw = day.actual_work_minutes;
            const minutes = typeof raw === 'number' ? raw : (String(raw ?? '').trim() === '' ? NaN : Number(raw));
            if (!Number.isInteger(minutes) || minutes < 1 || minutes > 1440) {
                invalid.push({
                    workday_id: day.id || day.client_key || '',
                    work_date: day.work_date || '',
                    worker_name: item.worker_name || item.worker_client_name || item.worker_name_snapshot || '작업자',
                });
            }
        })));
        return invalid;
    }
    function calculationMissingInputs() {
        const missing = [];
        workGroups.forEach((group, groupIndex) => {
            const groupLabel = `${groupIndex + 1}번째 근무그룹`;
            const policy = businessUnitOption(group.business_unit);
            if (!group.business_unit) missing.push(`${groupLabel}의 사업구분을 선택해 주세요.`);
            else if (!policy || group.policy_status !== 'ready') missing.push(`${groupLabel}의 사업구분 정책을 확인해 주세요.`);
            if (policy?.requires_project && !group.project_id) missing.push(`${groupLabel}의 프로젝트를 선택해 주세요.`);
            if (policy?.requires_work_team && !group.work_team_id) missing.push(`${groupLabel}의 작업팀을 선택해 주세요.`);
            if (!group.items.length) missing.push(`${groupLabel}에 작업자를 추가해 주세요.`);
            group.items.forEach((item, itemIndex) => {
                const itemLabel = `${groupIndex + 1}번째 그룹 ${itemIndex + 1}번째 작업자`;
                if (!item.worker_client_id) missing.push(`${itemLabel}를 선택해 주세요.`);
                if (!item.work_type_code) missing.push(`${itemLabel}의 공종을 선택해 주세요.`);
                if (!item.workdays.size) missing.push(`${itemLabel}의 근무일을 선택해 주세요.`);
            });
        });
        return missing;
    }
    function documentMissingInputs() {
        const missing = calculationMissingInputs();
        workGroups.forEach((group, groupIndex) => {
            if (!String(group.work_description || '').trim()) missing.push(`${groupIndex + 1}번째 근무그룹의 그룹 작업내용을 입력해 주세요.`);
            group.items.forEach((item, itemIndex) => {
                if (!String(item.work_description || '').trim()) missing.push(`${groupIndex + 1}번째 그룹 ${itemIndex + 1}번째 작업자의 작업내용을 입력해 주세요.`);
            });
        });
        return missing;
    }
    function renderCalculationReadiness() {
        const container = document.getElementById('dailyIncomeCalculationReadiness');
        if (!container) return;
        const invalid = invalidWorkMinuteWorkdays();
        const calculationErrors = workGroups.flatMap((group, groupIndex) => group.items
            .filter(item => item.calculation_state === 'error' && item.calculation_error)
            .map((item, itemIndex) => `${groupIndex + 1}번째 그룹 ${itemIndex + 1}번째 작업자: ${item.calculation_error}`));
        if (calculationErrors.length > 0) {
            container.classList.remove('d-none');
            container.innerHTML = `<strong>자동계산 오류</strong><br>${calculationErrors.map(message => escapeHtml(message)).join('<br>')}`;
            syncWorkflowActions();
            return;
        }
        if (!isIncomeWithholdingDate(withholdingDateInput.value)) {
            container.classList.remove('d-none');
            container.textContent = '원천징수일을 입력하면 해당 날짜의 법정기준으로 자동 계산됩니다.';
            syncWorkflowActions();
            return;
        }
        const missingInputs = calculationMissingInputs();
        if (missingInputs.length > 0) {
            container.classList.remove('d-none');
            container.innerHTML = `<strong>자동계산에 필요한 필수값을 입력해 주세요.</strong><br>${missingInputs.map(message => escapeHtml(message)).join('<br>')}`;
            syncWorkflowActions();
            return;
        }
        container.classList.toggle('d-none', invalid.length === 0);
        if (invalid.length === 0) {
            container.replaceChildren();
            syncWorkflowActions();
            return;
        }
        const dates = invalid.map(day => `${day.work_date || '날짜 미확인'} (${day.worker_name})`).join(', ');
        container.innerHTML = `<strong>계산에 필요한 근로시간이 누락되었습니다: ${escapeHtml(dates)}</strong><br>실제근로시간을 <strong>1~1,440분 정수</strong>로 입력해 주세요. 입력하면 세금·보험료와 실지급액이 자동으로 다시 계산됩니다.<br>계산 결과를 확인한 후 저장할 수 있습니다.`;
        syncWorkflowActions();
    }
    const canAutoCalculate = () => /^\d{4}-\d{2}$/.test(yearMonthValue.value)
        && isIncomeWithholdingDate(withholdingDateInput.value)
        && workGroups.length > 0
        && calculationMissingInputs().length === 0
        && invalidWorkMinuteWorkdays().length === 0;
    function scheduleAutoCalculation({ immediate = false } = {}) {
        if (calculationTimer !== null) window.clearTimeout(calculationTimer);
        calculationTimer = null;
        calculationAbortController?.abort();
        calculationRequestVersion += 1;
        renderCalculationReadiness();
        if (readOnly || !canAutoCalculate()) return;
        calculationTimer = window.setTimeout(() => {
            calculationTimer = null;
            calculateDocument({ render: false }).catch(() => {});
        }, immediate ? 0 : 350);
    }

    yearMonthPicker.subscribe((_, date) => { setYearMonth(date ? formatYearMonthValue(date) : ''); yearMonthPicker.close(); yearMonthDisplay.dispatchEvent(new Event('change', { bubbles: true })); });
    withholdingDatePicker.subscribe((_, date) => { if(!date)return;withholdingDateInput.value=[date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');withholdingDatePicker.close();withholdingDateInput.dispatchEvent(new Event('change',{bubbles:true})); });
    document.getElementById('dailyIncomeYearMonthButton').addEventListener('click', () => yearMonthPicker.open({ anchor: yearMonthDisplay }));
    document.getElementById('dailyIncomeWithholdingDateButton').addEventListener('click',()=>{if(!readOnly)withholdingDatePicker.open({anchor:withholdingDateInput});});
    withholdingDateInput.addEventListener('click',()=>{if(!readOnly)withholdingDatePicker.open({anchor:withholdingDateInput});});
    withholdingDateInput.addEventListener('change', () => {
        if (readOnly) return;
        invalidateDocumentCalculation({ resetDraftAdjustments: false });
        renderGroups();
        renderCalculationReadiness();
        if (isIncomeWithholdingDate(withholdingDateInput.value)) scheduleAutoCalculation({ immediate: true });
    });
    yearMonthDisplay.addEventListener('input', () => { yearMonthDisplay.value = normalizeYearMonthInputValue(yearMonthDisplay.value); normalizeYearMonth(); });
    yearMonthDisplay.addEventListener('change', async () => {
        if (!normalizeYearMonth()) return;
        const nextMonth = yearMonthValue.value;
        if (nextMonth === committedYearMonth) return;
        const affected = groupItems().filter(item => item.workdays.size > 0);
        if (readOnly && affected.length > 0) {
            setYearMonth(committedYearMonth);
            notify('warning', '결재진행 또는 승인된 문서의 귀속연월은 변경할 수 없습니다.');
            return;
        }
        if (affected.length > 0) {
            const workdayCount = affected.reduce((sum, item) => sum + item.workdays.size, 0);
            const paymentChange = affected.reduce((sum, item) => sum + [...item.workdays.values()].reduce((daySum, day) => daySum + workdayGross(day), 0), 0);
            const accepted = await confirmDialog({
                title: '귀속연월 변경 영향 확인',
                message: `기존 귀속연월 ${committedYearMonth || '-'}에서 ${nextMonth}(으)로 변경하면 작업자 ${affected.length}명의 Workday ${workdayCount}건이 삭제되고 지급액 ${formatAmount(paymentChange)}원이 다시 계산됩니다. 계속하시겠습니까?`,
                confirmText: '변경',
                confirmClass: 'btn-danger',
            });
            if (!accepted) { setYearMonth(committedYearMonth); return; }
            affected.forEach(item => { item.workdays.clear(); item.calculation = null; });
        }
        applyDefaultWithholdingDate();
        committedYearMonth = nextMonth;
        invalidateDocumentCalculation();
        renderGroups();
        scheduleAutoCalculation({ immediate: true });
    });
    groupsHost.addEventListener('focusout', event => {
        if (event.target.matches('.daily-income-workday-grid input, [data-bulk-minutes], [data-bulk-rate]')) scheduleAutoCalculation({ immediate: true });
    });
    groupsHost.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.matches('.daily-income-workday-grid input, [data-bulk-minutes], [data-bulk-rate]')) scheduleAutoCalculation({ immediate: true });
    });
    modalElement.addEventListener('hidden.bs.modal', () => {
        if (calculationTimer !== null) window.clearTimeout(calculationTimer); calculationTimer = null; calculationRequestVersion += 1;
        calculationAbortController?.abort(); calculationAbortController = null; lastPreviewPayloadKey = '';
        yearMonthPicker.close(); withholdingDatePicker.close(); groupGridRegistry.destroyAll();
        groupsHost.querySelectorAll('select.select2-hidden-accessible').forEach(select => PickerSelect2.destroy(select));
    });
    document.getElementById('dailyIncomeAddWorker').addEventListener('click', async () => {
        try { await loadOptions(); workGroups.push(createGroup()); renderGroups(); }
        catch (error) { notify('error', error.message || '선택 목록을 불러오는 중 오류가 발생했습니다.'); }
    });

    const excelModalElement = document.getElementById('dailyIncomeExcelModal');
    const excelModal = bootstrap.Modal.getOrCreateInstance(excelModalElement, { focus: false });
    document.getElementById('dailyIncomeExcelManager').addEventListener('click', () => {
        if (readOnly) { notify('warning', '수정 가능한 문서에서만 엑셀 업로드를 사용할 수 있습니다.'); return; }
        excelModal.show();
    });
    await createDailyEmploymentIncomeExcelProvider({
        formSelector: '#daily-income-excel-form', downloadUrl: API.EXCEL_DOWNLOAD,
        getGroups: payloadGroups, getIncomeYearMonth: () => yearMonthValue.value,
        getHeader: () => ({ income_year_month: yearMonthValue.value, withholding_date: withholdingDateInput.value }),
        confirmPreview: summary => confirmDialog({
                title: '엑셀 Preview 적용',
                message: `근무그룹 ${summary.group_count || 0}개, 작업자 ${summary.row_count || 0}행을 현재 문서에 반영하시겠습니까? DB에는 아직 저장되지 않습니다.`,
                confirmText: 'Grid에 적용', confirmClass: 'btn-success',
            }),
        applyPreview: groups => {
            groups.forEach(group => {
                const businessUnit = (referenceOptions?.business_units || []).find(row =>
                    referenceKey(row.business_unit) === referenceKey(group.business_unit)
                    || referenceKey(row.id ?? row.code) === referenceKey(group.business_unit)
                );
                if (!businessUnit) throw new Error('엑셀 행의 사업구분을 현재 활성 기준정보에서 확인할 수 없습니다.');
                group.business_unit = businessUnit.id;
                group.policy_status = 'ready';
                group.policy_error = '';
                if (group.project_id) {
                    const project = (referenceOptions?.projects || []).find(row => String(row.id) === String(group.project_id));
                    if (!project || referenceKey(project.business_unit) !== referenceKey(group.business_unit)) {
                        throw new Error('엑셀 행의 프로젝트와 사업구분이 일치하지 않습니다.');
                    }
                }
                if (group.work_team_id) {
                    const workTeam = (referenceOptions?.work_teams || []).find(row => String(row.id) === String(group.work_team_id));
                    if (!workTeam || referenceKey(workTeam.business_unit) !== referenceKey(group.business_unit)) {
                        throw new Error('엑셀 행의 작업팀과 사업구분이 일치하지 않습니다.');
                    }
                }
                if (!businessUnit.uses_project) group.project_id = null;
                if (!businessUnit.uses_work_team) group.work_team_id = null;
            });
            workGroups = groups.map(group => ({
                ...group, client_key: token(), collapsed: false,
                items: (group.items || []).map(item => ({
                    ...createWorker(), ...item, client_key: token(), worker_name: item.worker_name || item.worker_name_snapshot || '',
                    workdays: new Map((item.workdays || []).map(day => [day.work_date, day])), calculation: null,
                })),
            }));
            renderGroups(); scheduleAutoCalculation(); notify('success', '엑셀 Preview를 Group Grid에 반영했습니다.');
        },
    });

    const statusMeta = value => ({
        DRAFT: ['미상신', 'text-bg-secondary'], PENDING: ['결재 진행', 'text-bg-primary'],
        APPROVED: ['승인 완료', 'text-bg-success'], REJECTED: ['반려', 'text-bg-danger'],
        WITHDRAWN: ['회수', 'text-bg-warning'],
    }[String(value || '').toUpperCase()] || ['확인 필요', 'text-bg-warning']);
    const statusBadge = value => { const [label, className] = statusMeta(value); return `<span class="badge ${className}">${label}</span>`; };
    const amount = value => value === null || value === undefined || value === '' ? '-' : `${formatAmount(value)}원`;
    const table = await createDataTable({
        tableSelector: '#daily-income-table', api: API.LIST, serverSide: true, selectable: true,
        deleteApi: API.DELETE, searchTableId: 'dailyIncome',
        columns: [
            { data: 'sort_no', title: '순번', className: 'text-center' },
            { data: 'income_year_month', title: '귀속연월' },
            { data: 'document_title', title: '제목' }, { data: 'worker_count', title: '작업자 수', className: 'text-end' },
            { data: 'work_team_count', title: '소속팀 수', className: 'text-end' }, { data: 'total_work_days', title: '총 근무일수', className: 'text-end' },
            { data: 'total_gross_amount', title: '총 지급액', className: 'text-end', render: amount },
            { data: 'total_deduction_amount', title: '총 공제액', className: 'text-end', render: amount },
            { data: 'total_net_payment_amount', title: '실지급액', className: 'text-end', render: amount },
            { data: 'total_employer_burden_amount', title: '사용자부담금', className: 'text-end', render: amount },
            { data: 'status_code', title: '문서상태', className: 'text-center', render: (value, type) => type === 'display' ? statusBadge(value) : statusMeta(value)[0] },
            actorColumn('created_by', '작성자', { defaultContent: '' }),
            { data: 'created_at', title: '작성일시', render: value => formatDateDisplay(value) || '-' },
            { data: null, title: '관리', orderable: false, searchable: false, render: () => '<button class="btn btn-outline-primary btn-sm" data-detail>상세</button>' },
        ],
        tableSettings: { pageKey: 'institution.income-data.daily-employment', tableKey: 'daily-income-table', metaDomain: 'daily-employment-income', tableLabel: '일용근로소득' },
        buttons: [
            { text: '휴지통', className: 'btn btn-danger btn-sm', action: () => bootstrap.Modal.getOrCreateInstance(document.getElementById('dailyIncomeTrashModal')).show() },
            { text: '신규등록', className: 'btn btn-warning btn-sm', action: async () => {
                form.reset(); form.elements.id.value = ''; setYearMonth(''); committedYearMonth = ''; workGroups = []; selectedWorkerKey = ''; readOnly = false; documentStatus = 'DRAFT'; currentApprovalRequestId = ''; renderSystemInfo(null);
                [yearMonthDisplay, withholdingDateInput, form.elements.document_title, form.elements.description, form.elements.memo].forEach(control => { control.disabled = false; });
                document.getElementById('dailyIncomeWithholdingDateButton').setAttribute('aria-disabled', 'false');
                document.getElementById('dailyIncomeAddWorker').classList.remove('d-none');
                form.querySelector('button[type="submit"]').classList.remove('d-none');
                await loadOptions(); applyFormSettings(); renderGroups(); syncWorkflowActions(); modal.show();
            } },
        ],
    });
    const formSettings = createDataTableFormSettings({ form, metaDomain: 'daily-employment-income', storageKey: TABLE_SETTINGS_KEY, settingsOptions: TABLE_SETTINGS_OPTIONS });
    const applyFormSettings = () => { const resolved=formSettings.apply();yearMonthDisplay.required=resolved.get('income_year_month')?.required===true;renderSystemInfo(currentSystemInfoData); };
    const isOwnSettingsEvent = event => {
        const key = String(event.detail?.storageKey || '').trim();
        return key === TABLE_SETTINGS_KEY || key === `datatable.settings.${TABLE_SETTINGS_KEY}`;
    };
    applyFormSettings();
    document.addEventListener('datatable-settings:updated', event => { if (isOwnSettingsEvent(event)) applyFormSettings(); });
    SearchForm({ table, apiList: API.LIST, tableId: 'dailyIncome', defaultSearchField: 'income_year_month' });
    const updateCount = () => { document.getElementById('dailyIncomeCount').textContent = `총 ${table.page.info()?.recordsDisplay ?? 0}건`; };
    table.on('init.dt draw.dt xhr.dt', updateCount);
    const openDetail = async id => {
        if (!id) return;
        try {
            const response = await request(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
            const header = response.data?.header || {};
            renderSystemInfo(header);
            documentStatus = String(header.status_code || 'DRAFT').toUpperCase();
            currentApprovalRequestId = String(header.approval_request_id || '');
            readOnly = !isIncomeCalculationEditableStatus(header.status_code);
            form.elements.id.value = header.id || ''; setYearMonth(header.income_year_month || ''); withholdingDateInput.value=header.withholding_date||''; committedYearMonth = yearMonthValue.value;
            form.elements.document_title.value = header.document_title || '';
            form.elements.description.value = header.description || '';
            form.elements.memo.value = header.memo || '';
            await loadOptions();
            const calculationResults = Array.isArray(response.data?.calculation_revision?.results)
                ? response.data.calculation_revision.results : [];
            const calculationProjection = (itemId, lineCode) => {
                const resultCodes = lineCode === 'LONG_TERM_CARE' ? ['LONG_TERM_CARE', 'LONG_TERM_CARE_INSURANCE']
                    : lineCode === 'INDUSTRIAL_ACCIDENT_INSURANCE' ? ['INDUSTRIAL_ACCIDENT', 'INDUSTRIAL_ACCIDENT_INSURANCE']
                        : lineCode === 'EMPLOYMENT_INSURANCE_VOCATIONAL' ? ['EMPLOYMENT_INSURANCE']
                        : [lineCode];
                const result = calculationResults.find(row => String(row.daily_employment_income_item_id || '') === String(itemId || '')
                    && resultCodes.includes(String(row.result_type_code || '')));
                if (!result) return null;
                const snapshot = result.eligibility_snapshot && typeof result.eligibility_snapshot === 'object'
                    ? result.eligibility_snapshot : {};
                return {
                    ...snapshot,
                    eligibility_status_code: result.eligibility_status_code || snapshot.eligibility_status_code
                        || result.application_status_code || snapshot.manual_application_status_code || snapshot.result_code,
                    reason_code: result.eligibility_reason_code || snapshot.eligibility_reason_code || snapshot.reason_code || '',
                    reason_name: result.eligibility_reason_name || snapshot.eligibility_reason_name || snapshot.reason_name || '',
                    reason_detail: result.eligibility_reason_detail || snapshot.eligibility_reason_detail || snapshot.reason_detail || '',
                    workplace_name: result.workplace_name || snapshot.workplace_name || '',
                    coverage_confirmation_status_name: snapshot.coverage_confirmation_status_name || '',
                };
            };
            (response.data?.groups || []).forEach(group => {
                const policy = group.business_unit_policy;
                if (policy && !businessUnitOption(policy.id)) referenceOptions.business_units.push({ ...policy, historical: true });
            });
            workGroups = (response.data?.groups || []).map(group => ({
                ...group, business_unit: referenceKey(group.business_unit), client_key: token(), collapsed: false,
                policy_status: businessUnitOption(group.business_unit) ? 'ready' : 'failed', policy_error: '',
                items: (group.items || []).map(item => {
                    const itemLines = (item.lines || []).filter(line => !line.daily_employment_income_workday_id).map(line => ({
                        ...line,
                        eligibility_projection: calculationProjection(item.id, line.line_code),
                    }));
                    const overrides = itemLines.filter(line => ['DEDUCTION','EMPLOYER_BURDEN'].includes(line.line_type_code)).map(line => ({
                        line_type_code: line.line_type_code, line_code: line.line_code, final_amount: line.final_amount,
                        adjustment_reason: line.adjustment_reason || null,
                        actual_application_source_code: line.actual_application_source_code || (line.calculated_amount === null ? 'HISTORICAL_ACTUAL' : 'AUTO_APPLIED'),
                    }));
                    return ({
                    ...createWorker(), ...item, client_key: token(), institution_line_overrides: overrides,
                    daily_rate_amount: item.workdays?.[0]?.daily_rate_amount ?? null,
                    workdays: new Map((item.workdays || []).map(day => [day.work_date, {
                        ...day,
                        institution_line_overrides: (day.lines || []).filter(line => ['DAILY_WORKER_INCOME_TAX','LOCAL_INCOME_TAX'].includes(line.line_code)).map(line => ({
                            line_code: line.line_code, final_amount: line.final_amount,
                            adjustment_reason: line.adjustment_reason || null,
                            actual_application_source_code: line.calculated_amount === null || numberValue(line.calculated_amount) !== numberValue(line.final_amount) ? 'HISTORICAL_ACTUAL' : 'AUTO_APPLIED',
                        })),
                        taxable_additional_amount: day.taxable_additional_amount ?? day.allowance_amount ?? 0,
                        non_taxable_additional_amount: day.non_taxable_additional_amount ?? day.non_taxable_amount ?? 0,
                        lines: (item.lines || []).filter(line => String(line.daily_employment_income_workday_id || '') === String(day.id || '')),
                    }])),
                    calculation: { lines: itemLines, summary: {
                        total_work_days: item.total_work_days, total_gross_amount: item.total_gross_amount,
                        total_deduction_amount: item.total_deduction_amount, total_net_payment_amount: item.total_net_payment_amount,
                        total_employer_burden_amount: item.total_employer_burden_amount,
                    } },
                });}),
            }));
            selectedWorkerKey = groupItems()[0]?.client_key || '';
            renderGroups();
            [yearMonthDisplay, withholdingDateInput, form.elements.document_title, form.elements.description, form.elements.memo].forEach(control => { control.disabled = readOnly; });
            const withholdingDateButton = document.getElementById('dailyIncomeWithholdingDateButton');
            withholdingDateButton.setAttribute('aria-disabled', readOnly ? 'true' : 'false');
            withholdingDateButton.tabIndex = readOnly ? -1 : 0;
            if (readOnly) withholdingDatePicker.close();
            document.getElementById('dailyIncomeAddWorker').classList.toggle('d-none', readOnly);
            form.querySelector('button[type="submit"]').classList.toggle('d-none', readOnly);
            syncWorkflowActions(); applyFormSettings(); modal.show();
            renderCalculationReadiness();
        } catch (error) { notify('error', error.message || '상세 조회 중 오류가 발생했습니다.'); }
    };
    document.querySelector('#daily-income-table tbody')?.addEventListener('dblclick', event => openDetail(table.row(event.target.closest('tr')).data()?.id));
    document.addEventListener('click', event => { const button = event.target.closest('[data-detail]'); if (button) openDetail(table.row(button.closest('tr')).data()?.id); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        try {
            applyFormSettings();
            if (!form.reportValidity()) return;
            await calculateDocument();
            const response = await request(API.SAVE, { method: 'POST', body: JSON.stringify({
                id: form.elements.id.value, request_key: token(), income_year_month: yearMonthValue.value, withholding_date: withholdingDateInput.value,
                document_title: form.elements.document_title.value,
                description: form.elements.description.value, memo: form.elements.memo.value, groups: payloadGroups(),
            }) });
            notify('success', response.message || '저장했습니다.'); modal.hide(); table.ajax.reload(null, false);
        } catch (error) { notify('error', error.message || '저장 중 오류가 발생했습니다.'); }
    });
    document.getElementById('dailyIncomeSubmit').addEventListener('click', async () => {
        const id = String(form.elements.id.value || '').trim();
        if (!id) { notify('error', '먼저 저장해 주세요.'); return; }
        try {
            const response = await request(`${API.PREFLIGHT}?id=${encodeURIComponent(id)}`);
            const preflight = response.data || {};
            if (!preflight.can_submit) {
                const messages = (preflight.blocking_errors || []).map(error => error.message).filter(Boolean);
                notify('error', messages[0] || '상신 사전검증을 통과하지 못했습니다.');
                return;
            }
            const submitted = await request(API.SUBMIT, { method: 'POST', body: JSON.stringify({ id }) });
            notify('success', submitted.message || '결재를 요청했습니다.');
            modal.hide();
            table.ajax.reload(null, false);
        } catch (error) { notify('error', error.message || '상신 사전검증 중 오류가 발생했습니다.'); }
    });
    document.getElementById('dailyIncomeWithdraw').addEventListener('click', async () => {
        if (!currentApprovalRequestId) { notify('error', '회수할 결재요청이 없습니다.'); return; }
        try {
            const response = await request(API.WITHDRAW, { method: 'POST', body: JSON.stringify({ request_id: currentApprovalRequestId }) });
            notify('success', response.message || '기안을 회수했습니다.');
            modal.hide();
            table.ajax.reload(null, false);
        } catch (error) { notify('error', error.message || '기안 회수 중 오류가 발생했습니다.'); }
    });
    document.getElementById('dailyIncomeDelete').addEventListener('click', async () => {
        const id = form.elements.id.value;
        if (!id || !await confirmDialog({ title: '일용근로소득 삭제', message: '이 문서를 삭제하시겠습니까?', confirmText: '삭제', confirmClass: 'btn-danger' })) return;
        try { await request(API.DELETE, { method: 'POST', body: JSON.stringify({ id }) }); notify('success', '삭제했습니다.'); modal.hide(); table.ajax.reload(null, false); }
        catch (error) { notify('error', error.message || '삭제 중 오류가 발생했습니다.'); }
    });
    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns['daily-employment-income'] = row => `<td>${escapeHtml(row.income_year_month || '-')}</td><td>${escapeHtml(row.document_title || '-')}</td><td>${statusBadge(row.status_code)}</td><td>${escapeHtml(formatDateDisplay(row.deleted_at) || '-')}</td><td></td>`;
    document.addEventListener('trash:changed', event => { if (event.detail?.type === 'daily-employment-income') table.ajax.reload(null, false); });
    const linkedDocumentId = new URLSearchParams(window.location.search).get('document_id');
    if (linkedDocumentId) void openDetail(linkedDocumentId);
}
