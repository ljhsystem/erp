// ?롪퍔?δ빳? PROJECT_ROOT . '/public/assets/js/pages/dashboard/settings/organization/positions.js'

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
        LIST: '/api/settings/organization/position/list',
        SAVE: '/api/settings/organization/position/save',
        DELETE: '/api/settings/organization/position/delete',
        REORDER: '/api/settings/organization/position/reorder'
    };

    const POSITION_COLUMN_MAP = {
        sort_no:       { label: '\uC21C\uBC88', visible: true },
        position_name: { label: '\uC9C1\uCC45\uBA85', visible: true },
        level_rank:    { label: '\uB808\uBCA8', visible: true },
        description:   { label: '\uC124\uBA85', visible: true },
        is_active:     { label: '\uC0C1\uD0DC', visible: true },
        created_at:    { label: '\uC0DD\uC131\uC77C\uC2DC', visible: false },
        created_by:    { label: '\uC0DD\uC131\uC790', visible: false },
        updated_at:    { label: '\uC218\uC815\uC77C\uC2DC', visible: false },
        updated_by:    { label: '\uC218\uC815\uC790', visible: false }
    };

    const POSITION_COLUMN_WIDTHS = {
        __reorder: '40px',
        sort_no: '80px',
        position_name: '180px',
        level_rank: '90px',
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

    const POSITION_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.dashboard.settings.organization.position.position-table.v1';
    const POSITION_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#position_edit_name', key: 'position_name', fallback: '\uC9C1\uCC45\uBA85' },
        { selector: '#position_edit_rank', key: 'level_rank', fallback: '\uB808\uBCA8' },
        { selector: '#position_edit_description', key: 'description', fallback: '\uC124\uBA85' },
        { selector: '#position_edit_is_active', key: 'is_active', fallback: '\uC0C1\uD0DC' }
    ]);

    let positionTable = null;
    let positionModal = null;
    let todayPicker = null;
    let globalBound = false;
    let positionModalEls = {};
    let positionPolicyBound = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('[positions.js] jQuery not loaded');
            return;
        }

        initPositionPage(window.jQuery);
    });

    function initPositionPage($) {
        sanitizePositionTableSettingsState();
        initModal();
        initDataTable($);
        bindRowReorder(positionTable, {
            api: API.REORDER,
            onSuccess() {
                notify('success', '\uC9C1\uCC45 \uC21C\uBC88\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
                positionTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '\uC9C1\uCC45 \uC21C\uBC88 \uC800\uC7A5\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
                positionTable?.ajax.reload(null, false);
            }
        });
        bindTableEvents($);
        bindModalEvents($);
        bindGlobalEvents();
    }

    function sanitizePositionTableSettingsState() {
        const raw = window.localStorage?.getItem(POSITION_TABLE_SETTINGS_STORAGE_KEY);
        if (!raw) return;

        try {
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return;

            let changed = false;
            const nextState = { ...parsed };
            const deprecated = new Set(['__legacy_position_status']);

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
                window.localStorage?.setItem(POSITION_TABLE_SETTINGS_STORAGE_KEY, JSON.stringify(nextState));
            }
        } catch (error) {
            console.warn('[position] table settings sanitize failed:', error);
        }
    }

    function initModal() {
        const modalEl = document.getElementById('positionEditModal');
        if (!modalEl) return;

        positionModal = new bootstrap.Modal(modalEl, { focus: false });
        positionModalEls = {
            modal: modalEl,
            form: document.getElementById('position-edit-form'),
            title: document.getElementById('positionEditModalLabel'),
            id: document.getElementById('position_edit_id'),
            name: document.getElementById('position_edit_name'),
            rank: document.getElementById('position_edit_rank'),
            description: document.getElementById('position_edit_description'),
            isActive: document.getElementById('position_edit_is_active'),
            deleteBtn: document.getElementById('position_edit_delete_btn')
        };
        bindPositionPolicySync();
        applyPositionModalPolicyLabels(document);

        modalEl.addEventListener('shown.bs.modal', () => {
            applyPositionModalPolicyLabels(document);
            positionModalEls.name?.focus();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            resetPositionForm();
        });
    }

    function currentPositionPolicyState() {
        return readDataTableSettingsState(POSITION_TABLE_SETTINGS_STORAGE_KEY) || {};
    }

    function positionFieldLabel(key, _fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentPositionPolicyState(),
            normalizedKey
        );
    }

    function positionFieldRequirement(key) {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentPositionPolicyState()
        );
    }

    function positionFieldStarMarkup(key) {
        const policy = positionFieldRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function findPositionModalLabel(fieldSelector, root = document) {
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

    function applyPositionModalPolicyLabels(root = document) {
        POSITION_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findPositionModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = positionFieldLabel(field.key, field.fallback);
            const starMarkup = positionFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function isPositionFieldVisible(field) {
        if (!field) return false;
        if (field.type === 'hidden') return false;
        if (field.disabled) return false;
        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') return false;
        return true;
    }

    function shouldValidatePositionPolicyField(field) {
        const selector = String(field?.selector || '').trim();
        if (!selector) return false;
        const input = document.querySelector(selector);
        return isPositionFieldVisible(input);
    }

    function collectPositionDetailValues(form, formData) {
        const values = {};

        POSITION_MODAL_FIELD_POLICIES.forEach((field) => {
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

    function validatePositionRequiredPolicies(fields = [], values = {}) {
        for (const field of fields) {
            const key = String(field?.key || '').trim();
            if (!key || positionFieldRequirement(key) !== 'required') {
                continue;
            }
            if (!shouldValidatePositionPolicyField(field)) {
                continue;
            }

            const value = values[key];
            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return `${positionFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
                }
                continue;
            }

            if (String(value ?? '').trim() === '') {
                return `${positionFieldLabel(key, field?.fallback || key)} 항목은 필수입니다.`;
            }
        }

        return '';
    }

    function bindPositionPolicySync() {
        if (positionPolicyBound) return;
        positionPolicyBound = true;

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== POSITION_TABLE_SETTINGS_STORAGE_KEY) return;
            applyPositionModalPolicyLabels(document);
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
            if (input.__positionDateBound) return;
            input.__positionDateBound = true;

            input.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                openDatePicker(input);
            });
        });

        document.querySelectorAll('.date-icon').forEach(icon => {
            if (icon.__positionDateBound) return;
            icon.__positionDateBound = true;

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
        const columns = buildPositionColumns();

        positionTable = createDataTable({
            tableSelector: '#position-table',
            api: API.LIST,
            columns,
            tableSettings: {
                pageKey: 'dashboard.settings.organization.position',
                tableKey: 'position-table',
                storageKey: 'datatable.settings.dashboard.settings.organization.position.position-table.v1',
                metaDomain: 'position',
                tableLabel: '\uC9C1\uCC45',
                title: '\uC9C1\uCC45 \uD14C\uC774\uBE14 \uC124\uC815',
            },
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            selectionColumn: { widthResizable: true },
            selectable: true,
            deleteButton: false,
            buttons: [
                {
                    text: '\uC0C8 \uC9C1\uCC45',
                    className: 'btn btn-primary btn-sm',
                    action: function () {
                        openCreateModal();
                    }
                }
            ]
        });

        window.PositionTable = positionTable;
        window.EmployeePositionsTable = {
            instance: positionTable,
            reload: () => positionTable?.ajax.reload(null, false)
        };

        if (positionTable) {
            SearchForm({
                table: positionTable,
                apiList: API.LIST,
                tableId: 'position',
                defaultSearchField: 'position_name',
                dateOptions: DATE_OPTIONS,
            });
            bindTableHighlight('#position-table', positionTable);

            positionTable.on('draw', updatePositionCountFromTable);
            updatePositionCountFromTable();
        }
    }

    function buildPositionColumns() {
        const columns = [];

        columns.push({
            title: '<i class="bi bi-arrows-move"></i>',
            settingsKey: '__reorder',
            width: POSITION_COLUMN_WIDTHS.__reorder,
            widthResizable: true,
            className: 'reorder-handle no-sort no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>'
        });

        Object.entries(POSITION_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                width: POSITION_COLUMN_WIDTHS[field] || '120px',
                visible: config.visible ?? true,
                className: field === 'is_active'
                    ? 'text-center'
                    : (config.noVis ? 'noVis text-center' : (field === 'level_rank' ? 'text-center' : '')),
                headerClassName: field === 'is_active' ? 'text-center' : '',
                defaultContent: '',
                render: function (data, type, row) {
                    if (type !== 'display') return data ?? '';

                    if (field === 'is_active') {
                        const active = String(data) === '1';
                        return `
                            <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                                <input type="checkbox"
                                       class="form-check-input position-active-toggle"
                                       data-id="${escapeHtml(row.id || '')}"
                                       ${active ? 'checked' : ''}
                                       aria-label='\uC0C1\uD0DC \uBCC0\uACBD'>
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
            width: POSITION_COLUMN_WIDTHS.__actions,
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
                            class="btn btn-outline-primary btn-sm position-edit-btn"
                            data-id="${escapeHtml(row.id || '')}">
                        \uC218\uC815
                    </button>
                `;
            }
        });

        return columns;
    }

    function bindTableEvents($) {
        $('#position-table tbody')
            .off('dblclick.positionEdit', 'tr')
            .on('dblclick.positionEdit', 'tr', function () {
                const data = positionTable.row(this).data();
                if (data) openEditModal(data);
            });

        $('#position-table tbody')
            .off('change.positionActiveToggle', '.position-active-toggle')
            .on('change.positionActiveToggle', '.position-active-toggle', async function (e) {
                e.stopPropagation();

                const data = positionTable.row($(this).closest('tr')).data();
                const active = this.checked;
                if (!data?.id) return;

                this.disabled = true;

                try {
                    await updatePositionActive(data, active);
                    reloadPositionTable();
                    notify('success', active ? '\uC0AC\uC6A9\uC73C\uB85C \uBCC0\uACBD\uB418\uC5C8\uC2B5\uB2C8\uB2E4.' : '\uBBF8\uC0AC\uC6A9\uC73C\uB85C \uBCC0\uACBD\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
                } catch (err) {
                    console.error('[positions.js] status update failed:', err);
                    this.checked = !active;
                    notify('error', err.message || '\uC0C1\uD0DC \uBCC0\uACBD\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
                } finally {
                    this.disabled = false;
                }
            });

        $('#position-table tbody')
            .off('click.positionEditBtn', '.position-edit-btn')
            .on('click.positionEditBtn', '.position-edit-btn', function (e) {
                e.stopPropagation();

                const data = positionTable.row($(this).closest('tr')).data();
                if (data) openEditModal(data);
            });

    }

    function bindModalEvents($) {
        $(document)
            .off('submit.positionForm', '#position-edit-form')
            .on('submit.positionForm', '#position-edit-form', async function (e) {
                e.preventDefault();
                await savePosition();
            });

        $(document)
            .off('click.positionDelete', '#position_edit_delete_btn')
            .on('click.positionDelete', '#position_edit_delete_btn', async function (e) {
                e.preventDefault();
                e.stopPropagation();

                const id = $('#position_edit_id').val();
                if (!id) return;
                if (!confirm('\uC9C1\uCC45\uC744 \uC601\uAD6C\uC0AD\uC81C\uD558\uC2DC\uACA0\uC2B5\uB2C8\uAE4C?')) return;

                await deletePosition(id);
            });
    }

    function openCreateModal() {
        resetPositionForm();
        setPositionModalMode('create');
        positionModal?.show();
    }

    function openEditModal(row) {
        resetPositionForm();
        setPositionModalMode('edit');

        if (positionModalEls.id) positionModalEls.id.value = row.id || '';
        if (positionModalEls.name) positionModalEls.name.value = row.position_name || '';
        if (positionModalEls.rank) positionModalEls.rank.value = row.level_rank ?? 0;
        if (positionModalEls.description) positionModalEls.description.value = row.description || '';
        if (positionModalEls.isActive) positionModalEls.isActive.checked = String(row.is_active) === '1';

        positionModal?.show();
    }

    function setPositionModalMode(mode) {
        const isCreate = mode === 'create';
        if (positionModalEls.title) {
            positionModalEls.title.textContent = isCreate ? '\uC9C1\uCC45 \uB4F1\uB85D' : '\uC9C1\uCC45 \uC218\uC815';
        }
        if (positionModalEls.deleteBtn) {
            positionModalEls.deleteBtn.textContent = '\uC601\uAD6C\uC0AD\uC81C';
            positionModalEls.deleteBtn.style.display = isCreate ? 'none' : '';
        }
    }

    function resetPositionForm() {
        const form = positionModalEls.form || document.getElementById('position-edit-form');
        form?.reset();

        if (positionModalEls.id) positionModalEls.id.value = '';
        if (positionModalEls.rank) positionModalEls.rank.value = '0';
        if (positionModalEls.isActive) positionModalEls.isActive.checked = true;
        setPositionModalMode('create');
    }

    async function savePosition() {
        const form = document.getElementById('position-edit-form');
        if (!form) return;
        const draftFormData = new FormData(form);
        const requiredMessage = validatePositionRequiredPolicies(
            POSITION_MODAL_FIELD_POLICIES,
            collectPositionDetailValues(form, draftFormData)
        );
        if (requiredMessage) {
            notify('warning', requiredMessage);
            return;
        }

        const id = $('#position_edit_id').val();
        const fd = new FormData(form);
        fd.set('action', id ? 'update' : 'create');
        fd.set('is_active', $('#position_edit_is_active').is(':checked') ? '1' : '0');

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
            positionModal?.hide();
            reloadPositionTable();
        } catch (err) {
            console.error('[positions.js] save failed:', err);
            notify('error', '\uC800\uC7A5 \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
        }
    }

    async function deletePosition(id) {
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
            positionModal?.hide();
            reloadPositionTable();
        } catch (err) {
            console.error('[positions.js] delete failed:', err);
            notify('error', '\uC800\uC7A5 \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
        }
    }

    async function updatePositionActive(row, active) {
        const fd = new FormData();
        fd.set('action', 'update');
        fd.set('id', row.id || '');
        fd.set('position_name', row.position_name || '');
        fd.set('level_rank', row.level_rank ?? 0);
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
        if (message === 'duplicate') return '\uC774\uBBF8 \uB4F1\uB85D\uB41C \uC9C1\uCC45\uBA85\uC785\uB2C8\uB2E4.';
        if (message === 'empty') return '\uC9C1\uCC45\uBA85\uC744 \uC785\uB825\uD558\uC138\uC694.';
        return message || '\uC800\uC7A5\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.';
    }

    function reloadPositionTable() {
        positionTable?.ajax.reload(() => {
            updatePositionCountFromTable();
        }, false);
    }

    function updatePositionCountFromTable() {
        if (!positionTable?.page) return;

        const info = positionTable.page.info();
        const el = document.getElementById('positionCount');
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
        const start = document.querySelector('#positionSearchConditionsForm input[name="dateStart"]');
        const end = document.querySelector('#positionSearchConditionsForm input[name="dateEnd"]');

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
