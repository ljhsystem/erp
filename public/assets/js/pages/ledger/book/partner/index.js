import { bindTableHighlight,createDataTable,refreshDataTableLayout } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';

const API={list:'/api/ledger/book/partner/list',clientSearch:'/api/settings/base-info/client/search-picker'};
const money=(value)=>`${Math.round(Number(value)||0).toLocaleString('ko-KR')}원`;
const escapeHtml=(value)=>String(value??'').replace(/[&<>"']/g,(char)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const statusLabel=(value)=>({POSTED:'전기완료',CLOSED:'마감'}[String(value||'').toUpperCase()]||String(value||''));
const columns=[
 {data:'account_code',name:'account_code',settingsKey:'account_code',title:'계정코드',width:'95px'},
 {data:'account_name',name:'account_name',settingsKey:'account_name',title:'계정과목',width:'140px'},
 {data:'voucher_date',name:'voucher_date',settingsKey:'voucher_date',title:'전표일자',className:'text-center',width:'105px'},
 {data:'voucher_no',name:'voucher_no',settingsKey:'voucher_no',title:'전표번호',width:'140px'},
 {data:'voucher_status',name:'voucher_status',settingsKey:'voucher_status',title:'전표상태',className:'text-center',render:(value,type)=>type==='display'?escapeHtml(statusLabel(value)):value},
 {data:'counterpart_summary',name:'counterpart_summary',settingsKey:'counterpart_summary',title:'상대계정',defaultContent:'',orderable:false,render:(value,type)=>type==='display'?escapeHtml(value):value},
 {data:'line_summary',name:'line_summary',settingsKey:'line_summary',title:'적요',defaultContent:'',render:(value,type)=>type==='display'?escapeHtml(value):value},
 {data:'ref_summary',name:'ref_summary',settingsKey:'ref_summary',title:'보조원장',defaultContent:'',orderable:false,render:(value,type)=>type==='display'?escapeHtml(value):value},
 {data:'debit',name:'debit',settingsKey:'debit',title:'차변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
 {data:'credit',name:'credit',settingsKey:'credit',title:'대변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
 {data:'running_balance',name:'running_balance',settingsKey:'running_balance',title:'계정별 누적잔액',className:'text-end',orderable:false,render:(value,type,row)=>type==='display'?`<span class="partner-balance-direction">${String(row.normal_balance||'DEBIT').toUpperCase()==='CREDIT'?'대':'차'}</span>${money(value)}`:Number(value||0)},
 {data:'evidence_count',name:'evidence_count',settingsKey:'evidence_count',title:'연결증빙',className:'text-center',render:(value,type)=>type==='display'?`${Number(value||0)}건`:Number(value||0)},
];
function updateSummary(summary={}){const values={partnerAccountCount:`${Number(summary.account_count||0)}개`,partnerOpeningDebit:money(summary.opening_debit),partnerOpeningCredit:money(summary.opening_credit),partnerPeriodDebit:money(summary.period_debit),partnerPeriodCredit:money(summary.period_credit),partnerEndingDebit:money(summary.ending_debit),partnerEndingCredit:money(summary.ending_credit),partnerVoucherLines:`${Number(summary.voucher_count||0)}건 / ${Number(summary.line_count||0)}건`};Object.entries(values).forEach(([id,value])=>{const el=document.getElementById(id);if(el)el.textContent=value;});}

(async()=>{
 const tableEl=document.getElementById('ledgerPartnerBookTable');const client=document.getElementById('partnerLedgerClient');
 if(!tableEl||!client||!window.jQuery?.fn?.DataTable)return;
 if(AdminPicker?.select2Ajax){AdminPicker.select2Ajax(client,{url:API.clientSearch,placeholder:'거래처 검색',minimumInputLength:0,width:'100%',dataBuilder:(params)=>({q:params.term||'',limit:30}),processResults(json){const rows=json?.results??json?.data??[];return{results:rows.map((row)=>({id:String(row.id??''),text:String(row.text||row.client_name||row.name||'-'),raw:row})).filter((item)=>item.id)}}});}
 else if(window.jQuery?.fn?.select2)window.jQuery(client).select2({width:'100%',placeholder:'거래처 검색',allowClear:true});
 const table=await createDataTable({tableSelector:'#ledgerPartnerBookTable',api:API.list,serverSide:true,columns,orderableColumnKeys:['account_code','account_name','voucher_date','voucher_no','voucher_status','line_summary','debit','credit','evidence_count'],ajaxData(request){return{...request,client_id:client.value||''};},tableSettings:{enabled:true,pageKey:'ledger.book.partner',tableKey:'ledger-partner-book-table',storageKey:'datatable.settings.ledger.book.partner.v1',userSettingPageKey:'ledger.book.partner',metaDomain:'ledger-partner-book',description:'거래처원장 분개 Projection 설정',tableLabel:'거래처원장',title:'거래처원장 테이블 설정'},dataSrc(json){updateSummary(json?.summary||{});return Array.isArray(json?.data)?json.data:[];},defaultOrder:[{key:'account_code',dir:'asc'}],pageLength:100,selectable:false,showCopyButton:false,deleteButton:false});
 SearchForm({table,apiList:API.list,tableId:'ledgerPartnerBook',defaultSearchField:'voucher_no',dateOptions:[{value:'voucher_date',label:'전표일자'}],searchFields:[{value:'voucher_no',label:'전표번호'},{value:'account_code',label:'계정코드'},{value:'account_name',label:'계정과목'},{value:'line_summary',label:'적요'}],excludeFields:['id','voucher_id','account_id']});
 table.on('draw.dt',()=>{document.getElementById('ledgerPartnerBookCount').textContent=client.value?`총 ${table.page.info()?.recordsDisplay??0}건`:'거래처를 선택해 주세요';});
 client.addEventListener('change',()=>table.ajax.reload());
 window.jQuery(tableEl).on('dblclick','tbody tr',function(){const row=table.row(this).data();if(row?.voucher_id)window.location.href=`/ledger/vouchers/input?voucher_id=${encodeURIComponent(row.voucher_id)}`;});
 bindTableHighlight('#ledgerPartnerBookTable',table);refreshDataTableLayout(table,{delays:[0]});
})();
