// 경로: PROJECT_ROOT . '/assets/js/pages/ledger/account.js'
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';
window.AdminPicker = AdminPicker;
(() => {

    'use strict';

    console.log('[ledger-account] loaded');

    const API_LIST = "/api/ledger/account/list";

    let accountTable = null;
    let todayPicker = null;
    let accountModal = null;
    let excelModal = null;
    let trashDetailOpen = false;
    let currentAccountId = null;
    let clickTimer = null;
    let subAccountAbort = null;
    let currentSubPolicies = [];

    window.setPeriod = setPeriod;


/* =========================
   계정 컬럼 한글 매핑
========================= */

const ACCOUNT_COLUMN_MAP = {
    sort_no : { label:"순번", visible:true },
    account_code : { label:"계정코드", visible:true },
    account_name : { label:"계정과목", visible:true },
    account_group : { label:"구분", visible:true },
    parent_name : { label:"상위계정", visible:true },
    level : { label:"레벨", visible:true },
    normal_balance : { label:"차/대", visible:true },
    is_posting : { label:"전표입력", visible:true },
    is_active : { label:"사용", visible:true },
    allow_sub_account : { label:"보조계정허용", visible:false },
    sub_account_status : { label:"보조계정상태", visible:true },
    note : { label:"비고", visible:true },
    memo : { label:"메모", visible:false },
    parent_id : { visible:false }
};

window.TrashColumns = window.TrashColumns || {};
window.TrashColumns.account = function(row = {}) {
    return `
        <td>${escapeHtml(row.account_code ?? '')}</td>
        <td>${escapeHtml(row.account_name ?? '')}</td>
        <td>${escapeHtml(row.account_group ?? '')}</td>
        <td>${escapeHtml(row.deleted_at ?? '')}</td>
        <td>${escapeHtml(row.deleted_by_name ?? row.deleted_by ?? 'SYSTEM')}</td>
        <td class="text-center">
            <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id ?? '')}">복원</button>
            <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id ?? '')}">영구삭제</button>
        </td>
    `;
};

function renderAccountTrashDetail(detailEl, data = {}) {
    detailEl.innerHTML = `
        <h5 class="mb-3">${escapeHtml(data.account_name ?? '')}</h5>
        <table class="table table-sm">
            <tr><th width="140">계정코드</th><td>${escapeHtml(data.account_code ?? '')}</td></tr>
            <tr><th>계정과목명</th><td>${escapeHtml(data.account_name ?? '')}</td></tr>
            <tr><th>계정구분</th><td>${escapeHtml(data.account_group ?? '')}</td></tr>
            <tr><th>상위계정</th><td>${escapeHtml(data.parent_name ?? '')}</td></tr>
            <tr><th>잔액방향</th><td>${escapeHtml(data.normal_balance === 'credit' ? '대변' : (data.normal_balance === 'debit' ? '차변' : ''))}</td></tr>
            <tr><th>전표입력</th><td>${Number(data.is_posting ?? 0) === 1 ? '가능' : '불가'}</td></tr>
            <tr><th>사용여부</th><td>${Number(data.is_active ?? 0) === 1 ? '사용' : '미사용'}</td></tr>
            <tr><th>비고</th><td>${escapeHtml(data.note ?? '')}</td></tr>
            <tr><th>메모</th><td>${escapeHtml(data.memo ?? '')}</td></tr>
            <tr><th>삭제일시</th><td>${escapeHtml(data.deleted_at ?? '')}</td></tr>
            <tr><th>삭제자</th><td>${escapeHtml(data.deleted_by_name ?? data.deleted_by ?? '')}</td></tr>
        </table>
    `;
}

document.addEventListener('trash:changed', function(event) {
    if (event.detail?.type !== 'account') {
        return;
    }

    if (accountTable) {
        accountTable.ajax.reload(null, false);
    }
});

document.addEventListener('trash:detail-render', async function(event) {
    const detail = event.detail || {};

    if (detail.type !== 'account') {
        return;
    }

    const modal = detail.modal || document.getElementById('accountTrashModal');
    const detailEl = modal?.querySelector('#account-trash-detail');
    const row = detail.data || {};

    if (!detailEl) {
        return;
    }

    detailEl.innerHTML = '<div class="text-muted py-3">상세 정보를 불러오는 중입니다.</div>';

    try {
        const accountCode = row.account_code || '';
        if (!accountCode) {
            renderAccountTrashDetail(detailEl, row);
            return;
        }

        const res = await fetch('/api/ledger/account/detail?code=' + encodeURIComponent(accountCode));
        const json = await res.json();

        renderAccountTrashDetail(detailEl, json.success ? (json.data || row) : row);
    } catch (err) {
        console.error(err);
        renderAccountTrashDetail(detailEl, row);
    }
});


    /* ============================================================
       DOM READY
    ============================================================ */
    document.addEventListener('DOMContentLoaded', async () => {

        if (!window.jQuery) {
            console.error('jQuery not loaded');
            return;
        }

        const $ = window.jQuery;

        const modalEl = document.getElementById('accountModal');

        accountModal = new bootstrap.Modal(modalEl, {
            focus:false
        });

        const accountExcelModalEl = document.getElementById('accountExcelModal');
        if (accountExcelModalEl) {
            excelModal = bootstrap.Modal.getOrCreateInstance(accountExcelModalEl, {
                focus: false
            });
        }

        modalEl.addEventListener('hidden.bs.modal', () => {
            const form = document.getElementById('account-edit-form');
            form.reset();
            currentSubPolicies = [];
            renderSubPolicyRows();
            updateAllowSubAccountDisplay();
        });

        const container = document.getElementById('searchFormContainer');
        const body = document.getElementById('searchFormBody');
        const btn = document.getElementById('toggleSearchForm');

        if (container) container.classList.add('collapsed');
        if (body) body.classList.add('hidden');
        if (btn) btn.textContent = '\uC5F4\uAE30';

        btn.addEventListener('click', () => {

            body.classList.toggle('hidden');
            container.classList.toggle('collapsed');

            const hidden = body.classList.contains('hidden');

            if(hidden){
                btn.textContent = '열기';
                accountTable.page.len(100).draw(false);
            }else{
                btn.textContent = '접기';
                accountTable.page.len(100).draw(false);
            }

            /* 즉시 1차 반영 */
            btn.textContent = hidden ? '\uC5F4\uAE30' : '\uC811\uAE30';
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    updateTableHeight();

                    if(accountTable){
                        accountTable.columns.adjust().draw(false);
                    }

                    forceTableHeightSync();
                });
            });

            /* 토글 애니메이션 동안 계속 보정 */
            /* transition 끝난 뒤 최종 보정 */
        });




        bindSearchEvents($);     // 🔥 먼저 바인딩
        initDataTable($);

        updateTableHeight();   // 초기 높이 맞춤
        /* 🔥 화면 리사이즈 시 테이블 높이 재계산 */
        window.addEventListener('resize', () => {
            updateTableHeight();

            if(accountTable){
                accountTable.columns.adjust().draw(false);
            }
        });

        bindTableEvents($);
        bindModalEvents($);
        bindExcelEvents($);
        afterAddSubAccount();
        openParentAccountPicker();


        /* ================================
           툴팁
        ================================ */
        setupTooltip("tooltipTrigger","tooltipContainer");
        setupTooltip("periodTooltipTrigger","periodTooltipContainer");

        initAdminDatePicker();
        bindAdminDateInputs();


        document.querySelectorAll('.date-icon').forEach(icon => {

            icon.addEventListener('click', e => {

                e.preventDefault();
                e.stopPropagation();

                const wrap = icon.closest('.date-input, .date-input-wrap');
                const input = wrap ? wrap.querySelector('input') : null;

                if (!input) return;

                const picker = initAdminDatePicker();
                if (!picker) return;

                picker.__target = input;

                if (typeof picker.clearDate === 'function') {
                    picker.clearDate();
                }

                const v = input.value;

                if (v) {
                    const d = new Date(v);
                    if (!isNaN(d)) picker.setDate(d);
                }

                picker.open({
                    anchor: input
                });

            });

        });

        //모달에서 picker 위치 문제 방지
        modalEl.addEventListener('shown.bs.modal', () => {
            bindAdminDateInputs();
        });



       /* =====================================
        휴지통 복원 / 완전삭제 (FIX)
        ===================================== */
        document.addEventListener('click', async function(e){

            const target = e.target;
            if (target.closest('#accountTrashModal')) {
                return;
            }

            /* =======================
            단일복원
            ======================= */
            const restoreBtn = target.closest('.btn-restore');

            if(restoreBtn){

                e.preventDefault();
                e.stopPropagation();

                const id = restoreBtn.dataset.id;

                if(!confirm('계정을 복원하시겠습니까?')) return;

                try{

                    const res = await fetch('/api/ledger/account/restore',{
                        method:'POST',
                        headers:{
                            'Content-Type':'application/x-www-form-urlencoded'
                        },
                        body:`id=${id}`
                    });

                    const json = await res.json();

                    if(json.success){

                        AppCore.notify('success','복원 완료');

                        closeTrashDetail();

                        await loadaccountTrash();
                        accountTable.ajax.reload(null,false);

                    }else{
                        AppCore.notify('error','복원 실패');
                    }

                }catch(err){
                    console.error(err);
                    AppCore.notify('error','복원 처리 중 오류');
                }

                return;
            }

            /* =======================
            단일영구삭제
            ======================= */
            const purgeBtn = target.closest('.btn-purge');

            if(purgeBtn){

                e.preventDefault();
                e.stopPropagation();

                const id = purgeBtn.dataset.id;

                if(!confirm('완전 삭제하시겠습니까? (복구 불가)')) return;

                try{

                    const res = await fetch('/api/ledger/account/hard-delete',{
                        method:'POST',
                        headers:{
                            'Content-Type':'application/x-www-form-urlencoded'
                        },
                        body:`id=${id}`
                    });

                    const json = await res.json();

                    if(json.success){

                        AppCore.notify('success','완전 삭제 완료');

                        closeTrashDetail();

                        await loadaccountTrash();

                    }else{
                        AppCore.notify('error','삭제 실패');
                    }

                }catch(err){
                    console.error(err);
                    AppCore.notify('error','삭제 처리 중 오류');
                }

                return;
            }

            /* =======================
            선택복원 (ID 수정됨 🔥)
            ======================= */
            const restoreSelectedBtn = target.closest('#btnRestoreSelectedAccount');

            if(restoreSelectedBtn){

                e.preventDefault();
                e.stopPropagation();

                const ids = getSelectedAccountIds();

                if(!ids.length){
                    AppCore.notify('warning','선택된 계정이 없습니다');
                    return;
                }

                if(!confirm(`${ids.length}건 복원하시겠습니까?`)) return;

                try{

                    const res = await fetch('/api/ledger/account/restore-bulk',{
                        method:'POST',
                        headers:{
                            'Content-Type':'application/json'
                        },
                        body: JSON.stringify({ ids })
                    });

                    const json = await res.json();

                    if(json.success){

                        AppCore.notify('success','선택 복원 완료');

                        closeTrashDetail();

                        await loadaccountTrash();
                        accountTable.ajax.reload(null,false);

                    }else{
                        AppCore.notify('error','선택 복원 실패');
                    }

                }catch(err){
                    console.error(err);
                    AppCore.notify('error','선택 복원 처리 중 오류');
                }

                return;
            }

            /* =======================
            선택영구삭제 (ID 수정됨 🔥)
            ======================= */
            const deleteSelectedBtn = target.closest('#btnDeleteSelectedAccount');

            if(deleteSelectedBtn){

                e.preventDefault();
                e.stopPropagation();

                const ids = getSelectedAccountIds();

                if(!ids.length){
                    AppCore.notify('warning','선택된 계정이 없습니다');
                    return;
                }

                if(!confirm(`${ids.length}건 영구삭제하시겠습니까? (복구 불가)`)) return;

                try{

                    const res = await fetch('/api/ledger/account/hard-delete-bulk',{
                        method:'POST',
                        headers:{
                            'Content-Type':'application/json'
                        },
                        body: JSON.stringify({ ids })
                    });

                    const json = await res.json();

                    if(json.success){

                        AppCore.notify('success','선택 영구삭제 완료');

                        closeTrashDetail();

                        await loadaccountTrash();

                    }else{
                        AppCore.notify('error','선택 삭제 실패');
                    }

                }catch(err){
                    console.error(err);
                    AppCore.notify('error','선택 삭제 처리 중 오류');
                }

                return;
            }

            /* =======================
            전체영구삭제 (ID 수정됨 🔥)
            ======================= */
            const deleteAllBtn = target.closest('#btnDeleteAllAccounts');

            if(deleteAllBtn){

                e.preventDefault();
                e.stopPropagation();

                if(!confirm('전체 영구삭제하시겠습니까? (복구 불가)')) return;

                try{

                    const res = await fetch('/api/ledger/account/hard-delete-all',{
                        method:'POST'
                    });

                    const json = await res.json();

                    if(json.success){

                        AppCore.notify('success','전체 영구삭제 완료');

                        closeTrashDetail();

                        await loadaccountTrash();

                    }else{
                        AppCore.notify('error','전체 삭제 실패');
                    }

                }catch(err){
                    console.error(err);
                    AppCore.notify('error','전체 삭제 처리 중 오류');
                }

                return;
            }

            /* =======================
            전체복원
            ======================= */
            const restoreAllBtn = target.closest('#btnRestoreAll_account');

            if(restoreAllBtn){

                e.preventDefault();
                e.stopPropagation();

                if(!confirm('휴지통의 모든 계정을 복원하시겠습니까?')) return;

                try{

                    const res = await fetch('/api/ledger/account/restore-all',{
                        method:'POST'
                    });

                    const json = await res.json();

                    if(json.success){

                        AppCore.notify('success','전체 복원 완료');

                        closeTrashDetail();

                        await loadaccountTrash();
                        accountTable.ajax.reload(null,false);

                    }else{
                        AppCore.notify('error','전체 복원 실패');
                    }

                }catch(err){
                    console.error(err);
                    AppCore.notify('error','전체 복원 처리 중 오류');
                }

                return;
            }

        });


        $(document).on('click','#btnSelectParent', function(){

            const container = document.getElementById('base-picker');

            container.innerHTML = '';
            delete container.__pickerInstance;

            const picker = AdminPicker.create({
                type: 'account',
                container
            });

            picker.subscribe((event, item) => {

                if(!item) return;

                $('#modal_parent_id').val(item.id);
                $('#modal_parent_name').val(item.account_name);
                $('#modal_account_group').val(item.account_group);

                picker.close();
            });

            picker.open({
                anchor: this
            });

        });

        $(document).on('click', '#btnClearParent', function(){

            $('#modal_parent_id').val('');
            $('#modal_parent_name').val('');
            $('#modal_account_group').val('');

        });



    });









    document.addEventListener('click', async function(e){

        const modal = e.target.closest('#accountTrashModal');
        if(!modal) return;
        if (modal.dataset.listUrl) return;

        const layout = modal.querySelector('.trash-layout');
        const detail = modal.querySelector('#account-trash-detail');
        const right  = modal.querySelector('.trash-right');

        if(!layout || !detail || !right){
            return;
        }

        if(e.target.closest('.btn-restore, .btn-purge, .trash-check, .trash-check-all')){
            return;
        }

        const row = e.target.closest('#account-trash-table tbody tr');
        const sidebar = e.target.closest('.trash-right');

        if(row){

            const code = row.dataset.accountCode;

            try{

                const res = await fetch('/api/ledger/account/detail?code=' + code);
                const json = await res.json();

                if(!json.success){
                    AppCore.notify('error','계정 조회 실패');
                    return;
                }

                const data = json.data;

                renderaccountTrashDetail(data);

                layout.classList.add('open');
                right.classList.add('open');
                detail.style.display = 'block';

                trashDetailOpen = true;

            }catch(err){

                console.error(err);
                AppCore.notify('error','계정 조회 오류');

            }

            return;
        }

        if(sidebar) return;

        if(trashDetailOpen){

            layout.classList.remove('detail-open');
            right.classList.remove('open');

            detail.style.display = 'none';

            trashDetailOpen = false;
        }

    });
















    /* =========================
    휴지통 전체 체크
    ========================= */

    document.addEventListener('change', function(e){

        const checkAll = e.target.closest('#trashCheckAllAccount');

        if(!checkAll) return;
        if (checkAll.closest('#accountTrashModal')?.dataset.listUrl) return;

        const checked = checkAll.checked;

        document.querySelectorAll('#account-trash-table .trash-check')
            .forEach(cb=>{
                cb.checked = checked;
            });

    });



    /* =========================
    ESC → 모달 닫기
    ========================= */

    document.addEventListener('keydown', function(e){

        if(e.key !== 'Escape') return;

        const modalEl = document.getElementById('accountModal');

        if(!modalEl) return;

        const modalInstance = bootstrap.Modal.getInstance(modalEl);

        if(modalInstance){
            modalInstance.hide();
        }

    });

    document.addEventListener('click', function(e){

        /* =========================
           🔥 1. 닫기 버튼 최우선 처리
        ========================= */
        const closeBtn = e.target.closest('#btnCloseSubPanel');

        if(closeBtn){

            const panel = document.querySelector('.account-right-panel');

            if(panel){
                panel.style.display = 'none';
            }

            currentAccountId = null;

            $('#btnAddSubAccount').prop('disabled', true);

            requestAnimationFrame(() => {
                accountTable.columns.adjust().draw(false);
            });

            return; // 🔥 여기서 끝
        }


        /* =========================
           기존 로직
        ========================= */

        const accountRow = e.target.closest('#account-table tbody tr');
        const subPanel   = e.target.closest('.account-right-panel');

        if(e.target.closest('.trash-check')){
            e.stopPropagation();
        }

        if(e.target.classList.contains('btnDeleteSubAccount')){
            setTimeout(adjustAllDataTables, 200);
        }

        /* 버튼 클릭 유지 */
        if(e.target.closest('.link-account, .btn-sub-add, .btn-sub-edit')){
            return;
        }

        /* 🔥 패널 내부 클릭이면 유지 */
        if(subPanel){
            return;
        }

        /* 🔥 외부 클릭 → 닫기 */
        const panel = document.querySelector('.account-right-panel');

        if(panel){
            panel.style.display = 'none';
        }

        requestAnimationFrame(() => {
            accountTable.columns.adjust().draw(false);
        });

        currentAccountId = null;

        $('#btnAddSubAccount').prop('disabled', true);

    });



    function closeTrashDetail(){

        const modal = document.getElementById('accountTrashModal');
        const layout = modal?.querySelector('.trash-layout');
        const detail = modal?.querySelector('#account-trash-detail');
        const right  = modal?.querySelector('.trash-right');

        if(layout) layout.classList.remove('open', 'detail-open');
        if(right)  right.classList.remove('open');
        if(detail) detail.style.display = 'none';

        trashDetailOpen = false;
    }





    function getSelectedAccountIds(){
        const ids = [];

        document.querySelectorAll('#account-trash-table tbody tr')
            .forEach(tr => {

                const checkbox = tr.querySelector('.trash-check');

                if(checkbox && checkbox.checked){
                    ids.push(tr.dataset.id);
                }
            });

        return ids;
    }

    function openParentAccountPicker(){

        // 🔥 임시: 계정 리스트 API 호출
        fetch('/api/ledger/account/list')
        .then(res => res.json())
        .then(json => {

            if(!json.success || !json.data.length){
                AppCore.notify('error','계정 목록 없음');
                return;
            }

            // 🔥 임시 선택 (첫번째 계정)
            const parent = json.data[0];

            console.log('선택된 상위계정:', parent);

            // 🔥 값 세팅
            $('#modal_parent_id').val(parent.id);
            $('#modal_parent_name').val(parent.account_name);

            // 🔥 핵심
            $('#modal_account_group').val(parent.account_group);

        })
        .catch(err => {
            console.error(err);
            AppCore.notify('error','상위계정 불러오기 실패');
        });

    }


    function afterAddSubAccount(){

        loadSubAccounts(currentAccountId);

        setTimeout(adjustAllDataTables, 200);
    }

    function adjustAllDataTables(){

        if(accountTable){
            accountTable.columns.adjust().draw(false);
        }

    }
    function renderaccountTrashDetail(data){

        const detail = document.getElementById('account-trash-detail');

        detail.innerHTML = `
        <h5 class="mb-3">${data.account_name ?? ''}</h5>

        <table class="table table-sm">

            <tr>
                <th width="140">계정코드</th>
                <td>${data.account_code ?? ''}</td>
            </tr>

            <tr>
                <th>계정명</th>
                <td>${data.account_name ?? ''}</td>
            </tr>

            <tr>
                <th>계정구분</th>
                <td>${data.account_group ?? ''}</td>
            </tr>

            <tr>
                <th>상위계정</th>
                <td>${data.parent_name ?? ''}</td>
            </tr>

            <tr>
                <th>잔액방향</th>
                <td>
                    ${
                        data.normal_balance === 'debit' ? '차변' :
                        data.normal_balance === 'credit' ? '대변' : ''
                    }
                </td>
            </tr>

            <tr>
                <th>전표입력</th>
                <td>
                    ${data.is_posting == 1 ? '가능' : '불가'}
                </td>
            </tr>

            <tr>
                <th>사용여부</th>
                <td>
                    ${data.is_active == 1 ? '사용' : '미사용'}
                </td>
            </tr>

            <tr>
                <th>보조계정</th>
                <td>
                    ${data.allow_sub_account == 1 ? '허용' : '미허용'}
                </td>
            </tr>

            <tr>
                <th>비고</th>
                <td>${data.note ?? ''}</td>
            </tr>

            <tr>
                <th>메모</th>
                <td>${data.memo ?? ''}</td>
            </tr>

            <tr>
                <th>등록일시</th>
                <td>${data.created_at ?? ''}</td>
            </tr>

            <tr>
                <th>등록자</th>
                <td>${data.created_by_name ?? data.created_by ?? ''}</td>
            </tr>


            <tr>
                <th>수정일시</th>
                <td>${data.updated_at ?? ''}</td>
            </tr>

            <tr>
                <th>수정자</th>
                <td>${data.updated_by_name ?? data.updated_by ?? ''}</td>
            </tr>

        </table>
        `;
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

        input.addEventListener('click', e => {

        e.preventDefault();
        e.stopPropagation();

        const picker = initAdminDatePicker();
        if(!picker) return;

        picker.__target = input;

        /* 🔥 항상 상태 초기화 */
        if (typeof picker.clearDate === 'function') {
            picker.clearDate();
        }

        const v = input.value;

        /* 값 있으면 다시 세팅 */
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


    /* ============================================================
       DataTable 초기화
    ============================================================ */

    function getTableHeight() {

        const main = document.querySelector('.main-content');
        const tableBox = document.querySelector('.table-box');
        const tableWrapper = document.querySelector('#account-table_wrapper');
        const dtTop = document.querySelector('#account-table_wrapper .dt-top');
        const dtBottom = document.querySelector('#account-table_wrapper .dt-bottom');
        const scrollHead = document.querySelector('#account-table_wrapper .dataTables_scrollHead');
        const scrollBody = document.querySelector('#account-table_wrapper .dataTables_scrollBody');
        const footer = document.querySelector('.footer.footer-fixed');

        if (!main || !tableBox || !tableWrapper) {
            return 320;
        }

        const mainRect = main.getBoundingClientRect();
        const tableBoxRect = tableBox.getBoundingClientRect();

        const mainStyle = window.getComputedStyle(main);
        const tableBoxStyle = window.getComputedStyle(tableBox);
        const tableBoxPaddingTop = parseFloat(tableBoxStyle.paddingTop) || 0;
        const tableBoxPaddingBottom = parseFloat(tableBoxStyle.paddingBottom) || 0;
        const tableBoxBorderTop = parseFloat(tableBoxStyle.borderTopWidth) || 0;
        const tableBoxBorderBottom = parseFloat(tableBoxStyle.borderBottomWidth) || 0;
        const tableBoxMarginBottom = parseFloat(tableBoxStyle.marginBottom) || 0;
        const mainPaddingBottom = parseFloat(mainStyle.paddingBottom) || 0;

        const dtTopHeight = dtTop ? dtTop.offsetHeight : 0;
        const dtBottomHeight = dtBottom ? dtBottom.offsetHeight : 0;
        const scrollHeadHeight = scrollHead ? scrollHead.offsetHeight : 0;
        const footerTop = footer?.getBoundingClientRect?.().top;
        const bottomLimit = Number.isFinite(footerTop)
            ? Math.min(footerTop, mainRect.bottom - mainPaddingBottom)
            : mainRect.bottom - mainPaddingBottom;
        const estimatedScrollBodyTop =
            tableBoxRect.top
            + tableBoxPaddingTop
            + tableBoxBorderTop
            + dtTopHeight
            + scrollHeadHeight;
        const scrollBodyTop = scrollBody
            ? scrollBody.getBoundingClientRect().top
            : estimatedScrollBodyTop;
        const safetyGap = 4;

        const available =
            bottomLimit
            - scrollBodyTop
            - dtBottomHeight
            - tableBoxPaddingBottom
            - tableBoxBorderBottom
            - tableBoxMarginBottom
            - safetyGap;

        return Math.max(220, Math.floor(available));
    }
    function updateTableHeight() {
        if (!accountTable) return;

        const height = getTableHeight();

        const wrapper = document.querySelector('#account-table_wrapper .dataTables_scrollBody');
        if (wrapper) {
            wrapper.style.height = height + 'px';
            wrapper.style.maxHeight = height + 'px';
        }

        const settings = accountTable.settings()[0];
        settings.oScroll.sY = height + 'px';

        accountTable.columns.adjust().draw(false);
    }
    function forceTableHeightSync() {
        if (!accountTable) return;

        const height = getTableHeight();
        const wrapper = document.querySelector('#account-table_wrapper .dataTables_scrollBody');

        if (wrapper) {
            wrapper.style.height = height + 'px';
            wrapper.style.maxHeight = height + 'px';
        }

        const settings = accountTable.settings()[0];
        settings.oScroll.sY = height + 'px';

        accountTable.columns.adjust().draw(false);
    }

    function animateSearchFormRelayout(duration = 280) {

        if (!accountTable) return;

        const startedAt = performance.now();

        function frame(now) {

            if (accountTable) {
                updateTableHeight();
            }

            if (now - startedAt < duration) {
                requestAnimationFrame(frame);
            }
        }

        requestAnimationFrame(frame);
    }
    function initDataTable($) {

        const columns = buildAccountColumns();

        accountTable = $('#account-table').DataTable({

            ajax: {
                url: API_LIST,
                type: "GET",
                cache: false,
                dataSrc: json => {
                    console.log('[ledger-account] apiList response', json);
                    console.log('[ledger-account] controller data count', Array.isArray(json?.data) ? json.data.length : 0);
                    if(json?.success === false){
                        console.error('[ledger-account] apiList failed', json?.message);
                    }
                    return json?.data ?? [];
                }

            },

            rowId: 'id',

            rowReorder: {
                selector: 'td.reorder-handle',
                dataSrc: 'sort_no'
            },

            scrollY: getTableHeight(),
            scrollCollapse: true,

            paging: true,
            processing: true,
            deferRender: true,

            responsive: false,
            autoWidth: true,

            language: {
                emptyTable: '계정목록 없음',
                zeroRecords: '검색 결과가 없습니다'
            },

            dom: '<"dt-top d-flex justify-content-end align-items-center gap-2"fBl>rt<"dt-bottom d-flex justify-content-between align-items-center"ip>',

            columns: columns,

            order: [[1, 'asc']],

            pageLength: 100,

            lengthMenu: [10, 25, 50, 100],

            infoCallback: function(settings, start, end, max, total){
                const displayLength = Number(settings._iDisplayLength || 10);
                const currentPage = total > 0
                    ? Math.floor(Number(settings._iDisplayStart || 0) / displayLength) + 1
                    : 0;
                const totalPages = total > 0
                    ? Math.ceil(total / displayLength)
                    : 0;

                return `${currentPage} / ${totalPages} 페이지 (총 ${max}건 / 검색 ${total}건)`;
            },

            initComplete: function () {
                const table = this.api();
                table.columns.adjust();

                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        updateTableHeight();
                        table.columns.adjust().draw(false);
                    });
                });
            },

            buttons: [

                {
                    extend: "colvis",
                    text: "열 표시",
                    className: "btn btn-secondary btn-sm",
                    popoverTitle: 'Column visibility',
                    collectionLayout: 'fixed two-column',
                    columns: ':not(.reorder-handle)'
                },

                "copy",

                "excel",

                {
                    extend: "print",
                    text: "인쇄",
                    title: "계정 목록",

                    customize: function (win) {

                        const doc = win.document;

                        /* 기존 스타일 제거 */
                        const links = doc.querySelectorAll("link, style");
                        links.forEach(el => el.remove());

                        /* 인쇄용 스타일 */
                        const style = doc.createElement("style");
                        style.innerHTML = `
                            @page {
                                size: A4 landscape;
                                margin: 15mm;
                            }
                            body{
                                font-family: Arial, Helvetica, sans-serif;
                                font-size:12px;
                                padding:20px;
                            }

                            h1{
                                text-align:center;
                                font-size:22px;
                                margin-bottom:20px;
                            }

                            table{
                                border-collapse:collapse;
                                width:100%;
                                table-layout:auto;
                            }

                            th, td{
                                border:1px solid #999;
                                padding:6px;
                                text-align:left;
                                white-space:normal;
                                word-break:break-word;
                            }

                            th{
                                background:#f2f2f2;
                                font-weight:bold;
                            }
                        `;

                        doc.head.appendChild(style);

                        /* 제목 추가 */
                        doc.body.insertAdjacentHTML(
                            "afterbegin",
                            "<h1>계정 목록</h1>"
                        );

                    }
                },

                /* 🔽 엑셀관리 진입 버튼 */
                {
                    text: "엑셀관리",
                    className: "btn btn-success btn-sm",
                    action: function () {
                        openAccountExcelModal();
                    }
                },

                {
                    text: "휴지통",
                    className: "btn btn-danger btn-sm",
                    action: function () {

                        const modal = new bootstrap.Modal(
                            document.getElementById('accountTrashModal')
                        );

                        modal.show();

                    }
                },

                {
                    text: '새 계정',
                    className: 'btn btn-warning btn-sm',
                    action: () => {

                        $('#account-edit-form')[0].reset();
                        currentSubPolicies = [];
                        renderSubPolicyRows();
                        updateAllowSubAccountDisplay();

                        $('#modal_account_id').val('');
                        $('#modal_sort_no').val('');

                        $('#btnDeleteAccount').hide();

                        window.isNewaccount = true;

                        document.getElementById('accountModalLabel').textContent = '계정과목 등록';

                        accountModal.show();
                    }
                }

            ],

            language: {
                lengthMenu: "페이지당 _MENU_ 개씩 보기",
                zeroRecords: "데이터가 없습니다.",
                info: "_PAGE_ 페이지 / 총 _PAGES_ 페이지",
                infoEmpty: "데이터 없음",
                infoFiltered: "(총 _MAX_개 중 필터링됨)",
                search: "검색:",
                paginate: {
                    first: "처음",
                    last: "끝",
                    next: "다음",
                    previous: "이전"
                }
            }

        });



        /* ==========================================================
           DataTable 초기화 완료 후 검색조건 자동 생성
        ========================================================== */
        accountTable.on('init.dt', function(){

            const fields = getTableColumns();

            const $select = $('.search-condition:first select');

            $select.empty();

            fields.forEach((f,i)=>{

                const selected = (f.value === 'account_name') ? 'selected' : '';

                $select.append(`<option value="${f.value}" ${selected}>${f.label}</option>`);

            });

        });

    }




    /* ============================================================
    테이블 이벤트
    ============================================================ */

    function bindTableEvents($) {

        $(document).on('focus', '#modal_sort_no', function(){

            if(window.isNewaccount){

                AppCore.notify(
                    'info',
                    '순번은 저장 시 자동 생성됩니다.'
                );

            }

        });




        $('#account-table tbody').on('click', '.btn-sub-add, .btn-sub-edit', function(e){

            e.preventDefault();
            e.stopPropagation();

            const id = $(this).data('id');

            if(!id) return;

            currentAccountId = id;

            const panel = document.querySelector('.account-right-panel');

            if(panel){
                panel.style.display = 'block';

                setTimeout(() => {
                    accountTable.columns.adjust();
                    accountTable.draw(false);
                }, 0);
            }

            $('#btnAddSubAccount').prop('disabled', false);

            loadSubAccounts(currentAccountId);

        });


        /* ================================
        셀 포인터 행/열 강조
        ================================ */
        $('#account-table tbody').on('mouseenter','td',function(){

            const cell = accountTable.cell(this);
            const idx = cell.index();

            if(!idx) return;

            const colIndex = idx.column;

            /* 행 강조 */
            $('#account-table tbody tr').removeClass('row-highlight');
            $(this).closest('tr').addClass('row-highlight');

            /* 열 강조 초기화 */
            $('#account-table td, #account-table th').removeClass('col-highlight');

            /* body (DataTables 방식) */
            accountTable.cells(null, colIndex).nodes().each(function(cell){
                $(cell).addClass('col-highlight');
            });

            /* header */
            $(accountTable.column(colIndex).header()).addClass('col-highlight');

        });



        $('#account-table tbody').on('dblclick', 'tr', async function () {

            /* 🔥 click 취소 */
            clearTimeout(clickTimer);

            const data = accountTable.row(this).data();
            if (!data) return;

            window.isNewaccount = false;

            document.getElementById('accountModalLabel').textContent = '계정과목 수정';

            $('#btnDeleteAccount').show();

            accountModal.show();

            try{

                const res = await fetch('/api/ledger/account/detail?code=' + encodeURIComponent(data.account_code));
                const json = await res.json();

                if(!json.success || !json.data){
                    AppCore.notify('error', '계정 상세 조회 실패');
                    return;
                }

                $('#modal_account_id').val(json.data.id ?? '');
                fillModal(json.data);

            }catch(err){

                console.error(err);
                AppCore.notify('error', '계정 상세 조회 오류');
            }
        });


        /* ================================
        셀 클릭 → 검색조건 자동 입력
        (검색 실행은 사용자가 버튼 클릭)
        ================================ */

        $('#account-table tbody').on('click', 'td', function (e) {

            /* 🔥 버튼/링크 클릭이면 무시 (핵심) */
            if($(e.target).closest('.link-account, .btn-sub-add, .btn-sub-edit').length){
                return;
            }

            const cell = accountTable.cell(this);

            const value = cell.data();

            const colIndex = cell.index().column;

            const field = accountTable.column(colIndex).dataSrc();

            if(!field || field === null) return;

            const $first = $('.search-condition').first();

            $first.find('select').val(field);

            $first.find('input').val(value);

        });


        /* RowReorder */

        accountTable.on('row-reorder', function (e, diff) {

            if (!diff.length) return;

            const changes = [];

            diff.forEach(d => {

                const rowData = accountTable.row(d.node).data();

                changes.push({
                    id: rowData.id,
                    newSortNo: d.newData
                });

            });

            if (!changes.length) return;

            $.ajax({

                url: '/api/ledger/account/reorder',

                method: 'POST',

                contentType: 'application/json',

                data: JSON.stringify({ changes })

            })
            .done(() => {

                accountTable.ajax.reload(null, false);

            })
            .fail(() => {

                alert('순서 저장 실패');

            });

        });


        /* ================================
        보조계정 추가
        ================================ */

        $(document).on('click','#btnAddSubAccount',async function(){

            if(!currentAccountId){

                AppCore.notify(
                    'warning',
                    '먼저 계정을 선택하세요'
                );

                return;
            }

            const tbody = document.querySelector('#subaccount-table tbody');

            if(!tbody) return;

            /* 🔥 이미 입력중이면 중복 생성 방지 */
            if(document.querySelector('.sub-new-row')){
                const input = document.querySelector('.sub-new-input');
                if(input) input.focus();
                return;
            }

            /* 🔥 신규 입력 row 생성 */
            const tr = document.createElement('tr');
            tr.classList.add('sub-new-row');

            tr.innerHTML = `
            <td>NEW</td>
            <td>
                <input
                    type="text"
                    class="form-control form-control-sm sub-new-input"
                    placeholder="보조계정 입력 후 Enter">
            </td>
            <td colspan="2">
                <span class="text-muted">Enter: 저장 / Esc: 취소</span>
            </td>
            `;

            tbody.prepend(tr);

            /* 🔥 자동 포커스 */
            const input = tr.querySelector('.sub-new-input');
            input.focus();

            /* 🔥 Enter / Esc 처리 */
            input.addEventListener('keydown', async function(e){

                /* =====================
                ESC → 취소
                ===================== */
                if(e.key === 'Escape'){
                    tr.remove();
                    return;
                }

                /* =====================
                Enter → 저장
                ===================== */
                if(e.key === 'Enter'){

                    e.preventDefault();

                    const name = input.value.trim();

                    if(!name){
                        AppCore.notify('warning','보조계정명을 입력하세요');
                        return;
                    }

                    try{

                        const form = new URLSearchParams();
                        form.append('account_id', currentAccountId);
                        form.append('sub_name', name);

                        const res = await fetch('/api/ledger/sub-account/save',{
                            method:'POST',
                            headers:{
                                'Content-Type':'application/x-www-form-urlencoded'
                            },
                            body: form
                        });

                        const json = await res.json();

                        if(json.success){

                            AppCore.notify('success','보조계정 등록 완료');

                            tr.remove();

                            loadSubAccounts(currentAccountId);
                            accountTable.ajax.reload(null,false);

                        }else{

                            AppCore.notify('error',json.message || '등록 실패');
                        }

                    }catch(err){
                        console.error(err);
                    }
                }
            });
        });


        /* ================================
        보조계정 삭제
        ================================ */

        $(document).on('click','.btnDeleteSubAccount',async function(){

            const id = $(this).data('id');

            if(!confirm('삭제하시겠습니까?')) return;

            const form = new URLSearchParams();
            form.append('id', id);

            const res = await fetch(
                '/api/ledger/sub-account/delete',
                {
                    method:'POST',
                    headers:{
                        'Content-Type':'application/x-www-form-urlencoded'
                    },
                    body: form
                }
            );

            const json = await res.json();

            if(json.success){

                AppCore.notify('success','삭제되었습니다');

                loadSubAccounts(currentAccountId);

                accountTable.ajax.reload(null,false);

            }else{

                AppCore.notify('error',json.message);

            }

        });

        $(document).on('focus', '.sub-name-input, .sub-note-input, .sub-memo-input', function(){

            $(this).data('original', $(this).val());

        });

        $(document).on('blur', '.sub-name-input, .sub-note-input, .sub-memo-input', async function(){

            if($(this).closest('tr').hasClass('sub-new-row')){
                return;
            }

            const id = $(this).data('id');

            const original = $(this).data('original');
            const current  = $(this).val();

            if(original === current){
                return;
            }

            let mainRow;
            let detailRow;

            if($(this).hasClass('sub-name-input')){
                mainRow = $(this).closest('tr');
                detailRow = mainRow.next('.sub-detail-row');
            }else{
                detailRow = $(this).closest('tr');
                mainRow = detailRow.prev('tr');
            }

            const name = mainRow.find('.sub-name-input').val();
            const note = detailRow.find('.sub-note-input').val();
            const memo = detailRow.find('.sub-memo-input').val();

            if(!name || name === 'undefined'){
                AppCore.notify('warning','보조계정명을 입력하세요');
                return;
            }

            try{

                const form = new URLSearchParams();

                form.append('id', id);
                form.append('sub_name', name);
                form.append('note', note || '');
                form.append('memo', memo || '');

                console.log('UPDATE PAYLOAD:', {
                    id, name, note, memo
                });

                const res = await fetch('/api/ledger/sub-account/update',{
                    method:'POST',
                    headers:{
                        'Content-Type':'application/x-www-form-urlencoded'
                    },
                    body: form
                });

                const json = await res.json();

                if(!json.success){
                    AppCore.notify('error', json.message);
                }

            }catch(err){
                console.error(err);
            }
        });

        /* 🔥 Enter → 다음 필드 이동 */
        $(document).on('keydown','.sub-name-input, .sub-note-input, .sub-memo-input',function(e){

            if(e.key !== 'Enter') return;

            const value = $(this).val();   // 🔥 추가 (핵심)

            if(value && value.length > 50){
                AppCore.notify('warning','50자 이하로 입력하세요');
                return;
            }

            e.preventDefault();

            const inputs = $('.sub-name-input, .sub-note-input, .sub-memo-input');
            const index = inputs.index(this);

            const next = inputs.eq(index + 1);

            if(next.length){
                next.focus();
            }else{
                $(this).blur(); // 마지막이면 저장 트리거
            }
        });

        $(document).on('click','.btnToggleDetail',function(){

            const tr = $(this).closest('tr');
            const detail = tr.next('.sub-detail-row');

            if(detail.is(':visible')){
                detail.hide();
            }else{
                detail.show();
            }

        });


    }


    /* ============================================================
       모달 저장 / 삭제
    ============================================================ */

    function bindModalEvents($) {

        $(document).on('click', '#btnAddSubPolicy', function () {

            currentSubPolicies.push({
                sub_account_type: 'partner',
                is_required: 0,
                is_multiple: 0,
                custom_group_code: ''
            });

            renderSubPolicyRows();
            updateAllowSubAccountDisplay();
        });

        $(document).on('click', '.btn-remove-policy', function () {

            const index = Number($(this).data('index'));

            currentSubPolicies.splice(index, 1);

            renderSubPolicyRows();
            updateAllowSubAccountDisplay();
        });

        $(document).on('change', '.policy-type-select', function () {

            const index = Number($(this).data('index'));
            const value = $(this).val();

            currentSubPolicies[index].sub_account_type = value;

            if (value !== 'custom') {
                currentSubPolicies[index].custom_group_code = '';
            }

            renderSubPolicyRows();
            updateAllowSubAccountDisplay();
        });

        $(document).on('change', '.policy-required-check', function () {

            const index = Number($(this).data('index'));
            currentSubPolicies[index].is_required = this.checked ? 1 : 0;
        });

        $(document).on('change', '.policy-multiple-check', function () {

            const index = Number($(this).data('index'));
            currentSubPolicies[index].is_multiple = this.checked ? 1 : 0;
        });

        $(document).on('input', '.policy-custom-group-input', function () {

            const index = Number($(this).data('index'));
            currentSubPolicies[index].custom_group_code = $(this).val().trim();
        });

        $('#account-edit-form').on('submit', function (e) {

            e.preventDefault();

            const formData = new FormData(this);
            formData.set('sub_policies', JSON.stringify(serializeSubPolicies()));
            formData.set('allow_sub_account', currentSubPolicies.length > 0 ? '1' : ($('#modal_allow_sub_account').val() || '0'));

            $.ajax({

                url: '/api/ledger/account/save',

                method: 'POST',

                data: formData,

                processData: false,

                contentType: false

            })
            .done(res => {

                if(res.success){

                    accountModal.hide();
                    accountTable.ajax.reload(null,false);

                    AppCore.notify(
                        'success',
                        '저장 완료'
                    );

                }else{

                    AppCore.notify(
                        'error',
                        res.message || '저장 실패'
                    );

                }

            })
            .fail(err => {

                console.error(err);

                AppCore.notify(
                    'error',
                    '서버 오류'
                );

            });
        });


        $('#btnDeleteAccount').on('click', function () {

            const id = $('#modal_account_id').val();

            if (!id || !confirm('삭제하시겠습니까?')) return;

            $.post('/api/ledger/account/soft-delete', { id })
            .done(res => {

                if(res.success){

                    AppCore.notify(
                        'success',
                        '삭제 완료'
                    );

                    accountTable.ajax.reload(null,false);

                    accountModal.hide();

                }else{

                    AppCore.notify(
                        'error',
                        res.message || '삭제 실패'
                    );

                }

            })
            .fail(err => {

                console.error(err);

                AppCore.notify(
                    'error',
                    '서버 오류가 발생했습니다'
                );

            });

        });


    }


    /* ============================================================
       엑셀 다운로드
    ============================================================ */

    function bindExcelEvents($) {
        document.addEventListener('excel:uploaded', function () {
            if (accountTable) {
                accountTable.ajax.reload(null, false);
            }
        });

    }

    function openAccountExcelModal() {
        const modalEl = document.getElementById('accountExcelModal');

        if (!modalEl) {
            AppCore?.notify?.('error', '계정과목 엑셀 모달을 찾을 수 없습니다.');
            return;
        }

        excelModal = bootstrap.Modal.getOrCreateInstance(modalEl, {
            focus: false
        });

        excelModal.show();
    }


    /* ============================================================
       검색
    ============================================================ */
    function bindSearchEvents($) {

        const MAX_CONDITION = 5;

        /* ===============================
           검색 실행
        =============================== */

        $('#searchConditionsForm').on('submit', function (e) {

            e.preventDefault();

            const filters = collectFilters();

            if (!filters.length) {

                accountTable.ajax.url(API_LIST).load();
                return;

            }

            accountTable.ajax.url(
                '/api/ledger/account/list?filters=' +
                encodeURIComponent(JSON.stringify(filters))
            ).load();

        });


        /* ===============================
           검색조건 삭제
        =============================== */

        $(document).on('click', '.remove-condition', function(){

            const rows = $('.search-condition');

            if(rows.length <= 1){
                alert("최소 1개의 검색조건은 유지해야 합니다.");
                return;
            }

            $(this).closest('.search-condition').remove();

            updateRemoveButtons();

            setTimeout(() => {
                forceTableHeightSync();
            }, 30);

        });


        /* ===============================
           초기화
        =============================== */

        $('#resetButton').on('click', () => {

            $('#searchConditions input[type="text"]').val('');

            $('#searchConditions')
                .find('.search-condition:gt(0)')
                .remove();

            $('input[name="dateStart"]').val('');
            $('input[name="dateEnd"]').val('');

            updateRemoveButtons();

            accountTable.ajax.url(API_LIST).load();

            setTimeout(() => {
                forceTableHeightSync();
            }, 30);

        });


        /* ===============================
           검색조건 추가
        =============================== */

        $('#addSearchCondition').on('click', function(){

            const rows = $('.search-condition');
            const count = rows.length;

            if(count >= MAX_CONDITION){
                alert("검색조건은 최대 5개까지 가능합니다.");
                return;
            }

            /* 첫번째 컬럼 */
            const firstField = rows.first().find('select').val();

            const fields = getTableColumns();

            const baseIndex = fields.findIndex(f => f.value === firstField);

            let nextIndex = baseIndex + count;

            if(nextIndex >= fields.length){
                nextIndex = fields.length - 1;
            }

            const html = `
            <div class="search-condition">

                ${renderSearchSelect(nextIndex)}

                <input type="text"
                       name="searchValue[]"
                       class="form-control search-input"
                       placeholder="검색어 입력">

                <button type="button" class="btn btn-danger remove-condition">-</button>

            </div>
            `;

            $('#searchConditions .search-condition:last').after(html);

            updateRemoveButtons();

            setTimeout(() => {
                forceTableHeightSync();
            }, 30);

        });


        /* ===============================
           삭제버튼 상태관리
        =============================== */

        function updateRemoveButtons(){

            const rows = $('.search-condition');

            rows.each(function(index){

                const btn = $(this).find('.remove-condition');

                if(index === 0){
                    btn.hide();   // 첫줄은 항상 삭제 불가
                } else {
                    btn.show();
                }

            });

        }

    }


    /* ============================================================
       검색 필터
    ============================================================ */

    function collectFilters() {

        const filters = [];

        $('.search-condition').each(function () {

            const field = $(this).find('select').val();

            const value = $(this).find('input').val();

            if (field && value) {

                filters.push({
                    field,
                    value
                });

            }

        });


        const dateType = $('#dateType').val();

        const start = $('input[name="dateStart"]').val();

        const end = $('input[name="dateEnd"]').val();


        if (dateType && start && end) {

            filters.push({
                field: dateType,
                value: { start, end }
            });

        }

        return filters;

    }


    /* ============================================================
       UTIL
    ============================================================ */

    function fillModal(data){

        currentSubPolicies = Array.isArray(data.sub_policies)
            ? data.sub_policies.map(policy => ({
                sub_account_type: policy.sub_account_type ?? 'partner',
                is_required: Number(policy.is_required ?? 0),
                is_multiple: Number(policy.is_multiple ?? 0),
                custom_group_code: policy.custom_group_code ?? ''
            }))
            : [];

        Object.keys(data).forEach(key => {

            if(key === 'id'){
                $('#modal_account_id').val(data.id);
                return;
            }

            const el = document.getElementById('modal_' + key);

            if(el){
                el.value = data[key] ?? '';
            }

        });

        $('#modal_allow_sub_account').val(
            String(data.allow_sub_account_computed ?? data.allow_sub_account ?? 0)
        );

        renderSubPolicyRows();
        updateAllowSubAccountDisplay();

    }

    function serializeSubPolicies() {

        return currentSubPolicies
            .map(policy => ({
                sub_account_type: String(policy.sub_account_type || '').trim(),
                is_required: Number(policy.is_required) ? 1 : 0,
                is_multiple: Number(policy.is_multiple) ? 1 : 0,
                custom_group_code: String(policy.custom_group_code || '').trim()
            }))
            .filter(policy => policy.sub_account_type !== '');
    }

    function renderSubPolicyRows() {

        const tbody = document.getElementById('sub-policy-tbody');

        if(!tbody) return;

        if(!currentSubPolicies.length){

            tbody.innerHTML = `
                <tr class="sub-policy-empty">
                    <td colspan="5" class="text-center text-muted">등록된 보조정책이 없습니다.</td>
                </tr>
            `;

            return;
        }

        tbody.innerHTML = currentSubPolicies.map((policy, index) => {

            const isCustom = policy.sub_account_type === 'custom';

            return `
                <tr>
                    <td>
                        <select class="form-select form-select-sm policy-type-select" data-index="${index}">
                            <option value="partner" ${policy.sub_account_type === 'partner' ? 'selected' : ''}>partner</option>
                            <option value="project" ${policy.sub_account_type === 'project' ? 'selected' : ''}>project</option>
                            <option value="custom" ${policy.sub_account_type === 'custom' ? 'selected' : ''}>custom</option>
                        </select>
                    </td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input policy-required-check" data-index="${index}" ${Number(policy.is_required) ? 'checked' : ''}>
                    </td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input policy-multiple-check" data-index="${index}" ${Number(policy.is_multiple) ? 'checked' : ''}>
                    </td>
                    <td>
                        <input type="text"
                               class="form-control form-control-sm policy-custom-group-input"
                               data-index="${index}"
                               value="${escapeHtml(policy.custom_group_code || '')}"
                               placeholder="custom 타입에서만 사용"
                               ${isCustom ? '' : 'disabled'}>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-policy" data-index="${index}">삭제</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function updateAllowSubAccountDisplay() {

        const hasPolicy = currentSubPolicies.length > 0;
        const value = hasPolicy ? '1' : ($('#modal_allow_sub_account').val() || '0');
        const label = hasPolicy ? '정책 사용' : (value === '1' ? '사용' : '미사용');

        $('#modal_allow_sub_account').val(value);
        $('#modal_allow_sub_account_label').val(label);
    }

    function escapeHtml(value) {

        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }




    function setPeriod(type){

        const today = new Date();
        let start = new Date(today);
        let end = new Date(today);

        switch(type){

            case 'today':
                break;

            case 'yesterday':
                start.setDate(today.getDate() - 1);
                end = new Date(start);
                break;

            case '3days':
                start.setDate(today.getDate() - 3);
                break;

            case '7days':
                start.setDate(today.getDate() - 7);
                break;

            case '15days':
                start.setDate(today.getDate() - 15);
                break;

            case '1month':
                start.setMonth(today.getMonth() - 1);
                break;

            case '3months':
                start.setMonth(today.getMonth() - 3);
                break;

            case '6months':
                start.setMonth(today.getMonth() - 6);
                break;

        }

        $('input[name="dateStart"]').val(formatDate(start));
        $('input[name="dateEnd"]').val(formatDate(end));

        $('#searchConditionsForm').submit();

    }



    /* ==============================
    Tooltip Control
    ============================== */
    function setupTooltip(triggerId, tooltipId){

        const trigger = document.getElementById(triggerId);
        const tooltip = document.getElementById(tooltipId);

        if(!trigger || !tooltip) return;

        trigger.addEventListener("click", function(e){

            e.stopPropagation();

            const isOpen = tooltip.style.display === "block";

            document.querySelectorAll(".tooltip-container").forEach(t=>{
                t.style.display="none";
            });

            tooltip.style.display = isOpen ? "none" : "block";

        });

        tooltip.addEventListener("click", function(e){
            e.stopPropagation();
        });

        document.addEventListener("click", function(){
            document.querySelectorAll(".tooltip-container").forEach(t=>{
                t.style.display="none";
            });
        });

        document.addEventListener("keydown", function(e){
            if(e.key === "Escape"){
                document.querySelectorAll(".tooltip-container").forEach(t=>{
                    t.style.display="none";
                });
            }
        });

    }

    function getTableColumns(){

        return Object.entries(ACCOUNT_COLUMN_MAP)
            .filter(([, config]) => String(config.label || '').trim() !== '')
            .map(([field, config]) => ({
                value: field,
                label: config.label
            }));

    }

    function renderSearchSelect(selectedIndex = 0){

        if(!accountTable) return '';

        const fields = getTableColumns();

        if(!fields.length) return '';

        let html = `<select name="searchField[]" class="form-select form-select-sm search-field">`;

        fields.forEach((f,i)=>{

            const sel = (i === selectedIndex) ? "selected" : "";

            html += `<option value="${f.value}" ${sel}>${f.label}</option>`;

        });

        html += `</select>`;

        return html;
    }


    function buildAccountColumns(){

        const columns = [];

        /* 드래그 컬럼 */
        columns.push({
            data:null,
            title: '<i class="bi bi-arrows-move"></i>',
            width:"40px",
            className:"reorder-handle no-colvis text-center",
            orderable:false,
            defaultContent:'<i class="bi bi-list"></i>',
        });

        Object.entries(ACCOUNT_COLUMN_MAP).forEach(([field,config]) => {

            columns.push({

                data: field,
                title: config.label,
                visible: config.visible ?? true,
                defaultContent: "",
                render: function(data,type,row){

                    if(data === null || data === undefined) return "";

                    /* 코드 강조만 유지 */
                    if(field === 'account_code'){
                        return `<strong>${data}</strong>`;
                    }

                    if(field === 'normal_balance'){
                        if(data === 'debit'){
                            return '<span class="text-primary-soft">차변</span>';
                        }
                        return '<span class="text-danger-soft">대변</span>';
                    }

                    if(field === 'level'){
                        return `<span class="text-muted-soft">Lv.${data}</span>`;
                    }

                    if(field === 'account_name'){

                        const level = Number(row.level || 0);
                        const indent = '&nbsp;'.repeat(level * 4);

                        return `${indent}<a href="#" class="link-account" data-id="${row.id}">${data}</a>`;
                    }

                    /* =========================
                       전표입력
                    ========================= */
                    if(field === 'is_posting'){
                        return data == 1
                            ? '<span class="text-success-soft">가능</span>'
                            : '<span class="text-muted-soft">불가</span>';
                    }

                    /* =========================
                       사용여부
                    ========================= */
                    if(field === 'is_active'){
                        return data == 1
                            ? '<span class="text-primary-soft">사용</span>'
                            : '<span class="text-muted-soft">미사용</span>';
                    }

                    /* =========================
                       보조계정 상태
                    ========================= */
                    if(field === 'sub_account_status'){

                        if(data === '사용중'){
                            return `
                                <span class="text-success-soft">사용중</span>
                                <span class="sub-btn edit btn-sub-edit" data-id="${row.id}">
                                    수정
                                </span>
                            `;
                        }

                        if(data === '가능' || data === '미사용'){
                            return `
                                <span class="text-muted-soft">미사용</span>
                                <span class="sub-btn add btn-sub-add" data-id="${row.id}">
                                    추가
                                </span>
                            `;
                        }

                        return `<span class="text-muted-soft">${data}</span>`;
                    }

                    if(field === 'allow_sub_account'){
                        return data == 1
                            ? '<span class="text-success-soft">사용</span>'
                            : '<span class="text-muted-soft">미사용</span>';
                    }

                    /* =========================
                       계정구분
                    ========================= */
                    if(field === 'account_group'){

                        const map = {
                            자산: 'text-primary-soft',
                            부채: 'text-danger-soft',
                            자본: 'text-warning-soft',
                            수익: 'text-success-soft',
                            비용: 'text-muted-soft'
                        };

                        const cls = map[data] || 'text-dark';

                        return `<span class="${cls}">${data}</span>`;
                    }

                    /* =========================
                       기본 텍스트
                    ========================= */
                    return String(data);
                }

            });

        });

        return columns;
    }



    async function loadaccountTrash() {

        const tbody = document.querySelector('#account-trash-table tbody');
        tbody.innerHTML = '';

        try {

            const res = await fetch('/api/ledger/account/trash');
            const json = await res.json();

            if (!json.success || !json.data.length) {

                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center">
                            삭제된 계정이 없습니다
                        </td>
                    </tr>
                `;
                return;
            }

            json.data.forEach(row => {

                const tr = document.createElement('tr');

                tr.dataset.id = row.id;
                tr.dataset.accountCode = row.account_code;
                tr.dataset.sortCode = row.account_code;
                tr.dataset.name = row.account_name;
                tr.dataset.deleted = row.deleted_at;
                tr.dataset.user = row.deleted_by;

                /* 🔥 계정구분 색상 */
                const groupClass = {
                    '자산': 'text-primary-soft',
                    '부채': 'text-danger-soft',
                    '자본': 'text-warning-soft',
                    '수익': 'text-success-soft',
                    '비용': 'text-muted-soft'
                }[row.account_group] || '';

                tr.innerHTML = `
                    <td>
                        <input type="checkbox"
                               class="trash-check"
                               value="${row.id}">
                    </td>

                    <td>${row.account_code ?? ''}</td>

                    <td>${row.account_name ?? ''}</td>

                    <td>
                        <span class="${groupClass}">
                            ${row.account_group ?? ''}
                        </span>
                    </td>

                    <td>${row.deleted_at ?? ''}</td>

                    <td>${row.deleted_by_name ?? 'SYSTEM'}</td>

                    <td>
                        <button class="btn btn-success btn-sm btn-restore"
                                data-id="${row.id}">
                            복원
                        </button>

                        <button class="btn btn-danger btn-sm btn-purge"
                                data-id="${row.id}">
                            영구삭제
                        </button>
                    </td>
                `;

                tbody.appendChild(tr);
            });

        } catch (err) {
            console.error(err);
        }
    }


    async function loadSubAccounts(accountId){

        if(subAccountAbort){
            subAccountAbort.abort();
        }

        subAccountAbort = new AbortController();

        const tbody = document.querySelector('#subaccount-table tbody');
        if(!tbody) return;

        /* 🔥 로딩 표시 */
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center text-muted">
                    <span class="spinner-border spinner-border-sm"></span>
                    불러오는 중...
                </td>
            </tr>
        `;

        try{

            const res = await fetch(
                '/api/ledger/sub-account/list?account_id=' + accountId
            );

            const json = await res.json();
            console.log('[ledger-account] sub-account response', json);
            console.log('[ledger-account] sub-account data count', Array.isArray(json?.data) ? json.data.length : 0);

            const tbody = document.querySelector('#subaccount-table tbody');

            if(!tbody) return;

            tbody.innerHTML = '';

            if(!json.success || !json.data.length){

                tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted">
                        등록된 보조계정이 없습니다
                    </td>
                </tr>
                `;

                return;
            }

            json.data.forEach(row => {

                /* =========================
                   🔹 메인 ROW (압축)
                ========================= */
                const tr = document.createElement('tr');

                tr.innerHTML = `
                <td>${row.sub_code ?? ''}</td>

                <td>
                    <input
                        type="text"
                        class="form-control form-control-sm sub-name-input"
                        data-id="${row.id}"
                        value="${row.sub_name ?? ''}">
                </td>

                <td colspan="2">
                    <button
                        class="btn btn-sm btn-outline-secondary btnToggleDetail"
                        data-id="${row.id}">
                        상세
                    </button>

                    <button
                        class="btn btn-sm btn-danger btnDeleteSubAccount"
                        data-id="${row.id}">
                        삭제
                    </button>
                </td>
                `;

                /* =========================
                   🔹 상세 ROW (숨김)
                ========================= */
                const detail = document.createElement('tr');
                detail.classList.add('sub-detail-row');
                detail.style.display = 'none';

                detail.innerHTML = `
                <td colspan="5">

                    <input
                        type="text"
                        class="form-control form-control-sm sub-note-input mb-1"
                        data-id="${row.id}"
                        value="${row.note ?? ''}"
                        placeholder="비고">

                    <input
                        type="text"
                        class="form-control form-control-sm sub-memo-input"
                        data-id="${row.id}"
                        value="${row.memo ?? ''}"
                        placeholder="메모">

                </td>
                `;

                tbody.appendChild(tr);
                tbody.appendChild(detail);

            });

            setTimeout(adjustAllDataTables, 100);

        }catch(err){
            console.error(err);
        }
    }










})();
