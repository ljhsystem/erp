import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { manageButtonRenderer } from '/public/assets/js/common/table/renderers/index.js';
import { actorColumn } from '/public/assets/js/common/actor.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { createAgGridInputAdapter } from '/public/assets/js/common/grid/ag-grid-input.js';
import { AgGridSelect2CellEditor } from '/public/assets/js/common/grid/ag-grid-cell-editors.js';
import { editableFieldNames } from '/public/assets/js/common/grid/ag-grid-navigation.js';
import { createExcelManagerSettingsCore } from '/public/assets/js/components/excel-manager/index.js';
import { createApprovalRenderer } from './approval-renderer.js';
import { initPersonalExpenseExcelManager } from './excel-manager.js';
import { createPersonalExpenseListColumns, createPersonalExpenseTrashPresenter } from './list-columns.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { checkBusinessStatus } from '/public/assets/js/common/biz_api.js';
import { formatBizNumber, formatNumber, formatPhone } from '/public/assets/js/common/format.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';
import { runDeleteProgress } from '/public/assets/js/common/delete-progress.js';

const API = {
    list: '/api/approval/personal-expense/list',
    detail: '/api/approval/personal-expense/detail',
    save: '/api/approval/personal-expense/save',
    saveSubmit: '/api/approval/personal-expense/save-submit',
    delete: '/api/approval/personal-expense/delete',
    trash: '/api/approval/personal-expense/trash',
    restore: '/api/approval/personal-expense/restore',
    purge: '/api/approval/personal-expense/purge',
    reorder: '/api/approval/personal-expense/reorder',
    withdraw: '/api/approval/personal-expense/withdraw',
    excelTemplate: '/api/approval/personal-expense/template',
    excelDownload: '/api/approval/personal-expense/excel',
    excelUpload: '/api/approval/personal-expense/excel-upload',
    tableColumns: '/api/settings/system/data-table-columns',
    clientSearch: '/api/settings/base-info/client/search-picker',
    projectSearch: '/api/settings/base-info/project/search-picker',
};
const STATUS = {
    draft: ['작성중', 'text-bg-secondary'],
    pending: ['결재요청', 'text-bg-info'],
    in_progress: ['승인진행', 'text-bg-primary'],
    approved: ['최종승인', 'text-bg-success'],
    rejected: ['반려', 'text-bg-danger'],
    withdrawn: ['회수', 'text-bg-warning'],
};
const EDITABLE = new Set(['draft', 'rejected', 'withdrawn']);
const form = document.getElementById('expenseForm');
const modalElement = document.getElementById('expenseModal');
const modal = modalElement && window.bootstrap ? new window.bootstrap.Modal(modalElement) : null;
const calculatorModalElement = document.getElementById('expenseAmountCalculatorModal');
const calculatorModal = calculatorModalElement && window.bootstrap
    ? new window.bootstrap.Modal(calculatorModalElement, { focus: false })
    : null;
const TABLE_SETTINGS_STORAGE_KEY = 'datatable.settings.approval.personal-expense.v2';
const HEADER_META_DOMAIN = 'personal-expense';
const HEADER_CARD_FIELDS = new Set([
    'application_date', 'title', 'description', 'memo',
]);
const HEADER_FIELD_POLICIES = Object.freeze([
    { key: 'application_date', fallback: '신청일자' },
    { key: 'title', fallback: '신청제목' },
    { key: 'description', fallback: '비고' },
    { key: 'memo', fallback: '메모' },
]);
let table;
let itemGrid;
let current = null;
let busy = false;
let readOnlyMode = false;
let headerMetaRows = null;
let headerMetaPromise = null;
let modalBaselineSnapshot = '';
let allowModalClose = false;
let expenseGridEscapeHandler = null;
let expenseEditingCell = null;
let expenseDatePicker = null;
let calculatorRowContext = null;
const codeOptions = JSON.parse(document.getElementById('personalExpenseCodeOptions')?.textContent || '{}');
const unitOptions = (codeOptions.units || []).map(option => {
    const label = String(option.code_name || option.code || '').trim();
    return { id: label, name: label };
}).filter(option => option.id !== '');
const currentEmployee = JSON.parse(document.getElementById('personalExpenseCurrentEmployee')?.textContent || '{}');
const systemInfoToggle = document.querySelector('.expense-system-toggle');
const systemInfoCollapseElement = document.getElementById('expenseSystemInfoCollapse');
const approvalInfoToggle = document.querySelector('.expense-approval-toggle');
const approvalInfoCollapseElement = document.getElementById('expenseApprovalCollapse');
const applicationDateInput = form?.elements.application_date;

function formatPickerDate(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function initExpenseDatePicker() {
    if (expenseDatePicker) return expenseDatePicker;
    const container = document.getElementById('expense-date-picker');
    if (!container) return null;

    expenseDatePicker = AdminPicker.create({ type: 'today', container });
    expenseDatePicker.subscribe((_, date) => {
        if (!applicationDateInput || !date) return;
        applicationDateInput.value = formatPickerDate(date);
        applicationDateInput.dispatchEvent(new Event('input', { bubbles: true }));
        applicationDateInput.dispatchEvent(new Event('change', { bubbles: true }));
        expenseDatePicker.close();
    });
    return expenseDatePicker;
}

function openExpenseDatePicker() {
    if (!applicationDateInput) return;
    const picker = initExpenseDatePicker();
    if (!picker) return;

    if (typeof picker.clearDate === 'function') picker.clearDate();
    if (/^\d{4}-\d{2}-\d{2}$/.test(applicationDateInput.value)) {
        const [year, month, day] = applicationDateInput.value.split('-').map(Number);
        picker.setDate(new Date(year, month - 1, day));
    }
    picker.open({ anchor: applicationDateInput });
}

applicationDateInput?.addEventListener('input', () => {
    const digits = applicationDateInput.value.replace(/\D/g, '').slice(0, 8);
    applicationDateInput.value = [
        digits.slice(0, 4),
        digits.slice(4, 6),
        digits.slice(6, 8),
    ].filter(Boolean).join('-');
});
applicationDateInput?.closest('.date-input')?.addEventListener('click', event => {
    event.preventDefault();
    openExpenseDatePicker();
});

class AddItemHeader {
    init(params) {
        this.params = params;
        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.tabIndex = -1;
        this.button.className = 'btn btn-link btn-sm text-primary p-0';
        this.button.textContent = '+추가';
        this.button.setAttribute('aria-label', '경비 아이템 행 추가');
        this.button.addEventListener('click', () => {
            if (readOnlyMode) return;
            const scrollContainer = this.button.closest('.modal-body');
            const scrollTop = scrollContainer?.scrollTop ?? 0;
            itemGrid?.addRow?.(blankItem());
            refreshSummary();
            if (scrollContainer) {
                scrollContainer.scrollTop = scrollTop;
                requestAnimationFrame(() => {
                    scrollContainer.scrollTop = scrollTop;
                });
            }
        });
        this.button.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                event.stopPropagation();
                params.api?.setFocusedCell?.(0, '__actions');
                return;
            }
            if (['ArrowLeft', 'ArrowRight', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }
    getGui() { return this.button; }
}

const money = value => Number(value || 0).toLocaleString('ko-KR', { maximumFractionDigits: 2 });
const number = value => Number(String(value ?? '').replaceAll(',', '')) || 0;
const statusKey = value => String(value || 'draft').toLowerCase();
const normalizeExpenseDate = value => {
    const raw = String(value ?? '').trim();
    const digits = raw.replace(/\D/g, '');
    if (digits.length !== 8) return raw;
    return `${digits.slice(0, 4)}-${digits.slice(4, 6)}-${digits.slice(6, 8)}`;
};

const formatExpenseDateInput = value => {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 8);
    return [
        digits.slice(0, 4),
        digits.slice(4, 6),
        digits.slice(6, 8),
    ].filter(Boolean).join('-');
};

class ExpenseDateCellEditor {
    init(params) {
        this.container = document.createElement('div');
        this.container.className = 'expense-date-cell-editor';

        this.textInput = document.createElement('input');
        this.textInput.type = 'text';
        this.textInput.inputMode = 'numeric';
        this.textInput.maxLength = 10;
        this.textInput.className = 'expense-date-cell-text';
        this.textInput.value = formatExpenseDateInput(params.value);

        this.dateInput = document.createElement('input');
        this.dateInput.type = 'date';
        this.dateInput.className = 'expense-date-cell-picker';
        this.dateInput.setAttribute('aria-label', '지출일자 달력에서 선택');
        this.dateInput.value = isValidDate(this.textInput.value) ? this.textInput.value : '';

        this.textInput.addEventListener('input', () => {
            this.textInput.value = formatExpenseDateInput(this.textInput.value);
            this.dateInput.value = isValidDate(this.textInput.value) ? this.textInput.value : '';
        });
        this.dateInput.addEventListener('change', () => {
            if (!this.dateInput.value) return;
            this.textInput.value = this.dateInput.value;
            this.textInput.focus();
        });

        this.container.append(this.textInput, this.dateInput);
    }

    getGui() {
        return this.container;
    }

    afterGuiAttached() {
        this.textInput.focus();
        this.textInput.select();
    }

    getValue() {
        return normalizeExpenseDate(this.textInput.value);
    }
}

class BusinessNumberCellEditor {
    init(params) {
        this.container = document.createElement('div');
        this.container.className = 'expense-business-number-editor';

        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.inputMode = 'numeric';
        this.input.maxLength = 12;
        this.input.className = 'expense-business-number-input';
        this.input.value = formatBizNumber(params.value);

        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.className = 'expense-business-number-check';
        this.button.textContent = '조회';
        this.button.title = '사업자 상태 조회';

        this.input.addEventListener('input', () => {
            this.input.value = formatBizNumber(this.input.value);
        });
        this.button.addEventListener('mousedown', event => {
            event.preventDefault();
            event.stopPropagation();
        });
        this.button.addEventListener('click', async event => {
            event.preventDefault();
            event.stopPropagation();
            await this.checkStatus();
        });

        this.container.append(this.input, this.button);
    }

    async checkStatus() {
        const businessNumber = this.input.value.replace(/\D/g, '');
        if (businessNumber.length !== 10) {
            notify('warning', '사업자등록번호 10자리를 입력해 주세요.');
            this.input.focus();
            return;
        }

        this.button.disabled = true;
        this.button.textContent = '조회중';
        try {
            const result = await checkBusinessStatus(businessNumber);
            const info = result?.data?.data?.[0];
            if (result?.data?.status_code !== 'OK') {
                notify('error', '사업자 상태 조회에 실패했습니다.');
                return;
            }
            if (!info) {
                notify('warning', '사업자 상태 조회 결과가 없습니다.');
                return;
            }

            const status = String(info.b_stt || '').trim();
            const taxType = String(info.tax_type || '').trim();
            const message = [status, taxType].filter(Boolean).join(' · ')
                || '사업자 상태를 확인했습니다.';
            notify(status.includes('계속') || status.includes('정상') ? 'success' : 'warning', message);
        } catch (error) {
            notify('error', '사업자 상태 조회에 실패했습니다.');
        } finally {
            this.button.disabled = false;
            this.button.textContent = '조회';
            this.input.focus();
        }
    }

    getGui() {
        return this.container;
    }

    afterGuiAttached() {
        this.input.focus();
        this.input.select();
    }

    getValue() {
        return this.input.value.replace(/\D/g, '');
    }
}

class AddressCellEditor {
    init(params) {
        this.params = params;
        this.container = document.createElement('div');
        this.container.className = 'expense-address-editor';

        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'expense-address-input';
        this.input.value = String(params.value || '');
        const editorId = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
        this.input.id = `expense-address-${editorId.replace(/[^a-zA-Z0-9_-]/g, '')}`;

        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.className = 'expense-address-search';
        this.button.textContent = '검색';
        this.button.title = '주소 검색';

        this.input.addEventListener('change', () => {
            params.data.merchant_address = this.input.value;
            params.api?.refreshCells?.({
                rowNodes: [params.node],
                columns: ['merchant_address'],
                force: true,
            });
        });
        this.button.addEventListener('mousedown', event => {
            event.preventDefault();
            event.stopPropagation();
        });
        this.button.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            if (!window.KakaoAddress?.open) {
                notify('error', '주소검색을 불러올 수 없습니다.');
                return;
            }
            window.KakaoAddress.open({ address: `#${this.input.id}` });
        });

        this.container.append(this.input, this.button);
    }

    getGui() {
        return this.container;
    }

    afterGuiAttached() {
        this.input.focus();
        this.input.select();
    }

    getValue() {
        return this.input.value;
    }
}

class CodeOptionCellEditor {
    init(params) {
        this.params = params;
        this.select = document.createElement('select');
        this.select.className = 'form-select form-select-sm expense-code-option-editor';

        this.select.appendChild(new Option('선택(없음)', '', false, String(params.value ?? '') === ''));
        for (const option of params.options || []) {
            const value = String(option.code ?? option.id ?? '');
            if (value === '') continue;
            const label = String(option.code_name ?? option.name ?? value);
            this.select.appendChild(new Option(label, value, false, value === String(params.value ?? '')));
        }

        this.select.addEventListener('keydown', event => {
            if (['ArrowUp', 'ArrowDown', 'Home', 'End', ' '].includes(event.key)) {
                event.stopPropagation();
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                event.stopPropagation();
                params.stopEditing();
                return;
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                params.stopEditing(true);
            }
        });
    }

    getGui() {
        return this.select;
    }

    afterGuiAttached() {
        this.select.focus();
        try {
            this.select.showPicker?.();
        } catch (error) {
            this.select.focus();
        }
    }

    getValue() {
        return this.select.value;
    }
}

const statusLabel = value => (STATUS[statusKey(value)] || [value || '작성중'])[0];
const statusBadge = value => {
    const [label, className] = STATUS[statusKey(value)] || [value || '작성중', 'text-bg-secondary'];
    return `<span class="badge ${className}">${label}</span>`;
};
const notify = (type, message) => {
    if (window.showToast) window.showToast(type, message);
    else window.alert(message);
};
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);

function setCalculatorInputValue(id, value) {
    const input = document.getElementById(id);
    if (input) input.value = formatNumber(value);
}

function calculateSupplyAndVat() {
    const total = number(document.getElementById('expenseCalculatorTotal')?.value);
    const supply = Math.round(total / 1.1);
    setCalculatorInputValue('expenseCalculatorSupply', supply);
    setCalculatorInputValue('expenseCalculatorVat', total - supply);
}

function openAmountCalculator(params) {
    if (!calculatorModal) return;
    modal?._focustrap?.deactivate?.();
    calculatorRowContext = {
        data: params.data,
        node: params.node,
        api: params.api,
    };
    setCalculatorInputValue('expenseCalculatorTotal', params.data.item_total_amount);
    setCalculatorInputValue('expenseCalculatorSupply', params.data.item_supply_amount);
    setCalculatorInputValue('expenseCalculatorVat', params.data.item_vat_amount);
    calculatorModal.show();
}

document.getElementById('expenseCalculatorRun')?.addEventListener('click', calculateSupplyAndVat);
document.getElementById('expenseCalculatorTotal')?.addEventListener('keydown', event => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    calculateSupplyAndVat();
});
['expenseCalculatorTotal', 'expenseCalculatorSupply', 'expenseCalculatorVat'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', event => {
        event.target.value = formatNumber(event.target.value);
    });
});
document.getElementById('expenseCalculatorApply')?.addEventListener('click', () => {
    if (!calculatorRowContext) return;
    const supply = number(document.getElementById('expenseCalculatorSupply')?.value);
    const vat = number(document.getElementById('expenseCalculatorVat')?.value);
    calculatorRowContext.data.item_supply_amount = supply;
    calculatorRowContext.data.item_vat_amount = vat;
    calculatorRowContext.data.item_total_amount = supply + vat;
    const quantity = number(calculatorRowContext.data.item_quantity);
    if (quantity > 0) {
        calculatorRowContext.data.item_unit_price = Math.round((supply / quantity) * 100) / 100;
    }
    calculatorRowContext.api?.refreshCells?.({
        rowNodes: [calculatorRowContext.node],
        columns: ['item_unit_price', 'item_supply_amount', 'item_vat_amount', 'item_total_amount'],
        force: true,
    });
    refreshSummary();
    calculatorModal.hide();
});
calculatorModalElement?.addEventListener('hidden.bs.modal', () => {
    calculatorRowContext = null;
    modal?._focustrap?.activate?.();
});
calculatorModalElement?.addEventListener('shown.bs.modal', () => {
    document.getElementById('expenseCalculatorTotal')?.focus();
});

function metaField(meta = {}) {
    return String(meta.column || meta.key || meta.system_field_name || '').trim();
}

function systemInfoValue(field, header = {}) {
    if (field === 'employee_id') {
        return header.employee_name || currentEmployee.employee_name || '-';
    }
    if (/_by$/.test(field)) {
        return header[`${field}_name`] || header[field] || '-';
    }
    if (field === 'document_status') {
        return statusLabel(header.approval_status || header.document_status);
    }
    if (field === 'current_approval_request_id') {
        return Object.prototype.hasOwnProperty.call(header, 'approval_stage_name')
            ? (header.approval_stage_name || '-')
            : (header.current_step_name || (header.current_approval_request_id ? '결재 진행정보' : '-'));
    }
    const value = header[field];
    if (value === null || value === undefined || String(value).trim() === '') return '-';
    return String(value);
}

async function loadHeaderMetaRows() {
    if (Array.isArray(headerMetaRows)) return headerMetaRows;
    if (!headerMetaPromise) {
        headerMetaPromise = request(`${API.tableColumns}?domain=${encodeURIComponent(HEADER_META_DOMAIN)}`)
            .then(result => {
                headerMetaRows = Array.isArray(result.data) ? result.data : [];
                return headerMetaRows;
            })
            .finally(() => { headerMetaPromise = null; });
    }
    return headerMetaPromise;
}

async function renderSystemInfo(header = {}) {
    const container = document.getElementById('expenseSystemInfoFields');
    if (!container) return;
    try {
        const state = currentHeaderPolicyState();
        const rows = await loadHeaderMetaRows();
        container.innerHTML = rows
            .filter(meta => {
                const field = metaField(meta);
                return field !== '' && !HEADER_CARD_FIELDS.has(field);
            })
            .map(meta => {
                const field = metaField(meta);
                const label = resolveDataTableColumnDisplayName(
                    { ...meta, key: field, system_field_name: field, original_column_key: field },
                    state,
                    meta.label || field
                );
                return `<label class="expense-field">
                    <span class="form-label">${escapeHtml(label)}</span>
                    <input class="form-control form-control-sm" value="${escapeHtml(systemInfoValue(field, header))}" readonly>
                </label>`;
            })
            .join('');
    } catch {
        container.innerHTML = '<p class="expense-system-empty">시스템 처리 정보를 불러올 수 없습니다.</p>';
    }
}

async function request(url, options = {}) {
    const response = await fetch(url, { headers: { 'Content-Type': 'application/json' }, ...options });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.success === false) throw new Error(result.message || '요청 처리 중 오류가 발생했습니다.');
    return result;
}

function columns() {
    return createPersonalExpenseListColumns({ actorColumn, escapeHtml, manageButtonRenderer, money, statusBadge });
}

async function initTable() {
    table = await createDataTable({
        tableSelector: '#personal-expense-table', api: API.list, columns: columns(),
        serverSide: true, pageLength: 100, defaultOrder: [[3, 'asc']], autoWidth: false,
        searchTableId: 'personalExpense', deleteApi: API.delete, bulkDelete: true,
        isRowSelectable: row => EDITABLE.has(statusKey(row.approval_status)),
        widthScopeSelector: '.personal-expense-page',
        tableSettings: {
            enabled: true, pageKey: 'approval.personal_expense', userSettingPageKey: 'approval.personal_expense',
            tableKey: 'personal-expense-table', storageKey: 'datatable.settings.approval.personal-expense.v2',
            metaDomain: 'personal-expense', tableLabel: '개인경비 신청', title: '개인경비 신청 테이블 설정',
        },
        buttons: [
            { text: '휴지통', className: 'btn btn-danger btn-sm dt-trash-btn', action: openTrash },
            { text: '신규등록', className: 'btn btn-warning btn-sm', action: openNew },
        ],
    });
    bindRowReorder(table, {
        api: API.reorder,
        onSuccess: () => {
            notify('success', '순서가 변경되었습니다.');
            table?.ajax?.reload(null, false);
        },
        onError: result => {
            notify('error', result?.message || '정렬 저장 중 오류가 발생했습니다.');
            table?.ajax?.reload(null, false);
        },
    });
    SearchForm({ table, apiList: API.list, tableId: 'personalExpense', defaultSearchField: 'keyword', dateOptions: [{ value: 'application_date', label: '신청일자' }] });
    bindTableHighlight('#personal-expense-table', table);
    document.querySelector('#personal-expense-table tbody')?.addEventListener('click', event => {
        const button = event.target.closest('.dt-manage-edit-btn');
        if (button) openDetail(button.dataset.id);
    });
    window.jQuery?.('#personal-expense-table tbody').on('dblclick', 'tr', function () {
        const row = table?.row(this).data();
        if (row?.id) openDetail(row.id);
    });
    table.on('draw.dt xhr.dt', updateCount);
}

function openTrash() {
    const element = document.getElementById('personalExpenseTrashModal');
    if (!element || !window.bootstrap) return;
    window.bootstrap.Modal.getOrCreateInstance(element).show();
}

const {
    columns: trashColumns,
    renderDetail: renderTrashDetail,
} = createPersonalExpenseTrashPresenter({ escapeHtml, money, status: STATUS, statusKey });

function blankItem() {
    return {
        __rowId: `new:${window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`}`,
        id: '', sort_no: 0, expense_date: new Date().toLocaleDateString('sv-SE'), expense_category: '',
        payment_method: '', receipt_type: '', merchant_name: '', merchant_business_no: '',
        merchant_representative: '', merchant_address: '', merchant_address_detail: '',
        merchant_phone: '', project_id: '', client_id: '', item_name: '', item_specification: '',
        item_unit_name: '', item_quantity: 1, item_unit_price: 0, item_supply_amount: 0,
        item_vat_amount: 0, item_total_amount: 0, item_description: '', item_memo: '',
    };
}

function prepareItemRows(rows = []) {
    return (Array.isArray(rows) ? rows : []).map(row => ({
        ...row,
        __rowId: row.__rowId || (row.id
            ? `saved:${row.id}`
            : `new:${window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`}`),
    }));
}

function itemColumns() {
    const text = (field, headerName, width = 130) => ({ field, headerName, editable: true, width });
    const numeric = (field, headerName, width = 110) => ({
        field, headerName, editable: true, width, type: 'numericColumn',
        valueParser: params => number(params.newValue),
        valueFormatter: params => money(params.value),
    });
    const select = (field, headerName, options, width = 130) => ({
        field, headerName, editable: true, width,
        cellEditor: CodeOptionCellEditor,
        cellEditorParams: {
            options,
        },
        valueFormatter: params => {
            const selected = options.find(option => String(option.code ?? option.id ?? '') === String(params.value ?? ''));
            return selected?.code_name ?? selected?.name ?? '';
        },
    });
    return [
        { field: '__drag', headerName: '', width: 42, editable: false, rowDrag: true, pinned: 'left', suppressHeaderMenuButton: true },
        { field: 'sort_no', headerName: '순번', width: 68, editable: false, pinned: 'left', valueGetter: params => (params.node?.rowIndex ?? 0) + 1 },
        {
            ...text('expense_date', '지출일자', 125),
            cellEditor: ExpenseDateCellEditor,
        },
        select('expense_category', '경비구분', codeOptions.expense_categories || [], 125),
        select('payment_method', '지출수단', codeOptions.payment_methods || [], 135),
        select('receipt_type', '증빙종류', codeOptions.receipt_types || [], 130),
        {
            field: 'project_id', headerName: '프로젝트', editable: true, width: 180,
            cellEditor: 'agGridSelect2CellEditor',
            cellEditorParams: params => ({
                url: API.projectSearch, minimumInputLength: 0, popupParent: modalElement,
                currentText: () => params.data?.project_name || '선택(없음)',
                onSelected: item => {
                    params.data.project_name = String(item?.id ?? '') === '' ? '' : (item?.text || '');
                },
            }),
            valueFormatter: params => params.data?.project_name || '',
        },
        {
            field: 'client_id', headerName: '거래처', editable: true, width: 190,
            cellEditor: 'agGridSelect2CellEditor',
            cellEditorParams: params => ({
                url: API.clientSearch, minimumInputLength: 0, popupParent: modalElement, tags: true,
                currentText: () => params.data?.client_name || params.data?.merchant_name || '선택(없음)',
                onSelected: item => {
                    if (String(item?.id ?? '') === '') {
                        Object.assign(params.data, {
                            client_name: '',
                            merchant_name: '',
                            merchant_business_no: '',
                            merchant_representative: '',
                            merchant_address: '',
                            merchant_address_detail: '',
                            merchant_phone: '',
                        });
                        return;
                    }
                    const directName = item?.isNew ? String(item.text || '').trim() : '';
                    params.data.client_name = directName === ''
                        ? (item?.client_name || item?.company_name || item?.text || '')
                        : '';
                    params.data.merchant_name = directName || params.data.client_name;
                    params.data.merchant_business_no = item?.business_number || '';
                    params.data.merchant_representative = item?.ceo_name || '';
                    params.data.merchant_address = item?.address || '';
                    params.data.merchant_address_detail = item?.address_detail || '';
                    params.data.merchant_phone = item?.phone || '';
                    params.api?.refreshCells?.({
                        rowNodes: [params.node],
                        columns: [
                            'merchant_name',
                            'merchant_business_no',
                            'merchant_representative',
                            'merchant_address',
                            'merchant_address_detail',
                            'merchant_phone',
                        ],
                        force: true,
                    });
                },
                resolveValue: (item, value) => item?.isNew ? '' : value,
            }),
            valueFormatter: params => params.data?.client_name || params.data?.merchant_name || '',
        },
        {
            ...text('merchant_business_no', '사업자등록번호', 180),
            cellEditor: BusinessNumberCellEditor,
            valueFormatter: params => formatBizNumber(params.value),
        },
        text('merchant_representative', '대표자', 100),
        {
            ...text('merchant_address', '기본주소', 240),
            cellEditor: AddressCellEditor,
        },
        text('merchant_address_detail', '상세주소', 160),
        {
            ...text('merchant_phone', '전화번호', 120),
            cellEditorParams: { useFormatter: true },
            valueFormatter: params => formatPhone(params.value),
            valueParser: params => String(params.newValue ?? '').replace(/\D/g, ''),
        },
        text('item_name', '품명', 150),
        text('item_specification', '규격', 100), select('item_unit_name', '단위', unitOptions, 90),
        {
            ...numeric('item_quantity', '수량', 90),
            valueFormatter: params => number(params.value) === 0 ? '' : money(params.value),
        },
        {
            ...numeric('item_unit_price', '단가', 110),
            valueFormatter: params => number(params.value) === 0 ? '' : money(params.value),
        },
        {
            field: '__amount_split', headerName: '분할', width: 68,
            minWidth: 68, maxWidth: 68, editable: false,
            cellClass: 'expense-amount-split-cell',
            suppressSizeToFit: true,
            cellRenderer: params => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'expense-amount-split-button';
                button.textContent = '계산';
                button.addEventListener('click', event => {
                    event.preventDefault();
                    event.stopPropagation();
                    openAmountCalculator(params);
                });
                return button;
            },
        },
        numeric('item_supply_amount', '공급가액', 115),
        numeric('item_vat_amount', '부가세', 105),
        {
            field: 'item_total_amount', headerName: '합계', width: 115,
            editable: false, type: 'numericColumn',
            valueFormatter: params => money(params.value),
        },
        text('item_description', '품목 비고', 150), text('item_memo', '품목 메모', 150),
        {
            field: '__actions',
            headerName: '+추가',
            headerComponent: 'addItemHeader',
            headerClass: 'expense-line-add-head',
            cellClass: 'expense-line-action-cell',
            width: 88,
            minWidth: 88,
            maxWidth: 88,
            editable: false,
            pinned: 'right',
            suppressSizeToFit: true,
            cellRenderer: () => '<button type="button" class="btn btn-link btn-sm text-danger p-0 expense-line-delete">-삭제</button>',
        },
    ];
}

function recalculateRow(row, changedField = '') {
    row.item_quantity = number(row.item_quantity);
    row.item_unit_price = number(row.item_unit_price);
    row.item_vat_amount = number(row.item_vat_amount);
    row.item_supply_amount = number(row.item_supply_amount);
    row.item_total_amount = number(row.item_total_amount);

    if (changedField === 'item_quantity' || changedField === 'item_unit_price') {
        row.item_vat_amount = 0;
    }

    const usesQuantityCalculation = row.item_quantity > 0 && row.item_unit_price > 0;
    if (usesQuantityCalculation) {
        row.item_supply_amount = Math.round(row.item_quantity * row.item_unit_price * 100) / 100;
        row.item_total_amount = Math.round((row.item_supply_amount + row.item_vat_amount) * 100) / 100;
        return;
    }
    row.item_total_amount = Math.round((row.item_supply_amount + row.item_vat_amount) * 100) / 100;
}

function refreshSummary() {
    const rows = itemGrid?.getData?.() || [];
    rows.forEach(recalculateRow);
    document.getElementById('expenseItemCount').textContent = rows.length;
    document.getElementById('expenseSupplyTotal').textContent = money(rows.reduce((sum, row) => sum + number(row.item_supply_amount), 0));
    document.getElementById('expenseVatTotal').textContent = money(rows.reduce((sum, row) => sum + number(row.item_vat_amount), 0));
    document.getElementById('expenseTotal').textContent = money(rows.reduce((sum, row) => sum + number(row.item_total_amount), 0));
}

function initItemGrid() {
    const gridHost = document.getElementById('expenseItemGrid');
    itemGrid = createAgGridInputAdapter(gridHost, {
        rowData: [], columnDefs: itemColumns(), autoFitColumns: false, domLayout: 'autoHeight',
        className: 'ag-theme-quartz', keyboardMode: 'excel-selection', useDefaultKeyboard: false,
        deleteColumnField: '__actions', deleteButtonSelector: '.expense-line-delete',
        onDeleteRow(event, adapter) {
            if (readOnlyMode) return true;
            return false;
        },
        gridOptions: {
            headerHeight: 40, rowHeight: 40, alwaysShowHorizontalScroll: true,
            singleClickEdit: false, suppressClickEdit: false, stopEditingWhenCellsLoseFocus: false, rowDragManaged: true,
            defaultColDef: {
                suppressKeyboardEvent: handleExpenseGridKeyboard,
                cellClassRules: {
                    'expense-grid-cell-invalid': params => Boolean(params.data?.__errors?.[params.colDef.field]),
                },
                tooltipValueGetter: params => params.data?.__errors?.[params.colDef.field] || '',
            },
            overlayNoRowsTemplate: '<span class="expense-grid-empty">데이터가 없습니다.</span>',
            getRowId: params => String(params.data.id || params.data.__rowId),
            popupParent: modalElement,
            components: {
                agGridSelect2CellEditor: AgGridSelect2CellEditor,
                addItemHeader: AddItemHeader,
            },
            navigateToNextCell(params) {
                return resolveExpenseArrowCell(params);
            },
            onCellEditingStarted(event) {
                bindExpenseGridEscape(event);
            },
            onCellEditingStopped() {
                unbindExpenseGridEscape();
            },
            tabToNextCell(params) {
                return resolveExpenseTabCell(params);
            },
            onRowDragEnd: refreshSummary,
        },
        onCellValueChanged(event, adapter) {
            recalculateRow(event.data, event.colDef?.field || '');
            adapter.api?.refreshCells?.({ rowNodes: [event.node], force: true });
        },
        onChanged: refreshSummary,
    });
    bindItemGridHorizontalWheel(gridHost);
    bindItemGridPaste(gridHost);
}

function handleExpenseGridKeyboard(params = {}) {
    const event = params.event;
    if (!event || readOnlyMode || itemGrid?.isEditing?.()) return false;
    const key = String(event.key || '');
    const rowIndex = Number(params.node?.rowIndex ?? params.rowIndex ?? 0);
    const field = String(params.column?.getColId?.() || '');

    if (key === 'Enter' && field === '__amount_split') {
        event.preventDefault();
        event.stopPropagation();
        openAmountCalculator(params);
        return true;
    }

    if (key === 'Enter' && field === '__actions') {
        event.preventDefault();
        event.stopPropagation();
        itemGrid.removeRows([rowIndex]);
        refreshSummary();
        return true;
    }

    if (key === 'Insert') {
        event.preventDefault();
        itemGrid.addRow(blankItem(), rowIndex + 1);
        itemGrid.startEditing(rowIndex + 1, 'expense_date');
        return true;
    }

    if (key === 'F2' && field) {
        event.preventDefault();
        itemGrid.startEditing(rowIndex, field);
        return true;
    }

    if ((key === 'Delete' || key === 'Backspace') && field && params.colDef?.editable === true) {
        event.preventDefault();
        if (field === 'project_id') {
            params.data.project_name = '';
        }
        if (field === 'client_id') {
            Object.assign(params.data, {
                client_name: '',
                merchant_name: '',
                merchant_business_no: '',
                merchant_representative: '',
                merchant_address: '',
                merchant_address_detail: '',
                merchant_phone: '',
            });
        }
        params.node?.setDataValue?.(field, '');
        params.api?.refreshCells?.({ rowNodes: [params.node], force: true });
        refreshSummary();
        return true;
    }

    const printable = key.length === 1 && !event.ctrlKey && !event.altKey && !event.metaKey;
    if (printable && field && params.colDef?.editable === true) {
        event.preventDefault();
        params.api?.startEditingCell?.({
            rowIndex,
            colKey: field,
            keyPress: key,
            charPress: key,
        });
        return true;
    }

    return false;
}

function resolveExpenseTabCell(params = {}) {
    if (params.nextCellPosition) return params.nextCellPosition;
    if (params.backwards || readOnlyMode) return params.previousCellPosition;

    const rowIndex = itemGrid?.countRows?.() || 0;
    itemGrid?.addRow?.(blankItem());
    const firstField = editableFieldNames(itemGrid?.columnDefs || [])[0];
    const column = params.api?.getColumn?.(firstField);
    return column
        ? { rowIndex, rowPinned: null, column }
        : params.previousCellPosition;
}

function resolveExpenseArrowCell(params = {}) {
    const previous = params.previousCellPosition || {};
    const key = String(params.key || '');
    const columns = itemGrid?.api?.getAllDisplayedColumns?.() || [];
    if (!columns.length || !previous.column) return params.nextCellPosition || previous;

    const currentColumnIndex = columns.findIndex(column => (
        column.getColId?.() === previous.column.getColId?.()
    ));
    if (currentColumnIndex < 0) return params.nextCellPosition || previous;

    const lastRowIndex = Math.max(0, (itemGrid?.countRows?.() || 1) - 1);
    let rowIndex = Math.max(0, Number(previous.rowIndex) || 0);
    let columnIndex = currentColumnIndex;

    if (key === 'ArrowUp') {
        if (rowIndex === 0 && previous.column.getColId?.() === '__actions') {
            window.requestAnimationFrame(() => {
                document.querySelector('#expenseItemGrid .expense-line-add-head button')?.focus();
            });
            return previous;
        }
        rowIndex = Math.max(0, rowIndex - 1);
    } else if (key === 'ArrowDown') {
        rowIndex = Math.min(lastRowIndex, rowIndex + 1);
    } else if (key === 'ArrowLeft') {
        if (columnIndex > 0) {
            columnIndex -= 1;
        } else if (rowIndex > 0) {
            rowIndex -= 1;
            columnIndex = columns.length - 1;
        }
    } else if (key === 'ArrowRight') {
        if (columnIndex < columns.length - 1) {
            columnIndex += 1;
        } else if (rowIndex < lastRowIndex) {
            rowIndex += 1;
            columnIndex = 0;
        }
    } else {
        return params.nextCellPosition || previous;
    }

    return {
        rowIndex,
        rowPinned: previous.rowPinned ?? null,
        column: columns[columnIndex],
    };
}

function bindExpenseGridEscape(event = {}) {
    expenseEditingCell = {
        rowIndex: Math.max(0, Number(event.rowIndex) || 0),
        field: String(event.colDef?.field || event.column?.getColId?.() || ''),
    };
    if (expenseGridEscapeHandler || !window.EscapeManager?.push) return;
    expenseGridEscapeHandler = () => {
        if (!itemGrid?.isEditing?.()) return false;
        const target = { ...expenseEditingCell };
        itemGrid.stopEditing(true);
        window.requestAnimationFrame(() => {
            if (target.field) itemGrid?.focusCell?.(target.rowIndex, target.field);
        });
        return true;
    };
    window.EscapeManager.push(expenseGridEscapeHandler);
}

function unbindExpenseGridEscape() {
    if (!expenseGridEscapeHandler) return;
    window.EscapeManager?.pop?.(expenseGridEscapeHandler);
    expenseGridEscapeHandler = null;
    expenseEditingCell = null;
}

function bindItemGridPaste(gridHost) {
    if (!gridHost || gridHost.dataset.expensePasteBound === 'true') return;
    gridHost.addEventListener('paste', event => {
        if (readOnlyMode || itemGrid?.isEditing?.()) return;
        const matrix = String(event.clipboardData?.getData('text/plain') || '')
            .replace(/\r/g, '')
            .split('\n')
            .filter((line, index, lines) => line !== '' || index < lines.length - 1)
            .map(line => line.split('\t'));
        if (!matrix.length || (matrix.length === 1 && matrix[0].length === 1)) return;
        event.preventDefault();
        const fields = editableFieldNames(itemGrid.columnDefs);
        const startField = itemGrid.focusedColumnId;
        const startColumn = Math.max(0, fields.indexOf(startField));
        const startRow = Math.max(0, itemGrid.focusedRowIndex);
        const rows = itemGrid.getData();
        matrix.forEach((values, rowOffset) => {
            const rowIndex = startRow + rowOffset;
            while (rows.length <= rowIndex) rows.push(blankItem());
            values.forEach((value, columnOffset) => {
                const field = fields[startColumn + columnOffset];
                if (!field) return;
                rows[rowIndex][field] = ['item_quantity', 'item_unit_price', 'item_vat_amount'].includes(field)
                    ? number(value)
                    : String(value).trim();
            });
            recalculateRow(rows[rowIndex]);
        });
        itemGrid.loadData(prepareItemRows(rows));
        refreshSummary();
    });
    gridHost.dataset.expensePasteBound = 'true';
}

function bindItemGridHorizontalWheel(gridHost) {
    if (!gridHost || gridHost.dataset.horizontalWheelBound === 'true') return;
    gridHost.addEventListener('wheel', event => {
        const viewport = gridHost.querySelector('.ag-body-horizontal-scroll-viewport');
        if (!viewport || viewport.scrollWidth <= viewport.clientWidth) return;

        const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
        if (!delta) return;

        const unit = event.deltaMode === WheelEvent.DOM_DELTA_LINE ? 40 : 1;
        event.preventDefault();
        event.stopPropagation();
        viewport.scrollLeft += delta * unit;
    }, { passive: false, capture: true });
    gridHost.dataset.horizontalWheelBound = 'true';
}

function payload() {
    itemGrid?.stopEditing?.();
    const rows = (itemGrid?.getData?.() || []).filter(row => !isBlankItem(row));
    rows.forEach(recalculateRow);
    return {
        id: form.elements.id.value,
        header: {
            application_date: form.elements.application_date.value,
            title: form.elements.title.value,
            description: form.elements.description.value,
            memo: form.elements.memo.value,
        },
        items: rows.map(({ __actions, __drag, __rowId, __errors, project_name, client_name, ...row }, index) => ({ ...row, sort_no: index + 1 })),
    };
}

function isBlankItem(row = {}) {
    return [
        'payment_method', 'expense_category', 'receipt_type', 'merchant_name', 'client_id',
        'project_id', 'item_name', 'item_description', 'item_memo',
    ].every(field => String(row[field] ?? '').trim() === '')
        && number(row.item_unit_price) === 0
        && number(row.item_vat_amount) === 0;
}

function isValidDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
    if (!match) return false;
    const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
    return date.getUTCFullYear() === Number(match[1])
        && date.getUTCMonth() === Number(match[2]) - 1
        && date.getUTCDate() === Number(match[3]);
}

function expenseModalSnapshot() {
    const rows = (itemGrid?.getData?.() || []).map(({
        __actions, __drag, __rowId, __errors, project_name, client_name, ...row
    }) => row);
    return JSON.stringify({
        id: form.elements.id.value || '',
        application_date: form.elements.application_date.value || '',
        title: form.elements.title.value || '',
        description: form.elements.description.value || '',
        memo: form.elements.memo.value || '',
        rows,
    });
}

function captureExpenseModalBaseline() {
    modalBaselineSnapshot = expenseModalSnapshot();
    allowModalClose = false;
}

function hasExpenseModalChanges() {
    return modalBaselineSnapshot !== '' && expenseModalSnapshot() !== modalBaselineSnapshot;
}

function confirmExpenseModalClose(event) {
    if (allowModalClose || !hasExpenseModalChanges()) return true;
    if (window.confirm('입력 중인 개인경비 내용이 있습니다. 저장하지 않고 닫으시겠습니까?')) {
        allowModalClose = true;
        return true;
    }
    event?.preventDefault?.();
    return false;
}

function currentHeaderPolicyState() {
    return readDataTableSettingsState(TABLE_SETTINGS_STORAGE_KEY, {
        userSettingPageKey: 'approval.personal_expense',
    }) || {};
}

function headerFieldPolicy(field, state = currentHeaderPolicyState()) {
    return resolveDataTableColumnRequirementPolicy(
        { key: field.key, system_field_name: field.key, original_column_key: field.key },
        state
    );
}

function headerFieldLabel(field, state = currentHeaderPolicyState()) {
    return resolveDataTableColumnDisplayName(
        { key: field.key, system_field_name: field.key, original_column_key: field.key },
        state,
        field.fallback
    );
}

function applyHeaderFieldPolicies() {
    const state = currentHeaderPolicyState();
    HEADER_FIELD_POLICIES.forEach(field => {
        const control = form.elements[field.key];
        const label = control?.closest('.expense-field')?.querySelector('.form-label');
        if (!label) return;
        const required = headerFieldPolicy(field, state) === 'required';
        label.innerHTML = `${escapeHtml(headerFieldLabel(field, state))}${required ? ' <span class="text-danger">*</span>' : ''}`;
    });
}

function validateHeaderFieldPolicies() {
    const state = currentHeaderPolicyState();
    for (const field of HEADER_FIELD_POLICIES) {
        if (headerFieldPolicy(field, state) !== 'required') continue;
        const control = form.elements[field.key];
        if (String(control?.value ?? '').trim() !== '') continue;
        notify('warning', `${headerFieldLabel(field, state)} 항목은 필수입니다.`);
        control?.focus?.();
        return false;
    }
    return true;
}

function validate() {
    itemGrid?.stopEditing?.();
    const rows = (itemGrid?.getData?.() || []).filter(row => !isBlankItem(row));
    if (!form.elements.application_date.value || !form.elements.title.value.trim()) {
        notify('warning', '신청일자와 신청제목을 입력해 주세요.');
        return false;
    }
    if (!validateHeaderFieldPolicies()) return false;
    if (!rows.length) {
        notify('warning', '최소 1개 이상의 경비 아이템을 입력해 주세요.');
        return false;
    }
    let firstError = null;
    rows.forEach((row, rowIndex) => {
        const errors = {};
        if (!isValidDate(row.expense_date)) errors.expense_date = '올바른 지출일자를 입력해 주세요.';
        if (!String(row.payment_method || '').trim()) errors.payment_method = '지출수단을 선택해 주세요.';
        if (!String(row.merchant_name || '').trim()) errors.merchant_name = '가맹점명을 입력해 주세요.';
        if (!String(row.item_name || '').trim()) errors.item_name = '품명을 입력해 주세요.';
        const manualAmountEntry = !String(row.item_unit_name || '').trim()
            && number(row.item_quantity) === 0
            && number(row.item_unit_price) === 0;
        if (!manualAmountEntry && number(row.item_quantity) <= 0) {
            errors.item_quantity = '수량은 0보다 커야 합니다.';
        }
        if (number(row.item_supply_amount) < 0 || number(row.item_vat_amount) < 0 || number(row.item_total_amount) <= 0) {
            errors.item_total_amount = '합계금액을 확인해 주세요.';
        }
        row.__errors = errors;
        if (!firstError && Object.keys(errors).length) firstError = { rowIndex, field: Object.keys(errors)[0] };
    });
    itemGrid?.loadData?.(rows);
    if (firstError) {
        notify('warning', '각 아이템의 지출일자, 지출수단, 가맹점명, 품명, 수량을 확인해 주세요.');
        itemGrid.focusCell(firstError.rowIndex, firstError.field);
        itemGrid.startEditing(firstError.rowIndex, firstError.field);
        return false;
    }
    return true;
}

function setBusy(value) {
    busy = value;
    form.querySelectorAll('button').forEach(button => { button.disabled = value; });
}

function setReadOnly(readOnly) {
    readOnlyMode = readOnly;
    form.querySelectorAll('.modal-body input:not([type="hidden"]),.modal-body textarea').forEach(control => { control.disabled = readOnly; });
    itemGrid?.setReadOnly?.(readOnly);
    document.getElementById('expenseExcelManager').disabled = readOnly;
}

async function initExcelManager() {
    return initPersonalExpenseExcelManager({
        api: API,
        createSettingsCore: createExcelManagerSettingsCore,
        form,
        getItemGrid: () => itemGrid,
        isReadOnly: () => readOnlyMode,
        notify,
        prepareItemRows,
        refreshSummary,
    });
}

function updateActions(actions = {}, status = 'draft', existing = false) {
    ['expenseDelete', 'expenseWithdraw', 'expenseSave', 'expenseSubmit', 'expenseResubmit'].forEach(id => document.getElementById(id).classList.add('d-none'));
    if (!existing || actions.can_edit) document.getElementById('expenseSave').classList.remove('d-none');
    if (!existing || actions.can_submit) document.getElementById(existing ? 'expenseResubmit' : 'expenseSubmit').classList.remove('d-none');
    if (existing && actions.can_delete) document.getElementById('expenseDelete').classList.remove('d-none');
    if (existing && actions.can_withdraw) document.getElementById('expenseWithdraw').classList.remove('d-none');
    setReadOnly(existing && !actions.can_edit);
    document.getElementById('expenseModalStatus').innerHTML = statusBadge(status);
}

const renderApprovalProgress = createApprovalRenderer({ escapeHtml, statusBadge });

function resetForm() {
    form.reset();
    current = null;
    form.elements.id.value = '';
    form.elements.application_date.value = new Date().toLocaleDateString('sv-SE');
    itemGrid?.loadData?.([]);
    void renderSystemInfo({
        employee_id: currentEmployee.id || '',
        employee_name: currentEmployee.employee_name || '',
    });
    renderApprovalProgress({});
    updateActions({ can_edit: true, can_submit: true }, 'draft', false);
    refreshSummary();
}

function openNew() {
    resetForm();
    captureExpenseModalBaseline();
    modal?.show();
}

async function openDetail(id) {
    try {
        const result = await request(`${API.detail}?id=${encodeURIComponent(id)}`);
        resetForm();
        current = result.data;
        const header = current.header;
        form.elements.id.value = header.id;
        ['application_date', 'title', 'description', 'memo'].forEach(key => { form.elements[key].value = header[key] ?? ''; });
        void renderSystemInfo(header);
        itemGrid.loadData(prepareItemRows(current.items || []));
        const approval = current.approval || {};
        renderApprovalProgress(current);
        updateActions(current.actions, approval.status, true);
        refreshSummary();
        captureExpenseModalBaseline();
        modal?.show();
    } catch (error) {
        notify('error', error.message);
    }
}

async function save(url) {
    if (busy || !validate()) return;
    setBusy(true);
    try {
        await request(url, { method: 'POST', body: JSON.stringify(payload()) });
        allowModalClose = true;
        modal?.hide();
        table?.ajax?.reload(null, false);
        if (url === API.saveSubmit) document.dispatchEvent(new CustomEvent('approval:changed'));
        notify('success', url === API.save ? '저장되었습니다.' : '결재를 요청했습니다.');
    } catch (error) {
        notify('error', error.message);
    } finally {
        setBusy(false);
    }
}

async function removeCurrent() {
    if (!current?.header?.id || !confirm('신청서를 삭제하시겠습니까?')) return;
    try {
        await runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '신청서를 휴지통으로 이동 중', trashChanged: true }, async () => {
            await request(API.delete, { method: 'POST', body: JSON.stringify({ id: current.header.id }) });
            allowModalClose = true;
            modal?.hide();
            await new Promise(resolve => table?.ajax?.reload(() => resolve(), false));
        });
    } catch (error) {
        notify('error', error.message);
    }
}

async function withdrawCurrent() {
    if (!current?.approval?.request_id || !confirm('결재요청을 회수하시겠습니까?')) return;
    try {
        await request(API.withdraw, { method: 'POST', body: JSON.stringify({ request_id: current.approval.request_id }) });
        allowModalClose = true;
        modal?.hide();
        table?.ajax?.reload(null, false);
        document.dispatchEvent(new CustomEvent('approval:changed'));
    } catch (error) {
        notify('error', error.message);
    }
}

function updateCount() {
    const info = table?.page?.info?.();
    document.getElementById('personalExpenseCount').textContent = info ? `총 ${info.recordsDisplay.toLocaleString()}건` : '';
}

form.addEventListener('submit', event => { event.preventDefault(); save(API.save); });
document.getElementById('expenseSubmit').addEventListener('click', () => save(API.saveSubmit));
document.getElementById('expenseResubmit').addEventListener('click', () => save(API.saveSubmit));
document.getElementById('expenseDelete').addEventListener('click', removeCurrent);
document.getElementById('expenseWithdraw').addEventListener('click', withdrawCurrent);
document.querySelectorAll('[data-expense-close]').forEach(button => button.addEventListener('click', () => modal?.hide()));
modalElement?.addEventListener('hide.bs.modal', event => {
    if (itemGrid?.isEditing?.()) {
        itemGrid.stopEditing(false);
        event.preventDefault();
        return;
    }
    confirmExpenseModalClose(event);
});
modalElement?.addEventListener('esc:modal-before-close', event => {
    if (event.detail?.modal !== modalElement) return;
    confirmExpenseModalClose(event);
});
modalElement?.addEventListener('hidden.bs.modal', () => {
    unbindExpenseGridEscape();
    allowModalClose = false;
    modalBaselineSnapshot = '';
});

function bindExpenseCardCollapse(toggle, collapseElement) {
    if (!toggle || !collapseElement) return;
    const collapse = window.bootstrap
        ? window.bootstrap.Collapse.getOrCreateInstance(collapseElement, { toggle: false })
        : null;
    toggle.addEventListener('click', () => {
        if (collapse) {
            collapse.toggle();
            return;
        }
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        collapseElement.classList.toggle('show', !expanded);
        toggle.classList.toggle('collapsed', expanded);
        toggle.setAttribute('aria-expanded', String(!expanded));
    });
    collapseElement.addEventListener('shown.bs.collapse', () => {
        toggle.classList.remove('collapsed');
        toggle.setAttribute('aria-expanded', 'true');
    });
    collapseElement.addEventListener('hidden.bs.collapse', () => {
        toggle.classList.add('collapsed');
        toggle.setAttribute('aria-expanded', 'false');
    });
}

bindExpenseCardCollapse(systemInfoToggle, systemInfoCollapseElement);
bindExpenseCardCollapse(approvalInfoToggle, approvalInfoCollapseElement);
modalElement?.addEventListener('shown.bs.modal', () => {
    itemGrid?.refreshDimensions?.();
});
modalElement?.addEventListener('shown.bs.modal', applyHeaderFieldPolicies);
document.addEventListener('datatable-settings:updated', event => {
    if (String(event?.detail?.storageKey || '') !== TABLE_SETTINGS_STORAGE_KEY) return;
    applyHeaderFieldPolicies();
});
window.TrashColumns = window.TrashColumns || {};
window.TrashColumns['personal-expense'] = trashColumns;
document.addEventListener('trash:detail-render', event => {
    if (event.detail?.type === 'personal-expense') renderTrashDetail(event.detail.data);
});
document.addEventListener('trash:changed', event => {
    if (event.detail?.type === 'personal-expense') table?.ajax?.reload(null, false);
});

initItemGrid();
await initTable();
initExcelManager();
applyHeaderFieldPolicies();
