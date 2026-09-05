import { bindTableHighlight, createDataTable, refreshDataTableLayout } from '/public/assets/js/common/table/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';

const API = { list: '/api/ledger/book/journal/list' };
const PAGE_KEY = 'ledger.book.journal';
const TABLE_KEY = 'ledger-journal-book-table';
const STORAGE_KEY = 'datatable.settings.ledger.book.journal.v1';
const money = (value) => `${Math.round(Number(value) || 0).toLocaleString('ko-KR')}원`;
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

const statusLabel = (value) => ({POSTED:'전기완료',CLOSED:'마감'}[String(value || '').toUpperCase()] || String(value || ''));
const columns = [
    {data:'voucher_date',name:'voucher_date',settingsKey:'voucher_date',title:'전표일자',className:'text-center',width:'105px'},
    {data:'voucher_no',name:'voucher_no',settingsKey:'voucher_no',title:'전표번호',width:'145px'},
    {data:'voucher_status',name:'voucher_status',settingsKey:'voucher_status',title:'전표상태',className:'text-center',render:(value,type)=>type==='display'?escapeHtml(statusLabel(value)):value},
    {data:'line_no',name:'line_no',settingsKey:'line_no',title:'행',className:'text-center',width:'55px'},
    {data:'account_code',name:'account_code',settingsKey:'account_code',title:'계정코드',width:'95px'},
    {data:'account_name',name:'account_name',settingsKey:'account_name',title:'계정과목',width:'140px'},
    {data:'line_summary',name:'line_summary',settingsKey:'line_summary',title:'적요',defaultContent:'',render:(value,type)=>type==='display'?escapeHtml(value||''):value},
    {data:'ref_summary',name:'ref_summary',settingsKey:'ref_summary',title:'보조원장',defaultContent:'',render:(value,type)=>type==='display'?escapeHtml(value||''):value,orderable:false},
    {data:'debit',name:'debit',settingsKey:'debit',title:'차변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
    {data:'credit',name:'credit',settingsKey:'credit',title:'대변',className:'text-end',render:(value,type)=>type==='display'?money(value):Number(value||0)},
    {data:'evidence_count',name:'evidence_count',settingsKey:'evidence_count',title:'연결증빙',className:'text-center',render:(value,type)=>type==='display'?`${Number(value||0)}건`:Number(value||0)},
];

function updateSummary(summary = {}) {
    document.getElementById('journalVoucherCount').textContent = `${Number(summary.voucher_count || 0).toLocaleString('ko-KR')}건`;
    document.getElementById('journalLineCount').textContent = `${Number(summary.line_count || 0).toLocaleString('ko-KR')}건`;
    document.getElementById('journalDebitTotal').textContent = money(summary.debit_total);
    document.getElementById('journalCreditTotal').textContent = money(summary.credit_total);
    const difference = Number(summary.difference || 0);
    const differenceEl = document.getElementById('journalDifference');
    differenceEl.textContent = money(difference);
    differenceEl.classList.toggle('text-danger', difference !== 0);
}

(async () => {
    const tableEl = document.getElementById('ledgerJournalBookTable');
    if (!tableEl || !window.jQuery?.fn?.DataTable) return;

    const table = await createDataTable({
        tableSelector: '#ledgerJournalBookTable', api: API.list, serverSide: true,
        columns, orderableColumnKeys: columns.filter((column)=>column.orderable!==false).map((column)=>column.data),
        ajaxData(request) { return request; },
        tableSettings: {enabled:true,pageKey:PAGE_KEY,tableKey:TABLE_KEY,storageKey:STORAGE_KEY,userSettingPageKey:PAGE_KEY,metaDomain:'ledger-journal-book',description:'분개장 조회 Projection 테이블 설정',tableLabel:'분개장 목록',title:'분개장 목록 테이블 설정'},
        dataSrc(json) { return Array.isArray(json?.data) ? json.data : []; },
        defaultOrder: [{key:'voucher_date',dir:'asc'}], pageLength: 100,
        selectable: false, showCopyButton: false, deleteButton: false,
    });

    SearchForm({
        table, apiList: API.list, tableId: 'ledgerJournalBook', defaultSearchField: 'voucher_no',
        dateOptions: [{value:'voucher_date',label:'전표일자'}],
        searchFields: [
            {value:'voucher_no',label:'전표번호'},{value:'voucher_status',label:'전표상태'},
            {value:'account_code',label:'계정코드'},{value:'account_name',label:'계정과목'},
            {value:'line_summary',label:'적요'},
        ], excludeFields: ['id','voucher_id','account_id'],
    });

    table.on('xhr.dt', (_event, _settings, json) => updateSummary(json?.summary || {}));
    table.on('draw.dt', () => {
        document.getElementById('ledgerJournalBookCount').textContent = `총 ${table.page.info()?.recordsDisplay ?? 0}건`;
    });
    window.jQuery(tableEl).on('dblclick', 'tbody tr', function () {
        const row = table.row(this).data();
        if (row?.voucher_id) window.location.href = `/ledger/vouchers/input?voucher_id=${encodeURIComponent(row.voucher_id)}`;
    });
    bindTableHighlight('#ledgerJournalBookTable', table);
    refreshDataTableLayout(table, {delays:[0]});
})();
