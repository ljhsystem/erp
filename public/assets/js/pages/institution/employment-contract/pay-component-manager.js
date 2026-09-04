import { notify } from '/public/assets/js/common/notification.js';
import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

const API = Object.freeze({
    list: '/api/institution/human-resources/pay-component/list',
    detail: '/api/institution/human-resources/pay-component/detail',
    save: '/api/institution/human-resources/pay-component/save',
    delete: '/api/institution/human-resources/pay-component/delete',
    reorder: '/api/institution/human-resources/pay-component/reorder',
});
const modalElement = document.getElementById('employmentPayComponentModal');
const form = document.getElementById('employmentPayComponentForm');
const tableElement = document.getElementById('employmentPayComponentTable');
const listPanel = modalElement?.querySelector('.employment-pay-component-list-panel');
const deleteButton = document.getElementById('employmentPayComponentDelete');
const countLabel = document.getElementById('employmentPayComponentCount');
const editorTitle = document.getElementById('employmentPayComponentEditorTitle');
const editorHint = document.getElementById('employmentPayComponentEditorHint');
const editorMode = document.getElementById('employmentPayComponentEditorMode');
const editorPanel = document.getElementById('employmentPayComponentEditor');
const editorBackdrop = document.getElementById('employmentPayComponentSidebarBackdrop');
const modal = modalElement && window.bootstrap ? new window.bootstrap.Modal(modalElement, { backdrop: 'static' }) : null;
const treatmentOptions = '<option value="INCLUDED">포함</option><option value="EXCLUDED">제외</option><option value="REVIEW_REQUIRED">검토 필요</option>';
let currentRows = [];
let selectedId = '';
let dataTable = null;
let datePicker = null;
let activeDateInput = null;
const labels = Object.freeze({
    component_type:{BASE_PAY:'기본급',ALLOWANCE:'일반수당',STATUTORY_PREMIUM:'법정 가산수당',BONUS:'상여금',OTHER_WAGE:'기타 임금'},
    default_tax_type:{TAXABLE:'과세',NON_TAXABLE:'비과세',POLICY_CALCULATED:'정책 적용'},
});

['minimum_wage_treatment','ordinary_wage_treatment','average_wage_treatment'].forEach(name => {
    if (form?.elements[name]) form.elements[name].innerHTML = treatmentOptions;
});

async function request(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }, ...options });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success === false) throw new Error(payload.message || '급여항목 처리 중 오류가 발생했습니다.');
    return payload;
}

function resetForm() {
    form.reset();
    form.elements.id.value = '';
    form.elements.component_type.value = 'ALLOWANCE';
    form.elements.default_calculation_type.value = 'FIXED_AMOUNT';
    form.elements.default_tax_type.value = 'TAXABLE';
    form.elements.minimum_wage_treatment.value = 'REVIEW_REQUIRED';
    form.elements.ordinary_wage_treatment.value = 'REVIEW_REQUIRED';
    form.elements.average_wage_treatment.value = 'REVIEW_REQUIRED';
    form.elements.is_active.checked = true;
    deleteButton.classList.add('d-none');
    selectedId='';editorTitle.textContent='새 급여항목';editorHint.textContent='표시순서는 저장할 때 자동으로 지정됩니다.';editorMode.textContent='신규';editorMode.className='badge text-bg-primary';openEditor();markSelected();
    form.elements.component_name.focus();
}

function fillForm(row) {
    Object.keys(row).forEach(key => {
        const control = form.elements[key];
        if (!control) return;
        if (control.type === 'checkbox') control.checked = Number(row[key]) === 1;
        else control.value = row[key] ?? '';
    });
    deleteButton.classList.toggle('d-none', !row.id || !!row.deleted_at);
    selectedId=String(row.id||'');editorTitle.textContent=row.component_name||'급여항목 수정';editorHint.textContent=`${row.component_code} 항목의 계산·과세 기준을 수정합니다.`;editorMode.textContent='수정';editorMode.className='badge text-bg-warning';openEditor();markSelected();
}

function escape(value){const node=document.createElement('div');node.textContent=String(value??'');return node.innerHTML;}
function badge(text,className='text-bg-light'){return `<span class="badge ${className}">${escape(text)}</span>`;}
function formatDate(date){return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;}
function initDatePicker(){const container=document.getElementById('employment-pay-component-date-picker');if(!container||datePicker)return datePicker;datePicker=AdminPicker.create({type:'today',container});datePicker.subscribe((_,date)=>{if(!activeDateInput||!date)return;activeDateInput.value=formatDate(date);activeDateInput.dispatchEvent(new Event('input',{bubbles:true}));activeDateInput.dispatchEvent(new Event('change',{bubbles:true}));datePicker.close();});return datePicker;}
function openDatePicker(input){const picker=initDatePicker();if(!picker||input.disabled)return;activeDateInput=input;picker.clearDate?.();if(/^\d{4}-\d{2}-\d{2}$/.test(input.value)){const [year,month,day]=input.value.split('-').map(Number);picker.setDate(new Date(year,month-1,day));}picker.open({anchor:input});}
form?.querySelectorAll('[data-pay-component-date]').forEach(input=>{input.addEventListener('input',()=>{const digits=input.value.replace(/\D/g,'').slice(0,8);input.value=digits.length===8?`${digits.slice(0,4)}-${digits.slice(4,6)}-${digits.slice(6,8)}`:digits;});input.addEventListener('click',()=>openDatePicker(input));input.closest('.date-input')?.querySelector('.date-icon')?.addEventListener('click',()=>openDatePicker(input));});
function syncEditorTop(){const manager=modalElement.querySelector('.employment-pay-component-manager'),wrapper=document.querySelector('#employmentPayComponentTable_wrapper')||document.querySelector('#employmentPayComponentTable')?.closest('.dt-container'),controls=wrapper?.querySelector('.dt-layout-row')||wrapper?.querySelector('.dataTables_filter')?.parentElement;if(!manager)return;const top=controls?Math.max(72,controls.getBoundingClientRect().bottom-manager.getBoundingClientRect().top+8):118;manager.style.setProperty('--pay-component-sidebar-top',`${Math.round(top)}px`);}
function adjustTableWidth(){window.requestAnimationFrame(()=>dataTable?.columns?.adjust?.().draw(false));}
function openEditor(){const manager=modalElement.querySelector('.employment-pay-component-manager');syncEditorTop();manager?.classList.add('is-editor-open');editorPanel.classList.add('is-open');editorPanel.setAttribute('aria-hidden','false');editorBackdrop.classList.add('is-open');adjustTableWidth();}
function closeEditor(){const manager=modalElement.querySelector('.employment-pay-component-manager');datePicker?.close?.();manager?.classList.remove('is-editor-open');editorPanel.classList.remove('is-open');editorPanel.setAttribute('aria-hidden','true');editorBackdrop.classList.remove('is-open');selectedId='';markSelected();adjustTableWidth();}
function markSelected(){document.querySelectorAll('#employmentPayComponentTable tbody tr').forEach(tr=>tr.classList.toggle('is-selected',String(dataTable?.row(tr).data()?.id||'')===selectedId));}
function managementColumns(){const hidden=(data,title,extra={})=>({data,title,visible:false,defaultContent:'',...extra});return[{data:'sort_no',title:'순서',className:'text-center'},{data:'component_name',title:'급여항목',render:(value,type,row)=>type==='display'?`<strong>${escape(value)}</strong><small class="d-block text-muted"> ${escape(row.component_code)}</small>`:`${value} ${row.component_code}`},{data:'component_type',title:'구분',render:value=>badge(labels.component_type[value]||value)},{data:'default_tax_type',title:'과세',render:value=>badge(labels.default_tax_type[value]||value,value==='NON_TAXABLE'?'text-bg-info':'text-bg-light')},{data:'is_active',title:'사용',render:value=>badge(Number(value)===1?'사용':'중지',Number(value)===1?'text-bg-success':'text-bg-secondary')},hidden('id','ID'),hidden('component_code','항목코드'),hidden('default_calculation_type','기본계산방식'),hidden('tax_policy_code','세무정책코드'),hidden('ordinary_wage_treatment','통상임금 반영'),hidden('average_wage_treatment','평균임금 반영'),hidden('minimum_wage_treatment','최저임금 반영'),hidden('effective_from','적용 시작일'),hidden('effective_to','적용 종료일'),hidden('note','비고'),hidden('memo','메모'),hidden('created_at','생성일시'),hidden('created_by','생성자',{render:(_value,_type,row)=>escape(row.created_by_name||row.created_by||'-')}),hidden('updated_at','수정일시'),hidden('updated_by','수정자',{render:(_value,_type,row)=>escape(row.updated_by_name||row.updated_by||'-')}),hidden('deleted_at','삭제일시'),hidden('deleted_by','삭제자',{render:(_value,_type,row)=>escape(row.deleted_by_name||row.deleted_by||'-')}),{data:null,settingsKey:'__actions',__dtColumnKind:'virtual',__dtVirtualType:'system',title:'관리',orderable:false,searchable:false,className:'text-center',render:()=>'<button type="button" class="btn btn-outline-primary btn-sm" data-pay-component-open>열기</button>'}];}
function buildTableHeader(columns){const head=document.createElement('thead'),row=document.createElement('tr');columns.forEach(column=>{const th=document.createElement('th');th.textContent=column.title||'';row.append(th);});head.append(row);tableElement.replaceChildren(head);}
function revealCompletedTable(){window.requestAnimationFrame(()=>window.requestAnimationFrame(()=>listPanel?.classList.remove('is-table-initializing')));}
async function render(rows) {
    currentRows=rows;countLabel.textContent=`${rows.length}개`;
    if(!dataTable){const columns=managementColumns();buildTableHeader(columns);dataTable=await createDataTable({tableSelector:'#employmentPayComponentTable',initialData:rows,serverSide:false,selectable:true,showSelectionMoveButtons:true,showCopyButton:true,deleteButton:true,deleteApi:API.delete,searching:true,paging:true,pageLength:10,defaultOrder:[[1,'asc']],widthScopeSelector:'.employment-pay-component-list-panel',fitColumnsToScope:true,buttons:[{text:'+ 신규등록',className:'btn btn-primary btn-sm',action:resetForm}],columns,tableSettings:{enabled:true,pageKey:'institution.human_resources.employment_contracts',userSettingPageKey:'institution.human_resources.employment_contracts',tableKey:'employment-contract-pay-component',storageKey:'datatable.settings.institution.employment-contract.pay-component.v4',tableLabel:'급여항목 관리',metaDomain:'employment-contract-pay-component',defaultVisibleColumns:['__select','sort_no','component_name','component_type','default_tax_type','is_active','__actions'],resetOnColumnSchemaChange:true}});}
    else{dataTable.clear();dataTable.rows.add(rows);dataTable.draw();}
    dataTable.columns.adjust().draw(false);syncEditorTop();markSelected();revealCompletedTable();
}

async function load() { const payload=await request(API.list);await render(payload.data||[]);closeEditor(); }
async function openManager() { try { const ready=modalElement.classList.contains('show')?Promise.resolve():new Promise(resolve=>modalElement.addEventListener('shown.bs.modal',resolve,{once:true}));modal?.show();await ready;await load(); } catch(error) { listPanel?.classList.remove('is-table-initializing');notify('error',error.message); } }

document.getElementById('employmentPayComponentManage')?.addEventListener('click', openManager);
document.getElementById('employmentPayComponentReset')?.addEventListener('click', resetForm);
tableElement?.addEventListener('click',event=>{if(event.target.closest('input,button,a'))return;const tr=event.target.closest('tr');const row=tr?dataTable?.row(tr).data():null;if(row)fillForm(row);});
tableElement?.addEventListener('datatable:move-selected',async event=>{
    event.preventDefault();
    const selected=new Set((event.detail?.ids||[]).map(String));
    const ordered=[...currentRows].sort((a,b)=>Number(a.sort_no)-Number(b.sort_no));
    if(event.detail?.direction==='up'){
        for(let index=1;index<ordered.length;index+=1){if(selected.has(String(ordered[index].id))&&!selected.has(String(ordered[index-1].id)))[ordered[index-1],ordered[index]]=[ordered[index],ordered[index-1]];}
    }else{
        for(let index=ordered.length-2;index>=0;index-=1){if(selected.has(String(ordered[index].id))&&!selected.has(String(ordered[index+1].id)))[ordered[index],ordered[index+1]]=[ordered[index+1],ordered[index]];}
    }
    const changes=ordered.map((row,index)=>({id:row.id,newSortNo:index+1}));
    try{await request(API.reorder,{method:'POST',body:JSON.stringify({changes})});await load();notify('success','순서를 변경했습니다.');}
    catch(error){notify('error',error.message);}
});
tableElement?.addEventListener('datatable:soft-delete-completed',()=>load());
document.getElementById('employmentPayComponentEditorClose')?.addEventListener('click',closeEditor);
editorBackdrop?.addEventListener('click',closeEditor);
form?.addEventListener('submit', async event => {
    event.preventDefault();
    const payload=Object.fromEntries(new FormData(form).entries());payload.is_active=form.elements.is_active.checked?1:0;
    try { const result=await request(API.save,{method:'POST',body:JSON.stringify(payload)});await load();fillForm(result.data||{});document.dispatchEvent(new CustomEvent('pay-component:changed'));notify('success','급여항목이 저장되었습니다.'); }
    catch(error) { notify('error',error.message); }
});
deleteButton?.addEventListener('click', async () => {
    const id=String(form.elements.id.value||'');if(!id)return;
    if(!window.confirm('이 급여항목을 삭제하시겠습니까? 기존 계약의 저장 Snapshot은 유지됩니다.'))return;
    try { await request(API.delete,{method:'POST',body:JSON.stringify({id})});await load();document.dispatchEvent(new CustomEvent('pay-component:changed'));notify('success','급여항목이 삭제되었습니다.'); }
    catch(error) { notify('error',error.message); }
});
modalElement?.addEventListener('hidden.bs.modal', () => {
    if (document.getElementById('employmentContractModal')?.classList.contains('show')) document.body.classList.add('modal-open');
});
modalElement?.addEventListener('shown.bs.modal',()=>{dataTable?.columns?.adjust?.().draw(false);syncEditorTop();});
editorPanel?.addEventListener('transitionend',event=>{if(event.propertyName==='width'||event.propertyName==='transform')adjustTableWidth();});
window.addEventListener('resize',syncEditorTop);
