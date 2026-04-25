import { formatNumber, onlyNumber } from '/public/assets/js/common/format.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/components/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { createJournalBasicInfoBridge } from '/public/assets/js/pages/ledger/journal.basic-info.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

(() => {
    const journalTableEl = document.getElementById('journal-table');
    const tableBody = document.getElementById('journal-table-body') || journalTableEl?.querySelector('tbody');
    const form = document.getElementById('journal-edit-form');
    const modalEl = document.getElementById('journalModal');
    const addLineBtn = document.getElementById('btnAddVoucherLine');
    const lineBody = document.getElementById('voucher-line-body');
    const debitTotalEl = document.getElementById('voucher_debit_total');
    const creditTotalEl = document.getElementById('voucher_credit_total');
    const balanceStatusEl = document.getElementById('voucher_balance_status');
    const voucherStatusEl = document.getElementById('voucher_status');
    const voucherDateEl = document.getElementById('voucher_date');
    const voucherTypeEl = document.getElementById('voucher_ref_type');
    const modalTitleEl = document.getElementById('journalModalLabel');
    const transactionModalEl = document.getElementById('journalTransactionSearchModal');
    const transactionSearchBody = document.getElementById('journal_transaction_search_body');
    const transactionSearchKeywordEl = document.getElementById('journal_transaction_search_keyword');
    const linkedTransactionIdEl = document.getElementById('linked_transaction_id');
    const linkedTransactionSummaryEl = document.getElementById('linked_transaction_summary');
    const selectTransactionBtn = document.getElementById('btnSelectTransaction');
    const clearTransactionLinkBtn = document.getElementById('btnClearTransactionLink');
    const searchTransactionBtn = document.getElementById('btnSearchTransaction');

    if (!tableBody || !journalTableEl || !form || !modalEl || !lineBody || !voucherDateEl) {
        return;
    }

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    if (transactionModalEl && transactionModalEl.parentElement !== document.body) {
        document.body.appendChild(transactionModalEl);
    }

    const pickerLayerEl = document.getElementById('journal-today-picker');
    if (pickerLayerEl && pickerLayerEl.parentElement !== document.body) {
        document.body.appendChild(pickerLayerEl);
    }

    const API = {
        list: '/api/ledger/voucher/list',
        detail: '/api/ledger/voucher/detail',
        save: '/api/ledger/voucher/save',
        remove: '/api/ledger/voucher/delete',
        transactionSearch: '/api/ledger/voucher/transaction-search',
        accountList: '/api/ledger/account/list',
        excelTemplate: '/api/ledger/voucher/template',
        excelDownload: '/api/ledger/voucher/download',
        excelUpload: '/api/ledger/voucher/excel-upload',
        trash: '/api/ledger/voucher/trash',
        restore: '/api/ledger/voucher/restore',
        purge: '/api/ledger/voucher/purge',
        purgeAll: '/api/ledger/voucher/purge-all',
        reorder: '/api/ledger/voucher/reorder',
    };

    const STATUS_LABELS = {
        draft: '?꾩떆???,
        posted: '?뺤젙',
        locked: '留덇컧',
        deleted: '??젣',
    };

    const TYPE_LABELS = {
        MANUAL: '?섎룞?꾪몴',
        AUTO: '?먮룞?꾪몴',
        ADJUST: '議곗젙?꾪몴',
        CLOSING: '寃곗궛?꾪몴',
    };

    const LINKED_STATUS_LABELS = {
        linked: '?곌껐',
        unlinked: '誘몄뿰寃?,
    };

    const JOURNAL_DATE_OPTIONS = [
        { value: 'voucher_date', label: '?꾪몴?쇱옄' },
        { value: 'updated_at', label: '?섏젙?쇱떆' },
    ];

    const QUICK_CREATE_ACCOUNT_VALUE = '__quick_create_account__';
    const modal = window.bootstrap ? new bootstrap.Modal(modalEl, { focus: false }) : null;
    const transactionModal = window.bootstrap && transactionModalEl
        ? new bootstrap.Modal(transactionModalEl, { focus: false })
        : null;
    const basicInfoBridge = createJournalBasicInfoBridge({ notify });

    let todayPicker = null;
    let accountPickerItems = null;
    let transactionRows = [];
    let journalTable = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            AppCore.notify(type, message);
            return;
        }

        window.alert(message);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, options);
        return response.json();
    }

    function formatDate(date) {
        if (!date) {
            return '';
        }

        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function normalizeAmountValue(value) {
        const raw = String(value ?? '')
            .replace(/,/g, '')
            .trim();
        const [integerPart = ''] = raw.split('.');

        return onlyNumber(integerPart);
    }

    function parseAmountValue(value) {
        const normalized = normalizeAmountValue(value);
        return normalized === '' ? 0 : Number(normalized);
    }

    function formatAmountValue(value) {
        const normalized = normalizeAmountValue(value);
        return normalized === '' ? '' : formatNumber(normalized);
    }

    function setAmountInputValue(input, { formatted = true } = {}) {
        if (!input) {
            return '';
        }

        const normalized = normalizeAmountValue(input.value);
        input.value = normalized === ''
            ? ''
            : (formatted ? formatNumber(normalized) : normalized);

        return normalized;
    }

    function getAmountInputTarget(target) {
        if (!(target instanceof HTMLInputElement) || !target.classList.contains('input-amount')) {
            return null;
        }

        return target;
    }

    function translateStatus(value) {
        return STATUS_LABELS[value] || value || '-';
    }

    function translateType(value) {
        return TYPE_LABELS[value] || value || '-';
    }

    function translateLinkedStatus(value) {
        return LINKED_STATUS_LABELS[value] || value || '誘몄뿰寃?;
    }

    function buildTransactionSummary(row = null) {
        if (!row) {
            return '?곌껐??嫄곕옒媛 ?놁뒿?덈떎.';
        }

        const date = row.transaction_date || '-';
        const client = row.client_name || row.client_id || '-';
        const amount = formatAmountValue(row.total_amount || 0) || '0';

        return `${date} / ${client} / ${amount}`;
    }

    function setLinkedTransaction(row = null) {
        if (!linkedTransactionIdEl || !linkedTransactionSummaryEl) {
            return;
        }

        linkedTransactionIdEl.value = row?.id || '';
        linkedTransactionSummaryEl.textContent = buildTransactionSummary(row);
        linkedTransactionSummaryEl.title = row
            ? `${buildTransactionSummary(row)}${row.item_summary ? ` / ${row.item_summary}` : ''}`
            : '';
    }

    function setModalTitle(mode = 'create') {
        if (!modalTitleEl) {
            return;
        }

        const icon = '<i class="bi bi-journal-check me-2"></i>';
        modalTitleEl.innerHTML = mode === 'edit'
            ? `${icon}?꾪몴 ?섏젙`
            : `${icon}?꾪몴 ?깅줉`;
    }

    function renderPickerOption(data) {
        const span = document.createElement('span');
        span.textContent = data?.text || '';

        if (data?.id === QUICK_CREATE_ACCOUNT_VALUE) {
            span.className = 'journal-quick-create-option';
        }

        return span;
    }

    function renderPickerSelection(data) {
        if (!data || !data.id || data.id === QUICK_CREATE_ACCOUNT_VALUE) {
            return '怨꾩젙怨쇰ぉ ?좏깮';
        }

        return data.text || data.id;
    }

    function setSelect2Option(selectEl, value, text) {
        if (!selectEl || !window.jQuery) {
            return;
        }

        const normalizedValue = String(value ?? '').trim();
        const $select = window.jQuery(selectEl);

        if (normalizedValue === '') {
            $select.val('').trigger('change');
            return;
        }

        $select.find(`option[value="${normalizedValue}"]`).remove();
        $select.append(new Option(text || normalizedValue, normalizedValue, true, true));
        $select.val(normalizedValue).trigger('change');
    }

    function clearSelect2Value(selectEl) {
        if (!selectEl || !window.jQuery) {
            return;
        }

        window.jQuery(selectEl).val('').trigger('change');
    }

    function initTodayPicker() {
        if (todayPicker) {
            return todayPicker;
        }

        const container = document.getElementById('today-picker') || document.getElementById('journal-today-picker');
        if (!container) {
            return null;
        }

        todayPicker = AdminPicker.create({
            type: 'today',
            container,
        });

        todayPicker.subscribe((_, date) => {
            const input = todayPicker.__target;
            if (!input || !date) {
                return;
            }

            input.value = formatDate(date);
            normalizeStartEnd(input);
            todayPicker.close();
        });

        return todayPicker;
    }

    function normalizeStartEnd(input) {
        if (!input || !['dateStart', 'dateEnd'].includes(input.name)) {
            return;
        }

        const formEl = input.closest('form') || document;
        const start = formEl.querySelector('input[name="dateStart"]');
        const end = formEl.querySelector('input[name="dateEnd"]');

        if (!start || !end || !start.value || !end.value) {
            return;
        }

        if (input.name === 'dateStart' && start.value > end.value) {
            end.value = start.value;
        }

        if (input.name === 'dateEnd' && end.value < start.value) {
            start.value = end.value;
        }
    }

    function bindDatePickerInput() {
        const openPicker = (input, event) => {
            if (!input) {
                return;
            }

            event?.preventDefault();
            event?.stopPropagation();

            const picker = initTodayPicker();
            if (!picker) {
                return;
            }

            picker.__target = input;

            if (typeof picker.clearDate === 'function') {
                picker.clearDate();
            }

            if (input.value) {
                const current = new Date(input.value);
                if (!Number.isNaN(current.getTime()) && typeof picker.setDate === 'function') {
                    picker.setDate(current);
                }
            }

            picker.open({ anchor: input });
        };

        document.querySelectorAll('.admin-date').forEach((input) => {
            if (input.__journalDatePickerBound) {
                return;
            }

            input.__journalDatePickerBound = true;
            input.addEventListener('click', (event) => openPicker(input, event));
        });

        document.querySelectorAll('.date-icon').forEach((icon) => {
            if (icon.__journalDatePickerBound) {
                return;
            }

            icon.__journalDatePickerBound = true;
            icon.addEventListener('click', (event) => {
                const wrap = icon.closest('.date-input, .date-input-wrap');
                const input = wrap ? wrap.querySelector('.admin-date') : null;
                openPicker(input, event);
            });
        });
    }

    function buildAccountPickerItems(rows = []) {
        const mappedRows = rows
            .map((row) => {
                const accountCode = String(row.account_code ?? row.value ?? '').trim();
                const accountName = String(row.account_name ?? row.name ?? '').trim();

                return {
                    id: accountCode,
                    text: accountCode && accountName ? `${accountCode} - ${accountName}` : accountCode,
                };
            })
            .filter((item) => item.id !== '');

        return [
            { id: '', text: '怨꾩젙怨쇰ぉ ?좏깮' },
            ...mappedRows,
            { id: QUICK_CREATE_ACCOUNT_VALUE, text: '+ 鍮좊Ⅸ ?깅줉' },
        ];
    }

    async function ensureAccountPickerItems(force = false) {
        if (!force && Array.isArray(accountPickerItems)) {
            return accountPickerItems;
        }

        try {
            const json = await fetchJson(API.accountList);
            const rows = Array.isArray(json?.data) ? json.data : [];
            accountPickerItems = buildAccountPickerItems(rows);
        } catch (error) {
            console.error('[ledger-journal] account list load failed', error);
            accountPickerItems = buildAccountPickerItems([]);
        }

        return accountPickerItems;
    }

    async function reloadAllAccountPickers({ selectedValue = '', selectedText = '', sourceEl = null } = {}) {
        const items = await ensureAccountPickerItems(true);

        Array.from(lineBody.querySelectorAll('.line-account-code-picker')).forEach((selectEl) => {
            const currentValue = selectEl === sourceEl
                ? selectedValue
                : String(selectEl.value || '').trim();

            AdminPicker.reloadSelect2(selectEl, items, 'id', 'text', currentValue || '');

            if (selectEl === sourceEl && selectedValue) {
                setSelect2Option(selectEl, selectedValue, selectedText || selectedValue);
            }
        });
    }

    function bindAccountQuickCreate(selectEl) {
        if (!selectEl || !window.jQuery || selectEl.dataset.quickCreateBound === 'true') {
            return;
        }

        const $select = window.jQuery(selectEl);
        $select.on('select2:select', async (event) => {
            const selectedId = String(event?.params?.data?.id || '').trim();
            if (selectedId !== QUICK_CREATE_ACCOUNT_VALUE) {
                return;
            }

            event.preventDefault();
            window.setTimeout(() => {
                clearSelect2Value(selectEl);

                basicInfoBridge.openQuickCreate('account', {
                    sourceEl: selectEl,
                    async onSaved(payload) {
                        await reloadAllAccountPickers({
                            selectedValue: payload.value,
                            selectedText: payload.text,
                            sourceEl: selectEl,
                        });
                    },
                    async onDeleted(payload) {
                        await reloadAllAccountPickers();

                        if (payload.value && String(selectEl.value || '').trim() === payload.value) {
                            clearSelect2Value(selectEl);
                        }
                    },
                });
            }, 0);
        });

        selectEl.dataset.quickCreateBound = 'true';
    }

    async function initLineAccountPicker(selectEl, selectedValue = '') {
        if (!selectEl || !window.jQuery) {
            return;
        }

        bindAccountQuickCreate(selectEl);

        AdminPicker.select2(selectEl, {
            placeholder: '怨꾩젙怨쇰ぉ ?좏깮',
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
            templateResult: renderPickerOption,
            templateSelection: renderPickerSelection,
        });

        const items = await ensureAccountPickerItems();
        AdminPicker.reloadSelect2(selectEl, items, 'id', 'text', selectedValue || '');
    }

    function emptyLineRow() {
        return '<tr class="voucher-line-empty"><td colspan="6" class="text-center text-muted py-4">遺꾧컻?쇱씤??異붽???二쇱꽭??</td></tr>';
    }

    function syncLineNumbers() {
        Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'))
            .forEach((row, index) => {
                const numberCell = row.querySelector('.line-no');
                if (numberCell) {
                    numberCell.textContent = String(index + 1);
                }
            });
    }

    function calculateTotals() {
        const rows = Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'));

        const debit = rows.reduce((sum, row) => {
            const value = parseAmountValue(row.querySelector('.line-debit')?.value || '0');
            return sum + (Number.isFinite(value) ? value : 0);
        }, 0);

        const credit = rows.reduce((sum, row) => {
            const value = parseAmountValue(row.querySelector('.line-credit')?.value || '0');
            return sum + (Number.isFinite(value) ? value : 0);
        }, 0);

        debitTotalEl.value = formatAmountValue(debit) || '0';
        creditTotalEl.value = formatAmountValue(credit) || '0';

        if (rows.length === 0) {
            balanceStatusEl.value = '遺꾧컻?쇱씤???낅젰??二쇱꽭??';
            return;
        }

        balanceStatusEl.value = debit === credit
            ? '李⑤?/?蹂 ?⑷퀎媛 ?쇱튂?⑸땲??'
            : '李⑤?/?蹂 ?⑷퀎媛 ?쇱튂?섏? ?딆뒿?덈떎.';
    }

    async function addLineRow(line = {}) {
        lineBody.querySelector('.voucher-line-empty')?.remove();

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-center line-no"></td>
            <td>
                <select class="form-select form-select-sm line-account-code-picker">
                    <option value="">怨꾩젙怨쇰ぉ ?좏깮</option>
                </select>
            </td>
            <td>
                <input type="text"
                       inputmode="numeric"
                       class="form-control form-control-sm line-debit input-amount"
                       value="${escapeHtml(line.debit || '')}"
                       placeholder="0">
            </td>
            <td>
                <input type="text"
                       inputmode="numeric"
                       class="form-control form-control-sm line-credit input-amount"
                       value="${escapeHtml(line.credit || '')}"
                       placeholder="0">
            </td>
            <td>
                <input type="text"
                       class="form-control form-control-sm line-summary"
                       value="${escapeHtml(line.line_summary || '')}"
                       placeholder="?쇱씤 ?곸슂">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-line">??젣</button>
            </td>
        `;

        lineBody.appendChild(row);
        setAmountInputValue(row.querySelector('.line-debit'));
        setAmountInputValue(row.querySelector('.line-credit'));
        syncLineNumbers();
        calculateTotals();

        const accountSelect = row.querySelector('.line-account-code-picker');
        await initLineAccountPicker(accountSelect, line.account_code || '');

        if (line.account_code && !accountSelect.value) {
            setSelect2Option(accountSelect, line.account_code, line.account_text || line.account_code);
        }
    }

    function resetModal() {
        form.reset();
        document.getElementById('journal_id').value = '';
        voucherDateEl.value = formatDate(new Date());
        voucherStatusEl.value = 'draft';
        if (voucherTypeEl) {
            voucherTypeEl.value = 'MANUAL';
        }
        setLinkedTransaction(null);
        lineBody.innerHTML = emptyLineRow();
        setModalTitle('create');
        calculateTotals();
    }

    function collectLines() {
        return Array.from(lineBody.querySelectorAll('tr'))
            .filter((row) => !row.classList.contains('voucher-line-empty'))
            .map((row) => ({
                account_code: row.querySelector('.line-account-code-picker')?.value?.trim() ?? '',
                debit: normalizeAmountValue(row.querySelector('.line-debit')?.value ?? '') || '0',
                credit: normalizeAmountValue(row.querySelector('.line-credit')?.value ?? '') || '0',
                line_summary: row.querySelector('.line-summary')?.value?.trim() ?? '',
            }))
            .filter((line) => line.account_code || Number(line.debit) > 0 || Number(line.credit) > 0 || line.line_summary);
    }

    function validateBeforeSave() {
        const lines = collectLines();

        if (lines.length === 0) {
            notify('warning', '遺꾧컻?쇱씤??1媛??댁긽 ?낅젰??二쇱꽭??');
            return false;
        }

        let debitTotal = 0;
        let creditTotal = 0;

        for (let index = 0; index < lines.length; index += 1) {
            const line = lines[index];
            const debit = Number(line.debit || '0');
            const credit = Number(line.credit || '0');

            if (!line.account_code) {
                notify('warning', `${index + 1}踰??쇱씤??怨꾩젙怨쇰ぉ???좏깮??二쇱꽭??`);
                return false;
            }

            if (debit <= 0 && credit <= 0) {
                notify('warning', `${index + 1}踰??쇱씤??李⑤? ?먮뒗 ?蹂 湲덉븸???낅젰??二쇱꽭??`);
                return false;
            }

            if (debit > 0 && credit > 0) {
                notify('warning', `${index + 1}踰??쇱씤? 李⑤?怨??蹂 以??섎굹留??낅젰?????덉뒿?덈떎.`);
                return false;
            }

            debitTotal += Number.isFinite(debit) ? debit : 0;
            creditTotal += Number.isFinite(credit) ? credit : 0;
        }

        if (debitTotal !== creditTotal) {
            notify('warning', '李⑤? ?⑷퀎? ?蹂 ?⑷퀎媛 ?쇱튂?댁빞 ?⑸땲??');
            return false;
        }

        return true;
    }

    function getVoucherSortNo(row = {}) {
        const numericSortNo = Number(String(row.sort_no ?? '').replace(/,/g, ''));
        return Number.isFinite(numericSortNo) ? numericSortNo : 0;
    }

    function buildJournalColumns() {
        return [
            {
                title: '<i class="bi bi-arrows-move"></i>',
                className: 'reorder-handle no-colvis text-center',
                orderable: false,
                searchable: false,
                defaultContent: '<i class="bi bi-list"></i>',
            },
            {
                data: 'sort_no',
                title: '?쒕쾲',
                className: 'text-center journal-sort-no-cell',
                render(data, type, row) {
                    const sortNo = getVoucherSortNo(row);

                    if (type === 'sort' || type === 'type') {
                        return sortNo;
                    }

                    return escapeHtml(data || '');
                },
            },
            {
                data: 'voucher_no',
                title: '?꾪몴踰덊샇',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'voucher_date',
                title: '?꾪몴?쇱옄',
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'status',
                title: '?곹깭',
                defaultContent: '',
                render(data) {
                    return escapeHtml(translateStatus(data));
                },
            },
            {
                data: 'type',
                title: '???,
                defaultContent: '',
                render(data, type, row) {
                    return escapeHtml(translateType(data || row.ref_type));
                },
            },
            {
                data: 'summary_text',
                title: '?곸슂',
                className: 'journal-summary-cell',
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'updated_at',
                title: '?섏젙?쇱떆',
                name: 'updated_at',
                render(data, type, row) {
                    return escapeHtml(data || row.created_at || '');
                },
            },
            {
                data: 'account_code',
                title: '怨꾩젙怨쇰ぉ',
                className: 'no-colvis',
                visible: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'linked_status',
                title: '嫄곕옒?곌껐?щ?',
                className: 'no-colvis',
                visible: false,
                defaultContent: 'unlinked',
                render(data, type) {
                    if (type === 'sort' || type === 'type') {
                        return data || 'unlinked';
                    }

                    return escapeHtml(translateLinkedStatus(data));
                },
            },
            {
                data: null,
                title: '愿由?,
                className: 'text-center no-colvis',
                orderable: false,
                searchable: false,
                render(data, type, row) {
                    const id = escapeHtml(row.id || '');

                    return `
                        <button type="button"
                                class="btn btn-outline-primary btn-sm btn-edit-voucher"
                                data-id="${id}">?섏젙</button>
                        <button type="button"
                                class="btn btn-outline-danger btn-sm btn-delete-voucher"
                                data-id="${id}">??젣</button>
                    `;
                },
            },
        ];
    }

    function initExcelDataset() {
        const excelForm = document.getElementById('journalExcelForm');
        if (!excelForm) {
            return;
        }

        excelForm.dataset.templateUrl = API.excelTemplate;
        excelForm.dataset.downloadUrl = API.excelDownload;
        excelForm.dataset.uploadUrl = API.excelUpload;
    }

    function openExcelModal() {
        const excelModalEl = document.getElementById('journalExcelModal');

        if (!excelModalEl || !window.bootstrap) {
            notify('warning', '?꾪몴 ?묒? 愿由?紐⑤떖??李얠쓣 ???놁뒿?덈떎.');
            return;
        }

        const instance = bootstrap.Modal.getInstance(excelModalEl) || new bootstrap.Modal(excelModalEl, {
            focus: false,
        });

        instance.show();
    }

    function openTrashModal() {
        const trashModalEl = document.getElementById('journalTrashModal');
        if (!trashModalEl || !window.bootstrap) {
            notify('warning', '?꾪몴 ?댁???紐⑤떖??李얠쓣 ???놁뒿?덈떎.');
            return;
        }

        trashModalEl.dataset.listUrl = API.trash;
        trashModalEl.dataset.restoreUrl = API.restore;
        trashModalEl.dataset.deleteUrl = API.purge;
        trashModalEl.dataset.deleteAllUrl = API.purgeAll;

        const instance = bootstrap.Modal.getInstance(trashModalEl) || new bootstrap.Modal(trashModalEl, {
            focus: false,
        });

        instance.show();
    }

    function setupTrashColumns() {
        window.TrashColumns = window.TrashColumns || {};
        window.TrashColumns.journal = function (row) {
            return `
                <td>${escapeHtml(row.voucher_no ?? row.sort_no ?? '')}</td>
                <td>${escapeHtml(row.voucher_date ?? '')}</td>
                <td>${escapeHtml(row.summary_text ?? '')}</td>
                <td>${escapeHtml(row.deleted_at ?? '')}</td>
                <td>
                    <button class="btn btn-success btn-sm btn-restore" data-id="${escapeHtml(row.id ?? '')}">蹂듭썝</button>
                    <button class="btn btn-danger btn-sm btn-purge" data-id="${escapeHtml(row.id ?? '')}">?꾩쟾 ??젣</button>
                </td>
            `;
        };

        document.addEventListener('trash:changed', (event) => {
            const { type } = event.detail || {};
            if (type === 'journal') {
                reloadJournalTable();
            }
        });
    }

    function updateJournalCount() {
        if (!journalTable?.page) {
            return;
        }

        const countEl = document.getElementById('journalCount');
        if (!countEl) {
            return;
        }

        const info = journalTable.page.info();
        countEl.textContent = `珥?${info?.recordsDisplay ?? 0}嫄?;
    }

    

    function bindJournalTableEvents() {
        if (!window.jQuery || !journalTable) {
            return;
        }

        const $ = window.jQuery;
        $('#journal-table tbody')
            .off('click.journalSearchFill')
            .on('click.journalSearchFill', 'td', function (event) {
                if (event.target.closest('button, .reorder-handle')) {
                    return;
                }

                const cell = journalTable.cell(this);
                const cellIndex = cell.index();
                if (!cellIndex) {
                    return;
                }

                const field = journalTable.column(cellIndex.column).dataSrc();
                if (!field || typeof field !== 'string') {
                    return;
                }

                const value = cell.data();
                const $first = $('#journalSearchConditions .search-condition').first();
                $first.find('select').val(field);
                $first.find('input').val(value ?? '');
            });

        $('#journal-table tbody')
            .off('dblclick.journalEdit')
            .on('dblclick.journalEdit', 'tr', function (event) {
                if (event.target.closest('button, .reorder-handle')) {
                    return;
                }

                const row = journalTable.row(this).data();
                const id = row?.id || '';
                if (!id) {
                    return;
                }

                resetModal();
                void loadDetail(id);
            });
    }

    function initJournalTable() {
        if (journalTable || !window.jQuery?.fn?.DataTable) {
            return Boolean(journalTable);
        }

        journalTable = createDataTable({
            tableSelector: '#journal-table',
            api: API.list,
            columns: buildJournalColumns(),
            buttons: [
                {
                    text: '?묒?愿由?,
                    className: 'btn btn-success btn-sm',
                    action: function () {
                        openExcelModal();
                    },
                },
                {
                    text: '?댁???,
                    className: 'btn btn-danger btn-sm',
                    action: function () {
                        openTrashModal();
                    },
                },
                {
                    text: '?덉쟾??,
                    className: 'btn btn-warning btn-sm',
                    action: function () {
                        void openCreateModal();
                    },
                },
            ],
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
        });

        bindRowReorder(journalTable, { api: API.reorder });
        bindTableHighlight('#journal-table', journalTable);

        journalTable.on('init.dt draw.dt', updateJournalCount);

        SearchForm({
            table: journalTable,
            apiList: API.list,
            tableId: 'journal',
            defaultSearchField: 'summary_text',
            dateOptions: JOURNAL_DATE_OPTIONS,
        });
        bindJournalTableEvents();
        updateJournalCount();

        return Boolean(journalTable);
    }

    function reloadJournalTable() {
        if (journalTable?.ajax) {
            journalTable.ajax.reload(null, false);
        }
    }

    async function openCreateModal() {
        resetModal();
        await addLineRow();
        await addLineRow();
        modal?.show();
    }

    async function loadDetail(id) {
        try {
            setModalTitle('edit');
            const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);

            if (!json.success || !json.data) {
                notify('error', json.message || '?꾪몴 ?곸꽭 ?뺣낫瑜?遺덈윭?ㅼ? 紐삵뻽?듬땲??');
                return;
            }

            const data = json.data;
            document.getElementById('journal_id').value = data.id || '';
            voucherDateEl.value = data.voucher_date || '';
            voucherStatusEl.value = data.status || 'draft';
            if (voucherTypeEl) {
                voucherTypeEl.value = data.ref_type || data.type || 'MANUAL';
            }
            document.getElementById('voucher_summary_text').value = data.summary_text || '';
            document.getElementById('voucher_note').value = data.note || '';
            document.getElementById('voucher_memo').value = data.memo || '';
            setLinkedTransaction(data.linked_transaction || null);

            lineBody.innerHTML = emptyLineRow();
            if (Array.isArray(data.lines) && data.lines.length > 0) {
                for (const line of data.lines) {
                    await addLineRow(line);
                }
            } else {
                await addLineRow();
                await addLineRow();
            }

            calculateTotals();
            modal?.show();
        } catch (error) {
            console.error('[ledger-journal] loadDetail failed', error);
            setModalTitle('create');
            notify('error', '?꾪몴 ?곸꽭 ?뺣낫瑜?遺덈윭?ㅼ? 紐삵뻽?듬땲??');
        }
    }

    async function saveVoucher() {
        const formData = new FormData(form);
        formData.set('lines', JSON.stringify(collectLines()));
        formData.set('linked_transaction_id', linkedTransactionIdEl?.value || '');

        const json = await fetchJson(API.save, {
            method: 'POST',
            body: formData,
        });

        if (!json.success) {
            notify('error', json.message || '?꾪몴 ??μ뿉 ?ㅽ뙣?덉뒿?덈떎.');
            return;
        }

        notify('success', '?꾪몴媛 ??λ릺?덉뒿?덈떎.');
        modal?.hide();
        reloadJournalTable();
    }

    async function deleteVoucher(id) {
        const formData = new FormData();
        formData.append('id', id);

        const json = await fetchJson(API.remove, {
            method: 'POST',
            body: formData,
        });

        if (!json.success) {
            notify('error', json.message || '?꾪몴 ??젣???ㅽ뙣?덉뒿?덈떎.');
            return;
        }

        notify('success', '?꾪몴媛 ??젣?섏뿀?듬땲??');
        modal?.hide();
        reloadJournalTable();
    }

    function renderTransactionSearchRows(rows = []) {
        if (!transactionSearchBody) {
            return;
        }

        if (!rows.length) {
            transactionSearchBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">?좏깮??嫄곕옒媛 ?놁뒿?덈떎.</td>
                </tr>
            `;
            return;
        }

        transactionSearchBody.innerHTML = rows.map((row, index) => `
            <tr data-index="${index}">
                <td>${escapeHtml(row.transaction_date || '')}</td>
                <td>${escapeHtml(row.client_name || '-')}</td>
                <td>${escapeHtml(row.item_summary || row.description || row.sort_no || '')}</td>
                <td class="text-end">${escapeHtml(formatAmountValue(row.total_amount || 0) || '0')}</td>
                <td class="text-center">
                    <button type="button"
                            class="btn btn-outline-primary btn-sm btn-pick-transaction">?좏깮</button>
                </td>
            </tr>
        `).join('');
    }

    async function loadTransactionSearch() {
        if (!transactionSearchBody) {
            return;
        }

        transactionSearchBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted py-4">嫄곕옒瑜?遺덈윭?ㅻ뒗 以묒엯?덈떎.</td>
            </tr>
        `;

        try {
            const query = new URLSearchParams();
            const keyword = transactionSearchKeywordEl?.value?.trim() || '';
            if (keyword) {
                query.set('q', keyword);
            }

            const json = await fetchJson(`${API.transactionSearch}?${query.toString()}`);
            if (!json.success) {
                throw new Error(json.message || '嫄곕옒 紐⑸줉??遺덈윭?ㅼ? 紐삵뻽?듬땲??');
            }

            transactionRows = Array.isArray(json.data) ? json.data : [];
            renderTransactionSearchRows(transactionRows);
        } catch (error) {
            console.error('[ledger-journal] transaction search failed', error);
            transactionRows = [];
            transactionSearchBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger py-4">嫄곕옒 紐⑸줉??遺덈윭?ㅼ? 紐삵뻽?듬땲??</td>
                </tr>
            `;
        }
    }

    function bindEvents() {
        addLineBtn?.addEventListener('click', () => {
            void addLineRow();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            calculateTotals();

            if (!validateBeforeSave()) {
                return;
            }

            void saveVoucher();
        });

        lineBody.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-remove-line');
            if (!button) {
                return;
            }

            button.closest('tr')?.remove();

            if (!lineBody.querySelector('tr')) {
                lineBody.innerHTML = emptyLineRow();
            }

            syncLineNumbers();
            calculateTotals();
        });

        document.addEventListener('input', (event) => {
            const input = getAmountInputTarget(event.target);
            if (!input) {
                return;
            }

            setAmountInputValue(input);
            calculateTotals();
        });

        document.addEventListener('focusin', (event) => {
            const input = getAmountInputTarget(event.target);
            if (!input) {
                return;
            }

            setAmountInputValue(input, { formatted: false });
        });

        document.addEventListener('focusout', (event) => {
            const input = getAmountInputTarget(event.target);
            if (!input) {
                return;
            }

            setAmountInputValue(input);
            calculateTotals();
        });

        journalTableEl.addEventListener('click', (event) => {
            const editBtn = event.target.closest('.btn-edit-voucher');
            const deleteBtn = event.target.closest('.btn-delete-voucher');

            if (editBtn?.dataset.id) {
                resetModal();
                void loadDetail(editBtn.dataset.id);
                return;
            }

            if (deleteBtn?.dataset.id && window.confirm('?꾪몴瑜???젣?섏떆寃좎뒿?덇퉴?')) {
                void deleteVoucher(deleteBtn.dataset.id);
            }
        });

        selectTransactionBtn?.addEventListener('click', () => {
            if (!transactionModal) {
                notify('warning', '嫄곕옒 ?좏깮 紐⑤떖??李얠쓣 ???놁뒿?덈떎.');
                return;
            }

            if (transactionSearchKeywordEl) {
                transactionSearchKeywordEl.value = '';
            }

            transactionModal.show();
            void loadTransactionSearch();
        });

        clearTransactionLinkBtn?.addEventListener('click', () => {
            setLinkedTransaction(null);
        });

        searchTransactionBtn?.addEventListener('click', () => {
            void loadTransactionSearch();
        });

        transactionSearchKeywordEl?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                void loadTransactionSearch();
            }
        });

        transactionSearchBody?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-pick-transaction');
            if (!button) {
                return;
            }

            const rowEl = button.closest('tr');
            const index = Number(rowEl?.dataset.index ?? -1);
            const row = transactionRows[index];
            if (!row) {
                return;
            }

            setLinkedTransaction(row);
            transactionModal?.hide();
        });
    }

    function boot() {
        initExcelDataset();
        setupTrashColumns();
        initJournalTable();
        bindDatePickerInput();
        bindEvents();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();



