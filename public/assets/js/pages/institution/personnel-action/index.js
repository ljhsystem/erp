import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { hideDeleteProgress, runDeleteProgress, updateDeleteProgress } from '/public/assets/js/common/delete-progress.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { actorColumn } from '/public/assets/js/common/actor.js';
import { approvalStatusBadge } from '/public/assets/js/common/approval-status.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { notify } from '/public/assets/js/common/notification.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { getCodeOptions } from '/public/assets/js/pages/main/settings/system/code-select.js';
import { getCachedDataTableMetaColumns, readDataTableSettingsState, resolveDataTableColumnDisplayName, resolveDataTableColumnRequirementPolicy } from '/public/assets/js/common/datatable/dataTableSettings.js';
import '/public/assets/js/components/trash-manager.js';

const API = Object.freeze({
    list: '/api/institution/human-resources/personnel-action/list', detail: '/api/institution/human-resources/personnel-action/detail', options: '/api/institution/human-resources/personnel-action/options',
    save: '/api/institution/human-resources/personnel-action/save', reorder: '/api/institution/human-resources/personnel-action/reorder', submit: '/api/institution/human-resources/personnel-action/submit', withdraw: '/api/institution/human-resources/personnel-action/withdraw',
    apply: '/api/institution/human-resources/personnel-action/apply', delete: '/api/institution/human-resources/personnel-action/delete', trash: '/api/institution/human-resources/personnel-action/trash', restore: '/api/institution/human-resources/personnel-action/restore',
    employeeSearch: '/api/settings/organization/employee/search-picker', employeeDetail: '/api/settings/organization/employee/detail', projectSearch: '/api/settings/base-info/project/search-picker',
});
const PERSONNEL_TABLE_SETTINGS_KEY = 'datatable.settings.institution.personnel-action.main.v1';
const PERSONNEL_TABLE_SETTINGS_OPTIONS = Object.freeze({ pageKey: 'institution.human_resources.personnel_actions', metaDomain: 'personnel-action' });
const form = document.getElementById('personnelActionForm');
const modalElement = document.getElementById('personnelActionModal');
const modal = window.bootstrap?.Modal.getOrCreateInstance(modalElement);
const targetsHost = document.getElementById('personnelTargets');
const targetTemplate = document.getElementById('personnelTargetTemplate');
const changeTemplate = document.getElementById('personnelChangeTemplate');
const options = { departments: [], positions: [], jobs: [], action_types: [], action_statuses: [], employment_statuses: [], leave_types: [], workplace_types: [], change_policy: { commands: [], actions: {} } };
const employees = new Map();
const labels = new Map();
let modalOptionsPromise;
let current = { business_status: 'DRAFT' };
let table;
let datePicker;
let activeDateInput;
let previousActionType = '';

function formatPickerDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function normalizeDateInput(input) {
    const digits = input.value.replace(/\D/g, '').slice(0, 8);
    if (digits.length <= 4) input.value = digits;
    else if (digits.length <= 6) input.value = `${digits.slice(0, 4)}-${digits.slice(4)}`;
    else input.value = `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
}

function initDatePicker() {
    const container = document.getElementById('personnel-action-date-picker');
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
    if (!picker || input.disabled || input.readOnly) return;
    activeDateInput = input;
    if (typeof picker.clearDate === 'function') picker.clearDate();
    if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
        const [year, month, day] = input.value.split('-').map(Number);
        picker.setDate(new Date(year, month - 1, day));
    }
    picker.open({ anchor: input });
}

function bindDateInputs(root = form) {
    root.querySelectorAll('[data-personnel-date]').forEach(input => {
        if (input.dataset.personnelDateBound === '1') return;
        input.dataset.personnelDateBound = '1';
        input.addEventListener('input', () => normalizeDateInput(input));
        input.addEventListener('blur', () => normalizeDateInput(input));
        input.addEventListener('click', () => openDatePicker(input));
        input.closest('.date-input')?.querySelector('.date-icon')?.addEventListener('click', event => {
            event.preventDefault();
            openDatePicker(input);
        });
    });
}

function setFormLabel(label, text, required) {
    if (!(label instanceof HTMLLabelElement) || !text) return;
    label.replaceChildren(document.createTextNode(`${text} `));
    const mark = document.createElement('span');
    mark.dataset.personnelRequirementMark = required ? 'required' : 'optional';
    mark.className = required ? 'text-danger' : 'text-primary';
    mark.textContent = '*';
    mark.title = required ? '필수 입력' : '선택 입력';
    label.append(mark);
}
function applyPersonnelFormColumnMeta() {
    const metaColumns = getCachedDataTableMetaColumns({ metaDomain: 'personnel-action' });
    if (!metaColumns.length) return;
    const metaByKey = new Map(metaColumns.map(column => [String(column.key || ''), column]));
    const settingsState = readDataTableSettingsState(PERSONNEL_TABLE_SETTINGS_KEY, PERSONNEL_TABLE_SETTINGS_OPTIONS);
    const aliases = { created_by_name: 'created_by', updated_by_name: 'updated_by', deleted_by_name: 'deleted_by' };
    form.querySelectorAll('[name]').forEach(control => {
        const key = aliases[control.name] || control.name;
        const column = metaByKey.get(String(key || '').trim());
        const label = control.closest('[class*="col-"]')?.querySelector(':scope > label.form-label');
        if (!column || !label) return;
        const displayName = resolveDataTableColumnDisplayName(column, settingsState, column.label || column.key);
        const policy = resolveDataTableColumnRequirementPolicy(column, settingsState);
        const required = settingsState ? policy === 'required' : Boolean(column.required);
        if (!control.readOnly && !control.disabled) control.required = required;
        control.dataset.columnMetaRequired = required ? '1' : '0';
        setFormLabel(label, displayName, required);
    });
}

const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]);
function bindSelectChange(select, namespace, handler) {
    window.jQuery(select).off(`change.${namespace}`).on(`change.${namespace}`, handler);
}
const optionMap = items => new Map((items || []).map(item => [String(item.id ?? item.value), item]));
const departments = () => optionMap(options.departments);
const positions = () => optionMap(options.positions);
const jobs = () => optionMap(options.jobs);
async function request(url, init = {}) { const response = await fetch(url, { credentials: 'same-origin', headers: init.body ? { 'Content-Type': 'application/json' } : {}, ...init }); const payload = await response.json().catch(() => ({ success: false })); if (!response.ok || !payload.success) throw new Error(payload.message || '처리 중 오류가 발생했습니다.'); return payload; }
const normalizeCodes = rows => rows.map(row => ({ value: row.code, label: row.code_name || row.code }));

async function ensureModalOptions() {
    if (!modalOptionsPromise) modalOptionsPromise = Promise.all([
        request(API.options), getCodeOptions('PERSONNEL_ACTION_TYPE'), getCodeOptions('PERSONNEL_ACTION_STATUS'), getCodeOptions('EMPLOYMENT_STATUS'), getCodeOptions('EMPLOYEE_LEAVE_TYPE'), getCodeOptions('EMPLOYEE_WORKPLACE_TYPE'),
    ]).then(([payload, actionTypes, actionStatuses, employmentStatuses, leaveTypes, workplaceTypes]) => {
        const payloadData = payload.data || {};
        const supportedActionTypes = new Set(Object.keys(payloadData.change_policy?.actions || {}));
        Object.assign(options, payloadData, { action_types: normalizeCodes(actionTypes).filter(item => supportedActionTypes.has(item.value)), action_statuses: normalizeCodes(actionStatuses), employment_statuses: normalizeCodes(employmentStatuses), leave_types: normalizeCodes(leaveTypes), workplace_types: normalizeCodes(workplaceTypes) });
        [...options.action_types, ...options.action_statuses, ...options.employment_statuses, ...options.leave_types, ...options.workplace_types].forEach(row => labels.set(String(row.value), row.label));
        fill(form.elements.action_type_code, options.action_types, '발령유형 선택');
        AdminPicker.select2(form.elements.action_type_code, { width: '100%', dropdownParent: window.jQuery(modalElement), allowClear: false });
        return options;
    });
    return modalOptionsPromise;
}
function fill(select, items, placeholder = '선택') { select.innerHTML = `<option value="">${placeholder}</option>` + (items || []).map(item => `<option value="${escapeHtml(item.id ?? item.value)}">${escapeHtml(item.label)}</option>`).join(''); }
function commandMetadata(type) { return options.change_policy.commands.find(command => command.value === type); }
function actionPolicy() { return options.change_policy.actions[form.elements.action_type_code.value] || null; }
function requiredChangeData(type) { return { change_type_code: type, ...(actionPolicy()?.required_values?.[type] || {}) }; }
function fillChangeTypeSelect(row, value = '') {
    const select = row.querySelector('.change-type');
    AdminPicker.destroySelect2(select);
    const policy = actionPolicy();
    const required = policy?.required_all || [];
    const allowed = new Set(policy?.allowed || []);
    if (value && !allowed.has(value)) throw new Error('저장된 변경구분이 현재 발령유형 정책과 일치하지 않습니다.');
    const selectedByOtherRows = new Set([...row.closest('.personnel-target')?.querySelectorAll('.personnel-change') || []].filter(candidate => candidate !== row).map(candidate => candidate.querySelector('.change-type').value).filter(Boolean));
    if (value && selectedByOtherRows.has(value)) throw new Error('저장된 변경구분이 대상자 안에서 중복되었습니다.');
    const commands = options.change_policy.commands.filter(command => allowed.has(command.value) && !selectedByOtherRows.has(command.value) && (!required.includes(command.value) || command.value === value));
    select.innerHTML = `<option value="">${policy ? '선택' : '발령유형을 먼저 선택해 주세요.'}</option>` + commands.map(command => `<option value="${escapeHtml(command.value)}">${escapeHtml(command.label)}${required.includes(command.value) ? ' (필수)' : ''}</option>`).join('');
    select.value = value;
    const isRequired = required.includes(value);
    row.dataset.policyRequired = isRequired ? '1' : '0';
    row.querySelector('.change-remove').classList.toggle('d-none', isRequired);
    select.disabled = (current.business_status || 'DRAFT') !== 'DRAFT';
    AdminPicker.select2(select, { width: '100%', dropdownParent: window.jQuery(modalElement), allowClear: false });
}
function refreshChangeTypeOptions(target) { target.querySelectorAll('.personnel-change').forEach(row => fillChangeTypeSelect(row, row.querySelector('.change-type').value)); }
function ensureRequiredChanges(target) {
    const required = actionPolicy()?.required_all || [];
    required.forEach(type => {
        const existing = [...target.querySelectorAll('.personnel-change')].find(row => row.querySelector('.change-type').value === type);
        if (existing) { fillChangeTypeSelect(existing, type); return; }
        const blank = [...target.querySelectorAll('.personnel-change')].find(row => !row.querySelector('.change-type').value);
        if (blank) setChangeData(blank, requiredChangeData(type));
        else addChange(target, requiredChangeData(type));
    });
}
function synchronizeChangePolicy({ confirmRemoval = false } = {}) {
    const allowed = new Set(actionPolicy()?.allowed || []);
    const invalid = [...targetsHost.querySelectorAll('.personnel-change')].filter(row => {
        const value = row.querySelector('.change-type').value;
        return value && !allowed.has(value);
    });
    if (invalid.length && confirmRemoval && !window.confirm('새 발령유형에서 허용되지 않는 변경항목을 제거하시겠습니까?')) return false;
    invalid.forEach(row => row.remove());
    targetsHost.querySelectorAll('.personnel-target').forEach(target => {
        refreshChangeTypeOptions(target);
        ensureRequiredChanges(target);
        if (!target.querySelector('.personnel-change')) addChange(target);
    });
    return true;
}
function businessBadge(value, name = '') { const tones = { DRAFT: 'secondary', APPROVAL_PENDING: 'warning', APPROVED: 'primary', APPLIED: 'success', CANCELLED: 'dark' }; return `<span class="badge text-bg-${tones[value] || 'secondary'}">${escapeHtml(name || labels.get(String(value)) || value || '-')}</span>`; }
function employeeRecord(row = {}) { return { id: String(row.employee_id ?? row.id ?? ''), employee_name: row.employee_name || row.text || '', employment_status: row.employment_status || '', department_id: row.department_id || '', position_id: row.position_id || '', job_id: row.job_id || '', doc_hire_date: row.doc_hire_date || '', real_hire_date: row.real_hire_date || '', doc_retire_date: row.doc_retire_date || '', real_retire_date: row.real_retire_date || '' }; }
async function loadEmployee(id) { const key = String(id || ''); if (!key) return null; if (employees.has(key) && employees.get(key).employment_status) return employees.get(key); const payload = await request(`${API.employeeDetail}?id=${encodeURIComponent(key)}`); const employee = employeeRecord(payload.data || payload.employee || payload); employees.set(key, employee); return employee; }
function currentSummary(employee) { if (!employee) return '직원을 선택해 주세요.'; return `현재상태: ${labels.get(employee.employment_status) || employee.employment_status || '-'} · 부서: ${departments().get(String(employee.department_id))?.label || '-'} · 직위·직책: ${positions().get(String(employee.position_id))?.label || '-'} · 직무: ${jobs().get(String(employee.job_id))?.label || '-'}`; }
function field(label, control, required = true) { return `<label class="form-label">${label} <span class="${required ? 'text-danger' : 'text-primary'}" title="${required ? '필수 입력' : '선택 입력'}">*</span></label>${control}`; }
function localSelect(name, items) { return `<select class="form-select form-select-sm change-input" data-name="${name}"><option value="">선택</option>${(items || []).map(item => `<option value="${escapeHtml(item.id ?? item.value)}">${escapeHtml(item.label)}</option>`).join('')}</select>`; }
function projectSelect(name) { return `<select class="form-select form-select-sm change-input project-picker" data-name="${name}"><option value="">프로젝트 검색</option></select>`; }
function input(name, kind = 'text') {
    if (kind === 'date') return `<span class="date-input"><input type="text" class="form-control form-control-sm admin-date change-input" data-name="${name}" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" autocomplete="off" data-personnel-date><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span>`;
    return `<input type="${kind}" class="form-control form-control-sm change-input" data-name="${name}">`;
}
function valueEditor(type) {
    const inputType = commandMetadata(type)?.input_type;
    if (inputType === 'employment_status') return field('변경 후 상태', localSelect('after_employment_status', options.employment_statuses));
    if (inputType === 'department') return field('변경 후 부서', localSelect('after_department_id', options.departments));
    if (inputType === 'position') return field('변경 후 직위·직책', localSelect('after_position_id', options.positions));
    if (inputType === 'job') return field('변경 후 직무', localSelect('after_job_id', options.jobs));
    if (inputType === 'leave') return field('휴직유형', localSelect('leave_type_code', options.leave_types)) + field('휴직 시작일', input('leave_start_date', 'date')) + field('예정 종료일', input('leave_planned_end_date', 'date'), false);
    if (inputType === 'return_from_leave') return field('휴직 이력 ID', input('leave_period_id')) + field('복직일', input('leave_actual_end_date', 'date'));
    if (inputType === 'employment_date') return field('일자구분', '<select class="form-select form-select-sm change-input" data-name="date_kind"><option value="ACTUAL">실제</option><option value="DOCUMENT">문서상</option></select>') + field('변경 후 일자', input('after_date', 'date'));
    if (inputType === 'project_assignment') return field('프로젝트', projectSelect('project_id')) + field('배치 시작일', input('assignment_start_date', 'date')) + field('배치 역할', input('assignment_role'), false) + '<label class="form-check mt-1"><input type="checkbox" class="form-check-input change-input" data-name="is_primary_assignment"> 주배치 <span class="text-primary" title="선택 입력">*</span></label>';
    if (inputType === 'project_release') return field('프로젝트 배치 ID', input('project_assignment_id')) + field('해제일', input('assignment_end_date', 'date'));
    if (inputType === 'workplace') return field('근무지유형', localSelect('workplace_type_code', options.workplace_types)) + field('현장 프로젝트', projectSelect('workplace_project_id'), false) + field('근무지명', input('workplace_name_snapshot'), false);
    return '<span class="text-muted small">변경구분을 선택해 주세요.</span>';
}
function beforeValue(employee, type) { if (!employee) return ''; const source = commandMetadata(type)?.before_source; return ({ employment_status: labels.get(employee.employment_status) || employee.employment_status, department: departments().get(String(employee.department_id))?.label, position: positions().get(String(employee.position_id))?.label, job: jobs().get(String(employee.job_id))?.label, hire_date: employee.real_hire_date, retire_date: employee.real_retire_date }[source] || '현재 이력에서 확인'); }
function initializeChangeValuePickers(row) {
    const pickerOptions = { width: '100%', dropdownParent: window.jQuery(modalElement), minimumInputLength: 0 };
    row.querySelectorAll('.change-input').forEach(select => {
        if (!(select instanceof HTMLSelectElement)) return;
        if (select.matches('.project-picker')) AdminPicker.select2Ajax(select, { ...pickerOptions, url: API.projectSearch, allowClear: true });
        else AdminPicker.select2(select, { ...pickerOptions, allowClear: !select.required });
    });
}
function renderChange(row, data = {}) { const target = row.closest('.personnel-target'); const employee = employees.get(target?.querySelector('.target-employee')?.value || ''); const type = row.querySelector('.change-type').value; row.querySelector('.before-label').value = data.before_display_snapshot || beforeValue(employee, type); row.querySelector('.change-value').innerHTML = valueEditor(type); bindDateInputs(row); }
function setChangeData(row, data) { const type = data.change_type_code || ''; fillChangeTypeSelect(row, type); renderChange(row, data); row.querySelectorAll('.change-input').forEach(control => { const value = data[control.dataset.name]; if (control.type === 'checkbox') control.checked = Number(value) === 1; else if (value !== null && value !== undefined && value !== '') { if (control.matches('.project-picker')) { const labelKey = control.dataset.name === 'workplace_project_id' ? 'workplace_project_name' : 'project_name'; control.appendChild(new Option(data[labelKey] || String(value), String(value), true, true)); } else control.value = value; } }); initializeChangeValuePickers(row); }
function addChange(target, data = {}) { const row = changeTemplate.content.firstElementChild.cloneNode(true); target.querySelector('.changes').appendChild(row); bindSelectChange(row.querySelector('.change-type'), 'personnelChangeType', () => { renderChange(row); initializeChangeValuePickers(row); refreshChangeTypeOptions(target); ensureRequiredChanges(target); }); row.querySelector('.change-remove').addEventListener('click', () => { row.remove(); refreshChangeTypeOptions(target); }); setChangeData(row, data); }
function addTarget(data = {}) {
    const target = targetTemplate.content.firstElementChild.cloneNode(true); targetsHost.appendChild(target); const employeeSelect = target.querySelector('.target-employee'); const existing = employeeRecord(data);
    if (existing.id) { employees.set(existing.id, existing); employeeSelect.appendChild(new Option(existing.employee_name || existing.id, existing.id, true, true)); }
    target.querySelector('.target-reason').value = data.individual_reason || '';
    const refresh = async () => { const employee = await loadEmployee(employeeSelect.value).catch(() => employees.get(employeeSelect.value)); target.querySelector('.current-info').textContent = currentSummary(employee); target.querySelectorAll('.personnel-change').forEach(row => renderChange(row)); };
    bindSelectChange(employeeSelect, 'personnelEmployee', refresh); AdminPicker.select2Ajax(employeeSelect, { width: '100%', dropdownParent: window.jQuery(modalElement), url: API.employeeSearch, allowClear: false, minimumInputLength: 0 });
    target.querySelector('.target-remove').addEventListener('click', () => target.remove()); target.querySelector('.change-add').addEventListener('click', () => addChange(target)); (data.changes || []).forEach(change => addChange(target, change)); if (!(data.changes || []).length) addChange(target); ensureRequiredChanges(target); refresh();
}
function resetForm() { current = { business_status: 'DRAFT' }; form.reset(); previousActionType = ''; window.jQuery?.(form.elements.action_type_code).val('').trigger('change.select2'); targetsHost.innerHTML = ''; form.elements.id.value = ''; form.elements.business_status.value = 'DRAFT'; form.elements.issued_date.value = new Date().toISOString().slice(0, 10); form.elements.action_date.value = form.elements.issued_date.value; addTarget(); applyState(); }
function setForm(action) { Object.entries(action).forEach(([key, value]) => { const control = form.elements.namedItem(key); if (control) control.value = value ?? ''; }); }
function applyState() { const status = current.business_status || 'DRAFT'; const editable = status === 'DRAFT'; form.querySelectorAll('input,select,textarea').forEach(control => { if (!control.readOnly && control.type !== 'hidden') control.disabled = !editable; }); targetsHost.querySelectorAll('.personnel-change').forEach(row => fillChangeTypeSelect(row, row.querySelector('.change-type').value)); document.getElementById('personnelActionStatus').innerHTML = businessBadge(status, current.business_status_name); document.getElementById('personnelActionSave').classList.toggle('d-none', !editable); document.getElementById('personnelActionSubmit').classList.toggle('d-none', !editable || !current.id); document.getElementById('personnelActionDelete').classList.toggle('d-none', !editable || !current.id); document.getElementById('personnelActionWithdraw').classList.toggle('d-none', status !== 'APPROVAL_PENDING'); document.getElementById('personnelActionApply').classList.toggle('d-none', status !== 'APPROVED' || current.action_date > new Date().toISOString().slice(0, 10)); document.getElementById('personnelTargetAdd').classList.toggle('d-none', !editable); }
function serializeChange(row) { const result = { change_type_code: row.querySelector('.change-type').value, before_display_snapshot: row.querySelector('.before-label').value }; row.querySelectorAll('.change-input').forEach(control => { result[control.dataset.name] = control.type === 'checkbox' ? (control.checked ? 1 : 0) : control.value; }); return result; }
function serialize() { const header = Object.fromEntries(new FormData(form).entries()); header.targets = [...targetsHost.querySelectorAll('.personnel-target')].map(target => ({ employee_id: target.querySelector('.target-employee').value, individual_reason: target.querySelector('.target-reason').value, changes: [...target.querySelectorAll('.personnel-change')].map(serializeChange) })); return header; }
async function openAction(id) { await ensureModalOptions(); const result = await request(`${API.detail}?id=${encodeURIComponent(id)}`); current = result.data.action; form.reset(); setForm(current); previousActionType = current.action_type_code || ''; window.jQuery?.(form.elements.action_type_code).val(previousActionType).trigger('change.select2'); targetsHost.innerHTML = ''; result.data.targets.forEach(addTarget); document.getElementById('personnelApprovalSteps').innerHTML = (result.data.approval_steps || []).map(step => `<span class="me-1">${escapeHtml(step.step_name)} ${approvalStatusBadge(step.status, escapeHtml)}</span>`).join(''); applyState(); modal.show(); }
async function command(url, body, confirmText, { softDelete = false } = {}) {
    if (confirmText && !window.confirm(confirmText)) return;
    const execute = async () => {
        const result = await request(url, { method: 'POST', body: JSON.stringify(body) });
        window.alert(result.message);
        modal.hide();
        await new Promise(resolve => table.ajax.reload(() => resolve(), false));
        if (softDelete) {
            document.dispatchEvent(new CustomEvent('datatable:soft-delete-completed', {
                detail: { table, ids: [body.id].filter(Boolean) },
            }));
        }
    };
    if (softDelete) {
        return runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '휴지통으로 이동 중', trashChanged: true }, execute);
    }
    return execute();
}

function filterItems(field) { if (field === 'action_type_code') return options.action_types; if (field === 'business_status') return options.action_statuses; if (field === 'department_id') return options.departments; if (field === 'position_id') return options.positions; if (field === 'job_id') return options.jobs; if (field === 'approval_status') return [{ value: 'pending', label: '결재 대기' }, { value: 'in_progress', label: '결재 진행' }, { value: 'approved', label: '승인 완료' }, { value: 'rejected', label: '반려' }, { value: 'withdrawn', label: '회수' }, { value: 'cancelled', label: '취소' }]; return []; }
async function configureSearchRow(row) {
    const fieldSelect = row.querySelector('select[name="searchField[]"]'); const valueInput = row.querySelector('input[name="searchValue[]"]'); if (!fieldSelect || !valueInput) return;
    const field = fieldSelect.value; const pickerFields = ['employee_id', 'department_id', 'position_id', 'job_id', 'action_type_code', 'business_status', 'approval_status']; const existing = row.querySelector('.personnel-search-picker');
    if (row.dataset.personnelPickerField === field && existing) return;
    if (existing) { AdminPicker.destroySelect2?.(existing); existing.remove(); }
    delete row.dataset.personnelPickerField; valueInput.type = 'text'; valueInput.classList.remove('d-none');
    if (!pickerFields.includes(field)) return;
    await ensureModalOptions(); valueInput.type = 'hidden'; valueInput.classList.add('d-none'); const picker = document.createElement('select'); picker.className = 'form-select form-select-sm personnel-search-picker'; picker.innerHTML = '<option value="">선택</option>'; valueInput.insertAdjacentElement('afterend', picker);
    if (field === 'employee_id') AdminPicker.select2Ajax(picker, { width: '100%', url: API.employeeSearch, allowClear: true, minimumInputLength: 0 });
    else { filterItems(field).forEach(item => picker.appendChild(new Option(item.label, item.id ?? item.value))); picker.value = valueInput.value; AdminPicker.select2(picker, { width: '100%', allowClear: true }); }
    row.dataset.personnelPickerField = field;
    window.jQuery(picker).on('change', () => { valueInput.value = picker.value || ''; });
}
function bindSearchPickers() { const host = document.getElementById('personnelActionSearchConditions'); const configureAll = () => host?.querySelectorAll('.search-condition').forEach(row => configureSearchRow(row)); host?.addEventListener('change', event => { if (event.target.matches('select[name="searchField[]"]')) configureSearchRow(event.target.closest('.search-condition')); }); if (host) new MutationObserver(configureAll).observe(host, { childList: true }); configureAll(); }

function reorderPermission(dataTable) {
    const info = dataTable.page.info();
    const order = dataTable.order();
    const orderColumn = Array.isArray(order) && order.length ? order[0] : null;
    const sortColumnIndex = orderColumn ? Number(orderColumn[0]) : -1;
    const sortDirection = orderColumn ? String(orderColumn[1] || '').toLowerCase() : '';
    const sortColumn = sortColumnIndex >= 0 ? dataTable.settings()[0]?.aoColumns?.[sortColumnIndex] : null;
    const fullList = Number(info?.pages || 0) <= 1 && Number(info?.recordsDisplay || 0) === Number(info?.recordsTotal || 0);
    if (!fullList || sortColumn?.mData !== 'sort_no' || sortDirection !== 'asc') {
        return '검색을 해제하고 순번 오름차순의 전체 목록에서만 순서를 변경할 수 있습니다.';
    }
    return true;
}

function bindSelectedDelete(dataTable) {
    const tableNode = document.querySelector('#personnelActionTable');
    tableNode?.addEventListener('datatable:delete-selected', async event => {
        const ids = Array.isArray(event.detail?.ids) ? event.detail.ids.filter(Boolean) : [];
        if (!ids.length || !window.confirm(`선택한 ${ids.length}건을 삭제하시겠습니까?`)) return;
        updateDeleteProgress({ total: ids.length, processed: 0, title: '소프트삭제 처리 중', step: '삭제 요청 준비 중' });
        try {
            for (const [index, id] of ids.entries()) {
                updateDeleteProgress({ total: ids.length, processed: index, title: '소프트삭제 처리 중', step: `${index + 1}번째 행 처리 중` });
                await request(API.delete, { method: 'POST', body: JSON.stringify({ id }) });
                updateDeleteProgress({ total: ids.length, processed: index + 1, title: '소프트삭제 처리 중', step: `${index + 1}건 처리 완료` });
            }
            event.detail?.selectedIds?.clear?.();
            notify('success', `${ids.length}건을 휴지통으로 이동했습니다.`);
            await new Promise(resolve => dataTable.ajax.reload(() => resolve(), false));
            tableNode.dispatchEvent(new CustomEvent('datatable:soft-delete-completed', {
                bubbles: true,
                detail: { table: dataTable, ids, selectedIds: event.detail?.selectedIds },
            }));
        } catch (error) {
            notify('error', error.message || '삭제 중 오류가 발생했습니다.');
            dataTable.ajax.reload(null, false);
        } finally {
            hideDeleteProgress();
        }
    });
}

async function createList() {
    table = await createDataTable({ tableSelector: '#personnelActionTable', api: API.list, selectable: true, showSelectionMoveButtons: true, serverSide: true, searching: true, pageLength: 50, defaultOrder: [[1, 'asc']], tableSettings: { enabled: true, pageKey: 'institution.human_resources.personnel_actions', tableKey: 'personnel-action-main', storageKey: PERSONNEL_TABLE_SETTINGS_KEY, tableLabel: '인사발령관리', metaDomain: 'personnel-action' }, buttons: [{ text: '휴지통', className: 'btn btn-danger btn-sm', action: () => window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('personnelActionTrashModal')).show() }, { text: '신규등록', className: 'btn btn-warning btn-sm', action: () => ensureModalOptions().then(() => { resetForm(); applyPersonnelFormColumnMeta(); modal.show(); }).catch(error => window.alert(error.message)) }], columns: [{ data: null, title: '<i class="bi bi-arrows-move"></i>', settingsKey: '__reorder', __dtColumnKind: 'virtual', className: 'reorder-handle no-sort no-colvis text-center', headerClassName: 'no-colvis text-center', orderable: false, searchable: false, defaultContent: '<i class="bi bi-list"></i>' }, { data: 'sort_no', title: '순번', className: 'text-center dt-sequence-column' }, { data: 'action_no', title: '발령번호' }, { data: 'employee_names', title: '직원명', defaultContent: '-' }, { data: 'action_name', title: '발령제목' }, { data: 'action_type_code', title: '발령유형', render: (_value, _type, row) => escapeHtml(row.action_type_name || row.action_type_code || '-') }, { data: 'issued_date', title: '발령일' }, { data: 'action_date', title: '효력일' }, { data: 'action_reason', title: '발령사유', defaultContent: '-' }, { data: 'change_summary', title: '변경내용', defaultContent: '-', render: (value, type, row) => type === 'display' ? escapeHtml(value || `${Number(row.change_count || 0)}건`) : (value || '') }, { data: 'business_status', title: '발령상태', render: (value, _type, row) => businessBadge(value, row.business_status_name) }, { data: 'approval_request_no', title: '결재요청 순번', defaultContent: '-' }, { data: 'approval_status', title: '결재상태', defaultContent: '-', render: value => value ? approvalStatusBadge(value, escapeHtml) : '-' }, { data: 'original_action_no', title: '원본 발령번호', defaultContent: '-' }, { data: 'correction_kind', title: '원본처리구분', defaultContent: '-' }, { data: 'approved_at', title: '최종 승인일시', defaultContent: '-' }, { data: 'applied_at', title: '적용일시', defaultContent: '-' }, { data: 'cancelled_at', title: '취소일시', defaultContent: '-' }, { data: 'cancelled_reason', title: '취소사유', defaultContent: '-' }, { data: 'note', title: '비고', defaultContent: '-' }, actorColumn('created_by', '작성자'), { data: 'created_at', title: '작성일시' }, actorColumn('updated_by', '수정자'), { data: 'updated_at', title: '수정일시', defaultContent: '-' }, actorColumn('deleted_by', '삭제자'), { data: 'deleted_at', title: '삭제일시', defaultContent: '-' }, { data: null, title: '관리', orderable: false, render: row => `<button type="button" class="btn btn-outline-primary btn-sm" data-open="${escapeHtml(row.id)}">상세</button>` }] });
    bindRowReorder(table, {
        api: API.reorder,
        canReorder: () => reorderPermission(table),
        onSuccess: () => { notify('success', '인사발령 순서가 저장되었습니다.'); table.ajax.reload(null, false); },
        onError: payload => { notify('error', payload?.message || '순서 저장 중 오류가 발생했습니다.'); table.ajax.reload(null, false); },
    });
    bindSelectedDelete(table);
    document.querySelector('#personnelActionTable tbody')?.addEventListener('dblclick', event => {
        const row = table.row(event.target.closest('tr')).data();
        if (row?.id) openAction(row.id).catch(error => window.alert(error.message));
    });
    document.querySelector('#personnelActionTable tbody')?.addEventListener('click', event => { const button = event.target.closest('[data-open]'); if (button) openAction(button.dataset.open).catch(error => window.alert(error.message)); }); SearchForm({ table, apiList: API.list, tableId: 'personnelAction', defaultSearchField: 'keyword', dateOptions: ['issued_date', 'action_date'] }); bindSearchPickers(); applyPersonnelFormColumnMeta();
}
document.getElementById('personnelTargetAdd').addEventListener('click', () => addTarget());
bindSelectChange(form.elements.action_type_code, 'personnelActionType', () => {
    const next = form.elements.action_type_code.value;
    if (!synchronizeChangePolicy({ confirmRemoval: Boolean(previousActionType && previousActionType !== next) })) {
        form.elements.action_type_code.value = previousActionType;
        window.jQuery?.(form.elements.action_type_code).trigger('change.select2');
        return;
    }
    previousActionType = next;
});
form.addEventListener('submit', event => { event.preventDefault(); if (!form.reportValidity()) return; request(API.save, { method: 'POST', body: JSON.stringify(serialize()) }).then(result => openAction(result.data.id)).catch(error => window.alert(error.message)); });
document.getElementById('personnelActionSubmit').addEventListener('click', () => command(API.submit, { id: current.id }, '결재를 요청하시겠습니까?').catch(error => window.alert(error.message)));
document.getElementById('personnelActionWithdraw').addEventListener('click', () => command(API.withdraw, { request_id: current.current_approval_request_id }, '기안을 회수하시겠습니까?').catch(error => window.alert(error.message)));
document.getElementById('personnelActionApply').addEventListener('click', () => command(API.apply, { id: current.id }, '현재 직원정보와 기간 이력에 발령을 적용하시겠습니까?').catch(error => window.alert(error.message)));
document.getElementById('personnelActionDelete').addEventListener('click', () => command(
    API.delete,
    { id: current.id },
    '휴지통으로 이동하시겠습니까?',
    { softDelete: true },
).catch(error => window.alert(error.message)));
window.TrashColumns = window.TrashColumns || {}; window.TrashColumns['personnel-action'] = row => `<td>${escapeHtml(row.action_no)}</td><td>${escapeHtml(row.action_name)}</td><td>${businessBadge(row.business_status, row.business_status_name)}</td><td>${escapeHtml(row.deleted_at || '-')}</td><td><button type="button" class="btn btn-outline-success btn-sm btn-restore" data-id="${escapeHtml(row.id)}">복원</button></td>`;
document.addEventListener('trash:changed', event => { if (event.detail?.type === 'personnel-action') table?.ajax.reload(null, false); });
document.addEventListener('datatable-settings:updated', event => { if (event.detail?.storageKey === PERSONNEL_TABLE_SETTINGS_KEY) applyPersonnelFormColumnMeta(); });
modalElement?.addEventListener('hidden.bs.modal', () => datePicker?.close?.());
bindDateInputs();
await createList();
