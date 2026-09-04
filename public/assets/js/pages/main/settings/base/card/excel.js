import { createExcelManagerSettingsCore } from '../../../../../components/excel-manager/index.js';

export async function initExcelDataset(api) {
    const excelForm = document.getElementById('cardExcelForm');
    if (!excelForm) return;

    excelForm.dataset.templateUrl = api.EXCEL_TEMPLATE;
    excelForm.dataset.downloadUrl = api.EXCEL_DOWNLOAD;
    excelForm.dataset.uploadUrl = api.EXCEL_UPLOAD;

    await createExcelManagerSettingsCore({
        domain: 'card',
        userSettingPageKey: 'card',
        formSelector: '#cardExcelForm',
        metaDomain: 'card',
    });
}

export function bindExcelEvents(getTable) {
    document.addEventListener('excel:uploaded', () => {
        getTable()?.ajax.reload(null, false);
    });
}
