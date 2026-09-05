import { bindTableHighlight, createDataTable, refreshDataTableLayout } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';

const API={list:'/api/ledger/book/account/list',accounts:'/api/ledger/account/posting'};
const money=(value)=>`${Math.round(Number(value)||0).toLocaleString('ko-KR')}원`;
const escapeHtml=(value)=>String(value??'').replace(/[&<>"']/g,(char)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const statusLabel=(value)=>({POSTED:'전기완료',CLOSED:'마감'}[String(value||'').toUpperCase()]||String(value||''));
const columns=[
    {data:'voucher_date',name:'voucher_date',settingsKey:'voucher_date',title:'전표일자',className:'text-center',width:'105px'},
    {data:'voucher_no',name:'voucher_no',settingsKey:'voucher_no',title:'전표번호',width:'145px'},
    {data:'voucher_status',name:'voucher_status',settingsKey:'voucher_status',title:'전표상태',className:'text-center',render:(value,type)=>type==='display'?escapeHtml(statusLabel(value)):value},
    {data:'counterpart_summary',name:'counterpart_summary',settingsKey:'counterpart_summary',title:'상대계정',defaultContent:'',orderable:false,render:(value,type)=>type==='display'?escapeHtml(value):value},
    {data:'line_summary',name:'line_summary',settingsKey:'line_summary',title:'적요',defaultContent:'',render:(value,type)=>type==='display'?escapeHtml(value):value},
    {data:'ref_summary',name:'ref_summary',settingsKey:'ref_summary',title:'보조원장',defaultContent:'',orderable:false,render:(value,type)=>type==='display'?escapeHtml(value):value},
    {data:'debit',name:'debit',settingsKey:'debit',title:'차변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
    {data:'credit',name:'credit',settingsKey:'credit',title:'대변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
    {data:'running_balance',name:'running_balance',settingsKey:'running_balance',title:'누적잔액',className:'text-end',orderable:false,render:(value,type,row)=>type==='display'?`<span class="account-balance-direction">${String(row.normal_balance||'DEBIT').toUpperCase()==='CREDIT'?'대':'차'}</span>${money(value)}`:Number(value||0)},
    {data:'evidence_count',name:'evidence_count',settingsKey:'evidence_count',title:'연결증빙',className:'text-center',render:(value,type)=>type==='display'?`${Number(value||0)}건`:Number(value||0)},
];

function balanceText(value,normal){return `${normal==='CREDIT'?'대':'차'} ${money(value)}`;}
function updateSummary(summary={}){
    const normal=String(summary.normal_balance||'DEBIT').toUpperCase();
    document.getElementById('accountOpeningBalance').textContent=balanceText(summary.opening_balance,normal);
    document.getElementById('accountPeriodDebit').textContent=money(summary.period_debit);
    document.getElementById('accountPeriodCredit').textContent=money(summary.period_credit);
    document.getElementById('accountEndingBalance').textContent=balanceText(summary.ending_balance,normal);
    document.getElementById('accountVoucherLines').textContent=`${Number(summary.voucher_count||0)}건 / ${Number(summary.line_count||0)}건`;
}

(async()=>{
    const tableEl=document.getElementById('ledgerAccountBookTable');
    const accountSelect=document.getElementById('accountLedgerAccount');
    if(!tableEl||!accountSelect||!window.jQuery?.fn?.DataTable)return;
    const requestedAccountId=new URLSearchParams(window.location.search).get('account_id')||'';
    try{
        const response=await fetch(API.accounts); const json=await response.json();
        (Array.isArray(json?.data)?json.data:[]).forEach((row)=>accountSelect.add(new Option(`${row.account_code} ${row.account_name}`,row.id)));
        if(requestedAccountId&&Array.from(accountSelect.options).some((option)=>option.value===requestedAccountId))accountSelect.value=requestedAccountId;
        if(window.jQuery?.fn?.select2) window.jQuery(accountSelect).select2({width:'100%',placeholder:'계정과목 선택',allowClear:true});
    }catch(_error){document.getElementById('accountLedgerSelectionGuide').textContent='계정과목 목록을 불러오지 못했습니다.';}

    const table=await createDataTable({
        tableSelector:'#ledgerAccountBookTable',api:API.list,serverSide:true,columns,
        orderableColumnKeys:['voucher_date','voucher_no','voucher_status','line_summary','debit','credit','evidence_count'],
        ajaxData(request){return {...request,account_id:accountSelect.value||''};},
        tableSettings:{enabled:true,pageKey:'ledger.book.account',tableKey:'ledger-account-book-table',storageKey:'datatable.settings.ledger.book.account.v1',userSettingPageKey:'ledger.book.account',metaDomain:'ledger-account-book',description:'계정별원장 전표 상세 Projection 설정',tableLabel:'계정별원장',title:'계정별원장 테이블 설정'},
        dataSrc(json){updateSummary(json?.summary||{});return Array.isArray(json?.data)?json.data:[];},
        defaultOrder:[{key:'voucher_date',dir:'asc'}],pageLength:100,selectable:false,showCopyButton:false,deleteButton:false,
    });
    SearchForm({table,apiList:API.list,tableId:'ledgerAccountBook',defaultSearchField:'voucher_no',dateOptions:[{value:'voucher_date',label:'전표일자'}],searchFields:[{value:'voucher_no',label:'전표번호'},{value:'line_summary',label:'적요'},{value:'counterpart_summary',label:'상대계정'}],excludeFields:['id','voucher_id','account_id']});
    table.on('draw.dt',()=>{document.getElementById('ledgerAccountBookCount').textContent=accountSelect.value?`총 ${table.page.info()?.recordsDisplay??0}건`:'계정과목을 선택해 주세요';});
    const reload=()=>table.ajax.reload();
    accountSelect.addEventListener('change',reload);
    window.jQuery(tableEl).on('dblclick','tbody tr',function(){const row=table.row(this).data();if(row?.voucher_id)window.location.href=`/ledger/vouchers/input?voucher_id=${encodeURIComponent(row.voucher_id)}`;});
    bindTableHighlight('#ledgerAccountBookTable',table); refreshDataTableLayout(table,{delays:[0]});
})();
