import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import * as NumberFormat from '/public/assets/js/common/format.js';
import { initCodeSelectControls, getCodeName } from '/public/assets/js/pages/dashboard/settings/system/code-select.js';
import { API, ACCOUNT_COLUMN_MAP, DATE_OPTIONS } from './api.js';
import { initExcelDataset, bindExcelEvents } from './excel.js';
import { bindTrashEvents } from './trash.js';
import { createBankAccountFormModule } from './form.js';
import { createBankAccountModalModule } from './modal.js';
import { createBankAccountTableModule } from './table.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';

window.AdminPicker = AdminPicker;

const state = {
    accountTable: null,
    accountModal: null,
    excelModal: null,
};

void NumberFormat.loadBankAccountFormatRegistry?.();

const formModule = createBankAccountFormModule({
    AdminPicker,
    initCodeSelectControls,
    NumberFormat,
});

const modalModule = createBankAccountModalModule({
    API,
    formModule,
    formatAccountNumber: NumberFormat.formatAccountNumber,
    state,
});

const tableModule = createBankAccountTableModule({
    createDataTable,
    bindTableHighlight,
    bindRowReorder,
    SearchForm,
    API,
    DATE_OPTIONS,
    ACCOUNT_COLUMN_MAP,
    getCodeName,
    formatAccountNumber: NumberFormat.formatAccountNumber,
    formModule,
    modalModule,
    state,
});

async function initAccountPage($) {
    modalModule.initModal();
    formModule.initAdminDatePicker();
    formModule.initBankBookUpload();
    initExcelDataset(API);
    void formModule.preloadAccountModalControls();
    tableModule.initDataTable();
    tableModule.bindTableEvents($);
    modalModule.bindModalEvents($, () => state.accountTable);
    formModule.bindAdminDateInputs();
    formModule.bindUIEvents();
    bindExcelEvents(() => state.accountTable);
    bindTrashEvents({
        getTable: () => state.accountTable,
        columnMap: ACCOUNT_COLUMN_MAP,
        escapeHtml: formModule.escapeHtml,
        getCodeName,
        formatAccountNumber: NumberFormat.formatAccountNumber,
    });
    formModule.bindGlobalEvents();
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.jQuery) {
        console.error('jQuery not loaded');
        return;
    }

    initAccountPage(window.jQuery);
});
