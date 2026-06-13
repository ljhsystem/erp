import { createExcelManagerSettingsCore } from '../../../../../components/excel-manager/index.js';

export function createWorkTeamExcelModule({ reloadTable }) {
    function initExcelDataset() {
        createExcelManagerSettingsCore({
            domain: 'work-team',
            formSelector: '#workTeamExcelForm',
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
