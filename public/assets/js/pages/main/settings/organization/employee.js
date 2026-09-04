import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import * as NumberFormat from '/public/assets/js/common/format.js';
import {
    createDataTable,
    bindTableHighlight
} from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { getCodeOptions, initCodeSelectControls } from '/public/assets/js/pages/main/settings/system/code-select.js';
import { bindLazyAddress } from '/public/assets/js/common/address-lazy.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import { bindEmployeePolicySync, applyEmployeeModalPolicyLabels, collectEmployeeDetailValues, validateEmployeeRequiredPolicies, bindFilePreviewAndDeleteEvents, resolveFileSrc, renderRepresentativeQualification, escapeHtml } from './employee/form.js';
import { renderEmployeeSystemInfo } from './employee/system-info.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';
    const onlyNumber = NumberFormat.onlyNumber || ((value) => String(value ?? '').replace(/\D/g, ''));
    const { formatMobile, formatPhone, formatCorpNumber, formatAccountNumber, unformatAccountNumber, setBankAccountFormatRegistry } = NumberFormat;
    const API = {
        LIST: '/api/settings/organization/employee/list',
        DETAIL: '/api/settings/organization/employee/detail',
        SEARCH_PICKER: '/api/settings/organization/employee/search-picker',
        SAVE: '/api/settings/organization/employee/save',
        UPDATE_STATUS: '/api/settings/organization/employee/update-status',
        DELETE: '/api/settings/organization/employee/delete',
        REORDER: '/api/settings/organization/employee/reorder',

        DEPARTMENT_LIST: '/api/settings/organization/department/list',
        POSITION_LIST: '/api/settings/organization/position/list',
        ROLE_LIST: '/api/settings/organization/role/list',
        PERSONNEL_OPTIONS: '/api/institution/human-resources/personnel-action/options'
    };

    const EMPLOYEE_COLUMN_MAP = {
        sort_no:                  { label: '\uC21C\uBC88', visible: true },
        user_id:                  { label: '\uC0AC\uC6A9\uC790ID', visible: false },
        profile_image:            { label: '\uC0AC\uC9C4', visible: true },
        username:                 { label: '\uC544\uC774\uB514', visible: true },
        employee_name:            { label: '\uC9C1\uC6D0\uBA85', visible: true },
        role_id:                  { label: '\uC5ED\uD560', visible: true },

        department_id:            { label: '\uBD80\uC11C', visible: true },
        position_id:              { label: '\uC9C1\uCC45', visible: true },

        approved:                 { label: '\uC2B9\uC778\uC5EC\uBD80', visible: false },
        approved_at:              { label: '\uC2B9\uC778\uC77C\uC2DC', visible: false },
        approved_by:              { label: '\uC2B9\uC778\uC790', visible: false, type: 'actor' },

        email:                    { label: '\uC774\uBA54\uC77C', visible: true },
        phone:                    { label: '\uC5F0\uB77D\uCC98', visible: true },
        emergency_phone:          { label: '\uBE44\uC0C1\uC5F0\uB77D\uCC98', visible: false },

        two_factor_enabled:       { label: '2\uCC28\uC778\uC99D', visible: true },
        email_notify:             { label: '\uC774\uBA54\uC77C\uC54C\uB9BC', visible: true },
        sms_notify:               { label: 'SMS\uC54C\uB9BC', visible: false },

        rrn:                      { label: '\uC8FC\uBBFC\uB4F1\uB85D\uBC88\uD638', visible: false },
        rrn_image:                { label: '\uC2E0\uBD84\uC99D\uD30C\uC77C', visible: false },
        address:                  { label: '\uC8FC\uC18C', visible: false },
        address_detail:           { label: '\uC0C1\uC138\uC8FC\uC18C', visible: false },

        doc_hire_date:            { label: '\uC11C\uB958\uC785\uC0AC\uC77C', visible: false },
        real_hire_date:           { label: '\uC2E4\uC785\uC0AC\uC77C', visible: false },
        doc_retire_date:          { label: '\uC11C\uB958\uD1F4\uC0AC\uC77C', visible: false },
        real_retire_date:         { label: '\uC2E4\uD1F4\uC0AC\uC77C', visible: false },

        login_fail_count:         { label: '\uB85C\uADF8\uC778\uC2E4\uD328\uD69F\uC218', visible: false },
        account_locked_until:     { label: '\uC7A0\uAE08\uB9CC\uB8CC\uC77C\uC2DC', visible: false },

        last_login:               { label: '\uB9C8\uC9C0\uB9C9\uB85C\uADF8\uC778', visible: true },
        last_login_ip:            { label: '\uB85C\uADF8\uC778IP', visible: false },
        last_login_device:        { label: '\uB85C\uADF8\uC778\uB514\uBC14\uC774\uC2A4', visible: false },

        password_updated_at:      { label: '\uBE44\uBC00\uBC88\uD638\uBCC0\uACBD\uC77C', visible: false },
        password_updated_by:      { label: '\uBE44\uBC00\uBC88\uD638\uBCC0\uACBD\uC790', visible: false, type: 'actor' },

        bank_name:                { label: '\uC740\uD589\uBA85', visible: false },
        account_number:           { label: '\uACC4\uC88C\uBC88\uD638', visible: false },
        account_holder:           { label: '\uC608\uAE08\uC8FC', visible: false },
        bank_file:                { label: '\uD1B5\uC7A5\uC0AC\uBCF8', visible: false },

        note:                     { label: '\uBE44\uACE0', visible: false },
        memo:                     { label: '\uBA54\uBAA8', visible: false },

        user_created_at:          { label: '\uC0DD\uC131\uC77C\uC2DC', visible: false },
        user_created_by:          { label: '\uC0DD\uC131\uC790', visible: false, type: 'actor' },
        user_updated_at:          { label: '\uC218\uC815\uC77C\uC2DC', visible: false },
        user_updated_by:          { label: '\uC218\uC815\uC790', visible: false, type: 'actor' },

        is_active:                { label: '\uC0C1\uD0DC', visible: true, noVis: true }
    };

    const DATE_OPTIONS = [
        { value: 'user_created_at', label: '\uB4F1\uB85D\uC77C\uC790' },
        { value: 'last_login', label: '\uB9C8\uC9C0\uB9C9 \uB85C\uADF8\uC778' },
        { value: 'real_hire_date', label: '\uC785\uC0AC\uC77C' },
        { value: 'real_retire_date', label: '\uD1F4\uC0AC\uC77C' }
    ];

    let employeeTable = null;
    let employeeEditModal = null;
    let originalImageModal = null;
    let todayPicker = null;
    let rrnVisible = false;
    let globalBound = false;
    const employeeSelectOptionsCache = new Map();
    let employeeBankControlsPromise = null;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('[employees.js] jQuery not loaded');
            return;
        }

        const $ = window.jQuery;
        initEmployeePage($);
    });

    async function initEmployeePage($) {
        await initDataTable($);

        initEmployeeModules();
        initModals();
        initAdminDatePicker();
        bindAdminDateInputs();
        bindDateIconPicker();
        bindRowReorder(employeeTable, {
            api: API.REORDER,
            onSuccess() {
                AppCore.notify('success', '직원 순번이 저장되었습니다.');
                employeeTable?.ajax.reload(null, false);
            },
            onError(json) {
                AppCore.notify('error', json?.message || '직원 순번 저장에 실패했습니다.');
                employeeTable?.ajax.reload(null, false);
            }
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
        bindGlobalEvents();
    }

    function initEmployeeModules() {
        try {
            window.EmployeeUtils?.hideAlertMessages?.();
            window.EmployeePreview?.initCreate?.();
            window.EmployeePreview?.initEdit?.();
            bindLazyAddress({
                root: document.getElementById('employeeEditModal'),
                detail: '#edit_employee_address_detail',
                notify: (type, message) => AppCore.notify(type, message),
            });
            window.EmployeeManagerSelect?.initCreate?.();
            window.EmployeeManagerSelect?.initEdit?.();
        } catch (e) {
            console.error('[employees.js] initEmployeeModules failed:', e);
        }
    }

    function initModals() {
        const editEl = document.getElementById('employeeEditModal');
        const imageEl = document.getElementById('originalImageModal');

        if (editEl) {
            employeeEditModal = new bootstrap.Modal(editEl, { focus: false });
            bindEmployeePolicySync();
            applyEmployeeModalPolicyLabels(document);
            renderEmployeeSystemInfo();

            editEl.addEventListener('shown.bs.modal', () => {
                bindAdminDateInputs();
                applyEmployeeModalPolicyLabels(document);
            });
        }

        if (imageEl) {
            originalImageModal = new bootstrap.Modal(imageEl, { focus: false });
        }
    }

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
        document.querySelectorAll('#employeeEditModal .admin-date').forEach(input => {
            if (input.dataset.dateInputBound === '1') return;
            input.dataset.dateInputBound = '1';

            input.addEventListener('input', () => {
                input.value = formatDateInputValue(input.value);
            });

            input.addEventListener('blur', () => {
                input.value = normalizeDateInputValue(input.value);
            });
        });
    }

    function bindDateIconPicker() {
        if (document.__employeeDateIconPickerBound) return;
        document.__employeeDateIconPickerBound = true;

        document.addEventListener('click', function (e) {
            const icon = e.target.closest('.date-icon');
            if (!icon) return;
            if (!icon.closest('#employeeEditModal')) return;

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

    function formatDateInputValue(value) {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 8);

        if (digits.length <= 4) return digits;
        if (digits.length <= 6) return `${digits.slice(0, 4)}-${digits.slice(4)}`;

        return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6)}`;
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

    async function initDataTable($) {
        const columns = buildEmployeeColumns();

        employeeTable = await createDataTable({
            tableSelector: '#employee-table',
            api: API.LIST,
            columns,
            tableSettings: {
                pageKey: 'dashboard.settings.organization.employee',
                userSettingPageKey: 'employee',
                tableKey: 'employee-table',
                storageKey: 'datatable.settings.dashboard.settings.organization.employee.employee-table.v1',
                metaDomain: 'employee',
                tableLabel: '\uC9C1\uC6D0',
                title: '\uC9C1\uC6D0 \uD14C\uC774\uBE14 \uC124\uC815',
            },
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            selectionColumn: { widthResizable: true },
            selectable: true,
            deleteButton: true,
            deleteApi: API.DELETE,
            buttons: [
                {
                    text: '신규등록',
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
                dateOptions: DATE_OPTIONS,
            });
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
            settingsKey: '__reorder',
            className: 'reorder-handle no-sort no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>'
        });



        Object.entries(EMPLOYEE_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                className: [config.noVis ? 'noVis' : '', field === 'is_active' ? 'text-center' : ''].filter(Boolean).join(' '),
                headerClassName: field === 'is_active' ? 'text-center' : '',
                defaultContent: '',
                orderable: field !== 'profile_image',
                searchable: field !== 'profile_image',
                render: function (data, type, row) {
                    if (config.type === 'actor') {
                        const displayValue = actorDisplay(row, field);
                        return type === 'display' ? escapeHtml(displayValue) : displayValue;
                    }

                    if (data == null) {
                        return '';
                    }

                    if (type !== 'display') return data;

                    if (field === 'profile_image') {
                        const src = resolveFileSrc(data, '/public/assets/img/default-avatar.png');
                        return '<img src="' + escapeHtml(src) + '" class="employee-img-preview" style="width:40px;height:40px;border-radius:6px;object-fit:cover;cursor:pointer;">';
                    }

                    if (field === 'is_active') {
                        const active = String(data) === '1';
                        return `
                            <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                                <input type="checkbox"
                                       class="form-check-input employee-active-toggle"
                                       data-id="${escapeHtml(row.id || '')}"
                                       ${active ? 'checked' : ''}
                                       aria-label="상태 변경">
                            </div>
                        `;
                    }

                    if (field === 'approved') {
                        return String(data) === '1' ? '\uC2B9\uC778' : '\uBBF8\uC2B9\uC778';
                    }

                    if (
                        field === 'two_factor_enabled' ||
                        field === 'email_notify' ||
                        field === 'sms_notify'
                    ) {
                        return String(data) === '1' ? 'ON' : 'OFF';
                    }

                    if (field === 'department_id') {
                        const departmentName = String(row?.department_name || '').trim();
                        return departmentName || '';
                    }

                    if (field === 'position_id') {
                        const positionName = String(row?.position_name || '').trim();
                        return positionName || '';
                    }

                    if (field === 'role_id') {
                        const roleName = String(row?.role_name || '').trim();
                        return roleName || '';
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

        columns.push({
            data: '__deprecated_is_active',
            title: '',
            visible: false,
            className: 'd-none no-colvis',
            headerClassName: 'd-none no-colvis',
            settingsKey: '__legacy_employee_status',
            defaultContent: '',
            render: function (data, type, row) {
                if (type !== 'display') return data;
                const active = String(data) === '1';
                return `
                    <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                        <input type="checkbox"
                               class="form-check-input employee-active-toggle"
                               data-id="${escapeHtml(row.id || '')}"
                               ${active ? 'checked' : ''}
                               aria-label="상태 변경">
                    </div>
                `;
            }
        });

        columns.push({
            data: null,
            settingsKey: '__actions',
            __dtColumnKind: 'virtual',
            title: '관리',
            width: '90px',
            widthResizable: true,
            className: 'text-center no-colvis',
            headerClassName: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render: function (_data, type, row) {
                if (type !== 'display') return '';
                return `
                    <button type="button"
                            class="btn btn-outline-primary btn-sm employee-edit-btn"
                            data-id="${escapeHtml(row.id || '')}">
                        수정
                    </button>
                `;
            }
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
            el.textContent = '\uCD1D ' + (count ?? 0) + '\uBA85';
        }
    }

    function bindTableEvents($) {
        $('#employee-table tbody')
            .off('click.employeeImgPreview', '.employee-img-preview')
            .on('click.employeeImgPreview', '.employee-img-preview', function (e) {
                e.stopPropagation();

                const src = $(this).attr('src');
                if (!src) {
                    AppCore.notify('warning', '대상이 없습니다.');
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
            .off('change.employeeActiveToggle', '.employee-active-toggle')
            .on('change.employeeActiveToggle', '.employee-active-toggle', async function (e) {
                e.stopPropagation();

                const id = this.dataset.id;
                const active = this.checked;
                if (!id) return;

                const fd = new FormData();
                fd.append('id', id);
                fd.append('is_active', active ? '1' : '0');

                this.disabled = true;

                try {
                    const res = await fetch(API.UPDATE_STATUS, { method: 'POST', body: fd, credentials: 'include' });
                    const json = await res.json();

                    if (!json.success) {
                        throw new Error(json.message || '상태 변경에 실패했습니다.');
                    }

                    employeeTable?.ajax.reload(null, false);
                    AppCore.notify('success', active ? '사용으로 변경되었습니다.' : '미사용으로 변경되었습니다.');
                } catch (err) {
                    console.error(err);
                    this.checked = !active;
                    AppCore.notify('error', err.message || '상태 변경에 실패했습니다.');
                } finally {
                    this.disabled = false;
                }
            });

        $('#employee-table tbody')
            .off('click.employeeEditBtn', '.employee-edit-btn')
            .on('click.employeeEditBtn', '.employee-edit-btn', function (e) {
                e.stopPropagation();

                const data = employeeTable.row($(this).closest('tr')).data();
                if (!data) return;

                $(document).trigger('employee:edit-open', [data]);
            });
    }

    function bindModalEvents($) {
        $(document)
            .off('submit.employeeSave', '#employee-edit-form')
            .on('submit.employeeSave', '#employee-edit-form', function (e) {
                e.preventDefault();
                saveEmployee();
            });
    }

    async function saveEmployee() {
        const form = document.getElementById('employee-edit-form');
        if (!form) return;

        const formData = new FormData(form);
        const requiredMessage = validateEmployeeRequiredPolicies(collectEmployeeDetailValues(form, formData));
        if (requiredMessage) {
            AppCore.notify('warning', requiredMessage);
            return;
        }

        const id = $('#edit_employee_id').val();
        const name = String($('#edit_employee_name').val() || '').trim();
        const username = String($('#edit_employee_username').val() || '').trim();

        if (!username || !name) {
            AppCore.notify('warning', '아이디와 이름을 입력하세요.');
            return;
        }

        formData.set('action', id ? 'update' : 'create');
        formData.set('account_number', unformatAccountNumber?.(formData.get('account_number') || '') || '');

        const rrnReal = onlyNumber($('#edit_employee_rrn').data('real') || $('#edit_employee_rrn').val() || '');
        formData.set('rrn', rrnReal);
        formData.set('two_factor_enabled', $('#edit_two_factor').is(':checked') ? '1' : '0');
        formData.set('email_notify', $('#edit_email_notify').is(':checked') ? '1' : '0');
        formData.set('sms_notify', $('#edit_sms_notify').is(':checked') ? '1' : '0');

        try {
            const res = await fetch(API.SAVE, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            const json = await res.json();

            if (json.success) {
                employeeEditModal?.hide();
                employeeTable?.ajax.reload(null, false);
                AppCore.notify('success', '저장 완료');
                return;
            }

            if (json.errors && Array.isArray(json.errors) && json.errors.length > 0) {
                AppCore.notify('error', json.errors.join('\n'));
                return;
            }

            AppCore.notify('error', json.message || '저장 실패');
        } catch (err) {
            console.error(err);
            AppCore.notify('error', '저장 실패');
        }
    }

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
                    if (!confirm('계정을 비활성화하시겠습니까?')) return;
                } else if (mode === 'activate') {
                    if (!confirm('계정을 활성화하시겠습니까?')) return;
                } else {
                    AppCore.notify('error', '잘못된 처리 모드입니다.');
                    return;
                }

                const fd = new FormData();
                fd.append('id', id);
                fd.append('is_active', mode === 'activate' ? '1' : '0');

                try {
                    const res = await fetch(API.UPDATE_STATUS, { method: 'POST', body: fd, credentials: 'include' });
                    const json = await res.json();

                    if (json.success) {
                        employeeEditModal?.hide();
                        employeeTable?.ajax.reload(null, false);
                        AppCore.notify('success', mode === 'deactivate' ? '계정이 비활성화되었습니다.' : '계정이 활성화되었습니다.');
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
                    const res = await fetch(API.DELETE, { method: 'POST', body: fd, credentials: 'include' });
                    const json = await res.json();

                    if (json.success) {
                        employeeEditModal?.hide();
                        employeeTable?.ajax.reload(null, false);
                        AppCore.notify('success', '삭제 완료');
                    } else {
                        AppCore.notify('error', json.message || '삭제 실패');
                    }
                } catch (err) {
                    console.error(err);
                    AppCore.notify('error', '삭제 실패');
                }
            });

    }

    function bindModalBridge($) {
        $(document)
            .off('employee:create-open.employees')
            .on('employee:create-open.employees', async function () {
                void prepareEmployeeBankControls();
                const prepare = resetEmployeeFormForCreate();
                employeeEditModal?.show();
                prepare.catch((error) => {
                    console.error('[employees] create modal prepare failed', error);
                    AppCore.notify('error', '직원 입력 항목 준비 중 오류가 발생했습니다.');
                });
            });

        $(document)
            .off('employee:edit-open.employees')
            .on('employee:edit-open.employees', async function (e, row) {
                void prepareEmployeeBankControls();
                const prepare = setEmployeeFormData(row);
                employeeEditModal?.show();
                prepare.catch((error) => {
                    console.error('[employees] edit modal prepare failed', error);
                    AppCore.notify('error', '직원 정보 준비 중 오류가 발생했습니다.');
                });
            });
    }

    async function resetEmployeeFormForCreate() {
        const form = document.getElementById('employee-edit-form');
        form?.reset();

        $('#employeeModalTitle').html('<i class="bi bi-person-plus"></i> 새 직원 추가');
        $('#edit_employee_id').val('');
        setRrnField('');
        setEmployeeBankSelectValue('');
        $('#edit_account_number').val('');
        $('#edit_account_holder').val('');
        renderEmployeeSystemInfo(); renderRepresentativeQualification();

        await Promise.all([
            loadSelectOptions('#edit_department_select', API.DEPARTMENT_LIST, ''),
            loadSelectOptions('#edit_position_select', API.POSITION_LIST, ''),
            loadEmployeeJobOptions(''),
            loadSelectOptions('#edit_role_select', API.ROLE_LIST, ''),
            initCodeSelectControls(document.getElementById('employeeEditModal'))
        ]);
        setEmployeeHrFieldMode(true);
        $('#edit_employment_status').val('ACTIVE').trigger('change');

        $('#edit_profile_preview').attr('src', '/public/assets/img/default-avatar.png').attr('data-file-path', '');
        $('#edit_profile_delete_btn').hide();
        $('#edit_profile_image_delete').val('0');
        $('#profile_box').attr('data-label', '\uC5C5\uB85C\uB4DC');

        $('#edit_id_preview').attr('src', '/public/assets/img/placeholder-id.png').attr('data-file-path', '');
        $('#edit_id_delete_btn').hide();
        $('#edit_rrn_image_delete').val('0');
        $('#id_box').attr('data-label', '\uC5C5\uB85C\uB4DC');

        $('#edit_bank_preview').attr('src', '/public/assets/img/placeholder-bank.png').data('file-path', '');
        $('#edit_bank_delete_btn').hide();
        $('#edit_bank_file_delete').val('0');
        $('#bank_box').attr('data-label', '\uC5C5\uB85C\uB4DC');

        $('#edit_soft_delete_btn')
            .text('계정 비활성화')
            .removeClass('btn-success')
            .addClass('btn-warning')
            .attr('data-mode', 'deactivate')
            .hide();

        $('#employeeEditSubmitBtn').show();
        $('#edit_force_delete_btn').hide();
        applyEmployeeModalPolicyLabels(document);
    }

    async function setEmployeeFormData(row) {
        if (!row) return;

        $('#employeeModalTitle').html('<i class="bi bi-pencil-square"></i> 직원 정보 수정');
        $('#employeeEditSubmitBtn').show();
        $('#edit_soft_delete_btn').show();
        $('#edit_force_delete_btn').show();

        $('#edit_profile_image').val('');
        $('#edit_rrn_image').val('');
        $('#edit_bank_file').val('');
        $('#edit_profile_image_delete').val('0');
        $('#edit_rrn_image_delete').val('0');
        $('#edit_bank_file_delete').val('0');

        $('#edit_employee_id').val(row.id || '');
        $('#edit_qualification_education_link').attr('href', '/institution/human-resources/qualification-education?employee_id=' + encodeURIComponent(row.id || ''));
        renderRepresentativeQualification(row);
        $('#edit_employee_username').val(row.username || '');
        $('#edit_employee_name').val(row.employee_name || '');
        $('#edit_employee_phone').val(formatMobile(row.phone || ''));
        $('#edit_employee_emergency_phone').val(formatPhone(row.emergency_phone || ''));
        $('#edit_employee_email').val(row.email || '');
        setRrnField(row.rrn || '');
        $('#edit_employee_address').val(row.address || '');
        $('#edit_employee_address_detail').val(row.address_detail || '');
        $('#edit_doc_hire_date').val(row.doc_hire_date || '');
        $('#edit_real_hire_date').val(row.real_hire_date || '');
        $('#edit_doc_retire_date').val(row.doc_retire_date || '');
        $('#edit_real_retire_date').val(row.real_retire_date || '');
        setEmployeeBankSelectValue(row.bank_name || '');
        $('#edit_account_number').val(row.account_number || '');
        formatEmployeeAccountNumberInput();
        $('#edit_account_holder').val(row.account_holder || '');
        $('#edit_employee_note').val(row.note || '');
        $('#edit_employee_memo').val(row.memo || '');

        $('#edit_two_factor').prop('checked', String(row.two_factor_enabled) === '1');
        $('#edit_email_notify').prop('checked', String(row.email_notify) === '1');
        $('#edit_sms_notify').prop('checked', String(row.sms_notify) === '1');

        await Promise.all([
            loadSelectOptions('#edit_department_select', API.DEPARTMENT_LIST, row.department_id || ''),
            loadSelectOptions('#edit_position_select', API.POSITION_LIST, row.position_id || ''),
            loadEmployeeJobOptions(row.job_id || ''),
            loadSelectOptions('#edit_role_select', API.ROLE_LIST, row.role_id || ''),
            initCodeSelectControls(document.getElementById('employeeEditModal'))
        ]);
        $('#edit_employment_status').val(row.employment_status || '').trigger('change');
        setEmployeeHrFieldMode(false);

        const profileSrc = resolveFileSrc(row.profile_image, '/public/assets/img/default-avatar.png');
        $('#edit_profile_preview').attr('src', profileSrc).attr('data-file-path', row.profile_image || '');
        $('#edit_profile_delete_btn').toggle(!!row.profile_image);
        $('#profile_box').attr('data-label', row.profile_image ? '\uC6D0\uBCF8 \uBCF4\uAE30' : '\uC5C5\uB85C\uB4DC');

        const idSrc = resolveFileSrc(row.rrn_image, '/public/assets/img/placeholder-id.png');
        $('#edit_id_preview').attr('src', idSrc).attr('data-file-path', row.rrn_image || '');
        $('#edit_id_delete_btn').toggle(!!row.rrn_image);
        $('#id_box').attr('data-label', row.rrn_image ? '\uC6D0\uBCF8 \uBCF4\uAE30' : '\uC5C5\uB85C\uB4DC');

        $('#edit_bank_preview').attr('src', getBankPreview(row.bank_file)).data('file-path', row.bank_file || '');
        $('#edit_bank_delete_btn').toggle(!!row.bank_file);
        $('#bank_box').attr('data-label', row.bank_file ? '\uC6D0\uBCF8 \uBCF4\uAE30' : '\uC5C5\uB85C\uB4DC');

        $('#edit_created_at').text(row.user_created_at || '-');
        $('#edit_created_by').text(actorDisplay(row, 'user_created_by'));
        $('#edit_updated_at').text(row.user_updated_at || '-');
        $('#edit_updated_by').text(actorDisplay(row, 'user_updated_by'));
        $('#edit_approved').text(String(row.approved) === '1' ? '\uC2B9\uC778' : '\uBBF8\uC2B9\uC778');
        $('#edit_approved_at').text(row.approved_at || '-');
        $('#edit_approved_by').text(actorDisplay(row, 'approved_by'));
        $('#edit_last_login').text(row.last_login || '-');
        $('#edit_login_fail_count').text(row.login_fail_count || '0');
        $('#edit_account_locked_until').text(row.account_locked_until || '-');
        $('#edit_last_login_ip').html('<div style="font-weight:600;">' + escapeHtml(row.last_login_ip || '-') + '</div>');
        $('#edit_last_login_device').text(row.last_login_device || '-');
        $('#edit_password_updated_at').text(row.password_updated_at || '-');
        $('#edit_password_updated_by').text(actorDisplay(row, 'password_updated_by'));
        renderEmployeeSystemInfo(row);

        $('#edit_is_active').html(
            String(row.is_active) === '1'
                ? '<span class="badge bg-success">\uC0AC\uC6A9</span>'
                : '<span class="badge bg-secondary">\uBBF8\uC0AC\uC6A9</span>'
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
                .text('\uACC4\uC815 \uD65C\uC131\uD654')
                .removeClass('btn-warning')
                .addClass('btn-success')
                .attr('data-mode', 'activate');
        }

        applyEmployeeModalPolicyLabels(document);
    }

    function prepareEmployeeBankControls() {
        if (!employeeBankControlsPromise) {
            employeeBankControlsPromise = Promise.all([
                getCodeOptions('BANK').then((rows) => setBankAccountFormatRegistry?.(rows)),
                initCodeSelectControls(document.getElementById('employeeEditModal'))
            ])
                .then(() => {
                    bindEmployeeBankFormatting();
                })
                .catch((error) => {
                    employeeBankControlsPromise = null;
                    throw error;
                });
        }

        return employeeBankControlsPromise;
    }

    function getEmployeeBankName() {
        const bankSelect = document.getElementById('edit_bank_name');
        if (!bankSelect) return '';

        const selectedText = bankSelect.options?.[bankSelect.selectedIndex]?.textContent || '';
        return [bankSelect.value, selectedText].filter(Boolean).join(' ');
    }

    function formatEmployeeAccountNumberInput(options = {}) {
        const input = document.getElementById('edit_account_number');
        if (!input) return;

        input.value = formatAccountNumber?.(input.value, getEmployeeBankName()) || input.value;

        void prepareEmployeeBankControls().then(() => {
            input.value = formatAccountNumber?.(input.value, getEmployeeBankName()) || input.value;
        });
    }

    function bindEmployeeBankFormatting() {
        const bankSelect = document.getElementById('edit_bank_name');
        const accountInput = document.getElementById('edit_account_number');
        if (!bankSelect || !accountInput) return;

        if (accountInput.dataset.employeeAccountFormatBound !== 'true') {
            accountInput.addEventListener('input', () => formatEmployeeAccountNumberInput());
            accountInput.addEventListener('blur', () => formatEmployeeAccountNumberInput());
            accountInput.dataset.employeeAccountFormatBound = 'true';
        }

        const reformat = () => {
            formatEmployeeAccountNumberInput({ forceReload: true });
            window.requestAnimationFrame?.(() => formatEmployeeAccountNumberInput());
        };

        if (bankSelect.dataset.employeeBankFormatBound !== 'true') {
            bankSelect.addEventListener('change', reformat);
            bankSelect.dataset.employeeBankFormatBound = 'true';
        }

        if (window.jQuery?.fn?.select2) {
            window.jQuery(bankSelect)
                .off('select2:select.employeeBankFormat select2:clear.employeeBankFormat')
                .on('select2:select.employeeBankFormat select2:clear.employeeBankFormat', reformat);
        }
    }

    function setEmployeeBankSelectValue(value = '') {
        const select = document.getElementById('edit_bank_name');
        if (!select) return;

        const raw = String(value ?? '').trim();
        const normalized = raw.toUpperCase();
        const option = Array.from(select.options || []).find((item) => {
            const optionValue = String(item.value ?? '').trim();
            const optionText = String(item.textContent ?? '').trim();
            return optionValue === raw || optionValue.toUpperCase() === normalized || optionText === raw || optionText.toUpperCase() === normalized;
        });

        select.value = option?.value ?? raw;
        if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).trigger('change.select2');
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

    async function getEmployeeSelectItems(apiUrl, method = 'GET') {
        const cacheKey = `${String(method).toUpperCase()}:${apiUrl}`;
        if (employeeSelectOptionsCache.has(cacheKey)) {
            return employeeSelectOptionsCache.get(cacheKey);
        }

        const promise = (async () => {
            const fetchOptions = {
                method,
                credentials: 'include'
            };

            if (String(method).toUpperCase() !== 'GET') {
                fetchOptions.body = new FormData();
            }

            const res = await fetch(apiUrl, fetchOptions);
            const text = await res.text();
            let json;

            try {
                json = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse failed:', apiUrl, text);
                return [];
            }

            const list = Array.isArray(json.data) ? json.data : [];
            const items = [];

            list.forEach((row) => {
                const id = row.user_id ?? row.department_id ?? row.position_id ?? row.role_id ?? row.id ?? Object.values(row)[0];
                const text = row.name ?? row.dept_name ?? row.department_name ?? row.position_name ?? row.role_name ?? row.label ?? Object.values(row)[1] ?? '';

                if (id !== undefined && id !== null && String(id) !== '') {
                    items.push({ id: String(id), text: String(text ?? ''), is_active: row.is_active });
                }
            });

            return items;
        })().catch((error) => {
            employeeSelectOptionsCache.delete(cacheKey);
            throw error;
        });

        employeeSelectOptionsCache.set(cacheKey, promise);
        return promise;
    }

    function setEmployeeHrFieldMode(isCreate) {
        const selectors = [
            '#edit_department_select', '#edit_position_select', '#edit_job_select', '#edit_employment_status',
            '#edit_doc_hire_date', '#edit_real_hire_date', '#edit_doc_retire_date', '#edit_real_retire_date'
        ];
        selectors.forEach((selector) => $(selector).prop('disabled', !isCreate));
        $('.employee-hr-edit-notice').toggleClass('d-none', isCreate);
    }

    async function loadEmployeeJobOptions(selectedValue = '') {
        const response = await fetch(API.PERSONNEL_OPTIONS, { headers: { Accept: 'application/json' } });
        const json = await response.json();
        if (!response.ok || json.success === false) throw new Error(json.message || '직무 목록을 불러오지 못했습니다.');
        const jobs = Array.isArray(json?.data?.jobs) ? json.data.jobs : [];
        const items = jobs.map((row) => ({ id: row.id, text: row.label || row.job_name || row.text || row.id }));
        AdminPicker.destroySelect2('#edit_job_select');
        AdminPicker.reloadSelect2('#edit_job_select', items, 'id', 'text', null);
        AdminPicker.select2('#edit_job_select', { allowClear: true, width: '100%', dropdownParent: $('#employeeEditModal') });
        $('#edit_job_select').val(String(selectedValue || '')).trigger('change');
    }

    async function loadSelectOptions(selector, apiUrl, selectedValue = '', method = 'GET') {
        selectedValue = selectedValue != null ? String(selectedValue) : '';

        try {
            const loadedItems = await getEmployeeSelectItems(apiUrl, method);
            const items = apiUrl === API.ROLE_LIST
                ? loadedItems.filter((item) => String(item.is_active) === '1' || String(item.id) === selectedValue)
                : loadedItems;

            AdminPicker.destroySelect2(selector);
            AdminPicker.reloadSelect2(selector, items, 'id', 'text', null);
            AdminPicker.select2(selector, {
                allowClear: true,
                width: '100%',
                dropdownParent: $('#employeeEditModal')
            });

            if (selectedValue) {
                const hasOption = items.some(item => String(item.id) === String(selectedValue));
                if (!hasOption) {
                    const el = document.querySelector(selector);
                    if (el) el.append(new Option('(이름 없음)', selectedValue, false, false));
                }
                AdminPicker.setSelect2Value(selector, selectedValue, true);
            } else {
                AdminPicker.clearSelect2(selector, true);
            }
        } catch (err) {
            console.error('Select Load Error:', selector, err);
        }
    }

    function bindInputGuide($) {
        let shown = {};

        function notifyOnce(key, message) {
            if (shown[key]) return;
            shown[key] = true;

            if (window.AppCore?.notify) {
                AppCore.notify('info', message);
            } else {
            }
        }

        $(document)
            .off('focus.employeeUsername')
            .on('focus.employeeUsername', '#edit_employee_username', function () {
                notifyOnce('username', '아이디는 영문/숫자 조합으로 입력하세요.');
            });

        $(document)
            .off('focus.employeeRrn')
            .on('focus.employeeRrn', '#edit_employee_rrn', function () {
                notifyOnce('rrn', '주민등록번호는 암호화되어 저장됩니다.');
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
            .off('focus.employeeRrnReveal', '#edit_employee_rrn')
            .on('focus.employeeRrnReveal', '#edit_employee_rrn', function () {
                if (!rrnVisible && String($(this).val() || '').includes('*')) {
                    setRrnField(getRrnRealValue($(this)), true);
                }
            });

        $(document)
            .off('input.employeeRrnFormat', '#edit_employee_rrn')
            .on('input.employeeRrnFormat', '#edit_employee_rrn', function () {
                const $input = $(this);
                const raw = onlyNumber($input.val()).slice(0, 13);

                $input.data('real', raw);
                $input.val(formatCorpNumber(raw));
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
                const realVal = getRrnRealValue($input);
                const nextVisible = !rrnVisible;
                setRrnField(realVal, nextVisible);
                if (nextVisible) {
                    $input.trigger('focus');
                }
            });
    }

    function setRrnField(value, visible = false) {
        const $input = $('#edit_employee_rrn');
        const raw = onlyNumber(value).slice(0, 13);

        rrnVisible = !!visible;
        $input
            .attr('type', 'text')
            .data('real', raw)
            .val(rrnVisible ? formatCorpNumber(raw) : maskRrn(raw));

        $('.toggle-rrn i')
            .toggleClass('bi-eye', !rrnVisible)
            .toggleClass('bi-eye-slash', rrnVisible);
    }

    function getRrnRealValue($input) {
        const currentValue = String($input.val() || '');
        const storedValue = onlyNumber($input.data('real') || '').slice(0, 13);

        if (currentValue.includes('*')) {
            return storedValue;
        }

        return onlyNumber(currentValue).slice(0, 13);
    }

    function maskRrn(rrn) {
        if (!rrn) return '';

        const clean = String(rrn).replace(/\D/g, '').slice(0, 13);
        if (clean.length <= 6) return clean;
        if (clean.length < 13) return clean.substring(0, 6) + '-' + clean.substring(6);

        return clean.substring(0, 6) + '-' + clean.substring(6, 7) + '******';
    }

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
