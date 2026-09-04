import { actorDisplay } from '/public/assets/js/common/actor.js';

export function registerEvents(ctx) {
    const { formatDateInputValue } = ctx;

    function releaseModalFocus() {
        const $ = window.jQuery || window.$;
        if ($?.fn?.select2) {
            ctx.modalEl.querySelectorAll('select.select2-hidden-accessible').forEach((select) => {
                try {
                    $(select).select2('close');
                } catch (error) {
                }

                select.blur?.();
            });
        }

        ctx.modalEl.querySelectorAll('.select2-selection, .select2-search__field').forEach((element) => {
            element.blur?.();
        });

        const active = document.activeElement;
        if (!active || !ctx.modalEl.contains(active)) {
            return;
        }

        active.blur?.();

        const body = document.body;
        if (!body) {
            return;
        }

        const previousTabIndex = body.getAttribute('tabindex');
        if (!body.hasAttribute('tabindex')) {
            body.setAttribute('tabindex', '-1');
        }

        try {
            body.focus({ preventScroll: true });
        } catch (error) {
            body.focus?.();
        }

        window.setTimeout(() => {
            if (previousTabIndex === null) {
                body.removeAttribute('tabindex');
            } else {
                body.setAttribute('tabindex', previousTabIndex);
            }
        }, 0);
    }

    function stopGridEditingForModalClose() {
        const grids = [
            { kind: ctx.GRID_KIND_LINE, grid: ctx.lineGrid },
            { kind: ctx.GRID_KIND_SETTLEMENT, grid: ctx.settlementGrid },
        ];
        let editingStopped = false;

        grids.forEach(({ kind, grid }) => {
            const editingCells = grid?.api?.getEditingCells?.() || [];
            if (editingCells.length < 1) {
                return;
            }

            grid.api?.stopEditing?.(true);
            editingStopped = true;
            const activeCell = editingCells[0];
            const rowIndex = Number(activeCell?.rowIndex);
            const colId = String(activeCell?.column?.getColId?.() || '').trim();
            if (rowIndex >= 0 && colId !== '') {
                window.requestAnimationFrame(() => {
                    ctx.focusGridCell?.(kind, rowIndex, colId);
                });
            }
        });

        return editingStopped;
    }

    function bindEvents() {
        ctx.importToggle?.addEventListener('change', ctx.syncConditionalPanels);
        ctx.fileToggle?.addEventListener('change', ctx.syncConditionalPanels);
        ctx.exchangeRateEl?.addEventListener('change', ctx.calculateTotals);
        ctx.exchangeRateEl?.addEventListener('input', ctx.calculateTotals);
        ctx.headerSupplyAmountEl?.addEventListener('input', ctx.syncHeaderFinalAmount);
        ctx.headerSupplyAmountEl?.addEventListener('change', ctx.syncHeaderFinalAmount);
        ctx.headerSettlementAmountEl?.addEventListener('input', ctx.syncHeaderFinalAmount);
        ctx.headerSettlementAmountEl?.addEventListener('change', ctx.syncHeaderFinalAmount);
        ctx.fileInput?.addEventListener('change', () => ctx.assignPendingFiles(ctx.fileInput.files));

        ctx.fileDropzoneEl?.addEventListener('click', () => {
            ctx.fileInput?.click();
        });

        ctx.fileDropzoneEl?.addEventListener('dragover', (event) => {
            event.preventDefault();
            ctx.fileDropzoneEl.classList.add('is-drag-over');
        });

        ctx.fileDropzoneEl?.addEventListener('dragleave', (event) => {
            if (!ctx.fileDropzoneEl.contains(event.relatedTarget)) {
                ctx.fileDropzoneEl.classList.remove('is-drag-over');
            }
        });

        ctx.fileDropzoneEl?.addEventListener('drop', (event) => {
            event.preventDefault();
            ctx.fileDropzoneEl.classList.remove('is-drag-over');
            ctx.appendPendingFiles(event.dataTransfer?.files || []);
        });

        ctx.transactionDateEl?.addEventListener('input', () => {
            ctx.transactionDateEl.value = formatDateInputValue(ctx.transactionDateEl.value);
        });

        ctx.transactionDateEl?.addEventListener('change', () => {
            ctx.transactionDateEl.value = formatDateInputValue(ctx.transactionDateEl.value);
            const date = ctx.transactionDateEl.value || ctx.today();
            (ctx.lineGrid?.getSourceData() || []).forEach((row, index) => {
                if (!row.item_date) ctx.setLineCellValue(index, 'item_date', date, 'date-sync');
            });
        });

        ctx.modalEl.addEventListener('click', (event) => {
            if (!event.target.closest('.date-icon')) return;
            event.preventDefault();
            ctx.openTransactionDatePicker();
        });

        ctx.modalEl.addEventListener('hide.bs.modal', (event) => {
            if (stopGridEditingForModalClose()) {
                event.preventDefault();
                return;
            }

            if (ctx.allowModalClose || !ctx.hasTransactionModalChanges()) {
                releaseModalFocus();
                return;
            }

            const ok = window.confirm('입력 중인 거래 내용이 있습니다. 저장하지 않고 닫으시겠습니까?');
            if (!ok) {
                event.preventDefault();
                return;
            }

            ctx.allowModalClose = true;
            releaseModalFocus();
        });

        ctx.modalEl.addEventListener('shown.bs.modal', () => {
            if (ctx.pendingLineGridInitAfterShow) {
                ctx.initLineGrid();
                ctx.pendingLineGridInitAfterShow = false;
            }
            const resolvers = ctx.modalShownResolvers;
            ctx.modalShownResolvers = [];
            resolvers.forEach((resolve) => resolve());
        });

        ctx.modalEl.addEventListener('esc:modal-before-close', (event) => {
            if (stopGridEditingForModalClose()) {
                event.preventDefault();
                return;
            }

            if (event.detail?.modal !== ctx.modalEl || ctx.allowModalClose || !ctx.hasTransactionModalChanges()) {
                return;
            }

            const ok = window.confirm('입력 중인 거래 내용이 있습니다. 저장하지 않고 닫으시겠습니까?');
            if (!ok) {
                event.preventDefault();
                return;
            }

            ctx.allowModalClose = true;
        });

        ctx.modalEl.addEventListener('hidden.bs.modal', () => {
            releaseModalFocus();
            ctx.allowModalClose = false;
            ctx.modalBaselineSnapshot = '';
            ctx.lineGridEditing = false;
            ctx.pendingLineGridInitAfterShow = false;
            ctx.modalShownResolvers = [];
            ctx.hideFloatingLineHeader();
            ctx.unbindLineDateInputFormatter();
            ctx.unbindLineDateEscHandler();
            ctx.closeGridDatePicker();
            ctx.transactionDatePicker?.close?.();
        });

        ctx.fileListEl?.addEventListener('click', (event) => {
            const button = event.target.closest('.btn-delete-transaction-file');
            if (!button) return;

            if (button.dataset.id) {
                ctx.markFileDeleted(button.dataset.id || '');
                return;
            }

            if (button.dataset.tempId) {
                ctx.removePendingFile(button.dataset.tempId || '');
            }
        });

        ctx.form.addEventListener('submit', (event) => {
            event.preventDefault();
            void ctx.saveTransaction();
        });

        ctx.deleteBtn?.addEventListener('click', () => {
            const id = document.getElementById('transaction_id')?.value || '';
            if (id && window.confirm('거래를 삭제하시겠습니까?')) {
                void ctx.deleteTransaction(id);
            }
        });

        ctx.unitCodeSelectEl?.addEventListener('change', ctx.applyPendingUnitSelection);

        document.addEventListener('trash:changed', (event) => {
            if (event.detail?.type === 'transaction') ctx.reloadTable();
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
                        <dt class="col-4">거래일</dt>
                        <dd class="col-8">${ctx.escapeHtml(row.transaction_date || '-')}</dd>
                        <dt class="col-4">거래처</dt>
                        <dd class="col-8">${ctx.escapeHtml(row.client_name || '-')}</dd>
                        <dt class="col-4">거래처</dt>
                        <dd class="col-8">${ctx.escapeHtml(row.project_name || '-')}</dd>
                        <dt class="col-4">프로젝트</dt>
                        <dd class="col-8">${ctx.escapeHtml(row.description || '-')}</dd>
                        <dt class="col-4">적요</dt>
                        <dd class="col-8">${ctx.escapeHtml(ctx.formatAmount(row.final_amount || row.transaction_final_amount || 0))}</dd>
                        <dt class="col-4">금액</dt>
                        <dd class="col-8">${ctx.escapeHtml(row.deleted_at || '-')}</dd>
                        <dt class="col-4">삭제자</dt>
                        <dd class="col-8">${ctx.escapeHtml(actorDisplay(row, 'deleted_by'))}</dd>
                    </dl>
                </div>
            `;
        });
    }

    function boot() {
        ctx.initTransactionTable?.();
        ctx.bindEvents?.();
        ctx.syncConditionalPanels?.();
        void ctx.initModalControls?.().catch(() => {
            ctx.notify('error', '거래 입력 기능을 초기화하지 못했습니다.');
        });
        ctx.refreshLineGridDimensions?.();
    }

    Object.assign(ctx, { bindEvents, boot });
    return ctx;
}
