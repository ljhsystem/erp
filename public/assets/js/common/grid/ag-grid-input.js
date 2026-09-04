import { createAgGridAdapter } from './ag-grid-adapter.js';

function ensureToolbar(host, adapter, config = {}) {
    if (!config.addButtonSelector || !config.addRow) return;
    const container = config.toolbarContainer
        || host.closest(config.toolbarContainerSelector || '.erp-grid-input-wrap')
        || host.parentElement;
    if (!container) return;

    let toolbar = container.querySelector(config.toolbarSelector || '.erp-grid-input-toolbar');
    if (!toolbar) {
        toolbar = document.createElement('div');
        toolbar.className = config.toolbarClass || 'erp-grid-input-toolbar d-flex justify-content-end mb-2';
        toolbar.innerHTML = config.addButtonHtml || '<button type="button" class="btn btn-outline-primary btn-sm erp-grid-add-row">+추가</button>';
        container.insertBefore(toolbar, config.insertBefore ? container.querySelector(config.insertBefore) : host);
    }

    if (container.__erpGridInputToolbarHandler) {
        container.removeEventListener('click', container.__erpGridInputToolbarHandler);
    }
    container.__erpGridInputToolbarHandler = (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target?.closest(config.addButtonSelector)) return;
        const rowIndex = adapter.countRows();
        adapter.addRow(config.addRow());
        if (config.focusColumnAfterAdd) {
            if (config.startEditingAfterAdd === true) {
                adapter.startEditing(rowIndex, config.focusColumnAfterAdd);
            } else {
                adapter.focusCell(rowIndex, config.focusColumnAfterAdd);
            }
        }
        config.onChanged?.(adapter.getData(), null, adapter);
    };
    container.addEventListener('click', container.__erpGridInputToolbarHandler);
}

export function createAgGridInputAdapter(host, config = {}) {
    const adapter = createAgGridAdapter(host, {
        ...config,
        onCellClicked(event, currentAdapter) {
            config.onCellClicked?.(event, currentAdapter);
            if (!config.deleteColumnField || event.colDef?.field !== config.deleteColumnField) return;
            const target = event.event?.target instanceof Element ? event.event.target : null;
            if (target && !target.closest(config.deleteButtonSelector || 'button')) return;
            if (config.onDeleteRow?.(event, currentAdapter) === true) {
                return;
            }
            currentAdapter.removeRows([event.rowIndex]);
            config.onChanged?.(currentAdapter.getData(), event, currentAdapter);
        },
        onCellValueChanged(event, currentAdapter) {
            config.onCellValueChanged?.(event, currentAdapter);
            config.onChanged?.(currentAdapter.getData(), event, currentAdapter);
        },
        onColumnHeaderClicked(event, currentAdapter) {
            config.onColumnHeaderClicked?.(event, currentAdapter);
            if (!config.addHeaderColumnField || event.column?.getColId?.() !== config.addHeaderColumnField || !config.addRow) return;
            const rowIndex = currentAdapter.countRows();
            currentAdapter.addRow(config.addRow());
            if (config.focusColumnAfterAdd) {
                if (config.startEditingAfterAdd === true) {
                    currentAdapter.startEditing(rowIndex, config.focusColumnAfterAdd);
                } else {
                    currentAdapter.focusCell(rowIndex, config.focusColumnAfterAdd);
                }
            }
            config.onChanged?.(currentAdapter.getData(), event, currentAdapter);
        },
        onRowDragEnd(event, currentAdapter) {
            config.onRowDragEnd?.(event, currentAdapter);
            config.onChanged?.(currentAdapter.getData(), event, currentAdapter);
        },
    });
    ensureToolbar(host, adapter, config);
    const destroyAdapter = adapter.destroy.bind(adapter);
    adapter.destroy = () => {
        const container = config.toolbarContainer
            || host.closest(config.toolbarContainerSelector || '.erp-grid-input-wrap')
            || host.parentElement;
        if (container?.__erpGridInputToolbarHandler) {
            container.removeEventListener('click', container.__erpGridInputToolbarHandler);
            delete container.__erpGridInputToolbarHandler;
        }
        destroyAdapter();
    };
    return adapter;
}
