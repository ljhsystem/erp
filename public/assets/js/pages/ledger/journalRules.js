import { createDataTable } from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import {
    initCodeSelectControls,
    onCodeOptionsLoaded,
} from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { createJournalBasicInfoBridge } from '/public/assets/js/pages/ledger/journal.basic-info.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

(() => {
    'use strict';

    const API = {
        list: '/api/ledger/journal-rules/list',
        detail: '/api/ledger/journal-rules/detail',
        save: '/api/ledger/journal-rules/save',
        delete: '/api/ledger/journal-rules/delete',
        status: '/api/ledger/journal-rules/status',
        reorder: '/api/ledger/journal-rules/reorder',
        businessUnits: '/api/settings/system/code/list?code_group=BUSINESS_UNIT&filters=[]',
        transactionTypes: '/api/settings/system/code/list?code_group=TRANSACTION_TYPE&filters=[]',
        importTypes: '/api/settings/system/code/list?code_group=IMPORT_TYPE&filters=[]',
        transactionDirections: '/api/settings/system/code/list?code_group=TRANSACTION_DIRECTION&filters=[]',
        clientTypes: '/api/settings/system/code/list?code_group=CLIENT_TYPE&filters=[]',
        accounts: '/api/ledger/account/list',
    };

    const QUICK_CREATE_ACCOUNT_VALUE = '__quick_create_account__';
    const CODE_GROUPS = {
        BUSINESS_UNIT: 'businessUnits',
        TRANSACTION_TYPE: 'transactionTypes',
        IMPORT_TYPE: 'importTypes',
        TRANSACTION_DIRECTION: 'transactionDirections',
        CLIENT_TYPE: 'clientTypes',
    };

    let table = null;
    let modal = null;
    let excelModal = null;
    let businessUnits = [];
    let transactionTypes = [];
    let importTypes = [];
    let transactionDirections = [];
    let clientTypes = [];
    let postingAccounts = [];
    let basicInfoBridge = null;
    let selectSourcesPromise = null;

    const $ = window.jQuery;

    document.addEventListener('DOMContentLoaded', boot);

    document.addEventListener('excel:uploaded', () => {
        table?.ajax.reload(null, false);
    });

    document.addEventListener('trash:changed', (event) => {
        if (event.detail?.type === 'journalRule') {
            table?.ajax.reload(null, false);
        }
    });

    document.addEventListener('trash:detail-render', (event) => {
        if (event.detail?.type !== 'journalRule') return;
        const row = event.detail.data || {};
        const detailEl = event.detail.modal?.querySelector('.trash-detail');
        if (!detailEl) return;

        detailEl.innerHTML = `
            <div class="small">
                <dl class="row mb-0">
                    <dt class="col-4">규칙코드</dt><dd class="col-8">${escapeHtml(row.rule_code || '-')}</dd>
                    <dt class="col-4">규칙명</dt><dd class="col-8">${escapeHtml(row.rule_name || '-')}</dd>
                    <dt class="col-4">사업구분</dt><dd class="col-8">${escapeHtml(codeLabel(businessUnits, row.business_unit, row.business_unit_name))}</dd>
                    <dt class="col-4">거래유형</dt><dd class="col-8">${escapeHtml(codeLabel(transactionTypes, row.transaction_type, row.transaction_type_name))}</dd>
                    <dt class="col-4">거래구분</dt><dd class="col-8">${escapeHtml(codeLabel(transactionDirections, row.transaction_direction, row.transaction_direction_name))}</dd>
                    <dt class="col-4">거래처구분</dt><dd class="col-8">${escapeHtml(codeLabel(clientTypes, row.client_type, row.client_type_name))}</dd>
                    <dt class="col-4">자료유형</dt><dd class="col-8">${escapeHtml(codeLabel(importTypes, row.import_type, row.import_type_name))}</dd>
                    <dt class="col-4">차변계정</dt><dd class="col-8">${escapeHtml(accountText(row, 'debit'))}</dd>
                    <dt class="col-4">대변계정</dt><dd class="col-8">${escapeHtml(accountText(row, 'credit'))}</dd>
                    <dt class="col-4">부가세계정</dt><dd class="col-8">${escapeHtml(accountText(row, 'vat'))}</dd>
                    <dt class="col-4">삭제일시</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
                </dl>
            </div>
        `;
    });

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.journalRule = function (row = {}) {
        return `
            <td>${escapeHtml(row.rule_code || '')}</td>
            <td>${escapeHtml(row.rule_name || '')}</td>
            <td>${escapeHtml(codeLabel(businessUnits, row.business_unit, row.business_unit_name))}</td>
            <td>${escapeHtml(codeLabel(transactionTypes, row.transaction_type, row.transaction_type_name))}</td>
            <td>${escapeHtml(codeLabel(transactionDirections, row.transaction_direction, row.transaction_direction_name))}</td>
            <td>${escapeHtml(codeLabel(clientTypes, row.client_type, row.client_type_name))}</td>
            <td>${escapeHtml(codeLabel(importTypes, row.import_type, row.import_type_name))}</td>
            <td>${escapeHtml(row.deleted_at || '')}</td>
            <td class="text-center">
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id || '')}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id || '')}">영구삭제</button>
            </td>
        `;
    };

    async function boot() {
        const modalEl = document.getElementById('journalRuleModal');
        modal = bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false });

        const excelEl = document.getElementById('journalRuleExcelModal');
        if (excelEl) {
            excelModal = bootstrap.Modal.getOrCreateInstance(excelEl, { focus: false });
        }

        basicInfoBridge = createJournalBasicInfoBridge({ notify });
        initTable();
        bindEvents();
        bindCodeOptionRefresh();
        selectSourcesPromise = prepareSelectSources(modalEl);
    }

    async function prepareSelectSources(modalEl) {
        try {
            await Promise.all([
                loadSelectSources(),
                initCodeSelectControls(modalEl),
            ]);
            initAccountSelect2();
            table?.rows().invalidate('data').draw(false);
        } catch (error) {
            console.error('[journal-rules] select source load failed:', error);
            notify('error', error.message || '분개규칙 기준정보를 불러오지 못했습니다.');
        }
    }

    async function ensureSelectSourcesReady() {
        if (selectSourcesPromise) {
            await selectSourcesPromise;
        }
    }

    async function loadSelectSources() {
        const [businessJson, typeJson, directionJson, clientJson, importJson, accountJson] = await Promise.all([
            fetchJson(API.businessUnits),
            fetchJson(API.transactionTypes),
            fetchJson(API.transactionDirections),
            fetchJson(API.clientTypes),
            fetchJson(API.importTypes),
            fetchJson(API.accounts),
        ]);

        businessUnits = activeCodes(businessJson.data || []);
        transactionTypes = activeCodes(typeJson.data || []);
        transactionDirections = activeCodes(directionJson.data || []);
        clientTypes = activeCodes(clientJson.data || []);
        importTypes = activeCodes(importJson.data || []);

        if (!transactionDirections.length) {
            transactionDirections = [
                { code: 'PURCHASE', code_name: '매입' },
                { code: 'SALES', code_name: '매출' },
                { code: 'IN', code_name: '입금' },
                { code: 'OUT', code_name: '출금' },
            ];
        }

        postingAccounts = (accountJson.data || []).filter((row) => (
            Number(row.is_active ?? 1) === 1
            && Number(row.is_posting ?? 1) === 1
        ));
    }

    function bindCodeOptionRefresh() {
        onCodeOptionsLoaded((options = {}) => {
            Object.entries(CODE_GROUPS).forEach(([group, stateName]) => {
                const rows = options[group];
                if (!Array.isArray(rows)) return;
                if (stateName === 'businessUnits') businessUnits = rows;
                if (stateName === 'transactionTypes') transactionTypes = rows;
                if (stateName === 'importTypes') importTypes = rows;
                if (stateName === 'transactionDirections') transactionDirections = rows;
                if (stateName === 'clientTypes') clientTypes = rows;
            });
            table?.rows().invalidate('data').draw(false);
        });
    }

    function activeCodes(rows) {
        return rows.filter((row) => Number(row.is_active ?? 1) === 1);
    }

    function initAccountSelect2() {
        const accountItems = [
            { id: '', text: '선택' },
            ...postingAccounts.map((row) => ({
                id: row.id,
                text: `[${row.full_path || row.account_name || ''}]`,
            })),
            { id: QUICK_CREATE_ACCOUNT_VALUE, text: '+ 계정과목추가' },
        ];

        document.querySelectorAll('#journalRuleModal .js-account-select').forEach((select) => {
            populateOptions(select, accountItems);
        });

        if (!$.fn.select2) return;

        $('#journalRuleModal .js-account-select').each(function () {
            const $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2({
                dropdownParent: $('#journalRuleModal'),
                width: '100%',
                allowClear: !this.required,
                placeholder: '선택',
                language: 'ko',
                templateResult: renderAccountOption,
                templateSelection: renderAccountSelection,
            });
        });

        bindAccountQuickCreate();
    }

    function renderAccountOption(data) {
        const span = document.createElement('span');
        span.textContent = data?.text || '';
        if (data?.id === QUICK_CREATE_ACCOUNT_VALUE) {
            span.className = 'journal-rule-quick-create-option';
        }
        return span;
    }

    function renderAccountSelection(data) {
        if (!data || !data.id || data.id === QUICK_CREATE_ACCOUNT_VALUE) {
            return '선택';
        }
        return data.text || data.id;
    }

    function bindAccountQuickCreate() {
        if (!$.fn.select2) return;

        $('#journalRuleModal .js-account-select')
            .off('select2:select.journalRuleAccountQuick')
            .on('select2:select.journalRuleAccountQuick', function (event) {
                const selectedId = String(event.params?.data?.id || '');
                if (selectedId !== QUICK_CREATE_ACCOUNT_VALUE) return;

                const selectEl = this;
                event.preventDefault();
                $(selectEl).val('').trigger('change.select2');

                window.setTimeout(() => {
                    basicInfoBridge?.openQuickCreate('account', {
                        sourceEl: selectEl,
                        async onSaved(payload) {
                            await reloadAccountSelects({
                                sourceEl: selectEl,
                                selectedAccountCode: payload.value,
                            });
                        },
                        async onDeleted() {
                            await reloadAccountSelects({ sourceEl: selectEl });
                        },
                    });
                }, 0);
            });
    }

    async function reloadAccountSelects({ sourceEl = null, selectedAccountCode = '' } = {}) {
        await refreshPostingAccounts();
        initAccountSelect2();

        if (!sourceEl || !selectedAccountCode) return;

        const created = postingAccounts.find((row) => (
            String(row.account_code || '').trim() === String(selectedAccountCode || '').trim()
        ));
        if (!created?.id) return;

        $(sourceEl).val(created.id).trigger('change');
    }

    async function refreshPostingAccounts() {
        const accountJson = await fetchJson(API.accounts);
        postingAccounts = (accountJson.data || []).filter((row) => (
            Number(row.is_active ?? 1) === 1
            && Number(row.is_posting ?? 1) === 1
        ));
    }

    function populateOptions(select, items) {
        if (!select) return;
        select.innerHTML = items
            .map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.text)}</option>`)
            .join('');
    }

    function initTable() {
        table = createDataTable({
            tableSelector: '#journal-rule-table',
            api: API.list,
            columns: columns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            searchTableId: 'journalRule',
            buttons: [
                { text: '엑셀관리', className: 'btn btn-success btn-sm', action: () => excelModal?.show() },
                { text: '휴지통', className: 'btn btn-danger btn-sm', action: openTrash },
                { text: '새 분개규칙', className: 'btn btn-warning btn-sm', action: () => { void openCreate(); } },
            ],
        });

        table.on('init.dt draw.dt', () => {
            const count = table.page.info()?.recordsDisplay ?? 0;
            const countEl = document.getElementById('journalRuleCount');
            if (countEl) countEl.textContent = `${Number(count).toLocaleString('ko-KR')}건`;
        });

        SearchForm({
            table,
            apiList: API.list,
            tableId: 'journalRule',
            defaultSearchField: 'rule_name',
            initialCollapsed: true,
            dateOptions: [
                { value: 'created_at', label: '생성일' },
                { value: 'updated_at', label: '수정일' },
            ],
        });

        bindRowReorder(table, {
            api: API.reorder,
            onSuccess() {
                notify('success', '분개규칙 순서가 변경되었습니다.');
                table?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '분개규칙 순서 변경에 실패했습니다.');
                table?.ajax.reload(null, false);
            }
        });
    }

    function columns() {
        return [
            {
                data: null,
                title: '<i class="bi bi-arrows-move"></i>',
                className: 'reorder-handle no-sort no-colvis text-center',
                headerClassName: 'no-colvis text-center',
                orderable: false,
                searchable: false,
                defaultContent: '<i class="bi bi-list"></i>'
            },
            { data: 'sort_no', title: '순번', className: 'text-center text-nowrap', width: 60 },
            { data: 'rule_code', title: '규칙코드', className: 'text-nowrap', render: textCell },
            { data: 'rule_name', title: '규칙명', render: textCell },
            { data: 'business_unit', title: '사업구분', className: 'text-nowrap', render: (_value, _type, row) => badge(codeLabel(businessUnits, row.business_unit, row.business_unit_name)) },
            { data: 'transaction_type', title: '거래유형', className: 'text-nowrap', render: (_value, _type, row) => badge(codeLabel(transactionTypes, row.transaction_type, row.transaction_type_name)) },
            { data: 'transaction_direction', title: '거래구분', className: 'text-nowrap text-center', render: (_value, _type, row) => badge(codeLabel(transactionDirections, row.transaction_direction, row.transaction_direction_name)) },
            { data: 'client_type', title: '거래처구분', className: 'text-nowrap', render: (_value, _type, row) => badge(codeLabel(clientTypes, row.client_type, row.client_type_name)) },
            { data: 'import_type', title: '자료유형', className: 'text-nowrap', render: (_value, _type, row) => badge(codeLabel(importTypes, row.import_type, row.import_type_name)) },
            { data: 'debit_account_name', title: '차변계정', render: (_value, _type, row) => escapeHtml(accountText(row, 'debit')) },
            { data: 'credit_account_name', title: '대변계정', render: (_value, _type, row) => escapeHtml(accountText(row, 'credit')) },
            { data: 'vat_account_name', title: '부가세계정', render: (_value, _type, row) => escapeHtml(accountText(row, 'vat')) },
            { data: 'description', title: '설명/적요', render: textCell },
            { data: 'is_active', title: '상태', className: 'text-center text-nowrap', orderable: false, render: renderStatusToggle },
            {
                data: null,
                title: '관리',
                className: 'text-center no-colvis',
                headerClassName: 'text-center no-colvis',
                orderable: false,
                searchable: false,
                defaultContent: '',
                render: (_value, type, row) => {
                    if (type !== 'display') return '';
                    return `
                        <button type="button"
                                class="btn btn-outline-primary btn-sm journal-rule-edit-btn"
                                data-id="${escapeHtml(row.id || '')}">
                            수정
                        </button>
                    `;
                },
            },
        ];
    }

    function bindEvents() {
        $('#journal-rule-table tbody').on('dblclick', 'tr', async function () {
            const row = table.row(this).data();
            if (!row?.id) return;
            await openEdit(row.id);
        });

        $('#journal-rule-table tbody')
            .off('click.journalRuleStatus')
            .on('click.journalRuleStatus', '.journal-rule-status-toggle', function (event) {
                event.stopPropagation();
            })
            .off('change.journalRuleStatus')
            .on('change.journalRuleStatus', '.journal-rule-status-toggle', updateRuleStatus);

        $('#journal-rule-table tbody')
            .off('click.journalRuleEditBtn')
            .on('click.journalRuleEditBtn', '.journal-rule-edit-btn', async function (event) {
                event.stopPropagation();
                const id = this.dataset.id || '';
                if (!id) return;
                await openEdit(id);
            });

        document.getElementById('journalRuleForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const form = event.currentTarget;
                const formData = new FormData(form);
                if (!formData.has('is_active')) formData.set('is_active', '0');

                const json = await fetchJson(API.save, { method: 'POST', body: formData });
                if (!json.success) throw new Error(json.message || '저장에 실패했습니다.');
                notify('success', json.message || '저장되었습니다.');
                modal.hide();
                table.ajax.reload(null, false);
            } catch (error) {
                notify('error', error.message || '저장에 실패했습니다.');
            }
        });

        document.getElementById('journalRuleDeleteBtn')?.addEventListener('click', async () => {
            try {
                const id = document.getElementById('journalRuleId')?.value || '';
                if (!id) {
                    modal.hide();
                    return;
                }
                if (!confirm('분개규칙을 휴지통으로 이동할까요?')) return;

                const body = new URLSearchParams({ id });
                const json = await fetchJson(API.delete, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                });
                if (!json.success) throw new Error(json.message || '삭제에 실패했습니다.');
                notify('success', '분개규칙을 휴지통으로 이동했습니다.');
                modal.hide();
                table.ajax.reload(null, false);
            } catch (error) {
                notify('error', error.message || '삭제에 실패했습니다.');
            }
        });
    }

    async function openCreate() {
        await ensureSelectSourcesReady();

        const form = document.getElementById('journalRuleForm');
        form.reset();
        form.querySelector('[name="id"]').value = '';
        form.querySelector('[name="is_active"]').checked = true;
        $('#journalRuleModal select').val('').trigger('change');
        document.querySelector('#journalRuleModal .modal-title').textContent = '분개규칙 등록';
        document.getElementById('journalRuleDeleteBtn').classList.add('d-none');
        modal.show();
    }

    async function openEdit(id) {
        await ensureSelectSourcesReady();

        const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);
        if (!json.success) throw new Error(json.message || '분개규칙을 찾을 수 없습니다.');
        bindForm(json.data || {});
        document.querySelector('#journalRuleModal .modal-title').textContent = '분개규칙 수정';
        document.getElementById('journalRuleDeleteBtn').classList.remove('d-none');
        modal.show();
    }

    function bindForm(row) {
        const form = document.getElementById('journalRuleForm');
        form.reset();
        Object.entries(row).forEach(([key, value]) => {
            const field = form.elements[key];
            if (!field || field.type === 'checkbox') return;
            field.value = value ?? '';
        });
        form.elements.is_active.checked = Number(row.is_active ?? 1) === 1;
        $('#journalRuleModal select').each(function () {
            $(this).trigger('change');
        });
    }

    function openTrash() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('journalRuleTrashModal'), { focus: false }).show();
    }

    async function fetchJson(url, options = {}) {
        const res = await fetch(url, options);
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.success === false) {
            throw new Error(json.message || `요청에 실패했습니다. (${res.status})`);
        }
        return json;
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        console[type === 'error' ? 'error' : 'log'](message);
    }

    function codeLabel(items, code, fallback = '') {
        return fallback || items.find((item) => item.code === code)?.code_name || code || '-';
    }

    function accountText(row, prefix) {
        const code = row[`${prefix}_account_code`] || '';
        const name = row[`${prefix}_account_name`] || '';
        return [code, name].filter(Boolean).join(' ') || '-';
    }

    function textCell(value) {
        return escapeHtml(value || '-');
    }

    function badge(label, tone = 'light') {
        const cls = tone === 'success'
            ? 'text-bg-success'
            : tone === 'secondary'
                ? 'text-bg-secondary'
                : 'text-bg-light border text-dark';
        return `<span class="badge ${cls}">${escapeHtml(label || '-')}</span>`;
    }

    function renderStatusToggle(value, _type, row) {
        const active = Number(value ?? 0) === 1;
        const id = escapeHtml(row.id || '');
        const checked = active ? ' checked' : '';

        return `
            <div class="form-check form-switch journal-rule-status-switch">
                <input type="checkbox"
                       class="form-check-input journal-rule-status-toggle"
                       data-id="${id}"
                       ${checked}>
            </div>
        `;
    }

    async function updateRuleStatus(event) {
        const input = event.currentTarget;
        const id = input.dataset.id || '';
        const nextValue = input.checked ? 1 : 0;
        const previousValue = nextValue === 1 ? 0 : 1;
        const label = input.closest('.journal-rule-status-switch')?.querySelector('.journal-rule-status-label');

        if (!id) {
            input.checked = previousValue === 1;
            notify('error', '분개규칙 ID를 찾을 수 없습니다.');
            return;
        }

        input.disabled = true;
        if (label) label.textContent = nextValue === 1 ? '사용' : '미사용';

        try {
            const body = new URLSearchParams({ id, is_active: String(nextValue) });
            const json = await fetchJson(API.status, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });

            if (!json.success) {
                throw new Error(json.message || '상태 변경에 실패했습니다.');
            }

            const tr = input.closest('tr');
            const row = table?.row(tr);
            const rowData = row?.data();
            if (rowData) {
                rowData.is_active = nextValue;
                row.data(rowData).invalidate();
            }

            notify('success', '사용여부가 변경되었습니다.');
        } catch (error) {
            input.checked = previousValue === 1;
            if (label) label.textContent = previousValue === 1 ? '사용' : '미사용';
            notify('error', error.message || '상태 변경 중 오류가 발생했습니다.');
        } finally {
            input.disabled = false;
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }
})();
