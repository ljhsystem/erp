import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { createHtmlGrid } from '/public/assets/js/common/html-grid/index.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { formatYearMonthValue, normalizeYearMonthInputValue, parseYearMonthValue } from '/public/assets/js/common/picker/picker.yearmonth.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { actorColumn } from '/public/assets/js/common/actor.js';
import { bindNumberInput, formatAmount, formatDateDisplay } from '/public/assets/js/common/format.js';
import { notify } from '/public/assets/js/common/notification.js';
import { confirmDialog } from '/public/assets/js/common/confirm-dialog.js';
import { bindModalCardCollapses } from '/public/assets/js/common/modal-card-collapse.js';
import { createDataTableFormSettings } from '/public/assets/js/common/datatable/dataTableFormSettings.js';
import { INCOME_INSTITUTION_CARDS, incomeCalculationLineDto, incomeCalculationPeriodText, incomeCalculationRoundingText, incomeInstitutionCardsDto, isIncomeCalculationEditableStatus, renderIncomeCalculationCards } from '/public/assets/js/common/income-calculation-cards.js?v=20260831-income-cards-14';
import { getCachedDataTableMetaColumns, readDataTableSettingsState, resolveDataTableColumnDisplayName, resolveDataTableColumnRequirementPolicy } from '/public/assets/js/common/datatable/dataTableSettings.js';
import '/public/assets/js/common/core/AppAjax.js';
import '/public/assets/js/components/trash-manager.js';
import { incomeWithholdingDate, INCOME_WITHHOLDING_RULES, isIncomeWithholdingDate } from '/public/assets/js/common/income-withholding-date.js';

const API = { LIST:'/api/institution/income-data/regular-employment/list', DETAIL:'/api/institution/income-data/regular-employment/detail', ELIGIBLE:'/api/institution/income-data/regular-employment/eligible-employees', PAY_COMPONENT_OPTIONS:'/api/institution/human-resources/pay-component/options', CALCULATE:'/api/institution/income-data/regular-employment/calculate', SAVE:'/api/institution/income-data/regular-employment/save', ADJUST:'/api/institution/income-data/regular-employment/adjust', SUBMIT:'/api/institution/income-data/regular-employment/submit', WITHDRAW:'/api/institution/income-data/regular-employment/withdraw', DELETE:'/api/institution/income-data/regular-employment/delete' };
const EDITABLE = { has: isIncomeCalculationEditableStatus };
const TABLE_SETTINGS_KEY = 'institution.income-data.regular-employment.regular-income-table.v1';
const TABLE_SETTINGS_OPTIONS = Object.freeze({pageKey:'institution.income-data.regular-employment',metaDomain:'regular-employment-income'});
const root = document.querySelector('.regular-employment-income-page');
const escapeHtml = value => { const node=document.createElement('div');node.textContent=String(value??'');return node.innerHTML; };
if (root) {
    const form = document.getElementById('regularIncomeForm');
    const withholdingTemplate=document.getElementById('regularIncomeWithholdingDateTemplate');
    const documentFields=form.querySelector('.income-document-fields');
    documentFields.firstElementChild.after(withholdingTemplate.content.cloneNode(true));
    const calculationGuidance=document.createElement('div');
    calculationGuidance.id='regularIncomeCalculationGuidance';
    calculationGuidance.className='alert alert-warning income-calculation-guidance mt-3 mb-3 d-none';
    calculationGuidance.setAttribute('role','status');
    calculationGuidance.setAttribute('aria-live','polite');
    form.querySelector('.income-document-card').after(calculationGuidance);
    const modalElement = document.getElementById('regularIncomeModal');
    const systemInfoTemplate = document.getElementById('regularIncomeSystemInfoTemplate');
    modalElement.querySelector('.modal-body').append(systemInfoTemplate.content.cloneNode(true));
    const modal = new bootstrap.Modal(modalElement);
    const summary = document.getElementById('regularIncomeSummary');
    const employeeCards = document.getElementById('regularIncomeEmployeeCards');
    const detail = document.getElementById('regularIncomeEmployeeDetail');
    const detailTitle = document.getElementById('regularIncomeEmployeeDetailTitle');
    const linesHost = document.getElementById('regularIncomeLineItems');
    const basesHost = document.getElementById('regularIncomeBases');
    const yearMonthValue = form.elements.income_year_month;
    const yearMonthDisplay = document.getElementById('regularIncomeYearMonthDisplay');
    let items = [], documentStatus = 'DRAFT', loadedMonth = '', calculationError = '';
    const recalculationTimers = new Map();
    const dependentCountOverrides = new Map();
    const payComponentOptions = new Map();
    const payComponentOptionRequests = new Map();
    const request = (url, options={}) => window.AppCore.fetchJson(url, {...options, headers:{...(options.body===undefined?{}:{'Content-Type':'application/json'}), ...(options.headers||{})}});
    const number = value => Number(String(value ?? 0).replaceAll(',', '')) || 0;
    const nullableNumber = value => value === null || value === '' || value === undefined ? null : Number(String(value).replaceAll(',', ''));
    const sum = (row, keys) => keys.reduce((total, key) => total + number(row[key]), 0);
    const calculationStatusLabel = value => ({CALCULATED:'계산완료',CONFIRMED:'확정',MANUAL:'수동확정',NEEDS_CONFIRMATION:'확인필요',BLOCKED:'차단'}[value]||value||'확인필요');
    const payComponentEffectiveDate = () => yearMonthValue.value ? `${yearMonthValue.value}-${String(new Date(Number(yearMonthValue.value.slice(0,4)),Number(yearMonthValue.value.slice(5,7)),0).getDate()).padStart(2,'0')}` : '';
    async function loadPayComponentOptions(force=false){const date=payComponentEffectiveDate();if(!date)throw new Error('귀속연월을 먼저 입력해 주세요.');if(force){payComponentOptions.delete(date);payComponentOptionRequests.delete(date);}if(payComponentOptions.has(date))return payComponentOptions.get(date);if(payComponentOptionRequests.has(date))return payComponentOptionRequests.get(date);const pending=request(`${API.PAY_COMPONENT_OPTIONS}?effective_date=${encodeURIComponent(date)}`).then(response=>{const options=response.data||[];if(options.length)payComponentOptions.set(date,options);return options;}).finally(()=>payComponentOptionRequests.delete(date));payComponentOptionRequests.set(date,pending);return pending;}
    const adjustmentToken = () => globalThis.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,character=>{const value=Math.random()*16|0;return(character==='x'?value:(value&3|8)).toString(16);});
    const createPayAdjustmentDraft=()=>({client_key:adjustmentToken(),pay_effect_code:'INCREASE',source_reference_id:null,item_code:null,item_name_snapshot:null,default_tax_type:null,tax_policy_code:null,taxable_flag:null,tax_label:'',final_amount:null,business_reason:''});
    const documentStatusMeta = value => ({
        DRAFT:['미상신','text-bg-secondary'],
        PENDING:['결재 대기','text-bg-primary'],
        APPROVAL_PENDING:['결재 진행','text-bg-primary'],
        IN_PROGRESS:['결재 진행','text-bg-primary'],
        APPROVED:['승인 완료','text-bg-success'],
        REJECTED:['반려','text-bg-danger'],
        WITHDRAWN:['회수','text-bg-secondary'],
    }[String(value||'').trim().toUpperCase()]||['확인 필요','text-bg-warning']);
    const documentStatusBadge = value => {const [label,className]=documentStatusMeta(value);return `<span class="badge ${className} regular-income-table-status">${label}</span>`;};
    const calculationSourceLabel = value => ({CALCULATED:'자동계산',MANUAL:'수동입력',HISTORICAL_IMPORT:'과거자료 확정'})[value] || value || '-';
    const systemInfoFields = [
        {key:'id',label:'문서 ID'}, {key:'company_id',label:'회사 ID'},
        {key:'sort_no',label:'정렬순서'}, {key:'payroll_period_start_date',label:'급여 산정기간 시작일',type:'date'},
        {key:'payroll_period_end_date',label:'급여 산정기간 종료일',type:'date'},
        {key:'employee_count',label:'직원 수',type:'count'}, {key:'calculation_version',label:'계산정책버전'},
        {key:'calculation_source_code',label:'계산원천',type:'calculationSource'}, {key:'document_status',label:'문서상태',type:'documentStatus'},
        {key:'current_approval_request_id',label:'현재 결재요청 ID'}, {key:'approved_at',label:'최종승인일시',type:'date'},
        {key:'gross_amount',label:'지급총액',type:'amount'}, {key:'taxable_amount',label:'과세금액',type:'amount'},
        {key:'non_taxable_amount',label:'비과세금액',type:'amount'}, {key:'income_tax_amount',label:'근로소득세',type:'amount'},
        {key:'local_income_tax_amount',label:'지방소득세',type:'amount'}, {key:'national_pension_amount',label:'국민연금',type:'amount'},
        {key:'health_insurance_amount',label:'건강보험',type:'amount'}, {key:'long_term_care_amount',label:'장기요양보험',type:'amount'},
        {key:'employment_insurance_amount',label:'고용보험',type:'amount'}, {key:'other_deduction_amount',label:'기타공제',type:'amount'},
        {key:'deduction_amount',label:'공제총액',type:'amount'}, {key:'net_payment_amount',label:'실지급액',type:'amount'},
        {key:'created_by_name',fallbackKey:'created_by',label:'생성자'}, {key:'created_at',label:'생성일시',type:'date'},
        {key:'updated_by_name',fallbackKey:'updated_by',label:'수정자'}, {key:'updated_at',label:'수정일시',type:'date'},
        {key:'deleted_by_name',fallbackKey:'deleted_by',label:'삭제자'}, {key:'deleted_at',label:'삭제일시',type:'date'}
    ];
    const systemInfoValue = (data, field) => {
        const value = data?.[field.key] ?? (field.fallbackKey ? data?.[field.fallbackKey] : null);
        if (value === null || value === undefined || value === '') return '-';
        if (field.type === 'amount') return `${formatAmount(value)}원`;
        if (field.type === 'count') return `${formatAmount(value)}명`;
        if (field.type === 'date') return formatDateDisplay(value) || '-';
        if (field.type === 'documentStatus') return documentStatusMeta(value)[0];
        if (field.type === 'calculationSource') return calculationSourceLabel(value);
        return String(value);
    };
    let currentSystemInfoData = {};
    const systemInfoColumnPolicy = field => {
        const metaKey = field.metaKey || field.fallbackKey || field.key;
        const column = getCachedDataTableMetaColumns({metaDomain:'regular-employment-income'}).find(candidate => String(candidate.key || '') === metaKey);
        if (!column) return {label:field.label,policy:'none'};
        const state = readDataTableSettingsState(TABLE_SETTINGS_KEY, TABLE_SETTINGS_OPTIONS);
        const policy = resolveDataTableColumnRequirementPolicy(column, state);
        return {
            label:resolveDataTableColumnDisplayName(column, state, column.label || field.label),
            policy:['required','optional'].includes(policy) ? policy : (column.required ? 'required' : 'none'),
        };
    };
    function renderSystemInfo(data={}) {
        currentSystemInfoData = data;
        const host = document.getElementById('regularIncomeSystemInfoFields');
        if (!host) return;
        host.replaceChildren(...systemInfoFields.map(field => {
            const columnPolicy = systemInfoColumnPolicy(field);
            const item = document.createElement('div');
            item.className = `regular-income-system-info-field${field.type === 'amount' || field.type === 'count' ? ' is-numeric' : ''}`;
            const label = document.createElement('span');
            label.className = 'regular-income-system-info-label';
            label.append(document.createTextNode(columnPolicy.label));
            if (columnPolicy.policy !== 'none') {
                const marker = document.createElement('span');
                marker.className = `column-policy-star is-${columnPolicy.policy}`;
                marker.setAttribute('aria-label', columnPolicy.policy === 'required' ? '필수' : '선택');
                marker.textContent = '*';
                label.append(marker);
            }
            const value = document.createElement('span');
            value.className = 'regular-income-system-info-value';
            value.textContent = systemInfoValue(data, field);
            item.append(label, value);
            return item;
        }));
    }
    const normalizeItem = item => {
        const payLines=(item.line_items||[]).filter(line=>line.item_type_code==='PAY');
        const contractPay=payLines.length?payLines.reduce((total,line)=>total+number(line.final_amount),0):sum(item,['base_salary_amount','allowance_amount','bonus_amount','non_taxable_amount']);
        const gross=number(item.gross_amount)||contractPay;
        const unresolved=['NEEDS_CONFIRMATION','BLOCKED'].includes(item.calculation_status_code)||Number(item.unresolved_deduction_count||0)>0||item.deduction_amount===null||item.net_payment_amount===null;
        const deduction=unresolved?null:(number(item.deduction_amount)||sum(item,['national_pension_amount','health_insurance_amount','long_term_care_amount','employment_insurance_amount','income_tax_amount','local_income_tax_amount','other_deduction_amount']));
        const net=unresolved?null:number(item.net_payment_amount)||(gross-number(deduction));
        return {...item, employee_name_snapshot:item.employee_name_snapshot||item.employee_name||'', department_name_snapshot:item.department_name_snapshot||item.department_name||'', contract_pay_amount:contractPay, additional_pay_amount:0, gross_amount:gross, deduction_amount:deduction, net_payment_amount:net, deduction_amount_display:unresolved?'미확정':formatAmount(deduction), net_payment_amount_display:unresolved?'미확정':formatAmount(net), deduction_unresolved:unresolved, calculation_status_label:calculationStatusLabel(item.calculation_status_code), calculation_message:item.calculation_message||'', adjustment_label:(item.line_items||[]).some(line=>number(line.adjustment_amount)!==0)?'조정':'-'};
    };
    const yearMonthPicker = AdminPicker.create({ type:'year-month', container:document.getElementById('regularIncomeYearMonthPicker') });
    const withholdingDateInput=form.elements.withholding_date;
    const withholdingDatePicker=AdminPicker.create({type:'today',container:document.getElementById('regularIncomeWithholdingDatePicker')});
    function setYearMonth(value) { const date=parseYearMonthValue(value);yearMonthValue.value=date?formatYearMonthValue(date):'';yearMonthDisplay.value=yearMonthValue.value;if(date)yearMonthPicker.setYearMonth(date);else yearMonthPicker.state.date=null; }
    function applyDefaultWithholdingDate(){withholdingDateInput.value=incomeWithholdingDate(yearMonthValue.value,INCOME_WITHHOLDING_RULES.REGULAR);withholdingDateInput.dispatchEvent(new Event('change',{bubbles:true}));}
    function normalizeYearMonthField(){const normalized=normalizeYearMonthInputValue(yearMonthDisplay.value);yearMonthDisplay.value=normalized;const date=parseYearMonthValue(normalized);yearMonthValue.value=date?formatYearMonthValue(date):'';if(date)yearMonthPicker.setYearMonth(date);return Boolean(date);}
    function openYearMonthPicker(){if(yearMonthValue.value)yearMonthPicker.setYearMonth(yearMonthValue.value);yearMonthPicker.open({anchor:yearMonthDisplay});}
    yearMonthPicker.subscribe((_,date)=>{setYearMonth(date?formatYearMonthValue(date):'');applyDefaultWithholdingDate();yearMonthPicker.close();yearMonthDisplay.dispatchEvent(new Event('change',{bubbles:true}));});
    const bindDateIcon=(id,open)=>{const icon=document.getElementById(id);icon.addEventListener('click',()=>{if(icon.getAttribute('aria-disabled')!=='true')open();});icon.addEventListener('keydown',event=>{if(icon.getAttribute('aria-disabled')==='true'||!['Enter',' '].includes(event.key))return;event.preventDefault();open();});};
    bindDateIcon('regularIncomeYearMonthButton',openYearMonthPicker);
    withholdingDatePicker.subscribe((_,date)=>{if(!date)return;withholdingDateInput.value=[date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');withholdingDatePicker.close();withholdingDateInput.dispatchEvent(new Event('change',{bubbles:true}));});
    const openWithholdingDatePicker=()=>{if(!withholdingDateInput.disabled)withholdingDatePicker.open({anchor:withholdingDateInput});};
    withholdingDateInput.addEventListener('click',openWithholdingDatePicker);
    async function handleWithholdingDateChange(){
        if(!EDITABLE.has(documentStatus))return;
        calculationError='';
        items=items.map(item=>({...item,calculation_status_code:'NEEDS_CONFIRMATION',calculation_message:'원천징수일을 입력하면 해당 날짜의 법정기준으로 자동 계산됩니다.',deduction_amount:null,net_payment_amount:null}));
        closeDetail();renderItems();syncWorkflowActions();
        if(!isIncomeWithholdingDate(withholdingDateInput.value)||!items.length)return;
        try{items=await calculateItems(yearMonthValue.value,items);renderItems();syncWorkflowActions();}catch(error){calculationError=error.message||'원천징수일 변경 후 자동계산 중 오류가 발생했습니다.';renderCalculationGuidance();notify('error',calculationError);}
    }
    withholdingDateInput.addEventListener('change',handleWithholdingDateChange);
    bindDateIcon('regularIncomeWithholdingDateButton',openWithholdingDatePicker);
    yearMonthDisplay.addEventListener('input',()=>{yearMonthDisplay.value=normalizeYearMonthInputValue(yearMonthDisplay.value);normalizeYearMonthField();});
    yearMonthDisplay.addEventListener('blur',()=>{if(yearMonthDisplay.value&&!normalizeYearMonthField())notify('warning','귀속연월은 YYYY-MM 형식으로 입력해 주세요.');else if(yearMonthValue.value)applyDefaultWithholdingDate();});
    bindModalCardCollapses(modalElement, {resetOnShow:true});
    renderSystemInfo();
    modalElement.addEventListener('hidden.bs.modal',()=>{yearMonthPicker.close();withholdingDatePicker.close();});
    modalElement.addEventListener('shown.bs.modal',()=>{if(currentSystemInfoData?.withholding_date)withholdingDateInput.value=currentSystemInfoData.withholding_date;});

    const table = await createDataTable({tableSelector:'#regular-income-table', api:API.LIST, serverSide:true, selectable:true, deleteApi:API.DELETE, searchTableId:'regularIncome', columns:[
        {data:'sort_no',title:'순번',className:'text-center'}, {data:'income_year_month',title:'귀속연월'}, {data:'title',title:'제목'}, {data:'employee_count',title:'대상인원',className:'text-end'},
        ...['gross_amount','taxable_amount','non_taxable_amount','deduction_amount','net_payment_amount'].map((key,index)=>({data:key,title:['지급총액','과세금액','비과세금액','공제합계','실지급액'][index],className:'text-end',render:formatAmount})),
        {data:'document_status',title:'문서상태',className:'text-center',render:(value,type)=>type==='display'?documentStatusBadge(value):documentStatusMeta(value)[0]}, {data:'approved_at',title:'최종승인일',defaultContent:''}, actorColumn('updated_by','수정자',{defaultContent:''}), {data:null,title:'관리',orderable:false,searchable:false,render:()=>'<button class="btn btn-outline-primary btn-sm" data-detail>상세</button>'}
    ], tableSettings:{pageKey:'institution.income-data.regular-employment',tableKey:'regular-income-table',metaDomain:'regular-employment-income',tableLabel:'상용근로소득'}, buttons:[{text:'휴지통',className:'btn btn-danger btn-sm',action:()=>window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('regularIncomeTrashModal')).show()},{text:'신규등록',className:'btn btn-warning btn-sm',action:openCreate}]});
    const formSettings = createDataTableFormSettings({form,metaDomain:'regular-employment-income',storageKey:TABLE_SETTINGS_KEY,settingsOptions:TABLE_SETTINGS_OPTIONS});
    const applyFormSettings = () => { const resolved=formSettings.apply();yearMonthDisplay.required=resolved.get('income_year_month')?.required===true; };
    applyFormSettings();
    renderSystemInfo(currentSystemInfoData);
    document.addEventListener('datatable-settings:updated', event => {
        if (event.detail?.storageKey === TABLE_SETTINGS_KEY) {
            applyFormSettings();
            renderSystemInfo(currentSystemInfoData);
        }
    });
    window.TrashColumns=window.TrashColumns||{};
    window.TrashColumns['regular-employment-income']=row=>`<td>${escapeHtml(row.income_year_month||'-')}</td><td>${escapeHtml(row.title||'-')}</td><td>${documentStatusBadge(row.document_status)}</td><td>${escapeHtml(formatDateDisplay(row.deleted_at)||'-')}</td><td></td>`;
    document.addEventListener('trash:changed',event=>{if(event.detail?.type==='regular-employment-income')table.ajax.reload(null,false);});

    function createActionEditor(context={}) {
        const wrap=document.createElement('div'); wrap.className='regular-income-grid-actions';
        const view=document.createElement('button'); view.type='button'; view.className='btn btn-outline-primary btn-sm'; view.textContent='상세'; view.addEventListener('click',()=>{showDetail(context.row.values.employee_id);renderDetailActions(context.row.values.employee_id);}); wrap.append(view);
        if(EDITABLE.has(documentStatus)){const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger btn-sm';remove.textContent='제외';remove.addEventListener('click',()=>{items=items.filter(item=>item.employee_id!==context.row.values.employee_id);closeDetail();renderItems();});wrap.append(remove);}
        return {element:wrap,create:()=>wrap,mount:host=>host.append(wrap),getValue:()=>''};
    }
    let draggedEmployeeId='',dragOverEmployeeId='';
    function createOrderEditor(context={}) {
        const wrap=document.createElement('div');wrap.className='regular-income-order-cell';
        const handle=document.createElement('span');handle.className='regular-income-order-handle';handle.textContent='☰';
        const sequence=document.createElement('strong');sequence.textContent=String(context.row.values.sort_no||'');
        if(EDITABLE.has(documentStatus)){
            handle.draggable=true;handle.tabIndex=0;handle.title='드래그하여 급여대장 순번 변경';handle.setAttribute('aria-label',`${context.row.values.employee_name_snapshot||'직원'} 순번 변경`);
            handle.addEventListener('dragstart',event=>{draggedEmployeeId=String(context.row.values.employee_id||'');event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',draggedEmployeeId);});
            handle.addEventListener('dragover',event=>{event.preventDefault();event.dataTransfer.dropEffect='move';});
            handle.addEventListener('drop',event=>{event.preventDefault();const sourceId=draggedEmployeeId||event.dataTransfer.getData('text/plain');const fromIndex=items.findIndex(item=>String(item.employee_id)===String(sourceId));const toIndex=items.findIndex(item=>String(item.employee_id)===String(context.row.values.employee_id));if(fromIndex>=0&&toIndex>=0&&fromIndex!==toIndex)grid.reorderRow(fromIndex,toIndex);draggedEmployeeId='';});
            handle.addEventListener('dragend',()=>{draggedEmployeeId='';});
            handle.addEventListener('keydown',event=>{if(!['ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();const fromIndex=items.findIndex(item=>String(item.employee_id)===String(context.row.values.employee_id));const toIndex=event.key==='ArrowUp'?fromIndex-1:fromIndex+1;if(toIndex>=0&&toIndex<items.length)grid.reorderRow(fromIndex,toIndex);});
        }else{handle.classList.add('is-disabled');handle.title='결재가 진행된 자료는 순번을 변경할 수 없습니다.';handle.setAttribute('aria-label','순번 변경 불가');}
        wrap.append(sequence,handle);
        return {element:wrap,create:()=>wrap,mount:host=>host.append(wrap),getValue:()=>context.row.values.sort_no};
    }
    const grid=createHtmlGrid({host:document.getElementById('regularIncomeItemsGrid'),gridId:'regular-employment-income-employees',rows:[],columns:[
        {key:'sort_no',label:'순번',width:65,pinned:'left',editor:'regular-income-order',editable:false},{key:'employee_name_snapshot',label:'직원',width:85,pinned:'left',editable:false},{key:'department_name_snapshot',label:'부서',width:85,editable:false},{key:'contract_pay_amount',label:'계약 지급액',type:'number',width:100,formatter:'number',editable:false},{key:'additional_pay_amount',label:'추가 지급',type:'number',width:75,formatter:'number',editable:false},{key:'gross_amount',label:'지급총액',type:'number',width:100,formatter:'number',editable:false},{key:'deduction_amount_display',label:'공제총액',width:90,editable:false},{key:'net_payment_amount_display',label:'실지급액',width:100,editable:false},{key:'calculation_status_label',label:'계산상태',width:90,editable:false},{key:'calculation_message',label:'확인사항',width:190,editable:false},{key:'adjustment_label',label:'조정',width:45,editable:false},{key:'row_action',label:'관리',width:100,editor:'regular-income-action',editable:false}
    ],editors:{'regular-income-order':createOrderEditor,'regular-income-action':createActionEditor},rowNumberField:'sort_no',capabilities:{addRow:false,deleteRow:false,reorder:true,selection:false,footer:false,keyboard:true,columnResize:false,columnHide:false,columnMove:false,clipboard:false}});
    grid.on('row:moved',({fromIndex,toIndex})=>{if(!EDITABLE.has(documentStatus))return;const [moved]=items.splice(fromIndex,1);items.splice(toIndex,0,moved);items.forEach((item,index)=>{item.sort_no=index+1;});const selectedEmployeeId=detail.classList.contains('is-hidden')?'':detail.dataset.employeeId;if(selectedEmployeeId)showDetail(selectedEmployeeId);});
    grid.render({noDataMessage:'귀속월 대상직원을 불러와 주세요.'});

    let calculationMessageTooltips=[];
    let calculationMessageTooltipVersion=0;
    function disposeCalculationMessageTooltips(){calculationMessageTooltipVersion+=1;calculationMessageTooltips.forEach(instance=>instance.dispose());calculationMessageTooltips=[];}
    function refreshCalculationMessageTooltips(){const version=calculationMessageTooltipVersion;requestAnimationFrame(()=>{if(version!==calculationMessageTooltipVersion)return;document.querySelectorAll('#regularIncomeItemsGrid .html-grid-column-calculation_message .html-grid-cell-value').forEach(value=>{const message=value.textContent.trim();if(!message||value.scrollWidth<=value.clientWidth)return;value.title=message;if(window.bootstrap?.Tooltip)calculationMessageTooltips.push(window.bootstrap.Tooltip.getOrCreateInstance(value,{container:'body',placement:'top',trigger:'hover focus'}));});});}
    function renderEmployeeCards(){
        employeeCards.replaceChildren();
        if(!items.length){const empty=document.createElement('div');empty.className='regular-income-employee-empty';empty.textContent='귀속월 대상직원을 불러와 주세요.';employeeCards.append(empty);return;}
        const selectedId=detail.classList.contains('is-hidden')?'':String(detail.dataset.employeeId||'');
        items.forEach((item,index)=>{
            const employeeId=String(item.employee_id||'');const expanded=selectedId===employeeId&&!detail.classList.contains('is-hidden');
            const card=document.createElement('article');card.className=`regular-income-employee-card${expanded?' is-selected':' is-collapsed'}`;card.dataset.employeeId=employeeId;
            const header=document.createElement('header');header.className=`regular-income-employee-card__header${EDITABLE.has(documentStatus)?' has-remove-action':''}`;
            const deduction=item.deduction_amount===null||item.deduction_unresolved?'미확정':`${formatAmount(item.deduction_amount)}원`;const net=item.net_payment_amount===null||item.deduction_unresolved?'미확정':`${formatAmount(item.net_payment_amount)}원`;
            header.innerHTML=`<span class="regular-income-employee-order">${index+1}</span><span class="regular-income-employee-drag-handle" title="${EDITABLE.has(documentStatus)?'드래그하여 순서 변경':'순서 변경 불가'}" aria-label="${EDITABLE.has(documentStatus)?'드래그하여 순서 변경':'순서 변경 불가'}"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span><button type="button" class="regular-income-employee-select">${escapeHtml(item.employee_name_snapshot||'직원 미선택')}</button><span class="regular-income-employee-badge">${escapeHtml(item.position_name_snapshot||'직책 미확인')}</span><span class="regular-income-employee-department">부서: ${escapeHtml(item.department_name_snapshot||'미지정')}</span><span class="regular-income-employee-payment">지급액(세전) ${formatAmount(item.gross_amount)}원</span><span>공제 ${deduction}</span><span>실지급 ${net}</span><span class="badge text-bg-${item.deduction_unresolved?'warning':'success'}">${escapeHtml(item.calculation_status_label||'계산 완료')}</span>`;
            const handle=header.querySelector('.regular-income-employee-drag-handle');
            if(EDITABLE.has(documentStatus)){
                handle.draggable=true;handle.tabIndex=0;
                handle.addEventListener('dragstart',event=>{draggedEmployeeId=employeeId;dragOverEmployeeId='';card.classList.add('is-dragging');event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',employeeId);});
                card.addEventListener('dragover',event=>{event.preventDefault();event.dataTransfer.dropEffect='move';card.classList.add('is-drag-over');});
                card.addEventListener('dragenter',event=>{event.preventDefault();const sourceId=draggedEmployeeId;if(!sourceId||sourceId===employeeId||dragOverEmployeeId===employeeId)return;dragOverEmployeeId=employeeId;const fromIndex=items.findIndex(row=>String(row.employee_id)===sourceId);const toIndex=items.findIndex(row=>String(row.employee_id)===employeeId);if(fromIndex<0||toIndex<0||fromIndex===toIndex)return;const sourceCard=Array.from(employeeCards.querySelectorAll('.regular-income-employee-card')).find(row=>row.dataset.employeeId===sourceId);if(!sourceCard)return;const [moved]=items.splice(fromIndex,1);items.splice(toIndex,0,moved);items.forEach((row,rowIndex)=>{row.sort_no=rowIndex+1;});if(fromIndex<toIndex)card.after(sourceCard);else card.before(sourceCard);employeeCards.querySelectorAll('.regular-income-employee-order').forEach((order,rowIndex)=>{order.textContent=String(rowIndex+1);});});
                card.addEventListener('dragleave',()=>card.classList.remove('is-drag-over'));
                card.addEventListener('drop',event=>{event.preventDefault();event.stopPropagation();card.classList.remove('is-drag-over');});
                handle.addEventListener('dragend',()=>{draggedEmployeeId='';dragOverEmployeeId='';card.classList.remove('is-dragging','is-drag-over');renderItems();});
                handle.addEventListener('keydown',event=>{if(!['ArrowUp','ArrowDown'].includes(event.key))return;event.preventDefault();const fromIndex=items.findIndex(row=>String(row.employee_id)===employeeId);const toIndex=event.key==='ArrowUp'?fromIndex-1:fromIndex+1;if(toIndex<0||toIndex>=items.length)return;const [moved]=items.splice(fromIndex,1);items.splice(toIndex,0,moved);items.forEach((row,rowIndex)=>{row.sort_no=rowIndex+1;});renderItems();employeeCards.querySelector(`[data-employee-id="${employeeId}"] .regular-income-employee-drag-handle`)?.focus();});
            }else handle.classList.add('is-disabled');
            const toggleDetail=()=>{if(expanded)closeDetail();else showDetail(employeeId);};
            const select=header.querySelector('.regular-income-employee-select');select.addEventListener('click',toggleDetail);
            if(EDITABLE.has(documentStatus)){const remove=document.createElement('button');remove.type='button';remove.className='regular-income-employee-action is-danger';remove.title='직원 제외';remove.setAttribute('aria-label',remove.title);remove.innerHTML='<i class="fa-regular fa-trash-can" aria-hidden="true"></i>';remove.addEventListener('click',()=>{items=items.filter(row=>String(row.employee_id)!==employeeId);if(String(detail.dataset.employeeId||'')===employeeId)closeDetail();renderItems();});header.append(remove);}
            const fold=document.createElement('button');fold.type='button';fold.className='regular-income-employee-action';fold.title=expanded?'접기':'펼치기';fold.setAttribute('aria-label',fold.title);fold.innerHTML=`<i class="fa-solid fa-chevron-${expanded?'up':'down'}" aria-hidden="true"></i>`;fold.addEventListener('click',toggleDetail);header.append(fold);card.append(header);
            const body=document.createElement('div');body.className='regular-income-employee-card__body';body.innerHTML=`<div><span>계약 지급액</span><strong>${formatAmount(item.contract_pay_amount)}원</strong></div><div><span>추가 지급</span><strong>${formatAmount(item.additional_pay_amount)}원</strong></div><div><span>과세대상</span><strong>${formatAmount(item.taxable_amount)}원</strong></div><div><span>비과세</span><strong>${formatAmount(item.non_taxable_amount)}원</strong></div>${item.calculation_message?`<p>${escapeHtml(item.calculation_message)}</p>`:''}`;card.append(body);
            employeeCards.append(card);
        });
        if(selectedId&&!detail.classList.contains('is-hidden')){
            Array.from(employeeCards.querySelectorAll('.regular-income-employee-card')).find(card=>card.dataset.employeeId===selectedId)?.append(detail);
        }
    }
    function renderCalculationGuidance(){
        const reasons=[];
        if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(yearMonthValue.value))reasons.push('귀속연월을 입력해 주세요.');
        if(!isIncomeWithholdingDate(withholdingDateInput.value))reasons.push('원천징수일을 입력하면 해당 날짜의 법정기준으로 자동 계산됩니다.');
        if(!items.length)reasons.push('계산할 직원을 불러와 주세요.');
        items.forEach(item=>{
            if(!item.deduction_unresolved&&!['NEEDS_CONFIRMATION','BLOCKED'].includes(item.calculation_status_code))return;
            const employee=item.employee_name_snapshot||item.employee_name||'직원 미확인';
            reasons.push(`${employee}: ${item.calculation_message||'세금·보험료 계산기준 또는 필수 입력값을 확인해 주세요.'}`);
        });
        if(calculationError)reasons.unshift(`자동계산 오류: ${calculationError}`);
        const unique=[...new Set(reasons.filter(Boolean))];
        calculationGuidance.classList.toggle('d-none',unique.length===0);
        calculationGuidance.innerHTML=unique.length?`<strong>계산 확인사항</strong><br>${unique.map(reason=>escapeHtml(reason)).join('<br>')}`:'';
    }
    function renderItems(){items=items.map((item,index)=>normalizeItem({...item,sort_no:index+1}));disposeCalculationMessageTooltips();grid.setState({gridId:'regular-employment-income-employees',rows:items.map((values,index)=>({rowId:values.id||values.employee_id||`employee-${index}`,rowState:EDITABLE.has(documentStatus)?'clean':'readonly',values}))});refreshCalculationMessageTooltips();renderEmployeeCards();const totals=items.reduce((r,item)=>({gross:r.gross+number(item.gross_amount),deduction:r.deduction+number(item.confirmed_deduction_amount??item.deduction_amount),net:r.net+number(item.net_payment_amount),unresolved:r.unresolved+(item.deduction_unresolved?1:0)}),{gross:0,deduction:0,net:0,unresolved:0});summary.textContent=totals.unresolved?`대상인원 ${items.length}명 · 총지급액 ${formatAmount(totals.gross)}원 · 확정공제 ${formatAmount(totals.deduction)}원 / 미확정 ${totals.unresolved}명 · 총실지급액 미확정`:`대상인원 ${items.length}명 · 총지급액 ${formatAmount(totals.gross)}원 · 총공제액 ${formatAmount(totals.deduction)}원 · 총실지급액 ${formatAmount(totals.net)}원`;renderCalculationGuidance();}
    function closeDetail(){detail.classList.add('is-hidden');delete detail.dataset.employeeId;linesHost.replaceChildren();basesHost.replaceChildren();renderEmployeeCards();}
    function renderDetailActions(employeeId){
        detail.querySelector('.regular-income-detail-actions')?.remove();
    }
    const recalculationVersions=new Map();
    const nextRecalculationVersion=employeeId=>{const version=(recalculationVersions.get(employeeId)||0)+1;recalculationVersions.set(employeeId,version);return version;};
    async function recalculateEmployee(item,scheduledVersion=null){const employeeId=item.employee_id;const version=scheduledVersion??nextRecalculationVersion(employeeId);const calculatedItems=await calculateItems(form.elements.income_year_month.value,items);if(recalculationVersions.get(employeeId)!==version)return false;items=calculatedItems;renderItems();showDetail(employeeId);renderDetailActions(employeeId);return true;}
    function renderEmployeePreview(item){updateAppliedTotals(item);showDetail(item.employee_id);renderDetailActions(item.employee_id);renderItems();}
    function scheduleRecalculation(item){const version=nextRecalculationVersion(item.employee_id);calculationError='';clearTimeout(recalculationTimers.get(item.employee_id));recalculationTimers.set(item.employee_id,setTimeout(async()=>{recalculationTimers.delete(item.employee_id);try{await recalculateEmployee(item,version);}catch(error){if(recalculationVersions.get(item.employee_id)===version){calculationError=error.message||'자동계산 중 오류가 발생했습니다.';renderCalculationGuidance();notify('error',calculationError);}}},350));}
    const periodText=incomeCalculationPeriodText;
    const roundingText=incomeCalculationRoundingText;
    const settlementMeta=line=>{const parts=String(line.source_key||'').split('|');return parts[0]==='SETTLEMENT'?{parent:parts[1],type:parts[2],period:parts[3]}:null;};
    const isSettlementLine=line=>Boolean(settlementMeta(line)||line.settlement_parent_code);
    const settlementParent=line=>line.settlement_parent_code||settlementMeta(line)?.parent||'';
    const settlementType=line=>line.settlement_type_code||settlementMeta(line)?.type||'';
    const settlementPeriod=line=>line.settlement_period||settlementMeta(line)?.period||'';
    const settlementIdentity=line=>[settlementParent(line),settlementType(line),settlementPeriod(line),String(line.business_reason||'').trim(),number(line.final_amount)].join('|');
    const signedDeductionAmount=line=>isSettlementLine(line)&&settlementType(line)==='REFUND'?-number(line.final_amount):number(line.final_amount);
    function updateAppliedTotals(item){const totals=(item.line_items||[]).reduce((result,row)=>{const amount=number(row.final_amount);if(row.item_type_code==='PAY')result.gross+=(row.pay_effect_code==='DECREASE'?-amount:amount);if(row.item_type_code==='DEDUCTION')result.deduction+=signedDeductionAmount(row);return result;},{gross:0,deduction:0});item.gross_amount=totals.gross;item.deduction_amount=totals.deduction;item.confirmed_deduction_amount=totals.deduction;item.net_payment_amount=totals.gross-totals.deduction;item.unresolved_deduction_count=(item.line_items||[]).filter(row=>row.item_type_code==='DEDUCTION'&&row.final_amount===null).length;}
    function calculationRows(line){
        if(line.item_code==='EMPLOYMENT_INCOME_TAX')return [['과세대상 월급여액',`${formatAmount(line.calculation_basis_amount)}원`],['공제대상 가족수',`${line.dependent_count??'-'}명`],['적용 간이세액표',periodText(line)],['적용 급여구간',`${formatAmount(line.tax_table_salary_from)}원 이상 ~ ${line.tax_table_salary_to===null?'상한 없음':`${formatAmount(line.tax_table_salary_to)}원 미만`}`],['조회',`가족수 ${line.dependent_column_key??'-'}명 열`],['세액표 조회액',`${formatAmount(line.table_tax_amount)}원`]];
        if(line.calculation_rate!==undefined){const rows=[['계산기초',`${formatAmount(line.calculation_basis_amount)}원`],['적용 기준',periodText(line)],['법정요율',`${(number(line.calculation_rate)*100).toLocaleString('ko-KR',{maximumFractionDigits:5})}%`]];if(number(line.minimum_base_amount)>0)rows.push(['기준 하한',`${formatAmount(line.minimum_base_amount)}원`]);if(number(line.maximum_base_amount)>0)rows.push(['기준 상한',`${formatAmount(line.maximum_base_amount)}원`]);rows.push(['계산 전 금액',`${formatAmount(line.calculation_before_rounding)}원`],['끝수처리',roundingText(line)]);return rows;}
        return [];
    }
    const basisFieldByCode={NATIONAL_PENSION:['national_pension_basis_snapshot','국민연금 기준소득월액'],HEALTH_INSURANCE:['health_insurance_basis_snapshot','건강보험 보수월액'],EMPLOYMENT_INSURANCE:['employment_insurance_basis_snapshot','고용보험 산정대상 보수'],EMPLOYMENT_INCOME_TAX:['dependent_count_snapshot','공제대상 가족수']};
    function appendBasisControl(card,line,item){
        const definition=basisFieldByCode[line.item_code];if(!definition)return;
        if(line.item_code==='EMPLOYMENT_INCOME_TAX'){const taxable=document.createElement('div');taxable.className='regular-income-card-basis';taxable.innerHTML=`<span>과세대상 월급여액</span><strong>${formatAmount(item.taxable_amount)}원</strong><small>지급항목에서 자동산출</small>`;card.append(taxable);}
        const [key,labelText]=definition;const isDependent=key==='dependent_count_snapshot';const supportedDependentCounts=isDependent?(item.supported_dependent_counts||[]).map(Number).filter(value=>Number.isInteger(value)&&value>=1).sort((left,right)=>left-right):[];const dependentMin=supportedDependentCounts[0]??1;const dependentMax=supportedDependentCounts.at(-1)??null;const group=document.createElement('div');group.className='regular-income-card-basis';const label=document.createElement('span');label.textContent=labelText;const input=document.createElement('input');input.type=isDependent?'number':'text';input.inputMode='numeric';input.min=isDependent?String(dependentMin):'';input.max=isDependent&&dependentMax!==null?String(dependentMax):'';input.step=isDependent?'1':'';input.className=`form-control form-control-sm${isDependent?'':' number-input text-end'}`;input.value=isDependent&&dependentCountOverrides.has(String(item.employee_id))?dependentCountOverrides.get(String(item.employee_id)):(item[key]??'');input.placeholder=isDependent?String(dependentMin):'자동산출값 사용';input.disabled=!EDITABLE.has(documentStatus);const controls=document.createElement('div');controls.className=`regular-income-card-basis-controls${isDependent?' regular-income-dependent-stepper':''}`;let decrease=null;let increase=null;const updateDependentButtons=()=>{if(!isDependent)return;const value=Number(input.value),index=supportedDependentCounts.indexOf(value);decrease.disabled=input.disabled||!Number.isFinite(value)||(supportedDependentCounts.length?index<=0:value<=dependentMin);increase.disabled=input.disabled||(supportedDependentCounts.length?index<0||index>=supportedDependentCounts.length-1:(dependentMax!==null&&value>=dependentMax));};const syncBasis=()=>{const raw=input.value.trim(),dependentValue=raw===''?null:Number(raw);if(isDependent&&raw!==''&&(!Number.isInteger(dependentValue)||dependentValue<dependentMin||(supportedDependentCounts.length&&!supportedDependentCounts.includes(dependentValue)))){input.setCustomValidity(dependentMax===null?`공제대상 가족수는 ${dependentMin}명 이상이어야 합니다.`:`공제대상 가족수는 간이세액표 지원범위 ${dependentMin}~${dependentMax}명 안에서 입력해 주세요.`);input.reportValidity();updateDependentButtons();return;}input.setCustomValidity('');item[key]=raw===''?null:(isDependent?dependentValue:Math.trunc(nullableNumber(raw)));if(isDependent){const employeeKey=String(item.employee_id);if(item[key]===null)dependentCountOverrides.delete(employeeKey);else dependentCountOverrides.set(employeeKey,item[key]);}updateDependentButtons();scheduleRecalculation(item);};if(isDependent){decrease=document.createElement('button');decrease.type='button';decrease.className='regular-income-dependent-step';decrease.textContent='◀';decrease.setAttribute('aria-label','공제대상 가족수 감소');increase=document.createElement('button');increase.type='button';increase.className='regular-income-dependent-step';increase.textContent='▶';increase.setAttribute('aria-label','공제대상 가족수 증가');const step=delta=>{const current=Number(input.value),index=supportedDependentCounts.indexOf(current);if(supportedDependentCounts.length){const nextIndex=index<0?(delta>0?0:supportedDependentCounts.length-1):Math.min(supportedDependentCounts.length-1,Math.max(0,index+delta));input.value=String(supportedDependentCounts[nextIndex]);}else{const base=Number.isFinite(current)&&current>=dependentMin?current:(delta>0?dependentMin-1:dependentMin);input.value=String(Math.max(dependentMin,dependentMax===null?base+delta:Math.min(dependentMax,base+delta)));}syncBasis();input.focus();};decrease.addEventListener('click',()=>step(-1));increase.addEventListener('click',()=>step(1));input.addEventListener('change',syncBasis);const unit=document.createElement('span');unit.className='regular-income-dependent-unit';unit.textContent='명';controls.append(decrease,input,unit,increase);updateDependentButtons();}else{bindNumberInput(input,{integerOnly:true,onInput:syncBasis,onBlur:syncBasis});controls.append(input);}group.append(label,controls);const hint=document.createElement('small');const resolution=item.basis_resolutions?.[key];hint.textContent=isDependent?(dependentMax===null?'화살표 또는 직접 입력으로 변경하면 근로소득세가 자동계산됩니다.':`간이세액표 지원범위 ${dependentMin}~${dependentMax}명 · 변경 시 자동계산`):resolution?.status_code==='REFERENCE_CONFIRMED'?'확정이력 자동제안':resolution?.status_code==='USER_CONFIRMED'?'사용자 적용값':resolution?.message||'입력하면 자동계산되며, 비워 두면 자동산출값을 사용합니다.';group.append(hint);card.append(group);
    }
    function appendSettlementSection(card,line,item){
        if(line.item_type_code!=='DEDUCTION'||isSettlementLine(line)||line.item_code==='OTHER_DEDUCTION')return;
        const settlements=(item.line_items||[]).filter(candidate=>isSettlementLine(candidate)&&settlementParent(candidate)===line.item_code);
        const section=document.createElement('section');section.className='regular-income-settlements';const title=document.createElement('h6');title.textContent='정산내역';section.append(title);
        const list=document.createElement('div');list.className='regular-income-settlement-list';
        if(!settlements.length){const empty=document.createElement('small');empty.className='text-muted';empty.textContent='현재 없음';list.append(empty);}
        settlements.forEach(settlement=>{const row=document.createElement('div');row.className='regular-income-settlement-row';const text=document.createElement('span');const refund=settlementType(settlement)==='REFUND';text.textContent=`${refund?'환급':'추가징수'} · ${settlementPeriod(settlement)} · ${refund?'-':'+'}${formatAmount(settlement.final_amount)}원 · ${settlement.business_reason||''}`;row.append(text);if(EDITABLE.has(documentStatus)){const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger btn-sm regular-income-settlement-remove';remove.textContent='삭제';remove.addEventListener('click',async()=>{const owner=items.find(candidate=>String(candidate.employee_id)===String(item.employee_id));if(!owner){notify('error','필요한 직원 계산정보를 찾을 수 없습니다.');return;}const identity=settlementIdentity(settlement);const removed=(owner.line_items||[]).find(candidate=>isSettlementLine(candidate)&&settlementIdentity(candidate)===identity);owner.line_items=(owner.line_items||[]).filter(candidate=>candidate!==removed);updateAppliedTotals(owner);showDetail(owner.employee_id);try{await recalculateEmployee(owner);}catch(error){if(removed)owner.line_items=[...(owner.line_items||[]),removed];updateAppliedTotals(owner);showDetail(owner.employee_id);notify('error',error.message||'정산내역 삭제 후 자동계산 중 오류가 발생했습니다.');}});row.append(remove);}list.append(row);});section.append(list);
        if(EDITABLE.has(documentStatus)){const field=(label,control,wide=false,hint='')=>{const wrap=document.createElement('label');wrap.className=`regular-income-settlement-field${wide?' is-wide':''}`;const title=document.createElement('span');title.textContent=label;wrap.append(title,control);if(hint){const help=document.createElement('small');help.textContent=hint;wrap.append(help);}return wrap;};const toggle=document.createElement('button');toggle.type='button';toggle.className='btn btn-outline-primary btn-sm regular-income-settlement-toggle';toggle.textContent='+ 정산 추가';const formWrap=document.createElement('div');formWrap.className='regular-income-settlement-form is-hidden';const type=document.createElement('select');type.className='form-select form-select-sm';type.innerHTML='<option value="ADDITIONAL_COLLECTION">추가징수</option><option value="REFUND">환급</option>';const period=document.createElement('input');period.className='form-control form-control-sm';period.placeholder='예: 2026, 2026-01, 2026-01~2026-03';period.setAttribute('aria-label','대상기간 또는 귀속연도');const amount=document.createElement('input');amount.type='text';amount.inputMode='numeric';amount.className='form-control form-control-sm number-input';amount.placeholder='0';const normalizedSettlementAmount=()=>{const entered=nullableNumber(amount.value);return entered===null?null:Math.trunc(type.value==='REFUND'?Math.abs(entered):entered);};const displaySettlementAmount=()=>{const value=normalizedSettlementAmount();if(value!==null)amount.value=formatAmount(type.value==='REFUND'?-value:Math.abs(value));};bindNumberInput(amount,{integerOnly:true,onBlur:displaySettlementAmount});type.addEventListener('change',displaySettlementAmount);const reason=document.createElement('input');reason.className='form-control form-control-sm';reason.placeholder='정산 사유 입력';const add=document.createElement('button');add.type='button';add.className='btn btn-primary btn-sm';add.textContent='추가';toggle.addEventListener('click',()=>formWrap.classList.toggle('is-hidden'));add.addEventListener('click',async()=>{const value=normalizedSettlementAmount();if(!/^\d{4}(?:-\d{2})?(?:~\d{4}(?:-\d{2})?)?$/.test(period.value.trim())||value===null||!Number.isFinite(value)||value<=0||!reason.value.trim()){notify('warning','정산 구분, 대상기간, 금액과 사유를 확인해 주세요. 대상기간은 2026, 2026-01 또는 2026-01~2026-03 형식으로 입력합니다.');return;}const owner=items.find(candidate=>String(candidate.employee_id)===String(item.employee_id));if(!owner){notify('error','필요한 직원 계산정보를 찾을 수 없습니다.');return;}const token=Date.now();const pending={item_type_code:'DEDUCTION',item_code:`SET_${line.item_code}_${type.value==='REFUND'?'R':'A'}_${token}`,item_name_snapshot:`${line.item_name_snapshot} 정산`,calculated_amount:value,adjustment_amount:0,final_amount:value,calculation_source_code:'MANUAL',business_source_code:'MANUAL',source_key:`SETTLEMENT|${line.item_code}|${type.value}|${period.value.trim()}|${token}`,business_reason:reason.value.trim(),settlement_parent_code:line.item_code,settlement_type_code:type.value,settlement_period:period.value.trim()};owner.line_items=[...(owner.line_items||[]),pending];updateAppliedTotals(owner);showDetail(owner.employee_id);try{await recalculateEmployee(owner);}catch(error){notify('error',error.message||'정산내역 자동계산 중 오류가 발생했습니다. 입력한 정산내역은 화면에 유지됩니다.');}});formWrap.append(field('정산 구분',type),field('대상기간',period,false,'연도·귀속월·기간 입력'),field('금액',amount),field('사유',reason,true),add);section.append(toggle,formWrap);}
        const final=document.createElement('div');final.className='regular-income-deduction-final';final.innerHTML=`<span>최종 공제</span><strong>${formatAmount(number(line.final_amount)+settlements.reduce((total,settlement)=>total+signedDeductionAmount(settlement),0))}원</strong>`;section.append(final);card.append(section);
    }
    function appendPayEffectEditor(card,item){
        if(!EDITABLE.has(documentStatus))return;
        item.pay_adjustment_drafts=Array.isArray(item.pay_adjustment_drafts)?item.pay_adjustment_drafts:[];
        const section=document.createElement('section');section.className='regular-income-pay-effect-editor';section.dataset.employeeId=String(item.employee_id);
        const lineComponentCode=line=>{const parts=String(line.source_key||'').split('|');return String(parts[0]==='PAY_COMPONENT'?parts[1]:line.item_code||'').trim().toUpperCase();};
        const optionCode=option=>String(option?.meta?.component_code||'').trim().toUpperCase();
        const positiveLines=()=>(item.line_items||[]).filter(line=>line.item_type_code==='PAY'&&line.pay_effect_code!=='DECREASE'&&number(line.final_amount)>0);
        const storedDecreases=()=>(item.line_items||[]).filter(line=>line.item_type_code==='PAY'&&line.pay_effect_code==='DECREASE');
        const availableAmount=(draft,option)=>{const code=optionCode(option);if(!code)return 0;const otherDraftDecreases=item.pay_adjustment_drafts.filter(candidate=>candidate!==draft&&candidate.pay_effect_code==='DECREASE');if(code==='OTHER_PAY'){const positive=positiveLines().filter(line=>number(line.taxable_flag)===1).reduce((total,line)=>total+number(line.final_amount),0);const stored=storedDecreases().filter(line=>number(line.taxable_flag)===1).reduce((total,line)=>total+number(line.final_amount),0);const pending=otherDraftDecreases.filter(candidate=>number(candidate.taxable_flag)===1).reduce((total,candidate)=>total+number(candidate.final_amount),0);return Math.max(0,positive-stored-pending);}const positive=positiveLines().filter(line=>lineComponentCode(line)===code).reduce((total,line)=>total+number(line.final_amount),0);const stored=storedDecreases().filter(line=>lineComponentCode(line)===code).reduce((total,line)=>total+number(line.final_amount),0);const pending=otherDraftDecreases.filter(candidate=>String(candidate.item_code||'').toUpperCase()===code).reduce((total,candidate)=>total+number(candidate.final_amount),0);return Math.max(0,positive-stored-pending);};
        const candidateOptions=(draft,allOptions)=>{if(draft.pay_effect_code==='DECREASE')return allOptions.filter(option=>optionCode(option)==='OTHER_PAY'||availableAmount(draft,option)>0).map(option=>optionCode(option)==='OTHER_PAY'?option:{...option,label:`${option.label} (감액가능 ${formatAmount(availableAmount(draft,option))}원)`});const existing=new Set(positiveLines().map(lineComponentCode));const pending=new Set(item.pay_adjustment_drafts.filter(candidate=>candidate!==draft&&candidate.pay_effect_code==='INCREASE'&&String(candidate.item_code||'').toUpperCase()!=='OTHER_PAY').map(candidate=>String(candidate.item_code||'').toUpperCase()));return allOptions.filter(option=>optionCode(option)==='OTHER_PAY'||(!existing.has(optionCode(option))&&!pending.has(optionCode(option))));};
        const removeDraft=draft=>{const owner=items.find(candidate=>String(candidate.employee_id)===String(item.employee_id));if(!owner)throw new Error('필요한 직원 계산정보를 찾을 수 없습니다.');owner.pay_adjustment_drafts=(owner.pay_adjustment_drafts||[]).filter(candidate=>String(candidate.client_key)!==String(draft.client_key));if(!showDetail(owner.employee_id)||!linesHost.querySelector(`button[data-action="pay-adjustment-add"][data-employee-id="${owner.employee_id}"]`))throw new Error('증감 입력행을 삭제하지 못했습니다.');};
        const renderDraft=draft=>{
            const fields=document.createElement('article');fields.className='regular-income-manual-pay regular-income-adjustment-draft';fields.dataset.clientKey=draft.client_key;
            const effect=document.createElement('select');effect.className='form-select form-select-sm';effect.setAttribute('aria-label','증감 구분');effect.innerHTML='<option value="INCREASE">증액</option><option value="DECREASE">감액</option>';effect.value=draft.pay_effect_code||'INCREASE';effect.addEventListener('change',()=>{draft.pay_effect_code=effect.value;draft.source_reference_id=null;draft.item_code=null;draft.item_name_snapshot=null;draft.default_tax_type=null;draft.tax_policy_code=null;draft.taxable_flag=null;draft.tax_label='';draft.final_amount=null;showDetail(item.employee_id);});
            const component=document.createElement('select');component.className='form-select form-select-sm';component.setAttribute('aria-label','급여항목 선택');component.disabled=true;component.replaceChildren(new Option('급여항목 불러오는 중',''));
            const amount=document.createElement('input');amount.type='text';amount.inputMode='numeric';amount.className='form-control form-control-sm number-input';amount.placeholder='금액';amount.value=draft.final_amount??'';const normalizedAmount=()=>{const entered=nullableNumber(amount.value);return entered===null?null:Math.trunc(effect.value==='DECREASE'?Math.abs(entered):entered);};const validateAmount=()=>{const option=options.find(candidate=>String(candidate.value)===String(component.value||draft.source_reference_id));const value=normalizedAmount();const limit=effect.value==='DECREASE'&&option?availableAmount(draft,option):null;amount.setCustomValidity(limit!==null&&value!==null&&value>limit?`감액 가능금액 ${formatAmount(limit)}원을 초과할 수 없습니다.`:'');};bindNumberInput(amount,{integerOnly:true,onInput:()=>{draft.final_amount=normalizedAmount();validateAmount();},onBlur:()=>{draft.final_amount=normalizedAmount();if(effect.value==='DECREASE'&&draft.final_amount!==null)amount.value=formatAmount(-draft.final_amount);validateAmount();}});
            const taxable=document.createElement('span');taxable.className='regular-income-tax-policy regular-income-tax-badge';taxable.textContent=draft.tax_label||'미선택';taxable.setAttribute('aria-label','과세 구분');
            const reason=document.createElement('input');reason.className='form-control form-control-sm';reason.placeholder='사유';reason.value=draft.business_reason||'';reason.addEventListener('input',()=>{draft.business_reason=reason.value;});
            const status=document.createElement('small');status.className='regular-income-pay-option-status text-muted';status.textContent='지급항목을 불러오는 중입니다.';
            const retry=document.createElement('button');retry.type='button';retry.className='btn btn-outline-warning btn-sm is-hidden';retry.textContent='다시 시도';
            const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger btn-sm';remove.textContent='삭제';remove.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();try{AdminPicker.destroySelect2(effect);AdminPicker.destroySelect2(component);removeDraft(draft);}catch(error){notify('error',error.message||'증감 입력행을 삭제하지 못했습니다.');}});
            const complete=document.createElement('button');complete.type='button';complete.className='btn btn-primary btn-sm';complete.textContent='완료';
            let options=[];let pickerReady=false;
            const selected=()=>options.find(option=>String(option.value)===String(component.value));
            const syncComponent=()=>{const option=selected();const taxType=String(option?.meta?.default_tax_type||'').toUpperCase();const taxLabel=option?.meta?.tax_label||({TAXABLE:'과세',NON_TAXABLE:'비과세',POLICY_CALCULATED:'정책 적용'}[taxType]||'');const other=optionCode(option)==='OTHER_PAY';draft.source_reference_id=option?.value||null;draft.item_code=option?.meta?.component_code||null;draft.item_name_snapshot=option?.meta?.component_name||option?.label||null;draft.default_tax_type=taxType||null;draft.tax_policy_code=option?.meta?.tax_policy_code||null;draft.taxable_flag=option?Number(option.meta?.taxable_flag||0):null;draft.tax_label=taxLabel;taxable.textContent=draft.tax_label||'미선택';taxable.classList.toggle('is-taxable',taxType==='TAXABLE');taxable.classList.toggle('is-non-taxable',taxType==='NON_TAXABLE');taxable.classList.toggle('is-policy',taxType==='POLICY_CALCULATED');taxable.title=draft.tax_policy_code?`${draft.tax_label} · ${draft.tax_policy_code}`:(draft.tax_label||'급여항목을 선택하면 과세정책이 자동 적용됩니다.');reason.required=other;reason.placeholder=other?'사유 (필수)':'사유 (선택)';validateAmount();};
            const bindComponentChange=()=>{const $component=window.jQuery?.(component);if($component){$component.off('.regularIncomeTaxPolicy').on('select2:select.regularIncomeTaxPolicy select2:clear.regularIncomeTaxPolicy change.regularIncomeTaxPolicy',()=>syncComponent());return;}component.addEventListener('change',syncComponent);};
            const hydrate=async force=>{component.disabled=true;retry.classList.add('is-hidden');status.className='regular-income-pay-option-status text-muted';status.textContent='급여항목을 불러오는 중입니다.';try{const allOptions=await loadPayComponentOptions(force);if(!fields.isConnected)return;options=candidateOptions(draft,allOptions);if(!options.length)throw new Error('선택할 수 있는 급여항목이 없습니다.');const selectedStillValid=options.some(option=>String(option.value)===String(draft.source_reference_id));if(draft.source_reference_id&&!selectedStillValid){draft.source_reference_id=null;draft.item_code=null;draft.item_name_snapshot=null;draft.default_tax_type=null;draft.tax_policy_code=null;draft.taxable_flag=null;draft.tax_label='';draft.final_amount=null;}if(effect.value==='DECREASE'&&!draft.source_reference_id){const other=options.find(option=>optionCode(option)==='OTHER_PAY');if(other)draft.source_reference_id=other.value;}component.replaceChildren(new Option('급여항목 선택',''),...options.map(option=>new Option(option.label,option.value)));component.value=draft.source_reference_id||'';component.disabled=false;if(!pickerReady){AdminPicker.select2(component,{width:'100%',allowClear:true,placeholder:'급여항목 선택',dropdownParent:window.jQuery?.(document.getElementById('regularIncomeModal'))});bindComponentChange();pickerReady=true;}syncComponent();status.textContent='';}catch(error){if(!fields.isConnected)return;component.replaceChildren(new Option('급여항목 조회 실패',''));component.disabled=true;status.className='regular-income-pay-option-status text-danger';status.textContent=error.message||'급여항목을 불러오지 못했습니다.';retry.classList.remove('is-hidden');}};
            retry.addEventListener('click',()=>{void hydrate(true);});
            complete.addEventListener('click',async()=>{let pendingLine=null;let owner=null;try{const option=selected();const value=normalizedAmount();const limit=option&&effect.value==='DECREASE'?availableAmount(draft,option):null;const reasonRequired=optionCode(option)==='OTHER_PAY';if(!option||value===null||!Number.isFinite(value)||value<=0){notify('warning','증감 구분, 급여항목과 금액을 확인해 주세요.');return;}if(reasonRequired&&!reason.value.trim()){reason.focus();notify('warning','기타 급여항목은 사유를 입력해 주세요.');return;}if(limit!==null&&value>limit){notify('warning',`감액 가능금액 ${formatAmount(limit)}원을 초과할 수 없습니다.`);return;}complete.disabled=true;pendingLine={item_type_code:'PAY',pay_effect_code:effect.value,item_code:`ADJ_${draft.client_key.replaceAll('-','')}`,item_name_snapshot:option.meta?.component_name||option.label,taxable_flag:Number(option.meta?.taxable_flag||0),calculated_amount:value,adjustment_amount:0,final_amount:value,calculation_source_code:'MANUAL',business_source_code:'MANUAL',source_reference_id:option.value,source_key:`PAY_COMPONENT|${option.meta?.component_code||''}|${effect.value}|${draft.client_key}`,business_reason:reason.value.trim()||null};owner=items.find(candidate=>String(candidate.employee_id)===String(item.employee_id));if(!owner)throw new Error('필요한 직원 계산정보를 찾을 수 없습니다.');owner.line_items=[...(owner.line_items||[]),pendingLine];owner.pay_adjustment_drafts=(owner.pay_adjustment_drafts||[]).filter(candidate=>candidate.client_key!==draft.client_key);AdminPicker.destroySelect2(effect);AdminPicker.destroySelect2(component);fields.remove();card.insertBefore(createLineCard(pendingLine,owner),section);if(!section.querySelector('[data-action="pay-adjustment-add"]')){const addCard=document.createElement('button');addCard.type='button';addCard.className='regular-income-add-card';addCard.dataset.action='pay-adjustment-add';addCard.dataset.employeeId=String(owner.employee_id);addCard.innerHTML='<strong>+</strong><span>증감 추가</span>';section.append(addCard);}renderEmployeePreview(owner);await recalculateEmployee(owner);}catch(error){if(owner&&pendingLine){const current=items.find(candidate=>String(candidate.employee_id)===String(owner.employee_id))||owner;current.line_items=(current.line_items||[]).filter(candidate=>String(candidate.item_code)!==String(pendingLine.item_code));current.pay_adjustment_drafts=[...(current.pay_adjustment_drafts||[]),draft];renderEmployeePreview(current);}else complete.disabled=false;notify('error',error.message||'증감 완료 처리 중 오류가 발생했습니다.');}});
            fields.append(effect,component,amount,taxable,reason,remove,complete,status,retry);section.append(fields);AdminPicker.select2(effect,{width:'100%',minimumResultsForSearch:Infinity,dropdownParent:window.jQuery?.(document.getElementById('regularIncomeModal'))});void hydrate(false);
        };
        item.pay_adjustment_drafts.forEach(renderDraft);
        if(item.pay_adjustment_drafts.length===0){const open=document.createElement('button');open.type='button';open.className='regular-income-add-card';open.dataset.action='pay-adjustment-add';open.dataset.employeeId=String(item.employee_id);open.innerHTML='<strong>+</strong><span>증감 추가</span>';section.append(open);}card.append(section);
    }
    const payTaxLabel=line=>Number(line.taxable_flag)===1?'과세':'비과세';
    const payTaxClass=line=>Number(line.taxable_flag)===1?'is-taxable':'is-non-taxable';
    function createLineCard(line,item,employerLine=null){
        const sharedLine=incomeCalculationLineDto(line);
        const card=document.createElement('article');card.className=`regular-income-line-card regular-income-line-${String(line.item_code||'').toLowerCase()}`;
        const heading=document.createElement('h6');heading.className='regular-income-line-title';heading.append(document.createTextNode(line.item_name_snapshot));if(line.item_type_code==='PAY'){const taxBadge=document.createElement('span');taxBadge.className=`regular-income-pay-tax-badge ${payTaxClass(line)}`;taxBadge.textContent=payTaxLabel(line);heading.append(taxBadge);}card.append(heading);if(line.item_type_code==='PAY'&&['INCREASE','DECREASE'].includes(line.pay_effect_code)){const effectBadge=document.createElement('span');effectBadge.className=`regular-income-pay-effect-badge is-${line.pay_effect_code.toLowerCase()}`;effectBadge.textContent=line.pay_effect_code==='DECREASE'?'감액':'증액';card.append(effectBadge);}if(line.item_type_code==='PAY'&&['INCREASE','DECREASE'].includes(line.pay_effect_code)&&line.business_source_code==='MANUAL'&&EDITABLE.has(documentStatus)){const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger btn-sm';remove.classList.add('regular-income-line-remove');remove.textContent='×';remove.setAttribute('aria-label',`${line.item_name_snapshot} 증감 카드 삭제`);remove.title='증감 카드 삭제';remove.addEventListener('click',async event=>{event.preventDefault();event.stopPropagation();const owner=items.find(candidate=>String(candidate.employee_id)===String(item.employee_id));if(!owner){notify('error','필요한 직원 계산정보를 찾을 수 없습니다.');return;}const removed=(owner.line_items||[]).find(candidate=>String(candidate.item_code)===String(line.item_code)&&String(candidate.source_key||'')===String(line.source_key||''));owner.line_items=(owner.line_items||[]).filter(candidate=>candidate!==removed);renderEmployeePreview(owner);try{await recalculateEmployee(owner);}catch(error){const current=items.find(candidate=>String(candidate.employee_id)===String(owner.employee_id))||owner;if(removed)current.line_items=[...(current.line_items||[]),removed];renderEmployeePreview(current);notify('error',error.message||'증감 삭제 후 자동계산 중 오류가 발생했습니다.');}});card.append(remove);}
        appendBasisControl(card,line,item);
        const facts=document.createElement('dl');facts.className='regular-income-calculation-facts';calculationRows(line).forEach(([label,value])=>{const dt=document.createElement('dt');dt.textContent=label;const dd=document.createElement('dd');dd.textContent=value;facts.append(dt,dd);});if(facts.children.length)card.append(facts);
        const signedPayAmount=value=>line.item_type_code==='PAY'&&line.pay_effect_code==='DECREASE'?-Math.abs(number(value)):value;const isInsurance=['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'].includes(line.item_code);const amounts=document.createElement('div');amounts.className='regular-income-applied-amounts';const auto=document.createElement('div');const autoLabel=document.createElement('span');autoLabel.className='regular-income-auto-label';autoLabel.append(document.createTextNode('자동계산'));if(isInsurance&&EDITABLE.has(documentStatus)){const reset=document.createElement('button');reset.type='button';reset.className='regular-income-auto-apply';reset.textContent='적용';reset.setAttribute('aria-label','자동계산 금액 적용');reset.addEventListener('click',()=>{if(line.calculated_amount===null)return;input.value=formatAmount(line.calculated_amount);line.final_amount=Math.trunc(number(line.calculated_amount));line.adjustment_amount=0;line.source_key=`INSURANCE_OVERRIDE_RESET|${line.item_code}|${form.elements.income_year_month.value}`;line.calculation_source_code='CALCULATED';line.override_inherited=false;reason.value='';sync();});autoLabel.append(reset);}const autoAmount=document.createElement('strong');autoAmount.textContent=line.calculated_amount===null?'계산기준 미확정':`${formatAmount(signedPayAmount(line.calculated_amount))}원`;auto.append(autoLabel,autoAmount);amounts.append(auto);
        const applied=document.createElement('label');applied.innerHTML='<span>적용금액</span>';const input=document.createElement('input');input.type='text';input.inputMode='numeric';input.className='form-control form-control-sm number-input regular-income-applied-amount-input';input.value=line.final_amount===null||line.final_amount===undefined?'':signedPayAmount(line.final_amount);input.disabled=!EDITABLE.has(documentStatus);applied.append(input);amounts.append(applied);card.append(amounts);
        const reasonWrap=document.createElement('label');reasonWrap.className='regular-income-adjustment-reason';const reasonLabel=document.createElement('span');reasonLabel.textContent=isInsurance?'적용사유 *':'조정사유 *';const reason=document.createElement('input');reason.className='form-control form-control-sm';reason.placeholder=isInsurance?'자동계산액과 다른 금액을 적용하는 이유':'자동계산과 다른 금액을 적용하는 이유';reason.value=line.adjustment_reason||'';reason.disabled=!EDITABLE.has(documentStatus);reasonWrap.append(reasonLabel,reason);card.append(reasonWrap);
        if(isInsurance&&line.override_inherited){const inherited=document.createElement('small');inherited.className='regular-income-inherited-override';inherited.textContent=`${line.override_origin_month} 저장 설정 적용 · ${line.adjustment_reason||'적용사유 확인 필요'}`;card.append(inherited);}
        const status=document.createElement('small');status.className='regular-income-status';card.append(status);const warning=document.createElement('small');warning.className='text-warning';card.append(warning);
        const sync=()=>{const enteredAmount=nullableNumber(input.value),appliedAmount=enteredAmount===null?null:Math.trunc(line.item_type_code==='PAY'&&line.pay_effect_code==='DECREASE'?Math.abs(enteredAmount):enteredAmount),calculated=nullableNumber(line.calculated_amount);line.final_amount=appliedAmount;line.adjustment_amount=appliedAmount===null||calculated===null?null:appliedAmount-Math.trunc(calculated);const changed=calculated===null||number(line.adjustment_amount)!==0;reasonWrap.classList.toggle('is-hidden',!changed);reason.required=changed;line.adjustment_reason=changed?(reason.value.trim()||null):null;if(isInsurance&&changed&&!String(line.source_key||'').startsWith('INSURANCE_OVERRIDE|'))line.source_key=`INSURANCE_OVERRIDE|${line.item_code}|${form.elements.income_year_month.value}`;line.calculation_source_code=calculated===null?'HISTORICAL_IMPORT':(changed?'MANUAL':'CALCULATED');const hasReason=Boolean(line.adjustment_reason);line.calculation_status_code=calculated===null?'NOT_VERIFIABLE':(changed&&!hasReason?'WARNING':'CALCULATED');status.textContent=calculated===null?'계산 오류':(line.override_inherited?'계승 적용':(changed?(hasReason?'수동 적용':'확인 필요'):'자동계산'));status.className=`regular-income-status ${calculated===null||changed&&!hasReason?'is-warning':'is-valid'}`;warning.textContent=calculated===null?(line.calculation_message||'자동계산 기준이 미확정이므로 적용금액과 사유를 입력해 주세요.'):(changed?`자동계산액과 적용금액이 ${formatAmount(Math.abs(number(line.adjustment_amount)))}원 다릅니다.`:'');updateAppliedTotals(item);renderItems();};
        bindNumberInput(input,{integerOnly:true,onInput:()=>{sync();if(line.item_type_code==='PAY')scheduleRecalculation(item);},onBlur:sync});reason.addEventListener('input',sync);sync();
        appendSettlementSection(card,line,item);
        if(employerLine){const employerSection=document.createElement('section');employerSection.className='regular-income-employer-burden';const employerTitle=document.createElement('h6');employerTitle.textContent='회사부담';const employerAmount=document.createElement('strong');employerAmount.textContent=employerLine.calculated_amount===null?'계산기준 미확정':`${formatAmount(employerLine.calculated_amount)}원`;const employerHint=document.createElement('small');employerHint.textContent='동일 소득·보수월액 기준 자동계산';employerSection.append(employerTitle,employerAmount,employerHint);card.append(employerSection);}
        return card;
    }
    const deductionDisplayOrder=['EMPLOYMENT_INCOME_TAX','LOCAL_INCOME_TAX','NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','LONG_TERM_CARE_INSURANCE','EMPLOYMENT_INSURANCE','OTHER_DEDUCTION'];
    const institutionLineOrder=line=>{const index=INCOME_INSTITUTION_CARDS.findIndex(definition=>definition.aliases.includes(line.item_code));return index<0?INCOME_INSTITUTION_CARDS.length:index;};
    const sortInstitutionLines=lines=>lines.map((line,index)=>({line,index})).sort((left,right)=>institutionLineOrder(left.line)-institutionLineOrder(right.line)||left.index-right.index).map(entry=>entry.line);
    const payEffectDisplayOrder={CONTRACT_BASE:0,INCREASE:1,DECREASE:2};
    function detailLineOrder(type,line){
        if(type==='PAY')return payEffectDisplayOrder[line.pay_effect_code]??99;
        if(type==='DEDUCTION'){const index=deductionDisplayOrder.indexOf(line.item_code);return index<0?99:index;}
        return 0;
    }
    function showDetail(employeeId){
        const item=items.find(entry=>String(entry.employee_id)===String(employeeId));if(!item){notify('error','필요한 직원 계산정보를 찾을 수 없습니다.');return false;}
        detail.dataset.employeeId=String(item.employee_id||'');
        detailTitle.textContent=`${item.employee_name_snapshot} 직원 상세`;linesHost.replaceChildren();
        const paySection=document.createElement('section');paySection.className='regular-income-detail-group';const payTitle=document.createElement('h6');payTitle.textContent='지급항목';paySection.append(payTitle);const payCards=document.createElement('div');payCards.className='regular-income-card-grid regular-income-pay-grid';const payLines=(item.line_items||[]).filter(line=>line.item_type_code==='PAY'&&!isSettlementLine(line)).map((line,index)=>({line,index})).sort((left,right)=>detailLineOrder('PAY',left.line)-detailLineOrder('PAY',right.line)||left.index-right.index).map(entry=>entry.line);if(payLines.length){payLines.forEach(line=>payCards.append(createLineCard(line,item)));appendPayEffectEditor(payCards,item);}else{const empty=document.createElement('p');empty.className='text-muted small';empty.textContent='현재 없음';payCards.append(empty);}paySection.append(payCards);linesHost.append(paySection);
        const institutionSection=document.createElement('section');institutionSection.className='regular-income-detail-group';const institutionTitle=document.createElement('h6');institutionTitle.textContent='기관별 계산 상세';const institutionCards=document.createElement('div');institutionSection.append(institutionTitle,institutionCards);linesHost.append(institutionSection);
        const institutionDto=incomeInstitutionCardsDto((item.line_items||[]).filter(line=>!isSettlementLine(line)),{
            editable:line=>EDITABLE.has(documentStatus)&&['DEDUCTION','EMPLOYER_BURDEN'].includes(line.item_type_code),
            mapLine:(result,line)=>({...result,
                standardLabel:line?periodText(line):'',
                rateLabel:result.key==='INCOME_TAX'?'간이세액표':null,
                roundingLabel:result.key==='INCOME_TAX'?'간이세액표 산출기준':(line?roundingText(line):''),
            }),
        });
        renderIncomeCalculationCards(institutionCards,institutionDto,{onChange:(code,finalAmount,reason,editor)=>{const dtoLine=institutionDto.lines.find(line=>line.key===code);const line=(item.line_items||[]).find(row=>row.item_type_code===dtoLine?.sourceType&&row.item_code===dtoLine?.sourceCode);if(!line)return;const calculated=line.calculated_amount;const changed=calculated===null?Math.abs(finalAmount)>=.01:Math.abs(finalAmount-number(calculated))>=.01;const error=changed&&!reason?'자동계산액과 다른 적용금액에는 적용사유가 필요합니다.':'';editor.classList.toggle('is-invalid',Boolean(error));editor.querySelector('.invalid-feedback').textContent=error;line.final_amount=finalAmount;line.adjustment_amount=calculated===null?0:finalAmount-number(calculated);line.adjustment_reason=reason||null;line.source_key=`INSTITUTION_OVERRIDE|${line.item_type_code}|${line.item_code}|${form.elements.income_year_month.value}`;line.business_source_code='MANUAL';updateAppliedTotals(item);renderItems();scheduleRecalculation(item);}});
        const adjustments=(item.line_items||[]).filter(line=>!isSettlementLine(line)&&Math.abs(number(line.adjustment_amount))>=.01);
        const itemIndex=Number(item.sort_no)||items.indexOf(item)+1;
        const positionName=String(item.position_name_snapshot||'직책 미확인');
        const deductionLines=sortInstitutionLines((item.line_items||[]).filter(line=>line.item_type_code==='DEDUCTION'));
        const employerLines=sortInstitutionLines((item.line_items||[]).filter(line=>line.item_type_code==='EMPLOYER_BURDEN'));
        const employmentEmployerLines=employerLines.filter(line=>['EMPLOYMENT_INSURANCE','EMPLOYMENT_INSURANCE_VOCATIONAL'].includes(line.item_code));
        const employmentEmployerAmount=!employmentEmployerLines.length||employmentEmployerLines.some(line=>line.final_amount===null||line.final_amount===undefined)
            ? null
            : employmentEmployerLines.reduce((total,line)=>total+number(line.final_amount),0);
        const employerSummaryDefinitions=INCOME_INSTITUTION_CARDS.filter(definition=>!definition.employeeOnly&&definition.key!=='EMPLOYMENT_INSURANCE_VOCATIONAL');
        const employerSummaryLines=employerSummaryDefinitions.map(definition=>{
            if(definition.key==='EMPLOYMENT_INSURANCE')return{item_code:'EMPLOYMENT_INSURANCE',item_name_snapshot:'고용보험 사용자부담',final_amount:employmentEmployerAmount};
            const source=employerLines.find(line=>definition.aliases.includes(line.item_code));
            return{...(source||{}),item_code:source?.item_code||definition.aliases[0],item_name_snapshot:`${definition.name} 사용자부담`,final_amount:source?.final_amount??null};
        });
        const summaryRow=(label,value,strong=false,className='')=>`<div class="${className}"><span>${escapeHtml(label)}</span><${strong?'strong':'b'}>${value}</${strong?'strong':'b'}></div>`;
        const summaryLineRows=(rows,signed=false)=>rows.length?rows.map(line=>summaryRow(line.item_name_snapshot||line.item_code||'항목',line.final_amount===null?'미확정':`${formatAmount(signed?signedDeductionAmount(line):line.final_amount)}원`)).join(''):summaryRow('보험료','미확정');
        const final=document.createElement('aside');
        final.className='regular-income-final-summary';
        final.innerHTML=`<h6>${itemIndex}. ${escapeHtml(item.employee_name_snapshot||'상용근로자')} <span class="badge text-bg-secondary">${escapeHtml(positionName)}</span></h6><section class="regular-income-result-section"><div class="regular-income-result-section-title">근무·지급</div><div class="regular-income-result-values">${summaryRow('과세대상',`${formatAmount(item.taxable_amount)}원`)}${summaryRow('비과세',`${formatAmount(item.non_taxable_amount)}원`)}${summaryRow('지급액(세전)',`${formatAmount(item.gross_amount)}원`,true)}</div></section><section class="regular-income-result-section"><div class="regular-income-result-section-title">세금·근로자 부담</div><div class="regular-income-result-values">${summaryLineRows(deductionLines,true)}${summaryRow('총 원천징수',item.deduction_amount===null?'미확정':`${formatAmount(item.deduction_amount)}원`,true)}</div></section><section class="regular-income-result-section"><div class="regular-income-result-section-title">사용자 부담</div><div class="regular-income-result-values">${summaryLineRows(employerSummaryLines)}${summaryRow('사용자부담 합계',employerLines.length?`${formatAmount(item.employer_burden_amount)}원`:'미확정',true)}</div></section><section class="regular-income-result-section is-final"><div class="regular-income-result-section-title">최종 지급</div><div class="regular-income-result-values">${summaryRow('지급액(세전)',`${formatAmount(item.gross_amount)}원`)}${summaryRow('공제 합계',item.deduction_amount===null?'미확정':`${formatAmount(item.deduction_amount)}원`)}${summaryRow('실지급액(세후)',item.net_payment_amount===null?'미확정':`${formatAmount(item.net_payment_amount)}원`,true,'is-net-payment')}</div></section>${adjustments.length?`<div class="regular-income-adjustment-summary"><strong>조정내역</strong>${adjustments.map(line=>`<span>${escapeHtml(line.item_name_snapshot)}: 자동 ${formatAmount(line.calculated_amount)} / 적용 ${formatAmount(line.final_amount)} / 차이 ${number(line.adjustment_amount)>0?'+':''}${formatAmount(line.adjustment_amount)}</span>`).join('')}</div>`:''}`;
        linesHost.append(final);basesHost.replaceChildren();detail.classList.remove('is-hidden');renderEmployeeCards();return true;
    }
    if(linesHost.dataset.payAdjustmentActionBound!=='true'){linesHost.dataset.payAdjustmentActionBound='true';linesHost.addEventListener('click',event=>{const button=event.target.closest('button[data-action="pay-adjustment-add"]');if(!button||!linesHost.contains(button))return;try{if(!EDITABLE.has(documentStatus))throw new Error('현재 문서상태에서는 증감 카드를 추가할 수 없습니다.');const employeeId=String(button.dataset.employeeId||'').trim();if(!employeeId)throw new Error('필요한 직원 계산정보를 찾을 수 없습니다.');const item=items.find(entry=>String(entry.employee_id)===employeeId);if(!item)throw new Error('필요한 직원 계산정보를 찾을 수 없습니다.');const section=button.closest('.regular-income-pay-effect-editor');if(!section||String(section.dataset.employeeId)!==employeeId)throw new Error('지급항목 영역을 찾을 수 없습니다.');item.pay_adjustment_drafts=Array.isArray(item.pay_adjustment_drafts)?item.pay_adjustment_drafts:[];const draft=createPayAdjustmentDraft();item.pay_adjustment_drafts.push(draft);if(!showDetail(employeeId))throw new Error('증감 카드를 생성하지 못했습니다.');if(!linesHost.querySelector(`.regular-income-adjustment-draft[data-client-key="${draft.client_key}"]`))throw new Error('증감 카드를 생성하지 못했습니다.');}catch(error){notify('error',error.message||'증감 카드를 생성하지 못했습니다.');}});}

    SearchForm({table,apiList:API.LIST,tableId:'regularIncome',defaultSearchField:'income_year_month'});table.on('init.dt draw.dt xhr.dt',()=>{document.getElementById('regularIncomeCount').textContent=`총 ${table.page.info()?.recordsDisplay??0}건`;});document.querySelector('#regular-income-table tbody')?.addEventListener('dblclick',event=>{const row=table.row(event.target.closest('tr')).data();if(row?.id)openDetail(row.id);});document.addEventListener('click',event=>{const button=event.target.closest('[data-detail]');if(!button)return;const row=table.row(button.closest('tr')).data();if(row?.id)openDetail(row.id);});
    function setReadonly(readonly){Array.from(form.elements).forEach(control=>{if(!['hidden'].includes(String(control.type).toLowerCase()))control.disabled=readonly;});const yearMonthIcon=document.getElementById('regularIncomeYearMonthButton');yearMonthIcon.setAttribute('aria-disabled',readonly?'true':'false');yearMonthIcon.tabIndex=readonly?-1:0;form.querySelectorAll('[data-bs-dismiss="modal"], [data-ui-modal-card-collapse]').forEach(control=>{control.disabled=false;});syncWorkflowActions();}
    function syncWorkflowActions(){
        const editable=EDITABLE.has(documentStatus);
        const calculationReady=isIncomeWithholdingDate(withholdingDateInput.value)&&items.every(item=>!['NEEDS_CONFIRMATION','BLOCKED'].includes(item.calculation_status_code)&&item.deduction_amount!==null&&item.net_payment_amount!==null);
        const requestId=String(form.elements.current_approval_request_id.value||'').trim();
        const withdrawable=documentStatus==='PENDING'&&requestId!=='';
        const submit=document.getElementById('regularIncomeSubmit');
        const withdraw=document.getElementById('regularIncomeWithdraw');
        const remove=document.getElementById('regularIncomeDelete');
        const save=form.querySelector('button[type="submit"]');
        if(submit)submit.disabled=!editable||!form.elements.id.value||!calculationReady;
        if(withdraw){withdraw.disabled=!withdrawable;withdraw.classList.toggle('d-none',!withdrawable);}
        if(remove)remove.disabled=!editable||!form.elements.id.value;
        if(save)save.disabled=!editable||!isIncomeWithholdingDate(withholdingDateInput.value);
    }
    function openCreate(){form.reset();setYearMonth('');items=[];dependentCountOverrides.clear();loadedMonth='';documentStatus='DRAFT';form.elements.id.value='';form.elements.current_approval_request_id.value='';setReadonly(false);applyFormSettings();closeDetail();renderItems();renderSystemInfo();modal.show();}
    async function calculateItems(month,rows){
        const paymentDate=withholdingDateInput.value;
        if(!rows.length)return rows;
        if(!isIncomeWithholdingDate(paymentDate))throw new Error('원천징수일을 입력하면 해당 날짜의 법정기준으로 자동 계산됩니다.');
        const calculated=(await request(API.CALCULATE,{method:'POST',body:JSON.stringify({income_year_month:month,withholding_date:paymentDate,employees:rows.map(item=>({employee_id:item.employee_id,dependent_count_snapshot:dependentCountOverrides.has(String(item.employee_id))?dependentCountOverrides.get(String(item.employee_id)):(item.dependent_count_snapshot??null),national_pension_basis_snapshot:item.national_pension_basis_snapshot??null,health_insurance_basis_snapshot:item.health_insurance_basis_snapshot??null,employment_insurance_basis_snapshot:item.employment_insurance_basis_snapshot??null,pay_line_items:(item.line_items||[]).filter(line=>line.item_type_code==='PAY'&&['INCREASE','DECREASE'].includes(line.pay_effect_code)),deduction_line_items:(item.line_items||[]).filter(line=>line.item_type_code==='DEDUCTION'&&isSettlementLine(line)),insurance_override_line_items:(item.line_items||[]).filter(line=>line.item_type_code==='DEDUCTION'&&['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'].includes(line.item_code)&&(Math.abs(number(line.adjustment_amount))>=.01||String(line.source_key||'').startsWith('INSURANCE_OVERRIDE'))),institution_override_line_items:(item.line_items||[]).filter(line=>['DEDUCTION','EMPLOYER_BURDEN'].includes(line.item_type_code)&&String(line.source_key||'').startsWith('INSTITUTION_OVERRIDE|'))}))})})).data;
        const results=new Map((calculated.results||[]).map(row=>[String(row.employee_id),row]));
        return rows.map(item=>{
            const result=results.get(String(item.employee_id));
            if(!result)return item;
            const appliedLines=new Map((item.line_items||[]).map(line=>[
                [line.item_type_code,line.item_code,line.pay_effect_code||''].join('|'),
                line,
            ]));
            const lineItems=(result.line_items||[]).map(calculatedLine=>{
                const key=[calculatedLine.item_type_code,calculatedLine.item_code,calculatedLine.pay_effect_code||''].join('|');
                const applied=appliedLines.get(key);
                if(!applied)return calculatedLine;
                const calculatedAmount=nullableNumber(calculatedLine.calculated_amount);
                const appliedCalculated=nullableNumber(applied.calculated_amount);
                const appliedFinal=nullableNumber(applied.final_amount);
                const wasAutomatic=appliedCalculated!==null&&appliedFinal!==null&&Math.abs(appliedFinal-appliedCalculated)<.01&&Math.abs(number(applied.adjustment_amount))<.01&&!applied.adjustment_reason;
                const finalAmount=wasAutomatic?calculatedAmount:appliedFinal;
                return {
                    ...calculatedLine,
                    final_amount:finalAmount,
                    adjustment_amount:calculatedAmount===null||finalAmount===null?null:finalAmount-calculatedAmount,
                    adjustment_reason:wasAutomatic?null:(applied.adjustment_reason||null),
                    calculation_source_code:wasAutomatic?calculatedLine.calculation_source_code:(applied.calculation_source_code||calculatedLine.calculation_source_code),
                };
            });
            const returnedSettlements=new Set(lineItems.filter(isSettlementLine).map(settlementIdentity));
            const missingSettlements=(item.line_items||[]).filter(line=>isSettlementLine(line)&&!returnedSettlements.has(settlementIdentity(line)));
            const employeeKey=String(item.employee_id);const dependentCount=dependentCountOverrides.has(employeeKey)?dependentCountOverrides.get(employeeKey):(result.dependent_count_snapshot??item.dependent_count_snapshot??null);
            const deductionAmount=code=>number(lineItems.find(line=>line.item_type_code==='DEDUCTION'&&line.item_code===code)?.final_amount);
            return {...item,...result,dependent_count_snapshot:dependentCount,national_pension_amount:deductionAmount('NATIONAL_PENSION'),health_insurance_amount:deductionAmount('HEALTH_INSURANCE'),long_term_care_amount:deductionAmount('LONG_TERM_CARE')||deductionAmount('LONG_TERM_CARE_INSURANCE'),employment_insurance_amount:deductionAmount('EMPLOYMENT_INSURANCE'),income_tax_amount:deductionAmount('EMPLOYMENT_INCOME_TAX'),local_income_tax_amount:deductionAmount('LOCAL_INCOME_TAX'),other_deduction_amount:deductionAmount('OTHER_DEDUCTION'),line_items:[...lineItems,...missingSettlements]};
        });
    }
    async function openDetail(id){dependentCountOverrides.clear();const data=(await request(`${API.DETAIL}?id=${encodeURIComponent(id)}`)).data;renderSystemInfo(data.header);form.elements.id.value=data.header.id;form.elements.current_approval_request_id.value=data.header.current_approval_request_id||'';['title','description','memo'].forEach(key=>form.elements[key].value=data.header[key]||'');setYearMonth(data.header.income_year_month||'');loadedMonth=data.header.income_year_month||'';documentStatus=data.header.document_status||'DRAFT';items=data.items;setReadonly(!EDITABLE.has(documentStatus));applyFormSettings();closeDetail();renderItems();modal.show();}
    function calculationBasisFingerprint(item={}){
        const scalarKeys=['employment_contract_id','dependent_count_snapshot','national_pension_basis_snapshot','health_insurance_basis_snapshot','employment_insurance_basis_snapshot'];
        const scalars=Object.fromEntries(scalarKeys.map(key=>[key,String(item[key]??'')]));
        const lines=(item.line_items||[]).map(line=>({item_type_code:String(line.item_type_code||''),item_code:String(line.item_code||''),pay_effect_code:String(line.pay_effect_code||''),source_reference_id:String(line.source_reference_id||''),source_key:String(line.source_key||''),taxable_flag:String(line.taxable_flag??''),calculated_amount:number(line.calculated_amount)})).sort((left,right)=>JSON.stringify(left).localeCompare(JSON.stringify(right)));
        const bases=(item.calculation_bases||[]).map(basis=>({basis_type_code:String(basis.basis_type_code||''),source_table:String(basis.source_table||''),source_id:String(basis.source_id||''),source_revision:String(basis.source_revision||''),effective_from:String(basis.effective_from||''),effective_to:String(basis.effective_to||''),basis_code:String(basis.basis_code||'')})).sort((left,right)=>JSON.stringify(left).localeCompare(JSON.stringify(right)));
        return JSON.stringify({scalars,lines,bases});
    }
    function eligibleLoadMessage({targetCount,updatedCount,addedCount}){
        const prefix=`대상 직원 ${targetCount}명을 불러왔습니다.`;
        if(updatedCount&&addedCount)return `${prefix} 기존 직원 ${updatedCount}명의 급여정보를 갱신하고 새 직원 ${addedCount}명을 추가했습니다.`;
        if(updatedCount)return `${prefix} 기존 직원 ${updatedCount}명의 급여정보를 갱신했습니다.`;
        if(addedCount)return `${prefix} 새 직원 ${addedCount}명이 추가되었습니다.`;
        return `${prefix} 새로 추가되거나 변경된 직원은 없습니다.`;
    }
    async function loadEligibleEmployees(){
        const month=form.elements.income_year_month.value;
        if(!month){notify('warning','귀속연월을 선택해 주세요.');return;}
        try{
            const selection=(await request(`${API.ELIGIBLE}?income_year_month=${encodeURIComponent(month)}`)).data||{};
            const candidates=Array.isArray(selection)?selection:(selection.candidates||[]);
            const currentByEmployee=new Map();
            items.forEach(item=>{const key=String(item.employee_id||'');if(!key||currentByEmployee.has(key))throw new Error('현재 문서에 동일 직원이 중복되어 있습니다.');currentByEmployee.set(key,item);});
            const candidateByEmployee=new Map();
            candidates.forEach(candidate=>{const key=String(candidate.employee_id||'');if(!key||candidateByEmployee.has(key))throw new Error('대상직원 조회 결과에 동일 직원이 중복되어 있습니다.');candidateByEmployee.set(key,candidate);});
            let addedCount=0;
            const beforeByEmployee=new Map(items.map(item=>[String(item.employee_id),calculationBasisFingerprint(item)]));
            const excluded=Array.isArray(selection.excluded)?selection.excluded:[];
            const excludedByEmployee=new Map(excluded.map(row=>[String(row.employee_id),row]));
            const retainedIneligible=[];
            const merged=items.map(existing=>{const key=String(existing.employee_id),candidate=candidateByEmployee.get(key);if(!candidate){const excludedRow=excludedByEmployee.get(key);const reason=excludedRow?.reason||'귀속월 재직기간 또는 대상조건에 맞지 않습니다.';retainedIneligible.push({employee_name:existing.employee_name_snapshot||existing.employee_name||'이름 미확인',reason});return{...existing,candidate_review_required:true,calculation_message:`${reason} 기존 작성내용 보호를 위해 문서에 유지했습니다. 제외 여부를 확인해 주세요.`};}candidateByEmployee.delete(key);return{...candidate,...existing,employee_name_snapshot:candidate.employee_name||existing.employee_name_snapshot,department_name_snapshot:candidate.department_name||existing.department_name_snapshot,position_name_snapshot:candidate.position_name||existing.position_name_snapshot,employment_contract_id:candidate.employment_contract_id||existing.employment_contract_id,candidate_review_required:false};});
            candidates.forEach(candidate=>{const key=String(candidate.employee_id);if(!candidateByEmployee.has(key))return;candidateByEmployee.delete(key);addedCount+=1;merged.push({...candidate,candidate_review_required:false});});
            loadedMonth=month;
            const recalculationTargets=merged.filter(item=>!item.candidate_review_required);
            if(recalculationTargets.length){
                const recalculated=await calculateItems(month,recalculationTargets);
                const recalculatedByEmployee=new Map(recalculated.map(item=>[String(item.employee_id),item]));
                items=merged.map(item=>recalculatedByEmployee.get(String(item.employee_id))||item);
            }else{
                items=merged;
            }
            closeDetail();renderItems();
            const updatedCount=items.filter(item=>beforeByEmployee.has(String(item.employee_id))&&!item.candidate_review_required&&beforeByEmployee.get(String(item.employee_id))!==calculationBasisFingerprint(item)).length;
            const targetCount=candidates.length;
            if(!items.length){
                const reason=excluded.length?excluded.map(row=>`${row.employee_name}: ${row.reason}`).join(' / '):'재직기간과 유효 근로계약이 귀속월에 겹치는 직원이 없습니다.';
                notify('warning',`대상 직원이 없습니다. ${reason}`);
            }else if(retainedIneligible.length){
                const retainedMessage=retainedIneligible.length===1?`${retainedIneligible[0].employee_name} 직원은 현재 대상조건에 맞지 않지만 기존 작성내용 보호를 위해 문서에 유지했습니다. 사유: ${retainedIneligible[0].reason}`:`현재 대상조건에 맞지 않는 기존 직원 ${retainedIneligible.length}명을 문서에 유지했습니다. 직원별 확인사항에서 제외 사유를 확인하고 제외 여부를 결정해 주세요.`;
                notify('warning',`${eligibleLoadMessage({targetCount,updatedCount,addedCount})} ${retainedMessage}`);
            }else{
                notify('success',eligibleLoadMessage({targetCount,updatedCount,addedCount}));
            }
        }catch(error){notify('error',error.message||'대상직원 조회 중 오류가 발생했습니다.');}
    }
    document.getElementById('loadEligibleEmployees').addEventListener('click',loadEligibleEmployees);
    yearMonthDisplay.addEventListener('change',()=>{const month=form.elements.income_year_month.value;if(items.length&&loadedMonth&&month!==loadedMonth){items=[];loadedMonth='';closeDetail();renderItems();notify('warning','귀속연월이 변경되어 기존 직원 내역을 초기화했습니다. 대상직원을 다시 불러와 주세요.');}});
    form.addEventListener('submit',async event=>{event.preventDefault();const submitButton=event.submitter;try{const draftOwner=items.find(item=>(item.pay_adjustment_drafts||[]).length);if(draftOwner)throw new Error(`${draftOwner.employee_name_snapshot} 직원의 증감 카드에서 지급항목, 금액, 사유를 입력하고 완료해 주세요.`);if(submitButton)submitButton.disabled=true;recalculationTimers.forEach(timer=>clearTimeout(timer));recalculationTimers.clear();items.forEach(item=>nextRecalculationVersion(item.employee_id));items=await calculateItems(form.elements.income_year_month.value,items);renderItems();await request(API.SAVE,{method:'POST',body:JSON.stringify({id:form.elements.id.value,income_year_month:form.elements.income_year_month.value,withholding_date:withholdingDateInput.value,title:form.elements.title.value,description:form.elements.description.value,memo:form.elements.memo.value,items})});modal.hide();table.ajax.reload(null,false);notify('success','최신 계산값으로 저장되었습니다.');}catch(error){notify('error',error.message||'저장 중 오류가 발생했습니다.');}finally{if(submitButton)submitButton.disabled=false;}});
    document.getElementById('regularIncomeSubmit').addEventListener('click',async()=>{try{const id=form.elements.id.value;if(!id)throw new Error('먼저 저장해 주세요.');await request(API.SUBMIT,{method:'POST',body:JSON.stringify({id})});notify('success','결재를 요청했습니다.');modal.hide();table.ajax.reload(null,false);}catch(error){notify('error',error.message);}});document.getElementById('regularIncomeWithdraw').addEventListener('click',async()=>{try{const requestId=form.elements.current_approval_request_id.value;if(!requestId)throw new Error('회수할 결재요청이 없습니다.');await request(API.WITHDRAW,{method:'POST',body:JSON.stringify({request_id:requestId})});notify('success','기안을 회수했습니다.');modal.hide();table.ajax.reload(null,false);}catch(error){notify('error',error.message);}});document.getElementById('regularIncomeDelete').addEventListener('click',async()=>{try{const id=form.elements.id.value;if(!id)return;const confirmed=await confirmDialog({title:'상용근로소득 삭제',message:'이 상용근로소득 문서를 삭제하시겠습니까?',confirmText:'삭제',confirmClass:'btn-danger'});if(!confirmed)return;await request(API.DELETE,{method:'POST',body:JSON.stringify({id})});notify('success','삭제되었습니다.');modal.hide();table.ajax.reload(null,false);}catch(error){notify('error',error.message);}});
}
