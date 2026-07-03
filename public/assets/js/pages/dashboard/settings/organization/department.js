// 寃쎈줈: PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/departments.js'

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
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

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
        manager_id:   { label: '\uBD80\uC11C\uC7A5', visible: true },
        description:  { label: '\uC124\uBA85', visible: true },
        is_active:    { label: '\uC0C1\uD0DC', visible: true },
        created_at:   { label: '\uC0DD\uC131\uC77C\uC2DC', visible: false },
        created_by:   { label: '\uC0DD\uC131\uC790', visible: false },
        updated_at:   { label: '\uC218\uC815\uC77C\uC2DC', visible: false },
        updated_by:   { label: '\uC218\uC815\uC790', visible: false }
    };

    const DEPARTMENT_COLUMN_WIDTHS = {
        __reorder: '40px',
        sort_no: '80px',
        dept_name: '180px',
        manager_id: '140px',
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

    const DEPARTMENT_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.dashboard.settings.organization.department.department-table.v1';
    const DEPARTMENT_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#dept_edit_name', key: 'dept_name', fallback: '부서명' },
        { selector: '#dept_edit_manager_id', key: 'manager_id', fallback: '부서장' },
        { selector: '#dept_edit_description', key: 'description', fallback: '설명' },
        { selector: '#dept_edit_is_active', key: 'is_active', fallback: '상태' }
    ]);
    const MANAGER_NONE_VALUE = '__NONE__';

    let departmentTable = null;
    let departmentModal = null;
    let todayPicker = null;
    let globalBound = false;
    let managerOptionsPromise = null;
    let managerOptionsCache = null;
    let departmentPolicyBound = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('[departments.js] jQuery not loaded');
            return;
        }

        initDepartmentPage(window.jQuery);
    });

    function initDepartmentPage($) {
        sanitizeDepartmentTableSettingsState();
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
        void preloadManagerOptions();
    }

    function sanitizeDepartmentTableSettingsState() {
        const raw = window.localStorage?.getItem(DEPARTMENT_TABLE_SETTINGS_STORAGE_KEY);
        if (!raw) return;

        try {
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return;

            let changed = false;
            const nextState = { ...parsed };
            const deprecated = new Set(['__legacy_department_status']);

            ['visibleColumns', 'columnOrder', 'requiredColumns'].forEach((key) => {
                if (!Array.isArray(nextState[key])) return;
                const filtered = nextState[key].filter((item) => !deprecated.has(String(item || '').trim()));
                if (filtered.length !== nextState[key].length) {
                    nextState[key] = filtered;
                    changed = true;
                }
            });

            ['columnDisplayName', 'columnRequirementPolicy', 'columnWidth'].forEach((key) => {
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
                window.localStorage?.setItem(DEPARTMENT_TABLE_SETTINGS_STORAGE_KEY, JSON.stringify(nextState));
            }
        } catch (error) {
            console.warn('[department] table settings sanitize failed:', error);
        }
    }

    function initModal() {
        const modalEl = document.getElementById('deptEditModal');
        if (!modalEl) return;

        departmentModal = new bootstrap.Modal(modalEl, { focus: false });
        bindDepartmentPolicySync();
        applyDepartmentModalPolicyLabels(document);

        modalEl.addEventListener('shown.bs.modal', () => {
            applyDepartmentModalPolicyLabels(document);
            const first = document.getElementById('dept_edit_name');
            first?.focus();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            resetDepartmentForm();
        });
    }

    function currentDepartmentPolicyState() {
        return readDataTableSettingsState(DEPARTMENT_TABLE_SETTINGS_STORAGE_KEY) || {};
    }

    function departmentFieldLabel(key, _fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentDepartmentPolicyState(),
            normalizedKey
        );
    }

    function departmentFieldRequirement(key) {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentDepartmentPolicyState()
        );
    }

    function departmentFieldStarMarkup(key) {
        const policy = departmentFieldRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function findDepartmentModalLabel(fieldSelector, root = document) {
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

    function applyDepartmentModalPolicyLabels(root = document) {
        DEPARTMENT_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findDepartmentModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = departmentFieldLabel(field.key, field.fallback);
            const starMarkup = departmentFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function isDepartmentFieldVisible(field) {
        if (!field) return false;
        if (field.type === 'hidden') return false;
        if (field.disabled) return false;
        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        return true;
    }

    function shouldValidateDepartmentPolicyField(field) {
        const selector = String(field?.selector || '').trim();
        if (!selector) return false;
        const input = document.querySelector(selector);
        return isDepartmentFieldVisible(input);
    }

    function collectDepartmentDetailValues(form, formData) {
        const values = {};

        DEPARTMENT_MODAL_FIELD_POLICIES.forEach((field) => {
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

    function validateDepartmentRequiredPolicies(fields = [], values = {}) {
        for (const field of fields) {
            const key = String(field?.key || '').trim();
            if (!key || departmentFieldRequirement(key) !== 'required') {
                continue;
            }
            if (!shouldValidateDepartmentPolicyField(field)) {
                continue;
            }

            const value = values[key];
            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return `${departmentFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
                }
                continue;
            }

            if (String(value ?? '').trim() === '') {
                return `${departmentFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
            }
        }

        return '';
    }

    function bindDepartmentPolicySync() {
        if (departmentPolicyBound) return;
        departmentPolicyBound = true;

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== DEPARTMENT_TABLE_SETTINGS_STORAGE_KEY) return;
            applyDepartmentModalPolicyLabels(document);
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
            tableSettings: {
                pageKey: 'dashboard.settings.organization.department',
                tableKey: 'department-table',
                storageKey: 'datatable.settings.dashboard.settings.organization.department.department-table.v1',
                metaDomain: 'department',
                tableLabel: '\uBD80\uC11C',
                title: '\uBD80\uC11C \uD14C\uC774\uBE14 \uC124\uC815',
            },
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            selectionColumn: { widthResizable: true },
            selectable: true,
            deleteButton: false,
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
                dateOptions: DATE_OPTIONS,
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
            settingsKey: '__reorder',
            width: DEPARTMENT_COLUMN_WIDTHS.__reorder,
            widthResizable: true,
            className: 'reorder-handle no-sort no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>'
        });



        Object.entries(DEPARTMENT_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                width: DEPARTMENT_COLUMN_WIDTHS[field] || '120px',
                visible: config.visible ?? true,
                className: field === 'is_active'
                    ? 'text-center'
                    : (config.noVis ? 'noVis text-center' : ''),
                headerClassName: field === 'is_active' ? 'text-center' : '',
                defaultContent: '',
                render: function (data, type, row) {
                    if (type !== 'display') return data ?? '';

                    if (field === 'manager_id') {
                        if (data == null) return '';
                        return escapeHtml(row?.manager_name || data);
                    }

                    if (field === 'is_active') {
                        const active = String(data) === '1';
                        return `
                            <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                                <input type="checkbox"
                                       class="form-check-input department-active-toggle"
                                       data-id="${escapeHtml(row.id || '')}"
                                       ${active ? 'checked' : ''}
                                       aria-label="상태 변경">
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
            title: '관리',
            settingsKey: '__actions',
            __dtColumnKind: 'virtual',
            width: DEPARTMENT_COLUMN_WIDTHS.__actions,
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
                            class="btn btn-outline-primary btn-sm department-edit-btn"
                            data-id="${escapeHtml(row.id || '')}">
                        수정
                    </button>
                `;
            }
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
            .off('change.departmentActiveToggle', '.department-active-toggle')
            .on('change.departmentActiveToggle', '.department-active-toggle', async function (e) {
                e.stopPropagation();

                const data = departmentTable.row($(this).closest('tr')).data();
                const active = this.checked;
                if (!data?.id) return;

                this.disabled = true;

                try {
                    await updateDepartmentActive(data, active);
                    reloadDepartmentTable();
                    notify('success', active ? '사용으로 변경되었습니다.' : '미사용으로 변경되었습니다.');
                } catch (err) {
                    console.error('[departments.js] status update failed:', err);
                    this.checked = !active;
                    notify('error', err.message || '상태 변경에 실패했습니다.');
                } finally {
                    this.disabled = false;
                }
            });

        $('#department-table tbody')
            .off('click.departmentEditBtn', '.department-edit-btn')
            .on('click.departmentEditBtn', '.department-edit-btn', function (e) {
                e.stopPropagation();

                const data = departmentTable.row($(this).closest('tr')).data();
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

    function openCreateModal() {
        resetDepartmentForm();
        setDepartmentModalMode('create');
        departmentModal?.show();
        deferManagerOptions('');
    }

    function openEditModal(row) {
        resetDepartmentForm();
        setDepartmentModalMode('edit');

        $('#dept_edit_id').val(row.id || '');
        $('#dept_edit_name').val(row.dept_name || '');
        $('#dept_edit_description').val(row.description || '');
        $('#dept_edit_is_active').prop('checked', String(row.is_active) === '1');

        departmentModal?.show();
        deferManagerOptions(row.manager_id || '');
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
        const form = document.getElementById('dept-edit-form');
        if (!form) return;
        const draftFormData = new FormData(form);
        const requiredMessage = validateDepartmentRequiredPolicies(
            DEPARTMENT_MODAL_FIELD_POLICIES,
            collectDepartmentDetailValues(form, draftFormData)
        );
        if (requiredMessage) {
            notify('warning', requiredMessage);
            return;
        }

        const id = $('#dept_edit_id').val();
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
                notify('error', json?.message === 'duplicate' ? '이미 등록된 부서명입니다.' : (json?.message || '저장에 실패했습니다.'));
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

    async function updateDepartmentActive(row, active) {
        const fd = new FormData();
        fd.set('action', 'update');
        fd.set('id', row.id || '');
        fd.set('dept_name', row.dept_name || '');
        fd.set('description', row.description || '');
        fd.set('manager_id', normalizeManagerId(row.manager_id || ''));
        fd.set('is_active', active ? '1' : '0');

        const res = await fetch(API.SAVE, {
            method: 'POST',
            body: fd,
            credentials: 'include'
        });
        const json = await res.json();

        if (!json?.success) {
            throw new Error(json?.message || '상태 변경에 실패했습니다.');
        }

        return json;
    }

    async function loadManagerOptions(selectedValue = '') {
        const select = document.getElementById('dept_edit_manager_id');
        if (!select) return;

        selectedValue = selectedValue != null ? String(selectedValue) : '';

        try {
            const items = await getManagerOptions();

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

    function preloadManagerOptions() {
        if (!managerOptionsPromise) {
            managerOptionsPromise = fetchManagerOptions()
                .then((items) => {
                    managerOptionsCache = items;
                    return items;
                })
                .catch((err) => {
                    managerOptionsPromise = null;
                    console.error('[departments.js] manager preload failed:', err);
                    return null;
                });
        }

        return managerOptionsPromise;
    }

    async function getManagerOptions() {
        if (managerOptionsCache) return managerOptionsCache;

        const items = await preloadManagerOptions();
        if (items) return items;

        return [
            { id: MANAGER_NONE_VALUE, text: '선택(없음)' }
        ];
    }

    async function fetchManagerOptions() {
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

        return items;
    }

    function deferManagerOptions(selectedValue = '') {
        const run = () => {
            loadManagerOptions(selectedValue).catch((err) => {
                console.error('[departments.js] manager prepare failed:', err);
                notify('error', '부서장 목록 준비 중 오류가 발생했습니다.');
            });
        };

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(() => window.setTimeout(run, 0));
            return;
        }

        window.setTimeout(run, 0);
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

})();
