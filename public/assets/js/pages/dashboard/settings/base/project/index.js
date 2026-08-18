import '/public/assets/js/common/core/AppAjax.js';
import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { formatDateDisplay, formatAmount, unformatAmount } from '/public/assets/js/common/format.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { openClientQuickCreate } from '/public/assets/js/pages/dashboard/settings/base/client.js';
import { initCodeSelectControls, getCodeName } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { API, DATE_OPTIONS } from './api.js';
import { initExcelDataset, bindExcelEvents } from './excel.js';
import { bindTrashEvents } from './trash.js';
import { createProjectFormModule } from './form.js';
import { createProjectModalModule } from './modal.js';
import { createProjectTableModule } from './table.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

const state = {
    projectTable: null,
    projectModal: null,
    excelModal: null,
};

const formModule = createProjectFormModule({
    AdminPicker,
    initCodeSelectControls,
    formatAmount,
    unformatAmount,
});

const modalModule = createProjectModalModule({
    AdminPicker,
    API,
    openClientQuickCreate,
    formatDateDisplay,
    formatAmount,
    formModule,
    state,
});

const tableModule = createProjectTableModule({
    createDataTable,
    bindTableHighlight,
    bindRowReorder,
    SearchForm,
    formatDateDisplay,
    formatAmount,
    getCodeName,
    API,
    DATE_OPTIONS,
    formModule,
    modalModule,
    state,
});

async function initProjectPage($) {
    modalModule.initModal();
    formModule.initAdminDatePicker();
    await tableModule.initDataTable();
    state.projectTable?.one('draw.dt', () => {
        void formModule.preloadProjectModalControls().then(() => {
            formModule.disconnectProjectClientTypeCodeSelect(document.getElementById('projectModal'));
            state.projectTable?.rows().invalidate('data').draw(false);
        });
    });
    state.projectTable?.one('draw.dt', () => { void initExcelDataset(API); });
    formModule.initExternal();
    formModule.initProjectValueInputs(API);
    tableModule.bindTableEvents($);
    modalModule.bindModalEvents($, () => state.projectTable);
    formModule.bindAdminDateInputs();
    bindExcelEvents(() => state.projectTable);
    bindTrashEvents({
        getTable: () => state.projectTable,
        columnMap: tableModule.PROJECT_COLUMN_MAP,
        formatDateDisplay,
        formatAmount,
    });
    formModule.bindGlobalEvents();
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.jQuery) {
        console.error('jQuery not loaded');
        return;
    }
    void initProjectPage(window.jQuery).catch(() => {
        window.AppCore?.notify?.('error', '프로젝트 목록을 불러오는 중 오류가 발생했습니다.');
    });
});
