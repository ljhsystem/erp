// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/employees.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    formatMobile,
    formatPhone,
    formatCorpNumber,
    onlyNumber
} from '/public/assets/js/common/format.js';
import {
    createDataTable,
    updateTableHeight,
    forceTableHeightSync,
    bindTableHighlight
} from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    console.log('[employees.js v2] loaded');

    /* =========================================================
       API
    ========================================================= */
    const API = {
        LIST: '/api/settings/organization/employee/list',
        DETAIL: '/api/settings/organization/employee/detail',
        SEARCH_PICKER: '/api/settings/organization/employee/search-picker',
    
        SAVE: '/api/settings/organization/employee/save',
        UPDATE_STATUS: '/api/settings/organization/employee/update-status',
        PURGE: '/api/settings/organization/employee/purge',
        REORDER: '/api/settings/organization/employee/reorder',
    
        DEPARTMENT_LIST: '/api/settings/organization/department/list',
        POSITION_LIST: '/api/settings/organization/position/list',
        ROLE_LIST: '/api/settings/organization/role/list'
    };

    /* =========================================================
       직원 컬럼 매핑
    ========================================================= */
    const EMPLOYEE_COLUMN_MAP = {
        sort_no:                 { label: '순번', visible: true },
        profile_image:        { label: '사진', visible: true },
        username:             { label: '아이디', visible: true },
        employee_name:        { label: '직원명', visible: true },
        role_name:            { label: '역할', visible: true },

        department_name:      { label: '부서', visible: false },
        position_name:        { label: '직책', visible: false },

        approved:             { label: '승인여부', visible: false },
        approved_at:          { label: '승인일시', visible: false },
        approved_by_name:     { label: '승인자', visible: false },

        email:                { label: '이메일', visible: true },
        phone:                { label: '연락처', visible: true },
        emergency_phone:      { label: '비상연락처', visible: false },

        two_factor_enabled:   { label: '2차인증', visible: true },
        email_notify:         { label: '이메일알림', visible: true },
        sms_notify:           { label: 'SMS알림', visible: false },

        rrn:                  { label: '주민번호', visible: false },
        address:              { label: '주소', visible: false },
        address_detail:       { label: '상세주소', visible: false },

        doc_hire_date:        { label: '서류입사일', visible: false },
        real_hire_date:       { label: '실입사일', visible: false },
        doc_retire_date:      { label: '서류퇴사일', visible: false },
        real_retire_date:     { label: '실퇴사일', visible: false },

        login_fail_count:     { label: '로그인실패횟수', visible: false },
        account_locked_until: { label: '잠금만료일시', visible: false },

        last_login:           { label: '마지막로그인', visible: true },
        last_login_ip:        { label: '로그인IP', visible: false },
        last_login_device:    { label: '로그인디바이스', visible: false },

        password_updated_at:  { label: '비밀번호변경일', visible: false },
        password_updated_by_name: { label: '비밀번호변경자', visible: false },

        note:                 { label: '비고', visible: false },
        memo:                 { label: '메모', visible: false },

        user_created_at:      { label: '생성일시', visible: false },
        user_created_by_name: { label: '생성자', visible: false },
        user_updated_at:      { label: '수정일시', visible: false },
        user_updated_by_name: { label: '수정자', visible: false },

        deleted_at:           { label: '비활성화일시', visible: false },
        deleted_by_name:      { label: '비활성화처리자', visible: false },

        is_active:            { label: '상태', visible: true, noVis: true }
    };

    const DATE_OPTIONS = [
        { value: 'user_created_at', label: '등록일자' },
        { value: 'last_login', label: '마지막 로그인' },
        { value: 'real_hire_date', label: '입사일' },
        { value: 'real_retire_date', label: '퇴사일' },
        { value: 'deleted_at', label: '비활성화일' }
    ];

    let employeeTable = null;
    let employeeEditModal = null;
    let originalImageModal = null;
    let todayPicker = null;
    let rrnVisible = false;
    let globalBound = false;

    /* =========================================================
       DOM READY
    ========================================================= */
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('[employees.js] jQuery not loaded');
            return;
        }

        const $ = window.jQuery;
        initEmployeePage($);
    });

    /* =========================================================
       PAGE INIT
    ========================================================= */
    function initEmployeePage($) {
        initEmployeeModules();
        initModals();
        initAdminDatePicker();
        bindAdminDateInputs();

        initDataTable($);

        bindRowReorder(employeeTable, {
            api: API.REORDER
        });

        bindTableEvents($);
        bindModalBridge($);
        bindModalEvents($);
        bindGlobalButtons($);
        bindInputGuide($);
        bindInputFormat($);
        bindTogglePassword($);
        bindToggleRrn($);
        bindFilePreviewAndDeleteEvents($);
        bindTableLayoutEvents(employeeTable, '#employee-table');
        bindGlobalEvents();
    }

    /* =========================================================
       외부 모듈
    ========================================================= */
    function initEmployeeModules() {
        try {
            window.EmployeeUtils?.hideAlertMessages?.();
            window.EmployeePreview?.initCreate?.();
            window.EmployeePreview?.initEdit?.();
            window.KakaoAddress?.bind?.();
            window.EmployeeManagerSelect?.initCreate?.();
            window.EmployeeManagerSelect?.initEdit?.();
        } catch (e) {
            console.error('[employees.js] initEmployeeModules failed:', e);
        }
    }

    /* =========================================================
       모달
    ========================================================= */
    function initModals() {
        const editEl = document.getElementById('employeeEditModal');
        const imageEl = document.getElementById('originalImageModal');

        if (editEl) {
            employeeEditModal = new bootstrap.Modal(editEl, { focus: false });

            editEl.addEventListener('shown.bs.modal', () => {
                bindAdminDateInputs();
            });
        }

        if (imageEl) {
            originalImageModal = new bootstrap.Modal(imageEl, { focus: false });
        }
    }

    /* =========================================================
       날짜피커
    ========================================================= */
    function initAdminDatePicker() {
        if (todayPicker) return todayPicker;

        const container = document.getElementById('today-picker');
        if (!container) return null;

        todayPicker = AdminPicker.create({
            type: 'today',
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

    function bindAdminDateInputs() {
        document.querySelectorAll('.admin-date').forEach(input => {
            input.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();

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
    }

    function formatDate(date) {
        if (!date) return '';

        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');

        return `${y}-${m}-${d}`;
    }

    function normalizeStartEnd(type) {
        const start = document.querySelector('input[name="dateStart"]');
        const end = document.querySelector('input[name="dateEnd"]');

        if (!start || !end) return;
        if (!start.value || !end.value) return;

        if (type === 'start' && start.value > end.value) {
            end.value = start.value;
        }

        if (type === 'end' && end.value < start.value) {
            start.value = end.value;
        }
    }

    /* =========================================================
       데이터테이블
    ========================================================= */
    function initDataTable($) {
        const columns = buildEmployeeColumns();

        employeeTable = createDataTable({
            tableSelector: '#employee-table',
            api: API.LIST,
            columns,
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            buttons: [
                {
                    text: '새 직원 추가',
                    className: 'btn btn-primary btn-sm',
                    action: function () {
                        $(document).trigger('employee:create-open');
                    }
                }
            ]
        });

        window.EmployeeTable = employeeTable;

        if (employeeTable) {
            SearchForm({
                table: employeeTable,
                apiList: API.LIST,
                tableId: 'employee',
                defaultSearchField: 'employee_name',
                dateOptions: DATE_OPTIONS
            });

            updateTableHeight(employeeTable, '#employee-table');
            bindTableHighlight('#employee-table', employeeTable);

            employeeTable.on('draw', function () {
                updateEmployeeCountFromTable();
            });

            updateEmployeeCountFromTable();
        }
    }

    function buildEmployeeColumns() {
        const columns = [];

        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'reorder-handle no-colvis text-center',
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>'
        });

        Object.entries(EMPLOYEE_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                className: config.noVis ? 'noVis' : '',
                defaultContent: '',
                orderable: field !== 'profile_image',
                searchable: field !== 'profile_image',
                render: function (data, type, row) {
                    if (data == null) return '';

                    if (type !== 'display') return data;

                    if (field === 'profile_image') {
                        const src = resolveFileSrc(data, '/public/assets/img/default-avatar.png');
                        return `
                            <img src="${src}"
                                 class="employee-img-preview"
                                 style="width:40px;height:40px;border-radius:6px;object-fit:cover;cursor:pointer;">
                        `;
                    }

                    if (field === 'approved') {
                        return String(data) === '1' ? '승인' : '미승인';
                    }

                    if (
                        field === 'two_factor_enabled' ||
                        field === 'email_notify' ||
                        field === 'sms_notify'
                    ) {
                        return String(data) === '1' ? 'ON' : 'OFF';
                    }

                    if (field === 'is_active') {
                        return String(data) === '1'
                            ? '<span class="badge bg-success">활성</span>'
                            : '<span class="badge bg-secondary">비활성</span>';
                    }

                    if (field === 'phone') {
                        return formatMobile(data);
                    }

                    if (field === 'emergency_phone') {
                        return formatPhone(data);
                    }

                    if (field === 'rrn') {
                        return maskRrn(data);
                    }

                    return data;
                }
            });
        });

        return columns;
    }

    function updateEmployeeCountFromTable() {
        if (!employeeTable || !employeeTable.page) return;

        const info = employeeTable.page.info();
        updateEmployeeCount(info?.recordsDisplay ?? 0);
    }

    function updateEmployeeCount(count) {
        const el = document.getElementById('employeeCount');
        if (el) {
            el.textContent = `총 ${count ?? 0}명`;
        }
    }

    /* =========================================================
       테이블 이벤트
    ========================================================= */
    function bindTableEvents($) {
        $('#employee-table tbody')
            .off('click.employeeImgPreview', '.employee-img-preview')
            .on('click.employeeImgPreview', '.employee-img-preview', function (e) {
                e.stopPropagation();

                const src = $(this).attr('src');
                if (!src) {
                    AppCore.notify('warning', '이미지가 없습니다.');
                    return;
                }

                $('#original-image-view').attr('src', src);
                originalImageModal?.show();
            });

        $('#employee-table tbody')
            .off('dblclick.employeeEdit', 'tr')
            .on('dblclick.employeeEdit', 'tr', function () {
                const data = employeeTable.row(this).data();
                if (!data) return;

                $(document).trigger('employee:edit-open', [data]);
            });

        $('#employee-table tbody')
            .off('click.employeeCellSearch', 'td')
            .on('click.employeeCellSearch', 'td', function () {
                const cell = employeeTable.cell(this);
                const idx = cell.index();
                if (!idx) return;

                const value = cell.data();
                const field = employeeTable.column(idx.column).dataSrc();
                if (!field || field === 'profile_image') return;

                const $first = $('.search-condition').first();
                $first.find('select').val(field);
                $first.find('input').val(
                    typeof value === 'string' ? value.replace(/<[^>]*>/g, '') : value
                );
            });
    }

    /* =========================================================
       공통 레이아웃
    ========================================================= */
    function bindTableLayoutEvents(table, tableSelector) {
        if (!table) return;

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

    /* =========================================================
       전역 버튼
    ========================================================= */
    function bindGlobalButtons($) {
        $(document)
            .off('click.employeeCreateBtn', '#create-employee-btn')
            .on('click.employeeCreateBtn', '#create-employee-btn', function () {
                $(document).trigger('employee:create-open');
            });

        $(document)
            .off('click.employeeSoftDelete', '#edit_soft_delete_btn')
            .on('click.employeeSoftDelete', '#edit_soft_delete_btn', async function () {
                const id = $('#edit_employee_id').val();
                const mode = $(this).attr('data-mode');

                if (!id) {
                    AppCore.notify('warning', '대상이 없습니다.');
                    return;
                }

                if (mode === 'deactivate') {
                    if (!confirm('계정을 비활성화 하시겠습니까?')) return;
                } else if (mode === 'activate') {
                    if (!confirm('계정을 활성화 하시겠습니까?')) return;
                } else {
                    AppCore.notify('error', '잘못된 처리 모드입니다.');
                    return;
                }

                const fd = new FormData();
                fd.append('id', id);
                fd.append('is_active', mode === 'activate' ? '1' : '0');

                try {
                    const res = await fetch(API.UPDATE_STATUS, {
                        method: 'POST',
                        body: fd
                    });

                    const json = await res.json();

                    if (json.success) {
                        employeeEditModal?.hide();
                        employeeTable?.ajax.reload(null, false);

                        AppCore.notify(
                            'success',
                            mode === 'deactivate'
                                ? '계정이 비활성화되었습니다.'
                                : '계정이 활성화되었습니다.'
                        );
                    } else {
                        AppCore.notify('error', json.message || '처리 실패');
                    }
                } catch (err) {
                    console.error(err);
                    AppCore.notify('error', '처리 중 오류가 발생했습니다.');
                }
            });

        $(document)
            .off('click.employeeForceDelete', '#edit_force_delete_btn')
            .on('click.employeeForceDelete', '#edit_force_delete_btn', async function () {
                const id = $('#edit_employee_id').val();

                if (!id) {
                    AppCore.notify('warning', '대상이 없습니다.');
                    return;
                }

                if (!confirm('정말 영구 삭제하시겠습니까?')) return;

                const fd = new FormData();
                fd.append('id', id);
                fd.append('action', 'delete');

                try {
                    const res = await fetch(API.PURGE, {
                        method: 'POST',
                        body: fd
                    });

                    const json = await res.json();

                    if (json.success) {
                        employeeEditModal?.hide();
                        employeeTable?.ajax.reload(null, false);
                        AppCore.notify('success', '삭제 완료');
                    } else {
                        AppCore.notify('error', json.message || '실패');
                    }
                } catch (err) {
                    console.error(err);
                    AppCore.notify('error', '삭제 실패');
                }
            });
    }

    /* =========================================================
       모달 브리지
    ========================================================= */
    function bindModalBridge($) {
        $(document)
            .off('employee:create-open.employees')
            .on('employee:create-open.employees', function () {
                resetEmployeeFormForCreate();
                employeeEditModal?.show();
            });

        $(document)
            .off('employee:edit-open.employees')
            .on('employee:edit-open.employees', async function (e, row) {
                await setEmployeeFormData(row);
                employeeEditModal?.show();
            });
    }

    function resetEmployeeFormForCreate() {
        const form = document.getElementById('employee-edit-form');
        form?.reset();
    
        $('#employeeModalTitle').html('<i class="bi bi-person-plus"></i> 새 직원 추가');
    
        $('#edit_employee_id').val('');
        $('#edit_employee_sort_no').val('');

        AdminPicker.clearSelect2('#edit_department_select', true);
        AdminPicker.clearSelect2('#edit_position_select', true);
        AdminPicker.clearSelect2('#edit_role_select', true);

        $('#edit_profile_preview').attr('src', '/public/assets/img/default-avatar.png');
        $('#edit_profile_delete_btn').hide();
        $('#edit_profile_image_delete').val('0');
        $('#profile_box').attr('data-label', '업로드');

        $('#edit_id_preview').attr('src', '/public/assets/img/placeholder-id.png');
        $('#edit_id_delete_btn').hide();
        $('#edit_rrn_image_delete').val('0');
        $('#id_box').attr('data-label', '업로드');

        $('#edit_cert_preview_img')
            .attr('src', '/public/assets/img/placeholder-cert.png')
            .data('file-path', '');
        $('#edit_cert_delete_btn').hide();
        $('#edit_certificate_file_delete').val('0');
        $('#edit_certificate_name').val('');
        $('#cert_box').attr('data-label', '업로드');

        $('#edit_soft_delete_btn')
            .text('계정 비활성화')
            .removeClass('btn-success')
            .addClass('btn-warning')
            .attr('data-mode', 'deactivate')
            .hide();

        $('#employeeEditSubmitBtn').show();
        $('#edit_force_delete_btn').hide();

        $('#edit_is_active').html('');
        $('#edit_created_at').text('-');
        $('#edit_created_by').text('-');
        $('#edit_approved').text('-');
        $('#edit_approved_at').text('-');
        $('#edit_approved_by').text('-');
        $('#edit_last_login').text('-');
        $('#edit_last_login_ip').html('-');
        $('#edit_last_login_device').text('-');
        $('#edit_updated_at').text('-');
        $('#edit_updated_by').text('-');
        $('#edit_password_updated_at').text('-');
        $('#edit_password_updated_by').text('-');
        $('#edit_deleted_at').text('-');
        $('#edit_deleted_by').text('-');

        rrnVisible = false;
        $('#edit_employee_rrn')
            .attr('type', 'text')
            .val('')
            .data('real', '');

        $('#employeeEditModal .nav-link').removeClass('active');
        $('#employeeEditModal .tab-pane').removeClass('show active');
        $('#employeeEditModal .nav-link:first').addClass('active');
        $('#employeeEditModal .tab-pane:first').addClass('show active');
    }

    /* =========================================================
       저장
    ========================================================= */
    function bindModalEvents($) {
        $(document)
            .off('submit.employeeModal', '#employee-edit-form')
            .on('submit.employeeModal', '#employee-edit-form', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                // ✅ 추가 (여기!!)
                formData.set(
                    'two_factor_enabled',
                    document.getElementById('edit_two_factor').checked ? '1' : '0'
                );
                
                formData.set(
                    'email_notify',
                    document.getElementById('edit_email_notify').checked ? '1' : '0'
                );
                
                formData.set(
                    'sms_notify',
                    document.getElementById('edit_sms_notify').checked ? '1' : '0'
                );

                const id = $('#edit_employee_id').val();

                if (!id) {
                    formData.set('action', 'create');
                } else {
                    formData.set('action', 'save');
                    formData.set('id', id);
                }

                formData.set('phone', onlyNumber(formData.get('phone') || ''));
                formData.set('emergency_phone', onlyNumber(formData.get('emergency_phone') || ''));

                const $rrnInput = $('#edit_employee_rrn');
                const realVal = onlyNumber($rrnInput.data('real') || '');
                formData.set('rrn', realVal !== '' ? realVal : '');

                const certName = String(formData.get('certificate_name') || '').trim();
                const certFile = formData.get('certificate_file');
                const hasExistingFile = !!$('#edit_cert_preview_img').data('file-path');
                const isDeleted = $('#edit_certificate_file_delete').val() === '1';
                const hasNewFile = certFile && certFile.name;

                if (
                    (certName && !hasNewFile && !hasExistingFile) ||
                    (!certName && (hasNewFile || hasExistingFile) && !isDeleted)
                ) {
                    AppCore.notify('warning', '자격증 이름과 파일은 함께 입력해야 합니다.');
                    return;
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
                        employeeEditModal?.hide();
                        employeeTable?.ajax.reload(null, false);
                        AppCore.notify('success', '저장 완료');
                    } else {
                        if (res.errors && Array.isArray(res.errors) && res.errors.length > 0) {
                            AppCore.notify('error', res.errors.join('\n'));
                        } else {
                            AppCore.notify('error', res.message || '저장 실패');
                        }
                    }
                })
                .fail(err => {
                    console.error(err);
                    AppCore.notify('error', '저장 실패');
                });
            });
    }

    /* =========================================================
       폼 데이터 세팅
    ========================================================= */
    async function setEmployeeFormData(row) {
        if (!row) return;

        $('#employeeModalTitle').html('<i class="bi bi-pencil-square"></i> 직원 정보 수정');
        $('#employeeEditSubmitBtn').show();
        $('#edit_soft_delete_btn').show();
        $('#edit_force_delete_btn').show();

        $('#edit_profile_image').val('');
        $('#edit_rrn_image').val('');
        $('#edit_certificate_file').val('');
        $('#edit_bank_file').val('');
        $('#edit_profile_image_delete').val('0');
        $('#edit_rrn_image_delete').val('0');
        $('#edit_certificate_file_delete').val('0');
        $('#edit_bank_file_delete').val('0');

        $('#edit_employee_id').val(row.id || '');
        $('#edit_employee_sort_no').val(row.sort_no || '');
        $('#edit_employee_username').val(row.username || '');
        $('#edit_employee_name').val(row.employee_name || '');
        $('#edit_employee_email').val(row.email || '');
        $('#edit_employee_phone').val(formatMobile(row.phone || ''));
        $('#edit_employee_emergency_phone').val(formatPhone(row.emergency_phone || ''));
        $('#edit_employee_address').val(row.address || '');
        $('#edit_employee_address_detail').val(row.address_detail || '');

        rrnVisible = false;
        $('#edit_employee_rrn')
            .attr('type', 'text')
            .val(maskRrn(row.rrn || ''))
            .data('real', onlyNumber(row.rrn || ''));

        $('#edit_doc_hire_date').val(row.doc_hire_date || '');
        $('#edit_real_hire_date').val(row.real_hire_date || '');
        $('#edit_doc_retire_date').val(row.doc_retire_date || '');
        $('#edit_real_retire_date').val(row.real_retire_date || '');

        $('#edit_certificate_name').val(row.certificate_name || '');

        $('#edit_bank_name').val(row.bank_name || '');
        $('#edit_account_number').val(row.account_number || '');
        $('#edit_account_holder').val(row.account_holder || '');


        $('#edit_two_factor').prop('checked', String(row.two_factor_enabled) === '1');
        $('#edit_email_notify').prop('checked', String(row.email_notify) === '1');
        $('#edit_sms_notify').prop('checked', String(row.sms_notify) === '1');

        $('#edit_employee_note').val(row.note || '');
        $('#edit_employee_memo').val(row.memo || '');

        await Promise.all([
            loadSelectOptions('#edit_department_select', API.DEPARTMENT_LIST, row.department_id, 'POST'),
            loadSelectOptions('#edit_position_select', API.POSITION_LIST, row.position_id, 'POST'),
            loadSelectOptions('#edit_role_select', API.ROLE_LIST, row.role_id, 'POST')
        ]);

        $('#edit_profile_preview')
            .attr('src', resolveFileSrc(row.profile_image, '/public/assets/img/default-avatar.png'));

        $('#edit_id_preview')
            .attr('src', resolveFileSrc(row.rrn_image, '/public/assets/img/placeholder-id.png'));

        $('#edit_cert_preview_img')
            .attr('src', getCertPreview(row.certificate_file || ''))
            .data('file-path', row.certificate_file || '')
            .off('error')
            .on('error', function () {
                $(this).attr('src', '/public/assets/img/placeholder-cert.png');
            });
        
        $('#edit_bank_preview')
            .attr('src', getBankPreview(row.bank_file || ''))
            .data('file-path', row.bank_file || '')
            .off('error')
            .on('error', function () {
                $(this).attr('src', '/public/assets/img/placeholder-bank.png');
            });
        
        $('#edit_profile_delete_btn').toggle(!!row.profile_image);
        $('#edit_id_delete_btn').toggle(!!row.rrn_image);
        $('#edit_cert_delete_btn').toggle(!!row.certificate_file);
        $('#edit_bank_delete_btn').toggle(!!row.bank_file);
        
        $('#profile_box').attr('data-label', row.profile_image ? '원본 보기' : '업로드');
        $('#id_box').attr('data-label', row.rrn_image ? '원본 보기' : '업로드');
        $('#cert_box').attr('data-label', row.certificate_file ? '원본 보기' : '업로드');
        $('#bank_box').attr('data-label', row.bank_file ? '원본 보기' : '업로드');

        $('#edit_created_at').text(row.user_created_at || '-');    
        $('#edit_created_by').text(
            row.user_created_by_name ||
            row.created_by_name ||
            ''
        );
        $('#edit_approved').text(String(row.approved) === '1' ? '승인' : '미승인');
        $('#edit_approved_at').text(row.approved_at || '-');
        $('#edit_approved_by').text(
            row.approved_by_name ||
            row.approved_by ||
            '-'
        );

        $('#edit_last_login').text(row.last_login || '-');

        const rawIp = row.last_login_ip || '-';
        let external = '';
        let internal = '';

        if (rawIp.includes('(')) {
            external = rawIp.split('(')[0].trim();
            internal = rawIp.split('(')[1].replace(')', '').trim();
        } else {
            external = rawIp;
        }

        $('#edit_last_login_ip').html(`
            <div style="font-weight:600;">${external || '-'}</div>
            <div style="font-size:12px;color:#888;">${internal}</div>
        `);

        $('#edit_last_login_device').text(row.last_login_device || '-');
        $('#edit_updated_at').text(row.user_updated_at || '-');
        $('#edit_updated_by').text(
            row.user_updated_by_name ||
            row.updated_by_name ||
            ''
        );

        $('#edit_password_updated_at').text(row.password_updated_at || '-');
        $('#edit_password_updated_by').text(row.password_updated_by_name || '-');
        $('#edit_deleted_at').text(row.deleted_at || '-');
        $('#edit_deleted_by').text(row.deleted_by_name || '');

        $('#edit_is_active').html(
            String(row.is_active) === '1'
                ? '<span class="badge bg-success">활성</span>'
                : '<span class="badge bg-secondary">비활성</span>'
        );

        const $btnDeactivate = $('#edit_soft_delete_btn');
        if (String(row.is_active) === '1') {
            $btnDeactivate
                .text('계정 비활성화')
                .removeClass('btn-success')
                .addClass('btn-warning')
                .attr('data-mode', 'deactivate');
        } else {
            $btnDeactivate
                .text('계정 활성화')
                .removeClass('btn-warning')
                .addClass('btn-success')
                .attr('data-mode', 'activate');
        }
    }

    function getBankPreview(filePath) {
        if (!filePath) {
            return '/public/assets/img/placeholder-bank.png';
        }
    
        const ext = String(filePath).split('.').pop().toLowerCase();
    
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            return `/api/file/preview?path=${encodeURIComponent(filePath)}`;
        }
    
        return '/public/assets/img/has-bank-file.png';
    }

    /* =========================================================
       Select2 로딩
    ========================================================= */
    async function loadSelectOptions(selector, apiUrl, selectedValue = '', method = 'GET') {
        selectedValue = selectedValue != null ? String(selectedValue) : '';

        try {
            const res = await fetch(apiUrl, {
                method,
                body: new FormData(),
                credentials: 'include'
            });

            const text = await res.text();
            let json;

            try {
                json = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse 실패:', apiUrl, text);
                return;
            }

            const list = Array.isArray(json.data) ? json.data : [];
            const items = [];

            list.forEach((row) => {
                const id =
                    row.user_id ??
                    row.department_id ??
                    row.position_id ??
                    row.role_id ??
                    Object.values(row)[0];

                const text =
                    row.name ??
                    row.dept_name ??
                    row.department_name ??
                    row.position_name ??
                    row.role_name ??
                    row.label ??
                    Object.values(row)[1] ??
                    '';

                items.push({
                    id: String(id ?? ''),
                    text: String(text ?? '')
                });
            });

            AdminPicker.destroySelect2(selector);
            AdminPicker.reloadSelect2(selector, items, 'id', 'text', null);
            AdminPicker.select2(selector, {
                placeholder: '선택해주세요',
                allowClear: true,
                width: '100%'
            });

            if (selectedValue) {
                const hasOption = items.some(item => String(item.id) === String(selectedValue));

                if (!hasOption) {
                    const el = document.querySelector(selector);
                    if (el) {
                        const option = new Option('(이름 없음)', selectedValue, false, false);
                        el.append(option);
                    }
                }

                AdminPicker.setSelect2Value(selector, selectedValue, true);
            } else {
                AdminPicker.clearSelect2(selector, true);
            }
        } catch (err) {
            console.error('Select Load Error:', selector, err);
        }
    }

    /* =========================================================
       입력가이드 / 포맷
    ========================================================= */
    function bindInputGuide($) {
        let shown = {};

        function notifyOnce(key, message) {
            if (shown[key]) return;
            shown[key] = true;

            if (window.AppCore?.notify) {
                AppCore.notify('info', message);
            } else {
                console.log(message);
            }
        }

        $(document)
            .off('focus.employeeSortNo')
            .on('focus.employeeSortNo', '#edit_employee_sort_no', function () {
                notifyOnce('sort_no', '순번은 자동 처리됩니다. 입력하지 마세요.');
            });

        $(document)
            .off('focus.employeeUsername')
            .on('focus.employeeUsername', '#edit_employee_username', function () {
                notifyOnce('username', '아이디는 영문/숫자 조합으로 입력하세요.');
            });

        $(document)
            .off('focus.employeeRrn')
            .on('focus.employeeRrn', '#edit_employee_rrn', function () {
                notifyOnce('rrn', '주민번호는 암호화되어 저장됩니다.');
            });

        $(document)
            .off('focus.employeePhone')
            .on('focus.employeePhone', '#edit_employee_phone', function () {
                notifyOnce('phone', '연락처는 숫자만 입력하세요.');
            });

        $(document)
            .off('focus.employeeEmail')
            .on('focus.employeeEmail', '#edit_employee_email', function () {
                notifyOnce('email', '이메일은 로그인 및 알림에 사용됩니다.');
            });
    }

    function bindInputFormat($) {
        $(document)
            .off('input.employeeMobile', '#edit_employee_phone')
            .on('input.employeeMobile', '#edit_employee_phone', function () {
                this.value = formatMobile(this.value);
            });

        $(document)
            .off('input.employeeEmergency', '#edit_employee_emergency_phone')
            .on('input.employeeEmergency', '#edit_employee_emergency_phone', function () {
                this.value = formatPhone(this.value);
            });

        $(document)
            .off('input.employeeRrnFormat', '#edit_employee_rrn')
            .on('input.employeeRrnFormat', '#edit_employee_rrn', function () {
                const $input = $(this);
                const raw = onlyNumber($input.val());

                $input.data('real', raw);

                if (rrnVisible) {
                    $input.val(formatCorpNumber(raw));
                } else {
                    $input.val(maskRrn(raw));
                }
            });
    }

    function bindTogglePassword($) {
        $(document)
            .off('click.togglePassword', '.toggle-password')
            .on('click.togglePassword', '.toggle-password', function () {
                const $btn = $(this);
                const target = $btn.data('target');
                if (!target) return;

                const $input = $(target);
                if (!$input.length) return;

                const type = $input.attr('type');

                if (type === 'password') {
                    $input.attr('type', 'text');
                    $btn.find('i').removeClass('bi-eye').addClass('bi-eye-slash');
                } else {
                    $input.attr('type', 'password');
                    $btn.find('i').removeClass('bi-eye-slash').addClass('bi-eye');
                }
            });
    }

    function bindToggleRrn($) {
        $(document)
            .off('click.toggleRrn', '.toggle-rrn')
            .on('click.toggleRrn', '.toggle-rrn', function () {
                const $input = $('#edit_employee_rrn');
                const $icon = $(this).find('i');

                const currentRaw = onlyNumber($input.val());
                if (!$input.data('real') && currentRaw !== '') {
                    $input.data('real', currentRaw);
                }

                const realVal = onlyNumber($input.data('real') || '');

                if (!rrnVisible) {
                    rrnVisible = true;
                    $input.attr('type', 'text').val(formatCorpNumber(realVal));
                    $icon.removeClass('bi-eye').addClass('bi-eye-slash');
                } else {
                    rrnVisible = false;
                    $input.attr('type', 'text').val(maskRrn(realVal));
                    $icon.removeClass('bi-eye-slash').addClass('bi-eye');
                }
            });
    }

    function maskRrn(rrn) {
        if (!rrn) return '';

        const clean = String(rrn).replace(/\D/g, '');
        if (clean.length < 7) return rrn;

        return clean.substring(0, 6) + '-' + '*******';
    }

    /* =========================================================
       파일 처리
    ========================================================= */
    function bindFilePreviewAndDeleteEvents($) {
        $(document)
            .off('click.profilePreview')
            .on('click.profilePreview', '#edit_profile_preview', function () {
                const src = $(this).attr('src');

                if (!src || src.includes('default-avatar.png')) {
                    $('#edit_profile_image').trigger('click');
                    return;
                }

                window.open(src, '_blank');
            });

        $(document)
            .off('click.idPreview')
            .on('click.idPreview', '#edit_id_preview', function () {
                const src = $(this).attr('src');

                if (!src || src.includes('placeholder-id.png')) {
                    $('#edit_rrn_image').trigger('click');
                    return;
                }

                window.open(src, '_blank');
            });

        $(document)
            .off('click.certPreview')
            .on('click.certPreview', '#edit_cert_preview_img', function () {
                const filePath = $(this).data('file-path');

                if (!filePath) {
                    $('#edit_certificate_file').trigger('click');
                    return;
                }

                const url = `/api/file/preview?path=${encodeURIComponent(filePath)}`;
                window.open(url, '_blank');
            });

        $(document)
            .off('click.employeeProfileDelete')
            .on('click.employeeProfileDelete', '#edit_profile_delete_btn', function (e) {
                e.preventDefault();
                e.stopPropagation();

                $('#edit_profile_preview')
                    .attr('src', '/public/assets/img/default-avatar.png')
                    .removeAttr('data-file-path');

                const $input = $('#edit_profile_image');
                const $newInput = $input.clone().val('');
                $input.replaceWith($newInput);

                $('#edit_profile_image_delete').val('1');
                $('#profile_box').attr('data-label', '업로드');
                $(this).hide();
            });

        $(document)
            .off('click.employeeIdDelete')
            .on('click.employeeIdDelete', '#edit_id_delete_btn', function (e) {
                e.preventDefault();
                e.stopPropagation();

                $('#edit_id_preview')
                    .attr('src', '/public/assets/img/placeholder-id.png')
                    .removeAttr('data-file-path');

                const $input = $('#edit_rrn_image');
                const $newInput = $input.clone().val('');
                $input.replaceWith($newInput);

                $('#edit_rrn_image_delete').val('1');
                $('#id_box').attr('data-label', '업로드');
                $(this).hide();
            });

            $(document)
            .off('click.employeeCertDelete')
            .on('click.employeeCertDelete', '#edit_cert_delete_btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
        
                $('#edit_cert_preview_img')
                    .attr('src', '/public/assets/img/placeholder-cert.png')
                    .data('file-path', '');
        
                const $input = $('#edit_certificate_file');
                const $newInput = $input.clone().val('');
                $input.replaceWith($newInput);
        
                $('#edit_certificate_file_delete').val('1');
                $('#edit_certificate_name').val('');
                $('#cert_box').attr('data-label', '업로드');
        
                $(this).hide();
            });

        $(document)
            .off('change.employeeProfilePreview', '#edit_profile_image')
            .on('change.employeeProfilePreview', '#edit_profile_image', function () {
                const file = this.files?.[0];
                if (!file) return;

                $('#edit_profile_image_delete').val('0');
                $('#edit_profile_delete_btn').show();
                $('#profile_box').attr('data-label', '원본 보기');

                const reader = new FileReader();
                reader.onload = function (e) {
                    $('#edit_profile_preview').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);
            });

        $(document)
            .off('change.employeeIdPreview', '#edit_rrn_image')
            .on('change.employeeIdPreview', '#edit_rrn_image', function () {
                const file = this.files?.[0];
                if (!file) return;

                $('#edit_rrn_image_delete').val('0');
                $('#edit_id_delete_btn').show();
                $('#id_box').attr('data-label', '원본 보기');

                const ext = file.name.split('.').pop().toLowerCase();

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        $('#edit_id_preview').attr('src', e.target.result);
                    };
                    reader.readAsDataURL(file);
                } else {
                    $('#edit_id_preview').attr('src', '/public/assets/img/placeholder-id.png');
                }
            });

        $(document)
            .off('change.employeeCertPreview', '#edit_certificate_file')
            .on('change.employeeCertPreview', '#edit_certificate_file', function () {
                const file = this.files?.[0];
                if (!file) return;


                $('#edit_cert_delete_btn').show();
                $('#cert_box').attr('data-label', '원본 보기');

                const ext = file.name.split('.').pop().toLowerCase();

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        $('#edit_cert_preview_img')
                            .attr('src', e.target.result)
                            .data('file-path', '');
                    };
                    reader.readAsDataURL(file);
                } else {
                    $('#edit_cert_preview_img')
                        .attr('src', '/public/assets/img/has-cert.png')
                        .data('file-path', '');
                }
            });

        $(document)
            .off('click.bankPreview')
            .on('click.bankPreview', '#edit_bank_preview', function () {
                const filePath = $(this).data('file-path');
                const src = $(this).attr('src');

                if (!filePath && (!src || src.includes('placeholder-bank.png'))) {
                    $('#edit_bank_file').trigger('click');
                    return;
                }

                if (filePath) {
                    const url = `/api/file/preview?path=${encodeURIComponent(filePath)}`;
                    window.open(url, '_blank');
                    return;
                }

                window.open(src, '_blank');
            });                             
        $(document)
            .off('click.employeeBankDelete')
            .on('click.employeeBankDelete', '#edit_bank_delete_btn', function (e) {
                e.preventDefault();
                e.stopPropagation();

   
                const $input = $('#edit_bank_file');
                const $newInput = $input.clone().val('');
                $input.replaceWith($newInput);

                $('#bank_box').attr('data-label', '업로드');
                $(this).hide();
            });

        $(document)
            .off('change.employeeBankPreview', '#edit_bank_file')
            .on('change.employeeBankPreview', '#edit_bank_file', function () {
                const file = this.files?.[0];
                if (!file) return;

                $('#edit_bank_file_delete').val('0');
                $('#edit_bank_delete_btn').show();
                $('#bank_box').attr('data-label', '원본 보기');

                const ext = file.name.split('.').pop().toLowerCase();

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        $('#edit_bank_preview')
                            .attr('src', e.target.result)
                            .data('file-path', '');
                    };
                    reader.readAsDataURL(file);
                } else {
                    $('#edit_bank_preview')
                        .attr('src', '/public/assets/img/has-bank-file.png')
                        .data('file-path', '');
                }
            });
    }

    function resolveFileSrc(path, fallback = '') {
        if (!path) return fallback;

        if (typeof path === 'string' && (path.startsWith('private://') || path.startsWith('public://'))) {
            return `/api/file/preview?path=${encodeURIComponent(path)}`;
        }

        if (typeof path === 'string' && (path.startsWith('http') || path.startsWith('/'))) {
            return path;
        }

        return fallback;
    }

    function getCertPreview(filePath) {
        if (!filePath) {
            return '/public/assets/img/placeholder-cert.png';
        }

        const ext = String(filePath).split('.').pop().toLowerCase();

        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            return `/api/file/preview?path=${encodeURIComponent(filePath)}`;
        }

        return '/public/assets/img/has-cert.png';
    }

    /* =========================================================
       전역 이벤트
    ========================================================= */
    function bindGlobalEvents() {
        if (globalBound) return;
        globalBound = true;

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            if (todayPicker && typeof todayPicker.close === 'function') {
                const pickerEl = document.getElementById('today-picker');
                if (pickerEl && !pickerEl.classList.contains('is-hidden')) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    todayPicker.close();
                    return;
                }
            }

            const modalEl = document.querySelector('.modal.show');
            if (modalEl) {
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                modalInstance?.hide();
            }
        });
    }














    
})();
