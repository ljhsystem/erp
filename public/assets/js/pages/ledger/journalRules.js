import { createDataTable } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { PickerSelect2 } from '/public/assets/js/common/picker/picker.select2.js';
import { createExcelManagerSettingsCore } from '/public/assets/js/components/excel-manager/index.js';
import { actorColumn } from '/public/assets/js/common/actor.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import {
    initCodeSelectControls,
    onCodeOptionsLoaded,
} from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
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
        importTypes: '/api/settings/system/code/list?code_group=IMPORT_TYPE&filters=[]',
        transactionDirections: '/api/settings/system/code/list?code_group=TRANSACTION_DIRECTION&filters=[]',
        clientTypes: '/api/settings/system/code/list?code_group=CLIENT_TYPE&filters=[]',
        accounts: '/api/ledger/account/list',
    };

    const CODE_GROUPS = {
        BUSINESS_UNIT: 'businessUnits',
        IMPORT_TYPE: 'importTypes',
        TRANSACTION_DIRECTION: 'transactionDirections',
        CLIENT_TYPE: 'clientTypes',
    };

    const JOURNAL_RULE_TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.ledger.journal-rules.journal-rule-table.v1';
    const JOURNAL_RULE_COLUMN_ORDER = [
        'id',
        'sort_no',
        'rule_code',
        'rule_name',
        'business_unit',
        'transaction_direction',
        'client_type',
        'import_type',
        'debit_account_id',
        'credit_account_id',
        'vat_account_id',
        'description',
        'is_active',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
    ];
    const JOURNAL_RULE_MODAL_FIELD_POLICIES = Object.freeze([
        { selector: '#journalRuleModal [name="rule_code"]', key: 'rule_code' },
        { selector: '#journalRuleModal [name="rule_name"]', key: 'rule_name' },
        { selector: '#journalRuleModal [name="business_unit"]', key: 'business_unit' },
        { selector: '#journalRuleModal [name="transaction_direction"]', key: 'transaction_direction' },
        { selector: '#journalRuleModal [name="client_type"]', key: 'client_type' },
        { selector: '#journalRuleModal [name="import_type"]', key: 'import_type' },
        { selector: '#journalRuleModal [name="debit_account_id"]', key: 'debit_account_id' },
        { selector: '#journalRuleModal [name="credit_account_id"]', key: 'credit_account_id' },
        { selector: '#journalRuleModal [name="vat_account_id"]', key: 'vat_account_id' },
        { selector: '#journalRuleModal [name="description"]', key: 'description' },
        { selector: '#journalRuleModal [name="is_active"]', key: 'is_active' },
    ]);

    let table = null;
    let modal = null;
    let excelModal = null;
    let businessUnits = [];
    let importTypes = [];
    let transactionDirections = [];
    let clientTypes = [];
    let postingAccounts = [];
    let selectSourcesPromise = null;
    let pendingTableLabelRefresh = false;
    let journalRulePolicyBound = false;

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
                    <dt class="col-4">洹쒖튃肄붾뱶</dt><dd class="col-8">${escapeHtml(row.rule_code || '-')}</dd>
                    <dt class="col-4">洹쒖튃紐?/dt><dd class="col-8">${escapeHtml(row.rule_name || '-')}</dd>
                    <dt class="col-4">?ъ뾽援щ텇</dt><dd class="col-8">${escapeHtml(codeLabel(businessUnits, row.business_unit, row.business_unit_name))}</dd>
                    <dt class="col-4">嫄곕옒援щ텇</dt><dd class="col-8">${escapeHtml(codeLabel(transactionDirections, row.transaction_direction, row.transaction_direction_name))}</dd>
                    <dt class="col-4">嫄곕옒泥섍뎄遺?/dt><dd class="col-8">${escapeHtml(codeLabel(clientTypes, row.client_type, row.client_type_name))}</dd>
                    <dt class="col-4">?먮즺?좏삎</dt><dd class="col-8">${escapeHtml(codeLabel(importTypes, row.import_type, row.import_type_name))}</dd>
                    <dt class="col-4">李⑤?怨꾩젙</dt><dd class="col-8">${escapeHtml(accountText(row, 'debit'))}</dd>
                    <dt class="col-4">?蹂怨꾩젙</dt><dd class="col-8">${escapeHtml(accountText(row, 'credit'))}</dd>
                    <dt class="col-4">부가세계정</dt><dd class="col-8">${escapeHtml(accountText(row, 'vat'))}</dd>
                    <dt class="col-4">??젣?쇱떆</dt><dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
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
            <td>${escapeHtml(codeLabel(transactionDirections, row.transaction_direction, row.transaction_direction_name))}</td>
            <td>${escapeHtml(codeLabel(clientTypes, row.client_type, row.client_type_name))}</td>
            <td>${escapeHtml(codeLabel(importTypes, row.import_type, row.import_type_name))}</td>
            <td>${escapeHtml(row.deleted_at || '')}</td>
            <td class="text-center">
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id || '')}">蹂듭썝</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id || '')}">?곴뎄??젣</button>
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

        initExcelDataset();
        initTable();
        bindEvents();
        bindCodeOptionRefresh();
        bindJournalRulePolicySync();
        applyJournalRuleModalPolicyLabels(document);
        warmSelectSources();
    }

    function initExcelDataset() {
        const form = document.getElementById('journal-rule-excel-upload-form');
        if (!form) return;

        form.dataset.templateUrl = '/api/ledger/journal-rules/template';
        form.dataset.downloadUrl = '/api/ledger/journal-rules/excel';
        form.dataset.uploadUrl = '/api/ledger/journal-rules/excel-upload';

        createExcelManagerSettingsCore({
            domain: 'ledger-journal-rule',
            formSelector: '#journal-rule-excel-upload-form',
            tableSettingsStorageKey: JOURNAL_RULE_TABLE_SETTINGS_STORAGE_KEY,
            tableSettingsMetaDomain: 'ledger-journal-rule',
        });
    }

    async function prepareSelectSources(modalEl) {
        try {
            await Promise.all([
                loadSelectSources(),
                initCodeSelectControls(modalEl),
            ]);
            initAccountSelect2();
            refreshRuleTableLabels();
        } catch (error) {
            notify('error', error.message || '분개규칙 기준정보를 불러오지 못했습니다.');
        }
    }

    async function ensureSelectSourcesReady() {
        if (!selectSourcesPromise) {
            const modalEl = document.getElementById('journalRuleModal');
            selectSourcesPromise = prepareSelectSources(modalEl);
        }
        await selectSourcesPromise;
    }

    function warmSelectSources() {
        const run = () => {
            void ensureSelectSourcesReady().catch(() => {});
        };

        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(run, { timeout: 1200 });
            return;
        }

        window.setTimeout(run, 250);
    }

    function isRuleModalOpen() {
        return document.getElementById('journalRuleModal')?.classList.contains('show') === true;
    }

    function refreshRuleTableLabels() {
        if (isRuleModalOpen()) {
            pendingTableLabelRefresh = true;
            return;
        }

        pendingTableLabelRefresh = false;
        table?.rows().invalidate('data').draw(false);
    }

    function currentJournalRulePolicyState() {
        return readDataTableSettingsState(JOURNAL_RULE_TABLE_SETTINGS_STORAGE_KEY) || {};
    }

    function journalRuleFieldLabel(key, fallback = '') {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnDisplayName(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentJournalRulePolicyState(),
            normalizedKey || fallback
        );
    }

    function journalRuleFieldRequirement(key) {
        const normalizedKey = String(key || '').trim();
        return resolveDataTableColumnRequirementPolicy(
            { key: normalizedKey, system_field_name: normalizedKey, original_column_key: normalizedKey },
            currentJournalRulePolicyState()
        );
    }

    function journalRuleFieldStarMarkup(key) {
        const policy = journalRuleFieldRequirement(key);
        if (policy === 'required') {
            return '<span class="column-policy-star is-required" aria-hidden="true">*</span>';
        }
        if (policy === 'optional') {
            return '<span class="column-policy-star is-optional" aria-hidden="true">*</span>';
        }
        return '';
    }

    function findJournalRuleModalLabel(selector, root = document) {
        const field = root.querySelector(selector);
        if (!field) return null;

        if (field.id) {
            const byFor = root.querySelector(`label[for="${field.id}"]`);
            if (byFor) return byFor;
        }

        const group = field.closest('.form-check, .col-md-3, .col-md-4, .col-md-6, .col-md-9, .col-12');
        if (group) {
            const label = group.querySelector('label.form-label, label.form-check-label');
            if (label) return label;
        }

        return field.closest('label.form-label, label.form-check-label') || null;
    }

    function applyJournalRuleModalPolicyLabels(root = document) {
        JOURNAL_RULE_MODAL_FIELD_POLICIES.forEach((field) => {
            const labelEl = findJournalRuleModalLabel(field.selector, root);
            if (!labelEl) return;

            const displayName = journalRuleFieldLabel(field.key, field.key);
            const starMarkup = journalRuleFieldStarMarkup(field.key);
            labelEl.innerHTML = `${escapeHtml(displayName)}${starMarkup ? ` ${starMarkup}` : ''}`;
        });
    }

    function bindJournalRulePolicySync() {
        if (journalRulePolicyBound) return;
        journalRulePolicyBound = true;

        document.addEventListener('datatable-settings:updated', (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== JOURNAL_RULE_TABLE_SETTINGS_STORAGE_KEY) {
                return;
            }

            applyJournalRuleModalPolicyLabels(document);
        });
    }

    function collectJournalRuleModalValues() {
        const form = document.getElementById('journalRuleForm');
        const formData = new FormData(form);

        return {
            rule_code: String(formData.get('rule_code') || '').trim(),
            rule_name: String(formData.get('rule_name') || '').trim(),
            business_unit: String(formData.get('business_unit') || '').trim(),
            transaction_direction: String(formData.get('transaction_direction') || '').trim(),
            client_type: String(formData.get('client_type') || '').trim(),
            import_type: String(formData.get('import_type') || '').trim(),
            debit_account_id: String(formData.get('debit_account_id') || '').trim(),
            credit_account_id: String(formData.get('credit_account_id') || '').trim(),
            vat_account_id: String(formData.get('vat_account_id') || '').trim(),
            description: String(formData.get('description') || '').trim(),
            is_active: formData.has('is_active') ? '1' : '0',
        };
    }

    function focusJournalRulePolicyField(selector) {
        const field = document.querySelector(selector);
        if (!field) return;
        if (typeof field.focus === 'function') {
            field.focus();
        }
        if (window.jQuery && field.tagName === 'SELECT') {
            window.jQuery(field).trigger('focus');
        }
    }

    function validateJournalRuleRequiredPolicies() {
        const values = collectJournalRuleModalValues();

        for (const field of JOURNAL_RULE_MODAL_FIELD_POLICIES) {
            if (journalRuleFieldRequirement(field.key) !== 'required') {
                continue;
            }

            if (String(values[field.key] ?? '').trim() !== '') {
                continue;
            }

            notify('warning', `${journalRuleFieldLabel(field.key, field.key)} 항목을 입력해 주세요.`);
            focusJournalRulePolicyField(field.selector);
            return false;
        }

        return true;
    }

    function setJournalRuleModalLoading(isLoading = false) {
        const loading = Boolean(isLoading);
        const modalEl = document.getElementById('journalRuleModal');
        modalEl?.classList.toggle('is-loading-detail', loading);
        modalEl?.setAttribute('aria-busy', loading ? 'true' : 'false');
    }

    async function loadSelectSources() {
        const [businessJson, typeJson, directionJson, clientJson, importJson, accountJson] = await Promise.all([
            fetchJson(API.businessUnits),
            fetchJson(API.transactionDirections),
            fetchJson(API.clientTypes),
            fetchJson(API.importTypes),
            fetchJson(API.accounts),
        ]);

        businessUnits = activeCodes(businessJson.data || []);
        transactionDirections = activeCodes(directionJson.data || []);
        clientTypes = activeCodes(clientJson.data || []);
        importTypes = activeCodes(importJson.data || []);

        if (!transactionDirections.length) {
            transactionDirections = [
                { code: 'PURCHASE', code_name: '留ㅼ엯' },
                { code: 'SALES', code_name: '留ㅼ텧' },
                { code: 'IN', code_name: '?낃툑' },
                { code: 'OUT', code_name: '異쒓툑' },
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
                if (stateName === 'importTypes') importTypes = rows;
                if (stateName === 'transactionDirections') transactionDirections = rows;
                if (stateName === 'clientTypes') clientTypes = rows;
            });
            refreshRuleTableLabels();
        });
    }

    function activeCodes(rows) {
        return rows.filter((row) => Number(row.is_active ?? 1) === 1);
    }

    function initAccountSelect2() {
        const accountItems = postingAccounts.map((row) => ({
            id: row.id,
            text: `[${row.full_path || row.account_name || ''}]`,
        }));

        document.querySelectorAll('#journalRuleModal .js-account-select').forEach((select) => {
            delete select.dataset.quickAddEnabled;
            delete select.dataset.hideCommonAdd;
            PickerSelect2.reloadOptions(select, accountItems, 'id', 'text', null, {
                quickAddEnabled: false,
            });
        });

        if (!$.fn.select2) return;

        document.querySelectorAll('#journalRuleModal .js-account-select').forEach((select) => {
            PickerSelect2.create(select, {
                dropdownParent: $('#journalRuleModal'),
                placeholder: '?좏깮',
                templateResult: renderAccountOption,
                templateSelection: renderAccountSelection,
            });
        });
    }

    function renderAccountOption(data) {
        const span = document.createElement('span');
        span.textContent = data?.text || '';
        return span;
    }

    function renderAccountSelection(data) {
        if (!data || !data.id) {
            return '?좏깮';
        }
        return data.text || data.id;
    }

    function initTable() {
        table = createDataTable({
            tableSelector: '#journal-rule-table',
            api: API.list,
            deleteApi: API.delete,
            bulkDelete: true,
            columns: columns(),
            defaultOrder: [[1, 'asc']],
            pageLength: 100,
            searchTableId: 'journalRule',
            pageLoading: false,
            selectionColumn: {
                widthResizable: true,
            },
            tableSettings: {
                pageKey: 'ledger.journal-rules',
                tableKey: 'journal-rule-table',
                storageKey: JOURNAL_RULE_TABLE_SETTINGS_STORAGE_KEY,
                metaDomain: 'ledger-journal-rule',
                tableLabel: '분개규칙',
                title: '분개규칙 테이블 설정',
                defaultVisibleColumns: [
                    'id',
                    'sort_no',
                    'rule_code',
                    'rule_name',
                    'business_unit',
                    'transaction_direction',
                    'client_type',
                    'import_type',
                    'debit_account_id',
                    'credit_account_id',
                    'vat_account_id',
                    'description',
                    'is_active',
                ],
            },
            buttons: [
                { text: '휴지통', className: 'btn btn-danger btn-sm', action: openTrash },
                { text: '엑셀관리', className: 'btn btn-success btn-sm', action: () => excelModal?.show() },
                { text: '새 분개규칙', className: 'btn btn-warning btn-sm', action: () => { void openCreate(); } },
            ],
        });

        table.on('init.dt draw.dt', () => {
            const count = table.page.info()?.recordsDisplay ?? 0;
            const countEl = document.getElementById('journalRuleCount');
            if (countEl) countEl.textContent = `총 ${Number(count).toLocaleString('ko-KR')}건`;
        });

        SearchForm({
            table,
            apiList: API.list,
            tableId: 'journalRule',
            defaultSearchField: 'rule_name',
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
            },
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
                defaultContent: '<i class="bi bi-list"></i>',
                settingsKey: '__reorder',
                width: '44px',
                widthResizable: true,
            },
            { data: 'id', title: 'ID', visible: false, render: textCell, settingsKey: 'id' },
            { data: 'sort_no', title: '순번', className: 'dt-sequence-column text-center text-nowrap', width: 60, settingsKey: 'sort_no' },
            { data: 'rule_code', title: '규칙코드', className: 'text-nowrap', render: textCell, settingsKey: 'rule_code' },
            { data: 'rule_name', title: '규칙명', render: textCell, settingsKey: 'rule_name' },
            { data: 'business_unit', title: '사업구분', className: 'text-nowrap', render: (_value, _type, row) => badge(codeLabel(businessUnits, row.business_unit, row.business_unit_name)), settingsKey: 'business_unit' },
            { data: 'transaction_direction', title: '거래구분', className: 'text-nowrap text-center', render: (_value, _type, row) => badge(codeLabel(transactionDirections, row.transaction_direction, row.transaction_direction_name)), settingsKey: 'transaction_direction' },
            { data: 'client_type', title: '거래처구분', className: 'text-nowrap', render: (_value, _type, row) => badge(codeLabel(clientTypes, row.client_type, row.client_type_name)), settingsKey: 'client_type' },
            { data: 'import_type', title: '자료유형', className: 'text-nowrap', render: (_value, _type, row) => badge(codeLabel(importTypes, row.import_type, row.import_type_name)), settingsKey: 'import_type' },
            { data: 'debit_account_id', title: '차변계정', render: (_value, _type, row) => escapeHtml(accountText(row, 'debit')), settingsKey: 'debit_account_id' },
            { data: 'credit_account_id', title: '대변계정', render: (_value, _type, row) => escapeHtml(accountText(row, 'credit')), settingsKey: 'credit_account_id' },
            { data: 'vat_account_id', title: '부가세계정', render: (_value, _type, row) => escapeHtml(accountText(row, 'vat')), settingsKey: 'vat_account_id' },
            { data: 'description', title: '설명/적요', render: textCell, settingsKey: 'description' },
            { data: 'is_active', title: '상태', className: 'text-center text-nowrap', orderable: false, render: renderStatusToggle, settingsKey: 'is_active' },
            { data: 'created_at', title: '생성일시', visible: false, render: textCell, settingsKey: 'created_at' },
            actorColumn('created_by', '생성자', { visible: false, settingsKey: 'created_by' }),
            { data: 'updated_at', title: '수정일시', visible: false, render: textCell, settingsKey: 'updated_at' },
            actorColumn('updated_by', '수정자', { visible: false, settingsKey: 'updated_by' }),
            { data: 'deleted_at', title: '삭제일시', visible: false, render: textCell, settingsKey: 'deleted_at' },
            actorColumn('deleted_by', '삭제자', { visible: false, settingsKey: 'deleted_by' }),
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
        const modalEl = document.getElementById('journalRuleModal');
        modalEl?.addEventListener('shown.bs.modal', () => {
            applyJournalRuleModalPolicyLabels(modalEl);
        });

        modalEl?.addEventListener('hidden.bs.modal', () => {
            if (pendingTableLabelRefresh) {
                refreshRuleTableLabels();
            }
        });

        $('#journal-rule-table tbody').on('dblclick', 'tr', async function () {
            const row = table.row(this).data();
            if (!row?.id) return;
            void openEdit(row.id);
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
                void openEdit(id);
            });

        document.getElementById('journalRuleForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!validateJournalRuleRequiredPolicies()) {
                return;
            }

            try {
                const form = event.currentTarget;
                const formData = new FormData(form);
                if (!formData.has('is_active')) formData.set('is_active', '0');

                const json = await fetchJson(API.save, { method: 'POST', body: formData });
                if (!json.success) throw new Error(json.message || '??μ뿉 ?ㅽ뙣?덉뒿?덈떎.');
                notify('success', json.message || '??λ릺?덉뒿?덈떎.');
                modal.hide();
                table.ajax.reload(null, false);
            } catch (error) {
                notify('error', error.message || '??μ뿉 ?ㅽ뙣?덉뒿?덈떎.');
            }
        });

        document.getElementById('journalRuleDeleteBtn')?.addEventListener('click', async () => {
            try {
                const id = document.getElementById('journalRuleId')?.value || '';
                if (!id) {
                    modal.hide();
                    return;
                }
                if (!confirm('분개규칙을 삭제하시겠습니까?')) return;

                const body = new URLSearchParams({ id });
                const json = await fetchJson(API.delete, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                });

                if (!json.success) {
                    throw new Error(json.message || '삭제에 실패했습니다.');
                }

                notify('success', '분개규칙이 삭제되었습니다.');
                modal.hide();
                table.ajax.reload(null, false);
            } catch (error) {
                notify('error', error.message || '삭제에 실패했습니다.');
            }
        });
    }

    async function openCreate() {
        const form = document.getElementById('journalRuleForm');
        form.reset();
        form.querySelector('[name="id"]').value = '';
        form.querySelector('[name="is_active"]').checked = true;

        document.querySelector('#journalRuleModal .modal-title').textContent = '분개규칙 등록';

        document.getElementById('journalRuleDeleteBtn').classList.add('d-none');
        setJournalRuleModalLoading(true);
        modal.show();

        try {
            await ensureSelectSourcesReady();
            $('#journalRuleModal select').val('').trigger('change');
            applyJournalRuleModalPolicyLabels(document.getElementById('journalRuleModal'));
        } catch (error) {
            notify('error', error.message || '분개규칙 입력 준비 중 오류가 발생했습니다.');
        } finally {
            setJournalRuleModalLoading(false);
        }
    }
    async function openEdit(id) {
        const title = document.querySelector('#journalRuleModal .modal-title');
        if (title) title.textContent = '분개규칙 수정';

        document.getElementById('journalRuleDeleteBtn').classList.add('d-none');
        setJournalRuleModalLoading(true);
        modal.show();

        try {
            const [json] = await Promise.all([
                fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`),
                ensureSelectSourcesReady(),
            ]);

            if (!json.success) {
                throw new Error(json.message || '분개규칙을 찾을 수 없습니다.');
            }

            bindForm(json.data || {});
            applyJournalRuleModalPolicyLabels(document.getElementById('journalRuleModal'));

            if (title) title.textContent = '분개규칙 수정';
            document.getElementById('journalRuleDeleteBtn').classList.remove('d-none');
        } catch (error) {
            notify('error', error.message || '분개규칙 상세 조회 중 오류가 발생했습니다.');
        } finally {
            setJournalRuleModalLoading(false);
        }
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
            throw new Error(json.message || `요청이 실패했습니다. (${res.status})`);
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

        if (!id) {
            input.checked = previousValue === 1;
            notify('error', '분개규칙 ID를 찾을 수 없습니다.');
            return;
        }

        input.disabled = true;

        try {
            const body = new URLSearchParams({ id, is_active: String(nextValue) });
            const json = await fetchJson(API.status, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });

            if (!json.success) {
                throw new Error(json.message || '?곹깭 蹂寃쎌뿉 ?ㅽ뙣?덉뒿?덈떎.');
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
