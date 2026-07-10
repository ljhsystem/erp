// ?롪퍔?δ빳? PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/roles.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createDataTable,
    bindTableHighlight
} from '/public/assets/js/common/table/data-table.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import { writeSystemUserSettingsStorage } from '/public/assets/js/common/user-settings/systemUserSettingsStorage.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    const API = {
        LIST: '/api/settings/organization/role/list',
        SAVE: '/api/settings/organization/role/save',
        DELETE: '/api/settings/organization/role/delete',
        REORDER: '/api/settings/organization/role/reorder'
    };

    const ROLE_COLUMN_MAP = {
        sort_no:     { label: '\uC21C\uBC88', visible: true },
        role_key:    { label: '\uC5ED\uD560 \uD0A4', visible: true },
        role_name:   { label: '\uC5ED\uD560\uBA85', visible: true },
        description: { label: '\uC124\uBA85', visible: true },
        is_active:   { label: '\uC0C1\uD0DC', visible: true },
        created_at:  { label: '\uC0DD\uC131\uC77C\uC2DC', visible: false },
        created_by:  { label: '\uC0DD\uC131\uC790', visible: false },
        updated_at:  { label: '\uC218\uC815\uC77C\uC2DC', visible: false },
        updated_by:  { label: '\uC218\uC815\uC790', visible: false }
    };

    const ROLE_COLUMN_WIDTHS = {
        __reorder: '40px',
        sort_no: '80px',
        role_key: '160px',
        role_name: '180px',
        description: '260px',
        is_active: '90px',
        created_at: '160px',
        created_by: '120px',
        updated_at: '160px',
        updated_by: '120px',
        __actions: '90px'
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '\uC0DD\uC131\uC77C\uC2DC' },
        { value: 'updated_at', label: '\uC218\uC815\uC77C\uC2DC' }
    ];

    const ROLE_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.dashboard.settings.organization.role.role-table.v1';
    const ROLE_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#role_edit_key', key: 'role_key', fallback: '\uC5ED\uD560 \uD0A4' },
        { selector: '#role_edit_name', key: 'role_name', fallback: '\uC5ED\uD560\uBA85' },
        { selector: '#role_edit_description', key: 'description', fallback: '\uC124\uBA85' },
        { selector: '#role_edit_is_active', key: 'is_active', fallback: '\uC0C1\uD0DC' }
    ]);

    let roleTable = null;
    let roleModal = null;
    let todayPicker = null;
    let globalBound = false;
    let roleModalEls = {};
    let rolePolicyBound = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('[roles.js] jQuery not loaded');
            return;
        }

        initRolePage(window.jQuery);
    });

    function initRolePage($) {
        sanitizeRoleTableSettingsState();
        initModal();
        initDataTable($);
        bindRowReorder(roleTable, {
            api: API.REORDER,
            onSuccess() {
                notify('success', '\uC5ED\uD560 \uC21C\uBC88\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
                roleTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '\uC5ED\uD560 \uC21C\uBC88 \uC800\uC7A5\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
                roleTable?.ajax.reload(null, false);
            }
        });
        bindTableEvents($);
        bindModalEvents($);
        bindGlobalEvents();
    }

    function sanitizeRoleTableSettingsState() {
        try {
            const parsed = readDataTableSettingsState(ROLE_TABLE_SETTINGS_STORAGE_KEY, {
                userSettingPageKey: 'role',
            });
            if (!parsed || typeof parsed !== 'object') return;

            let changed = false;
            const nextState = { ...parsed };
            const deprecated = new Set(['__legacy_role_status']);

            [
                'columnWidths',
                'pageLength',
                'sortSettings',
                'currentPage',
                'searchFormExpanded',
                'searchFormState',
                'requiredColumns',
                'columnWidth',
            ].forEach((key) => {
                if (Object.prototype.hasOwnProperty.call(nextState, key)) {
                    delete nextState[key];
                    changed = true;
                }
            });

            ['visibleColumns', 'columnOrder'].forEach((key) => {
                if (!Array.isArray(nextState[key])) return;
                const filtered = nextState[key].filter((item) => !deprecated.has(String(item || '').trim()));
                if (filtered.length !== nextState[key].length) {
                    nextState[key] = filtered;
                    changed = true;
                }
            });

            ['columnDisplayName', 'columnRequirementPolicy'].forEach((key) => {
                if (!nextState[key] || typeof nextState[key] !== 'object') return;
                const filtered = Object.fromEntries(
                    Object.entries(nextState[key]).filter(([itemKey]) => !deprecated.has(String(itemKey || '').trim()))
                );
                if (Object.keys(filtered).length !== Object.keys(nextState[key]).length) {
                    nextState[key] = filtered;
                    changed = true;
                }
            });

            if (changed) {
                writeSystemUserSettingsStorage(ROLE_TABLE_SETTINGS_STORAGE_KEY, nextState, {
                    userSettingPageKey: 'role',
                    settingType: 'TABLE',
                });
            }
        } catch (error) {
            console.warn('[role] table settings sanitize failed:', error);
        }
    }

    function initModal() {
        const modalEl = document.getElementById('roleEditModal');
        if (!modalEl) return;

        roleModal = new bootstrap.Modal(modalEl, { focus: false });
        roleModalEls = {
            modal: modalEl,
            form: document.getElementById('role-edit-form'),
            title: document.getElementById('roleEditModalLabel'),
            id: document.getElementById('role_edit_id'),
            key: document.getElementById('role_edit_key'),
            name: document.getElementById('role_edit_name'),
            description: document.getElementById('role_edit_description'),
            isActive: document.getElementById('role_edit_is_active'),
            deleteBtn: document.getElementById('role_edit_delete_btn')
        };
        bindRolePolicySync();
        applyRoleModalPolicyLabels(document);

        modalEl.addEventListener('shown.bs.modal', () => {
            applyRoleModalPolicyLabels(document);
            roleModalEls.key?.focus();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            resetRoleForm();
        });
    }

    function currentRolePolicyState() {
        return readDataTableSettingsState(ROLE_TABLE_SETTINGS_STORAGE_KEY, {
            userSettingPageKey: 'role',
        }) || {};
    }

    function roleFieldLabel(key, _fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentRolePolicyState(),
            normalizedKey
        );
    }

    function roleFieldRequirement(key) {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentRolePolicyState()
        );
    }

    function roleFieldStarMarkup(key) {
        const policy = roleFieldRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function findRoleModalLabel(fieldSelector, root = document) {
        const field = root.querySelector(fieldSelector);
        if (!field) return null;

        if (field.id) {
            const labelByFor = root.querySelector(`label[for="${field.id}"]`);
            if (labelByFor) return labelByFor;
        }

        const group = field.closest('.mb-3, .form-check, .col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-12');
        if (group) {
            const label = group.querySelector('label.form-label, label.form-check-label');
            if (label) return label;
        }

        return null;
    }

    function applyRoleModalPolicyLabels(root = document) {
        ROLE_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findRoleModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = roleFieldLabel(field.key, field.fallback);
            const starMarkup = roleFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function isRoleFieldVisible(field) {
        if (!field) return false;
        if (field.type === 'hidden') return false;
        if (field.disabled) return false;
        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        return true;
    }

    function shouldValidateRolePolicyField(field) {
        const selector = String(field?.selector || '').trim();
        if (!selector) return false;
        const input = document.querySelector(selector);
        return isRoleFieldVisible(input);
    }

    function collectRoleDetailValues(form, formData) {
        const values = {};

        ROLE_MODAL_FIELD_POLICIES.forEach((field) => {
            const key = String(field?.key || '').trim();
            const selector = String(field?.selector || '').trim();
            if (!key || !selector) return;

            const input = form?.querySelector(selector) || document.querySelector(selector);
            if (!input) return;

            const fieldName = String(input.name || key).trim();
            if (input.type === 'checkbox') {
                values[key] = input.checked ? '1' : '';
                return;
            }

            values[key] = formData.get(fieldName) ?? input.value ?? '';
        });

        return values;
    }

    function validateRoleRequiredPolicies(fields = [], values = {}) {
        for (const field of fields) {
            const key = String(field?.key || '').trim();
            if (!key || roleFieldRequirement(key) !== 'required') {
                continue;
            }
            if (!shouldValidateRolePolicyField(field)) {
                continue;
            }

            const value = values[key];
            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return `${roleFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
                }
                continue;
            }

            if (String(value ?? '').trim() === '') {
                return `${roleFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
            }
        }

        return '';
    }

    function bindRolePolicySync() {
        if (rolePolicyBound) return;
        rolePolicyBound = true;

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== ROLE_TABLE_SETTINGS_STORAGE_KEY) return;
            applyRoleModalPolicyLabels(document);
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
            tableSettings: {
                pageKey: 'dashboard.settings.organization.role',
                userSettingPageKey: 'role',
                tableKey: 'role-table',
                storageKey: 'datatable.settings.dashboard.settings.organization.role.role-table.v1',
                metaDomain: 'role',
                tableLabel: '\uC5ED\uD560',
                title: '\uC5ED\uD560 \uD14C\uC774\uBE14 \uC124\uC815',
            },
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            selectionColumn: { widthResizable: true },
            selectable: true,
            deleteButton: false,
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
                dateOptions: DATE_OPTIONS,
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
            settingsKey: '__reorder',
            width: ROLE_COLUMN_WIDTHS.__reorder,
            widthResizable: true,
            className: 'reorder-handle no-sort no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>'
        });

        Object.entries(ROLE_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                width: ROLE_COLUMN_WIDTHS[field] || '120px',
                visible: config.visible ?? true,
                className: field === 'is_active'
                    ? 'text-center'
                    : (config.noVis ? 'noVis text-center' : ''),
                headerClassName: field === 'is_active' ? 'text-center' : '',
                defaultContent: '',
                render: function (data, type, row) {
                    if (type !== 'display') return data ?? '';

                    if (field === 'is_active') {
                        const active = String(data) === '1';
                        return `
                            <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                                <input type="checkbox"
                                       class="form-check-input role-active-toggle"
                                       data-id="${escapeHtml(row.id || '')}"
                                       ${active ? 'checked' : ''}
                                       aria-label="\uC0C1\uD0DC \uBCC0\uACBD">
                            </div>
                        `;
                    }

                    if (data == null) return '';
                    return escapeHtml(data);
                }
            });
        });

        columns.push({
            data: null,
            title: '\uAD00\uB9AC',
            settingsKey: '__actions',
            __dtColumnKind: 'virtual',
            width: ROLE_COLUMN_WIDTHS.__actions,
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
                            class="btn btn-outline-primary btn-sm role-edit-btn"
                            data-id="${escapeHtml(row.id || '')}">
                        \uC218\uC815
                    </button>
                `;
            }
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

        $('#role-table tbody')
            .off('change.roleActiveToggle', '.role-active-toggle')
            .on('change.roleActiveToggle', '.role-active-toggle', async function (e) {
                e.stopPropagation();

                const data = roleTable.row($(this).closest('tr')).data();
                const active = this.checked;
                if (!data?.id) return;

                this.disabled = true;

                try {
                    await updateRoleActive(data, active);
                    reloadRoleTable();
                    notify('success', active ? '\uC0AC\uC6A9\uC73C\uB85C \uBCC0\uACBD\uB418\uC5C8\uC2B5\uB2C8\uB2E4.' : '\uBBF8\uC0AC\uC6A9\uC73C\uB85C \uBCC0\uACBD\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
                } catch (err) {
                    console.error('[roles.js] status update failed:', err);
                    this.checked = !active;
                    notify('error', err.message || '\uC0C1\uD0DC \uBCC0\uACBD\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
                } finally {
                    this.disabled = false;
                }
            });

        $('#role-table tbody')
            .off('click.roleEditBtn', '.role-edit-btn')
            .on('click.roleEditBtn', '.role-edit-btn', function (e) {
                e.stopPropagation();

                const data = roleTable.row($(this).closest('tr')).data();
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

        if (roleModalEls.id) roleModalEls.id.value = row.id || '';
        if (roleModalEls.key) roleModalEls.key.value = row.role_key || '';
        if (roleModalEls.name) roleModalEls.name.value = row.role_name || '';
        if (roleModalEls.description) roleModalEls.description.value = row.description || '';
        if (roleModalEls.isActive) roleModalEls.isActive.checked = String(row.is_active) === '1';

        roleModal?.show();
    }

    function setRoleModalMode(mode) {
        const isCreate = mode === 'create';
        if (roleModalEls.title) {
            roleModalEls.title.textContent = '\uC5ED\uD560 \uB4F1\uB85D';
            if (!isCreate) {
                roleModalEls.title.textContent = '\uC5ED\uD560 \uC218\uC815';
            }
        }
        if (roleModalEls.deleteBtn) {
            roleModalEls.deleteBtn.textContent = '\uC601\uAD6C\uC0AD\uC81C';
            roleModalEls.deleteBtn.style.display = isCreate ? 'none' : '';
        }
    }

    function resetRoleForm() {
        const form = roleModalEls.form || document.getElementById('role-edit-form');
        form?.reset();

        if (roleModalEls.id) roleModalEls.id.value = '';
        if (roleModalEls.isActive) roleModalEls.isActive.checked = true;
        setRoleModalMode('create');
    }

    async function saveRole() {
        const form = document.getElementById('role-edit-form');
        if (!form) return;
        const draftFormData = new FormData(form);
        const requiredMessage = validateRoleRequiredPolicies(
            ROLE_MODAL_FIELD_POLICIES,
            collectRoleDetailValues(form, draftFormData)
        );
        if (requiredMessage) {
            notify('warning', requiredMessage);
            return;
        }

        const id = $('#role_edit_id').val();
        const fd = new FormData(form);
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

            notify('success', '\uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
            roleModal?.hide();
            reloadRoleTable();
        } catch (err) {
            console.error('[roles.js] save failed:', err);
            notify('error', '\uC800\uC7A5 \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
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
                notify('error', json?.message || '\uC0AD\uC81C \uC2E4\uD328');
                return;
            }

            notify('success', '\uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
            roleModal?.hide();
            reloadRoleTable();
        } catch (err) {
            console.error('[roles.js] delete failed:', err);
            notify('error', '\uC800\uC7A5 \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
        }
    }

    async function updateRoleActive(row, active) {
        const fd = new FormData();
        fd.set('action', 'update');
        fd.set('id', row.id || '');
        fd.set('role_key', row.role_key || '');
        fd.set('role_name', row.role_name || '');
        fd.set('description', row.description || '');
        fd.set('is_active', active ? '1' : '0');

        const res = await fetch(API.SAVE, {
            method: 'POST',
            body: fd,
            credentials: 'include'
        });
        const json = await res.json();

        if (!json?.success) {
            throw new Error(resolveSaveMessage(json?.message));
        }

        return json;
    }

    function resolveSaveMessage(message) {
        if (message === 'duplicate_key') return '\uC774\uBBF8 \uB4F1\uB85D\uB41C Role Key\uC785\uB2C8\uB2E4.';
        if (message === 'duplicate') return '\uC774\uBBF8 \uB4F1\uB85D\uB41C Role Name\uC785\uB2C8\uB2E4.';
        return message || '\uC800\uC7A5\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.';
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

        window.console?.info?.(message);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

})();
