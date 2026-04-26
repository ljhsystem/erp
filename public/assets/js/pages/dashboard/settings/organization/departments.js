// 寃쎈줈: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/departments.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createDataTable,
    bindTableHighlight
} from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    console.log('[departments.js] loaded');

    const API = {
        LIST: '/api/settings/organization/department/list',
        SAVE: '/api/settings/organization/department/save',
        DELETE: '/api/settings/organization/department/delete',
        REORDER: '/api/settings/organization/department/reorder',
        EMPLOYEE_LIST: '/api/settings/organization/employee/list'
    };

    const DEPARTMENT_COLUMN_MAP = {
        sort_no:      { label: '\uC21C\uBC88', visible: true },
        dept_name:    { label: '\uBD80\uC11C\uBA85', visible: true },
        manager_id:   { label: '\uBD80\uC11C\uC7A5ID', visible: false },
        manager_name: { label: '\uBD80\uC11C\uC7A5', visible: true },
        description:  { label: '\uC124\uBA85', visible: true },
        is_active:    { label: '\uC0C1\uD0DC', visible: true },
        created_at:   { label: '\uC0DD\uC131\uC77C\uC2DC', visible: false },
        created_by:   { label: '\uC0DD\uC131\uC790', visible: false },
        updated_at:   { label: '\uC218\uC815\uC77C\uC2DC', visible: false },
        updated_by:   { label: '\uC218\uC815\uC790', visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '\uC0DD\uC131\uC77C\uC2DC' },
        { value: 'updated_at', label: '\uC218\uC815\uC77C\uC2DC' }
    ];

    const MANAGER_NONE_VALUE = '__NONE__';

    let departmentTable = null;
    let departmentModal = null;
    let todayPicker = null;
    let globalBound = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('[departments.js] jQuery not loaded');
            return;
        }

        initDepartmentPage(window.jQuery);
    });

    function initDepartmentPage($) {
        initModal();
        initDataTable($);
        bindRowReorder(departmentTable, {
            api: API.REORDER,
            onSuccess() {
                notify('success', '부서 순번이 저장되었습니다.');
                departmentTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '부서 순번 저장에 실패했습니다.');
                departmentTable?.ajax.reload(null, false);
            }
        });
        bindTableEvents($);
        bindModalEvents($);
        bindGlobalEvents();
    }

    function initModal() {
        const modalEl = document.getElementById('deptEditModal');
        if (!modalEl) return;

        departmentModal = new bootstrap.Modal(modalEl, { focus: false });

        modalEl.addEventListener('shown.bs.modal', () => {
            const first = document.getElementById('dept_edit_name');
            first?.focus();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            resetDepartmentForm();
        });
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
            normalizeStartEnd(input.name === 'dateStart' ? 'start' : 'end');
            todayPicker.close();
        });

        return todayPicker;
    }

    function bindAdminDateInputs() {
        document.querySelectorAll('.admin-date').forEach(input => {
            if (input.__departmentDateBound) return;
            input.__departmentDateBound = true;

            input.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                openDatePicker(input);
            });
        });

        document.querySelectorAll('.date-icon').forEach(icon => {
            if (icon.__departmentDateBound) return;
            icon.__departmentDateBound = true;

            icon.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();

                const wrap = icon.closest('.date-input, .date-input-wrap');
                const input = wrap ? wrap.querySelector('input') : null;
                if (input) openDatePicker(input);
            });
        });
    }

    function openDatePicker(input) {
        const picker = initAdminDatePicker();
        if (!picker) return;

        picker.__target = input;

        if (typeof picker.clearDate === 'function') {
            picker.clearDate();
        }

        const value = input.value;
        if (value) {
            const date = new Date(value);
            if (!Number.isNaN(date.getTime())) {
                picker.setDate(date);
            }
        }

        picker.open({ anchor: input });
    }

    function initDataTable($) {
        const columns = buildDepartmentColumns();

        departmentTable = createDataTable({
            tableSelector: '#department-table',
            api: API.LIST,
            columns,
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            buttons: [
                {
                    text: '\uC0C8 \uBD80\uC11C',
                    className: 'btn btn-primary btn-sm',
                    action: function () {
                        openCreateModal();
                    }
                }
            ]
        });

        window.DepartmentTable = departmentTable;
        window.EmployeeDepartmentsTable = {
            instance: departmentTable,
            reload: () => departmentTable?.ajax.reload(null, false)
        };

        if (departmentTable) {
            SearchForm({
                table: departmentTable,
                apiList: API.LIST,
                tableId: 'department',
                defaultSearchField: 'dept_name',
                dateOptions: DATE_OPTIONS
            });
            bindTableHighlight('#department-table', departmentTable);

            departmentTable.on('draw', updateDepartmentCountFromTable);
            updateDepartmentCountFromTable();
        }
    }

    function buildDepartmentColumns() {
        const columns = [];

        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'reorder-handle no-sort no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>'
        });



        Object.entries(DEPARTMENT_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                className: config.noVis ? 'noVis text-center' : '',
                defaultContent: '',
                render: function (data, type) {
                    if (data == null) return '';
                    if (type !== 'display') return data;

                    if (field === 'is_active') {
                        return String(data) === '1'
                            ? '<span class="badge bg-success">\uC0AC\uC6A9</span>'
                            : '<span class="badge bg-secondary">\uBBF8\uC0AC\uC6A9</span>';
                    }

                    return escapeHtml(data);
                }
            });
        });

        return columns;
    }

    function bindTableEvents($) {
        $('#department-table tbody')
            .off('dblclick.departmentEdit', 'tr')
            .on('dblclick.departmentEdit', 'tr', function () {
                const data = departmentTable.row(this).data();
                if (data) openEditModal(data);
            });

    }

    function bindModalEvents($) {
        $(document)
            .off('submit.departmentForm', '#dept-edit-form')
            .on('submit.departmentForm', '#dept-edit-form', async function (e) {
                e.preventDefault();
                await saveDepartment();
            });

        $(document)
            .off('click.departmentDelete', '#dept_edit_delete_btn')
            .on('click.departmentDelete', '#dept_edit_delete_btn', async function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (e.currentTarget?.id !== 'dept_edit_delete_btn') return;

                const id = $('#dept_edit_id').val();
                if (!id) return;
                if (!confirm('\uBD80\uC11C\uB97C \uC601\uAD6C\uC0AD\uC81C\uD558\uC2DC\uACA0\uC2B5\uB2C8\uAE4C?')) return;

                await deleteDepartment(id);
            });
    }

    async function openCreateModal() {
        resetDepartmentForm();
        setDepartmentModalMode('create');
        await loadManagerOptions('');
        departmentModal?.show();
    }

    async function openEditModal(row) {
        resetDepartmentForm();
        setDepartmentModalMode('edit');

        $('#dept_edit_id').val(row.id || '');
        $('#dept_edit_name').val(row.dept_name || '');
        $('#dept_edit_description').val(row.description || '');
        $('#dept_edit_is_active').prop('checked', String(row.is_active) === '1');

        await loadManagerOptions(row.manager_id || '');
        departmentModal?.show();
    }

    function setDepartmentModalMode(mode) {
        const isCreate = mode === 'create';
        $('#deptEditModal .modal-title').text(isCreate ? '부서 등록' : '부서 수정');
        $('#dept_edit_delete_btn')
            .text('영구삭제')
            .toggle(!isCreate);
    }

    function resetDepartmentForm() {
        const form = document.getElementById('dept-edit-form');
        form?.reset();

        $('#dept_edit_id').val('');
        AdminPicker.clearSelect2('#dept_edit_manager_id', false);
        AdminPicker.destroySelect2('#dept_edit_manager_id');
        $('#dept_edit_manager_id').empty();
        $('#dept_edit_is_active').prop('checked', true);
        setDepartmentModalMode('create');
    }

    async function saveDepartment() {
        const id = $('#dept_edit_id').val();
        const deptName = String($('#dept_edit_name').val() || '').trim();

        if (!deptName) {
            notify('warning', '부서명을 입력하세요.');
            return;
        }

        const fd = new FormData(document.getElementById('dept-edit-form'));
        fd.set('action', id ? 'update' : 'create');
        fd.set('manager_id', normalizeManagerId($('#dept_edit_manager_id').val()));
        fd.set('is_active', $('#dept_edit_is_active').is(':checked') ? '1' : '0');

        try {
            const res = await fetch(API.SAVE, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const json = await res.json();

            if (!json?.success) {
                notify('error', json?.message === 'duplicate' ? '?대? ?깅줉??遺?쒕챸?낅땲??' : (json?.message || '삭제 실패'));
                return;
            }

            notify('success', '저장되었습니다.');
            departmentModal?.hide();
            reloadDepartmentTable();
        } catch (err) {
            console.error('[departments.js] save failed:', err);
            notify('error', '저장 중 오류가 발생했습니다.');
        }
    }

    async function deleteDepartment(id) {
        const fd = new FormData();
        fd.append('id', id);

        try {
            const res = await fetch(API.DELETE, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const json = await res.json();

            if (!json?.success) {
                notify('error', json?.message || '삭제 실패');
                return;
            }

            notify('success', '저장되었습니다.');
            departmentModal?.hide();
            reloadDepartmentTable();
        } catch (err) {
            console.error('[departments.js] delete failed:', err);
            notify('error', '저장 중 오류가 발생했습니다.');
        }
    }

    async function loadManagerOptions(selectedValue = '') {
        const select = document.getElementById('dept_edit_manager_id');
        if (!select) return;

        selectedValue = selectedValue != null ? String(selectedValue) : '';

        try {
            const res = await fetch(API.EMPLOYEE_LIST, {
                method: 'GET',
                credentials: 'include'
            });
            const json = await res.json();
            const rows = Array.isArray(json?.data) ? json.data : [];
            const items = [
                { id: MANAGER_NONE_VALUE, text: '선택(없음)' }
            ];

            rows.forEach(row => {
                if (!row.user_id) return;

                items.push({
                    id: String(row.user_id),
                    text: String(row.employee_name || row.username || row.user_id)
                });
            });

            AdminPicker.destroySelect2(select);
            AdminPicker.reloadSelect2(select, items, 'id', 'text', null);
            AdminPicker.select2(select, {
                placeholder: '선택',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#deptEditModal')
            });

            if (selectedValue) {
                const hasOption = items.some(item => String(item.id) === selectedValue);
                if (!hasOption) {
                    select.append(new Option('(이름 없음)', selectedValue, false, false));
                }
                AdminPicker.setSelect2Value(select, selectedValue, true);
            } else {
                AdminPicker.setSelect2Value(select, MANAGER_NONE_VALUE, true);
            }
        } catch (err) {
            console.error('[departments.js] manager load failed:', err);
        }
    }

    function normalizeManagerId(value) {
        const normalized = String(value ?? '');
        return normalized === MANAGER_NONE_VALUE ? '' : normalized;
    }

    function reloadDepartmentTable() {
        departmentTable?.ajax.reload(() => {
            updateDepartmentCountFromTable();
        }, false);
    }

    function updateDepartmentCountFromTable() {
        if (!departmentTable?.page) return;

        const info = departmentTable.page.info();
        const el = document.getElementById('departmentCount');
        if (el) {
            el.textContent = '\uCD1D ' + (info?.recordsDisplay ?? 0) + '\uAC74';
        }
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
                    todayPicker.close();
                }
            }
        });
    }

    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function normalizeStartEnd(type) {
        const start = document.querySelector('#departmentSearchConditionsForm input[name="dateStart"]');
        const end = document.querySelector('#departmentSearchConditionsForm input[name="dateEnd"]');

        if (!start || !end) return;
        if (!start.value || !end.value) return;

        if (type === 'start' && start.value > end.value) {
            end.value = start.value;
        }

        if (type === 'end' && end.value < start.value) {
            start.value = end.value;
        }
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }

        if (type === 'error' || type === 'warning') {
            alert(message);
            return;
        }

        console.log(message);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function stripHtml(value) {
        const div = document.createElement('div');
        div.innerHTML = value;
        return div.textContent || '';
    }
})();
