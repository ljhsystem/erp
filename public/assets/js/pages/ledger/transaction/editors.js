import { actorNameField } from '/public/assets/js/common/actor.js';
import { formatDateInputValue as formatDateOnlyValue } from '/public/assets/js/common/format.js';
import {
    readDataTableSettingsState,
    resolveDataTableColumnDisplayName,
    resolveDataTableColumnRequirementPolicy,
} from '/public/assets/js/common/datatable/dataTableSettings.js';

export function registerEditors(ctx) {
    const {
        dateStringEditor,
        selectEditor,
        gridNumberFormatter,
        gridNumberParser,
        formatDateInputValue,
        formatNumber,
        parseNumber,
        createCodeSelect,
        getCodeName,
        onCodeOptionsLoaded,
        openCodeQuickModal,
        form,
        deleteBtn,
        AG_GRID_STYLE_URL,
        AG_GRID_THEME_URL,
        AG_GRID_SCRIPT_URL,
        headerForeignAmountEl,
        headerSupplyAmountEl,
        headerSettlementAmountEl,
        headerFinalAmountEl,
    } = ctx;
    let unitOptions = ctx.unitOptions;
    let settlementTypeOptions = ctx.settlementTypeOptions;
    let amountSignOptions = ctx.amountSignOptions;
    let unitCodeSelectEl = ctx.unitCodeSelectEl;
    let settlementTypeCodeSelectEl = ctx.settlementTypeCodeSelectEl;
    let amountSignCodeSelectEl = ctx.amountSignCodeSelectEl;
    let pendingUnitCell = ctx.pendingUnitCell;
    let lastInvalidUnitNotice = ctx.lastInvalidUnitNotice;
    let activeLineDateInput = ctx.activeLineDateInput;
    let activeLineDateInputHandler = ctx.activeLineDateInputHandler;
    let lineDateEscHandler = ctx.lineDateEscHandler;
    const usesForeignCurrency = (...args) => ctx.usesForeignCurrency(...args);

    const HOT_DATE_PICKER_CONFIG = {
        firstDay: 0,
        showMonthAfterYear: true,
        yearSuffix: '',
        onOpen: decorateGridDatePicker,
        onDraw: decorateGridDatePicker,
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
    const UNIT_EMPTY_LABEL = '선택(없음)';
    const UNIT_QUICK_ADD_LABEL = '+기준추가';

    const TRANSACTION_META_DOMAIN = 'transaction-header';
    const TRANSACTION_TABLE_SETTINGS_KEY = 'datatable.settings.ledger.transaction.transaction-table.v1';
    const TRANSACTION_TABLE_PAGE_KEY = 'ledger.transaction';
    const TRANSACTION_HEADER_LABEL_FIELDS = Object.freeze([
        { field: 'business_unit', selector: '#business_unit', textSelector: '.transaction-field-label-text' },
        { field: 'transaction_direction', selector: '#transaction_direction', textSelector: '.transaction-field-label-text' },
        { field: 'operation_type', selector: '#operation_type', textSelector: '.transaction-field-label-text' },
        { field: 'currency', selector: '#currency', textSelector: '.transaction-field-label-text' },
        { field: 'client_id', selector: '#client_id', textSelector: '.transaction-field-label-text' },
        { field: 'project_id', selector: '#project_id', textSelector: '.transaction-field-label-text' },
        { field: 'bank_account_id', selector: '#bank_account_id', textSelector: '.transaction-field-label-text' },
        { field: 'card_id', selector: '#card_id', textSelector: '.transaction-field-label-text' },
        { field: 'team_id', selector: '#team_id', textSelector: '.transaction-field-label-text' },
        { field: 'employee_id', selector: '#employee_id', textSelector: '.transaction-field-label-text' },
        { field: 'transaction_date', selector: '#transaction_date' },
        { field: 'description', selector: '#transaction_description' },
        { field: 'exchange_rate', selector: '#exchange_rate' },
        { field: 'foreign_amount', selector: '#transaction_foreign_amount', settingsKey: 'transaction_foreign_amount' },
        { field: 'note', selector: '#transaction_note' },
        { field: 'memo', selector: '#transaction_memo' },
        { field: 'supply_amount', selector: '#transaction_supply_amount', labelSelector: '.transaction-total-label', settingsKey: 'transaction_supply_amount' },
        { field: 'settlement_amount', selector: '#transaction_settlement_amount', labelSelector: '.transaction-total-label', settingsKey: 'transaction_settlement_amount' },
        { field: 'final_amount', selector: '#transaction_final_amount', labelSelector: '.transaction-total-label', settingsKey: 'transaction_final_amount' },
    ]);

    function readTransactionTableSettingsState() {
        return readDataTableSettingsState(TRANSACTION_TABLE_SETTINGS_KEY, {
            userSettingPageKey: TRANSACTION_TABLE_PAGE_KEY,
            metaDomain: TRANSACTION_META_DOMAIN,
        });
    }

    function normalizeTransactionMetaField(meta = {}) {
        return String(meta?.column || meta?.key || '').trim();
    }

    function normalizeTransactionMetaColumn(meta = {}, fieldConfig = {}) {
        const field = String(fieldConfig.field || normalizeTransactionMetaField(meta)).trim();
        const settingsKey = String(fieldConfig.settingsKey || field).trim() || field;
        const sourceField = String(fieldConfig.sourceField || meta?.source_title || field).trim() || field;
        const defaultLabel = String(meta?.label || fieldConfig.fallback || field).trim() || field;

        return {
            ...meta,
            key: field,
            column: field,
            settingsKey,
            __dtSettingsKey: settingsKey,
            sourceField,
            system_field_name: sourceField,
            original_column_key: sourceField,
            data: String(fieldConfig.dataKey || settingsKey).trim() || settingsKey,
            __dtDefaultDisplayName: defaultLabel,
        };
    }

    function transactionFieldLabel(meta = {}, state = null, fieldConfig = {}) {
        const column = normalizeTransactionMetaColumn(meta, fieldConfig);
        return resolveDataTableColumnDisplayName(
            column,
            state,
            column.__dtDefaultDisplayName || column.key
        );
    }

    function transactionFieldRequirementPolicy(meta = {}, state = null, fieldConfig = {}) {
        const column = normalizeTransactionMetaColumn(meta, fieldConfig);
        const policy = resolveDataTableColumnRequirementPolicy(column, state);
        if (policy === 'required' || policy === 'optional') {
            return policy;
        }
        return meta?.required === true ? 'required' : 'none';
    }

    function transactionFieldLabelHtml(label = '', policy = 'none') {
        const text = escapeHtml(label || '');
        if (policy === 'required') {
            return `${text}<span class="column-policy-star is-required" aria-hidden="true">*</span>`;
        }
        if (policy === 'optional') {
            return `${text}<span class="column-policy-star is-optional" aria-hidden="true">*</span>`;
        }
        return text;
    }

    function ensureTransactionLabelFallback(targetEl) {
        if (!targetEl) {
            return '';
        }

        const cached = String(targetEl.dataset.transactionLabelBase || '').trim();
        if (cached !== '') {
            return cached;
        }

        const fallback = String(targetEl.textContent || '').replace(/\*/g, '').trim();
        targetEl.dataset.transactionLabelBase = fallback;
        return fallback;
    }

    function applyTransactionHeaderLabels(metaRows = []) {
        const state = readTransactionTableSettingsState();
        const metaMap = new Map(
            (Array.isArray(metaRows) ? metaRows : [])
                .map((meta) => [normalizeTransactionMetaField(meta), meta])
                .filter(([field]) => field !== '')
        );

        TRANSACTION_HEADER_LABEL_FIELDS.forEach((fieldConfig) => {
            const inputEl = form?.querySelector(fieldConfig.selector) || document.querySelector(fieldConfig.selector);
            if (!inputEl) {
                return;
            }

            const fieldWrap = inputEl.closest('.transaction-field, .transaction-total-card');
            if (!fieldWrap) {
                return;
            }

            const labelEl = fieldWrap.querySelector(fieldConfig.labelSelector || '.transaction-field-label');
            if (!labelEl) {
                return;
            }

            const textEl = fieldConfig.textSelector
                ? labelEl.querySelector(fieldConfig.textSelector)
                : null;
            const targetEl = textEl || labelEl;
            const fallback = ensureTransactionLabelFallback(targetEl);
            const meta = metaMap.get(fieldConfig.field) || {
                column: fieldConfig.field,
                key: fieldConfig.field,
                label: fallback || fieldConfig.field,
                source_title: fieldConfig.field,
            };

            targetEl.innerHTML = transactionFieldLabelHtml(
                transactionFieldLabel(meta, state, { ...fieldConfig, fallback }),
                transactionFieldRequirementPolicy(meta, state, fieldConfig)
            );
        });
    }

    function bindTransactionHeaderMetaSync() {
        if (ctx.transactionHeaderMetaSyncBound) {
            return;
        }

        ctx.transactionHeaderMetaSyncBound = true;
        document.addEventListener('datatable-settings:updated', async (event) => {
            const storageKey = String(event?.detail?.storageKey || '').trim();
            if (storageKey && storageKey !== TRANSACTION_TABLE_SETTINGS_KEY) {
                return;
            }

            const metaRows = await fetchTransactionHeaderMeta();
            applyTransactionHeaderLabels(metaRows);
            if (ctx.systemInfoFieldsEl) {
                void setSystemInfoFields(ctx.transactionDetailMetaData || {});
            }
        });
    }

    const DEFAULT_SETTLEMENT_TYPE_OPTIONS = [
        { code: 'VAT', label: 'VAT' },
        { code: 'WITHHOLDING_INCOME', label: 'WITHHOLDING_INCOME' },
        { code: 'WITHHOLDING_BUSINESS', label: 'WITHHOLDING_BUSINESS' },
        { code: 'LOCAL_INCOME_TAX', label: 'LOCAL_INCOME_TAX' },
        { code: 'NATIONAL_PENSION', label: 'NATIONAL_PENSION' },
        { code: 'HEALTH_INSURANCE', label: 'HEALTH_INSURANCE' },
        { code: 'EMPLOYMENT_INSURANCE', label: 'EMPLOYMENT_INSURANCE' },
    ];
    const DEFAULT_AMOUNT_SIGN_OPTIONS = [
        { code: 'PLUS', label: 'PLUS' },
        { code: 'MINUS', label: 'MINUS' },
    ];
    const SYSTEM_INFO_EXCLUDED_FIELDS = new Set([
        'business_unit',
        'transaction_direction',
        'operation_type',
        'currency',
        'client_id',
        'project_id',
        'bank_account_id',
        'card_id',
        'team_id',
        'employee_id',
        'transaction_date',
        'transaction_description',
        'description',
        'transaction_exchange_rate',
        'exchange_rate',
        'transaction_foreign_amount',
        'foreign_amount',
        'transaction_supply_amount',
        'supply_amount',
        'base_amount',
        'transaction_settlement_amount',
        'settlement_amount',
        'adjustment_amount',
        'transaction_final_amount',
        'final_amount',
        'total_amount',
        'transaction_note',
        'note',
        'transaction_memo',
        'memo',
    ]);
    const SYSTEM_INFO_LABEL_OVERRIDES = {
        sort_no: '순번',
        status: '상태',
        transaction_no: '거래번호',
        created_at: '생성일시',
        created_by: '생성자',
        updated_at: '수정일시',
        updated_by: '수정자',
        deleted_at: '삭제일시',
        deleted_by: '삭제자',
    };
    const SYSTEM_INFO_FALLBACK_ORDER = [
        'sort_no',
        'status',
        'transaction_no',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
    ];
    const LINE_COLUMNS = [
        {
            field: '__move',
            headerName: '',
            headerComponent: dragHandleHeaderComponent(),
            editable: false,
            width: 34,
            resizable: true,
            rowDrag: false,
            rowDragText: '',
            suppressSizeToFit: false,
            headerClass: 'text-center transaction-line-drag-head',
            cellClass: 'text-center transaction-line-drag-cell',
            cellRenderer: dragHandleCellRenderer,
        },
        {
            field: '__row_no',
            headerName: '순번',
            editable: false,
            width: 58,
            resizable: true,
            valueGetter: (params) => `${Number(params.node?.rowIndex || 0) + 1}`,
            cellClass: 'transaction-line-row-no-cell',
        },
        {
            field: 'item_date',
            headerName: '거래일',
            width: 100,
            resizable: true,
            ...dateStringEditor(),
        },
        {
            field: 'item_name',
            headerName: '품명',
            width: 160,
            resizable: true,
        },
        {
            field: 'specification',
            headerName: '규격',
            width: 120,
            resizable: true,
        },
        {
            field: 'unit_name',
            headerName: '단위',
            ...selectEditor([UNIT_EMPTY_LABEL]),
            valueFormatter: ({ value }) => String(value ?? ''),
            width: 70,
            resizable: true,
            cellClass: 'transaction-line-unit-cell text-center',
        },
        {
            field: 'quantity',
            headerName: '수량',
            width: 80,
            resizable: true,
            type: 'numericColumn',
            valueFormatter: gridNumberFormatter,
            valueParser: gridNumberParser,
            cellClass: 'text-end',
        },
        {
            field: 'unit_price',
            headerName: '단가',
            width: 100,
            resizable: true,
            type: 'numericColumn',
            valueFormatter: gridNumberFormatter,
            valueParser: gridNumberParser,
            cellClass: 'text-end',
        },
        {
            field: 'amount',
            headerName: '공급가액',
            width: 110,
            resizable: true,
            type: 'numericColumn',
            valueFormatter: gridNumberFormatter,
            valueParser: gridNumberParser,
            cellClass: 'text-end',
        },
        {
            field: 'description',
            headerName: '적요',
            width: 160,
            resizable: true,
        },
        {
            field: '__actions',
            headerName: '+추가',
            editable: false,
            width: 70,
            resizable: true,
            headerClass: 'transaction-line-add-head text-center',
            cellClass: 'transaction-line-action-cell text-center',
            cellRenderer: lineActionRenderer,
            suppressKeyboardEvent: () => true,
        },
    ];

    const SETTLEMENT_COLUMNS = [
        {
            field: '__move',
            headerName: '',
            headerComponent: dragHandleHeaderComponent(),
            editable: false,
            width: 34,
            resizable: true,
            rowDrag: false,
            rowDragText: '',
            suppressSizeToFit: false,
            headerClass: 'text-center transaction-line-drag-head',
            cellClass: 'text-center transaction-line-drag-cell',
            cellRenderer: dragHandleCellRenderer,
        },
        {
            field: '__row_no',
            headerName: '순번',
            editable: false,
            width: 58,
            resizable: true,
            valueGetter: (params) => `${Number(params.node?.rowIndex || 0) + 1}`,
            cellClass: 'transaction-line-row-no-cell',
        },
        {
            field: 'settlement_type',
            headerName: '정산유형',
            ...selectEditor(DEFAULT_SETTLEMENT_TYPE_OPTIONS.map((option) => option.label)),
            width: 140,
            resizable: true,
        },
        {
            field: 'amount_sign',
            headerName: '가감유형',
            ...selectEditor(DEFAULT_AMOUNT_SIGN_OPTIONS.map((option) => option.label)),
            width: 102,
            resizable: true,
            cellClass: 'text-center',
        },
        {
            field: 'amount',
            headerName: '정산금액',
            width: 118,
            resizable: true,
            type: 'numericColumn',
            valueFormatter: gridNumberFormatter,
            valueParser: gridNumberParser,
            cellClass: 'text-end',
        },
        {
            field: 'description',
            headerName: '적요',
            width: 210,
            resizable: true,
        },
        {
            field: '__actions',
            headerName: '+추가',
            editable: false,
            width: 70,
            resizable: true,
            headerClass: 'transaction-line-add-head text-center',
            cellClass: 'transaction-line-action-cell text-center',
            cellRenderer: lineActionRenderer,
            suppressKeyboardEvent: () => true,
        },
    ];

    function getLineColumns() {
        const foreignMode = ctx.usesForeignCurrency();
        const baseColumns = LINE_COLUMNS.map((column) => {
            if (column.field === 'unit_name') {
                return {
                    ...column,
                    ...selectEditor(() => [UNIT_EMPTY_LABEL, ...unitOptions.map((option) => option.label), UNIT_QUICK_ADD_LABEL]),
                };
            }
            if (column.field === 'item_date') {
                return { ...column, ...dateStringEditor() };
            }
            if (column.field === 'quantity' || column.field === 'unit_price' || column.field === 'amount') {
                return { ...column };
            }
            return { ...column };
        });

        const columns = [];
        baseColumns.forEach((column) => {
            columns.push(column);
            if (column.field === 'quantity') {
                columns.push(
                    {
                        field: 'foreign_unit_price',
                        headerName: '외화단가',
                        hide: !foreignMode,
                        headerClass: 'transaction-line-foreign-head',
                        type: 'numericColumn',
                        valueFormatter: gridNumberFormatter,
                        valueParser: gridNumberParser,
                        width: 110,
                        resizable: true,
                        cellClass: 'transaction-line-foreign-cell text-end',
                    },
                    {
                        field: 'foreign_amount',
                        headerName: '외화금액',
                        hide: !foreignMode,
                        headerClass: 'transaction-line-foreign-head',
                        type: 'numericColumn',
                        valueFormatter: gridNumberFormatter,
                        valueParser: gridNumberParser,
                        width: 120,
                        resizable: true,
                        cellClass: 'transaction-line-foreign-cell text-end',
                    },
                );
            }
        });

        return columns;
    }

    function getSettlementColumns() {
        return SETTLEMENT_COLUMNS.map((column) => {
            if (column.field === 'settlement_type') {
                return {
                    ...column,
                    ...selectEditor(() => (ctx.settlementTypeOptions.length > 0 ? settlementTypeOptions : DEFAULT_SETTLEMENT_TYPE_OPTIONS)
                        .map((option) => option.label)),
                };
            }
            if (column.field === 'amount_sign') {
                return {
                    ...column,
                    ...selectEditor(() => (ctx.amountSignOptions.length > 0 ? amountSignOptions : DEFAULT_AMOUNT_SIGN_OPTIONS)
                        .map((option) => option.label)),
                };
            }
            return { ...column };
        });
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

    function decorateGridDatePicker(pickerArg) {
        const picker = pickerArg?.el ? pickerArg : this;
        const root = picker?.el;
        const title = root?.querySelector?.('.pika-title');
        if (!picker || !root || !title || title.dataset.yearNavBound === 'true') return;

        title.dataset.yearNavBound = 'true';

        const prevYear = document.createElement('button');
        prevYear.type = 'button';
        prevYear.className = 'pika-year-prev';
        prevYear.textContent = '<<';
        prevYear.setAttribute('aria-label', '?댁쟾 ?곕룄');

        const nextYear = document.createElement('button');
        nextYear.type = 'button';
        nextYear.className = 'pika-year-next';
        nextYear.textContent = '>>';
        nextYear.setAttribute('aria-label', '?ㅼ쓬 ?곕룄');

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

    function getVisibleGridDatePicker() {
        return Array.from(document.querySelectorAll('.pika-single'))
            .find((picker) => !picker.classList.contains('is-hidden') && picker.offsetParent !== null);
    }

    function closeGridDatePicker() {
        const editor = ctx.lineGrid?.getActiveEditor?.();
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
        ctx.activeLineDateInput = activeLineDateInput;
        ctx.activeLineDateInputHandler = activeLineDateInputHandler;
    }

    function bindLineDateInputFormatter() {
        unbindLineDateInputFormatter();

        const editor = ctx.lineGrid?.getActiveEditor?.();
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
        ctx.activeLineDateInput = activeLineDateInput;
        ctx.activeLineDateInputHandler = activeLineDateInputHandler;
    }

    function unbindLineDateEscHandler() {
        if (!lineDateEscHandler) return;
        window.ESCStack?.remove?.(lineDateEscHandler);
        lineDateEscHandler = null;
        ctx.lineDateEscHandler = lineDateEscHandler;
    }

    function bindLineDateEscHandler() {
        if (lineDateEscHandler || !window.ESCStack) return;

        lineDateEscHandler = () => {
            if (!ctx.lineGridEditing) {
                unbindLineDateEscHandler();
                return false;
            }

            if (getVisibleGridDatePicker()) {
                closeGridDatePicker();
                return true;
            }

        const editor = ctx.lineGrid?.getActiveEditor?.();
            editor.finishEditing?.(true);
            ctx.lineGridEditing = false;
            unbindLineDateEscHandler();
            return true;
        };

        window.ESCStack.push(lineDateEscHandler);
        ctx.lineDateEscHandler = lineDateEscHandler;
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
        ctx.lastInvalidUnitNotice = lastInvalidUnitNotice;
        notify(
            'warning',
            `"${text}"은(는) 현재 단위 기준정보 목록에 없습니다. 목록에서 선택하거나 +기준추가로 등록해 주세요.`
        );
    }
    function updateSettlementTypeOptionsFromCodeState(options = {}) {
        const rows = Array.isArray(options.SETTLEMENT_TYPE) ? options.SETTLEMENT_TYPE : [];
        settlementTypeOptions = rows
            .map((row) => ({
                code: String(row.code ?? '').trim().toUpperCase(),
                label: String(row.code_name || row.code || '').trim(),
            }))
            .filter((row) => row.code && row.label);

        if (settlementTypeOptions.length === 0) {
            settlementTypeOptions = DEFAULT_SETTLEMENT_TYPE_OPTIONS.slice();
        }

        ctx.settlementTypeOptions = settlementTypeOptions;
        ctx.settlementGrid?.render();
    }

    function updateAmountSignOptionsFromCodeState(options = {}) {
        const rows = Array.isArray(options.AMOUNT_SIGN) ? options.AMOUNT_SIGN : [];
        amountSignOptions = rows
            .map((row) => ({
                code: String(row.code ?? '').trim(),
                label: String(row.code_name || row.code || '').trim(),
            }))
            .filter((row) => row.code && row.label);

        if (amountSignOptions.length === 0) {
            amountSignOptions = DEFAULT_AMOUNT_SIGN_OPTIONS.slice();
        }

        ctx.amountSignOptions = amountSignOptions;
        ctx.settlementGrid?.render();
    }

    function findSettlementTypeOption(value) {
        const text = String(value ?? '').trim();
        const upper = text.toUpperCase();
        const options = settlementTypeOptions.length > 0 ? settlementTypeOptions : DEFAULT_SETTLEMENT_TYPE_OPTIONS;
        return options.find((option) => option.code === upper || option.label === text);
    }

    function settlementTypeLabelFromCode(value) {
        const found = findSettlementTypeOption(value);
        return found?.label || String(value ?? '').trim() || 'VAT';
    }

    function settlementTypeCodeFromCell(value) {
        const found = findSettlementTypeOption(value);
        return found?.code || String(value ?? '').trim().toUpperCase();
    }

    function findAmountSignOption(value) {
        const text = String(value ?? '').trim();
        const options = amountSignOptions.length > 0 ? amountSignOptions : DEFAULT_AMOUNT_SIGN_OPTIONS;
        return options.find((option) => option.code === text || option.label === text);
    }

    function amountSignLabelFromCode(value) {
        const found = findAmountSignOption(value);
        return found?.label || (String(value ?? '').trim().toUpperCase() === 'MINUS' ? 'MINUS' : 'PLUS');
    }

    function amountSignCodeFromCell(value) {
        const found = findAmountSignOption(value);
        return found?.code || (String(value ?? '').trim().toUpperCase() === 'MINUS' ? 'MINUS' : 'PLUS');
    }

    function updateUnitOptionsFromCodeState(options = {}) {
        const rows = Array.isArray(options.UNIT) ? options.UNIT : [];
        unitOptions = rows
            .map((row) => ({
                code: String(row.code ?? '').trim(),
                label: String(row.code_name || row.code || '').trim(),
            }))
            .filter((row) => row.label);

        ctx.unitOptions = unitOptions;
        (ctx.lineGrid?.getSourceData() || []).forEach((row, index) => {
            const normalized = normalizeUnitCellValue(row?.unit_name);
            if (row && row.unit_name !== normalized) {
                ctx.lineGrid.setSourceDataAtCell(index, 'unit_name', normalized);
            }
        });
        ctx.lineGrid?.render();
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
        ctx.unitCodeSelectEl = unitCodeSelectEl;

        settlementTypeCodeSelectEl = document.getElementById('transaction_settlement_type_code_select');
        if (!settlementTypeCodeSelectEl) {
            settlementTypeCodeSelectEl = document.createElement('select');
            settlementTypeCodeSelectEl.id = 'transaction_settlement_type_code_select';
            settlementTypeCodeSelectEl.dataset.codeGroup = 'SETTLEMENT_TYPE';
            settlementTypeCodeSelectEl.className = 'd-none';
            settlementTypeCodeSelectEl.tabIndex = -1;
            document.body.appendChild(settlementTypeCodeSelectEl);
        }
        ctx.settlementTypeCodeSelectEl = settlementTypeCodeSelectEl;

        amountSignCodeSelectEl = document.getElementById('transaction_amount_sign_code_select');
        if (!amountSignCodeSelectEl) {
            amountSignCodeSelectEl = document.createElement('select');
            amountSignCodeSelectEl.id = 'transaction_amount_sign_code_select';
            amountSignCodeSelectEl.dataset.codeGroup = 'AMOUNT_SIGN';
            amountSignCodeSelectEl.className = 'd-none';
            amountSignCodeSelectEl.tabIndex = -1;
            document.body.appendChild(amountSignCodeSelectEl);
        }
        ctx.amountSignCodeSelectEl = amountSignCodeSelectEl;
        onCodeOptionsLoaded(updateUnitOptionsFromCodeState);
        onCodeOptionsLoaded(updateSettlementTypeOptionsFromCodeState);
        onCodeOptionsLoaded(updateAmountSignOptionsFromCodeState);
        await createCodeSelect({
            selectId: unitCodeSelectEl.id,
            codeGroup: 'UNIT',
        });
        await createCodeSelect({
            selectId: settlementTypeCodeSelectEl.id,
            codeGroup: 'SETTLEMENT_TYPE',
        });
        await createCodeSelect({
            selectId: amountSignCodeSelectEl.id,
            codeGroup: 'AMOUNT_SIGN',
        });
    }

    function openUnitQuickAdd(row, col) {
        pendingUnitCell = { row, col };
        ctx.pendingUnitCell = pendingUnitCell;
        void openCodeQuickModal({
            codeGroup: 'UNIT',
            targetSelectId: unitCodeSelectEl?.id || '',
        });
    }

    function applyPendingUnitSelection() {
        if (!pendingUnitCell || !unitCodeSelectEl || !ctx.lineGrid) return;

        const selectedCode = unitCodeSelectEl.value || '';
        if (!selectedCode) return;

        const selected = unitOptions.find((option) => option.code === selectedCode);
        if (!selected) return;

        ctx.lineGrid.setDataAtCell(
            pendingUnitCell.row,
            pendingUnitCell.col,
            selected.label,
            'unit-quick-add'
        );
        pendingUnitCell = null;
        ctx.pendingUnitCell = pendingUnitCell;
    }

    function numberValue(value) {
        const parsed = Number(String(value ?? '').replace(/,/g, ''));
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function formatAmount(value) {
        return new Intl.NumberFormat('ko-KR').format(Math.round(numberValue(value)));
    }

    function setHeaderAmountValues(data = {}) {
        if (headerForeignAmountEl) {
            const foreignAmount = data.transaction_foreign_amount ?? data.foreign_amount;
            headerForeignAmountEl.value = foreignAmount === undefined || foreignAmount === null || numberValue(foreignAmount) === 0
                ? ''
                : formatNumber(foreignAmount);
        }
        if (headerSupplyAmountEl) {
            const baseAmount = data.base_amount ?? data.transaction_supply_amount ?? data.supply_amount;
            headerSupplyAmountEl.value = baseAmount === undefined || baseAmount === null ? '' : formatNumber(baseAmount);
        }
        if (headerSettlementAmountEl) {
            const adjustmentAmount = data.adjustment_amount
                ?? data.transaction_settlement_amount
                ?? data.settlement_amount
                ?? data.vat_amount;
            headerSettlementAmountEl.value = adjustmentAmount === undefined || adjustmentAmount === null ? '' : formatNumber(adjustmentAmount);
        }
        syncHeaderFinalAmount();
        if (headerFinalAmountEl) {
            const finalAmount = data.transaction_final_amount ?? data.final_amount ?? data.total_amount;
            if (finalAmount !== undefined && finalAmount !== null) {
                headerFinalAmountEl.value = formatNumber(finalAmount);
            }
        }
    }

    function syncHeaderFinalAmount() {
        if (!headerFinalAmountEl) return;
        const total = parseNumber(headerSupplyAmountEl?.value || '') + parseNumber(headerSettlementAmountEl?.value || '');
        headerFinalAmountEl.value = total !== 0 ? formatNumber(total) : '';
    }

    function normalizeHeaderAmountFormData(formData) {
        syncHeaderFinalAmount();
        ['foreign_amount', 'supply_amount', 'settlement_amount', 'final_amount'].forEach((name) => {
            const raw = String(formData.get(name) ?? '').trim();
            formData.set(name, raw === '' ? '' : String(parseNumber(raw)));
        });
        formData.set('transaction_foreign_amount', formData.get('foreign_amount') || '0');
        formData.set('base_amount', formData.get('supply_amount') || '0');
        formData.set('adjustment_amount', formData.get('settlement_amount') || '0');
        formData.set('transaction_supply_amount', formData.get('supply_amount') || '0');
        formData.set('transaction_settlement_amount', formData.get('settlement_amount') || '0');
        formData.set('transaction_final_amount', formData.get('final_amount') || '0');
        formData.set('transaction_description', String(formData.get('description') ?? ''));
        formData.set('transaction_note', String(formData.get('note') ?? ''));
        formData.set('transaction_memo', String(formData.get('memo') ?? ''));
        formData.set('transaction_exchange_rate', String(formData.get('exchange_rate') ?? ''));
    }

    function lineRowNoRenderer(params = {}) {
        return `${Number(params.node?.rowIndex || 0) + 1}`;
    }

    function dragHandleHeaderComponent() {
        function DragHandleHeader() {}

        DragHandleHeader.prototype.init = function init() {
            this.eGui = document.createElement('div');
            this.eGui.className = 'transaction-line-drag-head-inner';
            this.eGui.innerHTML = '<i class="bi bi-arrows-move" aria-hidden="true"></i>';
        };

        DragHandleHeader.prototype.getGui = function getGui() {
            return this.eGui;
        };

        return DragHandleHeader;
    }

    function dragHandleCellRenderer(params = {}) {
        const icon = document.createElement('span');
        icon.className = 'transaction-line-drag-cell-inner';
        icon.innerHTML = '<i class="bi bi-list" aria-hidden="true"></i>';

        if (typeof params.registerRowDragger === 'function') {
            params.registerRowDragger(icon, 4);
        }

        return icon;
    }

    function lineActionRenderer() {
        return '<span class="transaction-line-delete-text">-삭제</span>';
    }

    function formatBytes(value) {
        const size = Number(value || 0);
        if (!Number.isFinite(size) || size <= 0) return '-';
        if (size >= 1024 * 1024) return `${formatNumber((size / 1024 / 1024).toFixed(2))} MB`;
        if (size >= 1024) return `${formatNumber((size / 1024).toFixed(1))} KB`;
        return `${formatAmount(size)} bytes`;
    }

    function updateFileDropzone(rows = []) {
        if (!ctx.fileDropzoneEl) return;

        const count = rows.length;
        const totalSize = rows.reduce((sum, file) => sum + Number(file.file_size || file.size || 0), 0);
        const text = count > 0
            ? `파일 ${formatAmount(count)}개 (${formatBytes(totalSize)})`
            : ctx.fileDropzoneEmptyText;
        const textEl = ctx.fileDropzoneEl.querySelector('.transaction-file-dropzone-text');
        if (textEl) {
            textEl.textContent = text;
        } else {
            ctx.fileDropzoneEl.textContent = text;
        }
        ctx.fileDropzoneEl.classList.toggle('has-files', count > 0);
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

    function normalizeTransactionStatus(value) {
        const status = String(value || 'draft').toLowerCase();
        return ['draft', 'completed', 'closed', 'cancelled'].includes(status) ? status : 'draft';
    }

    function renderTransactionStatus(value) {
        const status = normalizeTransactionStatus(value);
        const labels = {
            draft: '임시저장',
            completed: '완료',
            closed: '마감',
            cancelled: '취소',
        };
        return `<span class="transaction-status transaction-status-${status}">${labels[status]}</span>`;
    }

    function renderLineStatus(_value, _type, row = {}) {
        const status = String(row.transaction_line_status || 'NONE').toUpperCase();
        const count = Number(row.transaction_line_count || 0);
        const incomplete = Number(row.transaction_line_incomplete_count || 0);

        if (status === 'COMPLETE') {
            return `<span class="badge text-bg-success">완성 ${count}</span>`;
        }

        if (status === 'INCOMPLETE') {
            return `<span class="badge text-bg-warning" title="보완 필요 ${incomplete}건">미완성 ${count}</span>`;
        }

        return '<span class="badge text-bg-secondary" title="거래 화면에서 거래내역을 추가해 주세요.">내역 없음</span>';
    }

    function updateTransactionStatusBadge(value) {
        if (!ctx.transactionStatusBadgeEl) return;

        const status = normalizeTransactionStatus(value);
        const labels = {
            draft: '임시저장',
            completed: '완료',
            closed: '마감',
            cancelled: '취소',
        };

        ctx.transactionStatusBadgeEl.className = `transaction-modal-state transaction-status-${status}`;
        const labelEl = ctx.transactionStatusBadgeEl.querySelector('span');
        if (labelEl) {
            labelEl.textContent = labels[status] || labels.draft;
        } else {
            ctx.transactionStatusBadgeEl.textContent = labels[status] || labels.draft;
        }
    }

    function statusDisplayLabel(value) {
        const labels = {
            draft: '임시저장',
            completed: '완료',
            closed: '마감',
            cancelled: '취소',
        };

        return labels[normalizeTransactionStatus(value)] || labels.draft;
    }

    async function fetchTransactionHeaderMeta() {
        if (Array.isArray(ctx.transactionHeaderMeta) && ctx.transactionHeaderMeta.length > 0) {
            return ctx.transactionHeaderMeta;
        }
        if (ctx.transactionHeaderMetaPromise) {
            return ctx.transactionHeaderMetaPromise;
        }

        ctx.transactionHeaderMetaPromise = ctx.fetchJson(`${ctx.API.systemTableColumns}?domain=transaction-header`)
            .then((json) => {
                ctx.transactionHeaderMeta = Array.isArray(json?.data) ? json.data : [];
                return ctx.transactionHeaderMeta;
            })
            .catch(() => {
                ctx.transactionHeaderMeta = [];
                return [];
            })
            .finally(() => {
                ctx.transactionHeaderMetaPromise = null;
            });

        return ctx.transactionHeaderMetaPromise;
    }

    function isBlankSystemValue(value) {
        const text = String(value ?? '').trim();
        return (
            text === ''
            || text === '0000-00-00'
            || text === '0000-00-00 00:00:00'
            || text === 'null'
            || text === 'undefined'
        );
    }

    function actorSystemDisplay(row, field) {
        const raw = String(row?.[field] ?? '').trim();
        const resolved = String(row?.[actorNameField(field)] ?? '').trim();
        if (raw === '') {
            return '-';
        }
        return resolved || '(알 수 없음)';
    }

    function formatSystemDateTime(value) {
        if (isBlankSystemValue(value)) {
            return '-';
        }

        const raw = String(value).trim();
        const date = formatDateOnlyValue(raw);
        const timeMatch = raw.match(/(\d{2}):(\d{2})(?::(\d{2}))?/);
        if (!date) {
            return raw;
        }
        if (!timeMatch) {
            return date;
        }

        const hour = timeMatch[1];
        const minute = timeMatch[2];
        const second = timeMatch[3] || '00';
        return `${date} ${hour}:${minute}:${second}`;
    }

    function formatSystemNumber(value) {
        if (isBlankSystemValue(value)) {
            return '-';
        }

        return formatNumber(value);
    }

    function systemInfoFieldLabel(field, meta = {}) {
        const state = readTransactionTableSettingsState();
        return SYSTEM_INFO_LABEL_OVERRIDES[field]
            || transactionFieldLabel(meta, state, { field })
            || String(meta.label || meta.source_title || field).trim()
            || field;
    }

    function systemInfoFieldRequirementPolicy(field, meta = {}) {
        const state = readTransactionTableSettingsState();
        return transactionFieldRequirementPolicy(meta, state, { field });
    }

    function systemInfoDisplayValue(field, data = {}) {
        switch (field) {
        case 'status':
            return statusDisplayLabel(data.status || 'draft');
        case 'created_by':
        case 'updated_by':
        case 'deleted_by':
            return actorSystemDisplay(data, field);
        default:
            break;
        }

        if (/_at$/.test(field) || /_date$/.test(field)) {
            return formatSystemDateTime(data[field]);
        }

        if (/(^sort_no$|amount$|_amount$|_price$|quantity$|_count$|_rate$)/.test(field)) {
            return formatSystemNumber(data[field]);
        }

        if (/_id$/.test(field)) {
            const nameField = field.replace(/_id$/, '_name');
            const display = String(data[nameField] || '').trim();
            if (display !== '') {
                return display;
            }
            return String(data[field] || '').trim() !== '' ? '(알 수 없음)' : '-';
        }

        return isBlankSystemValue(data[field]) ? '-' : String(data[field]).trim();
    }

    function shouldRenderSystemInfoField(field, data = {}, definedByMeta = false) {
        if (field === '' || SYSTEM_INFO_EXCLUDED_FIELDS.has(field)) {
            return false;
        }

        if (
            /(_name|_uuid|_code)$/.test(field)
            || field.includes('.')
            || ['items', 'settlements', 'files'].includes(field)
        ) {
            return false;
        }

        const value = data[field];
        const hasDisplaySource = definedByMeta
            || Object.prototype.hasOwnProperty.call(data, field)
            || Object.prototype.hasOwnProperty.call(data, actorNameField(field))
            || Object.prototype.hasOwnProperty.call(data, `${field}_name`);

        if (!hasDisplaySource) {
            return false;
        }

        if (Array.isArray(value) || (value && typeof value === 'object')) {
            return false;
        }

        return true;
    }

    function buildSystemInfoFields(metaRows = [], data = {}) {
        const rows = Array.isArray(metaRows) ? metaRows : [];
        const metaByField = new Map();
        const orderedFields = [];

        rows.forEach((meta) => {
            const field = String(meta?.column || meta?.key || '').trim();
            if (field === '' || metaByField.has(field)) {
                return;
            }
            metaByField.set(field, meta);
            orderedFields.push(field);
        });

        if (orderedFields.length === 0) {
            orderedFields.push(...Object.keys(data || {}));
        } else {
            SYSTEM_INFO_FALLBACK_ORDER.forEach((field) => {
                if (!orderedFields.includes(field) && Object.prototype.hasOwnProperty.call(data, field)) {
                    orderedFields.push(field);
                }
            });

            Object.keys(data || {}).forEach((field) => {
                if (!orderedFields.includes(field)) {
                    orderedFields.push(field);
                }
            });
        }

        const seen = new Set();
        return orderedFields
            .filter((field) => {
                const normalizedField = String(field || '').trim();
                if (seen.has(normalizedField)) {
                    return false;
                }
                seen.add(normalizedField);
                return shouldRenderSystemInfoField(normalizedField, data, metaByField.has(normalizedField));
            })
            .map((field) => ({
                ...(metaByField.get(field) || {}),
                column: field,
                key: field,
            }));
    }

    function renderSystemInfoField(field, label, value, policy = 'none') {
        return `
            <label class="transaction-field transaction-field-readonly">
                <span class="transaction-field-label">${transactionFieldLabelHtml(label, policy)}</span>
                <input type="text" class="form-control form-control-sm" value="${escapeHtml(value)}" readonly>
            </label>
        `;
    }

    async function setSystemInfoFields(data = {}) {
        const container = ctx.systemInfoFieldsEl || document.getElementById('transactionSystemInfoFields');
        if (!container) {
            return;
        }

        const metaRows = await fetchTransactionHeaderMeta();
        ctx.transactionDetailMetaData = data && typeof data === 'object' ? { ...data } : {};
        applyTransactionHeaderLabels(metaRows);
        const fields = buildSystemInfoFields(metaRows, data);

        container.innerHTML = fields
            .map((meta) => {
                const field = String(meta.column || '').trim();
                return renderSystemInfoField(
                    field,
                    systemInfoFieldLabel(field, meta),
                    systemInfoDisplayValue(field, data),
                    systemInfoFieldRequirementPolicy(field, meta)
                );
            })
            .join('');
    }

    function setTransactionModalEditable(editable) {
        form.querySelectorAll('input, select, textarea, button').forEach((control) => {
            if (control.matches('[data-bs-dismiss="ctx.modal"], .btn-close, .transaction-card-toggle')) return;

            control.disabled = !editable;
        });

        deleteBtn?.classList.toggle('d-none', !editable || !document.getElementById('transaction_id')?.value);
        ctx.lineGrid?.updateSettings({ readOnly: !editable });
        ctx.lineGrid?.render();
        ctx.settlementGrid?.updateSettings({ readOnly: !editable });
        ctx.settlementGrid?.render();
    }

    function renderCodeName(field, value) {
        return escapeHtml(getCodeName(field, value) || value || '');
    }

    function ensureAgGridLibrary() {
        if (window.agGrid?.createGrid) {
            return Promise.resolve();
        }
        if (ctx.agGridLoadPromise) {
            return ctx.agGridLoadPromise;
        }

        ctx.agGridLoadPromise = new Promise((resolve, reject) => {
            [AG_GRID_STYLE_URL, AG_GRID_THEME_URL].forEach((href) => {
                if (document.querySelector(`link[href="${href}"]`)) return;
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                document.head.appendChild(link);
            });

            const existing = document.querySelector(`script[src="${AG_GRID_SCRIPT_URL}"]`);
            if (existing) {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => reject(new Error('AG Grid library failed to load.')), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = AG_GRID_SCRIPT_URL;
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('AG Grid library failed to load.'));
            document.head.appendChild(script);
        });

        return ctx.agGridLoadPromise;
    }

    bindTransactionHeaderMetaSync();

    Object.assign(ctx, { getLineColumns, getSettlementColumns, escapeHtml, notify, today, formatDate, decorateGridDatePicker, isLineDateCell, isManualDateKey, getVisibleGridDatePicker, closeGridDatePicker, unbindLineDateInputFormatter, bindLineDateInputFormatter, unbindLineDateEscHandler, bindLineDateEscHandler, getUnitDropdownSource, unitDropdownSource, normalizeUnitCellValue, isAllowedUnitCellValue, notifyInvalidUnitValue, updateSettlementTypeOptionsFromCodeState, updateAmountSignOptionsFromCodeState, findSettlementTypeOption, settlementTypeLabelFromCode, settlementTypeCodeFromCell, findAmountSignOption, amountSignLabelFromCode, amountSignCodeFromCell, updateUnitOptionsFromCodeState, openUnitQuickAdd, applyPendingUnitSelection, numberValue, formatAmount, setHeaderAmountValues, syncHeaderFinalAmount, normalizeHeaderAmountFormData, lineRowNoRenderer, dragHandleHeaderComponent, dragHandleCellRenderer, lineActionRenderer, formatBytes, updateFileDropzone, normalizeTransactionStatus, renderTransactionStatus, renderLineStatus, updateTransactionStatusBadge, statusDisplayLabel, setSystemInfoFields, setTransactionModalEditable, renderCodeName, ensureAgGridLibrary, initUnitCodeOptions, fetchJson, HOT_DATE_PICKER_CONFIG, LINE_ITEM_DATE_COL, LINE_UNIT_COL, UNIT_EMPTY_LABEL, UNIT_QUICK_ADD_LABEL, DEFAULT_SETTLEMENT_TYPE_OPTIONS, DEFAULT_AMOUNT_SIGN_OPTIONS, LINE_COLUMNS, SETTLEMENT_COLUMNS });
    return ctx;
}
