import {
    bindTableHighlight,
    createDataTable,
} from '/public/assets/js/components/data-table.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { bindNumberInput, formatDateInputValue, formatNumber, parseNumber } from '/public/assets/js/common/format.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import {
    createCodeSelect,
    getCodeName,
    initCodeSelectControls,
    onCodeOptionsLoaded,
    openCodeQuickModal,
} from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { openClientQuickCreate } from '/public/assets/js/pages/dashboard/settings/base/client.js';
import '/public/assets/js/components/trash-manager.js';

(() => {
    const form = document.getElementById('transactionForm');
    const modalEl = document.getElementById('transactionModal');
    const hotEl = document.getElementById('transactionLineHot');
    const deleteBtn = document.getElementById('btnDeleteTransaction');
    const countEl = document.getElementById('transactionCount');
    const importToggle = document.getElementById('is_import');
    const fileToggle = document.getElementById('use_file_reference');
    const filePanel = document.getElementById('transactionFilePanel');
    const fileInput = document.getElementById('transaction_files');
    const fileListEl = document.getElementById('transaction_file_list');
    const fileDropzoneEl = document.getElementById('transaction_file_dropzone');
    const voucherStatusEl = document.getElementById('transactionVoucherStatus');
    const transactionStatusBadgeEl = document.getElementById('transactionStatusBadge');
    const voucherSummaryEl = document.getElementById('transaction_voucher_summary');
    const voucherIdInput = document.getElementById('transaction_voucher_id');
    const createVoucherBtn = document.getElementById('btnCreateTransactionVoucher');
    const linkVoucherBtn = document.getElementById('btnLinkTransactionVoucher');
    const unlinkVoucherBtn = document.getElementById('btnUnlinkTransactionVoucher');
    const transactionDateEl = document.getElementById('transaction_date');
    const pickerLayerEl = document.getElementById('transaction-today-picker');
    const clientSelectEl = document.getElementById('client_id');
    const projectSelectEl = document.getElementById('project_id');
    const currencySelectEl = document.getElementById('currency');
    const exchangeRateEl = document.getElementById('exchange_rate');
    const modalBodyEl = modalEl?.querySelector('.transaction-modal-body');

    if (!form || !modalEl || !hotEl) {
        return;
    }

    const modal = window.bootstrap ? new bootstrap.Modal(modalEl, { focus: false }) : null;
    let transactionTable = null;
    let lineHot = null;
    let currentFiles = [];
    let pendingFiles = [];
    let fileRowOrder = [];
    let transactionDatePicker = null;
    let lineDateEscHandler = null;
    let lineHotEditing = false;
    let manualLineDateEdit = false;
    let activeLineDateInput = null;
    let activeLineDateInputHandler = null;
    let unitCodeSelectEl = null;
    let unitOptions = [];
    let pendingUnitCell = null;
    let lastInvalidUnitNotice = '';
    let taxTypeCodeSelectEl = null;
    let taxTypeOptions = [];
    let modalBaselineSnapshot = '';
    let allowModalClose = false;
    let floatingLineHeaderEl = null;
    let lineHeaderFrame = null;
    let fileDropzoneEmptyText = '파일을 드래그해서 첨부하세요';

    if (pickerLayerEl && pickerLayerEl.parentElement !== document.body) {
        document.body.appendChild(pickerLayerEl);
    }

    const API = {
        list: '/api/ledger/transaction/list',
        detail: '/api/ledger/transaction/detail',
        save: '/api/ledger/transaction/save',
        remove: '/api/ledger/transaction/delete',
        trash: '/api/ledger/transaction/trash',
        restore: '/api/ledger/transaction/restore',
        purge: '/api/ledger/transaction/purge',
        purgeAll: '/api/ledger/transaction/purge-all',
        reorder: '/api/ledger/transaction/reorder',
        createVoucher: '/api/ledger/transaction/create-voucher',
        linkVoucher: '/api/ledger/transaction/link-voucher',
        unlinkVoucher: '/api/ledger/transaction/unlink-voucher',
        clientSearch: '/api/settings/base-info/client/search-picker',
        projectSearch: '/api/settings/base-info/project/search-picker',
        filePolicyList: '/api/system/file-policies',
    };

    const DATE_OPTIONS = [
        { value: 'transaction_date', label: '거래일자' },
        { value: 'updated_at', label: '수정일시' },
    ];

    const HOT_DATE_PICKER_CONFIG = {
        firstDay: 0,
        showMonthAfterYear: true,
        yearSuffix: '',
        onOpen: decorateHotDatePicker,
        onDraw: decorateHotDatePicker,
        i18n: {
            previousMonth: '이전 달',
            nextMonth: '다음 달',
            months: [
                '1월',
                '2월',
                '3월',
                '4월',
                '5월',
                '6월',
                '7월',
                '8월',
                '9월',
                '10월',
                '11월',
                '12월',
            ],
            weekdays: [
                '일요일',
                '월요일',
                '화요일',
                '수요일',
                '목요일',
                '금요일',
                '토요일',
            ],
            weekdaysShort: ['일', '월', '화', '수', '목', '금', '토'],
        },
    };

    const LINE_ITEM_DATE_COL = 2;
    const LINE_UNIT_COL = 5;
    const TAX_TYPE_DEFAULT_LABEL = '과세';
    const UNIT_EMPTY_LABEL = '선택(없음)';
    const UNIT_QUICK_ADD_LABEL = '+기준추가';

    const LINE_COLUMNS = [
        { data: '__move', title: '<i class="bi bi-arrows-move"></i>', readOnly: true, width: 28, renderer: lineMoveRenderer },
        { data: '__row_no', title: '순번', readOnly: true, width: 36, renderer: lineRowNoRenderer },
        { data: 'item_date', title: '발생일', type: 'date', dateFormat: 'YYYY-MM-DD', correctFormat: true, datePickerConfig: HOT_DATE_PICKER_CONFIG, width: 82 },
        { data: 'item_name', title: '품명', width: 105 },
        { data: 'specification', title: '규격', width: 70 },
        { data: 'unit_name', title: '단위', type: 'autocomplete', source: unitDropdownSource, strict: true, filter: false, allowInvalid: false, width: 54 },
        { data: 'quantity', title: '수량', type: 'numeric', numericFormat: { pattern: '0,0.000' }, width: 62 },
        { data: 'unit_price', title: '단가', type: 'numeric', numericFormat: { pattern: '0,0' }, width: 72 },
        { data: 'supply_amount', title: '공급가액', type: 'numeric', numericFormat: { pattern: '0,0' }, readOnly: true, width: 78 },
        { data: 'vat_amount', title: '부가세액', type: 'numeric', numericFormat: { pattern: '0,0' }, readOnly: true, width: 72 },
        { data: 'total_amount', title: '합계금액', type: 'numeric', numericFormat: { pattern: '0,0' }, readOnly: true, width: 78 },
        {
            data: 'tax_type',
            title: '과세구분',
            type: 'autocomplete',
            source: taxTypeDropdownSource,
            strict: true,
            filter: false,
            allowInvalid: false,
            width: 70,
        },
        { data: 'description', title: '적요', width: 105 },
        { data: '__actions', title: '+추가', readOnly: true, width: 44, renderer: lineActionRenderer },
    ];

    function getLineColumns() {
        if (!usesForeignCurrency()) {
            return LINE_COLUMNS.filter((column) => !['foreign_unit_price', 'foreign_amount'].includes(column.data));
        }

        const columns = [];
        LINE_COLUMNS.forEach((column) => {
            if (column.data === 'unit_price') {
                columns.push(
                    { data: 'foreign_unit_price', title: '외화단가', type: 'numeric', numericFormat: { pattern: '0,0.00' }, width: 62 },
                    { data: 'foreign_amount', title: '외화금액', type: 'numeric', numericFormat: { pattern: '0,0.00' }, width: 82 },
                );
                return;
            }
            columns.push(column);
        });

        return columns;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function notify(type, message) {
        if (window.AppCore?.notify) {
            window.AppCore.notify(type, message);
            return;
        }
        if (window.showToast) {
            window.showToast(type, message);
            return;
        }
        window.alert(message);
    }

    function today() {
        return formatDate(new Date());
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

    function decorateHotDatePicker(pickerArg) {
        const picker = pickerArg?.el ? pickerArg : this;
        const root = picker?.el;
        const title = root?.querySelector?.('.pika-title');
        if (!picker || !root || !title || title.dataset.yearNavBound === 'true') return;

        title.dataset.yearNavBound = 'true';

        const prevYear = document.createElement('button');
        prevYear.type = 'button';
        prevYear.className = 'pika-year-prev';
        prevYear.textContent = '<<';
        prevYear.setAttribute('aria-label', '이전 년');

        const nextYear = document.createElement('button');
        nextYear.type = 'button';
        nextYear.className = 'pika-year-next';
        nextYear.textContent = '>>';
        nextYear.setAttribute('aria-label', '다음 년');

        prevYear.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            picker.gotoYear(picker.calendars[0].year - 1);
        });

        nextYear.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            picker.gotoYear(picker.calendars[0].year + 1);
        });

        title.prepend(prevYear);
        title.append(nextYear);
    }

    function isLineDateCell(row, col) {
        return row >= 0 && col === LINE_ITEM_DATE_COL;
    }

    function isManualDateKey(event) {
        if (!event || event.ctrlKey || event.metaKey || event.altKey) return false;
        if (/^\d$/.test(event.key)) return true;
        return ['Backspace', 'Delete'].includes(event.key);
    }

    function getVisibleHotDatePicker() {
        return Array.from(document.querySelectorAll('.pika-single'))
            .find((picker) => !picker.classList.contains('is-hidden') && picker.offsetParent !== null);
    }

    function closeHotDatePicker() {
        const editor = lineHot?.getActiveEditor?.();
        editor?.datePicker?.hide?.();

        document.querySelectorAll('.pika-single').forEach((picker) => {
            picker.classList.add('is-hidden');
        });
    }

    function unbindLineDateInputFormatter() {
        if (activeLineDateInput && activeLineDateInputHandler) {
            activeLineDateInput.removeEventListener('input', activeLineDateInputHandler);
        }

        activeLineDateInput = null;
        activeLineDateInputHandler = null;
    }

    function bindLineDateInputFormatter() {
        unbindLineDateInputFormatter();

        const editor = lineHot?.getActiveEditor?.();
        const input = editor?.TEXTAREA;
        if (!input) return;

        activeLineDateInput = input;
        activeLineDateInputHandler = () => {
            const nextValue = formatDateInputValue(input.value);
            if (input.value === nextValue) return;

            const cursor = nextValue.length;
            input.value = nextValue;
            input.setSelectionRange?.(cursor, cursor);
        };

        input.addEventListener('input', activeLineDateInputHandler);
    }

    function unbindLineDateEscHandler() {
        if (!lineDateEscHandler) return;
        window.ESCStack?.remove?.(lineDateEscHandler);
        lineDateEscHandler = null;
    }

    function bindLineDateEscHandler() {
        if (lineDateEscHandler || !window.ESCStack) return;

        lineDateEscHandler = () => {
            if (!lineHotEditing) {
                unbindLineDateEscHandler();
                return false;
            }

            if (getVisibleHotDatePicker()) {
                closeHotDatePicker();
                return true;
            }

            const editor = lineHot?.getActiveEditor?.();
            editor.finishEditing?.(true);
            lineHotEditing = false;
            unbindLineDateEscHandler();
            return true;
        };

        window.ESCStack.push(lineDateEscHandler);
    }

    function getUnitDropdownSource() {
        return [
            UNIT_EMPTY_LABEL,
            ...unitOptions.map((option) => option.label),
            UNIT_QUICK_ADD_LABEL,
        ];
    }

    function unitDropdownSource(query, process) {
        const keyword = String(query ?? '').trim().toLowerCase();
        const isCurrentSelection = unitOptions.some((option) => (
            option.label.toLowerCase() === keyword ||
            option.code.toLowerCase() === keyword
        ));
        const baseOptions = unitOptions
            .filter((option) => {
                if (!keyword || isCurrentSelection) return true;

                return option.label.toLowerCase().includes(keyword)
                    || option.code.toLowerCase().includes(keyword);
            })
            .map((option) => option.label);

        process([
            UNIT_EMPTY_LABEL,
            ...baseOptions,
            UNIT_QUICK_ADD_LABEL,
        ]);
    }

    function normalizeUnitCellValue(value) {
        const text = String(value ?? '').trim();
        if (!text || text === UNIT_EMPTY_LABEL) return '';

        const found = unitOptions.find((option) => (
            option.label === text ||
            option.code === text
        ));

        return found?.label || text;
    }

    function isAllowedUnitCellValue(value) {
        const text = String(value ?? '').trim();
        if (!text || text === UNIT_EMPTY_LABEL || text === UNIT_QUICK_ADD_LABEL) return true;

        return unitOptions.some((option) => (
            option.label === text ||
            option.code === text
        ));
    }

    function notifyInvalidUnitValue(value) {
        const text = String(value ?? '').trim();
        const key = `${text}:${Date.now() >> 10}`;
        if (!text || lastInvalidUnitNotice === key) return;

        lastInvalidUnitNotice = key;
        notify('warning', `"${text}"은(는) 단위 기준정보 목록에 없습니다. 목록에서 선택하거나 +기준추가로 등록하세요.`);
    }

    function updateUnitOptionsFromCodeState(options = {}) {
        const rows = Array.isArray(options.UNIT) ? options.UNIT : [];
        unitOptions = rows
            .map((row) => ({
                code: String(row.code ?? '').trim(),
                label: String(row.code_name || row.code || '').trim(),
            }))
            .filter((row) => row.label);

        lineHot?.updateSettings({
            columns: getLineColumns(),
            colHeaders: getLineColumns().map((column) => column.title),
        });

        (lineHot?.getSourceData() || []).forEach((row, index) => {
            const normalized = normalizeUnitCellValue(row?.unit_name);
            if (row && row.unit_name !== normalized) {
                lineHot.setSourceDataAtCell(index, 'unit_name', normalized);
            }
        });
        lineHot?.render();
    }

    function updateTaxTypeOptionsFromCodeState(options = {}) {
        const rows = Array.isArray(options.TAX_TYPE) ? options.TAX_TYPE : [];
        taxTypeOptions = rows
            .map((row) => ({
                code: String(row.code ?? '').trim(),
                label: String(row.code_name || row.code || '').trim(),
            }))
            .filter((row) => row.code && row.label);

        lineHot?.updateSettings({
            columns: getLineColumns(),
            colHeaders: getLineColumns().map((column) => column.title),
        });
        (lineHot?.getSourceData() || []).forEach((row, index) => {
            const normalized = normalizeTaxTypeCellValue(row?.tax_type);
            if (row && row.tax_type !== normalized) {
                lineHot.setSourceDataAtCell(index, 'tax_type', normalized);
            }
        });
        lineHot?.render();
    }

    function findTaxTypeOption(value) {
        const text = String(value ?? '').trim();
        return taxTypeOptions.find((option) => (
            option.code === text ||
            option.label === text
        ));
    }

    function taxTypeDropdownSource(query, process) {
        const keyword = String(query ?? '').trim().toLowerCase();
        const isCurrentSelection = taxTypeOptions.some((option) => (
            option.label.toLowerCase() === keyword ||
            option.code.toLowerCase() === keyword
        ));
        const rows = taxTypeOptions.filter((option) => {
            if (!keyword || isCurrentSelection) return true;

            return option.label.toLowerCase().includes(keyword)
                || option.code.toLowerCase().includes(keyword);
        });

        process(rows.map((option) => option.label));
    }

    function normalizeTaxTypeCellValue(value) {
        const text = String(value ?? '').trim();
        if (!text) return '';

        const found = findTaxTypeOption(text);
        return found?.label || text;
    }

    function taxTypeCodeFromCellValue(value) {
        const text = String(value ?? '').trim();
        if (!text) return '';

        const found = findTaxTypeOption(text);
        return found?.code || text;
    }

    function normalizeTaxTypeCode(value) {
        const code = taxTypeCodeFromCellValue(value).trim().toUpperCase();
        if (code) return code;

        return String(value ?? '').trim().toUpperCase();
    }

    function taxTypeLabelFromCode(value) {
        const text = String(value ?? '').trim();
        if (!text) return TAX_TYPE_DEFAULT_LABEL;

        const found = findTaxTypeOption(text);
        return found?.label || text;
    }

    function defaultLineTaxTypeCode() {
        return usesForeignCurrency() ? 'ZERO' : 'TAXABLE';
    }

    function defaultLineTaxTypeLabel() {
        return taxTypeLabelFromCode(defaultLineTaxTypeCode());
    }

    function applyForeignTaxTypeToLines() {
        if (!usesForeignCurrency() || !lineHot) return;

        (lineHot.getSourceData() || []).forEach((row, index) => {
            if (!row) return;
            setLineCellValue(index, 'tax_type', taxTypeLabelFromCode('ZERO'), 'foreign-tax');
        });
    }

    async function initUnitCodeOptions() {
        unitCodeSelectEl = document.getElementById('transaction_unit_code_select');
        if (!unitCodeSelectEl) {
            unitCodeSelectEl = document.createElement('select');
            unitCodeSelectEl.id = 'transaction_unit_code_select';
            unitCodeSelectEl.dataset.codeGroup = 'UNIT';
            unitCodeSelectEl.dataset.emptyLabel = UNIT_EMPTY_LABEL;
            unitCodeSelectEl.className = 'd-none';
            unitCodeSelectEl.tabIndex = -1;
            document.body.appendChild(unitCodeSelectEl);
        }

        taxTypeCodeSelectEl = document.getElementById('transaction_tax_type_code_select');
        if (!taxTypeCodeSelectEl) {
            taxTypeCodeSelectEl = document.createElement('select');
            taxTypeCodeSelectEl.id = 'transaction_tax_type_code_select';
            taxTypeCodeSelectEl.dataset.codeGroup = 'TAX_TYPE';
            taxTypeCodeSelectEl.className = 'd-none';
            taxTypeCodeSelectEl.tabIndex = -1;
            document.body.appendChild(taxTypeCodeSelectEl);
        }

        onCodeOptionsLoaded(updateUnitOptionsFromCodeState);
        onCodeOptionsLoaded(updateTaxTypeOptionsFromCodeState);
        await createCodeSelect({
            selectId: unitCodeSelectEl.id,
            codeGroup: 'UNIT',
        });
        await createCodeSelect({
            selectId: taxTypeCodeSelectEl.id,
            codeGroup: 'TAX_TYPE',
        });
    }

    function openUnitQuickAdd(row, col) {
        pendingUnitCell = { row, col };
        void openCodeQuickModal({
            codeGroup: 'UNIT',
            targetSelectId: unitCodeSelectEl?.id || '',
        });
    }

    function applyPendingUnitSelection() {
        if (!pendingUnitCell || !unitCodeSelectEl || !lineHot) return;

        const selectedCode = unitCodeSelectEl.value || '';
        if (!selectedCode) return;

        const selected = unitOptions.find((option) => option.code === selectedCode);
        if (!selected) return;

        lineHot.setDataAtCell(
            pendingUnitCell.row,
            pendingUnitCell.col,
            selected.label,
            'unit-quick-add'
        );
        pendingUnitCell = null;
    }

    function numberValue(value) {
        const parsed = Number(String(value ?? '').replace(/,/g, ''));
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function formatAmount(value) {
        return new Intl.NumberFormat('ko-KR').format(Math.round(numberValue(value)));
    }

    function lineMoveRenderer(instance, td) {
        Handsontable.dom.empty(td);
        td.className = `${td.className || ''} transaction-line-move-cell`.trim();
        td.removeAttribute('draggable');
        td.innerHTML = '<span class="transaction-line-move-handle" aria-label="순서 변경"><i class="bi bi-list"></i></span>';
        return td;
    }

    function lineRowNoRenderer(instance, td, row) {
        Handsontable.dom.empty(td);
        td.className = `${td.className || ''} transaction-line-row-no-cell`.trim();
        td.textContent = String(row + 1);
        return td;
    }

    function lineActionRenderer(instance, td) {
        Handsontable.dom.empty(td);
        td.className = `${td.className || ''} transaction-line-action-cell`.trim();
        td.innerHTML = '<button type="button" class="transaction-line-delete-action">-삭제</button>';
        return td;
    }

    function formatBytes(value) {
        const size = Number(value || 0);
        if (!Number.isFinite(size) || size <= 0) return '-';
        if (size >= 1024 * 1024) return `${formatNumber((size / 1024 / 1024).toFixed(2))} MB`;
        if (size >= 1024) return `${formatNumber((size / 1024).toFixed(1))} KB`;
        return `${formatAmount(size)} bytes`;
    }

    function updateFileDropzone(rows = []) {
        if (!fileDropzoneEl) return;

        const count = rows.length;
        const totalSize = rows.reduce((sum, file) => sum + Number(file.file_size || file.size || 0), 0);
        const text = count > 0
            ? `파일 ${formatAmount(count)}개 (${formatBytes(totalSize)})`
            : fileDropzoneEmptyText;
        const textEl = fileDropzoneEl.querySelector('.transaction-file-dropzone-text');
        if (textEl) {
            textEl.textContent = text;
        } else {
            fileDropzoneEl.textContent = text;
        }
        fileDropzoneEl.classList.toggle('has-files', count > 0);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            ...options,
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '요청 처리에 실패했습니다.');
        }
        return json;
    }

    function renderMatchStatus(value) {
        const key = String(value || 'none').toLowerCase();
        const label = key === 'matched' ? '연결' : '미연결';
        const className = key === 'matched' ? 'transaction-status matched' : 'transaction-status none';
        return `<span class="${className}">${label}</span>`;
    }

    function normalizeTransactionStatus(value) {
        const status = String(value || 'draft').toLowerCase();
        return ['draft', 'approved', 'rejected'].includes(status) ? status : 'draft';
    }

    function renderTransactionStatus(value) {
        const status = normalizeTransactionStatus(value);
        const labels = {
            draft: '입력',
            approved: '승인완료',
            rejected: '반려',
        };
        return `<span class="transaction-status transaction-status-${status}">${labels[status]}</span>`;
    }

    function updateTransactionStatusBadge(value) {
        if (!transactionStatusBadgeEl) return;

        const status = normalizeTransactionStatus(value);
        const labels = {
            draft: '입력',
            approved: '승인',
            rejected: '반려',
        };

        transactionStatusBadgeEl.className = `transaction-modal-state transaction-status-${status}`;
        const labelEl = transactionStatusBadgeEl.querySelector('span');
        if (labelEl) {
            labelEl.textContent = labels[status] || labels.draft;
        } else {
            transactionStatusBadgeEl.textContent = labels[status] || labels.draft;
        }
    }

    function setTransactionModalEditable(editable) {
        form.querySelectorAll('input, select, textarea, button').forEach((control) => {
            if (control.matches('[data-bs-dismiss="modal"], .btn-close')) return;

            control.disabled = !editable;
        });

        deleteBtn?.classList.toggle('d-none', !editable || !document.getElementById('transaction_id')?.value);
        lineHot?.updateSettings({ readOnly: !editable });
        lineHot?.render();
    }

    function renderCodeName(field, value) {
        return escapeHtml(getCodeName(field, value) || value || '');
    }

    function buildTransactionDataColumns() {
        const textColumn = (data, title, options = {}) => ({
            data,
            title,
            defaultContent: '',
            visible: options.visible ?? false,
            className: options.className || '',
            width: options.width,
            render(value) {
                if (typeof options.render === 'function') {
                    return options.render(value);
                }
                return escapeHtml(value || '');
            },
        });

        const amountColumn = (data, title, visible = false) => ({
            data,
            title,
            className: 'text-end',
            visible,
            defaultContent: 0,
            render(value) {
                return escapeHtml(formatAmount(value || 0));
            },
        });

        return [
            {
                title: '<i class="bi bi-arrows-move"></i>',
                className: 'reorder-handle no-colvis text-center',
                orderable: false,
                searchable: false,
                defaultContent: '<i class="bi bi-list"></i>',
            },
            textColumn('sort_no', '순번', {
                visible: true,
                className: 'text-center transaction-sort-no-cell',
            }),
            textColumn('transaction_date', '작성일자', { visible: true }),
            textColumn('business_unit', '사업구분'),
            textColumn('transaction_type', '거래유형', {
                visible: true,
                render(value) {
                    return renderCodeName('transaction_type', value);
                },
            }),
            textColumn('client_id', '거래처ID'),
            {
                data: 'client_name',
                title: '거래처',
                visible: true,
                defaultContent: '',
                render(data, type, row) {
                    return escapeHtml(data || row.client_id || '');
                },
            },
            textColumn('project_id', '프로젝트ID'),
            {
                data: 'project_name',
                title: '프로젝트',
                defaultContent: '',
                visible: false,
                render(data, type, row) {
                    return escapeHtml(data || row.project_id || '');
                },
            },
            textColumn('currency', '통화'),
            textColumn('exchange_rate', '환율', { className: 'text-end' }),
            textColumn('tax_type', '과세구분'),
            amountColumn('supply_amount', '공급가'),
            amountColumn('vat_amount', '부가세'),
            amountColumn('total_amount', '총금액', true),
            {
                data: 'description',
                title: '적요',
                className: 'transaction-description-cell',
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
            {
                data: 'match_status',
                title: '전표연결',
                className: 'text-center',
                visible: true,
                defaultContent: 'none',
                render(data) {
                    return renderMatchStatus(data);
                },
            },
            textColumn('status', '상태', { visible: true, className: 'text-center', render: renderTransactionStatus }),
            textColumn('note', '비고'),
            textColumn('memo', '메모'),
            textColumn('created_at', '생성일시'),
            textColumn('created_by', '생성자'),
            textColumn('updated_at', '수정일시'),
            textColumn('updated_by', '수정자'),
            textColumn('deleted_at', '삭제일시'),
            textColumn('deleted_by', '삭제자'),
            {
                data: 'id',
                title: 'ID',
                className: 'no-colvis',
                visible: false,
                searchable: false,
                defaultContent: '',
                render(data) {
                    return escapeHtml(data || '');
                },
            },
        ];
    }

    function updateCount() {
        if (!transactionTable || !countEl) return;
        const info = transactionTable.page.info();
        countEl.textContent = `${info.recordsDisplay || 0}건`;
    }

    function initTransactionTable() {
        if (transactionTable || !window.jQuery?.fn?.DataTable) {
            return Boolean(transactionTable);
        }

        transactionTable = createDataTable({
            tableSelector: '#transaction-table',
            api: API.list,
            columns: buildTransactionDataColumns(),
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '엑셀 다운로드',
                    className: 'btn btn-outline-success btn-sm',
                    title: '거래입력',
                    filename: '거래입력',
                    exportOptions: { columns: ':visible:not(.no-export):not(.no-colvis)' },
                },
                { text: '휴지통', className: 'btn btn-danger btn-sm', action: openTrashModal },
                { text: '새 거래', className: 'btn btn-warning btn-sm', action: openCreateModal },
            ],
            defaultOrder: [[1, 'asc']],
            pageLength: 10,
            searchTableId: 'transaction',
        });

        bindRowReorder(transactionTable, {
            api: API.reorder,
            onSuccess() {
                notify('success', '거래 순번이 변경되었습니다.');
                transactionTable?.ajax.reload(null, false);
            },
            onError(json) {
                notify('error', json?.message || '거래 순번 변경에 실패했습니다.');
                transactionTable?.ajax.reload(null, false);
            },
        });
        bindTableHighlight('#transaction-table', transactionTable);
        transactionTable.on('init.dt draw.dt xhr.dt', updateCount);

        SearchForm({
            table: transactionTable,
            apiList: API.list,
            tableId: 'transaction',
            defaultSearchField: 'description',
            dateOptions: DATE_OPTIONS,
        });

        bindTableEvents();
        updateCount();
        return true;
    }

    function reloadTable() {
        transactionTable?.ajax?.reload(null, false);
    }

    function openTrashModal() {
        const trashModal = document.getElementById('transactionTrashModal');
        if (!trashModal) {
            notify('warning', '거래 휴지통 모달을 찾을 수 없습니다.');
            return;
        }

        trashModal.dataset.listUrl = API.trash;
        trashModal.dataset.restoreUrl = API.restore;
        trashModal.dataset.deleteUrl = API.purge;
        trashModal.dataset.deleteAllUrl = API.purgeAll;
        bootstrap.Modal.getOrCreateInstance(trashModal, { focus: false }).show();
    }

    function bindTableEvents() {
        const table = document.getElementById('transaction-table');
        table?.addEventListener('click', (event) => {
            const editBtn = event.target.closest('.btn-edit-transaction');
            if (editBtn) {
                void openDetail(editBtn.dataset.id || '');
                return;
            }

            const deleteRowBtn = event.target.closest('.btn-delete-transaction');
            if (deleteRowBtn && window.confirm('거래를 삭제하시겠습니까?')) {
                void deleteTransaction(deleteRowBtn.dataset.id || '');
            }
        });

        table?.querySelector('tbody')?.addEventListener('dblclick', (event) => {
            if (event.target.closest('a, button, input, select, textarea, .dropdown-menu, .reorder-handle')) {
                return;
            }

            const rowEl = event.target.closest('tr');
            if (!rowEl || !transactionTable) return;

            const rowData = transactionTable.row(rowEl).data();
            const id = rowData?.id || '';
            if (id) {
                void openDetail(id);
            }
        });
    }

    function blankLine() {
        return {
            item_date: document.getElementById('transaction_date')?.value || today(),
            item_name: '',
            specification: '',
            unit_name: '',
            quantity: '',
            unit_price: 0,
            foreign_unit_price: '',
            foreign_amount: '',
            supply_amount: 0,
            vat_amount: 0,
            total_amount: 0,
            tax_type: defaultLineTaxTypeLabel(),
            description: '',
        };
    }

    function normalizeLine(item = {}) {
        return {
            item_date: item.item_date || document.getElementById('transaction_date')?.value || today(),
            item_name: item.item_name || '',
            specification: item.specification || '',
            unit_name: item.unit_name || '',
            quantity: item.quantity === undefined || item.quantity === null || item.quantity === ''
                ? ''
                : numberValue(item.quantity),
            unit_price: numberValue(item.unit_price ?? 0),
            foreign_unit_price: item.foreign_unit_price === undefined || item.foreign_unit_price === null || item.foreign_unit_price === ''
                ? ''
                : numberValue(item.foreign_unit_price),
            foreign_amount: item.foreign_amount === undefined || item.foreign_amount === null || item.foreign_amount === ''
                ? ''
                : numberValue(item.foreign_amount),
            supply_amount: numberValue(item.supply_amount ?? 0),
            vat_amount: numberValue(item.vat_amount ?? 0),
            total_amount: numberValue(item.total_amount ?? 0),
            tax_type: taxTypeLabelFromCode(item.tax_type || defaultLineTaxTypeCode()),
            description: item.description || '',
        };
    }

    function getExchangeRateValue() {
        const value = parseNumber(exchangeRateEl?.value || '');
        return value > 0 ? value : 0;
    }

    function calculateLine(row) {
        const quantity = numberValue(row.quantity);
        const foreignMode = usesForeignCurrency();
        const foreignUnitPrice = numberValue(row.foreign_unit_price);
        const foreignAmount = numberValue(row.foreign_amount || (quantity * foreignUnitPrice));
        const exchangeRate = getExchangeRateValue();
        const unitPrice = foreignMode && quantity > 0 && exchangeRate > 0
            ? (foreignAmount * exchangeRate) / quantity
            : numberValue(row.unit_price);
        const supply = foreignMode
            ? Math.round(foreignAmount * exchangeRate)
            : Math.round(quantity * unitPrice);
        const taxType = foreignMode ? 'ZERO' : normalizeTaxTypeCode(row.tax_type || 'TAXABLE');
        const vat = taxType === 'TAXABLE' ? Math.round(supply * 0.1) : 0;

        row.quantity = quantity;
        row.unit_price = unitPrice;
        row.foreign_unit_price = foreignMode ? foreignUnitPrice : '';
        row.foreign_amount = foreignMode ? foreignAmount : '';
        row.supply_amount = supply;
        row.vat_amount = vat;
        row.total_amount = supply + vat;
        row.tax_type = taxTypeLabelFromCode(taxType);
        return row;
    }

    function setLineCellValue(row, prop, value, source = 'program') {
        if (!lineHot || row < 0) return;

        if (typeof lineHot.setSourceDataAtCell === 'function') {
            lineHot.setSourceDataAtCell(row, prop, value, source);
            return;
        }

        lineHot.setDataAtRowProp(row, prop, value, source);
    }

    function calculateTotals() {
        const rows = lineHot ? lineHot.getSourceData() : [];
        let supply = 0;
        let vat = 0;
        let total = 0;

        rows.forEach((row, index) => {
            if (!row || (!row.item_name && !row.specification && !numberValue(row.unit_price) && !numberValue(row.foreign_amount) && !numberValue(row.foreign_unit_price))) return;
            const calculated = calculateLine(row);
            supply += calculated.supply_amount;
            vat += calculated.vat_amount;
            total += calculated.total_amount;

            if (lineHot) {
                setLineCellValue(index, 'unit_price', calculated.unit_price, 'calc');
                setLineCellValue(index, 'foreign_amount', calculated.foreign_amount, 'calc');
                setLineCellValue(index, 'supply_amount', calculated.supply_amount, 'calc');
                setLineCellValue(index, 'vat_amount', calculated.vat_amount, 'calc');
                setLineCellValue(index, 'total_amount', calculated.total_amount, 'calc');
            }
        });

        document.getElementById('transaction_supply_total').value = formatAmount(supply);
        document.getElementById('transaction_vat_total').value = formatAmount(vat);
        document.getElementById('transaction_grand_total').value = formatAmount(total);
    }

    function refreshLineHotDimensions() {
        window.requestAnimationFrame(() => {
            lineHot?.refreshDimensions?.();
            lineHot?.render?.();
            syncFloatingLineHeader();
        });
    }

    function getLineHeaderRow() {
        return hotEl.querySelector('.ht_master .htCore thead tr')
            || hotEl.querySelector('.handsontable .htCore thead tr')
            || hotEl.querySelector('thead tr');
    }

    function ensureFloatingLineHeader() {
        if (floatingLineHeaderEl) return floatingLineHeaderEl;

        floatingLineHeaderEl = document.createElement('div');
        floatingLineHeaderEl.className = 'transaction-line-floating-head';
        floatingLineHeaderEl.setAttribute('aria-hidden', 'true');
        floatingLineHeaderEl.innerHTML = '<table><colgroup></colgroup><thead></thead></table>';
        document.body.appendChild(floatingLineHeaderEl);

        floatingLineHeaderEl.addEventListener('click', (event) => {
            if (!event.target.closest('.transaction-line-add-action')) return;
            event.preventDefault();
            addLine();
        });

        return floatingLineHeaderEl;
    }

    function hideFloatingLineHeader() {
        if (floatingLineHeaderEl) {
            floatingLineHeaderEl.classList.remove('is-visible');
        }
    }

    function updateFloatingLineHeader() {
        lineHeaderFrame = null;

        if (!modalBodyEl || !lineHot || !modalEl.classList.contains('show')) {
            hideFloatingLineHeader();
            return;
        }

        const wrap = hotEl.closest('.transaction-hot-wrap');
        const sourceRow = getLineHeaderRow();
        if (!wrap || !sourceRow) {
            hideFloatingLineHeader();
            return;
        }

        const bodyRect = modalBodyEl.getBoundingClientRect();
        const wrapRect = wrap.getBoundingClientRect();
        const headerRect = sourceRow.getBoundingClientRect();
        const shouldStick = headerRect.top <= bodyRect.top && wrapRect.bottom > bodyRect.top + headerRect.height;

        if (!shouldStick) {
            hideFloatingLineHeader();
            return;
        }

        const floating = ensureFloatingLineHeader();
        const colgroup = floating.querySelector('colgroup');
        const thead = floating.querySelector('thead');
        const headerCells = Array.from(sourceRow.children);

        colgroup.innerHTML = headerCells
            .map((cell) => `<col style="width:${cell.getBoundingClientRect().width}px">`)
            .join('');
        thead.innerHTML = `<tr>${headerCells.map((cell) => cell.outerHTML).join('')}</tr>`;

        floating.style.top = `${Math.round(bodyRect.top)}px`;
        floating.style.left = `${Math.round(wrapRect.left)}px`;
        floating.style.width = `${Math.round(wrapRect.width)}px`;
        floating.classList.add('is-visible');
    }

    function syncFloatingLineHeader() {
        if (lineHeaderFrame) return;
        lineHeaderFrame = window.requestAnimationFrame(updateFloatingLineHeader);
    }

    function bindLineHeaderStickiness() {
        if (!modalBodyEl || modalBodyEl.dataset.lineHeaderStickyBound === 'true') return;

        modalBodyEl.dataset.lineHeaderStickyBound = 'true';
        modalBodyEl.addEventListener('scroll', syncFloatingLineHeader, { passive: true });
        window.addEventListener('resize', syncFloatingLineHeader);
        modalEl.addEventListener('shown.bs.modal', syncFloatingLineHeader);
    }

    function initLineHot() {
        if (lineHot || !window.Handsontable) return;

        lineHot = new Handsontable(hotEl, {
            data: [blankLine()],
            columns: getLineColumns(),
            colHeaders: getLineColumns().map((column) => column.title),
            rowHeaders: false,
            minRows: 1,
            stretchH: 'all',
            width: '100%',
            height: 'auto',
            renderAllRows: true,
            licenseKey: 'non-commercial-and-evaluation',
            contextMenu: ['row_above', 'row_below', 'remove_row'],
            manualColumnResize: true,
            afterGetColHeader(column, th) {
                if (column === 0) {
                    th.classList.add('transaction-line-move-head');
                    th.innerHTML = '<i class="bi bi-arrows-move"></i>';
                    return;
                }

                if (column === getLineColumns().length - 1) {
                    th.classList.add('transaction-line-add-head');
                    th.innerHTML = '<button type="button" class="transaction-line-add-action">+추가</button>';
                }
            },
            beforeKeyDown(event) {
                const selected = lineHot.getSelectedLast();
                if (!selected) return;

                const [row, col] = selected;
                if (!isLineDateCell(row, col)) return;

                if (isManualDateKey(event)) {
                    manualLineDateEdit = true;
                    window.setTimeout(closeHotDatePicker, 0);
                }
            },
            afterBeginEditing(row, col) {
                lineHotEditing = true;
                bindLineDateEscHandler();

                if (isLineDateCell(row, col) && manualLineDateEdit) {
                    manualLineDateEdit = false;
                    bindLineDateInputFormatter();
                    window.requestAnimationFrame(closeHotDatePicker);
                } else {
                    unbindLineDateInputFormatter();
                }
            },
            afterFinishEditing() {
                lineHotEditing = false;
                unbindLineDateInputFormatter();
                unbindLineDateEscHandler();
            },
            beforeChange(changes, source) {
                if (!changes || source === 'date-format') return;

                changes.forEach((change) => {
                    const [, prop, , nextValue] = change;
                    if (prop === 'item_date') {
                        change[3] = formatDateInputValue(nextValue);
                    }

                    if (prop === 'unit_name') {
                        change[3] = normalizeUnitCellValue(nextValue);
                    }

                    if (prop === 'tax_type') {
                        change[3] = normalizeTaxTypeCellValue(nextValue);
                    }
                });
            },
            beforeValidate(value, row, prop) {
                if (prop === 'unit_name' && !isAllowedUnitCellValue(value)) {
                    notifyInvalidUnitValue(value);
                }
            },
            afterChange(changes, source) {
                if (!changes || source === 'calc' || source === 'loadData') return;
                if (source !== 'unit-quick-add') {
                    changes.forEach(([row, prop, previousValue, nextValue]) => {
                        if (prop !== 'unit_name') return;

                        if (nextValue === UNIT_QUICK_ADD_LABEL) {
                            setLineCellValue(row, prop, previousValue || '', 'unit-quick-add-reset');
                            openUnitQuickAdd(row, LINE_UNIT_COL);
                            return;
                        }

                        if (nextValue === UNIT_EMPTY_LABEL) {
                            setLineCellValue(row, prop, '', 'unit-empty');
                        }
                    });
                }

                if (source !== 'date-format') {
                    changes.forEach(([row, prop, previousValue, nextValue]) => {
                        if (prop !== 'item_date') return;
                        const formatted = formatDateInputValue(nextValue);
                        if (formatted !== nextValue) {
                            setLineCellValue(row, prop, formatted, 'date-format');
                        }
                    });
                }
                calculateTotals();
            },
            afterSelection(row, col) {
                if (!isLineDateCell(row, col)) {
                    closeHotDatePicker();
                }
            },
            afterDeselect() {
                lineHotEditing = false;
                unbindLineDateInputFormatter();
                unbindLineDateEscHandler();
            },
            afterRemoveRow() {
                calculateTotals();
                refreshLineHotDimensions();
            },
            afterCreateRow(index, amount) {
                for (let i = index; i < index + amount; i += 1) {
                    lineHot.setSourceDataAtCell(i, 'item_date', document.getElementById('transaction_date')?.value || today());
                    lineHot.setSourceDataAtCell(i, 'tax_type', defaultLineTaxTypeLabel());
                }
                calculateTotals();
                refreshLineHotDimensions();
            },
        });

        bindLineDragEvents();
        bindLineActionEvents();
    }

    function bindLineActionEvents() {
        if (!lineHot || hotEl.dataset.lineActionBound === 'true') return;

        hotEl.dataset.lineActionBound = 'true';
        hotEl.addEventListener('click', (event) => {
            if (event.target.closest('.transaction-line-add-action')) {
                event.preventDefault();
                addLine();
                return;
            }

            const deleteButton = event.target.closest('.transaction-line-delete-action');
            if (!deleteButton) return;

            event.preventDefault();
            const cell = deleteButton.closest('td');
            const coords = cell ? lineHot.getCoords(cell) : null;
            if (!coords || coords.row < 0) return;
            removeLineAt(coords.row);
        });
    }

    function bindLineDragEvents() {
        if (!lineHot || hotEl.dataset.lineDragBound === 'true') return;

        hotEl.dataset.lineDragBound = 'true';
        let draggingRow = null;

        function getLineRowFromElement(element) {
            const cell = element?.closest?.('td');
            if (!cell || !hotEl.contains(cell)) return null;

            const coords = lineHot.getCoords(cell);
            return coords && coords.row >= 0 ? coords.row : null;
        }

        function clearLineDragState() {
            hotEl.querySelectorAll('.transaction-line-move-cell.is-dragging, tr.is-drop-target')
                .forEach((element) => element.classList.remove('is-dragging', 'is-drop-target'));
        }

        function markLineDropTarget(rowIndex) {
            hotEl.querySelectorAll('tr.is-drop-target')
                .forEach((row) => row.classList.remove('is-drop-target'));

            if (rowIndex === null || rowIndex === draggingRow) return;

            const cell = lineHot.getCell(rowIndex, 0);
            cell?.parentElement?.classList.add('is-drop-target');
        }

        function reorderLineRows(fromIndex, toIndex) {
            if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return;

            const rows = lineHot.getSourceData().slice();
            const [moved] = rows.splice(fromIndex, 1);
            rows.splice(toIndex, 0, moved);
            lineHot.loadData(rows);
            lineHot.selectCell(toIndex, 1);
            calculateTotals();
            refreshLineHotDimensions();
        }

        hotEl.addEventListener('pointerdown', (event) => {
            const cell = event.target.closest('td.transaction-line-move-cell');
            if (!cell || lineHot.getSettings().readOnly) return;

            const rowIndex = getLineRowFromElement(cell);
            if (rowIndex === null) return;

            event.preventDefault();
            draggingRow = rowIndex;
            cell.classList.add('is-dragging');
            hotEl.classList.add('is-line-dragging');
            try {
                hotEl.setPointerCapture?.(event.pointerId);
            } catch (error) {
                // Some browsers only allow capture on the direct event target.
            }
        });

        hotEl.addEventListener('pointermove', (event) => {
            if (draggingRow === null) return;

            const element = document.elementFromPoint(event.clientX, event.clientY);
            markLineDropTarget(getLineRowFromElement(element));
        });

        hotEl.addEventListener('pointerup', (event) => {
            if (draggingRow === null) return;

            const fromIndex = draggingRow;
            const element = document.elementFromPoint(event.clientX, event.clientY);
            const toIndex = getLineRowFromElement(element);

            draggingRow = null;
            hotEl.classList.remove('is-line-dragging');
            clearLineDragState();

            if (toIndex !== null) {
                reorderLineRows(fromIndex, toIndex);
            }
        });

        hotEl.addEventListener('pointercancel', () => {
            draggingRow = null;
            hotEl.classList.remove('is-line-dragging');
            clearLineDragState();
        });
    }

    function initTransactionDatePicker() {
        if (!pickerLayerEl || transactionDatePicker) return;

        transactionDatePicker = AdminPicker.create({
            type: 'today',
            container: pickerLayerEl,
        });

        transactionDatePicker.subscribe((state, finalDate) => {
            if (!(finalDate instanceof Date)) return;

            transactionDateEl.value = formatDate(finalDate);
            transactionDateEl.dispatchEvent(new Event('change', { bubbles: true }));
            transactionDatePicker.close?.();
        });
    }

    function openTransactionDatePicker() {
        if (!transactionDateEl || !pickerLayerEl) return;

        initTransactionDatePicker();
        transactionDatePicker?.open?.({ anchor: transactionDateEl });
    }

    function setLines(items = []) {
        initLineHot();
        const rows = (Array.isArray(items) && items.length ? items : [blankLine()]).map((item) => calculateLine(normalizeLine(item)));
        lineHot?.loadData(rows);
        calculateTotals();
        refreshLineHotDimensions();
    }

    function addLine() {
        initLineHot();
        lineHot?.alter('insert_row_below', Math.max(lineHot.countRows() - 1, 0), 1);
        const rowIndex = Math.max(lineHot.countRows() - 1, 0);
        Object.entries(blankLine()).forEach(([key, value]) => lineHot.setSourceDataAtCell(rowIndex, key, value));
        lineHot?.selectCell(rowIndex, 1);
        calculateTotals();
        refreshLineHotDimensions();
    }

    function removeLineAt(rowIndex) {
        if (!lineHot) return;
        lineHot.alter('remove_row', rowIndex, 1);
        if (lineHot.countRows() === 0) {
            setLines([blankLine()]);
        }
        calculateTotals();
        refreshLineHotDimensions();
    }

    function collectLines() {
        initLineHot();
        return (lineHot?.getSourceData() || [])
            .map((row) => calculateLine(normalizeLine(row)))
            .filter((row) => String(row.item_name || '').trim() !== '')
            .map((row, index) => ({
                sort_no: index + 1,
                item_date: row.item_date || document.getElementById('transaction_date')?.value || today(),
                item_name: String(row.item_name || '').trim(),
                specification: String(row.specification || '').trim(),
                unit_name: String(row.unit_name || '').trim(),
                quantity: row.quantity,
                unit_price: row.unit_price,
                foreign_unit_price: usesForeignCurrency() ? row.foreign_unit_price : '',
                foreign_amount: usesForeignCurrency() ? row.foreign_amount : '',
                tax_type: normalizeTaxTypeCode(row.tax_type || 'TAXABLE') || 'TAXABLE',
                description: String(row.description || '').trim(),
            }));
    }

    function usesForeignCurrency() {
        return Boolean(importToggle?.checked);
    }

    function syncConditionalPanels() {
        const fileEnabled = Boolean(fileToggle?.checked);
        filePanel?.classList.toggle('d-none', !fileEnabled);
        if (fileInput) {
            fileInput.disabled = !fileEnabled;
        }

        const importEnabled = usesForeignCurrency();
        modalEl.querySelectorAll('.transaction-currency-field, .transaction-exchange-field').forEach((field) => {
            field.classList.toggle('d-none', !importEnabled);
        });
        lineHot?.updateSettings({
            columns: getLineColumns(),
            colHeaders: getLineColumns().map((column) => column.title),
        });

        if (!importEnabled) {
            setCurrencyValue('');
            if (exchangeRateEl) exchangeRateEl.value = '';
        }

        applyForeignTaxTypeToLines();
        calculateTotals();
    }

    function setBusinessUnitValue(value) {
        setCodeSelectValue('business_unit', value || '');
    }

    function setTransactionTypeValue(value) {
        setCodeSelectValue('transaction_type', value || '');
    }

    function setCurrencyValue(value) {
        setCodeSelectValue('currency', value || '');
    }

    function setCodeSelectValue(selectId, value) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const nextValue = value || '';
        select.value = nextValue;

        if (window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).val(nextValue).trigger('change.select2');
        }
    }

    function initClientSelect() {
        if (!clientSelectEl || !window.jQuery?.fn?.select2) return;

        AdminPicker.select2Ajax(clientSelectEl, {
            url: API.clientSearch,
            placeholder: '거래처검색',
            minimumInputLength: 0,
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
            templateResult(item) {
                if (!item.id) return item.text;

                if (item.isQuickCreate) {
                    return window.jQuery(
                        '<div class="select2-action-option"><span class="fw-semibold text-primary">+ 신규 거래처 추가</span></div>'
                    );
                }

                return item.text;
            },
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                    is_active: 1,
                };
            },
            processResults(data, params) {
                const rows = data?.results ?? data?.data ?? [];
                const term = String(params?.term ?? '').trim();

                return {
                    results: [
                        ...rows.map((row) => ({
                            id: String(row.id ?? ''),
                            text: row.text || row.client_name || row.company_name || '',
                        })).filter((row) => row.id !== ''),
                        {
                            id: '__quick_client__',
                            text: '+ 신규 거래처 추가',
                            isQuickCreate: true,
                            term,
                        },
                    ],
                };
            },
        });

        window.jQuery(clientSelectEl)
            .off('select2:select.transactionClient')
            .on('select2:select.transactionClient', (event) => {
                const item = event.params?.data;
                if (!item) return;

                if (item.id === '__quick_client__') {
                    clearClientSelect();
                    window.jQuery(clientSelectEl).select2('close');
                    openTransactionClientQuickCreate(item.term || '');
                }
            });
    }

    function clearClientSelect() {
        if (!clientSelectEl) return;

        clientSelectEl.value = '';
        if (window.jQuery?.fn?.select2) {
            window.jQuery(clientSelectEl).val(null).trigger('change');
        }
    }

    function setClientSelectValue(value = '', text = '') {
        if (!clientSelectEl) return;

        const clientId = String(value || '').trim();
        if (clientId === '') {
            clearClientSelect();
            return;
        }

        const label = String(text || clientId).trim();
        if (window.jQuery?.fn?.select2) {
            const option = new Option(label, clientId, true, true);
            window.jQuery(clientSelectEl)
                .find('option')
                .filter((index, item) => item.value === clientId)
                .remove();
            window.jQuery(clientSelectEl).append(option).val(clientId).trigger('change');
            return;
        }

        clientSelectEl.value = clientId;
    }

    function openTransactionClientQuickCreate(defaultName = '') {
        openClientQuickCreate({
            select: clientSelectEl,
            title: '신규 거래처 추가',
            initialValues: {
                client_name: defaultName,
            },
            getOptionText(values) {
                return values.client_name || values.company_name || '';
            },
            onSuccess() {
                notify('success', '거래처가 등록되었습니다.');
            },
        });
    }

    function initProjectSelect() {
        if (!projectSelectEl || !window.jQuery?.fn?.select2) return;

        AdminPicker.select2Ajax(projectSelectEl, {
            url: API.projectSearch,
            placeholder: '프로젝트 검색',
            minimumInputLength: 0,
            dropdownParent: window.jQuery(modalEl),
            width: '100%',
            dataBuilder(params) {
                return {
                    q: params.term || '',
                    limit: 20,
                };
            },
            processResults(data) {
                const rows = data?.results ?? data?.data ?? [];

                return {
                    results: [
                        { id: '__none__', text: '선택(없음)', isNone: true },
                        ...rows.map((row) => ({
                            id: String(row.id ?? ''),
                            text: row.text || row.project_name || row.construction_name || '',
                        })).filter((row) => row.id !== ''),
                    ],
                };
            },
        });

        window.jQuery(projectSelectEl)
            .off('select2:select.transactionProject')
            .on('select2:select.transactionProject', (event) => {
                const item = event.params?.data;
                if (!item) return;

                if (item.id === '__none__') {
                    clearProjectSelect();
                }
            });
    }

    function clearProjectSelect() {
        if (!projectSelectEl) return;

        projectSelectEl.value = '';
        if (window.jQuery?.fn?.select2) {
            window.jQuery(projectSelectEl).val(null).trigger('change');
        }
    }

    function setProjectSelectValue(value = '', text = '') {
        if (!projectSelectEl) return;

        const projectId = String(value || '').trim();
        if (projectId === '') {
            clearProjectSelect();
            return;
        }

        const label = String(text || projectId).trim();
        if (window.jQuery?.fn?.select2) {
            const option = new Option(label, projectId, true, true);
            window.jQuery(projectSelectEl)
                .find('option')
                .filter((index, item) => item.value === projectId)
                .remove();
            window.jQuery(projectSelectEl).append(option).val(projectId).trigger('change');
            return;
        }

        projectSelectEl.value = projectId;
    }

    function renderFiles(files = currentFiles) {
        if (!fileListEl) return;

        const existingFiles = Array.isArray(files) ? files : [];
        const baseRows = [
            ...existingFiles.map((file) => ({
                type: 'existing',
                key: String(file.id || ''),
                orderKey: `existing:${String(file.id || '')}`,
                id: String(file.id || ''),
                file_name: file.file_name || file.name || '',
                file_size: file.file_size || file.size || 0,
                file_url: file.id ? `/api/ledger/transaction/file?id=${encodeURIComponent(String(file.id))}` : '',
            })),
            ...pendingFiles.map((file) => ({
                type: 'new',
                key: file._transactionTempId,
                orderKey: `new:${file._transactionTempId}`,
                file_name: file.name,
                file_size: file.size,
            })),
        ];
        const rowMap = new Map(baseRows.map((row) => [row.orderKey, row]));
        const orderedRows = fileRowOrder.length > 0
            ? fileRowOrder.map((key) => rowMap.get(key)).filter(Boolean)
            : [];
        const orderedKeys = new Set(orderedRows.map((row) => row.orderKey));
        const rows = [
            ...orderedRows,
            ...baseRows.filter((row) => !orderedKeys.has(row.orderKey)),
        ];
        updateFileDropzone(rows);

        if (rows.length === 0) {
            fileListEl.innerHTML = '';
            return;
        }

        fileListEl.innerHTML = `
            <div class="transaction-file-table" role="table" aria-label="거래 증빙 파일">
                <div class="transaction-file-row transaction-file-head" role="row">
                    <span class="transaction-file-drag-head"><i class="bi bi-arrows-move"></i></span>
                    <span>순번</span>
                    <span>파일명</span>
                    <span>크기</span>
                    <span>관리</span>
                </div>
                ${rows.map((file, index) => {
                    const id = escapeHtml(file.id || '');
                    const key = escapeHtml(file.key || '');
                    const type = escapeHtml(file.type || '');
                    const name = escapeHtml(file.file_name || `파일 ${index + 1}`);
                    const fileUrl = file.type === 'existing' && file.file_url
                        ? escapeHtml(file.file_url)
                        : '';
                    const nameContent = fileUrl
                        ? `<a class="transaction-file-link" href="${fileUrl}" target="_blank" rel="noopener">${name}</a>`
                        : name;
                    const sizeText = escapeHtml(formatBytes(file.file_size));
                    const order = index + 1;
                    const orderInput = file.type === 'existing'
                        ? `<input type="hidden" name="file_orders[${id}]" value="${order}">`
                        : `<input type="hidden" name="new_file_orders[]" value="${order}">`;
                    const deleteAttrs = file.type === 'existing'
                        ? `data-id="${id}"`
                        : `data-temp-id="${key}"`;

                    return `
                    <div class="transaction-file-row transaction-file-item"
                         role="row"
                         draggable="true"
                         data-file-type="${type}"
                         data-file-key="${key}"
                         data-order-key="${escapeHtml(file.orderKey || '')}">
                        <span class="transaction-file-drag" aria-label="순서 변경"><i class="bi bi-list"></i></span>
                        <span class="transaction-file-order">${order}${orderInput}</span>
                        <span class="transaction-file-name" title="${name}">${nameContent}</span>
                        <span class="transaction-file-size">${sizeText}</span>
                        <button type="button"
                                class="transaction-file-delete btn-delete-transaction-file"
                                ${deleteAttrs}>-삭제</button>
                    </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function syncFileInputFromPending() {
        if (!fileInput || typeof DataTransfer === 'undefined') return;

        const transfer = new DataTransfer();
        pendingFiles.forEach((file) => transfer.items.add(file));
        fileInput.files = transfer.files;
    }

    function assignPendingFiles(files) {
        pendingFiles = Array.from(files || []).map((file) => {
            if (!file._transactionTempId) {
                Object.defineProperty(file, '_transactionTempId', {
                    value: `new-${Date.now()}-${Math.random().toString(36).slice(2)}`,
                    enumerable: false,
                });
            }
            return file;
        });
        syncFileInputFromPending();
        renderFiles(currentFiles);
    }

    function appendPendingFiles(files) {
        const appended = Array.from(files || []);
        if (appended.length === 0) return;

        assignPendingFiles([...pendingFiles, ...appended]);
    }

    function reorderFilesByDom() {
        if (!fileListEl) return;

        const currentMap = new Map(currentFiles.map((file) => [String(file.id || ''), file]));
        const pendingMap = new Map(pendingFiles.map((file) => [file._transactionTempId, file]));
        const nextCurrent = [];
        const nextPending = [];

        fileListEl.querySelectorAll('.transaction-file-item').forEach((row) => {
            const type = row.dataset.fileType || '';
            const key = row.dataset.fileKey || '';

            if (type === 'existing' && currentMap.has(key)) {
                nextCurrent.push(currentMap.get(key));
            }
            if (type === 'new' && pendingMap.has(key)) {
                nextPending.push(pendingMap.get(key));
            }
        });

        currentFiles = nextCurrent;
        pendingFiles = nextPending;
        fileRowOrder = Array.from(fileListEl.querySelectorAll('.transaction-file-item'))
            .map((row) => row.dataset.orderKey || '')
            .filter(Boolean);
        syncFileInputFromPending();
        renderFiles(currentFiles);
    }

    function removePendingFile(tempId) {
        pendingFiles = pendingFiles.filter((file) => file._transactionTempId !== tempId);
        fileRowOrder = fileRowOrder.filter((key) => key !== `new:${tempId}`);
        syncFileInputFromPending();
        renderFiles(currentFiles);
    }

    async function loadTransactionFilePolicy() {
        if (!fileDropzoneEl) return;

        try {
            const list = await fetchJson(API.filePolicyList);
            const policies = Array.isArray(list) ? list : (list.data || []);
            const policy = policies.find((item) => String(item.policy_key || '') === 'transaction_evidence');

            if (!policy) {
                fileDropzoneEmptyText = '파일을 드래그해서 첨부하세요';
                updateFileDropzone([]);
                return;
            }

            const ext = String(policy.allowed_ext || '').trim();
            const maxSize = Number(policy.max_size_mb || 0);
            const description = String(policy.description || '거래 증빙 파일 업로드').trim();

            if (fileInput && ext) {
                fileInput.accept = ext.split(',').map((item) => `.${item.trim().replace(/^\./, '')}`).join(',');
            }

            fileDropzoneEmptyText = `${description} / ${ext || '허용 확장자 확인'} / 최대 ${maxSize || '-'}MB`;
            if (!currentFiles.length && !pendingFiles.length) {
                updateFileDropzone([]);
            }
        } catch (error) {
            fileDropzoneEmptyText = '파일을 드래그해서 첨부하세요';
            updateFileDropzone([]);
        }
    }

    function bindFileReorderEvents() {
        if (!fileListEl) return;

        let draggingRow = null;

        fileListEl.addEventListener('dragstart', (event) => {
            const row = event.target.closest('.transaction-file-item');
            if (!row) return;

            draggingRow = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.fileKey || '');
        });

        fileListEl.addEventListener('dragover', (event) => {
            const row = event.target.closest('.transaction-file-item');
            if (!row || !draggingRow || row === draggingRow) return;

            event.preventDefault();
            const rect = row.getBoundingClientRect();
            const after = event.clientY > rect.top + rect.height / 2;
            row.parentNode.insertBefore(draggingRow, after ? row.nextSibling : row);
        });

        fileListEl.addEventListener('dragend', () => {
            if (draggingRow) {
                draggingRow.classList.remove('is-dragging');
                draggingRow = null;
                reorderFilesByDom();
            }
        });
    }

    function markFileDeleted(fileId) {
        if (!fileId) return;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_file_ids[]';
        input.value = fileId;
        input.dataset.generatedDeleteFile = 'true';
        form.appendChild(input);

        currentFiles = currentFiles.filter((file) => String(file.id || '') !== String(fileId));
        fileRowOrder = fileRowOrder.filter((key) => key !== `existing:${fileId}`);
        renderFiles(currentFiles);
    }

    function formatVoucherStatus(value) {
        const status = String(value || 'draft').toLowerCase();
        return {
            draft: '임시저장',
            confirmed: '확정',
            posted: '전기',
            closed: '마감',
        }[status] || status;
    }

    function renderVoucherState(transaction = {}) {
        const links = Array.isArray(transaction.linked_vouchers) ? transaction.linked_vouchers : [];
        const savedId = String(transaction.id || document.getElementById('transaction_id')?.value || '').trim();
        const status = String(transaction.match_status || document.getElementById('transaction_match_status')?.value || 'none');
        const isLinked = links.length > 0 || status === 'matched';

        if (voucherStatusEl) {
            voucherStatusEl.className = `transaction-status ${isLinked ? 'matched' : 'none'}`;
            voucherStatusEl.textContent = isLinked ? '연결' : '미연결';
        }

        if (!voucherSummaryEl) return;

        if (!savedId) {
            voucherSummaryEl.textContent = '저장 후 전표를 생성하거나 기존 전표와 연결할 수 있습니다.';
            return;
        }

        if (!links.length) {
            voucherSummaryEl.textContent = '연결된 전표가 없습니다.';
            return;
        }

        voucherSummaryEl.innerHTML = links.map((voucher) => {
            const voucherId = escapeHtml(voucher.id || '');
            const label = escapeHtml(voucher.voucher_no || voucher.sort_no || voucherId);
            const date = escapeHtml(voucher.voucher_date || '');
            const voucherStatus = escapeHtml(formatVoucherStatus(voucher.status));
            const summary = escapeHtml(voucher.summary_text || '');

            return `
                <div class="transaction-voucher-item">
                    <strong>${label}</strong>
                    <span>${date}</span>
                    <span>${voucherStatus}</span>
                    <span>${summary}</span>
                    <input type="hidden" class="linked-voucher-id" value="${voucherId}">
                </div>
            `;
        }).join('');
    }

    function getTransactionModalSnapshot() {
        const fieldNames = [
            'id',
            'status',
            'match_status',
            'transaction_date',
            'business_unit',
            'transaction_type',
            'client_id',
            'project_id',
            'description',
            'currency',
            'exchange_rate',
            'note',
            'memo',
        ];

        const fields = {};
        fieldNames.forEach((name) => {
            const field = form.elements[name];
            fields[name] = field ? String(field.value ?? '') : '';
        });

        const lines = (lineHot?.getSourceData() || []).map((row) => ({
            item_date: String(row?.item_date ?? ''),
            item_name: String(row?.item_name ?? ''),
            specification: String(row?.specification ?? ''),
            unit_name: String(row?.unit_name ?? ''),
            quantity: String(row?.quantity ?? ''),
            unit_price: String(row?.unit_price ?? ''),
            foreign_unit_price: String(row?.foreign_unit_price ?? ''),
            foreign_amount: String(row?.foreign_amount ?? ''),
            tax_type: String(row?.tax_type ?? ''),
            description: String(row?.description ?? ''),
        }));

        const deleteFileIds = Array.from(form.querySelectorAll('[data-generated-delete-file="true"]'))
            .map((input) => String(input.value || ''))
            .sort();

        return JSON.stringify({
            fields,
            toggles: {
                is_import: Boolean(importToggle?.checked),
                use_file_reference: Boolean(fileToggle?.checked),
            },
            lines,
            currentFiles: currentFiles.map((file) => String(file.id || file.file_name || '')).sort(),
            pendingFiles: pendingFiles.map((file) => ({
                name: String(file.name || ''),
                size: Number(file.size || 0),
                lastModified: Number(file.lastModified || 0),
            })),
            fileRowOrder: fileRowOrder.slice(),
            deleteFileIds,
            voucher_id: String(voucherIdInput?.value || ''),
        });
    }

    function markTransactionModalClean() {
        modalBaselineSnapshot = getTransactionModalSnapshot();
    }

    function hasTransactionModalChanges() {
        if (!modalBaselineSnapshot) return false;
        return modalBaselineSnapshot !== getTransactionModalSnapshot();
    }

    async function postTransactionAction(url, payload = {}) {
        const formData = new FormData();
        Object.entries(payload).forEach(([key, value]) => formData.set(key, value ?? ''));
        const json = await fetchJson(url, { method: 'POST', body: formData });

        if (json.data) {
            document.getElementById('transaction_match_status').value = json.data.match_status || 'matched';
            renderVoucherState(json.data);
        }

        reloadTable();
        return json;
    }

    async function createVoucherForCurrentTransaction() {
        const id = document.getElementById('transaction_id')?.value || '';
        if (!id) {
            notify('warning', '거래 저장 후 전표를 생성할 수 있습니다.');
            return;
        }

        const json = await postTransactionAction(API.createVoucher, { transaction_id: id });
        notify('success', json.message || '전표가 생성되었습니다.');
    }

    async function linkVoucherToCurrentTransaction() {
        const transactionId = document.getElementById('transaction_id')?.value || '';
        const voucherId = voucherIdInput?.value?.trim() || '';
        if (!transactionId || !voucherId) {
            notify('warning', '거래 저장 후 연결할 전표 ID를 입력해 주세요.');
            return;
        }

        const json = await postTransactionAction(API.linkVoucher, { transaction_id: transactionId, voucher_id: voucherId });
        notify('success', json.message || '전표가 연결되었습니다.');
    }

    async function unlinkVoucherFromCurrentTransaction() {
        const transactionId = document.getElementById('transaction_id')?.value || '';
        const voucherId = voucherIdInput?.value?.trim()
            || voucherSummaryEl?.querySelector('.linked-voucher-id')?.value
            || '';
        if (!transactionId) {
            notify('warning', '거래를 먼저 저장해 주세요.');
            return;
        }

        const json = await postTransactionAction(API.unlinkVoucher, { transaction_id: transactionId, voucher_id: voucherId });
        document.getElementById('transaction_match_status').value = 'none';
        notify('success', json.message || '전표 연결이 해제되었습니다.');
    }

    function resetModal() {
        form.reset();
        form.querySelectorAll('[data-generated-delete-file="true"]').forEach((input) => input.remove());
        document.getElementById('transaction_id').value = '';
        document.getElementById('transaction_date').value = today();
        setBusinessUnitValue('');
        setTransactionTypeValue('');
        setCurrencyValue('');
        if (exchangeRateEl) exchangeRateEl.value = '';
        document.getElementById('transaction_status').value = 'draft';
        document.getElementById('transaction_match_status').value = 'none';
        updateTransactionStatusBadge('draft');
        document.getElementById('transactionModalLabel').textContent = '거래 등록';
        if (fileToggle) fileToggle.checked = false;
        if (importToggle) importToggle.checked = false;
        clearClientSelect();
        clearProjectSelect();
        deleteBtn?.classList.add('d-none');
        currentFiles = [];
        pendingFiles = [];
        fileRowOrder = [];
        if (fileInput) fileInput.value = '';
        setLines([blankLine()]);
        renderFiles([]);
        renderVoucherState({});
        syncConditionalPanels();
        setTransactionModalEditable(true);
    }

    function openCreateModal() {
        resetModal();
        markTransactionModalClean();
        modal?.show();
        setTimeout(refreshLineHotDimensions, 150);
    }

    async function openDetail(id) {
        if (!id) return;

        const json = await fetchJson(`${API.detail}?id=${encodeURIComponent(id)}`);
        const data = json.data || {};
        resetModal();

        document.getElementById('transaction_id').value = data.id || '';
        document.getElementById('transaction_date').value = data.transaction_date || today();
        setBusinessUnitValue(data.business_unit || '');
        setTransactionTypeValue(data.transaction_type || '');
        setCurrencyValue(data.currency || '');
        if (exchangeRateEl) exchangeRateEl.value = data.exchange_rate ? formatNumber(data.exchange_rate) : '';
        setClientSelectValue(data.client_id || '', data.client_name || data.client_id || '');
        setProjectSelectValue(data.project_id || '', data.project_name || data.project_id || '');
        document.getElementById('transaction_description').value = data.description || '';
        const transactionStatus = normalizeTransactionStatus(data.status);
        document.getElementById('transaction_status').value = transactionStatus;
        updateTransactionStatusBadge(transactionStatus);
        document.getElementById('transaction_match_status').value = data.match_status || 'none';
        document.getElementById('transaction_note').value = data.note || '';
        document.getElementById('transaction_memo').value = data.memo || '';
        document.getElementById('transactionModalLabel').textContent = '거래 수정';
        if (importToggle) {
            const currency = String(data.currency || '').trim().toUpperCase();
            const hasForeignLines = (Array.isArray(data.items) ? data.items : [])
                .some((item) => numberValue(item.foreign_unit_price) || numberValue(item.foreign_amount));
            importToggle.checked = hasForeignLines || (currency !== '' && currency !== 'KRW') || Boolean(data.exchange_rate);
        }
        deleteBtn?.classList.remove('d-none');

        setLines(data.items || []);
        currentFiles = Array.isArray(data.files) ? data.files : [];
        pendingFiles = [];
        fileRowOrder = [];
        if (fileInput) fileInput.value = '';
        if (fileToggle) fileToggle.checked = currentFiles.length > 0;
        renderFiles(currentFiles);
        renderVoucherState(data);
        syncConditionalPanels();
        setTransactionModalEditable(transactionStatus !== 'approved');
        markTransactionModalClean();
        modal?.show();
        setTimeout(refreshLineHotDimensions, 150);
    }

    async function saveTransaction() {
        const lines = collectLines();
        if (lines.length === 0) {
            notify('warning', '거래내역을 1개 이상 입력해 주세요.');
            return;
        }

        const formData = new FormData(form);
        formData.set('items', JSON.stringify(lines));
        if (usesForeignCurrency()) {
            const rawExchangeRate = String(exchangeRateEl?.value || '').trim();
            if (parseNumber(rawExchangeRate) <= 0) {
                notify('warning', '외화사용여부를 선택한 경우 환율을 입력해 주세요.');
                return;
            }
            formData.set('exchange_rate', rawExchangeRate === '' ? '' : String(parseNumber(rawExchangeRate)));
        } else {
            formData.set('currency', '');
            formData.set('exchange_rate', '');
        }

        await fetchJson(API.save, { method: 'POST', body: formData });

        notify('success', '거래가 저장되었습니다.');
        allowModalClose = true;
        markTransactionModalClean();
        modal?.hide();
        reloadTable();
    }

    async function deleteTransaction(id) {
        if (!id) return;

        const formData = new FormData();
        formData.set('transaction_id', id);
        await fetchJson(API.remove, { method: 'POST', body: formData });
        notify('success', '거래가 삭제되었습니다.');
        allowModalClose = true;
        markTransactionModalClean();
        modal?.hide();
        reloadTable();
    }

    window.TrashColumns = window.TrashColumns || {};
    window.TrashColumns.transaction = function (row = {}) {
        const id = escapeHtml(row.id || '');
        return `
            <td>${escapeHtml(row.transaction_date || '')}</td>
            <td>${escapeHtml(row.client_name || row.client_id || '')}</td>
            <td>${escapeHtml(row.description || '')}</td>
            <td class="text-end">${escapeHtml(formatAmount(row.total_amount || 0))}</td>
            <td>${renderMatchStatus(row.match_status)}</td>
            <td>${escapeHtml(row.deleted_at || '')}</td>
            <td>${escapeHtml(row.deleted_by_name || row.deleted_by || '')}</td>
            <td>
                <button type="button" class="btn btn-success btn-sm btn-restore" data-id="${id}">복원</button>
                <button type="button" class="btn btn-danger btn-sm btn-purge" data-id="${id}">삭제</button>
            </td>
        `;
    };

    function bindEvents() {
        importToggle?.addEventListener('change', syncConditionalPanels);
        fileToggle?.addEventListener('change', syncConditionalPanels);
        exchangeRateEl?.addEventListener('change', calculateTotals);
        exchangeRateEl?.addEventListener('input', calculateTotals);
        fileInput?.addEventListener('change', () => assignPendingFiles(fileInput.files));

        fileDropzoneEl?.addEventListener('click', () => {
            fileInput?.click();
        });

        fileDropzoneEl?.addEventListener('dragover', (event) => {
            event.preventDefault();
            fileDropzoneEl.classList.add('is-drag-over');
        });

        fileDropzoneEl?.addEventListener('dragleave', (event) => {
            if (!fileDropzoneEl.contains(event.relatedTarget)) {
                fileDropzoneEl.classList.remove('is-drag-over');
            }
        });

        fileDropzoneEl?.addEventListener('drop', (event) => {
            event.preventDefault();
            fileDropzoneEl.classList.remove('is-drag-over');
            appendPendingFiles(event.dataTransfer?.files || []);
        });

        transactionDateEl?.addEventListener('input', () => {
            transactionDateEl.value = formatDateInputValue(transactionDateEl.value);
        });

        transactionDateEl?.addEventListener('change', () => {
            transactionDateEl.value = formatDateInputValue(transactionDateEl.value);
            const date = transactionDateEl.value || today();
            (lineHot?.getSourceData() || []).forEach((row, index) => {
                if (!row.item_date) setLineCellValue(index, 'item_date', date, 'date-sync');
            });
        });

        modalEl.addEventListener('click', (event) => {
            if (!event.target.closest('.date-icon')) return;
            event.preventDefault();
            openTransactionDatePicker();
        });

        modalEl.addEventListener('hide.bs.modal', (event) => {
            if (allowModalClose || !hasTransactionModalChanges()) {
                return;
            }

            const ok = window.confirm('입력 중인 거래 내용이 있습니다. 저장하지 않고 닫으시겠습니까?');
            if (!ok) {
                event.preventDefault();
                return;
            }

            allowModalClose = true;
        });

        modalEl.addEventListener('esc:modal-before-close', (event) => {
            if (event.detail?.modal !== modalEl || allowModalClose || !hasTransactionModalChanges()) {
                return;
            }

            const ok = window.confirm('입력 중인 거래 내용이 있습니다. 저장하지 않고 닫으시겠습니까?');
            if (!ok) {
                event.preventDefault();
                return;
            }

            allowModalClose = true;
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            allowModalClose = false;
            modalBaselineSnapshot = '';
            lineHotEditing = false;
            hideFloatingLineHeader();
            unbindLineDateInputFormatter();
            unbindLineDateEscHandler();
            closeHotDatePicker();
            transactionDatePicker?.close?.();
        });

        fileListEl?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-delete-transaction-file');
            if (!button) return;

            if (button.dataset.id) {
                markFileDeleted(button.dataset.id || '');
                return;
            }

            if (button.dataset.tempId) {
                removePendingFile(button.dataset.tempId || '');
            }
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            void saveTransaction();
        });

        deleteBtn?.addEventListener('click', () => {
            const id = document.getElementById('transaction_id')?.value || '';
            if (id && window.confirm('거래를 삭제하시겠습니까?')) {
                void deleteTransaction(id);
            }
        });

        createVoucherBtn?.addEventListener('click', () => void createVoucherForCurrentTransaction());
        linkVoucherBtn?.addEventListener('click', () => void linkVoucherToCurrentTransaction());
        unlinkVoucherBtn?.addEventListener('click', () => void unlinkVoucherFromCurrentTransaction());
        unitCodeSelectEl?.addEventListener('change', applyPendingUnitSelection);

        document.addEventListener('trash:changed', (event) => {
            if (event.detail?.type === 'transaction') reloadTable();
        });

        document.addEventListener('trash:detail-render', (event) => {
            const detail = event.detail || {};
            if (detail.type !== 'transaction') return;

            const detailEl = detail.modal?.querySelector('#transaction-trash-detail');
            const row = detail.data || {};
            if (!detailEl) return;

            detailEl.innerHTML = `
                <div class="transaction-trash-detail">
                    <dl class="row mb-0 small">
                        <dt class="col-4">거래일자</dt>
                        <dd class="col-8">${escapeHtml(row.transaction_date || '-')}</dd>
                        <dt class="col-4">거래처</dt>
                        <dd class="col-8">${escapeHtml(row.client_name || row.client_id || '-')}</dd>
                        <dt class="col-4">프로젝트</dt>
                        <dd class="col-8">${escapeHtml(row.project_name || row.project_id || '-')}</dd>
                        <dt class="col-4">적요</dt>
                        <dd class="col-8">${escapeHtml(row.description || '-')}</dd>
                        <dt class="col-4">금액</dt>
                        <dd class="col-8">${escapeHtml(formatAmount(row.total_amount || 0))}</dd>
                        <dt class="col-4">전표연결</dt>
                        <dd class="col-8">${renderMatchStatus(row.match_status)}</dd>
                        <dt class="col-4">삭제일시</dt>
                        <dd class="col-8">${escapeHtml(row.deleted_at || '-')}</dd>
                        <dt class="col-4">삭제자</dt>
                        <dd class="col-8">${escapeHtml(row.deleted_by_name || row.deleted_by || '-')}</dd>
                    </dl>
                </div>
            `;
        });
    }

    async function boot() {
        initTransactionDatePicker();
        initLineHot();
        bindLineHeaderStickiness();
        initClientSelect();
        initProjectSelect();
        bindFileReorderEvents();
        void loadTransactionFilePolicy();
        bindNumberInput(exchangeRateEl);
        void initUnitCodeOptions();
        void initCodeSelectControls(document.getElementById('clientModal'));
        await initCodeSelectControls(modalEl);
        initTransactionTable();
        bindEvents();
        syncConditionalPanels();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
