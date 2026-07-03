export function registerModal(ctx) {
    const { AdminPicker } = ctx;

    function initTransactionDatePicker() {
        if (!ctx.pickerLayerEl || ctx.transactionDatePicker) return;

        ctx.transactionDatePicker = AdminPicker.create({
            type: 'today',
            container: ctx.pickerLayerEl,
        });

        ctx.transactionDatePicker.subscribe((state, finalDate) => {
            if (!(finalDate instanceof Date)) return;

            ctx.transactionDateEl.value = ctx.formatDate(finalDate);
            ctx.transactionDateEl.dispatchEvent(new Event('change', { bubbles: true }));
            ctx.transactionDatePicker.close?.();
        });
    }

    function openTransactionDatePicker() {
        if (!ctx.transactionDateEl || !ctx.pickerLayerEl) return;

        initTransactionDatePicker();
        ctx.transactionDatePicker?.open?.({ anchor: ctx.transactionDateEl });
    }

    function setLines(items = []) {
        ctx.initLineGrid();
        const rows = (Array.isArray(items) ? items : [])
            .map((item) => ctx.ensureLineRowIdentity(ctx.calculateLine(ctx.normalizeLine(item))));
        ctx.lineGrid?.loadData(rows);
        ctx.updateLineEmptyState();
    }

    function setSettlements(rows = []) {
        ctx.settlementRows = (Array.isArray(rows) ? rows : []).map((row) => ctx.normalizeSettlement(row));
        ctx.loadSettlementRows();
        ctx.calculateTotals();
    }

    function addLine() {
        ctx.initLineGrid();
        ctx.appendGridRow?.(ctx.GRID_KIND_LINE, {
            focusField: ctx.gridFocusField(ctx.GRID_KIND_LINE),
            startEditing: true,
        });
    }

    function removeLineAt(rowIndex) {
        if (!ctx.lineGrid) return;
        ctx.removeGridRow?.(ctx.GRID_KIND_LINE, rowIndex, { logicalDelete: true });
    }

    function collectLines() {
        ctx.initLineGrid();
        return (ctx.lineGrid?.getSourceData() || [])
            .map((row) => ctx.calculateLine(ctx.normalizeLine(row)))
            .filter((row) => String(row.item_name || row.description || '').trim() !== '' || ctx.numberValue(row.amount) !== 0)
            .map((row, index) => ({
                sort_no: index + 1,
                item_date: row.item_date || document.getElementById('transaction_date')?.value || ctx.today(),
                item_name: String(row.item_name || '').trim(),
                specification: String(row.specification || '').trim(),
                unit_name: String(row.unit_name || '').trim(),
                quantity: row.quantity,
                unit_price: row.unit_price,
                foreign_unit_price: usesForeignCurrency() ? row.foreign_unit_price : '',
                foreign_amount: usesForeignCurrency() ? row.foreign_amount : '',
                supply_amount: row.supply_amount,
                item_supply_amount: row.supply_amount,
                tax_type: ctx.normalizeTaxTypeCode(row.tax_type || ctx.defaultLineTaxTypeCode()) || ctx.defaultLineTaxTypeCode(),
                description: String(row.description || '').trim(),
            }));
    }

    function collectSettlements() {
        ctx.saveCurrentSettlementRows();
        return (ctx.settlementRows || [])
            .map((row) => ctx.calculateSettlementRow(row))
            .filter((row) => String(row.settlement_type || '').trim() !== '' && ctx.numberValue(row.amount) !== 0)
            .map((row, index) => ({
                sort_no: index + 1,
                transaction_item_id: null,
                settlement_type: ctx.settlementTypeCodeFromCell(row.settlement_type || 'VAT'),
                amount_sign: ctx.amountSignCodeFromCell(row.amount_sign || 'PLUS'),
                amount: ctx.numberValue(row.amount),
                settlement_description: String(row.description || '').trim(),
            }));
    }

    function usesForeignCurrency() {
        return Boolean(ctx.importToggle?.checked);
    }

    function syncConditionalPanels() {
        const fileEnabled = Boolean(ctx.fileToggle?.checked);
        ctx.filePanel?.classList.toggle('d-none', !fileEnabled);
        if (ctx.fileInput) {
            ctx.fileInput.disabled = !fileEnabled;
        }

        const importEnabled = usesForeignCurrency();
        ctx.modalEl.querySelectorAll('.transaction-exchange-field').forEach((field) => {
            field.classList.toggle('d-none', !importEnabled);
        });

        if (ctx.lineGrid) {
            ctx.syncLineForeignColumns();
            ctx.applyForeignTaxTypeToLines();
            ctx.calculateTotals();
            ctx.lineGrid.render();
        }
    }

    function setBusinessUnitValue(value) {
        ctx.setCodeSelectValue('business_unit', value || '');
    }

    function setTransactionDirectionValue(value) {
        ctx.setCodeSelectValue('transaction_direction', value || '');
    }

    function setOperationTypeValue(value) {
        ctx.setCodeSelectValue('operation_type', value || '');
    }

    function setCurrencyValue(value) {
        ctx.setCodeSelectValue('currency', value || '');
    }

    async function postTransactionAction(url, payload = {}) {
        const formData = new FormData();
        Object.entries(payload).forEach(([key, value]) => formData.set(key, value ?? ''));
        const json = await ctx.fetchJson(url, { method: 'POST', body: formData });

        if (json.data) {
            document.getElementById('transaction_match_status').value = json.data.match_status || 'matched';
            ctx.renderVoucherState(json.data);
        }

        ctx.reloadTable();
        return json;
    }

    async function createVoucherForCurrentTransaction() {
        const id = document.getElementById('transaction_id')?.value || '';
        if (!id) {
            ctx.notify('warning', '거래를 먼저 저장한 후 전표를 생성해 주세요.');
            return;
        }
        await ctx.openVoucherRecommendationModal({
            transactionId: id,
            onSaved(json) {
                if (json.data) {
                    document.getElementById('transaction_match_status').value = json.data.match_status || 'matched';
                    ctx.renderVoucherState(json.data);
                }
                ctx.reloadTable();
                ctx.notify('success', json.message || 'draft 전표가 생성되었습니다.');
            },
        });
    }

    async function linkVoucherToCurrentTransaction() {
        const transactionId = document.getElementById('transaction_id')?.value || '';
        const voucherId = ctx.selectedVoucherId;
        if (!transactionId || !voucherId) {
            ctx.notify('warning', '거래와 전표를 모두 선택한 후 연결해 주세요.');
            return;
        }

        const json = await postTransactionAction(ctx.API.linkVoucher, { transaction_id: transactionId, voucher_id: voucherId });
        ctx.clearVoucherSelection();
        ctx.notify('success', json.message || '전표가 거래에 연결되었습니다.');
    }

    async function unlinkVoucherFromCurrentTransaction() {
        const transactionId = document.getElementById('transaction_id')?.value || '';
        const voucherId = ctx.voucherSummaryEl?.querySelector('.linked-voucher-id')?.value || '';
        if (!transactionId) {
            ctx.notify('warning', '거래를 먼저 저장한 후 연결을 해제해 주세요.');
            return;
        }

        const json = await postTransactionAction(ctx.API.unlinkVoucher, { transaction_id: transactionId, voucher_id: voucherId });
        document.getElementById('transaction_match_status').value = 'none';
        ctx.notify('success', json.message || '전표 연결이 해제되었습니다.');
    }

    function resetModal() {
        ctx.form.reset();
        ctx.form.querySelectorAll('[data-generated-delete-file="true"]').forEach((input) => input.remove());
        document.getElementById('transaction_id').value = '';
        document.getElementById('transaction_date').value = ctx.today();
        setBusinessUnitValue('');
        setTransactionDirectionValue('');
        setOperationTypeValue('');
        setCurrencyValue('');
        ctx.setBankAccountValue('', '');
        ctx.setCardValue('', '');
        ctx.setTeamValue('', '');
        ctx.setEmployeeValue('', '');
        if (ctx.exchangeRateEl) ctx.exchangeRateEl.value = '';
        ctx.setHeaderAmountValues({});
        document.getElementById('transaction_status').value = 'draft';
        document.getElementById('transaction_match_status').value = 'none';
        ctx.updateTransactionStatusBadge('draft');
        ctx.setSystemInfoFields({});
        document.getElementById('transactionModalLabel').textContent = '거래 신규 등록';
        if (ctx.fileToggle) ctx.fileToggle.checked = false;
        if (ctx.importToggle) ctx.importToggle.checked = false;
        ctx.clearClientSelect();
        ctx.clearProjectSelect();
        ctx.deleteBtn?.classList.add('d-none');
        ctx.currentFiles = [];
        ctx.pendingFiles = [];
        ctx.fileRowOrder = [];
        ctx.settlementRows = [];
        ctx.settlementDeletedRows = [];
        if (ctx.fileInput) ctx.fileInput.value = '';
        setLines([]);
        setSettlements([]);
        ctx.renderFiles([]);
        ctx.renderVoucherState({});
        ctx.clearVoucherSelection();
        syncConditionalPanels();
        ctx.setTransactionModalEditable(true);
    }

    function showTransactionModalShell(title = '') {
        if (title) {
            document.getElementById('transactionModalLabel').textContent = title;
        }
        ctx.allowModalClose = true;
        ctx.modalBaselineSnapshot = '';
        ctx.pendingLineGridInitAfterShow = true;
        setTransactionModalLoading(true);
        ctx.setTransactionModalEditable(false);
        ctx.modal?.show();
        window.setTimeout(() => {
            ctx.allowModalClose = false;
        }, 0);
    }

    function waitForTransactionModalShown() {
        if (!ctx.modal) return Promise.resolve();
        if (ctx.modalEl.classList.contains('show')) return Promise.resolve();
        return new Promise((resolve) => {
            ctx.modalShownResolvers.push(resolve);
        });
    }

    function setTransactionModalLoading(isLoading = false) {
        const loading = Boolean(isLoading);
        ctx.modalEl?.classList.toggle('is-loading-detail', loading);
        ctx.modalEl?.setAttribute('aria-busy', loading ? 'true' : 'false');
    }

    async function openCreateModal() {
        showTransactionModalShell('거래 신규 등록');
        try {
            await ctx.initModalControls();
            await waitForTransactionModalShown();
            resetModal();
            ctx.markTransactionModalClean();
            setTransactionModalLoading(false);
            ctx.focusInitialLineGridCell();
        } catch (error) {
            ctx.notify('error', error.message || '거래 신규 등록 화면을 여는 중 오류가 발생했습니다.');
            setTransactionModalLoading(false);
            ctx.allowModalClose = true;
            ctx.modal?.hide();
        }
    }

    async function openDetail(id) {
        if (!id) return;

        showTransactionModalShell('거래 상세 수정');
        let json = null;
        try {
            [json] = await Promise.all([
                ctx.fetchJson(`${ctx.API.detail}?id=${encodeURIComponent(id)}`),
                ctx.initModalControls(),
            ]);
            await waitForTransactionModalShown();
        } catch (error) {
            ctx.notify('error', error.message || '거래 상세 정보를 불러오는 중 오류가 발생했습니다.');
            setTransactionModalLoading(false);
            ctx.allowModalClose = true;
            ctx.modal?.hide();
            return;
        }

        const data = json.data || {};
        resetModal();

        document.getElementById('transaction_id').value = data.id || '';
        document.getElementById('transaction_date').value = data.transaction_date || ctx.today();
        setBusinessUnitValue(data.business_unit || '');
        setTransactionDirectionValue(data.transaction_direction || '');
        setOperationTypeValue(data.operation_type || '');
        setCurrencyValue(data.currency || '');
        if (ctx.exchangeRateEl) ctx.exchangeRateEl.value = data.exchange_rate ? ctx.formatNumber(data.exchange_rate) : '';
        ctx.setHeaderAmountValues(data);
        ctx.setClientSelectValue(data.client_id || '', data.client_name || '');
        ctx.setProjectSelectValue(data.project_id || '', data.project_name || '');
        ctx.setBankAccountValue(data.bank_account_id || '', data.bank_account_name || data.account_name || '');
        ctx.setCardValue(data.card_id || '', data.card_name || '');
        ctx.setTeamValue(data.team_id || '', data.team_name || '');
        ctx.setEmployeeValue(data.employee_id || '', data.employee_name || data.user_name || '');
        document.getElementById('transaction_description').value = data.description || '';
        const transactionStatus = ctx.normalizeTransactionStatus(data.status);
        document.getElementById('transaction_status').value = transactionStatus;
        ctx.updateTransactionStatusBadge(transactionStatus);
        document.getElementById('transaction_match_status').value = data.match_status || 'none';
        ctx.setSystemInfoFields({
            ...data,
            status: transactionStatus,
        });
        document.getElementById('transaction_note').value = data.note || '';
        document.getElementById('transaction_memo').value = data.memo || '';
        document.getElementById('transactionModalLabel').textContent = '거래 상세 수정';
        if (ctx.importToggle) {
            const currency = String(data.currency || '').trim().toUpperCase();
            const hasForeignLines = (Array.isArray(data.items) ? data.items : [])
                .some((item) => ctx.numberValue(item.foreign_unit_price) || ctx.numberValue(item.foreign_amount));
            ctx.importToggle.checked = hasForeignLines || (currency !== '' && currency !== 'KRW') || Boolean(data.exchange_rate);
        }
        ctx.deleteBtn?.classList.remove('d-none');

        setLines(data.items || []);
        setSettlements(Array.isArray(data.settlements) ? data.settlements : []);
        ctx.currentFiles = Array.isArray(data.files) ? data.files : [];
        ctx.pendingFiles = [];
        ctx.fileRowOrder = [];
        if (ctx.fileInput) ctx.fileInput.value = '';
        if (ctx.fileToggle) ctx.fileToggle.checked = ctx.currentFiles.length > 0;
        ctx.renderFiles(ctx.currentFiles);
        ctx.renderVoucherState(data);
        syncConditionalPanels();
        ctx.setTransactionModalEditable(!['approved', 'deleted'].includes(transactionStatus));
        ctx.markTransactionModalClean();
        setTransactionModalLoading(false);
        ctx.focusInitialLineGridCell();
    }

    async function saveTransaction() {
        const lines = collectLines();
        const settlements = collectSettlements();
        const formData = new FormData(ctx.form);
        formData.set('items', JSON.stringify(lines));
        formData.set('settlements', JSON.stringify(settlements));
        formData.delete('tax_type');
        ctx.normalizeHeaderAmountFormData(formData);
        if (ctx.parseNumber(formData.get('final_amount') || '') <= 0) {
            ctx.notify('warning', '거래금액을 먼저 입력해 주세요.');
            return;
        }
        const rawExchangeRate = String(ctx.exchangeRateEl?.value || '').trim();
        if (usesForeignCurrency()) {
            if (ctx.parseNumber(rawExchangeRate) <= 0) {
                ctx.notify('warning', '외화 거래인 경우 환율을 입력해 주세요.');
                return;
            }
            formData.set('exchange_rate', rawExchangeRate === '' ? '' : String(ctx.parseNumber(rawExchangeRate)));
            formData.set('transaction_exchange_rate', formData.get('exchange_rate') || '');
        } else if (rawExchangeRate !== '') {
            formData.set('exchange_rate', String(ctx.parseNumber(rawExchangeRate)));
            formData.set('transaction_exchange_rate', formData.get('exchange_rate') || '');
        }

        await ctx.fetchJson(ctx.API.save, { method: 'POST', body: formData });

        ctx.notify('success', '거래가 저장되었습니다.');
        ctx.allowModalClose = true;
        ctx.markTransactionModalClean();
        ctx.modal?.hide();
        ctx.reloadTable();
    }

    async function deleteTransaction(id) {
        if (!id) return;

        const formData = new FormData();
        formData.set('transaction_id', id);
        await ctx.fetchJson(ctx.API.remove, { method: 'POST', body: formData });
        ctx.notify('success', '거래가 삭제되었습니다.');
        ctx.allowModalClose = true;
        ctx.markTransactionModalClean();
        ctx.modal?.hide();
        ctx.reloadTable();
    }

    Object.assign(ctx, {
        initTransactionDatePicker,
        openTransactionDatePicker,
        setLines,
        setSettlements,
        addLine,
        removeLineAt,
        collectLines,
        collectSettlements,
        usesForeignCurrency,
        syncConditionalPanels,
        setBusinessUnitValue,
        setTransactionDirectionValue,
        setCurrencyValue,
        resetModal,
        showTransactionModalShell,
        waitForTransactionModalShown,
        setTransactionModalLoading,
        postTransactionAction,
        createVoucherForCurrentTransaction,
        linkVoucherToCurrentTransaction,
        unlinkVoucherFromCurrentTransaction,
        openCreateModal,
        openDetail,
        saveTransaction,
        deleteTransaction,
    });
    return ctx;
}
