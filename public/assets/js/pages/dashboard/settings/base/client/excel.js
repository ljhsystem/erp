import { createExcelManagerSettingsCore } from '../../../../../components/excel-manager/index.js';

export async function initExcelDataset(api) {
    const form = document.getElementById('clientExcelForm');
    if (!form) return;

    form.dataset.templateUrl = api.EXCEL_TEMPLATE;
    form.dataset.downloadUrl = api.EXCEL_DOWNLOAD;
    form.dataset.uploadUrl = api.EXCEL_UPLOAD;

    await createExcelManagerSettingsCore({
        domain: 'client',
        userSettingPageKey: 'client',
        formSelector: '#clientExcelForm',
        metaDomain: 'client',
    });
}

export function bindExcelEvents(getClientTable) {
    document.addEventListener('excel:uploaded', () => {
        const table = typeof getClientTable === 'function' ? getClientTable() : null;
        if (table) {
            table.ajax.reload(null, false);
        }
    });
}
