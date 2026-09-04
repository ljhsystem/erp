import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import { initCodeModalRuntime, renderCodeSystemInfo } from './code-modal-runtime.js';
import { writeSystemUserSettingsStorage } from '/public/assets/js/common/user-settings/systemUserSettingsStorage.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    const API = {
        LIST: '/api/settings/system/code/list',
        DETAIL: '/api/settings/system/code/detail',
        GROUPS: '/api/settings/system/code/groups',
        SAVE: '/api/settings/system/code/save',
        DELETE: '/api/settings/system/code/delete',
        REFERENCES: '/api/settings/system/code/references',
        REORDER: '/api/settings/system/code/reorder'
    };

    const CODE_COLUMN_MAP = {
        sort_no: { label: '순번', visible: true, className: 'text-center' },
        code_group: { label: '코드그룹', visible: true },
        group_name: { label: '그룹명', visible: true },
        code: { label: '코드', visible: true },
        code_name: { label: '코드명', visible: true },
        note: { label: '비고', visible: true },
        memo: { label: '메모', visible: false },
        is_active: { label: '상태', visible: true, className: 'text-center' },
        extra_data: { label: '추가속성', visible: false },
        created_at: { label: '생성일', visible: false },
        created_by_name: { label: '생성자', visible: false },
        updated_at: { label: '수정일', visible: false },
        updated_by_name: { label: '수정자', visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '등록일시' },
        { value: 'updated_at', label: '수정일시' }
    ];
    const NEW_CODE_GROUP_VALUE = '__new_code_group__';
    const CODE_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.main.settings.system.code.code-table.v1';
    const CODE_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#modal_code_group', key: 'code_group' },
        { selector: '#modal_code_group_name', key: 'group_name' },
        { selector: '#modal_code_code', key: 'code' },
        { selector: '#modal_code_code_name', key: 'code_name' },
        { selector: '#modal_code_is_active', key: 'is_active' },
        { selector: '#modal_code_extra_data', key: 'extra_data' },
        { selector: '#modal_code_note', key: 'note' },
        { selector: '#modal_code_memo', key: 'memo' },
    ]);
    const CODE_QUICK_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#codeQuickForm [name="code_group"]', key: 'code_group' },
        { selector: '#codeQuickForm [name="group_name"]', key: 'group_name' },
        { selector: '#codeQuickForm [name="code"]', key: 'code' },
        { selector: '#codeQuickForm [name="code_name"]', key: 'code_name' },
    ]);

    let codeTable = null;
    let codeModal = null;
    let codeModalCardCollapses = null;
    let codeQuickModal = null;
    let todayPicker = null;
    let codeGroups = [];
    let codeGroupNames = {};
    let codeModalEls = {};
    let codeQuickEls = {};
    let codePolicyBound = false;

    document.addEventListener('DOMContentLoaded', () => {
        if (!window.jQuery) {
            console.error('jQuery not loaded');
            return;
        }

        initCodePage(window.jQuery);
    });

    async function initCodePage($) {
        sanitizeCodeTableSettingsState();
        initModal();
        bindCodePolicySync();
        initAdminDatePicker();
        loadCodeGroups();
        await initDataTable($);
        bindRowReorder(codeTable, {
            api: API.REORDER,
            onSuccess() {
                AppCore?.notify?.('success', '기준정보 순번이 저장되었습니다.');
                codeTable?.ajax.reload(null, false);
            },
            onError(json) {
                AppCore?.notify?.('error', json?.message || '기준정보 순번 저장에 실패했습니다.');
                codeTable?.ajax.reload(null, false);
            }
        });
        bindTableEvents($);
        bindModalEvents($);
        bindAdminDateInputs();
        bindDateIconPicker();
    }

    function sanitizeCodeTableSettingsState() {
        try {
            const parsed = readDataTableSettingsState(CODE_TABLE_SETTINGS_STORAGE_KEY, {
                userSettingPageKey: 'code',
            });
            if (!parsed || typeof parsed !== 'object') return;

            let changed = false;
            const nextState = { ...parsed };
            const deprecated = new Set(['__legacy_code_status', 'deleted_at', 'deleted_by', 'deleted_by_name']);

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
                writeSystemUserSettingsStorage(CODE_TABLE_SETTINGS_STORAGE_KEY, nextState, {
                    userSettingPageKey: 'code',
                    settingType: 'TABLE',
                });
            }
        } catch (error) {
            console.warn('[code] table settings sanitize failed:', error);
        }
    }

    function initModal() {
        const modalEl = document.getElementById('codeModal');
        if (!modalEl) return;

        codeModal = new bootstrap.Modal(modalEl, { focus: false });
        codeModalCardCollapses = initCodeModalRuntime(modalEl);
        codeModalEls = {
            modal: modalEl,
            form: document.getElementById('codeForm'),
            title: document.getElementById('codeModalLabel'),
            id: document.getElementById('modal_code_id'),
            group: document.getElementById('modal_code_group'),
            groupInput: document.getElementById('modal_code_group_input'),
            groupInputWrap: document.getElementById('modal_code_group_input_wrap'),
            groupName: document.getElementById('modal_code_group_name'),
            code: document.getElementById('modal_code_code'),
            codeName: document.getElementById('modal_code_code_name'),
            isActive: document.getElementById('modal_code_is_active'),
            extraData: document.getElementById('modal_code_extra_data'),
            note: document.getElementById('modal_code_note'),
            memo: document.getElementById('modal_code_memo'),
            deleteBtn: document.getElementById('btnDeleteCode')
        };
        modalEl.addEventListener('hidden.bs.modal', resetForm);
        modalEl.addEventListener('shown.bs.modal', () => {
            applyCodeModalPolicyLabels(modalEl);
        });

        const quickModalEl = document.getElementById('codeQuickModal');
        if (quickModalEl) {
            codeQuickModal = new bootstrap.Modal(quickModalEl, { focus: false });
            const quickForm = quickModalEl.querySelector('#codeQuickForm');
            codeQuickEls = {
                modal: quickModalEl,
                form: quickForm,
                title: quickModalEl.querySelector('.modal-title'),
                codeGroup: quickForm?.querySelector('[name="code_group"]'),
                groupInput: quickForm?.querySelector('[data-role="quick-code-group-input"]'),
                groupInputWrap: quickForm?.querySelector('[data-role="quick-code-group-input-wrap"]'),
                groupBackBtn: quickForm?.querySelector('[data-role="quick-code-group-back"]'),
                groupName: quickForm?.querySelector('[name="group_name"]'),
                code: quickForm?.querySelector('[name="code"]'),
                codeName: quickForm?.querySelector('[name="code_name"]'),
                message: quickForm?.querySelector('[data-role="message"]'),
                detailBtn: quickForm?.querySelector('[data-role="detail"]')
            };
            quickModalEl.addEventListener('shown.bs.modal', () => {
                applyCodeQuickModalPolicyLabels(quickModalEl);
                codeQuickEls.codeGroup?.focus();
            });
        }

        applyCodeModalPolicyLabels(document);
        applyCodeQuickModalPolicyLabels(document);
    }

    function currentPolicyState() {
        return readDataTableSettingsState(CODE_TABLE_SETTINGS_STORAGE_KEY, {
            userSettingPageKey: 'code',
        }) || {};
    }

    function codeFieldLabel(key, fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentPolicyState(),
            normalizedKey || fallback
        );
    }

    function codeFieldRequirement(key) {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentPolicyState()
        );
    }

    function codeFieldStarMarkup(key) {
        const policy = codeFieldRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function findFieldLabel(fieldSelector, root = document) {
        const field = root.querySelector(fieldSelector);
        if (!field) return null;

        if (field.id) {
            const labelByFor = root.querySelector(`label[for="${field.id}"]`);
            if (labelByFor) return labelByFor;
        }

        const group = field.closest('.mb-3, .form-check, .col-md-2, .col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-12');
        if (group) {
            const label = group.querySelector('label.form-label, label.form-check-label');
            if (label) return label;
        }

        return field.closest('label.form-label, label.form-check-label') || null;
    }

    function applyPolicyLabels(fieldPolicies, root = document) {
        fieldPolicies.forEach((field) => {
            const labelEl = findFieldLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = codeFieldLabel(field.key, field.key);
            const starMarkup = codeFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function applyCodeModalPolicyLabels(root = document) {
        applyPolicyLabels(CODE_MODAL_FIELD_POLICIES, root);
    }

    function applyCodeQuickModalPolicyLabels(root = document) {
        applyPolicyLabels(CODE_QUICK_MODAL_FIELD_POLICIES, root);
    }

    function bindCodePolicySync() {
        if (codePolicyBound) return;
        codePolicyBound = true;

        sanitizeCodeTableSettingsState();

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== CODE_TABLE_SETTINGS_STORAGE_KEY) {
                return;
            }

            sanitizeCodeTableSettingsState();
            applyCodeModalPolicyLabels(document);
            applyCodeQuickModalPolicyLabels(document);
        });
    }

    function focusCodePolicyField(selector) {
        const field = document.querySelector(selector);
        if (!field) return;
        if (typeof field.focus === 'function') {
            field.focus();
        }
    }

    function collectCodeModalValues() {
        return {
            code_group: normalizeCodeGroup(getModalCodeGroupValue()),
            group_name: String(codeModalEls.groupName?.value || '').trim(),
            code: String(codeModalEls.code?.value || '').trim().toUpperCase(),
            code_name: String(codeModalEls.codeName?.value || '').trim(),
            is_active: codeModalEls.isActive?.checked ? '1' : '0',
            extra_data: String(codeModalEls.extraData?.value || '').trim(),
            note: String(codeModalEls.note?.value || '').trim(),
            memo: String(codeModalEls.memo?.value || '').trim(),
        };
    }

    function validateCodeModalRequiredPolicies() {
        const values = collectCodeModalValues();

        for (const field of CODE_MODAL_FIELD_POLICIES) {
            if (codeFieldRequirement(field.key) !== 'required') {
                continue;
            }

            if (String(values[field.key] ?? '').trim() !== '') {
                continue;
            }

            AppCore?.notify?.('warning', `${codeFieldLabel(field.key, field.key)} 항목을 입력해 주세요.`);
            focusCodePolicyField(field.selector);
            return false;
        }

        return true;
    }

    function validateQuickCodeRequiredPolicies(values) {
        for (const field of CODE_QUICK_MODAL_FIELD_POLICIES) {
            if (codeFieldRequirement(field.key) !== 'required') {
                continue;
            }

            if (String(values[field.key] ?? '').trim() !== '') {
                continue;
            }

            setQuickMessage(`${codeFieldLabel(field.key, field.key)} 항목을 입력해 주세요.`);
            focusCodePolicyField(field.selector);
            return false;
        }

        return true;
    }

    async function initDataTable($) {
        codeTable = await createDataTable({
            tableSelector: '#code-table',
            api: API.LIST,
            columns: buildColumns(),
            defaultOrder: [[3, 'asc'], [2, 'asc'], [1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            deleteButton: true,
            deleteApi: API.DELETE,
            selectionColumn: {
                widthResizable: true,
            },
            tableSettings: {
                pageKey: 'main.settings.system.code',
                userSettingPageKey: 'code',
                tableKey: 'code-table',
                storageKey: 'datatable.settings.main.settings.system.code.code-table.v1',
                metaDomain: 'code',
                tableLabel: '코드관리',
                title: '코드관리 테이블 설정',
                defaultVisibleColumns: ['sort_no', 'code_group', 'group_name', 'code', 'code_name', 'note', 'is_active'],
            },
            buttons: [
                {
                    text: '신규등록',
                    className: 'btn btn-warning btn-sm',
                    action: openQuickCreateModal
                }
            ]
        });

        window.codeTable = codeTable;

        if (codeTable) {
            const settingsButton = codeTable.table()
                .container()
                .querySelector('.dt-table-settings-trigger');
            if (settingsButton) {
                settingsButton.innerHTML = '<i class="bi bi-gear"></i>';
                settingsButton.setAttribute('aria-label', '테이블 설정');
                settingsButton.setAttribute('title', '테이블 설정');
            }

            codeTable.on('init.dt draw.dt xhr.dt', () => {
                updateCount(codeTable.page.info()?.recordsDisplay ?? 0);
            });

            codeTable.on('xhr.dt', (event, settings, json) => {
                const rows = Array.isArray(json?.data) ? json.data : [];
                mergeCodeGroups(rows);
            });

            SearchForm({
                table: codeTable,
                apiList: API.LIST,
                tableId: 'code',
                defaultSearchField: 'code_name',
                dateOptions: DATE_OPTIONS,
            });

            bindTableHighlight('#code-table', codeTable);
        }
    }

    function buildColumns() {
        const columns = [{
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'col-reorder reorder-handle no-sort no-colvis text-center',
            headerClassName: 'col-reorder no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>',
            settingsKey: '__reorder',
            width: '44px',
            widthResizable: true
        }];

        Object.entries(CODE_COLUMN_MAP).forEach(([field, config]) => {
            if (field === 'is_active') return;
            const className = [
                config.className || '',
                field === 'sort_no' ? 'dt-sequence-column' : '',
            ].filter(Boolean).join(' ');

            columns.push({
                data: field,
                title: config.label,
                visible: config.visible ?? true,
                className,
                headerClassName: className,
                defaultContent: '',
                settingsKey: field,
                render(data, _type, row) {
                    if (field === 'created_by_name') {
                        return escapeHtml(actorDisplay(row, 'created_by'));
                    }

                    if (field === 'updated_by_name') {
                        return escapeHtml(actorDisplay(row, 'updated_by'));
                    }

                    if (data === null || data === undefined) {
                        return '';
                    }

                    if (field === 'is_active') {
                        return Number(data) === 1
                            ? '<span class="badge bg-success">사용</span>'
                            : '<span class="badge bg-secondary">미사용</span>';
                    }

                    return escapeHtml(data);
                }
            });
        });

        columns.push({
            data: 'is_active',
            title: CODE_COLUMN_MAP.is_active.label,
            visible: true,
            className: 'text-center',
            headerClassName: 'text-center',
            defaultContent: '',
            settingsKey: 'is_active',
            render(data, type, row) {
                if (type !== 'display') return data;
                const active = Number(data) === 1;
                return `
                    <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                        <input type="checkbox"
                               class="form-check-input code-active-toggle"
                               data-id="${escapeHtml(row.id || '')}"
                               ${active ? 'checked' : ''}
                               aria-label="상태 변경">
                    </div>
                `;
            }
        });

        columns.push({
            data: null,
            title: '관리',
            className: 'text-center no-colvis',
            headerClassName: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render(_data, type, row) {
                if (type !== 'display') return '';
                return `
                    <button type="button"
                            class="btn btn-outline-primary btn-sm code-edit-btn"
                            data-id="${escapeHtml(row.id || '')}">
                        수정
                    </button>
                `;
            }
        });

        return columns;
    }

    function bindTableEvents($) {
        $('#code-table tbody')
            .on('dblclick', 'tr', async function () {
                const row = codeTable.row(this).data();
                if (!row?.id) return;
                await openEditById(row.id, row);
            });

        $('#code-table tbody')
            .on('change', '.code-active-toggle', async function (event) {
                event.stopPropagation();
                const id = this.dataset.id;
                const active = this.checked;
                if (!id) return;

                this.disabled = true;

                try {
                    await updateCodeActive(id, active);
                    codeTable?.ajax.reload(null, false);
                    AppCore?.notify?.('success', active ? '사용으로 변경되었습니다.' : '미사용으로 변경되었습니다.');
                } catch (error) {
                    console.error(error);
                    this.checked = !active;
                    AppCore?.notify?.('error', error.message || '상태 변경에 실패했습니다.');
                } finally {
                    this.disabled = false;
                }
            });

        $('#code-table tbody')
            .on('click', '.code-edit-btn', async function (event) {
                event.stopPropagation();
                const id = this.dataset.id;
                if (!id) return;
                const row = codeTable.row($(this).closest('tr')).data();
                await openEditById(id, row);
            });
    }

    async function openEditById(id, rowData = null) {
        if (rowData) {
            openEditModal(rowData);
        } else {
            openEditModal({ id });
        }

        try {
            const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
            const json = await res.json();

            if (!json.success || !json.data) {
                AppCore?.notify?.('error', json.message || '기준정보 상세 조회에 실패했습니다.');
                return;
            }

            openEditModal(json.data);
            await loadReferenceSummary(id);
        } catch (error) {
            console.error(error);
            AppCore?.notify?.('error', '서버 오류가 발생했습니다.');
        }
    }

    async function fetchCodeDetail(id) {
        const res = await fetch(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
        const json = await res.json();

        if (!json.success || !json.data) {
            throw new Error(json.message || '기준정보 상세 조회에 실패했습니다.');
        }

        return json.data;
    }

    async function loadReferenceSummary(id) {
        const summary = document.getElementById('codeReferenceSummary');
        if (!summary) return;
        summary.textContent = '참조 상태 확인 중...';
        try {
            const response = await fetch(`${API.REFERENCES}?id=${encodeURIComponent(id)}`);
            const json = await response.json();
            if (!json.success || !json.data?.checked) {
                summary.textContent = '참조 상태를 확인할 수 없습니다.';
                return;
            }
            const references = Array.isArray(json.data.references) ? json.data.references : [];
            summary.textContent = references.length === 0
                ? '참조 중인 업무 데이터가 없습니다.'
                : `참조 중: ${references.map((item) => `${item.label} ${item.count}건`).join(', ')}`;
        } catch (error) {
            console.error(error);
            summary.textContent = '참조 상태를 확인할 수 없습니다.';
        }
    }

    async function updateCodeActive(id, active) {
        const data = await fetchCodeDetail(id);
        const formData = new FormData();

        Object.entries(data).forEach(([key, value]) => {
            formData.set(key, value ?? '');
        });

        formData.set('id', id);
        formData.set('code_group', normalizeCodeGroup(data.code_group || ''));
        formData.set('group_name', String(data.group_name || '').trim());
        formData.set('code', String(data.code || '').trim().toUpperCase());
        formData.set('code_name', String(data.code_name || '').trim());
        formData.set('is_active', active ? '1' : '0');

        const res = await fetch(API.SAVE, {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (!json.success) {
            throw new Error(json.message || '상태 변경에 실패했습니다.');
        }

        return json;
    }

    function bindModalEvents($) {
        $(document).off('submit', '#codeForm');
        $(document).on('submit', '#codeForm', function (event) {
            event.preventDefault();

            const formData = new FormData(this);
            formData.set('is_active', codeModalEls.isActive?.checked ? '1' : '0');
            const codeGroup = normalizeCodeGroup(getModalCodeGroupValue());
            const groupName = String(formData.get('group_name') || '').trim();
            const code = String(formData.get('code') || '').trim();
            const codeName = String(formData.get('code_name') || '').trim();

            if (!validateCodeModalRequiredPolicies()) {
                return;
            }

            if (!isValidCodeGroup(codeGroup)) {
                AppCore?.notify?.('warning', '코드그룹은 영문과 _만 사용할 수 있습니다.');
                return;
            }

            formData.set('code_group', codeGroup);
            formData.set('group_name', groupName);
            formData.set('code', code.toUpperCase());

            const extraData = String(formData.get('extra_data') || '').trim();
            if (extraData) {
                try {
                    JSON.parse(extraData);
                } catch (error) {
                    AppCore?.notify?.('warning', '추가 속성은 올바른 JSON 형식이어야 합니다.');
                    return;
                }
            }

            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            $.ajax({
                url: API.SAVE,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done((res) => {
                if (res.success) {
                    codeModal?.hide();
                    codeTable?.ajax.reload(null, false);
                    AppCore?.notify?.('success', '저장 완료');
                } else {
                    AppCore?.notify?.('error', res.message || '저장 실패');
                }
            })
            .fail(() => AppCore?.notify?.('error', '서버 오류가 발생했습니다.'))
            .always(() => {
                if (submitButton) submitButton.disabled = false;
            });
        });

        $('#btnDeleteCode').on('click', function () {
            const id = $('#modal_code_id').val();
            if (!id || !confirm('영구삭제하시겠습니까? 삭제한 코드는 복구할 수 없습니다.')) return;

            deleteCodeById($, id, true);
        });

        $('#modal_code_code').on('input', function () {
            this.value = this.value.toUpperCase();
        });

        $('#modal_code_group, #modal_code_group_input').on('input change', function () {
            syncCodeGroupInputs(this);
        });

        $('#btnBackCodeGroupSelect').on('click', function () {
            showCodeGroupSelect('');
        });

        $('#codeQuickForm')
            .off('submit.codeQuickForm')
            .on('submit.codeQuickForm', async function (event) {
                event.preventDefault();
                await saveQuickCode(this);
            });

        $('#codeQuickForm [name="code_group"]')
            .off('change.codeQuickGroup')
            .on('change.codeQuickGroup', function () {
                syncQuickGroupInputs(this);
            });

        $('#codeQuickForm [data-role="quick-code-group-input"]')
            .off('input.codeQuickGroupInput')
            .on('input.codeQuickGroupInput', function () {
                syncQuickGroupInputs(this);
            });

        $('#codeQuickForm [data-role="quick-code-group-back"]')
            .off('click.codeQuickGroupBack')
            .on('click.codeQuickGroupBack', function () {
                showQuickCodeGroupSelect('');
            });

        $('#codeQuickForm [name="code"]')
            .off('input.codeQuickCode')
            .on('input.codeQuickCode', function () {
                this.value = this.value.toUpperCase();
            });

        $('#codeQuickForm [data-role="detail"]')
            .off('click.codeQuickDetail')
            .on('click.codeQuickDetail', function () {
                const values = getQuickValues();
                codeQuickModal?.hide();
                openCreateModal(values);
            });

    }

    function openQuickCreateModal(initialValues = {}) {
        resetQuickForm(initialValues);
        codeQuickModal?.show();
    }

    function resetQuickForm(values = {}) {
        codeQuickEls.form?.reset();
        if (codeQuickEls.title) codeQuickEls.title.textContent = '기준정보 빠른 추가';
        renderQuickGroupOptions(values.code_group || '');
        if (codeQuickEls.groupName) {
            codeQuickEls.groupName.value = values.group_name || getCodeGroupName(values.code_group || '') || '';
        }
        if (codeQuickEls.code) codeQuickEls.code.value = String(values.code || '').toUpperCase();
        if (codeQuickEls.codeName) codeQuickEls.codeName.value = values.code_name || '';
        if (codeQuickEls.message) codeQuickEls.message.textContent = '';
    }

    function getQuickValues() {
        return {
            code_group: normalizeCodeGroup(getQuickCodeGroupValue()),
            group_name: String(codeQuickEls.groupName?.value || '').trim(),
            is_active: '1',
            code: String(codeQuickEls.code?.value || '').trim().toUpperCase(),
            code_name: String(codeQuickEls.codeName?.value || '').trim(),
            note: '',
            memo: ''
        };
    }

    async function saveQuickCode(form) {
        const values = getQuickValues();

        if (!validateQuickCodeRequiredPolicies(values)) {
            return;
        }

        if (!isValidCodeGroup(values.code_group)) {
            setQuickMessage('코드그룹은 영문과 _만 사용할 수 있습니다.');
            return;
        }

        const submitButton = form?.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        setQuickMessage('');

        try {
            const formData = new FormData();
            Object.entries(values).forEach(([key, value]) => {
                formData.set(key, value ?? '');
            });

            const res = await fetch(API.SAVE, {
                method: 'POST',
                body: formData
            });
            const json = await res.json();

            if (!json?.success) {
                setQuickMessage(json?.message || '저장에 실패했습니다.');
                return;
            }

            mergeCodeGroups([{ code_group: values.code_group, group_name: values.group_name }]);
            codeQuickModal?.hide();
            codeTable?.ajax.reload(null, false);
            AppCore?.notify?.('success', '저장 완료');
        } catch (error) {
            console.error(error);
            setQuickMessage('저장 중 오류가 발생했습니다.');
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    }

    function setQuickMessage(message = '') {
        if (codeQuickEls.message) codeQuickEls.message.textContent = message;
    }

    function syncQuickGroupName(codeGroup) {
        if (!codeQuickEls.groupName) return;
        const groupName = getCodeGroupName(codeGroup);
        if (groupName) {
            codeQuickEls.groupName.value = groupName;
        }
    }

    function renderQuickGroupOptions(selectedGroup = '') {
        const select = codeQuickEls.codeGroup;
        if (!select) return;

        const normalizedSelected = normalizeCodeGroup(selectedGroup);
        const groups = Array.from(new Set([...codeGroups, normalizedSelected].filter(Boolean))).sort();

        select.innerHTML = '<option value="">선택</option>';
        groups.forEach((group) => {
            const option = document.createElement('option');
            option.value = group;
            option.textContent = codeGroupNames[group] ? `${codeGroupNames[group]} (${group})` : group;
            select.appendChild(option);
        });

        const create = document.createElement('option');
        create.value = NEW_CODE_GROUP_VALUE;
        create.textContent = '+ 신규 코드그룹 생성';
        select.appendChild(create);

        if (normalizedSelected && groups.includes(normalizedSelected)) {
            showQuickCodeGroupSelect(normalizedSelected);
        } else {
            showQuickCodeGroupSelect('');
        }
    }

    function getQuickCodeGroupValue() {
        if (codeQuickEls.groupInputWrap && !codeQuickEls.groupInputWrap.classList.contains('d-none')) {
            return codeQuickEls.groupInput?.value || '';
        }

        return codeQuickEls.codeGroup?.value === NEW_CODE_GROUP_VALUE ? '' : codeQuickEls.codeGroup?.value || '';
    }

    function syncQuickGroupInputs(source) {
        if (source === codeQuickEls.codeGroup && codeQuickEls.codeGroup?.value === NEW_CODE_GROUP_VALUE) {
            showQuickCodeGroupInput('');
            return;
        }

        if (source === codeQuickEls.codeGroup) {
            syncQuickGroupName(codeQuickEls.codeGroup?.value || '');
            return;
        }

        if (source === codeQuickEls.groupInput) {
            codeQuickEls.groupInput.value = normalizeCodeGroup(codeQuickEls.groupInput.value);
            syncQuickGroupName(codeQuickEls.groupInput.value);
        }
    }

    function showQuickCodeGroupSelect(value = '') {
        const normalized = normalizeCodeGroup(value);
        if (codeQuickEls.groupInputWrap) codeQuickEls.groupInputWrap.classList.add('d-none');
        if (codeQuickEls.codeGroup) {
            codeQuickEls.codeGroup.classList.remove('d-none');
            codeQuickEls.codeGroup.disabled = false;
            codeQuickEls.codeGroup.value = Array.from(codeQuickEls.codeGroup.options).some((option) => option.value === normalized)
                ? normalized
                : '';
        }
        if (codeQuickEls.groupInput) {
            codeQuickEls.groupInput.value = '';
        }
        syncQuickGroupName(normalized);
    }

    function showQuickCodeGroupInput(value = '') {
        if (codeQuickEls.codeGroup) {
            codeQuickEls.codeGroup.classList.add('d-none');
            codeQuickEls.codeGroup.disabled = true;
            codeQuickEls.codeGroup.value = '';
        }
        if (codeQuickEls.groupInputWrap) codeQuickEls.groupInputWrap.classList.remove('d-none');
        if (codeQuickEls.groupInput) {
            codeQuickEls.groupInput.value = normalizeCodeGroup(value);
            codeQuickEls.groupInput.focus();
        }
        syncQuickGroupName(value);
    }

    function openCreateModal(initialValues = {}) {
        resetForm();
        codeModalEls.title.textContent = '기준정보 등록';
        codeModalEls.deleteBtn.style.display = 'none';
        const referenceSummary = document.getElementById('codeReferenceSummary');
        if (referenceSummary) referenceSummary.textContent = '';
        fillForm({
            ...initialValues,
            id: '',
            extra_data: initialValues.extra_data || '{}'
        });
        codeModal?.show();
    }

    function openEditModal(data = {}) {
        resetForm();
        codeModalEls.title.textContent = '기준정보 수정';
        codeModalEls.deleteBtn.style.display = data?.id ? '' : 'none';
        fillForm(data);
        codeModal?.show();
    }

    function resetForm() {
        const form = codeModalEls.form || document.getElementById('codeForm');
        if (form) form.reset();

        if (codeModalEls.id) codeModalEls.id.value = '';
        if (codeModalEls.groupName) codeModalEls.groupName.value = '';
        if (codeModalEls.extraData) codeModalEls.extraData.value = '{}';
        if (codeModalEls.isActive) codeModalEls.isActive.checked = true;
        setModalCodeGroup('');
        if (codeModalEls.deleteBtn) codeModalEls.deleteBtn.style.display = 'none';
        const referenceSummary = document.getElementById('codeReferenceSummary');
        if (referenceSummary) referenceSummary.textContent = '';
        renderCodeSystemInfo();
        codeModalCardCollapses?.reset?.();
    }

    function fillForm(data) {
        if (data.code_group && data.group_name) {
            codeGroupNames[normalizeCodeGroup(data.code_group)] = String(data.group_name).trim();
        }

        Object.entries(data).forEach(([key, value]) => {
            const el = document.getElementById(`modal_code_${key}`);
            if (!el) return;
            if (el.type === 'checkbox') {
                el.checked = Number(value) === 1;
                return;
            }
            el.value = value ?? '';
        });

        setModalCodeGroup(data.code_group ?? '');
        syncGroupNameFromCodeGroup(data.code_group ?? '', data.group_name ?? '');
        renderCodeSystemInfo(data);
    }

    function setModalCodeGroup(value) {
        const normalized = normalizeCodeGroup(value);
        const select = document.getElementById('modal_code_group');
        const input = document.getElementById('modal_code_group_input');

        if (!normalized) {
            showCodeGroupSelect('');
            return;
        }

        const hasOption = select
            ? Array.from(select.options).some((option) => option.value === normalized)
            : false;

        if (hasOption) {
            showCodeGroupSelect(normalized);
            return;
        }

        showCodeGroupInput(normalized);
    }

    function getModalCodeGroupValue() {
        const inputWrap = document.getElementById('modal_code_group_input_wrap');
        const inputValue = document.getElementById('modal_code_group_input')?.value || '';
        const selectValue = document.getElementById('modal_code_group')?.value || '';

        if (inputWrap && !inputWrap.classList.contains('d-none')) {
            return inputValue;
        }

        return selectValue === NEW_CODE_GROUP_VALUE ? '' : selectValue;
    }

    async function loadCodeGroups() {
        try {
            const res = await fetch(`${API.GROUPS}?_=${Date.now()}`, { cache: 'no-store' });
            const json = await res.json();
            const groups = Array.isArray(json.data) ? json.data : [];

            mergeCodeGroups(groups);
        } catch (error) {
            console.error('[code] groups load failed', error);
        }
    }

    function mergeCodeGroups(groups) {
        const merged = new Set(codeGroups);

        groups.forEach((group) => {
            const value = normalizeCodeGroup(group?.code_group ?? group);
            if (value) merged.add(value);

            const groupName = String(group?.group_name || '').trim();
            if (value && groupName) {
                codeGroupNames[value] = groupName;
            }
        });

        codeGroups = Array.from(merged).sort();
        renderGroupOptions(document.getElementById('modal_code_group'), codeGroups, '선택');
        if (codeQuickEls.codeGroup) {
            renderQuickGroupOptions(getQuickCodeGroupValue());
        }
    }

    function renderGroupOptions(select, groups, firstLabel) {
        if (!select) return;

        const currentValue = select.value;
        select.innerHTML = '';

        const first = document.createElement('option');
        first.value = '';
        first.textContent = firstLabel;
        select.appendChild(first);

        groups.forEach((group) => {
            const value = normalizeCodeGroup(group);
            if (!value) return;

            const option = document.createElement('option');
            option.value = value;
            option.textContent = codeGroupNames[value] ? `${codeGroupNames[value]} (${value})` : value;
            select.appendChild(option);
        });

        const create = document.createElement('option');
        create.value = NEW_CODE_GROUP_VALUE;
        create.textContent = '+ 신규 코드그룹 생성';
        select.appendChild(create);

        if (currentValue && Array.from(select.options).some((option) => option.value === currentValue)) {
            select.value = currentValue;
        } else {
            select.value = '';
        }
    }

    function syncCodeGroupInputs(source) {
        const select = document.getElementById('modal_code_group');
        const input = document.getElementById('modal_code_group_input');

        if (!select || !input) return;

        if (source === select && select.value === NEW_CODE_GROUP_VALUE) {
            showCodeGroupInput('');
            return;
        }

        if (source === select && select.value) {
            input.value = '';
            syncGroupNameFromCodeGroup(select.value);
            return;
        }

        if (source === input) {
            input.value = normalizeCodeGroup(input.value);
            syncGroupNameFromCodeGroup(input.value);
        }
    }

    function showCodeGroupSelect(value = '') {
        const select = document.getElementById('modal_code_group');
        const input = document.getElementById('modal_code_group_input');
        const inputWrap = document.getElementById('modal_code_group_input_wrap');

        if (inputWrap) inputWrap.classList.add('d-none');
        if (select) {
            select.classList.remove('d-none');
            select.disabled = false;
            select.required = true;
            select.value = value;
        }
        if (input) {
            input.required = false;
            input.value = '';
        }
        syncGroupNameFromCodeGroup(value);
    }

    function showCodeGroupInput(value = '') {
        const select = document.getElementById('modal_code_group');
        const input = document.getElementById('modal_code_group_input');
        const inputWrap = document.getElementById('modal_code_group_input_wrap');

        if (select) {
            select.classList.add('d-none');
            select.disabled = true;
            select.required = false;
            select.value = '';
        }
        if (inputWrap) inputWrap.classList.remove('d-none');
        if (input) {
            input.required = true;
            input.value = normalizeCodeGroup(value);
            input.focus();
        }
        syncGroupNameFromCodeGroup(value);
    }

    function syncGroupNameFromCodeGroup(codeGroup, fallback = '') {
        const input = codeModalEls.groupName || document.getElementById('modal_code_group_name');
        if (!input) return;

        const normalized = normalizeCodeGroup(codeGroup);
        const groupName = getCodeGroupName(normalized) || fallback || '';

        input.value = groupName;
    }

    function getCodeGroupName(codeGroup) {
        return codeGroupNames[normalizeCodeGroup(codeGroup)] || '';
    }

    function normalizeCodeGroup(value) {
        return String(value || '').trim().replace(/\s+/g, '').toUpperCase();
    }

    function isValidCodeGroup(value) {
        return /^[A-Z_]+$/.test(value);
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
        document.querySelectorAll('.admin-date').forEach(input => {
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
        if (document.__codeDateIconPickerBound) return;
        document.__codeDateIconPickerBound = true;

        document.addEventListener('click', function (event) {
            const icon = event.target.closest('.date-icon');
            if (!icon) return;

            const wrap = icon.closest('.date-input, .date-input-wrap');
            const input = wrap ? wrap.querySelector('input.admin-date, input[name="dateStart"], input[name="dateEnd"]') : null;
            if (!input) return;

            event.preventDefault();
            event.stopPropagation();
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

        picker.open({ anchor: input });
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
            AppCore?.notify?.('warning', '올바른 날짜를 입력하세요.');
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

    function deleteCodeById($, id, closeModal) {
        $.post(API.DELETE, { id })
            .done((res) => {
                if (res.success) {
                    if (closeModal) codeModal?.hide();
                    codeTable?.ajax.reload(null, false);
                    AppCore?.notify?.('success', '영구삭제 완료');
                } else {
                    AppCore?.notify?.('error', res.message || '영구삭제 실패');
                }
            })
            .fail(() => AppCore?.notify?.('error', '서버 오류가 발생했습니다.'));
    }

    function updateCount(count) {
        const el = document.getElementById('codeCount');
        if (el) el.textContent = `총 ${count ?? 0}건`;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
})();
