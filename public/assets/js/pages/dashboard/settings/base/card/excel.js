import { createExcelManagerSettingsCore } from '../../../../../components/excel-manager/index.js';

export function initExcelDataset(api) {
    const excelForm = document.getElementById('cardExcelForm');
    if (!excelForm) return;

    excelForm.dataset.templateUrl = api.EXCEL_TEMPLATE;
    excelForm.dataset.downloadUrl = api.EXCEL_DOWNLOAD;
    excelForm.dataset.uploadUrl = api.EXCEL_UPLOAD;

    createExcelManagerSettingsCore({
        domain: 'card',
        formSelector: '#cardExcelForm',
        tableSettingsStorageKey: 'datatable.settings.dashboard.settings.base-info.card.card-table.v1',
        tableSettingsMetaDomain: 'card',
    });
}

export function bindExcelEvents(getTable) {
    document.addEventListener('excel:uploaded', () => {
        getTable()?.ajax.reload(null, false);
    });
}
