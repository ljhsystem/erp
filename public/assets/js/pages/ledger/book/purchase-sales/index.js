import { bindTableHighlight,createDataTable,refreshDataTableLayout } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
const API={list:'/api/ledger/book/purchase-sales/list'};
const money=(value)=>`${Math.round(Number(value)||0).toLocaleString('ko-KR')}원`;
const escapeHtml=(value)=>String(value??'').replace(/[&<>"']/g,(char)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const evidenceLabel=(value)=>({TAX_INVOICE:'전자세금계산서',TAX_INVOICE_MANUAL:'수기 세금계산서',CASH_RECEIPT:'현금영수증',CARD_HOMETAX:'카드 국세청',CARD_STATEMENT:'카드명세'}[value]||value||'-');
const statusLabel=(value)=>({POSTED:'전기완료',CLOSED:'마감'}[String(value||'').toUpperCase()]||String(value||''));
const columns=[
 {data:'evidence_date',name:'evidence_date',settingsKey:'evidence_date',title:'증빙일자',className:'text-center',width:'105px'},
 {data:'direction_label',name:'direction_code',settingsKey:'direction_label',title:'구분',className:'text-center',width:'65px',render:(value,type)=>type==='display'?`<span class="direction-badge ${value==='매출'?'is-sales':value==='매입'?'is-purchase':'is-unknown'}">${escapeHtml(value)}</span>`:value},
 {data:'evidence_type',name:'evidence_type',settingsKey:'evidence_type',title:'증빙유형',width:'125px',render:(value,type)=>type==='display'?escapeHtml(evidenceLabel(value)):value},
 {data:'counterparty_name',name:'counterparty_name',settingsKey:'counterparty_name',title:'거래처',defaultContent:''},
 {data:'business_number',name:'business_number',settingsKey:'business_number',title:'사업자번호',className:'text-center',width:'125px'},
 {data:'item_name',name:'item_name',settingsKey:'item_name',title:'품목·업종',defaultContent:''},
 {data:'supply_amount',name:'supply_amount',settingsKey:'supply_amount',title:'공급가액',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
 {data:'vat_amount',name:'vat_amount',settingsKey:'vat_amount',title:'부가세',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
 {data:'total_amount',name:'total_amount',settingsKey:'total_amount',title:'합계금액',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
 {data:'project_name',name:'project_name',settingsKey:'project_name',title:'프로젝트',defaultContent:''},
 {data:'voucher_no',name:'voucher_no',settingsKey:'voucher_no',title:'전표번호',width:'140px'},
 {data:'voucher_status',name:'voucher_status',settingsKey:'voucher_status',title:'전표상태',className:'text-center',render:(value,type)=>type==='display'?escapeHtml(statusLabel(value)):value},
];
function updateSummary(summary={}){for(const [id,value] of Object.entries({salesSupply:money(summary.sales_supply),salesVat:money(summary.sales_vat),salesTotal:money(summary.sales_total),purchaseSupply:money(summary.purchase_supply),purchaseVat:money(summary.purchase_vat),purchaseTotal:money(summary.purchase_total)})){const el=document.getElementById(id);if(el)el.textContent=value;}}
(async()=>{const tableEl=document.getElementById('ledgerPurchaseSalesTable');if(!tableEl||!window.jQuery?.fn?.DataTable)return;
 const table=await createDataTable({tableSelector:'#ledgerPurchaseSalesTable',api:API.list,serverSide:true,columns,orderableColumnKeys:['evidence_date','direction_code','evidence_type','counterparty_name','business_number','supply_amount','vat_amount','total_amount','project_name','voucher_no','voucher_status'],ajaxData(request){return request;},tableSettings:{enabled:true,pageKey:'ledger.book.purchase_sales',tableKey:'ledger-purchase-sales-table',storageKey:'datatable.settings.ledger.book.purchase_sales.v1',userSettingPageKey:'ledger.book.purchase_sales',metaDomain:'ledger-purchase-sales',description:'매입매출장 증빙 Projection 설정',tableLabel:'매입매출장',title:'매입매출장 테이블 설정'},dataSrc(json){updateSummary(json?.summary||{});return Array.isArray(json?.data)?json.data:[];},defaultOrder:[{key:'evidence_date',dir:'desc'}],pageLength:100,selectable:false,showCopyButton:false,deleteButton:false});
 SearchForm({table,apiList:API.list,tableId:'ledgerPurchaseSales',defaultSearchField:'counterparty_name',dateOptions:[{value:'evidence_date',label:'증빙일자'}],searchFields:[{value:'direction_code',label:'매출·매입(매출/매입)'},{value:'evidence_type',label:'증빙유형'},{value:'counterparty_name',label:'거래처'},{value:'business_number',label:'사업자번호'},{value:'voucher_no',label:'전표번호'},{value:'project_name',label:'프로젝트'}],excludeFields:['id','voucher_id','transaction_id']});
 table.on('draw.dt',()=>{document.getElementById('purchaseSalesCount').textContent=`총 ${table.page.info()?.recordsDisplay??0}건`;});window.jQuery(tableEl).on('dblclick','tbody tr',function(){const row=table.row(this).data();if(row?.voucher_id)window.location.href=`/ledger/vouchers/input?voucher_id=${encodeURIComponent(row.voucher_id)}`;});bindTableHighlight('#ledgerPurchaseSalesTable',table);refreshDataTableLayout(table,{delays:[0]});})();
