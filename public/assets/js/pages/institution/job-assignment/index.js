import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

const API = Object.freeze({
    list: '/api/institution/human-resources/job-assignment/list',
    detail: '/api/institution/human-resources/job-assignment/detail',
    options: '/api/institution/human-resources/job-assignment/options',
    employeeSearch: '/api/settings/organization/employee/search-picker',
    projectSearch: '/api/settings/base-info/project/search-picker',
    history_save: '/api/institution/human-resources/job-assignment/history-save',
    project_save: '/api/institution/human-resources/job-assignment/project-save',
    end: '/api/institution/human-resources/job-assignment/end',
    correct: '/api/institution/human-resources/job-assignment/correct',
});
const bootstrapData = JSON.parse(document.getElementById('jobAssignmentOptions')?.textContent || '{}');
const capabilities = bootstrapData.capabilities || {};
const form = document.getElementById('jobAssignmentForm');
const formModalElement = document.getElementById('jobAssignmentFormModal');
const formModal = window.bootstrap?.Modal.getOrCreateInstance(formModalElement);
const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' })[char]);
const labels = new Map();
const notify = (type, message) => window.AppCore?.notify?.(type, message);
let currentDetail = null;
let table;
let datePicker = null;
let datePickerInput = null;
let modalOptions = null;
let modalOptionsPromise = null;

function badge(value, label = '') { const tone = { ACTIVE:'success', PLANNED:'primary', ENDED:'secondary', CANCELLED:'dark', ON_LEAVE:'warning', RETIRED:'secondary', PENDING_HIRE:'info' }[value] || 'secondary'; return `<span class="badge text-bg-${tone}">${escapeHtml(label || labels.get(String(value)) || value || '-')}</span>`; }
function reload() { table.ajax.reload(null, false); }

const buttons = [];
if (capabilities.history_save) buttons.push({ text:'과거 직무 이력',className:'btn btn-outline-primary btn-sm',action:()=>openForm('history_save').catch(error=>notify('error',error.message)) });
if (capabilities.project_save) buttons.push({ text:'프로젝트 배치',className:'btn btn-primary btn-sm',action:()=>openForm('project_save').catch(error=>notify('error',error.message)) });
buttons.push({ text:'인사발령 등록',className:'btn btn-warning btn-sm',action:()=>{ location.href='/institution/human-resources/personnel-actions'; } });

table = await createDataTable({ tableSelector:'#jobAssignmentTable', api:API.list, serverSide:true, searching:false, selectable:false, showSelectionMoveButtons:false, deleteButton:false, pageLength:50, defaultOrder:[[0,'asc']], redrawAfterInitialVisibility:false,
    tableSettings:{ enabled:true,pageKey:'institution.human_resources.job_assignments',tableKey:'job-assignment-main',storageKey:'datatable.settings.institution.job-assignment.main.v1',tableLabel:'직무·배치관리',metaDomain:'job-assignment' },
    buttons,
    columns:[{data:'sort_no',title:'순번'},{data:'username',title:'아이디'},{data:'employee_name',title:'직원명'},{data:'employment_status',title:'재직상태',render:(value,_type,row)=>badge(value,row.employment_status_name)},{data:'department_name',title:'부서',defaultContent:'-'},{data:'position_name',title:'직위·직책',defaultContent:'-'},{data:'job_name',title:'직무',defaultContent:'-'},{data:'primary_project_name',title:'주 프로젝트',defaultContent:'-'},{data:'other_project_summary',title:'기타 프로젝트',defaultContent:'-'},{data:'workplace_name',title:'근무지',defaultContent:'-'},{data:'assignment_start_date',title:'배치 시작일',defaultContent:'-'},{data:'assignment_end_date',title:'배치 종료일',defaultContent:'-'},{data:'assignment_status',title:'배치상태',render:(value,_type,row)=>badge(value,row.assignment_status_name)},{data:'updated_at',title:'수정일시',defaultContent:'-'},{data:null,title:'관리',orderable:false,render:row=>`<button class="btn btn-outline-primary btn-sm" data-detail="${escapeHtml(row.employee_id)}">상세</button>`}]
});
SearchForm({ table, apiList:API.list, tableId:'jobAssignment', defaultSearchField:'keyword', dateOptions:[{value:'as_of_date',label:'기준일'}], normalizeFilters:filters=>filters.map(filter=>filter.field==='as_of_date'&&typeof filter.value==='object'?{field:'as_of_date',value:filter.value.end||filter.value.start}:filter) });
bindSearchPickers();

document.querySelector('#jobAssignmentTable tbody')?.addEventListener('click', event => { const button=event.target.closest('[data-detail]'); if(button) openDetail(button.dataset.detail).catch(error=>notify('error',error.message)); });
document.querySelector('#jobAssignmentTable tbody')?.addEventListener('dblclick', event => {
    if (event.target.closest('a, button, input, select, textarea')) return;
    const row = table.row(event.target.closest('tr')).data();
    if (row?.employee_id) openDetail(row.employee_id).catch(error=>notify('error',error.message));
});
document.querySelectorAll('[data-assignment-open]').forEach(button => button.addEventListener('click', () => openForm(button.dataset.assignmentOpen)));
form?.addEventListener('submit', submitForm);
form?.querySelectorAll('.date-picker-trigger').forEach(button => button.addEventListener('click', () => openDatePicker(button.parentElement.querySelector('input'))));
document.getElementById('jobAssignmentTabContent')?.addEventListener('click', event => {
    const button = event.target.closest('[data-assignment-action]');
    if (!button || !currentDetail) return;
    const domain = button.dataset.domain;
    const rows = domain === 'JOB' ? currentDetail.job_assignments : currentDetail.project_assignments;
    const row = rows.find(item => item.id === button.dataset.assignmentId);
    if (row) openForm(button.dataset.assignmentAction, { ...row, assignment_domain:domain, employee_id:currentDetail.id, employee_name:currentDetail.employee_name }).catch(error=>notify('error',error.message));
});

function setSelect(name, rows, placeholder) {
    const select = form.elements[name];
    if (!select) return;
    select.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>` + rows.map(row=>`<option value="${escapeHtml(row.value)}">${escapeHtml(row.label)}</option>`).join('');
}
async function ensureModalOptions() {
    if (modalOptions) return modalOptions;
    if (!modalOptionsPromise) modalOptionsPromise = fetch(API.options, { credentials:'same-origin' }).then(async response => {
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || '입력 옵션을 불러오지 못했습니다.');
        modalOptions = payload.data || {};
        [...(modalOptions.employment_statuses || []), ...(modalOptions.assignment_statuses || []), ...(modalOptions.workplace_types || [])].forEach(row => labels.set(String(row.value), row.label));
        setSelect('job_id', modalOptions.jobs || [], '직무 선택');
        return modalOptions;
    }).catch(error => { modalOptionsPromise = null; throw error; });
    return modalOptionsPromise;
}

function restoreAjaxSelection(select, value, text) {
    if (!select || !value) return;
    select.appendChild(new Option(text || value, value, true, true));
    window.jQuery(select).trigger('change');
}

function resetAjaxSelection(select) {
    if (!select) return;
    select.replaceChildren(new Option('', '', true, true));
    window.jQuery(select).trigger('change');
}

AdminPicker.select2Ajax(form.elements.employee_id, { width:'100%', dropdownParent:window.jQuery(formModalElement), url:API.employeeSearch, allowClear:false, minimumInputLength:0 });
AdminPicker.select2Ajax(form.elements.project_id, { width:'100%', dropdownParent:window.jQuery(formModalElement), url:API.projectSearch, allowClear:false, minimumInputLength:0 });

async function openForm(operation, row = {}) {
    const options = await ensureModalOptions();
    form.reset();
    resetAjaxSelection(form.elements.employee_id);
    resetAjaxSelection(form.elements.project_id);
    ['employee_id','job_id','project_id','assignment_role','start_date','end_date'].forEach(name => { if (form.elements[name]) form.elements[name].disabled = false; });
    form.elements.operation.value = operation;
    form.elements.request_key.value = crypto.randomUUID();
    const config = operationConfig(operation, row.assignment_domain);
    document.getElementById('jobAssignmentFormTitle').textContent = config.title;
    document.getElementById('jobAssignmentFormDescription').textContent = config.description;
    document.getElementById('jobAssignmentFormPolicy').textContent = config.policy;
    document.getElementById('jobAssignmentFormSubmit').textContent = config.submit;
    ['employee_id','job_id','project_id','assignment_role','start_date','end_date'].forEach(field => form.querySelector(`[data-field="${field}"]`)?.classList.toggle('d-none', !config.fields.includes(field)));
    form.elements.employee_id.disabled = Boolean(row.employee_id);
    form.elements.assignment_domain.value = row.assignment_domain || config.domain;
    form.elements.assignment_id.value = row.id || '';
    Object.entries(row).forEach(([key,value]) => { if (form.elements[key] && value !== null) form.elements[key].value = value; });
    restoreAjaxSelection(form.elements.employee_id, row.employee_id, row.employee_name);
    restoreAjaxSelection(form.elements.project_id, row.project_id, row.project_name);
    if (operation === 'end') ['project_id','start_date'].forEach(name => { form.elements[name].disabled = true; });
    setSelect('source_type', (options.assignment_sources || []).filter(item=>config.sources.includes(item.value)), '출처 선택');
    form.querySelector('[data-required-end]')?.classList.toggle('d-none', !config.endRequired);
    formModal?.show();
}

function searchItems(field, options) {
    if (field === 'employment_status') return options.employment_statuses || [];
    if (field === 'department_id') return options.departments || [];
    if (field === 'position_id') return options.positions || [];
    if (field === 'job_id') return options.jobs || [];
    if (field === 'workplace_type_code') return options.workplace_types || [];
    if (field === 'assignment_status') return options.assignment_statuses || [];
    if (field === 'current_only' || field === 'include_ended') return [{ value:'1',label:'예' },{ value:'0',label:'아니오' }];
    return [];
}

async function configureSearchRow(row) {
    const fieldSelect=row.querySelector('select[name="searchField[]"]');const valueInput=row.querySelector('input[name="searchValue[]"]');if(!fieldSelect||!valueInput)return;
    const field=fieldSelect.value;const pickerFields=['employee_id','employment_status','department_id','position_id','job_id','project_id','workplace_type_code','assignment_status','current_only','include_ended'];const existing=row.querySelector('.job-assignment-search-picker');
    if(row.dataset.assignmentPickerField===field&&existing)return;
    if(existing){AdminPicker.destroySelect2?.(existing);existing.remove();}delete row.dataset.assignmentPickerField;valueInput.type='text';valueInput.classList.remove('d-none');
    if(!pickerFields.includes(field))return;
    const picker=document.createElement('select');picker.className='form-select form-select-sm job-assignment-search-picker';picker.innerHTML='<option value="">선택</option>';valueInput.insertAdjacentElement('afterend',picker);valueInput.type='hidden';valueInput.classList.add('d-none');
    if(field==='employee_id')AdminPicker.select2Ajax(picker,{width:'100%',url:API.employeeSearch,allowClear:true,minimumInputLength:0});
    else if(field==='project_id')AdminPicker.select2Ajax(picker,{width:'100%',url:API.projectSearch,allowClear:true,minimumInputLength:0});
    else {const options=await ensureModalOptions();searchItems(field,options).forEach(item=>picker.appendChild(new Option(item.label,item.value)));AdminPicker.select2(picker,{width:'100%',allowClear:true});}
    row.dataset.assignmentPickerField=field;window.jQuery(picker).on('change',()=>{valueInput.value=picker.value||'';});
}

function bindSearchPickers(){const host=document.getElementById('jobAssignmentSearchConditions');const configureAll=()=>host?.querySelectorAll('.search-condition').forEach(row=>configureSearchRow(row).catch(error=>notify('error',error.message)));host?.addEventListener('change',event=>{if(event.target.matches('select[name="searchField[]"]'))configureSearchRow(event.target.closest('.search-condition')).catch(error=>notify('error',error.message));});if(host)new MutationObserver(configureAll).observe(host,{childList:true});configureAll();}

function operationConfig(operation, domain = '') {
    if (operation === 'history_save') return { title:'과거 직무 이력 등록',description:'인사발령 도입 이전의 종료된 과거 직무만 등록합니다.',policy:'현재 직무와 직원 마스터는 변경되지 않습니다.',submit:'이력 등록',domain:'JOB',fields:['employee_id','job_id','start_date','end_date'],sources:['INITIAL_MIGRATION','PRE_PERSONNEL_ACTION_HISTORY'],endRequired:true };
    if (operation === 'project_save') return { title:'프로젝트 배치 등록',description:'비주요 프로젝트 배치만 직접 등록합니다.',policy:'주 프로젝트, 휴직 기간, 입·퇴사일 및 프로젝트 기간 밖 배치는 등록할 수 없습니다.',submit:'배치 등록',domain:'PROJECT',fields:['employee_id','job_id','project_id','assignment_role','start_date','end_date'],sources:['INITIAL_MIGRATION','DIRECT_PROJECT_ASSIGNMENT','TEMPORARY_PROJECT_ASSIGNMENT','CONCURRENT_PROJECT_ASSIGNMENT'],endRequired:false };
    if (operation === 'end') return { title:'프로젝트 배치 종료',description:'직접 등록된 비주요 프로젝트 배치를 종료합니다.',policy:'인사발령으로 생성된 배치와 주 프로젝트 배치는 직접 종료할 수 없습니다.',submit:'배치 종료',domain:'PROJECT',fields:['employee_id','project_id','start_date','end_date'],sources:['DIRECT_PROJECT_ASSIGNMENT','TEMPORARY_PROJECT_ASSIGNMENT','CONCURRENT_PROJECT_ASSIGNMENT','ADMIN_CORRECTION','DATA_CONSISTENCY_REPAIR'],endRequired:true };
    const isJob = domain === 'JOB';
    return { title:'배치 관리자 정정',description:'직접 등록된 이력의 잘못된 값을 정정합니다.',policy:'인사발령 생성 이력은 정정할 수 없으며 변경 전후와 사유가 감사 증적으로 보존됩니다.',submit:'정정 저장',domain,fields:isJob?['employee_id','job_id','start_date','end_date']:['employee_id','job_id','project_id','assignment_role','start_date','end_date'],sources:['ADMIN_CORRECTION','DATA_CONSISTENCY_REPAIR'],endRequired:isJob };
}

async function submitForm(event) {
    event.preventDefault();
    const submit = document.getElementById('jobAssignmentFormSubmit');
    const data = Object.fromEntries(new FormData(form).entries());
    if (form.elements.employee_id.disabled) data.employee_id = form.elements.employee_id.value;
    const operation = data.operation;
    delete data.operation;
    submit.disabled = true;
    try {
        const response = await fetch(API[operation], { method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(data) });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || '직무·배치 처리 중 오류가 발생했습니다.');
        formModal?.hide();
        notify('success', payload.message || '처리되었습니다.');
        reload();
        if (currentDetail?.id) await openDetail(currentDetail.id);
    } catch (error) { notify('error', error.message); }
    finally { submit.disabled = false; }
}

function initDatePicker() {
    if (datePicker) return datePicker;
    const container = document.createElement('div');
    container.className = 'admin-picker-layer';
    document.body.appendChild(container);
    datePicker = AdminPicker.create({ type:'today',container });
    datePicker.subscribe((_,date)=>{ if(datePickerInput) datePickerInput.value=date;datePicker.close(); });
    return datePicker;
}
function openDatePicker(input) { const picker=initDatePicker();datePickerInput=input;picker.open({anchor:input}); }

function actionButtons(row, domain) {
    if (!row.direct_registration) return '-';
    const buttons = [];
    if (domain === 'PROJECT' && capabilities.end && ['ACTIVE','PLANNED'].includes(row.status_code)) buttons.push(`<button class="btn btn-outline-danger btn-sm" data-assignment-action="end" data-domain="PROJECT" data-assignment-id="${escapeHtml(row.id)}">종료</button>`);
    if (capabilities.correct) buttons.push(`<button class="btn btn-outline-secondary btn-sm" data-assignment-action="correct" data-domain="${domain}" data-assignment-id="${escapeHtml(row.id)}">정정</button>`);
    return buttons.join(' ') || '-';
}
function rowsTable(rows, columns, domain = '') { if(!rows?.length)return'<div class="text-muted py-4 text-center">이력이 없습니다.</div>';const action=domain?'<th>관리</th>':'';return`<div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr>${columns.map(column=>`<th>${escapeHtml(column[1])}</th>`).join('')}${action}</tr></thead><tbody>${rows.map(row=>`<tr>${columns.map(column=>`<td>${escapeHtml(column[2]?column[2](row[column[0]],row):(row[column[0]]??'-'))}</td>`).join('')}${domain?`<td>${actionButtons(row,domain)}</td>`:''}</tr>`).join('')}</tbody></table></div>`; }
const tabs = [
    ['department_assignments','부서',[['dept_name','부서'],['effective_from','시작일'],['effective_to','종료일'],['action_no','발령번호'],['action_name','근거']]],
    ['position_assignments','직위·직책',[['position_name','직위·직책'],['effective_from','시작일'],['effective_to','종료일'],['action_no','발령번호'],['action_name','근거']]],
    ['job_assignments','직무',[['job_name','직무'],['start_date','시작일'],['end_date','종료일'],['status_code','상태',v=>labels.get(v)||v],['action_no','발령번호']],'JOB'],
    ['project_assignments','프로젝트',[['project_name','프로젝트'],['assignment_role','역할'],['start_date','시작일'],['end_date','종료일'],['is_primary','주배치',v=>Number(v)===1?'예':'아니오'],['status_code','상태',v=>labels.get(v)||v],['action_no','발령번호']],'PROJECT'],
    ['workplace_assignments','근무지',[['workplace_name','근무지'],['workplace_type_code','유형',v=>labels.get(v)||v],['start_date','시작일'],['end_date','종료일'],['status_code','상태',v=>labels.get(v)||v],['action_no','발령번호']]],
    ['employment_status_histories','재직상태',[['status_code','상태',v=>labels.get(v)||v],['effective_date','시작일'],['ended_date','종료일'],['reason','변경사유'],['action_no','발령번호']]],
    ['personnel_actions','인사발령',[['action_no','발령번호'],['action_name','발령명'],['action_type_code','유형'],['issued_date','발령일'],['action_date','효력일'],['business_status','상태'],['application_status','적용상태'],['applied_by_name','적용자']]],
];
async function openDetail(employeeId) { const response=await fetch(`${API.detail}?employee_id=${encodeURIComponent(employeeId)}`,{credentials:'same-origin'});const payload=await response.json();if(!response.ok||!payload.success)throw new Error(payload.message||'상세 조회 중 오류가 발생했습니다.');const row=payload.data;currentDetail=row;document.getElementById('jobAssignmentEmployeeSummary').textContent=`${row.employee_name} · ${row.username}`;document.getElementById('jobAssignmentCurrentSummary').innerHTML=`<strong>현재 요약</strong> · ${escapeHtml(labels.get(row.employment_status)||row.employment_status)} · 부서 ${escapeHtml(row.department_name||'-')} · 직위·직책 ${escapeHtml(row.position_name||'-')} · 직무 ${escapeHtml(row.job_name||'-')}`;document.getElementById('jobAssignmentTabs').innerHTML=tabs.map((tab,index)=>`<li class="nav-item"><button class="nav-link ${index===0?'active':''}" data-bs-toggle="tab" data-bs-target="#job-assignment-tab-${index}">${tab[1]} <span class="badge text-bg-light">${(row[tab[0]]||[]).length}</span></button></li>`).join('');document.getElementById('jobAssignmentTabContent').innerHTML=tabs.map((tab,index)=>`<div class="tab-pane fade ${index===0?'show active':''}" id="job-assignment-tab-${index}">${rowsTable(row[tab[0]],tab[2],tab[3]||'')}</div>`).join('');window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('jobAssignmentDetailModal')).show(); }
