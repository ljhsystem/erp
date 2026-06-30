// Path: PROJECT_ROOT . '/public/assets/js/pages/ledger/account.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, setTableSelectedRow } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { createExcelManagerSettingsCore } from '/public/assets/js/components/excel-manager/index.js';
import { formatNumber } from '/public/assets/js/common/format.js';
import { initCodeSelectControls, getCodeName } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    const API = {
        LIST: '/api/ledger/account/list',
        DETAIL: '/api/ledger/account/detail',
        SAVE: '/api/ledger/account/save',
        DELETE: '/api/ledger/account/delete',
        STATUS: '/api/ledger/account/status',
        REORDER: '/api/ledger/account/reorder',
        TRASH: '/api/ledger/account/trash',
        RESTORE: '/api/ledger/account/restore',
        PURGE: '/api/ledger/account/purge',
        PURGE_ALL: '/api/ledger/account/purge-all',
        EXCEL_TEMPLATE: '/api/ledger/account/template',
        EXCEL_DOWNLOAD: '/api/ledger/account/excel',
        EXCEL_UPLOAD: '/api/ledger/account/excel-upload',
        SUB_LIST: '/api/ledger/sub-account/list'
    };

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(options.headers || {})
            },
            ...options
        });

        const text = await response.text();
        let json = null;

        try {
            json = text ? JSON.parse(text) : null;
        } catch (error) {
            throw new Error(`JSON 응답을 해석할 수 없습니다. ${text.slice(0, 200)}`);
        }

        if (!response.ok) {
            throw new Error(json?.message || `요청에 실패했습니다. (${response.status})`);
        }

        return json;
    }

    const ACCOUNT_COLUMN_MAP = {
        sort_no: { label: '순번', visible: true, className: 'text-center' },
        account_code: { label: '계정코드', visible: true },
        account_name: { label: '계정과목명', visible: true },
        parent_name: { label: '상위계정', visible: true },
        parent_id: { label: '상위계정ID', visible: false },
        account_group: { label: '계정구분', visible: true, className: 'text-center' },
        normal_balance: { label: '정상잔액', visible: true, className: 'text-center' },
        level: { label: '레벨', visible: true, className: 'text-center' },
        is_posting: { label: '전표입력', visible: true, className: 'text-center' },
        allow_sub_account: { label: '보조계정', visible: true, className: 'text-center' },
        is_active: { label: '상태', visible: true, className: 'text-center' },
        note: { label: '비고', visible: false },
        memo: { label: '메모', visible: false },
        created_at: { label: '생성일시', visible: false },
        created_by: { label: '생성자ID', visible: false },
        created_by_name: { label: '생성자', visible: false },
        updated_at: { label: '수정일시', visible: false },
        updated_by: { label: '수정자ID', visible: false },
        updated_by_name: { label: '수정자', visible: false },
        deleted_at: { label: '삭제일시', visible: false },
        deleted_by: { label: '삭제자ID', visible: false },
        deleted_by_name: { label: '삭제자', visible: false },
        has_sub_account: { label: '보조계정등록', visible: false, className: 'text-center' },
        id: { label: 'ID', visible: false }
    };
    Object.assign(ACCOUNT_COLUMN_MAP, {
        account_category: { label: '계정카테고리', visible: false },
        account_level: { label: '계정레벨', visible: false, className: 'text-center' },
        is_postable: { label: '전표가능', visible: false, className: 'text-center' },
        status: { label: '상태코드', visible: false },
        full_path: { label: '전체경로', visible: false },
        path_ids: { label: '경로 ID', visible: false },
        tree_sort: { label: '트리정렬값', visible: false },
    });
    const ACCOUNT_COLUMN_ORDER = [
        'id',
        'sort_no',
        'account_code',
        'account_name',
        'parent_id',
        'account_group',
        'account_category',
        'normal_balance',
        'level',
        'account_level',
        'is_posting',
        'is_postable',
        'allow_sub_account',
        'status',
        'full_path',
        'path_ids',
        'tree_sort',
        'note',
        'memo',
        'is_active',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'parent_name',
        'has_sub_account',
        'created_by_name',
        'updated_by_name',
        'deleted_by_name',
    ];
    const DATE_OPTIONS = [
        { value: 'created_at', label: '생성일자' },
        { value: 'updated_at', label: '수정일자' }
    ];
    const ACCOUNT_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.ledger.account.account-table.v1';
    const NEW_PARENT_ACCOUNT_VALUE = '__new_parent_account__';
    const SUB_ACCOUNT_CODE_GROUP = 'REF_TARGET';
    const SUB_ACCOUNT_SELECT_NAME = 'ledger_sub_ref_target';
    const ACCOUNT_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#modal_account_code', key: 'account_code' },
        { selector: '#modal_account_name', key: 'account_name' },
        { selector: '#modal_parent_id', key: 'parent_id' },
        { selector: '#modal_account_group', key: 'account_group' },
        { selector: '#modal_normal_balance_debit', key: 'normal_balance' },
        { selector: '#modal_allow_sub_account_toggle', key: 'allow_sub_account' },
        { selector: '#modal_is_posting_toggle', key: 'is_posting' },
        { selector: '#modal_is_active_toggle', key: 'is_active' },
        { selector: '#modal_note', key: 'note' },
        { selector: '#modal_memo', key: 'memo' },
    ]);

    let accountTable = null;
    let accountModal = null;
    let excelModal = null;
    let parentAccounts = [];
    let modalDraftSubAccounts = [];
    let accountFormInitialSnapshot = '';
    let skipAccountCloseConfirm = false;
    let accountModalInitializing = false;
    let accountModalControlsPromise = null;
    let parentAccountsRefreshPromise = null;
    let parentAccountsLoadedAt = 0;
    let accountModalPostOpenTimer = 0;
    let subAccountLoadTimer = 0;
    let subAccountLoadSeq = 0;
    let accountPolicyBound = false;

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.account = function (row = {}) {
        return `
            <td>${escapeHtml(formatAccountCodeDisplay(row.account_code ?? ''))}</td>
            <td>${escapeHtml(row.account_name ?? '')}</td>
            <td>${escapeHtml(row.account_group ?? '')}</td>
            <td>${escapeHtml(row.deleted_at ?? '')}</td>
            <td>${escapeHtml(row.deleted_by_name ?? row.deleted_by ?? '')}</td>
            <td class="text-center">
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id ?? '')}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id ?? '')}">영구삭제</button>
            </td>
        `;
    };
    document.addEventListener('DOMContentLoaded', () => {
        initModals();
        initExcelDataset();
        initAccountCodeFormat();
        initDataTable();
        bindEvents();
    });

    document.addEventListener('excel:uploaded', () => {
        accountTable?.ajax.reload(null, false);
    });

    document.addEventListener('trash:changed', (event) => {
        if (event.detail?.type === 'account') {
            accountTable?.ajax.reload(null, false);
        }
    });

    function currentAccountPolicyState() {
        return readDataTableSettingsState(ACCOUNT_TABLE_SETTINGS_STORAGE_KEY) || {};
    }

    function accountFieldLabel(key, fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentAccountPolicyState(),
            normalizedKey || fallback
        );
    }

    function accountFieldRequirement(key) {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentAccountPolicyState()
        );
    }

    function accountFieldStarMarkup(key) {
        const policy = accountFieldRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function findAccountModalLabel(selector, root = document) {
        const field = root.querySelector(selector);
        if (!field) return null;

        if (field.id) {
            const byFor = root.querySelector(`label[for="${field.id}"]`);
            if (byFor) return byFor;
        }

        const group = field.closest('.mb-3, .form-check, .col-md-3, .col-md-4, .col-md-5, .col-md-6, .col-md-auto, .col-12');
        if (group) {
            const label = group.querySelector('label.form-label, label.form-check-label');
            if (label) return label;
        }

        return field.closest('label.form-label, label.form-check-label') || null;
    }

    function applyAccountModalPolicyLabels(root = document) {
        ACCOUNT_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findAccountModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = accountFieldLabel(field.key, field.key);
            const starMarkup = accountFieldStarMarkup(field.key);
            labelEl.innerHTML = `${displayName}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function bindAccountPolicySync() {
        if (accountPolicyBound) return;
        accountPolicyBound = true;

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== ACCOUNT_TABLE_SETTINGS_STORAGE_KEY) {
                return;
            }

            applyAccountModalPolicyLabels(document);
        });
    }

    function focusAccountPolicyField(key, selector) {
        if (key === 'parent_id' && isParentAccountInputMode()) {
            document.getElementById('modal_new_parent_code')?.focus();
            return;
        }

        const field = document.querySelector(selector);
        if (!field) return;
        if (typeof field.focus === 'function') {
            field.focus();
        }
    }

    function collectAccountModalValues() {
        return {
            account_code: normalizeAccountCodeValue($('#modal_account_code').val()),
            account_name: String($('#modal_account_name').val() || '').trim(),
            parent_id: isParentAccountInputMode()
                ? normalizeAccountCodeValue($('#modal_new_parent_code').val())
                : String($('#modal_parent_id').val() || '').trim(),
            account_group: String($('#modal_account_group').val() || '').trim(),
            normal_balance: String($('input[name="normal_balance"]:checked').val() || '').trim(),
            allow_sub_account: String($('#modal_allow_sub_account').val() || '').trim(),
            is_posting: String($('#modal_is_posting').val() || '').trim(),
            is_active: String($('#modal_is_active').val() || '').trim(),
            note: String($('#modal_note').val() || '').trim(),
            memo: String($('#modal_memo').val() || '').trim(),
        };
    }

    function validateAccountRequiredPolicies() {
        const values = collectAccountModalValues();

        for (const field of ACCOUNT_MODAL_FIELD_POLICIES) {
            if (accountFieldRequirement(field.key) !== 'required') {
                continue;
            }

            if (String(values[field.key] ?? '').trim() !== '') {
                continue;
            }

            notify('warning', `${accountFieldLabel(field.key, field.key)} 항목을 입력해 주세요.`);
            focusAccountPolicyField(field.key, field.selector);
            return false;
        }

        return true;
    }

    document.addEventListener('trash:detail-render', async (event) => {
        const detail = event.detail || {};
        if (detail.type !== 'account') return;

        const detailEl = detail.modal?.querySelector('#account-trash-detail');
        const row = detail.data || {};
        if (!detailEl) return;

        detailEl.innerHTML = '<div class="text-muted py-3">상세 정보를 불러오는 중입니다.</div>';

        try {
            const query = row.account_code
                ? `code=${encodeURIComponent(row.account_code)}`
                : (row.id ? `id=${encodeURIComponent(row.id)}` : '');
            const json = query ? await fetchJson(`${API.DETAIL}?${query}`) : null;
            renderTrashDetail(detailEl, json?.success ? (json.data || row) : row);
        } catch (err) {
            console.error('[ledger-account] trash detail failed:', err);
            renderTrashDetail(detailEl, row);
        }
    });

    function initModals() {
        const modalEl = document.getElementById('accountModal');
        if (modalEl) {
            ensureModalSubAccountSection(modalEl);
            accountModal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false, keyboard: false });
            document.addEventListener('keydown', handleAccountModalEscapeCapture, true);
            modalEl.addEventListener('shown.bs.modal', () => {
                applyAccountModalPolicyLabels(modalEl);
                initParentAccountSelect2();
                setParentSelect2Visible(!isParentAccountInputMode());
                window.setTimeout(() => {
                    if ($('#modal_allow_sub_account').val() === '1' && $('#modal_account_id').val()) {
                        return;
                    }
                    markAccountFormClean();
                    accountModalInitializing = false;
                }, 0);
            });
            modalEl.addEventListener('hidden.bs.modal', () => {
                if (accountModalPostOpenTimer) {
                    window.clearTimeout(accountModalPostOpenTimer);
                    accountModalPostOpenTimer = 0;
                }
                if (subAccountLoadTimer) {
                    window.clearTimeout(subAccountLoadTimer);
                    subAccountLoadTimer = 0;
                }
                subAccountLoadSeq += 1;
            });
            modalEl.addEventListener('hide.bs.modal', (event) => {
                if (!confirmAccountModalClose()) {
                    event.preventDefault();
                }
            });
            modalEl.addEventListener('hidden.bs.modal', resetAccountForm);
        }

        const excelEl = document.getElementById('accountExcelModal');
        if (excelEl) {
            excelModal = bootstrap.Modal.getOrCreateInstance(excelEl, { focus: false });
        }

        bindAccountPolicySync();
        applyAccountModalPolicyLabels(document);
    }

    function initExcelDataset() {
        const form = document.getElementById('account-excel-upload-form');
        if (!form) return;

        form.dataset.templateUrl = API.EXCEL_TEMPLATE;
        form.dataset.downloadUrl = API.EXCEL_DOWNLOAD;
        form.dataset.uploadUrl = API.EXCEL_UPLOAD;

        createExcelManagerSettingsCore({
            domain: 'ledger-account',
            formSelector: '#account-excel-upload-form',
            tableSettingsStorageKey: ACCOUNT_TABLE_SETTINGS_STORAGE_KEY,
            tableSettingsMetaDomain: 'ledger-account',
        });
    }

    function initAccountCodeFormat() {
        [
            document.getElementById('modal_account_code'),
            document.getElementById('modal_new_parent_code')
        ].forEach((input) => {
            if (!input || input.dataset.accountCodeFormatBound === 'true') return;

            input.addEventListener('input', () => {
                input.value = formatAccountCodeInput(input.value);
            });

            input.addEventListener('blur', () => {
                input.value = formatAccountCodeDisplay(input.value);
            });

            input.dataset.accountCodeFormatBound = 'true';
        });
    }

    function ensureModalSubAccountSection(modalEl) {
        if (document.getElementById('modal_subaccount_section')) return;

        const allowSelect = modalEl.querySelector('#modal_allow_sub_account');
        const accountGroupCard = allowSelect?.closest('.card');
        if (!accountGroupCard) return;

        const section = document.createElement('div');
        section.className = 'card mb-3 d-none modal-subaccount-card';
        section.id = 'modal_subaccount_section';
        section.innerHTML = `
            <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center">
                <span>보조계정</span>
            </div>
            <div class="card-body py-2">
                <div class="table-responsive modal-subaccount-table-wrap">
                    <table class="table table-sm table-bordered align-middle mb-0" id="modal-subaccount-table">
                        <thead class="table-light">
                            <tr>
                                <th width="56" class="text-center">순번</th>
                                <th width="190">보조계정명</th>
                                <th width="150" class="text-center">옵션</th>
                                <th width="90" class="text-center">
                                    <button type="button" class="sub-add-action" id="btnAddSubAccountModal">+ 추가</button>
                                </th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        `;

        accountGroupCard.insertAdjacentElement('afterend', section);
    }
    function initDataTable() {
        accountTable = createDataTable({
            tableSelector: '#account-table',
            api: API.LIST,
            deleteApi: API.DELETE,
            columns: buildColumns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            autoWidth: false,
            searchTableId: 'ledgerAccount',
            pageLoading: false,
            selectionColumn: {
                widthResizable: true,
            },
            tableSettings: {
                pageKey: 'ledger.account',
                tableKey: 'account-table',
                storageKey: ACCOUNT_TABLE_SETTINGS_STORAGE_KEY,
                metaDomain: 'ledger-account',
                tableLabel: '계정과목',
                title: '계정과목 테이블 설정',
                defaultVisibleColumns: ['sort_no', 'account_code', 'account_name', 'parent_id', 'account_group', 'normal_balance', 'level', 'is_posting', 'allow_sub_account', 'is_active'],
            },
            buttons: [
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm dt-trash-btn account-trash-btn',
                    attr: { 'data-trash-modal': '#accountTrashModal' },
                    action: openTrashModal
                },
                { text: '엑셀관리', className: 'btn btn-success btn-sm', action: () => excelModal?.show() },
                { text: '새 계정과목', className: 'btn btn-warning btn-sm', action: () => { void openCreateModal().catch((error) => notify('error', error.message)); } }
            ]
        });

        window.accountTable = accountTable;
        if (!accountTable) return;

        SearchForm({
            table: accountTable,
            apiList: API.LIST,
            tableId: 'ledgerAccount',
            defaultSearchField: 'account_name',
            dateOptions: DATE_OPTIONS,
            excludeFields: ['id', 'parent_id', 'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at', 'deleted_by', 'deleted_by_name', 'has_sub_account']
        });

        bindRowReorder(accountTable, {
            api: API.REORDER,
            onSuccess() {
                notify('success', '계정과목 순번이 변경되었습니다.');
                accountTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '계정과목 순번 변경에 실패했습니다.');
                accountTable?.ajax.reload(null, false);
            }
        });

        accountTable.on('xhr.dt', (event, settings, json) => {
            mergeParentAccounts(Array.isArray(json?.data) ? json.data : []);
            parentAccountsLoadedAt = Date.now();
        });

        accountTable.on('init.dt draw.dt xhr.dt', () => {
            updateCount(accountTable.page.info()?.recordsDisplay ?? 0);
            applyAccountTreeRowStyles();
        });
    }
    function buildColumns() {
        const columns = [{
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'reorder-handle no-sort no-colvis text-center',
            headerClassName: 'no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>',
            settingsKey: '__reorder',
            width: '44px',
            widthResizable: true
        }];

        ACCOUNT_COLUMN_ORDER.forEach((field) => {
            const config = ACCOUNT_COLUMN_MAP[field];
            if (!config) {
                return;
            }

            if (field === 'parent_id' || field === 'created_by_name' || field === 'updated_by_name' || field === 'deleted_by_name') {
                return;
            }

            const dataField = field === 'parent_name' ? 'parent_id' : field;
            const className = [
                config.className || '',
                field === 'sort_no' ? 'dt-sequence-column' : '',
            ].filter(Boolean).join(' ');

            columns.push({
                data: dataField,
                title: config.label,
                visible: config.visible,
                className,
                headerClassName: config.headerClassName || '',
                defaultContent: '',
                settingsKey: dataField,
                render(value, type, row) {
                    if (type === 'sort' || type === 'type') return row?.tree_sort || value || '';
                    if (type !== 'display') return value ?? '';
                    if (field === 'normal_balance') return value === 'credit' ? '대변' : '차변';
                    if (field === 'account_code') return escapeHtml(formatAccountCodeDisplay(value ?? ''));
                    if (field === 'account_name') return renderAccountTreeCell(row, value);
                    if (field === 'parent_name') return escapeHtml(row?.parent_name ?? value ?? '');
                    if (field === 'is_posting') return renderPostableBadge(row);
                    if (field === 'created_by') return escapeHtml(row?.created_by_name ?? value ?? '');
                    if (field === 'updated_by') return escapeHtml(row?.updated_by_name ?? value ?? '');
                    if (field === 'deleted_by') return escapeHtml(row?.deleted_by_name ?? value ?? '');

                    if (field === 'allow_sub_account') {
                        return Number(value) === 1
                            ? '<span class="badge bg-primary">사용</span>'
                            : '<span class="badge bg-secondary">미사용</span>';
                    }

                    if (field === 'is_active') {
                        const active = Number(value) === 1;
                        return `
                            <div class="form-check form-switch account-table-status-switch">
                                <input type="checkbox"
                                       class="form-check-input account-status-toggle"
                                       role="switch"
                                       data-id="${escapeHtml(row?.id || '')}"
                                       ${active ? 'checked' : ''}>
                            </div>
                        `;
                    }

                    return escapeHtml(value ?? '');
                }
            });
        });

        columns.push({
            data: null,
            title: '관리',
            className: 'text-center no-colvis',
            headerClassName: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            defaultContent: '',
            render(_value, type, row) {
                if (type !== 'display') return '';
                return `
                    <button type="button"
                            class="btn btn-outline-primary btn-sm account-edit-btn"
                            data-id="${escapeHtml(row?.id || '')}">
                        수정
                    </button>
                `;
            }
        });

        return columns;
    }
    function normalizeAccountCodeValue(value) {
        return String(value ?? '').replace(/,/g, '').trim();
    }

    function isNumericAccountCode(value) {
        return /^\d+$/.test(normalizeAccountCodeValue(value));
    }

    function formatAccountCodeDisplay(value) {
        const normalized = normalizeAccountCodeValue(value);
        if (!normalized || !isNumericAccountCode(normalized)) {
            return normalized;
        }

        return formatNumber(normalized);
    }

    function formatAccountCodeInput(value) {
        const raw = String(value ?? '').trim();
        const withoutCommas = normalizeAccountCodeValue(raw);

        if (withoutCommas === '') {
            return '';
        }

        if (!/^\d+$/.test(withoutCommas)) {
            return withoutCommas.toUpperCase();
        }

        return formatAccountCodeDisplay(withoutCommas.slice(0, 6));
    }

    function getAccountLevel(row = {}) {
        const level = Number(row.account_level ?? row.level ?? 1);
        return Number.isFinite(level) && level > 0 ? level : 1;
    }

    function isPostableAccount(row = {}) {
        const postable = String(row.is_postable ?? '').toUpperCase();
        if (postable === 'Y') return true;
        if (postable === 'N') return false;
        return Number(row.is_posting ?? 0) === 1;
    }

    function getTreeClass(level) {
        if (level <= 1) return 'tree-level-1';
        if (level === 2) return 'tree-level-2';
        if (level === 3) return 'tree-level-3';
        return 'tree-level-leaf';
    }

    function renderPostableBadge(row = {}) {
        if (isPostableAccount(row)) {
            return '<span class="account-postable-badge is-postable"><i class="bi bi-pencil-square"></i> 전표</span>';
        }
        return '<span class="account-postable-badge is-group"><i class="bi bi-folder2"></i> 그룹</span>';
    }
    function renderAccountTreeCell(row = {}, value = '') {
        const level = getAccountLevel(row);
        const depth = Math.max(level - 1, 0);
        const padding = Math.min(depth * 20, 240);
        const isPostable = isPostableAccount(row);
        const branch = level > 1 ? '<span class="account-tree-branch">&#9492;</span>' : '';
        const icon = isPostable
            ? '<i class="bi bi-file-earmark-text account-tree-icon is-postable"></i>'
            : '<i class="bi bi-folder2-open account-tree-icon is-group"></i>';
        const badge = isPostable
            ? '<span class="account-tree-chip is-postable">전표입력</span>'
            : '<span class="account-tree-chip is-group">그룹계정</span>';

        return `
            <div class="account-tree-cell ${getTreeClass(level)}" style="padding-left:${padding}px" title="${escapeHtml(row.full_path || value || '')}">
                ${branch}
                ${icon}
                <span class="account-tree-title">${escapeHtml(value ?? '')}</span>
                ${badge}
            </div>
        `;
    }
    function applyAccountTreeRowStyles() {
        if (!accountTable) return;

        accountTable.rows({ page: 'current' }).every(function () {
            const rowData = this.data() || {};
            const node = this.node();
            if (!node) return;

            const level = getAccountLevel(rowData);
            const isPostable = isPostableAccount(rowData);
            node.classList.remove(
                'account-row-level-1',
                'account-row-level-2',
                'account-row-level-3',
                'account-row-leaf',
                'account-row-group',
                'account-row-postable'
            );
            node.classList.add(`account-row-level-${Math.min(level, 3)}`);
            node.classList.add(isPostable ? 'account-row-postable' : 'account-row-group');
            if (level >= 4) {
                node.classList.add('account-row-leaf');
            }
        });
    }

    function bindEvents() {
        $('#account-table tbody')
            .off('click.accountSelect')
            .on('click.accountSelect', 'tr', function () {
                const row = accountTable?.row(this).data();
                if (!row) return;

                selectAccountRow(this);
            })
            .off('dblclick.accountEdit')
            .on('dblclick.accountEdit', 'tr', function () {
                const row = accountTable?.row(this).data();
                if (row) void openEditModal(row).catch((error) => notify('error', error.message));
            });

        $('#account-table tbody')
            .off('click.accountStatusToggle change.accountStatusToggle')
            .on('click.accountStatusToggle', '.account-status-toggle', function (event) {
                event.stopPropagation();
            })
            .on('change.accountStatusToggle', '.account-status-toggle', updateAccountStatusInline);

        $('#account-table tbody')
            .off('click.accountEditBtn')
            .on('click.accountEditBtn', '.account-edit-btn', function (event) {
                event.stopPropagation();
                const row = accountTable?.row($(this).closest('tr')).data();
                if (row) void openEditModal(row).catch((error) => notify('error', error.message));
            });

        $('#account-edit-form')
            .off('submit.accountSave')
            .on('submit.accountSave', saveAccount);

        $('#btnDeleteAccount')
            .off('click.accountDelete')
            .on('click.accountDelete', deleteAccount);

        $('#modal_parent_id, #modal_new_parent_code, #modal_new_parent_name')
            .off('input.accountParent change.accountParent')
            .on('input.accountParent change.accountParent', function () {
                syncParentAccountInputs(this);
            });

        $('#modal_is_active_toggle')
            .off('change.accountStatus')
            .on('change.accountStatus', function () {
                setAccountStatusToggle(this.checked ? 1 : 0);
            });

        $('#modal_is_posting_toggle')
            .off('change.accountPosting')
            .on('change.accountPosting', function () {
                setPostingToggle(this.checked ? 1 : 0);
            });

        $('#modal_allow_sub_account_toggle')
            .off('change.subAccountStatus')
            .on('change.subAccountStatus', function () {
                setSubAccountToggle(this.checked ? 1 : 0);
                updateModalSubAccountSection();
            });

        $('#btnBackParentAccountSelect')
            .off('click.accountParentBack')
            .on('click.accountParentBack', () => {
                showParentAccountSelect('');
            });

        $('#btnAddSubAccountModal')
            .off('click.subAddModal')
            .on('click.subAddModal', addSubAccount);

        $('#modal_allow_sub_account')
            .off('change.subAccountManage')
            .on('change.subAccountManage', updateModalSubAccountSection);

        $('#modal-subaccount-table tbody')
            .off('click.subDeleteModal')
            .on('click.subDeleteModal', '.btnDeleteSubAccount', function () {
                if (this.dataset.draftIndex !== undefined) {
                    syncModalDraftSubAccounts();
                    modalDraftSubAccounts.splice(Number(this.dataset.draftIndex), 1);
                    renderModalDraftSubAccounts();
                    return;
                }

                renderModalDraftSubAccounts();
            });
    }

    function handleAccountModalEscape(event) {
        const modalEl = document.getElementById('accountModal');
        if (!modalEl?.classList.contains('show')) return false;

        if (window.jQuery && window.jQuery('.select2-container--open').length > 0) {
            window.jQuery('select.select2-hidden-accessible').select2('close');
            event.preventDefault();
            event.stopImmediatePropagation();
            return true;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        accountModal?.hide();
        return true;
    }

    function handleAccountModalEscapeCapture(event) {
        if (event.key !== 'Escape') return;
        handleAccountModalEscape(event);
    }

    function getAccountFormSnapshot() {
        if (document.getElementById('accountModal')?.classList.contains('show')) {
            syncModalDraftSubAccounts();
        }

        const allowSubAccount = $('#modal_allow_sub_account').val() === '1';
        const payload = {
            id: String($('#modal_account_id').val() || '').trim(),
            account_code: normalizeAccountCodeValue($('#modal_account_code').val()),
            account_name: String($('#modal_account_name').val() || '').trim(),
            parent_id: isParentAccountInputMode() ? '' : String($('#modal_parent_id').val() || '').trim(),
            new_parent_code: isParentAccountInputMode() ? normalizeAccountCodeValue($('#modal_new_parent_code').val()) : '',
            new_parent_name: isParentAccountInputMode() ? String($('#modal_new_parent_name').val() || '').trim() : '',
            account_group: String($('#modal_account_group').val() || '').trim(),
            normal_balance: String($('input[name="normal_balance"]:checked').val() || 'debit').trim(),
            allow_sub_account: allowSubAccount ? '1' : '0',
            is_posting: String($('#modal_is_posting').val() || '0').trim(),
            is_active: String($('#modal_is_active').val() || '0').trim(),
            note: String($('#modal_note').val() || '').trim(),
            memo: String($('#modal_memo').val() || '').trim(),
            sub_accounts: allowSubAccount
                ? modalDraftSubAccounts
                    .map((row) => ({
                        id: String(row.id || ''),
                        sub_code: String(row.sub_code || '').trim(),
                        is_required: String(row.is_required ?? '')
                    }))
                    .filter((row) => row.sub_code !== '' || row.is_required !== '')
                : []
        };

        return JSON.stringify(payload);
    }

    function markAccountFormClean() {
        accountFormInitialSnapshot = getAccountFormSnapshot();
    }

    function isAccountFormDirty() {
        return getAccountFormSnapshot() !== accountFormInitialSnapshot;
    }

    function confirmAccountModalClose() {
        if (skipAccountCloseConfirm) {
            skipAccountCloseConfirm = false;
            return true;
        }
        if (accountModalInitializing) {
            markAccountFormClean();
            accountModalInitializing = false;
            return true;
        }
        if (!isAccountFormDirty()) return true;
        return confirm('변경된 내용이나 입력 중인 내용이 있습니다. 닫으시겠습니까?');
    }
    function setAccountStatusToggle(value) {
        const active = Number(value) === 1;
        $('#modal_is_active').val(active ? '1' : '0');
        $('#modal_is_active_toggle').prop('checked', active);
        $('#modal_is_active_label').text(active ? '사용' : '미사용');
    }

    function setPostingToggle(value) {
        const enabled = Number(value) === 1;
        $('#modal_is_posting').val(enabled ? '1' : '0');
        $('#modal_is_posting_toggle').prop('checked', enabled);
        $('#modal_is_posting_label').text(enabled ? '가능' : '불가');
    }

    function setSubAccountToggle(value) {
        const enabled = Number(value) === 1;
        $('#modal_allow_sub_account').val(enabled ? '1' : '0');
        $('#modal_allow_sub_account_toggle').prop('checked', enabled);
        $('#modal_allow_sub_account_label').text(enabled ? '사용' : '미사용');
    }

    function updateModalSubAccountSection(options = {}) {
        const enabled = $('#modal_allow_sub_account').val() === '1';
        const section = document.getElementById('modal_subaccount_section');
        if (section) {
            section.classList.toggle('d-none', !enabled);
        }

        if (!enabled) return;

        const accountId = $('#modal_account_id').val();
        if (accountId) {
            if (!options.deferLoad) {
                scheduleSubAccountsLoad(accountId);
            }
            return;
        }

        renderModalDraftSubAccounts();
    }

    function openTrashModal() {
        const modalEl = document.getElementById('accountTrashModal');
        if (!modalEl) return;

        modalEl.dataset.listUrl = API.TRASH;
        modalEl.dataset.restoreUrl = API.RESTORE;
        modalEl.dataset.deleteUrl = API.PURGE;
        modalEl.dataset.deleteAllUrl = API.PURGE_ALL;

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function selectAccountRow(tr) {
        setTableSelectedRow('#account-table', tr);
    }

    async function updateAccountStatusInline(event) {
        const input = event.currentTarget;
        const id = input.dataset.id || '';
        const nextValue = input.checked ? 1 : 0;
        const previousValue = nextValue === 1 ? 0 : 1;
        const label = input.closest('.account-table-status-switch')?.querySelector('.account-status-toggle-label');

        if (!id) {
            input.checked = previousValue === 1;
            notify('error', '계정 ID를 찾을 수 없습니다.');
            return;
        }

        input.disabled = true;
        if (label) label.textContent = nextValue === 1 ? '사용' : '미사용';

        try {
            const res = await $.post(API.STATUS, { id, is_active: nextValue });
            if (!res?.success) {
                input.checked = previousValue === 1;
                if (label) label.textContent = previousValue === 1 ? '사용' : '미사용';
                notify('error', res?.message || '상태 변경에 실패했습니다.');
                return;
            }

            const tr = input.closest('tr');
            const row = accountTable?.row(tr);
            const rowData = row?.data();
            if (rowData) {
                rowData.is_active = nextValue;
                row.data(rowData).invalidate();
            }

            const updatedIds = Array.isArray(res.updated_ids) ? res.updated_ids.map(String) : [];
            if (updatedIds.length > 1) {
                accountTable?.rows().every(function () {
                    const data = this.data();
                    if (data && updatedIds.includes(String(data.id || ''))) {
                        data.is_active = nextValue;
                        this.data(data).invalidate();
                    }
                });
                accountTable?.draw(false);
            }

            notify('success', '상태가 변경되었습니다.');
        } catch (err) {
            console.error('[ledger-account] status update failed:', err);
            input.checked = previousValue === 1;
            if (label) label.textContent = previousValue === 1 ? '사용' : '미사용';
            notify('error', '상태 변경 중 오류가 발생했습니다.');
        } finally {
            input.disabled = false;
        }
    }

    function ensureAccountModalControls() {
        if (accountModalControlsPromise) return accountModalControlsPromise;
        const modalEl = document.getElementById('accountModal') || document;
        accountModalControlsPromise = initCodeSelectControls(modalEl)
            .catch((error) => {
                accountModalControlsPromise = null;
                throw error;
            });
        return accountModalControlsPromise;
    }

    function warmAccountModalControls() {
        void ensureAccountModalControls().catch((error) => {
            console.error('[ledger-account] modal controls failed:', error);
        });
    }

    function refreshParentAccountsInBackground({ force = false } = {}) {
        const now = Date.now();
        if (!force && parentAccounts.length > 0 && now - parentAccountsLoadedAt < 60000) {
            renderParentAccountOptions();
            return parentAccountsRefreshPromise || Promise.resolve();
        }

        if (parentAccountsRefreshPromise) return parentAccountsRefreshPromise;

        parentAccountsRefreshPromise = loadParentAccounts()
            .finally(() => {
                parentAccountsRefreshPromise = null;
            });

        return parentAccountsRefreshPromise;
    }

    function scheduleAccountModalPostOpenWork() {
        if (accountModalPostOpenTimer) {
            window.clearTimeout(accountModalPostOpenTimer);
        }

        accountModalPostOpenTimer = window.setTimeout(() => {
            accountModalPostOpenTimer = 0;
            warmAccountModalControls();
            void refreshParentAccountsInBackground();
        }, 120);
    }

    function setAccountModalLoading(isLoading = false, disableControls = false) {
        const loading = Boolean(isLoading);
        const modalEl = document.getElementById('accountModal');
        modalEl?.classList.toggle('is-loading-detail', loading);
        modalEl?.setAttribute('aria-busy', loading ? 'true' : 'false');
        if (!disableControls) return;

        document.getElementById('account-edit-form')
            ?.querySelectorAll('input, select, textarea, button')
            .forEach((control) => {
                if (control.matches('[data-bs-dismiss="modal"], .btn-close')) return;
                control.disabled = loading;
            });
    }

    async function openCreateModal() {
        accountModalInitializing = true;
        resetAccountForm();
        $('#accountModalLabel').text('계정과목 등록');
        $('#btnDeleteAccount').hide();
        renderParentAccountOptions();
        accountModal?.show();
        scheduleAccountModalPostOpenWork();
    }

    async function openEditModal(row) {
        const id = row.id || '';
        if (!id) return;

        accountModalInitializing = true;
        fillAccountForm(row, { deferSubAccounts: true });
        $('#accountModalLabel').text('계정과목 수정');
        $('#btnDeleteAccount').show();
        setAccountModalLoading(true);
        accountModal?.show();
        scheduleAccountModalPostOpenWork();

        try {
            const json = await fetchJson(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
            if (!json.success) {
                accountModalInitializing = false;
                notify('error', json.message || '계정과목 상세 조회에 실패했습니다.');
                return;
            }

            fillAccountForm(json.data || row, { deferSubAccounts: true });
            $('#accountModalLabel').text('계정과목 수정');
            $('#btnDeleteAccount').show();
            if ($('#modal_allow_sub_account').val() === '1') {
                await loadSubAccounts($('#modal_account_id').val());
            } else {
                accountModalInitializing = false;
                markAccountFormClean();
            }
        } catch (err) {
            accountModalInitializing = false;
            console.error('[ledger-account] detail failed:', err);
            notify('error', '계정과목 상세 조회 중 오류가 발생했습니다.');
        } finally {
            setAccountModalLoading(false);
        }
    }
    function setNormalBalance(value) {
        const normalized = value === 'credit' ? 'credit' : 'debit';
        $(`input[name="normal_balance"][value="${normalized}"]`).prop('checked', true);
    }

    function fillAccountForm(data = {}, options = {}) {
        resetAccountForm();

        $('#modal_account_id').val(data.id || '');
        $('#modal_account_code').val(formatAccountCodeDisplay(data.account_code || ''));
        $('#modal_account_name').val(data.account_name || '');
        setModalParentAccount(data.parent_id || '');
        $('#modal_account_group').val(data.account_group || '');
        setNormalBalance(data.normal_balance || 'debit');
        setPostingToggle(data.is_posting ?? 1);
        setAccountStatusToggle(data.is_active ?? 1);
        setSubAccountToggle(data.allow_sub_account ?? 0);
        $('#modal_note').val(data.note || '');
        $('#modal_memo').val(data.memo || '');

        updateModalSubAccountSection({ deferLoad: options.deferSubAccounts === true });
    }

    function resetAccountForm() {
        const form = document.getElementById('account-edit-form');
        form?.reset();

        $('#modal_account_id').val('');
        showParentAccountSelect('');
        $('#modal_new_parent_code').val('');
        $('#modal_new_parent_name').val('');
        setSubAccountToggle(0);
        setNormalBalance('debit');
        setPostingToggle(1);
        setAccountStatusToggle(1);
        $('#btnDeleteAccount').hide();
        modalDraftSubAccounts = [];

        updateModalSubAccountSection();
    }

    async function saveAccount(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const fd = new FormData(form);
        fd.set('account_code', normalizeAccountCodeValue(fd.get('account_code')));
        fd.set('new_parent_code', normalizeAccountCodeValue(fd.get('new_parent_code')));

        if (!validateAccountRequiredPolicies()) {
            return;
        }

        const subAccountRows = collectModalSubAccountRows();
        if (subAccountRows === null) return;
        fd.set('sub_accounts', JSON.stringify(subAccountRows));

        try {
            const res = await $.ajax({ url: API.SAVE, type: 'POST', data: fd, processData: false, contentType: false });
            if (!res?.success) {
                notify('error', res?.message || '저장에 실패했습니다.');
                return;
            }

            notify('success', '저장되었습니다.');
            modalDraftSubAccounts = [];
            skipAccountCloseConfirm = true;
            accountModal?.hide();
            accountTable?.ajax.reload(null, false);
        } catch (err) {
            console.error('[ledger-account] save failed:', err);
            notify('error', '저장 중 오류가 발생했습니다.');
        }
    }

    async function deleteAccount() {
        const id = $('#modal_account_id').val();
        if (!id) return;
        if (!confirm('계정과목을 삭제하시겠습니까?')) return;

        try {
            const res = await $.post(API.DELETE, { id });
            if (!res?.success) {
                notify('error', res?.message || '삭제에 실패했습니다.');
                return;
            }

            notify('success', '삭제되었습니다.');
            skipAccountCloseConfirm = true;
            accountModal?.hide();
            accountTable?.ajax.reload(null, false);
        } catch (err) {
            console.error('[ledger-account] delete failed:', err);
            notify('error', '삭제 중 오류가 발생했습니다.');
        }
    }
    async function loadParentAccounts() {
        try {
            const json = await fetchJson(`${API.LIST}?_=${Date.now()}`);
            mergeParentAccounts(Array.isArray(json?.data) ? json.data : []);
            parentAccountsLoadedAt = Date.now();
        } catch (err) {
            console.error('[ledger-account] parent accounts load failed:', err);
        }
    }

    function mergeParentAccounts(rows = []) {
        const merged = new Map(parentAccounts.map((row) => [String(row.id || ''), row]));

        rows.forEach((row) => {
            const id = String(row?.id || '');
            if (!id) return;

            merged.set(id, {
                id,
                account_code: row.account_code || '',
                account_name: row.account_name || '',
                account_group: row.account_group || '',
                level: row.level ?? ''
            });
        });

        parentAccounts = Array.from(merged.values()).sort((a, b) => {
            return String(a.account_code || '').localeCompare(String(b.account_code || ''), 'ko');
        });

        renderParentAccountOptions();
    }

    function renderParentAccountOptions() {
        const select = document.getElementById('modal_parent_id');
        if (!select) return;

        const currentValue = select.value;
        const currentAccount = document.getElementById('modal_account_id')?.value || '';
        select.innerHTML = '';

        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '선택 없음';
        select.appendChild(empty);

        parentAccounts
            .filter((account) => account.id && account.id !== currentAccount)
            .forEach((account) => {
                const option = document.createElement('option');
                option.value = account.id;
                option.textContent = `${formatAccountCodeDisplay(account.account_code || '')} ${account.account_name || ''}`.trim();
                select.appendChild(option);
            });

        const create = document.createElement('option');
        create.value = NEW_PARENT_ACCOUNT_VALUE;
        create.textContent = '+ 신규 상위계정 생성';
        select.appendChild(create);

        select.value = Array.from(select.options).some((option) => option.value === currentValue) ? currentValue : '';
        if (window.jQuery && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).trigger('change.select2');
        }
    }
    function setModalParentAccount(value) {
        renderParentAccountOptions();

        const select = document.getElementById('modal_parent_id');
        const normalized = String(value || '');
        const hasOption = select
            ? Array.from(select.options).some((option) => option.value === normalized)
            : false;

        showParentAccountSelect(hasOption ? normalized : '');
    }

    function syncParentAccountInputs(source) {
        const select = document.getElementById('modal_parent_id');
        const codeInput = document.getElementById('modal_new_parent_code');

        if (source === select && select.value === NEW_PARENT_ACCOUNT_VALUE) {
            showParentAccountInput();
            return;
        }

        if (source === select && select.value) {
            $('#modal_new_parent_code').val('');
            $('#modal_new_parent_name').val('');
            return;
        }

        if (source === codeInput) {
            codeInput.value = String(codeInput.value || '').trim();
        }
    }

    function showParentAccountSelect(value = '') {
        const select = document.getElementById('modal_parent_id');
        const inputWrap = document.getElementById('modal_parent_account_input_wrap');
        const codeInput = document.getElementById('modal_new_parent_code');
        const nameInput = document.getElementById('modal_new_parent_name');

        if (inputWrap) inputWrap.classList.add('d-none');
        if (select) {
            select.classList.remove('d-none');
            select.disabled = false;
            select.value = value;
        }
        setParentSelect2Visible(true);
        if (codeInput) {
            codeInput.disabled = true;
            codeInput.required = false;
            codeInput.value = '';
        }
        if (nameInput) {
            nameInput.disabled = true;
            nameInput.required = false;
            nameInput.value = '';
        }
    }

    function showParentAccountInput() {
        const select = document.getElementById('modal_parent_id');
        const inputWrap = document.getElementById('modal_parent_account_input_wrap');
        const codeInput = document.getElementById('modal_new_parent_code');
        const nameInput = document.getElementById('modal_new_parent_name');

        if (select) {
            select.classList.add('d-none');
            select.disabled = true;
            select.value = '';
        }
        setParentSelect2Visible(false);
        if (inputWrap) inputWrap.classList.remove('d-none');
        if (codeInput) {
            codeInput.disabled = false;
            codeInput.required = true;
            codeInput.focus();
        }
        if (nameInput) {
            nameInput.disabled = false;
            nameInput.required = false;
        }
    }

    function initParentAccountSelect2() {
        const select = document.getElementById('modal_parent_id');
        const modalEl = document.getElementById('accountModal');
        if (!select || !window.jQuery || !window.jQuery.fn?.select2) return;

        const $select = window.jQuery(select);
        if ($select.hasClass('select2-hidden-accessible')) return;

        $select.select2({
            dropdownParent: modalEl ? window.jQuery(modalEl) : window.jQuery(document.body),
            width: '100%',
            placeholder: '상위계정 검색',
            allowClear: true,
            language: { noResults: () => '검색 결과가 없습니다' }
        });

        $select.on('select2:select select2:clear', () => {
            window.jQuery(select).trigger('change');
        });
    }
    function isParentAccountInputMode() {
        const inputWrap = document.getElementById('modal_parent_account_input_wrap');
        return Boolean(inputWrap && !inputWrap.classList.contains('d-none'));
    }

    function setParentSelect2Visible(visible) {
        const select = document.getElementById('modal_parent_id');
        const container = select?.nextElementSibling;
        if (!container?.classList?.contains('select2-container')) return;
        container.classList.toggle('d-none', !visible);
    }

    async function loadSubAccounts(accountId) {
        const tbody = document.querySelector('#modal-subaccount-table tbody');
        if (!tbody) return;

        if (!accountId) {
            await renderModalDraftSubAccounts();
            return;
        }

        if (!document.getElementById('accountModal')?.classList.contains('is-loading-detail')) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">불러오는 중...</td></tr>';
        }

        try {
            const json = await fetchJson(`${API.SUB_LIST}?account_id=${encodeURIComponent(accountId)}`);
            const rows = Array.isArray(json?.data) ? json.data : [];

            modalDraftSubAccounts = rows.map((row) => ({
                id: row.id || '',
                sub_code: row.sub_code || '',
                sub_name: row.sub_name || '',
                is_required: Number(row.is_required ?? 0),
            }));
            ensureModalDraftInput();
            await renderModalDraftSubAccounts();
            if (accountModalInitializing) {
                markAccountFormClean();
                accountModalInitializing = false;
            }
        } catch (err) {
            console.error('[ledger-account] sub list failed:', err);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">보조계정을 불러오지 못했습니다.</td></tr>';
        }
    }

    function scheduleSubAccountsLoad(accountId) {
        const id = String(accountId || '').trim();
        if (!id) return;

        if (subAccountLoadTimer) {
            window.clearTimeout(subAccountLoadTimer);
        }

        const seq = ++subAccountLoadSeq;
        subAccountLoadTimer = window.setTimeout(() => {
            subAccountLoadTimer = 0;
            if (seq !== subAccountLoadSeq) return;
            void loadSubAccounts(id);
        }, 80);
    }

    function addSubAccount() {
        syncModalDraftSubAccounts();
        modalDraftSubAccounts.push({ sub_code: '', sub_name: '', is_required: '' });
        renderModalDraftSubAccounts();
        const selects = document.querySelectorAll('#modal-subaccount-table .modal-sub-code-select');
        selects[selects.length - 1]?.focus();
    }




    function renderModalDraftSubAccounts() {
        const tbody = document.querySelector('#modal-subaccount-table tbody');
        if (!tbody) return Promise.resolve();

        ensureModalDraftInput();
        const section = document.getElementById('modal_subaccount_section');
        section?.classList.add('is-hydrating');

        tbody.innerHTML = modalDraftSubAccounts.map((row, index) => `
            <tr>
                <td class="text-center">${index + 1}</td>
                <td>
                    ${renderSubAccountCodeSelect('modal-sub-code-select', row.sub_code || '', 'modal-sub-code-select', index)}
                </td>
                <td>
                    ${renderRequiredSelect('modal-sub-required-select', row.is_required)}
                </td>
                <td class="text-center">
                    <button type="button"
                            class="sub-delete-action btnDeleteSubAccount"
                            data-draft-index="${index}">
                        - 삭제
                    </button>
                </td>
            </tr>
        `).join('');

        return initSubAccountCodeSelects(tbody)
            .finally(() => {
                section?.classList.remove('is-hydrating');
            });
    }
    function ensureModalDraftInput() {
        if (!modalDraftSubAccounts.length) {
            modalDraftSubAccounts.push({ sub_code: '', sub_name: '', is_required: '' });
        }
    }

    function syncModalDraftSubAccounts() {
        const rows = Array.from(document.querySelectorAll('#modal-subaccount-table tbody tr'));
        if (!rows.length) return;

        modalDraftSubAccounts = rows.map((row, index) => {
            const subCode = row.querySelector('.modal-sub-code-select')?.value?.trim() || '';
            const requiredValue = row.querySelector('.modal-sub-required-select')?.value ?? '';
            return {
                id: modalDraftSubAccounts[index]?.id || '',
                sub_code: subCode,
                sub_name: getSubAccountCodeName(subCode),
                is_required: requiredValue === '' ? '' : Number(requiredValue),
            };
        });
    }

    function collectModalSubAccountRows() {
        if ($('#modal_allow_sub_account').val() !== '1') {
            return [];
        }

        syncModalDraftSubAccounts();

        const rows = modalDraftSubAccounts
            .map((row) => ({
                id: row.id || '',
                sub_code: String(row.sub_code || '').trim(),
                sub_name: getSubAccountCodeName(row.sub_code, row.sub_name),
                is_required: row.is_required,
            }))
            .filter((row) => row.sub_code !== '');

        if (!rows.length) {
            notify('warning', '보조계정 사용 시 보조계정명을 1개 이상 선택해주세요.');
            document.querySelector('#modal-subaccount-table .modal-sub-code-select')?.focus();
            return null;
        }

        const codeSelects = document.querySelectorAll('#modal-subaccount-table .modal-sub-code-select');
        const requiredSelects = document.querySelectorAll('#modal-subaccount-table .modal-sub-required-select');
        const seen = new Set();
        for (const [index, row] of rows.entries()) {
            const rowNumber = index + 1;
            const codeSelect = codeSelects[index];
            const requiredSelect = requiredSelects[index];

            if (!row.sub_code) {
                notify('warning', `${rowNumber}번째 보조계정명을 선택해주세요.`);
                codeSelect?.focus();
                return null;
            }

            if (row.is_required !== 0 && row.is_required !== 1) {
                notify('warning', `${rowNumber}번째 보조계정 옵션을 선택해주세요.`);
                requiredSelect?.focus();
                return null;
            }

            if (seen.has(row.sub_code)) {
                notify('warning', '보조계정은 중복 추가할 수 없습니다.');
                return null;
            }
            seen.add(row.sub_code);
        }

        return rows.map((row) => ({
            ...row,
            is_required: Number(row.is_required)
        }));
    }

    function renderTrashDetail(detailEl, data = {}) {
        detailEl.innerHTML = `
            <h5 class="mb-3">${escapeHtml(data.account_name ?? '')}</h5>
            <table class="table table-sm">
                <tr><th width="140">순번</th><td>${escapeHtml(data.sort_no ?? '')}</td></tr>
                <tr><th>계정코드</th><td>${escapeHtml(data.account_code ?? '')}</td></tr>
                <tr><th>계정과목명</th><td>${escapeHtml(data.account_name ?? '')}</td></tr>
                <tr><th>상위계정</th><td>${escapeHtml(data.parent_name ?? '')}</td></tr>
                <tr><th>계정구분</th><td>${escapeHtml(data.account_group ?? '')}</td></tr>
                <tr><th>정상잔액</th><td>${escapeHtml(data.normal_balance === 'credit' ? '대변' : '차변')}</td></tr>
                <tr><th>전표입력</th><td>${Number(data.is_posting ?? 0) === 1 ? '가능' : '불가'}</td></tr>
                <tr><th>보조계정</th><td>${Number(data.allow_sub_account ?? 0) === 1 ? '사용' : '미사용'}</td></tr>
                <tr><th>상태</th><td>${Number(data.is_active ?? 0) === 1 ? '사용' : '미사용'}</td></tr>
                <tr><th>비고</th><td>${escapeHtml(data.note ?? '')}</td></tr>
                <tr><th>메모</th><td>${escapeHtml(data.memo ?? '')}</td></tr>
                <tr><th>생성일시</th><td>${escapeHtml(data.created_at ?? '')}</td></tr>
                <tr><th>생성자</th><td>${escapeHtml(data.created_by_name ?? data.created_by ?? '')}</td></tr>
                <tr><th>수정일시</th><td>${escapeHtml(data.updated_at ?? '')}</td></tr>
                <tr><th>수정자</th><td>${escapeHtml(data.updated_by_name ?? data.updated_by ?? '')}</td></tr>
                <tr><th>삭제일시</th><td>${escapeHtml(data.deleted_at ?? '')}</td></tr>
                <tr><th>삭제자</th><td>${escapeHtml(data.deleted_by_name ?? data.deleted_by ?? '')}</td></tr>
            </table>
        `;
    }
    function updateCount(count) {
        const el = document.getElementById('accountCount');
        if (el) el.textContent = `총 ${count ?? 0}건`;
    }
    function renderSubAccountCodeSelect(className, selectedValue = '', extraClass = '', index = '') {
        const selectClass = [className, extraClass].filter(Boolean).join(' ');
        return `
            <select class="form-select form-select-sm ${selectClass}"
                    name="${SUB_ACCOUNT_SELECT_NAME}"
                    data-code-group="${SUB_ACCOUNT_CODE_GROUP}"
                    data-index="${escapeHtml(index)}"
                    data-selected="${escapeHtml(selectedValue || '')}">
                <option value="" ${selectedValue ? '' : 'selected'}>선택(없음)</option>
                ${selectedValue ? `<option value="${escapeHtml(selectedValue)}" selected>${escapeHtml(getSubAccountCodeName(selectedValue, selectedValue))}</option>` : ''}
            </select>
        `;
    }

    function renderRequiredSelect(className, selectedValue = 0) {
        const hasValue = selectedValue === 0 || selectedValue === 1 || selectedValue === '0' || selectedValue === '1';
        const value = hasValue ? Number(selectedValue) : '';
        return `
            <select class="form-select form-select-sm ${className}">
                <option value="" ${value === '' ? 'selected' : ''}>선택하세요</option>
                <option value="1" ${value === 1 ? 'selected' : ''}>필수</option>
                <option value="0" ${value === 0 ? 'selected' : ''}>선택</option>
            </select>
        `;
    }

    async function initSubAccountCodeSelects(root = document) {
        const selects = Array.from(root.querySelectorAll(`select[data-code-group="${SUB_ACCOUNT_CODE_GROUP}"]`));
        await initCodeSelectControls(root);
        selects.forEach((select) => {
            const selected = select.dataset.selected || select.value || '';
            const emptyOption = select.querySelector('option[value=""]');
            if (emptyOption) {
                emptyOption.textContent = '선택(없음)';
            } else {
                select.insertAdjacentHTML('afterbegin', '<option value="">선택(없음)</option>');
            }

            if (selected) {
                select.value = selected;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                select.value = '';
            }

            if (window.jQuery && window.jQuery(select).hasClass('select2-hidden-accessible')) {
                window.jQuery(select).trigger('change.select2');
            }
        });
    }
    function getSubAccountCodeName(code, fallback = '') {
        const value = String(code || '').trim();
        if (!value) return '';
        return getCodeName(SUB_ACCOUNT_SELECT_NAME, value) || fallback || value;
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }

        if (type === 'error' || type === 'warning') {
            alert(message);
        }
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
