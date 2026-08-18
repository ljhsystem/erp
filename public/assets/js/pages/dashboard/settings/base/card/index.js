import { AdminPicker } from '/public/assets/js/common/picker/admin_picker.js';
import { createDataTable, bindTableHighlight } from '/public/assets/js/common/table/data-table.js';
import { bindRowReorder } from '/public/assets/js/common/row-reorder.js';
import { SearchForm } from '/public/assets/js/components/search-form.js';
import { openClientQuickCreate } from '/public/assets/js/pages/dashboard/settings/base/client.js';
import { confirmDialog } from '/public/assets/js/common/confirm-dialog.js';
import { formatAmount, formatDateDisplay, initNumberInputs, parseNumber } from '/public/assets/js/common/format.js';
import { API, CARD_COLUMN_MAP, DATE_OPTIONS } from './api.js';
import { initExcelDataset, bindExcelEvents } from './excel.js';
import { bindTrashEvents } from './trash.js';
import { createCardFormModule } from './form.js';
import { createCardModalModule } from './modal.js';
import { createCardTableModule } from './table.js';
import '/public/assets/js/components/excel-manager.js';
import '/public/assets/js/components/trash-manager.js';
import '/public/assets/js/common/core/AppAjax.js';
import '/public/assets/js/common/modal-card-collapse.js';

window.AdminPicker = AdminPicker;

const state = {
    cardTable: null,
    cardModal: null,
    excelModal: null,
};

const formModule = createCardFormModule({
    AdminPicker,
    initNumberInputs,
    openClientQuickCreate,
    API,
    confirmDialog,
});

const modalModule = createCardModalModule({
    API,
    AdminPicker,
    formatAmount,
    formatDateDisplay,
    formModule,
    state,
    confirmDialog,
});

const tableModule = createCardTableModule({
    createDataTable,
    bindTableHighlight,
    bindRowReorder,
    SearchForm,
    formatAmount,
    API,
    CARD_COLUMN_MAP,
    DATE_OPTIONS,
    formModule,
    modalModule,
    state,
});

async function initCardPage($) {
    modalModule.initModal();
    initNumberInputs('#cardForm .number-input');
    formModule.initAdminDatePicker();
    formModule.bindAdminDateInputs();
    formModule.bindDateIconPicker();
    formModule.initCardImageUpload();
    await tableModule.initDataTable();
    void initExcelDataset(API);
    tableModule.bindTableEvents($);
    modalModule.bindModalEvents($, () => state.cardTable, parseNumber);
    formModule.bindUIEvents();
    bindExcelEvents(() => state.cardTable);
    bindTrashEvents({
        getTable: () => state.cardTable,
        columnMap: CARD_COLUMN_MAP,
        formatAmount,
    });
    formModule.bindGlobalEvents();
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.jQuery) {
        console.error('jQuery not loaded');
        return;
    }

    initCardPage(window.jQuery);
});
