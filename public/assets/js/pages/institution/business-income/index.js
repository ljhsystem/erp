import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { formatAmount, formatDateDisplay } from '/public/assets/js/common/format.js';
import { notify } from '/public/assets/js/common/notification.js';
import { confirmDialog } from '/public/assets/js/common/confirm-dialog.js';
import { bindModalCardCollapses } from '/public/assets/js/common/modal-card-collapse.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { formatYearMonthValue, normalizeYearMonthInputValue, parseYearMonthValue } from '/public/assets/js/common/picker/picker.yearmonth.js';
import { createDataTableFormSettings } from '/public/assets/js/common/datatable/dataTableFormSettings.js';
import { fetchDataTableMetaColumns, getCachedDataTableMetaColumns, readDataTableSettingsState, resolveDataTableColumnDisplayName, resolveDataTableColumnRequirementPolicy } from '/public/assets/js/common/datatable/dataTableSettings.js';
import { PickerSelect2 } from '/public/assets/js/common/picker/picker.select2.js';
import { createHtmlGrid } from '/public/assets/js/common/html-grid/index.js';
import { incomeCalculationLineDto, incomeCalculationRoundingText, renderIncomeCalculationCards } from '/public/assets/js/common/income-calculation-cards.js?v=20260831-income-cards-14';
import { getCodeOptions } from '/public/assets/js/pages/main/settings/system/code-select.js';
import { createBusinessIncomeExcelProvider } from './excel-provider.js';
import '/public/assets/js/common/core/AppAjax.js';
import '/public/assets/js/components/trash-manager.js';
import { incomeWithholdingDate, INCOME_WITHHOLDING_RULES, isIncomeWithholdingDate } from '/public/assets/js/common/income-withholding-date.js';

const API={LIST:'/api/institution/income-data/business-income/list',DETAIL:'/api/institution/income-data/business-income/detail',OPTIONS:'/api/institution/income-data/business-income/options',CALCULATE:'/api/institution/income-data/business-income/calculate',PREFLIGHT:'/api/institution/income-data/business-income/preflight',SAVE:'/api/institution/income-data/business-income/save',SUBMIT:'/api/institution/income-data/business-income/submit',WITHDRAW:'/api/institution/income-data/business-income/withdraw',DELETE:'/api/institution/income-data/business-income/delete',EXCEL_DOWNLOAD:'/api/institution/income-data/business-income/excel'};
const root=document.querySelector('.business-income-page');

if(root){
    const modalElement=document.getElementById('businessIncomeModal');
    const modal=bootstrap.Modal.getOrCreateInstance(modalElement,{focus:false});
    const form=document.getElementById('businessIncomeForm');
    const groupsHost=document.getElementById('businessIncomeGroups');
    const recipientResult=document.getElementById('businessIncomeRecipientResult');
    const preview=document.getElementById('businessIncomePreview');
    const calculationGuidance=document.getElementById('businessIncomeCalculationGuidance');
    const yearMonthDisplay=document.getElementById('businessIncomeYearMonthDisplay');
    const withholdingDateInput=document.getElementById('businessIncomeWithholdingDate');
    const yearMonthPicker=AdminPicker.create({type:'year-month',container:document.getElementById('businessIncomeYearMonthPicker')});
    const datePicker=AdminPicker.create({type:'today',container:document.getElementById('businessIncomeDatePicker')});
    const modalParent=window.jQuery?.(modalElement);
    const settingsKey='institution.income-data.business-income.business-income-table.v1';
    const settingsOptions={pageKey:'institution.income-data.business-income',metaDomain:'business-income'};
    const formSettings=createDataTableFormSettings({form,metaDomain:'business-income',storageKey:settingsKey,settingsOptions});
    let groups=[];
    let calculated=false;
    let saved=false;
    let readOnly=false;
    let detailState=null;
    let activeDateInput=null;
    let syncingDatePicker=false;
    let selectedItemKey='';
    let calculationTimer=null;
    let calculationAbortController=null;
    let calculationRequestVersion=0;
    let calculationState='idle';
    let calculationError='';
    let workLineGrids=[];
    let optionCache={business_units:[],projects:[],work_teams:[],work_types:[],recipients:[]};
    let unitOptions=[];
    const modalMetaDomains={group:'business-income-group',item:'business-income-item',workLine:'business-income-work-line'};

    const token=()=>crypto.randomUUID?.()||`${Date.now()}-${Math.random()}`;
    const escapeHtml=value=>{const node=document.createElement('div');node.textContent=String(value??'');return node.innerHTML;};
    const number=value=>Number(value||0);
    const request=(url,options={})=>window.AppCore.fetchJson(url,{...options,headers:{...(options.body===undefined?{}:{'Content-Type':'application/json'}),...(options.headers||{})}});
    const optionName=(rows,id)=>rows.find(row=>String(row.id)===String(id))?.name||'';
    const statusLabel=value=>({DRAFT:'작성 중',PENDING:'결재 진행 중',APPROVED:'승인 완료',REJECTED:'반려',WITHDRAWN:'회수',CALCULATED:'계산 완료',NOT_CALCULATED:'계산 전'}[value]||value||'-');
    const amountCard=(label,value)=>`<div class="business-income-amount"><span>${label}</span><strong>${formatAmount(number(value))}원</strong></div>`;
    const settlementText=value=>calculated&&isIncomeWithholdingDate(withholdingDateInput.value)?`${formatAmount(number(value))}원`:'미확정';
    const settlementCard=(label,value)=>`<div class="business-income-amount"><span>${label}</span><strong>${settlementText(value)}</strong></div>`;
    const metricCard=(label,value)=>`<div class="business-income-amount"><span>${label}</span><strong>${escapeHtml(value)}</strong></div>`;
    const physicalMeta=(domain,key)=>getCachedDataTableMetaColumns({metaDomain:modalMetaDomains[domain]||domain}).find(column=>String(column.key||'')===String(key));
    const physicalLabel=(domain,key,fallback)=>physicalMeta(domain,key)?.label||fallback;
    const physicalRequired=(domain,key)=>physicalMeta(domain,key)?.is_nullable==='NO';
    const physicalLabelHtml=(domain,key,fallback)=>{const column=physicalMeta(domain,key);const policy=column?.is_nullable==='NO'?'required':'optional';return `${escapeHtml(column?.label||fallback)} <span class="column-policy-star is-${policy}" aria-label="${policy==='required'?'필수':'선택'}">*</span>`;};
    const optionsHtml=(rows,value,empty='선택 안 함')=>`<option value="">${empty}</option>${rows.map(row=>`<option value="${escapeHtml(row.id)}" ${String(row.id)===String(value)?'selected':''}>${escapeHtml(row.name||row.id)}</option>`).join('')}`;

    function createWorkLine(source={}){
        const quantity=number(source.item_quantity||1);const unitPrice=number(source.item_unit_price);const adjustment=number(source.adjustment_amount);const calculated=Math.round(quantity*unitPrice*100)/100;
        return {...source,key:source.key||token(),item_name:source.item_name||'',item_specification:source.item_specification||'',item_unit_name:source.item_unit_name||'',item_quantity:quantity,item_unit_price:unitPrice,calculated_amount:calculated,adjustment_amount:adjustment,adjustment_reason:source.adjustment_reason||'',final_amount:Math.round((calculated+adjustment)*100)/100};
    }
    function refreshWorkAmounts(item){item.work_lines.forEach(line=>{line.calculated_amount=Math.round(number(line.item_quantity)*number(line.item_unit_price)*100)/100;line.final_amount=Math.round((line.calculated_amount+number(line.adjustment_amount))*100)/100;});item.gross_payment_amount=Math.round(item.work_lines.reduce((sum,line)=>sum+number(line.final_amount),0)*100)/100;}
    function createItem(source={}){
        return {
            ...source,
            key:source.key||token(),collapsed:Boolean(source.collapsed),client_id:source.client_id||'',client_name:source.client_name||'',client_type:source.client_type||'',client_type_name:source.client_type_name||'',
            transaction_date:source.transaction_date||'',service_type_code:source.service_type_code||'',service_description:source.service_description||'',
            gross_payment_amount:number(source.gross_payment_amount),income_tax_amount:number(source.income_tax_amount),
            local_income_tax_amount:number(source.local_income_tax_amount),
            total_deduction_amount:number(source.total_deduction_amount),net_payment_amount:number(source.net_payment_amount),
            work_lines:(source.work_lines?.length?source.work_lines:[{}]).map(createWorkLine),
            lines:Array.isArray(source.lines)?source.lines.map(line=>({...line})):[],
            client_tax_profile_id:source.client_tax_profile_id||null,recipient_tax_snapshot_json:source.recipient_tax_snapshot_json||null,
        };
    }
    function createGroup(source={}){
        return {...source,key:source.key||token(),collapsed:Boolean(source.collapsed),business_unit:source.business_unit||'',project_id:source.project_id||'',work_team_id:source.work_team_id||'',group_description:source.group_description||'',items:(source.items||[]).map(createItem)};
    }
    function groupTotals(group){return group.items.reduce((sum,item)=>({gross:sum.gross+number(item.gross_payment_amount),deduction:sum.deduction+number(item.total_deduction_amount),net:sum.net+number(item.net_payment_amount)}),{gross:0,deduction:0,net:0});}
    function groupDetailTotals(group){return group.items.reduce((sum,item)=>({recipient_count:sum.recipient_count+1,work_line_count:sum.work_line_count+item.work_lines.length,gross_payment_amount:sum.gross_payment_amount+number(item.gross_payment_amount),income_tax_amount:sum.income_tax_amount+number(item.income_tax_amount),local_income_tax_amount:sum.local_income_tax_amount+number(item.local_income_tax_amount),total_deduction_amount:sum.total_deduction_amount+number(item.total_deduction_amount),net_payment_amount:sum.net_payment_amount+number(item.net_payment_amount)}),{recipient_count:0,work_line_count:0,gross_payment_amount:0,income_tax_amount:0,local_income_tax_amount:0,total_deduction_amount:0,net_payment_amount:0});}
    const referenceKey=value=>String(value??'').trim().toUpperCase();
    function businessUnitPolicy(value){
        const key=referenceKey(value);const row=optionCache.business_units.find(option=>referenceKey(option.id??option.code??option.business_unit)===key);if(!row)return null;
        if(['uses_project','requires_project','uses_work_team','requires_work_team'].every(key=>Object.hasOwn(row,key)))return row;
        let extra=row.extra_data;if(typeof extra==='string'){try{extra=JSON.parse(extra);}catch{extra={};}}
        const policy=extra?.daily_employment_income||{};
        const enabled=value=>value===true||value===1||value==='1'||value==='true';
        return {...row,uses_project:enabled(policy.uses_project),requires_project:enabled(policy.requires_project),uses_work_team:enabled(policy.uses_work_team),requires_work_team:enabled(policy.requires_work_team)};
    }
    function documentTotals(){return groups.flatMap(group=>group.items).reduce((sum,item)=>{for(const key of Object.keys(sum))sum[key]+=number(item[key]);return sum;},{gross_payment_amount:0,income_tax_amount:0,local_income_tax_amount:0,total_deduction_amount:0,net_payment_amount:0});}
    function documentDetailTotals(){const amounts=documentTotals();return{...amounts,group_count:groups.length,recipient_count:groups.reduce((sum,group)=>sum+group.items.length,0),work_line_count:groups.reduce((sum,group)=>sum+group.items.reduce((itemSum,item)=>itemSum+item.work_lines.length,0),0)};}
    function resetSettlementAmounts(item){
        item.income_tax_amount=null;
        item.local_income_tax_amount=null;
        item.total_deduction_amount=null;
        item.net_payment_amount=null;
        item.lines=[];
    }
    function prepareDraftAmounts(){groups.forEach(group=>group.items.forEach(item=>{refreshWorkAmounts(item);resetSettlementAmounts(item);}));}
    function selectedItem(){return groups.flatMap(group=>group.items).find(item=>item.key===selectedItemKey)||null;}
    function copyItem(item){return createItem({...item,id:null,key:token(),collapsed:false,work_lines:item.work_lines.map(line=>({...line,id:null,key:token()}))});}
    function copyGroup(group){return createGroup({...group,id:null,key:token(),collapsed:false,items:group.items.map(item=>({...item,id:null,key:token(),collapsed:Boolean(item.collapsed)}))});}
    function renderRecipientResult(){
        const item=selectedItem();
        if(!item){recipientResult.innerHTML='<h6>선택 사업소득자 계산 결과</h6><div class="business-income-result-empty">소득자 카드를 선택해 주세요.</div>';return;}
        const groupIndex=groups.findIndex(group=>group.items.includes(item));const itemIndex=groups[groupIndex]?.items.indexOf(item)??-1;
        const recipient=optionCache.recipients.find(row=>String(row.id)===String(item.client_id));const name=recipient?.name||item.client_name||'소득자 미선택';const type=recipient?.client_type_name||item.client_type_name||recipient?.client_type||item.client_type||'거래처유형미등록';const workType=optionName(optionCache.work_types,item.service_type_code)||item.service_type_code||'공종 미선택';
        const row=(label,value,strong=false)=>`<div><span>${label}</span><${strong?'strong':'b'}>${value}</${strong?'strong':'b'}></div>`;
        const workRows=item.work_lines.map((line,index)=>row(`${index+1}. ${escapeHtml(line.item_name||'품명 미입력')}`,`${formatAmount(line.item_quantity)} ${escapeHtml(line.item_unit_name||'')} × ${formatAmount(line.item_unit_price)}원${number(line.adjustment_amount)!==0?` ${number(line.adjustment_amount)>0?'+':''}${formatAmount(line.adjustment_amount)}원`:''} = ${formatAmount(line.final_amount)}원`,true)).join('');
        recipientResult.innerHTML=`<h6>그룹 ${groupIndex+1} <span class="business-income-result-separator">·</span> ${itemIndex+1}.${escapeHtml(name)} <span class="badge text-bg-secondary">${escapeHtml(type)}</span></h6><section class="business-income-result-section"><div class="business-income-result-section-title">외주 작업 금액</div><div class="business-income-result-values">${row('공종',escapeHtml(workType))}${row('작업내용',escapeHtml(item.service_description||'미입력'))}${workRows}${row('작업 확정금액 합계',`${formatAmount(item.gross_payment_amount)}원`,true)}</div></section><section class="business-income-result-section"><div class="business-income-result-section-title">원천징수</div><div class="business-income-result-values">${row('사업소득세',settlementText(item.income_tax_amount))}${row('개인지방소득세',settlementText(item.local_income_tax_amount))}${row('총공제액',settlementText(item.total_deduction_amount),true)}</div></section><section class="business-income-result-section is-final"><div class="business-income-result-section-title">최종 지급</div><div class="business-income-result-values">${row('지급액(세전)',`${formatAmount(item.gross_payment_amount)}원`)}${row('공제 합계',settlementText(item.total_deduction_amount))}${row('최종지급액',settlementText(item.net_payment_amount),true)}</div></section>`;
    }

    function businessIncomeInstitutionDto(item){
        const definitions=[['INCOME_TAX','국세청 · 사업소득세'],['LOCAL_INCOME_TAX','지방자치단체 · 개인지방소득세']];
        return{lines:definitions.map(([code,name])=>{const line=(item.lines||[]).find(candidate=>candidate.line_code===code)||{};return incomeCalculationLineDto(line,{key:code,name,basisAmount:line.calculation_base_amount??null,rate:line.applied_rate??null,beforeRounding:line.amount_before_rounding??null,calculatedAmount:line.calculated_amount??null,finalAmount:line.calculated_amount??null,difference:0,standardLabel:line.statutory_standard_revision_id?'원천징수일 기준 법정기준':'법정기준 확인 필요',roundingLabel:incomeCalculationRoundingText(line),applicationStatus:line.applicability_status||'',editable:false});})};
    }
    function renderInstitutionDetails(item,itemElement){
        const host=itemElement?.querySelector('[data-business-income-institution-detail]');if(!host)return;
        host.replaceChildren();
        const heading=document.createElement('div');heading.className='business-income-institution-heading';heading.innerHTML='<strong>기관별 원천징수 계산</strong><small>원천징수일의 법정요율과 끝수처리를 적용한 결과입니다.</small>';
        const cards=document.createElement('div');host.append(heading,cards);renderIncomeCalculationCards(cards,businessIncomeInstitutionDto(item));
    }
    function groupTotalsHtml(group){const totals=groupDetailTotals(group);return`${metricCard('소득자',`${totals.recipient_count}명`)}${metricCard('외주 작업',`${totals.work_line_count}건`)}${amountCard('지급액(세전)',totals.gross_payment_amount)}${settlementCard('사업소득세',totals.income_tax_amount)}${settlementCard('개인지방소득세',totals.local_income_tax_amount)}${settlementCard('총공제액',totals.total_deduction_amount)}${settlementCard('최종지급액',totals.net_payment_amount)}`;}
    function renderLiveAmounts(){
        renderTotals();
        groups.forEach((group,groupIndex)=>{
            const groupElement=groupsHost.querySelector(`[data-group="${CSS.escape(group.key)}"]`);
            const summary=groupElement?.querySelector('.business-income-group__summary small');
            const totalsForGroup=groupTotals(group);
            if(summary)summary.textContent=`소득자 ${group.items.length}명 · 지급액(세전) ${formatAmount(totalsForGroup.gross)}원 · 총공제 ${settlementText(totalsForGroup.deduction)} · 최종지급액 ${settlementText(totalsForGroup.net)}`;
            const groupFooter=groupElement?.querySelector('[data-business-income-group-totals]');if(groupFooter)groupFooter.innerHTML=groupTotalsHtml(group);
            group.items.forEach(item=>{
                const itemElement=groupElement?.querySelector(`[data-item="${CSS.escape(item.key)}"]`);
                const payment=itemElement?.querySelector('.business-income-item__payment');
                const itemTotals=itemElement?.querySelector('.business-income-item__totals');
                if(payment)payment.textContent=`지급액(세전) ${formatAmount(item.gross_payment_amount)}원`;
                if(itemTotals)itemTotals.innerHTML=`${amountCard('작업 확정금액',item.gross_payment_amount)}${settlementCard('사업소득세',item.income_tax_amount)}${settlementCard('개인지방소득세',item.local_income_tax_amount)}${settlementCard('총공제액',item.total_deduction_amount)}${settlementCard('최종지급액',item.net_payment_amount)}`;
                renderInstitutionDetails(item,itemElement);
            });
        });
        renderRecipientResult();
    }

    function renderSystemInfo(){
        const data=detailState||{};
        const host=document.getElementById('businessIncomeSystemInfo');
        const amount=value=>value===null||value===undefined||value===''?'-':`${formatAmount(value)}원`;
        const date=value=>value?formatDateDisplay(value)||'-':'-';
        const values=[
            ['id','문서 ID',data.id],['sort_no','정렬순서',data.sort_no],
            ['document_status','문서상태',statusLabel(data.document_status)],['calculation_status','계산상태',statusLabel(data.calculation_status)],
            ['approval_status','결재상태',statusLabel(data.approval_status)],['payment_status','지급처리상태',statusLabel(data.payment_status)],
            ['withholding_filing_status','원천징수 신고상태',statusLabel(data.withholding_filing_status)],['simplified_statement_status','간이지급명세서 제출상태',statusLabel(data.simplified_statement_status)],
            ['current_calculation_revision_id','현재 계산 Revision ID',data.current_calculation_revision_id],['current_approval_request_id','현재 결재요청 ID',data.current_approval_request_id],
            ['group_count','소득그룹 수',`${formatAmount(data.group_count||0)}개`],['item_count','소득자 지급내역 수',`${formatAmount(data.item_count||0)}건`],
            ['total_gross_payment_amount','총지급액 합계',amount(data.total_gross_payment_amount)],['total_income_tax_amount','사업소득세 합계',amount(data.total_income_tax_amount)],
            ['total_local_income_tax_amount','개인지방소득세 합계',amount(data.total_local_income_tax_amount)],
            ['total_deduction_amount','총공제액 합계',amount(data.total_deduction_amount)],['total_net_payment_amount','최종지급액 합계',amount(data.total_net_payment_amount)],
            ['created_by','등록자',data.created_by_name||data.created_by],['created_at','등록일시',date(data.created_at)],
            ['updated_by','수정자',data.updated_by_name||data.updated_by],['updated_at','수정일시',date(data.updated_at)],
            ['deleted_by','삭제자',data.deleted_by_name||data.deleted_by],['deleted_at','삭제일시',date(data.deleted_at)],
        ];
        const columns=getCachedDataTableMetaColumns({metaDomain:'business-income'});const state=readDataTableSettingsState(settingsKey,settingsOptions);
        host.replaceChildren(...values.map(([key,fallbackLabel,rawValue],index)=>{
            const column=columns.find(candidate=>String(candidate.key||'')===key);const policy=column?resolveDataTableColumnRequirementPolicy(column,state):'none';const label=column?resolveDataTableColumnDisplayName(column,state,column.label||fallbackLabel):fallbackLabel;
            const item=document.createElement('div');const term=document.createElement('span');const value=document.createElement('span');
            item.className=`business-income-system-info-field${index>=10&&index<=17?' is-numeric':''}`;term.className='business-income-system-info-label';value.className='business-income-system-info-value';
            term.append(document.createTextNode(label));if(['required','optional'].includes(policy)){const marker=document.createElement('span');marker.className=`column-policy-star is-${policy}`;marker.setAttribute('aria-label',policy==='required'?'필수':'선택');marker.textContent='*';term.append(marker);}value.textContent=rawValue===null||rawValue===undefined||rawValue===''?'-':String(rawValue);value.title=value.textContent;item.append(term,value);return item;
        }));
    }
    function syncActions(){
        const status=detailState?.document_status||'DRAFT';
        const editable=['DRAFT','REJECTED','WITHDRAWN'].includes(status)&&!readOnly;
        const hasDocument=Boolean(form.elements.id.value);
        const withdraw=document.getElementById('businessIncomeWithdraw');
        const save=form.querySelector('button[type="submit"]');
        document.getElementById('businessIncomeDelete').disabled=!editable||!hasDocument;
        const withdrawable=status==='PENDING'&&Boolean(detailState?.current_approval_request_id);
        withdraw.disabled=!withdrawable;
        withdraw.classList.toggle('d-none',!withdrawable);
        save.disabled=!editable||!isIncomeWithholdingDate(withholdingDateInput.value)||!calculated;
        document.getElementById('businessIncomeSubmit').disabled=!editable||!hasDocument||!calculated||!saved;
        document.getElementById('businessIncomeAddGroup').disabled=!editable;
        document.getElementById('businessIncomeExcelManager').disabled=!editable;
        form.querySelectorAll('input:not([type="hidden"]),select,textarea').forEach(control=>{
            if(!editable)control.disabled=true;
            else if(!control.matches('[data-field="project_id"],[data-field="work_team_id"]'))control.disabled=false;
        });
        document.getElementById('businessIncomeYearMonthButton').setAttribute('aria-disabled',editable?'false':'true');
    }
    function invalidate(){prepareDraftAmounts();calculated=false;saved=false;calculationState='waiting';calculationError='';renderTotals();syncActions();render();scheduleAutoCalculation();}
    function invalidateWorkLineCell(){
        prepareDraftAmounts();
        calculated=false;
        saved=false;
        calculationState='waiting';
        calculationError='';
        renderLiveAmounts();
        syncActions();
        scheduleAutoCalculation();
    }

    function renderItem(item,itemIndex,editable){
        refreshWorkAmounts(item);
        const recipientOption=optionCache.recipients.find(row=>String(row.id)===String(item.client_id));const recipient=item.client_name||recipientOption?.name||'소득자 선택';
        const clientType=recipientOption?.client_type_name||item.client_type_name||recipientOption?.client_type||item.client_type||'거래처유형미등록';
        const serviceTypeLabel=item.service_type_name||item.service_type_code_name||optionName(optionCache.work_types,item.service_type_code)||item.service_type_code||'공종 미선택';
        return `<article class="business-income-item ${item.collapsed?'is-collapsed':''} ${item.key===selectedItemKey?'is-selected':''}" data-item="${item.key}">
            <header class="business-income-item__header"><span class="business-income-item__order">${itemIndex+1}</span><button type="button" class="business-income-item__select" data-action="select-item">${escapeHtml(recipient)}</button><span class="business-income-item__type">${escapeHtml(clientType)}</span><span class="business-income-item__work-type">공종: ${escapeHtml(serviceTypeLabel)}</span><span class="business-income-item__description">작업내용: ${escapeHtml(item.service_description||'미입력')}</span><span class="business-income-item__payment">지급액(세전) ${formatAmount(item.gross_payment_amount)}원</span>${editable?`<button type="button" class="business-income-item__action" data-action="copy-item" title="소득자 복제" aria-label="소득자 복제"><i class="fa-regular fa-copy" aria-hidden="true"></i></button><button type="button" class="business-income-item__action is-danger" data-action="remove-item" title="소득자 삭제" aria-label="소득자 삭제"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>`:''}<button type="button" class="business-income-item__action" data-action="toggle-item" title="${item.collapsed?'펼치기':'접기'}" aria-label="${item.collapsed?'펼치기':'접기'}"><i class="fa-solid fa-chevron-${item.collapsed?'down':'up'}" aria-hidden="true"></i></button></header>
            <div class="business-income-item__body"><div class="row g-3 align-items-end">
                <div class="col-lg-2"><label class="form-label">${physicalLabelHtml('item','transaction_date','거래일')}</label><div class="date-input-wrap"><input class="form-control" data-item-field="transaction_date" inputmode="numeric" maxlength="10" placeholder="YYYY-MM-DD" value="${escapeHtml(item.transaction_date)}"><span class="date-icon" data-open-transaction-date role="button" tabindex="${editable?'0':'-1'}" aria-label="거래일 달력 열기"><i class="bi bi-calendar3"></i></span></div></div>
                <div class="col-lg-3"><label class="form-label">${physicalLabelHtml('item','client_id','사업소득자')}</label><select class="form-select" data-item-field="client_id" aria-label="${escapeHtml(physicalLabel('item','client_id','사업소득자'))}">${optionsHtml(optionCache.recipients,item.client_id,'사업소득자 선택')}</select></div>
                <div class="col-lg-2"><label class="form-label">${physicalLabelHtml('item','service_type_code','공종')}</label><select class="form-select" data-item-field="service_type_code">${optionsHtml(optionCache.work_types,item.service_type_code,'공종 선택')}</select></div>
                <div class="col-lg-3"><label class="form-label">${physicalLabelHtml('item','service_description','작업내용')}</label><input class="form-control" data-item-field="service_description" value="${escapeHtml(item.service_description)}"></div>
            </div><section class="business-income-work-lines"><header><div><strong>외주 작업내역</strong><small>각 작업의 수량 × 단가에 증감액을 반영해 소득자 지급액을 산정합니다.</small></div>${editable?'<button type="button" class="btn btn-outline-primary btn-sm" data-action="add-work-line">+ 작업내역 추가</button>':''}</header>
            <div class="html-grid-host html-grid-variant-compact business-income-work-line-grid" data-work-line-grid></div><div class="business-income-work-adjustment" data-work-adjustment-detail></div></section>
            <div class="business-income-item__totals">${amountCard('작업 확정금액',item.gross_payment_amount)}${amountCard('사업소득세',item.income_tax_amount)}${amountCard('개인지방소득세',item.local_income_tax_amount)}${amountCard('총공제액',item.total_deduction_amount)}${amountCard('최종지급액',item.net_payment_amount)}</div><section class="business-income-institution-detail" data-business-income-institution-detail></section></div></article>`;
    }
    function renderGroup(group,index,editable){
        const totals=groupTotals(group);const unit=optionName(optionCache.business_units,group.business_unit)||'사업구분 미선택';const project=optionName(optionCache.projects,group.project_id)||'해당 없음';const team=optionName(optionCache.work_teams,group.work_team_id)||'해당 없음';
        const policy=businessUnitPolicy(group.business_unit);const businessUnitKey=referenceKey(group.business_unit);const projects=optionCache.projects.filter(row=>referenceKey(row.business_unit)===businessUnitKey);const teams=optionCache.work_teams.filter(row=>referenceKey(row.business_unit)===businessUnitKey);
        return `<section class="business-income-group ${group.collapsed?'is-collapsed':''}" data-group="${group.key}"><header class="business-income-group__header"><div class="business-income-group__summary"><strong>그룹 ${index+1} · ${escapeHtml(unit)} / ${escapeHtml(project)} / ${escapeHtml(team)}</strong><small>소득자 ${group.items.length}명 · 지급액(세전) ${formatAmount(totals.gross)}원 · 총공제 ${formatAmount(totals.deduction)}원 · 최종지급액 ${formatAmount(totals.net)}원</small></div><div class="business-income-group__actions">${editable?`<button type="button" class="btn btn-outline-primary btn-sm" data-action="add-item">+ 소득자 추가</button><button type="button" class="btn btn-outline-secondary btn-sm" data-action="copy-group">그룹 복제</button><button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-group">삭제</button>`:''}<button type="button" class="btn btn-outline-secondary btn-sm" data-action="toggle-group">${group.collapsed?'펼치기':'접기'}</button></div></header><div class="business-income-group__body"><div class="row g-3 align-items-end">
            <div class="col-lg-2"><label class="form-label">${physicalLabelHtml('group','business_unit','사업구분')}</label><select class="form-select" data-field="business_unit">${optionsHtml(optionCache.business_units,group.business_unit,'사업구분 선택')}</select></div>
            <div class="col-lg-2"><label class="form-label">${physicalLabelHtml('group','project_id','프로젝트')}</label><select class="form-select" data-field="project_id" ${policy?.uses_project?'':'disabled'}>${optionsHtml(projects,group.project_id,policy?.uses_project?'선택(없음)':'미적용')}</select></div>
            <div class="col-lg-2"><label class="form-label">${physicalLabelHtml('group','work_team_id','작업팀')}</label><select class="form-select" data-field="work_team_id" ${policy?.uses_work_team?'':'disabled'}>${optionsHtml(teams,group.work_team_id,policy?.uses_work_team?'선택(없음)':'미적용')}</select></div>
            <div class="col"><label class="form-label">${physicalLabelHtml('group','group_description','그룹 작업내용')}</label><input class="form-control" data-field="group_description" value="${escapeHtml(group.group_description)}"></div></div><div class="business-income-items">${group.items.length?group.items.map((item,itemIndex)=>renderItem(item,itemIndex,editable)).join(''):'<div class="business-income-empty">소득자를 추가해 주세요.</div>'}</div><footer class="business-income-group-totals" data-business-income-group-totals>${groupTotalsHtml(group)}</footer></div></section>`;
    }
    function render(){
        const editable=!readOnly&&['DRAFT','REJECTED','WITHDRAWN'].includes(detailState?.document_status||'DRAFT');
        workLineGrids.forEach(grid=>grid.destroy());workLineGrids=[];
        groupsHost.querySelectorAll('select.select2-hidden-accessible').forEach(select=>PickerSelect2.destroy(select));
        groupsHost.innerHTML=groups.length
            ? groups.map((group,index)=>renderGroup(group,index,editable)).join('')
            : '<div class="business-income-empty">소득그룹을 추가해 주세요.</div>';
        initializePickers(editable);
        initializeWorkLineGrids(editable);
        groups.forEach(group=>group.items.forEach(item=>renderInstitutionDetails(item,groupsHost.querySelector(`[data-item="${CSS.escape(item.key)}"]`))));
        renderRecipientResult();
        syncActions();
    }
    function initializeWorkLineGrids(editable){
        groupsHost.querySelectorAll('[data-work-line-grid]').forEach(host=>{
            const itemKey=host.closest('[data-item]')?.dataset.item;const item=groups.flatMap(group=>group.items).find(row=>row.key===itemKey);if(!item)return;
            const detailHost=host.parentElement.querySelector('[data-work-adjustment-detail]');if(!item.work_lines.some(line=>line.key===item.adjustment_line_key))item.adjustment_line_key='';
            const renderAdjustmentDetail=()=>{const line=item.work_lines.find(candidate=>candidate.key===item.adjustment_line_key);detailHost.classList.toggle('is-hidden',!line);if(!line){detailHost.replaceChildren();return;}detailHost.innerHTML=`<div class="business-income-work-adjustment__title"><strong>${escapeHtml(line.item_name||'선택 작업내역')} 증감</strong><small>금액에 반영할 증감액과 사유를 입력합니다.</small></div><label><span>${physicalLabelHtml('workLine','adjustment_amount','증감액')}</span><input class="form-control text-end" type="number" step="0.01" data-adjustment-field="adjustment_amount" value="${line.adjustment_amount}" ${editable?'':'disabled'}></label><label class="is-reason"><span>${physicalLabelHtml('workLine','adjustment_reason','증감사유')}</span><input class="form-control" data-adjustment-field="adjustment_reason" value="${escapeHtml(line.adjustment_reason)}" placeholder="증감액이 있으면 필수" ${editable?'':'disabled'}></label>`;detailHost.querySelectorAll('[data-adjustment-field]').forEach(input=>input.addEventListener('input',()=>{line[input.dataset.adjustmentField]=input.type==='number'?number(input.value):input.value;refreshWorkAmounts(item);const finalCell=host.querySelector(`[data-row-id="${CSS.escape(line.key)}"][data-column-key="final_amount"] .html-grid-cell-value`);if(finalCell){const displayAmount=formatAmount(line.final_amount);finalCell.textContent=displayAmount;finalCell.title=displayAmount;}invalidateWorkLineCell();}));};
            let grid;
            const orderEditor=context=>{const wrap=document.createElement('div');wrap.className='business-income-work-order';const sequence=document.createElement('strong');sequence.textContent=String(context.row.values.sort_no||'');const handle=document.createElement('span');handle.className='business-income-work-drag-handle';handle.innerHTML='<i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>';handle.title=editable?'드래그하여 순서 변경':'순서 변경 불가';if(editable){handle.draggable=true;handle.addEventListener('dragstart',event=>{event.dataTransfer.setData('text/plain',context.row.rowId);});handle.addEventListener('dragover',event=>event.preventDefault());handle.addEventListener('drop',event=>{event.preventDefault();const sourceKey=event.dataTransfer.getData('text/plain');const from=item.work_lines.findIndex(line=>line.key===sourceKey);const to=item.work_lines.findIndex(line=>line.key===context.row.rowId);if(from<0||to<0||from===to)return;const [moved]=item.work_lines.splice(from,1);item.work_lines.splice(to,0,moved);invalidate();});}wrap.append(sequence,handle);return{element:wrap,create:()=>wrap,mount:target=>target.append(wrap),getValue:()=>context.row.values.sort_no};};
            const adjustmentEditor=context=>{const wrap=document.createElement('div');wrap.className='business-income-work-line-actions';const detail=document.createElement('button');detail.type='button';detail.className='business-income-work-line-action is-primary';detail.title='증감 입력 열기/닫기';detail.setAttribute('aria-label',detail.title);detail.setAttribute('aria-expanded',item.adjustment_line_key===context.row.rowId?'true':'false');detail.innerHTML='<i class="fa-solid fa-sliders" aria-hidden="true"></i>';detail.addEventListener('click',()=>{item.adjustment_line_key=item.adjustment_line_key===context.row.rowId?'':context.row.rowId;renderAdjustmentDetail();host.querySelectorAll('[data-column-key="adjustment_action"] [aria-expanded]').forEach(button=>button.setAttribute('aria-expanded',button===detail&&item.adjustment_line_key===context.row.rowId?'true':'false'));});wrap.append(detail);return{element:wrap,create:()=>wrap,mount:target=>target.append(wrap),getValue:()=>''};};
            const actionEditor=context=>{const wrap=document.createElement('div');wrap.className='business-income-work-line-actions';const copy=document.createElement('button');copy.type='button';copy.className='business-income-work-line-action';copy.title='복제';copy.setAttribute('aria-label',copy.title);copy.innerHTML='<i class="fa-regular fa-copy" aria-hidden="true"></i>';copy.addEventListener('click',()=>{const index=item.work_lines.findIndex(line=>line.key===context.row.rowId);item.work_lines.splice(index+1,0,createWorkLine({...item.work_lines[index],id:null,key:token()}));invalidate();});const remove=document.createElement('button');remove.type='button';remove.className='business-income-work-line-action is-danger';remove.title='삭제';remove.setAttribute('aria-label',remove.title);remove.innerHTML='<i class="fa-regular fa-trash-can" aria-hidden="true"></i>';remove.addEventListener('click',()=>{item.work_lines=item.work_lines.filter(line=>line.key!==context.row.rowId);invalidate();});wrap.append(copy,remove);return{element:wrap,create:()=>wrap,mount:target=>target.append(wrap),getValue:()=>''};};
            const rows=item.work_lines.map((line,index)=>({rowId:line.key,rowState:editable?'clean':'readonly',values:{...line,sort_no:index+1}}));
            const unitColumnOptions=[{value:'',label:'단위 선택'},...unitOptions];
            const columns=[
                {key:'sort_no',label:'순번',width:58,editor:'business-income-work-order',editable:false},{key:'item_name',label:physicalLabel('workLine','item_name','품명'),editor:'text',required:physicalRequired('workLine','item_name'),width:220},{key:'item_specification',label:physicalLabel('workLine','item_specification','규격'),editor:'text',required:physicalRequired('workLine','item_specification'),width:180},{key:'item_unit_name',label:physicalLabel('workLine','item_unit_name','단위'),editor:'select',formatter:'option',plugins:['select2'],required:physicalRequired('workLine','item_unit_name'),width:100,meta:{options:unitColumnOptions,editorOptions:{options:unitColumnOptions},formatterOptions:{options:unitColumnOptions},pluginOptions:{select2:{width:'100%',dropdownParent:modalParent,minimumResultsForSearch:0}}}},
                {key:'item_quantity',label:physicalLabel('workLine','item_quantity','수량'),type:'number',formatter:'number',editor:'number',required:physicalRequired('workLine','item_quantity'),width:100,meta:{editorOptions:{allowNegative:false,maximumFractionDigits:4}}},{key:'item_unit_price',label:physicalLabel('workLine','item_unit_price','단가'),type:'number',formatter:'number',editor:'number',required:physicalRequired('workLine','item_unit_price'),width:120,meta:{editorOptions:{allowNegative:false,maximumFractionDigits:2}}},
                {key:'calculated_amount',label:physicalLabel('workLine','calculated_amount','금액'),type:'number',formatter:'number',editable:false,width:120},
                {key:'adjustment_action',label:'증감',editor:'business-income-work-adjustment',editable:false,width:64},
                {key:'final_amount',label:physicalLabel('workLine','final_amount','확정금액'),type:'number',formatter:'number',editable:false,width:120},
                {key:'row_action',label:'관리',editor:'business-income-work-action',editable:false,width:84},
            ].map(column=>editable?column:{...column,editor:undefined,editable:false});
            grid=createHtmlGrid({host,gridId:`business-income-work-lines-${item.key}`,rows,columns,editors:{'business-income-work-order':orderEditor,'business-income-work-adjustment':adjustmentEditor,'business-income-work-action':actionEditor},adapters:{select2:({editorElement,options})=>{PickerSelect2.create(editorElement,{...options,width:'100%',dropdownParent:modalParent,allowClear:false});return{destroy:()=>PickerSelect2.destroy(editorElement)};}},rowNumberField:'sort_no',keepHeaderWhenEmpty:true,commitEditorsOnChange:true,emptyMessage:'등록된 외주 작업내역이 없습니다. 작업내역을 추가해 주세요.',capabilities:{addRow:false,deleteRow:false,reorder:editable,selection:false,footer:false,keyboard:true,columnResize:false,columnHide:false,columnMove:false,clipboard:true}});
            grid.on('cell:changed',({row,columnKey})=>{const line=item.work_lines.find(candidate=>candidate.key===row.rowId);if(!line)return;line[columnKey]=row.values[columnKey];refreshWorkAmounts(item);[['calculated_amount',line.calculated_amount],['final_amount',line.final_amount]].forEach(([key,value])=>{const cell=host.querySelector(`[data-row-id="${CSS.escape(row.rowId)}"][data-column-key="${key}"] .html-grid-cell-value`);if(cell){const displayAmount=formatAmount(value);cell.textContent=displayAmount;cell.title=displayAmount;}});invalidateWorkLineCell();});
            grid.render();renderAdjustmentDetail();workLineGrids.push(grid);
        });
    }
    function initializePickers(editable){
        groupsHost.querySelectorAll('select').forEach(select=>{
            const group=groups.find(row=>row.key===select.closest('[data-group]')?.dataset.group);if(!group)return;
            const field=select.dataset.itemField||select.dataset.field;
            const optionType={business_unit:'business_unit',project_id:'project',work_team_id:'work_team',client_id:'recipient',service_type_code:'work_type'}[field];
            if(!optionType)return;
            select.disabled=!editable||select.disabled;
            window.setTimeout(()=>{
                if(!select.isConnected)return;
                select.dataset.pickerReady='false';
                const picker=PickerSelect2.createAjax(select,{
                    placeholder:field==='client_id'?'사업소득자 선택':'선택(없음)',
                    minimumInputLength:0,
                    allowClear:true,
                    dropdownParent:modalParent,
                    url:API.OPTIONS,
                    dataBuilder:params=>({option_type:optionType,q:params.term||'',page:params.page||1,business_unit:group.business_unit||''}),
                    processResults:data=>({results:data.data?.results||[],pagination:{more:data.data?.has_more===true}}),
                });
                if(field==='business_unit'){
                    const commitBusinessUnit=rawValue=>{
                        if(!select.isConnected)return;
                        const nextValue=referenceKey(rawValue);
                        group.business_unit=nextValue;
                        group.project_id='';
                        group.work_team_id='';
                        invalidate();
                    };
                    picker?.off('select2:select.businessIncomeBusiness select2:clear.businessIncomeBusiness')
                        .on('select2:select.businessIncomeBusiness',event=>commitBusinessUnit(event.params?.data?.id??select.value))
                        .on('select2:clear.businessIncomeBusiness',()=>commitBusinessUnit(''));
                }
            },0);
        });
    }
    function calculationMissingReasons(){
        const reasons=[];
        if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(form.elements.income_year_month.value))reasons.push('귀속연월을 입력해 주세요.');
        if(!isIncomeWithholdingDate(withholdingDateInput.value))reasons.push('원천징수일을 입력하면 해당 날짜의 법정기준으로 자동 계산됩니다.');
        if(!groups.length)reasons.push('소득그룹을 추가해 주세요.');
        groups.forEach((group,groupIndex)=>{
            const groupLabel=`${groupIndex+1}번째 소득그룹`;
            const policy=businessUnitPolicy(group.business_unit);
            if(!policy)reasons.push(`${groupLabel}의 사업구분을 선택해 주세요.`);
            if(policy?.requires_project&&!group.project_id)reasons.push(`${groupLabel}의 프로젝트를 선택해 주세요.`);
            if(policy?.requires_work_team&&!group.work_team_id)reasons.push(`${groupLabel}의 작업팀을 선택해 주세요.`);
            if(!group.items.length)reasons.push(`${groupLabel}에 소득자를 추가해 주세요.`);
            group.items.forEach((item,itemIndex)=>{
                const itemLabel=`${groupLabel} ${itemIndex+1}번째 소득자`;
                if(!/^\d{4}-\d{2}-\d{2}$/.test(item.transaction_date))reasons.push(`${itemLabel}의 거래일을 입력해 주세요.`);
                if(!item.client_id)reasons.push(`${itemLabel}를 선택해 주세요.`);
                if(!item.service_type_code)reasons.push(`${itemLabel}의 공종을 선택해 주세요.`);
                if(!String(item.service_description||'').trim())reasons.push(`${itemLabel}의 작업내용을 입력해 주세요.`);
                if(!item.work_lines.length)reasons.push(`${itemLabel}에 외주 작업내역을 추가해 주세요.`);
                item.work_lines.forEach((line,lineIndex)=>{
                    const lineLabel=`${itemLabel}의 ${lineIndex+1}번째 작업내역`;
                    if(!String(line.item_name||'').trim())reasons.push(`${lineLabel} 품명을 입력해 주세요.`);
                    if(!String(line.item_unit_name||'').trim())reasons.push(`${lineLabel} 단위를 선택해 주세요.`);
                    if(number(line.item_quantity)<=0)reasons.push(`${lineLabel} 수량은 0보다 커야 합니다.`);
                    if(number(line.item_unit_price)<0)reasons.push(`${lineLabel} 단가는 0 이상이어야 합니다.`);
                    if(number(line.final_amount)<0)reasons.push(`${lineLabel} 확정금액은 0 이상이어야 합니다.`);
                    if(number(line.adjustment_amount)!==0&&!String(line.adjustment_reason||'').trim())reasons.push(`${lineLabel}의 증감사유를 입력해 주세요.`);
                });
            });
        });
        return [...new Set(reasons)];
    }
    function renderCalculationGuidance(){
        const reasons=calculationMissingReasons();
        if(calculationError)reasons.unshift(`자동계산 오류: ${calculationError}`);
        calculationGuidance.classList.toggle('d-none',reasons.length===0);
        calculationGuidance.innerHTML=reasons.length?`<strong>계산 확인사항</strong><br>${reasons.map(reason=>escapeHtml(reason)).join('<br>')}`:'';
    }
    function renderTotals(){const totals=documentDetailTotals();preview.innerHTML=`<div class="business-income-preview-grid">${metricCard('소득그룹',`${totals.group_count}개`)}${metricCard('소득자',`${totals.recipient_count}명`)}${metricCard('외주 작업',`${totals.work_line_count}건`)}${amountCard('총지급액(세전)',totals.gross_payment_amount)}${settlementCard('사업소득세',totals.income_tax_amount)}${settlementCard('개인지방소득세',totals.local_income_tax_amount)}${settlementCard('총공제액',totals.total_deduction_amount)}${settlementCard('최종지급액',totals.net_payment_amount)}</div>`;renderCalculationGuidance();}
    function canAutoCalculate(){
        return !readOnly&&calculationMissingReasons().length===0;
    }
    function applyCalculation(resultGroups){
        resultGroups.forEach((resultGroup,groupIndex)=>{
            const group=groups[groupIndex];if(!group)return;
            (resultGroup.items||[]).forEach((result,itemIndex)=>{
                const item=group.items[itemIndex];if(!item)return;
                Object.assign(item,{
                    gross_payment_amount:number(result.gross_payment_amount),income_tax_amount:number(result.income_tax_amount),
                    local_income_tax_amount:number(result.local_income_tax_amount),
                    total_deduction_amount:number(result.total_deduction_amount),net_payment_amount:number(result.net_payment_amount),
                    lines:Array.isArray(result.lines)?result.lines.map(line=>({...line})):[],
                    client_tax_profile_id:result.client_tax_profile_id||item.client_tax_profile_id,recipient_tax_snapshot_json:result.recipient_tax_snapshot_json||item.recipient_tax_snapshot_json,
                });
            });
        });
    }
    async function calculateAutomatically(){
        const version=++calculationRequestVersion;calculationAbortController?.abort();calculationAbortController=new AbortController();calculationState='loading';calculationError='';syncActions();
        try{
            const response=await request(API.CALCULATE,{method:'POST',signal:calculationAbortController.signal,body:JSON.stringify({income_year_month:form.elements.income_year_month.value,withholding_date:withholdingDateInput.value,groups})});
            if(version!==calculationRequestVersion)return;
            applyCalculation(response.data?.groups||[]);calculated=true;calculationState='complete';calculationError='';renderLiveAmounts();syncActions();
        }catch(error){
            if(error?.name==='AbortError'||version!==calculationRequestVersion)return;
            calculated=false;calculationState='error';calculationError=error.message||'자동계산 중 오류가 발생했습니다.';renderLiveAmounts();syncActions();
        }
    }
    function scheduleAutoCalculation(){
        if(calculationTimer!==null)window.clearTimeout(calculationTimer);calculationTimer=null;
        calculationAbortController?.abort();calculationRequestVersion+=1;
        if(!canAutoCalculate())return;
        calculationTimer=window.setTimeout(()=>{calculationTimer=null;calculateAutomatically();},0);
    }

    groupsHost.addEventListener('input',event=>{
        const editor=event.target.closest?.('[data-work-line-grid] .html-grid-editor');if(!editor)return;
        const item=groups.flatMap(group=>group.items).find(candidate=>candidate.key===editor.closest('[data-item]')?.dataset.item);const line=item?.work_lines.find(candidate=>candidate.key===editor.closest('[data-row-id]')?.dataset.rowId);const columnKey=editor.closest('[data-column-key]')?.dataset.columnKey;if(!item||!line||!columnKey)return;
        line[columnKey]=editor.type==='number'?number(editor.value):editor.value;refreshWorkAmounts(item);resetSettlementAmounts(item);
        [['calculated_amount',line.calculated_amount],['final_amount',line.final_amount]].forEach(([key,value])=>{const cell=editor.closest('[data-work-line-grid]').querySelector(`[data-row-id="${CSS.escape(line.key)}"][data-column-key="${key}"] .html-grid-cell-value`);if(cell){const displayAmount=formatAmount(value);cell.textContent=displayAmount;cell.title=displayAmount;}});
        calculated=false;saved=false;calculationState='waiting';calculationError='';renderLiveAmounts();syncActions();scheduleAutoCalculation();
    });
    groupsHost.addEventListener('input',event=>{
        const itemField=event.target.dataset.itemField;if(!itemField||event.target.closest('[data-work-line-grid]'))return;
        const item=groups.flatMap(group=>group.items).find(candidate=>candidate.key===event.target.closest('[data-item]')?.dataset.item);if(!item)return;
        item[itemField]=event.target.type==='number'?number(event.target.value):event.target.value;
        const itemElement=event.target.closest('[data-item]');if(itemField==='service_description'){const description=itemElement?.querySelector('.business-income-item__description');if(description)description.textContent=`작업내용: ${item.service_description||'미입력'}`;}
        prepareDraftAmounts();calculated=false;saved=false;calculationState='waiting';calculationError='';renderLiveAmounts();syncActions();scheduleAutoCalculation();
    });

    groupsHost.addEventListener('change',event=>{const group=groups.find(row=>row.key===event.target.closest('[data-group]')?.dataset.group);if(!group)return;const item=group.items.find(row=>row.key===event.target.closest('[data-item]')?.dataset.item);const line=item?.work_lines.find(row=>row.key===event.target.closest('[data-work-line]')?.dataset.workLine);const field=event.target.dataset.lineField||event.target.dataset.itemField||event.target.dataset.field;if(!field)return;(line||item||group)[field]=event.target.type==='number'?number(event.target.value):event.target.value;if(line)refreshWorkAmounts(item);if(field==='client_id'&&item){const selected=optionCache.recipients.find(row=>String(row.id)===String(event.target.value));item.client_name=selected?.name||event.target.selectedOptions[0]?.textContent||'';item.client_type=selected?.client_type||'';item.client_type_name=selected?.client_type_name||'';selectedItemKey=item.key;}if(field==='business_unit'){group.project_id='';group.work_team_id='';}invalidate();});
    groupsHost.addEventListener('click',async event=>{
        const button=event.target.closest('[data-action],[data-open-transaction-date],[data-item-field="transaction_date"]');if(!button)return;const group=groups.find(row=>row.key===button.closest('[data-group]')?.dataset.group);const item=group?.items.find(row=>row.key===button.closest('[data-item]')?.dataset.item);
        if(button.matches('[data-open-transaction-date],[data-item-field="transaction_date"]')){if(readOnly)return;openDatePicker(button.closest('.date-input-wrap').querySelector('input'));return;}
        if(button.dataset.action==='toggle-group'){datePicker.close();activeDateInput=null;group.collapsed=!group.collapsed;render();return;}
        if(button.dataset.action==='select-item'){selectedItemKey=item.key;render();return;}
        if(button.dataset.action==='toggle-item'){selectedItemKey=item.key;item.collapsed=!item.collapsed;render();return;}
        if(button.dataset.action==='add-item'){group.items.forEach(row=>row.collapsed=true);const added=createItem();group.items.push(added);selectedItemKey=added.key;invalidate();return;}
        const workLine=item?.work_lines.find(row=>row.key===button.closest('[data-work-line]')?.dataset.workLine);
        if(button.dataset.action==='add-work-line'){item.work_lines.push(createWorkLine());invalidate();return;}
        if(button.dataset.action==='copy-work-line'){item.work_lines.splice(item.work_lines.indexOf(workLine)+1,0,createWorkLine({...workLine,id:null,key:token()}));invalidate();return;}
        if(button.dataset.action==='remove-work-line'){item.work_lines=item.work_lines.filter(row=>row!==workLine);invalidate();return;}
        if(button.dataset.action==='copy-item'){group.items.forEach(row=>row.collapsed=true);const copied=copyItem(item);group.items.splice(group.items.indexOf(item)+1,0,copied);selectedItemKey=copied.key;invalidate();return;}
        if(button.dataset.action==='remove-item'){group.items=group.items.filter(row=>row!==item);if(selectedItemKey===item.key)selectedItemKey=group.items[0]?.key||'';invalidate();return;}
        if(button.dataset.action==='copy-group'){const copied=copyGroup(group);groups.splice(groups.indexOf(group)+1,0,copied);selectedItemKey=copied.items[0]?.key||'';invalidate();return;}
        if(button.dataset.action==='remove-group'){if(group.items.length&&!await confirmDialog(`소득그룹 ${groups.indexOf(group)+1}과 소득자 ${group.items.length}명의 입력내역을 삭제하시겠습니까?`))return;groups=groups.filter(row=>row!==group);if(group.items.some(row=>row.key===selectedItemKey))selectedItemKey=groups.flatMap(row=>row.items)[0]?.key||'';invalidate();}
    });

    function setYearMonth(value){const date=parseYearMonthValue(value);form.elements.income_year_month.value=date?formatYearMonthValue(date):'';yearMonthDisplay.value=form.elements.income_year_month.value;if(date)yearMonthPicker.setYearMonth(date);else yearMonthPicker.state.date=null;}
    function applyDefaultWithholdingDate(){withholdingDateInput.value=incomeWithholdingDate(form.elements.income_year_month.value,INCOME_WITHHOLDING_RULES.MONTH_END);withholdingDateInput.dispatchEvent(new Event('change',{bubbles:true}));}
    function dateParts(value){const match=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})$/);if(!match)return null;const year=Number(match[1]);const month=Number(match[2]);const day=Number(match[3]);const date=new Date(year,month-1,day);return date.getFullYear()===year&&date.getMonth()===month-1&&date.getDate()===day?{year,month:month-1,day}:null;}
    function openDatePicker(input){
        yearMonthPicker.close();datePicker.close();activeDateInput=input;
        const selected=dateParts(input.value);const month=parseYearMonthValue(form.elements.income_year_month.value);const base=selected||{year:month?.getFullYear()||new Date().getFullYear(),month:month?.getMonth()??new Date().getMonth(),day:1};
        syncingDatePicker=true;datePicker.clearDate();datePicker.setView(base.year,base.month);syncingDatePicker=false;datePicker.open({anchor:input});
    }
    function openYearMonth(){if(document.getElementById('businessIncomeYearMonthButton').getAttribute('aria-disabled')==='true')return;datePicker.close();activeDateInput=null;yearMonthPicker.open({anchor:yearMonthDisplay});}
    yearMonthPicker.subscribe((_,date)=>{setYearMonth(date?formatYearMonthValue(date):'');applyDefaultWithholdingDate();yearMonthPicker.close();invalidate();});
    datePicker.subscribe((_,date)=>{if(syncingDatePicker||!date||!activeDateInput)return;activeDateInput.value=[date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');datePicker.close();activeDateInput.dispatchEvent(new Event('change',{bubbles:true}));});
    document.getElementById('businessIncomeYearMonthButton').addEventListener('click',openYearMonth);
    document.getElementById('businessIncomeWithholdingDateButton').addEventListener('click',()=>{if(!readOnly)openDatePicker(withholdingDateInput);});
    withholdingDateInput.addEventListener('click',()=>{if(!readOnly)openDatePicker(withholdingDateInput);});
    withholdingDateInput.addEventListener('change',invalidate);
    yearMonthDisplay.addEventListener('click',openYearMonth);
    yearMonthDisplay.addEventListener('input',()=>{yearMonthDisplay.value=normalizeYearMonthInputValue(yearMonthDisplay.value);const date=parseYearMonthValue(yearMonthDisplay.value);form.elements.income_year_month.value=date?formatYearMonthValue(date):'';if(date){yearMonthPicker.setYearMonth(date);applyDefaultWithholdingDate();}invalidate();});
    bindModalCardCollapses(modalElement,{resetOnShow:true});
    renderSystemInfo();
    document.getElementById('businessIncomeAddGroup').addEventListener('click',()=>{groups.forEach(group=>group.collapsed=true);const group=createGroup({items:[{}]});groups.push(group);selectedItemKey=group.items[0].key;invalidate();});

    const excelModalElement=document.getElementById('businessIncomeExcelModal');
    const excelModal=bootstrap.Modal.getOrCreateInstance(excelModalElement,{focus:false});
    document.getElementById('businessIncomeExcelManager').addEventListener('click',()=>{if(readOnly){notify('warning','수정 가능한 문서에서만 엑셀 업로드를 사용할 수 있습니다.');return;}excelModal.show();});
    await createBusinessIncomeExcelProvider({
        formSelector:'#business-income-excel-form',downloadUrl:API.EXCEL_DOWNLOAD,
        getGroups:()=>groups,getIncomeYearMonth:()=>form.elements.income_year_month.value,
        getHeader:()=>({income_year_month:form.elements.income_year_month.value,withholding_date:withholdingDateInput.value,title:form.elements.title.value,description:form.elements.description.value,memo:form.elements.memo.value}),
        confirmPreview:summary=>confirmDialog({title:'엑셀 Preview 적용',message:`소득그룹 ${summary.group_count||0}개, 소득자 지급내역 ${summary.row_count||0}행을 현재 문서에 반영하시겠습니까? DB에는 아직 저장되지 않습니다.`,confirmText:'현재 문서에 적용',confirmClass:'btn-success'}),
        applyPreview:(previewGroups,totals)=>{groups=previewGroups.map(createGroup);calculated=true;calculationState='complete';saved=false;renderTotals(totals);render();},
    });

    const payload=()=>({id:form.elements.id.value,income_year_month:form.elements.income_year_month.value,withholding_date:withholdingDateInput.value,title:form.elements.title.value,description:form.elements.description.value,memo:form.elements.memo.value,groups});
    form.addEventListener('submit',async event=>{event.preventDefault();try{const result=await request(API.SAVE,{method:'POST',body:JSON.stringify(payload())});form.elements.id.value=result.data.id;saved=true;calculated=true;calculationState='complete';detailState={...(detailState||{}),id:result.data.id,document_status:detailState?.document_status||'DRAFT',calculation_status:'CALCULATED'};render();notify('success',result.message);table.ajax.reload(null,false);}catch(error){notify('error',error.message||'저장 중 오류가 발생했습니다.');}});
    document.getElementById('businessIncomeSubmit').addEventListener('click',async()=>{try{const id=form.elements.id.value;await request(`${API.PREFLIGHT}?id=${encodeURIComponent(id)}`);if(!await confirmDialog('사업소득 문서를 결재요청하시겠습니까?'))return;const result=await request(API.SUBMIT,{method:'POST',body:JSON.stringify({id})});notify('success',result.message);modal.hide();table.ajax.reload(null,false);}catch(error){notify('warning',error.message||'결재요청 중 오류가 발생했습니다.');}});

    function openCreate(){form.reset();setYearMonth('');detailState=null;readOnly=false;calculated=false;calculationState='idle';saved=false;groups=[];selectedItemKey='';applyFormSettings();renderTotals();renderSystemInfo();render();modal.show();}
    async function openDetail(id){try{const result=await request(`${API.DETAIL}?id=${encodeURIComponent(id)}`);detailState=result.data;readOnly=!['DRAFT','REJECTED','WITHDRAWN'].includes(detailState.document_status);calculated=detailState.calculation_status==='CALCULATED';calculationState=calculated?'complete':'idle';saved=true;form.reset();form.elements.id.value=detailState.id;setYearMonth(detailState.income_year_month);form.elements.title.value=detailState.title;form.elements.description.value=detailState.description||'';form.elements.memo.value=detailState.memo||'';groups=detailState.groups.map(createGroup);selectedItemKey=groups.flatMap(group=>group.items)[0]?.key||'';applyFormSettings();renderTotals();renderSystemInfo();render();modal.show();}catch(error){notify('error',error.message||'상세조회 중 오류가 발생했습니다.');}}

    document.getElementById('businessIncomeWithdraw').addEventListener('click',async()=>{if(!detailState?.current_approval_request_id||!await confirmDialog('진행 중인 기안을 회수하시겠습니까?'))return;try{const result=await request(API.WITHDRAW,{method:'POST',body:JSON.stringify({request_id:detailState.current_approval_request_id})});notify('success',result.message);modal.hide();table.ajax.reload(null,false);}catch(error){notify('warning',error.message||'기안회수 중 오류가 발생했습니다.');}});
    document.getElementById('businessIncomeDelete').addEventListener('click',async()=>{if(!await confirmDialog('이 사업소득 문서를 휴지통으로 이동하시겠습니까?'))return;try{const result=await request(API.DELETE,{method:'POST',body:JSON.stringify({id:form.elements.id.value})});notify('success',result.message);modal.hide();table.ajax.reload(null,false);}catch(error){notify('error',error.message||'삭제 중 오류가 발생했습니다.');}});
    modalElement.querySelector('.modal-body').addEventListener('scroll',()=>{yearMonthPicker.close();datePicker.close();activeDateInput=null;},{passive:true});
    modalElement.addEventListener('shown.bs.modal',()=>{withholdingDateInput.value=detailState?.withholding_date||withholdingDateInput.value||'';});
    modalElement.addEventListener('hidden.bs.modal',()=>{yearMonthPicker.close();datePicker.close();groupsHost.querySelectorAll('select').forEach(select=>PickerSelect2.destroy(select));workLineGrids.forEach(grid=>grid.destroy());workLineGrids=[];if(calculationTimer!==null)window.clearTimeout(calculationTimer);calculationTimer=null;calculationAbortController?.abort();calculationAbortController=null;calculationRequestVersion+=1;activeDateInput=null;});
    form.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.target.matches('textarea'))event.preventDefault();});

    optionCache=(await request(API.OPTIONS)).data;
    unitOptions=(await getCodeOptions('UNIT')).map(row=>{const label=String(row.code_name||row.code||'').trim();return{value:label,label};}).filter(row=>row.value!=='');
    await Promise.all(Object.values(modalMetaDomains).map(metaDomain=>fetchDataTableMetaColumns({metaDomain})));
    const applyFormSettings=()=>{const resolved=formSettings.apply();yearMonthDisplay.required=resolved.get('income_year_month')?.required===true;};
    applyFormSettings();
    renderSystemInfo();
    document.addEventListener('datatable-settings:updated',event=>{const key=String(event.detail?.storageKey||'').replace(/^datatable\.settings\./,'');if(key===settingsKey){applyFormSettings();renderSystemInfo();}});
    const badge=value=>`<span class="badge ${value==='APPROVED'||value==='CALCULATED'?'text-bg-success':value==='PENDING'?'text-bg-warning':value==='REJECTED'?'text-bg-danger':'text-bg-secondary'}">${escapeHtml(statusLabel(value))}</span>`;
    const columns=[{data:'income_year_month',settingsKey:'income_year_month',title:'귀속연월'},{data:'title',settingsKey:'title',title:'문서제목'},{data:'item_count',settingsKey:'item_count',title:'소득자 지급내역 수'},{data:'total_gross_payment_amount',settingsKey:'total_gross_payment_amount',title:'총지급액',className:'text-end',render:formatAmount},{data:'total_net_payment_amount',settingsKey:'total_net_payment_amount',title:'최종지급액',className:'text-end',render:formatAmount},{data:'document_status',settingsKey:'document_status',title:'업무문서 상태',render:(value,type)=>type==='display'?badge(value):value},{data:'calculation_status',settingsKey:'calculation_status',title:'계산 상태',render:(value,type)=>type==='display'?badge(value):value},{data:'approval_status',settingsKey:'approval_status',title:'결재 상태',render:(value,type)=>type==='display'?badge(value):value},{data:'created_by_name',settingsKey:'created_by',title:'등록자',defaultContent:'-'},{data:'created_at',settingsKey:'created_at',title:'등록일시',defaultContent:'-'},{data:null,settingsKey:'__actions',title:'관리',orderable:false,searchable:false,className:'no-colvis',render:()=>'<button class="btn btn-outline-primary btn-sm" data-detail>상세</button>'}];
    const table=await createDataTable({tableSelector:'#business-income-table',api:API.LIST,serverSide:true,selectable:true,searchTableId:'businessIncome',columns,tableSettings:{pageKey:'institution.income-data.business-income',tableKey:'business-income-table',storageKey:settingsKey,metaDomain:'business-income',tableLabel:'사업소득'},buttons:[{text:'휴지통',className:'btn btn-danger btn-sm',action:()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('businessIncomeTrashModal')).show()},{text:'신규등록',className:'btn btn-warning btn-sm',action:openCreate}]});
    SearchForm({table,apiList:API.LIST,tableId:'businessIncome',defaultSearchField:'income_year_month'});
    document.querySelector('#business-income-table tbody')?.addEventListener('click',event=>{const button=event.target.closest('[data-detail]');if(!button)return;const row=table.row(button.closest('tr')).data();if(row?.id)openDetail(row.id);});
    window.TrashColumns=window.TrashColumns||{};window.TrashColumns['business-income']=row=>`<td>${escapeHtml(row.income_year_month||'-')}</td><td>${escapeHtml(row.title||'-')}</td><td>${escapeHtml(row.document_status||'-')}</td><td>${escapeHtml(row.deleted_at||'-')}</td><td></td>`;
    document.addEventListener('trash:changed',event=>{if(event.detail?.type==='business-income')table.ajax.reload(null,false);});
}
