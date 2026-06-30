import { createExcelManagerSettingsCore } from '../../../../../components/excel-manager/index.js';

export function initExcelDataset(api) {
    const excelForm = document.getElementById('accountExcelForm');
    if (!excelForm) return;

    excelForm.dataset.templateUrl = api.EXCEL_TEMPLATE;
    excelForm.dataset.downloadUrl = api.EXCEL_DOWNLOAD;
    excelForm.dataset.uploadUrl = api.EXCEL_UPLOAD;

    createExcelManagerSettingsCore({
        domain: 'bank-account',
        formSelector: '#accountExcelForm',
        tableSettingsStorageKey: 'datatable.settings.dashboard.settings.base-info.bank-account.account-table.v1',
        tableSettingsMetaDomain: 'bank-account',
    });
}

export function bindExcelEvents(getTable) {
    document.addEventListener('excel:uploaded', () => {
        getTable()?.ajax.reload(null, false);
    });
}
