import { createExcelManagerSettingsCore } from '../../../../../components/excel-manager/index.js';

export async function initExcelDataset(api) {
    const excelForm = document.getElementById('accountExcelForm');
    if (!excelForm) return;

    excelForm.dataset.templateUrl = api.EXCEL_TEMPLATE;
    excelForm.dataset.downloadUrl = api.EXCEL_DOWNLOAD;
    excelForm.dataset.uploadUrl = api.EXCEL_UPLOAD;

    await createExcelManagerSettingsCore({
        domain: 'bank-account',
        userSettingPageKey: 'bank-account',
        formSelector: '#accountExcelForm',
        metaDomain: 'bank-account',
    });
}

export function bindExcelEvents(getTable) {
    document.addEventListener('excel:uploaded', () => {
        getTable()?.ajax.reload(null, false);
    });
}
