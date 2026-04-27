// Path: PROJECT_ROOT . '/public/assets/js/pages/ledger/account.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, clearTableSelectedRows, setTableSelectedRow } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { initCodeSelectControls, getCodeName, onCodeOptionsLoaded } from '/public/assets/js/common/code-select.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    'use strict';

    const API = {
        LIST: '/api/ledger/account/list',
        DETAIL: '/api/ledger/account/detail',
        SAVE: '/api/ledger/account/save',
        DELETE: '/api/ledger/account/soft-delete',
        REORDER: '/api/ledger/account/reorder',
        TRASH: '/api/ledger/account/trash',
        RESTORE: '/api/ledger/account/restore',
        PURGE: '/api/ledger/account/hard-delete',
        PURGE_ALL: '/api/ledger/account/hard-delete-all',
        EXCEL_TEMPLATE: '/api/ledger/account/template',
        EXCEL_DOWNLOAD: '/api/ledger/account/excel',
        EXCEL_UPLOAD: '/api/ledger/account/excel-upload',
        SUB_LIST: '/api/ledger/sub-account/list',
        SUB_SAVE: '/api/ledger/sub-account/save',
        SUB_UPDATE: '/api/ledger/sub-account/update',
        SUB_DELETE: '/api/ledger/sub-account/delete'
    };

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
        note: { label: '비고', visible: true },
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
        id: { label: 'ID', visible: false, headerClassName: 'no-colvis' }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '생성일자' },
        { value: 'updated_at', label: '수정일자' }
    ];

    const NEW_PARENT_ACCOUNT_VALUE = '__new_parent_account__';
    const SUB_ACCOUNT_CODE_GROUP = 'REF_TARGET';
    const SUB_ACCOUNT_SELECT_NAME = 'ledger_sub_ref_target';

    let accountTable = null;
    let accountModal = null;
    let excelModal = null;
    let currentAccountId = '';
    let parentAccounts = [];
    let currentSubPolicies = [];
    let modalDraftSubAccounts = [];

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.account = function (row = {}) {
        return `
            <td>${escapeHtml(row.account_code ?? '')}</td>
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
        initDataTable();
        bindEvents();
        initCodeSelectControls(document);
        onCodeOptionsLoaded(() => {
            refreshSubAccountLabels();
        });
    });

    document.addEventListener('trash:changed', (event) => {
        if (event.detail?.type === 'account') {
            accountTable?.ajax.reload(null, false);
        }
    });

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
            accountModal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });
            modalEl.addEventListener('hidden.bs.modal', resetAccountForm);
        }

        const excelEl = document.getElementById('accountExcelModal');
        if (excelEl) {
            excelModal = bootstrap.Modal.getOrCreateInstance(excelEl, { focus: false });
        }

    }

    function initExcelDataset() {
        const form = document.getElementById('account-excel-upload-form');
        if (!form) return;

        form.dataset.templateUrl = API.EXCEL_TEMPLATE;
        form.dataset.downloadUrl = API.EXCEL_DOWNLOAD;
        form.dataset.uploadUrl = API.EXCEL_UPLOAD;
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
                <button type="button"
                        class="btn btn-primary btn-sm"
                        id="btnAddSubAccountModal">
                    + 추가
                </button>
            </div>
            <div class="card-body py-2">
                <div class="table-responsive modal-subaccount-table-wrap">
                    <table class="table table-sm table-bordered align-middle mb-0" id="modal-subaccount-table">
                        <thead class="table-light">
                            <tr>
                                <th width="70" class="text-center">순번</th>
                                <th>보조계정명</th>
                                <th width="110" class="text-center">옵션</th>
                                <th width="90" class="text-center">삭제</th>
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
            columns: buildColumns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            autoWidth: false,
            searchTableId: 'ledgerAccount',
            buttons: [
                {
                    text: '엑셀 관리',
                    className: 'btn btn-success btn-sm',
                    action: () => excelModal?.show()
                },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal
                },
                {
                    text: '계정과목',
                    className: 'btn btn-warning btn-sm',
                    action: openCreateModal
                }
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
        });

        accountTable.on('init.dt draw.dt xhr.dt', () => {
            updateCount(accountTable.page.info()?.recordsDisplay ?? 0);
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
            defaultContent: '<i class="bi bi-list"></i>'
        }];

        Object.entries(ACCOUNT_COLUMN_MAP).forEach(([field, config]) => {
            columns.push({
                data: field,
                title: config.label,
                visible: config.visible,
                className: config.className || '',
                headerClassName: config.headerClassName || '',
                defaultContent: '',
                render(value, type, row) {
                    if (type !== 'display') return value ?? '';

                if (field === 'normal_balance') {
                    return value === 'credit' ? '대변' : '차변';
                }

                    if (field === 'is_posting') {
                        return Number(value) === 1
                            ? '<span class="badge bg-success">가능</span>'
                            : '<span class="badge bg-secondary">불가</span>';
                    }

                    if (field === 'allow_sub_account') {
                        const hasSubAccount = Number(row?.has_sub_account) === 1;
                        const buttonClass = hasSubAccount ? 'edit' : 'add';
                        const buttonText = hasSubAccount ? '수정' : '추가';

                        return `
                            <button type="button"
                                    class="sub-btn ${buttonClass} btn-sub-account-panel"
                                    data-id="${escapeHtml(row?.id || '')}">
                                ${buttonText}
                            </button>
                        `;
                    }

                    if (field === 'is_active') {
                        return Number(value) === 1
                            ? '<span class="badge bg-success">사용</span>'
                            : '<span class="badge bg-secondary">미사용</span>';
                    }

                    return escapeHtml(value ?? '');
                }
            });
        });

        return columns;
    }

    function bindEvents() {
        $(document)
            .off('keydown.accountSubPanel')
            .on('keydown.accountSubPanel', function (event) {
                if (event.key !== 'Escape') return;
                if (event.target.closest('.modal.show')) return;
                if (event.target.closest('.sub-new-row')) return;
                if (event.target.closest('.sub-edit-row')) return;
                if (!document.querySelector('.account-split-layout.subaccount-open')) return;

                event.preventDefault();
                clearSubAccountPanel();
            });

        $('#account-table tbody')
            .off('click.accountSelect')
            .on('click.accountSelect', 'tr', function () {
                const row = accountTable?.row(this).data();
                if (!row) return;

                selectAccountRow(row, this, false);
            })
            .off('dblclick.accountEdit')
            .on('dblclick.accountEdit', 'tr', function () {
                const row = accountTable?.row(this).data();
                if (row) openEditModal(row);
            });

        $('#account-table tbody')
            .off('click.subAccountPanel')
            .on('click.subAccountPanel', '.btn-sub-account-panel', function (event) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();

                const tr = this.closest('tr');
                const row = accountTable?.row(tr).data();
                if (!row) return;

                selectAccountRow(row, tr, true);
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

        $('#btnBackParentAccountSelect')
            .off('click.accountParentBack')
            .on('click.accountParentBack', () => {
                showParentAccountSelect('');
            });

        $('#btnAddSubPolicy')
            .off('click.subPolicyAdd')
            .on('click.subPolicyAdd', () => {
                currentSubPolicies.push({
                    policy_type: 'custom',
                    is_required: 0,
                    allow_multiple: 0,
                    custom_group_code: ''
                });
                renderSubPolicyRows();
                updateAllowSubAccountDisplay();
            });

        $('#sub-policy-tbody')
            .off('click.subPolicyRemove')
            .on('click.subPolicyRemove', '.btn-remove-policy', function () {
                currentSubPolicies.splice(Number(this.dataset.index), 1);
                renderSubPolicyRows();
                updateAllowSubAccountDisplay();
            })
            .off('change.subPolicyInput')
            .on('change.subPolicyInput input.subPolicyInput', 'select,input', syncSubPolicyRows);

            $('#btnAddSubAccount')
                .off('click.subAdd')
                .on('click.subAdd', () => addSubAccount('panel'));

        $('#btnAddSubAccountModal')
            .off('click.subAddModal')
            .on('click.subAddModal', () => addSubAccount('modal'));

        $('#btnCloseSubPanel')
            .off('click.subClose')
            .on('click.subClose', clearSubAccountPanel);

        $('#subaccount-table tbody')
            .off('click.subDelete')
            .on('click.subDelete', '.btnDeleteSubAccount', function () {
                deleteSubAccount(this.dataset.id);
            })
            .off('click.subEdit')
            .on('click.subEdit', '.btnEditSubAccount', function () {
                editSubAccountInline(this);
            });

        $('#modal_allow_sub_account')
            .off('change.subAccountManage')
            .on('change.subAccountManage', updateModalSubAccountSection);

        $('#btnSubAccountManage')
            .off('click.subAccountManage')
            .on('click.subAccountManage', () => {
                const accountId = $('#modal_account_id').val();

                if (!accountId) {
                    notify('warning', '계정과목을 선택해야 보조계정을 관리할 수 있습니다.');
                    return;
                }

                currentAccountId = accountId;
                loadSubAccounts(currentAccountId, 'modal');
                updateModalSubAccountSection();
            });

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

    function updateSubAccountManageButton() {
        const enabled = $('#modal_allow_sub_account').val() === '1';
        const hasAccountId = Boolean($('#modal_account_id').val());

        $('#btnSubAccountManage').prop('disabled', !enabled || !hasAccountId);
    }

    function updateModalSubAccountSection() {
        const enabled = $('#modal_allow_sub_account').val() === '1';
        const section = document.getElementById('modal_subaccount_section');
        if (section) {
            section.classList.toggle('d-none', !enabled);
        }

        if (!enabled) return;

        const accountId = $('#modal_account_id').val();
        if (accountId) {
            loadSubAccounts(accountId, 'modal');
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

    function selectAccountRow(row, tr, openPanel = true) {
        currentAccountId = row.id || '';
        setTableSelectedRow('#account-table', tr);

        $('#btnAddSubAccount').prop('disabled', !currentAccountId);
        $('#subaccountGuide').text(`${row.account_code || ''} ${row.account_name || ''}`);

        if (openPanel) {
            document.querySelector('.account-split-layout')?.classList.add('subaccount-open');
        }

        loadSubAccounts(currentAccountId);
    }

    function openCreateModal() {
        resetAccountForm();
        loadParentAccounts();
        $('#accountModalLabel').text('계정과목 등록');
        $('#btnDeleteAccount').hide();
        accountModal?.show();
    }

    async function openEditModal(row) {
        const id = row.id || '';
        if (!id) return;

        try {
            await loadParentAccounts();
            const json = await fetchJson(`${API.DETAIL}?id=${encodeURIComponent(id)}`);
            if (!json.success) {
            notify('error', json.message || '계정과목 상세 조회에 실패했습니다.');
                return;
            }

            fillAccountForm(json.data || row);
            $('#accountModalLabel').text('계정과목 수정');
            $('#btnDeleteAccount').show();
            accountModal?.show();
        } catch (err) {
            console.error('[ledger-account] detail failed:', err);
            notify('error', '계정과목 상세 조회 중 오류가 발생했습니다.');
        }
    }

    function fillAccountForm(data = {}) {
        resetAccountForm();

        $('#modal_account_id').val(data.id || '');
        $('#modal_sort_no').val(data.sort_no || '');
        $('#modal_account_code').val(data.account_code || '');
        $('#modal_account_name').val(data.account_name || '');
        setModalParentAccount(data.parent_id || '');
        $('#modal_account_group').val(data.account_group || '');
        $('#modal_normal_balance').val(data.normal_balance || 'debit');
        $('#modal_is_posting').val(String(data.is_posting ?? 1));
        $('#modal_is_active').val(String(data.is_active ?? 1));
        $('#modal_allow_sub_account').val(String(data.allow_sub_account ?? 0));
        $('#modal_note').val(data.note || '');
        $('#modal_memo').val(data.memo || '');

        updateSubAccountManageButton();
        updateModalSubAccountSection();
    }

    function resetAccountForm() {
        const form = document.getElementById('account-edit-form');
        form?.reset();

        $('#modal_account_id').val('');
        $('#modal_sort_no').val('');
        showParentAccountSelect('');
        $('#modal_new_parent_code').val('');
        $('#modal_new_parent_name').val('');
        $('#modal_allow_sub_account').val('0');
        $('#modal_normal_balance').val('debit');
        $('#modal_is_posting').val('1');
        $('#modal_is_active').val('1');
        $('#btnDeleteAccount').hide();
        modalDraftSubAccounts = [];

        updateSubAccountManageButton();
        updateModalSubAccountSection();
    }

    async function saveAccount(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const fd = new FormData(form);

        if (!String(fd.get('account_code') || '').trim() || !String(fd.get('account_name') || '').trim()) {
            notify('warning', '계정코드와 계정과목명은 필수입니다.');
            return;
        }

        if (isParentAccountInputMode() && !String(fd.get('new_parent_code') || '').trim()) {
            notify('warning', '자식 상위계정 코드는 필수입니다.');
            return;
        }

        const subAccountRows = collectModalSubAccountRows();
        if (subAccountRows === null) {
            return;
        }
        fd.set('sub_accounts', JSON.stringify(subAccountRows));

        try {
            const res = await $.ajax({
                url: API.SAVE,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false
            });

            if (!res?.success) {
                notify('error', res?.message || '저장에 실패했습니다.');
                return;
            }

            notify('success', '저장되었습니다.');
            modalDraftSubAccounts = [];
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
                option.textContent = `${account.account_code || ''} ${account.account_name || ''}`.trim();
                select.appendChild(option);
            });

        const create = document.createElement('option');
        create.value = NEW_PARENT_ACCOUNT_VALUE;
        create.textContent = '+ 자식 상위계정 생성';
        select.appendChild(create);

        select.value = Array.from(select.options).some((option) => option.value === currentValue)
            ? currentValue
            : '';
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

    function isParentAccountInputMode() {
        const inputWrap = document.getElementById('modal_parent_account_input_wrap');
        return Boolean(inputWrap && !inputWrap.classList.contains('d-none'));
    }

    function renderSubPolicyRows() {
        const tbody = document.getElementById('sub-policy-tbody');
        if (!tbody) return;

        if (!currentSubPolicies.length) {
            tbody.innerHTML = '<tr class="sub-policy-empty"><td colspan="5" class="text-center text-muted">등록된 보조계정 항목이 없습니다.</td></tr>';
            $('#modal_sub_policies').val('[]');
            return;
        }

        tbody.innerHTML = currentSubPolicies.map((policy, index) => `
            <tr data-index="${index}">
                <td>
                    <select class="form-select form-select-sm policy-type">
                        <option value="partner" ${policy.policy_type === 'partner' ? 'selected' : ''}>거래처</option>
                        <option value="project" ${policy.policy_type === 'project' ? 'selected' : ''}>프로젝트</option>
                        <option value="custom" ${policy.policy_type === 'custom' ? 'selected' : ''}>사용자정의</option>
                    </select>
                </td>
                <td class="text-center"><input type="checkbox" class="form-check-input policy-required" ${Number(policy.is_required) === 1 ? 'checked' : ''}></td>
                <td class="text-center"><input type="checkbox" class="form-check-input policy-multiple" ${Number(policy.allow_multiple) === 1 ? 'checked' : ''}></td>
                <td><input type="text" class="form-control form-control-sm policy-custom-code" value="${escapeHtml(policy.custom_group_code || '')}"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-policy" data-index="${index}">삭제</button>
                </td>
            </tr>
        `).join('');

        syncSubPolicyRows();
    }

    function syncSubPolicyRows() {
        currentSubPolicies = Array.from(document.querySelectorAll('#sub-policy-tbody tr[data-index]')).map((row) => ({
            policy_type: row.querySelector('.policy-type')?.value || 'custom',
            is_required: row.querySelector('.policy-required')?.checked ? 1 : 0,
            allow_multiple: row.querySelector('.policy-multiple')?.checked ? 1 : 0,
            custom_group_code: row.querySelector('.policy-custom-code')?.value?.trim() || ''
        }));

        $('#modal_sub_policies').val(JSON.stringify(currentSubPolicies));
        updateAllowSubAccountDisplay();
    }

    function updateAllowSubAccountDisplay() {
        const enabled = currentSubPolicies.length > 0 ? 1 : 0;
        $('#modal_allow_sub_account').val(String(enabled));
        $('#modal_allow_sub_account_label').val(enabled ? '사용' : '미사용');
    }

    async function loadSubAccounts(accountId, target = 'panel') {

        const tableSelector = target === 'modal'
            ? '#modal-subaccount-table'
            : '#subaccount-table';

        const tbody = document.querySelector(`${tableSelector} tbody`);
        if (!tbody) return;

        if (!accountId) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">계정과목을 선택하세요</td></tr>';
            return;
        }

        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">불러오는 중...</td></tr>';

        try {
            const json = await fetchJson(`${API.SUB_LIST}?account_id=${encodeURIComponent(accountId)}`);
            const rows = Array.isArray(json?.data) ? json.data : [];

            if (target === 'modal') {
                modalDraftSubAccounts = rows.map((row) => ({
                    id: row.id || '',
                    sub_code: row.sub_code || '',
                    sub_name: row.sub_name || '',
                    is_required: Number(row.is_required ?? 0),
                    note: row.note || '',
                    memo: row.memo || ''
                }));
                ensureModalDraftInput();
                renderModalDraftSubAccounts();
                return;
            }

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">등록된 보조계정이 없습니다.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map((row, index) => `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td data-sub-code="${escapeHtml(row.sub_code || '')}">${escapeHtml(resolveSubAccountName(row))}</td>
                    <td class="text-center">${Number(row.is_required ?? 0) === 1 ? '필수' : '선택'}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm btnEditSubAccount"
                            data-id="${escapeHtml(row.id || '')}"
                            data-code="${escapeHtml(row.sub_code || '')}"
                            data-name="${escapeHtml(row.sub_name || '')}"
                            data-required="${Number(row.is_required ?? 0) === 1 ? '1' : '0'}"
                            data-note="${escapeHtml(row.note || '')}"
                            data-memo="${escapeHtml(row.memo || '')}">수정</button>
                        <button type="button" class="btn btn-outline-danger btn-sm btnDeleteSubAccount" data-id="${escapeHtml(row.id || '')}">삭제</button>
                    </td>
                </tr>
            `).join('');
        } catch (err) {
            console.error('[ledger-account] sub list failed:', err);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">보조계정을 불러오지 못했습니다.</td></tr>';
        }
    }


    function addSubAccount(target = 'panel') {
        const targetAccountId = target === 'modal'
            ? ($('#modal_account_id').val() || '')
            : currentAccountId;

        if (target === 'modal') {
            syncModalDraftSubAccounts();
            modalDraftSubAccounts.push({ sub_code: '', sub_name: '', is_required: 0, note: '', memo: '' });
            renderModalDraftSubAccounts();
            const selects = document.querySelectorAll('#modal-subaccount-table .modal-sub-code-select');
            selects[selects.length - 1]?.focus();
            return;
        }

        if (!targetAccountId) {
            notify('warning', '계정과목을 먼저 선택하세요');
            return;
        }

        const tbody = document.querySelector('#subaccount-table tbody');
        if (!tbody) return;

        const existingRow = tbody.querySelector('.sub-new-row');
        if (existingRow) {
            existingRow.querySelector('.sub-new-code-select')?.focus();
            return;
        }

        if (tbody.querySelector('td[colspan]')) {
            tbody.innerHTML = '';
        }

        const tr = document.createElement('tr');
        tr.className = 'sub-new-row';
        tr.innerHTML = `
        <td class="text-center">추가</td>
        <td>${renderSubAccountCodeSelect('sub-new-code-select')}</td>
        <td>${renderRequiredSelect('sub-new-required-select', 0)}</td>
        <td class="text-center">
            <button type="button" class="btn btn-success btn-sm btnSubNewSave">저장</button>
            <button type="button" class="btn btn-secondary btn-sm btnSubNewCancel">취소</button>
        </td>
        `;

        tbody.prepend(tr);
        initSubAccountCodeSelects(tr).then(() => tr.querySelector('.sub-new-code-select')?.focus());

        const saveNew = async () => {
            const select = tr.querySelector('.sub-new-code-select');
            const subCode = select?.value?.trim() || '';
            const isRequired = Number(tr.querySelector('.sub-new-required-select')?.value || 0);
            const subName = getSubAccountCodeName(subCode);

            if (!subCode) {
                notify('warning', '보조계정명을 선택하세요');
                select?.focus();
                return;
            }

            if (hasDuplicateSubCode(subCode, { tableSelector: '#subaccount-table' })) {
                notify('warning', '이미 추가된 보조계정입니다');
                select?.focus();
                return;
            }

            tr.querySelectorAll('select,button').forEach((el) => { el.disabled = true; });

            try {
                const res = await $.post(API.SUB_SAVE, {
                    account_id: targetAccountId,
                    sub_code: subCode,
                    sub_name: subName,
                    is_required: isRequired
                });

                if (!res?.success) {
                    notify('error', res?.message || '보조계정 저장에 실패했습니다.');
                    tr.querySelectorAll('select,button').forEach((el) => { el.disabled = false; });
                    select?.focus();
                    return;
                }

                notify('success', '보조계정이 저장되었습니다.');
                loadSubAccounts(targetAccountId, target);
                accountTable?.ajax.reload(null, false);
            } catch (err) {
                console.error('[ledger-account] sub save failed:', err);
                notify('error', '보조계정 저장 중 오류가 발생했습니다.');
                tr.querySelectorAll('select,button').forEach((el) => { el.disabled = false; });
                select?.focus();
            }
        };

        tr.querySelector('.btnSubNewSave')?.addEventListener('click', saveNew);
        tr.querySelector('.btnSubNewCancel')?.addEventListener('click', () => {
            tr.remove();
            restoreEmptySubAccountRow(target);
        });
    }

    function restoreEmptySubAccountRow(target = 'panel') {
        const tableSelector = target === 'modal'
            ? '#modal-subaccount-table'
            : '#subaccount-table';
        const tbody = document.querySelector(`${tableSelector} tbody`);
        if (!tbody || tbody.children.length > 0) return;

        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">등록된 보조계정이 없습니다.</td></tr>';
    }





    function renderModalDraftSubAccounts() {
        const tbody = document.querySelector('#modal-subaccount-table tbody');
        if (!tbody) return;

        ensureModalDraftInput();

        tbody.innerHTML = modalDraftSubAccounts.map((row, index) => `
            <tr>
                <td class="text-center">${index + 1}</td>
                <td>
                    ${renderSubAccountCodeSelect('modal-sub-code-select', row.sub_code || '', 'modal-sub-code-select', index)}
                </td>
                <td>
                    ${renderRequiredSelect('modal-sub-required-select', row.is_required || 0)}
                </td>
                <td class="text-center">
                    <button type="button"
                            class="btn btn-outline-danger btn-sm btnDeleteSubAccount"
                            data-draft-index="${index}">
                        삭제
                    </button>
                </td>
            </tr>
        `).join('');

        initSubAccountCodeSelects(tbody);
    }

    function ensureModalDraftInput() {
        if (!modalDraftSubAccounts.length) {
            modalDraftSubAccounts.push({ sub_code: '', sub_name: '', is_required: 0, note: '', memo: '' });
        }
    }

    function syncModalDraftSubAccounts() {
        const rows = Array.from(document.querySelectorAll('#modal-subaccount-table tbody tr'));
        if (!rows.length) return;

        modalDraftSubAccounts = rows.map((row, index) => {
            const subCode = row.querySelector('.modal-sub-code-select')?.value?.trim() || '';
            return {
                id: modalDraftSubAccounts[index]?.id || '',
                sub_code: subCode,
                sub_name: getSubAccountCodeName(subCode),
                is_required: Number(row.querySelector('.modal-sub-required-select')?.value || 0),
                note: modalDraftSubAccounts[index]?.note || '',
                memo: modalDraftSubAccounts[index]?.memo || ''
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
                is_required: Number(row.is_required || 0),
                note: row.note || '',
                memo: row.memo || ''
            }))
            .filter((row) => row.sub_code !== '');

        if (!rows.length) {
            notify('warning', '보조계정 사용 시 보조계정명을 선택하세요.');
            return null;
        }

        const seen = new Set();
        for (const row of rows) {
            if (seen.has(row.sub_code)) {
                notify('warning', '보조계정은 중복 추가할 수 없습니다.');
                return null;
            }
            seen.add(row.sub_code);
        }

        return rows;
    }


    function editSubAccountInline(button) {
        const tr = button?.closest('tr');
        const id = button?.dataset?.id || '';
        const code = button?.dataset?.code || '';
        const note = button?.dataset?.note || '';
        const memo = button?.dataset?.memo || '';
        const required = Number(button?.dataset?.required || 0);
        if (!id || !tr) return;

        const tbody = tr.closest('tbody');
        const existingRow = tbody?.querySelector('.sub-edit-row');
        if (existingRow && existingRow !== tr) {
            existingRow.querySelector('.sub-edit-code-select')?.focus();
            return;
        }

        const originalHtml = tr.innerHTML;
        tr.classList.add('sub-edit-row');
        tr.innerHTML = `
            <td class="text-center">수정</td>
            <td>${renderSubAccountCodeSelect('sub-edit-code-select', code)}</td>
            <td>${renderRequiredSelect('sub-edit-required-select', required)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-success btn-sm btnSubEditSave">저장</button>
                <button type="button" class="btn btn-secondary btn-sm btnSubEditCancel">취소</button>
            </td>
        `;

        initSubAccountCodeSelects(tr).then(() => tr.querySelector('.sub-edit-code-select')?.focus());

        const cancelEdit = () => {
            tr.classList.remove('sub-edit-row');
            tr.innerHTML = originalHtml;
        };

        const saveEdit = async () => {
            const select = tr.querySelector('.sub-edit-code-select');
            const subCode = select?.value?.trim() || '';
            const isRequired = Number(tr.querySelector('.sub-edit-required-select')?.value || 0);

            if (!subCode) {
                notify('warning', '보조계정명을 선택하세요');
                select?.focus();
                return;
            }

            if (hasDuplicateSubCode(subCode, { tableSelector: '#subaccount-table', excludeId: id })) {
                notify('warning', '이미 추가된 보조계정입니다');
                select?.focus();
                return;
            }

            tr.querySelectorAll('select,button').forEach((el) => { el.disabled = true; });

            try {
                const res = await $.post(API.SUB_UPDATE, {
                    id,
                    sub_code: subCode,
                    sub_name: getSubAccountCodeName(subCode),
                    is_required: isRequired,
                    note: note || '',
                    memo: memo || ''
                });

                if (!res?.success) {
                    notify('error', res?.message || '보조계정 수정에 실패했습니다.');
                    tr.querySelectorAll('select,button').forEach((el) => { el.disabled = false; });
                    select?.focus();
                    return;
                }

                notify('success', '보조계정이 수정되었습니다.');
                loadSubAccounts(currentAccountId, 'panel');
                accountTable?.ajax.reload(null, false);
            } catch (err) {
                console.error('[ledger-account] sub update failed:', err);
                notify('error', '보조계정 수정 중 오류가 발생했습니다.');
                tr.querySelectorAll('select,button').forEach((el) => { el.disabled = false; });
                select?.focus();
            }
        };

        tr.querySelector('.btnSubEditSave')?.addEventListener('click', saveEdit);
        tr.querySelector('.btnSubEditCancel')?.addEventListener('click', cancelEdit);
    }

    async function deleteSubAccount(id, target = 'panel') {
        const targetAccountId = target === 'modal'
            ? ($('#modal_account_id').val() || currentAccountId)
            : currentAccountId;
        if (!id) return;
        if (!confirm('보조계정을 삭제하시겠습니까?')) return;

        try {
            const res = await $.post(API.SUB_DELETE, { id });
            if (!res?.success) {
                notify('error', res?.message || '보조계정 삭제에 실패했습니다.');
                return;
            }

            notify('success', '보조계정이 삭제되었습니다.');
            loadSubAccounts(targetAccountId, target);
            accountTable?.ajax.reload(null, false);
        } catch (err) {
            console.error('[ledger-account] sub delete failed:', err);
            notify('error', '보조계정 삭제 중 오류가 발생했습니다.');
        }
    }

    function clearSubAccountPanel() {
        currentAccountId = '';
        $('#btnAddSubAccount').prop('disabled', true);
        $('#subaccountGuide').text('계정과목을 선택하면 해당 보조계정을 관리할 수 있습니다.');
        clearTableSelectedRows('#account-table');
        document.querySelector('.account-split-layout')?.classList.remove('subaccount-open');
        const tbody = document.querySelector('#subaccount-table tbody');
        if (tbody) {
            tbody.innerHTML = '';
        }
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
                <option value="">선택하세요</option>
                <option value="${escapeHtml(selectedValue || '')}" selected>${escapeHtml(getSubAccountCodeName(selectedValue, selectedValue))}</option>
            </select>
        `;
    }

    function renderRequiredSelect(className, selectedValue = 0) {
        const value = Number(selectedValue || 0);
        return `
            <select class="form-select form-select-sm ${className}">
                <option value="1" ${value === 1 ? 'selected' : ''}>필수</option>
                <option value="0" ${value !== 1 ? 'selected' : ''}>선택</option>
            </select>
        `;
    }

    async function initSubAccountCodeSelects(root = document) {
        const selects = Array.from(root.querySelectorAll(`select[data-code-group="${SUB_ACCOUNT_CODE_GROUP}"]`));
        await initCodeSelectControls(root);
        selects.forEach((select) => {
            const selected = select.dataset.selected || select.value || '';
            if (selected) {
                select.value = selected;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function getSubAccountCodeName(code, fallback = '') {
        const value = String(code || '').trim();
        if (!value) return '';
        return getCodeName(SUB_ACCOUNT_SELECT_NAME, value) || fallback || value;
    }

    function resolveSubAccountName(row = {}) {
        return getSubAccountCodeName(row.sub_code, row.sub_name || row.sub_code || '');
    }

    function hasDuplicateSubCode(subCode, { tableSelector, excludeId = '' } = {}) {
        const value = String(subCode || '').trim();
        if (!value || !tableSelector) return false;

        return Array.from(document.querySelectorAll(`${tableSelector} .btnEditSubAccount`))
            .some((button) => {
                if (excludeId && String(button.dataset.id || '') === String(excludeId)) {
                    return false;
                }
                return String(button.dataset.code || '').trim() === value;
            });
    }

    function refreshSubAccountLabels() {
        document.querySelectorAll('#subaccount-table td[data-sub-code]').forEach((cell) => {
            const code = cell.dataset.subCode || '';
            if (code) {
                cell.textContent = getSubAccountCodeName(code, cell.textContent);
            }
        });
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, {
            credentials: 'include',
            ...options
        });
        return res.json();
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
