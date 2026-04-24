// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/base/project.js'
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { formatDateDisplay, formatAmount, unformatAmount } from '/public/assets/js/common/format.js';
import { createDataTable, updateTableHeight, forceTableHeightSync, bindTableHighlight } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    console.log('[base-project.js] loaded');

    /* =========================
       API / 상수
    ========================= */
    const API = {
        LIST: "/api/settings/base-info/project/list",
        SAVE: "/api/settings/base-info/project/save",
        DELETE: "/api/settings/base-info/project/delete",
        DETAIL: "/api/settings/base-info/project/detail",

        TRASH: "/api/settings/base-info/project/trash",
        RESTORE: "/api/settings/base-info/project/restore",
        PURGE: "/api/settings/base-info/project/purge",
        PURGE_ALL: "/api/settings/base-info/project/purge-all",

        REORDER: "/api/settings/base-info/project/reorder",

        EXCEL_UPLOAD: '/api/settings/base-info/project/excel-upload',
        EXCEL_TEMPLATE: '/api/settings/base-info/project/template',
        EXCEL_DOWNLOAD: '/api/settings/base-info/project/download',

        EMPLOYEE_SEARCH: "/api/settings/organization/employee/search-picker",
        CLIENT_SEARCH: "/api/settings/base-info/client/search-picker"
    };
    /* =========================
       프로젝트 컬럼 한글 매핑
    ========================= */
    const PROJECT_COLUMN_MAP = {
        sort_no:                       { label: "순번", visible: true },
        project_name:               { label: "프로젝트명", visible: true },
        construction_name:          { label: "공사명", visible: true },
        linked_client_name:         { label: "거래처", visible: true },
        client_name:                { label: "발주자명", visible: true },
        employee_name:              { label: "담당직원", visible: true },
        site_agent:                 { label: "현장대리인", visible: false },
        contract_type:              { label: "계약형태", visible: false },
        director:                   { label: "소장", visible: false },
        manager:                    { label: "실장", visible: false },
        business_type:              { label: "업종", visible: false },
        housing_type:               { label: "주력분야", visible: false },
        site_region_city:           { label: "시도", visible: false },
        site_region_district:       { label: "시군구", visible: false },
        site_region_address:        { label: "주소", visible: false },
        site_region_address_detail: { label: "상세주소", visible: false },
        work_type:                  { label: "공종", visible: true },
        work_subtype:               { label: "공종 세분류", visible: false },
        work_detail_type:           { label: "세부 공사종류", visible: false },
        contract_work_type:         { label: "도급종류", visible: false },
        bid_type:                   { label: "입찰형태", visible: false },
        client_type:                { label: "발주자분류", visible: false },
        permit_agency:              { label: "인허가기관", visible: false },
        permit_date:                { label: "인허가일자", visible: false },
        contract_date:              { label: "계약일자", visible: true },
        start_date:                 { label: "착공일자", visible: true },
        completion_date:            { label: "준공일자", visible: true },
        bid_notice_date:            { label: "입찰공고일", visible: false },
        initial_contract_amount:    { label: "최초계약금액", visible: true },
        authorized_company_seal:    { label: "사용인감명", visible: false },
        is_active:                  { label: "진행상태", visible: true },
        note:                       { label: "비고", visible: true },
        memo:                       { label: "메모", visible: false },
        created_at:                 { label: "등록일시", visible: false },
        created_by_name:            { label: "등록자", visible: false },
        updated_at:                 { label: "수정일시", visible: false },
        updated_by_name:            { label: "수정자", visible: false },
        deleted_at:                 { label: "삭제일시", visible: false },
        deleted_by_name:            { label: "삭제자", visible: false }
    };

    function getProjectColumnAlignClass(field) {
        if ([
            'sort_no',
            'employee_name',
            'work_type',
            'permit_date',
            'contract_date',
            'start_date',
            'completion_date',
            'bid_notice_date',
            'is_active'
        ].includes(field)) {
            return 'text-center';
        }

        if (field === 'initial_contract_amount') {
            return 'text-end';
        }

        return '';
    }

    const DATE_OPTIONS = [
        { value: 'start_date', label: '착공일자' },
        { value: 'completion_date', label: '준공일자' },
        { value: 'contract_date', label: '계약일자' },
        { value: 'permit_date', label: '인허가일자' },
        { value: 'bid_notice_date', label: '입찰공고일' },
        { value: 'updated_at', label: '수정일자' }
    ];

    let projectTable = null;
    let projectModal = null;
    let excelModal = null;
    let todayPicker = null;
    let globalBound = false;
    let employeeSelect2Inited = false;
    let clientSelect2Inited = false;

    /* ============================================================
       DOM READY
    ============================================================ */
    document.addEventListener('DOMContentLoaded', async () => {
        if (!window.jQuery) {
            console.error('jQuery not loaded');
            return;
        }

        const $ = window.jQuery;
        initProjectPage($);
    });

    /* ============================================================
       PAGE INIT
    ============================================================ */
    function initProjectPage($){
        initModal();
        initAdminDatePicker();

        initExcelDataset(); // 🔥 추가 (핵심)

        initDataTable($);
        initExternal();

        bindRowReorder(projectTable, { api: API.REORDER });
        bindTableEvents($);

        bindModalEvents($);
        bindAdminDateInputs();

        bindTableLayoutEvents(projectTable, '#project-table');

        bindUIEvents();

        bindExcelEvents();
        bindTrashEvents();

        bindGlobalEvents();
    }
    function initExcelDataset() {
        const form = document.getElementById('project-excel-upload-form');
        if (!form) return;

        form.dataset.templateUrl = API.EXCEL_TEMPLATE;
        form.dataset.downloadUrl = API.EXCEL_DOWNLOAD;
        form.dataset.uploadUrl   = API.EXCEL_UPLOAD;
    }
    function initModal(){
        const modalEl = document.getElementById('projectModal');
        if (!modalEl) return;

        projectModal = new bootstrap.Modal(modalEl, {
            focus: false
        });

        const excelModalEl = document.getElementById('projectExcelUploadModal');
        if (excelModalEl) {
            excelModal = new bootstrap.Modal(excelModalEl);
        }

        modalEl.addEventListener('hidden.bs.modal', () => {
            const form = document.getElementById('project-edit-form');
            if (form) form.reset();

            resetProjectModalSelect2();

            const amountInput = document.getElementById('modal_initial_contract_amount');
            if (amountInput) {
                amountInput.value = '';
            }
        });

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

                picker.open({ anchor: input });
            });
        });

        modalEl.addEventListener('shown.bs.modal', () => {
            bindAdminDateInputs();
            initProjectModalSelect2();
        });
    }

    function bindTableLayoutEvents(table, tableSelector){
        if(!table) return;

        window.addEventListener('resize', () => {
            updateTableHeight(table, tableSelector);
        });

        document.addEventListener('sidebar:toggled', () => {
            updateTableHeight(table, tableSelector);

            setTimeout(() => {
                forceTableHeightSync(table, tableSelector);
            }, 340);
        });
    }

    function bindUIEvents(){
        // 프로젝트 전용 일반 UI 필요 시 여기에 추가
    }

    function initExternal(){
        if(window.KakaoAddress){
            window.KakaoAddress.bind();
        }
    }

    function bindExcelEvents(){
        document.addEventListener("excel:uploaded", () => {
            if (projectTable) {
                projectTable.ajax.reload(null, false);
            }
        });
    }

    function bindGlobalEvents(){
        if(globalBound) return;
        globalBound = true;

        document.addEventListener('input', onGlobalInput);
    }

    function onGlobalInput(e){
        const type = e.target.dataset.format;
        if(!type) return;

        if(type === 'amount'){
            const raw = unformatAmount(e.target.value);
            e.target.value = formatAmount(raw);
        }
    }



    function bindTrashEvents(){
        document.addEventListener('trash:detail-render', function(e){
            const { data, modal } = e.detail;

            if (modal.dataset.type !== 'project') return;

            const detailBox = modal.querySelector('.trash-detail');
            if(!detailBox) return;

            let html = `
                <div class="p-3">
                    <h6 class="mb-3">프로젝트 상세</h6>
            `;

            Object.entries(PROJECT_COLUMN_MAP).forEach(([key, config]) => {
                let value = data[key];

                if (value === null || value === undefined || value === '') return;

                if ([
                    'permit_date',
                    'contract_date',
                    'start_date',
                    'completion_date',
                    'bid_notice_date'
                ].includes(key)) {
                    value = formatDateDisplay(value);
                }

                if (key === 'initial_contract_amount') {
                    value = formatAmount(value);
                }

                if (key === 'is_active') {
                    value = Number(value) === 1 ? '진행중' : '완료/종료';
                }

                html += `<div><b>${config.label}:</b> ${value}</div>`;
            });

            html += `</div>`;
            detailBox.innerHTML = html;
        });

        //휴지통 모달에 전달
        window.TrashColumns = window.TrashColumns || {};

        window.TrashColumns.project = function(row) {
            return `
                <td>${row.sort_no ?? ''}</td>
                <td>${row.project_name ?? ''}</td>
                <td>${row.deleted_at ?? ''}</td>
                <td>${row.deleted_by_name ?? ''}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${row.id}">복원</button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${row.id}">영구삭제</button>
                </td>
            `;
        };

        //테이블갱신
        document.addEventListener('trash:changed', (e) => {

            const { type } = e.detail || {};

            if (type === 'project') {
                if (window.projectTable) {
                    window.projectTable.ajax.reload(null, false);
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
            input.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();

                const picker = initAdminDatePicker();
                if(!picker) return;

                picker.__target = input;

                if (typeof picker.clearDate === 'function') {
                    picker.clearDate();
                }

                const v = input.value;
                if(v){
                    const d = new Date(v);
                    if(!isNaN(d)){
                        picker.setDate(d);
                    }
                }

                picker.open({ anchor: input });
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

        if(type === 'start' && start.value > end.value){
            end.value = start.value;
        }

        if(type === 'end' && end.value < start.value){
            start.value = end.value;
        }
    }

    function initDataTable($) {
        const columns = buildProjectColumns();

        projectTable = createDataTable({
            tableSelector: '#project-table',
            api: API.LIST,
            columns: columns,
            defaultOrder: [[1, "asc"]],
            pageLength: 100,
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
                        const trashModalEl = document.getElementById('projectTrashModal');
                        if (!trashModalEl) return;

                        /* 🔥 핵심: JS에서 API 세팅 */
                        trashModalEl.dataset.listUrl      = API.TRASH;
                        trashModalEl.dataset.restoreUrl   = API.RESTORE;
                        trashModalEl.dataset.deleteUrl    = API.PURGE;
                        trashModalEl.dataset.deleteAllUrl = API.PURGE_ALL;

                        const modal = new bootstrap.Modal(trashModalEl);
                        modal.show();
                    }
                },
                {
                    text: "새 프로젝트",
                    className: "btn btn-warning btn-sm",
                    action: function () {
                        const form = document.getElementById('project-edit-form');
                        if (form) form.reset();

                        resetProjectModalSelect2();

                        $('#modal_project_id').val('');
                        $('#modal_sort_no').val('');
                        $('#btnDeleteProject').hide();

                        window.isNewProject = true;

                        const titleEl = document.getElementById('projectModalLabel');
                        if (titleEl) {
                            titleEl.textContent = '프로젝트 신규 등록';
                        }

                        const amountInput = document.getElementById('modal_initial_contract_amount');
                        if(amountInput){
                            amountInput.value = '';
                        }

                        if (projectModal) {
                            projectModal.show();
                        }
                    }
                }
            ]
        });

        window.projectTable = projectTable;

        if (projectTable) {
            console.log('✅ DataTable 생성 완료');

            projectTable.on('init.dt', () => {
                updateProjectCount(projectTable.page.info()?.recordsDisplay ?? 0);
            });

            projectTable.on('draw.dt', () => {
                updateProjectCount(projectTable.page.info()?.recordsDisplay ?? 0);
            });

            SearchForm({
                table: projectTable,
                apiList: API.LIST,
                tableId: 'project',
                defaultSearchField: 'project_name',
                dateOptions: DATE_OPTIONS
            });

            updateTableHeight(projectTable, '#project-table');
            bindTableHighlight('#project-table', projectTable);
        }
    }

    function updateProjectCount(count) {
        const el = document.getElementById('projectCount');
        if (!el) return;
        el.textContent = `총 ${count ?? 0}건`;
    }

    function bindTableEvents($) {
        $(document).on('focus', '#modal_sort_no', function(){
            if(window.isNewProject){
                AppCore.notify(
                    'info',
                    '순번은 저장 시 자동 생성됩니다.'
                );
            }
        });

        $('#project-table tbody').on('dblclick', 'tr', async function () {
            const rowData = projectTable.row(this).data();
            if (!rowData || !rowData.id) return;

            try {
                const res = await fetch(API.DETAIL + '?id=' + encodeURIComponent(rowData.id));
                const json = await res.json();

                if (!json.success || !json.data) {
                    AppCore.notify('error', json.message || '프로젝트 상세 조회 실패');
                    return;
                }

                const data = json.data;

                window.isNewProject = false;
                document.getElementById('projectModalLabel').textContent = '프로젝트 정보 수정';
                $('#btnDeleteProject').show();

                projectModal.show();

                const modalEl = document.getElementById('projectModal');
                const onShown = () => {
                    $('#modal_project_id').val(data.id ?? '');
                    fillModal(data);
                    modalEl.removeEventListener('shown.bs.modal', onShown);
                };

                modalEl.addEventListener('shown.bs.modal', onShown);

            } catch (err) {
                console.error(err);
                AppCore.notify('error', '프로젝트 상세 조회 중 오류');
            }
        });

        $('#project-table tbody').on('click', 'td', function () {
            const cell = projectTable.cell(this);
            const value = cell.data();
            const colIndex = cell.index().column;
            const field = projectTable.column(colIndex).dataSrc();

            if(!field) return;

            const $first = $('.search-condition').first();
            $first.find('select').val(field);
            $first.find('input').val(value);
        });
    }

    function bindModalEvents($) {
        $(document).off('submit', '#project-edit-form');

        $(document).on('submit', '#project-edit-form', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');

            const amountInput = document.getElementById('modal_initial_contract_amount');
            if(amountInput){
                formData.set('initial_contract_amount', unformatAmount(amountInput.value));
            }

            const projectName = String(formData.get('project_name') || '').trim();
            const contractDate = String(formData.get('contract_date') || '').trim();
            const startDate = String(formData.get('start_date') || '').trim();
            const completionDate = String(formData.get('completion_date') || '').trim();
            const amount = String(formData.get('initial_contract_amount') || '').trim();

            if (!projectName) {
                AppCore.notify('warning', '프로젝트명은 필수입니다.');
                return;
            }

            const datePattern = /^\d{4}-\d{2}-\d{2}$/;

            if (contractDate && !datePattern.test(contractDate)) {
                AppCore.notify('warning', '계약일자는 YYYY-MM-DD 형식이어야 합니다.');
                return;
            }

            if (startDate && !datePattern.test(startDate)) {
                AppCore.notify('warning', '착공일자는 YYYY-MM-DD 형식이어야 합니다.');
                return;
            }

            if (completionDate && !datePattern.test(completionDate)) {
                AppCore.notify('warning', '준공일자는 YYYY-MM-DD 형식이어야 합니다.');
                return;
            }

            if (startDate && completionDate && startDate > completionDate) {
                AppCore.notify('warning', '준공일자는 착공일자보다 빠를 수 없습니다.');
                return;
            }

            if (amount && !/^-?\d+$/.test(amount)) {
                AppCore.notify('warning', '최초 계약금액은 숫자만 입력할 수 있습니다.');
                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
            }

            $.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(res => {
                if (res.success) {
                    projectModal.hide();
                    projectTable.ajax.reload(null, false);
                    AppCore.notify('success', '저장 완료');
                } else {
                    AppCore.notify('error', res.message || '저장 실패');
                }
            })
            .fail(err => {
                console.error(err);
                AppCore.notify('error', '서버 오류');
            })
            .always(() => {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
        });

        $('#btnDeleteProject').on('click', function () {
            const id = $('#modal_project_id').val();
            if (!id || !confirm('삭제하시겠습니까?')) return;

            $.post(API.DELETE, { id })
                .done(res => {
                    if (res.success) {
                        AppCore.notify('success', '삭제 완료');
                        projectTable.ajax.reload(null, false);
                        projectModal.hide();
                    } else {
                        AppCore.notify('error', res.message || '삭제 실패');
                    }
                });
        });
    }

    function fillModal(data){
        Object.keys(data).forEach(key => {
            if(key === 'id') return;
            if(key === 'employee_id') return;
            if(key === 'client_id') return;

            const el = document.getElementById('modal_' + key);
            if(!el) return;

            let value = data[key] ?? '';

            if ([
                'permit_date',
                'contract_date',
                'start_date',
                'completion_date',
                'bid_notice_date'
            ].includes(key)) {
                value = formatDateDisplay(value);
            }

            if (key === 'initial_contract_amount') {
                value = formatAmount(value);
            }

            el.value = value;
        });

        setTimeout(() => {
            setProjectEmployeeSelect2(data);
            setProjectClientSelect2(data);
        }, 50);
    }

    function buildProjectColumns(){
        const columns = [];

        columns.push({
            data:null,
            title: '<i class="bi bi-arrows-move"></i>',
            className:"col-reorder reorder-handle no-colvis text-center",
            headerClassName:"col-reorder no-colvis text-center",
            orderable:false,
            defaultContent:'<i class="bi bi-list"></i>',
        });

        Object.entries(PROJECT_COLUMN_MAP).forEach(([field, config]) => {
            const alignClassName = getProjectColumnAlignClass(field);
            const columnClassName = alignClassName || '';

            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                className: columnClassName,
                headerClassName: columnClassName,
                defaultContent: "",
                render: function(data){
                    if(data === null || data === undefined) return "";

                    if ([
                        'permit_date',
                        'contract_date',
                        'start_date',
                        'completion_date',
                        'bid_notice_date'
                    ].includes(field)) {
                        return formatDateDisplay(data);
                    }

                    if (field === 'initial_contract_amount') {
                        return formatAmount(data);
                    }

                    if (field === 'is_active') {
                        return Number(data) === 1 ? '진행중' : '완료/종료';
                    }

                    return data;
                }
            });
        });

        return columns;
    }

    function initProjectModalSelect2() {
        initEmployeeSelect2();
        initClientSelect2();
    }

    function initEmployeeSelect2() {
        const el = document.getElementById('modal_employee_id');
        if (!el || employeeSelect2Inited) return;

        const $el = window.jQuery(el);

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        AdminPicker.select2Ajax(el, {
            url: API.EMPLOYEE_SEARCH,
            placeholder: '담당직원 검색',
            minimumInputLength: 0,
            dropdownParent: window.jQuery('#projectModal'),
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20
                };
            },
            processResults(json) {
                const rows = json?.results ?? json?.data ?? [];

                return {
                    results: rows.map(row => ({
                        id: String(row.id ?? ''),
                        text: row.text ?? row.employee_name ?? row.username ?? row.id,
                        raw: row
                    })).filter(item => item.id !== '')
                };
            }
        });

        employeeSelect2Inited = true;
    }

    function initClientSelect2() {
        const el = document.getElementById('modal_client_id');
        if (!el || clientSelect2Inited) return;

        const $el = window.jQuery(el);

        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }

        AdminPicker.select2Ajax(el, {
            url: API.CLIENT_SEARCH,
            placeholder: '거래처 검색',
            minimumInputLength: 0,
            dropdownParent: window.jQuery('#projectModal'),
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20
                };
            },
            processResults(json) {
                const rows = json?.results ?? json?.data ?? [];

                return {
                    results: rows.map(row => ({
                        id: String(row.id ?? ''),
                        text: row.text ?? row.client_name ?? '',
                        raw: row
                    })).filter(item => item.id !== '')
                };
            }
        });

        $el.off('select2:select.projectClient');
        $el.on('select2:select.projectClient', function (e) {
            const item = e.params?.data;
            if (!item) return;

            window.jQuery(this).val(String(item.id)).trigger('change');
        });

        clientSelect2Inited = true;
    }

    function resetProjectModalSelect2() {
        const $employee = window.jQuery('#modal_employee_id');
        const $client   = window.jQuery('#modal_client_id');

        if ($employee.hasClass('select2-hidden-accessible')) {
            $employee.off('.projectEmployee');
            $employee.select2('destroy');
        }

        if ($client.hasClass('select2-hidden-accessible')) {
            $client.off('.projectClient');
            $client.select2('destroy');
        }

        const employeeEl = document.getElementById('modal_employee_id');
        const clientEl   = document.getElementById('modal_client_id');

        if (employeeEl) {
            employeeEl.innerHTML = '<option value=""></option>';
        }

        if (clientEl) {
            clientEl.innerHTML = '<option value=""></option>';
        }

        employeeSelect2Inited = false;
        clientSelect2Inited   = false;
    }

    function setProjectEmployeeSelect2(data) {
        const employeeId = String(data.employee_id ?? '').trim();
        const $el = $('#modal_employee_id');

        if (!employeeId) {
            $el.val(null).trigger('change');
            return;
        }

        const employeeText =
        data.employee_name ?? employeeId;

        $el.find(`option[value="${employeeId}"]`).remove();
        $el.append(new Option(employeeText, employeeId, true, true));
        $el.val(employeeId).trigger('change');
    }

    function setProjectClientSelect2(data) {
        const clientId = String(data.client_id ?? '').trim();
        const $el = $('#modal_client_id');

        if (!clientId) {
            $el.val(null).trigger('change');
            return;
        }

        const clientText =
            data.linked_client_name ??
            data.client_name ??
            clientId;

        $el.find(`option[value="${clientId}"]`).remove();
        $el.append(new Option(clientText, clientId, true, true));
        $el.val(clientId).trigger('change');
    }
})();
