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

    console.log('[permissions.js] loaded');

    const API = {
        LIST: '/api/settings/organization/permission/list',
        SAVE: '/api/settings/organization/permission/save',
        REORDER: '/api/settings/organization/permission/reorder'
    };

    const PERMISSION_COLUMN_MAP = {
        sort_no:            { label: '?쒕쾲', visible: true },
        category:        { label: '移댄뀒怨좊━', visible: true },
        permission_name: { label: '?쇰??섎챸', visible: true },
        permission_key:  { label: '?쇰??섑궎', visible: true },
        is_active:       { label: '?곹깭', visible: true, noVis: true },
        description:     { label: '?ㅻ챸', visible: false },
        created_at:      { label: '?깅줉?쇱옄', visible: false },
        updated_at:      { label: '?섏젙?쇱옄', visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '?깅줉?쇱옄' },
        { value: 'updated_at', label: '?섏젙?쇱옄' }
    ];

    let permissionTable = null;
    let permissionModal = null;
    let todayPicker = null;
    let globalBound = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('[permissions.js] jQuery not loaded');
            return;
        }

        initPermissionPage(window.jQuery);
    });

    function initPermissionPage($) {
        initModal();
        initAdminDatePicker();
        bindAdminDateInputs();
        initDataTable($);
        bindRowReorder(permissionTable, { api: API.REORDER });
        bindTableEvents($);
        bindModalEvents($);
        bindGlobalEvents();
    }

    function initModal() {
        const modalEl = document.getElementById('permissionEditModal');
        if (!modalEl) return;

        permissionModal = new bootstrap.Modal(modalEl, { focus: false });

        modalEl.addEventListener('shown.bs.modal', () => {
            document.getElementById('permission_edit_name')?.focus();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            resetPermissionForm();
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
            if (input.__permissionDateBound) return;
            input.__permissionDateBound = true;

            input.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                openDatePicker(input);
            });
        });

        document.querySelectorAll('.date-icon').forEach(icon => {
            if (icon.__permissionDateBound) return;
            icon.__permissionDateBound = true;

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
        const columns = buildPermissionColumns();

        permissionTable = createDataTable({
            tableSelector: '#permission-table',
            api: API.LIST,
            columns,
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            buttons: [
                {
                    text: '??沅뚰븳',
                    className: 'btn btn-primary btn-sm',
                    action: function () {
                        openCreateModal();
                    }
                }
            ]
        });

        window.PermissionTable = permissionTable;
        window.EmployeePermissionsTable = {
            instance: permissionTable,
            reload: () => permissionTable?.ajax.reload(null, false)
        };

        if (permissionTable) {
            SearchForm({
                table: permissionTable,
                apiList: API.LIST,
                tableId: 'permission',
                defaultSearchField: 'permission_name',
                dateOptions: DATE_OPTIONS
            });
            bindTableHighlight('#permission-table', permissionTable);

            permissionTable.on('draw', updatePermissionCountFromTable);
            updatePermissionCountFromTable();
        }
    }

    function buildPermissionColumns() {
        const columns = [];

        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'reorder-handle no-colvis text-center',
            orderable: false,
            searchable: false,
            render: () => '<i class="bi bi-list"></i>'
        });

        Object.entries(PERMISSION_COLUMN_MAP).forEach(([field, config]) => {
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
                            ? '<span class="badge bg-success">?쒖꽦</span>'
                            : '<span class="badge bg-secondary">鍮꾪솢??/span>';
                    }

                    return escapeHtml(data);
                }
            });
        });

        return columns;
    }

    function bindTableEvents($) {
        $('#permission-table tbody')
            .off('dblclick.permissionEdit', 'tr')
            .on('dblclick.permissionEdit', 'tr', function () {
                const data = permissionTable.row(this).data();
                if (data) openEditModal(data);
            });

        $('#permission-table tbody')
            .off('click.permissionCellSearch', 'td')
            .on('click.permissionCellSearch', 'td', function () {
                const cell = permissionTable.cell(this);
                const idx = cell.index();
                if (!idx) return;

                const field = permissionTable.column(idx.column).dataSrc();
                if (!field || field === 'is_active') return;

                const value = cell.data();
                const $first = $('#permissionSearchConditions .search-condition').first();
                $first.find('select').val(field);
                $first.find('input').val(stripHtml(String(value ?? '')).trim());
            });
    }

    function bindModalEvents($) {
        $(document)
            .off('submit.permissionForm', '#permission-edit-form')
            .on('submit.permissionForm', '#permission-edit-form', async function (e) {
                e.preventDefault();
                await savePermission();
            });

        $(document)
            .off('click.permissionDelete', '#permission_edit_delete_btn')
            .on('click.permissionDelete', '#permission_edit_delete_btn', async function (e) {
                e.preventDefault();
                e.stopPropagation();

                const id = $('#permission_edit_id').val();
                if (!id) return;
                if (!confirm('沅뚰븳???곴뎄??젣?섏떆寃좎뒿?덇퉴?')) return;

                await deletePermission(id);
            });
    }

    function openCreateModal() {
        resetPermissionForm();
        setPermissionModalMode('create');
        permissionModal?.show();
    }

    function openEditModal(row) {
        resetPermissionForm();
        setPermissionModalMode('edit');

        $('#permission_edit_id').val(row.id || '');
        $('#permission_edit_name').val(row.permission_name || '');
        $('#permission_edit_category').val(row.category || '');
        $('#permission_edit_key').val(row.permission_key || '');
        $('#permission_edit_description').val(row.description || '');
        $('#permission_edit_is_active').prop('checked', String(row.is_active) === '1');

        permissionModal?.show();
    }

    function setPermissionModalMode(mode) {
        const isCreate = mode === 'create';
        $('#permissionEditModal .modal-title').text(isCreate ? '沅뚰븳 ?깅줉' : '沅뚰븳 ?섏젙');
        $('#permission_edit_delete_btn')
            .text('?곴뎄??젣')
            .toggle(!isCreate);
    }

    function resetPermissionForm() {
        const form = document.getElementById('permission-edit-form');
        form?.reset();

        $('#permission_edit_id').val('');
        $('#permission_edit_is_active').prop('checked', true);
        setPermissionModalMode('create');
    }

    async function savePermission() {
        const id = $('#permission_edit_id').val();
        const permissionName = String($('#permission_edit_name').val() || '').trim();
        const permissionKey = String($('#permission_edit_key').val() || '').trim();

        if (!permissionName || !permissionKey) {
            notify('warning', '?쇰??섎챸怨??쇰??섑궎瑜??낅젰?섏꽭??');
            return;
        }

        const fd = new FormData(document.getElementById('permission-edit-form'));
        fd.set('action', id ? 'update' : 'create');
        fd.set('is_active', $('#permission_edit_is_active').is(':checked') ? '1' : '0');

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

            notify('success', '??λ릺?덉뒿?덈떎.');
            permissionModal?.hide();
            reloadPermissionTable();
        } catch (err) {
            console.error('[permissions.js] save failed:', err);
            notify('error', '???以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.');
        }
    }

    async function deletePermission(id) {
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
                notify('error', json?.message || '??젣 ?ㅽ뙣');
                return;
            }

            notify('success', '??젣?섏뿀?듬땲??');
            permissionModal?.hide();
            reloadPermissionTable();
        } catch (err) {
            console.error('[permissions.js] delete failed:', err);
            notify('error', '??젣 以??ㅻ쪟媛 諛쒖깮?덉뒿?덈떎.');
        }
    }

    function resolveSaveMessage(message) {
        if (message === 'duplicate_key') return '?대? ?깅줉???쇰??섑궎?낅땲??';
        if (message === 'duplicate') return '?대? ?깅줉???쇰??섑궎?낅땲??';
        return message || '????ㅽ뙣';
    }

    function reloadPermissionTable() {
        permissionTable?.ajax.reload(() => {
            updatePermissionCountFromTable();
        }, false);
    }

    function updatePermissionCountFromTable() {
        if (!permissionTable?.page) return;

        const info = permissionTable.page.info();
        const el = document.getElementById('permissionCount');
        if (el) {
            el.textContent = `珥?${info?.recordsDisplay ?? 0}嫄?;
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
        const start = document.querySelector('#permissionSearchConditionsForm input[name="dateStart"]');
        const end = document.querySelector('#permissionSearchConditionsForm input[name="dateEnd"]');

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



