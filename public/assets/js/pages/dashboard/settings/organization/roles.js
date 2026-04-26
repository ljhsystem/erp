// 寃쎈줈: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/roles.js'

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

    console.log('[roles.js] loaded');

    const API = {
        LIST: '/api/settings/organization/role/list',
        SAVE: '/api/settings/organization/role/save',
        DELETE: '/api/settings/organization/role/delete',
        REORDER: '/api/settings/organization/role/reorder'
    };

    const ROLE_COLUMN_MAP = {
        sort_no:     { label: '\uC21C\uBC88', visible: true },
        role_key:    { label: 'Role Key', visible: true },
        role_name:   { label: 'Role Name', visible: true },
        description: { label: '\uC124\uBA85', visible: true },
        is_active:   { label: '\uC0C1\uD0DC', visible: true },
        created_at:  { label: '\uC0DD\uC131\uC77C\uC2DC', visible: false },
        created_by:  { label: '\uC0DD\uC131\uC790', visible: false },
        updated_at:  { label: '\uC218\uC815\uC77C\uC2DC', visible: false },
        updated_by:  { label: '\uC218\uC815\uC790', visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '\uC0DD\uC131\uC77C\uC2DC' },
        { value: 'updated_at', label: '\uC218\uC815\uC77C\uC2DC' }
    ];

    let roleTable = null;
    let roleModal = null;
    let todayPicker = null;
    let globalBound = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('[roles.js] jQuery not loaded');
            return;
        }

        initRolePage(window.jQuery);
    });

    function initRolePage($) {
        initModal();
        initDataTable($);
        bindRowReorder(roleTable, {
            api: API.REORDER,
            onSuccess() {
                notify('success', '역할 순번이 저장되었습니다.');
                roleTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '역할 순번 저장에 실패했습니다.');
                roleTable?.ajax.reload(null, false);
            }
        });
        bindTableEvents($);
        bindModalEvents($);
        bindGlobalEvents();
    }

    function initModal() {
        const modalEl = document.getElementById('roleEditModal');
        if (!modalEl) return;

        roleModal = new bootstrap.Modal(modalEl, { focus: false });

        modalEl.addEventListener('shown.bs.modal', () => {
            document.getElementById('role_edit_key')?.focus();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            resetRoleForm();
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
            if (input.__roleDateBound) return;
            input.__roleDateBound = true;

            input.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                openDatePicker(input);
            });
        });

        document.querySelectorAll('.date-icon').forEach(icon => {
            if (icon.__roleDateBound) return;
            icon.__roleDateBound = true;

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
        const columns = buildRoleColumns();

        roleTable = createDataTable({
            tableSelector: '#role-table',
            api: API.LIST,
            columns,
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            buttons: [
                {
                    text: '\uC0C8 \uC5ED\uD560',
                    className: 'btn btn-primary btn-sm',
                    action: function () {
                        openCreateModal();
                    }
                }
            ]
        });

        window.RoleTable = roleTable;
        window.EmployeeRolesTable = {
            instance: roleTable,
            reload: () => roleTable?.ajax.reload(null, false)
        };

        if (roleTable) {
            SearchForm({
                table: roleTable,
                apiList: API.LIST,
                tableId: 'role',
                defaultSearchField: 'role_name',
                dateOptions: DATE_OPTIONS
            });
            bindTableHighlight('#role-table', roleTable);

            roleTable.on('draw', updateRoleCountFromTable);
            updateRoleCountFromTable();
        }
    }

    function buildRoleColumns() {
        const columns = [];

        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'reorder-handle no-sort no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>'
        });



        Object.entries(ROLE_COLUMN_MAP).forEach(([field, config]) => {
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
        $('#role-table tbody')
            .off('dblclick.roleEdit', 'tr')
            .on('dblclick.roleEdit', 'tr', function () {
                const data = roleTable.row(this).data();
                if (data) openEditModal(data);
            });

    }

    function bindModalEvents($) {
        $(document)
            .off('submit.roleForm', '#role-edit-form')
            .on('submit.roleForm', '#role-edit-form', async function (e) {
                e.preventDefault();
                await saveRole();
            });

        $(document)
            .off('click.roleDelete', '#role_edit_delete_btn')
            .on('click.roleDelete', '#role_edit_delete_btn', async function (e) {
                e.preventDefault();
                e.stopPropagation();

                const id = $('#role_edit_id').val();
                if (!id) return;
                if (!confirm('\uC5ED\uD560\uC744 \uC601\uAD6C\uC0AD\uC81C\uD558\uC2DC\uACA0\uC2B5\uB2C8\uAE4C?')) return;

                await deleteRole(id);
            });
    }

    function openCreateModal() {
        resetRoleForm();
        setRoleModalMode('create');
        roleModal?.show();
    }

    function openEditModal(row) {
        resetRoleForm();
        setRoleModalMode('edit');

        $('#role_edit_id').val(row.id || '');
        $('#role_edit_key').val(row.role_key || '');
        $('#role_edit_name').val(row.role_name || '');
        $('#role_edit_description').val(row.description || '');
        $('#role_edit_is_active').prop('checked', String(row.is_active) === '1');

        roleModal?.show();
    }

    function setRoleModalMode(mode) {
        const isCreate = mode === 'create';
        $('#roleEditModal .modal-title').text(isCreate ? '역할 등록' : '역할 수정');
        $('#role_edit_delete_btn')
            .text('영구 삭제')
            .toggle(!isCreate);
        $('#role_edit_delete_btn').text('영구삭제');
    }

    function resetRoleForm() {
        const form = document.getElementById('role-edit-form');
        form?.reset();

        $('#role_edit_id').val('');
        $('#role_edit_is_active').prop('checked', true);
        setRoleModalMode('create');
    }

    async function saveRole() {
        const id = $('#role_edit_id').val();
        const roleKey = String($('#role_edit_key').val() || '').trim();
        const roleName = String($('#role_edit_name').val() || '').trim();

        if (!roleKey || !roleName) {
            notify('warning', 'Role Key와 Role Name을 입력하세요.');
            return;
        }

        const fd = new FormData(document.getElementById('role-edit-form'));
        fd.set('action', id ? 'update' : 'create');
        fd.set('is_active', $('#role_edit_is_active').is(':checked') ? '1' : '0');

        try {
            const res = await fetch(API.SAVE, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const json = await res.json();

            if (!json?.success) {
                notify('error', resolveSaveMessage(json?.message));
                return;
            }

            notify('success', '저장되었습니다.');
            roleModal?.hide();
            reloadRoleTable();
        } catch (err) {
            console.error('[roles.js] save failed:', err);
            notify('error', '저장 중 오류가 발생했습니다.');
        }
    }

    async function deleteRole(id) {
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
            roleModal?.hide();
            reloadRoleTable();
        } catch (err) {
            console.error('[roles.js] delete failed:', err);
            notify('error', '저장 중 오류가 발생했습니다.');
        }
    }

    function resolveSaveMessage(message) {
        if (message === 'duplicate_key') return '이미 등록된 Role Key입니다.';
        if (message === 'duplicate') return '이미 등록된 Role Key입니다.';
        return message || '저장 실패';
    }

    function reloadRoleTable() {
        roleTable?.ajax.reload(() => {
            updateRoleCountFromTable();
        }, false);
    }

    function updateRoleCountFromTable() {
        if (!roleTable?.page) return;

        const info = roleTable.page.info();
        const el = document.getElementById('roleCount');
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
        const start = document.querySelector('#roleSearchConditionsForm input[name="dateStart"]');
        const end = document.querySelector('#roleSearchConditionsForm input[name="dateEnd"]');

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
