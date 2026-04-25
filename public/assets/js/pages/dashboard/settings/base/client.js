// 경로: PROJECT_ROOT . '/assets/js/pages/dashboard/settings/base/client.js'
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { checkBusinessStatus }from '/public/assets/js/common/biz_api.js';
import { formatBizNumber, formatCorpNumber, formatMobile, formatPhone, onlyNumber } from '/public/assets/js/common/format.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';
window.AdminPicker = AdminPicker;

(() => {
    'use strict';
    console.log('[base-client.js] loaded');
    /* =========================
    API / 상수
    ========================= */
    const API = {
        LIST: "/api/settings/base-info/client/list",
        DETAIL: "/api/settings/base-info/client/detail",

        SAVE: "/api/settings/base-info/client/save",
        DELETE: "/api/settings/base-info/client/delete",

        TRASH: "/api/settings/base-info/client/trash",
        RESTORE: "/api/settings/base-info/client/restore",
        RESTORE_BULK: "/api/settings/base-info/client/restore-bulk",
        RESTORE_ALL: "/api/settings/base-info/client/restore-all",

        PURGE: "/api/settings/base-info/client/purge",
        PURGE_BULK: "/api/settings/base-info/client/purge-bulk",
        PURGE_ALL: "/api/settings/base-info/client/purge-all",

        REORDER: "/api/settings/base-info/client/reorder",

        EXCEL_UPLOAD: "/api/settings/base-info/client/excel-upload",
        EXCEL_DOWNLOAD: "/api/settings/base-info/client/download",
        EXCEL_TEMPLATE: "/api/settings/base-info/client/template",

        SEARCH_PICKER: "/api/settings/base-info/client/search-picker"
    };

    // fetch(API.DETAIL + '?sort_no=' + sort_no);
    // fetch(API.TRASH);
    // fetch(API.RESTORE);
    // fetch(API.PURGE);

    const CLIENT_COLUMN_MAP = {
        sort_no: { label: "순번", visible: true },
        client_name: { label: "거래처명", visible: true },
        company_name: { label: "상호", visible: true },
        registration_date: { label: "등록일자", visible: true },
        business_number: { label: "사업자등록번호", visible: true },
        rrn: { label: "법인/주민등록번호", visible: false },
        business_type: { label: "업태", visible: false },
        business_category: { label: "업종", visible: false },
        business_status: { label: "사업자상태", visible: true },
        business_certificate: { label: "사업자등록증", visible: false },
        address: { label: "주소", visible: false },
        address_detail: { label: "상세주소", visible: false },
        phone: { label: "전화번호", visible: true },
        fax: { label: "팩스", visible: false },
        email: { label: "이메일", visible: true },
        ceo_name: { label: "대표자", visible: true },
        ceo_phone: { label: "대표자전화", visible: false },
        manager_name: { label: "담당자", visible: false },
        manager_phone: { label: "담당자전화", visible: false },
        bank_name: { label: "은행명", visible: false },
        account_number: { label: "계좌번호", visible: false },
        account_holder: { label: "예금주", visible: false },
        bank_file: { label: "통장사본", visible: false },
        trade_category: { label: "거래구분", visible: false },
        item_category: { label: "취급품목", visible: false },
        client_category: { label: "거래처분류", visible: false },
        client_type: { label: "거래유형", visible: false },
        tax_type: { label: "과세구분", visible: false },
        payment_term: { label: "결제조건", visible: false },
        client_grade: { label: "거래처등급", visible: false },
        homepage: { label: "홈페이지", visible: false },
        note: { label: "비고", visible: true },
        memo: { label: "메모", visible: false },
        is_active: { label: "사용여부", visible: false },
        created_at: { label: "생성일시", visible: false },
        created_by_name: { label: "생성자", visible: false },
        updated_at: { label: "수정일시", visible: false },
        updated_by_name: { label: "수정자", visible: false },
        deleted_at: { label: "삭제일시", visible: false },
        deleted_by_name: { label: "삭제자", visible: false }
    };

    CLIENT_COLUMN_MAP.rrn_image = { label: "등록증이미지", visible: false };
    CLIENT_COLUMN_MAP.is_active = { label: "상태", visible: true };
    CLIENT_COLUMN_MAP.created_by_name = { label: "생성자", visible: false };
    CLIENT_COLUMN_MAP.updated_by_name = { label: "수정자", visible: false };
    CLIENT_COLUMN_MAP.deleted_by_name = { label: "삭제자", visible: false };

    const CLIENT_COLUMN_WIDTHS = {
        __reorder: '40px',
        sort_no: '80px',
        client_name: '200px',
        company_name: '160px',
        registration_date: '120px',
        business_number: '140px',
        rrn: '150px',
        rrn_image: '120px',
        business_type: '120px',
        business_category: '120px',
        business_status: '100px',
        business_certificate: '120px',
        address: '240px',
        address_detail: '220px',
        phone: '140px',
        fax: '140px',
        email: '200px',
        ceo_name: '120px',
        ceo_phone: '140px',
        manager_name: '120px',
        manager_phone: '140px',
        bank_name: '120px',
        account_number: '160px',
        account_holder: '120px',
        bank_file: '120px',
        trade_category: '120px',
        item_category: '140px',
        client_category: '140px',
        client_type: '120px',
        tax_type: '120px',
        payment_term: '140px',
        client_grade: '120px',
        homepage: '200px',
        note: '220px',
        memo: '220px',
        is_active: '90px',
        created_at: '160px',
        created_by_name: '120px',
        updated_at: '160px',
        updated_by_name: '120px',
        deleted_at: '160px',
        deleted_by_name: '120px'
    };

    const DATE_OPTIONS = [
        { value: 'registration_date', label: '등록일자' },
        { value: 'updated_at', label: '수정일자' }
    ];

    let clientTable = null;
    let clientModal = null;
    let excelModal = null;
    let todayPicker = null;
    let rrnVisible = false;
    let globalBound = false;


/* ============================================================
   DOM READY
   - 페이지 로딩 완료 후 초기화를 시작한다.
============================================================ */
document.addEventListener('DOMContentLoaded', async () => {
    if (!window.jQuery) {
        console.error('jQuery not loaded');
        return;
    }
    const $ = window.jQuery;
    // 전체 페이지 초기화 진입
    initClientPage($);
});


/* ============================================================
   PAGE INIT
   - 실행 순서가 중요하다.
   - UI, 데이터, 이벤트 순서로 구성한다.
============================================================ */
function initClientPage($){
    /* --------------------------------------------------------
       1. UI 기본 구성 (DOM + 컴포넌트 준비)
       - 모달 / 날짜 / 업로드 UI를 먼저 준비한다.
    -------------------------------------------------------- */
    initModal();              // Bootstrap Modal 초기화
    initAdminDatePicker();    // 날짜 선택기
    initBizCertUpload();      // 사업자등록증 업로드 UI
    initRrnUpload();          // 신분증 업로드 UI
    initBankFileUpload();     // 통장사본 업로드 UI
    initExcelDataset();       // 엑셀 파일 업로드
    /* --------------------------------------------------------
       2. 공통 데이터 영역
       - DataTable 생성 후 이후 기능들이 여기에 의존한다.
    -------------------------------------------------------- */
    initDataTable($);         // clientTable 생성
    /* --------------------------------------------------------
       3. 외부 기능 연결
       - 외부 서비스와 주소 검색 등을 연결한다.
    -------------------------------------------------------- */
    initExternal();
    /* --------------------------------------------------------
       4. 테이블 기능 바인딩 (DataTable 의존)
    -------------------------------------------------------- */
    bindRowReorder(clientTable, { api: API.REORDER });  // 행 드래그 정렬
    bindTableEvents($);                        // 클릭, 선택 이벤트
    /* --------------------------------------------------------
       5. 모달 및 입력 관련 이벤트
    -------------------------------------------------------- */
    bindModalEvents($);            // 신규/수정 모달 이벤트
    bindBizStatusButton();         // 사업자 상태 조회 버튼
    bindAdminDateInputs();         // 날짜 input 연동
    bindRrnInputEvents($);         // RRN 마스킹 원본 관리
    /* --------------------------------------------------------
       6. 레이아웃 제어
       - 검색폼과 테이블 영역 동기화
    -------------------------------------------------------- */
    /* --------------------------------------------------------
       7. 일반 UI 이벤트
    -------------------------------------------------------- */
    bindUIEvents();                // 버튼, 토글 등 일반 UI
    /* --------------------------------------------------------
       8. 부가 기능
    -------------------------------------------------------- */
    bindExcelEvents();             // 엑셀 업로드/다운로드
    bindTrashEvents();             // 휴지통 기능
    /* --------------------------------------------------------
       9. 전역 이벤트
    -------------------------------------------------------- */
    bindGlobalEvents();            // 전역 클릭/입력 이벤트
}


    // 엑셀 모달에 API 경로를 주입한다.
    function initExcelDataset() {

        const excelForm = document.getElementById('clientExcelForm');

        if (!excelForm) return;

        excelForm.dataset.templateUrl = API.EXCEL_TEMPLATE;
        excelForm.dataset.downloadUrl = API.EXCEL_DOWNLOAD;
        excelForm.dataset.uploadUrl   = API.EXCEL_UPLOAD;

    }


    function initModal(){
        const modalEl = document.getElementById('clientModal');
        clientModal = new bootstrap.Modal(modalEl, {
            focus:false
        });
        excelModal = new bootstrap.Modal(
            document.getElementById('clientExcelModal')   // 엑셀 모달
        );
        modalEl.addEventListener('hidden.bs.modal', () => {
            const help = document.getElementById('bizCertHelp');
            if(help) help.style.display = 'block';
            const form = document.getElementById('client-edit-form');
            form.reset();
            /* 통장사본 초기화 */
            const bankText = document.getElementById('bankCopyText');
            if(bankText){
                bankText.innerHTML = `
                    여기에 파일을 끌어놓거나 클릭하여 업로드
                    <br>
                    (PDF, JPG, PNG)
                `;
            }
            const drop = document.getElementById('bankCopyUpload');
            if(drop){
                drop.dataset.original = "0";
            }
            const delBank = document.getElementById('delete_bank_file');
            if(delBank){
                delBank.value = '0';
            }
            /* 사업자등록증 파일 input 초기화 */
            const fileInput = document.getElementById('modal_business_certificate');
            if(fileInput){
                fileInput.value = '';
            }
            /* 드롭존 텍스트 초기화 */
            const text = document.getElementById('dropZoneTextBiz');
            if(text){
                text.innerHTML =
                    "여기에 파일을 끌어놓거나 클릭하여 선택하세요<br>(PDF, JPG, PNG)";
            }
            /* 아이콘 초기화 */
            const icon = document.getElementById('certStatusIcon');
            if(icon){
                icon.innerHTML = '';
            }
            /* 신분증 초기화 */
            const rrnInput = document.getElementById('modal_rrn_image');
            const rrnDelete = document.getElementById('delete_rrn_image');
            const rrnList = document.getElementById('rrnImageList');
            const rrnText = document.getElementById('dropZoneTextRrn');

            if (rrnInput) rrnInput.value = '';
            if (rrnDelete) rrnDelete.value = '0';
            if (rrnList) rrnList.innerHTML = '';
            if (rrnText) {
                rrnText.innerHTML =
                    "파일 선택 또는 클릭<br>(JPG, PNG)";
            }

            const rrnField = document.getElementById('modal_rrn');
            if (rrnField) {
                rrnField.value = '';
                rrnField.dataset.real = '';
            }

            rrnVisible = false;

            const toggleBtn = document.querySelector('.toggle-rrn');
            if (toggleBtn) {
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }


        });

        bindDateIconPicker();
        // 모달 안에서 picker 위치 문제 방지
        modalEl.addEventListener('shown.bs.modal', () => {
            bindAdminDateInputs();
        });
    }



    function bindUIEvents(){
        /* ==============================
        사업자등록증 교체
        ============================== */
        const btnReplaceCert = document.getElementById('btnReplaceCert');
        if(btnReplaceCert){
            btnReplaceCert.addEventListener('click', function(){
                document
                    .getElementById('modal_business_certificate')
                    .click();
            });
        }
        /* ==============================
        사업자등록증 삭제
        ============================== */
        const btnRemoveCert = document.getElementById('btnRemoveCert');
        if(btnRemoveCert){
            btnRemoveCert.addEventListener('click', function(){
                if(!confirm('사업자등록증 파일을 삭제하시겠습니까?')){
                    return;
                }
                document.getElementById('modal_business_certificate').value = '';

                const bizList = document.getElementById('bizCertList');
                if (bizList) {
                    bizList.dataset.original = "0";
                    bizList.innerHTML = '';
                }

                const preview = document.getElementById('bizCertPreview');
                if (preview) preview.innerHTML = '';

                const actions = document.getElementById('bizCertActions');
                if (actions) actions.style.display = 'none';

                const icon = document.getElementById('certStatusIcon');
                if (icon) icon.innerHTML = '';

                document.getElementById('delete_business_certificate').value = '1';

                const dropZoneTextBiz = document.getElementById('dropZoneTextBiz');
                if (dropZoneTextBiz) {
                    dropZoneTextBiz.innerHTML =
                        "여기에 파일을 끌어놓거나 클릭하여 선택하세요<br>(PDF, JPG, PNG)";
                }
            });
        }
    }





    function initExternal(){
        /* =====================================
        카카오 주소 검색기 호출
        ===================================== */
        if(window.KakaoAddress){
            window.KakaoAddress.bind();
        }
    }



    function bindExcelEvents(){
        document.addEventListener("excel:uploaded", () => {
            if (clientTable) {
                clientTable.ajax.reload(null, false);
            }
        });
    }

    function bindGlobalEvents(){

        if(globalBound) return;
        globalBound = true;

        document.addEventListener('click', onGlobalClick);
        document.addEventListener('input', onGlobalInput);
    }



    /* =========================
    사업자 상태 조회
    ========================= */
    function onGlobalClick(e){

        // 사업자 상태 조회 버튼만 처리
        const btn = e.target.closest('.btn-biz-status');
        if(!btn) return;

        const input = document.getElementById('modal_business_number');
        if(!input) return;

        const bizNo = input.value?.replace(/\D/g,'');
        if(!bizNo){
            AppCore.notify('warning','사업자번호를 입력하세요.');
            return;
        }

        fetch(API.BIZ_STATUS,{
            method:'POST',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify({ business_number: bizNo })
        })
        .then(res => {
            if(!res.ok) throw new Error('HTTP 오류');
            return res.json();
        })
        .then(json => {

            if(!json.success){
                AppCore.notify('error','사업자 조회 실패');
                return;
            }

            // 상태 표시
            const statusEl = document.getElementById('modal_business_status');
            if(statusEl){
                statusEl.value = json.data?.status || '';
            }

            AppCore.notify('success','조회 완료');
        })
        .catch(err => {
            console.error(err);
            AppCore.notify('error','사업자 조회 오류');
        });
    }


    /* =========================
    ?먮룞?섏씠?덊뵂
    ========================= */
    function onGlobalInput(e){

        const type = e.target.dataset.format;
        if(!type) return;

        if(type === 'biz'){
            e.target.value = formatBizNumber(e.target.value);
        }

        if(type === 'corp'){
            e.target.value = formatCorpNumber(e.target.value);
        }

        if(type === 'mobile'){
            e.target.value = formatMobile(e.target.value);
        }

        if(type === 'phone' || type === 'fax'){
            e.target.value = formatPhone(e.target.value);
        }
    }


    function bindTrashEvents(){

        document.addEventListener('trash:detail-render', function(e){

            const { data, modal } = e.detail;

            // 상세 영역이 없으면 종료
            if(modal.dataset.type !== 'client') return;

            console.log("detail data:", data);

            const detailBox = modal.querySelector('.trash-detail');
            if(!detailBox) return;

            let html = `
                <div class="p-3">
                    <h6 class="mb-3">거래처 상세</h6>
            `;

            Object.entries(CLIENT_COLUMN_MAP).forEach(([key, config]) => {

                const value = data[key];

            // 값이 없으면 숨김
                if(value === null || value === undefined || value === '') return;

                html += `
                    <div><b>${config.label}:</b> ${value}</div>
                `;
            });

            html += `</div>`;

            detailBox.innerHTML = html;
        });

        // 휴지통 모달에 컬럼 렌더러 전달
        window.TrashColumns = window.TrashColumns || {};

        window.TrashColumns.client = function(row) {
            return `
                <td>${row.sort_no ?? ''}</td>
                <td>${row.client_name ?? ''}</td>
                <td>${row.deleted_at ?? ''}</td>
                <td>${row.deleted_by_name ?? ''}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">영구삭제</button>
                </td>
            `;
        };

        // 테이블 갱신
        document.addEventListener('trash:changed', (e) => {

            const { type } = e.detail || {};

            if (type === 'client') {
                if (clientTable){
                    clientTable.ajax.reload(null, false);
                }
            }

        });
    }


    function initAdminDatePicker(){
        if (todayPicker) return todayPicker;
        const container = document.getElementById('today-picker');
        if (!container) return null;
        todayPicker = AdminPicker.create({
            type:'today',
            container
        });
        todayPicker.subscribe((_, date) => {
            const input = todayPicker.__target;
            if (!input || !date) return;
            input.value = formatDate(date);
            normalizeStartEnd(
            input.name === 'dateStart' ? 'start' : 'end'
            );
            todayPicker.close();
        });
        return todayPicker;
    }

    function bindAdminDateInputs(){
        document.querySelectorAll('.admin-date').forEach(input => {
            if (input.dataset.dateInputBound === '1') return;
            input.dataset.dateInputBound = '1';

            input.addEventListener('input', () => {
                input.value = formatDateInputValue(input.value);
            });

            input.addEventListener('blur', () => {
                input.value = normalizeDateInputValue(input.value);
            });

            input.addEventListener('click', e => {
            return;
            e.preventDefault();
            e.stopPropagation();
            const picker = initAdminDatePicker();
            if(!picker) return;
            picker.__target = input;
            /* 기존 상태 초기화 */
            if (typeof picker.clearDate === 'function') {
                picker.clearDate();
            }
            const v = input.value;
            /* 값이 있으면 다시 세팅 */
            if(v){
                const d = new Date(v);
                if(!isNaN(d)){
                picker.setDate(d);
                }
            }
            picker.open({
                anchor: input
            });
            });
        });
    }

    function formatDateInputValue(value) {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 8);

        if (digits.length <= 4) return digits;
        if (digits.length <= 6) return `${digits.slice(0, 4)}-${digits.slice(4)}`;

        return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
    }

    function bindDateIconPicker() {
        if (document.__clientDateIconPickerBound) return;
        document.__clientDateIconPickerBound = true;

        document.addEventListener('click', function (e) {
            const icon = e.target.closest('.date-icon');
            if (!icon) return;

            const wrap = icon.closest('.date-input, .date-input-wrap');
            const input = wrap ? wrap.querySelector('input.admin-date, input[name="dateStart"], input[name="dateEnd"]') : null;
            if (!input) return;

            e.preventDefault();
            e.stopPropagation();
            openDatePickerForInput(input);
        }, true);
    }

    function openDatePickerForInput(input) {
        const picker = initAdminDatePicker();
        if (!picker) return;

        picker.__target = input;

        if (typeof picker.clearDate === 'function') {
            picker.clearDate();
        }

        input.value = normalizeDateInputValue(input.value);

        if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            const date = new Date(input.value);
            if (!Number.isNaN(date.getTime())) {
                picker.setDate(date);
            }
        }

        picker.open({
            anchor: input
        });
    }

    function normalizeDateInputValue(value) {
        const formatted = formatDateInputValue(value);
        const match = formatted.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (!match) return formatted;

        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const date = new Date(year, month - 1, day);

        if (
            date.getFullYear() !== year ||
            date.getMonth() !== month - 1 ||
            date.getDate() !== day
        ) {
            AppCore.notify('warning', '올바른 날짜를 입력하세요.');
            return '';
        }

        return formatted;
    }

    function formatDate(date){
        if(!date) return '';
        const y = date.getFullYear();
        const m = String(date.getMonth()+1).padStart(2,'0');
        const d = String(date.getDate()).padStart(2,'0');
        return `${y}-${m}-${d}`;
    }

    function normalizeStartEnd(type){
        const start = document.querySelector('input[name="dateStart"]');
        const end   = document.querySelector('input[name="dateEnd"]');
        if(!start || !end) return;
        if(!start.value || !end.value) return;
        if(type === 'start'){
            if(start.value > end.value){
            end.value = start.value;
            }
        }
        if(type === 'end'){
            if(end.value < start.value){
            start.value = end.value;
            }
        }
    }


    function initDataTable($) {
        const columns = buildClientColumns();
        clientTable = createDataTable({
            tableSelector: '#client-table',
            api: API.LIST,
            columns: columns,
            defaultOrder: [[1, "asc"]],
            pageLength: 10,
            autoWidth: false,
            buttons: [
                {
                    text: "엑셀관리",
                    className: "btn btn-success btn-sm",
                    action: function () {
                        if (excelModal) {
                            excelModal.show();
                        }
                    }
                },
                {
                    text: "휴지통",
                    className: "btn btn-danger btn-sm",
                    action: function () {
                        const trashModalEl = document.getElementById('clientTrashModal');
                        if (!trashModalEl) return;

                        /* 공통 휴지통 컴포넌트에 거래처 API를 전달한다. */
                        trashModalEl.dataset.listUrl      = API.TRASH;
                        trashModalEl.dataset.restoreUrl   = API.RESTORE;
                        trashModalEl.dataset.restoreBulkUrl = API.RESTORE_BULK;
                        trashModalEl.dataset.restoreAllUrl  = API.RESTORE_ALL;

                        trashModalEl.dataset.deleteUrl    = API.PURGE;
                        trashModalEl.dataset.deleteBulkUrl = API.PURGE_BULK;
                        trashModalEl.dataset.deleteAllUrl = API.PURGE_ALL;

                        const modal = new bootstrap.Modal(trashModalEl);
                        modal.show();
                    }
                },
                {
                    text: "새 거래처",
                    className: "btn btn-warning btn-sm",
                    action: function () {

                        const form = document.getElementById('client-edit-form');
                        if (form) form.reset();

                        $('#modal_client_id').val('');
                        $('#btnDeleteClient').hide();

                        window.isNewClient = true;

                        const titleEl = document.getElementById('clientModalLabel');
                        if (titleEl) {
                            titleEl.textContent = '거래처 신규 등록';
                        }

                        const bizList = document.getElementById('bizCertList');
                        const bizHelp = document.getElementById('bizCertHelp');
                        const bizInput = document.getElementById('modal_business_certificate');
                        const bizDelete = document.getElementById('delete_business_certificate');
                        const dropText = document.getElementById('dropZoneTextBiz');
                        const certIcon = document.getElementById('certStatusIcon');

                        if (bizList) {
                            bizList.innerHTML = '';
                            bizList.dataset.original = '0';
                        }

                        if (bizHelp) bizHelp.style.display = 'block';
                        if (bizInput) bizInput.value = '';
                        if (bizDelete) bizDelete.value = '0';

                        if (dropText) {
                            dropText.innerHTML =
                                '여기에 파일을 끌어놓거나 클릭하여 선택하세요<br>(PDF, JPG, PNG)';
                        }

                        if (certIcon) certIcon.innerHTML = '';

                        const bankText = document.getElementById('bankCopyText');
                        const bankDrop = document.getElementById('bankCopyUpload');
                        const bankDelete = document.getElementById('delete_bank_file');
                        const bankInput = document.getElementById('modal_bank_file');

                        if (bankText) {
                            bankText.innerHTML =
                                '여기에 파일을 끌어놓거나 클릭하여 업로드<br>(PDF, JPG, PNG)';
                        }

                        if (bankDrop) bankDrop.dataset.original = '0';
                        if (bankDelete) bankDelete.value = '0';
                        if (bankInput) bankInput.value = '';

                        const rrnInput = document.getElementById('modal_rrn_image');
                        const rrnDelete = document.getElementById('delete_rrn_image');
                        const rrnList = document.getElementById('rrnImageList');
                        const rrnText = document.getElementById('dropZoneTextRrn');

                        if (rrnInput) rrnInput.value = '';
                        if (rrnDelete) rrnDelete.value = '0';
                        if (rrnList) rrnList.innerHTML = '';
                        if (rrnText) {
                            rrnText.innerHTML = '파일 선택 또는 클릭<br>(JPG, PNG)';
                        }
                        // 신규 거래처 모달을 열 때 등록일자를 오늘 날짜로 자동 세팅
                        const dateEl = document.getElementById('modal_registration_date');
                        if(dateEl){
                            const d = new Date();
                            dateEl.value = d.toISOString().slice(0,10);
                        }

                        if (clientModal) {
                            clientModal.show();
                        }
                    }
                }
            ]
        });

        window.clientTable = clientTable;

        if (clientTable) {
            console.log('DataTable 생성 완료');

            SearchForm({
                table: clientTable,
                apiList: API.LIST,
                tableId: 'client',
                defaultSearchField: 'client_name',
                dateOptions: DATE_OPTIONS,
                normalizeFilters: normalizeClientFilters
            });
            bindTableHighlight('#client-table', clientTable);

            clientTable.on('init.dt', function () {
                updateClientCount(clientTable.page.info()?.recordsDisplay ?? 0);
            });

            clientTable.on('draw.dt', function () {
                updateClientCount(clientTable.page.info()?.recordsDisplay ?? 0);
            });
        }
    }

    function updateClientCount(count) {
        const el = document.getElementById('clientCount');
        if (!el) return;
        el.textContent = `총 ${count ?? 0}건`;
    }



    function normalizeClientFilters(filters) {
        return (filters || []).map(filter => {
            if (filter?.field !== 'is_active') return filter;

            const value = normalizeActiveValue(filter.value);
            return value === '' ? null : { field: 'is_active', value };
        }).filter(Boolean);
    }

    function normalizeActiveValue(value) {
        const raw = String(value ?? '').trim().toLowerCase();
        if (['1', '사용', '사용중', '활성', 'active', 'y', 'yes', 'true'].includes(raw)) return '1';
        if (['0', '미사용', '비활성', 'inactive', 'n', 'no', 'false'].includes(raw)) return '0';
        return '';
    }

    function bindTableEvents($) {
        /* ================================
        더블클릭 시 수정 모달
        ================================ */
        $('#client-table tbody').on('dblclick', 'tr', async function () {

            const row = clientTable.row(this).data();
            if (!row) return;

            try {
                const res = await fetch(API.DETAIL + '?id=' + row.id);
                const json = await res.json();

                if (!json.success) {
                    AppCore.notify('error', '상세조회 실패');
                    return;
                }

                const data = json.data;

                window.isNewClient = false;
                $('#btnDeleteClient').show();
                $('#modal_client_id').val(data.id);

                /* 삭제 플래그와 파일 input 먼저 초기화 */
                const delBiz = document.getElementById('delete_business_certificate');
                const delRrn = document.getElementById('delete_rrn_image');
                const delBank = document.getElementById('delete_bank_file');

                const bizInput = document.getElementById('modal_business_certificate');
                const rrnInput = document.getElementById('modal_rrn_image');
                const bankInput = document.getElementById('modal_bank_file');

                if (delBiz) delBiz.value = '0';
                if (delRrn) delRrn.value = '0';
                if (delBank) delBank.value = '0';

                if (bizInput) bizInput.value = '';
                if (rrnInput) rrnInput.value = '';
                if (bankInput) bankInput.value = '';

                fillModal(data);
                clientModal.show();

            } catch (e) {
                console.error(e);
                AppCore.notify('error', '서버 오류');
            }
        });

        /* ================================
        셀 클릭 시 검색조건 입력
        ================================ */
        $('#client-table tbody').on('click', 'td', function () {
            const cell = clientTable.cell(this);
            const value = cell.data();
            const colIndex = cell.index().column;
            const field = clientTable.column(colIndex).dataSrc();
            if(!field) return;
            const $first = $('.search-condition').first();
            $first.find('select').val(field);
            $first.find('input').val(value);
        });
    }









    /* ============================================================
       모달 저장 / 삭제
    ============================================================ */
    function bindModalEvents($) {
        // 기존 submit 바인딩 제거 후 다시 바인딩 (중복 방지)
        $(document).off('submit', '#client-edit-form');

        $(document).on('submit', '#client-edit-form', function (e) {
            e.preventDefault();

            const form = this;
            form.querySelectorAll('.admin-date').forEach(input => {
                input.value = normalizeDateInputValue(input.value);
            });
            const formData = new FormData(form);

            const rrnInput = document.getElementById('modal_rrn');
            if (rrnInput) {
                const realVal = onlyNumber(rrnInput.dataset.real || '');
                formData.set('rrn', realVal !== '' ? realVal : '');
            }

            const btn = form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;   // 중복 클릭 방지

            $.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(res => {

                if (!res.success) {
                    AppCore.notify('error', res.message || '저장 실패');
                    return; // 반드시 빠져나감 (연속 저장 방지)
                }

                clientModal.hide();
                clientTable.ajax.reload(null, false);

                AppCore.notify('success', '저장 완료');
            })
            .fail(() => {
                AppCore.notify('error', '서버 오류');
            })
            .always(() => {
                if (btn) btn.disabled = false; // 다시 활성화
            });
        });


        $('#btnDeleteClient').on('click', function () {
            const id = $('#modal_client_id').val();
            if (!id || !confirm('삭제하시겠습니까?')) return;
            $.post(API.DELETE, { id })
                .done(res => {
                    if (res.success) {
                        AppCore.notify(
                            'success',
                            '삭제 완료'
                        );
                        clientTable.ajax.reload(null, false);
                        clientModal.hide();
                    } else {
                        alert(res.message || '삭제 실패');
                    }
                });
        });
    }





    /* ============================================================
       UTIL
    ============================================================ */

    function fillModal(data){
        Object.keys(data).forEach(key => {

            if(key === 'id') return;
            if(key === 'business_certificate') return;
            if (key === 'rrn_image') return;
            if(key === 'bank_file') return;

            const el = document.getElementById('modal_' + key);

            if(!el) return;

            let value = data[key] ?? '';

            if (key === 'rrn') {
                const raw = onlyNumber(value);
                el.dataset.real = raw;
                rrnVisible = false;

                // 마스킹 적용 타이밍 보정
                setTimeout(() => {
                    el.value = maskRrn(raw);
                }, 0);

                const toggleBtn = document.querySelector('.toggle-rrn');
                if (toggleBtn) {
                    const icon = toggleBtn.querySelector('i');
                    if (icon) {
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                }
                return;
            }

            const formatType = el.dataset.format;

            if(formatType === 'biz'){
                value = formatBizNumber(value);
            }
            else if(formatType === 'corp'){
                value = formatCorpNumber(value);
            }
            else if(formatType === 'mobile'){
                value = formatMobile(value);
            }
            else if(formatType === 'phone' || formatType === 'fax'){
                value = formatPhone(value);
            }

            el.value = value;

        });
        const list = document.getElementById('bizCertList');
        const help = document.getElementById('bizCertHelp');
        if(!list) return;
        list.innerHTML = '';
        if(data.business_certificate){
                if(help) help.style.display = 'none';   // 기존 파일이 있으면 안내 숨김
            const fileName = data.business_certificate.split('/').pop();
            const path = encodeURIComponent(data.business_certificate);
            list.dataset.original = "1";
            list.innerHTML = `
            <div class="file-item">
                <span>
                    파일 <strong>사업자등록증</strong> (${fileName})
                </span>
                <div class="file-actions">
                    <a href="/api/file/preview?path=${path}"
                    target="_blank"
                    class="file-preview">
                    미리보기
                    </a>
                    <span class="file-divider">|</span>
                    <a href="javascript:void(0)"
                    id="btnDeleteCert"
                    class="file-delete">
                    삭제
                    </a>
                </div>
            </div>
            `;

            document.getElementById('btnDeleteCert').onclick = function(){
                if(!confirm('사업자등록증을 삭제하시겠습니까?')) return;

                const del = document.getElementById('delete_business_certificate');
                const input = document.getElementById('modal_business_certificate');

                if (del) del.value = '1';
                if (input) input.value = '';
                list.dataset.original = "0";

                list.innerHTML = `
                <div class="file-item">
                    <span>
                         파일 <strong>사업자등록증</strong> (${fileName})
                    </span>
                    <div class="file-status text-danger">
                        사업자등록증이 삭제됩니다. 저장 시 반영됩니다.
                    </div>
                </div>
                `;
            };
        }else{
            list.dataset.original = "0";
        }
        const rrnList = document.getElementById('rrnImageList');

        if (!rrnList) return;

        rrnList.innerHTML = '';

        if (data.rrn_image) {

            const fileName = data.rrn_image.split('/').pop();
            const path = encodeURIComponent(data.rrn_image);

            rrnList.innerHTML = `
                <div class="file-item">
                    <span>
                         파일 <strong>신분증</strong> (${fileName})
                    </span>

                    <div class="file-actions">
                        <a href="/api/file/preview?path=${path}" target="_blank">
                            미리보기
                        </a>
                        <span class="file-divider">|</span>
                        <a href="javascript:void(0)" id="btnDeleteRrn">
                            삭제
                        </a>
                    </div>
                </div>
            `;

            document.getElementById('btnDeleteRrn').onclick = function(){

                if(!confirm('신분증을 삭제하시겠습니까?')) return;

                const input = document.getElementById('modal_rrn_image');
                const del = document.getElementById('delete_rrn_image');

                if (input) input.value = '';
                if (del) del.value = '1';

                rrnList.innerHTML = `
                    <div class="file-item">
                        <span>
                             파일 <strong>신분증</strong> (${fileName})
                        </span>
                        <div class="file-status text-danger">
                            신분증이 삭제됩니다. 저장 시 반영됩니다.
                        </div>
                    </div>
                `;
            };

        }

        /* =========================
        통장사본 표시
        ========================= */
        const bankText = document.getElementById('bankCopyText');
        const drop = document.getElementById('bankCopyUpload');
        if(bankText){
            if(data.bank_file){
                if(drop){
                    drop.dataset.original = "1";
                }
                const path = encodeURIComponent(data.bank_file);
                bankText.innerHTML = `
                <div class="file-status">
                    <div class="upload-guide">
                        여기에 파일을 끌어놓거나 클릭하여 업로드
                        <br>
                        (PDF, JPG, PNG)
                    </div>
                    <div class="file-line">
                         파일 <strong>통장사본 등록됨</strong>
                    </div>
                    <div class="file-links">
                        <a href="javascript:void(0)"
                           id="btnOpenBankCopy"
                           class="file-link-open disabled">
                           미리보기
                        </a>
                        <span class="file-divider">|</span>
                        <a href="javascript:void(0)"
                           id="btnDeleteBankCopy"
                           class="file-link-delete disabled">
                           삭제
                        </a>
                    </div>
                </div>
                `;
                const btnOpen = document.getElementById("btnOpenBankCopy");
                const btnDelete = document.getElementById("btnDeleteBankCopy");
                if(btnOpen){
                    btnOpen.classList.remove("disabled");
                    btnOpen.href = "/api/file/preview?path=" + path;
                    btnOpen.target = "_blank";
                    btnOpen.addEventListener("click", function(e){
                        e.stopPropagation();   // 업로드 이벤트 차단
                    });
                }

                if(btnDelete){
                    btnDelete.classList.remove("disabled");
                    btnDelete.onclick = function(e){
                        e.stopPropagation();
                        if(!confirm('통장사본을 삭제하시겠습니까?')) return;
                        const del = document.getElementById('delete_bank_file');
                        const drop = document.getElementById('bankCopyUpload');
                        const input = document.getElementById('modal_bank_file');
                        if(del) del.value = '1';
                        if(drop) drop.dataset.original = "0";
                        if(input) input.value = '';
                        const bankfileName = data.bank_file.split('/').pop();
                        const shortName = shortenFileName(bankfileName, 20);
                        bankText.innerHTML = `
                        파일 <strong>통장사본</strong> (${shortName})
                        <br>
                        <div class="file-status text-danger">
                        통장사본이 삭제됩니다.<br>
                        저장 시 반영됩니다.
                        </div>
                        `;
                    };
                }
            }else{
                if(drop){
                    drop.dataset.original = "0";
                }
                bankText.innerHTML = `
                    여기에 파일을 끌어놓거나 클릭하여 업로드
                    <br>
                    (PDF, JPG, PNG)
                `;
            }
        }
    }


    function buildClientColumns(){
        const columns = [];

        /* 드래그 컬럼 */
        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            width: CLIENT_COLUMN_WIDTHS.__reorder,
            className:"reorder-handle no-sort no-colvis text-center",
            orderable:false,
            searchable:false,
            render: () => '<i class="bi bi-list"></i>'
        });

        Object.entries(CLIENT_COLUMN_MAP).forEach(([field,config]) => {

            columns.push({
                data: field,
                title: config.label,
                width: CLIENT_COLUMN_WIDTHS[field] || '120px',
                visible: config.visible ?? true,
                defaultContent: "",
                render: function(data,type,row){

                    if(data == null) return "";

                    // display 렌더링일 때만 포맷 적용
                    if(type !== 'display') return data;

                    if(field === "bank_file")
                        return data ? "등록" : "";

                    if(field === "business_certificate" || field === "rrn_image")
                        return data ? "등록" : "";

                    if(field === "is_active") {
                        const active = Number(data) === 1;
                        return active
                            ? '<span class="badge bg-success">사용</span>'
                            : '<span class="badge bg-secondary">미사용</span>';
                    }

                    if(field === "business_number")
                        return formatBizNumber(data);

                    if(field === "rrn")
                        return formatCorpNumber(data);

                    if(field === "ceo_phone")
                        return formatMobile(data);

                    if(field === "manager_phone")
                        return formatMobile(data);

                    if(field === "phone" || field === "fax")
                        return formatPhone(data);

                    return data;
                }
            });
        });

        return columns;
    }

    /* ============================================================
    사업자등록증 업로드
    ============================================================ */
    function initBizCertUpload(){
        const drop  = document.getElementById('dropZoneBiz');
        const input = document.getElementById('modal_business_certificate');
        const list  = document.getElementById('bizCertList');
        if(!drop || !input) return;
        drop.addEventListener('click', () => {
            input.click();
        });
        input.addEventListener('change', e => {
            const file = e.target.files[0];
            if(!file) return;
            renderFile(file);
        });
        drop.addEventListener('dragover', e=>{
            e.preventDefault();
        });
        drop.addEventListener('drop', e=>{
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            if(!file) return;
            input.files = e.dataTransfer.files;
            renderFile(file);
        });

        function renderFile(file){
            const allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            const fileExt = String(file.name || '').split('.').pop()?.toLowerCase() || '';

            if (!allowedExt.includes(fileExt)) {
                AppCore.notify('warning', '사업자등록증은 PDF, JPG, PNG 파일만 업로드할 수 있습니다.');
                input.value = '';
                return;
            }

            if (Number(file.size || 0) > 10 * 1024 * 1024) {
                AppCore.notify('warning', '사업자등록증 파일은 10MB 이하만 업로드할 수 있습니다.');
                input.value = '';
                return;
            }

            const hasOriginal = drop.dataset.original === "1";
            let message = "";
            if(hasOriginal){
                message = "저장 시 기존 사업자등록증을 교체합니다.";
            }else{
                message = "저장 시 사업자등록증이 등록됩니다.";
            }
            const shortName = shortenFileName(file.name);
            const title = hasOriginal ? "교체 파일" : "선택 파일";
            const text = document.getElementById('dropZoneTextBiz');
            text.innerHTML = `
            파일 <strong>${title} (${shortName})</strong>
            <br>
            <span class="text-primary">
            ${message}
            </span>
            `;
        }
    }
    function initRrnUpload(){

        const drop  = document.getElementById('dropZoneRrn');
        const input = document.getElementById('modal_rrn_image');

        if(!drop || !input) return;

        // 클릭
        drop.addEventListener('click', () => {
            input.click();
        });

        // 파일 선택
        input.addEventListener('change', e => {

            const file = e.target.files[0];
            if(!file) return;

            renderFile(file);
        });

        // drag
        drop.addEventListener('dragover', e=>{
            e.preventDefault();
        });

        // drop
        drop.addEventListener('drop', e=>{

            e.preventDefault();

            const file = e.dataTransfer.files[0];
            if(!file) return;

            input.files = e.dataTransfer.files;

            renderFile(file);
        });

        function renderFile(file){

            const shortName = file.name.length > 20
                ? file.name.substring(0,17)+'...'
                : file.name;

            const text = document.getElementById('dropZoneTextRrn');

            text.innerHTML = `
            파일 <strong>${shortName}</strong>
            <br>
            <span class="text-primary">
            저장 시 신분증이 등록됩니다.
            </span>
            `;
        }
    }
    function shortenFileName(name, max = 20){ // 길이 제한 기본값
        if(!name) return '';
        const lastDot = name.lastIndexOf('.');
        if(lastDot <= 0){
            return name.length <= max
                ? name
                : name.substring(0, Math.max(1, max - 3)) + '...';
        }
        const ext = name.substring(lastDot);   // ".pdf"
        const base = name.substring(0, lastDot);
        if(name.length <= max){
            return name;
        }
        const keep = Math.max(1, max - ext.length - 3);
        return base.substring(0, keep) + '...' + ext;
    }

    function initBankFileUpload(){
        const drop  = document.getElementById('bankCopyUpload');
        const input = document.getElementById('modal_bank_file');
        const text  = document.getElementById('bankCopyText');
        if(!drop || !input || !text) return;
        if(!drop.dataset.original){
            drop.dataset.original = "0";
        }

        function renderFile(file){
            const hasOriginal = drop.dataset.original === "1";
            let message = "";
            if(hasOriginal){
                message = "저장 시 기존 통장사본을 교체합니다.";
            }else{
                message = "저장 시 통장사본이 등록됩니다.";
            }
            const shortName = shortenFileName(file.name, 20);
            text.innerHTML = `
            파일 <strong>통장사본</strong>
            <br>
            (${shortName})
            <br>
            <span class="text-primary">
            ${message}
            </span>
            `;
        }
        /* 클릭 업로드 */
        drop.addEventListener('click', () => {
            input.click();
        });
        /* 파일 선택 */
        input.addEventListener('change', e => {
            const file = e.target.files[0];
            if(!file) return;
            renderFile(file);
        });
        /* drag */
        drop.addEventListener('dragover', e=>{
            e.preventDefault();
        });
        /* drop */
        drop.addEventListener('drop', e=>{
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            if(!file) return;
            input.files = e.dataTransfer.files;
            renderFile(file);
        });
    }


    function bindBizStatusButton(){

        const btn = document.getElementById('btnCheckBizStatus');
        if(!btn) return;

        btn.addEventListener('click', async function(e){

            e.preventDefault();
            e.stopPropagation();

            const bizInput = document.getElementById('modal_business_number');

            if(!bizInput){
                console.error('사업자번호 input 없음');
                return;
            }

            const bizNo = bizInput.value.replace(/[^0-9]/g,'');

            if(!bizNo){
                AppCore.notify('warning','사업자번호를 입력하세요.');
                return;
            }

            if(bizNo.length !== 10){
                AppCore.notify('warning','사업자번호는 10자리입니다.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '조회중...';

            try{

                const res = await checkBusinessStatus(bizNo);

                if(!res || !res.data || res.data.status_code !== "OK"){
                    AppCore.notify('error','사업자 조회 실패');
                    return;
                }

                if(!res.data.data || !res.data.data.length){
                    AppCore.notify('warning','조회 결과 없음');
                    return;
                }

                const info = res.data.data[0];
                const statusSelect = document.getElementById('modal_business_status');

                /* ===============================
                   상태 매핑
                =============================== */
                const STATUS_MAP = {
                    "계속사업자": "정상",
                    "정상": "정상",
                    "폐업자": "폐업",
                    "폐업": "폐업",
                    "휴업자": "휴업",
                    "휴업": "휴업"
                };

                /* ------------------------------
                   1. 정상 상태 (b_stt)
                ------------------------------ */
                if(info.b_stt){

                    const mapped = STATUS_MAP[info.b_stt] ?? "";

                    AppCore.notify('success', `사업자 상태 : ${info.b_stt}`);

                    if(statusSelect){
                        statusSelect.value = mapped;
                        statusSelect.dispatchEvent(new Event('change'));
                    }

                    return;
                }

                /* ------------------------------
                   2. 미등록 사업자
                ------------------------------ */
                if(info.tax_type){

                    AppCore.notify('warning', info.tax_type);

                    if(statusSelect){
                        statusSelect.value = "";   // 선택 없음
                        statusSelect.dispatchEvent(new Event('change'));
                    }

                    return;
                }

                /* ------------------------------
                   3. 알 수 없는 상태
                ------------------------------ */
                AppCore.notify('warning','사업자 상태 확인 불가');

            }catch(err){

                console.error(err);
                AppCore.notify('error','사업자 조회 실패');

            }finally{

                btn.disabled = false;
                btn.innerHTML = '상태확인';

            }

        });
    }

    function maskRrn(rrn) {
        if (!rrn) return '';

        const clean = String(rrn).replace(/\D/g, '');

        if (clean.length <= 6) return clean;

        return clean.substring(0, 6) + '-' + '********';
    }

    function bindRrnInputEvents($) {
        $(document)
            .off('input.clientRrn focus.clientRrn blur.clientRrn', '#modal_rrn')
            .off('click.clientToggleRrn', '.toggle-rrn')

            .on('input.clientRrn', '#modal_rrn', function () {

                const $input = $(this);
                let raw = onlyNumber($input.val());

                raw = raw.substring(0, 13);

                $input.data('real', raw);

                this.dataset.real = raw;   // 원본 값 동기화

                if (rrnVisible) {
                    $input.val(formatRrn(raw));
                } else {
                    $input.val(maskRrn(raw));
                }
            })

            .on('focus.clientRrn', '#modal_rrn', function () {

                const $input = $(this);
                const raw = onlyNumber($input.data('real') || '');

                // 현재 표시 상태 유지
                if (rrnVisible) {
                    $input.val(formatRrn(raw));
                } else {
                    $input.val(maskRrn(raw));
                }
            })

            .on('blur.clientRrn', '#modal_rrn', function () {

                if (rrnVisible) return;

                const $input = $(this);
                const raw = onlyNumber($input.data('real') || '');

                $input.val(maskRrn(raw));
            })

            .on('click.clientToggleRrn', '.toggle-rrn', function () {

                const $input = $('#modal_rrn');
                const icon = this.querySelector('i');
                const realVal = onlyNumber($input.data('real') || '');

                rrnVisible = !rrnVisible;

                if (rrnVisible) {
                    $input.val(formatRrn(realVal));
                    icon?.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    $input.val(maskRrn(realVal));
                    icon?.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });
    }

    function formatRrn(rrn) {
        if (!rrn) return '';

        const clean = String(rrn).replace(/\D/g, '');

        if (clean.length <= 6) return clean;

        return clean.substring(0, 6) + '-' + clean.substring(6);
    }

})();
