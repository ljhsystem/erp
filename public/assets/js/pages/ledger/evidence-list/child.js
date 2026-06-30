export function createEvidenceChildModule({
    state,
    API,
    notify,
    updateSummary,
    refreshDataTableLayout,
    escapeHtml,
    mapped,
    splitModalColumns,
    requirementStar,
    splitCellHtml,
    bindSplitModalInputs,
    bindSplitModalHorizontalWheel,
    splitMissingRequiredForRow,
    splitChildFromRow,
    businessProjectRuleMessage,
    rowDataFromTableNode,
    openProcessingSplitModal,
    openEvidenceEditModalLatest,
}) {
    function processingChildrenForParent(row = {}) {
        const parentId = String(row.processing_item_id || '').trim();
        if (!parentId) return [];
        return state.lastRows.filter((item) => (
            item?.processing_is_child
            && String(item.processing_parent_item_id || '').trim() === parentId
        ));
    }

    function ensureChildEditModal() {
        let modal = document.getElementById('evidenceProcessingChildEditModal');
        if (modal) return modal;
        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="evidenceProcessingChildEditModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content evidence-split-modal">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title">하위 증빙 수정</h5>
                                <div class="small text-muted evidence-child-edit-subtitle"></div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive evidence-split-scroll">
                                <table class="table table-sm align-middle evidence-split-table mb-0">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-sm evidence-child-edit-save">저장</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        modal = document.getElementById('evidenceProcessingChildEditModal');
        modal.querySelector('.evidence-child-edit-save')?.addEventListener('click', () => saveProcessingChildEdit(modal));
        bindSplitModalHorizontalWheel(modal);
        return modal;
    }

    function renderChildEditModalHeader(modal) {
        const headerRow = modal.querySelector('.evidence-split-table thead tr');
        if (!headerRow) return;
        headerRow.innerHTML = `
            <th style="width:72px;">순번</th>
            ${splitModalColumns().map((column) => `<th>${escapeHtml(column.title)}${requirementStar(column.column || {})}</th>`).join('')}
        `;
    }

    function childEditRowHtml(child = {}) {
        const columns = splitModalColumns();
        const payload = mapped(child);
        const sortNo = child.processing_display_path || child.sort_no || '';
        return `
            <tr data-id="${escapeHtml(child.processing_item_id || child.id || '')}" data-split-role="child">
                <td class="text-center"><span class="evidence-split-sort">${escapeHtml(sortNo)}</span></td>
                ${columns.map((column) => splitCellHtml(column, child, payload)).join('')}
            </tr>
        `;
    }

    function openProcessingChildEditModal(row = {}) {
        const modal = ensureChildEditModal();
        modal.dataset.processingItemId = row.processing_item_id || row.id || '';
        const subtitle = modal.querySelector('.evidence-child-edit-subtitle');
        if (subtitle) {
            subtitle.textContent = `순번 ${row.sort_no || row.processing_display_path || '-'}`;
        }
        renderChildEditModalHeader(modal);
        const tbody = modal.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = childEditRowHtml(row);
        }
        bindSplitModalInputs(modal);
        bootstrap.Modal.getOrCreateInstance(modal, { focus: false }).show();
    }

    async function saveProcessingChildEdit(modal) {
        const row = modal.querySelector('tbody tr[data-split-role="child"]');
        if (!row) return;
        const missing = splitMissingRequiredForRow(row, 0);
        if (missing.length > 0) {
            notify('warning', `필수 항목이 비어 있습니다. 다음 값을 확인해 주세요: ${missing.slice(0, 5).join(', ')}`);
            return;
        }
        const child = splitChildFromRow(row, 0);
        const ruleMessage = businessProjectRuleMessage(child);
        if (ruleMessage !== '') {
            notify('warning', ruleMessage);
            return;
        }
        const response = await fetch(API.updateProcessingChild, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                processing_item_id: modal.dataset.processingItemId || child.id || '',
                child,
            }),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            notify('error', json.message || '하위 증빙 저장 중 오류가 발생했습니다.');
            return;
        }
        notify('success', json.message || '하위 증빙 저장이 완료되었습니다.');
        bootstrap.Modal.getInstance(modal)?.hide();
        state.table?.ajax.reload(() => {
            updateSummary(state.lastRows);
            refreshDataTableLayout(state.table);
            window.setTimeout(() => refreshDataTableLayout(state.table), 150);
        }, false);
    }

    async function deleteProcessingChild(button) {
        const processingItemId = String(button?.dataset.processingItemId || '').trim();
        if (!processingItemId) return;
        if (!window.confirm('하위 증빙을 삭제하시겠습니까? 삭제 후에는 되돌릴 수 없습니다.')) {
            return;
        }

        const response = await fetch(API.deleteProcessingChild, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ processing_item_id: processingItemId }),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok || json.success === false) {
            throw new Error(json.message || '하위 증빙 삭제 중 오류가 발생했습니다.');
        }
        notify('success', json.message || '하위 증빙이 삭제되었습니다.');
        state.table?.ajax.reload(() => updateSummary(state.lastRows), false);
    }

    function bindProcessingChildEvents(evidenceTableEl) {
        evidenceTableEl?.addEventListener('dblclick', (event) => {
            if (event.target.closest('a, button, input, select, textarea, .dt-select-column')) return;
            const rowNode = event.target.closest('tr');
            if (!rowNode || !state.table) return;
            const row = rowDataFromTableNode(rowNode);
            if (row?.processing_is_child) {
                openProcessingChildEditModal(row);
                return;
            }
            void openEvidenceEditModalLatest(row);
        });

        evidenceTableEl?.addEventListener('mouseover', (event) => {
            const cell = event.target.closest('td, th');
            const rowNode = cell?.closest('tr.processing-child-display-row');
            if (!cell || !rowNode || !evidenceTableEl.contains(rowNode)) return;
            const wrapper = evidenceTableEl.closest('.dataTables_wrapper');
            const columnIndex = Array.from(rowNode.children).indexOf(cell);
            wrapper?.querySelectorAll('tbody tr').forEach((tr) => tr.classList.remove('row-highlight'));
            rowNode.classList.add('row-highlight');
            wrapper?.querySelectorAll('td, th').forEach((node) => node.classList.remove('col-highlight'));
            if (columnIndex >= 0) {
                wrapper?.querySelectorAll('tbody tr').forEach((tr) => tr.children[columnIndex]?.classList.add('col-highlight'));
                wrapper?.querySelector(`.dataTables_scrollHead th:nth-child(${columnIndex + 1})`)?.classList.add('col-highlight');
            }
        });

        evidenceTableEl?.addEventListener('click', (event) => {
            const deleteChildButton = event.target.closest('.evidence-delete-child-row-btn');
            if (deleteChildButton) {
                void deleteProcessingChild(deleteChildButton).catch((error) => notify('error', error.message));
                return;
            }
            const childButton = event.target.closest('.evidence-add-child-row-btn');
            if (childButton && state.table) {
                const rowNode = childButton.closest('tr');
                const row = rowDataFromTableNode(rowNode);
                openProcessingSplitModal(row || {});
                return;
            }
            const button = event.target.closest('.evidence-edit-row-btn');
            if (!button || !state.table) return;
            const rowNode = button.closest('tr');
            const row = rowDataFromTableNode(rowNode);
            if (row?.processing_is_child) {
                openProcessingChildEditModal(row);
                return;
            }
            void openEvidenceEditModalLatest(row);
        });
    }

    return {
        processingChildrenForParent,
        ensureChildEditModal,
        renderChildEditModalHeader,
        childEditRowHtml,
        openProcessingChildEditModal,
        saveProcessingChildEdit,
        deleteProcessingChild,
        bindProcessingChildEvents,
    };
}
