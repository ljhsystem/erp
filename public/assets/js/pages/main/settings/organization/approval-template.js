import { createDataTable, setTableSelectedRow } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import { writeSystemUserSettingsStorage } from '/public/assets/js/common/user-settings/systemUserSettingsStorage.js';
import { bindModalCardCollapses } from '/public/assets/js/common/modal-card-collapse.js';

const API = {
    TEMPLATE_LIST: '/api/settings/organization/approval/template/list',
    TEMPLATE_SAVE: '/api/settings/organization/approval/template/save',
    TEMPLATE_DELETE: '/api/settings/organization/approval/template/delete',
    TEMPLATE_REORDER: '/api/settings/organization/approval/template/reorder',
    STEP_LIST: '/api/settings/organization/approval/step/list',
    STEP_SAVE: '/api/settings/organization/approval/step/save',
    STEP_REORDER: '/api/settings/organization/approval/step/reorder',
    STEP_DELETE: '/api/settings/organization/approval/step/delete',
    ROLE_LIST: '/api/settings/organization/role/list',
    EMPLOYEE_LIST: '/api/settings/organization/employee/list'
};

let selectedTemplateId = '';
let selectedTemplateActive = false;
let selectedStepId = '';
let templateTable = null;
let stepTable = null;
let templateModal = null;
let stepModal = null;
let roleList = [];
let userList = [];
let lastStepReorderGuardAt = 0;
const ROLE_NONE_VALUE = '';
const APPROVER_NONE_VALUE = '';
const TEMPLATE_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.main.settings.organization.approval-template.template-list.v2';
const STEP_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.main.settings.organization.approval-template.step-list.v2';
const TEMPLATE_TABLE_USER_SETTING_PAGE_KEY = 'approval-template-list';
const STEP_TABLE_USER_SETTING_PAGE_KEY = 'approval-template-step';
let approvalPolicyBound = false;
const TEMPLATE_MODAL_FIELD_POLICIES = Object.freeze([
    { selector: '#tpl-edit-name', key: 'template_name', fallback: '\uD15C\uD50C\uB9BF\uBA85' },
    { selector: '#tpl-edit-doc-type', key: 'document_type', fallback: '\uBB38\uC11C \uC720\uD615' },
    { selector: '#tpl-edit-desc', key: 'description', fallback: '\uC124\uBA85' },
    { selector: '#tpl-edit-active', key: 'is_active', fallback: '\uD65C\uC131' },
]);
const STEP_MODAL_FIELD_POLICIES = Object.freeze([
    { selector: '#step-edit-name', key: 'step_name', fallback: '\uB2E8\uACC4\uBA85' },
    { selector: '#step-edit-type', key: 'step_type', fallback: '\uB2E8\uACC4\uC720\uD615' },
    { selector: '#step-edit-role', key: 'role_id', fallback: '\uACB0\uC7AC \uC5ED\uD560' },
    { selector: '#step-edit-user', key: 'approver_id', fallback: '\uD2B9\uC815 \uACB0\uC7AC\uC790' },
    { selector: '#step-edit-active', key: 'is_active', fallback: '\uD65C\uC131' },
]);

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function normalize(value) {
    return String(value ?? '').trim().replace(/\s+/g, ' ');
}

function normalizeApproverId(value) {
    const normalized = normalize(value);
    return normalized === APPROVER_NONE_VALUE ? '' : normalized;
}

function normalizeRoleId(value) {
    const normalized = normalize(value);
    return normalized === ROLE_NONE_VALUE ? '' : normalized;
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

function statusBadge(value) {
    return String(value) === '1'
        ? '<span class="badge bg-success">\uD65C\uC131</span>'
        : '<span class="badge bg-secondary">\uBE44\uD65C\uC131</span>';
}

function getRows(json) {
    return Array.isArray(json?.data) ? json.data : [];
}

function userSettingPageKeyForStorageKey(storageKey) {
    return String(storageKey || '').trim() === STEP_TABLE_SETTINGS_STORAGE_KEY
        ? STEP_TABLE_USER_SETTING_PAGE_KEY
        : TEMPLATE_TABLE_USER_SETTING_PAGE_KEY;
}

function sanitizeApprovalTableSettingsState(state = null, deprecatedKeys = []) {
    if (!state || typeof state !== 'object') {
        return state;
    }

    const deprecatedKeySet = new Set(
        (Array.isArray(deprecatedKeys) ? deprecatedKeys : [])
            .map((key) => String(key || '').trim())
            .filter(Boolean)
    );
    let changed = false;
    const nextState = { ...state };

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
        if (!Array.isArray(nextState[key])) {
            return;
        }

        const filtered = nextState[key]
            .map((item) => String(item || '').trim())
            .filter((item) => item !== '' && !deprecatedKeySet.has(item));

        if (filtered.length !== nextState[key].length) {
            nextState[key] = filtered;
            changed = true;
        }
    });

    ['columnDisplayName', 'columnRequirementPolicy'].forEach((key) => {
        if (!nextState[key] || typeof nextState[key] !== 'object') {
            return;
        }

        const filtered = Object.fromEntries(
            Object.entries(nextState[key]).filter(([itemKey]) => !deprecatedKeySet.has(String(itemKey || '').trim()))
        );

        if (Object.keys(filtered).length !== Object.keys(nextState[key]).length) {
            nextState[key] = filtered;
            changed = true;
        }
    });

    return changed ? nextState : state;
}

function persistApprovalTableSettingsState(storageKey, userSettingPageKey, state = null) {
    if (!state || typeof state !== 'object') {
        return;
    }

    writeSystemUserSettingsStorage(
        storageKey,
        {
            ...state,
            updatedAt: new Date().toISOString(),
        },
        {
            userSettingPageKey,
            settingType: 'TABLE',
        }
    );
}

function normalizeApprovalTableSettingsState(storageKey, userSettingPageKey, deprecatedKeys = []) {
    const currentState = readDataTableSettingsState(storageKey, {
        userSettingPageKey,
    });
    const sanitizedState = sanitizeApprovalTableSettingsState(currentState, deprecatedKeys);
    if (!sanitizedState || sanitizedState === currentState) {
        return;
    }

    persistApprovalTableSettingsState(storageKey, userSettingPageKey, sanitizedState);
}

function currentPolicyState(storageKey) {
    return readDataTableSettingsState(storageKey, {
        userSettingPageKey: userSettingPageKeyForStorageKey(storageKey),
    }) || {};
}

function approvalFieldLabel(storageKey, key, fallback = '') {
    const normalizedKey = String(key || '').trim();
    return resolveDataTableColumnDisplayName(
        { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
        currentPolicyState(storageKey),
        normalizedKey || fallback
    );
}

function approvalFieldRequirement(storageKey, key) {
    const normalizedKey = String(key || '').trim();
    return resolveDataTableColumnRequirementPolicy(
        { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
        currentPolicyState(storageKey)
    );
}

function approvalFieldStarMarkup(storageKey, key) {
    const policy = approvalFieldRequirement(storageKey, key);
    if (policy === 'required') {
        return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
    }
    if (policy === 'optional') {
        return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
    }
    return '';
}

function focusApprovalPolicyField(selector) {
    const field = document.querySelector(selector);
    if (!field) {
        return;
    }

    if (typeof field.focus === 'function') {
        field.focus();
    }

    if (window.jQuery && window.jQuery(field).hasClass('select2-hidden-accessible')) {
        window.jQuery(field).select2('open');
    }
}

function collectTemplateModalValues() {
    return {
        template_name: normalize($('#tpl-edit-name').val()),
        document_type: normalize($('#tpl-edit-doc-type').val()),
        description: normalize($('#tpl-edit-desc').val()),
        is_active: $('#tpl-edit-active').is(':checked') ? '1' : '0',
    };
}

function validateTemplateRequiredPolicies() {
    const values = collectTemplateModalValues();

    for (const field of TEMPLATE_MODAL_FIELD_POLICIES) {
        if (approvalFieldRequirement(TEMPLATE_TABLE_SETTINGS_STORAGE_KEY, field.key) !== 'required') {
            continue;
        }

        if (String(values[field.key] ?? '').trim() !== '') {
            continue;
        }

        notify('warning', `${approvalFieldLabel(TEMPLATE_TABLE_SETTINGS_STORAGE_KEY, field.key, field.key)} \uD56D\uBAA9\uC744 \uC785\uB825\uD574 \uC8FC\uC138\uC694.`);
        focusApprovalPolicyField(field.selector);
        return false;
    }

    return true;
}

function collectStepModalValues() {
    return {
        step_name: normalize($('#step-edit-name').val()),
        step_type: normalize($('#step-edit-type').val()),
        role_id: normalizeRoleId($('#step-edit-role').val()),
        approver_id: normalizeApproverId($('#step-edit-user').val()),
        is_active: $('#step-edit-active').is(':checked') ? '1' : '0',
    };
}

function validateStepRequiredPolicies() {
    const values = collectStepModalValues();

    for (const field of STEP_MODAL_FIELD_POLICIES) {
        if (approvalFieldRequirement(STEP_TABLE_SETTINGS_STORAGE_KEY, field.key) !== 'required') {
            continue;
        }

        if (String(values[field.key] ?? '').trim() !== '') {
            continue;
        }

        notify('warning', `${approvalFieldLabel(STEP_TABLE_SETTINGS_STORAGE_KEY, field.key, field.key)} \uD56D\uBAA9\uC744 \uC785\uB825\uD574 \uC8FC\uC138\uC694.`);
        focusApprovalPolicyField(field.selector);
        return false;
    }

    return true;
}

function findModalLabel(fieldSelector, root = document) {
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

    return field.closest('label.form-label, label.form-check-label') || null;
}

function applyTemplateModalPolicyLabels(root = document) {
    TEMPLATE_MODAL_FIELD_POLICIES.forEach((field) => {
        const labelEl = findModalLabel(field.selector, root);
        if (!labelEl) return;

        const displayName = approvalFieldLabel(TEMPLATE_TABLE_SETTINGS_STORAGE_KEY, field.key, field.fallback);
        const starMarkup = approvalFieldStarMarkup(TEMPLATE_TABLE_SETTINGS_STORAGE_KEY, field.key);
        labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
    });
}

function applyStepModalPolicyLabels(root = document) {
    STEP_MODAL_FIELD_POLICIES.forEach((field) => {
        const labelEl = findModalLabel(field.selector, root);
        if (!labelEl) return;

        const displayName = approvalFieldLabel(STEP_TABLE_SETTINGS_STORAGE_KEY, field.key, field.fallback);
        const starMarkup = approvalFieldStarMarkup(STEP_TABLE_SETTINGS_STORAGE_KEY, field.key);
        labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
    });
}

function bindApprovalPolicySync() {
    if (approvalPolicyBound) return;
    approvalPolicyBound = true;

    normalizeApprovalTableSettingsState(
        TEMPLATE_TABLE_SETTINGS_STORAGE_KEY,
        TEMPLATE_TABLE_USER_SETTING_PAGE_KEY,
        ['__legacy_template_status']
    );
    normalizeApprovalTableSettingsState(
        STEP_TABLE_SETTINGS_STORAGE_KEY,
        STEP_TABLE_USER_SETTING_PAGE_KEY,
        ['__legacy_step_status']
    );

    document.addEventListener('datatable-settings:updated', (event) => {
        const storageKey = String(event?.detail?.storageKey || '').trim();
        if (
            storageKey
            && storageKey !== TEMPLATE_TABLE_SETTINGS_STORAGE_KEY
            && storageKey !== STEP_TABLE_SETTINGS_STORAGE_KEY
        ) {
            return;
        }

        if (storageKey === TEMPLATE_TABLE_SETTINGS_STORAGE_KEY) {
            normalizeApprovalTableSettingsState(
                TEMPLATE_TABLE_SETTINGS_STORAGE_KEY,
                TEMPLATE_TABLE_USER_SETTING_PAGE_KEY,
                ['__legacy_template_status']
            );
        }

        if (storageKey === STEP_TABLE_SETTINGS_STORAGE_KEY) {
            normalizeApprovalTableSettingsState(
                STEP_TABLE_SETTINGS_STORAGE_KEY,
                STEP_TABLE_USER_SETTING_PAGE_KEY,
                ['__legacy_step_status']
            );
        }

        applyTemplateModalPolicyLabels(document);
        applyStepModalPolicyLabels(document);
    });
}

function hideLegacyHeaderButtons() {
    document.getElementById('btn-create-template')?.remove();
    document.getElementById('btn-add-step')?.remove();
}

function setStepAddButtonDisabled(disabled = true) {
    const button = document.querySelector('#template-steps-table_wrapper .dt-add-step-btn');
    if (!button) {
        return;
    }

    button.disabled = !!disabled;
    button.classList.toggle('disabled', !!disabled);
    button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
}

function syncStepReorderLock() {
    const $body = $('#template-steps-table tbody');
    if ($body.length && typeof $body.sortable === 'function' && $body.data('ui-sortable')) {
        $body.sortable(selectedTemplateActive ? 'disable' : 'enable');
    }
    const deleteButton = document.querySelector('#template-steps-table_wrapper .dt-soft-delete-btn');
    if (deleteButton) {
        const noSelection = (stepTable?.getSelectedIds?.() || []).length === 0;
        const disabled = selectedTemplateActive || noSelection;
        deleteButton.disabled = disabled;
        deleteButton.classList.toggle('disabled', disabled);
        deleteButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    }
}

function hideStepLengthControl() {
    const wrapper = document.getElementById('template-steps-table_wrapper');
    if (!wrapper) {
        return;
    }

    wrapper.querySelectorAll('.dataTables_length').forEach((node) => {
        node.style.display = 'none';
    });
}

async function init() {
    hideLegacyHeaderButtons();
    bindApprovalPolicySync();
    initModals();
    await initTemplateTable();
    await initStepTable();
    bindTemplateEvents();
    bindStepEvents();
    bindLayoutEvents();
    preloadSelectLists();
}

function initModals() {
    const templateModalEl = document.getElementById('modal-template-edit');
    const stepModalEl = document.getElementById('modal-step-edit');

    if (templateModalEl) {
        bindModalCardCollapses(templateModalEl, { resetOnShow: true });
        templateModal = new bootstrap.Modal(templateModalEl, { focus: false });
        templateModalEl.addEventListener('hidden.bs.modal', () => {
            resetTemplateModal();
            applyTemplateModalPolicyLabels(document);
        });
        templateModalEl.addEventListener('shown.bs.modal', () => {
            applyTemplateModalPolicyLabels(document);
        });
    }

    if (stepModalEl) {
        bindModalCardCollapses(stepModalEl, { resetOnShow: true });
        stepModal = new bootstrap.Modal(stepModalEl, { focus: false });
        stepModalEl.addEventListener('hidden.bs.modal', () => {
            resetStepModal();
            applyStepModalPolicyLabels(document);
        });
        stepModalEl.addEventListener('shown.bs.modal', () => {
            applyStepModalPolicyLabels(document);
        });
    }

    applyTemplateModalPolicyLabels(document);
    applyStepModalPolicyLabels(document);
}

async function initTemplateTable() {
    templateTable = await createDataTable({
        tableSelector: '#template-list-table',
        api: API.TEMPLATE_LIST,
        tableSettings: {
            pageKey: 'main.settings.organization.approval-template.template-list',
            userSettingPageKey: TEMPLATE_TABLE_USER_SETTING_PAGE_KEY,
            tableKey: 'approval-template-list-table',
            storageKey: TEMPLATE_TABLE_SETTINGS_STORAGE_KEY,
            metaDomain: 'approval-template',
            tableLabel: '\uACB0\uC7AC \uD15C\uD50C\uB9BF\uBAA9\uB85D',
            title: '\uACB0\uC7AC \uD15C\uD50C\uB9BF\uBAA9\uB85D \uD14C\uC774\uBE14 \uC124\uC815',
            defaultSortSettings: [{ key: 'sort_no', dir: 'asc' }],
            resetOnColumnSchemaChange: true,
        },
        defaultOrder: [[2, 'asc']],
        pageLength: 100,
        autoWidth: false,
        fixedLayout: false,
        cellSearchFill: false,
        rowReorder: false,
        selectionColumn: { widthResizable: true },
        selectable: true,
        deleteButton: true,
        deleteApi: API.TEMPLATE_DELETE,
        buttons: [
            {
                text: '신규등록',
                className: 'btn btn-primary btn-sm',
                action: function () {
                    openTemplateModal('create');
                }
            }
        ],
        columns: [
            {
                title: '<i class="bi bi-list"></i>',
                data: null,
                settingsKey: '__reorder',
                className: 'text-center reorder-handle no-colvis',
                width: '44px',
                widthResizable: true,
                orderable: false,
                searchable: false,
                render: () => '<i class="bi bi-list"></i>'
            },
            { title: 'ID', data: 'id', visible: false, searchable: false, settingsKey: 'id', render: (value) => escapeHtml(value) },
            { title: '\uC21C\uBC88', data: 'sort_no', className: 'text-center dt-sequence-column', width: '80px', render: (value) => escapeHtml(value) },
            { title: '\uD15C\uD50C\uB9BF\uBA85', data: 'template_name', width: '180px', render: (value) => escapeHtml(value) },
            { title: '\uBB38\uC11C\uC720\uD615', data: 'document_type', width: '160px', render: (value) => escapeHtml(value) },
            { title: '\uC124\uBA85', data: 'description', width: '220px', defaultContent: '', render: (value) => escapeHtml(value) },
            {
                title: '\uC0C1\uD0DC',
                data: 'is_active',
                className: 'text-center',
                width: '90px',
                render: (value, type) => (type === 'display' ? statusBadge(value) : escapeHtml(value))
            },
            { title: '\uD15C\uD50C\uB9BF\uD0A4', data: 'template_key', width: '160px', visible: false, render: (value) => escapeHtml(value) },
            { title: '\uC0DD\uC131\uC77C\uC2DC', data: 'created_at', width: '160px', visible: false, render: (value) => escapeHtml(value) },
            {
                title: '\uC0DD\uC131\uC790',
                data: 'created_by',
                settingsKey: 'created_by',
                visible: false,
                width: '140px',
                widthResizable: true,
                render: (_value, _type, row) => escapeHtml(actorDisplay(row, 'created_by'))
            },
            { title: '\uC218\uC815\uC77C\uC2DC', data: 'updated_at', width: '160px', visible: false, render: (value) => escapeHtml(value) },
            {
                title: '\uC218\uC815\uC790',
                data: 'updated_by',
                settingsKey: 'updated_by',
                visible: false,
                width: '140px',
                widthResizable: true,
                render: (_value, _type, row) => escapeHtml(actorDisplay(row, 'updated_by'))
            },
            {
                title: '\uAD00\uB9AC',
                settingsTitle: '\uAD00\uB9AC',
                data: null,
                settingsKey: '__actions',
                settingsVirtualType: 'system',
                __dtColumnKind: 'virtual',
                className: 'text-center',
                headerClassName: 'text-center',
                width: '72px',
                widthResizable: true,
                orderable: false,
                searchable: false,
                render: (_value, type, row) => type === 'display'
                    ? `<button type="button" class="btn btn-outline-primary btn-sm ap-template-edit-btn" data-template-id="${escapeHtml(row?.id || '')}">\uC218\uC815</button>`
                    : ''
            }
        ],
        dataSrc(json) {
            return getRows(json);
        }
    });

    bindRowReorder(templateTable, {
        api: API.TEMPLATE_REORDER,
        includeAppliedRows: true,
        changedRowsOnly: false,
        canReorder: () => approvalReorderPermission(templateTable),
        onSuccess() {
            notify('success', '템플릿 순번이 저장되었습니다.');
            reloadTemplateTable();
        },
        onError(res) {
            notify('error', res?.message || '템플릿 순번 저장에 실패했습니다.');
            reloadTemplateTable();
        }
    });

    templateTable.on('draw.dt xhr.dt', () => {
        updateTemplateCount();
        markSelectedTemplateRow();
    });
}


async function initStepTable() {
    stepTable = await createDataTable({
        tableSelector: '#template-steps-table',
        api: API.STEP_LIST,
        tableSettings: {
            pageKey: 'main.settings.organization.approval-template.step-list',
            userSettingPageKey: STEP_TABLE_USER_SETTING_PAGE_KEY,
            tableKey: 'approval-template-step-table',
            storageKey: STEP_TABLE_SETTINGS_STORAGE_KEY,
            metaDomain: 'approval-template-step',
            tableLabel: '\uACB0\uC7AC \uB2E8\uACC4\uAD6C\uC131',
            title: '\uACB0\uC7AC \uB2E8\uACC4\uAD6C\uC131 \uD14C\uC774\uBE14 \uC124\uC815',
            defaultSortSettings: [{ key: 'sort_no', dir: 'asc' }],
            resetOnColumnSchemaChange: true,
        },
        defaultOrder: [[3, 'asc']],
        pageLength: 100,
        // 선택한 템플릿에 따라 반복 조회되는 종속 테이블이므로 페이지 진입용
        // 공용 스피너 hold를 사용하지 않고 DataTable의 processing 표시만 사용한다.
        pageLoading: false,
        autoWidth: false,
        fixedLayout: false,
        cellSearchFill: false,
        rowReorder: false,
        selectionColumn: { widthResizable: true },
        selectable: true,
        deleteButton: true,
        deleteApi: API.STEP_DELETE,
        deletePayload: ({ id }) => ({ step_id: id }),
        buttons: [
            {
                text: '신규등록',
                className: 'btn btn-success btn-sm dt-add-step-btn',
                action: async function () {
                    if (!selectedTemplateId) {
                        return;
                    }

                    await preloadSelectLists();
                    openStepModal('create');
                }
            }
        ],
        ajaxData() {
            return { template_id: selectedTemplateId || '' };
        },
        columns: [
            {
                title: '<i class="bi bi-list"></i>',
                data: null,
                settingsKey: '__reorder',
                className: 'text-center reorder-handle drag-handle no-colvis',
                width: '44px',
                widthResizable: true,
                orderable: false,
                searchable: false,
                render: () => '<i class="bi bi-list"></i>'
            },
            { title: 'ID', data: 'id', visible: false, searchable: false, settingsKey: 'id', render: (value) => escapeHtml(value) },
            { title: 'Template ID', data: 'template_id', visible: false, searchable: false, settingsKey: 'template_id', render: (value) => escapeHtml(value) },
            { title: '\uC21C\uBC88', data: 'sort_no', className: 'text-center step-sequence dt-sequence-column', width: '80px', render: (value) => escapeHtml(value) },
            { title: '\uB2E8\uACC4\uBA85', data: 'step_name', width: '180px', render: (value) => escapeHtml(value) },
            {
                title: '\uB2E8\uACC4\uC720\uD615',
                data: 'step_type',
                width: '120px',
                render(value) {
                    const labels = { SUBMIT: '\uBC1C\uC758', APPROVAL: '\uC2B9\uC778', FINAL_APPROVAL: '\uCD5C\uC885\uC2B9\uC778' };
                    return escapeHtml(labels[value] || value || '-');
                }
            },
            {
                title: '\uACB0\uC7AC \uC5ED\uD560',
                data: 'role_id',
                width: '160px',
                render(value, type, row) {
                    return escapeHtml(row?.role_name || value || '-');
                }
            },
            {
                title: '\uD2B9\uC815 \uACB0\uC7AC\uC790',
                data: 'approver_id',
                width: '180px',
                render(value, type, row) {
                    const name = row?.specific_employee_name || row?.specific_username || value || '';
                    return escapeHtml(name || '-');
                }
            },
            {
                title: '\uC0C1\uD0DC',
                data: 'is_active',
                className: 'text-center',
                width: '90px',
                render: (value, type) => (type === 'display' ? statusBadge(value) : escapeHtml(value))
            },
            { title: '\uC0DD\uC131\uC77C\uC2DC', data: 'created_at', width: '160px', visible: false, render: (value) => escapeHtml(value) },
            {
                title: '\uC0DD\uC131\uC790',
                data: 'created_by',
                width: '140px',
                visible: false,
                render: (_value, _type, row) => escapeHtml(actorDisplay(row, 'created_by'))
            },
            { title: '\uC218\uC815\uC77C\uC2DC', data: 'updated_at', width: '160px', visible: false, render: (value) => escapeHtml(value) },
            {
                title: '\uC218\uC815\uC790',
                data: 'updated_by',
                width: '140px',
                visible: false,
                render: (_value, _type, row) => escapeHtml(actorDisplay(row, 'updated_by'))
            },
            {
                title: '\uAD00\uB9AC',
                settingsTitle: '\uAD00\uB9AC',
                data: null,
                settingsKey: '__actions',
                settingsVirtualType: 'system',
                __dtColumnKind: 'virtual',
                className: 'text-center',
                headerClassName: 'text-center',
                width: '72px',
                widthResizable: true,
                orderable: false,
                searchable: false,
                render: (_value, type, row) => type === 'display'
                    ? `<button type="button" class="btn btn-outline-primary btn-sm ap-step-edit-btn" data-step-id="${escapeHtml(row?.id || '')}">\uC218\uC815</button>`
                    : ''
            }
        ],
        createdRow(row, data) {
            setStepRowData(row, data);
        },
        dataSrc(json) {
            return getRows(json);
        }
    });

    setStepAddButtonDisabled(true);

    stepTable.on('draw.dt xhr.dt', () => {
        updateStepCount();
        markSelectedStepRow();
    });

    bindStepRowReorder();
}

function setStepRowData(row, data) {
    $(row)
        .attr('data-id', data?.id || '')
        .attr('data-sort_no', data?.sort_no || '')
        .attr('data-step_name', data?.step_name || '')
        .attr('data-role_id', data?.role_id || '')
        .attr('data-user_id', data?.approver_id || '')
        .attr('data-active', data?.is_active ?? 1);
}

function bindTemplateEvents() {
    $('#template-list-table tbody').on('click', '.ap-template-edit-btn', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const row = templateTable.row($(this).closest('tr')).data();
        if (row) {
            openTemplateModal('edit', row);
        }
    });

    $('#template-list-table tbody').on('click', 'tr', function () {
        const row = templateTable.row(this).data();
        if (!row) return;

        selectedTemplateId = String(row.id || '');
        selectedTemplateActive = String(row.is_active) === '1';
        selectedStepId = '';

        setTableSelectedRow('#template-list-table', this);

        setStepAddButtonDisabled(selectedTemplateActive);
        syncStepReorderLock();
        $('#ap-selected-template-name').text(`[${row.template_name}]`);

        reloadStepTable();
    });

    $('#template-list-table tbody').on('dblclick', 'tr', function () {
        const row = templateTable.row(this).data();
        if (row) {
            openTemplateModal('edit', row);
        }
    });

    $('#btn-save-template-edit').on('click', saveTemplate);
    $('#btn-delete-template-edit').on('click', deleteTemplate);
}

function bindStepEvents() {
    $('#template-steps-table tbody').on('mousedown', '.reorder-handle', function (event) {
        if (!selectedTemplateActive) return;
        event.preventDefault();
        const now = Date.now();
        if (now - lastStepReorderGuardAt >= 2000) {
            lastStepReorderGuardAt = now;
            notify('warning', '해당 템플릿의 결재흐름을 변경하려면 해당 템플릿을 먼저 비활성화해 주세요.');
        }
    });

    $('#template-steps-table tbody').on('click', 'tr', function () {
        const row = stepTable.row(this).data();
        if (!row) return;

        selectedStepId = String(row.id || '');
        setTableSelectedRow('#template-steps-table', this);
    });

    $('#template-steps-table tbody').on('click', '.ap-step-edit-btn', async function (event) {
        event.preventDefault();
        event.stopPropagation();
        const row = stepTable.row($(this).closest('tr')).data();
        if (!row) return;
        if (selectedTemplateActive) {
            notify('warning', '\uD574\uB2F9 \uD15C\uD50C\uB9BF\uC758 \uACB0\uC7AC\uD750\uB984\uC744 \uBCC0\uACBD\uD558\uB824\uBA74 \uD574\uB2F9 \uD15C\uD50C\uB9BF\uC744 \uBA3C\uC800 \uBE44\uD65C\uC131\uD654\uD574 \uC8FC\uC138\uC694.');
            return;
        }

        await preloadSelectLists();
        openStepModal('edit', row);
    });

    $('#template-steps-table tbody').on('dblclick', 'tr', async function () {
        const row = stepTable.row(this).data();
        if (!row) return;
        if (selectedTemplateActive) {
            notify('warning', '해당 템플릿의 결재흐름을 변경하려면 해당 템플릿을 먼저 비활성화해 주세요.');
            return;
        }

        await preloadSelectLists();
        openStepModal('edit', row);
    });

    $('#btn-save-step-edit').on('click', saveStep);
    $('#btn-delete-step-edit').on('click', deleteStep);
    $('#step-edit-type').on('change', syncStepAssignmentUi);
}

function syncStepAssignmentUi() {
    const isSubmit = normalize($('#step-edit-type').val()) === 'SUBMIT';
    const $role = $('#step-edit-role');
    const $approver = $('#step-edit-user');
    if (isSubmit) {
        resetSelect('#step-edit-role');
        resetSelect('#step-edit-user');
    }
    $role.prop('disabled', isSubmit).closest('.mb-3').toggle(!isSubmit);
    $approver.prop('disabled', isSubmit).closest('.mb-3').toggle(!isSubmit);
}

function openTemplateModal(mode, row = null) {
    resetTemplateModal();

    const isCreate = mode === 'create';
    $('#modal-template-edit .modal-title').text(isCreate ? '\uD15C\uD50C\uB9BF \uB4F1\uB85D' : '\uD15C\uD50C\uB9BF \uC218\uC815');
    $('#btn-delete-template-edit').toggle(!isCreate);

    if (!isCreate && row) {
        $('#tpl-edit-id').val(row.id || '');
        $('#tpl-edit-name').val(normalize(row.template_name));
        $('#tpl-edit-doc-type').val(normalize(row.document_type));
        $('#tpl-edit-desc').val(normalize(row.description || ''));
        $('#tpl-edit-active').prop('checked', String(row.is_active) === '1');
        $('#tpl-info-id').text(normalize(row.id));
        $('#tpl-info-sort-no').text(normalize(row.sort_no));
        $('#tpl-info-created-at').text(normalize(row.created_at));
        $('#tpl-info-created-by').text(actorDisplay(row, 'created_by'));
        $('#tpl-info-updated-at').text(normalize(row.updated_at));
        $('#tpl-info-updated-by').text(actorDisplay(row, 'updated_by'));
        $('#tpl-system-info-card').show();
    } else {
        $('#tpl-edit-active').prop('checked', false);
        $('#tpl-system-info-card').hide();
    }

    templateModal?.show();
}

function resetTemplateModal() {
    $('#tpl-edit-id').val('');
    $('#tpl-edit-name').val('');
    $('#tpl-edit-doc-type').val('');
    $('#tpl-edit-desc').val('');
    $('#tpl-edit-active').prop('checked', false);
    $('#tpl-system-info-card').hide();
    $('#btn-delete-template-edit').hide();
}

async function saveTemplate() {
    if (!validateTemplateRequiredPolicies()) {
        return;
    }

    const id = $('#tpl-edit-id').val();
    const payload = {
        id,
        name: normalize($('#tpl-edit-name').val()),
        document_type: normalize($('#tpl-edit-doc-type').val()),
        description: normalize($('#tpl-edit-desc').val()),
        is_active: $('#tpl-edit-active').is(':checked') ? 1 : 0
    };

    try {
        const res = await $.post(API.TEMPLATE_SAVE, payload);
        if (!res?.success) {
            notify('error', res?.message || '\uC800\uC7A5\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
            return;
        }

        templateModal?.hide();
        reloadTemplateTable(id || res?.id || '');
        notify('success', '\uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
    } catch (err) {
        console.error('[approval] save template failed:', err);
        notify('error', '\uC800\uC7A5 \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
    }
}

async function deleteTemplate() {
    const id = $('#tpl-edit-id').val();
    if (!id) return;
    if (!confirm('\uD15C\uD50C\uB9BF\uC744 \uC601\uAD6C \uC0AD\uC81C\uD558\uC2DC\uACA0\uC2B5\uB2C8\uAE4C?')) return;

    try {
        const res = await $.post(API.TEMPLATE_DELETE, { id });
        if (!res?.success) {
            notify('error', res?.message || '\uC0AD\uC81C\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
            return;
        }

        if (String(selectedTemplateId) === String(id)) {
            selectedTemplateId = '';
            selectedTemplateActive = false;
            selectedStepId = '';
            $('#ap-selected-template-name').text('');
            $('#approvalStepCount').text('');
            setStepAddButtonDisabled(true);
            syncStepReorderLock();
            reloadStepTable();
        }

        templateModal?.hide();
        reloadTemplateTable();
        notify('success', '\uC0AD\uC81C\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
    } catch (err) {
        console.error('[approval] delete template failed:', err);
        notify('error', '\uC0AD\uC81C \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
    }
}

function openStepModal(mode, step = null) {
    resetStepModal();

    const isCreate = mode === 'create';
    $('#modal-step-edit .modal-title').text(isCreate ? '\uB2E8\uACC4 \uB4F1\uB85D' : '\uB2E8\uACC4 \uC218\uC815');
    $('#btn-delete-step-edit').toggle(!isCreate);

    fillRoleSelect('#step-edit-role', step?.role_id || '');
    fillUserSelect('#step-edit-user', step?.approver_id || '');
    $('#step-edit-type').val(step?.step_type || 'APPROVAL');
    syncStepAssignmentUi();

    if (!isCreate && step) {
        $('#step-edit-id').val(step.id || '');
        $('#step-edit-name').val(normalize(step.step_name || ''));
        $('#step-edit-active').prop('checked', String(step.is_active) === '1');
        $('#step-info-id').text(normalize(step.id));
        $('#step-info-sort-no').text(normalize(step.sort_no));
        $('#step-info-template-id').text(normalize(step.template_id));
        $('#step-info-created-at').text(normalize(step.created_at));
        $('#step-info-created-by').text(actorDisplay(step, 'created_by'));
        $('#step-info-updated-at').text(normalize(step.updated_at));
        $('#step-info-updated-by').text(actorDisplay(step, 'updated_by'));
        $('#step-system-info-card').show();
    } else {
        $('#step-edit-active').prop('checked', true);
        $('#step-system-info-card').hide();
    }

    stepModal?.show();
}

function resetStepModal() {
    $('#step-edit-id').val('');
    $('#step-edit-name').val('');
    $('#step-edit-type').val('APPROVAL');
    syncStepAssignmentUi();
    resetSelect('#step-edit-role');
    resetSelect('#step-edit-user');
    $('#step-edit-active').prop('checked', true);
    $('#step-system-info-card').hide();
    $('#btn-delete-step-edit').hide();
}

async function saveStep() {
    if (!selectedTemplateId) {
        notify('warning', '\uBA3C\uC800 \uD15C\uD50C\uB9BF\uC744 \uC120\uD0DD\uD574 \uC8FC\uC138\uC694.');
        return;
    }

    if (!validateStepRequiredPolicies()) {
        return;
    }

    const payload = {
        id: $('#step-edit-id').val(),
        template_id: selectedTemplateId,
        step_name: normalize($('#step-edit-name').val()),
        step_type: normalize($('#step-edit-type').val()),
        role_id: normalize($('#step-edit-type').val()) === 'SUBMIT' ? '' : normalizeRoleId($('#step-edit-role').val()),
        approver_id: normalize($('#step-edit-type').val()) === 'SUBMIT' ? '' : normalizeApproverId($('#step-edit-user').val()),
        is_active: $('#step-edit-active').is(':checked') ? 1 : 0
    };

    try {
        const res = await $.post(API.STEP_SAVE, payload);
        if (!res?.success) {
            notify('error', res?.message || '\uC800\uC7A5\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
            return;
        }

        stepModal?.hide();
        reloadStepTable();
        notify('success', '\uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
    } catch (err) {
        console.error('[approval] save step failed:', err);
        notify('error', '\uC800\uC7A5 \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
    }
}

async function deleteStep() {
    const stepId = $('#step-edit-id').val();
    if (!stepId) return;
    if (!confirm('\uB2E8\uACC4\uB97C \uC601\uAD6C \uC0AD\uC81C\uD558\uC2DC\uACA0\uC2B5\uB2C8\uAE4C?')) return;

    try {
        const res = await $.post(API.STEP_DELETE, { step_id: stepId });
        if (!res?.success) {
            notify('error', res?.message || '\uC0AD\uC81C\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.');
            return;
        }

        selectedStepId = '';
        stepModal?.hide();
        reloadStepTable();
        notify('success', '\uC0AD\uC81C\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
    } catch (err) {
        console.error('[approval] delete step failed:', err);
        notify('error', '\uC0AD\uC81C \uC911 \uC624\uB958\uAC00 \uBC1C\uC0DD\uD588\uC2B5\uB2C8\uB2E4.');
    }
}

async function preloadSelectLists() {
    try {
        const [roleRes, userRes] = await Promise.all([
            $.get(API.ROLE_LIST),
            $.get(API.EMPLOYEE_LIST)
        ]);

        roleList = getRows(roleRes);
        userList = getRows(userRes);
    } catch (err) {
        console.error('[approval] preload select lists failed:', err);
    }
}

function resetSelect(selector) {
    const $el = $(selector);
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }
    const select = $el[0];
    const emptyOption = select?.querySelector?.('option[value=""]')?.cloneNode(true) || null;
    $el.empty();
    if (emptyOption) {
        select.append(emptyOption);
    }
}

function initSelect2(selector, dropdownParent, options = {}) {
    if (!$.fn.select2) return;

    const $el = $(selector);
    if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
    }

    $el.select2({
        width: '100%',
        dropdownParent: $(dropdownParent),
        allowClear: Boolean(options.allowClear)
    });
}

function fillRoleSelect(selector, selected = '') {
    const $el = $(selector);
    const select = $el[0];
    const selectedValue = selected ? String(selected) : ROLE_NONE_VALUE;

    resetSelect(selector);
    if (select && !select.querySelector('option[value=""]')) {
        const option = document.createElement('option');
        option.value = ROLE_NONE_VALUE;
        option.textContent = '선택(없음)';
        select.append(option);
    } else if (select) {
        select.querySelector('option[value=""]').textContent = '선택(없음)';
    }

    roleList.filter((role) => String(role.is_active) === '1' || selectedValue === String(role.id)).forEach((role) => {
        $el.append(new Option(role.role_name || role.role_key || role.id, role.id, false, selectedValue === String(role.id)));
    });

    if (select) select.value = selectedValue;
    initSelect2(selector, '#modal-step-edit');
}

function fillUserSelect(selector, selected = '') {
    const $el = $(selector);
    const select = $el[0];
    const selectedValue = selected ? String(selected) : APPROVER_NONE_VALUE;

    resetSelect(selector);
    if (select && !select.querySelector('option[value=""]')) {
        const option = document.createElement('option');
        option.value = APPROVER_NONE_VALUE;
        option.textContent = '선택(없음)';
        select.append(option);
    } else if (select) {
        select.querySelector('option[value=""]').textContent = '선택(없음)';
    }

    userList.forEach((user) => {
        const userId = user.user_id || user.id || '';
        const label = user.employee_name
            ? `${user.employee_name} (${user.username || ''})`
            : (user.username || userId);

        $el.append(new Option(label, userId, false, selectedValue === String(userId)));
    });

    if (select) select.value = selectedValue;
    initSelect2(selector, '#modal-step-edit');
}

function updateTemplateCount() {
    if (!templateTable?.page) return;
    const info = templateTable.page.info();
    $('#approvalTemplateCount').text(`\uCD1D ${info?.recordsDisplay ?? 0}\uAC74`);
}

function updateStepCount() {
    if (!stepTable?.page) return;
    const info = stepTable.page.info();
    $('#approvalStepCount').text(info.recordsDisplay ? `\uCD1D ${info.recordsDisplay}\uB2E8\uACC4` : '');
}

function reloadTemplateTable(preferredId = '') {
    templateTable?.ajax.reload(() => {
        if (preferredId) {
            selectedTemplateId = String(preferredId);
        }

        markSelectedTemplateRow();
        updateTemplateCount();
    }, false);
}

function reloadStepTable() {
    stepTable?.ajax.reload(() => {
        updateStepCount();
    }, false);
}

function markSelectedTemplateRow() {
    if (!templateTable || !selectedTemplateId) return;

    setTableSelectedRow('#template-list-table', null);
    let selectedRowExists = false;
    templateTable.rows().every(function () {
        const row = this.data();
        if (String(row?.id || '') === String(selectedTemplateId)) {
            selectedRowExists = true;
            selectedTemplateActive = String(row.is_active) === '1';
            setTableSelectedRow('#template-list-table', this.node());
            $('#ap-selected-template-name').text(`[${row.template_name}]`);
            setStepAddButtonDisabled(selectedTemplateActive);
            syncStepReorderLock();
        }
    });
    if (!selectedRowExists) {
        selectedTemplateId = '';
        selectedTemplateActive = false;
        selectedStepId = '';
        $('#ap-selected-template-name').text('');
        $('#approvalStepCount').text('');
        setStepAddButtonDisabled(true);
        syncStepReorderLock();
        reloadStepTable();
    }
}

function markSelectedStepRow() {
    if (!stepTable || !selectedStepId) return;

    setTableSelectedRow('#template-steps-table', null);
    stepTable.rows().every(function () {
        const row = this.data();
        if (String(row?.id || '') === String(selectedStepId)) {
            setTableSelectedRow('#template-steps-table', this.node());
        }
    });
}

function bindStepRowReorder() {
    bindRowReorder(stepTable, {
        api: API.STEP_REORDER,
        includeAppliedRows: true,
        changedRowsOnly: false,
        extraData: () => ({ template_id: selectedTemplateId }),
        canReorder: () => approvalReorderPermission(stepTable, true),
        onSuccess() {
            notify('success', '\uB2E8\uACC4 \uC21C\uBC88\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.');
            reloadStepTable();
        },
        onError(res) {
            const message = res?.message || '\uB2E8\uACC4 \uC21C\uC11C \uC800\uC7A5\uC5D0 \uC2E4\uD328\uD588\uC2B5\uB2C8\uB2E4.';
            if (message === '해당 템플릿의 결재흐름을 변경하려면 해당 템플릿을 먼저 비활성화해 주세요.') {
                const now = Date.now();
                if (now - lastStepReorderGuardAt >= 2000) {
                    lastStepReorderGuardAt = now;
                    notify('warning', message);
                }
                return;
            }
            notify('error', message);
            reloadStepTable();
        },
    });
    syncStepReorderLock();
}

function approvalReorderPermission(table, requireTemplate = false) {
    if (requireTemplate && !selectedTemplateId) {
        return { allowed: false, message: '결재템플릿을 먼저 선택해 주세요.' };
    }
    if (requireTemplate && selectedTemplateActive) {
        return { allowed: false, message: '해당 템플릿의 결재흐름을 변경하려면 해당 템플릿을 먼저 비활성화해 주세요.' };
    }

    const searchValue = String(table?.search?.() || '').trim();
    const pageInfo = table?.page?.info?.();
    const order = table?.order?.() || [];
    const firstOrder = Array.isArray(order[0]) ? order[0] : [];
    const orderColumn = Number(firstOrder[0]);
    const orderDirection = String(firstOrder[1] || '').toLowerCase();
    const columnData = table?.settings?.()?.[0]?.aoColumns?.[orderColumn]?.mData;
    const showsAllRows = !pageInfo || Number(pageInfo.pages || 1) <= 1;

    if (searchValue !== '' || !showsAllRows || columnData !== 'sort_no' || orderDirection !== 'asc') {
        return {
            allowed: false,
            message: '검색을 해제하고 순번 오름차순의 전체 목록에서만 순서를 변경할 수 있습니다.',
        };
    }

    return { allowed: true, message: '' };
}

function bindLayoutEvents() {
    window.addEventListener('resize', () => {
        if (templateTable?.__dtTableSettings?.refreshLayout) {
            templateTable.__dtTableSettings.refreshLayout({ draw: false });
        } else {
            templateTable?.columns.adjust();
        }

        if (stepTable?.__dtTableSettings?.refreshLayout) {
            stepTable.__dtTableSettings.refreshLayout({ draw: false });
        } else {
            stepTable?.columns.adjust();
        }
    });
}

$(function () {
    init();
});
