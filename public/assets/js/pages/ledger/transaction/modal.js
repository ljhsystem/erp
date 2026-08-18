import { createHtmlGrid } from '/public/assets/js/common/html-grid/index.js';
import { formatAmount } from '/public/assets/js/common/format.js';
import { runDeleteProgress } from '/public/assets/js/common/delete-progress.js';

export function registerModal(ctx) {
    const { AdminPicker } = ctx;
    const evidenceIdentity = (row = {}) => `${String(row.import_type || '').toUpperCase()}:${String(row.evidence_id || row.id || '')}`;
    const isEvidenceComplete = (row = {}) => ['COMPLETED', 'READY', 'VERIFY_ONLY']
        .includes(String(row.evidence_status || '').trim().toUpperCase());
    const evidencePolicyLabel = (value) => ({ DATA: '자료증빙', BOTH: '겸용' }[String(value || '').toUpperCase()] || '-');
    const formatEvidenceAmount = (value) => formatAmount(value);

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

    function initLinkedEvidenceGrid() {
        if (ctx.linkedEvidenceGrid || !ctx.linkedEvidencesGridEl) return;
        ctx.linkedEvidenceGrid = createHtmlGrid({
            host: ctx.linkedEvidencesGridEl,
            gridId: 'transaction-linked-evidences',
            columns: [
                { key: 'selection', label: '선택', type: 'selection', width: 56 },
                { key: 'link_state', label: '연결변경상태', formatter: 'text', width: 110 },
                { key: 'evidence_policy', label: '증빙구분', formatter: 'text', width: 96 },
                { key: 'evidence_date', label: '증빙일자', formatter: 'date', width: 112 },
                { key: 'display_type', label: '자료유형', formatter: 'text', width: 150 },
                { key: 'client_name', label: '거래처', formatter: 'text', width: 190 },
                { key: 'display_summary', label: '적요', formatter: 'text', minWidth: 220 },
                { key: 'display_amount', label: '금액', formatter: 'evidenceAmount', width: 140 },
            ],
            rows: [],
            formatters: { evidenceAmount: formatEvidenceAmount },
            capabilities: {
                addRow: false, deleteRow: false, insertRow: false, reorder: false,
                selection: true, multiSelection: true, keyboard: false, clipboard: false,
                footer: false, validation: false, columnHide: false, columnMove: false, columnResize: false,
            },
        });
        ctx.linkedEvidenceGrid.render({ noDataMessage: '조회된 데이터가 없습니다.' });
    }

    function setLinkedEvidences(rows = []) {
        const identities = new Set();
        ctx.linkedEvidences = (Array.isArray(rows) ? rows : []).filter((row) => {
            const key = evidenceIdentity(row);
            if (key === ':' || identities.has(key)) return false;
            identities.add(key);
            return true;
        });
        initLinkedEvidenceGrid();
        ctx.linkedEvidenceGrid?.setState({
            ...ctx.linkedEvidenceGrid.getState(),
            rows: ctx.linkedEvidences.map((row) => ({
                rowId: evidenceIdentity(row),
                rowState: 'readonly',
                values: {
                    selection: '',
                    link_state: row._link_state === 'PENDING' ? '추가 예정' : '연결됨',
                    evidence_policy: evidencePolicyLabel(row.evidence_type),
                    evidence_date: row.evidence_date || '-',
                    display_type: row.display_type || row.import_type || '-',
                    client_name: row.client_name || '-',
                    display_summary: row.display_summary || '-',
                    display_amount: Number(row.display_amount || 0),
                },
                meta: { evidence: row },
            })),
        });
        if (ctx.clearEvidenceBtn) ctx.clearEvidenceBtn.disabled = ctx.linkedEvidences.length === 0;
        ctx.refreshRecommendation?.();
    }

    function updateEvidenceSelectionCount() {
        if (ctx.evidenceSelectionCountEl) ctx.evidenceSelectionCountEl.textContent = `${ctx.pendingEvidenceKeys.size}개 선택`;
    }

    function resetModal() {
        ctx.form.reset();
        ctx.form.querySelectorAll('[data-generated-delete-file="true"]').forEach((input) => input.remove());
        document.getElementById('transaction_id').value = '';
        document.getElementById('transaction_loaded_updated_at').value = '';
        document.getElementById('transaction_date').value = ctx.today();
        setBusinessUnitValue('');
        setTransactionDirectionValue('');
        setOperationTypeValue('');
        setCurrencyValue('');
        ctx.setBankAccountValue('', '');
        ctx.setCardValue('', '');
        ctx.setTeamValue('', '');
        ctx.setEmployeeValue('', '');
        setLinkedEvidences([]);
        if (ctx.exchangeRateEl) ctx.exchangeRateEl.value = '';
        ctx.setHeaderAmountValues({});
        document.getElementById('transaction_status').value = 'draft';
        ctx.updateTransactionStatusBadge('draft');
        void ctx.setSystemInfoFields({});
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

    function bindDetailCardCollapses() {
        if (!ctx.modalEl || !window.bootstrap?.Collapse) {
            return;
        }

        ctx.modalEl.querySelectorAll('.transaction-card-toggle[data-bs-target]').forEach((button) => {
            if (button.dataset.collapseBound === '1') {
                return;
            }

            const targetSelector = String(button.getAttribute('data-bs-target') || '').trim();
            if (targetSelector === '' || !targetSelector.startsWith('#')) {
                return;
            }

            const targetEl = ctx.modalEl.querySelector(targetSelector);
            if (!targetEl) {
                return;
            }

            const collapse = bootstrap.Collapse.getOrCreateInstance(targetEl, { toggle: false });

            const syncState = () => {
                const expanded = targetEl.classList.contains('show');
                button.classList.toggle('collapsed', !expanded);
                button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                window.requestAnimationFrame(() => {
                    ctx.modal?.handleUpdate?.();
                });
            };

            button.removeAttribute('data-bs-toggle');

            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (targetEl.classList.contains('show')) {
                    collapse.hide();
                    return;
                }

                collapse.show();
            });

            targetEl.addEventListener('shown.bs.collapse', syncState);
            targetEl.addEventListener('hidden.bs.collapse', syncState);
            syncState();
            button.dataset.collapseBound = '1';
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
            bindDetailCardCollapses();
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
            bindDetailCardCollapses();
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
        document.getElementById('transaction_loaded_updated_at').value = data.updated_at || '';
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
        setLinkedEvidences(Array.isArray(data.linked_evidences) ? data.linked_evidences : []);
        document.getElementById('transaction_description').value = data.description || '';
        const transactionStatus = ctx.normalizeTransactionStatus(data.status);
        document.getElementById('transaction_status').value = transactionStatus;
        ctx.updateTransactionStatusBadge(transactionStatus);
        await ctx.setSystemInfoFields({
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
        syncConditionalPanels();
        ctx.setTransactionModalEditable(!data.deleted_at && !['closed', 'cancelled'].includes(transactionStatus));
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
        formData.set('linked_evidences', JSON.stringify(ctx.linkedEvidences.map((evidence) => ({
            import_type: evidence.import_type || evidence.source_type || '',
            evidence_id: evidence.evidence_id || evidence.id || '',
        }))));
        formData.delete('evidence_id');
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
        await runDeleteProgress({ total: 1, title: '소프트삭제 처리 중', step: '거래를 휴지통으로 이동 중' }, async () => {
            const formData = new FormData();
            formData.set('transaction_id', id);
            await ctx.fetchJson(ctx.API.remove, { method: 'POST', body: formData });
            ctx.notify('success', '거래가 삭제되었습니다.');
            ctx.allowModalClose = true;
            ctx.markTransactionModalClean();
            ctx.modal?.hide();
            await ctx.reloadTable();
        });
    }

    let restoreEvidenceSelectionAfterEditor = false;
    ctx.openEvidenceSourceEditor = (row = {}) => {
        const importType = String(row.import_type || '').trim().toUpperCase();
        const evidenceId = String(row.evidence_id || row.id || '').trim();
        if (!importType || !evidenceId || !ctx.evidenceEditor?.open) {
            ctx.notify('warning', '증빙원본 수정 화면을 열 수 없습니다.');
            return;
        }
        const openEditor = () => {
            restoreEvidenceSelectionAfterEditor = true;
            void ctx.evidenceEditor.open({ import_type: importType, evidence_id: evidenceId });
        };
        if (ctx.evidenceSearchModalEl?.classList.contains('show')) {
            ctx.evidenceSearchModalEl.addEventListener('hidden.bs.modal', openEditor, { once: true });
            bootstrap.Modal.getInstance(ctx.evidenceSearchModalEl)?.hide();
            return;
        }
        openEditor();
    };
    document.addEventListener('evidence:updated', () => ctx.reloadTransactionEvidenceSelectionTable?.());
    document.getElementById('evidenceSeedRowEditModal')?.addEventListener('hidden.bs.modal', () => {
        if (!restoreEvidenceSelectionAfterEditor || !ctx.evidenceSearchModalEl) return;
        restoreEvidenceSelectionAfterEditor = false;
        bootstrap.Modal.getOrCreateInstance(ctx.evidenceSearchModalEl).show();
        ctx.reloadTransactionEvidenceSelectionTable?.();
    });

    if (ctx.selectEvidenceBtn && !ctx.selectEvidenceBtn.dataset.evidenceBound) {
        ctx.selectEvidenceBtn.dataset.evidenceBound = '1';
        ctx.evidenceSearchModalEl?.addEventListener('shown.bs.modal', () => {
            const backdrops = Array.from(document.querySelectorAll('.modal-backdrop.show'));
            backdrops[backdrops.length - 1]?.classList.add('transaction-evidence-search-backdrop');
            window.requestAnimationFrame(() => ctx.evidenceSearchTable?.columns?.adjust?.());
        });
        ctx.evidenceSearchModalEl?.addEventListener('hidden.bs.modal', () => {
            document.querySelectorAll('.modal-backdrop.transaction-evidence-search-backdrop').forEach((backdrop) => {
                backdrop.classList.remove('transaction-evidence-search-backdrop');
            });
            if (ctx.modalEl?.classList.contains('show')) {
                document.body.classList.add('modal-open');
            }
        });
        ctx.selectEvidenceBtn.addEventListener('click', () => {
            if (!ctx.evidenceSearchModalEl || !window.bootstrap) return;
            ctx.pendingEvidenceKeys = new Set();
            ctx.pendingEvidenceRows = new Map(
                ctx.evidenceSearchRows.map((row) => [evidenceIdentity(row), row])
            );
            ctx.evidenceSearchTable?.clearSelectedIds?.();
            updateEvidenceSelectionCount();
            bootstrap.Modal.getOrCreateInstance(ctx.evidenceSearchModalEl).show();
            const table = ctx.ensureTransactionEvidenceSelectionTable?.();
            table?.ajax?.reload(() => table.columns?.adjust?.(), false);
        });
        ctx.clearEvidenceBtn?.addEventListener('click', () => {
            const selected = new Set(ctx.linkedEvidenceGrid?.getState()?.selection?.selectedRowIds || []);
            if (selected.size === 0) {
                ctx.notify('info', '해제할 증빙을 선택해 주세요.');
                return;
            }
            setLinkedEvidences(ctx.linkedEvidences.filter((row) => !selected.has(evidenceIdentity(row))));
        });
        ctx.applyEvidenceBtn?.addEventListener('click', () => {
            const selectedRows = Array.from(ctx.pendingEvidenceKeys)
                .map((key) => ctx.pendingEvidenceRows.get(key)
                    || ctx.evidenceSearchRows.find((row) => evidenceIdentity(row) === key))
                .filter(Boolean);
            if (selectedRows.some((row) => !isEvidenceComplete(row))) {
                ctx.notify('warning', '증빙상태가 완료된 증빙만 추가할 수 있습니다.');
                return;
            }
            const additions = selectedRows
                .map((row) => ({ ...row, _link_state: 'PENDING' }));
            if (additions.length === 0) {
                ctx.notify('info', '추가할 증빙을 선택해 주세요.');
                return;
            }
            setLinkedEvidences([...ctx.linkedEvidences, ...additions]);
            bootstrap.Modal.getInstance(ctx.evidenceSearchModalEl)?.hide();
            ctx.reloadTransactionEvidenceSelectionTable?.();
        });
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
        setOperationTypeValue,
        setCurrencyValue,
        resetModal,
        showTransactionModalShell,
        waitForTransactionModalShown,
        bindDetailCardCollapses,
        setTransactionModalLoading,
        openCreateModal,
        openDetail,
        saveTransaction,
        deleteTransaction,
        setLinkedEvidences,
        updateEvidenceSelectionCount,
    });
    window.LedgerTransactionModal = {
        async openTransaction(id, options = {}) {
            if (typeof options.onClosed === 'function') {
                ctx.modalEl.addEventListener('hidden.bs.modal', options.onClosed, { once: true });
            }
            await openDetail(id);
        },
        async openCreate(options = {}) {
            if (typeof options.onClosed === 'function') {
                ctx.modalEl.addEventListener('hidden.bs.modal', options.onClosed, { once: true });
            }
            await openCreateModal();
            if (options.evidence) {
                const evidence = { ...options.evidence, _link_state: 'PENDING' };
                setLinkedEvidences([evidence]);
            }
        },
    };
    return ctx;
}
