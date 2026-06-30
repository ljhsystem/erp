import { createExcelManagerSettingsCore } from '../../../../../components/excel-manager/index.js';

export function initExcelDataset(api) {
    const form = document.getElementById('project-excel-upload-form');
    if (!form) return;

    form.dataset.templateUrl = api.EXCEL_TEMPLATE;
    form.dataset.downloadUrl = api.EXCEL_DOWNLOAD;
    form.dataset.uploadUrl = api.EXCEL_UPLOAD;

    createExcelManagerSettingsCore({
        domain: 'project',
        formSelector: '#project-excel-upload-form',
        tableSettingsStorageKey: 'datatable.settings.dashboard.settings.base-info.project.project-table.v1',
        tableSettingsMetaDomain: 'project',
    });
}

export function bindExcelEvents(getTable) {
    document.addEventListener('excel:uploaded', () => {
        const table = typeof getTable === 'function' ? getTable() : null;
        if (table) table.ajax.reload(null, false);
    });
}
