// Path: PROJECT_ROOT . '/public/assets/js/pages/ledger/account.js'

import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
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
        id: { label: 'ID', visible: false }
    };

    const DATE_OPTIONS = [
        { value: 'created_at', label: '생성일자' },
        { value: 'updated_at', label: '수정일자' }
    ];

    let accountTable = null;
    let accountModal = null;
    let excelModal = null;
    let currentAccountId = '';
    let currentSubPolicies = [];

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
                    text: '엑셀관리',
                    className: 'btn btn-success btn-sm',
                    action: () => excelModal?.show()
                },
                {
                    text: '휴지통',
                    className: 'btn btn-danger btn-sm',
                    action: openTrashModal
                },
                {
                    text: '새 계정과목',
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

        bindTableHighlight('#account-table', accountTable);

        bindRowReorder(accountTable, {
            api: API.REORDER,
            onSuccess() {
                notify('success', '계정과목 순번이 저장되었습니다.');
                accountTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '계정과목 순번 저장에 실패했습니다.');
                accountTable?.ajax.reload(null, false);
            }
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

        $('#btnSelectParent')
            .off('click.accountParent')
            .on('click.accountParent', selectParentByCode);

        $('#btnClearParent')
            .off('click.accountParentClear')
            .on('click.accountParentClear', () => {
                $('#modal_parent_id').val('');
                $('#modal_parent_name').val('');
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
            .on('click.subAdd', addSubAccount);

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
        $('#account-table tbody tr').removeClass('table-active');
        if (tr) {
            $(tr).addClass('table-active');
        }

        $('#btnAddSubAccount').prop('disabled', !currentAccountId);
        $('#subaccountGuide').text(`${row.account_code || ''} ${row.account_name || ''}`);

        if (openPanel) {
            document.querySelector('.account-split-layout')?.classList.add('subaccount-open');
        }

        loadSubAccounts(currentAccountId);
    }

    function openCreateModal() {
        resetAccountForm();
        $('#accountModalLabel').text('계정과목 등록');
        $('#btnDeleteAccount').hide();
        accountModal?.show();
    }

    async function openEditModal(row) {
        const id = row.id || '';
        if (!id) return;

        try {
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
        $('#modal_parent_id').val(data.parent_id || '');
        $('#modal_parent_name').val(data.parent_name || '');
        $('#modal_account_group').val(data.account_group || '');
        $('#modal_normal_balance').val(data.normal_balance || 'debit');
        $('#modal_is_posting').val(String(data.is_posting ?? 1));
        $('#modal_is_active').val(String(data.is_active ?? 1));
        $('#modal_note').val(data.note || '');
        $('#modal_memo').val(data.memo || '');

        currentSubPolicies = Array.isArray(data.sub_policies) ? data.sub_policies : [];
        renderSubPolicyRows();
        updateAllowSubAccountDisplay();
    }

    function resetAccountForm() {
        const form = document.getElementById('account-edit-form');
        form?.reset();

        $('#modal_account_id').val('');
        $('#modal_sort_no').val('');
        $('#modal_parent_id').val('');
        $('#modal_parent_name').val('');
        $('#modal_sub_policies').val('[]');
        $('#modal_allow_sub_account').val('0');
        $('#modal_allow_sub_account_label').val('미사용');
        $('#modal_normal_balance').val('debit');
        $('#modal_is_posting').val('1');
        $('#modal_is_active').val('1');
        $('#btnDeleteAccount').hide();

        currentSubPolicies = [];
        renderSubPolicyRows();
    }

    async function saveAccount(event) {
        event.preventDefault();
        syncSubPolicyRows();

        const form = event.currentTarget;
        const fd = new FormData(form);

        if (!String(fd.get('account_code') || '').trim() || !String(fd.get('account_name') || '').trim()) {
            notify('warning', '계정코드와 계정과목명은 필수입니다.');
            return;
        }

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

    async function selectParentByCode() {
        const code = prompt('상위계정 코드를 입력하세요.');
        if (!code) return;

        try {
            const json = await fetchJson(`${API.DETAIL}?code=${encodeURIComponent(code)}`);
            if (!json.success || !json.data?.id) {
                notify('warning', '상위계정을 찾을 수 없습니다.');
                return;
            }

            $('#modal_parent_id').val(json.data.id);
            $('#modal_parent_name').val(`${json.data.account_code} ${json.data.account_name}`);
        } catch (err) {
            console.error('[ledger-account] parent lookup failed:', err);
            notify('error', '상위계정 조회 중 오류가 발생했습니다.');
        }
    }

    function renderSubPolicyRows() {
        const tbody = document.getElementById('sub-policy-tbody');
        if (!tbody) return;

        if (!currentSubPolicies.length) {
            tbody.innerHTML = '<tr class="sub-policy-empty"><td colspan="5" class="text-center text-muted">등록된 보조계정 정책이 없습니다.</td></tr>';
            $('#modal_sub_policies').val('[]');
            return;
        }

        tbody.innerHTML = currentSubPolicies.map((policy, index) => `
            <tr data-index="${index}">
                <td>
                    <select class="form-select form-select-sm policy-type">
                        <option value="partner" ${policy.policy_type === 'partner' ? 'selected' : ''}>거래처</option>
                        <option value="project" ${policy.policy_type === 'project' ? 'selected' : ''}>프로젝트</option>
                        <option value="custom" ${policy.policy_type === 'custom' ? 'selected' : ''}>Custom</option>
                    </select>
                </td>
                <td class="text-center"><input type="checkbox" class="form-check-input policy-required" ${Number(policy.is_required) === 1 ? 'checked' : ''}></td>
                <td class="text-center"><input type="checkbox" class="form-check-input policy-multiple" ${Number(policy.allow_multiple) === 1 ? 'checked' : ''}></td>
                <td><input type="text" class="form-control form-control-sm policy-custom-code" value="${escapeHtml(policy.custom_group_code || '')}"></td>
                <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm btn-remove-policy" data-index="${index}">삭제</button></td>
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

    async function loadSubAccounts(accountId) {
        const tbody = document.querySelector('#subaccount-table tbody');
        if (!tbody) return;

        if (!accountId) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">계정과목을 선택하세요.</td></tr>';
            return;
        }

        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">불러오는 중...</td></tr>';

        try {
            const json = await fetchJson(`${API.SUB_LIST}?account_id=${encodeURIComponent(accountId)}`);
            const rows = Array.isArray(json?.data) ? json.data : [];

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">등록된 보조계정이 없습니다.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map((row, index) => `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td>${escapeHtml(row.sub_name || '')}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm btnEditSubAccount"
                            data-id="${escapeHtml(row.id || '')}"
                            data-name="${escapeHtml(row.sub_name || '')}"
                            data-note="${escapeHtml(row.note || '')}"
                            data-memo="${escapeHtml(row.memo || '')}">수정</button>
                        <button type="button" class="btn btn-outline-danger btn-sm btnDeleteSubAccount" data-id="${escapeHtml(row.id || '')}">삭제</button>
                    </td>
                </tr>
            `).join('');
        } catch (err) {
            console.error('[ledger-account] sub list failed:', err);
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">보조계정을 불러오지 못했습니다.</td></tr>';
        }
    }

    function addSubAccount() {
        if (!currentAccountId) {
            notify('warning', '계정과목을 먼저 선택하세요.');
            return;
        }

        const tbody = document.querySelector('#subaccount-table tbody');
        if (!tbody) return;

        const existingRow = tbody.querySelector('.sub-new-row');
        if (existingRow) {
            existingRow.querySelector('.sub-new-input')?.focus();
            return;
        }

        const emptyRow = tbody.querySelector('td[colspan]');
        if (emptyRow) {
            tbody.innerHTML = '';
        }

        const tr = document.createElement('tr');
        tr.className = 'sub-new-row';
        tr.innerHTML = `
            <td class="text-center">신규</td>
            <td>
                <input type="text"
                       class="form-control form-control-sm sub-new-input"
                       placeholder="보조계정 입력 후 Enter">
            </td>
            <td class="text-center">
                <span class="text-muted small">Enter 저장 / Esc 취소</span>
            </td>
        `;

        tbody.prepend(tr);

        const input = tr.querySelector('.sub-new-input');
        input?.focus();

        input?.addEventListener('keydown', async (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                tr.remove();
                restoreEmptySubAccountRow();
                return;
            }

            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();

            const subName = input.value.trim();
            if (!subName) {
                notify('warning', '보조계정명을 입력하세요.');
                input.focus();
                return;
            }

            input.disabled = true;

            try {
                const res = await $.post(API.SUB_SAVE, {
                    account_id: currentAccountId,
                    sub_name: subName
                });

                if (!res?.success) {
                    notify('error', res?.message || '보조계정 저장에 실패했습니다.');
                    input.disabled = false;
                    input.focus();
                    return;
                }

                notify('success', '보조계정이 저장되었습니다.');
                loadSubAccounts(currentAccountId);
                accountTable?.ajax.reload(null, false);
            } catch (err) {
                console.error('[ledger-account] sub save failed:', err);
                notify('error', '보조계정 저장 중 오류가 발생했습니다.');
                input.disabled = false;
                input.focus();
            }
        });
    }

    function restoreEmptySubAccountRow() {
        const tbody = document.querySelector('#subaccount-table tbody');
        if (!tbody || tbody.children.length > 0) return;

        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">등록된 보조계정이 없습니다.</td></tr>';
    }

    function editSubAccountInline(button) {
        const tr = button?.closest('tr');
        const id = button?.dataset?.id || '';
        const name = button?.dataset?.name || '';
        const note = button?.dataset?.note || '';
        const memo = button?.dataset?.memo || '';
        if (!id || !tr) return;

        const tbody = tr.closest('tbody');
        const existingRow = tbody?.querySelector('.sub-edit-row');
        if (existingRow && existingRow !== tr) {
            existingRow.querySelector('.sub-edit-input')?.focus();
            return;
        }

        const originalHtml = tr.innerHTML;
        tr.classList.add('sub-edit-row');
        tr.innerHTML = `
            <td class="text-center">수정</td>
            <td>
                <input type="text"
                       class="form-control form-control-sm sub-edit-input"
                       value="${escapeHtml(name)}"
                       placeholder="보조계정 입력 후 Enter">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-success btn-sm btnSubEditSave">저장</button>
                <button type="button" class="btn btn-secondary btn-sm btnSubEditCancel">취소</button>
            </td>
        `;

        const input = tr.querySelector('.sub-edit-input');
        const saveButton = tr.querySelector('.btnSubEditSave');
        const cancelButton = tr.querySelector('.btnSubEditCancel');

        const cancelEdit = () => {
            tr.classList.remove('sub-edit-row');
            tr.innerHTML = originalHtml;
        };

        const saveEdit = async () => {
            const subName = input?.value.trim() || '';
            if (!subName) {
                notify('warning', '보조계정명을 입력하세요.');
                input?.focus();
                return;
            }

            input.disabled = true;
            if (saveButton) saveButton.disabled = true;
            if (cancelButton) cancelButton.disabled = true;

            try {
                const res = await $.post(API.SUB_UPDATE, {
                    id,
                    sub_name: subName,
                    note: note || '',
                    memo: memo || ''
                });

                if (!res?.success) {
                    notify('error', res?.message || '보조계정 수정에 실패했습니다.');
                    input.disabled = false;
                    if (saveButton) saveButton.disabled = false;
                    if (cancelButton) cancelButton.disabled = false;
                    input.focus();
                    return;
                }

                notify('success', '보조계정이 수정되었습니다.');
                loadSubAccounts(currentAccountId);
                accountTable?.ajax.reload(null, false);
            } catch (err) {
                console.error('[ledger-account] sub update failed:', err);
                notify('error', '보조계정 수정 중 오류가 발생했습니다.');
                input.disabled = false;
                if (saveButton) saveButton.disabled = false;
                if (cancelButton) cancelButton.disabled = false;
                input.focus();
            }
        };

        input?.focus();
        input?.select();

        input?.addEventListener('keydown', async (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                cancelEdit();
                return;
            }

            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            await saveEdit();
        });

        saveButton?.addEventListener('click', saveEdit);
        cancelButton?.addEventListener('click', cancelEdit);
    }

    async function editSubAccount(id, name, note = '', memo = '') {
        if (!id) return;

        const subName = prompt('보조계정명을 입력하세요.', name || '');
        if (!subName) return;

        try {
            const res = await $.post(API.SUB_UPDATE, {
                id,
                sub_name: subName,
                note: note || '',
                memo: memo || ''
            });

            if (!res?.success) {
                notify('error', res?.message || '보조계정 수정에 실패했습니다.');
                return;
            }

            notify('success', '보조계정이 수정되었습니다.');
            loadSubAccounts(currentAccountId);
        } catch (err) {
            console.error('[ledger-account] sub update failed:', err);
            notify('error', '보조계정 수정 중 오류가 발생했습니다.');
        }
    }

    async function deleteSubAccount(id) {
        if (!id) return;
        if (!confirm('보조계정을 삭제하시겠습니까?')) return;

        try {
            const res = await $.post(API.SUB_DELETE, { id });
            if (!res?.success) {
                notify('error', res?.message || '보조계정 삭제에 실패했습니다.');
                return;
            }

            notify('success', '보조계정이 삭제되었습니다.');
            loadSubAccounts(currentAccountId);
            accountTable?.ajax.reload(null, false);
        } catch (err) {
            console.error('[ledger-account] sub delete failed:', err);
            notify('error', '보조계정 삭제 중 오류가 발생했습니다.');
        }
    }

    function clearSubAccountPanel() {
        currentAccountId = '';
        $('#btnAddSubAccount').prop('disabled', true);
        $('#subaccountGuide').text('계정과목을 선택하면 연결된 보조계정을 관리할 수 있습니다.');
        $('#account-table tbody tr').removeClass('table-active');
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
