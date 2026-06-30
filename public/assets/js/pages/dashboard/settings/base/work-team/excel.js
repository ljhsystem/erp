import { createExcelManagerSettingsCore } from '../../../../../components/excel-manager/index.js';

export function createWorkTeamExcelModule({ reloadTable }) {
    function initExcelDataset() {
        createExcelManagerSettingsCore({
            domain: 'work-team',
            formSelector: '#workTeamExcelForm',
            tableSettingsStorageKey: 'datatable.settings.dashboard.settings.base-info.work-team.work-team-table.v1',
            tableSettingsMetaDomain: 'work-team',
        });
    }

    function bindExcelEvents() {
        document.addEventListener('excel:uploaded', () => {
            reloadTable();
        });
    }

    return {
        initExcelDataset,
        bindExcelEvents,
    };
}
