// Path: PROJECT_ROOT . '/public/assets/js/pages/ledger/account.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, setTableSelectedRow } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { actorDisplay } from '/public/assets/js/common/actor.js';
import { initCodeSelectControls } from '/public/assets/js/pages/main/settings/system/code-select.js';
import { runDeleteProgress } from '/public/assets/js/common/delete-progress.js';
import '/public/assets/js/components/trash-manager.js';
import {
    ACCOUNT_COLUMN_MAP,
    ACCOUNT_COLUMN_ORDER,
    DATE_OPTIONS,
    ACCOUNT_TABLE_SETTINGS_STORAGE_KEY,
    ACCOUNT_SUB_TABLE_SETTINGS_STORAGE_KEY,
    ACCOUNT_USER_SETTING_PAGE_KEY,
    ACCOUNT_SUB_USER_SETTING_PAGE_KEY,
    ACCOUNT_META_DOMAIN,
    ACCOUNT_SUB_META_DOMAIN,
    NEW_PARENT_ACCOUNT_VALUE,
    SUB_ACCOUNT_CODE_GROUP,
    sanitizeAccountTableSettingsState,
    sanitizeSubAccountTableSettingsState,
    currentAccountPolicyState,
    accountFieldLabel,
    accountFieldRequirement,
    accountFieldStarMarkup,
    applyAccountModalPolicyLabels,
    bindAccountPolicySync,
    focusAccountPolicyField,
    collectAccountModalValues,
    validateAccountRequiredPolicies,
    initAccountCodeFormat,
    normalizeAccountCodeValue,
    isNumericAccountCode,
    formatAccountCodeDisplay,
    formatAccountCodeInput,
    getAccountLevel,
    isPostableAccount,
    renderPostableBadge,
    renderAccountTreeCell,
    renderTrashDetail,
    updateCount,
    renderSubAccountCodeSelect,
    renderRequiredSelect,
    initSubAccountCodeSelects,
    getSubAccountCodeName,
    notify,
    escapeHtml,
} from '/public/assets/js/pages/ledger/account-support.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    const layoutTestMode = new URLSearchParams(window.location.search).get('dtCssTest');
    if (layoutTestMode === 'account-width-off') {
        document.documentElement.classList.add('dt-test-account-width-off');
    }

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
        SUB_LIST: '/api/ledger/sub-account/list',
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

    let accountTable = null;
    let subAccountTable = null;
    let accountModal = null;
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

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.account = function (row = {}) {
        return `
            <td>${escapeHtml(formatAccountCodeDisplay(row.account_code ?? ''))}</td>
            <td>${escapeHtml(row.account_name ?? '')}</td>
            <td>${escapeHtml(row.account_group ?? '')}</td>
            <td>${escapeHtml(row.deleted_at ?? '')}</td>
            <td>${escapeHtml(actorDisplay(row, 'deleted_by'))}</td>
            <td class="text-center">
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id ?? '')}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id ?? '')}">영구삭제</button>
            </td>
        `;
    };
    document.addEventListener('DOMContentLoaded', async () => {
        initModals();
        initAccountCodeFormat();
        await initDataTable();
        bindEvents();
    });

    document.addEventListener('trash:changed', (event) => {
        if (event.detail?.type === 'account') {
            accountTable?.ajax.reload(null, false);
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

        bindAccountPolicySync();
        applyAccountModalPolicyLabels(document);
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
                                <th width="190">보조계정 대상</th>
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
        initSubAccountTable();
    }

    function createSubAccountDraftRow(row = {}) {
        return {
            __rowKey: String(row.__rowKey || row.id || `${Date.now()}-${Math.random().toString(16).slice(2)}`),
            id: String(row.id || ''),
            sub_code: String(row.sub_code || ''),
            sub_name: String(row.sub_name || ''),
            is_required: row.is_required === '' ? '' : Number(row.is_required ?? ''),
        };
    }

    function buildSubAccountTableRows() {
        return modalDraftSubAccounts.map((row, index) => ({
            ...createSubAccountDraftRow(row),
            sort_no: index + 1,
            sub_name: String(row.sub_name || getSubAccountCodeName(row.sub_code, row.sub_name) || ''),
        }));
    }

    function refreshSubAccountTable() {
        if (!subAccountTable) return;
        const rows = buildSubAccountTableRows();
        subAccountTable.clear();
        subAccountTable.rows.add(rows);
        subAccountTable.draw(false);
    }

    function hydrateSubAccountTargetCells(root = null) {
        const scope = root instanceof HTMLElement ? root : document;
        const tableRows = Array.from(scope.querySelectorAll('#modal-subaccount-table tbody tr'));

        tableRows.forEach((rowEl, index) => {
            const targetCell = rowEl.querySelector('td:nth-child(4)');
            if (!(targetCell instanceof HTMLElement)) {
                return;
            }

            const draftRow = modalDraftSubAccounts[index] || {};
            targetCell.innerHTML = renderSubAccountCodeSelect(
                'modal-sub-code-select',
                draftRow?.sub_code ?? '',
                'modal-sub-code-select',
                draftRow?.__rowKey || ''
            );
        });
    }

    function buildSubAccountColumns() {
        return [{
            data: null,
            title: '<i class="bi bi-arrows-move"></i>',
            className: 'reorder-handle no-sort no-colvis text-center',
            headerClassName: 'no-colvis text-center',
            orderable: false,
            searchable: false,
            defaultContent: '<i class="bi bi-list"></i>',
            settingsKey: '__reorder',
            width: '36px',
            widthResizable: true,
        }, {
            data: 'sort_no',
            title: '순번',
            className: 'text-center',
            width: '56px',
            settingsKey: 'sort_no',
        }, {
            data: 'sub_code',
            title: '보조계정코드',
            settingsKey: 'sub_code',
            render(value, type) {
                if (type !== 'display') return value ?? '';
                return escapeHtml(value ?? '');
            },
        }, {
            data: 'sub_name',
            title: '보조계정 대상',
            settingsKey: 'sub_name',
            render(value, type, row) {
                if (type === 'display') return row?.sub_name ?? value ?? '';
                return row?.sub_code ?? value ?? '';
            },
        }, {
            data: 'is_required',
            title: '필수구분',
            className: 'text-center',
            settingsKey: 'is_required',
            render(value, type, row) {
                if (type !== 'display') return value ?? '';
                return renderRequiredSelect('modal-sub-required-select', value, row.__rowKey || '');
            },
        }, {
            data: null,
            title: '관리',
            className: 'text-center no-colvis',
            headerClassName: 'text-center no-colvis',
            orderable: false,
            searchable: false,
            settingsKey: '__actions',
            render(_value, type, row) {
                if (type !== 'display') return '';
                return `
                    <button type="button"
                            class="sub-delete-action btnDeleteSubAccount"
                            data-row-key="${escapeHtml(row.__rowKey || '')}">
                        - 삭제
                    </button>
                `;
            },
        }];
    }

    async function initSubAccountTable() {
        if (subAccountTable || !document.getElementById('modal-subaccount-table')) {
            return;
        }

        sanitizeSubAccountTableSettingsState();
        subAccountTable = await createDataTable({
            tableSelector: '#modal-subaccount-table',
            columns: buildSubAccountColumns(),
            initialData: buildSubAccountTableRows(),
            defaultOrder: [[2, 'asc']],
            pageLength: 100,
            autoWidth: false,
            pageLoading: false,
            paging: false,
            info: false,
            searchTableId: 'ledgerSubAccount',
            deleteButton: false,
            bulkDelete: false,
            rowIdField: '__rowKey',
            tableSettings: {
                pageKey: 'ledger.account.sub',
                tableKey: 'sub-account-table',
                storageKey: ACCOUNT_SUB_TABLE_SETTINGS_STORAGE_KEY,
                userSettingPageKey: ACCOUNT_SUB_USER_SETTING_PAGE_KEY,
                metaDomain: ACCOUNT_SUB_META_DOMAIN,
                tableLabel: '보조계정',
                title: '보조계정 테이블 설정',
                defaultVisibleColumns: ['sort_no', 'sub_name', 'is_required'],
            },
            buttons: [
                { text: '추가', className: 'btn btn-warning btn-sm', action: addSubAccount },
            ],
        });

        subAccountTable.on('draw.dt', () => {
            window.setTimeout(() => {
                const tableEl = document.getElementById('modal-subaccount-table');
                if (!tableEl) {
                    return;
                }

                const initRoot = tableEl.closest('.dataTables_wrapper') || tableEl;
                hydrateSubAccountTargetCells(initRoot);
                void initSubAccountCodeSelects(initRoot);
            }, 0);
        });

        document.querySelector('#modal-subaccount-table')?.addEventListener('datatable:move-selected', (event) => {
            const ids = Array.isArray(event.detail?.ids) ? event.detail.ids.map((id) => String(id || '').trim()) : [];
            if (ids.length === 0) return;

            event.preventDefault();
            syncModalDraftSubAccounts();

            const rows = [...modalDraftSubAccounts];
            const selected = rows.filter((row) => ids.includes(String(row.__rowKey || '')));
            const remaining = rows.filter((row) => !ids.includes(String(row.__rowKey || '')));
            if (selected.length === 0) {
                return;
            }

            if (event.detail?.direction === 'up') {
                const firstIndex = rows.findIndex((row) => ids.includes(String(row.__rowKey || '')));
                const insertIndex = Math.max(0, firstIndex - 1);
                remaining.splice(insertIndex, 0, ...selected);
            } else {
                const lastIndex = rows.map((row) => String(row.__rowKey || '')).reduce((acc, key, index) => ids.includes(key) ? index : acc, -1);
                const beforeCount = rows.slice(0, lastIndex + 1).filter((row) => !ids.includes(String(row.__rowKey || ''))).length;
                remaining.splice(Math.min(remaining.length, beforeCount + 1), 0, ...selected);
            }

            modalDraftSubAccounts = remaining.map((row) => createSubAccountDraftRow(row));
            refreshSubAccountTable();
        });
    }

    async function initDataTable() {
        sanitizeAccountTableSettingsState();
        accountTable = await createDataTable({
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
                userSettingPageKey: ACCOUNT_USER_SETTING_PAGE_KEY,
                metaDomain: ACCOUNT_META_DOMAIN,
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
                { text: '신규등록', className: 'btn btn-warning btn-sm', action: () => { void openCreateModal().catch((error) => notify('error', error.message)); } }
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
            excludeFields: ['id', 'parent_id', 'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at', 'deleted_by', 'has_sub_account']
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

            if (field === 'parent_id') {
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
                    if (config.type === 'actor') return escapeHtml(actorDisplay(row, field));

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
                syncModalDraftSubAccounts();
                const rowKey = String(this.dataset.rowKey || '').trim();
                modalDraftSubAccounts = modalDraftSubAccounts.filter((row) => String(row.__rowKey || '') !== rowKey);
                renderModalDraftSubAccounts();
            })
            .off('change.subCodeModal')
            .on('change.subCodeModal', '.modal-sub-code-select', function () {
                const rowKey = String(this.dataset.rowKey || '').trim();
                const draft = modalDraftSubAccounts.find((row) => String(row.__rowKey || '') === rowKey);
                if (!draft) return;
                draft.sub_code = String(this.value || '').trim();
                draft.sub_name = getSubAccountCodeName(draft.sub_code, draft.sub_name);
            })
            .off('change.subRequiredModal')
            .on('change.subRequiredModal', '.modal-sub-required-select', function () {
                const rowKey = String(this.dataset.rowKey || '').trim();
                const draft = modalDraftSubAccounts.find((row) => String(row.__rowKey || '') === rowKey);
                if (!draft) return;
                draft.is_required = this.value === '' ? '' : Number(this.value);
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
            await runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '계정과목을 휴지통으로 이동 중', trashChanged: true }, async () => {
                const res = await $.post(API.DELETE, { id });
                if (!res?.success) throw new Error(res?.message || '삭제에 실패했습니다.');
                notify('success', '삭제되었습니다.');
                skipAccountCloseConfirm = true;
                accountModal?.hide();
                await new Promise(resolve => accountTable?.ajax.reload(() => resolve(), false));
            });
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

            modalDraftSubAccounts = rows.map((row) => createSubAccountDraftRow({
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
        modalDraftSubAccounts.push(createSubAccountDraftRow({ sub_code: '', sub_name: '', is_required: '' }));
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
        modalDraftSubAccounts = modalDraftSubAccounts.map((row) => createSubAccountDraftRow(row));
        refreshSubAccountTable();

        return new Promise((resolve) => {
            window.setTimeout(() => {
                hydrateSubAccountTargetCells(document);
                initSubAccountCodeSelects(document)
                    .finally(() => {
                        section?.classList.remove('is-hydrating');
                        resolve();
                    });
            }, 0);
        });
    }
    function ensureModalDraftInput() {
        if (!modalDraftSubAccounts.length) {
            modalDraftSubAccounts.push(createSubAccountDraftRow({ sub_code: '', sub_name: '', is_required: '' }));
        }
    }

    function syncModalDraftSubAccounts() {
        const rows = Array.from(document.querySelectorAll('#modal-subaccount-table tbody tr'));
        if (!rows.length) return;

        modalDraftSubAccounts = rows.map((row, index) => {
            const subCode = row.querySelector('.modal-sub-code-select')?.value?.trim() || '';
            const requiredValue = row.querySelector('.modal-sub-required-select')?.value ?? '';
            return createSubAccountDraftRow({
                __rowKey: row.querySelector('.modal-sub-code-select')?.dataset.rowKey
                    || row.querySelector('.modal-sub-required-select')?.dataset.rowKey
                    || modalDraftSubAccounts[index]?.__rowKey
                    || '',
                id: modalDraftSubAccounts[index]?.id || '',
                sub_code: subCode,
                sub_name: getSubAccountCodeName(subCode),
                is_required: requiredValue === '' ? '' : Number(requiredValue),
            });
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
            notify('warning', '보조계정 사용 시 보조계정 대상을 1개 이상 선택해주세요.');
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
                notify('warning', `${rowNumber}번째 보조계정 대상을 선택해주세요.`);
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

})();
