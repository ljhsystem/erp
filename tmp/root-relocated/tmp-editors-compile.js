(function(ctx){
with (ctx) {
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
        prevYear.setAttribute('aria-label', '이전 연도');

        const nextYear = document.createElement('button');
        nextYear.type = 'button';
        nextYear.className = 'pika-year-next';
        nextYear.textContent = '>>';
        nextYear.setAttribute('aria-label', '다음 연도');

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
        const editor = lineGrid?.getActiveEditor?.();
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

        const editor = lineGrid?.getActiveEditor?.();
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
            if (!lineGridEditing) {
                unbindLineDateEscHandler();
                return false;
            }

            if (getVisibleGridDatePicker()) {
                closeGridDatePicker();
                return true;
            }

            const editor = lineGrid?.getActiveEditor?.();
            editor.finishEditing?.(true);
            lineGridEditing = false;
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
        notify(
            'warning',
            `"${text}"은(는) 현재 단위 기준정보 목록에 없습니다. 목록에서 선택하거나 +기준추가로 등록해주세요.`
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

        settlementGrid?.render();
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

        settlementGrid?.render();
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

        (lineGrid?.getSourceData() || []).forEach((row, index) => {
            const normalized = normalizeUnitCellValue(row?.unit_name);
            if (row && row.unit_name !== normalized) {
                lineGrid.setSourceDataAtCell(index, 'unit_name', normalized);
            }
        });
        lineGrid?.render();
    }

    function updateTaxTypeOptionsFromCodeState(options = {}) {
        const rows = Array.isArray(options.TAX_TYPE) ? options.TAX_TYPE : [];
        taxTypeOptions = rows
            .map((row) => ({
                code: String(row.code ?? '').trim(),
                label: String(row.code_name || row.code || '').trim(),
            }))
            .filter((row) => row.code && row.label);

        (lineGrid?.getSourceData() || []).forEach((row, index) => {
            const normalized = normalizeTaxTypeCellValue(row?.tax_type);
            if (row && row.tax_type !== normalized) {
                lineGrid.setSourceDataAtCell(index, 'tax_type', normalized);
            }
        });
        lineGrid?.render();
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
        if (!text) return '품목';

        const found = findTaxTypeOption(text);
        return found?.label || text;
    }

    function taxTypeCodeFromCellValue(value) {
        const text = String(value ?? '').trim();
        if (!text) return '품목';

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
        if (!usesForeignCurrency() || !lineGrid) return;

        (lineGrid.getSourceData() || []).forEach((row, index) => {
            if (!row) return;
            if (!String(row.tax_type || '').trim()) {
                setLineCellValue(index, 'tax_type', taxTypeLabelFromCode('ZERO'), 'foreign-tax');
            }
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

        settlementTypeCodeSelectEl = document.getElementById('transaction_settlement_type_code_select');
        if (!settlementTypeCodeSelectEl) {
            settlementTypeCodeSelectEl = document.createElement('select');
            settlementTypeCodeSelectEl.id = 'transaction_settlement_type_code_select';
            settlementTypeCodeSelectEl.dataset.codeGroup = 'SETTLEMENT_TYPE';
            settlementTypeCodeSelectEl.className = 'd-none';
            settlementTypeCodeSelectEl.tabIndex = -1;
            document.body.appendChild(settlementTypeCodeSelectEl);
        }

        amountSignCodeSelectEl = document.getElementById('transaction_amount_sign_code_select');
        if (!amountSignCodeSelectEl) {
            amountSignCodeSelectEl = document.createElement('select');
            amountSignCodeSelectEl.id = 'transaction_amount_sign_code_select';
            amountSignCodeSelectEl.dataset.codeGroup = 'AMOUNT_SIGN';
            amountSignCodeSelectEl.className = 'd-none';
            amountSignCodeSelectEl.tabIndex = -1;
            document.body.appendChild(amountSignCodeSelectEl);
        }
        onCodeOptionsLoaded(updateUnitOptionsFromCodeState);
        onCodeOptionsLoaded(updateTaxTypeOptionsFromCodeState);
        onCodeOptionsLoaded(updateSettlementTypeOptionsFromCodeState);
        onCodeOptionsLoaded(updateAmountSignOptionsFromCodeState);
        await createCodeSelect({
            selectId: unitCodeSelectEl.id,
            codeGroup: 'UNIT',
        });
        await createCodeSelect({
            selectId: taxTypeCodeSelectEl.id,
            codeGroup: 'TAX_TYPE',
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
        void openCodeQuickModal({
            codeGroup: 'UNIT',
            targetSelectId: unitCodeSelectEl?.id || '',
        });
    }

    function applyPendingUnitSelection() {
        if (!pendingUnitCell || !unitCodeSelectEl || !lineGrid) return;

        const selectedCode = unitCodeSelectEl.value || '';
        if (!selectedCode) return;

        const selected = unitOptions.find((option) => option.code === selectedCode);
        if (!selected) return;

        lineGrid.setDataAtCell(
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
        return ['draft', 'approved', 'rejected', 'deleted'].includes(status) ? status : 'draft';
    }

    function renderTransactionStatus(value) {
        const status = normalizeTransactionStatus(value);
        const labels = {
            draft: '입력',
            approved: '승인완료',
            rejected: '반려',
            deleted: '삭제',
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
        if (!transactionStatusBadgeEl) return;

        const status = normalizeTransactionStatus(value);
        const labels = {
            draft: '입력',
            approved: '승인',
            rejected: '반려',
            deleted: '삭제',
        };

        transactionStatusBadgeEl.className = `transaction-modal-state transaction-status-${status}`;
        const labelEl = transactionStatusBadgeEl.querySelector('span');
        if (labelEl) {
            labelEl.textContent = labels[status] || labels.draft;
        } else {
            transactionStatusBadgeEl.textContent = labels[status] || labels.draft;
        }
    }

    function statusDisplayLabel(value) {
        const labels = {
            draft: '입력',
            approved: '승인',
            rejected: '반려',
            deleted: '삭제',
        };

        return labels[normalizeTransactionStatus(value)] || labels.draft;
    }

    function matchStatusDisplayLabel(value) {
        const labels = {
            none: '미연결',
            matched: '연결완료',
            partial: '부분연결',
            pending: '대기',
        };

        return labels[String(value || 'none').trim().toLowerCase()] || '미연결';
    }

    function setSystemInfoFields(data = {}) {
        const createdAtEl = document.getElementById('transaction_created_at_display');
        const createdByEl = document.getElementById('transaction_created_by_display');
        const updatedAtEl = document.getElementById('transaction_updated_at_display');
        const updatedByEl = document.getElementById('transaction_updated_by_display');
        const deletedAtEl = document.getElementById('transaction_deleted_at_display');
        const deletedByEl = document.getElementById('transaction_deleted_by_display');
        const statusEl = document.getElementById('transaction_status_display');
        const matchStatusEl = document.getElementById('transaction_match_status_display');

        if (createdAtEl) createdAtEl.value = data.created_at || '';
        if (createdByEl) createdByEl.value = data.created_by_name || data.created_by || '';
        if (updatedAtEl) updatedAtEl.value = data.updated_at || '';
        if (updatedByEl) updatedByEl.value = data.updated_by_name || data.updated_by || '';
        if (deletedAtEl) deletedAtEl.value = data.deleted_at || '';
        if (deletedByEl) deletedByEl.value = data.deleted_by_name || data.deleted_by || '';
        if (statusEl) statusEl.value = statusDisplayLabel(data.status || 'draft');
        if (matchStatusEl) matchStatusEl.value = matchStatusDisplayLabel(data.match_status || 'none');
    }

    function setTransactionModalEditable(editable) {
        form.querySelectorAll('input, select, textarea, button').forEach((control) => {
            if (control.matches('[data-bs-dismiss="modal"], .btn-close, .transaction-card-toggle')) return;

            control.disabled = !editable;
        });

        deleteBtn?.classList.toggle('d-none', !editable || !document.getElementById('transaction_id')?.value);
        lineGrid?.updateSettings({ readOnly: !editable });
        lineGrid?.render();
        settlementGrid?.updateSettings({ readOnly: !editable });
        settlementGrid?.render();
    }

    function renderCodeName(field, value) {
        return escapeHtml(getCodeName(field, value) || value || '');
    }

    function ensureAgGridLibrary() {
        if (window.agGrid?.createGrid) {
            return Promise.resolve();
        }
        if (agGridLoadPromise) {
            return agGridLoadPromise;
        }

        agGridLoadPromise = new Promise((resolve, reject) => {
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

        return agGridLoadPromise;
    }

return {};
}
})