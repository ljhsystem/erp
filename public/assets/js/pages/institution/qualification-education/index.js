import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { actorColumn } from '/public/assets/js/common/actor.js';

const root = document.querySelector('.qualification-education-page');
if (root) {
    const boot = JSON.parse(root.dataset.bootstrap || '{}');
    const options = boot.options || {};
    const capability = boot.capabilities || {};
    const API = {
        qList: '/api/institution/human-resources/qualification-education/qualification/all-list',
        qDetail: '/api/institution/human-resources/qualification-education/qualification/detail',
        qSave: '/api/institution/human-resources/qualification-education/qualification/save',
        qVerify: '/api/institution/human-resources/qualification-education/qualification/verify',
        qRenew: '/api/institution/human-resources/qualification-education/qualification/renew',
        qDelete: '/api/institution/human-resources/qualification-education/qualification/delete',
        eList: '/api/institution/human-resources/qualification-education/education/all-list',
        eDetail: '/api/institution/human-resources/qualification-education/education/detail',
        eSave: '/api/institution/human-resources/qualification-education/education/save',
        eDelete: '/api/institution/human-resources/qualification-education/education/delete',
        courseSave: '/api/institution/human-resources/qualification-education/course/save',
        excel: '/api/institution/human-resources/qualification-education/excel'
    };
    let active = 'qualifications';
    const initialEmployeeId = new URLSearchParams(location.search).get('employee_id') || '';
    let table = null;
    const qModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('qeQualificationModal'));
    const eModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('qeEducationModal'));
    const courseModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('qeCourseModal'));
    const qForm = document.getElementById('qeQualificationForm');
    const eForm = document.getElementById('qeEducationForm');
    const courseForm = document.getElementById('qeCourseForm');
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const requestKey = () => crypto.randomUUID?.() || String(Date.now()) + '-' + Math.random().toString(16).slice(2);

    function fillSelect(form, name, rows, blank = '선택') {
        const element = form.elements[name];
        if (!element) return;
        element.innerHTML = '<option value="">' + blank + '</option>' + (rows || []).map(row => '<option value="' + escapeHtml(row.value) + '">' + escapeHtml(row.label) + '</option>').join('');
    }
    function initializeOptions() {
        [qForm, eForm].forEach(form => fillSelect(form, 'employee_id', options.employees));
        fillSelect(qForm, 'qualification_type_code', options.qualification_types);
        fillSelect(qForm, 'status_code', options.qualification_statuses);
        fillSelect(eForm, 'course_id', options.courses);
        fillSelect(eForm, 'attendance_status_code', options.attendance_statuses);
        fillSelect(eForm, 'completion_status_code', options.completion_statuses);
        fillSelect(courseForm, 'education_type_code', options.education_types);
    }
    function badge(value) {
        const color = value === 'EXPIRED' ? 'danger' : value === 'EXPIRING' ? 'warning' : ['ACTIVE', 'COMPLETED'].includes(value) ? 'success' : 'secondary';
        return '<span class="badge text-bg-' + color + ' qe-status">' + escapeHtml(value || '-') + '</span>';
    }
    function fileLink(path, name) {
        return path ? '<a href="/api/file/preview?path=' + encodeURIComponent(path) + '" target="_blank" rel="noopener">' + escapeHtml(name || '첨부 보기') + '</a>' : '-';
    }
    function actionButtons(row, type) {
        const canEdit = type === 'q' ? capability.save : capability.education_manage;
        const edit = canEdit ? '<button class="btn btn-outline-primary btn-sm" data-edit-' + type + '="' + escapeHtml(row.id) + '">수정</button>' : '';
        const verify = type === 'q' && capability.verify ? '<button class="btn btn-outline-success btn-sm" data-verify-q="' + escapeHtml(row.id) + '">검증</button>' : '';
        const renew = type === 'q' && capability.renew ? '<button class="btn btn-outline-warning btn-sm" data-renew-q="' + escapeHtml(row.id) + '">갱신</button>' : '';
        const remove = capability.delete ? '<button class="btn btn-outline-danger btn-sm" data-delete-' + type + '="' + escapeHtml(row.id) + '">삭제</button>' : '';
        return '<div class="d-flex gap-1">' + edit + verify + renew + remove + '</div>';
    }
    const qualificationColumns = [
        {data:'employee_name',title:'직원명'},{data:'username',title:'아이디'},{data:'dept_name',title:'부서',defaultContent:'-'},
        {data:'qualification_name',title:'자격명'},{data:'credential_number',title:'자격번호',defaultContent:'-'},{data:'issuer_name',title:'발급기관',defaultContent:'-'},
        {data:'acquired_date',title:'취득일',defaultContent:'-'},{data:'valid_to',title:'만료일',defaultContent:'-'},{data:'display_status_code',title:'상태',render:badge},
        {data:'attachment_path',title:'첨부',orderable:false,render:(value,type,row)=>fileLink(value,row.attachment_name)},actorColumn('updated_by','수정자'),
        {data:'updated_at',title:'수정일시'},{data:null,title:'관리',orderable:false,render:row=>actionButtons(row,'q')}
    ];
    const educationColumns = [
        {data:'employee_name',title:'직원명'},{data:'username',title:'아이디'},{data:'dept_name',title:'부서',defaultContent:'-'},{data:'course_name',title:'교육과정'},
        {data:'education_name',title:'교육명'},{data:'institution_name',title:'교육기관',defaultContent:'-'},{data:'education_start_at',title:'교육일'},
        {data:'education_minutes',title:'교육시간(분)'},{data:'completion_number',title:'수료번호',defaultContent:'-'},{data:'valid_to',title:'만료일',defaultContent:'-'},
        {data:'display_status_code',title:'상태',render:badge},{data:'attachment_path',title:'첨부',orderable:false,render:(value,type,row)=>fileLink(value,row.attachment_name)},
        actorColumn('updated_by','수정자'),{data:null,title:'관리',orderable:false,render:row=>actionButtons(row,'e')}
    ];
    const courseColumns = [
        {data:'course_code',title:'코드'},{data:'course_name',title:'교육과정'},{data:'education_type_code',title:'교육 종류'},
        {data:'default_institution_name',title:'기본 기관',defaultContent:'-'},{data:'default_minutes',title:'기본 시간(분)'},{data:'validity_months',title:'유효기간(개월)'},
        {data:'is_mandatory',title:'필수',render:value=>value==1?'예':'아니오'},{data:'is_active',title:'사용',render:value=>value==1?'사용':'미사용'},
        {data:null,title:'관리',orderable:false,render:row=>'<button class="btn btn-outline-primary btn-sm" data-edit-course="' + escapeHtml(row.id || row.value) + '">수정</button>'}
    ];
    async function initializeTable() {
        table?.destroy?.();
        const isCourse = active === 'courses';
        const isEducation = active === 'educations';
        const api = isEducation ? API.eList : API.qList;
        table = await createDataTable({
            tableSelector:'#qualificationEducationTable', api:isCourse ? null : api, data:isCourse ? (options.courses || []) : undefined,
            serverSide:!isCourse, searching:false, paging:!isCourse, pageLength:50,
            columns:isCourse ? courseColumns : isEducation ? educationColumns : qualificationColumns,
            tableSettings:{enabled:true,pageKey:'institution.human_resources.qualification_education',tableKey:'qualification-education-' + active,storageKey:'datatable.settings.institution.qualification-education.' + active + '.v1',tableLabel:'자격·교육관리'}
        });
        if (!isCourse) SearchForm({table,apiList:api,tableId:'qualificationEducation',defaultSearchField:'employee_id',dateOptions:[{value:'valid_to',label:'만료일'},{value:'acquired_date',label:'취득일'},{value:'education_start_at',label:'교육일'}],normalizeFilters:filters=>{const normalized=[...filters];if(initialEmployeeId&&!normalized.some(filter=>filter.field==='employee_id'))normalized.push({field:'employee_id',value:initialEmployeeId});if(active==='expiring')normalized.push({field:'expiry_state',value:'EXPIRING'});return normalized;}});
        document.getElementById('qeExcel')?.setAttribute('href', API.excel + '?type=' + (isEducation ? 'educations' : 'qualifications'));
    }
    function setForm(form, row = {}) {
        form.reset();
        form.dataset.mode = 'save';
        [...form.elements].forEach(element => {
            if (!element.name || element.type === 'file') return;
            if (element.type === 'checkbox') element.checked = Number(row[element.name] ?? (element.name === 'is_active' ? 1 : 0)) === 1;
            else element.value = row[element.name] ?? '';
        });
        form.elements.request_key.value = requestKey();
        const file = form.querySelector('[data-current-file]');
        if (file) file.innerHTML = fileLink(row.attachment_path, row.attachment_name);
    }
    async function get(url) {
        const response = await fetch(url,{credentials:'same-origin'}); const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || '조회 중 오류가 발생했습니다.');
        return payload.data;
    }
    async function send(url, input) {
        const isForm = input instanceof HTMLFormElement;
        const response = await fetch(url,{method:'POST',credentials:'same-origin',headers:isForm?{}:{'Content-Type':'application/json'},body:isForm?new FormData(input):JSON.stringify(input)});
        const payload = await response.json(); if (!response.ok || !payload.success) throw new Error(payload.message || '처리 중 오류가 발생했습니다.');
        window.AppCore?.notify?.('success',payload.message || '처리되었습니다.'); return payload;
    }
    async function editQualification(id, mode = 'save') { setForm(qForm, await get(API.qDetail + '?id=' + encodeURIComponent(id))); qForm.dataset.mode = mode; qModal.show(); }
    async function editEducation(id) { setForm(eForm, await get(API.eDetail + '?id=' + encodeURIComponent(id))); eModal.show(); }
    document.querySelectorAll('[data-qe-tab]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[data-qe-tab]').forEach(item => item.classList.toggle('active',item===button)); active=button.dataset.qeTab; initializeTable();
    }));
    document.querySelectorAll('[data-qe-action]').forEach(button => button.addEventListener('click', () => {
        if (button.dataset.qeAction === 'qualification-add') { setForm(qForm); qModal.show(); } else { setForm(eForm); eModal.show(); }
    }));
    document.querySelector('#qualificationEducationTable tbody')?.addEventListener('click', async event => {
        try {
            const q=event.target.closest('[data-edit-q]'),e=event.target.closest('[data-edit-e]'),course=event.target.closest('[data-edit-course]'),verify=event.target.closest('[data-verify-q]'),renew=event.target.closest('[data-renew-q]'),dq=event.target.closest('[data-delete-q]'),de=event.target.closest('[data-delete-e]');
            if(q) await editQualification(q.dataset.editQ); else if(e) await editEducation(e.dataset.editE);
            else if(verify&&confirm('자격 정보를 검증 완료 처리하시겠습니까?')){await send(API.qVerify,{id:verify.dataset.verifyQ,request_key:requestKey(),reason:'관리자 검증'});table.ajax.reload(null,false);}
            else if(renew) await editQualification(renew.dataset.renewQ,'renew');
            else if(course){const row=(options.courses||[]).find(item=>(item.id||item.value)===course.dataset.editCourse);setForm(courseForm,row||{});courseModal.show();}
            else if(dq&&confirm('자격 정보를 삭제하시겠습니까?')){await send(API.qDelete,{id:dq.dataset.deleteQ,request_key:requestKey(),reason:'관리자 삭제'});table.ajax.reload(null,false);}
            else if(de&&confirm('교육 이력을 삭제하시겠습니까?')){await send(API.eDelete,{id:de.dataset.deleteE,request_key:requestKey(),reason:'관리자 삭제'});table.ajax.reload(null,false);}
        } catch(error) { window.AppCore?.notify?.('error',error.message); }
    });
    qForm.addEventListener('submit',async event=>{event.preventDefault();try{await send(qForm.dataset.mode==='renew'?API.qRenew:API.qSave,qForm);qModal.hide();table?.ajax?.reload(null,false);}catch(error){window.AppCore?.notify?.('error',error.message);}});
    eForm.addEventListener('submit',async event=>{event.preventDefault();try{await send(API.eSave,eForm);eModal.hide();table?.ajax?.reload(null,false);}catch(error){window.AppCore?.notify?.('error',error.message);}});
    courseForm.addEventListener('submit',async event=>{event.preventDefault();try{await send(API.courseSave,courseForm);courseModal.hide();location.reload();}catch(error){window.AppCore?.notify?.('error',error.message);}});
    initializeOptions(); initializeTable();
}
