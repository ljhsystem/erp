import { createExcelManagerSettingsCore } from '../../../../../components/excel-manager/index.js';

export function createWorkTeamExcelModule({ reloadTable }) {
    function initExcelDataset() {
        createExcelManagerSettingsCore({
            domain: 'work-team',
            userSettingPageKey: 'work-team',
            formSelector: '#workTeamExcelForm',
            metaDomain: 'work-team',
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
