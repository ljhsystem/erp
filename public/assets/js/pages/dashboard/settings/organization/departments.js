// 경로: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/departments.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
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

    console.log('[departments.js] loaded');

    const API = {
        LIST: '/api/settings/organization/department/list',
        SAVE: '/api/settings/organization/department/save',
        REORDER: '/api/settings/organization/department/reorder',
        EMPLOYEE_LIST: '/api/settings/organization/employee/list'
    };

    const DEPARTMENT_COLUMN_MAP = {
        sort_no:         { label: '순번', visible: true },
        dept_name:    { label: '부서명', visible: true },
        manager_name: { label: '부서장', visible: true },
        description:  { label: '설명', visible: true },
        is_active:    { label: '상태', visible: true, noVis: true },
        created_at:   { label: '등록일자', visible: false },
        updated_at:   { label: '수정일자', visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '등록일자' },
        { value: 'updated_at', label: '수정일자' }
    ];

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
        initAdminDatePicker();
        bindAdminDateInputs();
        initDataTable($);
        bindRowReorder(departmentTable, { api: API.REORDER });
        bindTableEvents($);
        bindModalEvents($);
        bindTableLayoutEvents(departmentTable, '#department-table');
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
                    text: '새 부서',
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

            updateTableHeight(departmentTable, '#department-table');
            bindTableHighlight('#department-table', departmentTable);

            departmentTable.on('draw', updateDepartmentCountFromTable);
            updateDepartmentCountFromTable();
        }
    }

    function buildDepartmentColumns() {
        const columns = [];

        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            width: '40px',
            className: 'reorder-handle no-colvis text-center',
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>'
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
                            ? '<span class="badge bg-success">활성</span>'
                            : '<span class="badge bg-secondary">비활성</span>';
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

        $('#department-table tbody')
            .off('click.departmentCellSearch', 'td')
            .on('click.departmentCellSearch', 'td', function () {
                const cell = departmentTable.cell(this);
                const idx = cell.index();
                if (!idx) return;

                const field = departmentTable.column(idx.column).dataSrc();
                if (!field || field === 'is_active') return;

                const value = cell.data();
                const $first = $('#departmentSearchConditions .search-condition').first();
                $first.find('select').val(field);
                $first.find('input').val(stripHtml(String(value ?? '')).trim());
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
            .text('\uC601\uAD6C\uC0AD\uC81C')
            .toggle(!isCreate);
    }

    function resetDepartmentForm() {
        const form = document.getElementById('dept-edit-form');
        form?.reset();

        $('#dept_edit_id').val('');
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
        fd.set('is_active', $('#dept_edit_is_active').is(':checked') ? '1' : '0');

        try {
            const res = await fetch(API.SAVE, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const json = await res.json();

            if (!json?.success) {
                notify('error', json?.message === 'duplicate' ? '이미 등록된 부서명입니다.' : (json?.message || '저장 실패'));
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
        fd.append('action', 'delete');
        fd.append('id', id);

        try {
            const res = await fetch(API.SAVE, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const json = await res.json();

            if (!json?.success) {
                notify('error', json?.message || '삭제 실패');
                return;
            }

            notify('success', '삭제되었습니다.');
            departmentModal?.hide();
            reloadDepartmentTable();
        } catch (err) {
            console.error('[departments.js] delete failed:', err);
            notify('error', '삭제 중 오류가 발생했습니다.');
        }
    }

    async function loadManagerOptions(selectedValue = '') {
        const select = document.getElementById('dept_edit_manager_id');
        if (!select) return;

        select.innerHTML = '<option value="">선택</option>';

        try {
            const res = await fetch(API.EMPLOYEE_LIST, {
                method: 'GET',
                credentials: 'include'
            });
            const json = await res.json();
            const rows = Array.isArray(json?.data) ? json.data : [];

            rows.forEach(row => {
                if (!row.user_id) return;

                const option = new Option(
                    row.employee_name || row.username || row.user_id,
                    row.user_id
                );
                select.add(option);
            });

            if (selectedValue) {
                select.value = String(selectedValue);
            }
        } catch (err) {
            console.error('[departments.js] manager load failed:', err);
        }
    }

    function reloadDepartmentTable() {
        departmentTable?.ajax.reload(() => {
            updateDepartmentCountFromTable();
            forceTableHeightSync(departmentTable, '#department-table');
        }, false);
    }

    function updateDepartmentCountFromTable() {
        if (!departmentTable?.page) return;

        const info = departmentTable.page.info();
        const el = document.getElementById('departmentCount');
        if (el) {
            el.textContent = `총 ${info?.recordsDisplay ?? 0}건`;
        }
    }

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
