import { bindTableHighlight, createDataTable, refreshDataTableLayout } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';

const API = {list:'/api/ledger/book/general/list',detail:'/api/ledger/book/general/detail'};
const money = (value) => `${Math.round(Number(value)||0).toLocaleString('ko-KR')}원`;
const escapeHtml = (value) => String(value??'').replace(/[&<>"']/g,(char)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const balance = (value, type, row) => {
    const amount=Number(value||0); if(type!=='display') return amount;
    const direction=String(row?.normal_balance||'DEBIT').toUpperCase()==='CREDIT'?'대':'차';
    return `<span class="general-balance-direction">${direction}</span>${money(amount)}`;
};
const generalColumns = [
    {data:'account_code',name:'account_code',settingsKey:'account_code',title:'계정코드',width:'100px'},
    {data:'account_name',name:'account_name',settingsKey:'account_name',title:'계정과목',width:'180px'},
    {data:'opening_balance',name:'opening_balance',settingsKey:'opening_balance',title:'기초잔액',className:'text-end',render:balance},
    {data:'period_debit',name:'period_debit',settingsKey:'period_debit',title:'당기차변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
    {data:'period_credit',name:'period_credit',settingsKey:'period_credit',title:'당기대변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
    {data:'ending_balance',name:'ending_balance',settingsKey:'ending_balance',title:'기말잔액',className:'text-end',render:balance},
    {data:'line_count',name:'line_count',settingsKey:'line_count',title:'분개건수',className:'text-center',render:(value,type)=>type==='display'?`${Number(value||0)}건`:Number(value||0)},
];
const detailColumns = [
    {data:'voucher_date',name:'voucher_date',settingsKey:'voucher_date',title:'전표일자',className:'text-center',width:'105px'},
    {data:'voucher_no',name:'voucher_no',settingsKey:'voucher_no',title:'전표번호',width:'145px'},
    {data:'line_no',name:'line_no',settingsKey:'line_no',title:'행',className:'text-center',width:'55px'},
    {data:'line_summary',name:'line_summary',settingsKey:'line_summary',title:'적요',defaultContent:'',render:(value,type)=>type==='display'?escapeHtml(value):value},
    {data:'ref_summary',name:'ref_summary',settingsKey:'ref_summary',title:'보조원장',defaultContent:'',orderable:false,render:(value,type)=>type==='display'?escapeHtml(value):value},
    {data:'debit',name:'debit',settingsKey:'debit',title:'차변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
    {data:'credit',name:'credit',settingsKey:'credit',title:'대변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
    {data:'running_balance',name:'running_balance',settingsKey:'running_balance',title:'누적잔액',className:'text-end',orderable:false,render:balance},
    {data:'evidence_count',name:'evidence_count',settingsKey:'evidence_count',title:'연결증빙',className:'text-center',render:(value,type)=>type==='display'?`${Number(value||0)}건`:Number(value||0)},
];

function updateSummary(summary={}) {
    const values={generalAccountCount:`${Number(summary.account_count||0).toLocaleString('ko-KR')}개`,generalOpeningDebit:money(summary.opening_debit),generalOpeningCredit:money(summary.opening_credit),generalPeriodDebit:money(summary.period_debit),generalPeriodCredit:money(summary.period_credit),generalEndingDebit:money(summary.ending_debit),generalEndingCredit:money(summary.ending_credit),generalDifference:money(summary.difference)};
    Object.entries(values).forEach(([id,value])=>{const element=document.getElementById(id);if(element)element.textContent=value;});
    document.getElementById('generalDifference')?.classList.toggle('text-danger',Number(summary.difference||0)!==0);
}

(async()=>{
    const mainEl=document.getElementById('ledgerGeneralBookTable');
    const detailEl=document.getElementById('ledgerGeneralDetailTable');
    if(!mainEl||!detailEl||!window.jQuery?.fn?.DataTable)return;
    let selected=null;
    let mainTable=null;
    const currentFilters=()=>{
        try { return new URL(mainTable.ajax.url(),window.location.origin).searchParams.get('filters')||'[]'; }
        catch(_error){ return '[]'; }
    };
    const detailTable=await createDataTable({
        tableSelector:'#ledgerGeneralDetailTable',api:API.detail,serverSide:true,columns:detailColumns,
        orderableColumnKeys:['voucher_date','voucher_no','line_no','line_summary','debit','credit','evidence_count'],
        ajaxData(request){return {...request,account_id:selected?.id||'',filters:currentFilters()};},
        tableSettings:{enabled:true,pageKey:'ledger.book.general',tableKey:'ledger-general-detail-table',storageKey:'datatable.settings.ledger.book.general.detail.v1',userSettingPageKey:'ledger.book.general',metaDomain:'ledger-general-detail',description:'총계정원장 선택 계정 상세 Projection 설정',tableLabel:'계정 상세원장',title:'계정 상세원장 테이블 설정'},
        dataSrc(json){
            let running=Number(selected?.opening_balance||0); const normal=String(selected?.normal_balance||'DEBIT').toUpperCase();
            return (Array.isArray(json?.data)?json.data:[]).map((row)=>{running+=normal==='CREDIT'?Number(row.credit||0)-Number(row.debit||0):Number(row.debit||0)-Number(row.credit||0);return {...row,normal_balance:normal,running_balance:running};});
        },defaultOrder:[{key:'voucher_date',dir:'asc'}],pageLength:500,selectable:false,showCopyButton:false,deleteButton:false,
    });
    mainTable=await createDataTable({
        tableSelector:'#ledgerGeneralBookTable',api:API.list,serverSide:true,columns:generalColumns,
        orderableColumnKeys:generalColumns.map((column)=>column.data),ajaxData(request){return request;},
        tableSettings:{enabled:true,pageKey:'ledger.book.general',tableKey:'ledger-general-book-table',storageKey:'datatable.settings.ledger.book.general.v1',userSettingPageKey:'ledger.book.general',metaDomain:'ledger-general-book',description:'총계정원장 계정별 집계 Projection 설정',tableLabel:'총계정원장 계정별 집계',title:'총계정원장 테이블 설정'},
        dataSrc(json){return Array.isArray(json?.data)?json.data:[];},defaultOrder:[{key:'account_code',dir:'asc'}],pageLength:100,selectable:false,showCopyButton:false,deleteButton:false,
    });
    SearchForm({table:mainTable,apiList:API.list,tableId:'ledgerGeneralBook',defaultSearchField:'account_code',dateOptions:[{value:'voucher_date',label:'전표일자'}],searchFields:[{value:'account_code',label:'계정코드'},{value:'account_name',label:'계정과목'}],excludeFields:['id','normal_balance']});
    mainTable.on('xhr.dt',(_event,_settings,json)=>updateSummary(json?.summary||{}));
    mainTable.on('draw.dt',()=>{document.getElementById('ledgerGeneralBookCount').textContent=`총 ${mainTable.page.info()?.recordsDisplay??0}개 계정`;});
    detailTable.on('draw.dt',()=>{document.getElementById('generalDetailCount').textContent=selected?`${detailTable.page.info()?.recordsDisplay??0}건`:'';});
    window.jQuery(mainEl).on('click','tbody tr',function(){
        selected=mainTable.row(this).data()||null;
        window.jQuery(mainEl).find('tbody tr').removeClass('is-selected'); this.classList.add('is-selected');
        document.getElementById('generalDetailTitle').textContent=selected?`${selected.account_code} ${selected.account_name}`:'계정 상세원장';
        document.getElementById('generalDetailDescription').textContent=selected?'선택 계정의 전표별 증감과 누적잔액입니다.':'위 목록에서 계정과목을 선택해 주세요.';
        detailTable.ajax.reload();
    });
    bindTableHighlight('#ledgerGeneralBookTable',mainTable); bindTableHighlight('#ledgerGeneralDetailTable',detailTable);
    refreshDataTableLayout(mainTable,{delays:[0]}); refreshDataTableLayout(detailTable,{delays:[0]});
})();
